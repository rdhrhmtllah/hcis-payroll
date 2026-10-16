<template>
    <div class="container-fluid px-0 team-management-container">
        <!-- Header Section -->
        <div class="row d-flex align-items-center justify-content-center mb-3 fade-in">
            <div class="mb-3 w-100">
                <HeaderComponent
                    title="Team Management"
                    subtitle="Kelola anggota tim dan struktur organisasi"
                    :dayNight="dayNight"
                    :formattedDate="formatDateString(currentDate)"
                    buttonText="Tambah Anggota Tim"
                    @button-click="showAddMemberModal"
                />
            </div>
        </div>

        <!-- Quick Stats -->
        <quick-stat :stats="quickStatField" />

        <!-- Main Content Tabs -->
        <ApprovalTabs
            :tabs="mainTabs"
            :activeTab="activeTab"
            @tab-change="handleTabChange"
        >
            <!-- Tab 1: Team Overview -->
            <template #overview>
                <div class="team-overview-content">
                    <!-- Team Actions -->
                    <div class="team-actions-bar mb-4">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-6">
                                <div class="search-box">
                                    <i class="bi bi-search"></i>
                                    <input
                                        type="text"
                                        v-model="teamFilters.search"
                                        placeholder="Cari karyawan..."
                                        class="search-input"
                                    />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="bulk-actions-header">
                                    <button
                                        class="btn-modern btn-primary"
                                        @click="showAddMemberModal"
                                    >
                                        <i class="bi bi-person-plus me-2"></i>
                                        Tambah Anggota Tim
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Team Filters -->
                    <div class="team-filters-bar mb-4">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-3">
                                <div class="search-box">
                                    <i class="bi bi-search"></i>
                                    <input
                                        type="text"
                                        v-model="teamFilters.search"
                                        placeholder="Cari nama, NIK, posisi..."
                                        class="search-input"
                                    />
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select
                                    v-model="teamFilters.department"
                                    class="filter-select"
                                >
                                    <option value="">Semua Departemen</option>
                                    <option
                                        v-for="dept in uniqueDepartments"
                                        :key="dept"
                                        :value="dept"
                                    >
                                        {{ dept }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select
                                    v-model="teamFilters.position"
                                    class="filter-select"
                                >
                                    <option value="">Semua Jabatan</option>
                                    <option
                                        v-for="pos in uniquePositions"
                                        :key="pos"
                                        :value="pos"
                                    >
                                        {{ pos }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select
                                    v-model="teamFilters.team"
                                    class="filter-select"
                                >
                                    <option value="">Semua Tim</option>
                                    <option
                                        v-for="team in uniqueTeams"
                                        :key="team"
                                        :value="team"
                                    >
                                        {{ team }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <div class="filter-actions">
                                    <button
                                        @click="resetFilters"
                                        class="btn-filter-reset"
                                    >
                                        <i class="bi bi-arrow-clockwise"></i>
                                        Reset
                                    </button>
                                    <span class="filter-count">
                                        {{ filteredTeamMembers.length }} hasil
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Team Structure Visualization -->
                    <div class="team-structure-section mb-4">
                        <div class="section-header mb-3">
                            <h4 class="section-title">Struktur Tim</h4>
                            <p class="section-subtitle">
                                Visualisasi hierarki dan distribusi anggota tim
                            </p>
                        </div>
                        <div class="structure-visualization">
                            <div class="leaders-container">
                                <div
                                    v-for="leader in teamLeaders"
                                    :key="leader.id"
                                    class="leader-card"
                                >
                                    <div class="leader-avatar">
                                        {{ getInitials(leader.name) }}
                                    </div>
                                    <div class="leader-info">
                                        <h6>{{ leader.name }}</h6>
                                        <p class="leader-position">
                                            {{ leader.position }}
                                        </p>
                                        <div class="team-count">
                                            <i class="bi bi-people"></i>
                                            <span>
                                                {{ getTeamMemberCount(leader.id) }} anggota
                                            </span>
                                        </div>
                                    </div>
                                    <div class="leader-actions">
                                        <button
                                            class="btn-action btn-view"
                                            @click="viewLeaderTeam(leader)"
                                            title="Lihat Tim"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button
                                            class="btn-action btn-edit"
                                            @click="editLeaderTeam(leader)"
                                            title="Kelola Tim"
                                        >
                                            <i class="bi bi-gear"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Team Members Table -->
                    <div v-if="!loading.team">
                        <div v-if="filteredTeamMembers.length > 0">
                            <!-- Desktop Table View -->
                            <div class="d-none d-lg-block">
                                <div class="table-responsive-wrapper">
                                    <table class="modern-table">
                                        <thead>
                                            <tr>
                                                <th class="header-cell">Nama Karyawan</th>
                                                <th class="header-cell">Posisi</th>
                                                <th class="header-cell">Departemen</th>
                                                <th class="header-cell">Tim</th>
                                                <th class="header-cell">Leader</th>
                                                <th class="header-cell">Tanggal Bergabung</th>
                                                <th class="header-cell">Status</th>
                                                <th class="header-cell">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="member in paginatedTeamMembers"
                                                :key="member.id"
                                                class="table-row"
                                            >
                                                <td>
                                                    <div class="employee-cell">
                                                        <div class="avatar-sm">
                                                            {{ getInitials(member.name) }}
                                                        </div>
                                                        <div class="employee-details">
                                                            <div class="employee-name">
                                                                {{ member.name }}
                                                            </div>
                                                            <div class="employee-code">
                                                                {{ member.employeeId }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{{ member.position }}</td>
                                                <td>{{ member.department }}</td>
                                                <td>
                                                    <span class="team-badge">
                                                        {{ member.team || "Belum Ditugaskan" }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span v-if="member.leaderId" class="leader-info">
                                                        {{ getLeaderName(member.leaderId) }}
                                                    </span>
                                                    <span v-else class="text-muted">-</span>
                                                </td>
                                                <td>
                                                    {{ formatDate(member.joinedAt) }}
                                                </td>
                                                <td>
                                                    <span :class="`status-badge status-${member.status}`">
                                                        {{ getStatusText(member.status) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-cell">
                                                        <button
                                                            class="btn-action btn-view"
                                                            @click="viewMemberDetails(member)"
                                                            title="Lihat Detail"
                                                        >
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button
                                                            class="btn-action btn-edit"
                                                            @click="editMember(member)"
                                                            title="Edit"
                                                        >
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button
                                                            class="btn-action btn-transfer"
                                                            @click="showTransferModal(member)"
                                                            title="Pindah Tim"
                                                        >
                                                            <i class="bi bi-arrow-left-right"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Mobile Card View -->
                            <div class="d-block d-lg-none">
                                <div class="team-members-grid">
                                    <div
                                        v-for="member in paginatedTeamMembers"
                                        :key="member.id"
                                        class="team-member-card"
                                    >
                                        <div class="member-header">
                                            <div class="member-avatar">
                                                {{ getInitials(member.name) }}
                                            </div>
                                            <div class="member-info">
                                                <h6 class="member-name">{{ member.name }}</h6>
                                                <p class="member-position">{{ member.position }}</p>
                                                <div class="member-meta">
                                                    <span class="member-id">{{ member.employeeId }}</span>
                                                    <span class="member-department">{{ member.department }}</span>
                                                </div>
                                            </div>
                                            <div class="member-status">
                                                <span :class="`status-badge status-${member.status}`">
                                                    {{ getStatusText(member.status) }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="member-team-info">
                                            <div class="team-details">
                                                <div class="team-item">
                                                    <i class="bi bi-people"></i>
                                                    <span>{{ member.team || "Belum Ditugaskan" }}</span>
                                                </div>
                                                <div class="team-item" v-if="member.leaderId">
                                                    <i class="bi bi-person-badge"></i>
                                                    <span>{{ getLeaderName(member.leaderId) }}</span>
                                                </div>
                                                <div class="team-item">
                                                    <i class="bi bi-calendar"></i>
                                                    <span>{{ formatDate(member.joinedAt) }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="member-actions">
                                            <button
                                                class="btn-action btn-view"
                                                @click="viewMemberDetails(member)"
                                                title="Lihat Detail"
                                            >
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button
                                                class="btn-action btn-edit"
                                                @click="editMember(member)"
                                                title="Edit"
                                            >
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button
                                                class="btn-action btn-transfer"
                                                @click="showTransferModal(member)"
                                                title="Pindah Tim"
                                            >
                                                <i class="bi bi-arrow-left-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pagination -->
                            <div class="pagination-container mt-4" v-if="filteredTeamMembers.length > itemsPerPage">
                                <div class="pagination-info">
                                    Menampilkan {{ pagination.overview.startItem }}-{{ pagination.overview.endItem }}
                                    dari {{ filteredTeamMembers.length }} data
                                </div>
                                <div class="pagination-controls">
                                    <button
                                        class="pagination-btn pagination-prev"
                                        @click="previousPage"
                                        :disabled="pagination.overview.currentPage === 1"
                                    >
                                        <i class="bi bi-chevron-left"></i>
                                    </button>

                                    <div class="pagination-pages">
                                        <button
                                            v-for="page in visiblePages"
                                            :key="page"
                                            class="pagination-page"
                                            :class="{
                                                active: page === pagination.overview.currentPage,
                                            }"
                                            @click="goToPage(page)"
                                        >
                                            {{ page }}
                                        </button>
                                    </div>

                                    <button
                                        class="pagination-btn pagination-next"
                                        @click="nextPage"
                                        :disabled="pagination.overview.currentPage === pagination.overview.totalPages"
                                    >
                                        <i class="bi bi-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="empty-state">
                            <div class="empty-icon mb-4">
                                <i class="bi bi-people"></i>
                            </div>
                            <h4>Tidak Ada Anggota Tim</h4>
                            <p>Belum ada anggota tim yang tersedia atau data tidak ditemukan</p>
                            <button class="btn-modern btn-primary mt-3" @click="showAddMemberModal">
                                <i class="bi bi-person-plus me-2"></i>
                                Tambah Anggota Tim
                            </button>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div v-else class="loading-state">
                        <div class="loading-spinner">
                            <i class="bi bi-arrow-repeat"></i>
                        </div>
                        <h4 class="loading-title">Memuat Data Team...</h4>
                        <p class="loading-description">Sedang mengambil data team</p>
                    </div>
                </div>
            </template>

            <!-- Tab 2: Team Assignment -->
            <template #assignment>
                <div class="team-assignment-content">
                    <!-- Assignment Header -->
                    <div class="assignment-header mb-4">
                        <div class="row align-items-center justify-content-between">
                            <div class="col-md-8">
                                <h4 class="section-title">Penugasan Tim</h4>
                                <p class="section-subtitle">
                                    Kelola penugasan anggota ke dalam tim leader
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <button class="btn-modern btn-primary" @click="showBulkAssignmentModal">
                                    <i class="bi bi-people-fill me-2"></i>
                                    Penugasan Massal
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Assignment Interface -->
                    <div class="assignment-interface">
                        <div class="row">
                            <!-- Available Members -->
                            <div class="col-md-5">
                                <div class="assignment-section">
                                    <div class="section-header-compact">
                                        <h5 class="section-title">Anggota Tersedia</h5>
                                        <p class="section-subtitle">
                                            Karyawan yang belum memiliki tim ({{ availableEmployees.length }})
                                        </p>
                                    </div>
                                    <div class="members-list">
                                        <div
                                            v-for="member in availableEmployees"
                                            :key="member.id"
                                            class="member-assignment-card"
                                            :class="{
                                                selected: isMemberSelected(member.id),
                                            }"
                                            @click="toggleMemberSelection(member.id)"
                                        >
                                            <div class="selection-checkbox">
                                                <input
                                                    type="checkbox"
                                                    :checked="isMemberSelected(member.id)"
                                                    @change="toggleMemberSelection(member.id)"
                                                />
                                            </div>
                                            <div class="member-avatar-sm">
                                                {{ getInitials(member.name) }}
                                            </div>
                                            <div class="member-details-sm">
                                                <h6>{{ member.name }}</h6>
                                                <p>{{ member.position }}</p>
                                                <span class="employee-code">{{ member.employeeId }}</span>
                                            </div>
                                        </div>
                                        <div v-if="availableEmployees.length === 0" class="empty-assignment-state">
                                            <i class="bi bi-check-circle"></i>
                                            <p>Semua anggota sudah memiliki tim</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Assignment Actions -->
                            <div class="col-md-2">
                                <div class="assignment-actions d-flex flex-column justify-content-center align-items-center h-100">
                                    <button
                                        class="btn-assign"
                                        @click="assignSelectedMembers"
                                        :disabled="selectedMembers.length === 0 || !selectedLeader"
                                        title="Assign ke Tim"
                                    >
                                        <i class="bi bi-arrow-right"></i>
                                        <span>Assign</span>
                                    </button>
                                    <button
                                        class="btn-unassign"
                                        @click="unassignSelectedMembers"
                                        :disabled="selectedMembers.length === 0"
                                        title="Hapus dari Tim"
                                    >
                                        <i class="bi bi-arrow-left"></i>
                                        <span>Unassign</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Team Leaders -->
                            <div class="col-md-5">
                                <div class="assignment-section">
                                    <div class="section-header-compact">
                                        <h5 class="section-title">Leader Tim</h5>
                                        <p class="section-subtitle">
                                            Pilih leader untuk menugaskan anggota
                                        </p>
                                    </div>
                                    <div class="leaders-list">
                                        <div
                                            v-for="leader in teamLeaders"
                                            :key="leader.id"
                                            class="leader-assignment-card"
                                            :class="{
                                                active: selectedLeader === leader.id,
                                            }"
                                            @click="selectLeader(leader.id)"
                                        >
                                            <div class="leader-avatar-sm">
                                                {{ getInitials(leader.name) }}
                                            </div>
                                            <div class="leader-details-sm">
                                                <h6>{{ leader.name }}</h6>
                                                <p>{{ leader.position }}</p>
                                                <div class="team-stats">
                                                    <span class="member-count">
                                                        {{ getTeamMemberCount(leader.id) }} anggota
                                                    </span>
                                                    <span class="team-name">Tim {{ leader.team }}</span>
                                                </div>
                                            </div>
                                            <div class="selection-indicator">
                                                <i class="bi bi-check-circle-fill" v-if="selectedLeader === leader.id"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Selected Leader Team -->
                                    <div v-if="selectedLeader" class="selected-team-section mt-4">
                                        <div class="section-header-compact">
                                            <h6 class="section-title">
                                                Tim {{ getLeaderName(selectedLeader) }}
                                            </h6>
                                            <p class="section-subtitle">
                                                Anggota yang sudah ditugaskan ({{ getLeaderTeamMembers(selectedLeader).length }})
                                            </p>
                                        </div>
                                        <div class="team-members-list">
                                            <div
                                                v-for="member in getLeaderTeamMembers(selectedLeader)"
                                                :key="member.id"
                                                class="team-member-item"
                                                :class="{
                                                    selected: isMemberSelected(member.id),
                                                }"
                                                @click="toggleMemberSelection(member.id)"
                                            >
                                                <div class="selection-checkbox">
                                                    <input
                                                        type="checkbox"
                                                        :checked="isMemberSelected(member.id)"
                                                        @change="toggleMemberSelection(member.id)"
                                                    />
                                                </div>
                                                <div class="member-avatar-xs">
                                                    {{ getInitials(member.name) }}
                                                </div>
                                                <div class="member-details-xs">
                                                    <span>{{ member.name }}</span>
                                                    <small>{{ member.position }}</small>
                                                </div>
                                                <button
                                                    class="btn-remove-member"
                                                    @click.stop="removeMemberFromTeam(member.id)"
                                                    title="Hapus dari tim"
                                                >
                                                    <i class="bi bi-x"></i>
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

            <!-- Tab 3: Team Analytics -->
            <template #analytics>
                <div class="team-analytics-content">
                    <!-- Analytics Header -->
                    <div class="analytics-header mb-4">
                        <h4 class="section-title">Analisis Tim</h4>
                        <p class="section-subtitle">
                            Statistik dan insights tentang struktur tim
                        </p>
                    </div>

                    <!-- Analytics Overview -->
                    <div class="analytics-overview mb-4">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="analytics-card">
                                    <div class="analytics-icon total-members">
                                        <i class="bi bi-people-fill"></i>
                                    </div>
                                    <div class="analytics-content">
                                        <h3>{{ teamMembers.length }}</h3>
                                        <p>Total Anggota</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="analytics-card">
                                    <div class="analytics-icon team-leaders">
                                        <i class="bi bi-person-badge"></i>
                                    </div>
                                    <div class="analytics-content">
                                        <h3>{{ teamLeaders.length }}</h3>
                                        <p>Leader Tim</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="analytics-card">
                                    <div class="analytics-icon available-members">
                                        <i class="bi bi-person-plus"></i>
                                    </div>
                                    <div class="analytics-content">
                                        <h3>{{ availableEmployees.length }}</h3>
                                        <p>Anggota Tersedia</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="analytics-card">
                                    <div class="analytics-icon avg-team-size">
                                        <i class="bi bi-graph-up"></i>
                                    </div>
                                    <div class="analytics-content">
                                        <h3>{{ calculateAverageTeamSize }}</h3>
                                        <p>Rata-rata Ukuran Tim</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Team Distribution Chart -->
                    <div class="team-distribution-section mb-4">
                        <div class="section-header mb-3">
                            <h5 class="section-title">Distribusi Anggota per Tim</h5>
                            <p class="section-subtitle">
                                Visualisasi jumlah anggota di setiap tim
                            </p>
                        </div>
                        <div class="distribution-chart">
                            <div class="chart-container">
                                <div
                                    v-for="leader in teamLeaders"
                                    :key="leader.id"
                                    class="chart-bar"
                                >
                                    <div class="bar-label">
                                        <span>{{ getLeaderName(leader.id) }}</span>
                                        <span class="bar-count">{{ getTeamMemberCount(leader.id) }}</span>
                                    </div>
                                    <div class="bar-track">
                                        <div
                                            class="bar-fill"
                                            :style="{
                                                width: calculateBarWidth(leader.id) + '%',
                                            }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Department Breakdown -->
                    <div class="department-breakdown-section">
                        <div class="section-header mb-3">
                            <h5 class="section-title">Breakdown per Departemen</h5>
                            <p class="section-subtitle">
                                Distribusi anggota berdasarkan departemen
                            </p>
                        </div>
                        <div class="department-grid">
                            <div
                                v-for="dept in departmentStats"
                                :key="dept.name"
                                class="department-card"
                            >
                                <div class="department-header">
                                    <h6>{{ dept.name }}</h6>
                                    <span class="department-count">{{ dept.count }} anggota</span>
                                </div>
                                <div class="department-teams">
                                    <div
                                        v-for="team in dept.teams"
                                        :key="team.name"
                                        class="team-breakdown"
                                    >
                                        <span class="team-name">{{ team.name }}</span>
                                        <span class="team-member-count">{{ team.memberCount }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </ApprovalTabs>

        <!-- Add Member Modal -->
        <Modal
            :show="showAddMemberModal"
            title="Tambah Anggota Tim"
            size="large"
            @close="closeAddMemberModal"
        >
            <template #body>
                <div class="add-member-form">
                    <div class="form-section">
                        <h6 class="section-title">Pilih Karyawan</h6>
                        <div class="employee-selection">
                            <div class="search-box">
                                <i class="bi bi-search"></i>
                                <input
                                    type="text"
                                    v-model="employeeSearch"
                                    placeholder="Cari karyawan..."
                                    class="search-input"
                                    @input="searchEmployees"
                                />
                            </div>

                            <div class="employees-list">
                                <div
                                    v-for="employee in filteredEmployees"
                                    :key="employee.id"
                                    class="employee-option"
                                    :class="{ selected: selectedEmployee?.id === employee.id }"
                                    @click="selectEmployee(employee)"
                                >
                                    <div class="employee-avatar-sm">
                                        {{ getInitials(employee.name) }}
                                    </div>
                                    <div class="employee-details">
                                        <h6>{{ employee.name }}</h6>
                                        <p>{{ employee.position }} - {{ employee.department }}</p>
                                        <span class="employee-id">{{ employee.employeeId }}</span>
                                    </div>
                                    <div class="selection-indicator">
                                        <i
                                            class="bi bi-check-circle-fill"
                                            v-if="selectedEmployee?.id === employee.id"
                                        ></i>
                                    </div>
                                </div>

                                <div v-if="filteredEmployees.length === 0" class="empty-state">
                                    <i class="bi bi-search"></i>
                                    <p>Tidak ada karyawan ditemukan</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section" v-if="selectedEmployee">
                        <h6 class="section-title">Penugasan Tim</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Tim</label>
                                    <select
                                        v-model="newMember.team"
                                        class="form-control"
                                        @change="updateAvailableLeaders"
                                    >
                                        <option value="">Pilih Tim</option>
                                        <option
                                            v-for="team in availableTeams"
                                            :key="team.id"
                                            :value="team.name"
                                        >
                                            {{ team.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Leader</label>
                                    <select
                                        v-model="newMember.leaderId"
                                        class="form-control"
                                        :disabled="!newMember.team"
                                    >
                                        <option value="">Pilih Leader</option>
                                        <option
                                            v-for="leader in availableLeaders"
                                            :key="leader.id"
                                            :value="leader.id"
                                        >
                                            {{ leader.name }} ({{ leader.position }})
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="assignment-preview" v-if="newMember.leaderId">
                            <div class="preview-card">
                                <div class="preview-header">
                                    <h6>Preview Penugasan</h6>
                                </div>
                                <div class="preview-content">
                                    <div class="preview-item">
                                        <span class="label">Karyawan:</span>
                                        <span class="value">{{ selectedEmployee.name }}</span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="label">Tim:</span>
                                        <span class="value">{{ newMember.team }}</span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="label">Leader:</span>
                                        <span class="value">{{ getLeaderName(newMember.leaderId) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="wrapper-button-footer w-100 gap-2">
                    <button class="btn-modern btn-secondary" @click="closeAddMemberModal">
                        Batal
                    </button>
                    <button
                        class="btn-modern btn-primary"
                        @click="saveNewMember"
                        :disabled="!isNewMemberValid"
                    >
                        Simpan Anggota Tim
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Transfer Member Modal -->
        <Modal
            :show="showTransferModal"
            title="Pindah Anggota Tim"
            size="medium"
            @close="closeTransferModal"
        >
            <template #body>
                <div class="transfer-member-form">
                    <div class="current-assignment mb-3">
                        <h6>Penugasan Saat Ini</h6>
                        <div class="assignment-info">
                            <div class="member-info">
                                <div class="avatar-sm">
                                    {{ getInitials(transferMember.name) }}
                                </div>
                                <div class="member-details">
                                    <strong>{{ transferMember.name }}</strong>
                                    <span>{{ transferMember.position }}</span>
                                </div>
                            </div>
                            <div class="current-team" v-if="transferMember.leaderId">
                                <i class="bi bi-arrow-right"></i>
                                <div class="team-info">
                                    <strong>{{ getLeaderName(transferMember.leaderId) }}</strong>
                                    <span>Tim {{ transferMember.team }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="transfer-options">
                        <h6>Pindah ke Tim</h6>
                        <div class="leaders-grid">
                            <div
                                v-for="leader in availableLeadersForTransfer"
                                :key="leader.id"
                                class="leader-option"
                                :class="{
                                    selected: transferToLeader === leader.id,
                                }"
                                @click="transferToLeader = leader.id"
                            >
                                <div class="leader-avatar-sm">
                                    {{ getInitials(leader.name) }}
                                </div>
                                <div class="leader-details">
                                    <strong>{{ leader.name }}</strong>
                                    <span>{{ leader.position }}</span>
                                    <div class="team-stats">
                                        <small>{{ getTeamMemberCount(leader.id) }} anggota</small>
                                    </div>
                                </div>
                                <div class="selection-indicator">
                                    <i
                                        class="bi bi-check-circle-fill"
                                        v-if="transferToLeader === leader.id"
                                    ></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="wrapper-button-footer w-100 gap-2">
                    <button class="btn-modern btn-secondary" @click="closeTransferModal">
                        Batal
                    </button>
                    <button
                        class="btn-modern btn-primary"
                        @click="confirmTransfer"
                        :disabled="!transferToLeader"
                    >
                        Konfirmasi Pemindahan
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Bulk Assignment Modal -->
        <Modal
            :show="showBulkAssignmentModal"
            title="Penugasan Massal"
            size="large"
            @close="closeBulkAssignmentModal"
        >
            <template #body>
                <div class="bulk-assignment-form">
                    <div class="stepper-container mb-4">
                        <div class="stepper">
                            <div
                                class="step"
                                :class="{
                                    active: bulkStep === 1,
                                    completed: bulkStep > 1,
                                }"
                            >
                                <div class="step-number">1</div>
                                <div class="step-label">Pilih Anggota</div>
                            </div>
                            <div
                                class="step"
                                :class="{
                                    active: bulkStep === 2,
                                    completed: bulkStep > 2,
                                }"
                            >
                                <div class="step-number">2</div>
                                <div class="step-label">Pilih Leader</div>
                            </div>
                            <div
                                class="step"
                                :class="{ active: bulkStep === 3 }"
                            >
                                <div class="step-number">3</div>
                                <div class="step-label">Konfirmasi</div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 1: Select Members -->
                    <div v-if="bulkStep === 1" class="step-content">
                        <h5 class="step-title">Pilih Anggota untuk Ditugaskan</h5>
                        <p class="step-description">
                            Pilih anggota yang akan ditugaskan ke tim
                        </p>

                        <div class="search-box mb-3">
                            <i class="bi bi-search"></i>
                            <input
                                type="text"
                                v-model="bulkMemberSearch"
                                placeholder="Cari anggota..."
                                class="search-input"
                            />
                        </div>

                        <div class="members-selection-grid">
                            <div
                                v-for="member in filteredBulkMembers"
                                :key="member.id"
                                class="member-selection-card"
                                :class="{
                                    selected: bulkSelectedMembers.includes(member.id),
                                }"
                                @click="toggleBulkMemberSelection(member.id)"
                            >
                                <div class="selection-checkbox">
                                    <input
                                        type="checkbox"
                                        :checked="bulkSelectedMembers.includes(member.id)"
                                        @change="toggleBulkMemberSelection(member.id)"
                                    />
                                </div>
                                <div class="member-avatar-sm">
                                    {{ getInitials(member.name) }}
                                </div>
                                <div class="member-details">
                                    <h6>{{ member.name }}</h6>
                                    <p>{{ member.position }}</p>
                                    <span class="department">{{ member.department }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="selection-summary mt-3">
                            <span class="selected-count">
                                {{ bulkSelectedMembers.length }} anggota terpilih
                            </span>
                        </div>

                        <div class="step-actions">
                            <button
                                class="btn-modern btn-primary"
                                @click="nextBulkStep"
                                :disabled="bulkSelectedMembers.length === 0"
                            >
                                Lanjut
                                <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Select Leader -->
                    <div v-else-if="bulkStep === 2" class="step-content">
                        <h5 class="step-title">Pilih Leader Tim</h5>
                        <p class="step-description">
                            Pilih leader yang akan menerima anggota terpilih
                        </p>

                        <div class="leaders-selection-grid">
                            <div
                                v-for="leader in teamLeaders"
                                :key="leader.id"
                                class="leader-selection-card"
                                :class="{
                                    selected: bulkSelectedLeader === leader.id,
                                }"
                                @click="bulkSelectedLeader = leader.id"
                            >
                                <div class="leader-avatar-md">
                                    {{ getInitials(leader.name) }}
                                </div>
                                <div class="leader-details">
                                    <h6>{{ leader.name }}</h6>
                                    <p>{{ leader.position }}</p>
                                    <div class="team-info">
                                        <span class="team-name">Tim {{ leader.team }}</span>
                                        <span class="member-count">
                                            {{ getTeamMemberCount(leader.id) }} anggota
                                        </span>
                                    </div>
                                </div>
                                <div class="selection-indicator">
                                    <i
                                        class="bi bi-check-circle-fill"
                                        v-if="bulkSelectedLeader === leader.id"
                                    ></i>
                                </div>
                            </div>
                        </div>

                        <div class="step-actions">
                            <div class="d-flex justify-content-between w-100">
                                <button class="btn-modern btn-secondary" @click="previousBulkStep">
                                    <i class="bi bi-arrow-left me-2"></i>
                                    Kembali
                                </button>
                                <button
                                    class="btn-modern btn-primary"
                                    @click="nextBulkStep"
                                    :disabled="!bulkSelectedLeader"
                                >
                                    Lanjut
                                    <i class="bi bi-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Confirmation -->
                    <div v-else-if="bulkStep === 3" class="step-content">
                        <h5 class="step-title">Konfirmasi Penugasan</h5>
                        <p class="step-description">
                            Tinjau penugasan sebelum disimpan
                        </p>

                        <div class="confirmation-details">
                            <div class="assignment-summary">
                                <div class="summary-item">
                                    <strong>Leader Tujuan:</strong>
                                    <span>{{ getLeaderName(bulkSelectedLeader) }}</span>
                                </div>
                                <div class="summary-item">
                                    <strong>Jumlah Anggota:</strong>
                                    <span>{{ bulkSelectedMembers.length }} orang</span>
                                </div>
                                <div class="summary-item">
                                    <strong>Tim:</strong>
                                    <span>{{ getLeaderTeam(bulkSelectedLeader) }}</span>
                                </div>
                            </div>

                            <div class="members-preview">
                                <h6>Anggota yang Akan Ditugaskan:</h6>
                                <div class="preview-grid">
                                    <div
                                        v-for="memberId in bulkSelectedMembers"
                                        :key="memberId"
                                        class="preview-member"
                                    >
                                        <div class="member-avatar-xs">
                                            {{ getInitials(getMemberById(memberId).name) }}
                                        </div>
                                        <span class="member-name">{{ getMemberById(memberId).name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="step-actions">
                            <div class="d-flex justify-content-between w-100">
                                <button class="btn-modern btn-secondary" @click="previousBulkStep">
                                    <i class="bi bi-arrow-left me-2"></i>
                                    Kembali
                                </button>
                                <button class="btn-modern btn-success" @click="confirmBulkAssignment">
                                    <i class="bi bi-check-circle me-2"></i>
                                    Konfirmasi Penugasan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </Modal>

        <!-- Team Details Modal -->
        <Modal
            :show="showTeamDetailsModal"
            :title="`Detail Tim ${teamDetailsData?.leader?.name || ''}`"
            size="large"
            @close="hideTeamDetailsModal"
        >
            <template #body>
                <div v-if="teamDetailsData" class="team-details-content">
                    <!-- Leader Info -->
                    <div class="leader-info-section mb-4">
                        <div class="section-header mb-3">
                            <h5 class="section-title">Informasi Leader</h5>
                        </div>
                        <div class="leader-info-card">
                            <div class="leader-avatar-lg">
                                {{ getInitials(teamDetailsData.leader.name) }}
                            </div>
                            <div class="leader-details-lg">
                                <h4>{{ teamDetailsData.leader.name }}</h4>
                                <p class="position">{{ teamDetailsData.leader.position }}</p>
                                <p class="department">{{ teamDetailsData.leader.department }}</p>
                                <div class="team-stats">
                                    <span class="member-count">
                                        <i class="bi bi-people"></i>
                                        {{ teamDetailsData.totalMembers }} anggota
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Team Members -->
                    <div class="team-members-section">
                        <div class="section-header mb-3">
                            <h5 class="section-title">Anggota Tim</h5>
                            <span class="member-count-badge">{{ teamDetailsData.totalMembers }} orang</span>
                        </div>
                        <div class="team-members-grid">
                            <div
                                v-for="member in teamDetailsData.members"
                                :key="member.id"
                                class="team-member-detail-card"
                            >
                                <div class="member-avatar">
                                    {{ getInitials(member.name) }}
                                </div>
                                <div class="member-info">
                                    <h6>{{ member.name }}</h6>
                                    <p class="member-position">{{ member.position }}</p>
                                    <p class="member-department">{{ member.department }}</p>
                                    <div class="member-meta">
                                        <span class="employee-id">{{ member.employeeId }}</span>
                                        <span :class="`status-badge status-${member.status}`">
                                            {{ getStatusText(member.status) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="member-actions">
                                    <button
                                        class="btn-action btn-view"
                                        @click="viewMemberDetails(member)"
                                        title="Lihat Detail"
                                    >
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button
                                        class="btn-action btn-transfer"
                                        @click="showTransferModal(member)"
                                        title="Pindah Tim"
                                    >
                                        <i class="bi bi-arrow-left-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="wrapper-button-footer w-100">
                    <button class="btn-modern btn-secondary" @click="hideTeamDetailsModal">
                        Tutup
                    </button>
                    <button class="btn-modern btn-primary" @click="editLeaderTeam(teamDetailsData.leader)">
                        <i class="bi bi-gear me-2"></i>
                        Kelola Tim
                    </button>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import quickStat from "@/components/legacy/quickStat.vue";
import ApprovalTabs from "@/components/legacy/toggleSectionV2.vue";
import Modal from "@/components/legacy/modalV2-noir.vue";
import { ElMessage, ElNotification } from "element-plus";
import HeaderComponent from "@/components/legacy/header.vue";
import { tanggalPanjang } from '@/utils/tanggal';

export default {
    components: {
        quickStat,
        ApprovalTabs,
        Modal,
        HeaderComponent,
    },
    data() {
        return {
            currentDate: new Date(),
            loading: {
                team: false,
                employees: false
            },

            // Tabs
            activeTab: "overview",
            mainTabs: [
                {
                    id: "overview",
                    label: "Overview Tim",
                    icon: "bi bi-people",
                    count: 0,
                },
                {
                    id: "assignment",
                    label: "Penugasan",
                    icon: "bi bi-person-check",
                    count: 0,
                },
                {
                    id: "analytics",
                    label: "Analisis",
                    icon: "bi bi-graph-up",
                    count: 0,
                },
            ],

            // Data utama
            allEmployees: [], // Semua karyawan dari sistem
            teamMembers: [], // Anggota yang sudah memiliki tim
            teamLeaders: [], // Leader tim
            teams: [], // Data tim yang tersedia

            // Form data
            newMember: {
                employeeId: '',
                team: '',
                leaderId: ''
            },
            selectedEmployee: null,
            employeeSearch: '',

            // Filter dan state
            teamFilters: {
                search: '',
                department: '',
                position: '',
                team: '',
                status: 'active'
            },

            // Selection state
            selectedMembers: [],
            selectedLeader: null,

            // Modals
            showAddMemberModal: false,
            showTransferModal: false,
            showBulkAssignmentModal: false,
            showTeamDetailsModal: false,

            // Transfer data
            transferMember: null,
            transferToLeader: null,

            // Bulk assignment
            bulkStep: 1,
            bulkSelectedMembers: [],
            bulkSelectedLeader: null,
            bulkMemberSearch: '',

            // Team details
            teamDetailsData: null,

            // Pagination
            itemsPerPage: 10,
            pagination: {
                overview: {
                    currentPage: 1,
                    totalPages: 1,
                    startItem: 0,
                    endItem: 0,
                },
            },
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
                    title: "Total Anggota",
                    value: this.teamMembers.length,
                    unit: "orang",
                    icon: "bi bi-people",
                    trend: "up",
                    size: "col-4",
                },
                {
                    title: "Leader Aktif",
                    value: this.teamLeaders.length,
                    unit: "orang",
                    icon: "bi bi-person-badge",
                    trend: "stable",
                    size: "col-4",
                },
                {
                    title: "Tersedia",
                    value: this.availableEmployees.length,
                    unit: "orang",
                    icon: "bi bi-person-plus",
                    trend: "down",
                    size: "col-4",
                },
            ];
        },
        filteredTeamMembers() {
            return this.teamMembers.filter((member) => {
                const matchesSearch =
                    !this.teamFilters.search ||
                    member.name.toLowerCase().includes(this.teamFilters.search.toLowerCase()) ||
                    member.employeeId.toLowerCase().includes(this.teamFilters.search.toLowerCase()) ||
                    member.position.toLowerCase().includes(this.teamFilters.search.toLowerCase());

                const matchesDepartment =
                    !this.teamFilters.department ||
                    member.department === this.teamFilters.department;

                const matchesPosition =
                    !this.teamFilters.position ||
                    member.position === this.teamFilters.position;

                const matchesTeam =
                    !this.teamFilters.team ||
                    member.team === this.teamFilters.team;

                const matchesStatus =
                    !this.teamFilters.status ||
                    member.status === this.teamFilters.status;

                return (
                    matchesSearch &&
                    matchesDepartment &&
                    matchesPosition &&
                    matchesTeam &&
                    matchesStatus
                );
            });
        },
        paginatedTeamMembers() {
            const start = (this.pagination.overview.currentPage - 1) * this.itemsPerPage;
            const end = start + this.itemsPerPage;
            return this.filteredTeamMembers.slice(start, end);
        },
        visiblePages() {
            const current = this.pagination.overview.currentPage;
            const total = this.pagination.overview.totalPages;
            const range = 2;
            const pages = [];

            for (let i = Math.max(1, current - range); i <= Math.min(total, current + range); i++) {
                pages.push(i);
            }

            if (pages[0] > 1) {
                pages.unshift(1);
                if (pages[1] > 2) {
                    pages.splice(1, 0, "...");
                }
            }

            if (pages[pages.length - 1] < total) {
                if (pages[pages.length - 1] < total - 1) {
                    pages.push("...");
                }
                pages.push(total);
            }

            return pages;
        },
        uniqueDepartments() {
            return [...new Set(this.teamMembers.map((m) => m.department))].filter(Boolean);
        },
        uniquePositions() {
            return [...new Set(this.teamMembers.map((m) => m.position))].filter(Boolean);
        },
        uniqueTeams() {
            return [...new Set(this.teamMembers.map((m) => m.team))].filter(Boolean);
        },
        // Karyawan yang tersedia (belum memiliki tim)
        availableEmployees() {
            return this.allEmployees.filter(employee =>
                !this.teamMembers.some(member => member.employeeId === employee.employeeId) &&
                employee.status === 'active'
            );
        },
        // Employees yang difilter untuk pencarian di modal
        filteredEmployees() {
            if (!this.employeeSearch) return this.availableEmployees.slice(0, 10);

            return this.availableEmployees.filter(employee =>
                employee.name.toLowerCase().includes(this.employeeSearch.toLowerCase()) ||
                employee.employeeId.toLowerCase().includes(this.employeeSearch.toLowerCase()) ||
                employee.position.toLowerCase().includes(this.employeeSearch.toLowerCase())
            ).slice(0, 10);
        },
        // Members untuk bulk assignment
        filteredBulkMembers() {
            if (!this.bulkMemberSearch) return this.availableEmployees;

            return this.availableEmployees.filter(member =>
                member.name.toLowerCase().includes(this.bulkMemberSearch.toLowerCase()) ||
                member.employeeId.toLowerCase().includes(this.bulkMemberSearch.toLowerCase())
            );
        },
        // Tim yang tersedia untuk dipilih
        availableTeams() {
            return [...new Set(this.teamLeaders.map(leader => ({
                id: leader.teamId,
                name: leader.team
            })))];
        },
        // Leader yang tersedia berdasarkan tim yang dipilih
        availableLeaders() {
            if (!this.newMember.team) return [];
            return this.teamLeaders.filter(leader => leader.team === this.newMember.team);
        },
        availableLeadersForTransfer() {
            if (!this.transferMember) return [];
            return this.teamLeaders.filter(leader => leader.id !== this.transferMember.leaderId);
        },
        // Validasi form
        isNewMemberValid() {
            return this.selectedEmployee &&
                   this.newMember.team &&
                   this.newMember.leaderId;
        },
        calculateAverageTeamSize() {
            if (this.teamLeaders.length === 0) return 0;
            const totalMembers = this.teamMembers.filter(m => m.leaderId).length;
            return Math.round(totalMembers / this.teamLeaders.length);
        },
        departmentStats() {
            const stats = {};
            this.teamMembers.forEach((member) => {
                if (!stats[member.department]) {
                    stats[member.department] = {
                        name: member.department,
                        count: 0,
                        teams: {},
                    };
                }
                stats[member.department].count++;

                if (member.team) {
                    if (!stats[member.department].teams[member.team]) {
                        stats[member.department].teams[member.team] = {
                            name: member.team,
                            memberCount: 0,
                        };
                    }
                    stats[member.department].teams[member.team].memberCount++;
                }
            });

            return Object.values(stats).map((dept) => ({
                ...dept,
                teams: Object.values(dept.teams),
            }));
        },
    },
    methods: {
        formatDateString(dateString) {
            return tanggalPanjang(dateString);
        },
        formatDate(dateString) {
            if (!dateString) return "-";
            try {
                const date = new Date(dateString);
                return new Intl.DateTimeFormat("id-ID", {
                    day: "2-digit",
                    month: "2-digit",
                    year: "numeric"
                }).format(date);
            } catch (error) {
                return dateString;
            }
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
                active: "Aktif",
                inactive: "Nonaktif",
                pending: "Menunggu",
            };
            return statusMap[status] || "Tidak Diketahui";
        },
        getLeaderName(leaderId) {
            const leader = this.teamLeaders.find((l) => l.id === leaderId);
            return leader ? leader.name : "Tidak Diketahui";
        },
        getLeaderTeam(leaderId) {
            const leader = this.teamLeaders.find((l) => l.id === leaderId);
            return leader ? leader.team : "Tidak Diketahui";
        },
        getTeamMemberCount(leaderId) {
            return this.teamMembers.filter((member) => member.leaderId === leaderId).length;
        },
        getLeaderTeamMembers(leaderId) {
            return this.teamMembers.filter((member) => member.leaderId === leaderId);
        },
        getMemberById(memberId) {
            return this.teamMembers.find((member) => member.id === memberId) ||
                   this.allEmployees.find((emp) => emp.id === memberId);
        },
        calculateBarWidth(leaderId) {
            const maxMembers = Math.max(...this.teamLeaders.map((leader) => this.getTeamMemberCount(leader.id)));
            if (maxMembers === 0) return 0;
            return (this.getTeamMemberCount(leaderId) / maxMembers) * 100;
        },
        isMemberSelected(memberId) {
            return this.selectedMembers.includes(memberId);
        },
        toggleMemberSelection(memberId) {
            const index = this.selectedMembers.indexOf(memberId);
            if (index > -1) {
                this.selectedMembers.splice(index, 1);
            } else {
                this.selectedMembers.push(memberId);
            }
        },
        selectLeader(leaderId) {
            this.selectedLeader = leaderId;
        },
        // Method untuk memilih karyawan di modal
        selectEmployee(employee) {
            this.selectedEmployee = employee;
            this.newMember.employeeId = employee.employeeId;
        },
        // Update leader yang tersedia berdasarkan tim
        updateAvailableLeaders() {
            this.newMember.leaderId = '';
        },
        // Pencarian employees
        searchEmployees() {
            // Debounced search bisa ditambahkan di sini
        },
        async assignSelectedMembers() {
            if (this.selectedMembers.length === 0 || !this.selectedLeader) {
                ElMessage.warning("Pilih anggota dan leader terlebih dahulu");
                return;
            }

            try {
                this.loading.team = true;

                const payload = {
                    leaderId: this.selectedLeader,
                    memberIds: this.selectedMembers,
                };

                const response = await fetch("/api/v1/karyawan-tim/assign-members", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify(payload),
                });

                const result = await response.json();

                if (result.success) {
                    ElNotification({
                        title: "Berhasil",
                        message: `${this.selectedMembers.length} anggota berhasil ditugaskan`,
                        type: "success",
                    });

                    this.selectedMembers = [];
                    await this.initializeData();
                    this.updateTabCounts();
                } else {
                    ElMessage.error(result.message || "Gagal menugaskan anggota");
                }
            } catch (error) {
                console.error("Error assigning members:", error);
                ElMessage.error("Gagal menugaskan anggota ke tim");
            } finally {
                this.loading.team = false;
            }
        },
        async unassignSelectedMembers() {
            if (this.selectedMembers.length === 0) {
                ElMessage.warning("Pilih anggota terlebih dahulu");
                return;
            }

            try {
                this.loading.team = true;

                const response = await fetch("/api/v1/karyawan-tim/remove-members", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        memberIds: this.selectedMembers,
                    }),
                });

                const result = await response.json();

                if (result.success) {
                    ElNotification({
                        title: "Berhasil",
                        message: `${this.selectedMembers.length} anggota berhasil dihapus dari tim`,
                        type: "success",
                    });

                    this.selectedMembers = [];
                    await this.initializeData();
                    this.updateTabCounts();
                } else {
                    ElMessage.error(result.message || "Gagal menghapus anggota");
                }
            } catch (error) {
                console.error("Error unassigning members:", error);
                ElMessage.error("Gagal menghapus anggota dari tim");
            } finally {
                this.loading.team = false;
            }
        },
        async removeMemberFromTeam(memberId) {
            try {
                const response = await fetch("/api/v1/karyawan-tim/remove-member", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        memberId: memberId,
                    }),
                });

                const result = await response.json();

                if (result.success) {
                    ElMessage.success("Anggota berhasil dihapus dari tim");
                    await this.initializeData();
                    this.updateTabCounts();
                } else {
                    ElMessage.error(result.message || "Gagal menghapus anggota");
                }
            } catch (error) {
                console.error("Error removing member:", error);
                ElMessage.error("Gagal menghapus anggota dari tim");
            }
        },
        viewMemberDetails(member) {
            // Implementation for viewing member details
            console.log("View member details:", member);
            ElMessage.info(`Melihat detail ${member.name}`);
        },
        editMember(member) {
            // Implementation for editing member
            console.log("Edit member:", member);
            ElMessage.info(`Mengedit ${member.name}`);
        },
        async viewLeaderTeam(leader) {
            try {
                const response = await fetch(`/api/v1/karyawan-tim/team-details/${leader.id}`);
                const result = await response.json();

                if (result.success) {
                    this.teamDetailsData = result.data;
                    this.showTeamDetailsModal = true;
                } else {
                    ElMessage.error("Gagal memuat detail tim");
                }
            } catch (error) {
                console.error("Error viewing leader team:", error);
                ElMessage.error("Gagal memuat detail tim");
            }
        },
        hideTeamDetailsModal() {
            this.showTeamDetailsModal = false;
            this.teamDetailsData = null;
        },
        editLeaderTeam(leader) {
            // Implementation for editing leader's team
            console.log("Edit leader team:", leader);
            ElMessage.info(`Mengelola tim ${leader.name}`);
        },
        resetFilters() {
            this.teamFilters = {
                search: "",
                department: "",
                position: "",
                team: "",
                status: "active"
            };
        },
        handleTabChange(tabId) {
            this.activeTab = tabId;
            this.pagination.overview.currentPage = 1;
            this.updatePagination();
        },
        goToPage(page) {
            if (page === "...") return;
            this.pagination.overview.currentPage = page;
            this.updatePagination();
        },
        previousPage() {
            if (this.pagination.overview.currentPage > 1) {
                this.pagination.overview.currentPage--;
                this.updatePagination();
            }
        },
        nextPage() {
            if (this.pagination.overview.currentPage < this.pagination.overview.totalPages) {
                this.pagination.overview.currentPage++;
                this.updatePagination();
            }
        },
        updatePagination() {
            const dataLength = this.filteredTeamMembers.length;
            this.pagination.overview.totalPages = Math.ceil(dataLength / this.itemsPerPage);
            this.pagination.overview.startItem = (this.pagination.overview.currentPage - 1) * this.itemsPerPage + 1;
            this.pagination.overview.endItem = Math.min(
                this.pagination.overview.currentPage * this.itemsPerPage,
                dataLength
            );
        },
        updateTabCounts() {
            this.mainTabs[0].count = this.teamMembers.length;
            this.mainTabs[1].count = this.availableEmployees.length;
            this.mainTabs[2].count = this.teamLeaders.length;
        },
        // Modal Methods
        showAddMemberModal() {
            this.showAddMemberModal = true;
        },
        closeAddMemberModal() {
            this.showAddMemberModal = false;
            this.selectedEmployee = null;
            this.employeeSearch = '';
            this.newMember = {
                employeeId: '',
                team: '',
                leaderId: ''
            };
        },
        async saveNewMember() {
            if (!this.isNewMemberValid) {
                ElMessage.warning('Harap lengkapi semua field yang diperlukan');
                return;
            }

            try {
                this.loading.team = true;

                const payload = {
                    employeeId: this.newMember.employeeId,
                    leaderId: this.newMember.leaderId,
                    team: this.newMember.team
                };

                const response = await fetch('/api/v1/karyawan-tim/add-member', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (result.success) {
                    ElNotification({
                        title: 'Berhasil',
                        message: `Anggota ${this.selectedEmployee.name} berhasil ditambahkan ke tim`,
                        type: 'success',
                    });

                    await this.initializeData();
                    this.closeAddMemberModal();
                } else {
                    ElMessage.error(result.message || 'Gagal menambahkan anggota tim');
                }
            } catch (error) {
                console.error('Error adding team member:', error);
                ElMessage.error('Terjadi kesalahan saat menambahkan anggota tim');
            } finally {
                this.loading.team = false;
            }
        },
        showTransferModal(member) {
            this.transferMember = { ...member };
            this.transferToLeader = null;
            this.showTransferModal = true;
        },
        closeTransferModal() {
            this.showTransferModal = false;
            this.transferMember = null;
            this.transferToLeader = null;
        },
        async confirmTransfer() {
            if (!this.transferToLeader) {
                ElMessage.warning("Pilih leader tujuan terlebih dahulu");
                return;
            }

            try {
                const response = await fetch("/api/v1/karyawan-tim/transfer-member", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        memberId: this.transferMember.id,
                        newLeaderId: this.transferToLeader,
                    }),
                });

                const result = await response.json();

                if (result.success) {
                    ElNotification({
                        title: "Berhasil",
                        message: `Anggota berhasil dipindahkan ke tim ${this.getLeaderName(this.transferToLeader)}`,
                        type: "success",
                    });

                    this.closeTransferModal();
                    await this.initializeData();
                    this.updateTabCounts();
                } else {
                    ElMessage.error(result.message || "Gagal memindahkan anggota");
                }
            } catch (error) {
                console.error("Error transferring member:", error);
                ElMessage.error("Gagal memindahkan anggota tim");
            }
        },
        showBulkAssignmentModal() {
            this.bulkStep = 1;
            this.bulkSelectedMembers = [];
            this.bulkSelectedLeader = null;
            this.bulkMemberSearch = '';
            this.showBulkAssignmentModal = true;
        },
        closeBulkAssignmentModal() {
            this.showBulkAssignmentModal = false;
            this.bulkStep = 1;
            this.bulkSelectedMembers = [];
            this.bulkSelectedLeader = null;
            this.bulkMemberSearch = '';
        },
        toggleBulkMemberSelection(memberId) {
            const index = this.bulkSelectedMembers.indexOf(memberId);
            if (index > -1) {
                this.bulkSelectedMembers.splice(index, 1);
            } else {
                this.bulkSelectedMembers.push(memberId);
            }
        },
        nextBulkStep() {
            if (this.bulkStep < 3) {
                this.bulkStep++;
            }
        },
        previousBulkStep() {
            if (this.bulkStep > 1) {
                this.bulkStep--;
            }
        },
        async confirmBulkAssignment() {
            if (this.bulkSelectedMembers.length === 0 || !this.bulkSelectedLeader) {
                ElMessage.warning("Pilih anggota dan leader terlebih dahulu");
                return;
            }

            try {
                const response = await fetch("/api/v1/karyawan-tim/bulk-assign", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        leaderId: this.bulkSelectedLeader,
                        memberIds: this.bulkSelectedMembers,
                    }),
                });

                const result = await response.json();

                if (result.success) {
                    ElNotification({
                        title: "Berhasil",
                        message: `${this.bulkSelectedMembers.length} anggota berhasil ditugaskan ke tim ${this.getLeaderName(this.bulkSelectedLeader)}`,
                        type: "success",
                    });

                    this.closeBulkAssignmentModal();
                    await this.initializeData();
                    this.updateTabCounts();
                } else {
                    ElMessage.error(result.message || "Gagal melakukan penugasan massal");
                }
            } catch (error) {
                console.error("Error in bulk assignment:", error);
                ElMessage.error("Gagal melakukan penugasan massal");
            }
        },

        // API methods
        async loadAllEmployees() {
            try {
                const response = await fetch('/api/v1/karyawan-tim/all-employees');
                const result = await response.json();

                if (result.success) {
                    this.allEmployees = result.data;
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                console.error('Error loading employees:', error);
                // Fallback data untuk demo
                this.allEmployees = [
                    { id: 1, employeeId: 'EMP001', name: 'Ahmad Wijaya', position: 'Software Engineer', department: 'IT', status: 'active' },
                    { id: 2, employeeId: 'EMP002', name: 'Siti Rahayu', position: 'UI/UX Designer', department: 'Design', status: 'active' },
                    { id: 3, employeeId: 'EMP003', name: 'Budi Santoso', position: 'Product Manager', department: 'Product', status: 'active' },
                    { id: 4, employeeId: 'EMP004', name: 'Dewi Lestari', position: 'QA Engineer', department: 'IT', status: 'active' },
                    { id: 5, employeeId: 'EMP005', name: 'Rizki Pratama', position: 'Frontend Developer', department: 'IT', status: 'active' },
                ];
            }
        },
        async loadTeamMembers() {
            try {
                const response = await fetch('/api/v1/karyawan-tim/members');
                const result = await response.json();

                if (result.success) {
                    this.teamMembers = result.data;
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                console.error('Error loading team members:', error);
                // Fallback data untuk demo
                this.teamMembers = [
                    { id: 1, employeeId: 'EMP001', name: 'Ahmad Wijaya', position: 'Software Engineer', department: 'IT', team: 'Development', leaderId: 1, joinedAt: '2024-01-15', status: 'active' },
                    { id: 2, employeeId: 'EMP002', name: 'Siti Rahayu', position: 'UI/UX Designer', department: 'Design', team: 'Design', leaderId: 2, joinedAt: '2024-01-20', status: 'active' },
                ];
            }
        },
        async loadTeamLeaders() {
            try {
                const response = await fetch('/api/v1/karyawan-tim/leaders');
                const result = await response.json();

                if (result.success) {
                    this.teamLeaders = result.data;
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                console.error('Error loading team leaders:', error);
                // Fallback data untuk demo
                this.teamLeaders = [
                    { id: 1, name: 'John Doe', position: 'Tech Lead', department: 'IT', team: 'Development', teamId: 1 },
                    { id: 2, name: 'Jane Smith', position: 'Design Lead', department: 'Design', team: 'Design', teamId: 2 },
                    { id: 3, name: 'Robert Brown', position: 'Product Lead', department: 'Product', team: 'Product', teamId: 3 },
                ];
            }
        },
        async loadTeams() {
            try {
                const response = await fetch('/api/v1/karyawan-tim/teams');
                const result = await response.json();

                if (result.success) {
                    this.teams = result.data;
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                console.error('Error loading teams:', error);
                // Fallback data untuk demo
                this.teams = [
                    { id: 1, name: 'Development', description: 'Tim pengembangan software' },
                    { id: 2, name: 'Design', description: 'Tim desain UI/UX' },
                    { id: 3, name: 'Product', description: 'Tim manajemen produk' },
                ];
            }
        },
        async initializeData() {
            this.loading.team = true;
            try {
                await Promise.all([
                    this.loadAllEmployees(),
                    this.loadTeamMembers(),
                    this.loadTeamLeaders(),
                    this.loadTeams()
                ]);

                this.updateTabCounts();
                this.updatePagination();
            } catch (error) {
                console.error('Error initializing data:', error);
                ElMessage.error('Gagal memuat data tim');
            } finally {
                this.loading.team = false;
            }
        },
    },
    watch: {
        filteredTeamMembers: {
            handler() {
                this.$nextTick(() => {
                    this.updatePagination();
                });
            },
            deep: true,
        },
    },
    async mounted() {
        await this.initializeData();
    },
};
</script>

<style scoped>
/* Improved Layout and Spacing */
.team-management-container {
    min-height: 100vh;
    background: var(--surface-soft);
}

/* Header Improvements */
.header-section {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow);
}

/* Quick Stats Improvements */
.quick-stats-section {
    margin-bottom: 1.5rem;
}

/* Tab Content Improvements */
.tab-content-wrapper {
    background: var(--surface);
    border-radius: 12px;
    box-shadow: var(--shadow);
    overflow: hidden;
}

/* Filter Section Improvements */
.team-filters-bar {
    background: var(--surface-soft);
    padding: 1.25rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    border: 1px solid var(--border);
}

.search-box {
    position: relative;
    width: 100%;
}

.search-box i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
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
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    background: var(--surface);
}

.filter-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.filter-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 1rem;
}

.btn-filter-reset {
    background: var(--secondary);
    color: white;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-filter-reset:hover {
    background: var(--hcis-slate-600);
    transform: translateY(-1px);
}

.filter-count {
    font-size: 0.875rem;
    color: var(--text-muted);
    font-weight: 500;
}

/* Team Structure Visualization */
.team-structure-section {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    margin-bottom: 1.5rem;
}

.leaders-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}

.leader-card {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s ease;
}

.leader-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
    border-color: var(--primary-light);
}

.leader-avatar {
    width: 60px;
    height: 60px;
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

.leader-info {
    flex: 1;
}

.leader-info h6 {
    margin: 0 0 0.25rem 0;
    color: var(--dark);
    font-weight: 600;
}

.leader-position {
    margin: 0 0 0.5rem 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.team-count {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-muted);
}

.leader-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-action {
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-action.btn-view {
    background: rgba(59, 130, 246, 0.1);
    color: var(--primary);
}

.btn-action.btn-view:hover {
    background: var(--primary);
    color: white;
    transform: scale(1.1);
}

.btn-action.btn-edit {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.btn-action.btn-edit:hover {
    background: var(--success);
    color: white;
    transform: scale(1.1);
}

.btn-action.btn-transfer {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.btn-action.btn-transfer:hover {
    background: var(--warning);
    color: white;
    transform: scale(1.1);
}

/* Table Improvements */
.modern-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--surface);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: var(--shadow);
}

.modern-table th {
    background: var(--surface-soft);
    padding: 1rem;
    font-weight: 600;
    color: var(--text-muted);
    text-align: left;
    border-bottom: 1px solid var(--border);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.modern-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    color: var(--text);
    font-size: 0.9rem;
}

.modern-table tr:last-child td {
    border-bottom: none;
}

.modern-table tr:hover {
    background: var(--surface-soft);
}

.employee-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.avatar-sm {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
}

.employee-details .employee-name {
    font-weight: 600;
    color: var(--text);
    margin: 0;
}

.employee-details .employee-code {
    color: var(--text-muted);
    font-size: 0.8rem;
    margin: 0;
}

.team-badge {
    background: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
}

/* Status Badges Improvements */
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

.status-badge:before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}

.status-active {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-active:before {
    background: var(--success);
}

.status-inactive {
    background: rgba(100, 116, 139, 0.1);
    color: var(--secondary);
}

.status-inactive:before {
    background: var(--secondary);
}

.status-pending {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.status-pending:before {
    background: var(--warning);
}

.action-cell {
    display: flex;
    gap: 0.5rem;
}

/* Mobile Card View */
.team-members-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}

.team-member-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.25rem;
    transition: all 0.3s ease;
}

.team-member-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
    border-color: var(--primary-light);
}

.member-header {
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
    font-size: 1rem;
    flex-shrink: 0;
}

.member-info {
    flex: 1;
}

.member-name {
    margin: 0 0 0.25rem 0;
    color: var(--dark);
    font-weight: 600;
    font-size: 1rem;
}

.member-position {
    margin: 0 0 0.5rem 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.member-meta {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.member-id, .member-department {
    background: var(--surface-soft);
    color: var(--text-muted);
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
}

.member-status {
    flex-shrink: 0;
}

.member-team-info {
    margin-bottom: 1rem;
}

.team-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.team-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-muted);
}

.member-actions {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 0;
    border-top: 1px solid var(--border);
    margin-top: 1.5rem;
}

.pagination-info {
    color: var(--text-muted);
    font-size: 0.9rem;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pagination-btn {
    width: 36px;
    height: 36px;
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.pagination-btn:hover:not(:disabled) {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-pages {
    display: flex;
    gap: 0.25rem;
}

.pagination-page {
    width: 36px;
    height: 36px;
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.pagination-page:hover {
    background: var(--surface-soft);
}

.pagination-page.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Loading States */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: var(--text-muted);
    text-align: center;
}

.loading-spinner {
    margin-bottom: 1rem;
}

.loading-spinner i {
    font-size: 2.5rem;
    color: var(--primary);
    animation: spin 1.5s linear infinite;
}

.loading-title {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-weight: 600;
}

.loading-description {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Empty States */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--text-muted);
}

.empty-icon {
    margin-bottom: 1.5rem;
}

.empty-icon i {
    font-size: 4rem;
    opacity: 0.5;
    color: var(--primary);
}

.empty-state h4 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-weight: 600;
}

.empty-state p {
    margin: 0 0 1.5rem 0;
    font-size: 0.9rem;
}

/* Team Assignment Styles */
.team-assignment-content {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
}

.assignment-section {
    background: var(--surface-soft);
    border-radius: 12px;
    padding: 1.5rem;
    height: 100%;
}

.section-header-compact {
    margin-bottom: 1rem;
}

.section-header-compact .section-title {
    margin: 0 0 0.25rem 0;
    color: var(--dark);
    font-weight: 600;
    font-size: 1rem;
}

.section-header-compact .section-subtitle {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.85rem;
}

.members-list, .leaders-list {
    max-height: 400px;
    overflow-y: auto;
}

.member-assignment-card, .leader-assignment-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.member-assignment-card:hover, .leader-assignment-card:hover {
    border-color: var(--primary-light);
    background: rgba(99, 102, 241, 0.05);
}

.member-assignment-card.selected, .leader-assignment-card.active {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
}

.member-avatar-sm, .leader-avatar-sm {
    width: 40px;
    height: 40px;
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

.member-details-sm, .leader-details-sm {
    flex: 1;
}

.member-details-sm h6, .leader-details-sm h6 {
    margin: 0 0 0.25rem 0;
    font-size: 0.9rem;
    color: var(--dark);
}

.member-details-sm p, .leader-details-sm p {
    margin: 0;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.employee-code {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-family: monospace;
}

.team-stats {
    margin-top: 0.25rem;
    display: flex;
    gap: 1rem;
}

.member-count, .team-name {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.selection-checkbox input {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.selection-indicator {
    color: var(--primary);
    font-size: 1.2rem;
}

.assignment-actions {
    gap: 1rem;
}

.btn-assign, .btn-unassign {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 80px;
}

.btn-assign:hover:not(:disabled) {
    border-color: var(--success);
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.btn-unassign:hover:not(:disabled) {
    border-color: var(--danger);
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.btn-assign:disabled, .btn-unassign:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.selected-team-section {
    background: var(--surface);
    border-radius: 8px;
    padding: 1rem;
    border: 1px solid var(--border);
}

.team-members-list {
    max-height: 200px;
    overflow-y: auto;
}

.team-member-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border: 1px solid var(--border);
    border-radius: 6px;
    margin-bottom: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.team-member-item:hover {
    border-color: var(--primary-light);
}

.team-member-item.selected {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.member-avatar-xs {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 600;
    flex-shrink: 0;
}

.member-details-xs {
    flex: 1;
    font-size: 0.85rem;
    color: var(--dark);
}

.member-details-xs small {
    display: block;
    color: var(--text-muted);
    font-size: 0.75rem;
}

.btn-remove-member {
    width: 24px;
    height: 24px;
    border: none;
    background: var(--danger);
    color: white;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-remove-member:hover {
    background: var(--hcis-red-600);
    transform: scale(1.1);
}

.empty-assignment-state {
    text-align: center;
    padding: 2rem;
    color: var(--text-muted);
}

.empty-assignment-state i {
    font-size: 2rem;
    margin-bottom: 1rem;
    color: var(--success);
}

/* Team Analytics Styles */
.team-analytics-content {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
}

.analytics-card {
    background: var(--surface-soft);
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s ease;
}

.analytics-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.analytics-icon {
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

.analytics-icon.total-members {
    background: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
}

.analytics-icon.team-leaders {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.analytics-icon.available-members {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.analytics-icon.avg-team-size {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.analytics-content h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--dark);
}

.analytics-content p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.distribution-chart {
    background: var(--surface-soft);
    border-radius: 12px;
    padding: 1.5rem;
}

.chart-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.chart-bar {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.bar-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 200px;
    font-size: 0.9rem;
    color: var(--dark);
}

.bar-count {
    font-weight: 600;
    color: var(--primary);
}

.bar-track {
    flex: 1;
    height: 20px;
    background: var(--border);
    border-radius: 10px;
    overflow: hidden;
}

.bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    border-radius: 10px;
    transition: width 1s ease-in-out;
}

.department-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}

.department-card {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.department-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
    border-color: var(--primary-light);
}

.department-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border);
}

.department-header h6 {
    margin: 0;
    color: var(--dark);
    font-weight: 600;
}

.department-count {
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.department-teams {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.team-breakdown {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem;
    background: var(--surface);
    border-radius: 6px;
    font-size: 0.85rem;
}

.team-name {
    color: var(--dark);
    font-weight: 500;
}

.team-member-count {
    background: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.2rem 0.5rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.75rem;
}

/* Add Member Modal Styles */
.add-member-form {
    padding: 0.5rem;
}

.form-section {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.section-title {
    margin: 0 0 1rem 0;
    color: var(--dark);
    font-weight: 600;
    font-size: 1rem;
}

.employee-selection {
    margin-bottom: 1.5rem;
}

.employees-list {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-top: 1rem;
}

.employee-option {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    transition: all 0.3s ease;
}

.employee-option:last-child {
    border-bottom: none;
}

.employee-option:hover {
    background: var(--surface-soft);
}

.employee-option.selected {
    background: rgba(99, 102, 241, 0.05);
    border-left: 3px solid var(--primary);
}

.employee-avatar-sm {
    width: 40px;
    height: 40px;
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

.employee-details {
    flex: 1;
}

.employee-details h6 {
    margin: 0 0 0.25rem 0;
    color: var(--dark);
    font-weight: 600;
    font-size: 0.9rem;
}

.employee-details p {
    margin: 0 0 0.25rem 0;
    color: var(--text-muted);
    font-size: 0.8rem;
}

.employee-id {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-family: monospace;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--dark);
    font-size: 0.9rem;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text);
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-control:disabled {
    background: var(--surface-soft);
    color: var(--text-muted);
    cursor: not-allowed;
}

.assignment-preview {
    margin-top: 1.5rem;
}

.preview-card {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1.5rem;
}

.preview-header {
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border);
}

.preview-header h6 {
    margin: 0;
    color: var(--dark);
    font-weight: 600;
}

.preview-content {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.preview-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
}

.preview-item .label {
    color: var(--text-muted);
    font-weight: 500;
}

.preview-item .value {
    color: var(--dark);
    font-weight: 600;
}

/* Transfer Modal Styles */
.transfer-member-form {
    padding: 0.5rem;
}

.current-assignment, .transfer-options {
    background: var(--surface-soft);
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.assignment-info {
    display: flex;
    align-items: center;
    gap: 2rem;
    margin-top: 1rem;
}

.member-info, .current-team {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.team-info {
    display: flex;
    flex-direction: column;
}

.team-info strong {
    color: var(--dark);
    font-weight: 600;
}

.team-info span {
    color: var(--text-muted);
    font-size: 0.85rem;
}

.leaders-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.leader-option {
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.leader-option:hover {
    border-color: var(--primary-light);
    transform: translateY(-2px);
}

.leader-option.selected {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.leader-details {
    flex: 1;
}

.leader-details strong {
    display: block;
    margin-bottom: 0.25rem;
    color: var(--dark);
}

.leader-details span {
    color: var(--text-muted);
    font-size: 0.85rem;
}

.team-stats small {
    color: var(--text-muted);
    font-size: 0.75rem;
}

/* Bulk Assignment Styles */
.bulk-assignment-form {
    padding: 0.5rem;
}

.stepper-container {
    border-bottom: 1px solid var(--border);
    padding-bottom: 1.5rem;
}

.stepper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
}

.stepper::before {
    content: '';
    position: absolute;
    top: 20px;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--border);
    z-index: 1;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 2;
    flex: 1;
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--border);
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    margin-bottom: 0.5rem;
    transition: all 0.3s ease;
}

.step.active .step-number {
    background: var(--primary);
    color: white;
}

.step.completed .step-number {
    background: var(--success);
    color: white;
}

.step-label {
    font-size: 0.85rem;
    color: var(--text-muted);
    font-weight: 500;
}

.step.active .step-label {
    color: var(--primary);
    font-weight: 600;
}

.step-content {
    min-height: 400px;
    padding: 1rem 0;
}

.step-title {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-weight: 600;
}

.step-description {
    margin: 0 0 1.5rem 0;
    color: var(--text-muted);
}

.selection-summary {
    text-align: center;
    padding: 1rem;
    background: var(--surface-soft);
    border-radius: 8px;
}

.selected-count {
    color: var(--primary);
    font-weight: 600;
}

.members-selection-grid, .leaders-selection-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.member-selection-card, .leader-selection-card {
    background: var(--surface-soft);
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.member-selection-card:hover, .leader-selection-card:hover {
    border-color: var(--primary-light);
    transform: translateY(-2px);
}

.member-selection-card.selected, .leader-selection-card.selected {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.leader-avatar-md {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1rem;
    flex-shrink: 0;
}

.leader-details h6 {
    margin: 0 0 0.25rem 0;
    color: var(--dark);
    font-weight: 600;
}

.leader-details p {
    margin: 0 0 0.5rem 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.team-info {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
}

.team-name {
    color: var(--primary);
    font-weight: 500;
}

.member-count {
    color: var(--text-muted);
}

.confirmation-details {
    background: var(--surface-soft);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.assignment-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: var(--surface);
    border-radius: 8px;
}

.summary-item strong {
    color: var(--dark);
}

.summary-item span {
    color: var(--primary);
    font-weight: 600;
}

.members-preview h6 {
    margin: 0 0 1rem 0;
    color: var(--dark);
    font-weight: 600;
}

.preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 0.75rem;
}

.preview-member {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: var(--surface);
    border-radius: 8px;
    border: 1px solid var(--border);
}

.member-name {
    font-size: 0.85rem;
    color: var(--dark);
    font-weight: 500;
}

.step-actions {
    display: flex;
    justify-content: flex-end;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
}

/* Team Details Modal Styles */
.team-details-content {
    max-height: 70vh;
    overflow-y: auto;
}

.leader-info-section {
    background: var(--surface-soft);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border);
}

.leader-info-card {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.leader-avatar-lg {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.8rem;
    flex-shrink: 0;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.leader-details-lg h4 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-weight: 700;
    font-size: 1.3rem;
}

.leader-details-lg .position {
    margin: 0 0 0.25rem 0;
    color: var(--primary);
    font-weight: 600;
    font-size: 1rem;
}

.leader-details-lg .department {
    margin: 0 0 0.5rem 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.team-members-section {
    background: var(--surface-soft);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.section-title {
    margin: 0;
    color: var(--dark);
    font-weight: 600;
    font-size: 1.1rem;
}

.member-count-badge {
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.team-members-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
    max-height: 400px;
    overflow-y: auto;
    padding: 0.5rem;
}

.team-member-detail-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s ease;
}

.team-member-detail-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
    border-color: var(--primary-light);
}

.member-info {
    flex: 1;
}

.member-info h6 {
    margin: 0 0 0.25rem 0;
    color: var(--dark);
    font-weight: 600;
    font-size: 0.95rem;
}

.member-position {
    margin: 0 0 0.25rem 0;
    color: var(--text-muted);
    font-size: 0.85rem;
}

.member-department {
    margin: 0 0 0.5rem 0;
    color: var(--text-muted);
    font-size: 0.8rem;
}

.member-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.employee-id {
    background: var(--surface-soft);
    color: var(--text-muted);
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-family: monospace;
}

/* Improved Button Styles */
.btn-modern {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    position: relative;
    overflow: hidden;
}

.btn-modern:before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn-modern:hover:before {
    left: 100%;
}

.btn-modern:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.btn-modern.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-modern.btn-primary:hover:not(:disabled) {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.btn-modern.btn-secondary {
    background: var(--surface);
    color: var(--text);
    border: 2px solid var(--border);
}

.btn-modern.btn-secondary:hover:not(:disabled) {
    background: var(--surface-soft);
    border-color: var(--primary-light);
    transform: translateY(-2px);
}

.btn-modern.btn-success {
    background: var(--success);
    color: white;
}

.btn-modern.btn-success:hover:not(:disabled) {
    background: var(--hcis-emerald-600);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

/* Animation for smooth transitions */
.fade-in {
    animation: fadeIn 0.5s ease-in-out;
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

/* Custom scrollbar */
.members-list::-webkit-scrollbar,
.leaders-list::-webkit-scrollbar,
.team-members-list::-webkit-scrollbar,
.employees-list::-webkit-scrollbar {
    width: 6px;
}

.members-list::-webkit-scrollbar-track,
.leaders-list::-webkit-scrollbar-track,
.team-members-list::-webkit-scrollbar-track,
.employees-list::-webkit-scrollbar-track {
    background: var(--surface-soft);
    border-radius: 3px;
}

.members-list::-webkit-scrollbar-thumb,
.leaders-list::-webkit-scrollbar-thumb,
.team-members-list::-webkit-scrollbar-thumb,
.employees-list::-webkit-scrollbar-thumb {
    background: var(--primary-light);
    border-radius: 3px;
}

.members-list::-webkit-scrollbar-thumb:hover,
.leaders-list::-webkit-scrollbar-thumb:hover,
.team-members-list::-webkit-scrollbar-thumb:hover,
.employees-list::-webkit-scrollbar-thumb:hover {
    background: var(--primary);
}

/* Enhanced Responsive Design */
@media (max-width: 768px) {
    .team-filters-bar .row {
        gap: 1rem;
    }

    .team-filters-bar .col-md-2,
    .team-filters-bar .col-md-3 {
        flex: 1 1 100%;
    }

    .filter-actions {
        justify-content: space-between;
        width: 100%;
    }

    .modern-table {
        display: block;
        overflow-x: auto;
    }

    .employee-option {
        padding: 0.75rem;
    }

    .preview-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .assignment-interface .row {
        flex-direction: column;
    }

    .assignment-actions {
        flex-direction: row;
        margin: 1rem 0;
    }

    .leaders-container {
        grid-template-columns: 1fr;
    }

    .team-members-grid {
        grid-template-columns: 1fr;
    }

    .leader-info-card {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }

    .team-member-detail-card {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }

    .member-actions {
        flex-direction: row;
        justify-content: center;
    }

    .stepper {
        flex-direction: column;
        gap: 1rem;
    }

    .stepper::before {
        display: none;
    }

    .members-selection-grid,
    .leaders-selection-grid {
        grid-template-columns: 1fr;
    }

    .assignment-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .leaders-grid {
        grid-template-columns: 1fr;
    }

    .pagination-container {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
}

@media (max-width: 576px) {
    .analytics-overview .row {
        grid-template-columns: 1fr;
    }

    .preview-grid {
        grid-template-columns: 1fr;
    }

    .assignment-summary {
        grid-template-columns: 1fr;
    }

    .department-grid {
        grid-template-columns: 1fr;
    }

    .team-actions-bar .row {
        flex-direction: column;
        gap: 1rem;
    }

    .bulk-actions-header {
        flex-direction: column;
        gap: 1rem;
    }

    .leader-avatar-lg {
        width: 60px;
        height: 60px;
        font-size: 1.4rem;
    }

    .leader-details-lg h4 {
        font-size: 1.1rem;
    }
}

/* Utility Classes */
.text-muted {
    color: var(--text-muted) !important;
}

.w-100 {
    width: 100% !important;
}

.gap-2 {
    gap: 0.5rem !important;
}

.mt-3 {
    margin-top: 1rem !important;
}

.mt-4 {
    margin-top: 1.5rem !important;
}

.mb-3 {
    margin-bottom: 1rem !important;
}

.mb-4 {
    margin-bottom: 1.5rem !important;
}

.me-2 {
    margin-right: 0.5rem !important;
}

.ms-2 {
    margin-left: 0.5rem !important;
}

.d-flex {
    display: flex !important;
}

.justify-content-center {
    justify-content: center !important;
}

.justify-content-between {
    justify-content: space-between !important;
}

.align-items-center {
    align-items: center !important;
}

.flex-column {
    flex-direction: column !important;
}

.text-center {
    text-align: center !important;
}

.text-end {
    text-align: end !important;
}

.d-none {
    display: none !important;
}

.d-block {
    display: block !important;
}

@media (min-width: 992px) {
    .d-lg-block {
        display: block !important;
    }

    .d-lg-none {
        display: none !important;
    }
}
</style>

<style scoped>
/* Consolidated and deduplicated styles */
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
    --danger-light: var(--hcis-legacy-bootstrap-danger); /* Merah muda, lebih terang untuk latar belakang atau efek disabled */
    --gray-light: var(--hcis-legacy-bootstrap-gray);
    --el-slider-main-bg-color: var(--hcis-indigo-500);
    --el-calendar-cell-width: 50px !important;
}

/* info bar */
.leave-info-panel {
}

.info-card {
    transition: all 0.3s ease;
    border: 1px solid rgba(0, 0, 0, 0.05);
    border-radius: 12px !important;
}

.info-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}

.btnModal {
    flex-grow: 1;
    justify-content: center;
}

.fontFooter {
    /* font-size: 0.9rem; */
    padding: 0.5rem 0rem;
}
.footerModal {
    flex-direction: column;
}
.icon-circle {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Color variants */
.bg-primary-light {
    background-color: rgba(13, 110, 253, 0.1);
}

.bg-warning-light {
    background-color: rgba(255, 193, 7, 0.1);
}

.bg-danger-light {
    background-color: rgba(220, 53, 69, 0.1);
}

/* Animation */
.fade-in {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .icon-circle {
        width: 30px;
        height: 30px;
        margin-top: 8px;
        margin-right: 8px;
        font-size: 0.9rem;
        position: absolute;
        right: 0;
        top: 0;
    }
    .fontFooter {
        font-size: 0.9rem;
        padding: 0.5rem 0rem;
    }
    .footerModal {
        flex-direction: column;
    }
    .info-card {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: space-between;
    }

    .info-value {
        font-size: 1.25rem !important;
    }
}

/* akhir infobar */

/* cell calendar skin */
.is-red-date .date-cell {
    color: red !important;
}

.is-orange-date .date-cell {
    color: rgb(255, 166, 0) !important;
}

/* Style yang sudah ada untuk rentang tanggal */
.is-start .date-cell {
    background-color: lightblue;
    border-radius: 50%; /* Contoh */
}
.is-end .date-cell {
    background-color: lightblue;
    border-radius: 50%; /* Contoh */
}
.is-in-range .date-cell {
    background-color: lightcyan; /* Contoh */
}

/* Style untuk tanggal yang dinonaktifkan secara custom */
.is-disabled-custom .date-cell {
    opacity: 0.5; /* Membuat tanggal terlihat abu-abu atau pudar */
    cursor: not-allowed;
    pointer-events: none;
}

/* Pastikan style dasar untuk .date-cell */
.date-cell {
    text-align: center;
    line-height: 20px; /* Sesuaikan sesuai kebutuhan layout Anda */
    /* Tambahkan style dasar lain jika diperlukan */
}
.is-red-date .date-cell {
    color: red !important;
}

/* akhir cell calnedar */
.glass-warning {
    background: rgba(245, 158, 11, 0.2);
    border-radius: 20px;
    color: var(--surface);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(245, 158, 11, 0.3);
    transition: all 0.3s ease;
}
.glass-success {
    background: rgba(16, 185, 129, 0.2);
    border-radius: 20px;
    color: var(--surface);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(16, 185, 129, 0.3);
    transition: all 0.3s ease;
}
.glass-danger {
    background: rgba(239, 68, 68, 0.2);
    border-radius: 20px;
    color: var(--surface);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(239, 68, 68, 0.3);
    transition: all 0.3s ease;
}

.infoBar {
    background: rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    color: var(--surface);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.3);
    transition: all 0.3s ease;
}

.infoBar:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

/* Ensure tooltips are visible */
.custom-tooltip .el-tooltip__popper {
    opacity: 1 !important;
    display: block !important;
}

/* Fix z-index issues */
.el-slider__button-wrapper {
    z-index: 100 !important;
}

/* Parent container should not hide overflow */
.input-group {
    overflow: visible !important;
}

/* overlay dialog */
.el-overlay-dialog {
    display: flex;
    justify-content: center;
    align-items: center;
    max-height: 100vh;
    max-width: 100vw;
    overflow: hidden;
}

.el-dialog {
    display: flex;
    margin: 0px;
    flex-direction: column; /* Tata header, body, footer secara vertikal */
    max-height: calc(
        100vh - 0px
    ); /* Sesuaikan Xpx untuk margin/padding di sekitar modal */
    /* Anda mungkin ingin menambahkan background-color dan box-shadow di sini */
}

.el-dialog__body {
    flex-grow: 1; /* Biarkan body mengisi sisa ruang yang tersedia */
    overflow-y: auto; /* *Ini Kunci Utamanya*: Biarkan body modal discroll jika kontennya terlalu panjang */
    padding: 1rem; /* Atur padding sesuai kebutuhan */
}

.el-dialog__header,
.el-dialog__footer {
    flex-shrink: 0; /* Pastikan header/footer tidak mengecil */
}
/* akhir dialog */
.animated-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

.attachment-link.is-loading {
    pointer-events: none;
    opacity: 0.7;
}

/* Modal container */
.detail-modal {
    border-radius: 8px;
}

.detail-modal .el-dialog__header {
    border-bottom: 1px solid #eee;
    padding: 16px 20px;
}

.detail-modal .el-dialog__body {
    padding: 20px;
}
/* Tooltip container adjustment */
.el-popper {
    border: none !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
    border-radius: 8px !important;
    padding: 2px !important;
    font-size: 1rem;
    font-weight: 600;
    color: var(--primary) !important;
    background: white !important;
}

.el-popper__arrow {
    display: none !important;
}

.el-tooltip__popper.is-dark {
    background: transparent !important;
    border: none !important;
}

/* Grid layout for details */
.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    margin-bottom: 8px;
}

.detail-item.full-width {
    grid-column: 1 / -1;
}

.detail-label {
    font-weight: 500;
    color: #606266;
    margin-bottom: 4px;
    font-size: 14px;
}

.detail-value {
    color: #303133;
    font-size: 15px;
}

.closeFilter {
    transition: all 0.3s ease;
    background: var(--surface);
}

.closeFilter:hover {
    color: white;
    background: var(--danger);
}

.detail-value.highlight {
    font-weight: 600;
    color: #409eff;
}

.detail-value.multiline {
    white-space: pre-line;
    line-height: 1.6;
}

.empty-value {
    color: #909399;
    font-style: italic;
}

/* Approval progress */
.approval-progress {
    margin-top: 24px;
    padding-top: 16px;
    border-top: 1px dashed #eaeaea;
}

.progress-title {
    font-weight: 500;
    margin-bottom: 12px;
    color: #606266;
}

/* Footer buttons */
.modal-footer-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 12px 20px;
    border-top: 1px solid #eee;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .detail-grid {
        grid-template-columns: 1fr;
    }

    .detail-modal .el-dialog {
        margin: 0;
        max-height: 100vh;
    }
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
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    position: relative;
    overflow: hidden;
}
.action-menu {
    position: absolute !important; /* Popup akan mengikuti posisi elemen yang diklik */
    z-index: 1000; /* Pastikan popup tetap di atas elemen lainnya */
    background-color: white;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    border-radius: 4px;
    padding: 10px;
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

.btn-secondary {
    background: var(--surface);
    color: var(--text);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    background: var(--surface-soft);
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: var(--hcis-emerald-600);
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

#app
    > div:nth-child(3)
    > div
    > div
    > div
    > div
    > div:nth-child(6)
    > div.card-body.row
    > div.col-md-4.col-6
    > div
    > div.el-step.is-vertical.is-flex
    > div.el-step__head.is-error
    > div.el-step__icon.is-text
    > i {
    display: flex;
    justify-content: center;
    align-items: center;
}

.btn-danger {
    background: var(--danger);
    color: white;
}
.btn-danger:hover {
    background: #c23030;
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.shiningEffect {
    transition: all 0.3s ease;
    overflow: hidden;
    position: relative;
}

.shiningEffect::before {
    content: "";
    position: absolute;
    top: 0%;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.6),
        transparent
    );
    transition: left 0.5s;
}

.shiningEffect:hover::before {
    left: 100%;
}

/* Header */
.modern-header {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: var(--shadow);
    /* box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); */
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

/* Control Panel (Search, Filter, View Toggle) */
.control-panel {
    background: var(--surface);
    border-radius: 20px;
    padding: 0.5rem;
    /* box-shadow: var(--shadow); */
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border);
}

.search-filter-section {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.search-container {
    position: relative;
    flex: 1;
    min-width: 280px;
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    z-index: 2;
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 2px solid var(--border);
    border-radius: 12px;
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.search-results-count {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}

.search-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--surface);
    border-radius: 0 0 12px 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    z-index: 100;
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid var(--border);
    border-top: none;
}
.suggestion-item {
    padding: 0.75rem 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.suggestion-item:hover {
    background: var(--surface-soft);
}

.filters-container {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-dropdown {
    position: relative;
}

.filter-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 0.5rem;
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text);
    cursor: pointer;
    transition: all 0.3s ease;
    justify-content: space-between;
}

.filter-btn:hover {
    background: var(--surface);
    border-color: var(--primary);
}

.view-toggle {
    display: flex;
    background: var(--surface-soft);
    border-radius: 10px;
    padding: 0.25rem;
}

.toggle-btn {
    padding: 0.5rem;
    border: none;
    background: transparent;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    color: var(--text-muted);
}

.toggle-btn.active {
    background: var(--primary);
    color: white;
}

.toggle-btn:hover:not(.active) {
    background: var(--surface);
    color: var(--text);
}

/* Content Container & Loading/Empty States */
.content-container {
    background: var(--surface);
    border-radius: 20px;
    overflow: hidden;
    /* box-shadow: var(--shadow); */
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border);
}

.loading-container,
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    text-align: center;
    min-height: 300px;
}

.loading-animation {
    position: relative;
    margin-bottom: 1.5rem;
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

.empty-icon {
    font-size: 3rem;
    color: var(--border);
    margin-bottom: 1rem;
    opacity: 0.7;
}
.empty-state h4 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}
.empty-state p {
    margin-bottom: 1.5rem;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

/* Table View */
.table-view-container {
    width: 100%;
    overflow-x: auto;
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
}
.modern-table th,
.modern-table td {
    padding: 0.75rem 1rem;
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
}
.header-cell {
    white-space: nowrap;
}
.header-cell-content {
    display: flex;
    align-items: center;
    justify-content: center;
}
.badge-count {
    margin-left: 0.5rem;
    background: var(--primary);
    color: white;
    border-radius: 10px;
    padding: 0.15rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
}
.sticky-column {
    position: sticky;
    left: 0;
    background: var(--surface) !important;
    z-index: 11;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
}
.table-row {
    transition: background-color 0.2s ease;
    cursor: pointer;
}
.table-row:hover {
    background-color: var(--surface-soft);
}
.transaction-cell {
    display: flex;
    flex-direction: column;
}
.transaction-id {
    font-weight: 500;
    color: var(--text);
}
.transaction-type {
    font-size: 0.75rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    margin-top: 0.25rem;
}
.type-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 0.5rem;
}
.type-sakit .type-indicator {
    background: var(--danger);
}
.type-terlambat .type-indicator {
    background: var(--warning);
}
.type-pulang-cepat .type-indicator {
    background: var(--info);
}
.type-tidak-masuk .type-indicator,
.type-cuti-tahunan .type-indicator,
.type-izin-pribadi .type-indicator {
    background: var(--primary);
}

.date-cell {
    white-space: nowrap;
    width: 100%;
}
.reason-cell {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.attachment-cell {
    display: flex;
    align-items: center;
    justify-content: start;
}
.attachment-link {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--primary);
    text-decoration: none;
}
.attachment-link:hover {
    text-decoration: underline;
}
.no-attachment {
    color: var(--text-muted);
}
.status-cell {
    display: flex;
    justify-content: center;
}
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.status-approved {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}
.status-pending {
    background: rgba(6, 182, 212, 0.1);
    color: var(--info);
}
.status-rejected {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.action-cell {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
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
}
.btn-action:hover {
    background: var(--surface-soft);
}
.btn-action.delete-btn {
    color: var(--danger);
}
.btn-action.delete-btn:disabled {
    color: var(--danger-light);
    /* background-color: var(--gray-light); */
    cursor: not-allowed;
    opacity: 0.6;
}
.btn-action.delete-btn:hover {
    background: rgba(239, 68, 68, 0.1);
}
.btn-action.more-btn {
    color: var(--text-muted);
}
.btn-action.more-btn:hover {
    color: var(--text);
}

/* Card View */
.card-view-container {
    padding: 1rem;
}
.card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.25rem;
}
.permission-card {
    background: var(--surface);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--border);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}
.permission-card:hover {
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}
.card-header {
    padding: 1rem;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.header-approved {
    background: linear-gradient(135deg, var(--hcis-emerald-400) 0%, #059669ce 100%);
}
.header-pending {
    background: linear-gradient(135deg, var(--hcis-indigo-400) 0%, #6365f1ce 100%);
}
.header-rejected {
    background: linear-gradient(135deg, #f472b5 0%, #ec489ad3 100%);
}
.card-date {
    font-weight: 500;
    font-size: 0.9rem;
}
.card-status {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.2);
}
.card-body {
    padding: 1rem;
    flex-grow: 1;
}
.card-row {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    font-size: 0.875rem;
}
.card-row i {
    color: var(--primary);
    margin-top: 0.1rem;
    flex-shrink: 0;
}
.card-label {
    color: var(--text-muted);
    flex-shrink: 0;
    width: 80px;
}
.card-value {
    color: var(--text);
    word-break: break-word;
}
.card-link {
    color: var(--primary);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.card-link:hover {
    text-decoration: underline;
}
.no-file {
    color: var(--text-muted);
    font-style: italic;
}
.card-footer {
    padding: 0.75rem 1rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
}
.card-btn {
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
.card-btn.delete-btn {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}
.card-btn.delete-btn:hover {
    background: rgba(239, 68, 68, 0.2);
}

.card-btn.delete-btn:disabled {
    color: var(--danger-light);
    background-color: var(--gray-light);
    cursor: not-allowed;
    opacity: 0.6;
}
.card-btn.more-btn {
    background: var(--surface-soft);
    color: var(--text-muted);
}
.card-btn.more-btn:hover {
    background: var(--border);
    color: var(--text);
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 1.5rem 0;
    border-top: 1px solid var(--border);
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

/* Detail Modal */
.detail-modal-content {
    padding: 0.5rem;
}
.detail-section {
    margin-bottom: 1.5rem;
}
.detail-row {
    display: flex;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}
.detail-label {
    width: 120px;
    font-weight: 500;
    color: var(--text-muted);
}
.detail-value {
    flex: 1;
    color: var(--text);
}
.detail-link {
    color: var(--primary);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.detail-link:hover {
    text-decoration: underline;
}
.detail-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

/* Delete Confirmation Modal */
.delete-modal-content {
    text-align: center;
    padding: 1rem;
}
.warning-icon {
    font-size: 3rem;
    color: var(--danger);
    margin-bottom: 1rem;
}
.delete-modal-footer {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

/* Action Menu */
.action-menu {
    position: fixed;
    z-index: 2000;
    background: var(--surface);
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    min-width: 180px;
    overflow: hidden;
    animation: fadeInUp 0.2s ease;
}
.action-menu-item {
    padding: 0.75rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.2s ease;
}
.action-menu-item:hover {
    background: var(--surface-soft);
}
.action-menu-item.text-danger {
    color: var(--danger);
}
.action-menu-item.text-danger:hover {
    background: rgba(239, 68, 68, 0.1);
}
.action-menu-divider {
    height: 1px;
    background: var(--border);
    margin: 0.25rem 0;
}
.action-menu-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1999;
    background: rgba(0, 0, 0, 0.3);
}

/* Assign Modal (New/Edit Permission) */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1048;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-overlay.show {
    opacity: 1;
    visibility: visible;
}

.modal-container {
    background: var(--surface);
    border-radius: 20px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    transform: scale(0.9) translateY(20px);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}

.modal-container.assign-modal {
    max-width: 700px;
    max-height: 95vh;
}

.modal-overlay.show .modal-container {
    transform: scale(1) translateY(0);
}
.section-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border);
    background: var(--surface-soft);
}

.header-title-section h3 {
    margin: 0 0 0.25rem 0;
    font-weight: 600;
    color: var(--text);
    display: flex;
    align-items: center;
}

.header-subtitle {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.modal-close {
    width: 40px;
    height: 40px;
    border: none;
    background: var(--surface);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    color: var(--text-muted);
}

.modal-close:hover {
    background: var(--danger);
    color: white;
}

.modal-body {
    padding: 1rem 2rem;
    max-height: calc(95vh - 200px);
    overflow-y: auto;
    flex: 1;
}
.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 1rem;
    justify-content: end;
}
.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #eee;
    background-color: #f8f9fa;
    border-bottom-left-radius: 12px;
    border-bottom-right-radius: 12px;
}
.footer-actions {
    display: flex;
    gap: 0.5rem;
}
.form-section {
    padding: 0.2rem;
}

.form-group {
    margin-bottom: 1.5rem; /* Standard margin for form groups */
    border: 1px solid var(--border); /* Added for visual separation */
    background: rgba(99, 102, 241, 0.05); /* Soft background */
    border-radius: 12px;
    padding: 1rem;
    overflow-y: auto; /* Ensure content within is scrollable if needed */
    max-height: 18rem; /* Cap height to prevent excessive modal height */
}

.form-group.AssignRingkasan {
    max-height: 25rem;
}

.form-group label,
.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text);
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    transform: translateY(-1px);
}

.permission-type-options {
    margin-bottom: 0rem;
}

.permission-type-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
    position: relative;
    background-color: var(--surface);
}

.permission-type-card:hover {
    border-color: #adb5bd;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.permission-type-card.selected {
    border-color: var(--primary);
    background-color: rgba(13, 110, 253, 0.05);
}

.permission-type-icon {
    font-size: 1.5rem;
    color: var(--primary);
    margin-bottom: 0.75rem;
}

.permission-type-content h6 {
    font-size: 0.9375rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.permission-type-check {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    font-size: 1rem;
    color: #adb5bd;
}

.permission-type-check .bi-check-circle-fill {
    color: #0d6efd;
}

/* File Upload */
.file-upload-wrapper {
    position: relative;
}
.file-upload-area {
    cursor: pointer;
    border: 1px dashed var(--border);
    border-radius: 0.375rem;
    padding: 1rem;
    min-height: 10rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    background-color: var(--surface);
}

.file-upload-area:hover {
    background-color: var(--hcis-legacy-bootstrap-gray);
    border-color: #adb5bd;
}

.file-upload-label {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 0.5rem;
    color: var(--text-muted);
    font-weight: 500;
    cursor: pointer;
    width: 100%;
    height: 100%;
}

.file-upload-label i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.file-display-area {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border);
    border-radius: 12px;
    background-color: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    min-height: fit-content;
}

.file-name {
    flex-grow: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-right: 1rem;
}

.btn-remove-file {
    background: none;
    border: none;
    color: var(--danger);
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    border-radius: 50%;
    transition: background-color 0.2s ease;
}

.btn-remove-file:hover {
    background-color: rgba(239, 68, 68, 0.1);
}

/* Calendar Modals (Element Plus specific overrides) */
.el-overlay {
    z-index: 1050;
}
.el-dialog__body {
    padding-top: 10px;
    padding-bottom: 10px;
}
.el-calendar-table .el-calendar-day {
    padding: 2px !important;
    height: 50px !important;
}
.cell-date-wrapper {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 1;
}
.date-table-cell {
    width: 100%;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: start;
    border-radius: 50%;
    cursor: pointer;
    transition: background-color 0.2s, color 0.2s;
    position: relative;
    z-index: 2;
}
.date-cell {
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    cursor: pointer;
    transition: background-color 0.2s, color 0.2s;
    position: relative;
    z-index: 2;
}
.cell-date-wrapper:hover .date-cell {
    background-color: #d9ecff;
}
.is-start .date-cell,
.is-end .date-cell {
    background-color: #409eff;
    color: white;
}
.is-in-range::before {
    content: "";
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    right: 0;
    background-color: #ecf5ff;
    z-index: 0;
}
.is-start::before {
    left: 50%;
}
.is-end::before {
    right: 50%;
}
.is-disabled-custom {
    color: #c0c4cc !important;
    cursor: not-allowed !important;
    background-color: #f5f7fa !important;
    /* pointer-events: none; */
}
.is-disabled-custom .date-cell {
    background-color: transparent !important;
}
.is-disabled-custom:hover .date-cell {
    background-color: transparent !important;
}

/* Transitions */
.fade-in {
    animation: fadeIn 0.5s ease-in-out;
}
.fade-switch-enter-active,
.fade-switch-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.fade-switch-enter-from,
.fade-switch-leave-to {
    opacity: 0;
    transform: translateY(10px);
}
@keyframes float {
    0%,
    100% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-10px);
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
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

/* Responsive Adjustments */
@media (max-width: 1200px) {
    .modern-header {
        min-height: 120px;
    }
    .header-content {
        padding: 2rem;
    }
    .header-actions {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-end;
    }
    .date-range {
        padding: 0.75rem 1rem;
    }
}

@media (max-width: 992px) {
    .header-info {
        width: 100%;
        padding-bottom: 0.6rem;
        gap: 1rem;
    }
    .el-dialog.is-align-center {
        margin: auto 1rem;
    }
    .el-calendar__header {
        padding: 1px 0.2rem;
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
    .search-filter-section {
        align-items: stretch;
    }
    .filters-container {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .card-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    }
    .detail-row {
        flex-direction: column;
        gap: 0.25rem;
    }
    .detail-label {
        width: 100%;
    }
}

@media (max-width: 768px) {
    .el-calendar__header {
        flex-direction: column;
        justify-content: center;
        align-items: start;
    }
    .el-calendar__title {
        align-self: start;
    }
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
    .control-panel {
        padding: 1rem;
    }
    .search-container {
        min-width: auto;
    }
    .modern-table {
        font-size: 0.8rem;
    }
    .sticky-column {
        min-width: 150px;
        position: relative;
        left: auto;
        /* z-index: 100; */
        /* box-shadow: none; */
    }
    .modal-container {
        margin: 0.5rem;
        max-height: 83vh;
    }
    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 1rem;
    }
    .modal-footer .btn-modern {
        width: 100%;
        justify-content: center;
    }
    .card-grid {
        grid-template-columns: 1fr;
    }
    .pagination-container {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .page-numbers {
        order: 1;
        width: 100%;
        justify-content: center;
        margin: 0.5rem 0;
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
    .el-calendar__body {
        padding: 0 !important;
        padding-top: 1rem !important;
    }
    .control-panel {
        padding: 0.75rem;
        margin-bottom: 0.5rem;
    }
    .search-filter-section {
        flex-direction: column;
        gap: 0.75rem;
    }
    .table-responsive-wrapper {
        border-radius: 12px;
    }
    .card-view-container {
        padding: 0.75rem;
    }
    .card-row {
        flex-direction: column;
        gap: 0.25rem;
    }
    .card-label {
        width: 100%;
    }
    .modal-container.assign-modal {
        width: 98%;
        max-height: 90vh;
    }
    .form-section {
        padding: 0.2rem;
    }
    .form-group {
        padding: 0.8rem;
    }
    .permission-type-card {
        padding: 0.75rem;
    }
    .permission-type-icon {
        font-size: 1.25rem;
        margin-bottom: 0.5rem;
    }
    .permission-type-content h6 {
        font-size: 0.7rem;
    }
    .page-number,
    .page-btn {
        min-width: 30px;
        height: 30px;
        margin: 0 0.1rem;
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
</style>
