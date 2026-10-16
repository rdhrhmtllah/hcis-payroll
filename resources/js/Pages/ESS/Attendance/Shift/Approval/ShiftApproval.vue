<template>
    <div class="cuti-app-container">
        <!-- Header -->
        <HeaderKpi
            title="Shifting & Lembur Approval Center"
            subtitle="Tinjau dan kelola pengajuan shifting dan konversi lembur tim"
            :dayNight="dayNight"
            :formattedDate="formattedDate"
        />
        <quick-stat :stats="quickStatField" />

        <!-- Main Content -->
        <div class="main-content-wrapper">
            <div class="content-container">
                <ApprovalTabs :tabs="tabs" :activeTab="activeTab" @tab-change="handleTabChange">
                    <!-- Tab Dashboard -->
                    <template #dashboard>
                        <div class="p-4 rounded-4" style="background: var(--surface-soft)">
                            <!-- Summary Cards -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6 col-12">
                                    <div class="stitch-card h-100 d-flex flex-column justify-content-center">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="stitch-card-icon orange">
                                                <i class="bi bi-inbox" style="font-size: 1.25rem"></i>
                                            </div>
                                            <div>
                                                <p class="text-muted text-sm font-medium mb-0">Menunggu Approval</p>
                                                <p class="text-dark text-2xl font-bold mb-0 pl-1 tracking-tight">
                                                    {{ stat.pending || 0 }}
                                                </p>
                                                <p class="text-muted small pl-1 mb-0" style="font-size: 0.75rem">
                                                    Permintaan baru
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 col-12">
                                    <div class="stitch-card h-100 d-flex flex-column justify-content-center">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="stitch-card-icon green">
                                                <i class="bi bi-check2-circle" style="font-size: 1.25rem"></i>
                                            </div>
                                            <div>
                                                <p class="text-muted text-sm font-medium mb-0">Diproses Bulan Ini</p>
                                                <p class="text-dark text-2xl font-bold mb-0 pl-1 tracking-tight">
                                                    {{ stat.approved || 0 }}
                                                </p>
                                                <p class="text-muted small pl-1 mb-0" style="font-size: 0.75rem">
                                                    Transaksi selesai
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Team Activity Placeholder -->
                            <div class="stitch-card w-100 p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="text-dark font-bold m-0">
                                        <i class="bi bi-activity me-2"></i>Aktivitas Tim
                                    </h5>
                                    <!-- <span class="text-sm text-muted">Hari Ini</span> -->
                                </div>
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-people display-4 mb-2 d-block"></i>
                                    <p class="small m-0">Ringkasan aktivitas tim akan muncul di sini</p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Tab Inbox -->
                    <template #inbox>
                        <div class="p-4 rounded-4" style="background: var(--surface-soft)">
                            <div class="d-flex justify-content-between align-items-end mb-4">
                                <div class="d-flex flex-column gap-1">
                                    <h1 class="text-dark text-2xl font-extrabold leading-tight tracking-tight m-0">
                                        Pengajuan
                                    </h1>
                                    <p class="text-muted text-sm m-0">
                                        Daftar permintaan yang menunggu persetujuan Anda
                                    </p>
                                </div>
                                <button @click="loadInbox()" class="stitch-action-btn" title="Refresh">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>

                            <!-- Filter -->
                            <div class="mb-6">
                                <ModernFilter
                                    :config="inboxFilterConfig"
                                    :filters="inboxFilters"
                                    @filters-update="handleInboxFilters"
                                />
                            </div>

                            <!-- List -->
                            <div v-if="inboxLoading" class="loading-state">
                                <div class="loading-spinner">
                                    <i class="bi bi-arrow-repeat"></i>
                                </div>
                                <h4 class="loading-title">Memuat Inbox...</h4>
                                <p class="loading-description">Sedang mengambil data permintaan</p>
                            </div>

                            <div
                                v-else-if="inboxRequests.length === 0"
                                class="text-center py-5 text-muted bg-white rounded-3 shadow-sm border"
                            >
                                <div class="d-flex flex-column align-items-center gap-2">
                                    <i class="bi bi-check2-all text-success display-4"></i>
                                    <span class="fw-medium text-dark">Semua beres!</span>
                                    <span class="small">Tidak ada permintaan menunggu saat ini.</span>
                                </div>
                            </div>

                            <!-- Responsive View: Desktop Table & Mobile Cards -->
                            <div v-else>
                                <!-- DESKTOP TABLE VIEW -->
                                <div class="d-none d-md-block bg-white rounded-3 border shadow-sm overflow-hidden">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="bg-light text-muted text-xs text-uppercase tracking-wider">
                                            <tr>
                                                <th
                                                    class="ps-4 py-3 fw-bold bg-gray-50 text-secondary"
                                                    style="width: 25%"
                                                >
                                                    Karyawan
                                                </th>
                                                <th class="py-3 fw-bold bg-gray-50 text-secondary" style="width: 15%">
                                                    No Transaksi
                                                </th>
                                                <th class="py-3 fw-bold bg-gray-50 text-secondary" style="width: 15%">
                                                    Jabatan
                                                </th>
                                                <th class="py-3 fw-bold bg-gray-50 text-secondary" style="width: 15%">
                                                    Tipe
                                                </th>
                                                <th class="py-3 fw-bold bg-gray-50 text-secondary" style="width: 25%">
                                                    Ringkasan
                                                </th>
                                                <!-- <th
                                                    class="py-3 fw-bold bg-gray-50 text-secondary"
                                                    style="width: 20%"
                                                >
                                                    Alasan
                                                </th> -->
                                                <th
                                                    class="pe-4 py-3 fw-bold bg-gray-50 text-secondary text-end"
                                                    style="width: 15%"
                                                >
                                                    Aksi
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="req in inboxRequests"
                                                :key="req.No_Transaksi"
                                                class="cursor-pointer transition-colors hover:bg-gray-50"
                                                @click="openDetailModal(req)"
                                            >
                                                <td class="ps-4 py-3">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div
                                                            class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold shadow-sm flex-shrink-0"
                                                            style="width: 36px; height: 36px; font-size: 0.85rem"
                                                        >
                                                            {{ getInitials(getRequesterName(req)) }}
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold text-dark text-sm mb-0">
                                                                {{ getRequesterName(req) }}
                                                            </div>
                                                            <!-- <div
                                                                class="text-xs text-muted"
                                                            >
                                                                {{
                                                                    getRequesterNIK(
                                                                        req,
                                                                    )
                                                                }}
                                                            </div> -->
                                                            <span
                                                                v-if="getCategoryLabel(req)"
                                                                class="badge rounded-pill fw-normal text-xs"
                                                                :class="getCategoryClass(req)"
                                                                style="font-size: 0.6rem; padding: 0.2rem 0.5rem"
                                                            >
                                                                {{ getCategoryLabel(req) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td class="py-3">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i :class="getTypeIcon(req)" style="font-size: 1.1rem"></i>
                                                        <span
                                                            class="badge rounded-pill border fw-normal text-xs"
                                                            style="letter-spacing: 0.5px"
                                                            :class="getTypeBadgeClass(req)"
                                                        >
                                                            {{ req.No_Transaksi }}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="py-3">
                                                    <div
                                                        class="text-muted text-xs text-truncate"
                                                        style="max-width: 180px"
                                                    >
                                                        {{ req.nama_jabatan }}
                                                    </div>
                                                </td>
                                                <td class="py-3">
                                                    <span
                                                        class="badge rounded-pill border fw-normal text-xs"
                                                        style="letter-spacing: 0.5px"
                                                        :class="getTypeBadgeClass(req)"
                                                    >
                                                        {{ getTypeLabel(req) }}
                                                    </span>
                                                </td>
                                                <td class="py-3">
                                                    <div class="d-flex flex-column gap-1">
                                                        <span class="fw-bold text-dark text-xs">
                                                            <template v-if="req.transaction_type === 'TUKAR_HARI'">
                                                                1 Tukar Hari
                                                            </template>
                                                            <template
                                                                v-else-if="req.transaction_type === 'STATUS_HARI'"
                                                            >
                                                                1 Status Hari
                                                            </template>
                                                            <template v-else>
                                                                {{ req.total_days }}
                                                                Hari Shift
                                                            </template>
                                                        </span>
                                                        <span
                                                            class="text-muted text-xs text-truncate"
                                                            style="max-width: 200px"
                                                            >{{ req.dates_summary }}</span
                                                        >
                                                    </div>
                                                </td>
                                                <!-- <td class="py-3">
                                                    <div
                                                        class="text-muted text-xs fst-italic text-truncate"
                                                        style="max-width: 180px"
                                                    >
                                                        "{{
                                                            req.Alasan || "-"
                                                        }}"
                                                    </div>
                                                </td> -->
                                                <td class="pe-4 py-3 text-end">
                                                    <button
                                                        class="stitch-btn secondary small d-inline-flex align-items-center gap-1 text-xs px-3"
                                                    >
                                                        Review
                                                        <i class="bi bi-chevron-right"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- MOBILE CARD VIEW -->
                                <div class="d-block d-md-none">
                                    <div class="d-flex flex-column gap-3 p-0 m-0">
                                        <div
                                            v-for="req in inboxRequests"
                                            :key="req.No_Transaksi"
                                            class="stitch-card p-0 overflow-hidden border shadow-sm"
                                            @click="openDetailModal(req)"
                                        >
                                            <!-- Card Header: Compact -->
                                            <div
                                                class="p-3 border-bottom bg-white d-flex justify-content-between align-items-center"
                                                :style="getTypeBorderColor(req)"
                                            >
                                                <div class="d-flex align-items-center gap-3">
                                                    <i :class="getTypeIcon(req)" style="font-size: 1.8rem"></i>
                                                    <div>
                                                        <h6 class="fw-bold text-dark mb-0 text-sm">
                                                            {{ getRequesterName(req) }}
                                                        </h6>
                                                        <div class="text-muted text-xs">
                                                            {{ getRequesterNIK(req) }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                                    <span
                                                        class="badge rounded-pill fw-normal"
                                                        style="font-size: 0.65rem; padding: 0.35rem 0.6rem"
                                                        :class="getTypeBadgeClass(req)"
                                                    >
                                                        {{ getTypeLabel(req) }}
                                                    </span>
                                                    <span
                                                        v-if="getCategoryLabel(req)"
                                                        class="badge rounded-pill fw-normal"
                                                        :class="getCategoryClass(req)"
                                                        style="font-size: 0.6rem; padding: 0.2rem 0.5rem"
                                                    >
                                                        {{ getCategoryLabel(req) }}
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Card Body: Grid -->
                                            <div class="p-3 bg-gray-50">
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <div
                                                            class="text-muted text-uppercase fw-bold mb-1"
                                                            style="font-size: 0.65rem; letter-spacing: 0.5px"
                                                        >
                                                            Total
                                                        </div>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <i class="bi bi-calendar-week text-primary text-xs"></i>
                                                            <span class="fw-bold text-dark text-sm"
                                                                >{{ req.total_days }} Hari</span
                                                            >
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div
                                                            class="text-muted text-uppercase fw-bold mb-1"
                                                            style="font-size: 0.65rem; letter-spacing: 0.5px"
                                                        >
                                                            Range
                                                        </div>
                                                        <div class="text-dark text-xs fw-medium text-truncate">
                                                            {{ req.dates_summary }}
                                                        </div>
                                                    </div>
                                                    <div class="col-12" v-if="req.Alasan">
                                                        <div
                                                            class="mt-2 p-2 bg-white rounded border fst-italic text-muted text-xs"
                                                        >
                                                            "{{ req.Alasan }}"
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Card Footer: Compact Action -->
                                            <div class="p-2 bg-white border-top">
                                                <button
                                                    class="stitch-btn primary small w-100 justify-content-center shadow-none text-xs py-2"
                                                >
                                                    Review Pengajuan
                                                    <i class="bi bi-arrow-right ms-1"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-if="inboxPagination.total_pages > 1"
                                class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top border-light"
                            >
                                <button
                                    class="stitch-btn secondary small"
                                    :disabled="!inboxPagination.prev_page_url"
                                    @click="loadInbox(inboxPagination.prev_page_url)"
                                >
                                    <i class="bi bi-chevron-left me-1"></i>
                                    <span class="d-none d-md-block">Previous</span>
                                </button>
                                <span class="small text-muted fw-medium">
                                    Halaman
                                    <span class="text-dark">{{ inboxPagination.current_page }}</span>
                                    dari
                                    <span class="text-dark">{{
                                        inboxPagination.total_pages || inboxPagination.last_page || 1
                                    }}</span>
                                </span>
                                <button
                                    class="stitch-btn secondary small"
                                    :disabled="!inboxPagination.next_page_url"
                                    @click="loadInbox(inboxPagination.next_page_url)"
                                >
                                    <span class="d-none d-md-block">Next</span>
                                    <i class="bi bi-chevron-right ms-1"></i>
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- Tab History -->
                    <template #history>
                        <div class="p-4 rounded-4" style="background: var(--surface-soft)">
                            <div class="d-flex justify-content-between align-items-end mb-4">
                                <div class="d-flex flex-column gap-1">
                                    <h1 class="text-dark text-2xl font-extrabold leading-tight tracking-tight m-0">
                                        Riwayat Review
                                    </h1>
                                    <p class="text-muted text-sm m-0">Daftar pengajuan yang telah anda review</p>
                                </div>
                                <button @click="loadHistory()" class="stitch-action-btn" title="Refresh">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>

                            <!-- Filter -->
                            <div class="mb-6">
                                <ModernFilter
                                    :config="historyFilterConfig"
                                    :filters="historyFilters"
                                    :results-count="historyPagination.total || historyRequests.length"
                                    @filters-update="handleHistoryFilters"
                                />
                            </div>

                            <!-- Responsive History View -->
                            <div v-if="historyLoading" class="loading-state">
                                <div class="loading-spinner">
                                    <i class="bi bi-arrow-repeat"></i>
                                </div>
                                <h4 class="loading-title">Memuat Riwayat...</h4>
                                <p class="loading-description">Sedang mengambil data approval</p>
                            </div>

                            <div
                                v-else-if="historyRequests.length === 0"
                                class="text-center py-5 text-muted bg-white rounded-3 shadow-sm border d-flex flex-column justify-content-center align-items-center"
                                style="min-height: 400px"
                            >
                                <i class="bi bi-clock-history display-4 mb-3"></i>
                                <p class="text-center mt-3">Belum ada riwayat approval.</p>
                            </div>

                            <div v-else>
                                <!-- Responsive Table (Stitch Design) -->
                                <div class="d-none d-md-block bg-white rounded-3 border shadow-sm overflow-hidden">
                                    <div class="table-responsive">
                                        <table class="stitch-table">
                                            <thead>
                                                <tr>
                                                    <th class="text-center" style="width: 60px">Tipe</th>
                                                    <th class="text-center">Karyawan</th>
                                                    <th class="text-center">No Transaksi</th>
                                                    <th class="text-center">Jabatan</th>
                                                    <th class="text-center">Tanggal Pengajuan</th>
                                                    <th class="text-center">Tanggal</th>
                                                    <th class="text-center">Status</th>
                                                    <th class="text-center">Diproses</th>
                                                    <th class="text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="req in historyRequests"
                                                    :key="req.No_Transaksi"
                                                    class="stitch-table-row"
                                                >
                                                    <td data-label="Tipe" class="text-center">
                                                        <i
                                                            :class="
                                                                req.transaction_type === 'TUKAR_HARI'
                                                                    ? 'bi-arrow-left-right text-warning'
                                                                    : 'bi-calendar-check text-success'
                                                            "
                                                            class="bi fs-4"
                                                            :title="
                                                                req.transaction_type === 'TUKAR_HARI'
                                                                    ? 'Tukar Hari'
                                                                    : 'Pergantian Shift'
                                                            "
                                                        ></i>
                                                    </td>
                                                    <td data-label="Karyawan">
                                                        <div class="d-flex align-items-center gap-3 w-100">
                                                            <div
                                                                class="avatar-circle-sm rounded-circle bg-light border d-flex align-items-center justify-content-center text-secondary fw-bold"
                                                                style="width: 32px; height: 32px; font-size: 0.75rem"
                                                            >
                                                                {{ getInitials(getRequesterName(req)) }}
                                                            </div>
                                                            <div>
                                                                <div class="fw-bold text-dark">
                                                                    {{ getRequesterName(req) }}
                                                                </div>
                                                                <div class="small text-muted">
                                                                    {{ getRequesterNIK(req) }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td data-label="No Transaksi" class="text-center">
                                                        {{ req.No_Transaksi }}
                                                    </td>
                                                    <td data-label="Jabatan" class="text-center">
                                                        {{ req.nama_jabatan }}
                                                    </td>
                                                    <td data-label="Tanggal Pengajuan" class="text-center">
                                                        {{ formatDate(req.Created_At) }}
                                                    </td>
                                                    <td data-label="Tanggal" class="text-center">
                                                        <template v-if="req.transaction_type === 'TUKAR_HARI'">
                                                            <span class="badge bg-warning text-dark border">
                                                                1 Tukar Hari
                                                            </span>
                                                            <div class="small text-muted mt-1" v-if="req.dates_summary">
                                                                {{ req.dates_summary }}
                                                            </div>
                                                        </template>
                                                        <template v-else>
                                                            <span class="badge bg-light text-dark border">
                                                                {{ req.total_days || 0 }}
                                                                Hari
                                                            </span>
                                                            <div class="small text-muted mt-1" v-if="req.dates_summary">
                                                                {{ req.dates_summary }}
                                                            </div>
                                                        </template>
                                                    </td>
                                                    <td data-label="Status" class="text-center">
                                                        <span
                                                            class="stitch-badge"
                                                            :class="req.Approval_Status ? 'approved' : 'rejected'"
                                                        >
                                                            Selesai Direview
                                                        </span>
                                                    </td>
                                                    <td data-label="Diproses" class="text-center">
                                                        {{ formatDate(req.Tanggal_Approval) }}
                                                    </td>
                                                    <td data-label="Action" class="text-center">
                                                        <button
                                                            class="stitch-action-btn"
                                                            title="Lihat Detail"
                                                            style="width: 36px; height: 36px"
                                                            @click="openDetailModal(req, 'view')"
                                                        >
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- MOBILE CARD VIEW FOR HISTORY -->
                                <div class="d-block d-md-none">
                                    <div class="d-flex flex-column gap-3 p-0 m-0">
                                        <div
                                            v-for="req in historyRequests"
                                            :key="req.No_Transaksi"
                                            class="stitch-card p-0 overflow-hidden border shadow-sm"
                                            :style="
                                                req.transaction_type === 'TUKAR_HARI'
                                                    ? 'border-left: 4px solid #f59e0b !important'
                                                    : 'border-left: 4px solid #10b981 !important'
                                            "
                                            @click="openDetailModal(req, 'view')"
                                        >
                                            <!-- Card Header: Compact -->
                                            <div
                                                class="p-3 border-bottom bg-white d-flex justify-content-between align-items-center"
                                            >
                                                <div class="d-flex align-items-center gap-3">
                                                    <div
                                                        class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold shadow-sm"
                                                        :style="
                                                            req.transaction_type === 'TUKAR_HARI'
                                                                ? 'width: 40px; height: 40px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); font-size: 0.9rem;'
                                                                : 'width: 40px; height: 40px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); font-size: 0.9rem;'
                                                        "
                                                    >
                                                        {{ getInitials(getRequesterName(req)) }}
                                                    </div>
                                                    <div>
                                                        <h6 class="fw-bold text-dark mb-0 text-sm">
                                                            {{ getRequesterName(req) }}
                                                        </h6>
                                                        <div class="text-muted text-xs">
                                                            {{ getRequesterNIK(req) }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <span
                                                    class="stitch-badge"
                                                    :class="req.Approval_Status ? 'approved' : 'rejected'"
                                                    style="font-size: 0.6rem; padding: 0.3rem 0.5rem"
                                                >
                                                    Selesai
                                                </span>
                                            </div>

                                            <!-- Card Body: Grid -->
                                            <div class="p-3 bg-gray-50">
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <div
                                                            class="text-muted text-uppercase fw-bold mb-1"
                                                            style="font-size: 0.65rem; letter-spacing: 0.5px"
                                                        >
                                                            Tipe
                                                        </div>
                                                        <div class="d-flex align-items-center gap-1">
                                                            <i
                                                                :class="
                                                                    req.transaction_type === 'TUKAR_HARI'
                                                                        ? 'bi-arrow-left-right text-warning'
                                                                        : 'bi-calendar-check text-success'
                                                                "
                                                                class="bi"
                                                                style="font-size: 0.9rem"
                                                            ></i>
                                                            <span
                                                                class="badge text-xs"
                                                                :class="
                                                                    req.transaction_type === 'TUKAR_HARI'
                                                                        ? 'bg-warning text-dark'
                                                                        : 'bg-success text-white'
                                                                "
                                                                style="font-size: 0.6rem; padding: 0.2rem 0.4rem"
                                                            >
                                                                {{
                                                                    req.transaction_type === 'TUKAR_HARI'
                                                                        ? 'Tukar'
                                                                        : 'Shift'
                                                                }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div
                                                            class="text-muted text-uppercase fw-bold mb-1"
                                                            style="font-size: 0.65rem; letter-spacing: 0.5px"
                                                        >
                                                            Tanggal
                                                        </div>
                                                        <template v-if="req.transaction_type === 'TUKAR_HARI'">
                                                            <div class="text-dark text-xs fw-bold">1 Hari</div>
                                                            <div
                                                                class="text-muted"
                                                                style="font-size: 0.65rem"
                                                                v-if="req.dates_summary"
                                                            >
                                                                {{ req.dates_summary }}
                                                            </div>
                                                        </template>
                                                        <template v-else>
                                                            <div class="text-dark text-xs fw-bold">
                                                                {{ req.total_days || 0 }}
                                                                Hari
                                                            </div>
                                                            <div
                                                                class="text-muted"
                                                                style="font-size: 0.65rem"
                                                                v-if="req.dates_summary"
                                                            >
                                                                {{ req.dates_summary }}
                                                            </div>
                                                        </template>
                                                    </div>
                                                    <div class="col-6 mt-2">
                                                        <div
                                                            class="text-muted text-uppercase fw-bold mb-1"
                                                            style="font-size: 0.65rem; letter-spacing: 0.5px"
                                                        >
                                                            Tgl Pengajuan
                                                        </div>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <i class="bi bi-calendar3 text-primary text-xs"></i>
                                                            <span class="fw-bold text-dark text-sm">{{
                                                                formatDate(req.Created_At)
                                                            }}</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 mt-2">
                                                        <div
                                                            class="text-muted text-uppercase fw-bold mb-1"
                                                            style="font-size: 0.65rem; letter-spacing: 0.5px"
                                                        >
                                                            Diproses
                                                        </div>
                                                        <div class="text-dark text-xs fw-medium text-truncate">
                                                            {{ formatDate(req.Tanggal_Approval) }}
                                                        </div>
                                                    </div>
                                                    <div class="col-12 mt-2">
                                                        <div class="d-flex align-items-center justify-content-between">
                                                            <span class="badge bg-light text-dark border text-xs">{{
                                                                req.nama_jabatan
                                                            }}</span>
                                                            <span class="text-muted text-xs">{{
                                                                req.No_Transaksi
                                                            }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Card Footer: Compact Action -->
                                            <div class="p-2 bg-white border-top">
                                                <button
                                                    class="stitch-btn secondary small w-100 justify-content-center shadow-none text-xs py-2"
                                                >
                                                    Lihat Detail
                                                    <i class="bi bi-eye ms-1"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pagination -->
                                <div
                                    v-if="(historyPagination.last_page || historyPagination.total_pages || 1) > 1"
                                    class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top border-light"
                                >
                                    <button
                                        class="stitch-btn secondary small"
                                        :disabled="(historyPagination.current_page || 1) <= 1"
                                        @click="loadHistory((historyPagination.current_page || 1) - 1)"
                                    >
                                        <i class="bi bi-chevron-left me-1"></i>
                                        <span class="d-none d-md-block">Previous</span>
                                    </button>
                                    <span class="small text-muted fw-medium">
                                        Halaman
                                        <span class="text-dark">{{ historyPagination.current_page }}</span>
                                        dari
                                        <span class="text-dark">{{
                                            historyPagination.total_pages || historyPagination.last_page || 1
                                        }}</span>
                                    </span>
                                    <button
                                        class="stitch-btn secondary small"
                                        :disabled="
                                            (historyPagination.current_page || 1) >=
                                            (historyPagination.last_page || historyPagination.total_pages || 1)
                                        "
                                        @click="loadHistory((historyPagination.current_page || 1) + 1)"
                                    >
                                        <span class="d-none d-md-block">Next</span>
                                        <i class="bi bi-chevron-right ms-1"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </ApprovalTabs>
            </div>
        </div>

        <!-- Detail Modal (Approval Action) -->
        <Modal :show="showDetailModal" :title="detailModalTitle" size="large" @close="closeDetailModal">
            <template #body>
                <div v-if="selectedRequest" class="detail-modal-content">
                    <div class="detail-modal-grid">
                        <!-- Left Column: User Info -->
                        <div class="detail-left-column">
                            <!-- Blue Header Card -->
                            <div
                                class="detail-transaksi-card p-3 mb-3 border rounded-3 text-white shadow-sm"
                                style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%)"
                            >
                                <div class="d-flex align-items-center gap-3">
                                    <div
                                        class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center fw-bold fs-6 shadow-sm"
                                        style="width: 3rem; height: 3rem"
                                    >
                                        {{ getInitials(requesterName) }}
                                    </div>
                                    <div class="detail-transaksi-type">
                                        <div class="text-white-50 text-xs mb-1">Nama Pengaju</div>
                                        <div class="fw-bold mb-0 text-white tracking-tight text-md">
                                            {{ requesterName }}
                                        </div>
                                        <span class="text-white-50 text-xs">{{ requesterNIK }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Status Review Section -->
                            <div class="detail-status-section mb-3">
                                <div class="detail-status-label text-xs text-muted mb-1 text-uppercase fw-bold">
                                    STATUS REVIEW
                                </div>
                                <div
                                    class="detail-status-badge text-xs"
                                    :class="selectedRequest.Flag_Selesai === 'Y' ? 'approved' : 'pending'"
                                >
                                    {{ selectedRequest.Flag_Selesai === 'Y' ? 'SUDAH DIREVIEW' : 'MENUNGGU REVIEW' }}
                                </div>
                            </div>

                            <div class="detail-status-section mb-3">
                                <div class="detail-status-label text-xs text-muted mb-1 text-uppercase fw-bold">
                                    TIPE PENGAJUAN
                                </div>
                                <div
                                    class="detail-status-badge pe-3"
                                    style="
                                        background: var(--surface);
                                        color: var(--dark);
                                        box-shadow: none;
                                        border: 1px solid var(--border);
                                    "
                                >
                                    <template v-if="selectedRequest.transaction_type === 'TUKAR_HARI'">
                                        <i class="bi bi-arrow-left-right"></i>
                                        TUKAR HARI
                                    </template>
                                    <template v-else-if="selectedRequest.transaction_type === 'STATUS_HARI'">
                                        <i class="bi bi-calendar-day"></i>
                                        {{ hasPermanentStatusHariRequest ? 'STATUS HARI PERMANEN' : 'STATUS HARI' }}
                                    </template>
                                    <template v-else-if="selectedRequest.transaction_type === 'KONVERSI_LEMBUR'">
                                        <i class="bi bi-clock-history"></i>
                                        KONVERSI LEMBUR
                                    </template>
                                    <template v-else>
                                        {{
                                            selectedRequest.Target_Table === 'SEMENTARA'
                                                ? 'SHIFT SEMENTARA'
                                                : 'SHIFT PERMANEN'
                                        }}
                                    </template>
                                </div>
                                <div class="detail-status-date mt-2 text-xs text-muted">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    Diajukan:
                                    {{ formatDate(selectedRequest.Created_At) }}
                                </div>
                            </div>

                            <div class="detail-info-row mt-3" v-if="selectedRequest.Alasan">
                                <div class="detail-info-label text-xs text-muted mb-1 text-uppercase fw-bold">
                                    Alasan
                                </div>
                                <div class="detail-info-value detail-alasan fst-italic">
                                    "{{ selectedRequest.Alasan }}"
                                </div>
                            </div>

                            <div class="detail-approval-section mt-4">
                                <div class="detail-section-title mb-3">
                                    <i class="bi bi-diagram-3 text-primary"></i>
                                    Alur Persetujuan
                                </div>

                                <div
                                    v-if="approvalFlowLoading"
                                    class="detail-approval-empty text-center p-3 rounded bg-light border-dashed"
                                >
                                    <i class="bi bi-arrow-repeat fs-5 d-block mb-1"></i>
                                    Memuat alur approval...
                                </div>

                                <div v-else class="detail-approval-list">
                                    <div
                                        v-for="(step, idx) in approvalFlowSteps"
                                        :key="idx"
                                        class="detail-approval-item"
                                    >
                                        <div
                                            class="detail-approval-indicator"
                                            :class="{
                                                approved: step.Flag_Approval === 'Y',
                                                rejected: step.Flag_Approval === 'T',
                                                pending: step.Flag_Approval === null,
                                            }"
                                        >
                                            <i v-if="step.Flag_Approval === 'Y'" class="bi bi-check"></i>
                                            <i v-else-if="step.Flag_Approval === 'T'" class="bi bi-x"></i>
                                            <i v-else class="bi bi-hourglass-split"></i>
                                        </div>

                                        <div class="detail-approval-info">
                                            <div
                                                class="detail-approval-name"
                                                :class="{
                                                    'text-success': step.Flag_Approval === 'Y',
                                                    'text-danger': step.Flag_Approval === 'T',
                                                }"
                                            >
                                                {{ getApprovalStepName(step) }}
                                            </div>

                                            <div v-if="step.Flag_Approval === 'Y'" class="detail-approval-date">
                                                <span class="text-success fw-medium">Disetujui</span>
                                                <span v-if="step.Tanggal_Approval">
                                                    -
                                                    {{ formatDate(step.Tanggal_Approval) }}
                                                </span>
                                            </div>

                                            <div v-else-if="step.Flag_Approval === 'T'" class="detail-approval-date">
                                                <span class="text-danger fw-medium">Ditolak</span>
                                                <span v-if="step.Tanggal_Approval">
                                                    -
                                                    {{ formatDate(step.Tanggal_Approval) }}
                                                </span>
                                            </div>

                                            <div v-else class="detail-approval-date">
                                                <span class="text-warning fw-medium">Menunggu review</span>
                                            </div>

                                            <div v-if="step.Catatan" class="text-muted small mt-1">
                                                <i class="bi bi-chat-left-text"></i>
                                                {{ step.Catatan }}
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-if="approvalFlowSteps.length === 0"
                                        class="detail-approval-empty text-center p-3 rounded bg-light border-dashed"
                                    >
                                        <i class="bi bi-check-circle-fill text-success fs-4 mb-2 d-block"></i>
                                        <span class="fw-bold text-success">Auto Approved</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Details -->
                        <div class="detail-right-column">
                            <div v-if="selectedRequest.transaction_type === 'TUKAR_HARI'" class="detail-info-section">
                                <div class="detail-section-title mb-3 text-uppercase text-xs fw-bold text-muted">
                                    <i class="bi bi-arrow-left-right me-2"></i>
                                    Detail Tukar Hari
                                </div>

                                <div class="detail-shift-card p-3 rounded-3 border">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="text-muted text-xs">Detail Pertukaran</div>
                                        <div
                                            v-if="selectedRequest.mode !== 'view'"
                                            class="d-flex align-items-center gap-1"
                                        >
                                            <button
                                                @click="processingDetails[0].approval_status = 'Y'"
                                                class="btn btn-icon-circle d-flex align-items-center justify-content-center p-0 transition-all"
                                                style="width: 2rem; height: 2rem; border-radius: 50%"
                                                :class="
                                                    processingDetails[0]?.approval_status === 'Y'
                                                        ? 'btn-success text-white'
                                                        : 'btn-light border text-muted hover-success'
                                                "
                                                title="Approve"
                                            >
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                            <button
                                                @click="processingDetails[0].approval_status = 'T'"
                                                class="btn btn-icon-circle d-flex align-items-center justify-content-center p-0 transition-all"
                                                style="width: 2rem; height: 2rem; border-radius: 50%"
                                                :class="
                                                    processingDetails[0]?.approval_status === 'T'
                                                        ? 'btn-danger text-white'
                                                        : 'btn-light border text-muted hover-danger'
                                                "
                                                title="Reject"
                                            >
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>
                                        <div v-else>
                                            <span
                                                class="stitch-badge"
                                                :class="
                                                    processingDetails[0]?.Flag_Approval === 'Y'
                                                        ? 'approved'
                                                        : processingDetails[0]?.Flag_Approval === 'T'
                                                          ? 'rejected'
                                                          : 'pending'
                                                "
                                            >
                                                {{
                                                    processingDetails[0]?.Flag_Approval === 'Y'
                                                        ? 'Disetujui'
                                                        : processingDetails[0]?.Flag_Approval === 'T'
                                                          ? 'Ditolak'
                                                          : 'Pending'
                                                }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-3 flex-wrap">
                                        <div class="flex-grow-1">
                                            <div class="text-muted text-xs mb-1">Tanggal Bekerja</div>
                                            <div class="fw-bold text-dark text-sm">
                                                {{ formatDate(processingDetails[0]?.Tanggal_Bekerja) }}
                                            </div>
                                            <div class="text-muted text-xs mt-1">
                                                Shift:
                                                {{ processingDetails[0]?.Nama_Shift || '-' }}
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <i
                                                class="bi bi-arrow-left-right text-warning"
                                                style="font-size: 1.5rem"
                                            ></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="text-muted text-xs mb-1">Tanggal Off</div>
                                            <div class="fw-bold text-dark text-sm">
                                                {{ formatDate(processingDetails[0]?.Tanggal_Off) }}
                                            </div>
                                            <div class="text-muted text-xs mt-1">
                                                Hari libur ditukar menjadi hari kerja
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else-if="selectedRequest.transaction_type === 'KONVERSI_LEMBUR'"
                                class="detail-info-section"
                            >
                                <div class="detail-section-title mb-3 text-uppercase text-xs fw-bold text-muted">
                                    <i class="bi bi-clock-history me-2"></i>
                                    Detail Konversi Lembur
                                </div>

                                <div class="detail-shift-card p-3 rounded-3 border">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="text-muted text-xs">Ringkasan Konversi</div>
                                        <div
                                            v-if="selectedRequest.mode !== 'view'"
                                            class="d-flex align-items-center gap-1"
                                        >
                                            <button
                                                @click="processingDetails[0].approval_status = 'Y'"
                                                class="btn btn-icon-circle d-flex align-items-center justify-content-center p-0 transition-all"
                                                style="width: 2rem; height: 2rem; border-radius: 50%"
                                                :class="
                                                    processingDetails[0]?.approval_status === 'Y'
                                                        ? 'btn-success text-white'
                                                        : 'btn-light border text-muted hover-success'
                                                "
                                                title="Approve"
                                            >
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                            <button
                                                @click="processingDetails[0].approval_status = 'T'"
                                                class="btn btn-icon-circle d-flex align-items-center justify-content-center p-0 transition-all"
                                                style="width: 2rem; height: 2rem; border-radius: 50%"
                                                :class="
                                                    processingDetails[0]?.approval_status === 'T'
                                                        ? 'btn-danger text-white'
                                                        : 'btn-light border text-muted hover-danger'
                                                "
                                                title="Reject"
                                            >
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>
                                        <div v-else>
                                            <span
                                                class="stitch-badge"
                                                :class="
                                                    processingDetails[0]?.Flag_Approval === 'Y'
                                                        ? 'approved'
                                                        : processingDetails[0]?.Flag_Approval === 'T'
                                                          ? 'rejected'
                                                          : 'pending'
                                                "
                                            >
                                                {{
                                                    processingDetails[0]?.Flag_Approval === 'Y'
                                                        ? 'Disetujui'
                                                        : processingDetails[0]?.Flag_Approval === 'T'
                                                          ? 'Ditolak'
                                                          : 'Pending'
                                                }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="row g-3">
                                        <div
                                            class="col-12"
                                            v-if="
                                                String(
                                                    processingDetails[0]?.Jenis_Konversi ||
                                                        selectedRequest.Jenis_Konversi ||
                                                        'DAY_OFF',
                                                ).toUpperCase() === 'DAY_OFF' &&
                                                (processingDetails[0]?.Target_Off || selectedRequest.Target_Off)
                                            "
                                        >
                                            <div class="konversi-target-highlight">
                                                <div class="konversi-target-highlight__label">
                                                    <i class="bi bi-calendar2-check me-2"></i>
                                                    Target Day Off Yang Dipilih
                                                </div>
                                                <div class="konversi-target-highlight__date">
                                                    {{
                                                        formatDate(
                                                            processingDetails[0]?.Target_Off ||
                                                                selectedRequest.Target_Off,
                                                        )
                                                    }}
                                                </div>
                                                <div class="konversi-target-highlight__hint">
                                                    Tanggal ini akan dijadikan hari libur pengganti jika pengajuan
                                                    disetujui.
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            class="col-12"
                                            v-else-if="
                                                String(
                                                    processingDetails[0]?.Jenis_Konversi ||
                                                        selectedRequest.Jenis_Konversi ||
                                                        'DAY_OFF',
                                                ).toUpperCase() === 'TO_CUTI'
                                            "
                                        >
                                            <div class="konversi-target-highlight">
                                                <div class="konversi-target-highlight__label">
                                                    <i class="bi bi-journal-check me-2"></i>
                                                    Target Konversi
                                                </div>
                                                <div class="konversi-target-highlight__date">
                                                    Bayar Hutang Cuti
                                                </div>
                                                <div class="konversi-target-highlight__hint">
                                                    Jika pengajuan disetujui, reward konversi lembur akan langsung
                                                    dipakai untuk mengurangi hutang cuti requester.
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-sm-6">
                                            <div class="text-muted text-xs mb-1">Tanggal Lembur</div>
                                            <div class="fw-bold text-dark text-sm">
                                                {{
                                                    formatDate(
                                                        processingDetails[0]?.Tanggal_Lembur ||
                                                            processingDetails[0]?.Tanggal_Shift,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="text-muted text-xs mb-1">Jenis Konversi</div>
                                            <div class="fw-bold text-dark text-sm">
                                                {{
                                                    getKonversiTypeLabel(
                                                        processingDetails[0]?.Jenis_Konversi ||
                                                            selectedRequest.Jenis_Konversi,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="text-muted text-xs mb-1">Durasi Lembur</div>
                                            <div class="fw-bold text-dark text-sm">
                                                {{
                                                    formatDurationHours(
                                                        processingDetails[0]?.Durasi_Jam || selectedRequest.Durasi_Jam,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                        <div
                                            class="col-sm-6"
                                            v-if="
                                                (processingDetails[0]?.Target_Off || selectedRequest.Target_Off) &&
                                                String(
                                                    processingDetails[0]?.Jenis_Konversi ||
                                                        selectedRequest.Jenis_Konversi ||
                                                        'DAY_OFF',
                                                ).toUpperCase() !== 'DAY_OFF'
                                            "
                                        >
                                            <div class="text-muted text-xs mb-1">Target Off</div>
                                            <div class="fw-bold text-dark text-sm">
                                                {{
                                                    formatDate(
                                                        processingDetails[0]?.Target_Off || selectedRequest.Target_Off,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                        <div
                                            class="col-12"
                                            v-if="
                                                processingDetails[0]?.CheckIn_Pilih ||
                                                processingDetails[0]?.CheckOut_Pilih
                                            "
                                        >
                                            <div class="text-muted text-xs mb-1">Waktu Pilihan</div>
                                            <div class="fw-bold text-dark text-sm">
                                                {{
                                                    processingDetails[0]?.CheckIn_Pilih
                                                        ? formatDate(processingDetails[0]?.CheckIn_Pilih, true)
                                                        : '-'
                                                }}
                                                -
                                                {{
                                                    processingDetails[0]?.CheckOut_Pilih
                                                        ? formatDate(processingDetails[0]?.CheckOut_Pilih, true)
                                                        : '-'
                                                }}
                                            </div>
                                        </div>
                                        <div class="col-12" v-if="getKonversiLampiranUrl()">
                                            <div class="text-muted text-xs mb-2">Lampiran</div>
                                            <button
                                                type="button"
                                                class="stitch-btn secondary"
                                                @click="downloadKonversiLampiran()"
                                            >
                                                <i class="bi bi-download me-1"></i>
                                                Download Lampiran
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="detail-info-section">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="detail-section-title mb-0 text-uppercase text-xs fw-bold text-muted">
                                        <i class="bi bi-people me-2"></i>
                                        Daftar Karyawan ({{ groupedMembers.length }})
                                    </div>

                                    <div v-if="selectedRequest.mode !== 'view'" class="d-flex gap-2">
                                        <!-- <button
                                            @click="batchSetAction('Y')"
                                            class="btn btn-primary btn-sm d-flex align-items-center gap-1 py-0 px-2"
                                            style="
                                                height: 28px;
                                                font-size: 0.75rem;
                                            "
                                        >
                                            <i class="bi bi-check-lg"></i>
                                            Approve All
                                        </button> -->
                                        <button
                                            @click="batchSetAction('T')"
                                            class="btn btn-danger btn-sm d-flex align-items-center gap-1 py-0 px-2"
                                            style="height: 28px; font-size: 0.75rem"
                                        >
                                            <i class="bi bi-x-lg"></i>
                                            Reject All
                                        </button>
                                    </div>
                                </div>

                                <div v-if="groupedMembers.length" class="detail-shift-list">
                                    <div
                                        v-for="member in groupedMembers"
                                        :key="member.memberKey"
                                        class="stitch-card mb-2 p-3 border rounded-3 bg-white"
                                    >
                                        <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
                                            <div class="overflow-hidden">
                                                <div class="fw-bold text-dark text-sm text-truncate">
                                                    {{ member.Nama_Karyawan }}
                                                </div>
                                                <div
                                                    class="text-muted text-xs d-flex align-items-center flex-wrap gap-1"
                                                >
                                                    <span class="text-truncate" style="max-width: 150px">{{
                                                        member.Nama_Jabatan || '-'
                                                    }}</span>
                                                    <span
                                                        v-if="member.Target_Table"
                                                        class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1"
                                                        style="font-size: 0.65rem"
                                                    >
                                                        {{ member.Target_Table }}
                                                    </span>
                                                </div>
                                                <div class="mt-2" v-if="member.details.length">
                                                    <span
                                                        class="badge rounded-pill border text-xs"
                                                        :class="getAggregateMemberStatusClass(member)"
                                                    >
                                                        {{ getAggregateMemberStatusLabel(member) }}
                                                    </span>
                                                </div>
                                                <div
                                                    v-if="isPermanentStatusHariMember(member)"
                                                    class="mt-2 text-xs text-muted d-flex flex-column gap-1"
                                                >
                                                    <span>
                                                        <i class="bi bi-calendar-event me-1"></i>
                                                        {{
                                                            getPermanentMemberStartDate(member)
                                                                ? `Berlaku: mulai ${formatDate(getPermanentMemberStartDate(member))} dan seterusnya`
                                                                : 'Berlaku: mulai tanggal pengajuan dan seterusnya'
                                                        }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div
                                                class="d-flex flex-column flex-sm-row align-items-end align-items-sm-center gap-1 flex-shrink-0"
                                            >
                                                <!-- <button
                                                    v-if="
                                                        selectedRequest.mode !==
                                                        'view'
                                                    "
                                                    @click="
                                                        setMemberAction(
                                                            member,
                                                            'Y',
                                                        )
                                                    "
                                                    class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1 py-0 px-2"
                                                    style="
                                                        height: 24px;
                                                        font-size: 0.7rem;
                                                    "
                                                    title="Approve All for this member"
                                                >
                                                    <i
                                                        class="bi bi-check-lg"
                                                    ></i>
                                                    Approve
                                                </button> -->
                                                <button
                                                    v-if="selectedRequest.mode !== 'view'"
                                                    @click="setMemberAction(member, 'T')"
                                                    class="btn btn-outline-danger btn-sm d-flex align-items-center gap-1 py-0 px-2"
                                                    style="height: 24px; font-size: 0.7rem"
                                                    title="Reject All for this member"
                                                >
                                                    <i class="bi bi-x-lg"></i>
                                                    Reject
                                                </button>
                                                <button
                                                    class="stitch-btn icon-only small"
                                                    @click="toggleMemberExpand(member.memberKey)"
                                                >
                                                    <i
                                                        class="bi"
                                                        :class="
                                                            isMemberExpanded(member.memberKey)
                                                                ? 'bi-chevron-up'
                                                                : 'bi-chevron-down'
                                                        "
                                                    ></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div v-if="isMemberExpanded(member.memberKey)" class="mt-3 border-top pt-2">
                                            <div class="text-xs text-muted fw-bold mb-2 text-uppercase">
                                                Detail Tanggal
                                            </div>
                                            <div v-if="member.details.length" class="d-flex flex-column gap-2">
                                                <div
                                                    v-for="detail in member.details"
                                                    :key="detail.Id_Det || detail.Id"
                                                    class="detail-shift-card p-3 rounded-3 border"
                                                    :class="{
                                                        'border-success':
                                                            detail.approval_status === 'Y' ||
                                                            detail.Flag_Approval === 'Y',
                                                        'border-danger':
                                                            detail.approval_status === 'T' ||
                                                            detail.Flag_Approval === 'T',
                                                        'border-light':
                                                            !detail.approval_status && !detail.Flag_Approval,
                                                    }"
                                                >
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div class="d-flex align-items-center gap-2 w-100">
                                                            <div class="stitch-date-box small">
                                                                <span class="day">{{
                                                                    getDayName(detail.Tanggal_Shift).substring(0, 3)
                                                                }}</span>
                                                                <span class="num">{{
                                                                    getDateNumber(detail.Tanggal_Shift)
                                                                }}</span>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <div class="text-xs text-primary mb-1 fw-bold">
                                                                    {{ getDetailDateDisplayLabel(member, detail) }}
                                                                </div>
                                                                <div
                                                                    class="small fw-bold d-flex align-items-center gap-2 flex-wrap"
                                                                >
                                                                    <span
                                                                        v-if="
                                                                            selectedRequest.transaction_type ===
                                                                            'STATUS_HARI'
                                                                        "
                                                                    >
                                                                        {{ detail.Status_Hari || '-' }}
                                                                    </span>
                                                                    <span v-else>
                                                                        <!-- Shift sebelumnya kosong = absensi tanggal itu tidak
                                                                             punya shift (DAY OFF), jadi ditampilkan sebagai Off. -->
                                                                        <span v-if="detail.Nama_Shift">
                                                                            {{ detail.Nama_Shift_Sebelumnya || 'Off' }}
                                                                            <i
                                                                                class="bi bi-arrow-right text-muted mx-1"
                                                                                style="font-size: 0.8em"
                                                                            ></i>
                                                                            {{ detail.Nama_Shift }}
                                                                        </span>
                                                                        <span v-else>-</span>
                                                                    </span>
                                                                </div>
                                                                <div
                                                                    class="text-xs text-muted d-flex gap-2 mt-1 flex-wrap"
                                                                >
                                                                    <span
                                                                        v-if="detail.Jenis_Pengajuan === 'BACKDATE'"
                                                                        class="badge bg-danger-subtle text-danger border border-danger-subtle px-1"
                                                                        style="font-size: 0.65rem"
                                                                        >Backdate</span
                                                                    >
                                                                    <span
                                                                        v-else-if="detail.Jenis_Pengajuan === 'FUTURE'"
                                                                        class="badge bg-success-subtle text-success border border-success-subtle px-1"
                                                                        style="font-size: 0.65rem"
                                                                        >Future</span
                                                                    >
                                                                </div>
                                                                <div
                                                                    v-if="
                                                                        detail.Flag_Approval === 'T' &&
                                                                        (detail.Processed_By_Nama ||
                                                                            detail.Processed_By_Kode)
                                                                    "
                                                                    class="text-xs text-danger mt-1 fw-semibold"
                                                                >
                                                                    Ditolak oleh:
                                                                    {{
                                                                        detail.Processed_By_Nama ||
                                                                        detail.Processed_By_Kode
                                                                    }}
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div
                                                            v-if="selectedRequest.mode !== 'view'"
                                                            class="d-flex gap-2"
                                                        >
                                                            <button
                                                                @click="detail.approval_status = 'Y'"
                                                                class="btn btn-icon-circle d-flex align-items-center justify-content-center p-0 transition-all"
                                                                style="width: 2rem; height: 2rem; border-radius: 50%"
                                                                :class="
                                                                    detail.approval_status === 'Y'
                                                                        ? 'btn-success text-white'
                                                                        : 'btn-light border text-muted hover-success'
                                                                "
                                                                title="Approve"
                                                            >
                                                                <i class="bi bi-check-lg"></i>
                                                            </button>
                                                            <button
                                                                @click="detail.approval_status = 'T'"
                                                                class="btn btn-icon-circle d-flex align-items-center justify-content-center p-0 transition-all"
                                                                style="width: 2rem; height: 2rem; border-radius: 50%"
                                                                :class="
                                                                    detail.approval_status === 'T'
                                                                        ? 'btn-danger text-white'
                                                                        : 'btn-light border text-muted hover-danger'
                                                                "
                                                                title="Reject"
                                                            >
                                                                <i class="bi bi-x-lg"></i>
                                                            </button>
                                                        </div>
                                                        <div v-else>
                                                            <span
                                                                class="stitch-badge"
                                                                :class="
                                                                    detail.Flag_Approval === 'Y'
                                                                        ? 'approved'
                                                                        : detail.Flag_Approval === 'T'
                                                                          ? 'rejected'
                                                                          : 'pending'
                                                                "
                                                            >
                                                                {{
                                                                    detail.Flag_Approval === 'Y'
                                                                        ? 'Disetujui'
                                                                        : detail.Flag_Approval === 'T'
                                                                          ? 'Ditolak'
                                                                          : 'Pending'
                                                                }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-else class="text-center text-muted small py-2">
                                                Tidak ada detail tanggal
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-center text-muted small py-3">Tidak ada detail karyawan</div>
                            </div>

                            <!-- Notes Section -->
                            <div class="mt-4 pt-3 border-top">
                                <label class="detail-info-label mb-2">Catatan Approval</label>
                                <textarea
                                    v-if="selectedRequest.mode !== 'view'"
                                    v-model="approvalNotes"
                                    class="stitch-input"
                                    rows="2"
                                    placeholder="Tambahkan catatan untuk requester..."
                                ></textarea>
                                <p v-else class="text-muted small fst-italic p-2 bg-light rounded border">
                                    {{ approvalNotes || 'Tidak ada catatan.' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <button
                    @click="closeDetailModal"
                    class="stitch-btn secondary flex-md-grow-0 flex-grow-1"
                    :disabled="submitting"
                >
                    {{ selectedRequest?.mode === 'view' ? 'Tutup' : 'Batal' }}
                </button>
                <button
                    v-if="selectedRequest?.mode !== 'view'"
                    @click="submitApproval"
                    class="stitch-btn primary flex-md-grow-0 flex-grow-1"
                    :disabled="submitting"
                >
                    <span v-if="submitting">
                        <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                        Processing...
                    </span>
                    <span v-else> Selesai Review </span>
                </button>
            </template>
        </Modal>
    </div>
</template>

<script>
import { defineComponent } from 'vue';
import HeaderKpi from '@/components/legacy/header.vue';
import ApprovalTabs from '@/components/legacy/toggleSectionV2.vue';
import Modal from '@/components/legacy/modalV2-noir.vue';
import ModernFilter from '@/components/legacy/Filter/modernFilter.vue';
import quickStat from '@/components/legacy/quickStat.vue';
import moment from 'moment';
import axios from 'axios';
import { ElMessage } from 'element-plus';

export default defineComponent({
    name: 'ShiftApproval',
    components: {
        HeaderKpi,
        ApprovalTabs,
        Modal,
        ModernFilter,
        quickStat,
    },
    props: {
        serverTime: {
            type: String,
            default: null,
        },
        serverDate: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            // State
            activeTab: 'inbox',

            // Inbox
            inboxLoading: false,
            inboxRequests: [],
            stat: {
                total: 0,
                pending: 0,
                approved: 0,
            },
            inboxFilters: {
                search: '',
                date_range: [],
                start_date: null,
                end_date: null,
                jabatan: null,
                transaction_type: null,
            },

            // History
            historyLoading: false,
            historyRequests: [],
            historyPagination: {
                current_page: 1,
                last_page: 1,
                total: 0,
            },
            historyFilters: {
                status: 'all',
                search: '',
                date_range: [],
                start_date: null,
                end_date: null,
                jabatan: null,
                transaction_type: null,
            },

            // Modal & Actions
            showDetailModal: false,
            selectedRequest: null,
            processingDetails: [], // Copy of details for editing
            approvalNotes: '',
            submitting: false,
            detailExpandedMembers: [],
            approvalFlowSteps: [],
            approvalFlowLoading: false,

            // Configs (Moved from setup)
            tabs: [
                {
                    id: 'inbox',
                    label: 'Pengajuan',
                    icon: 'bi bi-inbox',
                    count: null,
                },
                {
                    id: 'history',
                    label: 'Riwayat',
                    icon: 'bi bi-clock-history',
                    count: null,
                },
            ],

            inboxPagination: {},

            jabatan: [],
        };
    },
    computed: {
        quickStatField() {
            return [
                {
                    title: 'Total Pengajuan',
                    value: this.stat.total || 0,
                    unit: '',
                    icon: 'bi bi-calendar-check',
                    bgIcon: 'bg-primary',
                    color: 'blue',
                    sentiment: 'primary',
                    size: 'col-4',
                },
                {
                    title: 'Menunggu Review',
                    value: this.stat.pending || 0,
                    unit: '',
                    icon: 'bi bi-clock-history',
                    bgIcon: 'bg-warning',
                    color: 'orange',
                    sentiment: 'warning',
                    size: 'col-4',
                },
                {
                    title: 'Sudah Direview',
                    value: this.stat.approved || 0,
                    unit: '',
                    icon: 'bi bi-check-circle',
                    bgIcon: 'bg-success',
                    color: 'green',
                    sentiment: 'success',
                    size: 'col-4',
                },
            ];
        },
        inboxFilterConfig() {
            return {
                searchable: true,
                searchPlaceholder: 'Cari Nama / No Transaksi...',
                showResultsCount: true,
                showActions: true,
                filters: [
                    {
                        key: 'jabatan',
                        label: 'Jabatan',
                        type: 'select',
                        filterable: true,
                        col: 4,
                        options: this.jabatanFilter,
                    },
                    {
                        key: 'date_range',
                        label: 'Rentang Tanggal Detail',
                        type: 'daterange',
                        dateType: 'daterange',
                        startPlaceholder: 'Tanggal Mulai',
                        endPlaceholder: 'Tanggal Selesai',
                        valueFormat: 'YYYY-MM-DD',
                        col: 4,
                    },
                    {
                        key: 'transaction_type',
                        label: 'Tipe Transaksi',
                        type: 'select',
                        placeholder: 'Pilih Tipe',
                        clearable: true,
                        col: 4,
                        options: [
                            { value: 'SHIFT', label: 'Shift' },
                            { value: 'TUKAR_HARI', label: 'Tukar Hari' },
                            { value: 'STATUS_HARI', label: 'Status Hari' },
                            {
                                value: 'KONVERSI_LEMBUR',
                                label: 'Konversi Lembur',
                            },
                        ],
                    },
                ],
            };
        },
        dayNight() {
            // const h = new Date().getHours();
            const h = new Date(this.serverTime).getHours();
            return h >= 6 && h < 18 ? 'day' : 'night';
        },
        formattedDate() {
            return new Intl.DateTimeFormat('id-ID', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            }).format(new Date(this.serverDate));
        },
        jabatanFilter() {
            return (
                this.jabatan?.map((jenis) => ({
                    value: jenis.Id_Jabatan,
                    label: jenis.nama_jabatan,
                })) ?? []
            );
        },
        historyFilterConfig() {
            return {
                searchable: true,
                searchPlaceholder: 'Cari Nama...',
                showResultsCount: true,
                showActions: true,
                filters: [
                    {
                        key: 'jabatan',
                        label: 'Jabatan',
                        type: 'select',
                        filterable: true,
                        col: 4,
                        options: this.jabatanFilter,
                    },
                    {
                        key: 'date_range',
                        label: 'Rentang Tanggal Detail',
                        type: 'daterange',
                        dateType: 'daterange',
                        startPlaceholder: 'Tanggal Mulai',
                        endPlaceholder: 'Tanggal Selesai',
                        valueFormat: 'YYYY-MM-DD',
                        col: 4,
                    },
                    {
                        key: 'transaction_type',
                        label: 'Tipe Transaksi',
                        type: 'select',
                        placeholder: 'Pilih Tipe',
                        clearable: true,
                        col: 4,
                        options: [
                            { value: 'SHIFT', label: 'Shift' },
                            { value: 'TUKAR_HARI', label: 'Tukar Hari' },
                            { value: 'STATUS_HARI', label: 'Status Hari' },
                            {
                                value: 'KONVERSI_LEMBUR',
                                label: 'Konversi Lembur',
                            },
                        ],
                    },
                ],
            };
        },
        requesterName() {
            return this.selectedRequest?.Nama_Pengaju || this.selectedRequest?.Nama_Karyawan || '-';
        },
        requesterNIK() {
            return this.selectedRequest?.NIK_Pengaju || this.selectedRequest?.NIK || '-';
        },
        detailModalTitle() {
            return `Review Pengajuan: ${this.requesterName}`;
        },
        groupedMembers() {
            if (!this.selectedRequest) return [];

            const memberMetaByKode = new Map();
            const memberMetaByDetail = new Map();
            (this.selectedRequest.karyawan || []).forEach((member) => {
                if (member.Kode_Karyawan) {
                    memberMetaByKode.set(member.Kode_Karyawan, member);
                }
                if (member.Id) {
                    memberMetaByDetail.set(member.Id, member);
                }
            });

            const groups = new Map();
            this.processingDetails.forEach((detail) => {
                const detailId = detail.Id_Detail;
                const kode = detail.Kode_Karyawan;
                const key = detailId || kode || detail.Nama_Karyawan || 'unknown';

                if (!groups.has(key)) {
                    const meta = memberMetaByDetail.get(detailId) || memberMetaByKode.get(kode) || {};
                    groups.set(key, {
                        memberKey: key,
                        Kode_Karyawan: kode || meta.Kode_Karyawan || null,
                        Nama_Karyawan: meta.Nama_Karyawan || meta.Nama || detail.Nama_Karyawan || '-',
                        Nama_Jabatan: meta.Nama_Jabatan || meta.nama_jabatan || '-',
                        Target_Table: meta.Target_Table || detail.Target_Table || '',
                        details: [],
                    });
                }

                groups.get(key).details.push(detail);
            });

            return Array.from(groups.values()).map((member) => {
                member.details = member.details.slice().sort((a, b) => {
                    const aDate = a.Tanggal_Shift || a.Tanggal;
                    const bDate = b.Tanggal_Shift || b.Tanggal;
                    return new Date(aDate) - new Date(bDate);
                });
                return member;
            });
        },
        hasPermanentStatusHariRequest() {
            if (this.selectedRequest?.transaction_type !== 'STATUS_HARI') {
                return false;
            }

            return this.groupedMembers.some((member) => this.isPermanentStatusHariMember(member));
        },
    },
    mounted() {
        this.loadInbox();
        this.loadStats();
        this.loadJabatan();
    },
    methods: {
        async loadJabatan() {
            try {
                const response = await axios.get('/shift-approval/jabatan');
                this.jabatan = response.data;
                // console.log(this.jabatan);
            } catch (error) {
                console.error('Error loading jabatan:', error);
            }
        },
        async loadDivisi() {
            try {
                const response = await axios.get('/api/divisi');
                this.divisi = response.data;
            } catch (error) {
                console.error('Error loading divisi:', error);
            }
        },
        formatDate(date, withTime = false) {
            return moment(date)
                .locale('id')
                .format(withTime ? 'D MMM YYYY, HH:mm' : 'D MMM YYYY');
        },
        getDayName(date) {
            return moment(date).locale('id').format('ddd');
        },
        getDateNumber(date) {
            return moment(date).locale('id').format('D');
        },
        getMonthYear(date) {
            return moment(date).locale('id').format('D MMM YYYY');
        },
        showPageMessage(type, message) {
            ElMessage({
                type,
                message,
                customClass: 'shift-approval-message',
            });
        },
        isPermanentStatusHariMember(member) {
            return (
                this.selectedRequest?.transaction_type === 'STATUS_HARI' &&
                String(member?.Target_Table || '').toUpperCase() === 'PERMANEN'
            );
        },
        getPermanentMemberStartDate(member) {
            if (!member?.details?.length) return null;

            const sorted = member.details.slice().sort((a, b) => {
                const aDate = a.Tanggal_Shift || a.Tanggal || '';
                const bDate = b.Tanggal_Shift || b.Tanggal || '';
                return new Date(aDate) - new Date(bDate);
            });

            return sorted[0]?.Tanggal_Shift || sorted[0]?.Tanggal || null;
        },
        getDetailDateDisplayLabel(member, detail) {
            const tanggal = detail?.Tanggal_Shift || detail?.Tanggal;
            if (!tanggal) return '-';

            if (this.isPermanentStatusHariMember(member)) {
                return `Mulai Berlaku: ${this.formatDate(tanggal)}`;
            }

            return this.formatDate(tanggal);
        },
        getInitials(name) {
            if (!name) return '?';
            const parts = name.split(' ');
            if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
            return name.substring(0, 2).toUpperCase();
        },
        getRequesterName(req) {
            return req?.Nama_Pengaju || req?.Nama_Karyawan || '-';
        },
        getRequesterNIK(req) {
            return req?.NIK_Pengaju || req?.NIK || '-';
        },

        // Category & Transaction Type Helpers
        getCategoryLabel(req) {
            if (
                req.Jenis_Transaksi === 'LEADER_SHIFT' ||
                req.Jenis_Transaksi === 'STATUS_HARI' ||
                req.Jenis_Transaksi === 'LEADER_STATUS_HARI'
            )
                return 'Leader';
            return 'Perorangan';
        },
        getCategoryClass(req) {
            if (
                req.Jenis_Transaksi === 'LEADER_SHIFT' ||
                req.Jenis_Transaksi === 'STATUS_HARI' ||
                req.Jenis_Transaksi === 'LEADER_STATUS_HARI'
            )
                return 'bg-indigo-subtle text-indigo';
            return 'bg-teal-subtle text-teal';
        },
        getTypeLabel(req) {
            if (req.transaction_type === 'TUKAR_HARI') return 'Tukar Hari';
            if (req.transaction_type === 'STATUS_HARI') return 'Status Hari';
            if (req.transaction_type === 'KONVERSI_LEMBUR')
                return `Konversi ${this.getKonversiTypeLabel(req.Jenis_Konversi)}`;
            // SHIFT type
            if (req.Target_Table === 'SEMENTARA') return 'Shift Sementara';
            return 'Shift Permanen';
        },
        getTypeIcon(req) {
            if (req.transaction_type === 'TUKAR_HARI') return 'bi bi-arrow-left-right text-warning';
            if (req.transaction_type === 'STATUS_HARI') return 'bi bi-calendar-day text-info';
            if (req.transaction_type === 'KONVERSI_LEMBUR') return 'bi bi-clock-history text-success';
            return 'bi bi-calendar-check text-primary';
        },
        getTypeBadgeClass(req) {
            if (req.transaction_type === 'TUKAR_HARI')
                return 'bg-warning-subtle text-warning-emphasis border-warning-subtle';
            if (req.transaction_type === 'STATUS_HARI') return 'bg-info-subtle text-info-emphasis border-info-subtle';
            if (req.transaction_type === 'KONVERSI_LEMBUR')
                return 'bg-success-subtle text-success-emphasis border-success-subtle';
            if (req.Target_Table === 'SEMENTARA') return 'bg-info-subtle text-info-emphasis border-info-subtle';
            return 'bg-primary-subtle text-primary-emphasis border-primary-subtle';
        },
        getTypeBorderColor(req) {
            if (req.transaction_type === 'TUKAR_HARI') return 'border-left: 4px solid #ffc107 !important;';
            if (req.transaction_type === 'STATUS_HARI') return 'border-left: 4px solid #0dcaf0 !important;';
            if (req.transaction_type === 'KONVERSI_LEMBUR') return 'border-left: 4px solid #198754 !important;';
            return 'border-left: 4px solid #0d6efd !important;';
        },
        getKonversiTypeLabel(jenisKonversi) {
            const normalized = String(jenisKonversi || 'DAY_OFF').toUpperCase();
            if (normalized === 'TO_CUTI') return 'Bayar Hutang Cuti';
            return normalized === 'DAY_OFF' ? 'Day Off' : 'Material / Uang';
        },
        isHeaderDecisionType(transactionType) {
            return ['TUKAR_HARI', 'KONVERSI_LEMBUR'].includes(String(transactionType || '').toUpperCase());
        },
        formatDurationHours(value) {
            const numeric = Number(value || 0);
            if (!Number.isFinite(numeric) || numeric <= 0) return '-';
            const fixed = Math.abs(numeric % 1) < 0.01 ? numeric.toFixed(0) : numeric.toFixed(1);
            return `${fixed} Jam`;
        },
        getKonversiLampiranUrl() {
            const detail = this.processingDetails?.[0] || {};
            return (
                detail?.Lampiran ||
                detail?.Lampiran_Url ||
                this.selectedRequest?.Lampiran ||
                this.selectedRequest?.Lampiran_Url ||
                ''
            );
        },
        getKonversiLampiranDownloadUrl() {
            const path = this.getKonversiLampiranUrl();
            if (!path) return '';

            const endpoint =
                typeof route === 'function'
                    ? route('shift-approval.download-lampiran')
                    : '/shift-approval/download-lampiran';

            return `${endpoint}?path=${encodeURIComponent(path)}`;
        },
        async downloadKonversiLampiran() {
            const url = this.getKonversiLampiranDownloadUrl();
            if (!url) {
                this.showPageMessage('warning', 'Lampiran tidak tersedia.');
                return;
            }

            try {
                const link = document.createElement('a');
                link.href = url;
                document.body.appendChild(link);
                link.click();
                link.remove();
            } catch (error) {
                this.showPageMessage('error', 'Gagal mengunduh lampiran.');
            }
        },

        toggleMemberExpand(memberKey) {
            const index = this.detailExpandedMembers.indexOf(memberKey);
            if (index >= 0) {
                this.detailExpandedMembers.splice(index, 1);
                return;
            }
            this.detailExpandedMembers.push(memberKey);
        },
        isMemberExpanded(memberKey) {
            return this.detailExpandedMembers.includes(memberKey);
        },
        setMemberAction(member, action) {
            member.details.forEach((detail) => {
                detail.approval_status = action;
            });
        },
        getAggregateMemberStatusLabel(member) {
            const statuses = member.details.map((detail) => {
                return detail.approval_status || detail.Flag_Approval || null;
            });
            if (!statuses.length) return 'Pending';

            const allApproved = statuses.every((status) => status === 'Y');
            const allRejected = statuses.every((status) => status === 'T');
            const hasApproved = statuses.some((status) => status === 'Y');
            const hasRejected = statuses.some((status) => status === 'T');

            if (allApproved) return 'Disetujui';
            if (allRejected) return 'Ditolak';
            if (hasApproved && hasRejected) return 'Sebagian';
            return 'Pending';
        },
        getAggregateMemberStatusClass(member) {
            const label = this.getAggregateMemberStatusLabel(member);
            if (label === 'Disetujui') return 'bg-success-subtle text-success border-success-subtle';
            if (label === 'Ditolak') return 'bg-danger-subtle text-danger border-danger-subtle';
            if (label === 'Sebagian') return 'bg-warning-subtle text-warning border-warning-subtle';
            return 'bg-secondary-subtle text-secondary border-secondary-subtle';
        },

        // Actions
        handleTabChange(tabId) {
            this.activeTab = tabId;
            if (tabId === 'inbox') {
                this.loadInbox();
                this.loadStats();
            }
            if (tabId === 'history') {
                this.loadHistory();
                this.loadStats();
            }
            if (tabId === 'dashboard') this.loadStats();
        },

        async loadStats() {
            try {
                const url = window.route ? window.route('shift-approval.stats') : '/shift-approval/dashboard-stats';
                const res = await axios.get(url);
                if (res.data.success) {
                    const stats = res.data.data || {};
                    const pending = stats.pending_count || 0;
                    const processed = stats.processed_month || 0;
                    this.stat.pending = pending;
                    this.stat.approved = processed;
                    this.stat.total = pending + processed;
                }
            } catch (e) {
                console.error('Failed to load stats', e);
            }
        },

        async loadInbox(url = null) {
            this.inboxLoading = true;
            try {
                this.inboxRequests = [];
                this.inboxPagination = {};
                const enpoint = url ? url : '/shift-approval/inbox';
                const res = await axios.get(enpoint, {
                    params: this.inboxFilters,
                });
                if (res.data.success) {
                    this.inboxRequests = res.data.result; // result adalah array langsung
                    this.inboxPagination = res.data.pagination;
                    // console.log(res.data.result);
                }
            } catch (e) {
                this.showPageMessage('error', 'Gagal memuat inbox.');
            } finally {
                this.inboxLoading = false;
            }
        },

        handleInboxFilters(newFilters) {
            const dateRange = Array.isArray(newFilters.date_range) ? newFilters.date_range : [];
            this.inboxFilters = {
                ...newFilters,
                date_range: dateRange,
                start_date: dateRange.length === 2 && dateRange[0] ? dateRange[0] : null,
                end_date: dateRange.length === 2 && dateRange[1] ? dateRange[1] : null,
                transaction_type: newFilters.transaction_type || null,
            };
            this.loadInbox();
        },

        async loadHistory(page = 1) {
            this.historyLoading = true;
            try {
                this.historyRequests = [];
                this.historyPagination = {};
                const endpoint = '/shift-approval/history';
                const res = await axios.get(endpoint, {
                    params: {
                        page,
                        ...this.historyFilters,
                    },
                });
                if (res.data.success) {
                    this.historyRequests = res.data.result; // result adalah array langsung
                    this.historyPagination = res.data.pagination;
                }
            } catch (e) {
                this.showPageMessage('error', 'Gagal memuat riwayat.');
            } finally {
                this.historyLoading = false;
            }
        },

        handleHistoryFilters(newFilters) {
            const dateRange = Array.isArray(newFilters.date_range) ? newFilters.date_range : [];
            this.historyFilters = {
                ...newFilters,
                date_range: dateRange,
                start_date: dateRange.length === 2 && dateRange[0] ? dateRange[0] : null,
                end_date: dateRange.length === 2 && dateRange[1] ? dateRange[1] : null,
                transaction_type: newFilters.transaction_type || null,
            };
            this.loadHistory(1); // Reset to page 1
        },

        changeHistoryPage(page) {
            if (page >= 1 && page <= this.historyPagination.last_page) {
                this.loadHistory(page);
            }
        },

        openDetailModal(req, mode = 'action') {
            this.selectedRequest = { ...req, mode }; // Add mode: 'action' or 'view'
            // Initialize processing details
            this.processingDetails = (req.details || []).map((d) => ({
                ...d,
                approval_status: mode === 'action' ? '' : d.Flag_Approval, // Default Y for action, actual status for view
            }));
            if (this.isHeaderDecisionType(req.transaction_type) && this.processingDetails.length === 0) {
                this.processingDetails = [{ approval_status: '' }];
            }
            this.approvalNotes = req.Approval_Notes || '';
            this.detailExpandedMembers = [];
            this.approvalFlowSteps = [];
            this.showDetailModal = true;
            this.loadApprovalFlow(req.No_Transaksi);
        },

        closeDetailModal() {
            this.showDetailModal = false;
            this.selectedRequest = null;
            this.processingDetails = [];
            this.detailExpandedMembers = [];
            this.approvalFlowSteps = [];
            this.approvalFlowLoading = false;
        },

        getApprovalStepName(step) {
            return step.Nama || step.Nama_Karyawan || step.Current_Approver_Name || step.Current_Approver || 'Approver';
        },

        async loadApprovalFlow(noTransaksi) {
            if (!noTransaksi) return;

            this.approvalFlowLoading = true;
            try {
                const endpoint = window.route
                    ? window.route('shift-approval.approval-flow')
                    : '/shift-approval/approval-flow';

                const res = await axios.get(endpoint, {
                    params: { no_transaksi: noTransaksi },
                });

                if (res.data?.success) {
                    const pending = res.data?.result?.pending || [];
                    const history = res.data?.result?.history || [];

                    const normalizedPending = pending.map((item) => ({
                        ...item,
                        Flag_Approval: null,
                    }));

                    const steps = [...history, ...normalizedPending].sort((a, b) => {
                        const orderA = Number(a.Request_Order_Flow || 0);
                        const orderB = Number(b.Request_Order_Flow || 0);
                        return orderA - orderB;
                    });

                    if (this.selectedRequest && this.selectedRequest.No_Transaksi === noTransaksi) {
                        this.approvalFlowSteps = steps;
                    }
                }
            } catch (error) {
                this.approvalFlowSteps = [];
            } finally {
                if (this.selectedRequest && this.selectedRequest.No_Transaksi === noTransaksi) {
                    this.approvalFlowLoading = false;
                }
            }
        },

        batchSetAction(action) {
            this.processingDetails.forEach((d) => (d.approval_status = action));
        },

        async submitApproval() {
            if (!this.selectedRequest) return;

            let mainAction = 'REJECT';
            let detailsMap = {};

            if (this.isHeaderDecisionType(this.selectedRequest.transaction_type)) {
                const decision = this.processingDetails[0]?.approval_status;
                if (!decision) {
                    this.showPageMessage('warning', 'Pilih Setujui atau Tolak terlebih dahulu.');
                    return;
                }

                if (decision === 'T' && !this.approvalNotes?.trim()) {
                    this.showPageMessage('warning', 'Alasan wajib diisi jika ada penolakan.');
                    return;
                }

                mainAction = decision === 'Y' ? 'APPROVE' : 'REJECT';
                detailsMap = {};
            } else {
                if (this.processingDetails.length === 0) {
                    this.showPageMessage('warning', 'Tidak ada detail untuk direview.');
                    return;
                }
                const allApproved = this.processingDetails.every(
                    (d) => d.approval_status === 'Y' || d.approval_status === 'T',
                );
                if (!allApproved) {
                    this.showPageMessage('warning', 'Semua tanggal harus di-approve atau di-reject');
                    return;
                }

                const hasRejected = this.processingDetails.some((d) => d.approval_status === 'T');
                if (hasRejected && !this.approvalNotes?.trim()) {
                    this.showPageMessage('warning', 'Alasan wajib diisi jika ada penolakan.');
                    return;
                }

                // Determine Main Action:
                const hasApproved = this.processingDetails.some((d) => d.approval_status === 'Y');
                mainAction = hasApproved ? 'APPROVE' : 'REJECT';

                // Map details for backend: [Id_Det => Status] (using Det table ID)
                detailsMap = {};
                this.processingDetails.forEach((d) => {
                    detailsMap[d.Id_Det] = d.approval_status;
                });
            }

            this.submitting = true;
            try {
                let url = '/shift-approval/process';
                if (typeof window.route === 'function') {
                    try {
                        // Force relative URL to keep request on same origin/session cookie.
                        url = window.route('shift-approval.process', {}, false);
                    } catch (e) {
                        url = '/shift-approval/process';
                    }
                }

                // JANGAN kirim _token / X-CSRF-TOKEN dari <meta> (token itu basi
                // setelah session di-regenerate -> 419). Andalkan cookie XSRF-TOKEN
                // yang otomatis dikirim axios sebagai X-XSRF-TOKEN; interceptor 419
                // global akan menyegarkan & retry bila perlu.
                const res = await axios.post(url, {
                    no_transaksi: this.selectedRequest.No_Transaksi,
                    action: mainAction,
                    details: detailsMap,
                    notes: this.approvalNotes,
                });

                if (res.data.success) {
                    this.showPageMessage('success', 'Approval berhasil diproses.');
                    this.closeDetailModal();
                    await Promise.allSettled([this.loadInbox(), this.loadStats(), this.loadHistory(1)]);
                }
            } catch (e) {
                // 419 = sesi/CSRF; interceptor sudah mencoba refresh+retry. Kalau
                // masih sampai sini, jangan tampilkan pesan mentah "CSRF token
                // mismatch." ke user — beri pesan ramah & minta ulangi.
                const msg =
                    e.response?.status === 419
                        ? 'Sesi Anda perlu disegarkan. Silakan coba lagi.'
                        : e.response?.data?.message || 'Terjadi kesalahan saat memproses.';
                this.showPageMessage('error', msg);
            } finally {
                this.submitting = false;
            }
        },
    },
});
</script>

<style scoped>
.el-message.shift-approval-message {
    z-index: 200000 !important;
}

/* =========================================
   PREMIUM DESIGN SYSTEM (Synced with SelfShift.vue)
   ========================================= */

/* Category Badge Colors */
.bg-indigo-subtle {
    background-color: rgba(102, 16, 242, 0.12) !important;
}
.text-indigo {
    color: #6610f2 !important;
}
.bg-teal-subtle {
    background-color: rgba(32, 201, 151, 0.12) !important;
}
.text-teal {
    color: #20c997 !important;
}

.konversi-target-highlight {
    padding: 0.85rem 1rem;
    border-radius: 12px;
    border: 1px solid rgba(25, 135, 84, 0.18);
    background: linear-gradient(135deg, rgba(25, 135, 84, 0.12), rgba(25, 135, 84, 0.04));
}

.konversi-target-highlight__label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    color: #58708d;
    margin-bottom: 0.3rem;
    display: flex;
    align-items: center;
}

.konversi-target-highlight__date {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.35;
}

.konversi-target-highlight__hint {
    margin-top: 0.2rem;
    font-size: 0.68rem;
    line-height: 1.35;
    font-weight: 500;
    color: #4f6b5f;
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
    --radius: 12px;
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

.d-flex,
.flex {
    display: flex !important;
}
.flex-column,
.flex-col {
    flex-direction: column !important;
}
.align-items-center,
.items-center {
    align-items: center !important;
}
.justify-content-between,
.justify-between {
    justify-content: space-between !important;
}
.gap-2 {
    gap: 0.5rem !important;
}
.gap-3 {
    gap: 1rem !important;
}
.gap-4 {
    gap: 1.5rem !important;
}

.p-3 {
    padding: 1rem !important;
}
.p-4 {
    padding: 1.5rem !important;
}
.py-2 {
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
}
.px-4 {
    padding-left: 1rem !important;
    padding-right: 1rem !important;
}

.w-100,
.w-full {
    width: 100% !important;
}
.h-100,
.h-full {
    height: 100% !important;
}

.fw-bold,
.font-bold {
    font-weight: 700 !important;
}
.fw-medium,
.font-medium {
    font-weight: 500 !important;
}
.small,
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
    color: var(--text);
}

.main-content-wrapper {
    position: relative;
    z-index: 10;
}

.content-container {
    min-height: 500px;
    overflow: hidden;
}

/* =========================================
   COMPONENTS
   ========================================= */

/* Stitch Card (Consistent Card Style) */
.stitch-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    transition: all 0.2s ease;
}

.stitch-card.hover-shadow:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-light);
}

/* Buttons */
.stitch-action-btn {
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
.stitch-action-btn:hover {
    background: var(--surface-soft);
    color: var(--primary);
}

/* Filter Section */
.filter-section {
    padding: 1.5rem;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    margin-bottom: 0 !important;
}

/* Tables (Modern Table) */
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

/* Badges */
.badge {
    padding: 0.35em 0.65em;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

/* Responsive Utilities */
@media (max-width: 768px) {
    .modern-table thead {
        display: none;
    }
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
        justify-content: space-between;
    }

    .date-nav-right {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }

    .today-btn,
    .add-shift-btn {
        justify-content: center;
        padding: 0.625rem !important;
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
    gap: 1rem;
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

/* Stitch Action Buttons */
.stitch-action-btn {
    width: 40px;
    height: 40px;
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

.stitch-action-btn.danger:hover {
    border-color: var(--hcis-red-200);
    background: var(--hcis-red-50);
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

.stitch-btn.primary:hover {
    background: var(--primary-dark);
}

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
    padding: 1.5rem;
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
</style>

<style scoped>
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
    --radius: 12px;
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
    border-radius: var(--radius-sm);
    transition: all 0.2s ease;
    border: 1px solid transparent;
    cursor: pointer;
}

.btn-modern.btn-primary {
    background: var(--gradient-primary);
    color: white;
    box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
}

.btn-modern.btn-primary:hover {
    filter: brightness(110%);
    transform: translateY(-1px);
    box-shadow: 0 6px 8px -1px rgba(79, 70, 229, 0.3);
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

.bg-indigo-subtle {
    background-color: rgba(102, 16, 242, 0.12) !important;
}
.text-indigo {
    color: #6610f2 !important;
}
.bg-teal-subtle {
    background-color: rgba(32, 201, 151, 0.12) !important;
}
.text-teal {
    color: #20c997 !important;
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
        justify-content: space-between;
    }

    .date-nav-right {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }

    .today-btn,
    .add-shift-btn {
        justify-content: center;
        padding: 0.625rem !important;
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
    width: 40px;
    height: 40px;
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

.stitch-action-btn.danger:hover {
    border-color: var(--hcis-red-200);
    background: var(--hcis-red-50);
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

.stitch-btn.primary:hover {
    background: var(--primary-dark);
}

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
    /* gap:2rem */
}

.detail-modal-grid {
    display: grid;
    grid-template-columns: 360px 1fr;
    min-height: 420px;
    gap: 1rem;
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
    padding: 0rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    overflow-y: auto;
    max-height: 584px;
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
</style>
