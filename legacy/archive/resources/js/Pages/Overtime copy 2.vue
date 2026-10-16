<template>
    <div class="overtime-app-container">
        <!-- Header -->
        <HeaderKpi
            title="Manajemen Lembur Karyawan"
            subtitle="Kelola jadwal Lembur"
            :dayNight="dayNight"
            :formattedDate="formatDateString(TanggalSekarang)"
            :rightSection="true"
            :buttonAction="true"
            buttonText="Ajukan Lembur"
            @button-clicked="openAssignModal"
        />

        <!-- Quick Stats -->
        <QuickStat :stats="quickStatField" />

        <!-- Main Content -->
        <div class="main-content-wrapper">
            <div class="content-container">
                <!-- Filter Section -->
                <FilterPanel
                    :config="filterConfig"
                    :filters="tableFilters"
                    :results-count="totalData"
                    @filters-update="handleFiltersUpdate"
                    @search="handleSearch"
                />

                <!-- Data Table -->
                <ModernDataTable
                    v-if="viewMode === 'table'"
                    :data="data"
                    :columns="tableColumns"
                    :mobile-columns="mobileColumns"
                    :loading="loading"
                    :clickable="true"
                    :show-pagination="true"
                    :current-page="currentPage"
                    :total-pages="totalPages"
                    :total-data="totalData"
                    :full-pagination="{
                        first_page_url: null,
                        last_page_url: null,
                        next_page_url: nextLink,
                        prev_page_url: prevLink,
                        from: (currentPage - 1) * itemsPerPage + 1,
                        to: Math.min(currentPage * itemsPerPage, totalData),
                        links: [],
                    }"
                    card-title-key="No_Transaksi"
                    card-subtitle-key="Nama"
                    card-status-key="status"
                    empty-message="Tidak ada transaksi lembur ditemukan"
                    @row-click="showDetailsMethod"
                    @page-change="handlePageChange"
                    @empty-action="clearFilters"
                >
                    <!-- Custom slot untuk kolom No. Transaksi -->
                    <template #column-No_Transaksi="{ item }">
                        <div class="employee-info-cell">
                            <div class="avatar-sm">
                                {{ getInitials(item.No_Transaksi) }}
                            </div>
                            <div class="employee-details">
                                <h6 class="employee-name">
                                    {{ item.No_Transaksi }}
                                </h6>
                                <p class="employee-dept">
                                    {{ item.Kode_Perusahaan || "-" }}
                                </p>
                            </div>
                        </div>
                    </template>

                    <!-- Custom slot untuk kolom Tanggal -->
                    <template #column-Tanggal="{ item }">
                        {{ formatDate(item.Tanggal) }}
                    </template>

                    <!-- Custom slot untuk kolom Jam -->
                    <template #column-Jam="{ item }">
                        {{ formatTimeSingle(item.Jam) }}
                    </template>

                    <!-- Custom slot untuk kolom User -->
                    <template #column-User="{ item }">
                        {{ item.Nama }}
                    </template>

                    <!-- Custom slot untuk kolom Aksi -->
                    <template #column-actions="{ item }">
                        <div class="action-cell">
                            <button
                                class="btn-action btn-view"
                                @click.stop="showDetailsMethod(item)"
                                v-tooltip="'Lihat Detail'"
                            >
                                <i class="bi bi-eye"></i>
                            </button>
                            <button
                                v-if="disabledButton2(item.Tanggal_Assign)"
                                class="btn-action btn-delete"
                                @click.stop="
                                    deleteAlertButton(item.No_Transaksi)
                                "
                                v-tooltip="'Hapus'"
                                :disabled="
                                    !disabledButton2(item.Tanggal_Assign)
                                "
                            >
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </template>

                    <!-- Custom header untuk card view -->
                    <template #card-header="{ item }">
                        <div class="card-title">{{ item.No_Transaksi }}</div>
                        <div class="card-subtitle">
                            {{ formatDate(item.Tanggal) }}
                        </div>
                    </template>

                    <!-- Custom value untuk mobile view -->
                    <template #mobile-Jam="{ item }">
                        {{ formatTimeSingle(item.Jam) }}
                    </template>

                    <template #mobile-User="{ item }">
                        {{ item.Nama }}
                    </template>

                    <!-- Custom actions untuk mobile -->
                    <template #card-actions="{ item }">
                        <div class="d-flex gap-2">
                            <button
                                class="card-btn btn-view"
                                @click.stop="showDetailsMethod(item)"
                            >
                                <i class="bi bi-eye"></i>
                                <span>Detail</span>
                            </button>
                            <button
                                v-if="disabledButton2(item.Tanggal_Assign)"
                                class="card-btn btn-delete"
                                @click.stop="
                                    deleteAlertButton(item.No_Transaksi)
                                "
                                :disabled="
                                    !disabledButton2(item.Tanggal_Assign)
                                "
                            >
                                <i class="bi bi-trash"></i>
                                <span>Hapus</span>
                            </button>
                        </div>
                    </template>
                </ModernDataTable>

                <!-- Card View -->
                <div v-else class="card-view-container">
                    <div v-if="loading" class="loading-state">
                        <div class="loading-spinner">
                            <i class="bi bi-arrow-repeat spin"></i>
                        </div>
                        <p>Memuat data lembur...</p>
                    </div>

                    <div v-else-if="data.length === 0" class="empty-state">
                        <div class="empty-icon">
                            <DotLottieVue
                                style="height: 150px; width: 150px"
                                autoplay
                                loop
                                src="/animation/empty-data.json"
                            />
                        </div>
                        <h4>Tidak ada transaksi ditemukan</h4>
                        <p>Coba ubah filter atau kata kunci pencarian Anda</p>
                        <button
                            class="btn-modern btn-primary"
                            @click="clearFilters"
                        >
                            <i class="bi bi-arrow-clockwise me-2"></i>
                            Reset Filter
                        </button>
                    </div>

                    <div v-else class="cards-grid">
                        <div
                            v-for="item in data"
                            :key="item.No_Transaksi"
                            class="overtime-card"
                            @click="showDetailsMethod(item)"
                        >
                            <div class="card-header">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-md">
                                        {{ getInitials(item.No_Transaksi) }}
                                    </div>
                                    <div>
                                        <h6 class="card-title">
                                            {{ item.No_Transaksi }}
                                        </h6>
                                        <p class="card-subtitle">
                                            {{ formatDate(item.Tanggal) }}
                                        </p>
                                    </div>
                                </div>
                                <div class="card-time">
                                    {{ formatTimeSingle(item.Jam) }}
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="card-info">
                                    <i class="bi bi-person me-2"></i>
                                    <span>{{ item.Nama }}</span>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="d-flex gap-2">
                                    <button
                                        class="btn-action btn-view"
                                        @click.stop="showDetailsMethod(item)"
                                    >
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button
                                        v-if="
                                            disabledButton2(item.Tanggal_Assign)
                                        "
                                        class="btn-action btn-delete"
                                        @click.stop="
                                            deleteAlertButton(item.No_Transaksi)
                                        "
                                        :disabled="
                                            !disabledButton2(
                                                item.Tanggal_Assign
                                            )
                                        "
                                    >
                                        <i class="bi bi-trash"></i>
                                        Hapus
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination untuk Card View -->
                    <div class="pagination-container" v-if="totalPages > 1">
                        <button
                            class="page-btn"
                            @click="getData(prevLink)"
                            :disabled="currentPage === 1"
                        >
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <span class="page-info">
                            Halaman {{ currentPage }} dari {{ totalPages }}
                        </span>
                        <button
                            class="page-btn"
                            @click="getData(nextLink)"
                            :disabled="currentPage === totalPages"
                        >
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>

                <!-- View Toggle -->
                <div class="view-toggle-container">
                    <div class="view-toggle">
                        <button
                            class="toggle-btn"
                            :class="{ active: viewMode === 'table' }"
                            @click="viewMode = 'table'"
                            title="Table View"
                        >
                            <i class="bi bi-table"></i>
                            <span>Tabel</span>
                        </button>
                        <button
                            class="toggle-btn"
                            :class="{ active: viewMode === 'card' }"
                            @click="viewMode = 'card'"
                            title="Card View"
                        >
                            <i class="bi bi-grid-3x3-gap"></i>
                            <span>Kartu</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assign Overtime Modal -->
        <Modal
            v-if="showAssignModal"
            :show="showAssignModal"
            title="Penugasan Lembur"
            size="large"
            @close="closeAssignModal"
            class="overtime-assign-modal"
        >
            <template #body>
                <!-- Step Indicator - Improved -->
                <div class="step-indicator-wrapper mb-5">
                    <div class="step-indicator">
                        <div
                            v-for="step in 3"
                            :key="step"
                            class="step"
                            :class="{
                                'step-active': assignStep === step,
                                'step-completed': assignStep > step,
                            }"
                            @click="assignStep > step ? goToStep(step) : null"
                            :style="{
                                cursor:
                                    assignStep > step ? 'pointer' : 'default',
                            }"
                        >
                            <div class="step-icon-container">
                                <div class="step-icon">
                                    <i
                                        v-if="assignStep > step"
                                        class="bi bi-check-lg"
                                    ></i>
                                    <span v-else>{{ step }}</span>
                                </div>
                            </div>
                            <div class="step-content">
                                <div class="step-label">
                                    {{ getStepLabel(step) }}
                                </div>
                                <div class="step-subtitle">
                                    {{ getStepDescription(step) }}
                                </div>
                            </div>
                            <div v-if="step < 3" class="step-connector"></div>
                        </div>
                    </div>
                </div>

                <!-- Step Content Wrapper -->
                <div class="step-content-wrapper">
                    <!-- Step 1: Pilih Tanggal - Improved -->
                    <transition name="fade-slide" mode="out-in">
                        <div
                            v-if="assignStep === 1"
                            class="step-content step-1"
                        >
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon">
                                        <i class="bi bi-calendar-event"></i>
                                    </div>
                                    <div class="section-title-wrapper">
                                        <h4 class="section-title">
                                            Pilih Tanggal Lembur
                                        </h4>
                                        <p class="section-subtitle">
                                            Pilih tanggal untuk penugasan lembur
                                        </p>
                                    </div>
                                </div>

                                <div class="date-selection-container">
                                    <div class="date-navigation mb-4">
                                        <button
                                            class="btn-nav-date"
                                            @click="navigateDate(-1)"
                                            :disabled="!canNavigateDate(-1)"
                                        >
                                            <i class="bi bi-chevron-left"></i>
                                        </button>
                                        <div class="current-month">
                                            {{ formatCurrentMonth() }}
                                        </div>
                                        <button
                                            class="btn-nav-date"
                                            @click="navigateDate(1)"
                                            :disabled="!canNavigateDate(1)"
                                        >
                                            <i class="bi bi-chevron-right"></i>
                                        </button>
                                    </div>

                                    <div class="date-picker-grid">
                                        <div
                                            class="day-header"
                                            v-for="day in [
                                                'Min',
                                                'Sen',
                                                'Sel',
                                                'Rab',
                                                'Kam',
                                                'Jum',
                                                'Sab',
                                            ]"
                                        >
                                            {{ day }}
                                        </div>
                                        <div
                                            v-for="day in tanggal"
                                            :key="day"
                                            class="date-picker-item"
                                            :class="{
                                                'date-selected':
                                                    assignForm.selectedDate ===
                                                    day,
                                                'date-today': isToday(day),
                                                'date-weekend': isWeekend(day),
                                                'date-disabled':
                                                    !isDateAvailable(day),
                                            }"
                                            @click="selectDate(day)"
                                        >
                                            <div class="date-number">
                                                {{ formatDayNumber(day) }}
                                            </div>
                                            <div
                                                v-if="isToday(day)"
                                                class="date-badge today-badge"
                                            >
                                                Hari Ini
                                            </div>
                                            <div
                                                class="date-check"
                                                v-if="
                                                    assignForm.selectedDate ===
                                                    day
                                                "
                                            >
                                                <i
                                                    class="bi bi-check-circle"
                                                ></i>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="date-selection-info mt-4">
                                        <div
                                            class="selected-date-display"
                                            v-if="assignForm.selectedDate"
                                        >
                                            <i
                                                class="bi bi-calendar-check text-primary me-2"
                                            ></i>
                                            <span class="fw-medium"
                                                >Tanggal dipilih:</span
                                            >
                                            <span class="ms-2">{{
                                                formatDateLong(
                                                    assignForm.selectedDate
                                                )
                                            }}</span>
                                        </div>
                                        <div class="text-muted" v-else>
                                            <i
                                                class="bi bi-info-circle me-2"
                                            ></i>
                                            Pilih tanggal untuk melanjutkan
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Pilih Waktu & Alasan - Improved -->
                        <div
                            v-else-if="assignStep === 2"
                            class="step-content step-2"
                        >
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon">
                                        <i class="bi bi-clock"></i>
                                    </div>
                                    <div class="section-title-wrapper">
                                        <h4 class="section-title">
                                            Pilih Waktu & Alasan
                                        </h4>
                                        <p class="section-subtitle">
                                            Tentukan waktu selesai dan alasan
                                            lembur
                                        </p>
                                    </div>
                                </div>

                                <div class="time-selection-container">
                                    <div class="row g-4">
                                        <div class="col-lg-6">
                                            <div class="form-card">
                                                <label class="form-label">
                                                    <i
                                                        class="bi bi-stopwatch me-2"
                                                    ></i>
                                                    Waktu Selesai Lembur
                                                </label>
                                                <div class="time-input-wrapper">
                                                    <input
                                                        type="time"
                                                        class="form-control form-control-lg"
                                                        v-model="
                                                            assignForm.endTime
                                                        "
                                                        :min="getMinEndTime()"
                                                        required
                                                    />
                                                    <div
                                                        class="time-input-helper"
                                                    >
                                                        <small
                                                            class="text-muted"
                                                        >
                                                            Minimal:
                                                            {{
                                                                getMinEndTime() ||
                                                                "Setelah jam kerja"
                                                            }}
                                                        </small>
                                                    </div>
                                                </div>
                                                <div
                                                    class="estimated-duration mt-3"
                                                    v-if="assignForm.endTime"
                                                >
                                                    <i
                                                        class="bi bi-hourglass-split me-2 text-primary"
                                                    ></i>
                                                    <span
                                                        >Durasi estimasi:
                                                        <strong>{{
                                                            calculateOvertimeDuration()
                                                        }}</strong></span
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="form-card">
                                                <label class="form-label">
                                                    <i
                                                        class="bi bi-chat-text me-2"
                                                    ></i>
                                                    Alasan Lembur
                                                    <span class="text-danger"
                                                        >*</span
                                                    >
                                                </label>
                                                <textarea
                                                    class="form-control"
                                                    v-model="assignForm.reason"
                                                    rows="5"
                                                    placeholder="Contoh: Menyelesaikan proyek akhir bulan, Menangani permintaan mendesak dari klien, dll."
                                                    required
                                                    maxlength="500"
                                                ></textarea>
                                                <div class="form-footer">
                                                    <small class="text-muted">
                                                        {{
                                                            assignForm.reason
                                                                .length
                                                        }}/500 karakter
                                                    </small>
                                                    <small
                                                        v-if="
                                                            !assignForm.reason
                                                        "
                                                        class="text-danger"
                                                    >
                                                        Alasan harus diisi
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="step-summary mt-4"
                                        v-if="assignForm.selectedDate"
                                    >
                                        <div class="summary-card">
                                            <div class="summary-item">
                                                <span class="summary-label"
                                                    >Tanggal:</span
                                                >
                                                <span class="summary-value">{{
                                                    formatDateLong(
                                                        assignForm.selectedDate
                                                    )
                                                }}</span>
                                            </div>
                                            <div
                                                class="summary-item"
                                                v-if="assignForm.endTime"
                                            >
                                                <span class="summary-label"
                                                    >Selesai:</span
                                                >
                                                <span class="summary-value">{{
                                                    formatTime(
                                                        assignForm.endTime
                                                    )
                                                }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Pilih Karyawan - Improved -->
                        <div
                            v-else-if="assignStep === 3"
                            class="step-content step-3"
                        >
                            <div class="form-section">
                                <div class="section-header mb-4">
                                    <div class="section-icon">
                                        <i class="bi bi-people-fill"></i>
                                    </div>
                                    <div class="section-title-wrapper">
                                        <h4 class="section-title">
                                            Pilih Karyawan
                                        </h4>
                                        <p class="section-subtitle">
                                            Pilih karyawan untuk penugasan
                                            lembur
                                        </p>
                                    </div>
                                    <div class="selection-badge">
                                        <span
                                            class="badge bg-primary rounded-pill"
                                        >
                                            {{
                                                assignForm.selectedEmployees
                                                    .length
                                            }}
                                            terpilih
                                        </span>
                                    </div>
                                </div>

                                <div class="employee-selection-container">
                                    <!-- Search and Filter -->
                                    <div class="selection-toolbar mb-4">
                                        <div class="search-wrapper">
                                            <i
                                                class="bi bi-search search-icon"
                                            ></i>
                                            <input
                                                type="text"
                                                class="form-control search-input"
                                                placeholder="Cari nama atau divisi..."
                                                v-model="assignSearchQuery"
                                                :disabled="loading"
                                            />
                                            <button
                                                class="btn-clear-search"
                                                v-if="assignSearchQuery"
                                                @click="assignSearchQuery = ''"
                                            >
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>

                                        <div class="filter-actions">
                                            <div
                                                class="btn-group filter-buttons"
                                            >
                                                <button
                                                    class="btn btn-outline-secondary btn-sm"
                                                    @click="
                                                        toggleFilter(
                                                            'available'
                                                        )
                                                    "
                                                    :class="{
                                                        active:
                                                            filterBy ===
                                                            'available',
                                                    }"
                                                >
                                                    <i
                                                        class="bi bi-check-circle me-1"
                                                    ></i>
                                                    Tersedia
                                                </button>
                                                <button
                                                    class="btn btn-outline-secondary btn-sm"
                                                    @click="
                                                        toggleFilter('selected')
                                                    "
                                                    :class="{
                                                        active:
                                                            filterBy ===
                                                            'selected',
                                                    }"
                                                >
                                                    <i
                                                        class="bi bi-person-check me-1"
                                                    ></i>
                                                    Terpilih
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Loading State -->
                                    <div
                                        v-if="loading"
                                        class="loading-container"
                                    >
                                        <div class="spinner-container">
                                            <div
                                                class="spinner-border text-primary"
                                                role="status"
                                            ></div>
                                            <p class="mt-3 text-muted">
                                                Memuat data karyawan...
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Empty State -->
                                    <div
                                        v-else-if="
                                            filteredAssignEmployees.length === 0
                                        "
                                        class="empty-state"
                                    >
                                        <div class="empty-state-icon">
                                            <i class="bi bi-people fs-1"></i>
                                        </div>
                                        <h5 class="empty-state-title">
                                            Tidak ada karyawan ditemukan
                                        </h5>
                                        <p class="empty-state-text text-muted">
                                            {{
                                                assignSearchQuery
                                                    ? "Coba dengan kata kunci lain"
                                                    : "Tidak ada karyawan yang memenuhi kriteria untuk tanggal ini"
                                            }}
                                        </p>
                                        <button
                                            v-if="assignSearchQuery"
                                            class="btn btn-outline-primary"
                                            @click="assignSearchQuery = ''"
                                        >
                                            <i
                                                class="bi bi-arrow-counterclockwise me-2"
                                            ></i>
                                            Reset Pencarian
                                        </button>
                                    </div>

                                    <!-- Employee List -->
                                    <div v-else class="employee-list-container">
                                        <div class="employee-list">
                                            <div
                                                v-for="employee in filteredAssignEmployees"
                                                :key="employee.userId"
                                                class="employee-card"
                                                :class="{
                                                    'employee-selected':
                                                        isEmployeeSelected(
                                                            employee.userId
                                                        ),
                                                    'employee-unavailable':
                                                        !isEmployeeAvailable(
                                                            employee
                                                        ),
                                                }"
                                                @click="
                                                    toggleEmployeeSelection(
                                                        employee
                                                    )
                                                "
                                            >
                                                <div class="employee-card-body">
                                                    <!-- Selection Checkbox -->
                                                    <div
                                                        class="employee-selection-checkbox"
                                                    >
                                                        <div class="form-check">
                                                            <input
                                                                class="form-check-input"
                                                                type="checkbox"
                                                                :checked="
                                                                    isEmployeeSelected(
                                                                        employee.userId
                                                                    )
                                                                "
                                                                :disabled="
                                                                    !isEmployeeAvailable(
                                                                        employee
                                                                    )
                                                                "
                                                                @click.stop
                                                                @change="
                                                                    toggleEmployeeSelection(
                                                                        employee
                                                                    )
                                                                "
                                                            />
                                                        </div>
                                                    </div>

                                                    <!-- Avatar -->
                                                    <div
                                                        class="employee-avatar"
                                                    >
                                                        <div
                                                            class="avatar-circle"
                                                            :style="{
                                                                backgroundColor:
                                                                    getAvatarColor(
                                                                        employee.userId
                                                                    ),
                                                            }"
                                                        >
                                                            {{
                                                                getInitials(
                                                                    employee.name
                                                                )
                                                            }}
                                                            <span
                                                                class="avatar-status"
                                                                :class="{
                                                                    'status-available':
                                                                        isEmployeeAvailable(
                                                                            employee
                                                                        ),
                                                                    'status-unavailable':
                                                                        !isEmployeeAvailable(
                                                                            employee
                                                                        ),
                                                                }"
                                                            ></span>
                                                        </div>
                                                    </div>

                                                    <!-- Details -->
                                                    <div
                                                        class="employee-details"
                                                    >
                                                        <div
                                                            class="employee-name"
                                                        >
                                                            {{ employee.name }}
                                                            <span
                                                                v-if="
                                                                    !isEmployeeAvailable(
                                                                        employee
                                                                    )
                                                                "
                                                                class="unavailable-badge"
                                                            >
                                                                <i
                                                                    class="bi bi-exclamation-triangle me-1"
                                                                ></i>
                                                                Tidak Tersedia
                                                            </span>
                                                        </div>
                                                        <div
                                                            class="employee-info"
                                                        >
                                                            <span
                                                                class="department-badge"
                                                            >
                                                                <i
                                                                    class="bi bi-building me-1"
                                                                ></i>
                                                                {{
                                                                    employee.Divisi
                                                                }}
                                                            </span>
                                                            <span
                                                                class="shift-info"
                                                            >
                                                                <i
                                                                    class="bi bi-clock-history me-1"
                                                                ></i>
                                                                {{
                                                                    formatShiftTime(
                                                                        employee.Jam_Masuk,
                                                                        employee.Jam_Keluar
                                                                    )
                                                                }}
                                                            </span>
                                                        </div>

                                                        <!-- Unavailable Reason -->
                                                        <div
                                                            v-if="
                                                                !isEmployeeAvailable(
                                                                    employee
                                                                )
                                                            "
                                                            class="unavailable-reason"
                                                        >
                                                            <small
                                                                class="text-danger"
                                                            >
                                                                <i
                                                                    class="bi bi-info-circle me-1"
                                                                ></i>
                                                                {{
                                                                    getUnavailableReason(
                                                                        employee
                                                                    )
                                                                }}
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Bulk Actions -->
                                        <div class="bulk-actions mt-4">
                                            <div class="row align-items-center">
                                                <div class="col-md-8">
                                                    <div class="selection-info">
                                                        <span
                                                            class="text-muted me-3"
                                                        >
                                                            <i
                                                                class="bi bi-person-check me-1"
                                                            ></i>
                                                            {{
                                                                assignForm
                                                                    .selectedEmployees
                                                                    .length
                                                            }}
                                                            dari
                                                            {{
                                                                availableEmployeesCount
                                                            }}
                                                            tersedia
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 text-end">
                                                    <div
                                                        class="btn-group"
                                                        role="group"
                                                    >
                                                        <button
                                                            class="btn btn-outline-primary btn-sm"
                                                            @click="
                                                                selectAllAvailableEmployees
                                                            "
                                                            :disabled="
                                                                !canSelectAll
                                                            "
                                                        >
                                                            <i
                                                                class="bi bi-check-all me-1"
                                                            ></i>
                                                            Pilih Semua
                                                        </button>
                                                        <button
                                                            class="btn btn-outline-danger btn-sm"
                                                            @click="
                                                                clearEmployeeSelection
                                                            "
                                                            :disabled="
                                                                assignForm
                                                                    .selectedEmployees
                                                                    .length ===
                                                                0
                                                            "
                                                        >
                                                            <i
                                                                class="bi bi-x-circle me-1"
                                                            ></i>
                                                            Hapus
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </transition>
                </div>
            </template>

            <template #footer>
                <div class="modal-footer-actions">
                    <div class="d-flex justify-content-between w-100">
                        <div class="footer-left">
                            <button
                                v-if="assignStep > 1"
                                class="btn btn-outline-secondary"
                                @click="prevAssignStep"
                                :disabled="isSubmitting"
                            >
                                <i class="bi bi-arrow-left me-2"></i>
                                Kembali
                            </button>
                        </div>

                        <div class="footer-center">
                            <div class="step-progress">
                                <span class="step-current">{{
                                    assignStep
                                }}</span>
                                <span class="step-separator">/</span>
                                <span class="step-total">3</span>
                            </div>
                        </div>

                        <div class="footer-right">
                            <button
                                class="btn btn-light me-2"
                                @click="closeAssignModal"
                                :disabled="isSubmitting"
                            >
                                Batal
                            </button>

                            <button
                                v-if="assignStep < 3"
                                class="btn btn-primary"
                                @click="nextAssignStep"
                                :disabled="
                                    !canProceedToNextStep || isSubmitting
                                "
                            >
                                Lanjutkan
                                <i class="bi bi-arrow-right ms-2"></i>
                            </button>

                            <button
                                v-if="assignStep === 3"
                                class="btn btn-success"
                                @click="submitOvertime"
                                :disabled="
                                    isSubmitting ||
                                    assignForm.selectedEmployees.length === 0
                                "
                            >
                                <div
                                    v-if="isSubmitting"
                                    class="d-flex align-items-center"
                                >
                                    <span
                                        class="spinner-border spinner-border-sm me-2"
                                        role="status"
                                    ></span>
                                    Menyimpan...
                                </div>
                                <div v-else class="d-flex align-items-center">
                                    <i class="bi bi-send-check me-2"></i>
                                    Ajukan Lembur
                                    <span
                                        class="badge bg-white text-success ms-2"
                                    >
                                        {{
                                            assignForm.selectedEmployees.length
                                        }}
                                    </span>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </Modal>

        <!-- Detail Modal -->
        <Modal
            v-if="showDetailModal"
            :show="showDetailModal"
            :title="`Detail Transaksi - ${
                selectedTransaction?.No_Transaksi || ''
            }`"
            size="large"
            @close="closeDetailModal"
        >
            <template #body>
                <div v-if="detailLoading" class="loading-state p-5 text-center">
                    <div
                        class="spinner-border text-primary"
                        role="status"
                    ></div>
                    <p class="mt-3">Memuat detail transaksi...</p>
                </div>

                <div v-else-if="selectedTransaction" class="detail-content">
                    <!-- Transaction Header -->
                    <div class="transaction-header mb-4">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label class="detail-label"
                                        >No. Transaksi:</label
                                    >
                                    <span class="detail-value">{{
                                        selectedTransaction.No_Transaksi
                                    }}</span>
                                </div>
                                <div class="detail-item">
                                    <label class="detail-label"
                                        >Tanggal Transaksi:</label
                                    >
                                    <span class="detail-value">{{
                                        formatDate(selectedTransaction.Tanggal)
                                    }}</span>
                                </div>
                                <div class="detail-item">
                                    <label class="detail-label"
                                        >Jam Transaksi:</label
                                    >
                                    <span class="detail-value">{{
                                        formatTimeSingle(
                                            selectedTransaction.Jam
                                        )
                                    }}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label class="detail-label"
                                        >User Transaksi:</label
                                    >
                                    <span class="detail-value">{{
                                        selectedTransaction.Nama
                                    }}</span>
                                </div>
                                <div class="detail-item">
                                    <label class="detail-label"
                                        >Kode Perusahaan:</label
                                    >
                                    <span class="detail-value">{{
                                        selectedTransaction.Kode_Perusahaan ||
                                        "-"
                                    }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Employee List -->
                    <div class="employee-details-section">
                        <h5 class="section-title mb-3">
                            <i class="bi bi-people me-2"></i>
                            Daftar Karyawan Lembur
                            <small class="text-muted"
                                >({{ detailTransaksi.length }} orang)</small
                            >
                        </h5>

                        <ModernDataTable
                            :data="detailTransaksi"
                            :columns="detailColumns"
                            :mobile-columns="detailMobileColumns"
                            :loading="detailLoading"
                            :show-pagination="false"
                            card-title-key="name"
                            card-subtitle-key="reason"
                            empty-message="Tidak ada detail karyawan ditemukan"
                        >
                            <!-- Custom slot untuk kolom Nama -->
                            <template #column-name="{ item }">
                                <div class="employee-info-cell">
                                    <div class="avatar-sm">
                                        {{ getInitials(item.name) }}
                                    </div>
                                    <div class="employee-details">
                                        <h6 class="employee-name">
                                            {{ item.name }}
                                        </h6>
                                    </div>
                                </div>
                            </template>

                            <!-- Custom slot untuk kolom Tanggal -->
                            <template #column-tanggal="{ item }">
                                {{ formatDate(item.start_time) }} -
                                {{ formatDate(item.end_time) }}
                            </template>

                            <!-- Custom slot untuk kolom Waktu -->
                            <template #column-waktu="{ item }">
                                {{ formatTime(item.start_time, item.end_time) }}
                            </template>

                            <!-- Custom slot untuk kolom Durasi -->
                            <template #column-durasi="{ item }">
                                {{
                                    formatDuration(
                                        item.start_time,
                                        item.end_time
                                    )
                                }}
                            </template>

                            <!-- Custom slot untuk kolom Alasan -->
                            <template #column-alasan="{ item }">
                                {{ truncateText(item.reason, 30) }}
                            </template>

                            <!-- Custom slot untuk kolom Status -->
                            <template #column-status="{ item }">
                                <Badge
                                    :type="getCheckoutBadgeType(item.CHECKOUT)"
                                >
                                    {{ item.CHECKOUT || "Belum Checkout" }}
                                </Badge>
                            </template>

                            <!-- Custom slot untuk kolom Aksi -->
                            <template #column-actions="{ item }">
                                <div class="action-cell">
                                    <button
                                        v-if="disabledButton2(item.end_time)"
                                        class="btn-action btn-delete"
                                        @click.stop="
                                            deleteUserButton(item.Urut_Oto)
                                        "
                                        v-tooltip="'Hapus'"
                                        :disabled="
                                            !disabledButton2(item.end_time)
                                        "
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </template>
                        </ModernDataTable>
                    </div>
                </div>
            </template>

            <template #footer>
                <button
                    class="btn-modern btn-secondary"
                    @click="closeDetailModal"
                >
                    Tutup
                </button>
            </template>
        </Modal>

        <!-- Alert Modal -->
        <Modal
            v-if="showAlertModal"
            :show="showAlertModal"
            :title="titleAlertModal"
            size="medium"
            @close="closeAlertModal"
        >
            <template #body>
                <div class="confirmation-content text-center">
                    <i
                        class="bi bi-exclamation-triangle fs-1 text-warning mb-3"
                    ></i>
                    <p class="mb-0">{{ bodyAlert }}</p>
                </div>
            </template>

            <template #footer>
                <button
                    class="btn-modern btn-secondary"
                    @click="closeAlertModal"
                >
                    Batal
                </button>
                <button
                    class="btn-modern btn-danger"
                    @click="yesModal"
                    :disabled="loading"
                >
                    <i v-if="loading" class="bi bi-arrow-repeat spin me-2"></i>
                    <i v-else class="bi bi-check-lg me-2"></i>
                    {{ buttonAlertModal }}
                </button>
            </template>
        </Modal>

        <!-- Calendar Export Modal -->
        <Modal
            v-if="dialogVisible"
            :show="dialogVisible"
            title="Export Data Lembur"
            size="medium"
            @close="handleCancel"
        >
            <template #body>
                <div class="calendar-container">
                    <el-calendar v-model="calendarDate">
                        <template #date-cell="{ data }">
                            <div
                                class="cell-wrapper"
                                :class="getCellClass(data)"
                                @click="handleDateClick(data)"
                            >
                                <div class="date-cell">
                                    {{ data.day.split("-")[2] }}
                                </div>
                            </div>
                        </template>
                    </el-calendar>

                    <div class="date-range-display mt-3">
                        <div
                            v-if="tempStartDate && tempEndDate"
                            class="alert alert-info"
                        >
                            <i class="bi bi-calendar-range me-2"></i>
                            Periode yang dipilih:
                            {{ formatDate(tempStartDate) }} s.d
                            {{ formatDate(tempEndDate) }}
                        </div>
                        <div
                            v-else-if="tempStartDate"
                            class="alert alert-warning"
                        >
                            <i class="bi bi-info-circle me-2"></i>
                            Pilih tanggal akhir untuk menyelesaikan range
                        </div>
                    </div>
                </div>
            </template>

            <template #footer>
                <div class="footer-actions">
                    <button
                        class="btn-modern btn-secondary"
                        @click="handleCancel"
                    >
                        Batal
                    </button>
                    <button
                        class="btn-modern btn-primary"
                        @click="confirmDate"
                        :disabled="!tempStartDate || !tempEndDate"
                    >
                        <i class="bi bi-download me-2"></i>
                        Export Data
                    </button>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import axios from "axios";
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import { ElMessage, ElNotification, ElCalendar } from "element-plus";
import { debounce } from "lodash";
import HeaderKpi from "./components/ui/header.vue";
import Modal from "./KPI/components/ui/modalV2-noir.vue";
import QuickStat from "./KPI/components/ui/quickStat.vue";
import ModernDataTable from "./components/ui/table-noir.vue";
import FilterPanel from "./components/ui/Filter/modernFilter.vue";
import Badge from "./components/ui/badge.vue";

export default {
    components: {
        DotLottieVue,
        ElCalendar,
        HeaderKpi,
        Modal,
        QuickStat,
        ModernDataTable,
        FilterPanel,
        Badge,
    },
    props: {
        tanggal: Array,
        shiftList: Array,
    },
    data() {
        return {
            data: [],
            totalData: 0,
            nextLink: null,
            prevLink: null,
            totalPages: 0,
            currentPage: 1,
            itemsPerPage: 10,

            serverTime: null,
            TanggalSekarang: new Date(),

            // Filter & Search
            tableFilters: {
                search: "",
                tanggal: null,
            },

            // View Mode
            viewMode: "table",

            // Export
            dialogVisible: false,
            tempStartDate: null,
            tempEndDate: null,
            calendarDate: new Date(),

            // Detail Modal
            showDetailModal: false,
            selectedTransaction: null,
            detailTransaksi: [],
            detailLoading: false,

            // Alert Modal
            showAlertModal: false,
            titleAlertModal: "",
            buttonAlertModal: "",
            bodyAlert: "",
            yesModal: null,

            // Assign Modal
            showAssignModal: false,
            assignStep: 1,
            assignSearchQuery: "",
            assignForm: {
                selectedDate: null,
                endTime: null,
                reason: "",
                selectedEmployees: [],
            },
            userChoose: [],
            isSubmitting: false,

            // Loading
            loading: false,
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
                    title: "Total Transaksi",
                    value: this.totalData,
                    unit: "Transaksi",
                    icon: "bi bi-file-text text-primary",
                    bgIcon: "bg-primary-light",
                    size: "col-4",
                },
                {
                    title: "Hari Ini",
                    value: this.getTodayCount(),
                    unit: "Transaksi",
                    icon: "bi bi-calendar-day text-success",
                    bgIcon: "bg-success-light",
                    size: "col-4",
                },
                {
                    title: "Pending",
                    value: 0,
                    unit: "Transaksi",
                    icon: "bi bi-clock-history text-warning",
                    bgIcon: "bg-warning-light",
                    size: "col-4",
                },
            ];
        },

        filterConfig() {
            return {
                searchable: true,
                searchPlaceholder: "Cari nomor transaksi atau nama...",
                searchCol: 3,
                showResultsCount: true,
                showActiveFilters: true,
                showActions: true,
                showReset: true,
                showClearAll: true,
                mobileTrigger: true,
                filters: [
                    {
                        key: "tanggal",
                        label: "Filter Tanggal",
                        type: "date",
                        col: 6,
                        dateType: "single",
                    },
                    {
                        key: "export",
                        label: "Export Data",
                        type: "button",
                        col: 6,
                        buttonText: "Export Excel",
                        buttonIcon: "bi bi-download",
                        buttonClass: "btn-primary",
                        action: this.openExportDialog,
                    },
                ],
            };
        },

        tableColumns() {
            return [
                {
                    key: "No_Transaksi",
                    title: "No. Transaksi",
                    icon: "bi bi-hash",
                    showCount: true,
                    sticky: true,
                    cellClass: "employee-cell",
                },
                {
                    key: "Tanggal",
                    title: "Tanggal",
                    icon: "bi bi-calendar",
                    type: "date",
                    formatter: (value) => this.formatDate(value),
                },
                {
                    key: "Jam",
                    title: "Jam",
                    icon: "bi bi-clock",
                    formatter: (value) => this.formatTimeSingle(value),
                },
                {
                    key: "User",
                    title: "User",
                    icon: "bi bi-person",
                    formatter: (value, item) => item.Nama,
                },
                {
                    key: "actions",
                    title: "Aksi",
                    icon: "bi bi-three-dots",
                    showInMobile: false,
                },
            ];
        },

        mobileColumns() {
            return [
                {
                    key: "No_Transaksi",
                    title: "No. Transaksi",
                    icon: "bi bi-hash",
                },
                {
                    key: "Tanggal",
                    title: "Tanggal",
                    icon: "bi bi-calendar",
                },
                {
                    key: "Jam",
                    title: "Jam",
                    icon: "bi bi-clock",
                },
                {
                    key: "User",
                    title: "User",
                    icon: "bi bi-person",
                },
            ];
        },

        detailColumns() {
            return [
                {
                    key: "name",
                    title: "Nama",
                    icon: "bi bi-person",
                    sticky: true,
                },
                {
                    key: "tanggal",
                    title: "Tanggal",
                    icon: "bi bi-calendar",
                },
                {
                    key: "waktu",
                    title: "Waktu",
                    icon: "bi bi-clock",
                },
                {
                    key: "durasi",
                    title: "Durasi",
                    icon: "bi bi-hourglass",
                },
                {
                    key: "alasan",
                    title: "Alasan",
                    icon: "bi bi-chat-text",
                },
                {
                    key: "status",
                    title: "Status",
                    icon: "bi bi-check-circle",
                },
                {
                    key: "actions",
                    title: "Aksi",
                    icon: "bi bi-three-dots",
                },
            ];
        },

        detailMobileColumns() {
            return [
                {
                    key: "name",
                    title: "Nama",
                    icon: "bi bi-person",
                },
                {
                    key: "tanggal",
                    title: "Tanggal",
                    icon: "bi bi-calendar",
                },
                {
                    key: "waktu",
                    title: "Waktu",
                    icon: "bi bi-clock",
                },
                {
                    key: "alasan",
                    title: "Alasan",
                    icon: "bi bi-chat-text",
                },
            ];
        },

        filteredAssignEmployees() {
            let filtered = this.userChoose.map((emp) => {
                const isAvailable = this.isEmployeeAvailable(emp);
                const isSelected = this.isEmployeeSelected(emp.userId);

                return {
                    ...emp,
                    isAvailable,
                    isSelected,
                };
            });

            // Filter berdasarkan pencarian
            if (this.assignSearchQuery) {
                filtered = filtered.filter((employee) =>
                    employee.name
                        .toLowerCase()
                        .includes(this.assignSearchQuery.toLowerCase())
                );
            }

            // Urutkan: selected > available > disabled
            filtered.sort((a, b) => {
                if (a.isSelected && !b.isSelected) return -1;
                if (!a.isSelected && b.isSelected) return 1;
                if (a.isAvailable && !b.isAvailable) return -1;
                if (!a.isAvailable && b.isAvailable) return 1;
                return 0;
            });

            return filtered;
        },

        canSelectAll() {
            const availableEmployees = this.userChoose.filter(
                (emp) =>
                    this.isEmployeeAvailable(emp) &&
                    !this.isEmployeeSelected(emp.userId)
            );
            return availableEmployees.length > 0;
        },

        canProceedToNextStep() {
            if (this.assignStep === 1) {
                return this.assignForm.selectedDate;
            }
            if (this.assignStep === 2) {
                return this.assignForm.endTime && this.assignForm.reason;
            }
            if (this.assignStep === 3) {
                return this.assignForm.selectedEmployees.length > 0;
            }
            return true;
        },
    },
    watch: {
        "tableFilters.search": {
            handler() {
                this.debouncedSearch();
            },
        },
        "tableFilters.tanggal": {
            handler() {
                this.debouncedSearch();
            },
        },
    },
    methods: {
        // Formatting Methods
        formatDatePanjang(tanggal) {
            return new Date(tanggal).toLocaleDateString("id-ID", {
                weekday: "long",
                year: "numeric",
                month: "long",
                day: "numeric",
            });
        },

        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString("id-ID", {
                day: "2-digit",
                month: "short",
                year: "numeric",
            });
        },

        formatDateString(date) {
            if (!date) return "";
            return new Date(date).toLocaleDateString("id-ID", {
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
            });
        },

        formatTimeSingle(timeString) {
            if (!timeString) return "";
            return timeString.split(":").slice(0, 2).join(":");
        },

        formatTime(start_time, end_time) {
            const start = new Date(start_time);
            const end = new Date(end_time);

            const startTime = start.toLocaleTimeString("id-ID", {
                hour: "2-digit",
                minute: "2-digit",
            });
            const endTime = end.toLocaleTimeString("id-ID", {
                hour: "2-digit",
                minute: "2-digit",
            });

            return `${startTime} - ${endTime}`;
        },

        formatDuration(start_time, end_time) {
            const start = new Date(start_time);
            const end = new Date(end_time);
            const duration = end - start;

            const hours = Math.floor(duration / 1000 / 60 / 60);
            const minutes = Math.floor(
                (duration % (1000 * 60 * 60)) / (1000 * 60)
            );

            return `${hours} jam ${minutes} menit`;
        },

        formatShiftTime(jamMasuk, jamKeluar) {
            return `${this.formatTimeSingle(
                jamMasuk
            )} - ${this.formatTimeSingle(jamKeluar)}`;
        },

        formatDateShort(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString("id-ID", {
                day: "2-digit",
                month: "short",
            });
        },

        getDayName(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString("id-ID", { weekday: "short" });
        },

        isToday(dateString) {
            const today = new Date().toISOString().split("T")[0];
            return dateString === today;
        },

        getInitials(name) {
            if (!name) return "??";
            return name
                .split(" ")
                .map((word) => word[0])
                .join("")
                .substring(0, 2)
                .toUpperCase();
        },

        truncateText(text, length) {
            if (!text) return "";
            return text.length > length
                ? text.substring(0, length) + "..."
                : text;
        },

        // API Methods
        async getData(url = null) {
            try {
                this.loading = true;
                const params = new URLSearchParams();

                if (this.tableFilters.search) {
                    params.append("search", this.tableFilters.search);
                }
                if (this.tableFilters.tanggal) {
                    params.append("tanggalFilter", this.tableFilters.tanggal);
                }

                const endpoint =
                    url || `/overtime/getData?${params.toString()}`;
                const response = await axios.get(endpoint);

                if (response.status === 200) {
                    this.data = response.data.data.data;
                    this.prevLink = response.data.data.prev_page_url;
                    this.nextLink = response.data.data.next_page_url;
                    this.totalData = response.data.data.total;
                    this.currentPage = response.data.data.current_page;
                    this.totalPages = response.data.data.last_page;
                }
            } catch (error) {
                console.error("Error fetching data:", error);
                ElMessage.error("Gagal memuat data lembur");
                this.data = [];
                this.totalData = 0;
                this.totalPages = 0;
            } finally {
                this.loading = false;
            }
        },

        debouncedSearch: debounce(function () {
            this.getData();
        }, 500),

        async getTimeServer() {
            try {
                const res = await axios.get("/getTime");
                if (res.status === 200) {
                    this.serverTime = new Date(
                        new Date(res.data.time).toLocaleString("en-US", {
                            timeZone: "Asia/Jakarta",
                        })
                    );
                }
            } catch (error) {
                console.error("Error fetching server time:", error);
                this.serverTime = new Date();
            }
        },

        // Filter & Search Methods
        handleFiltersUpdate(updatedFilters) {
            this.tableFilters = { ...this.tableFilters, ...updatedFilters };
            this.currentPage = 1;
            this.getData();
        },

        handleSearch(searchQuery) {
            this.tableFilters.search = searchQuery;
            this.currentPage = 1;
            this.getData();
        },

        handlePageChange(page) {
            if (page > this.currentPage && this.nextLink) {
                this.getData(this.nextLink);
            } else if (page < this.currentPage && this.prevLink) {
                this.getData(this.prevLink);
            }
        },

        clearFilters() {
            this.tableFilters = {
                search: "",
                tanggal: null,
            };
            this.currentPage = 1;
            this.getData();
        },

        // Utility Methods
        getTodayCount() {
            const today = new Date().toISOString().split("T")[0];
            return this.data.filter((item) => {
                const itemDate = new Date(item.Tanggal)
                    .toISOString()
                    .split("T")[0];
                return itemDate === today;
            }).length;
        },

        disabledButton2(date) {
            const tanggalSekarang = new Date(this.TanggalSekarang);
            const tanggalCek = new Date(date);

            tanggalSekarang.setHours(0, 0, 0, 0);
            tanggalCek.setHours(0, 0, 0, 0);

            return tanggalSekarang.getTime() <= tanggalCek.getTime();
        },

        // Badge Type Methods
        getDepartmentBadgeType(department) {
            const colors = ["primary", "success", "warning", "danger", "info"];
            const index = Math.abs(this.hashCode(department)) % colors.length;
            return colors[index];
        },

        getCheckoutBadgeType(status) {
            if (status === "SUDAH") return "success";
            if (status === "BELUM") return "warning";
            return "secondary";
        },

        hashCode(str) {
            let hash = 0;
            for (let i = 0; i < str.length; i++) {
                hash = (hash << 5) - hash + str.charCodeAt(i);
                hash |= 0;
            }
            return hash;
        },

        // Detail Modal Methods
        async showDetailsMethod(item) {
            this.selectedTransaction = item;
            this.detailLoading = true;
            this.showDetailModal = true;

            try {
                const response = await axios.get("/overtime/getDetails", {
                    params: {
                        No_Transaksi: item.No_Transaksi,
                    },
                });

                if (response.data.status === 200) {
                    this.detailTransaksi = response.data.data;
                }
            } catch (error) {
                console.error("Error fetching detail:", error);
                ElMessage.error("Gagal memuat detail transaksi");
                this.detailTransaksi = [];
            } finally {
                this.detailLoading = false;
            }
        },

        closeDetailModal() {
            this.showDetailModal = false;
            this.selectedTransaction = null;
            this.detailTransaksi = [];
        },

        // Alert Modal Methods
        deleteAlertButton(noTransaksi) {
            this.titleAlertModal = "Hapus Transaksi";
            this.buttonAlertModal = "Hapus";
            this.bodyAlert = `Apakah Anda yakin menghapus transaksi ${noTransaksi}?`;
            this.yesModal = () => this.destroyItem(noTransaksi);
            this.showAlertModal = true;
        },

        deleteUserButton(urutOto) {
            this.titleAlertModal = "Hapus Karyawan Lembur";
            this.buttonAlertModal = "Hapus";
            this.bodyAlert =
                "Apakah Anda yakin menghapus karyawan dari lembur ini?";
            this.yesModal = () => this.destroyUser(urutOto);
            this.showAlertModal = true;
        },

        closeAlertModal() {
            this.showAlertModal = false;
            this.titleAlertModal = "";
            this.buttonAlertModal = "";
            this.bodyAlert = "";
            this.yesModal = null;
        },

        async destroyItem(noTransaksi) {
            try {
                this.loading = true;
                const response = await axios.post("/overtime/destroyOvertime", {
                    params: {
                        No_Transaksi: noTransaksi,
                    },
                });

                if (response.status === 200) {
                    ElNotification({
                        title: "Berhasil",
                        message: "Transaksi lembur berhasil dihapus",
                        type: "success",
                    });
                    this.getData();
                    this.closeAlertModal();
                }
            } catch (error) {
                console.error("Error deleting item:", error);
                ElNotification({
                    title: "Gagal",
                    message: "Gagal menghapus transaksi",
                    type: "error",
                });
            } finally {
                this.loading = false;
            }
        },

        async destroyUser(urutOto) {
            try {
                this.loading = true;
                const response = await axios.post(
                    "/overtime/destroyUserOvertime",
                    {
                        params: {
                            urut_Oto: urutOto,
                        },
                    }
                );

                if (response.status === 200) {
                    ElNotification({
                        title: "Berhasil",
                        message: "Karyawan berhasil dihapus dari lembur",
                        type: "success",
                    });
                    this.closeDetailModal();
                    this.getData();
                    this.closeAlertModal();
                }
            } catch (error) {
                console.error("Error deleting user:", error);
                ElNotification({
                    title: "Gagal",
                    message: "Gagal menghapus karyawan",
                    type: "error",
                });
            } finally {
                this.loading = false;
            }
        },

        // Assign Modal Methods
        openAssignModal() {
            console.log("as");
            this.showAssignModal = true;
            this.assignStep = 1;
            this.resetAssignForm();
        },

        closeAssignModal() {
            this.showAssignModal = false;
            this.assignStep = 1;
            this.resetAssignForm();
        },

        resetAssignForm() {
            this.assignForm = {
                selectedDate: null,
                endTime: null,
                reason: "",
                selectedEmployees: [],
            };
            this.assignSearchQuery = "";
            this.userChoose = [];
        },

        getStepLabel(step) {
            const labels = {
                1: "Tanggal",
                2: "Waktu & Alasan",
                3: "Karyawan",
            };
            return labels[step] || "";
        },

        async nextAssignStep() {
            if (!this.canProceedToNextStep) {
                ElMessage.warning("Harap lengkapi data terlebih dahulu");
                return;
            }

            if (this.assignStep === 1) {
                // Validasi tanggal
                if (!this.assignForm.selectedDate) {
                    ElMessage.warning("Harap pilih tanggal lembur");
                    return;
                }

                // Cek apakah tanggal tidak di masa lalu
                const selectedDate = new Date(this.assignForm.selectedDate);
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                if (selectedDate < today) {
                    ElMessage.warning(
                        "Tidak dapat memilih tanggal di masa lalu"
                    );
                    return;
                }

                this.assignStep++;
            } else if (this.assignStep === 2) {
                // Validasi waktu dan alasan
                if (!this.assignForm.endTime) {
                    ElMessage.warning("Harap pilih waktu selesai lembur");
                    return;
                }

                if (
                    !this.assignForm.reason ||
                    this.assignForm.reason.trim().length < 3
                ) {
                    ElMessage.warning("Alasan lembur minimal 3 karakter");
                    return;
                }

                // Load karyawan untuk tanggal yang dipilih
                await this.loadAvailableEmployees();
                this.assignStep++;
            } else if (this.assignStep === 3) {
                // Validasi pilihan karyawan
                if (this.assignForm.selectedEmployees.length === 0) {
                    ElMessage.warning("Harap pilih minimal 1 karyawan");
                    return;
                }

                this.submitOvertime();
            }
        },

        prevAssignStep() {
            if (this.assignStep > 1) {
                this.assignStep--;
            }
        },

        async loadAvailableEmployees() {
            try {
                this.loading = true;
                await this.getTimeServer();

                const params = {
                    date: this.assignForm.selectedDate,
                    time: this.assignForm.endTime,
                };

                const response = await axios.get("/overtime/getUserActive", {
                    params,
                });
                this.userChoose = response.data.data || [];
            } catch (error) {
                console.error("Error loading employees:", error);
                this.userChoose = [];

                if (error.response?.status === 404) {
                    ElMessage.warning(
                        `Tidak ada karyawan tersedia pada tanggal ${this.formatDate(
                            this.assignForm.selectedDate
                        )}`
                    );
                } else {
                    ElMessage.error("Gagal memuat data karyawan");
                }
            } finally {
                this.loading = false;
            }
        },

        isEmployeeAvailable(employee) {
            return (
                employee.JamComparison === "TRUE" &&
                employee.lemburPernah === "FALSE" &&
                this.isTimeAfter(
                    employee.Jam_Masuk,
                    employee.Jam_Keluar,
                    employee.Nama_Shift
                )
            );
        },

        getUnavailableReason(employee) {
            if (employee.lemburPernah === "TRUE") {
                return "Sudah ditugaskan lembur hari ini";
            } else if (
                !this.isTimeAfter(
                    employee.Jam_Masuk,
                    employee.Jam_Keluar,
                    employee.Nama_Shift
                )
            ) {
                return "Batas waktu input sudah habis";
            } else if (employee.JamComparison === "FALSE") {
                return "Jam tidak memenuhi syarat";
            }
            return "Tidak tersedia";
        },

        isEmployeeSelected(userId) {
            return this.assignForm.selectedEmployees.some(
                (emp) => emp.id === userId
            );
        },

        toggleEmployeeSelection(employee) {
            if (!this.isEmployeeAvailable(employee)) {
                return;
            }

            const isSelected = this.isEmployeeSelected(employee.userId);

            if (isSelected) {
                this.assignForm.selectedEmployees =
                    this.assignForm.selectedEmployees.filter(
                        (emp) => emp.id !== employee.userId
                    );
            } else {
                this.assignForm.selectedEmployees.push({
                    id: employee.userId,
                    nama: employee.name,
                    divisi: employee.Divisi,
                    shift: employee.Nama_Shift,
                    jamMasuk: employee.Jam_Masuk,
                    jamKeluar: employee.Jam_Keluar,
                    waktu: this.assignForm.endTime,
                    reason: this.assignForm.reason,
                });
            }
        },

        selectAllAvailableEmployees() {
            const availableEmployees = this.userChoose.filter(
                (emp) =>
                    this.isEmployeeAvailable(emp) &&
                    !this.isEmployeeSelected(emp.userId)
            );

            availableEmployees.forEach((employee) => {
                this.assignForm.selectedEmployees.push({
                    id: employee.userId,
                    nama: employee.name,
                    divisi: employee.Divisi,
                    shift: employee.Nama_Shift,
                    jamMasuk: employee.Jam_Masuk,
                    jamKeluar: employee.Jam_Keluar,
                    waktu: this.assignForm.endTime,
                    reason: this.assignForm.reason,
                });
            });

            ElMessage.success(
                `${availableEmployees.length} karyawan ditambahkan`
            );
        },

        clearEmployeeSelection() {
            this.assignForm.selectedEmployees = [];
        },

        async submitOvertime() {
            if (this.assignForm.selectedEmployees.length === 0) {
                ElMessage.warning("Harap pilih minimal 1 karyawan");
                return;
            }

            try {
                this.isSubmitting = true;

                const response = await axios.post("/overtime/submit", {
                    params: {
                        Kode_Perusahaan: "001",
                        dataUsers: this.assignForm.selectedEmployees,
                    },
                });

                if (response.status === 200) {
                    ElNotification({
                        title: "Berhasil",
                        message: "Pengajuan lembur berhasil disimpan",
                        type: "success",
                    });

                    this.closeAssignModal();
                    this.getData();
                }
            } catch (error) {
                console.error("Error submitting overtime:", error);
                ElNotification({
                    title: "Gagal",
                    message: "Gagal menyimpan pengajuan lembur",
                    type: "error",
                });
            } finally {
                this.isSubmitting = false;
            }
        },

        // Time Validation Methods
        isTimeAfter(jamMasuk, jamKeluar, shiftType) {
            if (!this.assignForm.selectedDate) return false;

            const selectedDate = new Date(this.assignForm.selectedDate);
            const serverTime = this.serverTime || new Date();

            // Parse jamMasuk
            const [hmH, hmM = "0", hmS = "0"] = jamMasuk.split(":");
            let masukTime = new Date(selectedDate);
            masukTime.setHours(parseInt(hmH), parseInt(hmM), parseInt(hmS), 0);

            // Parse jamKeluar
            const [hkH, hkM = "0", hkS = "0"] = jamKeluar.split(":");
            let cutoffTime = new Date(selectedDate);
            cutoffTime.setHours(parseInt(hkH), parseInt(hkM), parseInt(hkS), 0);

            // Kalau shift lintas hari (jam masuk >= jam keluar) → jam keluar dianggap besok
            if (masukTime >= cutoffTime) {
                cutoffTime.setDate(cutoffTime.getDate() + 1);
            }

            // Tambahkan toleransi 4 jam
            cutoffTime.setHours(cutoffTime.getHours() + 4);

            return serverTime <= cutoffTime;
        },

        // Export Methods
        openExportDialog() {
            this.dialogVisible = true;
            this.tempStartDate = null;
            this.tempEndDate = null;
            this.calendarDate = new Date();
        },

        handleCancel() {
            this.dialogVisible = false;
            this.tempStartDate = null;
            this.tempEndDate = null;
        },

        handleDateClick(data) {
            const clickedDate = data.date;

            if (!this.tempStartDate) {
                this.tempStartDate = clickedDate;
            } else if (!this.tempEndDate) {
                if (clickedDate.getTime() < this.tempStartDate.getTime()) {
                    this.tempEndDate = this.tempStartDate;
                    this.tempStartDate = clickedDate;
                } else {
                    this.tempEndDate = clickedDate;
                }
            } else {
                this.tempStartDate = clickedDate;
                this.tempEndDate = null;
            }
        },

        getCellClass(data) {
            const classes = {};
            const currentDate = data.date.setHours(0, 0, 0, 0);
            const start = this.tempStartDate
                ? this.tempStartDate.setHours(0, 0, 0, 0)
                : null;
            const end = this.tempEndDate
                ? this.tempEndDate.setHours(0, 0, 0, 0)
                : null;

            if (!start) return classes;

            if (currentDate === start) classes["is-start"] = true;
            if (currentDate === end) classes["is-end"] = true;

            if (start && end && currentDate > start && currentDate < end) {
                classes["is-in-range"] = true;
            }

            return classes;
        },

        formatDateToString(date) {
            if (!date) return "";
            const year = date.getFullYear();
            const month = (date.getMonth() + 1).toString().padStart(2, "0");
            const day = date.getDate().toString().padStart(2, "0");
            return `${year}-${month}-${day}`;
        },

        async confirmDate() {
            if (!this.tempStartDate || !this.tempEndDate) {
                ElMessage.warning("Harap pilih tanggal mulai dan selesai");
                return;
            }

            const startDate = this.formatDateToString(this.tempStartDate);
            const endDate = this.formatDateToString(this.tempEndDate);

            await this.downloadExcel(startDate, endDate);
            this.handleCancel();
        },

        async downloadExcel(startDate, endDate) {
            try {
                this.loading = true;

                const response = await axios.get("/overtime/getExport", {
                    params: {
                        start: startDate,
                        end: endDate,
                    },
                });

                if (response.status === 200) {
                    const ExcelJS = await import("exceljs");
                    await this.exportCustomExcel(
                        response.data.data,
                        startDate,
                        endDate,
                        ExcelJS
                    );
                }
            } catch (error) {
                console.error("Error exporting data:", error);
                ElMessage.error("Gagal mengekspor data");
            } finally {
                this.loading = false;
            }
        },

        async exportCustomExcel(dataExport, startDate, endDate, ExcelJS) {
            try {
                const workbook = new ExcelJS.Workbook();
                const worksheet = workbook.addWorksheet("Data Lembur");

                // Styling
                const headerStyle = {
                    fill: {
                        type: "pattern",
                        pattern: "solid",
                        fgColor: { argb: "ffeb9c" },
                    },
                    font: { bold: true },
                    alignment: { vertical: "middle", horizontal: "center" },
                    border: {
                        top: { style: "thin" },
                        left: { style: "thin" },
                        bottom: { style: "thin" },
                        right: { style: "thin" },
                    },
                };

                const cellStyle = {
                    border: {
                        top: { style: "thin" },
                        left: { style: "thin" },
                        bottom: { style: "thin" },
                        right: { style: "thin" },
                    },
                    alignment: { vertical: "middle", horizontal: "left" },
                };

                // Header
                worksheet.mergeCells("B1:H1");
                worksheet.getCell("B1").value = "Employee Overtime Management";
                worksheet.getCell("B1").style = headerStyle;

                // Kolom
                worksheet.columns = [
                    { key: "tanggal", width: 15 },
                    { key: "noTrans", width: 20 },
                    { key: "nama", width: 30 },
                    { key: "tglLembur", width: 15 },
                    { key: "waktu", width: 20 },
                    { key: "durasi", width: 15 },
                    { key: "alasan", width: 40 },
                    { key: "status", width: 40 },
                ];

                // Fill data
                // ... (isi dengan logika pengisian data seperti sebelumnya)

                const buffer = await workbook.xlsx.writeBuffer();
                const blob = new Blob([buffer], {
                    type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                });

                const link = document.createElement("a");
                link.href = URL.createObjectURL(blob);
                link.download = `Export_Lembur_${this.formatDate(
                    startDate
                )}_sd_${this.formatDate(endDate)}.xlsx`;
                link.click();
                URL.revokeObjectURL(link.href);

                ElMessage.success("Export berhasil diunduh");
            } catch (error) {
                console.error("Error creating Excel:", error);
                throw error;
            }
        },
        checkScreenSize() {
            this.viewMode = window.innerWidth < 768 ? "card" : "table";
        },

        // Shift Methods
        async getShift() {
            try {
                this.loading = true;
                const response = await axios.get("/overtime/getShift", {
                    params: {
                        tanggal: this.assignForm.selectedDate,
                    },
                });

                if (response.status === 200) {
                    this.shiftList = response.data.data;
                }
            } catch (error) {
                console.error("Error fetching shift:", error);
            } finally {
                this.loading = false;
            }
        },
    },
    mounted() {
        this.getTimeServer();
        this.getData();

        // Set view mode berdasarkan ukuran layar
        this.checkScreenSize();
        window.addEventListener("resize", this.checkScreenSize);
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.checkScreenSize);
    },
};
</script>

<style scoped>
*,
*::before,
*::after {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
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
    --shadow-inset: inset 0 0px 0.3rem -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --shadow-lg-inset: inset 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --gradient-success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --gradient-info: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --gradient-dark: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
}
* {
    box-sizing: border-box;
}

/* Modal Container */
.overtime-assign-modal :deep(.modal-content) {
    border-radius: 12px;
    border: none;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
}

.overtime-assign-modal :deep(.modal-header) {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px 12px 0 0;
    padding: 1.5rem;
}

.overtime-assign-modal :deep(.modal-title) {
    font-weight: 600;
    font-size: 1.25rem;
}

.overtime-assign-modal :deep(.modal-body) {
    padding: 2rem;
    max-height: 70vh;
    overflow-y: auto;
}

/* Step Indicator */
.step-indicator-wrapper {
    position: relative;
}

.step-indicator {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    position: relative;
}

.step {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 2;
}

.step-icon-container {
    margin-bottom: 0.75rem;
}

.step-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    background: #f8f9fa;
    border: 2px solid #dee2e6;
    color: #6c757d;
    transition: all 0.3s ease;
}

.step-active .step-icon {
    background: #4f46e5;
    border-color: #4f46e5;
    color: white;
    transform: scale(1.1);
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
}

.step-completed .step-icon {
    background: #10b981;
    border-color: #10b981;
    color: white;
}

.step-content {
    text-align: center;
    max-width: 120px;
}

.step-label {
    font-weight: 600;
    font-size: 0.875rem;
    color: #495057;
    margin-bottom: 0.25rem;
}

.step-active .step-label {
    color: #4f46e5;
}

.step-completed .step-label {
    color: #10b981;
}

.step-subtitle {
    font-size: 0.75rem;
    color: #6c757d;
    line-height: 1.3;
}

.step-connector {
    position: absolute;
    top: 20px;
    left: 60%;
    right: -40%;
    height: 2px;
    background: #dee2e6;
    z-index: 1;
}

.step-completed .step-connector {
    background: #10b981;
}

/* Section Header */
.section-header {
    display: flex;
    align-items: flex-start;
    margin-bottom: 2rem;
    gap: 1rem;
}

.section-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.section-title-wrapper {
    flex: 1;
}

.section-title {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 0.25rem;
}

.section-subtitle {
    color: #718096;
    font-size: 0.875rem;
    margin: 0;
}

/* Date Selection */
.date-navigation {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.btn-nav-date {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 1px solid #e2e8f0;
    background: white;
    color: #4a5568;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.btn-nav-date:hover:not(:disabled) {
    background: #f7fafc;
    border-color: #cbd5e0;
}

.btn-nav-date:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.current-month {
    font-weight: 600;
    color: #2d3748;
    font-size: 1.125rem;
    min-width: 200px;
    text-align: center;
}

.date-picker-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.5rem;
}

.day-header {
    text-align: center;
    font-weight: 600;
    color: #718096;
    font-size: 0.875rem;
    padding: 0.5rem;
}

.date-picker-item {
    aspect-ratio: 1;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    border: 2px solid transparent;
    background: #f8fafc;
}

.date-picker-item:hover:not(.date-disabled) {
    background: #edf2f7;
    transform: translateY(-2px);
}

.date-selected {
    background: #4f46e5 !important;
    border-color: #4f46e5;
    color: white;
    box-shadow: 0 4px 6px rgba(79, 70, 229, 0.2);
}

.date-today {
    border-color: #10b981;
}

.date-weekend {
    color: #e53e3e;
}

.date-disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f1f5f9;
}

.date-number {
    font-weight: 600;
    font-size: 1.125rem;
}

.date-badge {
    position: absolute;
    bottom: 4px;
    font-size: 0.625rem;
    padding: 1px 4px;
    border-radius: 4px;
    background: #10b981;
    color: white;
}

.date-check {
    position: absolute;
    top: 4px;
    right: 4px;
    color: white;
}

.date-selection-info {
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
    border-left: 4px solid #4f46e5;
}

.selected-date-display {
    display: flex;
    align-items: center;
    color: #2d3748;
}

/* Time Selection */
.form-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    height: 100%;
    transition: all 0.3s ease;
}

.form-card:hover {
    border-color: #cbd5e0;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.form-label {
    font-weight: 600;
    color: #4a5568;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
}

.time-input-wrapper {
    position: relative;
}

.time-input-helper {
    margin-top: 0.5rem;
}

.estimated-duration {
    padding: 0.75rem;
    background: #f0f9ff;
    border-radius: 8px;
    border-left: 4px solid #3b82f6;
}

/* Step Summary */
.step-summary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 1.5rem;
    color: white;
}

.summary-card {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.summary-item {
    display: flex;
    flex-direction: column;
}

.summary-label {
    font-size: 0.875rem;
    opacity: 0.9;
    margin-bottom: 0.25rem;
}

.summary-value {
    font-weight: 600;
    font-size: 1rem;
}

/* Employee Selection */
.selection-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
}

.search-wrapper {
    flex: 1;
    min-width: 300px;
    position: relative;
}

.search-input {
    padding-left: 2.5rem;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.search-input:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #a0aec0;
}

.btn-clear-search {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #a0aec0;
    padding: 0.25rem;
}

.filter-buttons .btn.active {
    background: #4f46e5;
    color: white;
    border-color: #4f46e5;
}

/* Employee List */
.employee-list-container {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

.employee-card {
    border-bottom: 1px solid #f1f5f9;
    transition: all 0.2s ease;
    cursor: pointer;
}

.employee-card:last-child {
    border-bottom: none;
}

.employee-card:hover {
    background: #f8fafc;
}

.employee-selected {
    background: #f0f9ff !important;
    border-left: 4px solid #3b82f6;
}

.employee-unavailable {
    opacity: 0.7;
    cursor: not-allowed;
}

.employee-unavailable:hover {
    background: #fef2f2 !important;
}

.employee-card-body {
    display: flex;
    align-items: center;
    padding: 1rem;
    gap: 1rem;
}

.employee-selection-checkbox {
    flex-shrink: 0;
}

.employee-avatar {
    flex-shrink: 0;
}

.avatar-circle {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    position: relative;
}

.avatar-status {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid white;
}

.status-available {
    background: #10b981;
}

.status-unavailable {
    background: #ef4444;
}

.employee-details {
    flex: 1;
    min-width: 0;
}

.employee-name {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.unavailable-badge {
    font-size: 0.75rem;
    padding: 0.125rem 0.5rem;
    background: #fef2f2;
    color: #dc2626;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
}

.employee-info {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    font-size: 0.875rem;
}

.department-badge {
    background: #f3f4f6;
    color: #6b7280;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
}

.shift-info {
    color: #6b7280;
    display: inline-flex;
    align-items: center;
}

.unavailable-reason {
    margin-top: 0.5rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-state-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    background: #f8fafc;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #cbd5e0;
    font-size: 2rem;
}

.empty-state-title {
    font-weight: 600;
    color: #4a5568;
    margin-bottom: 0.5rem;
}

.empty-state-text {
    max-width: 400px;
    margin: 0 auto 1.5rem;
}

/* Loading State */
.loading-container {
    padding: 3rem 1rem;
    text-align: center;
}

.spinner-container {
    display: inline-block;
}

/* Bulk Actions */
.bulk-actions {
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

/* Modal Footer */
.modal-footer-actions {
    padding: 1.5rem;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 0 0 12px 12px;
}

.footer-left,
.footer-center,
.footer-right {
    display: flex;
    align-items: center;
}

.step-progress {
    background: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    font-weight: 600;
}

.step-current {
    color: #4f46e5;
}

.step-separator {
    color: #cbd5e0;
    margin: 0 0.25rem;
}

.step-total {
    color: #718096;
}

/* Animations */
.fade-slide-enter-active {
    animation: fadeSlideIn 0.3s ease;
}

.fade-slide-leave-active {
    animation: fadeSlideOut 0.2s ease;
}

@keyframes fadeSlideIn {
    from {
        opacity: 0;
        transform: translateX(10px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes fadeSlideOut {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(-10px);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .overtime-assign-modal :deep(.modal-body) {
        padding: 1rem;
    }

    .step-indicator {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .step {
        flex-direction: row;
        width: 100%;
        align-items: center;
        gap: 1rem;
    }

    .step-connector {
        display: none;
    }

    .step-content {
        text-align: left;
        max-width: none;
    }

    .date-picker-grid {
        grid-template-columns: repeat(7, minmax(40px, 1fr));
        font-size: 0.875rem;
    }

    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }

    .selection-badge {
        align-self: flex-start;
    }

    .modal-footer-actions .d-flex {
        flex-direction: column;
        gap: 1rem;
    }

    .footer-left,
    .footer-center,
    .footer-right {
        width: 100%;
        justify-content: center;
    }

    .search-wrapper {
        min-width: 100%;
    }

    .employee-card-body {
        flex-wrap: wrap;
    }

    .employee-selection-checkbox {
        order: 1;
    }

    .employee-avatar {
        order: 2;
    }

    .employee-details {
        order: 3;
        flex: 0 0 100%;
        margin-top: 0.5rem;
    }
}

@media (max-width: 576px) {
    .date-picker-grid {
        gap: 0.25rem;
    }

    .date-picker-item {
        font-size: 0.875rem;
    }

    .date-number {
        font-size: 1rem;
    }

    .filter-buttons {
        width: 100%;
    }

    .filter-buttons .btn {
        flex: 1;
    }
}

/* Base Styles */
.overtime-app-container {
    min-height: 100vh;
    /* background: #f8fafc; */
}

.main-content-wrapper {
    padding: 1rem 0;
}

.content-container {
    max-width: 1400px;
    margin: 0 auto;
    /* padding: 0 1rem; */
}

/* Header Customizations */
.header-right-section {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.btn-atas {
    border-radius: 15px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    transition: all 0.3s ease;
}

.btn-atas:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Card View Styles */
.card-view-container {
    padding: 1rem 0;
}

.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}

@media (max-width: 768px) {
    .cards-grid {
        grid-template-columns: 1fr;
    }
}

.overtime-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}

.overtime-card:hover {
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
    transform: translateY(-4px);
}

.card-header {
    padding: 1.25rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.avatar-md {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1rem;
    color: white;
}

.card-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
}

.card-subtitle {
    margin: 0.25rem 0 0 0;
    font-size: 0.85rem;
    opacity: 0.9;
}

.card-time {
    font-size: 0.9rem;
    font-weight: 500;
    background: rgba(255, 255, 255, 0.2);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
}

.card-body {
    padding: 1.25rem;
    flex-grow: 1;
}

.card-info {
    display: flex;
    align-items: center;
    color: #64748b;
    font-size: 0.9rem;
}

.card-footer {
    padding: 1rem 1.25rem;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 0.75rem;
}

/* View Toggle */
.view-toggle-container {
    display: flex;
    justify-content: center;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e2e8f0;
}

.view-toggle {
    display: flex;
    background: #f1f5f9;
    border-radius: 12px;
    padding: 0.25rem;
    gap: 0.25rem;
}

.toggle-btn {
    padding: 0.5rem 1rem;
    border: none;
    background: transparent;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    color: #64748b;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.toggle-btn.active {
    background: #6366f1;
    color: white;
}

.toggle-btn:hover:not(.active) {
    background: #e2e8f0;
    color: #334155;
}

/* Loading & Empty States */
.loading-state,
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    text-align: center;
}

.loading-spinner {
    width: 60px;
    height: 60px;
    border: 3px solid #e2e8f0;
    border-top: 3px solid #6366f1;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 1.5rem;
}

.loading-state p,
.empty-state p {
    color: #64748b;
    margin: 1rem 0 0 0;
}

.empty-state h4 {
    color: #334155;
    margin: 1rem 0 0.5rem 0;
}

.empty-state button {
    margin-top: 1.5rem;
}

/* Step Indicator */
.step-indicator {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    position: relative;
}

.step-indicator::before {
    content: "";
    position: absolute;
    top: 20px;
    left: 0;
    right: 0;
    height: 2px;
    background: #e2e8f0;
    z-index: 1;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    position: relative;
    z-index: 2;
    flex: 1;
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e2e8f0;
    border: 2px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: #64748b;
    transition: all 0.3s ease;
}

.step.active .step-number {
    background: #6366f1;
    border-color: #6366f1;
    color: white;
}

.step.completed .step-number {
    background: #10b981;
    border-color: #10b981;
    color: white;
}

.step-label {
    font-size: 0.85rem;
    font-weight: 500;
    color: #64748b;
    text-align: center;
}

.step.active .step-label {
    color: #6366f1;
    font-weight: 600;
}

/* Date Picker Grid */
.date-picker-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 0.75rem;
    margin-top: 1rem;
}

.date-picker-item {
    padding: 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.date-picker-item:hover {
    border-color: #6366f1;
    transform: translateY(-2px);
}

.date-picker-item.selected {
    border-color: #6366f1;
    background: rgba(99, 102, 241, 0.1);
}

.date-picker-item.today {
    border-color: #f59e0b;
    background: rgba(245, 158, 11, 0.1);
}

.date-day {
    font-size: 0.8rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    margin-bottom: 0.25rem;
}

.date-number {
    font-size: 1.1rem;
    font-weight: 600;
    color: #334155;
}

.date-check {
    position: absolute;
    top: -8px;
    right: -8px;
    color: #6366f1;
    font-size: 1.2rem;
}

/* Employee Selection */
.employee-selection-list {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

.employee-selection-item {
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;
    transition: all 0.3s ease;
}

.employee-selection-item:last-child {
    border-bottom: none;
}

.employee-selection-item:hover:not(.disabled) {
    background: #f8fafc;
}

.employee-selection-item.selected {
    background: rgba(99, 102, 241, 0.05);
    border-left: 4px solid #6366f1;
}

.employee-selection-item.disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.employee-selection-card {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.employee-checkbox {
    width: 20px;
    height: 20px;
    border: 2px solid #e2e8f0;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6366f1;
    font-size: 0.8rem;
    flex-shrink: 0;
}

.employee-selection-item.selected .employee-checkbox {
    background: #6366f1;
    border-color: #6366f1;
    color: white;
}

.employee-avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: #6366f1;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.employee-details {
    flex: 1;
    min-width: 0;
}

.employee-name {
    margin: 0;
    font-weight: 600;
    color: #334155;
    font-size: 0.95rem;
    line-height: 1.3;
}

.employee-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.25rem;
    flex-wrap: wrap;
}

.employee-unavailable-reason {
    margin-top: 0.5rem;
    padding: 0.5rem;
    background: #fef2f2;
    border-radius: 6px;
    color: #dc2626;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
}

.selection-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.btn-link {
    background: none;
    border: none;
    color: #6366f1;
    padding: 0.5rem;
    cursor: pointer;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    transition: all 0.2s ease;
}

.btn-link:hover {
    color: #4f46e5;
    text-decoration: underline;
}

.btn-link.text-danger {
    color: #ef4444;
}

.btn-link.text-danger:hover {
    color: #dc2626;
}

.selected-count {
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0.5rem 1rem;
    border-radius: 20px;
}

/* Summary Card */
.summary-card {
    background: #f8fafc;
    border-radius: 12px;
    padding: 1.5rem;
    margin: 1.5rem 0;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e2e8f0;
}

.summary-item:last-child {
    border-bottom: none;
}

.summary-label {
    font-weight: 500;
    color: #64748b;
}

.summary-value {
    font-weight: 600;
    color: #334155;
    text-align: right;
}

.selected-employees-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.selected-employee-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.selected-employee-card:hover {
    border-color: #6366f1;
    transform: translateY(-2px);
}

.employee-avatar-sm {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #6366f1;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
    flex-shrink: 0;
}

.employee-info-sm {
    flex: 1;
    min-width: 0;
}

.employee-name-sm {
    margin: 0;
    font-weight: 600;
    color: #334155;
    font-size: 0.85rem;
    line-height: 1.3;
}

.employee-schedule {
    margin-top: 0.25rem;
}

/* Detail Modal Styles */
.transaction-header {
    background: #f8fafc;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.detail-item {
    margin-bottom: 1rem;
}

.detail-item:last-child {
    margin-bottom: 0;
}

.detail-label {
    display: block;
    font-weight: 500;
    color: #64748b;
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.detail-value {
    color: #334155;
    font-weight: 500;
}

.employee-details-section {
    margin-top: 2rem;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #334155;
    display: flex;
    align-items: center;
}

.employee-info-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.avatar-xs {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #6366f1;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
}

.employee-name-sm {
    margin: 0;
    font-weight: 600;
    color: #334155;
    font-size: 0.9rem;
}

/* Calendar Container */
.calendar-container {
    padding: 1rem 0;
}

.date-range-display {
    margin-top: 1.5rem;
}

/* Button Styles */
.btn-modern {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
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

.btn-modern:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
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

.btn-primary {
    background-color: #6366f1;
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background-color: #4f46e5;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.btn-secondary {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #e2e8f0;
}

.btn-secondary:hover:not(:disabled) {
    background: #e2e8f0;
    transform: translateY(-1px);
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover:not(:disabled) {
    background: #059669;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover:not(:disabled) {
    background: #dc2626;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.btn-action {
    padding: 0.5rem 0.75rem;
    border: none;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-view {
    background: #e3f2fd;
    color: #1976d2;
}

.btn-view:hover {
    background: #bbdefb;
    transform: translateY(-1px);
}

.btn-delete {
    background: #ffebee;
    color: #d32f2f;
}

.btn-delete:hover:not(:disabled) {
    background: #ffcdd2;
    transform: translateY(-1px);
}

.btn-delete:disabled {
    opacity: 0.5;
    cursor: not-allowed;
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
    flex: 1;
    justify-content: center;
}

.card-btn.btn-view {
    background: #e3f2fd;
    color: #1976d2;
}

.card-btn.btn-delete {
    background: #ffebee;
    color: #d32f2f;
}

/* Pagination */
.pagination-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding: 1.5rem 0;
}

.page-btn {
    width: 40px;
    height: 40px;
    border: 1px solid #e2e8f0;
    background: white;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.page-btn:hover:not(:disabled) {
    background: #6366f1;
    color: white;
    border-color: #6366f1;
}

.page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.page-info {
    font-weight: 500;
    color: #64748b;
    min-width: 120px;
    text-align: center;
}

/* Animation */
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

.fade-in {
    animation: fadeIn 0.3s ease;
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

/* Responsive Adjustments */
@media (max-width: 768px) {
    .content-container {
        padding: 0 0.75rem;
    }

    .date-picker-grid {
        grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    }

    .employee-selection-card {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }

    .employee-avatar {
        width: 48px;
        height: 48px;
    }

    .summary-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .selected-employees-grid {
        grid-template-columns: 1fr;
    }

    .footer-actions {
        flex-direction: column;
        gap: 0.75rem;
    }

    .footer-actions .btn-modern {
        width: 100%;
        justify-content: center;
    }

    .step-indicator::before {
        top: 15px;
    }

    .step-number {
        width: 30px;
        height: 30px;
        font-size: 0.9rem;
    }

    .step-label {
        font-size: 0.75rem;
    }
}

@media (max-width: 480px) {
    .header-right-section {
        width: 100%;
    }

    .cards-grid {
        gap: 1rem;
    }

    .card-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }

    .card-footer {
        flex-direction: column;
    }

    .card-btn {
        width: 100%;
    }

    .pagination-container {
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .page-info {
        order: -1;
        width: 100%;
        text-align: center;
    }
}

/* Badge Styles */
.badge-xs {
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
}

/* Glass Morphism Effect */
.glassMorph {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

/* Shining Effect */
.shiningEffect {
    transition: all 0.3s ease;
    overflow: hidden;
    position: relative;
}

.shiningEffect::before {
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

.shiningEffect:hover::before {
    left: 100%;
}

/* Element Plus Calendar Customization */
:deep(.el-calendar) {
    border: none;
}

:deep(.el-calendar__header) {
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;
}

:deep(.el-calendar__body) {
    padding: 1rem;
}

:deep(.cell-wrapper) {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 1;
}

:deep(.date-cell) {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    z-index: 2;
}

:deep(.cell-wrapper:hover .date-cell) {
    background-color: #d9ecff;
}

:deep(.is-start .date-cell),
:deep(.is-end .date-cell) {
    background-color: #409eff;
    color: white;
}

:deep(.is-in-range::before) {
    content: "";
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    right: 0;
    background-color: #ecf5ff;
    z-index: 0;
}

:deep(.is-start::before) {
    left: 50%;
}

:deep(.is-end::before) {
    right: 50%;
}
</style>

<style scoped>
/* Design System Variables */
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
    --radius: 12px;
    --radius-lg: 16px;
    --transition: all 0.3s ease;
}

/* Modern Data Table Custom Styles */
.employee-info-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.employee-details {
    min-width: 0;
}

.employee-name {
    margin: 0;
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.875rem;
    line-height: 1.3;
}

.employee-dept {
    margin: 0.25rem 0 0 0;
    color: #6c757d;
    font-size: 0.75rem;
}

.badge-sm {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
}

.action-cell {
    display: flex;
    gap: 0.5rem;
}

.btn-action {
    padding: 0.5rem 0.75rem;
    border: none;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-view {
    background: #e3f2fd;
    color: #1976d2;
}

.btn-view:hover {
    background: #bbdefb;
    transform: translateY(-1px);
}

.btn-cancel {
    background: #ffebee;
    color: #d32f2f;
}

.action-text {
    font-size: 0.7rem;
}

.transaction-id-sm {
    font-family: "Monaco", "Menlo", "Ubuntu Mono", monospace;
    font-size: 0.7rem;
    background: #f8f9fa;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
    color: #495057;
}

/* Loading spin animation */
.loading-spin {
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

/* Refresh button */
.btn-refresh {
    padding: 0.75rem 1rem;
    border: 1px solid #007bff;
    background: white;
    color: #007bff;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 0.875rem;
}

.btn-refresh:hover:not(:disabled) {
    background: #007bff;
    color: white;
}

.btn-refresh:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Mobile card improvements */
.mobile-btn {
    flex: 1;
    justify-content: center;
    padding: 0.75rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .employee-info-cell {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }

    .action-cell {
        justify-content: center;
    }

    .filter-section .row > div {
        margin-bottom: 0.5rem;
    }
}

.date-display {
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

.pagination-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    background-color: var(--surface);
    color: var(--text-muted);
    font-size: 1rem;
    transition: var(--transition);
    cursor: pointer;
}

.pagination-btn:hover:not(:disabled) {
    background-color: var(--surface-soft);
    color: var(--text);
    border-color: var(--primary-light);
}

.pagination-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.pagination-btn.active {
    background-color: var(--primary);
    color: var(--light);
    border-color: var(--primary);
}

.pagination-btn.active:hover {
    background-color: var(--primary-dark);
    border-color: var(--primary-dark);
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

/* File Upload Container */
.file-upload-container {
    margin-top: 0.5rem;
}

/* Upload Card */
.file-upload-card {
    border: 2px dashed var(--border);
    border-radius: var(--radius-lg);
    padding: 2.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: var(--surface-soft);
    position: relative;
    overflow: hidden;
}

.file-upload-card:hover {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.02);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.file-upload-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(99, 102, 241, 0.05),
        transparent
    );
    transition: left 0.5s ease;
}

.file-upload-card:hover::before {
    left: 100%;
}

.upload-icon {
    font-size: 3rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.upload-content h4 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-weight: 600;
}

.upload-content p {
    margin: 0 0 1.5rem 0;
    color: var(--text-muted);
}

.file-requirements {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: center;
}

.requirement-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    font-size: 0.85rem;
}

.requirement-item i {
    color: var(--success);
}

/* File Preview Card */
.file-preview-card {
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    background: var(--surface);
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    animation: slideIn 0.3s ease;
}

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

.file-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1.5rem;
}

.file-info {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    flex: 1;
}

.file-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.file-details {
    flex: 1;
}

.file-name {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-weight: 600;
    word-break: break-word;
}

.file-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.file-size,
.file-type {
    padding: 0.25rem 0.75rem;
    background: var(--surface-soft);
    border-radius: 20px;
    font-size: 0.8rem;
    color: var(--text-muted);
    font-weight: 500;
}

.upload-status {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--success);
    font-size: 0.8rem;
    font-weight: 500;
}

.upload-status i {
    font-size: 0.9rem;
}

/* File Actions */
.file-actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}

.btn-action {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: var(--radius);
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-preview {
    background: var(--info);
    color: white;
}

.btn-preview:hover {
    background: #0891b2;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
}

.btn-replace {
    background: var(--warning);
    color: white;
}

.btn-replace:hover {
    background: #d97706;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.btn-remove-action {
    background: var(--danger);
    color: white;
}

.btn-remove-action:hover {
    background: #c23030;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

/* Upload Progress */
.upload-progress {
    margin-top: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.progress-bar {
    flex: 1;
    height: 6px;
    background: var(--surface-soft);
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success), #22c55e);
    border-radius: 3px;
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-weight: 600;
    min-width: 40px;
}

/* Validation & Requirements */
.requirement-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 0.5rem;
}

.requirement-badge.required {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.requirement-badge:not(.required) {
    background: rgba(100, 116, 139, 0.1);
    color: var(--secondary);
    border: 1px solid rgba(100, 116, 139, 0.3);
}

.validation-error {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--danger);
    font-size: 0.85rem;
    margin-top: 0.5rem;
    padding: 0.75rem;
    background: rgba(239, 68, 68, 0.05);
    border-radius: var(--radius);
    border-left: 3px solid var(--danger);
}

/* Responsive Design */
@media (max-width: 768px) {
    .file-upload-card {
        padding: 2rem 1.5rem;
    }

    .file-header {
        flex-direction: column;
        gap: 1rem;
    }

    .file-actions {
        width: 100%;
        justify-content: flex-end;
    }

    .file-meta {
        gap: 0.5rem;
    }

    .btn-action span {
        display: none;
    }

    .btn-action {
        padding: 0.5rem;
    }
}

/* Base Styles */
.cuti-app-container {
    min-height: 100vh;
    /* background: var(--light); */
}

.main-content-wrapper {
    padding: 0.5rem 0;
}

.content-container {
    /* max-width: 1400px; */
    margin: 0 auto;
    /* padding: 0 1rem; */
}

/* Tab Content */
.tab-content-wrapper {
    /* padding: 1.5rem 0; */
}

/* Form Layout */
.form-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    align-items: start;
}

.form-main-section {
    min-height: 500px;
}

.form-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 2rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.form-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--surface-soft);
}

.form-icon-wrapper {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.form-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
    margin: 0;
}

/* Form Elements */
.form-field-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: flex;
    align-items: center;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
}

.form-textarea {
    width: 100%;
    padding: 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: var(--transition);
    resize: vertical;
    min-height: 120px;
    font-family: inherit;
}

.form-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.date-picker-trigger {
    cursor: pointer;
}

.date-display {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    transition: var(--transition);
}

.date-display.disabled {
    background: #eaeaea !important;
    cursor: not-allowed;
}

.date-display:hover {
    border-color: var(--primary);
}

.placeholder {
    color: var(--text-muted);
    background: none;
}

.selected-dates {
    color: var(--text);
    font-weight: 500;
}

/* File Upload */
.file-upload-wrapper {
    margin-top: 0.5rem;
}

.file-upload-area {
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
    background: var(--surface-soft);
}

.file-upload-area:hover {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.file-upload-area i {
    font-size: 2rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.file-preview {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: var(--surface-soft);
    border-radius: var(--radius);
    padding: 1rem;
    margin-top: 1rem;
}

.file-name {
    flex: 1;
    font-weight: 500;
}

.btn-remove {
    background: none;
    border: none;
    color: var(--danger);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: var(--transition);
}

.btn-remove:hover {
    background: var(--danger);
    color: white;
}

/* Validation & Alerts */
.validation-section {
    margin: 1.5rem 0;
}

.alert {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    border-radius: var(--radius);
    margin-bottom: 0.75rem;
}

.alert.error {
    background: rgba(239, 68, 68, 0.1);
    border-left: 4px solid var(--danger);
    color: var(--danger);
}

.alert.info {
    background: rgba(6, 182, 212, 0.1);
    border-left: 4px solid var(--info);
    color: var(--info);
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border);
}

/* Sidebar */
.form-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    position: sticky;
    top: 1rem;
}

.info-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
}

.info-card.highlight {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}

.info-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.info-header h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.duration-display {
    text-align: center;
}

.duration-value {
    display: block;
    font-size: 2.5rem;
    font-weight: 700;
    line-height: 1;
}

.duration-label {
    display: block;
    font-size: 0.9rem;
    opacity: 0.9;
    margin-top: 0.5rem;
}

.tips-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.tip-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.tip-item i {
    color: var(--warning);
    margin-top: 0.125rem;
    flex-shrink: 0;
}

.tip-item span {
    font-size: 0.9rem;
    line-height: 1.4;
    color: var(--text);
}

.info-card.highlight .tip-item span {
    color: rgba(255, 255, 255, 0.9);
}

/* Riwayat Styles */
.riwayat-container {
    /* padding: 1rem 0; */
}

/* .filter-section {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow);
} */

.filter-grid {
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 1rem;
    margin-bottom: 1rem;
}

.search-field {
    position: relative;
    display: flex;
    align-items: center;
}

.search-field i {
    position: absolute;
    left: 1rem;
    color: var(--text-muted);
    z-index: 2;
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    color: var(--text);
    transition: var(--transition);
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.filter-select {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    color: var(--text);
    cursor: pointer;
    transition: var(--transition);
    min-width: 150px;
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary);
}

.filter-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
}

.filter-info {
    color: var(--text-muted);
    font-weight: 500;
}

/* Stats Overview */
.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border: 1px solid var(--border);
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.stat-card.total {
    border-left: 4px solid var(--primary);
}

.stat-card.approved {
    border-left: 4px solid var(--success);
}

.stat-card.pending {
    border-left: 4px solid var(--warning);
}

.stat-card.rejected {
    border-left: 4px solid var(--danger);
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

.stat-card.total .stat-icon {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
}

.stat-card.approved .stat-icon {
    background: linear-gradient(135deg, var(--success), #059669);
}

.stat-card.pending .stat-icon {
    background: linear-gradient(135deg, var(--warning), #d97706);
}

.stat-card.rejected .stat-icon {
    background: linear-gradient(135deg, var(--danger), #dc2626);
}

.stat-content h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
}

.stat-content p {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.9rem;
}

/* Riwayat List */
.riwayat-list {
    margin-bottom: 2rem;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: 0.5;
}

.empty-state h4 {
    margin-bottom: 1rem;
    color: var(--text);
}

.empty-state p {
    margin-bottom: 2rem;
}

.riwayat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 1.5rem;
}

.riwayat-item {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    border: 1px solid var(--border);
    transition: var(--transition);
    box-shadow: var(--shadow);
}

.riwayat-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.riwayat-item.status-approved {
    border-left: 4px solid var(--success);
}

.riwayat-item.status-pending {
    border-left: 4px solid var(--warning);
}

.riwayat-item.status-rejected {
    border-left: 4px solid var(--danger);
}

.riwayat-item.status-cancelled {
    border-left: 4px solid var(--secondary);
}

.riwayat-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.cuti-type {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.type-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.type-info h4 {
    margin: 0 0 0.25rem 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
}

.duration {
    background: var(--primary-light);
    color: var(--primary);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-badge {
    font-size: 0.8rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
}

.riwayat-content {
    margin-bottom: 1.5rem;
}

.date-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--surface-soft);
    border-radius: var(--radius);
    margin-bottom: 1rem;
}

.date-info i {
    color: var(--info);
    font-size: 1.1rem;
}

.date-details {
    flex: 1;
}

.date-range {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.25rem;
}

.date-full {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.reason-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.reason-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    font-size: 0.85rem;
    font-weight: 600;
}

.reason-label i {
    color: var(--primary);
}

.reason-text {
    margin: 0;
    color: var(--text);
    line-height: 1.6;
    font-size: 0.9rem;
    padding: 0.75rem;
    background: var(--surface);
    border-radius: var(--radius);
    border-left: 3px solid var(--primary);
}

.riwayat-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}

.meta-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    font-size: 0.8rem;
}

.meta-info i {
    color: var(--primary);
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-action {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: var(--radius);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
}

.btn-action.primary {
    background: var(--primary);
    color: white;
}

.btn-action.primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.btn-action.danger {
    background: var(--danger);
    color: white;
}

.btn-action.danger:hover {
    background: #c23030;
    transform: translateY(-1px);
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    background: var(--surface);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
}

.pagination-info {
    color: var(--text-muted);
    font-size: 0.9rem;
    font-weight: 500;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pagination-btn {
    width: 40px;
    height: 40px;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    color: var(--text);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition);
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

.pagination-numbers {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.pagination-number {
    min-width: 40px;
    height: 40px;
    border: 2px solid transparent;
    background: transparent;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition);
    font-weight: 500;
    color: var(--text);
}

.pagination-number:hover {
    border-color: var(--border);
    background: var(--surface-soft);
}

.pagination-number.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Modal Styles */
.mobile-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    background: var(--surface);
}

.btn-back {
    background: none;
    border: none;
    font-size: 1.25rem;
    color: var(--text);
    cursor: pointer;
    padding: 0.5rem;
}

.mobile-modal-title {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
}

.header-spacer {
    width: 40px;
}

.btn-confirm {
    background: var(--primary);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: var(--radius);
    font-weight: 600;
    cursor: pointer;
}

/* Detail Modal */
.detail-content {
    padding: 0.5rem;
}

.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.detail-section {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.detail-label {
    display: flex;
    align-items: center;
    font-weight: 600;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.detail-value {
    color: var(--text);
    font-size: 1rem;
}

.detail-meta {
    color: var(--text-muted);
    font-size: 0.85rem;
    margin-left: 0.5rem;
}

.reason-text {
    background: var(--surface-soft);
    padding: 1rem;
    border-radius: var(--radius);
    border-left: 4px solid var(--primary);
    line-height: 1.6;
}

.file-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
    padding: 0.5rem 1rem;
    background: rgba(99, 102, 241, 0.1);
    border-radius: var(--radius);
    transition: var(--transition);
}

.file-link:hover {
    background: var(--primary);
    color: white;
}

.approval-flow {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    border: 1px solid var(--border);
}

.flow-title {
    display: flex;
    align-items: center;
    margin-bottom: 1.5rem;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
}

/* Confirmation Modal */
.confirmation-content {
    text-align: center;
    padding: 1rem;
}

.warning-icon {
    font-size: 3rem;
    color: var(--warning);
    margin-bottom: 1rem;
}

.confirmation-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

/* Loading Skeleton */
.loading-skeleton {
    padding: 2rem;
}

.skeleton-line {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
    border-radius: 4px;
    margin-bottom: 1rem;
}

.skeleton-line.large {
    height: 2rem;
    width: 60%;
}

.skeleton-line.medium {
    height: 1.5rem;
    width: 80%;
}

.skeleton-line.small {
    height: 1rem;
    width: 40%;
}

.skeleton-line.full {
    height: 4rem;
    width: 100%;
}

@keyframes loading {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

/* Button Styles */
.btn {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: var(--radius);
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
    position: relative;
    overflow: hidden;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.btn-primary {
    background: var(--primary);
    color: white;
    border: 1px solid var(--primary);
}

.btn-primary:hover:not(:disabled) {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-secondary {
    background: var(--surface);
    color: var(--text);
    border: 1px solid var(--border);
}

.btn-secondary:hover:not(:disabled) {
    background: var(--surface-soft);
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-danger {
    background: var(--danger);
    color: white;
    border: 1px solid var(--danger);
}

.btn-danger:hover:not(:disabled) {
    background: #c23030;
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-outline {
    background: transparent;
    color: var(--text);
    border: 1px solid var(--border);
}

.btn-outline:hover:not(:disabled) {
    background: var(--surface-soft);
    transform: translateY(-1px);
}

/* Animations */
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

/* Mobile Responsive */
@media (max-width: 1024px) {
    .form-layout {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .form-sidebar {
        position: static;
    }

    .riwayat-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    /* .content-container {
        padding: 0 0.75rem;
    } */

    .mobile-modal-header {
        padding: 0;
        border: none;
    }
    .form-card {
        padding: 1.5rem;
    }

    .filter-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .stats-overview {
        grid-template-columns: repeat(2, 1fr);
    }

    .riwayat-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }

    .riwayat-footer {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }

    .action-buttons {
        width: 100%;
        justify-content: flex-end;
    }

    .pagination {
        /* flex-direction: column; */
        gap: 1rem;
        text-align: center;
    }

    .detail-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .mobile-fullscreen {
        width: 100% !important;
        margin: 0 !important;
        max-height: 100vh;
    }
}

@media (max-width: 480px) {
    /* .content-container {
        padding: 0 0.5rem;
    } */

    .form-card {
        padding: 1rem;
    }

    .stats-overview {
        grid-template-columns: 1fr;
    }

    .form-actions {
        flex-direction: column;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }

    .riwayat-item {
        padding: 1rem;
    }

    .cuti-type {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
}

/* Utility Classes */
.d-none {
    display: none !important;
}

.me-2 {
    margin-right: 0.5rem;
}

.cursor-pointer {
    cursor: pointer;
}

.text-center {
    text-align: center;
}

.justify-content-center {
    justify-content: center;
}

.align-items-center {
    align-items: center;
}

.d-flex {
    display: flex;
}

.w-100 {
    width: 100%;
}

/* V-Select Custom Styles */
:deep(.modern-select .vs__dropdown-toggle) {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: var(--transition);
    min-height: 48px;
}

:deep(.modern-select.vs--open .vs__dropdown-toggle) {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

:deep(.modern-select .vs__dropdown-menu) {
    border: 2px solid var(--border);
    border-radius: var(--radius);
    margin-top: 4px;
    box-shadow: var(--shadow);
}

:deep(.modern-select .vs__dropdown-option) {
    padding: 0.75rem 1rem;
    color: var(--text);
    font-size: 0.95rem;
}

:deep(.modern-select .vs__dropdown-option--highlight) {
    background: var(--primary);
    color: white;
}

/* Element Plus Overrides */
/* :deep(.el-dialog) {
    border-radius: var(--radius-lg);
    overflow: hidden;
}

:deep(.el-dialog__header) {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    margin: 0;
}

:deep(.el-dialog__body) {
    padding: 1.5rem;
}

:deep(.el-dialog__footer) {
    padding: 1.5rem;
    border-top: 1px solid var(--border);
}

:deep(.el-calendar) {
    border: none;
}

:deep(.el-calendar__header) {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
}

:deep(.el-calendar__body) {
    padding: 1rem;
}

:deep(.el-step__head) {
    width: 32px;
    height: 32px;
}

:deep(.el-step__title) {
    font-size: 0.9rem;
    font-weight: 600;
}

:deep(.el-step__description) {
    font-size: 0.8rem;
    color: var(--text-muted);
} */
</style>

<style>
/* Consolidated and deduplicated styles */
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
    --danger-light: #f8d7da; /* Merah muda, lebih terang untuk latar belakang atau efek disabled */
    --gray-light: #e9ecef;
    --el-slider-main-bg-color: #6366f1;
    --el-calendar-cell-width: 50px !important;
}

/* .is-selected {
    background-color: #409eff !important;
    color: white;
    border-radius: 4px;
} */

.is-terlambat {
    background-color: rgba(239, 68, 68, 0.15); /* Merah transparan */
    color: #dc2626; /* Merah lebih gelap */
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.is-pulang-cepat {
    background-color: rgba(245, 158, 11, 0.15); /* Kuning transparan */
    color: #d97706; /* Kuning lebih gelap */
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.is-sakit {
    background-color: rgba(100, 116, 139, 0.15); /* Secondary transparan */
    color: #475569; /* Secondary lebih gelap */
    border: 1px solid rgba(100, 116, 139, 0.2);
}

.is-izin {
    background-color: rgba(6, 182, 212, 0.15); /* Info transparan */
    color: #0891b2; /* Info lebih gelap */
    border: 1px solid rgba(6, 182, 212, 0.2);
}

.is-cuti-bersama {
    background-color: rgba(253, 246, 236, 0.8); /* Orange sangat transparan */
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: #b45309; /* Orange gelap */
}

.is-cuti-pribadi {
    background-color: rgba(34, 197, 94, 0.15); /* Hijau transparan */
    color: #16a34a; /* Hijau lebih gelap */
    border: 1px solid rgba(34, 197, 94, 0.2);
}

.is-special-dates {
    background-color: rgba(254, 240, 240, 0.8); /* Merah sangat transparan */
    color: #dc2626; /* Merah gelap */
    border: 1px solid rgba(239, 68, 68, 0.2);
}

/* Optional: Tambahkan efek hover untuk interaktivitas */
.is-terlambat:hover,
.is-pulang-cepat:hover,
.is-sakit:hover,
.is-izin:hover,
.is-cuti-bersama:hover,
.is-special-dates:hover,
.is-cuti-pribadi:hover {
    opacity: 0.9;
    transition: opacity 0.2s ease;
}

/* Pastikan div pembungkus memenuhi kotak kalender */
.calendar-cell-content {
    height: 100%;
    width: 100%;
    padding: 5px;
    box-sizing: border-box;
}

/* Detail Modal Styles */
.detail-content-v2 {
    padding: 0.5rem 0;
}

.detail-left-col {
    border-right: 1px solid #e9ecef;
    padding-right: 2rem;
}

.detail-employee-info {
    text-align: center;
    margin-bottom: 1rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.detail-status-section {
    margin-bottom: 1rem;
    text-align: center;
}

.section-title {
    font-weight: 600;
    color: #495057;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}

.info-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #f1f3f4;
}

.info-item.full-width {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
}

.info-item .label {
    font-weight: 500;
    color: #6c757d;
    font-size: 0.875rem;
    min-width: 120px;
}

.info-item .value {
    color: #495057;
    font-weight: 500;
    text-align: right;
    flex: 1;
}

.value-reason {
    color: #495057;
    line-height: 1.5;
    margin: 0;
    font-size: 0.9rem;
}

.file-link {
    color: #007bff;
    text-decoration: none;
    font-size: 0.875rem;
}

.file-link:hover {
    text-decoration: underline;
}

.approval-status-info {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 6px;
}

.status-label {
    font-weight: 500;
    color: #6c757d;
    font-size: 0.875rem;
}

.status-value {
    color: #495057;
    font-weight: 500;
    font-size: 0.875rem;
}

/* Modal Footer */
.modal-footer {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    padding: 1rem 0 0 0;
    border-top: 1px solid #e9ecef;
}

.btn-modern {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #218838;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover {
    background: #c82333;
}

.avatar-sm,
.avatar-lg {
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #007bff;
    color: white;
    font-weight: 600;
}

.avatar-sm {
    width: 40px;
    height: 40px;
    font-size: 0.8rem;
}

.avatar-lg {
    width: 80px;
    height: 80px;
    font-size: 1.5rem;
    margin-bottom: 1rem;
}

.employee-details {
    flex: 1;
}

.employee-name {
    margin: 0;
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.95rem;
}

.employee-meta {
    margin: 0.25rem 0 0 0;
    color: #6c757d;
    font-size: 0.8rem;
}

/* Cuti Duration Card */
.cuti-duration-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
}

.cuti-duration-card.cuti-highlight {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border: none;
}

.cuti-info-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.cuti-info-header h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.cuti-duration-card.cuti-highlight .cuti-info-header h4 {
    color: white;
}

.cuti-duration-card.cuti-highlight .cuti-info-header i {
    color: white;
}

/* Duration Display */
.cuti-duration-display {
    text-align: center;
    padding: 1rem 0;
}

.cuti-duration-value {
    display: block;
    font-size: 3rem;
    font-weight: 800;
    line-height: 1;
    color: inherit;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    margin-bottom: 0.25rem;
}

.cuti-duration-card.cuti-highlight .cuti-duration-value {
    color: white;
}

.cuti-duration-label {
    display: block;
    font-size: 1rem;
    color: inherit;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: 0.9;
}

.cuti-duration-card.cuti-highlight .cuti-duration-label {
    color: rgba(255, 255, 255, 0.9);
}

/* Hutang Warning Styles */
.cuti-hutang-warning {
    margin-top: 1.5rem;
    padding: 1.25rem;
    background: rgba(245, 158, 11, 0.08);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: var(--radius);
    animation: cuti-slideDown 0.3s ease;
}

@keyframes cuti-slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.cuti-warning-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    color: var(--warning);
    font-weight: 600;
    font-size: 0.95rem;
}

.cuti-warning-header i {
    font-size: 1.1rem;
}

.cuti-hutang-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.cuti-hutang-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(245, 158, 11, 0.1);
}

.cuti-hutang-item.cuti-highlight-item {
    background: rgba(245, 158, 11, 0.05);
    margin: 0 -0.5rem;
    padding: 0.75rem 0.5rem;
    border-radius: var(--radius);
    border-bottom: none;
}

.cuti-label {
    color: var(--text);
    font-weight: 500;
    font-size: 0.9rem;
}

.cuti-duration-card.cuti-highlight .cuti-label {
    color: rgba(255, 255, 255, 0.9);
}

.cuti-value {
    font-weight: 600;
    font-size: 0.9rem;
}

.cuti-duration-card.cuti-highlight .cuti-value {
    color: white;
}

.cuti-value.cuti-negative {
    color: var(--danger);
    background: rgba(239, 68, 68, 0.1);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.cuti-duration-card.cuti-highlight .cuti-value.cuti-negative {
    background: rgba(239, 68, 68, 0.3);
    color: #fecaca;
    border-color: rgba(239, 68, 68, 0.5);
}

.cuti-warning-message {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    background: rgba(245, 158, 11, 0.1);
    border-radius: var(--radius);
    border-left: 3px solid var(--warning);
}

.cuti-warning-message i {
    color: var(--warning);
    font-size: 0.9rem;
}

.cuti-warning-message small {
    color: var(--warning);
    font-size: 0.8rem;
    font-weight: 500;
}

/* Cuti Cukup Info Styles */
.cuti-sufficient-info {
    margin-top: 1.5rem;
    padding: 1.25rem;
    background: rgba(16, 185, 129, 0.08);
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: var(--radius);
    animation: cuti-slideDown 0.3s ease;
}

.cuti-sufficient-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    color: var(--success);
    font-weight: 600;
    font-size: 0.95rem;
}

.cuti-sufficient-header i {
    font-size: 1.1rem;
}

.cuti-sufficient-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.cuti-sufficient-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(16, 185, 129, 0.1);
}

.cuti-value.cuti-positive {
    color: var(--success);
    background: rgba(16, 185, 129, 0.1);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.cuti-duration-card.cuti-highlight .cuti-value.cuti-positive {
    background: rgba(16, 185, 129, 0.3);
    color: #bbf7d0;
    border-color: rgba(16, 185, 129, 0.5);
}

/* Empty State */
.cuti-empty-state {
    margin-top: 1.5rem;
    padding: 1.5rem;
    text-align: center;
    color: var(--text-muted);
    background: rgba(100, 116, 139, 0.05);
    border-radius: var(--radius);
    border: 1px dashed var(--border);
}

.cuti-duration-card.cuti-highlight .cuti-empty-state {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
    color: rgba(255, 255, 255, 0.8);
}

.cuti-empty-state i {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    display: block;
    opacity: 0.5;
}

.cuti-empty-state span {
    font-size: 0.9rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .cuti-duration-card {
        padding: 1.25rem;
    }
    .detail-left-col {
        /* border-right: 1px solid #e9ecef; */
        padding: 0rem;
        border: none;
    }

    .cuti-duration-value {
        font-size: 2.5rem;
    }

    .cuti-hutang-details,
    .cuti-sufficient-details {
        gap: 0.5rem;
    }

    .cuti-hutang-item,
    .cuti-sufficient-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .cuti-hutang-warning,
    .cuti-sufficient-info {
        padding: 1rem;
    }

    .cuti-warning-header,
    .cuti-sufficient-header {
        font-size: 0.9rem;
    }
}

/* Additional Utility Classes untuk Cuti */
.cuti-text-center {
    text-align: center;
}

.cuti-mt-1 {
    margin-top: 0.5rem;
}

.cuti-mt-2 {
    margin-top: 1rem;
}

.cuti-mb-1 {
    margin-bottom: 0.5rem;
}

.cuti-p-1 {
    padding: 0.5rem;
}

.cuti-p-2 {
    padding: 1rem;
}

/* info bar */
.leave-info-panel {
}

/* Selected Date Styles */
.cell-date-wrapper.is-selected .date-cell {
    /* background: linear-gradient(135deg, var(--primary), var(--primary-dark)); */
    background: var(--primary);
    color: white;
    font-weight: 700;
    /* box-shadow: var(--shadow); */

    /* box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4); */
    transform: scale(1.05);
    border: 2px solid white;
    position: relative;
    z-index: 2;
}

/* Hover effect for selected dates */
.cell-date-wrapper.is-selected .date-cell:hover {
    background: linear-gradient(135deg, var(--primary-dark), #4338ca);
    transform: scale(1.08);
    box-shadow: 0 6px 16px rgba(99, 102, 241, 0.6);
}

/* Optional: Add a subtle pulse animation for selected dates */
@keyframes selectedPulse {
    0% {
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
    }
    50% {
        box-shadow: 0 4px 16px rgba(99, 102, 241, 0.4);
    }
    100% {
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
    }
}

.cell-date-wrapper.is-selected .date-cell {
    animation: selectedPulse 2s ease-in-out infinite;
}

/* Alternative simpler version without animation */
/*
.cell-date-wrapper.is-selected .date-cell {
    background: var(--primary);
    color: white;
    font-weight: 600;
    border: 2px solid white;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
}
*/

/* For better visual hierarchy with other states */
.cell-date-wrapper.is-selected.is-today .date-cell {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-color: white;
}

.cell-date-wrapper.is-selected.is-weekend .date-cell {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}

.cell-date-wrapper.is-weekend .date-cell {
    /* color:  */
    color: var(--danger);
}

/* Ensure selected dates stand out from disabled dates */
.cell-date-wrapper.is-selected .date-cell.is-disabled {
    background: var(--secondary) !important;
    color: var(--light) !important;
    opacity: 0.6;
    transform: none;
    box-shadow: none;
    animation: none;
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
    .el-dialog__body {
        padding: 0 !important;
    }
    .el-dialog {
        margin-bottom: 0 !important;
    }
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
    /* overflow: hidden; */
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
    background: #059669;
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
    /* background: var(--surface); */
    border-radius: 20px;
    /* overflow: hidden; */
    /* box-shadow: var(--shadow); */
    /* box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); */
    /* border: 1px solid var(--border); */
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
    background: linear-gradient(135deg, #34d399 0%, #059669ce 100%);
}
.header-pending {
    background: linear-gradient(135deg, #818cf8 0%, #6365f1ce 100%);
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
    /* background: rgba(255, 255, 255, 0.2); */
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
    background-color: #e9ecef;
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
    /* .el-dialog.is-align-center {
        margin: auto 1rem;
    } */
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
        align-self: center;
        font-size: 15px;
        font-weight: 600;
    }
    .el-dialog {
        border-top-left-radius: 20px;
        border-top-right-radius: 20px;
    }
    .el-calendar__button-group {
        width: 100%;
    }

    .el-button-group {
        width: 100%;
        display: flex;
    }

    .el-button.el-button--small {
        flex: 1;
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
        /* margin: 0.5rem; */
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
