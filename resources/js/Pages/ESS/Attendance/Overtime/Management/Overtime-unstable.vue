<template>
    <div class="overtime-app-container">
        <!-- Header -->
        <HeaderKpi
            title="Manajemen Lembur Karyawan"
            :subtitle="!isAdmin ? 'Kelola jadwal Lembur' : 'Lembur akses admin'"
            :dayNight="dayNight"
            :formattedDate="formatDateString(TanggalSekarang)"
            :rightSection="true"
            :buttonAction="true"
            buttonText="Ajukan Lembur"
            @button-clicked="openAssignModal"
        />
        <div class="mb-4">
            <QuickStat :stats="quickStatField" />
        </div>

        <!-- Main Content -->
        <div class="main-content-wrapper">
            <div class="content-container">
                <ApprovalTabs :tabs="tabs" :activeTab="activeTab" @tab-change="handleTabChange">
                    <!-- Tab: Dashboard -->
                    <template #dashboard>
                        <!-- Analytics Row -->
                        <div class="row g-4 mb-4">
                            <!-- Chart: Trend Lembur -->
                            <div class="col-lg-8">
                                <div class="stitch-card p-4 border-0 shadow-sm rounded-4 bg-white h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                                            <i class="bi bi-graph-up-arrow text-primary"></i>
                                            Tren Lembur 7 Hari Terakhir
                                        </h5>
                                        <select class="form-select form-select-sm w-auto">
                                            <option>Minggu Ini</option>
                                            <option>Bulan Ini</option>
                                            <option>Tahun Ini</option>
                                        </select>
                                    </div>
                                    <div style="min-height: 300px">
                                        <apexchart
                                            type="area"
                                            height="300"
                                            :options="trendChartOptions"
                                            :series="trendChartSeries"
                                        ></apexchart>
                                    </div>
                                </div>
                            </div>

                            <!-- Chart: Distribusi Departemen -->
                            <div class="col-lg-4">
                                <div class="stitch-card p-4 border-0 shadow-sm rounded-4 bg-white h-100">
                                    <h5 class="fw-bold mb-4 d-flex align-items-center gap-2">
                                        <i class="bi bi-pie-chart text-info"></i>
                                        Distribusi Departemen
                                    </h5>
                                    <div
                                        class="d-flex justify-content-center align-items-center"
                                        style="min-height: 300px"
                                    >
                                        <apexchart
                                            type="donut"
                                            width="100%"
                                            :options="deptChartOptions"
                                            :series="deptChartSeries"
                                        ></apexchart>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Secondary Row -->
                        <div class="row g-4 mb-4">
                            <!-- Chart: Status Pengajuan -->
                            <div class="col-lg-4">
                                <div class="stitch-card p-4 border-0 shadow-sm rounded-4 bg-white h-100">
                                    <h5 class="fw-bold mb-4 d-flex align-items-center gap-2">
                                        <i class="bi bi-activity text-success"></i>
                                        Status Pengajuan
                                    </h5>
                                    <div
                                        class="d-flex justify-content-center align-items-center"
                                        style="min-height: 300px"
                                    >
                                        <apexchart
                                            type="radialBar"
                                            width="100%"
                                            :options="statusChartOptions"
                                            :series="statusChartSeries"
                                        ></apexchart>
                                    </div>
                                </div>
                            </div>

                            <!-- Leaderboard -->
                            <div class="col-lg-8">
                                <div class="stitch-card p-4 border-0 shadow-sm rounded-4 bg-white h-100">
                                    <h5 class="mb-4 fw-bold d-flex align-items-center gap-2">
                                        <i class="bi bi-trophy text-warning"></i>
                                        Leaderboard Lembur Terbanyak
                                    </h5>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Tab: Riwayat -->
                    <template #riwayat>
                        <div class="rounded-4 mb-4" style="background: var(--surface-soft)">
                            <div class="d-flex justify-content-between align-items-end">
                                <div>
                                    <h2 class="h5 fw-bold text-dark mb-1">Riwayat Pengajuan Lembur</h2>
                                    <p class="text-muted text-sm mb-0">Pengajuan lembur untuk Tim.</p>
                                </div>
                                <button
                                    class="stitch-action-btn"
                                    :disabled="loading"
                                    type="button"
                                    @click="refreshRiwayat"
                                >
                                    <i
                                        class="bi bi-arrow-clockwise d-flex justify-content-center align-items-center"
                                        :class="{ spin: loading }"
                                    ></i>
                                </button>
                            </div>
                        </div>

                        <!-- Accordion Filter Section -->
                        <HcisFilterBar
                            :search="tableFilters.search"
                            search-placeholder="Cari nomor transaksi..."
                            :filters="hcisFilters"
                            :filter-values="tableFilters"
                            :results-count="totalData"
                            :collapsible="true"
                            :show-active-tags="true"
                            sentiment="brand"
                            variant="glass"
                            :allow-export="true"
                            class="mb-3"
                            @update:search="handleSearch"
                            @filter-change="handleHcisFilterChange"
                            @reset="clearFilters"
                            @export="openExportDialog"
                        />

                        <!-- Data Table -->
                        <ModernDataTable
                            v-if="viewMode === 'table'"
                            :data="data"
                            :columns="tableColumns"
                            :mobile-columns="mobileColumns"
                            :loading="loading"
                            :fit-to-container="true"
                            :clickable="true"
                            :show-pagination="true"
                            :current-page="currentPage"
                            :total-pages="totalPages"
                            :total-data="totalData"
                            :full-pagination="{
                                first_page_url: null,
                                last_page_url: null,
                                next_page_url: nextLink,
                                prev_page_url: prevLink,
                                from: (currentPage - 1) * itemsPerPage + 1,
                                to: Math.min(currentPage * itemsPerPage, totalData),
                                links: [],
                            }"
                            card-title-key="No_Transaksi"
                            card-subtitle-key="Nama"
                            card-status-key="status"
                            empty-message="Tidak ada transaksi lembur ditemukan"
                            @row-click="showDetailsMethod"
                            @page-change="handlePageChange"
                            @empty-action="clearFilters"
                            empty-action-label="Reset Filter"
                        >
                            <!-- Custom slot untuk kolom No. Transaksi -->
                            <template #column-No_Transaksi="{ item }">
                                <div class="employee-info-cell">
                                    <div class="avatar-sm bg-primary-subtle text-primary">
                                        {{ getInitials(item.No_Transaksi) }}
                                    </div>
                                    <div class="employee-details">
                                        <h6 class="employee-name text-sm fw-bold mb-0 text-dark text-nowrap">
                                            {{ item.No_Transaksi }}
                                        </h6>
                                        <p class="employee-dept text-xs text-muted mb-0">
                                            <span class="jenis-chip">
                                                {{ getJenisLemburLabel(item.Jenis_Lembur) }}
                                            </span>
                                            <span v-if="item.Flag_Admin === 'Y'" class="admin-chip ms-1">
                                                <i class="bi bi-shield-check me-1"></i>Admin
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </template>

                            <!-- Custom slot untuk kolom Tanggal -->
                            <template #column-Tanggal="{ item }">
                                <div class="d-flex flex-column">
                                    <span>{{ formatDate(item.Tanggal) }}</span>
                                    <small class="text-muted">
                                        {{ formatTimeSingle(item.Jam) }}
                                    </small>
                                </div>
                            </template>

                            <!-- Custom slot untuk kolom Pengaju -->
                            <template #column-Pengaju="{ item }">
                                {{ item.Nama || '-' }}
                            </template>

                            <!-- Custom slot untuk kolom Karyawan -->
                            <template #column-Assignment="{ item }">
                                <div class="assignment-cell">
                                    <div class="fw-semibold text-dark">{{ getAssignedCount(item) }} orang</div>
                                    <small class="text-muted">
                                        {{ getAssignmentPreview(item.Assignment) }}
                                    </small>
                                </div>
                            </template>

                            <!-- Custom slot untuk kolom Tanggal Kerja -->
                            <template #column-Effective_Work_Dates="{ item }">
                                {{ item.Effective_Work_Dates || '-' }}
                            </template>

                            <!-- Custom slot untuk kolom Status -->
                            <template #column-Flag_Selesai="{ item }">
                                <Badge v-if="item.Status === 'Y'" type="danger"> Dibatalkan </Badge>
                                <Badge v-else :type="item.Flag_Selesai === 'Y' ? 'success' : 'warning'">
                                    {{ getFlagSelesaiLabel(item.Flag_Selesai) }}
                                </Badge>
                            </template>

                            <!-- Custom slot untuk kolom Aksi -->
                            <template #column-actions="{ item }">
                                <div class="d-flex align-items-center gap-2 justify-content-center">
                                    <button
                                        class="stitch-action-btn text-primary p-2 rounded-lg"
                                        @click.stop="showDetailsMethod(item)"
                                        v-tooltip="'Lihat Detail'"
                                        style="background: var(--surface-soft); border: 1px solid var(--border)"
                                    >
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button
                                        v-if="canDeleteTransaction(item)"
                                        class="stitch-action-btn text-danger p-2 rounded-lg"
                                        @click.stop="deleteAlertButton(item)"
                                        v-tooltip="'Hapus'"
                                        :disabled="!canDeleteTransaction(item)"
                                        style="background: var(--surface-soft); border: 1px solid var(--border)"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </template>

                            <!-- Custom header untuk card view -->
                            <template #card-header="{ item }">
                                <div class="card-title">
                                    {{ item.No_Transaksi }}
                                </div>
                                <div class="card-subtitle">
                                    {{ formatDate(item.Tanggal) }}
                                </div>
                            </template>

                            <template #mobile-Pengaju="{ item }">
                                {{ item.Nama || '-' }}
                            </template>

                            <template #mobile-Jenis_Lembur="{ item }">
                                <span class="jenis-chip">
                                    {{ getJenisLemburLabel(item.Jenis_Lembur) }}
                                </span>
                                <span v-if="item.Flag_Admin === 'Y'" class="admin-chip ms-1">
                                    <i class="bi bi-shield-check me-1"></i>Admin
                                </span>
                            </template>

                            <template #mobile-Tanggal="{ item }">
                                {{ formatDate(item.Tanggal) }} |
                                {{ formatTimeSingle(item.Jam) }}
                            </template>

                            <template #mobile-Assignment="{ item }">
                                {{ getAssignedCount(item) }} orang
                                <small class="d-block text-muted">
                                    {{ getAssignmentPreview(item.Assignment) }}
                                </small>
                            </template>

                            <template #mobile-Effective_Work_Dates="{ item }">
                                {{ item.Effective_Work_Dates || '-' }}
                            </template>

                            <template #mobile-Flag_Selesai="{ item }">
                                <span v-if="item.Status === 'Y'" class="text-danger fw-bold">Dibatalkan</span>
                                <span v-else>{{ getFlagSelesaiLabel(item.Flag_Selesai) }}</span>
                            </template>

                            <!-- Custom actions untuk mobile -->
                            <template #card-actions="{ item }">
                                <div class="d-flex gap-2">
                                    <button
                                        class="stitch-action-btn flex-grow-1 text-primary py-2 px-3 rounded-lg d-flex align-items-center justify-content-center fw-bold"
                                        style="background: var(--surface-soft); border: 1px solid var(--border)"
                                        @click.stop="showDetailsMethod(item)"
                                    >
                                        <i class="bi bi-eye"></i>
                                        <span>Detail</span>
                                    </button>
                                    <button
                                        v-if="canDeleteTransaction(item)"
                                        class="stitch-action-btn flex-grow-1 text-danger py-2 px-3 rounded-lg d-flex align-items-center justify-content-center fw-bold"
                                        style="background: var(--surface-soft); border: 1px solid var(--border)"
                                        @click.stop="deleteAlertButton(item)"
                                        :disabled="!canDeleteTransaction(item)"
                                    >
                                        <i class="bi bi-trash"></i>
                                        <span>Hapus</span>
                                    </button>
                                </div>
                            </template>
                        </ModernDataTable>

                        <!-- Card View -->
                        <div v-else class="card-view-container">
                            <div v-if="loading" class="text-center p-5 loading-state">
                                <div class="loading-spinner">
                                    <i class="bi bi-arrow-repeat spin"></i>
                                </div>
                                <p>Memuat data lembur...</p>
                            </div>

                            <div
                                v-else-if="data.length === 0"
                                style="min-height: 200px"
                                class="text-center p-5 text-muted bg-white rounded-3 shadow-sm border d-flex justify-content-center align-items-center flex-column"
                            >
                                <div class="empty-icon">
                                    <DotLottieVue
                                        style="height: 150px; width: 150px"
                                        autoplay
                                        loop
                                        src="/animation/empty-data.json"
                                    />
                                </div>
                                <h4 class="h6 mb-2">Tidak ada transaksi ditemukan</h4>
                                <p class="small text-muted mb-3">Coba ubah filter atau kata kunci pencarian Anda</p>
                                <button class="stitch-btn primary small" @click="clearFilters">
                                    <i
                                        class="bi bi-arrow-clockwise me-2 d-flex justify-content-center align-items-center"
                                    ></i>
                                    Reset Filter
                                </button>
                            </div>

                            <div v-else class="cards-grid row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                                <div class="col" v-for="item in data" :key="item.No_Transaksi">
                                    <div
                                        class="stitch-card p-0 overflow-hidden border shadow-sm h-100"
                                        @click="showDetailsMethod(item)"
                                        style="cursor: pointer"
                                    >
                                        <!-- Card Header -->
                                        <div class="d-flex align-items-center gap-3 p-3 bg-white border-bottom">
                                            <div
                                                class="avatar-circle bg-primary-subtle text-primary fw-bold shadow-sm d-flex justify-content-center align-items-center"
                                                style="width: 42px; height: 42px; font-size: 1rem; border-radius: 50%"
                                            >
                                                {{ getInitials(item.No_Transaksi) }}
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="fw-bold mb-0 text-dark text-sm">
                                                    {{ item.No_Transaksi }}
                                                </h6>
                                                <p class="text-muted text-xs mb-0">
                                                    {{ formatDate(item.Tanggal) }}
                                                </p>
                                            </div>
                                            <div class="text-sm fw-bold">
                                                {{ formatTimeSingle(item.Jam) }}
                                            </div>
                                        </div>
                                        <!-- Card Body -->
                                        <div
                                            class="p-3 bg-surface-soft h-100 d-flex flex-column justify-content-between"
                                        >
                                            <div
                                                class="d-flex align-items-start gap-2 text-sm text-dark mb-3 flex-column"
                                            >
                                                <div>
                                                    <i class="bi bi-person text-secondary me-2"></i>
                                                    <span>Pengaju: {{ item.Nama || '-' }}</span>
                                                </div>
                                                <div>
                                                    <i class="bi bi-people text-secondary me-2"></i>
                                                    <span>
                                                        {{ getAssignedCount(item) }}
                                                        karyawan
                                                    </span>
                                                </div>
                                                <div>
                                                    <i class="bi bi-tag text-secondary me-2"></i>
                                                    <span>
                                                        Jenis:
                                                        {{ getJenisLemburLabel(item.Jenis_Lembur) }}
                                                    </span>
                                                    <span v-if="item.Flag_Admin === 'Y'" class="admin-chip ms-1">
                                                        <i class="bi bi-shield-check me-1"></i>Admin
                                                    </span>
                                                </div>
                                                <div>
                                                    <i class="bi bi-calendar3 text-secondary me-2"></i>
                                                    <span>
                                                        Tgl Kerja:
                                                        {{ item.Effective_Work_Dates || '-' }}
                                                    </span>
                                                </div>
                                                <div>
                                                    <i class="bi bi-check2-circle text-secondary me-2"></i>
                                                    <span>
                                                        Status:
                                                        <span
                                                            v-if="item.Status === 'Y'"
                                                            class="text-danger fw-bold ms-1"
                                                            >Dibatalkan</span
                                                        >
                                                        <span v-else class="ms-1">{{
                                                            getFlagSelesaiLabel(item.Flag_Selesai)
                                                        }}</span>
                                                    </span>
                                                </div>
                                                <div>
                                                    <i class="bi bi-pencil-square text-secondary me-2"></i>
                                                    <span>
                                                        Updated By:
                                                        {{ item.Updated_By || '-' }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-end gap-2 mt-auto">
                                                <button
                                                    class="stitch-action-btn flex-grow-1 text-primary py-1 px-2 rounded-lg d-flex align-items-center justify-content-center fw-bold text-xs"
                                                    style="background: white; border: 1px solid var(--border)"
                                                    @click.stop="showDetailsMethod(item)"
                                                >
                                                    <i class="bi bi-eye"></i>
                                                    <span class="ms-1">Detail</span>
                                                </button>
                                                <button
                                                    v-if="canDeleteTransaction(item)"
                                                    class="stitch-action-btn flex-grow-1 text-danger py-1 px-2 rounded-lg d-flex align-items-center justify-content-center fw-bold text-xs"
                                                    style="background: white; border: 1px solid var(--border)"
                                                    @click.stop="deleteAlertButton(item)"
                                                    :disabled="!canDeleteTransaction(item)"
                                                >
                                                    <i class="bi bi-trash"></i>
                                                    <span class="ms-1">Hapus</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pagination untuk Card View -->
                            <div
                                class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top border-light"
                                v-if="totalPages > 1"
                            >
                                <button
                                    class="stitch-btn secondary small"
                                    @click="getData(prevLink)"
                                    :disabled="currentPage === 1"
                                >
                                    <i class="bi bi-chevron-left d-none d-md-block me-1"></i>
                                    <span class="d-md-none"><i class="bi bi-chevron-left"></i></span>
                                    <span class="d-none d-md-block">Previous</span>
                                </button>
                                <span class="small text-muted fw-medium">
                                    Halaman
                                    <span class="text-dark">{{ currentPage }}</span>
                                    dari
                                    <span class="text-dark">{{ totalPages }}</span>
                                </span>
                                <button
                                    class="stitch-btn secondary small"
                                    @click="getData(nextLink)"
                                    :disabled="currentPage === totalPages"
                                >
                                    <span class="d-none d-md-block">Next</span>
                                    <i class="bi bi-chevron-right d-none d-md-block ms-1"></i>
                                    <span class="d-md-none"><i class="bi bi-chevron-right"></i></span>
                                </button>
                            </div>
                        </div>

                        <!-- View Toggle
                        <div class="view-toggle-container">
                            <div class="view-toggle">
                                <button
                                    class="toggle-btn"
                                    :class="{ active: viewMode === 'table' }"
                                    @click="viewMode = 'table'"
                                    title="Table View"
                                >
                                    <i class="bi bi-table"></i>
                                    <span>Tabel</span>
                                </button>
                                <button
                                    class="toggle-btn"
                                    :class="{ active: viewMode === 'card' }"
                                    @click="viewMode = 'card'"
                                    title="Card View"
                                >
                                    <i class="bi bi-grid-3x3-gap"></i>
                                    <span>Kartu</span>
                                </button>
                            </div>
                        </div> -->
                    </template>
                </ApprovalTabs>
            </div>
        </div>

        <!-- Assign Overtime Modal -->
        <Modal
            v-if="showAssignModal"
            :show="showAssignModal"
            title="Penugasan Lembur"
            size="large"
            @close="closeAssignModal"
        >
            <template #body>
                <!-- Step Indicator -->
                <div class="step-indicator mb-4">
                    <div
                        v-for="step in 4"
                        :key="step"
                        class="step"
                        :class="{
                            active: assignStep === step,
                            completed: assignStep > step,
                        }"
                    >
                        <div class="step-number">{{ step }}</div>
                        <div class="step-label">
                            {{ getStepLabel(step) }}
                        </div>
                    </div>
                </div>

                <!-- Step 1: Pilih Tanggal -->
                <div v-if="assignStep === 1" class="step-content mt-3">
                    <div class="form-group">
                        <h4 class="section-title">
                            <i class="bi bi-calendar me-2 d-flex justify-content-center align-items-center"></i>
                            Pilih Jenis Lembur
                        </h4>
                        <div class="permission-type-options row g-2">
                            <div class="col-12 col-md-4">
                                <div
                                    class="permission-type-card"
                                    :class="{
                                        selected: assignForm.selectedType === 'LEMBUR_HK',
                                    }"
                                    @click="handleTypeSelect('LEMBUR_HK')"
                                >
                                    <div class="permission-type-icon">
                                        <i class="bi bi-clock-history"></i>
                                    </div>
                                    <div class="permission-type-content">
                                        <h6>Lembur Hari Kerja</h6>
                                        <p class="text-muted small">Lembur untuk hari kerja</p>
                                    </div>
                                    <div class="permission-type-check">
                                        <i
                                            class="bi bi-check-circle-fill"
                                            v-if="assignForm.selectedType === 'LEMBUR_HK'"
                                        ></i>
                                        <i class="bi bi-circle" v-else></i>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <div
                                    class="permission-type-card"
                                    :class="{
                                        selected: assignForm.selectedType === 'LEMBUR_AWAL',
                                    }"
                                    @click="handleTypeSelect('LEMBUR_AWAL')"
                                >
                                    <div class="permission-type-icon">
                                        <i class="bi bi-sunrise"></i>
                                    </div>
                                    <div class="permission-type-content">
                                        <h6>Lembur Awal</h6>
                                        <p class="text-muted small">Lembur sebelum jam masuk shift</p>
                                    </div>
                                    <div class="permission-type-check">
                                        <i
                                            class="bi bi-check-circle-fill"
                                            v-if="assignForm.selectedType === 'LEMBUR_AWAL'"
                                        ></i>
                                        <i class="bi bi-circle" v-else></i>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <div
                                    class="permission-type-card"
                                    :class="{
                                        selected: assignForm.selectedType === 'LEMBUR_LIBUR',
                                    }"
                                    @click="handleTypeSelect('LEMBUR_LIBUR')"
                                >
                                    <div class="permission-type-icon">
                                        <i class="bi bi-calendar-x"></i>
                                    </div>
                                    <div class="permission-type-content">
                                        <h6>Lembur Minggu/Libur</h6>
                                        <p class="text-muted small">Lembur pada hari minggu/libur</p>
                                    </div>
                                    <div class="permission-type-check">
                                        <i
                                            class="bi bi-check-circle-fill"
                                            v-if="assignForm.selectedType === 'LEMBUR_LIBUR'"
                                        ></i>
                                        <i class="bi bi-circle" v-else></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <h4 class="section-title">
                            <i class="bi bi-calendar me-2 d-flex justify-content-center align-items-center"></i>
                            Pilih Tanggal Lembur
                        </h4>
                        <div v-if="isAdmin" class="row g-2">
                            <div class="col-md-12">
                                <el-date-picker
                                    v-model="assignForm.selectedDate"
                                    type="date"
                                    format="DD MMM YYYY"
                                    value-format="YYYY-MM-DD"
                                    placeholder="Pilih tanggal lembur"
                                    class="w-100"
                                    @change="setAssignDate"
                                />
                            </div>
                            <div class="col-12">
                                <small class="text-muted">Admin dapat memilih seluruh tanggal.</small>
                            </div>
                        </div>
                        <div v-else class="d-flex gap-2 justofy-content-center align-items-center">
                            <div
                                v-for="day in availableTanggal"
                                :key="day"
                                class="date-picker-item flex-grow-1 py-3 py-md-4 bg-white"
                                :class="{
                                    selected: assignForm.selectedDate === day,
                                    today: isToday(day),
                                }"
                                @click="setAssignDate(day)"
                            >
                                <div class="date-day">
                                    {{ getDayName(day) }}
                                </div>
                                <div class="date-number">
                                    {{ formatDateShort(day) }}
                                </div>
                                <div class="date-check" v-if="assignForm.selectedDate === day">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Pilih Waktu & Alasan -->
                <div v-if="assignStep === 2" class="step-content">
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="bi bi-clock me-2 d-flex justify-content-center align-items-center"></i>
                            Pilih Waktu & Alasan
                        </h4>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group mb-2">
                                    <label class="form-label">
                                        {{ requiresRangeTime ? 'Range Waktu Lembur' : 'Waktu Lembur' }}
                                    </label>
                                    <div v-if="requiresRangeTime" class="time-range-grid">
                                        <el-time-picker
                                            v-model="assignForm.startTime"
                                            class="el-time-picker-full"
                                            popper-class="el-time-picker-popper-high"
                                            format="HH:mm"
                                            value-format="HH:mm:ss"
                                            placeholder="Mulai lembur"
                                        />
                                        <div class="time-range-separator">s/d</div>
                                        <el-time-picker
                                            v-model="assignForm.endTime"
                                            class="el-time-picker-full"
                                            popper-class="el-time-picker-popper-high"
                                            format="HH:mm"
                                            value-format="HH:mm:ss"
                                            placeholder="Selesai lembur"
                                        />
                                    </div>
                                    <el-time-picker
                                        v-else
                                        v-model="assignForm.singleTime"
                                        class="el-time-picker-full"
                                        popper-class="el-time-picker-popper-high"
                                        format="HH:mm"
                                        value-format="HH:mm:ss"
                                        placeholder="Pilih waktu lembur"
                                        @change="handleSingleTimeChange"
                                    />
                                </div>
                            </div>
                            <div class="p-2 col-md-12 my-0">
                                <div class="alert alert-info" role="alert">
                                    Range jam lembur akan diproses sesuai tipe lembur yang dipilih.
                                </div>
                            </div>
                            <div class="col-md-12 mb-2">
                                <div class="policy-hint-box">
                                    <div class="policy-hint-title">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Ketentuan Pengajuan
                                    </div>
                                    <ul class="policy-hint-list">
                                        <li v-for="(item, idx) in policyHintItems" :key="`policy-${idx}`">
                                            {{ item }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="form-label"
                                        >Alasan Lembur <span class="fw-bold">(min. 50 karakter)</span>
                                    </label>
                                    <textarea
                                        class="form-control"
                                        v-model="assignForm.reason"
                                        rows="4"
                                        minlength="50"
                                        maxlength="300"
                                        placeholder="Mohon tuliskan alasan lembur dengan jelas (minimal 50 karakter)..."
                                        required
                                    ></textarea>
                                    <small
                                        class="d-flex justify-content-end mt-1"
                                        :class="
                                            (assignForm.reason || '').trim().length < 50
                                                ? 'text-danger fw-bold'
                                                : 'text-muted'
                                        "
                                    >
                                        {{ (assignForm.reason || '').trim().length }}/300 karakter
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Pilih Karyawan -->
                <div v-if="assignStep === 3" class="step-content">
                    <div class="form-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="section-title m-0">
                                <i class="bi bi-people me-2"></i>
                                Pilih Karyawan
                                <small class="text-muted ms-2">
                                    ({{ assignEmployeeTotalCount }}
                                    tersedia)
                                </small>
                            </h4>
                            <div class="selected-count badge bg-primary">
                                {{ assignForm.selectedEmployees.length }}
                                dipilih
                            </div>
                        </div>
                        <div v-if="assignSelectionSummary" class="step3-schedule-summary mb-3">
                            <div class="summary-item">
                                <i class="bi bi-calendar3"></i>
                                <span>{{ assignSelectionSummary.date }}</span>
                            </div>
                            <div class="summary-item">
                                <i class="bi bi-clock"></i>
                                <span>{{ assignSelectionSummary.time }}</span>
                            </div>
                            <div class="summary-item">
                                <i class="bi bi-tag"></i>
                                <span>{{ assignSelectionSummary.type }}</span>
                            </div>
                        </div>

                        <div class="search-container assign-search mb-3">
                            <i class="bi bi-search search-icon"></i>
                            <input
                                type="text"
                                class="search-input"
                                placeholder="Cari nama karyawan..."
                                v-model="assignSearchInput"
                                :disabled="loading"
                            />
                        </div>

                        <div v-if="assignForm.selectedEmployees.length > 0" class="selected-employee-panel mb-3">
                            <div class="selected-employee-panel-header">
                                <span class="fw-semibold">Karyawan Terpilih</span>
                                <small class="text-muted">{{ assignForm.selectedEmployees.length }} orang</small>
                            </div>
                            <div class="selected-employee-chip-list">
                                <button
                                    v-for="emp in assignForm.selectedEmployees"
                                    :key="`selected-${emp.stableId || emp.id}`"
                                    type="button"
                                    class="selected-employee-chip"
                                    @click="removeSelectedEmployee(emp.stableId || emp.id)"
                                    title="Klik untuk hapus dari pilihan"
                                >
                                    <span>{{ emp.nama || emp.name }}</span>
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            </div>
                        </div>

                        <div v-if="loading" class="loading-state p-4 text-center">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-3">Memuat data karyawan...</p>
                        </div>

                        <div
                            v-else
                            ref="assignEmployeeList"
                            class="employee-selection-list"
                            @scroll.passive="handleAssignEmployeeScroll"
                        >
                            <div v-if="filteredAssignEmployees.length === 0" class="empty-state p-4 text-center">
                                <i class="bi bi-people fs-1 text-muted mb-3"></i>
                                <h5>Tidak ada karyawan tersedia</h5>
                                <p class="text-muted">Tidak ada karyawan yang memenuhi kriteria untuk tanggal ini</p>
                            </div>

                            <div
                                v-for="employee in visibleAssignEmployees"
                                :key="employee.stableId || employee.userId"
                                class="employee-selection-item"
                                :class="{
                                    selected: isEmployeeSelected(employee.stableId),
                                    disabled: !isEmployeeAvailable(employee),
                                }"
                                @click="toggleEmployeeSelection(employee)"
                            >
                                <div class="employee-selection-card">
                                    <div class="employee-checkbox">
                                        <i v-if="isEmployeeSelected(employee.stableId)" class="bi bi-check"></i>
                                    </div>
                                    <div class="employee-avatar">
                                        {{ getInitials(employee.name) }}
                                    </div>
                                    <div class="employee-details d-flex">
                                        <div class="">
                                            <div class="employee-name">
                                                {{ employee.name }}
                                            </div>
                                            <div class="employee-meta">
                                                <Badge
                                                    :type="getDepartmentBadgeType(employee.Divisi)"
                                                    class="badge-xs me-2"
                                                >
                                                    {{ truncateText(employee.Divisi, 10) }}
                                                </Badge>
                                                <template v-if="isStatusHariOff(employee)">
                                                    <span class="text-muted fw-semibold"> OFF </span>
                                                </template>
                                                <template v-else>
                                                    <span class="text-muted fw-semibold">
                                                        {{ employee.Nama_Shift || employee.shift || '-' }}
                                                    </span>
                                                    <span class="text-muted">
                                                        {{ formatShiftTime(employee.Jam_Masuk, employee.Jam_Keluar) }}
                                                    </span>
                                                </template>
                                            </div>
                                            <div
                                                v-if="isEmployeeSelected(employee.stableId)"
                                                class="employee-actual-time"
                                            >
                                                Aktual:
                                                {{ formatEmployeeActualOvertimeCompact(employee.stableId) }}
                                            </div>
                                            <div
                                                v-if="isEmployeeSelected(employee.stableId)"
                                                class="employee-actual-duration"
                                            >
                                                Durasi:
                                                {{ formatEmployeeFinalDurationCompact(employee.stableId) }}
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center ms-auto">
                                            <div
                                                v-if="!isEmployeeSelected(employee.stableId)"
                                                class="employee-status-badge fade-in"
                                                :class="{
                                                    available: isEmployeeAvailable(employee),
                                                    unavailable: !isEmployeeAvailable(employee),
                                                }"
                                            >
                                                {{ isEmployeeAvailable(employee) ? 'Tersedia' : 'Tidak Tersedia' }}
                                            </div>
                                            <div v-else class="d-flex gap-2">
                                                <!-- <div
                                                    @click.stop
                                                    class="reInput-timeStart position-relative"
                                                >
                                                    <span class="me-2"
                                                        >Mulai:</span
                                                    >
                                                    <input
                                                        type="time"
                                                        class="form-control sm"
                                                        v-model="
                                                            employee.startTime
                                                        "
                                                    />
                                                </div>
                                                <div
                                                    @click.stop
                                                    class="reInput-timeStart position-relative"
                                                >
                                                    <span class="me-2"
                                                        >Selesai:</span
                                                    >
                                                    <input
                                                        type="time"
                                                        class="form-control sm"
                                                        v-model="
                                                            employee.endTime
                                                        "
                                                    />
                                                </div> -->
                                                <div
                                                    @click.stop="openExpandModal(employee)"
                                                    v-tooltip="'Edit Detail Penugasan'"
                                                    class="expand-button employee-status-badge px-3 py-2 stitch-btn primary popup-animation"
                                                >
                                                    <!-- Edit Detail -->
                                                    <i
                                                        class="bi bi-arrows-angle-expand d-flex justify-content-center align-items-center"
                                                    ></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="!isEmployeeAvailable(employee)" class="employee-unavailable-reason">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    {{ getUnavailableReason(employee) }}
                                </div>
                            </div>

                            <div v-if="hasMoreAssignEmployees" class="employee-load-more">
                                <button
                                    type="button"
                                    class="btn-load-more"
                                    :disabled="assignEmployeeChunkLoading"
                                    @click.stop="loadMoreAssignEmployees"
                                >
                                    <span v-if="assignEmployeeChunkLoading">Memuat...</span>
                                    <span v-else>Load more</span>
                                </button>
                                <small>
                                    Menampilkan
                                    {{ visibleAssignEmployees.length }} dari
                                    {{ assignEmployeeTotalCount }}
                                    karyawan
                                </small>
                            </div>
                        </div>

                        <div class="selection-actions mt-3">
                            <button class="btn-link" @click="selectAllAvailableEmployees" v-if="canSelectAll">
                                <i class="bi bi-check-all me-1"></i>
                                Pilih Semua yang Tersedia
                            </button>
                            <button
                                class="btn-link text-danger"
                                @click="clearEmployeeSelection"
                                v-if="assignForm.selectedEmployees.length > 0"
                            >
                                <i class="bi bi-x-circle me-1"></i>
                                Hapus Semua
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Review -->
                <div v-if="assignStep === 4" class="step-content">
                    <div class="form-section review-section">
                        <h4 class="section-title">
                            <i class="bi bi-clipboard-check me-2"></i>
                            Review Pengajuan
                        </h4>

                        <div class="review-summary-grid mb-3">
                            <div class="review-card">
                                <div class="review-label">Tanggal Kerja</div>
                                <div class="review-value">
                                    {{ formatDatePanjang(assignForm.selectedDate) }}
                                </div>
                            </div>
                            <div class="review-card">
                                <div class="review-label">Jenis Lembur</div>
                                <div class="review-value">
                                    {{ getTypeLabel(assignForm.selectedType) }}
                                </div>
                            </div>
                            <div class="review-card">
                                <div class="review-label">Jam Lembur</div>
                                <div class="review-value">
                                    {{ getReviewTimeLabel() }}
                                </div>
                            </div>
                            <div class="review-card">
                                <div class="review-label">Total Karyawan</div>
                                <div class="review-value">
                                    {{ assignForm.selectedEmployees.length }}
                                    orang
                                </div>
                            </div>
                        </div>

                        <div class="review-reason-box mb-3">
                            <div class="review-label">Alasan Lembur<span class="fw-bold">(min. 50 karakter)</span></div>
                            <div class="review-reason-text">
                                {{ assignForm.reason || '-' }}
                            </div>
                        </div>

                        <div class="review-employee-list">
                            <div class="review-employee-header">Karyawan yang akan diajukan</div>
                            <div class="review-employee-items">
                                <div
                                    v-for="emp in assignForm.selectedEmployees"
                                    :key="emp.id"
                                    class="review-employee-item"
                                >
                                    <div class="review-emp-name">
                                        {{ emp.nama }}
                                    </div>
                                    <div class="review-emp-meta">
                                        <template v-if="isStatusHariOff(emp)"> {{ emp.divisi || '-' }} | OFF </template>
                                        <template v-else>
                                            {{ emp.divisi || '-' }} | Shift: {{ emp.shift || '-' }} | Jam Shift:
                                            {{ formatTimeSingle(emp.jamMasuk) }}
                                            -
                                            {{ formatTimeSingle(emp.jamKeluar) }}
                                        </template>
                                    </div>
                                    <div class="review-emp-time">
                                        Lembur:
                                        {{ formatReviewOvertimeDateTime(emp) }}
                                    </div>
                                    <div class="review-emp-time">
                                        Durasi:
                                        {{ formatEmployeeFinalDuration(emp) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template #footer>
                <!-- <button
                    class="stitch-btn secondary d-md-none d-block me-auto"
                    @click="closeAssignModal"
                >
                    Batal
                </button> -->
                <div class="d-flex w-100 justify-content-end gap-2">
                    <button
                        v-if="assignStep > 1"
                        class="stitch-btn secondary"
                        @click="prevAssignStep"
                        :disabled="isAssignStepProcessing || isSubmitting"
                    >
                        <i class="bi bi-arrow-left me-2 d-flex justify-content-center align-items-center"></i>
                        Kembali
                    </button>
                    <button
                        class="stitch-btn secondary"
                        @click="closeAssignModal"
                        :disabled="isAssignStepProcessing || isSubmitting"
                    >
                        Batal
                    </button>
                    <button
                        v-if="assignStep < 4"
                        class="stitch-btn primary"
                        @click="nextAssignStep"
                        :disabled="!canProceedToNextStep || isAssignStepProcessing || isSubmitting"
                    >
                        <i
                            v-if="isAssignStepProcessing"
                            class="bi bi-arrow-repeat spin me-2 d-flex justify-content-center align-items-center"
                        ></i>
                        {{ isAssignStepProcessing ? 'Memproses...' : 'Lanjut' }}
                        <i
                            v-if="!isAssignStepProcessing"
                            class="bi bi-arrow-right ms-2 d-flex justify-content-center align-items-center"
                        ></i>
                    </button>
                    <button
                        v-if="assignStep === 4"
                        class="btn-modern btn-success"
                        @click="submitOvertime"
                        :disabled="isSubmitting || isAssignStepProcessing || !canProceedToNextStep"
                    >
                        <i
                            v-if="isSubmitting"
                            class="bi bi-arrow-repeat spin me-2 d-flex justify-content-center align-items-center"
                        ></i>
                        <i v-else class="bi bi-check-lg me-2 d-flex justify-content-center align-items-center"></i>
                        {{ isSubmitting ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Modal Expand employee -->
        <Modal
            v-if="showExpandModal"
            :show="showExpandModal"
            :title="`Detail Lembur - ${getExpandEmployeeDisplayName()}`"
            size="medium"
            @close="closeExpandModal"
        >
            <!-- Form Edit Input Expand Employee -->
            <template #body>
                <div v-if="selectedExpandEmployee" class="detail-content">
                    <div class="employee-expand-details">
                        <div class="employee-info d-flex align-items-center mb-3">
                            <div class="employee-avatar me-3">
                                {{ getInitials(getExpandEmployeeDisplayName()) }}
                            </div>
                            <div>
                                <h5 class="employee-name mb-0">
                                    {{ getExpandEmployeeDisplayName() }}
                                </h5>
                                <p class="employee-meta mb-0 text-muted">
                                    {{
                                        truncateText(selectedExpandEmployee.Divisi || selectedExpandEmployee.divisi, 20)
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label">
                                {{ requiresRangeTimeForExpand ? 'Range Waktu Lembur' : 'Waktu Lembur' }}
                            </label>
                            <div v-if="requiresRangeTimeForExpand" class="time-range-grid">
                                <el-time-picker
                                    v-model="selectedExpandEmployee.startTime"
                                    class="el-time-picker-full"
                                    popper-class="el-time-picker-popper-high"
                                    format="HH:mm"
                                    value-format="HH:mm:ss"
                                    placeholder="Mulai lembur"
                                    :disabled-hours="() => getDisabledHoursForEmployee(selectedExpandEmployee)"
                                    :disabled-minutes="
                                        (hour) => getDisabledMinutesForEmployee(selectedExpandEmployee, hour)
                                    "
                                />
                                <div class="time-range-separator">s/d</div>
                                <el-time-picker
                                    v-model="selectedExpandEmployee.endTime"
                                    class="el-time-picker-full"
                                    popper-class="el-time-picker-popper-high"
                                    format="HH:mm"
                                    value-format="HH:mm:ss"
                                    placeholder="Selesai lembur"
                                    :disabled-hours="() => getDisabledHoursForEmployee(selectedExpandEmployee)"
                                    :disabled-minutes="
                                        (hour) => getDisabledMinutesForEmployee(selectedExpandEmployee, hour)
                                    "
                                />
                            </div>
                            <el-time-picker
                                v-else
                                v-model="expandSingleTimeModel"
                                class="el-time-picker-full"
                                popper-class="el-time-picker-popper-high"
                                format="HH:mm"
                                value-format="HH:mm:ss"
                                placeholder="Pilih waktu lembur"
                                :disabled-hours="() => getDisabledHoursForEmployee(selectedExpandEmployee)"
                                :disabled-minutes="
                                    (hour) => getDisabledMinutesForEmployee(selectedExpandEmployee, hour)
                                "
                            />
                        </div>
                        <div class="form-group">
                            <label class="form-label"
                                >Alasan Lembur<span class="fw-bold">(min. 50 karakter)</span></label
                            >
                            <textarea
                                class="form-control"
                                v-model="selectedExpandEmployee.reason"
                                rows="4"
                                minlength="50"
                                maxlength="300"
                                placeholder="Mohon tuliskan alasan lembur khusus (minimal 50 karakter)..."
                            ></textarea>
                            <small
                                class="d-flex justify-content-end mt-1"
                                :class="
                                    selectedExpandEmployee.reason &&
                                    selectedExpandEmployee.reason.trim().length > 0 &&
                                    selectedExpandEmployee.reason.trim().length < 50
                                        ? 'text-danger fw-bold'
                                        : 'text-muted'
                                "
                            >
                                {{
                                    selectedExpandEmployee.reason ? selectedExpandEmployee.reason.trim().length : 0
                                }}/300 karakter
                                <span v-if="!selectedExpandEmployee.reason" class="ms-1"
                                    >(Kosongkan untuk menggunakan alasan utama)</span
                                >
                            </small>
                        </div>
                    </div>
                </div>
            </template>

            <template #footer>
                <div class="d-flex w-100 justify-content-end gap-2">
                    <button class="stitch-btn secondary" @click="closeExpandModal">Batal</button>
                    <button class="btn-modern btn-primary" @click="saveExpandEmployee">Simpan</button>
                </div>
            </template>
        </Modal>

        <!-- Detail Modal -->
        <Modal
            v-if="showDetailModal"
            :show="showDetailModal"
            :title="`Detail Transaksi - ${selectedTransaction?.No_Transaksi || ''}`"
            size="large"
            @close="closeDetailModal"
        >
            <template #body>
                <div v-if="detailLoading" class="loading-state p-5 text-center">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-3">Memuat detail transaksi...</p>
                </div>

                <div v-else-if="selectedTransaction" class="detail-content">
                    <!-- Transaction Header -->
                    <div class="transaction-summary-card mb-4">
                        <div class="transaction-summary-top">
                            <div class="summary-title-wrap">
                                <i class="bi bi-calendar-check"></i>
                                <div>
                                    <small>No. Transaksi</small>
                                    <h5>
                                        {{ selectedTransaction.No_Transaksi }}
                                    </h5>
                                </div>
                            </div>
                            <span
                                class="summary-status-badge"
                                :class="getTransactionStatusBadgeClass(selectedTransaction)"
                            >
                                {{ getTransactionStatusLabel(selectedTransaction) }}
                            </span>
                            <button
                                v-if="canDeleteTransaction(selectedTransaction)"
                                class="stitch-action-btn text-danger p-2 rounded-lg"
                                style="background: var(--surface-soft); border: 1px solid var(--border)"
                                @click.stop="deleteAlertButton(selectedTransaction)"
                                v-tooltip="'Hapus Transaksi'"
                                :disabled="!canDeleteTransaction(selectedTransaction)"
                            >
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>

                        <!-- Cancellation Reason Alert -->
                        <div v-if="selectedTransaction.Status === 'Y'" class="cancellation-card mt-3">
                            <div class="cancellation-card-head">
                                <i class="bi bi-x-octagon-fill"></i>
                                <span>Transaksi Dibatalkan</span>
                            </div>
                            <p class="cancellation-reason">
                                <i class="bi bi-chat-left-quote me-1"></i>
                                {{ selectedTransaction.Alasan_Pembatalan || 'Tidak ada alasan' }}
                            </p>
                            <div class="cancellation-meta">
                                <div class="cancellation-meta-item">
                                    <i class="bi bi-person-x"></i>
                                    <div>
                                        <small>Dibatalkan oleh</small>
                                        <span>{{
                                            selectedTransaction.Updated_By_Nama || selectedTransaction.Updated_By || '-'
                                        }}</span>
                                    </div>
                                </div>
                                <div class="cancellation-meta-item">
                                    <i class="bi bi-clock-history"></i>
                                    <div>
                                        <small>Dibatalkan pada</small>
                                        <span>{{ formatDateTime(selectedTransaction.Updated_At) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="transaction-summary-grid" :class="{ 'mt-3': selectedTransaction.Status !== 'Y' }">
                            <div class="summary-field">
                                <label>Dibuat</label>
                                <p>
                                    {{ formatDate(selectedTransaction.Tanggal) }}
                                    {{ formatTimeSingle(selectedTransaction.Jam) }}
                                </p>
                            </div>
                            <div class="summary-field">
                                <label>User</label>
                                <p>{{ selectedTransaction.Nama || '-' }}</p>
                            </div>
                            <div class="summary-field">
                                <label>Jenis Lembur</label>
                                <p>
                                    {{ getJenisLemburLabel(selectedTransaction.Jenis_Lembur) }}
                                </p>
                            </div>
                            <div class="summary-field">
                                <label>Perusahaan</label>
                                <p>
                                    {{ selectedTransaction.Kode_Perusahaan || '-' }}
                                </p>
                            </div>
                            <div class="summary-field">
                                <label>Total Karyawan</label>
                                <p>{{ detailTransaksi.length }} orang</p>
                            </div>
                        </div>
                    </div>

                    <!-- Employee List -->
                    <div class="employee-details-section">
                        <h5 class="section-title mb-3">
                            <i class="bi bi-people me-2"></i>
                            Daftar Karyawan Lembur
                            <small class="text-muted">({{ detailTransaksi.length }} orang)</small>
                        </h5>

                        <ModernDataTable
                            :data="detailTransaksi"
                            :columns="detailColumns"
                            :mobile-columns="detailMobileColumns"
                            :loading="detailLoading"
                            :show-pagination="false"
                            :show-actions="false"
                            card-title-key="name"
                            card-subtitle-key=""
                            empty-message="Tidak ada detail karyawan ditemukan"
                        >
                            <!-- Custom slot untuk kolom Nama -->
                            <template #column-name="{ item }">
                                <div class="employee-info-cell">
                                    <div class="avatar-sm">
                                        {{ getInitials(item.name) }}
                                    </div>
                                    <div class="employee-details">
                                        <h6 class="employee-name">
                                            {{ item.name }}
                                        </h6>
                                        <div class="employee-shift-name">
                                            {{ item.Nama_Shift || 'OFF' }}
                                        </div>
                                        <small class="employee-shift-time">
                                            {{
                                                item.Jam_Masuk && item.Jam_Keluar
                                                    ? formatShiftTime(item.Jam_Masuk, item.Jam_Keluar)
                                                    : '-'
                                            }}
                                        </small>
                                    </div>
                                </div>
                            </template>

                            <!-- Custom slot untuk kolom Jenis -->
                            <template #column-jenis="{ item }">
                                {{ getJenisLemburLabel(item.jenis_lembur) }}
                            </template>

                            <!-- Custom slot untuk kolom Hari Kerja -->
                            <template #column-tanggal="{ item }">
                                {{ formatDate(getEffectiveWorkDateForDetail(item)) }}
                            </template>

                            <!-- Custom slot untuk kolom Tanggal Lembur (aktual) -->
                            <template #column-tgl_lembur="{ item }">
                                <div class="d-flex flex-column">
                                    <span>{{ formatDate(item.start_time) }}</span>
                                    <small class="text-muted">
                                        {{ formatTimeRangeWithDate(item.start_time, item.end_time) }}
                                    </small>
                                </div>
                            </template>

                            <!-- Custom slot untuk kolom Waktu -->
                            <template #column-waktu="{ item }">
                                {{ formatTimeRangeWithDate(item.start_time, item.end_time) }}
                            </template>

                            <!-- Custom slot untuk kolom Durasi -->
                            <template #column-durasi="{ item }">
                                {{ formatDuration(item.start_time, item.end_time) }}
                            </template>

                            <!-- Custom slot untuk kolom Alasan -->
                            <template #column-alasan="{ item }">
                                <div class="detail-reason-wrap">
                                    {{ item.reason || '-' }}
                                </div>
                            </template>

                            <!-- Custom slot untuk kolom Status -->
                            <template #column-status="{ item }">
                                <div class="detail-status-stack">
                                    <!-- <span
                                        class="detail-status-chip"
                                        :class="
                                            getDetailReviewStatus(item).chipClass
                                        "
                                    >
                                        {{
                                            getDetailReviewStatus(item).label
                                        }}
                                    </span> -->
                                    <span class="detail-status-chip" :class="getDetailAttendanceStatus(item).chipClass">
                                        {{ getDetailAttendanceStatus(item).label }}
                                    </span>
                                    <small v-if="getDetailAttendanceStatus(item).note" class="detail-status-note">
                                        {{ getDetailAttendanceStatus(item).note }}
                                    </small>
                                </div>
                            </template>

                            <!-- Mobile slot untuk kolom virtual detail -->
                            <template #mobile-tanggal="{ item }">
                                {{ formatDate(getEffectiveWorkDateForDetail(item)) }}
                            </template>
                            <template #mobile-tgl_lembur="{ item }">
                                <div class="d-flex flex-column">
                                    <span>{{ formatDate(item.start_time) }}</span>
                                    <small class="text-muted">
                                        {{ formatTimeRangeWithDate(item.start_time, item.end_time) }}
                                    </small>
                                </div>
                            </template>
                            <template #mobile-waktu="{ item }">
                                {{ formatTimeRangeWithDate(item.start_time, item.end_time) }}
                            </template>
                            <template #mobile-alasan="{ item }">
                                <div class="detail-reason-wrap">
                                    {{ item.reason || '-' }}
                                </div>
                            </template>
                            <template #mobile-jenis="{ item }">
                                {{ getJenisLemburLabel(item.jenis_lembur) }}
                            </template>
                            <template #mobile-name="{ item }">
                                <div class="employee-name-mobile-wrap">
                                    <div class="employee-name-mobile-main">
                                        {{ item.name || '-' }}
                                    </div>
                                    <div class="employee-shift-name">
                                        {{ item.Nama_Shift || 'OFF' }}
                                    </div>
                                    <small class="employee-shift-time">
                                        {{
                                            item.Jam_Masuk && item.Jam_Keluar
                                                ? formatShiftTime(item.Jam_Masuk, item.Jam_Keluar)
                                                : '-'
                                        }}
                                    </small>
                                </div>
                            </template>
                        </ModernDataTable>
                    </div>
                </div>
            </template>

            <template #footer>
                <div class="d-flex w-100 justify-content-end gap-2">
                    <button class="stitch-btn secondary" @click="closeDetailModal">Tutup</button>
                </div>
            </template>
        </Modal>

        <!-- Alert Modal -->
        <Modal
            v-if="showAlertModal"
            :show="showAlertModal"
            :title="titleAlertModal"
            size="medium"
            @close="closeAlertModal"
        >
            <template #body>
                <div class="confirmation-content text-center">
                    <i class="bi bi-exclamation-triangle fs-1 text-warning mb-3"></i>
                    <p class="mb-3">{{ bodyAlert }}</p>

                    <!-- Cancellation reason input (only for delete) -->
                    <div v-if="isDeleteAlert" class="text-start mt-3">
                        <label class="form-label fw-semibold" style="font-size: 0.9rem">
                            <i class="bi bi-chat-left-text me-1"></i>
                            Alasan Pembatalan <span class="text-danger">*</span>
                        </label>
                        <textarea
                            v-model="cancelReason"
                            class="form-control"
                            rows="3"
                            maxlength="100"
                            placeholder="Tuliskan alasan pembatalan transaksi lembur ini..."
                            style="resize: none; font-size: 0.9rem"
                        ></textarea>
                        <div class="d-flex justify-content-between mt-1">
                            <small
                                :class="cancelReason.length < 10 ? 'text-danger' : 'text-muted'"
                                style="font-size: 0.78rem"
                            >
                                {{
                                    cancelReason.length < 10
                                        ? `Minimal 10 karakter (kurang ${10 - cancelReason.length})`
                                        : '✓ Alasan valid'
                                }}
                            </small>
                            <small
                                :class="cancelReason.length > 90 ? 'text-warning' : 'text-muted'"
                                style="font-size: 0.78rem"
                            >
                                {{ cancelReason.length }}/100
                            </small>
                        </div>
                    </div>
                </div>
            </template>

            <template #footer>
                <div class="d-flex w-100 justify-content-end gap-2">
                    <button class="stitch-btn secondary" @click="closeAlertModal" :disabled="loading">Batal</button>
                    <button
                        class="btn-modern"
                        :class="buttonAlertModal === 'Hapus' ? 'btn-danger' : 'btn-success'"
                        @click="yesModal"
                        :disabled="loading || (isDeleteAlert && cancelReason.length < 10)"
                    >
                        <i v-if="loading" class="bi bi-arrow-repeat spin me-2"></i>
                        <i v-else class="bi bi-check-lg me-2"></i>
                        {{ buttonAlertModal }}
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Calendar Export Modal -->
        <Modal
            v-if="dialogVisible"
            :show="dialogVisible"
            title="Export Data Lembur"
            size="medium"
            @close="handleCancel"
        >
            <template #body>
                <div class="calendar-container">
                    <el-calendar v-model="calendarDate">
                        <template #date-cell="{ data }">
                            <div class="cell-wrapper" :class="getCellClass(data)" @click="handleDateClick(data)">
                                <div class="date-cell">
                                    {{ data.day.split('-')[2] }}
                                </div>
                            </div>
                        </template>
                    </el-calendar>

                    <div class="date-range-display mt-3">
                        <div v-if="tempStartDate && tempEndDate" class="alert alert-info">
                            <i class="bi bi-calendar-range me-2"></i>
                            Periode yang dipilih:
                            {{ formatDate(tempStartDate) }} s.d
                            {{ formatDate(tempEndDate) }}
                        </div>
                        <div v-else-if="tempStartDate" class="alert alert-warning">
                            <i class="bi bi-info-circle me-2"></i>
                            Pilih tanggal akhir untuk menyelesaikan range
                        </div>
                    </div>
                </div>
            </template>

            <template #footer>
                <div class="d-flex w-100 justify-content-end gap-2">
                    <button class="stitch-btn secondary" @click="handleCancel">Batal</button>
                    <button
                        class="btn-modern btn-primary"
                        @click="confirmDate"
                        :disabled="!tempStartDate || !tempEndDate"
                    >
                        <i class="bi bi-download me-2"></i>
                        Export Data
                    </button>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import axios from 'axios';
import { DotLottieVue } from '@lottiefiles/dotlottie-vue';
import { ElMessage, ElNotification, ElCalendar, ElDatePicker, ElTimePicker } from 'element-plus';
import { debounce } from 'lodash';
import HeaderKpi from '@/components/legacy/header.vue';
import Modal from '@/components/legacy/modalV2-noir.vue';
import QuickStat from '@/components/legacy/quickStat.vue';
import ModernDataTable from '@/components/legacy/table-noir.vue';
import FilterPanel from '@/components/legacy/Filter/modernFilter.vue';
import { HcisFilterBar } from '@/components/ui';
import Badge from '../../../components/ui/badge.vue';
import ApprovalTabs from '@/components/legacy/toggleSectionV2.vue';
import VueApexCharts from 'vue3-apexcharts';

export default {
    components: {
        DotLottieVue,
        ElCalendar,
        ElDatePicker,
        ElTimePicker,
        HeaderKpi,
        Modal,
        QuickStat,
        ModernDataTable,
        FilterPanel,
        HcisFilterBar,
        Badge,
        ApprovalTabs,
        apexchart: VueApexCharts,
    },
    props: {
        tanggal: {
            type: Array,
            default: () => [],
        },
        shiftList: {
            type: Array,
            default: () => [],
        },
        isAdmin: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            data: [],
            totalData: 0,
            // Dihitung server bersama daftarnya (kunci `ringkasan`), memakai
            // saringan yang sama. JANGAN diturunkan dari `this.data`: itu hanya
            // satu halaman paginasi.
            ringkasan: { total: 0, hari_ini: 0, pending: 0 },
            nextLink: null,
            prevLink: null,
            totalPages: 0,
            currentPage: 1,
            itemsPerPage: 10,

            serverTime: null,
            TanggalSekarang: new Date(),

            // Filter & Search
            tableFilters: {
                search: '',
                tanggal_range: [],
                jenis: '',
            },

            // Tabs
            activeTab: 'riwayat',
            tabs: [
                // {
                //     id: "dashboard",
                //     label: "Dashboard",
                //     icon: "bi-pie-chart",
                //     count: null,
                // },
                {
                    id: 'riwayat',
                    label: 'Riwayat',
                    icon: 'bi-clock-history',
                    count: null,
                },
            ],

            // Mock Chart Data
            trendChartSeries: [
                {
                    name: 'Jam Lembur',
                    data: [12, 18, 14, 25, 20, 30, 22],
                },
            ],
            trendChartOptions: {
                chart: {
                    type: 'area',
                    fontFamily: 'Inter, sans-serif',
                    toolbar: { show: false },
                    zoom: { enabled: false },
                },
                colors: ['#6366f1'],
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 2 },
                xaxis: {
                    categories: ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'],
                    labels: { style: { colors: '#64748b' } },
                },
                yaxis: { labels: { style: { colors: '#64748b' } } },
                grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.4,
                        opacityTo: 0.05,
                        stops: [0, 90, 100],
                    },
                },
                tooltip: { theme: 'light' },
            },
            deptChartSeries: [45, 30, 15, 10],
            deptChartOptions: {
                chart: { type: 'donut', fontFamily: 'Inter, sans-serif' },
                labels: ['IT', 'HRD', 'Finance', 'Ops'],
                colors: ['#6366f1', '#3b82f6', '#10b981', '#f59e0b'],
                plotOptions: {
                    pie: {
                        donut: {
                            size: '70%',
                            labels: {
                                show: true,
                                name: { show: true },
                                value: { show: true },
                            },
                        },
                    },
                },
                dataLabels: { enabled: false },
                legend: { position: 'bottom', markers: { radius: 12 } },
                tooltip: { theme: 'light' },
            },
            statusChartSeries: [65, 25, 10],
            statusChartOptions: {
                chart: { type: 'radialBar', fontFamily: 'Inter, sans-serif' },
                plotOptions: {
                    radialBar: {
                        dataLabels: {
                            name: { fontSize: '14px', color: '#64748b' },
                            value: {
                                fontSize: '20px',
                                fontWeight: 700,
                                color: '#1e293b',
                            },
                            total: {
                                show: true,
                                label: 'Total',
                                formatter: function () {
                                    return 100;
                                },
                            },
                        },
                    },
                },
                labels: ['Approved', 'Pending', 'Rejected'],
                colors: ['#10b981', '#f59e0b', '#ef4444'],
            },

            // Mock Leaderboard Data
            leaderboardData: [
                {
                    id: 1,
                    name: 'Budi Santoso',
                    department: 'IT',
                    hours: 45,
                    percentage: 80,
                },
                {
                    id: 2,
                    name: 'Andi Wijaya',
                    department: 'HRD',
                    hours: 38,
                    percentage: 65,
                },
                {
                    id: 3,
                    name: 'Siti Aminah',
                    department: 'Finance',
                    hours: 32,
                    percentage: 55,
                },
                {
                    id: 4,
                    name: 'Dewi Lestari',
                    department: 'Marketing',
                    hours: 28,
                    percentage: 48,
                },
                {
                    id: 5,
                    name: 'Rina Kusuma',
                    department: 'Operations',
                    hours: 25,
                    percentage: 42,
                },
            ],

            // View Mode
            viewMode: 'table',

            // Export
            dialogVisible: false,
            tempStartDate: null,
            tempEndDate: null,
            calendarDate: new Date(),

            // Detail Modal
            showDetailModal: false,
            selectedTransaction: null,
            detailTransaksi: [],
            detailLoading: false,

            // Alert Modal
            showAlertModal: false,
            titleAlertModal: '',
            buttonAlertModal: '',
            bodyAlert: '',
            yesModal: null,
            cancelReason: '',
            isDeleteAlert: false,

            // Assign Modal
            showAssignModal: false,
            assignStep: 1,
            isAssignStepProcessing: false,
            assignSearchInput: '',
            assignSearchQuery: '',
            assignEmployeeBatchSize: 10,
            assignEmployeeVisibleCount: 10,
            assignEmployeeScrollThreshold: 80,
            assignEmployeeChunkLoading: false,
            assignEmployeeServerPage: 0,
            assignEmployeeServerPerPage: 10,
            assignEmployeeServerHasMore: false,
            assignEmployeeServerTotal: 0,
            assignSearchDebounceTimer: null,
            assignEmployeeAbortController: null,
            assignForm: {
                selectedType: null,
                selectedDate: null,
                endTime: null,
                startTime: null,
                singleTime: null,
                reason: '',
                selectedEmployees: [],
            },
            userChoose: [],
            isSubmitting: false,

            // Loading
            loading: false,

            // Expand Employee Modal
            showExpandModal: false,
            selectedExpandEmployee: null,
            lastSelectionContextKey: null,
            policyConfig: {
                batasJamRequestLembur: 4,
                minimalDurasiLembur: 1,
                batasDurasiLembur: 18,
                batasDurasiLemburLibur: 18,
                batasKedekatanAkhirDenganJamKerja: 0.5,
                batasLemburAwalDariJamMasuk: 5,
            },
        };
    },
    computed: {
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? 'Siang' : 'Malam';
        },

        quickStatField() {
            return [
                {
                    title: 'Total Transaksi',
                    value: this.totalData,
                    icon: 'bi bi-file-text text-primary',
                    bgIcon: 'bg-primary-light',
                    size: 'col-4',
                },
                {
                    title: 'Hari Ini',
                    value: this.ringkasan.hari_ini,
                    icon: 'bi bi-calendar-day ',
                    bgIcon: 'bg-success-light',
                    size: 'col-4',
                },
                {
                    title: 'Pending',
                    value: this.ringkasan.pending,
                    icon: 'bi bi-clock-history text-warning',
                    bgIcon: 'bg-warning-light',
                    size: 'col-4',
                },
            ];
        },

        hcisFilters() {
            return [
                {
                    key: 'tanggal_range',
                    label: 'Filter Tanggal Kerja',
                    type: 'daterange',
                    col: 8,
                    placeholder: 'Pilih rentang tanggal kerja',
                },
                {
                    key: 'jenis',
                    label: 'Jenis Lembur',
                    type: 'select',
                    col: 4,
                    placeholder: 'Semua jenis',
                    options: [
                        { label: 'Semua Jenis', value: '' },
                        { label: 'Lembur Hari Kerja', value: 'LEMBUR_HK' },
                        { label: 'Lembur Awal', value: 'LEMBUR_AWAL' },
                        { label: 'Lembur Minggu/Libur', value: 'LEMBUR_LIBUR' },
                    ],
                },
            ];
        },

        filterConfig() {
            return {
                searchable: true,
                searchPlaceholder: 'Cari nomor transaksi (dari awal)…',
                searchCol: 3,
                showResultsCount: true,
                showActiveFilters: true,
                showActions: true,
                showReset: true,
                showClearAll: true,
                mobileTrigger: true,
                filters: [
                    {
                        key: 'tanggal_range',
                        label: 'Filter Tanggal Kerja',
                        type: 'daterange',
                        colClass: 'col-md-7 col-12',
                        dateType: 'daterange',
                        placeholder: 'Pilih rentang tanggal kerja',
                        format: 'DD/MM/YYYY',
                        valueFormat: 'YYYY-MM-DD',
                    },
                    {
                        key: 'jenis',
                        label: 'Jenis Lembur',
                        type: 'select',
                        colClass: 'col-md-3 col-6',
                        placeholder: 'Semua jenis',
                        options: [
                            {
                                label: 'Lembur Hari Kerja',
                                value: 'LEMBUR_HK',
                            },
                            { label: 'Lembur Awal', value: 'LEMBUR_AWAL' },
                            {
                                label: 'Lembur Minggu/Libur',
                                value: 'LEMBUR_LIBUR',
                            },
                        ],
                    },
                    {
                        key: 'export',
                        label: 'Export Data',
                        type: 'button',
                        colClass: 'col-md-2 col-6',
                        buttonText: 'Export Excel',
                        buttonIcon: 'bi bi-download',
                        buttonClass: 'btn-primary',
                        action: this.openExportDialog,
                    },
                ],
            };
        },

        tableColumns() {
            return [
                {
                    key: 'No_Transaksi',
                    title: 'No. Transaksi',
                    icon: 'bi bi-hash',
                    showCount: true,
                    sticky: true,
                    cellClass: 'employee-cell',
                    align: 'left',
                    width: '280px',
                    minWidth: '280px',
                    maxWidth: '280px',
                },
                {
                    key: 'Tanggal',
                    title: 'Tanggal & Jam',
                    icon: 'bi bi-calendar',
                    type: 'date',
                    formatter: (value) => this.formatDate(value),
                    align: 'center',
                    width: '180px',
                    minWidth: '180px',
                },
                {
                    key: 'Pengaju',
                    title: 'Pengaju',
                    icon: 'bi bi-person',
                    formatter: (value, item) => item.Nama || '-',
                    align: 'center',
                    width: '96px',
                    minWidth: '96px',
                },
                {
                    key: 'Assignment',
                    title: 'Karyawan',
                    icon: 'bi bi-people',
                    formatter: (value, item) => `${this.getAssignedCount(item)} orang`,
                    align: 'center',
                    width: '190px',
                    minWidth: '190px',
                },
                // {
                //     key: "Effective_Work_Dates",
                //     title: "Tgl Kerja",
                //     icon: "bi bi-calendar3",
                //     formatter: (value) => value || "-",
                //     align: "center",
                //     width: "190px",
                //     minWidth: "190px",
                // },
                {
                    key: 'Flag_Selesai',
                    title: 'Status',
                    icon: 'bi bi-check2-circle',
                    formatter: (value) => this.getFlagSelesaiLabel(value),
                    align: 'center',
                    width: '120px',
                    minWidth: '120px',
                },
                {
                    key: 'actions',
                    title: 'Aksi',
                    icon: 'bi bi-three-dots',
                    showInMobile: false,
                    align: 'center',
                    width: '96px',
                },
            ];
        },

        mobileColumns() {
            return [
                {
                    key: 'No_Transaksi',
                    title: 'No. Transaksi',
                    icon: 'bi bi-hash',
                },
                {
                    key: 'Tanggal',
                    title: 'Tanggal & Jam',
                    icon: 'bi bi-calendar',
                },
                {
                    key: 'Pengaju',
                    title: 'Pengaju',
                    icon: 'bi bi-person',
                },
                {
                    key: 'Assignment',
                    title: 'Karyawan',
                    icon: 'bi bi-people',
                },
                {
                    key: 'Effective_Work_Dates',
                    title: 'Tgl Kerja',
                    icon: 'bi bi-calendar3',
                },
                {
                    key: 'Flag_Selesai',
                    title: 'Status',
                    icon: 'bi bi-check2-circle',
                },
            ];
        },

        detailColumns() {
            return [
                {
                    key: 'name',
                    title: 'Nama',
                    icon: 'bi bi-person',
                    sticky: true,
                },
                {
                    key: 'jenis',
                    title: 'Jenis',
                    icon: 'bi bi-tag',
                },
                {
                    key: 'tanggal',
                    title: 'Hari Kerja',
                    icon: 'bi bi-calendar',
                },
                {
                    key: 'tgl_lembur',
                    title: 'Tgl. Lembur',
                    icon: 'bi bi-calendar-event',
                },
                {
                    key: 'waktu',
                    title: 'Waktu',
                    icon: 'bi bi-clock',
                },
                {
                    key: 'durasi',
                    title: 'Durasi',
                    icon: 'bi bi-hourglass',
                },
                {
                    key: 'alasan',
                    title: 'Alasan',
                    icon: 'bi bi-chat-text',
                },
                {
                    key: 'status',
                    title: 'Status',
                    icon: 'bi bi-check-circle',
                },
            ];
        },

        detailMobileColumns() {
            return [
                {
                    key: 'name',
                    title: 'Nama',
                    icon: 'bi bi-person',
                },
                {
                    key: 'jenis',
                    title: 'Jenis',
                    icon: 'bi bi-tag',
                },
                {
                    key: 'tanggal',
                    title: 'Hari Kerja',
                    icon: 'bi bi-calendar',
                },
                {
                    key: 'tgl_lembur',
                    title: 'Tgl. Lembur',
                    icon: 'bi bi-calendar-event',
                },
                {
                    key: 'waktu',
                    title: 'Waktu',
                    icon: 'bi bi-clock',
                },
                {
                    key: 'alasan',
                    title: 'Alasan',
                    icon: 'bi bi-chat-text',
                },
            ];
        },

        filteredAssignEmployees() {
            let filtered = this.userChoose.map((emp) => {
                const isAvailable = this.isEmployeeAvailable(emp);
                const isSelected = this.isEmployeeSelected(emp.userId);

                return {
                    ...emp,
                    isAvailable,
                    isSelected,
                };
            });

            // Filter berdasarkan pencarian
            if (this.assignSearchQuery) {
                filtered = filtered.filter((employee) =>
                    employee.name.toLowerCase().includes(this.assignSearchQuery.toLowerCase()),
                );
            }

            // Urutkan: selected > available > disabled
            filtered.sort((a, b) => {
                if (a.isSelected && !b.isSelected) return -1;
                if (!a.isSelected && b.isSelected) return 1;
                if (a.isAvailable && !b.isAvailable) return -1;
                if (!a.isAvailable && b.isAvailable) return 1;
                return 0;
            });

            return filtered;
        },

        visibleAssignEmployees() {
            return this.filteredAssignEmployees.slice(0, this.assignEmployeeVisibleCount);
        },

        hasMoreAssignEmployees() {
            return (
                this.visibleAssignEmployees.length < this.filteredAssignEmployees.length ||
                this.assignEmployeeServerHasMore
            );
        },

        assignEmployeeTotalCount() {
            if (this.assignEmployeeServerTotal > 0) {
                return this.assignEmployeeServerTotal;
            }

            return this.filteredAssignEmployees.length;
        },

        canSelectAll() {
            if (this.assignEmployeeServerHasMore) {
                return false;
            }

            const availableEmployees = this.userChoose.filter(
                (emp) => this.isEmployeeAvailable(emp) && !this.isEmployeeSelected(emp.userId),
            );
            return availableEmployees.length > 0;
        },

        availableTanggal() {
            const sourceDates = Array.isArray(this.tanggal) && this.tanggal.length > 0 ? this.tanggal : null;

            if (sourceDates) {
                return this.filterTanggalByType(sourceDates);
            }

            const base = new Date();
            const result = [];
            for (let i = -1; i <= 2; i++) {
                const d = new Date(base);
                d.setDate(base.getDate() + i);
                result.push(this.formatDateToString(d));
            }
            return this.filterTanggalByType(result);
        },

        assignSelectionSummary() {
            if (!this.assignForm.selectedDate || !this.assignForm.selectedType) {
                return null;
            }

            const start = this.assignForm.startTime;
            const end = this.assignForm.endTime;
            const timeText =
                this.assignForm.selectedType === 'LEMBUR_AWAL'
                    ? start
                        ? `${this.formatTimeSingle(start)} s/d jam masuk`
                        : '-'
                    : start && end
                      ? `${this.formatTimeSingle(start)} - ${this.formatTimeSingle(end)}`
                      : start
                        ? this.formatTimeSingle(start)
                        : '-';

            return {
                date: this.formatDate(this.assignForm.selectedDate),
                time: timeText,
                type: this.getTypeLabel(this.assignForm.selectedType),
            };
        },

        requiresRangeTime() {
            const rangeTypes = ['LEMBUR_HK', 'LEMBUR_LIBUR'];
            return rangeTypes.includes(this.assignForm.selectedType);
        },

        assignTimeRangeModel: {
            get() {
                if (!this.assignForm.startTime || !this.assignForm.endTime) {
                    return [];
                }
                return [this.assignForm.startTime, this.assignForm.endTime];
            },
            set(value) {
                this.assignForm.startTime = value?.[0] || null;
                this.assignForm.endTime = value?.[1] || null;
            },
        },

        requiresRangeTimeForExpand() {
            const rangeTypes = ['LEMBUR_HK', 'LEMBUR_LIBUR'];
            const currentType = this.selectedExpandEmployee?.type || this.assignForm.selectedType;
            return rangeTypes.includes(currentType);
        },

        expandTimeRangeModel: {
            get() {
                if (!this.selectedExpandEmployee) return [];
                if (!this.selectedExpandEmployee.startTime || !this.selectedExpandEmployee.endTime) {
                    return [];
                }
                return [this.selectedExpandEmployee.startTime, this.selectedExpandEmployee.endTime];
            },
            set(value) {
                if (!this.selectedExpandEmployee) return;
                this.selectedExpandEmployee.startTime = value?.[0] || null;
                this.selectedExpandEmployee.endTime = value?.[1] || null;
            },
        },

        expandSingleTimeModel: {
            get() {
                if (!this.selectedExpandEmployee) return null;
                return this.selectedExpandEmployee.endTime || this.selectedExpandEmployee.startTime || null;
            },
            set(value) {
                if (!this.selectedExpandEmployee) return;
                this.selectedExpandEmployee.startTime = value || null;
                if ((this.selectedExpandEmployee.type || this.assignForm.selectedType) === 'LEMBUR_AWAL') {
                    this.selectedExpandEmployee.endTime = null;
                } else {
                    this.selectedExpandEmployee.endTime = value || null;
                }
            },
        },

        canProceedToNextStep() {
            if (this.assignStep === 1) {
                return this.assignForm.selectedDate;
            }
            if (this.assignStep === 2) {
                const isReasonValid = this.assignForm.reason && this.assignForm.reason.trim().length >= 50;
                if (this.requiresRangeTime) {
                    return this.assignForm.startTime && this.assignForm.endTime && isReasonValid;
                }
                return this.assignForm.startTime && isReasonValid;
            }
            if (this.assignStep === 3) {
                return this.assignForm.selectedEmployees.length > 0;
            }
            if (this.assignStep === 4) {
                return this.assignForm.selectedEmployees.length > 0;
            }
            return true;
        },

        policyHintItems() {
            const cfg = this.policyConfig || {};
            const global = [`Durasi lembur minimal ${cfg.minimalDurasiLembur} jam.`];
            const windowHint = !this.isAdmin
                ? `Window pengajuan maksimal +${cfg.batasJamRequestLembur} jam dari jam pulang shift.`
                : null;

            if (this.assignForm.selectedType === 'LEMBUR_AWAL') {
                return [
                    ...global,
                    'Jam akhir lembur awal otomatis mengikuti jam masuk shift.',
                    `Jam mulai lembur awal maksimal ${cfg.batasLemburAwalDariJamMasuk} jam sebelum jam masuk shift.`,
                    ...(windowHint ? [windowHint] : []),
                ];
            }

            if (this.assignForm.selectedType === 'LEMBUR_HK') {
                return [
                    ...global,
                    /*`Durasi lembur maksimal ${cfg.batasDurasiLembur} jam.`,*/
                    `Jam akhir lembur minimal berjarak ${cfg.batasKedekatanAkhirDenganJamKerja} jam dari jam masuk shift berikutnya.`,
                    ...(windowHint ? [windowHint] : []),
                ];
            }

            if (this.assignForm.selectedType === 'LEMBUR_LIBUR') {
                return [
                    ...global,
                    /*`Durasi lembur libur maksimal ${cfg.batasDurasiLemburLibur} jam.`,*/
                    'Tidak terikat jam kerja shift.',
                ];
            }

            return global;
        },
    },
    watch: {
        // CATATAN: watcher tableFilters.search & tableFilters.tanggal_range DIHAPUS.
        // Perubahan filter sudah didorong satu jalur lewat handleFiltersUpdate() (dipanggil
        // FilterPanel yang SUDAH men-debounce 500ms) -> getData(). Watcher lama memicu
        // debouncedSearch() KEDUA sehingga 1 ketikan = 2x getData. Kini single-fire.
        'assignForm.startTime'(newVal, oldVal) {
            if (newVal !== oldVal) {
                this.syncSelectionContext(false);
            }
        },
        'assignForm.endTime'(newVal, oldVal) {
            if (newVal !== oldVal) {
                this.syncSelectionContext(false);
            }
        },
        assignSearchInput() {
            if (this.assignSearchDebounceTimer) {
                clearTimeout(this.assignSearchDebounceTimer);
            }

            this.assignSearchDebounceTimer = setTimeout(() => {
                this.applyAssignSearchDebounced();
            }, 800);
        },
    },
    methods: {
        async reconcileSelectedEmployeesOnLoad(showToast = true) {
            if (!(this.showAssignModal && this.assignStep === 3) || this.assignForm.selectedEmployees.length === 0) {
                return;
            }

            try {
                this.ensureAssignTimeShape();

                const response = await axios.post(this.getValidateDraftEndpoint(), {
                    params: {
                        Kode_Perusahaan: '001',
                        dataUsers: this.assignForm.selectedEmployees,
                        date: this.assignForm.selectedDate,
                        type: this.assignForm.selectedType,
                        startTime: this.assignForm.startTime,
                        endTime: this.assignForm.endTime,
                    },
                });

                const result = response?.data?.result || {};
                const conflicts = Array.isArray(result.conflicts) ? result.conflicts : [];

                if (conflicts.length === 0) {
                    return;
                }

                const conflictMap = new Map(
                    conflicts.map((c) => [String(c.employee_id), c.reason || 'Tidak tersedia']),
                );

                const beforeCount = this.assignForm.selectedEmployees.length;
                this.assignForm.selectedEmployees = this.assignForm.selectedEmployees.filter(
                    (emp) => !conflictMap.has(String(emp.id)),
                );

                this.userChoose = (this.userChoose || []).map((emp) => {
                    const reason = conflictMap.get(String(emp.userId));
                    if (!reason) return emp;
                    return {
                        ...emp,
                        isActive: false,
                        inactiveReason: reason,
                    };
                });

                const removed = beforeCount - this.assignForm.selectedEmployees.length;
                if (removed > 0) {
                    this.lastSelectionContextKey = this.buildSelectionContextKey();
                    if (showToast) {
                        ElMessage.warning(
                            `${removed} pilihan karyawan dibersihkan karena status terbaru sudah tidak valid`,
                        );
                    }
                }
            } catch (error) {
                console.error('Error reconciling selected employees on load:', error);
            }
        },

        applyAssignSearchDebounced() {
            this.assignSearchQuery = (this.assignSearchInput || '').trim();
            this.resetAssignEmployeeLazyState();

            if (!(this.showAssignModal && this.assignStep === 3)) {
                return;
            }

            this.loadAvailableEmployees({
                reset: true,
                // Waktu server sudah disinkronkan saat modal masuk step 3.
                // Tidak perlu round-trip /getTime ulang tiap pencarian.
                syncServerTime: false,
                reconcileSelection: false,
            });
        },

        resetAssignEmployeeLazyState() {
            this.assignEmployeeVisibleCount = this.assignEmployeeBatchSize;
            this.assignEmployeeChunkLoading = false;

            this.$nextTick(() => {
                this.maybeAutoLoadAssignEmployees();
            });
        },

        async loadMoreAssignEmployees() {
            if (this.assignEmployeeChunkLoading || !this.hasMoreAssignEmployees) {
                return;
            }

            const hasLocalMore = this.visibleAssignEmployees.length < this.filteredAssignEmployees.length;

            if (hasLocalMore) {
                this.assignEmployeeChunkLoading = true;
                this.assignEmployeeVisibleCount = Math.min(
                    this.assignEmployeeVisibleCount + this.assignEmployeeBatchSize,
                    this.filteredAssignEmployees.length,
                );

                this.$nextTick(() => {
                    this.assignEmployeeChunkLoading = false;
                    this.maybeAutoLoadAssignEmployees();
                });
                return;
            }

            if (!this.assignEmployeeServerHasMore) {
                return;
            }

            await this.loadAvailableEmployees({ reset: false });
            this.assignEmployeeVisibleCount = Math.min(
                this.assignEmployeeVisibleCount + this.assignEmployeeBatchSize,
                this.filteredAssignEmployees.length,
            );

            this.$nextTick(() => {
                this.maybeAutoLoadAssignEmployees();
            });
        },

        handleAssignEmployeeScroll(event) {
            if (!this.hasMoreAssignEmployees || this.loading) {
                return;
            }

            const target = event?.target;
            if (!target) {
                return;
            }

            const distanceToBottom = target.scrollHeight - target.scrollTop - target.clientHeight;

            if (distanceToBottom <= this.assignEmployeeScrollThreshold) {
                this.loadMoreAssignEmployees();
            }
        },

        maybeAutoLoadAssignEmployees() {
            if (!this.hasMoreAssignEmployees || this.loading) {
                return;
            }

            const listElement = this.$refs.assignEmployeeList;
            if (!listElement) {
                return;
            }

            const distanceToBottom = listElement.scrollHeight - listElement.scrollTop - listElement.clientHeight;

            if (distanceToBottom <= this.assignEmployeeScrollThreshold) {
                this.loadMoreAssignEmployees();
            }
        },

        // batas absen pulang
        calculateDeadlineTime(endTime) {
            const [hours, minutes] = endTime.split(':').map(Number);
            const deadline = new Date();
            deadline.setHours(hours + 4, minutes, 0, 0); // Tambah 4 jam
            return this.formatTimeSingle(deadline.toTimeString().split(' ')[0]);
        },
        // Edit Expand Employee Modal Methods
        openExpandModal(employee) {
            this.purgeStaleSelectedEmployeesByContext(false);
            this.selectedExpandEmployee = this.assignForm.selectedEmployees.find(
                (emp) => emp.stableId === employee.stableId,
            );
            if (!this.selectedExpandEmployee) {
                ElMessage.warning('Detail karyawan tidak valid karena konteks pengajuan berubah. Silakan pilih ulang.');
                return;
            }
            // Simpan snapshot data asli sebelum diedit, agar bisa di-restore saat Batal
            this._expandEmployeeSnapshot = JSON.parse(JSON.stringify(this.selectedExpandEmployee));
            this.showExpandModal = true;
        },
        getExpandEmployeeDisplayName() {
            const emp = this.selectedExpandEmployee || {};
            return emp.nama || emp.name || '-';
        },
        closeExpandModal() {
            // Restore data asli dari snapshot saat Batal (bukan Simpan)
            if (this._expandEmployeeSnapshot && this.selectedExpandEmployee) {
                const index = this.assignForm.selectedEmployees.findIndex(
                    (emp) => emp.stableId === this.selectedExpandEmployee.stableId,
                );
                if (index !== -1) {
                    this.assignForm.selectedEmployees.splice(index, 1, this._expandEmployeeSnapshot);
                }
            }
            this._expandEmployeeSnapshot = null;
            this.showExpandModal = false;
            this.selectedExpandEmployee = null;
        },
        async saveExpandEmployee() {
            if (this.selectedExpandEmployee) {
                this.ensureAssignTimeShape();

                const expandReasonLength = (this.selectedExpandEmployee.reason || '').trim().length;
                if (expandReasonLength > 0 && expandReasonLength < 50) {
                    ElMessage.warning(
                        `Alasan lembur khusus minimal 50 karakter (saat ini ${expandReasonLength} karakter, kurang ${50 - expandReasonLength} karakter).`,
                    );
                    return;
                }

                const draftPayload = {
                    ...this.selectedExpandEmployee,
                    type: this.selectedExpandEmployee.type || this.assignForm.selectedType,
                    tanggal: this.selectedExpandEmployee.tanggal || this.assignForm.selectedDate,
                    effectiveWorkDate: this.selectedExpandEmployee.effectiveWorkDate || this.assignForm.selectedDate,
                    startTime: this.selectedExpandEmployee.startTime || this.assignForm.startTime,
                    endTime: this.selectedExpandEmployee.endTime || this.assignForm.endTime,
                    reason: this.selectedExpandEmployee.reason || this.assignForm.reason,
                };

                const singleValidation = await this.validateDraftSelection([draftPayload]);
                if (!singleValidation.ok) {
                    ElMessage.warning(singleValidation.message || 'Perubahan jam detail tidak valid.');
                    return;
                }

                const index = this.assignForm.selectedEmployees.findIndex(
                    (emp) => emp.stableId === this.selectedExpandEmployee.stableId,
                );
                if (index !== -1) {
                    this.selectedExpandEmployee.selectionContextKey = this.buildSelectionContextKey();
                    this.assignForm.selectedEmployees.splice(index, 1, this.selectedExpandEmployee);
                }
            }
            // Hapus snapshot agar closeExpandModal tidak me-restore data lama
            this._expandEmployeeSnapshot = null;
            this.closeExpandModal();
        },

        handleSingleTimeChange(value) {
            this.assignForm.startTime = value || null;
            if (this.assignForm.selectedType === 'LEMBUR_AWAL') {
                this.assignForm.endTime = null;
            } else {
                this.assignForm.endTime = value || null;
            }
        },

        buildSelectionContextKey() {
            const type = this.assignForm.selectedType || '-';
            const date = this.assignForm.selectedDate || '-';
            const start = this.assignForm.startTime || '-';
            const end = this.assignForm.endTime || '-';
            return `${type}|${date}|${start}|${end}`;
        },

        clearSelectionForContextChange(showToast = true) {
            if (this.assignForm.selectedEmployees.length === 0) {
                this.lastSelectionContextKey = this.buildSelectionContextKey();
                return;
            }

            this.assignForm.selectedEmployees = [];
            this.selectedExpandEmployee = null;
            this.lastSelectionContextKey = this.buildSelectionContextKey();

            if (showToast) {
                ElMessage.info('Pilihan karyawan direset karena tanggal/tipe/jam lembur berubah');
            }
        },

        syncSelectionContext(showToast = true) {
            const currentKey = this.buildSelectionContextKey();

            if (!this.lastSelectionContextKey) {
                this.lastSelectionContextKey = currentKey;
                return;
            }

            if (this.lastSelectionContextKey !== currentKey) {
                this.clearSelectionForContextChange(showToast);
            }
        },

        purgeStaleSelectedEmployeesByContext(showToast = true) {
            const currentKey = this.buildSelectionContextKey();
            const before = this.assignForm.selectedEmployees.length;
            if (before === 0) return;

            this.assignForm.selectedEmployees = this.assignForm.selectedEmployees.filter((emp) => {
                const key = emp.selectionContextKey || null;
                return key === currentKey;
            });

            const removed = before - this.assignForm.selectedEmployees.length;
            if (removed > 0) {
                this.selectedExpandEmployee = null;
                if (showToast) {
                    ElMessage.info(`${removed} pilihan karyawan dibersihkan karena konteks pengajuan berubah`);
                }
            }
        },

        setAssignDate(day) {
            const prev = this.assignForm.selectedDate;
            this.assignForm.selectedDate = day;

            if (prev !== day) {
                this.syncSelectionContext(false);
            }
        },

        filterTanggalByType(dates) {
            const normalized = (dates || []).filter(Boolean).map((d) => this.formatDateToString(new Date(d)));

            const today = this.formatDateToString(new Date());
            if (this.assignForm.selectedType === 'LEMBUR_LIBUR') {
                return normalized.filter((d) => d >= today);
            }

            // LEMBUR_HK & LEMBUR_AWAL boleh menampilkan H-1 untuk kasus lintas hari.
            return normalized;
        },

        ensureAssignTimeShape() {
            if (!this.requiresRangeTime) {
                const single =
                    this.assignForm.startTime || this.assignForm.singleTime || this.assignForm.endTime || null;
                this.assignForm.singleTime = single;
                this.assignForm.startTime = single;
                if (this.assignForm.selectedType === 'LEMBUR_AWAL') {
                    this.assignForm.endTime = null;
                } else {
                    this.assignForm.endTime = single;
                }
            }
        },

        getReviewTimeLabel() {
            if (this.assignForm.selectedType === 'LEMBUR_AWAL') {
                return `${this.formatTimeSingle(this.assignForm.startTime)} s/d jam masuk shift`;
            }
            return `${this.formatTimeSingle(this.assignForm.startTime)} - ${this.formatTimeSingle(this.assignForm.endTime)}`;
        },

        handleTypeSelect(type) {
            const prev = this.assignForm.selectedType;
            this.assignForm.selectedType = type;
            this.ensureAssignTimeShape();

            if (
                !this.isAdmin &&
                this.assignForm.selectedDate &&
                !this.availableTanggal.includes(this.assignForm.selectedDate)
            ) {
                this.assignForm.selectedDate = null;
            }

            if (prev !== type) {
                this.syncSelectionContext(false);
            }
        },

        getTypeLabel(type) {
            const typeMap = {
                LEMBUR_HK: 'Lembur Hari Kerja',
                LEMBUR_AWAL: 'Lembur Awal',
                LEMBUR_LIBUR: 'Lembur Minggu/Libur',
            };
            return typeMap[type] || 'Lembur';
        },
        // Formatting Methods
        formatDatePanjang(tanggal) {
            return new Date(tanggal).toLocaleDateString('id-ID', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });
        },

        formatDatePanjangTanpaHari(tanggal) {
            if (!tanggal) return '-';
            return new Date(tanggal).toLocaleDateString('id-ID', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });
        },

        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            });
        },

        formatDateString(date) {
            if (!date) return '';
            return new Date(date).toLocaleDateString('id-ID', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            });
        },

        formatDateTime(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return '-';
            return date.toLocaleString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },

        formatTimeSingle(timeString) {
            if (!timeString) return '';
            return timeString.split(':').slice(0, 2).join(':');
        },

        formatTime(start_time, end_time) {
            const start = new Date(start_time);
            const end = new Date(end_time);

            const startTime = start.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
            });
            const endTime = end.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
            });

            return `${startTime} - ${endTime}`;
        },
        formatTimeRangeWithDate(start_time, end_time) {
            const start = new Date(start_time);
            const end = new Date(end_time);
            if (isNaN(start.getTime()) || isNaN(end.getTime())) return '-';

            const startDate = start.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
            });
            const endDate = end.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
            });

            const startTime = start.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
            });
            const endTime = end.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
            });

            if (startDate === endDate) {
                return `${startTime} - ${endTime}`;
            }

            return `${startDate} ${startTime} - ${endDate} ${endTime}`;
        },

        formatDuration(start_time, end_time) {
            const start = new Date(start_time);
            const end = new Date(end_time);
            const duration = end - start;

            const hours = Math.floor(duration / 1000 / 60 / 60);
            const minutes = Math.floor((duration % (1000 * 60 * 60)) / (1000 * 60));

            return `${hours} jam ${minutes} menit`;
        },

        formatShiftTime(jamMasuk, jamKeluar) {
            return `${this.formatTimeSingle(jamMasuk)} - ${this.formatTimeSingle(jamKeluar)}`;
        },

        formatDateShort(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
            });
        },

        getDayName(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('id-ID', { weekday: 'short' });
        },

        parseTimeToMinuteOfDay(timeString) {
            if (!timeString || typeof timeString !== 'string') return null;
            const [h = '0', m = '0'] = timeString.split(':');
            const hour = Number(h);
            const minute = Number(m);
            if (Number.isNaN(hour) || Number.isNaN(minute) || hour < 0 || hour > 23 || minute < 0 || minute > 59) {
                return null;
            }
            return hour * 60 + minute;
        },

        buildDateByOffset(baseDateString, offsetDay = 0) {
            const d = new Date(baseDateString);
            d.setDate(d.getDate() + offsetDay);
            return d;
        },

        formatReviewOvertimeDateTime(employee) {
            const actual = this.resolveActualOvertimeRange(employee);
            if (!actual) {
                const start = employee?.startTime || this.assignForm.startTime;
                const end = employee?.endTime || this.assignForm.endTime;
                return `${this.formatTimeSingle(start)} - ${this.formatTimeSingle(end)}`;
            }

            const startDateText = this.formatDatePanjangTanpaHari(actual.startDate);
            const endDateText = this.formatDatePanjangTanpaHari(actual.endDate);
            const startTimeText = this.formatTimeSingle(actual.startTime);
            const endTimeText = this.formatTimeSingle(actual.endTime);

            if (startDateText === endDateText) {
                return `${startDateText} ${startTimeText} - ${endTimeText}`;
            }

            return `${startDateText} ${startTimeText} - ${endDateText} ${endTimeText}`;
        },

        resolveActualOvertimeRange(employee) {
            const workDate = employee?.effectiveWorkDate || employee?.tanggal || this.assignForm.selectedDate;
            const startTime = employee?.startTime || this.assignForm.startTime;
            const type = employee?.type || this.assignForm.selectedType;
            const endTime =
                type === 'LEMBUR_AWAL'
                    ? employee?.jamMasuk || employee?.snapshot_jam_masuk || employee?.endTime || this.assignForm.endTime
                    : employee?.endTime || this.assignForm.endTime;

            if (!workDate || !startTime || !endTime) return null;

            const startMin = this.parseTimeToMinuteOfDay(startTime);
            const endMin = this.parseTimeToMinuteOfDay(endTime);
            let offsets = { startOffset: 0, endOffset: 0 };
            if (type === 'LEMBUR_AWAL') {
                offsets = this.resolveOffsetsForLemburAwal(employee, startTime);
            } else if (type === 'LEMBUR_HK') {
                offsets = this.resolveOffsetsForLemburHariKerja(employee);
            }

            if (startMin !== null && endMin !== null && endMin < startMin) {
                offsets.endOffset = offsets.startOffset + 1;
            }

            return {
                startDate: this.buildDateByOffset(workDate, offsets.startOffset),
                endDate: this.buildDateByOffset(workDate, offsets.endOffset),
                startTime,
                endTime,
            };
        },

        resolveOffsetsForLemburAwal(employee, startTime) {
            const shiftStartRaw = employee?.jamMasuk || employee?.snapshot_jam_masuk || null;
            const shiftStartMin = this.parseTimeToMinuteOfDay(shiftStartRaw);
            const startMin = this.parseTimeToMinuteOfDay(startTime);

            if (shiftStartMin === null || startMin === null) {
                return { startOffset: 0, endOffset: 0 };
            }

            return {
                startOffset: startMin >= shiftStartMin ? -1 : 0,
                endOffset: 0,
            };
        },

        resolveOffsetsForLemburHariKerja(employee) {
            const shiftStartRaw = employee?.jamMasuk || employee?.snapshot_jam_masuk || null;
            const shiftEndRaw = employee?.jamKeluar || employee?.snapshot_jam_keluar || null;
            const shiftStartMin = this.parseTimeToMinuteOfDay(shiftStartRaw);
            const shiftEndMin = this.parseTimeToMinuteOfDay(shiftEndRaw);

            if (shiftStartMin === null || shiftEndMin === null) {
                return { startOffset: 0, endOffset: 0 };
            }

            const overnightShift = shiftEndMin <= shiftStartMin;
            if (!overnightShift) {
                return { startOffset: 0, endOffset: 0 };
            }

            return { startOffset: 1, endOffset: 1 };
        },

        getSelectedEmployeeById(stableId) {
            return this.assignForm.selectedEmployees.find((emp) => emp.stableId === stableId);
        },

        formatEmployeeActualOvertimeCompact(userId) {
            const emp = this.getSelectedEmployeeById(userId);
            if (!emp) return '-';
            const actual = this.resolveActualOvertimeRange(emp);
            if (!actual) return '-';

            const startDateText = this.formatDate(actual.startDate);
            const endDateText = this.formatDate(actual.endDate);
            const startTimeText = this.formatTimeSingle(actual.startTime);
            const endTimeText = this.formatTimeSingle(actual.endTime);

            if (startDateText === endDateText) {
                return `${startDateText} ${startTimeText} - ${endTimeText}`;
            }
            return `${startDateText} ${startTimeText} - ${endDateText} ${endTimeText}`;
        },

        getActualDurationMinutes(employee) {
            const actual = this.resolveActualOvertimeRange(employee);
            if (!actual) return null;

            const startDate = this.formatDateToString(actual.startDate);
            const endDate = this.formatDateToString(actual.endDate);
            if (!startDate || !endDate) return null;

            // Buang detik agar durasi dihitung murni dari jam:menit
            // (mencegah 12:00:01-15:00:00 = 179 menit → floor 2 jam, padahal seharusnya 3 jam)
            const stripSeconds = (t) => (t || '').split(':').slice(0, 2).join(':');
            const start = new Date(`${startDate}T${stripSeconds(actual.startTime)}:00`);
            const end = new Date(`${endDate}T${stripSeconds(actual.endTime)}:00`);
            const diff = Math.floor((end.getTime() - start.getTime()) / 60000);
            return Number.isFinite(diff) && diff > 0 ? diff : null;
        },

        formatDurationFromMinutes(totalMinutes) {
            if (!totalMinutes || totalMinutes <= 0) return '-';
            const hours = Math.floor(totalMinutes / 60);
            return `${hours} jam`;
        },

        formatEmployeeFinalDurationCompact(userId) {
            const emp = this.getSelectedEmployeeById(userId);
            if (!emp) return '-';
            return this.formatDurationFromMinutes(this.getActualDurationMinutes(emp));
        },

        formatEmployeeFinalDuration(employee) {
            return this.formatDurationFromMinutes(this.getActualDurationMinutes(employee));
        },

        getEmployeeShiftWindow(employee) {
            const type = employee?.type || this.selectedExpandEmployee?.type || this.assignForm.selectedType;
            if (type === 'LEMBUR_LIBUR') {
                return null;
            }

            const startRaw = employee?.jamMasuk || employee?.Jam_Masuk || employee?.snapshot_jam_masuk || null;
            const endRaw = employee?.jamKeluar || employee?.Jam_Keluar || employee?.snapshot_jam_keluar || null;

            const start = this.parseTimeToMinuteOfDay(startRaw);
            const end = this.parseTimeToMinuteOfDay(endRaw);

            if (start === null || end === null) {
                return null;
            }

            return {
                start,
                end,
                overnight: end <= start,
            };
        },

        isMinuteInsideShiftWindow(minuteOfDay, shiftWindow) {
            if (!shiftWindow) return false;
            if (!shiftWindow.overnight) {
                return minuteOfDay >= shiftWindow.start && minuteOfDay <= shiftWindow.end;
            }
            return minuteOfDay >= shiftWindow.start || minuteOfDay <= shiftWindow.end;
        },

        getDisabledHoursForEmployee(employee) {
            const shiftWindow = this.getEmployeeShiftWindow(employee);
            if (!shiftWindow) return [];

            const disabledHours = [];
            for (let hour = 0; hour <= 23; hour++) {
                let allMinuteInShift = true;
                for (let minute = 0; minute <= 59; minute++) {
                    const minuteOfDay = hour * 60 + minute;
                    if (!this.isMinuteInsideShiftWindow(minuteOfDay, shiftWindow)) {
                        allMinuteInShift = false;
                        break;
                    }
                }
                if (allMinuteInShift) {
                    disabledHours.push(hour);
                }
            }
            return disabledHours;
        },

        getDisabledMinutesForEmployee(employee, selectedHour) {
            const shiftWindow = this.getEmployeeShiftWindow(employee);
            if (!shiftWindow || typeof selectedHour !== 'number') return [];

            const disabledMinutes = [];
            for (let minute = 0; minute <= 59; minute++) {
                const minuteOfDay = selectedHour * 60 + minute;
                if (this.isMinuteInsideShiftWindow(minuteOfDay, shiftWindow)) {
                    disabledMinutes.push(minute);
                }
            }
            return disabledMinutes;
        },

        getEffectiveWorkDateForDetail(item) {
            return (
                item?.effective_work_date ||
                item?.effectiveWorkDate ||
                item?.Tanggal_Lembur ||
                item?.tanggal_lembur ||
                item?.start_time
            );
        },

        isToday(dateString) {
            const today = new Date().toISOString().split('T')[0];
            return dateString === today;
        },

        isStatusHariOff(employee) {
            const status = employee?.StatusHari || employee?.statusHari || employee?.snapshot_status_hari || '';
            return String(status).toUpperCase().startsWith('OFF');
        },

        getInitials(name) {
            if (!name) return '??';
            return name
                .split(' ')
                .map((word) => word[0])
                .join('')
                .substring(0, 2)
                .toUpperCase();
        },

        truncateText(text, length) {
            if (!text) return '';
            return text.length > length ? text.substring(0, length) + '...' : text;
        },
        getAssignedCount(item) {
            const raw = (item?.Assignment || '').toString().trim();
            if (!raw) return 0;
            return raw
                .split(',')
                .map((v) => v.trim())
                .filter(Boolean).length;
        },
        getAssignmentPreview(assignmentRaw) {
            const names = (assignmentRaw || '')
                .toString()
                .split(',')
                .map((v) => v.trim())
                .filter(Boolean);

            if (names.length === 0) return '-';
            if (names.length <= 2) return names.join(', ');

            return `${names.slice(0, 2).join(', ')} +${names.length - 2}`;
        },
        getFlagSelesaiLabel(flagSelesai) {
            return String(flagSelesai || '').toUpperCase() === 'Y' ? 'Selesai' : 'Proses';
        },
        getJenisLemburLabel(jenis) {
            const key = String(jenis || '').toUpperCase();
            if (key === 'LEMBUR_HK' || key === 'LEMBUR') {
                return 'Lembur Hari Kerja';
            }
            if (key === 'LEMBUR_AWAL') {
                return 'Lembur Awal';
            }
            if (key === 'LEMBUR_LIBUR') {
                return 'Lembur Minggu/Libur';
            }
            return jenis || '-';
        },

        // API Methods
        async getData(url = null) {
            try {
                this.loading = true;
                const params = new URLSearchParams();

                // Min 2 karakter agar tidak memicu query search untuk 1 karakter (kurang
                // selektif). <2 char -> tanpa filter search (list normal, cepat via indeks).
                const searchTerm = (this.tableFilters.search || '').trim();
                if (searchTerm.length >= 2) {
                    params.append('search', searchTerm);
                }
                if (Array.isArray(this.tableFilters.tanggal_range) && this.tableFilters.tanggal_range.length === 2) {
                    const [tanggalDari, tanggalSampai] = this.tableFilters.tanggal_range;
                    if (tanggalDari) params.append('tanggalDari', tanggalDari);
                    if (tanggalSampai) params.append('tanggalSampai', tanggalSampai);
                }
                if (this.tableFilters.jenis) {
                    params.append('jenisFilter', this.tableFilters.jenis);
                }

                const endpoint = url || `${this.getHistoryEndpoint()}?${params.toString()}`;
                const response = await axios.get(endpoint);

                if (response.status === 200) {
                    const pager = response?.data?.result || {};
                    this.data = Array.isArray(pager.data) ? pager.data : [];
                    this.prevLink = pager.prev_page_url || null;
                    this.nextLink = pager.next_page_url || null;
                    this.totalData = Number(pager.total || 0);
                    this.currentPage = Number(pager.current_page || 1);
                    this.totalPages = Number(pager.last_page || 0);

                    const ringkasan = response?.data?.ringkasan || {};
                    this.ringkasan = {
                        total: Number(ringkasan.total || 0),
                        hari_ini: Number(ringkasan.hari_ini || 0),
                        pending: Number(ringkasan.pending || 0),
                    };
                }
            } catch (error) {
                console.error('Error fetching data:', error);
                ElMessage.error('Gagal memuat data lembur');
                this.data = [];
                this.totalData = 0;
                this.totalPages = 0;
                this.ringkasan = { total: 0, hari_ini: 0, pending: 0 };
            } finally {
                this.loading = false;
            }
        },

        debouncedSearch: debounce(function () {
            this.getData();
        }, 500),

        async getTimeServer() {
            try {
                const res = await axios.get('/getTime');
                if (res.status === 200) {
                    this.serverTime = new Date(
                        new Date(res.data.time).toLocaleString('en-US', {
                            timeZone: 'Asia/Jakarta',
                        }),
                    );
                }
            } catch (error) {
                console.error('Error fetching server time:', error);
                this.serverTime = new Date();
            }
        },

        // Filter & Search Methods
        handleTabChange(tabId) {
            this.activeTab = tabId;
        },

        handleFiltersUpdate(updatedFilters) {
            this.tableFilters = { ...this.tableFilters, ...updatedFilters };
            this.currentPage = 1;
            this.getData();
        },

        handleHcisFilterChange({ key, value }) {
            this.tableFilters[key] = value;
            this.currentPage = 1;
            this.getData();
        },

        handleSearch(searchQuery) {
            this.tableFilters.search = searchQuery;
            this.currentPage = 1;
            this.getData();
        },

        handlePageChange(page) {
            if (typeof page === 'string') {
                this.getData(page);
                return;
            }

            if (typeof page !== 'number' || Number.isNaN(page)) return;

            if (page > this.currentPage && this.nextLink) {
                this.getData(this.nextLink);
            } else if (page < this.currentPage && this.prevLink) {
                this.getData(this.prevLink);
            }
        },
        refreshRiwayat() {
            this.getData();
        },

        clearFilters() {
            this.tableFilters = {
                search: '',
                tanggal_range: [],
                jenis: '',
            };
            this.currentPage = 1;
            this.getData();
        },

        // Utility Methods
        disabledButton2(date) {
            if (!date) return false;
            const tanggalSekarang = this.serverTime ? new Date(this.serverTime) : new Date();
            const tanggalCek = new Date(date);

            if (Number.isNaN(tanggalCek.getTime())) return false;

            tanggalCek.setHours(tanggalCek.getHours() + 5);

            return tanggalSekarang.getTime() <= tanggalCek.getTime();
        },

        getOvertimeDeleteDeadline(item) {
            const endTime =
                item?.end_time || item?.endTime || item?.Tanggal_Assign || item?.Tanggal_Lembur_Sampai || null;

            if (!endTime) return null;

            const deadline = new Date(endTime);
            if (Number.isNaN(deadline.getTime())) return null;

            deadline.setHours(deadline.getHours() + 7);
            return deadline;
        },

        canDeleteTransaction(item) {
            if (!item) return false;

            // Transaksi yang sudah dibatalkan tak bisa dibatalkan lagi.
            if (item.Status === 'Y') return false;

            // Admin boleh membatalkan kapan pun, tanpa batas waktu.
            if (this.isAdmin) return true;

            // Leader: satu-satunya syarat = belum lewat +7 jam dari estimasi akhir lembur
            // (Tanggal_Assign = MAX Tanggal_Lembur_Sampai antar detail).
            const latestEndTime = item?.Tanggal_Assign || item?.Effective_Work_Dates || item?.Tanggal || null;
            if (!latestEndTime) return false;

            const deadline = new Date(latestEndTime);
            if (Number.isNaN(deadline.getTime())) return false;

            deadline.setHours(deadline.getHours() + 7);
            const now = this.serverTime ? new Date(this.serverTime) : new Date();

            return now.getTime() <= deadline.getTime();
        },

        // Badge Type Methods
        getDepartmentBadgeType(department) {
            const colors = ['primary'];
            const index = Math.abs(this.hashCode(department)) % colors.length;
            return colors[index];
        },

        getCheckoutBadgeType(status) {
            if (status === 'SUDAH') return 'success';
            if (status === 'BELUM') return 'warning';
            return 'secondary';
        },
        getTransactionStatusLabel(item) {
            const flagSelesai = String(item?.Flag_Selesai || '')
                .toUpperCase()
                .trim();
            if (flagSelesai === 'Y') return 'SELESAI REVIEW';

            const raw = (item?.Dashboard_Status || item?.status || item?.Status || 'MENUNGGU REVIEW')
                .toString()
                .toUpperCase();

            if (raw === 'APPROVED') return 'DISETUJUI';
            if (raw === 'REJECTED') return 'DITOLAK';
            if (raw === 'VOID' || raw === 'Y') return 'DIBATALKAN';
            if (raw === 'PENDING') return 'MENUNGGU REVIEW';
            return raw;
        },
        getTransactionStatusBadgeClass(item) {
            const label = this.getTransactionStatusLabel(item);
            if (label === 'SELESAI REVIEW') return 'status-approved';
            if (label === 'DISETUJUI') return 'status-approved';
            if (label === 'DITOLAK') return 'status-rejected';
            if (label === 'DIBATALKAN') return 'status-cancelled';
            return 'status-pending';
        },

        getDetailReviewStatus(item) {
            const done =
                String(item?.Flag_Selesai || '')
                    .toUpperCase()
                    .trim() === 'Y';
            return {
                label: done ? 'SELESAI REVIEW' : 'MENUNGGU REVIEW',
                chipClass: done ? 'is-success' : 'is-warning',
            };
        },

        isTruthyFlag(value) {
            return ['Y', 'TRUE', '1'].includes(
                String(value || '')
                    .toUpperCase()
                    .trim(),
            );
        },

        getDetailAttendanceStatus(item) {
            const jenis = String(item?.jenis_lembur || '')
                .toUpperCase()
                .trim();
            const hasCheckIn = this.isTruthyFlag(item?.Has_CheckIn);
            const hasCheckOut = this.isTruthyFlag(item?.Has_CheckOut);

            const requireCheckIn = jenis === 'LEMBUR_LIBUR';
            const requireCheckOut =
                jenis === 'LEMBUR' || jenis === 'LEMBUR_HK' || jenis === 'LEMBUR_AWAL' || jenis === 'LEMBUR_LIBUR';

            const missing = [];
            if (requireCheckIn && !hasCheckIn) missing.push('Check-In');
            if (requireCheckOut && !hasCheckOut) missing.push('Check-Out');

            if (missing.length === 0) {
                return {
                    label: 'KEHADIRAN LENGKAP',
                    chipClass: 'is-success',
                    note: '',
                };
            }

            const now = this.serverTime ? new Date(this.serverTime) : new Date();
            const deleteDeadline = this.getOvertimeDeleteDeadline(item);
            const isExpired = !deleteDeadline || now > deleteDeadline;

            return {
                label: isExpired ? 'EXPIRED' : 'IN PROGRESS',
                chipClass: isExpired ? 'is-danger' : 'is-info',
                note: `Belum lengkap: ${missing.join(' + ')}`,
            };
        },

        canDeleteDetailItem(item) {
            if (!item) return false;
            const status = this.getDetailAttendanceStatus(item);
            return status?.label === 'IN PROGRESS';
        },

        hashCode(str) {
            let hash = 0;
            for (let i = 0; i < str.length; i++) {
                hash = (hash << 5) - hash + str.charCodeAt(i);
                hash |= 0;
            }
            return hash;
        },

        // Detail Modal Methods
        async showDetailsMethod(item) {
            this.selectedTransaction = item;
            this.detailLoading = true;
            this.showDetailModal = true;

            try {
                const response = await axios.get(this.getDetailsEndpoint(), {
                    params: {
                        No_Transaksi: item.No_Transaksi,
                    },
                });

                if (response.data.status === 200) {
                    this.detailTransaksi = response.data.result || [];
                }
            } catch (error) {
                console.error('Error fetching detail:', error);
                ElMessage.error('Gagal memuat detail transaksi');
                this.detailTransaksi = [];
            } finally {
                this.detailLoading = false;
            }
        },

        closeDetailModal() {
            this.showDetailModal = false;
            this.selectedTransaction = null;
            this.detailTransaksi = [];
        },

        // Alert Modal Methods
        deleteAlertButton(transaksiOrItem) {
            const noTransaksi = typeof transaksiOrItem === 'object' ? transaksiOrItem?.No_Transaksi : transaksiOrItem;

            if (typeof transaksiOrItem === 'object' && !this.canDeleteTransaction(transaksiOrItem)) {
                ElMessage.warning('Transaksi tidak dapat dihapus karena sudah expired atau kehadiran lengkap.');
                return;
            }

            if (!noTransaksi) {
                ElMessage.warning('No transaksi tidak valid');
                return;
            }

            this.titleAlertModal = 'Hapus Transaksi';
            this.buttonAlertModal = 'Hapus';
            this.bodyAlert = `Apakah Anda yakin menghapus transaksi ${noTransaksi}?`;
            this.cancelReason = '';
            this.isDeleteAlert = true;
            this.yesModal = () => this.destroyItem(noTransaksi);
            this.showAlertModal = true;
        },

        deleteUserButton(itemOrUrutOto) {
            const urutOto = typeof itemOrUrutOto === 'object' ? itemOrUrutOto?.Urut_Oto : itemOrUrutOto;

            if (typeof itemOrUrutOto === 'object' && !this.canDeleteDetailItem(itemOrUrutOto)) {
                ElMessage.warning('Detail tidak dapat dihapus karena sudah expired atau kehadiran lengkap.');
                return;
            }

            if (!urutOto) {
                ElMessage.warning('Data detail lembur tidak valid');
                return;
            }

            this.titleAlertModal = 'Hapus Karyawan Lembur';
            this.buttonAlertModal = 'Hapus';
            this.bodyAlert = 'Apakah Anda yakin menghapus karyawan dari lembur ini?';
            this.yesModal = () => this.destroyUser(urutOto);
            this.showAlertModal = true;
        },

        closeAlertModal() {
            this.showAlertModal = false;
            this.titleAlertModal = '';
            this.buttonAlertModal = '';
            this.bodyAlert = '';
            this.yesModal = null;
            this.cancelReason = '';
            this.isDeleteAlert = false;
        },

        async destroyItem(noTransaksi) {
            if (this.cancelReason.length < 10) {
                ElMessage.warning('Alasan pembatalan minimal 10 karakter.');
                return;
            }
            try {
                this.loading = true;
                const response = await axios.post('/overtime/destroyOvertime', {
                    params: {
                        No_Transaksi: noTransaksi,
                        Alasan_Pembatalan: this.cancelReason.trim(),
                        // Admin boleh membatalkan kapan pun (bypass batas +7 jam) di backend.
                        admin: this.isAdmin ? 'true' : 'false',
                    },
                });

                if (response.status === 200) {
                    ElNotification({
                        title: 'Berhasil',
                        message: 'Transaksi lembur berhasil dihapus',
                        type: 'success',
                    });
                    this.closeAlertModal();
                    this.closeDetailModal();
                    this.closeExpandModal();
                    this.showAssignModal = false;
                    await this.getData();
                }
            } catch (error) {
                console.error('Error deleting item:', error);
                ElNotification({
                    title: 'Gagal',
                    message: error?.response?.data?.message || 'Gagal menghapus transaksi',
                    type: 'error',
                });
            } finally {
                this.loading = false;
            }
        },

        async destroyUser(urutOto) {
            try {
                this.loading = true;
                const response = await axios.post('/overtime/destroyUserOvertime', {
                    params: {
                        urut_Oto: urutOto,
                        // Admin boleh membatalkan kapan pun (bypass batas +7 jam) di backend.
                        admin: this.isAdmin ? 'true' : 'false',
                    },
                });

                if (response.status === 200) {
                    ElNotification({
                        title: 'Berhasil',
                        message: 'Karyawan berhasil dihapus dari lembur',
                        type: 'success',
                    });
                    this.closeAlertModal();
                    this.closeDetailModal();
                    this.closeExpandModal();
                    this.showAssignModal = false;
                    await this.getData();
                }
            } catch (error) {
                console.error('Error deleting user:', error);
                ElNotification({
                    title: 'Gagal',
                    message: error?.response?.data?.message || 'Gagal menghapus karyawan',
                    type: 'error',
                });
            } finally {
                this.loading = false;
            }
        },

        // Assign Modal Methods
        openAssignModal() {
            console.log('as');
            this.showAssignModal = true;
            this.assignStep = 1;
            this.resetAssignForm();
        },

        closeAssignModal(force = false) {
            if (!force && (this.isAssignStepProcessing || this.isSubmitting)) return;
            this.showAssignModal = false;
            this.assignStep = 1;
            this.resetAssignForm();
        },

        resetAssignForm() {
            this.assignForm = {
                selectedType: null,
                selectedDate: null,
                startTime: null,
                endTime: null,
                singleTime: null,
                reason: '',
                selectedEmployees: [],
            };
            this.assignSearchInput = '';
            this.assignSearchQuery = '';
            this.userChoose = [];
            this.lastSelectionContextKey = null;
            this.assignEmployeeVisibleCount = this.assignEmployeeBatchSize;
            this.assignEmployeeChunkLoading = false;
            this.assignEmployeeServerPage = 0;
            this.assignEmployeeServerHasMore = false;
            this.assignEmployeeServerTotal = 0;
            if (this.assignEmployeeAbortController) {
                this.assignEmployeeAbortController.abort();
                this.assignEmployeeAbortController = null;
            }
            if (this.assignSearchDebounceTimer) {
                clearTimeout(this.assignSearchDebounceTimer);
                this.assignSearchDebounceTimer = null;
            }
        },

        getStepLabel(step) {
            const labels = {
                1: 'Tanggal',
                2: 'Keterangan',
                3: 'Karyawan',
                4: 'Review',
            };
            return labels[step] || '';
        },

        async nextAssignStep() {
            if (this.isAssignStepProcessing || this.isSubmitting) return;

            if (!this.canProceedToNextStep) {
                ElMessage.warning('Harap lengkapi data terlebih dahulu');
                return;
            }

            try {
                this.isAssignStepProcessing = true;

                if (this.assignStep === 1) {
                    if (!this.assignForm.selectedType) {
                        ElMessage.warning('Harap pilih tipe lembur');
                        return;
                    }
                    // Validasi tanggal
                    if (!this.assignForm.selectedDate) {
                        ElMessage.warning('Harap pilih tanggal lembur');
                        return;
                    }

                    // Cek apakah tanggal tidak di masa lalu
                    const selectedDate = new Date(this.assignForm.selectedDate);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);

                    if (
                        !this.isAdmin &&
                        selectedDate < today &&
                        !['LEMBUR_HK', 'LEMBUR_AWAL'].includes(this.assignForm.selectedType)
                    ) {
                        ElMessage.warning('Tidak dapat memilih tanggal di masa lalu');
                        return;
                    }

                    this.assignStep++;
                } else if (this.assignStep === 2) {
                    this.ensureAssignTimeShape();
                    // Validasi waktu dan alasan
                    if (!this.assignForm.startTime) {
                        ElMessage.warning('Harap pilih waktu mulai lembur');
                        return;
                    }
                    if (this.requiresRangeTime && !this.assignForm.endTime) {
                        ElMessage.warning('Harap pilih estimasi waktu selesai lembur');
                        return;
                    }
                    const reasonLength = (this.assignForm.reason || '').trim().length;
                    if (reasonLength < 50) {
                        ElMessage.warning(
                            `Alasan lembur minimal 50 karakter (saat ini ${reasonLength} karakter, kurang ${50 - reasonLength} karakter).`,
                        );
                        return;
                    }

                    // Pindah step dulu agar UX terasa responsif, data dimuat di background
                    this.assignStep++;
                    this.userChoose = [];
                    await this.loadAvailableEmployees();
                } else if (this.assignStep === 3) {
                    this.purgeStaleSelectedEmployeesByContext(false);
                    // Validasi pilihan karyawan
                    if (this.assignForm.selectedEmployees.length === 0) {
                        ElMessage.warning('Harap pilih minimal 1 karyawan');
                        return;
                    }
                    const validation = await this.validateDraftSelection();
                    if (!validation.ok) {
                        return;
                    }
                    this.assignStep++;
                }
            } finally {
                this.isAssignStepProcessing = false;
            }
        },

        prevAssignStep() {
            if (this.isAssignStepProcessing || this.isSubmitting) return;

            if (this.assignStep > 1) {
                // Hanya kosongkan pilihan karyawan saat back ke step 2 atau lebih awal
                // karena API call berikutnya akan menghasilkan encrypted ID baru
                // Dari step 4 (review) ke step 3 (pilih karyawan), pertahankan pilihan
                if (this.assignStep <= 3) {
                    this.assignForm.selectedEmployees = [];
                }
                this.selectedExpandEmployee = null;
                this.assignStep--;
            }
        },

        async loadAvailableEmployees(options = {}) {
            const { reset = true, syncServerTime = true, reconcileSelection = true } = options;

            if (!reset && !this.assignEmployeeServerHasMore) {
                return;
            }

            if (this.assignEmployeeChunkLoading) {
                return;
            }

            try {
                if (reset) {
                    this.loading = true;
                    this.assignEmployeeServerPage = 0;
                    this.assignEmployeeServerHasMore = false;
                    this.assignEmployeeServerTotal = 0;
                    this.userChoose = [];

                    if (this.assignEmployeeAbortController) {
                        this.assignEmployeeAbortController.abort();
                    }
                }

                this.assignEmployeeChunkLoading = true;
                if (syncServerTime) {
                    await this.getTimeServer();
                }
                this.ensureAssignTimeShape();

                const requestController = new AbortController();
                this.assignEmployeeAbortController = requestController;

                const nextPage = reset ? 1 : this.assignEmployeeServerPage + 1;

                const params = {
                    date: this.assignForm.selectedDate,
                    type: this.assignForm.selectedType,
                    startTime: this.assignForm.startTime,
                    endTime: this.assignForm.endTime,
                    time: this.assignForm.endTime,
                    search: this.assignSearchQuery || null,
                    page: nextPage,
                    per_page: this.assignEmployeeServerPerPage,
                };

                const response = await axios.get(this.getUserActiveEndpoint(), {
                    params,
                    signal: requestController.signal,
                });

                if (this.assignEmployeeAbortController !== requestController) {
                    return;
                }
                const payload = response?.data?.result;
                const rows = Array.isArray(payload) ? payload : Array.isArray(payload?.data) ? payload.data : [];
                const meta = payload && !Array.isArray(payload) ? payload.meta || {} : null;

                if (reset) {
                    this.userChoose = rows;
                } else {
                    const merged = [...(this.userChoose || []), ...rows];
                    const seen = new Set();
                    this.userChoose = merged.filter((emp) => {
                        const key = String(emp?.userId || '');
                        if (!key || seen.has(key)) {
                            return false;
                        }
                        seen.add(key);
                        return true;
                    });
                }

                if (meta) {
                    this.assignEmployeeServerPage = Number(meta.page || nextPage);
                    this.assignEmployeeServerHasMore = Boolean(meta.has_more);
                    this.assignEmployeeServerTotal = Number(meta.total || 0);
                } else {
                    this.assignEmployeeServerPage = nextPage;
                    this.assignEmployeeServerHasMore = false;
                    this.assignEmployeeServerTotal = this.userChoose.length;
                }

                // Hapus hanya karyawan yang eksplisit dikembalikan API sebagai tidak valid.
                // Jangan hapus berdasarkan "tidak muncul di list" karena bisa terkena pagination/filter search.
                if (this.assignForm.selectedEmployees.length > 0) {
                    const explicitlyInactiveIds = new Set(
                        this.userChoose
                            .filter((emp) => !this.isEmployeeAvailable(emp))
                            .map((emp) => String(emp.userId)),
                    );

                    if (explicitlyInactiveIds.size > 0) {
                        const beforeCount = this.assignForm.selectedEmployees.length;
                        this.assignForm.selectedEmployees = this.assignForm.selectedEmployees.filter(
                            (emp) => !explicitlyInactiveIds.has(String(emp.id)),
                        );
                        const removed = beforeCount - this.assignForm.selectedEmployees.length;
                        if (removed > 0) {
                            ElMessage.info(`${removed} pilihan karyawan dibersihkan karena sudah tidak valid`);
                        }
                    }
                }

                if (reset && reconcileSelection && this.assignForm.selectedEmployees.length > 0) {
                    await this.reconcileSelectedEmployeesOnLoad(false);
                }

                if (reset) {
                    this.resetAssignEmployeeLazyState();
                }
            } catch (error) {
                if (error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError') {
                    return;
                }
                console.error('Error loading employees:', error);
                if (reset) {
                    this.userChoose = [];
                    this.assignEmployeeServerHasMore = false;
                    this.assignEmployeeServerTotal = 0;
                    this.resetAssignEmployeeLazyState();
                }

                if (error.response?.status === 404) {
                    if (reset) {
                        ElMessage.warning(
                            `Tidak ada karyawan tersedia pada tanggal ${this.formatDate(this.assignForm.selectedDate)}`,
                        );
                    }
                } else {
                    ElMessage.error('Gagal memuat data karyawan');
                }
            } finally {
                this.assignEmployeeAbortController = null;
                this.assignEmployeeChunkLoading = false;
                if (reset) {
                    this.loading = false;
                }
            }
        },

        isEmployeeAvailable(employee) {
            return employee?.isActive === true;
        },

        getUnavailableReason(employee) {
            return employee?.inactiveReason || 'Tidak tersedia';
        },

        normalizeEmployeeId(value) {
            return String(value ?? '');
        },

        dedupeSelectedEmployees() {
            const seen = new Set();
            this.assignForm.selectedEmployees = this.assignForm.selectedEmployees.filter((emp) => {
                const key = emp?.stableId || this.normalizeEmployeeId(emp?.id);
                if (!key || seen.has(key)) {
                    return false;
                }
                seen.add(key);
                return true;
            });
        },

        isEmployeeSelected(stableId) {
            if (!stableId) return false;
            return this.assignForm.selectedEmployees.some((emp) => emp?.stableId === stableId);
        },

        toggleEmployeeSelection(employee) {
            if (!this.isEmployeeAvailable(employee)) {
                return;
            }

            const stableId = employee?.stableId;
            const isSelected = this.isEmployeeSelected(stableId);

            if (isSelected) {
                this.assignForm.selectedEmployees = this.assignForm.selectedEmployees.filter(
                    (emp) => emp?.stableId !== stableId,
                );
            } else {
                if (this.isEmployeeSelected(stableId)) {
                    return;
                }
                this.assignForm.selectedEmployees.push({
                    id: employee.userId,
                    stableId: employee.stableId,
                    nama: employee.name,
                    divisi: employee.Divisi,
                    shift: employee.Nama_Shift,
                    idShift: employee.ID_Shift,
                    jamMasuk: employee.Jam_Masuk,
                    jamKeluar: employee.Jam_Keluar,
                    snapshot_shift_id: employee.ID_Shift,
                    snapshot_status_hari: employee.StatusHari,
                    snapshot_jam_masuk: employee.Jam_Masuk,
                    snapshot_jam_keluar: employee.Jam_Keluar,
                    snapshot_checked_at: employee.eligibility_checked_at,
                    tanggal: this.assignForm.selectedDate,
                    effectiveWorkDate: this.assignForm.selectedDate,
                    type: this.assignForm.selectedType,
                    waktu: this.assignForm.endTime,
                    startTime: this.assignForm.startTime,
                    endTime: this.assignForm.endTime,
                    reason: this.assignForm.reason,
                    selectionContextKey: this.buildSelectionContextKey(),
                });
            }

            this.dedupeSelectedEmployees();

            this.lastSelectionContextKey = this.buildSelectionContextKey();
        },

        selectAllAvailableEmployees() {
            if (this.assignEmployeeServerHasMore) {
                ElMessage.info('Scroll sampai data karyawan habis dimuat sebelum pilih semua.');
                return;
            }

            const availableEmployees = this.userChoose.filter(
                (emp) => this.isEmployeeAvailable(emp) && !this.isEmployeeSelected(emp.stableId),
            );

            availableEmployees.forEach((employee) => {
                this.assignForm.selectedEmployees.push({
                    id: employee.userId,
                    stableId: employee.stableId,
                    nama: employee.name,
                    divisi: employee.Divisi,
                    shift: employee.Nama_Shift,
                    idShift: employee.ID_Shift,
                    jamMasuk: employee.Jam_Masuk,
                    jamKeluar: employee.Jam_Keluar,
                    snapshot_shift_id: employee.ID_Shift,
                    snapshot_status_hari: employee.StatusHari,
                    snapshot_jam_masuk: employee.Jam_Masuk,
                    snapshot_jam_keluar: employee.Jam_Keluar,
                    snapshot_checked_at: employee.eligibility_checked_at,
                    tanggal: this.assignForm.selectedDate,
                    effectiveWorkDate: this.assignForm.selectedDate,
                    type: this.assignForm.selectedType,
                    waktu: this.assignForm.endTime,
                    startTime: this.assignForm.startTime,
                    endTime: this.assignForm.endTime,
                    reason: this.assignForm.reason,
                    selectionContextKey: this.buildSelectionContextKey(),
                });
            });

            this.dedupeSelectedEmployees();

            this.lastSelectionContextKey = this.buildSelectionContextKey();

            ElMessage.success(`${availableEmployees.length} karyawan ditambahkan`);
        },

        clearEmployeeSelection() {
            this.assignForm.selectedEmployees = [];
            this.lastSelectionContextKey = this.buildSelectionContextKey();
        },

        removeSelectedEmployee(employeeId) {
            this.assignForm.selectedEmployees = this.assignForm.selectedEmployees.filter(
                (emp) => (emp.stableId || emp.id) !== String(employeeId),
            );
            this.lastSelectionContextKey = this.buildSelectionContextKey();
        },

        async submitOvertime() {
            this.purgeStaleSelectedEmployeesByContext(false);
            if (this.assignForm.selectedEmployees.length === 0) {
                ElMessage.warning('Harap pilih minimal 1 karyawan');
                return;
            }

            // Validasi alasan lembur minimal 50 karakter (berlaku untuk ketiga jenis lembur:
            // Lembur Hari Kerja, Lembur Awal, dan Lembur Libur).
            const mainReason = (this.assignForm.reason || '').trim();
            if (mainReason.length < 50) {
                ElMessage.warning(
                    `Alasan lembur minimal 50 karakter (saat ini ${mainReason.length} karakter). Mohon lengkapi alasan lembur.`,
                );
                return;
            }

            // Pastikan alasan khusus per karyawan (jika diisi) juga memenuhi minimal 50 karakter.
            const invalidEmployee = this.assignForm.selectedEmployees.find((emp) => {
                const empReason = (emp.reason || '').trim();
                return empReason.length > 0 && empReason.length < 50;
            });
            if (invalidEmployee) {
                const empReason = (invalidEmployee.reason || '').trim();
                ElMessage.warning(
                    `Alasan lembur khusus untuk ${invalidEmployee.nama || 'karyawan'} minimal 50 karakter (saat ini ${empReason.length} karakter).`,
                );
                return;
            }

            this.titleAlertModal = 'Konfirmasi Pengajuan';
            this.buttonAlertModal = 'Ya, Simpan';
            this.bodyAlert = 'Pastikan data pengajuan lembur sudah benar. Lanjutkan simpan?';
            this.yesModal = () => this.executeSubmitOvertime();
            this.showAlertModal = true;
        },

        async executeSubmitOvertime() {
            try {
                this.loading = true;
                this.isSubmitting = true;
                this.ensureAssignTimeShape();

                const response = await axios.post(this.getSubmitEndpoint(), {
                    params: {
                        Kode_Perusahaan: '001',
                        dataUsers: this.assignForm.selectedEmployees,
                        date: this.assignForm.selectedDate,
                        type: this.assignForm.selectedType,
                        startTime: this.assignForm.startTime,
                        endTime: this.assignForm.endTime,
                    },
                });

                if (response.status === 200) {
                    const result = response.data?.result || {};
                    const inserted = Number(result.inserted || 0);
                    const conflicts = Array.isArray(result.conflicts) ? result.conflicts : [];

                    if (inserted > 0 && conflicts.length === 0) {
                        ElNotification({
                            title: 'Berhasil',
                            message: 'Pengajuan lembur berhasil disimpan',
                            type: 'success',
                        });
                        this.closeAlertModal();
                        this.closeAssignModal(true);
                        await this.getData();
                        return;
                    }

                    if (inserted > 0 && conflicts.length > 0) {
                        ElNotification({
                            title: 'Sebagian Berhasil',
                            message: `${inserted} karyawan tersimpan, ${conflicts.length} karyawan gagal karena data berubah.`,
                            type: 'warning',
                            duration: 6000,
                        });
                    } else if (inserted === 0 && conflicts.length > 0) {
                        ElNotification({
                            title: 'Data Berubah',
                            message:
                                conflicts[0]?.reason ||
                                'Semua karyawan gagal disimpan karena data terbaru berubah. Harap refresh.',
                            type: 'warning',
                            duration: 6000,
                        });
                    }

                    // Tetap di step 3, hapus pilihan lama, refresh daftar terbaru.
                    this.closeAlertModal();
                    this.assignStep = 3;
                    this.assignForm.selectedEmployees = [];
                    await this.loadAvailableEmployees();
                }
            } catch (error) {
                console.error('Error submitting overtime:', error);
                ElNotification({
                    title: 'Gagal',
                    message: 'Gagal menyimpan pengajuan lembur',
                    type: 'error',
                });
            } finally {
                this.isSubmitting = false;
                this.loading = false;
            }
        },

        async validateDraftSelection(customRows = null) {
            try {
                this.loading = true;
                this.ensureAssignTimeShape();

                const rows = Array.isArray(customRows) ? customRows : this.assignForm.selectedEmployees;

                const response = await axios.post(this.getValidateDraftEndpoint(), {
                    params: {
                        Kode_Perusahaan: '001',
                        dataUsers: rows,
                        date: this.assignForm.selectedDate,
                        type: this.assignForm.selectedType,
                        startTime: this.assignForm.startTime,
                        endTime: this.assignForm.endTime,
                    },
                });

                const result = response?.data?.result || {};
                const conflicts = Array.isArray(result.conflicts) ? result.conflicts : [];
                const validRows = Array.isArray(result.valid_rows) ? result.valid_rows : [];

                if (conflicts.length === 0) {
                    return { ok: true };
                }

                // Untuk validasi single-row (edit detail), cukup return reason tanpa mutasi list.
                if (Array.isArray(customRows)) {
                    return {
                        ok: false,
                        message: conflicts[0]?.reason || 'Perubahan jam detail tidak valid.',
                    };
                }

                const conflictMap = new Map(conflicts.map((c) => [String(c.employee_id), c.reason]));
                const validIds = new Set(validRows.map((r) => String(r.id)));

                this.assignForm.selectedEmployees = this.assignForm.selectedEmployees.filter((emp) =>
                    validIds.has(String(emp.id)),
                );

                this.userChoose = (this.userChoose || []).map((emp) => {
                    const reason = conflictMap.get(String(emp.userId));
                    if (!reason) return emp;
                    return {
                        ...emp,
                        isActive: false,
                        inactiveReason: reason,
                    };
                });

                if (this.assignForm.selectedEmployees.length === 0) {
                    ElNotification({
                        title: 'Data Berubah',
                        message: conflicts[0]?.reason || 'Semua pilihan tidak valid. Silakan cek ulang data terbaru.',
                        type: 'warning',
                        duration: 6000,
                    });
                    return { ok: false };
                }

                ElNotification({
                    title: 'Sebagian Tidak Valid',
                    message: `${conflicts.length} karyawan dihapus dari pilihan karena data/policy terbaru.`,
                    type: 'warning',
                    duration: 6000,
                });
                return { ok: false };
            } catch (error) {
                console.error('Error validating draft selection:', error);
                ElMessage.error('Gagal validasi draft lembur');
                return { ok: false };
            } finally {
                this.loading = false;
            }
        },

        getUserActiveEndpoint() {
            return this.isAdmin ? '/lembur-management/admin/get-user-active' : '/lembur-management/get-user-active';
        },

        getSubmitEndpoint() {
            return this.isAdmin ? '/lembur-management/admin/submit' : '/lembur-management/submit';
        },

        getValidateDraftEndpoint() {
            return this.isAdmin ? '/lembur-management/admin/validate-draft' : '/lembur-management/validate-draft';
        },

        getHistoryEndpoint() {
            return this.isAdmin ? '/lembur-management/admin/get-data' : '/lembur-management/get-data';
        },

        getDetailsEndpoint() {
            return this.isAdmin ? '/lembur-management/admin/get-details' : '/lembur-management/get-details';
        },

        // Time Validation Methods
        isTimeAfter(jamMasuk, jamKeluar, shiftType) {
            if (!this.assignForm.selectedDate) return false;

            const selectedDate = new Date(this.assignForm.selectedDate);
            const serverTime = this.serverTime || new Date();

            // Parse jamMasuk
            const [hmH, hmM = '0', hmS = '0'] = jamMasuk.split(':');
            let masukTime = new Date(selectedDate);
            masukTime.setHours(parseInt(hmH), parseInt(hmM), parseInt(hmS), 0);

            // Parse jamKeluar
            const [hkH, hkM = '0', hkS = '0'] = jamKeluar.split(':');
            let cutoffTime = new Date(selectedDate);
            cutoffTime.setHours(parseInt(hkH), parseInt(hkM), parseInt(hkS), 0);

            // Kalau shift lintas hari (jam masuk >= jam keluar) → jam keluar dianggap besok
            if (masukTime >= cutoffTime) {
                cutoffTime.setDate(cutoffTime.getDate() + 1);
            }

            // Tambahkan toleransi 4 jam
            cutoffTime.setHours(cutoffTime.getHours() + 4);

            return serverTime <= cutoffTime;
        },

        // Export Methods
        openExportDialog() {
            this.dialogVisible = true;
            this.tempStartDate = null;
            this.tempEndDate = null;
            this.calendarDate = new Date();
        },

        handleCancel() {
            this.dialogVisible = false;
            this.tempStartDate = null;
            this.tempEndDate = null;
        },

        handleDateClick(data) {
            const clickedDate = data.date;

            if (!this.tempStartDate) {
                this.tempStartDate = clickedDate;
            } else if (!this.tempEndDate) {
                if (clickedDate.getTime() < this.tempStartDate.getTime()) {
                    this.tempEndDate = this.tempStartDate;
                    this.tempStartDate = clickedDate;
                } else {
                    this.tempEndDate = clickedDate;
                }
            } else {
                this.tempStartDate = clickedDate;
                this.tempEndDate = null;
            }
        },

        getCellClass(data) {
            const classes = {};
            const currentDate = data.date.setHours(0, 0, 0, 0);
            const start = this.tempStartDate ? this.tempStartDate.setHours(0, 0, 0, 0) : null;
            const end = this.tempEndDate ? this.tempEndDate.setHours(0, 0, 0, 0) : null;

            if (!start) return classes;

            if (currentDate === start) classes['is-start'] = true;
            if (currentDate === end) classes['is-end'] = true;

            if (start && end && currentDate > start && currentDate < end) {
                classes['is-in-range'] = true;
            }

            return classes;
        },

        formatDateToString(date) {
            if (!date) return '';
            const year = date.getFullYear();
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const day = date.getDate().toString().padStart(2, '0');
            return `${year}-${month}-${day}`;
        },

        async confirmDate() {
            if (!this.tempStartDate || !this.tempEndDate) {
                ElMessage.warning('Harap pilih tanggal mulai dan selesai');
                return;
            }

            const startDate = this.formatDateToString(this.tempStartDate);
            const endDate = this.formatDateToString(this.tempEndDate);

            await this.downloadExcel(startDate, endDate);
            this.handleCancel();
        },

        async downloadExcel(startDate, endDate) {
            try {
                this.loading = true;

                const response = await axios.get('/overtime/getExport', {
                    params: {
                        start: startDate,
                        end: endDate,
                    },
                });

                if (response.status === 200) {
                    const ExcelJS = await import('exceljs');
                    await this.exportCustomExcel(response.data.data, startDate, endDate, ExcelJS);
                }
            } catch (error) {
                console.error('Error exporting data:', error);
                ElMessage.error('Gagal mengekspor data');
            } finally {
                this.loading = false;
            }
        },

        async exportCustomExcel(dataExport, startDate, endDate, ExcelJS) {
            try {
                const workbook = new ExcelJS.Workbook();
                const worksheet = workbook.addWorksheet('Assign Overtime');

                // Styling
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

                // Header
                worksheet.mergeCells('B1:H1');
                worksheet.getCell('B1').value = 'Employee Overtime Management';
                worksheet.getCell('B1').style = headerStyle;

                worksheet.getCell('A1').value = 'Tanggal';

                const headers = [
                    'No. Transaksi',
                    'Nama Karyawan',
                    'Tanggal Lembur',
                    'Waktu',
                    'Durasi',
                    'Alasan',
                    'Status Lembur(Check Out)',
                ];

                const headerRow = worksheet.getRow(2);
                headerRow.values = [null, ...headers];
                headerRow.eachCell({ includeEmpty: true }, (cell, colNumber) => {
                    if (colNumber > 0) {
                        cell.style = headerStyle;
                    }
                });

                worksheet.mergeCells('A1:A2');
                worksheet.getCell('A1').style = headerStyle;

                // Kolom
                worksheet.columns = [
                    { key: 'tanggal', width: 15 },
                    { key: 'noTrans', width: 20 },
                    { key: 'nama', width: 30 },
                    { key: 'tglLembur', width: 15 },
                    { key: 'waktu', width: 20 },
                    { key: 'durasi', width: 15 },
                    { key: 'alasan', width: 40 },
                    { key: 'status', width: 40 },
                ];

                const normalizedData = Array.isArray(dataExport) ? dataExport : Object.entries(dataExport || {});

                let currentRowIndex = 3;

                normalizedData.forEach(([date, trans]) => {
                    const dateBlockStartRow = currentRowIndex;
                    const transEntries = Object.entries(trans || {});

                    transEntries.forEach(([noTrans, detailTrans]) => {
                        const transBlockStartRow = currentRowIndex;
                        const details = Array.isArray(detailTrans?.details) ? detailTrans.details : [];

                        if (details.length === 0) {
                            return;
                        }

                        details.forEach((user) => {
                            const row = worksheet.getRow(currentRowIndex);
                            row.values = {
                                nama: user.name || '-',
                                tglLembur: this.formatDate(user.start_time),
                                waktu: this.formatTime(user.start_time, user.end_time),
                                durasi: this.formatDuration(user.start_time, user.end_time),
                                alasan: user.reason || '-',
                                status: user.CHECKOUT || '-',
                            };

                            row.eachCell({ includeEmpty: true }, (cell, colNumber) => {
                                if (colNumber > 1) {
                                    cell.style = cellStyle;
                                }
                            });

                            currentRowIndex++;
                        });

                        const transBlockEndRow = currentRowIndex - 1;
                        if (transBlockStartRow <= transBlockEndRow) {
                            worksheet.mergeCells(`B${transBlockStartRow}:B${transBlockEndRow}`);
                            const cellB = worksheet.getCell(`B${transBlockStartRow}`);
                            cellB.value = noTrans;
                            cellB.style = centeredCellStyle;
                        }
                    });

                    const dateBlockEndRow = currentRowIndex - 1;
                    if (dateBlockStartRow <= dateBlockEndRow) {
                        worksheet.mergeCells(`A${dateBlockStartRow}:A${dateBlockEndRow}`);
                        const cellA = worksheet.getCell(`A${dateBlockStartRow}`);
                        cellA.value = date;
                        cellA.style = centeredCellStyle;
                    }
                });

                const buffer = await workbook.xlsx.writeBuffer();
                const blob = new Blob([buffer], {
                    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                });

                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `Export_Lembur_${this.formatDate(startDate)}_sd_${this.formatDate(endDate)}.xlsx`;
                link.click();
                URL.revokeObjectURL(link.href);

                ElMessage.success('Export berhasil diunduh');
            } catch (error) {
                console.error('Error creating Excel:', error);
                throw error;
            }
        },
        checkScreenSize() {
            this.viewMode = window.innerWidth < 768 ? 'card' : 'table';
        },

        // Shift Methods
        async getShift() {
            try {
                this.loading = true;
                const response = await axios.get('/overtime/getShift', {
                    params: {
                        tanggal: this.assignForm.selectedDate,
                    },
                });

                if (response.status === 200) {
                    this.shiftList = response.data.data;
                }
            } catch (error) {
                console.error('Error fetching shift:', error);
            } finally {
                this.loading = false;
            }
        },
    },
    mounted() {
        this.getTimeServer();
        this.getData();

        // Set view mode berdasarkan ukuran layar
        this.checkScreenSize();
        window.addEventListener('resize', this.checkScreenSize);
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.checkScreenSize);
        if (this.assignSearchDebounceTimer) {
            clearTimeout(this.assignSearchDebounceTimer);
            this.assignSearchDebounceTimer = null;
        }
        if (this.assignEmployeeAbortController) {
            this.assignEmployeeAbortController.abort();
            this.assignEmployeeAbortController = null;
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

/* Syle Alert estimasi */

.employee-status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
    color: white;
    display: inline-block;
}

.expand-button {
    background: none;
    border: none;
    color: var(--primary);
    cursor: pointer;
    font-size: 0.9rem;
    padding: 0;
    margin-top: 0.5rem;
    transition: all 0.2s ease;
}

.form-control.sm {
    padding: 0.5rem 0.75rem;
    font-size: 0.85rem;
}
.employee-status-badge.available {
    background: var(--success);
}
.employee-status-badge.unavailable {
    background: var(--danger);
}

.form-group {
    margin-bottom: 1.5rem; /* Standard margin for form groups */
    border: 1px solid var(--border); /* Added for visual separation */
    background: rgba(99, 102, 241, 0.05); /* Soft background */
    border-radius: 12px;
    padding: 1rem;
    overflow-y: auto; /* Ensure content within is scrollable if needed */
    max-height: 20rem; /* Cap height to prevent excessive modal height */
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

/* Base Styles */
.overtime-app-container {
    min-height: 100vh;
    /* background: var(--hcis-slate-50); */
}

.main-content-wrapper {
    padding: 1rem 0;
}

.content-container {
    /* max-width: 1400px; */
    margin: 0 auto;
    /* padding: 0 1rem; */
}

/* Header Customizations */
.header-right-section {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.btn-atas {
    border-radius: 15px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    transition: all 0.3s ease;
}

.btn-atas:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Card View Styles */
.card-view-container {
    padding: 1rem 0;
}

.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}

@media (max-width: 768px) {
    .cards-grid {
        grid-template-columns: 1fr;
    }
}

.overtime-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid var(--hcis-slate-200);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}

.overtime-card:hover {
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
    transform: translateY(-4px);
}

.card-header {
    padding: 1.25rem;
    background: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.avatar-md {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1rem;
    color: white;
}

.card-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
}

.card-subtitle {
    margin: 0.25rem 0 0 0;
    font-size: 0.85rem;
    opacity: 0.9;
}

.card-time {
    font-size: 0.9rem;
    font-weight: 500;
    background: rgba(255, 255, 255, 0.2);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
}

.card-body {
    padding: 1.25rem;
    flex-grow: 1;
}

.card-info {
    display: flex;
    align-items: center;
    color: var(--hcis-slate-500);
    font-size: 0.9rem;
}

.card-footer {
    padding: 1rem 1.25rem;
    border-top: 1px solid var(--hcis-slate-200);
    display: flex;
    gap: 0.75rem;
}

/* View Toggle */
.view-toggle-container {
    display: flex;
    justify-content: center;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--hcis-slate-200);
}

.view-toggle {
    display: flex;
    background: var(--hcis-slate-100);
    border-radius: 12px;
    padding: 0.25rem;
    gap: 0.25rem;
}

.toggle-btn {
    padding: 0.5rem 1rem;
    border: none;
    background: transparent;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    color: var(--hcis-slate-500);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.toggle-btn.active {
    background: var(--hcis-indigo-500);
    color: white;
}

.toggle-btn:hover:not(.active) {
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-700);
}

/* Loading & Empty States */
.loading-state,
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    text-align: center;
}

.loading-spinner {
    width: 60px;
    height: 60px;
    border: 3px solid var(--hcis-slate-200);
    border-top: 3px solid var(--hcis-indigo-500);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 1.5rem;
}

.loading-state p,
.empty-state p {
    color: var(--hcis-slate-500);
    margin: 1rem 0 0 0;
}

.empty-state h4 {
    color: var(--hcis-slate-700);
    margin: 1rem 0 0.5rem 0;
}

.empty-state button {
    margin-top: 1.5rem;
}

/* Step Indicator */
.step-indicator {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    position: relative;
}
/* 
.step-indicator::before {
    content: "";
    position: absolute;
    top: 20px;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--hcis-slate-200);
    z-index: 1;
} */

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    position: relative;
    z-index: 2;
    flex: 1;
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--hcis-slate-200);
    border: 2px solid var(--hcis-slate-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: var(--hcis-slate-500);
    transition: all 0.3s ease;
}

.step.active .step-number {
    background: var(--hcis-indigo-500);
    border-color: var(--hcis-indigo-500);
    color: white;
}

.step.completed .step-number {
    background: var(--hcis-emerald-500);
    border-color: var(--hcis-emerald-500);
    color: white;
}

.step-label {
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--hcis-slate-500);
    text-align: center;
}

.step.active .step-label {
    color: var(--hcis-indigo-500);
    font-weight: 600;
}

/* Date Picker Grid */
.date-picker-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 0.75rem;
    margin-top: 1rem;
}

.date-picker-item {
    padding: 1rem;
    border: 2px solid var(--hcis-slate-200);
    border-radius: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.date-picker-item:hover {
    border-color: var(--hcis-indigo-500);
    transform: translateY(-2px);
}

.date-picker-item.selected {
    border-color: var(--hcis-indigo-500);
    background: rgba(99, 102, 241, 0.1);
}

.date-picker-item.today {
    border-color: var(--hcis-amber-500);
    background: rgba(245, 158, 11, 0.1);
}

.date-day {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
    margin-bottom: 0.25rem;
}

.date-number {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--hcis-slate-700);
}

.date-check {
    position: absolute;
    top: -8px;
    right: -8px;
    color: var(--hcis-indigo-500);
    font-size: 1.2rem;
}

/* Employee Selection */
.employee-selection-list {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
}

.employee-load-more {
    padding: 0.85rem 1rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.45rem;
    border-top: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
}

.btn-load-more {
    border: 1px solid var(--hcis-indigo-200);
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    font-weight: 600;
    font-size: 0.83rem;
    padding: 0.4rem 0.9rem;
    border-radius: 999px;
    cursor: pointer;
}

.btn-load-more:hover:not(:disabled) {
    background: var(--hcis-indigo-100);
}

.btn-load-more:disabled {
    cursor: not-allowed;
    opacity: 0.75;
}

.employee-selection-item {
    padding: 1rem;
    border-bottom: 1px solid var(--hcis-slate-200);
    transition: all 0.3s ease;
    cursor: pointer;
}

.employee-selection-item:last-child {
    border-bottom: none;
}

.employee-selection-item:hover:not(.disabled) {
    background: var(--hcis-slate-50);
}

.employee-selection-item.selected {
    background: rgba(99, 102, 241, 0.05);
    border-left: 4px solid var(--hcis-indigo-500);
}

.employee-selection-item.disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.employee-selection-card {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.employee-checkbox {
    width: 20px;
    height: 20px;
    border: 2px solid var(--hcis-slate-200);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-indigo-500);
    font-size: 0.8rem;
    flex-shrink: 0;
}

.employee-selection-item.selected .employee-checkbox {
    background: var(--hcis-indigo-500);
    border-color: var(--hcis-indigo-500);
    color: white;
}

.employee-avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: var(--hcis-indigo-500);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.employee-details {
    flex: 1;
    min-width: 0;
}

.employee-name {
    margin: 0;
    font-weight: 600;
    color: var(--hcis-slate-700);
    font-size: 0.95rem;
    line-height: 1.3;
}

.employee-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.25rem;
    flex-wrap: wrap;
}

.employee-actual-time {
    margin-top: 0.2rem;
    font-size: 0.76rem;
    color: var(--hcis-slate-500);
}

.employee-unavailable-reason {
    margin-top: 0.5rem;
    padding: 0.5rem;
    background: var(--hcis-red-50);
    border-radius: 6px;
    color: var(--hcis-red-600);
    font-size: 0.8rem;
    display: flex;
    align-items: center;
}

.selected-employee-panel {
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    border-radius: 12px;
    padding: 0.75rem;
}

.selected-employee-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.selected-employee-chip-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.selected-employee-chip {
    border: 1px solid var(--hcis-indigo-200);
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-800);
    border-radius: 999px;
    padding: 0.3rem 0.7rem;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    cursor: pointer;
}

.selected-employee-chip:hover {
    background: var(--hcis-indigo-100);
}

.selection-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.btn-link {
    background: none;
    border: none;
    color: var(--hcis-indigo-500);
    padding: 0.5rem;
    cursor: pointer;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    transition: all 0.2s ease;
}

.btn-link:hover {
    color: var(--hcis-indigo-600);
    text-decoration: underline;
}

.btn-link.text-danger {
    color: var(--hcis-red-500);
}

.btn-link.text-danger:hover {
    color: var(--hcis-red-600);
}

.selected-count {
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0.5rem 1rem;
    border-radius: 20px;
}

/* Summary Card */
.summary-card {
    background: var(--hcis-slate-50);
    border-radius: 12px;
    padding: 1.5rem;
    margin: 1.5rem 0;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--hcis-slate-200);
}

.summary-item:last-child {
    border-bottom: none;
}

.summary-label {
    font-weight: 500;
    color: var(--hcis-slate-500);
}

.summary-value {
    font-weight: 600;
    color: var(--hcis-slate-700);
    text-align: right;
}

.selected-employees-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.selected-employee-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: white;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    transition: all 0.2s ease;
}

.selected-employee-card:hover {
    border-color: var(--hcis-indigo-500);
    transform: translateY(-2px);
}

.employee-avatar-sm {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--hcis-indigo-500);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
    flex-shrink: 0;
}

.employee-info-sm {
    flex: 1;
    min-width: 0;
}

.employee-name-sm {
    margin: 0;
    font-weight: 600;
    color: var(--hcis-slate-700);
    font-size: 0.85rem;
    line-height: 1.3;
}

.employee-schedule {
    margin-top: 0.25rem;
}

/* Detail Modal Styles */
.transaction-header {
    background: var(--hcis-slate-50);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.detail-item {
    margin-bottom: 1rem;
}

.detail-item:last-child {
    margin-bottom: 0;
}

.detail-label {
    display: block;
    font-weight: 500;
    color: var(--hcis-slate-500);
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.detail-value {
    color: var(--hcis-slate-700);
    font-weight: 500;
}

.employee-details-section {
    margin-top: 2rem;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--hcis-slate-700);
    display: flex;
    align-items: center;
}

.employee-info-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.avatar-xs {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--hcis-indigo-500);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
}

.employee-name-sm {
    margin: 0;
    font-weight: 600;
    color: var(--hcis-slate-700);
    font-size: 0.9rem;
}

/* Calendar Container */
.calendar-container {
    padding: 1rem 0;
}

.date-range-display {
    margin-top: 1.5rem;
}

/* Button Styles */
.btn-modern {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
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

.btn-modern:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
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
    background-color: var(--hcis-indigo-500);
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background-color: var(--hcis-indigo-600);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.btn-secondary {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-700);
    border: 1px solid var(--hcis-slate-200);
}

.btn-secondary:hover:not(:disabled) {
    background: var(--hcis-slate-200);
    transform: translateY(-1px);
}

.btn-success {
    background: var(--hcis-emerald-500);
    color: white;
}

.btn-success:hover:not(:disabled) {
    background: var(--hcis-emerald-600);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-danger {
    background: var(--hcis-red-500);
    color: white;
}

.btn-danger:hover:not(:disabled) {
    background: var(--hcis-red-600);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.btn-action {
    padding: 0.5rem 0.75rem;
    border: none;
    border-radius: 8px;
    font-size: 0.85rem;
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
    transform: translateY(-1px);
}

.btn-delete {
    background: #ffebee;
    color: #d32f2f;
}

.btn-delete:hover:not(:disabled) {
    background: #ffcdd2;
    transform: translateY(-1px);
}

.btn-delete:disabled {
    opacity: 0.5;
    cursor: not-allowed;
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
    flex: 1;
    justify-content: center;
}

.card-btn.btn-view {
    background: #e3f2fd;
    color: #1976d2;
}

.card-btn.btn-delete {
    background: #ffebee;
    color: #d32f2f;
}

/* Pagination */
.pagination-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding: 1.5rem 0;
}

.page-btn {
    width: 40px;
    height: 40px;
    border: 1px solid var(--hcis-slate-200);
    background: white;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.page-btn:hover:not(:disabled) {
    background: var(--hcis-indigo-500);
    color: white;
    border-color: var(--hcis-indigo-500);
}

.page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.page-info {
    font-weight: 500;
    color: var(--hcis-slate-500);
    min-width: 120px;
    text-align: center;
}

/* Animation */
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

.fade-in {
    animation: fadeIn 0.3s ease;
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

.popup-animation {
    animation: popup 0.3s ease forwards;
}

@keyframes popup {
    0% {
        opacity: 0;
        transform: scale(0.95);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .content-container {
        /* padding: 0 0.75rem; */
    }
    .employee-name {
        text-align: start;
    }

    .date-picker-grid {
        grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    }

    .employee-selection-card {
        /* flex-direction: column; */
        text-align: center;
        gap: 0.75rem;
    }

    .employee-avatar {
        width: 48px;
        height: 48px;
        display: none;
    }
    .employee-meta {
        flex-direction: column;
        justify-content: center;
        align-items: start;
        gap: 0rem;
    }
    .summary-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .selected-employees-grid {
        grid-template-columns: 1fr;
    }

    .footer-actions {
        flex-direction: column;
        gap: 0.75rem;
    }

    .footer-actions .btn-modern {
        width: 100%;
        justify-content: center;
    }

    .step-indicator::before {
        top: 15px;
    }

    .step-number {
        width: 30px;
        height: 30px;
        font-size: 0.9rem;
    }

    .step-label {
        font-size: 0.75rem;
    }
}

@media (max-width: 480px) {
    .header-right-section {
        width: 100%;
    }

    .cards-grid {
        gap: 1rem;
    }

    .card-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }

    .card-footer {
        flex-direction: column;
    }

    .card-btn {
        width: 100%;
    }

    .pagination-container {
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .page-info {
        order: -1;
        width: 100%;
        text-align: center;
    }
}

/* Badge Styles */
.badge-xs {
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
}

/* Glass Morphism Effect */
.glassMorph {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

/* Shining Effect */
.shiningEffect {
    transition: all 0.3s ease;
    overflow: hidden;
    position: relative;
}

.shiningEffect::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.shiningEffect:hover::before {
    left: 100%;
}

/* Element Plus Calendar Customization */
:deep(.el-calendar) {
    border: none;
}

:deep(.el-calendar__header) {
    padding: 1rem;
    border-bottom: 1px solid var(--hcis-slate-200);
}

:deep(.el-calendar__body) {
    padding: 1rem;
}

:deep(.cell-wrapper) {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 1;
}

:deep(.date-cell) {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    z-index: 2;
}

:deep(.cell-wrapper:hover .date-cell) {
    background-color: #d9ecff;
}

:deep(.is-start .date-cell),
:deep(.is-end .date-cell) {
    background-color: #409eff;
    color: white;
}

:deep(.is-in-range::before) {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    right: 0;
    background-color: #ecf5ff;
    z-index: 0;
}

:deep(.is-start::before) {
    left: 50%;
}

:deep(.is-end::before) {
    right: 50%;
}

.info-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-lg);
}
</style>
<style scoped>
.riwayat-page-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.9rem;
}

.riwayat-page-head__title h4 {
    margin: 0;
    font-size: 1.02rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
    letter-spacing: 0.1px;
}

.riwayat-page-head__title p {
    margin: 0.2rem 0 0;
    font-size: 0.82rem;
    color: var(--hcis-slate-500);
    line-height: 1.4;
}

.btn-refresh {
    min-width: 104px;
    height: 36px;
    border-radius: 10px;
    border: 1px solid var(--hcis-slate-300);
    background: #fff;
    color: var(--hcis-slate-600);
    font-size: 0.82rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    transition: all 0.2s ease;
}

.btn-refresh:hover:not(:disabled) {
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-600);
    background: #f8faff;
}

.btn-refresh:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.btn-refresh .spin {
    animation: spin 0.8s linear infinite;
}

.card-view-container {
    padding-top: 0.45rem;
}

.cards-grid {
    gap: 0.9rem;
}

.overtime-card {
    border-radius: 14px;
    border: 1px solid #dbe3ee;
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
}

.overtime-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.09);
}

.overtime-card .card-header {
    padding: 0.9rem 1rem;
    background: linear-gradient(135deg, #5b6cf4 0%, #6d4bd9 100%);
}

.overtime-card .card-title {
    font-size: 0.9rem;
    font-weight: 700;
}

.overtime-card .card-subtitle {
    font-size: 0.76rem;
    opacity: 0.95;
}

.overtime-card .card-time {
    font-size: 0.72rem;
    padding: 0.22rem 0.55rem;
}

.overtime-card .card-body {
    padding: 0.95rem 1rem;
}

.overtime-card .card-info {
    font-size: 0.82rem;
}

.overtime-card .card-footer {
    padding: 0.75rem 1rem;
}

.overtime-card .btn-action {
    height: 34px;
    border-radius: 9px;
    font-size: 0.75rem;
    font-weight: 700;
    border: 1px solid #d7deea;
    background: var(--hcis-slate-50);
}

.overtime-card .btn-action.btn-view {
    color: var(--hcis-indigo-600);
}

.overtime-card .btn-action.btn-delete {
    color: var(--hcis-red-600);
}

.assignment-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 180px;
}

.assignment-cell small {
    line-height: 1.25;
}

.detail-reason-wrap {
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: break-word;
    line-height: 1.35;
}

.jenis-cell {
    display: inline-flex;
    align-items: center;
    min-width: 170px;
}

.jenis-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.14rem 0.5rem;
    border-radius: 999px;
    border: 1px solid var(--hcis-indigo-200);
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
    font-size: 0.66rem;
    font-weight: 600;
    line-height: 1.1;
    max-width: 170px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.admin-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.14rem 0.5rem;
    border-radius: 999px;
    border: 1px solid var(--hcis-amber-300);
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-700);
    font-size: 0.66rem;
    font-weight: 600;
    line-height: 1.1;
    white-space: nowrap;
}

.cancellation-card {
    border: 1px solid var(--hcis-red-200);
    background: linear-gradient(135deg, var(--hcis-red-50) 0%, #fff5f5 100%);
    border-radius: 14px;
    padding: 1rem 1.15rem;
    box-shadow: 0 1px 2px rgba(220, 38, 38, 0.06);
}

.cancellation-card-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: var(--hcis-red-700);
    font-size: 0.95rem;
}

.cancellation-card-head i {
    font-size: 1.05rem;
}

.cancellation-reason {
    margin: 0.6rem 0 0;
    color: var(--hcis-red-900);
    font-size: 0.88rem;
    line-height: 1.45;
}

.cancellation-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.6rem;
    margin-top: 0.85rem;
    padding-top: 0.85rem;
    border-top: 1px dashed var(--hcis-red-300);
}

.cancellation-meta-item {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    color: var(--hcis-red-800);
}

.cancellation-meta-item i {
    font-size: 1rem;
    flex-shrink: 0;
}

.cancellation-meta-item div {
    display: flex;
    flex-direction: column;
    line-height: 1.25;
}

.cancellation-meta-item small {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: #b45757;
}

.cancellation-meta-item span {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--hcis-red-900);
}

.stitch-modal-header {
    border-bottom: 1px solid var(--hcis-slate-200);
}

.stitch-modal-title {
    font-size: 1.02rem;
}

.section-title {
    font-size: 1.02rem;
    color: var(--hcis-slate-700);
}

.form-label {
    font-size: 0.82rem;
    color: var(--hcis-slate-600);
}

.form-control {
    font-size: 0.86rem;
}

.alert,
.policy-hint-box {
    border-radius: 12px;
}

.step-label {
    font-size: 0.78rem;
    letter-spacing: 0.2px;
}

.transaction-summary-card {
    border: 1px solid #dce5f2;
    border-left: 4px solid var(--hcis-blue-500);
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
    padding: 0.95rem 1rem;
}

.transaction-summary-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.8rem;
}

.summary-title-wrap {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
}

.summary-title-wrap i {
    color: var(--hcis-indigo-600);
    font-size: 1.15rem;
    line-height: 1;
    margin-top: 1px;
}

.summary-title-wrap small {
    color: var(--hcis-slate-500);
    font-size: 0.79rem;
}

.summary-title-wrap h5 {
    margin: 0.12rem 0 0;
    font-size: 1.16rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
    letter-spacing: 0.2px;
}

.summary-status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    padding: 0.28rem 0.62rem;
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.35px;
    text-transform: uppercase;
    white-space: nowrap;
}

.summary-status-badge.status-pending {
    color: var(--hcis-amber-800);
    background: var(--hcis-amber-100);
}

.summary-status-badge.status-approved {
    color: #166534;
    background: var(--hcis-green-100);
}

.summary-status-badge.status-rejected {
    color: var(--hcis-red-800);
    background: var(--hcis-red-100);
}

.summary-status-badge.status-cancelled {
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-200);
}

.transaction-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem 1rem;
}

.summary-field label {
    display: block;
    margin: 0 0 0.15rem;
    color: var(--hcis-slate-500);
    font-size: 0.74rem;
    font-weight: 600;
}

.summary-field p {
    margin: 0;
    color: var(--hcis-slate-700);
    font-size: 0.87rem;
    font-weight: 600;
}

@media (max-width: 768px) {
    .riwayat-page-head {
        align-items: stretch;
        flex-direction: column;
    }

    .btn-refresh {
        width: 100%;
    }

    .riwayat-page-head__title h4 {
        font-size: 0.96rem;
    }

    .riwayat-page-head__title p {
        font-size: 0.77rem;
    }

    .transaction-summary-grid {
        grid-template-columns: 1fr;
    }

    .summary-title-wrap h5 {
        font-size: 1.02rem;
    }
}
</style>

<style scoped>
/* Design System Variables */
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
    --radius-lg: 16px;
    --transition: all 0.3s ease;
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

.date-display {
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

/* File Upload Container */
.file-upload-container {
    margin-top: 0.5rem;
}

/* Upload Card */
.file-upload-card {
    border: 2px dashed var(--border);
    border-radius: var(--radius-lg);
    padding: 2.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: var(--surface-soft);
    position: relative;
    overflow: hidden;
}

.file-upload-card:hover {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.02);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.file-upload-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(99, 102, 241, 0.05), transparent);
    transition: left 0.5s ease;
}

.file-upload-card:hover::before {
    left: 100%;
}

.upload-icon {
    font-size: 3rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.upload-content h4 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-weight: 600;
}

.upload-content p {
    margin: 0 0 1.5rem 0;
    color: var(--text-muted);
}

.file-requirements {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: center;
}

.requirement-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    font-size: 0.85rem;
}

.requirement-item i {
    color: var(--success);
}

/* File Preview Card */
.file-preview-card {
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    background: var(--surface);
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.file-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1.5rem;
}

.file-info {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    flex: 1;
}

.file-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.file-details {
    flex: 1;
}

.file-name {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-weight: 600;
    word-break: break-word;
}

.file-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.file-size,
.file-type {
    padding: 0.25rem 0.75rem;
    background: var(--surface-soft);
    border-radius: 20px;
    font-size: 0.8rem;
    color: var(--text-muted);
    font-weight: 500;
}

.upload-status {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--success);
    font-size: 0.8rem;
    font-weight: 500;
}

.upload-status i {
    font-size: 0.9rem;
}

/* File Actions */
.file-actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}

.btn-action {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: var(--radius);
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-preview {
    background: var(--info);
    color: white;
}

.btn-preview:hover {
    background: var(--hcis-cyan-600);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
}

.btn-replace {
    background: var(--warning);
    color: white;
}

.btn-replace:hover {
    background: var(--hcis-amber-600);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.btn-remove-action {
    background: var(--danger);
    color: white;
}

.btn-remove-action:hover {
    background: #c23030;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

/* Upload Progress */
.upload-progress {
    margin-top: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.progress-bar {
    flex: 1;
    height: 6px;
    background: var(--surface-soft);
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success), #22c55e);
    border-radius: 3px;
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-weight: 600;
    min-width: 40px;
}

/* Validation & Requirements */
.requirement-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 0.5rem;
}

.requirement-badge.required {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.requirement-badge:not(.required) {
    background: rgba(100, 116, 139, 0.1);
    color: var(--secondary);
    border: 1px solid rgba(100, 116, 139, 0.3);
}

.validation-error {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--danger);
    font-size: 0.85rem;
    margin-top: 0.5rem;
    padding: 0.75rem;
    background: rgba(239, 68, 68, 0.05);
    border-radius: var(--radius);
    border-left: 3px solid var(--danger);
}

/* Responsive Design */
@media (max-width: 768px) {
    .file-upload-card {
        padding: 2rem 1.5rem;
    }

    .file-header {
        flex-direction: column;
        gap: 1rem;
    }

    .file-actions {
        width: 100%;
        justify-content: flex-end;
    }

    .file-meta {
        gap: 0.5rem;
    }

    .btn-action span {
        display: none;
    }

    .btn-action {
        padding: 0.5rem;
    }
}

/* Base Styles */
.cuti-app-container {
    min-height: 100vh;
    /* background: var(--light); */
}

.main-content-wrapper {
    padding: 0.5rem 0;
}

.content-container {
    /* max-width: 1400px; */
    margin: 0 auto;
    /* padding: 0 1rem; */
}

/* Tab Content */
.tab-content-wrapper {
    /* padding: 1.5rem 0; */
}

/* Form Layout */
.form-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    align-items: start;
}

.form-main-section {
    min-height: 500px;
}

.form-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 2rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.form-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--surface-soft);
}

.form-icon-wrapper {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.form-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
    margin: 0;
}

/* Form Elements */
.form-field-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: flex;
    align-items: center;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
}

.form-textarea {
    width: 100%;
    padding: 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: var(--transition);
    resize: vertical;
    min-height: 120px;
    font-family: inherit;
}

.form-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.date-picker-trigger {
    cursor: pointer;
}

.date-display {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    transition: var(--transition);
}

.date-display.disabled {
    background: #eaeaea !important;
    cursor: not-allowed;
}

.date-display:hover {
    border-color: var(--primary);
}

.placeholder {
    color: var(--text-muted);
    background: none;
}

.selected-dates {
    color: var(--text);
    font-weight: 500;
}

/* File Upload */
.file-upload-wrapper {
    margin-top: 0.5rem;
}

.file-upload-area {
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
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
    border-radius: var(--radius);
    padding: 1rem;
    margin-top: 1rem;
}

.file-name {
    flex: 1;
    font-weight: 500;
}

.btn-remove {
    background: none;
    border: none;
    color: var(--danger);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: var(--transition);
}

.btn-remove:hover {
    background: var(--danger);
    color: white;
}

/* Validation & Alerts */
.validation-section {
    margin: 1.5rem 0;
}

.alert {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    border-radius: var(--radius);
    margin-bottom: 0.75rem;
}

.alert.error {
    background: rgba(239, 68, 68, 0.1);
    border-left: 4px solid var(--danger);
    color: var(--danger);
}

.alert.info {
    background: rgba(6, 182, 212, 0.1);
    border-left: 4px solid var(--info);
    color: var(--info);
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border);
}

/* Sidebar */
.form-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    position: sticky;
    top: 1rem;
}

.info-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.info-header h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.duration-display {
    text-align: center;
}

.duration-value {
    display: block;
    font-size: 2.5rem;
    font-weight: 700;
    line-height: 1;
}

.duration-label {
    display: block;
    font-size: 0.9rem;
    opacity: 0.9;
    margin-top: 0.5rem;
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
    color: var(--warning);
    margin-top: 0.125rem;
    flex-shrink: 0;
}

.tip-item span {
    font-size: 0.9rem;
    line-height: 1.4;
    color: var(--text);
}

.info-card.highlight .tip-item span {
    color: rgba(255, 255, 255, 0.9);
}

/* Riwayat Styles */
.riwayat-container {
    /* padding: 1rem 0; */
}

/* .filter-section {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow);
} */

.filter-grid {
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 1rem;
    margin-bottom: 1rem;
}

.search-field {
    position: relative;
    display: flex;
    align-items: center;
}

.search-field i {
    position: absolute;
    left: 1rem;
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
    transition: var(--transition);
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.filter-select {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    color: var(--text);
    cursor: pointer;
    transition: var(--transition);
    min-width: 150px;
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary);
}

.filter-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
}

.filter-info {
    color: var(--text-muted);
    font-weight: 500;
}

/* Stats Overview */
.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border: 1px solid var(--border);
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.stat-card.total {
    border-left: 4px solid var(--primary);
}

.stat-card.approved {
    border-left: 4px solid var(--success);
}

.stat-card.pending {
    border-left: 4px solid var(--warning);
}

.stat-card.rejected {
    border-left: 4px solid var(--danger);
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
    background: linear-gradient(135deg, var(--success), var(--hcis-emerald-600));
}

.stat-card.pending .stat-icon {
    background: linear-gradient(135deg, var(--warning), var(--hcis-amber-600));
}

.stat-card.rejected .stat-icon {
    background: linear-gradient(135deg, var(--danger), var(--hcis-red-600));
}

.stat-content h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
}

.stat-content p {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.9rem;
}

/* Riwayat List */
.riwayat-list {
    margin-bottom: 2rem;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: 0.5;
}

.empty-state h4 {
    margin-bottom: 1rem;
    color: var(--text);
}

.empty-state p {
    margin-bottom: 2rem;
}

.riwayat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 1.5rem;
}

.riwayat-item {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    border: 1px solid var(--border);
    transition: var(--transition);
    box-shadow: var(--shadow);
}

.riwayat-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.riwayat-item.status-approved {
    border-left: 4px solid var(--success);
}

.riwayat-item.status-pending {
    border-left: 4px solid var(--warning);
}

.riwayat-item.status-rejected {
    border-left: 4px solid var(--danger);
}

.riwayat-item.status-cancelled {
    border-left: 4px solid var(--secondary);
}

.riwayat-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.cuti-type {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.type-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.type-info h4 {
    margin: 0 0 0.25rem 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
}

.duration {
    background: var(--primary-light);
    color: var(--primary);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-badge {
    font-size: 0.8rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
}

.riwayat-content {
    margin-bottom: 1.5rem;
}

.date-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--surface-soft);
    border-radius: var(--radius);
    margin-bottom: 1rem;
}

.date-info i {
    color: var(--info);
    font-size: 1.1rem;
}

.date-details {
    flex: 1;
}

.date-range {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.25rem;
}

.date-full {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.reason-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.reason-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    font-size: 0.85rem;
    font-weight: 600;
}

.reason-label i {
    color: var(--primary);
}

.reason-text {
    margin: 0;
    color: var(--text);
    line-height: 1.6;
    font-size: 0.9rem;
    padding: 0.75rem;
    background: var(--surface);
    border-radius: var(--radius);
    border-left: 3px solid var(--primary);
}

.riwayat-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}

.meta-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    font-size: 0.8rem;
}

.meta-info i {
    color: var(--primary);
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-action {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: var(--radius);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
}

.btn-action.primary {
    background: var(--primary);
    color: white;
}

.btn-action.primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.btn-action.danger {
    background: var(--danger);
    color: white;
}

.btn-action.danger:hover {
    background: #c23030;
    transform: translateY(-1px);
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    background: var(--surface);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
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
    border-radius: var(--radius);
    background: var(--surface);
    color: var(--text);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition);
}

.pagination-btn:hover:not(:disabled) {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
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
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition);
    font-weight: 500;
    color: var(--text);
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
.mobile-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    background: var(--surface);
}

.btn-back {
    background: none;
    border: none;
    font-size: 1.25rem;
    color: var(--text);
    cursor: pointer;
    padding: 0.5rem;
}

.mobile-modal-title {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
}

.header-spacer {
    width: 40px;
}

.btn-confirm {
    background: var(--primary);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: var(--radius);
    font-weight: 600;
    cursor: pointer;
}

/* Detail Modal */
.detail-content {
    padding: 0.5rem;
}

.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.detail-section {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.detail-label {
    display: flex;
    align-items: center;
    font-weight: 600;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.detail-value {
    color: var(--text);
    font-size: 1rem;
}

.detail-meta {
    color: var(--text-muted);
    font-size: 0.85rem;
    margin-left: 0.5rem;
}

.reason-text {
    background: var(--surface-soft);
    padding: 1rem;
    border-radius: var(--radius);
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
    border-radius: var(--radius);
    transition: var(--transition);
}

.file-link:hover {
    background: var(--primary);
    color: white;
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

/* Button Styles */
.btn {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
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

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.btn-primary {
    background: var(--primary);
    color: white;
    border: 1px solid var(--primary);
}

.btn-primary:hover:not(:disabled) {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-secondary {
    background: var(--surface);
    color: var(--text);
    border: 1px solid var(--border);
}

.btn-secondary:hover:not(:disabled) {
    background: var(--surface-soft);
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-danger {
    background: var(--danger);
    color: white;
    border: 1px solid var(--danger);
}

.btn-danger:hover:not(:disabled) {
    background: #c23030;
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-outline {
    background: transparent;
    color: var(--text);
    border: 1px solid var(--border);
}

.btn-outline:hover:not(:disabled) {
    background: var(--surface-soft);
    transform: translateY(-1px);
}

/* Animations */
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

/* Mobile Responsive */
@media (max-width: 1024px) {
    .form-layout {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .form-sidebar {
        position: static;
    }

    .riwayat-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    /* .content-container {
        padding: 0 0.75rem;
    } */

    .mobile-modal-header {
        padding: 0;
        border: none;
    }
    .form-card {
        padding: 1.5rem;
    }

    .filter-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .stats-overview {
        grid-template-columns: repeat(2, 1fr);
    }

    .riwayat-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }

    .riwayat-footer {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }

    .action-buttons {
        width: 100%;
        justify-content: flex-end;
    }

    .pagination {
        /* flex-direction: column; */
        gap: 1rem;
        text-align: center;
    }

    .detail-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .mobile-fullscreen {
        width: 100% !important;
        margin: 0 !important;
        max-height: 100vh;
    }
}

@media (max-width: 480px) {
    /* .content-container {
        padding: 0 0.5rem;
    } */

    .form-card {
        padding: 1rem;
    }

    .stats-overview {
        grid-template-columns: 1fr;
    }

    .form-actions {
        flex-direction: column;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }

    .riwayat-item {
        padding: 1rem;
    }

    .cuti-type {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
}

/* Utility Classes */
.d-none {
    display: none !important;
}

.me-2 {
    margin-right: 0.5rem;
}

.cursor-pointer {
    cursor: pointer;
}

.text-center {
    text-align: center;
}

.justify-content-center {
    justify-content: center;
}

.align-items-center {
    align-items: center;
}

.d-flex {
    display: flex;
}

.w-100 {
    width: 100%;
}

/* V-Select Custom Styles */
:deep(.modern-select .vs__dropdown-toggle) {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    color: var(--text);
    font-size: 0.95rem;
    transition: var(--transition);
    min-height: 48px;
}

:deep(.modern-select.vs--open .vs__dropdown-toggle) {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

:deep(.modern-select .vs__dropdown-menu) {
    border: 2px solid var(--border);
    border-radius: var(--radius);
    margin-top: 4px;
    box-shadow: var(--shadow);
}

:deep(.modern-select .vs__dropdown-option) {
    padding: 0.75rem 1rem;
    color: var(--text);
    font-size: 0.95rem;
}

:deep(.modern-select .vs__dropdown-option--highlight) {
    background: var(--primary);
    color: white;
}

/* Element Plus Overrides */
/* :deep(.el-dialog) {
    border-radius: var(--radius-lg);
    overflow: hidden;
}

:deep(.el-dialog__header) {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    margin: 0;
}

:deep(.el-dialog__body) {
    padding: 1.5rem;
}

:deep(.el-dialog__footer) {
    padding: 1.5rem;
    border-top: 1px solid var(--border);
}

:deep(.el-calendar) {
    border: none;
}

:deep(.el-calendar__header) {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
}

:deep(.el-calendar__body) {
    padding: 1rem;
}

:deep(.el-step__head) {
    width: 32px;
    height: 32px;
}

:deep(.el-step__title) {
    font-size: 0.9rem;
    font-weight: 600;
}

:deep(.el-step__description) {
    font-size: 0.8rem;
    color: var(--text-muted);
} */
</style>

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
    --danger-light: var(--hcis-legacy-bootstrap-danger); /* Merah muda, lebih terang untuk latar belakang atau efek disabled */
    --gray-light: var(--hcis-legacy-bootstrap-gray);
    --el-slider-main-bg-color: var(--hcis-indigo-500);
    --el-calendar-cell-width: 50px !important;
}

/* .is-selected {
    background-color: #409eff !important;
    color: white;
    border-radius: 4px;
} */

.is-terlambat {
    background-color: rgba(239, 68, 68, 0.15); /* Merah transparan */
    color: var(--hcis-red-600); /* Merah lebih gelap */
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.is-pulang-cepat {
    background-color: rgba(245, 158, 11, 0.15); /* Kuning transparan */
    color: var(--hcis-amber-600); /* Kuning lebih gelap */
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.is-sakit {
    background-color: rgba(100, 116, 139, 0.15); /* Secondary transparan */
    color: var(--hcis-slate-600); /* Secondary lebih gelap */
    border: 1px solid rgba(100, 116, 139, 0.2);
}

.is-izin {
    background-color: rgba(6, 182, 212, 0.15); /* Info transparan */
    color: var(--hcis-cyan-600); /* Info lebih gelap */
    border: 1px solid rgba(6, 182, 212, 0.2);
}

.is-cuti-bersama {
    background-color: rgba(253, 246, 236, 0.8); /* Orange sangat transparan */
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: var(--hcis-amber-700); /* Orange gelap */
}

.is-cuti-pribadi {
    background-color: rgba(34, 197, 94, 0.15); /* Hijau transparan */
    color: var(--hcis-green-600); /* Hijau lebih gelap */
    border: 1px solid rgba(34, 197, 94, 0.2);
}

.is-special-dates {
    background-color: rgba(254, 240, 240, 0.8); /* Merah sangat transparan */
    color: var(--hcis-red-600); /* Merah gelap */
    border: 1px solid rgba(239, 68, 68, 0.2);
}

/* Optional: Tambahkan efek hover untuk interaktivitas */
.is-terlambat:hover,
.is-pulang-cepat:hover,
.is-sakit:hover,
.is-izin:hover,
.is-cuti-bersama:hover,
.is-special-dates:hover,
.is-cuti-pribadi:hover {
    opacity: 0.9;
    transition: opacity 0.2s ease;
}

/* Pastikan div pembungkus memenuhi kotak kalender */
.calendar-cell-content {
    height: 100%;
    width: 100%;
    padding: 5px;
    box-sizing: border-box;
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
    border-radius: 12px;
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

.employee-shift-name {
    margin-top: 2px;
    font-size: 0.78rem;
    font-weight: 600;
    color: #5b6b83;
    line-height: 1.2;
}

.employee-shift-time {
    display: inline-block;
    margin-top: 2px;
    font-size: 0.72rem;
    color: #7b8aa0;
    line-height: 1.2;
}

.employee-name-mobile-wrap {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.employee-name-mobile-main {
    font-weight: 600;
    color: #2c3e50;
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

/* info bar */
.leave-info-panel {
}

/* Selected Date Styles */
.cell-date-wrapper.is-selected .date-cell {
    /* background: linear-gradient(135deg, var(--primary), var(--primary-dark)); */
    background: var(--primary);
    color: white;
    font-weight: 700;
    /* box-shadow: var(--shadow); */

    /* box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4); */
    transform: scale(1.05);
    border: 2px solid white;
    position: relative;
    z-index: 2;
}

/* Hover effect for selected dates */
.cell-date-wrapper.is-selected .date-cell:hover {
    background: linear-gradient(135deg, var(--primary-dark), var(--hcis-indigo-700));
    transform: scale(1.08);
    box-shadow: 0 6px 16px rgba(99, 102, 241, 0.6);
}

/* Optional: Add a subtle pulse animation for selected dates */
@keyframes selectedPulse {
    0% {
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
    }
    50% {
        box-shadow: 0 4px 16px rgba(99, 102, 241, 0.4);
    }
    100% {
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
    }
}

.cell-date-wrapper.is-selected .date-cell {
    animation: selectedPulse 2s ease-in-out infinite;
}

/* Alternative simpler version without animation */
/*
.cell-date-wrapper.is-selected .date-cell {
    background: var(--primary);
    color: white;
    font-weight: 600;
    border: 2px solid white;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
}
*/

/* For better visual hierarchy with other states */
.cell-date-wrapper.is-selected.is-today .date-cell {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-color: white;
}

.cell-date-wrapper.is-selected.is-weekend .date-cell {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}

.cell-date-wrapper.is-weekend .date-cell {
    /* color:  */
    color: var(--danger);
}

/* Ensure selected dates stand out from disabled dates */
.cell-date-wrapper.is-selected .date-cell.is-disabled {
    background: var(--secondary) !important;
    color: var(--light) !important;
    opacity: 0.6;
    transform: none;
    box-shadow: none;
    animation: none;
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
    .el-dialog__body {
        padding: 0 !important;
    }
    .el-dialog {
        margin-bottom: 0 !important;
    }
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
    /* overflow: hidden; */
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
    /* background: var(--surface); */
    border-radius: 20px;
    /* overflow: hidden; */
    /* box-shadow: var(--shadow); */
    /* box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); */
    /* border: 1px solid var(--border); */
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
    /* background: rgba(255, 255, 255, 0.2); */
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

/* Calendar Modals (Element Plus specific overrides) */
.el-overlay {
    z-index: 1050;
}
.el-dialog__body {
    padding-top: 10px;
    padding-bottom: 10px;
}
.el-calendar-table .el-calendar-day {
    padding: 2px !important;
    height: 50px !important;
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
    content: '';
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
    /* .el-dialog.is-align-center {
        margin: auto 1rem;
    } */
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
    .el-calendar__header {
        flex-direction: column;
        justify-content: center;
        align-items: start;
    }
    .el-calendar__title {
        align-self: center;
        font-size: 15px;
        font-weight: 600;
    }
    .el-dialog {
        border-top-left-radius: 20px;
        border-top-right-radius: 20px;
    }
    .el-calendar__button-group {
        width: 100%;
    }

    .el-button-group {
        width: 100%;
        display: flex;
    }

    .el-button.el-button--small {
        flex: 1;
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
        /* margin: 0.5rem; */
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

/* =========================================
   PREMIUM DESIGN SYSTEM
   ========================================= */

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

:root {
    /* Color Palette - User Provided (Indigo Theme) */
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
    --radius-sm: 8px;
    --radius: 12px;
    --radius-md: 12px;
    --radius-lg: 16px;
    --transition: all 0.3s ease;

    /* Gradients (Derived) */
    --gradient-primary: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
}

.tengahkan {
    display: flex;
    justify-content: center;
    align-items: center;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 52px;
    height: 28px;
}

/* Hide default checkbox */
.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

/* Track */
.slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background-color: #d1d5db; /* gray */
    border-radius: 999px;
    transition: background-color 0.3s ease;
}

/* Thumb */
.slider::before {
    content: '';
    position: absolute;
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background-color: #fff;
    border-radius: 50%;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
    transition: transform 0.3s ease;
}

/* Checked */
.toggle-switch input:checked + .slider {
    background-color: var(--hcis-indigo-600); /* indigo */
}

.toggle-switch input:checked + .slider::before {
    transform: translateX(24px);
}

/* Optional focus */
.toggle-switch input:focus + .slider {
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.3);
}

/* Compact toggle (small) */
.toggle-switch-sm {
    position: relative;
    display: inline-block;
    width: 36px;
    height: 20px;
    cursor: pointer;
}
.toggle-switch-sm input {
    opacity: 0;
    width: 0;
    height: 0;
}
.slider-sm {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background-color: #d1d5db;
    border-radius: 999px;
    transition: background-color 0.3s ease;
}
.slider-sm::before {
    content: '';
    position: absolute;
    height: 14px;
    width: 14px;
    left: 3px;
    bottom: 3px;
    background-color: #fff;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    transition: transform 0.3s ease;
}
.toggle-switch-sm input:checked + .slider-sm {
    background-color: var(--hcis-indigo-600);
}
.toggle-switch-sm input:checked + .slider-sm::before {
    transform: translateX(16px);
}

/* Label text */
.toggle-label {
    margin-left: 10px;
    font-size: 14px;
    font-weight: 600;
    color: #4b5563;
}

/* Universal Icon Centering */
.bi {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

/* =========================================
   UTILITY CLASSES (Tailwind Mimic)
   ========================================= */
.text-primary {
    color: var(--primary) !important;
}
.text-secondary {
    color: var(--secondary) !important;
}
.text-success {
    color: var(--success) !important;
}
.text-danger {
    color: var(--danger) !important;
}
.text-warning {
    color: var(--warning) !important;
}
.text-muted {
    color: var(--text-muted) !important;
}
.text-dark {
    color: var(--text) !important;
}

.bg-primary {
    background-color: var(--primary) !important;
}
.bg-primary-subtle {
    background-color: var(--hcis-indigo-100) !important;
    color: var(--primary-dark) !important;
}
.bg-surface {
    background-color: var(--surface) !important;
}
.bg-light {
    background-color: var(--surface-soft) !important;
}

.rounded-lg {
    border-radius: var(--radius) !important;
}
.rounded-xl {
    border-radius: var(--radius-lg) !important;
}
.rounded-full {
    border-radius: 9999px !important;
}

.shadow-sm {
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
}
.shadow {
    box-shadow: var(--shadow) !important;
}
.shadow-lg {
    box-shadow: var(--shadow-lg) !important;
}

.border {
    border: 1px solid var(--border) !important;
}
.border-0 {
    border: none !important;
}

.flex {
    display: flex !important;
}
.flex-col {
    flex-direction: column !important;
}
.items-center {
    align-items: center !important;
}
.justify-between {
    justify-content: space-between !important;
}
.gap-2 {
    gap: 0.5rem !important;
}
.gap-4 {
    gap: 1rem !important;
}

.p-4 {
    padding: 1rem !important;
}
.py-2 {
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
}
.px-4 {
    padding-left: 1rem !important;
    padding-right: 1rem !important;
}

.w-full {
    width: 100% !important;
}
.h-full {
    height: 100% !important;
}

.font-bold {
    font-weight: 700 !important;
}
.font-medium {
    font-weight: 500 !important;
}
.text-sm {
    font-size: 0.875rem !important;
}
.text-xs {
    font-size: 0.75rem !important;
}

/* =========================================
   GLOBAL LAYOUT & TYPOGRAPHY
   ========================================= */

.cuti-app-container {
    background-color: var(--bg-container);
    min-height: 100vh;
    padding-bottom: 2rem;
    /* font-family: 'Inter', system-ui, -apple-system, sans-serif; */
    color: var(--text);
}

.main-content-wrapper {
    /* max-width: 1400px; */
    /* margin: -40px auto 0; Overlap header slightly for premium look */
    /* padding: 0 1.5rem; */
    position: relative;
    z-index: 10;
}

.content-container {
    /* background: var(--surface); */
    /* border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg); */
    min-height: 500px;
    overflow: hidden;
    /* border: 1px solid var(--border); */
}

/* =========================================
   COMPONENTS
   ========================================= */

/* Buttons */
.btn-modern {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    font-weight: 600;
    font-size: 0.875rem;
    border-radius: 12px !important;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    cursor: pointer;
}

.modal-footer .btn-modern,
.modal-footer-actions .btn-modern,
.detail-modal-footer .btn-modern,
.delete-modal-footer .btn-modern,
.stitch-modal-footer .btn-modern {
    border-radius: 12px !important;
    padding: 0.625rem 1.25rem !important;
    font-weight: 600 !important;
    font-size: 0.875rem !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.5rem !important;
    transition: all 0.2s ease !important;
}

.modal-footer .btn-modern.btn-secondary,
.modal-footer-actions .btn-modern.btn-secondary,
.delete-modal-footer .btn-modern.btn-secondary {
    background: var(--hcis-slate-100) !important;
    color: var(--hcis-slate-600) !important;
    border: 1px solid var(--hcis-slate-300) !important;
}

.modal-footer .btn-modern.btn-secondary:hover,
.modal-footer-actions .btn-modern.btn-secondary:hover,
.delete-modal-footer .btn-modern.btn-secondary:hover {
    background: var(--hcis-slate-200) !important;
    color: var(--hcis-slate-800) !important;
    border-color: var(--hcis-slate-400) !important;
    transform: translateY(-1px);
}

.modal-footer .btn-modern.btn-primary,
.modal-footer-actions .btn-modern.btn-primary,
.btn-modern.btn-primary {
    background: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%) !important;
    color: white !important;
    border: none !important;
    box-shadow: 0 4px 6px -1px rgba(102, 126, 234, 0.25) !important;
}

.modal-footer .btn-modern.btn-primary:hover,
.modal-footer-actions .btn-modern.btn-primary:hover,
.btn-modern.btn-primary:hover {
    filter: brightness(108%);
    transform: translateY(-1px);
    box-shadow: 0 6px 12px -1px rgba(102, 126, 234, 0.35) !important;
}

.modal-footer .btn-modern.btn-success,
.modal-footer-actions .btn-modern.btn-success,
.btn-modern.btn-success {
    background: linear-gradient(135deg, var(--hcis-emerald-500) 0%, var(--hcis-emerald-600) 100%) !important;
    color: white !important;
    border: none !important;
    box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.25) !important;
}

.modal-footer .btn-modern.btn-success:hover,
.modal-footer-actions .btn-modern.btn-success:hover,
.btn-modern.btn-success:hover {
    filter: brightness(108%);
    transform: translateY(-1px);
    box-shadow: 0 6px 12px -1px rgba(16, 185, 129, 0.35) !important;
}

.modal-footer .btn-modern.btn-danger,
.modal-footer-actions .btn-modern.btn-danger,
.delete-modal-footer .btn-modern.btn-danger,
.btn-modern.btn-danger {
    background: linear-gradient(135deg, var(--hcis-red-500) 0%, var(--hcis-red-600) 100%) !important;
    color: white !important;
    border: none !important;
    box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.25) !important;
}

.modal-footer .btn-modern.btn-danger:hover,
.modal-footer-actions .btn-modern.btn-danger:hover,
.delete-modal-footer .btn-modern.btn-danger:hover,
.btn-modern.btn-danger:hover {
    filter: brightness(108%);
    transform: translateY(-1px);
    box-shadow: 0 6px 12px -1px rgba(239, 68, 68, 0.35) !important;
}

.btn-icon {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-sm);
    color: var(--text-muted);
    transition: all 0.2s;
    background: transparent;
    border: none;
}

.btn-icon:hover {
    background: var(--surface-soft);
    color: var(--primary);
}

/* Filter Section */
.filter-section {
    padding: 1.5rem;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    margin-bottom: 0 !important; /* Override template mb-4 */
}

.nav-group {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    padding: 0.25rem;
    border-radius: var(--radius-sm);
}

/* =========================================
   CALENDAR GRID SYSTEM
   ========================================= */

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1rem;
    padding: 1.5rem;
    background: var(--surface-soft);
}

.calendar-cell {
    background: var(--surface);
    border-radius: var(--radius);
    min-height: 180px;
    padding: 1rem;
    border: 1px solid var(--border);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    position: relative;
    display: flex;
    flex-direction: column;
}

.calendar-cell:hover {
    border-color: var(--primary-light);
    transform: translateY(-4px);
    box-shadow: var(--shadow);
    z-index: 2;
}

.calendar-cell.is-today {
    border-color: var(--primary);
    background: linear-gradient(to bottom right, var(--hcis-violet-50), var(--hcis-white));
}

.calendar-cell.is-backdate {
    opacity: 0.7;
    background: #fcfcfc;
    cursor: default;
}

.calendar-cell.is-backdate:hover {
    transform: none;
    box-shadow: none;
    border-color: var(--border);
}

/* Cell Header */
.cell-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.day-name {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--text-muted);
    letter-spacing: 0.05em;
}

.date-circle {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--text);
}

.date-circle.current-date {
    background: var(--primary);
    color: white;
    box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3);
}

/* Shift Content within Cell */
.shift-info-card {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 0.75rem;
    text-align: center;
    margin-top: auto;
}

.shift-time-badge {
    background: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--primary-dark);
    display: inline-block;
    margin-bottom: 0.5rem;
    border: 1px solid var(--border);
}

.shift-name {
    font-weight: 600;
    color: var(--text);
    font-size: 0.875rem;
    display: block;
}

.empty-shift-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    opacity: 0.4;
    transition: 0.2s;
}

.calendar-cell:hover .empty-shift-state {
    opacity: 0.8;
}

.dashed-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px dashed var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
}

.calendar-cell:hover .dashed-circle {
    border-color: var(--primary);
    color: var(--primary);
    background: rgba(99, 102, 241, 0.05); /* Tint */
}

/* =========================================
   HISTORY TAB & TABLES
   ========================================= */

.tab-content-improved {
    padding: 0;
}

/* Reuse styling from Reference KuotaManagement */
.modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.modern-table th {
    background: var(--surface-soft);
    padding: 1rem;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    color: var(--text-muted);
    letter-spacing: 0.05em;
    border-bottom: 1px solid var(--border);
}

.modern-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    color: var(--text);
    vertical-align: middle;
}

.modern-table tr:hover td {
    background-color: var(--surface-soft);
}

/* =========================================
   MODALS & FORMS
   ========================================= */

.detail-row {
    display: flex;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.detail-row:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.detail-label {
    width: 140px;
    color: var(--text-muted);
    font-weight: 500;
}

.detail-value {
    flex: 1;
    color: var(--text);
    font-weight: 500;
}

/* Responsive */
@media (max-width: 1024px) {
    .calendar-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 768px) {
    /* .main-content-wrapper {
        margin-top: -20px;
        padding: 0 1rem;
    } */

    .calendar-grid {
        grid-template-columns: repeat(2, 1fr);
        padding: 1rem;
        gap: 0.5rem;
    }

    .calendar-cell {
        min-height: 140px;
    }

    .filter-section {
        flex-direction: column;
        gap: 1rem;
    }
}

@media (max-width: 480px) {
    .calendar-grid {
        grid-template-columns: 1fr;
    }
}
/* =========================================
   MODALS & FORMS EXTRA
   ========================================= */

/* Form Groups & Inputs */
.form-field-group {
    margin-bottom: 1.25rem;
}

.form-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 0.5rem;
    display: block;
}

.form-label-sm {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.35rem;
    display: block;
}

.form-control,
.form-textarea {
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 0.625rem 0.875rem;
    font-size: 0.9rem;
    color: var(--text);
    background: var(--surface);
    transition: all 0.2s;
    width: 100%;
}

.form-control:focus,
.form-textarea:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); /* Primary ring */
    outline: none;
}

/* Custom Checkbox Card */
.form-check-card {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all 0.2s;
}

.form-check-card:hover {
    border-color: var(--primary-light);
    background: white;
}

.form-check-input {
    margin-top: 0.25rem;
    cursor: pointer;
}

/* v-select Overrides for Premium Look */
.modern-select .vs__dropdown-toggle {
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 4px 0; /* Adjust internal padding */
    background: var(--surface);
    transition: all 0.2s;
}

.modern-select .vs__search::placeholder {
    color: var(--text-light);
}

.modern-select.vs--open .vs__dropdown-toggle {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    border-bottom-color: transparent;
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
}

.modern-select .vs__dropdown-menu {
    border: 1px solid var(--primary);
    border-top: none;
    box-shadow: var(--shadow-lg);
    border-radius: 0 0 var(--radius-sm) var(--radius-sm);
    padding: 0;
}

.modern-select .vs__dropdown-option {
    padding: 8px 12px;
    color: var(--text);
}

.modern-select .vs__dropdown-option--highlight {
    background: var(--primary);
    color: white;
}

/* Wizard Steps */
.wizard-steps {
    display: flex;
    padding: 0;
    margin-bottom: 2rem;
    position: relative;
}

.step {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 0.5rem;
    opacity: 0.6;
    transition: all 0.3s;
    position: relative;
    z-index: 1;
}

.step::after {
    content: '';
    position: absolute;
    top: 14px;
    left: 50%;
    width: 100%;
    height: 2px;
    background: var(--border);
    z-index: -1;
}

.step:last-child::after {
    display: none;
}

.step.active,
.step.completed {
    opacity: 1;
}

.step.completed::after {
    background: var(--success);
}

.step-number {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--surface);
    border: 2px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
    color: var(--text-muted);
    transition: all 0.3s;
}

.step.active .step-number {
    border-color: var(--primary);
    background: var(--primary);
    color: white;
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.2);
}

.step.completed .step-number {
    border-color: var(--success);
    background: var(--success);
    color: white;
}

.step-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: block; /* Force display */
}

/* Type Selection Cards */
.type-selection {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.type-card {
    padding: 2rem 1.5rem;
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: var(--radius);
    text-align: center;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.type-card:hover {
    border-color: var(--primary-light);
    transform: translateY(-4px);
    box-shadow: var(--shadow);
}

.type-card.selected {
    border-color: var(--primary);
    background: linear-gradient(to bottom right, var(--hcis-indigo-50), var(--hcis-white));
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
}

.type-card .icon-wrapper {
    width: 64px;
    height: 64px;
    background: var(--surface-soft);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--text-muted);
    transition: all 0.2s;
}

.type-card.selected .icon-wrapper {
    background: var(--primary);
    color: white;
}

.type-card h6 {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: var(--text);
}

/* Date Selection List */
.selected-dates-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.selected-date-item {
    background: var(--surface);
    border: 1px solid var(--border);
    padding: 0.5rem 0.75rem;
    border-radius: 2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 0.2s;
}

.selected-date-item:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow-sm);
}

/* Review Summary */
.review-summary {
    background: var(--surface-soft);
    padding: 1.5rem;
    border-radius: var(--radius);
    border: 1px solid var(--border);
}

/* =========================================
   VERTICAL ROSTER LIST STYLES
   ========================================= */
.vertical-roster-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    transition: all 0.2s ease;
    gap: 1rem;
}

.vertical-roster-card:hover {
    box-shadow: var(--shadow);
    z-index: 10;
}

.vertical-roster-card.opacity-60 {
    opacity: 0.6;
}

.date-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 70px;
    padding-right: 1rem;
    border-right: 1px solid var(--border);
}

.content-col {
    flex: 1;
}

.actions-col {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.badge-status {
    background: var(--hcis-emerald-100);
    color: var(--hcis-emerald-800);
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    text-transform: uppercase;
}

.add-shift-btn-dashed {
    width: 100%;
    height: 3rem;
    border: 2px dashed var(--border);
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    color: var(--text-muted);
    background: transparent;
    transition: all 0.2s;
}

.add-shift-btn-dashed:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: rgba(99, 102, 241, 0.05); /* Tint */
}

@media (max-width: 640px) {
    .vertical-roster-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .date-col {
        border-right: none;
        border-bottom: 1px solid var(--border);
        width: 100%;
        padding-right: 0;
        padding-bottom: 0.5rem;
        flex-direction: row;
        justify-content: space-between;
    }

    .actions-col {
        width: 100%;
        justify-content: flex-end;
        border-top: 1px solid var(--border);
        padding-top: 0.5rem;
    }
}

/* =========================================
   STITCH DESIGN SYSTEM - COMPREHENSIVE STYLES
   ========================================= */

/* Additional Utility Classes */
.grid {
    display: grid !important;
}
.grid-cols-1 {
    grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
}
.grid-cols-3 {
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
}
.grid-cols-7 {
    grid-template-columns: repeat(7, minmax(0, 1fr)) !important;
}
.grid-cols-12 {
    grid-template-columns: repeat(12, minmax(0, 1fr)) !important;
}
.col-span-1 {
    grid-column: span 1 / span 1 !important;
}
.col-span-2 {
    grid-column: span 2 / span 2 !important;
}
.col-span-3 {
    grid-column: span 3 / span 3 !important;
}
.col-span-5 {
    grid-column: span 5 / span 5 !important;
}

.gap-3 {
    gap: 0.75rem !important;
}
.gap-6 {
    gap: 1.5rem !important;
}
.gap-8 {
    gap: 2rem !important;
}

.p-1 {
    padding: 0.25rem !important;
}
.p-2 {
    padding: 0.5rem !important;
}
.p-5 {
    padding: 1.25rem !important;
}
.p-6 {
    padding: 1.5rem !important;
}
.p-8 {
    padding: 2rem !important;
}
.px-3 {
    padding-left: 0.75rem !important;
    padding-right: 0.75rem !important;
}
.px-6 {
    padding-left: 1.5rem !important;
    padding-right: 1.5rem !important;
}
.py-3 {
    padding-top: 0.75rem !important;
    padding-bottom: 0.75rem !important;
}
.py-4 {
    padding-top: 1rem !important;
    padding-bottom: 1rem !important;
}
.pl-1 {
    padding-left: 0.25rem !important;
}

.mb-1 {
    margin-bottom: 0.25rem !important;
}
.mb-2 {
    margin-bottom: 0.5rem !important;
}
.mb-4 {
    margin-bottom: 1rem !important;
}
.mb-6 {
    margin-bottom: 1.5rem !important;
}
.mt-auto {
    margin-top: auto !important;
}

.text-lg {
    font-size: 1.125rem !important;
    line-height: 1.75rem !important;
}
.text-xl {
    font-size: 1.25rem !important;
    line-height: 1.75rem !important;
}
.text-2xl {
    font-size: 1.5rem !important;
    line-height: 2rem !important;
}
.text-3xl {
    font-size: 1.875rem !important;
    line-height: 2.25rem !important;
}
.font-semibold {
    font-weight: 600 !important;
}
.font-extrabold {
    font-weight: 800 !important;
}
.uppercase {
    text-transform: uppercase !important;
}
.tracking-wider {
    letter-spacing: 0.05em !important;
}
.tracking-tight {
    letter-spacing: -0.025em !important;
}
.leading-tight {
    line-height: 1.25 !important;
}

.text-left {
    text-align: left !important;
}
.text-center {
    text-align: center !important;
}
.text-right {
    text-align: right !important;
}

.flex-1 {
    flex: 1 1 0% !important;
}
.flex-wrap {
    flex-wrap: wrap !important;
}
.items-start {
    align-items: flex-start !important;
}
.items-end {
    align-items: flex-end !important;
}
.justify-center {
    justify-content: center !important;
}
.justify-end {
    justify-content: flex-end !important;
}

.relative {
    position: relative !important;
}
.absolute {
    position: absolute !important;
}
.sticky {
    position: sticky !important;
}
.top-0 {
    top: 0 !important;
}
.z-10 {
    z-index: 10 !important;
}
.z-50 {
    z-index: 50 !important;
}

.overflow-hidden {
    overflow: hidden !important;
}
.overflow-x-auto {
    overflow-x: auto !important;
}

.transition-all {
    transition: all 0.2s ease !important;
}
.transition-colors {
    transition:
        color 0.2s,
        background-color 0.2s,
        border-color 0.2s !important;
}

.cursor-pointer {
    cursor: pointer !important;
}
.select-none {
    user-select: none !important;
}

.opacity-50 {
    opacity: 0.5 !important;
}
.opacity-60 {
    opacity: 0.6 !important;
}

.w-8 {
    width: 2rem !important;
}
.w-10 {
    width: 2.5rem !important;
}
.w-12 {
    width: 3rem !important;
}
.h-8 {
    height: 2rem !important;
}
.h-10 {
    height: 2.5rem !important;
}
.h-11 {
    height: 2.75rem !important;
}
.h-12 {
    height: 3rem !important;
}
.min-w-70 {
    min-width: 70px !important;
}

.hidden {
    display: none !important;
}

/* Color Extensions */
.bg-success {
    background-color: var(--success) !important;
}
.bg-warning {
    background-color: var(--warning) !important;
}
.bg-secondary {
    background-color: var(--secondary) !important;
}

.bg-blue-50 {
    background-color: #eff6ff !important;
}
.bg-purple-50 {
    background-color: #faf5ff !important;
}
.bg-orange-50 {
    background-color: #fff7ed !important;
}
.bg-green-50 {
    background-color: var(--hcis-green-50) !important;
}
.bg-yellow-50 {
    background-color: var(--hcis-yellow-50) !important;
}
.bg-red-50 {
    background-color: var(--hcis-red-50) !important;
}
.bg-gray-50 {
    background-color: #f9fafb !important;
}
.bg-gray-100 {
    background-color: #f3f4f6 !important;
}

.text-green-600 {
    color: var(--hcis-green-600) !important;
}
.text-green-700 {
    color: var(--hcis-green-700) !important;
}
.text-yellow-700 {
    color: var(--hcis-yellow-700) !important;
}
.text-red-600 {
    color: var(--hcis-red-600) !important;
}
.text-red-700 {
    color: var(--hcis-red-700) !important;
}
.text-purple-600 {
    color: var(--hcis-purple-600) !important;
}
.text-orange-600 {
    color: var(--hcis-orange-600) !important;
}
.text-gray-400 {
    color: #9ca3af !important;
}
.text-gray-500 {
    color: #6b7280 !important;
}

.border-green-200 {
    border-color: var(--hcis-green-200) !important;
}
.border-yellow-200 {
    border-color: var(--hcis-yellow-200) !important;
}
.border-red-200 {
    border-color: var(--hcis-red-200) !important;
}
.border-primary {
    border-color: var(--primary) !important;
}

.ring-1 {
    box-shadow: 0 0 0 1px !important;
}
.ring-primary-light {
    --tw-ring-color: var(--primary-light) !important;
    box-shadow: 0 0 0 2px var(--primary-light) !important;
}

/* =========================================
   STITCH CARD COMPONENTS
   ========================================= */
/* Summary Cards Grid - Responsive */
.summary-cards-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

@media (max-width: 991px) {
    .summary-cards-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 575px) {
    .summary-cards-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
}

/* Date Navigation Responsive */
.date-nav-wrapper {
    display: flex;
    flex-direction: row !important;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.date-nav-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.date-nav-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.date-nav-label {
    font-size: 0.9375rem;
}

.add-shift-btn {
    box-shadow: 0 4px 12px -2px rgba(99, 102, 241, 0.3);
}

@media (max-width: 640px) {
    .date-nav-wrapper {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
        padding: 0.75rem !important;
    }

    .date-nav-left {
        width: 100%;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .date-nav-left > div:first-child {
        width: 100%;
        justify-content: space-between;
    }

    .date-nav-right {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }

    .today-btn,
    .add-shift-btn {
        justify-content: center;
        padding: 0.625rem !important;
        width: 100%;
        border-radius: 12px;
    }

    .date-nav-wrapper .form-control {
        height: 40px;
        border-radius: 12px;
    }

    .date-nav-wrapper .add-shift-btn {
        height: 42px;
        font-size: 0.9rem;
    }

    .date-nav-label {
        font-size: 0.8125rem;
    }
}

@media (max-width: 400px) {
    .today-btn {
        display: none !important;
    }

    .date-nav-right {
        grid-template-columns: 1fr;
    }
}

.stitch-card {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    border-radius: var(--radius);
    background: var(--surface);
    padding: 1.25rem;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    border: 1px solid var(--border);
}

/* Roster List Container */
.roster-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

@media (max-width: 575px) {
    .roster-list {
        gap: 0.5rem;
    }
}

.stitch-card-icon {
    padding: 0.5rem;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stitch-card-icon.blue {
    background: #eff6ff;
    color: var(--primary);
}
.stitch-card-icon.purple {
    background: #faf5ff;
    color: var(--hcis-purple-600);
}
.stitch-card-icon.orange {
    background: #fff7ed;
    color: var(--hcis-orange-600);
}
.stitch-card-icon.green {
    background: var(--hcis-green-50);
    color: var(--success);
}

/* =========================================
   STITCH ROSTER LIST
   ========================================= */
.stitch-roster-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--surface);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    transition: box-shadow 0.2s ease;
}

.stitch-roster-item:hover {
    box-shadow: var(--shadow);
}

.stitch-roster-item.today {
    border-color: var(--primary);
    box-shadow: 0 0 0 2px var(--primary-light);
}

.stitch-roster-item.weekend {
    background: var(--surface-soft);
    opacity: 0.7;
}

/* Roster Content Layout */
.roster-content {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.shift-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.shift-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.shift-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.shift-name {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text);
    margin: 0;
}

.shift-meta {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--text-muted);
    font-size: 0.8125rem;
    font-weight: 500;
}

.shift-time,
.shift-location {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.roster-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Mobile Roster Styles */
@media (max-width: 640px) {
    .stitch-roster-item {
        flex-direction: column;
        align-items: stretch;
        padding: 0.75rem;
        gap: 0.75rem;
    }

    .stitch-date-box {
        display: none;
        flex-direction: row;
        justify-content: flex-start;
        align-items: center;
        gap: 0.5rem;
        padding: 0 0 0.5rem 0;
        border-right: none;
        border-bottom: 1px solid var(--border);
        min-width: unset;
    }

    .stitch-date-day {
        font-size: 0.625rem;
    }

    .stitch-date-num {
        font-size: 1.25rem;
    }

    .roster-content {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }

    .shift-info {
        flex: 1;
    }

    .stitch-shift-indicator {
        width: 4px;
        height: 100%;
        min-height: 36px;
    }

    .shift-name {
        font-size: 0.9375rem;
    }

    .shift-meta {
        font-size: 0.75rem;
        gap: 0.75rem;
    }

    .hide-mobile {
        display: none !important;
    }

    .roster-actions {
        justify-content: flex-end;
        padding-top: 0.5rem;
        border-top: 1px dashed var(--border);
    }

    .roster-actions .stitch-action-btn {
        width: 32px;
        height: 32px;
    }
}

.stitch-date-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 100px;
    padding-right: 1rem;
    /* border-right: 1px solid var(--border); */
}

.stitch-date-day {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
}

.stitch-date-day.weekend {
    color: var(--danger);
}

.stitch-date-num {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text);
}

.stitch-shift-indicator {
    width: 6px;
    height: 48px;
    border-radius: 9999px;
}

.stitch-shift-indicator.green {
    background: var(--success);
}
.stitch-shift-indicator.yellow {
    background: var(--warning);
}
.stitch-shift-indicator.blue {
    background: var(--primary);
}
.stitch-shift-indicator.purple {
    background: var(--hcis-purple-600);
}

.status-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.2rem 0.5rem;
    border-radius: 9999px;
    border: 1px solid transparent;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.status-pill-wrap {
    white-space: normal;
    line-height: 1.1;
    text-align: center;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
}

.status-pill.badge {
    border: none;
}

.status-pill.bg-danger-subtle {
    background: #fff1f2 !important;
    color: var(--hcis-rose-600) !important;
    border-color: #fecdd3 !important;
}

.status-pill.bg-success-subtle {
    background: #ecfdf3 !important;
    color: var(--hcis-green-700) !important;
    border-color: var(--hcis-green-200) !important;
}

.shift-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.shift-time-pill {
    font-size: 0.55rem;
    padding: 0.2rem 0.45rem;
    border-radius: 0.4rem;
    box-shadow: none;
}

.status-subtext {
    font-size: 0.6rem;
    color: var(--hcis-red-600);
    line-height: 1.1;
    text-align: center;
    max-width: 100%;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.detail-status-stack {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
}

.detail-status-chip {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.2px;
    border: 1px solid transparent;
    line-height: 1.2;
    white-space: nowrap;
}

.detail-status-chip.is-success {
    background: #e8f9f1;
    color: #0f9f6e;
    border-color: #b9ecd4;
}

.detail-status-chip.is-warning {
    background: #fff5df;
    color: #b7791f;
    border-color: #fde3b0;
}

.detail-status-chip.is-info {
    background: #e8f1ff;
    color: #2f63d8;
    border-color: #c7d9ff;
}

.detail-status-chip.is-danger {
    background: #ffe9e9;
    color: #d64545;
    border-color: #ffc4c4;
}

.detail-status-note {
    font-size: 11px;
    color: #6f7f94;
    line-height: 1.3;
}

.mobile-week-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.5rem;
}

.mobile-day-card {
    min-height: 92px;
}

.mobile-day-card .status-pill {
    font-size: 0.5rem;
    padding: 0.15rem 0.35rem;
}

.mobile-day-card .shift-badge {
    font-size: 0.65rem;
}

@media (max-width: 480px) {
    .mobile-week-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .mobile-day-card {
        min-height: 86px;
        padding: 0.4rem !important;
    }
}

@media (max-width: 640px) {
    .mobile-member-card {
        border-radius: 16px;
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
    }

    .mobile-member-card.is-expanded {
        border-color: #dbeafe;
        box-shadow: 0 10px 20px rgba(59, 130, 246, 0.12);
    }

    .mobile-member-header {
        padding: 0.75rem !important;
        gap: 0.75rem !important;
    }

    .mobile-member-header.is-expanded {
        background: var(--hcis-slate-50);
    }

    .mobile-member-avatar {
        display: none !important;
    }

    .mobile-member-info h6 {
        font-size: 0.95rem;
        line-height: 1.2;
    }

    .mobile-member-info {
        flex: 1;
        min-width: 0;
    }

    .mobile-member-info .text-xs {
        font-size: 0.7rem;
        line-height: 1.2;
        margin-top: 0.15rem;
    }

    .mobile-member-divider {
        height: 1px;
        width: 100%;
        background: #e5e7eb;
        margin: 0.45rem 0;
    }

    .mobile-member-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.6rem;
        font-weight: 700;
        color: #1d4ed8;
        background: #eff6ff;
        border: 1px solid #dbeafe;
        border-radius: 9999px;
        padding: 0.15rem 0.5rem;
        width: fit-content;
    }

    .mobile-toggle-btn {
        width: 40px;
        height: 40px;
        padding: 0;
        border-radius: 9999px;
        background: var(--hcis-slate-50);
        border: 1px solid var(--hcis-slate-200);
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
    }

    .mobile-toggle-btn i {
        font-size: 1.05rem;
        color: var(--hcis-slate-600);
        transition: transform 0.2s ease;
    }

    .mobile-member-card.is-expanded .mobile-toggle-btn i {
        transform: rotate(180deg);
    }

    .mobile-member-card + .mobile-member-card {
        margin-top: 0.75rem;
    }

    .mobile-week-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.6rem;
    }

    .mobile-day-card {
        min-height: 110px;
        padding: 0.6rem !important;
        border-radius: 12px;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
    }

    .mobile-day-card .text-xxs {
        font-size: 0.6rem;
    }

    .mobile-day-card .shift-badge {
        font-size: 0.7rem;
    }

    .mobile-day-card .status-subtext {
        font-size: 0.55rem;
        line-height: 1.15;
    }
}

/* Shift Source Badge */
.shift-source {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    /* padding: 0.125rem 0.5rem; */
    border-radius: 4px;
    font-size: 0.6875rem;
    font-weight: 600;
    text-transform: capitalize;
}

.shift-source.source-permanen {
    background: #dbeafe;
    color: #1e40af;
}

.shift-source.source-sementara {
    background: var(--hcis-orange-100);
    color: #c2410c;
}

/* Stitch Action Buttons */
.stitch-action-btn {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.stitch-action-btn:hover {
    border-color: var(--primary-light);
    background: #eff6ff;
    color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.stitch-action-btn.danger {
    border-color: var(--hcis-red-200);
    background: #fddede;
    color: var(--danger);
}

.stitch-action-btn.secondary {
    border-color: #e5e7eb;
    background: #f9fafb;
    color: var(--text-muted);
}

.stitch-action-btn.danger:hover {
    border-color: var(--hcis-red-200);
    background: #fdd0d0;
    color: var(--danger);
}

.stitch-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.stitch-badge.approved {
    background: var(--hcis-green-100);
    color: #166534;
}

.stitch-badge.pending {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-800);
}

.stitch-badge.rejected {
    background: var(--hcis-red-100);
    color: var(--hcis-red-800);
}

.stitch-badge.cancelled {
    background: #e5e7eb;
    color: #374151;
}

/* =========================================
   STITCH FILTER & TABLE
   ========================================= */
.stitch-filter {
    background: var(--surface);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    padding: 1.25rem;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    margin-bottom: 1.5rem;
}

.stitch-filter-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 0.375rem;
}

.stitch-input {
    width: 100%;
    height: 2.75rem;
    padding: 0 0.75rem;
    background: var(--surface-soft);
    border: 1px solid transparent;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    color: var(--text);
    transition: all 0.2s;
}

.stitch-input:focus {
    outline: none;
    border-color: var(--primary);
    background: var(--surface);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
}

.stitch-input::placeholder {
    color: var(--text-muted);
}

.stitch-table {
    width: 100%;
    text-align: left;
    border-collapse: collapse;
}

.stitch-table thead tr {
    background: var(--surface-soft);
    border-bottom: 1px solid var(--border);
}

.stitch-table th {
    padding: 1rem 1.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
}

.stitch-table tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background-color 0.2s;
}

.stitch-table tbody tr:hover {
    background: var(--surface-soft);
}

.stitch-table td {
    padding: 1rem 1.5rem;
    font-size: 0.875rem;
    color: var(--text);
}

/* =========================================
   ELEMENT PLUS DATE PICKER CUSTOMIZATION
   ========================================= */
.date-picker-wrapper {
    display: flex;
    justify-content: center;
    margin-bottom: 1rem;
}

.el-date-picker-full {
    width: 100% !important;
}

.el-date-picker-full .el-input__wrapper {
    width: 100%;
    padding: 0.75rem 1rem;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: var(--surface);
    box-shadow: none !important;
    transition: all 0.2s ease;
}

.el-date-picker-full .el-input__wrapper:hover,
.el-date-picker-full .el-input__wrapper.is-focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light) !important;
}

.el-date-picker-full .el-input__inner {
    font-size: 0.875rem;
    color: var(--text);
}

.el-date-picker-full .el-input__prefix,
.el-date-picker-full .el-input__suffix {
    color: var(--text-muted);
}

.el-time-picker-full {
    width: 100% !important;
}

.el-time-picker-full .el-input__wrapper {
    width: 100%;
    padding: 0.75rem 1rem;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: var(--surface);
    box-shadow: none !important;
    transition: all 0.2s ease;
}

.el-time-picker-full .el-input__wrapper:hover,
.el-time-picker-full .el-input__wrapper.is-focus,
.el-time-picker-full.is-focus .el-input__wrapper {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light) !important;
}

.el-time-picker-full .el-input__inner {
    font-size: 0.875rem;
    color: var(--text);
}

.el-time-picker-full .el-input__prefix,
.el-time-picker-full .el-input__suffix {
    color: var(--text-muted);
}

.el-time-picker-full.el-date-editor--timerange {
    width: 100% !important;
}

.el-time-picker-full.el-date-editor--timerange .el-range-input {
    font-size: 0.875rem;
    color: var(--text);
}

.el-time-picker-full.el-date-editor--timerange .el-range-separator {
    color: var(--text-muted);
}

.time-range-grid {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 0.5rem;
    align-items: center;
}

.time-range-separator {
    font-size: 0.9rem;
    color: var(--text-muted);
    font-weight: 600;
    text-align: center;
}

.policy-hint-box {
    background: var(--hcis-slate-50);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0.65rem 0.75rem;
}

.policy-hint-title {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 0.35rem;
}

.policy-hint-list {
    margin: 0;
    padding-left: 1.05rem;
}

.policy-hint-list li {
    font-size: 0.78rem;
    color: var(--text-muted);
    line-height: 1.35;
    margin-bottom: 0.2rem;
}

.step3-schedule-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0.5rem 0.65rem;
}

.step3-schedule-summary .summary-item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.78rem;
    color: var(--text-muted);
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: 0.25rem 0.6rem;
    line-height: 1.2;
}

.step3-schedule-summary .summary-item i {
    color: var(--primary);
}

.review-section {
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--surface-soft);
    padding: 0.9rem;
}

.review-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.6rem;
}

.review-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0.65rem 0.75rem;
}

.review-label {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.review-value {
    font-size: 0.88rem;
    color: var(--text);
    font-weight: 600;
    line-height: 1.25;
}

.review-reason-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0.65rem 0.75rem;
}

.review-reason-text {
    font-size: 0.86rem;
    color: var(--text);
    line-height: 1.4;
    word-break: break-word;
    white-space: pre-wrap;
}

.review-employee-list {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}

.review-employee-header {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    background: var(--surface-soft);
    border-bottom: 1px solid var(--border);
    padding: 0.55rem 0.75rem;
}

.review-employee-items {
    max-height: 240px;
    overflow-y: auto;
}

.review-employee-item {
    padding: 0.6rem 0.75rem;
    border-bottom: 1px solid var(--border);
}

.review-employee-item:last-child {
    border-bottom: none;
}

.review-emp-name {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--text);
}

.review-emp-meta,
.review-emp-time {
    font-size: 0.78rem;
    color: var(--text-muted);
    line-height: 1.35;
}

.employee-actual-duration {
    margin-top: 0.1rem;
    font-size: 0.75rem;
    color: var(--hcis-slate-500);
}

/* Popper/Dropdown Styles */
.el-picker__popper {
    border-radius: var(--radius) !important;
    border: 1px solid var(--border) !important;
    box-shadow: var(--shadow-lg) !important;
}

.el-picker-panel {
    border: none !important;
}

.el-date-picker__header {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--border);
}

.el-date-picker__header-label {
    font-weight: 600;
    color: var(--text);
}

.el-picker-panel__icon-btn {
    color: var(--text-muted);
}

.el-picker-panel__icon-btn:hover {
    color: var(--primary);
}

.el-date-table th {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
}

.el-date-table td.available:hover {
    color: var(--primary);
}

.el-date-table td.today .el-date-table-cell__text {
    color: var(--primary);
    font-weight: 700;
}

.el-date-table td.current:not(.disabled) .el-date-table-cell__text,
.el-date-table td.selected .el-date-table-cell__text {
    background-color: var(--primary) !important;
    color: white !important;
    border-radius: 50%;
}

.el-date-table td.disabled .el-date-table-cell__text {
    color: var(--text-muted);
    opacity: 0.4;
}

/* =========================================
   ELEMENT PLUS SELECT CUSTOMIZATION
   ========================================= */
.el-select-full {
    width: 100% !important;
}

.el-select-full .el-input__wrapper {
    padding: 0.5rem 0.875rem;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: var(--surface);
    box-shadow: none !important;
    transition: all 0.2s ease;
}

.el-select-full .el-input__wrapper:hover,
.el-select-full .el-input.is-focus .el-input__wrapper {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light) !important;
}

.el-select-full .el-input__inner {
    font-size: 0.875rem;
    color: var(--text);
}

.el-select-full .el-input__suffix {
    color: var(--text-muted);
}

/* High z-index for select popper (teleported to body) */
.el-select-popper-high {
    z-index: 99999 !important;
}

.el-select-popper-high .el-select-dropdown__item {
    font-size: 0.875rem;
    padding: 0.625rem 1rem;
}

.el-select-popper-high .el-select-dropdown__item.selected {
    color: var(--primary);
    font-weight: 600;
}

.el-select-popper-high .el-select-dropdown__item:hover {
    background: var(--surface-soft);
}

/* High z-index for date picker popper (teleported to body) */
.el-date-picker-popper-high {
    z-index: 99999 !important;
}

.el-time-picker-popper-high {
    z-index: 99999 !important;
}

@media (max-width: 576px) {
    .time-range-grid {
        grid-template-columns: 1fr auto 1fr;
        gap: 0.35rem;
    }

    .time-range-separator {
        padding: 0;
        font-size: 0.85rem;
    }

    .el-time-picker-full .el-input__wrapper {
        padding: 0.6rem 0.75rem;
    }

    .step3-schedule-summary {
        gap: 0.4rem;
        padding: 0.45rem 0.5rem;
    }

    .step3-schedule-summary .summary-item {
        font-size: 0.72rem;
        padding: 0.22rem 0.5rem;
    }

    .review-section {
        padding: 0.7rem;
    }

    .review-summary-grid {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }

    .review-card,
    .review-reason-box {
        padding: 0.55rem 0.6rem;
    }

    .review-value {
        font-size: 0.82rem;
    }

    .review-employee-header {
        font-size: 0.74rem;
        padding: 0.5rem 0.6rem;
    }

    .review-employee-item {
        padding: 0.55rem 0.6rem;
    }

    .review-emp-name {
        font-size: 0.84rem;
    }

    .review-emp-meta,
    .review-emp-time {
        font-size: 0.72rem;
    }

    .policy-hint-box {
        padding: 0.55rem 0.6rem;
    }

    .policy-hint-title {
        font-size: 0.75rem;
    }

    .policy-hint-list li {
        font-size: 0.72rem;
    }
}

/* =========================================
   STITCH WIZARD STEPPER
   ========================================= */
.stitch-stepper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    max-width: 600px;
    margin: 0 auto 2rem;
}

.stitch-stepper::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--border);
    transform: translateY(-50%);
    z-index: 0;
}

.stitch-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    position: relative;
    z-index: 1;
}

.stitch-step-circle {
    width: 2rem;
    height: 2rem;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
    background: var(--surface);
    border: 2px solid var(--border);
    color: var(--text-muted);
    box-shadow: 0 0 0 4px var(--surface);
}

.stitch-step.completed .stitch-step-circle {
    background: var(--success);
    border-color: var(--success);
    color: white;
}

.stitch-step.active .stitch-step-circle {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
    box-shadow:
        0 0 0 4px var(--surface),
        0 0 0 6px rgba(99, 102, 241, 0.2);
}

.stitch-step-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
}

.stitch-step.completed .stitch-step-label {
    color: var(--success);
}

.stitch-step.active .stitch-step-label {
    color: var(--primary);
    font-weight: 700;
}

/* =========================================
   STITCH TYPE CARDS (Wizard Step 1)
   ========================================= */
.stitch-type-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.5rem;
}

.stitch-type-card {
    padding: 2rem 1.5rem;
    border-radius: 1rem;
    border: 2px solid var(--border);
    background: var(--surface);
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.stitch-type-card:hover {
    border-color: var(--primary-light);
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.stitch-type-card.selected {
    border-color: var(--primary);
    background: linear-gradient(to bottom right, var(--hcis-indigo-50), var(--surface));
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
}

.stitch-type-icon {
    width: 5rem;
    height: 5rem;
    border-radius: 9999px;
    background: var(--surface-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--text-muted);
    transition: all 0.2s;
}

.stitch-type-card.selected .stitch-type-icon {
    background: var(--primary);
    color: white;
}

.stitch-type-card .stitch-type-icon i {
    font-size: 2.5rem;
}

/* =========================================
   STITCH DATE CHIPS (Wizard Step 2)
   ========================================= */
/* Dates Summary Section */
.dates-summary-section {
    margin-top: 1rem;
}

.dates-summary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    gap: 0.5rem;
}

.dates-count {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text);
}

.clear-dates-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--danger);
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-200);
    border-radius: 0.25rem;
    cursor: pointer;
    transition: all 0.2s;
}

.clear-dates-btn:hover {
    background: var(--hcis-red-100);
    border-color: var(--hcis-red-300);
}

.clear-dates-btn i {
    font-size: 0.625rem;
}

.stitch-date-chips {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
    max-height: 200px;
    overflow-y: auto;
    padding: 0.25rem;
}

.stitch-date-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.5rem;
    background: var(--surface);
    border: 1px solid var(--primary-light);
    border-radius: 0.375rem;
    color: var(--primary);
    font-size: 0.75rem;
    font-weight: 600;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    white-space: nowrap;
    overflow: hidden;
}

.stitch-date-chip i {
    font-size: 0.625rem;
    flex-shrink: 0;
}

.stitch-date-chip span {
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
}

.stitch-date-chip button {
    background: transparent;
    border: none;
    color: inherit;
    cursor: pointer;
    padding: 0.125rem;
    border-radius: 9999px;
    transition: all 0.2s;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.stitch-date-chip button:hover {
    background: var(--hcis-red-100);
    color: var(--danger);
}

/* Mobile: Single column for date chips */
@media (max-width: 480px) {
    .stitch-date-chips {
        grid-template-columns: 1fr;
        max-height: 180px;
    }

    .stitch-type-card .stitch-type-icon i {
        font-size: 1.5rem;
    }

    .stitch-date-chip {
        font-size: 0.6875rem;
        padding: 0.3rem 0.4rem;
    }
}

/* =========================================
   STITCH MODAL ENHANCEMENTS
   ========================================= */
.stitch-modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.stitch-modal-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text);
}

.stitch-modal-body {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.stitch-modal-footer {
    padding: 1rem 1.5rem;
    background: var(--surface-soft);
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
}

.stitch-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem;
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
}

.stitch-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.625rem 1rem;
    font-weight: 700;
    font-size: 0.875rem;
    border-radius: 0.5rem;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
}

.stitch-btn.primary {
    background: var(--primary);
    color: white;
    box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.2);
}
/* 
.stitch-btn.primary:hover {
    background: var(--primary-dark);
} */

.stitch-btn.secondary {
    background: var(--surface);
    color: var(--text-muted);
    border: 1px solid var(--border);
}

.stitch-btn.secondary:hover {
    background: var(--surface-soft);
    color: var(--text);
}

.stitch-btn.danger {
    background: var(--danger);
    color: white;
    box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
}

.stitch-btn.danger:hover {
    background: var(--danger-dark);
    color: black;
    box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
    transition: all 0.2s;
    /* border: 0.1px solid var(--text-muted); */
}

/* =========================================
   RESPONSIVE OVERRIDES - TABLET (768px+)
   ========================================= */
@media (min-width: 768px) {
    .md\:grid-cols-3 {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    }
    .md\:grid-cols-12 {
        grid-template-columns: repeat(12, minmax(0, 1fr)) !important;
    }
    .md\:col-span-1 {
        grid-column: span 1 / span 1 !important;
    }
    .md\:col-span-2 {
        grid-column: span 2 / span 2 !important;
    }
    .md\:col-span-5 {
        grid-column: span 5 / span 5 !important;
    }
    .md\:flex {
        display: flex !important;
    }
}

/* =========================================
   RESPONSIVE OVERRIDES - MOBILE (< 768px)
   ========================================= */
@media (max-width: 767px) {
    /* Tab Content Padding */
    .p-6 {
        padding: 1rem !important;
    }

    /* Summary Cards - Single Column */
    .grid.grid-cols-3 {
        grid-template-columns: 1fr !important;
        gap: 0.75rem !important;
    }

    .stitch-card {
        padding: 1rem;
        gap: 0.25rem;
    }

    .stitch-card .text-2xl {
        font-size: 1.5rem !important;
    }

    .stitch-card-icon {
        width: 36px;
        height: 36px;
    }

    /* Date Navigation - Stack on Mobile */
    .stitch-card.sticky {
        flex-direction: row !important;
        gap: 0.75rem !important;
        padding: 0.75rem !important;
    }

    .stitch-card.sticky > div:first-child {
        flex-wrap: wrap;
        width: 100%;
        justify-content: space-between;
    }

    .stitch-card.sticky .text-lg {
        font-size: 0.875rem !important;
    }

    .stitch-card.sticky .stitch-btn {
        width: 100%;
        justify-content: center;
    }

    /* Hide secondary button on mobile */
    .stitch-btn.secondary.hidden.sm\:block {
        display: none !important;
    }

    /* Action Buttons - Smaller */
    .stitch-action-btn {
        width: 32px !important;
        height: 32px !important;
        font-size: 0.875rem !important;
    }

    /* Roster Items - Vertical Layout */
    .stitch-roster-item {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
        padding: 0.875rem;
    }

    .stitch-date-box {
        flex-direction: row;
        justify-content: flex-start;
        align-items: center;
        gap: 0.75rem;
        border-right: none;
        border-bottom: 1px solid var(--border);
        padding: 0 0 0.75rem 0;
        min-width: unset;
    }

    .stitch-date-day {
        font-size: 0.65rem;
    }

    .stitch-date-num {
        font-size: 1.25rem !important;
    }

    .stitch-roster-item .flex.items-center.gap-3:not(.stitch-date-box) {
        flex: 1;
    }

    .stitch-roster-item .flex.items-center.gap-2:last-child {
        justify-content: flex-end;
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 1px dashed var(--border);
    }

    .stitch-shift-indicator {
        width: 4px;
        height: 32px;
    }

    .stitch-roster-item .text-lg {
        font-size: 0.9375rem !important;
    }

    .stitch-roster-item .text-sm {
        font-size: 0.75rem !important;
    }

    /* Filter Section - Stack on Mobile */
    .stitch-filter {
        padding: 0.875rem !important;
    }

    .stitch-filter .grid {
        display: flex !important;
        flex-direction: column !important;
        gap: 0.75rem !important;
    }

    .stitch-filter input,
    .stitch-filter select {
        width: 100% !important;
    }

    /* Table - Card-like rows on mobile */
    .stitch-table {
        display: block;
    }

    .stitch-table thead {
        display: none;
    }

    .stitch-table tbody {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .stitch-table tr {
        display: flex;
        flex-direction: column;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 0.75rem;
    }

    .stitch-table td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.25rem 0;
        border: none;
    }

    .stitch-table td::before {
        content: attr(data-label);
        font-weight: 600;
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
    }

    .stitch-table td.text-right {
        justify-content: flex-end;
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 1px dashed var(--border);
    }

    /* Wizard Steps - Numbers only on mobile */
    .stitch-stepper {
        gap: 0.25rem;
    }

    .stitch-step-label {
        display: none;
    }

    .stitch-step-circle {
        width: 32px;
        height: 32px;
        font-size: 0.75rem;
    }

    .stitch-stepper::before {
        top: 16px;
    }

    /* Type Selection - Single Column */
    .stitch-type-grid {
        grid-template-columns: 1fr !important;
        gap: 0.75rem !important;
    }

    .stitch-type-card {
        padding: 1rem;
    }

    .stitch-type-icon {
        width: 48px;
        height: 48px;
        font-size: 1.25rem;
    }

    /* Date Chips - Responsive */
    .stitch-date-chips {
        gap: 0.5rem;
    }

    .stitch-date-chip {
        padding: 0.375rem 0.625rem;
        font-size: 0.75rem;
    }

    /* Modal Footer - Stack buttons on mobile */
    .stitch-modal-footer {
        flex-direction: column-reverse;
        gap: 0.5rem;
    }

    .stitch-modal-footer .stitch-btn {
        width: 100%;
        justify-content: center;
        margin: 0 !important;
    }

    /* History Page Header */
    .flex.flex-wrap.justify-between.items-end.gap-4.mb-6 {
        flex-direction: column;
        align-items: stretch !important;
    }

    .flex.flex-wrap.justify-between.items-end.gap-4.mb-6 .stitch-btn {
        width: 100%;
        justify-content: center;
    }

    /* Text sizes on mobile */
    .text-2xl {
        font-size: 1.25rem !important;
    }

    .text-xl {
        font-size: 1rem !important;
    }

    .text-lg {
        font-size: 0.9375rem !important;
    }

    /* Gaps on mobile */
    .gap-4 {
        gap: 0.75rem !important;
    }
    .gap-6 {
        gap: 1rem !important;
    }
    .mb-6 {
        margin-bottom: 1rem !important;
    }
    .mb-4 {
        margin-bottom: 0.75rem !important;
    }
}

/* =========================================
   RESPONSIVE OVERRIDES - SMALL MOBILE (< 480px)
   ========================================= */
@media (max-width: 479px) {
    .stitch-card {
        padding: 0.75rem;
        flex-direction: column;
    }

    .stitch-roster-item {
        padding: 0.75rem;
    }

    .stitch-date-num {
        font-size: 1.125rem !important;
    }

    .stitch-badge {
        font-size: 0.5rem;
        padding: 0.1rem 0.375rem;
    }

    .stitch-btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.8125rem;
    }

    .stitch-action-btn {
        width: 28px !important;
        height: 28px !important;
    }
}

/* =========================================
   DETAIL MODAL - TWO COLUMN LAYOUT
   ========================================= */
.detail-modal-content {
    padding: 0;
}

.detail-modal-grid {
    display: grid;
    grid-template-columns: 360px 1fr;
    min-height: 420px;
}

.detail-left-column {
    background: var(--surface-soft);
    padding: 1.25rem;
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.detail-right-column {
    padding: 0 1.5rem 0 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    overflow-y: auto;
    max-height: 500px;
}

/* Transaction Card - No Transaksi Highlight */
.detail-transaksi-card {
    text-align: center;
    padding: 1.25rem;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border-radius: var(--radius);
    margin-bottom: 0.5rem;
}

.detail-transaksi-number {
    font-size: 1.25rem;
    font-weight: 800;
    color: white;
    letter-spacing: 1px;
    margin-bottom: 0.5rem;
}

.detail-transaksi-type {
    margin-top: 0.5rem;
}

.type-badge {
    display: inline-block;
    padding: 0.375rem 0.875rem;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    color: white;
    backdrop-filter: blur(4px);
}

/* Status Section */
.detail-status-section {
    text-align: center;
}

.detail-status-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.75rem;
}

.detail-status-badge {
    display: inline-block;
    padding: 0.625rem 2rem;
    border-radius: 9999px;
    font-size: 0.875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-status-badge.approved {
    background: linear-gradient(135deg, var(--hcis-emerald-500) 0%, var(--hcis-emerald-600) 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.detail-status-badge.pending {
    background: linear-gradient(135deg, var(--hcis-amber-500) 0%, var(--hcis-amber-600) 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.detail-status-badge.rejected {
    background: linear-gradient(135deg, var(--hcis-red-500) 0%, var(--hcis-red-600) 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.detail-status-date {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
}

.detail-status-date i {
    font-size: 0.875rem;
}

/* Approval Section */
.detail-approval-section {
    background: white;
    border-radius: var(--radius);
    padding: 1rem;
    border: 1px solid var(--border);
}

.detail-section-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--primary);
    display: inline-block;
}

.detail-section-title i {
    color: var(--primary);
    margin-right: 0.25rem;
}

.detail-approval-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.detail-approval-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.detail-approval-indicator {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.75rem;
}

.detail-approval-indicator.approved {
    background: var(--success);
    color: white;
}

.detail-approval-indicator.rejected {
    background: var(--danger);
    color: white;
}

.detail-approval-indicator.pending {
    background: var(--border);
    color: var(--text-muted);
}

.detail-approval-info {
    flex: 1;
    min-width: 0;
}

.detail-approval-name {
    font-size: 0.875rem;
    font-weight: 600;
}

.detail-approval-name.text-success {
    color: var(--success);
}

.detail-approval-name.text-danger {
    color: var(--danger);
}

.detail-approval-date {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.detail-approval-empty {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--success);
}

/* Right Column Info Section */
.detail-info-section {
    background: white;
    border-radius: var(--radius);
    padding: 1rem 1.25rem;
    border: 1px solid var(--border);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.detail-info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 0.875rem 0;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.detail-info-row:first-child {
    padding-top: 0;
}

.detail-info-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.detail-info-label {
    font-size: 0.8125rem;
    color: var(--text-muted);
    flex-shrink: 0;
    margin-right: 1rem;
}

.detail-info-value {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--dark);
    text-align: right;
}

.detail-shift-date {
    font-weight: 700;
    color: var(--dark);
    font-size: 0.9375rem;
}

.detail-shift-name {
    color: var(--primary);
    font-size: 0.8125rem;
    font-weight: 500;
}

.detail-shift-time {
    font-size: 0.75rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.25rem;
    margin-top: 0.25rem;
}

.detail-alasan {
    font-style: italic;
    max-width: 220px;
    word-wrap: break-word;
    color: var(--text);
}

/* Detail Shift Card - Per-date card styling */
.detail-shift-card {
    background: white;
    border-radius: var(--radius);
    padding: 0.875rem 1rem;
    border: 1px solid var(--border);
    border-left-width: 4px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: all 0.2s ease;
}

.detail-shift-card:hover {
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.detail-shift-card.border-success {
    border-left-color: var(--success);
}

.detail-shift-card.border-danger {
    border-left-color: var(--danger);
}

.detail-shift-card.border-warning {
    border-left-color: var(--warning);
}

/* Status Text Colors */
.status-text-approved {
    color: var(--success);
    font-weight: 600;
}

.status-text-pending {
    color: var(--warning);
    font-weight: 600;
}

.status-text-rejected {
    color: var(--danger);
    font-weight: 600;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .detail-modal-grid {
        grid-template-columns: 1fr;
    }

    .detail-left-column {
        border-right: none;
        border-bottom: 1px solid var(--border);
    }

    .detail-right-column {
        max-height: none;
        padding: 0;
        margin-top: 1rem;
    }

    .detail-info-row {
        flex-direction: column;
        gap: 0.25rem;
    }

    .detail-info-value {
        text-align: left;
    }

    .detail-shift-time {
        justify-content: flex-start;
    }

    .detail-alasan {
        max-width: 100%;
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

/* Fade In Animation */
.fade-in {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
/* STITCH TABLE (UserShift Style + Premium) */
.stitch-table-wrapper {
    overflow-x: auto;
    width: 100%;
    /* border: 1px solid var(--border); */
    border-radius: var(--radius);
    background: white;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.stitch-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.stitch-table th,
.stitch-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.day-col,
.day-cell {
    width: 120px;
    min-width: 120px;
}

.stitch-table th {
    background: var(--surface-soft);
    position: sticky;
    top: 0;
    z-index: 20;
    border-bottom: 1px solid var(--border-dark, var(--hcis-slate-200));
}

.sticky-col {
    position: sticky;
    left: 0;
    background: white;
    z-index: 10;
    border-right: 1px solid var(--border);
    transition: all 0.2s;
}

.stitch-table th.sticky-col {
    z-index: 30; /* Higher than normal th and body sticky col */
    /* background: var(--surface-soft); */
}

.member-row:hover .sticky-col {
    background: var(--surface-soft);
}

@media (max-width: 1200px) {
    .day-col,
    .day-cell {
        width: 105px;
        min-width: 105px;
    }

    .stitch-table th,
    .stitch-table td {
        padding: 0.75rem;
    }
}

@media (max-width: 1024px) {
    .day-col,
    .day-cell {
        width: 96px;
        min-width: 96px;
    }
}

.member-row:hover td {
    background: var(--surface-soft);
}

.member-row:last-child td {
    border-bottom: none;
}

/* Pending Approval Badge in Calendar Cells */
.pending-approval-badge {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
    padding: 2px 8px;
    border-radius: 10px;
    background: rgba(245, 158, 11, 0.12);
    border: 1px solid rgba(245, 158, 11, 0.3);
    font-size: 10px;
    font-weight: 600;
    color: var(--hcis-amber-600);
    line-height: 1;
    cursor: default;
    transition: all 0.2s ease;
}

.pending-approval-badge:hover {
    background: rgba(245, 158, 11, 0.2);
    border-color: rgba(245, 158, 11, 0.5);
}

.pending-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--hcis-amber-500);
    animation: pendingPulse 2s ease-in-out infinite;
    flex-shrink: 0;
}

.pending-text {
    white-space: nowrap;
}

@keyframes pendingPulse {
    0%,
    100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.4;
        transform: scale(0.8);
    }
}

/* Shift Cell Interactions */
.shift-cell-hover .add-overlay {
    opacity: 0;
    transform: translate(-50%, -40%);
    transition: all 0.2s ease;
}

.shift-cell-hover:hover .add-overlay {
    opacity: 1;
    transform: translate(-50%, -50%);
}

.shift-cell-hover:hover .empty-placeholder {
    opacity: 0;
}

.shift-content-wrapper {
    border: 1px solid transparent;
}

.shift-cell-hover:hover .shift-content-wrapper {
    background: white;
    border-color: var(--primary-light);
    box-shadow: var(--shadow-sm);
}

/* COLORFUL SHIFT BADGES (userShift.vue style) */
.shift-morning {
    background: linear-gradient(135deg, var(--hcis-amber-100), var(--hcis-amber-300));
    color: var(--hcis-amber-800);
    border: none !important;
}

.shift-afternoon {
    background: linear-gradient(135deg, #ddd6fe, var(--hcis-violet-400));
    color: #5b21b6;
    border: none !important;
}

.shift-night {
    background: linear-gradient(135deg, #1f2937, #374151);
    color: white;
    border: none !important;
}

.shift-noshift {
    background: var(--surface-soft);
    color: var(--text-muted);
    border: 1px dashed var(--border) !important;
}

/* COLORFUL SHIFT BADGES (userShift.vue style) */
.shift-morning {
    background: linear-gradient(135deg, var(--hcis-amber-100), var(--hcis-amber-300));
    color: var(--hcis-amber-800);
    border: none !important;
}

.shift-afternoon {
    background: linear-gradient(135deg, #ddd6fe, var(--hcis-violet-400));
    color: #5b21b6;
    border: none !important;
}

.shift-night {
    background: linear-gradient(135deg, #1f2937, #374151);
    color: white;
    border: none !important;
}

.shift-noshift {
    background: var(--surface-soft);
    color: var(--text-muted);
    border: 1px dashed var(--border) !important;
}

/* Detail Reason Wrap Styles */
.detail-reason-wrap {
    max-width: 300px;
    max-height: 120px;
    overflow-y: auto;
    padding-right: 8px;
    font-size: 0.85rem;
    line-height: 1.5;
    color: var(--hcis-slate-600); /* var(--text-muted) equivalent for better readability */
}

.detail-reason-wrap::-webkit-scrollbar {
    width: 4px;
}

.detail-reason-wrap::-webkit-scrollbar-track {
    background: transparent;
}

.detail-reason-wrap::-webkit-scrollbar-thumb {
    background: var(--hcis-slate-300);
    border-radius: 4px;
}

.detail-reason-wrap::-webkit-scrollbar-thumb:hover {
    background: var(--hcis-slate-400);
}

.reason-list {
    margin: 0;
    padding-left: 1rem;
    list-style-type: disc;
    text-align: left;
}

.reason-list li {
    margin-bottom: 4px;
}

.reason-list li:last-child {
    margin-bottom: 0;
}
</style>
