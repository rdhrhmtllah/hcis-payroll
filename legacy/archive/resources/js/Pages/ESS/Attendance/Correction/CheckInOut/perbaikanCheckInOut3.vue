<template>
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
                                <div @click="log" class="text-content">
                                    <h2
                                        class="title fw-bold text-white text-start"
                                    >
                                        Halo, Ridho
                                    </h2>
                                    <p>Manajemen kehadiran karyawan</p>
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
                                        <span class="fs-md-6 text-header">
                                            {{ Tanggal }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Control Panel -->
    <div class="row d-flex justify-content-center mb-1 mb-md-3 fade-in">
        <div class="col-md-11">
            <div class="control-panel">
                <div class="search-filter-section">
                    <div
                        class="d-md-flex flex-grow-1 row gap-0 gap-md-2 px-2 px-md-0"
                    >
                        <!-- Mobile -->
                        <div
                            class="d-md-none d-block flex-grow-1 col-6 col-md-12 p-1"
                        >
                            <el-date-picker
                                v-model="selectedDatesFilter"
                                type="daterange"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                unlink-panels
                                range-separator="s.d"
                                start-placeholder="Tanggal Mulai"
                                end-placeholder="Tanggal Selesai"
                                :shortcuts="shortcuts"
                                style="width: fit-content"
                            />
                        </div>

                        <!-- desktop -->
                        <div class="search-container d-md-flex d-none pe-0">
                            <i
                                class="ms-2 bi bi-search search-icon d-flex justify-content-center align-items-center"
                            ></i>
                            <input
                                type="text"
                                class="search-input"
                                placeholder="Cari izin..."
                                v-model="searchQuery"
                                @focus="showSearchSuggestions = true"
                                @blur="hideSuggestions"
                            />
                            <div class="search-results-count">
                                {{ filteredSuggestions.length }} hasil
                            </div>
                            <div
                                class="search-suggestions ms-md-2"
                                v-if="showSearchSuggestions"
                            >
                                <div
                                    class="suggestion-item"
                                    v-for="suggestion in filteredSuggestions"
                                    :key="suggestion"
                                    @mousedown="selectSuggestion(suggestion)"
                                >
                                    <i class="bi bi-clock-history"></i>
                                    {{ suggestion.Nama }} |
                                    {{ suggestion.nama_sub_divisi }}
                                </div>
                                <div
                                    v-if="
                                        searchQuery &&
                                        filteredSuggestions.length === 0
                                    "
                                    class="suggestion-item text-muted"
                                >
                                    Tidak ada saran ditemukan.
                                </div>
                            </div>
                        </div>
                        <div
                            style="width: 10rem"
                            class="d-md-block w-max d-none position-relative ps-0"
                        >
                            <!-- <select
                                v-model="filterDropdown"
                                name=""
                                id=""
                                class="form-control w-max"
                            >
                                <option selected value="">Semua</option>
                                <option value="Izin Pribadi">Izin</option>
                                <option value="cuti">Cuti</option>
                                <option value="sakit">Sakit</option>
                                <option value="pulang cepat">
                                    Pulang cepat
                                </option>
                                <option value="terlambat">Terlambat</option>
                            </select> -->

                            <el-autocomplete
                                v-model="selectedDivisi"
                                :fetch-suggestions="filteredDivisi"
                                value-key="nama_divisi"
                                clearable
                                class="filter"
                                placeholder="Semua Divisi"
                                @select="handleSelectDivisi"
                            />
                        </div>
                    </div>

                    <div class="filters-container">
                        <div class="search-container d-block d-md-none pe-0">
                            <i
                                class="ms-2 bi bi-search search-icon d-flex justify-content-center align-items-center"
                            ></i>
                            <input
                                type="text"
                                class="search-input"
                                placeholder="Cari izin..."
                                v-model="searchQuery"
                                @focus="showSearchSuggestions = true"
                                @blur="hideSuggestions"
                            />
                            <div class="search-results-count">
                                {{ filteredSuggestions.length }} hasil
                            </div>
                            <div
                                class="search-suggestions ms-md-2"
                                v-if="showSearchSuggestions"
                            >
                                <div
                                    class="suggestion-item"
                                    v-for="suggestion in filteredSuggestions"
                                    :key="suggestion"
                                    @mousedown="selectSuggestion(suggestion)"
                                >
                                    <i class="bi bi-clock-history"></i>
                                    {{ suggestion.Nama }} |
                                    {{ suggestion.nama_sub_divisi }}
                                </div>
                                <div
                                    v-if="
                                        searchQuery &&
                                        filteredSuggestions.length === 0
                                    "
                                    class="suggestion-item text-muted"
                                >
                                    Tidak ada saran ditemukan.
                                </div>
                            </div>
                        </div>

                        <div class="d-md-block d-none">
                            <!-- <input
                                v-if="!selectedDateFilter"
                                v-model="selectedDateFilter"
                                type="date"
                                class="form-control"
                                placeholder="Semua"
                                style="cursor: pointer"
                                title="Pilih Tanggal"
                            /> -->
                            <el-date-picker
                                v-model="selectedDatesFilter"
                                type="daterange"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                unlink-panels
                                range-separator="s.d"
                                start-placeholder="Tanggal Mulai"
                                end-placeholder="Tanggal Selesai"
                                :shortcuts="shortcuts"
                                :size="size"
                            />
                            <!--
                            <div
                                v-else
                                type="date"
                                class="form-control"
                                placeholder="Semua"
                                title="Pilih Tanggal"
                                style="cursor: pointer"
                            >
                                {{ formatDateToDMY(selectedDateFilter) }}
                                <span
                                    @click="selectedDateFilter = null"
                                    v-tooltip="'Hapus filter'"
                                    class="closeFilter p-1 rounded-3 ms-2"
                                    ><i class="bi bi-x"></i
                                ></span>
                            </div> -->
                        </div>
                        <!-- <div class="">
                            <div
                                class="filter-btn py-2 position-relative"
                                style="width: fit-content !important"
                            >
                                <button
                                    @click="openDialogExport"
                                    class="toggle-btn active"
                                    title="Card View"
                                >
                                    <i
                                        class="bi bi-file-earmark-arrow-up d-flex justify-content-center align-items-center"
                                    ></i>
                                </button>
                            </div>
                        </div> -->
                        <button
                            :disabled="
                                !selectedEmployee || !selectedDatesFilter
                            "
                            @click="fetchAttendanceData"
                            class="btn-primary btn-modern"
                        >
                            Pilih
                        </button>
                        <!-- view Toggle -->
                        <!-- <div class="view-toggle py-2">
                            <button
                                class="toggle-btn"
                                :class="{ active: viewMode === 'table' }"
                                @click="setViewMode('table')"
                                title="Tampilan Tabel"
                            >
                                <i
                                    class="bi bi-table d-flex justify-content-center align-items-center"
                                ></i>
                            </button>
                            <button
                                class="toggle-btn"
                                :class="{ active: viewMode === 'card' }"
                                @click="setViewMode('card')"
                                title="Tampilan Kartu"
                            >
                                <i
                                    class="bi bi-grid-3x3-gap d-flex justify-content-center align-items-center"
                                ></i>
                            </button>
                        </div> -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Absensi -->
    <div class="row d-flex justify-content-center fade-in">
        <div class="col-md-11">
            <div class="content-container">
                <!-- Loading State -->
                <div v-if="loading" class="loading-container">
                    <div class="loading-animation">
                        <div class="loading-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                    <p class="loading-text">Memuat data absensi...</p>
                </div>

                <!-- Empty State Awal -->
                <div v-else-if="!dataFetched" class="empty-state">
                    <div class="empty-icon">
                        <i
                            class="bi bi-calendar-x d-flex justify-content-center align-items-center"
                        ></i>
                    </div>
                    <h4>Data Belum Dipilih</h4>
                    <p>
                        Silakan pilih karyawan dan periode tanggal untuk melihat
                        data absensi.
                    </p>
                </div>

                <!-- Empty State Setelah Filter -->
                <div
                    v-else-if="attendanceData.length === 0"
                    class="empty-state"
                >
                    <div class="empty-icon">
                        <i
                            class="bi bi-inbox d-flex justify-content-center align-items-center"
                        ></i>
                    </div>
                    <h4>Tidak Ada Data Absensi</h4>
                    <p>
                        Tidak ditemukan data absensi untuk kriteria yang
                        dipilih.
                    </p>
                </div>

                <!-- Tabel Data Absensi -->
                <div v-else class="table-view-container">
                    <div class="table-responsive-wrapper">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th
                                        @click="sort"
                                        class="pointer header-cell"
                                    >
                                        Tanggal
                                        <i
                                            class="ms-2 bi"
                                            :class="
                                                sortDirection === 'asc'
                                                    ? 'bi-sort-down-alt'
                                                    : 'bi-sort-down'
                                            "
                                        ></i>
                                    </th>
                                    <th class="header-cell">Shift</th>
                                    <th class="header-cell">Check-In</th>
                                    <th class="header-cell">Check-Out</th>
                                    <th class="header-cell">Status</th>
                                    <th class="header-cell">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in paginatedAbsensi"
                                    :key="item.id"
                                >
                                    <td>{{ formatDate(item.Tanggal) }}</td>
                                    <td>{{ item.Nama_Shift }}</td>
                                    <td>
                                        <span v-if="item.CheckIn">{{
                                            formatDateString(item.CheckIn)
                                        }}</span>
                                        <span v-else class="text-muted">-</span>
                                    </td>
                                    <td>
                                        <span v-if="item.CheckOut">{{
                                            formatDateString(item.CheckOut)
                                        }}</span>
                                        <span v-else class="text-muted">-</span>
                                    </td>
                                    <td>
                                        <span
                                            :class="
                                                getStatusClass(
                                                    item.Status_Kehadiran
                                                )
                                            "
                                            >{{
                                                item.Status_Kehadiran
                                                    ? item.Status_Kehadiran
                                                    : "Tidak ada"
                                            }}</span
                                        >
                                    </td>
                                    <td>
                                        <button
                                            class="btn-action edit-btn"
                                            @click="editAttendance(item)"
                                            title="Edit Absensi"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div
                        class="pagination-container"
                        v-if="totalAbsensiPages > 1"
                    >
                        <button
                            class="page-btn"
                            @click="prevPage"
                            :disabled="dataAbsensiCurrentPage === 1"
                        >
                            <i
                                class="bi bi-chevron-left d-flex justify-content-center align-items-center"
                            ></i>
                        </button>
                        <!-- <div class="page-numbers">
                            <button
                                v-for="page in visiblePages"
                                :key="page"
                                class="page-number"
                                :class="{ active: page === dataAbsensiCurrentPage }"
                                @click="goToPage(page)"
                            >
                                {{ page }}
                            </button>
                        </div> -->
                        <button
                            class="page-btn"
                            @click="nextPage"
                            :disabled="
                                dataAbsensiCurrentPage === totalAbsensiPages
                            "
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

    <!-- Modal Edit Absensi -->
    <el-dialog
        v-model="showEditModal"
        :title="`Edit Absensi - ${
            selectedUser ? selectedUser.Nama : 'Karyawan'
        }`"
        width="700px"
        center
        :close-on-click-modal="false"
        class="modern-modal"
    >
        <div v-if="selectedUser" class="edit-modal-content modernized">
            <!-- Header Info dengan gaya yang konsisten -->
            <div class="modal-header-info">
                <div class="header-badge date-badge">
                    <i class="bi bi-calendar3"></i>
                    <span>{{ formatDateDisplay(selectedItem.Tanggal) }}</span>
                </div>
                <div class="header-badge user-badge">
                    <i class="bi bi-person"></i>
                    <span>{{ selectedUser.Nama }}</span>
                </div>
                <div
                    class="header-badge status-badge"
                    :class="attendanceStatus"
                >
                    <i class="bi" :class="statusIcon"></i>
                    <span>{{ statusText }}</span>
                </div>
            </div>

            <!-- Mode Selection dengan gaya card -->
            <div class="mode-selection-cards">
                <div
                    class="mode-card"
                    :class="{
                        active: selectionMode === 'checkin',
                        'has-value': editCheckIn,
                    }"
                    @click="setSelectionMode('checkin')"
                >
                    <div class="card-icon">
                        <i class="bi bi-box-arrow-in-right"></i>
                    </div>
                    <div class="card-content">
                        <h4>Check-In</h4>
                        <p v-if="editCheckIn">
                            {{ formatDateOnly(editCheckIn) }}
                        </p>
                        <p v-else class="text-muted">Belum diatur</p>
                    </div>
                    <div class="card-indicator">
                        <i
                            class="bi bi-check-circle-fill"
                            v-if="selectionMode === 'checkin'"
                        ></i>
                    </div>
                </div>

                <div
                    class="mode-card"
                    :class="{
                        active: selectionMode === 'checkout',
                        'has-value': editCheckOut,
                    }"
                    @click="setSelectionMode('checkout')"
                >
                    <div class="card-icon">
                        <i class="bi bi-box-arrow-right"></i>
                    </div>
                    <div class="card-content">
                        <h4>Check-Out</h4>
                        <p v-if="editCheckOut">{{ editCheckOut }}</p>
                        <p v-else class="text-muted">Belum diatur</p>
                    </div>
                    <div class="card-indicator">
                        <i
                            class="bi bi-check-circle-fill"
                            v-if="selectionMode === 'checkout'"
                        ></i>
                    </div>
                </div>
            </div>

            <!-- Date Selection -->
            <div v-if="selectionMode" class="date-selection-section">
                <div class="section-title">
                    <h4>
                        Pilih Waktu
                        {{
                            selectionMode === "checkin"
                                ? "Check-In"
                                : "Check-Out"
                        }}
                    </h4>
                    <button class="btn-close" @click="cancelSelection">
                        <i class="bi bi-x"></i>
                    </button>
                </div>

                <div class="time-options-grid">
                    <div
                        v-for="(record, index) in attendanceRecords"
                        :key="'date-' + index"
                        class="time-option"
                        :class="{
                            selected: selectedDate === record.Checktime,
                            'has-time': !!record[selectionMode],
                            current: record.isCurrent,
                            disabled:
                                record.Checktime === yesterdayData?.CheckIn ||
                                record.Checktime === yesterdayData?.CheckOut ||
                                record.Checktime === tomorrowData?.CheckOut ||
                                record.Checktime === tomorrowData?.CheckIn ||
                                (record.Checktime === editCheckIn &&
                                    selectionMode !== 'checkin') ||
                                (record.Checktime === editCheckOut &&
                                    selectionMode !== 'checkout'),
                        }"
                        @click="
                            !isDisabled(record) && selectDate(record.Checktime)
                        "
                    >
                        <div class="time-main">
                            <div class="time-day">
                                {{ getDayName(record.Checktime) }}
                            </div>
                            <div class="time-full">
                                {{ formatDateOnly(record.Checktime) }}
                            </div>
                        </div>
                        <div class="time-display" v-if="record.Checktime">
                            {{ record.Checktime.split(" ")[1] }}
                        </div>
                        <div class="time-badge current" v-if="record.isCurrent">
                            <i class="bi bi-star-fill"></i> Sekarang
                        </div>
                        <div class="time-badge used" v-if="isDisabled(record)">
                            <i class="bi bi-lock-fill"></i> Digunakan
                        </div>
                    </div>
                </div>

                <!-- Confirmation -->
                <div
                    v-if="
                        selectedDate &&
                        selectedDate != editCheckIn &&
                        selectedDate != editCheckOut
                    "
                    class="confirmation-banner"
                >
                    <div class="banner-content">
                        <i class="bi bi-question-circle"></i>
                        <span
                            >Gunakan waktu
                            <strong>{{ getSelectedTime(selectedDate) }}</strong>
                            untuk {{ selectionMode }}?</span
                        >
                    </div>
                    <button
                        class="btn-modern btn-primary"
                        @click="confirmSelection"
                    >
                        <i class="bi bi-check"></i> Konfirmasi
                    </button>
                </div>
            </div>

            <!-- Changes Preview -->
            <div class="changes-preview">
                <h4>Ringkasan Perubahan</h4>
                <div class="preview-grid">
                    <div class="preview-item">
                        <label>Check-In:</label>
                        <div class="preview-value">
                            <span>{{ editCheckIn || "-" }}</span>
                            <i
                                class="bi bi-arrow-repeat text-warning"
                                v-if="
                                    editCheckIn &&
                                    editCheckIn !== originalCheckIn
                                "
                            ></i>
                        </div>
                    </div>
                    <div class="preview-item">
                        <label>Check-Out:</label>
                        <div class="preview-value">
                            <span>{{ editCheckOut || "-" }}</span>
                            <i
                                class="bi bi-arrow-repeat text-warning"
                                v-if="
                                    editCheckOut &&
                                    editCheckOut !== originalCheckOut
                                "
                            ></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="modal-footer-actions">
                <button
                    class="btn-modern btn-outline"
                    @click="showEditModal = false"
                >
                    <i class="bi bi-x"></i> Batal
                </button>
                <button
                    class="btn-modern btn-primary"
                    @click="showAlertModalFC"
                    :disabled="!hasChanges"
                >
                    <i class="bi bi-check"></i> Simpan Perubahan
                </button>
            </div>
        </template>
    </el-dialog>

    <!-- Alert Modal -->
    <el-dialog
        v-model="showAlertModal"
        title="Konfirmasi menyimpan data"
        width="400px"
        center
        :lock-scroll="true"
    >
        <div class="delete-modal-content">
            <i class="bi bi-exclamation-triangle warning-icon"></i>
            <p>Anda Yakin untuk Mengubah</p>
            <p class="text-danger">Tindakan ini tidak dapat dibatalkan.</p>
        </div>
        <template #footer>
            <div class="delete-modal-footer">
                <button
                    class="btn-modern btn-secondary"
                    @click="closeAlertModal"
                >
                    Batal
                </button>
                <button class="btn-modern btn-success" @click="fnc">
                    {{ buttonAlert }}
                </button>
            </div>
        </template>
    </el-dialog>
</template>

<script>
import axios from "axios";
import "element-plus/dist/index.css";
import {
    ElDialog,
    ElTimePicker,
    ElMessage,
    ElDatePicker,
    ElAutocomplete,
} from "element-plus";

export default {
    components: {
        ElDialog,
        ElTimePicker,
        ElMessage,
        ElDatePicker,
        ElAutocomplete,
    },
    props: {
        dataKaryawan: Array,
        allDivisi: Array,
        Tanggal: String,
    },
    data() {
        return {
            yesterdayData: [],
            tomorrowData: [],
            sortDirection: "asc",
            loadingEdit: false,
            selectionMode: null, // 'checkin' atau 'checkout'
            attendanceRecords: [], // Data absensi dari server (hari ini dan kemarin)
            selectedDate: null, // Tanggal yang dipilih
            originalCheckIn: null, // Nilai check-in asli
            originalCheckOut: null, // Nilai check-out asli
            selectedUser: null, // Data user yang dipilih
            loading: false,
            dataAbsensiSearchQuery: "", // pencarian teks
            dataAbsensiFilterDropdown: "", // filter dropdown (shift/status)
            dataAbsensiSelectedDateFilter: "", // filter tanggal tertentu

            // paginasi
            dataAbsensiCurrentPage: 1,
            dataAbsensiItemsPerPage: 10,
            selectedDivisi: null,
            searchQueryDivisi: null,
            showSearchSuggestions: false,
            searchQuery: "",
            selectedDatesFilter: null,
            loading: false,
            dataFetched: false,
            selectedEmployee: null,
            startDate: "",
            endDate: "",
            employees: [],
            attendanceData: [],
            showEditModal: false,
            selectedItem: null,
            editCheckIn: null,
            editCheckOut: null,
            currentDate: new Date().toLocaleDateString("id-ID", {
                weekday: "long",
                year: "numeric",
                month: "long",
                day: "numeric",
            }),
        };
    },
    mounted() {
        // this.fetchEmployees();
        // Set default date range (current month)
        const today = new Date();
        this.startDate = new Date(today.getFullYear(), today.getMonth(), 1)
            .toISOString()
            .split("T")[0];
        this.endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0)
            .toISOString()
            .split("T")[0];
    },
    // watch: {
    //     showEditModal(newVal) {
    //         if (newVal) {
    //             // Set nilai default dari selectedItem
    //             this.editCheckIn = this.selectedItem.check_in;
    //             this.editCheckOut = this.selectedItem.check_out;
    //         }
    //     },
    // },
    computed: {
        isFormValid() {
            return this.editCheckIn !== null || this.editCheckOut !== null;
        },
        attendanceStatus() {
            if (!this.editCheckIn && !this.editCheckOut) return "absent";
            if (this.editCheckIn && !this.editCheckOut) return "half";
            return "complete";
        },
        attendanceStatus() {
            if (!this.editCheckIn && !this.editCheckOut) return "absent";
            if (this.editCheckIn && !this.editCheckOut) return "half";
            return "complete";
        },
        hasChanges() {
            return (
                this.editCheckIn !== this.originalCheckIn ||
                this.editCheckOut !== this.originalCheckOut
            );
        },

        // Status kehadiran
        attendanceStatus() {
            if (!this.editCheckIn && !this.editCheckOut) return "absent";
            if (this.editCheckIn && !this.editCheckOut) return "half";
            return "complete";
        },

        // Teks status
        statusText() {
            const statusMap = {
                absent: "Tidak Hadir",
                half: "Setengah Hari",
                complete: "Lengkap",
            };
            return statusMap[this.attendanceStatus];
        },
        filteredAbsensi() {
            let filtered = this.attendanceData.map((row) => ({ ...row }));

            // === SEARCH ===
            if (this.dataAbsensiSearchQuery) {
                const query = this.dataAbsensiSearchQuery.toLowerCase();
                filtered = filtered.filter(
                    (item) =>
                        (item.Tanggal &&
                            item.Tanggal.toLowerCase().includes(query)) ||
                        (item.Nama_Shift &&
                            item.Nama_Shift.toLowerCase().includes(query)) ||
                        (item.CheckIn &&
                            item.CheckIn.toLowerCase().includes(query)) ||
                        (item.CheckOut &&
                            item.CheckOut.toLowerCase().includes(query)) ||
                        (item.Status_Kehadiran &&
                            item.Status_Kehadiran.toLowerCase().includes(query))
                );
            }

            // === DROPDOWN FILTER ===
            if (this.dataAbsensiFilterDropdown) {
                const query = this.dataAbsensiFilterDropdown.toLowerCase();
                filtered = filtered.filter(
                    (item) =>
                        (item.Nama_Shift &&
                            item.Nama_Shift.toLowerCase().includes(query)) ||
                        (item.Status_Kehadiran &&
                            item.Status_Kehadiran.toLowerCase().includes(query))
                );
            }

            // === DATE FILTER ===
            if (this.dataAbsensiSelectedDateFilter) {
                const selectedDate = new Date(
                    this.dataAbsensiSelectedDateFilter
                );
                selectedDate.setHours(0, 0, 0, 0);

                filtered = filtered.filter((item) => {
                    const absensiDate = new Date(item.Tanggal);
                    absensiDate.setHours(0, 0, 0, 0);

                    return absensiDate.getTime() === selectedDate.getTime();
                });
            }

            // === SORT TANGGAL ===
            filtered.sort((a, b) => {
                const dateA = new Date(a.Tanggal);
                const dateB = new Date(b.Tanggal);

                if (this.sortDirection === "asc") {
                    return dateA - dateB;
                } else {
                    return dateB - dateA;
                }
            });

            return filtered;
        },

        totalAbsensiPages() {
            return Math.ceil(
                this.filteredAbsensi.length / this.dataAbsensiItemsPerPage
            );
        },

        paginatedAbsensi() {
            const start =
                (this.dataAbsensiCurrentPage - 1) *
                this.dataAbsensiItemsPerPage;
            const end = start + this.dataAbsensiItemsPerPage;
            return this.filteredAbsensi.slice(start, end);
        },

        // Validasi form
        isFormValid() {
            return this.editCheckIn !== null || this.editCheckOut !== null;
        },
        filteredSuggestions() {
            let filtered = this.dataKaryawan;

            if (this.selectedDivisi) {
                // filter dulu berdasarkan divisi
                filtered = filtered.filter(
                    (suggestion) =>
                        suggestion.nama_divisi.toLowerCase() ===
                        this.selectedDivisi.toLowerCase()
                );
            }

            if (this.searchQuery) {
                // lalu filter lagi berdasarkan Nama
                filtered = filtered.filter((suggestion) =>
                    suggestion.Nama.toLowerCase().includes(
                        this.searchQuery.toLowerCase()
                    )
                );
            }

            return filtered;
        },
    },
    methods: {
        log() {
            console.log(this.dataKaryawan);
        },
        sort() {
            // toggle asc <-> desc
            console.log(this.sortDirection);
            this.sortDirection = this.sortDirection === "asc" ? "desc" : "asc";
        },
        formatDateOnly(dateString) {
            try {
                const date = new Date(dateString);
                return date.toLocaleDateString("id-ID", {
                    day: "numeric",
                    month: "short",
                    year: "2-digit",
                    hour: "numeric",
                    minute: "numeric",
                });
            } catch (error) {
                return dateString;
            }
        },
        toDateStr(date) {
            let yyyy = date.getFullYear();
            let mm = String(date.getMonth() + 1).padStart(2, "0");
            let dd = String(date.getDate()).padStart(2, "0");
            return `${yyyy}-${mm}-${dd}`;
        },

        // Mendapatkan nama hari
        getDayName(dateString) {
            try {
                const date = new Date(dateString);
                const days = [
                    "Minggu",
                    "Senin",
                    "Selasa",
                    "Rabu",
                    "Kamis",
                    "Jumat",
                    "Sabtu",
                ];
                return days[date.getDay()].substring(0, 3); // Hanya 3 karakter pertama
            } catch (error) {
                return "-";
            }
        },
        async loadAttendanceRecords(item) {
            // console.log($item);
            if (!item.Tanggal) {
                ElMessage.warning("Tanggal Tidak ada");
                return;
            }

            this.loadingEdit = true;
            try {
                const response = await axios.get(
                    "/perbaiki-checkinout/getCheckInOut",
                    {
                        params: {
                            Kode_Karyawan: item.Kode_Karyawan,
                            date: item.Tanggal,
                        },
                    }
                );
                this.attendanceRecords = response.data.data;
                console.log(this.attendanceRecords);
            } catch (error) {
                console.error("Error fetching attendance data:", error);
                ElMessage.error("Gagal memuat data absensi");
            } finally {
                this.loadingEdit = false;
            }
        },
        async loadRecentAttendance() {
            this.loading = true;
            try {
                // Ganti dengan API call sesuai kebutuhan
                const response = await this.$http.get(
                    `/api/attendance/recent?user_id=${this.selectedItem.user_id}`
                );

                if (response.data.success) {
                    this.attendanceRecords = response.data.data;

                    // Tandai record yang sedang diedit
                    this.attendanceRecords = this.attendanceRecords.map(
                        (record) => {
                            if (record.date === this.selectedItem.date) {
                                return {
                                    ...record,
                                    isCurrent: true, // Tandai sebagai record yang sedang diedit
                                };
                            }
                            return record;
                        }
                    );
                }
            } catch (error) {
                console.error("Error loading attendance records:", error);
                this.$message.error("Gagal memuat data absensi");
            } finally {
                this.loading = false;
            }
        },

        setSelectionMode(mode) {
            console.log(this.editCheckIn, this.editCheckOut);
            this.selectionMode = mode;
            if (mode == "checkin") {
                this.selectedDate = this.editCheckIn;
            } else {
                this.selectedDate = this.editCheckOut;
            }
        },

        // Pilih tanggal
        selectDate(date) {
            // console.log(this.editCheckIn, this.editCheckOut);
            if (this.selectedDate != date) {
                if (
                    date == this.yesterdayData?.CheckIn ||
                    date == this.yesterdayData?.CheckOut
                ) {
                    ElMessage.warning(
                        "Absensi sudah digunakan di hari kemarin ! "
                    );
                } else if (
                    date == this.tomorrowData?.CheckIn ||
                    date == this.tomorrowData?.CheckOut
                ) {
                    ElMessage.warning(
                        "Absensi sudah digunakan di hari Besok ! "
                    );
                } else if (
                    date == this.editCheckIn &&
                    this.selectionMode != "checkin"
                ) {
                    ElMessage.warning("Absensi sudah digunakan untuk check-in");
                } else if (
                    date == this.editCheckOut &&
                    this.selectionMode != "checkout"
                ) {
                    ElMessage.warning(
                        "Absensi sudah digunakan untuk check-out"
                    );
                } else {
                    this.selectedDate = date;
                }
            } else {
                this.selectedDate = null;
            }
        },

        // Konfirmasi pilihan
        confirmSelection() {
            if (
                (this.editCheckIn && !this.selectedDate) ||
                (this.editCheckOut && !this.selectedDate)
            ) {
                if (this.selectionMode === "checkin") {
                    this.editCheckIn = null;
                } else {
                    this.editCheckOut = null;
                }
            } else {
                const selectedRecord = this.attendanceRecords.find(
                    (r) => r.Checktime === this.selectedDate
                );
                // console.log(this.editCheckOut);
                if (selectedRecord) {
                    if (this.selectionMode === "checkin") {
                        this.editCheckIn = selectedRecord.Checktime;
                    } else {
                        this.editCheckOut = selectedRecord.Checktime;
                    }
                }
            }
            // console.log(this.editCheckOut);

            // Reset seleksi
            this.selectionMode = null;
            this.selectedDate = null;
        },

        // Format tampilan tanggal
        formatDateDisplay(dateString) {
            const date = new Date(dateString);
            const today = new Date();
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);

            if (date.toDateString() === today.toDateString()) return "Hari ini";
            if (date.toDateString() === yesterday.toDateString())
                return "Kemarin";

            return date.toLocaleDateString("id-ID", {
                weekday: "short",
                day: "numeric",
                month: "short",
                hour: "2-digit",
                minute: "2-digit",
                second: "2-digit",
            });
        },

        // Mendapatkan waktu yang dipilih
        getSelectedTime(date) {
            const record = this.attendanceRecords.find(
                (r) => r.Checktime === date
            );
            return record ? this.formatDateOnly(record.Checktime) : "-";
        },

        // Cek apakah waktu sudah dipilih untuk tanggal tertentu
        isTimeSelected(date, mode) {
            const record = this.attendanceRecords.find((r) => r.date === date);
            if (!record || !record[mode]) return false;

            if (mode === "checkin") {
                return this.editCheckIn === record.check_in;
            } else {
                return this.editCheckOut === record.check_out;
            }
        },
        // Format tanggal untuk judul modal
        formatDate(dateString) {
            const options = {
                weekday: "long",
                year: "numeric",
                month: "long",
                day: "numeric",
            };
            return new Date(dateString).toLocaleDateString("id-ID", options);
        },

        // Validasi form sebelum menyimpan
        validateForm() {
            if (this.editCheckIn && this.editCheckOut) {
                return this.editCheckIn < this.editCheckOut;
            }
            return true;
        },
        cancelSelection() {
            this.selectionMode = null;
            this.selectedDate = null;
        },

        // Simpan perubahan

        prevPage() {
            if (this.dataAbsensiCurrentPage > 1) {
                this.dataAbsensiCurrentPage--;
            }
        },
        nextPage() {
            if (this.dataAbsensiCurrentPage < this.totalAbsensiPages) {
                this.dataAbsensiCurrentPage++;
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
                    hour: "2-digit",
                    minute: "2-digit",
                    hour12: false,
                };
                return new Intl.DateTimeFormat("id-ID", options).format(date);
            } catch (error) {
                console.error("Error formatting date:", error);
                return dateString; // fallback
            }
        },
        filteredDivisi(queryString, cb) {
            let results = [];

            if (queryString) {
                results = this.allDivisi.filter((item) =>
                    item.nama_divisi
                        .toLowerCase()
                        .includes(queryString.toLowerCase())
                );
            } else {
                results = this.allDivisi;
            }

            cb(results);
        },
        handleSelectDivisi(item) {
            // this.divisiSelected = item;
            console.log(item);
        },
        hideSuggestions() {
            setTimeout(() => {
                this.showSearchSuggestions = false;
            }, 200);
        },
        selectSuggestion(suggestion) {
            this.selectedEmployee = suggestion;
            this.searchQuery = suggestion.Nama;
            this.showSearchSuggestions = false;
        },
        truncateText(text, length) {
            return text.length > length
                ? text.substring(0, length) + "..."
                : text;
        },
        // async fetchEmployees() {
        //     try {
        //         const response = await axios.get("/api/employees");
        //         this.employees = response.data;
        //     } catch (error) {
        //         console.error("Error fetching employees:", error);
        //         ElMessage.error("Gagal memuat data karyawan");
        //     }
        // },
        async fetchAttendanceData() {
            if (!this.selectedEmployee || !this.selectedDatesFilter) {
                ElMessage.warning(
                    "Silakan pilih karyawan dan tanggal terlebih dahulu"
                );
                return;
            }

            this.loading = true;
            try {
                const response = await axios.get(
                    "/perbaiki-checkinout/attendance",
                    {
                        params: {
                            employee_id: this.selectedEmployee,
                            date: this.selectedDatesFilter,
                        },
                    }
                );
                this.attendanceData = response.data.data;
                this.dataFetched = true;
            } catch (error) {
                console.error("Error fetching attendance data:", error);
                ElMessage.error("Gagal memuat data absensi");
            } finally {
                this.loading = false;
            }
        },
        async editAttendance(item) {
            // console.log(item);
            // misal item.Tanggal = '2025-08-09 00:00:00'

            if (item && item.Tanggal) {
                let itemDate = new Date(item.Tanggal);
                // console.log(itemDate);
                // kurangi 1 hari
                let yesterday = new Date(itemDate);
                yesterday.setDate(itemDate.getDate() - 1);
                let tomorrow = new Date(itemDate);
                tomorrow.setDate(itemDate.getDate() + 1);

                let yesterdayStr = this.toDateStr(yesterday);
                let tomorrowStr = this.toDateStr(tomorrow);
                // console.log(yesterdayStr);

                this.tomorrowData = this.attendanceData.find(
                    (r) => this.toDateStr(new Date(r.Tanggal)) === tomorrowStr
                );
                this.yesterdayData = this.attendanceData.find(
                    (r) => this.toDateStr(new Date(r.Tanggal)) === yesterdayStr
                );
            }
            // console.log(this.yesterdayData);

            this.selectedItem = item;
            this.selectedUser = item; // Simpan data user yang dipilih
            this.originalCheckIn = item.CheckIn || null;
            this.originalCheckOut = item.CheckOut || null;
            this.editCheckIn = item.CheckIn || null;
            this.editCheckOut = item.CheckOut || null;
            // Reset state seleksi
            this.selectionMode = null;
            this.selectedDate = null;

            // Load data attendance records
            await this.loadAttendanceRecords(item);

            this.showEditModal = true;
        },

        async saveAttendance() {
            // Inisialisasi array untuk menyimpan perubahan

            // Bentuk payload data untuk dikirim ke backend
            const payload = {
                tanggalAbsensi: this.selectedItem.Tanggal, // Gunakan data dari selectedItem
                Kode_Karyawan: this.selectedUser.Kode_Karyawan, // Pastikan ini sesuai dengan ID karyawan
                perubahan: changesArray,
            };

            try {
                // Kirim data ke API backend
                const response = await axios.post(
                    "/perbaiki-checkinout/submit",
                    payload
                );

                // Handle respons sukses
                if (response.status == 200) {
                    ElMessage.success({
                        message: "Perubahan absensi berhasil disimpan!",
                    });
                    this.fetchAttendanceData();
                }
                this.showEditModal = false;
            } catch (error) {
                // Handle error dari backend
                console.error(
                    "Gagal menyimpan perubahan:",
                    error.response.data
                );
                ElMessage.error({
                    message: "Gagal menyimpan perubahan. Silakan coba lagi.",
                });
            }
        },
        formatDate(dateString) {
            return new Date(dateString).toLocaleDateString("id-ID");
        },
        formatTime(timeString) {
            if (!timeString) return "-";
            return timeString.substring(0, 5); // Format HH:mm
        },
        getDayName(dateString) {
            const days = [
                "Minggu",
                "Senin",
                "Selasa",
                "Rabu",
                "Kamis",
                "Jumat",
                "Sabtu",
            ];
            return days[new Date(dateString).getDay()];
        },
        getStatusClass(status) {
            const statusClasses = {
                Hadir: "status-approved",
                Terlambat: "status-warning",
                "Tidak Hadir": "status-rejected",
                Cuti: "status-info",
            };
            return statusClasses[status] || "";
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
    --success-light: rgb(202, 253, 242);
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
}

/* info bar */
.leave-info-panel {
}
.pointer {
    cursor: pointer !important;
}
/* Compact Modal Styles */
.edit-modal-content.compact {
    padding: 0.75rem;
}

.compact-header {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.header-badge {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.75rem;
    background: var(--surface-soft);
    border-radius: 16px;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.header-badge i {
    font-size: 0.9rem;
}

.date-badge {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
}

.user-badge {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

/* Compact Mode Selection */
.compact-mode-selection {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.mode-pill {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.75rem;
    border: 2px solid var(--border);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    min-height: 80px;
    justify-content: center;
}

.mode-pill:hover {
    border-color: var(--primary-light);
    transform: translateY(-2px);
}

.mode-pill.active {
    border-color: var(--primary);
    background: var(--primary-light);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
}

.mode-pill.has-value {
    border-left: 3px solid var(--success);
}

.pill-icon {
    font-size: 1.25rem;
    margin-bottom: 0.4rem;
}

.pill-label {
    font-weight: 600;
    font-size: 0.85rem;
    margin-bottom: 0.25rem;
    color: var(--text);
}

.pill-time {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-weight: 500;
}

/* Compact Date Section */
.compact-date-section {
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: var(--surface-soft);
    border-radius: 12px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.section-header h5 {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text);
    font-weight: 600;
}

.btn-close-sm {
    width: 24px;
    height: 24px;
    border: none;
    background: var(--surface);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.9rem;
    color: var(--text-muted);
}

.btn-close-sm:hover {
    background: var(--danger-light);
    color: var(--danger);
}

.date-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.date-card {
    padding: 0.6rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    text-align: center;
}

.date-card:hover {
    border-color: var(--primary);
    transform: translateY(-1px);
}

.date-card.selected {
    border-color: var(--primary);
    background: var(--primary-light);
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.1);
}
.date-card.choosed {
    border-color: var(--success);
    background: var(--success-light);
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.1);
}

.date-card.has-time {
    border-left: 2px solid var(--success);
}

.date-card.current {
    background: rgba(99, 102, 241, 0.05);
}

.date-card.yesterday {
    border-color: var(--secondary);

    background: rgba(118, 118, 118, 0.05);
    cursor: not-allowed;
}

.date-card.used {
    border-color: var(--secondary);
    background: rgba(118, 118, 118, 0.05);
    cursor: not-allowed;
}

.date-main {
    margin-bottom: 0.4rem;
}

.date-day {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text);
    text-transform: uppercase;
}

.date-full {
    font-size: 0.7rem;
    color: var(--text-muted);
}

.date-time {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--success);
    padding: 0.2rem 0;
    background: rgba(16, 185, 129, 0.1);
    border-radius: 4px;
}

.date-badge.current-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: var(--primary);
    color: white;
    font-size: 0.6rem;
    padding: 0.1rem 0.4rem;
    border-radius: 10px;
    font-weight: 600;
}

.compact-confirmation {
    padding: 0.6rem;
    background: var(--surface);
    border-radius: 8px;
    text-align: center;
    border: 1px dashed var(--border);
}

.compact-confirmation p {
    margin: 0 0 0.5rem 0;
    font-size: 0.85rem;
    color: var(--text);
}

.btn-confirm-sm {
    padding: 0.4rem 0.75rem;
    background: var(--success);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    transition: all 0.2s ease;
}

.btn-confirm-sm:hover {
    background: #059669;
    transform: translateY(-1px);
}

/* Compact Preview */
.compact-preview {
    padding: 0.75rem;
    background: var(--surface-soft);
    border-radius: 12px;
    margin-bottom: 0.5rem;
}

.preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.6rem;
}

.preview-header h5 {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text);
    font-weight: 600;
}

.status-indicator {
    padding: 0.25rem 0.6rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-indicator.absent {
    background: var(--danger-light);
    color: var(--danger);
}

.status-indicator.half {
    background: var(--warning-light);
    color: var(--warning);
}

.status-indicator.complete {
    background: var(--success-light);
    color: var(--success);
}

.preview-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}

.preview-item {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem;
    background: var(--surface);
    border-radius: 8px;
    font-size: 0.85rem;
}

.preview-label {
    font-weight: 600;
    color: var(--text-muted);
    font-size: 0.8rem;
}

.preview-value {
    font-weight: 500;
    color: var(--text);
    flex-grow: 1;
}

.change-indicator {
    color: var(--success);
    font-weight: bold;
    font-size: 0.9rem;
}

/* Compact Footer */
.compact-modal-footer {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    padding: 0.75rem;
    border-top: 1px solid var(--border);
}

.btn-outline {
    background: transparent;
    color: var(--text);
    border: 1px solid var(--border);
}

.btn-outline:hover {
    background: var(--surface-soft);
    border-color: var(--text-muted);
}
.el-range-editor.el-input__wrapper {
    height: 50px;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

/* Style untuk informasi tanggal yang sedang diedit */
.current-date-info {
    text-align: center;
    margin-bottom: 20px;
    padding: 10px;
    background-color: var(--surface-soft);
    border-radius: 8px;
}

.current-date-info h4 {
    margin: 0 0 5px 0;
    color: var(--text);
}

.current-date-info .date-display {
    font-weight: bold;
    font-size: 1.1rem;
    color: var(--primary);
}

/* Style untuk seleksi mode */
.mode-selection {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.mode-option {
    flex: 1;
    padding: 15px;
    border: 2px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.mode-option:hover {
    border-color: var(--primary);
}

.mode-option.selected {
    border-color: var(--primary);
    background-color: var(--primary-light);
}

.mode-option.has-value {
    border-left: 4px solid var(--success);
}

.mode-icon {
    font-size: 1.5rem;
    display: block;
    margin-bottom: 8px;
}

.mode-label {
    font-weight: bold;
    display: block;
    margin-bottom: 5px;
}

.current-time {
    font-size: 0.9rem;
    color: var(--text-muted);
}

/* Style untuk header seleksi */
.selection-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.btn-cancel {
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: var(--text-muted);
}

.btn-cancel:hover {
    color: var(--danger);
}

/* Style untuk daftar tanggal */
.date-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-bottom: 15px;
}

.date-option {
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 6px;
    cursor: pointer;
    text-align: center;
    position: relative;
    transition: all 0.2s;
}

.date-option:hover {
    border-color: var(--primary);
}

.date-option.selected {
    border-color: var(--primary);
    background-color: var(--primary-light);
}

.date-option.has-time {
    border-left: 4px solid var(--success);
}

.date-option.current-record {
    background-color: rgba(99, 102, 241, 0.1);
}

.date-display {
    font-weight: bold;
    margin-bottom: 5px;
}

.current-badge {
    font-size: 0.7rem;
    color: var(--primary);
}

.time-display {
    font-size: 0.9rem;
    color: var(--text-muted);
}

.checkmark {
    position: absolute;
    top: 5px;
    right: 5px;
    color: var(--success);
    font-weight: bold;
}

/* Style untuk konfirmasi dan preview */
.selection-confirmation {
    padding: 15px;
    background-color: var(--surface-soft);
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
}

.btn-confirm {
    margin-top: 10px;
}

.changes-preview {
    padding: 15px;
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-top: 20px;
}

.preview-row {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}

.preview-label {
    font-weight: bold;
    width: 100px;
}

.preview-value {
    flex-grow: 1;
}

.preview-change {
    color: var(--success);
    font-size: 0.8rem;
    margin-left: 10px;
}

/* Loading state */
.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.recent-time-item:hover {
    background-color: var(--primary-light);
    color: white;
}

.status-indicator {
    padding: 10px;
    border-radius: 6px;
    text-align: center;
    font-weight: bold;
    margin-top: 15px;
}

.status-indicator.absent {
    background-color: var(--danger-light);
    color: var(--danger);
}

.status-indicator.half {
    background-color: var(--warning-light);
    color: var(--warning);
}

.status-indicator.complete {
    background-color: var(--success-light);
    color: var(--success);
}

/* Untuk menutup daftar recent times ketika klik di luar */
.form-group {
    position: relative;
}

.el-input__wrapper {
    height: 50px;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.el-input__wrapper.is-focus {
    box-shadow: 0 0 0 2px var(--primary) inset;
}

.el-range-editor.is-active,
.el-range-editor.is-active:hover {
    box-shadow: 0 0 0 2px var(--primary) inset;
}

.el-input__wrapper.is-active,
.el-input__wrapper.is-active:hover {
    box-shadow: 0 0 0 2px var(--primary) inset !important;
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
    padding: 2px;
    height: 50px;
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
.status-approved {
    color: #10b981;
    font-weight: 600;
}

.status-warning {
    color: #f59e0b;
    font-weight: 600;
}

.status-rejected {
    color: #ef4444;
    font-weight: 600;
}

.status-info {
    color: #3b82f6;
    font-weight: 600;
}

.edit-modal-content {
    padding: 1rem 0;
}

.edit-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}
</style>
<style scoped>
/* Modern Modal Styles */
.modern-modal {
    border-radius: 20px;
    overflow: hidden;
}

.modern-modal :deep(.el-dialog) {
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1),
        0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.modern-modal :deep(.el-dialog__header) {
    padding: 1.5rem 2rem;
    margin: 0;
    background: var(--gradient-primary);
    color: white;
}

.modern-modal :deep(.el-dialog__title) {
    color: white;
    font-weight: 600;
    font-size: 1.25rem;
}

.modern-modal :deep(.el-dialog__headerbtn) {
    top: 1.5rem;
    right: 2rem;
}

.modern-modal :deep(.el-dialog__headerbtn .el-dialog__close) {
    color: white;
    font-size: 1.25rem;
}

.modern-modal :deep(.el-dialog__body) {
    padding: 0;
}

.modern-modal :deep(.el-dialog__footer) {
    padding: 1.5rem 2rem;
    border-top: 1px solid var(--border);
}

/* Modal Header Info */
.modal-header-info {
    display: flex;
    gap: 1rem;
    padding: 1.5rem 2rem;
    background: var(--surface-soft);
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
}

.header-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--surface);
    border-radius: 12px;
    font-size: 0.9rem;
    font-weight: 500;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.header-badge i {
    font-size: 1rem;
}

.date-badge {
    border-left: 4px solid var(--primary);
}

.user-badge {
    border-left: 4px solid var(--success);
}

.status-badge {
    border-left: 4px solid var(--warning);
}

.status-badge.complete {
    border-left: 4px solid var(--success);
}

.status-badge.half {
    border-left: 4px solid var(--warning);
}

.status-badge.absent {
    border-left: 4px solid var(--danger);
}

/* Mode Selection Cards */
.mode-selection-cards {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    padding: 1.5rem 2rem;
}

.mode-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    border: 2px solid var(--border);
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.mode-card:hover {
    border-color: var(--primary-light);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.mode-card.active {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.mode-card.has-value {
    border-left: 4px solid var(--success);
}

.card-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: var(--primary-light);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1.5rem;
}

.card-content {
    flex-grow: 1;
}

.card-content h4 {
    margin: 0 0 0.25rem 0;
    font-weight: 600;
    color: var(--text);
}

.card-content p {
    margin: 0;
    font-size: 0.9rem;
}

.card-indicator {
    color: var(--primary);
    font-size: 1.25rem;
}

/* Date Selection Section */
.date-selection-section {
    padding: 0 2rem 1.5rem;
}

.section-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.section-title h4 {
    margin: 0;
    font-weight: 600;
    color: var(--text);
}

.btn-close {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--surface);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-close:hover {
    background: var(--surface-soft);
    color: var(--danger);
}

/* Time Options Grid */
.time-options-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.time-option {
    position: relative;
    padding: 1rem;
    border: 1px solid var(--border);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
}

.time-option:hover:not(.disabled) {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
}

.time-option.selected {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
}

.time-option.disabled {
    opacity: 0.6;
    cursor: not-allowed;
    background: var(--surface-soft);
}

.time-main {
    margin-bottom: 0.5rem;
}

.time-day {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text);
    text-transform: uppercase;
}

.time-full {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.time-display {
    font-size: 1rem;
    font-weight: 600;
    color: var(--success);
    padding: 0.25rem 0;
}

.time-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    border-radius: 10px;
    font-weight: 600;
}

.time-badge.current {
    background: var(--primary);
    color: white;
}

.time-badge.used {
    background: var(--danger);
    color: white;
}

/* Confirmation Banner */
.confirmation-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    background: rgba(6, 182, 212, 0.1);
    border: 1px solid rgba(6, 182, 212, 0.2);
    border-radius: 12px;
}

.banner-content {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--info);
    font-weight: 500;
}

.banner-content i {
    font-size: 1.25rem;
}

/* Changes Preview */
.changes-preview {
    padding: 1.5rem 2rem;
    border-top: 1px solid var(--border);
}

.changes-preview h4 {
    margin: 0 0 1rem 0;
    font-weight: 600;
    color: var(--text);
}

.preview-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.preview-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.preview-item label {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text-muted);
}

.preview-value {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
    color: var(--text);
}

/* Modal Footer */
.modal-footer-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .modern-modal :deep(.el-dialog) {
        width: 90% !important;
        max-width: 100%;
    }

    .modal-header-info {
        flex-direction: column;
        align-items: flex-start;
    }

    .mode-selection-cards {
        grid-template-columns: 1fr;
    }

    .time-options-grid {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    }

    .confirmation-banner {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }

    .preview-grid {
        grid-template-columns: 1fr;
    }

    .modal-footer-actions {
        flex-direction: column;
    }

    .modal-footer-actions .btn-modern {
        width: 100%;
    }
}
</style>
