<template>
    <div class="row d-flex align-items-center justify-content-center mb">
        <div class="col-11 p-0">
            <!-- Header -->
            <div
                class="header-wrapper p-3 p-md-5 rounded-4 d-flex flex-column flex-md-row justify-content-between shadow-unix fade-in"
            >
                <div class="d-flex gap-2">
                    <div
                        class="icon-container glassMorph rounded-4 d-flex justify-content-center align-items-center"
                    >
                        <i
                            class="bi bi-clock-history fs-1 d-flex justify-content-center align-items-center text-white"
                        ></i>
                    </div>
                    <div>
                        <div
                            class="title-header fs-3 fw-bold text-white m-0 p-0 title"
                        >
                            Manajemen Lembur Karyawan
                        </div>
                        <div class="text-white sub-title-header fs-6">
                            Kelola jadwal Lembur
                        </div>
                    </div>
                </div>
                <div
                    class="d-flex flex-md-column align-items-center align-items-md-end justify-content-center gap-2 button-header"
                >
                    <div
                        @click="getShift()"
                        class="px-md-4 btn-atas px-2 py-1 glassMorph fw-bold text-white"
                    >
                        <i
                            v-if="dayNight == 'Siang'"
                            class="bi bi-cloud-sun-fill me-2 fs-4 fade-in"
                        ></i>
                        <i
                            v-else
                            class="bi bi-cloud-moon-fill me-2 fs-4 fade-in"
                        ></i>
                        {{ formatDatePanjang(TanggalSekarang) }}
                    </div>
                    <button
                        @click="AssignOvertime = true"
                        class="btn btn-atas btn-primary py-2 px-2 shiningEffect d-flex justify-content-center align-items-center"
                    >
                        <i
                            class="bi bi-plus-circle me-2 d-flex justify-content-center align-items-center"
                        ></i>
                        Ajukan Lembur
                    </button>
                </div>
            </div>
            <!-- Tools -->
            <div
                class="search-filter-section p-4 bg-white rounded-4 my-3 shadow-unix2 fade-in"
            >
                <div class="search-container">
                    <i
                        class="bi bi-search search-icon d-flex justify-content-center align-items-center"
                    ></i>
                    <input
                        type="text"
                        class="search-input"
                        placeholder="Cari nama karyawan..."
                        v-model="searchQuery"
                    />
                    <div class="search-results-count" v-if="searchQuery">
                        {{ totalData }} hasil
                    </div>
                </div>
                <div class="filters-container">
                    <div v-if="!filterDate" class="filter-dropdown d-flex">
                        <button
                            class="filter-btn text-center position-relative"
                            @click="openDialogFilter()"
                        >
                            <i
                                class="bi bi-calendar d-flex justify-content-center align-items-center"
                            ></i>
                            <span class="sm-nav">
                                <span>SEMUA</span>
                            </span>
                            <!-- <el-date-picker
                                v-model="value2"
                                type="date"
                                ref="myDatePicker"
                                :size="size"
                                class="position-absolute hidden-datepicker"
                                style="left: 10rem"
                            /> -->
                        </button>
                    </div>
                    <div v-else class="filter-dropdown h-100 d-flex">
                        <button
                            class="filter-btn text-center position-relative m-0"
                            @click="openDialogFilter()"
                        >
                            <i
                                class="bi bi-calendar d-flex justify-content-center align-items-center"
                            ></i>
                            <span
                                class="sm-nav d-flex justify-content-center align-items-center"
                            >
                                <span class="me-2"
                                    >{{ formatDate(filterDate) }}
                                </span>
                                <button
                                    class="modal-close"
                                    style="
                                        width: 25px !important;
                                        height: 25px !important;
                                        border-radius: 5px;
                                    "
                                    @click.stop="handleCancelFilter()"
                                >
                                    <i
                                        class="bi bi-x-lg d-flex justify-content-center align-items-center"
                                    ></i>
                                </button>
                            </span>
                        </button>
                    </div>
                    <div class="filter-dropdowns">
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

            <!-- Table -->
            <div
                v-if="viewMode === 'table'"
                class="table-container rounded-4 shadow-unix2 fade-in"
            >
                <div class="table-wrapper">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th class="sticky-column employee-header">
                                    <i class="bi bi-columns me-2"></i>
                                    Nomor Transaksi
                                    <small class="employee-count">{{
                                        "(" + totalData + ")"
                                    }}</small>
                                </th>
                                <th>
                                    <div class="header-info">
                                        <div class="day-name">
                                            Tanggal Transaksi
                                        </div>
                                    </div>
                                    <div class="day-indicator"></div>
                                </th>
                                <th>
                                    <div class="header-info">
                                        <div class="day-name">
                                            Jam Transaksi
                                        </div>
                                    </div>
                                    <div class="day-indicator"></div>
                                </th>
                                <th>
                                    <div class="header-info">
                                        <div class="day-name">
                                            User Transaksi
                                        </div>
                                    </div>
                                    <div class="day-indicator"></div>
                                </th>
                                <th>
                                    <div class="header-info">
                                        <div class="day-name">Action</div>
                                    </div>
                                    <div class="day-indicator"></div>
                                </th>
                            </tr>
                        </thead>
                        <!-- error -->
                        <tbody v-if="loading">
                            <tr>
                                <td colspan="5" class="">
                                    <div
                                        class="empty-state d-flex flex-column justify-content-center align-items-center"
                                    >
                                        <div class="loading-animation">
                                            <DotLottieVue
                                                style="
                                                    height: 150px;
                                                    width: 150px;
                                                "
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
                                        <p class="loading-text">
                                            Memuat data shift karyawan...
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else-if="!loading && totalData == 0">
                            <tr>
                                <td colspan="5" class="">
                                    <div
                                        class="empty-state d-flex flex-column justify-content-center align-items-center"
                                    >
                                        <div class="empty-icon">
                                            <DotLottieVue
                                                style="
                                                    height: 250px;
                                                    width: 250px;
                                                "
                                                autoplay
                                                loop
                                                src="/animation/empty-data.json"
                                            />
                                        </div>
                                        <h3>Tidak ada Transaksi ditemukan</h3>
                                        <!-- <p>Coba ubah filter atau kata kunci pencarian Anda</p> -->
                                        <button
                                            class="btn-modern btn-primary"
                                            @click="clearFilters"
                                        >
                                            <i
                                                class="bi bi-arrow-clockwise me-2"
                                            ></i>
                                            Reset Filter
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr
                                v-for="(item, idx) in data"
                                :key="idx"
                                class="employee-row"
                                :class="{ highlight: item.hasConflict }"
                                @click="showDetailsMethod(item.No_Transaksi)"
                            >
                                <td class="sticky-column employee-cell me-1">
                                    <div class="employee-info">
                                        <div class="employee-avatar">
                                            {{ getInitials(item.No_Transaksi) }}
                                        </div>
                                        <div class="employee-details">
                                            <div class="employee-name">
                                                {{ item.No_Transaksi }}
                                            </div>
                                            <!-- <div class="employee-id">
                                                K.Perusahaan:
                                                {{ item.Kode_Perusahaan }}
                                            </div> -->
                                        </div>
                                    </div>
                                </td>
                                <td class="shift-cell">
                                    <div class="shift-container shift-default">
                                        <div class="shift-content">
                                            <span class="shift-name">
                                                {{ formatDate(item.Tanggal) }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="shift-cell">
                                    <div class="shift-container">
                                        <div class="shift-content">
                                            <span class="shift-name">
                                                {{
                                                    item.Jam?.split(":")
                                                        .slice(0, 2)
                                                        .join(":")
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="shift-cell">
                                    <div class="shift-container">
                                        <div class="shift-content">
                                            <span class="shift-name">
                                                {{ item.Nama }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td class="shift-cell">
                                    <div class="shift-container">
                                        <button
                                            :disabled="
                                                !disabledButton2(
                                                    item.Tanggal_Assign,
                                                )
                                            "
                                            @click.stop="
                                                deleteAlertButton(
                                                    item.No_Transaksi,
                                                )
                                            "
                                            class="btn btn-sm btn-secondary shiningEffect text-white border-white bg-danger"
                                        >
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination-container p-3" v-if="totalPages > 1">
                    <button
                        class="page-btn"
                        @click="getData(prevLink)"
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
                        @click="getData(nextLink)"
                        :disabled="currentPage === totalPages"
                    >
                        <i
                            class="bi bi-chevron-right d-flex justify-content-center align-items-center"
                        ></i>
                    </button>
                </div>
            </div>

            <!-- Card View -->
            <div v-else class="card-view fade-in">
                <div
                    v-if="loading"
                    class="empty-state d-flex flex-column justify-content-center align-items-center"
                >
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
                <div v-else-if="!loading && totalData != 0" class="cards-grid">
                    <div
                        v-for="(employee, empIndex) in data"
                        :key="empIndex"
                        @click="showDetailsMethod(employee.No_Transaksi)"
                        class="employee-card"
                    >
                        <div class="card-header">
                            <div class="">
                                <div
                                    class="d-flex justify-content-start align-items-start gap-2"
                                >
                                    <div class="employee-avatar large">
                                        {{ getInitials(employee.No_Transaksi) }}
                                    </div>
                                    <div class="">
                                        <h3 class="m-0">
                                            {{ employee.No_Transaksi }}
                                        </h3>
                                        <p>
                                            {{ formatDate(employee.Tanggal) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-shifts">{{ employee.Nama }}</div>
                    </div>
                </div>

                <div
                    v-else
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

                <div class="pagination-container p-3" v-if="totalPages > 1">
                    <button
                        class="page-btn"
                        @click="getData(prevLink)"
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
                        @click="getData(nextLink)"
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
    <!--  Assign Shift Modal -->
    <div
        class="modal-overlay"
        :class="{ show: AssignOvertime }"
        @click="closeAssignModal"
    >
        <div class="modal-container assign-modal" @click.stop>
            <div class="modal-header">
                <div class="header-title-section">
                    <h3>
                        <i
                            class="bi bi-calendar-plus me-3 d-flex justify-content-center align-items-center"
                        ></i>
                        Penugasan Lembur
                    </h3>
                    <p class="header-subtitle">
                        Atur jadwal lembur untuk karyawan
                    </p>
                </div>
                <button class="modal-close" @click="closeAssignModal">
                    <i
                        class="bi bi-x-lg d-flex justify-content-center align-items-center"
                    ></i>
                </button>
            </div>

            <div class="modal-body">
                <div v-if="assignStep === 1" class="step-content">
                    <div class="form-section mt-3">
                        <h4 class="section-title">
                            <i
                                class="bi bi-calendar me-2 d-flex justify-content-center align-items-center"
                            ></i>
                            Pilih tanggal hari kerja
                        </h4>
                        <div class="date-selection mb-5 px-md-3">
                            <div class="form-group">
                                <div class="date-picker-grid">
                                    <div
                                        v-for="(day, idx) in tanggal"
                                        :key="idx"
                                        class="date-picker-item"
                                        :class="{
                                            selected:
                                                selectedAssignDates === day,
                                            today: isToday(day),
                                        }"
                                        @click="selectedAssignDates = day"
                                    >
                                        <div class="date-day">
                                            {{ getDayName(day) }}
                                        </div>
                                        <div class="date-number">
                                            {{ formatDateShort(day) }}
                                        </div>
                                        <div
                                            class="date-check"
                                            v-if="selectedAssignDates === day"
                                        >
                                            <i
                                                class="bi bi-check-circle-fill"
                                            ></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- <h4 class="section-title">
                            <i
                                class="bi bi-calendar me-2 d-flex justify-content-center align-items-center"
                            ></i>
                            Pilih Shift
                        </h4> -->
                        <!--
                        <div class="search-container assign-search">
                            <i
                                class="bi bi-search search-icon d-flex justify-content-center align-items-center"
                            ></i>
                            <input
                                type="text"
                                class="search-input"
                                placeholder="Cari Shift..."
                                v-model="assignSearchShift"
                            />
                        </div>

                        <div class="form-group">
                            <div class="employee-selection-list p-3">
                                <div class="shift-options assign-shift-options">
                                    <div
                                        v-if="filteredShift.length == 0"
                                        class="text-center w-100"
                                    >
                                        Tidak Ada Shift
                                    </div>
                                    <div
                                        v-for="shift in filteredShift"
                                        :key="shift"
                                        class="shift-option assign-shift-option"
                                        :class="{
                                            selected:
                                                selectedAssignShift ===
                                                shift.ID_Shift,
                                        }"
                                        @click="
                                            selectedAssignShift = shift.ID_Shift
                                        "
                                    >
                                        <div
                                            class="shift-badge large"
                                            :class="
                                                getShiftClass(shift.ID_Shift)
                                            "
                                        >
                                            {{ shift.Nama }}
                                        </div>
                                        <div class="shift-details">
                                            <div class="shift-time">
                                                {{ shift.time }}
                                            </div>
                                            <div class="shift-description">
                                                {{ shift.deksripsi }}
                                            </div>
                                        </div>
                                        <div class="shift-radio">
                                            <i
                                                class="bi bi-record-circle-fill"
                                                style="transition: all 3s ease"
                                                :class="
                                                    shift.ID_Shift
                                                        ? 'opa-100'
                                                        : 'opa-0'
                                                "
                                                v-if="
                                                    selectedAssignShift ===
                                                    shift.ID_Shift
                                                "
                                            ></i>
                                            <i class="bi bi-circle" v-else></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div> -->

                        <!-- <div class="selection-actions">
                            <button
                                class="btn-link"
                                @click="selectedAssignShift = []"
                                v-if="selectedAssignShift.length > 0"
                            >
                                <i class="bi bi-x-circle me-1"></i>
                                Hapus Pilihan
                            </button>
                        </div> -->

                        <!-- <div
                            class="selected-count"
                            v-if="selectedAssignShift.length > 0"
                        >
                            <i
                                class="bi bi-info-circle me-2 d-flex justify-content-center align-items-center"
                            ></i>
                            {{ selectedAssignShift.length }} Shift dipilih
                        </div> -->
                    </div>
                </div>

                <!-- <div v-if="assignStep === 2" class="step-content">
                    <div class="form-section">
                        <h4 class="section-title">
                            <i
                                class="bi bi-gear me-2 d-flex justify-content-center align-items-center"
                            ></i>
                            Pilih Shift
                        </h4>

                        <div class="form-shift">
                            <div class="shift-options assign-shift-options">
                                <div
                                    v-if="loading"
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
                                    v-if="!loading && shiftList.length == 0"
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
                                    v-for="shift in shiftList"
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
                                            :class="
                                                getShiftClass(shift.ID_Shift)
                                            "
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
                </div> -->

                <div v-if="assignStep === 2" class="step-content">
                    <div class="form-section">
                        <h4 class="section-title">
                            <i
                                class="bi bi-calendar-event me-2 d-flex justify-content-center align-items-center"
                            ></i>
                            Pilih Tanggal & Periode
                        </h4>

                        <!-- Date Range Selection -->
                        <label class="form-label"
                            >Pilih Waktu Akhir Lembur:</label
                        >
                        <!-- <div
                            class="user2 custom-time-picker my-4"
                            style="width: 100%"
                        >
                            <el-time-picker
                                @change="log()"
                                v-model="assignTime"
                                :disabled-hours="disabledHours"
                                :disabled-minutes="disabledMinutes"
                                :disabled-seconds="disabledSeconds"
                                placeholder="Pilih waktu demo"
                                format="HH:mm"
                                value-format="HH:mm"
                            >
                            </el-time-picker>
                        </div> -->
                        <input
                            type="time"
                            class="form-control"
                            v-model="assignTime"
                        />
                        <!-- <input
                            :type="
                                startTimeShift && endTimeShift ? 'time' : 'text'
                            "
                            :disabled="!startTimeShift && !endTimeShift"
                            class="form-control"
                            v-model="assignTime"
                            :min="startTimeShift"
                            :max="endTimeShift"
                            :placeholder="
                                !startTimeShift && !endTimeShift
                                    ? 'Belum Ada Jam Lembur'
                                    : ''
                            "
                            ref="timeAssign"
                        /> -->

                        <label class="form-label">Alasan Lembur:</label>
                        <div
                            class="input-group date"
                            id="timepicker"
                            data-target-input="nearest"
                        >
                            <textarea
                                rows="7"
                                name=""
                                class="form-control"
                                id=""
                                v-model="assignReason"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <div v-if="assignStep === 3" class="step-content">
                    <div class="form-section">
                        <h4 class="section-title">
                            <i
                                class="bi bi-people me-2 d-flex justify-content-center align-items-center"
                            ></i>
                            Pilih Karyawan
                        </h4>

                        <div class="search-container assign-search">
                            <i
                                class="bi bi-search search-icon d-flex justify-content-center align-items-center"
                            ></i>
                            <input
                                :disabled="loading"
                                type="text"
                                class="search-input"
                                placeholder="Cari nama karyawan..."
                                v-model="assignSearchQuery"
                            />
                        </div>

                        <div
                            v-if="loading"
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

                        <div v-else class="employee-selection-list fade-in">
                            <div
                                v-if="
                                    !loading &&
                                    filteredAssignEmployees.length === 0
                                "
                                class="fade-in py-2 d-flex flex-column justify-content-center align-items-center"
                            >
                                <div class="empty-icon">
                                    <DotLottieVue
                                        style="height: 170px; width: 180px"
                                        autoplay
                                        loop
                                        src="/animation/empty-data.json"
                                    />
                                </div>
                                <h5>Tidak ada karyawan ditemukan</h5>
                                <!-- <p>Coba ubah filter atau kata kunci pencarian Anda</p> -->
                                <button
                                    class="btn-modern btn-primary"
                                    @click="clearFiltersAssign()"
                                >
                                    <i class="bi bi-arrow-clockwise me-2"></i>
                                    Reset Filter
                                </button>
                            </div>
                            <div
                                v-else
                                v-for="employee in filteredAssignEmployees"
                                :key="employee.userId"
                                class="employee-selection-item"
                                :class="
                                    getEmployeeData(employee.userId)
                                        ? 'selected'
                                        : ' '
                                "
                            >
                                <div
                                    :class="
                                        !isDisabled(employee)
                                            ? ' '
                                            : 'disabled-card'
                                    "
                                    style="min-height: 4.7rem"
                                    class="row gap-0 employee-selection-card align-items-center justify-content-between"
                                >
                                    <div
                                        @click="
                                            toggleEmployeeSelection(
                                                employee.userId,
                                            )
                                        "
                                        class="col-6 col-md-5 px-0 ps-1 px-md-3 d-flex align-items-center gap-2"
                                    >
                                        <div
                                            style="min-width: 18px"
                                            class="employee-checkbox me-1"
                                        >
                                            <i
                                                class="bi bi-check d-flex justify-content-center align-items-center"
                                                v-if="
                                                    getEmployeeData(
                                                        employee.userId,
                                                    )
                                                "
                                            ></i>
                                        </div>
                                        <div
                                            class="employee-avatar d-md-flex d-none"
                                        >
                                            {{ getInitials(employee.name) }}
                                        </div>
                                        <div class="employee-details">
                                            <div
                                                v-tooltip="employee.name"
                                                class="flex-column-reverse d-flex d-md-none employee-name m-0 p-0 d-flex align-items-start justify-content-center justify-content-md-start"
                                            >
                                                {{
                                                    truncateText(
                                                        employee.name,
                                                        6,
                                                    )
                                                }}
                                                <!-- <div
                                                    v-if="
                                                        employee.lemburPernah ==
                                                        'TRUE'
                                                    "
                                                    style="
                                                        font-size: 10px;
                                                        max-height: fit-content;
                                                    "
                                                    class="fw-bold badge bg-danger rounded-5 m-0"
                                                >
                                                    Sudah Lembur
                                                </div>
                                                <div
                                                    v-if="
                                                        employee.JamComparison ==
                                                        'False'
                                                    "
                                                    style="
                                                        font-size: 10px;
                                                        max-height: fit-content;
                                                    "
                                                    class="fw-bold badge bg-danger rounded-5 m-0"
                                                >
                                                    Sudah Lembur
                                                </div> -->
                                            </div>
                                            <div
                                                v-tooltip="employee.name"
                                                class="d-none d-md-flex flex-column-reverse employee-name m-0 p-0 d-flex align-items-start justify-content-center justify-content-md-start"
                                            >
                                                {{
                                                    truncateText(
                                                        employee.name,
                                                        10,
                                                    )
                                                }}
                                                <div
                                                    v-if="
                                                        employee.lemburPernah ==
                                                        'TRUE'
                                                    "
                                                    style="
                                                        font-size: 10px;
                                                        max-height: fit-content;
                                                    "
                                                    class="fw-bold badge bg-danger rounded-5 m-0"
                                                >
                                                    Sudah Lembur
                                                </div>
                                            </div>
                                            <div
                                                v-tooltip="employee.Divisi"
                                                class="employee-id m-0 p-0 bg-primary px-2 rounded-5 text-white"
                                            >
                                                {{
                                                    truncateText(
                                                        employee.Divisi,
                                                        10,
                                                    )
                                                }}
                                            </div>

                                            <div
                                                class="employee-id m-0 p-0 fw-bold"
                                            >
                                                {{
                                                    employee.Jam_Masuk?.split(
                                                        ":",
                                                    )
                                                        .slice(0, 2)
                                                        .join(":") +
                                                    " - " +
                                                    employee.Jam_Keluar?.split(
                                                        ":",
                                                    )
                                                        .slice(0, 2)
                                                        .join(":")
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                    <!-- collapse -->
                                    <div
                                        v-if="!this.isDisabled(employee)"
                                        class="col-6 px-0 col-md-7 d-flex flex-column flex-md-row gap-2"
                                    >
                                        <div
                                            class="d-flex"
                                            style="height: max-content"
                                        >
                                            <span
                                                style="
                                                    border-radius: 7px 0 0 7px;
                                                    margin-right: -10px;
                                                    z-index: 100;
                                                "
                                                :style="
                                                    getEmployeeData(
                                                        employee.userId,
                                                    )
                                                        ? ' '
                                                        : ' background: #a1aede !important;'
                                                "
                                                class="shiningEffect px-2 py-0 bg-primary d-flex justify-content-center align-items-center"
                                                ><i
                                                    class="bi bi-clock text-white d-flex justify-content-center align-items-center"
                                                ></i
                                            ></span>
                                            <!-- <div
                                                v-if="
                                                    getEmployeeData(
                                                        employee.userId
                                                    )
                                                "
                                                class="custom-time-picker"
                                            >
                                                <el-time-picker
                                                    :disabled="
                                                        !getEmployeeData(
                                                            employee.userId
                                                        )
                                                    "
                                                    v-model="
                                                        getEmployeeData(
                                                            employee.userId
                                                        ).waktu
                                                    "
                                                    :disabled-hours="
                                                        () =>
                                                            disabledHoursModal(
                                                                employee.userId
                                                            )
                                                    "
                                                    placeholder="waktu"
                                                    format="HH:mm"
                                                    value-format="HH:mm"
                                                >
                                                </el-time-picker>
                                            </div> -->
                                            <!-- disabled -->
                                            <!-- <div
                                                v-else
                                                class="custom-time-picker"
                                            >
                                                <el-time-picker
                                                    disabled
                                                    :disabled-hours="
                                                        disabledHours
                                                    "
                                                    :disabled-minutes="
                                                        disabledMinutes
                                                    "
                                                    :disabled-seconds="
                                                        disabledSeconds
                                                    "
                                                    placeholder="Waktu"
                                                    format="HH:mm"
                                                    value-format="HH:mm"
                                                >
                                                </el-time-picker>
                                            </div> -->

                                            <!-- timePicker Baru -->

                                            <input
                                                v-if="
                                                    getEmployeeData(
                                                        employee.userId,
                                                    )
                                                "
                                                :disabled="
                                                    !getEmployeeData(
                                                        employee.userId,
                                                    )
                                                "
                                                v-model="
                                                    getEmployeeData(
                                                        employee.userId,
                                                    ).waktu
                                                "
                                                :ref="
                                                    (el) => {
                                                        if (el)
                                                            timeInputRefs[
                                                                employee.userId
                                                            ] = el;
                                                    }
                                                "
                                                @input="
                                                    handleValidation(
                                                        employee.userId,
                                                    )
                                                "
                                                :min="employee.Jam_Keluar"
                                                :max="
                                                    subtractHours(
                                                        employee.Jam_Masuk,
                                                        2,
                                                    )
                                                "
                                                :class="
                                                    !getEmployeeData(
                                                        employee.userId,
                                                    ).isValid
                                                        ? 'is-invalid'
                                                        : ''
                                                "
                                                required
                                                type="time"
                                                placeholder="Masukan alasan"
                                                class="form-control"
                                                style="height: 2rem"
                                            />
                                            <!-- disabled -->
                                            <input
                                                v-else
                                                disabled
                                                type="time"
                                                placeholder="Masukan alasan"
                                                class="form-control"
                                                style="height: 2rem"
                                            />
                                        </div>
                                        <div class="d-flex">
                                            <span
                                                style="
                                                    border-radius: 7px 0 0 7px;
                                                    margin-right: -10px;
                                                    z-index: 100;
                                                "
                                                :style="
                                                    getEmployeeData(
                                                        employee.userId,
                                                    )
                                                        ? ' '
                                                        : ' background: #a1aede !important;'
                                                "
                                                class="shiningEffect px-2 py-0 bg-primary d-flex justify-content-center align-items-center"
                                                ><i
                                                    class="bi bi-calendar text-white d-flex justify-content-center align-items-center"
                                                ></i
                                            ></span>
                                            <input
                                                v-if="
                                                    getEmployeeData(
                                                        employee.userId,
                                                    )
                                                "
                                                :disabled="
                                                    !getEmployeeData(
                                                        employee.userId,
                                                    )
                                                "
                                                v-model="
                                                    getEmployeeData(
                                                        employee.userId,
                                                    ).reason
                                                "
                                                type="text"
                                                placeholder="Masukan alasan"
                                                class="form-control"
                                                style="height: 2rem"
                                            />
                                            <!-- disabled -->
                                            <input
                                                v-else
                                                disabled
                                                type="text"
                                                placeholder="Masukan alasan"
                                                class="form-control"
                                                style="height: 2rem"
                                            />
                                        </div>
                                    </div>

                                    <div
                                        v-else
                                        class="text-start col-6 px-0 col-md-7 d-flex align-items-center"
                                    >
                                        <span
                                            style="font-size: 0.7rem"
                                            class="bg-danger text-white px-2 py-1 d-flex align-items-center rounded-3"
                                        >
                                            <i
                                                class="bi bi-exclamation-triangle-fill me-1 d-flex justify-content-center align-items-center"
                                            ></i>
                                            {{
                                                penyebabDisabled(
                                                    employee.JamComparison,
                                                    employee.lemburPernah,
                                                    isTimeAfter(
                                                        employee.Jam_Masuk,

                                                        employee.Jam_Keluar,
                                                        employee.Nama_Shift,
                                                    ),
                                                    employee.StatusHari,
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="selection-actions">
                            <button
                                :disabled="loading"
                                :class="loading ? 'disabled-card' : ' '"
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

                        <div class="selected-count">
                            <i
                                class="bi bi-info-circle me-2 d-flex justify-content-center align-items-center"
                            ></i>
                            {{ selectedAssignEmployees.length }} karyawan
                            dipilih
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
                    >
                        Lanjut
                        <i
                            class="bi bi-arrow-right ms-2 d-flex justify-content-center align-items-center"
                        ></i>
                    </button>

                    <button
                        v-if="assignStep === 3"
                        class="btn-modern btn-success"
                        @click="
                            {
                                {
                                    storeAlertButton();
                                }
                            }
                        "
                    >
                        <div
                            v-if="loading"
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
                                class="bi bi-check-lg me-2 d-flex justify-content-center align-items-center"
                            ></i>
                            Simpan
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal element -->
    <div
        class="modal-overlay"
        :class="{ show: showEditOvertime }"
        @click="closeEditModal"
    >
        <div class="modal-container" @click.stop>
            <div class="modal-header">
                <h3>Edit Overtime</h3>
                <button class="modal-close" @click="closeEditModal">
                    <i
                        class="bi bi-x-lg d-flex justify-content-center align-items-center"
                    ></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Edit Karyawan:</label>
                    <p class="employee-name">{{ selectedTransaction?.name }}</p>
                </div>
                <div class="form-group mt-4">
                    <div class="row">
                        <div class="col-4 position-relative">
                            <label
                                for="alasan"
                                class="form-floating"
                                style="z-index: 1001 !important"
                            >
                                Waktu Selesai</label
                            >

                            <div
                                class="user2 custom-time-picker my-2"
                                style="width: 20%"
                            >
                                <el-time-picker
                                    v-if="
                                        getEmployeeData(
                                            selectedTransaction.userId,
                                        )
                                    "
                                    v-model="
                                        getEmployeeData(
                                            selectedTransaction.userId,
                                        ).waktu
                                    "
                                    :disabled-hours="disabledHours"
                                    :disabled-minutes="disabledMinutes"
                                    :disabled-seconds="disabledSeconds"
                                    placeholder="Pilih waktu demo"
                                    format="HH:mm"
                                    value-format="HH:mm"
                                >
                                </el-time-picker>
                            </div>
                        </div>
                        <div class="col-8 position-relative">
                            <label for="alasan" class="form-floating">
                                Alasan
                            </label>
                            <input
                                v-if="
                                    getEmployeeData(selectedTransaction.userId)
                                "
                                v-model="
                                    getEmployeeData(selectedTransaction.userId)
                                        .reason
                                "
                                type="text"
                                class="form-control"
                            />
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button
                    class="btn-modern btn-secondary"
                    @click="closeEditModal"
                >
                    Batal
                </button>
                <button class="btn-modern btn-primary" @click="saveEditModal()">
                    <i class="bi bi-check-lg me-2"></i>
                    Simpan
                </button>
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
                    :disabled="loading"
                    class="btn-modern btn-success"
                    @click="yesModal"
                >
                    <div
                        v-if="loading"
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

    <!-- DetailsModal -->
    <div
        class="modal-overlay"
        :class="{ show: detailShow }"
        @click="() => closeDetailsMethod()"
    >
        <div class="modal-container details" @click.stop>
            <div class="modal-header details">
                <div class="d-flex justify-content-center align-items-center">
                    <i
                        class="bi bi-bar-chart-fill fs-5 me-2 d-flex justify-content-center align-items-center"
                    ></i>
                    <h4 class="m-0">{{ titleDetails }} Details</h4>
                </div>
                <button class="modal-close" @click="() => closeDetailsMethod()">
                    <i
                        class="bi bi-x-lg d-flex justify-content-center align-items-center"
                    ></i>
                </button>
            </div>
            <!-- error -->

            <div
                v-if="loading"
                class="modal-body details d-flex flex-column justify-content-center align-items-center"
            >
                <DotLottieVue
                    style="height: 150px; width: 150px"
                    autoplay
                    loop
                    src="/animation/loading2.lottie"
                />
                <span class="fw-semibold fs-5">Loading...</span>
            </div>
            <div v-else class="modal-body details">
                <div class="table-container shadow-none">
                    <div class="table-wrapper">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th class="sticky-column employee-header">
                                        <i class="bi bi-people me-2"></i>
                                        Nama User
                                        <small class="employee-count">{{
                                            "(" +
                                            filteredUserTransaksi.length +
                                            ")"
                                        }}</small>
                                    </th>
                                    <th>
                                        <div class="header-info">
                                            <div class="day-name">
                                                Tanggal Lembur
                                            </div>
                                        </div>
                                        <div class="day-indicator"></div>
                                    </th>
                                    <th>
                                        <div class="header-info">
                                            <div class="day-name">
                                                Jam Lembur
                                            </div>
                                        </div>
                                        <div class="day-indicator"></div>
                                    </th>
                                    <th>
                                        <div class="header-info">
                                            <div class="day-name">
                                                Durasi Lembur
                                            </div>
                                        </div>
                                        <div class="day-indicator"></div>
                                    </th>
                                    <th>
                                        <div class="header-info">
                                            <div class="day-name">Alasan</div>
                                        </div>
                                        <div class="day-indicator"></div>
                                    </th>
                                    <th>
                                        <div class="header-info">
                                            <div class="day-name">
                                                Status Lembur(CheckOut)
                                            </div>
                                        </div>
                                        <div class="day-indicator"></div>
                                    </th>
                                    <th>
                                        <div class="header-info">
                                            <div class="day-name">Action</div>
                                        </div>
                                        <div class="day-indicator"></div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                v-if="!loading && detailTransaksi.length === 0"
                            >
                                <tr>
                                    <td colspan="6">
                                        <div
                                            class="empty-state d-flex flex-column justify-content-center align-items-center"
                                        >
                                            <div class="empty-icon">
                                                <DotLottieVue
                                                    style="
                                                        height: 250px;
                                                        width: 250px;
                                                    "
                                                    autoplay
                                                    loop
                                                    src="/animation/empty-data.json"
                                                />
                                            </div>
                                            <h3>
                                                Tidak ada karyawan ditemukan
                                            </h3>
                                            <!-- <p>Coba ubah filter atau kata kunci pencarian Anda</p> -->
                                            <button
                                                class="btn-modern btn-primary"
                                                @click="clearFilters"
                                            >
                                                <i
                                                    class="bi bi-arrow-clockwise me-2"
                                                ></i>
                                                Reset Filter
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            <tbody v-else>
                                <tr
                                    class="employee-row"
                                    v-for="(
                                        item, idx
                                    ) in paginatedUserTransaksi"
                                    :key="idx"
                                    :class="highlight"
                                >
                                    <td
                                        class="sticky-column employee-cell me-1"
                                    >
                                        <div class="employee-info">
                                            <div class="employee-avatar">
                                                {{ getInitials(item.name) }}
                                            </div>
                                            <div class="employee-details">
                                                <div class="employee-name">
                                                    {{ item.name }}
                                                </div>
                                                <div class="employee-id"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="shift-cell">
                                        <div
                                            class="shift-container shift-default"
                                        >
                                            <div class="shift-content">
                                                {{
                                                    formatDate(
                                                        item.start_time,
                                                    ) ==
                                                    formatDate(item.end_time)
                                                        ? formatDate(
                                                              item.start_time,
                                                          )
                                                        : formatDate(
                                                              item.start_time,
                                                          ) +
                                                          " - " +
                                                          formatDate(
                                                              item.end_time,
                                                          )
                                                }}
                                                <span class="shift-name">
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="shift-cell">
                                        <div class="shift-container">
                                            <div class="shift-content">
                                                {{
                                                    formatTime(
                                                        item.start_time,
                                                        item.end_time,
                                                    )
                                                }}
                                                <span class="shift-name">
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="shift-cell">
                                        <div class="shift-container">
                                            <div class="shift-content">
                                                {{
                                                    formatDuration(
                                                        item.start_time,
                                                        item.end_time,
                                                    )
                                                }}
                                                <span class="shift-name">
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="shift-cell">
                                        <div class="shift-container">
                                            <div class="shift-content">
                                                {{
                                                    truncateText(
                                                        item.reason,
                                                        20,
                                                    )
                                                }}
                                                <span class="shift-name">
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="shift-cell">
                                        <div class="shift-container">
                                            <div class="shift-content">
                                                {{ item.CHECKOUT }}
                                                <span class="shift-name">
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="shift-cell">
                                        <div class="shift-container">
                                            <!-- <div
                                            @click="
                                                showEditModal(item.No_Transaksi)
                                            "
                                            class="btn btn-sm btn-secondary shiningEffect text-white border-white btn-success"
                                        >
                                        <i class="bi bi-pen"></i>
                                        </div> -->
                                            <button
                                                :disabled="
                                                    !disabledButton2(
                                                        item.end_time,
                                                    )
                                                "
                                                @click="
                                                    deleteUserButton(
                                                        item.Urut_Oto,
                                                    )
                                                "
                                                class="btn btn-sm btn-secondary shiningEffect text-white border-white bg-danger"
                                            >
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <!-- Pagination -->
                <!-- <div class="pagination-container" v-if="totalPageUser > 1">
                    <button
                        class="page-btn"
                        @click="prevUser"
                        :disabled="currentPage === 1"
                    >
                        <i
                            class="bi bi-chevron-left d-flex justify-content-center align-items-center"
                        ></i>
                    </button>
                    <span class="page-info">
                        {{ currentPageUser }} / {{ totalPageUser }}
                    </span>
                    <button
                        class="page-btn"
                        @click="nextUser"
                        :disabled="currentPageUser === totalPageUser"
                    >
                        <i
                            class="bi bi-chevron-right d-flex justify-content-center align-items-center"
                        ></i>
                    </button>
                </div> -->
                <button
                    class="btn-modern btn-secondary"
                    @click="() => closeDetailsMethod()"
                >
                    Close
                </button>
                <!-- <button class="btn-modern btn-success" @click="yesModal">
                    <div
                        v-if="loading"
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

                    </div>
                </button> -->
            </div>
        </div>
    </div>

    <!-- Date Modal -->
    <div>
        <div class=""></div>
    </div>

    <!-- Calendar Export -->
    <el-dialog
        v-model="dialogVisible"
        title="Pilih Tanggal Mulai dan Selesai"
        width="480px"
        align-center
        :z-index="1048"
        style="z-index: 1048 !important"
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

    <!-- Calendar Filter -->
    <el-dialog
        v-model="dialogFilter"
        title="Pilih Tanggal Filter"
        width="480px"
        align-center
        :z-index="1048"
    >
        <el-calendar v-model="calendarDateFilter">
            <template #date-cell="{ data }">
                <div
                    class="cell-wrapper"
                    :class="getCellClass(data)"
                    @click="handleDateClickFilter(data)"
                >
                    <div class="date-cell">
                        {{ data.day.split("-")[2] }}
                    </div>
                </div>
            </template>
        </el-calendar>

        <template #footer>
            <div class="dialog-footer">
                <el-button @click="handleCancelFilter">Batal</el-button>
                <el-button
                    class="shiningEffect btn-primary"
                    :disabled="!tempStartDateFilter"
                    @click="getDateFilter"
                >
                    Pilih Tanggal
                </el-button>
            </div>
        </template>
    </el-dialog>

    <!--
        Blok "History Detail Modal" DIHAPUS: markup yang tidak bisa
        dirender. Ia memakai <Modal> dan <Badge> yang tak pernah diimpor,
        dan bergantung pada showHistoryDetail, selectedHistory, serta
        closeHistoryDetail yang nol kemunculan di <script>.

        Vue tetap me-resolve kedua komponen itu di awal fungsi render,
        di luar v-if, sehingga tiap muat halaman melempar 18 peringatan
        konsol yang menutupi galat sungguhan.
    -->
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
    ElNotification,
    ElButton,
} from "element-plus";
import "element-plus/dist/index.css";
import { debounce } from "lodash";

export default {
    components: {
        ElTimePicker,
        DotLottieVue,
        ElDatePicker,
        ElDialog,
        ElCalendar,
        ElNotification,
        ElButton,
    },
    props: {
        title: String,
        // data: Array,
        tanggal: Array,
        // userChoose: Array,
        // shiftList SENGAJA tidak dideklarasikan sebagai prop: komponen ini
        // punya data.shiftList dengan nama sama, dan di Vue data menang atas
        // props. Nilai dari server karena itu tak pernah terbaca — daftarnya
        // selalu diambil ulang lewat API. Payload-nya ikut dibuang di
        // OvertimeController supaya tidak jatuh jadi atribut DOM.
        availableShifts: Array,
    },
    data() {
        return {
            data: [],
            totalData: 0,
            tipeFilter: null,
            tanggalFilter: null,
            nextLink: null,
            prevLink: null,
            totalPages: 0,

            serverTime: null,

            timeInputRefs: {},
            shiftList: [],
            startTimeShift: null,
            endTimeShift: null,

            filterDate: null,

            isDekstop: false,

            // export Excel
            tanggalExportStart: "",
            tanggalExportEnd: "",
            dataExport: [],

            dialogVisible: false,
            selectedDate: [], // Ini akan menyimpan [startDate, endDate]
            tempStartDate: null,
            tempEndDate: null,
            calendarDate: new Date(),

            dialogFilter: false,
            selectedDateFilter: "", // Ini akan menyimpan [startDate, endDate]
            tempStartDateFilter: null,

            calendarDateFilter: new Date(),

            // Tanggal Sekarang
            TanggalSekarang: new Date(),
            //detailsModal
            detailShow: false,
            titleDetails: "",
            detailTransaksi: [],
            searchUserTransaksi: "",
            currentPageUser: 1,
            itemsPerPageUser: 10,

            // alert modal
            titleAlertModal: "",
            buttonAlertModal: "",
            showAlertModal: false,
            param1: "",
            bodyAlert: "",
            yesModal: "",
            //loading
            loading: false,
            userChoose: [],

            // edit modal data
            showEditOvertime: false,
            selectedTransaction: [],

            // shiftModal Assign
            assignSearchShift: "",
            selectedAssignShift: null,
            selectedAssignDates: null,
            assignReason: "",
            // filteredAssignEmployees: [],

            // time picker
            minTime: "",
            maxTime: "00:00",
            assignTime: null,
            assignMulaiTime: null,
            searchQuery: "",
            currentPage: 1,
            itemsPerPage: 10,
            // random
            AssignOvertime: false,
            showAssignModal: false,
            assignStep: 1,
            assignSearchQuery: "",
            selectedAssignEmployees: [],
            assignShiftType: "temporary", // 'temporary' or 'permanent'
            assignStartDate: new Date().toISOString().split("T")[0],
            assignEndDate: new Date().toISOString().split("T")[0],
            assignSelectedShift: null,
            replaceExistingShifts: false,
            sendNotification: true,
            viewMode: "card",
        };
    },
    watch: {
        searchQuery() {
            this.debouncedSearch();
        },
        filterDate() {
            this.debouncedSearch();
        },
    },
    // watch: {
    //     // Mengubah 'new' dan 'old' menjadi 'newValue' dan 'oldValue'
    //     // assignTime(newValue, oldValue) {
    //     //     if (this.isTimeAssignValid) {
    //     //         ElMessage({
    //     //             showClose: true,
    //     //             message: "Waktu dipilih: " + newValue, // Menampilkan waktu yang dipilih
    //     //             type: "success", // Perbaiki typo 'succeess' menjadi 'success'
    //     //             customStyle: {
    //     //                 "z-index": "1050",
    //     //             },
    //     //         });
    //     //     } else {
    //     //         ElMessage({
    //     //             showClose: true,
    //     //             message: `Tidak Boleh Pilih ${newValue}. Pilih antara ${this.startTimeShift} - ${this.endTimeShift}.`,
    //     //             type: "error",
    //     //             customStyle: {
    //     //                 "z-index": "1050",
    //     //             },
    //     //         });
    //     //     }
    //     // },
    // },
    computed: {
        // isTimeAssignValid() {
        //     const timeInput = this.$refs.timeAssign;
        //     if (!timeInput) {
        //         return false;
        //     }

        //     const ValidTime = timeInput.validity.valid;
        //     return ValidTime;
        // },
        isAllAssignValid() {
            // Check setiap user sudah valid jam nya
            return this.selectedAssignEmployees.every((emp) => emp.isValid);
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

        formattedDate() {
            if (this.selectedDate.length < 2) return "";
            const [start, end] = this.selectedDate;
            if (!start || !end) return "";
            // Menggunakan fungsi dari methods
            return `${this.formatFullDate(start)} - ${this.formatFullDate(
                end,
            )}`;
        },
        filteredShift() {
            let filtered = this.shiftList;
            // console.log(filtered[0]["Nama"]);

            if (this.assignSearchShift) {
                filtered = filtered.filter((item) =>
                    item.Nama.toLowerCase().includes(
                        this.assignSearchShift.toLocaleLowerCase(),
                    ),
                );
            }
            return filtered;
        },

        // filteredEmployees() {
        //     let filtered = this.data.map((emp) => ({ ...emp, isOpen: false }));

        //     // console.log(filtered);

        //     // Search filter
        //     if (this.searchQuery) {
        //         const query = this.searchQuery.toLowerCase();

        //         filtered = filtered.filter(
        //             (employee) =>
        //                 employee.No_Transaksi.toLowerCase().includes(query) ||
        //                 employee.Nama.toLowerCase().includes(query)
        //         );
        //     }
        //     // console.log(filtered);

        //     // Department filter
        //     // if (this.selectedDepartment) {
        //     //     filtered = filtered.filter(
        //     //         (employee) =>
        //     //             employee.department === this.selectedDepartment
        //     //     );
        //     // }

        //     // Shift filter
        //     if (this.filterDate) {
        //         filtered = filtered.filter((employee) =>
        //             this.formatDate(employee.Tanggal).includes(
        //                 this.formatDate(this.filterDate)
        //             )
        //         );
        //     }

        //     return filtered;
        // },
        filteredAssignEmployees() {
            let filtered = this.userChoose.map((emp) => {
                const isActive = !this.isDisabled(emp);

                // cek apakah karyawan ini ada di selectedAssignEmployees
                const alreadySelected = this.selectedAssignEmployees.some(
                    (sel) => sel.id === emp.userId,
                );

                return {
                    ...emp,
                    isActive, // true = bisa dipilih
                    isSelected: alreadySelected, // true = sudah dicentang
                    isOpen: false,
                    isValid: true,
                };
            });

            // Urutkan: selected > active > disabled
            filtered.sort((a, b) => {
                if (a.isSelected && !b.isSelected) return -1; // selected duluan
                if (!a.isSelected && b.isSelected) return 1;

                if (a.isActive && !b.isActive) return -1; // active sebelum disabled
                if (!a.isActive && b.isActive) return 1;

                return 0;
            });

            // Filter berdasarkan pencarian nama
            if (this.assignSearchQuery) {
                filtered = filtered.filter((employee) =>
                    employee.name
                        .toLowerCase()
                        .includes(this.assignSearchQuery.toLowerCase()),
                );
                this.currentPage = 1;
            }

            return filtered;
        },

        // paginatedEmployees() {
        //     const start = (this.currentPage - 1) * this.itemsPerPage;
        //     const end = start + this.itemsPerPage;
        //     return this.filteredEmployees.slice(start, end);
        // },

        // totalPages() {
        //     return Math.ceil(this.totalData / this.itemsPerPage);
        // },

        canProceedToNextStep() {
            if (this.assignStep === 1) {
                return this.selectedAssignDates;
            }
            if (this.assignStep === 2) {
                return this.assignReason && this.assignTime;
            }
            if (this.assignStep === 3) {
                return this.selectedAssignEmployees;
            }

            return true;
        },
        filteredUserTransaksi() {
            let filteredData = this.detailTransaksi;
            if (this.searchUserTransaksi) {
                filteredData = filteredData.find((e) => {
                    e.name
                        .toLowerCase()
                        .includes(this.searchUserTransaksi.toLowerCase());
                });
            }
            return filteredData;
        },
        paginatedUserTransaksi() {
            return this.filteredUserTransaksi;
        },
        totalPageUser() {
            return Math.ceil(
                this.filteredUserTransaksi.length / this.itemsPerPageUser,
            );
        },
        prevUser() {
            if (this.currentPageUser > 1) this.currentPageUser--;
        },
        nextUser() {
            if (this.currentPageUser < this.totalPageUser)
                this.currentPageUser++;
        },
    },
    mounted() {
        this.checkScreenSize();
        this.getData();
        window.addEventListener("resize", this.checkScreenSize);
    },
    berforeUnmount() {
        window.removeEventListener("resize", this.checkScreenSize);
    },
    methods: {
        isDisabled(employee) {
            // console.log(employee);
            // 1️⃣ Tidak boleh jika JamComparison bukan TRUE
            if (employee.JamComparison != "TRUE") {
                return true;
            }

            // 2️⃣ Tidak boleh jika hari OFF
            // contoh StatusHari: OFF, OFFDAY, OFF_SHIFT
            if (employee.StatusHari && employee.StatusHari.startsWith("OFF")) {
                return true;
            }

            // 3️⃣ Tidak boleh jika sudah pernah lembur
            if (employee.lemburPernah != "FALSE") {
                return true;
            }

            // 4️⃣ Validasi window waktu absensi / lembur
            if (
                !this.isTimeAfter(
                    employee.Jam_Masuk,
                    employee.Jam_Keluar,
                    employee.Nama_Shift,
                )
            ) {
                return true;
            }

            // ✔ selain itu → boleh klik
            return false;
        },
        async getData(url = null) {
            try {
                this.loading = true;
                const params = new URLSearchParams();
                if (this.searchQuery) {
                    params.append("search", this.searchQuery);
                }
                if (this.filterDate) {
                    params.append("tanggalFilter", this.filterDate);
                }

                const endpoint =
                    url || `/overtime/getData?${params.toString()}`;

                const response = await axios.get(endpoint);
                if (response.status == 200) {
                    this.data = response.data.data.data;
                    this.prevLink = response.data.data.prev_page_url;
                    this.nextLink = response.data.data.next_page_url;
                    this.totalData = response.data.data.total;
                    this.currentPage = response.data.data.current_page;
                    this.totalPages = response.data.data.last_page;
                }
            } catch (error) {
                this.data = [];
                this.totalData = 0;
                this.totalPages = 0;
                ElMessage.error("Data Tidak ditemukan.");
                console.log(error);
            } finally {
                this.loading = false;
            }
        },
        debouncedSearch: debounce(function () {
            this.getData();
        }, 500),
        subtractHours(time, hours) {
            if (!time) return "";
            const [h, m] = time.split(":");
            const date = new Date();
            date.setHours(parseInt(h), parseInt(m));
            date.setHours(date.getHours() - hours);
            return date.toTimeString().slice(0, 5); // format HH:mm
        },
        async getTimeServer() {
            const res = await axios.get("/getTime");
            if (res.status == 200) {
                this.serverTime = new Date(
                    new Date(res.data.time).toLocaleString("en-US", {
                        timeZone: "Asia/Jakarta",
                    }),
                );
                console.log(this.serverTime);
            }
        },
        // isTimeAfter(timeString) {
        //     const today = new Date();
        //     const selectedDate = new Date(this.selectedAssignDates);

        //     // Cek apakah selectedAssignDates adalah hari ini
        //     if (selectedDate.toDateString() === today.toDateString()) {
        //         const currentTimeString = today.toTimeString().slice(0, 8); // HH:MM:SS
        //         return timeString > currentTimeString;
        //     }

        //     return true;
        // },

        disabledButton(date) {
            const tanggalSekarang = new Date(this.TanggalSekarang);
            const tanggalCek = new Date(date);

            tanggalSekarang.setHours(0, 0, 0, 0);
            tanggalCek.setHours(0, 0, 0, 0);
            // console.log(tanggalSekarang, tanggalCek);

            return tanggalSekarang.getTime() === tanggalCek.getTime();
        },
        disabledButton2(date) {
            const tanggalSekarang = new Date(this.TanggalSekarang);
            const tanggalCek = new Date(date);

            tanggalSekarang.setHours(0, 0, 0, 0);
            tanggalCek.setHours(0, 0, 0, 0);
            // console.log(tanggalSekarang, tanggalCek);

            return tanggalSekarang.getTime() <= tanggalCek.getTime();
        },
        penyebabDisabled(JamComparison, lemburPernah, BatasInput, StatusHari) {
            if (lemburPernah == "TRUE") {
                return "Karyawan sudah ditugaskan lembur hari ini.";
            } else if (StatusHari && StatusHari.startsWith("OFF")) {
                return "Karyawan tidak dapat lembur di hari libur.";
            } else if (BatasInput === false) {
                return "Batas waktu input lembur karyawan ini sudah habis.";
            } else if (JamComparison == "FALSE") {
                return "Jam akhir lembur yang dipilih sebelumnya tidak memenuhi untuk karyawan ini.";
            } else {
                return "Terjadi kesalahan silahkan hubungi IT";
            }
        },
        truncateText(text, length) {
            return text.length > length
                ? text.substring(0, length) + "..."
                : text;
        },
        // async isTimeAfter(timeString) {
        //     const today = new Date();
        //     const selectedDate = new Date(this.selectedAssignDates);
        //     const res = await axios.get("/getTime");
        //     if (res.status == 200) {
        //         const serverTime = res.data.time; // now()->toDateTimeString()
        //     }

        //     if (selectedDate.toDateString() > serverTime.toDateString()) {
        //         const now = new Date(
        //             new Date().toLocaleString("en-US", {
        //                 timeZone: "Asia/Jakarta",
        //             })
        //         );

        //         const [h, m = "0", s = "0"] = timeString.split(":");
        //         let assignTime = new Date(selectedDate);
        //         assignTime.setHours(parseInt(h), parseInt(m), parseInt(s), 0);

        //         // 🔑 Jika jam keluar lebih kecil dari jam masuk → geser ke hari berikutnya
        //         if (parseInt(h) < 6) {
        //             // asumsi lembur biasanya lewat tengah malam tapi gak lebih dari pagi hari
        //             assignTime.setDate(assignTime.getDate() + 4);
        //         }

        //         // Tambahkan 4 jam
        //         assignTime.setHours(assignTime.getHours() + 4);

        //         // console.log("Jam keluar:", timeString);
        //         // console.log("AssignTime +4 (final):", assignTime.toString());
        //         // console.log("Now:", now.toString());

        //         return assignTime > now;
        //     }

        //     return true;
        // },
        // isTimeAfter(timeString, shiftType) {
        //     const selectedDate = new Date(this.selectedAssignDates);

        //     const serverTime = this.serverTime; // jam server
        //     // console.log(serverTime);
        //     const now = serverTime;

        //     // Buat cutoff dari timeString
        //     const [h, m = "0", s = "0"] = timeString.split(":");
        //     let cutoffTime = new Date(selectedDate);
        //     cutoffTime.setHours(parseInt(h), parseInt(m), parseInt(s), 0);

        //     if (
        //         shiftType === "EMI-S3" ||
        //         shiftType === "EMI-S2" ||
        //         shiftType === "EMI-LS2" ||
        //         shiftType === "EMI-B2" ||
        //         shiftType === "SEC-M" ||
        //         shiftType === "SEC-SORE" ||
        //         shiftType === "SEC-MINGGU-MALAM"
        //     ) {
        //         // Khusus shift ini → boleh lewat tengah malam
        //         if (parseInt(h) < 11) {
        //             cutoffTime.setDate(cutoffTime.getDate() + 1); // geser ke besok
        //         }

        //         if (selectedDate.toDateString() === now.toDateString()) {
        //             const [h, m = "0", s = "0"] = timeString.split(":");
        //             let assignTime = new Date(selectedDate);
        //             assignTime.setHours(
        //                 parseInt(h),
        //                 parseInt(m),
        //                 parseInt(s),
        //                 0
        //             );

        //             // Tambahkan 4 jam
        //             assignTime.setHours(assignTime.getHours() + 4);

        //             // console.log("Jam keluar:", timeString);
        //             // console.log("AssignTime +4 (final):", assignTime.toString());
        //             // console.log("Now:", now.toString());

        //             return assignTime > now;
        //         }
        //         // +4 jam grace period
        //         cutoffTime.setHours(cutoffTime.getHours() + 4);
        //         console.log(cutoffTime, shiftType);

        //         return now <= cutoffTime;
        //     } else {
        //         if (selectedDate.toDateString() === now.toDateString()) {
        //             const [h, m = "0", s = "0"] = timeString.split(":");
        //             let assignTime = new Date(selectedDate);
        //             assignTime.setHours(
        //                 parseInt(h),
        //                 parseInt(m),
        //                 parseInt(s),
        //                 0
        //             );

        //             // Tambahkan 4 jam
        //             assignTime.setHours(assignTime.getHours() + 4);

        //             // console.log("Jam keluar:", timeString);
        //             // console.log("AssignTime +4 (final):", assignTime.toString());
        //             // console.log("Now:", now.toString());

        //             return assignTime > now;
        //         }
        //         // Regular → cutoff = timeString pada hari itu
        //         return now <= cutoffTime;
        //     }
        // },
        isTimeAfter(jamMasuk, jamKeluar, shiftType) {
            const selectedDate = new Date(this.selectedAssignDates);

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

            // Ambil waktu server
            let now = new Date(this.serverTime);

            return now <= cutoffTime;
        },
        getCurrentTime() {
            return new Date();
        },
        formatDateTime(item) {
            if (!item) {
                // Jika item kosong atau undefined, kembalikan nilai default
                return "";
            }

            const date = new Date(item);

            // Memastikan item bisa diubah menjadi tanggal yang valid
            if (isNaN(date.getTime())) {
                console.error("Invalid date format:", item);
                return ""; // Atau kembalikan nilai default sesuai kebutuhan
            }

            // Mendapatkan jam dan menit
            const hours = date.getHours().toString().padStart(2, "0");
            const minutes = date.getMinutes().toString().padStart(2, "0");

            // Mendapatkan hari, bulan, dan tahun
            const day = date.getDate().toString().padStart(2, "0");
            const month = (date.getMonth() + 1).toString().padStart(2, "0"); // bulan dimulai dari 0
            const year = date.getFullYear();

            // Mengembalikan format yang diinginkan: "23:59 22-06-2025"
            return `${hours}:${minutes} ${day}-${month}-${year}`;
        },

        handleValidation(userId) {
            const employeeData = this.selectedAssignEmployees.find(
                (emp) => emp.id === userId,
            );
            if (!employeeData) return;
            const isValid = this.isTimeAssignValid(userId);
            console.log(employeeData);

            if (!isValid) {
                ElMessage({
                    showClose: true,
                    message: ` Jam ${
                        employeeData.nama
                    } tidak valid. Pilih antara ${employeeData.masuk
                        ?.split(":")
                        .slice(0, 2)
                        .join(":")} - ${this.subtractHours(
                        employeeData.Jam_MasukShift,
                        2,
                    )
                        ?.split(":")
                        .slice(0, 2)
                        .join(":")}.`,
                    type: "error",
                    customStyle: {
                        "z-index": "1050",
                    },
                });
            }
            employeeData.isValid = isValid;
        },

        isTimeAssignValid(userId) {
            const timeInput = this.timeInputRefs[userId];
            // console.log(timeInput);

            if (!timeInput) {
                return false;
            }
            return timeInput.validity.valid;
        },
        log() {
            // console.log(this.selectedAssignEmployees);
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
                (emp) => emp.userId === userId,
            );
            if (cardSelect) {
                cardSelect.isOpen = !cardSelect.isOpen;
            }
        },
        downloadExcel() {
            this.titleAlertModal = `Export Data Lembur`;
            this.buttonAlertModal = "Download";
            this.bodyAlert = `Apa kamu yakin mengunduh data lembur ${this.formatDate(
                this.tempStartDate,
            )} s.d ${this.formatDate(this.tempEndDate)}?`;
            this.yesModal = () => this.confirmDate();
            // this.AssignOvertime = false;
            this.showAlertModal = true;
        },
        // Calendar
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

        openDialogFilter() {
            if (this.selectedDateFilter.length) {
                const startStr = this.selectedDate;
                this.tempStartDateFilter = new Date(startStr + "T00:00:00");
                this.calendarDateFilter = new Date(startStr + "T00:00:00");
            } else {
                this.tempStartDateFilter = null;
                this.calendarDateFilter = new Date();
            }
            this.dialogFilter = true;
        },

        handleCancel() {
            this.dialogVisible = false;
            this.selectedDate = [];
            this.tempStartDate = null;
            this.tempEndDate = null;
        },
        handleCancelFilter() {
            this.dialogVisible = false;
            this.filterDate = null;
            this.tempStartDateFilter = null;
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
        handleDateClickFilter(data) {
            const clickedDate = data.date;
            // console.log(clickedDate);
            this.tempStartDateFilter = clickedDate;
        },
        getDateFilter() {
            this.filterDate = this.tempStartDateFilter;
            this.dialogFilter = false;
            // console.log(this.tempStartDate);
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
            // console.log(this.selectedDate);
            this.getExportData(
                this.formatDateToString(this.tempStartDate),
                this.formatDateToString(this.tempEndDate),
            );
        },
        // Akhir Tanggal
        async getExportData(Start, End) {
            try {
                this.loading = true;
                const response = await axios.get("/overtime/getExport", {
                    params: {
                        start: Start,
                        end: End,
                    },
                });

                if (response.status == 200) {
                    this.dataExport = Object.entries(response.data.data);
                    this.exportCustomExcel();
                    // console.log(response.data.data);
                } else {
                    // console.log(response);
                    console.log("Terjadi Error saat mengambil data!");
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
                this.closeDetailsMethod();
            } finally {
                this.loading = false;
            }
        },
        async exportCustomExcel() {
            const ExcelJS = await import("exceljs");
            const workbook = new ExcelJS.Workbook();
            const worksheet = workbook.addWorksheet("Assign Overtime");
            try {
                // console.log("di export");
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

                worksheet.mergeCells("B1:H1");
                worksheet.getCell("B1").value = "Employee Overtime Management";
                worksheet.getCell("B1").style = headerStyle;

                worksheet.getCell("A1").value = "Tanggal"; // Akan dimerge nanti

                // Baris 2 (Header Kolom)
                const headers = [
                    "No. Transaksi",
                    "Nama Karyawan",
                    "Tanggal Lembur",
                    "Waktu",
                    "Durasi",
                    "Alasan",
                    "Status Lembur(Check Out)",
                ];

                const headerRow = worksheet.getRow(2);
                headerRow.values = [null, ...headers];
                headerRow.eachCell(
                    { includeEmpty: true },
                    (cell, colNumber) => {
                        if (colNumber > 0) {
                            cell.style = headerStyle;
                        }
                    },
                );

                // Merge header "Tanggal" dari baris 1 ke 2
                worksheet.mergeCells("A1:A2");
                worksheet.getCell("A1").style = headerStyle;

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

                // Pengisian Sel
                let currentRowIndex = 3;

                this.dataExport.forEach(([date, trans]) => {
                    const dateBlockStartRow = currentRowIndex;

                    Object.entries(trans).forEach(([noTrans, detailTrans]) => {
                        const transBlockStartRow = currentRowIndex;

                        if (
                            !detailTrans.details ||
                            detailTrans.details.length === 0
                        ) {
                            return;
                        }

                        detailTrans.details.forEach((user, index) => {
                            const row = worksheet.getRow(currentRowIndex);

                            // Mengisi data dari kolom C dan seterusnya
                            row.values = {
                                nama: user.name,
                                tglLembur: this.formatDate(user.start_time),
                                waktu: this.formatTime(
                                    user.start_time,
                                    user.end_time,
                                ),
                                durasi: this.formatDuration(
                                    user.start_time,
                                    user.end_time,
                                ),
                                alasan: user.reason,
                                status: user.CHECKOUT,
                            };

                            row.eachCell(
                                { includeEmpty: true },
                                (cell, colNumber) => {
                                    if (colNumber > 1) {
                                        cell.style = cellStyle;
                                    }
                                },
                            );

                            currentRowIndex++;
                        });

                        // Merge kolom "No. Transaksi" (Kolom B)
                        const transBlockEndRow = currentRowIndex - 1;
                        if (transBlockStartRow <= transBlockEndRow) {
                            worksheet.mergeCells(
                                `B${transBlockStartRow}:B${transBlockEndRow}`,
                            );
                            const cellB = worksheet.getCell(
                                `B${transBlockStartRow}`,
                            );
                            cellB.value = noTrans;
                            cellB.style = centeredCellStyle;
                        }
                    });

                    // Merge kolom "Tanggal" (Kolom A)
                    const dateBlockEndRow = currentRowIndex - 1;
                    if (dateBlockStartRow <= dateBlockEndRow) {
                        worksheet.mergeCells(
                            `A${dateBlockStartRow}:A${dateBlockEndRow}`,
                        );
                        const cellA = worksheet.getCell(
                            `A${dateBlockStartRow}`,
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
                link.download = `Export_Lembur_${this.formatDate(
                    this.tempStartDate,
                )} s.d ${this.formatDate(this.tempEndDate)}.xlsx`;
                link.click();
                URL.revokeObjectURL(link.href); // Membersihkan memori
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
                this.loading = false;
                this.closeAlertModal();
                this.handleCancel();
            }
        },
        openDatePicker() {
            // Cek apakah ref sudah tersedia
            if (this.$refs.myDatePicker) {
                // Panggil metode .focus() untuk membuka popup
                this.$refs.myDatePicker.focus();
            }
        },

        async showDetailsMethod($item) {
            this.titleDetails = $item;
            this.detailShow = true;
            this.loading = true;
            try {
                const response = await axios.get("/overtime/getDetails", {
                    params: {
                        No_Transaksi: $item,
                    },
                });
                // console.log(response);
                if (response.data.status == 200) {
                    this.detailTransaksi = response.data.data;
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
                this.closeDetailsMethod();
            } finally {
                this.loading = false;
            }
        },
        closeDetailsMethod() {
            this.titleDetails = "";
            this.detailShow = false;
        },

        // alertModal
        closeAlertModal() {
            this.showAlertModal = false;
            this.titleAlertModal = "";
            this.buttonAlertModal = "";
            this.param1 = "";
            this.yesModal = "";
        },
        // delete Alert
        deleteAlertButton(param1) {
            this.titleAlertModal = `Delete ${param1}`;
            this.buttonAlertModal = "Hapus";
            this.bodyAlert = "Apa Kamu yakin akan menghapus?";
            this.yesModal = () => this.destroyItem(param1); // Tambah 'this.' jika method class
            this.showAlertModal = true;
        },
        deleteUserButton(param1) {
            this.titleAlertModal = `Delete ${param1}`;
            this.buttonAlertModal = "Hapus";
            this.bodyAlert = "Apa Kamu yakin akan menghapus?";
            this.yesModal = () => this.destroyUser(param1); // Tambah 'this.' jika method class
            this.showAlertModal = true;
        },
        // store
        storeAlertButton() {
            if (this.isAllAssignValid) {
                this.titleAlertModal = `Simpan data lembur`;
                this.buttonAlertModal = "Simpan";
                this.bodyAlert = "Apa Kamu yakin akan Menyimpan Lembur ini?";
                this.yesModal = () => this.storeOvertime(); // Tambah 'this.' jika method class
                // this.AssignOvertime = false;
                this.showAlertModal = true;
            } else {
                ElMessage({
                    showClose: true,
                    message: `Ada beberapa jam user tidak valid!, Perbaiki dulu. `,
                    type: "error",
                    customStyle: {
                        "z-index": "1050",
                    },
                });
            }
        },

        prevPage() {
            if (this.currentPage > 1) this.currentPage--;
        },
        nextPage() {
            if (this.currentPage < this.totalPages) this.currentPage++;
        },
        getEmployeeShiftForDay(userId, dayIndex) {
            const employee = this.employees.find(
                (emp) => emp.userId === userId,
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
            return classes[shift] || "shift-default";
        },

        getShiftTime(shift) {
            // console.log(shift);
            const times = {
                PAGI: "06:00 - 14:00",
                SIANG: "14:00 - 22:00",
                MALAM: "22:00 - 06:00",
                NOSHIFT: "Tidak ada shift",
            };
            return times[shift] || "";
        },

        toggleShiftSelection(ID_Shift) {
            const index = this.selectedAssignShift.indexOf(ID_Shift);
            if (index === -1) {
                this.selectedAssignShift.push(ID_Shift);
            } else {
                this.selectedAssignShift.splice(index, 1);
            }
        },
        isToday(dateString) {
            const today = new Date().toISOString().split("T")[0];
            return dateString === today;
        },
        closeAssignModal() {
            this.AssignOvertime = false;
            this.assignStep = 1;
            this.assignSearchQuery = "";
            this.selectedAssignEmployees = [];
            this.selectedAssignDates = null;
            this.assignReason = "";
            this.assignSearchQuery = "";
            this.assignTime = null;
            // console.log(this.AssignOvertime);
            // console.log(this.tanggal);
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
        async getShift() {
            this.loading = true;
            try {
                const response = await axios.get("/overtime/getShift", {
                    params: {
                        tanggal: this.selectedAssignDates,
                    },
                });

                if (response.status == 200) {
                    // console.log(response);
                    this.shiftList = response.data.data;
                    // console.log(response.data.data[0].Jam_Masuk);
                }
            } catch (error) {
                console.error("Error Fetching Time :", error);
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
            } finally {
                this.loading = false;
            }
        },

        nextAssignStep() {
            if (this.assignStep == 1) {
                if (!this.selectedAssignDates) {
                    // Menampilkan notifikasi jika selectedAssignDates kosong atau null
                    ElMessage({
                        showClose: true,
                        message: "Silahkan pilih tanggal terlebih dahulu.",
                        type: "error",
                        customStyle: {
                            "z-index": "1050",
                        },
                    });
                    return false; // Mengembalikan false agar tidak melanjutkan ke langkah berikutnya
                }
                // this.shiftList = [];
                // this.getShift();
                this.assignStep++;
                // } else if (this.assignStep == 2) {
                //     if (!this.assignSelectedShift) {
                //         // Menampilkan notifikasi jika selectedAssignDates kosong atau null
                //         ElMessage({
                //             showClose: true,
                //             message: "Silahkan Pilih Salah satu shift .",
                //             type: "error",
                //             customStyle: {
                //                 "z-index": "1050",
                //             },
                //         });
                //         return false; // Mengembalikan false agar tidak melanjutkan ke langkah berikutnya
                //     }
                //     this.startTimeShift = null;
                //     this.endTimeShift = null;
                //     this.getWaktuShift();
                //     this.assignStep++;
            } else if (this.assignStep == 2) {
                if (!this.assignReason || !this.assignTime) {
                    ElMessage({
                        showClose: true,
                        message: `Silahkan masukan alasan dan waktu.`,
                        type: "error",
                        customStyle: {
                            "z-index": "1050",
                        },
                    });
                    return false;
                }
                this.userChoose = [];
                this.selectedAssignEmployees = [];
                this.getUser();
                this.assignStep++;
            } else if (this.assignStep > 2 && this.assignStep < 3) {
                if (!this.selectedAssignEmployees) {
                    ElMessage({
                        showClose: true,
                        message: "Silahkan pilih user minimal 1 .",
                        type: "error",
                        customStyle: {
                            "z-index": "1050",
                        },
                    });
                    return false;
                }
                this.assignStep++;
            }
        },

        prevAssignStep() {
            if (this.assignStep > 1) {
                this.assignStep--;
            }
        },

        selectAllEmployees() {
            const validUsersToAdd = this.userChoose.filter(
                (user) =>
                    !this.isDisabled(user) &&
                    !this.selectedAssignEmployees.some(
                        (selectedEmp) => selectedEmp.id === user.userId,
                    ),
            );

            validUsersToAdd.forEach((user) => {
                const isAlreadySelected = this.selectedAssignEmployees.some(
                    (selectedEmp) => selectedEmp.id === user.userId,
                );

                if (!isAlreadySelected) {
                    this.selectedAssignEmployees.push({
                        id: user.userId,
                        tanggal: this.selectedAssignDates,
                        reason: this.assignReason,
                        waktu: this.assignTime,
                        masuk: user.Jam_Keluar,
                        Jam_MasukShift: user.Jam_Masuk,
                        Jam_selesai_absen: user.Jam_selesai_absen,
                        nama: user.name,
                        isSelected: true,
                        isValid: true,
                    });
                }
            });
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

        formatDatePanjang(tanggal) {
            const opsiFormat = {
                year: "numeric", // 'numeric' (2025)
                month: "long", // 'long' (Juni), 'short' (Jun), 'numeric' (6)
                day: "numeric", // 'numeric' (10)
            };

            // 3. Buat formatter dan format tanggalnya
            const formatter = new Intl.DateTimeFormat("id-ID", opsiFormat);
            // console.log(formatter.format(tanggal));
            return formatter.format(tanggal);
        },
        formatDate(dateString) {
            const date = new Date(dateString);

            return date.toLocaleDateString("id-ID", {
                day: "2-digit",
                month: "short",
                year: "numeric",
            });
        },
        getDayName(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString("id-ID", { weekday: "short" });
        },
        toggleEmployeeSelection(userId) {
            // Gunakan findIndex untuk mencari object berdasarkan properti id

            const index = this.selectedAssignEmployees.findIndex(
                (emp) => emp.id === userId,
            );

            if (index === -1) {
                const userData = this.userChoose.find(
                    (emp) => emp.userId === userId,
                );
                if (!this.isDisabled(userData)) {
                    // console.log(userData.Jam_Keluar);
                    // Jika tidak ditemukan, tambahkan
                    this.selectedAssignEmployees.push({
                        id: userId,
                        tanggal: this.selectedAssignDates,
                        reason: this.assignReason,
                        waktu: this.assignTime,
                        masuk: userData.Jam_Keluar,
                        Jam_MasukShift: userData.Jam_Masuk,
                        Jam_selesai_absen: userData.Jam_selesai_absen,
                        nama: userData.name,
                        isSelected: true,
                        isValid: true,
                    });
                }

                // this.log();
            } else {
                // Jika ditemukan, hapus
                this.selectedAssignEmployees.splice(index, 1);
            }
        },
        getEmployeeData(userId) {
            return this.selectedAssignEmployees.find(
                (emp) => emp.id === userId,
            );
        },

        formatDateShort(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString("id-ID", {
                day: "2-digit",
                month: "short",
            });
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
        formatTimeSingle(start_time) {
            const start = new Date(start_time);
            // console.log(start);
            // Format waktu dalam gaya yang mudah dibaca
            const startTime = start.toLocaleTimeString("id-ID", {
                hour: "2-digit",
                minute: "2-digit",
            });

            return `${startTime}`;
        },
        formatDuration(start_time, end_time) {
            const start = new Date(start_time);
            const end = new Date(end_time);

            // Menghitung selisih waktu dalam milidetik
            const duration = end - start;

            // Mengonversi milidetik ke dalam jam dan menit
            const hours = Math.floor(duration / 1000 / 60 / 60); // Jam
            const minutes = Math.floor(
                (duration % (1000 * 60 * 60)) / (1000 * 60),
            ); // Menit

            // Menyusun hasilnya dalam format yang mudah dibaca
            return `${hours} jam ${minutes} menit`;
        },
        getInitials(name) {
            return name
                .split(" ")
                .map((word) => word[0])
                .join("")
                .substring(0, 2)
                .toUpperCase();
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
        disabledHours() {
            const minHour = parseInt(this.minTime.split(":")[0], 10) + 1; // Ambil jam dari minTime
            return Array.from({ length: minHour }, (_, index) => index); // Disable jam sebelum minHour
        },
        disabledHoursModal(userId) {
            // 1. Mencari data karyawan berdasarkan userId
            const data = this.filteredAssignEmployees.find(
                (emp) => emp.userId === userId,
            );

            // 2. Pastikan data, Jam_Keluar, dan Jam_selesai_absen tersedia untuk menghindari error
            if (data && data.Jam_Keluar && data.Jam_selesai_absen) {
                // 3. Ambil angka jam dari string waktu (misal: "09:00" -> 9)
                const minHour = parseInt(data.Jam_Keluar.split(":")[0], 10);
                const maxHour = parseInt(
                    data.Jam_selesai_absen.split(":")[0],
                    10,
                );

                const disabledHours = [];

                // 4. Looping melalui semua jam dalam sehari (0 sampai 23)
                for (let i = 0; i < 24; i++) {
                    // 5. Jika jam (i) berada DI LUAR rentang yang diizinkan, tambahkan ke array
                    if (i < minHour || i > maxHour) {
                        disabledHours.push(i);
                    }
                }

                // 6. Kembalikan array jam yang akan dinonaktifkan
                return disabledHours;
            }

            // 7. Jika data tidak lengkap, kembalikan array kosong (tidak ada jam yang dinonaktifkan)
            return [];
        },
        clearFilters() {
            this.searchQuery = "";
            this.selectedDepartment = null;
            this.selectedShift = null;
            this.currentPage = 1;
        },
        clearFiltersAssign() {
            this.assignSearchQuery = "";
        },
        // edit modal method
        showEditModal(item) {
            this.selectedTransaction = this.data.find(
                (one) => one.No_Transaksi === item,
            );
            // console.log(this.selectedTransaction);
            this.minTime = this.selectedTransaction.end_time
                .split(" ")
                .slice(1, 2)
                .join(" ");
            this.selectedAssignEmployees.push({
                id: this.selectedTransaction.userId,
                tanggal: this.selectedTransaction.end_time
                    .split(" ")
                    .slice(0, 1)
                    .join(" "),
                reason: this.selectedTransaction.reason,
                waktu: this.selectedTransaction.end_time
                    .split(" ")
                    .slice(1, 2)
                    .join(" "),
                isSelected: true,
            });
            this.showEditOvertime = true;
        },
        closeEditModal() {
            this.showEditOvertime = false;
            this.resetEditModal();
        },
        resetEditModal() {
            this.selectedTransaction = [];
            this.selectedAssignEmployees = [];
            this.minTime = "";
        },
        saveEditModal() {
            // console.log(this.selectedAssignEmployees);
            this.showEditOvertime = false;
            ElMessage({
                showClose: true,
                message: "Congrats, data berhasil diedit.",
                type: "success",
                customStyle: {
                    "z-index": "1050",
                },
            });

            this.resetEditModal();
        },
        async destroyItem(item) {
            try {
                this.loading = true;
                const response = await axios.post("/overtime/destroyOvertime", {
                    params: {
                        No_Transaksi: item,
                    },
                });
                if (response.status == 200) {
                    this.closeAlertModal();
                    this.closeDetailsMethod();
                    this.closeAssignModal();
                    this.getData();

                    ElNotification({
                        title: "Berhasil",
                        message: "Pengajuan Lembur Berhasil dihapus",
                        type: "success",
                    });
                }
            } catch (error) {
                console.error("Error Fetching Time :", error);
                this.closeAlertModal();
                this.closeAssignModal();
                this.closeAlertModal();
                ElNotification({
                    title: "Gagal",
                    message:
                        "Terjadi Kesalahan, Silahkan Coba lagi beberapa saat",
                    type: "error",
                });
                this.closeAlertModal();
            } finally {
                this.loading = false;
            }
        },
        async destroyUser(item) {
            try {
                this.loading = true;
                const response = await axios.post(
                    "/overtime/destroyUserOvertime",
                    {
                        params: {
                            urut_Oto: item,
                        },
                    },
                );
                if (response.status == 200) {
                    this.closeDetailsMethod();
                    this.closeAlertModal();
                    this.closeAssignModal();
                    this.getData();

                    ElNotification({
                        title: "Berhasil",
                        message: "Pengajuan Lembur Berhasil dihapus",
                        type: "success",
                    });
                }
            } catch (error) {
                console.error("Error Fetching Time :", error);
                this.closeAlertModal();
                this.closeAssignModal();
                this.closeAlertModal();
                ElNotification({
                    title: "Gagal",
                    message:
                        "Terjadi Kesalahan, Silahkan Coba lagi beberapa saat",
                    type: "error",
                });
                this.closeAlertModal();
            } finally {
                this.loading = false;
            }
        },
        async getWaktuShift() {
            this.loading = true;
            try {
                const response = await axios.get("/overtime/getWaktuShift", {
                    params: {
                        shift: this.assignSelectedShift,
                        date: this.selectedAssignDates,
                    },
                });
                if (response.status == 200) {
                    this.startTimeShift =
                        response.data.data[0].Jam_Keluar.split(":")
                            .slice(0, 2)
                            .join(":");
                    this.endTimeShift =
                        response.data.data[0].Jam_selesai_absen.split(":")
                            .slice(0, 2)
                            .join(":");
                }
            } catch (error) {
                console.error("Error Fetching Time :", error);
            } finally {
                this.loading = false;
            }
            // console.log(this.selectedAssignShift, this.selectedAssignDates);
        },

        async getUser() {
            this.loading = true;

            try {
                this.getTimeServer(); // Memanggil fungsi untuk mendapatkan waktu server
                const params = {
                    date: this.selectedAssignDates,
                    time: this.assignTime,
                };

                const response = await axios.get("/overtime/getUserActive", {
                    params,
                });

                this.userChoose = response.data.data;
            } catch (error) {
                if (error.response) {
                    // Jika statusnya 404 (Not Found)
                    if (error.response.status === 404) {
                        this.userChoose = [];
                        ElMessage({
                            showClose: true,
                            message: `Maaf, tidak ada jadwal karyawan yang tersedia pada tanggal ${this.formatDate(
                                this.selectedAssignDates,
                            )}`,
                            type: "warning",
                            customStyle: {
                                "z-index": "1050",
                            },
                        });
                    } else {
                        ElMessage({
                            showClose: true,
                            message:
                                "Terjadi kesalahan pada server. Silakan coba lagi.",
                            type: "error",
                            customStyle: {
                                "z-index": "1050",
                            },
                        });
                    }
                } else if (error.request) {
                    // console.error("Network Error:", error.request);
                    ElMessage({
                        showClose: true,
                        message:
                            "Tidak dapat terhubung ke server. Periksa koneksi internet Anda.",
                        type: "error",
                        customStyle: {
                            "z-index": "1050",
                        },
                    });
                } else {
                    // console.error("Error Fetching Data:", error.message);
                    ElMessage({
                        showClose: true,
                        message:
                            "Terjadi kesalahan, silakan coba lagi beberapa saat.",
                        type: "error",
                        customStyle: {
                            "z-index": "1050",
                        },
                    });
                }
            } finally {
                this.loading = false;
            }
        },
        async storeOvertime() {
            if (this.selectedAssignEmployees.length > 0) {
                this.loading = true;
                try {
                    const response = await axios.post("/overtime/submit", {
                        params: {
                            Kode_Perusahaan: "001",
                            dataUsers: this.selectedAssignEmployees,
                        },
                    });
                    // console.log(response);
                    if (response.status === 200) {
                        this.closeAlertModal();
                        this.closeAssignModal();
                        this.getData();

                        ElNotification({
                            title: "Berhasil",
                            message: "Pengajuan Lembur Berhasil disimpan",
                            type: "success",
                        });
                    }
                } catch (error) {
                    console.error("Error Fetching Time :", error);
                    this.closeAlertModal();
                    this.closeAssignModal();
                    this.closeAlertModal();
                    ElNotification({
                        title: "Gagal",
                        message:
                            "Terjadi Kesalahan, Silahkan Coba lagi beberapa saat",
                        type: "error",
                    });
                } finally {
                    this.loading = false;
                }
                // console.log(this.selectedAssignShift, this.selectedAssignDates);
            } else {
                // ElMessage({
                //     showClose: true,
                //     message: "Silahkan pilih user minimal 1 .",
                //     type: "error",
                //     customStyle: {
                //         "z-index": "1050",
                //     },
                // });
                ElNotification({
                    title: "Gagal",
                    message: "Silahkan pilih user minimal 1",
                    type: "error",
                });
                return false;
            }
        },
    },
};
</script>

<style scoped scope>
*,
*::before,
*::after {
    --primary: var(--hcis-indigo-500);
    --primary-dark: var(--hcis-indigo-600);
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
    --shadow-inset: inset 0 0px 0.3rem -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --shadow-lg-inset: inset 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --gradient-primary: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
    --gradient-success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --gradient-info: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --gradient-dark: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
}
* {
    box-sizing: border-box;
}
element.style {
    --el-dialog-width: 95%;
    /* --el-dialog-height: 55%; */
}
/* calendar */
.element.style {
    z-index: 1048;
}
.el-overlay {
    z-index: 1048 !important;
}
.el-picker-panel.el-date-picker.el-popper {
    z-index: 1048 !important; /* Pastikan lebih tinggi dari 2003 */
}
element.style {
    z-index: 1048 !important;
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

#app > div:nth-child(7) {
    z-index: 1048 !important;
}

:deep(.el-overlay) {
    z-index: 1048 !important;
}
:deep(.el-dialog) {
    z-index: 1049 !important;
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
    transition:
        background-color 0.2s,
        color 0.2s;
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

/* detailsModal */

/* timepicker */
.el-input__wrapper {
    flex-grow: 0;
    /* justify-content: start; */
}
.el-input.is-disabled .el-input__wrapper {
    background-color: transparent;
    box-shadow: none;
    cursor: not-allowed;
}
.el-input__wrapper {
    width: 10px;
    border: none;
    box-shadow: none;
    background: transparent;
    /* left: -5rem; */
    /* opacity: 0; */
    position: relative;
}
.hidden-datepicker {
    position: absolute;
    width: 0;
    height: 0;
    padding: 0;
    margin: 0;
    border: 0;
    opacity: 0;
    pointer-events: none;
}
.el-input__suffix-inner {
    display: none;
}
.el-input__wrapper.is-focus {
    box-shadow: none;
}
.el-popper.is-pure {
    margin-top: 0rem;
    position: relative;
    margin-left: -4.5rem;
}
.el-input__prefix-inner {
    display: none;
}
.el-input__wrapper:hover {
    box-shadow: none;
}
.el-date-editor.el-input,
.el-date-editor.el-input__wrapper {
    width: 0;
}
.el-input__inner {
    width: 100%;
    position: absolute;
}
.custom-time-picker {
    height: max-content;
}

.custom-time-picker .el-input__inner {
    width: 9rem;
    opacity: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: all 0.3s ease;
    height: 2rem;
    margin-left: -10rem;
}
.custom-time-picker .el-input--suffix .el-input__inner {
    padding-right: 0px;
    padding-left: 2rem;
}
.user.custom-time-picker .el-input__inner {
    width: 40%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 12px;
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: all 0.3s ease;
    height: 52px;
}
.user2.custom-time-picker .el-input__inner {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 12px;
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: all 0.3s ease;
    height: 52px;
    margin: 0;
    margin-left: 30.4rem;
}

.custom-time-picker .el-input__inner:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.custom-time-picker .el-input--suffix .el-input__inner {
    padding-right: 40px;
}

.custom-time-picker .el-input__suffix {
    /* right: 10px; */
}

.custom-time-picker .el-input__icon {
    color: var(--primary);
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

/* Fix z-index for time picker dropdown */
.el-time-panel {
    z-index: 10000 !important;
}

.el-picker-panel {
    z-index: 10000 !important;
}

.el-popper {
    z-index: 10000 !important;
}

.header-wrapper {
    min-height: 140px;

    background: var(--gradient-primary);
}
.title {
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    letter-spacing: -0.025em;
    color: var(--text);
}
.shadow-unix {
    box-shadow:
        0 20px 25px -5px rgba(0, 0, 0, 0.1),
        0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.glassMorph {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10rem);
    border: 1px solid rgba(255, 255, 255, 0.3);
    transition: all 0.3s ease;
}
.glassMorph:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}
.shiningEffect {
    transition: all 0.3s ease;
    overflow: hidden;
    position: relative;
    border: var(--border);
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
        rgba(255, 255, 255, 0.2),
        transparent
    );
    transition: left 0.5s;
}

.shiningEffect:hover::before {
    left: 100%;
    border: var(--border);
}
.btn-primary {
    background-color: var(--primary);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(20px);
}

/* Search and Filters */
.search-filter-section {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.search-container {
    position: relative;
    flex: 1;
    flex-grow: 1;
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
    gap: 0.5rem;
}

/* Filter Dropdown */
.filter-dropdown {
    flex-grow: 1;
    flex-shrink: 1;
    flex: 1;
}

.filter-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;

    padding: 0.75rem 1rem;
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text);
    cursor: pointer;
    transition: all 0.3s ease;
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
    z-index: 1000;
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

/* Table */

.table-container {
    background: var(--surface);
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
    border-radius: 0px;
}

.modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.9rem;
}

.modern-table thead th {
    background: var(--surface-soft);
    padding: 1.25rem 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--text);
    border-bottom: 2px solid var(--border);
    position: sticky;
    top: 0;
    z-index: 10;
}

.sticky-column {
    position: sticky;
    left: 0;
    background: var(--surface);
    z-index: 11;
    /* box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1); */
}

.employee-header {
    min-width: 200px;
    z-index: 1050;
    position: relative;
    /* background-color: rgba(239, 68, 68, 0.05); */
    border-right: 1px solid var(--border);
}

/* ini Baru */

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

.header-info {
    display: flex;
    text-align: center;
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
    cursor: pointer;
    background: var(--surface);
}

.employee-row:hover {
    background: var(--surface-soft);
}

/* Try this if the hover is being overridden by .highlight */
.employee-row.highlight:hover {
    /* More specific */
    background: var(--surface-soft);
}

.employee-row.highlight {
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
    width: 28px;
    height: 28px;
    border-radius: 5px;
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
    gap: 0rem;
}

.employee-name {
    font-weight: 600;
    color: var(--text);
}

.employee-id {
    width: fit-content;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.shift-cell {
    padding: 0.5rem;
    /* background: var(--surface); */
    border-bottom: 1px solid var(--border);
    text-align: center;
}

.shift-container {
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
    justify-content: center;
    margin-bottom: 0.25rem;
}

.shift-name {
    font-weight: 600;
    font-size: 0.85rem;
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
    opacity: 0.8;
}

/* Shift Status Colors */
.shift-morning {
    background: linear-gradient(135deg, var(--hcis-amber-100), var(--hcis-amber-300));
    color: var(--hcis-amber-800);
}

.shift-afternoon {
    background: linear-gradient(135deg, #ddd6fe, var(--hcis-violet-400));
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
    padding: 0 1.5rem;
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
    background: var(--surface);
    box-shadow: var(--shadow-lg);
    border-radius: 15px;
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
    margin-bottom: 0.7rem;
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

.modal-container.details {
    max-width: 80vw;
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
.modal-header.details {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 2rem;
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

.modal-body.details {
    position: relative;

    padding: 0rem 0rem;
}
.modern-table thead th {
    position: sticky !important;
    top: -1rem;
    z-index: 100;
}

.form-shift {
    max-height: 25rem;
    overflow-y: auto;
    margin-bottom: 0.1rem;
    border: 1px solid var(--border);
    background: rgba(99, 102, 241, 0.05);
    border-radius: 12px;
    padding: 1rem;
}

.form-shift label,
.form-label {
    display: block;
    margin-bottom: 0.1rem;
    font-weight: 600;
    color: var(--text);
}

.form-shift p {
    margin: 0;
    padding: 0.4rem 0;

    color: var(--text-muted);
}

.form-group {
    margin-bottom: 0.1rem;
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
.container-shift {
    max-height: 10rem;
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
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
}

.shift-badge.large {
    padding: 0rem 0rem;
    font-weight: 700;

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

.btn-atas {
    border-radius: 12px;
    border: var(--border);
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
.form-floating {
    position: absolute;
    top: -0.7rem;
    background: white;
    left: 1.5rem;
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
    flex-direction: column;
    align-items: start;
    gap: 1rem;
    /* padding: 1rem; */
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    transition: all 0.3s ease;
}
.disabled-card {
    cursor: not-allowed;
    pointer-events: none;
    opacity: 0.5;
}

.employee-selection-card {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 0.2rem;
    padding: 0.3rem;
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
    width: 18px;
    height: 18px;
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
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
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
    padding: 0;
    /* box-shadow: var(--shadow-inset); */
}

.date-picker-grid {
    min-height: 8rem;

    width: 100%;
    display: flex;
    justify-content: center;
    padding: 1rem 0rem;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.date-picker-item {
    min-width: 6rem;
    justify-content: center;
    flex-grow: 1;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.15rem;
    position: relative;
    cursor: pointer;
    border-radius: 12px;
    transition: all 0.3s ease;
    border: 2px solid var(--border);
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
/* button */
.btn-modern {
    display: flex;

    align-items: center;
    padding: 0.75rem 1rem;
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
    background: var(--hcis-emerald-600);
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
.shadow-unix2 {
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
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
    z-index: 10000;
    animation: slideIn 0.3s ease-out;
    min-width: 300px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
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

.notification.success {
    background: var(--gradient-success);
}

.notification.error {
    background: linear-gradient(135deg, var(--hcis-red-500) 0%, var(--hcis-red-600) 100%);
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
    z-index: 1000;
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
    border-radius: 0px;
}

::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 0px;
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
    .header-info {
        /* flex-direction: column; */
        /* text-align: center; */
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

    .nav-controls {
        flex-direction: column;
        gap: 1rem;
    }

    .btn-nav {
        width: 100%;
        max-width: 200px;
    }

    /* .search-filter-section {
        flex-direction: column;
        flex: 1;
        align-items: stretch;
    } */

    .filters-container {
        width: 100%;

        gap: 0.5rem;
    }

    .cards-grid {
        grid-template-columns: 1fr;
    }

    .shift-type-options {
        grid-template-columns: 1fr;
    }

    .date-range-inputs {
        flex-direction: column;
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

    .modal-header {
        padding: 1rem;
    }
    .modal-header.details {
        padding: 0.6rem;
    }

    .modal-body {
        position: relative;

        padding: 1rem;
    }

    .modal-footer {
        padding: 1rem;
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
        padding: 0.4rem;
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
    .shift-badge.large {
        padding: 0rem 0rem;
        font-weight: 700;
        font-size: 0.8rem;
    }
    .sm-nav {
        height: 100%;
        font-size: 0.9rem;
    }
    .filter-btn {
        gap: 2;
        flex: 1;
        flex-grow: 1;
        padding: 0.5rem;
    }
    .button-header {
        margin-top: 0.7rem;
        font-size: 0.8rem !important;
    }

    .title-header {
        font-size: 1.1rem !important;
    }

    .sub-title-header {
        font-size: 0.8rem !important;
    }

    .subtitle {
        font-size: 0.85rem;
    }

    .control-panel {
        padding: 0.75rem;
    }

    .nav-controls {
        margin-bottom: 1rem;
        padding-bottom: 1rem;
    }

    .search-filter-section {
        gap: 0.75rem;
    }
    .search-container {
        display: flex;
        flex-grow: 1;
        flex: 1;
    }

    .table-wrapper {
        border-radius: 12px;
        overflow-x: auto;
    }

    .sticky-column {
        min-width: 100px;
        position: relative;
        left: auto;
        padding: 0.5rem 0.5rem;
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
        padding: 0.5rem 0rem;
    }

    .employee-name {
        font-size: 0.9rem;
    }

    .employee-id {
        font-size: 0.75rem;
    }

    .shift-cell {
        padding: 0.2rem;
    }
    .table-wrapper thead th {
        padding: 0.5rem;
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
        margin-bottom: 0.2rem;
        padding-bottom: 0.75rem;
    }
    .modal-container {
        width: 95%;
    }
    .modal-container.details {
        max-width: none;
    }
    .modal-container.assign-modal {
        width: 95%;
        max-height: 98vh;
    }
    element.style {
        --el-dialog-width: 95%;
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
        padding: 1rem;
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
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
        background: white;
        /* color: black !important; */
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

.opa-100 {
    opacity: 100%;
}

.opa-0 {
    opacity: 0%;
}

/* Component Specific Utilities */
.fade {
    animation: fade 0.5s ease-in-out;
}
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
