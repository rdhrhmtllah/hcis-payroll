<template>
    <!-- Header -->
    <div
        class="row d-flex align-items-center justify-content-center mb-3 fade-in"
    >
        <div class="col-md-12">
            <div class="modern-header">
                <div class="header-background"></div>
                <div class="header-content">
                    <div class="row align-items-center">
                        <div class="col-sm-8">
                            <div class="header-info">
                                <div
                                    @click="log()"
                                    class="icon-container d-flex align-items-center justify-content-center"
                                >
                                    <i
                                        class="bi bi-person-badge d-flex justify-content-center align-items-center"
                                    ></i>
                                </div>
                                <div class="text-content">
                                    <h2
                                        class="title fw-bold text-white text-start"
                                    >
                                        Halo,
                                        {{
                                            capitalize(
                                                namaKaryawan
                                                    ?.split(" ")
                                                    .slice(0, 2)
                                                    .join(" "),
                                            )
                                        }}
                                    </h2>
                                    <p class="subtitle">
                                        Manage Pengajuan Izin
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div
                                class="header-actions d-flex gap-2 flex-nowrap flex-sm-column justify-content-center justify-content-md-start justify-content-md-center align-items-md-end"
                            >
                                <div
                                    class="date-range btn-modern px-2 px-md-3 py-1 gap-2 gap-md-3"
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
                                        <span class="fs-md-6 text-header">{{
                                            formatDateString(currentDate)
                                        }}</span>
                                    </div>
                                </div>
                                <!-- <button
                                    class="btn-modern btn-primary flex-nowrap w-full px-md-2 py-md-2"
                                    @click="showAssignModal = true"
                                >
                                    <i
                                        class="bi bi-plus-circle me-2 d-flex justify-content-center align-items-center"
                                    ></i>
                                    <span class="text-header">Ajukan Izin</span>
                                </button> -->
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
                    <div
                        class="d-md-flex flex-grow-1 row gap-0 gap-md-2 px-2 px-md-0"
                    >
                        <div
                            class="d-md-none d-block flex-grow-1 col-6 col-md-12 p-1"
                        >
                            <input
                                v-model="selectedDateFilter"
                                type="date"
                                class="form-control"
                                placeholder="Semua"
                                style="cursor: pointer"
                                title="Pilih Tanggal"
                            />
                            <!-- <div
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
                        <div
                            class="d-md-none d-block flex-grow-1 col-6 col-md-12 p-1 position-relative"
                        >
                            <select
                                v-model="filterDropdown"
                                name=""
                                id=""
                                class="form-control"
                            >
                                <option selected value="">Semua</option>
                                <option value="izinFull">Izin</option>
                                <option value="cuti">Cuti</option>
                                <option value="sakit">Sakit</option>
                                <option value="pulangCepat">
                                    Pulang cepat
                                </option>
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
                        <div
                            class="d-md-none d-block flex-grow-1 col-12 p-1 position-relative"
                        >
                            <select
                                v-model="approvalStatusFilter"
                                class="form-control"
                            >
                                <option selected value="">Semua Status</option>
                                <option value="belumApprove">
                                    Belum approve
                                </option>
                                <option value="approved">Disetujui</option>
                                <option value="rejected">Ditolak</option>
                                <option value="expired">Expired</option>
                            </select>
                            <i
                                v-if="approvalStatusFilter == ''"
                                class="bi bi-chevron-down position-absolute"
                                style="right: 17px; top: 18px"
                            ></i>
                            <span
                                v-else
                                style="right: 17px; top: 13px"
                                @click="approvalStatusFilter = ''"
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
                            <div
                                class="search-results-count"
                                v-if="searchQuery"
                            >
                                {{ totalData }} hasil
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
                        <div
                            style="width: 10rem"
                            class="d-md-block w-max d-none position-relative ps-0"
                        >
                            <select
                                v-model="filterDropdown"
                                name=""
                                id=""
                                class="form-control w-max"
                            >
                                <option selected value="">Semua</option>
                                <option value="izinFull">Izin</option>
                                <option value="cuti">Cuti</option>
                                <option value="sakit">Sakit</option>
                                <option value="pulangCepat">
                                    Pulang cepat
                                </option>
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
                        <div
                            style="width: 12rem"
                            class="d-md-block w-max d-none position-relative ps-0"
                        >
                            <select
                                v-model="approvalStatusFilter"
                                class="form-control w-max"
                            >
                                <option selected value="">Semua Status</option>
                                <option value="belumApprove">
                                    Belum approve
                                </option>
                                <option value="approved">Disetujui</option>
                                <option value="rejected">Ditolak</option>
                                <option value="expired">Expired</option>
                            </select>
                            <i
                                v-if="approvalStatusFilter == ''"
                                class="bi bi-chevron-down position-absolute"
                                style="right: 25px; top: 14px"
                            ></i>
                            <span
                                v-else
                                style="right: 20px; top: 8px"
                                @click="approvalStatusFilter = ''"
                                class="closeFilter p-1 rounded-3 ms-2 position-absolute"
                                ><i class="bi bi-x"></i
                            ></span>
                        </div>
                    </div>

                    <div class="filters-container flex-grow-1 flex-md-grow-0">
                        <div
                            class="search-container flex-grow-1 d-block d-md-none"
                        >
                            <i
                                class="bi bi-search search-icon d-flex justify-content-center align-items-center"
                            ></i>
                            <input
                                type="text"
                                class="search-input"
                                placeholder="Cari izin..."
                                v-model="searchQuery"
                                @focus="showSearchSuggestions = true"
                                @blur="hideSuggestions"
                            />
                            <div
                                class="search-results-count"
                                v-if="searchQuery"
                            >
                                {{ totalData }} hasil
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
                        </div>
                        <!-- view Toggle -->
                        <div class="view-toggle py-2">
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
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

                <!-- Tidak ada Isi -->
                <div v-else-if="totalData == 0 && !loading" class="empty-state">
                    <div class="empty-icon">
                        <i
                            class="bi bi-inbox d-flex justify-content-center align-items-center"
                        ></i>
                    </div>
                    <h4>Tidak Ada Data Pengajuan Izin</h4>
                    <p>Anda belum memiliki pengajuan izin dari team.</p>
                    <!-- <button
                        class="btn-modern btn-primary"
                        @click="showAssignModal = true"
                    >
                        <i
                            class="bi bi-plus-circle me-2 d-flex justify-content-center align-items-center"
                        ></i>
                        Ajukan Izin
                    </button> -->
                </div>

                <transition name="fade-switch" mode="out-in">
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
                                                    class="bi bi-stack me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span
                                                    >No Transaksi
                                                    <small class="fw-light"
                                                        >({{
                                                            totalData
                                                        }})</small
                                                    ></span
                                                >
                                            </div>
                                        </th>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-people me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Nama Pengaju</span>
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
                                                    class="bi bi-paperclip me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Step</span>
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
                                    </tr>
                                </thead>
                                <tbody v-if="permissionRecords.length > 0">
                                    <tr
                                        v-for="item in sortedPermissionRecords"
                                        :key="item.No_Transaksi"
                                        class="table-row"
                                        @click="showDetail(item)"
                                    >
                                        <td class="sticky-column">
                                            <div class="transaction-cell">
                                                <div class="transaction-id">
                                                    {{ item.No_Transaksi }}
                                                </div>
                                                <div
                                                    class="transaction-type"
                                                    :class="
                                                        getTypeClass(
                                                            item.Tipe_Izin,
                                                        )
                                                    "
                                                >
                                                    <span
                                                        class="type-indicator"
                                                    ></span>
                                                    {{
                                                        parseIzin(
                                                            item.Tipe_Izin,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-table-cell">
                                                {{
                                                    item.requester_Nama
                                                        ?.split(" ")
                                                        .slice(0, 2)
                                                        .join(" ")
                                                }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-table-cell">
                                                {{
                                                    truncateText(
                                                        item.tanggalReal,
                                                        25,
                                                    )
                                                }}
                                            </div>
                                        </td>
                                        <td>
                                            <div
                                                class="reason-cell"
                                                :title="item.Alasan"
                                            >
                                                {{
                                                    truncateText(
                                                        item.Alasan,
                                                        30,
                                                    )
                                                }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="attachment-cell">
                                                <a
                                                    v-if="item.Lampiran"
                                                    href="#"
                                                    @click.prevent.stop="
                                                        viewAttachment(
                                                            item.Lampiran,
                                                        )
                                                    "
                                                    target="_blank"
                                                    class="attachment-link"
                                                    :class="{
                                                        'is-loading':
                                                            loadingAttachment,
                                                    }"
                                                    :disabled="
                                                        loadingAttachment
                                                    "
                                                >
                                                    <span
                                                        class="d-flex justify-content-center align-items-center"
                                                        v-if="
                                                            !loadingAttachment
                                                        "
                                                    >
                                                        <i
                                                            class="bi me-1 bi-file-earmark-arrow-down d-flex justify-content-center align-items-center"
                                                        ></i>
                                                        Lihat
                                                    </span>
                                                    <span v-else>
                                                        <i
                                                            class="bi bi-arrow-clockwise animated-spin"
                                                        ></i>
                                                        Memuat...
                                                    </span>
                                                </a>
                                                <span
                                                    v-else
                                                    class="no-attachment"
                                                >
                                                    <i class="bi bi-file-x"></i>
                                                    Tidak ada
                                                </span>
                                            </div>
                                        </td>
                                        <td
                                            @click.stop
                                            @mouseover="
                                                approvalData = [item.STATUSLIST]
                                            "
                                        >
                                            <el-tooltip
                                                :content="
                                                    (item.status == 'Ditolak' &&
                                                    item.Status_Approval_Saat_Ini ==
                                                        'N') ||
                                                    isExpired(item.expired_at, item.status)
                                                        ? ''
                                                        : tooltipContent
                                                "
                                                raw-content
                                                placement="top"
                                                :width="350"
                                            >
                                                <div class="status-cell">
                                                    <template v-if="isExpired(item.expired_at, item.status)">
                                                        Expired
                                                    </template>
                                                    <template v-else>
                                                        {{
                                                            item.currentFlow +
                                                            "/" +
                                                            item.MaxFlow
                                                        }}
                                                        Step
                                                    </template>
                                                </div>
                                            </el-tooltip>
                                        </td>
                                        <td>
                                            <div class="status-cell">
                                                <span
                                                    v-if="
                                                        item.Status_Approval_Saat_Ini ==
                                                            'N' &&
                                                        item.status ==
                                                            'Diproses' &&
                                                        !isExpired(item.expired_at, item.status)
                                                    "
                                                    class="status-badge p-0 d-flex justify-content-between"
                                                    style="width: 10rem"
                                                >
                                                    <span
                                                        @click.stop="
                                                            showConfirmYesModal(
                                                                'Terima',
                                                                item.No_Transaksi,
                                                                item.Requester_Kode_Karyawan,
                                                                item.Approver_Kode_Karyawan,
                                                                item.Status_Approval_Saat_Ini,
                                                            )
                                                        "
                                                        class="d-flex px-2 justify-content-center text-success cursor-pointer buttonAccept right"
                                                        ><i
                                                            class="bi bi-check-circle me-1"
                                                        ></i
                                                        >Terima</span
                                                    >
                                                    <span
                                                        @click.stop="
                                                            showConfirmNoModal(
                                                                'Tolak',
                                                                item.No_Transaksi,
                                                                item.Requester_Kode_Karyawan,
                                                                item.Approver_Kode_Karyawan,
                                                                item.Status_Approval_Saat_Ini,
                                                            )
                                                        "
                                                        class="d-flex px-2 justify-content-center text-danger cursor-pointer buttonAccept left"
                                                        ><i
                                                            class="bi bi-x-circle me-1"
                                                        ></i
                                                        >Tolak</span
                                                    >
                                                </span>
                                                <span
                                                    v-else-if="
                                                        item.Status_Approval_Saat_Ini ==
                                                            'N' &&
                                                        item.status ==
                                                            'Diproses' &&
                                                        isExpired(item.expired_at, item.status)
                                                    "
                                                    class="text-danger"
                                                    >EXPIRED</span
                                                >
                                                <span
                                                    v-else-if="
                                                        item.Status_Approval_Saat_Ini
                                                            ? item.Status_Approval_Saat_Ini?.trim() ===
                                                              'Y'
                                                            : false
                                                    "
                                                    class="text-success"
                                                    >DISETUJUI</span
                                                >
                                                <span
                                                    v-else-if="
                                                        item.Status_Approval_Saat_Ini ==
                                                            'P' ||
                                                        item.status ==
                                                            'Ditolak sistem'
                                                    "
                                                    class="text-danger"
                                                    >Ditolak
                                                    Sistem/Expired</span
                                                >
                                                <span v-else class="text-danger"
                                                    >DITOLAK
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                                <tbody v-else>
                                    <tr>
                                        <td colspan="7">
                                            <div
                                                class="empty-state h-100 d-flex justify-content-center align-items-center flex-column py-5"
                                            >
                                                <div class="empty-icon mb-4">
                                                    <DotLottieVue
                                                        style="
                                                            height: 150px;
                                                            width: 150px;
                                                        "
                                                        autoplay
                                                        loop
                                                        src="/animation/empty-data.json"
                                                    />
                                                </div>
                                                <h4 class="mb-1">
                                                    Tidak Ada Data
                                                </h4>
                                                <p class="text-muted">
                                                    Belum ada pengajuan
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="pagination-container" v-if="totalPages > 1">
                            <button
                                class="page-btn"
                                @click="getData(prevLink)"
                                :disabled="currentPage === 1"
                            >
                                <i
                                    class="bi bi-chevron-left d-flex justify-content-center align-items-center"
                                ></i>
                            </button>
                            <div class="page-info">
                                Halaman {{ currentPage }} dari {{ totalPages }}
                            </div>
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

                    <div
                        v-else-if="
                            viewMode === 'card' && !loading && totalData > 0
                        "
                        class="card-view-container"
                        key="card"
                    >
                        <div
                            v-if="permissionRecords.length > 0"
                            class="card-grid"
                        >
                            <div
                                v-for="item in sortedPermissionRecords"
                                :key="item.No_Transaksi"
                                class="permission-card"
                                @click="showDetail(item)"
                            >
                                <div
                                    class="card-header"
                                    :class="
                                        getStatusHeaderClass(
                                            item.Status_Approval_Saat_Ini,
                                        )
                                    "
                                >
                                    <div class="card-date">
                                        {{ item.tanggalReal }}
                                    </div>
                                    <div class="card-status">
                                        {{
                                            maknaStatus(
                                                item.Status_Approval_Saat_Ini,
                                            )
                                        }}
                                    </div>
                                </div>
                                <div class="card-body row">
                                    <div class="col-md-8 col-6">
                                        <div class="card-row">
                                            <!-- <i
                                                class="bi bi-hash d-flex justify-content-center align-items-center"
                                            ></i> -->
                                            <div class="card-label">Nama :</div>
                                            <div class="card-value">
                                                {{
                                                    truncateText(
                                                        capitalize(
                                                            item.requester_Nama,
                                                        ),
                                                        20,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                        <div class="card-row">
                                            <!-- <i
                                                class="bi bi-tag d-flex justify-content-center align-items-center"
                                            ></i> -->
                                            <div class="card-label">
                                                Jenis Izin :
                                            </div>
                                            <div class="card-value">
                                                {{ parseIzin(item.Tipe_Izin) }}
                                            </div>
                                        </div>
                                        <div class="card-row">
                                            <!-- <i
                                                class="bi bi-chat-dots d-flex justify-content-center align-items-center"
                                            ></i> -->
                                            <div class="card-label">
                                                Alasan :
                                            </div>
                                            <div class="card-value">
                                                {{
                                                    truncateText(
                                                        item.Alasan,
                                                        20,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                        <div v-if="item.Waktu" class="card-row">
                                            <!-- <i
                                                class="bi bi-clock d-flex justify-content-center align-items-center"
                                            ></i> -->
                                            <div class="card-label">
                                                Waktu :
                                            </div>
                                            <div class="card-value">
                                                {{
                                                    formatSliderTooltip(
                                                        item.Waktu,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                        <div class="card-row">
                                            <!-- <i
                                                class="bi bi-paperclip d-flex justify-content-center align-items-center"
                                            ></i> -->
                                            <div class="card-label">
                                                Lampiran :
                                            </div>
                                            <div class="card-value">
                                                <a
                                                    v-if="item.Lampiran"
                                                    href="#"
                                                    @click.prevent.stop="
                                                        viewAttachment(
                                                            item.Lampiran,
                                                        )
                                                    "
                                                    target="_blank"
                                                    class="card-link"
                                                    :class="{
                                                        'is-loading':
                                                            loadingAttachment,
                                                    }"
                                                    :disabled="
                                                        loadingAttachment
                                                    "
                                                >
                                                    <span
                                                        class="d-flex justify-content-center align-items-center"
                                                        v-if="
                                                            !loadingAttachment
                                                        "
                                                    >
                                                        Lihat File
                                                    </span>
                                                    <span v-else>
                                                        <i
                                                            class="bi bi-arrow-clockwise animated-spin"
                                                        ></i>
                                                        Memuat...
                                                    </span>
                                                </a>
                                                <span v-else class="no-file">
                                                    Tidak ada
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6 p-0">
                                        <div
                                            class="card-label flex-column mb-2"
                                        >
                                            Progress:
                                        </div>
                                        <div
                                            class="card-value"
                                            style="height: 50%"
                                        >
                                            <el-steps
                                                align-center
                                                style="
                                                    font-size: 0.5rem;
                                                    max-height: 15rem;
                                                "
                                                direction="vertical"
                                                :active="2"
                                            >
                                                <el-step
                                                    align-center
                                                    v-for="(
                                                        data, index
                                                    ) in parsedDataArray(
                                                        item.STATUSLIST,
                                                    )"
                                                    :status="
                                                        data.status == 'Y'
                                                            ? 'success'
                                                            : data.status == 'T' || data.status == 'P'
                                                              ? 'error'
                                                              : isExpired(item.expired_at, item.status)
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
                                    <!-- <button
                                        class="card-btn delete-btn"
                                        @click.stop="
                                            showDeleteConfirm(item.No_Transaksi)
                                        "
                                    >
                                        <i
                                            class="bi bi-trash d-flex justify-content-center align-items-center"
                                        ></i>
                                        Hapus
                                    </button> -->
                                    <div class="card-btn px-0 py-0">
                                        <div class="status-cell">
                                            <span
                                                v-if="
                                                    item.Status_Approval_Saat_Ini ==
                                                        'N' &&
                                                    item.status == 'Diproses' &&
                                                    !isExpired(item.expired_at, item.status)
                                                "
                                                class="d-flex justify-content-between"
                                                style="width: 10rem"
                                            >
                                                <span
                                                    style="
                                                        cursor: pointer !important;
                                                        border-radius: 8px 0px
                                                            0px 8px !important;
                                                    "
                                                    @click.stop="
                                                        showConfirmYesModal(
                                                            'Terima',
                                                            item.No_Transaksi,
                                                            item.Requester_Kode_Karyawan,
                                                            item.Approver_Kode_Karyawan,
                                                            item.Status_Approval_Saat_Ini,
                                                        )
                                                    "
                                                    class="d-flex pe-1 ps-1 justify-content-center text-success cursor-pointer buttonAccept right"
                                                    ><i
                                                        class="bi bi-check-circle me-1"
                                                    ></i
                                                    >Terima</span
                                                >
                                                <span
                                                    style="
                                                        cursor: pointer !important;
                                                        border-radius: 0 8px 8px
                                                            0 !important;
                                                    "
                                                    @click.stop="
                                                        showConfirmNoModal(
                                                            'Tolak',
                                                            item.No_Transaksi,
                                                            item.Requester_Kode_Karyawan,
                                                            item.Approver_Kode_Karyawan,
                                                            item.Status_Approval_Saat_Ini,
                                                        )
                                                    "
                                                    class="d-flex ps-1 pe-1 justify-content-center text-danger cursor-pointer buttonAccept left"
                                                    ><i
                                                        class="bi bi-x-circle me-1"
                                                    ></i
                                                    >Tolak</span
                                                >
                                            </span>
                                            <span
                                                v-else-if="
                                                    item.Status_Approval_Saat_Ini ==
                                                        'N' &&
                                                    item.status == 'Diproses' &&
                                                    isExpired(item.expired_at, item.status)
                                                "
                                                class="text-danger"
                                                >EXPIRED</span
                                            >
                                            <span
                                                v-else-if="
                                                    item.Status_Approval_Saat_Ini
                                                        ? item.Status_Approval_Saat_Ini?.trim() ===
                                                          'Y'
                                                        : false
                                                "
                                                class="text-success"
                                                >DISETUJUI</span
                                            >
                                            <span
                                                v-else-if="
                                                    item.Status_Approval_Saat_Ini ==
                                                        'P' ||
                                                    item.status ==
                                                        'Ditolak sistem'
                                                "
                                                class="text-danger"
                                                >Ditolak Sistem/Expired</span
                                            >
                                            <span v-else class="text-danger"
                                                >DITOLAK</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="empty-state">
                            <div class="empty-icon">
                                <DotLottieVue
                                    style="height: 150px; width: 150px"
                                    autoplay
                                    loop
                                    src="/animation/empty-data.json"
                                />
                            </div>
                            <h4>Tidak Ada Data</h4>
                            <p>Belum ada pengajuan</p>
                        </div>

                        <div class="pagination-container" v-if="totalPages > 1">
                            <button
                                class="page-btn"
                                @click="getData(prevLink)"
                                :disabled="currentPage === 1"
                            >
                                <i
                                    class="bi bi-chevron-left d-flex justify-content-center align-items-center"
                                ></i>
                            </button>
                            <div class="page-info">
                                Halaman {{ currentPage }} dari {{ totalPages }}
                            </div>
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
                </transition>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
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
                    <span class="detail-label">Nama Pengaju:</span>
                    <span class="detail-value highlight">
                        {{
                            truncateText(
                                capitalize(selectedItem.requester_Nama),
                                30,
                            )
                        }}
                    </span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Jenis Izin:</span>
                    <span class="detail-value highlight">
                        {{ parseIzin(selectedItem.Tipe_Izin) }}
                    </span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Tanggal:</span>
                    <span class="detail-value">
                        {{ selectedItem.tanggalReal }}
                    </span>
                </div>

                <div v-if="selectedItem.Jam_Mulai" class="detail-item">
                    <span class="detail-label">Waktu:</span>
                    <span class="detail-value">
                        {{
                            selectedItem.Jam_Mulai?.split(":")
                                .slice(0, 2)
                                .join(":")
                        }}
                    </span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">
                        <el-tag
                            :type="
                                getStatusTagType(
                                    selectedItem.Status_Approval_Saat_Ini,
                                )
                            "
                            size="medium"
                        >
                            <span
                                v-if="
                                    selectedItem.Status_Approval_Saat_Ini
                                        ? selectedItem.Status_Approval_Saat_Ini?.trim() ===
                                          'Y'
                                        : false
                                "
                                class="text-success"
                                >DISETUJUI</span
                            >
                            <span
                                v-else-if="
                                    selectedItem.Status_Approval_Saat_Ini?.trim() ===
                                        'T' && selectedItem.status == 'Ditolak'
                                "
                                class="text-danger"
                                >DITOLAK</span
                            >
                            <span
                                v-else-if="
                                    selectedItem.Status_Approval_Saat_Ini ==
                                        'P' ||
                                    selectedItem.status == 'Ditolak sistem' ||
                                    isExpired(selectedItem.expired_at, selectedItem.status)
                                "
                                class="text-danger"
                                >Ditolak Sistem/Expired</span
                            >
                            <span v-else class="text-danger"
                                >Menunggu Keputusan</span
                            >
                        </el-tag>
                    </span>
                </div>

                <div class="detail-item full-width">
                    <span class="detail-label">Alasan:</span>
                    <span class="detail-value multiline">
                        {{ selectedItem.Alasan }}
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
                        <el-steps
                            :active="getActiveStep(selectedItem.STATUSLIST)"
                            align-center
                        >
                            <el-step
                                v-for="(data, index) in parsedDataArray(
                                    selectedItem.STATUSLIST,
                                )"
                                :key="index"
                                :title="data.name"
                                :status="getStepStatus(data.status, isExpired(selectedItem.expired_at, selectedItem.status))"
                            />
                        </el-steps>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div
                v-if="
                    selectedItem.Status_Approval_Saat_Ini == 'N' &&
                    selectedItem.status == 'Diproses' &&
                    !isExpired(selectedItem.expired_at, selectedItem.status)
                "
                class="modal-footer-actions"
            >
                <button
                    class="btn-modern btn-danger"
                    @click="
                        showConfirmNoModal(
                            'Tolak',
                            selectedItem.No_Transaksi,
                            selectedItem.Requester_Kode_Karyawan,
                            selectedItem.Approver_Kode_Karyawan,
                            selectedItem.Status_Approval_Saat_Ini,
                        )
                    "
                >
                    <i
                        class="bi bi-x fs-4 d-flex justify-content-center align-items-center me-1"
                    ></i>
                    Tolak
                </button>
                <button
                    class="btn-modern btn-success"
                    @click="
                        showConfirmYesModal(
                            'Terima',
                            selectedItem.No_Transaksi,
                            selectedItem.Requester_Kode_Karyawan,
                            selectedItem.Approver_Kode_Karyawan,
                            selectedItem.Status_Approval_Saat_Ini,
                        )
                    "
                    v-if="selectedItem"
                >
                    <i
                        class="bi bi-check fs-4 d-flex justify-content-center align-items-center me-1"
                    ></i>
                    Setuju
                </button>
            </div>
            <div v-else class="modal-footer-actions">
                <button
                    class="btn-modern btn-secondary"
                    @click="showDetailModal = false"
                >
                    Batal
                </button>
            </div>
        </template>
    </el-dialog>

    <!-- Picture Modal -->

    <!-- confirm Modal -->
    <el-dialog
        v-model="showConfirmModal"
        :title="decisionIzin === 'Terima' ? 'Konfirmasi Persetujuan' : 'Konfirmasi Penolakan'"
        width="440px"
        center
        align-center
        class="confirm-dialog"
        :lock-scroll="true"
    >
        <div class="confirm-modal-body text-center">
            <div class="confirm-icon-badge-wrapper mb-3">
                <div class="confirm-icon-badge" :class="decisionIzin === 'Terima' ? 'badge-success' : 'badge-danger'">
                    <i :class="decisionIzin === 'Terima' ? 'bi bi-check-lg' : 'bi bi-exclamation-triangle-fill'"></i>
                </div>
            </div>
            
            <p class="confirm-modal-message fs-5 fw-bold text-dark mb-2">
                {{ decisionIzin === 'Terima' ? 'Setujui Pengajuan Izin?' : 'Tolak Pengajuan Izin?' }}
            </p>
            
            <p class="confirm-modal-submessage text-secondary px-2 mb-3">
                Apakah Anda yakin ingin {{ decisionIzin === 'Terima' ? 'menyetujui' : 'menolak' }} pengajuan izin dengan nomor transaksi <strong>{{ confirmIzinId }}</strong>?
            </p>
            
            <div class="confirm-modal-warning-box mx-2" :class="decisionIzin === 'Terima' ? 'warning-box-success' : 'warning-box-danger'">
                <div class="d-flex align-items-center gap-2">
                    <i :class="decisionIzin === 'Terima' ? 'bi bi-info-circle-fill text-success' : 'bi bi-exclamation-circle-fill text-danger'"></i>
                    <span class="small fw-medium text-start" :class="decisionIzin === 'Terima' ? 'text-success-dark' : 'text-danger-dark'">
                        Aksi ini tidak dapat dibatalkan setelah diproses.
                    </span>
                </div>
            </div>

            <!-- Textarea Keterangan Penolakan -->
            <div v-if="decisionIzin === 'Tolak'" class="w-100 text-start mt-4 px-2">
                <label class="form-label fw-semibold text-dark mb-2">
                    Keterangan Penolakan <span class="text-danger">*</span>
                </label>
                <textarea
                    v-model="rejectionReason"
                    class="form-control modern-textarea"
                    rows="3"
                    maxlength="300"
                    placeholder="Masukkan alasan penolakan (misal: Tanggal tidak sesuai shift, dokumen tidak lengkap)..."
                ></textarea>
                <div class="d-flex justify-content-between mt-1 px-1">
                    <span class="text-danger small" v-if="!rejectionReason.trim()">
                        * Wajib diisi
                    </span>
                    <span v-else></span>
                    <span class="text-muted small character-counter" :class="{'text-danger': rejectionReason.length >= 300}">
                        {{ rejectionReason.length }}/300
                    </span>
                </div>
            </div>
        </div>
        <template #footer>
            <div class="modal-footer-actions w-100 d-flex justify-content-end gap-2 m-0 border-0">
                <button
                    class="btn-modern btn-secondary px-4 py-2"
                    @click="closeConfirmModal"
                >
                    Batal
                </button>
                <button
                    :disabled="loadingSimpan"
                    v-if="decisionIzin == 'Terima'"
                    class="btn-modern btn-success px-4 py-2"
                    @click="yesModal"
                >
                    <i
                        v-if="loadingSimpan"
                        class="spinner-border spinner-border-sm me-1"
                    ></i>
                    <i
                        v-else
                        class="bi bi-check-lg me-1 fs-5"
                    ></i>
                    {{ buttonAlertModal }}
                </button>
                <button
                    v-else
                    :disabled="loadingSimpan || !rejectionReason.trim()"
                    class="btn-modern btn-danger px-4 py-2"
                    @click="yesModal"
                >
                    <i
                        v-if="!loadingSimpan"
                        class="bi bi-x-lg me-1 fs-5"
                    ></i>
                    <i v-else class="spinner-border spinner-border-sm me-1"></i>
                    {{ buttonAlertModal }}
                </button>
            </div>
        </template>
    </el-dialog>

    <!-- Action Modal -->
    <div class="action-menu" v-if="showActionPopup" :style="actionMenuPosition">
        <div class="action-menu-item" @click="showDetail(selectedItem)">
            <i class="bi bi-eye"></i> Lihat Detail
        </div>
        <div class="action-menu-item" @click="editPermission(selectedItem)">
            <i class="bi bi-pencil"></i> Edit
        </div>
        <div
            class="action-menu-item text-danger"
            @click="showDeleteConfirm(selectedItem.No_Transaksi)"
        >
            <i class="bi bi-trash"></i> Hapus
        </div>
        <div class="action-menu-divider"></div>
        <div class="action-menu-item" @click="showActionPopup = false">
            <i class="bi bi-x"></i> Tutup
        </div>
    </div>

    <div
        class="action-menu-backdrop"
        v-if="showActionPopup"
        @click="showActionPopup = false"
    ></div>

    <!-- Modal Assign -->
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
                                </div>

                                <div class="col-12 col-md-3">
                                    <div
                                        class="permission-type-card"
                                        :class="{
                                            selected:
                                                assignIzinType === 'sakit',
                                        }"
                                        @click="handleAssignIzin('sakit')"
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
                                                    assignIzinType === 'sakit'
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
                                assignIzinType == 'sakit'
                            "
                            @click="openDialog"
                            class="form-group d-flex cursor-pointer justify-content-start align-items-center"
                        >
                            <div
                                class="form-control d-flex align-items-center justify-content-center cursor-pointer"
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
                                            ? formatDateString(
                                                  AssignDateRange[0],
                                              )
                                            : formatDateString(
                                                  AssignDateRange[0],
                                              ) +
                                              " s.d " +
                                              formatDateString(
                                                  AssignDateRange[1],
                                              )
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
                                class="form-control d-flex align-items-center justify-content-center cursor-pointer"
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
                                assignIzinType === 'terlambat'
                            "
                        >
                            <label class="form-label">
                                {{
                                    assignIzinType === "pulangCepat"
                                        ? "Waktu Pulang"
                                        : "Waktu Datang"
                                }}
                            </label>
                            <div class="input-group px-2 px-md-3 mb-3">
                                <el-slider
                                    @change="console.log(AssignTime)"
                                    v-model="AssignTime"
                                    :min="min"
                                    :max="max"
                                    :step="0.5"
                                    :marks="jamOptions"
                                    :disabled="!max"
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
                            <i
                                class="bi bi-paperclip me-2 d-flex justify-content-center align-items-center"
                            ></i>
                            Lampiran
                        </h4>

                        <!-- File Upload for Sick Leave -->
                        <div
                            class="form-group"
                            v-if="assignIzinType === 'sakit'"
                        >
                            <label class="form-label"
                                >Surat Keterangan Dokter (Wajib)</label
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
                                    v-if="!AssignFile"
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
                                        AssignFile.name
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
                        <div class="form-group" v-else>
                            <label class="form-label"
                                >Foto Atau File Tambahan (Optional)</label
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
                                    v-if="!AssignFile"
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
                                        AssignFile.name
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
                        <!-- <div v-else class="text-muted text-center py-4">
                            Tidak ada lampiran yang diperlukan untuk jenis izin
                            ini.
                        </div> -->
                    </div>
                </div>
                <div v-if="assignStep === 4" class="step-content">
                    <div class="form-section">
                        <h4 class="section-title">
                            <i
                                class="bi bi-gear me-2 d-flex justify-content-center align-items-center"
                            ></i>
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
                                    <span class="m-0 fw-medium">
                                        Jenis Izin</span
                                    >
                                </span>
                                <div style="color: #6366f1" class="fw-bold">
                                    {{ parseIzin(assignIzinType) }}
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
                                <div
                                    style="color: #6366f1"
                                    class="fw-bold"
                                ></div>
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
                                    {{ permissionReason }}
                                </div>
                            </div>
                            <div
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
                                        assignIzinType == "izinFull" ||
                                        assignIzinType == "sakit"
                                            ? "Tidak ada"
                                            : formatTimeForSubmission(
                                                  AssignTime,
                                              )
                                                  .split(":")
                                                  .slice(0, 2)
                                                  .join(":")
                                    }}
                                </div>
                            </div>
                            <div
                                class="d-flex justify-content-between pb-2 align-items-center mb-1"
                            >
                                <span class="d-flex align-items-center fs-6">
                                    <i
                                        class="bi bi-textarea me-2 fw-bold d-flex justify-content-center align-items-center"
                                    ></i>
                                    <span class="m-0 fw-medium">Lampiran</span>
                                </span>
                                <div style="color: #6366f1" class="fw-bold">
                                    {{
                                        AssignFile
                                            ? AssignFile.name
                                            : "Tidak ada"
                                    }}
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
                        v-if="assignStep === 4"
                        class="btn-modern btn-success"
                        @click="submitData"
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

    <!-- Calendar Single -->
    <el-dialog
        v-model="dialogVisible"
        title="Pilih Tanggal Mulai dan Selesai"
        width="480px"
        align-center
        :lock-scroll="true"
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
                    class="shiningEffect btn-success py-0 rounded-3 px-1 py-1"
                    :disabled="!tempEndDate"
                    @click="confirmDate('range')"
                >
                    Pilih Tanggal
                </el-button>
            </div>
        </template>
    </el-dialog>

    <!-- Calendar Range -->
    <el-dialog
        v-model="dialogVisibleSingle"
        title="Pilih Tanggal"
        width="480px"
        align-center
        :lock-scroll="true"
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
                    class="shiningEffect btn-success py-0 rounded-3 px-1 py-1"
                    :disabled="!tempEndDate"
                    @click="confirmDate('single')"
                >
                    Pilih Tanggal
                </el-button>
            </div>
        </template>
    </el-dialog>

    <!-- Delete Modal -->
    <el-dialog
        v-model="showDeleteModal"
        title="Konfirmasi Hapus"
        width="400px"
        center
        align-center
        class="confirm-dialog"
        :lock-scroll="true"
    >
        <div class="delete-modal-content">
            <i class="bi bi-exclamation-triangle warning-icon"></i>
            <p>
                Anda yakin ingin Mensetujui pengajuan izin
                <strong>{{ deleteItemId }}</strong
                >?
            </p>
            <p class="text-muted">Aksi ini tidak dapat dibatalkan.</p>
        </div>
        <template #footer>
            <div class="delete-modal-footer">
                <button
                    class="btn-modern btn-secondary"
                    @click="showDeleteModal = false"
                >
                    Batal
                </button>
                <button class="btn-modern btn-danger" @click="confirmDelete">
                    <i
                        class="bi bi-trash d-flex justify-content-center align-items-center"
                    ></i>
                    Simpan
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
    >
        <el-calendar v-model="calendarDateExport">
            <template #date-cell="{ data }">
                <div
                    class="cell-date-wrapper"
                    :class="getCellClassExport(data)"
                    @click="handleDateClickExport(data)"
                >
                    <div class="date-cell">
                        {{ data.day.split("-")[2] }}
                    </div>
                </div>
            </template>
        </el-calendar>

        <template #footer>
            <div class="dialog-footer gap-3 d-flex justify-content-between">
                <div>
                    <div
                        class="px-2 rounded d-flex justify-content-center align-items-center bg-primary"
                    >
                        <div v-if="!tempStartDateExport" class="text-white">
                            Pilih Tanggal Mulai
                        </div>
                        <div
                            v-if="tempStartDateExport && !tempEndDateExport"
                            class="text-white"
                        >
                            Pilih Tanggal Selesai
                        </div>
                        <div
                            v-if="tempStartDateExport && tempEndDateExport"
                            class="text-white"
                        >
                            {{
                                formatTanggalTemp(
                                    tempStartDateExport,
                                    tempEndDateExport,
                                )
                            }}
                        </div>
                    </div>
                    <div
                        style="font-size: 0.8rem"
                        class="text-danger text-start"
                    >
                        Klik 2 kali jika ingin 1 hari!
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <el-button
                        style="cursor: pointer !important"
                        @click="handleCancelExport"
                        class="btn-secondary p-1 rounded-3"
                        >Batal</el-button
                    >
                    <el-button
                        style="cursor: pointer !important"
                        class="btn-modern btn-success py-1 rounded-3 px-1"
                        :disabled="!tempEndDateExport"
                        @click="showAlertModalFC('export', 'Download')"
                    >
                        Pilih Tanggal
                    </el-button>
                </div>
            </div>
        </template>
    </el-dialog>

    <!-- alert modal -->
    <el-dialog
        v-model="showAlertModal"
        title="Konfirmasi menyimpan data"
        width="400px"
        center
        align-center
        class="confirm-dialog"
        :lock-scroll="true"
    >
        <div class="delete-modal-content">
            <i class="bi bi-exclamation-triangle warning-icon"></i>
            <p>
                {{ Pesan }}
            </p>
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

<script scope>
import axios from "axios";
import {
    ElDialog,
    ElCalendar,
    ElMessage,
    ElSteps,
    ElTag,
    ElStep,
    ElTooltip,
    ElNotification,
    ElSlider,
} from "element-plus";
import "element-plus/dist/index.css";
import { debounce } from "lodash";
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import { escapeHtml } from '@/utils/html';
export default {
    components: {
        ElDialog,
        ElCalendar,
        ElMessage,
        ElSteps,
        ElStep,
        ElTooltip,
        ElTag,
        ElNotification,
        ElSlider,
        DotLottieVue,
    },
    props: {
        dataShift: Array,
        namaKaryawan: String,
    },
    data() {
        return {
            totalData: 0,
            tipeFilter: null,
            tanggalFilter: null,
            nextLink: null,
            prevLink: null,
            totalPages: 0,

            dataExport: [],
            showAlertModal: false,
            Pesan: "",
            buttonAlert: "",
            headerAlert: "",
            fnc: null,

            dialogVisibleExport: false,
            selectedDateExport: [],
            tempStartDateExport: null,
            tempEndDateExport: null,
            calendarDateExport: new Date(),

            filterDropdown: "",
            approvalStatusFilter: "",

            dialogFilter: false,
            selectedDateFilter: null,
            tempStartDateFilter: null,

            loadingSimpan: false,
            titleAlert: "",
            confirmIzinId: null,
            decisionIzin: null,
            rejectionReason: "",
            requesterConfirm: null,
            approverConfirm: null,
            buttonConfirmModal: null,
            iconConfirm: "",
            showConfirmModal: false,
            yesModal: null,

            showPictureModal: false,
            showDetailModal: false,
            selectedItem: null,
            showDeleteModal: false,
            deleteItemId: null,
            showActionPopup: false,
            actionMenuPosition: { top: "0", left: "0" },
            showSearchSuggestions: false,
            searchSuggestions: [
                "Izin Sakit",
                "Izin Terlambat",
                "Pulang Cepat",
                "Izin Pribadi",
            ],
            viewMode: "table",
            assignStep: 1,
            loading: true,
            searchQuery: "",
            currentPage: 1,
            itemsPerPage: 8,
            currentDate: new Date(),
            showAssignModal: false,
            dayNight: "Siang",
            permissionRecords: [],

            min: null,
            max: null,
            jamOptions: [],

            assignIzinType: null,
            AssignDateRange: null,
            AssignTime: null,
            permissionReason: "",
            AssignFile: null,

            dialogVisible: false,
            dialogVisibleSingle: false,
            tempStartDate: null,
            tempEndDate: null,

            DataIzin: null,

            loadingAttachment: false,
            min: null,
            max: null,
            timeMarks: {
                8: "08",
                9: "09",
                10: "10",
                11: "11",
                12: "12",
                13: "13",
                14: "14",
                15: "15",
                16: "16",
            },
            windowWidth: window.innerWidth, // For responsive design checks
            approvalData: ["M.REZA: Y, RIDHO RAHMAT: T, BUDI SANTOSO: N"],
        };
    },
    computed: {
        sortedPermissionRecords() {
            return [...this.permissionRecords].sort((a, b) => {
                const aPending = a.Status_Approval_Saat_Ini === 'N' && a.status === 'Diproses' && !this.isExpired(a.expired_at, a.status);
                const bPending = b.Status_Approval_Saat_Ini === 'N' && b.status === 'Diproses' && !this.isExpired(b.expired_at, b.status);
                // Prioritas: pending & belum expired di atas
                if (aPending && !bPending) return -1;
                if (!aPending && bPending) return 1;
                // Pending: sort by submission date (newest first)
                if (aPending) {
                    return new Date(b.tanggal_create || 0) - new Date(a.tanggal_create || 0);
                }
                // Non-pending: sort by approval date (newest first), fallback submission date
                const aApproval = new Date(a.tanggal_approval || a.tanggal_create || 0);
                const bApproval = new Date(b.tanggal_approval || b.tanggal_create || 0);
                return bApproval - aApproval;
            });
        },
        parsedData() {
            const allApprovals = this.approvalData.join(", ").split(", ");
            return allApprovals.map((item) => {
                const [name, status] = item.split(": ");
                return { name: name.trim(), status: status.trim() };
            });
        },
        tooltipContent() {
            const totalApprovals = this.parsedData.length;
            const approvedCount = this.parsedData.filter(
                (item) => item.status === "Y" || item.status === "T",
            ).length;
            const rejectedCount = this.parsedData.filter(
                (item) => item.status === "T",
            ).length;
            const progressCount = this.parsedData.filter(
                (item) => item.status === "N",
            ).length;
            const approvalRate = Math.round(
                (approvedCount / totalApprovals) * 100,
            );

            // Find active step
            const firstRejected = this.parsedData.findIndex(
                (item) => item.status === "T",
            );
            const firstPending = this.parsedData.findIndex(
                (item) => item.status === "N",
            );
            const activeIndex =
                firstRejected >= 0
                    ? firstRejected
                    : firstPending >= 0
                      ? firstPending
                      : -1;

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
                    const isApproved = item.status === "Y";
                    const isRejected = item.status === "T";
                    const isExpired = item.status === "P";
                    const isPending = item.status === "N";

                    return `
                    <div class="approval-step ${isActive ? "active-step" : ""}">
                        <div class="step-icon ${
                            isApproved
                                ? "approved"
                                : isRejected
                                  ? "rejected"
                                  : isExpired
                                    ? "expired"
                                    : isPending
                                      ? "pending"
                                      : "waiting"
                        }">
                            ${
                                isApproved
                                    ? "✓"
                                    : isRejected
                                      ? "✕"
                                      : isExpired
                                        ? "❌"
                                        : isPending
                                          ? "⏳"
                                          : "?"
                            }
                        </div>
                        <div class="step-details">
                            <div class="step-name">${escapeHtml(item.name)}</div>
                            <div class="step-status ${
                                isApproved
                                    ? "approved"
                                    : isRejected
                                      ? "rejected"
                                      : isExpired
                                        ? "expired"
                                        : isPending
                                          ? "pending"
                                          : "waiting"
                            }">
                                ${
                                    isApproved
                                        ? "Disetujui"
                                        : isRejected
                                          ? "Ditolak"
                                          : isExpired
                                            ? "Ditolak sistem"
                                            : isPending
                                              ? "Dalam Proses"
                                              : "Menunggu"
                                }
                            </div>
                        </div>
                        ${isActive ? '<div class="step-indicator"></div>' : ""}
                    </div>
                `;
                })
                .join("")}
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
        .expired {
            background: linear-gradient(135deg, #f472b5 0%, var(--hcis-pink-500) 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(244, 114, 182, 0.3);
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
        filteredSuggestions() {
            if (!this.searchQuery) {
                return this.searchSuggestions;
            }
            return this.searchSuggestions.filter((suggestion) =>
                suggestion
                    .toLowerCase()
                    .includes(this.searchQuery.toLowerCase()),
            );
        },
        // filteredPermissions() {
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
        //             if (isNaN(selectedDate)) return false;
        //             selectedDate.setHours(0, 0, 0, 0);

        //             const { startDate, endDate } = this.parseTanggalIzin(
        //                 item.tanggalReal
        //             );
        //             if (!startDate) return false;

        //             const result = endDate
        //                 ? selectedDate >= startDate && selectedDate <= endDate
        //                 : selectedDate.getTime() === startDate.getTime();

        //             console.log("DEBUG:", {
        //                 selected: selectedDate,
        //                 start: startDate,
        //                 end: endDate,
        //                 hasil: result,
        //                 raw: item.tanggalReal,
        //             });

        //             return result;
        //         });
        //     }

        //     return filtered;
        // },
        // totalPages() {
        //     return Math.ceil(
        //         this.filteredPermissions.length / this.itemsPerPage
        //     );
        // },
        // paginatedPermissions() {
        //     const start = (this.currentPage - 1) * this.itemsPerPage;
        //     const end = start + this.itemsPerPage;
        //     return this.filteredPermissions.slice(start, end);
        // },
        // visiblePages() {
        //     const maxVisible = 5;
        //     const half = Math.floor(maxVisible / 2);
        //     let start = Math.max(1, this.currentPage - half);
        //     let end = Math.min(this.totalPages, start + maxVisible - 1);

        //     if (end - start + 1 < maxVisible) {
        //         start = Math.max(1, end - maxVisible + 1);
        //     }

        //     return Array.from({ length: end - start + 1 }, (_, i) => start + i);
        // },

        assignSelectedShift() {
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
        approvalStatusFilter() {
            this.debouncedSearch();
        },
        selectedDateFilter() {
            this.debouncedSearch();
        },
    },
    methods: {
        isExpired(expiredAt, status = null) {
            if (!expiredAt) return false;
            // Transaksi yang sudah final (disetujui/ditolak) TIDAK pernah dianggap expired,
            // walau batas waktu approval kebetulan sudah lewat. Sumber kebenaran = status.
            if (status) {
                const s = String(status).toLowerCase();
                if (['selesai', 'disetujui', 'approved', 'ditolak', 'rejected'].includes(s)) {
                    return false;
                }
            }
            return new Date(expiredAt) < new Date();
        },
        parseTanggalIzin(str) {
            if (!str) return { startDate: null, endDate: null };

            // Mapping bulan
            const monthMap = {
                January: 0,
                February: 1,
                March: 2,
                April: 3,
                May: 4,
                June: 5,
                July: 6,
                August: 7,
                September: 8,
                October: 9,
                November: 10,
                December: 11,
            };

            // Case rentang: "23-26 July 2025"
            const rangeMatch = str.match(
                /^(\d{1,2})-(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/,
            );
            if (rangeMatch) {
                const [, startDay, endDay, monthName, year] = rangeMatch;
                const month = monthMap[monthName];
                const startDate = new Date(year, month, parseInt(startDay));
                const endDate = new Date(year, month, parseInt(endDay));

                startDate.setHours(0, 0, 0, 0);
                endDate.setHours(0, 0, 0, 0);

                return { startDate, endDate };
            }

            // Case single date: "28 August 2025 08:30"
            const singleMatch = str.match(/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/);
            if (singleMatch) {
                const [, day, monthName, year] = singleMatch;
                const month = monthMap[monthName];
                const d = new Date(year, month, parseInt(day));
                d.setHours(0, 0, 0, 0);

                return { startDate: d, endDate: null };
            }

            // Default gagal
            return { startDate: null, endDate: null };
        },
        formatDateToYYYYMMDD(date) {
            const d = new Date(date);
            const year = d.getFullYear();
            // getMonth() adalah 0-indexed, jadi tambahkan 1
            const month = (d.getMonth() + 1).toString().padStart(2, "0");
            const day = d.getDate().toString().padStart(2, "0");
            return `${year}-${month}-${day}`;
        },
        openDialogExport() {
            if (this.selectedDateExport.length === 2) {
                const [startStr, endStr] = this.selectedDateExport;
                this.tempStartDateExport = new Date(startStr + "T00:00:00");
                this.tempEndDateExport = new Date(endStr + "T00:00:00");
                this.calendarDateExport = new Date(startStr + "T00:00:00");
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
                const response = await axios.get("/izin/getExportDH", {
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
                this.closeAlertModal();
            } finally {
                this.closeAlertModal();
                this.handleCancelExport();
                this.loading = false;
            }
        },
        async exportAbsensiExcel() {
            const ExcelJS = await import("exceljs");
            const workbook = new ExcelJS.Workbook();
            const worksheet = workbook.addWorksheet("Izin Export");

            try {
                const headerStyle = {
                    fill: {
                        type: "pattern",
                        pattern: "solid",
                        fgColor: { argb: "FFEB9C" },
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

                const parseIzin = (tipe) => {
                    if (tipe === "izinFull") return "Izin Pribadi";
                    else if (tipe === "sakit") return "Izin Sakit";
                    else if (tipe === "pulangCepat") return "Pulang Cepat";
                    else if (tipe === "terlambat") return "Izin Terlambat";
                    else if (["cuti", "CUTI", "cutiHutang"].includes(tipe))
                        return "Cuti";

                    return tipe;
                };

                // Judul
                worksheet.mergeCells("A1:H1");
                worksheet.getCell("A1").value =
                    `Export Data Izin Approver ${this.capitalize(
                        this.namaKaryawan,
                    )}`;
                worksheet.getCell("A1").style = headerStyle;

                // Header
                const headers = [
                    "No. Transaksi",
                    "Nama Pengaju",
                    "Jenis Izin",
                    "Jam",
                    "Tanggal Mulai",
                    "Tanggal Selesai",
                    "Alasan",
                    "Status",
                ];
                const headerRow = worksheet.getRow(2);
                headerRow.values = headers;
                headerRow.eachCell((cell) => {
                    cell.style = headerStyle;
                });

                worksheet.columns = [
                    { key: "noTransaksi", width: 20 },
                    { key: "nama", width: 25 },
                    { key: "jenisIzin", width: 20 },
                    { key: "jam", width: 15 },
                    { key: "tglMulai", width: 15 },
                    { key: "tglSelesai", width: 15 },
                    { key: "alasan", width: 40 },
                    { key: "status", width: 30 },
                ];

                let rowIndex = 3;
                const today = new Date();

                this.dataExport.forEach((user) => {
                    const row = worksheet.getRow(rowIndex);

                    let tglMulai = "-";
                    let tglSelesai = "Tidak ada";
                    let jam = "-";

                    // Parsing tanggal izin
                    if (
                        user.Tipe_Izin === "izinFull" ||
                        user.Tipe_Izin === "sakit" ||
                        user.Tipe_Izin === "cuti"
                    ) {
                        if (user.tanggalReal?.includes("-")) {
                            // contoh: "18-20 August 2025"
                            const parts = user.tanggalReal.split(" ");
                            if (parts.length >= 3 && parts[0].includes("-")) {
                                const [start, end] = parts[0].split("-");
                                tglMulai =
                                    start + " " + parts[1] + " " + parts[2];
                                tglSelesai =
                                    end + " " + parts[1] + " " + parts[2];
                            } else {
                                tglMulai = user.tanggalReal;
                            }
                        } else {
                            tglMulai = user.tanggalReal;
                        }
                    } else {
                        // contoh: "26 August 2025 14:00"
                        const parts = user.tanggalReal?.split(" ");
                        if (parts?.length >= 4) {
                            jam = parts[3];
                            tglMulai =
                                parts[0] + " " + parts[1] + " " + parts[2];
                        } else {
                            tglMulai = user.tanggalReal || "-";
                        }
                    }

                    // Cek status
                    let statusFinal = user.status;
                    if (
                        user.status?.toLowerCase() === "ditolak" &&
                        user.Approver?.toLowerCase() === "sistem"
                    ) {
                        statusFinal = "Ditolak oleh Sistem (Expired)";
                    } else {
                        // Cek expired lebih dari 2 hari
                        try {
                            const izinDate = new Date(tglMulai);
                            const diffDays = Math.floor(
                                (today - izinDate) / (1000 * 60 * 60 * 24),
                            );
                            if (
                                diffDays > 2 &&
                                user.status?.toLowerCase() === "diproses"
                            ) {
                                statusFinal = "Ditolak oleh Sistem (Expired)";
                            }
                        } catch (e) {
                            console.warn(
                                "Tanggal izin tidak valid:",
                                user.tanggalReal,
                            );
                        }
                    }

                    row.values = {
                        noTransaksi: user.No_Transaksi,
                        nama: user.requester_Nama,
                        jenisIzin: parseIzin(user.Tipe_Izin),
                        jam,
                        tglMulai,
                        tglSelesai,
                        alasan: user.Alasan || "-",
                        status: statusFinal,
                    };

                    row.eachCell((cell) => {
                        cell.style = cellStyle;
                    });
                    rowIndex++;
                });

                const buffer = await workbook.xlsx.writeBuffer();
                const blob = new Blob([buffer], {
                    type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                });
                const link = document.createElement("a");
                link.href = URL.createObjectURL(blob);
                link.download = `Export_Izin_${new Date()
                    .toISOString()
                    .slice(0, 10)}.xlsx`;
                link.click();
                URL.revokeObjectURL(link.href);
            } catch (error) {
                console.error(`Terjadi Error di : ${error}`);
            } finally {
                this.loading = false;
            }
        },
        showAlertModalFC(jenis, button) {
            if (jenis == "export") {
                this.Pesan = `Anda yakin ingin mendownload data izin ${this.formatTanggalTemp(
                    this.tempStartDateExport,
                    this.tempEndDateExport,
                )} `;
                this.buttonAlert = button;
                this.headerAlert = "Download Export data!";
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
                if (
                    clickedDate.getTime() < this.tempStartDateExport.getTime()
                ) {
                    this.tempStartDateExport = clickedDate;
                } else {
                    this.tempEndDateExport = clickedDate;
                }
            }
        },
        getCellClassExport(data) {
            const classes = {};
            const dateObj = new Date(data.date);
            const formattedDate = this.formatDateToYYYYMMDD(dateObj); // Menggunakan fungsi pembantu
            const checkDate = new Date(formattedDate);
            checkDate.setHours(0, 0, 0, 0);

            // Logika untuk rentang tanggal yang dipilih
            const start = this.tempStartDateExport
                ? this.tempStartDateExport.setHours(0, 0, 0, 0)
                : null;
            const end = this.tempEndDateExport
                ? this.tempEndDateExport.setHours(0, 0, 0, 0)
                : null;

            if (start && checkDate.getTime() === start)
                classes["is-start"] = true;
            if (end && checkDate.getTime() === end) classes["is-end"] = true;
            if (start && end && checkDate > start && checkDate < end) {
                classes["is-in-range"] = true;
            }

            // Logika untuk hari Minggu (Weekend)
            const dayOfWeek = dateObj.getDay(); // 0 = Minggu
            if (dayOfWeek === 0) {
                classes["is-red-date"] = true;
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
                "Januari",
                "Februari",
                "Maret",
                "April",
                "Mei",
                "Juni",
                "Juli",
                "Agustus",
                "September",
                "Oktober",
                "November",
                "Desember",
            ];

            if (!dateStart) return "";

            let start = new Date(dateStart);
            let end = dateEnd ? new Date(dateEnd) : null;

            // Kalau hanya 1 tanggal
            if (!end) {
                return `${start.getDate()} ${
                    bulan[start.getMonth()]
                } ${start.getFullYear()}`;
            }

            // Kalau bulan & tahun sama
            if (
                start.getMonth() === end.getMonth() &&
                start.getFullYear() === end.getFullYear()
            ) {
                return `${start.getDate()}-${end.getDate()} ${
                    bulan[start.getMonth()]
                } ${start.getFullYear()}`;
            }

            // Kalau bulan atau tahun beda
            return `${start.getDate()} ${
                bulan[start.getMonth()]
            } ${start.getFullYear()} - ${end.getDate()} ${
                bulan[end.getMonth()]
            } ${end.getFullYear()}`;
        },
        capitalize(value) {
            if (!value) return "";
            const words = value.split(" ");
            const capitalizedWords = words.map((word) => {
                if (!word) return "";
                return (
                    word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
                );
            });
            return capitalizedWords.join(" ");
        },
        pisahTanggal(tanggalIzin) {
            if (!tanggalIzin) return null;

            // 1. Handle date with time (18 July 2025 18:30)
            const dateWithTimeMatch = tanggalIzin.match(
                /^(\d{1,2}) ([A-Za-z]+) (\d{4}) (\d{1,2}):(\d{2})$/,
            );
            if (dateWithTimeMatch) {
                const [, day, month, year, hours, minutes] = dateWithTimeMatch;
                const dateStr = `${month} ${day}, ${year} ${hours}:${minutes}`;
                const date = new Date(dateStr);

                if (!isNaN(date.getTime())) {
                    return {
                        startDate: new Date(date),
                        endDate: new Date(date),
                    };
                }
            }

            // 2. Handle single date without time (18 July 2025)
            const singleDateMatch = tanggalIzin.match(
                /^(\d{1,2}) ([A-Za-z]+) (\d{4})$/,
            );
            if (singleDateMatch) {
                const [, day, month, year] = singleDateMatch;
                const dateStr = `${month} ${day}, ${year}`;
                const date = new Date(dateStr);

                if (!isNaN(date.getTime())) {
                    return {
                        startDate: new Date(date.setHours(0, 0, 0, 0)),
                        endDate: new Date(date.setHours(23, 59, 59, 999)),
                    };
                }
            }

            // 3. Handle date range (16-24 July 2025)
            const rangeMatch = tanggalIzin.match(
                /^(\d{1,2})-(\d{1,2}) ([A-Za-z]+) (\d{4})$/,
            );
            if (rangeMatch) {
                const [, startDay, endDay, month, year] = rangeMatch;
                const startStr = `${month} ${startDay}, ${year}`;
                const endStr = `${month} ${endDay}, ${year}`;

                const startDate = new Date(startStr);
                const endDate = new Date(endStr);

                if (!isNaN(startDate.getTime()) && !isNaN(endDate.getTime())) {
                    startDate.setHours(0, 0, 0, 0);
                    endDate.setHours(23, 59, 59, 999);
                    return { startDate, endDate };
                }
            }

            console.error("Unrecognized date format:", tanggalIzin);
            return null;
        },
        parseStatus(status, app) {
            let nama = app.split(" ").slice(0, 1).join(" ");
            if (status == "Ditolak") {
                return `Ditolak oleh ${nama}`;
            } else if (status == "Selesai") {
                return "Disetujui";
            } else if (status == "Ditolak Sistem") {
                return `Ditolak oleh Sistem (Expired)`;
            } else {
                return `Diproses oleh ${nama}`;
            }
        },
        getStatusTagType(status, approver) {
            if (status === null) return "warning";
            if (status === "Y") return "success";
            if (status === "T" || status === "P") return "danger";
            return "info";
        },

        getActiveStep(statusList) {
            const parsed = this.parsedDataArray(statusList);
            const lastApprovedIndex = parsed.findLastIndex(
                (item) =>
                    item.status === "Y" ||
                    item.status === "T" ||
                    item.status === "P",
            );
            return lastApprovedIndex + 1;
        },

        getStepStatus(status, isExpired = false) {
            if (status === "Y") return "success";
            if (status === "T" || status === "P") return "error";
            if (isExpired) return "error";
            return "wait";
        },

        getStepIcon(status) {
            if (status === "Y") return "el-icon-check";
            if (status === "T") return "el-icon-close";
            return "el-icon-more";
        },
        parsedDataArray(data) {
            const allApprovals = data.split(", ");
            return allApprovals.map((item) => {
                const [name, status] = item.split(": ");
                return {
                    name: name.split(" ").slice(0, 1).join(" ").trim(),
                    status: status.trim(),
                };
            });
        },
        async viewAttachment(attachmentPath) {
            if (!attachmentPath) {
                ElMessage({
                    message: "Tidak ada lampiran untuk ditampilkan.",
                    type: "warning",
                });
                return;
            }

            this.loadingAttachment = true;

            try {
                const response = await axios.post(
                    "/izin/getAttachmentUrl",
                    {
                        path: attachmentPath,
                    },
                    {
                        responseType: "blob", // Penting untuk file download
                    },
                );

                if (response.status === 200) {
                    // Buat blob URL untuk download
                    const blob = new Blob([response.data]);
                    const url = window.URL.createObjectURL(blob);

                    // Extract filename dari response headers atau path
                    const fileName = attachmentPath.substring(
                        attachmentPath.lastIndexOf("/") + 1,
                    );

                    // Trigger download
                    const downloadLink = document.createElement("a");
                    downloadLink.href = url;
                    downloadLink.download = fileName;
                    document.body.appendChild(downloadLink);
                    downloadLink.click();
                    document.body.removeChild(downloadLink);

                    // Cleanup
                    window.URL.revokeObjectURL(url);

                    ElMessage({
                        message: "File berhasil didownload.",
                        type: "success",
                    });
                }
            } catch (error) {
                console.error("Error downloading file:", error);
                ElMessage({
                    message: "Terjadi kesalahan saat mendownload file.",
                    type: "error",
                });
            } finally {
                this.loadingAttachment = false;
            }
        },
        maknaStatus(status) {
            if (status === "Y") {
                return "Disetujui";
            } else if (
                status === null ||
                status === undefined ||
                status === "N"
            ) {
                return "Menunggu Persetujuan";
            } else if (status === "T") {
                return "Ditolak";
            } else if (status === "P") {
                return "Ditolak sistem";
            }
            return "";
        },
        showConfirmYesModal(
            decision,
            No_Transaksi,
            requester,
            approver,
            flag_approval,
        ) {
            if (flag_approval == "P") {
                ElMessage({
                    message:
                        "Pengajuan izin sudah Expired, tidak dapat menyetujui.",
                    type: "warning",
                });
                return;
            } else if (flag_approval == "T") {
                ElMessage({
                    message:
                        "Pengajuan izin sudah ditolak, tidak dapat menyetujui.",
                    type: "warning",
                });
                return;
            } else if (flag_approval == "Y") {
                ElMessage({
                    message:
                        "Pengajuan izin sudah disetujui, tidak dapat menyetujui kembali.",
                    type: "warning",
                });
                return;
            }
            this.titleAlert = "Anda yakin ingin menerima pengajuan izin";
            this.confirmIzinId = No_Transaksi;
            this.decisionIzin = decision;
            this.requesterConfirm = requester;
            this.approverConfirm = approver;
            this.buttonAlertModal = "Setuju";
            this.iconConfirm = "bi bi-check-circle success-icon";
            this.yesModal = () => this.confirmIzin();
            this.showConfirmModal = true;
        },
        showConfirmNoModal(
            decision,
            No_Transaksi,
            requester,
            approver,
            flag_approval,
        ) {
            if (flag_approval == "P") {
                ElMessage({
                    message:
                        "Pengajuan izin sudah Expired, tidak dapat menyetujui.",
                    type: "warning",
                });
                return;
            } else if (flag_approval == "T") {
                ElMessage({
                    message:
                        "Pengajuan izin sudah ditolak, tidak dapat menyetujui.",
                    type: "warning",
                });
                return;
            } else if (flag_approval == "Y") {
                ElMessage({
                    message:
                        "Pengajuan izin sudah disetujui, tidak dapat menyetujui kembali.",
                    type: "warning",
                });
                return;
            }
            this.titleAlert = "Anda yakin ingin Menolak pengajuan izin";
            this.confirmIzinId = No_Transaksi;
            this.decisionIzin = decision;
            this.requesterConfirm = requester;
            this.approverConfirm = approver;
            this.buttonAlertModal = "Tolak";
            this.iconConfirm = "bi bi-exclamation-triangle warning-icon";
            this.rejectionReason = "";
            this.yesModal = () => this.confirmIzin();
            this.showConfirmModal = true;
        },
        closeConfirmModal() {
            this.showConfirmModal = false;
            this.titleAlert = "";
            this.confirmIzinId = null;
            this.requesterConfirm = null;
            this.approverConfirm = null;
            this.buttonAlertModal = null;
            this.iconConfirmModal = "";
            this.decisionIzin = null;
            this.rejectionReason = "";
            this.yesModal = null;
        },
        async confirmIzin() {
            // Klik kedua sebelum render ulang tombol masih lolos dari :disabled,
            // dan request kembar selalu gagal di guard state server.
            if (this.loadingSimpan) return;

            try {
                this.loadingSimpan = true;
                const formData = new FormData();
                formData.append("No_Transaksi", this.confirmIzinId);
                formData.append("tipeConfirm", this.decisionIzin);
                formData.append("requester", this.requesterConfirm);
                formData.append("approver", this.approverConfirm);
                if (this.decisionIzin == "Tolak") {
                    formData.append("catatan", this.rejectionReason.trim());
                }

                const response = await axios.post(
                    // "/izin/confirmIzin",
                    "/api/v1/izin-atasan-approval/approveDH",
                    formData,
                );

                if (response.status === 200) {
                    ElNotification({
                        title: "Success",
                        message:
                            response.data.message ||
                            "Konfirmasi izin berhasil!",
                        type: "success",
                    });
                    this.getData();
                } else {
                    ElMessage({
                        message:
                            response.data.message ||
                            "Terjadi kesalahan saat konfirmasi izin.",
                        type: "warning",
                    });
                }
            } catch (error) {
                console.error("Error in ConfirmIzin:", error);

                let errorMessage =
                    "Terjadi kesalahan saat menyimpan, silakan coba lagi.";
                if (
                    error.response &&
                    error.response.data &&
                    error.response.data.message
                ) {
                    errorMessage = error.response.data.message;
                }

                // 409 = giliran/status pengajuan sudah berubah sejak baris ini dimuat.
                // Tarik ulang datanya supaya tombol aksi yang basi ikut hilang.
                const isStale = error.response && error.response.status === 409;

                ElMessage({
                    message: errorMessage,
                    type: isStale ? "warning" : "error",
                    customStyle: {
                        "z-index": "1050",
                    },
                });

                if (isStale) {
                    this.getData();
                }
            } finally {
                this.showDetailModal = false;
                this.showConfirmModal = false;
                this.loadingSimpan = false;
            }
        },
        konfirmApprov() {
            // console.log(object);
        },
        parseIzin(tipe) {
            if (tipe == "izinFull") {
                return "Izin Pribadi";
            } else if (tipe == "sakit") {
                return "Izin Sakit";
            } else if (tipe == "pulangCepat") {
                return "Izin Pulang Cepat";
            } else if (tipe == "terlambat") {
                return "Izin Terlambat";
            } else if (tipe == "cuti") {
                return "Cuti";
            } else if (tipe == "cutiHutang") {
                return "Cuti";
            } else {
                return tipe;
            }
        },
        async getJamKerja() {
            try {
                const tanggal = this.AssignDateRange;
                const response = await axios.get("/izin/getKerja", {
                    params: tanggal,
                });
                if (response.status == 200) {
                    this.min = response.data.data.min;
                    this.max = response.data.data.max;
                    this.AssignTime = response.data.data.min;
                    this.JamOptions = response.data.data.JamOptions;
                }
            } catch (error) {
                console.log(error);
            }
        },

        log() {
            // console.log(this.yesModal);
        },
        formatTimeForSubmission(sliderValue) {
            const numValue = parseFloat(sliderValue);

            if (isNaN(numValue) || !isFinite(numValue)) {
                return "00:00:00";
            }

            const hours = Math.floor(numValue);
            const minutes = (numValue - hours) * 60;

            const formattedHours = String(
                Math.max(0, Math.min(23, hours)),
            ).padStart(2, "0");
            const formattedMinutes = String(
                Math.max(0, Math.min(59, minutes)),
            ).padStart(2, "0");

            return `${formattedHours}:${formattedMinutes}:00`;
        },

        // async submitData() {
        //     // console.log(this.AssignDateRange);
        //     const formData = new FormData();
        //     formData.append("jenisIzin", this.assignIzinType);
        //     if (this.AssignDateRange && Array.isArray(this.AssignDateRange)) {
        //         this.AssignDateRange.forEach((date) => {
        //             formData.append("tanggal[]", date); // Ini yang benar!
        //         });
        //     } else {
        //         console.warn(
        //             "AssignDateRange is not a valid array or is empty."
        //         );
        //     }
        //     const formattedTime = this.formatTimeForSubmission(this.AssignTime);
        //     formData.append("waktu", formattedTime);
        //     formData.append("Alasan", this.permissionReason);
        //     if (this.AssignFile instanceof File) {
        //         formData.append("file", this.AssignFile);
        //         console.log("Appending 'file' to FormData as a File object.");
        //     } else {
        //         console.log(
        //             "this.AssignFile is NOT a File object. NOT appending 'file' to FormData."
        //         );
        //     }

        //     try {
        //         const response = await axios.post("/izin/submit", formData, {
        //             headers: { "Content-Type": "multipart/form-data" },
        //         });
        //         if (response.status == 200) {
        //             console.log(response.data.data);
        //         }
        //     } catch (error) {
        //         console.log(error);
        //         if (error.response.status == 422) {
        //             ElMessage({
        //                 message: error.response.data.errors,
        //                 type: "warning",
        //                 customStyle: {
        //                     "z-index": "1050",
        //                 },
        //             });
        //         }
        //     }
        // },
        async getData(url = null) {
            try {
                this.loading = true;
                const params = new URLSearchParams();
                if (this.searchQuery) {
                    params.append("search", this.searchQuery);
                }

                if (this.filterDropdown) {
                    params.append("tipeFilter", this.filterDropdown);
                }
                if (this.selectedDateFilter) {
                    params.append("tanggalFilter", this.selectedDateFilter);
                }
                if (this.approvalStatusFilter) {
                    params.append("statusFilter", this.approvalStatusFilter);
                }

                const endpoint =
                    // url || `/izin/getDataIzinDH?${params.toString()}`;
                    url ||
                    `/api/v1/izin-atasan-approval/getDataDH?${params.toString()}`;

                const response = await axios.get(endpoint);

                if (response.status == 200) {
                    this.permissionRecords = response.data.result.data;
                    this.prevLink = response.data.result.prev_page_url;
                    this.nextLink = response.data.result.next_page_url;
                    this.totalData = response.data.result.total;
                    this.currentPage = response.data.result.current_page;
                    this.totalPages = response.data.result.last_page;
                    // console.log(this.permissionRecords);
                } else {
                    this.permissionRecords = [];
                    this.currentPage = 0;
                    this.totalPages = 0;
                    ElMessage.warning("Data Tidak ditemukan.");
                }
            } catch (error) {
                this.permissionRecords = [];
                this.currentPage = 0;
                this.totalPages = 0;

                ElMessage.warning("Data Tidak ditemukan.");
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
                    this.AssignDateRange !== null &&
                    this.AssignDateRange.length > 0
                );
            }
            if (this.assignStep === 2) {
                if (
                    this.assignIzinType === "pulangCepat" ||
                    this.assignIzinType === "terlambat"
                ) {
                    return (
                        this.AssignTime !== null && this.permissionReason !== ""
                    );
                }

                return this.permissionReason !== "";
            }
            if (this.assignStep === 3) {
                if (this.assignIzinType === "sakit") {
                    return this.AssignFile !== null;
                }
                return true;
            }
            return true;
        },
        formatSliderTooltip(value) {
            const hours = Math.floor(value);
            const minutes = (value % 1) * 60;
            const formattedHours = String(hours).padStart(2, "0");
            const formattedMinutes = String(minutes).padStart(2, "0");
            return `${formattedHours}:${formattedMinutes}`;
        },
        formatDateToDMY(datetimeString) {
            if (!datetimeString) {
                return ""; // Return empty string if input is empty or null
            }

            const date = new Date(datetimeString);

            if (isNaN(date.getTime())) {
                console.warn("Invalid date string provided:", datetimeString);
                return "";
            }

            const day = String(date.getDate()).padStart(2, "0");
            const year = date.getFullYear();

            const monthNames = [
                "Jan",
                "Feb",
                "Mar",
                "Apr",
                "Mei",
                "Jun",
                "Jul",
                "Agu",
                "Sep",
                "Okt",
                "Nov",
                "Des",
            ];
            const month = monthNames[date.getMonth()];

            return `${day} ${month} ${year}`;
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
            if (!dateString) return "";
            try {
                const date = new Date(dateString);
                const options = {
                    day: "numeric",
                    month: "long",
                    year: "numeric",
                };
                return new Intl.DateTimeFormat("id-ID", options).format(date);
            } catch (error) {
                console.error("Error formatting date:", error);
                return dateString; // Fallback
            }
        },
        disablePastDates(date) {
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Normalize today to the start of the day

            const dateToCheck = new Date(date);
            dateToCheck.setHours(0, 0, 0, 0); // Normalize the date to check to the start of the day

            return dateToCheck.getTime() < today.getTime();
        },
        openDialog() {
            if (this.AssignDateRange && this.AssignDateRange.length === 2) {
                const [startStr, endStr] = this.AssignDateRange;
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
        openDialogSingle() {
            if (this.AssignDateRange && this.AssignDateRange.length === 2) {
                const [startStr] = this.AssignDateRange;
                this.tempStartDate = new Date(startStr + "T00:00:00");
                this.tempEndDate = new Date(startStr + "T00:00:00");
                this.calendarDate = new Date(startStr + "T00:00:00");
            } else {
                this.tempStartDate = null;
                this.tempEndDate = null;
                this.calendarDate = new Date();
            }
            this.dialogVisibleSingle = true;
        },
        handleAssignIzin(type) {
            this.AssignDateRange = null;
            this.assignIzinType = type;
            this.tempStartDate = null;
            this.tempEndDate = null;
            this.AssignFile = null;
        },
        handleCancel() {
            this.dialogVisible = false;
            this.dialogVisibleSingle = false;
            this.tempStartDate = null;
            this.tempEndDate = null;
        },
        handleDateClick(data) {
            const clickedDate = data.date;
            if (this.disablePastDates(clickedDate)) {
                ElMessage({
                    message: "Tanggal ini tidak bisa dipilih.",
                    type: "warning",
                });
                return;
            }

            if (this.tempStartDate && this.tempEndDate) {
                this.tempStartDate = clickedDate;
                this.tempEndDate = null;
            } else if (!this.tempStartDate) {
                this.tempStartDate = clickedDate;
            } else {
                if (clickedDate.getTime() < this.tempStartDate.getTime()) {
                    this.tempEndDate = this.tempStartDate;
                    this.tempStartDate = clickedDate;
                } else {
                    this.tempEndDate = clickedDate;
                }
            }
        },
        handleDateClickSingle(data) {
            const clickedDate = data.date;
            if (this.disablePastDates(clickedDate)) {
                ElMessage({
                    message: "Tanggal ini tidak bisa dipilih.",
                    type: "warning",
                });
                return;
            }
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
            // console.log(this.AssignDateRange);
        },

        nextAssignStep() {
            if (this.canProceedToNextStep()) {
                if (this.assignStep == 1) {
                    if (
                        this.assignIzinType == "pulangCepat" ||
                        this.assignIzinType == "terlambat"
                    ) {
                        this.getJamKerja().then(() => {
                            this.assignStep++;
                        });
                    } else {
                        this.assignStep++;
                    }
                } else {
                    this.assignStep++;
                }
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

        // --- File Upload Methods ---
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
                    this.AssignFile = null;
                    event.target.value = "";
                    return;
                }

                if (file.size > maxSize) {
                    ElMessage({
                        message: "Ukuran file terlalu besar. Maksimal 5MB.",
                        type: "error",
                    });
                    this.AssignFile = null;
                    event.target.value = "";
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
                this.$refs.doctorLetterInput.value = "";
            }
        },

        // --- Data and UI State Management Methods ---
        deleteAlertButton(transactionId) {
            // console.log("Delete transaction:", transactionId);
            ElMessage({
                message: `Transaksi ${transactionId} akan dihapus.`,
                type: "info",
            });
            // Implement actual deletion logic here
        },
        storeAlertButton() {
            console.log("Storing alert/permission...");
            // You can access this.AssignFile here if it's needed for submission
            ElNotification({
                title: "Berhasil",
                message: "Pengajuan izin berhasil disimpan!",
                type: "success",
                position: "bottom-right",
            });
            this.closeAssignModal();
            // Implement actual storage logic here
        },
        closeAssignModal() {
            this.showAssignModal = false;
            this.assignStep = 1;
            this.assignIzinType = null;
            this.AssignDateRange = null;
            this.AssignTime = null;
            this.permissionReason = "";
            this.tempStartDate = null;
            this.tempEndDate = null;
            this.AssignFile = null;
        },
        isMobile() {
            return this.windowWidth < 768;
        },
        /** Rujukan tetap untuk addEventListener/removeEventListener. */
        onWindowResize() {
            this.updateWindowWidth();
            if (!localStorage.getItem("preferredViewMode")) {
                this.viewMode = this.isMobile() ? "card" : "table";
            }
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
            localStorage.setItem("preferredViewMode", mode);
        },
        showDetail(item) {
            this.selectedItem = item;
            this.showDetailModal = true;
        },
        showPicture(item) {
            this.selectedItem = item;
            this.showDetailModal = true;
        },
        showDeleteConfirm(id) {
            this.deleteItemId = id;
            this.showDeleteModal = true;
            this.showActionPopup = false;
            this.showDetailModal = false;
        },
        confirmDelete() {
            this.permissionRecords = this.permissionRecords.filter(
                (item) => item.No_Transaksi !== this.deleteItemId,
            );
            this.showDeleteModal = false;
            ElNotification({
                title: "Berhasil",
                message: "Pengajuan izin berhasil dihapus",
                type: "success",
                position: "bottom-right",
            });
            if (
                this.selectedItem &&
                this.selectedItem.No_Transaksi === this.deleteItemId
            ) {
                this.selectedItem = null;
            }
        },
        showActionMenu(item, event) {
            this.selectedItem = item;
            if (event) {
                this.actionMenuPosition = {
                    top: `${event.clientY}px`,
                    left: `${event.clientX}px`,
                };
            } else {
                this.actionMenuPosition = {
                    top: "50%",
                    left: "50%",
                    transform: "translate(-50%, -50%)",
                };
            }
            this.showActionPopup = true;
        },
        editPermission(item) {
            this.showActionPopup = false;
            ElMessage.info("Fitur edit akan segera tersedia");
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
            return text.length > length
                ? text.substring(0, length) + "..."
                : text;
        },
        getTypeClass(type) {
            return (
                {
                    sakit: "type-sakit",
                    terlambat: "type-terlambat",
                    pulangCepat: "type-pulang-cepat",
                    "Tidak Masuk": "type-tidak-masuk",
                    cuti: "type-cuti-tahunan",
                    cutiHutang: "type-cuti-tahunan",
                    izinFull: "type-izin-pribadi",
                }[type] || "type-izin-pribadi"
            );
        },
        getStatusClass(status) {
            if (status === "Y") {
                return "status-approved";
            } else if (
                status === null ||
                status === undefined ||
                status === "N"
            ) {
                return "status-pending";
            } else if (status === "T" || status === "P") {
                return "status-rejected";
            }
            return "";
        },
        getStatusHeaderClass(status) {
            if (status === "Y") {
                return "header-approved";
            } else if (
                status === null ||
                status === undefined ||
                status === "N"
            ) {
                return "header-pending";
            } else if (status === "T" || status === "P") {
                return "header-rejected";
            }
            return "";
        },
        checkScreenSize() {
            if (window.innerWidth >= 768) {
                this.viewMode = "table";
            } else {
                this.viewMode = "card";
            }
        },
    },
    mounted() {
        // Set day/night based on current time for the header icon
        const currentHour = new Date().getHours();
        this.dayNight =
            currentHour >= 6 && currentHour < 18 ? "Siang" : "Malam";

        // Check preferred view mode from localStorage or detect device
        const preferredMode = localStorage.getItem("preferredViewMode");
        if (preferredMode) {
            this.viewMode = preferredMode;
        } else {
            this.viewMode = this.isMobile() ? "card" : "table";
        }

        // Add event listener for window resize
        /*
         * unmounted() melepas this.updateWindowWidth, sedangkan yang dipasang
         * dulu adalah arrow inline -- dua fungsi berbeda, jadi tidak ada yang
         * pernah terlepas. Sekarang keduanya menunjuk metode yang sama.
         */
        window.addEventListener("resize", this.onWindowResize);
        this.getData();
        this.checkScreenSize();
        // Simulate fetching data
    },
    unmounted() {
        window.removeEventListener("resize", this.onWindowResize);
    },
};
</script>

<style scoped>
/* Consolidated and deduplicated styles */
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
}
.delete-modal-content {
    padding-right: 0 !important;
    padding-left: 0 !important;
}
/* detail */
/* overlay dialog */
.el-overlay-dialog {
    display: flex;
    justify-content: center;
    align-items: center;
    max-height: 100vh;
    max-width: 100vw;
    overflow: hidden;
}

.card-label {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
    font-weight: 500;
    width: fit-content !important;
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
    padding: 0rem; /* Atur padding sesuai kebutuhan */
}

.el-icon {
    display: flex;
    align-items: center;
    justify-content: center;
}

.el-dialog {
    padding: 0;
}

.el-dialog__header,
.el-dialog__footer {
    /* flex-shrink: 0; */
    border-radius: 0px 0px 12px 12px;
}
.detail-modal .el-dialog__header {
    border-radius: 0 0 0 0;
}

.el-dialog__header.show-close {
    /* padding-right: calc(var(--el-dialog-padding-primary) + var(--el-message-close-size, 16px)); */
    padding: 1rem;
}

.modal-footer-actions {
    border-top: none !important;
}

.el-dialog__header,
.el-dialog__footer {
    flex-shrink: 0; /* Pastikan header/footer tidak mengecil */
}
/* akhir dialog */
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
    padding: 0 !important;
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

    .el-overlay-dialog {
        align-items: end;
    }

    .detail-modal .el-dialog {
        margin: 0;
        max-height: 100vh;
    }
}

/*  */

.el-popper {
    border: none !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
    border-radius: 8px !important;
    padding: 0 !important;
    background: white !important;
}

.el-popper__arrow {
    display: none !important;
}

.el-tooltip__popper.is-dark {
    background: transparent !important;
    border: none !important;
}

.buttonAccept {
    width: 100%;
    padding: 0.3rem 1rem;
    text-align: center;
    transition: all 0.3s ease;
    background: var(--surface-soft);
}

.buttonAccept.left {
    border-radius: 0px 20px 20px 0px;
}
.buttonAccept.right {
    border-radius: 20px 0px 0px 20px;
}

.buttonAccept:hover {
    background: var(--primary);
    color: var(--surface) !important;
}

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
    background: var(--hcis-emerald-600);
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
    box-shadow:
        0 20px 25px -5px rgba(0, 0, 0, 0.1),
        0 10px 10px -5px rgba(0, 0, 0, 0.04);
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
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

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
    gap: 1rem;
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

    /* background: linear-gradient(135deg, var(--hcis-violet-400) 0%, #8a5cf6ce 100%); */
}
.header-pending {
    /* background: linear-gradient(135deg, var(--hcis-emerald-400) 0%, #059669ce 100%); */
    background: linear-gradient(135deg, var(--hcis-indigo-400) 0%, #6365f1ce 100%);
}
.header-rejected {
    background: linear-gradient(135deg, #f472b5 0%, #ec489ad3 100%);
}
.card-date {
    font-weight: 500;
    font-size: 0.8rem;
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
    /* justify-content: space-between; */
    justify-content: end;
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
.success-icon {
    font-size: 3rem;
    color: var(--success);
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
    justify-content: center;
    border-radius: 50%;
    cursor: pointer;
    transition:
        background-color 0.2s,
        color 0.2s;
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
    transition:
        background-color 0.2s,
        color 0.2s;
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
    pointer-events: none;
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
        z-index: 100;
        box-shadow: none;
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
    .control-panel {
        padding: 0.75rem;
        margin-bottom: 0.5rem;
    }
    .search-filter-section {
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

/* Custom Confirm Dialog Styling */
.confirm-dialog {
    border-radius: 16px !important;
    overflow: hidden;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
}

@media (min-width: 769px) {
    .confirm-dialog {
        max-width: 440px !important;
    }
}

@media (max-width: 768px) {
    .el-dialog.confirm-dialog,
    .confirm-dialog {
        width: 100% !important;
        max-width: 100% !important;
        margin: auto 0 0 0 !important;
        margin-top: auto !important;
        margin-bottom: 0 !important;
        border-radius: 20px 20px 0 0 !important;
        border-bottom-left-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
        box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.18) !important;
        animation: slideUpMobile 0.28s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    .confirm-dialog .el-dialog__header {
        padding: 1rem 1.25rem !important;
        border-radius: 20px 20px 0 0 !important;
    }

    .confirm-dialog .confirm-modal-body {
        padding: 1.25rem 1rem !important;
    }

    .confirm-dialog .el-dialog__footer {
        padding: 0.875rem 1rem calc(0.875rem + env(safe-area-inset-bottom, 0px)) !important;
    }

    .confirm-dialog .modal-footer-actions,
    .confirm-dialog .delete-modal-footer {
        display: flex !important;
        flex-direction: row !important;
        gap: 0.75rem !important;
        width: 100% !important;
    }

    .confirm-dialog .modal-footer-actions .btn-modern,
    .confirm-dialog .delete-modal-footer .btn-modern {
        flex: 1 1 50% !important;
        justify-content: center !important;
        padding: 0.75rem 0.5rem !important;
        font-size: 0.95rem !important;
        border-radius: 10px !important;
    }
}

.confirm-dialog .el-dialog__header {
    border-bottom: 1px solid var(--border);
    padding: 1.25rem 1.5rem !important;
    margin-right: 0 !important;
    background-color: var(--surface-soft);
}

.confirm-dialog .el-dialog__title {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--dark);
}

.confirm-dialog .el-dialog__body {
    padding: 0 !important;
}

.confirm-dialog .el-dialog__footer {
    background-color: var(--surface-soft);
    border-top: 1px solid var(--border);
    padding: 1rem 1.5rem !important;
}

.confirm-modal-body {
    padding: 1.75rem 1.5rem;
}

.confirm-icon-badge-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
}

.confirm-icon-badge {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    animation: scaleIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.confirm-icon-badge.badge-success {
    background-color: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.confirm-icon-badge.badge-danger {
    background-color: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.confirm-modal-message {
    color: var(--dark);
    margin-top: 1rem;
}

.confirm-modal-submessage {
    font-size: 0.9rem;
    line-height: 1.5;
}

.confirm-modal-warning-box {
    display: flex;
    flex-direction: column;
    padding: 0.75rem 1rem;
    border-radius: 10px;
    text-align: left;
}

.confirm-modal-warning-box.warning-box-success {
    background-color: rgba(16, 185, 129, 0.05);
    border: 1px dashed rgba(16, 185, 129, 0.3);
}

.confirm-modal-warning-box.warning-box-danger {
    background-color: rgba(239, 68, 68, 0.05);
    border: 1px dashed rgba(239, 68, 68, 0.3);
}

.text-success-dark {
    color: var(--hcis-emerald-800);
}

.text-danger-dark {
    color: var(--hcis-red-800);
}

.modern-textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: all 0.2s ease;
    resize: none;
}

.modern-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.character-counter {
    font-variant-numeric: tabular-nums;
}

.confirm-dialog .btn-secondary {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    border: 1px solid var(--hcis-slate-300);
}

.confirm-dialog .btn-secondary:hover {
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-800);
    border-color: var(--hcis-slate-400);
}

@keyframes scaleIn {
    from {
        transform: scale(0);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes slideUpMobile {
    from {
        transform: translateY(100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
</style>
