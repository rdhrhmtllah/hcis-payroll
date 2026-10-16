<template>
    <div class="container-fluid px-0">
        <!-- Header -->
        <HeaderKpi
            title="Manajemen Cuti"
            subtitle="Kelola pengajuan cuti dan kuota karyawan"
            :dayNight="dayNight"
            :formattedDate="formatDateString(currentDate)"
        />

        <quick-stat class="" :stats="quickStatField" />

        <!-- Main Content -->
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <ApprovalTabs :tabs="adminTabs" :activeTab="activeTab" @tab-change="handleTabChange">
                    <!-- Tab Pengajuan yang Dioptimalkan -->
                    <template #pengajuan>
                        <div class="tab-content-improved">
                            <!-- Filter & Search Section -->
                            <div class="filter-section mb-4">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-9">
                                        <div class="search-box">
                                            <i
                                                class="d-flex justify-content-center align-items-center bi bi-search"
                                            ></i>
                                            <input
                                                type="text"
                                                v-model="filtersPengajuan.search"
                                                placeholder="Cari berdasarkan nama, no. transaksi, atau jenis cuti..."
                                                class="search-input"
                                                @input="handleSearch"
                                            />
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <select
                                            v-model="filtersPengajuan.status"
                                            class="filter-select"
                                            @change="applyFilters"
                                        >
                                            <option value="">Semua Status</option>
                                            <option value="pending">Menunggu</option>
                                            <option value="approved">Disetujui</option>
                                            <option value="rejected">Ditolak</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Pengajuan List -->
                            <div v-if="loading.pengajuan" class="loading-state">
                                <div class="loading-spinner">
                                    <i
                                        class="bi d-flex justify-content-center align-items-center bi-arrow-repeat d-flex justify-content-center align-items-center"
                                    ></i>
                                </div>
                                <h4 class="loading-title">Memuat Pengajuan...</h4>
                                <p class="loading-description">Harap tunggu sebentar</p>
                            </div>
                            <div v-else class="pengajuan-list">
                                <!-- <div
                                    v-for="pengajuan in pengajuanList"
                                    :key="pengajuan.No_Transaksi"
                                    class="pengajuan-card"
                                >
                                    <div class="pengajuan-header">
                                        <div class="employee-info">
                                            <div class="avatar-sm">
                                                {{
                                                    getInitials(
                                                        pengajuan.nama_karyawan
                                                    )
                                                }}
                                            </div>
                                            <div class="employee-details">
                                                <h6 class="employee-name">
                                                    {{
                                                        pengajuan.nama_karyawan
                                                    }}
                                                </h6>
                                                <p class="employee-code">
                                                    {{
                                                        pengajuan.kode_karyawan
                                                    }}
                                                </p>
                                                <p class="employee-meta">
                                                    {{ pengajuan.divisi }} •
                                                    {{ pengajuan.level }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="pengajuan-meta">
                                            <Badge
                                                :type="
                                                    getBadgeType(
                                                        pengajuan.status
                                                    )
                                                "
                                            >
                                                {{
                                                    capitalize(pengajuan.status)
                                                }}
                                            </Badge>
                                            <span class="pengajuan-date">{{
                                                formatDate(pengajuan.created_at)
                                            }}</span>
                                            <span class="pengajuan-no">{{
                                                pengajuan.No_Transaksi
                                            }}</span>
                                        </div>
                                    </div>

                                    <div class="pengajuan-content">
                                        <div class="pengajuan-details">
                                            <div class="detail-item">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-tag"
                                                ></i>
                                                <span>{{
                                                    pengajuan.jenis_cuti
                                                }}</span>
                                            </div>
                                            <div class="detail-item">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-calendar"
                                                ></i>
                                                <span
                                                    >{{
                                                        formatDate(
                                                            pengajuan.tanggal_mulai
                                                        )
                                                    }}
                                                    -
                                                    {{
                                                        formatDate(
                                                            pengajuan.tanggal_selesai
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                            <div class="detail-item">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-clock"
                                                ></i>
                                                <span
                                                    >{{
                                                        pengajuan.jumlah_hari
                                                    }}
                                                    hari</span
                                                >
                                            </div>
                                        </div>
                                        <div class="pengajuan-reason">
                                            <p>{{ pengajuan.alasan }}</p>
                                        </div>
                                    </div>

                                    <div class="pengajuan-footer">
                                        <div class="pengajuan-actions">
                                            <button
                                                class="btn-action btn-view"
                                                @click="viewDetail(pengajuan)"
                                            >
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-eye"
                                                ></i>
                                                <span>Detail</span>
                                            </button>
                                            <button
                                                v-if="
                                                    pengajuan.status ===
                                                    'pending'
                                                "
                                                class="btn-action btn-success"
                                                @click="
                                                    showApproveModalFC(
                                                        pengajuan
                                                    )
                                                "
                                            >
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-check"
                                                ></i>
                                                <span>Setujui</span>
                                            </button>
                                            <button
                                                v-if="
                                                    pengajuan.status ===
                                                    'pending'
                                                "
                                                class="btn-action btn-danger"
                                                @click="
                                                    openRejectModal(pengajuan)
                                                "
                                            >
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-x"
                                                ></i>
                                                <span>Tolak</span>
                                            </button>
                                        </div>
                                    </div>
                                </div> -->
                                <ModernDataTable
                                    v-if="filteredPengajuanData?.length > 0"
                                    :data="filteredPengajuanData"
                                    :columns="pengajuanColumns"
                                    :mobile-columns="pengajuanMobileColumns"
                                    :loading="loading.pengajuan"
                                    :clickable="true"
                                    :show-pagination="true"
                                    :current-page="paginationPengajuan?.current_page"
                                    :total-pages="paginationPengajuan?.total_pages"
                                    :total-data="paginationPengajuan?.total"
                                    :full-pagination="paginationPengajuan"
                                    card-title-key="requester_Nama"
                                    card-subtitle-key="Tipe_Izin"
                                    card-status-key="status"
                                    empty-message="Belum ada riwayat pengajuan yang sesuai dengan filter Anda."
                                    @row-click="viewDetail"
                                    @page-change="handlePengajuanPageChange"
                                    @empty-action="loadPengajuan"
                                >
                                    <!-- Custom slot untuk kolom Karyawan -->
                                    <template #column-requester_Nama="{ item }">
                                        <div class="employee-info-cell">
                                            <!-- <div class="avatar-sm">
                                                {{
                                                    getInitials(
                                                        item?.requester_Nama
                                                    )
                                                }}
                                            </div> -->
                                            <div class="employee-details">
                                                <h6 class="employee-name">
                                                    {{ item?.requester_Nama }}
                                                </h6>
                                                <p class="employee-dept">
                                                    {{ item?.department || '-' }}
                                                </p>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Custom slot untuk kolom Jenis Izin -->
                                    <template #column-Tipe_Izin="{ item }">
                                        <Badge :type="getJenisIzinBadgeType(item)" class="badge-sm">
                                            {{ getJenisIzinText(item?.Tipe_Izin2 || item?.Tipe_Izin) }}
                                        </Badge>
                                    </template>

                                    <!-- Custom slot untuk kolom Status -->
                                    <template #column-status="{ item }">
                                        <Badge :type="getStatusBadgeType(item?.status)" class="status-badge">
                                            <i :class="getStatusIcon(item?.status)" class="me-1"></i>
                                            {{ getStatusText(item?.status) }}
                                        </Badge>
                                    </template>

                                    <!-- Custom slot untuk kolom Aksi -->
                                    <template #column-actions="{ item }">
                                        <div class="action-cell">
                                            <button
                                                class="btn-action table btn-success"
                                                @click.stop="showApproveModalFC(item)"
                                                title="Setujui Pengajuan"
                                            >
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-check-circle"
                                                ></i>
                                            </button>
                                            <button
                                                class="btn-action table btn-danger"
                                                @click.stop="openRejectModal(item)"
                                                title="Tolak Pengajuan"
                                            >
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-x-circle"
                                                ></i>
                                            </button>
                                            <button
                                                class="btn-action table btn-view"
                                                @click.stop="viewDetail(item)"
                                                title="Lihat Detail"
                                            >
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-eye"
                                                ></i>
                                                <!-- <span class="action-text"
                                                >Detail</span
                                            > -->
                                            </button>
                                        </div>
                                    </template>

                                    <!-- Custom header untuk card view -->
                                    <template #card-header="{ item }">
                                        <div class="card-title">
                                            {{ item?.requester_Nama }}
                                        </div>
                                        <div class="card-subtitle">
                                            {{ getJenisIzinText(item?.Tipe_Izin2 || item?.Tipe_Izin) }}
                                        </div>
                                    </template>

                                    <!-- Custom status untuk card view -->
                                    <template #card-status="{ item }">
                                        <Badge :type="getStatusBadgeType(item?.status)">
                                            <i :class="getStatusIcon(item?.status)" class="me-1"></i>
                                            {{ getStatusText(item?.status) }}
                                        </Badge>
                                    </template>

                                    <!-- Custom value untuk mobile view -->
                                    <template #mobile-TanggalIzin="{ item }">
                                        {{ formatRiwayatDate(item) }}
                                    </template>

                                    <template #mobile-tanggalReal="{ item }">
                                        {{ item?.tanggalReal?.jumlah_hari || '1' }}
                                        hari
                                    </template>

                                    <template #mobile-No_Transaksi="{ item }">
                                        <code class="transaction-id-sm">{{ item?.No_Transaksi }}</code>
                                    </template>

                                    <template #mobile-Alasan="{ item }">
                                        {{ truncateText(item?.Alasan, 60) }}
                                    </template>

                                    <!-- Custom actions untuk mobile -->
                                    <template #card-actions="{ item }">
                                        <button class="card-btn btn-view mobile-btn" @click.stop="viewDetail(item)">
                                            <i class="bi bi-eye"></i>
                                            <span>Lihat Detail</span>
                                        </button>
                                        <!-- <button
                                            class="card-btn btn-view mobile-btn"
                                            @click.stop="viewDetail(item)"
                                        >
                                            <i class="bi bi-eye"></i>
                                            <span>Tolak</span>
                                        </button>
                                        <button
                                            class="card-btn btn-view mobile-btn"
                                            @click.stop="viewDetail(item)"
                                        >
                                            <i class="bi bi-eye"></i>
                                            <span>Setuju</span>
                                        </button> -->
                                    </template>
                                </ModernDataTable>
                            </div>

                            <div v-if="!loading.pengajuan && pengajuanList.length === 0" class="empty-state-admin">
                                <i
                                    class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-inbox"
                                ></i>
                                <h4>Tidak Ada Pengajuan</h4>
                                <p>Tidak ada pengajuan cuti yang ditemukan.</p>
                            </div>
                        </div>
                    </template>

                    <template #riwayat>
                        <div class="tab-content-improved">
                            <!-- Filter & Search Section -->
                            <div class="filter-section mb-4">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-9">
                                        <div class="search-box">
                                            <i
                                                class="d-flex justify-content-center align-items-center bi bi-search"
                                            ></i>
                                            <input
                                                type="text"
                                                v-model="filtersRiwayat.search"
                                                placeholder="Cari berdasarkan nama, no. transaksi, atau jenis cuti..."
                                                class="search-input"
                                                @input="handleSearchRiwayat"
                                            />
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <select
                                            v-model="filtersRiwayat.status"
                                            class="filter-select"
                                            @change="applyFiltersRiwayat"
                                        >
                                            <option value="">Semua Status</option>
                                            <option value="approved">Disetujui</option>
                                            <option value="rejected">Ditolak</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Riwayat List -->
                            <div v-if="loading.riwayat" class="loading-state">
                                <div class="loading-spinner">
                                    <i
                                        class="bi d-flex justify-content-center align-items-center bi-arrow-repeat d-flex justify-content-center align-items-center"
                                    ></i>
                                </div>
                                <h4 class="loading-title">Memuat Riwayat Pengajuan...</h4>
                                <p class="loading-description">Harap tunggu sebentar</p>
                            </div>
                            <div v-else class="pengajuan-list">
                                <!-- <div
                                    v-for="riwayat in riwayatList"
                                    :key="riwayat.No_Transaksi"
                                    class="pengajuan-card"
                                >
                                    <div class="pengajuan-header">
                                        <div class="employee-info">
                                            <div class="avatar-sm">
                                                {{
                                                    getInitials(
                                                        riwayat.nama_karyawan
                                                    )
                                                }}
                                            </div>
                                            <div class="employee-details">
                                                <h6 class="employee-name">
                                                    {{ riwayat.nama_karyawan }}
                                                </h6>
                                                <p class="employee-code">
                                                    {{ riwayat.kode_karyawan }}
                                                </p>
                                                <p class="employee-meta">
                                                    {{ riwayat.divisi }} •
                                                    {{ riwayat.level }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="pengajuan-meta">
                                            <Badge
                                                :type="
                                                    getBadgeType(riwayat.status)
                                                "
                                            >
                                                {{ capitalize(riwayat.status) }}
                                            </Badge>
                                            <span class="pengajuan-date">{{
                                                formatDate(riwayat.created_at)
                                            }}</span>
                                            <span class="pengajuan-no">{{
                                                riwayat.No_Transaksi
                                            }}</span>
                                        </div>
                                    </div>

                                    <div class="pengajuan-content">
                                        <div class="pengajuan-details">
                                            <div class="detail-item">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-tag"
                                                ></i>
                                                <span>{{
                                                    riwayat.jenis_cuti
                                                }}</span>
                                            </div>
                                            <div class="detail-item">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-calendar"
                                                ></i>
                                                <span
                                                    >{{
                                                        formatDate(
                                                            riwayat.tanggal_mulai
                                                        )
                                                    }}
                                                    -
                                                    {{
                                                        formatDate(
                                                            riwayat.tanggal_selesai
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                            <div class="detail-item">
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-clock"
                                                ></i>
                                                <span
                                                    >{{
                                                        riwayat.jumlah_hari
                                                    }}
                                                    hari</span
                                                >
                                            </div>
                                        </div>
                                        <div class="pengajuan-reason">
                                            <p>{{ riwayat.alasan }}</p>
                                        </div>
                                    </div>

                                    <div class="pengajuan-footer">
                                        <div class="pengajuan-actions">
                                            <button
                                                class="btn-action btn-view"
                                                @click="viewDetail(riwayat)"
                                            >
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-eye"
                                                ></i>
                                                <span>Detail</span>
                                            </button>
                                        </div>
                                    </div>
                                </div> -->
                                <ModernDataTable
                                    v-if="filteredRiwayatPengajuanData?.length > 0"
                                    :data="filteredRiwayatPengajuanData"
                                    :columns="riwayatColumns"
                                    :mobile-columns="riwayatMobileColumns"
                                    :loading="loading.riwayat"
                                    :clickable="true"
                                    :show-pagination="true"
                                    :current-page="paginationRiwayat?.current_page"
                                    :total-pages="paginationRiwayat?.total_pages"
                                    :total-data="paginationRiwayat?.total"
                                    :full-pagination="paginationRiwayat"
                                    card-title-key="requester_Nama"
                                    card-subtitle-key="Tipe_Izin"
                                    card-status-key="status"
                                    empty-message="Belum ada riwayat pengajuan yang sesuai dengan filter Anda."
                                    @row-click="(row) => viewDetail(row, true)"
                                    @page-change="handleRiwayatPageChange"
                                    @empty-action="loadRiwayat"
                                >
                                    <!-- Custom slot untuk kolom Karyawan -->
                                    <template #column-requester_Nama="{ item }">
                                        <div class="employee-info-cell">
                                            <!-- <div class="avatar-sm">
                                                {{
                                                    getInitials(
                                                        item?.requester_Nama
                                                    )
                                                }}
                                            </div> -->
                                            <div class="employee-details">
                                                <h6 class="employee-name">
                                                    {{ item?.requester_Nama }}
                                                </h6>
                                                <p class="employee-dept">
                                                    {{ item?.department || '-' }}
                                                </p>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Custom slot untuk kolom Jenis Izin -->
                                    <template #column-Tipe_Izin="{ item }">
                                        <Badge :type="getJenisIzinBadgeType(item)" class="badge-sm">
                                            {{ getJenisIzinText(item?.Tipe_Izin2 || item?.Tipe_Izin) }}
                                        </Badge>
                                    </template>

                                    <!-- Custom slot untuk kolom Status -->
                                    <template #column-status="{ item }">
                                        <Badge :type="getStatusBadgeType(item?.status)" class="status-badge">
                                            <i :class="getStatusIcon(item?.status)" class="me-1"></i>
                                            {{ getStatusText(item?.status) }}
                                        </Badge>
                                    </template>

                                    <!-- Custom slot untuk kolom Aksi -->
                                    <template #column-actions="{ item }">
                                        <div class="action-cell">
                                            <button
                                                class="btn-action table btn-view"
                                                @click.stop="viewDetail(item, true)"
                                                title="Lihat Detail"
                                            >
                                                <i
                                                    class="bi bi-eye d-flex justify-content-center align-items-center"
                                                ></i>
                                                <!-- <span class="action-text"
                                                >Detail</span
                                            > -->
                                            </button>
                                        </div>
                                    </template>

                                    <!-- Custom header untuk card view -->
                                    <template #card-header="{ item }">
                                        <div class="card-title">
                                            {{ item?.requester_Nama }}
                                        </div>
                                        <div class="card-subtitle">
                                            {{ getJenisIzinText(item?.Tipe_Izin2 || item?.Tipe_Izin) }}
                                        </div>
                                    </template>

                                    <!-- Custom status untuk card view -->
                                    <template #card-status="{ item }">
                                        <Badge :type="getStatusBadgeType(item?.status)">
                                            <i :class="getStatusIcon(item?.status)" class="me-1"></i>
                                            {{ getStatusText(item?.status) }}
                                        </Badge>
                                    </template>

                                    <!-- Custom value untuk mobile view -->
                                    <template #mobile-TanggalIzin="{ item }">
                                        {{ formatRiwayatDate(item) }}
                                    </template>

                                    <template #mobile-tanggalReal="{ item }">
                                        {{ item?.tanggalReal?.jumlah_hari || '1' }}
                                        hari
                                    </template>

                                    <template #mobile-No_Transaksi="{ item }">
                                        <code class="transaction-id-sm">{{ item?.No_Transaksi }}</code>
                                    </template>

                                    <template #mobile-Alasan="{ item }">
                                        {{ truncateText(item?.Alasan, 60) }}
                                    </template>

                                    <!-- Custom actions untuk mobile -->
                                    <template #card-actions="{ item }">
                                        <button
                                            class="card-btn btn-view mobile-btn"
                                            @click.stop="viewDetail(item, true)"
                                        >
                                            <i class="bi bi-eye"></i>
                                            <span>Lihat Detail</span>
                                        </button>
                                    </template>
                                </ModernDataTable>
                            </div>

                            <!-- Empty State -->
                            <div v-if="!loading.riwayat && riwayatList.length === 0" class="empty-state-admin">
                                <i
                                    class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-inbox"
                                ></i>
                                <h4>Tidak Ada Riwayat Pengajuan</h4>
                                <p>Tidak ada riwayat pengajuan cuti yang ditemukan.</p>
                            </div>
                        </div>
                    </template>

                    <!-- Tab Jenis Cuti yang Dioptimalkan -->
                    <template #jenis_cuti>
                        <div class="tab-content-improved">
                            <div class="jenis-cuti-container">
                                <!-- Search and Filter -->
                                <div class="filter-section mb-3">
                                    <div class="row g-2 align-items-center">
                                        <div class="col-md-12">
                                            <div class="search-box">
                                                <i
                                                    class="d-flex justify-content-center align-items-center bi bi-search"
                                                ></i>
                                                <input
                                                    type="text"
                                                    v-model="filtersJenis.search"
                                                    placeholder="Cari Jenis Cuti..."
                                                    class="search-input"
                                                    @input="handleSearchJenis"
                                                />
                                            </div>
                                        </div>
                                        <!-- <div class="col-md-3">
                                            <select
                                                v-model="filters.division"
                                                class="filter-select"
                                                @change="fetchApprovals"
                                            >
                                                <option value="">
                                                    All Division
                                                </option>
                                                <option
                                                    v-for="division in filterOptions.divisions"
                                                    :key="division.id"
                                                    :value="division.id"
                                                >
                                                    {{ division.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <select
                                                v-model="filters.level"
                                                class="filter-select"
                                                @change="fetchApprovals"
                                            >
                                                <option value="">
                                                    All Level
                                                </option>
                                                <option
                                                    v-for="level in filterOptions.levels"
                                                    :key="level.id"
                                                    :value="level.id"
                                                >
                                                    {{ level.name }}
                                                </option>
                                            </select>
                                        </div> -->
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-12">
                                            <div class="filter-info">
                                                <span class="filter-text"
                                                    >Showing
                                                    {{ jenisCutiList.length }}
                                                    results</span
                                                >
                                                <button class="btn-clear-filters" @click="clearFilters">
                                                    <i
                                                        class="d-flex justify-content-center align-items-center bi bi-arrow-clockwise"
                                                    ></i>
                                                    Reset Filters
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-8 order-2 order-lg-1">
                                        <div>
                                            <div v-if="!loading.jenisCuti" class="h-100">
                                                <div v-if="filteredJenisCuti?.length != 0" class="jenis-cuti-grid">
                                                    <div
                                                        v-for="jenis in filteredJenisCuti"
                                                        :key="jenis.Id_Jenis_Cuti"
                                                        class="jenis-cuti-card"
                                                    >
                                                        <div class="jenis-cuti-header">
                                                            <div class="jenis-cuti-icon">
                                                                <i
                                                                    class="d-flex justify-content-center align-items-center"
                                                                    :class="getCutiTypeIcon(jenis.Nama_Jenis_Cuti)"
                                                                ></i>
                                                            </div>
                                                            <div class="jenis-cuti-info">
                                                                <h6>
                                                                    {{ jenis.Nama_Jenis_Cuti }}
                                                                </h6>
                                                                <p>
                                                                    {{ jenis.Deskripsi }}
                                                                </p>
                                                            </div>
                                                            <div class="jenis-cuti-badge">
                                                                {{ jenis.Created_At }}
                                                                oleh
                                                                {{ jenis.Nama_Pembuat }}
                                                            </div>
                                                        </div>

                                                        <div class="jenis-cuti-details">
                                                            <div class="detail-row">
                                                                <span class="label">Masa Berlaku:</span>
                                                                <span class="value"
                                                                    >{{
                                                                        jenis.Masa_Berlaku_Bulan || 'Tidak ada'
                                                                    }}
                                                                    bulan</span
                                                                >
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="label">Minimal Masa Kerja:</span>
                                                                <span class="value"
                                                                    >{{
                                                                        jenis.Minimal_Masa_Kerja_Bulan || 0
                                                                    }}
                                                                    bulan</span
                                                                >
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="label">Maksimum Pengajuan:</span>
                                                                <span class="value">{{
                                                                    jenis.Maksimum_Pengajuan || 'Tidak ada'
                                                                }}</span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="label">Hutang Cuti:</span>
                                                                <span
                                                                    class="value"
                                                                    v-if="jenis.Flag_Bisa_Hutang_Cuti === 'Y'"
                                                                >
                                                                    {{
                                                                        jenis.Maks_Hutang_Cuti
                                                                            ? jenis.Maks_Hutang_Cuti + ' hari'
                                                                            : 'Tanpa batas'
                                                                    }}
                                                                    ·
                                                                    {{
                                                                        jenis.Hutang_Cuti_Mode === 'PERIODIK'
                                                                            ? 'Periodik'
                                                                            : 'Outstanding'
                                                                    }}<template
                                                                        v-if="jenis.Hutang_Cuti_Mode === 'PERIODIK'"
                                                                    >
                                                                        ({{
                                                                            jenis.Hutang_Cuti_Basis_Periode ===
                                                                            'JOIN_DATE'
                                                                                ? 'Join Date'
                                                                                : 'Kalender'
                                                                        }}
                                                                        ·
                                                                        {{
                                                                            (jenis.Hutang_Periodik_Perhitungan ||
                                                                                'GROSS') === 'NETTO'
                                                                                ? 'Netto'
                                                                                : 'Gross'
                                                                        }})</template
                                                                    >
                                                                </span>
                                                                <span class="value" v-else>Tidak boleh</span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="label">Pengaturan:</span>
                                                                <div class="settings">
                                                                    <span
                                                                        v-if="jenis.Dapat_Diakumulasi === 'Y'"
                                                                        class="setting-badge"
                                                                        >Dapat Diakumulasi</span
                                                                    >
                                                                    <span
                                                                        v-if="jenis.Perlu_Persetujuan_Khusus === 'Y'"
                                                                        class="setting-badge"
                                                                        >Perlu Persetujuan Khusus</span
                                                                    >
                                                                    <span
                                                                        v-if="jenis.Dokumen_Pendukung === 'Y'"
                                                                        class="setting-badge"
                                                                        >Dokumen Pendukung</span
                                                                    >
                                                                    <span
                                                                        v-if="jenis.Gender_Spesifik"
                                                                        class="setting-badge"
                                                                        >{{ jenis.Gender_Spesifik }}</span
                                                                    >
                                                                </div>
                                                            </div>
                                                            <div
                                                                class="detail-row"
                                                                v-if="jenis.Perlu_Persetujuan_Khusus === 'Y'"
                                                            >
                                                                <span class="label">Alur Approval:</span>
                                                                <div class="settings">
                                                                    <template
                                                                        v-if="jenis.Approver && jenis.Approver.length"
                                                                    >
                                                                        <span
                                                                            v-for="(ap, i) in sortApprover(
                                                                                jenis.Approver,
                                                                            )"
                                                                            :key="ap.Id_Cuti_Approval || i"
                                                                            class="setting-badge"
                                                                        >
                                                                            {{ i + 1 }}. {{ ap.Nama_Approval
                                                                            }}<template
                                                                                v-if="
                                                                                    ap.Source_Type === 'TABLE' &&
                                                                                    ap.Order_Approver_Limit
                                                                                "
                                                                            >
                                                                                (maks
                                                                                {{ ap.Order_Approver_Limit }} level
                                                                                atasan)</template
                                                                            >
                                                                        </span>
                                                                    </template>
                                                                    <span class="value" v-else>Belum diatur</span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="jenis-cuti-actions">
                                                            <button
                                                                class="btn-action btn-edit"
                                                                @click="editJenisCuti(jenis)"
                                                            >
                                                                <i
                                                                    class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-pencil"
                                                                ></i>
                                                                <span>Edit</span>
                                                            </button>
                                                            <button
                                                                class="btn-action btn-danger"
                                                                @click="openDeleteJenisCutiModal(jenis)"
                                                            >
                                                                <i
                                                                    class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-trash"
                                                                ></i>
                                                                <span>Hapus</span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div
                                                    v-else
                                                    class="empty-state h-100 d-flex justify-content-center align-items-center flex-column"
                                                >
                                                    <div class="empty-icon mb-4">
                                                        <DotLottieVue
                                                            style="height: 150px; width: 150px"
                                                            autoplay
                                                            loop
                                                            src="/animation/empty-data.json"
                                                        />
                                                    </div>
                                                    <h4>Tidak Ada Data</h4>
                                                    <p>Belum ada Jenis Cuti yang ditambahkan</p>
                                                </div>
                                            </div>

                                            <div v-else class="row justify-content-center fade-in">
                                                <div class="col-12">
                                                    <div class="loading-state">
                                                        <div class="loading-spinner">
                                                            <i
                                                                class="bi d-flex justify-content-center align-items-center bi-arrow-repeat d-flex justify-content-center align-items-center"
                                                            ></i>
                                                        </div>
                                                        <h4 class="loading-title">Memuat Data Team...</h4>
                                                        <p class="loading-description">Sedang mengambil data team</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="paginationJenisCuti.total > 0" class="unix-pagination-container">
                                            <button
                                                @click="loadJenisCuti(paginationJenisCuti.prev_page_url)"
                                                :disabled="paginationJenisCuti.prev_page_url == null"
                                                class="unix-pagination-btn"
                                                aria-label="Previous page"
                                            >
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-chevron-left"
                                                ></i>
                                            </button>
                                            <!-- <div class="unix-pagination-pages">
                                                <button
                                                    v-for="(
                                                        page, index
                                                    ) in paginationJenisCuti.links"
                                                    :key="index"
                                                    @click="
                                                        loadJenisCuti(page.url)
                                                    "
                                                    :class="{
                                                        active: page.active,
                                                    }"
                                                    class="unix-pagination-btn page-number"
                                                    v-html="page.label"
                                                ></button>
                                            </div> -->
                                            <button
                                                @click="loadJenisCuti(paginationJenisCuti.next_page_url)"
                                                :disabled="paginationJenisCuti.next_page_url == null"
                                                class="unix-pagination-btn"
                                                aria-label="Next page"
                                            >
                                                <i
                                                    class="bi d-flex justify-content-center align-items-center bi-chevron-right"
                                                ></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="col-md-4 order-1 order-lg-2">
                                        <div class="action-sidebar">
                                            <div class="action-card">
                                                <h6 class="action-title">
                                                    <i
                                                        class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-plus-circle me-2"
                                                    ></i>
                                                    Tambah Jenis Cuti Baru
                                                </h6>
                                                <p class="action-description">
                                                    Buat jenis cuti baru dengan kuota dan aturan khusus
                                                </p>
                                                <button
                                                    class="btn-modern btn-primary w-100"
                                                    @click="showJenisCutiModalFC"
                                                >
                                                    <i
                                                        class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-plus me-2"
                                                    ></i
                                                    >Tambah Jenis Cuti
                                                </button>
                                            </div>

                                            <div class="info-card-admin d-none d-md-block">
                                                <h6 class="info-title">
                                                    <i
                                                        class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-info-circle me-2"
                                                    ></i>
                                                    Tips Manajemen
                                                </h6>
                                                <div class="tips-list">
                                                    <div class="tip-item">
                                                        <i
                                                            class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-check-circle"
                                                        ></i>
                                                        <p>Atur kuota sesuai kebutuhan perusahaan</p>
                                                    </div>
                                                    <div class="tip-item">
                                                        <i
                                                            class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-check-circle"
                                                        ></i>
                                                        <p>Tinjau masa berlaku untuk cuti tertentu</p>
                                                    </div>
                                                    <div class="tip-item">
                                                        <i
                                                            class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-check-circle"
                                                        ></i>
                                                        <p>Perhatikan minimal masa kerja</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Tab Cuti Bersama -->
                    <template #cuti_bersama>
                        <div class="tab-content">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="tab-title">Cuti Bersama</h5>
                                <button class="btn-modern btn-primary" @click="showCutiBersamaModalFC">
                                    <i
                                        class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-plus me-2"
                                    ></i
                                    >Tambah Cuti Bersama
                                </button>
                            </div>

                            <!-- Table Cuti Bersama -->
                            <div class="table-responsive">
                                <table class="modern-table">
                                    <thead>
                                        <tr>
                                            <th>Nama</th>
                                            <th>Tanggal Mulai</th>
                                            <th>Tanggal Selesai</th>
                                            <th>Jumlah Hari</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="item in cutiBersamaList" :key="item.Id_Cuti_Bersama">
                                            <td>
                                                {{ item.Nama_Cuti_Bersama }}
                                            </td>
                                            <td>
                                                {{ formatDate(item.Tanggal_Mulai) }}
                                            </td>
                                            <td>
                                                {{ formatDate(item.Tanggal_Selesai) }}
                                            </td>
                                            <td>{{ item.Jumlah_Hari }}</td>
                                            <td>
                                                <Badge
                                                    :type="
                                                        getCutiBersamaStatus(item) === 'Aktif' ? 'success' : 'secondary'
                                                    "
                                                >
                                                    {{ getCutiBersamaStatus(item) }}
                                                </Badge>
                                            </td>
                                            <td>
                                                <div class="action-cell">
                                                    <!-- <button
                                                        v-if="
                                                            getCutiBersamaStatus(
                                                                item
                                                            ) === 'Aktif'
                                                        "
                                                        class="btn-action btn-danger"
                                                        @click="
                                                            batalkanCutiBersama(
                                                                item
                                                            )
                                                        "
                                                        title="Batalkan"
                                                    >
                                                        <i
                                                            class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-x-circle"
                                                        ></i>
                                                        Batalkan
                                                    </button> -->
                                                    <button
                                                        class="btn-action btn-edit"
                                                        @click="editCutiBersama(item)"
                                                        title="Edit"
                                                    >
                                                        <i
                                                            class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-pencil"
                                                        ></i>
                                                        Edit
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>

                    <!-- Tab Kuota Karyawan -->
                    <!-- <template #kuota>
                        <div class="tab-content">
                            <div
                                class="d-flex justify-content-between align-items-center mb-4"
                            >
                                <h5 class="tab-title">Kuota Cuti Karyawan</h5>
                                <div class="search-box">
                                    <i
                                        class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-search"
                                    ></i>
                                    <input
                                        type="text"
                                        v-model="searchKuota"
                                        placeholder="Cari karyawan..."
                                        class="search-input"
                                    />
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="modern-table">
                                    <thead>
                                        <tr>
                                            <th>Kode</th>
                                            <th>Nama Karyawan</th>
                                            <th>Department</th>
                                            <th>Level</th>
                                            <th>Cuti Tahunan</th>
                                            <th>Hutang Cuti</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="item in filteredKuota"
                                            :key="item.id"
                                        >
                                            <td>{{ item.kode_karyawan }}</td>
                                            <td>{{ item.nama_karyawan }}</td>
                                            <td>{{ item.department }}</td>
                                            <td>{{ item.level }}</td>
                                            <td>
                                                {{ item.sisa_cuti_tahunan }}
                                            </td>
                                            <td>{{ item.hutang_cuti }}</td>
                                            <td>
                                                <button
                                                    class="btn-modern btn-outline"
                                                    @click="adjustKuota(item)"
                                                >
                                                    <i
                                                        class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-pencil me-2"
                                                    ></i
                                                    >Adjust
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template> -->
                </ApprovalTabs>
            </div>
        </div>

        <!-- Modal Detail Pengajuan -->
        <!-- <Modal
            v-if="showDetailModal"
            :show="showDetailModal"
            title="Detail Pengajuan Cuti"
            size="large"
            @close="closeDetailModal"
        >
            <template #body>
                <div v-if="!loading.pengajuanDetail">
                    <div v-if="selectedPengajuan" class="detail-content">
                        <div class="detail-section">
                            <div class="detail-row">
                                <div class="detail-label">No. Transaksi</div>
                                <div class="detail-value">
                                    {{ selectedPengajuan.No_Transaksi }}
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Nama Karyawan</div>
                                <div class="detail-value">
                                    {{ selectedPengajuan.nama_karyawan }}
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Divisi & Level</div>
                                <div class="detail-value">
                                    {{ selectedPengajuan.divisi }} •
                                    {{ selectedPengajuan.level }}
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Jenis Cuti</div>
                                <div class="detail-value">
                                    {{ selectedPengajuan.jenis_cuti }}
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Periode</div>
                                <div class="detail-value">
                                    {{
                                        formatDate(
                                            selectedPengajuan.tanggal_mulai
                                        )
                                    }}
                                    -
                                    {{
                                        formatDate(
                                            selectedPengajuan.tanggal_selesai
                                        )
                                    }}
                                    ({{ selectedPengajuan.jumlah_hari }} hari)
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Alasan</div>
                                <div class="detail-value">
                                    {{ selectedPengajuan.alasan }}
                                </div>
                            </div>
                            <div
                                v-if="selectedPengajuan.file_pendukung"
                                class="detail-row"
                            >
                                <div class="detail-label">File Pendukung</div>
                                <div class="detail-value">
                                    <a
                                        :href="selectedPengajuan.file_pendukung"
                                        target="_blank"
                                        class="file-link"
                                    >
                                        <i
                                            class="bi d-flex justify-content-center align-items-center d-flex justify-content-center items-center-center bi-paperclip me-2"
                                        ></i
                                        >Lihat File
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="empty-state d-flex justify-content-center align-items-center flex-column"
                    >
                        <div class="empty-icon mb-4">
                            <DotLottieVue
                                style="height: 150px; width: 150px"
                                autoplay
                                loop
                                src="/animation/empty-data.json"
                            />
                        </div>
                        <h4>Tidak Ada Anggota Tim</h4>
                        <p>Belum ada anggota tim yang tersedia</p>
                    </div>
                </div>
                <div v-else class="row justify-content-center fade-in">
                    <div class="col-12">
                        <div class="loading-state">
                            <div class="loading-spinner">
                                <i
                                    class="bi d-flex justify-content-center align-items-center bi-arrow-repeat d-flex justify-content-center align-items-center"
                                ></i>
                            </div>
                            <h4 class="loading-title">Memuat Data...</h4>
                            <p class="loading-description">
                                Sedang mengambil data detail
                            </p>
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
                        v-if="selectedPengajuan?.status === 'pending'"
                        class="btn-modern btn-danger"
                        @click="rejectSelected"
                    >
                        Tolak
                    </button>
                    <button
                        v-if="selectedPengajuan?.status === 'pending'"
                        class="btn-modern btn-success"
                        @click="approveSelected"
                    >
                        Setujui
                    </button>
                </div>
            </template>
        </Modal> -->

        <!-- Modal tolak pengajuan -->
        <Modal
            :show="showRejectModal"
            :zIndex="1060"
            :title="
                selectedPengajuan
                    ? 'Tolak Pengajuan Cuti - ' + selectedPengajuan.requester_Nama
                    : 'Tolak Pengajuan Cuti - Tidak Diketahui'
            "
            size="medium"
            @close="closeRejectModal"
        >
            <template #body>
                <p>
                    Anda yakin ingin menolak pengajuan cuti ini? <br />
                    <span class="fw-bold">Tindakan ini tidak dapat dibatalkan.</span>
                </p>
                <div class="form-group">
                    <label for="rejectionReason">Alasan Penolakan</label>
                    <textarea
                        id="rejectionReason"
                        v-model="rejectionReason"
                        class="form-control"
                        rows="3"
                        placeholder="Masukkan alasan penolakan (opsional)"
                    ></textarea>
                </div>
            </template>
            <template #footer>
                <button class="btn-modern btn-secondary" @click="closeRejectModal">Batal</button>
                <button class="btn-modern btn-danger" @click="confirmRejectPengajuan" :disabled="loading.submit">
                    <span v-if="!loading.submit">
                        <i class="bi d-flex justify-content-center align-items-center bi-trash"></i>
                    </span>
                    <span v-else>
                        <i class="bi bi-arrow-repeat spin d-inline-flex justify-content-center align-items-center"></i>
                    </span>
                    <span v-if="!loading.submit" class="ms-2"> Ya, Tolak </span>
                    <span v-else class="ms-2"> Memuat... </span>
                </button>
            </template>
        </Modal>

        <!-- Modal Jenis Cuti -->
        <Modal
            v-if="showJenisCutiModal"
            :show="showJenisCutiModal"
            :title="isEditingJenisCuti ? 'Edit Jenis Cuti' : 'Tambah Jenis Cuti'"
            size="large"
            @close="closeJenisCutiModal"
        >
            <template #body>
                <form @submit.prevent="submitJenisCuti">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Nama Jenis Cuti</label>
                                <input
                                    type="text"
                                    v-model="jenisCutiForm.Nama_Jenis_Cuti"
                                    class="form-control"
                                    required
                                />
                            </div>
                        </div>
                        <!-- <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Kuota Default</label>
                                <input
                                    type="number"
                                    v-model="jenisCutiForm.Kuota_Default"
                                    class="form-control"
                                    required
                                />
                            </div>
                        </div> -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Batas Tanggal Pengajuan(Hari)</label>
                                <input
                                    type="number"
                                    v-model="jenisCutiForm.Batas_Hari_Pengajuan"
                                    class="form-control"
                                    placeholder="Batas Pengajuan H-1, H+1, dst.."
                                    required
                                />
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Deskripsi</label>
                                <textarea v-model="jenisCutiForm.Deskripsi" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Mode Tanggal </label>
                                <select v-model="jenisCutiForm.mode_tanggal" class="form-control" required>
                                    <option value="MULTIPLE">Multiple</option>
                                    <option value="RANGE">Range</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Masa Berlaku (bulan)</label>
                                <input
                                    type="number"
                                    v-model="jenisCutiForm.Masa_Berlaku_Bulan"
                                    class="form-control"
                                    placeholder="Kosongkan jika tidak ada"
                                />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Maksimum Pengajuan *</label>
                                <input
                                    type="number"
                                    v-model="jenisCutiForm.Maksimum_Pengajuan"
                                    class="form-control"
                                    required
                                />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Minimal Masa Kerja (bulan)</label>
                                <input
                                    type="number"
                                    v-model="jenisCutiForm.Minimal_Masa_Kerja_Bulan"
                                    class="form-control"
                                />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Gender Spesifik</label>
                                <select v-model="jenisCutiForm.Gender_Spesifik" class="form-control">
                                    <option value="">Tidak Spesifik</option>
                                    <option value="Laki-Laki">Laki-laki</option>
                                    <option value="Perempuan">Perempuan</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Tahap Approval</label>
                                <!-- <v-select
                                    v-model="jenisCutiForm.Tahap_Approval"
                                    :options="tahapApprovalOptions"
                                    label="label"
                                    :reduce="(option) => option.value"
                                    multiple
                                    class="form-control"
                                /> -->
                                <div
                                    @click="openApprovalFlowModal"
                                    class="form-control d-flex justify-content-between approvalFlow"
                                    style="cursor: pointer"
                                    :class="{
                                        disabled: jenisCutiForm.Perlu_Persetujuan_Khusus == 'T',
                                    }"
                                >
                                    <span class="opacity-50">{{
                                        jenisCutiForm.Approver && jenisCutiForm.Approver?.length
                                            ? `${jenisCutiForm.Approver?.length} Tahapan `
                                            : 'Pilih Tahapan'
                                    }}</span>
                                    <div class="">
                                        <i
                                            class="bi d-flex justify-content-center align-items-center bi-arrows-angle-expand opacity-50"
                                        ></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- <div class="col-md-4">
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    v-model="jenisCutiForm.Dapat_Diakumulasi"
                                    true-value="Y"
                                    false-value="T"
                                    class="form-check-input"
                                />
                                <label class="form-check-label"
                                    >Dapat Diakumulasi</label
                                >
                            </div>
                        </div> -->
                        <div class="col-md-3">
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    v-model="jenisCutiForm.Memotong_Saldo_Cuti"
                                    true-value="Y"
                                    false-value="T"
                                    class="form-check-input"
                                />
                                <label class="form-check-label">Memotong saldo cuti</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    v-model="jenisCutiForm.Perlu_Persetujuan_Khusus"
                                    true-value="T"
                                    false-value="Y"
                                    class="form-check-input"
                                />
                                <label class="form-check-label">Tidak Perlu Persetujuan </label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    v-model="jenisCutiForm.Dokumen_Pendukung"
                                    true-value="Y"
                                    false-value="T"
                                    class="form-check-input"
                                />
                                <label class="form-check-label">Dokumen Pendukung</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    v-model="jenisCutiForm.Flag_Bisa_Hutang_Cuti"
                                    true-value="Y"
                                    false-value="T"
                                    class="form-check-input"
                                />
                                <label class="form-check-label">Bisa Hutang Cuti</label>
                            </div>
                        </div>
                    </div>
                    <div v-if="jenisCutiForm.Flag_Bisa_Hutang_Cuti === 'Y'" class="row g-3 mt-1">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Maks Hutang Cuti (hari)</label>
                                <input
                                    type="number"
                                    min="0"
                                    v-model="jenisCutiForm.Maks_Hutang_Cuti"
                                    class="form-control"
                                    placeholder="Kosongkan = tanpa batas angka"
                                />
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Mode Batas Hutang</label>
                                <select v-model="jenisCutiForm.Hutang_Cuti_Mode" class="form-control">
                                    <option value="OUTSTANDING">Outstanding (sisa hutang berjalan)</option>
                                    <option value="PERIODIK">Periodik (reset tiap periode)</option>
                                </select>
                            </div>
                        </div>
                        <div v-if="jenisCutiForm.Hutang_Cuti_Mode === 'PERIODIK'" class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Basis Periode</label>
                                <select v-model="jenisCutiForm.Hutang_Cuti_Basis_Periode" class="form-control">
                                    <option value="KALENDER">Tahun Kalender (Jan - Des)</option>
                                    <option value="JOIN_DATE">Tahun Join Date (anniversary)</option>
                                </select>
                            </div>
                        </div>
                        <div v-if="jenisCutiForm.Hutang_Cuti_Mode === 'PERIODIK'" class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Perhitungan Jatah</label>
                                <select v-model="jenisCutiForm.Hutang_Periodik_Perhitungan" class="form-control">
                                    <option value="GROSS">Total dipinjam (pelunasan tak menambah jatah)</option>
                                    <option value="NETTO">Sisa berjalan (pelunasan menambah jatah)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="d-flex w-100 gap-2 justify-content-md-end">
                    <button class="btn-modern btn-secondary" @click="closeJenisCutiModal">Batal</button>
                    <button class="btn-modern btn-primary" @click="showConfirmJenisCutiFC">
                        {{ isEditingJenisCuti ? 'Update' : 'Simpan' }}
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Modal konfirmasi simpan/update jenis cuti -->

        <Modal
            :show="showConfirmJenisCutiModal"
            :title="isEditingJenisCuti ? `Konfirmasi mengubah Jenis Cuti` : `Konfirmasi menambahkan Jenis Cuti`"
            size="medium"
            :z-index="1200"
            @close="closeConfirmJenisCutiFC"
        >
            <template #body>
                <p>
                    Anda yakin ingin
                    {{ isEditingJenisCuti ? 'mengubah' : 'menambahkan' }} jenis cuti ini?
                </p>
            </template>
            <template #footer>
                <button class="btn-modern btn-secondary" @click="closeConfirmJenisCutiFC">Batal</button>
                <button class="btn-modern btn-primary" @click="submitJenisCuti">
                    <span v-if="!loading.submit">
                        <i class="bi d-flex justify-content-center align-items-center bi-check-circle"></i>
                    </span>
                    <lord-icon
                        v-else
                        src="https://cdn.lordicon.com/ibckyoan.json"
                        trigger="loop"
                        delay="0"
                        colors="primary:#ffffff"
                        style="width: 25px; height: 25px"
                    />
                    <span v-if="!loading.submit" class="ms-2"> Ya, Lanjutkan </span>
                    <span v-else class="ms-2"> Memuat... </span>
                </button>
            </template>
        </Modal>

        <!-- Modal delete jenis cuti -->
        <Modal
            :show="showDeleteJenisCutiModal"
            title="Hapus Jenis Cuti"
            size="small"
            @close="closeDeleteJenisCutiModal"
        >
            <template #body>
                <p>
                    Anda yakin ingin menghapus jenis cuti "
                    <span class="fw-bold">{{ selectedJenisCuti?.Nama_Jenis_Cuti }}</span
                    >"? Tindakan ini tidak dapat dibatalkan.
                </p>
            </template>
            <template #footer>
                <button class="btn-modern btn-secondary" @click="closeDeleteJenisCutiModal">Batal</button>
                <button class="btn-modern btn-danger" @click="confirmDeleteJenisCuti">Hapus</button>
            </template>
        </Modal>

        <!-- Modal Adjust Kuota -->
        <Modal v-if="showAdjustModal" :show="showAdjustModal" title="Adjust Kuota Cuti" @close="closeAdjustModal">
            <template #body>
                <form @submit.prevent="submitAdjustKuota">
                    <div class="form-group">
                        <label class="form-label">Karyawan</label>
                        <input type="text" :value="selectedKuota?.nama_karyawan" class="form-control" disabled />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kode Karyawan</label>
                        <input type="text" :value="selectedKuota?.kode_karyawan" class="form-control" disabled />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jenis Cuti</label>
                        <select v-model="adjustForm.tipe_cuti" class="form-control" disabled>
                            <option value="tahunan">Cuti Tahunan</option>
                            <option value="hutang">Hutang Cuti</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Saldo</label>
                        <input type="number" v-model="adjustForm.jumlah" class="form-control" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hutang</label>
                        <input type="number" v-model="adjustForm.hutang" class="form-control" required />
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="modal-footer">
                    <button class="btn-modern btn-secondary" @click="closeAdjustModal">Batal</button>
                    <button class="btn-modern btn-primary" @click="submitAdjustKuota">Simpan</button>
                </div>
            </template>
        </Modal>

        <!-- Modal Cuti Bersama -->
        <Modal
            v-if="showCutiBersamaModal"
            :show="showCutiBersamaModal"
            :title="isEditingCutiBersama ? 'Edit Cuti Bersama' : 'Tambah Cuti Bersama'"
            size="large"
            @close="closeCutiBersamaModal"
        >
            <template #body>
                <form @submit.prevent="submitCutiBersama">
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Nama Cuti Bersama *</label>
                                <input
                                    type="text"
                                    v-model="cutiBersamaForm.Nama_Cuti_Bersama"
                                    class="form-control"
                                    required
                                />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Tanggal Mulai *</label>
                                <input
                                    type="date"
                                    v-model="cutiBersamaForm.Tanggal_Mulai"
                                    class="form-control"
                                    required
                                />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Tanggal Selesai *</label>
                                <input
                                    type="date"
                                    v-model="cutiBersamaForm.Tanggal_Selesai"
                                    class="form-control"
                                    required
                                />
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Jumlah Hari</label>
                                <input
                                    type="number"
                                    v-model="cutiBersamaForm.Jumlah_Hari"
                                    class="form-control"
                                    step="0.5"
                                    readonly
                                />
                                <small class="form-text text-muted">Jumlah hari akan dihitung otomatis</small>
                            </div>
                        </div>
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="modal-footer">
                    <button class="btn-modern btn-secondary" @click="closeCutiBersamaModal">Batal</button>
                    <button class="btn-modern btn-primary" @click="submitCutiBersama">
                        {{ isEditingCutiBersama ? 'Update' : 'Simpan' }}
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Modal Konfirmasi Hapus Jenis Cuti -->
        <Modal
            :show="showDeleteJenisCutiModal"
            title="Hapus Jenis Cuti"
            size="small"
            @close="closeDeleteJenisCutiModal"
        >
            <template #body>
                <p>
                    Anda yakin ingin menghapus jenis cuti
                    <span class="fw-bold">{{ selectedJenisCuti?.Nama_Jenis_Cuti }}</span
                    >? Tindakan ini tidak dapat dibatalkan.
                </p>
            </template>
            <template #footer>
                <button class="btn-modern btn-secondary" @click="closeDeleteJenisCutiModal">Batal</button>
                <button class="btn-modern btn-danger" @click="confirmDeleteJenisCuti">
                    <span v-if="!loading.submit">
                        <i class="bi d-flex justify-content-center align-items-center bi-trash"></i>
                    </span>
                    <lord-icon
                        v-else
                        src="https://cdn.lordicon.com/ibckyoan.json"
                        trigger="loop"
                        delay="0"
                        colors="primary:#ffffff"
                        style="width: 25px; height: 25px"
                    />
                    <span v-if="!loading.submit" class="ms-2"> Ya, Hapus </span>
                    <span v-else class="ms-2"> Memuat... </span>
                </button>
            </template>
        </Modal>

        <!-- Modal Konfirmasi Setuju transaksi -->

        <Modal
            :show="showApproveModal"
            title="Setujui Pengajuan Cuti"
            size="small"
            :zIndex="1060"
            @close="closeApproveModal"
        >
            <template #body>
                <p>
                    Anda yakin ingin menyetujui pengajuan cuti
                    <span class="fw-bold">{{ selectedPengajuan?.nama_karyawan }}</span
                    >?
                </p>
                <br />
                <span class="fw-bold">Tindakan ini tidak dapat dibatalkan.</span>
            </template>
            <template #footer>
                <button class="btn-modern btn-secondary" @click="closeApproveModal">Batal</button>
                <button
                    class="btn-modern btn-success"
                    @click="approvePengajuan(selectedPengajuan)"
                    :disabled="loading.submit"
                >
                    <span v-if="!loading.submit">
                        <i class="bi d-flex justify-content-center align-items-center bi-check"></i>
                    </span>
                    <span v-else>
                        <i class="bi bi-arrow-repeat spin d-inline-flex justify-content-center align-items-center"></i>
                    </span>
                    <span v-if="!loading.submit" class="ms-2"> Ya, Setujui </span>
                    <span v-else class="ms-2"> Memuat... </span>
                </button>
            </template>
        </Modal>

        <Modal
            v-if="showApprovalFlowModal"
            :show="showApprovalFlowModal"
            title="Atur Tahapan Approval Cuti"
            size="large"
            @close="closeApprovalFlowModal"
        >
            <template #body>
                <div class="approval-flow-container">
                    <!-- Daftar Tahapan Approval -->
                    <div class="approval-steps-section">
                        <div class="section-header">
                            <h6 class="section-title">Tahapan Approval</h6>
                            <small class="section-subtitle">Urutkan dengan drag and drop</small>
                        </div>

                        <div v-if="approvalSteps.length === 0" class="empty-approval-state">
                            <div class="empty-icon">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <h5>Belum ada tahapan approval</h5>
                            <p>Tambahkan tahapan approval pertama Anda dengan memilih jenis approval di bawah</p>
                        </div>

                        <draggable
                            v-else
                            :list="approvalSteps"
                            handle=".drag-handle"
                            item-key="id"
                            class="approval-steps-list"
                            @change="onDragChange"
                            :key="dragKey"
                        >
                            <template #item="{ element, index }">
                                <div class="approval-step-item">
                                    <div class="step-header">
                                        <div class="step-drag">
                                            <i class="fas fa-grip-vertical drag-handle"></i>
                                        </div>
                                        <div class="step-number">[{{ index + 1 }}]</div>
                                        <div class="step-content">
                                            <div class="step-title">
                                                {{ element.nama_approval }}
                                                <span
                                                    v-if="element.source_type === 'TABLE' && element.order_approver_limit"
                                                    class="badge badge-info ms-1"
                                                    title="Batas level approver"
                                                >
                                                    &times;{{ element.order_approver_limit }}
                                                </span>
                                            </div>
                                            <!-- <div class="step-source">
                                                <span
                                                    class="source-badge"
                                                    :class="
                                                        element.source_type.toLowerCase()
                                                    "
                                                >
                                                    {{ element.source_type }}
                                                </span>
                                                <span
                                                    v-if="
                                                        element.source_reference
                                                    "
                                                    class="source-reference"
                                                >
                                                    {{
                                                        element.source_type ===
                                                        "DIRECT_EMPLOYEE"
                                                            ? getEmployeeName(
                                                                  element.source_reference
                                                              )
                                                            : element.source_reference
                                                    }}
                                                </span>
                                            </div> -->
                                            <div v-if="element.keterangan" class="step-keterangan">
                                                <small>{{ element.keterangan }}</small>
                                            </div>
                                        </div>
                                        <div class="step-actions">
                                            <div class="auto-approve-toggle">
                                                <label class="toggle-label">
                                                    <input
                                                        type="checkbox"
                                                        v-model="element.auto_approve"
                                                        true-value="Y"
                                                        false-value="T"
                                                        class="toggle-input"
                                                        @change="updateStepAutoApprove(index, $event)"
                                                    />
                                                    <span class="toggle-slider"></span>
                                                    <span class="toggle-text">Auto Approve</span>
                                                </label>
                                            </div>
                                            <button
                                                class="btn-action btn-edit btn-sm"
                                                @click="editStep(index)"
                                                title="Edit"
                                            >
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button
                                                class="btn-action btn-danger btn-sm"
                                                @click="removeStep(index)"
                                                title="Hapus"
                                            >
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </draggable>
                    </div>

                    <!-- Pilihan Jenis Approval -->
                    <div class="approval-type-section">
                        <h6 class="section-title">Pilih Jenis Approval</h6>
                        <div class="approval-type-grid">
                            <div
                                class="approval-type-card"
                                :class="{
                                    active: selectedApprovalType === 'atasan_langsung',
                                }"
                                @click="selectApprovalType('atasan_langsung')"
                            >
                                <div class="type-icon">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <div class="type-content">
                                    <h6>Atasan Langsung</h6>
                                    <p>Approval oleh atasan langsung karyawan</p>
                                </div>
                                <div class="type-check">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>

                            <div
                                class="approval-type-card"
                                :class="{
                                    active: selectedApprovalType === 'hr',
                                }"
                                @click="selectApprovalType('hr')"
                            >
                                <div class="type-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="type-content">
                                    <h6>HR</h6>
                                    <p>Approval oleh role HC Ops Cuti</p>
                                </div>
                                <div class="type-check">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                            <!--
                            <div
                                class="approval-type-card"
                                :class="{
                                    active: selectedApprovalType === 'manager',
                                }"
                                @click="selectApprovalType('manager')"
                            >
                                <div class="type-icon">
                                    <i class="fas fa-user-cog"></i>
                                </div>
                                <div class="type-content">
                                    <h6>Manager</h6>
                                    <p>Approval oleh manager tertentu</p>
                                </div>
                                <div class="type-check">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div> -->

                            <div
                                class="approval-type-card"
                                :class="{
                                    active: selectedApprovalType === 'direct_employee',
                                }"
                                @click="selectApprovalType('direct_employee')"
                            >
                                <div class="type-icon">
                                    <i class="fas fa-user-check"></i>
                                </div>
                                <div class="type-content">
                                    <h6>Karyawan Tertentu</h6>
                                    <p>Approval oleh karyawan spesifik</p>
                                </div>
                                <div class="type-check">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>

                            <div
                                class="approval-type-card"
                                :class="{
                                    active: selectedApprovalType === 'role_pool',
                                }"
                                @click="selectApprovalType('role_pool')"
                            >
                                <div class="type-icon">
                                    <i class="fas fa-user-shield"></i>
                                </div>
                                <div class="type-content">
                                    <h6>Role Approval Pool</h6>
                                    <p>Approval oleh salah satu anggota role functional</p>
                                </div>
                                <div class="type-check">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Detail Approval (Muncul setelah memilih jenis) -->
                    <div v-if="selectedApprovalType" class="approval-detail-section">
                        <h6 class="section-title">Detail Approval</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Nama Approval *</label>
                                    <input
                                        type="text"
                                        v-model="stepForm.nama_approval"
                                        class="form-control"
                                        :placeholder="getPlaceholderName()"
                                        required
                                    />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Keterangan</label>
                                    <input
                                        type="text"
                                        v-model="stepForm.keterangan"
                                        class="form-control"
                                        :placeholder="getPlaceholderKeterangan()"
                                    />
                                </div>
                            </div>

                            <!-- Field khusus untuk DIRECT_EMPLOYEE -->
                            <div v-if="selectedApprovalType === 'direct_employee'" class="col-12">
                                <div class="form-group">
                                    <label class="form-label">Pilih Karyawan *</label>
                                    <el-autocomplete
                                        v-model="stepForm.selected_employee"
                                        :fetch-suggestions="loadKaryawan"
                                        value-key="Kode_Karyawan"
                                        popper-class="my-autocomplete"
                                        clearable
                                        placeholder="Pilih Karyawan"
                                        @select="handleSelectKaryawan"
                                        class="w-100"
                                    >
                                        <template #default="{ item }">
                                            <div class="value text-gray-800 font-medium">
                                                {{ item.value }}
                                            </div>
                                            <div style="border-bottom: 1px solid #afcffa">
                                                <span class="link text-gray-500 text-md">{{ item.Nama }}</span>
                                                <br />
                                                <span class="fw-bold link text-gray-500 text-sm">{{
                                                    item.nama_divisi
                                                }}</span>
                                            </div>
                                        </template>
                                    </el-autocomplete>

                                    <small class="form-text">Pilih karyawan yang akan menjadi approver</small>
                                </div>
                            </div>

                            <div v-if="selectedApprovalType === 'role_pool'" class="col-12">
                                <div class="form-group">
                                    <label class="form-label">Pilih Role *</label>
                                    <el-select
                                        v-model="stepForm.source_reference"
                                        filterable
                                        clearable
                                        placeholder="Pilih role approval"
                                        class="w-100"
                                    >
                                        <el-option
                                            v-for="role in roleOptions"
                                            :key="role.Id_Role"
                                            :value="role.Role_Code"
                                            :label="`${role.Role_Name} (${role.Role_Code})`"
                                        >
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>{{ role.Role_Name }}</span>
                                                <small class="text-muted">{{ role.user_count || 0 }} user</small>
                                            </div>
                                            <small class="text-muted">{{ role.Role_Code }}</small>
                                        </el-option>
                                    </el-select>
                                    <small class="form-text"
                                        >Salah satu user aktif dalam role ini bisa memproses approval.</small
                                    >
                                </div>
                            </div>

                            <!-- Field khusus untuk TABLE types -->
                            <!-- <div
                                v-if="
                                    [
                                        'atasan_langsung',
                                        'hr',
                                        'manager',
                                    ].includes(selectedApprovalType)
                                "
                                class="col-12"
                            >
                                <div class="form-group">
                                    <label class="form-label"
                                        >Source Reference *</label
                                    >
                                    <input
                                        type="text"
                                        v-model="stepForm.source_reference"
                                        class="form-control"
                                        :placeholder="
                                            getSourceReferencePlaceholder()
                                        "
                                        required
                                    />
                                    <small class="form-text"
                                        >Referensi tabel untuk approval
                                        flow</small
                                    >
                                </div>
                            </div> -->

                            <!-- Batas jumlah approver (hanya untuk source rantai atasan / TABLE) -->
                            <div v-if="stepForm.source_type === 'TABLE'" class="col-12">
                                <div class="form-group">
                                    <label class="form-label">Batas Jumlah Approver (Level Atasan)</label>
                                    <input
                                        type="number"
                                        min="1"
                                        step="1"
                                        v-model.number="stepForm.order_approver_limit"
                                        class="form-control"
                                        placeholder="Kosongkan untuk tanpa batas"
                                        @input="onOrderApproverLimitInput"
                                    />
                                    <small class="form-text">
                                        Berapa level atasan yang wajib menyetujui secara berjenjang. Kosongkan untuk
                                        mengambil seluruh rantai atasan. Nilai harus ≤ kedalaman rantai atasan pemohon,
                                        jika tidak pengajuan akan gagal.
                                    </small>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input
                                        type="checkbox"
                                        v-model="stepForm.auto_approve"
                                        true-value="Y"
                                        false-value="T"
                                        class="form-check-input"
                                    />
                                    <label class="form-check-label"> Auto Approve </label>
                                    <small class="form-text"
                                        >Jika diaktifkan, tahapan ini akan otomatis disetujui tanpa menunggu persetujuan
                                        manual</small
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="detail-form-actions">
                            <button v-if="isEditingStep" class="btn-modern btn-secondary" @click="cancelEdit">
                                Batal Edit
                            </button>
                            <button
                                class="btn-modern btn-primary"
                                @click="addOrUpdateStep"
                                :disabled="!isJenisApprovalValid"
                            >
                                <i class="fas fa-plus"></i>
                                {{ isEditingStep ? 'Update Tahap' : 'Tambah Tahap' }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="modal-footer">
                    <button class="btn-modern btn-secondary" @click="closeApprovalFlowModal">Batal</button>
                    <button
                        class="btn-modern btn-primary"
                        @click="saveApprovalFlow"
                        :disabled="approvalSteps.length === 0"
                    >
                        Simpan Approval Flow
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Modal Konfirmasi Hapus Jenis Cuti -->
        <Modal
            v-if="showDeleteJenisCutiModal"
            :show="showDeleteJenisCutiModal"
            title="Konfirmasi Hapus Jenis Cuti"
            @close="closeDeleteJenisCutiModal"
        >
            <template #body>
                <p>
                    Anda yakin ingin menghapus jenis cuti
                    <strong>{{ selectedJenisCuti?.Nama_Jenis_Cuti }}</strong
                    >? Tindakan ini tidak dapat dibatalkan.
                </p>
            </template>
            <template #footer>
                <div class="modal-footer">
                    <button class="btn-modern btn-secondary" @click="closeDeleteJenisCutiModal">Batal</button>
                    <button class="btn-modern btn-danger" @click="confirmDeleteJenisCuti">
                        <span v-if="!loading.submit">
                            <i class="bi d-flex justify-content-center align-items-center bi-trash"></i>
                        </span>
                        <lord-icon
                            v-else
                            src="https://cdn.lordicon.com/ibckyoan.json"
                            trigger="loop"
                            delay="0"
                            colors="primary:#ffffff"
                            style="width: 25px; height: 25px"
                        />
                        <span v-if="!loading.submit" class="ms-2"> Ya, Hapus </span>
                        <span v-else class="ms-2"> Memuat... </span>
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Modal Detail 2 -->
        <Modal
            v-if="showDetailModal"
            :show="showDetailModal"
            :title="`Detail Pengajuan - ${selectedPengajuan?.No_Transaksi || ''}`"
            size="large"
            @close="closeDetailModal"
        >
            <template #body>
                <div v-if="loading.pengajuanDetail" class="loading">
                    <div class="loading-state">
                        <div class="loading-spinner">
                            <i
                                class="bi d-flex justify-content-center align-items-center bi-arrow-repeat d-flex justify-content-center align-items-center"
                            ></i>
                        </div>
                        <h4 class="loading-title">Memuat Data...</h4>
                        <p class="loading-description">Sedang mengambil data detail</p>
                    </div>
                </div>
                <div v-else class="detail-content-v2">
                    <div class="row">
                        <!-- Kolom Kiri: Info Karyawan & Status -->
                        <div class="col-md-5 detail-left-col mb-3">
                            <div class="detail-employee-info">
                                <div class="avatar-lg">
                                    {{ getInitials(selectedPengajuan?.requester_Nama) }}
                                </div>
                                <h5 class="employee-name">
                                    {{ selectedPengajuan?.requester_Nama }}
                                </h5>
                                <p class="employee-meta">
                                    {{ selectedPengajuan?.Tipe_Izin2 || selectedPengajuan?.Tipe_Izin }}
                                </p>
                                <p class="employee-meta text-muted">
                                    No. Transaksi:
                                    {{ selectedPengajuan?.No_Transaksi }}
                                </p>
                            </div>
                            <div class="detail-status-section">
                                <h6 class="section-title">Status Pengajuan</h6>
                                <Badge
                                    :type="
                                        selectedPengajuan?.status === 'pending'
                                            ? 'warning'
                                            : selectedPengajuan?.status === 'approved'
                                              ? 'success'
                                              : 'danger'
                                    "
                                    class="w-100 justify-content-center"
                                >
                                    {{ getStatusText(selectedPengajuan?.status) }}
                                </Badge>
                                <small class="text-muted mt-2 d-block">
                                    Diajukan pada:
                                    {{ formatDateTime(selectedPengajuan?.tanggal_create) }}
                                </small>
                            </div>
                            <div
                                class="detail-sidebar"
                                v-if="selectedPengajuan?.approvers && selectedPengajuan?.approvers?.length > 0"
                            >
                                <div class="approval-flow">
                                    <h4 class="flow-title">
                                        <i
                                            class="bi d-flex justify-content-center align-items-center bi-diagram-3 me-2"
                                        ></i>
                                        Alur Persetujuan
                                    </h4>
                                    <el-steps
                                        direction="vertical"
                                        :active="getActiveStep(selectedPengajuan.approvers)"
                                        finish-status="success"
                                    >
                                        <el-step
                                            v-for="approver in selectedPengajuan.approvers"
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

                        <!-- Kolom Kanan: Detail Izin & Jejak Persetujuan -->
                        <div class="col-md-7">
                            <div class="detail-izin-info">
                                <h6 class="section-title">Detail Izin</h6>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <span class="label">Jenis Izin</span>
                                        <span class="value">{{
                                            selectedPengajuan?.Tipe_Izin2 || selectedPengajuan?.Tipe_Izin
                                        }}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Tanggal Pengajuan</span>
                                        <span class="value">
                                            {{ selectedPengajuan?.tanggalReal }}
                                        </span>
                                    </div>
                                    <!-- <div class="info-item">
                                        <span class="label"
                                            >Tanggal Selesai</span
                                        >
                                        <span class="value">
                                            {{
                                                formatDate(
                                                    selectedPengajuan?.Tanggal_Selesai
                                                )
                                            }}
                                        </span>
                                    </div> -->
                                    <div class="info-item">
                                        <span class="label">Jumlah Hari</span>
                                        <span class="value">{{ selectedPengajuan?.jumlah_hari || '1' }} hari</span>
                                    </div>
                                    <div class="info-item full-width">
                                        <span class="label">Alasan</span>
                                        <p class="value-reason">
                                            {{ selectedPengajuan?.Alasan }}
                                        </p>
                                    </div>
                                    <div v-if="selectedPengajuan?.Lampiran" class="info-item">
                                        <span class="label">Lampiran</span>
                                        <a :href="selectedPengajuan?.Lampiran_Url" target="_blank" class="file-link">
                                            <i class="bi bi-paperclip me-2"></i>Lihat File
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Status Approval Flow -->
                            <div class="approval-trace-section-v2 mt-4">
                                <h6 class="section-title">Status Persetujuan</h6>
                                <div class="approval-status-info">
                                    <div class="status-item">
                                        <span class="status-label">Approver Saat Ini:</span>
                                        <span class="status-value">{{
                                            selectedPengajuan?.Approver || 'Tidak tersedia'
                                        }}</span>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-label">Diapprove oleh:</span>
                                        <span class="status-value">{{
                                            selectedPengajuan?.approvedBy
                                                ? `admin - ${selectedPengajuan.approvedBy}`
                                                : 'Belum direview admin'
                                        }}</span>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-label">Status Saat Ini:</span>
                                        <span class="status-value">{{
                                            selectedPengajuan?.Status_Approval_Saat_Ini || 'Pending'
                                        }}</span>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-label">Approver Sebelumnya:</span>
                                        <span class="status-value">{{
                                            selectedPengajuan?.Status_Approval_Langkah_Sebelumnya || 'Tidak ada'
                                        }}</span>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-label">Urutan Flow:</span>
                                        <span class="status-value"
                                            >{{ selectedPengajuan?.order_flow }}
                                            /
                                            {{ selectedPengajuan?.MaxFlow }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <button class="btn-modern btn-secondary" @click="closeDetailModal">Tutup</button>
                <button v-if="!isRiwayat" class="btn-modern btn-danger" @click="openRejectModal(selectedPengajuan)">
                    Tolak
                </button>
                <button v-if="!isRiwayat" class="btn-modern btn-success" @click="showApproveModalFC(selectedPengajuan)">
                    Setujui
                </button>
            </template>
        </Modal>
    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import ApprovalTabs from '@/components/legacy/toggleSectionV2.vue';
import HeaderKpi from '@/components/legacy/header.vue';
import Modal from '@/components/legacy/modalV2-noir.vue';
import Badge from '../../../../components/ui/badge.vue';
import { DotLottieVue } from '@lottiefiles/dotlottie-vue';
import vSelect from 'vue-select';
import { ElMessage, ElNotification, ElAutocomplete, ElSteps, ElStep } from 'element-plus';
import 'element-plus/dist/index.css';
import axios from 'axios';
import draggable from 'vuedraggable';
import { debounce } from 'lodash';
import quickStat from '@/components/legacy/quickStat.vue';
import ModernDataTable from '@/components/legacy/table-noir.vue';

export default {
    name: 'AdminCutiOptimized',
    components: {
        ApprovalTabs,
        HeaderKpi,
        Modal,
        DotLottieVue,
        Badge,
        vSelect,
        ElMessage,
        ElAutocomplete,
        ElSteps,
        ElStep,
        ElNotification,
        ModernDataTable,
        draggable,
        quickStat,
    },
    data() {
        return {
            showConfirmJenisCutiModal: false,
            isRiwayat: false,
            riwayatColumns: [
                // {
                //     key: "No_Transaksi",
                //     title: "No. Transaksi",
                //     icon: "bi bi-hash",
                //     showCount: true,
                //     sticky: true,

                //     cellClass: "employee-cell",
                // },
                {
                    key: 'requester_Nama',
                    title: 'Karyawan',
                    icon: 'bi bi-person',
                    sticky: true,
                    formatter: (value, item) => item.requester_Nama,
                    cellClass: 'employee-cell',
                },
                {
                    key: 'No_Transaksi',
                    title: 'No Transaksi',
                    icon: 'bi bi-hash',
                    formatter: (value, item) => item.No_Transaksi,
                },
                {
                    key: 'Tipe_Izin',
                    title: 'Jenis Izin',
                    icon: 'bi bi-tag',
                    formatter: (value, item) => this.getJenisIzinText(item.Tipe_Izin2 || item.Tipe_Izin),
                },

                {
                    key: 'TanggalIzin',
                    title: 'Tanggal',
                    icon: 'bi bi-calendar',
                    type: 'date',
                    formatter: (value, item) => item.tanggalReal,
                },
                {
                    key: 'tanggalReal',
                    title: 'Durasi',
                    icon: 'bi bi-clock',
                    formatter: (value, item) => `${item.tanggalReal?.jumlah_hari || '1'} hari`,
                },
                {
                    key: 'status',
                    title: 'Status',
                    icon: 'bi bi-check-circle',
                    formatter: (value, item) => this.getStatusText(item.status),
                    cellClass: 'status-cell',
                },
                {
                    key: 'Admin_Reviewer',
                    title: 'Direview Oleh',
                    icon: 'bi bi-person-badge',
                    formatter: (value, item) => item.Admin_Reviewer || '-',
                },
                {
                    key: 'actions',
                    title: 'Aksi',
                    icon: 'bi bi-three-dots',
                    showInMobile: false,
                },
            ],
            riwayatMobileColumns: [
                {
                    key: 'No_Transaksi',
                    title: 'No. Transaksi',
                    icon: 'bi bi-hash',
                },
                {
                    key: 'TanggalIzin',
                    title: 'Tanggal',
                    icon: 'bi bi-calendar',
                },
                {
                    key: 'tanggalReal',
                    title: 'Durasi',
                    icon: 'bi bi-clock',
                },
                {
                    key: 'Alasan',
                    title: 'Alasan',
                    icon: 'bi bi-chat-left-text',
                },
                {
                    key: 'Admin_Reviewer',
                    title: 'Direview Oleh',
                    icon: 'bi bi-person-badge',
                },
            ],
            pengajuanColumns: [
                // {
                //     key: "No_Transaksi",
                //     title: "No. Transaksi",
                //     icon: "bi bi-hash",
                //     showCount: true,
                //     sticky: true,

                //     cellClass: "employee-cell",
                // },
                {
                    key: 'requester_Nama',
                    title: 'Karyawan',
                    icon: 'bi bi-person',
                    sticky: true,
                    formatter: (value, item) => item.requester_Nama,
                    cellClass: 'employee-cell',
                },
                {
                    key: 'No_Transaksi',
                    title: 'No Transaksi',
                    icon: 'bi bi-hash',
                    formatter: (value, item) => item.No_Transaksi,
                },
                {
                    key: 'Tipe_Izin',
                    title: 'Jenis Izin',
                    icon: 'bi bi-tag',
                    formatter: (value, item) => this.getJenisIzinText(item.Tipe_Izin2 || item.Tipe_Izin),
                },

                {
                    key: 'TanggalIzin',
                    title: 'Tanggal',
                    icon: 'bi bi-calendar',
                    type: 'date',
                    formatter: (value, item) => item.tanggalReal,
                },
                {
                    key: 'tanggalReal',
                    title: 'Durasi',
                    icon: 'bi bi-clock',
                    formatter: (value, item) => `${item.tanggalReal?.jumlah_hari || '1'} hari`,
                },
                {
                    key: 'status',
                    title: 'Status',
                    icon: 'bi bi-check-circle',
                    formatter: (value, item) => this.getStatusText(item.status),
                    cellClass: 'status-cell',
                },
                {
                    key: 'actions',
                    title: 'Aksi',
                    icon: 'bi bi-three-dots',
                    showInMobile: false,
                },
            ],
            pengajuanMobileColumns: [
                {
                    key: 'No_Transaksi',
                    title: 'No. Transaksi',
                    icon: 'bi bi-hash',
                },
                {
                    key: 'TanggalIzin',
                    title: 'Tanggal',
                    icon: 'bi bi-calendar',
                },
                {
                    key: 'tanggalReal',
                    title: 'Durasi',
                    icon: 'bi bi-clock',
                },
                {
                    key: 'Alasan',
                    title: 'Alasan',
                    icon: 'bi bi-chat-left-text',
                },
            ],
            showApproveModal: false,
            showRejectModal: false,
            rejectionReason: '',
            showDeleteJenisCutiModal: false,
            riwayatList: [],
            paginationRiwayat: {
                currentPage: 1,
                limit: 10,
                total: 0,
                totalPages: 1,
                links: [],
                next_page_url: null,
                prev_page_url: null,
            },
            paginationRiwayat: {
                currentPage: 1,
                limit: 10,
                total: 0,
                totalPages: 1,
                links: [],
            },
            filtersRiwayat: {
                search: '',
                status: '',
            },
            showApprovalFlowModal: false,
            isEditApproval: false,
            selectedApprovalType: null,
            isEditingStep: false,
            editingIndex: -1,
            employeeOptions: [],
            roleOptions: [],
            dragKey: 0,
            stepForm: {
                nama_approval: '',
                source_type: 'TABLE',
                source_reference: '',
                selected_employee: null,
                keterangan: '',
                auto_approve: 'T',
            },

            tahapApprovalOptions: [
                { value: 1, label: 'Atasan Langsung' },
                { value: 2, label: 'HC' },
                { value: 3, label: 'Manajemen' },
                { value: 4, label: 'Direktur' },
            ],
            currentDate: new Date(),
            activeTab: 'pengajuan',
            searchKuota: '',
            searchJenisCuti: '',
            filtersJenis: {
                search: '',
            },
            // paginationJenisCuti: {
            //     currentPage: 1,
            //     perPage: 6,
            //     total: 0,
            // },
            filtersPengajuan: {
                status: '',
                jenisCuti: '',
                divisi: '',
                level: '',
                search: '',
                tanggalMulai: null,
                tanggalSelesai: null,
            },
            paginationJenisCuti: {
                currentPage: 1,
                limit: 10,
                total: 0,
                totalPages: 1,
            },
            // Data
            pengajuanList: [],
            jenisCutiList: [],
            cutiBersamaList: [],
            kuotaList: [],
            laporanData: [],

            // Modals
            showDetailModal: false,
            showJenisCutiModal: false,
            showAdjustModal: false,
            showCutiBersamaModal: false,

            // Selected items
            selectedPengajuan: null,
            selectedKuota: null,
            selectedCutiBersama: null,

            // Forms
            jenisCutiForm: {
                cariNama: null,
                Nama_Jenis_Cuti: '',
                Deskripsi: '',
                Kuota_Default: 0,
                // Satuan: "hari",
                mode_tanggal: 'MULTIPLE',
                Flag_Bisa_Hutang_Cuti: 'T',
                Masa_Berlaku_Bulan: 12,
                Dapat_Diakumulasi: 'T',
                Gender_Spesifik: '',
                Minimal_Masa_Kerja_Bulan: 0,
                Maksimum_Pengajuan: 1,
                Perlu_Persetujuan_Khusus: 'T',
                Tahap_Approval: 1,
                Flag_Bisa_Hutang_Cuti: 'T',
                Dokumen_Pendukung: 'T',
                Flag_Aktif: 'T',
                Created_By: 'Admin',
                Maks_Hutang_Cuti: null,
                Hutang_Cuti_Mode: 'OUTSTANDING',
                Hutang_Cuti_Basis_Periode: 'KALENDER',
                Hutang_Periodik_Perhitungan: 'GROSS',
                Memotong_Saldo_Cuti: 'T',
            },
            adjustForm: {
                kode_karyawan: '',
                tipe_cuti: 'tahunan',
                jumlah: 0,
                hutang: 0,
            },
            cutiBersamaForm: {
                Nama_Cuti_Bersama: '',
                Tanggal_Mulai: null,
                Tanggal_Selesai: null,
                Jumlah_Hari: 0,
                Flag_Aktif: 'T',
                Created_By: 'Admin',
            },
            availableKaryawan: [],
            isEditingJenisCuti: false,
            editingJenisCutiId: null,
            isEditingCutiBersama: false,
            editingCutiBersamaId: null,

            paginationPengajuan: {
                currentPage: 1,
                limit: 10,
                total: 0,
                totalPages: 1,
            },
            // loading
            loading: {
                jenisCuti: false,
                cutiBersama: false,
                pengajuan: false,
                cariKaryawan: false,
                pengajuanDetail: false,
                kuota: false,
                submit: false,
                riwayat: false,
            },
            stats: {
                pending: 0,
                approved: 0,
                rejected: 0,
            },
            searchTimeout: null,
        };
    },
    computed: {
        filteredRiwayatPengajuanData() {
            return this.riwayatList || [];
        },
        filteredPengajuanData() {
            return this.pengajuanList || [];
        },
        quickStatField() {
            return [
                {
                    title: 'Menunggu',
                    value: this.pengajuanList.length,
                    icon: 'bi-hourglass-split',
                    bgIcon: 'bg-warning',
                    size: 'col-4',
                },
                {
                    title: 'Disetujui',
                    value: this.riwayatList.filter((item) => item.status === 'approved').length,
                    icon: 'bi-check-circle',
                    bgIcon: 'bg-success',
                    size: 'col-4',
                },
                {
                    title: 'Ditolak',
                    value: this.riwayatList.filter((item) => item.status === 'rejected').length,
                    icon: 'bi-x-circle',
                    bgIcon: 'bg-danger',
                    size: 'col-4',
                },
            ];
        },
        isFormJenisCutiValid() {
            const form = this.jenisCutiForm;
            console.log('ini Jenis', this.jenisCutiForm);

            if (
                // Field Bertanda Bintang
                !form.Nama_Jenis_Cuti ||
                // form.Kuota_Default === null ||
                // form.Kuota_Default === undefined ||
                form.Flag_Bisa_Hutang_Cuti === null ||
                form.Flag_Bisa_Hutang_Cuti === undefined ||
                form.Batas_Hari_Pengajuan === null ||
                form.Batas_Hari_Pengajuan === undefined ||
                // !form.Satuan ||
                form.Maksimum_Pengajuan === null ||
                form.Maksimum_Pengajuan === undefined ||
                !form.Deskripsi ||
                form.Masa_Berlaku_Bulan === null ||
                form.Masa_Berlaku_Bulan === undefined ||
                form.Minimal_Masa_Kerja_Bulan === null ||
                form.Minimal_Masa_Kerja_Bulan === undefined
            ) {
                return false;
            }

            if (
                // typeof form.Kuota_Default !== "number" ||
                // form.Kuota_Default <= 0 ||
                // typeof form.Maksimum_Pengajuan !== "number" ||
                form.Maksimum_Pengajuan <= 0 ||
                // typeof form.Masa_Berlaku_Bulan !== "number" ||
                form.Masa_Berlaku_Bulan <= 0 ||
                // typeof form.Minimal_Masa_Kerja_Bulan !== "number" ||
                form.Minimal_Masa_Kerja_Bulan < 0
            ) {
                return false;
            }

            if (form.Perlu_Persetujuan_Khusus !== 'T') {
                if (!form.Approver || form.Approver.length === 0) {
                    return false;
                }
            }

            return true;
        },
        isJenisApprovalValid() {
            if (!this.stepForm.nama_approval.trim()) return false;
            if (!this.stepForm.source_type) return false;

            if (this.stepForm.source_type === 'TABLE' || this.stepForm.source_type === 'GROUP_TABLE') {
                return !!this.stepForm.source_reference.trim();
            } else if (this.stepForm.source_type === 'DIRECT_EMPLOYEE') {
                return !!this.stepForm.selected_employee;
            } else if (this.stepForm.source_type === 'ROLE') {
                return !!this.stepForm.source_reference.trim();
            }

            return false;
        },
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? 'Siang' : 'Malam';
        },

        adminTabs() {
            return [
                {
                    id: 'pengajuan',
                    label: 'Pengajuan',
                    icon: 'bi bi-list-ul d-flex justify-content-center align-items-center',
                    count: this.paginationPengajuan.total,
                },
                {
                    id: 'riwayat',
                    label: 'Riwayat',
                    icon: 'bibi-clock-history d-flex justify-content-center align-items-center',
                    count: this.paginationRiwayat.total,
                },

                {
                    id: 'jenis_cuti',
                    label: 'Jenis Cuti',
                    icon: 'bi bi-tags d-flex justify-content-center align-items-center',
                    count: this.jenisCutiList?.length,
                },
                // {
                //     id: 'cuti_bersama',
                //     label: 'Cuti Bersama',
                //     icon: 'bi bi-calendar-event d-flex justify-content-center align-items-center',
                //     count: this.cutiBersamaList.length,
                // },
                // {
                //     id: "kuota",
                //     label: "Kuota Karyawan",
                //     icon: "bi bi-people d-flex justify-content-center align-items-center",
                //     count: this.kuotaList.length,
                // },
            ];
        },

        filteredJenisCuti() {
            if (!this.searchJenisCuti) return this.jenisCutiList;

            const search = this.searchJenisCuti.toLowerCase();
            return this.jenisCutiList.filter(
                (item) =>
                    item.Nama_Jenis_Cuti.toLowerCase().includes(search) ||
                    item.Deskripsi.toLowerCase().includes(search),
            );
        },

        paginatedJenisCuti() {
            const start = (this.paginationJenisCuti.currentPage - 1) * this.paginationJenisCuti.perPage;
            const end = start + this.paginationJenisCuti.perPage;
            return this.filteredJenisCuti.slice(start, end);
        },

        totalJenisCutiPages() {
            return Math.ceil(this.filteredJenisCuti.length / this.paginationJenisCuti.perPage);
        },

        filteredKuota() {
            let filtered = this.kuotaList;

            if (this.searchKuota) {
                const search = this.searchKuota.toLowerCase();
                filtered = filtered.filter(
                    (item) =>
                        item.nama_karyawan.toLowerCase().includes(search) ||
                        item.kode_karyawan.toLowerCase().includes(search),
                );
            }

            return filtered;
        },

        filteredLaporan() {
            return this.laporanData;
        },
    },
    methods: {
        showConfirmJenisCutiFC(jenisCuti) {
            this.selectedJenisCuti = jenisCuti;
            this.showConfirmJenisCutiModal = true;
        },
        closeConfirmJenisCutiFC() {
            this.showConfirmJenisCutiModal = false;
            this.selectedJenisCuti = null;
            // this.closeJenisCutiModal();
        },

        getPendingCount() {
            return 0;
        },

        // Approval flow helpers
        getStepStatus(approver) {
            if (approver.Flag_Approval === 'Y') return 'success';
            if (approver.Flag_Approval === 'T') return 'error';
            return 'process';
        },

        getStepIcon(approver) {
            const status = this.getStepStatus(approver);
            const icons = {
                success: 'bi bi-check-circle-fill',
                error: 'bi bi-x-circle-fill',
                process: 'bi bi-hourglass-split',
            };
            return icons[status] || icons.process;
        },

        getStepDescription(approver) {
            if (approver.Flag_Approval === 'P') {
                return 'Ditolak sistem/Expired';
            }

            if (approver.Flag_Approval === 'T') {
                const approvalDate = approver.Tanggal_Approval || approver.Tanggal_Approved;
                const reviewer =
                    approver.Nama_Reviewer || approver.Approved_By_Kode || approver.approver_name || 'Sistem';
                const approvedInfo = approvalDate
                    ? `${this.formatDateString(approvalDate, true)} oleh ${reviewer}`
                    : 'Ditolak';
                const rejectReason = this.getApprovalRejectReason(approver);

                return rejectReason
                    ? `${approvedInfo}. Alasan: ${rejectReason}`
                    : `${approvedInfo}. Alasan belum diisi.`;
            }

            const approvalDate = approver.Tanggal_Approval || approver.Tanggal_Approved;
            const reviewer = approver.Nama_Reviewer || approver.Approved_By_Kode || approver.approver_name || 'Sistem';

            if (approvalDate) {
                return `${this.formatDateString(approvalDate, true)} oleh ${reviewer}`;
            }
            return 'Menunggu persetujuan';
        },

        getApprovalRejectReason(approver) {
            return String(
                approver?.Catatan_Approval ||
                    approver?.Keterangan_Approval ||
                    approver?.Catatan ||
                    approver?.Keterangan ||
                    approver?.alasan_tolak ||
                    approver?.alasan ||
                    '',
            ).trim();
        },

        getOverallStatus(approvers) {
            if (!approvers || approvers.length === 0) return 'pending';

            // Check if any rejected
            if (approvers.some((a) => a.Flag_Approval === 'T')) return 'rejected';

            // Check if all approved
            if (approvers.every((a) => a.Flag_Approval === 'Y')) return 'approved';

            return 'pending';
        },

        getActiveStep(approvers) {
            if (!approvers) return 0;

            // Find the last approved step
            for (let i = approvers.length - 1; i >= 0; i--) {
                if (approvers[i].Flag_Approval === 'Y') {
                    return i + 1;
                }
            }
            return 0;
        },
        truncateText(text, max) {
            if (!text) return '';
            return text.length > max ? text.substr(0, max) + '...' : text;
        },
        getJenisIzinBadgeType(item) {
            const jenis = item?.Tipe_Izin2 || item?.Tipe_Izin || item?.jenis_cuti;
            const jenisText = String(jenis || '');

            if (jenisText.includes('Cuti')) return 'info';
            if (jenisText.includes('Sakit')) return 'warning';
            if (jenisText.includes('Izin')) return 'primary';
            return 'secondary';
        },

        getJenisIzinText(jenis) {
            const jenisMap = {
                cuti: 'Cuti',
                sakit: 'Izin Sakit',
                izinFull: 'Izin Pribadi',
                pulangCepat: 'Pulang Cepat',
                terlambat: 'Izin Terlambat',
            };
            return jenisMap[jenis] || jenis;
        },

        getStatusBadgeType(status) {
            const statusMap = {
                approved: 'success',
                rejected: 'danger',
                pending: 'warning',
            };
            return statusMap[status] || 'secondary';
        },
        formatRiwayatDate(item) {
            if (!item?.TanggalIzin) return '-';

            const startDate = this.formatDate(item?.TanggalIzin);
            if (item?.Tanggal_Selesai && item?.Tanggal_Selesai !== item?.TanggalIzin) {
                const endDate = this.formatDate(item?.Tanggal_Selesai);
                return `${startDate} - ${endDate}`;
            }
            return startDate;
        },
        getStatusIcon(status) {
            const iconMap = {
                approved: 'bi bi-check-circle',
                rejected: 'bi bi-x-circle',
                pending: 'bi bi-clock',
            };
            return iconMap[status] || 'bi bi-question-circle';
        },

        getStatusText(status) {
            const statusMap = {
                pending: 'Menunggu',
                approved: 'Disetujui',
                rejected: 'Ditolak',
            };
            return statusMap[status] || this.capitalize(status);
        },
        handleJenisCutiPageChange(page) {
            this.loadJenisCuti(page);
        },
        handlePengajuanPageChange(page) {
            this.loadPengajuan(page);
        },
        handleRiwayatPageChange(page) {
            this.loadRiwayat(page);
        },
        showApproveModalFC(item) {
            this.selectedPengajuan = item;
            this.showApproveModal = true;
        },
        closeApproveModal() {
            this.showApproveModal = false;
            this.selectedPengajuan = null;
        },

        openRejectModal(item) {
            this.showRejectModal = true;
            this.selectedPengajuan = item;
        },
        async confirmRejectPengajuan() {
            try {
                this.loading.submit = true;
                const pengajuan = this.selectedPengajuan;
                if (!pengajuan) return;

                const response = await axios.post(`/api/v1/cuti/admin/pengajuan/reject`, {
                    No_Transaksi: pengajuan.No_Transaksi,
                    catatan: this.rejectionReason,
                });
                if (response.status == 200) {
                    ElNotification({
                        title: 'Berhasil',
                        message: 'Pengajuan cuti berhasil ditolak.',
                        type: 'success',
                    });
                    this.closeDetailModal();
                    this.closeRejectModal();
                    this.loadPengajuan();
                }
            } catch (error) {
                console.log(error);
                let errorMessage = 'Terjadi Kesalahan';
                if (error.response && error.response.data && error.response.data.message) {
                    errorMessage = error.response.data.message;
                }

                this.jenisCutiList = [];

                ElMessage({
                    message: errorMessage,
                    type: 'error',
                });
            } finally {
                this.loading.submit = false;
            }
        },
        closeRejectModal() {
            this.showRejectModal = false;
            this.rejectionReason = '';
            this.selectedPengajuan = null;
        },
        closeDeleteJenisCutiModal() {
            this.showDeleteJenisCutiModal = false;
            this.selectedJenisCuti = null;
        },
        async confirmDeleteJenisCuti() {
            try {
                this.loading.submit = true;
                const response = await axios.delete(
                    `/api/v1/cuti/admin/jenis-cuti/destroy/${this.selectedJenisCuti.Id_Jenis_Cuti}`,
                );

                if (response.status == 200) {
                    ElNotification({
                        title: 'Berhasil',
                        message: 'Jenis cuti berhasil dihapus!',
                        type: 'success',
                    });
                    this.loadJenisCuti();
                }
            } catch (error) {
                ElNotification({
                    title: 'Error',
                    message: 'Gagal menghapus jenis cuti.',
                    type: 'error',
                });
                console.error('Error deleting jenis cuti:', error);
            } finally {
                this.loading.submit = false;
                this.closeDeleteJenisCutiModal();
            }
        },
        openDeleteJenisCutiModal(item) {
            this.selectedJenisCuti = item;
            this.showDeleteJenisCutiModal = true;
        },
        handleSearchRiwayat() {
            this.paginationRiwayat.currentPage = 1;
            this.loadRiwayat();
        },
        applyFiltersRiwayat() {
            this.paginationRiwayat.currentPage = 1;
            this.loadRiwayat();
        },
        async getDetailPengajuan(id) {
            try {
                const response = await axios.get(`/api/cuti/pengajuan/${id}`);
                this.selectedPengajuan = response.data.data;
                this.showDetailModal = true;
            } catch (error) {
                console.error('Error fetching detail pengajuan:', error);
                ElNotification({
                    title: 'Error',
                    message: 'Gagal mengambil detail pengajuan. Silakan coba lagi.',
                    type: 'error',
                });
            }
        },
        capitalize(text) {
            if (!text) return '';
            return text.charAt(0).toUpperCase() + text.slice(1);
        },
        handleSearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.loadPengajuan();
            }, 500);
        },
        handleSearchJenis() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.loadJenisCuti();
            }, 500);
        },
        handleSelectKaryawan(item) {
            console.log(item);
            this.stepForm.source_reference = item.Kode_Karyawan;
            this.stepForm.selected_employee = item.Nama;
        },
        filteredKaryawan(queryString, cb) {
            let results = [];

            if (queryString) {
                results = this.allDivisi.filter((item) =>
                    item.nama_divisi.toLowerCase().includes(queryString.toLowerCase()),
                );
            } else {
                results = this.allDivisi;
            }

            cb(results);
        },
        cariNama(search) {
            this.debouncedSearch(search);
        },
        openApprovalFlowModal() {
            if (this.jenisCutiForm.Perlu_Persetujuan_Khusus == 'T') {
                ElMessage.warning('Uncentang box Tidak perlu persetujuan!');
                return;
            }
            this.approvalSteps = [];

            const existingApprovers = this.jenisCutiForm?.Approver;
            if (existingApprovers && Array.isArray(existingApprovers) && existingApprovers.length > 0) {
                this.approvalSteps = existingApprovers.map((approver) => ({
                    id: approver.Id_Cuti_Approval || Date.now() + Math.random(),
                    nama_approval: approver.Nama_Approval || '',
                    source_type: approver.Source_Type || 'TABLE',
                    source_reference: approver.Source_Reference || '',
                    keterangan: approver.Keterangan || '',
                    auto_approve: approver.Auto_Approve || 'T',
                    order_approver_limit: approver.Order_Approver_Limit ?? null,
                }));
            }

            this.selectedApprovalType = null;
            this.resetStepForm();
            this.showApprovalFlowModal = true;
        },

        closeApprovalFlowModal() {
            this.showApprovalFlowModal = false;
            this.resetStepForm();
        },

        resetStepForm() {
            this.stepForm = {
                nama_approval: '',
                source_type: 'TABLE',
                source_reference: '',
                selected_employee: null,
                keterangan: '',
                auto_approve: 'T',
                order_approver_limit: null,
            };
            this.selectedApprovalType = null;
            this.isEditingStep = false;
            this.editingIndex = -1;
        },

        onOrderApproverLimitInput(event) {
            const raw = event?.target?.value;
            if (raw === '' || raw === null || raw === undefined) {
                this.stepForm.order_approver_limit = null;
                return;
            }
            const parsed = parseInt(raw, 10);
            this.stepForm.order_approver_limit = Number.isFinite(parsed) && parsed >= 1 ? parsed : null;
        },

        sortApprover(list) {
            if (!Array.isArray(list)) return [];
            return [...list].sort((a, b) => (a.Order_Flow || 0) - (b.Order_Flow || 0));
        },

        selectApprovalType(type) {
            this.selectedApprovalType = type;
            this.isEditingStep = false;

            // Set default values berdasarkan jenis yang dipilih
            switch (type) {
                case 'atasan_langsung':
                    this.stepForm.source_type = 'TABLE';
                    this.stepForm.source_reference = 'HRIS_Approval_Flow';
                    this.stepForm.nama_approval = 'Atasan Langsung';
                    this.stepForm.keterangan = 'Atasan yang melakukan approval';
                    break;
                case 'hr':
                    this.stepForm.source_type = 'ROLE';
                    this.stepForm.source_reference = 'ADMIN_HC_CUTI_VERIFIKATOR';
                    this.stepForm.nama_approval = 'Admin HC';
                    this.stepForm.keterangan = 'Approval oleh role ADMIN_HC_CUTI_VERIFIKATOR';
                    break;
                case 'manager':
                    this.stepForm.source_type = 'TABLE';
                    this.stepForm.source_reference = 'HRIS_Manager_Approval';
                    this.stepForm.nama_approval = 'Manager';
                    this.stepForm.keterangan = 'Manager yang melakukan approval';
                    break;
                case 'direct_employee':
                    this.stepForm.source_type = 'DIRECT_EMPLOYEE';
                    this.stepForm.nama_approval = 'Karyawan Tertentu';
                    this.stepForm.keterangan = 'Approval oleh karyawan spesifik';
                    break;
                case 'role_pool':
                    this.stepForm.source_type = 'ROLE';
                    this.stepForm.source_reference = 'ADMIN_HC_CUTI_VERIFIKATOR';
                    this.stepForm.nama_approval = 'Admin HC';
                    this.stepForm.keterangan = 'Approval pool berdasarkan role functional';
                    break;
            }

            // Batas approver hanya berlaku untuk source rantai (TABLE). Selain itu selalu null.
            if (this.stepForm.source_type !== 'TABLE') {
                this.stepForm.order_approver_limit = null;
            }
        },

        getPlaceholderName() {
            switch (this.selectedApprovalType) {
                case 'atasan_langsung':
                    return 'Atasan Langsung';
                case 'hr':
                    return 'Admin HC';
                case 'manager':
                    return 'Manager';
                case 'direct_employee':
                    return 'Karyawan Tertentu';
                case 'role_pool':
                    return 'Admin HC';
                default:
                    return 'Nama Approval';
            }
        },

        getPlaceholderKeterangan() {
            switch (this.selectedApprovalType) {
                case 'atasan_langsung':
                    return 'Atasan yang melakukan approval';
                case 'hr':
                    return 'Approval oleh role ADMIN_HC_CUTI_VERIFIKATOR';
                case 'manager':
                    return 'Manager yang melakukan approval';
                case 'direct_employee':
                    return 'Approval oleh karyawan spesifik';
                case 'role_pool':
                    return 'Approval pool berdasarkan role functional';
                default:
                    return 'Deskripsi tahapan approval';
            }
        },

        getSourceReferencePlaceholder() {
            switch (this.selectedApprovalType) {
                case 'atasan_langsung':
                    return 'HRIS_Approval_Flow';
                case 'hr':
                    return 'ADMIN_HC_CUTI_VERIFIKATOR';
                case 'manager':
                    return 'HRIS_Manager_Approval';
                case 'role_pool':
                    return 'ADMIN_HC_CUTI_VERIFIKATOR';
                default:
                    return 'Source reference';
            }
        },

        getEmployeeName(employeeId) {
            if (this.employeeOptions && this.employeeOptions?.length == 0) {
                return employeeId;
            }
            const employee = this.employeeOptions.find((emp) => emp.id === employeeId);
            return employee ? employee.nama : employeeId;
        },

        addOrUpdateStep() {
            if (!this.isJenisApprovalValid) return;

            const stepData = {
                id: this.isEditingStep
                    ? this.approvalSteps[this.editingIndex].Id_Cuti_Approval
                    : Date.now() + Math.random(),
                nama_approval: this.stepForm.nama_approval,
                source_type: this.stepForm.source_type,
                source_reference: this.stepForm.source_reference,
                keterangan: this.stepForm.keterangan,
                auto_approve: this.stepForm.auto_approve,
                order_approver_limit:
                    this.stepForm.source_type === 'TABLE' ? this.stepForm.order_approver_limit || null : null,
            };

            if (this.isEditingStep) {
                this.approvalSteps[this.editingIndex] = stepData;
            } else {
                this.approvalSteps.push(stepData);
            }

            this.resetStepForm();
        },

        editStep(index) {
            const step = this.approvalSteps[index];
            const isLegacyHrApproval =
                step.source_type === 'GROUP_TABLE' && step.source_reference === 'N_HRIS_HC_Approval';
            const isHrRoleApproval =
                step.source_type === 'ROLE' && step.source_reference === 'ADMIN_HC_CUTI_VERIFIKATOR';

            // Tentukan jenis approval berdasarkan data yang ada
            let type = 'direct_employee';
            if (isLegacyHrApproval || isHrRoleApproval) {
                type = 'hr';
            } else if (step.source_type === 'ROLE') {
                type = 'role_pool';
            } else if (step.source_type === 'TABLE' || step.source_type === 'GROUP_TABLE') {
                if (step.nama_approval.toLowerCase().includes('atasan')) type = 'atasan_langsung';
                else if (step.nama_approval.toLowerCase().includes('hr')) type = 'hr';
                else type = 'manager';
            }

            this.selectedApprovalType = type;
            this.stepForm = {
                nama_approval: isLegacyHrApproval ? 'Admin HC' : step.nama_approval,
                source_type: isLegacyHrApproval ? 'ROLE' : step.source_type,
                source_reference: isLegacyHrApproval
                    ? 'ADMIN_HC_CUTI_VERIFIKATOR'
                    : ['TABLE', 'GROUP_TABLE', 'ROLE'].includes(step.source_type)
                      ? step.source_reference
                      : '',
                selected_employee: step.source_type === 'DIRECT_EMPLOYEE' ? step.source_reference : null,
                keterangan: isLegacyHrApproval ? 'Approval oleh role ADMIN_HC_CUTI_VERIFIKATOR' : step.keterangan,
                auto_approve: step.auto_approve,
                order_approver_limit: step.source_type === 'TABLE' ? (step.order_approver_limit ?? null) : null,
            };
            this.isEditingStep = true;
            this.editingIndex = index;
        },

        cancelEdit() {
            this.resetStepForm();
        },

        removeStep(index) {
            if (confirm('Apakah Anda yakin ingin menghapus tahapan ini?')) {
                this.approvalSteps.splice(index, 1);
                this.dragKey += 1;
            }
        },

        onDragChange(evt) {
            this.dragKey += 1;
        },

        // Fix untuk reactivity checkbox auto_approve
        updateStepAutoApprove(index, event) {
            const newValue = event.target.checked ? 'Y' : 'T';
            this.$set(this.approvalSteps[index], 'auto_approve', newValue);
        },

        saveApprovalFlow() {
            const approvalFlowData = this.approvalSteps.map((step, index) => ({
                ...step,
                order: index + 1,
            }));

            console.log('Approval Flow yang disimpan:', approvalFlowData);

            this.jenisCutiForm.Approver = approvalFlowData.map((approver) => ({
                Id_Cuti_Approval: approver.id || Date.now() + Math.random(),
                Nama_Approval: approver.nama_approval || '',
                Source_Type: approver.source_type || 'TABLE',
                Source_Reference: approver.source_reference || '',
                Keterangan: approver.keterangan || '',
                Auto_Approve: approver.auto_approve || 'T',
                Order_Approver_Limit:
                    approver.source_type === 'TABLE' ? approver.order_approver_limit || null : null,
            }));

            ElMessage.success('Approval Flow berhasil disimpan!');

            this.closeApprovalFlowModal();
        },
        async loadRoleOptions() {
            try {
                const response = await axios.get('/api/v1/cuti/admin/jenis-cuti/role-options');
                this.roleOptions = response.data.result || [];
            } catch (error) {
                console.error('Error loading role approval options:', error);
                this.roleOptions = [];
            }
        },
        async loadKaryawan(queryString, cb) {
            let results = [];
            try {
                this.loading.cariKaryawan = true;
                const params = new URLSearchParams();
                if (queryString) {
                    params.append('search', queryString);
                }
                const response = await axios.get(`/api/v1/cuti/admin/pengajuan/karyawan?${params.toString()}`);

                if (response.status == 200) {
                    results = response.data.result;
                    cb(results);
                } else {
                    cb(results);
                }
            } catch (error) {
                console.log(error);
                let errorMessage = 'Terjadi Kesalahan';
                if (error.response && error.response.data && error.response.data.message) {
                    errorMessage = error.response.data.message;
                }

                cb(results);

                ElMessage({
                    message: errorMessage,
                    type: 'error',
                });
            } finally {
                this.loading.cariKaryawan = false;
            }
        },
        initMobileSlider() {
            // Initialize mobile stats slider
            const statsTrack = document.querySelector('.stats-track');
            if (statsTrack) {
                let isDown = false;
                let startX;
                let scrollLeft;

                statsTrack.addEventListener('mousedown', (e) => {
                    isDown = true;
                    statsTrack.classList.add('active');
                    startX = e.pageX - statsTrack.offsetLeft;
                    scrollLeft = statsTrack.scrollLeft;
                });

                statsTrack.addEventListener('mouseleave', () => {
                    isDown = false;
                    statsTrack.classList.remove('active');
                });

                statsTrack.addEventListener('mouseup', () => {
                    isDown = false;
                    statsTrack.classList.remove('active');
                });

                statsTrack.addEventListener('mousemove', (e) => {
                    if (!isDown) return;
                    e.preventDefault();
                    const x = e.pageX - statsTrack.offsetLeft;
                    const walk = (x - startX) * 2;
                    statsTrack.scrollLeft = scrollLeft - walk;
                });

                // Touch events for mobile
                statsTrack.addEventListener('touchstart', (e) => {
                    isDown = true;
                    startX = e.touches[0].pageX - statsTrack.offsetLeft;
                    scrollLeft = statsTrack.scrollLeft;
                });

                statsTrack.addEventListener('touchend', () => {
                    isDown = false;
                });

                statsTrack.addEventListener('touchmove', (e) => {
                    if (!isDown) return;
                    const x = e.touches[0].pageX - statsTrack.offsetLeft;
                    const walk = (x - startX) * 2;
                    statsTrack.scrollLeft = scrollLeft - walk;
                });
            }
        },

        formatDateString(date) {
            if (!date) return '';
            return new Date(date).toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },

        formatDate(date) {
            if (!date) return '';
            return new Date(date).toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
        },

        formatDateTime(date) {
            if (!date) return '';
            return new Date(date).toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },

        getInitials(name) {
            if (!name) return '??';
            return name
                .split(' ')
                .map((n) => n[0])
                .join('')
                .toUpperCase();
        },
        getBadgeType(status) {
            const types = {
                pending: 'warning',
                approved: 'success',
                rejected: 'danger',
                cancelled: 'secondary',
            };
            return types[status] || 'secondary';
        },

        getCutiTypeIcon(jenisCuti) {
            const icons = {
                'Cuti Tahunan': 'bi bi-calendar-check',
                'Cuti Melahirkan': 'bi bi-gender-female',
                'Cuti Penting': 'bi bi-star',
                'Cuti Besar': 'bi bi-briefcase',
                'Cuti Alasan Penting': 'bi bi-exclamation-triangle',
                'Cuti Menikah': 'bi bi-heart',
                'Cuti Keluarga': 'bi bi-people',
            };
            return icons[jenisCuti] || 'bi bi-calendar';
        },

        getCutiBersamaStatus(cutiBersama) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const tanggalMulai = new Date(cutiBersama.Tanggal_Mulai);
            const tanggalSelesai = new Date(cutiBersama.Tanggal_Selesai);

            tanggalMulai.setHours(0, 0, 0, 0);
            tanggalSelesai.setHours(0, 0, 0, 0);

            // 2. Jika sedang berlangsung
            if (today >= tanggalMulai && today <= tanggalSelesai) {
                return 'Berlangsung';
            }

            // 3. Jika sudah selesai
            if (today > tanggalSelesai) {
                return 'Selesai';
            }

            // 4. Jika belum mulai
            if (today < tanggalMulai) {
                return 'Belum Dimulai';
            }

            return 'Tidak Aktif';
        },
        handleTabChange(tabId) {
            this.activeTab = tabId;
            this.loadTabData();
        },

        applyFilters() {
            this.paginationPengajuan.currentPage = 1;
            this.loadPengajuan();
        },

        resetFilters() {
            this.filters = {
                status: '',
                jenisCuti: '',
                divisi: '',
                level: '',
                search: '',
                tanggalMulai: null,
                tanggalSelesai: null,
            };
        },

        // Modal Methods
        async viewDetail(pengajuan, isRiwayat = false) {
            this.loading.pengajuanDetail = true;
            if (isRiwayat) {
                this.isRiwayat = true;
            }
            this.showDetailModal = true;
            try {
                const response = await axios.get(`/api/v1/cuti/admin/pengajuan/detail/${pengajuan.No_Transaksi}`);
                this.selectedPengajuan = response.data.result;
            } catch (error) {
                console.error('Error fetching detail pengajuan:', error);
                ElNotification({
                    title: 'Error',
                    message: 'Gagal mengambil detail pengajuan. Silakan coba lagi.',
                    type: 'error',
                });
            } finally {
                this.loading.pengajuanDetail = false;
            }
        },

        closeDetailModal() {
            this.showDetailModal = false;
            this.selectedPengajuan = null;
        },

        showJenisCutiModalFC() {
            this.isEditingJenisCuti = false;
            this.jenisCutiForm = {
                Id_Jenic_Cuti: '',
                Nama_Jenis_Cuti: '',
                Deskripsi: '',
                Kuota_Default: 0,
                Batas_Hari_Pengajuan: 0,
                // Satuan: "hari",
                mode_tanggal: 'MULTIPLE',
                Masa_Berlaku_Bulan: 12,
                Dapat_Diakumulasi: 'T',
                Gender_Spesifik: '',
                Minimal_Masa_Kerja_Bulan: 0,
                Maksimum_Pengajuan: 1,
                Perlu_Persetujuan_Khusus: 'T',
                Approver: [],
                Dokumen_Pendukung: 'T',
                Flag_Aktif: 'T',
                Flag_Bisa_Hutang_Cuti: 'T',
                Created_By: 'Admin',
                Maks_Hutang_Cuti: null,
                Hutang_Cuti_Mode: 'OUTSTANDING',
                Hutang_Cuti_Basis_Periode: 'KALENDER',
                Hutang_Periodik_Perhitungan: 'GROSS',
                Memotong_Saldo_Cuti: 'T',
            };
            this.showJenisCutiModal = true;
        },

        closeJenisCutiModal() {
            this.showJenisCutiModal = false;
            this.isEditingJenisCuti = false;
            this.editingJenisCutiId = null;
            this.resetJenisCutiForm();
        },

        resetJenisCutiForm() {
            this.jenisCutiForm = {
                cariNama: null,
                Nama_Jenis_Cuti: '',
                Deskripsi: '',
                Kuota_Default: 0,
                Batas_Hari_Pengajuan: 0,
                // Satuan: "hari",
                mode_tanggal: 'MULTIPLE',
                Flag_Bisa_Hutang_Cuti: 'T',
                Masa_Berlaku_Bulan: 12,
                Dapat_Diakumulasi: 'T',
                Gender_Spesifik: '',
                Minimal_Masa_Kerja_Bulan: 0,
                Maksimum_Pengajuan: 1,
                Perlu_Persetujuan_Khusus: 'T',
                Tahap_Approval: 1,
                Dokumen_Pendukung: 'T',
                Flag_Aktif: 'T',
                Created_By: 'Admin',
                Maks_Hutang_Cuti: null,
                Hutang_Cuti_Mode: 'OUTSTANDING',
                Hutang_Cuti_Basis_Periode: 'KALENDER',
                Hutang_Periodik_Perhitungan: 'GROSS',
                Memotong_Saldo_Cuti: 'T',
                Approver: [],
            };
            this.approvalSteps = [];
        },

        showCutiBersamaModalFC() {
            this.isEditingCutiBersama = false;
            this.cutiBersamaForm = {
                Nama_Cuti_Bersama: '',
                Tanggal_Mulai: null,
                Tanggal_Selesai: null,
                Jumlah_Hari: 0,
                Flag_Aktif: 'T',
                Created_By: 'Admin',
            };
            this.showCutiBersamaModal = true;
        },

        closeCutiBersamaModal() {
            this.showCutiBersamaModal = false;
            this.isEditingCutiBersama = false;
            this.editingCutiBersamaId = null;
        },

        adjustKuota(kuota) {
            this.selectedKuota = kuota;
            this.adjustForm = {
                kode_karyawan: kuota.kode_karyawan,
                tipe_cuti: 'tahunan',
                jumlah: 0,
                hutang: 0,
            };
            this.showAdjustModal = true;
        },

        closeAdjustModal() {
            this.showAdjustModal = false;
            this.selectedKuota = null;
        },

        // API Methods dengan Data Dummy
        async loadTabData(data = null) {
            try {
                if (!data) {
                    switch (this.activeTab) {
                        case 'pengajuan':
                            await this.loadPengajuan();
                            break;
                        case 'jenis_cuti':
                            await this.loadJenisCuti();
                            break;
                        case 'cuti_bersama':
                            await this.loadCutiBersama();
                            break;
                        case 'kuota':
                            await this.loadKuota();
                            break;
                        case 'laporan':
                            await this.loadLaporan();
                            break;
                        case 'riwayat':
                            await this.loadRiwayat();
                            break;
                    }
                } else {
                    this.loadPengajuan();
                    this.loadJenisCuti();
                    this.loadCutiBersama();
                    this.loadKuota();
                    this.loadLaporan();
                    this.loadRiwayat();
                }
            } catch (error) {
                console.error('Error loading data:', error);
                this.showToast('Terjadi kesalahan saat memuat data', 'error');
            }
        },

        async loadPengajuan(pageOrUrl = null) {
            this.loading.pengajuan = true;
            try {
                const params = new URLSearchParams();
                if (this.filtersPengajuan.search) {
                    params.append('search', this.filtersPengajuan.search);
                }
                if (this.filtersPengajuan.status) {
                    params.append('status', this.filtersPengajuan.status);
                }
                if (this.filtersPengajuan.jenisCuti) {
                    params.append('jenis_cuti', this.filtersPengajuan.jenisCuti);
                }

                let endpoint = `/api/v1/cuti/admin/pengajuan?${params.toString()}`;
                const pageNumber = Number(pageOrUrl);
                if (typeof pageOrUrl === 'string' && pageOrUrl.length > 0) {
                    endpoint = pageOrUrl;
                } else if (Number.isInteger(pageNumber) && pageNumber > 0) {
                    params.append('page', String(pageNumber));
                    endpoint = `/api/v1/cuti/admin/pengajuan?${params.toString()}`;
                }

                const response = await axios.get(endpoint);

                if (response.data.success) {
                    this.pengajuanList = response.data.result;
                    this.paginationPengajuan = {
                        ...response.data.pagination,
                        limit: response.data.pagination.per_page,
                        currentPage: response.data.pagination.current_page,
                        totalPages: response.data.pagination.total_pages,
                    };

                    // Update stats
                    const allData = response.data.result;
                    this.stats.pending = allData.filter((p) => p.status === 'pending').length;
                    this.stats.approved = allData.filter((p) => p.status === 'approved').length;
                    this.stats.rejected = allData.filter((p) => p.status === 'rejected').length;
                }
            } catch (error) {
                console.error('Error loading pengajuan:', error);
                ElMessage.error('Gagal memuat data pengajuan.');
            } finally {
                this.loading.pengajuan = false;
            }
        },
        async loadRiwayat(pageOrUrl = null) {
            this.loading.riwayat = true;
            try {
                const params = new URLSearchParams();
                if (this.filtersRiwayat.search) {
                    params.append('search', this.filtersRiwayat.search);
                }
                if (this.filtersRiwayat.status) {
                    params.append('status', this.filtersRiwayat.status);
                }

                let endpoint = `/api/v1/cuti/admin/pengajuan/riwayat?${params.toString()}`;
                const pageNumber = Number(pageOrUrl);
                if (typeof pageOrUrl === 'string' && pageOrUrl.length > 0) {
                    endpoint = pageOrUrl;
                } else if (Number.isInteger(pageNumber) && pageNumber > 0) {
                    params.append('page', String(pageNumber));
                    endpoint = `/api/v1/cuti/admin/pengajuan/riwayat?${params.toString()}`;
                }

                const response = await axios.get(endpoint);

                if (response.data.success) {
                    this.riwayatList = response.data.result;
                    this.paginationRiwayat = {
                        ...response.data.pagination,
                        limit: response.data.pagination.per_page,
                        currentPage: response.data.pagination.current_page,
                        totalPages: response.data.pagination.total_pages,
                    };
                    console.log(this.paginationRiwayat);
                }
            } catch (error) {
                console.error('Error loading riwayat:', error);
                ElMessage.error('Gagal memuat data riwayat.');
            } finally {
                this.loading.riwayat = false;
            }
        },

        clearFilters() {
            this.filtersJenis = {
                search: '',
            };
            this.loadJenisCuti();
        },
        async loadJenisCuti(pageOrUrl = null) {
            try {
                this.loading.jenisCuti = true;

                const params = new URLSearchParams();

                if (this.filtersJenis.search) {
                    params.append('search', this.filtersJenis.search);
                }

                let endpoint = `/api/v1/cuti/admin/jenis-cuti?${params.toString()}`;
                const pageNumber = Number(pageOrUrl);
                if (typeof pageOrUrl === 'string' && pageOrUrl.length > 0) {
                    endpoint = pageOrUrl;
                } else if (Number.isInteger(pageNumber) && pageNumber > 0) {
                    params.append('page', String(pageNumber));
                    endpoint = `/api/v1/cuti/admin/jenis-cuti?${params.toString()}`;
                }

                const response = await axios.get(endpoint);

                if (response.status == 200) {
                    this.jenisCutiList = response.data.result;
                    this.paginationJenisCuti = {
                        ...response.data.pagination,
                        limit: response.data.pagination.per_page,
                        currentPage: response.data.pagination.current_page,
                        totalPages: response.data.pagination.total_pages,
                    };
                } else {
                    this.jenisCutiList = [];
                }
            } catch (error) {
                console.log(error);
                let errorMessage = 'Terjadi Kesalahan';
                if (error.response && error.response.data && error.response.data.message) {
                    errorMessage = error.response.data.message;
                }

                this.jenisCutiList = [];

                ElMessage({
                    message: errorMessage,
                    type: 'error',
                });
            } finally {
                this.loading.jenisCuti = false;
            }
        },

        async loadCutiBersama() {
            try {
                this.loading.cutiBersama = true;
                const response = await axios.get('/api/v1/cuti/admin/cuti-bersama');

                if (response.status == 200) {
                    this.cutiBersamaList = response.data.result;
                } else {
                    this.cutiBersamaList = [];
                }
            } catch (error) {
                console.log(error);
                let errorMessage = 'Terjadi Kesalahan';
                if (error.response && error.response.data && error.response.data.message) {
                    errorMessage = error.response.data.message;
                }

                this.cutiBersamaList = [];

                ElMessage({
                    message: errorMessage,
                    type: 'error',
                });
            } finally {
                this.loading.cutiBersama = false;
            }
        },

        async loadKuota() {
            // Data dummy kuota karyawan yang lebih detail
            const dummyKuota = [
                {
                    id: 1,
                    kode_karyawan: 'EMP001',
                    nama_karyawan: 'Ahmad Rizki',
                    department: 'Produksi',
                    level: 'Staff',
                    sisa_cuti_tahunan: 8,
                    hutang_cuti: 0,
                    total_cuti_diambil: 4,
                    last_cuti_tanggal: '2023-12-20',
                    status: 'active',
                },
                {
                    id: 2,
                    kode_karyawan: 'EMP002',
                    nama_karyawan: 'Siti Nurhaliza',
                    department: 'HCGA',
                    level: 'Manager',
                    sisa_cuti_tahunan: 10,
                    hutang_cuti: 2,
                    total_cuti_diambil: 2,
                    last_cuti_tanggal: '2024-01-12',
                    status: 'active',
                },
                {
                    id: 3,
                    kode_karyawan: 'EMP003',
                    nama_karyawan: 'Budi Santoso',
                    department: 'Produksi',
                    level: 'Staff',
                    sisa_cuti_tahunan: 12,
                    hutang_cuti: 0,
                    total_cuti_diambil: 0,
                    last_cuti_tanggal: null,
                    status: 'active',
                },
                {
                    id: 4,
                    kode_karyawan: 'EMP004',
                    nama_karyawan: 'Dewi Lestari',
                    department: 'HCGA',
                    level: 'Staff',
                    sisa_cuti_tahunan: 6,
                    hutang_cuti: 1,
                    total_cuti_diambil: 6,
                    last_cuti_tanggal: '2024-01-18',
                    status: 'active',
                },
            ];

            await new Promise((resolve) => setTimeout(resolve, 500));
            this.kuotaList = dummyKuota;
        },

        async loadLaporan() {
            // Data dummy laporan
            const dummyLaporan = [
                {
                    id: 1,
                    periode: 'Januari 2024',
                    total_pengajuan: 24,
                    pengajuan_disetujui: 18,
                    pengajuan_ditolak: 3,
                    pengajuan_pending: 3,
                    total_hari_cuti: 45,
                    rata_rata_lama_cuti: 2.5,
                    department_terbanyak: 'Produksi',
                    jenis_cuti_terbanyak: 'Cuti Tahunan',
                },
            ];

            await new Promise((resolve) => setTimeout(resolve, 500));
            this.laporanData = dummyLaporan;
        },

        // Action Methods dengan Data Dummy
        async approvePengajuan(pengajuan) {
            try {
                this.loading.submit = true;
                const response = await axios.post(`/api/v1/cuti/admin/pengajuan/approve`, {
                    No_Transaksi: pengajuan.No_Transaksi,
                });

                if (response.status == 200) {
                    ElNotification({
                        title: 'Berhasil',
                        message: 'Pengajuan cuti berhasil disetujui.',
                        type: 'success',
                    });
                    this.closeDetailModal();
                    this.closeApproveModal();
                    this.loadPengajuan();
                }
            } catch (error) {
                console.log(error);
                let errorMessage = 'Terjadi Kesalahan';
                if (error.response && error.response.data && error.response.data.message) {
                    errorMessage = error.response.data.message;
                }

                this.jenisCutiList = [];

                ElMessage({
                    message: errorMessage,
                    type: 'error',
                });
            } finally {
                this.loading.submit = false;
            }
        },

        async rejectPengajuan(pengajuan) {
            const reason = prompt('Alasan penolakan:');
            if (reason) {
                try {
                    const response = await axios.post(`/api/v1/cuti/admin/pengajuan/reject`, {
                        No_Transaksi: pengajuan.No_Transaksi,
                        catatan: reason,
                    });
                    if (response.status == 200) {
                        ElNotification({
                            title: 'Berhasil',
                            message: 'Pengajuan cuti berhasil ditolak.',
                            type: 'success',
                        });
                        this.closeDetailModal();
                        this.loadPengajuan();
                    }
                } catch (error) {
                    console.log(error);
                    let errorMessage = 'Terjadi Kesalahan';
                    if (error.response && error.response.data && error.response.data.message) {
                        errorMessage = error.response.data.message;
                    }

                    this.jenisCutiList = [];

                    ElMessage({
                        message: errorMessage,
                        type: 'error',
                    });
                } finally {
                    this.loading.submit = false;
                }
            }
        },

        updateKuotaKaryawan(kodeKaryawan, jenisCutiId, jumlahHari) {
            const kuotaIndex = this.kuotaList.findIndex((k) => k.kode_karyawan === kodeKaryawan);
            if (kuotaIndex !== -1) {
                this.kuotaList[kuotaIndex].sisa_cuti_tahunan -= jumlahHari;
                this.kuotaList[kuotaIndex].total_cuti_diambil += jumlahHari;
                this.kuotaList[kuotaIndex].last_cuti_tanggal = new Date().toISOString().split('T')[0];
            }
        },

        approveSelected() {
            if (this.selectedPengajuan) {
                this.approvePengajuan(this.selectedPengajuan);
                this.closeDetailModal();
            }
        },

        rejectSelected() {
            if (this.selectedPengajuan) {
                this.rejectPengajuan(this.selectedPengajuan);
                this.closeDetailModal();
            }
        },

        async submitJenisCuti() {
            if (!this.isFormJenisCutiValid) {
                ElMessage.warning('Form Belum terisi sepenuh nya!');
                return;
            }
            try {
                this.loading.submit = true;
                let param = this.jenisCutiForm;
                let endpoint = null;
                await new Promise((resolve) => setTimeout(resolve, 800));

                if (this.isEditingJenisCuti) {
                    endpoint = `/api/v1/cuti/admin/jenis-cuti/update/${param.Id_Jenis_Cuti}`;
                } else {
                    endpoint = '/api/v1/cuti/admin/jenis-cuti/store';
                }

                const response = await axios.post(endpoint, param);

                if (response.status == 200) {
                    this.closeJenisCutiModal();
                    this.closeConfirmJenisCutiFC();
                    ElNotification.success('Berhasil Menyimpan Jenis Cuti!');
                    this.loadJenisCuti();
                }
            } catch (error) {
                console.log(error);
                let errorMessage = 'Terjadi Kesalahan';
                if (error.response && error.response.data && error.response.data.message) {
                    errorMessage = error.response.data.message;
                }

                this.jenisCutiList = [];

                ElMessage({
                    message: errorMessage,
                    type: 'error',
                });
            } finally {
                this.loading.submit = false;
            }
        },

        editJenisCuti(jenisCuti) {
            this.isEditingJenisCuti = true;
            this.editingJenisCutiId = jenisCuti.Id_Jenis_Cuti;
            this.jenisCutiForm = {
                ...jenisCuti,
                Gender_Spesifik: !jenisCuti.Gender_Spesifik ? '' : jenisCuti.Gender_Spesifik,
                mode_tanggal: !jenisCuti.Format_Tanggal_Select ? 'MULTIPLE' : jenisCuti.Format_Tanggal_Select,
                Minimal_Masa_Kerja_Bulan: !jenisCuti.Minimal_Masa_Kerja_Bulan ? 0 : jenisCuti.Minimal_Masa_Kerja_Bulan,
                Hutang_Cuti_Mode: jenisCuti.Hutang_Cuti_Mode || 'OUTSTANDING',
                Hutang_Cuti_Basis_Periode: jenisCuti.Hutang_Cuti_Basis_Periode || 'KALENDER',
                Hutang_Periodik_Perhitungan: jenisCuti.Hutang_Periodik_Perhitungan || 'GROSS',
            };
            this.showJenisCutiModal = true;
            console.log(this.jenisCutiForm);
        },

        async deleteJenisCuti(jenisCuti) {
            if (confirm(`Hapus jenis cuti "${jenisCuti.Nama_Jenis_Cuti}"?`)) {
                try {
                    await new Promise((resolve) => setTimeout(resolve, 800));

                    this.jenisCutiList = this.jenisCutiList.filter((j) => j.Id_Jenis_Cuti !== jenisCuti.Id_Jenis_Cuti);

                    this.showToast('Jenis cuti berhasil dihapus!', 'success');
                } catch (error) {
                    console.error('Error deleting jenis cuti:', error);
                    this.showToast('Terjadi kesalahan saat menghapus jenis cuti', 'error');
                }
            }
        },

        async submitCutiBersama() {
            try {
                const response = await axios.post('/api/v1/cuti/admin/cuti-bersama/store', this.cutiBersamaForm);

                if (response.status == 200) {
                    this.closeCutiBersamaModal();
                    ElNotification.success('Berhasil Menyimpan Cuti Bersama!');
                    this.loadCutiBersama();
                }
            } catch (error) {
                console.log(error);
                let errorMessage = 'Terjadi Kesalahan';
                if (error.response && error.response.data && error.response.data.message) {
                    errorMessage = error.response.data.message;
                }

                ElMessage({
                    message: errorMessage,
                    type: 'error',
                });
            }
        },

        async deleteCutiBersama(cutiBersama) {
            if (confirm(`Hapus cuti bersama "${cutiBersama.Nama_Cuti_Bersama}"?`)) {
                try {
                    await new Promise((resolve) => setTimeout(resolve, 800));

                    this.cutiBersamaList = this.cutiBersamaList.filter(
                        (cb) => cb.Id_Cuti_Bersama !== cutiBersama.Id_Cuti_Bersama,
                    );

                    this.showToast('Cuti bersama berhasil dihapus!', 'success');
                } catch (error) {
                    console.error('Error deleting cuti bersama:', error);
                    this.showToast('Terjadi kesalahan saat menghapus cuti bersama', 'error');
                }
            }
        },

        /*
         * Sampai perubahan ini ada DUA showToast di blok methods yang sama.
         * Dalam objek JavaScript kunci terakhir menang, jadi yang berlaku
         * adalah yang membangun DOM dengan toast.innerHTML dan menyisipkan
         * ${message} mentah -- sementara pemanggilnya mengisi pesan dengan
         * nama karyawan dari basis data. Definisi ini yang aman justru mati.
         * Yang tidak aman dibuang; ElMessage meng-escape isinya sendiri.
         */
        showToast(message, type = 'info') {
            ElMessage({
                message: message,
                type: type,
            });
        },
        editCutiBersama(cutiBersama) {
            this.isEditingCutiBersama = true;
            this.editingCutiBersamaId = cutiBersama.Id_Cuti_Bersama;
            this.cutiBersamaForm = { ...cutiBersama };
            this.showCutiBersamaModal = true;
        },

        async batalkanCutiBersama(cutiBersama) {
            if (confirm(`Batalkan cuti bersama "${cutiBersama.Nama_Cuti_Bersama}"?`)) {
                try {
                    await new Promise((resolve) => setTimeout(resolve, 800));

                    const index = this.cutiBersamaList.findIndex(
                        (c) => c.Id_Cuti_Bersama === cutiBersama.Id_Cuti_Bersama,
                    );
                    if (index !== -1) {
                        this.cutiBersamaList[index].Flag_Aktif = 'F';
                    }

                    this.showToast('Cuti bersama berhasil dibatalkan!', 'success');
                } catch (error) {
                    console.error('Error canceling cuti bersama:', error);
                    this.showToast('Terjadi kesalahan saat membatalkan cuti bersama', 'error');
                }
            }
        },

        async submitAdjustKuota() {
            try {
                await new Promise((resolve) => setTimeout(resolve, 800));

                if (this.selectedKuota) {
                    const kuotaIndex = this.kuotaList.findIndex(
                        (k) => k.kode_karyawan === this.selectedKuota.kode_karyawan,
                    );

                    if (kuotaIndex !== -1) {
                        if (this.adjustForm.tipe_cuti === 'tahunan') {
                            this.kuotaList[kuotaIndex].sisa_cuti_tahunan += parseInt(this.adjustForm.jumlah);
                        } else if (this.adjustForm.tipe_cuti === 'hutang') {
                            this.kuotaList[kuotaIndex].hutang_cuti += parseInt(this.adjustForm.jumlah);
                        }

                        if (this.adjustForm.jumlah > 0) {
                            this.showToast(
                                `Kuota ${this.selectedKuota.nama_karyawan} berhasil ditambah sebanyak ${this.adjustForm.jumlah}`,
                                'success',
                            );
                        } else {
                            this.showToast(
                                `Kuota ${this.selectedKuota.nama_karyawan} berhasil dikurangi sebanyak ${Math.abs(
                                    this.adjustForm.jumlah,
                                )}`,
                                'success',
                            );
                        }
                    }
                }

                this.closeAdjustModal();
            } catch (error) {
                console.error('Error adjusting kuota:', error);
                this.showToast('Terjadi kesalahan saat mengadjust kuota', 'error');
            }
        },

        // Export Methods
        exportCSV() {
            const data = this.filteredPengajuan;
            const headers = [
                'No. Transaksi',
                'Nama Karyawan',
                'Kode',
                'Divisi',
                'Level',
                'Jenis Cuti',
                'Tanggal Mulai',
                'Tanggal Selesai',
                'Jumlah Hari',
                'Status',
                'Alasan',
            ];
            const csvContent = [
                headers.join(','),
                ...data.map((row) =>
                    [
                        `"${row.no_transaksi}"`,
                        `"${row.nama_karyawan}"`,
                        `"${row.kode_karyawan}"`,
                        `"${row.divisi}"`,
                        `"${row.level}"`,
                        `"${row.jenis_cuti}"`,
                        `"${this.formatDate(row.tanggal_mulai)}"`,
                        `"${this.formatDate(row.tanggal_selesai)}"`,
                        `"${row.jumlah_hari}"`,
                        `"${this.getStatusText(row.status)}"`,
                        `"${row.alasan}"`,
                    ].join(','),
                ),
            ].join('\n');

            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `laporan-pengajuan-cuti-${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);

            this.showToast('Data berhasil diexport dalam format CSV', 'success');
        },

        exportPDF() {
            this.showToast('Fitur export PDF akan segera tersedia', 'info');
        },

        exportData() {
            switch (this.activeTab) {
                case 'pengajuan':
                    this.exportCSV();
                    break;
                case 'laporan':
                    this.exportPDF();
                    break;
                default:
                    this.showToast(`Export data untuk tab ${this.activeTab} akan segera tersedia`, 'info');
            }
        },

        // Utility Methods
        calculateTotalHari(tanggalMulai, tanggalSelesai) {
            const start = new Date(tanggalMulai);
            const end = new Date(tanggalSelesai);
            const diffTime = Math.abs(end - start);
            return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        },

        debouncedSearch: debounce(function (serach) {
            this.loadKaryawan(serach);
        }, 500),

    },
    mounted() {
        this.loadTabData(1);
        this.loadRoleOptions();

        // Initialize mobile slider
        this.initMobileSlider();
    },

    watch: {
        // "jenisCutiForm.cariNama": function () {
        //     this.debouncedSearch();
        // },
        activeTab() {
            this.loadTabData();
        },
        searchKuota() {
            if (this.activeTab === 'kuota') {
                this.loadKuota();
            }
        },
        'cutiBersamaForm.Tanggal_Mulai': function (newVal) {
            if (newVal && this.cutiBersamaForm.Tanggal_Selesai) {
                const start = new Date(newVal);
                const end = new Date(this.cutiBersamaForm.Tanggal_Selesai);
                const diffTime = Math.abs(end - start);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                this.cutiBersamaForm.Jumlah_Hari = diffDays;
            }
        },
        'cutiBersamaForm.Tanggal_Selesai': function (newVal) {
            if (this.cutiBersamaForm.Tanggal_Mulai && newVal) {
                const start = new Date(this.cutiBersamaForm.Tanggal_Mulai);
                const end = new Date(newVal);
                const diffTime = Math.abs(end - start);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                this.cutiBersamaForm.Jumlah_Hari = diffDays;
            }
        },
    },
};
</script>
<style scoped>
/* detail style */
.detail-modal-content {
    padding: 20px;
}

.el-autocomplete-suggestion li {
    line-height: 25px;
}

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
</style>
<style scoped>
/* CSS Variables untuk Konsistensi */
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
    --radius: 12px;
    --transition: all 0.3s ease;
    --radius: 12px;
    --radius-lg: 16px;
    --transition: all 0.3s ease;
}

.unix-pagination-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin: 24px 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.unix-pagination-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 12px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background-color: var(--surface);
    color: var(--text);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    box-shadow: var(--shadow);
}

.unix-pagination-btn:hover:not(:disabled) {
    background-color: var(--surface-soft);
    border-color: var(--primary-light);
    transform: translateY(-1px);
    box-shadow: var(--shadow-lg);
}

.unix-pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    box-shadow: none;
}

.unix-pagination-btn.active {
    background-color: var(--primary);
    color: white;
    border-color: var(--primary);
}

.unix-pagination-btn.active:hover {
    background-color: var(--primary-dark);
    border-color: var(--primary-dark);
}

.unix-pagination-pages {
    display: flex;
    gap: 4px;
}

.unix-pagination-btn.page-number {
    min-width: 40px;
}

.unix-pagination-btn i {
    font-size: 16px;
}

/* Responsive adjustments */
@media (max-width: 640px) {
    .unix-pagination-container {
        flex-wrap: wrap;
    }

    .unix-pagination-pages {
        order: 1;
        width: 100%;
        justify-content: center;
        margin-top: 12px;
    }
}

/* style pagination jenis cuti */

.pagination-pages {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 20px 0 0 0;
    justify-content: center;
    align-items: center;
}

.pagination-pages li {
    margin: 0 5px;
}

.pagination-pages li button {
    background-color: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 8px 12px;
    cursor: pointer;
    transition: var(--transition);
    font-size: 0.9rem;
    color: var(--text);
}

.pagination-pages li button:hover:not(.active) {
    background-color: var(--surface-soft);
    border-color: var(--primary-light);
}

.pagination-pages li button.active {
    background-color: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination-pages li button:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

/* Base Styles */
.cuti-admin-container {
    font-family: 'Inter', sans-serif;
    background-color: var(--surface-soft);
    color: var(--text);
    min-height: 100vh;
}

/* Header */
.header {
    background-color: var(--surface);
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--dark);
}

/* Main Content */
.main-content {
    padding: 2rem;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.stats-card {
    background-color: var(--surface);
    padding: 1.5rem;
}
.pagination-jenis-cuti {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    margin-top: 1rem;
}

.pagination-jenis-cuti button {
    background-color: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.5rem 1rem;
    cursor: pointer;
    transition: var(--transition);
    font-size: 0.9rem;
    color: var(--text);
}

.pagination-jenis-cuti button:hover {
    background-color: var(--surface-soft);
    border-color: var(--primary-light);
}

.pagination-jenis-cuti button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.pagination-jenis-cuti span {
    margin: 0 1rem;
    font-size: 0.9rem;
    color: var(--text);
}

/* Base Styling */
.cuti-admin-container {
    font-family: 'Inter', sans-serif;
    background-color: var(--surface-soft);
    color: var(--text);
    line-height: 1.6;
}

.dashboard-header {
    background: var(--surface);
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.dashboard-header h1 {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--dark);
    margin: 0;
}

.dashboard-header-actions {
    display: flex;
    gap: 0.75rem;
}

.btn {
    padding: 0.75rem 1.25rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    border: 1px solid transparent;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
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

.approval-flow {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    border: 1px solid var(--border);
}

.flow-title {
    display: flex;
    align-items: center;
    margin-bottom: 1.5rem;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
}

/* Confirmation Modal */
.confirmation-content {
    text-align: center;
    padding: 1rem;
}

.warning-icon {
    font-size: 3rem;
    color: var(--warning);
    margin-bottom: 1rem;
}

.confirmation-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

/* Loading Skeleton */
.loading-skeleton {
    padding: 2rem;
}

.skeleton-line {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
    border-radius: 4px;
    margin-bottom: 1rem;
}

.skeleton-line.large {
    height: 2rem;
    width: 60%;
}

.skeleton-line.medium {
    height: 1.5rem;
    width: 80%;
}

.skeleton-line.small {
    height: 1rem;
    width: 40%;
}

.skeleton-line.full {
    height: 4rem;
    width: 100%;
}

@keyframes loading {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
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
    margin-bottom: 1rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.detail-status-section {
    margin-bottom: 1rem;
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
    margin-bottom: 1rem;
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

/* Cuti Duration Card */
.cuti-duration-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
}

.cuti-duration-card.cuti-highlight {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border: none;
}

.cuti-info-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.cuti-info-header h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.cuti-duration-card.cuti-highlight .cuti-info-header h4 {
    color: white;
}

.cuti-duration-card.cuti-highlight .cuti-info-header i {
    color: white;
}

/* Duration Display */
.cuti-duration-display {
    text-align: center;
    padding: 1rem 0;
}

.cuti-duration-value {
    display: block;
    font-size: 3rem;
    font-weight: 800;
    line-height: 1;
    color: inherit;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    margin-bottom: 0.25rem;
}

.cuti-duration-card.cuti-highlight .cuti-duration-value {
    color: white;
}

.cuti-duration-label {
    display: block;
    font-size: 1rem;
    color: inherit;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: 0.9;
}

.cuti-duration-card.cuti-highlight .cuti-duration-label {
    color: rgba(255, 255, 255, 0.9);
}

/* Hutang Warning Styles */
.cuti-hutang-warning {
    margin-top: 1.5rem;
    padding: 1.25rem;
    background: rgba(245, 158, 11, 0.08);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: var(--radius);
    animation: cuti-slideDown 0.3s ease;
}

@keyframes cuti-slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.cuti-warning-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    color: var(--warning);
    font-weight: 600;
    font-size: 0.95rem;
}

.cuti-warning-header i {
    font-size: 1.1rem;
}

.cuti-hutang-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.cuti-hutang-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(245, 158, 11, 0.1);
}

.cuti-hutang-item.cuti-highlight-item {
    background: rgba(245, 158, 11, 0.05);
    margin: 0 -0.5rem;
    padding: 0.75rem 0.5rem;
    border-radius: var(--radius);
    border-bottom: none;
}

.cuti-label {
    color: var(--text);
    font-weight: 500;
    font-size: 0.9rem;
}

.cuti-duration-card.cuti-highlight .cuti-label {
    color: rgba(255, 255, 255, 0.9);
}

.cuti-value {
    font-weight: 600;
    font-size: 0.9rem;
}

.cuti-duration-card.cuti-highlight .cuti-value {
    color: white;
}

.cuti-value.cuti-negative {
    color: var(--danger);
    background: rgba(239, 68, 68, 0.1);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.cuti-duration-card.cuti-highlight .cuti-value.cuti-negative {
    background: rgba(239, 68, 68, 0.3);
    color: var(--hcis-red-200);
    border-color: rgba(239, 68, 68, 0.5);
}

.cuti-warning-message {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    background: rgba(245, 158, 11, 0.1);
    border-radius: var(--radius);
    border-left: 3px solid var(--warning);
}

.cuti-warning-message i {
    color: var(--warning);
    font-size: 0.9rem;
}

.cuti-warning-message small {
    color: var(--warning);
    font-size: 0.8rem;
    font-weight: 500;
}

/* Cuti Cukup Info Styles */
.cuti-sufficient-info {
    margin-top: 1.5rem;
    padding: 1.25rem;
    background: rgba(16, 185, 129, 0.08);
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: var(--radius);
    animation: cuti-slideDown 0.3s ease;
}

.cuti-sufficient-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    color: var(--success);
    font-weight: 600;
    font-size: 0.95rem;
}

.cuti-sufficient-header i {
    font-size: 1.1rem;
}

.cuti-sufficient-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.cuti-sufficient-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(16, 185, 129, 0.1);
}

.cuti-value.cuti-positive {
    color: var(--success);
    background: rgba(16, 185, 129, 0.1);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.cuti-duration-card.cuti-highlight .cuti-value.cuti-positive {
    background: rgba(16, 185, 129, 0.3);
    color: var(--hcis-green-200);
    border-color: rgba(16, 185, 129, 0.5);
}

/* Empty State */
.cuti-empty-state {
    margin-top: 1.5rem;
    padding: 1.5rem;
    text-align: center;
    color: var(--text-muted);
    background: rgba(100, 116, 139, 0.05);
    border-radius: var(--radius);
    border: 1px dashed var(--border);
}

.cuti-duration-card.cuti-highlight .cuti-empty-state {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
    color: rgba(255, 255, 255, 0.8);
}

.cuti-empty-state i {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    display: block;
    opacity: 0.5;
}

.cuti-empty-state span {
    font-size: 0.9rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .cuti-duration-card {
        padding: 1.25rem;
    }
    .detail-left-col {
        /* border-right: 1px solid var(--hcis-legacy-bootstrap-gray); */
        padding: 0rem;
        border: none;
    }

    .cuti-duration-value {
        font-size: 2.5rem;
    }

    .cuti-hutang-details,
    .cuti-sufficient-details {
        gap: 0.5rem;
    }

    .cuti-hutang-item,
    .cuti-sufficient-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .cuti-hutang-warning,
    .cuti-sufficient-info {
        padding: 1rem;
    }

    .cuti-warning-header,
    .cuti-sufficient-header {
        font-size: 0.9rem;
    }
}

/* Additional Utility Classes untuk Cuti */
.cuti-text-center {
    text-align: center;
}

.cuti-mt-1 {
    margin-top: 0.5rem;
}

.cuti-mt-2 {
    margin-top: 1rem;
}

.cuti-mb-1 {
    margin-bottom: 0.5rem;
}

.cuti-p-1 {
    padding: 0.5rem;
}

.cuti-p-2 {
    padding: 1rem;
}

/* Loading State */
.loading-state {
    background: var(--surface);
    border-radius: 16px;
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.loading-spinner {
    width: 80px;
    height: 80px;
    background: var(--primary-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--primary);
    font-size: 2rem;
}

.loading-spinner .bi-arrow-repeat {
    animation: spin 1s linear infinite;
}

.loading-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.loading-description {
    color: var(--text-muted);
    margin: 0;
}

/* Spin Animation */
.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Modern Data Table Custom Styles */
.employee-info-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.employee-details {
    min-width: 0;
}

.employee-name {
    margin: 0;
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.875rem;
    line-height: 1.3;
}

.employee-dept {
    margin: 0.25rem 0 0 0;
    color: #6c757d;
    font-size: 0.75rem;
}

.badge-sm {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
}

.action-cell {
    display: flex;
    gap: 0.5rem;
}

.btn-action {
    padding: 0.5rem 0.75rem;
    border: none;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-action.table {
    padding: 0.35rem;
    width: 2rem;
    height: 2rem;
    justify-content: center;
}

.btn-view {
    background: #e3f2fd;
    color: #1976d2;
}

.btn-view:hover {
    background: #bbdefb;
    transform: translateY(-1px);
}

.btn-cancel {
    background: #ffebee;
    color: #d32f2f;
}

.action-text {
    font-size: 0.7rem;
}

.transaction-id-sm {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 0.7rem;
    background: #f8f9fa;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
    color: #495057;
}

/* Loading spin animation */
.loading-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Refresh button */
.btn-refresh {
    padding: 0.75rem 1rem;
    border: 1px solid #007bff;
    background: white;
    color: #007bff;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 0.875rem;
}

.btn-refresh:hover:not(:disabled) {
    background: #007bff;
    color: white;
}

.btn-refresh:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Mobile card improvements */
.mobile-btn {
    flex: 1;
    justify-content: center;
    padding: 0.75rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .employee-info-cell {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }

    .action-cell {
        justify-content: center;
    }

    .filter-section .row > div {
        margin-bottom: 0.5rem;
    }
}

/* Loading State */
.loading-state {
    background: var(--surface);
    border-radius: 16px;
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.loading-spinner {
    width: 80px;
    height: 80px;
    background: var(--primary-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--primary);
    font-size: 2rem;
}

.loading-spinner .bi-arrow-repeat {
    animation: spin 1s linear infinite;
}

.loading-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.loading-description {
    color: var(--text-muted);
    margin: 0;
}

/* Spin Animation */
.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.pagination-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    background-color: var(--surface);
    color: var(--text-muted);
    font-size: 1rem;
    transition: var(--transition);
    cursor: pointer;
}

.pagination-btn:hover:not(:disabled) {
    background-color: var(--surface-soft);
    color: var(--text);
    border-color: var(--primary-light);
}

.pagination-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.pagination-btn.active {
    background-color: var(--primary);
    color: var(--light);
    border-color: var(--primary);
}

.pagination-btn.active:hover {
    background-color: var(--primary-dark);
    border-color: var(--primary-dark);
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
.pagination-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    background-color: var(--surface);
    color: var(--text-muted);
    font-size: 1rem;
    transition: var(--transition);
    cursor: pointer;
}

.pagination-btn:hover:not(:disabled) {
    background-color: var(--surface-soft);
    color: var(--text);
    border-color: var(--primary-light);
}

.pagination-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.pagination-btn.active {
    background-color: var(--primary);
    color: var(--light);
    border-color: var(--primary);
}

.pagination-btn.active:hover {
    background-color: var(--primary-dark);
    border-color: var(--primary-dark);
}

/* Empty States */
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

.empty-state h4 {
    margin-bottom: 1rem;
    color: var(--text-muted);
}

.empty-state p {
    margin: 0 0 1.5rem 0;
}

.no-schedule-state {
    padding: 4rem 2rem;
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

/* Loading State */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: var(--text-muted);
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid var(--border);
    border-left: 4px solid var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 1rem;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

/* Division and Level Cells */
.division-cell,
.level-cell {
    font-size: 0.875rem;
    color: var(--text);
}

.employee-code {
    font-size: 0.75rem;
    color: var(--text-muted);
}

/* Responsive Design */
@media (max-width: 768px) {
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
    .filter-section {
        padding: 0.5rem;
    }

    .search-input,
    .filter-select {
        padding: 0.5rem 0.6rem 0.5rem 2rem;
        font-size: 0.85rem;
    }
}

/* Filter Tags */
.filter-tags {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 0.5rem;
}

.filter-tag {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.25rem 0.75rem;
    border-radius: 16px;
    font-size: 0.8rem;
    font-weight: 500;
}

.filter-tag i {
    cursor: pointer;
    font-size: 0.7rem;
}

.filter-tag i:hover {
    color: var(--danger);
}

.approvalFlow.disabled {
    background: #cbcbcb;
    cursor: not-allowed !important;
}

/* Loading State */
.loading-state {
    background: var(--surface);
    border-radius: 16px;
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.loading-spinner {
    width: 80px;
    height: 80px;
    background: var(--primary-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--primary);
    font-size: 2rem;
}

.loading-spinner .bi-arrow-repeat {
    animation: spin 1s linear infinite;
}

.loading-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.loading-description {
    color: var(--text-muted);
    margin: 0;
}

/* Spin Animation */
.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.approval-flow-container {
    padding: 1rem 0;
}

.section-header {
    margin-bottom: 1rem;
}

.section-title {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.25rem;
}

.section-subtitle {
    color: var(--text-muted);
    font-size: 0.85rem;
}

/* Empty State */
.empty-approval-state {
    text-align: center;
    padding: 3rem 2rem;
    background: var(--surface-soft);
    border-radius: var(--radius);
    border: 2px dashed var(--border);
}

.empty-approval-state .empty-icon {
    font-size: 3rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-approval-state h5 {
    color: var(--text-muted);
    margin-bottom: 0.5rem;
}

.empty-approval-state p {
    color: var(--text-muted);
    margin: 0;
}

/* Approval Steps List */
.approval-steps-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 2rem;
}

.approval-step-item {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1rem;
    transition: var(--transition);
}

.approval-step-item:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow);
}

.step-header {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.step-drag {
    color: var(--text-muted);
    cursor: grab;
}

.step-drag:active {
    cursor: grabbing;
}

.step-number {
    font-weight: 600;
    color: var(--primary);
    min-width: 40px;
}

.step-content {
    flex: 1;
}

.step-title {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.25rem;
}

.step-source {
    /* display: flex; */
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    margin-bottom: 0.25rem;
}

.source-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
}

.source-badge.table {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
}

.source-badge.direct_employee {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.source-reference {
    color: var(--text-muted);
    font-family: monospace;
}

.step-keterangan {
    color: var(--text-muted);
    font-size: 0.85rem;
}

.step-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

/* Auto Approve Toggle */
.auto-approve-toggle {
    display: flex;
    align-items: center;
}

.toggle-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    font-size: 0.85rem;
}

.toggle-input {
    display: none;
}

.toggle-slider {
    width: 40px;
    height: 20px;
    background: var(--border);
    border-radius: 20px;
    position: relative;
    transition: var(--transition);
}

.toggle-slider::before {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    background: white;
    border-radius: 50%;
    top: 2px;
    left: 2px;
    transition: var(--transition);
}

.toggle-input:checked + .toggle-slider {
    background: var(--success);
}

.toggle-input:checked + .toggle-slider::before {
    transform: translateX(20px);
}

.toggle-text {
    color: var(--text);
    font-weight: 500;
}

/* Approval Type Grid */
.approval-type-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.approval-type-card {
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: var(--radius);
    padding: 1.5rem;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
}

.approval-type-card:hover {
    border-color: var(--primary-light);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.approval-type-card.active {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.type-icon {
    width: 50px;
    height: 50px;
    background: var(--primary-light);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1.25rem;
}

.type-content {
    flex: 1;
}

.type-content h6 {
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
}

.type-content p {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.85rem;
}

.type-check {
    color: var(--primary);
    opacity: 0;
    transition: var(--transition);
}

.approval-type-card.active .type-check {
    opacity: 1;
}

/* Detail Section */
.approval-detail-section {
    background: var(--surface-soft);
    border-radius: var(--radius);
    padding: 1.5rem;
    border: 1px solid var(--border);
}

.detail-form-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}

/* Responsive Design */
@media (max-width: 768px) {
    .approval-type-grid {
        grid-template-columns: 1fr;
    }

    .step-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }

    .step-actions {
        width: 100%;
        justify-content: space-between;
    }

    .detail-form-actions {
        flex-direction: column;
    }

    .detail-form-actions .btn-modern {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .approval-step-item {
        padding: 0.75rem;
    }

    .approval-detail-section {
        padding: 1rem;
    }

    .step-actions {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .auto-approve-toggle {
        width: 100%;
        justify-content: space-between;
    }
}

/* Empty States */
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

.empty-state h4 {
    margin-bottom: 1rem;
    color: var(--text-muted);
}

.empty-state p {
    margin: 0 0 1.5rem 0;
}

.no-schedule-state {
    padding: 4rem 2rem;
}

/* Base Styles */
.container-fluid {
    /* background-color: var(--light); */
    min-height: 100vh;
}

/* Filter Styles */
.filter-group {
    margin-bottom: 1rem;
}

.filter-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text);
    font-size: 0.9rem;
}

.filter-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: var(--transition);
    cursor: pointer;
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Search Box */
.search-box {
    position: relative;
    width: 100%;
}

.search-box i {
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
    border-radius: var(--radius);
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: var(--transition);
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Stats Overview - Mobile Slider */
.stats-slider-mobile {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.stats-slider-mobile::-webkit-scrollbar {
    display: none;
}

.stats-track {
    display: flex;
    gap: 1rem;
    padding: 0.5rem 0;
    min-width: min-content;
}

.stats-track .stat-card-admin {
    min-width: 200px;
    flex-shrink: 0;
}

/* Stats Overview */
.stats-overview {
    margin-bottom: 2rem;
}

.stat-card-admin {
    background: var(--surface);
    border-radius: var(--radius);
    padding: 1.5rem;
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: var(--transition);
    cursor: pointer;
    height: 100%;
}

.stat-card-admin:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.stat-card-admin.total {
    border-left: 4px solid var(--primary);
}

.stat-card-admin.pending {
    border-left: 4px solid var(--warning);
}

.stat-card-admin.approved {
    border-left: 4px solid var(--success);
}

.stat-card-admin.rejected {
    border-left: 4px solid var(--danger);
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: var(--surface-soft);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1.5rem;
}

.stat-content h4 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    color: var(--dark);
}

.stat-content p {
    margin: 0.25rem 0 0 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

/* Quick Stats */
.quick-stats {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.stat-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.75rem;
    border-radius: 10px;
    cursor: pointer;
    transition: var(--transition);
    min-width: 80px;
}

.stat-badge.waiting {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.stat-badge.approved {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.stat-badge.rejected {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.stat-badge:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.stat-badge .count {
    font-weight: 700;
    font-size: 1.25rem;
}

.stat-badge .label {
    font-size: 0.75rem;
    font-weight: 600;
}

/* Pengajuan List */
.pengajuan-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pengajuan-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.5rem;
    transition: var(--transition);
}

.pengajuan-card:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow);
}

.pengajuan-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.employee-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.avatar-sm {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
}

.employee-details h6 {
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
}

.employee-details p {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.85rem;
}

.employee-meta {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.pengajuan-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

.pengajuan-date {
    font-size: 0.85rem;
    color: var(--text-muted);
}

.pengajuan-no {
    font-size: 0.8rem;
    color: var(--primary);
    font-weight: 600;
}

.pengajuan-content {
    margin-bottom: 1.5rem;
}

.pengajuan-details {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text);
    font-size: 0.9rem;
}

.detail-item i {
    color: var(--primary);
}

.pengajuan-reason p {
    margin: 0;
    color: var(--text-muted);
    line-height: 1.5;
    background: var(--surface-soft);
    padding: 1rem;
    border-radius: 8px;
}

.pengajuan-footer {
    border-top: 1px solid var(--border);
    padding-top: 1rem;
}

.pengajuan-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}

/* Button Styles */
.btn-modern {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.2rem;
    border: none;
    border-radius: var(--radius);
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
    position: relative;
    overflow: hidden;
}

.btn-modern.btn-primary {
    background-color: var(--primary);
    color: white;
}

.btn-modern.btn-primary:hover {
    background-color: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-modern.btn-secondary {
    background: var(--surface);
    color: var(--text);
    border: 1px solid var(--border);
}

.btn-modern.btn-secondary:hover {
    background: var(--surface-soft);
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-modern.btn-success {
    background: var(--success);
    color: white;
}

.btn-modern.btn-success:hover {
    background: var(--hcis-emerald-600);
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-modern.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-modern.btn-danger:hover {
    background: var(--hcis-red-600);
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-modern.btn-outline {
    background: transparent;
    color: var(--primary);
    border: 1px solid var(--primary);
}

.btn-modern.btn-outline:hover {
    background: var(--primary);
    color: white;
}

.btn-action {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
}

.btn-action.btn-view {
    background: var(--surface-soft);
    color: var(--text);
}

.btn-action.btn-view:hover {
    background: var(--border);
}

.btn-action.btn-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.btn-action.btn-success:hover {
    background: var(--success);
    color: white;
}

.btn-action.btn-danger {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.btn-action.btn-danger:hover {
    background: var(--danger);
    color: white;
}

.btn-action.btn-edit {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
}

.btn-action.btn-edit:hover {
    background: var(--primary);
    color: white;
}

/* Jenis Cuti Styles */
.jenis-cuti-grid {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.jenis-cuti-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.5rem;
    transition: var(--transition);
}

.jenis-cuti-card:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow);
}

.jenis-cuti-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.jenis-cuti-icon {
    width: 50px;
    height: 50px;
    background: var(--primary-light);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1.25rem;
}

.jenis-cuti-info {
    flex: 1;
}

.jenis-cuti-info h6 {
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
}

.jenis-cuti-info p {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.9rem;
}

.jenis-cuti-badge {
    background: var(--primary);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}

.jenis-cuti-details {
    margin-bottom: 1.5rem;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row .label {
    color: var(--text-muted);
    font-weight: 500;
}

.detail-row .value {
    color: var(--text);
    font-weight: 600;
}

.settings {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.setting-badge {
    background: var(--surface-soft);
    color: var(--text);
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}

.jenis-cuti-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}

/* Action Sidebar */
.action-sidebar {
    position: sticky;
    /* top: 1rem; */
}

.action-card {
    background: var(--surface);
    border-radius: var(--radius);
    padding: 1.5rem;
    border: 1px solid var(--border);
    margin-bottom: 1.5rem;
    text-align: center;
}

.action-title {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.action-description {
    color: var(--text-muted);
    margin-bottom: 1.5rem;
    line-height: 1.5;
}

.info-card-admin {
    background: linear-gradient(135deg, #dbeafe, #eff6ff);
    border-radius: var(--radius);
    padding: 1.5rem;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.info-title {
    font-weight: 600;
    color: #1e40af;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.tips-list {
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
    color: var(--hcis-blue-500);
    margin-top: 0.125rem;
}

.tip-item p {
    margin: 0;
    color: #374151;
    font-size: 0.9rem;
    line-height: 1.4;
}

/* Table Styles */
.table-responsive {
    overflow-x: auto;
    border-radius: var(--radius);
    border: 1px solid var(--border);
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.modern-table th,
.modern-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border);
}

.modern-table th {
    background-color: var(--surface-soft);
    font-weight: 600;
    color: var(--text);
    position: sticky;
    top: 0;
}

.modern-table tr:last-child td {
    border-bottom: none;
}

.modern-table tr:hover {
    background-color: var(--surface-soft);
}

.action-cell {
    display: flex;
    gap: 0.5rem;
}

/* Report Cards */
.report-card {
    background: var(--surface);
    border-radius: var(--radius);
    padding: 1.5rem;
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: var(--transition);
    height: 100%;
}

.report-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.report-icon {
    width: 60px;
    height: 60px;
    background: var(--surface-soft);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1.5rem;
}

.report-content {
    flex: 1;
}

.report-content h6 {
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 0.5rem 0;
}

.report-content p {
    color: var(--text-muted);
    margin: 0 0 1rem 0;
    font-size: 0.9rem;
}

/* Empty State */
.empty-state-admin {
    text-align: center;
    padding: 4rem 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    color: var(--text-muted);
}

.empty-state-admin i {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: 0.5;
}

.empty-state-admin h4 {
    /* margin-bottom: 1rem; */
    color: var(--text);
    font-weight: 600;
}

.empty-state-admin p {
    margin-bottom: 0;
    font-size: 1rem;
}

/* Form Styles */
.form-group {
    margin-bottom: 1.5rem;
}

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
    border-radius: var(--radius);
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: var(--transition);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-check {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-check-input {
    width: 18px;
    height: 18px;
}

.form-check-label {
    margin: 0;
    font-weight: 500;
}

.form-text {
    font-size: 0.875rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

/* Modal Footer */
.modal-footer {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    /* padding: 0rem 0; */
    /* border-top: 1px solid var(--border); */
    background: var(--surface-soft);
}

/* Responsive Design */
@media (max-width: 1200px) {
    /* .jenis-cuti-container .row {
        flex-direction: column;
    } */

    /* .action-sidebar {
        position: static;
        margin-top: 2rem;
    } */
}

@media (max-width: 992px) {
    .pengajuan-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }

    .pengajuan-meta {
        align-items: flex-start;
    }

    .jenis-cuti-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .jenis-cuti-badge {
        align-self: flex-start;
    }

    .detail-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .quick-stats {
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .modal-footer {
        width: 100%;
    }
    .stats-overview .row {
        grid-template-columns: repeat(2, 1fr);
    }

    .pengajuan-details {
        flex-direction: column;
        gap: 0.75rem;
    }

    .pengajuan-actions {
        flex-direction: column;
    }

    .pengajuan-actions .btn-action {
        width: 100%;
        justify-content: center;
    }

    .jenis-cuti-actions {
        flex-direction: column;
    }

    .jenis-cuti-actions .btn-action {
        width: 100%;
        justify-content: center;
    }

    .action-cell {
        flex-direction: column;
    }

    .modal-footer {
        flex-direction: column;
    }

    .modal-footer .btn-modern {
        width: 100%;
        justify-content: center;
    }

    .report-card {
        flex-direction: column;
        text-align: center;
    }

    .quick-stats {
        flex-wrap: wrap;
    }

    .stat-badge {
        min-width: 70px;
    }

    /* Mobile slider enhancements */
    .stats-track {
        padding: 0.5rem 1rem;
    }

    .stats-track .stat-card-admin {
        min-width: 180px;
    }
}

@media (max-width: 576px) {
    .stats-overview .row {
        grid-template-columns: 1fr;
    }

    .tab-title {
        font-size: 1.1rem;
    }

    .search-box {
        margin-top: 1rem;
    }

    .employee-info {
        flex-direction: column;
        align-items: flex-start;
    }

    .avatar-sm {
        margin-bottom: 0.5rem;
    }

    /* Enhanced mobile slider */
    .stats-slider-mobile {
        margin: 0 -1rem;
        padding: 0 1rem;
    }

    .stats-track {
        gap: 0.75rem;
    }

    .stats-track .stat-card-admin {
        min-width: 160px;
        padding: 1rem;
    }

    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 1.25rem;
    }

    .stat-content h4 {
        font-size: 1.25rem;
    }

    .stat-content p {
        font-size: 0.8rem;
    }
}

/* Additional Mobile Optimizations */
@media (max-width: 480px) {
    .container-fluid {
        padding: 0 0.5rem;
    }

    .pengajuan-card {
        padding: 1rem;
    }

    .jenis-cuti-card {
        padding: 1rem;
    }

    .action-card {
        padding: 1rem;
    }

    .modal-footer {
        padding: 0.5rem 0rem;
    }

    /* Touch-friendly elements */
    .btn-modern,
    .btn-action {
        min-height: 44px;
    }

    .filter-select,
    .search-input,
    .form-control {
        font-size: 16px; /* Prevent zoom on iOS */
    }
}

/* Print Styles */
@media print {
    .btn-modern,
    .btn-action,
    .modal-footer,
    .action-sidebar {
        display: none !important;
    }

    .pengajuan-card {
        break-inside: avoid;
        border: 1px solid #000;
    }

    .stat-card-admin {
        border: 1px solid #000;
    }
}
</style>
