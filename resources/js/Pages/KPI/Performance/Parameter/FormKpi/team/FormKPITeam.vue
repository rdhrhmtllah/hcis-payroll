<template>
    <div class="container-fluid px-0">
        <!-- Loading State -->
        <!-- <div v-if="loading.schedule" class="loading-container">
            <div class="loading-animation">
                <div class="loading-spinner"></div>
                <div class="loading-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
            <p class="loading-text">Memuat data penjadwalan...</p>
        </div> -->

        <!-- Schedule Selection -->
        <div v-if="!activeSchedule && schedules.length > 0" class="schedule-selection-container">
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
                                                class="bi bi-calendar-check d-flex justify-content-center align-items-center"
                                            ></i>
                                        </div>
                                        <div class="text-content">
                                            <h2 class="title fw-bold text-white text-start">Pilih Periode KPI</h2>
                                            <p class="subtitle">Pilih periode penjadwalan untuk mengelola KPI tim</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div
                                        class="header-actions d-flex gap-2 flex-nowrap flex-sm-column justify-content-center justify-content-md-start justify-content-md-center align-items-md-end"
                                    >
                                        <div class="date-range px-2 px-md-3 py-2 gap-2 gap-md-3">
                                            <div class="date-item">
                                                <i
                                                    v-if="dayNight == 'Siang'"
                                                    class="bi bi-cloud-sun-fill me-2 fs-4 fade-in d-flex justify-content-center align-items-center"
                                                ></i>
                                                <i
                                                    v-else
                                                    class="bi bi-cloud-moon-fill me-2 fs-4 fade-in d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span class="fs-md-6 text-header">{{
                                                    formatDateString(currentDate)
                                                }}</span>
                                            </div>
                                        </div>
                                        <!-- <button
                                            class="btn-modern btn-primary py-2"
                                            @click="backToScheduleSelection"
                                        >
                                            <i
                                                class="bi bi-arrow-left me-2 d-flex justify-content-center align-items-center"
                                            ></i>
                                            Kembali
                                        </button> -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schedule List -->
            <div class="schedule-list-container">
                <div class="section-header mb-4">
                    <h4 class="section-title">Daftar Periode KPI</h4>
                    <p class="section-subtitle">Pilih periode aktif atau lihat history periode sebelumnya</p>
                </div>

                <div class="schedule-grid">
                    <div
                        v-for="schedule in schedules"
                        :key="schedule.id"
                        class="schedule-card"
                        :class="{
                            active: schedule.is_active,
                            historical: !schedule.is_active,
                        }"
                        @click="selectSchedule(schedule)"
                    >
                        <div class="schedule-header">
                            <div class="schedule-icon">
                                <i
                                    class="bi"
                                    :class="
                                        schedule.is_active
                                            ? 'bi-play-circle d-flex justify-content-center align-items-center'
                                            : 'bi-clock-history d-flex justify-content-center align-items-center'
                                    "
                                ></i>
                            </div>
                            <div class="schedule-info">
                                <h5 class="schedule-name">
                                    {{ schedule.nama_kegiatan?.toUpperCase() }}
                                </h5>
                                <p class="schedule-period">
                                    {{ schedule.tahun }} -
                                    {{ truncateText(schedule.deskripsi, 70) }}
                                </p>
                            </div>
                            <div class="schedule-status">
                                <span class="status-badge" :class="schedule.is_active ? 'active' : 'historical'">
                                    {{ schedule.is_active ? 'Aktif' : 'History' }}
                                </span>
                            </div>
                        </div>
                        <div class="schedule-dates">
                            <div class="date-item">
                                <i class="d-flex justify-content-center align-items-center bi bi-calendar"></i>
                                <span>Mulai: {{ formatDateString(schedule.jadwal_mulai) }}</span>
                            </div>
                            <div class="date-item">
                                <i class="d-flex justify-content-center align-items-center bi bi-calendar"></i>
                                <span>Akhir: {{ formatDateString(schedule.jadwal_akhir) }}</span>
                            </div>
                        </div>
                        <div class="schedule-actions">
                            <button class="btn-modern btn-primary" @click.stop="selectSchedule(schedule)">
                                <i class="d-flex justify-content-center align-items-center bi bi-eye me-2"></i>
                                {{ schedule.is_active ? 'Kelola KPI' : 'Lihat History' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main KPI Management -->
        <div v-else-if="activeSchedule">
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
                                                class="bi bi-people-fill d-flex justify-content-center align-items-center"
                                            ></i>
                                        </div>
                                        <div class="text-content">
                                            <h2 class="title fw-bold text-white text-start">Team KPI Management</h2>
                                            <p class="subtitle">
                                                {{ activeSchedule.nama_kegiatan }}
                                                - {{ activeSchedule.tahun }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div
                                        class="header-actions d-flex gap-2 flex-nowrap flex-sm-column justify-content-center justify-content-md-start justify-content-md-center align-items-md-end"
                                    >
                                        <div class="date-range px-2 px-md-3 py-2 gap-2 gap-md-3">
                                            <div class="date-item">
                                                <i
                                                    v-if="dayNight == 'Siang'"
                                                    class="bi bi-cloud-sun-fill me-2 fs-4 fade-in d-flex justify-content-center align-items-center"
                                                ></i>
                                                <i
                                                    v-else
                                                    class="bi bi-cloud-moon-fill me-2 fs-4 fade-in d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span class="fs-md-6 text-header">{{
                                                    formatDateString(currentDate)
                                                }}</span>
                                            </div>
                                        </div>
                                        <!-- <button
                                            class="btn-modern btn-primary py-2"
                                            :disabled="loading.team"
                                            @click="backToScheduleSelection"
                                        >
                                            <i
                                                class="bi bi-arrow-left me-2 d-flex justify-content-center align-items-center"
                                            ></i>
                                            Kembali
                                        </button> -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <quick-stat :stats="quickStatField" />

            <!-- Main Content Tabs -->
            <ApprovalTabs :tabs="mainTabs" :activeTab="activeTab" @tab-change="handleTabChange">
                <!-- Tab 1: Team Overview -->
                <template #overview>
                    <div class="team-overview-content">
                        <!-- Team Actions -->
                        <div class="team-actions-bar mb-4">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-6">
                                    <div class="search-box">
                                        <i class="d-flex justify-content-center align-items-center bi bi-search"></i>
                                        <input
                                            type="text"
                                            v-model="teamFilters.search"
                                            placeholder="Cari anggota tim..."
                                            class="search-input"
                                        />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="bulk-actions-header">
                                        <button
                                            :disabled="loading.team"
                                            class="btn-modern btn-primary"
                                            @click="navigateToBulkCreation"
                                        >
                                            <i
                                                class="d-flex justify-content-center align-items-center bi bi-plus-circle me-2"
                                            ></i>
                                            Buat KPI Multiple
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tambahkan di bagian Team Actions Bar -->
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
                                    <select v-model="teamFilters.division" class="filter-select">
                                        <option value="">Semua Divisi</option>
                                        <option v-for="div in uniqueDivisions" :key="div" :value="div">
                                            {{ div }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select v-model="teamFilters.position" class="filter-select">
                                        <option value="">Semua Jabatan</option>
                                        <option v-for="pos in uniquePositions" :key="pos" :value="pos">
                                            {{ pos }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select v-model="teamFilters.status" class="filter-select">
                                        <option value="">Semua Status</option>
                                        <option value="draft">Menunggu KPI</option>
                                        <option value="waiting_approval">Menunggu Approval</option>
                                        <option value="approved">Disetujui</option>
                                        <option value="rejected">Ditolak</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <div class="filter-actions">
                                        <button @click="resetFilters" class="btn-filter-reset">
                                            <i class="bi bi-arrow-clockwise"></i>
                                            Reset
                                        </button>
                                        <span class="filter-count">{{ filteredTeamMembers.length }} hasil</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ringkasan revisi: SATU baris, tingginya tetap berapa pun
                             jumlah yang ditolak. Daftar namanya sengaja tidak di sini —
                             barisnya sendiri yang disorot merah di tabel, supaya panel
                             tidak tumbuh ke bawah dan mendorong seluruh tabel. -->
                        <div v-if="anggotaPerluRevisi.length" class="revisi-ringkas mb-4">
                            <i class="bi bi-x-octagon-fill"></i>
                            <span>
                                <strong>{{ anggotaPerluRevisi.length }} anggota</strong>
                                pengajuannya ditolak approver dan menunggu revisi —
                                ditandai merah pada tabel di bawah.
                            </span>
                        </div>

                        <!-- Priority Alert -->
                        <div v-if="hasMembersWithoutKPI" class="priority-alert mb-4">
                            <div class="alert-content">
                                <i
                                    class="d-flex justify-content-center align-items-center bi bi-exclamation-triangle-fill"
                                ></i>
                                <div class="alert-text">
                                    <strong>Prioritas:</strong>
                                    {{ membersWithoutKPI.length }} anggota tim belum memiliki KPI
                                </div>
                                <!-- <button
                                    class="btn-alert"
                                    @click="prioritizeMembersWithoutKPI"
                                >
                                    Buat KPI untuk Mereka
                                </button> -->
                            </div>
                        </div>

                        <div v-if="!loading.team">
                            <div v-if="filteredTeamMembers.length != 0">
                                <!-- Desktop Table View -->
                                <div class="d-none d-lg-block">
                                    <div class="table-responsive-wrapper">
                                        <table class="modern-table">
                                            <thead>
                                                <tr>
                                                    <!-- <th class="header-cell">
                                                        <input
                                                            type="checkbox"
                                                            v-model="selectAll"
                                                            @change="
                                                                toggleSelectAll
                                                            "
                                                            class="table-checkbox"
                                                        />
                                                    </th> -->
                                                    <th class="header-cell">Nama Karyawan</th>
                                                    <th class="header-cell">Posisi</th>
                                                    <th class="header-cell">Divisi</th>
                                                    <th class="header-cell">Jadwal & Periode</th>
                                                    <th class="header-cell">Total KPI</th>
                                                    <th class="header-cell">Total Weight</th>
                                                    <th class="header-cell">Status</th>
                                                    <th class="header-cell">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="member in paginatedTeamMembers"
                                                    :key="member.id"
                                                    class="table-row"
                                                    :class="{
                                                        'no-approver': !hasApprover(member) && !member.hasSubmittedKPI,
                                                        'no-kpi': !member.hasSubmittedKPI,
                                                        'baris-ditolak': perluRevisi(member),
                                                        'row-not-eligible': member.is_eligible === false,
                                                    }"
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
                                                    <td>
                                                        {{ member.position }}
                                                    </td>
                                                    <td>
                                                        {{ member.division }}
                                                    </td>
                                                    <td>
                                                        <div class="schedule-cell-info">
                                                            <template v-if="member.is_eligible === false || member.sumber_jadwal === 'tidak_tercakup'">
                                                                <div class="d-flex align-items-center gap-1">
                                                                    <span class="badge-jadwal-type badge-jadwal-none">
                                                                        <i class="bi bi-slash-circle me-1"></i>Tidak Terdaftar
                                                                    </span>
                                                                </div>
                                                                <div class="schedule-sub-text text-muted fst-italic mt-1">
                                                                    Belum masuk jadwal
                                                                </div>
                                                            </template>
                                                            <template v-else>
                                                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                                                    <span :class="['badge-jadwal-type', member.sumber_jadwal === 'perorangan' ? 'badge-jadwal-perorangan' : 'badge-jadwal-global']">
                                                                        <i :class="['bi me-1', member.sumber_jadwal === 'perorangan' ? 'bi-person-fill' : 'bi-globe']"></i>
                                                                        {{ member.sumber_jadwal === 'perorangan' ? 'Perorangan' : 'Global' }}
                                                                    </span>
                                                                    <span v-if="member.start_periode" class="badge-start-period">
                                                                        <i class="bi bi-calendar-event me-1"></i>Mulai {{ formatStartPeriode(member.start_periode) }}
                                                                    </span>
                                                                </div>
                                                                <div v-if="member.nama_jadwal" class="schedule-sub-text text-muted mt-1">
                                                                    <i class="bi bi-tag-fill text-secondary me-1"></i>{{ member.nama_jadwal }}
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="count-badge"
                                                            :class="{
                                                                'no-kpi-badge': !member.hasSubmittedKPI,
                                                            }"
                                                        >
                                                            {{ member.kpis?.length || 0 }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="weight-value"
                                                            >{{ calculateTotalWeight(member.kpis) }}%</span
                                                        >
                                                    </td>
                                                    <td>
                                                        <div class="d-flex flex-column gap-1">
                                                            <span
                                                                :class="`status-badge status-${getKpiStatus(member).class}`"
                                                            >
                                                                {{ getKpiStatus(member).text }}
                                                            </span>
                                                            <small
                                                                v-if="member.is_eligible === false"
                                                                class="text-muted fst-italic"
                                                                style="font-size: 11px;"
                                                            >
                                                                {{ member.ineligible_reason || 'Belum terdaftar di penjadwalan aktif ini' }}
                                                            </small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="action-cell">
                                                            <button
                                                                class="btn-action btn-view"
                                                                @click="viewMemberKpis(member)"
                                                                title="Lihat Detail"
                                                            >
                                                                <i
                                                                    class="bi bi-eye d-flex justify-content-center align-items-center"
                                                                ></i>
                                                            </button>
                                                            <button
                                                                v-if="member.hasSubmittedKPI && perluRevisi(member)"
                                                                class="btn-action btn-revisi-aksi"
                                                                @click="editMemberKpis(member)"
                                                                title="Revisi pengajuan yang ditolak"
                                                            >
                                                                <i class="bi bi-arrow-counterclockwise"></i>
                                                                <span>Revisi</span>
                                                            </button>
                                                            <button
                                                                v-else-if="member.hasSubmittedKPI && canEditKpi(member)"
                                                                class="btn-action btn-edit"
                                                                @click="editMemberKpis(member)"
                                                                title="Edit KPI"
                                                            >
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <span
                                                                v-else-if="member.is_eligible === false && !member.hasSubmittedKPI"
                                                                class="badge bg-light text-muted border py-1 px-2"
                                                                style="font-size: 11px;"
                                                                title="Tidak dapat diisi pada penjadwalan ini"
                                                            >
                                                                <i class="bi bi-slash-circle me-1"></i>Tidak Terjadwal
                                                            </span>
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
                                            :class="{
                                                'no-kpi': !member.hasSubmittedKPI,
                                                'baris-ditolak': perluRevisi(member),
                                                'row-not-eligible': member.is_eligible === false,
                                            }"
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
                                                        <span class="member-id">{{ member.employeeId }}</span>
                                                        <span class="member-division">{{ member.division }}</span>
                                                    </div>
                                                    <div class="member-schedule-tags mt-1">
                                                        <template v-if="member.is_eligible === false || member.sumber_jadwal === 'tidak_tercakup'">
                                                            <span class="badge-jadwal-type badge-jadwal-none">
                                                                <i class="bi bi-slash-circle me-1"></i>Tidak Terdaftar
                                                            </span>
                                                        </template>
                                                        <template v-else>
                                                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                                                <span :class="['badge-jadwal-type', member.sumber_jadwal === 'perorangan' ? 'badge-jadwal-perorangan' : 'badge-jadwal-global']">
                                                                    <i :class="['bi me-1', member.sumber_jadwal === 'perorangan' ? 'bi-person-fill' : 'bi-globe']"></i>
                                                                    {{ member.sumber_jadwal === 'perorangan' ? 'Perorangan' : 'Global' }}
                                                                </span>
                                                                <span v-if="member.start_periode" class="badge-start-period">
                                                                    <i class="bi bi-calendar-event me-1"></i>Mulai {{ formatStartPeriode(member.start_periode) }}
                                                                </span>
                                                            </div>
                                                            <div v-if="member.nama_jadwal" class="schedule-sub-text text-muted mt-0-5" style="font-size: 11px;">
                                                                <i class="bi bi-tag-fill text-secondary me-1"></i>{{ member.nama_jadwal }}
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                                <div class="member-status">
                                                    <span :class="`status-badge status-${getKpiStatus(member).class}`">
                                                        {{ getKpiStatus(member).text }}
                                                    </span>
                                                    <small
                                                        v-if="member.is_eligible === false"
                                                        class="text-muted d-block mt-1 fst-italic text-end"
                                                        style="font-size: 11px;"
                                                    >
                                                        {{ member.ineligible_reason || 'Belum terdaftar di penjadwalan aktif ini' }}
                                                    </small>
                                                </div>
                                            </div>

                                            <div class="member-kpi-summary">
                                                <div class="kpi-stats">
                                                    <div class="stat-item">
                                                        <i
                                                            class="d-flex justify-content-center align-items-center bi bi-layers"
                                                        ></i>
                                                        <span
                                                            :class="{
                                                                'no-kpi-text': !member.hasSubmittedKPI,
                                                            }"
                                                        >
                                                            {{ member.kpis?.length || 0 }}
                                                            KPI
                                                        </span>
                                                    </div>
                                                    <div class="stat-item">
                                                        <i class="bi bi-speedometer2"></i>
                                                        <span>{{ calculateTotalWeight(member.kpis) }}%</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="member-actions">
                                                <button
                                                    class="btn-action btn-view"
                                                    @click="viewMemberKpis(member)"
                                                    title="Lihat KPI"
                                                >
                                                    <i
                                                        class="d-flex justify-content-center align-items-center bi bi-eye"
                                                    ></i>
                                                </button>
                                                <button
                                                    v-if="member.hasSubmittedKPI && perluRevisi(member)"
                                                    class="btn-action btn-revisi-aksi"
                                                    @click="editMemberKpis(member)"
                                                    title="Revisi pengajuan yang ditolak"
                                                >
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                    <span>Revisi</span>
                                                </button>
                                                <button
                                                    v-else-if="member.hasSubmittedKPI && canEditKpi(member)"
                                                    class="btn-action btn-edit"
                                                    @click="editMemberKpis(member)"
                                                    title="Edit KPI"
                                                >
                                                    <i
                                                        class="d-flex justify-content-center align-items-center bi bi-pencil"
                                                    ></i>
                                                </button>
                                                <span
                                                    v-else-if="member.is_eligible === false && !member.hasSubmittedKPI"
                                                    class="badge bg-light text-muted border py-1 px-2"
                                                    style="font-size: 11px;"
                                                    title="Tidak dapat diisi pada penjadwalan ini"
                                                >
                                                    <i class="bi bi-slash-circle me-1"></i>Tidak Terjadwal
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="pagination-container mt-4" v-if="filteredTeamMembers.length > itemsPerPage">
                                    <div class="pagination-info">
                                        Menampilkan
                                        {{ pagination.overview.startItem }}-{{ pagination.overview.endItem }}
                                        dari
                                        {{ filteredTeamMembers.length }} data
                                    </div>
                                    <div class="pagination-controls">
                                        <button
                                            class="pagination-btn pagination-prev"
                                            @click="previousPage"
                                            :disabled="pagination.overview.currentPage === 1"
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
                                            :disabled="
                                                pagination.overview.currentPage === pagination.overview.totalPages
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
                                    <DotLottieVue
                                        style="height: 150px; width: 150px"
                                        autoplay
                                        loop
                                        src="/animation/empty-data.json"
                                    />
                                </div>
                                <h4>Tidak Ada Anggota Tim</h4>
                                <p>Belum ada anggota tim yang tersedia</p>
                            </div>
                        </div>
                        <div v-else class="row justify-content-center fade-in">
                            <div class="col-12">
                                <div class="loading-state">
                                    <div class="loading-spinner">
                                        <i
                                            class="bi bi-arrow-repeat d-flex justify-content-center align-items-center"
                                        ></i>
                                    </div>
                                    <h4 class="loading-title">Memuat Data Team...</h4>
                                    <p class="loading-description">Sedang mengambil data team</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Tab 2: Bulk Creation -->
                <template #bulk>
                    <div class="bulk-kpi-content">
                        <!-- Bulk Creation Header -->
                        <div class="bulk-creation-header mb-4">
                            <div class="row align-items-center justify-content-between">
                                <div class="col-md-8">
                                    <h4 class="section-title">Buat KPI untuk Multiple Karyawan</h4>
                                    <p class="section-subtitle">Pilih anggota tim dan buat KPI secara bersamaan</p>
                                </div>
                                <div class="col-md-4 text-end d-flex justify-content-end">
                                    <button class="btn-modern btn-secondary" @click="confirmResetBulkCreation">
                                        <i
                                            class="d-flex justify-content-center align-items-center bi bi-arrow-clockwise me-2"
                                        ></i>
                                        Reset
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Stepper -->
                        <div class="col-12 mb-4 fade-in">
                            <Stepper
                                :steps="bulkStepperSteps"
                                :currentStep="bulkCurrentStep"
                                activeColor="#6366f1"
                                completedColor="#10b981"
                                inactiveColor="#e5e7eb"
                                lineGradientFrom="#667eea"
                                lineGradientTo="#764ba2"
                                @update:currentStep="handleBulkStepChange"
                            />
                        </div>

                        <!-- Step 1: Member Selection -->
                        <div v-if="bulkCurrentStep === 1" class="bulk-step-content">
                            <div class="step-header">
                                <h5>Pilih Anggota Tim</h5>
                                <p>Pilih anggota tim yang akan diberikan KPI</p>
                            </div>

                            <div v-if="!loading.team">
                                <div v-if="teamMembers?.length != 0" class="member-selection-section">
                                    <div class="selection-actions mb-3">
                                        <div class="d-flex gap-2 flex-wrap">
                                            <button class="btn-selection" @click="selectAllMembers">Pilih Semua</button>
                                            <button class="btn-selection" @click="clearMemberSelection">
                                                Hapus Semua
                                            </button>
                                            <button
                                                v-if="hasMembersWithoutKPI"
                                                class="btn-selection btn-priority"
                                                @click="selectMembersWithoutKPI"
                                            >
                                                Pilih yang Belum Ada KPI
                                            </button>
                                        </div>
                                        <div class="selection-info mt-2">
                                            <small class="text-muted">
                                                Terpilih:
                                                {{ selectedMembers.length }}
                                                dari
                                                {{ availableTeamMembers.length }}
                                                anggota
                                                <span v-if="membersWithoutKPISelected" class="text-warning">
                                                    ({{ membersWithoutKPI.length }}
                                                    belum memiliki KPI)
                                                </span>
                                            </small>
                                        </div>
                                    </div>

                                    <div class="bulk-filters-bar mb-3">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-md-4">
                                                <div class="search-box compact">
                                                    <i class="bi bi-search"></i>
                                                    <input
                                                        type="text"
                                                        v-model="bulkFilters.search"
                                                        placeholder="Cari anggota tim..."
                                                        class="search-input"
                                                    />
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <select v-model="bulkFilters.division" class="filter-select compact">
                                                    <option value="">Semua Divisi</option>
                                                    <option v-for="div in uniqueDivisions" :key="div" :value="div">
                                                        {{ div }}
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <select v-model="bulkFilters.kpiStatus" class="filter-select compact">
                                                    <option value="">Status KPI</option>
                                                    <option value="without">Tersedia (Belum Ada KPI)</option>
                                                    <option value="with">Sudah Ada KPI</option>
                                                    <option value="not_eligible">Tidak Terdaftar di Jadwal</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="filter-info">
                                                    <small>{{ selectedMembers.length }} terpilih</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Desktop Table Selection -->
                                    <div class="d-none d-lg-block">
                                        <div class="table-responsive-wrapper">
                                            <table class="modern-table selection-table">
                                                <thead>
                                                    <tr>
                                                        <th class="header-cell">
                                                            <input
                                                                type="checkbox"
                                                                v-model="selectAllStep1"
                                                                @change="toggleSelectAllStep1"
                                                                class="table-checkbox"
                                                            />
                                                        </th>
                                                        <th class="header-cell">Nama Karyawan</th>
                                                        <th class="header-cell">Posisi</th>
                                                        <th class="header-cell">Divisi</th>
                                                        <th class="header-cell">Jadwal & Periode</th>
                                                        <th class="header-cell">Status KPI</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                        v-for="member in paginatedAvailableMembers"
                                                        :key="member.id"
                                                        class="table-row"
                                                        :class="{
                                                            selected: isMemberSelected(member.id),
                                                            disabled: member.hasSubmittedKPI || member.is_eligible === false,
                                                            'row-not-eligible': member.is_eligible === false,
                                                        }"
                                                        @click="
                                                            member.is_eligible !== false && !member.hasSubmittedKPI && toggleMemberSelection(member.id)
                                                        "
                                                    >
                                                        <td @click.stop>
                                                            <input
                                                                type="checkbox"
                                                                :checked="isMemberSelected(member.id)"
                                                                :disabled="member.hasSubmittedKPI || member.is_eligible === false"
                                                                @change="
                                                                    member.is_eligible !== false && !member.hasSubmittedKPI &&
                                                                    toggleMemberSelection(member.id)
                                                                "
                                                                class="table-checkbox"
                                                            />
                                                        </td>
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
                                                        <td>
                                                            {{ member.position }}
                                                        </td>
                                                        <td>
                                                            {{ member.division }}
                                                        </td>
                                                        <td>
                                                            <div class="schedule-cell-info">
                                                                <template v-if="member.is_eligible === false || member.sumber_jadwal === 'tidak_tercakup'">
                                                                    <div class="d-flex align-items-center gap-1">
                                                                        <span class="badge-jadwal-type badge-jadwal-none">
                                                                            <i class="bi bi-slash-circle me-1"></i>Tidak Terdaftar
                                                                        </span>
                                                                    </div>
                                                                    <div class="schedule-sub-text text-muted fst-italic mt-1">
                                                                        Belum masuk jadwal
                                                                    </div>
                                                                </template>
                                                                <template v-else>
                                                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                                                        <span :class="['badge-jadwal-type', member.sumber_jadwal === 'perorangan' ? 'badge-jadwal-perorangan' : 'badge-jadwal-global']">
                                                                            <i :class="['bi me-1', member.sumber_jadwal === 'perorangan' ? 'bi-person-fill' : 'bi-globe']"></i>
                                                                            {{ member.sumber_jadwal === 'perorangan' ? 'Perorangan' : 'Global' }}
                                                                        </span>
                                                                        <span v-if="member.start_periode" class="badge-start-period">
                                                                            <i class="bi bi-calendar-event me-1"></i>Mulai {{ formatStartPeriode(member.start_periode) }}
                                                                        </span>
                                                                    </div>
                                                                    <div v-if="member.nama_jadwal" class="schedule-sub-text text-muted mt-1">
                                                                        <i class="bi bi-tag-fill text-secondary me-1"></i>{{ member.nama_jadwal }}
                                                                    </div>
                                                                </template>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column gap-1">
                                                                <span
                                                                    v-if="member.is_eligible === false"
                                                                    class="status-badge status-not-eligible"
                                                                >
                                                                    <i class="bi bi-slash-circle me-1"></i>
                                                                    Tidak Terdaftar
                                                                </span>
                                                                <span
                                                                    v-else-if="member.hasSubmittedKPI"
                                                                    class="status-badge status-submitted"
                                                                >
                                                                    <i class="bi bi-check-circle me-1"></i>
                                                                    Sudah diajukan
                                                                </span>
                                                                <span
                                                                    v-else-if="
                                                                        !member.hasSubmittedKPI && hasApprover(member)
                                                                    "
                                                                    class="status-badge status-available"
                                                                >
                                                                    <i class="bi bi-plus-circle me-1"></i>
                                                                    Tersedia
                                                                </span>
                                                                <span v-else class="status-badge status-danger">
                                                                    <i class="bi bi-x-circle me-1"></i>
                                                                    Belum Ada Approver
                                                                </span>
                                                                <small
                                                                    v-if="member.is_eligible === false"
                                                                    class="text-muted fst-italic"
                                                                    style="font-size: 11px;"
                                                                >
                                                                    {{ member.ineligible_reason || 'Belum terdaftar di jadwal ini' }}
                                                                </small>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Mobile Card Selection -->
                                    <div class="d-block d-lg-none">
                                        <div class="member-selection-grid-mobile">
                                            <div
                                                v-for="member in paginatedAvailableMembers"
                                                :key="member.id"
                                                class="member-selection-card-mobile"
                                                :class="{
                                                    selected: isMemberSelected(member.id),
                                                    disabled: member.hasSubmittedKPI || member.is_eligible === false,
                                                    'card-not-eligible': member.is_eligible === false,
                                                }"
                                                @click="member.is_eligible !== false && !member.hasSubmittedKPI && toggleMemberSelection(member.id)"
                                            >
                                                <div @click.stop class="selection-checkbox-mobile">
                                                    <input
                                                        type="checkbox"
                                                        :checked="isMemberSelected(member.id)"
                                                        :disabled="member.hasSubmittedKPI || member.is_eligible === false"
                                                        @change="
                                                            member.is_eligible !== false && !member.hasSubmittedKPI && toggleMemberSelection(member.id)
                                                        "
                                                    />
                                                </div>
                                                <div class="selection-content-mobile">
                                                    <div class="selection-avatar-mobile">
                                                        {{ getInitials(member.name) }}
                                                    </div>
                                                    <div class="selection-info-mobile">
                                                        <h6 class="member-name">
                                                            {{ member.name }}
                                                        </h6>
                                                        <p class="member-position">
                                                            {{ member.position }}
                                                        </p>
                                                        <div class="member-division-mobile">
                                                            {{ member.division }}
                                                        </div>
                                                        <div class="member-schedule-tags mt-1">
                                                            <template v-if="member.is_eligible === false || member.sumber_jadwal === 'tidak_tercakup'">
                                                                <span class="badge-jadwal-type badge-jadwal-none">
                                                                    <i class="bi bi-slash-circle me-1"></i>Tidak Terdaftar
                                                                </span>
                                                            </template>
                                                            <template v-else>
                                                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                                                    <span :class="['badge-jadwal-type', member.sumber_jadwal === 'perorangan' ? 'badge-jadwal-perorangan' : 'badge-jadwal-global']">
                                                                        <i :class="['bi me-1', member.sumber_jadwal === 'perorangan' ? 'bi-person-fill' : 'bi-globe']"></i>
                                                                        {{ member.sumber_jadwal === 'perorangan' ? 'Perorangan' : 'Global' }}
                                                                    </span>
                                                                    <span v-if="member.start_periode" class="badge-start-period">
                                                                        <i class="bi bi-calendar-event me-1"></i>Mulai {{ formatStartPeriode(member.start_periode) }}
                                                                    </span>
                                                                </div>
                                                                <div v-if="member.nama_jadwal" class="schedule-sub-text text-muted mt-0-5" style="font-size: 11px;">
                                                                    <i class="bi bi-tag-fill text-secondary me-1"></i>{{ member.nama_jadwal }}
                                                                </div>
                                                            </template>
                                                        </div>
                                                        <div
                                                            v-if="member.is_eligible === false"
                                                            class="text-muted fst-italic mt-1"
                                                            style="font-size: 11px;"
                                                        >
                                                            <i class="bi bi-slash-circle text-danger me-1"></i>
                                                            <span>{{ member.ineligible_reason || 'Belum terdaftar di jadwal ini' }}</span>
                                                        </div>
                                                        <div
                                                            v-else-if="member.hasSubmittedKPI"
                                                            class="submission-info-mobile"
                                                        >
                                                            <i class="bi bi-check-circle"></i>
                                                            <span>Sudah diajukan</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="pagination-container mt-4"
                                        v-if="availableTeamMembers.length > itemsPerPage"
                                    >
                                        <div class="pagination-info">
                                            Menampilkan
                                            {{ pagination.bulk.startItem }}-{{ pagination.bulk.endItem }}
                                            dari
                                            {{ availableTeamMembers.length }}
                                            data
                                        </div>
                                        <div class="pagination-controls">
                                            <button
                                                class="pagination-btn pagination-prev"
                                                @click="previousPage"
                                                :disabled="pagination.bulk.currentPage === 1"
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
                                                        active: page === pagination.bulk.currentPage,
                                                    }"
                                                    @click="goToPage(page)"
                                                >
                                                    {{ page }}
                                                </button>
                                            </div>

                                            <button
                                                class="pagination-btn pagination-next"
                                                @click="nextPage"
                                                :disabled="pagination.bulk.currentPage === pagination.bulk.totalPages"
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
                                        <DotLottieVue
                                            style="height: 150px; width: 150px"
                                            autoplay
                                            loop
                                            src="/animation/empty-data.json"
                                        />
                                    </div>
                                    <h4>Tidak Ada Anggota Tim</h4>
                                    <p>Belum ada anggota tim yang tersedia</p>
                                </div>
                            </div>
                            <div v-else class="row justify-content-center fade-in">
                                <div class="col-12">
                                    <div class="loading-state">
                                        <div class="loading-spinner">
                                            <i
                                                class="bi bi-arrow-repeat d-flex justify-content-center align-items-center"
                                            ></i>
                                        </div>
                                        <h4 class="loading-title">Memuat Data Team...</h4>
                                        <p class="loading-description">Sedang mengambil data team</p>
                                    </div>
                                </div>
                            </div>

                            <div class="step-actions">
                                <button
                                    class="btn-modern btn-primary"
                                    @click="nextBulkStep"
                                    :disabled="selectedMembers.length === 0"
                                >
                                    Lanjut ke Pengisian KPI
                                    <i
                                        class="d-flex justify-content-center align-items-center bi bi-arrow-right ms-2"
                                    ></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: KPI Configuration -->
                        <div v-else-if="bulkCurrentStep === 2" class="bulk-step-content">
                            <div class="step-header">
                                <h5>Pengisian Parameter KPI</h5>
                                <p>Isi parameter KPI untuk anggota tim yang dipilih</p>
                                <div class="template-info-badge">
                                    <i class="bi bi-info-circle"></i>
                                    <span
                                        >Template KPI berlaku untuk semua anggota yang tidak mengaktifkan custom
                                        KPI</span
                                    >
                                </div>
                            </div>

                            <!-- Member Tabs dengan Slider -->
                            <div class="member-tabs-section mb-4">
                                <div class="member-tabs-slider">
                                    <div class="tabs-container">
                                        <button
                                            v-for="member in getSelectedMembersObjects()"
                                            :key="member.id"
                                            class="member-tab-slider"
                                            :class="{
                                                active: activeMemberTab && activeMemberTab.id === member.id,
                                                'has-revision': hasRevisionKPI(member),
                                            }"
                                            @click="selectMember(member)"
                                        >
                                            <div class="tab-avatar-slider">
                                                {{ getInitials(member.name) }}
                                            </div>
                                            <span class="tab-name-slider">{{ truncateText(member.name, 15) }}</span>
                                            <!-- Custom KPI Toggle per Member -->
                                            <div
                                                class="custom-kpi-indicator"
                                                @click.stop="toggleCustomKpiForMember(member)"
                                            >
                                                <i
                                                    class="bi"
                                                    :class="
                                                        member.useCustomKpi
                                                            ? 'bi-toggle-on text-success'
                                                            : 'bi-toggle-off text-muted'
                                                    "
                                                ></i>
                                            </div>
                                            <div
                                                v-if="hasRevisionKPI(member)"
                                                class="revision-indicator"
                                                title="Ada KPI yang perlu direvisi"
                                            >
                                                <i class="bi bi-exclamation-triangle"></i>
                                            </div>
                                        </button>
                                    </div>
                                    <div class="slider-controls">
                                        <button class="slider-btn prev" @click="scrollTabs(-1)">
                                            <i
                                                class="d-flex justify-content-center align-items-center bi bi-chevron-left"
                                            ></i>
                                        </button>
                                        <button class="slider-btn next" @click="scrollTabs(1)">
                                            <i
                                                class="d-flex justify-content-center align-items-center bi bi-chevron-right"
                                            ></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Alasan penolakan GLOBAL untuk anggota yang sedang dibuka.
                                 Markup & gaya sengaja disalin dari form individu
                                 (formKPI.vue .global-reject-banner) supaya leader
                                 melihat hal yang sama persis dengan yang dilihat
                                 karyawan pada formnya sendiri. Komentar per parameter
                                 tetap tampil di kartu KPI masing-masing. -->
                            <div
                                v-if="activeMemberTab && perluRevisi(activeMemberTab)"
                                class="global-reject-banner"
                            >
                                <i class="bi bi-x-octagon-fill"></i>
                                <div class="global-reject-text">
                                    <span class="global-reject-label">Alasan Penolakan (Umum)</span>
                                    <p class="global-reject-value">
                                        {{
                                            activeMemberTab.alasan_reject_global ||
                                            'Approver tidak mencantumkan alasan umum.'
                                        }}
                                    </p>
                                    <p
                                        v-if="jumlahParameterBerkomentar(activeMemberTab)"
                                        class="global-reject-param"
                                    >
                                        {{ jumlahParameterBerkomentar(activeMemberTab) }} parameter
                                        punya catatan khusus — ditandai pada kartunya masing-masing.
                                    </p>
                                    <p v-else class="global-reject-param">
                                        Tidak ada catatan per parameter.
                                    </p>
                                </div>
                            </div>

                            <!-- Current Member Info -->
                            <div class="current-member-info mb-4" v-if="activeMemberTab">
                                <div class="member-info-card">
                                    <div class="member-avatar">
                                        {{ getInitials(activeMemberTab.name) }}
                                    </div>
                                    <div class="member-details">
                                        <h6>{{ activeMemberTab.name }}</h6>
                                        <p>
                                            {{ activeMemberTab.position }} •
                                            {{ activeMemberTab.division }}
                                        </p>
                                        <small class="text-muted">{{ activeMemberTab.employeeId }}</small>

                                        <!--
                                            Bulan mulai anggota ini. Sel bulan sebelumnya
                                            dimatikan di grid target, dan nilainya memang
                                            tidak akan disimpan - jadi sebabnya dijelaskan
                                            di sini, bukan dibiarkan jadi tebakan.
                                        -->
                                        <div v-if="bulanMulaiAnggotaAktif" class="bulan-mulai-anggota">
                                            <i class="bi bi-calendar-range me-1"></i>
                                            Mulai <strong>{{ bulanMulaiAnggotaAktif.label }}</strong>
                                            <span v-if="bulanMulaiAnggotaAktif.sumber === 'perorangan'">
                                                — jadwal perorangan
                                            </span>
                                        </div>
                                    </div>
                                    <div class="member-stats">
                                        <div class="stat">
                                            <span class="stat-value">{{ getMemberKpis(activeMemberTab).length }}</span>
                                            <span class="stat-label">KPI</span>
                                        </div>
                                        <div class="stat">
                                            <span class="stat-value"
                                                >{{ calculateMemberTotalWeight(activeMemberTab) }}%</span
                                            >
                                            <span class="stat-label">Weight</span>
                                        </div>
                                    </div>
                                    <div class="member-custom-toggle">
                                        <label class="custom-toggle-label">
                                            <input
                                                type="checkbox"
                                                :checked="activeMemberTab.useCustomKpi"
                                                @change="toggleCustomKpiForMember(activeMemberTab)"
                                                class="custom-toggle-checkbox"
                                            />
                                            <span class="custom-toggle-slider"></span>
                                            <span class="custom-toggle-text">
                                                {{ activeMemberTab.useCustomKpi ? 'KPI Custom' : 'Gunakan Template' }}
                                            </span>
                                        </label>
                                        <div class="custom-toggle-description">
                                            <small class="text-muted">
                                                {{
                                                    activeMemberTab.useCustomKpi
                                                        ? 'KPI khusus untuk ' + activeMemberTab.name
                                                        : 'Menggunakan template standar'
                                                }}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- KPI Form -->
                            <div class="kpi-configuration-section">
                                <!-- Compact Dashboard untuk Multiple KPI -->
                                <div class="compact-dashboard mb-4">
                                    <div class="dashboard-header d-block d-md-flex">
                                        <h6>KPI Dashboard</h6>
                                        <div class="dashboard-info ms-2">
                                            <span class="info-badge" v-if="!activeMemberTab.useCustomKpi">
                                                <i class="bi bi-info-circle me-1"></i>
                                                Menggunakan Template
                                            </span>
                                            <span class="info-badge success" v-else>
                                                <i class="bi bi-check-circle me-1"></i>
                                                KPI Custom Aktif
                                            </span>
                                            <span v-if="hasRevisionKPI(activeMemberTab)" class="info-badge warning">
                                                <i class="bi bi-exclamation-triangle me-1"></i>
                                                Ada Revisi
                                            </span>
                                        </div>
                                    </div>
                                    <div class="cards-scroll-container">
                                        <div class="cards-scroll-wrapper">
                                            <!-- Add New Card - Tampil untuk semua kondisi -->
                                            <div class="compact-card add-new" @click="addNewKpi(activeMemberTab)">
                                                <div class="card-main">
                                                    <div class="add-icon">
                                                        <i
                                                            class="d-flex justify-content-center align-items-center bi bi-plus-lg"
                                                        ></i>
                                                    </div>
                                                    <span class="card-title">Tambah Parameter</span>
                                                </div>
                                                <div class="card-hint">Klik untuk menambah</div>
                                            </div>

                                            <!-- KPI Cards -->
                                            <div
                                                v-for="(kpi, index) in activeKpiContainer"
                                                :key="index"
                                                class="compact-card"
                                                :class="{
                                                    active: getActiveKpiIndex(activeMemberTab) === index,
                                                    completed: isFormCompleted(kpi),
                                                    custom: activeMemberTab.useCustomKpi,
                                                    revision: isRevisionKPI(activeMemberTab, kpi),
                                                }"
                                                @click="switchToKpiForm(activeMemberTab, index)"
                                            >
                                                <div class="card-main">
                                                    <div class="kpi-icon">
                                                        <i
                                                            class="d-flex justify-content-center align-items-center"
                                                            :class="getKpiIcon(kpi.bscCategory)"
                                                        ></i>
                                                    </div>
                                                    <div class="kpi-info">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <h4 class="card-title">
                                                                {{
                                                                    truncateText(kpi?.parameter, 9) || 'Parameter Baru'
                                                                }}
                                                            </h4>
                                                            <div class="card-actions">
                                                                <button
                                                                    v-if="activeKpiContainer.length > 1"
                                                                    @click.stop="removeKpi(activeMemberTab, index)"
                                                                    class="btn-remove"
                                                                    title="Hapus KPI"
                                                                >
                                                                    <i
                                                                        class="d-flex justify-content-center align-items-center bi bi-trash"
                                                                    ></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="kpi-meta">
                                                            <span class="category">
                                                                {{
                                                                    formatBscCategory(kpi.bscCategory) ||
                                                                    'Belum Ada Kategori'
                                                                }}
                                                            </span>
                                                            <span class="weight" v-if="kpi.weight > 0">
                                                                {{ kpi.weight }}%
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="progress-container">
                                                        <div class="progress-bar">
                                                            <div
                                                                class="progress-fill"
                                                                :style="{
                                                                    width: calculateCompletion(kpi) + '%',
                                                                }"
                                                            ></div>
                                                        </div>
                                                        <span class="progress-text"
                                                            >{{ calculateCompletion(kpi) }}%</span
                                                        >
                                                    </div>
                                                    <span class="card-badge">#{{ index + 1 }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Form KPI -->
                                <KpiForm
                                    class="kpiform-border"
                                    :oldDataComponent="getOldDataForMember(activeMemberTab)"
                                    :modelValue="activeKpi"
                                    :fields="bulkKpiFields"
                                    :kpis="activeKpiContainer"
                                    :ApprovalDataComponent="approvalData(activeMemberTab)"
                                    :activeFormIndex="getActiveKpiIndex(activeMemberTab)"
                                    :formulas="formulas"
                                    :categories="categories"
                                    :availableUnits="activeKpi?.availableUnits"
                                    :loading="loading"
                                    :showValidation="showValidation"
                                    :validationErrorsComponents="validationErrors"
                                    :showHeader="true"
                                    :showNavigation="true"
                                    :showHeaderActions="false"
                                    :showQuickActions="false"
                                    :isEditFormComponent="isEditModeForMember(activeMemberTab)"
                                    :statusTransaksi="getSubmissionStatusForMember(activeMemberTab)"
                                    :totalWeight="calculateMemberTotalWeight(activeMemberTab)"
                                    :remainingWeight="calculateMemberRemainingWeight(activeMemberTab)"
                                    :maxAllowedWeight="getMaxAllowedWeightForMember(activeMemberTab)"
                                    @update:modelValue="updateMemberKpiData(activeMemberTab, $event)"
                                    @switchToForm="switchToKpiForm(activeMemberTab, $event)"
                                    @removeKpi="removeKpi(activeMemberTab, $event)"
                                    @triggerTarget="triggerTargetForMember(activeMemberTab)"
                                    @onFormulaChange="onFormulaChangeForMember(activeMemberTab, $event)"
                                    @weightExceeded="handleWeightExceededForMember"
                                />
                            </div>

                            <!-- Bottom Action Bar untuk Step 2 -->
                            <div class="bottom-action-bar-integrated fade-in">
                                <div class="action-content-integrated">
                                    <div class="action-info justify-content-between d-flex d-md-inline">
                                        <span class="info-text me-md-3">
                                            {{ activeKpiContainer.length }} KPI Ready
                                            <span
                                                v-if="!activeMemberTab.useCustomKpi"
                                                class="text-muted d-none d-md-inline"
                                                >(Template)</span
                                            >
                                            <span v-else class="text-success d-none d-md-inline">(Custom)</span>
                                        </span>
                                        <span
                                            class="weight-info"
                                            :class="{
                                                danger: calculateMemberTotalWeight(activeMemberTab) > 100,
                                            }"
                                        >
                                            Total Weight:
                                            {{ calculateMemberTotalWeight(activeMemberTab) }}%
                                        </span>
                                    </div>
                                    <div class="action-buttons-integrated">
                                        <button @click="validateAllMembers" class="btn-action-integrated validate">
                                            <i
                                                class="d-flex justify-content-center align-items-center bi bi-check-circle"
                                            ></i>
                                            Validate
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="step-actions">
                                <div class="d-flex justify-content-between w-100">
                                    <button class="btn-modern btn-secondary" @click="previousBulkStep">
                                        <i
                                            class="d-flex justify-content-center align-items-center bi bi-arrow-left me-2"
                                        ></i>
                                        Kembali
                                    </button>
                                    <button
                                        class="btn-modern btn-primary"
                                        @click="nextBulkStep"
                                        :disabled="!isStep2Valid"
                                    >
                                        Lanjut ke Preview
                                        <i
                                            class="d-flex justify-content-center align-items-center bi bi-arrow-right ms-2"
                                        ></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Preview -->
                        <div v-else-if="bulkCurrentStep === 3" class="bulk-step-content">
                            <div v-if="!loading.bulk">
                                <!-- Modern Step Header -->
                                <div class="step-3-header mb-4">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                        <div>
                                            <h5 class="step-3-title mb-1">
                                                <i class="bi bi-shield-check text-primary me-2"></i>Verifikasi & Tinjau Pengajuan KPI Tim
                                            </h5>
                                            <p class="step-3-subtitle text-muted mb-0">
                                                Pastikan daftar parameter, target bulanan, dan total bobot (100%) untuk setiap anggota tim sudah tepat sebelum diajukan ke approver.
                                            </p>
                                        </div>
                                        <div class="step-3-header-badges d-flex align-items-center gap-2">
                                            <span class="badge bg-light text-dark border px-3 py-2">
                                                <i class="bi bi-calendar3 me-1 text-primary"></i>
                                                {{ activeSchedule?.nama_kegiatan }} ({{ activeSchedule?.tahun }})
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Verification Summary Cards -->
                                    <div class="verification-stats-grid">
                                        <div class="verification-stat-card">
                                            <div class="stat-icon-wrapper bg-primary-soft text-primary">
                                                <i class="bi bi-people-fill"></i>
                                            </div>
                                            <div class="stat-info">
                                                <span class="stat-label">Anggota Dipilih</span>
                                                <h4 class="stat-value">{{ selectedMembers.length }} <small>Orang</small></h4>
                                            </div>
                                        </div>

                                        <div class="verification-stat-card">
                                            <div class="stat-icon-wrapper bg-info-soft text-info">
                                                <i class="bi bi-layers-fill"></i>
                                            </div>
                                            <div class="stat-info">
                                                <span class="stat-label">Total Indikator KPI</span>
                                                <h4 class="stat-value">{{ totalKpisAcrossSelected }} <small>Parameter</small></h4>
                                            </div>
                                        </div>

                                        <div class="verification-stat-card" :class="isStep3Valid ? 'valid' : 'invalid'">
                                            <div class="stat-icon-wrapper" :class="isStep3Valid ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger'">
                                                <i :class="isStep3Valid ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-triangle-fill'"></i>
                                            </div>
                                            <div class="stat-info">
                                                <span class="stat-label">Status Validasi Bobot</span>
                                                <h4 class="stat-value fs-6" :class="isStep3Valid ? 'text-success' : 'text-danger'">
                                                    {{ isStep3Valid ? 'Semua Bobot 100% (Valid)' : `${invalidMembersCount} Anggota Belum 100%` }}
                                                </h4>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Notice jika ada variasi bulan mulai antar anggota -->
                                    <div v-if="startPeriodeRingkasan.length > 1" class="start-periode-notice-card mt-3">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="bi bi-info-circle-fill text-primary fs-5 mt-0-5"></i>
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold text-dark">Informasi Bulan Mulai Efektif Anggota:</div>
                                                <p class="mb-2 text-muted" style="font-size: 13px;">
                                                    Sebagian anggota memiliki jadwal perorangan dengan bulan mulai yang berbeda. Target sebelum bulan mulai masing-masing tidak akan disimpan.
                                                </p>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <span v-for="kelompok in startPeriodeRingkasan" :key="kelompok.mulai" class="badge-start-summary">
                                                        <strong>{{ kelompok.label }}:</strong> {{ kelompok.anggota.length }} orang ({{ kelompok.ringkas }})
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Member Preview Grid -->
                                <div class="preview-section mb-4">
                                    <div class="preview-grid">
                                        <div
                                            v-for="memberId in selectedMembers"
                                            :key="memberId"
                                            class="preview-card"
                                            :class="{ 'card-invalid-weight': calculateMemberTotalWeight(getMemberById(memberId)) !== 100 }"
                                        >
                                            <!-- Card Header: Profile, Status & Edit -->
                                            <div class="preview-card-header">
                                                <div class="preview-user-profile">
                                                    <div class="preview-avatar">
                                                        {{ getInitials(getMemberById(memberId)?.name) }}
                                                    </div>
                                                    <div class="preview-user-details">
                                                        <div class="preview-name-row">
                                                            <h6 class="member-preview-name" :title="getMemberById(memberId)?.name">
                                                                {{ getMemberById(memberId)?.name }}
                                                            </h6>
                                                            <span class="member-id-pill">{{ getMemberById(memberId)?.employeeId }}</span>
                                                        </div>
                                                        <div class="member-preview-position">
                                                            <span>{{ getMemberById(memberId)?.position }}</span>
                                                            <span v-if="getMemberById(memberId)?.division" class="pos-divider">&bull;</span>
                                                            <span v-if="getMemberById(memberId)?.division" class="preview-division">{{ getMemberById(memberId)?.division }}</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="preview-header-actions">
                                                    <span class="status-badge" :class="getPreviewStatusClass(memberId)">
                                                        <i :class="calculateMemberTotalWeight(getMemberById(memberId)) === 100 ? 'bi bi-check-circle-fill me-1' : 'bi bi-exclamation-circle-fill me-1'"></i>
                                                        {{ getPreviewStatusText(memberId) }}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        class="btn-edit-shortcut"
                                                        @click="editMemberFromPreview(memberId)"
                                                        title="Edit KPI anggota ini di Step 2"
                                                    >
                                                        <i class="bi bi-pencil-square me-1"></i>Edit
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Schedule & Custom Mode Badges Row -->
                                            <div class="preview-meta-chips">
                                                <span :class="['badge-jadwal-type', getMemberById(memberId)?.sumber_jadwal === 'perorangan' ? 'badge-jadwal-perorangan' : 'badge-jadwal-global']">
                                                    <i :class="['bi me-1', getMemberById(memberId)?.sumber_jadwal === 'perorangan' ? 'bi-person-badge-fill' : 'bi-globe2']"></i>
                                                    Jadwal {{ getMemberById(memberId)?.sumber_jadwal === 'perorangan' ? 'Perorangan' : 'Global' }}
                                                </span>
                                                <span v-if="getMemberById(memberId)?.start_periode" class="badge-start-period">
                                                    <i class="bi bi-calendar-check me-1"></i>Mulai {{ formatStartPeriode(getMemberById(memberId)?.start_periode) }}
                                                </span>
                                                <span v-if="getMemberById(memberId)?.useCustomKpi" class="badge-custom-kpi">
                                                    <i class="bi bi-sliders2 me-1"></i>Custom KPI
                                                </span>
                                            </div>

                                            <!-- Card Body: KPI Parameters List -->
                                            <div class="preview-kpis">
                                                <div class="kpi-list-header">
                                                    <span class="kpi-list-title">
                                                        <i class="bi bi-list-nested me-1 text-primary"></i>Daftar Parameter KPI
                                                    </span>
                                                    <span class="kpi-count-badge">
                                                        {{ getMemberKpis(getMemberById(memberId)).length }} Parameter
                                                    </span>
                                                </div>

                                                <div class="kpi-list-container">
                                                    <div v-if="getMemberKpis(getMemberById(memberId)).length === 0" class="empty-kpi-preview">
                                                        <i class="bi bi-inbox fs-3 d-block mb-1 text-muted"></i>
                                                        <span>Belum ada KPI yang diisi untuk anggota ini</span>
                                                    </div>
                                                    <div
                                                        v-for="(kpi, index) in getMemberKpis(getMemberById(memberId))"
                                                        :key="index"
                                                        class="preview-kpi-item"
                                                    >
                                                        <div class="kpi-item-index">{{ index + 1 }}</div>
                                                        <div class="kpi-item-content">
                                                            <div class="kpi-item-top">
                                                                <span class="kpi-item-title" :title="kpi.parameter">
                                                                    {{ kpi.parameter || 'Parameter Tanpa Nama' }}
                                                                </span>
                                                                <span class="kpi-weight-badge">
                                                                    {{ kpi.weight }}%
                                                                </span>
                                                            </div>
                                                            <div class="kpi-meta-tags">
                                                                <span v-if="getKpiCategoryName(kpi)" class="meta-tag tag-category" :title="'Kategori: ' + getKpiCategoryName(kpi)">
                                                                    <i class="bi bi-folder2 me-1"></i>{{ getKpiCategoryName(kpi) }}
                                                                </span>
                                                                <span class="meta-tag tag-target" :title="'Skala Target: ' + getTargetStatusForPreview(kpi)">
                                                                    <i class="bi bi-bullseye me-1"></i>{{ getTargetStatusForPreview(kpi) }}
                                                                </span>
                                                                <span v-if="getKpiUnitName(kpi)" class="meta-tag tag-unit" :title="'Satuan: ' + getKpiUnitName(kpi)">
                                                                    <i class="bi bi-tag me-1"></i>{{ getKpiUnitName(kpi) }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Card Footer: Total Weight Progress Bar & Validation -->
                                            <div class="preview-summary">
                                                <div class="d-flex justify-content-between align-items-center mb-1.5">
                                                    <span class="summary-label">Total Bobot:</span>
                                                    <span
                                                        class="summary-weight-val"
                                                        :class="calculateMemberTotalWeight(getMemberById(memberId)) === 100 ? 'weight-valid' : 'weight-invalid'"
                                                    >
                                                        {{ calculateMemberTotalWeight(getMemberById(memberId)) }}% <small class="text-muted fw-normal">/ 100%</small>
                                                    </span>
                                                </div>
                                                <div class="progress weight-progress">
                                                    <div
                                                        class="progress-bar"
                                                        :class="calculateMemberTotalWeight(getMemberById(memberId)) === 100 ? 'bg-success' : 'bg-danger'"
                                                        role="progressbar"
                                                        :style="{ width: `${Math.min(calculateMemberTotalWeight(getMemberById(memberId)), 100)}%` }"
                                                    ></div>
                                                </div>
                                                <div
                                                    v-if="calculateMemberTotalWeight(getMemberById(memberId)) !== 100"
                                                    class="summary-validation alert-warning-soft"
                                                >
                                                    <i class="bi bi-exclamation-triangle-fill text-warning me-1.5 flex-shrink-0"></i>
                                                    <span class="validation-text">Total bobot harus tepat 100% (saat ini {{ calculateMemberTotalWeight(getMemberById(memberId)) }}%)</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Confirmation Agreement Box -->
                                <div class="confirmation-agreement-card mb-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="custom-checkbox-wrapper">
                                            <input
                                                type="checkbox"
                                                v-model="confirmationChecked"
                                                id="confirmation-check"
                                                class="checklist-checkbox-custom"
                                            />
                                        </div>
                                        <label for="confirmation-check" class="agreement-label mb-0 cursor-pointer">
                                            <div class="fw-semibold text-dark mb-0-5">
                                                Saya menyatakan telah memeriksa dan menyetujui seluruh parameter & target KPI tim yang diajukan
                                            </div>
                                            <div class="text-muted" style="font-size: 12.5px;">
                                                Setelah diajukan, pengajuan akan langsung diteruskan ke alur approval atasan/approver masing-masing anggota tim.
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Step Actions -->
                                <div class="step-actions">
                                    <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-2">
                                        <button class="btn-modern btn-secondary" @click="previousBulkStep">
                                            <i class="bi bi-arrow-left me-2"></i>
                                            Kembali ke Pengisian KPI
                                        </button>
                                        <button
                                            class="btn-modern btn-success"
                                            @click="submitBulkKpis"
                                            :disabled="!confirmationChecked || !isStep3Valid"
                                        >
                                            <i class="bi bi-send-fill me-2"></i>
                                            Ajukan KPI Tim ({{ selectedMembers.length }} Orang)
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="row justify-content-center fade-in">
                                <div class="col-12">
                                    <div class="loading-state">
                                        <div class="loading-spinner upload">
                                            <lord-icon
                                                src="https://cdn.lordicon.com/ukdwhewu.json"
                                                trigger="loop"
                                                delay="300"
                                                colors="primary:#6366f1"
                                                style="width: 50px; height: 50px; /* padding: 20px; */"
                                            >
                                            </lord-icon>
                                        </div>
                                        <h4 class="loading-title">Menyimpan data...</h4>

                                        <p class="loading-description">Sedang menyimpan data KPI Team</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Tab 3: Submission Progress -->
                <template #progress>
                    <div class="submission-progress-content">
                        <!-- Progress Header -->
                        <div class="progress-header mb-4">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h4 class="section-title">Progress Pengajuan KPI</h4>
                                    <p class="section-subtitle">Pantau status pengajuan KPI seluruh tim</p>
                                </div>
                                <!-- <div class="col-md-4">
                                    <div class="progress-filters">
                                        <select
                                            v-model="progressFilter"
                                            class="filter-select"
                                        >
                                            <option value="all">
                                                Semua Status
                                            </option>
                                            <option value="waiting_approval">
                                                Menunggu Approval
                                            </option>
                                            <option value="rejected">
                                                Ditolak
                                            </option>
                                            <option value="approved">
                                                Disetujui
                                            </option>
                                            <option value="resubmitted">
                                                Mengajukan Ulang
                                            </option>
                                        </select>
                                    </div>
                                </div> -->
                            </div>
                        </div>

                        <!-- Compact Progress Stats -->
                        <!-- <div class="compact-progress-stats mb-4">
                            <div class="stats-grid-compact">
                                <div
                                    class="stat-card-compact"
                                    :class="{
                                        active: progressFilter === 'all',
                                    }"
                                    @click="progressFilter = 'all'"
                                >
                                    <div class="stat-icon-compact all">
                                        <i
                                            class="d-flex justify-content-center align-items-center bi bi-grid-3x3"
                                        ></i>
                                    </div>
                                    <div class="stat-content-compact">
                                        <h4>{{ teamMembers.length }}</h4>
                                        <p>Total</p>
                                    </div>
                                </div>
                                <div
                                    class="stat-card-compact"
                                    :class="{
                                        active:
                                            progressFilter ===
                                            'waiting_approval',
                                    }"
                                    @click="progressFilter = 'waiting_approval'"
                                >
                                    <div class="stat-icon-compact waiting">
                                        <i
                                            class="d-flex justify-content-center align-items-center bi bi-clock"
                                        ></i>
                                    </div>
                                    <div class="stat-content-compact">
                                        <h4>
                                            {{ progressStats.waiting_approval }}
                                        </h4>
                                        <p>Menunggu</p>
                                    </div>
                                </div>
                                <div
                                    class="stat-card-compact"
                                    :class="{
                                        active: progressFilter === 'rejected',
                                    }"
                                    @click="progressFilter = 'rejected'"
                                >
                                    <div class="stat-icon-compact rejected">
                                        <i
                                            class="d-flex justify-content-center align-items-center bi bi-x-circle"
                                        ></i>
                                    </div>
                                    <div class="stat-content-compact">
                                        <h4>{{ progressStats.rejected }}</h4>
                                        <p>Ditolak</p>
                                    </div>
                                </div>
                                <div
                                    class="stat-card-compact"
                                    :class="{
                                        active: progressFilter === 'approved',
                                    }"
                                    @click="progressFilter = 'approved'"
                                >
                                    <div class="stat-icon-compact approved">
                                        <i
                                            class="d-flex justify-content-center align-items-center bi bi-check-circle"
                                        ></i>
                                    </div>
                                    <div class="stat-content-compact">
                                        <h4>{{ progressStats.approved }}</h4>
                                        <p>Disetujui</p>
                                    </div>
                                </div>
                            </div>
                        </div> -->
                        <div class="progress-filters-bar mb-4">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-3">
                                    <div class="search-box">
                                        <i class="bi bi-search"></i>
                                        <input
                                            type="text"
                                            v-model="progressFilters.search"
                                            placeholder="Cari nama Tim..."
                                            class="search-input"
                                        />
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <select v-model="progressFilters.division" class="filter-select">
                                        <option value="">Semua Divisi</option>
                                        <option v-for="div in uniqueDivisions" :key="div" :value="div">
                                            {{ div }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select v-model="progressFilters.position" class="filter-select">
                                        <option value="">Semua Jabatan</option>
                                        <option v-for="pos in uniquePositions" :key="pos" :value="pos">
                                            {{ pos }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select v-model="progressFilters.status" class="filter-select">
                                        <option value="all">Semua Status</option>
                                        <option value="waiting_approval">Menunggu</option>
                                        <option value="approved">Disetujui</option>
                                        <option value="rejected">Ditolak</option>
                                        <option value="resubmitted">Ajukan Ulang</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <div class="filter-stats">
                                        <div
                                            class="stat-badge waiting"
                                            @click="progressFilters.status = 'waiting_approval'"
                                        >
                                            <span class="count">{{ progressStats.waiting_approval }}</span>
                                            <span class="label">Menunggu</span>
                                        </div>
                                        <div class="stat-badge approved" @click="progressFilters.status = 'approved'">
                                            <span class="count">{{ progressStats.approved }}</span>
                                            <span class="label">Disetujui</span>
                                        </div>
                                        <div class="stat-badge rejected" @click="progressFilters.status = 'rejected'">
                                            <span class="count">{{ progressStats.rejected }}</span>
                                            <span class="label">Ditolak</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div></div>
                        <div v-if="!loading.team">
                            <div v-if="filteredProgressMembers?.length != 0">
                                <!-- Desktop Table View -->
                                <div class="d-none d-lg-block">
                                    <div class="table-responsive-wrapper">
                                        <table class="modern-table">
                                            <thead>
                                                <tr>
                                                    <th class="header-cell">Nama Karyawan</th>
                                                    <th class="header-cell">Posisi</th>
                                                    <th class="header-cell">Jadwal & Periode</th>
                                                    <th class="header-cell">Total KPI</th>
                                                    <th class="header-cell">Total Weight</th>
                                                    <th class="header-cell">Status</th>
                                                    <th class="header-cell">Approver</th>
                                                    <th class="header-cell">Terakhir Diupdate</th>
                                                    <th class="header-cell">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="member in paginatedProgressMembers"
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
                                                    <td>
                                                        {{ member.position }}
                                                    </td>
                                                    <td>
                                                        <div class="schedule-cell-info">
                                                            <template v-if="member.is_eligible === false || member.sumber_jadwal === 'tidak_tercakup'">
                                                                <div class="d-flex align-items-center gap-1">
                                                                    <span class="badge-jadwal-type badge-jadwal-none">
                                                                        <i class="bi bi-slash-circle me-1"></i>Tidak Terdaftar
                                                                    </span>
                                                                </div>
                                                                <div class="schedule-sub-text text-muted fst-italic mt-1">
                                                                    Belum masuk jadwal
                                                                </div>
                                                            </template>
                                                            <template v-else>
                                                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                                                    <span :class="['badge-jadwal-type', member.sumber_jadwal === 'perorangan' ? 'badge-jadwal-perorangan' : 'badge-jadwal-global']">
                                                                        <i :class="['bi me-1', member.sumber_jadwal === 'perorangan' ? 'bi-person-fill' : 'bi-globe']"></i>
                                                                        {{ member.sumber_jadwal === 'perorangan' ? 'Perorangan' : 'Global' }}
                                                                    </span>
                                                                    <span v-if="member.start_periode" class="badge-start-period">
                                                                        <i class="bi bi-calendar-event me-1"></i>Mulai {{ formatStartPeriode(member.start_periode) }}
                                                                    </span>
                                                                </div>
                                                                <div v-if="member.nama_jadwal" class="schedule-sub-text text-muted mt-1">
                                                                    <i class="bi bi-tag-fill text-secondary me-1"></i>{{ member.nama_jadwal }}
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="count-badge">{{ member.kpis?.length || 0 }}</span>
                                                    </td>
                                                    <td>
                                                        <span class="weight-value"
                                                            >{{ calculateTotalWeight(member.kpis) }}%</span
                                                        >
                                                    </td>
                                                    <td>
                                                        <span
                                                            :class="`status-badge status-${getKpiStatus(member).class}`"
                                                        >
                                                            {{ getKpiStatus(member).text }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="approver-info">{{ getApproverInfo(member) }}</span>
                                                    </td>
                                                    <td>
                                                        {{ formatDateString(member.lastUpdate) }}
                                                    </td>
                                                    <td>
                                                        <div class="action-cell">
                                                            <button
                                                                class="btn-action btn-view"
                                                                @click="viewMemberKpis(member)"
                                                                title="Lihat Detail"
                                                            >
                                                                <i class="bi bi-eye"></i>
                                                            </button>
                                                            <button
                                                                v-if="canEditKpi(member)"
                                                                class="btn-action btn-edit"
                                                                @click="editMemberKpis(member)"
                                                                title="Edit KPI"
                                                            >
                                                                <i class="bi bi-pencil"></i>
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
                                    <div class="progress-cards-grid">
                                        <div
                                            v-for="member in paginatedProgressMembers"
                                            :key="member.id"
                                            class="progress-card"
                                        >
                                            <div class="card-header">
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
                                                    <div class="member-schedule-tags mt-1">
                                                        <template v-if="member.is_eligible === false || member.sumber_jadwal === 'tidak_tercakup'">
                                                            <span class="badge-jadwal-type badge-jadwal-none">
                                                                <i class="bi bi-slash-circle me-1"></i>Tidak Terdaftar
                                                            </span>
                                                        </template>
                                                        <template v-else>
                                                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                                                <span :class="['badge-jadwal-type', member.sumber_jadwal === 'perorangan' ? 'badge-jadwal-perorangan' : 'badge-jadwal-global']">
                                                                    <i :class="['bi me-1', member.sumber_jadwal === 'perorangan' ? 'bi-person-fill' : 'bi-globe']"></i>
                                                                    {{ member.sumber_jadwal === 'perorangan' ? 'Perorangan' : 'Global' }}
                                                                </span>
                                                                <span v-if="member.start_periode" class="badge-start-period">
                                                                    <i class="bi bi-calendar-event me-1"></i>Mulai {{ formatStartPeriode(member.start_periode) }}
                                                                </span>
                                                            </div>
                                                            <div v-if="member.nama_jadwal" class="schedule-sub-text text-muted mt-0-5" style="font-size: 11px;">
                                                                <i class="bi bi-tag-fill text-secondary me-1"></i>{{ member.nama_jadwal }}
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                                <div class="member-status">
                                                    <span :class="`status-badge status-${getKpiStatus(member).class}`">
                                                        {{ getKpiStatus(member).text }}
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="card-content">
                                                <div class="progress-stats">
                                                    <div class="stat">
                                                        <i
                                                            class="d-flex justify-content-center align-items-center bi bi-layers"
                                                        ></i>
                                                        <span>{{ member.kpis?.length || 0 }} KPI</span>
                                                    </div>
                                                    <div class="stat">
                                                        <i class="bi bi-speedometer2"></i>
                                                        <span>{{ calculateTotalWeight(member.kpis) }}%</span>
                                                    </div>
                                                </div>
                                                <div class="approver-info">
                                                    <small>Approver: {{ getApproverInfo(member) }}</small>
                                                </div>
                                                <div class="update-info">
                                                    <small>Update: {{ formatDateString(member.lastUpdate) }}</small>
                                                </div>
                                            </div>

                                            <div class="card-actions">
                                                <button class="btn-action btn-view" @click="viewMemberKpis(member)">
                                                    <i
                                                        class="d-flex justify-content-center align-items-center bi bi-eye"
                                                    ></i>
                                                </button>
                                                <button
                                                    v-if="canEditKpi(member)"
                                                    class="btn-action btn-edit"
                                                    @click="editMemberKpis(member)"
                                                >
                                                    <i
                                                        class="d-flex justify-content-center align-items-center bi bi-pencil"
                                                    ></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Pagination untuk Progress -->
                                <div
                                    class="pagination-container mt-4"
                                    v-if="filteredProgressMembers.length > itemsPerPage"
                                >
                                    <div class="pagination-info">
                                        Menampilkan
                                        {{ pagination.progress.startItem }}-{{ pagination.progress.endItem }}
                                        dari
                                        {{ filteredProgressMembers.length }}
                                        data
                                    </div>
                                    <div class="pagination-controls">
                                        <button
                                            class="pagination-btn pagination-prev"
                                            @click="previousPage"
                                            :disabled="pagination.progress.currentPage === 1"
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
                                                    active: page === pagination.progress.currentPage,
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
                                                pagination.progress.currentPage === pagination.progress.totalPages
                                            "
                                        >
                                            <i
                                                class="bi bi-chevron-right d-flex justify-content-center align-items-center"
                                            ></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="empty-state d-flex justify-content-center align-items-center flex-column"
                            >
                                <div class="empty-icon mb-4">
                                    <DotLottieVue
                                        style="height: 150px; width: 150px"
                                        autoplay
                                        loop
                                        src="/animation/empty-data.json"
                                    />
                                </div>
                                <h4>Tidak Ada Data Progress</h4>
                                <p>Belum ada KPI yang diajukan untuk periode ini</p>
                            </div>
                        </div>
                        <div v-else class="row justify-content-center fade-in">
                            <div class="col-12">
                                <div class="loading-state">
                                    <div class="loading-spinner">
                                        <i
                                            class="bi bi-arrow-repeat d-flex justify-content-center align-items-center"
                                        ></i>
                                    </div>
                                    <h4 class="loading-title">Memuat Data Team...</h4>
                                    <p class="loading-description">Sedang mengambil data team</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </ApprovalTabs>
        </div>

        <!-- No Active Schedule State -->
        <div v-else class="w-100">
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
                                                class="bi bi-people-fill d-flex justify-content-center align-items-center"
                                            ></i>
                                        </div>
                                        <div class="text-content">
                                            <h2 class="title fw-bold text-white text-start">Team KPI Management</h2>
                                            <p class="subtitle">Tidak ada Jadwal</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div
                                        class="header-actions d-flex gap-2 flex-nowrap flex-sm-column justify-content-center justify-content-md-start justify-content-md-center align-items-md-end"
                                    >
                                        <div class="date-range px-2 px-md-3 py-2 gap-2 gap-md-3">
                                            <div class="date-item">
                                                <i
                                                    v-if="dayNight == 'Siang'"
                                                    class="bi bi-cloud-sun-fill me-2 fs-4 fade-in d-flex justify-content-center align-items-center"
                                                ></i>
                                                <i
                                                    v-else
                                                    class="bi bi-cloud-moon-fill me-2 fs-4 fade-in d-flex justify-content-center align-items-center"
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

            <div class="d-flex flex-column gap-4 w-100 mb-4">
                <!-- Empty State Component using HcisEmptyState -->
                <HcisCard variant="default" sentiment="neutral" class="w-100">
                    <HcisEmptyState
                        title="Tidak Ada Penjadwalan Aktif"
                        :description="sebabTanpaPenjadwalan && sebabTanpaPenjadwalan.length ? 'Penjadwalan Form KPI sudah ada, tetapi tidak ada yang berlaku hari ini. Syaratnya dua: penjadwalan berstatus aktif dan hari ini berada di dalam jendela pengisiannya.' : 'Belum ada penjadwalan Form KPI yang dibuat untuk tim Anda saat ini.'"
                        icon="calendar-x"
                        variant="flat"
                        sentiment="neutral"
                        :animated="false"
                        class="w-100 py-4"
                    >
                        <template #actions>
                            <div class="d-flex flex-column align-items-center gap-3 mt-3 w-100" style="max-width: 640px; margin: 0 auto;">
                                <HcisAlert
                                    variant="info"
                                    appearance="soft"
                                    rounded="md"
                                    :pattern="false"
                                    class="text-start w-100"
                                >
                                    <template #default>
                                        Pastikan status penjadwalan dari admin sudah <strong>diaktifkan</strong> dan rentang tanggal jadwal mencakup tanggal hari ini (<strong>{{ formatDateString(currentDate) }}</strong>).
                                    </template>
                                </HcisAlert>

                                <HcisButton
                                    variant="primary"
                                    leading-icon="arrow-clockwise"
                                    label="Refresh Halaman"
                                    @click="refreshPage"
                                />
                            </div>
                        </template>
                    </HcisEmptyState>
                </HcisCard>

                <!-- Cakupan Tim Section using HcisCard & System Components -->
                <HcisCard
                    v-if="statusJadwalAnggota && statusJadwalAnggota.length"
                    variant="default"
                    sentiment="neutral"
                    class="w-100"
                >
                    <template #header>
                        <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-2 py-1">
                            <div class="d-flex align-items-center gap-2">
                                <HcisIcon name="people-fill" size="md" class="text-primary" />
                                <div>
                                    <h5 class="mb-0 fw-bold">Cakupan Penjadwalan Tim Anda</h5>
                                    <small class="text-muted">Status pendaftaran dan periode mulai pengisian Form KPI anggota tim</small>
                                </div>
                            </div>
                            <HcisBadge
                                :label="`${statusJadwalAnggota.length} Anggota`"
                                variant="neutral"
                                size="md"
                            />
                        </div>
                    </template>

                    <div class="row g-3">
                        <div
                            v-for="a in statusJadwalAnggota"
                            :key="a.kode_karyawan"
                            class="col-12 col-md-6 col-xl-4"
                        >
                            <HcisCard
                                variant="flat"
                                sentiment="neutral"
                                size="sm"
                                class="h-100"
                            >
                                <div class="d-flex flex-column h-100 justify-content-between gap-3">
                                    <div class="d-flex align-items-start gap-3">
                                        <HcisAvatar
                                            :name="a.nama"
                                            size="md"
                                            shape="rounded"
                                        />
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                                <span class="fw-bold text-truncate">{{ a.nama }}</span>
                                                <small class="text-muted">({{ a.kode_karyawan }})</small>
                                            </div>
                                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                                <HcisBadge
                                                    v-if="a.terjadwal"
                                                    :label="a.sumber || 'Perorangan'"
                                                    variant="brand"
                                                    size="xs"
                                                />
                                                <HcisBadge
                                                    v-if="a.start_periode"
                                                    :label="`Mulai ${a.start_periode}`"
                                                    variant="warning"
                                                    size="xs"
                                                />
                                                <HcisBadge
                                                    v-if="a.nama_jadwal"
                                                    :label="a.nama_jadwal"
                                                    variant="neutral"
                                                    size="xs"
                                                    class="text-truncate"
                                                    style="max-width: 140px;"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between pt-2 border-top gap-2 flex-wrap">
                                        <HcisBadge
                                            v-if="a.terbuka"
                                            label="Jadwal Terbuka"
                                            status="ACTIVE"
                                            size="sm"
                                        />
                                        <HcisBadge
                                            v-else-if="!a.terjadwal"
                                            label="Belum Terdaftar"
                                            status="INACTIVE"
                                            size="sm"
                                        />
                                        <HcisBadge
                                            v-else-if="(a.keterangan || '').toLowerCase().includes('dinonaktifkan')"
                                            label="Dinonaktifkan"
                                            status="CANCELLED"
                                            size="sm"
                                        />
                                        <HcisBadge
                                            v-else-if="(a.keterangan || '').toLowerCase().includes('ditutup')"
                                            label="Jendela Ditutup"
                                            status="CLOSED"
                                            size="sm"
                                        />
                                        <HcisBadge
                                            v-else-if="(a.keterangan || '').toLowerCase().includes('belum dibuka') || (a.keterangan || '').toLowerCase().includes('baru dibuka')"
                                            label="Belum Dibuka"
                                            variant="info"
                                            size="sm"
                                        />
                                        <HcisBadge
                                            v-else
                                            :label="a.keterangan"
                                            variant="neutral"
                                            size="sm"
                                        />
                                        <small class="text-muted text-end flex-grow-1 text-truncate" :title="a.keterangan">
                                            {{ a.keterangan }}
                                        </small>
                                    </div>
                                </div>
                            </HcisCard>
                        </div>
                    </div>
                </HcisCard>
            </div>
        </div>

        <!-- Member KPI Detail Modal -->
        <Modal
            :show="showMemberDetailModal"
            :title="`KPI - ${selectedMember?.name}`"
            size="large"
            @close="closeMemberDetailModal"
        >
            <template #body>
                <div class="member-kpi-detail" v-if="selectedMember">
                    <div class="member-detail-header">
                        <div class="member-avatar-lg">
                            {{ getInitials(selectedMember.name) }}
                        </div>
                        <div class="member-detail-info">
                            <h5>{{ selectedMember.name }}</h5>
                            <p>
                                {{ selectedMember.position }} •
                                {{ selectedMember.division }}
                            </p>
                            <p class="member-id">
                                {{ selectedMember.employeeId }}
                            </p>
                        </div>
                        <div class="member-status-detail">
                            <span :class="`status-badge status-${getKpiStatus(selectedMember).class}`">
                                {{ getKpiStatus(selectedMember).text }}
                            </span>
                        </div>
                    </div>

                    <div
                        class="approval-progress-section"
                        v-if="selectedMember.approvers && selectedMember.approvers.length > 0"
                    >
                        <SubmissionSidebar
                            :approvalData="selectedMember.approvers || []"
                            :activeApproval="true"
                            :activeHistory="false"
                        />
                    </div>
                    <div class="approval-progress-section nothing" v-else>
                        <i
                            class="bi bi-x-circle-fill rejection-icon d-flex justify-content-center align-items-center me-2"
                        ></i>
                        Tidak ada approver, Silahkan hubungi admin untuk menambahkan approver
                    </div>

                    <div class="member-kpi-list">
                        <div class="kpi-list-header">
                            <h6>Daftar KPI</h6>
                        </div>

                        <div v-if="selectedMember.kpis && selectedMember.kpis.length > 0" class="kpi-items">
                            <div class="kpi-list-compact">
                                <div
                                    v-for="(kpi, index) in selectedMember?.kpis"
                                    :key="kpi.id"
                                    class="kpi-item-compact"
                                >
                                    <div class="kpi-header-compact">
                                        <div class="kpi-number">#{{ index + 1 }}</div>
                                        <div class="kpi-main-info-compact">
                                            <h6>{{ kpi.parameter }}</h6>
                                            <div class="kpi-meta-compact">
                                                <span class="category-badge">{{ kpi.Nama_Kategori }}</span>
                                                <span class="weight-badge">{{ formatAchievement(kpi.weight) }}%</span>
                                            </div>
                                        </div>
                                        <div class="kpi-icon-compact d-none d-md-flex">
                                            <i
                                                class="d-flex justify-content-center align-content-center"
                                                :class="getKpiIcon(kpi.category)"
                                            ></i>
                                        </div>
                                    </div>

                                    <div class="kpi-content-compact">
                                        <div class="kpi-description-compact">
                                            <strong>Description:</strong>
                                            {{ kpi.description }}
                                        </div>

                                        <div
                                            class="targets-section-compact"
                                            v-if="kpi.targetDataPreview && kpi.targetDataPreview.length > 0"
                                        >
                                            <strong>Targets:</strong>
                                            <div class="targets-grid-compact">
                                                <div
                                                    v-for="target in kpi.targetDataPreview"
                                                    :key="target.month"
                                                    class="target-item-compact"
                                                >
                                                    <span class="target-label">{{ target.month }}:</span>
                                                    <span class="target-value">
                                                        {{
                                                            convertValueUnit(
                                                                target.target,
                                                                kpi.Format_Input,
                                                                kpi.Nama_Satuan,
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

                        <div v-else class="empty-kpis">
                            <div class="empty-icon d-flex justify-content-center align-items-center flex-column">
                                <DotLottieVue
                                    style="height: 150px; width: 150px"
                                    autoplay
                                    loop
                                    src="/animation/empty-data.json"
                                />
                            </div>
                            <p>Belum ada KPI untuk karyawan ini</p>
                        </div>
                    </div>

                    <!-- Submission Info -->
                    <div v-if="selectedMember.kpis && selectedMember.kpis.length > 0" class="submission-info">
                        <div class="kpi-summary">
                            <div class="summary-item">
                                <strong>Total KPI:</strong>
                                <span>{{ selectedMember.kpis.length }}</span>
                            </div>
                            <div class="summary-item">
                                <strong>Total Bobot:</strong>
                                <span>{{ calculateTotalWeight(selectedMember.kpis) }}%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </Modal>

        <!-- Modal Target untuk Bulk Creation -->
        <Modal
            v-if="showModalTarget"
            :show="showModalTarget"
            title="Target Breakdown"
            size="large"
            @close="closeModalTarget"
        >
            <template #body>
                <div class="modal-target-content">
                    <!-- Skala Target Section -->
                    <div class="target-scale-section mb-4">
                        <label class="form-label-optimized mb-3">
                            <div class="label-icon-container">
                                <i class="d-flex justify-content-center align-items-center bi bi-tag-fill"></i>
                            </div>
                            <div class="label-text">
                                <span class="label-title">Skala Target</span>
                                <span class="label-subtitle">Pilih periode target yang diinginkan</span>
                            </div>
                        </label>
                        <v-select
                            :options="TargetDatasField"
                            :searchable="true"
                            :filterable="true"
                            :clearable="false"
                            label="label"
                            :disabled="!getActiveKpiForMember(activeMemberTab)?.unit"
                            placeholder="Pilih Skala Target..."
                            :reduce="(opt) => opt.value"
                            v-model="getActiveKpiForMember(activeMemberTab).target"
                            @update:modelValue="targetDecision"
                            class="target-scale-select"
                        />
                    </div>

                    <!-- Target Form Section menggunakan KpiForm -->
                    <div
                        v-if="choosedTarget && targetBreakdownDatas?.length > 0 && !loading.fillingTarget"
                        class="target-form-section"
                    >
                        <div class="section-header mb-3">
                            <div class="section-icon">
                                <i class="d-flex justify-content-center align-items-center bi bi-bullseye"></i>
                            </div>
                            <div class="section-text">
                                <h4 class="section-title">
                                    Detail Target
                                    {{ choosedTarget.Nama_Target }}
                                </h4>
                                <p class="section-subtitle">Isi nilai target untuk setiap periode</p>
                            </div>
                        </div>

                        <!-- Target Summary -->
                        <div class="target-summary mb-3" v-if="targetBreakdownDatas.length > 0">
                            <div class="summary-info">
                                <span class="summary-text">
                                    Total Field:
                                    {{ activeTargetFieldsCount }} | Terisi: {{ getFilledTargetCount() }} | Status:
                                    {{
                                        getFilledTargetCount() === activeTargetFieldsCount
                                            ? 'Lengkap'
                                            : 'Belum Lengkap'
                                    }}
                                </span>
                                <span class="total-value">
                                    Total:
                                    {{
                                        truncateText(
                                            convertValueUnit(
                                                calculateTargetTotal(),
                                                getTargetUnitFormat,
                                                getTargetUnitSymbol,
                                            ),
                                            45 || 0,
                                        )
                                    }}
                                </span>
                            </div>
                        </div>

                        <!-- Quick Action: Isi Cepat Periode -->
                        <div class="quick-fill-panel mb-4" v-if="targetBreakdownDatas.length > 1">
                            <div class="quick-fill-header">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="quick-fill-icon">
                                        <i class="bi bi-lightning-charge-fill"></i>
                                    </div>
                                    <div>
                                        <span class="quick-fill-title">Isi Cepat Periode</span>
                                        <span class="quick-fill-subtitle text-muted">
                                            Terapkan nilai seragam atau bagi rata ke {{ activeTargetFieldsCount }} periode aktif
                                        </span>
                                    </div>
                                </div>
                                <span v-if="getTargetUnitSymbol" class="badge-unit-pill">
                                    Satuan: <strong>{{ getTargetUnitSymbol }}</strong>
                                </span>
                            </div>

                            <div class="quick-fill-body">
                                <div class="quick-fill-controls">
                                    <div class="quick-fill-input-box">
                                        <i class="bi bi-pencil-fill input-icon"></i>
                                        <input
                                            type="text"
                                            inputmode="decimal"
                                            :value="quickTargetFormatted"
                                            @input="handleQuickTargetInput"
                                            class="quick-input"
                                            :placeholder="quickTargetPlaceholder"
                                            @keyup.enter="applyQuickValueToAllPeriods"
                                        />
                                        <span v-if="getTargetUnitSymbol" class="input-unit-tag">
                                            {{ getTargetUnitSymbol }}
                                        </span>
                                    </div>

                                    <div class="quick-fill-actions">
                                        <button
                                            type="button"
                                            class="btn-quick btn-quick-primary"
                                            @click="applyQuickValueToAllPeriods"
                                            :disabled="quickTargetValue === '' || quickTargetValue === null"
                                            title="Isi seluruh periode aktif dengan nilai ini"
                                        >
                                            <i class="bi bi-check2-all"></i>
                                            <span>Terapkan ke Semua</span>
                                        </button>

                                        <button
                                            type="button"
                                            class="btn-quick btn-quick-secondary"
                                            @click="distributeQuickValueEvenly"
                                            :disabled="quickTargetValue === '' || quickTargetValue === null || Number(quickTargetValue) <= 0"
                                            title="Bagi rata nilai ini ke jumlah periode aktif"
                                        >
                                            <i class="bi bi-distribute-horizontal"></i>
                                            <span>Bagi Rata ({{ activeTargetFieldsCount }}x)</span>
                                        </button>

                                        <button
                                            type="button"
                                            class="btn-quick btn-quick-ghost"
                                            @click="resetAllTargetPeriods"
                                            title="Kosongkan seluruh nilai target"
                                        >
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                            <span>Reset</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <KpiForm
                            :modelValue="activeKpi.targetSimpan"
                            :fields="targetModalFields"
                            :kpis="[activeKpi.targetSimpan]"
                            :activeFormIndex="0"
                            :availableUnits="activeKpi?.availableUnits || []"
                            :loading="loading"
                            :showValidation="false"
                            :validationErrorsComponents="[]"
                            :showHeader="false"
                            :showNavigation="false"
                            :showHeaderActions="false"
                            :showQuickActions="false"
                            :isEditFormComponent="false"
                            :statusTransaksi="submissionStatus"
                            @update:modelValue="updateTargetValues"
                        />
                    </div>

                    <!-- Empty State -->
                    <div
                        v-else-if="choosedTarget && targetBreakdownDatas?.length == 0 && !loading.fillingTarget"
                        class="empty-state d-flex justify-content-center align-items-center flex-column"
                    >
                        <div class="empty-icon mb-4">
                            <i class="d-flex justify-content-center align-items-center bi bi-inbox mb-3"></i>
                        </div>
                        <p class="empty-text">Tidak ada field target yang tersedia</p>
                    </div>

                    <!-- loading State -->
                    <div v-else-if="choosedTarget && loading.fillingTarget" class="loading-container">
                        <div class="loading-spinner">
                            <i class="bi bi-arrow-repeat d-flex justify-content-center align-items-center"></i>
                        </div>
                        <h4 class="loading-title">Memuat Data Target...</h4>
                        <p class="loading-description">Sedang mengambil data target</p>
                    </div>

                    <!-- Instruction State -->
                    <div v-else class="instruction-state">
                        <div class="instruction-icon">
                            <i class="mb-3 d-flex justify-content-center align-items-center bi bi-arrow-up-circle"></i>
                        </div>
                        <h5 class="instruction-title">Pilih Skala Target</h5>
                        <p class="instruction-text">
                            Silakan pilih skala target terlebih dahulu untuk menampilkan form input
                        </p>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="wrapper-button-footer w-100 gap-2">
                    <button class="btn-modern btn-secondary" @click="closeModalTarget">Batal</button>
                    <button class="btn-modern btn-primary" @click="submitTarget" :disabled="!isTargetFull(activeKpi)">
                        Simpan Target
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Modal Konfirmasi Reset -->
        <Modal
            v-if="showResetConfirmation"
            :show="showResetConfirmation"
            title="Konfirmasi Reset"
            size="small"
            @close="showResetConfirmation = false"
        >
            <template #body>
                <div class="confirmation-content">
                    <i
                        class="bi bi-exclamation-triangle text-warning fs-1 mb-4 d-flex justify-content-center align-items-center"
                    ></i>
                    <p>Apakah Anda yakin ingin mereset data bulk creation?</p>
                    <p class="text-muted small">Semua data yang belum disimpan akan hilang.</p>
                </div>
            </template>
            <template #footer>
                <div class="wrapper-button-footer w-100 gap-2">
                    <button class="btn-modern btn-secondary" @click="showResetConfirmation = false">Batal</button>
                    <button class="btn-modern btn-danger" @click="resetBulkCreation">Ya, Reset</button>
                </div>
            </template>
        </Modal>

        <!-- Modal Validasi -->
        <Modal
            v-if="showValidationModal"
            :show="showValidationModal"
            title="Validasi Form KPI"
            size="large"
            @close="showValidationModal = false"
        >
            <template #body>
                <div class="kpi-validation-content">
                    <div class="kpi-validation-header">
                        <div class="kpi-validation-icon">
                            <i class="bi bi-exclamation-triangle d-flex justify-content-center align-items-center"></i>
                        </div>
                        <p class="kpi-validation-title">Beberapa form KPI belum lengkap:</p>
                        <p class="kpi-validation-subtitle">Silakan lengkapi bagian berikut:</p>
                    </div>

                    <div class="kpi-validation-scroll-container">
                        <ul class="kpi-validation-list">
                            <li
                                v-for="error in validationResults.errors"
                                :key="error.memberId"
                                class="kpi-validation-item"
                            >
                                <div class="kpi-member-validation">
                                    <div class="kpi-member-header">
                                        <i
                                            :class="
                                                error.isTemplate ? 'bi bi-file-earmark-text' : 'bi bi-person-circle'
                                            "
                                            class="kpi-member-icon d-flex justify-content-center align-items-center"
                                        ></i>
                                        <h3 class="kpi-member-name">
                                            {{ error.memberName }}
                                        </h3>
                                    </div>

                                    <p
                                        v-if="error.memberSubtitle"
                                        class="kpi-validation-subtitle"
                                        style="margin: 8px 0 12px 0; font-size: 13px"
                                    >
                                        {{ error.memberSubtitle }}
                                    </p>

                                    <div
                                        v-if="
                                            error.isTemplate &&
                                            error.affectedMembers &&
                                            error.affectedMembers.length > 0
                                        "
                                        style="
                                            background: #f3f4f6;
                                            padding: 10px;
                                            border-radius: 6px;
                                            margin-bottom: 12px;
                                        "
                                    >
                                        <p style="margin: 0 0 8px 0; font-size: 13px; font-weight: 600; color: #4b5563">
                                            <i class="bi bi-people-fill"></i>
                                            Member yang terpengaruh:
                                        </p>
                                        <div style="display: flex; flex-wrap: wrap; gap: 6px">
                                            <span
                                                v-for="(memberName, idx) in error.affectedMembers"
                                                :key="idx"
                                                style="
                                                    display: inline-block;
                                                    padding: 4px 10px;
                                                    background: white;
                                                    border: 1px solid #d1d5db;
                                                    border-radius: 6px;
                                                    font-size: 12px;
                                                "
                                            >
                                                {{ memberName }}
                                            </span>
                                        </div>
                                    </div>

                                    <ul class="kpi-error-list">
                                        <li v-for="(msg, index) in error.messages" :key="index" class="kpi-error-item">
                                            <i
                                                class="bi bi-x-circle kpi-error-icon d-flex justify-content-center align-items-center"
                                            ></i>
                                            <span class="kpi-error-text">{{ msg }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="kpi-validation-summary">
                        <p class="kpi-summary-text">
                            Total
                            <strong>{{ validationResults.errors.length }}</strong>
                            {{ validationResults.errors.length > 1 ? 'item' : 'item' }}
                            memerlukan perbaikan
                        </p>
                        <p
                            v-if="validationResults.errors.some((err) => err.isTemplate)"
                            class="kpi-validation-subtitle"
                            style="
                                margin-top: 8px;
                                padding: 8px 12px;
                                background: #fef3c7;
                                border-left: 3px solid #f59e0b;
                                border-radius: 4px;
                            "
                        >
                            <i class="bi bi-info-circle"></i>
                            Perbaikan template akan berlaku untuk semua anggota yang menggunakannya
                        </p>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="kpi-modal-footer-actions">
                    <button class="kpi-btn kpi-btn-primary" @click="showValidationModal = false">
                        <i class="bi bi-check-lg d-flex justify-content-center align-items-center"></i>
                        Mengerti
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Modal Alert Kustom -->
        <Modal
            v-if="showCustomAlert"
            :show="showCustomAlert"
            :title="alertTitle"
            size="small"
            @close="closeCustomAlert"
        >
            <template #body>
                <div class="alert-content-custom d-flex justify-content-center align-items-center flex-column">
                    <i :class="alertIcon" class="alert-icon d-flex justify-content-center align-items-center mb-4"></i>
                    <p>{{ alertMessage }}</p>

                    <!--
                        Bulan mulai berbeda per anggota (jadwal perorangan menimpa
                        jadwal global). Target sebelum bulan mulai TIDAK disimpan,
                        jadi pembagiannya ditampilkan di sini sebelum leader menekan
                        Ya - bukan setelah datanya terlanjur terpotong diam-diam.
                    -->
                    <div v-if="startPeriodeRingkasan.length" class="start-periode-ringkasan w-100">
                        <div class="start-periode-judul">
                            <i class="bi bi-calendar-range me-1"></i>
                            Bulan mulai per anggota
                        </div>
                        <div
                            v-for="kelompok in startPeriodeRingkasan"
                            :key="kelompok.mulai"
                            class="start-periode-baris"
                        >
                            <span class="start-periode-bulan">{{ kelompok.label }}</span>
                            <span class="start-periode-jumlah">{{ kelompok.anggota.length }} orang</span>
                            <span class="start-periode-nama" :title="kelompok.anggota.join(', ')">
                                {{ kelompok.ringkas }}
                            </span>
                        </div>
                        <div v-if="adaPemangkasan" class="start-periode-catatan">
                            Target sebelum bulan mulai masing-masing tidak akan disimpan.
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <!-- <div class="modal-footer"> -->
                <div class="wrapper-button-footer w-100 gap-2">
                    <button class="btn-modern btn-secondary button-footer" @click="handleAlertCancel">Batal</button>
                    <button class="btn-modern button-footer" :class="alertConfirmClass" @click="handleAlertConfirm">
                        {{ alertConfirmText }}
                    </button>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import quickStat from '@/components/legacy/quickStat.vue';
import ApprovalTabs from '@/components/legacy/toggleSectionV2.vue';
import KpiForm from '../../../../components/form/form-dynamic.vue';
import Stepper from '../../../../components/ui/stepper.vue';
import Modal from '@/components/legacy/modalV2-noir.vue';
import SubmissionSidebar from '../../../../components/ui/SubmissionSidebar.vue';
import HcisEmptyState from '@/components/ui/HcisEmptyState.vue';
import HcisCard from '@/components/ui/HcisCard.vue';
import HcisAlert from '@/components/ui/HcisAlert.vue';
import HcisBadge from '@/components/ui/HcisBadge.vue';
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisAvatar from '@/components/ui/HcisAvatar.vue';
import HcisIcon from '@/components/ui/HcisIcon.vue';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';
import axios from 'axios';
import { DotLottieVue } from '@lottiefiles/dotlottie-vue';
import { ElMessage, ElNotification, ElMessageBox } from 'element-plus';
import { tanggalPanjang } from '@/utils/tanggal';

const formatUtils = {
    parseNumeric: (value) => {
        if (value === null || value === undefined || value === '') return '';
        let str = value.toString().replace('%', '').replace(/,/g, '.');
        const parts = str.split('.');
        if (parts.length > 2) {
            str = parts[0] + '.' + parts.slice(1).join('');
        }
        return str.replace(/[^\d.]/g, '');
    },
    currency: {
        format: (value, locale = 'id-ID') => {
            if (value === null || value === undefined || value === '') return '';
            const strValue = value.toString();
            if (strValue.endsWith('.') && strValue.length > 1) {
                return new Intl.NumberFormat(locale).format(parseFloat(strValue)) + ',';
            }
            const numericValue = parseFloat(strValue) || 0;
            if (isNaN(numericValue)) return '';
            const parts = strValue.split('.');
            let decimals = parts.length > 1 ? parts[1].length : 0;
            return new Intl.NumberFormat(locale, {
                minimumFractionDigits: decimals,
                maximumFractionDigits: 5,
            }).format(numericValue);
        },
        parse: (value) => {
            if (value === null || value === undefined || value === '') return '';
            let str = value.toString();
            str = str.replace(/\./g, '');
            str = str.replace(',', '.');
            return str.replace(/[^\d.]/g, '');
        },
    },
};

export default {
    name: 'TeamKpiManagement',
    components: {
        quickStat,
        ApprovalTabs,
        KpiForm,
        Stepper,
        DotLottieVue,
        ElMessage,
        ElNotification,
        Modal,
        SubmissionSidebar,
        vSelect,
        ElMessageBox,
        HcisEmptyState,
        HcisCard,
        HcisAlert,
        HcisBadge,
        HcisButton,
        HcisAvatar,
        HcisIcon,
    },
    props: {
        penjadwalan: { type: Object, default: () => ({}) },
        // Terisi HANYA ketika tak ada payung: daftar penjadwalan Form KPI yang
        // ada berikut sebab masing-masing tidak berlaku hari ini. Tanpa ini,
        // layar kosongnya menyangkal keberadaan jadwal yang admin baru saja buat.
        sebabTanpaPenjadwalan: { type: Array, default: () => [] },
        // SELALU terisi: status penjadwalan tiap anggota tim, terlepas dari ada
        // tidaknya payung hari ini. Penjadwalan perorangan pada dasarnya per
        // orang, jadi leader berhak melihat cakupan timnya tanpa menunggu ada
        // jadwal yang kebetulan terbuka.
        statusJadwalAnggota: { type: Array, default: () => [] },
    },
    data() {
        return {
            teamFilters: {
                search: '',
                division: '',
                position: '',
                status: '',
            },
            bulkFilters: {
                search: '',
                division: '',
                kpiStatus: '',
            },
            progressFilters: {
                search: '',
                division: '',
                position: '',
                status: 'all',
            },

            // Data untuk dropdown options
            filterOptions: {
                divisions: [],
                positions: [],
            },
            itemsPerPage: 10,
            pagination: {
                overview: {
                    currentPage: 1,
                    totalPages: 1,
                    startItem: 0,
                    endItem: 0,
                },
                bulk: {
                    currentPage: 1,
                    totalPages: 1,
                    startItem: 0,
                    endItem: 0,
                },
                progress: {
                    currentPage: 1,
                    totalPages: 1,
                    startItem: 0,
                    endItem: 0,
                },
            },

            currentDate: new Date(),
            useGlobalTemplate: true,
            templateKpis: [
                {
                    Id_Penjadwalan: null,
                    parameter: '',
                    bscCategory: '',
                    definition: '',
                    formula: '',
                    unit: '',
                    weight: null,
                    target: '',
                    targetSimpan: {},
                    availableUnits: [],
                },
            ],

            // TEAM MEMBERS DENGAN LOCKED STATE
            teamMembers: [],
            selectedMembers: [],
            activeMemberTab: null,

            loading: {
                schedule: true,
                team: false,
                bulk: false,
                fillingTarget: false,
                listData: false,
                units: false,
                formulas: false,
                categories: false,
            },
            schedules: [],
            activeSchedule: null,

            // Team Data
            teamFilters: {
                search: '',
            },
            selectAll: false,
            selectAllStep1: false,

            // Modal States
            showMemberDetailModal: false,
            selectedMember: null,
            showModalTarget: false,
            showResetConfirmation: false,
            showValidationModal: false,
            showCustomAlert: false,

            // Tabs
            activeTab: 'overview',
            mainTabs: [
                {
                    id: 'overview',
                    label: 'Team Overview',
                    icon: 'bi bi-people',
                    count: 0,
                },
                {
                    id: 'bulk',
                    label: 'Form KPI',
                    icon: 'bi bi-layers',
                    count: 0,
                },
                {
                    id: 'progress',
                    label: 'Submission Progress',
                    icon: 'bi bi-graph-up',
                    count: 0,
                },
            ],

            // Form KPI
            // Diisi kode karyawan saat leader membuka satu anggota lewat tombol
            // Revisi/Edit. Selama terisi, kiriman dikunci ke orang itu saja:
            // menyimpan pengajuan akan membatalkan pengajuan lama yang masih
            // hidup, jadi anggota lain yang ikut terbawa akan tertimpa template
            // milik orang ini. Backend menolaknya juga (tolakRevisiBercampur);
            // kunci di layar ada supaya leader tidak sampai menabraknya.
            revisiUntukKode: null,
            bulkCurrentStep: 1,
            bulkStepperSteps: [
                {
                    id: 1,
                    title: 'Pilih Tim',
                    description: 'Pilih tim yang akan diberikan KPI',
                },
                {
                    id: 2,
                    title: 'Pengisian KPI',
                    description: 'Pengisian Parameter KPI Tim',
                },
                {
                    id: 3,
                    title: 'Verifikasi',
                    description: 'Verifikasi data KPI Tim',
                },
            ],
            confirmationChecked: false,
            quickTargetValue: '',
            quickTargetFormatted: '',

            // Progress Tracking
            progressFilter: 'all',
            progressStats: {
                waiting_approval: 0,
                rejected: 0,
                approved: 0,
                resubmitted: 0,
                total: 0,
                without_kpi: 0,
            },

            // Form Data
            BscKategoriDatas: [],
            TargetDatas: [],
            TargetDatasField: [],
            FormulaDatas: [],
            formulas: [],
            categories: [],
            availableUnits: [],
            targetBreakdownDatas: [],
            showValidation: false,
            validationErrors: [],

            // Data untuk modal target
            submissionStatus: 'draft',

            // Ringkasan bulan mulai per anggota, dikelompokkan, untuk dialog konfirmasi.
            startPeriodeRingkasan: [],

            // Bulan mulai per NIK, dipakai untuk keterangan di kartu anggota.
            startPeriodeAnggota: {},

            // Custom Alert
            alertTitle: '',
            alertMessage: '',
            alertIcon: '',
            alertConfirmText: 'Ya',
            alertConfirmClass: 'btn-primary',
            alertConfirmCallback: null,
            alertCancelCallback: null,

            // Validation Results
            validationResults: {
                isValid: false,
                errors: [],
            },
        };
    },
    computed: {
        // Sedang memperbaiki KPI satu anggota tertentu — kiriman harus memuat
        // dia saja. Lihat revisiUntukKode di data().
        modeSatuAnggota() {
            return !!this.revisiUntukKode;
        },
        // Anggota yang pengajuannya ditolak approver dan menunggu leader
        // memperbaikinya. Hanya penolakan yang SEDANG berlaku — putaran
        // sebelumnya sudah dibatalkan saat revisi dikirim, jadi tidak ikut
        // terbaca di sini.
        anggotaPerluRevisi() {
            return this.teamMembers.filter((m) => m.submissionStatus === 'rejected');
        },
        // Ada anggota yang bulan mulainya lebih lambat dari yang paling awal,
        // artinya sebagian target yang diisi leader memang akan dipangkas.
        adaPemangkasan() {
            return this.startPeriodeRingkasan.length > 1;
        },
        // Bulan mulai anggota yang tabnya sedang dibuka, untuk keterangan di kartunya.
        bulanMulaiAnggotaAktif() {
            const kode = this.activeMemberTab?.employeeId;

            return kode ? this.startPeriodeAnggota[kode] || null : null;
        },
        uniqueDivisions() {
            return [...new Set(this.teamMembers.map((m) => m.division))].filter(Boolean);
        },
        uniquePositions() {
            return [...new Set(this.teamMembers.map((m) => m.position))].filter(Boolean);
        },
        paginatedTeamMembers() {
            const start = (this.pagination.overview.currentPage - 1) * this.itemsPerPage;
            const end = start + this.itemsPerPage;
            return this.filteredTeamMembers.slice(start, end);
        },

        // Pagination untuk Progress
        paginatedProgressMembers() {
            const start = (this.pagination.progress.currentPage - 1) * this.itemsPerPage;
            const end = start + this.itemsPerPage;
            return this.filteredProgressMembers.slice(start, end);
        },

        // Pagination untuk Available Team Members (Bulk Creation Step 1)
        paginatedAvailableMembers() {
            const start = (this.pagination.bulk.currentPage - 1) * this.itemsPerPage;
            const end = start + this.itemsPerPage;
            return this.availableTeamMembers.slice(start, end);
        },

        // Visible Pages untuk Pagination
        visiblePages() {
            const current = this.getCurrentPagination().currentPage;
            const total = this.getCurrentPagination().totalPages;
            const range = 2; // Jumlah halaman yang ditampilkan di kiri dan kanan
            const pages = [];

            for (let i = Math.max(1, current - range); i <= Math.min(total, current + range); i++) {
                pages.push(i);
            }

            // Tambahkan ellipsis jika perlu
            if (pages[0] > 1) {
                pages.unshift(1);
                if (pages[1] > 2) {
                    pages.splice(1, 0, '...');
                }
            }

            if (pages[pages.length - 1] < total) {
                if (pages[pages.length - 1] < total - 1) {
                    pages.push('...');
                }
                pages.push(total);
            }

            return pages;
        },
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? 'Siang' : 'Malam';
        },
        // Data member yang sedang aktif
        activeMemberData() {
            return this.activeMemberTab;
        },

        // Container KPI yang aktif (template atau member specific)
        activeKpiContainer() {
            if (!this.activeMemberTab) {
                return this.templateKpis;
            }

            // Jika member menggunakan custom KPI dan memiliki KPI
            if (this.activeMemberTab.useCustomKpi && this.activeMemberTab.kpis) {
                return this.activeMemberTab.kpis;
            }

            // Gunakan template global
            return this.templateKpis;
        },

        /**
         * Apakah yang sedang disunting itu TEMPLATE bersama, bukan KPI custom
         * milik satu anggota.
         *
         * Aturannya sengaja mencerminkan activeKpiContainer di atas: tab anggota
         * boleh terbuka sementara yang disunting tetap template bersama, dan
         * itulah kasus yang dulu salah. Kalau kedua tempat ini berselisih, sel
         * yang mati tidak lagi cocok dengan nilai yang benar-benar disimpan.
         */
        sedangMenyuntingTemplate() {
            if (!this.activeMemberTab) return true;

            return !(this.activeMemberTab.useCustomKpi && this.activeMemberTab.kpis);
        },

        /**
         * Sidik jari cakupan permintaan breakdown: mode (template/custom) plus
         * daftar anggota yang menentukannya. Dipantau sebagai satu nilai supaya
         * satu pemantau cukup, dan supaya perubahan yang tidak mengubah cakupan
         * tidak memicu permintaan sia-sia.
         */
        cakupanBreakdown() {
            return (this.sedangMenyuntingTemplate ? 'template:' : 'custom:')
                + this.anggotaBreakdown.slice().sort().join(',');
        },

        /**
         * Daftar NIK yang dikirim ke endpoint breakdown.
         *
         * Dipakai BERSAMA oleh permintaannya dan oleh sidik jari yang dipantau.
         * Kalau keduanya menghitung sendiri-sendiri, pemantau bisa diam padahal
         * cakupan yang dikirim berubah — modal lalu memakai batas bulan milik
         * keadaan sebelumnya, tanpa galat apa pun.
         */
        anggotaBreakdown() {
            if (!this.sedangMenyuntingTemplate) {
                return [this.activeMemberTab?.employeeId].filter(Boolean);
            }

            const tercentang = this.getSelectedMembersObjects()
                .map((m) => m.employeeId)
                .filter(Boolean);

            // Belum ada yang dicentang: pakai anggota tab supaya modal tidak
            // kosong sama sekali.
            return tercentang.length ? tercentang : [this.activeMemberTab?.employeeId].filter(Boolean);
        },

        activeKpi() {
            const container = this.activeKpiContainer;
            const activeIndex = this.activeMemberTab?.activeKpiIndex || 0;

            if (Array.isArray(container) && container.length > 0 && container[activeIndex]) {
                return container[activeIndex];
            }

            // Return template pertama
            return this.templateKpis[0] || {};
        },

        choosedTarget() {
            const activeKpi = this.activeKpi;
            if (!activeKpi || !activeKpi.target) return null;
            const selected = this.TargetDatas.find((t) => t.Id_Master_Target === activeKpi.target);
            return selected || null;
        },

        quickStatField() {
            return [
                {
                    title: 'Total Anggota',
                    value: this.teamMembers.length,
                    unit: '',
                    icon: 'bi bi-people',
                    bgIcon: '',
                    size: 'col-4',
                },
                {
                    title: 'Tanpa KPI',
                    value: this.membersWithoutKPI.length,
                    unit: '',
                    icon: 'bi bi-file-earmark',
                    bgIcon: '',
                    size: 'col-4',
                },
                {
                    title: 'Disetujui',
                    value: this.progressStats.approved,
                    unit: '',
                    icon: 'bi bi-check-circle',
                    bgIcon: '',
                    size: 'col-4',
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

                const matchesDivision = !this.teamFilters.division || member.division === this.teamFilters.division;

                const matchesPosition = !this.teamFilters.position || member.position === this.teamFilters.position;

                const matchesStatus =
                    !this.teamFilters.status || this.getStatusForFilter(member) === this.teamFilters.status;

                return matchesSearch && matchesDivision && matchesPosition && matchesStatus;
            });
        },

        filteredProgressMembers() {
            const hasKpiMembers = this.teamMembers.filter(
                (m) => m.kpis && m.kpis.length > 0 && m.submissionStatus !== 'draft',
            );

            return hasKpiMembers
                .filter((member) => {
                    const matchesSearch =
                        !this.progressFilters.search ||
                        member.name.toLowerCase().includes(this.progressFilters.search.toLowerCase()) ||
                        member.employeeId.toLowerCase().includes(this.progressFilters.search.toLowerCase());

                    const matchesDivision =
                        !this.progressFilters.division || member.division === this.progressFilters.division;

                    const matchesPosition =
                        !this.progressFilters.position || member.position === this.progressFilters.position;

                    const matchesStatus =
                        this.progressFilters.status === 'all' ||
                        member.submissionStatus === this.progressFilters.status;

                    return matchesSearch && matchesDivision && matchesPosition && matchesStatus;
                })
                .sort((a, b) => new Date(b.lastUpdate) - new Date(a.lastUpdate));
        },

        availableTeamMembers() {
            return this.teamMembers.filter((member) => {
                const matchesSearch =
                    !this.bulkFilters.search ||
                    member.name.toLowerCase().includes(this.bulkFilters.search.toLowerCase()) ||
                    member.employeeId.toLowerCase().includes(this.bulkFilters.search.toLowerCase());

                const matchesDivision = !this.bulkFilters.division || member.division === this.bulkFilters.division;

                const matchesKpiStatus =
                    !this.bulkFilters.kpiStatus ||
                    (this.bulkFilters.kpiStatus === 'without' && !member.hasSubmittedKPI && member.is_eligible !== false) ||
                    (this.bulkFilters.kpiStatus === 'with' && member.hasSubmittedKPI) ||
                    (this.bulkFilters.kpiStatus === 'not_eligible' && member.is_eligible === false);

                return matchesSearch && matchesDivision && matchesKpiStatus;
            });
        },

        bulkKpiFields() {
            return [
                {
                    name: 'parameter',
                    label: 'Parameter KPI',
                    type: 'text',
                    placeholder: 'Masukkan parameter KPI',
                    required: true,
                    rowIndex: 0,
                    size: 'col-12 col-md-6',
                    enabled: true,
                    icon: 'bi bi-tag',
                },
                {
                    name: 'bscCategory',
                    label: 'BSC Category',
                    type: 'select',
                    placeholder: 'Pilih kategori',
                    required: true,
                    enabled: true,
                    rowIndex: 0,
                    size: 'col-12 col-md-6',
                    icon: 'bi bi-grid-3x3',
                    loadingKey: 'categories',
                },
                {
                    name: 'definition',
                    label: 'Definition',
                    type: 'textarea',
                    placeholder: 'Detail deskripsi dari poin KPI...',
                    required: true,
                    rowIndex: 1,
                    size: 'col-12',
                    enabled: true,
                    icon: 'bi bi-card-text',
                    rowClass: 'full-width',
                },
                {
                    name: 'formula',
                    label: 'Formula',
                    type: 'select',
                    placeholder: 'Pilih Formula',
                    icon: 'bi bi-calculator',
                    size: 'col-12 col-md-6',
                    rowIndex: 2,
                    enabled: true,
                    loading: true,
                    loadingKey: 'formulas',
                    onChange: (value) => this.onFormulaChangeForMember(this.activeMemberTab, value),
                },
                {
                    name: 'unit',
                    label: 'Unit of Measure',
                    type: 'select',
                    placeholder: 'Pilih Unit',
                    icon: 'bi bi-rulers',
                    size: 'col-12 col-md-6',
                    rowIndex: 2,
                    enabled: true,
                    loading: true,
                    loadingKey: 'units',
                },
                {
                    name: 'weight',
                    type: 'persen',
                    label: 'Weight',
                    icon: 'bi bi-speedometer2',
                    size: 'col-12 col-md-6',
                    rowIndex: 3,
                    enabled: true,
                    required: true,
                    min: 0,
                    max: 100,
                    format: 'percentage',
                },
                {
                    name: 'target',
                    type: 'popup',
                    label: 'Target',
                    placeholder: this.getTargetStatusForForm(),
                    icon: 'bi bi-bullseye',
                    size: 'col-12 col-md-6',
                    loading: false,
                    loadingKey: 'units',
                    rowIndex: 3,
                    enabled: true,
                    options: this.TargetDatasField || [],
                },
            ];
        },

        targetModalFields() {
            if (!this.choosedTarget) {
                this.loading.fillingTarget = false;
                return [];
            }

            const getTargetFormat = () => {
                const activeKpi = this.activeKpi;
                if (!activeKpi?.unit || !activeKpi?.availableUnits) return null;

                const currentUnit = activeKpi.availableUnits.find((unit) => unit.id === activeKpi.unit);

                if (!currentUnit) return null;
                const unitName = currentUnit.Format_Input;

                if (unitName) {
                    if (unitName === 'percentage') {
                        return { type: 'percentage', params: [0] };
                    } else {
                        return {
                            type: unitName,
                            params: [],
                        };
                    }
                } else {
                    return {
                        type: 'number',
                        params: [0],
                    };
                }
            };

            const getTargetUnitSymbol = () => {
                const activeKpi = this.activeKpi;
                if (!activeKpi?.unit || !activeKpi?.availableUnits) return null;

                const currentUnit = activeKpi.availableUnits.find((unit) => unit.id === activeKpi.unit);

                if (!currentUnit) return null;
                return currentUnit.name;
            };

            const targetFormat = getTargetFormat();
            let unitSymbol = getTargetUnitSymbol();
            let totalData = this.targetBreakdownDatas?.length || 0;

            let colSize = 'col-lg-12';

            if (totalData > 1) {
                colSize = 'col-lg-6';
            }

            let stackTargetBreakdownDatas = this.targetBreakdownDatas.map((item) => {
                // Periode sebelum start (isActive === false) → tetap tampil tapi disabled & tidak wajib.
                // enabled dibiarkan true agar field tidak ter-hide (wrapper pakai v-show field.enabled !== false),
                // lalu disabledField menonaktifkan input-nya (placeholder otomatis "Periode Tidak Aktif").
                const isActive = item.isActive !== false;
                return {
                    name: item.Id_Target_Breakdown,
                    label: `${item.Nama_Breakdown}`,
                    type: 'number',
                    placeholder: `Masukkan nilai ${item.Nama_Breakdown.toLowerCase()}...`,
                    icon: 'bi bi-calendar',
                    size: colSize,
                    enabled: true,
                    disabledField: !isActive,
                    format: targetFormat,
                    unitSymbol: unitSymbol,
                    required: isActive,
                    id: item.Id_Target_Breakdown,
                    urutan: item.Urutan,
                    periode: item.Periode,
                    isActive,
                };
            });

            this.loading.fillingTarget = false;
            return stackTargetBreakdownDatas;
        },

        // Jumlah field target yang WAJIB diisi (periode aktif saja, pra-start dikecualikan).
        activeTargetFieldsCount() {
            return this.targetModalFields.filter((f) => f.required !== false).length;
        },

        hasMembersWithoutKPI() {
            return this.membersWithoutKPI.length > 0;
        },

        membersWithoutKPI() {
            return this.teamMembers.filter(
                (member) => member.is_eligible !== false && !member.hasSubmittedKPI && this.hasApprover(member),
            );
        },

        membersWithoutKPISelected() {
            return this.selectedMembers.some((memberId) => {
                const member = this.getMemberById(memberId);
                return member && member.is_eligible !== false && !member.hasSubmittedKPI;
            });
        },

        isStep2Valid() {
            return this.selectedMembers.every((memberId) => {
                const member = this.getMemberById(memberId);
                const kpis = this.getMemberKpis(member);
                return (
                    kpis.length > 0 &&
                    kpis.every((kpi) => this.isFormCompleted(kpi)) &&
                    this.calculateMemberTotalWeight(member) === 100
                );
            });
        },

        isStep3Valid() {
            return this.selectedMembers.every((memberId) => {
                const member = this.getMemberById(memberId);
                const kpis = this.getMemberKpis(member);
                return (kpis?.length || 0) > 0 && this.calculateMemberTotalWeight(member) === 100;
            });
        },

        totalKpisAcrossSelected() {
            return this.selectedMembers.reduce((total, memberId) => {
                const member = this.getMemberById(memberId);
                return total + (this.getMemberKpis(member)?.length || 0);
            }, 0);
        },

        invalidMembersCount() {
            return this.selectedMembers.filter((memberId) => {
                const member = this.getMemberById(memberId);
                const kpis = this.getMemberKpis(member);
                return !kpis || kpis.length === 0 || this.calculateMemberTotalWeight(member) !== 100;
            }).length;
        },

        hasBulkData() {
            return this.selectedMembers.length > 0;
        },
        getTargetUnitSymbol() {
            const activeKpi = this.activeKpi;
            if (!activeKpi?.unit || !activeKpi?.availableUnits) return null;

            const currentUnit = activeKpi.availableUnits.find((unit) => unit.id === activeKpi.unit);

            if (!currentUnit) return null;
            return currentUnit.name;
        },
        getTargetUnitFormat() {
            const activeKpi = this.activeKpi;
            if (!activeKpi?.unit || !activeKpi?.availableUnits) return null;

            const currentUnit = activeKpi.availableUnits.find((unit) => unit.id === activeKpi.unit);

            if (!currentUnit) return null;
            return currentUnit.Format_Input;
        },
        quickTargetPlaceholder() {
            const format = this.getTargetUnitFormat;
            if (format === 'currency') return 'Contoh: 10.000.000';
            if (format === 'percentage') return 'Contoh: 100';
            return 'Ketik nilai target...';
        },
    },
    methods: {
        refreshPage() {
            window.location.reload();
        },
        hasApprover(e) {
            return e?.approvers?.length > 0;
        },
        formatAchievement(value) {
            const num = Number(value);
            if (isNaN(num)) return '0';
            // cek apakah punya desimal
            return num % 1 === 0 ? num.toString() : num.toFixed(1);
        },
        getStatusForFilter(member) {
            if (member.is_eligible === false) return 'not_eligible';
            if (!member.hasSubmittedKPI) return 'draft';
            return member.submissionStatus;
        },
        resetFilters() {
            this.teamFilters = {
                search: '',
                division: '',
                position: '',
                status: '',
            };
        },

        resetBulkFilters() {
            this.bulkFilters = { search: '', division: '', kpiStatus: '' };
        },

        resetProgressFilters() {
            this.progressFilters = {
                search: '',
                division: '',
                position: '',
                status: 'all',
            };
        },
        getCurrentPagination() {
            switch (this.activeTab) {
                case 'overview':
                    return this.pagination.overview;
                case 'bulk':
                    return this.pagination.bulk;
                case 'progress':
                    return this.pagination.progress;
                default:
                    return this.pagination.overview;
            }
        },

        // Get data length berdasarkan active tab
        getCurrentDataLength() {
            switch (this.activeTab) {
                case 'overview':
                    return this.filteredTeamMembers.length;
                case 'bulk':
                    return this.availableTeamMembers.length;
                case 'progress':
                    return this.filteredProgressMembers.length;
                default:
                    return 0;
            }
        },

        // Update pagination info
        updatePagination() {
            const pagination = this.getCurrentPagination();
            const dataLength = this.getCurrentDataLength();

            pagination.totalPages = Math.ceil(dataLength / this.itemsPerPage);
            pagination.startItem = (pagination.currentPage - 1) * this.itemsPerPage + 1;
            pagination.endItem = Math.min(pagination.currentPage * this.itemsPerPage, dataLength);

            // Ensure current page is valid
            if (pagination.currentPage > pagination.totalPages && pagination.totalPages > 0) {
                pagination.currentPage = pagination.totalPages;
                this.updatePagination(); // Recursive call to update again
            }
        },

        // Navigation methods
        goToPage(page) {
            if (page === '...') return;

            const pagination = this.getCurrentPagination();
            pagination.currentPage = page;
            this.updatePagination();
        },

        previousPage() {
            const pagination = this.getCurrentPagination();
            if (pagination.currentPage > 1) {
                pagination.currentPage--;
                this.updatePagination();
            }
        },

        nextPage() {
            const pagination = this.getCurrentPagination();
            if (pagination.currentPage < pagination.totalPages) {
                pagination.currentPage++;
                this.updatePagination();
            }
        },
        isKpisChanged() {
            let status = false;

            for (let i = 0; i < this.templateKpis.length; i++) {
                const newKpi = this.kpis[i];
                const oldKpi = this.formStatus.kpis[i];

                for (let key in newKpi) {
                    if (key === 'availableUnits') continue;
                    if (key === 'Alasan_Tolak') continue;
                    if (key === 'Nama_Satuan') continue;
                    if (key === 'Id_Penjadwalan') continue;

                    // kalau targetSimpan object → cek stringify
                    if (key === 'targetSimpan') {
                        if (JSON.stringify(newKpi[key]) !== JSON.stringify(oldKpi[key])) {
                            status = true;
                        }
                    } else {
                        //console.log(
                        //   newKpi[key],
                        //  oldKpi[key],
                        //   "hasil " + key + (newKpi[key] !== oldKpi[key])
                        // );

                        if (newKpi[key] !== oldKpi[key]) {
                            status = true;
                        }
                    }
                }
            }
            return status;
        },
        convertValueUnit(value, format, simbol = null) {
            if (!format || !value) return '0';

            let simbolSatuan = simbol ? simbol?.trim() : '';
            // console.log(simbolSatuan);
            let decimals = 2;
            let result = '';

            if (format == 'currency') {
                result = new Intl.NumberFormat('id-ID').format(value);
            } else if (format == 'percentage') {
                const numericValue = parseFloat(value) || 0;
                const strValue = value.toString();

                const userInputDecimal = strValue.includes('.') || strValue.includes(',');

                if (!userInputDecimal && numericValue % 1 === 0) {
                    result = `${numericValue}`;
                }

                result = `${numericValue.toFixed(decimals)}`;
            } else {
                const numericValue = parseFloat(value) || 0;
                result = new Intl.NumberFormat('id-ID', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0,
                }).format(numericValue);
            }

            return format == 'currency' ? simbolSatuan + '. ' + result : result + ' ' + simbolSatuan;
        },
        formatDateString(dateString) {
            return tanggalPanjang(dateString);
        },
        formatStartPeriode(periode) {
            if (!periode) return '-';
            if (typeof periode === 'string' && periode.includes('-')) {
                const parts = periode.split('-');
                const tahun = parts[0];
                const bulanIndex = parseInt(parts[1], 10) - 1;
                const namaBulan = [
                    'Januari',
                    'Februari',
                    'Maret',
                    'April',
                    'Mei',
                    'Juni',
                    'Juli',
                    'Agustus',
                    'September',
                    'Oktober',
                    'November',
                    'Desember',
                ];
                if (bulanIndex >= 0 && bulanIndex < 12) {
                    return `${namaBulan[bulanIndex]} ${tahun}`;
                }
            }
            return periode;
        },
        /**
         * Bulan mulai yang berlaku untuk tiap anggota yang dicentang, dikelompokkan
         * supaya 80 anggota tetap terbaca sebagai beberapa baris.
         *
         * Aturannya diresolusi backend memakai fungsi yang sama dengan saat
         * menyimpan, jadi yang dilihat leader persis yang akan tersimpan.
         */
        async muatRingkasanStartPeriode() {
            this.startPeriodeRingkasan = [];
            this.startPeriodeAnggota = {};

            const kode = this.selectedMembers
                .map((id) => this.getMemberById(id))
                .filter(Boolean)
                .map((m) => m.employeeId)
                .filter(Boolean);

            if (!kode.length || !this.activeSchedule?.id) return;

            try {
                const response = await axios.post('/api/v1/form-kpi/team/start-periode', {
                    Id_Penjadwalan: this.activeSchedule.id,
                    kode_karyawan: kode,
                });

                if (!response.data?.success) return;

                const namaBulan = [
                    'Januari',
                    'Februari',
                    'Maret',
                    'April',
                    'Mei',
                    'Juni',
                    'Juli',
                    'Agustus',
                    'September',
                    'Oktober',
                    'November',
                    'Desember',
                ];

                const kelompok = {};
                const perAnggota = {};

                (response.data.result || []).forEach((baris) => {
                    if (!baris.mulai || !baris.tercakup || !baris.bulan) {
                        perAnggota[baris.kode_karyawan] = {
                            mulai: null,
                            label: 'Tidak Tercakup',
                            sumber: baris.sumber || 'tidak_tercakup',
                        };
                        return;
                    }

                    const label = `${namaBulan[baris.bulan - 1]} ${baris.mulai.slice(0, 4)}`;

                    perAnggota[baris.kode_karyawan] = {
                        mulai: baris.mulai,
                        label,
                        sumber: baris.sumber,
                    };

                    if (!kelompok[baris.mulai]) {
                        kelompok[baris.mulai] = {
                            mulai: baris.mulai,
                            label,
                            anggota: [],
                        };
                    }
                    kelompok[baris.mulai].anggota.push(baris.nama_karyawan);
                });

                this.startPeriodeAnggota = perAnggota;

                this.startPeriodeRingkasan = Object.values(kelompok)
                    .sort((a, b) => (a.mulai || '').localeCompare(b.mulai || ''))
                    .map((k) => ({
                        ...k,
                        ringkas:
                            k.anggota.length > 3
                                ? `${k.anggota.slice(0, 3).join(', ')} +${k.anggota.length - 3} lainnya`
                                : k.anggota.join(', '),
                    }));
            } catch (error) {
                // Ringkasan hanya informatif. Kegagalan memuatnya tidak boleh
                // menghalangi pengiriman - backend tetap memangkas dgn aturan sama.
                console.error('Gagal memuat ringkasan bulan mulai:', error);
            }
        },
        showAlert(message, type = 'warning', title = null) {
            // Ringkasan hanya milik dialog konfirmasi pengiriman; jangan sampai
            // ikut muncul di peringatan lain yang memakai kotak yang sama.
            this.startPeriodeRingkasan = [];
            this.alertTitle = title || this.getAlertTitle(type);
            this.alertMessage = message;
            this.alertIcon = this.getAlertIcon(type);
            this.alertConfirmText = 'OK';
            this.alertConfirmClass = 'btn-primary';
            this.alertConfirmCallback = null;
            this.alertCancelCallback = null;
            this.showCustomAlert = true;
        },

        showConfirm(message, title = 'Konfirmasi', confirmCallback, cancelCallback = null) {
            this.alertTitle = title;
            this.alertMessage = message;
            this.alertIcon = 'bi bi-question-circle text-primary';
            this.alertConfirmText = 'Ya';
            this.alertConfirmClass = 'btn-primary';
            this.alertConfirmCallback = confirmCallback;
            this.alertCancelCallback = cancelCallback;
            this.showCustomAlert = true;
        },

        getAlertTitle(type) {
            const titles = {
                warning: 'Peringatan',
                error: 'Kesalahan',
                success: 'Berhasil',
                info: 'Informasi',
            };
            return titles[type] || 'Pemberitahuan';
        },

        getAlertIcon(type) {
            const icons = {
                warning: 'bi bi-exclamation-triangle text-warning',
                error: 'bi bi-x-circle text-danger',
                success: 'bi bi-check-circle text-success',
                info: 'bi bi-info-circle text-info',
            };
            return icons[type] || 'bi bi-info-circle text-info';
        },

        closeCustomAlert() {
            this.showCustomAlert = false;
            if (this.alertCancelCallback) {
                this.alertCancelCallback();
            }
        },

        handleAlertConfirm() {
            this.showCustomAlert = false;
            if (this.alertConfirmCallback) {
                this.alertConfirmCallback();
            }
        },

        handleAlertCancel() {
            this.closeCustomAlert();
        },

        // Target Methods
        getTargetStatusForForm() {
            if (!this.activeKpi.target) {
                return 'Pilih Target';
            }

            let total = 0;
            let filled = 0;

            if (Array.isArray(this.activeKpi.targetSimpan)) {
                total = this.activeKpi.targetSimpan.length;
                filled = this.activeKpi.targetSimpan.filter(
                    (item) => item.Nilai_Target !== null && item.Nilai_Target !== '',
                ).length;
            } else if (typeof this.activeKpi.targetSimpan === 'object' && this.activeKpi.targetSimpan !== null) {
                // Field pra-start bernilai null → tidak ikut total maupun filled.
                const values = Object.values(this.activeKpi.targetSimpan).filter((val) => val !== null);
                total = values.length;
                filled = values.filter((val) => val !== '').length;
            }

            const isFull = filled === total && total > 0;

            return `${this.choosedTarget?.Nama_Target || 'Target'} - ${filled}/${total} Terisi ${isFull ? '✔️' : '❌'}`;
        },

        getFilledTargetCount() {
            if (!this.activeKpi.targetSimpan) return 0;

            if (Array.isArray(this.activeKpi.targetSimpan)) {
                return this.activeKpi.targetSimpan.filter(
                    (item) => item.Nilai_Target !== null && item.Nilai_Target !== '',
                ).length;
            } else if (typeof this.activeKpi.targetSimpan === 'object') {
                return Object.values(this.activeKpi.targetSimpan).filter((val) => val !== null && val !== '').length;
            }
            return 0;
        },

        calculateTargetTotal() {
            if (!this.activeKpi.targetSimpan) return 0;

            let total = 0;
            if (Array.isArray(this.activeKpi.targetSimpan)) {
                total = this.activeKpi.targetSimpan.reduce((sum, item) => {
                    const value = parseFloat(item.Nilai_Target) || 0;
                    return sum + value;
                }, 0);
            } else if (typeof this.activeKpi.targetSimpan === 'object') {
                total = Object.values(this.activeKpi.targetSimpan).reduce((sum, val) => {
                    const value = parseFloat(val) || 0;
                    return sum + value;
                }, 0);
            }
            return total;
        },

        // Revision Methods
        hasRevisionKPI(member, kpi = null) {
            if (kpi) {
                return kpi.Alasan_Tolak && kpi.Alasan_Tolak.trim() !== '';
            }

            const kpis = this.getMemberKpis(member);
            return kpis.some((kpi) => kpi.Alasan_Tolak && kpi.Alasan_Tolak.trim() !== '');
        },

        isRevisionKPI(member, kpi) {
            return this.hasRevisionKPI(member, kpi);
        },

        // Satu-satunya penentu "ditolak dan menunggu revisi", dipakai baris
        // tabel, kartu mobile, tombol Revisi, dan banner alasan umum — supaya
        // keempatnya tidak bisa saling bertentangan.
        perluRevisi(member) {
            return member?.submissionStatus === 'rejected';
        },

        // Berapa parameter anggota ini yang diberi catatan approver. Dipakai
        // banner revisi untuk memberi tahu leader bahwa selain alasan umum, ada
        // catatan spesifik menunggu di kartu parameter.
        jumlahParameterBerkomentar(member) {
            return this.getMemberKpis(member).filter(
                (kpi) => kpi.Alasan_Tolak && kpi.Alasan_Tolak.trim() !== '',
            ).length;
        },

        getOldDataForMember(member) {
            return member.originalKpis || [];
        },

        isEditModeForMember(member) {
            return member.hasSubmittedKPI && this.canEditKpi(member);
        },

        getSubmissionStatusForMember(member) {
            return member.submissionStatus || 'draft';
        },

        getMaxAllowedWeightForMember(member) {
            const kpis = this.getMemberKpis(member);
            const totalWeightExcludingActive = kpis.reduce((total, kpi, index) => {
                if (index !== (member.activeKpiIndex || 0)) {
                    const weight = parseFloat(kpi.weight) || 0;
                    return total + weight;
                }
                return total;
            }, 0);

            const max = 100 - totalWeightExcludingActive;
            return Math.max(0, max);
        },

        handleWeightExceededForMember(exceededWeight) {
            const maxAllowed = this.getMaxAllowedWeightForMember(this.activeMemberTab);
            this.showAlert(`Weight melebihi batas.  Akan disesuaikan menjadi ${maxAllowed}%`, 'warning');

            this.updateMemberKpiData(this.activeMemberTab, {
                ...this.activeKpi,
                weight: maxAllowed.toString(),
            });
        },

        // Validation Methods
        validateAllMembers() {
            this.validationResults = {
                isValid: true,
                errors: [],
            };

            const membersUsingTemplate = this.selectedMembers.filter((memberId) => {
                const member = this.getMemberById(memberId);
                return member && !member.useCustomKpi;
            });

            const membersWithCustomKpi = this.selectedMembers.filter((memberId) => {
                const member = this.getMemberById(memberId);
                return member && member.useCustomKpi;
            });

            if (membersUsingTemplate.length > 0) {
                console.log('Memvalidasi Template KPI...');
                const templateErrors = this.validateTemplateKpis();

                if (templateErrors.length > 0) {
                    this.validationResults.isValid = false;

                    const affectedMemberNames = membersUsingTemplate
                        .map((id) => {
                            const m = this.getMemberById(id);
                            return m?.name;
                        })
                        .filter(Boolean);

                    this.validationResults.errors.push({
                        memberId: 'template',
                        memberName: 'TEMPLATE KPI GLOBAL',
                        memberSubtitle: `⚠️ Template ini digunakan oleh ${membersUsingTemplate.length} anggota tim`,
                        messages: templateErrors,
                        isTemplate: true,
                        affectedCount: membersUsingTemplate.length,
                        affectedMembers: affectedMemberNames,
                    });
                } else {
                    ElMessage.success('Template Form Sudah Valid!');
                }
            }

            // Validasi Custom KPI per Member
            if (membersWithCustomKpi.length > 0) {
                console.log('Memvalidasi Custom KPI individual...');

                membersWithCustomKpi.forEach((memberId) => {
                    const member = this.getMemberById(memberId);
                    console.log(`Validasi ${member.name} (Custom KPI)...`);

                    const memberErrors = this.validateMemberKpisSilent(member);

                    if (memberErrors.length > 0) {
                        this.validationResults.isValid = false;
                        this.validationResults.errors.push({
                            memberId: memberId,
                            memberName: `${member.name}`,
                            memberSubtitle: `🔧 KPI Custom • ${member.position || 'Posisi tidak tersedia'}`,
                            messages: memberErrors,
                            isTemplate: false,
                            affectedCount: 1,
                            affectedMembers: [member.name],
                        });
                    } else {
                        ElMessage.success('Semua Custom KPI Sudah Valid!');
                    }
                });
            }

            // Tampilkan modal validasi jika ada error
            if (!this.validationResults.isValid) {
                this.showValidationModal = true;
                return false;
            }

            return true;
        },
        // Method baru untuk validasi template
        validateTemplateKpis() {
            const errors = [];
            const kpis = this.templateKpis;

            if (kpis.length === 0) {
                errors.push('❌ Belum ada KPI yang dibuat di template');
                return errors;
            }

            kpis.forEach((kpi, index) => {
                const kpiLabel = `KPI ${index + 1}`;

                if (!kpi.parameter || !kpi.parameter.trim()) {
                    errors.push(`${kpiLabel}: Parameter KPI harus diisi`);
                }
                if (!kpi.bscCategory) {
                    errors.push(`${kpiLabel}: BSC Category harus dipilih`);
                }
                if (!kpi.definition || !kpi.definition.trim()) {
                    errors.push(`${kpiLabel}: Definition harus diisi`);
                }
                if (!kpi.weight || kpi.weight < 1 || kpi.weight > 100) {
                    errors.push(`${kpiLabel}: Weight harus antara 1-100%`);
                }
                if (!kpi.unit) {
                    errors.push(`${kpiLabel}: Unit/Satuan harus dipilih`);
                }
                if (!kpi.formula) {
                    errors.push(`${kpiLabel}: Formula harus dipilih`);
                }
                if (!kpi.target) {
                    errors.push(`${kpiLabel}: Target harus dipilih`);
                }
                if (kpi.target && !this.isTargetFull(kpi)) {
                    errors.push(`${kpiLabel}: Nilai target belum lengkap diisi`);
                }
            });

            // Validasi total weight untuk template
            const totalWeight = this.calculateTotalWeight(kpis);
            if (totalWeight !== 100) {
                errors.push(`Total weight harus 100% (saat ini: ${totalWeight}%)`);
            }

            return errors;
        },

        validateMemberKpisSilent(member) {
            const errors = [];
            const kpis = this.getMemberKpis(member);

            if (kpis.length === 0) {
                errors.push('Belum ada KPI yang dibuat');
                return errors;
            }

            kpis.forEach((kpi, index) => {
                if (!kpi.parameter.trim()) {
                    errors.push(`KPI ${index + 1}: Nama parameter dibutuhkan`);
                }
                if (!kpi.bscCategory) {
                    errors.push(`KPI ${index + 1}: BSC Category dibutuhkan`);
                }
                if (!kpi.definition.trim()) {
                    errors.push(`KPI ${index + 1}: Definition dibutuhkan`);
                }
                if (kpi.weight < 1 || kpi.weight > 100) {
                    errors.push(`KPI ${index + 1}: Weight harus dari 1 sampai 100`);
                }
                if (!kpi.unit) {
                    errors.push(`KPI ${index + 1}: Satuan dibutuhkan`);
                }
                if (!kpi.formula) {
                    errors.push(`KPI ${index + 1}: Formula dibutuhkan`);
                }
                if (!kpi.target) {
                    errors.push(`KPI ${index + 1}: Target dibutuhkan`);
                }
                if (!this.isTargetFull(kpi)) {
                    errors.push(`KPI ${index + 1}: Target belum terpenuhi`);
                }
            });

            const totalWeight = this.calculateMemberTotalWeight(member);
            if (totalWeight !== 100) {
                errors.push(`Total weight harus 100% (saat ini: ${totalWeight}%)`);
            }

            return errors;
        },

        // Override methods untuk menggunakan custom alert
        validateMemberKpis(member) {
            const errors = this.validateMemberKpisSilent(member);

            if (errors.length > 0) {
                this.showAlert(`Validasi gagal untuk ${member.name}: ` + errors.join(', '), 'error');
                return false;
            } else {
                this.showAlert('Seluruh form valid!', 'success');
                return true;
            }
        },

        // Override navigation methods untuk konfirmasi
        navigateToBulkCreation() {
            if (this.hasBulkData && this.activeTab !== 'bulk') {
                this.showConfirm('Data form KPI yang belum disimpan akan hilang. Lanjutkan?', 'Konfirmasi', () => {
                    this.resetBulkCreation();
                });
                return;
            }
            this.activeTab = 'bulk';
            this.bulkCurrentStep = 1;
            this.selectedMembers = [];
            this.activeMemberTab = null;
        },

        editMemberKpis(member) {
            if (this.hasBulkData && this.activeTab !== 'bulk') {
                this.showConfirm('Data form KPI yang belum disimpan akan hilang. Lanjutkan?', 'Konfirmasi', () => {
                    this.prepareEditMember(member);
                });
            } else {
                this.prepareEditMember(member);
            }
        },

        handleTabChange(tabId) {
            if (tabId === 'overview') this.resetFilters();
            else if (tabId === 'bulk') this.resetBulkFilters();
            else if (tabId === 'progress') this.resetProgressFilters();

            const previousTab = this.activeTab;
            if (this.hasBulkData && this.activeTab === 'bulk' && tabId !== 'bulk') {
                this.showConfirm(
                    'Data form KPI yang belum disimpan akan hilang. Lanjutkan?',
                    'Konfirmasi',
                    () => {
                        // User konfirmasi - pindah ke tab baru dan reset data
                        this.activeTab = tabId;
                        this.resetBulkCreation();
                    },
                    () => {
                        this.$nextTick(() => {
                            this.activeTab = previousTab;
                        });
                    },
                );

                // Jangan langsung ubah activeTab, tunggu konfirmasi
                return;
            }

            this.pagination[tabId].currentPage = 1;
            this.activeTab = tabId;
            this.updatePagination();
        },

        // Override target decision untuk custom confirm
        async targetDecision() {
            if (this.confirmChangeTarget()) {
                this.showConfirm('Data target sebelumnya akan hilang. Yakin ingin mengganti?', 'Konfirmasi', () => {
                    this.activeKpi.targetSimpan = {};
                    this.getTargetBreakdown();
                });
            } else {
                this.activeKpi.targetSimpan = {};
                await this.getTargetBreakdown();
            }
        },

        // Existing methods tetap dipertahankan dengan penyesuaian minor
        getSelectedMembersObjects() {
            return this.selectedMembers
                .map((memberId) => this.teamMembers.find((m) => m.id === memberId))
                .filter(Boolean);
        },

        async initializeData() {
            // langsung set dari props, tanpa fetch
            this.activeSchedule = this.penjadwalan;
            await this.fetchTeamData();
            this.calculateProgressStats();
            await this.fetchListData();
        },

        async fetchSchedules() {
            this.loading.schedule = true;
            try {
                this.schedules = this.penjadwalan.map((e) => ({
                    ...e,
                    is_active: true,
                }));
            } catch (error) {
                console.error('Error fetching schedules:', error);
                this.showAlert('Gagal memuat data penjadwalan', 'error');
            } finally {
                this.loading.schedule = false;
            }
        },

        async fetchListData() {
            this.loading.listData = true;
            this.loading.formulas = true;
            this.loading.categories = true;

            try {
                // Kirim anggota yang dicentang supaya daftar target dinilai memakai
                // bulan mulai paling awal di antara mereka, bukan bulan mulai leader.
                const response = await axios.get('/api/v1/form-kpi/current', {
                    params: {
                        Id_Penjadwalan: this.activeSchedule?.id,
                        kode_karyawan: this.getSelectedMembersObjects()
                            .map((m) => m.employeeId)
                            .filter(Boolean),
                    },
                });

                if (response.status === 200 && response.data?.result) {
                    this.BscKategoriDatas = response.data.result.dataBSCKategori || [];
                    this.TargetDatas = response.data.result.dataTarget || [];
                    this.FormulaDatas = response.data.result.dataFormula || [];

                    this.categories = this.BscKategoriDatas.map((item) => ({
                        id: item.Id_Master_Bsc_Kategori,
                        name: item.Nama_Kategori,
                    }));

                    this.TargetDatasField = this.TargetDatas.map((item) => ({
                        value: item.Id_Master_Target,
                        label: item.Nama_Target,
                        kode: item.Kode_Target,
                    }));

                    this.formulas = this.FormulaDatas.map((item) => ({
                        id: item.Id_Master_Formula,
                        name: item.Rumus,
                    }));
                }
            } catch (error) {
                console.error('Failed to fetch list data:', error);
                this.categories = [];
                this.TargetDatasField = [];
                this.formulas = [];
            } finally {
                this.loading.listData = false;
                this.loading.formulas = false;
                this.loading.categories = false;
            }
        },

        // async selectSchedule(schedule) {
        //     this.activeSchedule = schedule;

        // },

        backToScheduleSelection() {
            this.activeSchedule = null;
            this.teamMembers = [];
            this.selectedMembers = [];
            this.activeMemberTab = null;
        },

        async fetchTeamData() {
            this.loading.team = true;
            try {
                const response = await axios.get(`/api/v1/form-kpi/team/current/${this.activeSchedule?.id}`);
                this.teamMembers = response.data.result;
                console.log(this.teamMembers);
                // this.teamMembers = [];

                // console.log(response);
                // this.teamMembers = [
                //     {
                //         id: 1,
                //         name: "Ahmad Rizki",
                //         position: "LABORATORY & QUALITY ASSURANCE",
                //         division: "FOOD TECHNOLOGY & NUTRITION",
                //         employeeId: "EMP001",
                //         kpis: [
                //             {
                //                 id: 1,
                //                 parameter: "Project Completion Rate",
                //                 category: "process",
                //                 weight: 30,
                //                 target: "95% completion",
                //                 description:
                //                     "Tingkat penyelesaian proyek tepat waktu",
                //                 Alasan_Tolak: "Target tidak realistis",
                //             },
                //         ],
                //         originalKpis: [
                //             {
                //                 id: 1,
                //                 parameter: "Project Completion Rate",
                //                 category: "process",
                //                 weight: 30,
                //                 target: "95% completion",
                //                 description:
                //                     "Tingkat penyelesaian proyek tepat waktu",
                //                 Alasan_Tolak: "Target tidak realistis",
                //             },
                //         ],
                //         lastUpdate: new Date().toISOString(),
                //         submissionStatus: "rejected",
                //         approvers: [
                //             {
                //                 name: "Manager IT",
                //                 status: "rejected",
                //                 level: 1,
                //                 reason: "Target tidak realistis",
                //             },
                //         ],
                //         hasSubmittedKPI: true,
                //         activeKpiIndex: 0,
                //         useCustomKpi: false,
                //     },
                //     {
                //         id: 2,
                //         name: "Sari Dewi",
                //         position: "ENGINEERING & MAINTENANCE",
                //         division: "PRODUCTION SUPPORT",
                //         employeeId: "EMP002",
                //         kpis: [
                //             {
                //                 id: 1,
                //                 parameter: "Design Satisfaction",
                //                 category: "customer",
                //                 Alasan_Tolak: "Tidak Realistis",
                //                 weight: 40,
                //                 target: "90% satisfaction",
                //                 description:
                //                     "Tingkat kepuasan user terhadap desain",
                //             },
                //         ],
                //         originalKpis: [
                //             {
                //                 id: 1,
                //                 parameter: "Design Satisfaction",
                //                 category: "customer",
                //                 Alasan_Tolak: "Tidak Realistis",
                //                 weight: 40,
                //                 target: "90% satisfaction",
                //                 description:
                //                     "Tingkat kepuasan user terhadap desain",
                //             },
                //         ],
                //         lastUpdate: new Date().toISOString(),
                //         submissionStatus: "rejected",
                //         approvers: [
                //             {
                //                 name: "Manager Design",
                //                 status: "rejected",
                //                 level: 1,
                //                 reason: "Target tidak realistis",
                //             },
                //         ],
                //         hasSubmittedKPI: true,
                //         activeKpiIndex: 0,
                //         useCustomKpi: false,
                //     },
                //     {
                //         id: 3,
                //         name: "Budi Santoso",
                //         position: "BATCHING PLAN",
                //         division: "PROJECT",
                //         employeeId: "EMP003",
                //         kpis: null,
                //         lastUpdate: new Date().toISOString(),
                //         submissionStatus: "draft",
                //         hasSubmittedKPI: false,
                //         activeKpiIndex: 0,
                //         useCustomKpi: false,
                //     },
                //     {
                //         id: 4,
                //         name: "DURIAN",
                //         position: "BATCHING PLAN",
                //         division: "PROJECT",
                //         employeeId: "EMP008",
                //         kpis: null,
                //         lastUpdate: new Date().toISOString(),
                //         submissionStatus: "draft",
                //         hasSubmittedKPI: false,
                //         activeKpiIndex: 0,
                //         useCustomKpi: false,
                //     },
                //     {
                //         id: 5,
                //         name: "Maya Sari",
                //         position: "GENERAL TRADE",
                //         division: "SALES & DISTRIBUTION",
                //         employeeId: "EMP004",
                //         kpis: [
                //             {
                //                 id: 1,
                //                 parameter: "Bug Detection Rate",
                //                 category: "process",
                //                 weight: 35,
                //                 target: "98% detection",
                //                 description:
                //                     "Tingkat deteksi bug sebelum production",
                //             },
                //         ],
                //         lastUpdate: new Date().toISOString(),
                //         submissionStatus: "approved",
                //         approvers: [
                //             {
                //                 name: "Manager IT",
                //                 status: "approved",
                //                 level: 1,
                //             },
                //             { name: "Director", status: "approved", level: 2 },
                //         ],
                //         hasSubmittedKPI: true,
                //         activeKpiIndex: 0,
                //         useCustomKpi: false,
                //     },
                // ];

                this.calculateProgressStats();
            } catch (error) {
                console.error('Error fetching team data:', error);
                ElMessage.error('Tidak ditemukan anggota tim');
            } finally {
                this.loading.team = false;
            }
        },

        // Utility Methods
        getInitials(name) {
            if (!name) return '??';
            return name
                .split(' ')
                .map((n) => n[0])
                .join('')
                .toUpperCase();
        },

        formatDate(dateString) {
            if (!dateString) return '-';
            return new Date(dateString).toLocaleDateString('id-ID');
        },

        // KPI Status Methods
        getKpiStatus(member) {
            if (member.is_eligible === false) {
                return { class: 'not-eligible', text: 'Tidak Terdaftar' };
            }

            if (!this.hasApprover(member) && (!member.kpis || member.kpis.length === 0)) {
                return { class: 'draft', text: '⚠️ Tidak Ada Approval' };
            }

            if (!member.kpis || member.kpis.length === 0) {
                return { class: 'draft', text: 'Belum Ada KPI' };
            }

            const statusMap = {
                draft: { class: 'draft', text: 'Draft' },
                waiting_approval: {
                    class: 'waiting',
                    text: 'Menunggu Approval',
                },
                rejected: { class: 'rejected', text: 'Ditolak' },
                approved: { class: 'approved', text: 'Disetujui' },
                resubmitted: { class: 'resubmitted', text: 'Mengajukan Ulang' },
            };

            return statusMap[member.submissionStatus] || statusMap.draft;
        },

        getApproverInfo(member) {
            if (!member.approvers || member.approvers.length === 0) return '-';

            // Approver yang sedang aktif: status 'inprogress' (sedang diproses) atau 'rejected'.
            const currentApprover = member.approvers.find(
                (approver) => approver.status === 'inprogress' || approver.status === 'rejected',
            );

            if (currentApprover) {
                return `${currentApprover.name} (${currentApprover.status === 'rejected' ? 'Ditolak' : 'Menunggu'})`;
            }

            return 'Selesai';
        },

        canEditKpi(member) {
            if (member.is_eligible === false) return false;
            return (
                member.submissionStatus === 'rejected' ||
                (member.submissionStatus == 'waiting_approval' &&
                    member.approvers &&
                    member.approvers.some((a) => a.status == 'inprogress' && a.level == 1))
            );
        },

        calculateTotalWeight(kpis) {
            if (!kpis || kpis.length === 0) return 0;
            return kpis.reduce((sum, kpi) => sum + (parseFloat(kpi.weight) || 0), 0);
        },

        calculateProgressStats() {
            const stats = {
                waiting_approval: 0,
                rejected: 0,
                approved: 0,
                resubmitted: 0,
                total: this.teamMembers.length,
                without_kpi: this.membersWithoutKPI.length,
            };

            this.teamMembers.forEach((member) => {
                if (member.submissionStatus && stats.hasOwnProperty(member.submissionStatus)) {
                    stats[member.submissionStatus]++;
                }
            });

            this.progressStats = stats;

            // Update tab counts
            this.mainTabs[0].count = this.teamMembers.length;
            this.mainTabs[1].count = this.membersWithoutKPI.length;
            this.mainTabs[2].count = stats.waiting_approval + stats.rejected + stats.approved;
        },

        // Team Overview Methods
        prioritizeMembersWithoutKPI() {
            this.selectedMembers = this.membersWithoutKPI
                .filter((member) => member.is_eligible !== false)
                .map((member) => member.id);
            this.navigateToBulkCreation();
        },

        addKpiForMember(member) {
            if (member.is_eligible === false) {
                ElMessage.warning(member.ineligible_reason || 'Anggota belum terdaftar di jadwal pengisian aktif ini');
                return;
            }
            this.selectedMembers = [member.id];
            this.navigateToBulkCreation();
        },

        toggleSelectAll() {
            if (this.selectAll) {
                this.selectedMembers = this.filteredTeamMembers
                    .filter((member) => !member.hasSubmittedKPI)
                    .map((member) => member.id);
            } else {
                this.selectedMembers = [];
            }
        },

        // Form KPI Methods
        handleBulkStepChange(step) {
            // Saat mengunci satu orang, langkah "Pilih Tim" tertutup — di situlah
            // anggota lain bisa ikut tercentang.
            if (this.modeSatuAnggota && step === 1) {
                this.showAlert(
                    'Sedang memperbaiki KPI ' +
                        (this.activeMemberTab?.name || 'anggota ini') +
                        '. Simpan atau batalkan dulu sebelum memilih anggota lain.',
                    'warning',
                    'Perbaikan sedang berjalan',
                );

                return;
            }

            if (step < this.bulkCurrentStep) {
                this.bulkCurrentStep = step;
            }
        },

        nextBulkStep() {
            if (this.bulkCurrentStep === 2 && !this.validateAllMembers()) {
                return;
            }

            if (this.bulkCurrentStep < 3) {
                this.bulkCurrentStep++;
                if (this.bulkCurrentStep === 2 && this.selectedMembers.length > 0) {
                    this.activeMemberTab = this.getMemberById(this.selectedMembers[0]);
                }
            }
        },

        previousBulkStep() {
            if (this.modeSatuAnggota && this.bulkCurrentStep === 2) {
                this.showAlert(
                    'Sedang memperbaiki KPI ' +
                        (this.activeMemberTab?.name || 'anggota ini') +
                        '. Simpan atau batalkan dulu sebelum memilih anggota lain.',
                    'warning',
                    'Perbaikan sedang berjalan',
                );

                return;
            }

            if (this.bulkCurrentStep > 1) {
                this.bulkCurrentStep--;
            }
        },

        // Member Selection Methods
        isMemberSelected(memberId) {
            return this.selectedMembers.includes(memberId);
        },

        toggleMemberSelection(memberId) {
            const member = this.getMemberById(memberId);
            if (!member) return;

            if (member.is_eligible === false) {
                ElMessage.warning(`${member.name} belum terdaftar di jadwal pengisian aktif ini.`);
                return;
            }

            if (member.hasSubmittedKPI) {
                return;
            }

            if (!this.hasApprover(member)) {
                ElMessage.warning(`${member.name} tidak memiliki approver. Silahkan Hubungi Admin untuk menambahkan!`);
                return;
            }

            const index = this.selectedMembers.indexOf(memberId);
            if (index > -1) {
                this.selectedMembers.splice(index, 1);
            } else {
                this.selectedMembers.push(memberId);
            }
        },

        selectAllMembers() {
            this.selectedMembers = this.availableTeamMembers
                .filter((member) => member.is_eligible !== false && !member.hasSubmittedKPI && this.hasApprover(member))
                .map((member) => member.id);
        },

        selectMembersWithoutKPI() {
            this.selectedMembers = this.membersWithoutKPI
                .filter((member) => member.is_eligible !== false)
                .map((member) => member.id);
        },

        clearMemberSelection() {
            this.selectedMembers = [];
        },

        toggleSelectAllStep1() {
            if (this.selectAllStep1) {
                this.selectAllMembers();
            } else {
                this.clearMemberSelection();
            }
        },

        getMemberById(memberId) {
            return this.teamMembers.find((member) => member.id === memberId);
        },

        // Template Management Methods
        addTemplateKpi() {
            const newKpi = {
                Id_Penjadwalan: null,
                parameter: '',
                bscCategory: '',
                definition: '',
                formula: '',
                unit: '',
                weight: null,
                target: '',
                targetSimpan: {},
                availableUnits: [],
            };
            this.templateKpis.push(newKpi);
        },

        removeTemplateKpi(index) {
            if (this.templateKpis.length > 1) {
                this.templateKpis.splice(index, 1);
            }
        },

        toggleGlobalTemplate() {
            if (this.useGlobalTemplate) {
                // Reset semua member ke template
                this.selectedMembers.forEach((memberId) => {
                    const member = this.getMemberById(memberId);
                    if (member) {
                        member.useCustomKpi = false;
                        member.kpis = null;
                        member.activeKpiIndex = 0;
                    }
                });
            }
        },

        getMemberKpis(member) {
            if (!member) return this.templateKpis;

            // Jika member menggunakan custom KPI dan memiliki KPI
            if (member.useCustomKpi && member.kpis) {
                return member.kpis;
            }

            // Gunakan template global
            return this.templateKpis;
        },

        getActiveKpiForMember(member) {
            const kpis = this.getMemberKpis(member);
            const activeIndex = member?.activeKpiIndex || 0;
            return kpis[activeIndex] || this.templateKpis[0] || {};
        },

        getActiveKpiIndex(member) {
            return member?.activeKpiIndex || 0;
        },
        approvalData(member) {
            if (!member.approvalData || !member.approvers) return null;

            const approversArr = member.approvers;
            const approver = approversArr
                .filter((a) => a.Flag_approval === null || a.Flag_approval === 'T')
                .sort((a, b) => parseInt(a.level) - parseInt(b.level))[0];

            if (!approver) return {};

            const merged = { ...member.approvalData, approver: approver.name };

            return merged;
        },

        updateMemberKpiData(member, data) {
            if (!member) {
                // Update template - cari kpi yang aktif
                const activeIndex = this.activeMemberTab?.activeKpiIndex || 0;
                if (this.templateKpis[activeIndex]) {
                    Object.assign(this.templateKpis[activeIndex], data);
                }
                return;
            }

            // Jika member menggunakan template, update langsung ke template
            if (!member.useCustomKpi) {
                const activeIndex = member.activeKpiIndex || 0;
                if (this.templateKpis[activeIndex]) {
                    Object.assign(this.templateKpis[activeIndex], data);
                }
                return;
            }

            // Jika member menggunakan custom KPI
            const activeIndex = member.activeKpiIndex || 0;

            // Pastikan kpis array ada
            if (!member.kpis) {
                member.kpis = [...this.templateKpis];
            }

            if (!member.kpis[activeIndex]) {
                member.kpis[activeIndex] = { ...this.templateKpis[0] };
            }

            // Update data
            Object.assign(member.kpis[activeIndex], data);
        },

        enableCustomKpiForMember(member) {
            member.useCustomKpi = true;

            if (member.kpis && member.kpis?.length != 0) {
                member.kpis.forEach((kpi) => {
                    kpi.bscCategory = kpi.category || '';
                    kpi.definition = kpi.description || '';

                    delete kpi.category;
                    delete kpi.description;
                });
            }

            if (member.kpis?.length == 0) {
                member.kpis = JSON.parse(JSON.stringify(this.templateKpis));
            }

            member.activeKpiIndex = 0;

            console.log(member.kpis);

            ElMessage.success(`KPI custom diaktifkan untuk ${member.name}`);
        },

        switchToKpiForm(member, index) {
            if (member) {
                member.activeKpiIndex = index;
            }
        },

        addNewKpi(member) {
            const newKpi = {
                Id_Penjadwalan: null,
                parameter: '',
                bscCategory: '',
                definition: '',
                formula: '',
                unit: '',
                weight: null,
                target: '',
                targetSimpan: {},
                availableUnits: [],
            };

            if (!member.useCustomKpi) {
                // Tambah ke template (berlaku untuk semua)
                this.templateKpis.push(newKpi);
                // Update active index untuk member yang sedang aktif
                if (this.activeMemberTab && this.activeMemberTab.id === member.id) {
                    this.activeMemberTab.activeKpiIndex = this.templateKpis.length - 1;
                }
            } else {
                // Tambah ke custom KPI member
                if (!member.kpis) {
                    member.kpis = [...this.templateKpis];
                }
                member.kpis.push(newKpi);
                member.activeKpiIndex = member.kpis.length - 1;
            }
        },

        removeKpi(member, index) {
            if (!member.useCustomKpi) {
                // Hapus dari template (hanya jika lebih dari 1)
                if (this.templateKpis.length > 1) {
                    this.templateKpis.splice(index, 1);
                    // Adjust active index
                    if (this.activeMemberTab && this.activeMemberTab.activeKpiIndex >= index) {
                        this.activeMemberTab.activeKpiIndex = Math.max(0, this.activeMemberTab.activeKpiIndex - 1);
                    }
                }
            } else {
                // Hapus dari custom KPI member
                if (member.kpis && member.kpis.length > 1) {
                    member.kpis.splice(index, 1);
                    if (member.activeKpiIndex >= index) {
                        member.activeKpiIndex = Math.max(0, member.activeKpiIndex - 1);
                    }
                }
            }
        },

        calculateMemberTotalWeight(member) {
            const kpis = this.getMemberKpis(member);
            return this.calculateTotalWeight(kpis);
        },

        calculateMemberRemainingWeight(member) {
            return Math.max(0, 100 - this.calculateMemberTotalWeight(member));
        },

        toggleCustomKpiForMember(member) {
            if (!member.useCustomKpi) {
                // Aktifkan custom KPI
                this.enableCustomKpiForMember(member);
            } else {
                // Nonaktifkan custom KPI - kembali ke template
                this.showConfirm(
                    `Nonaktifkan KPI custom untuk ${member.name}? KPI akan kembali menggunakan template global.`,
                    'Konfirmasi',
                    () => {
                        member.useCustomKpi = false;
                        member.kpis = null;
                        member.activeKpiIndex = 0;
                        ElMessage.warning(`KPI custom dinonaktifkan untuk ${member.name}`);
                    },
                );
            }
        },

        // Validation Methods
        isFormCompleted(kpi) {
            return (
                kpi.parameter &&
                kpi.bscCategory &&
                kpi.definition &&
                kpi.unit &&
                kpi.formula &&
                kpi.target &&
                this.isTargetFull(kpi) &&
                kpi.weight > 0
            );
        },

        isTargetFull(kpi) {
            if (!kpi?.targetSimpan) return false;
            const values = Object.values(kpi?.targetSimpan);
            // Field pra-start bernilai null (dikecualikan). Field aktif yang belum diisi bernilai ''.
            // Lengkap = tidak ada field aktif yang masih '' DAN ada minimal satu field aktif terisi.
            const activeValues = values.filter((val) => val !== null);
            return activeValues.length > 0 && activeValues.every((val) => val !== '');
        },

        confirmChangeTarget() {
            if (Array.isArray(this.activeKpi.targetSimpan)) {
                return this.activeKpi.targetSimpan.some(
                    (item) => item.Nilai_Target !== null && item.Nilai_Target !== '',
                );
            }

            if (typeof this.activeKpi.targetSimpan === 'object' && this.activeKpi.targetSimpan !== null) {
                return Object.values(this.activeKpi.targetSimpan).some((val) => val !== null && val !== '');
            }

            return false;
        },

        // Target Modal Methods
        async triggerTargetForMember(member) {
            this.activeMemberTab = member;
            this.quickTargetValue = '';
            this.quickTargetFormatted = '';
            this.showModalTarget = true;
        },

        formatQuickTargetDisplay(value) {
            if (value === null || value === undefined || value === '') return '';
            const format = this.getTargetUnitFormat;
            if (format === 'currency') {
                return formatUtils.currency.format(value);
            }
            return value.toString();
        },

        handleQuickTargetInput(event) {
            const input = event.target;
            const cursorPosition = input.selectionStart;
            let value = event.target.value;
            const format = this.getTargetUnitFormat;

            if (format === 'currency') {
                const originalValue = value;
                const originalLength = value.length;

                // 1. Parse ("1.000,5" -> "1000.5")
                const parsedValue = formatUtils.currency.parse(value);
                this.quickTargetValue = parsedValue;

                // 2. Format display ("1000.5" -> "1.000,5")
                const formattedValue = formatUtils.currency.format(parsedValue);
                this.quickTargetFormatted = formattedValue;

                // 3. Atur cursor
                this.$nextTick(() => {
                    const newLength = formattedValue.length;
                    let newCursorPosition = cursorPosition + (newLength - originalLength);
                    if (originalLength > newLength) {
                        newCursorPosition = cursorPosition - (originalLength - newLength) + 1;
                    }
                    const dotsAdded =
                        (formattedValue.match(/\./g) || []).length - (originalValue.match(/\./g) || []).length;
                    if (dotsAdded > 0) newCursorPosition += dotsAdded;

                    input.setSelectionRange(newCursorPosition, newCursorPosition);
                });
            } else {
                // Percentage, decimal, number
                const parsedValue = formatUtils.parseNumeric(value);
                this.quickTargetValue = parsedValue;
                this.quickTargetFormatted = parsedValue;
            }
        },

        async getTargetBreakdown() {
            this.loading.fillingTarget = true;
            const activeKpi = this.activeKpi;
            try {
                this.targetBreakdownDatas = [];
                const targetId = activeKpi.target;

                // Bulan mana yang boleh diisi ditentukan oleh ANGGOTA, bukan leader.
                const kodeKaryawan = this.anggotaBreakdown;

                const response = await axios.get(`/api/v1/form-kpi/getTargetBreakdown`, {
                    params: {
                        Id_Master_Target: targetId,
                        Id_Penjadwalan: this.activeSchedule?.id,
                        kode_karyawan: kodeKaryawan,
                    },
                });

                if (response.status === 200 && response.data?.result) {
                    this.targetBreakdownDatas = response.data.result;
                    this.fillTargetData();
                }
            } catch (error) {
                console.error('Failed to fetch target breakdown:', error);
                this.targetBreakdownDatas = [];
            } finally {
                this.loading.fillingTarget = false;
            }
        },

        fillTargetData() {
            if (!this.choosedTarget) return;
            const activeKpi = this.activeKpi;

            if (Object.keys(activeKpi.targetSimpan).length > 0) return;

            const targetFields = this.targetModalFields;
            const initialValues = {};

            targetFields.forEach((field) => {
                // Periode pra-start (disabled) tidak diberi nilai (null) → tidak akan di-insert.
                initialValues[field.name] = field.disabledField ? null : '';
            });

            activeKpi.targetSimpan = { ...initialValues };
        },

        updateTargetValues(updatedTarget) {
            const activeKpi = this.activeKpi;
            activeKpi.targetSimpan = { ...updatedTarget };
        },

        applyQuickValueToAllPeriods() {
            if (this.quickTargetValue === '' || this.quickTargetValue === null) {
                ElMessage.warning('Masukkan nilai target terlebih dahulu');
                return;
            }

            const val = this.getTargetUnitFormat === 'currency'
                ? parseFloat(this.quickTargetValue)
                : (parseFloat(this.quickTargetValue) || this.quickTargetValue);

            const updatedTarget = { ...this.activeKpi.targetSimpan };

            this.targetModalFields.forEach((field) => {
                if (field.required !== false && !field.disabledField) {
                    updatedTarget[field.name] = val;
                }
            });

            this.activeKpi.targetSimpan = updatedTarget;
            const displayVal = this.quickTargetFormatted || this.quickTargetValue;
            ElMessage.success(`Nilai ${displayVal} diterapkan ke semua periode aktif`);
        },

        distributeQuickValueEvenly() {
            if (this.quickTargetValue === '' || this.quickTargetValue === null) {
                ElMessage.warning('Masukkan total nilai target terlebih dahulu');
                return;
            }

            const totalVal = parseFloat(this.quickTargetValue);
            if (isNaN(totalVal) || totalVal <= 0) {
                ElMessage.warning('Total nilai target harus lebih dari 0');
                return;
            }

            const activeFields = this.targetModalFields.filter((f) => f.required !== false && !f.disabledField);

            if (activeFields.length === 0) return;

            const perPeriodVal = Math.round((totalVal / activeFields.length) * 100) / 100;
            const updatedTarget = { ...this.activeKpi.targetSimpan };

            activeFields.forEach((field) => {
                updatedTarget[field.name] = perPeriodVal;
            });

            this.activeKpi.targetSimpan = updatedTarget;
            const formattedTotal = this.formatQuickTargetDisplay(totalVal);
            const formattedPerPeriod = this.formatQuickTargetDisplay(perPeriodVal);
            ElMessage.success(`Total ${formattedTotal} dibagi rata ke ${activeFields.length} periode (@${formattedPerPeriod})`);
        },

        resetAllTargetPeriods() {
            const updatedTarget = { ...this.activeKpi.targetSimpan };
            this.targetModalFields.forEach((field) => {
                if (field.required !== false && !field.disabledField) {
                    updatedTarget[field.name] = '';
                }
            });
            this.activeKpi.targetSimpan = updatedTarget;
            this.quickTargetValue = '';
            this.quickTargetFormatted = '';
            ElMessage.info('Nilai target seluruh periode telah direset');
        },

        closeModalTarget() {
            this.showModalTarget = false;
        },

        submitTarget() {
            if (this.validateTargetFields()) {
                this.showModalTarget = false;
                ElMessage.success('Target berhasil disimpan');
            } else {
                ElMessage.warning('Harap isi semua field target');
            }
        },

        validateTargetFields() {
            const activeKpi = this.activeKpi;
            const targetSimpan = activeKpi.targetSimpan;
            if (!targetSimpan) return false;

            const targetFields = this.targetModalFields;
            for (let field of targetFields) {
                // Periode pra-start (tidak wajib) dilewati dari validasi.
                if (field.required === false) continue;
                const value = this.activeKpi.targetSimpan[field.name];
                // Nilai 0 dianggap sah; yang ditolak hanya field kosong.
                if (value === undefined || value === null || value === '') {
                    return false;
                }
            }
            return true;
        },

        // Formula Change Handler
        async onFormulaChangeForMember(member, formulaId) {
            const activeKpi = this.activeKpi;
            activeKpi.formula = formulaId;
            const unitChoose = activeKpi.unit || null;

            this.loading.units = true;

            try {
                const response = await axios.get(`/api/v1/form-kpi/by/${formulaId}`);
                const data = Array.isArray(response.data.result) ? response.data.result : [];

                activeKpi.availableUnits = data.map((item) => ({
                    id: item.Id_Master_Satuan,
                    name: item.Simbol_Satuan,
                    Format_Input: item.Format_Input,
                }));
                activeKpi.unit = activeKpi.availableUnits.find((e) => e.id == unitChoose)?.id || null;
            } catch (error) {
                console.error('Error loading units:', error);
                activeKpi.availableUnits = [];
            } finally {
                this.loading.units = false;
            }
        },

        // Preview Methods
        getPreviewStatusClass(memberId) {
            const member = this.getMemberById(memberId);
            const totalWeight = this.calculateMemberTotalWeight(member);
            const kpis = this.getMemberKpis(member);
            if (!kpis || kpis.length === 0) {
                return 'status-rejected';
            }
            if (totalWeight === 100) {
                return 'status-approved';
            } else {
                return 'status-rejected';
            }
        },

        getPreviewStatusText(memberId) {
            const member = this.getMemberById(memberId);
            const totalWeight = this.calculateMemberTotalWeight(member);
            const kpis = this.getMemberKpis(member);
            if (!kpis || kpis.length === 0) {
                return 'Belum Ada KPI';
            }
            if (totalWeight === 100) {
                return 'Siap Diajukan';
            } else {
                return `Bobot: ${totalWeight}%`;
            }
        },

        editMemberFromPreview(memberId) {
            const member = this.getMemberById(memberId);
            if (!member) return;
            this.bulkCurrentStep = 2;
            this.activeMemberTab = member;
        },

        getKpiCategoryName(kpi) {
            if (kpi.Nama_Kategori) return kpi.Nama_Kategori;
            if (!kpi.category) return null;
            const cat = this.BscKategoriDatas?.find(
                (c) => c.id === kpi.category || c.Id_Bsc_Kategori === kpi.category,
            );
            return cat?.Nama_Kategori || null;
        },

        getKpiUnitName(kpi) {
            if (kpi.Nama_Satuan) return kpi.Nama_Satuan;
            if (kpi.availableUnits && Array.isArray(kpi.availableUnits)) {
                const u = kpi.availableUnits.find((unit) => unit.id === kpi.unit);
                if (u) return u.name;
            }
            return null;
        },

        formatTargetPreview(targetId) {
            const target = this.TargetDatas.find((t) => t.Id_Master_Target === targetId);
            return target?.Nama_Target || 'No Target';
        },

        getTargetStatusForPreview(kpi) {
            if (!kpi) return '-';
            if (kpi.Nama_Target) return kpi.Nama_Target;
            if (kpi.target) {
                const target = this.TargetDatas?.find(
                    (t) => t.Id_Master_Target === kpi.target || t.id === kpi.target,
                );
                if (target?.Nama_Target) return target.Nama_Target;
            }
            return 'Target Bulanan';
        },

        formatFormulaPreview(formulaId) {
            const formula = this.FormulaDatas.find((f) => f.Id_Master_Formula === formulaId);
            return formula?.Rumus || 'No Formula';
        },

        viewKpiDetailPreview(kpi) {
            console.log('View KPI detail:', kpi);
        },

        calculateCompletion(kpi) {
            let completedFields = 0;
            const totalFields = 8;
            if (kpi.parameter) completedFields++;
            if (kpi.bscCategory) completedFields++;
            if (kpi.definition) completedFields++;
            if (kpi.unit) completedFields++;
            if (kpi.formula) completedFields++;
            if (kpi.target) completedFields++;
            if (this.isTargetFull(kpi)) completedFields++;
            if (kpi.weight > 0) completedFields++;
            return Math.round((completedFields / totalFields) * 100);
        },

        formatBscCategory(category) {
            const data = this.BscKategoriDatas.find((i) => i.Id_Master_Bsc_Kategori === category);
            return data?.Nama_Kategori || 'Loading...';
        },

        getKpiIcon(category) {
            const icons = {
                financial: 'bi bi-currency-dollar',
                customer: 'bi bi-people',
                internal: 'bi bi-gear',
                learning: 'bi bi-graph-up',
            };
            return icons[category] || 'bi bi-file-text';
        },

        truncateText(text, length) {
            if (!text) return '';
            return text.length > length ? text.substring(0, length) + '...' : text;
        },

        // Submission Methods
        // async submitBulkKpis() {
        //     try {
        //         this.showConfirm(
        //             `Apakah Anda yakin ingin mengajukan KPI untuk ${this.selectedMembers.length} anggota?`,
        //             "Konfirmasi Pengajuan",
        //             async () => {
        //                 this.loading.bulk = true;
        //                 let data = [];
        //                 // Update team members data
        //                 this.selectedMembers.forEach((memberId) => {
        //                     const member = this.getMemberById(memberId);
        //                     if (member) {
        //                         const kpis = this.getMemberKpis(member);
        //                         if (kpis.length > 0) {
        //                             member.kpis = [...kpis];
        //                             member.submissionStatus =
        //                                 "waiting_approval";
        //                             member.lastUpdate =
        //                                 new Date().toISOString();
        //                             member.hasSubmittedKPI = true;
        //                         }

        //                         data.push(member);
        //                     }
        //                 });

        //                 this.showCustomAlert = false;

        //                 const response = await axios.post(
        //                     "/api/v1/form-kpi/team/store",
        //                     {
        //                         data: data,
        //                         penjadwalan: this.activeSchedule,
        //                     }
        //                 );

        //                 if (response.status == 200) {
        //                     ElNotification({
        //                         title: "Success",
        //                         message:
        //                             "Berhasil menyimpan pengajuan KPI tim.",
        //                         type: "success",
        //                     });
        //                     this.resetBulkCreation();
        //                     this.fetchListData();
        //                     this.calculateProgressStats();
        //                 }

        //                 this.activeTab = "progress";
        //                 this.loading.bulk = false;
        //             }
        //         );
        //     } catch (error) {
        //         this.loading.bulk = false;
        //         ElNotification({
        //             title: "Error",
        //             message: `Gagal enyimpan pengajuan KPI tim, ${error}.`,
        //             type: "error",
        //         });
        //         console.log(error);
        //     } finally {
        //         this.loading.bulk = false;
        //     }
        // },
        async submitBulkKpis() {
            // Dimuat sebelum dialog tampil supaya leader melihat pembagian bulan
            // mulai saat memutuskan, bukan setelah datanya terlanjur terpotong.
            await this.muatRingkasanStartPeriode();

            this.showConfirm(
                `Apakah Anda yakin ingin mengajukan KPI untuk ${this.selectedMembers.length} anggota?`,
                'Konfirmasi Pengajuan',
                async () => {
                    this.loading.bulk = true;
                    const backupMembers = {};

                    try {
                        let data = [];
                        // let No_Transaksi_Ref = null;

                        // Saat memperbaiki satu anggota, kiriman disaring ke
                        // orang itu saja. Langkah "Pilih Tim" memang sudah
                        // dikunci, tapi selectedMembers bisa saja tertinggal
                        // dari alur sebelumnya — dan menyertakan orang lain di
                        // sini akan membatalkan pengajuan mereka yang sedang
                        // berjalan.
                        const anggotaDikirim = this.modeSatuAnggota
                            ? this.selectedMembers.filter(
                                  (id) => this.getMemberById(id)?.employeeId === this.revisiUntukKode,
                              )
                            : this.selectedMembers;

                        anggotaDikirim.forEach((memberId) => {
                            const member = this.getMemberById(memberId);
                            if (member) {
                                console.log('ini store', this.approvalData(member));
                                const approval = this.approvalData(member);
                                console.log('ini store', approval);
                                const kpis = this.getMemberKpis(member);

                                backupMembers[memberId] = {
                                    ...member,
                                    kpis: [...(member.useCustomKpi ? member.kpis : [])],
                                };

                                if (kpis.length > 0) {
                                    member.kpis = [...kpis];
                                    member.submissionStatus = 'waiting_approval';
                                    member.lastUpdate = new Date().toISOString();
                                    member.hasSubmittedKPI = true;
                                    member.No_Transaksi_Ref = approval?.No_Transaksi || null;
                                }

                                data.push(member);
                            }
                        });

                        this.showCustomAlert = false;

                        const response = await axios.post('/api/v1/form-kpi/team/store', {
                            data: data,
                            penjadwalan: this.activeSchedule,
                        });

                        if (response.status == 200) {
                            ElNotification({
                                title: 'Success',
                                message: 'Berhasil menyimpan pengajuan KPI tim.',
                                type: 'success',
                            });
                            this.resetBulkCreation();
                            this.fetchListData();
                            this.fetchTeamData();
                            this.calculateProgressStats();
                        }

                        this.activeTab = 'progress';
                    } catch (error) {
                        console.error(error);

                        console.log(backupMembers);
                        Object.keys(backupMembers).forEach((id) => {
                            const index = this.teamMembers.findIndex((m) => m.id === id);
                            if (index !== -1) {
                                this.teamMembers[index] = backupMembers[id];
                            }
                        });

                        const backendMessage = error.response?.data?.message || 'Terjadi kesalahan tak terduga.';

                        await ElMessageBox.alert(
                            `Gagal menyimpan pengajuan KPI tim.<br><b>${backendMessage}</b>`,
                            'Kesalahan',
                            {
                                type: 'error',
                                dangerouslyUseHTMLString: true,
                            },
                        );
                    } finally {
                        this.loading.bulk = false;
                    }
                },
            );
        },

        confirmResetBulkCreation() {
            this.showResetConfirmation = true;
        },

        resetBulkCreation() {
            this.bulkCurrentStep = 1;
            this.selectedMembers = [];
            this.activeMemberTab = null;
            // Satu-satunya tempat kuncinya dilepas: leader benar-benar keluar
            // dari alur perbaikan, bukan sekadar berpindah langkah.
            this.revisiUntukKode = null;
            this.confirmationChecked = false;
            console.log(this.bulkCurrentStep);
            this.showResetConfirmation = false;
            this.templateKpis = [
                {
                    Id_Penjadwalan: null,
                    parameter: '',
                    bscCategory: '',
                    definition: '',
                    formula: '',
                    unit: '',
                    weight: null,
                    target: '',
                    targetSimpan: {},
                    availableUnits: [],
                },
            ];
        },

        // Member KPI Management
        viewMemberKpis(member) {
            this.selectedMember = { ...member };
            this.showMemberDetailModal = true;
        },

        closeMemberDetailModal() {
            this.showMemberDetailModal = false;
            this.selectedMember = null;
        },

        prepareEditMember(member) {
            this.selectedMembers = [member.id];
            this.activeTab = 'bulk';
            this.bulkCurrentStep = 2;
            this.activeMemberTab = member;

            // Kunci kiriman ke orang ini saja. Dilepas hanya oleh
            // resetBulkCreation(), yaitu ketika leader benar-benar keluar dari
            // alur ini — bukan sekadar berpindah langkah.
            this.revisiUntukKode = member.employeeId;

            // Pastikan member menggunakan custom KPI saat edit
            if (!member.useCustomKpi) {
                this.enableCustomKpiForMember(member);
            }
        },

        // Slider Methods
        scrollTabs(direction) {
            const container = document.querySelector('.tabs-container');
            if (container) {
                const scrollAmount = 200;
                container.scrollLeft += direction * scrollAmount;
            }
        },

        // Select Member untuk Form
        selectMember(member) {
            this.activeMemberTab = member;

            // Dimuat sekali lalu dipakai ulang; kalau gagal, kartu anggota hanya
            // kehilangan keterangan bulan mulai - pengisian tetap jalan.
            if (!Object.keys(this.startPeriodeAnggota).length) {
                this.muatRingkasanStartPeriode();
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

        filteredProgressMembers: {
            handler() {
                this.$nextTick(() => {
                    this.updatePagination();
                });
            },
            deep: true,
        },

        availableTeamMembers: {
            handler() {
                this.$nextTick(() => {
                    this.updatePagination();
                });
            },
            deep: true,
        },

        activeTab: {
            handler(newTab) {
                this.$nextTick(() => {
                    this.updatePagination();
                });
            },
        },
        selectedMembers: {
            handler(newVal) {
                const availableMembers = this.filteredTeamMembers.filter((m) => !m.hasSubmittedKPI);
                this.selectAll = newVal.length > 0 && newVal.length === availableMembers.length;

                const allAvailable = this.availableTeamMembers.filter((m) => !m.hasSubmittedKPI);
                this.selectAllStep1 = newVal.length > 0 && newVal.length === allAvailable.length;
            },
            deep: true,
        },
        activeKpi: {
            handler(newVal) {
                if (newVal) {
                    const activeKpi = this.activeKpi;
                    if (!activeKpi.availableUnits || activeKpi.availableUnits.length === 0) {
                        activeKpi.availableUnits = [];
                    }

                    if (activeKpi?.formula) {
                        this.onFormulaChangeForMember(newVal, activeKpi.formula);
                    }
                    if (activeKpi?.target) {
                        this.getTargetBreakdown();
                    }
                }
            },
            immediate: true,
        },
        activeMemberTab: {
            handler(newVal) {
                if (newVal) {
                    const activeKpi = this.activeKpi;
                    if (!activeKpi.availableUnits || activeKpi.availableUnits.length === 0) {
                        activeKpi.availableUnits = [];
                    }

                    if (activeKpi?.formula) {
                        this.onFormulaChangeForMember(newVal, activeKpi.formula);
                    }
                    if (activeKpi?.target) {
                        this.getTargetBreakdown();
                    }
                }
            },
            immediate: true,
        },

        /**
         * Cakupan sel yang boleh diisi ikut berubah TANPA activeKpi berganti.
         *
         * Dua hal mengubahnya: menekan "Gunakan Template" / "KPI Custom", dan
         * mencentang atau melepas anggota. Keduanya tidak menyentuh identitas
         * activeKpi, jadi tanpa pemantau ini modal tetap memakai batas bulan
         * milik keadaan sebelumnya — dan itu tidak menimbulkan galat apa pun,
         * hanya sel yang mati di tempat yang salah.
         */
        cakupanBreakdown: {
            handler(baru, lama) {
                if (baru === lama) return;
                if (!this.showModalTarget) return;
                if (!this.activeKpi?.target) return;

                this.getTargetBreakdown();
            },
        },
    },
    async mounted() {
        await this.initializeData();
    },
};
</script>

<style scoped>
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
/* Description Styling */
.kpi-description-preview {
    margin-top: 8px;
    color: #666;
    font-size: 14px;
    line-height: 1.4;
    padding: 0 8px;
}

/* Target Grid Section */
.preview-target-grid-section {
    margin-top: 16px;
    padding: 0 8px;
}

.preview-target-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 12px;
    font-size: 14px;
}

.preview-target-grid-layout {
    width: 100%;
}

.preview-target-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0;
}

.preview-target-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    border-bottom: 1px solid #e0e0e0;
    min-height: 40px;
}

.preview-target-item:nth-child(4n) {
    border-right: none;
}

.preview-month-label {
    color: #333;
    font-size: 13px;
    font-weight: 500;
    white-space: nowrap;
}

.preview-target-value {
    color: #007bff;
    font-weight: 600;
    font-size: 13px;
    white-space: nowrap;
    margin-left: 8px;
}

/* Untuk responsive design */
@media (max-width: 768px) {
    .preview-target-row {
        grid-template-columns: repeat(2, 1fr);
    }

    .preview-target-item:nth-child(2n) {
        border-right: none;
    }
}

@media (max-width: 480px) {
    .preview-target-row {
        grid-template-columns: 1fr;
    }

    .preview-target-item {
        border-right: none;
    }
}
/* Description Styling */
/* .kpi-description {
    margin-top: 8px;
    color: #666;
    font-size: 14px;
    line-height: 1.4;
} */

/* Target Grid Styling */
.target-breakdown-grid {
    margin-top: 16px;
}

.target-grid-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 12px;
    font-size: 14px;
}

.target-grid-container {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px 8px;
}

.target-grid-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e0e0e0;
}

.target-month {
    color: #333;
    font-size: 13px;
    font-weight: 500;
}

.target-percentage {
    color: #007bff;
    font-weight: 600;
    font-size: 13px;
}
/* Pagination */
/* Pagination Styles */

/* Filter Bars */
.team-filters-bar,
.bulk-filters-bar,
.progress-filters-bar {
    background: var(--surface);
    padding: 1.25rem;
    border-radius: 12px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

/* Filter Selects */
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

.filter-select.compact {
    padding: 0.6rem 0.75rem;
    font-size: 0.85rem;
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Filter Actions */
.filter-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
    justify-content: flex-end;
}

.btn-filter-reset {
    padding: 0.5rem 1rem;
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-filter-reset:hover {
    background: var(--primary-light);
    color: var(--primary);
}

.filter-count {
    font-size: 0.85rem;
    color: var(--text-muted);
    font-weight: 500;
}

/* Progress Filter Stats */
.filter-stats {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

.stat-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.5rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 60px;
}

.stat-badge.waiting {
    background: rgba(6, 182, 212, 0.1);
    color: var(--info);
}

.stat-badge.approved {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.stat-badge.rejected {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.stat-badge:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.stat-badge .count {
    font-weight: 700;
    font-size: 1.1rem;
}

.stat-badge .label {
    font-size: 0.7rem;
    font-weight: 600;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .team-filters-bar,
    .bulk-filters-bar,
    .progress-filters-bar {
        padding: 1rem;
    }

    .filter-actions {
        justify-content: space-between;
    }

    .filter-stats {
        justify-content: center;
    }

    .stat-badge {
        min-width: 50px;
        padding: 0.4rem;
    }

    .stat-badge .count {
        font-size: 1rem;
    }

    .stat-badge .label {
        font-size: 0.65rem;
    }
}

@media (max-width: 576px) {
    .filter-stats {
        flex-wrap: wrap;
    }

    .stat-badge {
        flex: 1;
        min-width: 45px;
    }
}

.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--surface);
    border-radius: 12px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    margin-top: 1rem;
}

.pagination-info {
    font-size: 0.875rem;
    color: var(--text-muted);
    font-weight: 500;
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
    color: var(--text);
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
    min-width: 36px;
    height: 36px;
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text);
}

.pagination-page:hover:not(.active) {
    background: var(--surface-soft);
    border-color: var(--primary-light);
}

.pagination-page.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination-page:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Responsive Design untuk Pagination */
@media (max-width: 768px) {
    .pagination-container {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }

    .pagination-controls {
        width: 100%;
        justify-content: center;
    }

    .pagination-pages {
        flex-wrap: wrap;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .pagination-pages {
        gap: 0.125rem;
    }

    .pagination-page {
        min-width: 32px;
        height: 32px;
        font-size: 0.8rem;
    }

    .pagination-btn {
        width: 32px;
        height: 32px;
    }
}

/* Validation modal */

.kpi-validation-content {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.kpi-validation-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0.75rem;
    padding: 0.5rem;
}

.kpi-validation-icon {
    width: 4rem;
    height: 4rem;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--warning) 0%, var(--hcis-amber-400) 100%);
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 0.5rem;
}

.kpi-validation-icon i {
    font-size: 1.75rem;
    color: white;
}

.kpi-validation-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.kpi-validation-subtitle {
    font-size: 0.875rem;
    color: var(--text-muted);
    margin: 0;
}

/* Scroll Container */
.kpi-validation-scroll-container {
    max-height: 280px;
    overflow-y: auto;
    border: 1px solid var(--border);
    border-radius: 8px;
    background-color: var(--surface-soft);
    padding: 0.5rem;
}

/* Validation List Styles */
.kpi-validation-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.kpi-validation-item {
    padding: 1rem;
    background-color: var(--surface);
    border-radius: 6px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    transition: all 0.2s ease;
}

.kpi-validation-item:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
}

.kpi-member-validation {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.kpi-member-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border);
}

.kpi-member-icon {
    font-size: 1.25rem;
    color: var(--primary);
}

.kpi-member-name {
    font-size: 1rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

/* Error List Styles */
.kpi-error-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.kpi-error-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.5rem;
    background-color: rgba(239, 68, 68, 0.05);
    border-radius: 4px;
    border-left: 3px solid var(--danger);
}

.kpi-error-icon {
    font-size: 1rem;
    color: var(--danger);
    flex-shrink: 0;
    margin-top: 0.125rem;
}

.kpi-error-text {
    font-size: 0.875rem;
    color: var(--text);
    line-height: 1.4;
}

/* Summary Section */
.kpi-validation-summary {
    padding: 0.75rem 1rem;
    background-color: var(--surface-soft);
    border-radius: 6px;
    border: 1px solid var(--border);
}

.kpi-summary-text {
    font-size: 0.875rem;
    color: var(--text);
    margin: 0;
    text-align: center;
}

.kpi-summary-text strong {
    color: var(--primary);
}

/* Footer Actions */
.kpi-modal-footer-actions {
    display: flex;
    justify-content: flex-end;
    width: 100%;
    gap: 0.75rem;
}

.kpi-btn {
    padding: 0.625rem 1.5rem;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.kpi-btn-primary {
    background-color: var(--primary);
    color: white;
}

.kpi-btn-primary:hover {
    background-color: var(--primary-dark);
    box-shadow: var(--shadow);
}

/* Scrollbar Styling */
.kpi-validation-scroll-container::-webkit-scrollbar {
    width: 6px;
}

.kpi-validation-scroll-container::-webkit-scrollbar-track {
    background: var(--surface-soft);
    border-radius: 3px;
}

.kpi-validation-scroll-container::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 3px;
}

.kpi-validation-scroll-container::-webkit-scrollbar-thumb:hover {
    background: var(--text-muted);
}

/* Responsive Design */
@media (max-width: 768px) {
    .kpi-validation-scroll-container {
        max-height: 54vh;
    }

    .kpi-validation-item {
        padding: 0.75rem;
    }

    .kpi-member-header {
        gap: 0.5rem;
    }

    .kpi-error-item {
        padding: 0.375rem;
    }

    .kpi-modal-footer-actions {
        flex-direction: column;
    }

    .kpi-btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .kpi-validation-header {
        gap: 0.5rem;
    }

    .kpi-validation-icon {
        width: 3rem;
        height: 3rem;
    }

    .kpi-validation-icon i {
        font-size: 1.5rem;
    }

    .kpi-validation-title {
        font-size: 1rem;
    }
}

/* Revision Indicator */
.revision-indicator {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ff4444;
    color: white;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.member-tab-slider.has-revision {
    border-color: #ff4444;
    background: rgba(255, 68, 68, 0.05);
}

/* Validation Modal */
.validation-content {
    /* padding: 1rem; */
}

.validation-header {
    display: flex;
    align-items: center;
    font-weight: 600;
    color: var(--dark);
}

.validation-list {
    /* max-height: 300px;
     */
    max-height: 80vh;
    overflow-y: auto;
}

.validation-item {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border);
}

.validation-item:last-child {
    border-bottom: none;
}

.member-validation {
    margin-bottom: 0.5rem;
}

.error-list {
    margin: 0.5rem 0 0 1rem;
    padding: 0;
}

.error-list li {
    color: var(--danger);
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.wrapper-button-footer {
    display: flex;
    justify-content: end;
    align-items: center;
    padding: 0;
}

/* Revision Indicator */
.revision-indicator {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ff4444;
    color: white;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.member-tab-slider.has-revision {
    border-color: #ff4444;
    background: rgba(255, 68, 68, 0.05);
}

/* Validation Modal */
.validation-content {
    padding: 1rem;
}

.validation-header {
    display: flex;
    align-items: center;
    font-weight: 600;
    color: var(--dark);
}

.validation-list {
    max-height: 300px;
    overflow-y: auto;
}

.validation-item {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border);
}

.validation-item:last-child {
    border-bottom: none;
}

.member-validation {
    margin-bottom: 0.5rem;
}

.error-list {
    margin: 0.5rem 0 0 1rem;
    padding: 0;
}

.error-list li {
    color: var(--danger);
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

/* Alert Custom */
.alert-content-custom {
    text-align: center;
    padding: 1rem;
}

.alert-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    display: block;
}

/* Target Summary */
.target-summary {
    background: var(--surface-soft);
    padding: 0.75rem;
    border-radius: 8px;
    border: 1px solid var(--border);
}

.summary-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
}

.summary-text {
    color: var(--text);
    font-weight: 500;
}

.total-value {
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.8rem;
}

/* Revision Note in Preview */
.revision-note-preview {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 6px;
    padding: 0.5rem;
    margin-top: 0.5rem;
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: #856404;
}

.revision-note-preview i {
    color: #ffc107;
    flex-shrink: 0;
    margin-top: 0.1rem;
}

/* KPI List Container */
.kpi-list-container {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.5rem;
}
/* .kpiform-border{
    border:1px solid var(--border);
} */
/* Info Badge Variants */
.info-badge.warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}
</style>

<style scoped>
/* Template Settings Section */
.template-settings-section {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    margin-bottom: 1.5rem;
}

.template-info-badge {
    background: var(--primary-light);
    color: var(--primary);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

/* Custom KPI Indicator di Slider */
.custom-kpi-indicator {
    margin-left: auto;
    padding: 0.25rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.custom-kpi-indicator:hover {
    transform: scale(1.1);
}

.custom-kpi-indicator .bi-toggle-on {
    font-size: 1.2rem;
    color: var(--success);
}

.custom-kpi-indicator .bi-toggle-off {
    font-size: 1.2rem;
    color: var(--text-muted);
}

/* Member Info Card */
.member-info-card {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.member-custom-toggle {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-end;
}

.custom-toggle-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    font-weight: 500;
}

.custom-toggle-checkbox {
    display: none;
}

.custom-toggle-slider {
    width: 44px;
    height: 22px;
    background: var(--border);
    border-radius: 11px;
    position: relative;
    transition: all 0.3s ease;
}

.custom-toggle-slider::before {
    content: '';
    position: absolute;
    width: 18px;
    height: 18px;
    background: white;
    border-radius: 50%;
    top: 2px;
    left: 2px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.custom-toggle-checkbox:checked + .custom-toggle-slider {
    background: var(--success);
}

.custom-toggle-checkbox:checked + .custom-toggle-slider::before {
    transform: translateX(22px);
}

.custom-toggle-text {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--dark);
}

.custom-toggle-description {
    text-align: right;
}

/* Dashboard Info */
.dashboard-info .info-badge {
    background: var(--primary-light);
    color: var(--primary);
    padding: 0.3rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.dashboard-info .info-badge.success {
    background: var(--success-light);
    color: var(--success);
}

/* Compact Card Custom Style */
.compact-card.custom {
    border-left: 4px solid var(--success);
}

/* Responsive Design */
@media (max-width: 768px) {
    .member-info-card {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }

    .wrapper-button-footer {
        padding: 1rem 0rem;
    }
    .button-footer {
        flex-grow: 0.5;
    }
    /*
    .section-header {
        display: block;
    } */

    .selection-actions {
        flex-direction: column;
    }

    .member-custom-toggle {
        align-items: center;
    }

    .custom-toggle-description {
        text-align: center;
    }

    .template-info-badge {
        font-size: 0.8rem;
        padding: 0.4rem 0.8rem;
    }
}

/* Add New Card Style */
.compact-card.add-new {
    background: linear-gradient(135deg, var(--surface-soft) 0%, var(--hcis-slate-100) 100%);
    border: 2px dashed var(--border);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.compact-card.add-new:hover {
    border-color: var(--primary);
    background: linear-gradient(135deg, var(--hcis-slate-50) 0%, var(--hcis-slate-200) 100%);
    transform: translateY(-2px);
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
    margin-bottom: 0.5rem;
}

.card-hint {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-align: center;
    margin-top: 0.5rem;
}
.template-settings-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.template-header h6 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-weight: 600;
}

.template-badge {
    background: var(--primary-light);
    color: var(--primary);
    padding: 0.4rem 0.75rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.template-actions {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.template-toggle {
    display: flex;
    align-items: center;
}

.toggle-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    font-weight: 500;
}

.toggle-label input {
    display: none;
}

.toggle-slider {
    width: 44px;
    height: 22px;
    background: var(--border);
    border-radius: 11px;
    position: relative;
    transition: all 0.3s ease;
}

.toggle-slider::before {
    content: '';
    position: absolute;
    width: 18px;
    height: 18px;
    background: white;
    border-radius: 50%;
    top: 2px;
    left: 2px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.toggle-label input:checked + .toggle-slider {
    background: var(--success);
}

.toggle-label input:checked + .toggle-slider::before {
    transform: translateX(22px);
}

.toggle-text {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--dark);
}

/* Responsive Design */
@media (max-width: 768px) {
    .template-settings-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .template-actions {
        width: 100%;
        justify-content: space-between;
    }
}

.custom-kpi-indicator {
    margin-left: auto;
    padding: 0.25rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.custom-kpi-indicator:hover {
    transform: scale(1.1);
}

.custom-kpi-indicator .bi-toggle-on {
    font-size: 1.2rem;
}

.custom-kpi-indicator .bi-toggle-off {
    font-size: 1.2rem;
}

/* Custom Toggle di Member Info */
.member-custom-toggle {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-end;
}

.custom-toggle-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    font-weight: 500;
}

.custom-toggle-checkbox {
    display: none;
}

.custom-toggle-slider {
    width: 44px;
    height: 22px;
    background: var(--border);
    border-radius: 11px;
    position: relative;
    transition: all 0.3s ease;
}

.custom-toggle-slider::before {
    content: '';
    position: absolute;
    width: 18px;
    height: 18px;
    background: white;
    border-radius: 50%;
    top: 2px;
    left: 2px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.custom-toggle-checkbox:checked + .custom-toggle-slider {
    background: var(--success);
}

.custom-toggle-checkbox:checked + .custom-toggle-slider::before {
    transform: translateX(22px);
}

.custom-toggle-text {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--dark);
}

.custom-toggle-description {
    text-align: right;
}

/* Dashboard Header */
.dashboard-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border);
}

.dashboard-header h6 {
    margin: 0;
    color: var(--dark);
    font-weight: 600;
}

.info-badge {
    background: var(--primary-light);
    color: var(--primary);
    padding: 0.3rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.info-badge.success {
    background: var(--success-light);
    color: var(--success);
}

/* Custom KPI Card Style */
.compact-card.custom {
    border-left: 4px solid var(--success);
}

/* Improved Member Info Card */
.member-info-card {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.member-info-card .member-avatar {
    width: 60px;
    height: 60px;
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

.member-details {
    flex: 1;
}

.member-details h6 {
    margin: 0 0 0.25rem 0;
    color: var(--dark);
    font-size: 1.1rem;
}

.member-details p {
    margin: 0 0 0.25rem 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.member-stats {
    display: flex;
    gap: 2rem;
    margin-right: 2rem;
}

.member-stats .stat {
    text-align: center;
}

.member-stats .stat-value {
    display: block;
    font-weight: 700;
    color: var(--primary);
    font-size: 1.3rem;
}

.member-stats .stat-label {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-weight: 500;
}

/* Improved Slider Tabs */
.member-tab-slider {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
    flex-shrink: 0;
    min-width: 200px;
    position: relative;
}

.member-tab-slider.active {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

/* Responsive Design */
@media (max-width: 768px) {
    .member-info-card {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }

    .member-stats {
        margin-right: 0;
        justify-content: center;
    }

    .member-custom-toggle {
        align-items: center;
    }

    .custom-toggle-description {
        text-align: center;
    }

    .member-tab-slider {
        min-width: 180px;
    }
}
.custom-kpi-toggle {
    background: var(--surface-soft);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border);
}

.toggle-container {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.toggle-label {
    display: flex;
    align-items: center;
    gap: 1rem;
    cursor: pointer;
    font-weight: 500;
    color: var(--dark);
}

.toggle-checkbox {
    display: none;
}

.toggle-slider {
    width: 50px;
    height: 24px;
    background: var(--border);
    border-radius: 12px;
    position: relative;
    transition: all 0.3s ease;
}

.toggle-slider::before {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    background: white;
    border-radius: 50%;
    top: 2px;
    left: 2px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.toggle-checkbox:checked + .toggle-slider {
    background: var(--primary);
}

.toggle-checkbox:checked + .toggle-slider::before {
    transform: translateX(26px);
}

.toggle-text {
    font-size: 0.95rem;
}

.toggle-description {
    margin-left: 66px;
}

/* Current Member Info */
.current-member-info {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.member-info-card {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.member-info-card .member-avatar {
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
}

.member-details {
    flex: 1;
}

.member-details h6 {
    margin: 0 0 0.25rem 0;
    color: var(--dark);
}

.member-details p {
    margin: 0 0 0.25rem 0;
    color: var(--text-muted);
    font-size: 0.85rem;
}

.member-stats {
    display: flex;
    gap: 1.5rem;
}

.member-stats .stat {
    text-align: center;
}

.member-stats .stat-value {
    display: block;
    font-weight: 600;
    color: var(--primary);
    font-size: 1.1rem;
}

.member-stats .stat-label {
    font-size: 0.75rem;
    color: var(--text-muted);
}

/* Improved Preview Styles */
.kpi-list-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border);
}

.kpi-list-header h6 {
    margin: 0;
    color: var(--dark);
}

.kpi-count {
    background: var(--primary-light);
    color: var(--primary);
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.preview-kpi-item {
    background: var(--surface-soft);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    border: 1px solid var(--border);
    transition: all 0.3s ease;
}

.preview-kpi-item:hover {
    border-color: var(--primary-light);
}

.kpi-main-preview {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 0.75rem;
}

.kpi-number {
    background: var(--primary);
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 600;
    flex-shrink: 0;
}

.kpi-content {
    flex: 1;
}

.kpi-parameter {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.kpi-details {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.kpi-weight {
    background: var(--primary);
    color: white;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}

.kpi-target {
    font-size: 0.8rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
}

.kpi-meta-preview {
    display: flex;
    gap: 1rem;
    font-size: 0.75rem;
    margin-bottom: 0.5rem;
}

.kpi-category,
.kpi-formula {
    background: var(--surface);
    padding: 0.3rem 0.6rem;
    border-radius: 6px;
    display: flex;
    align-items: center;
    color: var(--text-muted);
}

.kpi-description {
    background: var(--surface);
    padding: 0.75rem;
    border-radius: 6px;
    border-left: 3px solid var(--info);
}

.kpi-description p {
    margin: 0;
    font-size: 0.8rem;
    color: var(--text);
    line-height: 1.4;
}

.preview-summary {
    background: var(--surface);
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
    border: 1px solid var(--border);
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.summary-row:last-child {
    margin-bottom: 0;
}

.summary-validation {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border);
}

.validation-text {
    font-size: 0.8rem;
    color: var(--warning);
}

/* Responsive Design */
@media (max-width: 768px) {
    .member-info-card {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }

    .member-stats {
        justify-content: center;
    }

    .kpi-main-preview {
        flex-direction: column;
        gap: 0.75rem;
    }

    .kpi-details {
        flex-wrap: wrap;
    }

    .kpi-meta-preview {
        flex-direction: column;
        gap: 0.5rem;
    }
}
/* Priority Alert */
/* Ringkasan revisi — SATU baris, tinggi tetap berapa pun jumlah yang ditolak.
   Daftar namanya ada di tabel, bukan di sini. */
.revisi-ringkas {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-200);
    border-left: 4px solid var(--hcis-red-500);
    border-radius: 10px;
    padding: 0.7rem 1rem;
    font-size: 0.9rem;
    color: var(--hcis-red-900);
}

.revisi-ringkas > i {
    color: var(--hcis-red-500);
    font-size: 1.05rem;
    flex: 0 0 auto;
}

/* Baris/kartu anggota yang ditolak. Garis kiri tebal supaya tetap terbaca
   bagi yang kesulitan membedakan warna — sorotan tidak boleh bergantung
   pada warna saja. */
.table-row.baris-ditolak {
    background: var(--hcis-red-50);
    box-shadow: inset 3px 0 0 var(--hcis-red-500);
}

.table-row.baris-ditolak:hover {
    background: #fde8e8;
}

.team-member-card.baris-ditolak {
    background: var(--hcis-red-50);
    border-color: var(--hcis-red-200);
    box-shadow: inset 3px 0 0 var(--hcis-red-500);
}

/* Tombol Revisi menggantikan tombol Edit saat pengajuan ditolak. Berlabel
   teks, bukan ikon saja — "perbaiki yang ditolak" bukan tindakan yang boleh
   ditebak dari gambar pensil. */
.btn-action.btn-revisi-aksi {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    width: auto;
    padding: 0.35rem 0.75rem;
    background: var(--hcis-red-500);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
}

.btn-action.btn-revisi-aksi:hover {
    background: var(--hcis-red-600);
}

/* Banner alasan umum di dalam form anggota — disalin dari form individu
   (formKPI.vue) supaya leader melihat hal yang sama dengan karyawannya. */
.global-reject-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-200);
    border-left: 4px solid var(--hcis-red-500);
    border-radius: 10px;
    padding: 0.85rem 1rem;
    margin-bottom: 1rem;
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

.global-reject-param {
    margin: 0.35rem 0 0;
    font-size: 0.8rem;
    color: #9a3412;
}

.priority-alert {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    border: 1px solid #ffc107;
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
}

.alert-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.alert-content i {
    color: #ffc107;
    font-size: 1.5rem;
}

.alert-text {
    flex: 1;
    color: #856404;
    font-size: 0.95rem;
}

.btn-alert {
    background: #ffc107;
    color: #856404;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-alert:hover {
    background: #e0a800;
    transform: translateY(-1px);
}

/* Table Checkbox */
.table-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.mobile-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.table-row.no-approver {
    background: rgba(255, 205, 205, 0.3);
}

/* No KPI Styles */
.table-row.no-kpi {
    background: rgba(255, 243, 205, 0.3);
}

.no-kpi-badge {
    background: #fff3cd !important;
    color: #856404 !important;
}

.no-kpi-text {
    color: #856404 !important;
    font-weight: 600;
}

/* Selection Table */
.selection-table .table-row {
    cursor: pointer;
    transition: all 0.3s ease;
}

.selection-table .table-row:hover:not(.disabled) {
    background: rgba(99, 102, 241, 0.05);
}

.selection-table .table-row.disabled {
    opacity: 0.6;
    cursor: not-allowed;
    background: var(--surface-soft);
}

.selection-table .table-row.selected:not(.disabled) {
    background: rgba(99, 102, 241, 0.1);
    border-left: 4px solid var(--primary);
}

.status-submitted {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-available {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
}

.status-danger {
    background: rgba(241, 99, 99, 0.1);
    color: var(--danger);
}

/* Mobile Selection */
.member-selection-grid-mobile {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.member-selection-card-mobile {
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.member-selection-card-mobile:hover:not(.disabled) {
    border-color: var(--primary-light);
}

.member-selection-card-mobile.selected:not(.disabled) {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.member-selection-card-mobile.disabled {
    opacity: 0.6;
    cursor: not-allowed;
    background: var(--surface-soft);
}

.selection-checkbox-mobile {
    margin-top: 0.25rem;
}

.selection-checkbox-mobile input {
    width: 20px;
    height: 20px;
}

.selection-content-mobile {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.selection-avatar-mobile {
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

.selection-info-mobile {
    flex: 1;
}

.selection-info-mobile .member-name {
    font-size: 0.9rem;
    margin: 0 0 0.25rem 0;
}

.selection-info-mobile .member-position {
    font-size: 0.75rem;
    margin: 0 0 0.5rem 0;
    color: var(--text-muted);
}

.member-division-mobile {
    font-size: 0.7rem;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
}

.submission-info-mobile {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.7rem;
    color: var(--success);
}

/* Member Tabs Slider */
.member-tabs-slider {
    position: relative;
    background: var(--surface-soft);
    border-radius: 12px;
    padding: 1rem;
}

.tabs-container {
    display: flex;
    gap: 0.5rem;
    overflow-x: auto;
    scroll-behavior: smooth;
    padding-bottom: 0.5rem;
}

.tabs-container::-webkit-scrollbar {
    display: none;
}

.member-tab-slider {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
    flex-shrink: 0;
    min-width: 180px;
}

.member-tab-slider:hover {
    border-color: var(--primary-light);
}

.member-tab-slider.active {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.tab-avatar-slider {
    width: 32px;
    height: 32px;
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

.tab-name-slider {
    font-size: 0.85rem;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
}

.slider-controls {
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    transform: translateY(-50%);
    display: flex;
    justify-content: space-between;
    pointer-events: none;
}

.slider-btn {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    pointer-events: all;
    box-shadow: var(--shadow);
}

.slider-btn:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.slider-btn.prev {
    margin-left: -16px;
}

.slider-btn.next {
    margin-right: -16px;
}

/* Compact Progress Stats */
.compact-progress-stats {
    margin-bottom: 1.5rem;
}

.stats-grid-compact {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.75rem;
}

.stat-card-compact {
    background: var(--surface);
    padding: 1rem;
    border-radius: 12px;
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.stat-card-compact:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.stat-card-compact.active {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.stat-icon-compact {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
    flex-shrink: 0;
}

.stat-icon-compact.all {
    background: linear-gradient(135deg, var(--secondary), var(--hcis-slate-400));
}

.stat-icon-compact.waiting {
    background: linear-gradient(135deg, var(--info), var(--hcis-cyan-500));
}

.stat-icon-compact.rejected {
    background: linear-gradient(135deg, var(--danger), var(--hcis-red-500));
}

.stat-icon-compact.approved {
    background: linear-gradient(135deg, var(--success), var(--hcis-emerald-500));
}

.stat-content-compact h4 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
}

.stat-content-compact p {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin: 0;
}

/* Step 3: Verification & Preview Styles */
.step-3-header {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
}

.step-3-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--dark);
}

.step-3-subtitle {
    font-size: 0.875rem;
    line-height: 1.5;
}

.verification-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-top: 1rem;
}

@media (max-width: 768px) {
    .verification-stats-grid {
        grid-template-columns: 1fr;
    }
}

.verification-stat-card {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.25s ease;
}

.verification-stat-card.valid {
    border-color: rgba(16, 185, 129, 0.3);
    background: rgba(16, 185, 129, 0.04);
}

.verification-stat-card.invalid {
    border-color: rgba(239, 68, 68, 0.3);
    background: rgba(239, 68, 68, 0.04);
}

.stat-icon-wrapper {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.bg-primary-soft {
    background: rgba(99, 102, 241, 0.12);
    color: var(--primary);
}

.bg-info-soft {
    background: rgba(14, 165, 233, 0.12);
    color: var(--info);
}

.bg-success-soft {
    background: rgba(16, 185, 129, 0.12);
    color: var(--success);
}

.bg-danger-soft {
    background: rgba(239, 68, 68, 0.12);
    color: var(--danger);
}

.stat-info {
    flex: 1;
    min-width: 0;
}

.stat-label {
    font-size: 0.775rem;
    color: var(--text-muted);
    font-weight: 500;
    display: block;
    margin-bottom: 0.15rem;
}

.stat-value {
    font-size: 1.2rem;
    font-weight: 700;
    margin: 0;
    color: var(--dark);
    line-height: 1.2;
}

.stat-value small {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--text-muted);
}

/* Start Periode Notice Card */
.start-periode-notice-card {
    background: rgba(99, 102, 241, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 10px;
    padding: 1rem;
}

.badge-start-summary {
    display: inline-flex;
    align-items: center;
    font-size: 12px;
    background: var(--surface);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 6px;
    padding: 3px 8px;
    color: var(--dark);
}

/* Preview Grid & Cards */
.preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
    gap: 1.25rem;
}

@media (max-width: 576px) {
    .preview-grid {
        grid-template-columns: 1fr;
    }
}

.preview-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1.25rem;
    transition: all 0.25s ease;
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-sm);
}

.preview-card:hover {
    box-shadow: var(--shadow);
    border-color: rgba(99, 102, 241, 0.35);
}

.preview-card.card-invalid-weight {
    border-color: rgba(239, 68, 68, 0.4);
    background: linear-gradient(180deg, var(--surface) 0%, rgba(239, 68, 68, 0.02) 100%);
}

.preview-header {
    display: flex;
    align-items: flex-start;
    gap: 0.875rem;
    margin-bottom: 1rem;
    padding-bottom: 0.875rem;
    border-bottom: 1px solid var(--border);
}

.preview-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), #4338ca);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.25);
}

.preview-info {
    flex: 1;
    min-width: 0;
}

.member-preview-name {
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--dark);
    word-break: break-word;
}

.member-id-pill {
    font-size: 11px;
    background: var(--surface-soft);
    border: 1px solid var(--border);
    color: var(--text-muted);
    padding: 1px 6px;
    border-radius: 4px;
    font-weight: 600;
}

.member-preview-position {
    font-size: 0.8rem;
    line-height: 1.3;
}

.btn-edit-shortcut {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 6px;
    transition: all 0.2s ease;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
}

.btn-edit-shortcut:hover {
    background: rgba(99, 102, 241, 0.08);
    color: var(--primary);
    border-color: rgba(99, 102, 241, 0.3);
}

/* KPI List in Card */
.preview-kpis {
    flex: 1;
    margin-bottom: 1rem;
}

.kpi-list-container {
    max-height: 280px;
    overflow-y: auto;
    padding-right: 2px;
}

.preview-kpi-item {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.75rem 0.875rem;
    margin-bottom: 0.5rem;
    transition: all 0.2s ease;
}

.preview-kpi-item:hover {
    border-color: rgba(99, 102, 241, 0.3);
    background: var(--surface);
}

.kpi-main-preview {
    display: flex;
    align-items: flex-start;
    gap: 0.625rem;
}

.kpi-index-badge {
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 700;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 1px;
}

.kpi-parameter {
    font-size: 0.875rem;
    line-height: 1.35;
}

.kpi-weight-badge {
    background: rgba(99, 102, 241, 0.12);
    color: var(--primary);
    border: 1px solid rgba(99, 102, 241, 0.2);
    padding: 2px 7px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.kpi-meta-tags {
    margin-top: 0.25rem;
}

.meta-tag {
    font-size: 11px;
    padding: 1px 6px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    line-height: 1.3;
}

.tag-category {
    background: rgba(139, 92, 246, 0.08);
    color: #7c3aed;
    border: 1px solid rgba(139, 92, 246, 0.2);
}

.tag-target {
    background: rgba(14, 165, 233, 0.08);
    color: #0284c7;
    border: 1px solid rgba(14, 165, 233, 0.2);
}

.tag-unit {
    background: rgba(100, 116, 139, 0.08);
    color: #475569;
    border: 1px solid rgba(100, 116, 139, 0.2);
}

/* Preview Summary & Progress Bar */
.preview-summary {
    padding-top: 0.75rem;
    border-top: 1px solid var(--border);
    margin-top: auto;
}

.summary-validation.alert-warning-soft {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.25);
    color: #b45309;
    border-radius: 6px;
    padding: 0.4rem 0.6rem;
    font-size: 11.5px;
    font-weight: 500;
    display: flex;
    align-items: center;
}

/* Confirmation Agreement Card */
.confirmation-agreement-card {
    background: var(--surface);
    border: 1.5px solid rgba(99, 102, 241, 0.25);
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    box-shadow: var(--shadow-sm);
    transition: all 0.25s ease;
}

.confirmation-agreement-card:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.08);
}

.checklist-checkbox-custom {
    width: 22px;
    height: 22px;
    accent-color: var(--primary);
    cursor: pointer;
    border-radius: 6px;
}

.cursor-pointer {
    cursor: pointer;
}

/* Modal Footer */
.modal-footer {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    padding-top: 1rem;
    /* border-top: 1px solid var(--border); */
}

/* Confirmation Content */
.confirmation-content {
    text-align: center;
    padding: 1rem;
}

/* Add KPI Button */
.btn-add {
    color: var(--success);
    background: rgba(16, 185, 129, 0.1);
}

.btn-add:hover {
    background: rgba(16, 185, 129, 0.2);
}

/* Approval Progress in Modal */
.approval-progress-section {
    margin-bottom: 1.5rem;
}
.approval-progress-section.nothing {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 1rem;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface-soft);
}

/* Responsive Design */
@media (max-width: 768px) {
    .stats-grid-compact {
        grid-template-columns: repeat(2, 1fr);
    }

    .member-selection {
        width: 100%;
        display: flex;
        justify-content: start;
        align-items: center;
    }
    .slider-controls {
        display: none;
    }

    .modal-footer {
        justify-content: space-between;
    }

    .member-tab-slider {
        min-width: 160px;
    }

    .kpi-main-preview {
        flex-direction: column;
        gap: 0.5rem;
    }

    .kpi-details {
        align-self: flex-start;
    }

    .preview-total {
        flex-direction: column;
        gap: 0.25rem;
    }
}

@media (max-width: 576px) {
    .alert-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }

    .btn-alert {
        align-self: stretch;
        text-align: center;
    }

    .selection-content-mobile {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}

/* Existing CSS variables and styles remain the same, just add these new styles */
</style>

<style scoped>
/* CSS Variables */
*,
*::before,
*::after {
    --primary: var(--hcis-indigo-500);
    --primary-dark: var(--hcis-indigo-600);
    --primary-light: var(--hcis-indigo-300);
    --secondary: #949faf;
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

.btn-danger {
    background: var(--danger);
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
    /* border-top: 3px solid var(--primary); */
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.loading-spinner.upload {
    animation: none;
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

/* Schedule Selection */
.schedule-selection-container {
    padding: 1rem 0;
}

.schedule-list-container {
    background: var(--surface);
    border-radius: 12px;
    padding: 2rem;
    box-shadow: var(--shadow);
    margin-top: 1rem;
}

.schedule-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.schedule-card {
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    cursor: pointer;
}

.schedule-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.schedule-card.active {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.schedule-card.historical {
    border-color: var(--secondary);
    background: var(--surface-soft);
}

.schedule-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.schedule-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.schedule-card.historical .schedule-icon {
    background: var(--secondary);
}

.schedule-info {
    flex: 1;
}

.schedule-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
}

.schedule-period {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.9rem;
}

.schedule-status .status-badge {
    padding: 0.4rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.active {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-badge.historical {
    background: rgba(100, 116, 139, 0.1);
    color: var(--secondary);
}

.schedule-dates {
    color: var(--secondary) !important;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.date-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    /* color: var(--text-muted); */
}

.schedule-actions {
    display: flex;
    justify-content: flex-end;
}

/* Header Styles */
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
    margin-bottom: 0.6rem;
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
    display: flex;
    align-items: center;
    justify-content: flex-end;
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

.btn-secondary {
    background-color: var(--secondary);
}

.btn-outline {
    background: transparent;
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
}

.btn-outline:hover {
    background: rgba(255, 255, 255, 0.1);
}

.btn-modern:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Team Overview Styles */
.team-actions-bar {
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

.bulk-actions-header {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

/* Table Styles */
.table-responsive-wrapper {
    width: 100%;
    overflow-x: auto;
    border-radius: 12px;
    box-shadow: var(--shadow);
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

.table-row {
    transition: background-color 0.2s ease;
}

.table-row:hover {
    background-color: var(--surface-soft);
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

.status-draft {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.status-waiting {
    background: rgba(6, 182, 212, 0.1);
    color: var(--info);
}

.status-approved {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-rejected {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.status-resubmitted {
    background: rgba(139, 92, 246, 0.1);
    color: #8b5cf6;
}

.status-not-eligible {
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #cbd5e1;
}

.table-row.row-not-eligible {
    background-color: #fafbfc;
}

.table-row.row-not-eligible.disabled {
    cursor: not-allowed;
}

.member-selection-card-mobile.card-not-eligible {
    background-color: #f8fafc;
    opacity: 0.85;
    cursor: not-allowed;
    border-color: #e2e8f0;
}

/* Schedule Badges */
.schedule-cell-info {
    display: flex;
    flex-direction: column;
    gap: 3px;
    align-items: flex-start;
}

.badge-jadwal-type {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 600;
    padding: 2.5px 8px;
    border-radius: 6px;
    line-height: 1.3;
    white-space: nowrap;
}

.badge-jadwal-perorangan {
    background: rgba(99, 102, 241, 0.1);
    color: #4f46e5;
    border: 1px solid rgba(99, 102, 241, 0.25);
}

.badge-jadwal-global {
    background: rgba(14, 165, 233, 0.1);
    color: #0284c7;
    border: 1px solid rgba(14, 165, 233, 0.25);
}

.badge-jadwal-none {
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #cbd5e1;
}

.badge-start-period {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 600;
    padding: 2.5px 8px;
    border-radius: 6px;
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.25);
    line-height: 1.3;
    white-space: nowrap;
}

.schedule-sub-text {
    font-size: 11px;
    line-height: 1.3;
}

/* Avatar Styles */
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

.avatar-lg {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1.5rem;
    flex-shrink: 0;
}

/* Employee Cell */
.employee-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
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

/* Count Badge */
.count-badge {
    background: var(--primary-light);
    color: var(--primary);
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Weight Value */
.weight-value {
    font-weight: 600;
    color: var(--primary);
    font-size: 0.875rem;
}

/* Action Cell */
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

.btn-view {
    color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
}

.btn-view:hover {
    background: rgba(99, 102, 241, 0.2);
}

.btn-edit {
    color: var(--warning);
    background: rgba(245, 158, 11, 0.1);
}

.btn-edit:hover {
    background: rgba(245, 158, 11, 0.2);
}

/* Mobile Card Views */
.team-members-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.team-member-card {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    transition: all 0.3s ease;
}

.team-member-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
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
    font-size: 0.9rem;
    flex-shrink: 0;
}

.member-info {
    flex: 1;
    min-width: 0;
}

.member-name {
    font-size: 1rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
}

.member-position {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin: 0 0 0.5rem 0;
}

.member-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.75rem;
}

.member-id {
    color: var(--primary);
    font-weight: 500;
}

.member-division {
    color: var(--text-muted);
}

.member-status {
    flex-shrink: 0;
}

.member-kpi-summary {
    margin-bottom: 1rem;
}

.kpi-stats {
    display: flex;
    gap: 1rem;
    justify-content: space-between;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.stat-item i {
    color: var(--primary);
}

.member-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

/* Progress Cards Grid */
.progress-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.progress-card {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    transition: all 0.3s ease;
}

.progress-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.card-content {
    margin-bottom: 1rem;
}

.progress-stats {
    display: flex;
    gap: 1rem;
    margin-bottom: 0.75rem;
}

.progress-stats .stat {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.approver-info,
.update-info {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-bottom: 0.25rem;
}

.card-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

/* Bulk Creation Styles */
.bulk-creation-header {
    background: var(--surface);
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: var(--shadow);
    margin-bottom: 1.5rem;
}

.bulk-step-content {
    background: var(--surface);
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: var(--shadow);
}

.step-header {
    margin-bottom: 1.5rem;
}

.step-header h5 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-size: 1.1rem;
}

.step-header p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

/* Member Selection */
.member-selection-section {
    margin-bottom: 1.5rem;
}

.selection-actions {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.btn-selection {
    padding: 0.5rem 1rem;
    border: 1px solid var(--border);
    background: var(--surface-soft);
    color: var(--text);
    border-radius: 6px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-selection:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.member-selection-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
}

.member-selection-card {
    background: var(--surface-soft);
    border: 2px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.member-selection-card:hover {
    border-color: var(--primary-light);
}

.member-selection-card.selected {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.member-selection-card.disabled {
    opacity: 0.6;
    cursor: not-allowed;
    background: var(--surface-soft);
}

.selection-checkbox {
    flex-shrink: 0;
}

.selection-avatar {
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

.selection-info {
    flex: 1;
    min-width: 0;
}

.selection-info .member-name {
    font-size: 0.9rem;
    margin: 0 0 0.25rem 0;
}

.selection-info .member-position {
    font-size: 0.75rem;
    margin: 0;
}

.submission-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.7rem;
    color: var(--success);
    margin-top: 0.25rem;
}

/* Member Tabs */
.member-tabs-section {
    background: var(--surface-soft);
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.member-tabs {
    display: flex;
    gap: 0.5rem;
    overflow-x: auto;
}

.member-tab {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
    flex-shrink: 0;
}

.member-tab:hover {
    border-color: var(--primary-light);
}

.member-tab.active {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.tab-avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 600;
}

.tab-name {
    font-size: 0.85rem;
    font-weight: 500;
}

/* KPI Configuration */
.kpi-configuration-section {
    background: var(--surface);
    padding: 1.5rem;
    border-radius: 8px;
    border: 1px solid var(--border);
}

/* =========================================================================
   Step 3: Preview & Verification Redesign Styles
   ========================================================================= */
.step-3-header {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.25rem 1.5rem;
}

.step-3-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.step-3-subtitle {
    font-size: 0.85rem;
    color: #64748b;
    line-height: 1.5;
}

/* Verification Summary Stats */
.verification-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.verification-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    transition: all 0.2s ease;
}

.verification-stat-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.verification-stat-card.valid {
    border-color: #86efac;
    background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);
}

.verification-stat-card.invalid {
    border-color: #fca5a5;
    background: linear-gradient(135deg, #ffffff 0%, #fef2f2 100%);
}

.stat-icon-wrapper {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.bg-primary-soft {
    background: #eef2ff;
}

.bg-info-soft {
    background: #f0f9ff;
}

.bg-success-soft {
    background: #f0fdf4;
}

.bg-danger-soft {
    background: #fef2f2;
}

.stat-info {
    flex: 1;
    min-width: 0;
}

.stat-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    display: block;
    margin-bottom: 2px;
}

.stat-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    line-height: 1.2;
}

.stat-value small {
    font-size: 0.8rem;
    font-weight: 500;
    color: #64748b;
}

/* Notice Variasi Start Periode */
.start-periode-notice-card {
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 12px;
    padding: 1rem 1.25rem;
}

.badge-start-summary {
    background: #ffffff;
    border: 1px solid #7dd3fc;
    border-radius: 8px;
    padding: 0.35rem 0.65rem;
    font-size: 0.8rem;
    color: #0369a1;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* Member Preview Grid */
.preview-section {
    margin-bottom: 1.5rem;
}

.preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 1.25rem;
    max-height: 52rem;
    overflow-y: auto;
    padding: 2px;
}

.preview-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.02);
    transition: all 0.2s ease;
    position: relative;
}

.preview-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    transform: translateY(-2px);
}

.preview-card.card-invalid-weight {
    border-color: #fca5a5;
    background: linear-gradient(180deg, #ffffff 0%, #fffbfb 100%);
    box-shadow: 0 0 0 1px #fca5a5 inset;
}

/* Preview Header */
.preview-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}

.preview-user-profile {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
    flex: 1;
}

.preview-avatar {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.25);
}

.preview-user-details {
    min-width: 0;
    flex: 1;
    overflow: hidden;
}

.preview-name-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
    margin-bottom: 2px;
}

.member-preview-name {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    white-space: normal;
    word-break: break-word;
    line-height: 1.3;
}

.member-id-pill {
    font-size: 0.72rem;
    font-weight: 600;
    color: #475569;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 1px 6px;
    white-space: nowrap;
}

.member-preview-position {
    font-size: 0.78rem;
    color: #64748b;
    margin: 0;
    line-height: 1.35;
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
}

.member-preview-position .pos-divider {
    color: #cbd5e1;
    font-weight: bold;
}

.member-preview-position .preview-division {
    color: #94a3b8;
    font-weight: 500;
}

.preview-header-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.35rem;
    flex-shrink: 0;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.25rem 0.6rem;
    border-radius: 20px;
    white-space: nowrap;
    line-height: 1;
}

.status-badge.status-approved {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
}

.status-badge.status-rejected {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.btn-edit-shortcut {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #4f46e5;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: 8px;
    padding: 0.22rem 0.55rem;
    cursor: pointer;
    transition: all 0.2s ease;
    line-height: 1;
}

.btn-edit-shortcut:hover {
    background: #e0e7ff;
    color: #3730a3;
    border-color: #a5b4fc;
    transform: translateY(-1px);
}

/* Meta Chips */
.preview-meta-chips {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
    padding-top: 0.6rem;
    border-top: 1px dashed #f1f5f9;
}

.badge-jadwal-type {
    display: inline-flex;
    align-items: center;
    font-size: 0.73rem;
    font-weight: 600;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    line-height: 1.2;
}

.badge-jadwal-perorangan {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}

.badge-jadwal-global {
    background: #f8fafc;
    color: #475569;
    border: 1px solid #e2e8f0;
}

.badge-start-period {
    display: inline-flex;
    align-items: center;
    font-size: 0.73rem;
    font-weight: 600;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    background: #faf5ff;
    color: #7e22ce;
    border: 1px solid #e9d5ff;
    line-height: 1.2;
}

.badge-custom-kpi {
    display: inline-flex;
    align-items: center;
    font-size: 0.73rem;
    font-weight: 600;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
    line-height: 1.2;
}

/* KPI Parameter Items List */
.preview-kpis {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    flex: 1;
}

.kpi-list-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 0.35rem;
    border-bottom: 1px solid #f1f5f9;
}

.kpi-list-title {
    font-size: 0.8rem;
    font-weight: 700;
    color: #334155;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.kpi-count-badge {
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748b;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 1px 6px;
}

.kpi-list-container {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-height: 220px;
    overflow-y: auto;
    padding-right: 4px;
}

.empty-kpi-preview {
    padding: 1.25rem 1rem;
    text-align: center;
    color: #94a3b8;
    font-size: 0.82rem;
    background: #f8fafc;
    border: 1px dashed #e2e8f0;
    border-radius: 10px;
}

.preview-kpi-item {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    background: #f8fafc;
    border: 1px solid #edf2f7;
    border-radius: 10px;
    padding: 0.6rem 0.75rem;
    transition: all 0.15s ease;
}

.preview-kpi-item:hover {
    background: #f1f5f9;
    border-color: #e2e8f0;
}

.kpi-item-index {
    width: 20px;
    height: 20px;
    border-radius: 6px;
    background: #e2e8f0;
    color: #475569;
    font-size: 0.7rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 1px;
}

.kpi-item-content {
    flex: 1;
    min-width: 0;
}

.kpi-item-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.35rem;
}

.kpi-item-title {
    font-size: 0.84rem;
    font-weight: 600;
    color: #1e293b;
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.kpi-weight-badge {
    font-size: 0.75rem;
    font-weight: 700;
    color: #4f46e5;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: 6px;
    padding: 1px 6px;
    white-space: nowrap;
    flex-shrink: 0;
}

.kpi-meta-tags {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.meta-tag {
    display: inline-flex;
    align-items: center;
    font-size: 0.7rem;
    font-weight: 500;
    border-radius: 4px;
    padding: 1px 5px;
    white-space: nowrap;
}

.tag-category {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #dcfce7;
}

.tag-target {
    background: #fefce8;
    color: #854d0e;
    border: 1px solid #fef08a;
}

.tag-unit {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}

/* Preview Summary & Progress Bar */
.preview-summary {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.75rem 0.9rem;
    margin-top: auto;
}

.summary-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #64748b;
}

.summary-weight-val {
    font-size: 0.92rem;
    font-weight: 700;
}

.summary-weight-val.weight-valid {
    color: #16a34a;
}

.summary-weight-val.weight-invalid {
    color: #dc2626;
}

.weight-progress {
    height: 6px;
    background: #e2e8f0;
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 0.25rem;
}

.summary-validation {
    display: flex;
    align-items: center;
    font-size: 0.75rem;
    color: #b45309;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 6px;
    padding: 0.35rem 0.55rem;
    margin-top: 0.4rem;
}

.confirmation-agreement-card {
    background: #ffffff;
    border: 1.5px solid #cbd5e1;
    border-radius: 14px;
    padding: 1.15rem 1.4rem;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.confirmation-agreement-card:hover {
    border-color: #94a3b8;
}

@media (max-width: 768px) {
    .preview-grid {
        grid-template-columns: 1fr;
    }
}

/* Step Actions */
.step-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
}

/* Progress Stats */
.progress-stats-section {
    margin-bottom: 1.5rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.stat-card {
    background: var(--surface);
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    gap: 1rem;
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
}

.stat-icon.waiting {
    background: linear-gradient(135deg, var(--info), var(--hcis-cyan-500));
}

.stat-icon.rejected {
    background: linear-gradient(135deg, var(--danger), var(--hcis-red-500));
}

.stat-icon.approved {
    background: linear-gradient(135deg, var(--success), var(--hcis-emerald-500));
}

.stat-icon.resubmitted {
    background: linear-gradient(135deg, #8b5cf6, var(--hcis-purple-500));
}

.stat-content h4 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
}

.stat-content p {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin: 0;
}

/* Progress Filters */
.progress-filters {
    display: flex;
    justify-content: flex-end;
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

/* Member Detail Modal Styles */
.member-detail-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 1.5rem;
    background: var(--surface-soft);
    border-radius: 12px;
    margin-bottom: 1.5rem;
}

.member-detail-info {
    flex: 1;
}

.member-detail-info h5 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-weight: 600;
}

.member-detail-info p {
    margin: 0.25rem 0;
    color: var(--text-muted);
}

.member-id {
    font-size: 0.85rem;
    color: var(--primary);
    font-weight: 500;
}

.member-status-detail {
    flex-shrink: 0;
}

.member-kpi-list {
    margin-bottom: 1.5rem;
}

.kpi-list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.kpi-list-header h6 {
    margin: 0;
    color: var(--dark);
}

.kpi-items {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.kpi-item-detail {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.kpi-item-detail:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow);
}

.kpi-item-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--surface-soft);
    border-bottom: 1px solid var(--border);
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
    font-size: 0.75rem;
    font-weight: 600;
    flex-shrink: 0;
}

.kpi-main-info {
    flex: 1;
}

.kpi-main-info h6 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-size: 0.95rem;
}

.kpi-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
}

.kpi-meta .category {
    background: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-weight: 500;
}

.kpi-meta .weight {
    color: var(--primary);
    font-weight: 600;
}

.kpi-item-content {
    padding: 1rem;
}

.kpi-item-content p {
    margin: 0.5rem 0;
    font-size: 0.9rem;
    color: var(--text);
}

.kpi-item-content p:last-child {
    margin-bottom: 0;
}

.rejection-reason {
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-200);
    border-radius: 6px;
    padding: 1rem;
    margin-top: 1rem;
}

.rejection-reason strong {
    color: var(--danger);
    display: block;
    margin-bottom: 0.5rem;
}

.rejection-reason p {
    margin: 0;
    color: var(--hcis-red-900);
    font-size: 0.85rem;
    line-height: 1.4;
}

.empty-kpis {
    text-align: center;
    padding: 3rem 2rem;
    background: var(--surface-soft);
    border-radius: 8px;
    border: 2px dashed var(--border);
}

.empty-kpis .empty-icon {
    font-size: 2.5rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-kpis p {
    color: var(--text-muted);
    margin-bottom: 1.5rem;
}

.submission-info {
    background: var(--surface-soft);
    padding: 1.5rem;
    border-radius: 8px;
}

.kpi-summary {
    display: flex;
    gap: 2rem;
}

.summary-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.summary-item strong {
    font-size: 0.9rem;
    color: var(--text-muted);
}

.summary-item span {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
}

/* Empty States */

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
    .schedule-grid {
        grid-template-columns: 1fr;
    }

    .preview-avatar {
        display: none;
    }

    .kpi-configuration-section {
        padding: 1rem 0;
        border: 0;
    }

    .member-header {
        align-items: center;
    }

    .team-members-grid,
    .progress-cards-grid,
    .preview-grid {
        grid-template-columns: 1fr;
    }

    .member-selection-grid {
        width: 100%;
        display: flex;
        justify-content: start;
        grid-template-columns: 1fr;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .bulk-actions-header {
        flex-direction: column;
        gap: 0.5rem;
    }

    .member-header {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }

    .member-status {
        align-self: center;
    }

    .kpi-stats {
        justify-content: center;
    }

    .member-actions {
        justify-content: center;
    }

    .member-detail-header {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }

    .kpi-summary {
        justify-content: center;
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

    .step-actions .d-flex {
        flex-direction: column;
        gap: 0.5rem;
    }

    .step-actions .btn-modern {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .team-actions-bar,
    .bulk-creation-header,
    .bulk-step-content {
        padding: 1rem;
    }

    .schedule-list-container {
        padding: 1rem;
    }

    .member-tabs {
        flex-direction: column;
    }

    .member-tab {
        justify-content: flex-start;
    }

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
    /* border-top: 3px solid var(--primary); */
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
    box-shadow:
        0 0 10px rgba(255, 107, 53, 0.3),
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
    content: '⚠️';
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

/* Quick Fill Panel in Target Modal */
.quick-fill-panel {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1rem 1.25rem;
    transition: all 0.2s ease;
}

.quick-fill-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 0.85rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid #edf2f7;
}

.quick-fill-icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: #fef3c7;
    color: #d97706;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.quick-fill-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #1e293b;
    display: block;
    line-height: 1.2;
}

.quick-fill-subtitle {
    font-size: 0.75rem;
    color: #64748b;
    display: block;
    margin-top: 2px;
}

.badge-unit-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.6rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 0.75rem;
    color: #475569;
}

.quick-fill-controls {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.65rem;
}

.quick-fill-input-box {
    display: flex;
    align-items: center;
    background: #ffffff;
    border: 1.5px solid #cbd5e1;
    border-radius: 8px;
    padding: 0 0.5rem;
    transition: all 0.2s ease;
    flex: 1 1 200px;
    max-width: 260px;
    min-height: 36px;
}

.quick-fill-input-box:focus-within {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

.quick-fill-input-box .input-icon {
    color: #94a3b8;
    font-size: 0.75rem;
    margin-right: 0.4rem;
}

.quick-fill-input-box .quick-input {
    border: none;
    outline: none;
    background: transparent;
    font-size: 0.85rem;
    font-weight: 500;
    color: #1e293b;
    width: 100%;
    padding: 0.4rem 0;
}

.quick-fill-input-box .input-unit-tag {
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    background: #f1f5f9;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    margin-left: 0.35rem;
    white-space: nowrap;
}

.quick-fill-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.btn-quick {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.42rem 0.85rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.btn-quick:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-quick-primary {
    background: #6366f1;
    color: #ffffff;
    box-shadow: 0 1px 3px rgba(99, 102, 241, 0.25);
}

.btn-quick-primary:hover:not(:disabled) {
    background: #4f46e5;
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.35);
}

.btn-quick-secondary {
    background: #ffffff;
    color: #4f46e5;
    border-color: #c7d2fe;
}

.btn-quick-secondary:hover:not(:disabled) {
    background: #eef2ff;
    border-color: #a5b4fc;
}

.btn-quick-ghost {
    background: transparent;
    color: #64748b;
}

.btn-quick-ghost:hover:not(:disabled) {
    background: #f1f5f9;
    color: #dc2626;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    background: var(--surface);
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
    /* color: white; */
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
    box-shadow:
        0 0 0 1px #ff6b35 inset,
        0 4px 12px rgba(255, 107, 53, 0.15);
    animation: pulse-revision 2s ease-in-out infinite;

    /* Pastikan revision tetap interaktif */
    cursor: pointer;
    pointer-events: auto;
    opacity: 1;
    filter: none;
}

/* Badge/indicator revision */
.compact-card.revision::before {
    content: '⚠';
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
        box-shadow:
            0 0 0 1px #ff6b35 inset,
            0 4px 12px rgba(255, 107, 53, 0.15);
    }
    50% {
        box-shadow:
            0 0 0 1px #ff6b35 inset,
            0 4px 20px rgba(255, 107, 53, 0.3);
    }
}

/* Hover effect untuk revision */
.compact-card.revision:hover {
    background: linear-gradient(135deg, var(--surface-soft) 0%, #fff1eb 100%);
    border-color: #ff5722;
    transform: translateY(-2px);
    box-shadow:
        0 0 0 1px #ff5722 inset,
        0 6px 16px rgba(255, 87, 34, 0.25);
}

/* Active state untuk revision */
.compact-card.revision:active {
    transform: translateY(0);
    box-shadow:
        0 0 0 1px #ff6b35 inset,
        0 2px 8px rgba(255, 107, 53, 0.2);
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
    content: '🚫';
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

/* .btn-secondary {
    background: var(--surface-soft);
    color: var(--text-muted);
} */

.btn-success {
    background: var(--success);
    color: white;
}

/* Responsive Design */
@media (max-width: 920px) {
    .section-header {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }

    .compact-dashboard {
        padding: 1rem;
        border: 1px solid var(--border);
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

/* Keterangan bulan mulai di kartu anggota yang tabnya sedang dibuka. */
.bulan-mulai-anggota {
    margin-top: 0.25rem;
    font-size: 0.78rem;
    opacity: 0.85;
}

/* Ringkasan bulan mulai per anggota di dialog konfirmasi pengiriman. */
.start-periode-ringkasan {
    margin-top: 1rem;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 0.5rem;
    background: var(--surface, #f9fafb);
    text-align: left;
}

.start-periode-judul {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    opacity: 0.7;
    margin-bottom: 0.5rem;
}

.start-periode-baris {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    padding: 0.25rem 0;
    font-size: 0.875rem;
}

.start-periode-baris + .start-periode-baris {
    border-top: 1px dashed var(--border, #e5e7eb);
}

.start-periode-bulan {
    font-weight: 600;
    white-space: nowrap;
}

.start-periode-jumlah {
    white-space: nowrap;
    opacity: 0.75;
}

.start-periode-nama {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    opacity: 0.65;
    font-size: 0.8rem;
}

.start-periode-catatan {
    margin-top: 0.5rem;
    font-size: 0.8rem;
    opacity: 0.8;
}
</style>
