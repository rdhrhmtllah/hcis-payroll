<template>
    <div class="container-fluid hris-dashboard">
        <!-- Header Section -->
        <div v-if="!loadingTop" class="row mb-4 gap-2 gap-md-0 fade-in">
            <div class="col-md-6 col-12 d-none d-md-block">
                <div class="row m-0 dashboard-header">
                    <div class="col-md-6">
                        <h2 class="mb-0">Employee Dashboard</h2>
                        <p class="text-muted mb-0">
                            Halo,
                            {{ capitalize(namaKaryawan ? namaKaryawan.split(' ').slice(0, 2).join(' ') : 'undefined') }}
                        </p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="current-date">
                            <span class="date">{{ getCutoffPeriod() }}</span>
                            <span class="time">{{ currentTime }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-12">
                <div
                    class="content-container text-white p-3 h-100 d-flex justify-content-center align-items-center flex-column"
                >
                    <span class="fs-6">{{ `Hari ini SHIFT ${shiftHariIni}` }}</span>
                    <div class="d-flex justify-content-evenly w-100 align-items-center">
                        <span class="fs-1 fw-bold">{{ Jam_Masuk }} </span>
                        <span><i class="bi bi-three-dots fs-3"></i></span>
                        <span class="fs-1 fw-bold">{{ Jam_Keluar }}</span>
                    </div>
                    <span
                        class="btn-modern btn-primary px-2 flex-shrink-1 d-flex align-items-center w-100 text-center justify-content-center rounded-4 py-2 gradientPrimary"
                        ><i class="bi bi-clock-history me-2 d-flex justify-content-center align-items-center"></i> Besok
                        Shift {{ shiftBesok }}
                    </span>
                </div>
            </div>
        </div>
        <!-- Header Section -->

        <div v-else class="row mb-4 gap-2 gap-md-0 fade-in">
            <div class="col-md-6 col-12 d-none d-md-block">
                <div class="row m-0 dashboard-header skeleton-container">
                    <div class="col-md-6">
                        <div class="skeleton-text" style="width: 60%; height: 28px; margin-bottom: 8px"></div>
                        <div class="skeleton-text" style="width: 80%; height: 16px"></div>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="current-date">
                            <div
                                class="skeleton-text"
                                style="width: 120px; height: 20px; display: inline-block; margin-right: 8px"
                            ></div>
                            <div class="skeleton-text" style="width: 80px; height: 20px; display: inline-block"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-12">
                <div
                    class="content-container text-white p-3 h-100 skeleton-container d-flex justify-content-center align-items-center flex-column"
                >
                    <div class="skeleton-text" style="width: 40%; height: 18px; margin-bottom: 16px"></div>
                    <div class="d-flex justify-content-evenly w-100 align-items-center mb-3">
                        <div class="skeleton-text" style="width: 25%; height: 48px"></div>
                        <div class="skeleton-text" style="width: 24px; height: 24px"></div>
                        <div class="skeleton-text" style="width: 25%; height: 48px"></div>
                    </div>
                    <div class="skeleton-button" style="width: 100%; height: 40px; border-radius: 16px"></div>
                </div>
            </div>
        </div>
        <!-- Quick Stats Section -->

        <div v-if="!loadingQuick" class="row d-flex justify-content-center mb-3 fade-in align-items-center">
            <div class="col-md-12">
                <div class="leave-info-panel fade-in">
                    <div class="container-fluid px-0">
                        <div class="row g-2 g-md-3 justify-content-center">
                            <!-- Hutang Cuti -->
                            <div class="col-4">
                                <div class="info-card bg-white rounded shadow-sm p-3 h-100 position-relative">
                                    <div
                                        class="d-flex align-items-start h-100 flex-column flex-md-row justify-content-start justify-content-md-start w-100"
                                    >
                                        <div class="icon-circle bg-danger-light me-2">
                                            <i class="fas fa-exclamation-triangle text-danger"></i>
                                        </div>
                                        <div>
                                            <div class="info-value text-dark fw-bold fs-4">
                                                {{ totalTerlambat
                                                }}<span class="p-1" style="font-size: 0.6rem; opacity: 0.5">menit</span>
                                            </div>
                                            <div class="info-label text-muted small">Total terlambat</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Sisa Cuti -->
                            <div v-if="LeaderTeam" class="col-4">
                                <div class="info-card bg-white rounded shadow-sm p-3 h-100 position-relative">
                                    <div
                                        class="d-flex align-items-start h-100 flex-column flex-md-row justify-content-start justify-content-md-start w-100"
                                    >
                                        <div style="" class="icon-circle bg-warning-light me-md-3">
                                            <i class="fas fa-clock text-warning"></i>
                                        </div>
                                        <div>
                                            <div class="info-value text-dark fw-bold fs-4">
                                                {{ jumlahAntrianPengajuan?.toFixed(0)
                                                }}<span class="p-1" style="font-size: 0.6rem; opacity: 0.5">Data</span>
                                            </div>
                                            <div class="info-label text-muted small">Antrian Izin</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="col-4">
                                <div class="info-card bg-white rounded shadow-sm p-3 h-100 position-relative">
                                    <div
                                        class="d-flex align-items-start h-100 flex-column flex-md-row justify-content-start justify-content-md-start w-100"
                                    >
                                        <div style="" class="icon-circle bg-warning-light me-md-3">
                                            <i class="fas fa-clock text-warning"></i>
                                        </div>
                                        <div>
                                            <div class="info-value text-dark fw-bold fs-4">
                                                {{ totalLembur?.toFixed(0)
                                                }}<span class="p-1" style="font-size: 0.6rem; opacity: 0.5">jam</span>
                                            </div>
                                            <div class="info-label text-muted small">Total Lembur</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Menunggu Persetujuan -->
                            <div class="col-4">
                                <div class="info-card bg-white rounded shadow-sm p-3 h-100 position-relative">
                                    <div
                                        class="d-flex align-items-start h-100 flex-column flex-md-row justify-content-start justify-content-md-start w-100"
                                    >
                                        <div class="icon-circle bg-primary-light me-3">
                                            <i class="fas fa-calendar-check text-primary"></i>
                                        </div>
                                        <div>
                                            <div class="info-value text-dark fw-bold fs-4">
                                                {{ sisaCuti }}
                                            </div>
                                            <div class="info-label text-muted small">Sisa Cuti</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div v-else class="row d-flex justify-content-center mb-3 align-items-center fade-in">
            <div class="col-md-12">
                <div class="leave-info-panel skeleton-container">
                    <div class="container-fluid px-0">
                        <div class="row g-2 g-md-3 justify-content-center">
                            <!-- Terlambat -->
                            <div class="col-4">
                                <div class="info-card bg-white rounded shadow-sm p-3 h-100 position-relative">
                                    <div
                                        class="d-flex align-items-start h-100 flex-column flex-md-row justify-content-start justify-content-md-start w-100"
                                    >
                                        <div
                                            class="skeleton-circle"
                                            style="width: 40px; height: 40px; border-radius: 50%; margin-right: 12px"
                                        ></div>
                                        <div style="flex: 1">
                                            <div
                                                class="skeleton-text"
                                                style="width: 60%; height: 28px; margin-bottom: 4px"
                                            ></div>
                                            <div class="skeleton-text" style="width: 90%; height: 14px"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Lembur -->
                            <div class="col-4">
                                <div class="info-card bg-white rounded shadow-sm p-3 h-100 position-relative">
                                    <div
                                        class="d-flex align-items-start h-100 flex-column flex-md-row justify-content-start justify-content-md-start w-100"
                                    >
                                        <div
                                            class="skeleton-circle"
                                            style="width: 40px; height: 40px; border-radius: 50%; margin-right: 12px"
                                        ></div>
                                        <div style="flex: 1">
                                            <div
                                                class="skeleton-text"
                                                style="width: 60%; height: 28px; margin-bottom: 4px"
                                            ></div>
                                            <div class="skeleton-text" style="width: 90%; height: 14px"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sisa Cuti -->
                            <div class="col-4">
                                <div class="info-card bg-white rounded shadow-sm p-3 h-100 position-relative">
                                    <div
                                        class="d-flex align-items-start h-100 flex-column flex-md-row justify-content-start justify-content-md-start w-100"
                                    >
                                        <div
                                            class="skeleton-circle"
                                            style="width: 40px; height: 40px; border-radius: 50%; margin-right: 12px"
                                        ></div>
                                        <div style="flex: 1">
                                            <div
                                                class="skeleton-text"
                                                style="width: 60%; height: 28px; margin-bottom: 4px"
                                            ></div>
                                            <div class="skeleton-text" style="width: 90%; height: 14px"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Main Content Section -->
        <div class="row flex-row-reverse">
            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Calendar -->
                <div v-if="!loadingCalendar" class="fade-in">
                    <div class="position-relative wrapper-calendar">
                        <div class="cardFloat position-absolute d-flex justify-content-between align-items-center">
                            <div class="fw-bold text-white d-flex align-items-center justify-content-center h-100">
                                <!-- <i
                                    class="bg-white text-black p-3 border-primary rounded-4 me-2 bi bi-cloud-sun d-flex justify-content-center align-items-center"
                                ></i> -->
                                {{ attendancePeriodLabel }}
                            </div>
                            <div class="d-flex gap-2">
                                <button
                                    @click="prevWeekAbsensi"
                                    class="btn-modern btn-primary p-1"
                                    style="border-radius: 8px !important"
                                >
                                    <i class="bi bi-arrow-left d-flex justify-content-center align-items-center"></i>
                                </button>
                                <button
                                    @click="nextWeekAbsensi"
                                    class="btn-modern btn-primary p-1"
                                    style="border-radius: 8px !important"
                                >
                                    <i class="bi bi-arrow-right d-flex justify-content-center align-items-center"></i>
                                </button>
                            </div>
                        </div>
                        <div class="cardIn position-absolute">
                            <div class="p-3 d-flex gap-1">
                                <div class="w-100" v-for="(data, idx) in visibleDateAbsen" :key="idx">
                                    <el-tooltip
                                        :content="getTooltipContent(data)"
                                        raw-content
                                        placement="bottom"
                                        :width="350"
                                    >
                                        <div class="text-center w-100">
                                            <div
                                                class="btn-modern btn-primary justify-content-center tanggalBar p-2"
                                                :class="getClassAbsensi(data)"
                                            >
                                                {{ getJustDate(data.Tanggal) }}
                                            </div>
                                            {{ getJustDay(data.Tanggal) }}
                                        </div>
                                    </el-tooltip>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="fade-in">
                    <div class="position-relative wrapper-calendar">
                        <!-- Card Float Skeleton -->
                        <div
                            class="cardFloat position-absolute d-flex justify-content-between align-items-center skeleton-container"
                        >
                            <div class="fw-bold text-white d-flex align-items-center justify-content-center h-100">
                                <div class="skeleton-text" style="width: 120px; height: 24px"></div>
                            </div>
                            <div class="d-flex gap-2">
                                <div
                                    class="skeleton-button"
                                    style="width: 32px; height: 32px; border-radius: 8px"
                                ></div>
                                <div
                                    class="skeleton-button"
                                    style="width: 32px; height: 32px; border-radius: 8px"
                                ></div>
                            </div>
                        </div>

                        <!-- Card In Skeleton -->
                        <div class="cardIn position-absolute skeleton-container">
                            <div class="p-3 d-flex gap-2">
                                <div class="w-100" v-for="i in 7" :key="i">
                                    <div class="text-center w-100">
                                        <div
                                            class="skeleton-date"
                                            style="width: 100%; height: 40px; border-radius: 8px; margin-bottom: 8px"
                                        ></div>
                                        <div
                                            class="skeleton-text"
                                            style="width: 60%; height: 16px; margin: 0 auto"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Quick Actions Folder System -->
                <div v-if="!loadingCard" class="p-3 px-md-3 px-4 mb-md-0 fade-in">
                    <div class="row justify-content-center w-100">
                        <div
                            class="col-md-4 col-4 mb-3 px-1 px-md-1"
                            v-for="folder in filteredMenuFolders"
                            :key="folder.id"
                        >
                            <!-- Menu Single (tanpa items) -->
                            <a
                                v-if="folder.isSingle"
                                :href="folder.url"
                                class="btn btn-action shadow-sm w-100"
                                :class="folder.class"
                            >
                                <i :class="folder.icon" style="margin-bottom: 0.7rem"></i>
                                <span>{{ folder.label }}</span>
                            </a>

                            <!-- Menu Folder (dengan items) -->
                            <button
                                v-else
                                @click="openFolder(folder)"
                                class="btn btn-action shadow-sm w-100"
                                :class="folder.class"
                            >
                                <i :class="folder.icon" style="margin-bottom: 0.7rem"></i>
                                <span>{{ folder.label }}</span>
                                <!-- <small class="folder-badge">{{
                                    folder.items.length
                                }}</small> -->
                            </button>
                        </div>
                    </div>
                </div>
                <div v-else class="fade-in p-3 px-md-5 px-4 mb-md-3 skeleton-container">
                    <div class="row justify-content-center">
                        <!-- Repeat for each quick action (typically 3-6 items) -->
                        <div class="col-md-4 col-4 mb-3 px-1 px-md-1" v-for="i in 6" :key="i">
                            <div class="btn-action-skeleton">
                                <div
                                    class="skeleton-icon"
                                    style="width: 24px; height: 24px; margin: 0 auto 0.7rem; border-radius: 50%"
                                ></div>
                                <div class="skeleton-text" style="width: 80%; height: 16px; margin: 0 auto"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <section v-if="!LeaderTeam" class="profile-section shadow-sm leave-requests fade-in mb-4">
                    <div class="section-header d-flex justify-content-between align-items-center flex-wrap">
                        <div class="fs-5 fw-bold">Riwayat Absensi</div>

                        <!-- filter tanggal -->
                        <div class="filter-container">
                            <el-date-picker
                                v-model="selectedDateAbsensi"
                                type="date"
                                placeholder="Pilih Tanggal"
                                format="DD-MM-YYYY"
                                :default-value="null"
                                value-format="YYYY-MM-DD"
                                @change="handleDateChange"
                                :disabled="loadingRiwayatAbsen"
                                style="width: 150px"
                            >
                            </el-date-picker>
                        </div>
                    </div>

                    <div class="section-content">
                        <!-- loading state -->
                        <div v-if="loadingRiwayatAbsen" class="text-center py-4">
                            <div class="loading-spinner"></div>
                            <p class="mt-2 text-muted">Memuat data...</p>
                        </div>

                        <!-- jika ada data -->
                        <div class="p-2" v-else-if="allDataRiwayatAbsen.length">
                            <div
                                v-for="(item, idx) in allDataRiwayatAbsen"
                                :key="idx"
                                class="request-item d-flex justify-content-between align-items-center px-2"
                                @click="showDetailAbsen(item)"
                            >
                                <div>
                                    <div class="request-type fw-bold text-dark">
                                        {{ formatDate(item.Tanggal) }}
                                    </div>
                                    <div class="request-date">
                                        <small class="text-muted">{{ getDayName(item.Tanggal) }}</small>
                                    </div>
                                </div>
                                <div class="request-status">
                                    <span class="text-white status-badge bg-primary px-2 py-1 rounded-5">
                                        <i class="fas fa-user-clock me-1"></i>
                                        {{ item.JumlahAbsensi }} Absensi
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- jika tidak ada data -->
                        <div v-else class="empty-state">
                            <i class="fas fa-clipboard-list"></i>
                            <p>Tidak ada data absensi</p>
                            <small class="text-muted">Coba pilih tanggal lain atau periode berbeda</small>
                        </div>
                    </div>

                    <!-- pagination -->
                    <div class="pagination-container" v-if="peginationRiwayatAbsen && allDataRiwayatAbsen.length">
                        <button
                            class="btn btn-sm btn-pagination"
                            :disabled="!peginationRiwayatAbsen.prev_page_url || loadingRiwayatAbsen"
                            @click="getRiwayatAbsen(peginationRiwayatAbsen.prev_page_url)"
                        >
                            <i class="fas fa-chevron-left me-1 d-flex justify-content-center align-items-center"></i>
                        </button>

                        <div class="page-info">
                            Halaman
                            {{ peginationRiwayatAbsen.current_page }} dari
                            {{ peginationRiwayatAbsen.last_page }}
                        </div>

                        <button
                            class="btn btn-sm btn-pagination"
                            :disabled="!peginationRiwayatAbsen.next_page_url || loadingRiwayatAbsen"
                            @click="getRiwayatAbsen(peginationRiwayatAbsen.next_page_url)"
                        >
                            <i class="fas fa-chevron-right ms-1 d-flex justify-content-center align-items-center"></i>
                        </button>
                    </div>
                </section>
                <section v-else>
                    <div v-if="!loadingShift" class="p3 fade-in">
                        <div class="shift-timeline">
                            <div class="card shadow-sm border-0">
                                <div
                                    class="card-header bg-gradient-primary text-white d-flex justify-content-between align-items-center"
                                >
                                    <h5 class="mb-0 text-white">
                                        <i class="bi bi-calendar-week me-2 text-white"></i>Jadwal Shift
                                    </h5>
                                    <div class="date-range fw-bold">
                                        {{ getJustYear(dataShift[0]?.Tanggal) }}
                                    </div>
                                </div>

                                <div class="card-body pt-4 pb-3">
                                    <!-- 7-Day Timeline -->
                                    <div class="timeline-container">
                                        <div class="timeline-days">
                                            <div
                                                v-for="day in dataShift"
                                                :key="day.Tanggal"
                                                class="timeline-day"
                                                :class="{
                                                    active: day.isToday,
                                                    'day-off': day.shiftType === 'NO SHIFT',
                                                }"
                                                @click="selectDay(day)"
                                            >
                                                <div class="day-header">
                                                    <div class="day-name">
                                                        {{ day.Nama_Shift }}
                                                    </div>
                                                    <div class="day-date">
                                                        {{ formatTanggaldanBulan(day.Tanggal) }}
                                                    </div>
                                                </div>
                                                <div class="day-shift text-white">
                                                    <span class="shift-badge" :class="shiftBadgeClass(day)">
                                                        {{ day.Nama_Shift }}
                                                    </span>
                                                    <div class="shift-hours" v-if="day.Nama_Shift !== 'NO SHIFT'">
                                                        {{ day.Jam_Masuk }} -
                                                        {{ day.Jam_Keluar }}
                                                    </div>
                                                    <div v-else class="day-off-text">
                                                        <i class="bi bi-moon"></i>
                                                        OFF
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-3 justify-content-center align-items-center mt-3">
                                        <!-- <button
                                            @click="prevWeekShift"
                                            class="btn-modern btn-primary rounded-3 px-3 py-2"
                                        >
                                            <i
                                                class="bi bi-chevron-left d-flex justify-content-center align-items-center"
                                            ></i>
                                        </button>
                                        <div class="">
                                            {{
                                                getJustMonth(
                                                    dataShift[0]?.Tanggal
                                                )
                                            }}
                                        </div>
                                        <button
                                            @click="nextWeekShift"
                                            class="btn-modern btn-primary rounded-3 px-3 py-2"
                                        >
                                            <i
                                                class="bi bi-chevron-right d-flex justify-content-center align-items-center"
                                            ></i>
                                        </button> -->

                                        <div class="d-flex align-items-center justify-content-center">
                                            <button
                                                @click="prevWeekShift"
                                                class="btn-pagination btn-primary btn-modern"
                                            >
                                                <i
                                                    class="bi bi-chevron-left d-flex justify-content-center align-items-center"
                                                ></i>
                                            </button>
                                            <div class="">
                                                {{ getJustMonth(dataShift[0]?.Tanggal) }}
                                            </div>
                                            <button
                                                @click="nextWeekShift"
                                                class="btn-pagination btn-primary btn-modern"
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
                    </div>
                    <div v-else class="skeleton-container fade-in">
                        <div class="card shadow-sm border-0">
                            <!-- Card Header Skeleton -->
                            <div
                                class="card-header bg-gradient-primary text-white d-flex justify-content-between align-items-center"
                            >
                                <div class="d-flex align-items-center">
                                    <div
                                        class="skeleton-icon"
                                        style="width: 24px; height: 24px; margin-right: 8px; border-radius: 4px"
                                    ></div>
                                    <div class="skeleton-text" style="width: 120px; height: 24px"></div>
                                </div>
                                <div class="skeleton-text" style="width: 60px; height: 24px"></div>
                            </div>

                            <!-- Card Body Skeleton -->
                            <div class="card-body pt-4 pb-3">
                                <!-- 7-Day Timeline Skeleton -->
                                <div class="timeline-container">
                                    <div class="timeline-days">
                                        <div class="timeline-day skeleton-day" v-for="i in 7" :key="i">
                                            <div class="day-header">
                                                <div
                                                    class="skeleton-text"
                                                    style="width: 80%; height: 16px; margin-bottom: 8px"
                                                ></div>
                                                <div class="skeleton-text" style="width: 60%; height: 14px"></div>
                                            </div>
                                            <div class="day-shift">
                                                <div
                                                    class="skeleton-badge"
                                                    style="
                                                        width: 80%;
                                                        height: 24px;
                                                        border-radius: 12px;
                                                        margin-bottom: 4px;
                                                    "
                                                ></div>
                                                <div class="skeleton-text" style="width: 90%; height: 14px"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Navigation Controls Skeleton -->
                                <div class="d-flex gap-3 justify-content-center align-items-center mt-3">
                                    <div
                                        class="skeleton-button"
                                        style="width: 40px; height: 40px; border-radius: 8px"
                                    ></div>
                                    <div class="skeleton-text" style="width: 100px; height: 20px"></div>
                                    <div
                                        class="skeleton-button"
                                        style="width: 40px; height: 40px; border-radius: 8px"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <!-- approver izin -->
                <!-- <div class="">
                    <div class="card leave-requests-card">
                        <div
                            class="card-header d-flex justify-content-between align-items-center"
                        >
                            <h5 class="mb-0">Approver izin</h5>
                        </div>
                        <div class="card-body" style="max-height: 13rem">
                            <div
                                v-for="(data, idx) in nama_Approver"
                                :key="idx"
                                class="row fs-6 mt-3 ps-3 align-items-center justify-content-between"
                            >
                                <div
                                    v-tooltip="data.Nama"
                                    style="font-size: 0.9rem !important"
                                    class="col-6 p-0 d-flex justify-content-start text-start align-items-center"
                                >
                                    {{
                                        truncateText(capitalize(data?.Nama), 30)
                                    }}
                                </div>

                                <div
                                    style="font-size: 0.7rem !important"
                                    class="col-6 p-0 d-flex justify-content-end px-2 align-items-center"
                                >
                                    <span
                                        class=""
                                        :class="
                                            data.Status == 'Disetujui'
                                                ? 'badge-lembur-selesai'
                                                : 'badge-lembur'
                                        "
                                    >
                                        Approver {{ data.order_flow }}
                                    </span>
                                </div>
                            </div>


                        </div>
                    </div>
                </div> -->

                <!-- anggota team -->
                <!-- <div class="">
                    <div class="card leave-requests-card">
                        <div
                            class="card-header d-flex justify-content-between align-items-center"
                        >
                            <h5 class="mb-0">Anggota Team</h5>
                            <div class="d-flex gap-2">
                                <button
                                    @click="prevPageAnggota"
                                    class="btn-modern btn-primary p-1"
                                    style="border-radius: 8px !important"
                                >
                                    <i
                                        class="bi bi-arrow-left d-flex justify-content-center align-items-center"
                                    ></i>
                                </button>
                                <span
                                    >{{ currentPageAnggota }}
                                    of
                                    {{ totalPagesAnggota }}</span
                                >
                                <button
                                    @click="nextPageAnggota"
                                    class="btn-modern btn-primary p-1"
                                    style="border-radius: 8px !important"
                                >
                                    <i
                                        class="bi bi-arrow-right d-flex justify-content-center align-items-center"
                                    ></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body" style="height: 13rem">
                            <div
                                v-for="(data, idx) in paginatedAnggota"
                                :key="idx"
                                class="row fs-6 mt-3 ps-3"
                            >
                                <div
                                    v-tooltip="data.Nama"
                                    style="font-size: 0.9rem !important"
                                    class="col-4 p-0 d-flex justify-content-start text-start align-items-center"
                                >
                                    {{
                                        truncateText(capitalize(data?.Nama), 10)
                                    }}
                                </div>
                                <div
                                    v-tooltip="data.divisi"
                                    style="
                                        font-size: 0.9rem !important;
                                        cursor: pointer;
                                    "
                                    class="col-4 p-0 d-flex justify-content-center align-items-center"
                                >
                                    {{ truncateText(data.divisi, 10) }}
                                </div>
                                <div
                                    v-tooltip="
                                        data.HP == '-'
                                            ? 'silahkan hubungi admin untuk mengubah No HP'
                                            : ''
                                    "
                                    style="font-size: 0.7rem !important"
                                    class="col-4 p-0 d-flex justify-content-center align-items-center"
                                >
                                    <span
                                        class=""
                                        :class="
                                            data.Status == 'Disetujui'
                                                ? 'badge-lembur-selesai'
                                                : 'badge-lembur'
                                        "
                                    >
                                        {{ data.HP }}
                                    </span>
                                </div>
                            </div>


                        </div>
                    </div>
                </div> -->

                <!-- <div class="card holiday-card mb-4">
                    <div
                        class="card-header d-flex justify-content-between align-items-center"
                    >
                        <h5 class="mb-0">Holiday Calendar</h5>
                        <button
                            class="btn btn-sm btn-outline-primary"
                            @click="viewAllHolidays"
                        >
                            View All
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="upcoming-holiday" v-if="upcomingHoliday">
                            <div class="holiday-date">
                                {{ upcomingHoliday.date }}
                            </div>
                            <div class="holiday-name">
                                {{ upcomingHoliday.name }}
                            </div>
                            <div class="holiday-days">
                                {{ upcomingHoliday.daysLeft }} days left
                            </div>
                        </div>
                        <div class="holiday-list">
                            <div
                                class="holiday-item"
                                v-for="holiday in nextHolidays"
                                :key="holiday.id"
                            >
                                <div class="holiday-item-date">
                                    {{ holiday.date }}
                                </div>
                                <div class="holiday-item-name">
                                    {{ holiday.name }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div> -->
            </div>
            <!-- Left Column -->
            <div class="col-lg-8 mb-1">
                <div class="row justify-content-between fade-in">
                    <!-- Leave Requests -->

                    <div v-if="LeaderTeam" class="col-md-6">
                        <section class="profile-section shadow-sm leave-requests fade-in mb-4">
                            <div class="section-header d-flex justify-content-between align-items-center flex-wrap">
                                <div class="fs-5 fw-bold">Riwayat Absensi</div>

                                <!-- filter tanggal -->
                                <div class="filter-container">
                                    <el-date-picker
                                        v-model="selectedDateAbsensi"
                                        type="date"
                                        placeholder="Pilih Tanggal"
                                        format="DD-MM-YYYY"
                                        :default-value="null"
                                        value-format="YYYY-MM-DD"
                                        @change="handleDateChange"
                                        :disabled="loadingRiwayatAbsen"
                                        style="width: 150px"
                                    >
                                    </el-date-picker>
                                </div>
                            </div>

                            <div class="section-content leader">
                                <!-- loading state -->
                                <div v-if="loadingRiwayatAbsen" class="text-center py-4">
                                    <div class="loading-spinner"></div>
                                    <p class="mt-2 text-muted">Memuat data...</p>
                                </div>

                                <!-- jika ada data -->
                                <div class="p-2" v-else-if="allDataRiwayatAbsen.length">
                                    <div
                                        v-for="(item, idx) in allDataRiwayatAbsen"
                                        :key="idx"
                                        class="request-item d-flex justify-content-between align-items-center px-2"
                                        @click="showDetailAbsen(item)"
                                    >
                                        <div>
                                            <div class="request-type fw-bold text-dark">
                                                {{ formatDate(item.Tanggal) }}
                                            </div>
                                            <div class="request-date">
                                                <small class="text-muted">{{ getDayName(item.Tanggal) }}</small>
                                            </div>
                                        </div>
                                        <div class="request-status">
                                            <span class="text-white status-badge bg-primary px-2 py-1 rounded-5">
                                                <i class="fas fa-user-clock me-1"></i>
                                                {{ item.JumlahAbsensi }} Absensi
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- jika tidak ada data -->
                                <div v-else class="empty-state">
                                    <i class="fas fa-clipboard-list"></i>
                                    <p>Tidak ada data absensi</p>
                                    <small class="text-muted">Coba pilih tanggal lain atau periode berbeda</small>
                                </div>
                            </div>

                            <!-- pagination -->
                            <!-- <div class="pagination-container">
                                <button
                                    class="btn btn-sm btn-pagination"
                                    :disabled="
                                        !peginationRiwayatAbsen.prev_page_url ||
                                        loadingRiwayatAbsen
                                    "
                                    @click="
                                        getRiwayatAbsen(
                                            peginationRiwayatAbsen.prev_page_url
                                        )
                                    "
                                >
                                    <i
                                        class="fas fa-chevron-left me-1 d-flex justify-content-center align-items-center"
                                    ></i>
                                </button>

                                <div class="page-info">

                                    {{ peginationRiwayatAbsen.current_page }}
                                    of
                                    {{ peginationRiwayatAbsen.last_page }}
                                </div>

                                <button
                                    class="btn btn-sm btn-pagination"
                                    :disabled="
                                        !peginationRiwayatAbsen.next_page_url ||
                                        loadingRiwayatAbsen
                                    "
                                    @click="
                                        getRiwayatAbsen(
                                            peginationRiwayatAbsen.next_page_url
                                        )
                                    "
                                >
                                    <i
                                        class="fas fa-chevron-right ms-1 d-flex justify-content-center align-items-center"
                                    ></i>
                                </button>
                            </div> -->
                            <div class="d-flex align-items-center justify-content-center pt-2">
                                <button
                                    class="btn-pagination btn-primary btn-modern"
                                    :disabled="!peginationRiwayatAbsen.prev_page_url || loadingRiwayatAbsen"
                                    @click="getRiwayatAbsen(peginationRiwayatAbsen.prev_page_url)"
                                >
                                    <i class="bi bi-chevron-left d-flex justify-content-center align-items-center"></i>
                                </button>
                                <div class="page-info">
                                    {{ peginationRiwayatAbsen.current_page }}
                                    of
                                    {{ peginationRiwayatAbsen.last_page }}
                                </div>
                                <button
                                    :disabled="!peginationRiwayatAbsen.next_page_url || loadingRiwayatAbsen"
                                    @click="getRiwayatAbsen(peginationRiwayatAbsen.next_page_url)"
                                    class="btn-pagination btn-primary btn-modern"
                                >
                                    <i class="bi bi-chevron-right d-flex justify-content-center align-items-center"></i>
                                </button>
                            </div>
                        </section>
                    </div>
                    <div v-else class="col-md-6">
                        <div v-if="!loadingLembur" style="height: 25rem" class="lembur-history-card card">
                            <div class="card-header d-flex justify-content-between align-items-center w-100">
                                <h5 class="mb-0">Riwayat Lembur</h5>
                                <div class="lembur-pagination justify-content-end w-100">
                                    <button
                                        :disabled="
                                            paginationLembur.current_page <= 1 || !paginationLembur.prev_page_url
                                        "
                                        @click="getAllLembur(paginationLembur.prev_page_url)"
                                        class="lembur-pagination-btn btn-primary btn-modern"
                                    >
                                        <i
                                            class="bi bi-arrow-left d-flex justify-content-center align-items-center"
                                        ></i>
                                    </button>
                                    <span class=""
                                        >{{ paginationLembur.current_page }} of {{ paginationLembur.last_page }}</span
                                    >
                                    <button
                                        :disabled="paginationLembur.current_page == paginationLembur.last_page"
                                        @click="getAllLembur(paginationLembur.next_page_url)"
                                        class="lembur-pagination-btn btn-primary btn-modern"
                                    >
                                        <i
                                            class="bi bi-arrow-right d-flex justify-content-center align-items-center"
                                        ></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div v-if="allDataLembur.length === 0" class="lembur-empty">
                                    <i class="bi bi-clock-history d-flex justify-content-center align-items-center"></i>
                                    <p>Belum ada riwayat lembur</p>
                                </div>
                                <div
                                    v-else
                                    v-for="(data, idx) in allDataLembur"
                                    :key="idx"
                                    class="lembur-item"
                                    @click="showLemburDetail(data)"
                                >
                                    <div class="lembur-date lembur-transaction">
                                        <div class="lembur-no">
                                            {{ data.No_Transaksi || '-' }}
                                        </div>
                                        <div class="lembur-jenis-inline">
                                            {{ data.Jenis_Lembur_Label || 'Lembur' }}
                                        </div>
                                    </div>
                                    <div class="lembur-date lembur-range">
                                        {{ formatTanggal_Lembur(data.Tanggal_Lembur_Dari, data.Tanggal_Lembur_Sampai) }}
                                    </div>
                                    <div class="lembur-duration lembur-hours">{{ data.durasi }} jam</div>
                                    <!-- <div
                                        class="lembur-status"
                                        :class="
                                            'status-' +
                                            data.Status.toLowerCase()
                                        "
                                    >
                                        {{ data.Status }}
                                    </div> -->
                                </div>
                            </div>
                        </div>
                        <div style="height: 25rem" v-else class="skeleton-container card-loading-state">
                            <div class="card-loading-label">Memuat data...</div>
                            <div class="lembur-history-card card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div class="skeleton-text" style="width: 120px; height: 24px"></div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <div
                                            class="skeleton-button"
                                            style="width: 32px; height: 32px; border-radius: 8px"
                                        ></div>
                                        <div class="skeleton-text" style="width: 60px; height: 20px"></div>
                                        <div
                                            class="skeleton-button"
                                            style="width: 32px; height: 32px; border-radius: 8px"
                                        ></div>
                                    </div>
                                </div>
                                <div style="height: 21rem" class="card-body">
                                    <div class="lembur-skeleton" v-for="i in 5" :key="i">
                                        <div class="lembur-skeleton-date"></div>
                                        <div class="lembur-skeleton-duration"></div>
                                        <div class="lembur-skeleton-status"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="shift-timeline h-full">
                            <div class="card shadow-sm border-0">
                                <div
                                    class="card-header bg-gradient-primary text-white d-flex justify-content-between align-items-center"
                                >
                                    <h5 class="mb-0 text-white">
                                        <i class="bi bi-calendar-week me-2 text-white"></i>Statistik Absensi
                                    </h5>
                                    <!-- <select
                                        @change="getChartData"
                                        v-model="chartStateLoad"
                                        class="btn-modern btn-primary p-1"
                                        style="border-radius: 8px !important"
                                    >
                                        <option value="week">Perminggu</option>
                                        <option value="month">Perbulan</option>
                                        <option value="year">Pertahun</option>
                                    </select> -->
                                    <el-date-picker
                                        v-model="chartStateLoad"
                                        type="month"
                                        :default-value="new Date()"
                                        format="MM-YYYY"
                                        value-format="MM-YYYY"
                                        class="bg-primary text-white"
                                        @change="getChartData"
                                        :placeholder="currentMonthAndDate"
                                        style="width: 140px"
                                    />
                                </div>
                                <div
                                    v-if="!loadingChart"
                                    style="height: 21rem"
                                    class="p-3 d-md-flex py-0 py-md-3 flex-column justify-content-center gap-2"
                                >
                                    <div id="chart-donut">
                                        <apexchart type="donut" width="250" :options="chartOptions" :series="series">
                                        </apexchart>
                                    </div>
                                    <div class="legend-manual row">
                                        <div
                                            class="legend-item col-6"
                                            v-for="(label, index) in chartOptions.labels"
                                            :key="index"
                                        >
                                            <span
                                                class="legend-dot"
                                                :style="{
                                                    backgroundColor: chartOptions.colors[index],
                                                }"
                                            ></span>
                                            <span class="legend-text">
                                                {{ label }}:
                                                {{ series[index] ?? 0 }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <!-- Skeleton -->
                                <!-- Card Body Skeleton -->
                                <div
                                    v-else
                                    style="height: 21rem"
                                    class="p-7 d-md-flex flex-column justify-content-center gap-2"
                                >
                                    <!-- Donut Chart Placeholder -->
                                    <div id="chart-donut" class="d-flex justify-content-center">
                                        <div
                                            class="skeleton-chart my-2"
                                            style="width: 225px; height: 225px; border-radius: 50%"
                                        ></div>
                                    </div>

                                    <!-- Legend Placeholder -->
                                    <div class="legend-manual row">
                                        <div class="legend-item col-6 mb-2" v-for="i in 4" :key="i">
                                            <div class="d-flex align-items-center">
                                                <div
                                                    class="skeleton-legend-dot"
                                                    style="
                                                        width: 12px;
                                                        height: 12px;
                                                        border-radius: 50%;
                                                        margin-right: 8px;
                                                    "
                                                ></div>
                                                <div
                                                    class="skeleton-legend-text"
                                                    style="width: 80px; height: 16px"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Chart Container (opsional) -->
                <div v-if="LeaderTeam" class="card summary-card fade-in">
                    <div class="card-header leader gap-4 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-bold fs-5 fw-bold">Ringkasan Tim</h5>
                        <div class="d-flex align-items-center gap-2">
                            <div class="search-container">
                                <i
                                    class="bi bi-search search-icon d-flex justify-content-center align-items-center"
                                ></i>
                                <input
                                    type="text"
                                    class="search-input"
                                    v-model="serachQueryRingkasanTim"
                                    @input="onSearchRingkasanTim"
                                    placeholder="Cari nama"
                                />
                            </div>
                        </div>
                    </div>
                    <div v-if="!loadingRingkasan" class="card-body p-0">
                        <div v-for="(data, idx) in paginatedRingkasanTim" :key="idx" class="team-member">
                            <div class="member-rank d-md-flex d-none">
                                {{ getInitials(data.nama) }}
                            </div>
                            <div class="member-details">
                                <div class="d-flex align-items-center">
                                    <div class="member-rank d-flex d-md-none">
                                        {{ getInitials(data.nama) }}
                                    </div>
                                    <div class="member-name d-md-block d-none">
                                        {{ truncateText(capitalize(data.nama), 30) }}
                                    </div>
                                    <div class="member-name d-md-none d-block" v-tooltip="capitalize(data.nama)">
                                        {{ truncateText(capitalize(data.nama), 10) }}
                                    </div>

                                    <span class="badge shift-badge text-black"
                                        >{{ data.shift_hari_ini }} ({{
                                            data.jam_masuk_hari_ini + ' - ' + data.jam_keluar_hari_ini
                                        }})</span
                                    >
                                    <div class="status-badge present d-md-none d-block">
                                        {{ data.status_hari_ini }}
                                    </div>
                                </div>
                                <div class="member-stats">
                                    <div class="stat-badge">
                                        <span class="badge-value">{{ data.total_hadir + '/' + data.total_hari }}</span>
                                        <span class="badge-label">{{ data.status_hari_ini }}</span>
                                    </div>
                                    <div class="stat-badge">
                                        <span class="badge-value">{{ data.total_keterlambatan_menit }} Menit</span>
                                        <span class="badge-label">Terlambat</span>
                                    </div>
                                    <div class="stat-badge">
                                        <span class="badge-value">{{ data.total_izin }} Hari</span>
                                        <span class="badge-label">Izin</span>
                                    </div>
                                    <div class="stat-badge">
                                        <span class="badge-value">{{ data.total_sakit }} Hari</span>
                                        <span class="badge-label">Sakit</span>
                                    </div>
                                    <div class="stat-badge">
                                        <span class="badge-value">{{ data.total_lembur_jam }} Jam</span>
                                        <span class="badge-label">Lembur</span>
                                    </div>
                                    <div class="stat-badge">
                                        <span class="badge-value">{{ data.total_cuti }} Hari</span>
                                        <span class="badge-label">Cuti</span>
                                    </div>
                                </div>
                            </div>
                            <div class="status-badge present d-md-block d-none">
                                {{ data.status_hari_ini }}
                            </div>
                        </div>
                    </div>
                    <div v-else class="card-body p-0 card-loading-state">
                        <div class="card-loading-label">Memuat data...</div>
                        <!-- Skeleton untuk anggota tim (dibuat 5 item sebagai contoh) -->
                        <div v-for="i in 5" :key="i" class="team-member skeleton-container">
                            <div class="member-rank skeleton-circle" style="width: 40px; height: 40px"></div>

                            <div class="member-details">
                                <div class="d-flex align-items-center">
                                    <div class="member-name">
                                        <div
                                            class="skeleton-text"
                                            style="width: 180px; height: 20px; margin-bottom: 8px"
                                        ></div>
                                    </div>
                                    <div
                                        class="skeleton-text"
                                        style="width: 120px; height: 24px; margin-left: 12px"
                                    ></div>
                                </div>

                                <div class="member-stats">
                                    <!-- Skeleton untuk stat badge (6 item) -->
                                    <div v-for="j in 6" :key="j" class="stat-badge">
                                        <div
                                            class="skeleton-text"
                                            style="width: 50px; height: 16px; margin-bottom: 4px"
                                        ></div>
                                        <div class="skeleton-text" style="width: 40px; height: 14px"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="status-badge skeleton-text" style="width: 80px; height: 28px"></div>
                        </div>
                    </div>
                    <div
                        style="border-top: 1px solid #e2e8f0"
                        class="d-flex py-3 justify-content-center align-items-center"
                    >
                        <div class="d-flex align-items-center justify-content-end">
                            <button
                                @click="prevPageRingkasanTim"
                                class="btn-pagination btn-primary btn-modern"
                                :disabled="loadingRingkasan || currentPageRingkasanTim <= 1"
                            >
                                <i class="bi bi-chevron-left d-flex justify-content-center align-items-center"></i>
                            </button>
                            <span class="mx-2 pagination-text"
                                >{{ currentPageRingkasanTim }} of {{ totalPagesRingkasanTim }}</span
                            >
                            <button
                                @click="nextPageRingkasanTim"
                                :disabled="loadingRingkasan || currentPageRingkasanTim == totalPagesRingkasanTim"
                                class="btn-pagination btn-primary btn-modern"
                            >
                                <i class="bi bi-chevron-right d-flex justify-content-center align-items-center"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div v-else class="col-md-12 fade-in">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Statistik Lembur Bulanan</h5>
                            <el-date-picker
                                v-model="yearLembur"
                                :default-value="new Date()"
                                type="year"
                                format="YYYY"
                                value-format="YYYY"
                                @change="getDataChartLembur"
                                :placeholder="new Date().getFullYear()"
                                style="width: 100px"
                            />
                        </div>
                        <div class="card-body chart" style="overflow: visible">
                            <apexchart
                                v-if="chartOptionsLembur && chartSeriesLembur"
                                type="bar"
                                height="250"
                                :options="chartOptionsLembur"
                                :series="chartSeriesLembur"
                            ></apexchart>
                        </div>
                    </div>
                </div>
                <section v-if="!LeaderTeam">
                    <div v-if="!loadingShift" class="p3 fade-in">
                        <div class="shift-timeline">
                            <div class="card shadow-sm border-0">
                                <div
                                    class="card-header bg-gradient-primary text-white d-flex justify-content-between align-items-center"
                                >
                                    <h5 class="mb-0 text-white">
                                        <i class="bi bi-calendar-week me-2 text-white"></i>Jadwal Shift
                                    </h5>
                                    <div class="date-range fw-bold">
                                        {{ getJustYear(dataShift[0]?.Tanggal) }}
                                    </div>
                                </div>

                                <div class="card-body pt-4 pb-3">
                                    <!-- 7-Day Timeline -->
                                    <div class="timeline-container">
                                        <div class="timeline-days">
                                            <div
                                                v-for="day in dataShift"
                                                :key="day.Tanggal"
                                                class="timeline-day"
                                                :class="{
                                                    active: day.isToday,
                                                    'day-off': day.shiftType === 'NO SHIFT',
                                                }"
                                                @click="selectDay(day)"
                                            >
                                                <div class="day-header">
                                                    <div class="day-name">
                                                        {{ day.Nama_Shift }}
                                                    </div>
                                                    <div class="day-date">
                                                        {{ formatTanggaldanBulan(day.Tanggal) }}
                                                    </div>
                                                </div>
                                                <div class="day-shift text-white">
                                                    <span class="shift-badge" :class="shiftBadgeClass(day)">
                                                        {{ day.Nama_Shift }}
                                                    </span>
                                                    <div class="shift-hours" v-if="day.Nama_Shift !== 'NO SHIFT'">
                                                        {{ day.Jam_Masuk }} -
                                                        {{ day.Jam_Keluar }}
                                                    </div>
                                                    <div v-else class="day-off-text">
                                                        <i class="bi bi-moon"></i>
                                                        OFF
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-3 justify-content-center align-items-center mt-3">
                                        <button
                                            @click="prevWeekShift"
                                            class="btn-modern btn-primary rounded-3 px-3 py-2"
                                        >
                                            <i
                                                class="bi bi-chevron-left d-flex justify-content-center align-items-center"
                                            ></i>
                                        </button>
                                        <div class="">
                                            {{ getJustMonth(dataShift[0]?.Tanggal) }}
                                        </div>
                                        <button
                                            @click="nextWeekShift"
                                            class="btn-modern btn-primary rounded-3 px-3 py-2"
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
                    <div v-else class="skeleton-container fade-in">
                        <div class="card shadow-sm border-0">
                            <!-- Card Header Skeleton -->
                            <div
                                class="card-header bg-gradient-primary text-white d-flex justify-content-between align-items-center"
                            >
                                <div class="d-flex align-items-center">
                                    <div
                                        class="skeleton-icon"
                                        style="width: 24px; height: 24px; margin-right: 8px; border-radius: 4px"
                                    ></div>
                                    <div class="skeleton-text" style="width: 120px; height: 24px"></div>
                                </div>
                                <div class="skeleton-text" style="width: 60px; height: 24px"></div>
                            </div>

                            <!-- Card Body Skeleton -->
                            <div class="card-body pt-4 pb-3">
                                <!-- 7-Day Timeline Skeleton -->
                                <div class="timeline-container">
                                    <div class="timeline-days">
                                        <div class="timeline-day skeleton-day" v-for="i in 7" :key="i">
                                            <div class="day-header">
                                                <div
                                                    class="skeleton-text"
                                                    style="width: 80%; height: 16px; margin-bottom: 8px"
                                                ></div>
                                                <div class="skeleton-text" style="width: 60%; height: 14px"></div>
                                            </div>
                                            <div class="day-shift">
                                                <div
                                                    class="skeleton-badge"
                                                    style="
                                                        width: 80%;
                                                        height: 24px;
                                                        border-radius: 12px;
                                                        margin-bottom: 4px;
                                                    "
                                                ></div>
                                                <div class="skeleton-text" style="width: 90%; height: 14px"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Navigation Controls Skeleton -->
                                <div class="d-flex gap-3 justify-content-center align-items-center mt-3">
                                    <div
                                        class="skeleton-button"
                                        style="width: 40px; height: 40px; border-radius: 8px"
                                    ></div>
                                    <div class="skeleton-text" style="width: 100px; height: 20px"></div>
                                    <div
                                        class="skeleton-button"
                                        style="width: 40px; height: 40px; border-radius: 8px"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Modal untuk Detail Lembur -->
                <div class="modal fade" id="lemburDetailModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header text-black">
                                <h5 class="modal-title">Detail Lembur</h5>
                                <button
                                    type="button"
                                    class="btn-close"
                                    data-bs-dismiss="modal"
                                    aria-label="Close"
                                ></button>
                            </div>
                            <div class="modal-body">
                                <div class="detail-item">
                                    <span class="detail-label">No Transaksi</span>
                                    <span class="detail-value" id="modalNoTransaksi"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Jenis Lembur</span>
                                    <span class="detail-value" id="modalJenisLembur"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Tanggal</span>
                                    <span class="detail-value" id="modalTanggal"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Durasi</span>
                                    <span class="detail-value" id="modalDurasi"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Status</span>
                                    <span class="detail-value" id="modalStatus"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Alasan</span>
                                    <span class="detail-value" id="modalAlasan"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Tanggal Kerja</span>
                                    <span class="detail-value" id="modalTanggalKerja"></span>
                                </div>
                                <hr class="my-2" />
                                <div class="detail-item">
                                    <span class="detail-label">Ditugaskan</span>
                                    <span class="detail-value" id="modalAssignedRange"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Aktual</span>
                                    <span class="detail-value" id="modalActualRange"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Durasi</span>
                                    <span class="detail-value" id="modalDurationInfo"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Kelengkapan Absensi</span>
                                    <div class="detail-value">
                                        <span class="lembur-status" id="modalAbsensiBadge"></span>
                                        <div class="mt-1 text-muted small" id="modalAbsensiStatus"></div>
                                        <div class="mt-1 text-muted small" id="modalAbsensiRequirement"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row justify-content-between">
                    <!-- pengajuan izin -->
                    <!-- <div v-if="!loadingIzin" class="col-md-6">
                        <div class="card leave-requests-card">
                            <div
                                class="card-header d-flex justify-content-between align-items-center"
                            >
                                <h5 class="mb-0">Pengajuan Izin</h5>
                                <div class="d-flex gap-2">
                                    <button
                                        :disabled="
                                            paginationIzin.current_page <= 1
                                        "
                                        @click="
                                            getAllIzin(
                                                paginationIzin.prev_page_url
                                            )
                                        "
                                        class="btn-modern btn-primary p-1"
                                        style="border-radius: 8px !important"
                                    >
                                        <i
                                            class="bi bi-arrow-left d-flex justify-content-center align-items-center"
                                        ></i>
                                    </button>
                                    <span
                                        >{{ paginationIzin.current_page }}
                                        of
                                        {{ paginationIzin.last_page }}</span
                                    >
                                    <button
                                        :disabled="
                                            paginationIzin.current_page ==
                                            paginationIzin.last_page
                                        "
                                        @click="
                                            getAllIzin(
                                                paginationIzin.next_page_url
                                            )
                                        "
                                        class="btn-modern btn-primary p-1"
                                        style="border-radius: 8px !important"
                                    >
                                        <i
                                            class="bi bi-arrow-right d-flex justify-content-center align-items-center"
                                        ></i>
                                    </button>
                                </div>
                            </div>
                            <div
                                class="card-body d-flex flex-column justify-content-between"
                                style="height: 13rem"
                            >
                                <div>
                                    <div
                                        class="leave-request py-3"
                                        v-for="(request, idx) in allDataIzin"
                                        :key="idx"
                                    >
                                        <div
                                            class="request-type"
                                            :class="
                                                getTypeClass(request.Tipe_Izin)
                                            "
                                        >
                                            {{ parseIzin(request.Tipe_Izin) }}
                                        </div>
                                        <div class="request-dates">
                                            {{
                                                formatTanggal_Izin(
                                                    request.Tanggal_Mulai,
                                                    request.Tanggal_Selesai
                                                )
                                            }}
                                        </div>
                                        <div
                                            class="request-status"
                                            :class="'status-' + request.status"
                                        >
                                            {{ request.status }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="col-md-6 skeleton-container">
                        <div class="card leave-requests-card">
                            <div
                                class="card-header d-flex justify-content-between align-items-center"
                            >
                                <div
                                    class="skeleton-text"
                                    style="width: 120px; height: 24px"
                                ></div>
                                <div class="d-flex gap-2 align-items-center">
                                    <div
                                        class="skeleton-button"
                                        style="
                                            width: 32px;
                                            height: 32px;
                                            border-radius: 8px;
                                        "
                                    ></div>
                                    <div
                                        class="skeleton-text"
                                        style="width: 60px; height: 20px"
                                    ></div>
                                    <div
                                        class="skeleton-button"
                                        style="
                                            width: 32px;
                                            height: 32px;
                                            border-radius: 8px;
                                        "
                                    ></div>
                                </div>
                            </div>

                            <div
                                class="card-body d-flex flex-column justify-content-between"
                                style="height: 19.1rem"
                            >
                                <div class="">
                                    <div
                                        class="leave-request-skeleton py-1"
                                        v-for="i in 5"
                                        :key="i"
                                    >
                                        <div
                                            class="d-flex justify-content-between align-items-center mb-3"
                                        >
                                            <div
                                                class="skeleton-badge"
                                                style="
                                                    width: 80px;
                                                    height: 24px;
                                                    border-radius: 12px;
                                                "
                                            ></div>
                                            <div
                                                class="skeleton-text"
                                                style="
                                                    width: 120px;
                                                    height: 16px;
                                                "
                                            ></div>
                                            <div
                                                class="skeleton-badge"
                                                style="
                                                    width: 70px;
                                                    height: 24px;
                                                    border-radius: 12px;
                                                "
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="skeleton-button"
                                    style="
                                        width: 100%;
                                        height: 40px;
                                        border-radius: 8px;
                                        margin-top: 16px;
                                    "
                                ></div>
                            </div>
                        </div>
                    </div> -->

                    <!-- tergabung pada team -->
                    <div class="col-md-6">
                        <!-- <div class="card leave-requests-card">
                            <div
                                class="card-header d-flex justify-content-between align-items-center"
                            >
                                <h5 class="mb-0">Tergabung pada team</h5>
                                <div class="d-flex gap-2">
                                    <button
                                        @click="prevPageTergabung"
                                        class="btn-modern btn-primary p-1"
                                        style="border-radius: 8px !important"
                                    >
                                        <i
                                            class="bi bi-arrow-left d-flex justify-content-center align-items-center"
                                        ></i>
                                    </button>
                                    <span
                                        >{{ currentPageTergabung }}
                                        of
                                        {{ totalPagesTergabung }}</span
                                    >
                                    <button
                                        @click="nextPageTergabung"
                                        class="btn-modern btn-primary p-1"
                                        style="border-radius: 8px !important"
                                    >
                                        <i
                                            class="bi bi-arrow-right d-flex justify-content-center align-items-center"
                                        ></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body" style="height: 13rem">
                                <div
                                    v-for="(data, idx) in paginatedTergabung"
                                    :key="idx"
                                    class="row fs-6 mt-3 ps-3"
                                >
                                    <div
                                        v-tooltip="data.Nama"
                                        style="font-size: 0.9rem !important"
                                        class="col-4 p-0 d-flex justify-content-start text-start align-items-center"
                                    >
                                        {{
                                            truncateText(
                                                capitalize(data?.Nama),
                                                10
                                            )
                                        }}
                                    </div>
                                    <div
                                        v-tooltip="data.divisi"
                                        style="
                                            font-size: 0.9rem !important;
                                            cursor: pointer;
                                        "
                                        class="col-4 p-0 d-flex justify-content-center align-items-center"
                                    >
                                        {{ truncateText(data.divisi, 10) }}
                                    </div>
                                    <div
                                        style="font-size: 0.7rem !important"
                                        class="col-4 p-0 d-flex justify-content-center align-items-center"
                                    >
                                        <span
                                            class=""
                                            :class="
                                                data.Status == 'Disetujui'
                                                    ? 'badge-lembur-selesai'
                                                    : 'badge-lembur'
                                            "
                                        >
                                            {{ data.HP }}
                                        </span>
                                    </div>
                                </div>


                            </div>
                        </div> -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Shift Information -->
        <div class="row"></div>
    </div>

    <!-- buletin  -->
    <!-- <div
        class="modal-backdrop"
        v-if="showModalBuletin"
        @click.self="showModalBuletin = false"
    >
        <div class="glass-modal">
            <div class="glass-modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-white">
                        {{ judulBerita }}
                    </h5>
                    <button
                        type="button"
                        class="close"
                        @click="showModalBuletin = false"
                    >
                        &times;
                    </button>
                </div>
                <div v-html="convertedHtml" class="modal-body"></div>
                <div class="modal-footer">

                    <button
                        @click="showModalBuletin = false"
                        type="button"
                        class="btn-modern btn-primary"
                    >
                        Mengerti
                    </button>
                </div>
            </div>
        </div>
    </div> -->

    <!-- Modal Menu buttons -->

    <div v-if="showFolderModal" class="folder-modal-overlay" @click.self="closeFolderModal">
        <div class="folder-modal-container">
            <div class="folder-modal-card">
                <!-- Header -->
                <div class="folder-modal-head">
                    <div class="folder-header-content">
                        <h5 class="folder-modal-title text-white">
                            <i
                                class="d-flex justify-content-center align-items-center me-2"
                                :class="currentFolder.icon"
                            ></i>
                            {{ currentFolder.label }}
                        </h5>
                        <div class="folder-subtitle">Pilih menu yang tersedia</div>
                    </div>
                    <button type="button" class="folder-close-btn" @click="closeFolderModal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="folder-modal-body">
                    <div class="folder-grid">
                        <button
                            v-for="item in currentFolder.items"
                            :key="item.id"
                            @click="handleFolderItemClick(item)"
                            class="folder-item-btn"
                            :class="item.class"
                        >
                            <div class="folder-item-icon">
                                <i :class="item.icon"></i>
                            </div>
                            <span class="folder-item-label">{{ item.label }}</span>
                        </button>
                    </div>
                </div>

                <!-- Footer -->
                <div class="folder-modal-footer">
                    <button @click="closeFolderModal" type="button" class="folder-close-action">
                        <i class="fas fa-arrow-left me-2"></i>
                        Kembali
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal detail absen -->
    <div v-if="showModalDetailAbsen" class="absensi-modal-overlay" @click.self="showModalDetailAbsen = false">
        <div class="absensi-modal-container">
            <div class="absensi-modal-card">
                <!-- Header -->
                <div class="absensi-modal-head">
                    <div class="absensi-header-content">
                        <h5 class="absensi-modal-title text-white">
                            Detail Absensi -
                            {{ capitalize(namaKaryawan ? namaKaryawan.split(' ').slice(0, 2).join(' ') : 'undefined') }}
                        </h5>
                        <div class="absensi-shift-badge">
                            <i class="fas fa-clock"></i>
                            {{ formatDate(detailCheckinout?.Tanggal) }} |
                            {{ detailCheckinout?.Nama_Shift }}
                        </div>
                    </div>
                    <button type="button" class="absensi-close-btn" @click="showModalDetailAbsen = false">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="absensi-modal-body">
                    <!-- Info Shift -->
                    <div class="absensi-info-card">
                        <div class="absensi-info-header">
                            <i class="fas fa-calendar-day"></i>
                            <h6>Informasi Shift</h6>
                        </div>
                        <div class="absensi-info-grid">
                            <div class="absensi-info-item">
                                <span class="absensi-info-label">Jam Masuk Shift:</span>
                                <span class="absensi-info-value">{{
                                    formatDateTime(detailCheckinout?.Jam_Masuk)
                                }}</span>
                            </div>
                            <div class="absensi-info-item">
                                <span class="absensi-info-label">Jam Keluar Shift:</span>
                                <span class="absensi-info-value">{{
                                    formatDateTime(detailCheckinout?.Jam_Keluar)
                                }}</span>
                            </div>
                            <div class="absensi-info-item">
                                <span class="absensi-info-label">Check In Ideal:</span>
                                <span class="absensi-ideal-time">{{ formatDateTime(detailCheckinout?.CheckIn) }}</span>
                            </div>
                            <div class="absensi-info-item">
                                <span class="absensi-info-label">Check Out Ideal:</span>
                                <span class="absensi-ideal-time">{{ formatDateTime(detailCheckinout?.CheckOut) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Absensi -->
                    <div class="absensi-history-section">
                        <div class="absensi-section-header">
                            <i class="fas fa-history"></i>
                            <h6>Riwayat Absen</h6>
                        </div>
                        <div class="absensi-timeline">
                            <div
                                v-for="(item, idx) in getCheckinoutList()"
                                :key="idx"
                                class="absensi-timeline-item"
                                :class="'absensi-' + item.type.toLowerCase().replace(' ', '-')"
                            >
                                <div class="absensi-timeline-marker">
                                    <i v-if="item.type === 'Check In'" class="fas fa-sign-in-alt"></i>
                                    <i v-else-if="item.type === 'Check Out'" class="fas fa-sign-out-alt"></i>
                                    <i v-else class="fas fa-fingerprint"></i>
                                </div>
                                <div class="absensi-timeline-content">
                                    <span class="absensi-timeline-type">{{ item.type }}</span>
                                    <span class="absensi-timeline-time">{{ item.time }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="absensi-modal-footer">
                    <button @click="showModalDetailAbsen = false" type="button" class="absensi-primary-btn">
                        <i class="fas fa-check"></i>
                        Mengerti
                    </button>
                </div>
            </div>
        </div>
        <div v-if="ladingDetailAbsensi" class="absensi-modal-container">
            <div class="absensi-modal-card">
                <!-- Header Skeleton -->
                <div class="absensi-modal-head skeleton-container">
                    <div class="absensi-header-content">
                        <h5 class="absensi-modal-title skeleton-text" style="width: 200px; height: 24px"></h5>
                        <div class="absensi-shift-badge skeleton-text" style="width: 120px; height: 20px"></div>
                    </div>
                    <button
                        type="button"
                        class="absensi-close-btn skeleton-circle"
                        style="width: 24px; height: 24px"
                    ></button>
                </div>

                <!-- Body Skeleton -->
                <div class="absensi-modal-body">
                    <!-- Info Shift Skeleton -->
                    <div class="absensi-info-card skeleton-container">
                        <div class="absensi-info-header">
                            <i class="skeleton-circle" style="width: 20px; height: 20px"></i>
                            <h6 class="skeleton-text" style="width: 150px; height: 20px; margin-left: 8px"></h6>
                        </div>
                        <div class="absensi-info-grid">
                            <div class="absensi-info-item">
                                <span
                                    class="absensi-info-label skeleton-text"
                                    style="width: 140px; height: 16px"
                                ></span>
                                <span class="absensi-info-value skeleton-text" style="width: 80px; height: 16px"></span>
                            </div>
                            <div class="absensi-info-item">
                                <span
                                    class="absensi-info-label skeleton-text"
                                    style="width: 140px; height: 16px"
                                ></span>
                                <span class="absensi-info-value skeleton-text" style="width: 80px; height: 16px"></span>
                            </div>
                            <div class="absensi-info-item">
                                <span
                                    class="absensi-info-label skeleton-text"
                                    style="width: 140px; height: 16px"
                                ></span>
                                <span class="absensi-ideal-time skeleton-text" style="width: 80px; height: 16px"></span>
                            </div>
                            <div class="absensi-info-item">
                                <span
                                    class="absensi-info-label skeleton-text"
                                    style="width: 140px; height: 16px"
                                ></span>
                                <span class="absensi-ideal-time skeleton-text" style="width: 80px; height: 16px"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Absensi History Skeleton -->
                    <div class="absensi-history-section skeleton-container">
                        <div class="absensi-section-header">
                            <i class="skeleton-circle" style="width: 20px; height: 20px"></i>
                            <h6 class="skeleton-text" style="width: 150px; height: 20px; margin-left: 8px"></h6>
                        </div>
                        <div class="absensi-timeline">
                            <div class="absensi-timeline-item">
                                <div
                                    class="absensi-timeline-marker skeleton-circle"
                                    style="width: 24px; height: 24px"
                                ></div>
                                <div class="absensi-timeline-content">
                                    <span
                                        class="absensi-timeline-type skeleton-text"
                                        style="width: 80px; height: 16px"
                                    ></span>
                                    <span
                                        class="absensi-timeline-time skeleton-text"
                                        style="width: 60px; height: 14px; margin-top: 4px"
                                    ></span>
                                </div>
                            </div>
                            <div class="absensi-timeline-item">
                                <div
                                    class="absensi-timeline-marker skeleton-circle"
                                    style="width: 24px; height: 24px"
                                ></div>
                                <div class="absensi-timeline-content">
                                    <span
                                        class="absensi-timeline-type skeleton-text"
                                        style="width: 80px; height: 16px"
                                    ></span>
                                    <span
                                        class="absensi-timeline-time skeleton-text"
                                        style="width: 60px; height: 14px; margin-top: 4px"
                                    ></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Skeleton -->
                <div class="absensi-modal-footer">
                    <button class="absensi-primary-btn skeleton-button" style="width: 120px; height: 38px"></button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import VueApexCharts from 'vue3-apexcharts';
import { marked } from 'marked'; // Untuk konversi Markdown ke HTML
import {
    ElDialog,
    ElCalendar,
    ElMessage,
    ElTag,
    ElTooltip,
    ElSteps,
    ElStep,
    ElNotification,
    ElSlider,
    ElDatePicker,
} from 'element-plus';
import 'element-plus/dist/index.css';
import DOMPurify from 'dompurify'; // Untuk sanitasi HTML (SANGAT PENTING!)
import { capitalize } from 'vue';

export default {
    components: {
        apexchart: VueApexCharts,
        ElDialog,
        ElCalendar,
        ElMessage,
        ElSteps,
        ElStep,
        ElNotification,
        ElTooltip,
        ElSlider,
        ElTag,
        ElDatePicker,
    },
    props: {
        jumlahAntrianPengajuan: String,
        LeaderTeam: Boolean,
        accessPage: Array,
        allDynamicMenus: Array,
        namaKaryawan: String,
        shiftHariIni: String,
        Jam_Masuk: String,
        Jam_Keluar: String,
        shiftBesok: String,
        totalTerlambat: String,
        totalLembur: String,
        sisaCuti: String,
        judulBerita: String,
        isiBerita: String,
        nama_mengapprove: Array,
        nama_Approver: Array,
        nama_tergabung: Array,
        nama_anggota: Array,
        cutoffPeriod: {
            type: Object,
            default: null,
        },
    },
    watch: {
        // Ini penting jika prop bisa berubah setelah komponen di-mount
    },
    name: 'HRISDashboard',
    data() {
        return {
            windowWidth: window.innerWidth,
            loadingRingkasan: false,
            RingkasanTim: [],
            serachQueryRingkasanTim: '',
            currentPageRingkasanTim: 1,
            itemPerPageRingkasanTim: 5,
            paginationRingkasanTim: {
                current_page: 1,
                last_page: 1,
                per_page: 5,
                total: 0,
            },
            ringkasanSearchDebounce: null,

            loadingRiwayatAbsen: false,
            currentCutoffPeriod: this.cutoffPeriod,
            calendarWeekStartDate: null,
            monthNames: [
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
            ],
            monthChart: null,
            yearLembur: String(new Date().getFullYear()),
            chartOptionsLembur: {
                chart: {
                    type: 'bar',
                    toolbar: { show: true },
                },
                responsive: [
                    {
                        breakpoint: 768, // ukuran tablet/HP
                        options: {
                            chart: {
                                height: 100,
                                width: 100, // lebih kecil di HP
                            },
                            xaxis: {
                                labels: {
                                    show: true,
                                    rotate: -45, // biar label muat
                                },
                            },
                        },
                    },
                ],
                xaxis: {
                    categories: [],
                },
            },
            chartSeriesLembur: [
                {
                    name: 'Lembur',
                    data: [], // isi dari API / data kamu
                },
            ],

            detailData: [],
            detailCheckinout: [],
            showModalDetailAbsen: false,
            allDataRiwayatAbsen: [],
            peginationRiwayatAbsen: {},
            selectedDateAbsensi: null,
            searchKeywordAbsensi: '',
            currentPageAbsensi: 1,
            loadingRiwayatAbsen: false,

            itemPerPageCard: 5,

            serachQueryTergabung: '',
            currentPageTergabung: 1,

            serachQueryAnggota: '',
            currentPageAnggota: 1,

            convertedHtml: '',
            showModalBuletin: false,

            loadingShift: false,
            loadingChart: false,
            loadingTop: false,
            loadingCard: false,
            loadingLembur: false,
            loadingIzin: false,
            loadingQuick: false,
            loadingCalendar: false,

            paginationLembur: [],
            paginationIzin: [],
            allDataIzin: [],
            allDataLembur: [],
            chartStateLoad: null,
            dataShift: [],
            dataDateAbsen: [],
            weekSchedule: [
                {
                    dayName: 'Sun',
                    date: 'Jul 27',
                    shiftType: 'Non Shift',
                    startTime: '08:00',
                    endTime: '16:30',
                    shiftCode: 'HARI INI',
                    isToday: true,
                },
                {
                    dayName: 'Mon',
                    date: 'Jul 28',
                    shiftType: 'OGK2',
                    startTime: '08:00',
                    endTime: '17:00',
                    shiftCode: 'OGK2',
                    isToday: false,
                },
                {
                    dayName: 'Tue',
                    date: 'Jul 29',
                    shiftType: 'Regular',
                    startTime: '08:00',
                    endTime: '17:00',
                    isToday: false,
                },
                {
                    dayName: 'Wed',
                    date: 'Jul 30',
                    shiftType: 'Regular',
                    startTime: '08:00',
                    endTime: '17:00',
                    isToday: false,
                },
                {
                    dayName: 'Thu',
                    date: 'Jul 31',
                    shiftType: 'Regular',
                    startTime: '08:00',
                    endTime: '17:00',
                    isToday: false,
                },
                {
                    dayName: 'Fri',
                    date: 'Aug 1',
                    shiftType: 'Regular',
                    startTime: '08:00',
                    endTime: '17:00',
                    isToday: false,
                },
                {
                    dayName: 'Sat',
                    date: 'Aug 2',
                    shiftType: 'Day Off',
                    isToday: false,
                },
            ],
            selectedDay: {},

            series: [0, 0, 0, 0, 0, 0, 0],

            // Konfigurasi untuk tampilan chart
            chartOptions: {
                chart: { type: 'donut' },
                labels: ['Kehadiran', 'Izin Terlambat', 'Izin Pulang Cepat', 'Izin', 'Sakit', 'Cuti', 'Tidak Masuk'],
                colors: ['#00BFFF', '#FFD700', '#FF0000', '#32CD32', '#FF69B4', '#8A2BE2', '#64748B'],
                plotOptions: {
                    pie: {
                        donut: {
                            size: '65%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: 'Kehadiran',
                                    formatter: () => `${this.chartSummary?.kehadiran_percent ?? 0}%`, // langsung dari backend
                                    style: {
                                        fontSize: '16px',
                                        fontWeight: 600,
                                    },
                                },
                            },
                        },
                    },
                },
                legend: { show: false },
                dataLabels: { enabled: false },
            },
            // TANGGAL
            currentDate: new Date(),
            DayName: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'],
            //AKHIR TANGGAL
            userName: 'Ridho',
            currentDate: '',
            currentTime: '',
            currentMonthAndDate: '',
            stats: {
                overtime: 8.5,
                leaveBalance: 12,
                sickLeave: 5,
                pendingApproval: 3,
            },
            attendanceSummary: {
                present: 18,
                late: 2,
                earlyLeave: 1,
                absent: 0,
                workingDays: 21,
            },
            // Quick actions dibangun sepenuhnya dari allDynamicMenus (database).

            // State untuk modal
            showFolderModal: false,
            currentFolder: null,
            upcomingHoliday: {
                id: 1,
                name: 'Independence Day',
                date: 'August 17, 2023',
                daysLeft: 12,
            },
            nextHolidays: [
                { id: 2, name: 'Christmas Day', date: 'Dec 25, 2023' },
                { id: 3, name: 'New Year', date: 'Jan 1, 2024' },
            ],
            recentLeaveRequests: [
                {
                    id: 1,
                    type: 'annual',
                    typeLabel: 'Pulang Cepat',
                    startDate: 'Jul 10',
                    endDate: 'Jul 12',
                    status: 'Approved',
                },
                {
                    id: 2,
                    type: 'sick',
                    typeLabel: 'Sakit',
                    startDate: 'Jun 28',
                    endDate: 'Jun 29',
                    status: 'Approved',
                },
                {
                    id: 3,
                    type: 'permission',
                    typeLabel: 'Izin',
                    startDate: 'Jun 15',
                    endDate: 'Jun 15',
                    status: 'Pending',
                },
                {
                    id: 3,
                    type: 'permission',
                    typeLabel: 'Izin',
                    startDate: 'Jun 15',
                    endDate: 'Jun 15',
                    status: 'Pending',
                },
                {
                    id: 3,
                    type: 'permission',
                    typeLabel: 'Izin',
                    startDate: 'Jun 15',
                    endDate: 'Jun 15',
                    status: 'Pending',
                },
            ],
            currentShift: {
                id: 1,
                name: 'Regular Shift',
                startTime: '08:00',
                endTime: '17:00',
            },
            upcomingShifts: [
                {
                    id: 2,
                    day: 'Tomorrow',
                    name: 'Regular Shift',
                    startTime: '08:00',
                    endTime: '17:00',
                },
                {
                    id: 3,
                    day: 'Friday',
                    name: 'Regular Shift',
                    startTime: '08:00',
                    endTime: '17:00',
                },
                {
                    id: 4,
                    day: 'Saturday',
                    name: 'Day Off',
                    startTime: '-',
                    endTime: '-',
                },
            ],
            shiftProgress: 45,
            shiftTimeLeft: '4 hours 30 minutes',
        };
    },
    created() {
        this.selectedDay = this.weekSchedule.find((day) => day.isToday) || this.weekSchedule[0];
    },
    computed: {
        filteredMenuFolders() {
            const normalizeIcon = (icon) => {
                const value = String(icon || '').trim();
                if (!value) return 'bi bi-circle';
                if (value.startsWith('bi bi-')) return value;
                if (value.startsWith('bi-')) return `bi ${value}`;
                return value;
            };

            const neutralClass = 'btn-neutral';

            const normalizeHeaderKey = (value) =>
                String(value || '')
                    .trim()
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/gi, '-')
                    .replace(/^-+|-+$/g, '');

            const groups = {};
            (this.allDynamicMenus || []).forEach((menu) => {
                const groupKey = normalizeHeaderKey(menu?.Header_Code);

                if (!groupKey) return;

                if (!groups[groupKey]) groups[groupKey] = [];
                groups[groupKey].push(menu);
            });

            // console.log('Groups:', groups);
            const generatedFolders = Object.entries(groups)
                .map(([groupKey, menus]) => {
                    const firstMenu = menus[0] || {};
                    const folderClass = neutralClass;

                    const items = menus
                        .map((m, index) => ({
                            id: m?.Id_Menu ? `menu-${m.Id_Menu}` : `${groupKey}-${index}`,
                            label: m?.Nama_Menu,
                            icon: normalizeIcon(m?.Icon_Menu),
                            class: neutralClass,
                            akses: m?.Jenis_page,
                            url: String(m?.Url_Menu || '').trim(),
                            sortMenu: m?.Sort_Menu,
                            idMenu: m?.Id_Menu,
                        }))
                        .sort((a, b) => {
                            const aHasSort = a.sortMenu !== null && a.sortMenu !== undefined && a.sortMenu !== '';
                            const bHasSort = b.sortMenu !== null && b.sortMenu !== undefined && b.sortMenu !== '';

                            if (aHasSort && bHasSort) {
                                const sortDiff = Number(a.sortMenu || 0) - Number(b.sortMenu || 0);
                                if (sortDiff !== 0) return sortDiff;
                            }

                            if (aHasSort !== bHasSort) {
                                return aHasSort ? -1 : 1;
                            }

                            return Number(a.idMenu || 0) - Number(b.idMenu || 0);
                        })
                        .filter((item) => {
                            if (!item.url || !item.label) return false;
                            return true;
                        });

                    return {
                        id: groupKey,
                        label: firstMenu?.Header_Label || groupKey,
                        icon: normalizeIcon(firstMenu?.Header_Icon),
                        class: folderClass,
                        sort: Number(firstMenu?.Sort_Header || 999),
                        isSingle: false,
                        items,
                    };
                })
                .filter((folder) => folder.items.length > 0)
                .sort((a, b) => (a.sort || 999) - (b.sort || 999));

            generatedFolders.push({
                id: 'profile',
                label: 'Profile',
                icon: 'bi bi-person-circle',
                class: neutralClass,
                akses: '',
                url: '/user-profile',
                isSingle: true,
            });

            generatedFolders.push({
                id: 'logout',
                label: 'Logout',
                icon: 'bi bi-box-arrow-right',
                class: neutralClass,
                akses: '',
                url: '/logout',
                isSingle: true,
            });
            return generatedFolders;
        },
        filteredAnggota() {
            let filtered = this.nama_anggota;

            // console.log(this.nama_anggota);

            if (this.serachQueryAnggota) {
                this.currentPageAnggota = 1;
                filtered = filtered.filter((emp) => {
                    emp.Nama.toLowerCase().includes(this.serachQueryAnggota.toLowerCase());
                });
            }

            return filtered;
        },
        paginatedAnggota() {
            const start = (this.currentPageAnggota - 1) * this.itemPerPageCard;
            const end = start + this.itemPerPageCard;
            return this.filteredAnggota.slice(start, end);
        },
        totalPagesAnggota() {
            return Math.ceil(this.filteredAnggota.length / this.itemPerPageCard);
        },
        filteredTergabung() {
            let filtered = this.nama_tergabung;

            // console.log(this.nama_anggota);

            if (this.serachQueryTergabung) {
                this.currentPageTergabung = 1;
                filtered = filtered.filter((emp) => {
                    emp.Nama.toLowerCase().includes(this.serachQueryTergabung.toLowerCase());
                });
            }

            return filtered;
        },
        paginatedTergabung() {
            const start = (this.currentPageTergabung - 1) * this.itemPerPageCard;
            const end = start + this.itemPerPageCard;
            return this.filteredTergabung.slice(start, end);
        },
        totalPagesTergabung() {
            return Math.ceil(this.filteredTergabung.length / this.itemPerPageCard);
        },
        filteredRingkasanTim() {
            return this.RingkasanTim;
        },

        paginatedRingkasanTim() {
            return this.filteredRingkasanTim;
        },

        totalPagesRingkasanTim() {
            return Math.max(1, this.paginationRingkasanTim.last_page || 1);
        },

        attendancePercentage() {
            const total =
                this.attendanceSummary.present +
                this.attendanceSummary.late +
                this.attendanceSummary.earlyLeave +
                this.attendanceSummary.absent;
            return total > 0 ? Math.round((this.attendanceSummary.present / total) * 100) : 0;
        },
        attendancePeriodLabel() {
            return this.getJustMonthAndYear(this.visibleDateAbsen[0]?.Tanggal);
        },
        visibleDateAbsen() {
            const sortedData = [...this.dataDateAbsen].sort((a, b) =>
                this.normalizeDateKey(a?.Tanggal).localeCompare(this.normalizeDateKey(b?.Tanggal)),
            );

            if (!sortedData.length) {
                return [];
            }

            const startDate =
                this.calendarWeekStartDate ||
                this.getWeekStartDateKey(sortedData[0]?.Tanggal) ||
                this.normalizeDateKey(sortedData[0]?.Tanggal);
            const endDate = this.getWeekEndDateKey(startDate);

            return sortedData.filter((item) => {
                const itemDate = this.normalizeDateKey(item?.Tanggal);
                return itemDate >= startDate && itemDate <= endDate;
            });
        },
        currentMonth() {
            return this.currentDate.getMonth();
        },
        currentYear() {
            return this.currentDate.getFullYear();
        },
        monthName() {
            return this.currentDate.toLocaleDateString('id-ID', {
                month: 'long',
            });
        },
        currentFullMonth() {
            const currentMonthIndex = new Date().getMonth();
            return this.monthNames[currentMonthIndex];
        },
        calendarDays() {
            const year = this.currentYear;
            const month = this.currentMonth;

            const firstDay = new Date(year, month, 1).getDay();
            const lastDay = new Date(year, month + 1, 0).getDate();

            const days = [];

            // Tambahkan cell kosong untuk hari sebelum tanggal 1
            for (let i = 0; i < firstDay; i++) {
                days.push({ date: null });
            }

            for (let date = 1; date <= lastDay; date++) {
                const fullDate = `${year}-${(month + 1)
                    .toString()
                    .padStart(2, '0')}-${date.toString().padStart(2, '0')}`;
                const today = new Date();
                const isToday = today.getFullYear() === year && today.getMonth() === month && today.getDate() === date;
                const customClass = this.customDayClasses[fullDate] || '';

                days.push({ date, isToday, customClass });
            }
            return days;
        },
    },

    methods: {
        /** Rujukan tetap untuk addEventListener/removeEventListener. */
        onWindowResize() {
            this.windowWidth = window.innerWidth;
        },

        openFolder(folder) {
            if (!folder.items || folder.items.length === 0) {
                console.warn('Folder tidak memiliki items:', folder);
                return;
            }
            this.currentFolder = folder;
            this.showFolderModal = true;
        },

        closeFolderModal() {
            this.showFolderModal = false;
            this.currentFolder = null;
        },

        handleFolderItemClick(item) {
            if (item.url) {
                window.location.href = item.url;
            }
            this.closeFolderModal();
        },
        getCutoffPeriod(input = new Date()) {
            if (this.currentCutoffPeriod?.label) {
                return this.currentCutoffPeriod.label;
            }

            const d = input instanceof Date ? input : new Date(input);
            if (isNaN(d)) return null;

            const monthNames = [
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

            const day = d.getDate();
            const month = d.getMonth(); // 0..11
            const year = d.getFullYear();
            const startDay = this.currentCutoffPeriod?.start_day || 16;
            const endDay = this.currentCutoffPeriod?.end_day || 15;

            let startMonth, startYear;
            if (day >= startDay) {
                startMonth = month;
                startYear = year;
            } else {
                startMonth = (month - 1 + 12) % 12;
                startYear = month === 0 ? year - 1 : year;
            }

            const endMonth = (startMonth + 1) % 12;
            const endYear = startMonth === 11 ? startYear + 1 : startYear;

            const periodName = `${monthNames[startMonth]} Periode`;
            const range = `${startDay} ${monthNames[startMonth]} ${startYear} - ${endDay} ${monthNames[endMonth]} ${endYear}`;

            return `${periodName}, ${range}`;
        },
        isMobile() {
            return window.innerWidth < 768;
        },

        getInitials(name) {
            return name
                .split(' ')
                .map((word) => word[0])
                .join('')
                .substring(0, 2)
                .toUpperCase();
        },
        async getRingkasanTeam(page = 1) {
            if (!this.LeaderTeam) return;

            this.loadingRingkasan = true;

            try {
                const res = await axios.get('/uDash/getRingkasanTeam', {
                    params: {
                        page,
                        per_page: this.itemPerPageRingkasanTim,
                        search: this.serachQueryRingkasanTim || '',
                    },
                });

                if (res.status === 200) {
                    this.RingkasanTim = res.data.data || [];
                    this.paginationRingkasanTim = res.data.pagination || this.paginationRingkasanTim;
                    this.currentPageRingkasanTim = this.paginationRingkasanTim.current_page || page;
                }
            } catch (error) {
                console.log(error);
            } finally {
                this.loadingRingkasan = false;
            }
        },
        onSearchRingkasanTim() {
            if (this.ringkasanSearchDebounce) {
                clearTimeout(this.ringkasanSearchDebounce);
            }

            this.ringkasanSearchDebounce = setTimeout(() => {
                this.getRingkasanTeam(1);
            }, 350);
        },
        formatDurationMinutes(totalMinutes) {
            const safeMinutes = Math.max(0, Number(totalMinutes) || 0);
            const hours = Math.floor(safeMinutes / 60);
            const minutes = safeMinutes % 60;
            return `${hours} jam ${minutes} menit`;
        },
        async showLemburDetail(item) {
            const noTransaksi = item.No_Transaksi || '-';

            // Format tanggal untuk modal
            const fromDate = this.formatDate(item.Tanggal_Lembur_Dari);
            const toDate = this.formatDate(item.Tanggal_Lembur_Sampai);
            const dateRange = fromDate === toDate ? fromDate : `${fromDate} - ${toDate}`;

            // Set modal content
            document.getElementById('modalNoTransaksi').textContent = noTransaksi;
            document.getElementById('modalJenisLembur').textContent = item.Jenis_Lembur_Label || '-';
            document.getElementById('modalTanggal').textContent = dateRange;
            document.getElementById('modalDurasi').textContent = `${Number(item.durasi || 0).toFixed(0)} jam`;
            document.getElementById('modalStatus').textContent = item.Status;
            document.getElementById('modalAlasan').textContent = item.Alasan;
            document.getElementById('modalTanggalKerja').textContent = item.Tanggal_Kerja
                ? this.formatDate(item.Tanggal_Kerja)
                : this.formatDate(item.Tanggal_Lembur_Dari);

            document.getElementById('modalAssignedRange').textContent =
                item.Assigned_Range_Label ||
                `${this.formatDateTime(item.Tanggal_Lembur_Dari)} - ${this.formatDateTime(item.Tanggal_Lembur_Sampai)}`;

            const actualStart = item.Aktual_Mulai ? this.formatDateTime(item.Aktual_Mulai) : '-';
            const actualEnd = item.Aktual_Selesai ? this.formatDateTime(item.Aktual_Selesai) : '-';
            document.getElementById('modalActualRange').textContent =
                item.Actual_Range_Label && item.Actual_Range_Label !== '-'
                    ? item.Actual_Range_Label
                    : actualStart !== '-' && actualEnd !== '-'
                      ? `${actualStart} - ${actualEnd}`
                      : 'Belum ada data aktual lengkap';

            const actualMinute = Number(item.Durasi_Aktual_Menit ?? 0);
            const estimateMinute = Number(item.Durasi_Estimasi_Menit ?? 0);
            const buildDurationText = (minute, prefix = '') => {
                const h = Math.floor(minute / 60);
                const m = minute % 60;
                return `${prefix}${h} jam ${m} menit`;
            };
            document.getElementById('modalDurationInfo').textContent =
                actualMinute > 0
                    ? buildDurationText(actualMinute, 'Aktual: ')
                    : buildDurationText(estimateMinute, 'Est.: ');

            const absensiBadge = document.getElementById('modalAbsensiBadge');
            const absensiLengkap = item.Absensi_Lengkap === 'Y';
            absensiBadge.textContent = absensiLengkap ? 'ABSENSI LENGKAP' : 'BELUM LENGKAP';
            absensiBadge.classList.remove('status-selesai', 'status-proses', 'status-active');
            absensiBadge.classList.add(absensiLengkap ? 'status-selesai' : 'status-proses');

            document.getElementById('modalAbsensiStatus').textContent = item.Absensi_Status || '-';
            document.getElementById('modalAbsensiRequirement').textContent = item.Absensi_Requirement || '';

            if (noTransaksi === '-') {
                const modal = new bootstrap.Modal(document.getElementById('lemburDetailModal'));
                modal.show();
                return;
            }

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('lemburDetailModal'));
            modal.show();
        },

        // formatDate(dateString) {
        //     const options = {
        //         day: "2-digit",
        //         month: "2-digit",
        //         year: "numeric",
        //     };
        //     return new Date(dateString).toLocaleDateString("id-ID", options);
        // },

        // Method untuk inisialisasi chart dengan ApexCharts
        initLemburChart() {
            this.chartOptionsLembur = {
                chart: {
                    type: 'bar',
                    height: 250,
                    toolbar: { show: false },
                },
                colors: ['#6366f1'],
                plotOptions: {
                    bar: { borderRadius: 4, horizontal: false },
                },
                dataLabels: { enabled: false },
                xaxis: {
                    categories: [], // kita isi dari API
                },
                yaxis: {
                    title: { text: 'Jam' },
                },
                tooltip: {
                    y: {
                        formatter: (val, opts) => {
                            // Ambil index bar yg dihover
                            const index = opts.dataPointIndex;
                            const range = this.chartRangesLembur?.[index] ?? '';
                            return `${range} | Jam Lembur: ${val}`;
                        },
                    },
                },
            };

            this.chartSeriesLembur = [
                {
                    name: 'Jam Lembur',
                    data: Array(12).fill(0),
                },
            ];

            this.chartRangesLembur = []; // simpan range cutoff
        },

        async getDataChartLembur(year = null) {
            try {
                const yearParam = year || this.yearLembur;
                const res = await axios.get('/uDash/getChartLembur', {
                    params: { year: yearParam },
                });

                if (res.status === 200) {
                    // Isi chart data
                    this.chartSeriesLembur = [
                        {
                            name: 'data',
                            data: res.data.data,
                        },
                    ];

                    // Isi label bulan & range cutoff
                    this.chartOptionsLembur = {
                        ...this.chartOptionsLembur,
                        xaxis: { categories: res.data.labels },
                    };

                    this.chartRangesLembur = res.data.ranges;
                }
            } catch (error) {
                console.log(error);
            } finally {
                this.loadingLembur = false;
            }
        },

        getCheckinoutList() {
            if (!this.detailData || !this.detailCheckinout) return [];

            return this.detailData.map((c, idx) => {
                let type = `Absen ${idx + 1}`;
                if (c.CheckTime === this.detailCheckinout.CheckIn) {
                    type = 'Check In';
                } else if (c.CheckTime === this.detailCheckinout.CheckOut) {
                    type = 'Check Out';
                }
                return {
                    time: this.formatDateTime(c.CheckTime),
                    type,
                };
            });
        },
        formatDateTime(datetime) {
            if (!datetime) return '-';
            const d = new Date(datetime);
            const day = d.toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });

            const hour = String(d.getHours()).padStart(2, '0');
            const minute = String(d.getMinutes()).padStart(2, '0');

            return `${day}, ${hour}:${minute}`;
        },

        handleDateChange() {
            // Reset ke halaman pertama saat tanggal berubah
            this.peginationRiwayatAbsen.current_page = 1;
            this.getRiwayatAbsen('/uDash/getRiwayatAbsen');
        },

        showDetailAbsen(item) {
            this.loadingDetailAbsensi = true;
            this.showModalDetailAbsen = true;

            const datePickDetail = item.Tanggal;

            axios
                .get('/uDash/getAbsenDetail', {
                    params: { tanggal: datePickDetail },
                })
                .then((res) => {
                    // console.log("Response data:", res.data);

                    if (res.status === 200) {
                        this.detailData = res.data.data || [];
                        this.detailCheckinout = res.data.checkInOut?.[0] || null;
                    }
                })
                .catch((error) => {
                    console.error('Error showing detail:', error);
                    this.detailData = [];
                    this.detailCheckinout = null;
                })
                .finally(() => {
                    this.loadingDetailAbsensi = false;
                    // console.log(
                    //     "loadingDetailAbsensi sudah false:",
                    //     this.loadingDetailAbsensi
                    // );
                });
        },
        parseDateSafe(input) {
            if (!input) return null;

            if (input instanceof Date) {
                return Number.isNaN(input.getTime()) ? null : input;
            }

            if (typeof input === 'number') {
                const fromNumber = new Date(input);
                return Number.isNaN(fromNumber.getTime()) ? null : fromNumber;
            }

            if (typeof input !== 'string') {
                return null;
            }

            const raw = input.trim();
            if (!raw) return null;

            // Safari iOS often fails parsing "YYYY-MM-DD HH:mm:ss" (space separator).
            const normalized = raw.includes(' ') ? raw.replace(' ', 'T') : raw;
            const parsed = new Date(normalized);
            if (!Number.isNaN(parsed.getTime())) {
                return parsed;
            }

            const dateOnly = raw.split(' ')[0];
            const fallback = new Date(`${dateOnly}T00:00:00`);
            return Number.isNaN(fallback.getTime()) ? null : fallback;
        },
        formatDate(dateString) {
            const safeDate = this.parseDateSafe(dateString);
            if (!safeDate) return '-';
            const options = { day: 'numeric', month: 'long', year: 'numeric' };
            return safeDate.toLocaleDateString('id-ID', options);
        },
        getDayName(dateString) {
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const date = this.parseDateSafe(dateString);
            if (!date) return '-';
            return days[date.getDay()];
        },

        nextPageTergabung() {
            if (this.currentPageAnggota < this.totalPagesTergabung) this.currentPageAnggota++;
        },
        prevPageTergabung() {
            if (this.currentPageAnggota > 1) this.currentPageAnggota--;
        },
        nextPageRingkasanTim() {
            if (this.currentPageRingkasanTim < this.totalPagesRingkasanTim) {
                this.getRingkasanTeam(this.currentPageRingkasanTim + 1);
            }
        },
        prevPageRingkasanTim() {
            if (this.currentPageRingkasanTim > 1) {
                this.getRingkasanTeam(this.currentPageRingkasanTim - 1);
            }
        },
        nextPageAnggota() {
            if (this.currentPageAnggota < this.totalPagesAnggota) this.currentPageAnggota++;
        },
        prevPageAnggota() {
            if (this.currentPageAnggota > 1) this.currentPageAnggota--;
        },
        convertAndSanitize(markdown) {
            if (markdown) {
                const rawHtml = marked(markdown);
                this.convertedHtml = DOMPurify.sanitize(rawHtml);
            } else {
                this.convertedHtml = ''; // Kosongkan jika tidak ada markdown
            }
        },
        capitalize(value) {
            if (!value) return '';
            const words = value.split(' ');
            const capitalizedWords = words.map((word) => {
                if (!word) return '';
                return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
            });
            return capitalizedWords.join(' ');
        },
        async getChartData() {
            // alert(this.loadingChart);
            // this.loadingChart = true;
            this.loadingChart = true;
            // console.log("Tanggal dipilih:", this.chartStateLoad);
            try {
                const [bulan, tahun] = (
                    this.chartStateLoad ?? `${new Date().getMonth() + 1}-${new Date().getFullYear()}`
                ).split('-');

                await axios.get('/uDash/getChartData', { params: { bulan, tahun } }).then((res) => {
                    const d = res.data.data;

                    this.series = [d.kehadiran, d.terlambat, d.pulangCepat, d.izin, d.sakit, d.cuti, d.tidakMasuk];

                    this.chartOptions.labels = [
                        'Kehadiran',
                        'Izin Terlambat',
                        'Izin Pulang Cepat',
                        'Izin',
                        'Sakit',
                        'Cuti',
                        'Tidak Masuk',
                    ];

                    this.chartOptions.colors = [
                        '#00BFFF', // Kehadiran
                        '#FFD700', // Terlambat
                        '#FF0000', // Pulang Cepat
                        '#32CD32', // Izin
                        '#FF69B4', // Sakit
                        '#8A2BE2', // Cuti
                        '#64748B', // Tidak Masuk
                    ];

                    this.chartSummary = res.data.summary;
                });

                // if (response.status == 200) {
                //     this.series = response.data.data;
                //     this.chartSummary = response.data.summary; // simpan ke variabel
                // }
            } catch (error) {
                console.log(error);
            } finally {
                this.loadingChart = false;
            }
        },
        formatTanggaldanBulan(tanggalString) {
            if (!tanggalString) {
                return '';
            }

            const namaBulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

            const tanggalObjek = this.parseDateSafe(tanggalString);
            if (!tanggalObjek) return '-';
            const tanggal = tanggalObjek.getDate();
            const bulan = namaBulan[tanggalObjek.getMonth()];

            return `${tanggal} ${bulan}`;
        },
        async getDataShift() {
            try {
                this.loadingShift = true;
                const response = await axios.get('/uDash/getDataShift');
                if (response.status == 200) {
                    this.dataShift = response.data.data;
                }
            } catch (error) {
                console.log(error);
            } finally {
                this.loadingShift = false;
            }
        },
        async prevWeekShift() {
            const baseTanggal = this.dataShift?.[0]?.Tanggal;
            if (!baseTanggal) return;

            try {
                this.loadingShift = true;
                const response = await axios.get('/uDash/getDataShift', {
                    params: {
                        tanggal: baseTanggal,
                        jenis: 'prev',
                    },
                });
                if (response.status == 200) {
                    this.dataShift = response.data.data;
                }
            } catch (error) {
                console.log(error);
            } finally {
                this.loadingShift = false;
            }
        },
        async nextWeekShift() {
            const baseTanggal = this.dataShift?.[0]?.Tanggal;
            if (!baseTanggal) return;

            try {
                this.loadingShift = true;

                const response = await axios.get('/uDash/getDataShift', {
                    params: {
                        tanggal: baseTanggal,
                        jenis: 'next',
                    },
                });
                if (response.status == 200) {
                    this.dataShift = response.data.data;
                    // console.log(this.dataShift);
                }
            } catch (error) {
                console.log(error);
            } finally {
                this.loadingShift = false;
            }
        },
        async prevWeekAbsensi() {
            const currentStart =
                this.calendarWeekStartDate || this.getWeekStartDateKey(this.dataDateAbsen?.[0]?.Tanggal);
            if (!currentStart) return;

            const previousStart = this.addDaysToDateKey(currentStart, -7);

            try {
                this.loadingCalendar = true;
                const response = await axios.get('/uDash/getDateAbsen', {
                    params: {
                        tanggal: previousStart,
                    },
                });
                if (response.status == 200) {
                    this.setCalendarPeriodData(response.data, previousStart);
                }
            } catch (error) {
                console.log(error);
            } finally {
                this.loadingCalendar = false;
            }
        },
        async nextWeekAbsensi() {
            const currentStart =
                this.calendarWeekStartDate || this.getWeekStartDateKey(this.dataDateAbsen?.[0]?.Tanggal);
            if (!currentStart) return;

            const nextStart = this.addDaysToDateKey(currentStart, 7);

            try {
                this.loadingCalendar = true;

                const response = await axios.get('/uDash/getDateAbsen', {
                    params: {
                        tanggal: nextStart,
                    },
                });
                if (response.status == 200) {
                    this.setCalendarPeriodData(response.data, nextStart);
                }
            } catch (error) {
                console.log(error);
            } finally {
                this.loadingCalendar = false;
            }
        },
        getJustDate(dateString) {
            const date = this.parseDateSafe(dateString);
            if (!date) return '--';
            return String(date.getDate()).padStart(2, '0');
        },
        normalizeDateKey(value) {
            if (!value) return '';
            if (value instanceof Date) {
                const year = value.getFullYear();
                const month = String(value.getMonth() + 1).padStart(2, '0');
                const day = String(value.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }

            const raw = String(value).trim();
            if (!raw) return '';

            const dateOnly = raw.split(/[T\s]/)[0];
            return /^\d{4}-\d{2}-\d{2}$/.test(dateOnly) ? dateOnly : '';
        },
        addDaysToDateKey(dateKey, days) {
            if (!dateKey) return '';
            const date = new Date(`${dateKey}T00:00:00`);
            if (Number.isNaN(date.getTime())) return '';

            date.setDate(date.getDate() + days);
            return this.normalizeDateKey(date);
        },
        getWeekStartDateKey(dateInput = new Date()) {
            const date = this.parseDateSafe(dateInput);
            if (!date) return '';

            const weekStart = new Date(date.getFullYear(), date.getMonth(), date.getDate());
            weekStart.setDate(weekStart.getDate() - weekStart.getDay());
            return this.normalizeDateKey(weekStart);
        },
        getWeekEndDateKey(startDate) {
            return this.addDaysToDateKey(startDate, 6);
        },
        setCalendarPeriodData(payload, weekStartDate = null) {
            const sortedData = [...(payload?.data || [])].sort((a, b) =>
                this.normalizeDateKey(a?.Tanggal).localeCompare(this.normalizeDateKey(b?.Tanggal)),
            );

            this.dataDateAbsen = sortedData;
            this.calendarWeekStartDate =
                this.getWeekStartDateKey(weekStartDate) ||
                this.getWeekStartDateKey(new Date()) ||
                this.getWeekStartDateKey(sortedData[0]?.Tanggal);
        },
        getJustMonthAndYear(dateString) {
            const date = this.parseDateSafe(dateString);
            if (!date) return '-';
            const months = [
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
            const month = months[date.getMonth()];
            const year = date.getFullYear();
            return `${month} ${year}`;
        },
        getJustMonth(dateString) {
            const date = this.parseDateSafe(dateString);
            if (!date) return '-';
            const months = [
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
            const month = months[date.getMonth()];
            return `${month}`;
        },
        getJustYear(dateString) {
            const date = this.parseDateSafe(dateString);
            if (!date) return '-';
            const year = date.getFullYear();

            return `${year}`;
        },

        // Fungsi untuk mendapatkan nama hari singkat (contoh: "Sen")
        getJustDay(dateString) {
            const date = this.parseDateSafe(dateString);
            if (!date) return '-';
            const days = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
            return days[date.getDay()];
        },
        getClassAbsensi(data) {
            if (!data) return '';

            const realLate = Number(data.RealLate ?? data.Real_Late ?? 0);

            // 1. Prioritaskan kondisi cuti (cuti dianggap hari libur)
            if (data.Cuti_No_Transaksi || data.CutiHutang_No_Transaksi) {
                return 'dateCard holiday';
            }

            if (data.hariLibur) {
                return 'dateCard holiday';
            }

            // 2. Prioritaskan kondisi sakit atau izin
            if (
                (data.Sakit_No_Transaksi && data.Sakit_No_Transaksi.trim() !== '') ||
                (data.Izin_No_Transaksi && data.Izin_No_Transaksi.trim() !== '')
            ) {
                return 'dateCard holiday';
            }

            // 3. Periksa hari tanpa shift (termasuk hari Minggu)
            if (data.Nama_Shift === 'NO SHIFT') {
                return 'dateCard lost';
            }

            // 4. Periksa jika ada shift tapi belum check-in (absen)
            if (data.Nama_Shift !== 'NO SHIFT' && !data.CheckIn) {
                return 'dateCard weekday';
            }

            // 5. Tandai hari terlambat dari nilai final SP
            if (realLate > 0) {
                return 'dateCard late';
            }

            // 6. Default: Jika semua kondisi di atas tidak terpenuhi, anggap ini hari kerja normal
            return 'dateCard';
        },
        normalizeOffStatusLabel(rawStatus) {
            if (!rawStatus) return 'Hari Libur';

            const original = String(rawStatus).trim();
            if (original === '') return 'Hari Libur';

            const upper = original.toUpperCase();
            if (!upper.startsWith('OFF')) {
                return original;
            }

            const rawReason = original.includes('-') ? original.split('-').slice(1).join('-').trim() : '';

            if (rawReason === '') {
                return 'OFF';
            }

            const normalizedKey = rawReason
                .replace(/[^A-Za-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '')
                .toUpperCase();

            const dictionary = {
                DEFAULT_SHIFT: 'Default Shift',
                HARI_LIBUR: 'Hari Libur',
                CUTI_BERSAMA: 'Cuti Bersama',
                TUKAR_HARI: 'Tukar Hari',
                KONVERSI_LEMBUR: 'Konversi Lembur',
            };

            const readableReason =
                dictionary[normalizedKey] ||
                rawReason
                    .replace(/_/g, ' ')
                    .toLowerCase()
                    .replace(/\b\w/g, (c) => c.toUpperCase());

            return `OFF - ${readableReason}`;
        },
        escapeTooltipText(value) {
            if (value === null || value === undefined) return '';

            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },
        getTooltipContent(itemData) {
            // Check if itemData exists
            if (!itemData) {
                return `
        <div class="dashboard-tooltip no-data">
            <div class="tooltip-content">
                <span class="status-icon">ℹ️</span>
                <span>No shift data available</span>
            </div>
        </div>
         <style scoped>
        .dashboard-tooltip {
            // font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            max-width: 240px;
            padding: 12px;
            border-radius: 10px;
            background: var(--hcis-white);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            font-size: 13px;
            color: #2D3748;
            border: 1px solid var(--hcis-slate-200);
        }

        /* Status Card Styling */
        .status-card {
            text-align: center;
            padding: 12px 16px;
        }

        .status-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 6px;
        }

        .status-icon {
            font-size: 18px;
        }

        .status-title {
            font-weight: 600;
            font-size: 14px;
            color: #2D3748;
        }

        .status-dates {
            font-size: 12px;
            color: #718096;
            margin-bottom: 6px;
        }

        .status-footer {
            font-size: 11px;
            color: #718096;
            padding-top: 4px;
            border-top: 1px dashed var(--hcis-slate-200);
        }

        /* Status-specific colors */
        .cuti {
            background-color: #EBF8FF;
            border-color: #BEE3F8;
        }

        .sakit {
            background-color: #FFF5F5;
            border-color: #FED7D7;
        }

        .izin {
            background-color: #FFFAF0;
            border-color: #FEEBC8;
        }

        .cutihutang {
            background-color: #F0FFF4;
            border-color: #C6F6D5;
        }

        .libur {
            background-color: var(--hcis-slate-50);
            border-color: var(--hcis-slate-200);
        }

        .absent {
            background-color: #FFF5F5;
            border-color: #FED7D7;
        }

        /* Shift Card Styling */
        .shift-card {
            padding: 12px;
        }

        .shift-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .shift-icon {
            font-size: 16px;
        }

        .shift-name {
            font-weight: 600;
            flex-grow: 1;
            color: #4C51BF;
        }

        .shift-date {
            font-size: 12px;
            color: #718096;
        }

        .shift-schedule {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            margin-bottom: 8px;
            border-bottom: 1px dashed var(--hcis-slate-200);
        }

        .shift-duration {
            background: #EDF2F7;
            color: #4C51BF;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .attendance-record {
            margin: 8px 0;
        }

        .attendance-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
        }

        .attendance-time {
            font-weight: 500;
        }

        .attendance-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 8px;
        }

        /* Status colors */
        .late, .attendance-badge.late {
            color: #E53E3E;
            background-color: #FED7D7;
        }

        .early, .attendance-badge.early {
            color: #DD6B20;
            background-color: #FEEBC8;
        }

        .working {
            color: #D69E2E;
        }

        .attendance-badge.overtime {
            background-color: #EBF4FF;
            color: #4C51BF;
        }

        .lateleave, .attendance-badge.lateleave {
            background-color: #FEFCBF;
            color: #B7791F;
        }

        .attendance-badge.terlambat {
            background-color: #FED7D7;
            color: #E53E3E;
        }

        .overtime-info {
            font-size: 12px;
            padding: 8px;
            background: var(--hcis-slate-50);
            border-radius: 6px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .no-data {
            text-align: center;
            padding: 16px;
            background: var(--hcis-slate-50);
        }

        .working {
            background-color: #FFFAF0;
            border-color: #FEEBC8;
        }
    </style>
        `;
            }

            const mainShift = itemData;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const shiftDate = new Date(mainShift.Tanggal);
            shiftDate.setHours(0, 0, 0, 0);

            // Format time function
            const formatTime = (timeString) => {
                if (!timeString) return '-';
                const timePart = timeString.includes(' ') ? timeString.split(' ')[1] : timeString;
                return timePart ? timePart.substring(0, 5) : '-';
            };

            // Format date function
            const formatDate = (dateString) => {
                if (!dateString) return '';
                try {
                    const date = new Date(dateString);
                    return date.toLocaleDateString('id-ID', {
                        weekday: 'short',
                        day: '2-digit',
                        month: 'short',
                    });
                } catch (e) {
                    return dateString.split(' ')[0];
                }
            };

            // Check for leave/sick/absence cases first (priority)
            if (
                mainShift.Cuti_No_Transaksi ||
                mainShift.Sakit_No_Transaksi ||
                mainShift.Izin_No_Transaksi ||
                mainShift.CutiHutang_No_Transaksi
            ) {
                let type = 'Izin';
                let icon = '📝';
                let statusClass = 'izin';
                let startDate = mainShift.Izin_Mulai;
                let endDate = mainShift.Izin_Selesai;

                if (mainShift.Cuti_No_Transaksi) {
                    type = 'Cuti';
                    icon = '🏖️';
                    statusClass = 'cuti';
                    startDate = mainShift.Cuti_Mulai;
                    endDate = mainShift.Cuti_Selesai;
                } else if (mainShift.Sakit_No_Transaksi) {
                    type = 'Sakit';
                    icon = '🏥';
                    statusClass = 'sakit';
                    startDate = mainShift.Sakit_Mulai;
                    endDate = mainShift.Sakit_Selesai;
                } else if (mainShift.CutiHutang_No_Transaksi) {
                    type = 'Cuti Hutang';
                    icon = '⏳';
                    statusClass = 'cutihutang';
                    startDate = mainShift.CutiHutang_Mulai;
                    endDate = mainShift.CutiHutang_Selesai;
                }

                const formattedStart = formatDate(startDate);
                const formattedEnd = formatDate(endDate);

                return `
        <div class="dashboard-tooltip status-card ${statusClass}">
            <div class="status-header">
                <span class="status-icon">${icon}</span>
                <span class="status-title">${type}</span>
            </div>
            <div class="status-dates">${formattedStart}${
                formattedEnd !== formattedStart ? ` - ${formattedEnd}` : ''
            }</div>
            ${mainShift.Cuti_No_Transaksi ? '<div class="status-footer">Cuti</div>' : ''}
            ${mainShift.Sakit_No_Transaksi ? '<div class="status-footer">Sakit</div>' : ''}
            ${mainShift.CutiHutang_No_Transaksi ? '<div class="status-footer">Cuti hutang</div>' : ''}
            ${mainShift.Izin_No_Transaksi ? '<div class="status-footer">Izin pribadi</div>' : ''}
        </div>
         <style scoped>
        .dashboard-tooltip {
            // font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            max-width: 240px;
            padding: 12px;
            border-radius: 10px;
            background: var(--hcis-white);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            font-size: 13px;
            color: #2D3748;
            border: 1px solid var(--hcis-slate-200);
        }

        /* Status Card Styling */
        .status-card {
            text-align: center;
            padding: 12px 16px;
        }

        .status-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 6px;
        }

        .status-icon {
            font-size: 18px;
        }

        .status-title {
            font-weight: 600;
            font-size: 14px;
            color: #2D3748;
        }

        .status-dates {
            font-size: 12px;
            color: #718096;
            margin-bottom: 6px;
        }

        .status-footer {
            font-size: 11px;
            color: #718096;
            padding-top: 4px;
            border-top: 1px dashed var(--hcis-slate-200);
        }

        /* Status-specific colors */
        .cuti {
            background-color: #EBF8FF;
            border-color: #BEE3F8;
        }

        .sakit {
            background-color: #FFF5F5;
            border-color: #FED7D7;
        }

        .izin {
            background-color: #FFFAF0;
            border-color: #FEEBC8;
        }

        .cutihutang {
            background-color: #F0FFF4;
            border-color: #C6F6D5;
        }

        .libur {
            background-color: var(--hcis-slate-50);
            border-color: var(--hcis-slate-200);
        }

        .absent {
            background-color: #FFF5F5;
            border-color: #FED7D7;
        }

        /* Shift Card Styling */
        .shift-card {
            padding: 12px;
        }

        .shift-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .shift-icon {
            font-size: 16px;
        }

        .shift-name {
            font-weight: 600;
            flex-grow: 1;
            color: #4C51BF;
        }

        .shift-date {
            font-size: 12px;
            color: #718096;
        }

        .shift-schedule {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            margin-bottom: 8px;
            border-bottom: 1px dashed var(--hcis-slate-200);
        }

        .shift-duration {
            background: #EDF2F7;
            color: #4C51BF;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .attendance-record {
            margin: 8px 0;
        }

        .attendance-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
        }

        .attendance-time {
            font-weight: 500;
        }

        .attendance-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 8px;
        }

        /* Status colors */
        .late, .attendance-badge.late {
            color: #E53E3E;
            background-color: #FED7D7;
        }

        .early, .attendance-badge.early {
            color: #DD6B20;
            background-color: #FEEBC8;
        }

        .working {
            color: #D69E2E;
        }

        .attendance-badge.overtime {
            background-color: #EBF4FF;
            color: #4C51BF;
        }

        .lateleave, .attendance-badge.lateleave {
            background-color: #FEFCBF;
            color: #B7791F;
        }

        .attendance-badge.terlambat {
            background-color: #FED7D7;
            color: #E53E3E;
        }

        .overtime-info {
            font-size: 12px;
            padding: 8px;
            background: var(--hcis-slate-50);
            border-radius: 6px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .no-data {
            text-align: center;
            padding: 16px;
            background: var(--hcis-slate-50);
        }

        .working {
            background-color: #FFFAF0;
            border-color: #FEEBC8;
        }
    </style>`;
            }

            // Handle NO SHIFT case
            if (!mainShift.Nama_Shift || mainShift.Nama_Shift === 'NO SHIFT') {
                return `
        <div class="dashboard-tooltip status-card libur">
            <div class="status-header">
                <span class="status-icon">📅</span>
                <span class="status-title">Tidak ada Shift</span>
            </div>
            <div class="status-dates">${formatDate(mainShift.Tanggal)}</div>
        </div>
         <style scoped>
        .dashboard-tooltip {
            // font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            max-width: 240px;
            padding: 12px;
            border-radius: 10px;
            background: var(--hcis-white);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            font-size: 13px;
            color: #2D3748;
            border: 1px solid var(--hcis-slate-200);
        }

        /* Status Card Styling */
        .status-card {
            text-align: center;
            padding: 12px 16px;
        }

        .status-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 6px;
        }

        .status-icon {
            font-size: 18px;
        }

        .status-title {
            font-weight: 600;
            font-size: 14px;
            color: #2D3748;
        }

        .status-dates {
            font-size: 12px;
            color: #718096;
            margin-bottom: 6px;
        }

        .status-footer {
            font-size: 11px;
            color: #718096;
            padding-top: 4px;
            border-top: 1px dashed var(--hcis-slate-200);
        }

        /* Status-specific colors */
        .cuti {
            background-color: #EBF8FF;
            border-color: #BEE3F8;
        }

        .sakit {
            background-color: #FFF5F5;
            border-color: #FED7D7;
        }

        .izin {
            background-color: #FFFAF0;
            border-color: #FEEBC8;
        }

        .cutihutang {
            background-color: #F0FFF4;
            border-color: #C6F6D5;
        }

        .libur {
            background-color: var(--hcis-slate-50);
            border-color: var(--hcis-slate-200);
        }

        .absent {
            background-color: #FFF5F5;
            border-color: #FED7D7;
        }

        /* Shift Card Styling */
        .shift-card {
            padding: 12px;
        }

        .shift-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .shift-icon {
            font-size: 16px;
        }

        .shift-name {
            font-weight: 600;
            flex-grow: 1;
            color: #4C51BF;
        }

        .shift-date {
            font-size: 12px;
            color: #718096;
        }

        .shift-schedule {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            margin-bottom: 8px;
            border-bottom: 1px dashed var(--hcis-slate-200);
        }

        .shift-duration {
            background: #EDF2F7;
            color: #4C51BF;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .attendance-record {
            margin: 8px 0;
        }

        .attendance-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
        }

        .attendance-time {
            font-weight: 500;
        }

        .attendance-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 8px;
        }

        /* Status colors */
        .late, .attendance-badge.late {
            color: #E53E3E;
            background-color: #FED7D7;
        }

        .early, .attendance-badge.early {
            color: #DD6B20;
            background-color: #FEEBC8;
        }

        .working {
            color: #D69E2E;
        }

        .attendance-badge.overtime {
            background-color: #EBF4FF;
            color: #4C51BF;
        }

        .lateleave, .attendance-badge.lateleave {
            background-color: #FEFCBF;
            color: #B7791F;
        }

        .attendance-badge.terlambat {
            background-color: #FED7D7;
            color: #E53E3E;
        }

        .overtime-info {
            font-size: 12px;
            padding: 8px;
            background: var(--hcis-slate-50);
            border-radius: 6px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .no-data {
            text-align: center;
            padding: 16px;
            background: var(--hcis-slate-50);
        }

        .working {
            background-color: #FFFAF0;
            border-color: #FEEBC8;
        }
    </style>`;
            }

            // libur
            if (!mainShift.CheckIn && mainShift.hariLibur) {
                const offStatusLabel = this.normalizeOffStatusLabel(mainShift.hariLibur);
                const offStatusTitle = (mainShift.hariLiburTitle || '').trim();
                const escapedOffStatusTitle = this.escapeTooltipText(offStatusTitle);
                const shouldShowTitle =
                    offStatusTitle.length > 0 &&
                    offStatusTitle.toLowerCase() !==
                        String(offStatusLabel || '')
                            .trim()
                            .toLowerCase();
                return `
        <div class="dashboard-tooltip status-card libur">
            <div class="status-header">
                <span class="status-icon">🌴</span>
                <span class="status-title">${offStatusLabel || 'Hari Libur'} </span>
            </div>
            <div class="status-dates">${formatDate(mainShift.Tanggal)}</div>
            ${shouldShowTitle ? `<div class="status-footer">${escapedOffStatusTitle}</div>` : ''}
        </div>
         <style scoped>
        .dashboard-tooltip {
            // font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            max-width: 240px;
            padding: 12px;
            border-radius: 10px;
            background: var(--hcis-white);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            font-size: 13px;
            color: #2D3748;
            border: 1px solid var(--hcis-slate-200);
        }

        /* Status Card Styling */
        .status-card {
            text-align: center;
            padding: 12px 16px;
        }

        .status-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 6px;
        }

        .status-icon {
            font-size: 18px;
        }

        .status-title {
            font-weight: 600;
            font-size: 14px;
            color: #2D3748;
        }

        .status-dates {
            font-size: 12px;
            color: #718096;
            margin-bottom: 6px;
        }

        .status-footer {
            font-size: 11px;
            color: #718096;
            padding-top: 4px;
            border-top: 1px dashed var(--hcis-slate-200);
        }

        /* Status-specific colors */
        .cuti {
            background-color: #EBF8FF;
            border-color: #BEE3F8;
        }

        .sakit {
            background-color: #FFF5F5;
            border-color: #FED7D7;
        }

        .izin {
            background-color: #FFFAF0;
            border-color: #FEEBC8;
        }

        .cutihutang {
            background-color: #F0FFF4;
            border-color: #C6F6D5;
        }

        .libur {
            background-color: var(--hcis-slate-50);
            border-color: var(--hcis-slate-200);
        }

        .absent {
            background-color: #FFF5F5;
            border-color: #FED7D7;
        }

        /* Shift Card Styling */
        .shift-card {
            padding: 12px;
        }

        .shift-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .shift-icon {
            font-size: 16px;
        }

        .shift-name {
            font-weight: 600;
            flex-grow: 1;
            color: #4C51BF;
        }

        .shift-date {
            font-size: 12px;
            color: #718096;
        }

        .shift-schedule {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            margin-bottom: 8px;
            border-bottom: 1px dashed var(--hcis-slate-200);
        }

        .shift-duration {
            background: #EDF2F7;
            color: #4C51BF;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .attendance-record {
            margin: 8px 0;
        }

        .attendance-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
        }

        .attendance-time {
            font-weight: 500;
        }

        .attendance-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 8px;
        }

        /* Status colors */
        .late, .attendance-badge.late {
            color: #E53E3E;
            background-color: #FED7D7;
        }

        .early, .attendance-badge.early {
            color: #DD6B20;
            background-color: #FEEBC8;
        }

        .working {
            color: #D69E2E;
        }

        .attendance-badge.overtime {
            background-color: #EBF4FF;
            color: #4C51BF;
        }

        .lateleave, .attendance-badge.lateleave {
            background-color: #FEFCBF;
            color: #B7791F;
        }

        .attendance-badge.terlambat {
            background-color: #FED7D7;
            color: #E53E3E;
        }

        .overtime-info {
            font-size: 12px;
            padding: 8px;
            background: var(--hcis-slate-50);
            border-radius: 6px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .no-data {
            text-align: center;
            padding: 16px;
            background: var(--hcis-slate-50);
        }

        .working {
            background-color: #FFFAF0;
            border-color: #FEEBC8;
        }
    </style>`;
            }

            // Check if future date
            const isFutureDate = shiftDate > today;

            // Check if user didn't check in (null CheckIn) and it's today or past date
            if (mainShift.CheckIn === null && mainShift.CheckOut === null && !isFutureDate) {
                return `
        <div class="dashboard-tooltip status-card absent">
            <div class="status-header">
                <span class="status-icon">❌</span>
                <span class="status-title">Tidak Masuk</span>
            </div>
            <div class="shift-schedule">
                <span>Shift ${mainShift.Nama_Shift}  </span>
                <span>${mainShift.Jam_Masuk} - ${mainShift.Jam_Keluar}</span>
            </div>
            <div class="status-dates">${formatDate(mainShift.Tanggal)}</div>
        </div>
         <style scoped>
        .dashboard-tooltip {
            // font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            max-width: 240px;
            padding: 12px;
            border-radius: 10px;
            background: var(--hcis-white);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            font-size: 13px;
            color: #2D3748;
            border: 1px solid var(--hcis-slate-200);
        }

        /* Status Card Styling */
        .status-card {
            text-align: center;
            padding: 12px 16px;
        }

        .status-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 6px;
        }

        .status-icon {
            font-size: 18px;
        }

        .status-title {
            font-weight: 600;
            font-size: 14px;
            color: #2D3748;
        }

        .status-dates {
            font-size: 12px;
            color: #718096;
            margin-bottom: 6px;
        }

        .status-footer {
            font-size: 11px;
            color: #718096;
            padding-top: 4px;
            border-top: 1px dashed var(--hcis-slate-200);
        }

        /* Status-specific colors */
        .cuti {
            background-color: #EBF8FF;
            border-color: #BEE3F8;
        }

        .sakit {
            background-color: #FFF5F5;
            border-color: #FED7D7;
        }

        .izin {
            background-color: #FFFAF0;
            border-color: #FEEBC8;
        }

        .cutihutang {
            background-color: #F0FFF4;
            border-color: #C6F6D5;
        }

        .libur {
            background-color: var(--hcis-slate-50);
            border-color: var(--hcis-slate-200);
        }

        .absent {
            background-color: #FFF5F5;
            border-color: #FED7D7;
        }

        /* Shift Card Styling */
        .shift-card {
            padding: 12px;
        }

        .shift-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .shift-icon {
            font-size: 16px;
        }

        .shift-name {
            font-weight: 600;
            flex-grow: 1;
            color: #4C51BF;
        }

        .shift-date {
            font-size: 12px;
            color: #718096;
        }

        .shift-schedule {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            margin-bottom: 8px;
            border-bottom: 1px dashed var(--hcis-slate-200);
        }

        .shift-duration {
            background: #EDF2F7;
            color: #4C51BF;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .attendance-record {
            margin: 8px 0;
        }

        .attendance-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
        }

        .attendance-time {
            font-weight: 500;
        }

        .attendance-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 8px;
        }

        /* Status colors */
        .late, .attendance-badge.late {
            color: #E53E3E;
            background-color: #FED7D7;
        }

        .early, .attendance-badge.early {
            color: #DD6B20;
            background-color: #FEEBC8;
        }

        .working {
            color: #D69E2E;
        }

        .attendance-badge.overtime {
            background-color: #EBF4FF;
            color: #4C51BF;
        }

        .lateleave, .attendance-badge.lateleave {
            background-color: #FEFCBF;
            color: #B7791F;
        }

        .attendance-badge.terlambat {
            background-color: #FED7D7;
            color: #E53E3E;
        }

        .overtime-info {
            font-size: 12px;
            padding: 8px;
            background: var(--hcis-slate-50);
            border-radius: 6px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .no-data {
            text-align: center;
            padding: 16px;
            background: var(--hcis-slate-50);
        }

        .working {
            background-color: #FFFAF0;
            border-color: #FEEBC8;
        }
    </style>`;
            }

            // Regular shift case
            const realLate = Number(mainShift.RealLate ?? mainShift.Real_Late ?? 0);
            const realEarly = Number(mainShift.RealEarly ?? mainShift.Real_Early ?? 0);
            const isLate = mainShift.CheckIn && realLate > 0 && !mainShift.hariLibur;

            const isEarlyOut = mainShift.CheckOut && realEarly > 0 && !mainShift.hariLibur;

            const isLateLeave = mainShift.Pulang_No_Transaksi;
            const hasOvertime = mainShift.Lembur_No_Transaksi;
            const hasLongshift = mainShift.longShift;
            const isWorking = mainShift.CheckIn && !mainShift.CheckOut && shiftDate.getTime() === today.getTime();
            const hasValidWorkSchedule =
                !!mainShift.Jam_Masuk &&
                !!mainShift.Jam_Keluar &&
                String(mainShift.Jam_Masuk).toLowerCase() !== 'null' &&
                String(mainShift.Jam_Keluar).toLowerCase() !== 'null';

            return `
    <div class="dashboard-tooltip shift-card ${isWorking ? 'working' : ''}">
        <div class="shift-header">
            <span class="shift-icon">${isWorking ? '🔄' : '🕒'}</span>
            <span class="shift-name">${mainShift.Nama_Shift}</span>
            <span class="shift-date">${formatDate(mainShift.Tanggal)}</span>
        </div>

          ${
              mainShift.CheckIn
                  ? `
        ${
            hasValidWorkSchedule
                ? `<div class="shift-schedule">
            <span>${mainShift.Jam_Masuk} - ${mainShift.Jam_Keluar}</span>
            <span class="shift-duration">${this.calculateWorkHours(
                mainShift.Jam_Masuk,
                mainShift.Jam_Keluar,
            )} jam</span>
        </div>`
                : ''
        }

        <div class="attendance-record">
            <div class="attendance-row ${isLate ? 'late' : ''}">
                <span>Masuk:</span>
                <span class="attendance-time">${mainShift.CheckIn ? formatTime(mainShift.CheckIn) : 'Tidak ada'}</span>
                ${isLate ? '<span class="attendance-badge late">Telat</span>' : ''}
                ${
                    mainShift.Terlambat_No_Transaksi
                        ? '<span class="attendance-badge terlambat">Izin Terlambat</span>'
                        : ''
                }
            </div>`
                  : `
            <div class="attendance-row working">
                <span>Masuk:</span>
                <span class="attendance-time">${isFutureDate ? 'Belum Dilakukan' : 'Belum Check In'}</span>
            </div>
            `
          }


            ${
                mainShift.CheckOut
                    ? `
            <div class="attendance-row ${isEarlyOut ? 'early' : ''} ${isLateLeave ? 'lateleave' : ''}">
                <span>Pulang:</span>
                <span class="attendance-time">${formatTime(mainShift.CheckOut)}</span>
                ${isEarlyOut ? '<span class="attendance-badge early">Awal</span>' : ''}
                ${isLateLeave ? '<span class="attendance-badge lateleave">Izin Awal</span>' : ''}
                ${hasOvertime ? '<span class="attendance-badge overtime">Lembur</span>' : ''}
                ${hasLongshift ? '<span class="attendance-badge overtime">Longshift</span>' : ''}
            </div>
            `
                    : `
            <div class="attendance-row working">
                <span>Pulang:</span>
                <span class="attendance-time">${isFutureDate ? 'Belum Dilakukan' : 'Belum Check Out'}</span>
            </div>
            `
            }
        </div>

        ${
            hasOvertime
                ? `
        <div class="overtime-info">
            <span>⏰ Lembur: ${formatTime(mainShift.Lembur_Mulai)} - ${formatTime(mainShift.Lembur_Selesai)}</span>
        </div>
        `
                : ''
        }
        ${
            hasLongshift
                ? `
        <div class="overtime-info">
            <span>⏰ Lembur Longshift: ${formatTime(mainShift.longShift)} Jam</span>
        </div>
        `
                : ''
        }
    </div>

    <style scoped>
        .dashboard-tooltip {
            // font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            max-width: 240px;
            padding: 12px;
            border-radius: 10px;
            background: var(--hcis-white);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            font-size: 13px;
            color: #2D3748;
            border: 1px solid var(--hcis-slate-200);
        }

        /* Status Card Styling */
        .status-card {
            text-align: center;
            padding: 12px 16px;
        }

        .status-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 6px;
        }

        .status-icon {
            font-size: 18px;
        }

        .status-title {
            font-weight: 600;
            font-size: 14px;
            color: #2D3748;
        }

        .status-dates {
            font-size: 12px;
            color: #718096;
            margin-bottom: 6px;
        }

        .status-footer {
            font-size: 11px;
            color: #718096;
            padding-top: 4px;
            border-top: 1px dashed var(--hcis-slate-200);
        }

        /* Status-specific colors */
        .cuti {
            background-color: #EBF8FF;
            border-color: #BEE3F8;
        }

        .sakit {
            background-color: #FFF5F5;
            border-color: #FED7D7;
        }

        .izin {
            background-color: #FFFAF0;
            border-color: #FEEBC8;
        }

        .cutihutang {
            background-color: #F0FFF4;
            border-color: #C6F6D5;
        }

        .libur {
            background-color: var(--hcis-slate-50);
            border-color: var(--hcis-slate-200);
        }

        .absent {
            background-color: #FFF5F5;
            border-color: #FED7D7;
        }

        /* Shift Card Styling */
        .shift-card {
            padding: 12px;
        }

        .shift-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .shift-icon {
            font-size: 16px;
        }

        .shift-name {
            font-weight: 600;
            flex-grow: 1;
            color: #4C51BF;
        }

        .shift-date {
            font-size: 12px;
            color: #718096;
        }

        .shift-schedule {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            margin-bottom: 8px;
            border-bottom: 1px dashed var(--hcis-slate-200);
        }

        .shift-duration {
            background: #EDF2F7;
            color: #4C51BF;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .attendance-record {
            margin: 8px 0;
        }

        .attendance-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
        }

        .attendance-time {
            font-weight: 500;
        }

        .attendance-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 8px;
        }

        /* Status colors */
        .late, .attendance-badge.late {
            color: #E53E3E;
            background-color: #FED7D7;
            border-radius:12px;
            padding: 3px 5px;
        }

        .early, .attendance-badge.early {
            color: #DD6B20;
            background-color: #FEEBC8;
        }

        .working {
            color: #D69E2E;
        }

        .attendance-badge.overtime {
            background-color: #EBF4FF;
            color: #4C51BF;
        }

        .lateleave, .attendance-badge.lateleave {
            background-color: #FEFCBF;
            color: #B7791F;
            border-radius:12px;
            padding: 3px 3px
        }

        .attendance-badge.terlambat {
            background-color: #FED7D7;
            color: #E53E3E;
        }

        .overtime-info {
            font-size: 12px;
            padding: 8px;
            background: var(--hcis-slate-50);
            border-radius: 6px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .no-data {
            text-align: center;
            padding: 16px;
            background: var(--hcis-slate-50);
        }

        .working {
            background-color: #FFFAF0;
            border-color: #FEEBC8;
        }
    </style>
    `;
        },
        calculateWorkHours(jamMasuk, jamKeluar) {
            if (!jamMasuk || !jamKeluar) return 0;

            const [masukHour, masukMin] = jamMasuk.split(':').map(Number);
            const [keluarHour, keluarMin] = jamKeluar.split(':').map(Number);

            const masukMinutes = masukHour * 60 + masukMin;
            let keluarMinutes = keluarHour * 60 + keluarMin;

            if (keluarMinutes < masukMinutes) {
                keluarMinutes += 24 * 60;
            }

            const durasiMinutes = keluarMinutes - masukMinutes;
            return Math.round((durasiMinutes / 60) * 10) / 10; // 1 decimal
        },
        async getDateAbsen() {
            try {
                this.loadingCalendar = true;
                const currentWeekStart = this.getWeekStartDateKey(new Date());

                const response = await axios.get('/uDash/getDateAbsen', {
                    params: {
                        tanggal: currentWeekStart,
                    },
                });
                if (response.status == 200) {
                    this.setCalendarPeriodData(response.data, currentWeekStart);
                }
            } catch (error) {
                console.log(error);
            } finally {
                this.loadingCalendar = false;
            }
        },
        selectDay(day) {
            this.selectedDay = day;
        },
        shiftBadgeClass(day) {
            return {
                'bg-info': day.Nama_Shift === 'NO SHIFT',
                'bg-warning': day.Nama_Shift == 'OG-K2' || day.Nama_Shift == 'OG-K1',
                'bg-primary': day.Nama_Shift === 'EMI*',
                'bg-secondary': day.Nama_Shift === 'Day Off',
                'bg-primary': !(
                    day.Nama_Shift == 'NO SHIFT' ||
                    day.Nama_Shift == 'OG-K2' ||
                    day.Nama_Shift == 'OG-K1' ||
                    day.Nama_Shift == 'EMI*' ||
                    day.Nama_Shift == 'Day Off'
                ),
            };
        },

        nextMonth() {
            const nextDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 1);
            this.currentDate = nextDate;
        },
        previousMonth() {
            const prevDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() - 1, 1);
            this.currentDate = prevDate;
        },
        truncateText(text, length) {
            return text.length > length ? text.substring(0, length) + '...' : text;
        },
        formatTanggal_Lembur(start, end) {
            const [tanggalMulaiRaw] = start.split(' ');
            const [tanggalSelesaiRaw] = end.split(' ');

            const mulai = new Date(tanggalMulaiRaw);
            const selesai = new Date(tanggalSelesaiRaw);

            // Ambil komponen tanggal
            const dMulai = mulai.getDate();
            const mMulai = mulai.toLocaleString('id-ID', { month: 'long' });
            const yMulai = mulai.getFullYear();

            const dSelesai = selesai.getDate();
            const mSelesai = selesai.toLocaleString('id-ID', { month: 'long' });
            const ySelesai = selesai.getFullYear();

            // Kalau sama persis → tampilkan satu tanggal
            if (tanggalMulaiRaw === tanggalSelesaiRaw) {
                return `${dMulai} ${mMulai} ${yMulai}`;
            }

            // Kalau bulan & tahun sama → "28 - 29 Juni 2025"
            if (mMulai === mSelesai && yMulai === ySelesai) {
                return `${dMulai} - ${dSelesai} ${mMulai} ${yMulai}`;
            }

            // Kalau beda bulan/tahun → "28 Juni 2025 - 2 Juli 2025"
            return `${dMulai} ${mMulai} ${yMulai} - ${dSelesai} ${mSelesai} ${ySelesai}`;
        },
        parseIzin(tipe) {
            if (tipe == 'izinFull') {
                return 'Izin';
            } else if (tipe == 'sakit') {
                return 'Sakit';
            } else if (tipe == 'pulangCepat') {
                return 'Pulang Cepat';
            } else if (tipe == 'terlambat') {
                return 'Terlambat';
            } else if (tipe == 'cuti') {
                return 'Cuti';
            } else if (tipe == 'cutiHutang') {
                return 'Cuti';
            }
        },
        getTypeClass(type) {
            return (
                {
                    sakit: 'type-sakit',
                    terlambat: 'type-terlambat',
                    pulangCepat: 'type-pulang-cepat',
                    'Tidak Masuk': 'type-tidak-masuk',
                    cuti: 'type-cuti-tahunan',
                    cutiHutang: 'type-cuti-tahunan',
                    izinFull: 'type-izin-pribadi',
                }[type] || ''
            );
        },
        formatTanggal_Izin(start, end) {
            const tanggalMulai = new Date(start);

            const tanggalRawMulai = tanggalMulai?.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'long',
                year: 'numeric',
            });
            if (!end) {
                return `${tanggalRawMulai}`;
            }
            const tanggalEnd = new Date(end);

            const tanggalRawEnd = tanggalEnd?.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'long',
                year: 'numeric',
            });

            // Jika tanggalnya sama, kembalikan hanya tanggal dan rentang waktu
            if (tanggalRawMulai === tanggalRawEnd) {
                return `${tanggalRawMulai}`;
            } else {
                const date = new Date(tanggalRawMulai);
                const finalSelesai = date?.toLocaleDateString('id-ID', {
                    day: '2-digit',
                    month: 'long',
                    year: 'numeric',
                });
                // Jika tanggalnya berbeda, kembalikan rentang tanggal dan waktu
                return `${tanggalRawMulai.split('-')[0]} - ${tanggalRawEnd}`;
            }
        },

        updateDateTime() {
            const now = new Date();
            this.currentDate = now.toLocaleDateString('id-ID', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });
            this.currentMonthAndDate = now.toLocaleDateString('id-ID', {
                month: 'long',
                year: 'numeric',
            });
            this.currentTime = now.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true,
            });
        },
        changeAttendancePeriod(period) {
            // In a real app, this would fetch data for the selected period
            console.log('Changed attendance period to:', period);
        },
        handleQuickAction(actionId) {
            // Handle different quick actions
            console.log('Quick action:', actionId);
            // In a real app, this would trigger the appropriate action/modal
        },
        viewAllHolidays() {
            console.log('View all holidays');
            // In a real app, this would navigate to the full holiday calendar
        },
        viewAllRequests() {
            console.log('View all leave requests');
            // In a real app, this would navigate to the leave requests page
        },
        newLeaveRequest() {
            console.log('New leave request');
            // In a real app, this would open a leave request form
        },
        async getAllLembur(url) {
            try {
                this.loadingLembur = true;

                const response = await axios
                    .get(url)
                    .then((response) => {
                        this.allDataLembur = response.data.data;
                        this.paginationLembur = response.data;
                    })
                    .catch((error) => {
                        console.error(error);
                    });
            } catch (error) {
                console.log(error);
            } finally {
                this.loadingLembur = false;
            }
        },
        async getAllIzin(url) {
            try {
                this.loadingIzin = true;

                const response = await axios
                    .get(url)
                    .then((response) => {
                        this.allDataIzin = response.data.data;
                        this.paginationIzin = response.data;
                    })
                    .catch((error) => {
                        console.error(error);
                    });
            } catch (error) {
                console.log(error);
            } finally {
                this.loadingIzin = false;
            }
        },
        async getRiwayatAbsen(url) {
            try {
                this.loadingRiwayatAbsen = true;

                const response = await axios.get(url, {
                    params: {
                        // page: this.currentPageAbsensi,
                        search: this.searchKeywordAbsensi,
                        tanggal: this.selectedDateAbsensi,
                    },
                });

                this.allDataRiwayatAbsen = response.data.data.data;
                this.peginationRiwayatAbsen = response.data.data;
            } catch (error) {
                console.error(error);
            } finally {
                this.loadingRiwayatAbsen = false;
            }
        },
        changePage(page) {
            this.currentPageAbsensi = page;
            this.getRiwayatAbsen('/uDash/getRiwayatAbsen');
        },
    },
    mounted() {
        this.getRingkasanTeam();
        this.getDataChartLembur(this.yearLembur);
        this.initLemburChart();
        // console.log(this.isiBerita);
        this.convertAndSanitize(this.isiBerita);

        this.showModalBuletin = true;

        this.updateDateTime();
        this.getDateAbsen();
        this.getDataShift();
        this.getChartData();
        // this.getData();
        this.getAllLembur('/uDash/getAllLembur');
        this.getAllIzin('/uDash/getAllIzin');
        this.getRiwayatAbsen('/uDash/getRiwayatAbsen');

        // Update time every minute
        this.timeInterval = setInterval(this.updateDateTime, 60000);
        /*
         * Pasang dan lepas WAJIB memakai rujukan yang sama. Sebelumnya keduanya
         * arrow inline yang ditulis terpisah, jadi removeEventListener menerima
         * fungsi BARU dan tidak melepas apa pun -- pembersihannya tampak benar
         * saat dibaca dan tidak melakukan apa-apa saat dijalankan. Di SPA
         * Inertia halaman ini dikunjungi berkali-kali, jadi handler-nya
         * menumpuk sambil memegang instance komponen yang sudah mati.
         */
        window.addEventListener('resize', this.onWindowResize);
    },
    beforeUnmount() {
        clearInterval(this.timeInterval);
        window.removeEventListener('resize', this.onWindowResize);
    },
};
</script>
<style scoped>
*,
*::before,
*::after {
    --primary-color: var(--hcis-indigo-500);
    --secondary-color: var(--hcis-slate-500);
    --accent-color: #e74c3c;
    --light-color: #ecf0f1;
    --dark-color: var(--hcis-slate-800);
    --success-color: var(--hcis-emerald-500);
    --warning-color: var(--hcis-amber-500);
    --danger-color: var(--hcis-red-500);
    --border-radius: 20px;
    --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Style Folder Menus */

.btn-action[href] {
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.folder-badge {
    display: none;
}

button .folder-badge {
    display: flex;
}
.folder-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: var(--danger);
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 0.6rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.btn-action {
    position: relative;
    transition: all 0.3s ease;
}

.btn-action:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15) !important;
}

/* Folder Modal Styles */
.folder-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
    padding: 1rem;
}

.folder-modal-container {
    width: 100%;
    max-width: 500px;
    animation: folderModalAppear 0.3s ease-out;
}

@keyframes folderModalAppear {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.folder-modal-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    overflow: hidden;
}

/* Header */
.folder-modal-head {
    padding: 1.5rem;
    background: var(--gradient-primary);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.folder-header-content {
    flex: 1;
}

.folder-modal-title {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
}

.folder-subtitle {
    font-size: 0.9rem;
    opacity: 0.9;
}

.folder-close-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    flex-shrink: 0;
    margin-left: 1rem;
}

.folder-close-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

/* Body */
.folder-modal-body {
    padding: 2rem;
    max-height: 60vh;
    overflow-y: auto;
}

.folder-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 1rem;
}

.folder-item-btn {
    background: white;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
}

.folder-item-btn:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    border-color: var(--primary);
}

.folder-item-icon {
    font-size: 2rem;
    margin-bottom: 0.75rem;
    color: var(--primary);
}

.folder-item-label {
    font-weight: 600;
    font-size: 0.9rem;
    text-align: center;
    color: var(--text);
}

/* Color variants for folder items */
.folder-item-btn.btn-attendance .folder-item-icon {
    color: #27ae60;
}
.folder-item-btn.btn-overtime .folder-item-icon {
    color: var(--hcis-legacy-violet-400);
}
.folder-item-btn.btn-leave .folder-item-icon {
    color: #2b5876;
}
.folder-item-btn.btn-sick .folder-item-icon {
    color: #f54ea2;
}
.folder-item-btn.btn-permission .folder-item-icon {
    color: #ff7eb3;
}
.folder-item-btn.btn-shift .folder-item-icon {
    color: #9b59b6;
}
.folder-item-btn.btn-admin .folder-item-icon {
    color: #34495e;
}

.folder-item-btn.btn-attendance:hover {
    border-color: #27ae60;
}
.folder-item-btn.btn-overtime:hover {
    border-color: var(--hcis-legacy-violet-400);
}
.folder-item-btn.btn-leave:hover {
    border-color: #2b5876;
}
.folder-item-btn.btn-sick:hover {
    border-color: #f54ea2;
}
.folder-item-btn.btn-permission:hover {
    border-color: #ff7eb3;
}
.folder-item-btn.btn-shift:hover {
    border-color: #9b59b6;
}
.folder-item-btn.btn-admin:hover {
    border-color: #34495e;
}

/* Footer */
.folder-modal-footer {
    padding: 1.25rem 1.5rem;
    background: #f8f9fa;
    display: flex;
    justify-content: center;
}

.folder-close-action {
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.folder-close-action:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Responsive Design */
@media (max-width: 768px) {
    .folder-modal-container {
        margin: 0.5rem;
        max-width: none;
    }

    .folder-modal-body {
        padding: 1.5rem;
    }

    .folder-grid {
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 0.75rem;
    }

    .folder-item-btn {
        padding: 1.25rem 0.75rem;
    }

    .folder-item-icon {
        font-size: 1.75rem;
    }

    .folder-item-label {
        font-size: 0.8rem;
    }
}

@media (max-width: 576px) {
    .folder-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .folder-modal-head {
        padding: 1.25rem;
        flex-direction: column;
        align-items: flex-start;
    }

    .folder-close-btn {
        position: absolute;
        top: 1rem;
        right: 1rem;
    }

    .folder-header-content {
        margin-right: 3rem;
    }
}

/* Animation for folder items */
@keyframes folderItemAppear {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.folder-item-btn {
    animation: folderItemAppear 0.3s ease-out;
}

.folder-item-btn:nth-child(1) {
    animation-delay: 0.05s;
}
.folder-item-btn:nth-child(2) {
    animation-delay: 0.1s;
}
.folder-item-btn:nth-child(3) {
    animation-delay: 0.15s;
}
.folder-item-btn:nth-child(4) {
    animation-delay: 0.2s;
}
.folder-item-btn:nth-child(5) {
    animation-delay: 0.25s;
}

.search-container {
    position: relative;
    margin: 0px 0;
    width: 100%;
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #888;
    z-index: 2;
}

.search-input {
    width: 100%;
    padding: 12px 16px 12px 40px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
    /* box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1); */
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: #4a90e2;
    box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.2);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Styling untuk Ringkasan Tim */
.summary-card {
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border: none;
    margin-bottom: 20px;
}

.summary-card .card-header {
    background: #fff;
    padding: 16px 20px;
    border-bottom: 1px solid #eaeaea;
    font-weight: 600;
}

.summary-card .card-header h5 {
    font-size: 18px;
    color: #333;
    font-weight: 600;
}

.btn-pagination {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #ddd;
    background: #fff;
    color: #666;
    font-size: 12px;
}

.btn-pagination:not(:disabled):hover {
    background: #f5f5f5;
}

.pagination-text {
    font-size: 13px;
    color: #666;
}

.team-member {
    display: flex;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #f0f0f0;
    position: relative;
}

.team-member:last-child {
    border-bottom: none;
}

.member-rank {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--hcis-indigo-500);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-right: 12px;
    flex-shrink: 0;
    font-size: 12px;
}

.member-details {
    flex-grow: 1;
    min-width: 0;
}

.member-name {
    font-weight: 600;
    font-size: 14px;
    color: #333;
    margin-right: 10px;
}

.shift-badge {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    font-size: 11px;
    padding: 3px 8px;
    border-radius: 12px;
    font-weight: 500;
}

.member-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 10px;
}

.stat-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    background: #f8f9fa;
    padding: 5px 6px;
    border-radius: 6px;
    min-width: 45px;
    border: 1px solid #eaeaea;
}

.badge-value {
    font-weight: 600;
    font-size: 12px;
    color: #333;
    line-height: 1.2;
}

.badge-label {
    font-size: 9px;
    color: #666;
    margin-top: 2px;
    line-height: 1.1;
    text-align: center;
}

.status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    margin-left: 10px;
    white-space: nowrap;
}

.present {
    background-color: var(--hcis-green-100);
    color: #166534;
    border: 1px solid var(--hcis-green-200);
}

.absence {
    background-color: var(--hcis-amber-100);
    color: var(--hcis-amber-800);
    border: 1px solid var(--hcis-amber-200);
}

/* Responsiveness */
@media (max-width: 768px) {
    .team-member {
        flex-wrap: wrap;
        padding: 12px 15px;
    }

    .member-stats {
        order: 3;
        width: 100%;
        margin-top: 8px;
        justify-content: space-between;
    }

    .stat-badge {
        flex: 1;
        min-width: calc(33.333% - 6px);
        margin-bottom: 4px;
        padding: 4px 5px;
    }

    .status-badge {
        margin-left: auto;
        font-size: 10px;
        padding: 3px 8px;
    }

    .member-name {
        font-size: 13px;
    }

    .shift-badge {
        font-size: 10px;
        padding: 2px 6px;
    }
}

@media (max-width: 576px) {
    .member-stats {
        gap: 4px;
    }

    .stat-badge {
        min-width: calc(50% - 4px);
        padding: 4px 4px;
    }

    .badge-value {
        font-size: 11px;
    }

    .badge-label {
        font-size: 8px;
    }

    .status-badge {
        font-size: 10px;
        padding: 3px 8px;
    }

    .team-member {
        padding: 10px 12px;
    }

    .member-rank {
        width: 26px;
        height: 26px;
        font-size: 11px;
        margin-right: 10px;
    }
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
[data-underline] {
    text-decoration: underline;
}
.el-popper__arrow {
    display: none !important;
}

.absensi-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 1rem;
}

.absensi-modal-container {
    width: 100%;
    max-width: 500px;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    overflow: hidden;
    animation: absensiModalAppear 0.3s ease-out;
}

@keyframes absensiModalAppear {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Header Styles */
.absensi-modal-head {
    padding: 1.25rem 1.5rem;
    background: var(--gradient-primary);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.absensi-header-content {
    flex: 1;
}

.absensi-modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.absensi-shift-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.2);
    padding: 0.35rem 0.75rem;
    border-radius: 50px;
    font-size: 0.85rem;
}

.absensi-close-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}

.absensi-close-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

/* Body Styles */
.absensi-modal-body {
    padding: 1.5rem;
    max-height: 60vh;
    overflow-y: auto;
}

.absensi-info-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.absensi-info-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    color: #4a6fa5;
}

.absensi-info-header h6 {
    font-weight: 600;
    margin: 0;
}

.absensi-info-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.75rem;
}

.absensi-info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--hcis-legacy-bootstrap-gray);
}

.absensi-info-item:last-child {
    border-bottom: none;
}

.absensi-info-label {
    font-weight: 500;
    color: #495057;
}

.absensi-info-value {
    font-weight: 600;
    color: #212529;
}

.absensi-ideal-time {
    color: #28a745 !important;
}

/* Attendance Timeline */
.absensi-history-section {
    margin-top: 1.5rem;
}

.absensi-section-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    color: #4a6fa5;
}

.absensi-section-header h6 {
    font-weight: 600;
    margin: 0;
}

.absensi-timeline {
    position: relative;
    padding-left: 1.5rem;
}

.absensi-timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.absensi-timeline-item {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
    position: relative;
}

.absensi-timeline-marker {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    z-index: 1;
    flex-shrink: 0;
}

.absensi-check-in .absensi-timeline-marker {
    background: #28a745;
    color: white;
}

.absensi-check-out .absensi-timeline-marker {
    background: #dc3545;
    color: white;
}

.absensi-absen-1 .absensi-timeline-marker,
.absensi-absen-2 .absensi-timeline-marker,
.absensi-absen-3 .absensi-timeline-marker {
    background: #6c757d;
    color: white;
}

.absensi-timeline-content {
    background: white;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    flex: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.absensi-timeline-type {
    font-weight: 500;
}

.absensi-timeline-time {
    font-weight: 600;
    color: #495057;
}

/* Footer Styles */
.absensi-modal-footer {
    padding: 1.25rem 1.5rem;
    background: #f8f9fa;
    display: flex;
    justify-content: flex-end;
}

.absensi-primary-btn {
    background: var(--gradient-primary);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0.5rem 1.5rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.2s;
}

.absensi-primary-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Responsive Adjustments */
@media (max-width: 576px) {
    .absensi-modal-container {
        margin: 0.5rem;
    }

    .absensi-modal-body {
        padding: 1rem;
    }

    .absensi-info-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .absensi-timeline-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
}

.profile-section {
    background: white;
    border-radius: var(--border-radius);
    --box-shadow: var(--shadow);
    padding: 1.5rem;
    transition: all 0.3s ease;
}
.section-content.leader {
    height: 15.2rem;
    overflow: auto;
}
.section-content {
    height: 18.3rem;
    overflow: auto;
}

.section-header {
    border-bottom: 1px solid var(--hcis-slate-200);
    padding-bottom: 1rem;
    margin-bottom: 1.5rem;
}

.section-header h2 {
    color: var(--dark-color);
    font-weight: 600;
    margin: 0;
}

.request-item {
    background: white;
    border-radius: 12px;
    border: 0.5px solid var(--hcis-slate-200);
    padding: 1rem 1.25rem;
    margin-bottom: 0.75rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    border-left: 4px solid var(--primary-color);
    transition: all 0.2s ease;
    cursor: pointer;
}

.request-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    border-left: 4px solid var(--success-color);
}

.request-date {
    font-size: 0.9rem;
    color: var(--secondary-color);
}

.request-status {
    font-size: 0.85rem;
    font-weight: 500;
}

.status-badge {
    padding: 0.35rem 0.75rem;
    border-radius: 50px;
}

.bg-secondary {
    background: linear-gradient(135deg, var(--secondary-color), var(--hcis-slate-600)) !important;
}

.loading-spinner {
    display: inline-block;
    width: 1.5rem;
    height: 1.5rem;
    border: 3px solid rgba(99, 102, 241, 0.3);
    border-radius: 50%;
    border-top-color: var(--primary-color);
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.fade-in {
    animation: fadeIn 0.5s ease-in-out;
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

.empty-state {
    text-align: center;
    padding: 2rem;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: var(--hcis-slate-300);
}

.pagination-container {
    display: flex;
    justify-content: center;
    margin-top: 1.5rem;
}

.btn-pagination {
    border-radius: 50px;
    padding: 0.4rem 1rem;
    margin: 0 0.25rem;
    font-weight: 500;
    border: 1px solid var(--hcis-slate-200);
}

.btn-pagination:hover:not(:disabled) {
    background-color: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.page-info {
    display: flex;
    align-items: center;
    margin: 0 1rem;
    color: var(--secondary-color);
}

/* Responsive adjustments */
@media (max-width: 576px) {
    .section-header {
        flex-direction: column;
        align-items: flex-start !important;
    }

    .section-header h2 {
        margin-bottom: 1rem;
    }

    .filter-container {
        width: 100%;
    }

    .filter-container input {
        width: 100%;
    }

    .request-item {
        flex-direction: column;
        align-items: flex-start !important;
    }

    .request-status {
        margin-top: 0.5rem;
    }

    /* .pagination-container {
        flex-wrap: wrap;
    } */

    .page-info {
        width: 100%;
        justify-content: center;
        margin: 0.5rem 0;
    }
}
.el-tooltip__popper.is-dark {
    background: transparent !important;
    border: none !important;
}

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

.el-popper.is-dark,
.el-popper.is-dark > .el-popper__arrow:before {
    border: none !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
    border-radius: 8px !important;
    padding: 2px !important;
    font-size: 1rem;
    font-weight: 600;
    color: var(--primary) !important;
    background: white !important;
}
.el-popper.is-dark,
.el-popper.is-dark > .el-popper__arrow:before {
    background: var(--surface);
}
</style>
<style scoped>
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
}

#el-id-9874-77 {
    background: var(--surface) !important;
}

.tanggalBar {
    -webkit-user-select: none; /* For Chrome, Safari, Opera */
    -moz-user-select: none; /* For Firefox */
    -ms-user-select: none; /* For Internet Explorer/Edge */
    user-select: none;
}
.recharts-tooltip-wrapper {
    z-index: 9999 !important;
}

/* CSS untuk Card Riwayat Lembur */
.lembur-history-card {
    border-radius: 12px;
    box-shadow: var(--shadow);
    border: none;
    overflow: hidden;
}

/* CSS untuk Card Riwayat Lembur */
.lembur-history-card {
    border-radius: 12px;
    box-shadow: var(--shadow);
    border: none;
    overflow: hidden;
}

.lembur-history-card .card-header {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 1rem 1.25rem;
}

.lembur-history-card .card-body {
    padding: 0;
    max-height: 19.5rem;
    overflow-y: auto;
}

.lembur-item {
    display: flex;
    align-items: center;
    padding: 0.875rem 1.25rem;
    border-bottom: 1px solid var(--border);
    transition: background-color 0.2s ease;
    cursor: pointer;
}

.lembur-item:hover {
    background-color: var(--surface-soft);
}

.lembur-date {
    flex: 1;
    font-size: 0.9rem;
    color: var(--text);
    font-weight: 500;
}

.lembur-duration {
    margin: 0 1rem;
    padding: 0.25rem 0.75rem;
    background: var(--primary-light);
    color: var(--primary-dark);
    border-radius: 16px;
    font-size: 0.8rem;
    font-weight: 600;
}

.lembur-status {
    padding: 0.25rem 0.75rem;
    border-radius: 16px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-selesai {
    background-color: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-menunggu {
    background-color: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.status-proses,
.status-active {
    background-color: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.status-ditolak {
    background-color: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.lembur-pagination {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.lembur-pagination-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--primary);
    color: white;
    border: none;
    transition: all 0.2s ease;
}

.lembur-pagination-btn:hover:not(:disabled) {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.lembur-pagination-btn:disabled {
    background: var(--gray-light);
    color: var(--text-muted);
    cursor: not-allowed;
}

/* Modal Styles */
.detail-item {
    display: flex;
    margin-bottom: 1rem;
}

.detail-label {
    flex: 0 0 30%;
    font-weight: 600;
    color: var(--text-muted);
}

.detail-value {
    flex: 1;
    color: var(--text);
}

/* Skeleton Loading */
.lembur-skeleton {
    display: flex;
    align-items: center;
    padding: 0.875rem 1.25rem;
    border-bottom: 1px solid var(--border);
}

.lembur-skeleton-date {
    flex: 1;
    height: 16px;
    background: var(--surface-soft);
    border-radius: 4px;
}

.lembur-skeleton-duration {
    width: 60px;
    height: 24px;
    margin: 0 1rem;
    background: var(--surface-soft);
    border-radius: 16px;
}

.lembur-skeleton-status {
    width: 70px;
    height: 24px;
    background: var(--surface-soft);
    border-radius: 16px;
}

/* Empty State */
.lembur-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    color: var(--text-muted);
    text-align: center;
}

.lembur-empty i {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    color: var(--primary-light);
}

.lembur-history-card .card-header {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 1rem 1.25rem;
}

.lembur-history-card .card-body {
    padding: 0;
    max-height: 19.5rem;
    overflow-y: auto;
}

.lembur-item {
    display: flex;
    align-items: center;
    padding: 0.875rem 1.25rem;
    border-bottom: 1px solid var(--border);
    transition: background-color 0.2s ease;
    cursor: pointer;
}

.lembur-item:hover {
    background-color: var(--surface-soft);
}

.lembur-date {
    flex: 1;
    font-size: 0.9rem;
    color: var(--text);
    font-weight: 500;
}

.lembur-duration {
    margin: 0 1rem;
    padding: 0.25rem 0.75rem;
    background: var(--primary-light);
    color: var(--primary-dark);
    border-radius: 16px;
    font-size: 0.8rem;
    font-weight: 600;
}

.lembur-status {
    padding: 0.25rem 0.75rem;
    border-radius: 16px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-selesai {
    background-color: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-menunggu {
    background-color: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.status-ditolak {
    background-color: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.lembur-pagination {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.lembur-pagination-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--primary);
    color: white;
    border: none;
    transition: all 0.2s ease;
}

.lembur-pagination-btn:hover:not(:disabled) {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.lembur-pagination-btn:disabled {
    background: var(--gray-light);
    color: var(--text-muted);
    cursor: not-allowed;
}

/* Modal Styles */
.detail-item {
    display: flex;
    margin-bottom: 1rem;
}

.detail-label {
    flex: 0 0 30%;
    font-weight: 600;
    color: var(--text-muted);
}

.detail-value {
    flex: 1;
    color: var(--text);
}

/* Skeleton Loading */
.lembur-skeleton {
    display: flex;
    align-items: center;
    padding: 0.875rem 1.25rem;
    border-bottom: 1px solid var(--border);
}

.lembur-skeleton-date {
    flex: 1;
    height: 16px;
    background: var(--surface-soft);
    border-radius: 4px;
}

.lembur-skeleton-duration {
    width: 60px;
    height: 24px;
    margin: 0 1rem;
    background: var(--surface-soft);
    border-radius: 16px;
}

.lembur-skeleton-status {
    width: 70px;
    height: 24px;
    background: var(--surface-soft);
    border-radius: 16px;
}

/* Empty State */
.lembur-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    color: var(--text-muted);
    text-align: center;
}

.lembur-empty i {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    color: var(--primary-light);
}
/* buletin */
.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(5px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1050;
}

.glass-modal {
    background: rgba(255, 0, 106, 0.15);
    border-radius: 16px;
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    width: 100%;
    max-width: 500px;
    animation: fadeIn 0.3s ease-out;
}

.btn-approval-flow-shift {
    background-color: var(--primary);
    color: var(--white);
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.btn-approval-flow-shift:hover {
    background-color: var(--primary-dark);
}

.glass-modal.detail {
    /* From https://css.glass */
    background: rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(5px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    width: 100%;
    max-width: 500px;
    animation: fadeIn 0.3s ease-out;
}

.glass-modal-content {
    padding: 20px;
    color: #fff;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: black !important;
    margin-bottom: 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    padding-bottom: 10px;
}

.modal-title {
    margin: 0;
    font-size: 1.25rem;
}

.close {
    background: none;
    border: none;
    font-size: 1.5rem;
    padding: 0.1rem 0.7rem;
    border-radius: 12px;
    color: #fff;
    opacity: 0.8;
    cursor: pointer;
    transition: all 0.2s ease;
}

.close:hover {
    opacity: 1;
    /* padding: 0.2rem 1rem; */
    background: var(--danger);
}

.modal-body {
    margin-bottom: 20px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    padding-top: 15px;
}

.btn {
    margin-left: 10px;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Tambahan untuk dark/light mode */
@media (max-width: 768px) {
    .glass-modal {
        /* background: rgba(255, 255, 255, 0.8); */
        color: #333;
        max-width: 95vw;
        max-height: 80vh;
    }
    .modal-header,
    .modal-footer {
        border-color: rgba(0, 0, 0, 0.1);
    }
    .close {
        color: #333;
    }
}
/* akhir buletin */
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

.skeleton-container {
    /* background-color: #f0f0f0; */
    overflow: hidden;
    position: relative;
}

.skeleton-icon,
.skeleton-text,
.skeleton-dropdown,
.skeleton-chart,
.skeleton-legend-dot,
.skeleton-legend-text {
    background-color: var(--hcis-legacy-bootstrap-gray);
    position: relative;
    overflow: hidden;
}
.skeleton-text,
.skeleton-button,
.skeleton-circle {
    background-color: #e0e0e0;
    position: relative;
    overflow: hidden;
    border-radius: 4px;
}

.skeleton-text,
.skeleton-button,
.skeleton-circle {
    background-color: #e0e0e0;
    position: relative;
    overflow: hidden;
    border-radius: 4px;
}

/* Animation */
.skeleton-container::after,
.skeleton-text::after,
.skeleton-button::after,
.skeleton-circle::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
    animation: shimmer 1.5s infinite;
}

.timeline-days {
    display: flex;
    gap: 1px;
    overflow-x: auto;
    padding-bottom: 8px;
}

.timeline-day {
    flex: 1;
    min-width: 120px;
    padding: 12px;
    background-color: var(--hcis-white);
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.skeleton-day {
    background-color: var(--hcis-legacy-bootstrap-gray) !important;
}

.skeleton-icon,
.skeleton-text,
.skeleton-button,
.skeleton-badge {
    background-color: #e0e0e0;
    position: relative;
    overflow: hidden;
    border-radius: 4px;
}

/* Animation */
.skeleton-container::after,
.skeleton-icon::after,
.skeleton-text::after,
.skeleton-button::after,
.skeleton-badge::after,
.skeleton-day::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
    animation: shimmer 1.5s infinite;
}

.info-card {
    background-color: #f5f5f5;
    border: none !important;
}

/* Animation */
.skeleton-container::after,
.skeleton-text::after,
.skeleton-button::after,
.skeleton-circle::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
    animation: shimmer 1.5s infinite;
}

.info-card {
    background-color: #f5f5f5;
    border: none !important;
}

.skeleton-chart {
    background: conic-gradient(var(--hcis-legacy-bootstrap-gray) 0% 25%, #f0f2f5 25% 50%, var(--hcis-legacy-bootstrap-gray) 50% 75%, #f0f2f5 75% 100%);
}

.skeleton-text,
.skeleton-button,
.skeleton-date {
    background-color: #e0e0e0;
    position: relative;
    overflow: hidden;
}

/* Animation */
.skeleton-container::after,
.skeleton-text::after,
.skeleton-button::after,
.skeleton-date::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
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

.badge-lembur {
    padding: 0.2rem 0.4rem;
    border-radius: 20rem;
    color: white;
    background: var(--warning) !important;
}
.badge-lembur-selesai {
    padding: 0.2rem 0.4rem;
    border-radius: 20rem;
    color: white;
    background: var(--success) !important;
}

.card {
    border-radius: 12px;
    overflow: hidden;
}

.bg-gradient-primary {
    background: var(--gradient-primary);
    opacity: 0.95;
}

.timeline-days {
    display: flex;
    padding: 0 10px;
    overflow-x: auto;
    /* scrollbar-width: none; Firefox */
}

.timeline-days::-webkit-scrollbar {
    display: none; /* Chrome/Safari */
}

.timeline-day {
    flex: 1;
    min-width: 80px;
    padding: 12px 8px;
    margin: 0 2px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid var(--hcis-legacy-bootstrap-gray);
}

.timeline-day:hover {
    background-color: #f8f9fa;
    transform: translateY(-2px);
}

.timeline-day.active {
    background-color: #e7f1ff;
    border-color: #cfe2ff;
    box-shadow: 0 2px 8px rgba(0, 123, 255, 0.1);
}

.day-header {
    text-align: center;
    margin-bottom: 8px;
}

.day-name {
    font-weight: 600;
    font-size: 0.9rem;
}

.day-date {
    font-size: 0.75rem;
    color: #6c757d;
}

.day-shift {
    text-align: center;
}

.shift-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    background: var(--primary);
    color: white !important;
    margin-bottom: 5px;
}

.shift-hours {
    font-size: 0.75rem;
    color: #495057;
}

.day-off {
    background-color: #f8f9fa;
}

.day-off-text {
    font-size: 0.75rem;
    color: #6c757d;
    font-style: italic;
}

.selected-day-details {
    border-top: 1px dashed #dee2e6;
    border-bottom: 1px dashed #dee2e6;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 0.85rem;
}

.detail-label {
    color: #6c757d;
}

.detail-value {
    font-weight: 500;
}

.day-off-content {
    color: #6c757d;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .timeline-day {
        min-width: 70px;
        padding: 8px 4px;
    }

    .day-name {
        font-size: 0.8rem;
    }

    .shift-badge {
        font-size: 0.65rem;
    }
}

/* chart */
#chart-donut > div {
    display: flex;
    justify-content: center;
}
.legend-manual {
    margin-left: 20px;
    /* font-family: "Arial", sans-serif; */
    font-size: 14px;
}
.legend-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}
.legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 8px;
}
.legend-text {
    color: #333;
}

/* Styling untuk teks di tengah donut, ini mungkin perlu disesuaikan */
#chart-donut .apexcharts-text tspan:first-child {
    font-size: 36px; /* Ukuran font untuk 86% */
    font-weight: 700;
    fill: #000;
}
#chart-donut .apexcharts-text tspan:last-child {
    font-size: 12px; /* Ukuran font untuk 'Persentase kehadiran' */
    font-weight: 400;
    fill: #555;
}
.col-md-6 {
    padding: 0 8px;
}

/* akhirchart */
/* calendar */
.wrapper-calendar {
    height: 10.2rem;
}
.wrapper-calendar-2 {
    height: 19em;
}
.cardFloat {
    width: 100%;
    padding-bottom: 3rem !important;
    z-index: 9;
    border-radius: 12px;

    background: var(--gradient-primary);
    padding: 0.7rem 0.7rem;
    opacity: 0.95;
}
.cardIn {
    background: var(--surface);
    border-radius: 12px;
    z-index: 15;
    width: 100%;
    top: 3rem;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border);
}

.border-primary {
    border: 1px solid var(--border);
}

.dateCard {
    flex-grow: 1;
    padding: 0.5rem;
    margin-bottom: 4px;
    text-align: center;
    color: white;
    border-radius: 10px;
    background: var(--primary);
}

.dateCard.lost {
    background: var(--danger);
}
.dateCard.weekday {
    background: var(--secondary);
}
.dateCard.holiday {
    background-color: var(--warning);
}

.dateCard.late {
    background-color: #f97316;
}

.card-loading-state {
    position: relative;
}

.card-loading-label {
    position: absolute;
    top: 12px;
    right: 16px;
    font-size: 0.75rem;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 999px;
    padding: 2px 10px;
}

/* akhir calendar */

/* info bar */
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
    width: 28px;
    height: 28px;
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

/* button */

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

.card-body {
    overflow: auto;
    padding: 0.5rem 1rem;
}
/* Akhir button */
.card-wrap {
    background: var(--surface);
    border-radius: 20px;
    padding: 1rem 0.5rem;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border);
}
.content-container {
    background: var(--gradient-primary);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border);
    opacity: 0.95;
}
/* Base Styles */
.hris-dashboard {
    /* font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; */

    min-height: 100vh;
}

/* Header Styles */
.dashboard-header {
    background: var(--surface);
    padding: 15px 20px;
    border-radius: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    align-items: center;
    border: 1px solid var(--border);
    width: 100%;
    height: 100%;
}

.current-date {
    display: flex;
    flex-direction: column;
}

.date {
    font-size: 1rem;
    font-weight: 500;
}

.time {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2c3e50;
}

/* Stat Card Styles */
.quick-stats {
    margin: 0 -10px;
}

.stat-card {
    position: relative;
    padding: 20px;
    border-radius: 10px;
    color: white;
    height: 120px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
}

.stat-unit {
    font-size: 0.8rem;
    opacity: 0.8;
}

.stat-icon {
    position: absolute;
    right: 45px;
    bottom: 45px;
    font-size: 2.5rem;
    opacity: 0.2;
}

.overtime {
    background: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
}

.leave {
    background: linear-gradient(135deg, #2b5876 0%, #4e4376 100%);
}

.sick {
    background: linear-gradient(135deg, #f54ea2 0%, #ff7676 100%);
}

.permission {
    background: linear-gradient(135deg, #ff7eb3 0%, #ff758c 100%);
}

/* Card Styles */
.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);

    margin-bottom: 20px;
}

.card-header {
    background-color: white;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    padding: 15px 20px;
    border-radius: 12px 10px 0 0 !important;
}

.card-header.leader {
    padding: 15px 20px;
}

/* Attendance Summary Styles */
.attendance-stats {
    display: flex;
    justify-content: space-between;
    text-align: center;
    margin-bottom: 20px;
}

.attendance-item {
    flex: 1;
}

.attendance-value {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2c3e50;
}

.attendance-label {
    font-size: 0.8rem;
    color: #7f8c8d;
}

/* Quick Action Styles */
.btn-action {
    width: 100%;
    padding: 12px 5px;
    border-radius: 8px;
    font-size: 0.8rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    transition: all 0.3s ease;
    border: 1px solid #e0e0e0;
    background-color: white;
    min-height: 5.6rem;
    justify-content: center;
}

.btn-action i {
    font-size: 1.2rem;
    margin-bottom: 5px;
}

.btn-action:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.btn-neutral {
    color: #4b5563;
}

.btn-overtime {
    color: var(--hcis-legacy-violet-400);
}

.btn-leave {
    color: #2b5876;
}

.btn-sick {
    color: #f54ea2;
}

.btn-permission {
    color: #ff7eb3;
}

.btn-late {
    color: #f39c12;
}

.btn-early {
    color: #e74c3c;
}

.btn-shift {
    color: #9b59b6;
}

.btn-attendance {
    color: #27ae60;
}

.folder-item-btn.btn-neutral .folder-item-icon {
    color: #6b7280;
}

.folder-item-btn.btn-neutral:hover {
    border-color: #9ca3af;
}

/* Holiday Card Styles */
.upcoming-holiday {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    text-align: center;
}

.holiday-date {
    font-size: 0.9rem;
    color: #7f8c8d;
}

.holiday-name {
    font-size: 1.2rem;
    font-weight: 600;
    margin: 5px 0;
    color: #2c3e50;
}

.holiday-days {
    font-size: 0.9rem;
    color: #e74c3c;
    font-weight: 500;
}

.holiday-item {
    display: flex;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.holiday-item:last-child {
    border-bottom: none;
}

.holiday-item-date {
    width: 100px;
    font-size: 0.8rem;
    color: #7f8c8d;
}

.holiday-item-name {
    flex: 1;
    font-size: 0.9rem;
}

.type-sakit {
    background: var(--danger);
}
.type-terlambat {
    background: var(--warning);
}
.type-pulang-cepat {
    background: var(--info);
}
.type-tidak-masuk,
.type-cuti-tahunan,
.type-izin-pribadi {
    background: var(--primary);
}

/* Leave Request Styles */
.leave-request {
    display: flex;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
    align-items: center;
}

.leave-request:last-child {
    border-bottom: none;
}

.request-type i {
    font-size: 0.7rem;
    padding: 3px 8px;
    border-radius: 20px;
    color: white;
    margin-right: 10px;
    text-transform: uppercase;
    font-weight: 600;
}

.type-cuti {
    background-color: #3498db;
}

.type-sakit {
    background-color: #e74c3c;
}

.type-Iz {
    background-color: #f39c12;
}

.request-date {
    flex: 1;
    font-size: 0.9rem;
}

.request-status {
    font-size: 0.8rem;
    font-weight: 500;
}

.status-Selesai {
    color: #27ae60;
}

.status-Diproses {
    color: #f39c12;
}

.status-Ditolak {
    color: #e74c3c;
}

/* Shift Card Styles */
.current-shift {
    display: flex;
    margin-bottom: 20px;
    align-items: center;
}

.shift-info {
    flex: 1;
}

.shift-name {
    font-size: 1.1rem;
    font-weight: 600;
}

.shift-time {
    font-size: 0.9rem;
    color: #7f8c8d;
}

.shift-progress {
    flex: 2;
    padding-left: 20px;
}

.shift-time-left {
    font-size: 0.8rem;
    text-align: right;
    color: #7f8c8d;
    margin-top: 5px;
}

.upcoming-shifts {
    margin-top: 25px;
}

.upcoming-shifts h6 {
    font-size: 0.9rem;
    color: #7f8c8d;
    margin-bottom: 15px;
    text-transform: uppercase;
}

.upcoming-shift {
    display: flex;
    margin-bottom: 12px;
}

.shift-day {
    width: 80px;
    font-size: 0.9rem;
    font-weight: 500;
}

.shift-details {
    flex: 1;
}

.shift-details span {
    display: block;
}

.shift-name {
    font-size: 0.9rem;
    font-weight: 500;
}

.shift-time {
    font-size: 0.8rem;
    color: #7f8c8d;
}

/* Responsive Styles */
@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        text-align: center;
    }
    .btn-action {
        /* max-width: 90px; */
        padding: 12px 3px !important;
        min-height: 5.4rem;
    }
    .current-date {
        margin-top: 10px;
        align-items: center;
    }

    .attendance-stats {
        flex-wrap: wrap;
    }

    .attendance-item {
        flex: 50%;
        margin-bottom: 15px;
    }

    .current-shift {
        flex-direction: column;
        align-items: flex-start;
    }

    .shift-progress {
        width: 100%;
        padding-left: 0;
        margin-top: 15px;
    }

    .btn-action {
        padding: 8px 5px;
        font-size: 0.7rem;
    }

    .btn-action i {
        font-size: 1rem;
    }
}

@media (max-width: 576px) {
    .stat-card {
        height: 100px;
        padding: 15px;
    }

    .stat-value {
        font-size: 1.5rem;
    }

    .stat-icon {
        font-size: 2rem;
    }

    .quick-actions-card .col-6 {
        flex: 0 0 50%;
        max-width: 50%;
    }

    .lembur-history-card .card-header h5 {
        font-size: 1.05rem;
    }

    .lembur-history-card .lembur-item {
        grid-template-columns: 1fr auto;
        grid-template-areas:
            'tx status'
            'date status'
            'hours type';
        row-gap: 0.4rem;
        column-gap: 0.5rem;
        align-items: center;
        padding: 0.7rem 0.9rem;
    }

    .lembur-history-card .lembur-transaction {
        grid-area: tx;
        font-size: 0.78rem;
        font-weight: 700;
        line-height: 1.2;
        word-break: break-word;
    }

    .lembur-history-card .lembur-range {
        grid-area: date;
        font-size: 0.76rem;
        color: var(--text-muted);
        font-weight: 500;
        line-height: 1.2;
    }

    .lembur-history-card .lembur-hours {
        grid-area: hours;
        margin: 0;
        justify-self: start;
        padding: 0.2rem 0.5rem;
        font-size: 0.72rem;
    }

    .lembur-history-card .lembur-type {
        grid-area: type;
        margin: 0;
        justify-self: start;
        padding: 0.2rem 0.45rem;
        font-size: 0.68rem;
    }

    .lembur-history-card .lembur-status {
        grid-area: status;
        justify-self: end;
        font-size: 0.68rem;
        padding: 0.2rem 0.45rem;
        line-height: 1.1;
        white-space: nowrap;
    }
}

@media (min-width: 577px) {
    .lembur-history-card .lembur-item {
        display: grid;
        grid-template-columns: minmax(130px, 1fr) minmax(120px, 1fr) auto auto;
        align-items: center;
        column-gap: 0.55rem;
    }

    .lembur-history-card .lembur-transaction {
        font-size: 0.86rem;
        font-weight: 700;
        line-height: 1.2;
        word-break: break-word;
    }

    .lembur-history-card .lembur-range {
        font-size: 0.82rem;
        line-height: 1.2;
    }

    .lembur-history-card .lembur-hours {
        margin: 0;
        font-size: 0.74rem;
        padding: 0.22rem 0.5rem;
    }

    .lembur-history-card .lembur-status {
        font-size: 0.72rem;
        padding: 0.22rem 0.5rem;
        justify-self: end;
    }
}

/* Overtime history compact layout: no horizontal scroll */
.lembur-history-card .card-body {
    overflow-x: hidden !important;
}

.lembur-history-card .lembur-item {
    width: 100%;
    box-sizing: border-box;
}

.lembur-history-card .lembur-no {
    font-size: 0.88rem;
    font-weight: 700;
    line-height: 1.2;
}

.lembur-history-card .lembur-jenis-inline {
    font-size: 0.68rem;
    color: var(--text-muted);
    line-height: 1.2;
    margin-top: 0.15rem;
}

@media (max-width: 576px) {
    .lembur-history-card .lembur-item {
        grid-template-columns: 1fr auto;
        grid-template-areas:
            'tx status'
            'date status'
            'hours status';
        overflow: hidden;
    }

    .lembur-history-card .lembur-transaction {
        grid-area: tx;
        min-width: 0;
    }

    .lembur-history-card .lembur-range {
        grid-area: date;
        min-width: 0;
        white-space: normal;
    }

    .lembur-history-card .lembur-hours {
        grid-area: hours;
    }

    .lembur-history-card .lembur-status {
        grid-area: status;
    }
}
</style>
