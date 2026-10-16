<template>
    <div class="container-fluid px-0">
        <!-- Header -->
        <HeaderKpi
            title="Cuti Saya"
            subtitle="Kelola pengajuan cuti dan lihat riwayat"
            :dayNight="dayNight"
            :formattedDate="formatDateString(currentDate)"
        />

        <quick-stat :stats="quickStatField" />

        <!-- Main Content -->
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <ApprovalTabs
                    :tabs="karyawanTabs"
                    :activeTab="activeTab"
                    @tab-change="handleTabChange"
                >
                    <!-- Tab Ajukan Cuti -->
                    <template #ajukan>
                        <div class="tab-content-improved">
                            <div class="row">
                                <div class="col-lg-8">
                                    <div class="form-group-optimized">
                                        <h5 class="form-title-modern">
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-send-plus me-2"
                                            ></i>
                                            Ajukan Cuti Baru
                                        </h5>

                                        <!-- Form Pengajuan -->
                                        <form
                                            @submit.prevent="submitPengajuan"
                                            class="modern-form"
                                        >
                                            <div class="form-group-modern">
                                                <label
                                                    class="form-label-modern"
                                                >
                                                    <i
                                                        class="bi d-flex justify-content-center align-items-center bi-calendar me-2"
                                                    ></i>
                                                    Pilih Jenis Cuti
                                                </label>
                                                <v-select
                                                    :options="filteredJenisCuti"
                                                    :searchable="true"
                                                    :filterable="true"
                                                    :clearable="false"
                                                    label="label"
                                                    placeholder="Pilih Jenis Cuti..."
                                                    :reduce="(opt) => opt.value"
                                                    v-model="
                                                        pengajuanForm.jenis_cuti_id
                                                    "
                                                    @update:modelValue="
                                                        selectCutiType
                                                    "
                                                    :selectable="
                                                        (option) =>
                                                            !option.disabled
                                                    "
                                                    class="target-scale-select"
                                                />
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-12 mb-3">
                                                    <label
                                                        class="form-label-modern"
                                                    >
                                                        <i
                                                            class="bi d-flex justify-content-center align-items-center bi-calendar-range me-2"
                                                        ></i>
                                                        Periode Cuti
                                                    </label>
                                                    <div
                                                        @click="openDialog"
                                                        class="form-group-modern d-flex cursor-pointer justify-content-start align-items-center"
                                                    >
                                                        <div
                                                            style="
                                                                cursor: pointer !important;
                                                            "
                                                            class="form-control-modern d-flex align-items-center justify-content-center cursor-pointer"
                                                        >
                                                            <div class="me-2">
                                                                <i
                                                                    class="bi bi-calendar-week d-flex justify-content-center align-items-center"
                                                                ></i>
                                                            </div>
                                                            <div
                                                                v-if="
                                                                    !pengajuanForm.dateRange
                                                                "
                                                                class="w-100"
                                                            >
                                                                Tekan untuk
                                                                pilih tanggal
                                                            </div>
                                                            <div
                                                                v-else
                                                                class="w-100"
                                                            >
                                                                {{
                                                                    pengajuanForm
                                                                        .selectedDates
                                                                        .length >
                                                                    0
                                                                        ? `${pengajuanForm.selectedDates.length} hari dipilih`
                                                                        : formatDateString(
                                                                              pengajuanForm
                                                                                  .dateRange[0]
                                                                          ) +
                                                                          " s.d " +
                                                                          formatDateString(
                                                                              pengajuanForm
                                                                                  .dateRange[1]
                                                                          )
                                                                }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-12">
                                                    <div
                                                        class="form-group-modern"
                                                    >
                                                        <label
                                                            class="form-label-modern"
                                                        >
                                                            <i
                                                                class="bi d-flex justify-content-center align-items-center bi-chat-text me-2"
                                                            ></i>
                                                            Alasan Cuti
                                                        </label>
                                                        <textarea
                                                            v-model="
                                                                pengajuanForm.alasan
                                                            "
                                                            class="form-control-modern"
                                                            rows="4"
                                                            placeholder="Jelaskan alasan pengajuan cuti..."
                                                            required
                                                        ></textarea>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-12">
                                                    <div
                                                        class="form-group-modern"
                                                    >
                                                        <label
                                                            class="form-label-modern"
                                                        >
                                                            <i
                                                                class="bi d-flex justify-content-center align-items-center bi-paperclip me-2"
                                                            ></i>
                                                            Dokumen Pendukung
                                                            {{
                                                                selectedTypeCuti &&
                                                                selectedTypeCuti?.dokumen_pendukung
                                                                    ? "(Wajib)"
                                                                    : "(Opsional)"
                                                            }}
                                                        </label>
                                                        <div
                                                            class="file-upload-area"
                                                            @click="
                                                                $refs.fileInput.click()
                                                            "
                                                            @dragover.prevent
                                                            @drop="
                                                                handleFileDrop
                                                            "
                                                        >
                                                            <i
                                                                class="bi d-flex justify-content-center align-items-center bi-cloud-upload"
                                                            ></i>
                                                            <p>
                                                                Klik atau seret
                                                                file untuk
                                                                mengunggah
                                                            </p>
                                                            <small
                                                                >Format: PDF,
                                                                JPG, PNG (Maks.
                                                                2MB)</small
                                                            >
                                                            <input
                                                                type="file"
                                                                ref="fileInput"
                                                                @change="
                                                                    handleFileUpload
                                                                "
                                                                class="d-none"
                                                                accept=".pdf,.jpg,.jpeg,.png"
                                                            />
                                                        </div>
                                                        <div
                                                            v-if="
                                                                pengajuanForm.file_pendukung
                                                            "
                                                            class="file-preview"
                                                        >
                                                            <i
                                                                class="bi d-flex justify-content-center align-items-center bi-file-earmark-text"
                                                            ></i>
                                                            <span>{{
                                                                pengajuanForm
                                                                    .file_pendukung
                                                                    .name
                                                            }}</span>
                                                            <button
                                                                type="button"
                                                                @click="
                                                                    removeFile
                                                                "
                                                                class="btn-remove-file"
                                                            >
                                                                <i
                                                                    class="bi d-flex justify-content-center align-items-center bi-x"
                                                                ></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Validation Messages -->
                                            <div
                                                v-if="validationErrors.length"
                                                class="validation-errors"
                                            >
                                                <div
                                                    v-for="error in validationErrors"
                                                    :key="error"
                                                    class="alert-modern danger"
                                                >
                                                    <i
                                                        class="bi d-flex justify-content-center align-items-center bi-exclamation-triangle"
                                                    ></i>
                                                    <p>{{ error }}</p>
                                                </div>
                                            </div>

                                            <div class="form-actions-modern">
                                                <button
                                                    type="button"
                                                    class="btn-modern btn-secondary"
                                                    @click="resetForm"
                                                >
                                                    <i
                                                        class="bi d-flex justify-content-center align-items-center bi-arrow-clockwise me-2"
                                                    ></i>
                                                    Reset
                                                </button>
                                                <button
                                                    type="submit"
                                                    class="btn-modern btn-primary"
                                                    :disabled="
                                                        !isFormValid ||
                                                        isSubmitting
                                                    "
                                                >
                                                    <i
                                                        v-if="isSubmitting"
                                                        class="bi d-flex justify-content-center align-items-center bi-arrow-repeat animated-spin me-2"
                                                    ></i>
                                                    <i
                                                        v-else
                                                        class="bi d-flex justify-content-center align-items-center bi-send me-2"
                                                    ></i>
                                                    {{
                                                        isSubmitting
                                                            ? "Mengajukan..."
                                                            : "Ajukan Cuti"
                                                    }}
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Sidebar Info -->
                                <div class="col-lg-4">
                                    <div class="info-sidebar-modern">
                                        <div class="col-12">
                                            <div class="duration-display">
                                                <div class="duration-card">
                                                    <i
                                                        class="bi d-flex justify-content-center align-items-center bi-clock d-flex justify-content-center align-items-center me-2"
                                                    ></i>
                                                    <div
                                                        class="duration-content"
                                                    >
                                                        <span
                                                            class="duration-label"
                                                            >Durasi Cuti</span
                                                        >
                                                        <span
                                                            class="duration-value"
                                                            >{{
                                                                pengajuanForm.jumlah_hari
                                                            }}
                                                            Hari
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Info Cuti Bersama -->
                                <div
                                    class="info-card-modern"
                                    v-if="cutiBersamaInfo"
                                >
                                    <h6 class="info-title-modern">
                                        <i
                                            class="bi d-flex justify-content-center align-items-center bi-info-circle me-2"
                                        ></i>
                                        >Informasi
                                    </h6>
                                    <div class="alert-modern info">
                                        <i
                                            class="bi d-flex justify-content-center align-items-center bi-calendar-event"
                                        ></i>
                                        <p>{{ cutiBersamaInfo }}</p>
                                    </div>
                                </div>

                                <!-- Tips -->
                                <div class="tips-card">
                                    <h6 class="tips-title">
                                        <i
                                            class="bi d-flex justify-content-center align-items-center bi-lightbulb me-2"
                                        ></i>
                                        Tips
                                    </h6>
                                    <div class="tips-content">
                                        <div class="tip-item">
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-check-circle"
                                            ></i>
                                            <p>
                                                Gunakan cuti tahunan sebelum
                                                akhir tahun
                                            </p>
                                        </div>
                                        <div class="tip-item">
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-check-circle"
                                            ></i>
                                            <p>
                                                Ajukan cuti minimal 3 hari
                                                sebelumnya
                                            </p>
                                        </div>
                                        <div class="tip-item">
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-check-circle"
                                            ></i>
                                            <p>
                                                Periksa saldo cuti secara
                                                berkala
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Tab Riwayat -->
                    <template #riwayat>
                        <div class="tab-content-improved">
                            <div class="riwayat-container">
                                <!-- Filter Section -->
                                <div class="filter-section mb-4">
                                    <div class="row g-2 align-items-center">
                                        <div class="col-md-6">
                                            <div class="search-box">
                                                <i
                                                    class="d-flex justify-content-center align-items-center bi bi-search"
                                                ></i>
                                                <input
                                                    type="text"
                                                    v-model="
                                                        filters.searchQuery
                                                    "
                                                    placeholder="Cari berdasarkan alasan..."
                                                    class="search-input"
                                                    @input="handleSearch"
                                                />
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <select
                                                v-model="filters.status"
                                                class="filter-select"
                                                @change="loadRiwayat"
                                            >
                                                <option value="">
                                                    Semua Status
                                                </option>
                                                <option value="pending">
                                                    Menunggu
                                                </option>
                                                <option value="approved">
                                                    Disetujui
                                                </option>
                                                <option value="rejected">
                                                    Ditolak
                                                </option>
                                                <option value="cancelled">
                                                    Dibatalkan
                                                </option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <select
                                                v-model="filters.perPage"
                                                class="filter-select"
                                                @change="loadRiwayat"
                                            >
                                                <option value="5">
                                                    5 per halaman
                                                </option>
                                                <option value="10">
                                                    10 per halaman
                                                </option>
                                                <option value="20">
                                                    20 per halaman
                                                </option>
                                                <option value="50">
                                                    50 per halaman
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-12">
                                            <div class="filter-info">
                                                <span class="filter-text"
                                                    >Menampilkan
                                                    {{ filteredRiwayat.length }}
                                                    hasil</span
                                                >
                                                <button
                                                    class="btn-clear-filters"
                                                    @click="refreshData"
                                                >
                                                    <i
                                                        class="d-flex justify-content-center align-items-center bi bi-arrow-clockwise"
                                                    ></i>
                                                    Refresh
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Stats Cards -->
                                <div class="stats-cards mb-4">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="stat-card total">
                                                <div class="stat-icon">
                                                    <i
                                                        class="bi d-flex justify-content-center align-items-center bi-list-ul"
                                                    ></i>
                                                </div>
                                                <div class="stat-content">
                                                    <h4>
                                                        {{
                                                            riwayatData?.data
                                                                ?.length || 0
                                                        }}
                                                    </h4>
                                                    <p>Total Pengajuan</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stat-card approved">
                                                <div class="stat-icon">
                                                    <i
                                                        class="bi d-flex justify-content-center align-items-center bi-check-circle"
                                                    ></i>
                                                </div>
                                                <div class="stat-content">
                                                    <h4>
                                                        {{
                                                            getStatusCount(
                                                                "approved"
                                                            )
                                                        }}
                                                    </h4>
                                                    <p>Disetujui</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stat-card pending">
                                                <div class="stat-icon">
                                                    <i
                                                        class="bi d-flex justify-content-center align-items-center bi-clock"
                                                    ></i>
                                                </div>
                                                <div class="stat-content">
                                                    <h4>
                                                        {{
                                                            getStatusCount(
                                                                "pending"
                                                            )
                                                        }}
                                                    </h4>
                                                    <p>Menunggu</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stat-card rejected">
                                                <div class="stat-icon">
                                                    <i
                                                        class="bi d-flex justify-content-center align-items-center bi-x-circle"
                                                    ></i>
                                                </div>
                                                <div class="stat-content">
                                                    <h4>
                                                        {{
                                                            getStatusCount(
                                                                "rejected"
                                                            )
                                                        }}
                                                    </h4>
                                                    <p>Ditolak</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card View -->
                                <div class="card-view-container">
                                    <div class="riwayat-list-modern">
                                        <div
                                            v-for="item in filteredRiwayat"
                                            :key="item.id"
                                            class="riwayat-card-modern"
                                            :class="`status-${item.status}`"
                                        >
                                            <!-- Card Header with Type and Status -->
                                            <div class="card-header-modern">
                                                <div class="type-section">
                                                    <div
                                                        class="type-icon-wrapper"
                                                    >
                                                        <i
                                                            :class="
                                                                getCutiTypeIcon(
                                                                    item.jenis_cuti ??
                                                                        'Lainnya'
                                                                )
                                                            "
                                                            class="type-icon"
                                                        ></i>
                                                    </div>
                                                    <div class="type-content">
                                                        <h6 class="type-title">
                                                            {{
                                                                item.jenis_cuti
                                                            }}
                                                        </h6>
                                                        <span
                                                            class="duration-badge"
                                                        >
                                                            {{
                                                                item.jumlah_hari
                                                            }}
                                                            hari
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="status-section">
                                                    <Badge
                                                        :type="
                                                            getStatusBadgeType(
                                                                item.status
                                                            )
                                                        "
                                                        class="status-badge"
                                                    >
                                                        {{
                                                            getStatusText(
                                                                item.status
                                                            )
                                                        }}
                                                    </Badge>
                                                </div>
                                            </div>

                                            <!-- Card Content with Date Range and Reason -->
                                            <div class="card-content-modern">
                                                <div class="date-range-section">
                                                    <div
                                                        class="date-icon-wrapper"
                                                    >
                                                        <i
                                                            class="bi bi-calendar-range"
                                                        ></i>
                                                    </div>
                                                    <div class="date-content">
                                                        <div class="date-range">
                                                            <span
                                                                class="date-start"
                                                            >
                                                                {{
                                                                    formatDateCompact(
                                                                        item.tanggal_mulai
                                                                    )
                                                                }}
                                                            </span>
                                                            <i
                                                                class="bi bi-arrow-right date-separator"
                                                            ></i>
                                                            <span
                                                                class="date-end"
                                                            >
                                                                {{
                                                                    formatDateCompact(
                                                                        item.tanggal_selesai
                                                                    )
                                                                }}
                                                            </span>
                                                        </div>
                                                        <div class="date-full">
                                                            {{
                                                                formatDateString(
                                                                    item.tanggal_mulai
                                                                )
                                                            }}
                                                            -
                                                            {{
                                                                formatDateString(
                                                                    item.tanggal_selesai
                                                                )
                                                            }}
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="reason-section">
                                                    <div class="reason-header">
                                                        <i
                                                            class="bi bi-chat-text"
                                                        ></i>
                                                        <span>Alasan</span>
                                                    </div>
                                                    <p class="reason-text">
                                                        {{ item.alasan }}
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- Card Footer with Meta and Actions -->
                                            <div class="card-footer-modern">
                                                <div class="meta-section">
                                                    <div class="meta-item">
                                                        <i
                                                            class="bi bi-clock-history"
                                                        ></i>
                                                        <span>
                                                            Diajukan:
                                                            {{
                                                                formatDateRelative(
                                                                    item.created_at
                                                                )
                                                            }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="actions-section">
                                                    <button
                                                        class="action-btn btn-primary"
                                                        @click.stop="
                                                            viewRiwayatDetail(
                                                                item
                                                            )
                                                        "
                                                        title="Lihat Detail"
                                                    >
                                                        <i
                                                            class="bi bi-eye"
                                                        ></i>
                                                        <span>Detail</span>
                                                    </button>
                                                    <button
                                                        v-if="
                                                            item.status ===
                                                            'pending'
                                                        "
                                                        class="action-btn btn-danger"
                                                        @click.stop="
                                                            cancelPengajuan(
                                                                item
                                                            )
                                                        "
                                                        title="Batalkan Pengajuan"
                                                    >
                                                        <i
                                                            class="bi bi-x-lg"
                                                        ></i>
                                                        <span>Batal</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Empty State for Cards -->
                                    <div
                                        v-if="filteredRiwayat.length === 0"
                                        class="empty-state-modern"
                                    >
                                        <i
                                            class="bi d-flex justify-content-center align-items-center bi-inbox"
                                        ></i>
                                        <h4>Belum Ada Riwayat</h4>
                                        <p>
                                            Anda belum memiliki pengajuan cuti
                                        </p>
                                        <button
                                            class="btn-modern btn-primary"
                                            @click="activeTab = 'ajukan'"
                                        >
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-plus me-2"
                                            ></i>
                                            >Ajukan Cuti Pertama
                                        </button>
                                    </div>
                                </div>

                                <!-- Pagination -->
                                <div
                                    v-if="
                                        riwayatData.pagination &&
                                        riwayatData.pagination.total_pages > 1
                                    "
                                    class="pagination-wrapper"
                                >
                                    <div class="pagination-container">
                                        <div class="pagination-info">
                                            Menampilkan
                                            {{
                                                (riwayatData.pagination
                                                    .current_page -
                                                    1) *
                                                    riwayatData.pagination
                                                        .per_page +
                                                1
                                            }}
                                            -
                                            {{
                                                Math.min(
                                                    riwayatData.pagination
                                                        .current_page *
                                                        riwayatData.pagination
                                                            .per_page,
                                                    riwayatData.pagination.total
                                                )
                                            }}
                                            dari
                                            {{ riwayatData.pagination.total }}
                                            data
                                        </div>
                                        <div class="pagination-controls">
                                            <!-- Previous Button -->
                                            <button
                                                class="pagination-btn"
                                                :disabled="
                                                    riwayatData.pagination
                                                        .current_page === 1
                                                "
                                                @click="
                                                    changePage(
                                                        riwayatData.pagination
                                                            .current_page - 1
                                                    )
                                                "
                                                title="Halaman sebelumnya"
                                            >
                                                <i
                                                    class="bi bi-chevron-left"
                                                ></i>
                                            </button>

                                            <div class="pagination-numbers">
                                                <button
                                                    v-for="page in getVisiblePages()"
                                                    :key="page"
                                                    class="pagination-number"
                                                    :class="{
                                                        active:
                                                            page ===
                                                            riwayatData
                                                                .pagination
                                                                .current_page,
                                                    }"
                                                    @click="changePage(page)"
                                                >
                                                    {{ page }}
                                                </button>
                                            </div>

                                            <!-- Next Button -->
                                            <button
                                                class="pagination-btn"
                                                :disabled="
                                                    riwayatData.pagination
                                                        .current_page ===
                                                    riwayatData.pagination
                                                        .last_page
                                                "
                                                @click="
                                                    changePage(
                                                        riwayatData.pagination
                                                            .current_page + 1
                                                    )
                                                "
                                                title="Halaman berikutnya"
                                            >
                                                <i
                                                    class="bi bi-chevron-right"
                                                ></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </ApprovalTabs>
            </div>
        </div>

        <!-- Modal Detail Riwayat -->
        <el-dialog
            v-model="showDetailModal"
            title="Detail Pengajuan Cuti"
            @close="closeDetailModal"
            width="90%"
            :fullscreen="isMobile"
            :lock-scroll="true"
            destroy-on-close
            class="detail-modal"
        >
            <div v-if="detailLoading" class="skeleton-content p-4">
                <div
                    class="skeleton-line"
                    style="width: 40%; height: 1.5rem; margin-bottom: 1rem"
                ></div>
                <div class="skeleton-line" style="width: 80%"></div>
                <div class="skeleton-line" style="width: 60%"></div>
                <div
                    class="skeleton-line"
                    style="width: 90%; margin-top: 1rem"
                ></div>
            </div>
            <div v-else-if="selectedRiwayat" class="detail-content">
                <div class="row">
                    <div class="col-md-7">
                        <div class="detail-section">
                            <div class="detail-row">
                                <div class="detail-label">Jenis Cuti</div>
                                <div class="detail-value">
                                    {{ selectedRiwayat.jenis_cuti }}
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Periode</div>
                                <div class="detail-value">
                                    {{
                                        formatDateString(
                                            selectedRiwayat.tanggal_mulai
                                        )
                                    }}
                                    -
                                    {{
                                        formatDateString(
                                            selectedRiwayat.tanggal_selesai
                                        )
                                    }}
                                    ({{ selectedRiwayat.jumlah_hari }} hari)
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Status</div>
                                <div class="detail-value">
                                    <Badge
                                        :type="
                                            getStatusBadgeType(
                                                getOverallStatus(
                                                    selectedRiwayat.approvers
                                                )
                                            )
                                        "
                                    >
                                        {{
                                            getStatusText(
                                                getOverallStatus(
                                                    selectedRiwayat.approvers
                                                )
                                            )
                                        }}
                                    </Badge>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Alasan</div>
                                <div class="detail-value">
                                    {{ selectedRiwayat.alasan }}
                                </div>
                            </div>
                            <div
                                v-if="selectedRiwayat.file_pendukung_url"
                                class="detail-row"
                            >
                                <div class="detail-label">File Pendukung</div>
                                <div class="detail-value">
                                    <a
                                        :href="
                                            selectedRiwayat.file_pendukung_url
                                        "
                                        target="_blank"
                                        class="file-link d-flex"
                                    >
                                        <i
                                            class="bi d-flex justify-content-center align-items-center bi-paperclip me-2"
                                        ></i>
                                        Lihat File
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="approval-progress">
                            <h6 class="progress-title">Alur Persetujuan</h6>
                            <el-steps
                                direction="vertical"
                                :active="
                                    getActiveStep(selectedRiwayat.approvers)
                                "
                                finish-status="success"
                            >
                                <el-step
                                    v-for="approver in selectedRiwayat.approvers"
                                    :key="approver.Request_Order_Flow"
                                    :title="approver.approver_name || 'Grup HC'"
                                    :description="getStepDescription(approver)"
                                    :status="getStepStatus(approver)"
                                >
                                    <template #icon>
                                        <i :class="getStepIcon(approver)"></i>
                                    </template>
                                </el-step>
                            </el-steps>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="modal-footer-actions">
                    <button
                        class="btn-modern btn-secondary"
                        @click="closeDetailModal"
                    >
                        Tutup
                    </button>
                </div>
            </template>
        </el-dialog>

        <!-- Modal Konfirmasi Pembatalan -->
        <el-dialog
            v-model="showCancelModal"
            title="Konfirmasi Pembatalan"
            width="400px"
            align-center
            :lock-scroll="true"
            destroy-on-close
            class="cancel-modal"
        >
            <div class="text-center p-4">
                <i
                    class="bi bi-exclamation-triangle text-warning"
                    style="font-size: 3rem"
                ></i>
                <p class="mt-3 mb-0">
                    Apakah Anda yakin ingin membatalkan pengajuan cuti ini?
                </p>
            </div>
            <template #footer>
                <div class="dialog-footer justify-content-center gap-2">
                    <button
                        class="btn-modern btn-secondary"
                        @click="showCancelModal = false"
                    >
                        Batal
                    </button>
                    <button
                        class="btn-modern btn-danger"
                        @click="confirmCancelPengajuan"
                    >
                        Ya, Batalkan
                    </button>
                </div>
            </template>
        </el-dialog>

        <!-- Modal DatePicker -->
        <el-dialog
            v-model="dialogVisible"
            title="Pilih Tanggal Mulai dan Selesai"
            width="480px"
            align-center
            :lock-scroll="true"
        >
            <el-calendar
                v-model="pengajuanForm.calendarDate"
                :disabled-date="disablePastDates"
            >
                <template #date-cell="{ data }">
                    <el-tooltip
                        :disabled="!isSpecialDate(data.date)"
                        :content="getSpecialDateTooltip(data.date)"
                        placement="top"
                    >
                        <div
                            class="cell-date-wrapper"
                            :class="getCellClass(data)"
                            @click="handleDateClick(data)"
                        >
                            <div
                                class="date-cell"
                                :class="{
                                    'is-disabled-custom': disablePastDates(
                                        data.date
                                    ),
                                }"
                            >
                                {{ data.day.split("-")[2] }}
                            </div>
                        </div>
                    </el-tooltip>
                </template>
            </el-calendar>
        </el-dialog>
    </div>
</template>

<script>
import { useForm } from "@inertiajs/vue3";
import ApprovalTabs from "@/components/ui/toggleSectionV2.vue";
import HeaderKpi from "@/components/ui/header.vue";
import Modal from "@/components/ui/modalV2-noir.vue";
import Table from "../../../../components/ui/table.vue";
import Badge from "../../../../components/ui/badge.vue";
import DateTimePicker from "../../../../components/ui/DatePicker.vue";
import quickStat from "@/components/ui/quickStat.vue";
import vSelect from "vue-select";
import axios from "axios";
import {
    ElMessage,
    ElNotification,
    ElDialog,
    ElTooltip,
    ElCalendar,
    ElSteps,
    ElStep,
} from "element-plus";
import "element-plus/dist/index.css";

export default {
    name: "KaryawanCutiOptimized",
    components: {
        ApprovalTabs,
        HeaderKpi,
        Modal,
        Table,
        Badge,
        DateTimePicker,
        vSelect,
        quickStat,
        ElMessage,
        ElNotification,
        ElDialog,
        ElTooltip,
        ElCalendar,
        ElSteps,
        ElStep,
    },
    data() {
        return {
            serverTime: null,
            dialogVisible: false,
            specialDates: [],
            TanggalCuti: [],
            sisaCuti: 0,
            hutangCuti: 0,
            dateRange: null,
            currentDate: new Date(),
            activeTab: "ajukan",
            filters: {
                searchQuery: "",
                status: "",
                perPage: 10,
                currentPage: 1,
            },

            // Form data
            pengajuanForm: {
                jenis_cuti_id: "",
                dateRange: null,
                selectedDates: [],
                jumlah_hari: 0,
                alasan: "",
                file_pendukung: null,
                calendarDate: new Date(),
                hasSelectedDates: false,
            },

            // UI state
            isSubmitting: false,
            validationErrors: [],
            detailLoading: false,
            saldoWarning: "",
            cutiBersamaInfo: "",
            currentMonth: new Date().getMonth(),
            currentYear: new Date().getFullYear(),

            // Modals
            showDetailModal: false,
            showCancelModal: false,
            selectedRiwayat: null,
            pengajuanToCancel: null,
            selectedTypeCuti: null,

            // Data (akan diisi dengan dummy data)
            jenisCutiList: [],
            riwayatList: [],
            riwayatData: {
                data: [],
                pagination: {},
            },
            saldoCuti: [],
            saldoDetail: [],
            calendarEvents: [],
        };
    },
    props: {
        durasiMasuk: { type: Number, default: 0 },
    },
    computed: {
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? "Siang" : "Malam";
        },

        quickStatField() {
            return [
                {
                    title: "Saldo Cuti",
                    value: this.sisaCuti,
                    unit: "Hari",
                    icon: "bi bi-calendar-check",
                    bgIcon: "bg-success",
                    size: "col-4",
                },
                {
                    title: "Pengajuan Pending",
                    value: this.hutangCuti,
                    unit: "",
                    icon: "bi bi-clock-history",
                    bgIcon: "bg-warning",
                    size: "col-4",
                },
                {
                    title: "Total Pengajuan",
                    value: this.riwayatData.total || 0,
                    unit: "",
                    icon: "bi bi-list-check",
                    bgIcon: "bg-info",
                    size: "col-4",
                },
            ];
        },

        karyawanTabs() {
            return [
                {
                    id: "ajukan",
                    label: "Ajukan Cuti",
                    icon: "bi bi-send-plus",
                    count: this.getPendingCount(),
                },
                {
                    id: "riwayat",
                    label: "Riwayat",
                    icon: "bi bi-clock-history",
                    count: this.riwayatData.total || 0,
                },
            ];
        },

        filteredJenisCuti() {
            if (!this.filters.searchQuery) {
                return this.jenisCutiList.map((jenis) => {
                    const isMasaKerjaKurang =
                        this.durasiMasuk < jenis.minimal_masa_kerja;
                    const ishutangCutiMaks = this.hutangCuti > 2;

                    const isDisabled = isMasaKerjaKurang || ishutangCutiMaks;

                    let keteranganDisabled = "";
                    if (isMasaKerjaKurang) {
                        keteranganDisabled = ` (Min. ${jenis.minimal_masa_kerja} Bulan Kerja)`;
                    } else if (ishutangCutiMaks) {
                        keteranganDisabled = ` (Hutang cuti sudah ${this.hutangCuti})`;
                    }

                    return {
                        value: jenis.id,
                        label: `${jenis.nama}${keteranganDisabled}`,
                        original: jenis,
                        disabled: isDisabled,
                    };
                });
            }

            const query = this.filters.searchQuery.toLowerCase();

            return this.jenisCutiList
                .filter(
                    (jenis) =>
                        this.durasiMasuk >= jenis.minimal_masa_kerja &&
                        (jenis.nama.toLowerCase().includes(query) ||
                            (jenis.deskripsi &&
                                jenis.deskripsi.toLowerCase().includes(query)))
                )
                .map((jenis) => {
                    const sisaCuti = this.getSisaCuti(jenis.id);
                    const isSisaHabis = sisaCuti !== null && sisaCuti <= 0;
                    const isDisabled = isSisaHabis;
                    let keteranganSisa = `(Sisa: ${sisaCuti} ${jenis.satuan})`;
                    if (isDisabled) {
                        keteranganSisa = `(Sisa: HABIS)`;
                    }
                    return {
                        value: jenis.id,
                        label: `${jenis.nama} ${keteranganSisa}`,
                        original: jenis,
                        disabled: isDisabled,
                    };
                });
        },

        filteredRiwayat() {
            let filtered = this.riwayatData.data || [];
            if (this.filters.status) {
                filtered = filtered.filter(
                    (item) => item.status === this.filters.status
                );
            }
            return filtered;
        },

        isFormValid() {
            return (
                this.pengajuanForm.jenis_cuti_id &&
                this.pengajuanForm.dateRange &&
                this.pengajuanForm.jumlah_hari > 0 &&
                this.pengajuanForm.alasan &&
                this.validationErrors.length === 0
            );
        },

        // Calendar computations
        weekdays() {
            return ["Min", "Sen", "Sel", "Rab", "Kam", "Jum", "Sab"];
        },

        currentMonthYear() {
            return new Date(
                this.currentYear,
                this.currentMonth
            ).toLocaleDateString("id-ID", {
                month: "long",
                year: "numeric",
            });
        },

        calendarDays() {
            const days = [];
            const firstDay = new Date(this.currentYear, this.currentMonth, 1);
            const lastDay = new Date(
                this.currentYear,
                this.currentMonth + 1,
                0
            );
            const startDay = firstDay.getDay();
            const daysInMonth = lastDay.getDate();

            // Previous month days
            const prevMonthLastDay = new Date(
                this.currentYear,
                this.currentMonth,
                0
            ).getDate();
            for (let i = startDay - 1; i >= 0; i--) {
                days.push({
                    day: prevMonthLastDay - i,
                    date: new Date(
                        this.currentYear,
                        this.currentMonth - 1,
                        prevMonthLastDay - i
                    ),
                    isCurrentMonth: false,
                    isToday: false,
                    isCutiBersama: false,
                    isCutiPribadi: false,
                });
            }

            // Current month days
            const today = new Date();
            for (let i = 1; i <= daysInMonth; i++) {
                const date = new Date(this.currentYear, this.currentMonth, i);
                const isToday = date.toDateString() === today.toDateString();
                const isCutiBersama = this.isCutiBersama(date);
                const isCutiPribadi = this.isCutiPribadi(date);

                days.push({
                    day: i,
                    date: date,
                    isCurrentMonth: true,
                    isToday: isToday,
                    isCutiBersama: isCutiBersama,
                    isCutiPribadi: isCutiPribadi,
                });
            }

            // Next month days
            const totalCells = 42;
            const nextMonthDays = totalCells - days.length;
            for (let i = 1; i <= nextMonthDays; i++) {
                days.push({
                    day: i,
                    date: new Date(this.currentYear, this.currentMonth + 1, i),
                    isCurrentMonth: false,
                    isToday: false,
                    isCutiBersama: false,
                    isCutiPribadi: false,
                });
            }

            return days;
        },

        currentMonthEvents() {
            return this.calendarEvents.filter((event) => {
                const eventDate = new Date(event.tanggal_mulai);
                return (
                    eventDate.getMonth() === this.currentMonth &&
                    eventDate.getFullYear() === this.currentYear
                );
            });
        },

        // Summary computations
        totalCutiTersedia() {
            return this.saldoDetail.reduce(
                (total, saldo) => total + saldo.sisa,
                0
            );
        },

        totalCutiDigunakan() {
            return this.saldoDetail.reduce(
                (total, saldo) => total + saldo.digunakan,
                0
            );
        },

        totalCutiKedaluwarsa() {
            const now = new Date();
            return this.saldoDetail
                .filter(
                    (saldo) =>
                        saldo.expired_date && new Date(saldo.expired_date) > now
                )
                .reduce((total, saldo) => total + saldo.sisa, 0);
        },
    },
    methods: {
        showCancelConfirmation(item) {
            this.pengajuanToCancel = item;
            this.showCancelModal = true;
        },

        confirmCancelPengajuan() {
            if (this.pengajuanToCancel) {
                this.showCancelModal = false;
                this.cancelPengajuan(this.pengajuanToCancel);
            }
        },
        // Format date utilities
        formatDateString(date, withTime = false) {
            if (!date) return "";
            const options = {
                year: "numeric",
                month: "long",
                day: "numeric",
            };
            if (withTime) {
                options.hour = "2-digit";
                options.minute = "2-digit";
            }
            return new Date(date).toLocaleDateString("id-ID", options);
        },

        formatDate(date) {
            return new Date(date).toLocaleDateString("id-ID", {
                day: "numeric",
                month: "short",
                year: "numeric",
            });
        },

        formatDateCompact(date) {
            if (!date) return "";
            const d = new Date(date);
            const day = d.getDate();
            const month = d.toLocaleDateString("id-ID", { month: "short" });
            return `${day} ${month}`;
        },

        formatDateRelative(date) {
            if (!date) return "";
            const now = new Date();
            const target = new Date(date);
            const diffMs = now - target;
            const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

            if (diffDays === 0) return "Hari ini";
            if (diffDays === 1) return "Kemarin";
            if (diffDays < 7) return `${diffDays} hari lalu`;
            if (diffDays < 30) return `${Math.floor(diffDays / 7)} minggu lalu`;
            if (diffDays < 365)
                return `${Math.floor(diffDays / 30)} bulan lalu`;
            return `${Math.floor(diffDays / 365)} tahun lalu`;
        },

        // Badge type helpers
        getStatusBadgeType(status) {
            const types = {
                pending: "warning",
                approved: "success",
                rejected: "danger",
                cancelled: "secondary",
            };
            return types[status] || "secondary";
        },

        getStatusText(status) {
            const texts = {
                pending: "Menunggu",
                approved: "Disetujui",
                rejected: "Ditolak",
                cancelled: "Dibatalkan",
            };
            return texts[status] || status;
        },

        getPendingCount() {
            return (
                this.riwayatData.data?.filter(
                    (item) => item.status === "pending"
                ).length || 0
            );
        },

        // Icon helpers
        getCutiTypeIcon(jenisNama) {
            const icons = {
                tahunan: "bi bi-calendar-heart",
                sakit: "bi bi-heart-pulse",
                melahirkan: "bi bi-gender-female",
                menikah: "bi bi-heart",
                keluarga: "bi bi-people",
                izin: "bi bi-three-dots",
                lainnya: "bi bi-calendar",
            };

            const lowerNama = (jenisNama || "").toLowerCase();
            for (const [key, icon] of Object.entries(icons)) {
                if (lowerNama.includes(key)) {
                    return icon;
                }
            }
            return icons.lainnya;
        },

        // Approval flow helpers
        getStepStatus(approver) {
            if (approver.Flag_Approval === "Y") return "success";
            if (approver.Flag_Approval === "T") return "error";
            return "process";
        },

        getStepIcon(approver) {
            const status = this.getStepStatus(approver);
            const icons = {
                success: "bi bi-check-circle-fill",
                error: "bi bi-x-circle-fill",
                process: "bi bi-hourglass-split",
            };
            return icons[status] || icons.process;
        },

        getStepDescription(approver) {
            if (approver.Updated_At) {
                return `${this.formatDateString(
                    approver.Updated_At,
                    true
                )} oleh ${approver.approver_name || "Sistem"}`;
            }
            return "Menunggu persetujuan";
        },

        getOverallStatus(approvers) {
            if (!approvers || approvers.length === 0) return "pending";

            // Check if any rejected
            if (approvers.some((a) => a.Flag_Approval === "T"))
                return "rejected";

            // Check if all approved
            if (approvers.every((a) => a.Flag_Approval === "Y"))
                return "approved";

            return "pending";
        },

        getActiveStep(approvers) {
            if (!approvers) return 0;

            // Find the last approved step
            for (let i = approvers.length - 1; i >= 0; i--) {
                if (approvers[i].Flag_Approval === "Y") {
                    return i + 1;
                }
            }
            return 0;
        },

        // Validation
        validateForm() {
            this.validationErrors = [];

            // Validate jenis cuti
            if (!this.pengajuanForm.jenis_cuti_id) {
                this.validationErrors.push("Pilih jenis cuti");
            }

            // Validate tanggal
            if (
                !this.pengajuanForm.dateRange &&
                this.pengajuanForm.dateRange.length < 2
            ) {
                this.validationErrors.push("Pilih tanggal cuti");
            } else if (this.pengajuanForm.selectedDates.length === 0) {
                this.validationErrors.push("Pilih minimal satu tanggal");
            }

            // Validate alasan
            if (
                !this.pengajuanForm.alasan ||
                this.pengajuanForm.alasan.trim().length < 3
            ) {
                this.validationErrors.push("Alasan cuti minimal 3 karakter");
            }

            // Validate dokumen (if required)
            const selectedType = this.jenisCutiList.find(
                (j) => j.id === this.pengajuanForm.jenis_cuti_id
            );
            if (
                selectedType?.dokumen_pendukung &&
                !this.pengajuanForm.file_pendukung
            ) {
                this.validationErrors.push(
                    "Dokumen pendukung wajib untuk jenis cuti ini"
                );
            }
        },

        getSisaCuti(jenisCutiId) {
            const saldo = this.saldoCuti.find(
                (s) => s.jenis_cuti_id === jenisCutiId
            );
            return saldo ? saldo.sisa : 0;
        },

        // Event handlers
        handleTabChange(tabId) {
            this.activeTab = tabId;
            this.loadTabData();
        },

        handleSearch() {
            if (this.activeTab === "riwayat") {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.loadRiwayat();
                }, 500);
            }
        },

        selectCutiType(selectedOption) {
            const jenis = selectedOption.original;
            this.selectedTypeCuti = jenis;
            this.pengajuanForm.jenis_cuti_id = jenis.id;
            this.validateForm();
        },

        openDialog() {
            // Convert dateRange to selectedDates if we don't have selectedDates yet
            if (
                this.pengajuanForm.dateRange &&
                this.pengajuanForm.selectedDates.length === 0
            ) {
                const [startStr, endStr] = this.pengajuanForm.dateRange;
                const startDate = new Date(startStr + "T00:00:00");
                const endDate = new Date(endStr + "T23:59:59");

                const selectedDates = [];
                const currentDate = new Date(startDate);

                while (currentDate <= endDate) {
                    selectedDates.push(new Date(currentDate));
                    currentDate.setDate(currentDate.getDate() + 1);
                }

                this.pengajuanForm.selectedDates = selectedDates;
                this.pengajuanForm.hasSelectedDates = true;
            }
            this.dialogVisible = true;
        },

        handleCancel() {
            this.dialogVisible = false;
            this.pengajuanForm.hasSelectedDates = false;
        },

        confirmDate(jenis = "multiple") {
            if (jenis === "multiple") {
                // Update dateRange based on selectedDates
                if (this.pengajuanForm.selectedDates.length > 0) {
                    const sortedDates = [
                        ...this.pengajuanForm.selectedDates,
                    ].sort((a, b) => new Date(a) - new Date(b));
                    const startDate = sortedDates[0];
                    const endDate = sortedDates[sortedDates.length - 1];

                    this.pengajuanForm.dateRange = [
                        this.formatDateString(startDate),
                        this.formatDateString(endDate),
                    ];
                    this.pengajuanForm.jumlah_hari =
                        this.pengajuanForm.selectedDates.length;
                }
            }

            // Reset hasSelectedDates flag
            this.pengajuanForm.hasSelectedDates = false;
            this.validateForm();
        },

        handleDateClick(data) {
            if (this.disablePastDates(data.date)) {
                ElMessage.warning("Tanggal tersebut tidak dapat dipilih");
                return;
            }

            const date = new Date(data.date);
            const existingIndex = this.pengajuanForm.selectedDates.findIndex(
                (d) => d.toDateString() === date.toDateString()
            );

            if (existingIndex !== -1) {
                // Remove existing date
                this.pengajuanForm.selectedDates.splice(existingIndex, 1);
            } else {
                // Add new date
                this.pengajuanForm.selectedDates.push(date);
            }

            this.pengajuanForm.jumlah_hari =
                this.pengajuanForm.selectedDates.length;
            this.pengajuanForm.hasSelectedDates = true;
        },

        getCellClass(data) {
            const date = new Date(data.date);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            return {
                "is-today": date.toDateString() === today.toDateString(),
                "is-weekend": date.getDay() === 0 || date.getDay() === 6,
            };
        },

        isSpecialDate(date) {
            const formattedDate = this.formatDateToYYYYMMDD(date);
            return this.specialDates.some((d) => d.date === formattedDate);
        },

        getSpecialDateTooltip(date) {
            const formattedDate = this.formatDateToYYYYMMDD(date);
            const specialDate = this.specialDates.find(
                (d) => d.date === formattedDate
            );
            return specialDate ? specialDate.description : "";
        },

        formatDateToYYYYMMDD(date) {
            if (!date) return "";
            const d = new Date(date);
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, "0");
            const day = String(d.getDate()).padStart(2, "0");
            return `${year}-${month}-${day}`;
        },

        disablePastDates(date) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return date < today;
        },

        // File handling
        handleFileDrop(event) {
            event.preventDefault();
            const file = event.dataTransfer.files[0];
            if (file) {
                this.handleFileUpload({ target: { files: [file] } });
            }
        },

        handleFileUpload(event) {
            const file = event.target.files[0];
            if (file) {
                // Validate file size
                if (file.size > 2 * 1024 * 1024) {
                    // 2MB
                    ElMessage.error("Ukuran file maksimal 2MB");
                    return;
                }

                // Validate file type
                const allowedTypes = [
                    "application/pdf",
                    "image/jpeg",
                    "image/jpg",
                    "image/png",
                ];
                if (!allowedTypes.includes(file.type)) {
                    ElMessage.error(
                        "Format file tidak didukung. Gunakan PDF, JPG, atau PNG"
                    );
                    return;
                }

                this.pengajuanForm.file_pendukung = file;
            }
        },

        removeFile() {
            this.pengajuanForm.file_pendukung = null;
            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = "";
            }
        },

        // Form submission
        async submitPengajuan() {
            this.validateForm();
            if (this.validationErrors.length > 0 || this.isSubmitting) {
                return;
            }

            this.isSubmitting = true;

            try {
                const payload = new FormData();
                payload.append(
                    "jenis_cuti_id",
                    this.pengajuanForm.jenis_cuti_id
                );
                payload.append("alasan", this.pengajuanForm.alasan);

                // Handle file upload
                if (this.pengajuanForm.file_pendukung) {
                    payload.append(
                        "file_pendukung",
                        this.pengajuanForm.file_pendukung
                    );
                }

                // Handle multiple dates
                if (this.pengajuanForm.selectedDates.length > 0) {
                    // Add all selected dates
                    this.pengajuanForm.selectedDates.forEach((date, index) => {
                        payload.append(
                            `selected_dates[${index}]`,
                            this.formatDateToYYYYMMDD(date)
                        );
                    });
                    payload.jumlah_hari =
                        this.pengajuanForm.selectedDates.length;
                } else {
                    // Use single date range
                    const [startStr, endStr] = this.pengajuanForm.dateRange;
                    payload.append("tanggal_mulai", startStr);
                    payload.append("tanggal_selesai", endStr);
                    payload.jumlah_hari = 1;
                }

                const response = await axios.post(
                    "/api/v1/cuti/store",
                    payload,
                    {
                        headers: {
                            "Content-Type": "multipart/form-data",
                        },
                    }
                );

                if (response.data.status) {
                    ElNotification.success("Pengajuan cuti berhasil diajukan!");
                    this.resetForm();
                    this.activeTab = "riwayat";
                    await this.loadRiwayat();
                } else {
                    ElMessage.error(
                        "Gagal mengajukan cuti: " +
                            (response.data.message || "Terjadi kesalahan")
                    );
                }
            } catch (error) {
                console.error("Submit error:", error);
                ElMessage.error("Terjadi kesalahan saat mengajukan cuti");
            } finally {
                this.isSubmitting = false;
            }
        },

        async cancelPengajuan(item) {
            try {
                const response = await axios.post(
                    `/api/v1/cuti/cancel/${item.No_Transaksi}`
                );

                if (response.data.status) {
                    ElNotification.success(
                        "Pengajuan cuti berhasil dibatalkan"
                    );
                    await this.loadRiwayat();
                } else {
                    ElMessage.error("Gagal membatalkan pengajuan");
                }
            } catch (error) {
                console.error("Cancel error:", error);
                ElMessage.error("Terjadi kesalahan");
            }
        },

        // Reset form
        resetForm() {
            this.pengajuanForm = {
                jenis_cuti_id: "",
                dateRange: null,
                selectedDates: [],
                jumlah_hari: 0,
                alasan: "",
                file_pendukung: null,
                calendarDate: new Date(),
                hasSelectedDates: false,
            };
            this.selectedTypeCuti = null;
            this.validationErrors = [];
        },

        // API calls
        async loadJenisCuti() {
            try {
                const response = await axios.get("/api/v1/cuti/jenis-cuti");
                if (response.data.status === 200) {
                    this.jenisCutiList = response.data.result;
                } else {
                    console.error("Failed to load jenis cuti");
                }
            } catch (error) {
                console.error("Error loading jenis cuti:", error);
            }
        },

        async loadRiwayat() {
            try {
                const params = new URLSearchParams({
                    page: this.filters.currentPage || 1,
                    limit: this.filters.perPage || 10,
                    search_query: this.filters.searchQuery || "",
                    status_filter: this.filters.status || "",
                });

                const response = await axios.get(
                    `/api/v1/cuti/riwayat?${params}`
                );
                if (response.data.status === 200) {
                    this.riwayatData.data = response.data.result;
                    this.riwayatData.pagination = response.data.pagination;
                } else {
                    console.error("Failed to load riwayat:", response.data);
                }
            } catch (error) {
                console.error("Error loading riwayat:", error);
            }
        },

        // Pagination methods
        async changePage(page) {
            if (page < 1 || page > this.riwayatData.pagination.last_page)
                return;

            this.filters.currentPage = page;
            await this.loadRiwayat();

            // Scroll to top setelah ganti halaman
            this.$nextTick(() => {
                const container = this.$el.querySelector(".riwayat-container");
                if (container) {
                    container.scrollIntoView({
                        behavior: "smooth",
                        block: "start",
                    });
                }
            });
        },

        getVisiblePages() {
            if (!this.riwayatData.pagination) return [];

            const current = this.riwayatData.pagination.current_page;
            const last = this.riwayatData.pagination.last_page;
            const delta = 2; // Number of pages to show before and after current page

            const range = [];
            const rangeWithDots = [];
            let l;

            for (let i = 1; i <= last; i++) {
                if (
                    i === 1 ||
                    i === last ||
                    (i >= current - delta && i <= current + delta)
                ) {
                    range.push(i);
                }
            }

            range.forEach((i) => {
                if (l) {
                    if (i - l === 2) {
                        rangeWithDots.push(l + 1);
                    } else if (i - l !== 1) {
                        rangeWithDots.push("...");
                    }
                }
                rangeWithDots.push(i);
                l = i;
            });

            return rangeWithDots.filter(
                (page) => page !== "..." || !rangeWithDots.includes(page - 1)
            );
        },
        getStatusCount(status) {
            return this.riwayatData?.data?.filter(
                (item) => item.status === status
            ).length;
        },

        async loadSaldoCuti() {
            try {
                const response = await axios.get("/api/v1/cuti/check-saldo");
                if (response.data.status === 200) {
                    this.sisaCuti = response.data.result.saldo;
                    this.hutangCuti = response.data.result.hutang;

                    // Update saldoCuti list for display
                    this.saldoCuti = response.data.result.saldo;
                } else {
                    console.error("Failed to load saldo cuti");
                    this.sisaCuti = 0;
                    this.hutangCuti = 0;
                }
            } catch (error) {
                console.error("Error loading saldo:", error);
            }
        },

        async loadTabData() {
            switch (this.activeTab) {
                case "ajukan":
                    await Promise.all([
                        this.loadJenisCuti(),
                        this.loadSaldoCuti(),
                    ]);
                    break;
                case "riwayat":
                    await this.loadRiwayat();
                    break;
            }
        },

        refreshData() {
            ElNotification.info("Memperbarui data...");
            this.loadTabData();
        },

        // Load server time
        async getTimeServer() {
            try {
                const response = await axios.get("/getTime");
                this.serverTime = new Date(response.data.time);
            } catch (error) {
                console.error("Failed to get server time:", error);
                this.serverTime = new Date();
            }
        },

        // Modal handlers
        async viewRiwayatDetail(item) {
            this.selectedRiwayat = null;
            this.detailLoading = true;
            this.showDetailModal = true;

            try {
                const response = await axios.get(
                    `/api/v1/cuti/riwayat/${item.No_Transaksi}`
                );
                if (response.data.status === 200) {
                    this.selectedRiwayat = response.data.result;
                } else {
                    ElMessage.error("Gagal memuat detail riwayat");
                }
            } catch (error) {
                console.error("Error loading detail:", error);
            } finally {
                this.detailLoading = false;
            }
        },

        closeDetailModal() {
            this.showDetailModal = false;
            this.selectedRiwayat = null;
        },

        confirmCancelPengajuan() {
            this.showCancelModal = false;
            this.cancelPengajuan(this.pengajuanToCancel);
        },

        // Calendar utilities
        getPendingCount() {
            return (
                this.riwayatData.data?.filter(
                    (item) => item.status === "pending"
                ).length || 0
            );
        },

        // Mobile detection
        get isMobile() {
            return window.innerWidth < 768;
        },
    },
    mounted() {
        this.getTimeServer();
        this.loadTabData();
    },
    watch: {
        activeTab: {
            handler(newTab) {
                this.filters.status = ""; // Reset status filter when tab changes
                this.filters.searchQuery = ""; // Reset search when tab changes
                this.filters.currentPage = 1; // Reset page when tab changes
            },
        },
        "filters.searchQuery": {
            handler() {
                if (this.activeTab === "riwayat") {
                    this.filters.currentPage = 1; // Reset page when searching
                    clearTimeout(this.searchTimeout);
                    this.searchTimeout = setTimeout(() => {
                        this.loadRiwayat();
                    }, 500);
                }
            },
        },
        "filters.status": {
            handler() {
                if (this.activeTab === "riwayat") {
                    this.filters.currentPage = 1; // Reset page when filter changes
                    this.loadRiwayat();
                }
            },
        },
        "filters.perPage": {
            handler() {
                if (this.activeTab === "riwayat") {
                    this.filters.currentPage = 1; // Reset page when per page changes
                    this.loadRiwayat();
                }
            },
        },
    },
};
</script>

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
    --danger-light: #f8d7da;
    --gray-light: #e9ecef;
    --el-slider-main-bg-color: #6366f1;
    --el-calendar-cell-width: 50px !important;
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

.form-input-optimized:focus,
.form-select-optimized:focus,
.form-textarea-optimized:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
}

.form-textarea-optimized {
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}

/* Reset dan styling komprehensif */
:deep(.v-select) {
    width: 100%;
    font-family: inherit;
}

:deep(.vs__dropdown-toggle) {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    font-size: 14px;
    transition: all 0.3s ease;
    min-height: 48px;
    display: flex;
    align-items: center;
}

:deep(.vs__selected) {
    margin: 0;
    padding: 0;
    display: flex;
    align-items: center;
}

:deep(.vs__search) {
    font-size: 14px !important;
    color: var(--text) !important;
    margin: 0;
    padding: 0;
    border: none;
    background: transparent;
}

:deep(.vs__search::placeholder) {
    color: var(--text);
    opacity: 0.6;
}

:deep(.vs__selected-options) {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
    flex: 1;
}

:deep(.vs__actions) {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Focus states */
:deep(.v-select.focused .vs__dropdown-toggle),
:deep(.vs--open .vs__dropdown-toggle) {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Dropdown menu */
:deep(.vs__dropdown-menu) {
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: 10px;
    margin-top: 4px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

:deep(.vs__dropdown-option) {
    padding: 0.75rem 1rem;
    color: var(--text);
    font-size: 14px;
}

:deep(.vs__dropdown-option--highlight) {
    background: var(--primary);
    color: white;
}

.weight-control-optimized {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.weight-slider-optimized {
    flex: 1;
    height: 4px;
    border-radius: 2px;
    background: var(--border);
    outline: none;
    -webkit-appearance: none;
}

.weight-slider-optimized::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: var(--primary);
    cursor: pointer;
    border: 2px solid white;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.weight-value {
    min-width: 50px;
    text-align: center;
    font-weight: 600;
    color: var(--primary);
    font-size: 0.9rem;
}

.loading-text {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-style: italic;
    margin-top: 0.25rem;
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

/* Improved Pagination Styles */
.pagination-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    background: var(--surface-soft) !important;
    color: var(--text-muted) !important;
    border-color: var(--border) !important;
    transform: none !important;
}

.pagination-btn:not(:disabled):hover {
    background: var(--primary) !important;
    color: white !important;
    border-color: var(--primary) !important;
}

.pagination-number.active {
    background: var(--primary) !important;
    color: white !important;
    border-color: var(--primary) !important;
    font-weight: 600;
}

/* Improved Detail Modal Styles */
.detail-content-improved {
    padding: 0.5rem;
}

.detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border);
}

.detail-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.detail-status {
    font-size: 0.9rem;
    padding: 0.5rem 1rem;
}

.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.detail-column {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.detail-item.full-width {
    grid-column: 1 / -1;
}

.detail-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.detail-label i {
    color: var(--primary);
    font-size: 1rem;
}

.detail-value {
    color: var(--text);
    font-size: 1rem;
    line-height: 1.5;
}

.detail-meta {
    color: var(--text-muted);
    font-size: 0.85rem;
    margin-left: 0.5rem;
}

.reason-text {
    background: var(--surface-soft);
    padding: 1rem;
    border-radius: 8px;
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
    border-radius: 6px;
    transition: all 0.3s ease;
}

.file-link:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-1px);
}

.approval-progress-section {
    margin-top: 1rem;
}

.progress-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 1rem;
    font-size: 1rem;
}

.custom-steps {
    margin-top: 1rem;
}

.step-icon {
    font-size: 1.2rem;
}

.modal-footer-improved {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    padding: 1.5rem 0 0;
    border-top: 1px solid var(--border);
}

/* Mobile Responsive for Detail Modal */
@media (max-width: 768px) {
    .detail-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .detail-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .modal-footer-improved {
        flex-direction: column;
    }

    .modal-footer-improved .btn-modern {
        width: 100%;
        justify-content: center;
    }

    .el-dialog__header {
        padding: 1rem !important;
    }

    .el-dialog__body {
        padding: 1rem !important;
    }
}

.form-group-optimized {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-label-optimized {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-input-optimized,
.form-select-optimized,
.form-textarea-optimized {
    padding: 0.75rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    background: var(--surface);
    color: var(--text);
    width: 100%;
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

.form-input-optimized:focus,
.form-select-optimized:focus,
.form-textarea-optimized:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
}

.form-textarea-optimized {
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}

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

.btn-danger {
    background: var(--danger);
    color: white;
}
.btn-danger:hover {
    background: #c23030;
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

/* Modern Form Styles */
.form-section-modern {
    background: var(--surface);
    border-radius: 16px;
    padding: 2rem;
    border: 1px solid var(--border);
}

.form-title-modern {
    font-weight: 600;
    color: var(--dark);
    display: flex;
    align-items: center;
    font-size: 1.25rem;
}

/* Modern Form Elements */
.modern-form {
    margin-top: 2rem;
}

.form-group-modern {
    margin-bottom: 1.5rem;
}

.form-label-modern {
    display: flex;
    align-items: center;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
}

.form-control-modern {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.form-control-modern:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Duration Display */
.duration-display {
    margin: 1.5rem 0;
}

.duration-card {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.duration-card i {
    font-size: 2rem;
}

.duration-content {
    flex: 1;
}

.duration-label {
    display: block;
    font-size: 0.9rem;
    opacity: 0.9;
}

.duration-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
}

/* File Upload */
.file-upload-area {
    border: 2px dashed var(--border);
    border-radius: 10px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
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
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
}

.btn-remove-file {
    background: none;
    border: none;
    color: var(--danger);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    margin-left: auto;
}

/* Form Actions */
.form-actions-modern {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border);
}

/* Info Sidebar */
.info-sidebar-modern {
    position: sticky;
    top: 1rem;
}

.info-card-modern {
    background: var(--surface);
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid var(--border);
    margin-bottom: 1.5rem;
}

.info-title-modern {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

/* Alert Modern */
.alert-modern {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    border-radius: 8px;
    background: var(--surface-soft);
}

.alert-modern.info {
    background: rgba(6, 182, 212, 0.1);
    border-left: 4px solid var(--info);
}

.alert-modern i {
    color: var(--info);
    margin-top: 0.125rem;
}

.alert-modern p {
    margin: 0;
    color: var(--text);
    line-height: 1.5;
}

/* Tips Card */
.tips-card {
    background: linear-gradient(135deg, #fff3cd, #ffeaa7);
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.tips-title {
    font-weight: 600;
    color: #856404;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.tips-content {
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
    color: #d97706;
    margin-top: 0.125rem;
}

.tip-item p {
    margin: 0;
    color: #856404;
    font-size: 0.9rem;
    line-height: 1.4;
}

/* Stats Cards */
.stats-cards {
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--surface);
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
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

.stat-content h4 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
    margin: 0;
}

.stat-content p {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.9rem;
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

/* Modern Riwayat Card Styles */
.riwayat-list-modern {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.riwayat-card-modern {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.riwayat-card-modern:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    border-color: var(--primary-light);
}

/* Status-based card variations */
.riwayat-card-modern.status-approved {
    border-left: 4px solid var(--success);
}

.riwayat-card-modern.status-pending {
    border-left: 4px solid var(--warning);
}

.riwayat-card-modern.status-rejected {
    border-left: 4px solid var(--danger);
}

.riwayat-card-modern.status-cancelled {
    border-left: 4px solid var(--secondary);
}

/* Card Header */
.card-header-modern {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem 1rem;
    background: linear-gradient(135deg, var(--surface-soft), var(--surface));
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.type-section {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.type-icon-wrapper {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    position: relative;
    overflow: hidden;
}

.type-icon-wrapper::before {
    content: "";
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(
        45deg,
        transparent,
        rgba(255, 255, 255, 0.1),
        transparent
    );
    transform: rotate(45deg);
    transition: all 0.5s ease;
}

.riwayat-card-modern:hover .type-icon-wrapper::before {
    animation: shimmerCard 0.5s ease;
}

.type-content {
    flex: 1;
}

.type-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
    line-height: 1.2;
}

.duration-badge {
    display: inline-flex;
    align-items: center;
    background: var(--primary-light);
    color: var(--primary);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-section {
    display: flex;
    align-items: center;
}

.status-section .status-badge {
    font-size: 0.8rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Card Content */
.card-content-modern {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.date-range-section {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--surface-soft);
    border-radius: 12px;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.date-icon-wrapper {
    width: 40px;
    height: 40px;
    background: var(--info);
    color: white;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    box-shadow: 0 2px 8px rgba(6, 182, 212, 0.3);
}

.date-content {
    flex: 1;
}

.date-range {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
}

.date-start,
.date-end {
    font-weight: 600;
    color: var(--dark);
    font-size: 0.95rem;
}

.date-separator {
    color: var(--text-muted);
    font-size: 0.8rem;
}

.date-full {
    font-size: 0.8rem;
    color: var(--text-muted);
    line-height: 1.2;
}

.reason-section {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.reason-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    font-size: 0.85rem;
    font-weight: 600;
}

.reason-header i {
    color: var(--primary);
}

.reason-text {
    margin: 0;
    color: var(--text);
    line-height: 1.6;
    font-size: 0.9rem;
    padding: 0.75rem;
    background: var(--surface);
    border-radius: 8px;
    border-left: 3px solid var(--primary);
}

/* Card Footer */
.card-footer-modern {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem 1.25rem;
    background: var(--surface-soft);
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

.meta-section {
    display: flex;
    align-items: center;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    font-size: 0.8rem;
}

.meta-item i {
    color: var(--primary);
    font-size: 0.85rem;
}

.actions-section {
    display: flex;
    gap: 0.5rem;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    position: relative;
    overflow: hidden;
}

.action-btn::before {
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
    transition: left 0.5s ease;
}

.action-btn:hover::before {
    left: 100%;
}

.action-btn.btn-primary {
    background: var(--primary);
    color: white;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
}

.action-btn.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
}

.action-btn.btn-danger {
    background: var(--danger);
    color: white;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.action-btn.btn-danger:hover {
    background: #c23030;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

.action-btn i {
    font-size: 0.9rem;
}

.action-btn span {
    font-size: 0.75rem;
}

/* Empty State */
.empty-state-modern {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-muted);
}

.empty-state-modern i {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: 0.5;
}

.empty-state-modern h4 {
    margin-bottom: 1rem;
    color: var(--text);
}

.empty-state-modern p {
    margin-bottom: 2rem;
}

/* Pagination Styles */
.pagination-wrapper {
    margin-top: 2rem;
    background: var(--surface);
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid var(--border);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
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
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.pagination-btn:hover:not(:disabled) {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: translateY(-1px);
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
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
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
    color: var(--text);
    font-size: 0.9rem;
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

/* Calendar Styles */
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
}

.is-disabled-custom .date-cell {
    background-color: transparent !important;
}

.is-disabled-custom:hover .date-cell {
    background-color: transparent !important;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .riwayat-list-modern {
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1rem;
    }
}

@media (max-width: 768px) {
    .riwayat-list-modern {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .card-header-modern {
        padding: 1rem;
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }

    .type-section {
        width: 100%;
    }

    .status-section {
        align-self: flex-end;
    }

    .card-content-modern {
        padding: 1rem;
    }

    .date-range-section {
        padding: 0.75rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }

    .date-range {
        align-items: center;
    }

    .reason-text {
        padding: 0.5rem;
    }

    .card-footer-modern {
        padding: 1rem;
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }

    .actions-section {
        width: 100%;
        justify-content: center;
    }

    .action-btn {
        flex: 1;
        justify-content: center;
        padding: 0.75rem 1rem;
    }

    .action-btn span {
        display: none;
    }

    .pagination-container {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
        text-align: center;
    }

    .pagination-controls {
        justify-content: center;
        flex-wrap: wrap;
    }

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
    .type-icon-wrapper {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }

    .type-title {
        font-size: 0.9rem;
    }

    .duration-badge {
        font-size: 0.7rem;
        padding: 0.2rem 0.6rem;
    }

    .date-icon-wrapper {
        width: 36px;
        height: 36px;
        font-size: 0.9rem;
    }

    .date-start,
    .date-end {
        font-size: 0.9rem;
    }

    .reason-text {
        font-size: 0.85rem;
    }

    .action-btn {
        padding: 0.6rem 0.8rem;
        font-size: 0.75rem;
    }

    .action-btn i {
        font-size: 0.8rem;
    }

    .filter-section {
        padding: 0.5rem;
    }

    .search-input,
    .filter-select {
        padding: 0.5rem 0.6rem 0.5rem 2rem;
        font-size: 0.85rem;
    }
}

/* Animation for shimmer effect */
@keyframes shimmerCard {
    0% {
        transform: translateX(-100%) rotate(45deg);
    }
    100% {
        transform: translateX(100%) rotate(45deg);
    }
}
</style>

<style scope>
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

.form-input-optimized:focus,
.form-select-optimized:focus,
.form-textarea-optimized:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
}

.form-textarea-optimized {
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}

/* Reset dan styling komprehensif */
:deep(.v-select) {
    width: 100%;
    font-family: inherit;
}

:deep(.vs__dropdown-toggle) {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    font-size: 14px;
    transition: all 0.3s ease;
    min-height: 48px;
    display: flex;
    align-items: center;
}

:deep(.vs__selected) {
    margin: 0;
    padding: 0;
    display: flex;
    align-items: center;
}

:deep(.vs__search) {
    font-size: 14px !important;
    color: var(--text) !important;
    margin: 0;
    padding: 0;
    border: none;
    background: transparent;
}

:deep(.vs__search::placeholder) {
    color: var(--text);
    opacity: 0.6;
}

:deep(.vs__selected-options) {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
    flex: 1;
}

:deep(.vs__actions) {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Focus states */
:deep(.v-select.focused .vs__dropdown-toggle),
:deep(.vs--open .vs__dropdown-toggle) {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Dropdown menu */
:deep(.vs__dropdown-menu) {
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: 10px;
    margin-top: 4px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

:deep(.vs__dropdown-option) {
    padding: 0.75rem 1rem;
    color: var(--text);
    font-size: 14px;
}

:deep(.vs__dropdown-option--highlight) {
    background: var(--primary);
    color: white;
}
</style>
