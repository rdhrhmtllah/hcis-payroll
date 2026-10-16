<template>
    <div class="container-fluid px-0">
        <!-- Header Section -->
        <div
            class="row d-flex align-items-center justify-content-center mb-3 fade-in"
        >
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
                                    <i
                                        class="bi bi-search d-flex justify-content-center align-items-center"
                                    ></i>
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
                                        <i
                                            class="bi bi-person-plus d-flex justify-content-center align-items-center me-2"
                                        ></i>
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
                                    <span class="filter-count"
                                        >{{
                                            filteredTeamMembers.length
                                        }}
                                        hasil</span
                                    >
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
                                            <span
                                                >{{
                                                    leader.memberCount
                                                }}
                                                anggota</span
                                            >
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
                                                <th class="header-cell">
                                                    Nama Karyawan
                                                </th>
                                                <th class="header-cell">
                                                    Posisi
                                                </th>
                                                <th class="header-cell">
                                                    Departemen
                                                </th>
                                                <th class="header-cell">Tim</th>
                                                <th class="header-cell">
                                                    Leader
                                                </th>
                                                <th class="header-cell">
                                                    Jumlah Tim
                                                </th>
                                                <th class="header-cell">
                                                    Status
                                                </th>
                                                <th class="header-cell">
                                                    Aksi
                                                </th>
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
                                                            {{
                                                                getInitials(
                                                                    member.name
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
                                                                    member.name
                                                                }}
                                                            </div>
                                                            <div
                                                                class="employee-code"
                                                            >
                                                                {{
                                                                    member.employeeId
                                                                }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{{ member.position }}</td>
                                                <td>{{ member.department }}</td>
                                                <td>
                                                    <span class="team-badge">{{
                                                        member.team ||
                                                        "Belum Ditugaskan"
                                                    }}</span>
                                                </td>
                                                <td>
                                                    <span
                                                        v-if="member.leader"
                                                        class="leader-info"
                                                    >
                                                        {{
                                                            getLeaderName(
                                                                member.leaderId
                                                            )
                                                        }}
                                                    </span>
                                                    <span
                                                        v-else
                                                        class="text-muted"
                                                        >-</span
                                                    >
                                                </td>
                                                <td>
                                                    <span class="count-badge">{{
                                                        member.teamCount || 0
                                                    }}</span>
                                                </td>
                                                <td>
                                                    <span
                                                        :class="`status-badge status-${member.status}`"
                                                    >
                                                        {{
                                                            getStatusText(
                                                                member.status
                                                            )
                                                        }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-cell">
                                                        <button
                                                            class="btn-action btn-view"
                                                            @click="
                                                                viewMemberDetails(
                                                                    member
                                                                )
                                                            "
                                                            title="Lihat Detail"
                                                        >
                                                            <i
                                                                class="bi bi-eye"
                                                            ></i>
                                                        </button>
                                                        <button
                                                            class="btn-action btn-edit"
                                                            @click="
                                                                editMember(
                                                                    member
                                                                )
                                                            "
                                                            title="Edit"
                                                        >
                                                            <i
                                                                class="bi bi-pencil"
                                                            ></i>
                                                        </button>
                                                        <button
                                                            class="btn-action btn-transfer"
                                                            @click="
                                                                showTransferModal(
                                                                    member
                                                                )
                                                            "
                                                            title="Pindah Tim"
                                                        >
                                                            <i
                                                                class="bi bi-arrow-left-right"
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
                                                <h6 class="member-name">
                                                    {{ member.name }}
                                                </h6>
                                                <p class="member-position">
                                                    {{ member.position }}
                                                </p>
                                                <div class="member-meta">
                                                    <span class="member-id">{{
                                                        member.employeeId
                                                    }}</span>
                                                    <span
                                                        class="member-department"
                                                        >{{
                                                            member.department
                                                        }}</span
                                                    >
                                                </div>
                                            </div>
                                            <div class="member-status">
                                                <span
                                                    :class="`status-badge status-${member.status}`"
                                                >
                                                    {{
                                                        getStatusText(
                                                            member.status
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="member-team-info">
                                            <div class="team-details">
                                                <div class="team-item">
                                                    <i class="bi bi-people"></i>
                                                    <span>{{
                                                        member.team ||
                                                        "Belum Ditugaskan"
                                                    }}</span>
                                                </div>
                                                <div
                                                    class="team-item"
                                                    v-if="member.leader"
                                                >
                                                    <i
                                                        class="bi bi-person-badge"
                                                    ></i>
                                                    <span>{{
                                                        getLeaderName(
                                                            member.leaderId
                                                        )
                                                    }}</span>
                                                </div>
                                                <div class="team-item">
                                                    <i class="bi bi-layers"></i>
                                                    <span
                                                        >{{
                                                            member.teamCount ||
                                                            0
                                                        }}
                                                        Tim</span
                                                    >
                                                </div>
                                            </div>
                                        </div>

                                        <div class="member-actions">
                                            <button
                                                class="btn-action btn-view"
                                                @click="
                                                    viewMemberDetails(member)
                                                "
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
                                                @click="
                                                    showTransferModal(member)
                                                "
                                                title="Pindah Tim"
                                            >
                                                <i
                                                    class="bi bi-arrow-left-right"
                                                ></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pagination -->
                            <div
                                class="pagination-container mt-4"
                                v-if="filteredTeamMembers.length > itemsPerPage"
                            >
                                <div class="pagination-info">
                                    Menampilkan
                                    {{ pagination.overview.startItem }}-{{
                                        pagination.overview.endItem
                                    }}
                                    dari {{ filteredTeamMembers.length }} data
                                </div>
                                <div class="pagination-controls">
                                    <button
                                        class="pagination-btn pagination-prev"
                                        @click="previousPage"
                                        :disabled="
                                            pagination.overview.currentPage ===
                                            1
                                        "
                                    >
                                        <i
                                            class="bi bi-chevron-left d-flex justify-content-center align-items-center"
                                        ></i>
                                    </button>

                                    <div class="pagination-pages">
                                        <button
                                            v-for="page in visiblePages"
                                            :key="page"
                                            class="pagination-page"
                                            :class="{
                                                active:
                                                    page ===
                                                    pagination.overview
                                                        .currentPage,
                                            }"
                                            @click="goToPage(page)"
                                        >
                                            {{ page }}
                                        </button>
                                    </div>

                                    <button
                                        class="pagination-btn pagination-next"
                                        @click="nextPage"
                                        :disabled="
                                            pagination.overview.currentPage ===
                                            pagination.overview.totalPages
                                        "
                                    >
                                        <i
                                            class="bi bi-chevron-right d-flex justify-content-center align-items-center"
                                        ></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div
                            v-else
                            class="empty-state d-flex justify-content-center align-items-center flex-column"
                        >
                            <div class="empty-icon mb-4">
                                <i
                                    class="bi bi-people d-flex justify-content-center align-items-center"
                                ></i>
                            </div>
                            <h4>Tidak Ada Anggota Tim</h4>
                            <p>Belum ada anggota tim yang tersedia</p>
                            <button
                                class="btn-modern btn-primary mt-3"
                                @click="showAddMemberModal"
                            >
                                <i
                                    class="bi bi-person-plus d-flex justify-content-center align-items-center me-2"
                                ></i>
                                Tambah Anggota Tim
                            </button>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div v-else class="row justify-content-center fade-in">
                        <div class="col-12">
                            <div class="loading-state">
                                <div class="loading-spinner">
                                    <i
                                        class="bi bi-arrow-repeat d-flex justify-content-center align-items-center"
                                    ></i>
                                </div>
                                <h4 class="loading-title">
                                    Memuat Data Team...
                                </h4>
                                <p class="loading-description">
                                    Sedang mengambil data team
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Tab 2: Team Assignment -->
            <template #assignment>
                <div class="team-assignment-content">
                    <!-- Assignment Header -->
                    <div class="assignment-header mb-4">
                        <div
                            class="row align-items-center justify-content-between"
                        >
                            <div class="col-md-8">
                                <h4 class="section-title">Penugasan Tim</h4>
                                <p class="section-subtitle">
                                    Kelola penugasan anggota ke dalam tim leader
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <button
                                    class="btn-modern btn-primary"
                                    @click="showBulkAssignmentModal"
                                >
                                    <i
                                        class="bi bi-people-fill d-flex justify-content-center align-items-center me-2"
                                    ></i>
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
                                        <h5 class="section-title">
                                            Anggota Tersedia
                                        </h5>
                                        <p class="section-subtitle">
                                            Karyawan yang belum memiliki tim
                                        </p>
                                    </div>
                                    <div class="members-list">
                                        <div
                                            v-for="member in availableMembers"
                                            :key="member.id"
                                            class="member-assignment-card"
                                            :class="{
                                                selected: isMemberSelected(
                                                    member.id
                                                ),
                                            }"
                                            @click="
                                                toggleMemberSelection(member.id)
                                            "
                                        >
                                            <div class="selection-checkbox">
                                                <input
                                                    type="checkbox"
                                                    :checked="
                                                        isMemberSelected(
                                                            member.id
                                                        )
                                                    "
                                                    @change="
                                                        toggleMemberSelection(
                                                            member.id
                                                        )
                                                    "
                                                />
                                            </div>
                                            <div class="member-avatar-sm">
                                                {{ getInitials(member.name) }}
                                            </div>
                                            <div class="member-details-sm">
                                                <h6>{{ member.name }}</h6>
                                                <p>{{ member.position }}</p>
                                            </div>
                                        </div>
                                        <div
                                            v-if="availableMembers.length === 0"
                                            class="empty-assignment-state"
                                        >
                                            <i class="bi bi-check-circle"></i>
                                            <p>
                                                Semua anggota sudah memiliki tim
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Assignment Actions -->
                            <div class="col-md-2">
                                <div
                                    class="assignment-actions d-flex flex-column justify-content-center align-items-center h-100"
                                >
                                    <button
                                        class="btn-assign"
                                        @click="assignSelectedMembers"
                                        :disabled="
                                            selectedMembers.length === 0 ||
                                            !selectedLeader
                                        "
                                    >
                                        <i class="bi bi-arrow-right"></i>
                                        <span>Assign</span>
                                    </button>
                                    <button
                                        class="btn-unassign"
                                        @click="unassignSelectedMembers"
                                        :disabled="selectedMembers.length === 0"
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
                                        <h5 class="section-title">
                                            Leader Tim
                                        </h5>
                                        <p class="section-subtitle">
                                            Pilih leader untuk menugaskan
                                            anggota
                                        </p>
                                    </div>
                                    <div class="leaders-list">
                                        <div
                                            v-for="leader in teamLeaders"
                                            :key="leader.id"
                                            class="leader-assignment-card"
                                            :class="{
                                                active:
                                                    selectedLeader ===
                                                    leader.id,
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
                                                    <span class="member-count"
                                                        >{{
                                                            getTeamMemberCount(
                                                                leader.id
                                                            )
                                                        }}
                                                        anggota</span
                                                    >
                                                </div>
                                            </div>
                                            <div class="selection-indicator">
                                                <i
                                                    class="bi bi-check-circle-fill"
                                                    v-if="
                                                        selectedLeader ===
                                                        leader.id
                                                    "
                                                ></i>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Selected Leader Team -->
                                    <div
                                        v-if="selectedLeader"
                                        class="selected-team-section mt-4"
                                    >
                                        <div class="section-header-compact">
                                            <h6 class="section-title">
                                                Tim
                                                {{
                                                    getLeaderName(
                                                        selectedLeader
                                                    )
                                                }}
                                            </h6>
                                            <p class="section-subtitle">
                                                Anggota yang sudah ditugaskan
                                            </p>
                                        </div>
                                        <div class="team-members-list">
                                            <div
                                                v-for="member in getLeaderTeamMembers(
                                                    selectedLeader
                                                )"
                                                :key="member.id"
                                                class="team-member-item"
                                                :class="{
                                                    selected: isMemberSelected(
                                                        member.id
                                                    ),
                                                }"
                                                @click="
                                                    toggleMemberSelection(
                                                        member.id
                                                    )
                                                "
                                            >
                                                <div class="selection-checkbox">
                                                    <input
                                                        type="checkbox"
                                                        :checked="
                                                            isMemberSelected(
                                                                member.id
                                                            )
                                                        "
                                                        @change="
                                                            toggleMemberSelection(
                                                                member.id
                                                            )
                                                        "
                                                    />
                                                </div>
                                                <div class="member-avatar-xs">
                                                    {{
                                                        getInitials(member.name)
                                                    }}
                                                </div>
                                                <div class="member-details-xs">
                                                    <span>{{
                                                        member.name
                                                    }}</span>
                                                </div>
                                                <button
                                                    class="btn-remove-member"
                                                    @click.stop="
                                                        removeMemberFromTeam(
                                                            member.id
                                                        )
                                                    "
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
                                    <div
                                        class="analytics-icon available-members"
                                    >
                                        <i class="bi bi-person-plus"></i>
                                    </div>
                                    <div class="analytics-content">
                                        <h3>{{ availableMembers.length }}</h3>
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
                            <h5 class="section-title">
                                Distribusi Anggota per Tim
                            </h5>
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
                                        <span>{{
                                            getLeaderName(leader.id)
                                        }}</span>
                                        <span class="bar-count">{{
                                            getTeamMemberCount(leader.id)
                                        }}</span>
                                    </div>
                                    <div class="bar-track">
                                        <div
                                            class="bar-fill"
                                            :style="{
                                                width:
                                                    calculateBarWidth(
                                                        leader.id
                                                    ) + '%',
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
                            <h5 class="section-title">
                                Breakdown per Departemen
                            </h5>
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
                                    <span class="department-count"
                                        >{{ dept.count }} anggota</span
                                    >
                                </div>
                                <div class="department-teams">
                                    <div
                                        v-for="team in dept.teams"
                                        :key="team.name"
                                        class="team-breakdown"
                                    >
                                        <span class="team-name">{{
                                            team.name
                                        }}</span>
                                        <span class="team-member-count">{{
                                            team.memberCount
                                        }}</span>
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
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Nama Karyawan</label>
                                <input
                                    type="text"
                                    v-model="newMember.name"
                                    class="form-control"
                                    placeholder="Masukkan nama karyawan"
                                />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">NIK</label>
                                <input
                                    type="text"
                                    v-model="newMember.employeeId"
                                    class="form-control"
                                    placeholder="Masukkan NIK"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Posisi</label>
                                <input
                                    type="text"
                                    v-model="newMember.position"
                                    class="form-control"
                                    placeholder="Masukkan posisi"
                                />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Departemen</label>
                                <select
                                    v-model="newMember.department"
                                    class="form-control"
                                >
                                    <option value="">Pilih Departemen</option>
                                    <option
                                        v-for="dept in uniqueDepartments"
                                        :key="dept"
                                        :value="dept"
                                    >
                                        {{ dept }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Tim</label>
                                <select
                                    v-model="newMember.team"
                                    class="form-control"
                                >
                                    <option value="">Pilih Tim</option>
                                    <option
                                        v-for="team in uniqueTeams"
                                        :key="team"
                                        :value="team"
                                    >
                                        {{ team }}
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
                                >
                                    <option value="">Pilih Leader</option>
                                    <option
                                        v-for="leader in teamLeaders"
                                        :key="leader.id"
                                        :value="leader.id"
                                    >
                                        {{ leader.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="wrapper-button-footer w-100 gap-2">
                    <button
                        class="btn-modern btn-secondary"
                        @click="closeAddMemberModal"
                    >
                        Batal
                    </button>
                    <button
                        class="btn-modern btn-primary"
                        @click="saveNewMember"
                        :disabled="!isNewMemberValid"
                    >
                        Simpan
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
                            <div
                                class="current-team"
                                v-if="transferMember.leader"
                            >
                                <i class="bi bi-arrow-right"></i>
                                <div class="team-info">
                                    <strong>{{
                                        getLeaderName(transferMember.leaderId)
                                    }}</strong>
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
                                        <small
                                            >{{
                                                getTeamMemberCount(leader.id)
                                            }}
                                            anggota</small
                                        >
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
                    <button
                        class="btn-modern btn-secondary"
                        @click="closeTransferModal"
                    >
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
                        <h5 class="step-title">
                            Pilih Anggota untuk Ditugaskan
                        </h5>
                        <p class="step-description">
                            Pilih anggota yang akan ditugaskan ke tim
                        </p>

                        <div class="members-selection-grid">
                            <div
                                v-for="member in availableMembers"
                                :key="member.id"
                                class="member-selection-card"
                                :class="{
                                    selected: bulkSelectedMembers.includes(
                                        member.id
                                    ),
                                }"
                                @click="toggleBulkMemberSelection(member.id)"
                            >
                                <div class="selection-checkbox">
                                    <input
                                        type="checkbox"
                                        :checked="
                                            bulkSelectedMembers.includes(
                                                member.id
                                            )
                                        "
                                        @change="
                                            toggleBulkMemberSelection(member.id)
                                        "
                                    />
                                </div>
                                <div class="member-avatar-sm">
                                    {{ getInitials(member.name) }}
                                </div>
                                <div class="member-details">
                                    <h6>{{ member.name }}</h6>
                                    <p>{{ member.position }}</p>
                                    <span class="department">{{
                                        member.department
                                    }}</span>
                                </div>
                            </div>
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
                                        <span class="team-name"
                                            >Tim {{ leader.team }}</span
                                        >
                                        <span class="member-count"
                                            >{{
                                                getTeamMemberCount(leader.id)
                                            }}
                                            anggota</span
                                        >
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
                                <button
                                    class="btn-modern btn-secondary"
                                    @click="previousBulkStep"
                                >
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
                                    <span>{{
                                        getLeaderName(bulkSelectedLeader)
                                    }}</span>
                                </div>
                                <div class="summary-item">
                                    <strong>Jumlah Anggota:</strong>
                                    <span
                                        >{{
                                            bulkSelectedMembers.length
                                        }}
                                        orang</span
                                    >
                                </div>
                                <div class="summary-item">
                                    <strong>Tim:</strong>
                                    <span>{{
                                        getLeaderTeam(bulkSelectedLeader)
                                    }}</span>
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
                                            {{
                                                getInitials(
                                                    getMemberById(memberId).name
                                                )
                                            }}
                                        </div>
                                        <span class="member-name">{{
                                            getMemberById(memberId).name
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="step-actions">
                            <div class="d-flex justify-content-between w-100">
                                <button
                                    class="btn-modern btn-secondary"
                                    @click="previousBulkStep"
                                >
                                    <i class="bi bi-arrow-left me-2"></i>
                                    Kembali
                                </button>
                                <button
                                    class="btn-modern btn-success"
                                    @click="confirmBulkAssignment"
                                >
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
                                <p class="position">
                                    {{ teamDetailsData.leader.position }}
                                </p>
                                <p class="department">
                                    {{ teamDetailsData.leader.department }}
                                </p>
                                <div class="team-stats">
                                    <span class="member-count">
                                        <i class="bi bi-people"></i>
                                        {{ teamDetailsData.totalMembers }}
                                        anggota
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Team Members -->
                    <div class="team-members-section">
                        <div class="section-header mb-3">
                            <h5 class="section-title">Anggota Tim</h5>
                            <span class="member-count-badge"
                                >{{ teamDetailsData.totalMembers }} orang</span
                            >
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
                                    <p class="member-position">
                                        {{ member.position }}
                                    </p>
                                    <p class="member-department">
                                        {{ member.department }}
                                    </p>
                                    <div class="member-meta">
                                        <span class="employee-id">{{
                                            member.employeeId
                                        }}</span>
                                        <span
                                            :class="`status-badge status-${member.status}`"
                                        >
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
                    <button
                        class="btn-modern btn-secondary"
                        @click="hideTeamDetailsModal"
                    >
                        Tutup
                    </button>
                    <button
                        class="btn-modern btn-primary"
                        @click="editLeaderTeam(teamDetailsData.leader)"
                    >
                        <i class="bi bi-gear me-2"></i>
                        Kelola Tim
                    </button>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import quickStat from "@/components/ui/quickStat.vue";
import ApprovalTabs from "@/components/ui/toggleSectionV2.vue";
import Modal from "@/components/ui/modalV2-noir.vue";
import { ElMessage, ElNotification } from "element-plus";
import HeaderComponent from "@/components/ui/header.vue";

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

            // Team Data
            teamMembers: [],
            teamLeaders: [],
            availableMembersData: [],
            selectedMembers: [],
            selectedLeader: null,

            // Filters
            teamFilters: {
                search: "",
                department: "",
                position: "",
                team: "",
            },

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

            // Modals
            showAddMemberModal: false,
            showTransferModal: false,
            showBulkAssignmentModal: false,
            showTeamDetailsModal: false,

            // Form Data
            newMember: {
                name: "",
                employeeId: "",
                position: "",
                department: "",
                team: "",
                leaderId: "",
            },
            transferMember: null,
            transferToLeader: null,

            // Bulk Assignment
            bulkStep: 1,
            bulkSelectedMembers: [],
            bulkSelectedLeader: null,

            // Team Details
            teamDetailsData: null,
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
                    unit: "",
                    icon: "bi bi-people",
                    bgIcon: "",
                    size: "col-4",
                },
                {
                    title: "Leader Aktif",
                    value: this.teamLeaders.length,
                    unit: "",
                    icon: "bi bi-person-badge",
                    bgIcon: "",
                    size: "col-4",
                },
                {
                    title: "Tersedia",
                    value: this.availableMembers.length,
                    unit: "",
                    icon: "bi bi-person-plus",
                    bgIcon: "",
                    size: "col-4",
                },
            ];
        },
        filteredTeamMembers() {
            return this.teamMembers.filter((member) => {
                const matchesSearch =
                    !this.teamFilters.search ||
                    member.name
                        .toLowerCase()
                        .includes(this.teamFilters.search.toLowerCase()) ||
                    member.employeeId
                        .toLowerCase()
                        .includes(this.teamFilters.search.toLowerCase()) ||
                    member.position
                        .toLowerCase()
                        .includes(this.teamFilters.search.toLowerCase());

                const matchesDepartment =
                    !this.teamFilters.department ||
                    member.department === this.teamFilters.department;

                const matchesPosition =
                    !this.teamFilters.position ||
                    member.position === this.teamFilters.position;

                const matchesTeam =
                    !this.teamFilters.team ||
                    member.team === this.teamFilters.team;

                return (
                    matchesSearch &&
                    matchesDepartment &&
                    matchesPosition &&
                    matchesTeam
                );
            });
        },
        paginatedTeamMembers() {
            const start =
                (this.pagination.overview.currentPage - 1) * this.itemsPerPage;
            const end = start + this.itemsPerPage;
            return this.filteredTeamMembers.slice(start, end);
        },
        visiblePages() {
            const current = this.pagination.overview.currentPage;
            const total = this.pagination.overview.totalPages;
            const range = 2;
            const pages = [];

            for (
                let i = Math.max(1, current - range);
                i <= Math.min(total, current + range);
                i++
            ) {
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
            return [
                ...new Set(this.teamMembers.map((m) => m.department)),
            ].filter(Boolean);
        },
        uniquePositions() {
            return [...new Set(this.teamMembers.map((m) => m.position))].filter(
                Boolean
            );
        },
        uniqueTeams() {
            return [...new Set(this.teamMembers.map((m) => m.team))].filter(
                Boolean
            );
        },
        availableMembers() {
            return this.availableMembersData;
        },
        availableLeadersForTransfer() {
            return this.teamLeaders.filter(
                (leader) => leader.id !== this.transferMember?.leaderId
            );
        },
        isNewMemberValid() {
            return (
                this.newMember.name &&
                this.newMember.employeeId &&
                this.newMember.position &&
                this.newMember.department
            );
        },
        calculateAverageTeamSize() {
            if (this.teamLeaders.length === 0) return 0;
            const totalMembers = this.teamMembers.filter(
                (m) => m.leaderId
            ).length;
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
            return this.teamMembers.filter(
                (member) => member.leaderId === leaderId
            ).length;
        },
        getLeaderTeamMembers(leaderId) {
            return this.teamMembers.filter(
                (member) => member.leaderId === leaderId
            );
        },
        getMemberById(memberId) {
            return this.teamMembers.find((member) => member.id === memberId);
        },
        calculateBarWidth(leaderId) {
            const maxMembers = Math.max(
                ...this.teamLeaders.map((leader) =>
                    this.getTeamMemberCount(leader.id)
                )
            );
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
        async assignSelectedMembers() {
            if (this.selectedMembers.length === 0 || !this.selectedLeader) {
                ElMessage.warning("Pilih anggota dan leader terlebih dahulu");
                return;
            }

            try {
                const response = await fetch(
                    "/api/v1/karyawan-tim/assign-member",
                    {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                        },
                        body: JSON.stringify({
                            leaderId: this.selectedLeader,
                            memberIds: this.selectedMembers,
                        }),
                    }
                );

                const result = await response.json();

                if (result.success) {
                    ElNotification({
                        title: "Berhasil",
                        message: result.message,
                        type: "success",
                    });

                    this.selectedMembers = [];
                    await this.loadTeamMembers();
                    this.updateTabCounts();
                } else {
                    ElMessage.error(result.message);
                }
            } catch (error) {
                console.error("Error assigning members:", error);
                ElMessage.error("Gagal menugaskan anggota ke tim");
            }
        },
        async unassignSelectedMembers() {
            if (this.selectedMembers.length === 0) {
                ElMessage.warning("Pilih anggota terlebih dahulu");
                return;
            }

            try {
                const response = await fetch(
                    "/api/v1/karyawan-tim/bulk-remove-members",
                    {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                        },
                        body: JSON.stringify({
                            memberIds: this.selectedMembers,
                        }),
                    }
                );

                const result = await response.json();

                if (result.success) {
                    ElNotification({
                        title: "Berhasil",
                        message: result.message,
                        type: "success",
                    });

                    this.selectedMembers = [];
                    await this.loadTeamMembers();
                    this.updateTabCounts();
                } else {
                    ElMessage.error(result.message);
                }
            } catch (error) {
                console.error("Error unassigning members:", error);
                ElMessage.error("Gagal menghapus anggota dari tim");
            }
        },
        async removeMemberFromTeam(memberId) {
            try {
                const response = await fetch(
                    "/api/v1/karyawan-tim/remove-member",
                    {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                        },
                        body: JSON.stringify({
                            memberId: memberId,
                        }),
                    }
                );

                const result = await response.json();

                if (result.success) {
                    ElMessage.success(result.message);
                    await this.loadTeamMembers();
                    this.updateTabCounts();
                } else {
                    ElMessage.error(result.message);
                }
            } catch (error) {
                console.error("Error removing member:", error);
                ElMessage.error("Gagal menghapus anggota dari tim");
            }
        },
        viewMemberDetails(member) {
            // Implementation for viewing member details
            console.log("View member details:", member);
        },
        editMember(member) {
            // Implementation for editing member
            console.log("Edit member:", member);
        },
        async viewLeaderTeam(leader) {
            try {
                const response = await fetch(
                    `/api/v1/karyawan-tim/team-details/${leader.id}`
                );
                const result = await response.json();

                if (result.success) {
                    // Show team details modal or navigate to team details
                    this.teamDetailsData = result.data.data;
                    this.showTeamDetailsModal = true;
                } else {
                    ElMessage.error("Gagal memuat detail tim");
                }
            } catch (error) {
                console.error("Error viewing leader team:", error);
                ElMessage.error("Gagal memuat detail tim");
            }
        },

        showTeamDetailsModal(teamData) {
            this.teamDetailsData = teamData;
            this.showTeamDetailsModal = true;
        },

        hideTeamDetailsModal() {
            this.showTeamDetailsModal = false;
            this.teamDetailsData = null;
        },
        editLeaderTeam(leader) {
            // Implementation for editing leader's team
            console.log("Edit leader team:", leader);
        },
        resetFilters() {
            this.teamFilters = {
                search: "",
                department: "",
                position: "",
                team: "",
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
            if (
                this.pagination.overview.currentPage <
                this.pagination.overview.totalPages
            ) {
                this.pagination.overview.currentPage++;
                this.updatePagination();
            }
        },
        updatePagination() {
            const dataLength = this.filteredTeamMembers.length;
            this.pagination.overview.totalPages = Math.ceil(
                dataLength / this.itemsPerPage
            );
            this.pagination.overview.startItem =
                (this.pagination.overview.currentPage - 1) * this.itemsPerPage +
                1;
            this.pagination.overview.endItem = Math.min(
                this.pagination.overview.currentPage * this.itemsPerPage,
                dataLength
            );
        },
        updateTabCounts() {
            this.mainTabs[0].count = this.teamMembers.length;
            this.mainTabs[1].count = this.availableMembers.length;
            this.mainTabs[2].count = this.teamLeaders.length;
        },
        // Modal Methods
        showAddMemberModal() {
            this.showAddMemberModal = true;
        },
        closeAddMemberModal() {
            this.showAddMemberModal = false;
            this.newMember = {
                name: "",
                employeeId: "",
                position: "",
                department: "",
                team: "",
                leaderId: "",
            };
        },
        saveNewMember() {
            const newMember = {
                id: Date.now(),
                ...this.newMember,
                status: "active",
                teamCount: 0,
            };
            this.teamMembers.push(newMember);

            ElNotification({
                title: "Berhasil",
                message: `Anggota ${newMember.name} berhasil ditambahkan`,
                type: "success",
            });

            this.closeAddMemberModal();
            this.updateTabCounts();
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
                const response = await fetch(
                    "/api/v1/karyawan-tim/bulk-transfer",
                    {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                        },
                        body: JSON.stringify({
                            memberIds: [this.transferMember.id],
                            newLeaderId: this.transferToLeader,
                        }),
                    }
                );

                const result = await response.json();

                if (result.success) {
                    ElNotification({
                        title: "Berhasil",
                        message: result.message,
                        type: "success",
                    });

                    this.closeTransferModal();
                    await this.loadTeamMembers();
                    this.updateTabCounts();
                } else {
                    ElMessage.error(result.message);
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
            this.showBulkAssignmentModal = true;
        },
        closeBulkAssignmentModal() {
            this.showBulkAssignmentModal = false;
            this.bulkStep = 1;
            this.bulkSelectedMembers = [];
            this.bulkSelectedLeader = null;
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
            if (
                this.bulkSelectedMembers.length === 0 ||
                !this.bulkSelectedLeader
            ) {
                ElMessage.warning("Pilih anggota dan leader terlebih dahulu");
                return;
            }

            try {
                const response = await fetch(
                    "/api/v1/karyawan-tim/assign-member",
                    {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                        },
                        body: JSON.stringify({
                            leaderId: this.bulkSelectedLeader,
                            memberIds: this.bulkSelectedMembers,
                        }),
                    }
                );

                const result = await response.json();

                if (result.success) {
                    ElNotification({
                        title: "Berhasil",
                        message: result.message,
                        type: "success",
                    });

                    this.closeBulkAssignmentModal();
                    await this.loadTeamMembers();
                    this.updateTabCounts();
                } else {
                    ElMessage.error(result.message);
                }
            } catch (error) {
                console.error("Error in bulk assignment:", error);
                ElMessage.error("Gagal melakukan penugasan massal");
            }
        },

        // API methods
        async loadTeamMembers() {
            try {
                const response = await fetch("/api/v1/karyawan-tim/members");
                const result = await response.json();

                if (result.success) {
                    this.teamMembers = result.data;
                } else {
                    ElMessage.error("Gagal memuat data anggota tim");
                }
            } catch (error) {
                console.error("Error loading team members:", error);
                ElMessage.error("Gagal memuat data anggota tim");
            }
        },

        async loadTeamLeaders() {
            try {
                const response = await fetch("/api/v1/karyawan-tim/leaders");
                const result = await response.json();

                if (result.success) {
                    this.teamLeaders = result.data;
                } else {
                    ElMessage.error("Gagal memuat data leader tim");
                }
            } catch (error) {
                console.error("Error loading team leaders:", error);
                ElMessage.error("Gagal memuat data leader tim");
            }
        },

        async loadAvailableMembers() {
            try {
                const response = await fetch(
                    "/api/v1/karyawan-tim/available-members"
                );
                const result = await response.json();

                if (result.success) {
                    this.availableMembersData = result.data;
                } else {
                    ElMessage.error("Gagal memuat data anggota tersedia");
                }
            } catch (error) {
                console.error("Error loading available members:", error);
                ElMessage.error("Gagal memuat data anggota tersedia");
            }
        },

        async initializeData() {
            this.loading.team = true;
            try {
                await Promise.all([
                    this.loadTeamMembers(),
                    this.loadTeamLeaders(),
                    this.loadAvailableMembers(),
                ]);

                this.updateTabCounts();
                this.updatePagination();
            } catch (error) {
                console.error("Error loading team data:", error);
                ElMessage.error("Gagal memuat data tim");
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

.members-list,
.leaders-list {
    max-height: 400px;
    overflow-y: auto;
}

.member-assignment-card,
.leader-assignment-card {
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

.member-assignment-card:hover,
.leader-assignment-card:hover {
    border-color: var(--primary-light);
    background: rgba(99, 102, 241, 0.05);
}

.member-assignment-card.selected,
.leader-assignment-card.active {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
}

.member-avatar-sm,
.leader-avatar-sm {
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

.member-details-sm,
.leader-details-sm {
    flex: 1;
}

.member-details-sm h6,
.leader-details-sm h6 {
    margin: 0 0 0.25rem 0;
    font-size: 0.9rem;
    color: var(--dark);
}

.member-details-sm p,
.leader-details-sm p {
    margin: 0;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.team-stats {
    margin-top: 0.25rem;
}

.member-count {
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

.btn-assign,
.btn-unassign {
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

.btn-assign:disabled,
.btn-unassign:disabled {
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
    background: #dc2626;
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

/* Bulk Assignment Styles */
.stepper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    position: relative;
}

.stepper::before {
    content: "";
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

.members-selection-grid,
.leaders-selection-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.member-selection-card,
.leader-selection-card {
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

.member-selection-card:hover,
.leader-selection-card:hover {
    border-color: var(--primary-light);
    transform: translateY(-2px);
}

.member-selection-card.selected,
.leader-selection-card.selected {
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

/* Form Styles */
.add-member-form,
.transfer-member-form {
    padding: 0.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--dark);
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

.current-assignment,
.transfer-options {
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

.member-info,
.current-team {
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

/* Responsive Design */
@media (max-width: 768px) {
    .leaders-container {
        grid-template-columns: 1fr;
    }

    .assignment-interface .row {
        flex-direction: column;
    }

    .assignment-actions {
        flex-direction: row;
        margin: 1rem 0;
    }

    .analytics-overview .row {
        grid-template-columns: repeat(2, 1fr);
    }

    .department-grid {
        grid-template-columns: 1fr;
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
}

/* Team Badge */
.team-badge {
    background: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
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

.status-active {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-inactive {
    background: rgba(100, 116, 139, 0.1);
    color: var(--secondary);
}

.status-pending {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
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
.team-members-list::-webkit-scrollbar {
    width: 6px;
}

.members-list::-webkit-scrollbar-track,
.leaders-list::-webkit-scrollbar-track,
.team-members-list::-webkit-scrollbar-track {
    background: var(--surface-soft);
    border-radius: 3px;
}

.members-list::-webkit-scrollbar-thumb,
.leaders-list::-webkit-scrollbar-thumb,
.team-members-list::-webkit-scrollbar-thumb {
    background: var(--primary-light);
    border-radius: 3px;
}

.members-list::-webkit-scrollbar-thumb:hover,
.leaders-list::-webkit-scrollbar-thumb:hover,
.team-members-list::-webkit-scrollbar-thumb:hover {
    background: var(--primary);
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

.member-actions {
    display: flex;
    flex-direction: column;
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

.btn-action.btn-transfer {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.btn-action.btn-transfer:hover {
    background: var(--warning);
    color: white;
    transform: scale(1.1);
}

/* Enhanced Responsive Design */
@media (max-width: 768px) {
    .team-members-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .leader-info-card {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }

    .team-member-detail-card {
        padding: 1rem;
        gap: 0.75rem;
    }

    .member-actions {
        flex-direction: row;
    }

    .leader-avatar-lg {
        width: 60px;
        height: 60px;
        font-size: 1.4rem;
    }

    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .leaders-container {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .team-details-content {
        padding: 1rem;
    }

    .leader-info-section,
    .team-members-section {
        padding: 1rem;
    }

    .team-member-detail-card {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }

    .member-meta {
        justify-content: center;
    }

    .member-actions {
        flex-direction: row;
        justify-content: center;
    }

    .leader-avatar-lg {
        width: 50px;
        height: 50px;
        font-size: 1.2rem;
    }

    .leader-details-lg h4 {
        font-size: 1.1rem;
    }

    .bulk-actions-header {
        flex-direction: column;
        gap: 1rem;
    }

    .team-filters-bar .row {
        gap: 0.5rem;
    }

    .team-filters-bar .col-md-2,
    .team-filters-bar .col-md-3 {
        flex: 1 1 100%;
        max-width: 100%;
    }
}
</style>

<style scoped>
/* Menggunakan variabel CSS dari file lain untuk konsistensi */
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
    --surface: #ffffff;
    --surface-soft: #f1f5f9;
    --border: #e2e8f0;
    --text: #334155;
    --text-muted: #64748b;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --radius: 12px;
}

.tab-content-improved {
    padding: 1.5rem;
    background-color: var(--surface-soft);
    border-radius: var(--radius);
}

/* Filter & Search Section */
.filter-section {
    background: var(--surface);
    padding: 1rem;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
}

.search-box {
    position: relative;
}

.search-box i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
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

/* Employee List Table */
.modern-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.modern-table th,
.modern-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border);
}

.modern-table th {
    background-color: var(--surface-soft);
    font-weight: 600;
    color: var(--text-muted);
}

.modern-table tr:hover {
    background-color: var(--surface-soft);
}

.employee-info-cell {
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

.badge-access-count {
    background-color: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.8rem;
}

/* Manage Access Tab */
.manage-access-container {
    background: var(--surface);
    padding: 2rem;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
}

.selection-card {
    background: var(--surface-soft);
    padding: 1.5rem;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    height: 100%;
    transition: all 0.3s ease;
}

.selection-card.disabled-card {
    opacity: 0.5;
    pointer-events: none;
    background: #d6d6d6;
}

.selection-title {
    font-weight: 600;
    color: var(--text);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
}

.selection-description {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-bottom: 1.5rem;
}

.all-employees-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 2rem 1rem;
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    background-color: var(--surface);
    color: var(--text-muted);
}

.all-employees-info i {
    font-size: 2.5rem;
    color: var(--success);
    margin-bottom: 1rem;
}

.all-employees-info p {
    margin: 0;
    font-size: 0.9rem;
}

.selected-items-summary {
    background-color: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.75rem;
}

.summary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
}

.btn-clear-selection {
    background: none;
    border: none;
    color: var(--primary);
    cursor: pointer;
    font-weight: 600;
}

.selected-chips-container {
    max-height: 150px;
    overflow-y: auto;
}
.form-group {
    margin-bottom: 1rem;
}

.page-browser {
    background-color: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
}

.search-input-sm {
    width: 100%;
    padding: 0.6rem 1rem 0.6rem 2.5rem;
    border: 2px solid var(--border);
    border-radius: 8px;
    background: var(--surface-soft);
    color: var(--text);
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.search-input-sm:focus {
    outline: none;
    border-color: var(--primary);
    background: var(--surface);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.page-groups-container {
    max-height: 350px;
    overflow-y: auto;
    padding-right: 0.5rem;
}

.page-group {
    margin-bottom: 1rem;
}

.page-group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background-color: var(--surface-soft);
    border-radius: 8px;
    margin-bottom: 0.5rem;
}

.module-title {
    font-weight: 600;
    color: var(--text);
    margin: 0;
    font-size: 1rem;
}

.page-group-header .form-check {
    margin: 0;
}

.page-group-body {
    padding: 0 0.75rem;
}

.page-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border);
}

.page-item:last-child {
    border-bottom: none;
}

.page-item .form-check-label {
    font-size: 0.9rem;
    color: var(--text-muted);
}

.page-browser {
    background-color: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
}

.search-input-sm {
    width: 100%;
    padding: 0.6rem 1rem 0.6rem 2.5rem;
    border: 2px solid var(--border);
    border-radius: 8px;
    background: var(--surface-soft);
    color: var(--text);
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.search-input-sm:focus {
    outline: none;
    border-color: var(--primary);
    background: var(--surface);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.page-groups-container {
    max-height: 350px;
    overflow-y: auto;
    padding-right: 0.5rem;
}

.page-group {
    margin-bottom: 1rem;
}

.page-group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background-color: var(--surface-soft);
    border-radius: 8px;
    margin-bottom: 0.5rem;
}

.module-title {
    font-weight: 600;
    color: var(--text);
    margin: 0;
    font-size: 1rem;
}

.page-group-header .form-check {
    margin: 0;
}

.page-group-body {
    padding: 0 0.75rem;
}

.page-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border);
}

.page-item:last-child {
    margin-left: 0;
    border-bottom: none;
}

.page-item .form-check-label {
    font-size: 0.9rem;
    color: var(--text-muted);
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-muted);
    font-size: 0.85rem;
}

.chip {
    display: inline-flex;
    align-items: center;
    background-color: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.3rem 0.8rem;
    border-radius: 16px;
    font-size: 0.85rem;
    font-weight: 500;
    margin: 0.25rem;
}

.chip i {
    margin-left: 0.5rem;
    cursor: pointer;
    opacity: 0.7;
}
.chip i:hover {
    opacity: 1;
}
.action-footer {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.action-summary {
    color: var(--text-muted);
    margin: 0;
}

.action-buttons {
    display: flex;
    gap: 0.75rem;
}

/* Buttons */
.btn-action {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-view {
    background: var(--surface-soft);
    color: var(--text);
}

.btn-view:hover {
    background: var(--border);
}

.btn-modern {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.2rem;
    border: none;
    border-radius: var(--radius);
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-modern:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-filter-reset {
    background: var(--secondary);
    color: white;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: var(--radius);
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-filter-reset:hover {
    background: #6c757d;
    transform: translateY(-1px);
}

.btn-success {
    background: var(--success);
    color: white;
}
.btn-success:hover:not(:disabled) {
    background: #059669;
}

.btn-danger {
    background: var(--danger);
    color: white;
}
.btn-danger:hover:not(:disabled) {
    background: #dc2626;
}

.btn-secondary {
    background: var(--secondary);
    color: white;
}
.btn-secondary:hover:not(:disabled) {
    background: #475569;
}

/* Modal Detail */
.detail-employee-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding-bottom: 1.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.avatar-md {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1.5rem;
}

.detail-access-list h6 {
    font-weight: 600;
    color: var(--text);
    margin-bottom: 1rem;
}

.access-tags-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.access-tag {
    display: inline-flex;
    align-items: center;
    background-color: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 500;
    font-size: 0.9rem;
}

.empty-state-modal {
    text-align: center;
    padding: 2rem;
    color: var(--text-muted);
}

.empty-state-modal i {
    font-size: 3rem;
    margin-bottom: 1rem;
}

/* General States */
.loading-state,
.empty-state-admin {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: var(--text-muted);
    text-align: center;
}

.loading-spinner .bi-arrow-repeat {
    font-size: 2.5rem;
    color: var(--primary);
    animation: spin 1.5s linear infinite;
}

.empty-state-admin i {
    font-size: 4rem;
    opacity: 0.5;
    margin-bottom: 1rem;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

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
    cursor: pointer;
    transition: all 0.2s ease;
}
.page-btn:hover:not(:disabled) {
    background: var(--primary);
    color: white;
}
.page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.page-info {
    margin: 0 1rem;
    font-size: 0.9rem;
    color: var(--text-muted);
}

@media (max-width: 991px) {
    .action-footer {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
    }
    .action-buttons {
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .tab-content-improved {
        padding: 1rem;
    }
    .manage-access-container {
        padding: 1.5rem;
    }
    .modern-table {
        display: block;
        overflow-x: auto;
    }
}
</style>
