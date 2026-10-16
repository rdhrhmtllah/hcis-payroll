<template>
    <div class="menu-tab overflow-hidden" style="background: var(--surface-soft, #f8fafc)">
        <!-- Belum pilih grup -->
        <div v-if="!grupId" class="empty-state p-5 text-center">
            <i class="bi bi-arrow-up-circle fs-2 d-block mb-2 text-muted"></i>
            <p class="text-muted">Pilih Grup Fitur di atas untuk mengelola menu.</p>
        </div>

        <div v-else>
            <approval-tabs
                layout="sidebar"
                title="Opsi Menu"
                :tabs="sidebarTabs"
                :activeTab="activeSubTab"
                @tab-change="setActiveSubTab"
            >
                <template #summary>
                    <div class="approval-dashboard">
                        <!-- Hero Section -->
                        <header class="dashboard-hero">
                            <div class="hero-copy">
                                <div class="eyebrow">
                                    <i class="bi bi-activity"></i>
                                    Pusat kontrol menu
                                </div>
                                <h4>{{ selectedFeatureName }}</h4>
                                <p>Pantau sebaran hak akses menu karyawan dan log aktivitas penugasan.</p>
                                <div class="hero-badges">
                                    <span class="persona-badge">
                                        <i class="bi bi-shield-check"></i>
                                        Menu Control
                                    </span>
                                </div>
                            </div>
                            <div class="hero-actions">
                                <button
                                    class="dashboard-btn secondary"
                                    :disabled="summaryLoading"
                                    @click="fetchSummary"
                                >
                                    <i :class="['bi bi-arrow-clockwise', { spinning: summaryLoading }]"></i>
                                    Refresh
                                </button>
                                <button class="dashboard-btn primary" @click="setActiveSubTab('single')">
                                    <i class="bi bi-person-fill-gear"></i>
                                    Kelola Menu Karyawan
                                </button>
                            </div>
                        </header>

                        <!-- Loading State (Skeleton) -->
                        <div v-if="summaryLoading" class="dashboard-skeleton" aria-label="Memuat ringkasan menu">
                            <div class="skeleton-metrics">
                                <span v-for="index in 5" :key="index" class="skeleton-block"></span>
                            </div>
                            <div class="skeleton-panels">
                                <span class="skeleton-block tall"></span>
                                <span class="skeleton-block tall"></span>
                            </div>
                        </div>

                        <template v-else>
                            <!-- Metrics Grid -->
                            <div class="metric-grid mb-4">
                                <article
                                    v-for="metric in metricCards"
                                    :key="metric.key"
                                    :class="['metric-card', metric.tone]"
                                >
                                    <div class="metric-icon"><i :class="metric.icon"></i></div>
                                    <div class="metric-copy">
                                        <span>{{ metric.label }}</span>
                                        <strong>{{ metric.value }}</strong>
                                        <small>{{ metric.caption }}</small>
                                    </div>
                                </article>
                            </div>

                            <!-- Main Grid -->
                            <div class="dashboard-main-grid mb-4">
                                <!-- Left Panel: Tindakan Cepat -->
                                <article class="dashboard-panel d-flex flex-column" style="min-height: 340px">
                                    <div class="panel-heading">
                                        <div>
                                            <span class="panel-kicker">Tindakan Cepat</span>
                                            <h5>Aksi Administrator</h5>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column gap-2 flex-grow-1">
                                        <button class="quick-action-btn" @click="setActiveSubTab('single')">
                                            <span class="quick-action-icon indigo"><i class="bi bi-person-fill"></i></span>
                                            <div class="quick-action-copy">
                                                <strong>Single Assignment</strong>
                                                <small>Kelola hak akses untuk individu karyawan.</small>
                                            </div>
                                            <i class="bi bi-chevron-right quick-action-arrow"></i>
                                        </button>
                                        <button class="quick-action-btn" @click="setActiveSubTab('bulk')">
                                            <span class="quick-action-icon success"><i class="bi bi-people-fill"></i></span>
                                            <div class="quick-action-copy">
                                                <strong>Bulk Assignment</strong>
                                                <small>Tambahkan menu secara massal ke karyawan.</small>
                                            </div>
                                            <i class="bi bi-chevron-right quick-action-arrow"></i>
                                        </button>
                                        <div class="mt-auto pt-3 border-top">
                                            <label class="form-label-sm fw-bold mb-1.5" style="font-size: 0.72rem">
                                                <i class="bi bi-search me-1 text-indigo"></i> Cari Cepat Akses Karyawan
                                            </label>
                                            <el-select
                                                v-model="quickSearchUserId"
                                                filterable
                                                remote
                                                clearable
                                                placeholder="Ketik nama/kode/NIK..."
                                                :remote-method="searchKaryawanQuick"
                                                :loading="quickKaryawanLoading"
                                                style="width: 100%"
                                                @change="handleQuickSearchChange"
                                            >
                                                <el-option
                                                    v-for="k in quickKaryawanOptions"
                                                    :key="k.UserID_Web"
                                                    :label="`${k.Nama} (${k.Kode_Karyawan})`"
                                                    :value="k.UserID_Web"
                                                />
                                            </el-select>
                                        </div>
                                    </div>
                                </article>

                                <!-- Right Panel: Trend Chart -->
                                <article class="dashboard-panel d-flex flex-column" style="min-height: 340px">
                                    <div class="panel-heading">
                                        <div>
                                            <span class="panel-kicker">Aktivitas 7 hari</span>
                                            <h5>Tren Aktivitas Penugasan</h5>
                                        </div>
                                        <span class="badge bg-indigo-soft text-indigo text-xxs font-semibold"
                                            >7 Hari Terakhir</span
                                        >
                                    </div>
                                    <div class="text-muted mb-3" style="font-size: 0.72rem">
                                        Total: <strong class="text-dark">{{ totalWeeklyLogs }}</strong> log penugasan |
                                        Rata-rata harian:
                                        <strong class="text-dark">{{ averageDailyLogs }}</strong> tindakan
                                    </div>
                                    <div
                                        class="position-relative w-100 flex-grow-1 d-flex flex-column justify-content-end pt-3"
                                        style="height: 150px"
                                    >
                                        <!-- Grid Lines background -->
                                        <div
                                            class="position-absolute w-100 h-100 top-0 start-0 d-flex flex-column justify-content-between pointer-events-none"
                                            style="padding-bottom: 24px; padding-top: 10px"
                                        >
                                            <div
                                                class="border-bottom border-dashed border-gray-200 w-100"
                                                style="opacity: 0.3; border-color: #cbd5e1 !important"
                                            ></div>
                                            <div
                                                class="border-bottom border-dashed border-gray-200 w-100"
                                                style="opacity: 0.3; border-color: #cbd5e1 !important"
                                            ></div>
                                            <div
                                                class="border-bottom border-dashed border-gray-200 w-100"
                                                style="opacity: 0.3; border-color: #cbd5e1 !important"
                                            ></div>
                                            <div
                                                class="border-bottom border-dashed border-gray-200 w-100"
                                                style="opacity: 0.3; border-color: #cbd5e1 !important"
                                            ></div>
                                        </div>
                                        <!-- Bars rendering -->
                                        <div
                                            class="d-flex align-items-end justify-content-between position-relative"
                                            style="height: 110px; z-index: 2"
                                        >
                                            <div
                                                v-for="(day, index) in summaryData.weeklyTrend || []"
                                                :key="index"
                                                class="d-flex flex-column align-items-center flex-grow-1 bar-wrapper"
                                                style="width: calc(100% / 7)"
                                            >
                                                <span
                                                    class="fw-bold text-indigo text-xxs mb-1 transition-all duration-300"
                                                    style="font-size: 0.65rem"
                                                >
                                                    {{ day.count }}
                                                </span>
                                                <div
                                                    class="w-50 trend-bar"
                                                    :style="{
                                                        height: getBarHeight(day.count) + 'px',
                                                        background:
                                                            day.count > 0
                                                                ? 'linear-gradient(180deg, #6366f1 0%, #818cf8 100%)'
                                                                : '#e2e8f0',
                                                        borderRadius: '4px 4px 0 0',
                                                    }"
                                                    :title="`${day.date}: ${day.count} log tindakan`"
                                                ></div>
                                                <span
                                                    class="text-muted text-xxs mt-2 fw-semibold text-uppercase tracking-wider"
                                                    style="font-size: 0.6rem !important"
                                                >
                                                    {{ day.date }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            </div>

                            <!-- Secondary Grid -->
                            <div class="dashboard-secondary-grid">
                                <!-- Left Column: Popularity & Coverage -->
                                <div class="d-flex flex-column gap-3">
                                    <!-- Popularitas Menu Panel -->
                                    <article class="dashboard-panel">
                                        <div class="panel-heading compact">
                                            <div>
                                                <span class="panel-kicker">Popularitas Menu</span>
                                                <h5>Sebaran Akses Menu</h5>
                                            </div>
                                        </div>
                                        <div
                                            v-if="!summaryData.menuPopularity || !summaryData.menuPopularity.length"
                                            class="text-center py-4 text-muted small"
                                        >
                                            Tidak ada data sebaran menu.
                                        </div>
                                        <div
                                            v-else
                                            class="popularity-list-scroll"
                                            style="max-height: 270px; overflow: auto; padding-right: 5px"
                                        >
                                            <div
                                                v-for="item in summaryData.menuPopularity"
                                                :key="item.Jenis_page"
                                                class="mb-3 border-bottom pb-2 last-border-none"
                                            >
                                                <div class="d-flex justify-content-between align-items-center mb-1 gap-2">
                                                    <span class="fw-semibold text-dark text-truncate">{{
                                                        item.Nama_Menu
                                                    }}</span>
                                                    <span
                                                        v-if="item.employee_count === 0"
                                                        class="badge bg-danger-soft text-danger border border-danger border-opacity-10 small fw-bold flex-shrink-0"
                                                    >
                                                        Belum Digunakan
                                                    </span>
                                                    <span v-else class="badge bg-light text-secondary border flex-shrink-0">
                                                        {{ item.employee_count }} Karyawan
                                                    </span>
                                                </div>
                                                <div class="popularity-track">
                                                    <span
                                                        :style="{
                                                            width:
                                                                summaryData.stats.employeesWithAccess +
                                                                    summaryData.stats.employeesWithoutAccess >
                                                                0
                                                                    ? (item.employee_count /
                                                                          (summaryData.stats.employeesWithAccess +
                                                                              summaryData.stats.employeesWithoutAccess)) *
                                                                          100 +
                                                                      '%'
                                                                    : '0%',
                                                        }"
                                                    ></span>
                                                </div>
                                                <div class="text-muted text-monospace mt-1" style="font-size: 0.65rem">
                                                    {{ item.Jenis_page }}
                                                </div>
                                            </div>
                                        </div>
                                    </article>

                                    <!-- Rasio Cakupan Akses & Sebaran Divisi Panel -->
                                    <article class="dashboard-panel">
                                        <div class="panel-heading compact">
                                            <div>
                                                <span class="panel-kicker">Cakupan Akses</span>
                                                <h5>Rasio & Sebaran Divisi</h5>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-3 py-2 border-bottom mb-3">
                                            <div
                                                class="fs-2 fw-black text-indigo"
                                                style="font-weight: 800; line-height: 1"
                                            >
                                                {{
                                                    summaryData.stats.employeesWithAccess +
                                                        summaryData.stats.employeesWithoutAccess >
                                                    0
                                                        ? Math.round(
                                                              (summaryData.stats.employeesWithAccess /
                                                                  (summaryData.stats.employeesWithAccess +
                                                                      summaryData.stats.employeesWithoutAccess)) *
                                                                  100,
                                                          )
                                                        : 0
                                                }}%
                                            </div>
                                            <p class="small text-muted m-0" style="font-size: 0.7rem">
                                                Sebanyak
                                                <strong>{{ summaryData.stats.employeesWithAccess }}</strong> dari total
                                                <strong>{{
                                                    summaryData.stats.employeesWithAccess +
                                                    summaryData.stats.employeesWithoutAccess
                                                }}</strong>
                                                Karyawan Terikat.
                                            </p>
                                        </div>
                                        <div
                                            v-if="!summaryData.divisionCoverage || !summaryData.divisionCoverage.length"
                                            class="text-center py-2 text-muted small"
                                        >
                                            Belum ada divisi terikat akses.
                                        </div>
                                        <div v-else class="d-flex flex-column gap-2">
                                            <div
                                                v-for="div in summaryData.divisionCoverage"
                                                :key="div.name"
                                                class="d-flex justify-content-between align-items-center text-xs border-bottom pb-1 last-border-none"
                                            >
                                                <span
                                                    class="text-dark fw-semibold text-truncate"
                                                    style="max-width: 65%"
                                                    >{{ div.name || 'Lainnya' }}</span
                                                >
                                                <span class="badge bg-indigo-soft text-indigo font-monospace text-xxs"
                                                    >{{ div.count }} Karyawan</span
                                                >
                                            </div>
                                        </div>
                                    </article>
                                </div>

                                <!-- Right Column: Panels Stack -->
                                <div class="d-flex flex-column gap-3">
                                    <!-- Sebaran Akses per Header -->
                                    <article class="dashboard-panel">
                                        <div class="panel-heading compact">
                                            <div>
                                                <span class="panel-kicker">Distribusi Akses</span>
                                                <h5>Sebaran Karyawan per Header</h5>
                                            </div>
                                        </div>
                                        <div
                                            v-if="
                                                !summaryData.headerDistribution ||
                                                !summaryData.headerDistribution.length
                                            "
                                            class="text-center py-4 text-muted small"
                                        >
                                            Belum ada data distribusi akses per header.
                                        </div>
                                        <div v-else class="row g-3">
                                            <div
                                                v-for="hd in summaryData.headerDistribution"
                                                :key="hd.name"
                                                class="col-md-6"
                                            >
                                                <div
                                                    class="p-3 rounded-3 border bg-white shadow-sm hover-lift transition-all duration-300"
                                                    style="cursor: pointer; border-color: #f1f5f9 !important"
                                                    @click="openHeaderDistributionModal(hd.name)"
                                                >
                                                    <div
                                                        class="d-flex justify-content-between align-items-start mb-2 gap-2"
                                                    >
                                                        <div class="d-flex align-items-center gap-2 text-truncate">
                                                            <span
                                                                class="d-inline-flex align-items-center justify-content-center p-1.5 rounded-2 bg-indigo-soft text-indigo"
                                                                style="font-size: 0.8rem; width: 28px; height: 28px"
                                                            >
                                                                <i class="bi bi-layers-half"></i>
                                                            </span>
                                                            <strong
                                                                class="text-dark text-xs text-truncate"
                                                                :title="hd.name"
                                                                >{{ hd.name }}</strong
                                                            >
                                                        </div>
                                                        <span
                                                            class="badge bg-indigo-soft text-indigo text-xxs font-semibold flex-shrink-0"
                                                            style="font-size: 0.65rem"
                                                        >
                                                            {{ hd.count }} Karyawan
                                                        </span>
                                                    </div>
                                                    <div class="progress-container mt-2">
                                                        <div
                                                            class="d-flex justify-content-between text-xxs text-muted mb-1"
                                                            style="font-size: 0.65rem"
                                                        >
                                                            <span>Rasio Akses</span>
                                                            <span>
                                                                {{
                                                                    summaryData.stats.employeesWithAccess +
                                                                        summaryData.stats.employeesWithoutAccess >
                                                                    0
                                                                        ? Math.round(
                                                                              (hd.count /
                                                                                  (summaryData.stats
                                                                                      .employeesWithAccess +
                                                                                      summaryData.stats
                                                                                          .employeesWithoutAccess)) *
                                                                                  100,
                                                                          )
                                                                        : 0
                                                                }}%
                                                            </span>
                                                        </div>
                                                        <div
                                                            class="progress"
                                                            style="
                                                                height: 6px;
                                                                background-color: #f1f5f9;
                                                                border-radius: 3px;
                                                            "
                                                        >
                                                            <div
                                                                class="progress-bar"
                                                                role="progressbar"
                                                                :style="{
                                                                    width:
                                                                        (summaryData.stats.employeesWithAccess +
                                                                            summaryData.stats.employeesWithoutAccess >
                                                                        0
                                                                            ? (hd.count /
                                                                                  (summaryData.stats
                                                                                      .employeesWithAccess +
                                                                                      summaryData.stats
                                                                                          .employeesWithoutAccess)) *
                                                                              100
                                                                            : 0) + '%',
                                                                    background:
                                                                        'linear-gradient(90deg, #6366f1 0%, #a855f7 100%)',
                                                                    borderRadius: '3px',
                                                                }"
                                                            ></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                    <!-- Recent Activity Feed -->
                                    <article class="dashboard-panel d-flex flex-column">
                                        <div class="panel-heading compact">
                                            <div>
                                                <span class="panel-kicker">Audit Trail</span>
                                                <h5>5 Aktivitas Penugasan Terkini</h5>
                                            </div>
                                        </div>
                                        <div
                                            v-if="!summaryData.recentActivity || !summaryData.recentActivity.length"
                                            class="text-center py-5 text-muted small flex-grow-1 d-flex align-items-center justify-content-center"
                                        >
                                            Belum ada aktivitas terekam.
                                        </div>
                                        <div
                                            v-else
                                            class="activity-list flex-grow-1"
                                            style="max-height: 270px; overflow: auto; padding-right: 5px"
                                        >
                                            <div
                                                v-for="act in summaryData.recentActivity"
                                                :key="act.transaction_id"
                                                class="activity-row"
                                            >
                                                <span class="activity-dot"></span>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                                        <strong>{{ act.type || 'Perubahan Akses Menu' }}</strong>
                                                        <small class="text-muted text-xxs">{{
                                                            formatAuditDateTime(act.occurred_at)
                                                        }}</small>
                                                    </div>
                                                    <p class="m-0 text-dark" style="font-size: 0.68rem; line-height: 1.4">
                                                        {{ act.summary }}
                                                    </p>
                                                    <div class="activity-meta">
                                                        <span><i class="bi bi-person"></i> {{ act.actor_name || '-' }}</span>
                                                        <span
                                                            ><i class="bi bi-people"></i>
                                                            {{ act.target_count || 0 }} penerima</span
                                                        >
                                                        <span
                                                            ><i class="bi bi-pencil-square"></i>
                                                            {{ act.change_count || 0 }} perubahan</span
                                                        >
                                                    </div>
                                                    <small class="text-muted font-monospace">{{
                                                        act.transaction_id
                                                    }}</small>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <template #single>
                    <div class="stitch-card p-4">
                        <!-- Selector Karyawan -->
                        <div class="mb-4">
                            <label class="form-label-sm fw-bold mb-2 d-block">
                                <i class="bi bi-person-circle me-1"></i> Pilih Karyawan
                            </label>
                            <el-select
                                v-model="selectedUserId"
                                filterable
                                remote
                                clearable
                                placeholder="Cari karyawan berdasarkan nama/kode/NIK..."
                                :remote-method="searchKaryawan"
                                :loading="karyawanLoading"
                                style="width: 100%"
                                @change="handleUserChange"
                            >
                                <el-option
                                    v-for="k in karyawanOptions"
                                    :key="k.UserID_Web"
                                    :label="`${k.Nama} (${k.Kode_Karyawan} - NIK: ${k.NIK || '—'})`"
                                    :value="k.UserID_Web"
                                />
                            </el-select>
                        </div>

                        <!-- Loading -->
                        <div v-if="loading" class="text-center py-5">
                            <div class="spinner-border spinner-border-sm text-indigo" style="color: #6366f1"></div>
                            <p class="text-muted small mt-2">Memuat menu dan data karyawan...</p>
                        </div>

                        <!-- Belum pilih karyawan -->
                        <div v-else-if="!selectedUser" class="empty-state py-5 text-center text-muted">
                            <i class="bi bi-person-badge-fill" style="font-size: 2.5rem; opacity: 0.6"></i>
                            <p class="mt-2 small">Pilih Karyawan terlebih dahulu untuk mengelola hak akses menu.</p>
                        </div>

                        <div v-else>
                            <!-- Detailed User Profile Card -->
                            <div v-if="employeeDetails" class="user-profile-card p-3 mb-4 rounded-3 border">
                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    <div class="user-avatar-initials">
                                        {{ getUserInitials(employeeDetails.Nama) }}
                                    </div>
                                    <div class="user-info-text flex-grow-1">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <h6 class="m-0 fw-bold text-dark">{{ employeeDetails.Nama }}</h6>
                                            <span class="badge bg-light text-secondary border small"
                                                >{{ employeeDetails.Kode_Karyawan }} (NIK:
                                                {{ employeeDetails.NIK || '—' }})</span
                                            >
                                        </div>
                                        <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                                            <span class="badge-org"
                                                ><i class="bi bi-briefcase me-1"></i>{{ employeeDetails.Divisi }}</span
                                            >
                                            <span class="badge-org"
                                                ><i class="bi bi-award me-1"></i>{{ employeeDetails.Level }}</span
                                            >
                                        </div>
                                    </div>
                                    <div class="profile-actions">
                                        <button
                                            v-if="canAssign"
                                            class="btn-copy-access btn btn-sm d-flex align-items-center gap-1"
                                            @click="openCopyModal"
                                        >
                                            <i class="bi bi-copy"></i> Salin Akses Karyawan Lain
                                        </button>
                                    </div>
                                </div>
                                <!-- User Roles list -->
                                <div
                                    class="mt-3 pt-3 border-top"
                                    v-if="isSystemAdmin && employeeDetails.Roles && employeeDetails.Roles.length"
                                >
                                    <div class="text-muted small fw-bold mb-1">
                                        <i class="bi bi-shield-check me-1"></i>Role Aktif:
                                    </div>
                                    <div class="d-flex flex-wrap gap-1">
                                        <span
                                            v-for="r in employeeDetails.Roles"
                                            :key="r"
                                            class="badge rounded-pill bg-indigo-soft text-indigo"
                                        >
                                            {{ r }}
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-3 pt-3 border-top" v-else-if="!isSystemAdmin">
                                    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                                        <span class="text-muted small fw-bold">
                                            <i class="bi bi-shield-check me-1"></i>Akses Aktif:
                                        </span>
                                        <span
                                            class="badge bg-success-soft text-success border border-success border-opacity-20 small fw-bold"
                                        >
                                            {{ boundPages.length }} Menu Terikat
                                        </span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1" v-if="activeHeaders.length">
                                        <span
                                            v-for="h in activeHeaders"
                                            :key="h"
                                            class="badge rounded-pill bg-indigo-soft text-indigo border border-indigo border-opacity-20"
                                        >
                                            {{ h }}
                                        </span>
                                    </div>
                                    <div v-else class="text-muted small italic">Tidak ada menu aktif</div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <div>
                                    <h5 class="text-dark fw-bold m-0">
                                        <i class="bi bi-link-45deg me-2"></i>Akses Menu Karyawan
                                    </h5>
                                    <p class="small text-muted m-0">
                                        Kelola hak akses menu untuk <strong>{{ selectedUser.Nama }}</strong
                                        >.
                                        <span v-if="!canAssign" class="badge-view-only ms-1">view-only</span>
                                    </p>
                                </div>
                                <div class="binding-stats">
                                    <span class="stat-badge stat-bound">
                                        <i class="bi bi-check-circle-fill"></i> {{ boundPages.length }} aktif
                                    </span>
                                    <span class="stat-badge stat-available">
                                        <i class="bi bi-circle"></i> {{ allMenus.length - boundPages.length }} tidak
                                        aktif
                                    </span>
                                </div>
                            </div>

                            <!-- Filters & Search Toolbar -->
                            <div class="toolbar-box d-flex flex-wrap gap-2 align-items-center mb-3">
                                <div class="search-bar flex-grow-1">
                                    <i class="bi bi-search"></i>
                                    <input
                                        v-model="searchMenu"
                                        class="form-control form-control-sm"
                                        placeholder="Cari menu grup..."
                                    />
                                </div>
                                <div class="btn-group btn-group-sm border rounded-2 p-1 bg-white" role="group">
                                    <button
                                        type="button"
                                        class="btn border-0 rounded-2 py-1 px-3"
                                        :class="filterStatus === 'all' ? 'bg-indigo text-white' : 'text-secondary'"
                                        @click="filterStatus = 'all'"
                                    >
                                        Semua ({{ allMenus.length }})
                                    </button>
                                    <button
                                        type="button"
                                        class="btn border-0 rounded-2 py-1 px-3"
                                        :class="filterStatus === 'active' ? 'bg-indigo text-white' : 'text-secondary'"
                                        @click="filterStatus = 'active'"
                                    >
                                        Hanya Kategori Aktif ({{ boundPages.length }})
                                    </button>
                                    <button
                                        type="button"
                                        class="btn border-0 rounded-2 py-1 px-3"
                                        :class="filterStatus === 'inactive' ? 'bg-indigo text-white' : 'text-secondary'"
                                        @click="filterStatus = 'inactive'"
                                    >
                                        Hanya Kategori Nonaktif ({{ allMenus.length - boundPages.length }})
                                    </button>
                                </div>
                            </div>

                            <div v-if="!allMenus.length" class="empty-state text-center text-muted">
                                <i class="bi bi-info-circle"></i>
                                <p>Grup fitur ini belum memiliki menu yang terikat.</p>
                            </div>

                            <!-- Group by Header -->
                            <div
                                v-else-if="Object.keys(groupedMenus).length === 0"
                                class="empty-state py-4 text-center text-muted"
                            >
                                <i class="bi bi-search"></i>
                                <p>Tidak ada menu yang cocok dengan filter pencarian.</p>
                            </div>

                            <div
                                v-else
                                v-for="(menus, header) in groupedMenus"
                                :key="header"
                                class="header-group border rounded-3 mb-3 bg-white overflow-hidden shadow-sm"
                            >
                                <div
                                    class="header-group-title p-3 d-flex align-items-center justify-content-between cursor-pointer"
                                    @click="toggleCollapse(header)"
                                >
                                    <div class="d-flex align-items-center gap-2">
                                        <i
                                            :class="
                                                collapsed[header]
                                                    ? 'bi bi-chevron-right text-indigo'
                                                    : 'bi bi-chevron-down text-indigo'
                                            "
                                        ></i>
                                        <span class="fw-bold text-dark">{{ header || 'Tanpa Header' }}</span>
                                        <span class="header-count ms-2">
                                            {{ menus.filter((m) => boundPages.includes(m.Jenis_page)).length }}/{{
                                                menus.length
                                            }}
                                            Terpilih
                                        </span>
                                    </div>

                                    <!-- Header Quick Toggles -->
                                    <div v-if="canAssign" class="d-flex gap-2" @click.stop>
                                        <button
                                            class="btn btn-link btn-xs text-indigo p-0"
                                            @click="toggleHeaderAll(menus, true)"
                                        >
                                            Pilih Semua
                                        </button>
                                        <span class="text-muted">|</span>
                                        <button
                                            class="btn btn-link btn-xs text-secondary p-0"
                                            @click="toggleHeaderAll(menus, false)"
                                        >
                                            Kosongkan
                                        </button>
                                    </div>
                                </div>

                                <div v-if="!collapsed[header]" class="menu-check-list p-3 bg-light border-top">
                                    <div class="row g-2">
                                        <div v-for="menu in menus" :key="menu.Jenis_page" class="col-md-6 col-lg-4">
                                            <label
                                                class="menu-check-item h-100 d-flex align-items-center gap-3 p-3 rounded-3"
                                                :class="{
                                                    'menu-bound': boundPages.includes(menu.Jenis_page),
                                                    'menu-readonly': !canAssign || !menu.can_delegate,
                                                }"
                                            >
                                                <input
                                                    type="checkbox"
                                                    :value="menu.Jenis_page"
                                                    v-model="boundPages"
                                                    class="form-check-input"
                                                    :disabled="!canAssign || !menu.can_delegate"
                                                />
                                                <div class="menu-check-info">
                                                    <div class="menu-check-name text-truncate">
                                                        {{ menu.Nama_Menu }}
                                                    </div>
                                                    <div class="menu-check-page text-truncate">
                                                        {{ menu.Jenis_page }}
                                                    </div>
                                                    <div v-if="!menu.can_delegate" class="menu-lock-reason">
                                                        <i class="bi bi-lock-fill"></i> {{ menu.lock_reason }}
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Preview Changes Draft (Added/Removed diff list) -->
                            <div
                                v-if="hasChanges && canAssign"
                                class="changes-diff-preview p-3 mb-3 border rounded-3 bg-light"
                            >
                                <div class="small fw-bold text-dark mb-2">
                                    <i class="bi bi-eye me-1"></i>Pratinjau Perubahan (Draft):
                                </div>
                                <div class="d-flex flex-column gap-2">
                                    <div v-if="addedPages.length" class="d-flex align-items-start gap-2">
                                        <span class="badge bg-success small mt-1">+ Tambah</span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <span
                                                v-for="p in addedPages"
                                                :key="p"
                                                class="badge bg-success-soft text-success text-monospace border border-success border-opacity-25"
                                                >{{ getMenuName(p) }}</span
                                            >
                                        </div>
                                    </div>
                                    <div v-if="removedPages.length" class="d-flex align-items-start gap-2">
                                        <span class="badge bg-danger small mt-1">- Hapus</span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <span
                                                v-for="p in removedPages"
                                                :key="p"
                                                class="badge bg-danger-soft text-danger text-monospace text-decoration-line-through border border-danger border-opacity-25"
                                                >{{ getMenuName(p) }}</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Save Button -->
                            <div class="save-bar mt-4" v-if="canAssign && hasChanges">
                                <span class="change-note"
                                    ><i class="bi bi-exclamation-triangle-fill"></i> Ada draft perubahan akses menu yang
                                    belum disimpan!</span
                                >
                                <div class="d-flex gap-2">
                                    <button class="btn-cancel" @click="resetChanges" :disabled="saving">
                                        Batalkan Draft
                                    </button>
                                    <button class="btn-save" @click="saveBinding" :disabled="saving">
                                        <span v-if="saving"><i class="bi bi-arrow-repeat spin"></i> Menyimpan...</span>
                                        <span v-else><i class="bi bi-check-lg me-1"></i>Simpan Perubahan</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <template #bulk>
                    <div class="stitch-card p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h5 class="text-dark fw-bold m-0">
                                    <i class="bi bi-people-fill me-2"></i>Bulk Assignment Menu
                                </h5>
                                <p class="small text-muted m-0">
                                    Berikan akses menu secara massal ke beberapa karyawan sekaligus secara aman.
                                </p>
                            </div>
                        </div>

                        <!-- Stepper Progress Bar -->
                        <div class="stepper-progress mb-5 d-flex justify-content-between align-items-center px-5">
                            <div
                                class="step-progress-item"
                                :class="{ 'step-active': bulkStep === 1, 'step-done': bulkStep > 1 }"
                            >
                                <div class="step-number">1</div>
                                <div class="step-label">Pilih Karyawan</div>
                            </div>
                            <div class="step-line" :class="{ 'line-done': bulkStep > 1 }"></div>
                            <div
                                class="step-progress-item"
                                :class="{ 'step-active': bulkStep === 2, 'step-done': bulkStep > 2 }"
                            >
                                <div class="step-number">2</div>
                                <div class="step-label">Pilih Menu</div>
                            </div>
                            <div class="step-line" :class="{ 'line-done': bulkStep > 2 }"></div>
                            <div
                                class="step-progress-item"
                                :class="{ 'step-active': bulkStep === 3, 'step-done': bulkStep > 3 }"
                            >
                                <div class="step-number">3</div>
                                <div class="step-label">Konfirmasi</div>
                            </div>
                            <div class="step-line" :class="{ 'line-done': bulkStep > 3 }"></div>
                            <div class="step-progress-item" :class="{ 'step-active': bulkStep === 4 }">
                                <div class="step-number">4</div>
                                <div class="step-label">Hasil</div>
                            </div>
                        </div>

                        <!-- STEP 1: PILIH KARYAWAN -->
                        <div v-if="bulkStep === 1">
                            <!-- Toggle Mode -->
                            <div class="d-flex justify-content-center mb-4">
                                <div class="btn-group btn-group-sm border rounded-2 p-1 bg-light" role="group">
                                    <button
                                        type="button"
                                        class="btn border-0 rounded-2 py-1.5 px-4 fw-bold text-xs"
                                        :class="
                                            bulkSelectMode === 'manual'
                                                ? 'bg-white shadow-sm text-indigo'
                                                : 'text-secondary'
                                        "
                                        @click="bulkSelectMode = 'manual'"
                                    >
                                        <i class="bi bi-cursor-fill me-1"></i> Pilih Manual
                                    </button>
                                    <button
                                        type="button"
                                        class="btn border-0 rounded-2 py-1.5 px-4 fw-bold text-xs"
                                        :class="
                                            bulkSelectMode === 'filter'
                                                ? 'bg-white shadow-sm text-indigo'
                                                : 'text-secondary'
                                        "
                                        @click="bulkSelectMode = 'filter'"
                                    >
                                        <i class="bi bi-funnel-fill me-1"></i> Filter Kategori
                                    </button>
                                </div>
                            </div>

                            <!-- Pilih Manual Mode -->
                            <div v-if="bulkSelectMode === 'manual'">
                                <div class="alert alert-info border-0 rounded-3 p-3 mb-4 small shadow-sm">
                                    <i class="bi bi-info-circle-fill me-2 text-indigo"></i>
                                    Pilih satu atau lebih karyawan aktif secara manual yang ingin ditambahkan akses
                                    menunya.
                                </div>
                                <div class="mb-4">
                                    <label class="form-label-sm fw-bold">Pilih Karyawan Target:</label>
                                    <el-select
                                        v-model="bulkSelectedUserIds"
                                        multiple
                                        filterable
                                        remote
                                        reserve-keyword
                                        placeholder="Ketik nama / Kode Karyawan / NIK..."
                                        :remote-method="searchKaryawanBulk"
                                        :loading="bulkKaryawanLoading"
                                        style="width: 100%"
                                    >
                                        <el-option
                                            v-for="k in bulkKaryawanOptions"
                                            :key="k.UserID_Web"
                                            :label="`${k.Nama} (${k.Kode_Karyawan} - NIK: ${k.NIK || '—'})`"
                                            :value="k.UserID_Web"
                                        />
                                    </el-select>
                                    <div class="form-text small text-muted mt-2" v-if="bulkSelectedUserIds.length">
                                        Terpilih:
                                        <strong class="text-indigo">{{ bulkSelectedUserIds.length }}</strong> karyawan.
                                    </div>
                                </div>
                            </div>

                            <!-- Filter Kategori Mode -->
                            <div v-else>
                                <div
                                    v-if="filterOptionsEmpty"
                                    class="alert alert-warning border-0 rounded-3 p-3 mb-4 small shadow-sm"
                                >
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    Opsi filter kosong — kemungkinan scope role Anda untuk grup ini belum
                                    dikonfigurasi.
                                </div>
                                <div class="filter-panel-grid border rounded-3 p-3 bg-light mb-4 shadow-sm">
                                    <div class="row g-3">
                                        <!-- Lokasi -->
                                        <div class="col-md-4 col-sm-6">
                                            <label class="form-label-sm fw-bold">Lokasi (Cabang)</label>
                                            <el-select
                                                v-model="bulkFilters.cabang"
                                                filterable
                                                clearable
                                                placeholder="Semua Lokasi"
                                                style="width: 100%"
                                                @change="applyKaryawanFilters"
                                            >
                                                <el-option
                                                    v-for="c in filterOptions.cabang"
                                                    :key="c.value"
                                                    :label="c.label"
                                                    :value="c.value"
                                                />
                                            </el-select>
                                        </div>

                                        <!-- Divisi -->
                                        <div class="col-md-4 col-sm-6">
                                            <label class="form-label-sm fw-bold">Divisi</label>
                                            <el-select
                                                v-model="bulkFilters.divisi_id"
                                                filterable
                                                clearable
                                                placeholder="Semua Divisi"
                                                style="width: 100%"
                                                @change="
                                                    () => {
                                                        bulkFilters.sub_divisi_id = '';
                                                        applyKaryawanFilters();
                                                    }
                                                "
                                            >
                                                <el-option
                                                    v-for="d in filterOptions.divisi"
                                                    :key="d.value"
                                                    :label="d.label"
                                                    :value="d.value"
                                                />
                                            </el-select>
                                        </div>

                                        <!-- Department / Sub-divisi -->
                                        <div class="col-md-4 col-sm-6">
                                            <label class="form-label-sm fw-bold">Department / Sub-divisi</label>
                                            <el-select
                                                v-model="bulkFilters.sub_divisi_id"
                                                filterable
                                                clearable
                                                placeholder="Semua Department"
                                                style="width: 100%"
                                                @change="applyKaryawanFilters"
                                            >
                                                <el-option
                                                    v-for="sd in filteredSubDivisiOptions"
                                                    :key="sd.value"
                                                    :label="sd.label"
                                                    :value="sd.value"
                                                />
                                            </el-select>
                                        </div>

                                        <!-- Level -->
                                        <div class="col-md-4 col-sm-6">
                                            <label class="form-label-sm fw-bold">Level</label>
                                            <el-select
                                                v-model="bulkFilters.level_id"
                                                filterable
                                                clearable
                                                placeholder="Semua Level"
                                                style="width: 100%"
                                                @change="
                                                    () => {
                                                        bulkFilters.jabatan_id = '';
                                                        applyKaryawanFilters();
                                                    }
                                                "
                                            >
                                                <el-option
                                                    v-for="l in filterOptions.level"
                                                    :key="l.value"
                                                    :label="l.label"
                                                    :value="l.value"
                                                />
                                            </el-select>
                                        </div>

                                        <!-- Jabatan -->
                                        <div class="col-md-4 col-sm-6">
                                            <label class="form-label-sm fw-bold">Jabatan</label>
                                            <el-select
                                                v-model="bulkFilters.jabatan_id"
                                                filterable
                                                clearable
                                                placeholder="Semua Jabatan"
                                                style="width: 100%"
                                                @change="applyKaryawanFilters"
                                            >
                                                <el-option
                                                    v-for="j in filteredJabatanOptions"
                                                    :key="j.value"
                                                    :label="j.label"
                                                    :value="j.value"
                                                />
                                            </el-select>
                                        </div>

                                        <!-- Info/Clear -->
                                        <div class="col-md-4 col-sm-6 d-flex align-items-end justify-content-start">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-secondary w-100 py-1.5"
                                                @click="
                                                    () => {
                                                        bulkFilters = {
                                                            cabang: '',
                                                            divisi_id: '',
                                                            sub_divisi_id: '',
                                                            level_id: '',
                                                            jabatan_id: '',
                                                        };
                                                        filterResults = [];
                                                    }
                                                "
                                            >
                                                <i class="bi bi-arrow-counterclockwise me-1"></i> Bersihkan Filter
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Filter Results List -->
                                <div
                                    class="filter-results-box border rounded-3 bg-white overflow-hidden mb-4 shadow-sm"
                                >
                                    <!-- Table Header Info -->
                                    <div
                                        class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2"
                                    >
                                        <span class="text-dark fw-bold text-xs">
                                            <i class="bi bi-people-fill text-indigo me-1"></i>
                                            Hasil Filter:
                                            <span class="badge bg-indigo-soft text-indigo">{{
                                                filterResults.length
                                            }}</span>
                                            karyawan cocok
                                        </span>
                                        <span class="text-xs text-muted" v-if="bulkSelectedUserIds.length">
                                            Terpilih:
                                            <strong class="text-indigo">{{ bulkSelectedUserIds.length }}</strong>
                                            karyawan
                                        </span>
                                    </div>

                                    <!-- Loading state -->
                                    <div v-if="filterLoading" class="text-center py-5">
                                        <div
                                            class="spinner-border spinner-border-sm text-indigo"
                                            style="color: #6366f1"
                                        ></div>
                                        <p class="text-muted small mt-2">Mencari karyawan...</p>
                                    </div>

                                    <!-- Empty state -->
                                    <div
                                        v-else-if="!filterResults.length"
                                        class="empty-state py-5 text-center text-muted"
                                    >
                                        <i class="bi bi-funnel fs-2 mb-2 d-block" style="opacity: 0.5"></i>
                                        <p class="small m-0">
                                            Gunakan opsi filter di atas untuk menampilkan daftar karyawan aktif.
                                        </p>
                                    </div>

                                    <!-- Results Table -->
                                    <div v-else class="table-responsive" style="max-height: 380px; overflow-y: auto">
                                        <table class="table table-hover align-middle mb-0" style="font-size: 0.76rem">
                                            <thead class="table-light sticky-top">
                                                <tr>
                                                    <th scope="col" class="ps-3" style="width: 46px">
                                                        <input
                                                            type="checkbox"
                                                            class="form-check-input"
                                                            :checked="isAllFilteredSelected"
                                                            @change="(e) => selectAllFiltered(e.target.checked)"
                                                        />
                                                    </th>
                                                    <th scope="col">Karyawan</th>
                                                    <th scope="col">Organisasi</th>
                                                    <th scope="col">Jabatan & Level</th>
                                                    <th scope="col" class="pe-3">Lokasi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="emp in filterResults"
                                                    :key="emp.UserID_Web"
                                                    class="cursor-pointer"
                                                    @click="toggleFilteredSelection(emp)"
                                                >
                                                    <td class="ps-3" @click.stop>
                                                        <input
                                                            type="checkbox"
                                                            class="form-check-input"
                                                            :value="emp.UserID_Web"
                                                            v-model="bulkSelectedUserIds"
                                                        />
                                                    </td>
                                                    <td>
                                                        <div class="fw-bold text-dark">{{ emp.Nama }}</div>
                                                        <div class="text-muted text-xs font-monospace">
                                                            {{ emp.Kode_Karyawan }}
                                                            <span v-if="emp.NIK">(NIK: {{ emp.NIK }})</span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="text-dark font-medium">{{ emp.Divisi }}</div>
                                                        <div class="text-muted text-xs">{{ emp.SubDivisi }}</div>
                                                    </td>
                                                    <td>
                                                        <div class="text-dark">{{ emp.Jabatan }}</div>
                                                        <div class="text-muted text-xs">{{ emp.Level }}</div>
                                                    </td>
                                                    <td class="pe-3">
                                                        <span class="badge bg-light text-secondary border">{{
                                                            emp.Cabang_Untuk_Gaji || '—'
                                                        }}</span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 2: PILIH MENU -->
                        <div v-if="bulkStep === 2">
                            <div class="alert alert-info border-0 rounded-3 p-3 mb-3 small">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                Pilih menu yang akan ditambahkan ke seluruh karyawan terpilih.
                            </div>

                            <div class="toolbar-box d-flex align-items-center mb-3">
                                <div class="search-bar flex-grow-1">
                                    <i class="bi bi-search"></i>
                                    <input
                                        v-model="bulkSearchMenu"
                                        class="form-control form-control-sm"
                                        placeholder="Cari menu grup..."
                                    />
                                </div>
                            </div>

                            <div
                                class="bulk-menu-list-container mb-4"
                                style="max-height: 400px; overflow-y: auto; padding-right: 5px"
                            >
                                <div
                                    v-if="Object.keys(groupedBulkMenus).length === 0"
                                    class="empty-state py-4 text-center text-muted"
                                >
                                    <i class="bi bi-search fs-4"></i>
                                    <p class="small text-muted mt-2">Tidak ada menu yang cocok.</p>
                                </div>
                                <div
                                    v-else
                                    v-for="(menus, header) in groupedBulkMenus"
                                    :key="header"
                                    class="header-group border rounded-3 mb-3 bg-white overflow-hidden shadow-sm"
                                >
                                    <div
                                        class="header-group-title p-3 d-flex align-items-center justify-content-between cursor-pointer"
                                        @click="toggleBulkCollapse(header)"
                                    >
                                        <div class="d-flex align-items-center gap-2">
                                            <i
                                                :class="
                                                    bulkCollapsed[header]
                                                        ? 'bi bi-chevron-right text-indigo'
                                                        : 'bi bi-chevron-down text-indigo'
                                                "
                                            ></i>
                                            <span class="fw-bold text-dark text-sm">{{
                                                header || 'Tanpa Header'
                                            }}</span>
                                            <span class="header-count ms-2">
                                                {{
                                                    menus.filter((m) => bulkSelectedPages.includes(m.Jenis_page))
                                                        .length
                                                }}/{{ menus.length }} Terpilih
                                            </span>
                                        </div>
                                        <div class="d-flex gap-2" @click.stop>
                                            <button
                                                class="btn btn-link btn-xs text-indigo p-0 text-decoration-none"
                                                @click="toggleBulkHeaderAll(menus, true)"
                                            >
                                                Pilih Semua
                                            </button>
                                            <span class="text-muted text-xs">|</span>
                                            <button
                                                class="btn btn-link btn-xs text-secondary p-0 text-decoration-none"
                                                @click="toggleBulkHeaderAll(menus, false)"
                                            >
                                                Kosongkan
                                            </button>
                                        </div>
                                    </div>

                                    <div v-if="!bulkCollapsed[header]" class="menu-check-list p-3 bg-light border-top">
                                        <div class="row g-2">
                                            <div v-for="menu in menus" :key="menu.Jenis_page" class="col-md-6 col-lg-4">
                                                <label
                                                    class="menu-check-item h-100 d-flex align-items-center gap-2 p-2 rounded-3"
                                                    :class="{
                                                        'menu-bound': bulkSelectedPages.includes(menu.Jenis_page),
                                                        'menu-readonly': !menu.can_delegate,
                                                    }"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        :value="menu.Jenis_page"
                                                        v-model="bulkSelectedPages"
                                                        class="form-check-input"
                                                        :disabled="!menu.can_delegate"
                                                    />
                                                    <div class="menu-check-info">
                                                        <div
                                                            class="menu-check-name text-truncate text-sm"
                                                            style="font-size: 0.78rem"
                                                        >
                                                            {{ menu.Nama_Menu }}
                                                        </div>
                                                        <div
                                                            class="menu-check-page text-truncate"
                                                            style="
                                                                font-size: 0.65rem;
                                                                color: #94a3b8;
                                                                font-family: monospace;
                                                            "
                                                        >
                                                            {{ menu.Jenis_page }}
                                                        </div>
                                                        <div v-if="!menu.can_delegate" class="menu-lock-reason">
                                                            <i class="bi bi-lock-fill"></i> {{ menu.lock_reason }}
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 3: KONFIRMASI -->
                        <div v-if="bulkStep === 3">
                            <div
                                class="safety-panel p-3 mb-4 rounded-3 border"
                                style="background: #fffdf5; border-color: #fde68a !important"
                            >
                                <h6 class="fw-bold text-warning-dark mb-2">
                                    <i class="bi bi-shield-fill-exclamation me-1"></i> Jaminan Keamanan Hak Akses
                                </h6>
                                <p class="small text-muted m-0">
                                    Fitur bulk assignment ini menggunakan metode <strong>Append-Only</strong>. Sistem
                                    hanya akan menambahkan menu yang dicentang ke karyawan target.
                                </p>
                                <ul class="small text-muted mt-2 mb-0 ps-3">
                                    <li>
                                        Akses menu lama milik masing-masing karyawan
                                        <strong>TIDAK AKAN terhapus atau ditimpa</strong>.
                                    </li>
                                    <li>
                                        Karyawan yang sudah memiliki menu terpilih akan dilewati secara otomatis
                                        (<strong>tanpa duplikasi data</strong>).
                                    </li>
                                    <li>
                                        Karyawan target <strong>tidak akan</strong> saling berbagi akses menu lama
                                        mereka yang tidak dipilih.
                                    </li>
                                </ul>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="p-3 border rounded-3 bg-light h-100">
                                        <div class="fw-bold text-dark mb-2 small">
                                            <i class="bi bi-people-fill me-1 text-indigo"></i> Karyawan Penerima ({{
                                                bulkSelectedUserIds.length
                                            }})
                                        </div>
                                        <div class="bulk-review-list" style="max-height: 200px; overflow-y: auto">
                                            <div
                                                v-for="k in getBulkSelectedKaryawanDetails()"
                                                :key="k.UserID_Web"
                                                class="review-item py-1 border-bottom last-border-none small text-dark d-flex justify-content-between"
                                            >
                                                <span>{{ k.Nama }}</span>
                                                <span class="text-muted">{{ k.Kode_Karyawan }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded-3 bg-light h-100">
                                        <div class="fw-bold text-dark mb-2 small">
                                            <i class="bi bi-menu-button-wide-fill me-1 text-indigo"></i> Menu yang
                                            Diberikan ({{ bulkSelectedPages.length }})
                                        </div>
                                        <div class="bulk-review-list" style="max-height: 200px; overflow-y: auto">
                                            <div
                                                v-for="p in bulkSelectedPages"
                                                :key="p"
                                                class="review-item py-1 border-bottom last-border-none small text-dark d-flex justify-content-between"
                                            >
                                                <span>{{ getMenuName(p) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 4: HASIL EKSEKUSI -->
                        <div v-if="bulkStep === 4 && bulkResult">
                            <div class="text-center mb-4">
                                <div class="result-success-icon mb-2">
                                    <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem"></i>
                                </div>
                                <h5 class="fw-bold text-dark">Bulk Assignment Selesai!</h5>
                                <p class="small text-muted">Akses menu massal telah berhasil diproses oleh sistem.</p>
                            </div>

                            <div class="d-flex flex-column gap-3 mb-4">
                                <!-- Applied Section -->
                                <div
                                    v-if="bulkResult.applied && bulkResult.applied.length"
                                    class="result-card p-3 rounded-3 border border-success border-opacity-25"
                                    style="background: #f0fdf4"
                                >
                                    <div class="fw-bold text-success text-sm mb-2 d-flex justify-content-between">
                                        <span
                                            ><i class="bi bi-patch-check-fill me-1"></i> Berhasil Ditambahkan ({{
                                                bulkResult.applied.length
                                            }})</span
                                        >
                                    </div>
                                    <div class="result-scroll" style="max-height: 150px; overflow-y: auto">
                                        <div
                                            v-for="item in bulkResult.applied"
                                            :key="item.user_id"
                                            class="review-item py-1 border-bottom border-success border-opacity-10 small text-dark"
                                        >
                                            <strong>{{ item.Nama }}</strong
                                            >: ditambahkan menu
                                            <span
                                                class="badge bg-success-soft text-success border border-success border-opacity-20 ms-1"
                                                v-for="m in item.added"
                                                :key="m"
                                                >{{ getMenuNameOnly(m) }}</span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <!-- Skipped Section -->
                                <div
                                    v-if="bulkResult.skipped && bulkResult.skipped.length"
                                    class="result-card p-3 rounded-3 border border-warning border-opacity-25"
                                    style="background: #fffbeb"
                                >
                                    <div class="fw-bold text-warning-dark text-sm mb-2">
                                        <span
                                            ><i class="bi bi-exclamation-circle-fill me-1"></i> Dilewati / Sudah Ada ({{
                                                bulkResult.skipped.length
                                            }})</span
                                        >
                                    </div>
                                    <div class="result-scroll" style="max-height: 150px; overflow-y: auto">
                                        <div
                                            v-for="item in bulkResult.skipped"
                                            :key="item.user_id"
                                            class="review-item py-1 border-bottom border-warning border-opacity-10 small text-dark d-flex justify-content-between"
                                        >
                                            <span>{{ item.Nama }}</span>
                                            <span class="text-muted italic">Sudah memiliki menu ini</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Failed Section -->
                                <div
                                    v-if="bulkResult.failed && bulkResult.failed.length"
                                    class="result-card p-3 rounded-3 border border-danger border-opacity-25"
                                    style="background: #fef2f2"
                                >
                                    <div class="fw-bold text-danger text-sm mb-2">
                                        <span
                                            ><i class="bi bi-x-circle-fill me-1"></i> Gagal Diproses ({{
                                                bulkResult.failed.length
                                            }})</span
                                        >
                                    </div>
                                    <div class="result-scroll" style="max-height: 150px; overflow-y: auto">
                                        <div
                                            v-for="item in bulkResult.failed"
                                            :key="item.user_id"
                                            class="review-item py-1 border-bottom border-danger border-opacity-10 small text-dark d-flex justify-content-between"
                                        >
                                            <span>User ID: {{ item.user_id }}</span>
                                            <span class="text-danger">{{ item.reason }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer buttons inside content card -->
                        <div class="border-top pt-3 d-flex justify-content-between">
                            <div>
                                <button v-if="bulkStep < 4" class="btn btn-sm btn-light border" @click="resetBulkState">
                                    Reset
                                </button>
                            </div>
                            <div class="d-flex gap-2">
                                <button
                                    v-if="bulkStep > 1 && bulkStep < 4"
                                    class="btn btn-sm btn-light border"
                                    @click="bulkStep--"
                                >
                                    Kembali
                                </button>

                                <button
                                    v-if="bulkStep === 1"
                                    class="btn btn-sm btn-primary bg-indigo border-indigo"
                                    :disabled="!bulkSelectedUserIds.length"
                                    @click="proceedToStep2"
                                >
                                    Lanjut Pilih Menu <i class="bi bi-arrow-right ms-1"></i>
                                </button>

                                <button
                                    v-if="bulkStep === 2"
                                    class="btn btn-sm btn-primary bg-indigo border-indigo"
                                    :disabled="!bulkSelectedPages.length"
                                    @click="bulkStep = 3"
                                >
                                    Lanjut Konfirmasi <i class="bi bi-arrow-right ms-1"></i>
                                </button>

                                <button
                                    v-if="bulkStep === 3"
                                    class="btn btn-sm btn-success"
                                    :disabled="bulkSaving"
                                    @click="executeBulkAssign"
                                >
                                    <span v-if="bulkSaving"><i class="bi bi-arrow-repeat spin"></i> Memproses...</span>
                                    <span v-else><i class="bi bi-check-lg me-1"></i>Ya, Berikan Akses</span>
                                </button>

                                <button
                                    v-if="bulkStep === 4"
                                    class="btn btn-sm btn-primary bg-indigo border-indigo"
                                    @click="setActiveSubTab('single')"
                                >
                                    Selesai & Kembali
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <template #history>
                    <TransactionHistory
                        mode="menu"
                        :items="historyItems"
                        :loading="historyLoading"
                        :featureName="selectedFeatureName"
                        @refresh="fetchHistory"
                    />
                </template>

                <template #matrix>
                    <MenuKaryawanMatrixPanel
                        :grup-id="grupId"
                        :can-assign="canAssign"
                        :active-grup="activeGrup"
                        @assign-user="goToEmployeeAccess"
                    />
                </template>

                <template #view-access>
                    <div class="stitch-card p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h5 class="text-dark fw-bold m-0">
                                    <i class="bi bi-eye-fill me-2 text-indigo"></i>Lihat Akses Menu Karyawan
                                </h5>
                                <p class="small text-muted m-0">
                                    Lihat daftar menu aktif yang dimiliki oleh karyawan secara langsung.
                                </p>
                            </div>
                        </div>

                        <!-- Selector Karyawan -->
                        <div class="mb-4">
                            <label class="form-label-sm fw-bold mb-2 d-block">
                                <i class="bi bi-person-circle me-1"></i> Pilih Karyawan
                            </label>
                            <el-select
                                v-model="viewSelectedUserId"
                                filterable
                                remote
                                clearable
                                placeholder="Cari karyawan berdasarkan nama/kode/NIK..."
                                :remote-method="searchKaryawanView"
                                :loading="viewKaryawanLoading"
                                style="width: 100%"
                                @change="handleViewUserChange"
                            >
                                <el-option
                                    v-for="k in viewKaryawanOptions"
                                    :key="k.UserID_Web"
                                    :label="`${k.Nama} (${k.Kode_Karyawan} - NIK: ${k.NIK || '—'})`"
                                    :value="k.UserID_Web"
                                />
                            </el-select>
                        </div>

                        <!-- Loading -->
                        <div v-if="viewLoading" class="text-center py-5">
                            <div class="spinner-border spinner-border-sm text-indigo" style="color: #6366f1"></div>
                            <p class="text-muted small mt-2">Memuat data akses menu...</p>
                        </div>

                        <!-- Belum pilih karyawan -->
                        <div v-else-if="!viewSelectedUser" class="empty-state py-5 text-center text-muted">
                            <i class="bi bi-shield-lock" style="font-size: 2.5rem; opacity: 0.6"></i>
                            <p class="mt-2 small">
                                Pilih Karyawan terlebih dahulu untuk melihat daftar akses menu mereka.
                            </p>
                        </div>

                        <div v-else>
                            <!-- User Profile Card -->
                            <div
                                v-if="viewEmployeeDetails"
                                class="user-profile-card p-3 mb-4 rounded-3 border bg-white"
                            >
                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    <div class="user-avatar-initials bg-indigo-soft text-indigo">
                                        {{ getUserInitials(viewEmployeeDetails.Nama) }}
                                    </div>
                                    <div class="user-info-text flex-grow-1">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <h6 class="m-0 fw-bold text-dark">{{ viewEmployeeDetails.Nama }}</h6>
                                            <span class="badge bg-light text-secondary border small"
                                                >{{ viewEmployeeDetails.Kode_Karyawan }} (NIK:
                                                {{ viewEmployeeDetails.NIK || '—' }})</span
                                            >
                                        </div>
                                        <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                                            <span class="badge-org"
                                                ><i class="bi bi-briefcase me-1"></i
                                                >{{ viewEmployeeDetails.Divisi }}</span
                                            >
                                            <span class="badge-org"
                                                ><i class="bi bi-award me-1"></i>{{ viewEmployeeDetails.Level }}</span
                                            >
                                        </div>
                                    </div>
                                </div>
                                <!-- Roles list -->
                                <div
                                    class="mt-3 pt-3 border-top"
                                    v-if="
                                        isSystemAdmin && viewEmployeeDetails.Roles && viewEmployeeDetails.Roles.length
                                    "
                                >
                                    <div class="text-muted small fw-bold mb-1">
                                        <i class="bi bi-shield-check me-1"></i>Role Aktif:
                                    </div>
                                    <div class="d-flex flex-wrap gap-1">
                                        <span
                                            v-for="r in viewEmployeeDetails.Roles"
                                            :key="r"
                                            class="badge rounded-pill bg-indigo-soft text-indigo"
                                        >
                                            {{ r }}
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-3 pt-3 border-top" v-else-if="!isSystemAdmin">
                                    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                                        <span class="text-muted small fw-bold">
                                            <i class="bi bi-shield-check me-1"></i>Akses Aktif:
                                        </span>
                                        <span
                                            class="badge bg-success-soft text-success border border-success border-opacity-20 small fw-bold"
                                        >
                                            {{ viewBoundPages.length }} Menu Terikat
                                        </span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1" v-if="viewActiveHeaders.length">
                                        <span
                                            v-for="h in viewActiveHeaders"
                                            :key="h"
                                            class="badge rounded-pill bg-indigo-soft text-indigo border border-indigo border-opacity-20"
                                        >
                                            {{ h }}
                                        </span>
                                    </div>
                                    <div v-else class="text-muted small italic">Tidak ada menu aktif</div>
                                </div>
                            </div>

                            <!-- List of Owned Menus -->
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                                <div>
                                    <h6 class="text-dark fw-bold m-0">
                                        <i class="bi bi-check-all me-2 text-success"></i>Menu yang Dimiliki
                                    </h6>
                                    <p class="small text-muted m-0">
                                        Daftar menu dalam grup ini yang aktif untuk
                                        <strong>{{ viewSelectedUser.Nama }}</strong
                                        >.
                                    </p>
                                </div>
                                <div class="binding-stats">
                                    <span
                                        class="stat-badge stat-bound bg-success-soft text-success border border-success border-opacity-25 px-2 py-1 rounded-2"
                                    >
                                        <i class="bi bi-check-circle-fill"></i> {{ viewActiveMenus.length }} Menu Aktif
                                    </span>
                                </div>
                            </div>

                            <div
                                v-if="!viewActiveMenus.length"
                                class="empty-state text-center text-muted py-5 border rounded-3 bg-white"
                            >
                                <i class="bi bi-exclamation-triangle fs-3 text-warning mb-2 d-block"></i>
                                <p class="m-0 small">
                                    Karyawan ini tidak memiliki akses ke menu apa pun dalam grup fitur ini.
                                </p>
                            </div>

                            <div v-else class="row g-3">
                                <div v-for="(menus, header) in groupedViewActiveMenus" :key="header" class="col-12">
                                    <div class="border rounded-3 bg-white overflow-hidden shadow-sm">
                                        <div
                                            class="header-group-title p-3 bg-light border-bottom d-flex align-items-center gap-2"
                                        >
                                            <i class="bi bi-folder-fill text-indigo"></i>
                                            <span class="fw-bold text-dark">{{ header || 'Tanpa Header' }}</span>
                                            <span class="badge rounded-pill bg-indigo-soft text-indigo ms-auto"
                                                >{{ menus.length }} Menu</span
                                            >
                                        </div>
                                        <div class="p-3">
                                            <div class="row g-2">
                                                <div
                                                    v-for="menu in menus"
                                                    :key="menu.Jenis_page"
                                                    class="col-md-6 col-lg-4"
                                                >
                                                    <div
                                                        class="menu-check-item menu-bound h-100 d-flex align-items-center gap-3 p-3 rounded-3 border bg-white"
                                                    >
                                                        <i class="bi bi-shield-fill-check text-success fs-5"></i>
                                                        <div class="menu-check-info">
                                                            <div
                                                                class="menu-check-name text-truncate fw-bold text-dark"
                                                            >
                                                                {{ menu.Nama_Menu }}
                                                            </div>
                                                            <div
                                                                class="menu-check-page text-truncate text-muted small"
                                                                style="font-family: monospace"
                                                            >
                                                                {{ menu.Jenis_page }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </approval-tabs>
        </div>

        <!-- Dialog Header Distribution Users List -->
        <el-dialog
            v-model="headerModalVisible"
            :title="`Karyawan dengan Akses Kategori: ${selectedHeaderName}`"
            width="min(760px, 94vw)"
            destroy-on-close
            append-to-body
            custom-class="premium-dialog"
        >
            <div class="p-1">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="small text-muted">
                        Daftar karyawan yang memiliki akses ke menu di bawah kategori <strong>{{ selectedHeaderName }}</strong>.
                    </div>
                    <span class="badge bg-indigo-soft text-indigo font-semibold px-2.5 py-1 rounded-pill" style="font-size: 0.72rem;">
                        {{ headerUsers.length }} Total Karyawan
                    </span>
                </div>

                <!-- Search inside modal -->
                <div class="mb-3" v-if="headerUsers.length > 5">
                    <el-input
                        v-model="headerUsersSearch"
                        clearable
                        size="small"
                        placeholder="Cari karyawan berdasarkan nama, NIK, atau divisi..."
                    >
                        <template #prefix>
                            <i class="bi bi-search text-muted"></i>
                        </template>
                    </el-input>
                </div>

                <div v-loading="headerUsersLoading" style="min-height: 180px; max-height: 420px; overflow-y: auto; padding-right: 4px;">
                    <div v-if="!headerUsers || !headerUsers.length" class="text-center py-5 text-muted small">
                        <i class="bi bi-people fs-2 text-slate-300 d-block mb-2"></i>
                        Tidak ada karyawan yang terikat akses pada kategori header ini.
                    </div>
                    <div v-else-if="!filteredHeaderUsers.length" class="text-center py-4 text-muted small">
                        <i class="bi bi-search fs-3 text-slate-300 d-block mb-1"></i>
                        Tidak ada karyawan yang cocok dengan pencarian "{{ headerUsersSearch }}".
                    </div>
                    <div v-else class="d-flex flex-column gap-2.5">
                        <div
                            v-for="user in filteredHeaderUsers"
                            :key="user.UserID_Web"
                            class="p-3 bg-white rounded-3 border hover-lift transition-all duration-300"
                            style="border-color: #eef2f6 !important;"
                        >
                            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                <div class="flex-grow-1 min-width-0">
                                    <h6 class="mb-1.5 fw-bold text-dark text-truncate" style="font-size: 0.88rem;">{{ user.Nama }}</h6>
                                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                        <span class="badge bg-light text-secondary border text-xxs font-monospace" style="font-size: 0.65rem;">
                                            {{ user.Kode_Karyawan }} <span v-if="user.NIK">(NIK: {{ user.NIK }})</span>
                                        </span>
                                        <span class="badge bg-indigo-soft text-indigo text-xxs border border-indigo border-opacity-10" style="font-size: 0.65rem;">
                                            {{ user.Divisi }}
                                        </span>
                                        <span v-if="user.Level" class="badge bg-light text-muted text-xxs border" style="font-size: 0.65rem;">
                                            {{ user.Level }}
                                        </span>
                                    </div>
                                </div>
                                <button
                                    class="btn btn-sm d-inline-flex align-items-center gap-1 text-xxs fw-semibold px-2.5 py-1.5 rounded-2"
                                    style="font-size: 0.68rem; border: 1px solid #c7d2fe !important; color: #4f46e5; background: #eef2ff;"
                                    @click="goToEmployeeAccess(user)"
                                >
                                    <i class="bi bi-person-gear"></i> Kelola Akses
                                </button>
                            </div>
                            <!-- Badges of Active Pages/Menus under this Header -->
                            <div class="mt-2.5 pt-2.5 border-top" style="border-color: #f1f5f9 !important;">
                                <span class="text-muted text-xxs fw-bold d-block mb-1.5" style="font-size: 0.65rem;">Menu yang Dimiliki:</span>
                                <div class="d-flex flex-wrap gap-1.5">
                                    <span
                                        v-for="page in user.Pages"
                                        :key="page.Jenis_page"
                                        class="badge rounded-pill bg-indigo-soft text-indigo border border-indigo border-opacity-15 font-semibold text-xxs"
                                        style="font-size: 0.65rem; padding: 0.25rem 0.6rem;"
                                    >
                                        {{ page.Nama_Menu }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-sm btn-light border px-3" @click="headerModalVisible = false">
                        Tutup
                    </button>
                </div>
            </template>
        </el-dialog>

        <!-- Dialog Copy Menu Access from other employee -->
        <el-dialog
            v-model="copyModalVisible"
            title="Salin Hak Akses Menu"
            width="550px"
            destroy-on-close
            append-to-body
            custom-class="premium-dialog"
        >
            <div class="p-2">
                <div class="alert alert-info border-0 rounded-3 p-3 mb-4 small">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    Tindakan ini akan menimpa draft centang menu <strong>{{ selectedUser?.Nama }}</strong> dengan hak
                    akses menu milik karyawan yang Anda pilih di bawah. Perubahan baru akan benar-benar tersimpan
                    setelah Anda menekan tombol "Simpan Perubahan".
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Pilih Karyawan Sumber (Source):</label>
                    <el-select
                        v-model="sourceUserId"
                        filterable
                        remote
                        clearable
                        placeholder="Cari karyawan berdasarkan nama/kode..."
                        :remote-method="searchKaryawanSource"
                        :loading="sourceLoading"
                        style="width: 100%"
                    >
                        <el-option
                            v-for="k in sourceKaryawanOptions"
                            :key="k.UserID_Web"
                            :label="`${k.Nama} (${k.Kode_Karyawan})`"
                            :value="k.UserID_Web"
                            :disabled="k.UserID_Web === selectedUser?.UserID_Web"
                        />
                    </el-select>
                </div>
            </div>
            <template #footer>
                <div class="d-flex justify-content-end gap-2">
                    <button class="btn btn-sm btn-light border" @click="copyModalVisible = false">Batal</button>
                    <button
                        class="btn btn-sm btn-primary"
                        style="background: #6366f1; border-color: #6366f1"
                        :disabled="!sourceUserId"
                        @click="executeCopy"
                    >
                        Terapkan Salin Akses
                    </button>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import ApprovalTabs from '@/components/legacy/toggleSectionV2.vue';
import TransactionHistory from './TransactionHistory.vue';
import MenuKaryawanMatrixPanel from './MenuKaryawanMatrixPanel.vue';

export default {
    name: 'MenuTab',
    components: {
        ApprovalTabs,
        TransactionHistory,
        MenuKaryawanMatrixPanel,
    },
    props: {
        grupId: { type: Number, default: null },
        canAssign: { type: Boolean, default: false },
        activeGrup: { type: Object, required: true },
        isSystemAdmin: { type: Boolean, default: false },
    },
    data() {
        return {
            activeSubTab: 'summary', // "summary", "matrix", "single", "bulk", "history", "view-access"
            summaryLoading: false,
            summaryData: {
                stats: {
                    totalMenus: 0,
                    employeesWithAccess: 0,
                    employeesWithoutAccess: 0,
                    actionsToday: 0,
                },
                menuPopularity: [],
                weeklyTrend: [],
            },
            loading: false,
            saving: false,
            allMenus: [],
            boundPages: [],
            originalBound: [],
            searchMenu: '',
            collapsed: {},
            filterStatus: 'all', // 'all', 'active', 'inactive'
            employeeDetails: null,

            selectedUserId: null,
            selectedUser: null,
            karyawanOptions: [],
            karyawanLoading: false,

            // Copy access modal properties
            copyModalVisible: false,
            sourceUserId: null,
            sourceKaryawanOptions: [],
            sourceLoading: false,

            // Bulk assignment properties
            bulkStep: 1,
            bulkSelectedUserIds: [],
            bulkSelectedPages: [],
            bulkKaryawanOptions: [],
            bulkKaryawanLoading: false,
            bulkSearchMenu: '',
            bulkCollapsed: {},
            bulkSaving: false,
            bulkResult: null,
            bulkSelectMode: 'manual', // 'manual' or 'filter'
            filterOptions: {
                cabang: [],
                divisi: [],
                subDivisi: [],
                level: [],
                jabatan: [],
            },
            bulkFilters: {
                cabang: '',
                divisi_id: '',
                sub_divisi_id: '',
                level_id: '',
                jabatan_id: '',
            },
            filterLoading: false,
            filterResults: [],
            filterOptionsLoaded: false,

            // History properties
            historyItems: [],
            historyLoading: false,
            historySearch: '',

            // View access properties
            viewSelectedUserId: null,
            viewSelectedUser: null,
            viewKaryawanOptions: [],
            viewKaryawanLoading: false,
            viewLoading: false,
            viewAllMenus: [],
            viewBoundPages: [],
            viewEmployeeDetails: null,

            // Dashboard stale access & quick search variables
            purgingStale: false,
            showStaleDetails: false,
            quickSearchUserId: null,
            quickKaryawanLoading: false,
            quickKaryawanOptions: [],

            // Header distribution modal variables
            headerModalVisible: false,
            selectedHeaderName: '',
            headerUsers: [],
            headerUsersSearch: '',
            headerUsersLoading: false,
        };
    },
    computed: {
        selectedFeatureName() {
            return this.activeGrup?.Nama_Grup_Fitur || this.activeGrup?.Kode_Grup_Fitur || 'Fitur terpilih';
        },
        filteredHeaderUsers() {
            if (!this.headerUsersSearch) return this.headerUsers;
            const q = this.headerUsersSearch.trim().toLowerCase();
            return this.headerUsers.filter(
                (u) =>
                    (u.Nama && u.Nama.toLowerCase().includes(q)) ||
                    (u.Kode_Karyawan && u.Kode_Karyawan.toLowerCase().includes(q)) ||
                    (u.NIK && String(u.NIK).toLowerCase().includes(q)) ||
                    (u.Divisi && u.Divisi.toLowerCase().includes(q)),
            );
        },
        activeHeaders() {
            if (!this.employeeDetails || !this.boundPages || !this.allMenus) return [];
            const headers = new Set();
            this.boundPages.forEach((page) => {
                const menu = this.allMenus.find((m) => m.Jenis_page === page);
                if (menu && menu.Header_Label) {
                    headers.add(menu.Header_Label);
                }
            });
            return Array.from(headers);
        },
        viewActiveHeaders() {
            if (!this.viewEmployeeDetails || !this.viewBoundPages || !this.viewAllMenus) return [];
            const headers = new Set();
            this.viewBoundPages.forEach((page) => {
                const menu = this.viewAllMenus.find((m) => m.Jenis_page === page);
                if (menu && menu.Header_Label) {
                    headers.add(menu.Header_Label);
                }
            });
            return Array.from(headers);
        },
        metricCards() {
            return [
                {
                    key: 'total_menus',
                    label: 'Total Menu',
                    value: this.summaryData.stats.totalMenus || 0,
                    caption: 'Terdaftar dalam grup',
                    icon: 'bi bi-folder2-open',
                    tone: 'indigo',
                },
                {
                    key: 'employees_bound',
                    label: 'Karyawan Terikat',
                    value: this.summaryData.stats.employeesWithAccess || 0,
                    caption: 'Memiliki akses aktif',
                    icon: 'bi bi-people-fill',
                    tone: 'teal',
                },
                {
                    key: 'no_access',
                    label: 'Tanpa Akses',
                    value: this.summaryData.stats.employeesWithoutAccess || 0,
                    caption: 'Dari total karyawan aktif',
                    icon: 'bi bi-person-dash',
                    tone: 'blue',
                },
                {
                    key: 'logs_today',
                    label: 'Log Hari Ini',
                    value: this.summaryData.stats.actionsToday || 0,
                    caption: 'Tindakan penugasan',
                    icon: 'bi bi-activity',
                    tone: 'blue',
                },
            ];
        },
        filteredMenus() {
            let list = this.allMenus;
            if (this.searchMenu) {
                const s = this.searchMenu.toLowerCase();
                list = list.filter(
                    (m) => m.Nama_Menu?.toLowerCase().includes(s) || m.Jenis_page?.toLowerCase().includes(s),
                );
            }
            if (this.filterStatus === 'active') {
                list = list.filter((m) => this.boundPages.includes(m.Jenis_page));
            } else if (this.filterStatus === 'inactive') {
                list = list.filter((m) => !this.boundPages.includes(m.Jenis_page));
            }
            return list;
        },
        groupedMenus() {
            return this.filteredMenus.reduce((acc, m) => {
                const h = m.Header_Label || '';
                if (!acc[h]) acc[h] = [];
                acc[h].push(m);
                return acc;
            }, {});
        },
        hasChanges() {
            const a = [...this.boundPages].sort();
            const b = [...this.originalBound].sort();
            return JSON.stringify(a) !== JSON.stringify(b);
        },
        addedPages() {
            return this.boundPages.filter((p) => !this.originalBound.includes(p));
        },
        removedPages() {
            return this.originalBound.filter((p) => !this.boundPages.includes(p));
        },
        groupedBulkMenus() {
            let list = this.allMenus;
            if (this.bulkSearchMenu) {
                const s = this.bulkSearchMenu.toLowerCase();
                list = list.filter(
                    (m) => m.Nama_Menu?.toLowerCase().includes(s) || m.Jenis_page?.toLowerCase().includes(s),
                );
            }
            return list.reduce((acc, m) => {
                const h = m.Header_Label || '';
                if (!acc[h]) acc[h] = [];
                acc[h].push(m);
                return acc;
            }, {});
        },
        filteredSubDivisiOptions() {
            if (!this.filterOptions.subDivisi) return [];
            if (!this.bulkFilters.divisi_id) return this.filterOptions.subDivisi;
            return this.filterOptions.subDivisi.filter((item) => item.divisi_id === this.bulkFilters.divisi_id);
        },
        filteredJabatanOptions() {
            if (!this.filterOptions.jabatan) return [];
            if (!this.bulkFilters.level_id) return this.filterOptions.jabatan;
            return this.filterOptions.jabatan.filter((item) => item.level_id === this.bulkFilters.level_id);
        },
        filterOptionsEmpty() {
            const f = this.filterOptions || {};
            return (
                this.filterOptionsLoaded &&
                !(f.cabang?.length || f.divisi?.length || f.subDivisi?.length || f.level?.length || f.jabatan?.length)
            );
        },
        isAllFilteredSelected() {
            if (!this.filterResults.length) return false;
            return this.filterResults.every((item) => this.bulkSelectedUserIds.includes(item.UserID_Web));
        },
        filteredHistory() {
            let list = this.historyItems;
            if (this.historySearch) {
                const s = this.historySearch.toLowerCase();
                list = list.filter(
                    (h) =>
                        h.operator_nama?.toLowerCase().includes(s) ||
                        h.target_nama?.toLowerCase().includes(s) ||
                        h.no_transaksi?.toLowerCase().includes(s) ||
                        h.target_kode?.toLowerCase().includes(s),
                );
            }
            return list;
        },
        viewActiveMenus() {
            return this.viewAllMenus.filter((m) => this.viewBoundPages.includes(m.Jenis_page));
        },
        groupedViewActiveMenus() {
            return this.viewActiveMenus.reduce((acc, m) => {
                const h = m.Header_Label || '';
                if (!acc[h]) acc[h] = [];
                acc[h].push(m);
                return acc;
            }, {});
        },
        totalWeeklyLogs() {
            if (!this.summaryData?.weeklyTrend || !this.summaryData.weeklyTrend.length) return 0;
            return this.summaryData.weeklyTrend.reduce((acc, curr) => acc + curr.count, 0);
        },
        averageDailyLogs() {
            if (!this.summaryData?.weeklyTrend || !this.summaryData.weeklyTrend.length) return 0;
            return Math.round((this.totalWeeklyLogs / this.summaryData.weeklyTrend.length) * 10) / 10;
        },
        sidebarTabs() {
            return [
                {
                    id: 'summary',
                    label: 'Ringkasan',
                    icon: 'bi bi-grid-1x2-fill',
                    count: null,
                    section: 'Kelola Akses',
                },
                {
                    id: 'view-access',
                    label: 'Lihat Akses',
                    icon: 'bi bi-eye-fill',
                    count: null,
                    section: 'Kelola Akses',
                },
                {
                    id: 'matrix',
                    label: 'Matrix Karyawan',
                    icon: 'bi bi-grid-3x3-gap-fill',
                    count: null,
                    section: 'Kelola Akses',
                },
                {
                    id: 'single',
                    label: 'Single Assign',
                    icon: 'bi bi-person-fill',
                    count: null,
                    section: 'Kelola Akses',
                },
                { id: 'bulk', label: 'Bulk Assign', icon: 'bi bi-people-fill', count: null, section: 'Kelola Akses' },
                {
                    id: 'history',
                    label: 'Riwayat Assign',
                    icon: 'bi bi-clock-history',
                    count: null,
                    section: 'Audit Trail',
                },
            ];
        },
    },
    watch: {
        grupId: {
            immediate: true,
            handler() {
                this.resetUserAndBinding();
                if (this.activeSubTab === 'history') {
                    this.fetchHistory();
                } else if (this.activeSubTab === 'bulk') {
                    this.resetBulkState();
                    this.loadGroupMenus();
                    this.searchKaryawanBulk('');
                    this.fetchFilterOptions();
                } else if (this.activeSubTab === 'view-access') {
                    this.resetViewState();
                    this.searchKaryawanView('');
                } else if (this.activeSubTab === 'summary') {
                    this.fetchSummary();
                }
            },
        },
    },
    methods: {
        setActiveSubTab(tab) {
            this.activeSubTab = tab;
            if (tab === 'history') {
                this.fetchHistory();
            } else if (tab === 'bulk') {
                this.resetBulkState();
                this.searchKaryawanBulk('');
                this.loadGroupMenus();
                this.fetchFilterOptions();
            } else if (tab === 'view-access') {
                this.resetViewState();
                this.searchKaryawanView('');
            } else if (tab === 'summary') {
                this.fetchSummary();
            }
        },
        async fetchHistory() {
            if (!this.grupId) return;
            this.historyLoading = true;
            try {
                const { data } = await axios.get(`/api/v1/menu-approval-matrix/menu-binding/${this.grupId}/history`);
                this.historyItems = data.items || [];
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat riwayat menu binding.');
            } finally {
                this.historyLoading = false;
            }
        },
        async searchKaryawanView(query) {
            this.viewKaryawanLoading = true;
            try {
                const { data } = await axios.get('/api/v1/menu-approval-matrix/karyawan/search', {
                    params: { grup_id: this.grupId, purpose: 'target', search: query || undefined },
                });
                this.viewKaryawanOptions = data.items || [];
            } catch {
                this.viewKaryawanOptions = [];
            } finally {
                this.viewKaryawanLoading = false;
            }
        },
        async handleViewUserChange(userIdWeb) {
            if (!userIdWeb) {
                this.viewSelectedUser = null;
                this.viewAllMenus = [];
                this.viewBoundPages = [];
                this.viewEmployeeDetails = null;
                return;
            }
            const picked = this.viewKaryawanOptions.find((k) => k.UserID_Web === userIdWeb);
            if (picked) {
                this.viewSelectedUser = picked;
                await this.fetchViewBinding();
            }
        },
        async fetchViewBinding() {
            if (!this.grupId || !this.viewSelectedUser) return;
            this.viewLoading = true;
            try {
                const { data } = await axios.get(`/api/v1/menu-approval-matrix/menu-binding/${this.grupId}`, {
                    params: {
                        user_id: this.viewSelectedUser.UserID_Web,
                    },
                });
                this.viewAllMenus = data.allMenus || [];
                this.viewBoundPages = data.boundPages || [];
                this.viewEmployeeDetails = data.employeeDetails || null;
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat menu binding.');
            } finally {
                this.viewLoading = false;
            }
        },
        resetViewState() {
            this.viewSelectedUserId = null;
            this.viewSelectedUser = null;
            this.viewAllMenus = [];
            this.viewBoundPages = [];
            this.viewEmployeeDetails = null;
            this.viewKaryawanOptions = [];
        },
        getUserInitials(name) {
            if (!name) return '?';
            const parts = name.split(' ').filter((p) => p.length > 0);
            if (parts.length >= 2) {
                return (parts[0][0] + parts[1][0]).toUpperCase();
            }
            return parts[0][0].toUpperCase();
        },
        getMenuName(jenisPage) {
            const menu = this.allMenus.find((m) => m.Jenis_page === jenisPage);
            return menu ? `${menu.Nama_Menu} (${jenisPage})` : jenisPage;
        },
        resetUserAndBinding() {
            this.selectedUserId = null;
            this.selectedUser = null;
            this.allMenus = [];
            this.boundPages = [];
            this.originalBound = [];
            this.collapsed = {};
            this.filterStatus = 'all';
            this.employeeDetails = null;
            this.resetViewState();
            this.summaryData = {
                stats: {
                    totalMenus: 0,
                    employeesWithAccess: 0,
                    employeesWithoutAccess: 0,
                    actionsToday: 0,
                },
                menuPopularity: [],
                weeklyTrend: [],
            };
        },
        async fetchSummary() {
            if (!this.grupId) return;
            this.summaryLoading = true;
            try {
                const { data } = await axios.get(`/api/v1/menu-approval-matrix/menu-binding/${this.grupId}/summary`);
                this.summaryData = data;
            } catch (e) {
                ElMessage.error('Gagal memuat summary menu.');
            } finally {
                this.summaryLoading = false;
            }
        },
        async executePurgeStale() {
            if (!this.grupId || !this.summaryData.staleEmployees || !this.summaryData.staleEmployees.length) return;
            const ids = this.summaryData.staleEmployees.map((emp) => emp.UserID_Web);
            this.purgingStale = true;
            try {
                const { data } = await axios.post(
                    `/api/v1/menu-approval-matrix/menu-binding/${this.grupId}/purge-stale`,
                    {
                        user_ids: ids,
                    },
                );
                ElMessage.success(data.message || 'Akses stale berhasil dibersihkan.');
                // Refresh summary
                await this.fetchSummary();
                this.showStaleDetails = false;
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal membersihkan akses stale.');
            } finally {
                this.purgingStale = false;
            }
        },
        async searchKaryawanQuick(query) {
            this.quickKaryawanLoading = true;
            try {
                const { data } = await axios.get('/api/v1/menu-approval-matrix/karyawan/search', {
                    params: { grup_id: this.grupId, purpose: 'target', search: query || undefined },
                });
                this.quickKaryawanOptions = data.items || [];
            } catch {
                this.quickKaryawanOptions = [];
            } finally {
                this.quickKaryawanLoading = false;
            }
        },
        handleQuickSearchChange(userIdWeb) {
            if (!userIdWeb) return;
            const picked = this.quickKaryawanOptions.find((k) => k.UserID_Web === userIdWeb);
            if (picked) {
                if (!this.karyawanOptions.some((k) => k.UserID_Web === userIdWeb)) {
                    this.karyawanOptions.push(picked);
                }
                this.selectedUserId = userIdWeb;
                this.selectedUser = picked;
                this.setActiveSubTab('single');
                this.fetchBinding();
                this.quickSearchUserId = null;
            }
        },
        async openHeaderDistributionModal(headerName) {
            this.selectedHeaderName = headerName;
            this.headerModalVisible = true;
            this.headerUsersLoading = true;
            try {
                const { data } = await axios.get(`/api/v1/menu-approval-matrix/menu-binding/${this.grupId}/header-users`, {
                    params: { header: headerName }
                });
                this.headerUsers = data || [];
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat detail sebaran karyawan.');
            } finally {
                this.headerUsersLoading = false;
            }
        },
        goToEmployeeAccess(user) {
            this.headerModalVisible = false;
            if (!this.karyawanOptions.some((k) => k.UserID_Web === user.UserID_Web)) {
                this.karyawanOptions.push({
                    UserID_Web: user.UserID_Web,
                    Nama: user.Nama,
                    Kode_Karyawan: user.Kode_Karyawan,
                    NIK: user.NIK,
                    Divisi: user.Divisi,
                    Level: user.Level
                });
            }
            this.selectedUserId = user.UserID_Web;
            this.selectedUser = user;
            this.setActiveSubTab('single');
            this.fetchBinding();
        },
        formatAuditDateTime(value) {
            if (!value) return '-';
            const date = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return value;

            return date.toLocaleString('id-ID', {
                day: '2-digit',
                month: 'short',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
        getBarHeight(count) {
            if (!this.summaryData?.weeklyTrend || !this.summaryData.weeklyTrend.length) return 2;
            const max = Math.max(...this.summaryData.weeklyTrend.map((d) => d.count), 0);
            if (max === 0) return 4;
            const percentage = count / max;
            return Math.max(percentage * 65, 4); // max height 65px, min height 4px for visible line
        },
        async searchKaryawan(query) {
            this.karyawanLoading = true;
            try {
                const { data } = await axios.get('/api/v1/menu-approval-matrix/karyawan/search', {
                    params: { grup_id: this.grupId, purpose: 'target', search: query || undefined },
                });
                this.karyawanOptions = data.items || [];
            } catch {
                this.karyawanOptions = [];
            } finally {
                this.karyawanLoading = false;
            }
        },
        async searchKaryawanSource(query) {
            this.sourceLoading = true;
            try {
                const { data } = await axios.get('/api/v1/menu-approval-matrix/karyawan/search', {
                    params: { grup_id: this.grupId, purpose: 'target', search: query || undefined },
                });
                this.sourceKaryawanOptions = data.items || [];
            } catch {
                this.sourceKaryawanOptions = [];
            } finally {
                this.sourceLoading = false;
            }
        },
        async handleUserChange(userIdWeb) {
            if (!userIdWeb) {
                this.selectedUser = null;
                this.allMenus = [];
                this.boundPages = [];
                this.originalBound = [];
                this.employeeDetails = null;
                return;
            }
            const picked = this.karyawanOptions.find((k) => k.UserID_Web === userIdWeb);
            if (picked) {
                this.selectedUser = picked;
                await this.fetchBinding();
            }
        },
        async fetchBinding() {
            if (!this.grupId || !this.selectedUser) return;
            this.loading = true;
            try {
                const { data } = await axios.get(`/api/v1/menu-approval-matrix/menu-binding/${this.grupId}`, {
                    params: {
                        user_id: this.selectedUser.UserID_Web,
                    },
                });
                this.allMenus = data.allMenus || [];
                this.boundPages = [...(data.boundPages || [])];
                this.originalBound = [...this.boundPages];
                this.employeeDetails = data.employeeDetails || null;
                this.collapsed = {};
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat menu binding.');
            } finally {
                this.loading = false;
            }
        },
        async saveBinding() {
            if (!this.selectedUser) return;
            this.saving = true;
            try {
                const { data } = await axios.post(`/api/v1/menu-approval-matrix/menu-binding/${this.grupId}`, {
                    user_id: this.selectedUser.UserID_Web,
                    pages: this.boundPages,
                });
                this.originalBound = [...this.boundPages];
                ElMessage.success(data.message || 'Akses menu karyawan berhasil disimpan.');
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menyimpan menu binding.');
            } finally {
                this.saving = false;
            }
        },
        resetChanges() {
            this.boundPages = [...this.originalBound];
            ElMessage.info('Draft perubahan dibatalkan.');
        },
        toggleCollapse(header) {
            this.collapsed[header] = !this.collapsed[header];
        },
        toggleHeaderAll(menus, val) {
            const pages = menus.filter((m) => m.can_delegate).map((m) => m.Jenis_page);
            if (val) {
                pages.forEach((p) => {
                    if (!this.boundPages.includes(p)) {
                        this.boundPages.push(p);
                    }
                });
            } else {
                this.boundPages = this.boundPages.filter((p) => !pages.includes(p));
            }
        },
        openCopyModal() {
            this.sourceUserId = null;
            this.sourceKaryawanOptions = [];
            this.copyModalVisible = true;
        },
        async executeCopy() {
            if (!sourceUserId) return;
            try {
                const { data } = await axios.get(`/api/v1/menu-approval-matrix/menu-binding/${this.grupId}`, {
                    params: {
                        user_id: this.sourceUserId,
                    },
                });
                this.boundPages = [...(data.boundPages || [])];
                this.copyModalVisible = false;
                ElMessage.success("Akses menu disalin ke draft! Klik 'Simpan Perubahan' untuk menyimpan ke database.");
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menyalin akses menu.');
            }
        },
        resetBulkState() {
            this.bulkStep = 1;
            this.bulkSelectedUserIds = [];
            this.bulkSelectedPages = [];
            this.bulkKaryawanOptions = [];
            this.bulkKaryawanLoading = false;
            this.bulkSearchMenu = '';
            this.bulkCollapsed = {};
            this.bulkSaving = false;
            this.bulkResult = null;
            this.bulkSelectMode = 'manual';
            this.bulkFilters = {
                cabang: '',
                divisi_id: '',
                sub_divisi_id: '',
                level_id: '',
                jabatan_id: '',
            };
            this.filterResults = [];
            // Paksa muat ulang opsi filter agar tidak stale saat pindah grup.
            this.filterOptionsLoaded = false;
            this.filterOptions = { cabang: [], divisi: [], subDivisi: [], level: [], jabatan: [] };
        },
        async loadGroupMenus() {
            if (!this.grupId) return;
            try {
                const { data } = await axios.get(`/api/v1/menu-approval-matrix/menu-binding/${this.grupId}`);
                this.allMenus = data.allMenus || [];
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat menu untuk grup ini.');
            }
        },
        async searchKaryawanBulk(query) {
            this.bulkKaryawanLoading = true;
            try {
                const { data } = await axios.get('/api/v1/menu-approval-matrix/karyawan/search', {
                    params: { grup_id: this.grupId, purpose: 'target', search: query || undefined },
                });
                const items = data.items || [];
                // Ganti opsi dengan hasil query saat ini, tapi pertahankan opsi utk
                // orang yang sudah terpilih agar label chip-nya tetap tampil.
                const selected = this.bulkKaryawanOptions.filter((o) =>
                    this.bulkSelectedUserIds.includes(o.UserID_Web),
                );
                const merged = [...selected];
                items.forEach((item) => {
                    if (!merged.some((x) => x.UserID_Web === item.UserID_Web)) {
                        merged.push(item);
                    }
                });
                this.bulkKaryawanOptions = merged;
            } catch {
                // Keep existing
            } finally {
                this.bulkKaryawanLoading = false;
            }
        },
        async fetchFilterOptions() {
            if (this.filterOptionsLoaded) return;
            try {
                const { data } = await axios.get('/api/v1/menu-approval-matrix/karyawan/filter-options', {
                    params: { grup_id: this.grupId },
                });
                this.filterOptions = {
                    cabang: data.cabang || [],
                    divisi: data.divisi || [],
                    subDivisi: data.subDivisi || [],
                    level: data.level || [],
                    jabatan: data.jabatan || [],
                };
                this.filterOptionsLoaded = true;
            } catch (e) {
                ElMessage.error('Gagal memuat opsi filter karyawan.');
            }
        },
        async applyKaryawanFilters() {
            this.filterLoading = true;
            try {
                const { data } = await axios.post('/api/v1/menu-approval-matrix/karyawan/filter-search', {
                    grup_id: this.grupId,
                    cabang: this.bulkFilters.cabang || null,
                    divisi_id: this.bulkFilters.divisi_id || null,
                    sub_divisi_id: this.bulkFilters.sub_divisi_id || null,
                    level_id: this.bulkFilters.level_id || null,
                    jabatan_id: this.bulkFilters.jabatan_id || null,
                });
                this.filterResults = data.items || [];
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memfilter karyawan.');
                this.filterResults = [];
            } finally {
                this.filterLoading = false;
            }
        },
        toggleFilteredSelection(employee) {
            const idx = this.bulkSelectedUserIds.indexOf(employee.UserID_Web);
            if (idx > -1) {
                this.bulkSelectedUserIds.splice(idx, 1);
            } else {
                this.bulkSelectedUserIds.push(employee.UserID_Web);
            }
        },
        selectAllFiltered(val) {
            if (val) {
                this.filterResults.forEach((emp) => {
                    if (!this.bulkSelectedUserIds.includes(emp.UserID_Web)) {
                        this.bulkSelectedUserIds.push(emp.UserID_Web);
                    }
                });
            } else {
                const resultIds = this.filterResults.map((emp) => emp.UserID_Web);
                this.bulkSelectedUserIds = this.bulkSelectedUserIds.filter((id) => !resultIds.includes(id));
            }
        },
        async proceedToStep2() {
            if (!this.allMenus.length) {
                await this.loadGroupMenus();
            }
            this.bulkStep = 2;
        },
        toggleBulkCollapse(header) {
            this.bulkCollapsed = {
                ...this.bulkCollapsed,
                [header]: !this.bulkCollapsed[header],
            };
        },
        toggleBulkHeaderAll(menus, val) {
            const pages = menus.filter((m) => m.can_delegate).map((m) => m.Jenis_page);
            if (val) {
                pages.forEach((p) => {
                    if (!this.bulkSelectedPages.includes(p)) {
                        this.bulkSelectedPages.push(p);
                    }
                });
            } else {
                this.bulkSelectedPages = this.bulkSelectedPages.filter((p) => !pages.includes(p));
            }
        },
        getBulkSelectedKaryawanDetails() {
            const merged = [];
            const idsSeen = new Set();
            const add = (k) => {
                if (k && k.UserID_Web && !idsSeen.has(k.UserID_Web)) {
                    idsSeen.add(k.UserID_Web);
                    merged.push(k);
                }
            };
            this.bulkKaryawanOptions.forEach(add);
            this.filterResults.forEach(add);
            return merged.filter((k) => this.bulkSelectedUserIds.includes(k.UserID_Web));
        },
        getMenuNameOnly(jenisPage) {
            const menu = this.allMenus.find((m) => m.Jenis_page === jenisPage);
            return menu ? menu.Nama_Menu : jenisPage;
        },
        async executeBulkAssign() {
            if (!this.bulkSelectedUserIds.length || !this.bulkSelectedPages.length) return;
            this.bulkSaving = true;
            try {
                const { data } = await axios.post(`/api/v1/menu-approval-matrix/menu-binding/${this.grupId}/bulk`, {
                    user_ids: this.bulkSelectedUserIds,
                    pages: this.bulkSelectedPages,
                });
                this.bulkResult = data.detail || null;
                this.bulkStep = 4;
                ElMessage.success(data.message || 'Bulk assignment menu berhasil diproses.');
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal melakukan bulk assign menu.');
            } finally {
                this.bulkSaving = false;
            }
        },
    },
};
</script>

<style scoped>
.stitch-card {
    margin: 0.5rem;
    background: #fff;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    box-shadow:
        0 4px 6px -1px rgba(0, 0, 0, 0.05),
        0 2px 4px -1px rgba(0, 0, 0, 0.025);
}
.user-profile-card {
    background: #fdfdfd;
    border-color: var(--hcis-slate-200) !important;
}
.user-avatar-initials {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--hcis-indigo-500) 0%, var(--hcis-purple-500) 100%);
    color: #fff;
    font-weight: 700;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
}
.badge-org {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-100);
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
}
.bg-indigo-soft {
    background: var(--hcis-indigo-50) !important;
}
.text-indigo {
    color: var(--hcis-indigo-500) !important;
}
.bg-indigo {
    background: var(--hcis-indigo-500) !important;
}
.bg-success-soft {
    background-color: var(--hcis-emerald-100) !important;
    color: var(--hcis-emerald-500) !important;
    border: 1px solid rgba(16, 185, 129, 0.1) !important;
}
.bg-warning-soft {
    background-color: var(--hcis-amber-100) !important;
    color: var(--hcis-amber-600) !important;
    border: 1px solid rgba(217, 119, 6, 0.1) !important;
}
.bg-info-soft {
    background-color: var(--hcis-sky-100) !important;
    color: var(--hcis-sky-600) !important;
    border: 1px solid rgba(2, 132, 199, 0.1) !important;
}
.hover-bg-indigo:hover {
    background-color: var(--hcis-slate-50) !important;
    border-color: var(--hcis-indigo-500) !important;
}
.hover-bg-indigo:hover i {
    color: var(--hcis-indigo-600) !important;
}
.shadow-xs {
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}
.btn-copy-access {
    background: #fff;
    border: 1px dashed var(--hcis-indigo-500);
    color: var(--hcis-indigo-500);
    font-weight: 600;
    font-size: 0.75rem;
    border-radius: 8px;
    padding: 0.35rem 0.75rem;
    transition: all 0.2s;
}
.btn-copy-access:hover {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
}
.badge-view-only {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.15rem 0.5rem;
    border-radius: 20px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    text-transform: uppercase;
}
.toolbar-box {
    background: var(--hcis-slate-50);
    padding: 0.5rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
}
.search-bar {
    position: relative;
}
.search-bar i {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--hcis-slate-400);
    font-size: 0.85rem;
}
.search-bar input {
    padding-left: 2rem;
    border-radius: 8px;
    border: 1px solid var(--hcis-slate-200);
}
.binding-stats {
    display: flex;
    gap: 0.5rem;
}
.stat-badge {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
.stat-bound {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-600);
}
.stat-available {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
}
.header-group {
    background: #fff;
    border-color: var(--hcis-slate-200) !important;
}
.header-group-title {
    background: #fff;
    user-select: none;
    transition: background-color 0.15s;
}
.header-group-title:hover {
    background-color: var(--hcis-slate-50);
}
.header-count {
    font-size: 0.7rem;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-100);
    padding: 0.15rem 0.5rem;
    border-radius: 20px;
    font-weight: 600;
}
.menu-check-list {
    border-color: var(--hcis-slate-200) !important;
}
.menu-check-item {
    background: #fff;
    border: 1px solid var(--hcis-slate-200);
    cursor: pointer;
    transition: all 0.2s;
    user-select: none;
}
.menu-check-item:hover {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-300);
}
.menu-bound {
    background: var(--hcis-indigo-50);
    border-color: var(--hcis-indigo-400) !important;
    box-shadow: 0 2px 4px rgba(99, 102, 241, 0.05);
}
.menu-readonly {
    cursor: default;
    opacity: 0.76;
}
.menu-check-info {
    flex: 1;
    min-width: 0;
}
.menu-check-name {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--hcis-slate-800);
}
.menu-check-page {
    font-size: 0.68rem;
    color: var(--hcis-slate-400);
    font-family: monospace;
}
.menu-lock-reason {
    margin-top: 0.3rem;
    color: var(--hcis-amber-700);
    font-size: 0.66rem;
    line-height: 1.35;
    white-space: normal;
}
.changes-diff-preview {
    background: var(--hcis-slate-50) !important;
    border-color: var(--hcis-slate-200) !important;
}
.bg-success-soft {
    background-color: var(--hcis-emerald-100) !important;
}
.bg-danger-soft {
    background-color: var(--hcis-red-100) !important;
}
.text-monospace {
    font-family: monospace;
    font-size: 0.72rem;
    padding: 0.2rem 0.4rem;
}
.save-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    background: var(--hcis-amber-50);
    border: 1px solid var(--hcis-amber-200);
    border-radius: 10px;
}
.change-note {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-amber-600);
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.btn-save {
    display: inline-flex;
    align-items: center;
    padding: 0.45rem 1.25rem;
    border: none;
    border-radius: 8px;
    background: var(--hcis-indigo-500);
    color: #fff;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.2);
}
.btn-save:hover {
    background: var(--hcis-indigo-600);
}
.btn-save:disabled,
.btn-cancel:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.btn-cancel {
    display: inline-flex;
    align-items: center;
    padding: 0.45rem 1rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    background: #fff;
    color: var(--hcis-slate-500);
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: background-color 0.2s;
}
.btn-cancel:hover {
    background: var(--hcis-slate-50);
}
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
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: var(--hcis-slate-400);
}
.empty-state i {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.75rem;
    color: var(--hcis-slate-400);
}
.form-check-input {
    width: 16px;
    height: 16px;
    cursor: pointer;
    accent-color: var(--hcis-indigo-500);
}
.form-label-sm {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-slate-700);
    margin-bottom: 0.25rem;
    display: block;
}

.text-xs {
    font-size: 0.68rem;
}
.tracking-wider {
    letter-spacing: 0.05em;
}
.uppercase {
    text-transform: uppercase;
}

/* Stepper Styles */
.stepper-progress {
    position: relative;
}
.step-progress-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 2;
    flex: 1;
}
.step-number {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    font-weight: 700;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--hcis-slate-200);
    transition: all 0.3s ease;
}
.step-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
    margin-top: 0.35rem;
    text-align: center;
    transition: all 0.3s ease;
}
.step-line {
    height: 2px;
    background: var(--hcis-slate-200);
    flex: 1;
    margin-top: -24px;
    z-index: 1;
    transition: all 0.3s ease;
}
.step-active .step-number {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-500);
    border-color: var(--hcis-indigo-500);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
.step-active .step-label {
    color: var(--hcis-indigo-500);
    font-weight: 700;
}
.step-done .step-number {
    background: var(--hcis-indigo-500);
    color: #fff;
    border-color: var(--hcis-indigo-500);
}
.step-done .step-label {
    color: var(--hcis-slate-800);
}
.line-done {
    background: var(--hcis-indigo-500);
}

/* Result Screen & History Styles */
.result-success-icon {
    animation: scaleIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
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
.result-card {
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.02);
}
.result-scroll {
    scrollbar-width: thin;
}
.review-item {
    font-size: 0.78rem;
}
.last-border-none:last-child {
    border-bottom: none !important;
}
.italic {
    font-style: italic;
}

/* soft badges for history */
.bg-primary-soft {
    background-color: #eff6ff !important;
    color: var(--hcis-blue-600) !important;
    border: 1px solid rgba(37, 99, 235, 0.1) !important;
}
.bg-indigo-soft {
    background-color: var(--hcis-indigo-50) !important;
    color: var(--hcis-indigo-600) !important;
    border: 1px solid rgba(79, 70, 229, 0.1) !important;
}

/* Trend Chart Custom Styles */
.trend-bar-container {
    position: relative;
    width: 100%;
}
.bar-wrapper {
    position: relative;
    cursor: pointer;
}
.bar-wrapper:hover .trend-bar {
    filter: brightness(1.1);
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
    transform: translateY(-2px);
}
.trend-bar {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    min-width: 16px;
    max-width: 32px;
}
.text-xxs {
    font-size: 0.65rem !important;
}

/* Dashboard Premium Styles matching Approval Tab Dashboard */
.approval-dashboard {
    --ink: #172033;
    --muted: #68758c;
    --line: #e6eaf1;
    display: grid;
    gap: 1rem;
    padding: 0.5rem;
    color: var(--ink);
}
.dashboard-hero {
    position: relative;
    overflow: hidden;
    display: flex;
    justify-content: space-between;
    gap: 1.25rem;
    padding: 1.35rem;
    border: 1px solid #dfe4ff;
    border-radius: 18px;
    background: linear-gradient(130deg, #f8f9ff 0%, var(--hcis-indigo-50) 58%, #f0fdfa 100%);
}
.dashboard-hero::after {
    position: absolute;
    right: -60px;
    bottom: -85px;
    width: 220px;
    height: 220px;
    border: 35px solid rgba(79, 70, 229, 0.07);
    border-radius: 50%;
    content: '';
}
.hero-copy,
.hero-actions {
    position: relative;
    z-index: 1;
}
.eyebrow,
.panel-kicker {
    color: var(--hcis-indigo-600);
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.09em;
    text-transform: uppercase;
}
.eyebrow {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 0.45rem;
}
.hero-copy h4 {
    margin: 0;
    font-size: clamp(1.1rem, 2vw, 1.45rem);
    font-weight: 800;
}
.hero-copy p {
    max-width: 700px;
    margin: 0.3rem 0 0.8rem;
    color: var(--muted);
    font-size: 0.82rem;
}
.hero-badges,
.hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}
.hero-actions {
    justify-content: flex-end;
    align-self: flex-start;
}
.persona-badge,
.health-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.36rem 0.62rem;
    border: 1px solid #dfe4ee;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.78);
    font-size: 0.7rem;
    font-weight: 700;
}
.health-badge.has-issues {
    border-color: #fed7aa;
    color: var(--hcis-amber-700);
    background: #fff7ed;
}
.health-badge.is-healthy {
    border-color: var(--hcis-green-200);
    color: var(--hcis-green-700);
    background: var(--hcis-green-50);
}
.dashboard-btn {
    min-height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    padding: 0.55rem 0.8rem;
    border: 1px solid transparent;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 750;
    transition: 0.18s ease;
}
.dashboard-btn.primary {
    background: var(--hcis-indigo-600);
    color: #fff;
    box-shadow: 0 7px 18px rgba(79, 70, 229, 0.2);
}
.dashboard-btn.secondary {
    border-color: #d9deea;
    background: #fff;
    color: #344054;
}
.dashboard-btn:hover:not(:disabled) {
    transform: translateY(-1px);
}

.metric-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.75rem;
}
.metric-card {
    min-width: 0;
    display: flex;
    gap: 0.72rem;
    padding: 0.9rem;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.035);
}
.metric-icon {
    flex: 0 0 38px;
    height: 38px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    font-size: 1rem;
}
.metric-copy {
    min-width: 0;
    display: grid;
}
.metric-copy span {
    color: var(--muted);
    font-size: 0.63rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.metric-copy strong {
    font-size: 1.28rem;
    line-height: 1.2;
}
.metric-copy small {
    overflow-wrap: anywhere;
    color: #8993a5;
    font-size: 0.64rem;
}
.metric-card.indigo .metric-icon {
    color: var(--hcis-indigo-600);
    background: var(--hcis-indigo-50);
}
.metric-card.blue .metric-icon {
    color: var(--hcis-blue-600);
    background: #eff6ff;
}
.metric-card.teal .metric-icon {
    color: #0f766e;
    background: #f0fdfa;
}
.metric-card.green .metric-icon {
    color: var(--hcis-green-700);
    background: var(--hcis-green-50);
}
.metric-card.amber .metric-icon {
    color: var(--hcis-amber-700);
    background: #fff7ed;
}

.dashboard-main-grid {
    display: grid;
    grid-template-columns: minmax(0, 5fr) minmax(0, 7fr);
    gap: 1rem;
}
.dashboard-secondary-grid {
    display: grid;
    grid-template-columns: minmax(0, 4fr) minmax(0, 8fr);
    gap: 1rem;
}
.dashboard-panel {
    min-width: 0;
    padding: 1rem;
    height: 100%;
    border: 1px solid var(--line);
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 5px 18px rgba(15, 23, 42, 0.035);
}
.panel-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.8rem;
}
.panel-heading.compact {
    margin-bottom: 0.7rem;
}
.panel-heading h5 {
    margin: 0.1rem 0 0;
    font-size: 0.92rem;
    font-weight: 800;
}
.panel-count {
    min-width: 28px;
    height: 28px;
    display: grid;
    place-items: center;
    border-radius: 9px;
    background: #fff7ed;
    color: var(--hcis-amber-700);
    font-size: 0.72rem;
    font-weight: 800;
}
.panel-count.neutral {
    color: var(--hcis-indigo-600);
    background: var(--hcis-indigo-50);
}

.spinning {
    animation: spin 0.8s linear infinite;
}

/* Quick Action Buttons matching Dashboard Cards */
.quick-action-btn {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: #fff;
    color: inherit;
    text-align: left;
    transition: all 0.2s ease;
}
.quick-action-btn:hover {
    border-color: var(--hcis-indigo-200);
    background: #fafaff;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.04);
}
.quick-action-icon {
    flex: 0 0 32px;
    height: 32px;
    display: grid;
    place-items: center;
    border-radius: 9px;
    font-size: 1.1rem;
}
.quick-action-icon.indigo {
    color: var(--hcis-indigo-600);
    background: var(--hcis-indigo-50);
}
.quick-action-icon.success {
    color: var(--hcis-green-700);
    background: var(--hcis-green-50);
}
.quick-action-copy {
    min-width: 0;
    flex: 1;
    display: grid;
    gap: 0.1rem;
}
.quick-action-copy strong {
    font-size: 0.75rem;
    color: var(--ink);
    font-weight: 700;
}
.quick-action-copy small {
    color: var(--muted);
    font-size: 0.65rem;
}
.quick-action-arrow {
    flex: 0 0 auto;
    color: #98a2b3;
    font-size: 0.75rem;
}

/* Popularity Progress Bar matching Approval Progress */
.popularity-track {
    height: 6px;
    overflow: hidden;
    border-radius: 99px;
    background: #eef1f5;
    margin-top: 0.2rem;
}
.popularity-track span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, var(--hcis-indigo-500), var(--hcis-indigo-400));
}

/* Timeline Activity Row matching Approval Activity Row */
.activity-list {
    display: grid;
    gap: 0.55rem;
}
.activity-row {
    position: relative;
    display: flex;
    gap: 0.65rem;
    padding: 0.35rem 0.15rem;
}
.activity-dot {
    flex: 0 0 9px;
    height: 9px;
    margin-top: 0.25rem;
    border: 2px solid var(--hcis-indigo-200);
    border-radius: 50%;
    background: var(--hcis-indigo-600);
}
.activity-row strong {
    display: block;
    font-size: 0.7rem;
    color: var(--ink);
}
.activity-row p {
    margin: 0.08rem 0;
    color: var(--muted);
    font-size: 0.65rem;
}
.activity-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem 0.75rem;
    margin: 0.35rem 0 0.2rem;
    color: var(--hcis-slate-500);
    font-size: 0.62rem;
}
.activity-meta span {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
.activity-meta i {
    color: var(--hcis-indigo-500);
}
.activity-row small {
    color: #98a2b3;
    font-size: 0.6rem;
}

/* Dashboard Skeleton matching Approval Dashboard */
.dashboard-skeleton {
    animation: shimmer 1.5s infinite linear;
}
.skeleton-metrics {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 0.75rem;
}
.skeleton-panels {
    display: grid;
    grid-template-columns: 5fr 7fr;
    gap: 1rem;
    margin-top: 1rem;
}
.skeleton-block {
    height: 82px;
    border-radius: 14px;
    background: linear-gradient(90deg, #f1f3f7 25%, #fafbfc 50%, #f1f3f7 75%);
    background-size: 200% 100%;
    animation: shimmer 1.4s infinite;
}
.skeleton-block.tall {
    height: 310px;
}

@keyframes shimmer {
    to {
        background-position: -200% 0;
    }
}

@media (max-width: 1199px) {
    .metric-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .dashboard-main-grid,
    .dashboard-secondary-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 767px) {
    .dashboard-hero {
        flex-direction: column;
        padding: 1rem;
        border-radius: 14px;
    }
    .hero-actions {
        width: 100%;
        justify-content: stretch;
    }
    .hero-actions .dashboard-btn {
        flex: 1;
    }
    .metric-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .skeleton-panels {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 430px) {
    .metric-grid {
        grid-template-columns: 1fr;
    }
    .hero-actions {
        display: grid;
        grid-template-columns: 1fr;
    }
}
.hover-lift {
    transition:
        transform 0.22s cubic-bezier(0.4, 0, 0.2, 1),
        box-shadow 0.22s cubic-bezier(0.4, 0, 0.2, 1);
}
.hover-lift:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06) !important;
}
</style>
