<template>
    <div v-if="isActive" class="row d-flex align-items-center justify-content-center mb-3 fade-in">
        <div class="col-md-12">
            <div class="modern-header">
                <div class="header-background"></div>
                <div class="header-content">
                    <div class="row align-items-center">
                        <div class="col-sm-8">
                            <div class="header-info">
                                <div class="icon-container d-flex align-items-center justify-content-center">
                                    <i class="bi bi-person-badge d-flex justify-content-center align-items-center"></i>
                                </div>
                                <div class="text-content">
                                    <h2 class="title text-white text-start">
                                        {{ DataDayNow?.Nama ? `Halo, ${DataDayNow.Nama.split(' ')[0]}` : 'Halo' }}
                                    </h2>
                                    <p class="subtitle">
                                        {{ DataDayNow?.nama_divisi ? DataDayNow.nama_divisi.toLowerCase() : '' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <!-- Button & Dates -->
                        <div class="col-sm-4">
                            <div
                                class="header-actions d-flex gap-2 flex-nowrap flex-sm-column justify-content-center justify-content-md-start justify-content-md-center align-items-md-end"
                            >
                                <div class="date-range d-block d-md-none px-2 px-md-3 py-1 gap-2 gap-md-3">
                                    <div class="date-item">
                                        <i
                                            v-if="dayNight == 'Siang'"
                                            class="bi bi-cloud-sun-fill me-2 fs-4 fade-in"
                                        ></i>
                                        <i v-else class="bi bi-cloud-moon-fill me-2 fs-4 fade-in"></i>
                                        <span class="fs-md-6 text-header"
                                            >{{ formatDate(new Date(DataDayNow?.Tanggal)) }}
                                        </span>
                                    </div>
                                </div>
                                <button
                                    class="btn-modern btn-primary flex-nowrap w-full px-md-2 py-md-2"
                                    @click="openAttendanceModal"
                                >
                                    <i
                                        class="bi bi-plus-circle me-2 d-flex justify-content-center align-items-center"
                                    ></i>
                                    <span class="text-header">Tambah Absensi</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- OFF DAY INFO in shift box -->
                    <!-- ...existing code... -->
                </div>
            </div>
        </div>
    </div>
    <div v-else class="row d-flex align-items-center justify-content-center mb-3 fade-in">
        <div class="col-md-12">
            <div class="modern-header">
                <div class="header-background"></div>
                <div class="header-content">
                    <div class="row align-items-center">
                        <!-- Left Side Skeleton -->
                        <div class="col-sm-8">
                            <div class="header-info">
                                <div
                                    class="icon-container d-flex align-items-center justify-content-center skeleton"
                                    style="width: 50px; height: 50px; border-radius: 50%"
                                ></div>
                                <div class="text-content ms-3">
                                    <div
                                        class="skeleton skeleton-text"
                                        style="width: 150px; height: 25px; margin-bottom: 10px"
                                    ></div>
                                    <div class="skeleton skeleton-text" style="width: 100px; height: 20px"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Side Skeleton -->
                        <div class="col-sm-4">
                            <div
                                class="header-actions d-flex gap-2 flex-nowrap flex-sm-column justify-content-center justify-content-md-start justify-content-md-center align-items-md-end"
                            >
                                <!-- Date Skeleton (visible on mobile) -->
                                <div class="date-range d-block d-md-none px-2 px-md-3 py-1 gap-2 gap-md-3">
                                    <div class="date-item d-flex align-items-center">
                                        <div
                                            class="skeleton"
                                            style="width: 24px; height: 24px; margin-right: 8px"
                                        ></div>
                                        <div class="skeleton skeleton-text" style="width: 100px; height: 20px"></div>
                                    </div>
                                </div>

                                <!-- Button Skeleton -->
                                <div class="skeleton" style="width: 100%; height: 40px; border-radius: 5px"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DATE Container -->
    <div v-if="isActive" class="row d-flex justify-content-center fade-in mb-2 mb-md-0">
        <div class="col-12 p-md-2 row p-0">
            <div class="col-6 d-none d-md-block ps-0 pe-1">
                <div class="content-container p-3 flex-shrink-1 h-100 position-relative">
                    <i
                        v-if="dayNight == 'Siang'"
                        class="bi bi-cloud-sun position-absolute d-flex justify-content-center align-items-center"
                        style="font-size: 14rem; right: 8rem; top: 4.5rem; opacity: 30%"
                    ></i>
                    <i
                        v-else
                        class="bi bi-cloud-moon position-absolute d-flex justify-content-center align-items-center"
                        style="font-size: 14rem; right: 8rem; top: 4.5rem; opacity: 30%"
                    ></i>
                    <div class="d-flex gap-2 px-2">
                        <span
                            class="flex-shrink-1 align-items-center justify-content-center"
                            style="font-size: 5rem; font-weight: bolder"
                            >{{ formatDate(DataDayNow?.Tanggal).split(' ').slice(1, 2).join('') }}</span
                        >
                        <div class="d-flex flex-column flex-shrink-1 justify-content-center align-items-start">
                            <span class="fw-bold fs-2">{{
                                formatDate(DataDayNow?.Tanggal).split(' ').slice(0, 1).join('')
                            }}</span>
                            <span class="fw-medium">{{
                                formatDate(DataDayNow?.Tanggal).split(' ').slice(2, 5).join(' ')
                            }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-12 p-0 ps-md-1">
                <div class="content-container p-3 h-100 d-flex justify-content-center align-items-center flex-column">
                    <template v-if="!DataDayNow.isDayOff">
                        <span class="fs-6">{{ `SHIFT ${DataDayNow?.Nama_Shift.toUpperCase()} HARI INI` }}</span>
                        <div class="d-flex justify-content-evenly w-100 align-items-center">
                            <span class="fs-1 fw-bold">{{
                                DataDayNow?.Jam_Masuk.split(':').slice(0, 2).join(':')
                            }}</span>
                            <span><i class="bi bi-three-dots fs-3"></i></span>
                            <span class="fs-1 fw-bold">{{
                                DataDayNow?.Jam_Keluar.split(':').slice(0, 2).join(':')
                            }}</span>
                        </div>
                        <span
                            class="bg-primary px-2 flex-shrink-1 d-flex align-items-center w-100 text-white text-center justify-content-center rounded-4 py-2 gradientPrimary"
                            ><i class="bi bi-clock-history me-2 d-flex justify-content-center align-items-center"></i>
                            {{
                                DataDayNow?.CheckIn && DataDayNow?.CheckIn !== ''
                                    ? `Keluar ${DataDayNow?.Jam_Keluar.split(':').slice(0, 2).join(':')}`
                                    : `Masuk ${DataDayNow?.Jam_Masuk.split(':').slice(0, 2).join(':')}`
                            }}
                        </span>
                    </template>
                    <template v-else>
                        <span class="fs-6">{{ `Day OFF HARI INI` }}</span>
                        <div class="d-flex justify-content-evenly w-100 align-items-center">
                            <span class="fs-1 fw-bold">00:00</span>
                            <span><i class="bi bi-three-dots fs-3"></i></span>
                            <span class="fs-1 fw-bold"> 00:00 </span>
                        </div>
                        <span
                            class="bg-primary px-2 flex-shrink-1 d-flex align-items-center w-100 text-white text-center justify-content-center rounded-4 py-2 gradientPrimary"
                            ><i class="bi bi-clock-history me-2 d-flex justify-content-center align-items-center"></i>
                            {{ 'OFF TIME' }}
                        </span>
                    </template>
                    <!-- <template v-else>
                        <span class="fs-5 fw-bold text-danger">
                            <i class="bi bi-calendar-x me-2"></i>
                            Day Off ({{ DataDayNow?.status_hari_label || 'OFF' }})
                        </span>
                    </template> -->
                </div>
            </div>
        </div>
    </div>
    <div v-else class="row d-flex justify-content-center fade-in mb-2 mb-md-0">
        <div class="col-12 p-md-2 row p-0">
            <!-- Left Column (Date) - Hidden on mobile -->
            <div class="col-6 d-none d-md-block ps-0 pe-1">
                <div class="content-container p-3 flex-shrink-1 h-100 position-relative skeleton">
                    <div class="d-flex gap-2 px-2">
                        <!-- Day number -->
                        <div class="skeleton" style="width: 5rem; height: 5rem; font-weight: bolder"></div>

                        <div class="d-flex flex-column flex-shrink-1 justify-content-center align-items-start">
                            <!-- Day name -->
                            <div class="skeleton skeleton-text mb-2" style="width: 100px; height: 32px"></div>
                            <!-- Full date -->
                            <div class="skeleton skeleton-text" style="width: 150px; height: 20px"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column (Shift Info) -->
            <div class="col-md-6 col-12 p-0 ps-md-1">
                <div
                    class="content-container p-3 h-100 d-flex justify-content-center align-items-center flex-column skeleton"
                >
                    <!-- Shift title -->
                    <div class="skeleton skeleton-text mb-3" style="width: 180px; height: 20px"></div>

                    <!-- Time range -->
                    <div class="d-flex justify-content-evenly w-100 align-items-center mb-3">
                        <div class="skeleton skeleton-text" style="width: 60px; height: 48px"></div>
                        <div class="skeleton skeleton-text" style="width: 24px; height: 24px"></div>
                        <div class="skeleton skeleton-text" style="width: 60px; height: 48px"></div>
                    </div>

                    <!-- Action button -->
                    <div class="skeleton w-100 rounded-4 py-2" style="height: 40px"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- Week Navigation Skeleton -->
    <div v-if="loading" class="row d-flex justify-content-center mb-2 mb-md-0">
        <div class="week-navigation-skeleton col-12">
            <div class="skeleton-nav-button"></div>
            <div class="week-days-skeleton">
                <div v-for="i in 7" :key="i" class="day-card-skeleton">
                    <div class="skeleton-text date-skeleton"></div>
                    <div class="day-content-skeleton flex-grow-1">
                        <div class="skeleton-text day-name-skeleton"></div>
                        <div class="day-schedule-skeleton">
                            <div class="skeleton-text time-skeleton-small"></div>
                            <div class="skeleton-text time-skeleton-small"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="skeleton-nav-button"></div>
        </div>
    </div>

    <!-- History Skeleton -->
    <div v-if="loading" class="row d-flex justify-content-center mb-2 mb-md-0">
        <div class="col-12 p-md-2 p-0">
            <div class="content-container border py-3 px-2 skeleton">
                <div class="d-flex flex-column gap-4 p-4">
                    <div v-for="i in 3" :key="i" class="d-flex rounded-4 row gap-2 gap-md-0 px-0 py-2">
                        <div class="col-md-6 col-12">
                            <div class="skeleton-history-card"></div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="skeleton-history-card"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <template v-else>
        <!-- DATE BAR -->

        <div class="row d-flex justify-content-center fade-in mb-2 mb-md-0">
            <div class="week-navigation col-12">
                <button class="nav-button" @click="prevWeek">
                    <i class="bi bi-chevron-left d-flex justify-content-center align-items-center"></i>
                </button>

                <div class="week-days cards-container" ref="cardsContainer">
                    <div
                        v-for="(day, idx) in datas"
                        :key="day.date"
                        class="day-card flex-grow-1"
                        :class="{ 'active-day': isToday(day.Tanggal) }"
                        @click="scrollToRecord(day.Tanggal)"
                    >
                        <div
                            v-if="day.ID_Shift"
                            class="d-flex flex-column justify-content-center align-items-center flex-grow-1 w-100"
                        >
                            <span class="day-date">{{ formatDayDate(day.Tanggal) }}</span>
                            <div class="day-content flex-grow-1">
                                <span class="day-name">{{ dayInDate(day.Tanggal) }}</span>
                                <div class="day-schedule">
                                    <span>{{ day.CheckIn && day.CheckIn !== '' ? formatTime(day.CheckIn) : '-' }}</span>
                                    <span>{{
                                        day.CheckOut && day.CheckOut !== '' ? formatTime(day.CheckOut) : '-'
                                    }}</span>
                                </div>
                            </div>
                        </div>
                        <div
                            v-else
                            class="d-flex flex-column justify-content-center align-items-center flex-grow-1 w-100"
                        >
                            <span class="day-date">{{ formatDayDate(day.Tanggal) }}</span>
                            <div class="day-content flex-grow-1">
                                <span class="day-name">{{ dayInDate(day.Tanggal) }}</span>
                                <div class="day-schedule">None</div>
                            </div>
                        </div>
                    </div>
                </div>

                <button class="nav-button" @click="nextWeek">
                    <i class="bi bi-chevron-right d-flex justify-content-center align-items-center"></i>
                </button>
            </div>
        </div>

        <!-- HISTORY -->
        <div class="row d-flex justify-content-center fade-in mb-4 px-md-2">
            <div class="col-12 p-0">
                <div class="content-container bg-white p-3 shadow-sm">
                    <div class="d-flex flex-column gap-3">
                        <div
                            v-for="(item, idx) in datas"
                            :key="idx"
                            :ref="(el) => setHistoryCardRef(el, item.Tanggal)"
                            class="attendance-card cursor-pointer overflow-hidden shadow-sm"
                        >
                            <!-- Header with date -->
                            <div v-if="item.ID_Shift" class="div">
                                <div class="card-header bg-primary text-white p-3">
                                    <h5 class="mb-0 fw-semibold">
                                        {{ formatFullDate(item.Tanggal) }}
                                    </h5>
                                </div>

                                <div class="d-flex flex-column flex-md-row" style="min-height: 10rem">
                                    <!-- Check In Section -->
                                    <div
                                        @click="openFullPicture(item.filePath_Masuk)"
                                        class="check-section col-md-6 p-3 border-end-md"
                                    >
                                        <div class="d-flex flex-column h-100">
                                            <div class="d-flex align-items-center mb-2">
                                                <span class="badge bg-primary-light text-primary rounded-pill me-2">
                                                    <i class="fas fa-sign-in-alt me-1"></i>
                                                    Masuk
                                                </span>
                                                <span v-if="item.CheckIn" class="text-muted small">
                                                    {{ formatTime(item.CheckIn) }}
                                                </span>
                                            </div>

                                            <div v-if="item.CheckIn" class="d-flex flex-grow-1 gap-3">
                                                <div class="attendance-photo-container">
                                                    <img
                                                        v-if="item.filePath_Masuk"
                                                        :src="item.filePath_Masuk"
                                                        alt="Foto Absensi Masuk"
                                                        class="attendance-photo"
                                                    />
                                                    <div
                                                        v-else
                                                        class="attendance-photo-placeholder bg-light d-flex align-items-center justify-content-center"
                                                    >
                                                        <i class="fas fa-camera text-muted"></i>
                                                    </div>
                                                </div>
                                                <div class="d-flex flex-column justify-content-center">
                                                    <div
                                                        class="time-display bg-primary-light-10 rounded-2 px-3 py-2 mb-2"
                                                    >
                                                        <span class="fw-semibold">{{ formatTime(item.CheckIn) }}</span>
                                                    </div>
                                                    <span
                                                        class="text-warning"
                                                        v-if="item.CheckIn && String(item.jenisCheckin) === '180'"
                                                        >Absen Website</span
                                                    >
                                                    <span class="text-success" v-else>Absen Office</span>
                                                    <!-- <span
                                                        class="text-success small"
                                                        v-if="
                                                            isOnTime(item.CheckIn)
                                                        "
                                                    >
                                                        <i
                                                            class="fas fa-check-circle me-1"
                                                        ></i
                                                        >Tepat Waktu
                                                    </span>
                                                    <span
                                                        class="text-warning small"
                                                        v-else
                                                    >
                                                        <i
                                                            class="fas fa-exclamation-circle me-1"
                                                        ></i
                                                        >Terlambat
                                                    </span> -->
                                                </div>
                                            </div>

                                            <div
                                                v-else
                                                class="d-flex flex-grow-1 align-items-center justify-content-center py-4"
                                            >
                                                <div class="text-center text-muted">
                                                    <i class="far fa-clock fa-2x mb-2"></i>
                                                    <p class="mb-0 fw-medium">Belum Absen Masuk</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Check Out Section -->
                                    <div
                                        @click="openFullPicture(item.filePath_Keluar)"
                                        class="check-section col-md-6 p-3"
                                    >
                                        <div class="d-flex flex-column h-100">
                                            <div class="d-flex align-items-center mb-2">
                                                <span class="badge bg-primary-light text-primary rounded-pill me-2">
                                                    <i class="fas fa-sign-out-alt me-1"></i>
                                                    Keluar
                                                </span>
                                                <span v-if="item.CheckOut" class="text-muted small">
                                                    {{ formatTime(item.CheckOut) }}
                                                </span>
                                            </div>

                                            <div v-if="item.CheckOut" class="d-flex flex-grow-1 gap-3">
                                                <div class="attendance-photo-container">
                                                    <img
                                                        v-if="item.filePath_Keluar"
                                                        :src="item.filePath_Keluar"
                                                        alt="Foto Absensi Keluar"
                                                        class="attendance-photo"
                                                    />
                                                    <div
                                                        v-else
                                                        class="attendance-photo-placeholder bg-light d-flex align-items-center justify-content-center"
                                                    >
                                                        <i class="fas fa-camera text-muted"></i>
                                                    </div>
                                                </div>
                                                <div class="d-flex flex-column justify-content-center">
                                                    <div
                                                        class="time-display bg-primary-light-10 rounded-2 px-3 py-2 mb-2"
                                                    >
                                                        <span class="fw-semibold">{{ formatTime(item.CheckOut) }}</span>
                                                    </div>
                                                    <span
                                                        class="text-warning"
                                                        v-if="item.CheckOut && String(item.jenisCheckOut) === '180'"
                                                        >Absen Website</span
                                                    >
                                                    <span class="text-success" v-else>Absen Office</span>
                                                    <!-- <span
                                                        class="text-success small"
                                                        v-if="
                                                            isEarlyDeparture(
                                                                item.CheckOut
                                                            )
                                                        "
                                                    >
                                                        <i
                                                            class="fas fa-check-circle me-1"
                                                        ></i
                                                        >Normal
                                                    </span>
                                                    <span
                                                        class="text-danger small"
                                                        v-else-if="item.CheckOut"
                                                    >
                                                        <i
                                                            class="fas fa-exclamation-circle me-1"
                                                        ></i
                                                        >Pulang Cepat
                                                    </span> -->
                                                </div>
                                            </div>

                                            <div
                                                v-else
                                                class="d-flex flex-grow-1 align-items-center justify-content-center py-4"
                                            >
                                                <div class="text-center text-muted">
                                                    <i class="far fa-clock fa-2x mb-2"></i>
                                                    <p class="mb-0 fw-medium">Belum Absen Keluar</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="">
                                <div class="card-header bg-primary text-white p-3">
                                    <h5 class="mb-0 fw-semibold">
                                        {{ formatFullDate(item.Tanggal) }}
                                    </h5>
                                </div>

                                <div class="d-flex flex-grow-1 align-items-center justify-content-center py-4">
                                    <div class="text-center text-muted">
                                        <i class="fa-solid fa-bell-slash fs-2 mb-2"></i>
                                        <p class="mb-0 fw-medium">Tidak ada shift hari ini</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- PICTURE MODAL -->
        <div v-if="isPictureOpen" class="modal-backdrop fade-in" @click="closeAttendanceModal"></div>

        <div v-if="isPictureOpen" class="attendance-modal zoom-in">
            <div class="attendance-modal-content px-2 px-md-3 py-3 d-flex flex-column align-items-end">
                <i @click="closeFullPicture" class="fa-solid fa-xmark fs-1 rounded-2 px-2 py-1" style="right: 0"></i>
                <img :src="fullPicture" alt="Foto Absensi Keluar" class="attendance-photo" />
            </div>
        </div>

        <!-- modal -->
        <div v-if="isAttendanceModalOpen" class="modal-backdrop fade-in" @click="closeAttendanceModal"></div>
        <div v-if="isAttendanceModalOpen" class="attendance-modal zoom-in">
            <div class="attendance-modal-content overflow-auto px-4 py-3" style="max-height: 85vh">
                <h3 class="mb-md-4">Formulir Absensi</h3>
                <form @submit.prevent="submitAttendance">
                    <div class="row">
                        <div class="col-md-6 mb-4 mb-md-0">
                            <label class="form-label">Foto Bukti Kehadiran</label>

                            <div class="photo-preview-container">
                                <img v-if="capturedImage" :src="capturedImage" alt="Foto Absen" />
                                <div
                                    v-else
                                    class="text-center d-flex flex-column justify-content-center align-items-center"
                                >
                                    <i
                                        class="bi bi-image d-flex justify-content-center align-items-center mb-3"
                                        style="font-size: 2.5rem"
                                    ></i>
                                    <p class="px-2">Silahkan Ambil foto dengan menekan tombol kamera</p>
                                </div>
                            </div>
                            <button type="button" @click="openCamera" class="btn btn-primary btn-modern w-100">
                                <i class="bi bi-camera-fill me-2 d-flex justify-content-center align-items-center"></i
                                >Buka Kamera
                            </button>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="reason" class="form-label">Deskripsi Absen</label
                                ><textarea class="form-control" id="reason" rows="2" v-model="form.reason"></textarea>
                            </div>
                            <div v-if="showValidationSection" class="validation-section">
                                <div class="validation-status">
                                    <div class="status-item" :class="cameraStatus">
                                        <i
                                            class="bi bi-camera me-2 d-flex justify-content-center align-items-center"
                                        ></i>
                                        <span>Kamera: {{ cameraStatusText }}</span>
                                    </div>
                                    <div class="status-item" :class="locationStatus">
                                        <i
                                            class="bi d-flex justify-content-center align-items-center"
                                            :class="{
                                                'bi-geo-alt': locationStatus === 'pending',
                                                'bi-arrow-repeat': locationStatus === 'checking',
                                                'bi-geo-alt-fill': locationStatus === 'success',
                                                'bi-geo-alt': locationStatus === 'error',
                                            }"
                                        ></i>
                                        <span>Lokasi: {{ locationStatusText }}</span>
                                    </div>
                                </div>

                                <div
                                    v-if="validationChecked && (!cameraActive || !locationActive)"
                                    class="validation-alert"
                                >
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <div>
                                        <p>Pastikan kamera dan lokasi telah diaktifkan sebelum mengambil foto</p>
                                        <button @click="retryValidation" class="btn btn-sm btn-outline-primary mt-1">
                                            <i
                                                class="bi bi-arrow-repeat me-1 d-flex justify-content-center align-items-center"
                                            ></i
                                            >Coba Lagi
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <!-- <div
                                class="mb-md-3 form-control row m-0 d-flex w-100 align-items-center justify-content-between p-md-1 p-2"
                            >
                                <label for="reason" class="col-6"
                                    ><span class="form-label m-0"
                                        >Jenis Absensi</span
                                    ></label
                                ><span
                                    class="col-6"
                                    style="max-width: fit-content"
                                >
                                    <span
                                        v-if="
                                            DataDayNow?.CheckIn &&
                                            !DataDayNow?.CheckOut
                                        "
                                        style="font-size: 0.7rem"
                                        class="bg-warning text-white rounded-4 flex-nowrap px-md-2 py-md-1 px-2 py-1 text-center"
                                        >Absen Keluar</span
                                    >
                                    <span
                                        v-else-if="
                                            !DataDayNow?.CheckIn &&
                                            !DataDayNow?.CheckOut
                                        "
                                        style="font-size: 0.7rem"
                                        class="bg-success text-white rounded-4 flex-nowrap px-md-2 py-md-1 px-2 py-1 text-center"
                                        >Absen Masuk</span
                                    >
                                    <span
                                        v-else
                                        style="font-size: 0.7rem"
                                        class="bg-warning text-white rounded-4 opacity-50 flex-nowrap px-md-2 py-md-1 px-2 py-1 text-center"
                                        >Selesai</span
                                    >
                                </span>
                            </div> -->
                            <div class="validation-section mt-2 py-1">
                                <ul class="validation-list">
                                    <li :class="{ completed: !!capturedImage }">
                                        <i
                                            class="bi"
                                            :class="!!capturedImage ? 'bi-check-circle-fill' : 'bi-circle'"
                                        ></i>
                                        Ambil foto dengan kamera
                                    </li>
                                    <li
                                        :class="{
                                            completed:
                                                (latitude !== null && longitude !== null) || locationError !== null,
                                        }"
                                    >
                                        <i
                                            class="bi"
                                            :class="
                                                (latitude !== null && longitude !== null) || locationError !== null
                                                    ? 'bi-check-circle-fill'
                                                    : 'bi-circle'
                                            "
                                        ></i>
                                        Dapatkan informasi lokasi
                                    </li>
                                    <li
                                        :class="{
                                            completed: form.reason && form.reason.trim().length > 0,
                                        }"
                                    >
                                        <!-- <i
                                            class="bi"
                                            :class="
                                                form.reason &&
                                                form.reason.trim().length > 0
                                                    ? 'bi-check-circle-fill'
                                                    : 'bi-circle'
                                            "
                                        ></i>
                                        Isi deskripsi absen
                                    </li> -->
                                    </li>

                                    <li :class="{ completed: !isSubmitting }">
                                        <i
                                            class="bi"
                                            :class="!isSubmitting ? 'bi-check-circle-fill' : 'bi-bi-arrow-repeat'"
                                        ></i>
                                        Tidak dalam proses pengiriman
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="mt-md-4 d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary btn-modern" @click="closeAttendanceModal">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-success btn-modern" :disabled="!isFormValid">
                            <i
                                v-if="!loadingSimpan"
                                class="bi bi-send-check-fill me-2 d-flex justify-content-center align-items-center"
                            ></i>
                            <span v-else class="spinner-border spinner-border-sm border-2 me-2"></span>
                            Kirim Absensi
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="camera-modal fade-in" v-if="isCameraModalOpen">
            <video ref="cameraFeed" class="camera-modal-feed" autoplay playsinline></video>
            <canvas ref="canvas" style="display: none"></canvas>
            <button class="camera-close-btn" @click="closeCamera">
                <i class="bi bi-x-lg d-flex justify-content-center align-items-center"></i>
            </button>
            <div class="camera-modal-controls d-flex justify-content-center">
                <button class="capture-btn" @click="capturePhoto" :disabled="isLoadingLocation">
                    <span v-if="isLoadingLocation" class="spinner-border text-primary" role="status"></span>
                </button>
            </div>
        </div>
        <!-- Attendance Modal -->
    </template>
</template>

<script>
import axios from 'axios';
import { sha256 } from 'js-sha256';
import { ElMessage, ElNotification } from 'element-plus';
import 'element-plus/dist/index.css';

export default {
    name: 'DashboardAbsensi',
    components: {
        ElMessage,
        ElNotification,
    },
    props: {
        userLogin: Object,
        TodayData: Object,
    },
    data() {
        return {
            cameraActive: false,
            locationActive: false,
            cameraStatus: 'pending', // pending, checking, success, error
            locationStatus: 'pending', // pending, checking, success, error
            cameraStatusText: 'Belum diperiksa',
            locationStatusText: 'Belum diperiksa',
            showValidationSection: false,
            validationChecked: false,

            isPictureOpen: false,
            fullPicture: null,
            closePicture: false,
            isActive: false,
            DataDayNow: {},
            loading: true,
            datas: null,
            latitude: null,
            longitude: null,
            accuracy: null,
            loadingLocation: false,
            locationError: null,

            stream: null,
            capturedImage: null,
            capturedHash: null,
            randomHash: null,
            isLoadingLocation: false,

            isAttendanceModalOpen: false,
            isCameraModalOpen: false,

            form: {
                employeeName: this.userName,
                reason: '',
            },

            isSubmitting: false,

            calendarDate: new Date(),
            attendanceHistory: [],
            historyCardRefs: {},
        };
    },
    computed: {
        dayNight() {
            const sekarang = new Date();
            const jam = sekarang.getHours();

            let keteranganWaktu;

            if (jam >= 6 && jam < 18) {
                keteranganWaktu = 'Siang';
            } else {
                keteranganWaktu = 'Malam';
            }

            return keteranganWaktu;
        },
        pickDateToday() {
            const date = this.dates.find((d) => {
                new Date(d.Tanggal) == new Date();
            });
            console.log(date);
            return date;
        },
        filteredAttendanceHistory() {
            const month = this.calendarDate.getMonth();
            const year = this.calendarDate.getFullYear();
            return this.attendanceHistory.filter((record) => {
                const recordDate = new Date(record.date);
                return recordDate.getMonth() === month && recordDate.getFullYear() === year;
            });
        },
        weekDays() {
            return [...Array(7)].map((_, i) => {
                const date = new Date();
                date.setDate(date.getDate() + i);
                return {
                    date: date.toISOString(),
                    dayName: date.toLocaleDateString('id-ID', { weekday: 'short' }).toUpperCase(),
                };
            });
        },
        isFormValid() {
            const hasLocation = (this.latitude !== null && this.longitude !== null) || this.locationError !== null;
            const hasCapturedImage = !!this.capturedImage;
            // const hasReason = this.reason !== null;
            const isSubmitting = this.isSubmitting;

            return hasCapturedImage && hasLocation && !isSubmitting;
        },
        notif() {
            const hasLocation = (this.latitude !== null && this.longitude !== null) || this.locationError !== null;
            const hasCapturedImage = !!this.capturedImage;
            const hasReason = this.reason !== null;
            const isSubmitting = this.isSubmitting;

            if (!hasLocation) {
                return 'Lokasi belum didapatkan';
            }
            if (!hasCapturedImage) {
                return 'Foto bukti kehadiran belum diambil';
            }
            if (!hasReason) {
                return 'Deskripsi absen tidak boleh kosong';
            }
        },
    },

    methods: {
        // isAbsenMasukKeluar(){

        // },
        openFullPicture(url) {
            if (!url) return;
            this.fullPicture = url;
            this.isPictureOpen = true;
        },
        closeFullPicture() {
            this.fullPicture = null;
            this.isPictureOpen = false;
        },
        test() {
            this.DataDayNow = this.TodayData;
            console.log(this.DataDayNow);
            this.isActive = true;
        },
        async nextWeek() {
            if (this.datas > 0) {
                alert('Terjadi kesalahan saat minggu selanjut nya..');
                return;
            }
            try {
                this.loading = true;
                const response = await axios.get('/absensi/getUserShift', {
                    params: {
                        tanggal: this.datas[0].Tanggal,
                        jenis: 'next',
                    },
                });

                if (response.status === 200) {
                    this.datas = response.data.data;
                }
            } catch (error) {
                console.error('Error fetching data:', error);
            } finally {
                this.loading = false;
            }
        },
        async prevWeek() {
            if (this.datas > 0) {
                alert('Terjadi kesalahan saat minggu selanjut nya..');
                return;
            }
            try {
                this.loading = true;
                const response = await axios.get('/absensi/getUserShift', {
                    params: {
                        tanggal: this.datas[0].Tanggal,
                        jenis: 'prev',
                    },
                });

                if (response.status === 200) {
                    this.datas = response.data.data;
                }
            } catch (error) {
                console.error('Error fetching data:', error);
            } finally {
                this.loading = false;
            }
        },

        isToday(dateString) {
            const today = new Date();
            const date = new Date(dateString);

            return (
                date.getDate() === today.getDate() &&
                date.getMonth() === today.getMonth() &&
                date.getFullYear() === today.getFullYear()
            );
        },
        dayInDate(date) {
            const daten = new Date(date);
            return daten.toLocaleDateString('id-ID', {
                weekday: 'short',
            });
        },

        formatDayDate(dateString) {
            const date = new Date(dateString);
            return date.getDate();
        },

        // Helper function to generate week days
        generateWeekDays() {
            const today = new Date();
            const currentDay = today.getDay(); // 0 (Sunday) to 6 (Saturday)
            const startOfWeek = new Date(today);
            startOfWeek.setDate(today.getDate() - currentDay); // Start from Sunday

            return Array.from({ length: 7 }).map((_, index) => {
                const date = new Date(startOfWeek);
                date.setDate(startOfWeek.getDate() + index);

                return {
                    date: date.toISOString(),
                    dayName: date.toLocaleDateString('id-ID', { weekday: 'short' }).toUpperCase(),
                    isToday: this.isToday(date),
                };
            });
        },
        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('id-ID', {
                weekday: 'long',
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            });
        },
        formatFullDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('id-ID', {
                weekday: 'long',
                day: '2-digit',
                month: 'long',
                year: 'numeric',
            });
        },
        isOnTime(checkInTime) {
            if (!checkInTime) return false;

            const checkIn = new Date(checkInTime);
            const expectedTime = new Date(checkIn);

            // Set jam 08:00 sebagai batas waktu masuk
            expectedTime.setHours(8, 0, 0, 0);

            return checkIn <= expectedTime;
        },
        isEarlyDeparture(checkOutTime) {
            if (!checkOutTime) return false;

            const checkOut = new Date(checkOutTime);
            const expectedTime = new Date(checkOut);

            // Set jam 17:00 sebagai batas waktu pulang
            expectedTime.setHours(17, 0, 0, 0);

            return checkOut >= expectedTime;
        },
        formatTime(dateString) {
            const date = new Date(dateString);
            return date
                .toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                })
                .replace('.', ':');
        },
        getCurrentPosition(options = {}) {
            return new Promise((resolve, reject) => {
                if (!navigator.geolocation) {
                    reject(new Error('Geolocation tidak didukung oleh browser ini.'));
                    return;
                }

                navigator.geolocation.getCurrentPosition(resolve, reject, options);
            });
        },
        async getGeolocationWithFallback() {
            try {
                return await this.getCurrentPosition({
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 0,
                });
            } catch (firstError) {
                return await this.getCurrentPosition({
                    enableHighAccuracy: false,
                    timeout: 20000,
                    maximumAge: 60000,
                });
            }
        },
        getGeoLocation() {
            this.loadingLocation = true;
            this.locationError = null;

            this.getGeolocationWithFallback()
                .then((position) => {
                    this.latitude = position.coords.latitude;
                    this.longitude = position.coords.longitude;
                    this.accuracy = position.coords.accuracy;
                    this.loadingLocation = false;
                })
                .catch((error) => {
                    this.loadingLocation = false;
                    this.latitude = null;
                    this.longitude = null;
                    this.accuracy = null;
                    this.locationError = 'Gagal mendapatkan lokasi: ' + error.message;
                    console.error('Error getting geolocation:', error);
                });
        },

        // async openCamera() {
        //     this.isCameraModalOpen = true;
        //     this.capturedImage = null;
        //     await this.$nextTick();
        //     try {
        //         this.stream = await navigator.mediaDevices.getUserMedia({
        //             video: { facingMode: "user" },
        //         });
        //         if (this.$refs.cameraFeed)
        //             this.$refs.cameraFeed.srcObject = this.stream;
        //     } catch (e) {
        //         alert("Kamera tidak dapat diakses.");
        //         this.isCameraModalOpen = false;
        //     }
        // },
        async openCamera() {
            // Tampilkan section validasi
            this.showValidationSection = true;

            // Lakukan validasi
            await this.validateCameraAndLocation();

            // Jika validasi gagal, berhenti di sini
            if (!this.cameraActive || !this.locationActive) {
                // Tampilkan notifikasi error
                this.showValidationError();
                return;
            }

            // Lanjutkan dengan kode openCamera yang sudah ada
            this.isCameraModalOpen = true;
            this.capturedImage = null;
            await this.$nextTick();
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user' },
                });
                if (this.$refs.cameraFeed) this.$refs.cameraFeed.srcObject = this.stream;
            } catch (e) {
                alert('Kamera tidak dapat diakses.');
                this.isCameraModalOpen = false;
            }
        },
        async validateCameraAndLocation() {
            // Reset status
            this.cameraStatus = 'checking';
            this.locationStatus = 'checking';
            this.cameraStatusText = 'Memeriksa...';
            this.locationStatusText = 'Memeriksa...';

            // Jalankan validasi kamera dan lokasi secara paralel
            await Promise.allSettled([this.validateCamera(), this.validateLocation()]);

            this.validationChecked = true;
        },
        async validateCamera() {
            try {
                // Cek apakah browser mendukung getUserMedia
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    throw new Error('Browser tidak mendukung akses kamera');
                }

                const stream = await navigator.mediaDevices.getUserMedia({
                    video: true,
                });
                this.cameraActive = true;
                this.cameraStatus = 'success';
                this.cameraStatusText = 'Aktif';

                // Hentikan stream yang tidak digunakan
                stream.getTracks().forEach((track) => track.stop());
            } catch (error) {
                console.error('Kamera tidak dapat diakses:', error);
                this.cameraActive = false;
                this.cameraStatus = 'error';

                if (error.name === 'NotAllowedError') {
                    this.cameraStatusText = 'Izin ditolak';
                } else if (error.name === 'NotFoundError' || error.name === 'OverconstrainedError') {
                    this.cameraStatusText = 'Kamera tidak ditemukan';
                } else {
                    this.cameraStatusText = 'Tidak dapat diakses';
                }
            }
        },
        validateLocation() {
            return new Promise((resolve) => {
                if (!navigator.geolocation) {
                    this.locationActive = false;
                    this.locationStatus = 'error';
                    this.locationStatusText = 'Tidak didukung';
                    resolve();
                    return;
                }

                this.getGeolocationWithFallback()
                    .then((position) => {
                        this.locationActive = true;
                        this.locationStatus = 'success';
                        this.locationStatusText = 'Aktif';
                        this.latitude = position.coords.latitude;
                        this.longitude = position.coords.longitude;
                        this.accuracy = position.coords.accuracy;
                        resolve();
                    })
                    .catch((error) => {
                        console.error('Lokasi tidak dapat diakses:', error);
                        this.locationActive = false;
                        this.locationStatus = 'error';

                        switch (error.code) {
                            case error.PERMISSION_DENIED:
                                this.locationStatusText = 'Izin ditolak';
                                break;
                            case error.POSITION_UNAVAILABLE:
                                this.locationStatusText = 'Tidak tersedia';
                                break;
                            case error.TIMEOUT:
                                this.locationStatusText = 'Timeout';
                                break;
                            default:
                                this.locationStatusText = 'Tidak dapat diakses';
                        }

                        resolve();
                    });
            });
        },
        showValidationError() {
            let errorMessage = 'Perlu mengaktifkan:';

            if (!this.cameraActive) {
                errorMessage += '\n- Kamera';
            }

            if (!this.locationActive) {
                errorMessage += '\n- Lokasi';
            }

            errorMessage += '\n\nSilakan berikan izin yang diperlukan dan coba lagi.';

            ElMessage({
                showClose: true,
                message: errorMessage,
                type: 'error',
                customStyle: {
                    'z-index': '1050',
                },
            });
        },
        retryValidation() {
            this.cameraStatus = 'pending';
            this.locationStatus = 'pending';
            this.cameraStatusText = 'Belum diperiksa';
            this.locationStatusText = 'Belum diperiksa';
            this.validateCameraAndLocation();
        },

        closeCamera() {
            if (this.stream) this.stream.getTracks().forEach((t) => t.stop());
            this.isCameraModalOpen = false;
        },
        capturePhoto() {
            this.isLoadingLocation = true;
            if (!this.$refs.cameraFeed || !this.$refs.cameraFeed.srcObject) {
                alert('Kamera belum aktif.');
                this.isLoadingLocation = false;
                return;
            }

            this.getGeolocationWithFallback()
                .then((pos) => {
                    this.latitude = pos.coords.latitude;
                    this.longitude = pos.coords.longitude;
                    this.accuracy = pos.coords.accuracy;
                    this.drawCanvasWithWatermark(
                        `Lok: ${this.latitude.toFixed(4)}, ${this.longitude.toFixed(4)}`,
                        new Date().toLocaleString('id-ID', {
                            timeZone: 'Asia/Jakarta',
                            dateStyle: 'short',
                            timeStyle: 'short',
                        }),
                    );
                })
                .catch((error) => {
                    this.latitude = null;
                    this.longitude = null;
                    this.accuracy = null;
                    this.drawCanvasWithWatermark(
                        'Lokasi tidak terdeteksi',
                        new Date().toLocaleString('id-ID', {
                            timeZone: 'Asia/Jakarta',
                            dateStyle: 'short',
                            timeStyle: 'short',
                        }),
                    );
                    console.error('Error mengambil lokasi untuk watermark:', error);
                });
            // if (
            //     !this.latitude ||
            //     !this.longitude ||
            //     !this.accuracy ||
            //     this.latitude == 0 ||
            //     this.longitude == 0 ||
            //     this.accuracy == 0
            // ) {
            //     ElNotification({
            //         showClose: true,
            //         message:
            //             "Terjadi kesalahan mengambil lokasi,silahkan nyalakan lokasi dan refresh website.",
            //         type: "error",
            //         customStyle: {
            //             "z-index": "1050",
            //         },
            //     });
            //     this.latitude = null;
            //     this.longitude = null;
            //     this.accuracy = null;
            //     this.capturedImage = null;
            //     this.capturedHash = null;
            //     this.randomHash = null;

            //     this.isLoadingLocation = false;
            //     this.closeCamera();
            // }
            // dd();
        },
        drawCanvasWithWatermark(locationText, timeText, employeeId) {
            // alert(this.latitude, this.longitude, this.accuracy);

            const video = this.$refs.cameraFeed;
            const canvas = this.$refs.canvas;
            const ctx = canvas.getContext('2d');

            if (!video.videoWidth || !video.videoHeight) {
                this.isLoadingLocation = false;
                this.closeCamera();
                return;
            }

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            // Mirror video
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            ctx.setTransform(1, 0, 0, 1, 0, 0);

            // Generate random watermark hash
            const randomHash = Math.random().toString(36).substring(2, 10);
            const watermarkLines = [timeText, locationText, randomHash];

            // Draw watermark background
            const padding = 15;
            const fontSize = 22;
            const lineHeight = fontSize * 1.5;

            ctx.font = `bold ${fontSize}px Inter, Arial, sans-serif`;
            ctx.textAlign = 'left';

            const maxLineWidth = Math.max(...watermarkLines.map((line) => ctx.measureText(line).width));
            const bgWidth = maxLineWidth + padding * 2;
            const bgHeight = watermarkLines.length * lineHeight + padding * 2;
            const bgX = 0;
            const bgY = canvas.height - bgHeight;

            ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
            ctx.fillRect(bgX, bgY, bgWidth, bgHeight);

            ctx.fillStyle = 'rgba(255, 255, 255, 0.95)';
            ctx.shadowColor = 'black';
            ctx.shadowBlur = 4;

            watermarkLines.forEach((line, index) => {
                const y = canvas.height - bgHeight + padding + (index + 1) * lineHeight - lineHeight / 2;
                ctx.fillText(line, padding, y);
            });

            // Convert to image and hash
            const base64Image = canvas.toDataURL('image/png');
            const imageHash = sha256(base64Image + '|' + randomHash);

            // Simpan ke data
            this.capturedImage = base64Image;
            this.capturedHash = imageHash;
            this.randomHash = randomHash;

            this.isLoadingLocation = false;
            this.closeCamera();
        },

        openAttendanceModal() {
            this.form.employeeName = this.userName;
            this.isAttendanceModalOpen = true;
            this.getGeoLocation();
        },
        closeAttendanceModal() {
            this.isAttendanceModalOpen = false;
            this.form.reason = '';
            this.capturedImage = null;
            this.capturedHash = null;
            this.randomHash = null;
            this.latitude = null;
            this.longitude = null;
            this.accuracy = null;
            this.locationError = null;
            this.loadingLocation = false;
            this.isSubmitting = false;

            // Reset status validasi
            this.showValidationSection = false;
            this.validationChecked = false;
            this.cameraActive = false;
            this.locationActive = false;
            this.cameraStatus = 'pending';
            this.locationStatus = 'pending';
            this.cameraStatusText = 'Belum diperiksa';
            this.locationStatusText = 'Belum diperiksa';
        },
        async getDataToday() {
            try {
                const response = await axios.get('/absensi/getDataToday');
                if (response.status == 200) {
                    this.DataDayNow = response.data.data;
                    console.log(this.DataDayNow);
                }
            } catch (error) {
                ElMessage({
                    showClose: true,
                    message: 'Error, Terjadi kesalahan mengambil data hari ini!.',
                    type: 'error',
                    customStyle: {
                        'z-index': '1050',
                    },
                });
            }
        },
        async getData() {
            const now = new Date().toISOString(); // Format: YYYY-MM-DD

            try {
                const response = await axios.get('/absensi/getUserShift', {
                    params: {
                        tanggal: now,
                        jenis: 'now',
                    },
                });
                if (response.status === 200) {
                    this.datas = response.data.data;
                }
            } catch (error) {
                console.error('Error fetching data:', error);
            } finally {
                this.loading = false;
            }
        },
        async submitAttendance() {
            if (!this.isFormValid) {
                alert('Silakan lengkapi form dan ambil foto absensi.');
                return;
            }

            if (!this.capturedImage) {
                alert('Silakan ambil foto absensi terlebih dahulu.');
                return;
            }

            if (this.latitude === null && this.longitude === null && this.locationError === null) {
                alert('Sedang mencoba mendapatkan lokasi Anda. Mohon tunggu atau coba lagi.');
                return;
            }

            const blob = this.dataURLtoBlob(this.capturedImage);
            const fileName = `absensi_foto_${Date.now()}_${this.randomHash}.png`;
            const file = new File([blob], fileName, { type: 'image/png' });

            const formData = new FormData();
            formData.append('photo', file);
            formData.append('alasan', this.form.reason || '');
            formData.append('employee_id', this.employeeId);
            formData.append('location_latitude', this.latitude || null);
            formData.append('location_longitude', this.longitude || null);
            formData.append('accuracy', this.accuracy || null);
            const now = new Date();
            const wibOffsetMs = 7 * 60 * 60 * 1000;
            const wibDate = new Date(now.getTime() + wibOffsetMs);
            const frontend_timestamp = wibDate.toISOString().slice(0, 19).replace('T', ' ');

            formData.append('frontend_timestamp', frontend_timestamp);
            formData.append('Kode_Karyawan', this.userLogin.Kode_Karyawan || null);
            if (this.DataDayNow?.CheckIn && !this.DataDayNow?.CheckOut) {
                formData.append('Jenis', 'keluar');
            } else {
                formData.append('Jenis', 'masuk');
            }
            formData.append('UserID', this.userLogin.Id_Users || null);
            formData.append('UserID_Absen', this.userLogin.UserID_Absen || null);
            formData.append('image', this.capturedImage || null);
            formData.append('image_hash', this.capturedHash || null);
            formData.append('watermark_hash', this.randomHash || null);

            this.isSubmitting = true;

            try {
                this.loadingSimpan = true;
                const response = await axios.post('/absensi/submitAbsen', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });

                if (response.data.status === 200) {
                    // alert(
                    //     response.data.message || "Absensi berhasil diupload!"
                    // );
                    ElNotification({
                        showClose: true,
                        message: 'Congrats, Berhasil Menambahkan Absen.',
                        type: 'success',
                        customStyle: {
                            'z-index': '1050',
                        },
                    });
                    this.getData();
                    this.getDataToday();
                } else {
                    alert(response.data.message || 'Terjadi kesalahan saat submit absensi.');
                }
            } catch (error) {
                console.error('Error saat submit absensi:', error.response ? error.response.data : error);
                let errorMessage = 'Terjadi kesalahan tidak diketahui saat submit absensi.';

                if (error.response) {
                    if (error.response.status === 422 && error.response.data.errors) {
                        errorMessage =
                            'Validasi Gagal:\n' + Object.values(error.response.data.errors).flat().join('\n');
                    } else if (error.response.data && error.response.data.message) {
                        errorMessage = error.response.data.message;
                    } else {
                        errorMessage = `Server Error: ${error.response.status} ${error.response.statusText}`;
                    }
                } else if (error.request) {
                    errorMessage = 'Tidak ada respons dari server. Periksa koneksi internet Anda.';
                } else {
                    errorMessage = error.message;
                }
                alert(errorMessage);
            } finally {
                this.isSubmitting = false;
                this.loadingSimpan = false;
                this.closeAttendanceModal();
            }
        },

        dataURLtoBlob(dataurl) {
            const arr = dataurl.split(',');
            const mime = arr[0].match(/:(.*?);/)[1];
            const bstr = atob(arr[1]);
            let n = bstr.length;
            const u8arr = new Uint8Array(n);
            while (n--) {
                u8arr[n] = bstr.charCodeAt(n);
            }
            return new Blob([u8arr], { type: mime });
        },

        getStatusClass(status) {
            if (status === 'Hadir') return 'bg-success';
            if (status === 'Izin') return 'bg-warning text-dark';
            if (status === 'Sakit') return 'bg-danger';
            return 'bg-secondary';
        },

        setHistoryCardRef(el, date) {
            if (el) {
                this.historyCardRefs[date] = el;
            }
        },
        scrollToRecord(date) {
            const el = this.historyCardRefs[date];
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.classList.add('highlighted');
                setTimeout(() => el.classList.remove('highlighted'), 1500);
            }
        },
    },
    beforeUpdate() {
        this.historyCardRefs = {};
    },
    mounted() {
        this.clockInterval = setInterval(() => {
            this.liveClock = new Date().toLocaleTimeString('id-ID');
        }, 1000);
        setTimeout(() => {
            this.test();
        }, 1000);

        this.getData();
    },
    beforeUnmount() {
        clearInterval(this.clockInterval);
    },
};
</script>

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
    --gradient-success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --gradient-info: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --gradient-dark: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
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
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.5), transparent);
    animation: shimmer 1.5s infinite;
}

.skeleton-text {
    border-radius: 4px;
}

/* Styles untuk informasi validasi form */
.validation-info {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    border-left: 4px solid #6c757d;
}

.validation-title {
    font-weight: 600;
    margin-bottom: 10px;
    color: #495057;
    font-size: 0.9rem;
}

.validation-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.validation-list li {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 0;
    font-size: 0.85rem;
    color: #6c757d;
}

.validation-list li.completed {
    color: #198754;
}

.validation-list li i {
    font-size: 0.9rem;
}

.validation-list li.completed i {
    color: #198754;
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
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
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

/* Styles untuk validasi section */
.validation-section {
    border: 1px solid var(--hcis-legacy-bootstrap-gray);
    border-radius: 8px;
    padding: 15px;
    background-color: #f8f9fa;
}

.validation-status {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-bottom: 15px;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 0px;
    padding: 8px 10px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
}

.status-item.pending {
    background-color: var(--hcis-legacy-bootstrap-gray);
    color: #6c757d;
}

.status-item.checking {
    background-color: #fff3cd;
    color: #856404;
}

.status-item.success {
    background-color: #d4edda;
    color: #155724;
}

.status-item.error {
    background-color: var(--hcis-legacy-bootstrap-danger);
    color: #721c24;
}

.validation-alert {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 15px;
    background-color: #fff3cd;
    border-left: 4px solid #ffc107;
    border-radius: 4px;
    color: #856404;
}

.validation-alert i {
    font-size: 18px;
}

.validation-alert p {
    margin: 0;
    font-size: 14px;
}

/* Responsive design */
@media (max-width: 576px) {
    .validation-status {
        gap: 10px;
    }

    .status-item {
        width: 100%;
    }
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
    background-color: var(--hcis-slate-200);
}

.skeleton::after {
    content: '';
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
    background-color: var(--hcis-slate-200);
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
    background-color: var(--hcis-slate-200);
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
    background-color: var(--hcis-slate-200);
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
    background: var(--hcis-slate-100);
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
    background: var(--hcis-slate-100);
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
    background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-dark) 100%);
    box-shadow: var(--shadow);
}

.lightGradientPrimary {
    background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-dark) 100%);
}

.MarkHari {
    flex-grow: 0.3;
}

body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    font-family:
        'Inter',
        -apple-system,
        BlinkMacSystemFont,
        'Segoe UI',
        Roboto,
        sans-serif;
    color: var(--text);
    line-height: 1.6;
}

/* Modern Header (keeping original styles) */
.modern-header {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    box-shadow:
        0 20px 25px -5px rgba(0, 0, 0, 0.1),
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
    content: '';
    position: absolute;
    top: 0%;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
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

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h4 {
    margin: 0;
    font-weight: 600;
    color: var(--text);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
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
    background: linear-gradient(45deg, transparent 40%, rgba(255, 255, 255, 0.1) 50%, transparent 60%);
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
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition:
        width 0.3s ease,
        height 0.3s ease;
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
    background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-dark) 100%);
    color: white;
}

.day-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.day-date {
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.day-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex-grow: 1;
    gap: 0.25rem;
    width: 100%;
}

.day-name {
    font-size: 0.875rem;
    font-weight: 500;
}

.day-schedule {
    display: flex;
    width: 100%;
    flex-direction: column;
    align-items: center;
    font-size: 0.75rem;
    background: white;
    padding: 0.25rem 0.5rem;
    border-radius: 0.5rem;
    color: var(--text);
    flex-grow: 1;
}

/* Form Styles */
.form-section {
    background: var(--surface);
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid var(--border);
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: flex;
    align-items: center;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.form-label i {
    color: var(--primary);
}

.modern-input,
.modern-select,
.modern-textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    background: var(--surface);
    color: var(--text);
}

.modern-input:focus,
.modern-select:focus,
.modern-textarea:focus {
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

    .nav-controls {
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

/* Responsive Design */
@media (max-width: 768px) {
    .modal-container {
        margin: 0.5rem;
        max-height: 95vh;
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

    .modal-footer {
        flex-direction: column;
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
    object-fit: contain;
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
