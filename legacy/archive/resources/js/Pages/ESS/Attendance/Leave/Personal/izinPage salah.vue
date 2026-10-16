<template>
    <!-- Header -->
    <div
        class="row d-flex align-items-center justify-content-center mb-3 fade-in"
    >
        <div class="col-md-11">
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
                                        class="bi bi-person-badge d-flex justify-content-center align-items-center"
                                    ></i>
                                </div>
                                <div class="text-content">
                                    <h2 class="title text-white text-start">
                                        Halo, Ridho
                                    </h2>
                                    <p class="subtitle">IT</p>
                                </div>
                            </div>
                        </div>
                        <!-- Button & Dates -->
                        <div class="col-sm-4">
                            <div
                                class="header-actions d-flex gap-2 flex-nowrap flex-sm-column justify-content-center justify-content-md-start justify-content-md-center align-items-md-end"
                            >
                                <div
                                    class="date-range d-block d-md-none px-2 px-md-3 py-1 gap-2 gap-md-3"
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
                                            >24 Juni 2025
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

                                    <span class="text-header">Ajukan Izin</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Control Panel -->
    <div class="row d-flex justify-content-center mb-3 fade-in">
        <div class="col-md-11">
            <div class="control-panel">
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
                        <div class="search-results-count" v-if="searchQuery">
                            {{ filteredEmployees.length }} hasil
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

    <!-- Content -->

    <div class="row d-flex justify-content-center fade-in">
        <div class="col-11">
            <div class="content-container">
                <!-- Loading State -->
                <div v-if="loading" class="loading-container">
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
                <div v-else-if="viewMode === 'table'" class="table-container">
                    <div class="table-wrapper">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th class="header-table sticky-column">
                                        <i class="bi bi-people me-2"></i>
                                        No Transaksi
                                        <small>(1)</small>
                                    </th>
                                    <th class="header-table-normal">
                                        <span class="header-table-info">
                                            <span class="header-table-name">
                                                Tanggal
                                            </span>
                                            <span
                                                class="header-table-indicator"
                                            ></span>
                                        </span>
                                    </th>
                                    <th class="header-table-normal">
                                        <span class="header-table-info">
                                            <span class="header-table-name">
                                                Alasan
                                            </span>
                                            <span
                                                class="header-table-indicator"
                                            ></span>
                                        </span>
                                    </th>
                                    <th class="header-table-normal">
                                        <span class="header-table-info">
                                            <span class="header-table-name">
                                                Lampiran
                                            </span>
                                            <span
                                                class="header-table-indicator"
                                            ></span>
                                        </span>
                                    </th>
                                    <th class="header-table-normal">
                                        <span class="header-table-info">
                                            <span class="header-table-name">
                                                Status
                                            </span>
                                            <span
                                                class="header-table-indicator"
                                            ></span>
                                        </span>
                                    </th>
                                    <th class="header-table-normal">
                                        <span class="header-table-info">
                                            <span class="header-table-name">
                                                Action
                                            </span>
                                            <span
                                                class="header-table-indicator"
                                            ></span>
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="first-row">
                                    <td class="sticky-column first-cell">
                                        <div class="first-info px-2">
                                            <!-- <div class="avatar-cell">IS</div> -->
                                            <div class="first-details">
                                                <div class="title-cell">
                                                    IS07-25-0007
                                                </div>
                                                <div
                                                    class="first-desc flex justify-content-center align-items-center"
                                                >
                                                    <span
                                                        style="
                                                            font-size: 0.1rem;
                                                        "
                                                        class="me-2 spinner-grow-sm spinner-grow text-danger fs-6"
                                                    ></span
                                                    >Izin Sakit
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="cell-wrapper">
                                        <div class="cell-container">
                                            <div class="cell-content">
                                                <div class="cell-name">
                                                    20 Jun 2025
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="cell-wrapper">
                                        <div class="cell-container">
                                            <div class="cell-content">
                                                <div class="cell-name">
                                                    Alasan 1
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="cell-wrapper">
                                        <div class="cell-container">
                                            <div class="cell-content">
                                                <div class="cell-name">
                                                    File
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="cell-wrapper">
                                        <div class="cell-container">
                                            <div class="cell-content">
                                                <div class="cell-name">
                                                    In Progress
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="cell-wrapper">
                                        <div class="cell-container">
                                            <div class="cell-content">
                                                <div class="cell-name">
                                                    <div
                                                        @click.stop="
                                                            deleteAlertButton(
                                                                item.No_Transaksi
                                                            )
                                                        "
                                                        class="btn btn-sm btn-secondary shiningEffect text-white border-white bg-danger"
                                                    >
                                                        <i
                                                            class="bi bi-trash"
                                                        ></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination-container" v-if="totalPages > 1">
                        <button
                            class="page-btn"
                            @click="prevPage"
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
                            @click="nextPage"
                            :disabled="currentPage === totalPages"
                        >
                            <i
                                class="bi bi-chevron-right d-flex justify-content-center align-items-center"
                            ></i>
                        </button>
                    </div>
                </div>

                <!-- Card -->
                <div
                    v-else-if="viewMode == 'card'"
                    class="d-flex flex-column gap-3"
                >
                    <div
                        class="attendance-card cursor-pointer overflow-hidden shadow-sm"
                    >
                        <div class="">
                            <div class="card-header bg-primary text-white p-3">
                                <h5 class="mb-0 fw-semibold">25 Jun 2025</h5>
                            </div>

                            <div
                                class="d-flex flex-grow-1 align-items-center justify-content-center py-4"
                            >
                                <div class="text-center text-muted">
                                    <i
                                        class="fa-solid fa-bell-slash fs-2 mb-2"
                                    ></i>
                                    <p class="mb-0 fw-medium">
                                        Tidak ada shift hari ini
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Modal -->

    <div
        class="modal-overlay"
        :class="{ show: showAssignModal }"
        @click="closeAssignModal"
    >
        <div class="modal-container assign-modal" @click.stop>
            <!-- Header with progress indicator -->
            <div class="modal-header">
                <div class="header-title-section">
                    <h3>
                        <i
                            class="bi bi-calendar-plus me-2 d-flex justify-content-center align-items-center"
                        ></i>
                        Pengajuan Izin
                    </h3>
                    <p class="header-subtitle">
                        Ajukan izin pulang cepat, terlambat, atau sakit
                    </p>
                </div>
                <button
                    class="modal-close"
                    @click="closeAssignModal"
                    aria-label="Close"
                >
                    <i
                        class="bi bi-x-lg d-flex justify-content-center align-items-center"
                    ></i>
                </button>
            </div>

            <div class="modal-body">
                <div v-if="assignStep === 1" class="step-content">
                    <div class="form-section">
                        <!-- Jenis Izin -->
                        <h4 class="section-title">
                            <i
                                class="bi bi-calendar-event me-2 d-flex justify-content-center align-items-center"
                            ></i>
                            Pilih Jenis Izin
                        </h4>

                        <div class="form-group">
                            <div class="permission-type-options row g-2">
                                <div class="col-12 col-md-3">
                                    <div
                                        class="permission-type-card"
                                        :class="{
                                            selected:
                                                assignIzinType === 'izinFull',
                                        }"
                                        @click="handleAssignIzin('izinFull')"
                                    >
                                        <div class="permission-type-icon">
                                            <i class="bi bi-playstation"></i>
                                        </div>
                                        <div class="permission-type-content">
                                            <h6>Tidak Masuk</h6>
                                            <p class="text-muted small">
                                                Tidak masuk full hari
                                            </p>
                                        </div>
                                        <div class="permission-type-check">
                                            <i
                                                class="bi bi-check-circle-fill"
                                                v-if="
                                                    assignIzinType ===
                                                    'izinFull'
                                                "
                                            ></i>
                                            <i class="bi bi-circle" v-else></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-3">
                                    <div
                                        class="permission-type-card"
                                        :class="{
                                            selected:
                                                assignIzinType ===
                                                'pulangCepat',
                                        }"
                                        @click="handleAssignIzin('pulangCepat')"
                                    >
                                        <div class="permission-type-icon">
                                            <i class="bi bi-house-door"></i>
                                        </div>
                                        <div class="permission-type-content">
                                            <h6>Pulang Cepat</h6>
                                            <p class="text-muted small">
                                                Keluar sebelum waktu pulang
                                            </p>
                                        </div>
                                        <div class="permission-type-check">
                                            <i
                                                class="bi bi-check-circle-fill"
                                                v-if="
                                                    assignIzinType ===
                                                    'pulangCepat'
                                                "
                                            ></i>
                                            <i class="bi bi-circle" v-else></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-3">
                                    <div
                                        class="permission-type-card"
                                        :class="{
                                            selected:
                                                assignIzinType ===
                                                'izinTerlambat',
                                        }"
                                        @click="
                                            handleAssignIzin('izinTerlambat')
                                        "
                                    >
                                        <div class="permission-type-icon">
                                            <i class="bi bi-clock"></i>
                                        </div>
                                        <div class="permission-type-content">
                                            <h6>Terlambat</h6>
                                            <p class="text-muted small">
                                                Datang setelah waktu masuk
                                            </p>
                                        </div>
                                        <div class="permission-type-check">
                                            <i
                                                class="bi bi-check-circle-fill"
                                                v-if="
                                                    assignIzinType ===
                                                    'izinTerlambat'
                                                "
                                            ></i>
                                            <i class="bi bi-circle" v-else></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-3">
                                    <div
                                        class="permission-type-card"
                                        :class="{
                                            selected:
                                                assignIzinType === 'izinSakit',
                                        }"
                                        @click="handleAssignIzin('izinSakit')"
                                    >
                                        <div class="permission-type-icon">
                                            <i class="bi bi-heart-pulse"></i>
                                        </div>
                                        <div class="permission-type-content">
                                            <h6>Sakit</h6>
                                            <p class="text-muted small">
                                                Tidak masuk karena sakit
                                            </p>
                                        </div>
                                        <div class="permission-type-check">
                                            <i
                                                class="bi bi-check-circle-fill"
                                                v-if="
                                                    assignIzinType ===
                                                    'izinSakit'
                                                "
                                            ></i>
                                            <i class="bi bi-circle" v-else></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <h4 class="section-title">
                            <i
                                class="bi bi-calendar-event me-2 d-flex justify-content-center align-items-center"
                            ></i>
                            Pilih Tanggal
                        </h4>

                        <div
                            v-if="
                                assignIzinType == 'izinFull' ||
                                assignIzinType == 'izinSakit'
                            "
                            @click="openDialog"
                            class="form-group d-flex cursor-pointer justify-content-start align-items-center"
                        >
                            <div class="me-2">
                                <i
                                    class="bi bi-calendar-week d-flex justify-content-center align-items-center"
                                ></i>
                            </div>
                            <div v-if="!AssignDateRange" class="w-100">
                                Tekan untuk pilih tanggal
                            </div>
                            <div v-else class="w-100">
                                {{
                                    AssignDateRange[0] == AssignDateRange[1]
                                        ? formatDateString(AssignDateRange[0])
                                        : formatDateString(AssignDateRange[0]) +
                                          " s.d " +
                                          formatDateString(AssignDateRange[1])
                                }}
                            </div>
                        </div>
                        <div
                            v-else
                            @click="openDialogSingle"
                            class="form-group d-flex cursor-pointer justify-content-start align-items-center"
                        >
                            <div class="me-2">
                                <i
                                    class="bi bi-calendar-week d-flex justify-content-center align-items-center"
                                ></i>
                            </div>
                            <div v-if="!AssignDateRange" class="w-100">
                                Tekan untuk pilih tanggal
                            </div>
                            <div v-else class="w-100">
                                {{ formatDateString(AssignDateRange[0]) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="assignStep === 2" class="step-content">
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="bi bi-gear me-2"></i>
                            Detail Izin
                        </h4>

                        <!-- Time Selection (for early leave/late arrival) -->
                        <div
                            class="form-group mb-4"
                            v-if="
                                assignIzinType === 'pulangCepat' ||
                                assignIzinType === 'izinTerlambat'
                            "
                        >
                            <label class="form-label">
                                {{
                                    assignIzinType === "pulangCepat"
                                        ? "Waktu Pulang"
                                        : "Waktu Datang"
                                }}
                            </label>
                            <div class="input-group px-3 mb-3">
                                <el-slider
                                    @change="console.log(AssignTime)"
                                    v-model="AssignTime"
                                    :min="8.5"
                                    :max="16.5"
                                    :step="0.5"
                                    :marks="timeMarks"
                                    :format-tooltip="formatSliderTooltip"
                                    show-stops
                                />
                            </div>
                            <small class="text-muted">
                                {{
                                    assignIzinType === "pulangCepat"
                                        ? "Pilih waktu Anda akan pulang"
                                        : "Pilih waktu Anda akan tiba"
                                }}
                            </small>
                        </div>

                        <!-- Description -->
                        <div class="form-group mb-3">
                            <label class="form-label">Alasan/Keterangan</label>
                            <textarea
                                class="form-control"
                                rows="3"
                                v-model="permissionReason"
                                placeholder="Jelaskan alasan permohonan izin Anda"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <div v-if="assignStep === 3" class="step-content">
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="bi bi-paperclip me-2"></i>
                            Lampiran
                        </h4>

                        <!-- File Upload for Sick Leave -->
                        <div
                            class="form-group"
                            v-if="assignIzinType === 'izinSakit'"
                        >
                            <label class="form-label"
                                >Surat Keterangan Dokter (Opsional)</label
                            >
                            <div class="file-upload-wrapper">
                                <input
                                    type="file"
                                    class="form-control"
                                    ref="doctorLetterInput"
                                    @change="handleFileUpload"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    style="display: none"
                                />
                                <div
                                    v-if="!doctorLetterFile"
                                    class="file-upload-area"
                                    @click="$refs.doctorLetterInput.click()"
                                >
                                    <label
                                        class="file-upload-label d-flex align-items-center justify-content-center"
                                    >
                                        <i
                                            class="bi bi-upload me-2 d-flex align-items-center justify-content-center"
                                        ></i>
                                        <span>Unggah File</span>
                                    </label>
                                </div>
                                <div v-else class="file-display-area">
                                    <span class="file-name">{{
                                        doctorLetterFile.name
                                    }}</span>
                                    <button
                                        @click="removeFile"
                                        class="btn-remove-file"
                                    >
                                        <i class="bi bi-x-circle-fill"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted"
                                >Format: PDF, JPG, PNG (maks. 5MB)</small
                            >
                        </div>
                        <div v-else class="text-muted text-center py-4">
                            Tidak ada lampiran yang diperlukan untuk jenis izin
                            ini.
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
                        v-if="assignStep < 4"
                        class="btn-modern btn-primary"
                        @click="nextAssignStep"
                        :disabled="!canProceedToNextStep"
                    >
                        Lanjut
                        <i
                            class="bi bi-arrow-right ms-2 d-flex justify-content-center align-items-center"
                        ></i>
                    </button>

                    <button
                        v-if="assignStep === 3"
                        X
                        class="btn-modern btn-success"
                        @click="storeAlertButton"
                        :disabled="!assignSelectedShift"
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

    <!-- Calendar Modal (Range Selection) -->
    <el-dialog
        v-model="dialogVisible"
        title="Pilih Tanggal Mulai dan Selesai"
        width="480px"
        align-center
    >
        <el-calendar v-model="calendarDate" :disabled-date="disablePastDates">
            <template #date-cell="{ data }">
                <div
                    class="cell-date-wrapper"
                    :class="getCellClass(data)"
                    @click="handleDateClick(data)"
                >
                    <div
                        class="date-cell"
                        :class="{
                            'is-disabled-custom': disablePastDates(data.date),
                        }"
                    >
                        {{ data.day.split("-")[2] }}
                    </div>
                </div>
            </template>
        </el-calendar>

        <template #footer>
            <div class="dialog-footer gap-3 d-flex justify-content-end">
                <el-button
                    @click="handleCancel"
                    class="btn-secondary p-1 rounded-3"
                    >Batal</el-button
                >
                <el-button
                    class="shiningEffect btn-success py-0 rounded-3 px-1"
                    :disabled="!tempEndDate"
                    @click="confirmDate('range')"
                >
                    Pilih Tanggal
                </el-button>
            </div>
        </template>
    </el-dialog>

    <!-- Calendar Modal (Single Selection) -->
    <el-dialog
        v-model="dialogVisibleSingle"
        title="Pilih Tanggal"
        width="480px"
        align-center
    >
        <el-calendar v-model="calendarDate" :disabled-date="disablePastDates">
            <template #date-cell="{ data }">
                <div
                    class="cell-date-wrapper"
                    :class="getCellClass(data)"
                    @click="handleDateClickSingle(data)"
                >
                    <div
                        class="date-cell"
                        :class="{
                            'is-disabled-custom': disablePastDates(data.date),
                        }"
                    >
                        {{ data.day.split("-")[2] }}
                    </div>
                </div>
            </template>
        </el-calendar>

        <template #footer>
            <div class="dialog-footer gap-3 d-flex justify-content-end">
                <el-button
                    @click="handleCancel"
                    class="btn-secondary p-1 rounded-3"
                    >Batal</el-button
                >
                <el-button
                    class="shiningEffect btn-success py-0 rounded-3 px-1"
                    :disabled="!tempEndDate"
                    @click="confirmDate('single')"
                >
                    Pilih Tanggal
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script>
import { ElMessage, ElSlider, ElCalendar, ElDialog } from "element-plus";
import "element-plus/dist/index.css";
export default {
    components: {
        ElSlider,
        ElCalendar,
        ElMessage,
        ElDialog,
    },
    props: {},
    data() {
        return {
            showAssignModal: false,
            viewMode: "table",
            loading: false,
            assignStep: 1,
            assignIzinType: null,
            showCalendarRange: null,
            calendarDate: new Date(),
            AssignDateRange: null,
            AssignTime: null,
            timeMarks: {
                8: "08:30",
                9: "09:00",
                10: "10:00",
                11: "11:00",
                12: "12:00",
                13: "13:00",
                14: "14:00",
                15: "15:00",
                16: "16:00",
            },

            dialogVisible: false,
            dialogVisibleSingle: false,
            selectedDate: [], // Ini akan menyimpan [startDate, endDate]
            tempStartDate: null,
            tempEndDate: null,
            // Dummy data for table view (replace with actual data)
            searchQuery: "",
            filteredEmployees: [], // This would be populated by a computed property or method
            totalPages: 1, // Dummy total pages
            currentPage: 1, // Dummy current page
            permissionReason: "", // For the reason/description input
            assignSelectedShift: true, // Dummy value for button disabled state
            doctorLetterFile: null, // New: To store the selected file object
        };
    },
    methods: {
        formatSliderTooltip(value) {
            // Dapatkan bagian jam (angka bulat)
            const hours = Math.floor(value);
            // Dapatkan bagian menit (0.5 menjadi 30)
            const minutes = (value % 1) * 60; // Mengubah 0.5 menjadi 30 menit

            // Pastikan jam dan menit selalu 2 digit dengan padding nol
            const formattedHours = String(hours).padStart(2, "0");
            const formattedMinutes = String(minutes).padStart(2, "0");

            return `${formattedHours}:${formattedMinutes}`;
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
        formatDateString(dateString) {
            const [year, month, day] = dateString.split("-").map(Number);
            const date = new Date(Date.UTC(year, month - 1, day));
            const options = {
                year: "numeric",
                month: "long",
                day: "numeric",
            };
            return date.toLocaleDateString("id-ID", options);
        },
        // Corrected disablePastDates function: disables all dates before today
        disablePastDates(date) {
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Normalize today to the start of the day

            const dateToCheck = new Date(date);
            dateToCheck.setHours(0, 0, 0, 0); // Normalize the date to check to the start of the day

            // Disable if the date to check is strictly earlier than today
            return dateToCheck.getTime() < today.getTime();
        },

        openDialog() {
            // For range selection
            if (this.AssignDateRange && this.AssignDateRange.length === 2) {
                const [startStr, endStr] = this.AssignDateRange;
                this.tempStartDate = new Date(startStr + "T00:00:00");
                this.tempEndDate = new Date(endStr + "T00:00:00");
                this.calendarDate = new Date(startStr + "T00:00:00");
            } else {
                this.tempStartDate = null;
                this.tempEndDate = null;
                this.calendarDate = new Date(); // Set to current month/year
            }
            this.dialogVisible = true;
        },

        openDialogSingle() {
            // For single date selection
            if (this.AssignDateRange && this.AssignDateRange.length === 2) {
                const [startStr] = this.AssignDateRange; // Only need the first date for single selection
                this.tempStartDate = new Date(startStr + "T00:00:00");
                this.tempEndDate = new Date(startStr + "T00:00:00"); // For single, start and end are the same
                this.calendarDate = new Date(startStr + "T00:00:00");
            } else {
                this.tempStartDate = null;
                this.tempEndDate = null;
                this.calendarDate = new Date(); // Set to current month/year
            }
            this.dialogVisibleSingle = true;
        },
        handleAssignIzin(type) {
            this.AssignDateRange = null; // Reset date range when changing izin type
            this.assignIzinType = type;
            this.tempStartDate = null; // Reset temp dates too
            this.tempEndDate = null;
            this.doctorLetterFile = null; // New: Reset file when izin type changes
        },

        handleCancel() {
            this.dialogVisible = false;
            this.dialogVisibleSingle = false;
            // Optionally reset temp dates if user cancels
            this.tempStartDate = null;
            this.tempEndDate = null;
            // Do NOT reset AssignDateRange here, as it holds the previously confirmed value
        },
        handleDateClick(data) {
            const clickedDate = data.date;
            // Ensure disabled dates cannot be selected
            if (this.disablePastDates(clickedDate)) {
                ElMessage({
                    message: "Tanggal ini tidak bisa dipilih.",
                    type: "warning",
                });
                return;
            }

            if (this.tempStartDate && this.tempEndDate) {
                // If both are set, start a new range
                this.tempStartDate = clickedDate;
                this.tempEndDate = null;
            } else if (!this.tempStartDate) {
                // If no start date, set this as start
                this.tempStartDate = clickedDate;
            } else {
                // If start date is set, set this as end
                if (clickedDate.getTime() < this.tempStartDate.getTime()) {
                    // If clicked date is before start, swap them
                    this.tempEndDate = this.tempStartDate;
                    this.tempStartDate = clickedDate;
                } else {
                    this.tempEndDate = clickedDate;
                }
            }
        },
        handleDateClickSingle(data) {
            const clickedDate = data.date;
            // Ensure disabled dates cannot be selected
            if (this.disablePastDates(clickedDate)) {
                ElMessage({
                    message: "Tanggal ini tidak bisa dipilih.",
                    type: "warning",
                });
                return;
            }
            // For single selection, both start and end are the same date
            this.tempStartDate = clickedDate;
            this.tempEndDate = clickedDate;
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

            if (start && currentDate === start) classes["is-start"] = true;
            if (end && currentDate === end) classes["is-end"] = true;

            if (start && end && currentDate > start && currentDate < end) {
                classes["is-in-range"] = true;
            }
            // Add class for disabled dates
            if (this.disablePastDates(data.date)) {
                classes["is-disabled-custom"] = true;
            }

            return classes;
        },

        confirmDate(jenis) {
            this.loading = true;
            if (jenis == "single") {
                if (!this.tempEndDate) {
                    ElMessage({
                        message: "Silakan pilih tanggal izin.",
                        type: "warning",
                    });
                    this.loading = false;
                    return;
                }

                this.AssignDateRange = [
                    this.formatDateToString(this.tempEndDate),
                    this.formatDateToString(this.tempEndDate),
                ];
            } else {
                if (!this.tempStartDate || !this.tempEndDate) {
                    ElMessage({
                        message: "Silakan pilih tanggal mulai dan selesai.",
                        type: "warning",
                    });
                    this.loading = false;
                    return;
                }
                this.AssignDateRange = [
                    this.formatDateToString(this.tempStartDate),
                    this.formatDateToString(this.tempEndDate),
                ];
            }

            this.dialogVisible = false;
            this.dialogVisibleSingle = false;
            this.loading = false;
            console.log(this.AssignDateRange);
        },
        canProceedToNextStep() {
            if (this.assignStep === 1) {
                // For step 1: Jenis Izin and Tanggal must be selected
                return (
                    this.assignIzinType !== null &&
                    this.AssignDateRange !== null &&
                    this.AssignDateRange.length > 0
                );
            }
            if (this.assignStep === 2) {
                // For step 2: Time must be selected if izinType is 'pulangCepat' or 'izinTerlambat'
                if (
                    this.assignIzinType === "pulangCepat" ||
                    this.assignIzinType === "izinTerlambat"
                ) {
                    return this.AssignTime !== null;
                }
                // For 'izinFull' or 'izinSakit', no specific new conditions for step 2 (reason is optional)
                return true;
            }
            if (this.assignStep === 3) {
                // For step 3: File upload is optional, so always allow proceeding
                return true;
            }
            return true; // Default for other steps or if no specific validation
        },
        nextAssignStep() {
            if (this.canProceedToNextStep()) {
                this.assignStep++;
            } else {
                ElMessage({
                    message: "Harap lengkapi pilihan Anda sebelum melanjutkan.",
                    type: "warning",
                });
            }
        },

        prevAssignStep() {
            if (this.assignStep > 1) {
                this.assignStep--;
            }
        },
        // New: Handle file upload
        handleFileUpload(event) {
            const file = event.target.files[0];
            if (file) {
                const allowedTypes = [
                    "application/pdf",
                    "image/jpeg",
                    "image/jpg",
                    "image/png",
                ];
                const maxSize = 5 * 1024 * 1024; // 5MB

                if (!allowedTypes.includes(file.type)) {
                    ElMessage({
                        message:
                            "Format file tidak didukung. Gunakan PDF, JPG, atau PNG.",
                        type: "error",
                    });
                    this.doctorLetterFile = null;
                    event.target.value = ""; // Clear the input
                    return;
                }

                if (file.size > maxSize) {
                    ElMessage({
                        message: "Ukuran file terlalu besar. Maksimal 5MB.",
                        type: "error",
                    });
                    this.doctorLetterFile = null;
                    event.target.value = ""; // Clear the input
                    return;
                }

                this.doctorLetterFile = file;
            } else {
                this.doctorLetterFile = null;
            }
        },
        // New: Remove selected file
        removeFile() {
            this.doctorLetterFile = null;
            // Also clear the file input element's value to allow re-uploading the same file
            if (this.$refs.doctorLetterInput) {
                this.$refs.doctorLetterInput.value = "";
            }
        },
        // Dummy methods for table pagination and alert deletion
        prevPage() {
            if (this.currentPage > 1) this.currentPage--;
        },
        nextPage() {
            if (this.currentPage < this.totalPages) this.currentPage++;
        },
        deleteAlertButton(transactionId) {
            console.log("Delete transaction:", transactionId);
            ElMessage({
                message: `Transaksi ${transactionId} akan dihapus.`,
                type: "info",
            });
            // Implement actual deletion logic here
        },
        storeAlertButton() {
            console.log("Storing alert/permission...");
            // You can access this.doctorLetterFile here if it's needed for submission
            ElMessage({
                message: "Pengajuan izin berhasil disimpan!",
                type: "success",
            });
            this.closeAssignModal();
            // Implement actual storage logic here
        },
        closeAssignModal() {
            this.showAssignModal = false;
            this.assignStep = 1; // Reset step when closing modal
            this.assignIzinType = null; // Reset izin type
            this.AssignDateRange = null; // Reset assigned date range
            this.AssignTime = null; // Reset assigned time
            this.permissionReason = ""; // Reset reason
            this.tempStartDate = null; // Reset temp dates
            this.tempEndDate = null;
            this.doctorLetterFile = null; // New: Reset file when modal closes
        },
    },
};
</script>
<style>
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
/* Date Range */
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

/* Style untuk rentang tanggal */
.cell-date-wrapper {
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

/* Mengatur sudut untuk awal dan akhir rentang */
.is-start::before {
    left: 50%;
}
.is-end::before {
    right: 50%;
}

/* Custom style for disabled dates */
.is-disabled-custom {
    color: #c0c4cc !important; /* Lighter text color for disabled dates */
    cursor: not-allowed !important; /* Indicate not clickable */
    background-color: #f5f7fa !important; /* Slightly different background */
    pointer-events: none; /* Disable click events */
}
.is-disabled-custom .date-cell {
    background-color: transparent !important; /* Ensure background is clear */
}
.is-disabled-custom:hover .date-cell {
    background-color: transparent !important; /* No hover effect on disabled dates */
}

/* New: File Upload Display Styles */
.file-upload-area {
    cursor: pointer;
    border: 1px dashed var(--border);
    border-radius: 0.375rem;
    padding: 1rem;
    min-height: 10rem; /* Keep consistent height */
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    background-color: var(--surface-soft);
}

.file-upload-area:hover {
    background-color: #e9ecef;
    border-color: #adb5bd;
}

.file-upload-label {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column; /* Align icon and text vertically */
    gap: 0.5rem;
    color: var(--text-muted);
    font-weight: 500;
    cursor: pointer; /* Ensure cursor is pointer */
    width: 100%; /* Take full width of parent */
    height: 100%; /* Take full height of parent */
}

.file-upload-label i {
    font-size: 2rem; /* Larger icon */
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
    min-height: 10rem; /* Keep consistent height */
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
    padding: 0.25rem;
    border-radius: 50%;
    transition: background-color 0.2s ease;
}

.btn-remove-file:hover {
    background-color: rgba(239, 68, 68, 0.1);
}
</style>
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
    --gradient-success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --gradient-info: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --gradient-dark: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
}

/* ReaL */

.header-table {
    position: relative;
    border-right: 1px solid var(--border);
}

.header-table-normal {
    min-width: 100px;
    text-align: center;
}

.header-table-info {
    display: flex;
    gap: 0.25rem;
    justify-content: center;
}
.header-table-indicator {
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 30px;
    height: 3px;
    background: white;
    border-radius: 2px;
}

.header-table-name {
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.8rem;
}

.first-row {
    transition: all 0.3s ease;
}

.first-row:hover {
    background: var(--surface-soft);
}

.first-cell {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    border-right: 1px solid var(--border);
}
.title-cell {
    font-weight: 600;
    color: var(--text);
}

.avatar-cell {
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

.avatar-cell.large {
    width: 60px;
    height: 60px;
    font-size: 1.1rem;
}

.first-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.first-name {
    font-weight: 600;
    color: var(--text);
}

.first-desc {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.cell-wrapper {
    padding: 0.5rem;
    border-bottom: 1px solid var(--border);
    text-align: center;
    cursor: pointer;
}

.cell-container {
    min-height: 5rem;
    max-height: 5rem;
    min-width: 5rem;
    padding: 0.75rem;
    border-radius: 12px;
    display: flex;
    justify-content: center;
    align-items: center;
    transition: all 0.3s ease;
    overflow: hidden;
}

.cell-container.interactive:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.cell-content {
    display: flex;
    align-items: center;
    text-align: start;
    height: 100%;
    justify-content: start;
    margin-bottom: 0.25rem;
}

.cell-name {
    font-weight: 400;
    font-size: 1rem;
}

.first-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.modern-table {
    border-spacing: 0;
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
    max-width: 8rem;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
}

/* Control Panel */
.control-panel {
    background: var(--surface);
    border-radius: 20px;
    padding: 0.5rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    margin-bottom: 0rem;
}
.skeleton {
    background-color: #e0e0e0;
    position: relative;
    overflow: hidden;
}

.skeleton::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.5),
        transparent
    );
    animation: shimmer 1.5s infinite;
}

.skeleton-text {
    border-radius: 4px;
}

@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

.attendance-card {
    border: 1px solid var(--border);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.attendance-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.card-header {
    background: var(--gradient-primary);
}

.attendance-photo-container {
    width: 100px;
    height: 100px;
    flex-shrink: 0;
}

.attendance-photo {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid var(--border);
}

.attendance-photo-placeholder {
    width: 100%;
    height: 100%;
    border-radius: 8px;
    border: 1px dashed var(--border);
    color: var(--text-muted);
}

.time-display {
    background-color: rgba(99, 102, 241, 0.1);
    color: var(--primary-dark);
}

.bg-primary-light {
    background-color: var(--primary-light);
}

.bg-primary-light-10 {
    background-color: rgba(165, 180, 252, 0.1);
}

.text-primary {
    color: var(--primary);
}

.check-section {
    border-bottom: 1px solid var(--border);
}

@media (min-width: 768px) {
    .check-section {
        border-bottom: none;
    }
    .border-end-md {
        border-right: 1px solid var(--border);
    }
}

.highlighted {
    box-shadow: 0 0 15px 5px rgba(99, 102, 241, 0.2) !important;
    border: 2px solid var(--primary-light) !important;
    transform: scale(1.01);
    transition: all 0.3s ease;
}

.attendance-card {
    transition: all 0.2s ease;
    cursor: pointer;
}

.attendance-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.cursor-pointer {
    cursor: pointer;
}

.nav-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2rem;
}

/* Skeleton Loading Styles */
.skeleton {
    position: relative;
    overflow: hidden;
    background-color: #e2e8f0;
}

.skeleton::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.3) 50%,
        rgba(255, 255, 255, 0) 100%
    );
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

.skeleton-text {
    height: 1rem;
    background-color: #e2e8f0;
    border-radius: 0.25rem;
    margin-bottom: 0.5rem;
    position: relative;
    overflow: hidden;
}

.title-skeleton {
    height: 2rem;
    width: 60%;
    margin-bottom: 1rem;
}

.subtitle-skeleton {
    height: 1.25rem;
    width: 40%;
}

.skeleton-button {
    height: 2.5rem;
    width: 100%;
    background-color: #e2e8f0;
    border-radius: 0.75rem;
    position: relative;
    overflow: hidden;
}

.big-date {
    height: 5rem;
    width: 5rem;
}

.medium-text {
    height: 2rem;
    width: 4rem;
}

.small-text {
    height: 1.25rem;
    width: 6rem;
    margin-top: 0.5rem;
}

.time-skeleton {
    height: 3rem;
    width: 4rem;
}

.dots-skeleton {
    height: 1.5rem;
    width: 1.5rem;
}

/* Week Navigation Skeleton */
.week-navigation-skeleton {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-bottom: 0.6rem;
    margin-top: 1rem;
}

.skeleton-nav-button {
    width: 40px;
    height: 40px;
    background-color: #e2e8f0;
    border-radius: 0.5rem;
}

.week-days-skeleton {
    display: flex;
    flex: 1;
    gap: 0.5rem;
    overflow-x: auto;
}

.day-card-skeleton {
    min-width: 80px;
    flex-grow: 1;
    background: #f1f5f9;
    border-radius: 0.75rem;
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.date-skeleton {
    height: 1rem;
    width: 2rem;
    margin-bottom: 0.5rem;
}

.day-content-skeleton {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.day-name-skeleton {
    height: 1rem;
    width: 2.5rem;
}

.day-schedule-skeleton {
    width: 100%;
    background: white;
    padding: 0.25rem 0.5rem;
    border-radius: 0.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.time-skeleton-small {
    height: 0.75rem;
    width: 2rem;
}

/* History Card Skeleton */
.skeleton-history-card {
    min-height: 8rem;
    background: #f1f5f9;
    border-radius: 0.75rem;
    position: relative;
    overflow: hidden;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .week-days-skeleton {
        gap: 0.25rem;
    }

    .day-card-skeleton {
        min-width: 60px;
        padding: 0.5rem;
    }

    .date-skeleton {
        width: 1.5rem;
    }

    .day-name-skeleton {
        width: 2rem;
    }
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
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
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

/* Global Styles */
* {
    box-sizing: border-box;
}

.tanggalBar {
    min-height: 1rem;
    min-width: 7rem;
}

.gradientPrimary {
    background: var(--gradient-primary) !important;
}
.borderGradientPrimary {
    border: 3px dashed var(--primary);
}

.tanggalItem {
    background: linear-gradient(
        135deg,
        var(--primary-light) 0%,
        var(--primary-dark) 100%
    );
    box-shadow: var(--shadow);
}

.lightGradientPrimary {
    background: linear-gradient(
        135deg,
        var(--primary-light) 0%,
        var(--primary-dark) 100%
    );
}

.MarkHari {
    flex-grow: 0.3;
}

body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
        sans-serif;
    color: var(--text);
    line-height: 1.6;
}

/* Modern Header (keeping original styles) */
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

.btn-close {
    background: none;
    border: none;
    color: var(--text-muted);
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.btn-close:hover {
    background: var(--surface-soft);
    color: var(--text);
}

/* Content Container */
.content-container {
    background: var(--surface);
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid var(--border);
}

/* Attendance Cards */
.attendance-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 15px;
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.attendance-card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-2px);
}

.card-header {
    padding: 1.2rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.employee-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.employee-avatar {
    width: 40px;
    height: 40px;
    background: var(--primary);
    color: white;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
}

.employee-details h6 {
    margin: 0;
    font-weight: 600;
    color: var(--text);
}

.employee-details small {
    color: var(--text-muted);
}

.status-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-hadir {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-terlambat {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.status-izin {
    background: rgba(6, 182, 212, 0.1);
    color: var(--info);
}

.status-sakit {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.card-body {
    padding: 1.2rem;
}

.attendance-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-muted);
}

.info-item i {
    width: 16px;
    font-size: 0.8rem;
}

.attendance-photo {
    border-radius: 12px;
    overflow: hidden;
    margin-top: 0.75rem;
}

.attendance-photo img {
    width: 100%;
    height: 120px;
    object-fit: cover;
}

/* Modal Styles */
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
    z-index: 1000;
    padding: 1rem;
}

.modal-container {
    background: var(--surface);
    border-radius: 20px;
    box-shadow: var(--shadow-lg);
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 1rem;
    justify-content: end;
}

/* Camera Styles */
.camera-section {
    background: var(--surface-soft);
    border-radius: 16px;
    padding: 1.5rem;
}

.camera-container {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    background: var(--dark);
    aspect-ratio: 4/3;
    display: flex;
    align-items: center;
    justify-content: center;
}

.camera-preview {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.photo-preview {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.photo-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.camera-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
}

.camera-placeholder i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.camera-placeholder p {
    margin: 0;
    opacity: 0.7;
}

.camera-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    pointer-events: none;
}

.camera-frame {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 80%;
    height: 80%;
    border: 2px solid rgba(255, 255, 255, 0.5);
    border-radius: 16px;
    background: linear-gradient(
        45deg,
        transparent 40%,
        rgba(255, 255, 255, 0.1) 50%,
        transparent 60%
    );
}

.camera-controls {
    display: flex;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.capture-btn {
    position: relative;
    overflow: hidden;
}

.capture-btn::after {
    content: "";
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: width 0.3s ease, height 0.3s ease;
}

.capture-btn:active::after {
    width: 100%;
    height: 100%;
}

/* Week Navigation */
.week-navigation {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.nav-button {
    background: var(--surface);
    border: 1px solid var(--border);
    width: 40px;
    height: 40px;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.nav-button:hover {
    background: var(--surface-soft);
}

.week-days {
    display: flex;
    flex: 1;
    gap: 0.5rem;
    padding-bottom: 0.6rem;
    margin-top: 1rem;
}

.day-card {
    min-width: 80px;
    flex-grow: 1;
    background: var(--surface);
    border-radius: 0.75rem;
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.day-card.active-day {
    background: linear-gradient(
        135deg,
        var(--primary-light) 0%,
        var(--primary-dark) 100%
    );
    color: white;
}

.day-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
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
    max-height: 18rem;
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
    transform: translateY(-1px);
}

.modern-input::placeholder,
.modern-textarea::placeholder {
    color: var(--text-muted);
    opacity: 0.7;
}

.modern-textarea {
    resize: vertical;
    min-height: 100px;
    font-family: inherit;
}

.modern-select {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.75rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
}

/* Modal Overlay */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1050;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-overlay.show {
    opacity: 1;
    visibility: visible;
}

.modal-container {
    background: white;
    border-radius: 12px;
    width: 95%;
    max-width: 600px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    transform: translateY(20px);
    transition: transform 0.3s ease;
}

.modal-overlay.show .modal-container {
    transform: translateY(0);
}

/* Header */
.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #eee;
    position: relative;
}

.header-progress {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
}

.progress-step {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background-color: #e9ecef;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
}

.progress-step.active {
    background-color: #0d6efd;
    color: white;
}

.progress-step.completed {
    background-color: #198754;
    color: white;
}

.progress-line {
    flex: 1;
    height: 2px;
    background-color: #e9ecef;
    max-width: 60px;
}

.progress-line.active {
    background-color: #0d6efd;
}

.header-title-section h3 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
}

.header-subtitle {
    color: #6c757d;
    font-size: 0.875rem;
    margin-bottom: 0;
}

.modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #6c757d;
    padding: 0.25rem;
    line-height: 1;
}

/* Body */
.modal-body {
    padding: 1.5rem;
    overflow-y: auto;
    flex: 1;
}

.section-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
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
    border-color: #0d6efd;
    background-color: rgba(13, 110, 253, 0.05);
}

.permission-type-icon {
    font-size: 1.5rem;
    color: #0d6efd;
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

/* Form Elements */
.form-label {
    font-weight: 500;
    margin-bottom: 0.5rem;
    display: block;
}

.input-group-text {
    background-color: #f8f9fa;
}

/* File Upload */
.file-upload-wrapper {
    position: relative;
}
.is-today {
    font-weight: bold;
    color: #409eff; /* Warna biru Element Plus */
}
.is-start-date,
.is-end-date {
    background-color: #409eff;
    color: white;
    border-radius: 50%;
}
.file-upload-wrapper input[type="file"] {
    position: absolute;
    left: 0;
    top: 0;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.file-upload-label {
    display: block;
    padding: 0.5rem 1rem;
    min-height: 10rem;
    background-color: #f8f9fa;
    border: 1px dashed #dee2e6;
    border-radius: 0.375rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.file-upload-label:hover {
    background-color: #e9ecef;
    border-color: #adb5bd;
}

/* Footer */
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

/* Responsive Adjustments */
@media (max-width: 576px) {
    .modal-container {
        width: 100%;
        height: 100%;
        max-height: none;
        border-radius: 0;
    }

    .permission-type-card {
        padding: 0.75rem;
    }

    .permission-type-icon {
        font-size: 1.25rem;
        margin-bottom: 0.5rem;
    }
}

/* Toast Notification */
.toast-container {
    position: fixed;
    top: 2rem;
    right: 2rem;
    z-index: 1100;
}

.toast {
    display: flex;
    align-items: center;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    box-shadow: var(--shadow-lg);
    font-weight: 500;
    min-width: 300px;
}

.toast.success {
    background: var(--success);
    color: white;
}

.toast.success i {
    font-size: 1.2rem;
}

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

    .cards-grid {
        grid-template-columns: 1fr;
    }

    .date-separator {
        display: none;
    }
}

@media (max-width: 768px) {
    .week-days {
        overflow-x: auto;
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
    .header-content {
        padding: 1rem;
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
    .form-section {
        padding: 0.2rem;
    }

    .form-group {
        padding: 0.8rem;
    }
    .form-group .col-4 {
        padding: 0.4rem;
    }

    .shift-type-card {
        padding: 0.8rem 0.5rem;
        gap: 0.75rem;
    }
    .shift-type-card i {
        display: none;
    }
    .shift-type-card h6 {
        font-size: 0.7rem;
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

/* Responsive Design */
@media (max-width: 768px) {
    .modal-container {
        margin: 0.5rem;
        max-height: 83vh;
    }

    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 1rem;
    }

    .camera-section {
        padding: 1rem;
    }

    .camera-controls {
        flex-direction: column;
    }

    .camera-controls .btn-modern {
        width: 100%;
        justify-content: center;
    }

    .modal-footer .btn-modern {
        width: 100%;
        justify-content: center;
    }

    .header-content {
        padding: 1rem;
    }

    .title {
        font-size: 1.5rem;
    }

    .subtitle {
        font-size: 0.9rem;
    }

    .icon-container {
        width: 60px;
        height: 60px;
    }

    .icon-container i {
        font-size: 1.8rem;
    }

    .attendance-card {
        margin-bottom: 1rem;
    }

    .card-header {
        padding: 1rem;
    }

    .card-body {
        padding: 1rem;
    }

    .toast-container {
        top: 1rem;
        right: 1rem;
        left: 1rem;
    }

    .toast {
        min-width: auto;
        width: 100%;
    }
}

@media (max-width: 576px) {
    .header-actions {
        flex-direction: column;
        width: 100%;
    }

    .date-range {
        width: 100%;
        justify-content: center;
    }

    .btn-modern {
        width: 100%;
        justify-content: center;
    }

    .camera-frame {
        width: 90%;
        height: 90%;
    }

    .form-section {
        padding: 1rem;
    }

    .attendance-info {
        font-size: 0.8rem;
    }

    .employee-avatar {
        width: 35px;
        height: 35px;
        font-size: 0.8rem;
    }

    .status-badge {
        font-size: 0.7rem;
        padding: 0.3rem 0.6rem;
    }
}

/* Disabled state */
.btn-modern:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.btn-modern:disabled:hover {
    transform: none !important;
    box-shadow: none !important;
}

/* Loading state for camera */
.camera-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
}

.camera-loading i {
    font-size: 2rem;
    margin-bottom: 1rem;
    animation: spin 1s linear infinite;
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

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
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

@keyframes zoomIn {
    from {
        opacity: 0;
        transform: scale(0.3);
    }
    50% {
        opacity: 1;
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Utility Classes */
.fade-in {
    animation: fadeIn 0.5s ease-in-out;
}

.slide-up {
    animation: slideUp 0.5s ease-out;
}

.zoom-in {
    animation: zoomIn 0.5s ease-out;
}

.hover-lift:hover {
    transform: translateY(-2px);
    transition: transform 0.3s ease;
}

/* Focus management for accessibility */
.btn-modern:focus,
.modern-input:focus,
.modern-select:focus,
.modern-textarea:focus {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .modern-header {
        border: 2px solid var(--text);
    }

    .attendance-card {
        border: 2px solid var(--text);
    }

    .btn-modern {
        border: 2px solid currentColor;
    }
}

/* Reduce motion for users who prefer it */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* Modals and Forms */
.modal-backdrop {
    position: fixed;
    inset: 0;
    background-color: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(5px);
    z-index: 1040;
}
.attendance-modal {
    position: fixed;
    inset: 0;
    z-index: 1050;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 1rem;
}
.attendance-modal-content {
    background: var(--surface);
    border-radius: 20px;
    box-shadow: var(--shadow-lg);
    width: 100%;
    max-width: 900px;
    /* padding: 1rem 2rem; */
    position: relative;
}
.photo-preview-container {
    width: 100%;
    aspect-ratio: 4 / 3;
    background-color: var(--surface-soft);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    border: 2px dashed var(--border);
    margin-bottom: 1rem;
    overflow: hidden;
    position: relative;
}
.photo-preview-container img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.form-label {
    font-weight: 600;
}
.form-control {
    border-radius: 12px;
    padding: 0.75rem 1rem;
}
.status-option input {
    position: absolute;
    opacity: 0;
}
.status-option label {
    display: block;
    padding: 1rem;
    border: 2px solid var(--border);
    border-radius: 12px;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s ease;
}
.status-option input:checked + label {
    border-color: var(--primary);
    background-color: rgba(99, 102, 241, 0.05);
    color: var(--primary);
    font-weight: 600;
}
.camera-modal {
    position: fixed;
    inset: 0;
    background-color: #000;
    z-index: 1060;
}
.camera-modal-feed {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: scaleX(-1);
}
.camera-modal-controls {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 2rem;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.7), transparent);
}
.camera-close-btn {
    position: absolute;
    top: 1.5rem;
    right: 1.5rem;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.4);
    color: #fff;
    border: none;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
}
.capture-btn {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background-color: #fff;
    border: 5px solid rgba(255, 255, 255, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
}
.btn-modern {
    padding: 0.8rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
}
.btn-lg {
    padding: 1rem 2rem;
    font-size: 1.1rem;
}
.fade-in {
    animation: fadeIn 0.5s ease-out;
}
.zoom-in {
    animation: zoomIn 0.3s ease-out;
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
@keyframes zoomIn {
    from {
        transform: scale(0.9);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .history-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    .history-card-img {
        width: 100%;
        height: auto;
        max-height: 200px;
    }
}
@media (max-width: 576px) {
    .modern-header {
        padding: 1.5rem;
        text-align: center;
    }
    .modern-header .d-flex {
        flex-direction: column;
        align-items: center !important;
        gap: 1rem;
    }
    .calendar-day-card {
        padding: 0.25rem;
        font-size: 0.8rem;
        min-height: 65px;
    }
    .day-record-info {
        font-size: 0.6rem;
        padding: 0.2rem;
    }
    .attendance-modal .row {
    }
    .attendance-modal .col-md-6 {
        margin-bottom: 1rem;
    }
}
</style>
