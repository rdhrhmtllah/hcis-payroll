<template>
    <div class="container-fluid px-0">
        <!-- Header -->
        <HeaderKpi
            title="Persetujuan Izin"
            subtitle="Kelola pengajuan izin dari tim Anda"
            :dayNight="dayNight"
            :formattedDate="formatDateString(currentDate)"
        />

        <!-- Main Content -->
        <div class="row justify-content-center">
            <div class="col-12">
                <ApprovalTabs
                    :tabs="approvalTabs"
                    :activeTab="activeTab"
                    @tab-change="handleTabChange"
                >
                    <!-- Tab Dashboard -->
                    <template #dashboard>
                        <div class="tab-content-improved">
                            <!-- Quick Stats -->
                            <div class="row g-3 mb-4">
                                <div
                                    v-for="stat in dashboardStats"
                                    :key="stat.title"
                                    class="col-xl-3 col-md-6 col-12"
                                >
                                    <div class="stat-card-dashboard">
                                        <div
                                            class="stat-icon"
                                            :class="stat.bgClass"
                                        >
                                            <i :class="stat.icon"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h4>{{ stat.value }}</h4>
                                            <p>{{ stat.title }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Charts Section -->
                            <div class="row g-3">
                                <!-- Approval Rate -->
                                <div class="col-xl-4 col-md-6 col-12">
                                    <div class="chart-card">
                                        <h6 class="chart-title">
                                            Tingkat Persetujuan
                                        </h6>
                                        <apexchart
                                            v-if="!loading.dashboard"
                                            type="radialBar"
                                            height="300"
                                            :options="approvalRateChart.options"
                                            :series="approvalRateChart.series"
                                        ></apexchart>
                                        <div v-else class="chart-skeleton">
                                            <div class="skeleton-loader"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Leave Type Distribution -->
                                <div class="col-xl-4 col-md-6 col-12">
                                    <div class="chart-card">
                                        <h6 class="chart-title">
                                            Distribusi Jenis Izin
                                        </h6>
                                        <apexchart
                                            v-if="!loading.dashboard"
                                            type="donut"
                                            height="300"
                                            :options="leaveTypeChart.options"
                                            :series="leaveTypeChart.series"
                                        ></apexchart>
                                        <div v-else class="chart-skeleton">
                                            <div class="skeleton-loader"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Requests by Employee -->
                                <div class="col-xl-4 col-md-12 col-12">
                                    <div class="chart-card">
                                        <h6 class="chart-title">
                                            Pengajuan per Karyawan
                                        </h6>
                                        <apexchart
                                            v-if="!loading.dashboard"
                                            type="bar"
                                            height="300"
                                            :options="
                                                requestsByEmployeeChart.options
                                            "
                                            :series="
                                                requestsByEmployeeChart.series
                                            "
                                        ></apexchart>
                                        <div v-else class="chart-skeleton">
                                            <div class="skeleton-loader"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Tab Pengajuan -->
                    <template #pengajuan>
                        <div class="tab-content-improved">
                            <!-- Filter & Search -->
                            <div class="filter-section mb-4">
                                <div class="row g-2 align-items-center">
                                    <div
                                        class="col-lg-5 col-md-4 col-12 mb-2 mb-md-0"
                                    >
                                        <div class="search-box">
                                            <i class="bi bi-search"></i>
                                            <input
                                                type="text"
                                                v-model="filters.search"
                                                placeholder="Cari nama karyawan atau nomor transaksi..."
                                                class="search-input"
                                                @input="handleSearch"
                                            />
                                        </div>
                                    </div>
                                    <div class="col-lg-2 col-md-3 col-6">
                                        <select
                                            v-model="filters.tipeFilter"
                                            class="filter-select"
                                            @change="applyFilters"
                                        >
                                            <option value="">
                                                Semua Jenis
                                            </option>
                                            <option value="Cuti">Cuti</option>
                                            <option value="Sakit">
                                                Izin Sakit
                                            </option>
                                            <option value="Izin">
                                                Izin Lainnya
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-lg-2 col-md-3 col-6">
                                        <select
                                            v-model="filters.status"
                                            class="filter-select"
                                            @change="applyFilters"
                                        >
                                            <option value="">
                                                Semua Status
                                            </option>
                                            <option value="pending">
                                                Pending
                                            </option>
                                            <option value="approved">
                                                Disetujui
                                            </option>
                                            <option value="rejected">
                                                Ditolak
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-lg-3 col-md-2 col-12">
                                        <input
                                            type="date"
                                            v-model="filters.tanggalFilter"
                                            class="filter-select"
                                            @change="applyFilters"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Loading State -->
                            <div v-if="loading.pengajuan" class="loading-state">
                                <div class="loading-spinner">
                                    <i class="bi bi-arrow-repeat"></i>
                                </div>
                                <h4>Memuat Pengajuan...</h4>
                            </div>

                            <!-- List Pengajuan -->
                            <div
                                v-else-if="pengajuanList.length > 0"
                                class="pengajuan-list"
                            >
                                <div
                                    v-for="item in pengajuanList"
                                    :key="item.No_Transaksi"
                                    class="pengajuan-card"
                                >
                                    <div class="pengajuan-header">
                                        <div class="employee-info">
                                            <div class="avatar-sm">
                                                {{
                                                    getInitials(
                                                        item.requester_Nama
                                                    )
                                                }}
                                            </div>
                                            <div class="employee-details">
                                                <h6 class="employee-name">
                                                    {{ item.requester_Nama }}
                                                </h6>
                                                <p class="employee-meta">
                                                    {{
                                                        item.Tipe_Izin2 ||
                                                        item.Tipe_Izin
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="pengajuan-meta">
                                            <Badge :type="getBadgeType(item)">
                                                {{ getStatusText(item.status) }}
                                            </Badge>
                                            <span class="pengajuan-date">{{
                                                formatDate(item.tanggal_create)
                                            }}</span>
                                        </div>
                                    </div>

                                    <div class="pengajuan-current-approver">
                                        <i class="bi bi-person-check-fill"></i>
                                        <span>
                                            Status:
                                            <strong>{{
                                                getCurrentApproverText(item)
                                            }}</strong>
                                        </span>
                                    </div>

                                    <div class="pengajuan-content">
                                        <div class="pengajuan-details">
                                            <div class="detail-item">
                                                <i class="bi bi-calendar"></i>
                                                <span
                                                    >{{
                                                        formatDate(
                                                            item.TanggalIzin
                                                        )
                                                    }}
                                                    <span
                                                        v-if="
                                                            item.Tanggal_Selesai &&
                                                            item.Tanggal_Selesai !==
                                                                item.TanggalIzin
                                                        "
                                                    >
                                                        -
                                                        {{
                                                            formatDate(
                                                                item.Tanggal_Selesai
                                                            )
                                                        }}
                                                    </span>
                                                </span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="bi bi-clock"></i>
                                                <span
                                                    >{{
                                                        item.tanggalReal
                                                            ?.jumlah_hari || "1"
                                                    }}
                                                    hari</span
                                                >
                                            </div>
                                        </div>
                                        <div class="pengajuan-reason">
                                            <p class="p-0 m-0">
                                                {{
                                                    truncateText(
                                                        item.Alasan,
                                                        80
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="pengajuan-footer">
                                        <div class="pengajuan-actions">
                                            <button
                                                class="btn-action btn-view"
                                                @click="viewDetail(item)"
                                            >
                                                <i class="bi bi-eye"></i>
                                                <span class="d-none d-md-inline"
                                                    >Detail</span
                                                >
                                            </button>
                                            <button
                                                v-if="item.status === 'pending'"
                                                class="btn-action btn-danger"
                                                @click="rejectPengajuan(item)"
                                            >
                                                <i class="bi bi-x"></i>
                                                <span class="d-none d-md-inline"
                                                    >Tolak</span
                                                >
                                            </button>
                                            <button
                                                v-if="item.status === 'pending'"
                                                class="btn-action btn-success"
                                                @click="approvePengajuan(item)"
                                            >
                                                <i class="bi bi-check"></i>
                                                <span class="d-none d-md-inline"
                                                    >Setujui</span
                                                >
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pagination -->
                                <div class="pagination-container mt-4">
                                    <button
                                        class="btn-pagination"
                                        :disabled="
                                            !pengajuanPagination.prev_page_url
                                        "
                                        @click="
                                            fetchPengajuan(
                                                pengajuanPagination.prev_page_url
                                            )
                                        "
                                    >
                                        <i class="bi bi-chevron-left"></i>
                                        Sebelumnya
                                    </button>
                                    <span class="pagination-info">
                                        Halaman
                                        {{ pengajuanPagination.current_page }}
                                        dari
                                        {{ pengajuanPagination.last_page }}
                                    </span>
                                    <button
                                        class="btn-pagination"
                                        :disabled="
                                            !pengajuanPagination.next_page_url
                                        "
                                        @click="
                                            fetchPengajuan(
                                                pengajuanPagination.next_page_url
                                            )
                                        "
                                    >
                                        Selanjutnya
                                        <i class="bi bi-chevron-right"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Empty State -->
                            <div v-else class="empty-state-admin">
                                <i class="bi bi-inbox"></i>
                                <h4>Tidak Ada Pengajuan</h4>
                                <p>
                                    Saat ini tidak ada pengajuan izin yang
                                    sesuai dengan filter Anda.
                                </p>
                            </div>
                        </div>
                    </template>

                    <!-- Tab Riwayat -->
                    <template #riwayat>
                        <div class="tab-content-improved">
                            <!-- Filter & Search -->
                            <div class="filter-section mb-4">
                                <div class="row g-2 align-items-center">
                                    <div
                                        class="col-lg-5 col-md-4 col-12 mb-2 mb-md-0"
                                    >
                                        <div class="search-box">
                                            <i class="bi bi-search"></i>
                                            <input
                                                type="text"
                                                v-model="filtersRiwayat.search"
                                                placeholder="Cari nama karyawan atau nomor transaksi..."
                                                class="search-input"
                                                @input="handleSearchRiwayat"
                                            />
                                        </div>
                                    </div>
                                    <div class="col-lg-2 col-md-3 col-6">
                                        <select
                                            v-model="filtersRiwayat.tipeFilter"
                                            class="filter-select"
                                            @change="applyFiltersRiwayat"
                                        >
                                            <option value="">
                                                Semua Jenis
                                            </option>
                                            <option value="Cuti">Cuti</option>
                                            <option value="Sakit">
                                                Izin Sakit
                                            </option>
                                            <option value="Izin">
                                                Izin Lainnya
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-lg-2 col-md-3 col-6">
                                        <select
                                            v-model="filtersRiwayat.status"
                                            class="filter-select"
                                            @change="applyFiltersRiwayat"
                                        >
                                            <option value="">
                                                Semua Status
                                            </option>
                                            <option value="approved">
                                                Disetujui
                                            </option>
                                            <option value="rejected">
                                                Ditolak
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-lg-3 col-md-2 col-12">
                                        <input
                                            type="date"
                                            v-model="
                                                filtersRiwayat.tanggalFilter
                                            "
                                            class="filter-select"
                                            @change="applyFiltersRiwayat"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Loading State -->
                            <div v-if="loading.riwayat" class="loading-state">
                                <div class="loading-spinner">
                                    <i class="bi bi-arrow-repeat"></i>
                                </div>
                                <h4>Memuat Riwayat...</h4>
                            </div>

                            <!-- Data tersedia -->
                            <div v-else-if="riwayatList.length > 0">
                                <!-- Desktop Table View -->
                                <div class="table-responsive d-none d-md-block">
                                    <table class="modern-table">
                                        <thead>
                                            <tr>
                                                <th>Karyawan</th>
                                                <th>Jenis Izin</th>
                                                <th>Tanggal</th>
                                                <th>Durasi</th>
                                                <th>Status</th>
                                                <th>No. Transaksi</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="item in riwayatList"
                                                :key="item.No_Transaksi"
                                            >
                                                <td>
                                                    <div class="employee-info">
                                                        <div class="avatar-sm">
                                                            {{
                                                                getInitials(
                                                                    item.requester_Nama
                                                                )
                                                            }}
                                                        </div>
                                                        <div
                                                            class="employee-details"
                                                        >
                                                            <h6
                                                                class="employee-name"
                                                            >
                                                                {{
                                                                    item.requester_Nama
                                                                }}
                                                            </h6>
                                                            <p
                                                                class="employee-meta"
                                                            >
                                                                {{
                                                                    item.Tipe_Izin2 ||
                                                                    item.Tipe_Izin
                                                                }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <Badge
                                                        :type="
                                                            getBadgeType(item)
                                                        "
                                                    >
                                                        {{
                                                            item.Tipe_Izin2 ||
                                                            item.Tipe_Izin
                                                        }}
                                                    </Badge>
                                                </td>
                                                <td>
                                                    {{
                                                        formatDate(
                                                            item.TanggalIzin
                                                        )
                                                    }}
                                                </td>
                                                <td>
                                                    {{
                                                        item.tanggalReal
                                                            ?.jumlah_hari || "1"
                                                    }}
                                                    hari
                                                </td>
                                                <td>
                                                    <Badge
                                                        :type="
                                                            item.status ===
                                                            'approved'
                                                                ? 'success'
                                                                : 'danger'
                                                        "
                                                    >
                                                        {{
                                                            getStatusText(
                                                                item.status
                                                            )
                                                        }}
                                                    </Badge>
                                                </td>
                                                <td>
                                                    <small class="text-muted">{{
                                                        item.No_Transaksi
                                                    }}</small>
                                                </td>
                                                <td>
                                                    <button
                                                        class="btn-action btn-view"
                                                        @click="
                                                            viewDetail(item)
                                                        "
                                                    >
                                                        <i
                                                            class="bi bi-eye"
                                                        ></i>
                                                        <span>Detail</span>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <!-- Pagination -->
                                    <div class="pagination-container mt-4">
                                        <button
                                            class="btn-pagination"
                                            :disabled="
                                                !riwayatPagination.prev_page_url
                                            "
                                            @click="
                                                fetchRiwayat(
                                                    riwayatPagination.prev_page_url
                                                )
                                            "
                                        >
                                            <i class="bi bi-chevron-left"></i>
                                            Sebelumnya
                                        </button>
                                        <span class="pagination-info">
                                            Halaman
                                            {{ riwayatPagination.current_page }}
                                            dari
                                            {{ riwayatPagination.last_page }}
                                        </span>
                                        <button
                                            class="btn-pagination"
                                            :disabled="
                                                !riwayatPagination.next_page_url
                                            "
                                            @click="
                                                fetchRiwayat(
                                                    riwayatPagination.next_page_url
                                                )
                                            "
                                        >
                                            Selanjutnya
                                            <i class="bi bi-chevron-right"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Mobile Card View -->
                                <div class="d-md-none">
                                    <div
                                        v-for="item in riwayatList"
                                        :key="item.No_Transaksi"
                                        class="pengajuan-card mobile-card"
                                    >
                                        <div class="pengajuan-header">
                                            <div class="employee-info">
                                                <div class="avatar-sm">
                                                    {{
                                                        getInitials(
                                                            item.requester_Nama
                                                        )
                                                    }}
                                                </div>
                                                <div class="employee-details">
                                                    <h6 class="employee-name">
                                                        {{
                                                            item.requester_Nama
                                                        }}
                                                    </h6>
                                                    <p class="employee-meta">
                                                        {{
                                                            item.Tipe_Izin2 ||
                                                            item.Tipe_Izin
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="pengajuan-meta">
                                                <Badge
                                                    :type="
                                                        item.status ===
                                                        'approved'
                                                            ? 'success'
                                                            : 'danger'
                                                    "
                                                >
                                                    {{
                                                        getStatusText(
                                                            item.status
                                                        )
                                                    }}
                                                </Badge>
                                            </div>
                                        </div>

                                        <div class="pengajuan-content">
                                            <div class="pengajuan-details">
                                                <div class="detail-item">
                                                    <i
                                                        class="bi bi-calendar"
                                                    ></i>
                                                    <span>{{
                                                        formatDate(
                                                            item.TanggalIzin
                                                        )
                                                    }}</span>
                                                </div>
                                                <div class="detail-item">
                                                    <i class="bi bi-clock"></i>
                                                    <span
                                                        >{{
                                                            item.tanggalReal
                                                                ?.jumlah_hari ||
                                                            "1"
                                                        }}
                                                        hari</span
                                                    >
                                                </div>
                                                <div class="detail-item">
                                                    <i class="bi bi-hash"></i>
                                                    <span class="text-muted">{{
                                                        item.No_Transaksi
                                                    }}</span>
                                                </div>
                                            </div>
                                            <div class="pengajuan-reason">
                                                <p class="p-0 m-0">
                                                    {{
                                                        truncateText(
                                                            item.Alasan,
                                                            60
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                            <div class="pengajuan-footer mt-2">
                                                <button
                                                    class="btn-action btn-view w-100"
                                                    @click="viewDetail(item)"
                                                >
                                                    <i class="bi bi-eye"></i>
                                                    <span>Lihat Detail</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Pagination Mobile -->
                                    <div class="pagination-container mt-4">
                                        <button
                                            class="btn-pagination"
                                            :disabled="
                                                !riwayatPagination.prev_page_url
                                            "
                                            @click="
                                                fetchRiwayat(
                                                    riwayatPagination.prev_page_url
                                                )
                                            "
                                        >
                                            <i class="bi bi-chevron-left"></i>
                                        </button>
                                        <span class="pagination-info">
                                            {{
                                                riwayatPagination.current_page
                                            }}/{{ riwayatPagination.last_page }}
                                        </span>
                                        <button
                                            class="btn-pagination"
                                            :disabled="
                                                !riwayatPagination.next_page_url
                                            "
                                            @click="
                                                fetchRiwayat(
                                                    riwayatPagination.next_page_url
                                                )
                                            "
                                        >
                                            <i class="bi bi-chevron-right"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty State -->
                            <div v-else class="empty-state-admin">
                                <i class="bi bi-clock-history"></i>
                                <h4>Tidak Ada Riwayat</h4>
                                <p>
                                    Belum ada riwayat pengajuan yang sesuai
                                    dengan filter Anda.
                                </p>
                            </div>
                        </div>
                    </template>
                </ApprovalTabs>
            </div>
        </div>

        <!-- Modal Detail Pengajuan -->
        <Modal
            v-if="showDetailModal"
            :show="showDetailModal"
            :title="`Detail Pengajuan - ${selectedItem?.No_Transaksi || ''}`"
            size="large"
            @close="closeDetailModal"
        >
            <template #body>
                <div v-if="selectedItem" class="detail-content-v2">
                    <div class="row">
                        <!-- Kolom Kiri: Info Karyawan & Status -->
                        <div class="col-md-5 detail-left-col">
                            <div class="detail-employee-info">
                                <div class="avatar-lg">
                                    {{
                                        getInitials(selectedItem.requester_Nama)
                                    }}
                                </div>
                                <h5 class="employee-name">
                                    {{ selectedItem.requester_Nama }}
                                </h5>
                                <p class="employee-meta">
                                    {{
                                        selectedItem.Tipe_Izin2 ||
                                        selectedItem.Tipe_Izin
                                    }}
                                </p>
                                <p class="employee-meta text-muted">
                                    No. Transaksi:
                                    {{ selectedItem.No_Transaksi }}
                                </p>
                            </div>
                            <div class="detail-status-section">
                                <h6 class="section-title">Status Pengajuan</h6>
                                <Badge
                                    :type="
                                        selectedItem.status === 'pending'
                                            ? 'warning'
                                            : selectedItem.status === 'approved'
                                            ? 'success'
                                            : 'danger'
                                    "
                                    class="w-100 justify-content-center"
                                >
                                    {{ getStatusText(selectedItem.status) }}
                                </Badge>
                                <small class="text-muted mt-2 d-block">
                                    Diajukan pada:
                                    {{
                                        formatDateTime(
                                            selectedItem.tanggal_create
                                        )
                                    }}
                                </small>
                            </div>
                        </div>

                        <!-- Kolom Kanan: Detail Izin & Jejak Persetujuan -->
                        <div class="col-md-7">
                            <div class="detail-izin-info">
                                <h6 class="section-title">Detail Izin</h6>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <span class="label">Jenis Izin</span>
                                        <span class="value">{{
                                            selectedItem.Tipe_Izin2 ||
                                            selectedItem.Tipe_Izin
                                        }}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Tanggal Mulai</span>
                                        <span class="value">
                                            {{
                                                formatDate(
                                                    selectedItem.TanggalIzin
                                                )
                                            }}
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label"
                                            >Tanggal Selesai</span
                                        >
                                        <span class="value">
                                            {{
                                                formatDate(
                                                    selectedItem.Tanggal_Selesai
                                                )
                                            }}
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Jumlah Hari</span>
                                        <span class="value"
                                            >{{
                                                selectedItem.tanggalReal
                                                    ?.jumlah_hari || "1"
                                            }}
                                            hari</span
                                        >
                                    </div>
                                    <div class="info-item full-width">
                                        <span class="label">Alasan</span>
                                        <p class="value-reason">
                                            {{ selectedItem.Alasan }}
                                        </p>
                                    </div>
                                    <div
                                        v-if="selectedItem.Lampiran"
                                        class="info-item"
                                    >
                                        <span class="label">Lampiran</span>
                                        <a
                                            :href="selectedItem.Lampiran"
                                            target="_blank"
                                            class="file-link"
                                        >
                                            <i class="bi bi-paperclip me-2"></i
                                            >Lihat File
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Status Approval Flow -->
                            <div class="approval-trace-section-v2 mt-4">
                                <h6 class="section-title">
                                    Status Persetujuan
                                </h6>
                                <div class="approval-status-info">
                                    <div class="status-item">
                                        <span class="status-label"
                                            >Approver Saat Ini:</span
                                        >
                                        <span class="status-value">{{
                                            selectedItem.Approver ||
                                            "Tidak tersedia"
                                        }}</span>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-label"
                                            >Status Saat Ini:</span
                                        >
                                        <span class="status-value">{{
                                            selectedItem.Status_Approval_Saat_Ini ||
                                            "Pending"
                                        }}</span>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-label"
                                            >Langkah Sebelumnya:</span
                                        >
                                        <span class="status-value">{{
                                            selectedItem.Status_Approval_Langkah_Sebelumnya ||
                                            "Tidak ada"
                                        }}</span>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-label"
                                            >Urutan Flow:</span
                                        >
                                        <span class="status-value"
                                            >{{ selectedItem.order_flow }} /
                                            {{ selectedItem.MaxFlow }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="modal-footer">
                    <button
                        class="btn-modern btn-secondary"
                        @click="closeDetailModal"
                    >
                        Tutup
                    </button>
                    <button
                        v-if="selectedItem?.status === 'pending'"
                        class="btn-modern btn-danger"
                        @click="rejectPengajuan(selectedItem)"
                    >
                        Tolak
                    </button>
                    <button
                        v-if="selectedItem?.status === 'pending'"
                        class="btn-modern btn-success"
                        @click="approvePengajuan(selectedItem)"
                    >
                        Setujui
                    </button>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import HeaderKpi from "@/components/legacy/header.vue";
import VueApexCharts from 'vue3-apexcharts';
import ApprovalTabs from "@/components/legacy/toggleSectionV2.vue";
import Badge from "../../../components/ui/badge.vue";
import Modal from "@/components/legacy/modalV2-noir.vue";
import axios from "axios";

export default {
    name: "ApprovalIzin",
    components: {
        // <apexchart> dipakai di template tetapi tidak pernah didaftarkan,
        // sehingga ketiga grafik di halaman ini tidak muncul. Nama alias
        // mengikuti KuotaManagement.vue.
        apexchart: VueApexCharts,
        HeaderKpi,
        ApprovalTabs,
        Badge,
        Modal,
    },
    data() {
        return {
            // State Management
            activeTab: "dashboard",
            currentDate: new Date(),
            showDetailModal: false,
            selectedItem: null,

            // Data Collections
            pengajuanList: [],
            riwayatList: [],

            // Pagination
            pengajuanPagination: {
                current_page: 1,
                last_page: 1,
                prev_page_url: null,
                next_page_url: null,
            },
            riwayatPagination: {
                current_page: 1,
                last_page: 1,
                prev_page_url: null,
                next_page_url: null,
            },

            // Loading States
            loading: {
                dashboard: true,
                pengajuan: true,
                riwayat: true,
            },

            // Filters
            filters: {
                search: "",
                status: "",
                tipeFilter: "",
                tanggalFilter: "",
            },

            filtersRiwayat: {
                search: "",
                status: "",
                tipeFilter: "",
                tanggalFilter: "",
            },

            // Tabs Configuration
            approvalTabs: [
                { id: "dashboard", label: "Dashboard", icon: "bi-grid" },
                { id: "pengajuan", label: "Pengajuan", icon: "bi-inbox" },
                { id: "riwayat", label: "Riwayat", icon: "bi-clock-history" },
            ],

            // Dashboard Stats
            dashboardStats: [
                {
                    title: "Menunggu Persetujuan",
                    value: "0",
                    icon: "bi-clock",
                    bgClass: "bg-warning",
                },
                {
                    title: "Disetujui",
                    value: "0",
                    icon: "bi-check-circle",
                    bgClass: "bg-success",
                },
                {
                    title: "Ditolak",
                    value: "0",
                    icon: "bi-x-circle",
                    bgClass: "bg-danger",
                },
                {
                    title: "Total Pengajuan",
                    value: "0",
                    icon: "bi-file-text",
                    bgClass: "bg-info",
                },
            ],

            // Chart Configurations
            approvalRateChart: {
                series: [0],
                options: {
                    chart: { type: "radialBar" },
                    plotOptions: {
                        radialBar: {
                            hollow: { size: "70%" },
                            dataLabels: {
                                name: { show: false },
                                value: {
                                    fontSize: "24px",
                                    fontWeight: "bold",
                                    offsetY: 8,
                                },
                            },
                        },
                    },
                    labels: ["Tingkat Persetujuan"],
                },
            },

            leaveTypeChart: {
                series: [],
                options: {
                    chart: { type: "donut" },
                    labels: [],
                    legend: { position: "bottom" },
                    responsive: [
                        {
                            breakpoint: 480,
                            options: {
                                chart: { width: 200 },
                                legend: { position: "bottom" },
                            },
                        },
                    ],
                },
            },

            requestsByEmployeeChart: {
                series: [{ data: [] }],
                options: {
                    chart: { type: "bar" },
                    plotOptions: {
                        bar: { horizontal: true, borderRadius: 4 },
                    },
                    dataLabels: { enabled: false },
                    xaxis: { categories: [] },
                },
            },

            // Debounce timers
            searchTimer: null,
            searchTimerRiwayat: null,
        };
    },
    computed: {
        /* HeaderKpi memvalidasi nilai ini terhadap ['Siang', 'Malam'];
           sebelumnya di-hardcode "day" di data() sehingga selalu ditolak dan
           ikonnya jatuh ke cabang malam. Bentuk computed ini sama dengan
           puluhan halaman lain di repo ini. */
        dayNight() {
            const jam = new Date().getHours();

            return jam >= 6 && jam < 18 ? 'Siang' : 'Malam';
        },
    },
    mounted() {
        this.fetchDashboardData();
    },
    methods: {
        handleTabChange(tabId) {
            this.activeTab = tabId;
            if (tabId === "pengajuan") {
                this.fetchPengajuan();
            } else if (tabId === "riwayat") {
                this.fetchRiwayat();
            } else if (tabId === "dashboard") {
                this.fetchDashboardData();
            }
        },

        async fetchPengajuan(url = null) {
            try {
                this.loading.pengajuan = true;
                const params = new URLSearchParams();

                if (this.filters.search) {
                    params.append("search", this.filters.search);
                }
                if (this.filters.status) {
                    params.append("status", this.filters.status);
                }
                if (this.filters.tipeFilter) {
                    params.append("tipeFilter", this.filters.tipeFilter);
                }
                if (this.filters.tanggalFilter) {
                    params.append("tanggalFilter", this.filters.tanggalFilter);
                }

                const endpoint =
                    url || `/api/v1/izin-atasan-approval?${params.toString()}`;
                const response = await axios.get(endpoint);

                if (response.data.status === 200) {
                    this.pengajuanList = response.data.result || [];
                    this.pengajuanPagination = {
                        current_page: response.pagination.current_page || 1,
                        last_page: response.pagination.last_page || 1,
                        prev_page_url: response.pagination.prev_page_url,
                        next_page_url: response.pagination.next_page_url,
                    };
                } else {
                    console.error("Failed to load pengajuan:", response.data);
                }
            } catch (error) {
                console.error("Error loading pengajuan:", error);
            } finally {
                this.loading.pengajuan = false;
            }
        },

        async fetchRiwayat(url = null) {
            try {
                this.loading.riwayat = true;
                const params = new URLSearchParams();

                if (this.filtersRiwayat.search) {
                    params.append("search", this.filtersRiwayat.search);
                }
                if (this.filtersRiwayat.status) {
                    params.append("status", this.filtersRiwayat.status);
                }
                if (this.filtersRiwayat.tipeFilter) {
                    params.append("tipeFilter", this.filtersRiwayat.tipeFilter);
                }
                if (this.filtersRiwayat.tanggalFilter) {
                    params.append(
                        "tanggalFilter",
                        this.filtersRiwayat.tanggalFilter
                    );
                }

                const endpoint =
                    url ||
                    `/api/v1/izin-atasan-approval/riwayat?${params.toString()}`;
                const response = await axios.get(endpoint);

                if (response.data.status === 200) {
                    this.riwayatList = response.data.result || [];
                    this.riwayatPagination = {
                        current_page: response.pagination.current_page || 1,
                        last_page: response.pagination.last_page || 1,
                        prev_page_url: response.pagination.prev_page_url,
                        next_page_url: response.pagination.next_page_url,
                    };
                } else {
                    console.error("Failed to load riwayat:", response.data);
                }
            } catch (error) {
                console.error("Error loading riwayat:", error);
            } finally {
                this.loading.riwayat = false;
            }
        },

        async fetchDashboardData() {
            this.loading.dashboard = true;
            try {
                // Simulate API call
                setTimeout(() => {
                    this.dashboardStats = [
                        {
                            title: "Menunggu Persetujuan",
                            value: "12",
                            icon: "bi-clock",
                            bgClass: "bg-warning",
                        },
                        {
                            title: "Disetujui",
                            value: "45",
                            icon: "bi-check-circle",
                            bgClass: "bg-success",
                        },
                        {
                            title: "Ditolak",
                            value: "8",
                            icon: "bi-x-circle",
                            bgClass: "bg-danger",
                        },
                        {
                            title: "Total Pengajuan",
                            value: "65",
                            icon: "bi-file-text",
                            bgClass: "bg-info",
                        },
                    ];

                    this.approvalRateChart.series = [75];
                    this.leaveTypeChart.series = [30, 25, 20, 15, 10];
                    this.leaveTypeChart.options.labels = [
                        "Cuti Tahunan",
                        "Izin Sakit",
                        "Izin Pribadi",
                        "Cuti Melahirkan",
                        "Lainnya",
                    ];

                    this.requestsByEmployeeChart.series = [
                        { data: [5, 4, 3, 3, 2, 2, 1] },
                    ];
                    this.requestsByEmployeeChart.options.xaxis.categories = [
                        "Karyawan A",
                        "Karyawan B",
                        "Karyawan C",
                        "Karyawan D",
                        "Karyawan E",
                        "Karyawan F",
                        "Karyawan G",
                    ];

                    this.loading.dashboard = false;
                }, 1000);
            } catch (error) {
                console.error("Error fetching dashboard data:", error);
                this.loading.dashboard = false;
            }
        },

        applyFilters() {
            this.fetchPengajuan();
        },

        applyFiltersRiwayat() {
            this.fetchRiwayat();
        },

        handleSearch() {
            // Clear existing timer
            if (this.searchTimer) {
                clearTimeout(this.searchTimer);
            }

            // Set new timer
            this.searchTimer = setTimeout(() => {
                this.applyFilters();
            }, 500);
        },

        handleSearchRiwayat() {
            // Clear existing timer
            if (this.searchTimerRiwayat) {
                clearTimeout(this.searchTimerRiwayat);
            }

            // Set new timer
            this.searchTimerRiwayat = setTimeout(() => {
                this.applyFiltersRiwayat();
            }, 500);
        },

        viewDetail(item) {
            this.selectedItem = item;
            this.showDetailModal = true;
        },

        closeDetailModal() {
            this.showDetailModal = false;
            this.selectedItem = null;
        },

        async approvePengajuan(item) {
            if (!confirm("Apakah Anda yakin ingin menyetujui pengajuan ini?"))
                return;

            try {
                // Implement approval API call
                const response = await axios.post(
                    `/api/approval/izin/${item.No_Transaksi}/approve`
                );

                if (response.data.success) {
                    alert("Pengajuan berhasil disetujui");
                    this.closeDetailModal();
                    this.fetchPengajuan();
                    this.fetchDashboardData();
                } else {
                    alert(
                        "Gagal menyetujui pengajuan: " + response.data.message
                    );
                }
            } catch (error) {
                console.error("Error approving pengajuan:", error);
                alert("Terjadi kesalahan saat menyetujui pengajuan");
            }
        },

        async rejectPengajuan(item) {
            if (!confirm("Apakah Anda yakin ingin menolak pengajuan ini?"))
                return;

            try {
                // Implement rejection API call
                const response = await axios.post(
                    `/api/approval/izin/${item.No_Transaksi}/reject`
                );

                if (response.data.success) {
                    alert("Pengajuan berhasil ditolak");
                    this.closeDetailModal();
                    this.fetchPengajuan();
                    this.fetchDashboardData();
                } else {
                    alert("Gagal menolak pengajuan: " + response.data.message);
                }
            } catch (error) {
                console.error("Error rejecting pengajuan:", error);
                alert("Terjadi kesalahan saat menolak pengajuan");
            }
        },

        // Utility Functions
        getInitials(name) {
            if (!name) return "??";
            return name
                .split(" ")
                .map((n) => n[0])
                .join("")
                .toUpperCase()
                .substring(0, 2);
        },

        formatDate(dateString) {
            if (!dateString) return "-";
            const date = new Date(dateString);
            return date.toLocaleDateString("id-ID", {
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
            });
        },

        formatDateTime(dateString) {
            if (!dateString) return "-";
            const date = new Date(dateString);
            return date.toLocaleDateString("id-ID", {
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
                hour: "2-digit",
                minute: "2-digit",
            });
        },

        formatDateString(date) {
            return date.toLocaleDateString("id-ID", {
                weekday: "long",
                year: "numeric",
                month: "long",
                day: "numeric",
            });
        },

        truncateText(text, length) {
            if (!text) return "";
            return text.length > length
                ? text.substring(0, length) + "..."
                : text;
        },

        capitalize(text) {
            if (!text) return "";
            return text.charAt(0).toUpperCase() + text.slice(1);
        },

        getBadgeType(item) {
            if (item.status === "approved") return "success";
            if (item.status === "rejected") return "danger";
            return "warning";
        },

        getStatusText(status) {
            const statusMap = {
                pending: "Menunggu",
                approved: "Disetujui",
                rejected: "Ditolak",
            };
            return statusMap[status] || this.capitalize(status);
        },

        getCurrentApproverText(item) {
            if (item.status === "approved") return "Telah Disetujui";
            if (item.status === "rejected") return "Telah Ditolak";
            return item.Approver || "Menunggu Persetujuan";
        },
    },
    beforeUnmount() {
        // Clean up timers when component is destroyed
        if (this.searchTimer) {
            clearTimeout(this.searchTimer);
        }
        if (this.searchTimerRiwayat) {
            clearTimeout(this.searchTimerRiwayat);
        }
    },
};
</script>

<style scoped>
*,
*::before,
*::after {
    --primary: var(--hcis-indigo-500);
    --primary-dark: var(--hcis-indigo-600);
    --primary-light: var(--hcis-indigo-100);
    --secondary: var(--hcis-slate-500);
    --success: var(--hcis-emerald-500);
    --success-light: var(--hcis-emerald-100);
    --warning: var(--hcis-amber-500);
    --warning-light: var(--hcis-amber-100);
    --danger: var(--hcis-red-500);
    --info: var(--hcis-blue-500);
    --info-light: #dbeafe;
    --light: var(--hcis-slate-50);
    --dark: var(--hcis-slate-800);
    --surface: var(--hcis-white);
    --surface-soft: var(--hcis-slate-100);
    --border: var(--hcis-slate-200);
    --text: var(--hcis-slate-700);
    --text-muted: var(--hcis-slate-500);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --radius: 12px;
    --transition: all 0.3s ease;
}
/* Improved Responsive Design */
.container-fluid {
    min-height: 100vh;
    background-color: #f8f9fa;
}

/* Tab Content Improvements */
.tab-content-improved {
    padding: 1rem 0;
}

/* Stat Cards */
.stat-card-dashboard {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.2s ease;
    height: 100%;
}

.stat-card-dashboard:hover {
    transform: translateY(-2px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
}

.stat-content h4 {
    margin: 0;
    font-weight: 700;
    color: #2c3e50;
}

.stat-content p {
    margin: 0.25rem 0 0 0;
    color: #6c757d;
    font-size: 0.875rem;
}

/* Chart Cards */
.chart-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    height: 100%;
}

.chart-title {
    color: #2c3e50;
    font-weight: 600;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}

.chart-skeleton {
    height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.skeleton-loader {
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
    border-radius: 8px;
}

@keyframes loading {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

/* Filter Section */
.filter-section {
    background: white;
    padding: 1rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.search-box {
    position: relative;
    width: 100%;
}

.search-box i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    font-size: 0.875rem;
    transition: border-color 0.2s ease;
}

.search-input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.filter-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    font-size: 0.875rem;
    background: white;
    cursor: pointer;
}

/* Pengajuan Cards */
.pengajuan-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pengajuan-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.pengajuan-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.pengajuan-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    gap: 1rem;
}

.employee-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
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

.pengajuan-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

.pengajuan-date {
    color: #6c757d;
    font-size: 0.8rem;
}

.pengajuan-current-approver {
    background: #f8f9fa;
    padding: 0.75rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: #495057;
}

.pengajuan-content {
    margin-bottom: 1rem;
}

.pengajuan-details {
    display: flex;
    gap: 1rem;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #6c757d;
    font-size: 0.875rem;
}

.pengajuan-reason p {
    color: #495057;
    line-height: 1.5;
    font-size: 0.9rem;
}

.pengajuan-footer {
    border-top: 1px solid var(--hcis-legacy-bootstrap-gray);
    padding-top: 1rem;
}

.pengajuan-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    flex-wrap: wrap;
}

.btn-action {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    font-size: 0.8rem;
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
}

.btn-success {
    background: #e8f5e8;
    color: #2e7d32;
}

.btn-success:hover {
    background: #c8e6c9;
}

.btn-danger {
    background: #ffebee;
    color: #c62828;
}

.btn-danger:hover {
    background: #ffcdd2;
}

/* Table Styles */
.modern-table {
    width: 100%;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.modern-table th {
    background: #f8f9fa;
    padding: 1rem;
    font-weight: 600;
    color: #495057;
    font-size: 0.875rem;
    text-align: left;
    border-bottom: 1px solid #dee2e6;
}

.modern-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--hcis-legacy-bootstrap-gray);
    font-size: 0.875rem;
}

.modern-table tr:last-child td {
    border-bottom: none;
}

/* Loading State */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: #6c757d;
}

.loading-spinner {
    font-size: 2rem;
    margin-bottom: 1rem;
    animation: spin 1s linear infinite;
    color: #007bff;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Empty State */
.empty-state-admin {
    text-align: center;
    padding: 3rem 1rem;
    color: #6c757d;
}

.empty-state-admin i {
    font-size: 4rem;
    margin-bottom: 1rem;
    color: #dee2e6;
}

.empty-state-admin h4 {
    margin-bottom: 0.5rem;
    color: #495057;
}

.empty-state-admin p {
    margin: 0;
    font-size: 0.9rem;
}

/* Detail Modal Styles */
.detail-content-v2 {
    padding: 0.5rem 0;
}

.detail-left-col {
    border-right: 1px solid var(--hcis-legacy-bootstrap-gray);
    padding-right: 2rem;
}

.detail-employee-info {
    text-align: center;
    margin-bottom: 2rem;
}

.detail-status-section {
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
    border-top: 1px solid var(--hcis-legacy-bootstrap-gray);
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

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.btn-pagination {
    padding: 0.5rem 1rem;
    border: 1px solid #dee2e6;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-pagination:hover:not(:disabled) {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

.btn-pagination:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-info {
    color: #6c757d;
    font-size: 0.875rem;
}

/* Mobile Optimizations */
@media (max-width: 768px) {
    .container-fluid {
        padding: 0 0.5rem;
    }

    .pengajuan-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .pengajuan-meta {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        width: 100%;
    }

    .pengajuan-actions {
        justify-content: space-between;
        width: 100%;
    }

    .btn-action {
        flex: 1;
        justify-content: center;
        padding: 0.75rem 0.5rem;
    }

    .detail-left-col {
        border-right: none;
        border-bottom: 1px solid var(--hcis-legacy-bootstrap-gray);
        padding-right: 0;
        padding-bottom: 2rem;
        margin-bottom: 2rem;
    }

    .modal-footer {
        flex-direction: column;
    }

    .btn-modern {
        width: 100%;
    }

    .filter-section .row > div {
        margin-bottom: 0.5rem;
    }

    .pagination-container {
        gap: 0.5rem;
    }

    .pagination-info {
        order: -1;
        width: 100%;
        text-align: center;
        margin-bottom: 0.5rem;
    }
}

@media (max-width: 576px) {
    .stat-card-dashboard {
        flex-direction: column;
        text-align: center;
        padding: 1rem;
    }

    .pengajuan-details {
        flex-direction: column;
        gap: 0.5rem;
    }

    .chart-card {
        padding: 1rem;
    }
}
</style>
