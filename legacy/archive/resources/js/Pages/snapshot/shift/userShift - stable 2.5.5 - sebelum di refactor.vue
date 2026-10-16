<template>
    <div
        class="row d-flex align-items-center justify-content-center mb-4 fade-in"
    >
        <div class="col-md-11">
            <div class="modern-header">
                <div class="header-background"></div>
                <div class="header-content">
                    <div class="row align-items-center">
                        <div class="col-sm-8">
                            <div class="header-info">
                                <div
                                    @click="closeShiftModal"
                                    class="icon-container d-flex align-items-center justify-content-center"
                                >
                                    <i
                                        class="bi bi-calendar-week d-flex justify-content-center align-items-center"
                                    ></i>
                                </div>
                                <div class="text-content">
                                    <h2 class="title text-white">
                                        Manajemen Penugasan Shift
                                    </h2>
                                    <p class="subtitle">
                                        Kelola pergantian shift tim
                                    </p>
                                </div>
                            </div>
                        </div>
                        <!-- Button & Dates -->
                        <div class="col-sm-4">
                            <div
                                class="header-actions d-flex gap-2 flex-nowrap flex-sm-column justify-content-center justify-content-md-start justify-content-md-center align-items-md-end"
                            >
                                <div
                                    @click="log()"
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
                                        <span class="fs-md-6 text-header"
                                            >{{
                                                startOfWeek
                                                    .split(" ")
                                                    .slice(0, 1)
                                                    .join(" ")
                                            }}
                                            - {{ endOfWeek }}
                                        </span>
                                    </div>
                                </div>
                                <button
                                    class="btn-modern btn-primary flex-nowrap w-full px-md-2 py-md-2"
                                    @click="showAssignModal = true"
                                >
                                    <i
                                        class="bi bi-plus-circle me-2 d-flex justify-content-center align-items-center"
                                    ></i>
                                    <span class="text-header"
                                        >Tambahkan Shift</span
                                    >
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row d-flex justify-content-center mb-3 fade-in">
        <div class="col-md-11">
            <div class="control-panel">
                <!-- Navigation Controls -->
                <div class="nav-controls">
                    <button
                        @click="prevWeek()"
                        class="btn-nav"
                        :disabled="loading.fetchData"
                        :class="{ loading: loading.fetchData }"
                    >
                        <i
                            class="bi bi-chevron-left d-flex justify-content-center align-items-center"
                        ></i>
                        <span class="sm-nav">Sebelum</span>
                    </button>

                    <!-- Date Picker -->
                    <div class="date-picker-container">
                        <input
                            type="date"
                            v-model="selectedDate"
                            @change="onDateChange"
                            placeholder="Pilih tanggal"
                            class="date-picker form-control"
                        />
                    </div>

                    <button
                        @click="nextWeek()"
                        class="btn-nav"
                        :disabled="loading.fetchData"
                        :class="{ loading: loading.fetchData }"
                    >
                        <span class="sm-nav">Lanjut</span>
                        <i
                            class="bi bi-chevron-right d-flex justify-content-center align-items-center"
                        ></i>
                    </button>
                </div>

                <!-- Search and Filters -->
                <div class="search-filter-section">
                    <!-- Search Bar -->
                    <div class="search-container">
                        <i
                            class="bi bi-search search-icon d-flex justify-content-center align-items-center"
                        ></i>
                        <input
                            type="text"
                            class="search-input"
                            placeholder="Cari karyawan..."
                            v-model="searchQuery"
                        />
                        <div
                            class="search-results-count"
                            v-if="searchQuery && !loading.fetchData"
                        >
                            {{ employees.length }} hasil
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="filters-container">
                        <!-- Department Filter -->

                        <div class="filter-dropdown">
                            <div
                                class="filter-btn py-2 position-relative"
                                style="width: fit-content !important"
                            >
                                <button
                                    @click="openDialog"
                                    class="toggle-btn active"
                                    title="Card View"
                                >
                                    <i
                                        class="bi bi-file-earmark-arrow-up d-flex justify-content-center align-items-center"
                                    ></i>
                                </button>
                            </div>
                        </div>
                        <!-- View Toggle -->
                        <div class="view-toggle py-2">
                            <button
                                class="toggle-btn"
                                :class="{ active: viewMode === 'table' }"
                                @click="viewMode = 'table'"
                                title="Table View"
                            >
                                <i
                                    class="bi bi-table d-flex justify-content-center align-items-center"
                                ></i>
                            </button>
                            <button
                                class="toggle-btn"
                                :class="{ active: viewMode === 'card' }"
                                @click="viewMode = 'card'"
                                title="Card View"
                            >
                                <i
                                    class="bi bi-grid-3x3-gap d-flex justify-content-center align-items-center"
                                ></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="row d-flex justify-content-center fade-in">
        <div class="col-md-11">
            <div class="content-container">
                <!-- Loading State -->
                <div v-if="loading.fetchData" class="loading-container">
                    <div class="loading-animation">
                        <DotLottieVue
                            style="height: 150px; width: 150px"
                            autoplay
                            loop
                            src="/animation/loading2.lottie"
                        />
                        <!-- <div class="loading-spinner"></div> -->
                        <div class="loading-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                    <p class="loading-text">Memuat data shift karyawan...</p>
                </div>

                <!-- Table View -->
                <div
                    v-else-if="!loading.fetchData && viewMode === 'table'"
                    class="table-container"
                >
                    <div class="table-wrapper">
                        <div
                            v-if="
                                this.employees == 0 && !this.loading.fetchData
                            "
                            class="empty-state d-flex flex-column justify-content-center align-items-center"
                        >
                            <div class="empty-icon">
                                <DotLottieVue
                                    style="height: 250px; width: 250px"
                                    autoplay
                                    loop
                                    src="/animation/empty-data.json"
                                />
                            </div>
                            <h3>Tidak ada karyawan ditemukan</h3>
                            <!-- <p>Coba ubah filter atau kata kunci pencarian Anda</p> -->
                            <button
                                class="btn-modern btn-primary"
                                @click="clearFilters"
                            >
                                <i class="bi bi-arrow-clockwise me-2"></i>
                                Reset Filter
                            </button>
                        </div>
                        <table v-else class="modern-table">
                            <thead>
                                <tr>
                                    <th class="sticky-column employee-header">
                                        <i class="bi bi-people me-2"></i>
                                        Karyawan
                                        <small class="employee-count"
                                            >({{ totalData }})</small
                                        >
                                    </th>
                                    <th
                                        v-for="(day, idx) in Tanggal"
                                        :key="idx"
                                        class="day-header"
                                        :class="{ today: isToday(day) }"
                                    >
                                        <div class="day-info">
                                            <div class="day-name">
                                                {{ getDayName(day) }}
                                            </div>
                                            <div class="day-date">
                                                {{ formatDateShort(day) }}
                                            </div>
                                        </div>
                                        <div
                                            class="day-indicator"
                                            v-if="isToday(day)"
                                        ></div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(employee, empIndex) in employees"
                                    :key="empIndex"
                                    class="employee-row"
                                    :class="{ highlight: employee.hasConflict }"
                                >
                                    <td class="sticky-column employee-cell">
                                        <div class="employee-info px-2">
                                            <div class="employee-avatar">
                                                {{ getInitials(employee.name) }}
                                            </div>
                                            <div class="employee-details">
                                                <div class="employee-name">
                                                    {{ employee.name }}
                                                </div>
                                                <div class="employee-id">
                                                    {{
                                                        getData(
                                                            employee.Kode_Karyawan
                                                        )?.nama_divisi ||
                                                        "Tidak ada divisi"
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td
                                        v-for="(day, dayIndex) in Tanggal"
                                        :key="dayIndex"
                                        class="shift-cell"
                                        @click="
                                            openShiftModal(
                                                employee.userId,
                                                dayIndex,
                                                day
                                            )
                                        "
                                    >
                                        <div
                                            class="shift-container"
                                            :class="[
                                                getShiftClass(
                                                    getEmployeeShiftForDay(
                                                        employee.userId,
                                                        dayIndex
                                                    )
                                                ),
                                                {
                                                    interactive: true,
                                                    today: isToday(day),
                                                },
                                            ]"
                                        >
                                            <div class="shift-content">
                                                <span class="shift-name">
                                                    {{
                                                        getEmployeeShiftForDay(
                                                            employee.userId,
                                                            dayIndex
                                                        )
                                                    }}
                                                </span>
                                                <i
                                                    class="bi bi-pencil-square edit-icon"
                                                ></i>
                                            </div>
                                            <div class="shift-time">
                                                {{
                                                    getShiftTime(
                                                        getEmployeeShiftForDay(
                                                            employee.userId,
                                                            dayIndex
                                                        ),
                                                        dayIndex
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div
                        class="pagination-container mt-3"
                        v-if="totalPages > 1"
                    >
                        <button
                            class="page-btn"
                            @click="fetchWeekDates(prevLink)"
                            :disabled="currentPage === 1"
                        >
                            <i
                                class="bi bi-chevron-left d-flex justify-content-center align-items-center"
                            ></i>
                        </button>
                        <span class="page-info">
                            {{ currentPage }} / {{ totalPages }}
                        </span>
                        <button
                            class="page-btn"
                            @click="fetchWeekDates(nextLink)"
                            :disabled="currentPage === totalPages"
                        >
                            <i
                                class="bi bi-chevron-right d-flex justify-content-center align-items-center"
                            ></i>
                        </button>
                    </div>
                </div>

                <!-- Card View -->
                <div
                    v-else-if="!loading.fetchData && viewMode === 'card'"
                    class="card-view"
                >
                    <div
                        v-if="this.employees == 0 && !this.loading.fetchData"
                        class="empty-state d-flex flex-column justify-content-center align-items-center"
                    >
                        <div class="empty-icon">
                            <DotLottieVue
                                style="height: 250px; width: 250px"
                                autoplay
                                loop
                                src="/animation/empty-data.json"
                            />
                        </div>
                        <h3>Tidak ada karyawan ditemukan</h3>
                        <!-- <p>Coba ubah filter atau kata kunci pencarian Anda</p> -->
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
                            v-for="(employee, empIndex) in employees"
                            :key="empIndex"
                            @click="cardOpen(employee.userId)"
                            class="employee-card"
                        >
                            <div class="card-header">
                                <div class="employee-avatar large">
                                    {{ getInitials(employee.name) }}
                                </div>
                                <div
                                    class="employee-info d-flex flex-column align-items-start gap-0 justify-content-center"
                                >
                                    <h4>{{ employee.name }}</h4>
                                    <p>{{ divisi }}</p>
                                </div>
                            </div>
                            <div
                                class="card-shifts"
                                :style="{
                                    display:
                                        employee.isOpen == false
                                            ? 'none'
                                            : 'flex',
                                }"
                            >
                                <div
                                    v-for="(day, dayIndex) in Tanggal"
                                    :key="dayIndex"
                                    class="card-shift-item"
                                    @click.stop="
                                        openShiftModal(
                                            employee.userId,
                                            dayIndex,
                                            day
                                        )
                                    "
                                >
                                    <div class="shift-day">
                                        <span>{{ getDayName(day) }}</span>
                                        <small>{{
                                            formatDateShort(day)
                                        }}</small>
                                    </div>
                                    <div
                                        class="shift-badge-card"
                                        :class="
                                            getShiftClass(
                                                getEmployeeShiftForDay(
                                                    employee.userId,
                                                    dayIndex
                                                )
                                            )
                                        "
                                    >
                                        {{
                                            getEmployeeShiftForDay(
                                                employee.userId,
                                                dayIndex
                                            )
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div
                        class="pagination-container mt-3"
                        v-if="totalPages > 1"
                    >
                        <button
                            class="page-btn"
                            @click="fetchWeekDates(prevLink)"
                            :disabled="currentPage === 1"
                        >
                            <i
                                class="bi bi-chevron-left d-flex justify-content-center align-items-center"
                            ></i>
                        </button>
                        <span class="page-info">
                            {{ currentPage }} / {{ totalPages }}
                        </span>
                        <button
                            class="page-btn"
                            @click="fetchWeekDates(nextLink)"
                            :disabled="currentPage === totalPages"
                        >
                            <i
                                class="bi bi-chevron-right d-flex justify-content-center align-items-center"
                            ></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modern Modal for Shift Assignment -->
    <div
        class="modal-overlay"
        :class="{ show: showShiftModal }"
        @click="closeShiftModal"
    >
        <div class="modal-container" @click.stop>
            <div class="modal-header">
                <h3>Edit Shift</h3>
                <button class="modal-close" @click="closeShiftModal">
                    <i
                        class="bi bi-x-lg d-flex justify-content-center align-items-center"
                    ></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group border-0 px-2 mb-2">
                    <label>Karyawan:</label>
                    <p class="employee-name">{{ selectedEmployee?.name }}</p>
                </div>
                <div class="form-group border-0 px-2">
                    <label>Tanggal:</label>
                    <p class="shift-date">{{ formatDate(selectedDate) }}</p>
                </div>
                <label class="form-label pilihShift">Pilih Shift:</label>
                <div class="form-group">
                    <div class="shift-options assign-shift-options">
                        <div
                            v-if="loading.modalData"
                            class="employee-selection-list p-5 d-flex flex-column justify-content-center align-items-center"
                        >
                            <DotLottieVue
                                style="height: 150px; width: 150px"
                                autoplay
                                loop
                                src="/animation/loading2.lottie"
                            />
                            <span class="fw-semibold fs-5">Loading...</span>
                        </div>
                        <div
                            v-if="!loading.modalData && jenisShift?.length == 0"
                            class="empty-state d-flex flex-column justify-content-center align-items-center"
                        >
                            <div class="empty-icon">
                                <DotLottieVue
                                    style="height: 150px; width: 150px"
                                    autoplay
                                    loop
                                    src="/animation/empty-data.json"
                                />
                            </div>
                            <h3>Tidak ada shift ditemukan</h3>
                        </div>
                        <div
                            v-else
                            v-for="shift in jenisShift"
                            :key="shift.ID_Shift"
                            class="shift-option assign-shift-option"
                            :class="{
                                selected:
                                    assignSelectedShift === shift.ID_Shift,
                            }"
                            @click="assignSelectedShift = shift.ID_Shift"
                        >
                            <div class="d-block">
                                <div
                                    class="shift-badge large"
                                    :class="getShiftClass(shift.Shift)"
                                >
                                    {{ shift.Shift }}
                                </div>
                                <div class="shift-details">
                                    <div class="shift-description text-start">
                                        {{
                                            shift.Jam_Masuk.split(":")
                                                .slice(0, 2)
                                                .join(":") +
                                            " - " +
                                            shift.Jam_Keluar.split(":")
                                                .slice(0, 2)
                                                .join(":")
                                        }}
                                    </div>
                                </div>
                            </div>
                            <div class="shift-radio">
                                <i
                                    class="bi bi-record-circle-fill"
                                    v-if="
                                        assignSelectedShift === shift.ID_Shift
                                    "
                                ></i>
                                <i class="bi bi-circle" v-else></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button
                    class="btn-modern btn-secondary"
                    @click="closeShiftModal"
                >
                    Batal
                </button>
                <button
                    :disabled="
                        !assignSelectedShift ||
                        loading.simpan ||
                        loading.modalData
                    "
                    class="btn-modern btn-primary"
                    @click="
                        updateAlertButton(
                            selectedEmployee?.name,
                            formatDate(selectedDate)
                        )
                    "
                >
                    <i class="bi bi-check-lg me-2"></i>
                    Simpan
                </button>
            </div>
        </div>
    </div>

    <!--  Assign Shift Modal -->
    <div class="modal-overlay" :class="{ show: showAssignModal }">
        <div class="modal-container assign-modal" @click.stop>
            <div class="modal-header">
                <div class="header-title-section">
                    <h3>
                        <i
                            class="bi bi-calendar-plus me-3 d-flex justify-content-center align-items-center"
                        ></i>
                        Pengajuan Shift
                    </h3>
                    <p class="header-subtitle">
                        Atur jadwal shift untuk karyawan
                    </p>
                </div>
                <button class="modal-close" @click="closeAssignModal">
                    <i
                        class="bi bi-x-lg d-flex justify-content-center align-items-center"
                    ></i>
                </button>
            </div>

            <div class="modal-body">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div
                        class="step"
                        :class="{
                            active: assignStep === 1,
                            completed: assignStep > 1,
                        }"
                    >
                        <div class="step-number">1</div>
                        <div class="step-label">Pilih Karyawan</div>
                    </div>
                    <div
                        class="step-line"
                        :class="{ active: assignStep > 1 }"
                    ></div>
                    <div
                        class="step"
                        :class="{
                            active: assignStep === 2,
                            completed: assignStep > 2,
                        }"
                    >
                        <div class="step-number">2</div>
                        <div class="step-label">Pilih Tanggal</div>
                    </div>
                    <div
                        class="step-line"
                        :class="{ active: assignStep > 2 }"
                    ></div>
                    <div class="step" :class="{ active: assignStep === 3 }">
                        <div class="step-number">3</div>
                        <div class="step-label">Atur Shift</div>
                    </div>
                </div>

                <!-- Step 1: Employee Selection -->
                <div v-if="assignStep === 1" class="step-content">
                    <div class="form-section">
                        <h4 class="section-title">
                            <i
                                class="bi bi-people me-2 d-flex justify-content-center align-items-center"
                            ></i>
                            Pilih Karyawan
                        </h4>

                        <!-- Search Employee -->
                        <div class="search-container assign-search">
                            <i
                                class="bi bi-search search-icon d-flex justify-content-center align-items-center"
                            ></i>
                            <input
                                type="text"
                                class="search-input"
                                placeholder="Cari nama karyawan..."
                                v-model="assignSearchQuery"
                            />
                        </div>

                        <!-- Employee List -->
                        <div class="employee-selection-list">
                            <div
                                v-for="employee in filteredAssignEmployees"
                                :key="employee.UserID_Absen"
                                class="employee-selection-item"
                                :class="{
                                    selected: selectedAssignEmployees.includes(
                                        employee.Kode_Karyawan
                                    ),
                                }"
                                @click="
                                    toggleEmployeeSelection(
                                        employee.Kode_Karyawan
                                    )
                                "
                            >
                                <div class="employee-checkbox">
                                    <i
                                        class="bi bi-check d-flex justify-content-center align-items-center"
                                        v-if="
                                            selectedAssignEmployees.includes(
                                                employee.Kode_Karyawan
                                            )
                                        "
                                    ></i>
                                </div>
                                <div class="employee-avatar">
                                    {{ getInitials(employee.Nama) }}
                                </div>
                                <div class="employee-details">
                                    <div class="employee-Nama">
                                        {{ employee.Nama }}
                                    </div>

                                    <div class="employee-department">
                                        {{
                                            employee.nama_divisi?.toUpperCase()
                                        }}
                                    </div>
                                    <!-- <div class="" style="font-size: 0.6rem">
                                        {{ console.log(employee) }}
                                    </div> -->
                                </div>
                            </div>
                        </div>

                        <!-- Select All Options -->
                        <div class="selection-actions">
                            <button
                                class="btn-link"
                                @click="selectAllEmployees"
                                v-if="
                                    selectedAssignEmployees.length <
                                    filteredAssignEmployees.length
                                "
                            >
                                <i class="bi bi-check-all me-1"></i>
                                Pilih Semua
                            </button>
                            <button
                                class="btn-link"
                                @click="clearEmployeeSelection"
                                v-if="selectedAssignEmployees.length > 0"
                            >
                                <i class="bi bi-x-circle me-1"></i>
                                Hapus Pilihan
                            </button>
                        </div>

                        <!-- Selected Count -->
                        <div
                            class="selected-count"
                            v-if="selectedAssignEmployees.length > 0"
                        >
                            <i
                                class="bi bi-info-circle me-2 d-flex justify-content-center align-items-center"
                            ></i>
                            {{ selectedAssignEmployees.length }} karyawan
                            dipilih
                        </div>
                    </div>
                </div>

                <!-- Step 2: Date Selection -->
                <div v-if="assignStep === 2" class="step-content">
                    <div class="form-section">
                        <h4 class="section-title">
                            <i
                                class="bi bi-calendar-event me-2 d-flex justify-content-center align-items-center"
                            ></i>
                            Pilih Tanggal & Periode
                        </h4>

                        <!-- Shift Type Selection -->
                        <div class="form-group my-0 align-items-center">
                            <div class="shift-type-options row">
                                <div class="col-6">
                                    <div
                                        class="shift-type-card"
                                        :class="{
                                            selected:
                                                assignShiftType === 'temporary',
                                        }"
                                        @click="assignShiftType = 'temporary'"
                                    >
                                        <div
                                            class="shift-type-icon d-none d-md-flex"
                                        >
                                            <i
                                                class="bi bi-clock-history d-md-flex d-none justify-content-center align-items-center"
                                            ></i>
                                        </div>
                                        <div class="shift-type-content">
                                            <h6>Shift Sementara</h6>
                                            <span class="d-md-block d-none">
                                                Atur shift untuk tanggal
                                                tertentu saja
                                            </span>
                                        </div>
                                        <div class="shift-type-check">
                                            <i
                                                class="bi bi-check-circle-fill d-flex justify-content-center align-items-center"
                                                v-if="
                                                    assignShiftType ===
                                                    'temporary'
                                                "
                                            ></i>
                                            <i class="bi bi-circle" v-else></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div
                                        class="shift-type-card"
                                        :class="{
                                            selected:
                                                assignShiftType === 'permanent',
                                        }"
                                        @click="assignShiftType = 'permanent'"
                                    >
                                        <div
                                            class="shift-type-icon d-none d-md-flex"
                                        >
                                            <i
                                                class="bi bi-arrow-repeat d-md-flex d-none justify-content-center align-items-center"
                                            ></i>
                                        </div>
                                        <div class="shift-type-content">
                                            <h6>Shift Permanen</h6>
                                            <p class="d-none d-md-block">
                                                Atur shift untuk periode waktu
                                                tertentu
                                            </p>
                                        </div>
                                        <div class="shift-type-check">
                                            <i
                                                class="bi bi-check-circle-fill"
                                                v-if="
                                                    assignShiftType ===
                                                    'permanent'
                                                "
                                            ></i>
                                            <i class="bi bi-circle" v-else></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Date Range Selection -->
                        <div class="date-selection">
                            <div
                                class="form-group"
                                v-if="assignShiftType === 'temporary'"
                            >
                                <label class="form-label">Pilih Tanggal:</label>
                                <!-- <div class="date-picker-grid">
                                    <div
                                        v-for="(day, idx) in tanggalLoop"
                                        :key="idx"
                                        class="date-picker-item"
                                        :class="{
                                            selected:
                                                selectedAssignDates.includes(
                                                    day
                                                ),
                                            today: isToday(day),
                                        }"
                                        @click="toggleDateSelection(day)"
                                    >
                                        <div class="date-day">
                                            {{ getDayName(day) }}
                                        </div>
                                        <div class="date-number">
                                            {{ formatDateShort(day) }}
                                        </div>
                                        <div
                                            class="date-check"
                                            v-if="
                                                selectedAssignDates.includes(
                                                    day
                                                )
                                            "
                                        >
                                            <i
                                                class="bi bi-check-circle-fill"
                                            ></i>
                                        </div>
                                    </div>
                                </div> -->
                                <el-date-picker
                                    v-model="selectedAssignDates"
                                    class="w-100"
                                    type="dates"
                                    format="YYYY-MM-DD"
                                    value-format="YYYY-MM-DD"
                                    :disabled-date="disabledDate"
                                    placeholder="Pilih satu tanggal atau lebih"
                                />
                            </div>

                            <div
                                class="form-group"
                                v-if="assignShiftType === 'permanent'"
                            >
                                <label class="form-label">Periode Shift:</label>
                                <div class="date-range-inputs">
                                    <div class="date-input-group">
                                        <!-- <input
                                            type="date"
                                            class="form-control date-input"
                                            v-model="assignStartDate"
                                            :min="minDate"
                                        /> -->

                                        <el-date-picker
                                            v-model="assignStartDate"
                                            class="w-100"
                                            type="date"
                                            format="YYYY-MM-DD"
                                            value-format="YYYY-MM-DD"
                                            :disabled-date="disabledDate"
                                            placeholder="Pilih satu tanggal"
                                        />
                                    </div>
                                    <!-- <div class="date-separator">—</div> -->
                                    <!-- <div class="date-input-group">
                                        <label>Tanggal Selesai:</label>
                                        <input
                                            type="date"
                                            class="form-control date-input"
                                            v-model="assignEndDate"
                                            :min="assignStartDate"
                                        />
                                    </div> -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Shift Configuration -->
                <div v-if="assignStep === 3" class="step-content">
                    <div class="form-section">
                        <h4 class="section-title">
                            <i
                                class="bi bi-gear me-2 d-flex justify-content-center align-items-center"
                            ></i>
                            Konfigurasi Shift
                        </h4>

                        <!-- Summary -->
                        <div class="assignment-summary">
                            <div class="summary-item">
                                <div class="summary-label">
                                    <i
                                        class="bi bi-people me-2 d-flex justify-content-center align-items-center"
                                    ></i>
                                    Karyawan Dipilih:
                                </div>
                                <div class="summary-value">
                                    {{ selectedAssignEmployees.length }} orang
                                </div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">
                                    <i
                                        class="bi bi-calendar me-2 d-flex justify-content-center align-items-center"
                                    ></i>
                                    Periode:
                                </div>
                                <div class="summary-value">
                                    {{
                                        assignShiftType === "temporary"
                                            ? `${selectedAssignDates.length} hari dipilih`
                                            : `${assignStartDate} s/d Seterusnya`
                                    }}
                                </div>
                            </div>
                        </div>

                        <!-- Shift Selection -->
                        <label class="form-label pilihShift"
                            >Pilih Shift:</label
                        >
                        <div class="form-group">
                            <div class="shift-options assign-shift-options">
                                <div
                                    v-if="loading.modalData"
                                    class="employee-selection-list p-5 d-flex flex-column justify-content-center align-items-center"
                                >
                                    <DotLottieVue
                                        style="height: 150px; width: 150px"
                                        autoplay
                                        loop
                                        src="/animation/loading2.lottie"
                                    />
                                    <span class="fw-semibold fs-5"
                                        >Loading...</span
                                    >
                                </div>
                                <div
                                    v-if="
                                        !loading.modalData &&
                                        jenisShift?.length == 0
                                    "
                                    class="empty-state d-flex flex-column justify-content-center align-items-center"
                                >
                                    <div class="empty-icon">
                                        <DotLottieVue
                                            style="height: 150px; width: 150px"
                                            autoplay
                                            loop
                                            src="/animation/empty-data.json"
                                        />
                                    </div>
                                    <h3>Tidak ada shift ditemukan</h3>
                                </div>
                                <div
                                    v-else
                                    v-for="shift in jenisShift"
                                    :key="shift.ID_Shift"
                                    class="shift-option assign-shift-option"
                                    :class="{
                                        selected:
                                            assignSelectedShift ===
                                            shift.ID_Shift,
                                    }"
                                    @click="
                                        assignSelectedShift = shift.ID_Shift
                                    "
                                >
                                    <div class="d-block">
                                        <div
                                            class="shift-badge large"
                                            :class="getShiftClass(shift.Shift)"
                                        >
                                            {{ shift.Shift }}
                                        </div>
                                        <div class="shift-details">
                                            <div
                                                class="shift-description text-start"
                                            >
                                                {{
                                                    shift.Jam_Masuk.split(":")
                                                        .slice(0, 2)
                                                        .join(":") +
                                                    " - " +
                                                    shift.Jam_Keluar.split(":")
                                                        .slice(0, 2)
                                                        .join(":")
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="shift-radio">
                                        <i
                                            class="bi bi-record-circle-fill"
                                            v-if="
                                                assignSelectedShift ===
                                                shift.ID_Shift
                                            "
                                        ></i>
                                        <i class="bi bi-circle" v-else></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <div class="footer-actions">
                    <button
                        v-if="assignStep > 1"
                        class="btn-modern btn-secondary"
                        @click="prevAssignStep"
                    >
                        <i
                            class="bi bi-arrow-left me-2 d-flex justify-content-center align-items-center"
                        ></i>
                        Kembali
                    </button>

                    <button
                        class="btn-modern btn-secondary"
                        @click="closeAssignModal"
                    >
                        Batal
                    </button>

                    <button
                        v-if="assignStep < 3"
                        class="btn-modern btn-primary"
                        @click="nextAssignStep"
                        :disabled="!canProceedToNextStep"
                    >
                        Lanjut
                        <i
                            v-if="!loading.modalData"
                            class="bi bi-arrow-right ms-2 d-flex justify-content-center align-items-center"
                        ></i>
                        <i
                            v-if="loading.modalData"
                            class="spinner-border spinner-border-sm ms-2"
                        ></i>
                    </button>

                    <button
                        v-if="assignStep === 3"
                        class="btn-modern btn-success"
                        @click="storeAlertButton"
                        :disabled="
                            !assignSelectedShift ||
                            loading.simpan ||
                            loading.modalData
                        "
                    >
                        <i
                            class="bi bi-check-lg me-2 d-flex justify-content-center align-items-center"
                        ></i>
                        Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- alert Modal -->
    <div
        class="modal-overlay"
        style="z-index: 1049 !important"
        :class="{ show: showAlertModal }"
        @click="() => closeAlertModal()"
    >
        <div class="modal-container" @click.stop>
            <div class="modal-header">
                <h4>{{ titleAlertModal }}</h4>
                <button class="modal-close" @click="() => closeAlertModal()">
                    <i
                        class="bi bi-x-lg d-flex justify-content-center align-items-center"
                    ></i>
                </button>
            </div>
            <div class="modal-body">{{ bodyAlert }}</div>
            <div class="modal-footer">
                <button
                    class="btn-modern btn-secondary"
                    @click="() => closeAlertModal()"
                >
                    Batal
                </button>
                <button
                    class="btn-modern btn-success"
                    :disabled="loading.simpan || loading.modalData"
                    @click="yesModal"
                >
                    <div
                        v-if="loading.simpan"
                        class="d-flex justify-content-center align-items-center"
                    >
                        <span
                            class="me-1 spinner-border"
                            role="status"
                            style="width: 1rem; height: 1rem"
                        ></span>
                        Loading..
                    </div>
                    <div
                        v-else
                        class="d-flex justify-content-center align-items-center"
                    >
                        <i
                            class="fs-5 bi bi-check-lg me-2 d-flex justify-content-center align-items-center"
                        ></i>
                        {{ buttonAlertModal }}
                    </div>
                </button>
            </div>
        </div>
    </div>

    <!-- Calendar -->
    <el-dialog
        v-model="dialogVisible"
        title="Pilih Tanggal Mulai dan Selesai"
        width="480px"
        z-index="1000"
        align-center
        style="z-index: 1046 !important"
    >
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

        <template #footer>
            <div class="dialog-footer">
                <el-button @click="handleCancel">Batal</el-button>
                <el-button
                    class="shiningEffect btn-primary"
                    :disabled="!tempEndDate"
                    @click="downloadExcel"
                >
                    Pilih Tanggal
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script>
import axios from "axios";
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import {
    ElTimePicker,
    ElMessage,
    ElDatePicker,
    ElDialog,
    ElCalendar,
    ElButton,
} from "element-plus";
import "element-plus/dist/index.css";
import { debounce } from "lodash";

export default {
    components: {
        ElTimePicker,
        DotLottieVue,
        ElDatePicker,
        ElCalendar,
        ElDialog,
        ElCalendar,
        ElButton,
    },
    props: {
        semuaShift: Array,
        divisi: String,
        // dataAll: Array,
        tanggalLoop: Array,
    },
    data() {
        return {
            totalPages: 0,
            lastPage: 0,
            nextLink: null,
            prevLink: null,
            currentPage: 1,
            paginate: [],
            totalData: 0,
            loading: {
                simpan: false,
                fetchData: false,
                modalData: false,
            },
            dataFinal: [],
            isLemburData: [],
            isIzinData: [],
            jenisShift: [],
            tanggalHariIni: new Date(),
            minDate: "",
            isDekstop: false,
            // export Excel
            shiftLama: "",

            dataExport: [],

            dialogVisible: false,
            selectedDate: [], // Ini akan menyimpan [startDate, endDate]
            tempStartDate: null,
            tempEndDate: null,
            calendarDate: new Date(),

            // alert modal
            titleAlertModal: "",
            buttonAlertModal: "",
            showAlertModal: false,
            param1: "",
            bodyAlert: "",
            yesModal: "",

            weekDates: [],
            Tanggal: [],
            startOfWeek: "",
            endOfWeek: "",
            employees: [],
            searchQuery: "",
            // loading: false,
            currentStartDate: new Date().toISOString().split("T")[0],
            selectedDate: new Date().toISOString().split("T")[0],

            // Filters
            selectedDepartment: null,
            selectedShift: null,
            departments: ["IT", "HR", "Finance", "Operations", "Marketing"],
            availableShifts: ["PAGI", "SIANG", "MALAM", "NOSHIFT"],

            // UI State
            viewMode: "table", // 'table' or 'card'
            activeDropdown: null,
            currentPage: 1,
            itemsPerPage: 20,

            // Edit Shift Modal
            showShiftModal: false,
            selectedEmployee: null,
            selectedShiftValue: null,
            selectedDayIndex: null,

            // NEW: Assign Shift Modal
            showAssignModal: false,
            assignStep: 1,
            assignSearchQuery: "",
            selectedAssignEmployees: [],
            assignShiftType: "temporary", // 'temporary' or 'permanent'
            selectedAssignDates: [],
            assignStartDate: new Date().toISOString().split("T")[0],
            assignEndDate: new Date().toISOString().split("T")[0],
            assignSelectedShift: null,
            replaceExistingShifts: false,
            sendNotification: true,
        };
    },
    computed: {
        formattedDate() {
            if (this.selectedDate.length < 2) return "";
            const [start, end] = this.selectedDate;
            if (!start || !end) return "";
            // Menggunakan fungsi dari methods
            return `${this.formatFullDate(start)} - ${this.formatFullDate(
                end
            )}`;
        },
        // filteredEmployees() {
        //     let filtered = this.employees;

        //     // Search filter
        //     if (this.searchQuery) {
        //         this.currentPage = 1;
        //         filtered = filtered.filter((employee) =>
        //             employee.name
        //                 .toLowerCase()
        //                 .includes(this.searchQuery.toLowerCase())
        //         );
        //     }

        //     // Department filter
        //     if (this.selectedDepartment) {
        //         filtered = filtered.filter(
        //             (employee) =>
        //                 employee.department === this.selectedDepartment
        //         );
        //     }

        //     // Shift filter
        //     if (this.selectedShift) {
        //         filtered = filtered.filter((employee) =>
        //             employee.shifts.includes(this.selectedShift)
        //         );
        //     }

        //     return filtered;
        // },

        filteredAssignEmployees() {
            let filtered = this.dataFinal;

            if (this.assignSearchQuery) {
                filtered = filtered.filter((employee) =>
                    employee.Nama.toLowerCase().includes(
                        this.assignSearchQuery.toLowerCase()
                    )
                );
            }

            return filtered;
        },

        // paginatedEmployees() {
        //     const start = (this.currentPage - 1) * this.itemsPerPage;
        //     const end = start + this.itemsPerPage;
        //     return this.filteredEmployees.slice(start, end);
        // },

        // totalPages() {
        //     return Math.ceil(this.filteredEmployees.length / this.itemsPerPage);
        // },

        canProceedToNextStep() {
            if (this.assignStep === 1) {
                return this.selectedAssignEmployees.length > 0;
            }
            if (this.assignStep === 2) {
                if (this.assignShiftType === "temporary") {
                    return this.selectedAssignDates.length > 0;
                } else {
                    return this.assignStartDate && this.assignEndDate;
                }
            }
            return true;
        },
    },
    mounted() {
        this.setMinimumDate();
        this.getTeam();
        this.getTime();
        document.addEventListener("click", this.closeDropdowns);

        this.checkScreenSize();
        window.addEventListener("resize", this.checkScreenSize);
    },
    beforeUnmount() {
        document.removeEventListener("click", this.closeDropdowns);
        window.removeEventListener("resize", this.checkScreenSize);
    },
    watch: {
        searchQuery() {
            this.debouncedSearch();
        },
    },
    methods: {
        async getTeam() {
            this.loading.fetchData = true;
            try {
                const res = await axios.get("/swapShift/getTeam");
                if (res.status == 200) {
                    this.dataFinal = res.data.result;
                    this.fetchWeekDates("/swapShift/getWeek");
                }
            } catch (error) {
                this.employees = [];
                this.loading.fetchData = false;
                ElMessage.error(error);
            }
        },
        changePage(page) {
            if (page >= 1 && page <= this.pagination.totalPage) {
                this.$emit("change-page", page);
            }
        },
        getData(usr) {
            // console.log(this.dataFinal);
            return this.dataFinal.find((emp) => emp.Kode_Karyawan === usr);
        },
        dayNight() {
            const sekarang = new Date();
            const jam = sekarang.getHours();

            let keteranganWaktu;

            if (jam >= 6 && jam < 18) {
                keteranganWaktu = "Siang";
            } else {
                keteranganWaktu = "Malam";
            }

            return keteranganWaktu;
        },
        getShiftTime(shift, date) {
            const Hari = date + 1;

            const shiftChoose = this.semuaShift.find((shf) => {
                return shift == shf.Nama && Hari == shf.Hari;
            });

            if (shiftChoose) {
                // console.log(shiftChoose);
                return (
                    shiftChoose.Jam_Masuk.split(":").slice(0, 2).join(":") +
                    " - " +
                    shiftChoose.Jam_Keluar.split(":").slice(0, 2).join(":")
                );
            } else {
                // console.log("Shift not found");
                return "--"; // or an appropriate value indicating no shift found
            }
        },
        formatTime(start_time, end_time) {
            const start = new Date(start_time);
            const end = new Date(end_time);

            // Format waktu dalam gaya yang mudah dibaca
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

        async getShift(date) {
            this.loading.modalData = true;
            try {
                const response = await axios.get("/swapShift/getShift", {
                    params: {
                        tanggal: date,
                        type: this.assignShiftType,
                    },
                });

                if (response.status == 200) {
                    // console.log(response);
                    this.jenisShift = response.data.data ?? [];
                } else {
                    ElMessage({
                        showClose: true,
                        message: "Terjadi Kesalahan response dari server",
                        type: "error",
                        customStyle: {
                            "z-index": "1050",
                        },
                    });
                    this.jenisShift = [];
                }
            } catch (error) {
                // console.error("Error Fetching Time :", error);
                ElMessage({
                    showClose: true,
                    message:
                        "Terjadi Kesalahan, Silahkan Coba lagi beberapa saat",
                    type: "error",
                    customStyle: {
                        "z-index": "1050",
                    },
                });
                this.jenisShift = [];
                this.closeAlertModal();
            } finally {
                this.loading.modalData = false;
            }
        },
        setMinimumDate() {
            const today = new Date();

            // Logika inti: tambahkan 2 hari dari tanggal hari ini
            today.setDate(today.getDate());

            // Format tanggal ke format 'YYYY-MM-DD' yang dibutuhkan oleh atribut 'min'
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, "0"); // bulan 0-11, jadi +1
            const day = String(today.getDate()).padStart(2, "0");

            const formattedMinDate = `${year}-${month}-${day}`;

            // Setel state komponen
            this.minDate = formattedMinDate;

            // Opsional: setel tanggal yang dipilih secara default ke tanggal minimum
            this.assignStartDate = this.minDate;
        },
        isDatesame(date1, date2) {
            if (this.formatDate(date1) === this.formatDate(date2)) {
                return this.formatDate(date1);
            } else {
                return (
                    this.formatDate(date1).split(" ").slice(0, 1).join(" ") +
                    " - " +
                    this.formatDate(date2)
                );
            }
        },
        async getTime() {
            this.loading.fetchData = true;
            try {
                const response = await axios.get("/getTime");

                if (response.status === 200) {
                    // misal response.data.data = "2025-09-05"
                    this.minDate = new Date(response.data.time);
                } else {
                    ElMessage({
                        showClose: true,
                        message: "Terjadi kesalahan response dari server",
                        type: "error",
                        customStyle: { "z-index": "1050" },
                    });
                    this.minDate = null;
                }
            } catch (error) {
                ElMessage({
                    showClose: true,
                    message:
                        "Terjadi kesalahan, silakan coba lagi beberapa saat",
                    type: "error",
                    customStyle: { "z-index": "1050" },
                });
                this.minDate = null;
            }
        },

        disabledDate(time) {
            if (!this.minDate) return false;

            // Buat minDate di-normalisasi ke jam 00:00:00
            const min = new Date(this.minDate);
            min.setHours(0, 0, 0, 0);

            // Bandingkan juga time yang dipilih di-normalisasi
            const current = new Date(time);
            current.setHours(0, 0, 0, 0);

            // Disable hanya kalau tanggal < minDate
            return current.getTime() < min.getTime();
        },
        async saveShift() {
            this.loading.simpan = true;

            // langsung tutup modal begitu klik save

            try {
                const response = await axios.post("/swapShift/update", {
                    params: {
                        employee: this.selectedEmployee,
                        date: this.selectedDate,
                        shift: this.assignSelectedShift,
                        shift_lama: this.shiftLama,
                    },
                });

                if (response.status === 200) {
                    ElMessage.success("Berhasil menyimpan shift!");
                    this.closeAlertModal();

                    this.closeShiftModal(); // kalau ada modal lain khusus shift
                    this.fetchWeekDates(); // refresh data di background
                }
            } catch (error) {
                ElMessage.error("Terjadi kesalahan, silakan coba lagi!");
            } finally {
                this.loading.simpan = false;
            }
        },
        async isLembur(type) {
            this.loading.modalData = true;

            let response;
            try {
                if (type === "assign") {
                    response = await axios.get("/swapShift/isLembur", {
                        params: {
                            employees: this.selectedAssignEmployees,
                            dates:
                                this.assignShiftType === "temporary"
                                    ? this.selectedAssignDates
                                    : [this.assignStartDate],
                        },
                    });
                } else {
                    response = await axios.get("/swapShift/isLembur", {
                        params: {
                            employees: [this.selectedEmployee.Kode_Karyawan],
                            dates: [this.selectedDate],
                        },
                    });
                }

                if (response.status === 200) {
                    this.isLemburData = response.data.data ?? [];
                    this.isIzinData = response.data.dataIzin ?? [];

                    if (
                        this.isLemburData.length === 0 &&
                        this.isIzinData.length === 0
                    ) {
                        if (type === "assign") {
                            this.assignStep++;

                            this.jenisShift = [];

                            const dateParam =
                                this.assignShiftType === "temporary"
                                    ? this.selectedAssignDates
                                    : this.assignStartDate;

                            this.getShift(dateParam);
                        } else {
                            this.saveShift();
                        }
                    } else {
                        if (this.isIzinData) {
                            this.isIzinData.forEach((e) => {
                                this.closeAlertModal();
                                ElMessage({
                                    showClose: true,
                                    message: `${e.Nama?.toLowerCase()} ada izin pada tanggal ${
                                        e.Tanggal_Masuk_Pulang
                                    }, Silahkan Hapus pada ${e.No_Transaksi}`,
                                    type: "error",
                                    customStyle: {
                                        "z-index": "1050",
                                    },
                                });
                            });
                        }
                        this.isLemburData.forEach((e) => {
                            const dateMessage = this.isDatesame(
                                e.Tanggal_Lembur_Dari,
                                e.Tanggal_Lembur_Sampai
                            );

                            this.closeAlertModal();
                            ElMessage({
                                showClose: true,
                                message: `${e.Nama?.toLowerCase()} ada Lembur pada tanggal ${dateMessage}, Silahkan Hapus pada ${
                                    e.No_Transaksi
                                }`,
                                type: "error",
                                customStyle: {
                                    "z-index": "1050",
                                },
                            });
                        });
                    }
                } else {
                    // Handle non-200 status codes
                    ElMessage({
                        showClose: true,
                        message: "Gagal memuat data lembur",
                        type: "error",
                        customStyle: {
                            "z-index": "1050",
                        },
                    });
                }
            } catch (error) {
                console.error("Error Fetching Lembur Data:", error);
                ElMessage({
                    showClose: true,
                    message:
                        "Terjadi kesalahan, silahkan coba lagi beberapa saat",
                    type: "error",
                    customStyle: {
                        "z-index": "1050",
                    },
                });
            } finally {
                this.loading.modalData = false;
            }
        },
        log() {
            console.log(this.jenisShift);
        },
        checkScreenSize() {
            if (window.innerWidth >= 768) {
                this.viewMode = "table";
            } else {
                this.viewMode = "card";
            }
        },
        cardOpen(userId) {
            const cardSelect = this.employees.find(
                (emp) => emp.userId === userId
            );
            if (cardSelect) {
                cardSelect.isOpen = !cardSelect.isOpen;
            }
        },
        formatDateToString(date) {
            if (!date) return "";
            const year = date.getFullYear();
            const month = (date.getMonth() + 1).toString().padStart(2, "0");
            const day = date.getDate().toString().padStart(2, "0");
            return `${year}-${month}-${day}`;
        },

        formatFullDate(dateString) {
            const [year, month, day] = dateString.split("-").map(Number);
            const date = new Date(Date.UTC(year, month - 1, day));
            const options = {
                weekday: "long",
                year: "numeric",
                month: "long",
                day: "numeric",
                timeZone: "UTC",
            };
            return date.toLocaleDateString("id-ID", options);
        },

        openDialog() {
            if (this.selectedDate.length === 2) {
                const [startStr, endStr] = this.selectedDate;
                this.tempStartDate = new Date(startStr + "T00:00:00");
                this.tempEndDate = new Date(endStr + "T00:00:00");
                this.calendarDate = new Date(startStr + "T00:00:00");
            } else {
                this.tempStartDate = null;
                this.tempEndDate = null;
                this.calendarDate = new Date();
            }
            this.dialogVisible = true;
        },

        handleCancel() {
            this.dialogVisible = false;
            this.selectedDate = [];
            this.tempStartDate = null;
            this.tempEndDate = null;
        },

        handleDateClick(data) {
            const clickedDate = data.date;
            if (this.tempStartDate && this.tempEndDate) {
                this.tempStartDate = clickedDate;
                this.tempEndDate = null;
            } else if (!this.tempStartDate) {
                this.tempStartDate = clickedDate;
            } else {
                if (clickedDate.getTime() < this.tempStartDate.getTime()) {
                    this.tempStartDate = clickedDate;
                } else {
                    this.tempEndDate = clickedDate;
                }
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

        confirmDate() {
            // this.loading = true;

            if (!this.tempStartDate || !this.tempEndDate) {
                ElMessage({
                    message: "Silakan pilih tanggal mulai dan selesai.",
                    type: "warning",
                });
                return;
            }
            this.selectedDate = [
                this.formatDateToString(this.tempStartDate),
                this.formatDateToString(this.tempEndDate),
            ];
            this.dialogVisible = false;
            console.log(this.selectedDate);
            this.getExportData(
                this.formatDateToString(this.tempStartDate),
                this.formatDateToString(this.tempEndDate)
            );
        },
        // Akhir Tanggal
        async getExportData(Start, End) {
            this.loading.simpan = true;
            try {
                const response = await axios.get("/swapShift/getExport", {
                    params: {
                        start: Start,
                        end: End,
                    },
                });

                if (response.status == 200) {
                    this.dataExport = Object.entries(response.data.data);
                    this.exportCustomExcel();
                    console.log(response.data);
                }
            } catch (error) {
                console.log(`Terjadi Error di : ${error}`);
                ElMessage({
                    showClose: true,
                    message:
                        "Terjadi Kesalahan, Silahkan Coba lagi beberapa saat",
                    type: "error",
                    customStyle: {
                        "z-index": "1050",
                    },
                });
                this.closeAlertModal();
            }
        },
        async exportCustomExcel() {
            const ExcelJS = await import("exceljs");
            const workbook = new ExcelJS.Workbook();
            const worksheet = workbook.addWorksheet("SHIFT EXPORT DATA");
            try {
                const headerStyle = {
                    fill: {
                        type: "pattern",
                        pattern: "solid",
                        fgColor: { argb: "ffeb9c" },
                    },
                    font: {
                        bold: true,
                    },
                    alignment: {
                        vertical: "middle",
                        horizontal: "center",
                    },
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
                    alignment: {
                        vertical: "middle",
                        horizontal: "left",
                    },
                };
                const centeredCellStyle = {
                    ...cellStyle,
                    alignment: { vertical: "middle", horizontal: "center" },
                };

                worksheet.mergeCells("B1:F1");
                worksheet.getCell("B1").value = "SHIFT EXPORT DATA";
                worksheet.getCell("B1").style = headerStyle;

                worksheet.getCell("A1").value = "Tanggal"; // Akan dimerge nanti

                // Baris 2 (Header Kolom)
                const headers = [
                    "Kode_Perusahaan",
                    "Kode_Karyawan",
                    "Nama",
                    "Shift",
                    "Tanggal",
                ];

                const headerRow = worksheet.getRow(2);
                headerRow.values = [null, ...headers];
                headerRow.eachCell(
                    { includeEmpty: true },
                    (cell, colNumber) => {
                        if (colNumber > 0) {
                            cell.style = headerStyle;
                        }
                    }
                );

                // Merge header "Tanggal" dari baris 1 ke 2
                worksheet.mergeCells("A1:A2");
                worksheet.getCell("A1").style = headerStyle;

                worksheet.columns = [
                    { key: "tanggal", width: 15 },
                    { key: "kodePerusahaan", width: 20 },
                    { key: "Kode_Karyawan", width: 15 },
                    { key: "Nama", width: 30 },
                    { key: "shift", width: 20 },
                    { key: "tanggalSh", width: 30 },
                ];

                // Pengisian Sel
                let currentRowIndex = 3;

                this.dataExport.forEach(([date, shift]) => {
                    const dateBlockStartRow = currentRowIndex;

                    shift.forEach((user) => {
                        const row = worksheet.getRow(currentRowIndex);
                        // Mengisi data dari kolom C dan seterusnya
                        row.values = {
                            kodePerusahaan: user.Kode_Perusahaan,
                            Kode_Karyawan: user.Kode_Karyawan,
                            Nama: user.Nama,
                            shift: user.Nama_Shift,
                            tanggalSh: user.Tanggal,
                        };

                        row.eachCell(
                            { includeEmpty: true },
                            (cell, colNumber) => {
                                if (colNumber > 1) {
                                    cell.style = cellStyle;
                                }
                            }
                        );

                        currentRowIndex++;
                    });

                    // Merge kolom "Tanggal" (Kolom A)
                    const dateBlockEndRow = currentRowIndex - 1;
                    if (dateBlockStartRow <= dateBlockEndRow) {
                        worksheet.mergeCells(
                            `A${dateBlockStartRow}:A${dateBlockEndRow}`
                        );
                        const cellA = worksheet.getCell(
                            `A${dateBlockStartRow}`
                        );
                        cellA.value = date;
                        cellA.style = centeredCellStyle;
                    }
                });

                const buffer = await workbook.xlsx.writeBuffer();
                const blob = new Blob([buffer], {
                    type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                });

                const link = document.createElement("a");
                link.href = URL.createObjectURL(blob);
                link.download = `Export_Shift_${this.formatDate(
                    this.tempStartDate
                )} s.d ${this.formatDate(this.tempEndDate)}.xlsx`;
                link.click();
                URL.revokeObjectURL(link.href);
                ElMessage({
                    showClose: true,
                    message: "Congrats, Berhasil Mengunduh File.",
                    type: "success",
                    customStyle: {
                        "z-index": "1050",
                    },
                });
            } catch (error) {
                console.log(`Terjadi Error di : ${error}`);
                ElMessage({
                    showClose: true,
                    message:
                        "Terjadi Kesalahan, Silahkan Coba lagi beberapa saat",
                    type: "error",
                    customStyle: {
                        "z-index": "1050",
                    },
                });
            } finally {
                this.loading.simpan = true;
                this.closeAlertModal();
                this.handleCancel();
            }
        },
        storeAlertButton() {
            this.titleAlertModal = `Simpan data shift`;
            this.buttonAlertModal = "Simpan";
            this.bodyAlert = "Apa kamu yakin akan Menyimpan shift ini?";
            this.yesModal = () => this.saveAssignShift();
            // this.AssignOvertime = false;
            this.showAlertModal = true;
        },
        updateAlertButton(user, tanggal) {
            this.titleAlertModal = `Update shift ${user}`;
            this.buttonAlertModal = "Simpan";
            this.bodyAlert = `Apa kamu yakin menyimpan shift pada ${tanggal}?`;
            this.yesModal = () => this.isLembur("table");
            // this.AssignOvertime = false;
            this.showAlertModal = true;
        },
        downloadExcel() {
            this.titleAlertModal = `Export Data Shift`;
            this.buttonAlertModal = "Download";
            this.bodyAlert = `Apa kamu yakin mengunduh shift ${this.formatDate(
                this.tempStartDate
            )} s.d ${this.formatDate(this.tempEndDate)}?`;
            this.yesModal = () => this.confirmDate();
            // this.AssignOvertime = false;
            this.showAlertModal = true;
        },
        // alertModal
        closeAlertModal() {
            this.showAlertModal = false;
            this.titleAlertModal = "";
            this.buttonAlertModal = "";
            this.param1 = "";
            this.yesModal = "";
        },
        // async fetchWeekDates() {
        //     this.loading.fetchData = true;
        //     try {
        //         const response = await axios.get("/swapShift/getWeek", {
        //             params: { start_date: this.currentStartDate },
        //         });

        //         this.weekDates = Object.values(response.data.week_dates);
        //         this.Tanggal = response.data.Tanggal;
        //         this.dataFinal = response.data.dataAll;
        //         // console.log(response.data.Tanggal);
        //         this.startOfWeek = this.formatDate(response.data.start_of_week);
        //         this.endOfWeek = this.formatDate(response.data.end_of_week);
        //         this.processEmployeesData();
        //     } catch (error) {
        //         console.error("Error fetching week dates:", error);
        //         ElMessage({
        //             showClose: true,
        //             message: "Tidak Menemukan User yang mempunyai shift",
        //             type: "error",
        //             customStyle: {
        //                 "z-index": "1050",
        //             },
        //         });
        //     }
        // },

        async fetchWeekDates(url = null) {
            try {
                this.loading.fetchData = true;
                const params = new URLSearchParams();
                if (this.searchQuery) {
                    params.append("search", this.searchQuery);
                }

                params.append("start_date", this.currentStartDate);

                const endpoint =
                    url || `/swapShift/getWeek?${params.toString()}`;

                const res = await axios.get(endpoint);
                if (res.status == 200) {
                    this.dataRecord = res.data.result.week_dates.data;
                    this.totalPages = res.data.result.week_dates.last_page;
                    this.lastPage = res.data.result.week_dates.last_page;
                    this.nextLink = res.data.result.week_dates.next_page_url;
                    this.prevLink = res.data.result.week_dates.prev_page_url;
                    this.currentPage = res.data.result.week_dates.current_page;
                    this.paginate = res.data.result.week_dates.links;
                    this.totalData = res.data.result.week_dates.total;
                    // console.log(this.totalData);

                    this.weekDates = Object.values(
                        res.data.result.week_dates.data
                    );
                    this.Tanggal = res.data.result.Tanggal;
                    // this.dataFinal = res.data.result.dataAll;
                    // console.log(res.data.result.Tanggal);
                    this.startOfWeek = this.formatDate(
                        res.data.result.start_of_week
                    );
                    this.endOfWeek = this.formatDate(
                        res.data.result.end_of_week
                    );
                    this.processEmployeesData();
                }
            } catch (error) {
                this.employees = [];
                if (error.status == 404) {
                    ElMessage({
                        showClose: true,
                        message: "Shift tidak ditemukan ditim ",
                        type: "error",
                        customStyle: {
                            "z-index": "1050",
                        },
                    });
                } else {
                    console.log(error);
                    ElMessage({
                        showClose: true,
                        message: "Terjadi kesalahan, silahkan coba lagi ",
                        type: "error",
                        customStyle: {
                            "z-index": "1050",
                        },
                    });
                }
            } finally {
                this.loading.fetchData = false;
            }
        },
        debouncedSearch: debounce(function () {
            this.fetchWeekDates();
        }, 500),

        processEmployeesData() {
            const employeesMap = new Map();
            // console.log("before: " + this.employees);
            this.weekDates.forEach((day) => {
                if (Object.keys(day.data).length == 7) {
                    const dayData = Object.values(day.data);
                    dayData.forEach((shiftData, dayIndex) => {
                        if (Array.isArray(shiftData)) {
                            console.log(shiftData[0]);
                            const shift = shiftData[0];
                            if (shift && day.Nama) {
                                const userId = shiftData.UserID_Absen;

                                if (!employeesMap.has(userId)) {
                                    employeesMap.set(userId, {
                                        userId: userId,
                                        name: shiftData.Nama,
                                        tanggal: shiftData.Tanggal,
                                        shifts: new Array(7).fill("NOSHIFT"),
                                        department: this.getRandomDepartment(),
                                        Kode_Karyawan: shiftData.Kode_Karyawan,
                                        isOpen: false,
                                    });
                                }

                                const shiftName = shift.Nama_Shift || "NOSHIFT";
                                employeesMap.get(userId).shifts[dayIndex] =
                                    shiftName;
                            }
                        }
                        if (shiftData instanceof Object) {
                            const userId = shiftData.UserID_Absen;

                            if (!employeesMap.has(userId)) {
                                employeesMap.set(userId, {
                                    userId: userId,
                                    tanggal: shiftData.Tanggal,
                                    name: shiftData.Nama || "Unknown",
                                    shifts: new Array(7).fill("NOSHIFT"),
                                    department: this.getRandomDepartment(),
                                    Kode_Karyawan: shiftData.Kode_Karyawan,

                                    isOpen: false,
                                });
                            }

                            const shiftName = shiftData.Nama_Shift || "NOSHIFT";

                            employeesMap.get(userId).shifts[dayIndex] =
                                shiftName;
                        }
                    });
                }
            });
            // console.log(this.Tanggal);

            this.employees = Array.from(employeesMap.values()).sort((a, b) =>
                a.name.localeCompare(b.name)
            );
            // console.log("after :" + this.employees);
            // console.log(employeesMap);
            // console.log(this.weekDates);
        },

        getEmployeeShiftForDay(userId, dayIndex) {
            const employee = this.employees.find(
                (emp) => emp.userId === userId
            );
            return employee ? employee.shifts[dayIndex] : "NOSHIFT";
        },

        getShiftClass(shift) {
            const classes = {
                NOSHIFT: "shift-noshift",
                PAGI: "shift-morning",
                SIANG: "shift-afternoon",
                MALAM: "shift-night",
            };

            const lastChar = shift.slice(-1);

            switch (lastChar) {
                case "P":
                    return "shift-morning";
                case "M":
                    return "shift-night";
                case "T":
                    return "shift-noshift";
                case "1":
                    return "shift-morning";
                case "2":
                    return "shift-afternoon";
                case "3":
                    return "shift-night";
                default:
                    return "shift-afternoon";
            }
        },

        getShiftDescription(shift) {
            const descriptions = {
                PAGI: "Shift pagi hari untuk operasional normal",
                SIANG: "Shift siang untuk coverage maksimal",
                MALAM: "Shift malam untuk operasional 24 jam",
                NOSHIFT: "Karyawan tidak dijadwalkan shift",
            };
            return descriptions[shift] || "";
        },

        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString("id-ID", {
                day: "2-digit",
                month: "short",
                year: "numeric",
            });
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
            return name
                .split(" ")
                .map((word) => word[0])
                .join("")
                .substring(0, 2)
                .toUpperCase();
        },

        getRandomDepartment() {
            return this.departments[
                Math.floor(Math.random() * this.departments.length)
            ];
        },

        // Navigation
        prevWeek() {
            const prevDate = new Date(this.currentStartDate);
            prevDate.setDate(prevDate.getDate() - 7);
            this.currentStartDate = prevDate.toISOString().split("T")[0];
            this.selectedDate = this.currentStartDate;
            this.fetchWeekDates();
        },

        nextWeek() {
            const nextDate = new Date(this.currentStartDate);
            nextDate.setDate(nextDate.getDate() + 7);
            this.currentStartDate = nextDate.toISOString().split("T")[0];
            this.selectedDate = this.currentStartDate;
            console.log(this.employees);
            this.fetchWeekDates();
        },

        onDateChange() {
            this.currentStartDate = this.selectedDate;
            this.fetchWeekDates();
        },

        // Filters
        toggleDropdown(type) {
            this.activeDropdown = this.activeDropdown === type ? null : type;
        },

        closeDropdowns() {
            this.activeDropdown = null;
        },

        selectDepartment(dept) {
            this.selectedDepartment = dept;
            this.activeDropdown = null;
            this.currentPage = 1;
        },

        selectShift(shift) {
            this.selectedShift = shift;
            this.activeDropdown = null;
            this.currentPage = 1;
        },

        clearFilters() {
            this.searchQuery = "";
            this.selectedDepartment = null;
            this.selectedShift = null;
            this.currentPage = 1;
        },

        // Pagination
        prevPage() {
            if (this.currentPage > 1) this.currentPage--;
        },

        nextPage() {
            if (this.currentPage < this.totalPages) this.currentPage++;
        },
        isClickable(dateStringOrObject) {
            // Normalize the clicked cell's date to the start of the day
            const cellDate = new Date(dateStringOrObject);
            cellDate.setHours(0, 0, 0, 0);

            // Normalize today's date to the start of the day
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const sementara = new Date(this.tanggalLoop[0]);
            sementara.setHours(0, 0, 0, 0);

            // Calculate the H+2 date (lusa)

            const hPlusTwoDate = new Date(today);
            // hPlusTwoDate.setDate(today.getDate() + 2);
            hPlusTwoDate.setDate(today.getDate());

            // const canClick = cellDate >= hPlusTwoDate;
            const canClick = cellDate >= sementara;

            return canClick;
        },
        // Edit Shift Modal
        openShiftModal(userId, dayIndex, date) {
            if (this.isClickable(date)) {
                // alert(this.isClickable(date));
                this.assignSelectedShift = null;
                this.jenisShift = [];
                const tanggalShift = [date];
                // console.log(date);
                this.getShift(tanggalShift);
                this.selectedEmployee = this.employees.find(
                    (emp) => emp.userId === userId
                );
                this.selectedDayIndex = dayIndex;
                this.selectedDate = date;
                this.selectedShiftValue = this.getEmployeeShiftForDay(
                    userId,
                    dayIndex
                );
                this.shiftLama = this.getEmployeeShiftForDay(userId, dayIndex);
                this.showShiftModal = true;
            } else {
                ElMessage({
                    showClose: true,
                    message: "Tidak Bisa megubah shift di hari sebelum nya.",
                    type: "error",
                    customStyle: {
                        "z-index": "1050",
                    },
                });
            }
        },

        closeShiftModal() {
            this.showShiftModal = false;
            this.selectedEmployee = null;
            this.selectedShiftValue = null;
            this.shiftLama = null;
        },

        closeAssignModal() {
            this.showAssignModal = false;
            this.resetAssignModal();
        },

        resetAssignModal() {
            this.assignStep = 1;
            this.assignSearchQuery = "";
            this.selectedAssignEmployees = [];
            this.assignShiftType = "temporary";
            this.selectedAssignDates = [];
            this.assignStartDate = new Date().toISOString().split("T")[0];
            this.assignEndDate = new Date().toISOString().split("T")[0];
            this.assignSelectedShift = null;
            this.replaceExistingShifts = false;
            this.sendNotification = true;
        },

        nextAssignStep() {
            if (this.assignStep == 1) {
                this.assignStep++;
            } else if (this.assignStep == 2) {
                this.isLembur("assign");
            } else if (this.assignStep == 3) {
                this.assignStep++;
            }
        },

        prevAssignStep() {
            if (this.assignStep > 1) {
                this.assignStep--;
            }
        },

        toggleEmployeeSelection(Kode_Karyawan) {
            const index = this.selectedAssignEmployees.indexOf(Kode_Karyawan);
            if (index === -1) {
                this.selectedAssignEmployees.push(Kode_Karyawan);
            } else {
                this.selectedAssignEmployees.splice(index, 1);
            }
        },

        selectAllEmployees() {
            this.selectedAssignEmployees = this.filteredAssignEmployees.map(
                (emp) => emp.Kode_Karyawan
            );
        },

        clearEmployeeSelection() {
            this.selectedAssignEmployees = [];
        },

        toggleDateSelection(date) {
            const index = this.selectedAssignDates.indexOf(date);
            if (index === -1) {
                this.selectedAssignDates.push(date);
            } else {
                this.selectedAssignDates.splice(index, 1);
            }
        },

        async saveAssignShift() {
            this.loading.simpan = true;

            const assignmentData = {
                employees: this.selectedAssignEmployees,
                shiftType: this.assignShiftType,
                dates:
                    this.assignShiftType === "temporary"
                        ? this.selectedAssignDates
                        : {
                              start: this.assignStartDate,
                              end: this.assignEndDate,
                          },
                shift: this.assignSelectedShift,
                replaceExisting: this.replaceExistingShifts,
                sendNotification: this.sendNotification,
            };

            // Tutup modal duluan biar user gak bisa klik tombol lagi

            try {
                const response = await axios.post("/swapShift/submit", {
                    params: { AssignShift: assignmentData },
                });

                if (response.status === 200) {
                    ElMessage({
                        showClose: true,
                        message: "Congrats, Berhasil mengupdate shift.",
                        type: "success",
                        customStyle: { "z-index": "1050" },
                    });

                    // fetch jalan di background, tidak ditunggu
                    this.closeAlertModal();
                    this.closeAssignModal();

                    this.fetchWeekDates();
                }
            } catch (error) {
                console.error("Error Fetching Time :", error);
                ElMessage({
                    showClose: true,
                    message:
                        "Terjadi Kesalahan, Silahkan Coba lagi beberapa saat",
                    type: "error",
                    customStyle: { "z-index": "1050" },
                });
            } finally {
                this.loading.simpan = false;
            }
        },
        showNotification(type, message) {
            console.log(`${type}: ${message}`);
        },
    },
};
</script>
<style>
/* Modal Calendar */
.el-overlay {
    z-index: 1050;
}
.dialog-footer {
    text-align: right;
}
.el-calendar-table .el-calendar-day {
    padding: 2px;
    height: 50px;
}
.el-dialog__body {
    padding-top: 10px;
    padding-bottom: 10px;
}

.custom-dialog {
    z-index: 1046 !important;
}

.custom-dialog .el-overlay {
    z-index: 1045 !important; /* overlay */
}

.custom-dialog .el-dialog {
    z-index: 1046 !important; /* konten */
}

/* Style untuk rentang tanggal */
.cell-wrapper {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 1;
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
.cell-wrapper:hover .date-cell {
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

/* Mengatur sudut untuk awal dan akhir rentang */
.is-start::before {
    left: 50%;
}
.is-end::before {
    right: 50%;
}
</style>
<style scoped>
/* Previous styles remain the same... */
/* Modern Color Palette */
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
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --gradient-success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --gradient-info: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --gradient-dark: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
}

/* Global Styles */
* {
    box-sizing: border-box;
}
.modal-overlay {
    display: none;
}
.modal-overlay.show {
    display: flex; /* atau block, sesuai desain */
}
body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
        sans-serif;
    color: var(--text);
    line-height: 1.6;
}

/* Modern Header */
.modern-header {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1),
        0 10px 10px -5px rgba(0, 0, 0, 0.04);
    min-height: 140px;
    margin-bottom: 0rem;
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
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    letter-spacing: -0.025em;
}

.subtitle {
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
    font-size: 1.1rem;
    font-weight: 400;
}

.header-actions {
    /* gap: 2rem; */
    height: 100%;
}

.date-range {
    display: flex;
    align-items: center;
    /* padding: 1rem 1.5rem; */
    background: rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.date-item {
    text-align: center;
}

.date-item small {
    display: block;
    opacity: 0.8;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.date-item span {
    font-weight: 600;
    font-size: 0.9rem;
}

/* Modern Buttons */
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

.btn-link {
    background: none;
    border: none;
    color: var(--primary);
    padding: 0.5rem;
    cursor: pointer;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-link:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

/* Control Panel */
.control-panel {
    background: var(--surface);
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    margin-bottom: 1.5rem;
}

.nav-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.btn-nav {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 120px;
    justify-content: center;
}

.btn-nav:hover:not(:disabled) {
    background: var(--primary);
    color: white;
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-nav:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-nav.loading {
    animation: pulse 1.5s ease-in-out infinite;
}

.date-picker-container {
    position: relative;
    display: flex;
    align-items: center;
}

.date-picker {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 12px;
    background: var(--surface);
    color: var(--text);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 160px;
}

.date-picker:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.date-picker-icon {
    position: absolute;
    right: 0.75rem;
    color: var(--text-muted);
    pointer-events: none;
}

/* Search and Filters */
.search-filter-section {
    display: flex;
    align-items: center;
    gap: 1rem;
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

.filters-container {
    display: flex;
    align-items: center;
    gap: 1rem;
}

/* Filter Dropdown */
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

.filter-btn .arrow {
    transition: transform 0.3s ease;
}

.filter-btn .arrow.rotate {
    transform: rotate(180deg);
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: var(--shadow-lg);
    z-index: 1047;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    max-height: 200px;
    overflow-y: auto;
}

.dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.dropdown-item:hover {
    background: var(--surface-soft);
}

.dropdown-item i.bi-check {
    color: var(--primary);
    font-weight: bold;
}

/* View Toggle */
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

/* Content Container */
.content-container {
    background: var(--surface);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
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

/* Modern Table */
.table-container {
    overflow: hidden;
}

.table-wrapper {
    overflow-x: auto;
    scrollbar-width: thin;
    scrollbar-color: var(--border) transparent;
}

.table-wrapper::-webkit-scrollbar {
    height: 8px;
}

.table-wrapper::-webkit-scrollbar-track {
    background: var(--surface-soft);
}

.table-wrapper::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 4px;
}

.modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.9rem;
}

.modern-table thead th {
    background: var(--surface-soft);
    padding: 1.25rem 0.5rem;
    text-align: left;
    font-weight: 600;
    color: var(--text);
    border-bottom: 2px solid var(--border);
    position: sticky;
    top: 0;
    /* z-index: 10; */
}

.sticky-column {
    position: sticky;
    left: 0;
    background: var(--surface) !important;
    z-index: 11;
    max-width: 14rem;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
}

.employee-header {
    /* min-width: 200px; */
    /* z-index: 1050; */
    position: relative;
    /* background-color: rgba(239, 68, 68, 0.05); */
    border-right: 1px solid var(--border);
}

.employee-count {
    color: var(--text-muted);
    font-weight: 400;
}

.day-header {
    min-width: 100px;
    text-align: center;
    /* position: relative; */
}

.day-header.today {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}

.day-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.day-name {
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.8rem;
}

.day-date {
    font-size: 0.85rem;
    opacity: 0.8;
}

.day-indicator {
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 30px;
    height: 3px;
    background: white;
    border-radius: 2px;
}

.employee-row {
    transition: all 0.3s ease;
}

.employee-row:hover {
    background: var(--surface-soft);
}

.employee-row.highlight {
    background: rgba(239, 68, 68, 0.05);
    border-left: 4px solid var(--danger);
}

.employee-cell {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    border-right: 1px solid var(--border);
}

.employee-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.employee-avatar {
    min-width: 40px;
    max-width: 40px;
    min-height: 35px;

    max-height: 40px;
    border-radius: 30%;
    background: var(--gradient-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
}

.employee-avatar.large {
    width: 60px;
    height: 60px;
    font-size: 1.1rem;
}

.employee-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.employee-name {
    font-weight: 600;
    color: var(--text);
}

.employee-id {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.shift-cell {
    padding: 0.5rem;
    border-bottom: 1px solid var(--border);
    text-align: center;
    cursor: pointer;
}

.shift-container {
    min-height: 5rem;
    max-height: 5rem;
    min-width: 5rem;
    padding: 0.75rem;
    border-radius: 12px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.shift-container.interactive:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.shift-container.today {
    border: 2px solid var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.shift-content {
    display: flex;
    align-items: center;
    text-align: start;
    justify-content: space-between;
    margin-bottom: 0.25rem;
}

.shift-name {
    font-weight: 900;

    font-size: 0.75rem;
}

.edit-icon {
    opacity: 0;
    transition: opacity 0.3s ease;
    font-size: 0.8rem;
}

.shift-container:hover .edit-icon {
    opacity: 1;
}

.shift-time {
    font-size: 0.75rem;
    text-align: start;
    opacity: 0.8;
}

/* Shift Status Colors */
.shift-morning {
    background: linear-gradient(135deg, #fef3c7, #fcd34d);
    color: #92400e;
}

.shift-afternoon {
    background: linear-gradient(135deg, #ddd6fe, #a78bfa);
    color: #5b21b6;
}

.shift-night {
    background: linear-gradient(135deg, #1f2937, #374151);
    color: white;
}

.shift-noshift {
    background: var(--surface-soft);
    color: var(--text-muted);
    border: 1px dashed var(--border);
}

/* Pagination */
.pagination-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding: 1.5rem;
    border-top: 1px solid var(--border);
}

.page-btn {
    width: 40px;
    height: 40px;
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
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

.page-info {
    font-weight: 600;
    color: var(--text);
    min-width: 80px;
    text-align: center;
}

/* Card View */
.card-view {
    padding: 1.5rem;
}

.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.employee-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.employee-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.card-header h4 {
    margin: 0 0 0.25rem 0;
    font-weight: 600;
    color: var(--text);
}

.card-header p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.card-shifts {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.card-shift-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem;
    background: var(--surface-soft);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.card-shift-item:hover {
    background: var(--primary);
    color: white;
    transform: translateX(4px);
}

.shift-day {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.shift-day span {
    font-weight: 600;
    font-size: 0.9rem;
}

.shift-day small {
    font-size: 0.8rem;
    opacity: 0.8;
}

.shift-badge-card {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
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

.empty-state h3 {
    margin: 0 0 1rem 0;
    color: var(--text);
}

.empty-state p {
    margin: 0 0 2rem 0;
    font-size: 1.1rem;
}

/* Modern Modal */
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
}

.modal-container.assign-modal {
    max-width: 700px;
    max-height: 95vh;
}

.modal-overlay.show .modal-container {
    transform: scale(1) translateY(0);
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
}

.form-group {
    max-height: 20rem;
    overflow-y: auto;
    margin-bottom: 0.1rem;
    border: 1px solid var(--border);
    background: rgba(99, 102, 241, 0.05);
    border-radius: 12px;
    padding: 1rem;
}

.form-group label,
.form-label {
    display: block;
    margin-bottom: 0.1rem;
    font-weight: 600;
    color: var(--text);
}

.form-group p {
    margin: 0;
    padding: 0.4rem 0;

    color: var(--text-muted);
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
}

.shift-options {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.shift-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    border: 2px solid var(--border);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.shift-option:hover {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.shift-option.selected {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
}

.shift-badge {
    border-radius: 8px;
    text-align: center;
    font-weight: 600;
    font-size: 0.9rem;
}

.shift-badge.large {
    font-size: 1rem;
}

.shift-details {
    text-align: right;
}

.modal-footer {
    padding: 1rem 2rem;
    gap: 0.5rem;
    border-top: 1px solid var(--border);
    background: var(--surface-soft);
}

.footer-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 1rem;
}

/* NEW: Assign Modal Specific Styles */

/* Step Indicator */
.step-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 2rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid var(--border);
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    transition: all 0.3s ease;
}

.step.active {
    color: var(--primary);
}

.step.completed {
    color: var(--success);
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--surface-soft);
    border: 2px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    transition: all 0.3s ease;
}

.step.active .step-number {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.step.completed .step-number {
    background: var(--success);
    border-color: var(--success);
    color: white;
}

.step-label {
    font-size: 0.85rem;
    font-weight: 500;
    text-align: center;
}

.step-line {
    width: 60px;
    height: 2px;
    background: var(--border);
    transition: all 0.3s ease;
}

.step-line.active {
    background: var(--primary);
}

/* Step Content */
.step-content {
    animation: fadeIn 0.5s ease-in-out;
}

.form-section {
    margin-bottom: 2rem;
}

.section-title {
    display: flex;
    align-items: center;
    margin-bottom: 1.5rem;
    color: var(--text);
    font-size: 1.1rem;
    font-weight: 600;
    border-bottom: 1px solid var(--border);
    padding-bottom: 0.75rem;
}

/* Employee Selection */
.assign-search {
    margin-bottom: 1.5rem;
}

.employee-selection-list {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 1rem;
}

.employee-selection-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    transition: all 0.3s ease;
}

.employee-selection-item:last-child {
    border-bottom: none;
}

.employee-selection-item:hover {
    background: var(--surface-soft);
}

.employee-selection-item.selected {
    background: rgba(99, 102, 241, 0.1);
    border-color: var(--primary);
}

.employee-checkbox {
    width: 24px;
    height: 24px;
    border: 2px solid var(--border);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-weight: bold;
    transition: all 0.3s ease;
}

.employee-selection-item.selected .employee-checkbox {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.employee-department {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.selection-actions {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.selected-count {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 10px;
    color: var(--primary);
    font-weight: 500;
    font-size: 0.9rem;
}

/* Shift Type Selection */
.shift-type-options {
    margin-bottom: 1.5rem;
}

.shift-type-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    border: 2px solid var(--border);
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: var(--surface);
}

.shift-type-card:hover {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.shift-type-card.selected {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
}
.pilihShift {
    border-bottom: 1px solid var(--border);
    padding: 1rem 0;
    margin: 1rem 0;
}
.shift-type-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    background: var(--surface-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--primary);
}

.shift-type-content h5 {
    margin: 0 0 0.25rem 0;
    font-weight: 600;
    color: var(--text);
}

.shift-type-content p {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-muted);
}

.shift-type-check {
    margin-left: auto;
    font-size: 1.25rem;
    color: var(--primary);
}

/* Date Selection */
.date-selection {
    margin-top: 1.5rem;
}

.date-picker-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(4rem, 1fr));
    gap: 0.75rem;
}

.date-picker-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.3rem;
    padding: 1rem 0;
    border: 2px solid var(--border);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.date-picker-item:hover {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.date-picker-item.selected {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
}

.date-picker-item.today {
    border-color: var(--warning);
    background: rgba(245, 158, 11, 0.1);
}

.date-day {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--text-muted);
}

.date-number {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text);
}

.date-check {
    position: absolute;
    top: -8px;
    right: -8px;
    color: var(--primary);
    font-size: 1.2rem;
}

.date-range-inputs {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.date-input-group {
    flex: 1;
}

.date-input-group label {
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.date-input {
    width: 100%;
}

/* Assignment Summary */
.assignment-summary {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.summary-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border);
}

.summary-item:last-child {
    border-bottom: none;
}

.summary-label {
    display: flex;
    align-items: center;
    font-weight: 500;
    color: var(--text);
}

.summary-value {
    font-weight: 600;
    color: var(--primary);
}

/* Assign Shift Options */
.assign-shift-options {
    gap: 1rem;
}

.assign-shift-option {
    padding: 1.5rem;
}

.shift-description {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.shift-radio {
    font-size: 1.25rem;
    color: var(--primary);
}

/* Additional Options */
.additional-options {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
}

.option-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.option-checkbox {
    width: 20px;
    height: 20px;
    margin: 0;
    accent-color: var(--primary);
}

.option-label {
    display: flex;
    align-items: center;
    font-weight: 500;
    color: var(--text);
    cursor: pointer;
    margin: 0;
}

.option-description {
    display: block;
    color: var(--text-muted);
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

/* Animations
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

@keyframes pulse {
    0%,
    100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.8;
        transform: scale(1.05);
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

@keyframes slideIn {
    from {
        transform: translateX(-100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        transform: translateY(100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes shake {
    0%,
    100% {
        transform: translateX(0);
    }
    10%,
    30%,
    50%,
    70%,
    90% {
        transform: translateX(-5px);
    }
    20%,
    40%,
    60%,
    80% {
        transform: translateX(5px);
    }
}

@keyframes zoomIn {
    from {
        opacity: 0;
        transform: scale(0.3);
    }
    50% {
        opacity: 1;
    }
}

@keyframes ripple {
    0% {
        transform: scale(0);
        opacity: 1;
    }
    100% {
        transform: scale(4);
        opacity: 0;
    }
}

/* Notification System */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    color: white;
    font-weight: 500;
    box-shadow: var(--shadow-lg);
    z-index: 1048;
    animation: slideIn 0.3s ease-out;
    min-width: 300px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.notification.success {
    background: var(--gradient-success);
}

.notification.error {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.notification.warning {
    background: var(--gradient-warning);
}

.notification.info {
    background: var(--gradient-info);
}

.notification-icon {
    font-size: 1.25rem;
}

.notification-content {
    flex: 1;
}

.notification-close {
    background: none;
    border: none;
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
    opacity: 0.8;
    transition: opacity 0.3s ease;
}

.notification-close:hover {
    opacity: 1;
}

/* Tooltip */
.tooltip {
    position: relative;
    cursor: help;
}

.tooltip::before {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 125%;
    left: 50%;
    transform: translateX(-50%);
    background: var(--dark);
    color: white;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8rem;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    z-index: 1048;
}

.tooltip::after {
    content: "";
    position: absolute;
    bottom: 115%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: var(--dark);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.tooltip:hover::before,
.tooltip:hover::after {
    opacity: 1;
    visibility: visible;
}

/* Scrollbar Styling */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--surface-soft);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 10px;
    transition: background 0.3s ease;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--text-muted);
}

::-webkit-scrollbar-corner {
    background: var(--surface-soft);
}

/* Focus Indicators */
*:focus {
    outline: none;
}

.focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* Selection */
::selection {
    background: rgba(99, 102, 241, 0.2);
    color: var(--text);
}

/* Responsive Design */
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
    .employee-avatar {
        width: 50px;
    }
    .header-info {
        /* flex-direction: column; */
        /* text-align: center; */
        width: 100%;
        padding-bottom: 0.6rem;
        gap: 1rem;
    }
    .el-dialog.is-align-center {
        margin: auto 1rem;
    }
    .el-calendar__header {
        border-bottom: var(--el-calendar-header-border-bottom);
        display: flex;
        justify-content: space-between;
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

    .nav-controls {
        /* flex-direction: column; */
        gap: 1rem;
    }

    .btn-nav {
        width: 100%;
        /* max-width: 200px; */
    }

    .search-filter-section {
        /* flex-direction: column; */
        align-items: stretch;
    }

    .filters-container {
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .filter-dropdown {
        flex: 1;
        width: fit-content;
        max-width: fit-content;
    }

    .cards-grid {
        grid-template-columns: 1fr;
    }

    .date-separator {
        display: none;
    }
}

@media (max-width: 768px) {
    .modern-header {
        min-height: 100px;
    }

    .header-content {
        padding: 1.5rem;
    }

    .header-info {
        gap: 0.75rem;
    }

    .sm-nav {
        display: none;
    }
    .btn-nav {
        max-width: fit-content;
        width: fit-content;
        min-width: 0;
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

    .employee-avatar {
        width: 35px;
        height: 35px;
        font-size: 0.75rem;
    }

    .shift-container {
        padding: 0.5rem;
    }

    .shift-name {
        font-size: 0.75rem;
    }

    .shift-time {
        font-size: 0.7rem;
    }

    .modal-container {
        width: 95%;
        margin: 1rem;
    }

    .modal-header {
        padding: 1rem;
    }

    .modal-body {
        padding: 1rem;
    }

    .modal-footer {
        padding: 0.8rem 1rem;
    }

    .footer-actions {
        gap: 0.75rem;
    }

    .footer-actions .btn-modern {
        width: 100%;
        justify-content: center;
    }

    .step-indicator {
        margin-bottom: 1.5rem;
        padding-bottom: 1.5rem;
    }

    .step-number {
        width: 35px;
        height: 35px;
        font-size: 0.9rem;
    }

    .step-label {
        font-size: 0.75rem;
    }

    .step-line {
        width: 40px;
    }

    .date-picker-grid {
        grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
        gap: 0.5rem;
    }

    .date-picker-item {
        padding: 0.75rem;
    }

    .employee-selection-item {
        padding: 0.75rem;
    }

    .employee-avatar.large {
        width: 50px;
        height: 50px;
        font-size: 1rem;
    }
}

@media (max-width: 576px) {
    .header-content {
        padding: 1rem;
    }
    /* .text-header {
        font-size: 11px !important;
    } */
    .title {
        font-size: 1.1rem;
    }

    .subtitle {
        font-size: 0.85rem;
    }

    .control-panel {
        padding: 0.75rem;
        margin-bottom: 0.5rem;
    }

    .nav-controls {
        margin-bottom: 1rem;
        padding-bottom: 1rem;
    }

    .search-filter-section {
        gap: 0.75rem;
    }

    .table-wrapper {
        border-radius: 12px;
        overflow-x: auto;
    }

    .sticky-column {
        min-width: 150px;
        position: relative;
        left: auto;
        z-index: 100;
        box-shadow: none;
    }

    .day-header {
        min-width: 120px;
    }

    .employee-info {
        gap: 0.75rem;
    }

    .employee-details {
        gap: 0.15rem;
    }

    .employee-name {
        font-size: 0.9rem;
    }

    .employee-id {
        font-size: 0.75rem;
    }

    .shift-cell {
        padding: 0.25rem;
    }

    .shift-container {
        padding: 0.5rem;
        border-radius: 8px;
    }

    .pagination-container {
        padding: 1rem;
    }

    .card-view {
        padding: 1rem;
    }

    .employee-card {
        padding: 1rem;
    }

    .card-header {
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
    }

    .modal-container.assign-modal {
        width: 98%;
        max-height: 90vh;
    }

    .assignment-summary {
        padding: 1rem;
    }

    .summary-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
        padding: 0.5rem 0;
    }

    .shift-type-card {
        padding: 0.8rem 0.5rem;
        gap: 0.75rem;
    }
    /* Shift Type Selection */
    .shift-type-options {
        margin-bottom: 0rem;
    }
    .text-sfhit-type {
        font-size: 10px;
    }

    .shift-type-icon {
        width: 40px;
        height: 40px;
        font-size: 1.25rem;
    }

    .option-item {
        align-items: flex-start;
        gap: 0.75rem;
    }

    .option-label {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
}

/* Print Styles */
@media print {
    .control-panel,
    .modal-overlay,
    .btn-modern,
    .edit-icon,
    .pagination-container {
        display: none !important;
    }

    .modern-header {
        background: white !important;
        color: black !important;
        box-shadow: none !important;
    }

    .header-background {
        display: none;
    }

    .modern-table {
        border-collapse: collapse;
    }

    .modern-table th,
    .modern-table td {
        border: 1px solid #000 !important;
        padding: 0.5rem !important;
    }

    .shift-container {
        background: transparent !important;
        border: 1px solid #000 !important;
    }

    body {
        background: white !important;
        color: black !important;
    }
}

/* Utility Classes */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.text-center {
    text-align: center;
}

.text-left {
    text-align: left;
}

.text-right {
    text-align: right;
}

.d-flex {
    display: flex;
}

.d-grid {
    display: grid;
}

.d-none {
    display: none;
}

.align-items-center {
    align-items: center;
}

.align-items-start {
    align-items: flex-start;
}

.justify-content-center {
    justify-content: center;
}

.justify-content-between {
    justify-content: space-between;
}

.justify-content-end {
    justify-content: flex-end;
}

.flex-wrap {
    flex-wrap: wrap;
}

.gap-1 {
    gap: 0.25rem;
}

.gap-2 {
    gap: 0.5rem;
}

.gap-3 {
    gap: 1rem;
}

.gap-4 {
    gap: 1.5rem;
}

.m-0 {
    margin: 0;
}

.mb-1 {
    margin-bottom: 0.25rem;
}

.mb-2 {
    margin-bottom: 0.5rem;
}

.mb-3 {
    margin-bottom: 1rem;
}

.mb-4 {
    margin-bottom: 1.5rem;
}

.p-0 {
    padding: 0;
}

.p-1 {
    padding: 0.25rem;
}

.p-2 {
    padding: 0.5rem;
}

.p-3 {
    padding: 1rem;
}

.w-100 {
    width: 100%;
}

.h-100 {
    height: 100%;
}

.position-relative {
    position: relative;
}

.position-absolute {
    position: absolute;
}

.rounded {
    border-radius: 0.5rem;
}

.rounded-lg {
    border-radius: 1rem;
}

.shadow {
    box-shadow: var(--shadow);
}

.shadow-lg {
    box-shadow: var(--shadow-lg);
}

.opacity-50 {
    opacity: 0.5;
}

.opacity-75 {
    opacity: 0.75;
}

.cursor-pointer {
    cursor: pointer;
}

.cursor-not-allowed {
    cursor: not-allowed;
}

.overflow-hidden {
    overflow: hidden;
}

.overflow-auto {
    overflow: auto;
}

.text-primary {
    color: var(--primary);
}

.text-secondary {
    color: var(--secondary);
}

.text-success {
    color: var(--success);
}

.text-warning {
    color: var(--warning);
}

.text-danger {
    color: var(--danger);
}

.text-muted {
    color: var(--text-muted);
}

.bg-primary {
    background-color: var(--primary);
}

.bg-secondary {
    background-color: var(--secondary);
}

.bg-success {
    background-color: var(--success);
}

.bg-warning {
    background-color: var(--warning);
}

.bg-danger {
    background-color: var(--danger);
}

.bg-surface {
    background-color: var(--surface);
}

.bg-surface-soft {
    background-color: var(--surface-soft);
}

/* Component Specific Utilities */
.fade-in {
    animation: fadeIn 0.5s ease-in-out;
}

.slide-in {
    animation: slideIn 0.5s ease-out;
}

.slide-up {
    animation: slideUp 0.5s ease-out;
}

.pulse {
    animation: pulse 2s ease-in-out infinite;
}

.spin {
    animation: spin 1s linear infinite;
}

.bounce {
    animation: bounce 1.4s ease-in-out infinite;
}

.shake {
    animation: shake 0.82s cubic-bezier(0.36, 0.07, 0.19, 0.97);
}

.zoom-in {
    animation: zoomIn 0.5s ease-out;
}

/* Interactive States */
.hover-lift:hover {
    transform: translateY(-2px);
    transition: transform 0.3s ease;
}

.hover-scale:hover {
    transform: scale(1.05);
    transition: transform 0.3s ease;
}

.hover-shadow:hover {
    box-shadow: var(--shadow-lg);
    transition: box-shadow 0.3s ease;
}

/* Loading States */
.loading-state {
    position: relative;
    overflow: hidden;
}

.loading-state::after {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.4),
        transparent
    );
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% {
        left: -100%;
    }
    100% {
        left: 100%;
    }
}

.skeleton {
    background: linear-gradient(
        90deg,
        var(--surface-soft) 25%,
        var(--border) 50%,
        var(--surface-soft) 75%
    );
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
}

/* Error States */
.error-shake {
    animation: shake 0.82s cubic-bezier(0.36, 0.07, 0.19, 0.97);
}

.error-border {
    border-color: var(--danger) !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
}
</style>
