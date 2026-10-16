<template>
    <!-- Header -->
    <div class="row d-flex align-items-center justify-content-center mb-3 fade-in">
        <div class="col-md-12">
            <div class="modern-header">
                <div class="header-background"></div>
                <div class="header-content">
                    <div class="row align-items-center">
                        <div class="col-sm-8">
                            <div class="header-info">
                                <div
                                    @click="getData()"
                                    class="icon-container d-flex align-items-center justify-content-center"
                                >
                                    <i class="bi bi-person-badge d-flex justify-content-center align-items-center"></i>
                                </div>
                                <div class="text-content">
                                    <h2 class="title fw-bold text-white text-start">
                                        Halo,
                                        {{ capitalize(namaKaryawan?.split(' ').slice(0, 2).join(' ')) }}
                                    </h2>
                                    <p v-if="IzinApprover?.length != 0" class="subtitle fw-bold">
                                        Approver izin anda : <br />
                                        <span v-for="(data, index) in IzinApprover" :key="index">
                                            {{ index + 1 }}.
                                            {{ capitalize(data.Nama) }}
                                            <br />
                                        </span>
                                    </p>
                                    <p v-else class="subtitle fw-bold">Anda Belum memiliki approver izin</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div
                                class="header-actions d-flex gap-2 flex-nowrap flex-sm-column justify-content-center justify-content-md-start justify-content-md-center align-items-md-end"
                            >
                                <div class="date-range px-2 px-md-3 py-1 gap-2 gap-md-3">
                                    <div class="date-item">
                                        <i
                                            v-if="dayNight == 'Siang'"
                                            class="bi bi-cloud-sun-fill me-2 fs-4 fade-in"
                                        ></i>
                                        <i v-else class="bi bi-cloud-moon-fill me-2 fs-4 fade-in"></i>
                                        <span class="fs-md-6 text-header">{{ formatDateString(currentDate) }}</span>
                                    </div>
                                </div>
                                <button
                                    class="izin-btn-modern btn-primary flex-nowrap w-full px-md-2 py-md-2"
                                    @click="showAssignModal = true"
                                    :disabled="isTodayOff"
                                >
                                    <i
                                        class="bi bi-plus-circle me-2 d-flex justify-content-center align-items-center"
                                    ></i>
                                    <span class="text-header">Ajukan Izin</span>
                                </button>
                                <!-- OFF DAY BANNER -->
                                <div
                                    v-if="isTodayOff"
                                    class="alert alert-warning mt-3 text-center fw-bold rounded-3 shadow-sm"
                                >
                                    <i class="bi bi-calendar-x fs-4 me-2"></i>
                                    Hari ini adalah
                                    <span class="text-danger">Day Off</span> ({{ todayOffLabel }}) - Pengajuan izin
                                    dinonaktifkan.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- info bar -->
    <div class="row d-flex justify-content-center mb-3 fade-in align-items-center">
        <div class="col-md-12">
            <div class="leave-info-panel fade-in">
                <div class="container-fluid px-0">
                    <div class="row g-2 g-md-3 justify-content-center">
                        <!-- Sisa Cuti -->
                        <div class="col-4">
                            <div class="info-card bg-white rounded shadow-sm p-3 h-100 position-relative">
                                <div
                                    class="d-flex align-items-start h-100 flex-column flex-md-row justify-content-start justify-content-md-start w-100"
                                >
                                    <div style="" class="icon-circle bg-primary-light me-md-3">
                                        <i class="fas fa-calendar-check text-primary"></i>
                                    </div>
                                    <div>
                                        <div class="info-value text-dark fw-bold fs-4">
                                            {{ dataCuti.Sisa_Cuti }}
                                        </div>
                                        <div class="info-label text-muted small">Sisa Cuti</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Hutang Cuti -->
                        <div class="col-4">
                            <div class="info-card bg-white rounded shadow-sm p-3 h-100 position-relative">
                                <div
                                    class="d-flex align-items-start h-100 flex-column flex-md-row justify-content-start justify-content-md-start w-100"
                                >
                                    <div class="icon-circle bg-danger-light me-3">
                                        <i class="fas fa-exclamation-triangle text-danger"></i>
                                    </div>
                                    <div>
                                        <div class="info-value text-dark fw-bold fs-4">
                                            {{ dataCuti.Hutang_Cuti }}
                                        </div>
                                        <div class="info-label text-muted small">Hutang Cuti</div>
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
                                    <div class="icon-circle bg-warning-light me-3">
                                        <i class="fas fa-clock text-warning"></i>
                                    </div>
                                    <div>
                                        <div class="info-value text-dark fw-bold fs-4">
                                            {{ dataDiproses }}
                                        </div>
                                        <div class="info-label text-muted small">Menunggu Persetujuan</div>
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
        <div class="col-md-12">
            <div class="control-panel">
                <div class="search-filter-section">
                    <div class="d-md-flex flex-grow-1 row gap-0 gap-md-2 px-2 px-md-0">
                        <div class="d-md-none d-block flex-grow-1 col-6 col-md-12 p-1">
                            <input
                                v-if="!selectedDateFilter"
                                v-model="selectedDateFilter"
                                type="date"
                                class="form-control"
                                placeholder="Semua"
                                style="cursor: pointer"
                                title="Pilih Tanggal"
                                @mousedown.prevent="blockAutoSelect"
                            />

                            <div
                                v-else
                                class="form-control d-flex align-items-center justify-content-between"
                                style="cursor: pointer"
                                title="Pilih Tanggal"
                            >
                                <span>{{ formatDateToDMY(selectedDateFilter) }}</span>
                                <span
                                    @click="selectedDateFilter = null"
                                    v-tooltip="'Hapus filter'"
                                    class="closeFilter p-1 rounded-3 ms-2"
                                >
                                    <i class="bi bi-x"></i>
                                </span>
                            </div>
                        </div>
                        <div class="d-md-none d-block flex-grow-1 col-6 col-md-12 p-1 position-relative">
                            <select v-model="filterDropdown" name="" id="" class="form-control">
                                <option selected value="">Semua</option>
                                <option value="izinFull">Izin</option>
                                <option value="cuti">Cuti</option>
                                <option value="sakit">Sakit</option>
                                <option value="pulangCepat">Pulang cepat</option>
                                <option value="terlambat">Terlambat</option>
                            </select>
                            <i
                                v-if="filterDropdown == ''"
                                class="bi bi-chevron-down position-absolute"
                                style="right: 17px; top: 18px"
                            ></i>
                            <span
                                v-else
                                style="right: 17px; top: 13px"
                                @click="filterDropdown = ''"
                                class="closeFilter p-1 rounded-3 ms-2 position-absolute"
                                ><i class="bi bi-x"></i
                            ></span>
                        </div>
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
                            <div class="search-results-count" v-if="searchQuery">
                                {{ permissionRecords.length }} hasil
                            </div>
                            <!-- <div
                                class="search-suggestions"
                                v-if="showSearchSuggestions"
                            >
                                <div
                                    class="suggestion-item"
                                    v-for="suggestion in filteredSuggestions"
                                    :key="suggestion"
                                    @mousedown="selectSuggestion(suggestion)"
                                >
                                    <i class="bi bi-clock-history"></i>
                                    {{ suggestion }}
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
                            </div> -->
                        </div>
                        <div style="width: 10rem" class="d-md-block w-max d-none position-relative ps-0">
                            <select v-model="filterDropdown" name="" id="" class="form-control w-max">
                                <option selected value="">Semua</option>
                                <option value="izinFull">Izin</option>
                                <option value="cuti">Cuti</option>
                                <option value="sakit">Sakit</option>
                                <option value="pulangCepat">Pulang cepat</option>
                                <option value="terlambat">Terlambat</option>
                            </select>
                            <i
                                v-if="filterDropdown == ''"
                                class="bi bi-chevron-down position-absolute"
                                style="right: 25px; top: 14px"
                            ></i>
                            <span
                                v-else
                                style="right: 20px; top: 8px"
                                @click="filterDropdown = ''"
                                class="closeFilter p-1 rounded-3 ms-2 position-absolute"
                                ><i class="bi bi-x"></i
                            ></span>
                        </div>
                    </div>

                    <div class="filters-container">
                        <div class="search-container d-block d-md-none">
                            <i class="bi bi-search search-icon d-flex justify-content-center align-items-center"></i>
                            <input
                                type="text"
                                class="search-input"
                                placeholder="Cari izin..."
                                v-model="searchQuery"
                                @focus="showSearchSuggestions = true"
                                @blur="hideSuggestions"
                            />
                            <div class="search-results-count" v-if="searchQuery">
                                {{ permissionRecords.length }} hasil
                            </div>
                            <!-- <div
                                class="search-suggestions"
                                v-if="showSearchSuggestions"
                            >
                                <div
                                    class="suggestion-item"
                                    v-for="suggestion in filteredSuggestions"
                                    :key="suggestion"
                                    @mousedown="selectSuggestion(suggestion)"
                                >
                                    <i class="bi bi-clock-history"></i>
                                    {{ suggestion }}
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
                            </div> -->
                        </div>

                        <div class="flex-grow-1 d-md-block d-none">
                            <input
                                v-if="!selectedDateFilter"
                                v-model="selectedDateFilter"
                                type="date"
                                class="form-control"
                                placeholder="Semua"
                                style="cursor: pointer"
                                title="Pilih Tanggal"
                            />
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
                            </div>
                        </div>
                        <div class="">
                            <div class="filter-btn py-2 position-relative" style="width: fit-content !important">
                                <button @click="openDialogExport" class="toggle-btn active" title="Card View">
                                    <i
                                        class="bi bi-file-earmark-arrow-up d-flex justify-content-center align-items-center"
                                    ></i>
                                </button>
                            </div>
                        </div>
                        <!-- view Toggle -->
                        <div class="view-toggle py-2">
                            <button
                                class="toggle-btn"
                                :class="{ active: viewMode === 'table' }"
                                @click="setViewMode('table')"
                                title="Tampilan Tabel"
                            >
                                <i class="bi bi-table d-flex justify-content-center align-items-center"></i>
                            </button>
                            <button
                                class="toggle-btn"
                                :class="{ active: viewMode === 'card' }"
                                @click="setViewMode('card')"
                                title="Tampilan Kartu"
                            >
                                <i class="bi bi-grid-3x3-gap d-flex justify-content-center align-items-center"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table & Card -->
    <div class="row d-flex justify-content-center fade-in">
        <div class="col-md-12">
            <div class="content-container">
                <!-- Loading -->
                <div v-if="loading" class="loading-container">
                    <div class="loading-animation">
                        <div class="loading-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                    <p class="loading-text">Memuat data izin...</p>
                </div>

                <!-- Not Found -->
                <div v-else-if="permissionRecords.length == 0 && !loading" class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-inbox d-flex justify-content-center align-items-center"></i>
                    </div>
                    <h4>Tidak Ada Data Izin</h4>
                    <p>Anda belum memiliki pengajuan izin. Klik tombol "Ajukan Izin" untuk membuat pengajuan baru.</p>
                    <button class="izin-btn-modern btn-primary" @click="showAssignModal = true">
                        <i class="bi bi-plus-circle me-2 d-flex justify-content-center align-items-center"></i>
                        Ajukan Izin
                    </button>
                </div>

                <transition name="fade-switch" mode="out-in">
                    <!-- Table -->
                    <div
                        v-if="viewMode === 'table' && !loading && totalData > 0"
                        class="table-view-container"
                        key="table"
                    >
                        <div class="table-responsive-wrapper">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th class="header-cell sticky-column">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-people me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span
                                                    >No Transaksi
                                                    <small class="fw-light">({{ totalData }})</small></span
                                                >
                                            </div>
                                        </th>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-calendar me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Tanggal</span>
                                            </div>
                                        </th>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-chat-square-text me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Alasan</span>
                                            </div>
                                        </th>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-paperclip me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Lampiran</span>
                                            </div>
                                        </th>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-check-circle me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Status</span>
                                            </div>
                                        </th>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-node-plus me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Progress</span>
                                            </div>
                                        </th>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-three-dots me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Aksi</span>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in permissionRecords"
                                        :key="item.No_Transaksi"
                                        class="table-row"
                                        @click="showDetail(item)"
                                    >
                                        <td class="sticky-column">
                                            <div class="transaction-cell">
                                                <div class="transaction-id">
                                                    {{ item.No_Transaksi }}
                                                </div>
                                                <div class="transaction-type" :class="getTypeClass(item.Tipe_Izin)">
                                                    <span class="type-indicator"></span>
                                                    {{ parseIzin(item.Tipe_Izin) }}
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-table-cell">
                                                {{
                                                    formatTanggalIzin(
                                                        item.Tipe_Izin,
                                                        item.Tanggal_Mulai,
                                                        item.Tanggal_Selesai,
                                                        item.Jam_Mulai,
                                                    )
                                                }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="reason-cell" :title="item.Alasan">
                                                {{ truncateText(item.Alasan, 30) }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="attachment-cell">
                                                <a
                                                    v-if="item.Lampiran"
                                                    href="#"
                                                    @click.prevent.stop="viewAttachment(item.Lampiran)"
                                                    target="_blank"
                                                    class="attachment-link"
                                                    :class="{
                                                        'is-loading': loadingAttachment,
                                                    }"
                                                    :disabled="loadingAttachment"
                                                >
                                                    <span
                                                        class="d-flex justify-content-center align-items-center"
                                                        v-if="!loadingAttachment"
                                                    >
                                                        <i
                                                            class="bi me-1 bi-file-earmark-arrow-down d-flex justify-content-center align-items-center"
                                                        ></i>
                                                        Lihat
                                                    </span>
                                                    <span v-else>
                                                        <i class="bi bi-arrow-clockwise animated-spin"></i>
                                                        Memuat...
                                                    </span>
                                                </a>
                                                <span v-else class="no-attachment">
                                                    <i class="bi bi-file-x"></i>
                                                    Tidak ada
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div v-if="item.status == 'NO' && !item.Approver" class="status-cell">
                                                <span class="status-badge status-pending"> Tidak Diproses </span>
                                            </div>
                                            <div v-else class="status-cell">
                                                <span class="status-badge" :class="getStatusClass(item.status)">
                                                    {{ parseStatus(item.status, item.Approver) }}
                                                </span>
                                            </div>
                                        </td>
                                        <td @click.stop @mouseover="approvalData = [item.STATUSLIST]">
                                            <el-tooltip
                                                :content="
                                                    item.Approver == 'Sistem' || item.status == 'Ditolak'
                                                        ? ''
                                                        : tooltipContent
                                                "
                                                raw-content
                                                placement="top"
                                                :width="350"
                                            >
                                                <div class="status-cell">
                                                    {{ item.currentFlow + '/' + item.MaxFlow }}
                                                    Step
                                                </div>
                                            </el-tooltip>
                                        </td>
                                        <td>
                                            <div class="action-cell">
                                                <button
                                                    :disabled="
                                                        hasApprovedOrRejectedStatus(item.STATUSLIST) ||
                                                        item.Approver == 'Sistem'
                                                    "
                                                    class="btn-action delete-btn"
                                                    @click.stop="showDeleteConfirm(item.No_Transaksi)"
                                                    v-tooltip="'Hapus Pengajuan'"
                                                >
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <!-- <button
                                                    class="btn-action more-btn"
                                                    @click.stop="
                                                        showActionMenu(
                                                            item,
                                                            $event
                                                        )
                                                    "
                                                    v-tooltip="'Lainnya'"
                                                >
                                                    <i
                                                        class="bi bi-three-dots-vertical"
                                                    ></i>
                                                </button> -->
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="pagination-container" v-if="totalPages > 1">
                            <button class="page-btn" @click="getData(prevLink)" :disabled="currentPage === 1">
                                <i class="bi bi-chevron-left d-flex justify-content-center align-items-center"></i>
                            </button>
                            <!-- <div class="page-numbers">
                                <button
                                    v-for="page in visiblePages"
                                    :key="page"
                                    class="page-number"
                                    :class="{ active: page === currentPage }"
                                    @click="goToPage(page)"
                                >
                                    {{ page }}
                                </button>
                            </div> -->
                            <div class="page-info">Halaman {{ currentPage }} dari {{ totalPages }}</div>
                            <button class="page-btn" @click="getData(nextLink)" :disabled="currentPage === totalPages">
                                <i class="bi bi-chevron-right d-flex justify-content-center align-items-center"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Card -->
                    <div
                        v-else-if="viewMode === 'card' && !loading && totalData > 0"
                        class="card-view-container"
                        key="card"
                    >
                        <div class="card-grid">
                            <div
                                v-for="item in permissionRecords"
                                :key="item.No_Transaksi"
                                class="permission-card"
                                @click="showDetail(item)"
                            >
                                <div class="card-header" :class="getStatusHeaderClass(item.status)">
                                    <div class="card-date">
                                        {{
                                            formatTanggalIzin(
                                                item.Tipe_Izin,
                                                item.Tanggal_Mulai,
                                                item.Tanggal_Selesai,
                                                item.Jam_Mulai,
                                            )
                                        }}
                                    </div>
                                    <div v-if="item.status == 'NO' && !item.Approver" class="card-status">
                                        Tidak Diproses
                                    </div>
                                    <div v-else class="card-status">
                                        {{ parseStatus(item.status, item.Approver) }}
                                    </div>
                                </div>
                                <div class="card-body row">
                                    <div class="col-md-8 col-6">
                                        <div class="card-row">
                                            <i class="bi bi-hash d-flex justify-content-center align-items-center"></i>
                                            <div class="card-label">No. Transaksi:</div>
                                            <div class="card-value">
                                                {{ item.No_Transaksi }}
                                            </div>
                                        </div>
                                        <div class="card-row">
                                            <i class="bi bi-tag d-flex justify-content-center align-items-center"></i>
                                            <div class="card-label">Jenis Izin:</div>
                                            <div class="card-value">
                                                {{ parseIzin(item.Tipe_Izin) }}
                                            </div>
                                        </div>
                                        <div class="card-row">
                                            <i
                                                class="bi bi-chat-dots d-flex justify-content-center align-items-center"
                                            ></i>
                                            <div class="card-label">Alasan:</div>
                                            <div class="card-value">
                                                {{ truncateText(item.Alasan, 20) }}
                                            </div>
                                        </div>
                                        <div v-if="item.Waktu" class="card-row">
                                            <i class="bi bi-clock d-flex justify-content-center align-items-center"></i>
                                            <div class="card-label">Waktu:</div>
                                            <div class="card-value">
                                                {{ formatSliderTooltip(item.Waktu) }}
                                            </div>
                                        </div>
                                        <div class="card-row">
                                            <i
                                                class="bi bi-paperclip d-flex justify-content-center align-items-center"
                                            ></i>
                                            <div class="card-label">Lampiran:</div>
                                            <div class="card-value">
                                                <a
                                                    v-if="item.Lampiran"
                                                    href="#"
                                                    @click.prevent.stop="viewAttachment(item.Lampiran)"
                                                    target="_blank"
                                                    class="card-link"
                                                    :class="{
                                                        'is-loading': loadingAttachment,
                                                    }"
                                                    :disabled="loadingAttachment"
                                                >
                                                    <span
                                                        class="d-flex justify-content-center align-items-center"
                                                        v-if="!loadingAttachment"
                                                    >
                                                        Lihat File
                                                    </span>
                                                    <span v-else>
                                                        <i class="bi bi-arrow-clockwise animated-spin"></i>
                                                        Memuat...
                                                    </span>
                                                </a>
                                                <span v-else class="no-file"> Tidak ada </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6 p-0">
                                        <div class="card-label flex-column mb-2">Progress:</div>
                                        <div class="card-value" style="height: 50%">
                                            <el-steps
                                                style="font-size: 0.5rem; max-height: 15rem"
                                                direction="vertical"
                                                :active="2"
                                            >
                                                <el-step
                                                    v-for="(data, index) in parsedDataArray(item.STATUSLIST)"
                                                    :status="
                                                        data.status == 'Y'
                                                            ? 'success'
                                                            : data.status == 'T' || data.status == 'P'
                                                              ? 'error'
                                                              : isExpired(item.expired_at)
                                                                ? 'error'
                                                                : 'wait'
                                                    "
                                                    :key="index"
                                                    :title="data.name"
                                                />
                                            </el-steps>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button
                                        :disabled="
                                            hasApprovedOrRejectedStatus(item.STATUSLIST) || item.Approver == 'Sistem'
                                        "
                                        class="card-btn delete-btn"
                                        @click.stop="showDeleteConfirm(item.No_Transaksi)"
                                    >
                                        <i class="bi bi-trash d-flex justify-content-center align-items-center"></i>
                                        Hapus
                                    </button>
                                    <!-- <button
                                        class="card-btn more-btn"
                                        @click.stop="
                                            showActionMenu(item, $event)
                                        "
                                    >
                                        <i
                                            class="bi bi-three-dots d-flex justify-content-center align-items-center"
                                        ></i>
                                    </button> -->
                                </div>
                            </div>
                        </div>

                        <div class="pagination-container" v-if="totalPages > 1">
                            <button class="page-btn" @click="getData(prevLink)" :disabled="currentPage === 1">
                                <i class="bi bi-chevron-left d-flex justify-content-center align-items-center"></i>
                            </button>
                            <div class="page-info">Halaman {{ currentPage }} dari {{ totalPages }}</div>
                            <button class="page-btn" @click="getData(nextLink)" :disabled="currentPage === totalPages">
                                <i class="bi bi-chevron-right d-flex justify-content-center align-items-center"></i>
                            </button>
                        </div>
                    </div>
                </transition>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <!-- <el-dialog
        v-model="showDetailModal"
        :title="`Detail Izin - ${selectedItem?.No_Transaksi || ''}`"
        width="90%"
        :fullscreen="isMobile()"
    >
        <div v-if="selectedItem" class="detail-modal-content">
            <div class="detail-section">
                <div class="detail-row">
                    <div class="detail-label">Jenis Izin:</div>
                    <div class="detail-value">
                        {{ parseIzin(selectedItem.Tipe_Izin) }}
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Tanggal:</div>
                    <div class="detail-value">
                        {{ formatDateString(selectedItem.Tanggal_Mulai) }}
                        <span
                            v-if="
                                selectedItem.Tanggal_Mulai !==
                                    selectedItem.Tanggal_Selesai &&
                                selectedItem.Tanggal_Selesai
                            "
                        >
                            s.d
                            {{ formatDateString(selectedItem.Tanggal_Selesai) }}
                        </span>
                    </div>
                </div>
                <div v-if="selectedItem.Jam_Mulai" class="detail-row">
                    <div class="detail-label">Waktu:</div>
                    <div class="detail-value">
                        {{
                            selectedItem.Jam_Mulai?.split(":")
                                .slice(0, 2)
                                .join(":")
                        }}
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Status:</div>
                    <div
                        v-if="
                            selectedItem.status == 'NO' &&
                            !selectedItem.Approver
                        "
                        class="detail-value"
                    >
                        <span class="status-badge status-pending">
                            Tidak Diproses
                        </span>
                    </div>
                    <div v-else class="detail-value">
                        <span
                            class="status-badge"
                            :class="getStatusClass(selectedItem.status)"
                        >
                            {{
                                parseStatus(
                                    selectedItem.status,
                                    selectedItem.Approver
                                )
                            }}
                        </span>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Alasan:</div>
                    <div class="detail-value">{{ selectedItem.Alasan }}</div>
                </div>
                <div
                    v-if="selectedItem.Keterangan_Penolakan"
                    class="detail-row"
                >
                    <div class="detail-label">Keterangan Penolakan:</div>
                    <div class="detail-value text-danger">
                        {{ selectedItem.Keterangan_Penolakan }}
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Lampiran:</div>
                    <div class="detail-value">
                        <a
                            v-if="selectedItem.Lampiran"
                            href="#"
                            @click.prevent.stop="
                                viewAttachment(selectedItem.Lampiran)
                            "
                            target="_blank"
                            class="detail-link"
                            :class="{ 'is-loading': loadingAttachment }"
                            :disabled="loadingAttachment"
                        >
                            <span
                                class="d-flex justify-content-center align-items-center"
                                v-if="!loadingAttachment"
                            >
                                Lihat Lampiran
                            </span>
                            <span v-else>
                                <i
                                    class="bi bi-arrow-clockwise animated-spin"
                                ></i>
                                Memuat...
                            </span>
                        </a>
                        <span v-else> Tidak ada lampiran </span>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-value">
                        <el-steps
                            style="font-size: 0.5rem; max-width: 50%"
                            :active="2"
                        >
                            <el-step
                                v-for="(data, index) in parsedDataArray(
                                    selectedItem.STATUSLIST
                                )"
                                :status="
                                    data.status == 'Y'
                                        ? 'success'
                                        : data.status == 'T'
                                        ? 'error'
                                        : 'wait'
                                "
                                :key="index"
                                :title="data.name"
                            />
                        </el-steps>
                    </div>
                </div>
            </div>
        </div>
        <template #footer>
            <div class="detail-modal-footer px-0">
                <button
                    class="izin-btn-modern btn-secondary"
                    @click="showDetailModal = false"
                >
                    Tutup
                </button>
                <button
                    class="izin-btn-modern btn-danger"
                    @click="showDeleteConfirm(selectedItem.No_Transaksi)"
                    v-if="selectedItem"
                >
                    <i
                        class="bi bi-trash d-flex justify-content-center align-items-center"
                    ></i>
                    Hapus
                </button>
            </div>
        </template>
    </el-dialog> -->

    <el-dialog
        v-model="showDetailModal"
        :title="`Detail Izin - ${selectedItem?.No_Transaksi || ''}`"
        width="70%"
        :fullscreen="isMobile()"
        class="detail-modal"
        :lock-scroll="true"
    >
        <div v-if="selectedItem" class="detail-modal-content">
            <div class="detail-grid">
                <!-- Setiap detail row menggunakan grid system -->
                <div class="detail-item">
                    <span class="detail-label">Jenis Izin:</span>
                    <span class="detail-value highlight">
                        {{ parseIzin(selectedItem.Tipe_Izin) }}
                    </span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Tanggal:</span>
                    <span class="detail-value">
                        {{ formatDateString(selectedItem.Tanggal_Mulai) }}
                        <span
                            v-if="
                                selectedItem.Tanggal_Mulai !== selectedItem.Tanggal_Selesai &&
                                selectedItem.Tanggal_Selesai
                            "
                        >
                            s.d
                            {{ formatDateString(selectedItem.Tanggal_Selesai) }}
                        </span>
                    </span>
                </div>

                <div v-if="selectedItem.Jam_Mulai" class="detail-item">
                    <span class="detail-label">Waktu:</span>
                    <span class="detail-value">
                        {{ selectedItem.Jam_Mulai?.split(':').slice(0, 2).join(':') }}
                    </span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">
                        <el-tag :type="getStatusTagType(selectedItem.status, selectedItem.Approver)" size="medium">
                            {{ parseStatus(selectedItem.status, selectedItem.Approver) }}
                        </el-tag>
                    </span>
                </div>

                <div class="detail-item full-width">
                    <span class="detail-label">Alasan:</span>
                    <span class="detail-value multiline">
                        {{ selectedItem.Alasan }}
                    </span>
                </div>

                <div v-if="selectedItem.Keterangan_Penolakan" class="detail-item full-width">
                    <span class="detail-label">Keterangan Penolakan:</span>
                    <span class="detail-value multiline text-danger">
                        {{ selectedItem.Keterangan_Penolakan }}
                    </span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Lampiran:</span>
                    <span class="detail-value">
                        <el-button
                            v-if="selectedItem.Lampiran"
                            type="text"
                            :loading="loadingAttachment"
                            @click="viewAttachment(selectedItem.Lampiran)"
                            icon="el-icon-paperclip"
                        >
                            Lihat Lampiran
                        </el-button>
                        <span v-else class="empty-value">-</span>
                    </span>
                </div>

                <div class="detail-item full-width">
                    <div class="approval-progress">
                        <div class="progress-title">Proses Persetujuan:</div>
                        <el-steps :active="getActiveStep(selectedItem.STATUSLIST)" align-center>
                            <el-step
                                v-for="(data, index) in parsedDataArray(selectedItem.STATUSLIST)"
                                :key="index"
                                :title="data.name"
                                :status="getStepStatus(data.status, isExpired(selectedItem.expired_at))"
                            />
                        </el-steps>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="modal-footer-actions p-0 pt-3">
                <button class="izin-btn-modern btn-secondary" @click="showDetailModal = false">Tutup</button>
                <button
                    class="izin-btn-modern btn-danger"
                    @click="showDeleteConfirm(selectedItem.No_Transaksi)"
                    :disabled="
                        hasApprovedOrRejectedStatus(selectedItem.STATUSLIST) || selectedItem.Approver == 'Sistem'
                    "
                    v-if="selectedItem"
                >
                    <i class="bi bi-trash d-flex justify-content-center align-items-center"></i>
                    Hapus
                </button>
            </div>
        </template>
    </el-dialog>

    <!-- Delete Modal -->
    <el-dialog
        v-model="showDeleteModal"
        title="Konfirmasi Hapus"
        width="400px"
        center
        :lock-scroll="true"
        :close-on-click-modal="!loadingProccessData"
        :close-on-press-escape="!loadingProccessData"
        :show-close="!loadingProccessData"
    >
        <div class="delete-modal-content">
            <i class="bi bi-exclamation-triangle warning-icon"></i>
            <p>
                Anda yakin ingin menghapus pengajuan izin
                <strong>{{ deleteItemId }}</strong>?
            </p>
            <p class="text-muted">Aksi ini tidak dapat dibatalkan.</p>
        </div>
        <template #footer>
            <div class="delete-modal-footer">
                <button
                    class="izin-btn-modern btn-secondary"
                    @click="showDeleteModal = false"
                    :disabled="loadingProccessData"
                >
                    Batal
                </button>
                <button
                    class="izin-btn-modern btn-danger"
                    @click="confirmDelete"
                    :disabled="loadingProccessData"
                >
                    <span v-if="loadingProccessData" class="d-flex align-items-center justify-content-center">
                        <i class="bi bi-arrow-clockwise animated-spin me-2"></i>
                        Menghapus...
                    </span>
                    <span v-else class="d-flex align-items-center justify-content-center">
                        <i class="bi bi-trash me-2"></i>
                        Hapus
                    </span>
                </button>
            </div>
        </template>
    </el-dialog>

    <!-- Cuti alert Modal -->
    <el-dialog
        v-model="showCutiAlertModal"
        title="Konfirmasi menambah hutang cuti"
        width="400px"
        center
        :lock-scroll="true"
    >
        <div class="delete-modal-content">
            <i class="bi bi-exclamation-triangle warning-icon"></i>
            <p>
                Kuota cuti anda 0, anda yakin untuk mengambil cuti dan berhutang cuti
                <strong>{{ deleteItemId }}</strong
                >?
            </p>
            <p class="text-danger">
                Hutang cuti menambah
                <span class="fw-bold">{{ hutangTerhitung }}</span
                >.
            </p>
        </div>
        <template #footer>
            <div class="delete-modal-footer">
                <button class="izin-btn-modern btn-secondary" @click="showCutiAlertModal = false">Batal</button>
                <button
                    class="izin-btn-modern btn-danger"
                    @click="
                        showCutiAlertModal = false;
                        assignStep++;
                        assignIzinType2 = 'cutiHutang';
                    "
                >
                    <!-- <i
                        class="bi bi-arrow-right d-flex justify-content-center align-items-center"
                    ></i> -->
                    Lanjut
                </button>
            </div>
        </template>
    </el-dialog>

    <!-- Action Modal -->
    <div class="action-menu" v-if="showActionPopup" :style="actionMenuPosition">
        <div class="action-menu-item" @click="showDetail(selectedItem)"><i class="bi bi-eye"></i> Lihat Detail</div>
        <div class="action-menu-item" @click="editPermission(selectedItem)"><i class="bi bi-pencil"></i> Edit</div>
        <div class="action-menu-item text-danger" @click="showDeleteConfirm(selectedItem.No_Transaksi)">
            <i class="bi bi-trash"></i> Hapus
        </div>
        <div class="action-menu-divider"></div>
        <div class="action-menu-item" @click="showActionPopup = false"><i class="bi bi-x"></i> Tutup</div>
    </div>

    <!-- <div
        class="action-menu-backdrop"
        v-if="showActionPopup"
        @click="showActionPopup = false"
    ></div> -->

    <!-- Modal Assign -->
    <div class="modal-overlay" :class="{ show: showAssignModal }">
        <div class="modal-container assign-modal" @click.stop>
            <!-- Header with progress indicator -->
            <div class="modal-header">
                <div class="header-title-section">
                    <h3>
                        <i class="bi bi-calendar-plus me-2 d-flex justify-content-center align-items-center"></i>
                        Pengajuan Izin
                    </h3>
                    <p class="header-subtitle">Ajukan izin pulang cepat, terlambat, atau sakit</p>
                </div>
                <button class="modal-close" @click="closeAssignModal" aria-label="Close">
                    <i class="bi bi-x-lg d-flex justify-content-center align-items-center"></i>
                </button>
            </div>

            <div class="modal-body">
                <div v-if="assignStep === 1" class="step-content">
                    <div class="form-section">
                        <!-- Jenis Izin -->
                        <h4 class="section-title">
                            <i class="bi bi-calendar-event me-2 d-flex justify-content-center align-items-center"></i>
                            Pilih Jenis Izin
                        </h4>

                        <div class="form-group">
                            <div class="permission-type-options row g-2">
                                <div class="col-12 col-md-6">
                                    <div
                                        class="permission-type-card"
                                        :class="{
                                            selected: assignIzinType === 'izinFull',
                                        }"
                                        @click="handleAssignIzin('izinFull')"
                                    >
                                        <div class="permission-type-icon">
                                            <i class="bi bi-house-door"></i>
                                        </div>
                                        <div class="permission-type-content">
                                            <h6>Izin</h6>
                                            <p class="text-muted small">Pulang cepat dan Datang terlambat</p>
                                        </div>
                                        <div class="permission-type-check">
                                            <i class="bi bi-check-circle-fill" v-if="assignIzinType === 'izinFull'"></i>
                                            <i class="bi bi-circle" v-else></i>
                                        </div>
                                    </div>
                                </div>

                                <!-- Aktifkan Cuti -->

                                <!-- <div class="col-12 col-md-4">
                                    <div
                                        class="permission-type-card"
                                        :class="{
                                            selected: assignIzinType === 'cuti',
                                        }"
                                        @click="handleAssignIzin('cuti')"
                                    >
                                        <div class="permission-type-icon">
                                            <i class="bi bi-clock"></i>
                                        </div>
                                        <div class="permission-type-content">
                                            <h6>Cuti</h6>
                                            <p class="text-muted small">
                                                Izin untuk mengambil cuti
                                            </p>
                                        </div>
                                        <div class="permission-type-check">
                                            <i
                                                class="bi bi-check-circle-fill"
                                                v-if="assignIzinType === 'cuti'"
                                            ></i>
                                            <i class="bi bi-circle" v-else></i>
                                        </div>
                                    </div>
                                </div> -->

                                <!-- AKhir Aktifkan Cuti -->

                                <!-- <div class="col-12 col-md-3">
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
                                                assignIzinType === 'terlambat',
                                        }"
                                        @click="handleAssignIzin('terlambat')"
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
                                                    'terlambat'
                                                "
                                            ></i>
                                            <i class="bi bi-circle" v-else></i>
                                        </div>
                                    </div>
                                </div> -->

                                <div class="col-12 col-md-6">
                                    <div
                                        class="permission-type-card"
                                        :class="{
                                            selected: assignIzinType === 'sakit',
                                        }"
                                        @click="handleAssignIzin('sakit')"
                                    >
                                        <div class="permission-type-icon">
                                            <i class="bi bi-heart-pulse"></i>
                                        </div>
                                        <div class="permission-type-content">
                                            <h6>Sakit</h6>
                                            <p class="text-muted small">Tidak masuk karena sakit</p>
                                        </div>
                                        <div class="permission-type-check">
                                            <i class="bi bi-check-circle-fill" v-if="assignIzinType === 'sakit'"></i>
                                            <i class="bi bi-circle" v-else></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h4 v-if="assignIzinType == 'izinFull'" class="section-title">
                            <i class="bi bi-calendar-event me-2 d-flex justify-content-center align-items-center"></i>
                            Pilih Jenis
                        </h4>
                        <h4 v-else class="section-title">
                            <i class="bi bi-calendar-event me-2 d-flex justify-content-center align-items-center"></i>
                            Keterangan Jenis
                        </h4>

                        <div class="form-group">
                            <!-- Izin Tidak Masuk -->
                            <select
                                v-if="assignIzinType == 'izinFull'"
                                name=""
                                id=""
                                v-model="assignIzinType2"
                                class="form-control"
                            >
                                <option selected :value="null">Pilih Jenis Izin</option>
                                <option v-for="item in pilihanIzin" :key="item.jenis" :value="item.value">
                                    {{ item.Jenis }}
                                </option>
                            </select>
                            <!-- Sakit -->
                            <!-- <select
                                v-else-if="assignIzinType == 'sakit'"
                                name=""
                                id=""
                                v-model="assignIzinType2"
                                class="form-control"
                            >
                                <option selected :value="null">
                                    Pilih Jenis Izin
                                </option>
                                <option
                                    v-for="item in pilihanSakit"
                                    :key="item.jenis"
                                    :value="item.value"
                                >
                                    {{ item.Jenis }}
                                </option>
                            </select> -->

                            <div
                                v-else-if="assignIzinType == 'sakit'"
                                name=""
                                id=""
                                disabled
                                class="w-100 form-control"
                                style="border-radius: 10px !important; padding: 0.75rem 1rem !important"
                            >
                                <div class="d-flex justify-content-between">
                                    <span class="">Izin sakit satu hari penuh </span>
                                    <!-- <i
                                        :class="getIconCuti(dataCuti.Sisa_Cuti)"
                                    ></i> -->
                                </div>
                            </div>
                            <div
                                v-else-if="assignIzinType == 'cuti'"
                                name=""
                                id=""
                                disabled
                                class="w-100 form-control"
                                :class="getClassCuti(dataCuti.Sisa_Cuti)"
                                style="border-radius: 10px !important; padding: 0.75rem 1rem !important"
                            >
                                <div class="d-flex justify-content-between">
                                    <span class="">Sisa Cuti adalah {{ dataCuti.Sisa_Cuti }}</span>
                                    <i :class="getIconCuti(dataCuti.Sisa_Cuti)"></i>
                                </div>
                            </div>

                            <!-- non selected -->
                            <select v-tooltip="'Pilih Jenis Izin dulu'" v-else disabled class="form-control">
                                <option>Pilih Jenis Izin</option>
                            </select>
                        </div>

                        <h4 class="section-title">
                            <i class="bi bi-calendar-event me-2 d-flex justify-content-center align-items-center"></i>
                            Pilih Tanggal
                        </h4>
                        <div
                            v-if="!assignIzinType2 || !assignIzinType"
                            class="form-group d-flex cursor-pointer justify-content-start align-items-center"
                        >
                            <div class="form-control d-flex align-items-center justify-content-center cursor-pointer">
                                <div class="me-2">
                                    <i class="bi bi-calendar-week d-flex justify-content-center align-items-center"></i>
                                </div>
                                <div v-if="!AssignDateRange" class="w-100">
                                    Pilih Izin dan Jenis Izin terlebih dahulu
                                </div>
                                <div v-else class="w-100">
                                    {{ formatDateString(AssignDateRange[0]) }}
                                </div>
                            </div>
                        </div>
                        <div
                            v-else-if="assignIzinType2 && (assignIzinType2 == 'izinFull' || assignIzinType2 == 'sakit')"
                            @click="openDialog"
                            class="form-group d-flex cursor-pointer justify-content-start align-items-center"
                        >
                            <div
                                style="cursor: pointer !important"
                                class="form-control d-flex align-items-center justify-content-center cursor-pointer"
                            >
                                <div class="me-2">
                                    <i class="bi bi-calendar-week d-flex justify-content-center align-items-center"></i>
                                </div>
                                <div v-if="!AssignDateRange" class="w-100">Tekan untuk pilih tanggal</div>
                                <div v-else class="w-100">
                                    {{
                                        AssignDateRange[0] == AssignDateRange[1]
                                            ? formatDateString(AssignDateRange[0])
                                            : formatDateString(AssignDateRange[0]) +
                                              ' s.d ' +
                                              formatDateString(AssignDateRange[1])
                                    }}
                                </div>
                            </div>
                        </div>
                        <div
                            v-else-if="
                                assignIzinType2 && (assignIzinType == 'cuti' || assignIzinType2 === 'cutiHutang')
                            "
                            @click="openDialogCuti"
                            class="form-group d-flex cursor-pointer justify-content-start align-items-center"
                        >
                            <div
                                style="cursor: pointer !important"
                                class="form-control d-flex align-items-center justify-content-center cursor-pointer"
                            >
                                <div class="me-2">
                                    <i class="bi bi-calendar-week d-flex justify-content-center align-items-center"></i>
                                </div>
                                <div v-if="!AssignDateRange" class="w-100">Tekan untuk pilih tanggal Cuti</div>
                                <div v-else class="w-100">
                                    {{
                                        AssignDateRange[0] == AssignDateRange[1]
                                            ? formatDateString(AssignDateRange[0])
                                            : formatDateString(AssignDateRange[0]) +
                                              ' s.d ' +
                                              formatDateString(AssignDateRange[1])
                                    }}
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            @click="openDialogSingle"
                            class="form-group d-flex cursor-pointer justify-content-start align-items-center"
                        >
                            <div
                                style="cursor: pointer !important"
                                class="form-control d-flex align-items-center justify-content-center cursor-pointer"
                            >
                                <div class="me-2">
                                    <i class="bi bi-calendar-week d-flex justify-content-center align-items-center"></i>
                                </div>
                                <div v-if="!AssignDateRange" class="w-100">Tekan untuk pilih tanggal</div>
                                <div v-else class="w-100">
                                    {{ formatDateString(AssignDateRange[0]) }}
                                </div>
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

                        <div class="step-form-card">
                            <!-- Time Selection (for early leave/late arrival) -->
                            <div
                                class="form-field-group mb-3"
                                v-if="
                                    assignIzinType2 === 'pulangCepat' ||
                                    assignIzinType2 === 'terlambat' ||
                                    assignIzinType2 === 'sakitTibaTiba'
                                "
                            >
                                <label class="form-label fw-semibold text-dark mb-1 d-flex align-items-center gap-2">
                                    <i class="bi bi-clock-history text-primary"></i>
                                    <span>{{
                                        assignIzinType2 === 'pulangCepat' || assignIzinType2 === 'sakitTibaTiba'
                                            ? 'Waktu Pulang'
                                            : 'Waktu Datang'
                                    }}</span>
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="time-picker-custom-container mb-1">
                                    <el-time-picker
                                        v-model="AssignTime"
                                        :disabled-hours="disabledHours"
                                        :disabled-minutes="disabledMinutes"
                                        :disabled-seconds="disabledSeconds"
                                        format="HH:mm"
                                        value-format="HH:mm"
                                        placeholder="Pilih Jam & Menit"
                                        class="hcis-time-picker-input w-100"
                                        popper-class="hcis-time-picker-popper"
                                    />
                                </div>
                                <small class="text-muted d-flex align-items-center gap-1">
                                    <i class="bi bi-info-circle"></i>
                                    <span>{{
                                        assignIzinType2 === 'pulangCepat' || assignIzinType2 === 'sakitTibaTiba'
                                            ? 'Pilih waktu Anda akan pulang'
                                            : 'Pilih waktu Anda akan tiba'
                                    }}</span>
                                </small>
                            </div>

                            <!-- Description -->
                            <div class="form-field-group mb-0">
                                <label class="form-label fw-semibold text-dark mb-1 d-flex align-items-center gap-2">
                                    <i class="bi bi-chat-left-text text-primary"></i>
                                    <span>Alasan / Keterangan</span>
                                </label>
                                <textarea
                                    class="form-control hcis-textarea"
                                    rows="3"
                                    v-model="permissionReason"
                                    placeholder="Jelaskan alasan permohonan izin Anda"
                                ></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="assignStep === 3" class="step-content">
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="bi bi-paperclip me-2 d-flex justify-content-center align-items-center"></i>
                            Lampiran
                        </h4>

                        <!-- File Upload for Sick Leave -->
                        <div class="form-group" v-if="assignIzinType == 'sakit'">
                            <label v-if="AssignDateRange[0] != AssignDateRange[1]" class="form-label"
                                >Surat Keterangan Dokter (Wajib)</label
                            >
                            <label v-else class="form-label">Surat Keterangan Dokter (Optional)</label>
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
                                    v-if="!AssignFile"
                                    class="file-upload-area"
                                    @click="$refs.doctorLetterInput.click()"
                                >
                                    <label class="file-upload-label d-flex align-items-center justify-content-center">
                                        <i
                                            class="bi bi-upload me-2 d-flex align-items-center justify-content-center"
                                        ></i>
                                        <span>Unggah File</span>
                                    </label>
                                </div>
                                <div v-else class="file-display-area">
                                    <span class="file-name">{{ AssignFile.name }}</span>
                                    <button @click="removeFile" class="btn-remove-file">
                                        <i class="bi bi-x-circle-fill"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted">Format: PDF, JPG, PNG (maks. 10MB)</small>
                        </div>

                        <div class="form-group" v-else>
                            <label class="form-label">Foto Atau File Tambahan (Optional)</label>
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
                                    v-if="!AssignFile"
                                    class="file-upload-area"
                                    @click="$refs.doctorLetterInput.click()"
                                >
                                    <label class="file-upload-label d-flex align-items-center justify-content-center">
                                        <i
                                            class="bi bi-upload me-2 d-flex align-items-center justify-content-center"
                                        ></i>
                                        <span>Unggah File</span>
                                    </label>
                                </div>
                                <div v-else class="file-display-area">
                                    <span class="file-name">{{ AssignFile.name }}</span>
                                    <button @click="removeFile" class="btn-remove-file">
                                        <i class="bi bi-x-circle-fill"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted">Format: PDF, JPG, PNG (maks. 10MB)</small>
                        </div>
                        <!-- <div v-else class="text-muted text-center py-4">
                            Tidak ada lampiran yang diperlukan untuk jenis izin
                            ini.
                        </div> -->
                    </div>
                </div>
                <div v-if="assignStep === 4" class="step-content">
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="bi bi-gear me-2 d-flex justify-content-center align-items-center"></i>
                            Ringkasan
                        </h4>

                        <div class="form-group AssignRingkasan p-3">
                            <div
                                style="border-bottom: 1px solid #e2e8f0"
                                class="d-flex justify-content-between pb-2 align-items-center mb-4"
                            >
                                <span class="d-flex align-items-center fs-6">
                                    <i
                                        class="bi bi-code-square me-2 fw-bold d-flex justify-content-center align-items-center"
                                    ></i>
                                    <span class="m-0 fw-medium"> Jenis Izin</span>
                                </span>
                                <div style="color: #6366f1" class="fw-bold">
                                    {{ parseIzin(assignIzinType2) }}
                                </div>
                            </div>
                            <div
                                style="border-bottom: 1px solid #e2e8f0"
                                class="d-flex justify-content-between pb-2 align-items-center mb-4"
                            >
                                <span class="d-flex align-items-center fs-6">
                                    <i
                                        class="bi bi-calendar me-2 fw-bold d-flex justify-content-center align-items-center"
                                    ></i>
                                    <span class="m-0 fw-medium"> Tanggal</span>
                                </span>
                                <div style="color: #6366f1" class="fw-bold">
                                    {{
                                        formatTanggalIzin2(
                                            assignIzinType2,
                                            AssignDateRange[0],
                                            AssignDateRange[1],
                                            formatToHHMM(AssignTime),
                                        )
                                    }}
                                </div>
                            </div>
                            <div
                                style="border-bottom: 1px solid #e2e8f0"
                                class="d-flex justify-content-between pb-2 align-items-center mb-4"
                            >
                                <span class="d-flex align-items-center fs-6">
                                    <i
                                        class="bi bi-blockquote-right me-2 fw-bold d-flex justify-content-center align-items-center"
                                    ></i>
                                    <span class="m-0 fw-medium"> Alasan</span>
                                </span>
                                <div style="color: #6366f1" class="fw-bold">
                                    {{ truncateText(permissionReason, 20) }}
                                </div>
                            </div>
                            <div
                                v-if="
                                    assignIzinType2 === 'pulangCepat' ||
                                    assignIzinType2 === 'terlambat' ||
                                    assignIzinType2 === 'sakitTibaTiba'
                                "
                                style="border-bottom: 1px solid #e2e8f0"
                                class="d-flex justify-content-between pb-2 align-items-center mb-4"
                            >
                                <span class="d-flex align-items-center fs-6">
                                    <i
                                        class="bi bi-clock me-2 fw-bold d-flex justify-content-center align-items-center"
                                    ></i>
                                    <span class="m-0 fw-medium">Waktu</span>
                                </span>
                                <div style="color: #6366f1" class="fw-bold">
                                    {{
                                        assignIzinType2 == 'izinFull' || assignIzinType2 == 'sakit'
                                            ? 'Tidak ada'
                                            : formatToHHMM(AssignTime)
                                    }}
                                </div>
                            </div>
                            <div class="d-flex justify-content-between pb-2 align-items-center mb-1">
                                <span class="d-flex align-items-center fs-6">
                                    <i
                                        class="bi bi-textarea me-2 fw-bold d-flex justify-content-center align-items-center"
                                    ></i>
                                    <span class="m-0 fw-medium">Lampiran</span>
                                </span>
                                <div style="color: #6366f1" class="fw-bold">
                                    {{ AssignFile ? 'Ada' : 'Tidak ada' }}
                                </div>
                            </div>
                            <!-- <small class="text-muted"
                                >Format: PDF, JPG, PNG (maks. 5MB)</small
                            > -->
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <div class="footer-actions">
                    <button v-if="assignStep > 1" type="button" class="izin-btn-modern btn-secondary modal-btn" @click="prevAssignStep">
                        <i class="bi bi-arrow-left me-1"></i>
                        Kembali
                    </button>

                    <button type="button" class="izin-btn-modern btn-secondary modal-btn" @click="closeAssignModal">
                        Batal
                    </button>

                    <button
                        v-if="assignStep < 4"
                        type="button"
                        class="izin-btn-modern btn-primary modal-btn"
                        @click="nextAssignStep"
                        :disabled="!canProceedToNextStep"
                    >
                        <span>Lanjut</span>
                        <i
                            v-if="!isLoadingAssign"
                            class="bi bi-arrow-right ms-1"
                        ></i>
                        <span v-if="isLoadingAssign" class="spinner-border spinner-border-sm ms-1" role="status"></span>
                    </button>

                    <button
                        v-if="assignStep === 4"
                        type="button"
                        :disabled="loadingSimpan"
                        class="izin-btn-modern btn-success modal-btn"
                        @click="submitData"
                    >
                        <i
                            v-if="!loadingSimpan"
                            class="bi bi-check-lg me-1"
                        ></i>
                        <span v-if="loadingSimpan" class="spinner-border spinner-border-sm me-1" role="status"></span>
                        <span>{{ loadingSimpan ? 'Menyimpan...' : 'Simpan' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar Range -->
    <el-dialog
        v-model="dialogVisible"
        title="Pilih Tanggal Mulai dan Selesai"
        width="480px"
        align-center
        :lock-scroll="true"
        custom-class="calendar-premium-dialog"
    >
        <el-calendar v-model="calendarDate" :disabled-date="disablePastDates">
            <template #date-cell="{ data }">
                <el-tooltip
                    :disabled="!isSpecialDate(data.date)"
                    :content="getSpecialDateTooltip(data.date)"
                    placement="top"
                    popper-class="dayoff-calendar-tooltip"
                    :show-after="120"
                >
                    <div class="cell-date-wrapper" :class="getCellClass(data)" @click="handleDateClick(data)">
                        <div
                            class="date-cell"
                            :class="{
                                'is-disabled-custom': disablePastDates(data.date),
                            }"
                        >
                            {{ data.day.split('-')[2] }}
                        </div>
                    </div>
                </el-tooltip>
            </template>
        </el-calendar>

        <!-- Legend Indicator -->
        <div class="calendar-legend-bar">
            <div class="legend-item">
                <span class="legend-dot legend-dot-selected"></span>
                <span class="legend-text">Dipilih</span>
            </div>
            <div class="legend-item">
                <span class="legend-dot legend-dot-holiday"></span>
                <span class="legend-text">Libur / Minggu</span>
            </div>
            <div class="legend-item">
                <span class="legend-dot legend-dot-dayoff"></span>
                <span class="legend-text">Day Off</span>
            </div>
            <div class="legend-item">
                <span class="legend-dot legend-dot-leave"></span>
                <span class="legend-text">Cuti / Izin Ada</span>
            </div>
        </div>

        <template #footer>
            <div class="calendar-footer-wrap">
                <div class="calendar-selected-chip">
                    <i class="bi bi-calendar-check-fill chip-icon"></i>
                    <span v-if="!tempStartDate" class="chip-placeholder">Pilih Tanggal Mulai</span>
                    <span v-if="tempStartDate && !tempEndDate" class="chip-active">
                        {{ formatTanggalTemp(tempStartDate) }} <small class="text-muted fw-normal">(Pilih Selesai)</small>
                    </span>
                    <span v-if="tempStartDate && tempEndDate" class="chip-active">
                        {{ formatTanggalTemp(tempStartDate, tempEndDate) }}
                    </span>
                </div>
                <div class="calendar-footer-actions">
                    <button
                        type="button"
                        @click="handleCancel"
                        class="izin-btn-modern btn-secondary modal-action-btn"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="izin-btn-modern btn-primary modal-action-btn"
                        :disabled="!tempEndDate"
                        @click="confirmDate('range')"
                    >
                        <i class="bi bi-check2 me-1"></i>
                        Pilih Tanggal
                    </button>
                </div>
            </div>
        </template>
    </el-dialog>

    <!-- Calendar Range Cuti-->
    <el-dialog
        v-model="dialogVisibleCuti"
        title="Pilih Tanggal Mulai dan Selesai Cuti"
        width="480px"
        align-center
        :lock-scroll="true"
        custom-class="calendar-premium-dialog"
    >
        <el-calendar v-model="calendarDateCuti" :disabled-date="disablePastDatesCuti">
            <template #date-cell="{ data }">
                <el-tooltip
                    :disabled="!isSpecialDate(data.date)"
                    :content="getSpecialDateTooltip(data.date)"
                    placement="top"
                    popper-class="dayoff-calendar-tooltip"
                    :show-after="120"
                >
                    <div class="cell-date-wrapper" :class="getCellClassCuti(data)" @click="handleDateClick(data)">
                        <div
                            class="date-cell"
                            :class="{
                                'is-disabled-custom': disablePastDatesCuti(data.date),
                            }"
                        >
                            {{ data.day.split('-')[2] }}
                        </div>
                    </div>
                </el-tooltip>
            </template>
        </el-calendar>

        <!-- Legend Indicator -->
        <div class="calendar-legend-bar">
            <div class="legend-item">
                <span class="legend-dot legend-dot-selected"></span>
                <span class="legend-text">Dipilih</span>
            </div>
            <div class="legend-item">
                <span class="legend-dot legend-dot-holiday"></span>
                <span class="legend-text">Libur / Minggu</span>
            </div>
            <div class="legend-item">
                <span class="legend-dot legend-dot-dayoff"></span>
                <span class="legend-text">Day Off</span>
            </div>
            <div class="legend-item">
                <span class="legend-dot legend-dot-leave"></span>
                <span class="legend-text">Cuti / Izin Ada</span>
            </div>
        </div>

        <template #footer>
            <div class="calendar-footer-wrap">
                <div class="calendar-selected-chip">
                    <i class="bi bi-calendar-check-fill chip-icon"></i>
                    <span v-if="!tempStartDate" class="chip-placeholder">Pilih Tanggal Mulai</span>
                    <span v-if="tempStartDate && !tempEndDate" class="chip-active">
                        {{ formatTanggalTemp(tempStartDate) }} <small class="text-muted fw-normal">(Pilih Selesai)</small>
                    </span>
                    <span v-if="tempStartDate && tempEndDate" class="chip-active">
                        {{ formatTanggalTemp(tempStartDate, tempEndDate) }}
                    </span>
                </div>
                <div class="calendar-footer-actions">
                    <button
                        type="button"
                        @click="handleCancel"
                        class="izin-btn-modern btn-secondary modal-action-btn"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="izin-btn-modern btn-primary modal-action-btn"
                        :disabled="!tempEndDate"
                        @click="confirmDate('range')"
                    >
                        <i class="bi bi-check2 me-1"></i>
                        Pilih Tanggal
                    </button>
                </div>
            </div>
        </template>
    </el-dialog>

    <!-- Calendar Single -->
    <el-dialog
        v-model="dialogVisibleSingle"
        title="Pilih Tanggal"
        width="480px"
        align-center
        :lock-scroll="true"
        custom-class="calendar-premium-dialog"
    >
        <el-calendar v-model="calendarDate" :disabled-date="disablePastDatesSingle">
            <template #date-cell="{ data }">
                <el-tooltip
                    :disabled="!isSpecialDateSingle(data.date)"
                    :content="getSpecialDateTooltipSingle(data.date)"
                    placement="top"
                    popper-class="dayoff-calendar-tooltip"
                    :show-after="120"
                >
                    <div
                        class="cell-date-wrapper"
                        :class="getCellClassSingle(data)"
                        @click="handleDateClickSingle(data)"
                    >
                        <div
                            class="date-cell"
                            :class="{
                                'is-disabled-custom': disablePastDatesSingle(data.date),
                            }"
                        >
                            {{ data.day.split('-')[2] }}
                        </div>
                    </div>
                </el-tooltip>
            </template>
        </el-calendar>

        <!-- Legend Indicator -->
        <div class="calendar-legend-bar">
            <div class="legend-item">
                <span class="legend-dot legend-dot-selected"></span>
                <span class="legend-text">Dipilih</span>
            </div>
            <div class="legend-item">
                <span class="legend-dot legend-dot-holiday"></span>
                <span class="legend-text">Libur / Minggu</span>
            </div>
            <div class="legend-item">
                <span class="legend-dot legend-dot-dayoff"></span>
                <span class="legend-text">Day Off</span>
            </div>
            <div class="legend-item">
                <span class="legend-dot legend-dot-leave"></span>
                <span class="legend-text">Cuti / Izin Ada</span>
            </div>
        </div>

        <template #footer>
            <div class="calendar-footer-wrap">
                <div class="calendar-selected-chip">
                    <i class="bi bi-calendar-check-fill chip-icon"></i>
                    <span v-if="!tempStartDate" class="chip-placeholder">Pilih Tanggal</span>
                    <span v-else class="chip-active">
                        {{ formatTanggalTemp(tempStartDate) }}
                    </span>
                </div>
                <div class="calendar-footer-actions">
                    <button
                        type="button"
                        @click="handleCancel"
                        class="izin-btn-modern btn-secondary modal-action-btn"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="izin-btn-modern btn-primary modal-action-btn"
                        :disabled="!tempStartDate"
                        @click="confirmDate('single')"
                    >
                        <i class="bi bi-check2 me-1"></i>
                        Pilih Tanggal
                    </button>
                </div>
            </div>
        </template>
    </el-dialog>

    <!-- Calendar Filter -->
    <el-dialog v-model="dialogFilter" title="Pilih Tanggal Filter" width="480px" align-center :z-index="1048" custom-class="calendar-premium-dialog">
        <el-calendar v-model="calendarDateFilter">
            <template #date-cell="{ data }">
                <div class="cell-date-wrapper" :class="getCellClass(data)" @click="handleDateClickFilter(data)">
                    <div class="date-cell">
                        {{ data.day.split('-')[2] }}
                    </div>
                </div>
            </template>
        </el-calendar>

        <template #footer>
            <div class="dialog-footer d-flex justify-content-end gap-2">
                <button type="button" class="izin-btn-modern btn-secondary modal-action-btn" @click="handleCancelFilter">Batal</button>
                <button type="button" class="izin-btn-modern btn-primary modal-action-btn" :disabled="!tempStartDateFilter" @click="getDateFilter">
                    <i class="bi bi-check2 me-1"></i>
                    Pilih Tanggal
                </button>
            </div>
        </template>
    </el-dialog>

    <!-- calendar range Export -->
    <el-dialog
        v-model="dialogVisibleExport"
        title="Pilih Tanggal Mulai dan Selesai"
        width="480px"
        align-center
        :lock-scroll="true"
        custom-class="calendar-premium-dialog"
    >
        <el-calendar v-model="calendarDateExport">
            <template #date-cell="{ data }">
                <div class="cell-date-wrapper" :class="getCellClassExport(data)" @click="handleDateClickExport(data)">
                    <div class="date-cell">
                        {{ data.day.split('-')[2] }}
                    </div>
                </div>
            </template>
        </el-calendar>

        <!-- Legend Indicator -->
        <div class="calendar-legend-bar">
            <div class="legend-item">
                <span class="legend-dot legend-dot-selected"></span>
                <span class="legend-text">Dipilih</span>
            </div>
            <div class="legend-item">
                <span class="legend-dot legend-dot-holiday"></span>
                <span class="legend-text">Libur / Minggu</span>
            </div>
        </div>

        <template #footer>
            <div class="calendar-footer-wrap">
                <div class="calendar-selected-chip">
                    <i class="bi bi-calendar-check-fill chip-icon"></i>
                    <span v-if="!tempStartDateExport" class="chip-placeholder">Pilih Tanggal Mulai</span>
                    <span v-if="tempStartDateExport && !tempEndDateExport" class="chip-active">
                        {{ formatTanggalTemp(tempStartDateExport) }} <small class="text-muted fw-normal">(Pilih Selesai)</small>
                    </span>
                    <span v-if="tempStartDateExport && tempEndDateExport" class="chip-active">
                        {{ formatTanggalTemp(tempStartDateExport, tempEndDateExport) }}
                    </span>
                </div>
                <div class="calendar-footer-actions">
                    <button
                        type="button"
                        @click="handleCancelExport"
                        class="izin-btn-modern btn-secondary modal-action-btn"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="izin-btn-modern btn-primary modal-action-btn"
                        :disabled="!tempEndDateExport"
                        @click="showAlertModalFC('export', 'Download')"
                    >
                        <i class="bi bi-download me-1"></i>
                        Pilih Tanggal
                    </button>
                </div>
            </div>
        </template>
    </el-dialog>

    <!-- alert modal -->
    <el-dialog v-model="showAlertModal" title="Konfirmasi menyimpan data" width="400px" center :lock-scroll="true">
        <div class="delete-modal-content">
            <i class="bi bi-exclamation-triangle warning-icon"></i>
            <p>
                {{ Pesan }}
            </p>
            <p class="text-danger">Tindakan ini tidak dapat dibatalkan.</p>
        </div>
        <template #footer>
            <div class="delete-modal-footer">
                <button class="izin-btn-modern btn-secondary" @click="closeAlertModal">Batal</button>
                <button :disabled="loadingProccessData" class="izin-btn-modern btn-success" @click="fnc">
                    {{ buttonAlert }}
                </button>
            </div>
        </template>
    </el-dialog>
</template>

<script scope>
import axios from 'axios';
import {
    ElDialog,
    ElCalendar,
    ElMessage,
    ElTag,
    ElTooltip,
    ElTimePicker,
    ElSteps,
    ElStep,
    ElNotification,
    ElSlider,
} from 'element-plus';
import 'element-plus/dist/index.css';
import { differenceInDays } from 'date-fns';
import { debounce } from 'lodash';
import { escapeHtml } from '@/utils/html';

export default {
    computed: {
        isTodayOff() {
            if (!this.dataShift) return false;
            return this.dataShift?.is_day_off === true;
        },
        todayOffLabel() {
            if (!this.dataShift) return '';
            return this.dataShift?.status_hari_label || this.dataShift?.status_hari || 'OFF';
        },
    },
    components: {
        ElDialog,
        ElCalendar,
        ElMessage,
        ElSteps,
        ElStep,
        ElTimePicker,
        ElNotification,
        ElTooltip,
        ElSlider,
        ElTag,
        // DotLottieVue, // Uncomment if you are using dotlottie-vue
    },
    props: {
        dataShift: Array,
        namaKaryawan: String,
        IzinApprover: Array,
        todayTime: String,
    },
    data() {
        return {
            loadingProccessData: false,
            totalData: 0,
            tipeFilter: null,
            tanggalFilter: null,
            nextLink: null,
            prevLink: null,
            totalPages: 0,

            serverTime: null,

            Jam_Masuk: '00:00',
            Jam_Keluar: '07:00',

            dataExport: [],
            showAlertModal: false,
            Pesan: '',
            buttonAlert: '',
            headerAlert: '',
            fnc: null,

            dialogVisibleExport: false,
            selectedDateExport: [],
            tempStartDateExport: null,
            tempEndDateExport: null,
            calendarDateExport: new Date(),

            TanggalSakitIzin: [],
            TanggalCuti: [],
            TanggalPulangTerlambat: [],

            AssignJenisCuti: null,

            filterDropdown: '',
            dataCuti: { Nama: 'Undifined', Sisa_Cuti: 0, Hutang_Cuti: 0 },
            hutangTerhitung: 0,
            showCutiAlertModal: false,
            specialDates: [], //nanti ganti dengan data dari database
            dayOffDates: [],
            TanggalKonversiLemburPending: [],

            pilihanIzin: [
                // { Jenis: "Satu Hari Penuh", value: "izinFull" },
                { Jenis: 'Pulang Cepat', value: 'pulangCepat' },
                { Jenis: 'Datang Terlambat', value: 'terlambat' },
            ],
            pilihanSakit: [
                { Jenis: 'Satu Hari Penuh', value: 'sakit' },
                { Jenis: 'Setengah Hari', value: 'sakitTibaTiba' },
            ],

            dialogFilter: false,
            selectedDateFilter: null, // Ini akan menyimpan [startDate, endDate]
            tempStartDateFilter: null,
            calendarDateCuti: new Date(),
            calendarDateFilter: new Date(),

            dialogVisibleCuti: false,
            dialogVisibleSingle: false,
            calendarDate: new Date(), // Pastikan ini ada dan diinisialisasi
            tempEndDate: null,
            isLoadingAssign: false,
            activeNode: null,
            clickedElement: null,
            actionMenuPosition: { top: '0px', left: '0px' },
            loadingSimpan: false,
            loadingAttachment: false,
            showDetailModal: false,
            selectedItem: null,
            showDeleteModal: false,
            deleteItemId: null,
            showActionPopup: false,
            actionMenuPosition: { top: '0', left: '0' },
            showSearchSuggestions: false,
            searchSuggestions: [
                'Izin Sakit',
                'Izin Terlambat',
                'Pulang Cepat',
                'Tidak Masuk',
                'Cuti Tahunan',
                'Izin Pribadi',
            ],
            viewMode: 'table',
            assignStep: 1,
            loading: true,
            searchQuery: '',
            currentPage: 1,
            itemsPerPage: 8,
            currentDate: new Date(),
            showAssignModal: false,
            dayNight: 'Siang', // This would typically be dynamic based on current time
            permissionRecords: [],

            min: null,
            max: null,
            jamOptions: [],

            assignIzinType: null,
            assignIzinType2: null,
            AssignDateRange: null, // Stores confirmed date range [start_date_string, end_date_string]
            AssignTime: null, // For time selection in partial leave
            permissionReason: '',
            AssignFile: null, // To store the selected file object

            dialogVisible: false,
            dialogVisibleSingle: false,
            tempStartDate: null,
            tempEndDate: null,

            DataIzin: null,
            active: 0,
            // Marks for the time slider
            min: null,
            max: null,
            timeMarks: {
                8: '08',
                9: '09',
                10: '10',
                11: '11',
                12: '12',
                13: '13',
                14: '14',
                15: '15',
                16: '16',
            },
            windowWidth: window.innerWidth,
            approvalData: ['M.REZA: Y, RIDHO RAHMAT: T, BUDI SANTOSO: N'],
        };
    },
    computed: {
        dataDiproses() {
            let dataAll = this.permissionRecords.map((emp) => ({ ...emp }));
            let dataRaw = dataAll.filter((e) => {
                return e.status == 'Diproses';
            });

            return dataRaw.length ?? 0;
        },
        parsedData() {
            const allApprovals = this.approvalData.join(', ').split(', ');
            return allApprovals.map((item) => {
                const [name, status] = item.split(': ');
                return { name: name.trim(), status: status.trim() };
            });
        },
        tooltipContent() {
            // console.log(this.parsedData);

            const totalApprovals = this.parsedData.length;
            const approvedCount = this.parsedData.filter((item) => item.status === 'Y' || item.status === 'T').length;
            const rejectedCount = this.parsedData.filter((item) => item.status === 'T').length;
            const progressCount = this.parsedData.filter((item) => item.status === 'N').length;
            const approvalRate = Math.round((approvedCount / totalApprovals) * 100);

            // Find active step
            const firstRejected = this.parsedData.findIndex((item) => item.status === 'T');
            const firstPending = this.parsedData.findIndex((item) => item.status === 'N');
            const activeIndex = firstRejected >= 0 ? firstRejected : firstPending >= 0 ? firstPending : -1;

            return `
    <div class="approval-tooltip">
        <div class="tooltip-header">
            <span class="header-title">Progress Persetujuan</span>
            <span class="approval-rate">
                <span class="rate-value">${approvalRate}%</span>
                <span class="rate-label">Complete</span>
            </span>
        </div>

        <div class="approval-steps">
            ${this.parsedData
                .map((item, index) => {
                    const isActive = index === activeIndex;
                    const isApproved = item.status === 'Y';
                    const isRejected = item.status === 'T';
                    const isPending = item.status === 'N';

                    return `
                    <div class="approval-step ${isActive ? 'active-step' : ''}">
                        <div class="step-icon ${
                            isApproved ? 'approved' : isRejected ? 'rejected' : isPending ? 'pending' : 'waiting'
                        }">
                            ${isApproved ? '✓' : isRejected ? '✕' : isPending ? '⏳' : '?'}
                        </div>
                        <div class="step-details">
                            <div class="step-name">${escapeHtml(item.name)}</div>
                            <div class="step-status ${
                                isApproved ? 'approved' : isRejected ? 'rejected' : isPending ? 'pending' : 'waiting'
                            }">
                                ${
                                    isApproved
                                        ? 'Disetujui'
                                        : isRejected
                                          ? 'Ditolak'
                                          : isPending
                                            ? 'Dalam Proses'
                                            : 'Menunggu'
                                }
                            </div>
                        </div>
                        ${isActive ? '<div class="step-indicator"></div>' : ''}
                    </div>
                `;
                })
                .join('')}
        </div>

        <div class="approval-summary">
            <div class="summary-item approved">
                <div class="summary-count">${approvedCount}</div>
                <div class="summary-label">Disetujui</div>
            </div>
            <div class="summary-item pending">
                <div class="summary-count">${progressCount}</div>
                <div class="summary-label">Proses</div>
            </div>
            <div class="summary-item rejected">
                <div class="summary-count">${rejectedCount}</div>
                <div class="summary-label">Ditolak</div>
            </div>
        </div>
    </div>

    <style scoped>
        .approval-tooltip {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 320px;
            padding: 0;
            border-radius: 8px;
            background: white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid var(--hcis-slate-200);
        }

        .tooltip-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: var(--hcis-slate-50);
            border-bottom: 1px solid var(--hcis-slate-200);
            border-radius: 8px 8px 0 0;
        }

        .header-title {
            font-weight: 600;
            font-size: 14px;
            color: var(--hcis-slate-800);
        }

        .approval-rate {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .rate-value {
            font-weight: 700;
            font-size: 16px;
            color: var(--hcis-blue-500);
        }

        .rate-label {
            font-size: 11px;
            color: var(--hcis-slate-500);
            margin-top: 2px;
        }

        .approval-steps {
            padding: 12px 16px;
            max-height: 220px;
            overflow-y: auto;
        }

        .approval-step {
            display: flex;
            align-items: center;
            padding: 10px 8px;
            margin-bottom: 6px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .active-step {
            background: var(--hcis-slate-100);
            box-shadow: 0 0 0 1px var(--hcis-slate-200);
        }

        .step-icon {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 12px;
            font-weight: 600;
            flex-shrink: 0;
        }

        .approved {
            background: linear-gradient(135deg, var(--hcis-emerald-400) 0%, #059669ce 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(167, 139, 250, 0.3);
        }

        .rejected {
            background: linear-gradient(135deg, #f472b5 0%, #ec489ad3 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(244, 114, 182, 0.3);
        }

        .pending {
            background: linear-gradient(135deg, var(--hcis-indigo-400) 0%, var(--hcis-indigo-500) 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(129, 140, 248, 0.3);
        }

        .waiting {
            background: linear-gradient(135deg, var(--hcis-indigo-400) 0%, #6365f1ce 100%);
            color: var(--hcis-slate-500);
        }

        .step-details {
            flex: 1;
            min-width: 0;
        }

        .step-name {
            font-weight: 500;
            font-size: 13px;
            color: var(--hcis-slate-800);
            margin-bottom: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .step-status {
            font-size: 11px;
            font-weight: 500;
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        .step-status.approved {
            color: white;
             background: linear-gradient(135deg, var(--hcis-emerald-300) 0%, #10b981ce 100%);
        }

        .step-status.rejected {
            color: white;
                       background: linear-gradient(135deg, #f472b5 0%, #ec489ad3 100%);

        }

        .step-status.pending {
            color: var(--hcis-indigo-800);
            background: linear-gradient(135deg, var(--hcis-indigo-100) 0%, var(--hcis-indigo-200) 100%);
        }

        .step-status.waiting {
            color: var(--hcis-slate-600);
             background: linear-gradient(135deg, var(--hcis-indigo-400) 0%, #6365f1ce 100%);
        }

        .step-indicator {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--hcis-blue-500);
            margin-left: 8px;
            animation: pulse 1.5s infinite;
            flex-shrink: 0;
        }

        .approval-summary {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            padding: 12px 16px;
            border-top: 1px solid var(--hcis-slate-200);
            background: var(--hcis-slate-50);
            border-radius: 0 0 8px 8px;
        }

        .summary-item {
            text-align: center;
            padding: 8px;
            border-radius: 6px;
            background: white;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .summary-count {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .summary-item.approved .summary-count {
            color: #8b5cf6;
        }

        .summary-item.pending .summary-count {
            color: var(--hcis-indigo-500);
        }

        .summary-item.rejected .summary-count {
            color: var(--hcis-pink-500);
        }

        .summary-label {
            font-size: 11px;
            font-weight: 500;
            color: var(--hcis-slate-500);
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.1); }
        }
    </style>
`;
        },
        //     tooltipContent() {
        //         const totalApprovals = this.parsedData.length;
        //         const approvedCount = this.parsedData.filter(
        //             (item) => item.status === "Y"
        //         ).length;
        //         const rejectedCount = this.parsedData.filter(
        //             (item) => item.status === "T"
        //         ).length;
        //         const progressCount = this.parsedData.filter(
        //             (item) => item.status === "N"
        //         ).length;
        //         const approvalRate = Math.round(
        //             (approvedCount / totalApprovals) * 100
        //         );

        //         // Find active step
        //         const firstRejected = this.parsedData.findIndex(
        //             (item) => item.status === "T"
        //         );
        //         const firstPending = this.parsedData.findIndex(
        //             (item) => item.status === "N"
        //         );
        //         const activeIndex =
        //             firstRejected >= 0
        //                 ? firstRejected
        //                 : firstPending >= 0
        //                 ? firstPending
        //                 : -1;

        //         return `
        //     <div class="approval-tooltip">
        //         <div class="tooltip-header">
        //             <span class="header-title">Progress Persetujuan</span>
        //             <span class="approval-rate">
        //                 <span class="rate-value">${approvalRate}%</span>
        //                 <span class="rate-label">Complete</span>
        //             </span>
        //         </div>

        //         <div class="approval-steps">
        //             ${this.parsedData
        //                 .map((item, index) => {
        //                     const isActive = index === activeIndex;
        //                     const isApproved = item.status === "Y";
        //                     const isRejected = item.status === "T";
        //                     const isPending = item.status === "N";

        //                     return `
        //                     <div class="approval-step ${
        //                         isActive ? "active-step" : ""
        //                     }">
        //                         <div class="step-icon ${
        //                             isApproved
        //                                 ? "approved"
        //                                 : isRejected
        //                                 ? "rejected"
        //                                 : isPending
        //                                 ? "pending"
        //                                 : "waiting"
        //                         }">
        //                             ${
        //                                 isApproved
        //                                     ? "✓"
        //                                     : isRejected
        //                                     ? "✕"
        //                                     : isPending
        //                                     ? "⏳"
        //                                     : "?"
        //                             }
        //                         </div>
        //                         <div class="step-details">
        //                             <div class="step-name">${item.name}</div>
        //                             <div class="step-status ${
        //                                 isApproved
        //                                     ? "approved"
        //                                     : isRejected
        //                                     ? "rejected"
        //                                     : isPending
        //                                     ? "pending"
        //                                     : "waiting"
        //                             }">
        //                                 ${
        //                                     isApproved
        //                                         ? "Disetujui"
        //                                         : isRejected
        //                                         ? "Ditolak"
        //                                         : isPending
        //                                         ? "Dalam Proses"
        //                                         : "Menunggu"
        //                                 }
        //                             </div>
        //                         </div>
        //                         ${
        //                             isActive
        //                                 ? '<div class="step-indicator"></div>'
        //                                 : ""
        //                         }
        //                     </div>
        //                 `;
        //                 })
        //                 .join("")}
        //         </div>

        //         <div class="approval-summary">
        //             <div class="summary-item approved">
        //                 <div class="summary-count">${approvedCount}</div>
        //                 <div class="summary-label">Disetujui</div>
        //             </div>
        //             <div class="summary-item pending">
        //                 <div class="summary-count">${progressCount}</div>
        //                 <div class="summary-label">Proses</div>
        //             </div>
        //             <div class="summary-item rejected">
        //                 <div class="summary-count">${rejectedCount}</div>
        //                 <div class="summary-label">Ditolak</div>
        //             </div>
        //         </div>
        //     </div>

        //     <style scoped>
        //         .approval-tooltip {
        //             font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        //             max-width: 320px;
        //             padding: 0;
        //             border-radius: 8px;
        //             background: white;
        //             box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        //             border: 1px solid var(--hcis-slate-200);
        //         }

        //         .tooltip-header {
        //             display: flex;
        //             justify-content: space-between;
        //             align-items: center;
        //             padding: 12px 16px;
        //             background: var(--hcis-slate-50);
        //             border-bottom: 1px solid var(--hcis-slate-200);
        //             border-radius: 8px 8px 0 0;
        //         }

        //         .header-title {
        //             font-weight: 600;
        //             font-size: 14px;
        //             color: var(--hcis-slate-800);
        //         }

        //         .approval-rate {
        //             display: flex;
        //             flex-direction: column;
        //             align-items: flex-end;
        //         }

        //         .rate-value {
        //             font-weight: 700;
        //             font-size: 16px;
        //             color: var(--hcis-blue-500);
        //         }

        //         .rate-label {
        //             font-size: 11px;
        //             color: var(--hcis-slate-500);
        //             margin-top: 2px;
        //         }

        //         .approval-steps {
        //             padding: 12px 16px;
        //             max-height: 220px;
        //             overflow-y: auto;
        //         }

        //         .approval-step {
        //             display: flex;
        //             align-items: center;
        //             padding: 10px 8px;
        //             margin-bottom: 6px;
        //             border-radius: 6px;
        //             transition: all 0.2s ease;
        //         }

        //         .active-step {
        //             background: var(--hcis-slate-100);
        //             box-shadow: 0 0 0 1px var(--hcis-slate-200);
        //         }

        //         .step-icon {
        //             width: 24px;
        //             height: 24px;
        //             border-radius: 6px;
        //             display: flex;
        //             align-items: center;
        //             justify-content: center;
        //             margin-right: 12px;
        //             font-size: 12px;
        //             font-weight: 600;
        //             flex-shrink: 0;
        //         }

        //         .approved {
        //             background: var(--hcis-emerald-500);
        //             color: white;
        //             box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
        //         }

        //         .rejected {
        //             background: var(--hcis-red-500);
        //             color: white;
        //             box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);
        //         }

        //         .pending {
        //             background: var(--hcis-amber-500);
        //             color: white;
        //             box-shadow: 0 2px 4px rgba(245, 158, 11, 0.2);
        //         }

        //         .waiting {
        //             background: var(--hcis-slate-200);
        //             color: var(--hcis-slate-500);
        //         }

        //         .step-details {
        //             flex: 1;
        //             min-width: 0;
        //         }

        //         .step-name {
        //             font-weight: 500;
        //             font-size: 13px;
        //             color: var(--hcis-slate-800);
        //             margin-bottom: 4px;
        //             overflow: hidden;
        //             text-overflow: ellipsis;
        //             white-space: nowrap;
        //         }

        //         .step-status {
        //             font-size: 11px;
        //             font-weight: 500;
        //             padding: 3px 8px;
        //             border-radius: 4px;
        //             display: inline-block;
        //         }

        //         .step-status.approved {
        //             color: var(--hcis-emerald-800);
        //             background: var(--hcis-emerald-100);
        //         }

        //         .step-status.rejected {
        //             color: var(--hcis-red-800);
        //             background: var(--hcis-red-100);
        //         }

        //         .step-status.pending {
        //             color: var(--hcis-amber-800);
        //             background: var(--hcis-amber-100);
        //         }

        //         .step-status.waiting {
        //             color: var(--hcis-slate-600);
        //             background: var(--hcis-slate-100);
        //         }

        //         .step-indicator {
        //             width: 6px;
        //             height: 6px;
        //             border-radius: 50%;
        //             background: var(--hcis-blue-500);
        //             margin-left: 8px;
        //             animation: pulse 1.5s infinite;
        //             flex-shrink: 0;
        //         }

        //         .approval-summary {
        //             display: grid;
        //             grid-template-columns: 1fr 1fr 1fr;
        //             gap: 8px;
        //             padding: 12px 16px;
        //             border-top: 1px solid var(--hcis-slate-200);
        //             background: var(--hcis-slate-50);
        //             border-radius: 0 0 8px 8px;
        //         }

        //         .summary-item {
        //             text-align: center;
        //             padding: 8px;
        //             border-radius: 6px;
        //             background: white;
        //             box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        //         }

        //         .summary-count {
        //             font-size: 16px;
        //             font-weight: 700;
        //             margin-bottom: 2px;
        //         }

        //         .summary-item.approved .summary-count {
        //             color: var(--hcis-emerald-500);
        //         }

        //         .summary-item.pending .summary-count {
        //             color: var(--hcis-amber-500);
        //         }

        //         .summary-item.rejected .summary-count {
        //             color: var(--hcis-red-500);
        //         }

        //         .summary-label {
        //             font-size: 11px;
        //             font-weight: 500;
        //             color: var(--hcis-slate-500);
        //         }

        //         @keyframes pulse {
        //             0%, 100% { opacity: 1; transform: scale(1); }
        //             50% { opacity: 0.6; transform: scale(1.1); }
        //         }
        //     </style>
        // `;
        //     },
        // filteredSuggestions() {
        //     if (!this.searchQuery) {
        //         return this.searchSuggestions;
        //     }
        //     return this.searchSuggestions.filter((suggestion) =>
        //         suggestion
        //             .toLowerCase()
        //             .includes(this.searchQuery.toLowerCase())
        //     );
        // },
        // permissionRecords() {
        //     let filtered = this.permissionRecords.map((emp) => ({ ...emp }));
        //     if (this.searchQuery) {
        //         const query = this.searchQuery.toLowerCase();
        //         filtered = filtered.filter(
        //             (item) =>
        //                 item.No_Transaksi.toLowerCase().includes(query) ||
        //                 this.parseIzin(item.Tipe_Izin)
        //                     .toLowerCase()
        //                     .includes(query) ||
        //                 item.Alasan.toLowerCase().includes(query) ||
        //                 this.maknaStatus(item.Status)
        //                     .toLowerCase()
        //                     .includes(query)
        //         );
        //     }
        //     if (this.filterDropdown) {
        //         const query = this.filterDropdown.toLowerCase();
        //         filtered = filtered.filter(
        //             (item) =>
        //                 item.No_Transaksi.toLowerCase().includes(query) ||
        //                 this.parseIzin(item.Tipe_Izin)
        //                     .toLowerCase()
        //                     .includes(query) ||
        //                 item.Alasan.toLowerCase().includes(query) ||
        //                 this.maknaStatus(item.Status)
        //                     .toLowerCase()
        //                     .includes(query)
        //         );
        //     }

        //     if (this.selectedDateFilter) {
        //         filtered = filtered.filter((item) => {
        //             const selectedDate = new Date(this.selectedDateFilter);
        //             selectedDate.setHours(0, 0, 0, 0);

        //             const startDate = new Date(item.Tanggal_Mulai);
        //             startDate.setHours(0, 0, 0, 0);

        //             const endDate = item.Tanggal_Selesai
        //                 ? new Date(item.Tanggal_Selesai)
        //                 : null;
        //             if (endDate) endDate.setHours(0, 0, 0, 0);

        //             return endDate
        //                 ? selectedDate >= startDate && selectedDate <= endDate
        //                 : selectedDate.getTime() === startDate.getTime();
        //         });
        //     }

        //     return filtered;
        // },
        // totalPages() {
        //     return Math.ceil(
        //         this.permissionRecords.length / this.itemsPerPage
        //     );
        // },
        // paginatedPermissions() {
        //     const start = (this.currentPage - 1) * this.itemsPerPage;
        //     const end = start + this.itemsPerPage;
        //     return this.permissionRecords.slice(start, end);
        // },
        // visiblePages() {
        //     const maxVisible = 5; // Maximum number of visible page buttons
        //     const half = Math.floor(maxVisible / 2);
        //     let start = Math.max(1, this.currentPage - half);
        //     let end = Math.min(this.totalPages, start + maxVisible - 1);

        //     // Adjust start if we are near the end
        //     if (end - start + 1 < maxVisible) {
        //         start = Math.max(1, end - maxVisible + 1);
        //     }

        //     return Array.from({ length: end - start + 1 }, (_, i) => start + i);
        // },

        assignSelectedShift() {
            // This is a placeholder. In a real app, you'd check if all required fields are filled.
            return this.assignIzinType && this.AssignDateRange;
        },
    },
    watch: {
        searchQuery() {
            this.debouncedSearch();
        },
        filterDropdown() {
            this.debouncedSearch();
        },
        selectedDateFilter() {
            this.debouncedSearch();
        },
    },
    methods: {
        blockAutoSelect(e) {
            if (!this.selectedDateFilter) {
                e.target.value = ''; // kosongkan supaya tidak auto ke 2
            }
        },
        formatToHHMM(assignTime) {
            if (!assignTime) return '';

            if (typeof assignTime === 'string') {
                const match = assignTime.trim().match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
                if (match) {
                    return `${match[1].padStart(2, '0')}:${match[2]}`;
                }
            }

            const date = new Date(assignTime);
            if (!isNaN(date.getTime())) {
                const hours = date.getHours().toString().padStart(2, '0');
                const minutes = date.getMinutes().toString().padStart(2, '0');
                return `${hours}:${minutes}`;
            }

            return String(assignTime);
        },
        async getJamKerja() {
            try {
                const response = await axios.get('/izin/getKerja', {
                    params: this.AssignDateRange,
                });
                if (response.status === 200) {
                    const data = response.data.data;
                    this.Jam_Masuk = data.Jam_Masuk;
                    this.Jam_Keluar = data.Jam_Keluar;
                    this.value1 = data.Jam_Masuk;
                }
            } catch (error) {
                console.log(error);
            }
        },

        disabledHours() {
            if (!this.Jam_Masuk || !this.Jam_Keluar) return [];
            const [jamMasuk] = this.Jam_Masuk.split(':').map(Number);
            const [jamKeluar] = this.Jam_Keluar.split(':').map(Number);

            const disabled = [];

            if (jamMasuk <= jamKeluar) {
                // Normal shift (contoh: 08:00 - 16:00)
                for (let h = 0; h < 24; h++) {
                    if (h < jamMasuk || h > jamKeluar) disabled.push(h);
                }
            } else {
                // Shift lintas hari (contoh: 23:00 - 07:00)
                for (let h = jamKeluar + 1; h < jamMasuk; h++) {
                    disabled.push(h);
                }
            }

            return disabled;
        },

        disabledMinutes(hour) {
            if (!this.Jam_Masuk || !this.Jam_Keluar) return [];
            const [jamMasuk, menitMasuk] = this.Jam_Masuk.split(':').map(Number);
            const [jamKeluar, menitKeluar] = this.Jam_Keluar.split(':').map(Number);

            const minutes = [];

            if (jamMasuk <= jamKeluar) {
                // Normal shift
                if (hour === jamMasuk) {
                    for (let m = 0; m < menitMasuk; m++) minutes.push(m);
                }
                if (hour === jamKeluar) {
                    for (let m = menitKeluar + 1; m < 60; m++) minutes.push(m);
                }
            } else {
                // Lintas hari
                if (hour === jamMasuk) {
                    for (let m = 0; m < menitMasuk; m++) minutes.push(m);
                }
                if (hour === jamKeluar) {
                    for (let m = menitKeluar + 1; m < 60; m++) minutes.push(m);
                }
            }

            return minutes;
        },

        disabledSeconds() {
            return Array.from({ length: 60 }, (_, i) => i); // disable semua detik
        },
        openDialogExport() {
            if (this.selectedDateExport.length === 2) {
                const [startStr, endStr] = this.selectedDateExport;
                this.tempStartDateExport = new Date(startStr + 'T00:00:00');
                this.tempEndDateExport = new Date(endStr + 'T00:00:00');
                this.calendarDateExport = new Date(startStr + 'T00:00:00');
            } else {
                this.tempStartDateExport = null;
                this.tempEndDateExport = null;
                this.calendarDateExport = new Date();
            }
            this.dialogVisibleExport = true;
        },
        async getExportData() {
            try {
                this.loading = true;
                const response = await axios.get('/izin/getExport', {
                    params: {
                        start: this.tempStartDateExport,
                        end: this.tempEndDateExport,
                    },
                });

                if (response.status == 200) {
                    this.dataExport = response.data.data;
                    this.exportAbsensiExcel();
                    // console.log(response.data.data);
                } else {
                    // console.log(response);
                    console.log('Terjadi Error saat mengambil data!');
                }
            } catch (error) {
                console.log(`Terjadi Error di : ${error}`);
                ElMessage({
                    showClose: true,
                    message: 'Terjadi Kesalahan, Silahkan Coba lagi beberapa saat',
                    type: 'error',
                    customStyle: {
                        'z-index': '1050',
                    },
                });
                this.closeAlertModal();
            } finally {
                this.closeAlertModal();
                this.handleCancelExport();
                this.loading = false;
            }
        },
        async exportAbsensiExcel() {
            const ExcelJS = await import('exceljs');
            const workbook = new ExcelJS.Workbook();
            const worksheet = workbook.addWorksheet('Absensi Export');

            try {
                const headerStyle = {
                    fill: {
                        type: 'pattern',
                        pattern: 'solid',
                        fgColor: { argb: 'ffeb9c' },
                    },
                    font: { bold: true },
                    alignment: { vertical: 'middle', horizontal: 'center' },
                    border: {
                        top: { style: 'thin' },
                        left: { style: 'thin' },
                        bottom: { style: 'thin' },
                        right: { style: 'thin' },
                    },
                };

                const cellStyle = {
                    border: {
                        top: { style: 'thin' },
                        left: { style: 'thin' },
                        bottom: { style: 'thin' },
                        right: { style: 'thin' },
                    },
                    alignment: { vertical: 'middle', horizontal: 'left' },
                };

                const centeredCellStyle = {
                    ...cellStyle,
                    alignment: { vertical: 'middle', horizontal: 'center' },
                };

                // helper untuk mapping tipe izin
                const parseIzin = (tipe) => {
                    if (tipe === 'izinFull') return 'Izin Pribadi';
                    else if (tipe === 'sakit') return 'Izin Sakit';
                    else if (tipe === 'pulangCepat') return 'Pulang Cepat';
                    else if (tipe === 'terlambat') return 'Izin Terlambat';
                    else if (tipe === 'cuti' || tipe === 'CUTI' || tipe === 'cutiHutang') return 'Cuti';
                    return tipe;
                };

                // Judul
                worksheet.mergeCells('B1:I1');
                worksheet.getCell('B1').value = `Pengajuan Izin ${this.capitalize(this.namaKaryawan)}`;
                worksheet.getCell('B1').style = headerStyle;

                worksheet.getCell('A1').value = 'Tanggal';
                worksheet.mergeCells('A1:A2');
                worksheet.getCell('A1').style = headerStyle;

                // Header kolom
                const headers = [
                    'No. Transaksi',
                    'Nama Karyawan',
                    'Jenis Izin',
                    'Jam',
                    'Tanggal Mulai',
                    'Tanggal Selesai',
                    'Alasan',
                    'Status',
                ];
                const headerRow = worksheet.getRow(2);
                headerRow.values = [null, ...headers];
                headerRow.eachCell((cell, colNumber) => {
                    if (colNumber > 0) cell.style = headerStyle;
                });

                worksheet.columns = [
                    { key: 'tanggal', width: 20 },
                    { key: 'noTrans', width: 20 },
                    { key: 'nama', width: 25 },
                    { key: 'jenisIzin', width: 20 },
                    { key: 'jam', width: 15 },
                    { key: 'tglMulai', width: 15 },
                    { key: 'tglSelesai', width: 15 },
                    { key: 'alasan', width: 40 },
                    { key: 'status', width: 30 },
                ];

                // Grouping
                let grouped = {};
                this.dataExport.forEach((item) => {
                    let tanggalMulai = item.Tanggal_Mulai?.split(' ')[0];
                    let tanggalSelesai = item.Tanggal_Selesai ? item.Tanggal_Selesai.split(' ')[0] : 'Tidak ada';
                    let rangeTanggal = `${tanggalMulai} s.d. ${tanggalSelesai}`;

                    if (!grouped[rangeTanggal]) grouped[rangeTanggal] = {};
                    if (!grouped[rangeTanggal][item.No_Transaksi]) grouped[rangeTanggal][item.No_Transaksi] = [];
                    grouped[rangeTanggal][item.No_Transaksi].push(item);
                });

                // Isi data
                let currentRowIndex = 3;
                Object.entries(grouped).forEach(([rangeTanggal, transaksi]) => {
                    const dateBlockStartRow = currentRowIndex;

                    Object.entries(transaksi).forEach(([noTrans, details]) => {
                        const transBlockStartRow = currentRowIndex;

                        details.forEach((user) => {
                            const row = worksheet.getRow(currentRowIndex);

                            // Status handling
                            let statusFinal = user.status;
                            if (user.status === 'Ditolak' && user.Approver === 'Sistem') {
                                statusFinal = 'Ditolak oleh Sistem (Expired)';
                            }

                            row.values = {
                                nama: user.Nama,
                                jenisIzin: parseIzin(user.Tipe_Izin),
                                jam: user.Jam_Mulai || '-',
                                tglMulai: user.Tanggal_Mulai?.split(' ')[0],
                                tglSelesai: user.Tanggal_Selesai ? user.Tanggal_Selesai.split(' ')[0] : 'Tidak ada',
                                alasan: user.Alasan || '-',
                                status: statusFinal,
                            };

                            row.eachCell((cell, colNumber) => {
                                if (colNumber > 1) cell.style = cellStyle;
                            });

                            currentRowIndex++;
                        });

                        // Merge kolom "No. Transaksi"
                        const transBlockEndRow = currentRowIndex - 1;
                        if (transBlockStartRow <= transBlockEndRow) {
                            worksheet.mergeCells(`B${transBlockStartRow}:B${transBlockEndRow}`);
                            const cellB = worksheet.getCell(`B${transBlockStartRow}`);
                            cellB.value = noTrans;
                            cellB.style = centeredCellStyle;
                        }
                    });

                    // Merge kolom "Tanggal"
                    const dateBlockEndRow = currentRowIndex - 1;
                    if (dateBlockStartRow <= dateBlockEndRow) {
                        worksheet.mergeCells(`A${dateBlockStartRow}:A${dateBlockEndRow}`);
                        const cellA = worksheet.getCell(`A${dateBlockStartRow}`);
                        cellA.value = rangeTanggal;
                        cellA.style = centeredCellStyle;
                    }
                });

                // Download
                const buffer = await workbook.xlsx.writeBuffer();
                const blob = new Blob([buffer], {
                    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                });

                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `Export_Izin_${new Date().toISOString().slice(0, 10)}.xlsx`;
                link.click();
                URL.revokeObjectURL(link.href);
            } catch (error) {
                console.error(`Terjadi Error di : ${error}`);
            } finally {
                this.loading = false;
            }
        },
        showAlertModalFC(jenis, button) {
            if (jenis == 'export') {
                this.Pesan = `Anda yakin ingin mendownload data izin ${this.formatTanggalTemp(
                    this.tempStartDateExport,
                    this.tempEndDateExport,
                )} `;
                this.buttonAlert = button;
                this.headerAlert = 'Download Export data!';
                this.fnc = () => this.getExportData();
            }
            this.showAlertModal = true;
        },
        closeAlertModal() {
            this.Pesan = null;
            this.fnc = null;
            this.showAlertModal = false;
        },
        handleDateClickExport(data) {
            const clickedDate = data.date;
            if (this.tempStartDateExport && this.tempEndDateExport) {
                this.tempStartDateExport = clickedDate;
                this.tempEndDateExport = null;
            } else if (!this.tempStartDateExport) {
                this.tempStartDateExport = clickedDate;
            } else {
                if (clickedDate.getTime() < this.tempStartDateExport.getTime()) {
                    this.tempStartDateExport = clickedDate;
                } else {
                    this.tempEndDateExport = clickedDate;
                }
            }
        },
        getCellClassExport(data) {
            const classes = {};
            const dateObj = new Date(data.date);
            const formattedDate = this.formatDateToYYYYMMDD(dateObj);
            const checkDate = new Date(formattedDate);
            checkDate.setHours(0, 0, 0, 0);

            // Logika untuk rentang tanggal yang dipilih
            const start = this.tempStartDateExport ? new Date(this.tempStartDateExport).setHours(0, 0, 0, 0) : null;
            const end = this.tempEndDateExport ? new Date(this.tempEndDateExport).setHours(0, 0, 0, 0) : null;

            const isRange = start && end && start !== end;
            const isSingle = (start && !end) || (start && end && start === end);

            if (isSingle && start && checkDate.getTime() === start) {
                classes['is-selected-single'] = true;
            }

            if (isRange) {
                if (start && checkDate.getTime() === start) classes['is-start'] = true;
                if (end && checkDate.getTime() === end) classes['is-end'] = true;
                if (checkDate.getTime() > start && checkDate.getTime() < end) {
                    classes['is-in-range'] = true;
                }
            }

            // Logika untuk hari Minggu (Weekend)
            const dayOfWeek = dateObj.getDay(); // 0 = Minggu
            if (dayOfWeek === 0) {
                classes['is-red-date'] = true;
            }

            return classes;
        },
        handleCancelExport() {
            this.dialogVisibleExport = false;
            this.selectedDateExport = [];
            this.tempStartDateExport = null;
            this.tempEndDateExport = null;
        },
        formatTanggalTemp(dateStart, dateEnd) {
            const bulan = [
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

            if (!dateStart) return '';

            const start = new Date(dateStart);
            if (isNaN(start.getTime())) return '';

            const end = dateEnd ? new Date(dateEnd) : null;

            // Jika hanya satu tanggal (klik 1 kali atau start == end)
            if (!end || isNaN(end.getTime()) || start.getTime() === end.getTime()) {
                return `${start.getDate()} ${bulan[start.getMonth()]} ${start.getFullYear()}`;
            }

            // Hitung durasi hari inklusif
            const diffTime = Math.abs(end.getTime() - start.getTime());
            const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24)) + 1;

            // Jika bulan dan tahun sama
            if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
                return `${start.getDate()} - ${end.getDate()} ${bulan[start.getMonth()]} ${start.getFullYear()} (${diffDays} Hari)`;
            }

            // Jika tahun sama tapi beda bulan
            if (start.getFullYear() === end.getFullYear()) {
                return `${start.getDate()} ${bulan[start.getMonth()]} - ${end.getDate()} ${bulan[end.getMonth()]} ${start.getFullYear()} (${diffDays} Hari)`;
            }

            // Jika bulan atau tahun berbeda
            return `${start.getDate()} ${bulan[start.getMonth()]} ${start.getFullYear()} - ${end.getDate()} ${
                bulan[end.getMonth()]
            } ${end.getFullYear()} (${diffDays} Hari)`;
        },
        checkScreenSize() {
            if (window.innerWidth >= 768) {
                this.viewMode = 'table';
            } else {
                this.viewMode = 'card';
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
        getCutiDateRange(cuti) {
            const actualDate = cuti?.Tanggal_Cuti_Aktual || cuti?.Tanggal_Cuti || cuti?.Tanggal;
            const startDate = actualDate || cuti?.Tanggal_Cuti_Dari;
            const endDate = actualDate || cuti?.Tanggal_Cuti_Sampai || cuti?.Tanggal_Cuti_Dari;

            const start = startDate ? new Date(startDate) : null;
            const end = endDate ? new Date(endDate) : null;

            if (start) {
                start.setHours(0, 0, 0, 0);
            }

            if (end) {
                end.setHours(0, 0, 0, 0);
            }

            return { start, end };
        },
        normalizeDateOnly(date) {
            const normalized = new Date(date);
            normalized.setHours(0, 0, 0, 0);
            return normalized;
        },
        findDayOffEntry(date) {
            const formattedDate = this.formatDateToYYYYMMDD(date);
            return (this.dayOffDates || []).find((item) => {
                if (typeof item === 'string') {
                    return item === formattedDate;
                }

                return item?.date === formattedDate;
            });
        },
        isDayOffDate(date) {
            return !!this.findDayOffEntry(date);
        },
        findKonversiPendingEntry(date) {
            const formattedDate = this.formatDateToYYYYMMDD(date);
            return (this.TanggalKonversiLemburPending || []).find((item) => {
                if (typeof item === 'string') {
                    return item === formattedDate;
                }

                return item?.date === formattedDate;
            });
        },
        isKonversiPendingDate(date) {
            return !!this.findKonversiPendingEntry(date);
        },
        getKonversiPendingTooltip(date) {
            const pending = this.findKonversiPendingEntry(date);
            if (!pending) {
                return '';
            }

            return (typeof pending === 'object' && pending?.tooltip) || 'Konversi Lembur Day off';
        },
        formatDayOffSuffix(text) {
            return String(text || '')
                .replace(/[_-]+/g, ' ')
                .split(' ')
                .filter(Boolean)
                .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
                .join(' ');
        },
        getDayOffDisplayLabel(date) {
            const dayOff = this.findDayOffEntry(date);
            if (!dayOff) {
                return '';
            }

            const formattedDate = this.formatDateToYYYYMMDD(date);
            const holidayInfo = this.specialDates.find((sd) => sd.date === formattedDate);
            const holidayName = holidayInfo?.description || '';

            const rawStatus = typeof dayOff === 'string' ? 'OFF' : String(dayOff.status_hari || 'OFF');
            const normalizedStatus = rawStatus.replace(/_/g, '-');

            if (/^OFF$/i.test(normalizedStatus)) {
                return 'Day Off';
            }

            const suffix = normalizedStatus.replace(/^OFF[-\s]*/i, '').trim();
            const normalizedSuffix = suffix.toLowerCase().replace(/[_\s]+/g, '-');

            if (normalizedSuffix === 'hari-libur' || normalizedSuffix === 'hari libur') {
                return holidayName ? `Hari Libur - ${holidayName}` : 'Hari Libur';
            }

            const prettySuffix = this.formatDayOffSuffix(suffix);
            return prettySuffix ? `Day Off - ${prettySuffix}` : 'Day Off';
        },
        getDayOffTooltip(date) {
            const dayOff = this.findDayOffEntry(date);
            if (!dayOff) {
                return '';
            }

            const displayLabel = this.getDayOffDisplayLabel(date);
            return `${displayLabel}. Jika tetap perlu izin, ajukan perubahan ke WORK ke atasan.`;
        },
        isDateInCuti(checkDate, cuti) {
            const { start, end } = this.getCutiDateRange(cuti);
            if (!start || !end) {
                return false;
            }

            return checkDate >= start && checkDate <= end;
        },
        getTerlambatPulangDate(item) {
            return item?.Tanggal_Aktual_Izin || item?.Tanggal_Hari_Kerja || item?.Tanggal_Masuk_Pulang;
        },
        isSpecialDate(date) {
            const formattedDate = this.formatDateToYYYYMMDD(date);
            const dateObj = new Date(date);
            const checkDate = new Date(formattedDate);
            checkDate.setHours(0, 0, 0, 0);

            // Hari Minggu TIDAK otomatis libur: karyawan shift bisa masuk di hari
            // Minggu. Minggu yang memang libur sudah ada di dayOffDates (fungsi
            // status hari server), sama dengan pemeriksaan isOffDay() saat submit.
            return (
                this.isDayOffDate(checkDate) ||
                this.specialDates.some((sd) => sd.date === formattedDate) ||
                this.TanggalPulangTerlambat.some((item) => {
                    const start = new Date(this.getTerlambatPulangDate(item));
                    start.setHours(0, 0, 0, 0);
                    return checkDate.getTime() === start.getTime();
                }) ||
                this.TanggalSakitIzin.some((item) => {
                    const start = new Date(item.Tanggal_Sakit_Izin_Dari);
                    const end = new Date(item.Tanggal_Sakit_Izin_Sampai);
                    start.setHours(0, 0, 0, 0);
                    end.setHours(0, 0, 0, 0);
                    return checkDate >= start && checkDate <= end;
                }) ||
                this.TanggalCuti.some((cuti) => this.isDateInCuti(checkDate, cuti)) ||
                this.isKonversiPendingDate(checkDate)
            );
        },
        isSpecialDateSingle(date) {
            const formattedDate = this.formatDateToYYYYMMDD(date);
            const dateObj = new Date(date);
            const checkDate = new Date(formattedDate);
            checkDate.setHours(0, 0, 0, 0);

            const isSpecial = this.specialDates.some((sd) => sd.date === formattedDate);
            const isDayOff = this.isDayOffDate(checkDate);

            let isTerlambat = false;
            let isPulangTerlambat = false;

            if (this.assignIzinType2 === 'terlambat') {
                isTerlambat = this.TanggalPulangTerlambat.some((item) => {
                    const start = new Date(this.getTerlambatPulangDate(item));
                    start.setHours(0, 0, 0, 0);
                    return checkDate.getTime() === start.getTime() && item.Jenis === 'terlambat';
                });
            } else if (this.assignIzinType2 === 'pulangCepat') {
                isPulangTerlambat = this.TanggalPulangTerlambat.some((item) => {
                    const start = new Date(this.getTerlambatPulangDate(item));
                    start.setHours(0, 0, 0, 0);
                    return checkDate.getTime() === start.getTime() && item.Jenis === 'pulangCepat';
                });
            }

            const isSakitIzin = this.TanggalSakitIzin.some((item) => {
                const start = new Date(item.Tanggal_Sakit_Izin_Dari);
                const end = new Date(item.Tanggal_Sakit_Izin_Sampai);
                start.setHours(0, 0, 0, 0);
                end.setHours(0, 0, 0, 0);
                return checkDate >= start && checkDate <= end;
            });

            const isCuti = this.TanggalCuti.some((cuti) => this.isDateInCuti(checkDate, cuti));
            const isKonversiPending = this.isKonversiPendingDate(checkDate);

            return isSpecial || isDayOff || isTerlambat || isPulangTerlambat || isSakitIzin || isCuti || isKonversiPending;
        },
        getSpecialDateTooltip(date) {
            const formattedDate = this.formatDateToYYYYMMDD(date);
            const dateObj = new Date(date);
            const checkDate = new Date(formattedDate);
            checkDate.setHours(0, 0, 0, 0);

            const dayOffTooltip = this.getDayOffTooltip(checkDate);
            if (dayOffTooltip) {
                return dayOffTooltip;
            }

            const konversiPendingTooltip = this.getKonversiPendingTooltip(checkDate);
            if (konversiPendingTooltip) {
                return konversiPendingTooltip;
            }

            // 1. Prioritas: Hari libur nasional / spesial
            const specialDay = this.specialDates.find((sd) => sd.date === formattedDate);
            if (specialDay) {
                return specialDay.description;
            }

            // 2. Prioritas: Terlambat / Pulang Cepat (single day check)
            const isTerlambatPulang = this.TanggalPulangTerlambat.find((item) => {
                const start = new Date(this.getTerlambatPulangDate(item));
                start.setHours(0, 0, 0, 0);
                return checkDate.getTime() === start.getTime();
            });
            if (isTerlambatPulang) {
                return `${this.parseIzin(isTerlambatPulang.Jenis)} (${isTerlambatPulang.No_Transaksi})`;
            }

            // 3. Prioritas: Sakit / Izin (date range check)
            const isSakitIzin = this.TanggalSakitIzin.find((item) => {
                const start = new Date(item.Tanggal_Sakit_Izin_Dari);
                const end = new Date(item.Tanggal_Sakit_Izin_Sampai);
                start.setHours(0, 0, 0, 0);
                end.setHours(0, 0, 0, 0);
                return checkDate.getTime() >= start.getTime() && checkDate.getTime() <= end.getTime();
            });
            if (isSakitIzin) {
                return `${this.parseIzin(isSakitIzin.Jenis)} (${isSakitIzin.No_Transaksi})`;
            }

            // 4. Prioritas: Cuti (date range check)
            const cutiHariIni = this.TanggalCuti.find((cuti) => this.isDateInCuti(checkDate, cuti));
            if (cutiHariIni) {
                return `${this.parseIzin(cutiHariIni.Jenis)} (${cutiHariIni.No_Transaksi})`;
            }


            return '';
        },
        getSpecialDateTooltipSingle(date) {
            const formattedDate = this.formatDateToYYYYMMDD(date);
            const dateObj = new Date(date);
            const checkDate = new Date(formattedDate);
            checkDate.setHours(0, 0, 0, 0);

            const dayOffTooltip = this.getDayOffTooltip(checkDate);
            if (dayOffTooltip) {
                return dayOffTooltip;
            }

            const konversiPendingTooltip = this.getKonversiPendingTooltip(checkDate);
            if (konversiPendingTooltip) {
                return konversiPendingTooltip;
            }

            // 1. Prioritas: Hari spesial yang terdaftar
            const specialDay = this.specialDates.find((sd) => sd.date === formattedDate);
            if (specialDay) {
                return specialDay.description;
            }

            // 2. Prioritas: Terlambat / Pulang Cepat (hanya jika assignIzinType2 sesuai)
            if (this.assignIzinType2 === 'terlambat') {
                const isTerlambat = this.TanggalPulangTerlambat.find((item) => {
                    const start = new Date(this.getTerlambatPulangDate(item));
                    start.setHours(0, 0, 0, 0);
                    return checkDate.getTime() === start.getTime() && item.Jenis === 'terlambat';
                });
                if (isTerlambat) {
                    return `${this.parseIzin(isTerlambat.Jenis)} (${isTerlambat.No_Transaksi})`;
                }
            } else if (this.assignIzinType2 === 'pulangCepat') {
                const isPulangCepat = this.TanggalPulangTerlambat.find((item) => {
                    const start = new Date(this.getTerlambatPulangDate(item));
                    start.setHours(0, 0, 0, 0);
                    return checkDate.getTime() === start.getTime() && item.Jenis === 'pulangCepat';
                });
                if (isPulangCepat) {
                    return `${this.parseIzin(isPulangCepat.Jenis)} (${isPulangCepat.No_Transaksi})`;
                }
            }

            // 3. Prioritas: Sakit / Izin
            const isSakitIzin = this.TanggalSakitIzin.find((item) => {
                const start = new Date(item.Tanggal_Sakit_Izin_Dari);
                const end = new Date(item.Tanggal_Sakit_Izin_Sampai);
                start.setHours(0, 0, 0, 0);
                end.setHours(0, 0, 0, 0);
                return checkDate >= start && checkDate <= end;
            });
            if (isSakitIzin) {
                return `${this.parseIzin(isSakitIzin.Jenis)} (${isSakitIzin.No_Transaksi})`;
            }

            // 4. Prioritas: Cuti
            const cutiHariIni = this.TanggalCuti.find((cuti) => this.isDateInCuti(checkDate, cuti));
            if (cutiHariIni) {
                return `${this.parseIzin(cutiHariIni.Jenis)} (${cutiHariIni.No_Transaksi})`;
            }


            return '';
        },
        getClassCuti(angka) {
            if (angka > 3) {
                return '';
            } else if (angka >= 1) {
                return 'border-warning';
            } else {
                return 'border-danger';
            }
        },
        getIconCuti(angka) {
            if (angka > 3) {
                return 'bi bi-check-circle-fill text-success';
            } else if (angka >= 1) {
                return 'bi bi-exclamation-triangle-fill text-warning';
            } else {
                return 'bi bi-exclamation-circle-fill text-danger';
            }
        },
        openDialogFilter() {
            if (this.selectedDateFilter.length) {
                const startStr = this.selectedDate;
                this.tempStartDateFilter = new Date(startStr + 'T00:00:00');
                this.calendarDateFilter = new Date(startStr + 'T00:00:00');
            } else {
                this.tempStartDateFilter = null;
                this.calendarDateFilter = new Date();
            }
            this.dialogFilter = true;
        },
        maknaStatus(status) {
            if (status === 'Y') {
                return 'Disetujui';
            } else if (status === null || status === undefined) {
                return 'Menunggu Persetujuan';
            } else if (status === 'T') {
                return 'Ditolak';
            }
            return '';
        },
        getStatusTagType(status, approver) {
            if (status === 'NO' && !approver) return 'warning';
            if (status === 'Selesai') return 'success';
            if (status === 'Ditolak') return 'danger';
            return 'info';
        },

        getActiveStep(statusList) {
            const parsed = this.parsedDataArray(statusList);
            const lastApprovedIndex = parsed.findLastIndex((item) => item.status === 'Y' || item.status === 'T');
            return lastApprovedIndex + 1 || 1;
        },

        isExpired(expiredAt) {
            if (!expiredAt) return false;
            return new Date(expiredAt) < new Date();
        },
        getStepStatus(status, isExpired = false) {
            if (status === 'Y') return 'success';
            if (status === 'T' || status === 'P') return 'error';
            if (isExpired) return 'error';
            return 'wait';
        },

        getStepIcon(status) {
            if (status === 'Y') return 'el-icon-check';
            if (status === 'T') return 'el-icon-close';
            return 'el-icon-more';
        },
        parsedDataArray(data) {
            const allApprovals = data.split(', ');
            return allApprovals.map((item) => {
                const [name, status] = item.split(': ');
                return {
                    name: name.split(' ').slice(0, 1).join(' ').trim(),
                    status: status.trim(),
                };
            });
        },
        hasApprovedOrRejectedStatus(data) {
            const allApprovals = data.split(', ');
            const parsedApprovals = allApprovals.map((item) => {
                const [name, status] = item.split(': ');
                return {
                    name: name.split(' ').slice(0, 1).join(' ').trim(),
                    status: status.trim(),
                };
            });
            // Check if any approval has a status of 'Y' (Yes) or 'T' (True/Approved)
            return parsedApprovals.some((emp) => emp.status === 'Y' || emp.status === 'T');
        },
        parsedDatatoActive() {
            const totalApprovals = this.parsedData.length;
            const approvedCount = this.parsedData.filter((item) => item.status === 'Y').length;
            const rejectedCount = this.parsedData.filter((item) => item.status === 'T').length;
            const progressCount = this.parsedData.filter((item) => item.status === 'N').length;
            const approvalRate = Math.round((approvedCount / totalApprovals) * 100);
            // Ambil urutan pertama yang memiliki status "T" jika ada, jika tidak ambil "N"
            const firstT = this.parsedData.find((item) => item.status === 'T');
            const firstN = this.parsedData.find((item) => item.status === 'N');

            // Set state active sesuai prioritas
            if (firstT) {
                this.active = this.parsedData.indexOf(firstT); // Menyimpan index dari elemen dengan status "T"
            } else if (firstN) {
                this.active = this.parsedData.indexOf(firstN); // Menyimpan index dari elemen dengan status "N"
            } else {
                // Jika tidak ada "T" dan "N", mungkin set active ke default atau kondisi lain
                this.active = -1;
            }
        },
        async viewAttachment(attachmentPath) {
            if (!attachmentPath) {
                ElMessage({
                    message: 'Tidak ada lampiran untuk ditampilkan.',
                    type: 'warning',
                });
                return;
            }

            this.loadingAttachment = true;

            try {
                const response = await axios.post(
                    '/izin/getAttachmentUrl',
                    {
                        path: attachmentPath,
                    },
                    {
                        responseType: 'blob', // Penting untuk file download
                    },
                );

                if (response.status === 200) {
                    // Buat blob URL untuk download
                    const blob = new Blob([response.data]);
                    const url = window.URL.createObjectURL(blob);

                    // Extract filename dari response headers atau path
                    const fileName = attachmentPath.substring(attachmentPath.lastIndexOf('/') + 1);

                    // Trigger download
                    const downloadLink = document.createElement('a');
                    downloadLink.href = url;
                    downloadLink.download = fileName;
                    document.body.appendChild(downloadLink);
                    downloadLink.click();
                    document.body.removeChild(downloadLink);

                    // Cleanup
                    window.URL.revokeObjectURL(url);

                    ElMessage({
                        message: 'File berhasil didownload.',
                        type: 'success',
                    });
                }
            } catch (error) {
                console.error('Error downloading file:', error);
                ElMessage({
                    message: 'Terjadi kesalahan saat mendownload file.',
                    type: 'error',
                });
            } finally {
                this.loadingAttachment = false;
            }
        },
        parseStatus(status, app) {
            let nama = app.split(' ').slice(0, 1).join(' ');
            if (status == 'Ditolak') {
                return `Ditolak oleh ${nama}`;
            } else if (status == 'Selesai') {
                return 'Disetujui';
            } else {
                return `Diproses oleh ${nama}`;
            }
        },
        parseIzin(tipe) {
            if (tipe == 'izinFull') {
                return 'Izin Pribadi';
            } else if (tipe == 'sakit') {
                return 'Izin Sakit';
            } else if (tipe == 'pulangCepat') {
                return 'Pulang Cepat';
            } else if (tipe == 'terlambat') {
                return 'Izin Terlambat';
            } else if (tipe == 'cuti') {
                return 'Cuti';
            } else if (tipe == 'CUTI') {
                return 'Cuti';
            } else if (tipe == 'cutiHutang') {
                return 'Cuti';
            }
            return tipe;
        },

        formatTanggalIzin(tipe, Mulai, Selesai, Jam) {
            if (tipe == 'izinFull' || tipe == 'sakit' || tipe == 'cuti' || tipe == 'cutiHutang') {
                if (this.formatDateToDMY(Mulai) == this.formatDateToDMY(Selesai)) {
                    return this.formatDateToDMY(Mulai);
                } else {
                    return this.formatDateToDMY(Mulai).split(' ').slice(0, 1) + ' - ' + this.formatDateToDMY(Selesai);
                }
            } else {
                return this.formatDateToDMY(Mulai) + ' ' + Jam?.split(':').slice(0, 2).join(':');
            }
        },
        toMinutes(jamString) {
            if (jamString == null || jamString == undefined) return 0;
            let [h, m] = jamString.split(':').map(Number);
            return h * 60 + m;
        },
        formatTanggalIzin2(tipe, Mulai, Selesai, Jam) {
            if (tipe == 'izinFull' || tipe == 'sakit' || tipe == 'cuti' || tipe == 'cutiHutang') {
                if (this.formatDateToDMY(Mulai) == this.formatDateToDMY(Selesai)) {
                    return this.formatDateToDMY(Mulai);
                } else {
                    return this.formatDateToDMY(Mulai).split(' ').slice(0, 1) + ' - ' + this.formatDateToDMY(Selesai);
                }
            } else {
                const toMinutes = (jamString) => {
                    if (jamString == null || jamString == undefined) return 0;
                    let [h, m] = jamString.split(':').map(Number);
                    return h * 60 + m;
                };

                let jamMasuk = toMinutes(this.Jam_Masuk);
                let jamKeluar = toMinutes(this.Jam_Keluar);
                let jamIzin = toMinutes(Jam);

                let tanggal = new Date(Mulai);

                if (jamKeluar < jamMasuk) {
                    // ✅ shift malam
                    if (jamIzin < jamKeluar) {
                        tanggal.setDate(tanggal.getDate() + 1); // next day
                    }
                }
                // ✅ shift normal → selalu pakai tanggal Mulai

                return this.formatDateToDMY(tanggal) + ' ' + Jam.split(':').slice(0, 2).join(':');
            }
        },

        log() {
            // console.log(this.permissionReason);
        },
        formatTimeForSubmission(sliderValue) {
            const numValue = parseFloat(sliderValue);

            if (isNaN(numValue) || !isFinite(numValue)) {
                return '00:00:00';
            }

            const hours = Math.floor(numValue);
            const minutes = (numValue - hours) * 60;

            const formattedHours = String(Math.max(0, Math.min(23, hours))).padStart(2, '0');
            const formattedMinutes = String(Math.max(0, Math.min(59, minutes))).padStart(2, '0');

            return `${formattedHours}:${formattedMinutes}:00`;
        },

        extractSubmitErrorMessage(error) {
            const responseData = error?.response?.data || {};

            if (typeof responseData.message === 'string' && responseData.message.trim() !== '') {
                return responseData.message;
            }

            const backendErrors = responseData.errors;
            if (typeof backendErrors === 'string' && backendErrors.trim() !== '') {
                return backendErrors;
            }

            if (Array.isArray(backendErrors)) {
                const firstArrayError = backendErrors.find((item) => typeof item === 'string' && item.trim() !== '');
                if (firstArrayError) {
                    return firstArrayError;
                }
            }

            if (backendErrors && typeof backendErrors === 'object') {
                for (const value of Object.values(backendErrors)) {
                    if (
                        Array.isArray(value) &&
                        value.length > 0 &&
                        typeof value[0] === 'string' &&
                        value[0].trim() !== ''
                    ) {
                        return value[0];
                    }

                    if (typeof value === 'string' && value.trim() !== '') {
                        return value;
                    }
                }
            }

            if (typeof error?.message === 'string' && error.message.trim() !== '') {
                return error.message;
            }

            return 'Terjadi kesalahan pada server. Silakan coba lagi.';
        },

        async submitData() {
            const formData = new FormData();

            formData.append('jenisIzin', this.assignIzinType2);
            if (this.AssignDateRange && Array.isArray(this.AssignDateRange)) {
                this.AssignDateRange.forEach((date) => {
                    formData.append('tanggal[]', date);
                });
            } else {
                console.warn('AssignDateRange is not a valid array or is empty.');
            }
            const formattedTime = this.AssignTime ? this.formatToHHMM(this.AssignTime) : '00:00';
            formData.append('waktu', formattedTime);
            formData.append('jenisCuti', this.AssignJenisCuti || null);
            formData.append('Alasan', this.permissionReason);
            formData.append('jam_masuk', this.Jam_Masuk || null);
            formData.append('jam_keluar', this.Jam_Keluar || null);

            if (this.AssignFile instanceof File) {
                formData.append('file', this.AssignFile);
                console.log("Appending 'file' to FormData as a File object.");
            } else {
                console.log("this.AssignFile is NOT a File object. NOT appending 'file' to FormData.");
            }

            let isSuccess = false;
            try {
                this.loadingSimpan = true;
                const response = await axios.post('/izin/submit', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                if (response.status == 200) {
                    this.getData();
                    ElNotification({
                        title: 'Berhasil',
                        message: 'Pengajuan izin berhasil disimpan!',
                        type: 'success',
                    });
                    isSuccess = true;
                }
            } catch (error) {
                console.log(error);

                const submitErrorMessage = this.extractSubmitErrorMessage(error);
                this.errorMessage = submitErrorMessage;

                ElNotification({
                    title: 'Gagal!',
                    message: submitErrorMessage,
                    type: 'error',
                });

                ElMessage({
                    message: submitErrorMessage,
                    type: 'warning',
                    customStyle: {
                        'z-index': '1050',
                    },
                });
            } finally {
                if (isSuccess) {
                    this.closeAssignModal();
                }
                this.loadingSimpan = false;
            }
        },
        async getData(url = null) {
            try {
                this.loading = true;
                const params = new URLSearchParams();
                if (this.searchQuery) {
                    params.append('search', this.searchQuery);
                }

                if (this.filterDropdown) {
                    params.append('tipeFilter', this.filterDropdown);
                }
                if (this.selectedDateFilter) {
                    params.append('tanggalFilter', this.selectedDateFilter);
                }

                const endpoint = url || `/izin/getDataIzin?${params.toString()}`;

                const response = await axios.get(endpoint);
                if (response.status == 200) {
                    this.permissionRecords = response.data.data.data;
                    this.prevLink = response.data.data.prev_page_url;
                    this.nextLink = response.data.data.next_page_url;
                    this.totalData = response.data.data.total;
                    this.currentPage = response.data.data.current_page;
                    this.totalPages = response.data.data.last_page;

                    this.dataCuti = response.data.dataCuti;
                    this.TanggalPulangTerlambat = response.data.TanggalPulangTerlambat;
                    this.TanggalCuti = response.data.TanggalCuti;
                    this.TanggalSakitIzin = response.data.TanggalSakitIzin;
                    this.specialDates = response.data.HariLibur;

                    this.dayOffDates = Array.isArray(response.data.DayOffDates) ? response.data.DayOffDates : [];
                    this.TanggalKonversiLemburPending = Array.isArray(response.data.KonversiLemburPending)
                        ? response.data.KonversiLemburPending
                        : [];
                    // console.log(this.dayOffDates);
                }
            } catch (error) {
                this.permissionRecords = [];
                ElMessage.error('Data Tidak ditemukan.');
                console.log(error);
            } finally {
                this.loading = false;
            }
        },
        debouncedSearch: debounce(function () {
            this.getData();
        }, 500),
        canProceedToNextStep() {
            if (this.assignStep === 1) {
                return (
                    this.assignIzinType !== null &&
                    this.assignIzinType2 !== null &&
                    this.AssignDateRange !== null &&
                    this.AssignDateRange.length > 0
                );
            }
            if (this.assignStep === 2) {
                if (
                    this.assignIzinType2 === 'pulangCepat' ||
                    this.assignIzinType2 === 'terlambat' ||
                    this.assignIzinType2 === 'sakitTibaTiba'
                ) {
                    return this.AssignTime !== null && this.permissionReason !== '';
                }

                return this.permissionReason !== '';
            }
            if (this.assignStep === 3) {
                if (this.assignIzinType === 'sakit' && this.AssignDateRange[0] != this.AssignDateRange[1]) {
                    return this.AssignFile !== null;
                }
                return true;
            }
            return true;
        },
        formatSliderTooltip(value) {
            // console.log("Formatting tooltip for value:", value);
            const hours = Math.floor(value);
            const minutes = (value % 1) * 60;
            const formattedHours = String(hours).padStart(2, '0');
            const formattedMinutes = String(minutes).padStart(2, '0');
            return `${formattedHours}:${formattedMinutes}`;
        },
        formatDateToDMY(datetimeString) {
            if (!datetimeString) {
                return ''; // Return empty string if input is empty or null
            }

            const date = new Date(datetimeString);

            if (isNaN(date.getTime())) {
                console.warn('Invalid date string provided:', datetimeString);
                return '';
            }

            const day = String(date.getDate()).padStart(2, '0');
            const year = date.getFullYear();

            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            const month = monthNames[date.getMonth()];

            return `${day} ${month} ${year}`;
        },
        formatDateToString(date) {
            if (!date) return '';
            const year = date.getFullYear();
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const day = date.getDate().toString().padStart(2, '0');
            return `${year}-${month}-${day}`;
        },
        formatFullDate(dateString) {
            const [year, month, day] = dateString.split('-').map(Number);
            const date = new Date(Date.UTC(year, month - 1, day));
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                timeZone: 'UTC',
            };
            return date.toLocaleDateString('id-ID', options);
        },
        formatDateString(dateString) {
            if (!dateString) return '';
            try {
                const date = new Date(dateString);
                const options = {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                };
                let dating = new Intl.DateTimeFormat('id-ID', options).format(date);
                // console.log(dating);
                return dating;
            } catch (error) {
                console.error('Error formatting date:', error);
                return dateString; // Fallback
            }
        },
        async getTimeServer() {
            try {
                const res = await axios.get('/getTime');

                if (res.status == 200 && res.data.time) {
                    this.serverTime = new Date(res.data.time);
                } else {
                    this.serverTime = new Date();
                }
            } catch (error) {
                console.error('Gagal mengambil waktu server:', error);
                this.serverTime = new Date();
            }
        },
        disablePastDates(date) {
            const today = this.serverTime;
            today.setHours(0, 0, 0, 0); // Normalisasi 'hari ini' ke awal hari

            const dateToCheck = new Date(date);
            dateToCheck.setHours(0, 0, 0, 0); // Normalisasi tanggal yang dicek ke awal hari

            // 1. Logika untuk meng-disable tanggal yang sudah lebih dari 4 hari lalu
            const fourDaysAgo = new Date(today);
            fourDaysAgo.setDate(today.getDate() - 3);
            const isPastDate = dateToCheck.getTime() < fourDaysAgo.getTime();

            // 2. Logika untuk meng-disable tanggal spesial (cuti, sakit, terlambat)
            // Fungsi ini akan menggunakan isSpecialDate() yang sudah ada
            const isSpecial = this.isSpecialDate(dateToCheck);

            // Kembalikan 'true' jika salah satu kondisi terpenuhi
            return isPastDate || isSpecial;
        },
        disablePastDatesSingle(date) {
            const today = this.serverTime;

            today.setHours(0, 0, 0, 0); // Normalisasi 'hari ini' ke awal hari

            const dateToCheck = new Date(date);
            dateToCheck.setHours(0, 0, 0, 0); // Normalisasi tanggal yang dicek ke awal hari

            // 1. Logika untuk meng-disable tanggal yang sudah lebih dari 4 hari lalu
            const fourDaysAgo = new Date(today);
            fourDaysAgo.setDate(today.getDate() - 3);
            const isPastDate = dateToCheck.getTime() < fourDaysAgo.getTime();

            // 2. Logika untuk meng-disable tanggal spesial (cuti, sakit, terlambat)
            // Fungsi ini akan menggunakan isSpecialDate() yang sudah ada
            const isSpecial = this.isSpecialDateSingle(dateToCheck);

            // Kembalikan 'true' jika salah satu kondisi terpenuhi
            return isPastDate || isSpecial;
        },

        disablePastDatesCuti(date) {
            const today = this.serverTime;

            today.setHours(0, 0, 0, 0); // Normalisasi 'hari ini' ke awal hari

            const dateToCheck = new Date(date);
            dateToCheck.setHours(0, 0, 0, 0); // Normalisasi tanggal yang dicek ke awal hari

            // 1. Logika untuk meng-disable tanggal yang sudah lebih dari 4 hari lalu
            const fourDaysAgo = new Date(today);
            fourDaysAgo.setDate(today.getDate() + 10);
            const isPastDate = dateToCheck.getTime() < fourDaysAgo.getTime();

            // 2. Logika untuk meng-disable tanggal spesial (cuti, sakit, terlambat)
            // Fungsi ini akan menggunakan isSpecialDate() yang sudah ada
            const isSpecial = this.isSpecialDate(dateToCheck);

            // Kembalikan 'true' jika salah satu kondisi terpenuhi
            return isPastDate || isSpecial;
        },
        getDisabledDateMessage(date, mode = 'range') {
            const dateToCheck = this.normalizeDateOnly(date);

            if (this.isDayOffDate(dateToCheck)) {
                return this.getDayOffTooltip(dateToCheck);
            }

            if (mode === 'single' && this.isSpecialDateSingle(dateToCheck)) {
                return this.getSpecialDateTooltipSingle(dateToCheck) || 'Tanggal ini tidak bisa dipilih.';
            }

            if (this.isSpecialDate(dateToCheck)) {
                return this.getSpecialDateTooltip(dateToCheck) || 'Tanggal ini tidak bisa dipilih.';
            }

            return 'Tanggal ini tidak bisa dipilih.';
        },
        openDialog() {
            if (this.AssignDateRange && this.AssignDateRange.length === 2) {
                const [startStr, endStr] = this.AssignDateRange;
                this.tempStartDate = new Date(startStr + 'T00:00:00');
                this.tempEndDate = new Date(endStr + 'T00:00:00');
                this.calendarDate = new Date(startStr + 'T00:00:00');
            } else {
                this.tempStartDate = null;
                this.tempEndDate = null;
                this.calendarDate = new Date();
            }
            this.dialogVisible = true;
        },
        openDialogCuti() {
            if (this.AssignDateRange && this.AssignDateRange.length === 2) {
                const [startStr, endStr] = this.AssignDateRange;
                this.tempStartDate = new Date(startStr + 'T00:00:00');
                this.tempEndDate = new Date(endStr + 'T00:00:00');
                this.calendarDate = new Date(startStr + 'T00:00:00');
            } else {
                this.tempStartDate = null;
                this.tempEndDate = null;
                this.calendarDate = new Date();
            }
            this.dialogVisibleCuti = true;
        },
        openDialogSingle() {
            if (this.AssignDateRange && this.AssignDateRange.length === 2) {
                const [startStr] = this.AssignDateRange;
                this.tempStartDate = new Date(startStr + 'T00:00:00');
                this.tempEndDate = new Date(startStr + 'T00:00:00');
                this.calendarDate = new Date(startStr + 'T00:00:00');
            } else {
                this.tempStartDate = null;
                this.tempEndDate = null;
                this.calendarDate = new Date();
            }
            this.dialogVisibleSingle = true;
        },
        handleAssignIzin(type) {
            if (type == 'cuti') {
                this.AssignDateRange = null;
                this.assignIzinType = type;
                this.assignIzinType2 = 'cuti';
                this.tempStartDate = null;
                this.tempEndDate = null;
                this.AssignJenisCuti = null;
                this.AssignFile = null;
            } else if (type == 'sakit') {
                this.AssignDateRange = null;
                this.assignIzinType = type;
                this.assignIzinType2 = 'sakit';
                this.tempStartDate = null;
                this.tempEndDate = null;
                this.AssignFile = null;
                this.AssignJenisCuti = null;
            } else {
                this.AssignJenisCuti = null;
                this.AssignDateRange = null;
                this.assignIzinType = type;
                this.assignIzinType2 = null;
                this.tempStartDate = null;
                this.tempEndDate = null;
                this.AssignFile = null;
            }
        },
        handleCancel() {
            this.dialogVisible = false;
            this.dialogVisibleSingle = false;
            this.tempStartDate = null;
            this.tempEndDate = null;
        },
        // handleDateClick(data) {
        //     const clickedDate = data.date;

        //     // 1. Cek apakah tanggal yang diklik adalah tanggal yang di-disable
        //     if (this.disablePastDates(clickedDate)) {
        //         ElMessage({
        //             message: "Tanggal ini tidak bisa dipilih.",
        //             type: "warning",
        //         });
        //         return;
        //     }

        //     // 2. Jika rentang sudah terisi, mulai rentang baru
        //     if (this.tempStartDate && this.tempEndDate) {
        //         this.tempStartDate = clickedDate;
        //         this.tempEndDate = null;
        //     } else if (!this.tempStartDate) {
        //         // 3. Jika belum ada tanggal mulai, tetapkan clickedDate sebagai tanggal mulai
        //         this.tempStartDate = clickedDate;
        //     } else {
        //         // 4. Jika sudah ada tanggal mulai, tetapkan tanggal akhir
        //         const startDate = new Date(this.tempStartDate);
        //         const endDate = new Date(clickedDate);

        //         // Pastikan startDate tidak lebih dari endDate untuk loop
        //         let currentStart = startDate;
        //         let currentEnd = endDate;
        //         if (currentEnd.getTime() < currentStart.getTime()) {
        //             [currentStart, currentEnd] = [currentEnd, currentStart];
        //         }

        //         // --- Logika Tambahan: Memeriksa rentang tanggal ---
        //         let isDisabledInRange = false;
        //         const currentDate = new Date(currentStart);
        //         currentDate.setDate(currentStart.getDate() + 1); // Mulai dari hari setelah tanggal awal

        //         while (currentDate < currentEnd) {
        //             if (this.disablePastDates(currentDate)) {
        //                 isDisabledInRange = true;
        //                 break;
        //             }
        //             currentDate.setDate(currentDate.getDate() + 1);
        //         }

        //         if (isDisabledInRange) {
        //             ElMessage({
        //                 message:
        //                     "Tidak bisa memilih rentang tanggal yang mengandung tanggal tidak bisa dipilih.",
        //                 type: "warning",
        //             });
        //             // Reset tanggal mulai dan akhir
        //             this.tempStartDate = null;
        //             this.tempEndDate = null;
        //         } else {
        //             // Jika tidak ada tanggal yang di-disable di tengah, tetapkan tanggal akhir
        //             if (clickedDate.getTime() < this.tempStartDate.getTime()) {
        //                 this.tempEndDate = this.tempStartDate;
        //                 this.tempStartDate = clickedDate;
        //             } else {
        //                 this.tempEndDate = clickedDate;
        //             }
        //         }
        //     }
        // },
        handleDateClick(data) {
            const clickedDate = new Date(data.date);
            const isCuti = this.dialogVisibleCuti;
            const isDateDisabled = isCuti ? this.disablePastDatesCuti(clickedDate) : this.disablePastDates(clickedDate);

            // 1. Cek apakah tanggal yang diklik adalah tanggal yang di-disable
            if (isDateDisabled) {
                ElMessage({
                    message: this.getDisabledDateMessage(clickedDate, 'range'),
                    type: 'warning',
                });
                return;
            }

            // --- Klik pertama ---
            if (!this.tempStartDate && !this.tempEndDate) {
                this.tempStartDate = clickedDate;
                this.tempEndDate = clickedDate; // start & end sama
                return;
            }

            // --- Klik kedua ---
            if (this.tempStartDate && this.tempEndDate && this.tempStartDate.getTime() === this.tempEndDate.getTime()) {
                const startDate = new Date(this.tempStartDate);
                const endDate = new Date(clickedDate);

                // Tukar jika tanggal kedua < tanggal pertama
                let currentStart = startDate;
                let currentEnd = endDate;
                if (currentEnd.getTime() < currentStart.getTime()) {
                    [currentStart, currentEnd] = [currentEnd, currentStart];
                }

                // Cek apakah ada tanggal disable di tengah rentang
                let isDisabledInRange = false;
                const currentDate = new Date(currentStart);
                currentDate.setDate(currentStart.getDate() + 1); // mulai dari hari setelah tanggal awal

                const checkDisableFn = isCuti ? this.disablePastDatesCuti : this.disablePastDates;
                while (currentDate < currentEnd) {
                    if (checkDisableFn(currentDate)) {
                        isDisabledInRange = true;
                        break;
                    }
                    currentDate.setDate(currentDate.getDate() + 1);
                }

                if (isDisabledInRange) {
                    ElMessage({
                        message: 'Tidak bisa memilih rentang tanggal yang mengandung tanggal tidak bisa dipilih.',
                        type: 'warning',
                    });
                    this.tempStartDate = null;
                    this.tempEndDate = null;
                } else {
                    this.tempStartDate = currentStart;
                    this.tempEndDate = currentEnd;
                }

                return;
            }

            // --- Klik ketiga atau reset baru (jika sebelumnya sudah range penuh) ---
            if (this.tempStartDate && this.tempEndDate) {
                this.tempStartDate = clickedDate;
                this.tempEndDate = clickedDate;
            }
        },
        handleDateClickSingle(data) {
            const clickedDate = data.date;

            if (this.disablePastDatesSingle(clickedDate)) {
                ElMessage({
                    message: this.getDisabledDateMessage(clickedDate, 'single'),
                    type: 'warning',
                });
                return;
            }

            // Toggle: kalau tanggal yang sama diklik lagi, reset
            if (this.tempStartDate && this.tempStartDate.getTime() === clickedDate.getTime()) {
                this.tempStartDate = null;
            } else {
                this.tempStartDate = clickedDate;
            }

            this.tempEndDate = null;
        },
        formatDateToYYYYMMDD(date) {
            const d = new Date(date);
            const year = d.getFullYear();
            // getMonth() adalah 0-indexed, jadi tambahkan 1
            const month = (d.getMonth() + 1).toString().padStart(2, '0');
            const day = d.getDate().toString().padStart(2, '0');
            return `${year}-${month}-${day}`;
        },
        getCellClassCuti(data) {
            const classes = {};
            const dateObj = new Date(data.date);
            const formattedDate = this.formatDateToYYYYMMDD(dateObj);
            const checkDate = new Date(formattedDate);
            checkDate.setHours(0, 0, 0, 0);

            // Logika untuk rentang tanggal yang dipilih
            const start = this.tempStartDate ? new Date(this.tempStartDate).setHours(0, 0, 0, 0) : null;
            const end = this.tempEndDate ? new Date(this.tempEndDate).setHours(0, 0, 0, 0) : null;

            const isRange = start && end && start !== end;
            const isSingle = (start && !end) || (start && end && start === end);

            if (isSingle && start && checkDate.getTime() === start) {
                classes['is-selected-single'] = true;
            }

            if (isRange) {
                if (start && checkDate.getTime() === start) classes['is-start'] = true;
                if (end && checkDate.getTime() === end) classes['is-end'] = true;
                if (checkDate.getTime() > start && checkDate.getTime() < end) {
                    classes['is-in-range'] = true;
                }
            }

            // Logika untuk tanggal yang sudah lewat / tidak bisa dipilih cuti
            if (this.disablePastDatesCuti(data.date)) {
                classes['is-disabled-custom'] = true;
            }

            // Hari spesial & libur
            const isAnySpecialDate = this.specialDates.some((sd) => sd.date === formattedDate);
            if (isAnySpecialDate) {
                classes['is-red-date'] = true;
            }

            if (this.isDayOffDate(checkDate)) {
                classes['is-dayoff-date'] = true;
                classes['is-disabled-custom'] = true;
            }

            const isCuti = this.TanggalCuti.some((cuti) => this.isDateInCuti(checkDate, cuti));
            if (isCuti) {
                classes['is-orange-date'] = true;
                classes['is-disabled-custom'] = true;
            }

            const isSakitIzin = this.TanggalSakitIzin.some((item) => {
                const start = new Date(item.Tanggal_Sakit_Izin_Dari);
                const end = new Date(item.Tanggal_Sakit_Izin_Sampai);
                start.setHours(0, 0, 0, 0);
                end.setHours(0, 0, 0, 0);
                return checkDate >= start && checkDate <= end;
            });
            if (isSakitIzin) {
                classes['is-orange-date'] = true;
                classes['is-disabled-custom'] = true;
            }

            const dayOfWeek = dateObj.getDay();
            if (dayOfWeek === 0) {
                classes['is-red-date'] = true;
            }

            return classes;
        },
        getCellClass(data) {
            const classes = {};
            const dateObj = new Date(data.date);
            const formattedDate = this.formatDateToYYYYMMDD(dateObj); // Menggunakan fungsi pembantu
            const checkDate = new Date(formattedDate);
            checkDate.setHours(0, 0, 0, 0);

            // Logika untuk rentang tanggal yang dipilih
            const start = this.tempStartDate ? new Date(this.tempStartDate).setHours(0, 0, 0, 0) : null;
            const end = this.tempEndDate ? new Date(this.tempEndDate).setHours(0, 0, 0, 0) : null;

            const isRange = start && end && start !== end;
            const isSingle = (start && !end) || (start && end && start === end);

            if (isSingle && start && checkDate.getTime() === start) {
                classes['is-selected-single'] = true;
            }

            if (isRange) {
                if (start && checkDate.getTime() === start) classes['is-start'] = true;
                if (end && checkDate.getTime() === end) classes['is-end'] = true;
                if (checkDate.getTime() > start && checkDate.getTime() < end) {
                    classes['is-in-range'] = true;
                }
            }

            // Logika untuk tanggal yang sudah lewat (disabled)
            if (this.disablePastDates(data.date)) {
                classes['is-disabled-custom'] = true;
            }

            // --- Logika Penentuan Kelas untuk Tanggal Spesial ---
            // Pastikan untuk memeriksa semua jenis tanggal spesial

            // 1. Cek Hari Spesial dari specialDates
            const isAnySpecialDate = this.specialDates.some((sd) => sd.date === formattedDate);
            if (isAnySpecialDate) {
                classes['is-red-date'] = true;
            }

            if (this.isDayOffDate(checkDate)) {
                classes['is-dayoff-date'] = true;
                classes['is-disabled-custom'] = true;
            }

            // 2. Cek Tanggal Cuti
            const isCuti = this.TanggalCuti.some((cuti) => this.isDateInCuti(checkDate, cuti));
            if (isCuti) {
                classes['is-orange-date'] = true;
                classes['is-disabled-custom'] = true;
            }

            // 3. Cek Tanggal Sakit/Izin
            const isSakitIzin = this.TanggalSakitIzin.some((item) => {
                const start = new Date(item.Tanggal_Sakit_Izin_Dari);
                const end = new Date(item.Tanggal_Sakit_Izin_Sampai);
                start.setHours(0, 0, 0, 0);
                end.setHours(0, 0, 0, 0);
                return checkDate >= start && checkDate <= end;
            });
            if (isSakitIzin) {
                classes['is-orange-date'] = true;
                classes['is-disabled-custom'] = true;
            }

            // 4. Cek Tanggal Terlambat/Pulang Cepat
            const isTerlambatPulang = this.TanggalPulangTerlambat.some((item) => {
                const start = new Date(this.getTerlambatPulangDate(item));
                start.setHours(0, 0, 0, 0);
                // Menggunakan getTime() untuk perbandingan objek Date yang akurat
                return checkDate.getTime() === start.getTime();
            });
            if (isTerlambatPulang) {
                classes['is-orange-date'] = true;
                classes['is-disabled-custom'] = true;
            }

            // Logika untuk hari Minggu (Weekend)
            const dayOfWeek = dateObj.getDay(); // 0 = Minggu
            if (dayOfWeek === 0) {
                classes['is-red-date'] = true;
            }

            return classes;
        },
        getCellClassSingle(data) {
            const classes = {};
            const dateObj = new Date(data.date);
            const formattedDate = this.formatDateToYYYYMMDD(dateObj); // Menggunakan fungsi pembantu
            const checkDate = new Date(formattedDate);
            checkDate.setHours(0, 0, 0, 0);

            // Logika untuk single tanggal yang dipilih
            const start = this.tempStartDate ? new Date(this.tempStartDate).setHours(0, 0, 0, 0) : null;
            if (start && checkDate.getTime() === start) {
                classes['is-selected-single'] = true;
            }

            // Logika untuk tanggal yang sudah lewat (disabled)
            if (this.disablePastDatesSingle(data.date)) {
                classes['is-disabled-custom'] = true;
            }

            // --- Logika Penentuan Kelas untuk Tanggal Spesial ---
            // 1. Cek Hari Spesial dari specialDates
            const isAnySpecialDate = this.specialDates.some((sd) => sd.date === formattedDate);
            if (isAnySpecialDate) {
                classes['is-red-date'] = true;
            }

            if (this.isDayOffDate(checkDate)) {
                classes['is-dayoff-date'] = true;
                classes['is-disabled-custom'] = true;
            }

            // 2. Cek Tanggal Cuti
            const isCuti = this.TanggalCuti.some((cuti) => this.isDateInCuti(checkDate, cuti));
            if (isCuti) {
                classes['is-orange-date'] = true;
                classes['is-disabled-custom'] = true;
            }

            // 3. Cek Tanggal Sakit/Izin
            const isSakitIzin = this.TanggalSakitIzin.some((item) => {
                const start = new Date(item.Tanggal_Sakit_Izin_Dari);
                const end = new Date(item.Tanggal_Sakit_Izin_Sampai);
                start.setHours(0, 0, 0, 0);
                end.setHours(0, 0, 0, 0);
                return checkDate >= start && checkDate <= end;
            });
            if (isSakitIzin) {
                classes['is-orange-date'] = true;
                classes['is-disabled-custom'] = true;
            }

            // Logika untuk hari Minggu (Weekend)
            const dayOfWeek = dateObj.getDay(); // 0 = Minggu
            if (dayOfWeek === 0) {
                classes['is-red-date'] = true;
            }

            return classes;
        },

        confirmDate(jenis) {
            this.loading = true;
            if (jenis == 'single') {
                if (!this.tempStartDate) {
                    ElMessage({
                        message: 'Silakan pilih tanggal izin.',
                        type: 'warning',
                    });
                    this.loading = false;
                    return;
                }

                this.AssignDateRange = [
                    this.formatDateToString(this.tempStartDate),
                    this.formatDateToString(this.tempStartDate),
                ];
            } else {
                if (!this.tempStartDate || !this.tempEndDate) {
                    ElMessage({
                        message: 'Silakan pilih tanggal mulai dan selesai.',
                        type: 'warning',
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
            this.dialogVisibleCuti = false;
            this.loading = false;
            // console.log(this.AssignDateRange);
        },

        dayDiff(startDate, endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            return differenceInDays(end, start) + 1; // +1 agar inklusif
        },
        HitungHutang(hari) {
            // console.log(hari);
            let selisih = this.dataCuti.Sisa_Cuti - hari;
            if (selisih >= 0) {
                this.assignStep++;
            } else {
                this.hutangTerhitung = Math.abs(selisih);
                this.showCutiAlertModal = true;
            }
        },
        nextAssignStep() {
            if (this.canProceedToNextStep()) {
                if (this.assignStep == 1) {
                    if (
                        this.assignIzinType2 == 'pulangCepat' ||
                        this.assignIzinType2 == 'terlambat' ||
                        this.assignIzinType2 === 'sakitTibaTiba'
                    ) {
                        this.isLoadingAssign = true;
                        this.getJamKerja().then(() => {
                            this.assignStep++;
                            this.isLoadingAssign = false;
                        });
                    } else if (this.assignIzinType === 'cuti') {
                        this.HitungHutang(this.dayDiff(this.AssignDateRange[0], this.AssignDateRange[1]));
                    } else {
                        this.assignStep++;
                    }
                } else {
                    this.assignStep++;
                }
            } else {
                ElMessage({
                    message: 'Harap lengkapi pilihan Anda sebelum melanjutkan.',
                    type: 'warning',
                });
            }
        },
        prevAssignStep() {
            if (this.assignStep > 1) {
                this.assignStep--;
            }
        },

        // --- File Upload Methods ---
        handleFileUpload(event) {
            const file = event.target.files[0];
            if (file) {
                const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
                const maxSize = 10 * 1024 * 1024; // 10MB

                if (!allowedTypes.includes(file.type)) {
                    ElMessage({
                        message: 'Format file tidak didukung. Gunakan PDF, JPG, atau PNG.',
                        type: 'error',
                    });
                    this.AssignFile = null;
                    event.target.value = '';
                    return;
                }

                if (file.size > maxSize) {
                    ElMessage({
                        message: 'Ukuran file terlalu besar. Maksimal 10MB.',
                        type: 'error',
                    });
                    this.AssignFile = null;
                    event.target.value = '';
                    return;
                }

                this.AssignFile = file;
            } else {
                this.AssignFile = null;
            }
        },
        removeFile() {
            this.AssignFile = null;
            if (this.$refs.doctorLetterInput) {
                this.$refs.doctorLetterInput.value = '';
            }
        },

        // --- Data and UI State Management Methods ---
        deleteAlertButton(transactionId) {
            // console.log("Delete transaction:", transactionId);
            ElMessage({
                message: `Transaksi ${transactionId} akan dihapus.`,
                type: 'info',
            });
            // Implement actual deletion logic here
        },
        storeAlertButton() {
            // console.log("Storing alert/permission...");
            // You can access this.AssignFile here if it's needed for submission
            ElNotification({
                title: 'Berhasil',
                message: 'Pengajuan izin berhasil disimpan!',
                type: 'success',
                position: 'bottom-right',
            });
            this.closeAssignModal();
            // Implement actual storage logic here
        },
        closeAssignModal() {
            this.showAssignModal = false;
            this.assignStep = 1;
            this.assignIzinType = null;
            this.assignIzinType2 = null;
            this.AssignDateRange = null;
            this.AssignTime = null;
            this.permissionReason = '';
            this.tempStartDate = null;
            this.tempEndDate = null;
            this.AssignFile = null;
            this.AssignJenisCuti = null;
        },
        isMobile() {
            return this.windowWidth < 768;
        },
        updateWindowWidth() {
            this.windowWidth = window.innerWidth;
        },
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
            }
        },
        goToPage(page) {
            this.currentPage = page;
        },
        setViewMode(mode) {
            this.viewMode = mode;
            localStorage.setItem('preferredViewMode', mode);
        },
        showDetail(item) {
            this.selectedItem = item;
            this.showDetailModal = true;
        },
        showDeleteConfirm(id) {
            this.deleteItemId = id;
            this.showDeleteModal = true;
            this.showActionPopup = false;
            this.showDetailModal = false;
        },
        async confirmDelete() {
            try {
                this.loadingProccessData = true;
                const formData = new FormData();
                formData.append('No_Transaksi', this.deleteItemId);
                const response = await axios.post('/izin/destroy', formData);
                if (response.status === 200) {
                    this.getData();
                    ElNotification({
                        title: 'Berhasil',
                        message: 'Pengajuan izin berhasil dihapus',
                        type: 'success',
                    });
                }
            } catch (error) {
                // console.log(error);
                ElMessage({
                    message: `Terjadi Kesalahan ${error}`,
                    type: 'warning',
                    customStyle: {
                        'z-index': '1050',
                    },
                });
            } finally {
                this.deleteItemId = null;
                this.showDeleteModal = false;
                this.loadingProccessData = false;
            }
        },
        showActionMenu(item, event) {
            this.selectedItem = item;

            // Simpan referensi elemen yang diklik
            this.clickedElement = event.target;

            // Hitung posisi popup berdasarkan elemen yang diklik
            this.updatePopupPosition();
            this.showActionPopup = true;
        },

        // Fungsi untuk menghitung posisi popup
        updatePopupPosition() {
            // Pastikan clickedElement tidak null dan memiliki metode getBoundingClientRect
            if (this.clickedElement) {
                const rect = this.clickedElement.getBoundingClientRect();
                const popupWidth = 200; // Lebar popup
                const popupHeight = 150; // Tinggi popup

                let top = rect.bottom + window.scrollY;
                let left = rect.left + window.scrollX;

                // Menghindari popup melampaui sisi layar
                if (left + popupWidth > window.innerWidth) {
                    left = window.innerWidth - popupWidth - 10;
                }
                if (top + popupHeight > window.innerHeight) {
                    top = window.innerHeight - popupHeight - 10;
                }
                if (left < 0) {
                    left = 10;
                }
                if (top < 0) {
                    top = 10;
                }

                this.actionMenuPosition = {
                    top: `${top}px`,
                    left: `${left}px`,
                };
            }
        },
        editPermission(item) {
            this.showActionPopup = false;
            ElMessage.info('Fitur edit akan segera tersedia');
            // You would typically load the item's data into the assign modal for editing here
            // For example:
            // this.assignIzinType = item.Tipe_Izin;
            // this.AssignDateRange = [item.Tanggal_Mulai, item.Tanggal_Selesai];
            // this.permissionReason = item.Alasan;
            // this.AssignTime = item.Waktu ? parseFloat(item.Waktu.split('-')[0]) : null; // Parse time if available
            // this.showAssignModal = true;
        },
        hideSuggestions() {
            setTimeout(() => {
                this.showSearchSuggestions = false;
            }, 200);
        },
        selectSuggestion(suggestion) {
            this.searchQuery = suggestion;
            this.showSearchSuggestions = false;
        },
        truncateText(text, length) {
            return text.length > length ? text.substring(0, length) + '...' : text;
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
        getStatusClass(status) {
            return (
                {
                    Selesai: 'status-approved',
                    Diproses: 'status-pending',
                    Ditolak: 'status-rejected',
                }[status] || ''
            );
        },
        getStatusHeaderClass(status) {
            return (
                {
                    Selesai: 'header-approved',
                    Diproses: 'header-pending',
                    Ditolak: 'header-rejected',
                }[status] || 'header-pending'
            );
        },
    },
    beforeDestroy() {
        // Hapus event listener ketika komponen dihancurkan
        window.removeEventListener('scroll', this.updatePopupPosition);
    },
    mounted() {
        this.checkScreenSize();
        this.getTimeServer();
        window.addEventListener('scroll', this.updatePopupPosition);
        // Set day/night based on current time for the header icon
        const currentHour = new Date().getHours();
        this.dayNight = currentHour >= 6 && currentHour < 18 ? 'Siang' : 'Malam';

        // Check preferred view mode from localStorage or detect device
        // const preferredMode = localStorage.getItem("preferredViewMode");
        // if (preferredMode) {
        //     this.viewMode = preferredMode;
        // } else {
        //     this.viewMode = this.isMobile() ? "card" : "table";
        // }

        // Add event listener for window resize
        // window.addEventListener("resize", () => {
        //     this.updateWindowWidth();
        //     if (!localStorage.getItem("preferredViewMode")) {
        //         this.viewMode = this.isMobile() ? "card" : "table";
        //     }
        // });
        this.getData();
        // Simulate fetching data
    },
    unmounted() {
        window.removeEventListener('resize', this.updateWindowWidth);
    },
};
</script>

<style scoped>
/*
 * CSS variables (--primary, --shadow, dst) DIHAPUS dari sini karena sudah
 * didefinisikan global & identik di resources/css/app.css. Sebelumnya blok ini
 * memakai selector `*` global yang membebani setiap elemen di semua page dan
 * ikut "bocor" antar halaman pada navigasi Inertia (SPA). Gunakan var global.
 */

/* info bar */
.leave-info-panel {
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

.dayoff-calendar-tooltip {
    max-width: 240px !important;
    white-space: normal !important;
    word-break: break-word;
    line-height: 1.35 !important;
    text-align: left;
    padding: 8px 10px !important;
    font-size: 12px !important;
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
    max-height: calc(100vh - 0px); /* Sesuaikan Xpx untuk margin/padding di sekitar modal */
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
.izin-btn-modern {
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

.izin-btn-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.izin-btn-modern:hover::before {
    left: 100%;
}

.izin-btn-modern:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.izin-btn-modern:disabled:hover {
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
    background: var(--hcis-emerald-600);
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
    background: linear-gradient(135deg, var(--hcis-emerald-400) 0%, #059669ce 100%);
}
.header-pending {
    background: linear-gradient(135deg, var(--hcis-indigo-400) 0%, #6365f1ce 100%);
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
    background-color: var(--hcis-legacy-bootstrap-gray);
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

/* ==========================================================================
   Calendar Modals & Interactive Picker (Luxury Design System)
   ========================================================================== */
:deep(.el-overlay) {
    z-index: 1050;
    backdrop-filter: blur(4px);
}

:deep(.calendar-premium-dialog),
:deep(.el-dialog) {
    border-radius: 18px !important;
    overflow: hidden !important;
    border: 1px solid rgba(226, 232, 240, 0.9) !important;
    box-shadow: 0 20px 48px -12px rgba(15, 23, 42, 0.18), 0 0 0 1px rgba(255, 255, 255, 0.9) inset !important;
    background: var(--hcis-white) !important;
}

:deep(.el-dialog__header) {
    padding: 16px 20px 14px 20px !important;
    margin: 0 !important;
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200)) !important;
    background: linear-gradient(180deg, var(--hcis-white) 0%, var(--hcis-slate-50) 100%) !important;
}

:deep(.el-dialog__title) {
    font-size: 1.05rem !important;
    font-weight: 700 !important;
    color: var(--hcis-text-strong, var(--hcis-slate-900)) !important;
    letter-spacing: -0.01em !important;
}

:deep(.el-dialog__headerbtn) {
    top: 14px !important;
    right: 16px !important;
    width: 32px !important;
    height: 32px !important;
    border-radius: 8px !important;
    transition: all 150ms ease !important;
}

:deep(.el-dialog__headerbtn:hover) {
    background-color: var(--hcis-slate-100, var(--hcis-slate-100)) !important;
}

:deep(.el-dialog__body) {
    padding: 12px 18px 16px 18px !important;
    background: var(--hcis-white) !important;
}

:deep(.el-dialog__footer) {
    padding: 12px 20px 16px 20px !important;
    margin: 0 !important;
    border-top: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200)) !important;
    background: var(--hcis-surface-soft, var(--hcis-slate-50)) !important;
}

/* Calendar Element Structure */
:deep(.el-calendar) {
    background: transparent !important;
    padding: 0 !important;
}

:deep(.el-calendar__header) {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 6px 4px 12px 4px !important;
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200)) !important;
    margin-bottom: 6px !important;
}

:deep(.el-calendar__title) {
    font-weight: 800 !important;
    font-size: 0.975rem !important;
    color: var(--hcis-text-strong, var(--hcis-slate-900)) !important;
    letter-spacing: -0.01em !important;
}

:deep(.el-calendar__button-group .el-button-group) {
    display: inline-flex !important;
    gap: 4px !important;
}

:deep(.el-calendar__button-group .el-button) {
    height: 30px !important;
    padding: 0 10px !important;
    font-size: 0.75rem !important;
    font-weight: 600 !important;
    border-radius: 6px !important;
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-300)) !important;
    background: var(--hcis-white) !important;
    color: var(--hcis-text, var(--hcis-slate-700)) !important;
    transition: all 150ms ease !important;
}

:deep(.el-calendar__button-group .el-button:hover) {
    background: var(--hcis-brand-50, var(--hcis-indigo-50)) !important;
    border-color: var(--hcis-brand-300, var(--hcis-indigo-300)) !important;
    color: var(--hcis-brand-700, var(--hcis-indigo-700)) !important;
}

:deep(.el-calendar__body) {
    padding: 0 !important;
}

:deep(.el-calendar-table) {
    width: 100% !important;
    table-layout: fixed !important;
    border-collapse: separate !important;
    border-spacing: 0 2px !important;
}

:deep(.el-calendar-table thead th) {
    padding: 6px 0 8px 0 !important;
    font-size: 0.725rem !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
    color: var(--hcis-text-subtle, var(--hcis-slate-400)) !important;
    border: none !important;
    text-align: center !important;
}

:deep(.el-calendar-table tr:first-child td),
:deep(.el-calendar-table tr td:first-child),
:deep(.el-calendar-table td) {
    border: none !important;
    padding: 0 !important;
    background: transparent !important;
}

:deep(.el-calendar-table td.is-selected) {
    background-color: transparent !important;
}

:deep(.el-calendar-table .el-calendar-day) {
    height: 42px !important;
    min-height: 42px !important;
    padding: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-sizing: border-box !important;
    transition: all 150ms ease !important;
}

:deep(.el-calendar-table .el-calendar-day:hover) {
    background-color: transparent !important;
}

:deep(.el-calendar-table td.prev),
:deep(.el-calendar-table td.next) {
    opacity: 0.28 !important;
    pointer-events: none !important;
}

/* Custom Interactive Date Cell Wrapper */
:deep(.cell-date-wrapper) {
    width: 100%;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    cursor: pointer;
}

:deep(.date-cell) {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--hcis-text, var(--hcis-slate-800));
    position: relative;
    z-index: 2;
    transition: all 160ms cubic-bezier(0.2, 0.9, 0.2, 1);
    user-select: none;
}

/* Hover on normal available dates */
:deep(.cell-date-wrapper:not(.is-disabled-custom):hover .date-cell) {
    background-color: var(--hcis-brand-100, var(--hcis-indigo-100));
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    transform: scale(1.08);
}

/* In Range connecting ribbon */
:deep(.cell-date-wrapper.is-in-range::before) {
    content: '';
    position: absolute;
    top: 4px;
    bottom: 4px;
    left: 0;
    right: 0;
    background-color: var(--hcis-brand-50, var(--hcis-indigo-50));
    border-top: 1px dashed var(--hcis-brand-200, var(--hcis-indigo-200));
    border-bottom: 1px dashed var(--hcis-brand-200, var(--hcis-indigo-200));
    z-index: 1;
}

:deep(.cell-date-wrapper.is-in-range .date-cell) {
    color: var(--hcis-brand-800, var(--hcis-indigo-800));
    font-weight: 700;
}

/* Single Selected Date (Standalone Circle - NO ribbons) */
:deep(.cell-date-wrapper.is-selected-single .date-cell) {
    background: linear-gradient(135deg, var(--hcis-brand-500, var(--hcis-indigo-500)) 0%, var(--hcis-brand-600, var(--hcis-indigo-600)) 100%) !important;
    color: var(--hcis-white) !important;
    font-weight: 800 !important;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.45) !important;
    transform: scale(1.06);
}

/* Range Start Date (Half Ribbon extending to the right) */
:deep(.cell-date-wrapper.is-start:not(.is-end)::before) {
    content: '';
    position: absolute;
    top: 4px;
    bottom: 4px;
    left: 50%;
    right: 0;
    background-color: var(--hcis-brand-50, var(--hcis-indigo-50));
    border-top: 1px dashed var(--hcis-brand-200, var(--hcis-indigo-200));
    border-bottom: 1px dashed var(--hcis-brand-200, var(--hcis-indigo-200));
    z-index: 1;
}

:deep(.cell-date-wrapper.is-start .date-cell) {
    background: linear-gradient(135deg, var(--hcis-brand-500, var(--hcis-indigo-500)) 0%, var(--hcis-brand-600, var(--hcis-indigo-600)) 100%) !important;
    color: var(--hcis-white) !important;
    font-weight: 800 !important;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.45) !important;
    transform: scale(1.06);
}

/* Range End Date (Half Ribbon extending to the left) */
:deep(.cell-date-wrapper.is-end:not(.is-start)::before) {
    content: '';
    position: absolute;
    top: 4px;
    bottom: 4px;
    left: 0;
    right: 50%;
    background-color: var(--hcis-brand-50, var(--hcis-indigo-50));
    border-top: 1px dashed var(--hcis-brand-200, var(--hcis-indigo-200));
    border-bottom: 1px dashed var(--hcis-brand-200, var(--hcis-indigo-200));
    z-index: 1;
}

:deep(.cell-date-wrapper.is-end .date-cell) {
    background: linear-gradient(135deg, var(--hcis-brand-500, var(--hcis-indigo-500)) 0%, var(--hcis-brand-600, var(--hcis-indigo-600)) 100%) !important;
    color: var(--hcis-white) !important;
    font-weight: 800 !important;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.45) !important;
    transform: scale(1.06);
}

/* Range In-Between Connecting Ribbon */
:deep(.cell-date-wrapper.is-in-range::before) {
    content: '';
    position: absolute;
    top: 4px;
    bottom: 4px;
    left: 0;
    right: 0;
    background-color: var(--hcis-brand-50, var(--hcis-indigo-50));
    border-top: 1px dashed var(--hcis-brand-200, var(--hcis-indigo-200));
    border-bottom: 1px dashed var(--hcis-brand-200, var(--hcis-indigo-200));
    z-index: 1;
}

:deep(.cell-date-wrapper.is-in-range .date-cell) {
    color: var(--hcis-brand-800, var(--hcis-indigo-800)) !important;
    font-weight: 700 !important;
    background: transparent !important;
    box-shadow: none !important;
}

/* Disabled Dates - MUST allow pointer events so tooltip works on hover */
:deep(.cell-date-wrapper.is-disabled-custom),
:deep(.date-cell.is-disabled-custom) {
    cursor: not-allowed !important;
    pointer-events: auto !important;
}

/* Plain disabled dates (ordinary past dates without special status) */
:deep(.cell-date-wrapper.is-disabled-custom:not(.is-red-date):not(.is-dayoff-date):not(.is-orange-date) .date-cell) {
    color: var(--hcis-slate-300) !important;
    background: transparent !important;
    box-shadow: none !important;
    font-weight: 500 !important;
}

/* Special Dates (Vivid and clear even if unselectable/disabled, so users recognize them) */
:deep(.cell-date-wrapper.is-red-date:not(.is-start):not(.is-end):not(.is-selected-single) .date-cell) {
    color: var(--hcis-red-500) !important;
    font-weight: 700 !important;
}

:deep(.cell-date-wrapper.is-dayoff-date:not(.is-start):not(.is-end):not(.is-selected-single) .date-cell) {
    color: var(--hcis-blue-600) !important;
    font-weight: 700 !important;
}

:deep(.cell-date-wrapper.is-orange-date:not(.is-start):not(.is-end):not(.is-selected-single) .date-cell) {
    color: var(--hcis-amber-600) !important;
    font-weight: 700 !important;
}

/* Subtle dot indicator below special dates for extra visual clarity */
:deep(.cell-date-wrapper.is-red-date:not(.is-start):not(.is-end):not(.is-selected-single) .date-cell::after) {
    content: '';
    position: absolute;
    bottom: 2px;
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background-color: var(--hcis-red-500);
}

:deep(.cell-date-wrapper.is-dayoff-date:not(.is-start):not(.is-end):not(.is-selected-single) .date-cell::after) {
    content: '';
    position: absolute;
    bottom: 2px;
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background-color: var(--hcis-blue-600);
}

:deep(.cell-date-wrapper.is-orange-date:not(.is-start):not(.is-end):not(.is-selected-single) .date-cell::after) {
    content: '';
    position: absolute;
    bottom: 2px;
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background-color: var(--hcis-amber-600);
}

/* Disabled date hover state: subtle background, no scale, cursor not-allowed */
:deep(.cell-date-wrapper.is-disabled-custom:hover .date-cell) {
    background-color: rgba(241, 245, 249, 0.8) !important;
    transform: none !important;
}

/* Calendar Legend Indicator Bar */
:deep(.calendar-legend-bar) {
    display: flex !important;
    flex-wrap: wrap !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 8px 12px !important;
    padding: 10px 14px !important;
    margin: 12px auto 0 auto !important;
    width: 100% !important;
    border-top: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200)) !important;
    background: var(--hcis-surface-soft, var(--hcis-slate-50)) !important;
    border-radius: 12px !important;
    box-sizing: border-box !important;
}

:deep(.legend-item) {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 7px !important;
    padding: 5px 10px !important;
    background: var(--hcis-white) !important;
    border: 1px solid var(--hcis-slate-200) !important;
    border-radius: 8px !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important;
    flex-shrink: 0 !important;
}

:deep(.legend-dot) {
    width: 9px !important;
    height: 9px !important;
    min-width: 9px !important;
    min-height: 9px !important;
    border-radius: 50% !important;
    display: inline-block !important;
    flex-shrink: 0 !important;
}

:deep(.legend-dot-selected) {
    background-color: var(--hcis-indigo-500) !important;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.25) !important;
}

:deep(.legend-dot-holiday) {
    background-color: var(--hcis-red-500) !important;
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.25) !important;
}

:deep(.legend-dot-dayoff) {
    background-color: var(--hcis-blue-600) !important;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.25) !important;
}

:deep(.legend-dot-leave) {
    background-color: var(--hcis-amber-600) !important;
    box-shadow: 0 0 0 2px rgba(217, 119, 6, 0.25) !important;
}

:deep(.legend-text) {
    font-size: 0.75rem !important;
    font-weight: 600 !important;
    color: var(--hcis-text, var(--hcis-slate-700)) !important;
    white-space: nowrap !important;
    line-height: 1 !important;
}

/* Dialog Footer Structure & Selected Date Chip */
:deep(.calendar-footer-wrap) {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 12px !important;
    width: 100% !important;
}

:deep(.calendar-selected-chip) {
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
    padding: 6px 12px !important;
    background: var(--hcis-indigo-50) !important;
    border: 1px solid var(--hcis-indigo-200) !important;
    border-radius: 10px !important;
    min-height: 38px !important;
    flex-shrink: 1 !important;
    max-width: 60% !important;
    overflow: hidden !important;
}

:deep(.chip-icon) {
    color: var(--hcis-indigo-500) !important;
    font-size: 0.95rem !important;
    flex-shrink: 0 !important;
}

:deep(.chip-placeholder) {
    color: var(--hcis-slate-500) !important;
    font-size: 0.8125rem !important;
    font-weight: 500 !important;
    white-space: nowrap !important;
}

:deep(.chip-active) {
    color: var(--hcis-indigo-800) !important;
    font-size: 0.8125rem !important;
    font-weight: 700 !important;
    white-space: nowrap !important;
    text-overflow: ellipsis !important;
    overflow: hidden !important;
}

:deep(.calendar-footer-actions) {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    flex-shrink: 0 !important;
}

:deep(.modal-action-btn) {
    height: 38px !important;
    padding: 0 16px !important;
    font-size: 0.85rem !important;
    font-weight: 600 !important;
    border-radius: 8px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}

:deep(.dayoff-calendar-tooltip) {
    max-width: 260px !important;
    white-space: normal !important;
    word-break: break-word;
    line-height: 1.4 !important;
    text-align: left;
    padding: 8px 12px !important;
    font-size: 12px !important;
    border-radius: 8px !important;
    background: var(--hcis-slate-800, var(--hcis-slate-800)) !important;
    color: var(--hcis-white) !important;
}

/* Step 2 Time Picker Container */
:deep(.time-picker-custom-container) {
    width: 100%;
}

:deep(.time-picker-custom-container .el-date-editor) {
    width: 100% !important;
}

:deep(.time-picker-custom-container .el-input__wrapper) {
    height: 44px !important;
    border-radius: 10px !important;
    padding: 0 14px !important;
    box-shadow: 0 0 0 1px var(--hcis-slate-300) inset !important;
    background: var(--hcis-white) !important;
    transition: all 160ms cubic-bezier(0.2, 0.9, 0.2, 1) !important;
}

:deep(.time-picker-custom-container .el-input__wrapper:hover) {
    box-shadow: 0 0 0 1px var(--hcis-indigo-400) inset !important;
}

:deep(.time-picker-custom-container .el-input__wrapper.is-focus) {
    box-shadow: 0 0 0 2px var(--hcis-indigo-500) inset, 0 0 0 4px rgba(99, 102, 241, 0.15) !important;
}

:deep(.time-picker-custom-container .el-input__inner) {
    font-size: 0.925rem !important;
    font-weight: 600 !important;
    color: var(--hcis-slate-900) !important;
}

:deep(.time-picker-custom-container .el-input__prefix-inner) {
    color: var(--hcis-indigo-500) !important;
    font-size: 1.1rem !important;
}

/* Step 2 Form Card & Input Field Group */
.step-form-card {
    background: rgba(99, 102, 241, 0.04);
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    border-radius: 12px;
    padding: 1.125rem;
}

.form-field-group {
    margin-bottom: 1.125rem;
}

.form-field-group:last-child {
    margin-bottom: 0;
}

.hcis-textarea {
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-300));
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 0.925rem;
    font-weight: 500;
    transition: all 160ms cubic-bezier(0.2, 0.9, 0.2, 1);
    resize: vertical;
    width: 100%;
    background-color: var(--hcis-white);
    box-sizing: border-box;
}

.hcis-textarea:focus {
    border-color: var(--hcis-brand-500, var(--hcis-indigo-500));
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    outline: none;
}

/* Modal Footer & Buttons Polish */
.modal-footer {
    padding: 1rem 1.5rem !important;
    border-top: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200)) !important;
    background-color: var(--hcis-surface-soft, var(--hcis-slate-50)) !important;
    border-bottom-left-radius: 12px;
    border-bottom-right-radius: 12px;
    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
}

.footer-actions {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: 8px !important;
    width: 100% !important;
}

.footer-actions .izin-btn-modern.modal-btn {
    height: 40px !important;
    padding: 0 18px !important;
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    border-radius: 10px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    line-height: 1 !important;
    text-decoration: none !important;
    cursor: pointer !important;
    transition: all 160ms cubic-bezier(0.2, 0.9, 0.2, 1) !important;
}

.footer-actions .izin-btn-modern.btn-secondary {
    background: var(--hcis-white) !important;
    color: var(--hcis-text, var(--hcis-slate-700)) !important;
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-300)) !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
}

.footer-actions .izin-btn-modern.btn-secondary:hover {
    background: var(--hcis-slate-50) !important;
    color: var(--hcis-slate-900) !important;
    border-color: var(--hcis-slate-400) !important;
}

.footer-actions .izin-btn-modern.btn-primary {
    background: linear-gradient(135deg, var(--hcis-brand-500, var(--hcis-indigo-500)) 0%, var(--hcis-brand-600, var(--hcis-indigo-600)) 100%) !important;
    color: var(--hcis-white) !important;
    border: 1px solid var(--hcis-brand-600, var(--hcis-indigo-600)) !important;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3) !important;
}

.footer-actions .izin-btn-modern.btn-primary:hover:not(:disabled) {
    background: linear-gradient(135deg, var(--hcis-brand-600, var(--hcis-indigo-600)) 0%, var(--hcis-brand-700, var(--hcis-indigo-700)) 100%) !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4) !important;
}

.footer-actions .izin-btn-modern.btn-primary:disabled {
    opacity: 0.55 !important;
    cursor: not-allowed !important;
    transform: none !important;
    box-shadow: none !important;
}

.footer-actions .izin-btn-modern.btn-success {
    background: linear-gradient(135deg, var(--hcis-emerald-500) 0%, var(--hcis-emerald-600) 100%) !important;
    color: var(--hcis-white) !important;
    border: 1px solid var(--hcis-emerald-600) !important;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3) !important;
}

.footer-actions .izin-btn-modern.btn-success:hover:not(:disabled) {
    background: linear-gradient(135deg, var(--hcis-emerald-600) 0%, var(--hcis-emerald-700) 100%) !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4) !important;
}

/* Transitions */
.fade-in {
    animation: fadeIn 0.5s ease-in-out;
}
.fade-switch-enter-active,
.fade-switch-leave-active {
    transition:
        opacity 0.3s ease,
        transform 0.3s ease;
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
    :deep(.el-calendar__header) {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
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
    .izin-btn-modern {
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
    .modal-footer .izin-btn-modern {
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
    :deep(.calendar-premium-dialog),
    :deep(.el-dialog) {
        width: 95vw !important;
        margin: auto !important;
    }
    :deep(.el-calendar__header) {
        padding: 4px 0 8px 0 !important;
    }
    :deep(.el-calendar__title) {
        font-size: 0.85rem !important;
    }
    :deep(.el-calendar__button-group .el-button) {
        height: 26px !important;
        padding: 0 6px !important;
        font-size: 0.7rem !important;
    }
    :deep(.el-calendar__body) {
        padding: 0 !important;
    }
    :deep(.el-calendar-table .el-calendar-day) {
        height: 36px !important;
        min-height: 36px !important;
    }
    :deep(.cell-date-wrapper) {
        height: 36px !important;
    }
    :deep(.date-cell) {
        width: 30px !important;
        height: 30px !important;
        font-size: 0.8rem !important;
    }
    :deep(.calendar-footer-wrap) {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 8px !important;
    }
    :deep(.calendar-selected-chip) {
        max-width: 100% !important;
        width: 100% !important;
        justify-content: center !important;
    }
    :deep(.calendar-footer-actions) {
        width: 100% !important;
        justify-content: stretch !important;
    }
    :deep(.calendar-footer-actions .modal-action-btn) {
        flex: 1 !important;
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
    .izin-btn-modern {
        width: 100%;
        justify-content: center;
    }
}
</style>
