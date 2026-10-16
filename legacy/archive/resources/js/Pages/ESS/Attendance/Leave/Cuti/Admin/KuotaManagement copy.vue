<template>
    <div class="container-fluid px-0 kuota-management-page">
        <!-- Header -->
        <HeaderKpi
            title="Manajemen Kuota Cuti"
            subtitle="Kelola kuota cuti karyawan dan cuti bersama"
            :dayNight="dayNight"
            :formattedDate="formatDateString(currentDate)"
        />

        <div class="kuota-shell">
            <!-- Quick Stats -->
            <!-- <quick-stat class="" :stats="quickStatField" /> -->

            <!-- Main Content -->
            <div class="row justify-content-center kuota-main-grid">
                <div class="col-lg-12">
                    <ApprovalTabs :tabs="adminTabs" :activeTab="activeTab" @tab-change="handleTabChange">
                        <!-- Tab Kuota Karyawan -->
                        <template #kuota>
                            <div class="tab-content-improved">
                                <!-- Filter & Search Section using ModernFilter -->
                                <ModernFilter
                                    :config="filterConfig"
                                    :filters="filtersKuota"
                                    :resultsCount="paginationKaryawan.total"
                                    @filters-update="handleFilterUpdate"
                                >
                                    <template #actions>
                                        <el-button
                                            type="success"
                                            @click="exportKuota"
                                            :disabled="selectedForExport.length === 0 || loading.export"
                                            :icon="Download"
                                        >
                                            Export
                                        </el-button>
                                    </template>
                                </ModernFilter>

                                <!-- Loading State -->
                                <div v-if="loading.karyawan" class="loading-state">
                                    <div class="loading-spinner">
                                        <i
                                            class="bi bi-arrow-repeat d-flex justify-content-center align-items-center"
                                        ></i>
                                    </div>
                                    <h4 class="loading-title">Memuat Data Kuota...</h4>
                                    <p class="loading-description">Harap tunggu sebentar</p>
                                </div>

                                <div v-else>
                                    <!-- "Select All" Banner Info -->
                                    <div
                                        v-if="selectedForExport.length > 0"
                                        class="alert alert-info py-2 px-3 mb-3 d-flex align-items-center justify-content-between"
                                    >
                                        <div>
                                            <i class="bi bi-check-circle-fill me-2"></i>
                                            <span
                                                >Seluruh
                                                <strong>{{
                                                    selectAllAllPages
                                                        ? paginationKaryawan.total
                                                        : selectedForExport.length
                                                }}</strong>
                                                data karyawan terpilih untuk export.</span
                                            >
                                        </div>
                                        <button
                                            class="btn btn-sm btn-outline-primary bg-white"
                                            @click="toggleSelectAll"
                                        >
                                            Batalkan
                                        </button>
                                    </div>

                                    <!-- Empty State -->
                                    <div v-if="karyawanList.length === 0" class="empty-state">
                                        <div class="empty-icon">
                                            <i class="bi bi-people"></i>
                                        </div>
                                        <h4 class="empty-title">Tidak Ada Data</h4>
                                        <p class="empty-description">Belum ada data kuota karyawan yang ditemukan</p>
                                    </div>

                                    <!-- Data Table -->
                                    <div v-else>
                                        <div class="table-responsive">
                                            <table class="table table-modern">
                                                <thead class="">
                                                    <tr class="">
                                                        <th class="py-3 text-center" style="width: 40px">
                                                            <input
                                                                type="checkbox"
                                                                :checked="isAllSelected"
                                                                @change="toggleSelectAll"
                                                                class="form-check-input"
                                                            />
                                                        </th>
                                                        <th class="py-3"><i class="bi bi-person me-1"></i>Nama</th>
                                                        <th class="py-3"><i class="bi bi-building me-1"></i>Divisi</th>
                                                        <th class="text-center py-3">
                                                            <i class="bi bi-calendar-check me-1"></i>Sisa
                                                        </th>
                                                        <th class="text-center py-3">
                                                            <i class="bi bi-hourglass me-1"></i>Pending
                                                        </th>
                                                        <th class="text-center py-3">
                                                            <i class="bi bi-exclamation-triangle me-1"></i>Hutang
                                                        </th>
                                                        <th class="text-center py-3">
                                                            <i class="bi bi-gear me-1"></i>Aksi
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                        v-for="item in karyawanList"
                                                        :key="item.Kode_Karyawan_Encrypted"
                                                    >
                                                        <td class="text-center">
                                                            <input
                                                                type="checkbox"
                                                                :checked="selectedForMark.includes(item.UserId_Absen)"
                                                                @change="
                                                                    toggleSelectEmployee(
                                                                        item.Kode_Karyawan_Encrypted,
                                                                        item.UserId_Absen,
                                                                    )
                                                                "
                                                                class="form-check-input"
                                                            />
                                                        </td>
                                                        <td>
                                                            <div class="employee-info-cell">
                                                                <div class="avatar-sm">
                                                                    {{ getInitials(item.Nama_Karyawan) }}
                                                                </div>
                                                                <div class="employee-details">
                                                                    <span class="employee-name">{{
                                                                        item.Nama_Karyawan
                                                                    }}</span>
                                                                    <small class="employee-meta d-block">{{
                                                                        item.nama_jabatan
                                                                    }}</small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            {{ item.nama_divisi }}
                                                        </td>
                                                        <td class="text-center">
                                                            <span
                                                                :class="[
                                                                    'badge',
                                                                    parseFloat(item.Sisa_Cuti_Tampil) > 0
                                                                        ? 'bg-success'
                                                                        : 'bg-secondary',
                                                                ]"
                                                            >
                                                                {{ formatNumber(item.Sisa_Cuti_Tampil) }}
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <span
                                                                :class="[
                                                                    'badge',
                                                                    parseInt(item.Sedang_Diajukan) > 0
                                                                        ? 'bg-warning'
                                                                        : 'bg-secondary',
                                                                ]"
                                                            >
                                                                {{ parseInt(item.Sedang_Diajukan) || 0 }}
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <span
                                                                :class="[
                                                                    'badge',
                                                                    parseFloat(item.Hutang_Cuti_Tampil) > 0
                                                                        ? 'bg-danger'
                                                                        : 'bg-secondary',
                                                                ]"
                                                            >
                                                                {{ formatNumber(item.Hutang_Cuti_Tampil) }}
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <button
                                                                class="btn-action btn-view"
                                                                @click="openDetailModal(item)"
                                                            >
                                                                <i class="bi bi-eye me-1 tengahkan"></i>
                                                                Detail
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Pagination -->
                                        <div
                                            class="pagination-container"
                                            v-if="paginationKaryawan.total > paginationKaryawan.per_page"
                                        >
                                            <button
                                                class="page-btn"
                                                :disabled="paginationKaryawan.current_page === 1"
                                                @click="handlePageChange(paginationKaryawan.current_page - 1)"
                                            >
                                                <i class="bi bi-chevron-left tengahkan"></i>
                                            </button>
                                            <div class="page-numbers">
                                                <button
                                                    v-for="page in visiblePages"
                                                    :key="page"
                                                    :class="[
                                                        'page-number',
                                                        {
                                                            active: page === paginationKaryawan.current_page,
                                                        },
                                                    ]"
                                                    @click="handlePageChange(page)"
                                                >
                                                    {{ page }}
                                                </button>
                                            </div>
                                            <button
                                                class="page-btn"
                                                :disabled="paginationKaryawan.current_page === totalPages"
                                                @click="handlePageChange(paginationKaryawan.current_page + 1)"
                                            >
                                                <i class="bi bi-chevron-right tengahkan"></i>
                                            </button>
                                            <span class="page-info">
                                                Halaman
                                                {{ paginationKaryawan.current_page }}
                                                dari {{ totalPages }} ({{ paginationKaryawan.total }}
                                                data)
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template #dashboard>
                            <div class="tab-content-improved">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="section-title mb-0">
                                        <i class="bi bi-speedometer2 me-2 tengahkan"></i>Dashboard Proses Cuti
                                    </h5>
                                    <button class="btn-modern btn-secondary" @click="refreshDashboard">
                                        <i class="bi bi-arrow-clockwise me-2 tengahkan"></i>Refresh
                                    </button>
                                </div>

                                <div class="cuti-widget-grid mb-4">
                                    <section class="cuti-widget-card cuti-widget-card-wide">
                                        <div class="cuti-widget-header">
                                            <div>
                                                <h6>Heatmap Akrual</h6>
                                                <p>
                                                    {{ dashboardWidgets.heatmap.meta?.start || '-' }} -
                                                    {{ dashboardWidgets.heatmap.meta?.end || '-' }}
                                                </p>
                                            </div>
                                            <div class="cuti-segmented">
                                                <button
                                                    v-for="option in heatmapSourceOptions"
                                                    :key="option.value"
                                                    type="button"
                                                    :class="{ active: heatmapSource === option.value }"
                                                    @click="setHeatmapSource(option.value)"
                                                >
                                                    {{ option.label }}
                                                </button>
                                            </div>
                                        </div>
                                        <div
                                            v-if="dashboardWidgets.heatmap.error"
                                            class="alert alert-warning py-2 mb-3"
                                        >
                                            {{ dashboardWidgets.heatmap.error.message }}
                                        </div>
                                        <div class="heatmap-grid" :class="{ loading: loading.dashboardWidgets }">
                                            <button
                                                v-for="day in dashboardWidgets.heatmap.items"
                                                :key="day.date"
                                                type="button"
                                                :class="[
                                                    'heatmap-day',
                                                    day.status,
                                                    { 'scheduler-missing': day.scheduler_missing },
                                                    { 'is-today': day.is_today },
                                                ]"
                                                :title="heatmapDayTooltip(day)"
                                                @click="openHeatmapDetail(day)"
                                            >
                                                <span class="heatmap-day__num">{{ formatDayNumber(day.date) }}</span>
                                                <small class="heatmap-day__badge">{{ heatmapDayBadge(day) }}</small>
                                                <em
                                                    v-if="day.scheduler_missing"
                                                    class="heatmap-day__flag"
                                                    title="Scheduler tidak jalan"
                                                    >!</em
                                                >
                                            </button>
                                            <div
                                                v-if="dashboardWidgets.heatmap.items.length === 0"
                                                class="empty-inline"
                                            >
                                                Belum ada data heatmap.
                                            </div>
                                        </div>
                                        <div class="heatmap-legend mt-3">
                                            <span class="legend-item legend-ok">
                                                <i class="legend-dot"></i>
                                                Semua dapat
                                                <strong>{{ dashboardWidgets.heatmap.meta?.stats?.ok || 0 }}</strong>
                                            </span>
                                            <span class="legend-item legend-warn">
                                                <i class="legend-dot"></i>
                                                Sebagian
                                                <strong>{{ dashboardWidgets.heatmap.meta?.stats?.warn || 0 }}</strong>
                                            </span>
                                            <span class="legend-item legend-fail">
                                                <i class="legend-dot"></i>
                                                Belum dapat
                                                <strong>{{ dashboardWidgets.heatmap.meta?.stats?.fail || 0 }}</strong>
                                            </span>
                                            <span class="legend-item legend-idle">
                                                <i class="legend-dot"></i>
                                                Tidak ada due
                                                <strong>{{ dashboardWidgets.heatmap.meta?.stats?.idle || 0 }}</strong>
                                            </span>
                                            <span class="legend-item legend-missing">
                                                <i class="legend-dot"></i>
                                                Scheduler tidak jalan
                                                <strong>{{
                                                    dashboardWidgets.heatmap.meta?.stats?.scheduler_missing || 0
                                                }}</strong>
                                            </span>
                                        </div>
                                    </section>

                                    <section class="cuti-widget-card">
                                        <div class="cuti-widget-header">
                                            <div>
                                                <div class="widget-title-row">
                                                    <h6>Trend Akrual Bulanan</h6>
                                                    <span class="widget-purpose-badge widget-purpose-badge-monthly">
                                                        <i class="bi bi-calendar-plus"></i>
                                                        Cuti Tambahan Monthly
                                                    </span>
                                                </div>
                                                <p class="widget-context-note">
                                                    Monitoring cuti tambahan bulanan mulai cutoff:
                                                    {{ formatDate(dashboardWidgets.monthlyTrend.meta?.cutoff) }}.
                                                </p>
                                                <p class="widget-summary-row">
                                                    Coverage
                                                    <strong
                                                        :class="
                                                            coverageClass(
                                                                dashboardWidgets.monthlyTrend.meta?.coverage_overall,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            dashboardWidgets.monthlyTrend.meta?.coverage_overall ?? '-'
                                                        }}%
                                                    </strong>
                                                    ·
                                                    {{ dashboardWidgets.monthlyTrend.meta?.total_realized || 0 }}/{{
                                                        dashboardWidgets.monthlyTrend.meta?.total_expected || 0
                                                    }}
                                                    <small
                                                        v-if="dashboardWidgets.monthlyTrend.meta?.total_blocked"
                                                        class="text-muted"
                                                    >
                                                        · {{ dashboardWidgets.monthlyTrend.meta.total_blocked }} blocked
                                                    </small>
                                                </p>
                                            </div>
                                        </div>
                                        <div
                                            v-if="dashboardWidgets.monthlyTrend.error"
                                            class="alert alert-warning py-2 mb-3"
                                        >
                                            {{ dashboardWidgets.monthlyTrend.error.message }}
                                        </div>
                                        <div class="trend-bars">
                                            <button
                                                v-for="bar in dashboardWidgets.monthlyTrend.items"
                                                :key="bar.period"
                                                type="button"
                                                class="trend-bar-item"
                                                :title="trendBarTooltip(bar)"
                                                @click="openTrendDetail(bar, 'MONTHLY_ACCRUAL')"
                                            >
                                                <span class="trend-value">
                                                    {{ bar.realized || 0
                                                    }}<small class="trend-expected">/{{ bar.expected || 0 }}</small>
                                                </span>
                                                <span class="trend-bar-track">
                                                    <span
                                                        v-if="(bar.expected || 0) > 0 || (bar.blocked || 0) > 0"
                                                        class="trend-bar-stack"
                                                        :style="{
                                                            height: getStackHeight(
                                                                bar,
                                                                dashboardWidgets.monthlyTrend.items,
                                                            ),
                                                        }"
                                                    >
                                                        <span
                                                            v-if="bar.blocked"
                                                            class="trend-bar-fill blocked"
                                                            :style="{ flex: bar.blocked || 0 }"
                                                        ></span>
                                                        <span
                                                            class="trend-bar-fill gap"
                                                            :style="{ flex: bar.gap || 0 }"
                                                        ></span>
                                                        <span
                                                            class="trend-bar-fill realized"
                                                            :style="{ flex: bar.realized || 0 }"
                                                        ></span>
                                                    </span>
                                                </span>
                                                <span class="trend-label">{{ formatPeriodKey(bar.label) }}</span>
                                                <em
                                                    v-if="bar.coverage_percent !== null"
                                                    :class="['trend-coverage', coverageClass(bar.coverage_percent)]"
                                                >
                                                    {{ bar.coverage_percent }}%
                                                </em>
                                            </button>
                                            <div
                                                v-if="dashboardWidgets.monthlyTrend.items.length === 0"
                                                class="empty-inline"
                                            >
                                                Belum ada data trend.
                                            </div>
                                        </div>
                                        <div class="trend-legend mt-2">
                                            <span><i class="dot bg-success"></i>Sudah dapat</span>
                                            <span><i class="dot bg-danger"></i>Belum (gap)</span>
                                            <span><i class="dot bg-dark"></i>tidak lengkap</span>
                                            <span class="text-muted">Klik bar untuk drilldown</span>
                                        </div>
                                    </section>

                                    <section class="cuti-widget-card">
                                        <div class="cuti-widget-header">
                                            <div>
                                                <h6>Trend Akrual Loyalty</h6>
                                                <p>
                                                    Coverage
                                                    <strong
                                                        :class="
                                                            coverageClass(
                                                                dashboardWidgets.loyaltyTrend.meta?.coverage_overall,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            dashboardWidgets.loyaltyTrend.meta?.coverage_overall ?? '-'
                                                        }}%
                                                    </strong>
                                                    ·
                                                    {{ dashboardWidgets.loyaltyTrend.meta?.total_realized || 0 }}/{{
                                                        dashboardWidgets.loyaltyTrend.meta?.total_expected || 0
                                                    }}
                                                    <small
                                                        v-if="dashboardWidgets.loyaltyTrend.meta?.total_blocked"
                                                        class="text-muted"
                                                    >
                                                        · {{ dashboardWidgets.loyaltyTrend.meta.total_blocked }} blocked
                                                    </small>
                                                </p>
                                            </div>
                                        </div>
                                        <div
                                            v-if="dashboardWidgets.loyaltyTrend.error"
                                            class="alert alert-warning py-2 mb-3"
                                        >
                                            {{ dashboardWidgets.loyaltyTrend.error.message }}
                                        </div>
                                        <div class="trend-bars">
                                            <button
                                                v-for="bar in dashboardWidgets.loyaltyTrend.items"
                                                :key="bar.period"
                                                type="button"
                                                class="trend-bar-item"
                                                :title="trendBarTooltip(bar)"
                                                @click="openTrendDetail(bar, 'LOYALTY_ACCRUAL')"
                                            >
                                                <span class="trend-value">
                                                    {{ bar.realized || 0
                                                    }}<small class="trend-expected">/{{ bar.expected || 0 }}</small>
                                                </span>
                                                <span class="trend-bar-track">
                                                    <span
                                                        v-if="(bar.expected || 0) > 0 || (bar.blocked || 0) > 0"
                                                        class="trend-bar-stack"
                                                        :style="{
                                                            height: getStackHeight(
                                                                bar,
                                                                dashboardWidgets.loyaltyTrend.items,
                                                            ),
                                                        }"
                                                    >
                                                        <span
                                                            v-if="bar.blocked"
                                                            class="trend-bar-fill blocked"
                                                            :style="{ flex: bar.blocked || 0 }"
                                                        ></span>
                                                        <span
                                                            class="trend-bar-fill gap"
                                                            :style="{ flex: bar.gap || 0 }"
                                                        ></span>
                                                        <span
                                                            class="trend-bar-fill realized loyalty"
                                                            :style="{ flex: bar.realized || 0 }"
                                                        ></span>
                                                    </span>
                                                </span>
                                                <span class="trend-label">{{ formatPeriodKey(bar.label) }}</span>
                                                <em
                                                    v-if="bar.coverage_percent !== null"
                                                    :class="['trend-coverage', coverageClass(bar.coverage_percent)]"
                                                >
                                                    {{ bar.coverage_percent }}%
                                                </em>
                                            </button>
                                            <div
                                                v-if="dashboardWidgets.loyaltyTrend.items.length === 0"
                                                class="empty-inline"
                                            >
                                                Belum ada data trend.
                                            </div>
                                        </div>
                                        <div class="trend-legend mt-2">
                                            <span><i class="dot bg-success"></i>Sudah dapat</span>
                                            <span><i class="dot bg-danger"></i>Belum (gap)</span>
                                            <span><i class="dot bg-dark"></i>Data tidak lengkap</span>
                                            <span class="text-muted">Klik bar untuk drilldown</span>
                                        </div>
                                    </section>

                                    <section class="cuti-widget-card monthly-anniversary-card">
                                        <div class="cuti-widget-header">
                                            <div>
                                                <div class="widget-title-row">
                                                    <h6>Anniversary Monthly Cuti</h6>
                                                    <span class="widget-purpose-badge widget-purpose-badge-monthly">
                                                        <i class="bi bi-calendar-check"></i>
                                                        Cuti Tahunan / Monthly
                                                    </span>
                                                </div>
                                                <p class="widget-context-note">
                                                    Monitoring anniversary kerja untuk validasi jatah cuti tahunan
                                                    bulanan.
                                                </p>
                                                <p class="widget-summary-row">
                                                    Periode
                                                    {{
                                                        formatPeriodKey(
                                                            dashboardWidgets.monthlyAnniversary.meta?.period_key,
                                                        )
                                                    }}
                                                    Â· mulai cutoff
                                                    {{
                                                        formatHumanDate(
                                                            dashboardWidgets.monthlyAnniversary.meta?.cutoff,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                            <button
                                                v-if="dashboardWidgets.monthlyAnniversary.meta?.recovery_hint"
                                                type="button"
                                                class="btn-modern btn-warning btn-sm"
                                                @click="
                                                    applyRecoveryHint(
                                                        dashboardWidgets.monthlyAnniversary.meta.recovery_hint,
                                                    )
                                                "
                                            >
                                                <i class="bi bi-tools me-1"></i>
                                                Recovery
                                                {{
                                                    (
                                                        dashboardWidgets.monthlyAnniversary.meta.recovery_hint
                                                            .kode_karyawan || []
                                                    ).length
                                                }}
                                                Gap
                                            </button>
                                        </div>

                                        <div
                                            v-if="dashboardWidgets.monthlyAnniversary.error"
                                            class="alert alert-warning py-2 mb-3"
                                        >
                                            {{ dashboardWidgets.monthlyAnniversary.error.message }}
                                        </div>

                                        <div class="monthly-anniversary-metrics">
                                            <div class="monthly-anniv-metric">
                                                <span>Eligible</span>
                                                <strong>{{
                                                    dashboardWidgets.monthlyAnniversary.meta?.expected || 0
                                                }}</strong>
                                            </div>
                                            <div class="monthly-anniv-metric success">
                                                <span>Sudah dapat</span>
                                                <strong>{{
                                                    dashboardWidgets.monthlyAnniversary.meta?.realized || 0
                                                }}</strong>
                                            </div>
                                            <div class="monthly-anniv-metric danger">
                                                <span>Gap</span>
                                                <strong>{{
                                                    dashboardWidgets.monthlyAnniversary.meta?.gap || 0
                                                }}</strong>
                                            </div>
                                            <div class="monthly-anniv-metric muted">
                                                <span>Tidak lengkap</span>
                                                <strong>{{
                                                    dashboardWidgets.monthlyAnniversary.meta?.blocked || 0
                                                }}</strong>
                                            </div>
                                        </div>

                                        <div class="monthly-anniversary-list">
                                            <button
                                                v-for="item in (
                                                    dashboardWidgets.monthlyAnniversary.meta?.grouped?.problem || []
                                                ).slice(0, 5)"
                                                :key="`monthly-problem-${item.kode_karyawan}`"
                                                type="button"
                                                class="monthly-anniv-row"
                                                @click="
                                                    item.status !== 'blocked' &&
                                                    applyRecoveryHint(buildItemRecoveryHint(item))
                                                "
                                            >
                                                <span>
                                                    <strong>{{ item.nama }}</strong>
                                                    <small>
                                                        <code>{{ item.kode_karyawan }}</code>
                                                        Â· {{ item.divisi || '-' }}
                                                    </small>
                                                </span>
                                                <span :class="['badge', heatmapItemStatusClass(item)]">
                                                    {{ heatmapItemStatusBadge(item) }}
                                                </span>
                                            </button>
                                            <div
                                                v-if="
                                                    (dashboardWidgets.monthlyAnniversary.meta?.grouped?.problem || [])
                                                        .length === 0
                                                "
                                                class="empty-inline"
                                            >
                                                Semua karyawan due bulan ini sudah mendapat jatah cuti tahunan.
                                            </div>
                                        </div>

                                        <div class="monthly-anniversary-footer">
                                            <span class="text-muted">
                                                Validasi berdasarkan tanggal masuk karyawan yang due di bulan ini.
                                            </span>
                                            <button
                                                type="button"
                                                class="btn-modern btn-outline btn-sm"
                                                :disabled="!dashboardWidgets.monthlyAnniversary.meta?.period_key"
                                                @click="
                                                    openTrendDetail(
                                                        {
                                                            period: dashboardWidgets.monthlyAnniversary.meta
                                                                ?.period_key,
                                                        },
                                                        'MONTHLY_ACCRUAL',
                                                    )
                                                "
                                            >
                                                <i class="bi bi-search me-1"></i>
                                                Detail
                                            </button>
                                        </div>
                                    </section>

                                    <section class="cuti-widget-card">
                                        <div class="cuti-widget-header">
                                            <div>
                                                <div class="widget-title-row">
                                                    <h6>Anniversary Bulan Ini</h6>
                                                    <span class="widget-purpose-badge">
                                                        <i class="bi bi-award"></i>
                                                        Cuti Loyalty / Tambahan
                                                    </span>
                                                </div>
                                                <p class="widget-context-note">
                                                    Monitoring anniversary kerja untuk validasi jatah cuti loyalty.
                                                </p>
                                                <p class="widget-summary-row">
                                                    {{ dashboardWidgets.anniversary.items.length || 0 }} karyawan ·
                                                    <span class="text-success"
                                                        >{{
                                                            dashboardWidgets.anniversary.meta?.count_done || 0
                                                        }}
                                                        dapat</span
                                                    >
                                                    ·
                                                    <span class="text-danger"
                                                        >{{
                                                            dashboardWidgets.anniversary.meta?.count_missed || 0
                                                        }}
                                                        terlewat</span
                                                    >
                                                </p>
                                            </div>
                                            <button
                                                v-if="dashboardWidgets.anniversary.meta?.recovery_hint"
                                                type="button"
                                                class="btn-modern btn-warning btn-sm"
                                                @click="
                                                    applyRecoveryHint(dashboardWidgets.anniversary.meta.recovery_hint)
                                                "
                                            >
                                                <i class="bi bi-tools me-1"></i>
                                                Recovery
                                                {{
                                                    (
                                                        dashboardWidgets.anniversary.meta.recovery_hint.kode_karyawan ||
                                                        []
                                                    ).length
                                                }}
                                                Terlewat
                                            </button>
                                        </div>

                                        <!-- Quick stat chips -->
                                        <div class="anniv-stat-chips mb-2">
                                            <span
                                                class="chip chip-success"
                                                v-if="(dashboardWidgets.anniversary.meta?.count_done || 0) > 0"
                                            >
                                                <i class="bi bi-check-circle"></i>
                                                {{ dashboardWidgets.anniversary.meta.count_done }} sudah dapat
                                            </span>
                                            <span
                                                class="chip chip-today"
                                                v-if="(dashboardWidgets.anniversary.meta?.count_today || 0) > 0"
                                            >
                                                <i class="bi bi-calendar-event"></i>
                                                {{ dashboardWidgets.anniversary.meta.count_today }} hari ini
                                            </span>
                                            <span
                                                class="chip chip-danger"
                                                v-if="(dashboardWidgets.anniversary.meta?.count_missed || 0) > 0"
                                            >
                                                <i class="bi bi-exclamation-circle"></i>
                                                {{ dashboardWidgets.anniversary.meta.count_missed }} terlewat
                                            </span>
                                            <span
                                                class="chip chip-dark"
                                                v-if="(dashboardWidgets.anniversary.meta?.count_blocked || 0) > 0"
                                            >
                                                <i class="bi bi-lock"></i>
                                                {{ dashboardWidgets.anniversary.meta.count_blocked }} tidak lengkap
                                            </span>
                                            <span
                                                class="chip chip-muted"
                                                v-if="(dashboardWidgets.anniversary.meta?.count_ineligible || 0) > 0"
                                            >
                                                <i class="bi bi-slash-circle"></i>
                                                {{ dashboardWidgets.anniversary.meta.count_ineligible }} bukan PKWTT
                                            </span>
                                            <span
                                                class="chip chip-info"
                                                v-if="(dashboardWidgets.anniversary.meta?.count_pending || 0) > 0"
                                            >
                                                <i class="bi bi-hourglass-split"></i>
                                                {{ dashboardWidgets.anniversary.meta.count_pending }} menunggu
                                            </span>
                                        </div>

                                        <div
                                            v-if="dashboardWidgets.anniversary.error"
                                            class="alert alert-warning py-2 mb-3"
                                        >
                                            {{ dashboardWidgets.anniversary.error.message }}
                                        </div>

                                        <div class="anniversary-list">
                                            <button
                                                v-for="item in dashboardWidgets.anniversary.items"
                                                :key="item.kode_karyawan"
                                                type="button"
                                                :class="['anniversary-card', `anniv-${item.status}`]"
                                                :title="item.status_label"
                                                @click="openAnniversaryDetail(item)"
                                            >
                                                <div class="anniv-card__main">
                                                    <strong>{{ item.nama }}</strong>
                                                    <small>
                                                        <code>{{ item.kode_karyawan }}</code>
                                                        · {{ item.divisi || '-' }}
                                                        <span
                                                            v-if="item.status_kontrak"
                                                            class="badge bg-light text-dark ms-1"
                                                            >{{ item.status_kontrak }}</span
                                                        >
                                                    </small>
                                                </div>
                                                <div class="anniv-card__date">
                                                    <i class="bi bi-calendar3 me-1"></i
                                                    >{{ formatHumanMonth(item.anniv_date) }}
                                                    <small class="d-block text-muted"
                                                        >{{ item.masa_kerja_tahun }} thn kerja</small
                                                    >
                                                </div>
                                                <div class="anniv-card__status">
                                                    <span :class="['anniversary-status', anniversaryStatusClass(item)]">
                                                        <i :class="['bi me-1', anniversaryStatusIcon(item)]"></i>
                                                        {{ anniversaryStatusLabel(item) }}
                                                    </span>
                                                </div>
                                            </button>
                                            <div
                                                v-if="dashboardWidgets.anniversary.items.length === 0"
                                                class="empty-inline"
                                            >
                                                Tidak ada anniversary bulan ini.
                                            </div>
                                        </div>
                                    </section>
                                </div>

                                <div v-if="!processDashboard.installed" class="alert alert-warning">
                                    {{ processDashboard.message || 'Table process batch belum tersedia.' }}
                                </div>

                                <div v-if="loading.processDashboard" class="loading-state">
                                    <div class="loading-spinner">
                                        <i
                                            class="bi bi-arrow-repeat d-flex justify-content-center align-items-center"
                                        ></i>
                                    </div>
                                    <h4 class="loading-title">Memuat Dashboard...</h4>
                                </div>

                                <div v-else>
                                    <div class="process-stat-grid mb-4">
                                        <div class="process-stat">
                                            <span class="process-stat-value">{{
                                                processDashboard.summary.total_batch || 0
                                            }}</span>
                                            <span class="process-stat-label">Total Batch</span>
                                        </div>
                                        <div class="process-stat success">
                                            <span class="process-stat-value">{{
                                                processDashboard.summary.total_success || 0
                                            }}</span>
                                            <span class="process-stat-label">Success</span>
                                        </div>
                                        <div class="process-stat danger">
                                            <span class="process-stat-value">{{
                                                processDashboard.summary.total_failed || 0
                                            }}</span>
                                            <span class="process-stat-label">Failed</span>
                                        </div>
                                        <div class="process-stat warning">
                                            <span class="process-stat-value">{{
                                                processDashboard.summary.missing_monthly_accrual || 0
                                            }}</span>
                                            <span class="process-stat-label"
                                                >Belum Akrual
                                                {{ formatPeriodKey(processDashboard.summary.period_key) }}</span
                                            >
                                        </div>
                                        <div class="process-stat danger">
                                            <span class="process-stat-value">{{
                                                processDashboard.summary.karyawan_hutang || 0
                                            }}</span>
                                            <span class="process-stat-label">Karyawan Hutang</span>
                                        </div>
                                        <div class="process-stat">
                                            <span class="process-stat-value">{{
                                                processDashboard.summary.aktif_tanpa_saldo_tahunan || 0
                                            }}</span>
                                            <span class="process-stat-label">Aktif Tanpa Saldo</span>
                                        </div>
                                    </div>

                                    <div class="row g-4 process-section-grid">
                                        <div class="col-lg-12">
                                            <div class="process-section-panel h-100">
                                                <div
                                                    class="section-head d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"
                                                >
                                                    <div>
                                                        <h6 class="section-title mb-1">Batch Terakhir</h6>
                                                        <small class="text-muted">
                                                            Ringkasan batch terbaru dengan source, status, dan hasil.
                                                        </small>
                                                    </div>
                                                    <span class="badge bg-light text-dark process-count-chip">
                                                        {{ processDashboard.latest_batches.length }} batch
                                                    </span>
                                                </div>

                                                <div
                                                    v-if="processDashboard.latest_batches.length > 0"
                                                    class="d-none d-lg-block"
                                                >
                                                    <div class="table-responsive">
                                                        <table class="table table-modern process-table">
                                                            <thead>
                                                                <tr>
                                                                    <th>Source</th>
                                                                    <th>Status</th>
                                                                    <th>Periode</th>
                                                                    <th class="text-center">Success</th>
                                                                    <th class="text-center">Failed</th>
                                                                    <th>Origin</th>
                                                                    <th>Dibuat</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr
                                                                    v-for="batch in processDashboard.latest_batches"
                                                                    :key="batch.Id_Batch"
                                                                >
                                                                    <td>
                                                                        <div class="process-main-cell">
                                                                            <span class="process-title">{{
                                                                                processSourceLabel(batch.Source_Type)
                                                                            }}</span>
                                                                            <small class="text-muted d-block">{{
                                                                                batch.Source_Type
                                                                            }}</small>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <span
                                                                            :class="[
                                                                                'badge',
                                                                                getProcessStatusClass(batch.Status),
                                                                            ]"
                                                                        >
                                                                            {{ processStatusLabel(batch.Status) }}
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <div class="process-main-cell">
                                                                            <span class="process-title">{{
                                                                                processPeriodLabel(batch)
                                                                            }}</span>
                                                                            <small class="text-muted d-block">{{
                                                                                processCreatedLabel(batch.Created_At)
                                                                            }}</small>
                                                                        </div>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <span class="process-number success">{{
                                                                            formatNumber(batch.Total_Success)
                                                                        }}</span>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <span class="process-number danger">{{
                                                                            formatNumber(batch.Total_Failed)
                                                                        }}</span>
                                                                    </td>
                                                                    <td>
                                                                        <span
                                                                            :class="['badge', batchOriginClass(batch)]"
                                                                        >
                                                                            {{ batchOriginLabel(batch) }}
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <div class="process-main-cell">
                                                                            <span class="process-title">{{
                                                                                formatDateTime(batch.Created_At)
                                                                            }}</span>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                                <div v-else class="process-empty-card">Belum ada batch terbaru.</div>

                                                <div
                                                    v-if="processDashboard.latest_batches.length > 0"
                                                    class="d-lg-none process-mobile-list mt-3"
                                                >
                                                    <article
                                                        v-for="batch in processDashboard.latest_batches"
                                                        :key="batch.Id_Batch"
                                                        class="process-batch-card"
                                                    >
                                                        <div class="process-batch-card__header">
                                                            <div>
                                                                <div class="process-title">
                                                                    {{ processSourceLabel(batch.Source_Type) }}
                                                                </div>
                                                                <small class="text-muted d-block">{{
                                                                    batch.Source_Type
                                                                }}</small>
                                                            </div>
                                                            <span
                                                                :class="['badge', getProcessStatusClass(batch.Status)]"
                                                            >
                                                                {{ processStatusLabel(batch.Status) }}
                                                            </span>
                                                        </div>
                                                        <div class="process-batch-card__body">
                                                            <div class="process-meta-grid">
                                                                <div class="process-meta-item">
                                                                    <span class="process-meta-label">Periode</span>
                                                                    <strong>{{ processPeriodLabel(batch) }}</strong>
                                                                </div>
                                                                <div class="process-meta-item">
                                                                    <span class="process-meta-label">Dibuat</span>
                                                                    <strong>{{
                                                                        processCreatedLabel(batch.Created_At)
                                                                    }}</strong>
                                                                </div>
                                                                <div class="process-meta-item">
                                                                    <span class="process-meta-label">Success</span>
                                                                    <strong class="text-success">{{
                                                                        formatNumber(batch.Total_Success)
                                                                    }}</strong>
                                                                </div>
                                                                <div class="process-meta-item">
                                                                    <span class="process-meta-label">Failed</span>
                                                                    <strong class="text-danger">{{
                                                                        formatNumber(batch.Total_Failed)
                                                                    }}</strong>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="process-batch-card__footer">
                                                            <span :class="['badge', batchOriginClass(batch)]">
                                                                {{ batchOriginLabel(batch) }}
                                                            </span>
                                                        </div>
                                                    </article>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-lg-12">
                                            <div class="process-section-panel h-100">
                                                <div
                                                    class="section-head d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"
                                                >
                                                    <div>
                                                        <h6 class="section-title mb-1">Failed Terbaru</h6>
                                                        <small class="text-muted"
                                                            >Detail kegagalan terakhir yang paling mudah
                                                            ditindaklanjuti.</small
                                                        >
                                                    </div>
                                                    <span class="badge bg-light text-dark process-count-chip">
                                                        {{ processDashboard.failed_details.length }} item
                                                    </span>
                                                </div>

                                                <div
                                                    v-if="processDashboard.failed_details.length > 0"
                                                    class="process-list"
                                                >
                                                    <article
                                                        v-for="item in processDashboard.failed_details"
                                                        :key="item.Id_Detail"
                                                        class="process-detail-card"
                                                    >
                                                        <div class="process-detail-card__header">
                                                            <div>
                                                                <strong class="d-block process-detail-code">{{
                                                                    item.Kode_Karyawan || '-'
                                                                }}</strong>
                                                                <small class="d-block text-muted">
                                                                    {{ processSourceLabel(item.Source_Type) }} ·
                                                                    {{ formatPeriodKey(item.Period_Key) }}
                                                                </small>
                                                            </div>
                                                            <span class="badge bg-danger">
                                                                {{ processStatusLabel(item.Status) }}
                                                            </span>
                                                        </div>
                                                        <div class="process-detail-message">
                                                            {{ item.Message || 'Tidak ada pesan detail.' }}
                                                        </div>
                                                    </article>
                                                </div>

                                                <div v-else class="process-empty-card">
                                                    Tidak ada failed detail terbaru.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template #recovery>
                            <div class="tab-content-improved">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="section-title mb-0">
                                        <i class="bi bi-tools me-2 tengahkan"></i>Recovery Akrual
                                    </h5>
                                    <button class="btn-modern btn-secondary" @click="loadProcessBatches">
                                        <i class="bi bi-clock-history me-2 tengahkan"></i>Riwayat Batch
                                    </button>
                                </div>

                                <div class="recovery-panel mb-4">
                                    <div class="row g-3">
                                        <div class="col-md-2">
                                            <label class="form-label">Jenis Recovery</label>
                                            <el-select
                                                v-model="recoveryForm.source_type"
                                                class="w-100 recovery-field"
                                                size="large"
                                            >
                                                <el-option label="Akrual Bulanan" value="MONTHLY_ACCRUAL" />
                                                <el-option label="Loyalty" value="LOYALTY_ACCRUAL" />
                                            </el-select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Bulan</label>
                                            <el-date-picker
                                                v-model="recoveryMonthDraft"
                                                class="w-100 recovery-field"
                                                size="large"
                                                type="month"
                                                value-format="YYYY-MM"
                                                format="MMM YYYY"
                                                placeholder="Pilih bulan"
                                                @change="handleRecoveryMonthPick"
                                            />
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Tanggal Due</label>
                                            <el-date-picker
                                                v-model="recoveryForm.due_dates"
                                                class="w-100 recovery-field"
                                                size="large"
                                                type="dates"
                                                value-format="YYYY-MM-DD"
                                                format="DD/MM/YYYY"
                                                placeholder="Pilih satu atau lebih tanggal"
                                            />
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Target Karyawan</label>
                                            <el-select
                                                v-model="recoveryForm.employee_scope"
                                                class="w-100 recovery-field mb-2"
                                                size="large"
                                                @change="handleRecoveryEmployeeScopeChange"
                                            >
                                                <el-option label="Pilih satu karyawan" value="selected" />
                                                <el-option label="Seluruh karyawan" value="all" />
                                            </el-select>
                                            <el-select
                                                v-model="recoveryForm.kode_karyawan"
                                                class="w-100 recovery-field"
                                                size="large"
                                                filterable
                                                remote
                                                reserve-keyword
                                                clearable
                                                :disabled="recoveryForm.employee_scope === 'all'"
                                                :remote-method="handleRecoveryKaryawanSearch"
                                                :loading="recoveryKaryawanLoading"
                                                no-data-text="Ketik untuk mencari karyawan"
                                                placeholder="Cari nama karyawan"
                                                @visible-change="handleRecoveryKaryawanVisible"
                                            >
                                                <el-option
                                                    v-for="item in recoveryKaryawanOptions"
                                                    :key="item.value"
                                                    :label="item.label"
                                                    :value="item.value"
                                                />
                                            </el-select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Keterangan</label>
                                            <el-input
                                                v-model="recoveryForm.keterangan"
                                                class="recovery-field"
                                                size="large"
                                                placeholder="Recovery admin"
                                            />
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 justify-content-end mt-3">
                                        <button
                                            class="btn-modern btn-outline"
                                            @click="previewRecovery"
                                            :disabled="loading.recovery"
                                        >
                                            <i class="bi bi-eye me-2 tengahkan"></i>Preview
                                        </button>
                                        <button
                                            class="btn-modern btn-success"
                                            @click="executeRecovery"
                                            :disabled="loading.recovery || recoveryPreview.items.length === 0"
                                        >
                                            <i class="bi bi-check2-circle me-2 tengahkan"></i>Execute
                                        </button>
                                    </div>
                                </div>

                                <div v-if="recoveryPreview.summary" class="mb-4">
                                    <div class="process-stat-grid compact mb-3">
                                        <div class="process-stat">
                                            <span class="process-stat-value">{{
                                                recoveryPreview.summary.checked || 0
                                            }}</span
                                            ><span class="process-stat-label">Checked</span>
                                        </div>
                                        <div class="process-stat success">
                                            <span class="process-stat-value">{{
                                                recoveryPreview.summary.success || 0
                                            }}</span
                                            ><span class="process-stat-label">Success</span>
                                        </div>
                                        <div class="process-stat">
                                            <span class="process-stat-value">{{
                                                recoveryPreview.summary.dry_run || 0
                                            }}</span
                                            ><span class="process-stat-label">Dry Run</span>
                                        </div>
                                        <div class="process-stat warning">
                                            <span class="process-stat-value">{{
                                                recoveryPreview.summary.skipped_existing_key || 0
                                            }}</span
                                            ><span class="process-stat-label">Existing Key</span>
                                        </div>
                                        <div class="process-stat danger">
                                            <span class="process-stat-value">{{
                                                recoveryPreview.summary.failed || 0
                                            }}</span
                                            ><span class="process-stat-label">Failed</span>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-modern">
                                            <thead>
                                                <tr>
                                                    <th>Karyawan</th>
                                                    <th>Source</th>
                                                    <th>Periode</th>
                                                    <th>Due</th>
                                                    <th class="text-center">Jumlah</th>
                                                    <th>Status</th>
                                                    <th>Pesan</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="item in recoveryPreview.items"
                                                    :key="`${item.kode_karyawan}-${item.period_key}-${item.cuti_key || item.reason_code}`"
                                                >
                                                    <td>
                                                        {{ item.kode_karyawan
                                                        }}<small class="d-block text-muted">{{
                                                            item.nama_karyawan
                                                        }}</small>
                                                    </td>
                                                    <td>{{ item.source_type }}</td>
                                                    <td>{{ formatPeriodKey(item.period_key) }}</td>
                                                    <td>{{ formatHumanDate(item.due_date) }}</td>
                                                    <td class="text-center">{{ item.jumlah || '-' }}</td>
                                                    <td>
                                                        <span :class="['badge', getProcessStatusClass(item.status)]">{{
                                                            item.status
                                                        }}</span>
                                                    </td>
                                                    <td>{{ item.message }}</td>
                                                </tr>
                                                <tr v-if="recoveryPreview.items.length === 0">
                                                    <td colspan="7" class="text-center text-muted">
                                                        Belum ada preview
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <h6 class="section-title mb-3">Riwayat Batch</h6>
                                <div class="table-responsive">
                                    <table class="table table-modern">
                                        <thead>
                                            <tr>
                                                <th>Batch</th>
                                                <th>Source</th>
                                                <th>Status</th>
                                                <th>Checked</th>
                                                <th>Success</th>
                                                <th>Failed</th>
                                                <th>Origin</th>
                                                <th>Dibuat</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="batch in processBatches" :key="batch.Id_Batch">
                                                <td>{{ batch.Batch_Key }}</td>
                                                <td>{{ batch.Source_Type }}</td>
                                                <td>
                                                    <span :class="['badge', getProcessStatusClass(batch.Status)]">{{
                                                        batch.Status
                                                    }}</span>
                                                </td>
                                                <td>{{ batch.Total_Checked }}</td>
                                                <td>{{ batch.Total_Success }}</td>
                                                <td>{{ batch.Total_Failed }}</td>
                                                <td>
                                                    <span :class="['badge', batchOriginClass(batch)]">{{
                                                        batchOriginLabel(batch)
                                                    }}</span>
                                                </td>
                                                <td>{{ formatDateTime(batch.Created_At) }}</td>
                                            </tr>
                                            <tr v-if="processBatches.length === 0">
                                                <td colspan="8" class="text-center text-muted">Belum ada batch</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </template>

                        <!-- Tab Cuti Bersama -->
                        <template #cuti_bersama>
                            <div class="tab-content-improved">
                                <!-- Header & Add Button -->
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="section-title mb-0">
                                        <i class="bi bi-calendar-event me-2 tengahkan"></i>Cuti Bersama
                                        {{ currentYear }}
                                    </h5>
                                    <button class="btn-modern btn-primary" @click="openCBModal()">
                                        <i class="bi bi-plus-circle me-2 tengahkan"></i>Tambah
                                    </button>
                                </div>

                                <!-- Calendar Grid -->
                                <div class="calendar-container mb-4">
                                    <div class="calendar-header">
                                        <button @click="changeYear(-1)" class="btn-nav">
                                            <i class="bi bi-chevron-left tengahkan"></i>
                                        </button>
                                        <span class="year-display">{{ currentYear }}</span>
                                        <button @click="changeYear(1)" class="btn-nav">
                                            <i class="bi bi-chevron-right tengahkan"></i>
                                        </button>
                                    </div>
                                    <div class="calendar-grid">
                                        <div v-for="month in 12" :key="month" class="month-card">
                                            <div class="month-name">
                                                {{ getMonthName(month) }}
                                            </div>
                                            <div class="month-dates">
                                                <span
                                                    v-for="date in getCutiBersamaDates(month)"
                                                    :key="date"
                                                    class="cb-date"
                                                >
                                                    {{ new Date(date).getDate() }}
                                                </span>
                                                <span v-if="getCutiBersamaDates(month).length === 0" class="no-cb"
                                                    >-</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Loading State -->
                                <div v-if="loading.cutiBersama" class="loading-state">
                                    <div class="loading-spinner">
                                        <i
                                            class="bi bi-arrow-repeat d-flex justify-content-center align-items-center"
                                        ></i>
                                    </div>
                                    <h4 class="loading-title">Memuat Data...</h4>
                                </div>

                                <!-- Empty State -->
                                <div v-else-if="cutiBersamaList.length === 0" class="empty-state">
                                    <div class="empty-icon">
                                        <i class="bi bi-calendar-x tengahkan"></i>
                                    </div>
                                    <h4 class="empty-title">Belum Ada Cuti Bersama</h4>
                                    <p class="empty-description">Klik tombol "Tambah" untuk menambahkan cuti bersama</p>
                                </div>

                                <!-- Table -->
                                <div v-else class="table-responsive mb-4">
                                    <table class="table table-modern">
                                        <thead>
                                            <tr>
                                                <th>Nama Cuti Bersama</th>
                                                <th>Tanggal Mulai</th>
                                                <th>Tanggal Selesai</th>
                                                <th>Jumlah Hari</th>
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
                                                <td>
                                                    <span class="badge bg-primary">{{ item.Jumlah_Hari }} hari</span>
                                                </td>
                                                <td>
                                                    <button
                                                        class="btn-action btn-edit me-1"
                                                        @click="editCutiBersama(item)"
                                                    >
                                                        <i class="bi bi-pencil tengahkan"></i>
                                                    </button>
                                                    <button
                                                        class="btn-action btn-delete"
                                                        @click="confirmDeleteCB(item)"
                                                    >
                                                        <i class="bi bi-trash tengahkan"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Apply Section -->
                                <div class="apply-section" v-if="cutiBersamaList.length > 0">
                                    <h6 class="mb-3">
                                        <i class="bi bi-lightning-charge me-2 tengahkan"></i>Eksekusi Pemotongan Cuti
                                        Bersama
                                    </h6>
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-5">
                                            <label class="form-label">Pilih Cuti Bersama</label>
                                            <select v-model="applyForm.id_cuti_bersama" class="filter-select">
                                                <option value="">-- Pilih --</option>
                                                <option
                                                    v-for="cb in cutiBersamaList"
                                                    :key="cb.Id_Cuti_Bersama"
                                                    :value="cb.Id_Cuti_Bersama"
                                                >
                                                    {{ cb.Nama_Cuti_Bersama }} ({{ cb.Jumlah_Hari }}
                                                    hari)
                                                </option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Potong ke Jenis Cuti</label>
                                            <select v-model="applyForm.id_jenis_cuti" class="filter-select">
                                                <option value="">-- Pilih Jenis --</option>
                                                <option
                                                    v-for="jc in jenisCutiOptions"
                                                    :key="jc.Id_Jenis_Cuti"
                                                    :value="jc.Id_Jenis_Cuti"
                                                >
                                                    {{ jc.Nama_Jenis_Cuti }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <button
                                                class="btn-modern btn-warning w-100"
                                                @click="previewApply"
                                                :disabled="!applyForm.id_cuti_bersama || !applyForm.id_jenis_cuti"
                                            >
                                                <i class="bi bi-eye me-2 tengahkan"></i>Preview Dampak
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </ApprovalTabs>
                </div>
            </div>
        </div>

        <!-- Modal Detail Dashboard Cuti -->
        <Modal
            :show="dashboardDetail.show"
            :title="dashboardDetail.title"
            size="xlarge"
            :loading="loading.dashboardDetail"
            loadingText="Memuat detail..."
            @close="closeDashboardDetail"
        >
            <template #body>
                <div v-if="dashboardDetail.type === 'heatmap'" class="dashboard-detail-content">
                    <!-- Ringkasan validasi (4 kartu) -->
                    <div class="process-stat-grid compact mb-3">
                        <div class="process-stat">
                            <span class="process-stat-value">{{ dashboardDetail.data.meta?.due_count || 0 }}</span>
                            <span class="process-stat-label">Karyawan Due</span>
                        </div>
                        <div class="process-stat success">
                            <span class="process-stat-value">{{ dashboardDetail.data.meta?.done_count || 0 }}</span>
                            <span class="process-stat-label">Sudah Dapat</span>
                        </div>
                        <div
                            class="process-stat"
                            :class="(dashboardDetail.data.meta?.problem_count || 0) > 0 ? 'danger' : ''"
                        >
                            <span class="process-stat-value">{{ dashboardDetail.data.meta?.problem_count || 0 }}</span>
                            <span class="process-stat-label">Bermasalah</span>
                        </div>
                        <div
                            class="process-stat"
                            :class="heatmapSchedulerStatusOk(dashboardDetail.data.meta) ? 'success' : 'warning'"
                        >
                            <span class="process-stat-value">
                                <i
                                    :class="[
                                        'bi',
                                        heatmapSchedulerStatusOk(dashboardDetail.data.meta)
                                            ? 'bi-check-circle'
                                            : 'bi-exclamation-triangle',
                                    ]"
                                ></i>
                            </span>
                            <span class="process-stat-label">{{
                                heatmapSchedulerStatusLabel(dashboardDetail.data.meta)
                            }}</span>
                        </div>
                    </div>

                    <!-- Alert scheduler tidak jalan -->
                    <div
                        v-if="heatmapSchedulerMissingMessage(dashboardDetail.data.meta)"
                        class="alert alert-danger py-2 mb-3 d-flex align-items-start"
                    >
                        <i class="bi bi-exclamation-octagon me-2 mt-1"></i>
                        <div>
                            <strong>Scheduler tidak jalan hari ini.</strong>
                            <div>{{ heatmapSchedulerMissingMessage(dashboardDetail.data.meta) }}</div>
                            <small class="text-muted">Lapor ke admin IT bila berulang.</small>
                        </div>
                    </div>

                    <!-- Bulk recovery action -->
                    <div
                        v-if="dashboardDetail.data.meta?.recovery_hint"
                        class="d-flex justify-content-between align-items-center mb-3 p-2 px-3 recovery-cta"
                    >
                        <div>
                            <strong
                                >Recovery untuk
                                {{ dashboardDetail.data.meta.recovery_hint.kode_karyawan.length }} karyawan
                                bermasalah</strong
                            >
                            <small class="d-block text-muted">
                                Source:
                                {{ recoveryHintSourceLabel(dashboardDetail.data.meta.recovery_hint.source_type) }} ·
                                Periode: {{ (dashboardDetail.data.meta.recovery_hint.months || []).join(', ') || '-' }}
                            </small>
                        </div>
                        <button
                            type="button"
                            class="btn-modern btn-warning btn-sm"
                            @click="applyRecoveryHint(dashboardDetail.data.meta.recovery_hint)"
                        >
                            <i class="bi bi-tools me-1"></i>Recovery Sekarang
                        </button>
                    </div>

                    <!-- Tabel karyawan bermasalah -->
                    <h6 class="section-title mb-2">
                        <i class="bi bi-exclamation-circle text-danger me-1"></i>Karyawan Bermasalah
                        <span class="badge bg-light text-dark ms-1">{{
                            (dashboardDetail.data.meta?.grouped?.problem || []).length
                        }}</span>
                    </h6>
                    <div class="table-responsive mb-3 d-none d-md-block">
                        <table class="table table-modern table-compact-detail">
                            <thead>
                                <tr>
                                    <th>Karyawan</th>
                                    <th>Jenis</th>
                                    <th>Status</th>
                                    <th>Alasan</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in dashboardDetail.data.meta?.grouped?.problem || []"
                                    :key="`prob-${item.kode_karyawan}-${item.source_type}`"
                                >
                                    <td>
                                        <div class="detail-employee-cell">
                                            <code>{{ item.kode_karyawan }}</code>
                                            <strong>{{ item.nama }}</strong>
                                            <small>{{ item.divisi || '-' }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">{{ item.source_label }}</span>
                                    </td>
                                    <td>
                                        <span :class="['badge', heatmapItemStatusClass(item)]">
                                            {{ heatmapItemStatusBadge(item) }}
                                        </span>
                                    </td>
                                    <td>
                                        <small>{{ item.status_label }}</small>
                                        <small v-if="item.message" class="d-block text-muted">{{ item.message }}</small>
                                    </td>
                                    <td class="text-end">
                                        <button
                                            v-if="item.status !== 'blocked'"
                                            type="button"
                                            class="btn-modern btn-outline btn-sm"
                                            title="Recovery ke tab Recovery"
                                            @click="applyRecoveryHint(buildItemRecoveryHint(item))"
                                        >
                                            <i class="bi bi-tools"></i>
                                        </button>
                                        <span
                                            v-else
                                            class="text-muted small"
                                            title="Lengkapi profil karyawan (status kontrak) terlebih dahulu di Master Karyawan"
                                        >
                                            <i class="bi bi-lock"></i> Perbaiki Profil
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="(dashboardDetail.data.meta?.grouped?.problem || []).length === 0">
                                    <td colspan="5" class="text-center text-muted py-3">
                                        <i class="bi bi-check-circle text-success me-1"></i>
                                        Tidak ada karyawan bermasalah hari ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="detail-mobile-list d-md-none mb-3">
                        <article
                            v-for="item in dashboardDetail.data.meta?.grouped?.problem || []"
                            :key="`prob-mobile-${item.kode_karyawan}-${item.source_type}`"
                            class="detail-mobile-card problem"
                        >
                            <div class="detail-mobile-card__top">
                                <div>
                                    <code>{{ item.kode_karyawan }}</code>
                                    <strong>{{ item.nama }}</strong>
                                    <small>{{ item.divisi || '-' }}</small>
                                </div>
                                <span class="badge bg-light text-dark">{{ item.source_label }}</span>
                            </div>
                            <div class="detail-mobile-card__middle">
                                <span :class="['badge', heatmapItemStatusClass(item)]">
                                    {{ heatmapItemStatusBadge(item) }}
                                </span>
                                <small class="text-muted">{{ item.status_label }}</small>
                            </div>
                            <p v-if="item.message" class="detail-mobile-card__reason">{{ item.message }}</p>
                            <div class="detail-mobile-card__action">
                                <button
                                    v-if="item.status !== 'blocked'"
                                    type="button"
                                    class="btn-modern btn-outline btn-sm"
                                    title="Recovery ke tab Recovery"
                                    @click="applyRecoveryHint(buildItemRecoveryHint(item))"
                                >
                                    <i class="bi bi-tools me-1"></i> Recovery
                                </button>
                                <span
                                    v-else
                                    class="text-muted small"
                                    title="Lengkapi profil karyawan (status kontrak) terlebih dahulu di Master Karyawan"
                                >
                                    <i class="bi bi-lock"></i> Perbaiki Profil
                                </span>
                            </div>
                        </article>
                        <div
                            v-if="(dashboardDetail.data.meta?.grouped?.problem || []).length === 0"
                            class="empty-inline"
                        >
                            <i class="bi bi-check-circle text-success me-1"></i>
                            Tidak ada karyawan bermasalah hari ini.
                        </div>
                    </div>

                    <!-- Karyawan sudah dapat (collapsible) -->
                    <details class="done-list-collapsible mb-2">
                        <summary>
                            <i class="bi bi-check2-circle text-success me-1"></i>
                            Karyawan Sudah Dapat
                            <span class="badge bg-light text-dark ms-1">{{
                                (dashboardDetail.data.meta?.grouped?.done || []).length
                            }}</span>
                            <small class="text-muted ms-2">(klik untuk lihat)</small>
                        </summary>
                        <div class="table-responsive mt-2 d-none d-md-block">
                            <table class="table table-modern table-sm table-compact-detail">
                                <thead>
                                    <tr>
                                        <th>Karyawan</th>
                                        <th>Diproses</th>
                                        <th class="text-end">Id Log</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in dashboardDetail.data.meta?.grouped?.done || []"
                                        :key="`done-${item.kode_karyawan}-${item.source_type}`"
                                    >
                                        <td>
                                            <div class="detail-employee-cell">
                                                <code>{{ item.kode_karyawan }}</code>
                                                <strong>{{ item.nama }}</strong>
                                                <small>{{ item.divisi || '-' }}</small>
                                            </div>
                                        </td>
                                        <td>
                                            <small>{{ formatDateTime(item.processed_at) }}</small>
                                        </td>
                                        <td class="text-end">
                                            <small class="text-muted">{{ item.log_id || '-' }}</small>
                                        </td>
                                    </tr>
                                    <tr v-if="(dashboardDetail.data.meta?.grouped?.done || []).length === 0">
                                        <td colspan="6" class="text-center text-muted py-2">
                                            Belum ada karyawan yang dapat hari ini.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="detail-mobile-list d-md-none mt-2">
                            <article
                                v-for="item in dashboardDetail.data.meta?.grouped?.done || []"
                                :key="`done-mobile-${item.kode_karyawan}-${item.source_type}`"
                                class="detail-mobile-card done"
                            >
                                <div class="detail-mobile-card__top">
                                    <div>
                                        <code>{{ item.kode_karyawan }}</code>
                                        <strong>{{ item.nama }}</strong>
                                        <small>{{ item.divisi || '-' }}</small>
                                    </div>
                                    <span class="badge bg-light text-dark">{{ item.source_label }}</span>
                                </div>
                                <div class="detail-mobile-card__middle">
                                    <small class="text-muted">Diproses</small>
                                    <strong>{{ formatDateTime(item.processed_at) }}</strong>
                                </div>
                                <div class="detail-mobile-card__footer">
                                    <small class="text-muted">Id Log: {{ item.log_id || '-' }}</small>
                                </div>
                            </article>
                            <div
                                v-if="(dashboardDetail.data.meta?.grouped?.done || []).length === 0"
                                class="empty-inline"
                            >
                                Belum ada karyawan yang dapat di periode ini.
                            </div>
                        </div>
                    </details>
                </div>

                <div v-else-if="dashboardDetail.type === 'trend'" class="dashboard-detail-content">
                    <!-- 4 stat card: Expected / Realized / Gap / Blocked -->
                    <div class="process-stat-grid compact mb-3">
                        <div class="process-stat">
                            <span class="process-stat-value">{{ dashboardDetail.data.meta?.expected || 0 }}</span>
                            <span class="process-stat-label">Karyawan Eligible</span>
                        </div>
                        <div class="process-stat success">
                            <span class="process-stat-value">{{ dashboardDetail.data.meta?.realized || 0 }}</span>
                            <span class="process-stat-label">Sudah Dapat</span>
                        </div>
                        <div class="process-stat" :class="(dashboardDetail.data.meta?.gap || 0) > 0 ? 'danger' : ''">
                            <span class="process-stat-value">{{ dashboardDetail.data.meta?.gap || 0 }}</span>
                            <span class="process-stat-label">Gap (Belum Dapat)</span>
                        </div>
                        <div
                            class="process-stat"
                            :class="(dashboardDetail.data.meta?.blocked || 0) > 0 ? 'warning' : ''"
                        >
                            <span class="process-stat-value">{{ dashboardDetail.data.meta?.blocked || 0 }}</span>
                            <span class="process-stat-label">Data Tidak Lengkap</span>
                        </div>
                    </div>

                    <!-- Bulk Recovery CTA (skip blocked) -->
                    <div
                        v-if="dashboardDetail.data.meta?.recovery_hint"
                        class="d-flex justify-content-between align-items-center mb-3 p-2 px-3 recovery-cta"
                    >
                        <div>
                            <strong
                                >Recovery untuk
                                {{ dashboardDetail.data.meta.recovery_hint.kode_karyawan.length }} karyawan
                                bermasalah</strong
                            >
                            <small class="d-block text-muted">
                                Source:
                                {{ recoveryHintSourceLabel(dashboardDetail.data.meta.recovery_hint.source_type) }} ·
                                Periode: {{ (dashboardDetail.data.meta.recovery_hint.months || []).join(', ') || '-' }}
                            </small>
                        </div>
                        <button
                            type="button"
                            class="btn-modern btn-warning btn-sm"
                            @click="applyRecoveryHint(dashboardDetail.data.meta.recovery_hint)"
                        >
                            <i class="bi bi-tools me-1"></i>Recovery Sekarang
                        </button>
                    </div>

                    <!-- Tabel karyawan bermasalah (gap + blocked) -->
                    <h6 class="section-title mb-2">
                        <i class="bi bi-exclamation-circle text-danger me-1"></i>Karyawan Bermasalah
                        <span class="badge bg-light text-dark ms-1">{{
                            (dashboardDetail.data.meta?.grouped?.problem || []).length
                        }}</span>
                    </h6>
                    <div class="table-responsive mb-3 d-none d-md-block">
                        <table class="table table-modern table-compact-detail">
                            <thead>
                                <tr>
                                    <th>Karyawan</th>
                                    <th>Status</th>
                                    <th>Alasan</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in dashboardDetail.data.meta?.grouped?.problem || []"
                                    :key="`prob-${item.kode_karyawan}-${item.source_type}-${item.period_key}`"
                                >
                                    <td>
                                        <div class="detail-employee-cell">
                                            <code>{{ item.kode_karyawan }}</code>
                                            <strong>{{ item.nama }}</strong>
                                            <small>{{ item.divisi || '-' }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span :class="['badge', heatmapItemStatusClass(item)]">
                                            {{ heatmapItemStatusBadge(item) }}
                                        </span>
                                    </td>
                                    <td>
                                        <small>{{ item.status_label }}</small>
                                        <small v-if="item.message" class="d-block text-muted">{{ item.message }}</small>
                                    </td>
                                    <td class="text-end">
                                        <button
                                            v-if="item.status !== 'blocked'"
                                            type="button"
                                            class="btn-modern btn-outline btn-sm"
                                            title="Recovery ke tab Recovery"
                                            @click="applyRecoveryHint(buildItemRecoveryHint(item))"
                                        >
                                            <i class="bi bi-tools"></i>
                                        </button>
                                        <span
                                            v-else
                                            class="text-muted small"
                                            title="Lengkapi profil karyawan (status kontrak) di Master Karyawan"
                                        >
                                            <i class="bi bi-lock"></i> Perbaiki Profil
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="(dashboardDetail.data.meta?.grouped?.problem || []).length === 0">
                                    <td colspan="6" class="text-center text-muted py-3">
                                        <i class="bi bi-check-circle text-success me-1"></i>
                                        Tidak ada karyawan bermasalah di periode ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Karyawan sudah dapat (collapsible) -->
                    <details class="done-list-collapsible mb-2">
                        <summary>
                            <i class="bi bi-check2-circle text-success me-1"></i>
                            Karyawan Sudah Dapat
                            <span class="badge bg-light text-dark ms-1">{{
                                (dashboardDetail.data.meta?.grouped?.done || []).length
                            }}</span>
                            <small class="text-muted ms-2">(klik untuk lihat)</small>
                        </summary>
                        <div class="table-responsive mt-2">
                            <table class="table table-modern table-sm">
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Nama</th>
                                        <th>Divisi</th>
                                        <th>Diproses</th>
                                        <th class="text-end">Id Log</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in dashboardDetail.data.meta?.grouped?.done || []"
                                        :key="`done-${item.kode_karyawan}-${item.period_key}`"
                                    >
                                        <td>
                                            <code>{{ item.kode_karyawan }}</code>
                                        </td>
                                        <td>{{ item.nama }}</td>
                                        <td>
                                            <small>{{ item.divisi || '-' }}</small>
                                        </td>
                                        <td>
                                            <small>{{ formatDateTime(item.processed_at) }}</small>
                                        </td>
                                        <td class="text-end">
                                            <small class="text-muted">{{ item.log_id || '-' }}</small>
                                        </td>
                                    </tr>
                                    <tr v-if="(dashboardDetail.data.meta?.grouped?.done || []).length === 0">
                                        <td colspan="3" class="text-center text-muted py-2">
                                            Belum ada karyawan yang dapat di periode ini.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="detail-mobile-list d-md-none mb-3">
                            <article
                                v-for="item in dashboardDetail.data.meta?.grouped?.problem || []"
                                :key="`trend-mobile-${item.kode_karyawan}-${item.source_type}-${item.period_key}`"
                                class="detail-mobile-card problem"
                            >
                                <div class="detail-mobile-card__top">
                                    <div>
                                        <code>{{ item.kode_karyawan }}</code>
                                        <strong>{{ item.nama }}</strong>
                                        <small>{{ item.divisi || '-' }}</small>
                                    </div>
                                    <span :class="['badge', heatmapItemStatusClass(item)]">
                                        {{ heatmapItemStatusBadge(item) }}
                                    </span>
                                </div>
                                <p class="detail-mobile-card__reason">{{ item.status_label }}</p>
                                <p v-if="item.message" class="detail-mobile-card__reason text-muted">
                                    {{ item.message }}
                                </p>
                                <div class="detail-mobile-card__action">
                                    <button
                                        v-if="item.status !== 'blocked'"
                                        type="button"
                                        class="btn-modern btn-outline btn-sm"
                                        title="Recovery ke tab Recovery"
                                        @click="applyRecoveryHint(buildItemRecoveryHint(item))"
                                    >
                                        <i class="bi bi-tools me-1"></i> Recovery
                                    </button>
                                    <span v-else class="text-muted small">
                                        <i class="bi bi-lock"></i> Perbaiki Profil
                                    </span>
                                </div>
                            </article>
                            <div
                                v-if="(dashboardDetail.data.meta?.grouped?.problem || []).length === 0"
                                class="empty-inline"
                            >
                                Tidak ada karyawan bermasalah di periode ini.
                            </div>
                        </div>
                    </details>
                </div>

                <div v-else-if="dashboardDetail.type === 'anniversary'" class="dashboard-detail-content">
                    <div class="detail-header mb-4" v-if="dashboardDetail.data.meta?.karyawan">
                        <div class="avatar-lg">
                            {{ getInitials(dashboardDetail.data.meta.karyawan.Nama) }}
                        </div>
                        <div class="detail-info">
                            <h5 class="mb-1 detail-heading">{{ dashboardDetail.data.meta.karyawan.Nama }}</h5>
                            <p class="detail-subtitle mb-0">
                                {{ dashboardDetail.data.meta.karyawan.Kode_Karyawan }} -
                                {{ dashboardDetail.data.meta.karyawan.divisi || '-' }}
                            </p>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th>No Ref</th>
                                    <th>Cuti Key</th>
                                    <th class="text-center">Jumlah</th>
                                    <th>Dibuat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="log in dashboardDetail.data.items" :key="log.Id_Log || log.Cuti_Key">
                                    <td>{{ log.No_Transaksi_Ref || '-' }}</td>
                                    <td>{{ log.Cuti_Key || '-' }}</td>
                                    <td class="text-center">{{ log.Jumlah || '-' }}</td>
                                    <td>{{ formatDateTime(log.Created_At) }}</td>
                                </tr>
                                <tr v-if="dashboardDetail.data.items.length === 0">
                                    <td colspan="4" class="text-center text-muted">Belum ada log loyalty.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-else class="empty-inline">Pilih item dashboard untuk melihat detail.</div>
            </template>
            <template #footer>
                <button
                    v-if="dashboardDetail.data.meta?.recovery_hint"
                    class="btn-modern btn-success"
                    @click="applyRecoveryHint(dashboardDetail.data.meta.recovery_hint)"
                >
                    <i class="bi bi-tools me-2 tengahkan"></i>Recovery
                </button>
                <button class="btn-modern btn-secondary" @click="closeDashboardDetail">Tutup</button>
            </template>
        </Modal>

        <!-- Modal Detail Karyawan -->
        <Modal :show="showDetailModal" title="Detail Kuota Karyawan" size="large" @close="showDetailModal = false">
            <template #body>
                <div v-if="selectedKaryawan">
                    <!-- Karyawan Info -->
                    <div class="detail-header mb-4">
                        <div class="avatar-lg">
                            {{ getInitials(selectedKaryawan.Nama_Karyawan) }}
                        </div>
                        <div class="detail-info">
                            <h5 class="mb-1 detail-heading">
                                {{ selectedKaryawan.Nama_Karyawan }}
                            </h5>
                            <p class="detail-subtitle mb-0">
                                {{ selectedKaryawan.nama_divisi }} -
                                {{ selectedKaryawan.nama_jabatan }}
                            </p>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="detail-summary-grid mb-4">
                        <div class="detail-summary-item">
                            <div class="summary-card monthly-anniv-metric">
                                <span>Sisa Cuti</span>
                                <strong class="summary-value text-primary">{{
                                    formatNumber(detailData.summary?.Sisa_Cuti_Tampil)
                                }}</strong>
                            </div>
                        </div>
                        <div class="detail-summary-item">
                            <div class="summary-card monthly-anniv-metric warning">
                                <span>Pending</span>
                                <strong class="summary-value text-warning">{{
                                    parseInt(detailData.summary?.Sedang_Diajukan) || 0
                                }}</strong>
                            </div>
                        </div>
                        <!-- <div class="col-3">
                            <div class="summary-card">
                                <span class="summary-value text-info">{{ formatNumber(detailData.summary?.Potongan_Cuti_Bersama) }}</span>
                                <span class="summary-label">CUTI BERSAMA</span>
                            </div>
                        </div> -->
                        <div class="detail-summary-item">
                            <div class="summary-card monthly-anniv-metric danger">
                                <span>Hutang</span>
                                <strong class="summary-value text-danger">{{
                                    formatNumber(detailData.summary?.Hutang_Cuti_Tampil)
                                }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Breakdown Table -->
                    <h6 class="section-title mb-3"><i class="bi bi-list-ul me-2"></i>Detail per Jenis Cuti</h6>
                    <div v-if="loading.detail" class="text-center py-4">
                        <i class="bi bi-arrow-repeat spin"></i> Memuat data...
                    </div>
                    <div v-else class="table-responsive mb-4">
                        <table class="table table-modern table-sm">
                            <thead>
                                <tr>
                                    <th>Jenis Cuti</th>
                                    <th class="text-center">Awal</th>
                                    <th class="text-center">Terpakai</th>
                                    <th class="text-center">Sisa</th>
                                    <th>Expired</th>
                                    <th>Activated At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="b in detailData.breakdown"
                                    :key="b.Id_Buku_Cuti"
                                    :class="{
                                        'hutang-row': parseFloat(b.sisa_cuti) < 0,
                                    }"
                                >
                                    <td>
                                        {{ b.Nama_Jenis_Cuti || b.tipe_cuti }}
                                    </td>
                                    <td class="text-center">
                                        {{ formatNumber(b.jumlah_awal_cuti) }}
                                    </td>
                                    <td class="text-center">
                                        {{ formatNumber(b.jumlah_terpakai) }}
                                    </td>
                                    <td class="text-center">
                                        <strong :class="parseFloat(b.sisa_cuti) < 0 ? 'text-danger' : 'text-success'">
                                            {{ formatNumber(b.sisa_cuti) }}
                                        </strong>
                                    </td>
                                    <td>{{ formatDate(b.tanggal_expired) }}</td>
                                    <td>
                                        {{ formatDate(b.activated_date) }}
                                        <!-- Badge Future -->
                                        <span v-if="new Date(b.activated_date) > new Date()" class="badge bg-info mx-2"
                                            >Future</span
                                        >
                                    </td>
                                    <!-- <td>
                                        <span v-if="parseFloat(b.sisa_cuti) < 0" class="badge bg-danger">Hutang</span>
                                        <span v-else class="text-muted">{{ b.notes || '-' }}</span>
                                    </td> -->
                                </tr>
                                <tr v-if="!detailData.breakdown || detailData.breakdown.length === 0">
                                    <td colspan="6" class="text-center text-muted">Tidak ada data kuota</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Tabs: Riwayat / Log -->
                    <div class="detail-tabs mb-3">
                        <button
                            :class="['tab-btn', { active: detailTab === 'riwayat' }]"
                            @click="
                                detailTab = 'riwayat';
                                loadRiwayat(selectedKaryawan.Kode_Karyawan_Encrypted);
                            "
                        >
                            <i class="bi bi-clock-history me-1"></i>Riwayat Cuti
                        </button>
                        <button
                            :class="['tab-btn', { active: detailTab === 'log' }]"
                            @click="
                                detailTab = 'log';
                                loadLog(selectedKaryawan.Kode_Karyawan_Encrypted);
                            "
                        >
                            <i class="bi bi-journal-text me-1"></i>Log Perubahan
                        </button>
                    </div>

                    <!-- Tab Content: Riwayat -->
                    <div v-if="detailTab === 'riwayat'">
                        <div class="export-header-mobile">
                            <div class="date-range-picker">
                                <input
                                    type="date"
                                    v-model="exportRiwayatForm.date_start"
                                    class="form-control form-control-sm"
                                />
                                <span class="text-muted">s/d</span>
                                <input
                                    type="date"
                                    v-model="exportRiwayatForm.date_end"
                                    class="form-control form-control-sm"
                                />
                            </div>
                            <button
                                class="btn btn-sm btn-success export-btn-mobile"
                                @click="exportRiwayat"
                                :disabled="loading.export"
                            >
                                <i class="bi bi-download me-1 tengahkan"></i>
                                Export
                            </button>
                        </div>
                        <div v-if="loading.riwayat" class="text-center py-4">
                            <i class="bi bi-arrow-repeat spin"></i> Memuat...
                        </div>
                        <div
                            v-else-if="riwayatList.length === 0"
                            class="text-center text-muted py-4 d-flex flex-column justify-content-center align-items-center"
                        >
                            <i class="bi bi-inbox fs-1 d-block mb-4"></i>
                            Belum ada riwayat pengajuan cuti
                        </div>
                        <div v-else class="timeline-list">
                            <div v-for="r in riwayatList" :key="r.No_Transaksi" class="timeline-item">
                                <div class="timeline-date">
                                    {{ formatDate(r.tanggal_pengajuan) }}
                                </div>
                                <div class="timeline-content">
                                    <strong>{{ r.Nama_Jenis_Cuti }}</strong> - {{ r.jumlah_hari }} hari <br /><small
                                        class="text-muted"
                                        >{{ r.Alasan }}</small
                                    >
                                </div>
                                <Badge :type="getBadgeType(r.status_text)">{{ r.status_text }}</Badge>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Content: Log -->
                    <div v-else>
                        <div class="export-header-mobile">
                            <div class="date-range-picker">
                                <input
                                    type="date"
                                    v-model="exportLogForm.date_start"
                                    class="form-control form-control-sm"
                                />
                                <span class="text-muted">s/d</span>
                                <input
                                    type="date"
                                    v-model="exportLogForm.date_end"
                                    class="form-control form-control-sm"
                                />
                            </div>
                            <button
                                class="btn btn-sm btn-success export-btn-mobile"
                                @click="exportLog"
                                :disabled="loading.export"
                            >
                                <i class="bi bi-download me-1 tengahkan"></i>
                                Export
                            </button>
                        </div>
                        <div v-if="loading.log" class="text-center py-4">
                            <i class="bi bi-arrow-repeat spin"></i> Memuat...
                        </div>
                        <div
                            v-else-if="logList.length === 0"
                            class="text-center text-muted py-4 d-flex flex-column justify-content-center align-items-center"
                        >
                            <i class="bi bi-inbox fs-1 d-block mb-4"></i>
                            Belum ada log perubahan kuota
                        </div>
                        <div v-else class="log-list">
                            <div v-for="l in logList" :key="l.Id_Log" class="log-item">
                                <span :class="['log-badge', parseFloat(l.Jumlah) >= 0 ? 'credit' : 'debit']">
                                    {{ parseFloat(l.Jumlah) >= 0 ? '+' : '' }}{{ formatNumber(l.Jumlah) }}
                                </span>
                                <div class="log-info">
                                    <strong>{{ l.Kategori_Transaksi }}</strong>
                                    <small class="d-block text-muted d-none d-md-block">{{ l.Keterangan }}</small>
                                    <small class="d-block text-muted d-block d-md-none">{{
                                        truncateText(l.Keterangan, 20)
                                    }}</small>
                                </div>
                                <small class="text-muted d-none d-md-block"
                                    >{{ formatDateTime(l.Created_At) }} - {{ l.Created_By }}</small
                                >
                                <small class="text-muted d-block d-md-none">{{ formatDate(l.Created_At) }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <button style="flex-grow: 1" class="btn btn-success" @click="showAdjustModal('ADD')">
                    <i class="bi bi-plus-circle me-1 tengahkan"></i> Tambah Kuota
                </button>
                <button style="flex-grow: 1" class="btn btn-danger" @click="showAdjustModal('SUBTRACT')">
                    <i class="bi bi-dash-circle me-1 tengahkan"></i> Kurangi Kuota
                </button>
            </template>
        </Modal>

        <!-- Modal Adjust Kuota -->
        <Modal
            :show="showAdjustKuotaModal"
            :title="adjustForm.tipe === 'ADD' ? 'Tambah Kuota Cuti' : 'Kurangi Kuota Cuti'"
            size="medium"
            @close="showAdjustKuotaModal = false"
        >
            <template #body>
                <div class="mb-3">
                    <label class="form-label">Jenis Cuti <span class="text-danger">*</span></label>
                    <select v-model="adjustForm.id_jenis_cuti" class="form-select" @change="loadAvailableWallets">
                        <option value="">-- Pilih Jenis Cuti --</option>
                        <option v-for="jc in jenisCutiOptions" :key="jc.Id_Jenis_Cuti" :value="jc.Id_Jenis_Cuti">
                            {{ jc.Nama_Jenis_Cuti }}
                        </option>
                    </select>
                </div>

                <!-- Wallet Selection (only for ADD when jenis cuti selected) -->
                <div v-if="adjustForm.tipe === 'ADD'" class="mb-3">
                    <label class="form-label">Dompet Tujuan</label>
                    <select v-model="adjustForm.id_buku_cuti" class="form-select" :disabled="!adjustForm.id_jenis_cuti">
                        <option value="">-- Buat Dompet Baru (Periode {{ currentYear }}) --</option>
                        <option v-for="w in availableWallets" :key="w.id" :value="w.id">
                            {{ w.label }}
                        </option>
                    </select>
                    <small v-if="loading.wallets" class="text-muted">
                        <i class="bi bi-arrow-repeat spin me-1"></i> Memuat dompet...
                    </small>
                    <small v-else-if="!adjustForm.id_jenis_cuti" class="text-muted">
                        <i class="bi bi-info-circle me-1"></i> Pilih jenis cuti terlebih dahulu
                    </small>
                    <small v-else-if="availableWallets.length === 0" class="text-muted">
                        <i class="bi bi-info-circle me-1"></i> Tidak ada dompet aktif. Dompet baru akan dibuat.
                    </small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Jumlah Hari <span class="text-danger">*</span></label>
                    <input
                        type="number"
                        v-model="adjustForm.jumlah"
                        class="form-control"
                        min="1"
                        step="1"
                        :disabled="!adjustForm.id_jenis_cuti"
                    />
                </div>
                <div v-if="adjustForm.tipe === 'ADD' && !adjustForm.id_buku_cuti" class="mb-3">
                    <label class="form-label">Tanggal Berlaku (Aktif)</label>
                    <input
                        type="date"
                        v-model="adjustForm.tanggal_berlaku"
                        class="form-control"
                        :disabled="!adjustForm.id_jenis_cuti"
                    />
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Dompet baru akan dibuat dengan expired 1 tahun dari tanggal berlaku. Hutang cuti akan dilunasi
                        terlebih dahulu.
                    </small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Keterangan <span class="text-danger">*</span> (min 10 karakter)</label>
                    <textarea
                        v-model="adjustForm.keterangan"
                        class="form-control"
                        rows="3"
                        placeholder="Alasan adjustment kuota..."
                        :disabled="!adjustForm.id_jenis_cuti"
                    ></textarea>
                    <small :class="adjustForm.keterangan.length >= 10 ? 'text-success' : 'text-muted'">
                        {{ adjustForm.keterangan.length }}/10 karakter
                    </small>
                </div>
            </template>
            <template #footer>
                <button class="btn btn-secondary" @click="showAdjustKuotaModal = false">Batal</button>
                <button
                    class="btn btn-primary"
                    @click="showConfirmModal"
                    :disabled="!canSubmitAdjust || loading.adjust"
                >
                    <i v-if="loading.adjust" class="bi bi-arrow-repeat spin me-1"></i>
                    Simpan
                </button>
            </template>
        </Modal>

        <!-- Modal Konfirmasi Adjustment -->
        <Modal
            :show="showConfirmAdjustModal"
            title="Konfirmasi Adjustment"
            size="medium"
            @close="showConfirmAdjustModal = false"
        >
            <template #body>
                <div class="confirm-content">
                    <div class="confirm-icon">
                        <i
                            class="d-flex justify-content-center align-items-center mb-2"
                            :class="[
                                'bi',
                                adjustForm.tipe === 'ADD'
                                    ? 'bi-plus-circle-fill text-success'
                                    : 'bi-dash-circle-fill text-danger',
                            ]"
                            style="font-size: 3rem"
                        ></i>
                    </div>
                    <div class="confirm-message mt-3">
                        <h5 class="mb-3">
                            {{ adjustForm.tipe === 'ADD' ? 'Tambah' : 'Kurangi' }}
                            Kuota Cuti
                        </h5>
                        <div class="confirm-details">
                            <div class="row mb-2">
                                <div class="col-5 text-muted">Karyawan</div>
                                <div class="col-7 fw-bold">
                                    {{ selectedKaryawan?.Nama_Karyawan }}
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 text-muted">Jenis Cuti</div>
                                <div class="col-7">
                                    {{ confirmData.jenisCutiName }}
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 text-muted">Jumlah</div>
                                <div class="col-7">
                                    <span
                                        :class="adjustForm.tipe === 'ADD' ? 'text-success' : 'text-danger'"
                                        class="fw-bold"
                                    >
                                        {{ adjustForm.tipe === 'ADD' ? '+' : '-' }}{{ adjustForm.jumlah }} hari
                                    </span>
                                </div>
                            </div>
                            <div v-if="adjustForm.tipe === 'ADD'" class="row mb-2">
                                <div class="col-5 text-muted">Dompet Tujuan</div>
                                <div class="col-7">
                                    {{ confirmData.walletInfo }}
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 text-muted">Keterangan</div>
                                <div class="col-7">
                                    {{ adjustForm.keterangan }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <button class="btn btn-secondary" @click="showConfirmAdjustModal = false">Batal</button>
                <button
                    :class="['btn', adjustForm.tipe === 'ADD' ? 'btn-success' : 'btn-danger']"
                    @click="submitAdjust"
                    :disabled="loading.adjust"
                >
                    <i v-if="loading.adjust" class="bi bi-arrow-repeat spin me-1 tengahkan"></i>
                    <i
                        v-else
                        class="tengahkan"
                        :class="['bi me-1', adjustForm.tipe === 'ADD' ? 'bi-check-lg' : 'bi-dash-lg']"
                    ></i>
                    Ya,
                    {{ adjustForm.tipe === 'ADD' ? 'Tambahkan' : 'Kurangi' }}
                </button>
            </template>
        </Modal>

        <!-- Modal Cuti Bersama -->
        <Modal
            :show="showCutiBersamaModal"
            :title="editingCB ? 'Edit Cuti Bersama' : 'Tambah Cuti Bersama'"
            size="medium"
            @close="closeCBModal"
        >
            <template #body>
                <div class="mb-3">
                    <label class="form-label">Nama Cuti Bersama <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        v-model="cbForm.nama"
                        class="form-control"
                        placeholder="Contoh: Cuti Bersama Lebaran 2026"
                    />
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">Tanggal Mulai <span class="text-danger">*</span></label>
                        <input type="date" v-model="cbForm.tanggal_mulai" class="form-control" />
                    </div>
                    <div class="col-6">
                        <label class="form-label">Tanggal Selesai <span class="text-danger">*</span></label>
                        <input type="date" v-model="cbForm.tanggal_selesai" class="form-control" />
                    </div>
                </div>
                <div
                    v-if="cbForm.tanggal_mulai && cbForm.tanggal_selesai"
                    :class="[
                        'alert',
                        calculateDays(cbForm.tanggal_mulai, cbForm.tanggal_selesai) > 0 ? 'alert-info' : 'alert-danger',
                    ]"
                >
                    <i
                        :class="[
                            'bi me-2',
                            calculateDays(cbForm.tanggal_mulai, cbForm.tanggal_selesai) > 0
                                ? 'bi-info-circle'
                                : 'bi-exclamation-triangle',
                        ]"
                    ></i>
                    <span v-if="calculateDays(cbForm.tanggal_mulai, cbForm.tanggal_selesai) > 0">
                        Jumlah hari:
                        <strong>{{ calculateDays(cbForm.tanggal_mulai, cbForm.tanggal_selesai) }} hari</strong>
                    </span>
                    <span v-else>
                        <strong>Error:</strong> Tanggal Selesai harus setelah atau sama dengan Tanggal Mulai
                    </span>
                </div>
            </template>
            <template #footer>
                <button class="btn btn-secondary" @click="closeCBModal">Batal</button>
                <button
                    class="btn btn-primary"
                    @click="submitCutiBersama"
                    :disabled="
                        !cbForm.nama ||
                        !cbForm.tanggal_mulai ||
                        !cbForm.tanggal_selesai ||
                        calculateDays(cbForm.tanggal_mulai, cbForm.tanggal_selesai) <= 0
                    "
                >
                    Simpan
                </button>
            </template>
        </Modal>

        <!-- Modal Preview Apply -->
        <Modal
            :show="showPreviewModal"
            title="Preview Pemotongan Cuti Bersama"
            size="large"
            @close="showPreviewModal = false"
        >
            <template #body>
                <div v-if="previewData" class="preview-content">
                    <div class="alert alert-warning mb-4">
                        <strong><i class="bi bi-exclamation-triangle me-2"></i>Perhatian!</strong>
                        <p class="mb-0">
                            Anda akan memotong saldo cuti
                            {{ previewData.total_karyawan }} karyawan sebanyak
                            {{ previewData.cuti_bersama?.Jumlah_Hari }} hari.
                        </p>
                    </div>

                    <div class="preview-summary-grid mb-4">
                        <div class="preview-summary-item">
                            <div class="preview-card monthly-anniv-metric success">
                                <span>Saldo Cukup</span>
                                <strong class="preview-value">{{ previewData.preview?.cukup || 0 }}</strong>
                            </div>
                        </div>
                        <div class="preview-summary-item">
                            <div class="preview-card monthly-anniv-metric warning">
                                <span>Akan Jadi Hutang</span>
                                <strong class="preview-value">{{ previewData.preview?.kurang || 0 }}</strong>
                            </div>
                        </div>
                        <div class="preview-summary-item">
                            <div class="preview-card monthly-anniv-metric danger">
                                <span>Sudah Punya Hutang</span>
                                <strong class="preview-value">{{ previewData.preview?.sudah_hutang || 0 }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <button class="btn btn-secondary" @click="showPreviewModal = false">Batal</button>
                <button class="btn btn-danger" @click="executeApply" :disabled="loading.execute">
                    <i v-if="loading.execute" class="bi bi-arrow-repeat spin me-1"></i>
                    <i v-else class="bi bi-check-circle me-1"></i>
                    Eksekusi Pemotongan
                </button>
            </template>
        </Modal>
    </div>
</template>

<script>
import HeaderKpi from '@/components/ui/header.vue';
import ApprovalTabs from '@/components/ui/toggleSectionV2.vue';
import Modal from '@/components/ui/modalV2-noir.vue';
import Badge from '../../../../components/ui/badge.vue';
import quickStat from '@/components/ui/quickStat.vue';
import ModernDataTable from '@/components/ui/table-noir.vue';
import ModernFilter from '@/components/ui/Filter/modernFilter.vue';
import axios from 'axios';
import { debounce } from 'lodash';
import { ElMessage, ElButton, ElDatePicker, ElInput, ElOption, ElSelect } from 'element-plus';
import { Download } from '@element-plus/icons-vue';

export default {
    name: 'KuotaManagement',
    components: {
        HeaderKpi,
        ApprovalTabs,
        Modal,
        Badge,
        quickStat,
        ModernDataTable,
        ModernFilter,
        ElButton,
        ElDatePicker,
        ElInput,
        ElOption,
        ElSelect,
        Download,
    },
    data() {
        return {
            dayNight: new Date().getHours() >= 6 && new Date().getHours() < 18 ? 'Siang' : 'Malam',
            currentDate: new Date(),
            activeTab: 'dashboard',
            adminTabs: [
                {
                    id: 'dashboard',
                    label: 'Dashboard',
                    icon: 'bi bi-speedometer2',
                    count: null,
                },
                {
                    id: 'kuota',
                    label: 'Kuota Karyawan',
                    icon: 'bi bi-people',
                    count: 0,
                },
                {
                    id: 'recovery',
                    label: 'Recovery',
                    icon: 'bi bi-tools',
                    count: 0,
                },
                // { id: "cuti_bersama", label: "Cuti Bersama", icon: "bi bi-calendar-event", count: 0 },
            ],
            loading: {
                processDashboard: false,
                dashboardWidgets: false,
                dashboardDetail: false,
                dashboardBatchDetail: false,
                processBatches: false,
                recovery: false,
                karyawan: false,
                detail: false,
                riwayat: false,
                log: false,
                adjust: false,
                cutiBersama: false,
                execute: false,
                wallets: false,
                export: false,
            },

            // Export Selection
            isAllSelected: false,
            selectAllAllPages: false,
            selectedForExport: [],
            exportRiwayatForm: { date_start: '', date_end: '' },
            exportLogForm: { date_start: '', date_end: '' },

            // Stats
            quickStatField: [
                {
                    title: 'Total Karyawan',
                    value: 0,
                    icon: 'bi bi-people',
                    color: 'primary',
                },
                {
                    title: 'Normal',
                    value: 0,
                    icon: 'bi bi-check-circle',
                    color: 'success',
                },
                {
                    title: 'Pending',
                    value: 0,
                    icon: 'bi bi-hourglass',
                    color: 'warning',
                },
                {
                    title: 'Hutang',
                    value: 0,
                    icon: 'bi bi-exclamation-triangle',
                    color: 'danger',
                },
                {
                    title: 'Avg Sisa',
                    value: 0,
                    icon: 'bi bi-graph-up',
                    color: 'info',
                },
            ],

            // Kuota Tab
            filtersKuota: { search: '', divisi: '', status_kuota: '' },
            karyawanList: [],
            paginationKaryawan: { current_page: 1, per_page: 10, total: 0 },
            karyawanColumns: [
                {
                    key: 'Nama_Karyawan',
                    title: 'Nama',
                    icon: 'bi bi-person',
                    sticky: true,
                },
                { key: 'nama_divisi', title: 'Divisi', icon: 'bi bi-building' },
                {
                    key: 'Sisa_Cuti_Tampil',
                    title: 'Sisa',
                    icon: 'bi bi-calendar-check',
                },
                {
                    key: 'Sedang_Diajukan',
                    title: 'Pending',
                    icon: 'bi bi-hourglass',
                },
                {
                    key: 'Hutang_Cuti_Tampil',
                    title: 'Hutang',
                    icon: 'bi bi-exclamation',
                },
                { key: 'actions', title: 'Aksi', icon: 'bi bi-gear' },
            ],
            karyawanMobileColumns: [
                { key: 'Nama_Karyawan', title: 'Nama' },
                { key: 'Sisa_Cuti_Tampil', title: 'Sisa' },
            ],
            divisiOptions: [],
            jenisCutiOptions: [],
            selectedForMark: [],

            processDashboard: {
                installed: true,
                message: '',
                summary: {},
                latest_batches: [],
                failed_details: [],
            },
            heatmapSource: 'ALL',
            heatmapSourceOptions: [
                { value: 'ALL', label: 'All' },
                { value: 'MONTHLY_ACCRUAL', label: 'Monthly' },
                { value: 'LOYALTY_ACCRUAL', label: 'Loyalty' },
            ],
            dashboardWidgets: {
                heatmap: { metric: 0, items: [], meta: {}, error: null },
                monthlyTrend: { metric: 0, items: [], meta: {}, error: null },
                monthlyAnniversary: { metric: 0, items: [], meta: {}, error: null },
                loyaltyTrend: { metric: 0, items: [], meta: {}, error: null },
                anniversary: { metric: 0, items: [], meta: {}, error: null },
            },
            dashboardDetail: {
                show: false,
                type: '',
                title: 'Detail Dashboard',
                data: { metric: 0, items: [], meta: {}, error: null },
                batchDetail: {
                    batch: null,
                    items: [],
                    pagination: {},
                },
            },
            processBatches: [],
            recoveryKaryawanBaseOptions: [],
            recoveryKaryawanOptions: [],
            recoveryKaryawanLoading: false,
            recoveryKaryawanLoaded: false,
            recoveryKaryawanLastQuery: null,
            recoveryMonthDraft: new Date().toISOString().slice(0, 7),
            recoveryForm: {
                source_type: 'MONTHLY_ACCRUAL',
                month: new Date().toISOString().slice(0, 7),
                due_dates: [],
                employee_scope: 'selected',
                kode_karyawan: '',
                keterangan: 'Recovery admin',
            },
            recoveryPreview: {
                batch_id: null,
                preview_token: null,
                summary: null,
                items: [],
            },

            // Detail Modal
            showDetailModal: false,
            selectedKaryawan: null,
            detailData: { summary: null, breakdown: [] },
            detailTab: 'riwayat',
            riwayatList: [],
            logList: [],

            // Adjust Modal
            showAdjustKuotaModal: false,
            adjustForm: {
                tipe: 'ADD',
                id_jenis_cuti: '',
                id_buku_cuti: '',
                jumlah: 1,
                tanggal_berlaku: '',
                keterangan: '',
            },
            availableWallets: [],
            showConfirmAdjustModal: false,
            confirmData: { jenisCutiName: '', walletInfo: '' },

            // Cuti Bersama Tab
            currentYear: new Date().getFullYear(),
            cutiBersamaList: [],
            calendarData: [],
            showCutiBersamaModal: false,
            editingCB: null,
            cbForm: { nama: '', tanggal_mulai: '', tanggal_selesai: '' },

            // Apply
            applyForm: { id_cuti_bersama: '', id_jenis_cuti: '' },
            showPreviewModal: false,
            previewData: null,
        };
    },
    computed: {
        filterConfig() {
            return {
                searchable: true,
                searchPlaceholder: 'Cari nama atau kode karyawan...',
                searchCol: 6,
                showResultsCount: true,
                showActiveFilters: true,
                showActions: true,
                showReset: true,
                showClearAll: true,
                mobileTrigger: true,
                filters: [
                    {
                        key: 'divisi',
                        label: 'Divisi',
                        type: 'select',
                        placeholder: 'Semua Divisi',
                        col: 6,
                        options: [
                            { value: '', label: 'Semua Divisi' },
                            ...this.divisiOptions.map((d) => ({
                                value: d.ID_Divisi,
                                label: d.nama_divisi,
                            })),
                        ],
                    },
                    {
                        key: 'status_kuota',
                        label: 'Status Kuota',
                        type: 'select',
                        placeholder: 'Semua Status',
                        col: 6,
                        options: [
                            { value: '', label: 'Semua Status' },
                            { value: 'normal', label: 'Normal' },
                            { value: 'pending', label: 'Ada Pending' },
                            { value: 'hutang', label: 'Ada Hutang' },
                        ],
                    },
                ],
            };
        },
        canSubmitAdjust() {
            return (
                this.adjustForm.id_jenis_cuti && this.adjustForm.jumlah >= 1 && this.adjustForm.keterangan.length >= 10
            );
        },
        isAllSelected() {
            return (
                this.karyawanList.length > 0 &&
                this.karyawanList.every((k) => this.selectedForExport.includes(k.Kode_Karyawan_Encrypted))
            );
        },
        isSelected(kode) {
            return this.selectedForExport.includes(kode);
        },
        totalPages() {
            return Math.ceil(this.paginationKaryawan.total / this.paginationKaryawan.per_page) || 1;
        },
        visiblePages() {
            const total = this.totalPages;
            const current = this.paginationKaryawan.current_page;
            const pages = [];
            let start = Math.max(1, current - 2);
            let end = Math.min(total, current + 2);

            if (end - start < 4) {
                if (start === 1) end = Math.min(total, 5);
                else start = Math.max(1, total - 4);
            }

            for (let i = start; i <= end; i++) pages.push(i);
            return pages;
        },
    },
    mounted() {
        this.loadProcessDashboard();
        this.loadCutiDashboardWidgets();
        this.loadStats();
        this.loadKaryawan();
        this.loadOptions();
        this.loadCutiBersama();
    },
    methods: {
        applyRecoveryHint(payload) {
            this.activeTab = 'recovery';
            if (payload?.source_type) {
                this.recoveryForm.source_type = payload.source_type;
            }
            if (Array.isArray(payload?.months)) {
                this.recoveryForm.month = this.normalizeRecoveryMonths(payload.months)[0] || this.recoveryForm.month;
            }
            this.recoveryForm.due_dates = this.normalizeRecoveryDueDates(payload?.due_dates || payload?.due_date || []);
            this.recoveryForm.employee_scope = payload?.employee_scope || 'selected';
            const selectedCodes = this.normalizeRecoveryCodes(payload?.kode_karyawan || []);
            this.recoveryForm.kode_karyawan = this.recoveryForm.employee_scope === 'all' ? '' : selectedCodes[0] || '';
            this.recoveryMonthDraft = this.recoveryForm.month || new Date().toISOString().slice(0, 7);
            this.recoveryPreview = {
                batch_id: null,
                preview_token: null,
                summary: null,
                items: [],
            };
            this.closeDashboardDetail();
            this.$nextTick(() => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        },
        truncateText(text, maxLength) {
            if (text.length > maxLength) {
                return text.substring(0, maxLength) + '...';
            }
            return text;
        },
        formatDateString(date) {
            return new Date(date).toLocaleDateString('id-ID', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });
        },
        formatDate(date) {
            if (!date) return '-';
            return new Date(date).toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            });
        },
        formatDateTime(date) {
            if (!date) return '-';
            return new Date(date).toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
        handleTabChange(tabId) {
            this.activeTab = tabId;
            if (tabId === 'dashboard') this.refreshDashboard();
            if (tabId === 'recovery') {
                this.loadProcessBatches();
                this.loadRecoveryKaryawanOptions('');
            }
        },
        handleFilterUpdate(newFilters) {
            this.filtersKuota = { ...newFilters };
            // Reset selection when filters change
            // this.selectedForExport = [];
            this.isAllSelected = false;
            this.selectAllAllPages = false;

            this.loadKaryawan(1);
        },

        // Export Selection Methods
        toggleSelectAll() {
            // Toggle local selection
            if (this.isAllSelected) {
                // Uncheck all
                this.isAllSelected = false;
                this.selectAllAllPages = false;
                this.selectedForExport = [];
                this.selectedForMark = [];
            } else {
                // Check all visible
                this.isAllSelected = true;
                // If filters are active or total pages > 1, assume global selection intention?
                // For simplicity, we just enable global selection flag if user clicks header check
                this.selectAllAllPages = true;

                // Visual feedback: select all visible
                const visibleIds = this.karyawanList.map((k) => k.Kode_Karyawan_Encrypted);
                this.selectedForExport = [...visibleIds];

                const visibleIdsMark = this.karyawanList.map((k) => k.UserId_Absen);
                this.selectedForMark = [...visibleIdsMark];
            }
        },
        toggleSelectEmployee(kode, userid) {
            // If we were in "Select All Pages" mode and user manually toggles one,
            // we must disable global mode because it's now a custom selection.
            if (this.selectAllAllPages) {
                this.selectAllAllPages = false;
            }

            const index = this.selectedForExport.indexOf(kode);
            if (index > -1) {
                this.selectedForExport.splice(index, 1);
            } else {
                this.selectedForExport.push(kode);
            }

            const indexMarked = this.selectedForMark.indexOf(userid);
            if (indexMarked > -1) {
                this.selectedForMark.splice(indexMarked, 1);
            } else {
                this.selectedForMark.push(userid);
            }

            // console.log(this.selectedForExport);

            // Update header checkbox visual state
            this.isAllSelected =
                this.karyawanList.length > 0 &&
                this.karyawanList.every((k) => this.selectedForExport.includes(k.Kode_Karyawan_Encrypted));

            this.isAllSelected =
                this.karyawanList.length > 0 &&
                this.karyawanList.every((k) => this.selectedForMark.includes(k.UserId_Absen));
        },
        handlePageChange(page) {
            this.loadKaryawan(page);
        },
        async exportKuota() {
            // If not global select and no manual selection
            if (!this.selectAllAllPages && this.selectedForExport.length === 0) {
                ElMessage.warning('Pilih minimal 1 karyawan untuk di-export');
                return;
            }

            this.loading.export = true;
            try {
                // Prepare payload
                const payload = {};

                if (this.selectAllAllPages) {
                    // Global export using filters
                    payload.filters = {
                        divisi: this.filtersKuota.divisi,
                        status_kuota: this.filtersKuota.status_kuota,
                        search: this.filtersKuota.search, // Ensure 'search' is tracked in filtersKuota
                    };
                } else {
                    // Specific ID export
                    payload.kode_karyawan = this.selectedForExport;
                }

                const response = await axios.post('/api/v1/kuota-management/karyawan/export-kuota', payload, {
                    responseType: 'blob',
                });

                const url = window.URL.createObjectURL(new Blob([response.data]));
                const contentDisposition = response.headers['content-disposition'];
                let filename = `Kuota_Cuti_${new Date().toISOString().replace(/[:.]/g, '-')}.xlsx`;
                if (contentDisposition) {
                    const match = contentDisposition.match(/filename="?([^"]+)"?/);
                    if (match) filename = match[1];
                }
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', filename);
                document.body.appendChild(link);
                link.click();
                link.remove();
                ElMessage.success('Export berhasil!');

                // Reset selection
                this.selectedForExport = [];
                this.selectedForMark = [];
                this.isAllSelected = false;
                this.selectAllAllPages = false;
            } catch (e) {
                ElMessage.error('Gagal export: ' + (e.response?.data?.message || e.message));
            }
            this.loading.export = false;
        },
        async exportRiwayat() {
            if (!this.exportRiwayatForm.date_start || !this.exportRiwayatForm.date_end) {
                ElMessage.error('Harap pilih tanggal mulai dan tanggal selesai');
                return;
            }
            this.loading.export = true;
            try {
                const params = new URLSearchParams({
                    kode_karyawan: this.selectedKaryawan.Kode_Karyawan_Encrypted,
                    ...(this.exportRiwayatForm.date_start && {
                        date_start: this.exportRiwayatForm.date_start,
                    }),
                    ...(this.exportRiwayatForm.date_end && {
                        date_end: this.exportRiwayatForm.date_end,
                    }),
                });

                const response = await axios.get(`/api/v1/kuota-management/karyawan/export-riwayat?${params}`, {
                    responseType: 'blob',
                });

                const url = window.URL.createObjectURL(new Blob([response.data]));
                const contentDisposition = response.headers['content-disposition'];
                let filename = `Riwayat_Cuti_${this.selectedKaryawan.Nama_Karyawan?.replace(/\s+/g, '_') || 'Export'}_${new Date().toISOString().replace(/[:.]/g, '-')}.xlsx`;
                if (contentDisposition) {
                    const match = contentDisposition.match(/filename="?([^"]+)"?/);
                    if (match) filename = match[1];
                }
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', filename);
                document.body.appendChild(link);
                link.click();
                link.remove();
                ElMessage.success('Export berhasil!');
                // Reset date filter after successful export
                this.exportRiwayatForm = { date_start: '', date_end: '' };
            } catch (e) {
                ElMessage.error('Gagal export: ' + (e.response?.data?.message || e.message));
            }
            this.loading.export = false;
        },
        async exportLog() {
            if (!this.exportLogForm.date_start || !this.exportLogForm.date_end) {
                ElMessage.error('Harap pilih tanggal mulai dan tanggal selesai');
                return;
            }
            this.loading.export = true;
            try {
                const params = new URLSearchParams({
                    kode_karyawan: this.selectedKaryawan.Kode_Karyawan_Encrypted,
                    ...(this.exportLogForm.date_start && {
                        date_start: this.exportLogForm.date_start,
                    }),
                    ...(this.exportLogForm.date_end && {
                        date_end: this.exportLogForm.date_end,
                    }),
                });

                const response = await axios.get(`/api/v1/kuota-management/karyawan/export-log?${params}`, {
                    responseType: 'blob',
                });

                const url = window.URL.createObjectURL(new Blob([response.data]));
                const contentDisposition = response.headers['content-disposition'];
                let filename = `Log_Mutasi_${this.selectedKaryawan.Nama_Karyawan?.replace(/\s+/g, '_') || 'Export'}_${new Date().toISOString().replace(/[:.]/g, '-')}.xlsx`;
                if (contentDisposition) {
                    const match = contentDisposition.match(/filename="?([^"]+)"?/);
                    if (match) filename = match[1];
                }
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', filename);
                document.body.appendChild(link);
                link.click();
                link.remove();
                ElMessage.success('Export berhasil!');
                // Reset date filter after successful export
                this.exportLogForm = { date_start: '', date_end: '' };
            } catch (e) {
                ElMessage.error('Gagal export: ' + (e.response?.data?.message || e.message));
            }
            this.loading.export = false;
        },
        getBadgeType(status) {
            const map = {
                approved: 'success',
                pending: 'warning',
                rejected: 'danger',
                cancelled: 'secondary',
            };
            return map[status] || 'secondary';
        },
        getInitials(name) {
            if (!name) return '?';
            return name
                .split(' ')
                .map((n) => n[0])
                .join('')
                .substring(0, 2)
                .toUpperCase();
        },
        getMonthName(m) {
            return new Date(2000, m - 1, 1).toLocaleDateString('id-ID', {
                month: 'short',
            });
        },
        formatHumanMonth(value) {
            if (!value) return '-';
            const parsed = new Date(value);
            if (!Number.isNaN(parsed.getTime())) {
                return parsed.toLocaleDateString('id-ID', {
                    month: 'long',
                    year: 'numeric',
                });
            }
            return this.formatPeriodKey(value);
        },
        formatHumanDate(value) {
            if (!value) return '-';
            const parsed = new Date(value);
            if (!Number.isNaN(parsed.getTime())) {
                return parsed.toLocaleDateString('id-ID', {
                    day: '2-digit',
                    month: 'long',
                    year: 'numeric',
                });
            }
            return value;
        },
        formatPeriodKey(value) {
            const raw = String(value || '').trim();
            if (!raw) return '-';
            const match = raw.match(/^(\d{4})-(\d{2})$/);
            if (match) {
                const year = Number(match[1]);
                const month = Number(match[2]);
                if (month >= 1 && month <= 12) {
                    return new Date(year, month - 1, 1).toLocaleDateString('id-ID', {
                        month: 'long',
                        year: 'numeric',
                    });
                }
            }
            return raw;
        },
        getCutiBersamaDates(month) {
            const dates = [];
            if (!this.calendarData) return dates;
            this.calendarData.forEach((cb) => {
                if (cb.dates) {
                    cb.dates.forEach((d) => {
                        if (new Date(d).getMonth() + 1 === month) dates.push(d);
                    });
                }
            });
            return dates;
        },
        calculateDays(start, end) {
            if (!start || !end) return 0;
            const diffTime = new Date(end) - new Date(start);
            return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        },
        formatNumber(value) {
            const num = parseFloat(value) || 0;
            // If it's a whole number, show without decimal
            if (num === Math.floor(num)) return Math.floor(num);
            // Otherwise show with 1 decimal
            return Number(num.toFixed(1));
        },
        getProcessStatusClass(status) {
            const value = String(status || '').toUpperCase();
            if (['SUCCESS', 'COMPLETED'].includes(value)) return 'bg-success';
            if (['FAILED'].includes(value)) return 'bg-danger';
            if (
                ['COMPLETED_WITH_ERRORS', 'SKIPPED_EXISTING_KEY', 'SKIPPED_NOT_DUE', 'SKIPPED_NOT_ELIGIBLE'].includes(
                    value,
                )
            )
                return 'bg-warning';
            if (['DRY_RUN', 'RUNNING'].includes(value)) return 'bg-info';
            if (['SKIPPED_BEFORE_CUTOFF'].includes(value)) return 'bg-secondary';
            return 'bg-secondary';
        },
        processSourceLabel(sourceType) {
            const value = String(sourceType || '').toUpperCase();
            const map = {
                MONTHLY_ACCRUAL: 'Akrual Bulanan',
                LOYALTY_ACCRUAL: 'Loyalty',
                MANUAL_RECOVERY: 'Recovery Manual',
                MANUAL_ADJUSTMENT: 'Penyesuaian Manual',
            };
            return map[value] || sourceType || '-';
        },
        processStatusLabel(status) {
            const value = String(status || '').toUpperCase();
            const map = {
                SUCCESS: 'Sukses',
                COMPLETED: 'Selesai',
                FAILED: 'Gagal',
                RUNNING: 'Berjalan',
                DRY_RUN: 'Dry Run',
                COMPLETED_WITH_ERRORS: 'Selesai dengan Error',
                SKIPPED_EXISTING_KEY: 'Skip Duplikat',
                SKIPPED_NOT_DUE: 'Skip Belum Due',
                SKIPPED_NOT_ELIGIBLE: 'Skip Tidak Eligible',
                SKIPPED_BEFORE_CUTOFF: 'Skip Sebelum Cutoff',
            };
            return map[value] || status || '-';
        },
        processPeriodLabel(batch) {
            const start = this.formatDate(batch?.Period_Start);
            const end = this.formatDate(batch?.Period_End);
            if (start && end) return start === end ? start : `${start} - ${end}`;
            return start || end || '-';
        },
        processCreatedLabel(value) {
            return this.formatDateTime(value || null);
        },
        isAdminRecoveryBatch(batch) {
            return String(batch?.Batch_Source_Type || batch?.Source_Type || '').toUpperCase() === 'MANUAL_RECOVERY';
        },
        isSystemSchedulerBatch(batch) {
            const source = String(batch?.Batch_Source_Type || batch?.Source_Type || '').toUpperCase();
            const triggered = String(batch?.Triggered_From || '').toUpperCase();
            const requestedBy = String(batch?.Requested_By || '').toUpperCase();
            return (
                source !== 'MANUAL_RECOVERY' &&
                triggered === 'COMMAND' &&
                ['SYSTEM_SCHEDULER', 'SYSTEM_LOYALTY'].includes(requestedBy)
            );
        },
        batchOriginLabel(batch) {
            if (this.isAdminRecoveryBatch(batch)) return 'Admin Recover';
            if (this.isSystemSchedulerBatch(batch)) return 'Scheduler System';
            if (batch?.Triggered_From || batch?.Requested_By) {
                return `${batch.Triggered_From || 'UNKNOWN'}${batch.Requested_By ? ` / ${batch.Requested_By}` : ''}`;
            }
            return 'Unknown';
        },
        batchOriginClass(batch) {
            if (this.isAdminRecoveryBatch(batch)) return 'bg-info';
            if (this.isSystemSchedulerBatch(batch)) return 'bg-success';
            return 'bg-secondary';
        },
        manualMatchLabel(matchType) {
            const value = String(matchType || '').toUpperCase();
            if (value === 'DUE_DATE') return 'Due date';
            if (value === 'RUN_DATE') return 'Tanggal recover';
            if (value === 'PERIOD') return 'Periode batch';
            return 'Terkait';
        },
        parseCsvText(text) {
            return String(text || '')
                .split(/[,\s]+/)
                .map((item) => item.trim())
                .filter(Boolean);
        },
        normalizeRecoveryMonths(value) {
            const months = Array.isArray(value)
                ? value.map((item) => String(item || '').trim()).filter(Boolean)
                : this.parseCsvText(value);
            return months.slice(0, 1);
        },
        normalizeRecoveryCodes(value) {
            return Array.isArray(value)
                ? value
                      .map((item) =>
                          String(item || '')
                              .trim()
                              .toUpperCase(),
                      )
                      .filter(Boolean)
                : this.parseCsvText(value)
                      .map((item) =>
                          String(item || '')
                              .trim()
                              .toUpperCase(),
                      )
                      .filter(Boolean);
        },
        normalizeRecoveryDueDates(value) {
            const dates = Array.isArray(value) ? value : value ? [value] : [];
            return dates.map((item) => String(item || '').trim()).filter(Boolean);
        },
        buildRecoveryPayload() {
            const kodeKaryawan =
                this.recoveryForm.employee_scope === 'all'
                    ? []
                    : this.recoveryForm.kode_karyawan
                      ? [this.recoveryForm.kode_karyawan]
                      : [];
            return {
                source_type: this.recoveryForm.source_type,
                months: this.recoveryForm.month ? [this.recoveryForm.month] : [],
                due_dates: this.normalizeRecoveryDueDates(this.recoveryForm.due_dates),
                due_date: this.normalizeRecoveryDueDates(this.recoveryForm.due_dates)[0] || null,
                employee_scope: this.recoveryForm.employee_scope,
                kode_karyawan: kodeKaryawan,
                keterangan: this.recoveryForm.keterangan,
                preview_token: this.recoveryPreview.preview_token || '',
            };
        },
        handleRecoveryMonthPick(value) {
            if (!value) return;
            this.recoveryForm.month = String(value).trim();
            this.recoveryMonthDraft = this.recoveryForm.month;
        },
        handleRecoveryEmployeeScopeChange(scope) {
            if (scope === 'all') {
                this.recoveryForm.kode_karyawan = '';
                return;
            }

            if (!this.recoveryKaryawanLoaded && !this.recoveryKaryawanLoading) {
                this.loadRecoveryKaryawanOptions('');
            }
        },
        async loadRecoveryKaryawanOptions(query = '') {
            this.recoveryKaryawanLoading = true;
            try {
                const response = await axios.get('/api/v1/kuota-management/options/karyawan', {
                    params: {
                        search: query,
                        limit: 20,
                    },
                });

                if (response.data?.success) {
                    const mappedOptions = (response.data.result || []).map((item) => ({
                        value: item.kode_karyawan,
                        label: item.nama_karyawan,
                    }));
                    if (!query) {
                        this.recoveryKaryawanBaseOptions = mappedOptions;
                        this.recoveryKaryawanOptions = mappedOptions;
                    } else {
                        this.recoveryKaryawanOptions = mappedOptions;
                    }
                    this.recoveryKaryawanLoaded = true;
                }
            } catch (error) {
                console.error('Load recovery karyawan options error:', error);
                this.recoveryKaryawanOptions = [];
                this.recoveryKaryawanBaseOptions = [];
                this.recoveryKaryawanLoaded = false;
            }
            this.recoveryKaryawanLoading = false;
        },
        handleRecoveryKaryawanSearch: debounce(function (query) {
            if (this.recoveryForm.employee_scope === 'all') return;

            const normalizedQuery = String(query || '').trim();
            if (!normalizedQuery && this.recoveryKaryawanLoaded) {
                return;
            }

            if (this.recoveryKaryawanLoading && normalizedQuery === this.recoveryKaryawanLastQuery) {
                return;
            }

            this.recoveryKaryawanLastQuery = normalizedQuery;
            this.loadRecoveryKaryawanOptions(query);
        }, 300),
        handleRecoveryKaryawanVisible(visible) {
            if (this.recoveryForm.employee_scope === 'all') {
                return;
            }

            if (visible && !this.recoveryKaryawanLoaded && !this.recoveryKaryawanLoading) {
                this.loadRecoveryKaryawanOptions('');
                return;
            }

            if (!visible) {
                this.recoveryKaryawanLastQuery = null;
                this.recoveryKaryawanOptions = [...this.recoveryKaryawanBaseOptions];
            }
        },
        emptyWidget() {
            return { metric: 0, items: [], meta: {}, error: null };
        },
        refreshDashboard() {
            this.loadProcessDashboard();
            this.loadCutiDashboardWidgets();
        },
        async loadCutiDashboardWidgets() {
            this.loading.dashboardWidgets = true;
            await Promise.all([
                this.loadHeatmapWidget(),
                this.loadTrendWidget('monthlyTrend', '/api/v1/kuota-management/dashboard/widget/tren-akrual-bulanan'),
                this.loadMonthlyAnniversaryWidget(),
                this.loadTrendWidget('loyaltyTrend', '/api/v1/kuota-management/dashboard/widget/tren-akrual-loyalty'),
                this.loadAnniversaryWidget(),
            ]);
            this.loading.dashboardWidgets = false;
        },
        async loadHeatmapWidget() {
            try {
                const res = await axios.get('/api/v1/kuota-management/dashboard/widget/heatmap-akrual', {
                    params: { source: this.heatmapSource, days: 30 },
                });
                this.dashboardWidgets.heatmap = res.data?.result || this.emptyWidget();
            } catch (e) {
                this.dashboardWidgets.heatmap = {
                    ...this.emptyWidget(),
                    error: { message: e.response?.data?.message || 'Gagal memuat heatmap akrual' },
                };
            }
        },
        async loadTrendWidget(key, url) {
            try {
                const res = await axios.get(url, { params: { months: 6 } });
                this.dashboardWidgets[key] = res.data?.result || this.emptyWidget();
            } catch (e) {
                this.dashboardWidgets[key] = {
                    ...this.emptyWidget(),
                    error: { message: e.response?.data?.message || 'Gagal memuat trend akrual' },
                };
            }
        },
        async loadMonthlyAnniversaryWidget() {
            try {
                const res = await axios.get('/api/v1/kuota-management/dashboard/widget/monthly-anniversary-cuti');
                this.dashboardWidgets.monthlyAnniversary = res.data?.result || this.emptyWidget();
            } catch (e) {
                this.dashboardWidgets.monthlyAnniversary = {
                    ...this.emptyWidget(),
                    error: { message: e.response?.data?.message || 'Gagal memuat anniversary monthly cuti' },
                };
            }
        },
        async loadAnniversaryWidget() {
            try {
                const res = await axios.get('/api/v1/kuota-management/dashboard/widget/anniversary-bulan-ini');
                this.dashboardWidgets.anniversary = res.data?.result || this.emptyWidget();
            } catch (e) {
                this.dashboardWidgets.anniversary = {
                    ...this.emptyWidget(),
                    error: { message: e.response?.data?.message || 'Gagal memuat anniversary' },
                };
            }
        },
        setHeatmapSource(source) {
            if (this.heatmapSource === source) return;
            this.heatmapSource = source;
            this.loadHeatmapWidget();
        },
        async openHeatmapDetail(day) {
            if (!day?.date) return;
            this.dashboardDetail = {
                show: true,
                type: 'heatmap',
                title: `Detail Heatmap ${this.formatDate(day.date)}`,
                data: this.emptyWidget(),
                batchDetail: {
                    batch: null,
                    items: [],
                    pagination: {},
                },
            };
            this.loading.dashboardDetail = true;
            try {
                const res = await axios.get('/api/v1/kuota-management/dashboard/widget/heatmap-akrual/detail', {
                    params: { date: day.date, source: this.heatmapSource },
                });
                this.dashboardDetail.data = res.data?.result || this.emptyWidget();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat detail heatmap');
            }
            this.loading.dashboardDetail = false;
        },
        async openDashboardBatchDetail(batch) {
            if (!batch?.Id_Batch) return;
            this.dashboardDetail.batchDetail = {
                batch,
                items: [],
                pagination: {},
            };
            this.loading.dashboardBatchDetail = true;
            try {
                const res = await axios.get(`/api/v1/kuota-management/process/batches/${batch.Id_Batch}/details`, {
                    params: { per_page: 100 },
                });
                this.dashboardDetail.batchDetail.items = res.data?.result || [];
                this.dashboardDetail.batchDetail.pagination = res.data?.pagination || {};
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat detail batch');
            }
            this.loading.dashboardBatchDetail = false;
        },
        clearDashboardBatchDetail() {
            this.dashboardDetail.batchDetail = {
                batch: null,
                items: [],
                pagination: {},
            };
            this.loading.dashboardBatchDetail = false;
        },
        async openTrendDetail(bar, sourceType) {
            if (!bar?.period) return;
            this.dashboardDetail = {
                show: true,
                type: 'trend',
                title: `Detail ${sourceType === 'LOYALTY_ACCRUAL' ? 'Loyalty' : 'Akrual Bulanan'} ${bar.period}`,
                data: this.emptyWidget(),
                batchDetail: {
                    batch: null,
                    items: [],
                    pagination: {},
                },
            };
            this.loading.dashboardDetail = true;
            try {
                const res = await axios.get('/api/v1/kuota-management/dashboard/widget/tren-akrual/detail', {
                    params: { period_key: bar.period, source_type: sourceType },
                });
                this.dashboardDetail.data = res.data?.result || this.emptyWidget();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat detail trend');
            }
            this.loading.dashboardDetail = false;
        },
        async openAnniversaryDetail(item) {
            if (!item?.kode_karyawan) return;
            this.dashboardDetail = {
                show: true,
                type: 'anniversary',
                title: `Detail Anniversary ${item.nama || item.kode_karyawan}`,
                data: this.emptyWidget(),
                batchDetail: {
                    batch: null,
                    items: [],
                    pagination: {},
                },
            };
            this.loading.dashboardDetail = true;
            try {
                const res = await axios.get('/api/v1/kuota-management/dashboard/widget/anniversary/detail', {
                    params: {
                        kode_karyawan: item.kode_karyawan,
                        year: this.dashboardWidgets.anniversary.meta?.year,
                    },
                });
                const result = res.data?.result || this.emptyWidget();
                if (item.status === 'missed') {
                    result.meta = {
                        ...(result.meta || {}),
                        recovery_hint: {
                            source_type: 'LOYALTY_ACCRUAL',
                            months: [this.dashboardWidgets.anniversary.meta?.period_key].filter(Boolean),
                            kode_karyawan: [item.kode_karyawan],
                        },
                    };
                }
                this.dashboardDetail.data = result;
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat detail anniversary');
            }
            this.loading.dashboardDetail = false;
        },
        closeDashboardDetail() {
            this.dashboardDetail.show = false;
            this.loading.dashboardDetail = false;
            this.clearDashboardBatchDetail();
        },
        formatDayNumber(date) {
            if (!date) return '-';
            return new Date(date).getDate();
        },
        heatmapDayBadge(day) {
            const due = Number(day?.due_count || 0);
            if (due === 0) return 'no due';
            const done = Number(day?.done_count || 0);
            const problem = Number(day?.problem_count || 0);
            // Format: "done/due" — admin langsung lihat progres
            const base = `${done}/${due}`;
            if (day?.scheduler_missing) return `${base} ⚠`;
            if (problem > 0) return base;
            return base; // hijau: semua done
        },
        heatmapDayTooltip(day) {
            const parts = [this.formatDate(day?.date), day?.status_label || '-'];
            if (day?.scheduler_missing) {
                parts.push('⚠ Scheduler tidak jalan hari ini — lapor ke admin IT');
            }
            return parts.join('\n');
        },
        // ---------- Helper untuk modal heatmap detail (shape baru) ----------
        heatmapSchedulerStatusOk(meta) {
            if (!meta?.scheduler_ran) return true;
            const m = meta.scheduler_ran;
            // Bila widget filter ke source tertentu, cukup cek itu saja
            const filter = meta?.source_filter;
            if (filter === 'MONTHLY_ACCRUAL') return !!m.monthly;
            if (filter === 'LOYALTY_ACCRUAL') return !!m.loyalty;
            // ALL: cukup salah satu jalan asalkan tidak ada yang due tapi missing.
            // Untuk simplicity, OK kalau setidaknya satu jalan ATAU tidak ada bermasalah.
            return (m.monthly || m.loyalty) && (meta?.problem_count || 0) === 0;
        },
        heatmapSchedulerStatusLabel(meta) {
            if (!meta?.scheduler_ran) return 'Scheduler';
            return this.heatmapSchedulerStatusOk(meta) ? 'Scheduler OK' : 'Cek Scheduler';
        },
        heatmapSchedulerMissingMessage(meta) {
            if (!meta?.scheduler_ran) return null;
            const m = meta.scheduler_ran;
            const missing = [];
            // Hanya kasih warning kalau ADA karyawan due untuk source itu tapi scheduler tidak jalan
            const problemBySource = {};
            (meta?.grouped?.problem || []).forEach((it) => {
                problemBySource[it.source_type] = (problemBySource[it.source_type] || 0) + 1;
            });
            if (!m.monthly && problemBySource['MONTHLY_ACCRUAL'] > 0) {
                missing.push(`Akrual Bulanan (${problemBySource['MONTHLY_ACCRUAL']} karyawan due tidak ter-proses)`);
            }
            if (!m.loyalty && problemBySource['LOYALTY_ACCRUAL'] > 0) {
                missing.push(`Loyalty (${problemBySource['LOYALTY_ACCRUAL']} karyawan anniv tidak ter-proses)`);
            }
            return missing.length ? missing.join(' · ') : null;
        },
        recoveryHintSourceLabel(sourceType) {
            if (sourceType === 'MONTHLY_ACCRUAL') return 'Akrual Bulanan';
            if (sourceType === 'LOYALTY_ACCRUAL') return 'Loyalty';
            return sourceType || '-';
        },
        heatmapItemStatusClass(item) {
            const map = {
                done: 'bg-success',
                failed: 'bg-danger',
                skipped: 'bg-warning text-dark',
                not_processed: 'bg-secondary',
                blocked: 'bg-dark',
            };
            return map[item?.status] || 'bg-light text-dark';
        },
        heatmapItemStatusBadge(item) {
            const map = {
                done: 'Sudah',
                failed: 'Gagal',
                skipped: 'Skip',
                not_processed: 'Tidak Proses',
                blocked: 'Data Tidak Lengkap',
            };
            return map[item?.status] || item?.status || '-';
        },
        buildItemRecoveryHint(item) {
            return {
                source_type: item.source_type,
                months: [item.period_key],
                due_date: item.due_date,
                kode_karyawan: [item.kode_karyawan],
                employee_scope: 'selected',
            };
        },
        anniversaryStatusLabel(item) {
            const map = {
                done: 'Sudah Dapat',
                missed: 'Terlewat',
                today: 'Hari Ini',
                pending: 'Menunggu',
                blocked: 'Tidak Lengkap',
                ineligible: 'Bukan PKWTT',
            };
            return map[item?.status] || 'Belum';
        },
        anniversaryStatusClass(item) {
            const map = {
                done: 'status-done',
                missed: 'status-missed',
                today: 'status-today',
                pending: 'status-pending',
                blocked: 'status-blocked',
                ineligible: 'status-ineligible',
            };
            return map[item?.status] || 'status-pending';
        },
        anniversaryStatusIcon(item) {
            const map = {
                done: 'bi-check-circle-fill',
                missed: 'bi-exclamation-circle-fill',
                today: 'bi-calendar-event-fill',
                pending: 'bi-hourglass-split',
                blocked: 'bi-lock-fill',
                ineligible: 'bi-slash-circle-fill',
            };
            return map[item?.status] || 'bi-hourglass-split';
        },
        // ---------- Trend helpers (shape baru: expected/realized/gap/blocked) ----------
        coverageClass(percent) {
            if (percent === null || percent === undefined) return 'text-muted';
            const p = Number(percent);
            if (p >= 95) return 'text-success';
            if (p >= 75) return 'text-warning';
            return 'text-danger';
        },
        trendBarTooltip(bar) {
            const lines = [
                `${bar.label || bar.period}`,
                `Eligible: ${bar.expected || 0}`,
                `Sudah dapat: ${bar.realized || 0}`,
                `Belum (gap): ${bar.gap || 0}`,
            ];
            if (bar.blocked) lines.push(`Data tidak lengkap: ${bar.blocked}`);
            if (bar.coverage_percent !== null) lines.push(`Coverage: ${bar.coverage_percent}%`);
            return lines.join('\n');
        },
        getStackHeight(bar, series) {
            const max = Math.max(...(series || []).map((b) => Number(b.expected || 0) + Number(b.blocked || 0)), 1);
            const val = Number(bar?.expected || 0) + Number(bar?.blocked || 0);
            return `${Math.max(8, Math.round((val / max) * 100))}%`;
        },
        sumField(items, field) {
            return (items || []).reduce((total, item) => total + Number(item?.[field] || 0), 0);
        },

        // API Calls
        async loadProcessDashboard() {
            this.loading.processDashboard = true;
            try {
                const res = await axios.get('/api/v1/kuota-management/process/dashboard');
                if (res.data?.success) {
                    this.processDashboard = {
                        installed: res.data.result?.installed !== false,
                        message: res.data.result?.message || '',
                        summary: res.data.result?.summary || {},
                        latest_batches: res.data.result?.latest_batches || [],
                        failed_details: res.data.result?.failed_details || [],
                    };
                    const dashboardTab = this.adminTabs.find((tab) => tab.id === 'dashboard');
                    if (dashboardTab) dashboardTab.count = this.processDashboard.summary.failed_batch || 0;
                }
            } catch (e) {
                console.error('Load process dashboard error:', e);
            }
            this.loading.processDashboard = false;
        },
        async loadProcessBatches() {
            this.loading.processBatches = true;
            try {
                const res = await axios.get('/api/v1/kuota-management/process/batches', {
                    params: { per_page: 10 },
                });
                if (res.data?.success) {
                    this.processBatches = res.data.result || [];
                    const recoveryTab = this.adminTabs.find((tab) => tab.id === 'recovery');
                    if (recoveryTab) recoveryTab.count = res.data.pagination?.total || 0;
                }
            } catch (e) {
                console.error('Load process batches error:', e);
                this.processBatches = [];
            }
            this.loading.processBatches = false;
        },
        async previewRecovery() {
            const payload = this.buildRecoveryPayload();
            if (payload.months.length === 0 || payload.kode_karyawan.length === 0) {
                ElMessage.warning('Isi minimal 1 bulan dan 1 kode karyawan');
                return;
            }

            this.loading.recovery = true;
            try {
                const res = await axios.post('/api/v1/kuota-management/process/recovery/preview', payload);
                if (res.data?.success) {
                    this.recoveryPreview = res.data.result || {
                        batch_id: null,
                        preview_token: null,
                        summary: null,
                        items: [],
                    };
                    ElMessage.success('Preview recovery berhasil dibuat');
                    this.loadProcessBatches();
                    this.refreshDashboard();
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal preview recovery');
            }
            this.loading.recovery = false;
        },
        async executeRecovery() {
            const payload = this.buildRecoveryPayload();
            if (payload.months.length === 0 || payload.kode_karyawan.length === 0) {
                ElMessage.warning('Isi minimal 1 bulan dan 1 kode karyawan');
                return;
            }

            const ok = confirm('Execute recovery akan mengubah saldo untuk item yang eligible. Lanjutkan?');
            if (!ok) return;

            this.loading.recovery = true;
            try {
                const res = await axios.post('/api/v1/kuota-management/process/recovery/execute', payload);
                if (res.data?.success) {
                    this.recoveryPreview = res.data.result || {
                        batch_id: null,
                        preview_token: null,
                        summary: null,
                        items: [],
                    };
                    ElMessage.success('Recovery berhasil dieksekusi');
                    this.loadStats();
                    this.loadKaryawan();
                    this.loadProcessBatches();
                    this.refreshDashboard();
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal execute recovery');
            }
            this.loading.recovery = false;
        },
        async loadStats() {
            try {
                const res = await axios.get('/api/v1/kuota-management/stats');
                if (res.data?.success && res.data?.result) {
                    const s = res.data.result;
                    this.quickStatField[0].value = s.total_karyawan || 0;
                    this.quickStatField[1].value = s.karyawan_normal || 0;
                    this.quickStatField[2].value = s.total_pending || 0;
                    this.quickStatField[3].value = s.karyawan_hutang || 0;
                    this.quickStatField[4].value = s.avg_sisa_cuti || 0;
                }
            } catch (e) {
                console.error('Load stats error:', e);
            }
        },
        async loadOptions() {
            try {
                const [jc, div] = await Promise.all([
                    axios.get('/api/v1/kuota-management/options/jenis-cuti'),
                    axios.get('/api/v1/kuota-management/options/divisi'),
                ]);
                this.jenisCutiOptions = jc.data?.result || [];
                this.divisiOptions = div.data?.result || [];
            } catch (e) {
                console.error('Load options error:', e);
            }
        },
        handleSearchKuota: debounce(function () {
            // Reset selection on search
            // this.selectedForExport = [];
            this.isAllSelected = false;
            this.selectAllAllPages = false;

            this.loadKaryawan();
        }, 400),
        async loadKaryawan(page = 1) {
            this.loading.karyawan = true;
            try {
                // this.KaryawanList = [];
                const res = await axios.get('/api/v1/kuota-management/karyawan', {
                    params: {
                        page,
                        per_page: 10,
                        search: this.filtersKuota.search,
                        divisi: this.filtersKuota.divisi,
                        status_kuota: this.filtersKuota.status_kuota,
                    },
                });
                if (res.data?.success) {
                    this.karyawanList = res.data.result || [];
                    const kuotaTab = this.adminTabs.find((tab) => tab.id === 'kuota');
                    if (kuotaTab) kuotaTab.count = res.data.pagination.total || 0;
                    this.paginationKaryawan = res.data.pagination || {
                        current_page: 1,
                        per_page: 10,
                        total: 0,
                    };

                    // Check if all visible are selected (for manual selection persistence across pages)
                    const visibleIds = this.karyawanList.map((k) => k.Kode_Karyawan_Encrypted);
                    this.isAllSelected =
                        visibleIds.length > 0 && visibleIds.every((id) => this.selectedForExport.includes(id));

                    const visibleIdsMark = this.karyawanList.map((k) => k.UserId_Absen);
                    this.isAllSelected =
                        visibleIdsMark.length > 0 && visibleIdsMark.every((id) => this.selectedForMark.includes(id));
                }
            } catch (e) {
                console.error('Load karyawan error:', e);
                this.karyawanList = [];
            }
            this.loading.karyawan = false;
        },

        async openDetailModal(item) {
            this.selectedKaryawan = item;
            this.showDetailModal = true;
            this.detailTab = 'riwayat';
            this.detailData = { summary: null, breakdown: [] };
            this.riwayatList = [];
            this.logList = [];

            this.loading.detail = true;
            try {
                const res = await axios.get('/api/v1/kuota-management/karyawan/detail', {
                    params: { kode_karyawan: item.Kode_Karyawan_Encrypted },
                });
                if (res.data?.success) {
                    this.detailData = res.data.result || {
                        summary: null,
                        breakdown: [],
                    };
                }
                this.loadRiwayat(item.Kode_Karyawan_Encrypted);
            } catch (e) {
                console.error('Load detail error:', e);
            }
            this.loading.detail = false;
        },
        async loadRiwayat(kode) {
            this.loading.riwayat = true;
            try {
                const res = await axios.get('/api/v1/kuota-management/karyawan/riwayat', {
                    params: { kode_karyawan: kode },
                });
                if (res.data?.success) this.riwayatList = res.data.result || [];
            } catch (e) {
                console.error('Load riwayat error:', e);
            }
            this.loading.riwayat = false;
        },
        async loadLog(kode) {
            this.loading.log = true;
            try {
                const res = await axios.get('/api/v1/kuota-management/karyawan/logs', {
                    params: { kode_karyawan: kode },
                });
                if (res.data?.success) this.logList = res.data.result || [];
            } catch (e) {
                console.error('Load log error:', e);
            }
            this.loading.log = false;
        },

        showAdjustModal(tipe) {
            this.adjustForm = {
                tipe,
                id_jenis_cuti: '',
                id_buku_cuti: '',
                jumlah: 1,
                tanggal_berlaku: new Date().toISOString().split('T')[0],
                keterangan: '',
            };
            this.availableWallets = [];
            this.showAdjustKuotaModal = true;
        },
        async loadAvailableWallets() {
            if (!this.adjustForm.id_jenis_cuti || !this.selectedKaryawan) {
                this.availableWallets = [];
                return;
            }
            this.loading.wallets = true;
            this.adjustForm.id_buku_cuti = ''; // Reset wallet selection
            try {
                const res = await axios.get('/api/v1/kuota-management/karyawan/wallets', {
                    params: {
                        kode_karyawan: this.selectedKaryawan.Kode_Karyawan_Encrypted,
                        id_jenis_cuti: this.adjustForm.id_jenis_cuti,
                    },
                });
                if (res.data?.success) {
                    this.availableWallets = res.data.result || [];
                    // Automtaically select first wallet if available
                    if (this.availableWallets.length > 0) {
                        this.adjustForm.id_buku_cuti = this.availableWallets[0].id;
                    }
                }
            } catch (e) {
                console.error('Load wallets error:', e);
                this.availableWallets = [];
            }
            this.loading.wallets = false;
        },
        showConfirmModal() {
            this.confirmData.jenisCutiName =
                this.jenisCutiOptions.find((j) => j.Id_Jenis_Cuti == this.adjustForm.id_jenis_cuti)?.Nama_Jenis_Cuti ||
                'Unknown';
            this.confirmData.walletInfo = this.adjustForm.id_buku_cuti
                ? this.availableWallets.find((w) => w.id == this.adjustForm.id_buku_cuti)?.label || 'Dompet Terpilih'
                : `Dompet Baru (Periode ${this.currentYear})`;
            this.showConfirmAdjustModal = true;
        },
        async submitAdjust() {
            this.loading.adjust = true;
            try {
                const res = await axios.post('/api/v1/kuota-management/karyawan/adjust', {
                    kode_karyawan: this.selectedKaryawan.Kode_Karyawan_Encrypted,
                    ...this.adjustForm,
                });
                if (res.data?.success) {
                    ElMessage.success('Kuota berhasil di-adjust');
                    this.showConfirmAdjustModal = false;
                    this.showAdjustKuotaModal = false;
                    this.openDetailModal(this.selectedKaryawan);
                    this.loadStats();
                    this.loadKaryawan();
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal adjust kuota');
            }
            this.loading.adjust = false;
        },

        // Cuti Bersama
        changeYear(delta) {
            this.currentYear += delta;
            this.loadCutiBersama();
        },
        async loadCutiBersama() {
            this.loading.cutiBersama = true;
            try {
                const res = await axios.get('/api/v1/kuota-management/cuti-bersama', {
                    params: { year: this.currentYear },
                });
                if (res.data?.success && res.data?.result) {
                    this.cutiBersamaList = res.data.result.list || [];
                    this.calendarData = res.data.result.calendar || [];
                }
            } catch (e) {
                console.error('Load cuti bersama error:', e);
                this.cutiBersamaList = [];
                this.calendarData = [];
            }
            this.loading.cutiBersama = false;
        },
        openCBModal() {
            this.editingCB = null;
            this.cbForm = { nama: '', tanggal_mulai: '', tanggal_selesai: '' };
            this.showCutiBersamaModal = true;
        },
        editCutiBersama(item) {
            this.editingCB = item;
            this.cbForm = {
                nama: item.Nama_Cuti_Bersama,
                tanggal_mulai: item.Tanggal_Mulai,
                tanggal_selesai: item.Tanggal_Selesai,
            };
            this.showCutiBersamaModal = true;
        },
        closeCBModal() {
            this.showCutiBersamaModal = false;
            this.editingCB = null;
            this.cbForm = { nama: '', tanggal_mulai: '', tanggal_selesai: '' };
        },
        async submitCutiBersama() {
            try {
                if (this.editingCB) {
                    await axios.put(
                        `/api/v1/kuota-management/cuti-bersama/${this.editingCB.Id_Cuti_Bersama}`,
                        this.cbForm,
                    );
                    ElMessage.success('Cuti bersama berhasil diperbarui');
                } else {
                    await axios.post('/api/v1/kuota-management/cuti-bersama', this.cbForm);
                    ElMessage.success('Cuti bersama berhasil ditambahkan');
                }
                this.closeCBModal();
                this.loadCutiBersama();
            } catch (e) {
                ElMessage.error('Gagal menyimpan cuti bersama');
            }
        },
        async confirmDeleteCB(item) {
            if (confirm(`Hapus cuti bersama "${item.Nama_Cuti_Bersama}"?`)) {
                try {
                    await axios.delete(`/api/v1/kuota-management/cuti-bersama/${item.Id_Cuti_Bersama}`);
                    ElMessage.success('Cuti bersama berhasil dihapus');
                    this.loadCutiBersama();
                } catch (e) {
                    ElMessage.error('Gagal menghapus cuti bersama');
                }
            }
        },
        async previewApply() {
            try {
                const res = await axios.get(
                    `/api/v1/kuota-management/cuti-bersama/${this.applyForm.id_cuti_bersama}/preview`,
                );
                if (res.data?.success) {
                    this.previewData = res.data.result;
                    this.showPreviewModal = true;
                }
            } catch (e) {
                ElMessage.error('Gagal membuat preview');
            }
        },
        async executeApply() {
            this.loading.execute = true;
            try {
                const res = await axios.post(
                    `/api/v1/kuota-management/cuti-bersama/${this.applyForm.id_cuti_bersama}/execute`,
                    {
                        id_jenis_cuti: this.applyForm.id_jenis_cuti,
                    },
                );
                ElMessage.success(res.data?.message || 'Eksekusi berhasil');
                this.showPreviewModal = false;
                this.applyForm = { id_cuti_bersama: '', id_jenis_cuti: '' };
                this.loadStats();
                this.loadKaryawan();
            } catch (e) {
                ElMessage.error('Gagal mengeksekusi pemotongan');
            }
            this.loading.execute = false;
        },
    },
};
</script>

<style scoped>
/* Tab Content */
.tab-content-improved {
    /* padding: 1.5rem; */
    background: white;
    border-radius: 12px;
    /* box-shadow: 0 2px 8px rgba(0,0,0,0.06); */
}

.process-stat-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 1rem;
}

.process-stat-grid.compact {
    grid-template-columns: repeat(6, minmax(0, 1fr));
}

.process-stat,
.recovery-panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1rem;
}

.process-stat {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    border-left: 4px solid var(--primary);
}

.process-stat.success {
    border-left-color: var(--success);
}

.process-stat.warning {
    border-left-color: var(--warning);
}

.process-stat.danger {
    border-left-color: var(--danger);
}

.process-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.1;
}

.process-stat-label {
    color: var(--text-muted);
    font-size: 0.85rem;
}

.process-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.process-section-panel {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
    box-shadow: var(--shadow-sm);
}

.process-section-grid {
    align-items: stretch;
}

.process-count-chip {
    padding: 0.45rem 0.75rem;
    border: 1px solid var(--border);
    border-radius: 999px;
    font-weight: 800;
    background: var(--surface);
}

.process-main-cell {
    min-width: 0;
}

.process-title {
    display: block;
    color: var(--dark);
    font-weight: 800;
    line-height: 1.25;
}

.process-number {
    font-size: 1rem;
    font-weight: 900;
}

.process-number.success {
    color: var(--success);
}

.process-number.danger {
    color: var(--danger);
}

.process-empty-card {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 160px;
    padding: 1rem;
    border: 1px dashed var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text-muted);
    text-align: center;
}

.process-mobile-list {
    display: grid;
    gap: 0.85rem;
}

.process-batch-card,
.process-detail-card {
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
}

.process-batch-card {
    padding: 0.9rem;
    border-left: 4px solid rgba(99, 102, 241, 0.35);
}

.process-batch-card__header,
.process-detail-card__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
}

.process-batch-card__body {
    margin-top: 0.85rem;
}

.process-meta-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}

.process-meta-item {
    padding: 0.75rem 0.8rem;
    border-radius: 8px;
    background: var(--surface-soft);
    border: 1px solid var(--border);
}

.process-meta-label {
    display: block;
    margin-bottom: 0.25rem;
    color: var(--text-muted);
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.process-batch-card__footer {
    display: flex;
    justify-content: flex-end;
    margin-top: 0.85rem;
}

.process-detail-card {
    padding: 0.9rem 1rem;
    border-left: 4px solid rgba(239, 68, 68, 0.32);
}

.process-detail-code {
    font-size: 0.95rem;
    letter-spacing: 0.01em;
}

.process-detail-message {
    margin-top: 0.8rem;
    color: #475569;
    font-size: 0.92rem;
    line-height: 1.55;
    word-break: break-word;
}

.process-batch-card:hover,
.process-detail-card:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.process-section-panel .section-title,
.process-section-panel .text-muted {
    color: var(--dark);
}

.process-section-panel small.text-muted {
    color: var(--text-muted) !important;
}

.process-list-item,
.empty-inline {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 0.9rem;
}

.process-list-item {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: flex-start;
}

.cuti-widget-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.cuti-widget-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1rem;
    min-width: 0;
}

.cuti-widget-card-wide {
    grid-column: 1 / -1;
}

.cuti-widget-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
}

.cuti-widget-header h6 {
    margin: 0;
    color: var(--dark);
    font-weight: 700;
}

.cuti-widget-header p {
    margin: 0.25rem 0 0;
    color: var(--text-muted);
    font-size: 0.85rem;
}

.widget-title-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
}

.widget-purpose-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.24rem 0.6rem;
    border: 1px solid rgba(245, 158, 11, 0.35);
    border-radius: 999px;
    background: rgba(245, 158, 11, 0.12);
    color: #92400e;
    font-size: 0.72rem;
    font-weight: 800;
    line-height: 1;
}

.widget-purpose-badge-monthly {
    border-color: rgba(59, 130, 246, 0.35);
    background: rgba(59, 130, 246, 0.12);
    color: #1d4ed8;
}

.widget-context-note {
    max-width: 620px;
}

.widget-summary-row {
    font-weight: 600;
}

.monthly-anniversary-card {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.monthly-anniversary-metrics {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem;
}

.monthly-anniv-metric {
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.75rem;
    background: var(--surface-soft);
}

.monthly-anniv-metric span {
    display: block;
    color: var(--text-muted);
    font-size: 0.72rem;
    font-weight: 700;
}

.monthly-anniv-metric strong {
    display: block;
    margin-top: 0.2rem;
    color: var(--dark);
    font-size: 1.45rem;
    font-weight: 900;
    line-height: 1;
}

.monthly-anniv-metric.success {
    border-color: rgba(16, 185, 129, 0.28);
    background: rgba(16, 185, 129, 0.08);
}

.monthly-anniv-metric.success strong {
    color: var(--success);
}

.monthly-anniv-metric.danger {
    border-color: rgba(239, 68, 68, 0.28);
    background: rgba(239, 68, 68, 0.07);
}

.monthly-anniv-metric.danger strong {
    color: var(--danger);
}

.monthly-anniv-metric.muted {
    border-color: rgba(30, 41, 59, 0.18);
}

.monthly-anniversary-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.monthly-anniv-row {
    width: 100%;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 0.75rem;
    text-align: left;
}

.monthly-anniv-row strong,
.monthly-anniv-row small {
    display: block;
}

.monthly-anniv-row small {
    color: var(--text-muted);
    margin-top: 0.15rem;
}

.monthly-anniversary-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    margin-top: auto;
    font-size: 0.78rem;
}

.cuti-segmented {
    display: inline-flex;
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.2rem;
    gap: 0.2rem;
}

.cuti-segmented button {
    border: 0;
    background: transparent;
    color: var(--text-muted);
    border-radius: 6px;
    padding: 0.35rem 0.65rem;
    font-weight: 600;
    font-size: 0.8rem;
}

.cuti-segmented button.active {
    background: var(--surface);
    color: var(--primary);
    box-shadow: var(--shadow-sm);
}

.heatmap-grid {
    display: grid;
    grid-template-columns: repeat(15, minmax(34px, 1fr));
    gap: 0.45rem;
}

.heatmap-grid.loading {
    opacity: 0.65;
}

.heatmap-day {
    aspect-ratio: 1;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface-soft);
    color: var(--text);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    line-height: 1;
    transition: var(--transition);
}

.heatmap-day__num {
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--dark, #1e293b);
}

.heatmap-day__badge {
    margin-top: 0.2rem;
    color: var(--text-muted);
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.02em;
}

.heatmap-day__flag {
    position: absolute;
    top: 4px;
    right: 6px;
    background: var(--danger, #ef4444);
    color: #fff;
    font-style: normal;
    font-weight: 800;
    font-size: 0.65rem;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 0 2px #fff;
    line-height: 1;
}

.heatmap-day {
    position: relative;
}

.heatmap-day.ok {
    background: rgba(16, 185, 129, 0.14);
    border-color: rgba(16, 185, 129, 0.4);
}
.heatmap-day.ok .heatmap-day__badge {
    color: #047857;
}

.heatmap-day.warn {
    background: rgba(245, 158, 11, 0.16);
    border-color: rgba(245, 158, 11, 0.45);
}
.heatmap-day.warn .heatmap-day__badge {
    color: #b45309;
}

.heatmap-day.fail {
    background: rgba(239, 68, 68, 0.14);
    border-color: rgba(239, 68, 68, 0.42);
}
.heatmap-day.fail .heatmap-day__badge {
    color: #b91c1c;
}

.heatmap-day.idle {
    background: var(--surface-soft);
    border-color: var(--border);
}
.heatmap-day.idle .heatmap-day__badge {
    color: var(--text-muted);
}

/* Scheduler tidak jalan: outline dashed merah (override) */
.heatmap-day.scheduler-missing {
    border-style: dashed;
    border-width: 2px;
    border-color: var(--danger, #ef4444);
}

/* Hari ini: ring biru/indigo */
.heatmap-day.is-today {
    box-shadow: 0 0 0 2px var(--primary, #6366f1);
}

.heatmap-day:hover,
.trend-bar-item:hover,
.anniversary-card:hover,
.anniversary-row:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

/* Legend baru */
.heatmap-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 0.85rem;
    align-items: center;
    font-size: 0.78rem;
    color: var(--text-muted);
}
.heatmap-legend .legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: var(--surface-soft);
    padding: 0.25rem 0.6rem;
    border-radius: 999px;
}
.heatmap-legend .legend-item strong {
    color: var(--dark, #1e293b);
    font-weight: 700;
    margin-left: 2px;
}
.heatmap-legend .legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    border: 1px solid transparent;
}
.heatmap-legend .legend-ok .legend-dot {
    background: rgba(16, 185, 129, 0.7);
}
.heatmap-legend .legend-warn .legend-dot {
    background: rgba(245, 158, 11, 0.75);
}
.heatmap-legend .legend-fail .legend-dot {
    background: rgba(239, 68, 68, 0.75);
}
.heatmap-legend .legend-idle .legend-dot {
    background: var(--border);
}
.heatmap-legend .legend-missing .legend-dot {
    background: transparent;
    border: 2px dashed var(--danger, #ef4444);
}

/* Modal heatmap detail */
.recovery-cta {
    background: rgba(245, 158, 11, 0.08);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-left: 4px solid var(--warning, #f59e0b);
    border-radius: 8px;
}
.done-list-collapsible {
    background: var(--surface-soft, #f1f5f9);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.65rem 0.85rem;
}
.done-list-collapsible > summary {
    cursor: pointer;
    list-style: none;
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--dark, #1e293b);
    outline: none;
    user-select: none;
}
.done-list-collapsible > summary::-webkit-details-marker {
    display: none;
}
.done-list-collapsible[open] > summary {
    margin-bottom: 0.5rem;
}
.done-list-collapsible[open] {
    background: var(--surface, #fff);
}

.widget-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    color: var(--text-muted);
    font-size: 0.8rem;
}

.widget-meta-row span {
    background: var(--surface-soft);
    border-radius: 999px;
    padding: 0.25rem 0.55rem;
}

.trend-bars {
    min-height: 190px;
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    align-items: end;
    gap: 0.65rem;
}

.trend-bar-item {
    border: 0;
    background: transparent;
    min-width: 0;
    height: 210px;
    display: grid;
    grid-template-rows: 24px 1fr 24px 22px;
    align-items: end;
    gap: 0.35rem;
    color: var(--text);
}

.trend-value,
.trend-label {
    text-align: center;
    font-size: 0.78rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.trend-value {
    font-weight: 700;
    color: var(--dark);
}

.trend-label {
    color: var(--text-muted);
}

.trend-admin-badge {
    align-self: start;
    justify-self: center;
    max-width: 100%;
    border: 1px dashed rgba(59, 130, 246, 0.35);
    border-radius: 999px;
    padding: 0.08rem 0.35rem;
    color: var(--primary);
    font-size: 0.62rem;
    font-style: normal;
    font-weight: 800;
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.trend-bar-track {
    width: 100%;
    height: 130px;
    background: var(--surface-soft);
    border-radius: 8px;
    display: flex;
    align-items: flex-end;
    overflow: hidden;
}

.trend-bar-fill {
    width: 100%;
    border-radius: 8px 8px 0 0;
    min-height: 8px;
}

.trend-bar-fill.monthly {
    background: var(--primary);
}
.trend-bar-fill.loyalty {
    background: var(--success);
}

/* Stacked bar baru (expected = realized + gap) */
.trend-bar-stack {
    width: 100%;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    border-radius: 8px 8px 0 0;
    overflow: hidden;
}
.trend-bar-stack .trend-bar-fill {
    width: 100%;
    border-radius: 0;
    min-height: 0;
}
.trend-bar-stack .trend-bar-fill.gap {
    background: repeating-linear-gradient(
        45deg,
        rgba(239, 68, 68, 0.18),
        rgba(239, 68, 68, 0.18) 4px,
        rgba(239, 68, 68, 0.35) 4px,
        rgba(239, 68, 68, 0.35) 8px
    );
}
.trend-bar-stack .trend-bar-fill.blocked {
    background: repeating-linear-gradient(
        135deg,
        rgba(30, 41, 59, 0.18),
        rgba(30, 41, 59, 0.18) 4px,
        rgba(30, 41, 59, 0.34) 4px,
        rgba(30, 41, 59, 0.34) 8px
    );
}
.trend-bar-stack .trend-bar-fill.realized {
    background: linear-gradient(180deg, var(--success), #047857);
    border-radius: 0 0 0 0;
}
.trend-bar-stack .trend-bar-fill.realized.loyalty {
    background: linear-gradient(180deg, var(--primary, #6366f1), #4338ca);
}
.trend-bar-stack .trend-bar-fill.realized:first-child {
    border-radius: 8px 8px 0 0;
}

.trend-value .trend-expected {
    color: var(--text-muted);
    font-weight: 500;
    font-size: 0.78em;
    margin-left: 1px;
}

.trend-coverage {
    font-size: 0.65rem;
    font-weight: 800;
    font-style: normal;
    padding: 2px 6px;
    border-radius: 999px;
    background: var(--surface-soft);
}

.trend-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem 0.9rem;
    font-size: 0.72rem;
    color: var(--text-muted);
    align-items: center;
}
.trend-legend .dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 4px;
    vertical-align: middle;
}
.trend-legend .dot.bg-success {
    background: var(--success);
}
.trend-legend .dot.bg-danger {
    background: repeating-linear-gradient(
        45deg,
        rgba(239, 68, 68, 0.5),
        rgba(239, 68, 68, 0.5) 2px,
        rgba(239, 68, 68, 0.85) 2px,
        rgba(239, 68, 68, 0.85) 4px
    );
}
.trend-legend .dot.bg-dark {
    background: repeating-linear-gradient(
        135deg,
        rgba(30, 41, 59, 0.45),
        rgba(30, 41, 59, 0.45) 2px,
        rgba(30, 41, 59, 0.85) 2px,
        rgba(30, 41, 59, 0.85) 4px
    );
}

/* Anniversary stat chips */
.anniv-stat-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.anniv-stat-chips .chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    border: 1px solid transparent;
}
.anniv-stat-chips .chip-success {
    background: rgba(16, 185, 129, 0.12);
    color: #047857;
    border-color: rgba(16, 185, 129, 0.3);
}
.anniv-stat-chips .chip-today {
    background: rgba(99, 102, 241, 0.12);
    color: #4338ca;
    border-color: rgba(99, 102, 241, 0.3);
}
.anniv-stat-chips .chip-danger {
    background: rgba(239, 68, 68, 0.12);
    color: #b91c1c;
    border-color: rgba(239, 68, 68, 0.3);
}
.anniv-stat-chips .chip-dark {
    background: rgba(30, 41, 59, 0.08);
    color: #1e293b;
    border-color: rgba(30, 41, 59, 0.2);
}
.anniv-stat-chips .chip-muted {
    background: var(--surface-soft);
    color: var(--text-muted);
    border-color: var(--border);
}
.anniv-stat-chips .chip-info {
    background: rgba(59, 130, 246, 0.12);
    color: #1d4ed8;
    border-color: rgba(59, 130, 246, 0.3);
}

.anniversary-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    max-height: 360px;
    overflow: auto;
}

.anniversary-card,
.anniversary-row {
    width: 100%;
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 8px;
    padding: 0.85rem;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 150px 132px;
    gap: 0.75rem;
    align-items: center;
    text-align: left;
    color: var(--text);
    transition: var(--transition);
}

.anniversary-card {
    appearance: none;
    cursor: pointer;
}

.anniversary-card.anniv-missed {
    border-color: rgba(239, 68, 68, 0.35);
    background: linear-gradient(90deg, rgba(239, 68, 68, 0.06), var(--surface) 36%);
}

.anniversary-card.anniv-today {
    border-color: rgba(99, 102, 241, 0.35);
    background: linear-gradient(90deg, rgba(99, 102, 241, 0.06), var(--surface) 36%);
}

.anniversary-card.anniv-blocked {
    border-color: rgba(30, 41, 59, 0.22);
    background: linear-gradient(90deg, rgba(30, 41, 59, 0.05), var(--surface) 36%);
}

.anniversary-card.anniv-ineligible {
    opacity: 0.86;
}

.anniv-card__main,
.anniv-card__date,
.anniv-card__status {
    min-width: 0;
}

.anniv-card__main strong {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.anniversary-card small,
.anniversary-row small {
    display: block;
    color: var(--text-muted);
    margin-top: 0.15rem;
}

.anniversary-status {
    justify-self: end;
    border-radius: 999px;
    padding: 0.3rem 0.65rem;
    font-size: 0.48rem;
    font-weight: 700;
}

.anniversary-status.status-done,
.anniversary-status.done {
    background: rgba(16, 185, 129, 0.12);
    color: var(--success);
}

.anniversary-status.status-pending,
.anniversary-status.pending {
    background: rgba(59, 130, 246, 0.12);
    color: var(--info);
}

.anniversary-status.status-missed,
.anniversary-status.missed {
    background: rgba(239, 68, 68, 0.12);
    color: var(--danger);
}

.anniversary-status.status-today {
    background: rgba(99, 102, 241, 0.12);
    color: #4338ca;
}

.anniversary-status.status-blocked {
    background: rgba(30, 41, 59, 0.1);
    color: #1e293b;
}

.anniversary-status.status-ineligible {
    background: var(--surface-soft);
    color: var(--text-muted);
}

.dashboard-detail-content {
    min-height: 160px;
}

.tengahkan {
    display: flex;
    justify-content: center;
    align-items: center;
}

/* Filter Section */
.filter-section {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
}
/* .search-box {
    display: flex;
    align-items: center;
    background: white;
    border-radius: 8px;
    padding: 0.6rem 1rem;
    border: 1px solid #e0e0e0;
} */
.search-box i {
    color: #6c757d;
    margin-right: 0.75rem;
}
.search-input {
    border: none;
    outline: none;
    flex: 1;
    font-size: 0.95rem;
}
.filter-select {
    width: 100%;
    padding: 0.6rem 1rem;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    background: white;
    font-size: 0.95rem;
}

/* Loading State */
.loading-state {
    text-align: center;
    padding: 4rem 2rem;
}
.loading-spinner i {
    font-size: 2.5rem;
    color: #667eea;
    animation: spin 1s linear infinite;
}
.loading-title {
    margin-top: 1rem;
    font-weight: 600;
    color: #495057;
}
.loading-description {
    color: #6c757d;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
.spin {
    animation: spin 1s linear infinite;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}
.empty-icon i {
    font-size: 4rem;
    color: #dee2e6;
}
.empty-title {
    margin-top: 1rem;
    font-weight: 600;
    color: #495057;
}
.empty-description {
    color: #6c757d;
}

/* Section Title */
.section-title {
    font-weight: 600;
    color: #495057;
    font-size: 1rem;
}

/* Calendar */
.calendar-container {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.25rem;
}
.calendar-header {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 1rem;
}
.year-display {
    font-size: 1.25rem;
    font-weight: 600;
    color: #495057;
}
.btn-nav {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 0.25rem 0.5rem;
    cursor: pointer;
}
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 0.5rem;
}
@media (max-width: 768px) {
    .calendar-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}
@media (max-width: 576px) {
    .calendar-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
.month-card {
    background: white;
    border-radius: 8px;
    padding: 0.75rem;
    text-align: center;
    border: 1px solid #e9ecef;
}
.month-name {
    font-weight: 600;
    font-size: 0.8rem;
    color: #6c757d;
    margin-bottom: 0.5rem;
}
.month-dates {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    justify-content: center;
    min-height: 24px;
}
.cb-date {
    background: #dc3545;
    color: white;
    border-radius: 4px;
    padding: 2px 6px;
    font-size: 0.7rem;
    font-weight: 600;
}
.no-cb {
    color: #adb5bd;
    font-size: 0.75rem;
}

/* Table */
.table-modern {
    margin-bottom: 0;
}
.table-modern thead th {
    background: #f8f9fa;
    font-weight: 600;
    font-size: 0.85rem;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}
.table-modern tbody tr:hover {
    background: #f8f9fa;
}

.table-modern tbody tr.clickable-row {
    cursor: pointer;
}

.table-modern tbody tr.clickable-row:hover {
    background: rgba(59, 130, 246, 0.08);
}

.batch-drilldown {
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
    background: var(--surface);
}

/* Buttons */
.btn-modern {
    padding: 0.6rem 1.25rem;
    border-radius: 8px;
    border: none;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    transition: all 0.2s;
}
.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}
.btn-warning {
    background: linear-gradient(135deg, #f6d365, #fda085);
    color: #333;
}
.btn-action {
    padding: 0.35rem 0.6rem;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-size: 0.85rem;
}
.btn-view {
    background: #e3f2fd;
    color: #1976d2;
}
.btn-edit {
    background: #fff3e0;
    color: #f57c00;
}
.btn-delete {
    background: #ffebee;
    color: #c62828;
}

/* Apply Section */
.apply-section {
    background: linear-gradient(135deg, #fff3cd, #ffeeba);
    border-radius: 12px;
    padding: 1.25rem;
    border: 1px solid #ffc107;
}
.apply-section h6 {
    color: #856404;
    margin-bottom: 1rem;
}

/* Hutang Row Styling */
.hutang-row {
    background: linear-gradient(180deg, #ffebee, #ffcdd2) !important;
}

/* Confirmation Modal */
.confirm-content {
    text-align: center;
    padding: 1rem;
}
.confirm-icon {
    display: flex;
    justify-content: center;
    align-items: center;
}
.confirm-message h5 {
    color: #495057;
    font-weight: 600;
}
.confirm-details {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 1rem;
    text-align: left;
}

/* Detail Modal */
.detail-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 1.25rem;
    border-radius: 12px;
    margin: -1rem -1rem 1rem -1rem;
}
.avatar-lg {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.25rem;
}
.detail-info h5 {
    margin: 0;
    color: white;
}
.detail-info p {
    opacity: 0.9;
}
.detail-subtitle {
    color: rgba(255, 255, 255, 0.95) !important;
    font-size: 0.9rem;
    font-weight: 500;
}

/* Summary Cards */
.summary-card {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 1rem;
    text-align: center;
}
.summary-value {
    display: block;
    font-size: 1.75rem;
    font-weight: 700;
}
.summary-label {
    font-size: 0.75rem;
    color: #6c757d;
    text-transform: uppercase;
}

/* Detail Tabs */
.detail-tabs {
    display: flex;
    gap: 0.5rem;
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 0.5rem;
}
.tab-btn {
    padding: 0.5rem 1rem;
    border: none;
    background: transparent;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    color: #6c757d;
}
.tab-btn.active {
    background: #667eea;
    color: white;
}

/* Timeline */
.timeline-list {
    max-height: 300px;
    overflow-y: auto;
}
.timeline-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f1f3f4;
}
.timeline-date {
    font-size: 0.8rem;
    color: #6c757d;
    min-width: 80px;
}
.timeline-content {
    flex: 1;
}

/* Log List */
.log-list {
    max-height: 300px;
    overflow-y: auto;
}
.log-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f1f3f4;
}
.log-badge {
    font-weight: 700;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    min-width: 50px;
    text-align: center;
}
.log-badge.credit {
    background: #d4edda;
    color: #155724;
}
.log-badge.debit {
    background: #f8d7da;
    color: #721c24;
}
.log-info {
    flex: 1;
}

/* Preview Cards */
.preview-card {
    text-align: center;
    padding: 1.25rem;
    border-radius: 10px;
}
.preview-value {
    display: block;
    font-size: 2rem;
    font-weight: 700;
}
.preview-label {
    font-size: 0.8rem;
    text-transform: uppercase;
}
.preview-card.success {
    background: #d4edda;
    color: #155724;
}
.preview-card.warning {
    background: #fff3cd;
    color: #856404;
}
.preview-card.danger {
    background: #f8d7da;
    color: #721c24;
}
</style>

<style scoped>
/* CSS Variables untuk Konsistensi */
*,
*::before,
*::after {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #a5b4fc;
    --secondary: #64748b;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #06b6d4;
    --light: #f8fafc;
    --dark: #1e293b;
    --surface: #ffffff;
    --surface-soft: #f1f5f9;
    --border: #e2e8f0;
    --text: #334155;
    --text-muted: #64748b;
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

.bi {
    display: inline-table;
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
    border-right: 1px solid #e9ecef;
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
    border-top: 1px solid #e9ecef;
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
/* 
.avatar-lg {
    width: 80px;
    height: 80px;
    font-size: 1.5rem;
    margin-bottom: 1rem;
} */

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
    color: #fecaca;
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
    color: #bbf7d0;
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
        /* border-right: 1px solid #e9ecef; */
        padding: 0rem;
        border: none;
    }

    .summary-label {
        font-size: 0.5rem;
    }

    .avatar-lg {
        width: 80px;
        height: 60px;
        font-size: 1.5rem;
        margin-bottom: 1rem;
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
    padding: none;
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
    background: #059669;
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-modern.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-modern.btn-danger:hover {
    background: #dc2626;
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
    color: #3b82f6;
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

/* Toast Notifications */
.toast-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: var(--surface);
    border-radius: var(--radius);
    padding: 1rem 1.5rem;
    box-shadow: var(--shadow-lg);
    border-left: 4px solid var(--primary);
    transform: translateX(100%);
    transition: transform 0.3s ease;
    z-index: 9999;
    min-width: 300px;
}

.toast-notification.show {
    transform: translateX(0);
}

.toast-success {
    border-left-color: var(--success);
}

.toast-error {
    border-left-color: var(--danger);
}

.toast-warning {
    border-left-color: var(--warning);
}

.toast-info {
    border-left-color: var(--info);
}

.toast-content {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.toast-content i {
    font-size: 1.25rem;
}

.toast-success .toast-content i {
    color: var(--success);
}

.toast-error .toast-content i {
    color: var(--danger);
}

.toast-warning .toast-content i {
    color: var(--warning);
}

.toast-info .toast-content i {
    color: var(--info);
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
    .cuti-widget-grid {
        grid-template-columns: 1fr;
    }

    .heatmap-grid {
        grid-template-columns: repeat(10, minmax(30px, 1fr));
    }

    .anniversary-card,
    .anniversary-row {
        grid-template-columns: minmax(0, 1fr) 128px 118px;
    }

    .process-stat-grid,
    .process-stat-grid.compact {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

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

    .process-meta-grid {
        grid-template-columns: 1fr;
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
    .cuti-widget-header {
        flex-direction: column;
    }

    .cuti-segmented {
        width: 100%;
    }

    .cuti-segmented button {
        flex: 1;
    }

    .heatmap-grid {
        grid-template-columns: repeat(7, minmax(30px, 1fr));
    }

    .trend-bars {
        overflow-x: auto;
        grid-template-columns: repeat(6, minmax(58px, 1fr));
    }

    .anniversary-card,
    .anniversary-row {
        grid-template-columns: 1fr;
        gap: 0.35rem;
    }

    .anniv-card__status {
        justify-self: start;
    }

    .anniversary-status {
        justify-self: start;
    }

    .process-stat-grid,
    .process-stat-grid.compact {
        grid-template-columns: 1fr;
    }

    .monthly-anniversary-footer {
        align-items: flex-start;
        flex-direction: column;
    }

    .process-batch-card__header,
    .process-detail-card__header {
        flex-direction: column;
    }

    .process-batch-card__footer {
        justify-content: flex-start;
    }

    .stats-overview .row {
        grid-template-columns: 1fr;
    }

    .tab-title {
        font-size: 1.1rem;
    }

    .search-box {
        margin-top: 1rem;
    }

    .toast-notification {
        min-width: calc(100vw - 40px);
        right: 20px;
        left: 20px;
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

/* Export Header Mobile Responsive */
.export-header-mobile {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.date-range-picker {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 0;
}

.date-range-picker input[type='date'] {
    flex: 1;
    min-width: 110px;
    max-width: 150px;
}

.export-btn-mobile {
    white-space: nowrap;
    flex-shrink: 0;
}

/* Homepage-aligned shell */
.kuota-management-page {
    position: relative;
    min-height: 100vh;
    background:
        radial-gradient(circle at top right, rgba(99, 102, 241, 0.08), transparent 22rem),
        linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
}

.kuota-hero {
    position: relative;
    overflow: hidden;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(260px, 360px);
    gap: 1rem;
    align-items: center;
    margin: 1rem auto 1.25rem;
    padding: 1.25rem;
    border: 1px solid rgba(199, 210, 254, 0.9);
    border-radius: 1.15rem;
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.95), transparent 16rem),
        linear-gradient(135deg, rgba(255, 255, 255, 0.92), rgba(238, 242, 255, 0.82));
    box-shadow:
        0 12px 30px rgba(15, 23, 42, 0.06),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.kuota-hero::after {
    content: '';
    position: absolute;
    right: -4rem;
    top: -4.5rem;
    width: 12rem;
    height: 12rem;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.12);
    filter: blur(10px);
    pointer-events: none;
}

.kuota-hero__copy,
.kuota-hero__stats {
    position: relative;
    z-index: 1;
}

.kuota-hero__copy h2 {
    margin: 0.35rem 0 0.5rem;
    color: #0f172a;
    font-size: clamp(1.35rem, 2.2vw, 1.8rem);
    font-weight: 950;
    letter-spacing: -0.04em;
}

.kuota-hero__copy p {
    max-width: 56rem;
    margin: 0;
    color: #64748b;
    font-size: 0.92rem;
    line-height: 1.65;
}

.kuota-hero__stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
}

.kuota-hero__stat {
    display: grid;
    gap: 0.2rem;
    min-height: 5.5rem;
    padding: 0.9rem 1rem;
    border-radius: 0.95rem;
    border: 1px solid #e2e8f0;
    background: linear-gradient(180deg, #ffffff, #f8fafc);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
}

.kuota-hero__stat span {
    color: #64748b;
    font-size: 0.7rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.kuota-hero__stat strong {
    color: #0f172a;
    font-size: 1.8rem;
    font-weight: 950;
    line-height: 1;
    letter-spacing: -0.04em;
}

.kuota-hero__stat.is-primary {
    background: linear-gradient(180deg, rgba(239, 246, 255, 0.95), rgba(255, 255, 255, 0.98));
    border-color: rgba(191, 219, 254, 0.95);
}

.kuota-hero__stat.is-success {
    background: linear-gradient(180deg, rgba(240, 253, 244, 0.95), rgba(255, 255, 255, 0.98));
    border-color: rgba(187, 247, 208, 0.95);
}

.kuota-main-grid {
    margin-top: 0;
}

.kuota-management-page :deep(.section-label) {
    display: block;
    color: #64748b;
    font-size: 0.64rem;
    font-weight: 900;
    letter-spacing: 0.09em;
    text-transform: uppercase;
}

.kuota-management-page :deep(.tab-content-improved) {
    padding: 0;
    background: transparent;
    border-radius: 0;
}

.kuota-management-page :deep(.filter-section),
.kuota-management-page :deep(.recovery-panel),
.kuota-management-page :deep(.apply-section),
.kuota-management-page :deep(.cuti-widget-card),
.kuota-management-page :deep(.process-stat),
.kuota-management-page :deep(.batch-drilldown) {
    border-color: rgba(226, 232, 240, 0.95);
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
}

.kuota-management-page :deep(.filter-section) {
    margin-bottom: 1rem;
    border-radius: 1rem;
}

.kuota-management-page :deep(.table-responsive) {
    overflow: hidden;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: 1rem;
    background: #fff;
    box-shadow: 0 12px 26px rgba(15, 23, 42, 0.05);
}

.kuota-management-page :deep(.table-modern) {
    margin-bottom: 0;
}

.kuota-management-page :deep(.table-modern thead th) {
    background: linear-gradient(180deg, #f8fafc, #eef2ff);
    color: #0f172a;
    font-size: 0.8rem;
    font-weight: 900;
    border-bottom: 1px solid #e2e8f0;
}

.kuota-management-page :deep(.table-modern tbody tr) {
    transition:
        transform 180ms ease,
        background-color 180ms ease,
        box-shadow 180ms ease;
}

.kuota-management-page :deep(.table-modern tbody tr:hover) {
    background: rgba(99, 102, 241, 0.04);
}

.kuota-management-page :deep(.empty-state),
.kuota-management-page :deep(.loading-state) {
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.95);
    box-shadow: 0 12px 26px rgba(15, 23, 42, 0.05);
}

.kuota-management-page :deep(.empty-state) {
    margin-bottom: 1rem;
}

.kuota-management-page :deep(.loading-spinner) {
    background: rgba(224, 231, 255, 0.95);
    color: #4f46e5;
}

.kuota-management-page :deep(.badge.bg-success),
.kuota-management-page :deep(.badge.bg-warning),
.kuota-management-page :deep(.badge.bg-danger),
.kuota-management-page :deep(.badge.bg-secondary),
.kuota-management-page :deep(.badge.bg-info),
.kuota-management-page :deep(.badge.bg-primary) {
    border-radius: 999px;
    padding: 0.42rem 0.7rem;
    font-weight: 800;
    letter-spacing: 0.01em;
}

.kuota-management-page :deep(.btn-modern),
.kuota-management-page :deep(.btn-action),
.kuota-management-page :deep(.btn-nav),
.kuota-management-page :deep(.page-btn),
.kuota-management-page :deep(.page-number) {
    border-radius: 0.85rem;
}

.kuota-management-page :deep(.btn-modern.btn-primary) {
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    box-shadow: 0 10px 20px rgba(79, 70, 229, 0.18);
}

.kuota-management-page :deep(.btn-modern.btn-secondary),
.kuota-management-page :deep(.btn-modern.btn-outline) {
    background: #fff;
    color: #334155;
    border: 1px solid #cbd5e1;
}

.kuota-management-page :deep(.btn-modern.btn-success) {
    background: linear-gradient(135deg, #059669, #10b981);
    box-shadow: 0 10px 20px rgba(16, 185, 129, 0.18);
}

.kuota-management-page :deep(.btn-modern.btn-warning) {
    background: linear-gradient(135deg, #f59e0b, #fbbf24);
    color: #1f2937;
}

.kuota-management-page :deep(.pagination-container) {
    flex-wrap: wrap;
    gap: 0.5rem;
    border-top: 0;
    padding: 1.1rem 0 0.25rem;
}

.kuota-management-page :deep(.page-info) {
    margin-left: 0;
}

.kuota-management-page :deep(.recovery-field .el-input__wrapper),
.kuota-management-page :deep(.recovery-field .el-select__wrapper),
.kuota-management-page :deep(.recovery-field .el-date-editor.el-input__wrapper) {
    min-height: 44px;
    border-radius: 0.9rem;
    box-shadow: none;
    border: 1px solid #dbe4f0;
    background: #fff;
}

.kuota-management-page :deep(.recovery-field .el-input__wrapper.is-focus),
.kuota-management-page :deep(.recovery-field .el-select__wrapper.is-focused),
.kuota-management-page :deep(.recovery-field .el-date-editor.el-input__wrapper.is-focus) {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.kuota-management-page :deep(.recovery-field .el-select__tags) {
    flex-wrap: nowrap;
}

.kuota-management-page :deep(.recovery-field .el-select__tags-text) {
    max-width: 180px;
}

@media (max-width: 992px) {
    .kuota-hero {
        grid-template-columns: 1fr;
    }

    .kuota-hero__stats {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .kuota-shell {
        padding: 0 0.75rem 1rem;
    }

    .kuota-hero {
        padding: 1rem;
        margin-top: 0.75rem;
    }

    .kuota-hero__stats {
        grid-template-columns: 1fr;
    }

    .kuota-hero__stat {
        min-height: auto;
    }

    .kuota-management-page :deep(.table-responsive) {
        border-radius: 0.85rem;
    }

    .process-section-panel {
        padding: 0.9rem;
    }
}

@media (max-width: 576px) {
    .export-header-mobile {
        flex-direction: column;
        align-items: stretch;
    }

    .date-range-picker {
        width: 100%;
        justify-content: center;
    }

    .date-range-picker input[type='date'] {
        flex: 1;
        max-width: none;
    }

    .export-btn-mobile {
        width: 100%;
        margin-top: 8px;
        display: flex;
        justify-content: center;
        align-items: center;
    }
}

.cuti-widget-card {
    position: relative;
    overflow: hidden;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.99), rgba(248, 250, 252, 0.96));
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: 18px;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
    transition:
        transform 180ms ease,
        box-shadow 180ms ease,
        border-color 180ms ease;
}

.cuti-widget-card::before {
    content: '';
    position: absolute;
    inset: 0 auto auto 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), rgba(99, 102, 241, 0.18));
}

.cuti-widget-card:hover,
.monthly-anniv-row:hover,
.anniversary-card:hover,
.anniversary-row:hover,
.trend-bar-item:hover,
.summary-card:hover,
.preview-card:hover,
.process-batch-card:hover,
.process-detail-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 18px 36px rgba(15, 23, 42, 0.08);
}

.cuti-widget-header h6,
.section-title,
.detail-info h5,
.confirm-message h5 {
    color: var(--dark);
    font-weight: 800;
}

.cuti-widget-header p,
.widget-context-note,
.widget-summary-row,
.detail-subtitle,
.summary-label,
.preview-label,
.status-label,
.timeline-date,
.log-info small,
.process-detail-message,
.process-meta-label,
.process-count-chip {
    color: var(--text-muted);
}

.widget-purpose-badge {
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.18);
    color: #4338ca;
    border-radius: 999px;
}

.widget-purpose-badge-monthly {
    background: rgba(99, 102, 241, 0.08);
    border-color: rgba(99, 102, 241, 0.22);
    color: #4338ca;
}

.monthly-anniv-metric,
.monthly-anniv-row,
.anniversary-card,
.anniversary-row,
.summary-card,
.preview-card,
.confirm-details,
.done-list-collapsible,
.recovery-cta,
.status-item,
.process-batch-card,
.process-detail-card,
.process-empty-card,
.process-meta-item {
    border-color: rgba(226, 232, 240, 0.95);
    border-radius: 14px;
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05);
}

.monthly-anniv-row,
.anniversary-card,
.anniversary-row,
.process-batch-card,
.process-detail-card {
    background: var(--surface);
}

.monthly-anniv-metric,
.summary-card,
.preview-card,
.confirm-details,
.done-list-collapsible,
.recovery-cta,
.status-item,
.process-empty-card,
.process-meta-item {
    background: var(--surface-soft);
}

.monthly-anniv-row,
.anniversary-card,
.anniversary-row {
    transition: var(--transition);
}

.dashboard-detail-content {
    display: grid;
    gap: 1rem;
}

.detail-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin: 0 0 1rem 0;
    padding: 1.1rem 1.15rem;
    border: 1px solid var(--border);
    border-left: 4px solid var(--primary);
    border-radius: 18px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(255, 255, 255, 0.98));
    color: var(--dark);
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05);
}

.avatar-lg {
    width: 80px;
    height: 80px;
    border-radius: 20px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: #fff;
    box-shadow: var(--shadow-sm);
}

.detail-summary-grid,
.preview-summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
}

.summary-card,
.preview-card {
    position: relative;
    overflow: hidden;
    padding: 1.05rem 1.1rem;
    background:
        radial-gradient(circle at top right, rgba(99, 102, 241, 0.08), transparent 58%),
        linear-gradient(180deg, #ffffff, #f8fafc);
}

.summary-card {
    text-align: center;
}

.summary-value {
    color: var(--dark);
    font-size: 2.1rem;
    font-weight: 900;
    line-height: 1;
}

.summary-label,
.preview-label {
    letter-spacing: 0.05em;
    font-weight: 800;
}

.detail-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    padding: 0.4rem;
    border: 1px solid var(--border);
    border-radius: 16px;
    background: var(--surface-soft);
}

.tab-btn {
    border-radius: 12px;
    color: var(--text-muted);
}

.tab-btn.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: #fff;
    box-shadow: var(--shadow-sm);
}

.confirm-details {
    border: 1px solid rgba(226, 232, 240, 0.95);
    padding: 1rem 1.05rem;
    background: var(--surface-soft);
}

.detail-content-v2 {
    display: grid;
    gap: 1rem;
    padding: 0.25rem 0;
}

.detail-left-col {
    border-right: 1px solid var(--border);
    padding-right: 1.5rem;
}

.info-item,
.status-item,
.timeline-item,
.log-item {
    border-bottom-color: var(--border);
}

.info-item .label,
.status-label,
.timeline-date,
.log-badge,
.process-meta-label {
    color: var(--text-muted);
}

.info-item .value,
.value-reason,
.status-value,
.timeline-content,
.log-info strong {
    color: var(--dark);
}

.recovery-cta {
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-left: 4px solid var(--warning, #f59e0b);
    background: var(--surface-soft);
}

.done-list-collapsible {
    border: 1px solid rgba(226, 232, 240, 0.95);
    background: var(--surface-soft);
}

.modal-footer {
    border-top: 1px solid var(--border);
    padding-top: 1rem;
}

.btn-modern {
    border-radius: 10px;
}

.table-compact-detail thead th {
    font-size: 0.76rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    white-space: nowrap;
}

.table-compact-detail tbody tr:hover {
    background: rgba(99, 102, 241, 0.04);
}

.table-compact-detail tbody td {
    vertical-align: top;
}

.detail-employee-cell {
    display: grid;
    gap: 0.1rem;
    min-width: 0;
}

.detail-employee-cell code {
    color: #ec4899;
    font-weight: 800;
    font-size: 0.78rem;
}

.detail-employee-cell strong {
    color: var(--dark);
    font-weight: 800;
    line-height: 1.25;
}

.detail-employee-cell small {
    color: var(--text-muted);
    font-size: 0.78rem;
}

.detail-mobile-list {
    display: grid;
    gap: 0.75rem;
}

.detail-mobile-card {
    background: linear-gradient(180deg, #ffffff, #f8fafc);
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: 16px;
    padding: 0.95rem;
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05);
}

.detail-mobile-card__top,
.detail-mobile-card__middle,
.detail-mobile-card__footer,
.detail-mobile-card__action {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}

.detail-mobile-card__top code {
    color: #ec4899;
    font-weight: 800;
}

.detail-mobile-card__top strong,
.detail-mobile-card__middle strong {
    display: block;
    color: var(--dark);
}

.detail-mobile-card__top small,
.detail-mobile-card__middle small,
.detail-mobile-card__footer small {
    color: var(--text-muted);
}

.detail-mobile-card__middle {
    align-items: center;
    margin-top: 0.75rem;
}

.detail-mobile-card__reason {
    margin: 0.75rem 0 0;
    color: var(--dark);
    line-height: 1.5;
    font-size: 0.9rem;
}

.detail-mobile-card__action {
    margin-top: 0.75rem;
    align-items: center;
}

.detail-mobile-card.done .detail-mobile-card__middle {
    padding-top: 0.1rem;
}

.detail-mobile-card.problem {
    border-left: 4px solid rgba(239, 68, 68, 0.35);
}

.detail-mobile-card.done {
    border-left: 4px solid rgba(16, 185, 129, 0.35);
}

.process-stat-grid {
    gap: 0.85rem;
}

.process-stat,
.process-section-panel,
.process-list-item,
.empty-inline,
.batch-drilldown {
    border-radius: 14px;
}

.process-stat {
    background: linear-gradient(180deg, #ffffff, #f8fafc);
    box-shadow: 0 10px 20px rgba(15, 23, 42, 0.04);
}

.process-detail-card,
.process-batch-card {
    background: linear-gradient(180deg, #ffffff, #f8fafc);
}

.process-meta-item {
    background: rgba(248, 250, 252, 0.92);
}

@media (max-width: 768px) {
    .detail-summary-grid,
    .preview-summary-grid {
        grid-template-columns: 1fr;
    }

    .detail-header {
        align-items: flex-start;
    }

    .detail-header,
    .summary-card,
    .preview-card,
    .cuti-widget-card {
        border-radius: 16px;
    }

    .detail-left-col {
        border-right: 0;
        padding-right: 0;
    }

    .detail-tabs {
        border-radius: 12px;
    }
}

@media (max-width: 576px) {
    .detail-header {
        padding: 0.9rem;
        gap: 0.85rem;
    }

    .avatar-lg {
        width: 64px;
        height: 64px;
        font-size: 1rem;
    }

    .summary-card,
    .preview-card,
    .confirm-details,
    .detail-mobile-card {
        padding: 0.9rem;
    }

    .summary-value {
        font-size: 1.85rem;
    }
}

.kuota-management-page :deep(.detail-heading) {
    color: #0f172a;
    font-size: 1.25rem;
    font-weight: 900;
    letter-spacing: -0.03em;
}

.kuota-management-page :deep(.cuti-widget-grid) {
    gap: 1rem;
    align-items: start;
}

.kuota-management-page :deep(.cuti-widget-card) {
    border: 1px solid rgba(226, 232, 240, 0.95);
    background:
        radial-gradient(circle at top right, rgba(99, 102, 241, 0.08), transparent 42%),
        linear-gradient(180deg, rgba(255, 255, 255, 0.99), rgba(248, 250, 252, 0.97));
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
}

.kuota-management-page :deep(.cuti-widget-card::before) {
    height: 5px;
    background: linear-gradient(90deg, var(--primary), rgba(99, 102, 241, 0.08));
}

.kuota-management-page :deep(.cuti-widget-card-wide) {
    background:
        radial-gradient(circle at top right, rgba(99, 102, 241, 0.1), transparent 34%),
        linear-gradient(180deg, rgba(255, 255, 255, 0.995), rgba(245, 247, 255, 0.97));
}

.kuota-management-page :deep(.cuti-widget-header) {
    align-items: flex-start;
    gap: 0.9rem;
    margin-bottom: 1rem;
}

.kuota-management-page :deep(.cuti-widget-header h6) {
    margin: 0;
    color: #0f172a;
    font-size: 1.03rem;
    font-weight: 900;
    letter-spacing: -0.02em;
}

.kuota-management-page :deep(.widget-title-row) {
    margin-bottom: 0.2rem;
}

.kuota-management-page :deep(.widget-purpose-badge) {
    background: rgba(79, 70, 229, 0.08);
    border: 1px solid rgba(79, 70, 229, 0.16);
    color: #4338ca;
    box-shadow: 0 8px 16px rgba(79, 70, 229, 0.08);
}

.kuota-management-page :deep(.widget-purpose-badge-monthly) {
    background: rgba(16, 185, 129, 0.08);
    border-color: rgba(16, 185, 129, 0.18);
    color: #047857;
}

.kuota-management-page :deep(.widget-context-note),
.kuota-management-page :deep(.widget-summary-row) {
    color: #64748b;
    line-height: 1.55;
}

.kuota-management-page :deep(.heatmap-grid),
.kuota-management-page :deep(.trend-bars),
.kuota-management-page :deep(.monthly-anniversary-list),
.kuota-management-page :deep(.anniversary-list),
.kuota-management-page :deep(.process-list) {
    gap: 0.85rem;
}

.kuota-management-page :deep(.monthly-anniversary-metrics) {
    gap: 0.9rem;
}

.kuota-management-page :deep(.monthly-anniv-metric) {
    position: relative;
    overflow: hidden;
    min-height: 104px;
    padding: 1rem 1.1rem;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #6366f1;
    border-radius: 20px;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96));
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.04);
}

.kuota-management-page :deep(.monthly-anniv-metric::after) {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at top right, rgba(99, 102, 241, 0.08), transparent 40%);
    pointer-events: none;
}

.kuota-management-page :deep(.monthly-anniv-metric > *) {
    position: relative;
    z-index: 1;
}

.kuota-management-page :deep(.monthly-anniv-metric span) {
    margin-bottom: 0.2rem;
    color: #64748b;
    font-size: 0.76rem;
    font-weight: 800;
    letter-spacing: 0;
    text-transform: none;
}

.kuota-management-page :deep(.monthly-anniv-metric strong) {
    color: #0f172a;
    font-size: 2.15rem;
    font-weight: 900;
    line-height: 1;
    letter-spacing: -0.05em;
}

.kuota-management-page :deep(.monthly-anniv-metric.success) {
    border-left-color: #10b981;
    background: linear-gradient(180deg, rgba(236, 253, 245, 0.96), rgba(255, 255, 255, 0.98));
}

.kuota-management-page :deep(.monthly-anniv-metric.success::after) {
    background: radial-gradient(circle at top right, rgba(16, 185, 129, 0.1), transparent 40%);
}

.kuota-management-page :deep(.monthly-anniv-metric.success strong) {
    color: #10b981;
}

.kuota-management-page :deep(.monthly-anniv-metric.danger) {
    border-left-color: #f97316;
    background: linear-gradient(180deg, rgba(255, 247, 237, 0.96), rgba(255, 255, 255, 0.98));
}

.kuota-management-page :deep(.monthly-anniv-metric.danger::after) {
    background: radial-gradient(circle at top right, rgba(249, 115, 22, 0.1), transparent 40%);
}

.kuota-management-page :deep(.monthly-anniv-metric.danger strong) {
    color: #ef4444;
}

.kuota-management-page :deep(.monthly-anniv-metric.muted) {
    border-left-color: #6366f1;
    background: linear-gradient(180deg, rgba(244, 246, 255, 0.96), rgba(255, 255, 255, 0.98));
}

.kuota-management-page :deep(.monthly-anniv-metric.muted::after) {
    background: radial-gradient(circle at top right, rgba(99, 102, 241, 0.08), transparent 40%);
}

.kuota-management-page :deep(.monthly-anniv-metric.muted strong) {
    color: #1e293b;
}

.kuota-management-page :deep(.trend-bar-item),
.kuota-management-page :deep(.monthly-anniv-row),
.kuota-management-page :deep(.anniversary-card),
.kuota-management-page :deep(.process-batch-card),
.kuota-management-page :deep(.process-detail-card),
.kuota-management-page :deep(.summary-card),
.kuota-management-page :deep(.preview-card) {
    border: 1px solid rgba(226, 232, 240, 0.95);
    background: linear-gradient(180deg, #ffffff, #f8fafc);
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05);
}

.kuota-management-page :deep(.process-stat-grid) {
    gap: 0.9rem;
}

.kuota-management-page :deep(.process-stat) {
    position: relative;
    overflow: hidden;
    min-height: 104px;
    padding: 1rem 1.1rem;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #6366f1;
    border-radius: 20px;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96));
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.04);
}

.kuota-management-page :deep(.process-stat::after) {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at top right, rgba(99, 102, 241, 0.08), transparent 40%);
    pointer-events: none;
}

.kuota-management-page :deep(.process-stat > *) {
    position: relative;
    z-index: 1;
}

.kuota-management-page :deep(.process-stat-value) {
    display: block;
    color: #0f172a;
    font-size: 2.15rem;
    font-weight: 900;
    line-height: 1;
    letter-spacing: -0.05em;
}

.kuota-management-page :deep(.process-stat-label) {
    color: #64748b;
    font-size: 0.76rem;
    font-weight: 800;
    line-height: 1.45;
    text-transform: none;
}

.kuota-management-page :deep(.process-stat.success) {
    border-left-color: #10b981;
    background: linear-gradient(180deg, rgba(236, 253, 245, 0.96), rgba(255, 255, 255, 0.98));
}

.kuota-management-page :deep(.process-stat.success::after) {
    background: radial-gradient(circle at top right, rgba(16, 185, 129, 0.1), transparent 40%);
}

.kuota-management-page :deep(.process-stat.success .process-stat-value) {
    color: #10b981;
}

.kuota-management-page :deep(.process-stat.warning) {
    border-left-color: #f97316;
    background: linear-gradient(180deg, rgba(255, 247, 237, 0.96), rgba(255, 255, 255, 0.98));
}

.kuota-management-page :deep(.process-stat.warning::after) {
    background: radial-gradient(circle at top right, rgba(249, 115, 22, 0.1), transparent 40%);
}

.kuota-management-page :deep(.process-stat.warning .process-stat-value) {
    color: #f97316;
}

.kuota-management-page :deep(.process-stat.danger) {
    border-left-color: #ef4444;
    background: linear-gradient(180deg, rgba(254, 242, 242, 0.96), rgba(255, 255, 255, 0.98));
}

.kuota-management-page :deep(.process-stat.danger::after) {
    background: radial-gradient(circle at top right, rgba(239, 68, 68, 0.1), transparent 40%);
}

.kuota-management-page :deep(.process-stat.danger .process-stat-value) {
    color: #ef4444;
}

.kuota-management-page :deep(.detail-header) {
    background:
        radial-gradient(circle at top right, rgba(99, 102, 241, 0.14), transparent 38%),
        linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(239, 246, 255, 0.92));
}

.kuota-management-page :deep(.detail-info p),
.kuota-management-page :deep(.detail-subtitle) {
    color: #64748b;
}

.kuota-management-page :deep(.detail-summary-grid),
.kuota-management-page :deep(.preview-summary-grid) {
    gap: 0.85rem;
}

.kuota-management-page :deep(.summary-card),
.kuota-management-page :deep(.preview-card) {
    min-height: 120px;
    border-radius: 18px;
}

.kuota-management-page :deep(.summary-value),
.kuota-management-page :deep(.preview-value) {
    color: #0f172a;
}

.kuota-management-page :deep(.detail-tabs) {
    padding: 0.45rem;
    border-color: rgba(226, 232, 240, 0.95);
    background: rgba(248, 250, 252, 0.95);
}

.kuota-management-page :deep(.tab-btn) {
    padding: 0.7rem 1rem;
    border: 1px solid transparent;
    background: transparent;
    color: #64748b;
    font-weight: 800;
}

.kuota-management-page :deep(.tab-btn.active) {
    border-color: rgba(99, 102, 241, 0.18);
    background: linear-gradient(135deg, #4f46e5, #6366f1);
}

.kuota-management-page :deep(.table-compact-detail) {
    border-collapse: separate;
    border-spacing: 0;
}

.kuota-management-page :deep(.table-compact-detail thead th) {
    background: #f8fafc;
}

.kuota-management-page :deep(.modal-footer) {
    gap: 0.75rem;
    padding-top: 1rem;
}

.kuota-management-page :deep(.modal-footer .btn-modern),
.kuota-management-page :deep(.modal-footer .btn) {
    min-width: 150px;
    flex: 1 1 150px;
}

@media (max-width: 576px) {
    .kuota-management-page :deep(.summary-card),
    .kuota-management-page :deep(.preview-card) {
        min-height: 108px;
    }

    .kuota-management-page :deep(.modal-footer .btn-modern),
    .kuota-management-page :deep(.modal-footer .btn) {
        min-width: 100%;
    }
}

/* LeaderDashboard-like widget overrides */
.kuota-management-page :deep(.cuti-widget-card) {
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    padding: 0.5rem;
    border-radius: 0.7rem;
    border: none;
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.95), transparent 3rem),
        linear-gradient(135deg, #f8fafc, #ffffff);
}

.kuota-management-page :deep(.summary-card),
.kuota-management-page :deep(.preview-card) {
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: flex-start;
    text-align: left;
    min-height: 68px;
    padding: 0.45rem 0.58rem;
    border-radius: 0.6rem;
    border: 1px solid rgba(148, 163, 184, 0.28);
    background: #f8fafc;
    box-shadow: none;
}

.kuota-management-page :deep(.summary-value),
.kuota-management-page :deep(.preview-value) {
    display: block !important;
    margin: 0.08rem 0 0;
    font-size: 1.25rem;
    font-weight: 900;
    line-height: 1.1;
}

.kuota-management-page :deep(.summary-card > span:first-child),
.kuota-management-page :deep(.preview-card > span:first-child),
.kuota-management-page :deep(.summary-label),
.kuota-management-page :deep(.preview-label) {
    display: block !important;
    font-size: 0.62rem;
    font-weight: 800;
    color: #64748b;
    letter-spacing: 0;
    text-transform: none;
}

.kuota-management-page :deep(.cuti-widget-card__head h6),
.kuota-management-page :deep(.detail-info h5) {
    color: #0f172a;
    font-weight: 950;
}

.kuota-management-page :deep(.leader-mini-stat-grid),
.kuota-management-page :deep(.monthly-anniversary-metrics) {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.24rem;
}

.kuota-management-page :deep(.monthly-anniv-metric),
.kuota-management-page :deep(.process-stat) {
    min-width: 0;
    min-height: 68px;
    padding: 0.45rem 0.58rem;
    border-radius: 0.6rem;
    border: 1px solid rgba(148, 163, 184, 0.28);
    background: #f8fafc;
    box-shadow: none;
}

.kuota-management-page :deep(.monthly-anniv-metric span),
.kuota-management-page :deep(.process-stat-label) {
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 0.62rem;
    font-weight: 800;
    color: #64748b;
}

.kuota-management-page :deep(.monthly-anniv-metric strong),
.kuota-management-page :deep(.process-stat-value) {
    display: block;
    margin: 0.08rem 0 0;
    font-size: 1.25rem;
    font-weight: 900;
    color: #0f172a;
}

.kuota-management-page :deep(.monthly-anniv-metric.success),
.kuota-management-page :deep(.process-stat.success) {
    border-color: rgba(148, 163, 184, 0.28);
    border-left-color: rgba(148, 163, 184, 0.28);
    background: #f0fdf4;
}

.kuota-management-page :deep(.monthly-anniv-metric.danger),
.kuota-management-page :deep(.process-stat.danger) {
    border-color: rgba(148, 163, 184, 0.28);
    border-left-color: rgba(148, 163, 184, 0.28);
    background: #fef2f2;
}

.kuota-management-page :deep(.monthly-anniv-metric.warning),
.kuota-management-page :deep(.process-stat.warning) {
    border-color: rgba(148, 163, 184, 0.28);
    border-left-color: rgba(148, 163, 184, 0.28);
    background: #fffbeb;
}

.kuota-management-page :deep(.monthly-anniv-metric.muted),
.kuota-management-page :deep(.process-stat) {
    border-color: rgba(148, 163, 184, 0.28);
    border-left-color: rgba(148, 163, 184, 0.28);
}

.kuota-management-page :deep(.preview-card.success),
.kuota-management-page :deep(.preview-card.warning),
.kuota-management-page :deep(.preview-card.danger) {
    border-color: rgba(148, 163, 184, 0.28);
}

.kuota-management-page :deep(.preview-card.success) {
    background: #f0fdf4;
}

.kuota-management-page :deep(.preview-card.warning) {
    background: #fffbeb;
}

.kuota-management-page :deep(.preview-card.danger) {
    background: #fef2f2;
}

/* end LeaderDashboard-like overrides */
</style>
