<template>
    <div class="container-fluid px-0">
        <!-- Loading State -->
        <div v-if="loading.schedule" class="loading-container">
            <div class="loading-animation">
                <div class="loading-spinner"></div>
                <div class="loading-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
            <p class="loading-text">Memuat data penjadwalan...</p>
        </div>

        <!-- Schedule Selection -->
        <div
            v-else-if="!activeSchedule && schedules.length > 0"
            class="schedule-selection-container"
        >
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
                                                class="bi bi-calendar-check d-flex justify-content-center align-items-center"
                                            ></i>
                                        </div>
                                        <div class="text-content">
                                            <h2
                                                class="title fw-bold text-white text-start"
                                            >
                                                Pilih Periode KPI
                                            </h2>
                                            <p class="subtitle">
                                                Pilih periode penjadwalan untuk
                                                mengelola KPI tim
                                            </p>
                                        </div>
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
                    <p class="section-subtitle">
                        Pilih periode aktif atau lihat history periode
                        sebelumnya
                    </p>
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
                                            ? 'bi-play-circle'
                                            : 'bi-clock-history'
                                    "
                                ></i>
                            </div>
                            <div class="schedule-info">
                                <h5 class="schedule-name">
                                    {{ schedule.nama_kegiatan }}
                                </h5>
                                <p class="schedule-period">
                                    {{ schedule.tahun }} -
                                    {{ schedule.deskripsi }}
                                </p>
                            </div>
                            <div class="schedule-status">
                                <span
                                    class="status-badge"
                                    :class="
                                        schedule.is_active
                                            ? 'active'
                                            : 'historical'
                                    "
                                >
                                    {{
                                        schedule.is_active ? "Aktif" : "History"
                                    }}
                                </span>
                            </div>
                        </div>
                        <div class="schedule-dates">
                            <div class="date-item">
                                <i class="bi bi-calendar"></i>
                                <span
                                    >Mulai:
                                    {{
                                        formatDate(schedule.jadwal_mulai)
                                    }}</span
                                >
                            </div>
                            <div class="date-item">
                                <i class="bi bi-calendar"></i>
                                <span
                                    >Akhir:
                                    {{
                                        formatDate(schedule.jadwal_akhir)
                                    }}</span
                                >
                            </div>
                        </div>
                        <div class="schedule-actions">
                            <button
                                class="btn-modern btn-primary"
                                @click.stop="selectSchedule(schedule)"
                            >
                                <i class="bi bi-eye me-2"></i>
                                {{
                                    schedule.is_active
                                        ? "Kelola KPI"
                                        : "Lihat History"
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main KPI Management -->
        <div v-else-if="activeSchedule">
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
                                                Team KPI Management
                                            </h2>
                                            <p class="subtitle">
                                                {{
                                                    activeSchedule.nama_kegiatan
                                                }}
                                                - {{ activeSchedule.tahun }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="header-actions">
                                        <button
                                            class="btn-modern btn-outline"
                                            @click="backToScheduleSelection"
                                        >
                                            <i
                                                class="bi bi-arrow-left me-2"
                                            ></i>
                                            Kembali
                                        </button>
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
            <ApprovalTabs
                :tabs="mainTabs"
                :initialTab="activeTab"
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
                                            class="d-flex justify-content-center align-items-center bi bi-search"
                                        ></i>
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

                        <!-- Desktop Table View -->
                        <div class="d-none d-lg-block">
                            <div class="table-responsive-wrapper">
                                <table class="modern-table">
                                    <thead>
                                        <tr>
                                            <th class="header-cell">
                                                Nama Karyawan
                                            </th>
                                            <th class="header-cell">Posisi</th>
                                            <th class="header-cell">Divisi</th>
                                            <th class="header-cell">
                                                Total KPI
                                            </th>
                                            <th class="header-cell">
                                                Total Weight
                                            </th>
                                            <th class="header-cell">Status</th>
                                            <th class="header-cell">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="member in filteredTeamMembers"
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
                                                            {{ member.name }}
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
                                            <td>{{ member.division }}</td>
                                            <td>
                                                <span class="count-badge">{{
                                                    member.kpis?.length || 0
                                                }}</span>
                                            </td>
                                            <td>
                                                <span class="weight-value"
                                                    >{{
                                                        calculateTotalWeight(
                                                            member.kpis
                                                        )
                                                    }}%</span
                                                >
                                            </td>
                                            <td>
                                                <span
                                                    :class="`status-badge status-${
                                                        getKpiStatus(member)
                                                            .class
                                                    }`"
                                                >
                                                    {{
                                                        getKpiStatus(member)
                                                            .text
                                                    }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-cell">
                                                    <button
                                                        class="btn-action btn-view"
                                                        @click="
                                                            viewMemberKpis(
                                                                member
                                                            )
                                                        "
                                                        title="Lihat Detail"
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
                        <div class="d-block d-lg-none">
                            <div class="team-members-grid">
                                <div
                                    v-for="member in filteredTeamMembers"
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
                                                <span class="member-division">{{
                                                    member.division
                                                }}</span>
                                            </div>
                                        </div>
                                        <div class="member-status">
                                            <span
                                                :class="`status-badge status-${
                                                    getKpiStatus(member).class
                                                }`"
                                            >
                                                {{ getKpiStatus(member).text }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="member-kpi-summary">
                                        <div class="kpi-stats">
                                            <div class="stat-item">
                                                <i class="bi bi-layers"></i>
                                                <span
                                                    >{{
                                                        member.kpis?.length || 0
                                                    }}
                                                    KPI</span
                                                >
                                            </div>
                                            <div class="stat-item">
                                                <i
                                                    class="bi bi-speedometer2"
                                                ></i>
                                                <span
                                                    >{{
                                                        calculateTotalWeight(
                                                            member.kpis
                                                        )
                                                    }}%</span
                                                >
                                            </div>
                                        </div>
                                    </div>

                                    <div class="member-actions">
                                        <button
                                            class="btn-action btn-view"
                                            @click="viewMemberKpis(member)"
                                            title="Lihat KPI"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div
                            v-if="filteredTeamMembers.length === 0"
                            class="empty-state"
                        >
                            <div class="empty-icon">
                                <i class="bi bi-people"></i>
                            </div>
                            <h4>Tidak Ada Anggota Tim</h4>
                            <p>Belum ada anggota tim yang tersedia</p>
                        </div>
                    </div>
                </template>

                <!-- Tab 2: Bulk Creation -->
                <template #bulk>
                    <div class="bulk-kpi-content">
                        <!-- Bulk Creation Header -->
                        <div class="bulk-creation-header mb-4">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h4 class="section-title">
                                        Buat KPI untuk Multiple Karyawan
                                    </h4>
                                    <p class="section-subtitle">
                                        Pilih anggota tim dan buat KPI secara
                                        bersamaan
                                    </p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button
                                        class="btn-modern btn-secondary"
                                        @click="resetBulkCreation"
                                    >
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
                        <div
                            v-if="bulkCurrentStep === 1"
                            class="bulk-step-content"
                        >
                            <div class="step-header">
                                <h5>Pilih Anggota Tim</h5>
                                <p>Pilih anggota tim yang akan diberikan KPI</p>
                            </div>

                            <div class="member-selection-section">
                                <div class="selection-actions mb-3">
                                    <button
                                        class="btn-selection"
                                        @click="selectAllMembers"
                                    >
                                        Pilih Semua
                                    </button>
                                    <button
                                        class="btn-selection"
                                        @click="clearMemberSelection"
                                    >
                                        Hapus Semua
                                    </button>
                                </div>

                                <div class="member-selection-grid">
                                    <div
                                        v-for="member in availableTeamMembers"
                                        :key="member.id"
                                        class="member-selection-card"
                                        :class="{
                                            selected: isMemberSelected(
                                                member.id
                                            ),
                                            disabled: member.hasSubmittedKPI,
                                        }"
                                        @click="
                                            !member.hasSubmittedKPI &&
                                                toggleMemberSelection(member.id)
                                        "
                                    >
                                        <div class="selection-checkbox">
                                            <input
                                                type="checkbox"
                                                :checked="
                                                    isMemberSelected(member.id)
                                                "
                                                :disabled="
                                                    member.hasSubmittedKPI
                                                "
                                                @change="
                                                    !member.hasSubmittedKPI &&
                                                        toggleMemberSelection(
                                                            member.id
                                                        )
                                                "
                                            />
                                        </div>
                                        <div class="selection-avatar">
                                            {{ getInitials(member.name) }}
                                        </div>
                                        <div class="selection-info">
                                            <h6 class="member-name">
                                                {{ member.name }}
                                            </h6>
                                            <p class="member-position">
                                                {{ member.position }}
                                            </p>
                                            <div
                                                v-if="member.hasSubmittedKPI"
                                                class="submission-info"
                                            >
                                                <i
                                                    class="bi bi-check-circle"
                                                ></i>
                                                <span>Sudah diajukan</span>
                                            </div>
                                        </div>
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
                                    <i class="bi bi-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: KPI Configuration -->
                        <div
                            v-else-if="bulkCurrentStep === 2"
                            class="bulk-step-content"
                        >
                            <div class="step-header">
                                <h5>Pengisian Parameter KPI</h5>
                                <p>
                                    Isi parameter KPI untuk anggota tim yang
                                    dipilih
                                </p>
                            </div>

                            <!-- Member Tabs -->
                            <div class="member-tabs-section mb-4">
                                <div class="member-tabs">
                                    <button
                                        v-for="memberId in selectedMembers"
                                        :key="memberId"
                                        class="member-tab"
                                        :class="{
                                            active:
                                                activeMemberTab === memberId,
                                        }"
                                        @click="activeMemberTab = memberId"
                                    >
                                        <div class="tab-avatar">
                                            {{
                                                getInitials(
                                                    getMemberById(memberId)
                                                        ?.name
                                                )
                                            }}
                                        </div>
                                        <span class="tab-name">{{
                                            getMemberById(memberId)?.name
                                        }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- KPI Form -->
                            <div class="kpi-configuration-section">
                                <KpiForm
                                    :modelValue="
                                        getMemberKpiData(activeMemberTab)
                                    "
                                    :fields="bulkKpiFields"
                                    :kpis="[getMemberKpiData(activeMemberTab)]"
                                    :activeFormIndex="0"
                                    :showHeader="false"
                                    :showNavigation="false"
                                    :showQuickActions="false"
                                    @update:modelValue="
                                        updateMemberKpiData(
                                            activeMemberTab,
                                            $event
                                        )
                                    "
                                />
                            </div>

                            <div class="step-actions">
                                <div
                                    class="d-flex justify-content-between w-100"
                                >
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
                                    >
                                        Lanjut ke Preview
                                        <i class="bi bi-arrow-right ms-2"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Preview -->
                        <div
                            v-else-if="bulkCurrentStep === 3"
                            class="bulk-step-content"
                        >
                            <div class="step-header">
                                <h5>Verifikasi Data KPI</h5>
                                <p>Tinjau KPI sebelum diajukan</p>
                            </div>

                            <div class="preview-section">
                                <div class="preview-grid">
                                    <div
                                        v-for="memberId in selectedMembers"
                                        :key="memberId"
                                        class="preview-card"
                                    >
                                        <div class="preview-header">
                                            <div class="preview-avatar">
                                                {{
                                                    getInitials(
                                                        getMemberById(memberId)
                                                            ?.name
                                                    )
                                                }}
                                            </div>
                                            <div class="preview-info">
                                                <h6>
                                                    {{
                                                        getMemberById(memberId)
                                                            ?.name
                                                    }}
                                                </h6>
                                                <p>
                                                    {{
                                                        getMemberById(memberId)
                                                            ?.position
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="preview-kpis">
                                            <div
                                                v-for="(
                                                    kpi, index
                                                ) in getMemberKpis(memberId)"
                                                :key="index"
                                                class="preview-kpi-item"
                                            >
                                                <div class="kpi-parameter">
                                                    {{ kpi.parameter }}
                                                </div>
                                                <div class="kpi-details">
                                                    <span class="kpi-weight"
                                                        >{{ kpi.weight }}%</span
                                                    >
                                                    <span class="kpi-target">{{
                                                        kpi.target
                                                    }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="preview-total">
                                            Total Weight:
                                            <strong
                                                >{{
                                                    calculateMemberTotalWeight(
                                                        memberId
                                                    )
                                                }}%</strong
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="step-actions">
                                <div
                                    class="d-flex justify-content-between w-100"
                                >
                                    <button
                                        class="btn-modern btn-secondary"
                                        @click="previousBulkStep"
                                    >
                                        <i class="bi bi-arrow-left me-2"></i>
                                        Kembali
                                    </button>
                                    <button
                                        class="btn-modern btn-success"
                                        @click="submitBulkKpis"
                                    >
                                        <i class="bi bi-send me-2"></i>
                                        Ajukan KPI
                                    </button>
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
                                    <h4 class="section-title">
                                        Progress Pengajuan KPI
                                    </h4>
                                    <p class="section-subtitle">
                                        Pantau status pengajuan KPI seluruh tim
                                    </p>
                                </div>
                                <div class="col-md-4">
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
                                </div>
                            </div>
                        </div>

                        <!-- Progress Stats -->
                        <div class="progress-stats mb-4">
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-icon waiting">
                                        <i class="bi bi-clock"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h4>
                                            {{ progressStats.waiting_approval }}
                                        </h4>
                                        <p>Menunggu Approval</p>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon rejected">
                                        <i class="bi bi-x-circle"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h4>{{ progressStats.rejected }}</h4>
                                        <p>Ditolak</p>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon approved">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h4>{{ progressStats.approved }}</h4>
                                        <p>Disetujui</p>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon resubmitted">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h4>{{ progressStats.resubmitted }}</h4>
                                        <p>Mengajukan Ulang</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Desktop Table View -->
                        <div class="d-none d-lg-block">
                            <div class="table-responsive-wrapper">
                                <table class="modern-table">
                                    <thead>
                                        <tr>
                                            <th class="header-cell">
                                                Nama Karyawan
                                            </th>
                                            <th class="header-cell">Posisi</th>
                                            <th class="header-cell">
                                                Total KPI
                                            </th>
                                            <th class="header-cell">
                                                Total Weight
                                            </th>
                                            <th class="header-cell">Status</th>
                                            <th class="header-cell">
                                                Approver
                                            </th>
                                            <th class="header-cell">
                                                Terakhir Diupdate
                                            </th>
                                            <th class="header-cell">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="member in filteredProgressMembers"
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
                                                            {{ member.name }}
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
                                            <td>
                                                <span class="count-badge">{{
                                                    member.kpis?.length || 0
                                                }}</span>
                                            </td>
                                            <td>
                                                <span class="weight-value"
                                                    >{{
                                                        calculateTotalWeight(
                                                            member.kpis
                                                        )
                                                    }}%</span
                                                >
                                            </td>
                                            <td>
                                                <span
                                                    :class="`status-badge status-${
                                                        getKpiStatus(member)
                                                            .class
                                                    }`"
                                                >
                                                    {{
                                                        getKpiStatus(member)
                                                            .text
                                                    }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="approver-info">{{
                                                    getApproverInfo(member)
                                                }}</span>
                                            </td>
                                            <td>
                                                {{
                                                    formatDate(
                                                        member.lastUpdate
                                                    )
                                                }}
                                            </td>
                                            <td>
                                                <div class="action-cell">
                                                    <button
                                                        class="btn-action btn-view"
                                                        @click="
                                                            viewMemberKpis(
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
                                                        v-if="
                                                            canEditKpi(member)
                                                        "
                                                        class="btn-action btn-edit"
                                                        @click="
                                                            editMemberKpis(
                                                                member
                                                            )
                                                        "
                                                        title="Edit KPI"
                                                    >
                                                        <i
                                                            class="bi bi-pencil"
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
                            <div class="progress-cards-grid">
                                <div
                                    v-for="member in filteredProgressMembers"
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
                                        </div>
                                        <div class="member-status">
                                            <span
                                                :class="`status-badge status-${
                                                    getKpiStatus(member).class
                                                }`"
                                            >
                                                {{ getKpiStatus(member).text }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-content">
                                        <div class="progress-stats">
                                            <div class="stat">
                                                <i class="bi bi-layers"></i>
                                                <span
                                                    >{{
                                                        member.kpis?.length || 0
                                                    }}
                                                    KPI</span
                                                >
                                            </div>
                                            <div class="stat">
                                                <i
                                                    class="bi bi-speedometer2"
                                                ></i>
                                                <span
                                                    >{{
                                                        calculateTotalWeight(
                                                            member.kpis
                                                        )
                                                    }}%</span
                                                >
                                            </div>
                                        </div>
                                        <div class="approver-info">
                                            <small
                                                >Approver:
                                                {{
                                                    getApproverInfo(member)
                                                }}</small
                                            >
                                        </div>
                                        <div class="update-info">
                                            <small
                                                >Update:
                                                {{
                                                    formatDate(
                                                        member.lastUpdate
                                                    )
                                                }}</small
                                            >
                                        </div>
                                    </div>

                                    <div class="card-actions">
                                        <button
                                            class="btn-action btn-view"
                                            @click="viewMemberKpis(member)"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button
                                            v-if="canEditKpi(member)"
                                            class="btn-action btn-edit"
                                            @click="editMemberKpis(member)"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div
                            v-if="filteredProgressMembers.length === 0"
                            class="empty-state"
                        >
                            <div class="empty-icon">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <h4>Tidak Ada Data Progress</h4>
                            <p>Belum ada KPI yang diajukan untuk periode ini</p>
                        </div>
                    </div>
                </template>
            </ApprovalTabs>
        </div>

        <!-- No Active Schedule State -->
        <div v-else class="no-schedule-state">
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="bi bi-calendar-x"></i>
                </div>
                <h4>Tidak Ada Penjadwalan Aktif</h4>
                <p>Belum ada periode KPI yang aktif saat ini</p>
                <button class="btn-modern btn-primary" @click="fetchSchedules">
                    <i class="bi bi-arrow-clockwise me-2"></i>
                    Refresh
                </button>
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
                    <!-- Member Info -->
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
                            <span
                                :class="`status-badge status-${
                                    getKpiStatus(selectedMember).class
                                }`"
                            >
                                {{ getKpiStatus(selectedMember).text }}
                            </span>
                        </div>
                    </div>

                    <!-- KPI List -->
                    <div class="member-kpi-list">
                        <div class="kpi-list-header">
                            <h6>Daftar KPI</h6>
                        </div>

                        <div
                            v-if="
                                selectedMember.kpis &&
                                selectedMember.kpis.length > 0
                            "
                            class="kpi-items"
                        >
                            <div
                                v-for="(kpi, index) in selectedMember.kpis"
                                :key="kpi.id"
                                class="kpi-item-detail"
                            >
                                <div class="kpi-item-header">
                                    <div class="kpi-number">
                                        #{{ index + 1 }}
                                    </div>
                                    <div class="kpi-main-info">
                                        <h6>{{ kpi.parameter }}</h6>
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
                                <div class="kpi-item-content">
                                    <p>
                                        <strong>Target:</strong>
                                        {{ kpi.target }}
                                    </p>
                                    <p>
                                        <strong>Deskripsi:</strong>
                                        {{ kpi.description }}
                                    </p>
                                    <div
                                        v-if="kpi.rejectionReason"
                                        class="rejection-reason"
                                    >
                                        <strong>Alasan Penolakan:</strong>
                                        <p>{{ kpi.rejectionReason }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="empty-kpis">
                            <div class="empty-icon">
                                <i class="bi bi-file-earmark"></i>
                            </div>
                            <p>Belum ada KPI untuk karyawan ini</p>
                        </div>
                    </div>

                    <!-- Submission Info -->
                    <div
                        v-if="
                            selectedMember.kpis &&
                            selectedMember.kpis.length > 0
                        "
                        class="submission-info"
                    >
                        <div class="kpi-summary">
                            <div class="summary-item">
                                <strong>Total KPI:</strong>
                                <span>{{ selectedMember.kpis.length }}</span>
                            </div>
                            <div class="summary-item">
                                <strong>Total Bobot:</strong>
                                <span
                                    >{{
                                        calculateTotalWeight(
                                            selectedMember.kpis
                                        )
                                    }}%</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import { ElMessage, ElMessageBox } from "element-plus";
import quickStat from "@/components/ui/quickStat.vue";
import ApprovalTabs from "../../../../components/ui/toggleSection.vue";
import KpiForm from "../../../../components/form/form-dynamic.vue";
import Stepper from "../../../../components/ui/stepper.vue";
import Modal from "@/components/ui/modalV2-noir.vue";

export default {
    name: "TeamKpiManagement",
    components: {
        quickStat,
        ApprovalTabs,
        KpiForm,
        Stepper,
        Modal,
    },
    data() {
        return {
            // Global States
            loading: {
                schedule: true,
                team: false,
                bulk: false,
            },
            schedules: [],
            activeSchedule: null,

            // Team Data
            teamMembers: [],
            selectedMembers: [],
            teamFilters: {
                search: "",
            },

            // Modal States
            showMemberDetailModal: false,
            selectedMember: null,

            // Tabs
            activeTab: "overview",
            mainTabs: [
                {
                    id: "overview",
                    label: "Team Overview",
                    icon: "bi bi-people",
                    count: 0,
                },
                {
                    id: "bulk",
                    label: "Bulk Creation",
                    icon: "bi bi-layers",
                    count: 0,
                },
                {
                    id: "progress",
                    label: "Submission Progress",
                    icon: "bi bi-graph-up",
                    count: 0,
                },
            ],

            // Bulk Creation
            bulkCurrentStep: 1,
            bulkStepperSteps: [
                {
                    id: 1,
                    title: "Pilih Tim",
                    description: "Pilih tim yang akan diberikan KPI",
                },
                {
                    id: 2,
                    title: "Pengisian KPI",
                    description: "Pengisian Parameter KPI Tim",
                },
                {
                    id: 3,
                    title: "Verifikasi",
                    description: "Verifikasi data KPI Tim",
                },
            ],
            activeMemberTab: null,
            memberKpiData: {},

            // Progress Tracking
            progressFilter: "all",
            progressStats: {
                waiting_approval: 0,
                rejected: 0,
                approved: 0,
                resubmitted: 0,
            },
        };
    },
    computed: {
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
                    title: "Menunggu Approval",
                    value: this.progressStats.waiting_approval,
                    unit: "",
                    icon: "bi bi-clock",
                    bgIcon: "",
                    size: "col-4",
                },
                {
                    title: "Disetujui",
                    value: this.progressStats.approved,
                    unit: "",
                    icon: "bi bi-check-circle",
                    bgIcon: "",
                    size: "col-4",
                },
            ];
        },
        filteredTeamMembers() {
            if (!this.teamFilters.search) return this.teamMembers;

            const searchTerm = this.teamFilters.search.toLowerCase();
            return this.teamMembers.filter(
                (member) =>
                    member.name.toLowerCase().includes(searchTerm) ||
                    member.position.toLowerCase().includes(searchTerm) ||
                    member.employeeId.toLowerCase().includes(searchTerm)
            );
        },
        filteredProgressMembers() {
            let members = this.teamMembers.filter(
                (member) =>
                    member.kpis &&
                    member.kpis.length > 0 &&
                    member.submissionStatus !== "draft"
            );

            if (this.progressFilter !== "all") {
                members = members.filter(
                    (member) =>
                        this.getKpiStatus(member).class === this.progressFilter
                );
            }

            return members;
        },
        availableTeamMembers() {
            return this.teamMembers.filter((member) => !member.hasSubmittedKPI);
        },
        bulkKpiFields() {
            return [
                {
                    name: "parameter",
                    label: "Parameter KPI",
                    type: "text",
                    placeholder: "Masukkan parameter KPI",
                    required: true,
                    rowIndex: 0,
                    size: "col-12",
                    enabled: true,
                    icon: "bi bi-tag",
                },
                {
                    name: "category",
                    label: "Kategori",
                    type: "select",
                    placeholder: "Pilih kategori",
                    required: true,
                    rowIndex: 0,
                    size: "col-12 col-md-6",
                    icon: "bi bi-grid",
                    options: [
                        { value: "financial", label: "Financial" },
                        { value: "customer", label: "Customer" },
                        { value: "process", label: "Internal Process" },
                        { value: "growth", label: "Learning & Growth" },
                    ],
                },
                {
                    name: "weight",
                    label: "Bobot",
                    type: "persen",
                    placeholder: "0",
                    required: true,
                    enabled: true,
                    rowIndex: 1,
                    size: "col-12 col-md-6",
                    icon: "bi bi-speedometer2",
                },
                {
                    name: "target",
                    label: "Target",
                    type: "text",
                    placeholder: "Masukkan target",
                    required: true,
                    rowIndex: 2,
                    enabled: true,
                    size: "col-12",
                    icon: "bi bi-bullseye",
                },
                {
                    name: "description",
                    label: "Deskripsi",
                    type: "textarea",
                    placeholder: "Deskripsi detail KPI",
                    required: false,
                    rowIndex: 3,
                    enabled: true,
                    size: "col-12",
                    icon: "bi bi-text-paragraph",
                    rows: 3,
                },
            ];
        },
    },
    methods: {
        // Initialization
        async initializeData() {
            await this.fetchSchedules();
        },

        async fetchSchedules() {
            this.loading.schedule = true;
            try {
                // Simulate API call
                await new Promise((resolve) => setTimeout(resolve, 1000));

                // Dummy data - replace with actual API
                this.schedules = [
                    {
                        id: 1,
                        nama_kegiatan: "KPI Q1 2024",
                        tahun: "2024",
                        deskripsi: "Kuartal 1 Tahun 2024",
                        jadwal_mulai: "2024-01-01",
                        jadwal_akhir: "2024-03-31",
                        is_active: true,
                    },
                    {
                        id: 2,
                        nama_kegiatan: "KPI Q4 2023",
                        tahun: "2023",
                        deskripsi: "Kuartal 4 Tahun 2023",
                        jadwal_mulai: "2023-10-01",
                        jadwal_akhir: "2023-12-31",
                        is_active: false,
                    },
                ];
            } catch (error) {
                console.error("Error fetching schedules:", error);
                this.showAlert("Gagal memuat data penjadwalan", "error");
            } finally {
                this.loading.schedule = false;
            }
        },

        async selectSchedule(schedule) {
            this.activeSchedule = schedule;
            await this.fetchTeamData();
            this.calculateProgressStats();
        },

        backToScheduleSelection() {
            this.activeSchedule = null;
            this.teamMembers = [];
        },

        async fetchTeamData() {
            this.loading.team = true;
            try {
                // Simulate API call
                await new Promise((resolve) => setTimeout(resolve, 800));

                // Dummy data - replace with actual API
                this.teamMembers = [
                    {
                        id: 1,
                        name: "Ahmad Rizki",
                        position: "Software Engineer",
                        division: "Technology",
                        employeeId: "EMP001",
                        kpis: [
                            {
                                id: 1,
                                parameter: "Project Completion Rate",
                                category: "process",
                                weight: 30,
                                target: "95% completion",
                                description:
                                    "Tingkat penyelesaian proyek tepat waktu",
                            },
                            {
                                id: 2,
                                parameter: "Code Quality Score",
                                category: "process",
                                weight: 25,
                                target: "Score 4.5/5",
                                description: "Kualitas kode berdasarkan review",
                            },
                        ],
                        lastUpdate: new Date().toISOString(),
                        submissionStatus: "waiting_approval",
                        approvers: [
                            {
                                name: "Manager IT",
                                status: "approved",
                                level: 1,
                            },
                            { name: "Director", status: "waiting", level: 2 },
                        ],
                        hasSubmittedKPI: true,
                    },
                    {
                        id: 2,
                        name: "Sari Dewi",
                        position: "UI/UX Designer",
                        division: "Design",
                        employeeId: "EMP002",
                        kpis: [
                            {
                                id: 1,
                                parameter: "Design Satisfaction",
                                category: "customer",
                                weight: 40,
                                target: "90% satisfaction",
                                description:
                                    "Tingkat kepuasan user terhadap desain",
                            },
                        ],
                        lastUpdate: new Date().toISOString(),
                        submissionStatus: "rejected",
                        approvers: [
                            {
                                name: "Manager Design",
                                status: "rejected",
                                level: 1,
                                reason: "Target tidak realistis",
                            },
                        ],
                        hasSubmittedKPI: true,
                    },
                    {
                        id: 3,
                        name: "Budi Santoso",
                        position: "Product Manager",
                        division: "Product",
                        employeeId: "EMP003",
                        kpis: [],
                        lastUpdate: new Date().toISOString(),
                        submissionStatus: "draft",
                        hasSubmittedKPI: false,
                    },
                    {
                        id: 4,
                        name: "Maya Sari",
                        position: "QA Engineer",
                        division: "Technology",
                        employeeId: "EMP004",
                        kpis: [
                            {
                                id: 1,
                                parameter: "Bug Detection Rate",
                                category: "process",
                                weight: 35,
                                target: "98% detection",
                                description:
                                    "Tingkat deteksi bug sebelum production",
                            },
                        ],
                        lastUpdate: new Date().toISOString(),
                        submissionStatus: "approved",
                        approvers: [
                            {
                                name: "Manager IT",
                                status: "approved",
                                level: 1,
                            },
                            { name: "Director", status: "approved", level: 2 },
                        ],
                        hasSubmittedKPI: true,
                    },
                ];
            } catch (error) {
                console.error("Error fetching team data:", error);
                this.showAlert("Gagal memuat data tim", "error");
            } finally {
                this.loading.team = false;
            }
        },

        // Utility Methods
        getInitials(name) {
            if (!name) return "??";
            return name
                .split(" ")
                .map((n) => n[0])
                .join("")
                .toUpperCase();
        },

        formatDate(dateString) {
            if (!dateString) return "-";
            return new Date(dateString).toLocaleDateString("id-ID");
        },

        showAlert(message, type = "warning") {
            ElMessage({
                type: type,
                message: message,
            });
        },

        // KPI Status Methods
        getKpiStatus(member) {
            if (!member.kpis || member.kpis.length === 0) {
                return { class: "draft", text: "Belum Ada KPI" };
            }

            const statusMap = {
                draft: { class: "draft", text: "Draft" },
                waiting_approval: {
                    class: "waiting",
                    text: "Menunggu Approval",
                },
                rejected: { class: "rejected", text: "Ditolak" },
                approved: { class: "approved", text: "Disetujui" },
                resubmitted: { class: "resubmitted", text: "Mengajukan Ulang" },
            };

            return statusMap[member.submissionStatus] || statusMap.draft;
        },

        getApproverInfo(member) {
            if (!member.approvers || member.approvers.length === 0) return "-";

            const currentApprover = member.approvers.find(
                (approver) =>
                    approver.status === "waiting" ||
                    approver.status === "rejected"
            );

            if (currentApprover) {
                return `${currentApprover.name} (${
                    currentApprover.status === "rejected"
                        ? "Ditolak"
                        : "Menunggu"
                })`;
            }

            return "Selesai";
        },

        canEditKpi(member) {
            return (
                member.submissionStatus === "rejected" ||
                (member.submissionStatus === "waiting_approval" &&
                    member.approvers &&
                    member.approvers.some(
                        (a) => a.status === "waiting" && a.level === 1
                    ))
            );
        },

        calculateTotalWeight(kpis) {
            if (!kpis || kpis.length === 0) return 0;
            return kpis.reduce(
                (sum, kpi) => sum + (parseFloat(kpi.weight) || 0),
                0
            );
        },

        calculateProgressStats() {
            const stats = {
                waiting_approval: 0,
                rejected: 0,
                approved: 0,
                resubmitted: 0,
            };

            this.teamMembers.forEach((member) => {
                if (
                    member.submissionStatus &&
                    stats.hasOwnProperty(member.submissionStatus)
                ) {
                    stats[member.submissionStatus]++;
                }
            });

            this.progressStats = stats;
        },

        // Member Selection Methods
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

        selectAllMembers() {
            this.selectedMembers = this.availableTeamMembers.map(
                (member) => member.id
            );
        },

        clearMemberSelection() {
            this.selectedMembers = [];
        },

        getMemberById(memberId) {
            return this.teamMembers.find((member) => member.id === memberId);
        },

        // Bulk Creation Methods
        navigateToBulkCreation() {
            this.activeTab = "bulk";
            this.bulkCurrentStep = 1;
            this.selectedMembers = [];
            this.memberKpiData = {};
        },

        handleBulkStepChange(step) {
            if (step < this.bulkCurrentStep) {
                this.bulkCurrentStep = step;
            }
        },

        nextBulkStep() {
            if (this.bulkCurrentStep < 3) {
                this.bulkCurrentStep++;
                if (
                    this.bulkCurrentStep === 2 &&
                    this.selectedMembers.length > 0
                ) {
                    this.activeMemberTab = this.selectedMembers[0];
                    this.initializeMemberKpiData();
                }
            }
        },

        previousBulkStep() {
            if (this.bulkCurrentStep > 1) {
                this.bulkCurrentStep--;
            }
        },

        initializeMemberKpiData() {
            this.selectedMembers.forEach((memberId) => {
                if (!this.memberKpiData[memberId]) {
                    this.memberKpiData[memberId] = {
                        parameter: "",
                        category: "",
                        weight: 0,
                        target: "",
                        description: "",
                    };
                }
            });
        },

        getMemberKpiData(memberId) {
            return this.memberKpiData[memberId] || {};
        },

        updateMemberKpiData(memberId, data) {
            this.memberKpiData[memberId] = { ...data };
        },

        getMemberKpis(memberId) {
            const kpiData = this.memberKpiData[memberId];
            if (!kpiData || !kpiData.parameter) return [];
            return [kpiData];
        },

        calculateMemberTotalWeight(memberId) {
            const kpis = this.getMemberKpis(memberId);
            return this.calculateTotalWeight(kpis);
        },

        async submitBulkKpis() {
            try {
                await ElMessageBox.confirm(
                    `Apakah Anda yakin ingin mengajukan KPI untuk ${this.selectedMembers.length} anggota?`,
                    "Konfirmasi Pengajuan",
                    {
                        confirmButtonText: "Ya, Ajukan",
                        cancelButtonText: "Batal",
                        type: "warning",
                    }
                );

                // Simulate API call
                this.loading.bulk = true;
                await new Promise((resolve) => setTimeout(resolve, 1500));

                // Update team members data
                this.selectedMembers.forEach((memberId) => {
                    const member = this.getMemberById(memberId);
                    if (member) {
                        const kpiData = this.memberKpiData[memberId];
                        if (kpiData && kpiData.parameter) {
                            if (!member.kpis) member.kpis = [];
                            member.kpis.push({
                                id: Date.now() + Math.random(),
                                ...kpiData,
                            });
                            member.submissionStatus = "waiting_approval";
                            member.lastUpdate = new Date().toISOString();
                            member.hasSubmittedKPI = true;
                        }
                    }
                });

                this.showAlert(
                    `KPI berhasil diajukan untuk ${this.selectedMembers.length} anggota`,
                    "success"
                );
                this.resetBulkCreation();
                this.calculateProgressStats();
                this.activeTab = "progress";
            } catch (error) {
                // User cancelled
            } finally {
                this.loading.bulk = false;
            }
        },

        resetBulkCreation() {
            this.bulkCurrentStep = 1;
            this.selectedMembers = [];
            this.memberKpiData = {};
            this.activeMemberTab = null;
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

        editMemberKpis(member) {
            this.selectedMember = member;
            this.navigateToBulkCreation();
            // Pre-fill data for editing
            if (member.kpis && member.kpis.length > 0) {
                this.selectedMembers = [member.id];
                this.memberKpiData[member.id] = { ...member.kpis[0] };
                this.bulkCurrentStep = 2;
                this.activeMemberTab = member.id;
            }
        },

        // Tab Management
        handleTabChange(tabId) {
            this.activeTab = tabId;
        },
    },
    async mounted() {
        await this.initializeData();
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
    color: var(--text-muted);
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

/* Preview Section */
.preview-section {
    margin-bottom: 1.5rem;
}

.preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.preview-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.preview-card:hover {
    box-shadow: var(--shadow);
}

.preview-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.preview-avatar {
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
}

.preview-info h6 {
    margin: 0 0 0.25rem 0;
    font-size: 0.9rem;
    color: var(--dark);
}

.preview-info p {
    margin: 0;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.preview-kpis {
    margin-bottom: 1rem;
}

.preview-kpi-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border-soft);
}

.preview-kpi-item:last-child {
    border-bottom: none;
}

.kpi-parameter {
    flex: 1;
    font-size: 0.85rem;
    color: var(--dark);
}

.kpi-details {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.kpi-weight {
    background: var(--primary-light);
    color: var(--primary);
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.kpi-target {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.preview-total {
    text-align: right;
    font-size: 0.9rem;
    color: var(--dark);
    padding-top: 0.75rem;
    border-top: 1px solid var(--border);
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
    background: linear-gradient(135deg, var(--info), #06b6d4);
}

.stat-icon.rejected {
    background: linear-gradient(135deg, var(--danger), #ef4444);
}

.stat-icon.approved {
    background: linear-gradient(135deg, var(--success), #10b981);
}

.stat-icon.resubmitted {
    background: linear-gradient(135deg, #8b5cf6, #a855f7);
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
    background: #fef2f2;
    border: 1px solid #fecaca;
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
    color: #7f1d1d;
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

.no-schedule-state {
    padding: 4rem 2rem;
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
    .schedule-grid {
        grid-template-columns: 1fr;
    }

    .team-members-grid,
    .progress-cards-grid,
    .preview-grid {
        grid-template-columns: 1fr;
    }

    .member-selection-grid {
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
