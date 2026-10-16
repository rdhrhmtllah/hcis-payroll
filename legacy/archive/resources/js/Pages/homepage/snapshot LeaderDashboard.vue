<template>
    <div class="leader-dashboard-page">
        <div class="leader-dashboard-container">
            <!-- HERO -->
            <section class="leader-hero">
                <div class="leader-hero__content">
                    <div class="hero-left">
                        <div class="hero-topline">
                            <div class="hero-eyebrow">
                                <i class="bi bi-grid-1x2-fill"></i>
                                Leader Monitoring
                            </div>

                            <span class="period-pill">
                                <i class="bi bi-calendar3"></i>
                                {{ activePeriodLabel }}
                            </span>
                        </div>

                        <div class="hero-title-row">
                            <h1>Team Monitoring Center</h1>
                            <p>
                                Pantau absensi, shift, lembur, cuti, KPI, LMS, dan approval tim dalam satu dashboard
                                awareness yang ringkas untuk leader.
                            </p>
                        </div>

                        <div class="hero-brief-grid">
                            <div>
                                <span>Total Anggota</span>
                                <strong>{{ summary.totalMembers }}</strong>
                                <small>team aktif</small>
                            </div>

                            <div>
                                <span>Hadir Hari Ini</span>
                                <strong>{{ summary.presentToday }}</strong>
                                <small>{{ todayLabel }}</small>
                            </div>

                            <!-- <div>
                                <span>Coverage Shift</span>
                                <strong>{{ summary.shiftCoverage }}%</strong>
                                <small>hari ini</small>
                            </div> -->

                            <div>
                                <span>Antrian Approval</span>
                                <strong>{{ summary.approvalQueue }}</strong>
                                <small>sidebar approval</small>
                            </div>
                        </div>
                    </div>

                    <div class="hero-right">
                        <div class="hero-focus-card">
                            <div class="hero-focus-card__head">
                                <div>
                                    <span>Perlu Ditindak</span>
                                    <strong>{{ actionItemCount }}</strong>
                                    <small>item aktif</small>
                                </div>

                                <div class="hero-focus-icon">
                                    <i class="bi bi-bell-fill"></i>
                                </div>
                            </div>

                            <div class="hero-focus-list">
                                <button
                                    v-for="item in actionItems.slice(0, 3)"
                                    :key="item.id"
                                    type="button"
                                    class="hero-focus-item"
                                    @click="openAction(item)"
                                >
                                    <i :class="item.icon"></i>

                                    <div>
                                        <strong>{{ item.title }}</strong>
                                        <span>{{ item.meta }}</span>
                                    </div>
                                </button>

                                <EmptyState
                                    v-if="!actionItems.length"
                                    compact
                                    light
                                    icon="bi bi-check2-circle"
                                    title="Tidak ada item mendesak"
                                    description="Saat ini belum ada issue yang perlu ditindak."
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- COMPACT STATS -->
            <section class="quick-stat-grid is-leader-focused">
                <button
                    v-for="item in quickStats"
                    :key="item.key"
                    type="button"
                    class="quick-stat-card"
                    :class="[`is-${item.tone}`, { 'is-priority': item.priority }]"
                    @click="setQuickFilter(item.key)"
                >
                    <div class="quick-stat-card__icon">
                        <i :class="item.icon"></i>
                    </div>

                    <div class="quick-stat-card__copy">
                        <strong>{{ item.value }}</strong>
                        <span>{{ item.label }}</span>
                        <small>{{ item.description }}</small>
                    </div>

                    <i class="bi bi-arrow-right-short quick-stat-card__arrow"></i>
                </button>
            </section>

            <!-- INSIGHTS -->
            <section class="team-insight-grid is-balanced">
                <!-- HCIS -->
                <article class="insight-card hcis-insight-card" @click.capture="handleHcisCardInteract">
                    <div class="insight-card__head is-between">
                        <div>
                            <span class="section-label">Absensi</span>
                            <h2>Ringkasan HCIS Team</h2>
                            <small class="hcis-head-note">
                                Issue = tidak hadir pada hari kerja ({{ attendanceIssuePeriodLabel }})
                            </small>
                        </div>

                        <span class="mini-badge" :class="{ 'is-danger': summary.attendanceIssues > 0 }">
                            {{ summary.attendanceIssues }} issue
                        </span>
                    </div>

                    <div class="leader-summary-split">
                        <div
                            class="leader-score-card is-hcis is-clickable"
                            role="button"
                            tabindex="0"
                            @click.stop="openAttendanceStatusDrawer('all')"
                            @keydown.enter.prevent="openAttendanceStatusDrawer('all')"
                            @keydown.space.prevent="openAttendanceStatusDrawer('all')"
                        >
                            <span>Status Hari Ini</span>
                            <strong
                                >{{ formatNumber(summary.presentToday) }}/{{
                                    formatNumber(summary.totalMembers)
                                }}</strong
                            >
                            <small>
                                {{ formatNumber(summary.absentToday) }} tidak hadir ·
                                {{ formatNumber(summary.pendingCheckInToday) }} menunggu check-in ·
                                {{ formatNumber(summary.workingToday) }} sedang bekerja ·
                                {{ formatNumber(summary.leaveToday + summary.noDataToday) }} status lain
                            </small>
                        </div>

                        <div class="leader-mini-stat-grid">
                            <div
                                v-for="item in hcisMonitoringStats"
                                :key="item.key"
                                :class="[`is-${item.tone}`, 'is-clickable-stat']"
                                role="button"
                                tabindex="0"
                                @click.stop="openAttendanceStatusDrawer(item.key)"
                                @keydown.enter.prevent="openAttendanceStatusDrawer(item.key)"
                                @keydown.space.prevent="openAttendanceStatusDrawer(item.key)"
                            >
                                <span>{{ item.label }}</span>
                                <strong>{{ formatNumber(item.value, item.unit || '') }}</strong>
                                <small>{{ item.caption }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="line-chart-card">
                        <div class="line-chart-card__head">
                            <div>
                                <strong>Trend Issue Absensi</strong>
                                <span>7 hari terakhir · {{ hcisTrendRangeLabel }}</span>
                            </div>

                            <small>{{ summary.hcisCritical }} critical</small>
                        </div>

                        <div
                            class="leader-apex-chart is-danger"
                            :class="{ 'is-disabled': !isChartIssueClickable }"
                            @click="handleHcisTrendAreaClick"
                        >
                            <VueApexCharts
                                type="area"
                                height="140"
                                :options="hcisTrendChartOptions"
                                :series="hcisTrendChartSeries"
                            />

                            <div v-if="!isChartIssueClickable" class="chart-interaction-overlay">
                                Memuat trend issue...
                            </div>
                        </div>
                    </div>
                </article>

                <!-- KPI -->
                <article class="insight-card kpi-summary-card">
                    <div class="insight-card__head is-between">
                        <div>
                            <span class="section-label">KPI</span>
                            <h2>Ringkasan KPI Team</h2>
                        </div>

                        <div class="kpi-year-tools">
                            <label class="kpi-year-label">
                                Tahun KPI
                                <select
                                    :value="selectedKpiYear"
                                    class="kpi-year-select"
                                    @change="handleKpiYearChange($event)"
                                >
                                    <option v-for="year in kpiYearOptions" :key="year" :value="year">
                                        {{ year }}
                                    </option>
                                </select>
                            </label>
                            <span class="mini-badge">{{ summary.kpiYear || selectedKpiYear || kpiMonthLabel }}</span>
                        </div>
                    </div>

                    <div class="leader-summary-split">
                        <div
                            class="leader-score-card is-kpi is-clickable"
                            @click.stop="openKpiDetailDrawer(isKpiFullYearView ? 'ytd_avg' : 'monthly_avg')"
                        >
                            <span>AVG Update Performance</span>
                            <strong>{{ formatNumber(kpiPrimaryScore, '%') }}</strong>
                            <small v-if="isKpiFullYearView">Avg Full Year {{ summary.kpiYear }}</small>
                            <small v-else>
                                Avg {{ summary.performancePeriodLabel }} ·
                                <button class="inline-link-btn" @click.stop="openKpiDetailDrawer('ytd_avg')">
                                    YTD {{ formatNumber(summary.ytdAvgScore, '%') }}
                                </button>
                            </small>
                        </div>

                        <div class="leader-mini-stat-grid">
                            <div
                                v-for="item in kpiTeamStats"
                                :key="item.key"
                                :class="[`is-${item.tone}`, 'is-clickable-stat']"
                                @click.stop="openKpiDetailDrawer(item.context)"
                            >
                                <span>{{ item.label }}</span>
                                <strong>{{ formatNumber(item.value, item.unit || '') }}</strong>
                                <small>{{ item.caption }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="line-chart-card">
                        <div class="line-chart-card__head">
                            <div>
                                <strong>Trend Avg Performance Team</strong>
                                <span>{{ summary.kpiTrendRangeLabel }} · {{ summary.formScopeLabel }}</span>
                            </div>

                            <small>{{ formatNumber(kpiPrimaryScore, '%') }}</small>
                        </div>

                        <div class="leader-apex-chart is-purple">
                            <VueApexCharts
                                type="area"
                                height="140"
                                :options="kpiTrendChartOptions"
                                :series="kpiTrendChartSeries"
                            />
                        </div>
                    </div>
                </article>

                <!-- LMS -->
                <article class="insight-card" @click.capture="handleLmsCardInteract">
                    <div class="insight-card__head is-between">
                        <div>
                            <span class="section-label">LMS</span>
                            <h2>Progress Learning Team</h2>
                        </div>

                        <div class="kpi-year-tools lms-year-tools">
                            <label class="kpi-year-label">
                                Tahun LMS
                                <select
                                    :value="selectedLmsYear"
                                    class="kpi-year-select"
                                    @change="handleLmsYearChange($event)"
                                >
                                    <option v-for="year in lmsYearOptions" :key="year" :value="year">
                                        {{ year }}
                                    </option>
                                </select>
                            </label>
                            <span class="mini-badge is-teal">{{ summary.avgLmsProgress }}%</span>
                        </div>
                    </div>

                    <div class="lms-overview">
                        <div
                            class="leader-score-card is-lms is-clickable"
                            role="button"
                            tabindex="0"
                            @click.stop="openLmsDetailDrawer('progress_above_target')"
                            @keydown.enter.prevent="openLmsDetailDrawer('progress_above_target')"
                            @keydown.space.prevent="openLmsDetailDrawer('progress_above_target')"
                        >
                            <span>Avg Progress</span>
                            <strong>{{ formatNumber(summary.avgLmsProgress, '%') }}</strong>
                            <small>{{ formatNumber(summary.totalTrainingPending) }} pending</small>
                        </div>

                        <div class="lms-overview__stats leader-mini-stat-grid lms-mini-stat-grid">
                            <div
                                class="is-info is-clickable-stat"
                                role="button"
                                tabindex="0"
                                @click.stop="openLmsDetailDrawer('assigned')"
                                @keydown.enter.prevent="openLmsDetailDrawer('assigned')"
                                @keydown.space.prevent="openLmsDetailDrawer('assigned')"
                            >
                                <span>Total Training</span>
                                <strong>{{ formatNumber(summary.totalTrainingAssigned) }}</strong>
                                <small>modul terdaftar</small>
                            </div>

                            <div
                                class="is-success is-clickable-stat"
                                role="button"
                                tabindex="0"
                                @click.stop="openLmsDetailDrawer('progress_above_target')"
                                @keydown.enter.prevent="openLmsDetailDrawer('progress_above_target')"
                                @keydown.space.prevent="openLmsDetailDrawer('progress_above_target')"
                            >
                                <span>Selesai</span>
                                <strong>{{ formatNumber(summary.totalTrainingCompleted) }}</strong>
                                <small>training tuntas</small>
                            </div>

                            <div
                                class="is-warning is-clickable-stat"
                                role="button"
                                tabindex="0"
                                @click.stop="openLmsDetailDrawer('lms_not_started')"
                                @keydown.enter.prevent="openLmsDetailDrawer('lms_not_started')"
                                @keydown.space.prevent="openLmsDetailDrawer('lms_not_started')"
                            >
                                <span>Belum Selesai</span>
                                <strong>{{ formatNumber(summary.totalTrainingPending) }}</strong>
                                <small>perlu follow up</small>
                            </div>

                            <div
                                class="is-orange is-clickable-stat"
                                role="button"
                                tabindex="0"
                                @click.stop="openLmsDetailDrawer('progress_below_target')"
                                @keydown.enter.prevent="openLmsDetailDrawer('progress_below_target')"
                                @keydown.space.prevent="openLmsDetailDrawer('progress_below_target')"
                            >
                                <span>Progress dibawah rata-rata</span>
                                <strong>{{ formatNumber(summary.lmsAtRisk) }}</strong>
                                <small>progress &lt; 80%</small>
                            </div>
                        </div>
                    </div>

                    <div class="line-chart-card">
                        <div class="line-chart-card__head">
                            <div>
                                <strong>Trend Durasi Training Team</strong>
                                <span>rata-rata jam selesai per bulan · Jan-Des {{ selectedLmsYear }}</span>
                            </div>

                            <small>{{ formatNumber(summary.lmsAvgMonthlyHours || 0, ' jam') }}</small>
                        </div>

                        <div class="leader-apex-chart is-teal">
                            <VueApexCharts
                                type="area"
                                height="140"
                                :options="lmsTrendChartOptions"
                                :series="lmsTrendChartSeries"
                            />
                        </div>
                    </div>
                </article>
            </section>

            <!-- MAIN -->
            <section class="leader-main-grid">
                <main class="leader-main">
                    <!-- SHIFT VISUALIZATION -->
                    <!-- <section class="shift-board-card" @click.capture="ensureShiftSchedulesLoaded">
                        <div class="section-head">
                            <div>
                                <span class="section-label">Shift Distribution</span>
                                <h2>Distribusi Shift & Kebutuhan Coverage</h2>
                            </div>

                            <div class="date-picker-control">
                                <i class="bi bi-calendar-event"></i>
                                <select v-model="selectedShiftDate" @change="loadShiftBoard(selectedShiftDate)">
                                    <option v-for="date in availableShiftDates" :key="date.value" :value="date.value">
                                        {{ date.label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="shift-board-grid">
                            <EmptyState
                                v-if="shiftSectionLoading"
                                compact
                                icon="bi bi-hourglass-split"
                                title="Memuat distribusi shift"
                                description="Data shift sedang diambil saat panel dibuka..."
                            />

                            <button
                                v-for="shift in selectedDateShiftSummary"
                                :key="shift.key"
                                type="button"
                                class="shift-card"
                                :class="{ 'is-warning': shift.hasGap, 'is-active': selectedShiftKey === shift.key }"
                                @click="openShiftDetail(shift)"
                            >
                                <div class="shift-card__head">
                                    <div>
                                        <strong>{{ shift.name }}</strong>
                                        <span>{{ shift.time }}</span>
                                    </div>

                                    <b>{{ shift.memberCount }}</b>
                                </div>

                                <div class="shift-member-pills is-summary-only">
                                    <span class="shift-summary-pill">
                                        <b>{{ shift.coverageCount }}/{{ shift.minNeed }}</b>
                                        <em>siap coverage</em>
                                    </span>
                                    <small v-if="shift.gapCount > 0" class="shift-summary-pill is-gap">
                                        <b>{{ shift.gapCount }}</b>
                                        <em>butuh follow up</em>
                                    </small>
                                </div>

                                <div class="shift-card__footer">
                                    <span>{{ shift.hasGap ? 'Perlu coverage' : 'Coverage aman' }}</span>
                                    <i :class="shift.hasGap ? 'bi bi-exclamation-circle' : 'bi bi-check2-circle'"></i>
                                </div>
                            </button>

                            <EmptyState
                                v-if="!shiftSectionLoading && !selectedDateShiftSummary.length"
                                compact
                                icon="bi bi-calendar-week"
                                title="Shift kosong"
                                description="Klik card ini untuk memuat distribusi shift."
                            />
                        </div>

                        <div class="selected-shift-detail" v-if="selectedShiftDetail">
                            <div class="selected-shift-detail__head">
                                <div>
                                    <strong>{{ selectedShiftDetail.name }}</strong>
                                    <span>{{ selectedShiftDetail.time }} • {{ selectedShiftDateLabel }}</span>
                                </div>

                                <small :class="{ 'is-warning': selectedShiftDetail.hasGap }">
                                    {{ selectedShiftDetail.hasGap ? 'Coverage perlu dicek' : 'Coverage aman' }}
                                </small>
                            </div>

                            <EmptyState
                                v-if="shiftDetailLoading"
                                compact
                                icon="bi bi-hourglass-split"
                                title="Memuat detail shift"
                                description="Mengambil anggota shift pada halaman yang dipilih..."
                            />

                            <EmptyState
                                v-else-if="!selectedShiftDetail.detailLoaded"
                                compact
                                icon="bi bi-hand-index"
                                title="Klik shift untuk detail"
                                description="Detail anggota akan dimuat saat kartu shift dipilih."
                            />

                            <div v-else class="selected-shift-members">
                                <div
                                    v-for="person in selectedShiftDetail.members"
                                    :key="person.id"
                                    class="shift-person"
                                >
                                    <div class="member-avatar is-sm">{{ person.initials }}</div>

                                    <div>
                                        <strong>{{ person.name }}</strong>
                                        <span>{{ person.attendance.todayLabel }}</span>
                                    </div>

                                    <small :class="attendanceStatusClass(person.attendance.todayStatus)">
                                        {{
                                            person.attendance.todayStatus === 'leave'
                                                ? 'Cuti/Izin'
                                                : person.attendance.todayLabel
                                        }}
                                    </small>
                                </div>
                            </div>

                            <div v-if="selectedShiftDetail.detailLoaded" class="pagination-row">
                                <button
                                    type="button"
                                    class="pagination-btn"
                                    :disabled="!selectedShiftDetail.pagination.has_prev || shiftDetailLoading"
                                    @click="loadShiftDetail(selectedShiftDetailPage - 1, selectedShiftDetail)"
                                >
                                    Prev
                                </button>

                                <span>
                                    Halaman {{ selectedShiftDetail.pagination.current_page }} dari
                                    {{ selectedShiftDetail.pagination.last_page }}
                                </span>

                                <button
                                    type="button"
                                    class="pagination-btn"
                                    :disabled="!selectedShiftDetail.pagination.has_next || shiftDetailLoading"
                                    @click="loadShiftDetail(selectedShiftDetailPage + 1, selectedShiftDetail)"
                                >
                                    Next
                                </button>
                            </div>

                            <div class="coverage-alert" v-if="selectedShiftDetail.hasGap">
                                <i class="bi bi-exclamation-triangle"></i>
                                <span>
                                    Ada anggota tidak hadir/cuti pada shift ini. Leader bisa cek opsi assignment lembur
                                    atau redistribusi anggota dari shift lain.
                                </span>
                            </div>
                        </div>
                    </section> -->

                    <!-- FILTER -->
                    <section ref="teamListSectionRef" class="toolbar-card" @click.capture="ensureMembersLoaded">
                        <div class="toolbar-main">
                            <div class="toolbar-title">
                                <span class="section-label">Team List</span>
                                <h2>Daftar Anggota Tim</h2>
                            </div>

                            <div class="toolbar-actions">
                                <div class="search-box">
                                    <i class="bi bi-search"></i>
                                    <input
                                        v-model="searchKeyword"
                                        type="text"
                                        placeholder="Cari anggota..."
                                        @focus="ensureMembersLoaded"
                                    />
                                </div>

                                <select
                                    v-model="selectedAttendance"
                                    class="filter-control"
                                    @focus="ensureMembersLoaded"
                                >
                                    <option value="all">Semua Absensi</option>
                                    <option value="present">Hadir</option>
                                    <option value="absent">Alpha / Tidak Hadir</option>
                                    <option value="late">Terlambat</option>
                                    <option value="no-checkout">Belum Checkout</option>
                                    <option value="leave">Izin / Cuti / Sakit</option>
                                </select>

                                <select v-model="selectedKpi" class="filter-control" @focus="ensureMembersLoaded">
                                    <option value="all">Semua KPI</option>
                                    <option value="kpi-alert">Ada Alert KPI</option>
                                    <option value="complete">KPI Lengkap</option>
                                </select>

                                <select v-model="selectedLms" class="filter-control" @focus="ensureMembersLoaded">
                                    <option value="all">Semua LMS</option>
                                    <option value="pending">Training Pending</option>
                                    <option value="complete">Training Selesai</option>
                                </select>
                            </div>
                        </div>

                        <div v-if="hasActiveMemberFilters" class="toolbar-active-filters">
                            <span v-for="chip in activeMemberFilterChips" :key="chip.key" class="filter-chip">
                                {{ chip.label }}
                            </span>

                            <button type="button" class="filter-reset-btn" @click.stop="resetMemberFilters">
                                Reset Filter
                            </button>
                        </div>
                    </section>

                    <!-- MEMBER LIST -->
                    <section class="member-list" @click.capture="ensureMembersLoaded">
                        <EmptyState
                            v-if="memberSectionLoading"
                            compact
                            icon="bi bi-hourglass-split"
                            title="Memuat daftar anggota"
                            description="Mengambil data team list saat panel dibuka..."
                        />

                        <EmptyState
                            v-if="!memberSectionLoading && !members.length"
                            icon="bi bi-people"
                            title="Belum ada anggota tim"
                            description="Klik area ini atau gunakan filter untuk memuat data anggota tim."
                        />

                        <EmptyState
                            v-else-if="!memberSectionLoading && !filteredMembers.length"
                            icon="bi bi-search"
                            title="Data tidak ditemukan"
                            description="Coba ubah keyword pencarian atau filter monitoring."
                        />

                        <article
                            v-for="member in filteredMembers"
                            :key="member.id"
                            class="member-card"
                            :class="{ 'is-open': openedMemberId === member.id }"
                        >
                            <button type="button" class="member-row" @click="toggleMember(member)">
                                <div class="member-profile">
                                    <div class="member-avatar">{{ member.initials }}</div>

                                    <div class="member-name-wrap">
                                        <div class="member-name-line d-flex">
                                            <h3>{{ member.name }}</h3>

                                            <div class="member-head-badges">
                                                <span class="priority-pill" :class="`is-${member.urgencyLevel}`">
                                                    {{ member.urgencyLabel }}
                                                </span>

                                                <span class="shift-pill">
                                                    {{ member.shift.name || 'No Shift' }}
                                                    <small>{{ member.shift.time || '-' }}</small>
                                                </span>
                                            </div>
                                        </div>

                                        <p>{{ member.position || '-' }} • {{ member.department || '-' }}</p>
                                    </div>
                                </div>

                                <div class="member-metrics is-monitoring">
                                    <div
                                        class="mini-metric"
                                        :class="
                                            member.hcisRiskCount > 0
                                                ? 'is-danger-soft'
                                                : attendanceStatusClass(member.attendance.todayStatus)
                                        "
                                    >
                                        <strong>{{ member.hcisRiskCount }}</strong>
                                        <span>HCIS Risk</span>
                                    </div>

                                    <div class="mini-metric" :class="member.kpiAlertCount ? 'is-orange' : 'is-purple'">
                                        <strong>{{
                                            member.kpiAlertCount
                                                ? `${member.kpiAlertCount} alert`
                                                : member.kpi.score || '-'
                                        }}</strong>
                                        <span>KPI</span>
                                    </div>

                                    <div
                                        class="mini-metric"
                                        :class="
                                            member.lms.progress < 80
                                                ? 'is-warning'
                                                : member.lms.progress < 100
                                                  ? 'is-info'
                                                  : 'is-success'
                                        "
                                    >
                                        <strong>{{ member.lms.progress }}%</strong>
                                        <span>LMS</span>
                                    </div>
                                </div>

                                <div class="member-row-action">
                                    <span class="row-action-label">{{ member.attendance.todayLabel }}</span>
                                    <i
                                        class="bi bi-chevron-down member-chevron"
                                        :class="{ 'is-open': openedMemberId === member.id }"
                                    ></i>
                                </div>
                            </button>

                            <transition name="accordion">
                                <div v-if="openedMemberId === member.id" class="member-detail">
                                    <div class="detail-top">
                                        <div class="detail-top-copy">
                                            <strong>Prioritas Tindak Lanjut</strong>
                                            <span class="detail-headline">{{ member.prioritySummary.headline }}</span>

                                            <div class="priority-summary-grid">
                                                <div class="priority-summary-item">
                                                    <small>Masalah utama</small>
                                                    <b>{{ member.prioritySummary.mainIssue }}</b>
                                                </div>

                                                <div class="priority-summary-item">
                                                    <small>Dampak jika ditunda</small>
                                                    <b>{{ member.prioritySummary.delayImpact }}</b>
                                                </div>

                                                <div class="priority-summary-item">
                                                    <small>Tindakan pertama</small>
                                                    <b>{{ member.prioritySummary.firstAction }}</b>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="detail-tags">
                                            <span class="tag is-priority" :class="`is-${member.urgencyLevel}`">
                                                {{ member.urgencyLabel }}
                                            </span>

                                            <span
                                                v-if="member.attendance.issues.length || member.attendance.issueCount"
                                                class="tag is-danger"
                                            >
                                                {{ member.attendance.issues.length || member.attendance.issueCount }}
                                                issue absensi
                                            </span>

                                            <span v-if="member.kpi.pendingItems.length" class="tag is-warning">
                                                {{ member.kpi.pendingItems.length }} alert KPI
                                            </span>

                                            <span v-if="member.approvalCount" class="tag is-success">
                                                {{ member.approvalCount }} approval
                                            </span>

                                            <span v-if="isMemberDetailLoading(member.id)" class="tag is-info">
                                                Memuat detail...
                                            </span>

                                            <span v-if="!member.issueCount" class="tag is-info"> Aman </span>
                                        </div>
                                    </div>

                                    <div class="priority-intel-grid">
                                        <section class="priority-intel-card">
                                            <header class="priority-intel-head">
                                                <strong>Kenapa Prioritas Ini {{ member.urgencyLabel }}?</strong>
                                                <span>{{ member.priorityReasons.length }} indikator</span>
                                            </header>

                                            <div class="priority-reason-list">
                                                <article
                                                    v-for="reason in member.priorityReasons"
                                                    :key="`${member.id}-${reason.key}`"
                                                    class="priority-reason-item"
                                                >
                                                    <div>
                                                        <strong>{{ reason.label }}</strong>
                                                        <span>{{ reason.evidence }}</span>
                                                    </div>

                                                    <div class="priority-reason-side">
                                                        <b>{{ reason.valueLabel }}</b>
                                                        <small>+{{ reason.scoreImpact }}</small>
                                                    </div>
                                                </article>
                                            </div>
                                        </section>

                                        <section class="priority-intel-card">
                                            <header class="priority-intel-head">
                                                <strong>Next Best Action</strong>
                                                <span>{{ member.nextActions.length }} aksi</span>
                                            </header>

                                            <div class="priority-action-list">
                                                <button
                                                    v-for="action in member.nextActions"
                                                    :key="`${member.id}-${action.id}`"
                                                    type="button"
                                                    class="priority-action-item"
                                                    @click.stop="handleMemberPriorityAction(member, action)"
                                                >
                                                    <div>
                                                        <strong>{{ action.label }}</strong>
                                                        <span>{{ action.impact }}</span>
                                                    </div>

                                                    <div class="priority-action-side">
                                                        <small>{{ action.urgency }}</small>
                                                        <i class="bi bi-arrow-up-right"></i>
                                                    </div>
                                                </button>
                                            </div>
                                        </section>
                                    </div>

                                    <div v-if="isMemberDetailLoading(member.id)" class="detail-loading">
                                        <div class="detail-loading__head">
                                            <i class="bi bi-hourglass-split"></i>
                                            <span>Memuat detail anggota...</span>
                                        </div>
                                        <div class="detail-loading__grid">
                                            <div class="detail-loading__card"></div>
                                            <div class="detail-loading__card"></div>
                                            <div class="detail-loading__card"></div>
                                            <div class="detail-loading__card"></div>
                                        </div>
                                    </div>

                                    <div v-else class="detail-grid">
                                        <!-- HCIS DETAIL -->
                                        <section class="detail-panel detail-panel--wide">
                                            <PanelHead
                                                icon="bi bi-calendar-check"
                                                title="HCIS Absensi & Lembur"
                                                :count="`${member.attendance.issues.length || member.attendance.issueCount} issue`"
                                            />

                                            <div class="hcis-detail-layout">
                                                <div>
                                                    <div class="hcis-priority-row">
                                                        <div class="hcis-priority-item">
                                                            <span>Status hari ini</span>
                                                            <strong>{{ member.attendance.todayLabel }}</strong>
                                                        </div>

                                                        <div
                                                            class="hcis-priority-item"
                                                            :class="{
                                                                'is-attention':
                                                                    member.attendance.issues.length ||
                                                                    member.attendance.issueCount,
                                                            }"
                                                        >
                                                            <span>Issue aktif</span>
                                                            <strong>{{
                                                                member.attendance.issues.length ||
                                                                member.attendance.issueCount
                                                            }}</strong>
                                                        </div>

                                                        <div class="hcis-priority-item">
                                                            <span>Total lembur</span>
                                                            <strong>{{ member.overtime.totalHours }} jam</strong>
                                                        </div>
                                                    </div>

                                                    <div class="metric-grid metric-grid--hcis">
                                                        <div>
                                                            <span>Check In</span>
                                                            <strong>{{ member.attendance.checkIn || '-' }}</strong>
                                                        </div>

                                                        <div>
                                                            <span>Check Out</span>
                                                            <strong>{{ member.attendance.checkOut || '-' }}</strong>
                                                        </div>

                                                        <div>
                                                            <span>Hari Kerja</span>
                                                            <strong>{{ member.attendance.workDays }}</strong>
                                                        </div>

                                                        <div :class="{ 'is-danger': member.attendance.alphaCount > 0 }">
                                                            <span>Alpha</span>
                                                            <strong>{{ member.attendance.alphaCount }}</strong>
                                                        </div>

                                                        <div>
                                                            <span>Sakit</span>
                                                            <strong>{{ member.attendance.sickDays }}</strong>
                                                        </div>

                                                        <div>
                                                            <span>Izin</span>
                                                            <strong>{{ member.attendance.permissionDays }}</strong>
                                                        </div>

                                                        <div>
                                                            <span>Cuti</span>
                                                            <strong>{{ member.attendance.leaveDays }}</strong>
                                                        </div>

                                                        <div :class="{ 'is-warning': member.attendance.lateCount > 0 }">
                                                            <span>Terlambat</span>
                                                            <strong>{{ member.attendance.lateCount }}x</strong>
                                                        </div>

                                                        <div
                                                            :class="{ 'is-warning': member.attendance.lateMinutes > 0 }"
                                                        >
                                                            <span>Menit Telat</span>
                                                            <strong>{{ member.attendance.lateMinutes }}</strong>
                                                        </div>

                                                        <div>
                                                            <span>Pulang Cepat</span>
                                                            <strong>{{ member.attendance.earlyOutCount }}x</strong>
                                                        </div>

                                                        <div
                                                            :class="{
                                                                'is-warning': member.attendance.noCheckoutCount > 0,
                                                            }"
                                                        >
                                                            <span>No Checkout</span>
                                                            <strong>{{ member.attendance.noCheckoutCount }}x</strong>
                                                        </div>

                                                        <div :class="{ 'is-info': member.overtime.totalHours > 0 }">
                                                            <span>Lembur</span>
                                                            <strong>{{ member.overtime.totalHours }} Jam</strong>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="attendance-trend-card">
                                                    <div class="subsection-head">
                                                        <strong>Trend 7 Hari</strong>
                                                        <span>Hadir / terlambat / issue</span>
                                                    </div>

                                                    <div class="trend-legend">
                                                        <span class="is-success">Hadir</span>
                                                        <span class="is-warning">Terlambat</span>
                                                        <span class="is-danger">Issue</span>
                                                    </div>

                                                    <div class="daily-status-row">
                                                        <div
                                                            v-for="day in member.attendance.dailyLog"
                                                            :key="day.date"
                                                            class="daily-status-cell"
                                                            :class="`is-${day.tone}`"
                                                            :title="`${day.date} - ${day.label}`"
                                                        >
                                                            <span>{{ day.day }}</span>
                                                        </div>
                                                    </div>

                                                    <div class="mini-overtime-chart">
                                                        <div
                                                            v-for="item in member.overtime.weeklyTrend"
                                                            :key="item.day"
                                                            :style="{
                                                                height: `${Math.max(8, safePercent(item.hours * 16))}%`,
                                                            }"
                                                            :title="`${item.day}: ${item.hours} jam`"
                                                        ></div>
                                                    </div>

                                                    <small>Chart kecil menunjukkan jam lembur per hari.</small>
                                                </div>
                                            </div>

                                            <div class="hcis-issue-stack">
                                                <IssueList
                                                    :items="member.attendance.issues"
                                                    empty-title="Absensi aman"
                                                    empty-description="Tidak ada issue absensi pada periode ini."
                                                />
                                            </div>
                                        </section>

                                        <!-- KPI DETAIL -->
                                        <section class="detail-panel detail-panel--kpi">
                                            <PanelHead
                                                icon="bi bi-bar-chart-line"
                                                title="KPI"
                                                :count="memberKpiPanelView(member).performancePeriod"
                                            />

                                            <div class="kpi-panel-toolbar">
                                                <div class="kpi-panel-toolbar__filters">
                                                    <select
                                                        class="kpi-panel-select"
                                                        :value="getMemberKpiPanelState(member).draftYear"
                                                        @change="setMemberKpiDraftYear(member, $event?.target?.value)"
                                                    >
                                                        <option
                                                            v-for="year in kpiYearOptions"
                                                            :key="`${member.id}-year-${year}`"
                                                            :value="year"
                                                        >
                                                            Tahun {{ year }}
                                                        </option>
                                                    </select>

                                                    <select
                                                        class="kpi-panel-select"
                                                        :value="getMemberKpiPanelState(member).draftPeriod"
                                                        @change="setMemberKpiDraftPeriod(member, $event?.target?.value)"
                                                    >
                                                        <option
                                                            v-for="periodOpt in memberKpiPeriodOptions(member)"
                                                            :key="`${member.id}-period-${periodOpt.value}`"
                                                            :value="periodOpt.value"
                                                        >
                                                            {{ periodOpt.label }}
                                                        </option>
                                                    </select>
                                                </div>

                                                <button
                                                    type="button"
                                                    class="kpi-panel-apply-btn"
                                                    :disabled="
                                                        isMemberKpiPanelLoading(member.id) ||
                                                        !memberKpiPanelHasPendingChanges(member)
                                                    "
                                                    @click="applyMemberKpiPanelFilter(member)"
                                                >
                                                    {{ isMemberKpiPanelLoading(member.id) ? 'Memuat...' : 'Terapkan' }}
                                                </button>
                                            </div>

                                            <div
                                                v-if="getMemberKpiPanelError(member.id)"
                                                class="drawer-error-box is-compact"
                                            >
                                                <strong>{{ getMemberKpiPanelError(member.id) }}</strong>
                                            </div>

                                            <div class="kpi-period-box">
                                                <div>
                                                    <span>Periode KPI</span>
                                                    <strong>{{ memberKpiPanelView(member).performancePeriod }}</strong>
                                                    <small>{{ memberKpiPanelView(member).parameterPeriod }}</small>
                                                </div>

                                                <div>
                                                    <span>Skor</span>
                                                    <strong>{{ memberKpiPanelView(member).score || '-' }}</strong>
                                                    <small>{{
                                                        memberKpiPanelView(member).grade || 'Periode berjalan'
                                                    }}</small>
                                                </div>

                                                <div>
                                                    <span>Achievement</span>
                                                    <strong>{{ memberKpiPanelView(member).achievement }}%</strong>
                                                    <small>Target periode</small>
                                                </div>
                                            </div>

                                            <div
                                                v-if="memberKpiPanelView(member).pendingItems.length"
                                                class="alert-list"
                                            >
                                                <div
                                                    v-for="item in memberKpiPanelView(member).pendingItems"
                                                    :key="item.id"
                                                    class="module-alert is-warning"
                                                >
                                                    <i class="bi bi-exclamation-circle"></i>
                                                    <div>
                                                        <strong>{{ item.title }}</strong>
                                                        <span>{{ item.description }}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div v-else class="module-alert is-success">
                                                <i class="bi bi-check2-circle"></i>
                                                <div>
                                                    <strong>KPI lengkap</strong>
                                                    <span>Tidak ada alert KPI pada periode ini.</span>
                                                </div>
                                            </div>

                                            <div class="subsection-head">
                                                <strong>Achievement Bulanan</strong>
                                                <span>6 bulan terakhir</span>
                                            </div>

                                            <div class="achievement-chart">
                                                <div
                                                    v-for="item in memberKpiPanelView(member).monthlyAchievement"
                                                    :key="item.month"
                                                >
                                                    <span>{{ item.month }}</span>
                                                    <div>
                                                        <b :style="{ height: `${safePercent(item.value)}%` }"></b>
                                                    </div>
                                                    <strong>{{ item.value }}</strong>
                                                </div>
                                            </div>

                                            <div class="parameter-list">
                                                <div
                                                    v-for="param in memberKpiPanelView(member).parameters"
                                                    :key="param.name"
                                                    class="parameter-row"
                                                >
                                                    <div>
                                                        <span>{{ param.name }}</span>
                                                        <strong>{{ param.score }}</strong>
                                                    </div>

                                                    <div class="progress-line">
                                                        <div
                                                            class="progress-line__fill is-purple"
                                                            :style="{ width: `${safePercent(param.score)}%` }"
                                                        ></div>
                                                    </div>
                                                </div>

                                                <EmptyState
                                                    v-if="!memberKpiPanelView(member).parameters.length"
                                                    compact
                                                    icon="bi bi-list-check"
                                                    title="Parameter kosong"
                                                    description="Parameter KPI belum tersedia."
                                                />
                                            </div>
                                        </section>

                                        <!-- LMS DETAIL -->
                                        <section class="detail-panel detail-panel--lms">
                                            <PanelHead
                                                icon="bi bi-journal-bookmark"
                                                title="LMS"
                                                :count="`${member.lms.completed}/${member.lms.total} selesai`"
                                            />

                                            <div class="metric-grid is-three">
                                                <div>
                                                    <span>Total Training</span>
                                                    <strong>{{ member.lms.total }}</strong>
                                                </div>

                                                <div>
                                                    <span>Sudah Selesai</span>
                                                    <strong>{{ member.lms.completed }}</strong>
                                                </div>

                                                <div>
                                                    <span>Belum Selesai</span>
                                                    <strong>{{ member.lms.pending }}</strong>
                                                </div>

                                                <div>
                                                    <span>Progress</span>
                                                    <strong>{{ member.lms.progress }}%</strong>
                                                </div>

                                                <div>
                                                    <span>Avg Score</span>
                                                    <strong>{{ member.lms.averageScore || '-' }}</strong>
                                                </div>

                                                <div>
                                                    <span>Learning Gain</span>
                                                    <strong>{{
                                                        member.lms.learningGain ? `+${member.lms.learningGain}%` : '-'
                                                    }}</strong>
                                                </div>
                                            </div>

                                            <div class="progress-line is-lg">
                                                <div
                                                    class="progress-line__fill is-teal"
                                                    :style="{ width: `${safePercent(member.lms.progress)}%` }"
                                                ></div>
                                            </div>

                                            <div class="training-list">
                                                <div
                                                    v-for="training in member.lms.trainings"
                                                    :key="training.title"
                                                    class="training-row"
                                                >
                                                    <div>
                                                        <strong>{{ training.title }}</strong>
                                                        <span>{{ training.deadline || 'Tidak ada deadline' }}</span>
                                                    </div>

                                                    <span class="training-status" :class="`is-${training.status}`">
                                                        {{ training.statusLabel }}
                                                    </span>
                                                </div>

                                                <EmptyState
                                                    v-if="!member.lms.trainings.length"
                                                    compact
                                                    icon="bi bi-journal"
                                                    title="Training kosong"
                                                    description="Belum ada training untuk anggota ini."
                                                />
                                            </div>
                                        </section>

                                        <!-- CUTI / APPROVAL -->
                                        <section class="detail-panel detail-panel--approval">
                                            <PanelHead
                                                icon="bi bi-check2-square"
                                                title="Cuti, Lembur & Approval"
                                                :count="`${member.approvalCount} approval`"
                                            />

                                            <div class="metric-grid">
                                                <div>
                                                    <span>Sisa Cuti</span>
                                                    <strong
                                                        >{{ member.leave.remaining }}/{{ member.leave.quota }}</strong
                                                    >
                                                </div>

                                                <div>
                                                    <span>Cuti Terpakai</span>
                                                    <strong>{{ member.leave.used }}</strong>
                                                </div>

                                                <div>
                                                    <span>Total Lembur</span>
                                                    <strong>{{ member.overtime.totalHours }} Jam</strong>
                                                </div>

                                                <div>
                                                    <span>Sebelum Kerja</span>
                                                    <strong>{{ member.overtime.beforeWorkHours }} Jam</strong>
                                                </div>

                                                <div>
                                                    <span>Setelah Kerja</span>
                                                    <strong>{{ member.overtime.afterWorkHours }} Jam</strong>
                                                </div>

                                                <div>
                                                    <span>Hari Libur</span>
                                                    <strong>{{ member.overtime.holidayHours }} Jam</strong>
                                                </div>
                                            </div>

                                            <div class="approval-list">
                                                <div
                                                    v-for="approval in member.approvals"
                                                    :key="approval.id"
                                                    class="approval-item"
                                                    :class="`is-${approval.tone}`"
                                                >
                                                    <i :class="approval.icon"></i>

                                                    <div>
                                                        <strong>{{ approval.title }}</strong>
                                                        <span>{{ approval.description }}</span>
                                                    </div>

                                                    <small :class="approvalStatusClass(approval)">
                                                        {{ approval.statusLabel }}
                                                    </small>
                                                </div>

                                                <EmptyState
                                                    v-if="!member.approvalCount"
                                                    compact
                                                    icon="bi bi-check2-circle"
                                                    title="Tidak ada approval"
                                                    description="Tidak ada pengajuan yang menunggu approval."
                                                />
                                            </div>
                                        </section>
                                    </div>
                                </div>
                            </transition>
                        </article>

                        <div v-if="memberSectionLoaded" class="pagination-row is-tight">
                            <button
                                type="button"
                                class="pagination-btn"
                                :disabled="!memberSectionPagination.has_prev || memberSectionLoading"
                                @click="goToMemberPage(-1)"
                            >
                                Prev
                            </button>

                            <span>
                                Halaman {{ memberSectionPagination.current_page }} /
                                {{ memberSectionPagination.last_page }}
                            </span>

                            <button
                                type="button"
                                class="pagination-btn"
                                :disabled="!memberSectionPagination.has_next || memberSectionLoading"
                                @click="goToMemberPage(1)"
                            >
                                Next
                            </button>
                        </div>
                    </section>

                    <section
                        ref="teamCalendarPanelRef"
                        class="team-calendar-panel my-2"
                        tabindex="0"
                        @click.capture="ensureTeamCalendarMonthLoaded"
                        @focusin.capture="ensureTeamCalendarMonthLoaded"
                    >
                        <div class="team-calendar-panel__head">
                            <div>
                                <span class="section-label">Kalender Cutoff Tim</span>
                                <h2>Pantauan Harian Tim</h2>
                            </div>

                            <small class="team-calendar-panel__meta">
                                {{ teamCalendarMonthMeta.cutoff_label || '-' }}
                            </small>
                        </div>

                        <EmptyState
                            v-if="teamCalendarMonthLoading"
                            compact
                            icon="bi bi-hourglass-split"
                            title="Memuat kalender cutoff tim"
                            description="Data kalender bulanan sedang diambil dari backend..."
                        />

                        <div v-else-if="teamCalendarMonthError" class="drawer-error-box">
                            <strong>{{ teamCalendarMonthError }}</strong>
                            <button type="button" class="pagination-btn" @click="retryTeamCalendarMonthLoad">
                                Coba lagi
                            </button>
                        </div>

                        <EmptyState
                            v-else-if="!teamCalendarMonthLoaded"
                            compact
                            icon="bi bi-calendar2-week"
                            title="Klik untuk memuat kalender"
                            description="Kalender cutoff tim diambil saat section ini dibuka."
                        />

                        <EmptyState
                            v-else-if="!teamCalendarDays.length"
                            compact
                            icon="bi bi-calendar2"
                            title="Belum ada data kalender"
                            description="Data absensi tim belum tersedia pada periode cutoff ini."
                        />

                        <template v-else>
                            <div class="team-calendar-filter-row">
                                <button
                                    v-for="item in teamCalendarFilterOptions"
                                    :key="item.key"
                                    type="button"
                                    class="team-calendar-filter-chip"
                                    :class="{ 'is-active': teamCalendarActiveFilter === item.key }"
                                    @click="setTeamCalendarFilter(item.key)"
                                >
                                    {{ item.label }}
                                    <b>{{ item.count }}</b>
                                </button>
                            </div>

                            <div class="team-calendar-summary-row">
                                <div class="team-calendar-summary-pill is-success">
                                    <strong>{{ teamCalendarStats.present_count || 0 }}</strong>
                                    <span>Hadir</span>
                                </div>
                                <div class="team-calendar-summary-pill is-warning">
                                    <strong>{{ teamCalendarStats.late_count || 0 }}</strong>
                                    <span>Terlambat</span>
                                </div>
                                <div class="team-calendar-summary-pill is-danger">
                                    <strong>{{ teamCalendarStats.issue_day_count || 0 }}</strong>
                                    <span>Issue</span>
                                </div>
                                <div class="team-calendar-summary-pill is-info">
                                    <strong>{{ teamCalendarStats.izin_cuti_count || 0 }}</strong>
                                    <span>Izin/Cuti</span>
                                </div>
                            </div>

                            <div class="team-calendar-grid">
                                <button
                                    v-for="day in teamCalendarFilteredDays"
                                    :key="day.date"
                                    type="button"
                                    class="team-calendar-day"
                                    :class="[
                                        `is-${day.primary_status_tone || 'default'}`,
                                        { 'is-today': day.is_today, 'has-problem': day.has_problem },
                                    ]"
                                    @click="openTeamCalendarDayDetail(day)"
                                >
                                    <div class="team-calendar-day__head">
                                        <div>
                                            <strong>{{ day.day_number }}</strong>
                                            <span>{{ day.day_name }}</span>
                                        </div>
                                        <small>{{ day.primary_status_label || '-' }}</small>
                                    </div>

                                    <div class="team-calendar-day__stats">
                                        <span>
                                            Team kerja
                                            <b>{{ day.team_working_count || 0 }}</b>
                                        </span>
                                        <span>
                                            Issue
                                            <b>{{ day.issue_count || 0 }}</b>
                                        </span>
                                    </div>
                                </button>
                            </div>

                            <EmptyState
                                v-if="!teamCalendarFilteredDays.length"
                                compact
                                icon="bi bi-search"
                                title="Tidak ada tanggal pada filter ini"
                                description="Ubah filter kalender untuk melihat tanggal lainnya."
                            />
                        </template>
                    </section>
                </main>

                <!-- SIDEBAR -->
                <aside class="leader-side">
                    <section class="side-card">
                        <div class="side-card__head">
                            <div>
                                <span class="section-label">Action Center</span>
                                <h2>Perlu Ditindak</h2>
                            </div>

                            <div style="display: flex; align-items: center; gap: 0.55rem">
                                <span class="side-count">{{ actionItemCount }}</span>
                                <button
                                    type="button"
                                    class="pagination-btn"
                                    :disabled="needsActionLoading"
                                    @click="refreshNeedsAction"
                                >
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                        </div>

                        <div class="action-list is-scrollable">
                            <EmptyState
                                v-if="needsActionLoading"
                                compact
                                icon="bi bi-hourglass-split"
                                title="Memuat action center"
                                description="Mengambil issue HCIS dan KPI terbaru tim..."
                            />

                            <EmptyState
                                v-else-if="needsActionError"
                                compact
                                icon="bi bi-exclamation-triangle"
                                title="Gagal memuat action center"
                                :description="needsActionError"
                            />

                            <template v-else>
                                <button
                                    v-for="action in actionItems"
                                    :key="action.id"
                                    type="button"
                                    class="action-card"
                                    :class="`is-${action.tone}`"
                                    @click="openAction(action)"
                                >
                                    <div class="action-card__icon">
                                        <i :class="action.icon"></i>
                                    </div>

                                    <div class="action-card__body">
                                        <strong>{{ action.title }}</strong>
                                        <span>{{ action.description }}</span>
                                        <div
                                            v-if="
                                                action.category === 'hcis' &&
                                                (action.caseBreakdown.no_checkin > 0 ||
                                                    action.caseBreakdown.no_checkout > 0 ||
                                                    action.caseBreakdown.late > 0 ||
                                                    action.caseBreakdown.no_data > 0)
                                            "
                                            class="action-case-row"
                                        >
                                            <span
                                                v-if="action.caseBreakdown.no_checkin > 0"
                                                class="action-case-chip is-danger"
                                            >
                                                No check-in {{ action.caseBreakdown.no_checkin }}
                                            </span>
                                            <span
                                                v-if="action.caseBreakdown.no_checkout > 0"
                                                class="action-case-chip is-warning"
                                            >
                                                No check-out {{ action.caseBreakdown.no_checkout }}
                                            </span>
                                            <span v-if="action.caseBreakdown.late > 0" class="action-case-chip is-orange">
                                                Terlambat {{ action.caseBreakdown.late }}
                                            </span>
                                            <span v-if="action.caseBreakdown.no_data > 0" class="action-case-chip is-info">
                                                Belum ada data {{ action.caseBreakdown.no_data }}
                                            </span>
                                        </div>
                                        <small
                                            v-if="action.category === 'hcis' && action.caseDates.length"
                                            class="action-case-dates"
                                        >
                                            Contoh tanggal: {{ action.caseDates.join(', ') }}
                                        </small>
                                        <small>{{ action.meta }}</small>
                                    </div>

                                    <i class="bi bi-arrow-right-short"></i>
                                </button>

                                <EmptyState
                                    v-if="!actionItems.length"
                                    compact
                                    icon="bi bi-check2-circle"
                                    title="Tidak ada tindak lanjut"
                                    description="Belum ada issue aktif dari tim."
                                />
                            </template>
                        </div>

                        <div v-if="needsActionLoaded" class="pagination-row is-tight">
                            <button
                                type="button"
                                class="pagination-btn"
                                :disabled="!needsActionPagination.has_prev || needsActionLoading"
                                @click="goToNeedsActionPage(-1)"
                            >
                                <i class="bi bi-chevron-left"></i>
                                Prev
                            </button>

                            <span>
                                Halaman {{ needsActionPagination.current_page }} /
                                {{ needsActionPagination.last_page }}
                            </span>

                            <button
                                type="button"
                                class="pagination-btn"
                                :disabled="!needsActionPagination.has_next || needsActionLoading"
                                @click="goToNeedsActionPage(1)"
                            >
                                Next
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </section>

                    <section class="side-card" @click.capture="ensureApprovalsLoaded">
                        <div class="side-card__head">
                            <div>
                                <span class="section-label">Approval</span>
                                <h2>Antrian Approval</h2>
                            </div>

                            <span class="side-count is-success">{{ approvalItemCount }}</span>
                        </div>

                        <div class="approval-side-list">
                            <EmptyState
                                v-if="approvalSectionLoading"
                                compact
                                icon="bi bi-hourglass-split"
                                title="Memuat approval"
                                description="Mengambil antrian approval saat card dibuka..."
                            />

                            <button
                                v-for="item in approvalItems"
                                :key="item.id"
                                type="button"
                                class="approval-side-item"
                                @click="openAction(item)"
                            >
                                <div style="text-align: start">
                                    <strong>{{ item.memberName }}</strong>
                                    <span>{{ item.title }}</span>
                                    <span class="approval-side-item__meta">
                                        {{ item.meta || '-' }}
                                    </span>
                                    <span class="approval-side-item__reason">
                                        {{ truncateText(item.description, 100) || 'Tanpa keterangan.' }}
                                    </span>
                                </div>

                                <small :class="approvalStatusClass(item)">{{ item.statusLabel }}</small>
                            </button>

                            <EmptyState
                                v-if="!approvalSectionLoading && approvalSectionLoaded && !approvalItems.length"
                                compact
                                icon="bi bi-check2-circle"
                                title="Approval kosong"
                                description="Tidak ada antrian approval pada periode ini."
                            />

                            <EmptyState
                                v-if="!approvalSectionLoading && !approvalSectionLoaded"
                                compact
                                icon="bi bi-check2-square"
                                title="Klik untuk memuat approval"
                                description="Antrian approval akan diambil saat card ini dibuka."
                            />

                            <div v-if="approvalSectionLoaded" class="pagination-row is-tight">
                                <button
                                    type="button"
                                    class="pagination-btn"
                                    :disabled="!approvalSectionPagination.has_prev"
                                    @click.stop="goToApprovalPage(-1)"
                                >
                                    Prev
                                </button>

                                <span>
                                    Halaman {{ approvalSectionPagination.current_page }} /
                                    {{ approvalSectionPagination.last_page }}
                                </span>

                                <button
                                    type="button"
                                    class="pagination-btn"
                                    :disabled="!approvalSectionPagination.has_next"
                                    @click.stop="goToApprovalPage(1)"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="side-card" @click.capture="ensureHeatmapLoaded">
                        <div class="side-card__head">
                            <div>
                                <span class="section-label">Heatmap</span>
                                <h2>Pola Issue Tim</h2>
                            </div>

                            <span class="side-count is-warning">{{ heatmapIssueCount }}</span>
                        </div>

                        <div v-if="heatmapSectionLoaded" class="heatmap-controls heatmap-controls--top">
                            <div class="heatmap-controls__top">
                                <div class="heatmap-controls__group">
                                    <button
                                        type="button"
                                        class="pagination-btn"
                                        title="Minggu Sebelumnya"
                                        @click.stop="goToPreviousHeatmapWeek"
                                    >
                                        Prev Week
                                    </button>

                                    <button
                                        type="button"
                                        class="pagination-btn"
                                        title="Minggu Berikutnya"
                                        @click.stop="goToNextHeatmapWeek"
                                    >
                                        Next Week
                                    </button>
                                </div>

                                <div class="heatmap-controls__range">
                                    {{ heatmapDateRangeLabel || 'Loading...' }}
                                </div>
                            </div>
                        </div>

                        <div class="heatmap-board">
                            <EmptyState
                                v-if="heatmapSectionLoading"
                                compact
                                icon="bi bi-hourglass-split"
                                title="Memuat heatmap"
                                description="Mengambil pola issue tim saat card dibuka..."
                            />

                            <template v-else-if="heatmapRows.length">
                                <div v-for="member in heatmapRows" :key="member.id" class="heatmap-row">
                                    <strong>{{ member.name }}</strong>

                                    <div>
                                        <span
                                            v-for="day in member.attendance.dailyLog"
                                            :key="`${member.id}-${day.date}`"
                                            class="heat-cell"
                                            :class="`is-${day.tone}`"
                                            :title="`${member.name} - ${day.date} - ${day.label}`"
                                        ></span>
                                    </div>
                                </div>
                            </template>

                            <EmptyState
                                v-if="!heatmapSectionLoading && heatmapSectionLoaded && !heatmapRows.length"
                                compact
                                icon="bi bi-grid-3x3-gap"
                                title="Heatmap kosong"
                                description="Tidak ada data heatmap pada periode ini."
                            />

                            <EmptyState
                                v-if="!heatmapSectionLoading && !heatmapSectionLoaded"
                                compact
                                icon="bi bi-grid-3x3-gap"
                                title="Klik untuk memuat heatmap"
                                description="Pola issue tim akan diambil saat card ini dibuka."
                            />
                        </div>

                        <div v-if="heatmapSectionLoaded" class="heatmap-controls heatmap-controls--bottom">
                            <div class="heatmap-controls__bottom">
                                <div class="heatmap-controls__group heatmap-controls__group--page">
                                    <button
                                        type="button"
                                        class="pagination-btn"
                                        :disabled="!heatmapSectionPagination.has_prev"
                                        @click.stop="goToHeatmapPage(-1)"
                                        title="Halaman Sebelumnya"
                                    >
                                        Prev
                                    </button>

                                    <span class="heatmap-controls__page-label">
                                        {{ heatmapSectionPagination.current_page }} /
                                        {{ heatmapSectionPagination.last_page }}
                                    </span>

                                    <button
                                        type="button"
                                        class="pagination-btn"
                                        :disabled="!heatmapSectionPagination.has_next"
                                        @click.stop="goToHeatmapPage(1)"
                                        title="Halaman Berikutnya"
                                    >
                                        Next
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="heatmap-legend">
                            <span><i class="is-success"></i> Hadir</span>
                            <span><i class="is-warning"></i> Telat</span>
                            <span><i class="is-danger"></i> Issue</span>
                            <span><i class="is-info"></i> Cuti/Izin</span>
                        </div>
                    </section>

                    <section class="side-card" @click.capture="ensureTrainingLoaded">
                        <div class="side-card__head">
                            <div>
                                <span class="section-label">Learning</span>
                                <h2>Training Pending</h2>
                            </div>

                            <span class="side-count is-teal">{{ trainingPendingCount }}</span>
                        </div>

                        <div class="training-pending-list">
                            <EmptyState
                                v-if="trainingSectionLoading"
                                compact
                                icon="bi bi-hourglass-split"
                                title="Memuat training pending"
                                description="Mengambil daftar training saat card dibuka..."
                            />

                            <div v-for="item in trainingPendingMembers" :key="item.id" class="training-pending-item">
                                <div>
                                    <strong>{{ item.name }}</strong>
                                    <span>{{ item.lms.completed }}/{{ item.lms.total }} selesai</span>
                                </div>

                                <small>{{ item.lms.progress }}%</small>
                            </div>

                            <EmptyState
                                v-if="
                                    !trainingSectionLoading && trainingSectionLoaded && !trainingPendingMembers.length
                                "
                                compact
                                icon="bi bi-check2-circle"
                                title="LMS aman"
                                description="Tidak ada training pending pada periode ini."
                            />

                            <EmptyState
                                v-if="!trainingSectionLoading && !trainingSectionLoaded"
                                compact
                                icon="bi bi-journal-bookmark"
                                title="Klik untuk memuat training"
                                description="Daftar training pending akan diambil saat card ini dibuka."
                            />
                        </div>

                        <div v-if="trainingSectionLoaded" class="pagination-row is-tight">
                            <button
                                type="button"
                                class="pagination-btn"
                                :disabled="!trainingSectionPagination.has_prev"
                                @click.stop="goToTrainingPage(-1)"
                            >
                                Prev
                            </button>

                            <span>
                                Halaman {{ trainingSectionPagination.current_page }} /
                                {{ trainingSectionPagination.last_page }}
                            </span>

                            <button
                                type="button"
                                class="pagination-btn"
                                :disabled="!trainingSectionPagination.has_next"
                                @click.stop="goToTrainingPage(1)"
                            >
                                Next
                            </button>
                        </div>
                    </section>
                </aside>
            </section>

            <transition name="drawer-fade">
                <div
                    v-if="teamCalendarDayDetailDrawerOpen"
                    class="attendance-issue-drawer-backdrop"
                    @click="closeTeamCalendarDayDetailDrawer"
                ></div>
            </transition>

            <aside class="attendance-issue-drawer" :class="{ 'is-open': teamCalendarDayDetailDrawerOpen }">
                <div class="attendance-issue-drawer__head">
                    <div>
                        <span class="section-label">Detail Kalender Tim</span>
                        <h3>{{ teamCalendarDayDetailDayLabel }}</h3>
                        <small>
                            Team kerja {{ teamCalendarDayDetailMeta.team_working_count || 0 }} ·
                            {{ teamCalendarDayDetailMeta.issue_count || 0 }} issue
                        </small>
                    </div>

                    <button type="button" class="drawer-close-btn" @click="closeTeamCalendarDayDetailDrawer">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="attendance-issue-drawer__body">
                    <EmptyState
                        v-if="teamCalendarDayDetailLoading"
                        compact
                        icon="bi bi-hourglass-split"
                        title="Memuat detail tanggal"
                        description="Data detail tim pada tanggal ini sedang diambil..."
                    />

                    <div v-else-if="teamCalendarDayDetailError" class="drawer-error-box">
                        <strong>{{ teamCalendarDayDetailError }}</strong>
                        <button type="button" class="pagination-btn" @click="retryTeamCalendarDayDetailLoad">
                            Coba lagi
                        </button>
                    </div>

                    <template v-else-if="teamCalendarDayDetailRows.length">
                        <article
                            v-for="item in teamCalendarDayDetailRows"
                            :key="`team-calendar-day-${item.id}`"
                            class="attendance-issue-card"
                        >
                            <div class="attendance-issue-card__head">
                                <div>
                                    <strong>{{ item.name }}</strong>
                                    <span>{{ item.position || '-' }} · {{ item.department || '-' }}</span>
                                </div>
                                <small>{{ item.shift?.name || 'No Shift' }} · {{ item.shift?.time || '-' }}</small>
                            </div>

                            <div class="attendance-issue-card__meta">
                                <span>{{ item.attendance?.label || '-' }}</span>
                                <span>CI: {{ item.attendance?.check_in || '-' }}</span>
                                <span>CO: {{ item.attendance?.check_out || '-' }}</span>
                            </div>

                            <div class="attendance-issue-pill-wrap">
                                <span class="attendance-issue-pill" :class="`is-${item.attendance?.tone || 'default'}`">
                                    {{ item.attendance?.label || '-' }}
                                    <small
                                        v-if="
                                            item.attendance?.status === 'late' &&
                                            Number(item.attendance?.late_minutes || 0) > 0
                                        "
                                    >
                                        {{ item.attendance?.late_minutes || 0 }} menit
                                    </small>
                                </span>
                                <span
                                    v-for="issue in item.issues || []"
                                    :key="`issue-${item.id}-${issue.key}`"
                                    class="attendance-issue-pill"
                                    :class="`is-${issue.tone || 'warning'}`"
                                >
                                    {{ issue.label }}
                                    <small v-if="issue.meta">{{ issue.meta }}</small>
                                </span>
                            </div>
                        </article>
                    </template>

                    <EmptyState
                        v-else-if="teamCalendarDayDetailDrawerOpen"
                        compact
                        icon="bi bi-search"
                        title="Tidak ada data anggota"
                        description="Tidak ada anggota pada tanggal dan filter ini."
                    />
                </div>

                <div v-if="teamCalendarDayDetailDrawerOpen" class="attendance-issue-drawer__foot">
                    <button
                        type="button"
                        class="pagination-btn"
                        :disabled="teamCalendarDayDetailLoading || !teamCalendarDayDetailPagination.has_prev"
                        @click="goToTeamCalendarDayDetailPage(-1)"
                    >
                        Prev
                    </button>
                    <span>
                        Halaman {{ teamCalendarDayDetailPagination.current_page }} /
                        {{ teamCalendarDayDetailPagination.last_page }}
                    </span>
                    <button
                        type="button"
                        class="pagination-btn"
                        :disabled="teamCalendarDayDetailLoading || !teamCalendarDayDetailPagination.has_next"
                        @click="goToTeamCalendarDayDetailPage(1)"
                    >
                        Next
                    </button>
                </div>
            </aside>

            <transition name="drawer-fade">
                <div
                    v-if="attendanceIssueDrawerOpen"
                    class="attendance-issue-drawer-backdrop"
                    @click="closeAttendanceIssueDrawer"
                ></div>
            </transition>

            <aside class="attendance-issue-drawer" :class="{ 'is-open': attendanceIssueDrawerOpen }">
                <div class="attendance-issue-drawer__head">
                    <div>
                        <span class="section-label">Detail Issue Absensi</span>
                        <h3>{{ attendanceIssueDayLabel }}</h3>
                        <small>{{ attendanceIssueMeta.issue_count || 0 }} issue</small>
                    </div>

                    <button type="button" class="drawer-close-btn" @click="closeAttendanceIssueDrawer">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="attendance-issue-drawer__body">
                    <EmptyState
                        v-if="attendanceIssueLoading"
                        compact
                        icon="bi bi-hourglass-split"
                        title="Memuat detail issue"
                        description="Data issue harian sedang diambil dari backend..."
                    />

                    <div v-else-if="attendanceIssueError" class="drawer-error-box">
                        <strong>{{ attendanceIssueError }}</strong>
                        <button type="button" class="pagination-btn" @click="retryAttendanceIssueLoad">
                            Coba lagi
                        </button>
                    </div>

                    <template v-else-if="attendanceIssueRows.length">
                        <article v-for="item in attendanceIssueRows" :key="item.id" class="attendance-issue-card">
                            <div class="attendance-issue-card__head">
                                <div>
                                    <strong>{{ item.name }}</strong>
                                    <span>{{ item.position || '-' }}</span>
                                </div>

                                <small>{{ item.shift?.name || 'No Shift' }} · {{ item.shift?.time || '-' }}</small>
                            </div>

                            <div class="attendance-issue-card__meta">
                                <span>CI: {{ item.checkIn || '-' }}</span>
                                <span>CO: {{ item.checkOut || '-' }}</span>
                            </div>

                            <div class="attendance-issue-pill-wrap">
                                <span
                                    v-for="issue in item.issues"
                                    :key="`${item.id}-${issue.key}`"
                                    class="attendance-issue-pill"
                                    :class="`is-${issue.tone || 'warning'}`"
                                >
                                    {{ issue.label }}
                                    <small v-if="issue.meta">{{ issue.meta }}</small>
                                </span>
                            </div>
                        </article>
                    </template>

                    <EmptyState
                        v-else-if="attendanceIssueDrawerOpen"
                        compact
                        icon="bi bi-check2-circle"
                        title="Tidak ada issue"
                        description="Tidak ada issue absensi pada tanggal ini."
                    />
                </div>

                <div v-if="attendanceIssueDrawerOpen" class="attendance-issue-drawer__foot">
                    <button
                        type="button"
                        class="pagination-btn"
                        :disabled="attendanceIssueLoading || !attendanceIssuePagination.has_prev"
                        @click="goToAttendanceIssuePage(-1)"
                    >
                        Prev
                    </button>

                    <span>
                        Halaman {{ attendanceIssuePagination.current_page }} / {{ attendanceIssuePagination.last_page }}
                    </span>

                    <button
                        type="button"
                        class="pagination-btn"
                        :disabled="attendanceIssueLoading || !attendanceIssuePagination.has_next"
                        @click="goToAttendanceIssuePage(1)"
                    >
                        Next
                    </button>
                </div>
            </aside>

            <transition name="drawer-fade">
                <div
                    v-if="attendanceStatusDrawerOpen"
                    class="attendance-issue-drawer-backdrop"
                    @click="closeAttendanceStatusDrawer"
                ></div>
            </transition>

            <aside class="attendance-issue-drawer" :class="{ 'is-open': attendanceStatusDrawerOpen }">
                <div class="attendance-issue-drawer__head">
                    <div>
                        <span class="section-label">Detail Status Absensi</span>
                        <h3>Status Hari Ini</h3>
                        <small
                            >{{ attendanceStatusFilterLabel }} ·
                            {{ attendanceStatusPagination.total || 0 }} anggota</small
                        >
                    </div>

                    <button type="button" class="drawer-close-btn" @click="closeAttendanceStatusDrawer">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="attendance-status-drawer-main">
                    <div class="attendance-status-toolbar">
                        <label class="attendance-status-search">
                            <i class="bi bi-search"></i>
                            <input
                                v-model="attendanceStatusSearchInput"
                                type="text"
                                placeholder="Cari nama / jabatan / divisi..."
                                autocomplete="off"
                            />
                        </label>
                        <button
                            v-if="attendanceStatusSearchInput"
                            type="button"
                            class="attendance-status-clear-btn"
                            @click="clearAttendanceStatusSearch"
                        >
                            Reset
                        </button>
                    </div>

                    <div class="attendance-status-filter-row">
                        <button
                            v-for="item in attendanceStatusFilterOptions"
                            :key="item.key"
                            type="button"
                            class="status-filter-chip"
                            :class="{ 'is-active': attendanceStatusActiveFilter === item.key }"
                            @click="setAttendanceStatusFilter(item.key)"
                        >
                            {{ item.label }}
                            <b>{{ item.count }}</b>
                        </button>
                    </div>

                    <div
                        class="attendance-issue-drawer__body"
                        :class="{
                            'is-state-centered':
                                attendanceStatusLoading || (!attendanceStatusError && !attendanceStatusRows.length),
                        }"
                    >
                        <template v-if="attendanceStatusLoading">
                            <div class="attendance-status-skeleton-list">
                                <article
                                    v-for="skeletonIndex in 4"
                                    :key="`status-skeleton-${skeletonIndex}`"
                                    class="attendance-issue-card is-skeleton"
                                >
                                    <div class="skeleton-line is-title"></div>
                                    <div class="skeleton-line"></div>
                                    <div class="skeleton-line is-short"></div>
                                </article>
                            </div>
                        </template>

                        <div v-else-if="attendanceStatusError" class="drawer-error-box">
                            <strong>{{ attendanceStatusError }}</strong>
                            <button type="button" class="pagination-btn" @click="retryAttendanceStatusLoad">
                                Coba lagi
                            </button>
                        </div>

                        <template v-else-if="attendanceStatusRows.length">
                            <article
                                v-for="item in attendanceStatusRows"
                                :key="`attendance-status-${item.id}`"
                                class="attendance-issue-card"
                            >
                                <div class="attendance-issue-card__head">
                                    <div>
                                        <strong>{{ item.name }}</strong>
                                        <span>{{ item.position || '-' }} · {{ item.department || '-' }}</span>
                                    </div>

                                    <small>{{ item.shift?.name || 'No Shift' }} · {{ item.shift?.time || '-' }}</small>
                                </div>

                                <div class="attendance-issue-card__meta">
                                    <span>{{ item.attendance?.todayLabel || '-' }}</span>
                                    <span>CI: {{ item.attendance?.checkIn || '-' }}</span>
                                    <span>CO: {{ item.attendance?.checkOut || '-' }}</span>
                                </div>

                                <div class="attendance-issue-pill-wrap">
                                    <span class="attendance-issue-pill is-info">
                                        {{ statusLabel(item.attendance?.todayStatus || 'no-data') }}
                                    </span>
                                    <span
                                        v-if="Number(item.attendance?.lateCount || 0) > 0"
                                        class="attendance-issue-pill is-warning"
                                    >
                                        Terlambat
                                        <small>{{ item.attendance?.lateMinutes || 0 }} menit</small>
                                    </span>
                                    <span
                                        v-if="Number(item.attendance?.noCheckoutCount || 0) > 0"
                                        class="attendance-issue-pill is-orange"
                                    >
                                        Belum Checkout
                                    </span>
                                    <span
                                        v-if="Number(item.attendance?.earlyOutCount || 0) > 0"
                                        class="attendance-issue-pill is-warning"
                                    >
                                        Pulang Cepat
                                    </span>
                                </div>
                            </article>
                        </template>

                        <EmptyState
                            v-else-if="attendanceStatusDrawerOpen"
                            compact
                            icon="bi bi-search"
                            title="Tidak ada data"
                            description="Tidak ada anggota pada filter ini."
                        />
                    </div>
                </div>

                <div v-if="attendanceStatusDrawerOpen" class="attendance-issue-drawer__foot">
                    <button
                        type="button"
                        class="pagination-btn"
                        :disabled="attendanceStatusLoading || !attendanceStatusPagination.has_prev"
                        @click="goToAttendanceStatusPage(-1)"
                    >
                        Prev
                    </button>

                    <span>
                        Halaman {{ attendanceStatusPagination.current_page }} /
                        {{ attendanceStatusPagination.last_page }}
                    </span>

                    <button
                        type="button"
                        class="pagination-btn"
                        :disabled="attendanceStatusLoading || !attendanceStatusPagination.has_next"
                        @click="goToAttendanceStatusPage(1)"
                    >
                        Next
                    </button>
                </div>
            </aside>

            <transition name="drawer-fade">
                <div
                    v-if="kpiDetailDrawerOpen"
                    class="attendance-issue-drawer-backdrop"
                    @click="closeKpiDetailDrawer"
                ></div>
            </transition>

            <aside class="attendance-issue-drawer" :class="{ 'is-open': kpiDetailDrawerOpen }">
                <div class="attendance-issue-drawer__head">
                    <div>
                        <span class="section-label">Detail KPI Team</span>
                        <h3>{{ kpiDetailMeta.period_label || '-' }}</h3>
                        <small>{{ kpiDetailPagination.total || 0 }} {{ kpiDetailCountLabel }}</small>
                    </div>
                    <button type="button" class="drawer-close-btn" @click="closeKpiDetailDrawer">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="attendance-status-drawer-main">
                    <div class="attendance-status-toolbar">
                        <div class="attendance-status-filter-pills">
                            <span class="mini-badge">Tahun {{ kpiDetailMeta.kpi_year || selectedKpiYear }}</span>
                            <span class="mini-badge" :class="isKpiDetailPastYear ? '' : 'is-teal'">
                                {{
                                    isKpiDetailPastYear
                                        ? 'Scope: Full Year'
                                        : `Cutoff: ${kpiDetailMeta.effective_cutoff_period || '-'}`
                                }}
                            </span>
                        </div>
                        <label class="attendance-status-search">
                            <i class="bi bi-search"></i>
                            <input
                                v-model.trim="kpiDetailSearchInput"
                                type="text"
                                placeholder="Cari nama / jabatan / divisi..."
                            />
                        </label>
                    </div>

                    <div class="attendance-issue-drawer__body">
                        <EmptyState
                            v-if="kpiDetailLoading"
                            compact
                            icon="bi bi-hourglass-split"
                            title="Memuat detail KPI"
                            description="Data KPI per anggota sedang diambil..."
                        />
                        <div v-else-if="kpiDetailError" class="drawer-error-box">
                            <strong>{{ kpiDetailError }}</strong>
                            <button type="button" class="pagination-btn" @click="retryKpiDetailLoad">Coba lagi</button>
                        </div>
                        <template v-else-if="kpiDetailRows.length">
                            <article v-for="item in kpiDetailRows" :key="item.id" class="attendance-issue-card">
                                <template v-if="kpiDetailContext === 'approval_queue'">
                                    <div class="attendance-issue-card__head">
                                        <div>
                                            <strong>{{ item.name }}</strong>
                                            <span>{{ item.position || '-' }} · {{ item.department || '-' }}</span>
                                        </div>
                                        <small>{{ item.updated_at || '-' }}</small>
                                    </div>
                                    <div class="attendance-issue-card__meta">
                                        <span>{{ item.queue_type_label || 'Approval KPI' }}</span>
                                        <span>No: {{ item.transaction_no || '-' }}</span>
                                        <span>Step: {{ item.flow_order || 0 }}</span>
                                    </div>
                                    <div class="attendance-issue-pill-wrap">
                                        <span
                                            class="attendance-issue-pill"
                                            :class="item.queue_type === 'performance_kpi' ? 'is-purple' : 'is-info'"
                                        >
                                            {{ item.queue_type_label || 'Approval KPI' }}
                                        </span>
                                        <span class="attendance-issue-pill is-warning">Menunggu approval leader</span>
                                    </div>
                                </template>
                                <template v-else>
                                    <button
                                        type="button"
                                        class="kpi-member-card-btn"
                                        @click="toggleKpiDetailMember(item)"
                                    >
                                        <div class="attendance-issue-card__head">
                                            <div>
                                                <strong>{{ item.name }}</strong>
                                                <span>{{ item.position || '-' }} · {{ item.department || '-' }}</span>
                                            </div>
                                            <small>
                                                {{ isKpiDetailPastYear ? 'Full Year' : 'YTD' }}
                                                {{
                                                    formatNumber(
                                                        isKpiDetailPastYear
                                                            ? item.full_year_score || 0
                                                            : item.ytd_score || 0,
                                                        '%',
                                                    )
                                                }}
                                            </small>
                                        </div>
                                        <div class="attendance-issue-pill-wrap">
                                            <span class="attendance-issue-pill is-info"
                                                >Form KPI: {{ kpiFormCount(item) }} parameter</span
                                            >
                                            <span class="attendance-issue-pill is-purple">
                                                Performance: {{ kpiPerformanceCount(item) }} item
                                            </span>
                                            <span
                                                class="attendance-issue-pill"
                                                :class="
                                                    item.is_new_joiner
                                                        ? 'is-secondary'
                                                        : item.form_submitted
                                                          ? 'is-success'
                                                          : 'is-warning'
                                                "
                                            >
                                                {{
                                                    item.is_new_joiner
                                                        ? 'Baru bergabung'
                                                        : item.form_submitted
                                                          ? 'Form tersubmit'
                                                          : 'Form belum submit'
                                                }}
                                            </span>
                                            <span v-if="!isKpiDetailPastYear" class="attendance-issue-pill is-teal">
                                                Monthly {{ formatNumber(item.monthly_score_current_cutoff || 0, '%') }}
                                            </span>
                                        </div>
                                        <div class="kpi-member-card__foot">
                                            <span>
                                                {{
                                                    isKpiDetailPastYear
                                                        ? `Full Year ${kpiDetailMeta.kpi_year || selectedKpiYear}: ${formatNumber(item.full_year_score || 0, '%')}`
                                                        : `YTD ${formatNumber(item.ytd_score || 0, '%')} · Cutoff ${kpiDetailMeta.effective_cutoff_period || '-'}`
                                                }}
                                            </span>
                                            <i
                                                class="bi bi-chevron-down"
                                                :class="{
                                                    'is-open':
                                                        kpiDetailOpenedMemberId ===
                                                        String(item.id || '')
                                                            .trim()
                                                            .toUpperCase(),
                                                }"
                                            ></i>
                                        </div>
                                    </button>

                                    <transition name="accordion">
                                        <div
                                            v-if="
                                                kpiDetailOpenedMemberId ===
                                                String(item.id || '')
                                                    .trim()
                                                    .toUpperCase()
                                            "
                                            class="kpi-member-detail-panel"
                                        >
                                            <EmptyState
                                                v-if="isKpiMemberDetailLoading(item.id)"
                                                compact
                                                icon="bi bi-hourglass-split"
                                                title="Memuat detail anggota"
                                                description="Data parameter dan trend sedang diambil..."
                                            />
                                            <div v-else-if="getKpiMemberError(item.id)" class="drawer-error-box">
                                                <strong>{{ getKpiMemberError(item.id) }}</strong>
                                                <button
                                                    type="button"
                                                    class="pagination-btn"
                                                    @click="loadKpiMemberDetail(item.id)"
                                                >
                                                    Coba lagi
                                                </button>
                                            </div>
                                            <template v-else-if="kpiMemberDetailFor(item.id)">
                                                <div
                                                    v-if="kpiMemberDetailFor(item.id)?.summary?.is_new_joiner"
                                                    class="kpi-new-joiner-alert"
                                                >
                                                    Anggota ini baru bergabung pada periode terpilih.
                                                </div>

                                                <div class="attendance-status-filter-pills is-member-detail-mode">
                                                    <button
                                                        v-for="mode in kpiMemberDetailModes"
                                                        :key="`${item.id}-${mode.key}`"
                                                        type="button"
                                                        class="filter-pill"
                                                        :class="{ 'is-active': kpiMemberMode(item.id) === mode.key }"
                                                        @click="setKpiMemberMode(item.id, mode.key)"
                                                    >
                                                        {{ mode.label }}
                                                    </button>

                                                    <select
                                                        v-if="kpiMemberMode(item.id) === 'month'"
                                                        class="kpi-member-period-select"
                                                        :value="kpiMemberPeriod(item.id)"
                                                        @change="setKpiMemberPeriod(item.id, $event?.target?.value)"
                                                    >
                                                        <option
                                                            v-for="monthOpt in kpiMemberMonthOptions(item.id)"
                                                            :key="monthOpt.value"
                                                            :value="monthOpt.value"
                                                        >
                                                            {{ monthOpt.label }}
                                                        </option>
                                                    </select>
                                                </div>

                                                <div class="kpi-member-summary-grid">
                                                    <div>
                                                        <span>Monthly Cutoff</span>
                                                        <strong>{{
                                                            formatNumber(
                                                                kpiMemberDetailFor(item.id)?.summary
                                                                    ?.monthly_score_current_cutoff || 0,
                                                                '%',
                                                            )
                                                        }}</strong>
                                                    </div>
                                                    <div>
                                                        <span>YTD Score</span>
                                                        <strong>{{
                                                            formatNumber(
                                                                kpiMemberDetailFor(item.id)?.summary?.ytd_score || 0,
                                                                '%',
                                                            )
                                                        }}</strong>
                                                    </div>
                                                    <div>
                                                        <span>Full Year</span>
                                                        <strong>{{
                                                            formatNumber(
                                                                kpiMemberDetailFor(item.id)?.summary?.full_year_score ||
                                                                    0,
                                                                '%',
                                                            )
                                                        }}</strong>
                                                    </div>
                                                    <div>
                                                        <span>Skor Terpilih</span>
                                                        <strong>{{
                                                            formatNumber(
                                                                kpiMemberDetailFor(item.id)?.summary?.selected_score ||
                                                                    0,
                                                                '%',
                                                            )
                                                        }}</strong>
                                                    </div>
                                                </div>

                                                <section class="kpi-member-trend-card">
                                                    <div class="kpi-member-trend-card__head">
                                                        <strong>Trend Performance Per Bulan</strong>
                                                        <span
                                                            >Jan - Des
                                                            {{ kpiDetailMeta.kpi_year || selectedKpiYear }}</span
                                                        >
                                                    </div>
                                                    <EmptyState
                                                        v-if="!kpiMemberTrendRows(item.id).length"
                                                        compact
                                                        icon="bi bi-graph-up"
                                                        title="Trend belum tersedia"
                                                        description="Belum ada data performance untuk periode ini."
                                                    />
                                                    <div v-else class="kpi-member-trend-chart">
                                                        <VueApexCharts
                                                            type="line"
                                                            height="170"
                                                            :options="kpiMemberTrendOptions(item.id)"
                                                            :series="kpiMemberTrendSeries(item.id)"
                                                        />
                                                    </div>
                                                </section>

                                                <section class="kpi-member-detail-block">
                                                    <header>
                                                        <strong>Parameter KPI</strong>
                                                        <span
                                                            >{{
                                                                kpiMemberDetailFor(item.id)?.summary
                                                                    ?.form_parameter_count || 0
                                                            }}
                                                            parameter</span
                                                        >
                                                    </header>
                                                    <div
                                                        v-if="kpiMemberDetailFor(item.id)?.parameter_rows?.length"
                                                        class="kpi-member-detail-list"
                                                    >
                                                        <article
                                                            v-for="(param, index) in kpiMemberDetailFor(item.id)
                                                                ?.parameter_rows || []"
                                                            :key="`${item.id}-param-${index}`"
                                                            class="kpi-member-detail-item is-unified"
                                                        >
                                                            <div>
                                                                <strong>{{
                                                                    param.parameter || 'Parameter KPI'
                                                                }}</strong>
                                                                <span>
                                                                    Bobot {{ formatNumber(param.bobot || 0, '%') }} ·
                                                                    Target {{ formatNumber(param.target || 0) }} ·
                                                                    Actual {{ formatNumber(param.actual || 0) }}
                                                                </span>
                                                                <span>
                                                                    Achievement
                                                                    {{ formatNumber(param.achievement || 0, '%') }} ·
                                                                    Score
                                                                    {{ formatNumber(param.score || 0, '%') }}
                                                                </span>
                                                            </div>
                                                            <div class="kpi-parameter-status-stack">
                                                                <span class="attendance-issue-pill is-purple">
                                                                    {{ param.status || 'N/A' }}
                                                                </span>
                                                                <span
                                                                    class="attendance-issue-pill"
                                                                    :class="
                                                                        String(
                                                                            param.form_status_approval || '',
                                                                        ).toUpperCase() === 'Y'
                                                                            ? 'is-success'
                                                                            : 'is-warning'
                                                                    "
                                                                >
                                                                    {{ param.form_status || 'Menunggu' }}
                                                                </span>
                                                            </div>
                                                        </article>
                                                    </div>
                                                    <EmptyState
                                                        v-else
                                                        compact
                                                        icon="bi bi-list-check"
                                                        title="Belum ada parameter KPI"
                                                        description="Data parameter KPI belum tersedia untuk periode ini."
                                                    />
                                                </section>
                                            </template>
                                        </div>
                                    </transition>
                                </template>
                            </article>
                        </template>
                        <EmptyState
                            v-else-if="kpiDetailDrawerOpen"
                            compact
                            icon="bi bi-check2-circle"
                            title="Tidak ada data"
                            description="Tidak ada data KPI pada filter ini."
                        />
                    </div>
                </div>

                <div v-if="kpiDetailDrawerOpen" class="attendance-issue-drawer__foot">
                    <button
                        type="button"
                        class="pagination-btn"
                        :disabled="kpiDetailLoading || !kpiDetailPagination.has_prev"
                        @click="goToKpiDetailPage(-1)"
                    >
                        Prev
                    </button>
                    <span>Halaman {{ kpiDetailPagination.current_page }} / {{ kpiDetailPagination.last_page }}</span>
                    <button
                        type="button"
                        class="pagination-btn"
                        :disabled="kpiDetailLoading || !kpiDetailPagination.has_next"
                        @click="goToKpiDetailPage(1)"
                    >
                        Next
                    </button>
                </div>
            </aside>

            <transition name="drawer-fade">
                <div
                    v-if="lmsDetailDrawerOpen"
                    class="attendance-issue-drawer-backdrop"
                    @click="closeLmsDetailDrawer"
                ></div>
            </transition>

            <aside class="attendance-issue-drawer lms-detail-drawer" :class="{ 'is-open': lmsDetailDrawerOpen }">
                <div class="attendance-issue-drawer__head">
                    <div>
                        <span class="section-label">Detail LMS Team</span>
                        <h3>{{ lmsDetailDrawerTitle }}</h3>
                        <small>{{ lmsDetailMeta.total_members || 0 }} anggota</small>
                    </div>

                    <button type="button" class="drawer-close-btn" @click="closeLmsDetailDrawer">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="attendance-issue-drawer__body">
                    <div class="toolbar-actions lms-drawer-toolbar">
                        <div class="search-box is-wide">
                            <i class="bi bi-search"></i>
                            <input
                                v-model="lmsDetailSearchInput"
                                type="text"
                                placeholder="Cari nama / jabatan / divisi..."
                            />
                        </div>
                    </div>

                    <EmptyState
                        v-if="lmsDetailLoading"
                        compact
                        icon="bi bi-hourglass-split"
                        title="Memuat anggota LMS"
                        description="Mengambil data anggota sesuai konteks kartu..."
                    />

                    <div v-else-if="lmsDetailError" class="drawer-error-box">
                        <strong>{{ lmsDetailError }}</strong>
                        <button type="button" class="pagination-btn" @click="retryLmsDetailLoad">Coba lagi</button>
                    </div>

                    <template v-else-if="lmsDetailRows.length">
                        <article
                            v-for="item in lmsDetailRows"
                            :key="item.id"
                            class="attendance-issue-card lms-detail-member-card"
                        >
                            <button type="button" class="lms-detail-member-head" @click="toggleLmsDetailMember(item)">
                                <div class="attendance-issue-card__head">
                                    <div>
                                        <strong>{{ item.name }}</strong>
                                        <span>{{ item.position || '-' }} · {{ item.department || '-' }}</span>
                                    </div>

                                    <small>{{ formatNumber(item.avg_progress || 0, '%') }}</small>
                                </div>

                                <div class="lms-member-metrics">
                                    <span class="attendance-issue-pill is-info">
                                        Avg Progress: {{ formatNumber(item.avg_progress || 0, '%') }}
                                    </span>
                                    <span class="attendance-issue-pill is-success">
                                        Assigned: {{ formatNumber(item.assigned_total || 0) }}
                                    </span>
                                    <span class="attendance-issue-pill is-purple">
                                        Avg Gain:
                                        {{
                                            item.avg_learning_gain === null
                                                ? '-'
                                                : formatNumber(item.avg_learning_gain, '%')
                                        }}
                                    </span>
                                    <span class="attendance-issue-pill is-orange">
                                        Jam Selesai: {{ formatNumber(item.completed_hours_year || 0, ' jam') }}
                                    </span>
                                </div>
                                <div class="lms-member-expand-hint">
                                    <span>Klik untuk lihat detail training</span>
                                    <i
                                        class="bi bi-chevron-down"
                                        :class="{ 'is-open': lmsDetailOpenedMemberId === item.id }"
                                    ></i>
                                </div>
                            </button>

                            <transition name="accordion">
                                <div v-if="lmsDetailOpenedMemberId === item.id" class="lms-member-trainings">
                                    <EmptyState
                                        v-if="isLmsMemberDetailLoading(item.id)"
                                        compact
                                        icon="bi bi-hourglass-split"
                                        title="Memuat detail training"
                                        description="Mengambil detail training anggota..."
                                    />

                                    <div v-else-if="getLmsMemberDetailError(item.id)" class="drawer-error-box">
                                        <strong>{{ getLmsMemberDetailError(item.id) }}</strong>
                                    </div>

                                    <template v-else>
                                        <div v-if="lmsMemberTrainings(item.id).length" class="lms-member-training-list">
                                            <article
                                                v-for="(training, idx) in lmsMemberTrainings(item.id)"
                                                :key="`${item.id}-training-${idx}`"
                                                class="lms-member-training-item"
                                            >
                                                <div>
                                                    <strong>{{ training.title || 'Training' }}</strong>
                                                    <span>{{ training.deadline || 'Tanpa deadline' }}</span>
                                                </div>
                                                <span
                                                    class="training-status"
                                                    :class="`is-${training.status || 'pending'}`"
                                                >
                                                    {{ training.statusLabel || '-' }}
                                                </span>
                                            </article>
                                        </div>

                                        <EmptyState
                                            v-else
                                            compact
                                            icon="bi bi-journal"
                                            title="Belum ada detail training"
                                            description="Data training anggota belum tersedia."
                                        />
                                    </template>
                                </div>
                            </transition>
                            <div
                                class="lms-member-learning-progress"
                                v-if="lmsDetailOpenedMemberId === item.id && lmsMemberLearningProgress(item.id).length"
                            >
                                <div
                                    v-for="(training, idx) in lmsMemberLearningProgress(item.id)"
                                    :key="`${item.id}-progress-${idx}`"
                                    class="lms-member-learning-item"
                                >
                                    <span>{{ training.title || 'Training' }}</span>
                                    <strong>{{ formatNumber(training.progress || 0, '%') }}</strong>
                                </div>
                            </div>
                        </article>
                    </template>

                    <EmptyState
                        v-else-if="lmsDetailDrawerOpen"
                        compact
                        icon="bi bi-people"
                        title="Tidak ada data anggota"
                        description="Tidak ada anggota yang sesuai filter ini."
                    />
                </div>

                <div v-if="lmsDetailDrawerOpen" class="attendance-issue-drawer__foot">
                    <button
                        type="button"
                        class="pagination-btn"
                        :disabled="lmsDetailLoading || !lmsDetailPagination.has_prev"
                        @click="goToLmsDetailPage(-1)"
                    >
                        Prev
                    </button>
                    <span>Halaman {{ lmsDetailPagination.current_page }} / {{ lmsDetailPagination.last_page }}</span>
                    <button
                        type="button"
                        class="pagination-btn"
                        :disabled="lmsDetailLoading || !lmsDetailPagination.has_next"
                        @click="goToLmsDetailPage(1)"
                    >
                        Next
                    </button>
                </div>
            </aside>
        </div>
    </div>
</template>

<script setup>
import { computed, defineComponent, h, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import axios from 'axios';
import VueApexCharts from 'vue3-apexcharts';

const EmptyState = defineComponent({
    name: 'EmptyState',
    props: {
        icon: { type: String, default: 'bi bi-inbox' },
        title: { type: String, default: 'Data belum tersedia' },
        description: { type: String, default: 'Data akan muncul ketika sudah tersedia.' },
        compact: { type: Boolean, default: false },
        light: { type: Boolean, default: false },
    },
    setup(props) {
        return () =>
            h('div', { class: ['empty-state', { 'is-compact': props.compact, 'is-light': props.light }] }, [
                h('i', { class: props.icon }),
                h('strong', props.title),
                h('span', props.description),
            ]);
    },
});

const PanelHead = defineComponent({
    name: 'PanelHead',
    props: {
        icon: { type: String, default: 'bi bi-info-circle' },
        title: { type: String, required: true },
        count: { type: String, default: '' },
    },
    setup(props) {
        return () =>
            h('div', { class: 'detail-panel__head' }, [
                h('div', { class: 'panel-title' }, [h('i', { class: props.icon }), h('strong', props.title)]),
                props.count ? h('span', { class: 'panel-count' }, props.count) : null,
            ]);
    },
});

const IssueList = defineComponent({
    name: 'IssueList',
    props: {
        items: { type: Array, default: () => [] },
        tone: { type: String, default: '' },
        icon: { type: String, default: '' },
        emptyTitle: { type: String, default: 'Data kosong' },
        emptyDescription: { type: String, default: 'Belum ada data.' },
    },
    setup(props) {
        return () =>
            h('div', { class: 'issue-list' }, [
                ...props.items.map((item) => {
                    const tone = String(props.tone || item.tone || 'info')
                        .trim()
                        .toLowerCase()
                        .replace(/^is-/, '');

                    return h('div', { key: item.id, class: ['issue-item', `is-${tone || 'info'}`] }, [
                        h('i', { class: props.icon || item.icon || 'bi bi-info-circle' }),
                        h('div', [h('strong', item.title), h('span', item.description)]),
                    ]);
                }),
                !props.items.length
                    ? h(EmptyState, {
                          compact: true,
                          icon: 'bi bi-check2-circle',
                          title: props.emptyTitle,
                          description: props.emptyDescription,
                      })
                    : null,
            ]);
    },
});

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    greeting: { type: Object, default: () => ({}) },
    overview: { type: Object, default: () => ({}) },
    teamMembers: { type: Array, default: () => [] },
    shiftSchedules: { type: Array, default: () => [] },
    approvalItems: { type: Array, default: () => [] },
    period: {
        type: Object,
        default: () => ({
            label: 'April 2026',
            kpiMonth: 'April 2026',
        }),
    },
    todayLabel: { type: String, default: '' },
    useDemoData: { type: Boolean, default: false },
});

const todayLabel = computed(() => props.todayLabel || formatDateLabel(new Date().toISOString().slice(0, 10)));
const attendanceIssueDayLabel = computed(() => {
    if (attendanceIssueMeta.value?.day_label) {
        return attendanceIssueMeta.value.day_label;
    }

    if (attendanceIssueActiveDate.value) {
        return formatDateLabel(attendanceIssueActiveDate.value);
    }

    return 'Pilih tanggal issue';
});
const teamCalendarDayDetailDayLabel = computed(() => {
    if (teamCalendarDayDetailMeta.value?.day_label) {
        return teamCalendarDayDetailMeta.value.day_label;
    }

    if (teamCalendarDayDetailActiveDate.value) {
        return formatDateLabel(teamCalendarDayDetailActiveDate.value);
    }

    return 'Pilih tanggal';
});
const openedMemberId = ref(null);
const searchKeyword = ref('');
const selectedAttendance = ref('all');
const selectedKpi = ref('all');
const selectedLms = ref('all');
const selectedShiftDate = ref('');
const selectedShiftKey = ref('');
const teamListSectionRef = ref(null);
const memberDetailCache = shallowRef({});
const memberDetailLoadingId = ref('');
const MEMBER_DETAIL_MIN_LOADING_MS = 320;
const memberSectionRequested = ref(false);
const memberSectionLoading = ref(false);
const memberSectionLoaded = ref(false);
const memberSectionData = shallowRef([]);
const memberSectionPagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    has_prev: false,
    has_next: false,
});
const shiftSectionRequested = ref(false);
const shiftSectionLoading = ref(false);
const shiftSectionLoaded = ref(false);
const shiftSectionData = shallowRef([]);
const shiftSectionLoadedDate = ref('');
const shiftDetailLoading = ref(false);
const shiftDetailCache = shallowRef({});
const selectedShiftDetailData = shallowRef(null);
const selectedShiftDetailPage = ref(1);
const shiftDetailPagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 8,
    total: 0,
    has_prev: false,
    has_next: false,
});
const approvalSectionRequested = ref(false);
const approvalSectionLoading = ref(false);
const approvalSectionLoaded = ref(false);
const approvalSectionData = shallowRef([]);
const approvalSectionPagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 6,
    total: 0,
    has_prev: false,
    has_next: false,
});
const needsActionLoading = ref(false);
const needsActionLoaded = ref(false);
const needsActionError = ref('');
const needsActionRows = shallowRef([]);
const needsActionSummary = shallowRef({
    total_active_items: 0,
    hcis_total: 0,
    kpi_total: 0,
    critical_count: 0,
    high_count: 0,
    medium_count: 0,
    cutoff_label: '',
    kpi_year: new Date().getFullYear(),
    last_generated_at: '',
});
const needsActionPagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    has_prev: false,
    has_next: false,
});
const needsActionCategory = ref('all');
const heatmapSectionRequested = ref(false);
const heatmapSectionLoading = ref(false);
const heatmapSectionLoaded = ref(false);
const heatmapSectionData = shallowRef([]);
const heatmapWeekStart = ref(null);
const heatmapWeekEnd = ref(null);
const heatmapDateRangeLabel = ref('');
const heatmapSectionPagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 8,
    total: 0,
    has_prev: false,
    has_next: false,
});
const trainingSectionRequested = ref(false);
const trainingSectionLoading = ref(false);
const trainingSectionLoaded = ref(false);
const trainingSectionData = shallowRef([]);
const trainingSectionPagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 6,
    total: 0,
    has_prev: false,
    has_next: false,
});
const teamCalendarMonthRequested = ref(false);
const teamCalendarMonthLoading = ref(false);
const teamCalendarMonthLoaded = ref(false);
const teamCalendarMonthError = ref('');
const teamCalendarMonthData = shallowRef({
    days: [],
    stats: {
        present_count: 0,
        late_count: 0,
        issue_day_count: 0,
        izin_cuti_count: 0,
        filter_counts: {
            all: 0,
            problem: 0,
            late: 0,
            'no-in-out': 0,
            'izin-cuti': 0,
        },
    },
    meta: {
        cutoff_start: '',
        cutoff_end: '',
        cutoff_label: '',
        year: getTeamCalendarCutoffPeriod().year,
        month: getTeamCalendarCutoffPeriod().month,
        total_members: 0,
    },
});
const teamCalendarMonthCache = shallowRef({});
const teamCalendarMonthRequestingKey = ref('');
const teamCalendarMonthController = ref(null);
const teamCalendarPanelRef = ref(null);
const teamCalendarPanelObserver = ref(null);
const teamCalendarInitialPeriod = getTeamCalendarCutoffPeriod();
const teamCalendarSelectedYear = ref(teamCalendarInitialPeriod.year);
const teamCalendarSelectedMonth = ref(teamCalendarInitialPeriod.month);
const teamCalendarActiveFilter = ref('all');
const teamCalendarDayDetailDrawerOpen = ref(false);
const teamCalendarDayDetailLoading = ref(false);
const teamCalendarDayDetailError = ref('');
const teamCalendarDayDetailRows = shallowRef([]);
const teamCalendarDayDetailPagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    has_prev: false,
    has_next: false,
});
const teamCalendarDayDetailMeta = shallowRef({
    date: '',
    day_label: '',
    team_working_count: 0,
    issue_count: 0,
    filter: 'all',
});
const teamCalendarDayDetailActiveDate = ref('');
const teamCalendarDayDetailCache = shallowRef({});
const teamCalendarDayDetailRequestingKey = ref('');
const teamCalendarDayDetailController = ref(null);
const globalSummary = shallowRef(null);
const globalSummaryLoading = ref(false);
const hcisDomainSummary = shallowRef(null);
const kpiDomainSummary = shallowRef(null);
const lmsDomainSummary = shallowRef(null);
const isHcisTrendLoading = ref(false);
const isKpiDomainLoading = ref(false);
const isLmsDomainLoading = ref(false);
const hcisTrendAttempted = ref(false);
const summaryRequestControllers = shallowRef({
    core: null,
    hcis: null,
    kpi: null,
    lms: null,
});
const summaryRequestVersions = ref({
    core: 0,
    hcis: 0,
    kpi: 0,
    lms: 0,
});
const attendanceIssueDrawerOpen = ref(false);
const attendanceIssueLoading = ref(false);
const attendanceIssueRows = shallowRef([]);
const attendanceIssuePagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    has_prev: false,
    has_next: false,
});
const attendanceIssueMeta = shallowRef({
    date: '',
    day_label: '',
    issue_count: 0,
});
const attendanceIssueError = ref('');
const attendanceIssueActiveDate = ref('');
const attendanceIssueCache = shallowRef({});
const attendanceIssueRequestingKey = ref('');
const attendanceStatusDrawerOpen = ref(false);
const attendanceStatusLoading = ref(false);
const attendanceStatusError = ref('');
const attendanceStatusRows = shallowRef([]);
const attendanceStatusPagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    has_prev: false,
    has_next: false,
});
const attendanceStatusMeta = shallowRef({
    date: '',
    filter: 'all',
    filters: {},
});
const attendanceStatusActiveFilter = ref('all');
const attendanceStatusCache = shallowRef({});
const attendanceStatusRequestingKey = ref('');
const attendanceStatusSearchInput = ref('');
const attendanceStatusSearchKeyword = ref('');
const currentKpiYear = new Date().getFullYear();
const selectedKpiYear = ref(currentKpiYear);
const currentLmsYear = new Date().getFullYear();
const selectedLmsYear = ref(currentLmsYear);
const kpiDetailDrawerOpen = ref(false);
const kpiDetailLoading = ref(false);
const kpiDetailError = ref('');
const kpiDetailMode = ref('annual');
const kpiDetailSearchInput = ref('');
const kpiDetailSearchKeyword = ref('');
const kpiDetailRows = shallowRef([]);
const kpiDetailContext = ref('monthly_avg');
const kpiDetailMonth = ref('');
const kpiDetailPagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    has_prev: false,
    has_next: false,
});
const kpiDetailMeta = shallowRef({
    mode: 'annual',
    period_label: '-',
    kpi_year: new Date().getFullYear(),
    total_member: 0,
    effective_cutoff_period: '',
    calculation_scope: 'running_year',
});
const kpiDetailCache = shallowRef({});
const kpiDetailOpenedMemberId = ref('');
const kpiMemberDetailLoadingId = ref('');
const kpiMemberDetailCache = shallowRef({});
const kpiMemberDetailErrorMap = shallowRef({});
const kpiMemberModeMap = shallowRef({});
const kpiMemberPeriodMap = shallowRef({});
const memberKpiPanelStateMap = shallowRef({});
const memberKpiPanelCache = shallowRef({});
const memberKpiPanelErrorMap = shallowRef({});
const memberKpiPanelLoadingId = ref('');
const lmsDetailDrawerOpen = ref(false);
const lmsDetailLoading = ref(false);
const lmsDetailError = ref('');
const lmsDetailRows = shallowRef([]);
const lmsDetailFocus = ref('lms_not_started');
const lmsDetailCache = shallowRef({});
const lmsDetailSearchInput = ref('');
const lmsDetailSearchKeyword = ref('');
const lmsDetailPagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    has_prev: false,
    has_next: false,
});
const lmsDetailMeta = shallowRef({
    year: currentLmsYear,
    focus: 'lms_not_started',
    total_members: 0,
    sorting: {},
});
const lmsDetailOpenedMemberId = ref('');
const lmsMemberDetailLoadingId = ref('');
const lmsMemberDetailCache = shallowRef({});
const lmsMemberDetailErrorMap = shallowRef({});

/**
 * MOCK DATA WAJIB DI ATAS COMPUTED/WATCH
 * Supaya tidak error:
 * can't access lexical declaration 'demoShiftSchedules' before initialization
 */
const baseDailyLog = {
    good: [
        { day: 'Sen', date: '22 Apr', label: 'Hadir', tone: 'success' },
        { day: 'Sel', date: '23 Apr', label: 'Hadir', tone: 'success' },
        { day: 'Rab', date: '24 Apr', label: 'Hadir', tone: 'success' },
        { day: 'Kam', date: '25 Apr', label: 'Hadir', tone: 'success' },
        { day: 'Jum', date: '26 Apr', label: 'Hadir', tone: 'success' },
        { day: 'Sab', date: '27 Apr', label: 'OFF', tone: 'default' },
        { day: 'Min', date: '28 Apr', label: 'Hadir', tone: 'success' },
    ],
    issue: [
        { day: 'Sen', date: '22 Apr', label: 'Hadir', tone: 'success' },
        { day: 'Sel', date: '23 Apr', label: 'Terlambat', tone: 'warning' },
        { day: 'Rab', date: '24 Apr', label: 'Alpha', tone: 'danger' },
        { day: 'Kam', date: '25 Apr', label: 'Hadir', tone: 'success' },
        { day: 'Jum', date: '26 Apr', label: 'Pulang Cepat', tone: 'orange' },
        { day: 'Sab', date: '27 Apr', label: 'OFF', tone: 'default' },
        { day: 'Min', date: '28 Apr', label: 'Alpha', tone: 'danger' },
    ],
    leave: [
        { day: 'Sen', date: '22 Apr', label: 'Hadir', tone: 'success' },
        { day: 'Sel', date: '23 Apr', label: 'Hadir', tone: 'success' },
        { day: 'Rab', date: '24 Apr', label: 'Cuti', tone: 'info' },
        { day: 'Kam', date: '25 Apr', label: 'Cuti', tone: 'info' },
        { day: 'Jum', date: '26 Apr', label: 'Hadir', tone: 'success' },
        { day: 'Sab', date: '27 Apr', label: 'OFF', tone: 'default' },
        { day: 'Min', date: '28 Apr', label: 'Cuti', tone: 'info' },
    ],
};

const demoShiftSchedules = [
    {
        date: '2026-04-28',
        label: '28 Apr 2026',
        name: 'EMI-LS1',
        time: '07:00 - 19:00',
        minNeed: 2,
        memberIds: [4],
    },
    {
        date: '2026-04-28',
        label: '28 Apr 2026',
        name: 'EMI-S2',
        time: '15:00 - 23:00',
        minNeed: 2,
        memberIds: [2, 5],
    },
    {
        date: '2026-04-28',
        label: '28 Apr 2026',
        name: 'EMI-S3',
        time: '23:00 - 07:00',
        minNeed: 1,
        memberIds: [3],
    },
    {
        date: '2026-04-30',
        label: '30 Apr 2026',
        name: 'EMI-LS1',
        time: '07:00 - 19:00',
        minNeed: 2,
        memberIds: [2, 4],
    },
    {
        date: '2026-04-30',
        label: '30 Apr 2026',
        name: 'EMI-LS2',
        time: '19:00 - 07:00',
        minNeed: 2,
        memberIds: [1, 3],
    },
    {
        date: '2026-05-01',
        label: '1 Mei 2026',
        name: 'EMI-S2',
        time: '15:00 - 23:00',
        minNeed: 2,
        memberIds: [2, 5],
    },
    {
        date: '2026-05-01',
        label: '1 Mei 2026',
        name: 'EMI-S3',
        time: '23:00 - 07:00',
        minNeed: 2,
        memberIds: [1, 3, 4],
    },
];

const demoMembers = [
    {
        id: 1,
        name: 'Anita',
        initials: 'A',
        position: 'Operator Produksi',
        department: 'EMI',
        shift: { name: 'EMI-LS2', time: '19:00 - 07:00' },
        attendance: {
            todayStatus: 'absent',
            todayLabel: 'Tidak Hadir',
            checkIn: null,
            checkOut: null,
            workDays: 11,
            alphaCount: 2,
            lateCount: 1,
            lateMinutes: 18,
            earlyOutCount: 1,
            overtimeHours: 0,
            permissionDays: 0,
            sickDays: 0,
            leaveDays: 0,
            noCheckoutCount: 0,
            dailyLog: baseDailyLog.issue,
            issues: [
                {
                    id: 'att-1',
                    tone: 'danger',
                    icon: 'bi bi-person-x-fill',
                    title: 'Tidak hadir pada 28 Apr 2026',
                    description: 'Shift EMI-LS2 19:00 - 07:00, belum ada data check-in dan check-out.',
                },
                {
                    id: 'att-2',
                    tone: 'warning',
                    icon: 'bi bi-box-arrow-right',
                    title: 'Pulang cepat pada 26 Apr 2026',
                    description: 'Checkout tercatat lebih cepat dari jadwal shift.',
                },
            ],
        },
        kpi: {
            score: 78,
            grade: 'Good',
            achievement: 82,
            parameterSubmitted: false,
            performanceSubmitted: false,
            parameterPeriod: 'Q2 2026',
            performancePeriod: 'April 2026',
            deadlineText: 'Deadline pengisian KPI: 30 Apr 2026, tersisa 2 hari.',
            deadlineTone: 'is-warning',
            pendingItems: [
                {
                    id: 'kpi-1',
                    title: 'Parameter KPI Q2 2026 belum diisi',
                    description: 'Parameter KPI belum diajukan oleh anggota.',
                },
                {
                    id: 'kpi-2',
                    title: 'KPI Performance April 2026 belum diisi',
                    description: 'Performance bulanan belum diinput.',
                },
            ],
            monthlyAchievement: [
                { month: 'Nov', value: 75 },
                { month: 'Des', value: 77 },
                { month: 'Jan', value: 78 },
                { month: 'Feb', value: 80 },
                { month: 'Mar', value: 81 },
                { month: 'Apr', value: 82 },
            ],
            parameters: [
                { name: 'Discipline', score: 78 },
                { name: 'Quality', score: 80 },
                { name: 'Productivity', score: 76 },
            ],
        },
        lms: {
            completed: 2,
            total: 5,
            progress: 40,
            averageScore: 78,
            learningGain: 12,
            completedMonthly: [
                { month: 'Nov', value: 1 },
                { month: 'Des', value: 1 },
                { month: 'Jan', value: 0 },
                { month: 'Feb', value: 0 },
                { month: 'Mar', value: 0 },
                { month: 'Apr', value: 2 },
            ],
            trainings: [
                { title: 'Basic K3L', deadline: 'Selesai 20 Apr 2026', status: 'complete', statusLabel: 'Selesai' },
                { title: 'SOP Produksi', deadline: 'Deadline 04 Mei 2026', status: 'progress', statusLabel: '60%' },
                {
                    title: 'Quality Awareness',
                    deadline: 'Deadline 06 Mei 2026',
                    status: 'pending',
                    statusLabel: 'Belum',
                },
            ],
        },
        overtime: {
            totalHours: 0,
            beforeWorkHours: 0,
            afterWorkHours: 0,
            holidayHours: 0,
            weeklyTrend: [
                { day: 'Sen', hours: 0 },
                { day: 'Sel', hours: 0 },
                { day: 'Rab', hours: 0 },
                { day: 'Kam', hours: 0 },
                { day: 'Jum', hours: 0 },
                { day: 'Sab', hours: 0 },
                { day: 'Min', hours: 0 },
            ],
        },
        leave: { quota: 12, remaining: 8, used: 4 },
        approvals: [
            {
                id: 'apv-1',
                type: 'leave',
                tone: 'success',
                icon: 'bi bi-airplane',
                title: 'Pengajuan Cuti',
                description: 'Cuti 1 hari pada 30 Apr 2026.',
                statusLabel: 'Pending',
            },
        ],
    },
    {
        id: 2,
        name: 'Aisyah',
        initials: 'A',
        position: 'Operator Produksi',
        department: 'EMI',
        shift: { name: 'EMI-S2', time: '15:00 - 23:00' },
        attendance: {
            todayStatus: 'present',
            todayLabel: 'Hadir',
            checkIn: '14:55',
            checkOut: null,
            workDays: 11,
            alphaCount: 1,
            lateCount: 2,
            lateMinutes: 12,
            earlyOutCount: 0,
            overtimeHours: 3,
            permissionDays: 0,
            sickDays: 1,
            leaveDays: 0,
            noCheckoutCount: 0,
            dailyLog: [
                { day: 'Sen', date: '22 Apr', label: 'Hadir', tone: 'success' },
                { day: 'Sel', date: '23 Apr', label: 'Terlambat', tone: 'warning' },
                { day: 'Rab', date: '24 Apr', label: 'Hadir', tone: 'success' },
                { day: 'Kam', date: '25 Apr', label: 'Sakit', tone: 'info' },
                { day: 'Jum', date: '26 Apr', label: 'Hadir', tone: 'success' },
                { day: 'Sab', date: '27 Apr', label: 'OFF', tone: 'default' },
                { day: 'Min', date: '28 Apr', label: 'Hadir', tone: 'success' },
            ],
            issues: [],
        },
        kpi: {
            score: 91,
            grade: 'Excellent',
            achievement: 94,
            parameterSubmitted: true,
            performanceSubmitted: true,
            parameterPeriod: 'Q2 2026',
            performancePeriod: 'April 2026',
            deadlineText: 'KPI periode berjalan sudah lengkap.',
            deadlineTone: 'is-success',
            pendingItems: [],
            monthlyAchievement: [
                { month: 'Nov', value: 84 },
                { month: 'Des', value: 86 },
                { month: 'Jan', value: 88 },
                { month: 'Feb', value: 89 },
                { month: 'Mar', value: 91 },
                { month: 'Apr', value: 94 },
            ],
            parameters: [
                { name: 'Discipline', score: 92 },
                { name: 'Quality', score: 91 },
                { name: 'Productivity', score: 90 },
            ],
        },
        lms: {
            completed: 5,
            total: 5,
            progress: 100,
            averageScore: 92,
            learningGain: 25,
            completedMonthly: [
                { month: 'Nov', value: 1 },
                { month: 'Des', value: 1 },
                { month: 'Jan', value: 1 },
                { month: 'Feb', value: 1 },
                { month: 'Mar', value: 0 },
                { month: 'Apr', value: 1 },
            ],
            trainings: [
                { title: 'Basic K3L', deadline: 'Selesai 20 Apr 2026', status: 'complete', statusLabel: 'Selesai' },
                {
                    title: 'Quality Awareness',
                    deadline: 'Selesai 25 Apr 2026',
                    status: 'complete',
                    statusLabel: 'Selesai',
                },
            ],
        },
        overtime: {
            totalHours: 3,
            beforeWorkHours: 1,
            afterWorkHours: 2,
            holidayHours: 0,
            weeklyTrend: [
                { day: 'Sen', hours: 0 },
                { day: 'Sel', hours: 1 },
                { day: 'Rab', hours: 0 },
                { day: 'Kam', hours: 0 },
                { day: 'Jum', hours: 2 },
                { day: 'Sab', hours: 0 },
                { day: 'Min', hours: 0 },
            ],
        },
        leave: { quota: 12, remaining: 10, used: 2 },
        approvals: [],
    },
    {
        id: 3,
        name: 'Amelia',
        initials: 'A',
        position: 'Operator Produksi',
        department: 'EMI',
        shift: { name: 'EMI-S3', time: '23:00 - 07:00' },
        attendance: {
            todayStatus: 'present',
            todayLabel: 'Hadir',
            checkIn: '22:51',
            checkOut: '07:02',
            workDays: 11,
            alphaCount: 0,
            lateCount: 0,
            lateMinutes: 0,
            earlyOutCount: 0,
            overtimeHours: 5,
            permissionDays: 0,
            sickDays: 0,
            leaveDays: 0,
            noCheckoutCount: 0,
            dailyLog: baseDailyLog.good,
            issues: [],
        },
        kpi: {
            score: 94,
            grade: 'Excellent',
            achievement: 97,
            parameterSubmitted: true,
            performanceSubmitted: true,
            parameterPeriod: 'Q2 2026',
            performancePeriod: 'April 2026',
            deadlineText: 'KPI periode berjalan sudah lengkap.',
            deadlineTone: 'is-success',
            pendingItems: [],
            monthlyAchievement: [
                { month: 'Nov', value: 88 },
                { month: 'Des', value: 89 },
                { month: 'Jan', value: 90 },
                { month: 'Feb', value: 94 },
                { month: 'Mar', value: 95 },
                { month: 'Apr', value: 97 },
            ],
            parameters: [
                { name: 'Discipline', score: 95 },
                { name: 'Quality', score: 94 },
                { name: 'Productivity', score: 93 },
            ],
        },
        lms: {
            completed: 5,
            total: 5,
            progress: 100,
            averageScore: 95,
            learningGain: 28,
            completedMonthly: [
                { month: 'Nov', value: 0 },
                { month: 'Des', value: 1 },
                { month: 'Jan', value: 1 },
                { month: 'Feb', value: 1 },
                { month: 'Mar', value: 1 },
                { month: 'Apr', value: 1 },
            ],
            trainings: [
                { title: 'Basic K3L', deadline: 'Selesai 20 Apr 2026', status: 'complete', statusLabel: 'Selesai' },
                { title: 'SOP Produksi', deadline: 'Selesai 22 Apr 2026', status: 'complete', statusLabel: 'Selesai' },
            ],
        },
        overtime: {
            totalHours: 5,
            beforeWorkHours: 0,
            afterWorkHours: 3,
            holidayHours: 2,
            weeklyTrend: [
                { day: 'Sen', hours: 0 },
                { day: 'Sel', hours: 1 },
                { day: 'Rab', hours: 0 },
                { day: 'Kam', hours: 2 },
                { day: 'Jum', hours: 0 },
                { day: 'Sab', hours: 2 },
                { day: 'Min', hours: 0 },
            ],
        },
        leave: { quota: 12, remaining: 11, used: 1 },
        approvals: [
            {
                id: 'apv-2',
                type: 'overtime',
                tone: 'success',
                icon: 'bi bi-clock-history',
                title: 'Approval Lembur',
                description: 'Lembur hari libur 2 jam.',
                statusLabel: 'Pending',
            },
        ],
    },
    {
        id: 4,
        name: 'Andre',
        initials: 'A',
        position: 'Operator Produksi',
        department: 'EMI',
        shift: { name: 'EMI-LS1', time: '07:00 - 19:00' },
        attendance: {
            todayStatus: 'no-checkout',
            todayLabel: 'Belum Checkout',
            checkIn: '06:51',
            checkOut: null,
            workDays: 11,
            alphaCount: 0,
            lateCount: 0,
            lateMinutes: 0,
            earlyOutCount: 0,
            overtimeHours: 2,
            permissionDays: 0,
            sickDays: 0,
            leaveDays: 0,
            noCheckoutCount: 1,
            dailyLog: [
                { day: 'Sen', date: '22 Apr', label: 'Hadir', tone: 'success' },
                { day: 'Sel', date: '23 Apr', label: 'Hadir', tone: 'success' },
                { day: 'Rab', date: '24 Apr', label: 'Hadir', tone: 'success' },
                { day: 'Kam', date: '25 Apr', label: 'Hadir', tone: 'success' },
                { day: 'Jum', date: '26 Apr', label: 'Hadir', tone: 'success' },
                { day: 'Sab', date: '27 Apr', label: 'OFF', tone: 'default' },
                { day: 'Min', date: '28 Apr', label: 'No Checkout', tone: 'warning' },
            ],
            issues: [
                {
                    id: 'att-2',
                    tone: 'warning',
                    icon: 'bi bi-box-arrow-right',
                    title: 'Belum checkout pada 28 Apr 2026',
                    description: 'Check-in 06:51, data check-out belum tercatat.',
                },
            ],
        },
        kpi: {
            score: 89,
            grade: 'Very Good',
            achievement: 90,
            parameterSubmitted: true,
            performanceSubmitted: true,
            parameterPeriod: 'Q2 2026',
            performancePeriod: 'April 2026',
            deadlineText: 'KPI periode berjalan sudah lengkap.',
            deadlineTone: 'is-success',
            pendingItems: [],
            monthlyAchievement: [
                { month: 'Nov', value: 82 },
                { month: 'Des', value: 84 },
                { month: 'Jan', value: 85 },
                { month: 'Feb', value: 86 },
                { month: 'Mar', value: 88 },
                { month: 'Apr', value: 90 },
            ],
            parameters: [
                { name: 'Discipline', score: 90 },
                { name: 'Quality', score: 88 },
                { name: 'Productivity', score: 89 },
            ],
        },
        lms: {
            completed: 4,
            total: 5,
            progress: 80,
            averageScore: 85,
            learningGain: 20,
            completedMonthly: [
                { month: 'Nov', value: 1 },
                { month: 'Des', value: 0 },
                { month: 'Jan', value: 1 },
                { month: 'Feb', value: 0 },
                { month: 'Mar', value: 1 },
                { month: 'Apr', value: 1 },
            ],
            trainings: [
                { title: 'Basic K3L', deadline: 'Selesai 20 Apr 2026', status: 'complete', statusLabel: 'Selesai' },
                {
                    title: 'Quality Awareness',
                    deadline: 'Deadline 04 Mei 2026',
                    status: 'progress',
                    statusLabel: '50%',
                },
            ],
        },
        overtime: {
            totalHours: 2,
            beforeWorkHours: 0,
            afterWorkHours: 2,
            holidayHours: 0,
            weeklyTrend: [
                { day: 'Sen', hours: 0 },
                { day: 'Sel', hours: 0 },
                { day: 'Rab', hours: 1 },
                { day: 'Kam', hours: 0 },
                { day: 'Jum', hours: 1 },
                { day: 'Sab', hours: 0 },
                { day: 'Min', hours: 0 },
            ],
        },
        leave: { quota: 12, remaining: 9, used: 3 },
        approvals: [
            {
                id: 'apv-3',
                type: 'correction',
                tone: 'success',
                icon: 'bi bi-pencil-square',
                title: 'Koreksi Absensi',
                description: 'Koreksi checkout tanggal 28 Apr 2026.',
                statusLabel: 'Pending',
            },
        ],
    },
    {
        id: 5,
        name: 'Aji',
        initials: 'A',
        position: 'Operator Produksi',
        department: 'EMI',
        shift: { name: 'EMI-S2', time: '15:00 - 23:00' },
        attendance: {
            todayStatus: 'leave',
            todayLabel: 'Cuti',
            checkIn: null,
            checkOut: null,
            workDays: 10,
            alphaCount: 0,
            lateCount: 0,
            lateMinutes: 0,
            earlyOutCount: 0,
            overtimeHours: 0,
            permissionDays: 0,
            sickDays: 0,
            leaveDays: 1,
            noCheckoutCount: 0,
            dailyLog: baseDailyLog.leave,
            issues: [],
        },
        kpi: {
            score: 92,
            grade: 'Excellent',
            achievement: 93,
            parameterSubmitted: true,
            performanceSubmitted: true,
            parameterPeriod: 'Q2 2026',
            performancePeriod: 'April 2026',
            deadlineText: 'KPI periode berjalan sudah lengkap.',
            deadlineTone: 'is-success',
            pendingItems: [],
            monthlyAchievement: [
                { month: 'Nov', value: 87 },
                { month: 'Des', value: 88 },
                { month: 'Jan', value: 90 },
                { month: 'Feb', value: 91 },
                { month: 'Mar', value: 92 },
                { month: 'Apr', value: 93 },
            ],
            parameters: [
                { name: 'Discipline', score: 92 },
                { name: 'Quality', score: 90 },
                { name: 'Productivity', score: 91 },
            ],
        },
        lms: {
            completed: 5,
            total: 5,
            progress: 100,
            averageScore: 92,
            learningGain: 28,
            completedMonthly: [
                { month: 'Nov', value: 1 },
                { month: 'Des', value: 1 },
                { month: 'Jan', value: 0 },
                { month: 'Feb', value: 1 },
                { month: 'Mar', value: 1 },
                { month: 'Apr', value: 1 },
            ],
            trainings: [
                { title: 'Basic K3L', deadline: 'Selesai 20 Apr 2026', status: 'complete', statusLabel: 'Selesai' },
            ],
        },
        overtime: {
            totalHours: 0,
            beforeWorkHours: 0,
            afterWorkHours: 0,
            holidayHours: 0,
            weeklyTrend: [
                { day: 'Sen', hours: 0 },
                { day: 'Sel', hours: 0 },
                { day: 'Rab', hours: 0 },
                { day: 'Kam', hours: 0 },
                { day: 'Jum', hours: 0 },
                { day: 'Sab', hours: 0 },
                { day: 'Min', hours: 0 },
            ],
        },
        leave: { quota: 12, remaining: 7, used: 5 },
        approvals: [],
    },
];

/**
 * COMPUTED UTAMA
 * Diletakkan setelah mock data agar aman.
 */
const activePeriodLabel = computed(() => props.period?.label || 'Periode Berjalan');
const kpiMonthLabel = computed(() => props.period?.kpiMonth || props.period?.label || 'Bulan berjalan');
const overviewSummary = computed(() => props.overview?.summary || {});
const attendanceIssuePeriodLabel = computed(() => {
    return String(activePeriodLabel.value || 'Periode berjalan').trim();
});
const hcisTrendRangeLabel = computed(() => {
    const today = new Date();
    const start = new Date(today);
    start.setDate(today.getDate() - 6);

    return `${formatDateLabel(start)} - ${formatDateLabel(today)}`;
});

const rawMembers = computed(() => {
    if (memberSectionLoaded.value) return memberSectionData.value;
    if (props.teamMembers.length) return props.teamMembers;
    if (props.useDemoData) return demoMembers;
    return [];
});

const members = computed(() =>
    rawMembers.value.map((member) => {
        const key = member.id || member.kode_karyawan || member.Kode_Karyawan;
        const detail = memberDetailFor(key);
        return normalizeMember(detail ? { ...member, ...detail } : member);
    }),
);

const rawShiftSchedules = computed(() => {
    if (shiftSectionLoaded.value) return shiftSectionData.value;
    if (props.shiftSchedules.length) return props.shiftSchedules;
    if (props.useDemoData) return demoShiftSchedules;
    return [];
});

function getShiftCacheKey(date, shiftName, page) {
    return `${date}|${shiftName}|${page}`;
}

function truncateText(text, maxLength = 20) {
    if (text.length <= maxLength) return text;
    return text.slice(0, maxLength - 3) + '...';
}

const availableShiftDates = computed(() => {
    const map = new Map();

    rawShiftSchedules.value.forEach((item) => {
        map.set(item.date, {
            value: item.date,
            label: item.label || formatDateLabel(item.date),
        });
    });

    return Array.from(map.values());
});

watch(
    availableShiftDates,
    (dates) => {
        if (!dates.length) return;

        const exists = dates.some((item) => item.value === selectedShiftDate.value);

        if (!exists) {
            selectedShiftDate.value = dates[0].value;
        }
    },
    { immediate: true },
);

const selectedDateLabel = computed(() => {
    return (
        availableShiftDates.value.find((item) => item.value === selectedShiftDate.value)?.label ||
        selectedShiftDate.value
    );
});

const selectedShiftDateLabel = computed(() => selectedDateLabel.value);

const selectedDateShiftSummary = computed(() => {
    const schedules = rawShiftSchedules.value.filter((item) => item.date === selectedShiftDate.value);

    return schedules.map((shift) => ({
        ...shift,
        key: shift.key || `${shift.date}-${shift.name}`,
        memberCount: Number(shift.memberCount ?? shift.member_count ?? shift.memberIds?.length ?? 0),
        gapCount: Number(shift.gapCount ?? shift.gap_count ?? shift.unavailableMemberIds?.length ?? 0),
        coverageCount: Number(
            shift.coverageCount ??
                shift.coverage_count ??
                Math.max(
                    0,
                    Number(shift.memberCount ?? shift.memberIds?.length ?? 0) -
                        Number(shift.gapCount ?? shift.unavailableMemberIds?.length ?? 0),
                ),
        ),
        hasGap: Boolean(shift.hasGap),
    }));
});

watch(
    selectedDateShiftSummary,
    (list) => {
        if (!list.length) {
            selectedShiftKey.value = '';
            return;
        }

        const exists = list.some((item) => item.key === selectedShiftKey.value);

        if (!exists) {
            selectedShiftKey.value = list[0].key;
        }
    },
    { immediate: true },
);

const selectedShiftDetail = computed(() => {
    const base =
        selectedDateShiftSummary.value.find((item) => item.key === selectedShiftKey.value) ||
        selectedDateShiftSummary.value[0] ||
        null;

    if (!base) return null;

    const detail = selectedShiftDetailData.value;
    const sameShift =
        detail && (detail.shift?.name || base.name) === base.name && (detail.shift?.date || base.date) === base.date;

    return {
        ...base,
        members: sameShift && Array.isArray(detail.data) ? detail.data : [],
        pagination: sameShift ? detail.pagination || shiftDetailPagination.value : shiftDetailPagination.value,
        detailLoaded: Boolean(sameShift && Array.isArray(detail.data)),
    };
});

const filteredMembers = computed(() => {
    const keyword = searchKeyword.value.trim().toLowerCase();

    const filtered = members.value.filter((member) => {
        const matchKeyword =
            !keyword ||
            member.name.toLowerCase().includes(keyword) ||
            member.position.toLowerCase().includes(keyword) ||
            member.department.toLowerCase().includes(keyword);

        const matchAttendance =
            selectedAttendance.value === 'all' ||
            member.attendance.todayStatus === selectedAttendance.value ||
            (selectedAttendance.value === 'absent' && member.attendance.todayStatus === 'no-checkin') ||
            (selectedAttendance.value === 'late' && member.attendance.lateCount > 0) ||
            (selectedAttendance.value === 'leave' &&
                ['leave', 'permission', 'sick', 'cuti'].includes(member.attendance.todayStatus));

        const matchKpi =
            selectedKpi.value === 'all' ||
            (selectedKpi.value === 'kpi-alert' && member.kpi.pendingItems.length > 0) ||
            (selectedKpi.value === 'complete' && !member.kpi.pendingItems.length);

        const matchLms =
            selectedLms.value === 'all' ||
            (selectedLms.value === 'pending' && member.lms.progress < 100) ||
            (selectedLms.value === 'complete' && member.lms.progress >= 100);

        return matchKeyword && matchAttendance && matchKpi && matchLms;
    });

    const sorted = filtered
        .map((member) => {
            const score = urgencyScore(member);
            const level = urgencyLevel(score);
            const hcisRiskCount = Math.max(Number(member.attendance.issueCount || 0), member.attendance.issues.length);
            const kpiAlertCount = member.kpi.pendingItems.length;
            const priorityReasons = buildPriorityReasons(member);
            const nextActions = buildNextActions(member, priorityReasons);
            const prioritySummary = buildPrioritySummary(member, priorityReasons, nextActions);

            return {
                ...member,
                urgencyScore: score,
                urgencyLevel: level,
                urgencyLabel: urgencyLabel(level),
                hcisRiskCount,
                kpiAlertCount,
                priorityReasons,
                nextActions,
                prioritySummary,
            };
        })
        .sort((left, right) => {
            if (right.urgencyScore !== left.urgencyScore) {
                return right.urgencyScore - left.urgencyScore;
            }

            return left.name.localeCompare(right.name, 'id-ID');
        });

    return sorted;
});

const activeMemberFilterChips = computed(() => {
    const chips = [];

    if (searchKeyword.value.trim()) {
        chips.push({
            key: 'search',
            label: `Cari: "${searchKeyword.value.trim()}"`,
        });
    }

    if (selectedAttendance.value !== 'all') {
        chips.push({
            key: 'attendance',
            label: `Absensi: ${memberAttendanceFilterLabel(selectedAttendance.value)}`,
        });
    }

    if (selectedKpi.value !== 'all') {
        chips.push({
            key: 'kpi',
            label: `KPI: ${memberKpiFilterLabel(selectedKpi.value)}`,
        });
    }

    if (selectedLms.value !== 'all') {
        chips.push({
            key: 'lms',
            label: `LMS: ${memberLmsFilterLabel(selectedLms.value)}`,
        });
    }

    return chips;
});

const hasActiveMemberFilters = computed(() => activeMemberFilterChips.value.length > 0);

const teamCalendarMonthMeta = computed(() => {
    return teamCalendarMonthData.value?.meta || {};
});

const teamCalendarStats = computed(() => {
    return teamCalendarMonthData.value?.stats || {};
});

const teamCalendarDays = computed(() => {
    return Array.isArray(teamCalendarMonthData.value?.data?.days)
        ? teamCalendarMonthData.value.data.days
        : Array.isArray(teamCalendarMonthData.value?.days)
          ? teamCalendarMonthData.value.days
          : [];
});

const teamCalendarFilterOptions = computed(() => {
    const counts = teamCalendarStats.value?.filter_counts || {};
    return [
        { key: 'all', label: 'Semua', count: Number(counts.all || teamCalendarDays.value.length || 0) },
        { key: 'problem', label: 'Bermasalah', count: Number(counts.problem || 0) },
        { key: 'late', label: 'Terlambat', count: Number(counts.late || 0) },
        { key: 'no-in-out', label: 'No IN/OUT', count: Number(counts['no-in-out'] || 0) },
        { key: 'izin-cuti', label: 'Izin/Cuti', count: Number(counts['izin-cuti'] || 0) },
    ];
});

const teamCalendarFilteredDays = computed(() => {
    const source = teamCalendarDays.value;
    const active = String(teamCalendarActiveFilter.value || 'all');
    if (active === 'all') {
        return source;
    }

    if (active === 'problem') {
        return source.filter((day) => Boolean(day?.has_problem));
    }

    if (active === 'late') {
        return source.filter((day) => Boolean(day?.has_late));
    }

    if (active === 'no-in-out') {
        return source.filter((day) => Boolean(day?.has_no_in_out));
    }

    if (active === 'izin-cuti') {
        return source.filter((day) => Boolean(day?.has_izin_cuti));
    }

    return source;
});

const approvalItems = computed(() => {
    const source = approvalSectionLoaded.value || approvalSectionRequested.value ? approvalSectionData.value : [];

    if (source.length) {
        return source.map((approval) => ({
            ...approval,
            memberId: approval.memberId || approval.kode_karyawan,
            memberName: approval.memberName || approval.member_name || '',
            tone: approval.tone || 'success',
            icon: approval.icon || 'bi bi-check2-square',
            meta: approval.meta || 'Approval',
        }));
    }

    return props.approvalItems.map((approval) => ({
        ...approval,
        memberId: approval.memberId || approval.kode_karyawan,
        memberName: approval.memberName || approval.member_name || '',
        tone: approval.tone || 'success',
        icon: approval.icon || 'bi bi-check2-square',
        meta: approval.meta || 'Approval',
    }));
});

const approvalItemCount = computed(() => {
    if (approvalSectionLoaded.value) {
        return Number(approvalSectionPagination.value.total || approvalItems.value.length || 0);
    }

    return Number(props.overview?.approvalQueue || props.overview?.summary?.approvalQueue || 0);
});

const heatmapRows = computed(() => {
    if (!(heatmapSectionLoaded.value || heatmapSectionRequested.value)) {
        return [];
    }

    return heatmapSectionData.value.map((member) => ({
        ...member,
        attendance: {
            dailyLog: Array.isArray(member.attendance?.dailyLog) ? member.attendance.dailyLog : [],
        },
    }));
});

const heatmapIssueCount = computed(() => {
    if (!heatmapSectionLoaded.value) {
        return 0;
    }

    return heatmapRows.value.reduce(
        (total, member) =>
            total +
            member.attendance.dailyLog.filter((cell) =>
                ['danger', 'warning', 'orange'].includes(String(cell.tone || '').toLowerCase()),
            ).length,
        0,
    );
});

const trainingPendingMembers = computed(() => {
    if (!(trainingSectionLoaded.value || trainingSectionRequested.value)) {
        return [];
    }

    return trainingSectionData.value.map((member) => ({
        ...member,
        lms: {
            ...member.lms,
            progress: Number(member.lms?.progress || 0),
            completed: Number(member.lms?.completed || 0),
            total: Number(member.lms?.total || 0),
        },
    }));
});

const trainingPendingCount = computed(() => {
    if (trainingSectionLoaded.value) {
        return Number(trainingSectionPagination.value.total || trainingPendingMembers.value.length || 0);
    }

    return Number(props.overview?.summary?.totalTrainingPending || 0);
});

const summary = computed(() => {
    const now = new Date();
    const defaultAvgMonthDate = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    const defaultAvgMonthLabel = defaultAvgMonthDate.toLocaleDateString('id-ID', {
        month: 'short',
        year: 'numeric',
    });
    const normalizePeriodLabel = (value) =>
        String(value || '')
            .replace(/^Periode\s+/i, '')
            .trim();

    const base = globalSummary.value || overviewSummary.value || {};
    const hcisStats = hcisDomainSummary.value?.stats || {};
    const kpiStats = kpiDomainSummary.value?.stats || {};
    const lmsStats = lmsDomainSummary.value?.stats || {};

    const totalMembers = Number(hcisStats.totalMembers ?? base.totalMembers ?? 0);
    const presentToday = Number(hcisStats.presentToday ?? 0);
    const absentToday = Number(hcisStats.absentToday ?? 0);
    const pendingCheckInToday = Number(hcisStats.pendingCheckInToday ?? 0);
    const workingToday = Number(hcisStats.workingToday ?? 0);
    const noCheckoutToday = Number(hcisStats.noCheckoutToday ?? 0);
    const alphaCount = Number(base.alphaCount || 0);
    const sickDays = Number(base.sickDays || 0);
    const lateCount = Number(hcisStats.lateCount ?? 0);
    const earlyOutCount = Number(base.earlyOutCount || 0);
    const noCheckoutCount = Number(hcisStats.noCheckoutCount ?? 0);
    const leaveDays = Number(base.leaveDays || 0);
    const remainingLeave = Number(base.remainingLeave || 0);
    const leaveToday = Number(hcisStats.leaveToday ?? 0);
    const noDataToday = Number(hcisStats.noDataToday ?? 0);
    const otherToday = Number(hcisStats.otherToday ?? 0);
    const attendanceIssues = Number(hcisStats.attendanceIssues ?? 0);
    const hcisCritical = Number(hcisStats.hcisCritical ?? absentToday + noCheckoutToday ?? 0);
    const totalOvertime = Number(hcisStats.totalOvertime ?? 0);
    const performancePendingCount = Number(kpiStats.performancePendingCount ?? 0);
    const performanceSubmittedPercent = Number(kpiStats.performanceSubmittedPercent ?? 0);
    const scheduledMembers = Number(kpiStats.scheduledMembers ?? 0);
    const parameterCoveragePercent = Number(kpiStats.parameterCoveragePercent ?? 0);
    const performanceSubmittedCount = Number(kpiStats.performanceSubmittedCount ?? 0);
    const monthlyAvgScore = Number(kpiStats.monthlyAvgScore ?? 0);
    const ytdAvgScore = Number(kpiStats.ytdAvgScore ?? 0);
    const ytdLe80Count = Number(kpiStats.ytdLe80Count ?? 0);
    const ytdGt80Count = Number(kpiStats.ytdGt80Count ?? 0);
    const performanceAvailable = Boolean(kpiStats.performanceAvailable ?? false);
    const ytdPeriodLabel = String(kpiStats.ytdPeriodLabel || 'YTD -');
    const approvalQueueCount = Number(kpiStats.approvalQueueCount ?? 0);
    const kpiPending = Number(kpiStats.kpiPending ?? base.kpiPending ?? performancePendingCount ?? 0);
    const kpiCompletion = Number(kpiStats.kpiCompletion ?? base.kpiCompletion ?? performanceSubmittedPercent ?? 0);
    const avgKpiScore = Number(kpiStats.avgKpiScore ?? base.avgKpiScore ?? 0);
    const lowKpiCount = Number(kpiStats.lowKpiCount ?? base.lowKpiCount ?? 0);
    const excellentKpiCount = Number(kpiStats.excellentKpiCount ?? base.excellentKpiCount ?? 0);
    const kpiYear = Number(kpiStats.kpiYear || new Date().getFullYear());
    const formScopeLabel = String(kpiStats.formScopeLabel || `Tahun ${kpiYear}`);
    const parameterPeriodLabel = String(kpiStats.parameterPeriodLabel || 'Periode berjalan');
    const performancePeriodLabel = normalizePeriodLabel(kpiStats.performancePeriodLabel || defaultAvgMonthLabel);
    const kpiTrendRangeLabel = String(kpiStats.trendRangeLabel || `Jan-Des ${kpiYear}`);
    const totalTrainingAssigned = Number(lmsStats.totalTrainingAssigned ?? base.totalTrainingAssigned ?? 0);
    const totalTrainingCompleted = Number(lmsStats.totalTrainingCompleted ?? base.totalTrainingCompleted ?? 0);
    const totalTrainingPending = Number(
        lmsStats.totalTrainingPending ?? base.totalTrainingPending ?? trainingPendingCount.value ?? 0,
    );
    const lmsAtRisk = Number(lmsStats.lmsAtRisk ?? base.lmsAtRisk ?? 0);
    const avgLmsProgress = Number(lmsStats.avgLmsProgress ?? base.avgLmsProgress ?? 0);
    const lmsYear = Number(lmsStats.year || selectedLmsYear.value || currentLmsYear);
    const lmsAvgMonthlyHours = Number(
        Array.isArray(lmsDomainSummary.value?.trend) && lmsDomainSummary.value.trend.length
            ? lmsDomainSummary.value.trend.reduce((sum, item) => sum + Number(item.value || 0), 0) /
                  lmsDomainSummary.value.trend.length
            : 0,
    );
    const approvalQueue = approvalItemCount.value || Number(base.approvalQueue || 0);
    const followUpTotal = Number(base.followUpTotal || attendanceIssues + kpiPending + approvalQueue + lmsAtRisk || 0);

    const totalRequiredToday = selectedDateShiftSummary.value.reduce(
        (total, shift) => total + Number(shift.minNeed || 0),
        0,
    );

    const totalScheduledToday = selectedDateShiftSummary.value.reduce(
        (total, shift) => total + Number(shift.coverageCount || shift.memberCount || 0),
        0,
    );

    const shiftCoverage = totalRequiredToday
        ? Math.min(100, Math.round((totalScheduledToday / totalRequiredToday) * 100))
        : Number(base.shiftCoverage || 0);

    const kpiImprovement = Number(kpiStats.kpiImprovement ?? base.kpiImprovement ?? 0);

    return {
        totalMembers,
        presentToday,
        absentToday,
        pendingCheckInToday,
        workingToday,
        noCheckoutToday,
        leaveToday,
        noDataToday,
        otherToday,
        alphaCount,
        sickDays,
        lateCount,
        earlyOutCount,
        noCheckoutCount,
        leaveDays,
        remainingLeave,
        attendanceIssues,
        hcisCritical,
        totalOvertime,
        kpiPending,
        kpiCompletion,
        avgKpiScore,
        lowKpiCount,
        excellentKpiCount,
        scheduledMembers,
        parameterCoveragePercent,
        performanceSubmittedPercent,
        performanceSubmittedCount,
        monthlyAvgScore,
        ytdAvgScore,
        ytdLe80Count,
        ytdGt80Count,
        performanceAvailable,
        ytdPeriodLabel,
        approvalQueueCount,
        performancePendingCount,
        kpiYear,
        formScopeLabel,
        parameterPeriodLabel,
        performancePeriodLabel,
        kpiTrendRangeLabel,
        totalTrainingAssigned,
        totalTrainingCompleted,
        totalTrainingPending,
        lmsAtRisk,
        avgLmsProgress,
        lmsYear,
        lmsAvgMonthlyHours: Number.isFinite(lmsAvgMonthlyHours) ? Number(lmsAvgMonthlyHours.toFixed(2)) : 0,
        approvalQueue,
        followUpTotal,
        shiftCoverage,
        kpiImprovement,
    };
});

const isKpiFullYearView = computed(() => {
    const year = Number(summary.value.kpiYear || 0);
    const currentYear = new Date().getFullYear();
    return year > 0 && year < currentYear;
});

const kpiPrimaryScore = computed(() => {
    if (isKpiFullYearView.value) {
        return Number(summary.value.ytdAvgScore || 0);
    }
    return Number(summary.value.monthlyAvgScore || 0);
});

const quickStats = computed(() => [
    {
        key: 'follow-up',
        label: 'Perlu Ditindak',
        value: summary.value.followUpTotal,
        description: 'HCIS, KPI, LMS, approval',
        icon: 'bi bi-exclamation-diamond',
        tone: summary.value.followUpTotal > 0 ? 'danger' : 'success',
        priority: true,
    },
    {
        key: 'approval',
        label: 'Antrian Approval',
        value: summary.value.approvalQueue,
        description: 'izin, lembur, koreksi',
        icon: 'bi bi-inboxes',
        tone: 'primary',
        priority: summary.value.approvalQueue > 0,
    },
    // {
    //     key: 'coverage',
    //     label: 'Coverage Shift',
    //     value: `${summary.value.shiftCoverage}%`,
    //     description: selectedShiftDateLabel.value,
    //     icon: 'bi bi-diagram-3',
    //     tone: summary.value.shiftCoverage < 100 ? 'warning' : 'success',
    // },
    {
        key: 'hcis-issue',
        label: 'Issue HCIS',
        value: summary.value.attendanceIssues,
        description: 'alpha, no checkout, telat',
        icon: 'bi bi-calendar-x',
        tone: summary.value.attendanceIssues > 0 ? 'warning' : 'success',
    },
    {
        key: 'kpi-alert',
        label: 'Alert KPI',
        value: summary.value.kpiPending,
        description: `${summary.value.kpiCompletion}% data lengkap`,
        icon: 'bi bi-graph-down-arrow',
        tone: summary.value.kpiPending > 0 ? 'orange' : 'success',
    },
    {
        key: 'lms-risk',
        label: 'LMS Berisiko',
        value: summary.value.lmsAtRisk,
        description: 'progress di bawah 80%',
        icon: 'bi bi-journal-x',
        tone: summary.value.lmsAtRisk > 0 ? 'info' : 'success',
    },
]);

const hcisMonitoringStats = computed(() => [
    {
        key: 'absent',
        label: 'Tidak Hadir',
        value: Number(summary.value.absentToday || 0),
        caption: 'hari ini',
        tone: summary.value.absentToday > 0 ? 'danger' : 'success',
    },
    {
        key: 'pending-checkin',
        label: 'Menunggu Check-In',
        value: Number(summary.value.pendingCheckInToday || 0),
        caption: 'menunggu jam masuk',
        tone: summary.value.pendingCheckInToday > 0 ? 'secondary' : 'success',
    },
    {
        key: 'working',
        label: 'Sedang Bekerja',
        value: Number(summary.value.workingToday || 0),
        caption: 'sudah check in',
        tone: summary.value.workingToday > 0 ? 'info' : 'success',
    },
    {
        key: 'no-checkout',
        label: 'Belum Checkout',
        value: Number(summary.value.noCheckoutToday || 0),
        caption: 'perlu validasi',
        tone: summary.value.noCheckoutToday > 0 ? 'orange' : 'success',
    },
]);

const attendanceStatusFilterMap = {
    all: 'Semua Status',
    present: 'Hadir',
    'no-checkin': 'Tidak Check-In',
    'pending-checkin': 'Menunggu Check-In',
    working: 'Sedang Bekerja',
    'no-checkout': 'Belum Checkout',
    late: 'Terlambat',
    'early-out': 'Pulang Cepat',
    leave: 'Izin / Cuti / Sakit',
    'no-data': 'Belum Ada Data',
};

const attendanceStatusFilterLabel = computed(() => {
    return attendanceStatusFilterMap[attendanceStatusActiveFilter.value] || 'Semua Status';
});

const attendanceStatusFilterOptions = computed(() => {
    const counts = attendanceStatusMeta.value?.filters || {};
    const keys = [
        'all',
        'present',
        'no-checkin',
        'pending-checkin',
        'working',
        'no-checkout',
        'late',
        'early-out',
        'leave',
        'no-data',
    ];

    return keys.map((key) => ({
        key,
        label: attendanceStatusFilterMap[key] || key,
        count: Number(counts[key] || 0),
    }));
});

const kpiMemberDetailModes = [
    { key: 'month', label: 'Bulanan' },
    { key: 'full_year', label: 'Full Year' },
];
const kpiYearOptions = computed(() => {
    const nowYear = new Date().getFullYear();
    const minYear = nowYear - 5;
    const summaryYear = Number(summary.value.kpiYear || 0);
    const selectedYear = Number(selectedKpiYear.value || 0);

    const years = new Set();
    for (let year = nowYear; year >= minYear; year -= 1) {
        years.add(year);
    }

    if (summaryYear >= 2000 && summaryYear <= 2100) {
        years.add(summaryYear);
    }
    if (selectedYear >= 2000 && selectedYear <= 2100) {
        years.add(selectedYear);
    }

    return Array.from(years).sort((a, b) => b - a);
});
const lmsYearOptions = computed(() => {
    const nowYear = new Date().getFullYear();
    const minYear = nowYear - 5;
    const years = new Set();
    for (let year = nowYear; year >= minYear; year -= 1) {
        years.add(year);
    }

    const summaryYear = Number(lmsDomainSummary.value?.stats?.year || 0);
    if (summaryYear >= 2000 && summaryYear <= 2100) {
        years.add(summaryYear);
    }
    if (selectedLmsYear.value >= 2000 && selectedLmsYear.value <= 2100) {
        years.add(selectedLmsYear.value);
    }

    return Array.from(years).sort((a, b) => b - a);
});
const lmsFocusLabelMap = {
    lms_not_started: 'Belum Selesai',
    progress_below_target: 'Progress < 80%',
    progress_above_target: 'Progress Tertinggi',
    assigned: 'Total Assigned',
    learning_gain: 'Learning Gain',
};
const lmsDetailDrawerTitle = computed(() => {
    const focus = String(lmsDetailMeta.value?.focus || lmsDetailFocus.value || 'lms_not_started');
    const label = lmsFocusLabelMap[focus] || 'Anggota LMS';
    const year = Number(lmsDetailMeta.value?.year || selectedLmsYear.value || currentLmsYear);
    return `${label} • ${year}`;
});
const kpiDetailCountLabel = computed(() => (kpiDetailContext.value === 'approval_queue' ? 'antrian' : 'anggota'));
const isKpiDetailPastYear = computed(() => {
    const scope = String(kpiDetailMeta.value?.calculation_scope || '').toLowerCase();
    if (scope) {
        return scope === 'past_year';
    }

    const selectedYear = Number(kpiDetailMeta.value?.kpi_year || selectedKpiYear.value || 0);
    const nowYear = new Date().getFullYear();
    return selectedYear > 0 && selectedYear < nowYear;
});

const kpiTeamStats = computed(() => [
    {
        key: 'form-approved',
        context: 'form_approved',
        label: 'KPI Parameter Tersubmit',
        value: Number(summary.value.parameterCoveragePercent || 0),
        unit: '%',
        caption: summary.value.formScopeLabel || 'anggota tahun ini',
        tone: Number(summary.value.parameterCoveragePercent || 0) >= 80 ? 'success' : 'warning',
    },
    {
        key: 'approval-queue',
        context: 'approval_queue',
        label: 'Antrian Approval',
        value: Number(summary.value.approvalQueueCount || 0),
        caption: 'form KPI & update performance',
        tone: Number(summary.value.approvalQueueCount || 0) > 0 ? 'orange' : 'success',
    },
    {
        key: 'ytd-le-80',
        context: 'ytd_le_80',
        label: 'YTD <= 80%',
        value: Number(summary.value.performanceAvailable ? summary.value.ytdLe80Count : 0),
        caption: summary.value.performanceAvailable ? summary.value.ytdPeriodLabel : 'Performance belum tersedia',
        tone: Number(summary.value.ytdLe80Count || 0) > 0 ? 'danger' : 'success',
    },
    {
        key: 'ytd-gt-80',
        context: 'ytd_gt_80',
        label: 'YTD > 80%',
        value: Number(summary.value.performanceAvailable ? summary.value.ytdGt80Count : 0),
        caption: summary.value.performanceAvailable ? summary.value.ytdPeriodLabel : 'Performance belum tersedia',
        tone: Number(summary.value.ytdGt80Count || 0) > 0 ? 'success' : 'info',
    },
]);

const attendanceMix = computed(() => {
    const total = Math.max(1, summary.value.totalMembers);

    return [
        {
            key: 'present',
            label: 'Hadir',
            value: summary.value.presentToday,
            percent: (summary.value.presentToday / total) * 100,
            tone: 'success',
        },
        {
            key: 'absent',
            label: 'Alpha',
            value: summary.value.absentToday,
            percent: (summary.value.absentToday / total) * 100,
            tone: 'danger',
        },
        {
            key: 'leave',
            label: 'Izin/Cuti/Sakit',
            value: summary.value.leaveToday,
            percent: (summary.value.leaveToday / total) * 100,
            tone: 'info',
        },
    ];
});

const hcisInsightText = computed(() => {
    if (!summary.value.attendanceIssues)
        return 'Absensi tim aman. Leader cukup menjaga coverage shift dan validasi checkout berjalan normal.';

    const parts = [];
    if (summary.value.absentToday) parts.push(`${summary.value.absentToday} anggota tidak hadir`);
    if (summary.value.noCheckoutCount) parts.push(`${summary.value.noCheckoutCount} belum checkout`);
    if (summary.value.lateCount) parts.push(`${summary.value.lateCount} kejadian terlambat`);

    return `Perlu follow up: ${parts.join(', ') || `${summary.value.attendanceIssues} issue absensi`}.`;
});

const topKpiMembers = computed(() => {
    return members.value
        .filter((member) => Number(member.kpi.score || 0) > 0)
        .sort((a, b) => Number(b.kpi.score || 0) - Number(a.kpi.score || 0))
        .slice(0, 3);
});

const kpiWatchMembers = computed(() => {
    return members.value
        .filter((member) => Number(member.kpi.score || 0) > 0 || member.kpi.pendingItems.length > 0)
        .sort((a, b) => {
            if (a.kpi.pendingItems.length !== b.kpi.pendingItems.length) {
                return b.kpi.pendingItems.length - a.kpi.pendingItems.length;
            }

            return Number(a.kpi.score || 999) - Number(b.kpi.score || 999);
        })
        .slice(0, 3);
});

function getTeamKpiTrend() {
    const domainTrend = Array.isArray(kpiDomainSummary.value?.trend) ? kpiDomainSummary.value.trend : [];
    if (domainTrend.length) {
        return domainTrend.map((item) => ({
            month: item.month || '-',
            value: Number(item.value || 0),
        }));
    }

    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    return months.map((month) => {
        return {
            month,
            value: 0,
        };
    });
}

const teamKpiTrend = computed(() => getTeamKpiTrend());

const hcisDailyTrend = computed(() => {
    const domainTrend = Array.isArray(hcisDomainSummary.value?.trend) ? hcisDomainSummary.value.trend : [];
    if (domainTrend.length) {
        return domainTrend.map((item) => ({
            label: item.label || '-',
            value: Number(item.value || 0),
        }));
    }

    const labels = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

    const raw = labels.map((label) => {
        const value = members.value.reduce((total, member) => {
            const day = member.attendance.dailyLog.find((item) => item.day === label);
            return total + (day && ['danger', 'warning', 'orange'].includes(day.tone) ? 1 : 0);
        }, 0);

        return { label, value };
    });

    return raw;
});

const hasHcisTrendData = computed(() => {
    const trend = Array.isArray(hcisDomainSummary.value?.trend) ? hcisDomainSummary.value.trend : [];
    return trend.length > 0;
});

const isChartIssueClickable = computed(() => {
    return (
        !globalSummaryLoading.value && !isHcisTrendLoading.value && (hasHcisTrendData.value || hcisTrendAttempted.value)
    );
});

const hcisTrendChartSeries = computed(() => [
    {
        name: 'Issue',
        data: hcisDailyTrend.value.map((item) => Number(item.value || 0)),
    },
]);

const hcisTrendChartOptions = computed(() => ({
    chart: {
        type: 'area',
        toolbar: { show: false },
        zoom: { enabled: false },
        events: {
            dataPointSelection: (_event, _chartContext, config) => {
                if (!isChartIssueClickable.value) {
                    return;
                }

                const index = Number(config?.dataPointIndex ?? -1);
                if (index < 0) {
                    return;
                }

                void openAttendanceIssueDrawerByIndex(index);
            },
        },
    },
    dataLabels: { enabled: false },
    stroke: {
        curve: 'smooth',
        width: 2.6,
    },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 0.45,
            opacityFrom: 0.3,
            opacityTo: 0.05,
            stops: [0, 95, 100],
        },
    },
    markers: {
        size: 3.5,
        colors: ['#ef4444'],
        strokeColors: '#ffffff',
        strokeWidth: 2,
        hover: { size: 5 },
    },
    colors: ['#ef4444'],
    grid: {
        show: false,
        borderColor: 'rgba(148, 163, 184, 0.24)',
        strokeDashArray: 4,
        padding: { top: 4, right: 6, bottom: 2, left: 6 },
    },
    xaxis: {
        categories: hcisDailyTrend.value.map((item) => item.label),
        tooltip: {
            enabled: false,
        },
        labels: {
            style: {
                colors: '#64748b',
                fontSize: '10px',
                fontWeight: 700,
            },
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        min: 0,
        forceNiceScale: true,
        labels: { show: false },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    tooltip: {
        theme: 'light',
        x: {
            formatter: (_value, { dataPointIndex }) => {
                const date = resolveTrendDateByIndex(Number(dataPointIndex ?? 0));
                return formatDateLabel(date);
            },
        },
        y: {
            formatter: (value) => `${Math.round(Number(value || 0))} issue`,
        },
    },
    legend: { show: false },
}));

const teamLmsProgress = computed(() => {
    const domainTrend = Array.isArray(lmsDomainSummary.value?.trend) ? lmsDomainSummary.value.trend : [];
    if (domainTrend.length) {
        return domainTrend.map((item) => ({
            month: item.month || '-',
            period: item.period || '',
            value: Number(item.value || 0),
        }));
    }

    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    return months.map((month) => ({
        month,
        period: '',
        value: 0,
    }));
});

const lmsTrendChartSeries = computed(() => [
    {
        name: 'Avg Jam Selesai',
        data: teamLmsProgress.value.map((item) => Number(item.value || 0)),
    },
]);

const lmsTrendChartOptions = computed(() => ({
    chart: {
        type: 'area',
        toolbar: { show: false },
        zoom: { enabled: false },
    },
    dataLabels: { enabled: false },
    stroke: {
        curve: 'smooth',
        width: 2.6,
    },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 0.42,
            opacityFrom: 0.25,
            opacityTo: 0.03,
            stops: [0, 95, 100],
        },
    },
    markers: {
        size: 3.5,
        colors: ['#14b8a6'],
        strokeColors: '#ffffff',
        strokeWidth: 2,
        hover: { size: 5 },
    },
    colors: ['#14b8a6'],
    grid: {
        show: false,
        borderColor: 'rgba(148, 163, 184, 0.24)',
        strokeDashArray: 4,
        padding: { top: 4, right: 6, bottom: 2, left: 6 },
    },
    xaxis: {
        categories: teamLmsProgress.value.map((item) => item.month),
        labels: {
            style: {
                colors: '#64748b',
                fontSize: '10px',
                fontWeight: 700,
            },
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        min: 0,
        forceNiceScale: true,
        labels: { show: false },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    tooltip: {
        theme: 'light',
        y: {
            formatter: (value) => `${formatNumber(value || 0, ' jam')}`,
        },
    },
    legend: { show: false },
}));

const kpiTrendChartSeries = computed(() => [
    {
        name: 'Achievement',
        data: teamKpiTrend.value.map((item) => Number(item.value || 0)),
    },
]);

const kpiTrendChartOptions = computed(() => ({
    chart: {
        type: 'area',
        toolbar: { show: false },
        zoom: { enabled: false },
        events: {
            dataPointSelection: (_event, _chartContext, config) => {
                const index = Number(config?.dataPointIndex ?? -1);
                if (index < 0) return;
                const period = resolveKpiTrendMonthPeriodByIndex(index);
                openKpiDetailDrawer('trend_month', period);
            },
        },
    },
    dataLabels: { enabled: false },
    stroke: {
        curve: 'smooth',
        width: 2.6,
    },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 0.42,
            opacityFrom: 0.25,
            opacityTo: 0.03,
            stops: [0, 95, 100],
        },
    },
    markers: {
        size: 3.5,
        colors: ['#7c3aed'],
        strokeColors: '#ffffff',
        strokeWidth: 2,
        hover: { size: 5 },
    },
    colors: ['#7c3aed'],
    grid: {
        show: false,
        borderColor: 'rgba(148, 163, 184, 0.24)',
        strokeDashArray: 4,
        padding: { top: 4, right: 6, bottom: 2, left: 6 },
    },
    xaxis: {
        categories: teamKpiTrend.value.map((item) => item.month),
        labels: {
            style: {
                colors: '#64748b',
                fontSize: '10px',
                fontWeight: 700,
            },
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        min: 0,
        max: 100,
        tickAmount: 4,
        labels: { show: false },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    tooltip: {
        theme: 'light',
        y: {
            formatter: (value) => `${Math.round(Number(value || 0))}%`,
        },
    },
    legend: { show: false },
}));

const teamLmsMonthly = computed(() => {
    const months = ['Nov', 'Des', 'Jan', 'Feb', 'Mar', 'Apr'];

    return months.map((month) => {
        const completed = members.value.reduce((total, member) => {
            return total + Number(member.lms.completedMonthly.find((item) => item.month === month)?.value || 0);
        }, 0);

        return {
            month,
            value: completed,
        };
    });
});

const lmsMonthlyChartSeries = computed(() => [
    {
        name: 'Training selesai',
        data: teamLmsMonthly.value.map((item) => Number(item.value || 0)),
    },
]);

const lmsMonthlyChartOptions = computed(() => ({
    chart: {
        type: 'bar',
        toolbar: { show: false },
        zoom: { enabled: false },
    },
    plotOptions: {
        bar: {
            borderRadius: 5,
            columnWidth: '56%',
        },
    },
    dataLabels: { enabled: false },
    colors: ['#14b8a6'],
    grid: {
        show: false,
        borderColor: 'rgba(148, 163, 184, 0.2)',
        strokeDashArray: 4,
        padding: { top: -4, right: 4, bottom: -4, left: 4 },
    },
    xaxis: {
        categories: teamLmsMonthly.value.map((item) => item.month),
        labels: {
            style: {
                colors: '#64748b',
                fontSize: '10px',
                fontWeight: 700,
            },
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        min: 0,
        forceNiceScale: true,
        labels: { show: false },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    tooltip: {
        theme: 'light',
        y: {
            formatter: (value) => `${Math.round(Number(value || 0))} training`,
        },
    },
    legend: { show: false },
}));

const lmsPendingPool = computed(() => {
    if (members.value.length) {
        return members.value.map((member) => {
            const total = Number(member.lms.total || 0);
            const completed = Number(member.lms.completed || 0);
            const progress = Number(member.lms.progress || 0);

            return {
                id: member.id,
                name: member.name,
                total,
                completed,
                progress,
                pending: Math.max(0, total - completed),
            };
        });
    }

    return trainingPendingMembers.value.map((member) => {
        const total = Number(member.lms?.total || 0);
        const completed = Number(member.lms?.completed || 0);
        const progress = Number(member.lms?.progress || 0);

        return {
            id: member.id,
            name: member.name || 'Tanpa Nama',
            total,
            completed,
            progress,
            pending: Math.max(0, total - completed),
        };
    });
});

function lmsRiskMeta(progress) {
    const value = Number(progress || 0);
    if (value < 50) {
        return { riskTone: 'danger', riskLabel: 'Risiko tinggi' };
    }
    if (value < 80) {
        return { riskTone: 'warning', riskLabel: 'Perlu follow up' };
    }

    return { riskTone: 'safe', riskLabel: 'Terpantau' };
}

const lmsFocusMembers = computed(() => {
    return lmsPendingPool.value
        .filter((member) => member.total > 0 && member.pending > 0)
        .sort((a, b) => {
            if (a.progress !== b.progress) {
                return a.progress - b.progress;
            }

            return b.pending - a.pending;
        })
        .slice(0, 3)
        .map((member) => ({
            ...member,
            ...lmsRiskMeta(member.progress),
        }));
});

const lmsRiskSummary = computed(() => {
    const trackedPool = lmsPendingPool.value.filter((member) => member.total > 0);
    const riskCountFromPool = trackedPool.filter(
        (member) => Number(member.pending || 0) > 0 && Number(member.progress || 0) < 80,
    ).length;
    const pendingFromPool = lmsPendingPool.value.reduce((total, member) => total + Number(member.pending || 0), 0);
    const hasSummaryMetrics =
        Number(summary.value.totalTrainingAssigned || 0) > 0 ||
        Number(summary.value.totalTrainingPending || 0) > 0 ||
        Number(summary.value.lmsAtRisk || 0) > 0;

    const riskCount = hasSummaryMetrics ? Number(summary.value.lmsAtRisk || 0) : riskCountFromPool;
    const pendingTotal = hasSummaryMetrics ? Number(summary.value.totalTrainingPending || 0) : pendingFromPool;
    const isNeedFollowUp = riskCount > 0 || pendingTotal > 0;

    return {
        riskCount,
        pendingTotal,
        status: isNeedFollowUp ? 'Perlu follow up' : 'Terpantau',
        tone: isNeedFollowUp ? 'warning' : 'safe',
    };
});

const lmsQuickTip = computed(() => {
    if (!lmsFocusMembers.value.length && lmsRiskSummary.value.pendingTotal > 0) {
        return 'Data pending sudah terdeteksi. Buka daftar anggota tim untuk melihat prioritas follow up terendah.';
    }

    if (!lmsFocusMembers.value.length) {
        return 'Semua training terpantau. Jaga ritme review mingguan agar progress tetap konsisten.';
    }

    if (lmsRiskSummary.value.riskCount >= 3) {
        return 'Prioritaskan coaching untuk anggota berisiko tinggi dan pasang target progress mingguan.';
    }

    return 'Fokus follow up ke anggota dengan progress terendah lebih dulu untuk percepat penyelesaian training.';
});

const fallbackActionItems = computed(() => {
    if (!members.value.length) {
        return (props.overview?.sectionCards || []).slice(0, 3).map((item) => ({
            id: item.key,
            memberId: '',
            section: item.key,
            tone: 'info',
            icon: item.icon || 'bi bi-bell',
            title: item.title,
            description: item.description,
            meta: item.actionLabel || 'Buka panel',
        }));
    }

    const items = [];

    members.value.forEach((member) => {
        member.attendance.issues.forEach((issue) => {
            items.push({
                id: `attendance-${member.id}-${issue.id}`,
                memberId: member.id,
                tone: issue.tone || 'warning',
                icon: issue.icon || 'bi bi-exclamation-circle',
                title: `${member.name} - ${issue.title}`,
                description: issue.description,
                meta: 'HCIS Absensi',
            });
        });

        if (!member.attendance.issues.length && member.attendance.issueCount > 0) {
            items.push({
                id: `attendance-lite-${member.id}`,
                memberId: member.id,
                tone: 'warning',
                icon: 'bi bi-exclamation-circle',
                title: `${member.name} - Perlu cek absensi`,
                description: `${member.attendance.issueCount} issue absensi perlu ditinjau.`,
                meta: 'HCIS Absensi',
            });
        }

        member.kpi.pendingItems.forEach((item) => {
            items.push({
                id: `kpi-${member.id}-${item.id}`,
                memberId: member.id,
                tone: 'warning',
                icon: 'bi bi-graph-up-arrow',
                title: `${member.name} - ${item.title}`,
                description: item.description,
                meta: 'KPI',
            });
        });
    });

    approvalItems.value.forEach((approval) => {
        items.push({
            id: `approval-${approval.id}`,
            memberId: approval.memberId || approval.kode_karyawan || '',
            tone: approval.tone || 'success',
            icon: approval.icon || 'bi bi-check2-square',
            title: `${approval.memberName || approval.member_name || 'Anggota'} - ${approval.title}`,
            description: approval.description,
            meta: approval.meta || 'Approval',
        });
    });

    return items.slice(0, 16);
});

const actionItems = computed(() => {
    if (needsActionLoaded.value) {
        return needsActionRows.value.map((item) => {
            const category = String(item?.category || '').toLowerCase();
            const severity = String(item?.severity || '').toLowerCase();
            const tone =
                item?.tone ||
                (severity === 'critical' ? 'danger' : severity === 'high' ? 'warning' : 'orange');
            const icon = item?.icon || (category === 'hcis' ? 'bi bi-person-exclamation' : 'bi bi-graph-up-arrow');
            const memberName = item?.member_name || item?.memberName || 'Anggota';
            const titleBase = String(item?.title || 'Perlu tindak lanjut');
            const title = titleBase.toLowerCase().includes(memberName.toLowerCase())
                ? titleBase
                : `${memberName} - ${titleBase}`;
            const periodLabel = item?.period_label ? ` | ${item.period_label}` : '';

            return {
                id: item?.id || cryptoRandomId(),
                memberId: item?.member_id || item?.memberId || '',
                tone,
                icon,
                title,
                description: item?.description || '',
                meta: item?.meta || `${category.toUpperCase() || 'ACTION'}${periodLabel}`,
                severity,
                category,
                periodLabel: item?.period_label || '',
                updatedAt: item?.updated_at || '',
                caseBreakdown: {
                    no_checkin: Number(item?.case_breakdown?.no_checkin || 0),
                    no_checkout: Number(item?.case_breakdown?.no_checkout || 0),
                    late: Number(item?.case_breakdown?.late || 0),
                    no_data: Number(item?.case_breakdown?.no_data || 0),
                },
                caseDates: Array.isArray(item?.case_dates) ? item.case_dates : [],
            };
        });
    }

    return fallbackActionItems.value.map((item) => ({
        ...item,
        category: item?.category || '',
        caseBreakdown: item?.caseBreakdown || {
            no_checkin: 0,
            no_checkout: 0,
            late: 0,
            no_data: 0,
        },
        caseDates: Array.isArray(item?.caseDates) ? item.caseDates : [],
    }));
});

const actionItemCount = computed(() => {
    if (needsActionLoaded.value) {
        return Number(needsActionSummary.value?.total_active_items || needsActionPagination.value?.total || 0);
    }

    return actionItems.value.length;
});

function normalizeMember(member) {
    const attendance = member.attendance || {};
    const kpi = member.kpi || {};
    const lms = member.lms || {};
    const overtime = member.overtime || {};
    const leave = member.leave || {};
    const shift = member.shift || {};
    const rawApprovals = Array.isArray(member.approvals) ? member.approvals : [];
    const activeApprovalCount = rawApprovals.filter((approval) => !Boolean(approval?.is_expired)).length;

    const normalized = {
        id: member.id || member.kode_karyawan || member.name || cryptoRandomId(),
        name: member.name || member.nama || 'Tanpa Nama',
        initials: member.initials || getInitials(member.name || member.nama || 'U'),
        position: member.position || member.jabatan || '-',
        department: member.department || member.departemen || '-',
        shift: {
            name: shift.name || shift.shift_name || 'No Shift',
            time: shift.time || shift.shift_time || '-',
        },
        attendance: {
            todayStatus: attendance.todayStatus || attendance.today_status || 'present',
            todayLabel:
                attendance.todayLabel ||
                attendance.today_label ||
                statusLabel(attendance.todayStatus || attendance.today_status || 'present'),
            checkIn: attendance.checkIn || attendance.check_in || null,
            checkOut: attendance.checkOut || attendance.check_out || null,
            workDays: Number(attendance.workDays ?? attendance.work_days ?? 0),
            alphaCount: Number(
                attendance.alphaCount ??
                    attendance.alpha_count ??
                    attendance.absentCount ??
                    attendance.absent_count ??
                    0,
            ),
            absentCount: Number(
                attendance.absentCount ??
                    attendance.absent_count ??
                    attendance.alphaCount ??
                    attendance.alpha_count ??
                    0,
            ),
            lateCount: Number(attendance.lateCount ?? attendance.late_count ?? 0),
            lateMinutes: Number(attendance.lateMinutes ?? attendance.late_minutes ?? 0),
            earlyOutCount: Number(attendance.earlyOutCount ?? attendance.early_out_count ?? 0),
            overtimeHours: Number(attendance.overtimeHours ?? attendance.overtime_hours ?? 0),
            permissionDays: Number(attendance.permissionDays ?? attendance.permission_days ?? 0),
            sickDays: Number(attendance.sickDays ?? attendance.sick_days ?? 0),
            leaveDays: Number(attendance.leaveDays ?? attendance.leave_days ?? 0),
            noCheckoutCount: Number(attendance.noCheckoutCount ?? attendance.no_checkout_count ?? 0),
            issueCount: Number(attendance.issueCount ?? attendance.issue_count ?? 0),
            issues: Array.isArray(attendance.issues) ? attendance.issues : [],
            dailyLog: Array.isArray(attendance.dailyLog || attendance.daily_log)
                ? attendance.dailyLog || attendance.daily_log
                : [],
        },
        kpi: {
            year: Number(kpi.year ?? kpi.kpi_year ?? selectedKpiYear.value ?? new Date().getFullYear()),
            selectedPeriod: String(kpi.selectedPeriod ?? kpi.selected_period ?? '').trim(),
            score: Number(kpi.score ?? 0),
            grade: kpi.grade || kpi.gradeLabel || '',
            achievement: Number(kpi.achievement ?? 0),
            parameterSubmitted: Boolean(kpi.parameterSubmitted ?? kpi.parameter_submitted ?? false),
            performanceSubmitted: Boolean(kpi.performanceSubmitted ?? kpi.performance_submitted ?? false),
            parameterPeriod: kpi.parameterPeriod || kpi.parameter_period || 'Periode KPI',
            performancePeriod: kpi.performancePeriod || kpi.performance_period || 'Periode Performance',
            deadlineText: kpi.deadlineText || kpi.deadline_text || '',
            deadlineTone: kpi.deadlineTone || kpi.deadline_tone || 'is-info',
            pendingItems: Array.isArray(kpi.pendingItems || kpi.pending_items)
                ? kpi.pendingItems || kpi.pending_items
                : [],
            parameters: Array.isArray(kpi.parameters) ? kpi.parameters : [],
            monthlyAchievement: Array.isArray(kpi.monthlyAchievement || kpi.monthly_achievement)
                ? kpi.monthlyAchievement || kpi.monthly_achievement
                : [],
        },
        lms: {
            completed: Number(lms.completed ?? lms.completed_courses ?? 0),
            total: Number(lms.total ?? lms.enrolled_courses ?? 0),
            progress: Number(
                lms.progress ?? calcProgress(lms.completed ?? lms.completed_courses, lms.total ?? lms.enrolled_courses),
            ),
            averageScore: lms.averageScore ?? lms.average_score ?? 0,
            learningGain: lms.learningGain ?? lms.learning_gain ?? 0,
            trainings: Array.isArray(lms.trainings || lms.learning_progress)
                ? lms.trainings || lms.learning_progress
                : [],
            completedMonthly: Array.isArray(lms.completedMonthly || lms.completed_monthly)
                ? lms.completedMonthly || lms.completed_monthly
                : [],
        },
        overtime: {
            totalHours: Number(overtime.totalHours ?? overtime.total_hours ?? attendance.overtimeHours ?? 0),
            beforeWorkHours: Number(overtime.beforeWorkHours ?? overtime.before_work_hours ?? 0),
            afterWorkHours: Number(overtime.afterWorkHours ?? overtime.after_work_hours ?? 0),
            holidayHours: Number(overtime.holidayHours ?? overtime.holiday_hours ?? 0),
            weeklyTrend: Array.isArray(overtime.weeklyTrend || overtime.weekly_trend)
                ? overtime.weeklyTrend || overtime.weekly_trend
                : [],
        },
        leave: {
            quota: Number(leave.quota ?? leave.total_quota ?? 0),
            remaining: Number(leave.remaining ?? leave.remaining_quota ?? 0),
            used: Number(leave.used ?? leave.used_quota ?? 0),
        },
        approvalCount: Number(member.approvalCount ?? member.approval_count ?? activeApprovalCount),
        approvals: rawApprovals,
        followUpSummary: member.followUpSummary || member.follow_up_summary || '',
        links: member.links || {},
    };

    // Safety guard: jika detail approvals tersedia, selalu gunakan jumlah approval aktif (non-expired).
    if (normalized.approvals.length) {
        normalized.approvalCount = activeApprovalCount;
    }

    normalized.lms.pending = Math.max(0, normalized.lms.total - normalized.lms.completed);

    const attendanceIssueCount = Math.max(normalized.attendance.issueCount, normalized.attendance.issues.length);
    const kpiIssueCount = normalized.kpi.pendingItems.length;
    const lmsIssueCount = normalized.lms.progress < 100 ? 1 : 0;
    const approvalIssueCount = normalized.approvalCount;

    normalized.issueCount = attendanceIssueCount + kpiIssueCount + lmsIssueCount + approvalIssueCount;

    return normalized;
}

let memberSearchDebounce = null;
let attendanceStatusSearchDebounce = null;
let kpiDetailSearchDebounce = null;
let lmsDetailSearchDebounce = null;
const suppressMemberSearchWatch = ref(false);

async function loadNeedsAction(page = 1, options = {}) {
    const targetPage = Math.max(1, Number(page || 1));
    const force = Boolean(options?.force);

    if (needsActionLoading.value && needsActionPagination.value.current_page === targetPage && !force) {
        return;
    }

    needsActionLoading.value = true;
    needsActionError.value = '';

    try {
        const response = await axios.get('/homepage/leader-dashboard/needs-action', {
            params: {
                page: targetPage,
                per_page: needsActionPagination.value.per_page || 10,
                category: needsActionCategory.value || 'all',
                search: '',
                kpi_year: normalizeKpiYear(selectedKpiYear.value),
            },
        });

        needsActionRows.value = Array.isArray(response?.data?.data?.items) ? response.data.data.items : [];
        needsActionSummary.value = response?.data?.data?.summary || needsActionSummary.value;
        needsActionPagination.value = response?.data?.pagination || needsActionPagination.value;
        needsActionLoaded.value = true;
    } catch (error) {
        console.error('Failed to load needs action', error);
        needsActionError.value = 'Data perlu tindakan belum berhasil dimuat. Coba refresh manual.';
    } finally {
        needsActionLoading.value = false;
    }
}

function goToNeedsActionPage(step) {
    if (!needsActionLoaded.value) {
        return;
    }

    const nextPage = Math.max(1, Number(needsActionPagination.value.current_page || 1) + Number(step || 0));
    if (nextPage === needsActionPagination.value.current_page) {
        return;
    }

    void loadNeedsAction(nextPage);
}

function refreshNeedsAction() {
    void loadNeedsAction(needsActionPagination.value.current_page || 1, { force: true });
}

async function loadMembers(page = 1) {
    const targetPage = Math.max(1, Number(page || 1));
    if (memberSectionLoading.value && memberSectionPagination.value.current_page === targetPage) {
        return;
    }

    memberSectionRequested.value = true;
    memberSectionLoading.value = true;

    try {
        const response = await axios.get('/homepage/leader-dashboard/members', {
            params: {
                page: targetPage,
                per_page: memberSectionPagination.value.per_page || 10,
                search: searchKeyword.value || '',
                attendance: selectedAttendance.value,
                kpi: selectedKpi.value,
                lms: selectedLms.value,
                kpi_year: normalizeKpiYear(selectedKpiYear.value),
            },
        });

        memberSectionData.value = Array.isArray(response?.data?.data) ? response.data.data.map(normalizeMember) : [];
        memberSectionPagination.value = response?.data?.pagination || memberSectionPagination.value;
        memberSectionLoaded.value = true;
        openedMemberId.value = null;
    } catch (error) {
        console.error('Failed to load team members', error);
    } finally {
        memberSectionLoading.value = false;
    }
}

async function ensureMembersLoaded() {
    if (memberSectionLoaded.value) {
        return;
    }

    await loadMembers(memberSectionPagination.value.current_page || 1);
}

function scrollToTeamListSection() {
    const element = teamListSectionRef.value;
    if (!element || typeof element.scrollIntoView !== 'function') {
        return;
    }

    element.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
}

function resolveActionMemberSearchTerm(action) {
    const directName = String(action?.memberName || action?.member_name || '').trim();
    if (directName) {
        return directName;
    }

    const title = String(action?.title || '').trim();
    if (title.includes(' - ')) {
        return String(title.split(' - ')[0] || '').trim();
    }

    return title;
}

async function findMemberPageForAction(action) {
    const normalizedMemberId = normalizeMemberDetailMemberId(action?.memberId || action?.member_id || '');
    const searchTerm = resolveActionMemberSearchTerm(action);
    const perPage = 100;

    const response = await axios.get('/homepage/leader-dashboard/members', {
        params: {
            page: 1,
            per_page: perPage,
            search: searchTerm,
            attendance: 'all',
            kpi: 'all',
            lms: 'all',
            kpi_year: normalizeKpiYear(selectedKpiYear.value),
        },
    });

    const rows = Array.isArray(response?.data?.data) ? response.data.data.map(normalizeMember) : [];
    const pagination = response?.data?.pagination || {
        current_page: 1,
        last_page: 1,
        per_page: perPage,
        total: rows.length,
        has_prev: false,
        has_next: false,
    };

    let found = rows.find((member) => normalizeMemberDetailMemberId(member?.id) === normalizedMemberId);
    if (!found && searchTerm) {
        const normalizedSearchName = searchTerm.trim().toLowerCase();
        found = rows.find((member) => String(member?.name || '').trim().toLowerCase() === normalizedSearchName) || null;
    }
    if (!found && rows.length === 1) {
        found = rows[0];
    }

    return {
        found,
        rows,
        pagination,
        searchTerm,
    };
}

async function loadShiftBoard(date = selectedShiftDate.value || new Date().toISOString().slice(0, 10)) {
    const targetDate = date || new Date().toISOString().slice(0, 10);
    if (shiftSectionLoading.value && shiftSectionLoadedDate.value === targetDate) {
        return;
    }

    if (shiftSectionLoaded.value && shiftSectionLoadedDate.value === targetDate && shiftSectionData.value.length) {
        return;
    }

    shiftSectionRequested.value = true;
    shiftSectionLoading.value = true;

    try {
        const response = await axios.get('/homepage/leader-dashboard/shifts', {
            params: {
                date: targetDate,
            },
        });

        shiftSectionData.value = Array.isArray(response?.data?.data) ? response.data.data : [];
        shiftSectionLoaded.value = true;
        shiftSectionLoadedDate.value = response?.data?.meta?.date || targetDate;
        selectedShiftDate.value = shiftSectionLoadedDate.value;
        selectedShiftKey.value = shiftSectionData.value[0]?.key || shiftSectionData.value[0]?.name || '';
        selectedShiftDetailData.value = null;
        selectedShiftDetailPage.value = 1;
        shiftDetailPagination.value = {
            current_page: 1,
            last_page: 1,
            per_page: shiftDetailPagination.value.per_page || 8,
            total: 0,
            has_prev: false,
            has_next: false,
        };
        shiftDetailCache.value = {};
    } catch (error) {
        console.error('Failed to load shift board', error);
    } finally {
        shiftSectionLoading.value = false;
    }
}

async function loadApprovals(page = approvalSectionPagination.value.current_page || 1) {
    const targetPage = Math.max(1, page);
    if (approvalSectionLoading.value && approvalSectionPagination.value.current_page === targetPage) {
        return;
    }

    approvalSectionRequested.value = true;
    approvalSectionLoading.value = true;

    try {
        const response = await axios.get('/homepage/leader-dashboard/approvals', {
            params: {
                page: targetPage,
                per_page: approvalSectionPagination.value.per_page || 6,
            },
        });

        approvalSectionData.value = Array.isArray(response?.data?.data) ? response.data.data : [];
        approvalSectionPagination.value = response?.data?.pagination || approvalSectionPagination.value;
        approvalSectionLoaded.value = true;
    } catch (error) {
        console.error('Failed to load approvals', error);
    } finally {
        approvalSectionLoading.value = false;
    }
}

async function ensureApprovalsLoaded() {
    if (approvalSectionLoaded.value) {
        return;
    }

    await loadApprovals(1);
}

function isCanceledRequest(error) {
    return (
        error?.name === 'CanceledError' ||
        error?.name === 'AbortError' ||
        error?.code === 'ERR_CANCELED' ||
        (typeof axios.isCancel === 'function' && axios.isCancel(error))
    );
}

function startSummaryRequest(key) {
    const currentController = summaryRequestControllers.value[key];
    if (currentController) {
        currentController.abort();
    }

    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    summaryRequestControllers.value = {
        ...summaryRequestControllers.value,
        [key]: controller,
    };

    const requestVersion = Number(summaryRequestVersions.value[key] || 0) + 1;
    summaryRequestVersions.value = {
        ...summaryRequestVersions.value,
        [key]: requestVersion,
    };

    return { controller, requestVersion };
}

function isLatestSummaryRequest(key, requestVersion) {
    return Number(summaryRequestVersions.value[key] || 0) === Number(requestVersion || 0);
}

async function loadGlobalSummary() {
    const { controller, requestVersion } = startSummaryRequest('core');
    globalSummaryLoading.value = true;

    try {
        const response = await axios.get('/homepage/leader-dashboard/summary', {
            signal: controller?.signal,
        });
        if (!isLatestSummaryRequest('core', requestVersion)) {
            return;
        }

        globalSummary.value = response?.data?.data?.summary || null;
    } catch (error) {
        if (isCanceledRequest(error)) {
            return;
        }

        console.warn('Failed to load global summary', error);
    } finally {
        if (isLatestSummaryRequest('core', requestVersion)) {
            globalSummaryLoading.value = false;
        }
    }
}

async function loadHcisSummaryDomain(force = false) {
    if (!force && hcisDomainSummary.value) {
        return;
    }
    if (isHcisTrendLoading.value) {
        return;
    }

    const { controller, requestVersion } = startSummaryRequest('hcis');
    isHcisTrendLoading.value = true;

    try {
        const response = await axios.get('/homepage/leader-dashboard/summary/hcis', {
            signal: controller?.signal,
        });
        if (!isLatestSummaryRequest('hcis', requestVersion)) {
            return;
        }

        hcisDomainSummary.value = response?.data?.data || null;
    } catch (error) {
        if (isCanceledRequest(error)) {
            return;
        }

        console.warn('Failed to load HCIS domain summary', error);
    } finally {
        if (isLatestSummaryRequest('hcis', requestVersion)) {
            isHcisTrendLoading.value = false;
            hcisTrendAttempted.value = true;
        }
    }
}

async function loadKpiSummaryDomain(force = false) {
    if (!force && kpiDomainSummary.value) {
        return;
    }
    if (isKpiDomainLoading.value) {
        return;
    }

    const { controller, requestVersion } = startSummaryRequest('kpi');
    isKpiDomainLoading.value = true;
    const requestedYear = normalizeKpiYear(selectedKpiYear.value);

    try {
        const response = await axios.get('/homepage/leader-dashboard/summary/kpi', {
            signal: controller?.signal,
            params: {
                year: requestedYear,
            },
        });
        if (!isLatestSummaryRequest('kpi', requestVersion)) {
            return;
        }

        kpiDomainSummary.value = response?.data?.data || null;
        const resolvedYear = Number(response?.data?.data?.stats?.kpiYear || 0);
        if (resolvedYear >= 2000 && resolvedYear <= 2100) {
            selectedKpiYear.value = resolvedYear;
        }
    } catch (error) {
        if (isCanceledRequest(error)) {
            return;
        }

        console.warn('Failed to load KPI domain summary', error);
    } finally {
        if (isLatestSummaryRequest('kpi', requestVersion)) {
            isKpiDomainLoading.value = false;
        }
    }
}

async function loadLmsSummaryDomain(force = false) {
    if (!force && lmsDomainSummary.value) {
        return;
    }
    if (isLmsDomainLoading.value) {
        return;
    }

    const { controller, requestVersion } = startSummaryRequest('lms');
    isLmsDomainLoading.value = true;

    try {
        const response = await axios.get('/homepage/leader-dashboard/summary/lms', {
            signal: controller?.signal,
            params: {
                year: Number(selectedLmsYear.value || currentLmsYear),
            },
        });
        if (!isLatestSummaryRequest('lms', requestVersion)) {
            return;
        }

        lmsDomainSummary.value = response?.data?.data || null;
        const resolvedYear = Number(response?.data?.data?.stats?.year || 0);
        if (resolvedYear >= 2000 && resolvedYear <= 2100) {
            selectedLmsYear.value = resolvedYear;
        }
    } catch (error) {
        if (isCanceledRequest(error)) {
            return;
        }

        console.warn('Failed to load LMS domain summary', error);
    } finally {
        if (isLatestSummaryRequest('lms', requestVersion)) {
            isLmsDomainLoading.value = false;
        }
    }
}

async function loadHeatmap(page = heatmapSectionPagination.value.current_page || 1) {
    const targetPage = Math.max(1, page);
    if (heatmapSectionLoading.value && heatmapSectionPagination.value.current_page === targetPage) {
        return;
    }

    heatmapSectionRequested.value = true;
    heatmapSectionLoading.value = true;

    try {
        const params = {
            page: targetPage,
            per_page: heatmapSectionPagination.value.per_page || 8,
        };

        if (heatmapWeekStart.value) {
            params.start_date = heatmapWeekStart.value;
        }
        if (heatmapWeekEnd.value) {
            params.end_date = heatmapWeekEnd.value;
        }

        const response = await axios.get('/homepage/leader-dashboard/heatmap', { params });

        heatmapSectionData.value = Array.isArray(response?.data?.data) ? response.data.data : [];
        heatmapSectionPagination.value = response?.data?.pagination || heatmapSectionPagination.value;
        heatmapDateRangeLabel.value = response?.data?.dateRange?.formatted || '';

        if (response?.data?.dateRange) {
            heatmapWeekStart.value = response.data.dateRange.start_date;
            heatmapWeekEnd.value = response.data.dateRange.end_date;
        }

        heatmapSectionLoaded.value = true;
    } catch (error) {
        console.error('Failed to load heatmap', error);
    } finally {
        heatmapSectionLoading.value = false;
    }
}

async function ensureHeatmapLoaded() {
    if (heatmapSectionLoaded.value) {
        return;
    }

    await loadHeatmap(1);
}

async function loadTrainingPending(page = trainingSectionPagination.value.current_page || 1) {
    const targetPage = Math.max(1, page);
    if (trainingSectionLoading.value && trainingSectionPagination.value.current_page === targetPage) {
        return;
    }

    trainingSectionRequested.value = true;
    trainingSectionLoading.value = true;

    try {
        const response = await axios.get('/homepage/leader-dashboard/training-pending', {
            params: {
                page: targetPage,
                per_page: trainingSectionPagination.value.per_page || 6,
            },
        });

        trainingSectionData.value = Array.isArray(response?.data?.data) ? response.data.data : [];
        trainingSectionPagination.value = response?.data?.pagination || trainingSectionPagination.value;
        trainingSectionLoaded.value = true;
    } catch (error) {
        console.error('Failed to load training pending', error);
    } finally {
        trainingSectionLoading.value = false;
    }
}

async function ensureTrainingLoaded() {
    if (trainingSectionLoaded.value) {
        return;
    }

    await loadTrainingPending(1);
}

function normalizeTeamCalendarYear(value) {
    const year = Number(value || 0);
    const nowYear = new Date().getFullYear();
    if (!Number.isInteger(year) || year < 2000 || year > 2100) {
        return nowYear;
    }
    return year;
}

function normalizeTeamCalendarMonth(value) {
    const month = Number(value || 0);
    const nowMonth = new Date().getMonth() + 1;
    if (!Number.isInteger(month) || month < 1 || month > 12) {
        return nowMonth;
    }
    return month;
}

function getTeamCalendarCutoffPeriod(date = new Date()) {
    const sourceDate = date instanceof Date ? date : new Date(date);
    const periodDate = new Date(sourceDate);

    if (sourceDate.getDate() < 16) {
        periodDate.setMonth(periodDate.getMonth() - 1);
    }

    return {
        year: periodDate.getFullYear(),
        month: periodDate.getMonth() + 1,
    };
}

function getTeamCalendarMonthCacheKey(year, month) {
    return `${normalizeTeamCalendarYear(year)}|${normalizeTeamCalendarMonth(month)}`;
}

function getTeamCalendarDayDetailCacheKey(date, page = 1, filter = 'all', search = '') {
    return `${String(date || '')}|${Math.max(1, Number(page || 1))}|${String(filter || 'all').toLowerCase()}|${String(
        search || '',
    )
        .trim()
        .toLowerCase()}`;
}

function setTeamCalendarFilter(key) {
    const normalized = ['all', 'problem', 'late', 'no-in-out', 'izin-cuti'].includes(String(key)) ? String(key) : 'all';
    if (normalized === teamCalendarActiveFilter.value) {
        return;
    }
    teamCalendarActiveFilter.value = normalized;
}

async function loadTeamCalendarMonth(options = {}) {
    const defaultPeriod = getTeamCalendarCutoffPeriod();
    const year = normalizeTeamCalendarYear(
        Number(options?.year ?? teamCalendarSelectedYear.value ?? defaultPeriod.year),
    );
    const month = normalizeTeamCalendarMonth(
        Number(options?.month ?? teamCalendarSelectedMonth.value ?? defaultPeriod.month),
    );
    const force = Boolean(options?.force ?? false);
    const cacheKey = getTeamCalendarMonthCacheKey(year, month);

    teamCalendarMonthRequested.value = true;
    teamCalendarMonthError.value = '';
    teamCalendarSelectedYear.value = year;
    teamCalendarSelectedMonth.value = month;

    if (!force && teamCalendarMonthCache.value[cacheKey]) {
        teamCalendarMonthData.value = teamCalendarMonthCache.value[cacheKey];
        teamCalendarMonthLoaded.value = true;
        teamCalendarMonthLoading.value = false;
        return;
    }

    if (teamCalendarMonthLoading.value && teamCalendarMonthRequestingKey.value === cacheKey) {
        return;
    }

    if (teamCalendarMonthController.value) {
        teamCalendarMonthController.value.abort();
    }
    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    teamCalendarMonthController.value = controller;
    teamCalendarMonthLoading.value = true;
    teamCalendarMonthRequestingKey.value = cacheKey;

    try {
        const response = await axios.get('/homepage/leader-dashboard/calendar/month', {
            signal: controller?.signal,
            params: { year, month },
        });

        if (teamCalendarMonthRequestingKey.value !== cacheKey) {
            return;
        }

        const payload = {
            data: response?.data?.data || { days: [] },
            stats: response?.data?.stats || {},
            meta: response?.data?.meta || {},
        };

        teamCalendarMonthCache.value = {
            ...teamCalendarMonthCache.value,
            [cacheKey]: payload,
        };
        teamCalendarMonthData.value = payload;
        teamCalendarMonthLoaded.value = true;

        const resolvedYear = Number(payload?.meta?.year || 0);
        const resolvedMonth = Number(payload?.meta?.month || 0);
        if (resolvedYear >= 2000 && resolvedYear <= 2100) {
            teamCalendarSelectedYear.value = resolvedYear;
        }
        if (resolvedMonth >= 1 && resolvedMonth <= 12) {
            teamCalendarSelectedMonth.value = resolvedMonth;
        }
    } catch (error) {
        if (isCanceledRequest(error)) {
            return;
        }
        if (teamCalendarMonthRequestingKey.value !== cacheKey) {
            return;
        }
        console.error('Failed to load team cutoff calendar month', error);
        teamCalendarMonthError.value = 'Gagal memuat kalender cutoff tim. Silakan coba lagi.';
    } finally {
        if (teamCalendarMonthRequestingKey.value === cacheKey) {
            teamCalendarMonthLoading.value = false;
            teamCalendarMonthRequestingKey.value = '';
        }
    }
}

async function ensureTeamCalendarMonthLoaded() {
    if (teamCalendarMonthLoaded.value || teamCalendarMonthLoading.value) {
        return;
    }

    await loadTeamCalendarMonth({
        year: teamCalendarSelectedYear.value,
        month: teamCalendarSelectedMonth.value,
    });
}

function attachTeamCalendarObserver() {
    if (typeof window === 'undefined' || typeof IntersectionObserver === 'undefined') {
        return;
    }

    if (!teamCalendarPanelRef.value) {
        return;
    }

    if (teamCalendarPanelObserver.value) {
        teamCalendarPanelObserver.value.disconnect();
    }

    teamCalendarPanelObserver.value = new IntersectionObserver(
        (entries) => {
            const targetEntry = entries.find((entry) => entry.isIntersecting);
            if (!targetEntry) {
                return;
            }

            void ensureTeamCalendarMonthLoaded();
            if (teamCalendarPanelObserver.value) {
                teamCalendarPanelObserver.value.disconnect();
                teamCalendarPanelObserver.value = null;
            }
        },
        {
            root: null,
            rootMargin: '120px 0px',
            threshold: 0.12,
        },
    );

    teamCalendarPanelObserver.value.observe(teamCalendarPanelRef.value);
}

function retryTeamCalendarMonthLoad() {
    void loadTeamCalendarMonth({
        year: teamCalendarSelectedYear.value,
        month: teamCalendarSelectedMonth.value,
        force: true,
    });
}

async function loadTeamCalendarDayDetail(date, page = 1, options = {}) {
    const targetDate = String(date || '').trim();
    if (!targetDate) {
        return;
    }
    const targetPage = Math.max(1, Number(page || 1));
    const filter =
        String(options?.filter ?? 'all')
            .trim()
            .toLowerCase() || 'all';
    const search = String(options?.search ?? '').trim();
    const cacheKey = getTeamCalendarDayDetailCacheKey(targetDate, targetPage, filter, search);

    teamCalendarDayDetailDrawerOpen.value = true;
    teamCalendarDayDetailActiveDate.value = targetDate;
    teamCalendarDayDetailError.value = '';

    const cached = teamCalendarDayDetailCache.value[cacheKey];
    if (cached) {
        teamCalendarDayDetailRows.value = cached.data || [];
        teamCalendarDayDetailPagination.value = cached.pagination || teamCalendarDayDetailPagination.value;
        teamCalendarDayDetailMeta.value = cached.meta || teamCalendarDayDetailMeta.value;
        return;
    }

    if (teamCalendarDayDetailLoading.value && teamCalendarDayDetailRequestingKey.value === cacheKey) {
        return;
    }

    if (teamCalendarDayDetailController.value) {
        teamCalendarDayDetailController.value.abort();
    }
    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    teamCalendarDayDetailController.value = controller;

    teamCalendarDayDetailLoading.value = true;
    teamCalendarDayDetailRequestingKey.value = cacheKey;
    teamCalendarDayDetailRows.value = [];

    try {
        const response = await axios.get('/homepage/leader-dashboard/calendar/day-detail', {
            signal: controller?.signal,
            params: {
                date: targetDate,
                page: targetPage,
                per_page: teamCalendarDayDetailPagination.value.per_page || 10,
                filter,
                search,
            },
        });

        const payload = {
            data: Array.isArray(response?.data?.data) ? response.data.data : [],
            pagination: response?.data?.pagination || teamCalendarDayDetailPagination.value,
            meta: response?.data?.meta || teamCalendarDayDetailMeta.value,
        };

        teamCalendarDayDetailCache.value = {
            ...teamCalendarDayDetailCache.value,
            [cacheKey]: payload,
        };

        if (teamCalendarDayDetailRequestingKey.value !== cacheKey) {
            return;
        }

        teamCalendarDayDetailRows.value = payload.data;
        teamCalendarDayDetailPagination.value = payload.pagination;
        teamCalendarDayDetailMeta.value = payload.meta;
    } catch (error) {
        if (isCanceledRequest(error)) {
            return;
        }
        if (teamCalendarDayDetailRequestingKey.value !== cacheKey) {
            return;
        }
        console.error('Failed to load team cutoff calendar day detail', error);
        teamCalendarDayDetailError.value = 'Gagal memuat detail tanggal. Silakan coba lagi.';
        teamCalendarDayDetailRows.value = [];
    } finally {
        if (teamCalendarDayDetailRequestingKey.value === cacheKey) {
            teamCalendarDayDetailLoading.value = false;
            teamCalendarDayDetailRequestingKey.value = '';
        }
    }
}

function openTeamCalendarDayDetail(day) {
    const date = String(day?.date || '').trim();
    if (!date) {
        return;
    }
    teamCalendarDayDetailActiveDate.value = date;
    void loadTeamCalendarDayDetail(date, 1, { filter: 'all', search: '' });
}

function closeTeamCalendarDayDetailDrawer() {
    teamCalendarDayDetailDrawerOpen.value = false;
}

function retryTeamCalendarDayDetailLoad() {
    if (!teamCalendarDayDetailActiveDate.value) {
        return;
    }
    const page = Number(teamCalendarDayDetailPagination.value.current_page || 1);
    void loadTeamCalendarDayDetail(teamCalendarDayDetailActiveDate.value, page, { filter: 'all', search: '' });
}

function goToTeamCalendarDayDetailPage(step) {
    if (!teamCalendarDayDetailDrawerOpen.value) {
        return;
    }
    const currentPage = Number(teamCalendarDayDetailPagination.value.current_page || 1);
    const nextPage = Math.max(1, currentPage + Number(step || 0));
    if (nextPage === currentPage || !teamCalendarDayDetailActiveDate.value) {
        return;
    }

    void loadTeamCalendarDayDetail(teamCalendarDayDetailActiveDate.value, nextPage, { filter: 'all', search: '' });
}

function goToApprovalPage(step) {
    if (!approvalSectionLoaded.value) {
        return;
    }

    const nextPage = Math.max(1, Number(approvalSectionPagination.value.current_page || 1) + Number(step || 0));
    if (nextPage === approvalSectionPagination.value.current_page) {
        return;
    }

    void loadApprovals(nextPage);
}

function goToHeatmapPage(step) {
    if (!heatmapSectionLoaded.value) {
        return;
    }

    const nextPage = Math.max(1, Number(heatmapSectionPagination.value.current_page || 1) + Number(step || 0));
    if (nextPage === heatmapSectionPagination.value.current_page) {
        return;
    }

    void loadHeatmap(nextPage);
}

function goToPreviousHeatmapWeek() {
    if (!heatmapWeekStart.value) {
        return;
    }

    const start = new Date(heatmapWeekStart.value);
    const newStart = new Date(start.getTime() - 7 * 24 * 60 * 60 * 1000);
    const newEnd = new Date(start.getTime() - 1000);

    heatmapWeekStart.value = newStart.toISOString().split('T')[0];
    heatmapWeekEnd.value = newEnd.toISOString().split('T')[0];
    heatmapSectionPagination.value.current_page = 1;

    void loadHeatmap(1);
}

function goToNextHeatmapWeek() {
    if (!heatmapWeekEnd.value) {
        return;
    }

    const end = new Date(heatmapWeekEnd.value);
    const newEnd = new Date(end.getTime() + 7 * 24 * 60 * 60 * 1000);
    const newStart = new Date(end.getTime() + 1000);

    heatmapWeekStart.value = newStart.toISOString().split('T')[0];
    heatmapWeekEnd.value = newEnd.toISOString().split('T')[0];
    heatmapSectionPagination.value.current_page = 1;

    void loadHeatmap(1);
}

function goToMemberPage(step) {
    if (!memberSectionLoaded.value) {
        return;
    }

    const nextPage = Math.max(1, Number(memberSectionPagination.value.current_page || 1) + Number(step || 0));
    if (nextPage === memberSectionPagination.value.current_page) {
        return;
    }

    void loadMembers(nextPage);
}

function goToTrainingPage(step) {
    if (!trainingSectionLoaded.value) {
        return;
    }

    const nextPage = Math.max(1, Number(trainingSectionPagination.value.current_page || 1) + Number(step || 0));
    if (nextPage === trainingSectionPagination.value.current_page) {
        return;
    }

    void loadTrainingPending(nextPage);
}

async function bootstrapLeaderDashboardParallel() {
    // const initialShiftDate = selectedShiftDate.value || new Date().toISOString().slice(0, 10);

    await Promise.allSettled([loadGlobalSummary(), loadNeedsAction(1)]);
    await new Promise((resolve) => setTimeout(resolve, 0));

    await Promise.allSettled([
        loadHcisSummaryDomain(),
        loadKpiSummaryDomain(),
        loadLmsSummaryDomain(),
        loadTeamCalendarMonth(),
        // loadShiftBoard(initialShiftDate),
        loadMembers(1),
        loadApprovals(1),
        loadHeatmap(1),
        loadTrainingPending(1),
    ]);
}

function handleHcisCardInteract() {
    void loadHcisSummaryDomain();
}

function handleKpiCardInteract() {
    void loadKpiSummaryDomain();
}

function normalizeKpiYear(value) {
    const year = Number(value || 0);
    const nowYear = new Date().getFullYear();
    if (!Number.isInteger(year) || year < 2000 || year > 2100) {
        return nowYear;
    }
    return year;
}

function applyKpiYear(year) {
    const normalizedYear = normalizeKpiYear(year);
    if (normalizedYear === selectedKpiYear.value) {
        return;
    }

    selectedKpiYear.value = normalizedYear;
    kpiDomainSummary.value = null;
    kpiDetailCache.value = {};
    kpiMemberDetailCache.value = {};
    kpiMemberDetailErrorMap.value = {};
    kpiDetailOpenedMemberId.value = '';
    kpiMemberModeMap.value = {};
    kpiMemberPeriodMap.value = {};

    void loadKpiSummaryDomain(true);
    void loadNeedsAction(1, { force: true });

    if (kpiDetailDrawerOpen.value) {
        void loadKpiDetails(kpiDetailMode.value, 1, {
            context: kpiDetailContext.value,
            month: kpiDetailMonth.value,
            search: kpiDetailSearchKeyword.value,
        });
    }

    if (openedMemberId.value) {
        void fetchMemberDetail(openedMemberId.value);
    }

    if (memberSectionLoaded.value || memberSectionRequested.value) {
        void loadMembers(memberSectionPagination.value.current_page || 1);
    }
}

function handleKpiYearChange(event) {
    applyKpiYear(event?.target?.value);
}

function normalizeLmsYear(value) {
    const year = Number(value || 0);
    const nowYear = new Date().getFullYear();
    if (!Number.isInteger(year) || year < 2000 || year > 2100) {
        return nowYear;
    }
    return year;
}

function applyLmsYear(year) {
    const normalizedYear = normalizeLmsYear(year);
    if (normalizedYear === selectedLmsYear.value) {
        return;
    }

    selectedLmsYear.value = normalizedYear;
    lmsDomainSummary.value = null;
    lmsDetailCache.value = {};
    lmsMemberDetailCache.value = {};
    lmsMemberDetailErrorMap.value = {};
    lmsDetailRows.value = [];
    lmsDetailOpenedMemberId.value = '';
    lmsDetailPagination.value = {
        current_page: 1,
        last_page: 1,
        per_page: lmsDetailPagination.value.per_page || 10,
        total: 0,
        has_prev: false,
        has_next: false,
    };

    void loadLmsSummaryDomain(true);

    if (lmsDetailDrawerOpen.value) {
        void loadLmsDetailRows(1, { focus: lmsDetailFocus.value, search: lmsDetailSearchKeyword.value });
    }

    if (openedMemberId.value) {
        void fetchMemberDetail(openedMemberId.value);
    }
}

function handleLmsYearChange(event) {
    applyLmsYear(event?.target?.value);
}

function getKpiDetailCacheKey(mode, context, month, page = 1, search = '') {
    return `${normalizeKpiYear(selectedKpiYear.value)}|${String(mode || 'annual')}|${String(context || 'monthly_avg')}|${String(month || '')}|${Math.max(1, Number(page || 1))}|${String(
        search || '',
    )
        .trim()
        .toLowerCase()}`;
}

function getKpiMemberDetailCacheKey(memberId, mode = 'month', period = '') {
    return `${String(memberId || '')
        .trim()
        .toUpperCase()}|${normalizeKpiYear(selectedKpiYear.value)}|${String(mode || 'month')}|${String(period || '').trim()}`;
}

function normalizeMemberDetailMemberId(memberId) {
    return String(memberId || '')
        .trim()
        .toUpperCase();
}

function getMemberDetailContextYears() {
    return {
        kpiYear: normalizeKpiYear(selectedKpiYear.value),
        lmsYear: normalizeLmsYear(selectedLmsYear.value),
    };
}

function getMemberDetailCacheKey(memberId, contextYears = getMemberDetailContextYears()) {
    const normalizedMemberId = normalizeMemberDetailMemberId(memberId);
    if (!normalizedMemberId) {
        return '';
    }

    return `${normalizedMemberId}|kpi:${contextYears.kpiYear}|lms:${contextYears.lmsYear}`;
}

function memberDetailFor(memberId) {
    const cacheKey = getMemberDetailCacheKey(memberId);
    if (!cacheKey) {
        return null;
    }

    return memberDetailCache.value[cacheKey] || null;
}

function buildKpiPeriodValue(year, month) {
    const normalizedYear = normalizeKpiYear(year);
    const numericMonth = Number(month || 1);
    const clampedMonth = Math.max(1, Math.min(12, Number.isFinite(numericMonth) ? numericMonth : 1));
    return `${normalizedYear}-${String(clampedMonth).padStart(2, '0')}`;
}

function isValidKpiPeriodValue(value) {
    return /^\d{4}-\d{2}$/.test(String(value || '').trim());
}

function normalizeKpiPeriodForYear(period, year) {
    const normalizedYear = normalizeKpiYear(year);
    const raw = String(period || '').trim();
    const now = new Date();
    const fallbackPeriod = buildKpiPeriodValue(normalizedYear, now.getMonth() + 1);

    if (!isValidKpiPeriodValue(raw)) {
        return fallbackPeriod;
    }

    const [rawYear, rawMonth] = raw.split('-').map((item) => Number(item || 0));
    if (!Number.isInteger(rawMonth) || rawMonth < 1 || rawMonth > 12) {
        return fallbackPeriod;
    }

    if (rawYear !== normalizedYear) {
        return buildKpiPeriodValue(normalizedYear, rawMonth);
    }

    return raw;
}

function getMemberKpiPanelStateKey(memberId) {
    return String(memberId || '')
        .trim()
        .toUpperCase();
}

function getMemberKpiPanelDefault(memberLike = {}) {
    const year = normalizeKpiYear(
        Number(memberLike?.kpi?.year || memberLike?.kpi_year || selectedKpiYear.value || new Date().getFullYear()),
    );
    const selectedPeriod = String(memberLike?.kpi?.selectedPeriod || memberLike?.kpi?.selected_period || '').trim();
    const period = normalizeKpiPeriodForYear(selectedPeriod, year);
    return { year, period };
}

function initializeMemberKpiPanelState(memberId, memberLike = {}) {
    const stateKey = getMemberKpiPanelStateKey(memberId);
    if (!stateKey || memberKpiPanelStateMap.value[stateKey]) {
        return;
    }

    const defaults = getMemberKpiPanelDefault(memberLike);
    memberKpiPanelStateMap.value = {
        ...memberKpiPanelStateMap.value,
        [stateKey]: {
            draftYear: defaults.year,
            draftPeriod: defaults.period,
            appliedYear: defaults.year,
            appliedPeriod: defaults.period,
        },
    };
}

function getMemberKpiPanelState(memberLike = {}) {
    const stateKey = getMemberKpiPanelStateKey(memberLike?.id || memberLike?.kode_karyawan || memberLike);
    const defaults = getMemberKpiPanelDefault(memberLike);
    const current = memberKpiPanelStateMap.value[stateKey];
    if (!stateKey || !current) {
        return {
            stateKey,
            draftYear: defaults.year,
            draftPeriod: defaults.period,
            appliedYear: defaults.year,
            appliedPeriod: defaults.period,
        };
    }

    const draftYear = normalizeKpiYear(current.draftYear);
    const appliedYear = normalizeKpiYear(current.appliedYear);

    return {
        stateKey,
        draftYear,
        draftPeriod: normalizeKpiPeriodForYear(current.draftPeriod, draftYear),
        appliedYear,
        appliedPeriod: normalizeKpiPeriodForYear(current.appliedPeriod, appliedYear),
    };
}

function setMemberKpiPanelState(stateKey, patch = {}) {
    if (!stateKey) {
        return;
    }

    const existing = memberKpiPanelStateMap.value[stateKey] || {};
    memberKpiPanelStateMap.value = {
        ...memberKpiPanelStateMap.value,
        [stateKey]: {
            ...existing,
            ...patch,
        },
    };
}

function getMemberKpiPanelCacheKey(memberId, year, period) {
    const stateKey = getMemberKpiPanelStateKey(memberId);
    if (!stateKey) {
        return '';
    }

    return `${stateKey}|${normalizeKpiYear(year)}|${normalizeKpiPeriodForYear(period, year)}`;
}

function kpiPanelPeriodLabel(period) {
    const value = String(period || '').trim();
    if (!isValidKpiPeriodValue(value)) {
        return '-';
    }

    return new Date(`${value}-01`).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
}

function memberKpiPeriodOptions(memberLike = {}) {
    const state = getMemberKpiPanelState(memberLike);
    const year = normalizeKpiYear(state.draftYear);

    return Array.from({ length: 12 }).map((_, index) => {
        const month = index + 1;
        const value = buildKpiPeriodValue(year, month);
        const label = new Date(`${value}-01`).toLocaleDateString('id-ID', { month: 'short', year: 'numeric' });
        return { value, label };
    });
}

function setMemberKpiDraftYear(memberLike, yearValue) {
    const state = getMemberKpiPanelState(memberLike);
    if (!state.stateKey) {
        return;
    }

    const nextYear = normalizeKpiYear(Number(yearValue || state.draftYear));
    const nextPeriod = normalizeKpiPeriodForYear(state.draftPeriod, nextYear);
    setMemberKpiPanelState(state.stateKey, {
        draftYear: nextYear,
        draftPeriod: nextPeriod,
    });
}

function setMemberKpiDraftPeriod(memberLike, periodValue) {
    const state = getMemberKpiPanelState(memberLike);
    if (!state.stateKey) {
        return;
    }

    const nextPeriod = normalizeKpiPeriodForYear(periodValue, state.draftYear);
    setMemberKpiPanelState(state.stateKey, { draftPeriod: nextPeriod });
}

function memberKpiPanelHasPendingChanges(memberLike = {}) {
    const state = getMemberKpiPanelState(memberLike);
    return state.draftYear !== state.appliedYear || state.draftPeriod !== state.appliedPeriod;
}

function getMemberKpiPanelError(memberId) {
    const key = getMemberKpiPanelStateKey(memberId);
    return memberKpiPanelErrorMap.value[key] || '';
}

function isMemberKpiPanelLoading(memberId) {
    return memberKpiPanelLoadingId.value === getMemberKpiPanelStateKey(memberId);
}

function mapKpiPanelViewFromDetail(detailData, baseKpi = {}, fallbackPeriod = '') {
    const summary = detailData?.summary || {};
    const trendRows = Array.isArray(detailData?.trend_12_months) ? detailData.trend_12_months : [];
    const parameterRows = Array.isArray(detailData?.parameter_rows) ? detailData.parameter_rows : [];
    const selectedPeriod = String(summary?.period || fallbackPeriod || '').trim();
    const score = Number(summary?.selected_score ?? 0);
    const periodLabel =
        summary?.period_label || (selectedPeriod ? `Periode ${kpiPanelPeriodLabel(selectedPeriod)}` : '-');

    return {
        year: Number(baseKpi?.year || selectedKpiYear.value || new Date().getFullYear()),
        selectedPeriod,
        score,
        grade: baseKpi?.grade || '',
        achievement: score,
        parameterPeriod: periodLabel,
        performancePeriod: periodLabel,
        pendingItems: Array.isArray(baseKpi?.pendingItems) ? baseKpi.pendingItems : [],
        monthlyAchievement: trendRows.map((item) => ({
            month: String(item?.month || '').trim() || String(item?.period || '').trim() || '-',
            value: Number(item?.score || 0),
        })),
        parameters: parameterRows.map((item) => ({
            name: String(item?.parameter || 'Parameter KPI'),
            score: Number(item?.achievement ?? item?.score ?? 0),
            achievement: Number(item?.achievement ?? 0),
            target: Number(item?.target ?? 0),
            actual: Number(item?.actual ?? 0),
            status: String(item?.status || ''),
        })),
    };
}

function memberKpiPanelView(member) {
    const state = getMemberKpiPanelState(member);
    const cacheKey = getMemberKpiPanelCacheKey(member?.id, state.appliedYear, state.appliedPeriod);
    const cached = cacheKey ? memberKpiPanelCache.value[cacheKey] : null;
    if (cached?.view) {
        return cached.view;
    }

    const baseKpi = member?.kpi || {};
    return {
        year: Number(baseKpi?.year || state.appliedYear || selectedKpiYear.value || new Date().getFullYear()),
        selectedPeriod: state.appliedPeriod,
        score: Number(baseKpi?.score || 0),
        grade: baseKpi?.grade || '',
        achievement: Number(baseKpi?.achievement || 0),
        parameterPeriod: baseKpi?.parameterPeriod || `Periode ${kpiPanelPeriodLabel(state.appliedPeriod)}`,
        performancePeriod: baseKpi?.performancePeriod || `Periode ${kpiPanelPeriodLabel(state.appliedPeriod)}`,
        pendingItems: Array.isArray(baseKpi?.pendingItems) ? baseKpi.pendingItems : [],
        monthlyAchievement: Array.isArray(baseKpi?.monthlyAchievement) ? baseKpi.monthlyAchievement : [],
        parameters: Array.isArray(baseKpi?.parameters) ? baseKpi.parameters : [],
    };
}

async function applyMemberKpiPanelFilter(member) {
    const memberId = getMemberKpiPanelStateKey(member?.id || member?.kode_karyawan);
    if (!memberId) {
        return;
    }

    const state = getMemberKpiPanelState(member);
    const cacheKey = getMemberKpiPanelCacheKey(memberId, state.draftYear, state.draftPeriod);
    const baseKpi = member?.kpi || {};

    memberKpiPanelErrorMap.value = {
        ...memberKpiPanelErrorMap.value,
        [memberId]: '',
    };

    if (cacheKey && memberKpiPanelCache.value[cacheKey]) {
        setMemberKpiPanelState(state.stateKey, {
            appliedYear: state.draftYear,
            appliedPeriod: state.draftPeriod,
        });
        return;
    }

    if (memberKpiPanelLoadingId.value === memberId) {
        return;
    }

    memberKpiPanelLoadingId.value = memberId;
    try {
        const response = await axios.get(`/homepage/leader-dashboard/kpi-details/${encodeURIComponent(memberId)}`, {
            params: {
                year: normalizeKpiYear(state.draftYear),
                mode: 'month',
                context: 'monthly_avg',
                period: state.draftPeriod,
                month: state.draftPeriod,
            },
        });

        const detailData = response?.data?.data || null;
        if (detailData && cacheKey) {
            const view = mapKpiPanelViewFromDetail(detailData, baseKpi, state.draftPeriod);
            memberKpiPanelCache.value = {
                ...memberKpiPanelCache.value,
                [cacheKey]: {
                    detail: detailData,
                    view,
                },
            };
        }

        setMemberKpiPanelState(state.stateKey, {
            appliedYear: state.draftYear,
            appliedPeriod: state.draftPeriod,
        });
    } catch (error) {
        console.error('Failed to apply member KPI panel filter', error);
        memberKpiPanelErrorMap.value = {
            ...memberKpiPanelErrorMap.value,
            [memberId]: 'Gagal memuat KPI periode terpilih. Coba lagi.',
        };
    } finally {
        if (memberKpiPanelLoadingId.value === memberId) {
            memberKpiPanelLoadingId.value = '';
        }
    }
}

function kpiMemberDetailFor(memberId) {
    const mode = kpiMemberMode(memberId);
    const cacheKey = getKpiMemberDetailCacheKey(memberId, mode, mode === 'month' ? kpiMemberPeriod(memberId) : '');
    return kpiMemberDetailCache.value[cacheKey] || null;
}

function getKpiMemberError(memberId) {
    return (
        kpiMemberDetailErrorMap.value[
            String(memberId || '')
                .trim()
                .toUpperCase()
        ] || ''
    );
}

function isKpiMemberDetailLoading(memberId) {
    const key = String(memberId || '')
        .trim()
        .toUpperCase();
    return key !== '' && kpiMemberDetailLoadingId.value === key && !kpiMemberDetailFor(memberId);
}

function kpiMemberMode(memberId) {
    const key = String(memberId || '')
        .trim()
        .toUpperCase();
    return kpiMemberModeMap.value[key] || 'month';
}

function kpiMemberPeriod(memberId) {
    const key = String(memberId || '')
        .trim()
        .toUpperCase();
    const fallback = String(kpiDetailMeta.value?.effective_cutoff_period || '').trim();
    return kpiMemberPeriodMap.value[key] || fallback;
}

function kpiMemberMonthOptions(memberId) {
    const detail = kpiMemberDetailFor(memberId);
    const trend = Array.isArray(detail?.trend_12_months) ? detail.trend_12_months : [];
    if (!trend.length) {
        const year = Number(kpiDetailMeta.value?.kpi_year || selectedKpiYear.value || new Date().getFullYear());
        return Array.from({ length: 12 }).map((_, index) => {
            const month = index + 1;
            const value = `${year}-${String(month).padStart(2, '0')}`;
            const label = new Date(year, index, 1).toLocaleDateString('id-ID', { month: 'short', year: 'numeric' });
            return { value, label };
        });
    }

    return trend.map((item, index) => ({
        value:
            String(item?.period || '').trim() ||
            `${kpiDetailMeta.value?.kpi_year || selectedKpiYear.value}-${String(index + 1).padStart(2, '0')}`,
        label: String(item?.period || '').trim()
            ? new Date(`${item.period}-01`).toLocaleDateString('id-ID', { month: 'short', year: 'numeric' })
            : `Bulan ${index + 1}`,
    }));
}

function syncKpiMemberOpenState() {
    const openedId = String(kpiDetailOpenedMemberId.value || '')
        .trim()
        .toUpperCase();
    if (!openedId) {
        return;
    }

    const exists = kpiDetailRows.value.some(
        (row) =>
            String(row?.id || '')
                .trim()
                .toUpperCase() === openedId,
    );

    if (!exists) {
        kpiDetailOpenedMemberId.value = '';
    }
}

async function loadKpiDetails(mode = kpiDetailMode.value, page = 1, options = {}) {
    const normalizedMode = 'annual';
    const targetPage = Math.max(1, Number(page || 1));
    const context = String(options?.context ?? kpiDetailContext.value ?? 'monthly_avg');
    const month = String(options?.month ?? kpiDetailMonth.value ?? '').trim();
    const normalizedSearch = String(options?.search ?? kpiDetailSearchKeyword.value ?? '').trim();
    const cacheKey = getKpiDetailCacheKey(normalizedMode, context, month, targetPage, normalizedSearch);

    kpiDetailDrawerOpen.value = true;
    kpiDetailMode.value = normalizedMode;
    kpiDetailContext.value = context;
    kpiDetailMonth.value = month;
    kpiDetailError.value = '';

    const cached = kpiDetailCache.value[cacheKey];
    if (cached) {
        kpiDetailRows.value = cached.data || [];
        kpiDetailPagination.value = cached.pagination || kpiDetailPagination.value;
        kpiDetailMeta.value = cached.meta || kpiDetailMeta.value;
        syncKpiMemberOpenState();
        return;
    }

    kpiDetailLoading.value = true;
    kpiDetailRows.value = [];

    try {
        const response = await axios.get('/homepage/leader-dashboard/kpi-details', {
            params: {
                year: normalizeKpiYear(selectedKpiYear.value),
                mode: normalizedMode,
                context,
                month: month || undefined,
                page: targetPage,
                per_page: kpiDetailPagination.value.per_page || 10,
                q: normalizedSearch || undefined,
                compact: 1,
            },
        });

        const payload = {
            data: Array.isArray(response?.data?.data) ? response.data.data : [],
            pagination: response?.data?.pagination || kpiDetailPagination.value,
            meta: response?.data?.meta || kpiDetailMeta.value,
        };
        kpiDetailCache.value = { ...kpiDetailCache.value, [cacheKey]: payload };
        kpiDetailRows.value = payload.data;
        kpiDetailPagination.value = payload.pagination;
        kpiDetailMeta.value = payload.meta;
        syncKpiMemberOpenState();
    } catch (error) {
        console.error('Failed to load KPI detail rows', error);
        kpiDetailError.value = 'Gagal memuat detail KPI. Silakan coba lagi.';
        kpiDetailRows.value = [];
        kpiDetailOpenedMemberId.value = '';
    } finally {
        kpiDetailLoading.value = false;
    }
}

function openKpiDetailDrawer(context = 'monthly_avg', month = '') {
    void loadKpiSummaryDomain();
    kpiDetailOpenedMemberId.value = '';
    kpiMemberModeMap.value = {};
    kpiMemberPeriodMap.value = {};
    void loadKpiDetails(kpiDetailMode.value, 1, {
        context,
        month,
        search: kpiDetailSearchKeyword.value,
    });
}

function closeKpiDetailDrawer() {
    kpiDetailDrawerOpen.value = false;
    kpiDetailOpenedMemberId.value = '';
    kpiMemberModeMap.value = {};
    kpiMemberPeriodMap.value = {};
}

function retryKpiDetailLoad() {
    void loadKpiDetails(kpiDetailMode.value, Number(kpiDetailPagination.value.current_page || 1), {
        context: kpiDetailContext.value,
        month: kpiDetailMonth.value,
    });
}

function goToKpiDetailPage(step) {
    if (!kpiDetailDrawerOpen.value) {
        return;
    }
    const currentPage = Number(kpiDetailPagination.value.current_page || 1);
    const nextPage = Math.max(1, currentPage + Number(step || 0));
    if (nextPage === currentPage) {
        return;
    }
    kpiDetailOpenedMemberId.value = '';
    void loadKpiDetails(kpiDetailMode.value, nextPage, {
        context: kpiDetailContext.value,
        month: kpiDetailMonth.value,
    });
}

async function loadKpiMemberDetail(memberId, options = {}) {
    const normalizedMemberId = String(memberId || '')
        .trim()
        .toUpperCase();
    if (!normalizedMemberId) {
        return;
    }

    const mode = ['month', 'full_year'].includes(String(options?.mode || ''))
        ? String(options.mode)
        : kpiMemberMode(normalizedMemberId);
    const period = mode === 'month' ? String(options?.period ?? kpiMemberPeriod(normalizedMemberId) ?? '').trim() : '';
    const cacheKey = getKpiMemberDetailCacheKey(normalizedMemberId, mode, period);
    if (kpiMemberDetailCache.value[cacheKey]) {
        return;
    }
    if (kpiMemberDetailLoadingId.value === normalizedMemberId) {
        return;
    }

    kpiMemberDetailLoadingId.value = normalizedMemberId;
    kpiMemberDetailErrorMap.value = {
        ...kpiMemberDetailErrorMap.value,
        [normalizedMemberId]: '',
    };

    try {
        const response = await axios.get(
            `/homepage/leader-dashboard/kpi-details/${encodeURIComponent(normalizedMemberId)}`,
            {
                params: {
                    year: normalizeKpiYear(selectedKpiYear.value),
                    mode,
                    context: kpiDetailContext.value,
                    period: mode === 'month' ? period || undefined : undefined,
                    month: mode === 'month' ? period || undefined : undefined,
                },
            },
        );

        const detailData = response?.data?.data || null;
        if (detailData) {
            kpiMemberDetailCache.value = {
                ...kpiMemberDetailCache.value,
                [cacheKey]: detailData,
            };
        }
    } catch (error) {
        console.error('Failed to load KPI member detail', error);
        kpiMemberDetailErrorMap.value = {
            ...kpiMemberDetailErrorMap.value,
            [normalizedMemberId]: 'Gagal memuat detail anggota. Coba lagi.',
        };
    } finally {
        if (kpiMemberDetailLoadingId.value === normalizedMemberId) {
            kpiMemberDetailLoadingId.value = '';
        }
    }
}

async function toggleKpiDetailMember(item) {
    if (kpiDetailContext.value === 'approval_queue') {
        return;
    }

    const memberId = String(item?.id || '')
        .trim()
        .toUpperCase();
    if (!memberId) {
        return;
    }

    if (kpiDetailOpenedMemberId.value === memberId) {
        kpiDetailOpenedMemberId.value = '';
        return;
    }

    kpiMemberModeMap.value = {
        ...kpiMemberModeMap.value,
        [memberId]: kpiMemberMode(memberId) || 'month',
    };
    kpiMemberPeriodMap.value = {
        ...kpiMemberPeriodMap.value,
        [memberId]: kpiMemberPeriod(memberId) || String(kpiDetailMeta.value?.effective_cutoff_period || '').trim(),
    };

    kpiDetailOpenedMemberId.value = memberId;
    await loadKpiMemberDetail(memberId, {
        mode: kpiMemberMode(memberId),
        period: kpiMemberPeriod(memberId),
    });
}

function setKpiMemberMode(memberId, mode) {
    const normalizedMemberId = String(memberId || '')
        .trim()
        .toUpperCase();
    if (!normalizedMemberId) {
        return;
    }

    const nextMode = ['month', 'full_year'].includes(String(mode || '')) ? String(mode) : 'month';
    if (kpiMemberMode(normalizedMemberId) === nextMode) {
        return;
    }

    kpiMemberModeMap.value = {
        ...kpiMemberModeMap.value,
        [normalizedMemberId]: nextMode,
    };

    if (nextMode === 'month' && !kpiMemberPeriod(normalizedMemberId)) {
        const fallbackPeriod = String(kpiDetailMeta.value?.effective_cutoff_period || '').trim();
        kpiMemberPeriodMap.value = {
            ...kpiMemberPeriodMap.value,
            [normalizedMemberId]: fallbackPeriod,
        };
    }

    void loadKpiMemberDetail(normalizedMemberId, {
        mode: nextMode,
        period: nextMode === 'month' ? kpiMemberPeriod(normalizedMemberId) : '',
    });
}

function setKpiMemberPeriod(memberId, period) {
    const normalizedMemberId = String(memberId || '')
        .trim()
        .toUpperCase();
    const normalizedPeriod = String(period || '').trim();
    if (!normalizedMemberId || !/^\d{4}-\d{2}$/.test(normalizedPeriod)) {
        return;
    }

    if (kpiMemberPeriod(normalizedMemberId) === normalizedPeriod && kpiMemberMode(normalizedMemberId) === 'month') {
        return;
    }

    kpiMemberModeMap.value = {
        ...kpiMemberModeMap.value,
        [normalizedMemberId]: 'month',
    };
    kpiMemberPeriodMap.value = {
        ...kpiMemberPeriodMap.value,
        [normalizedMemberId]: normalizedPeriod,
    };

    void loadKpiMemberDetail(normalizedMemberId, {
        mode: 'month',
        period: normalizedPeriod,
    });
}

function kpiMemberTrendRows(memberId) {
    const detail = kpiMemberDetailFor(memberId);
    const trend = Array.isArray(detail?.trend_12_months) ? detail.trend_12_months : [];
    return trend
        .map((item) => ({
            period: item?.period || '',
            label: item?.month || item?.label || item?.period || '-',
            score: Number(item?.score || 0),
        }))
        .filter((item) => item.period !== '')
        .sort((a, b) => a.period.localeCompare(b.period));
}

function kpiMemberTrendSeries(memberId) {
    return [
        {
            name: 'Avg Score',
            data: kpiMemberTrendRows(memberId).map((item) => item.score),
        },
    ];
}

function kpiMemberTrendOptions(memberId) {
    return {
        chart: {
            type: 'line',
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2.5 },
        colors: ['#4f46e5'],
        grid: {
            borderColor: '#e2e8f0',
            strokeDashArray: 3,
            padding: { top: 6, right: 8, bottom: 0, left: 4 },
        },
        xaxis: {
            categories: kpiMemberTrendRows(memberId).map((item) => item.label),
            labels: {
                style: {
                    colors: '#64748b',
                    fontSize: '10px',
                    fontWeight: 700,
                },
            },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            min: 0,
            max: 100,
            tickAmount: 4,
            labels: {
                style: {
                    colors: '#64748b',
                    fontSize: '10px',
                    fontWeight: 700,
                },
            },
        },
        tooltip: {
            theme: 'light',
            y: {
                formatter: (value) => `${formatNumber(value)}%`,
            },
        },
        markers: {
            size: 4,
            colors: ['#4f46e5'],
            strokeColors: '#ffffff',
            strokeWidth: 2,
            hover: { size: 6 },
        },
        legend: { show: false },
    };
}

function kpiFormCount(item) {
    return Number(item?.form_kpi_count ?? item?.form_kpi?.length ?? 0);
}

function kpiPerformanceCount(item) {
    return Number(item?.performance_kpi_count ?? item?.performance_kpi?.length ?? 0);
}

function handleLmsCardInteract() {
    void loadLmsSummaryDomain();
}

function normalizeLmsFocus(focus) {
    const value = String(focus || '')
        .trim()
        .toLowerCase();
    const map = {
        kpi_param_not_submitted: 'lms_not_started',
        lms_not_started: 'lms_not_started',
        progress_below_target: 'progress_below_target',
        progress_above_target: 'progress_above_target',
        learning_gain: 'learning_gain',
        assigned: 'assigned',
        total_training: 'assigned',
        avg_progress: 'progress_above_target',
        completed: 'progress_above_target',
        risk: 'progress_below_target',
        at_risk: 'progress_below_target',
    };
    return map[value] || 'lms_not_started';
}

function getLmsDetailCacheKey(focus, page = 1, search = '') {
    return `${normalizeLmsYear(selectedLmsYear.value)}|${normalizeLmsFocus(focus)}|${Math.max(
        1,
        Number(page || 1),
    )}|${String(search || '')
        .trim()
        .toLowerCase()}`;
}

function getLmsMemberDetailCacheKey(memberId) {
    return `${String(memberId || '')
        .trim()
        .toUpperCase()}|${normalizeLmsYear(selectedLmsYear.value)}`;
}

function lmsMemberDetailFor(memberId) {
    const key = getLmsMemberDetailCacheKey(memberId);
    return lmsMemberDetailCache.value[key] || null;
}

function getLmsMemberDetailError(memberId) {
    const key = String(memberId || '')
        .trim()
        .toUpperCase();
    return lmsMemberDetailErrorMap.value[key] || '';
}

function isLmsMemberDetailLoading(memberId) {
    const key = String(memberId || '')
        .trim()
        .toUpperCase();
    return key !== '' && lmsMemberDetailLoadingId.value === key && !lmsMemberDetailFor(memberId);
}

function lmsMemberTrainings(memberId) {
    const detail = lmsMemberDetailFor(memberId);
    return Array.isArray(detail?.lms?.trainings) ? detail.lms.trainings : [];
}

function lmsMemberLearningProgress(memberId) {
    const detail = lmsMemberDetailFor(memberId);
    return Array.isArray(detail?.lms?.learningProgress) ? detail.lms.learningProgress : [];
}

async function loadLmsMemberDetail(memberId) {
    const normalizedId = String(memberId || '')
        .trim()
        .toUpperCase();
    if (!normalizedId) {
        return;
    }

    const cacheKey = getLmsMemberDetailCacheKey(normalizedId);
    if (lmsMemberDetailCache.value[cacheKey]) {
        return;
    }
    if (lmsMemberDetailLoadingId.value === normalizedId) {
        return;
    }

    lmsMemberDetailLoadingId.value = normalizedId;
    lmsMemberDetailErrorMap.value = {
        ...lmsMemberDetailErrorMap.value,
        [normalizedId]: '',
    };

    try {
        const response = await axios.get(
            `/homepage/leader-dashboard/members/${encodeURIComponent(normalizedId)}/detail`,
            {
                params: {
                    year: normalizeLmsYear(selectedLmsYear.value),
                },
            },
        );

        if (response?.data?.data) {
            lmsMemberDetailCache.value = {
                ...lmsMemberDetailCache.value,
                [cacheKey]: response.data.data,
            };
        }
    } catch (error) {
        console.error('Failed to load LMS member detail', error);
        lmsMemberDetailErrorMap.value = {
            ...lmsMemberDetailErrorMap.value,
            [normalizedId]: 'Gagal memuat detail training anggota.',
        };
    } finally {
        if (lmsMemberDetailLoadingId.value === normalizedId) {
            lmsMemberDetailLoadingId.value = '';
        }
    }
}

async function toggleLmsDetailMember(item) {
    const memberId = String(item?.id || '')
        .trim()
        .toUpperCase();
    if (!memberId) {
        return;
    }

    if (lmsDetailOpenedMemberId.value === memberId) {
        lmsDetailOpenedMemberId.value = '';
        return;
    }

    lmsDetailOpenedMemberId.value = memberId;
    await loadLmsMemberDetail(memberId);
}

async function loadLmsDetailRows(page = 1, options = {}) {
    const focus = normalizeLmsFocus(options?.focus ?? lmsDetailFocus.value);
    const targetPage = Math.max(1, Number(page || 1));
    const search = String(options?.search ?? lmsDetailSearchKeyword.value ?? '').trim();
    const cacheKey = getLmsDetailCacheKey(focus, targetPage, search);

    lmsDetailDrawerOpen.value = true;
    lmsDetailFocus.value = focus;
    lmsDetailError.value = '';

    const cached = lmsDetailCache.value[cacheKey];
    if (cached) {
        lmsDetailRows.value = cached.data || [];
        lmsDetailPagination.value = cached.pagination || lmsDetailPagination.value;
        lmsDetailMeta.value = cached.meta || lmsDetailMeta.value;
        lmsDetailOpenedMemberId.value = '';
        return;
    }

    lmsDetailLoading.value = true;
    lmsDetailRows.value = [];

    try {
        const response = await axios.get('/homepage/leader-dashboard/lms-team-members', {
            params: {
                year: normalizeLmsYear(selectedLmsYear.value),
                focus,
                search: search || undefined,
                page: targetPage,
                per_page: lmsDetailPagination.value.per_page || 10,
            },
        });

        const payload = {
            data: Array.isArray(response?.data?.data) ? response.data.data : [],
            pagination: response?.data?.pagination || lmsDetailPagination.value,
            meta: response?.data?.meta || lmsDetailMeta.value,
        };

        lmsDetailCache.value = {
            ...lmsDetailCache.value,
            [cacheKey]: payload,
        };
        lmsDetailRows.value = payload.data;
        lmsDetailPagination.value = payload.pagination;
        lmsDetailMeta.value = payload.meta;
        lmsDetailOpenedMemberId.value = '';
    } catch (error) {
        console.error('Failed to load LMS drawer rows', error);
        lmsDetailError.value = 'Gagal memuat detail LMS. Silakan coba lagi.';
        lmsDetailRows.value = [];
        lmsDetailOpenedMemberId.value = '';
    } finally {
        lmsDetailLoading.value = false;
    }
}

function openLmsDetailDrawer(focus = 'lms_not_started') {
    void loadLmsSummaryDomain();
    void loadLmsDetailRows(1, {
        focus,
        search: lmsDetailSearchKeyword.value,
    });
}

function closeLmsDetailDrawer() {
    lmsDetailDrawerOpen.value = false;
    lmsDetailOpenedMemberId.value = '';
}

function goToLmsDetailPage(step) {
    if (!lmsDetailDrawerOpen.value) {
        return;
    }
    const currentPage = Number(lmsDetailPagination.value.current_page || 1);
    const nextPage = Math.max(1, currentPage + Number(step || 0));
    if (nextPage === currentPage) {
        return;
    }
    void loadLmsDetailRows(nextPage, {
        focus: lmsDetailFocus.value,
        search: lmsDetailSearchKeyword.value,
    });
}

function retryLmsDetailLoad() {
    void loadLmsDetailRows(Number(lmsDetailPagination.value.current_page || 1), {
        focus: lmsDetailFocus.value,
        search: lmsDetailSearchKeyword.value,
    });
}

function toYmdLocal(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function resolveTrendDateByIndex(index) {
    const safeIndex = Math.max(0, Math.min(6, Number(index || 0)));
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const start = new Date(today);
    start.setDate(start.getDate() - 6 + safeIndex);

    return toYmdLocal(start);
}

function weekdayLabelFromDate(date) {
    const labels = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    return labels[date.getDay()] || '';
}

function resolveTrendDateByLabel(label, indexFallback = 0) {
    const targetLabel = String(label || '').trim();
    if (targetLabel === '') {
        return resolveTrendDateByIndex(indexFallback);
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    for (let offset = 6; offset >= 0; offset -= 1) {
        const cursor = new Date(today);
        cursor.setDate(today.getDate() - offset);

        if (weekdayLabelFromDate(cursor) === targetLabel) {
            return toYmdLocal(cursor);
        }
    }

    return resolveTrendDateByIndex(indexFallback);
}

function getAttendanceIssueCacheKey(date, page = 1) {
    return `${date}|${Math.max(1, Number(page || 1))}`;
}

function closeAttendanceIssueDrawer() {
    attendanceIssueDrawerOpen.value = false;
}

function normalizeAttendanceStatusFilter(key) {
    const value = String(key || '')
        .trim()
        .toLowerCase();
    const map = {
        all: 'all',
        present: 'present',
        absent: 'no-checkin',
        'no-checkin': 'no-checkin',
        'pending-checkin': 'pending-checkin',
        working: 'working',
        'no-checkout': 'no-checkout',
        late: 'late',
        'early-out': 'early-out',
        leave: 'leave',
        'no-data': 'no-data',
    };

    return map[value] || 'all';
}

function getAttendanceStatusCacheKey(filter, page = 1, search = '') {
    return `${normalizeAttendanceStatusFilter(filter)}|${Math.max(1, Number(page || 1))}|${String(search || '')
        .trim()
        .toLowerCase()}`;
}

async function loadAttendanceIssues(date, page = 1) {
    const targetDate = String(date || '').trim();
    if (!targetDate) {
        return;
    }

    const targetPage = Math.max(1, Number(page || 1));
    const cacheKey = getAttendanceIssueCacheKey(targetDate, targetPage);

    attendanceIssueActiveDate.value = targetDate;
    attendanceIssueDrawerOpen.value = true;
    attendanceIssueError.value = '';

    const cached = attendanceIssueCache.value[cacheKey];
    if (cached) {
        attendanceIssueRows.value = cached.data || [];
        attendanceIssuePagination.value = cached.pagination || attendanceIssuePagination.value;
        attendanceIssueMeta.value = cached.meta || attendanceIssueMeta.value;
        return;
    }

    if (attendanceIssueLoading.value && attendanceIssueRequestingKey.value === cacheKey) {
        return;
    }

    attendanceIssueLoading.value = true;
    attendanceIssueRequestingKey.value = cacheKey;
    attendanceIssueRows.value = [];

    try {
        const response = await axios.get('/homepage/leader-dashboard/attendance-issues', {
            params: {
                date: targetDate,
                page: targetPage,
                per_page: attendanceIssuePagination.value.per_page || 10,
            },
        });

        const payload = {
            data: Array.isArray(response?.data?.data) ? response.data.data : [],
            pagination: response?.data?.pagination || attendanceIssuePagination.value,
            meta: response?.data?.meta || {
                date: targetDate,
                day_label: formatDateLabel(targetDate),
                issue_count: 0,
            },
        };

        attendanceIssueCache.value = {
            ...attendanceIssueCache.value,
            [cacheKey]: payload,
        };
        attendanceIssueRows.value = payload.data;
        attendanceIssuePagination.value = payload.pagination;
        attendanceIssueMeta.value = payload.meta;
    } catch (error) {
        console.error('Failed to load attendance issues', error);
        attendanceIssueError.value = 'Gagal memuat detail issue. Silakan coba lagi.';
        attendanceIssueRows.value = [];
        attendanceIssuePagination.value = {
            current_page: 1,
            last_page: 1,
            per_page: attendanceIssuePagination.value.per_page || 10,
            total: 0,
            has_prev: false,
            has_next: false,
        };
        attendanceIssueMeta.value = {
            date: targetDate,
            day_label: formatDateLabel(targetDate),
            issue_count: 0,
        };
    } finally {
        if (attendanceIssueRequestingKey.value === cacheKey) {
            attendanceIssueLoading.value = false;
            attendanceIssueRequestingKey.value = '';
        }
    }
}

async function openAttendanceIssueDrawerByIndex(index) {
    if (!isChartIssueClickable.value) {
        return;
    }

    const label = hcisDailyTrend.value[index]?.label || '';
    const targetDate = resolveTrendDateByLabel(label, index);
    await loadAttendanceIssues(targetDate, 1);
}

function handleHcisTrendAreaClick(event) {
    if (!isChartIssueClickable.value) {
        return;
    }

    const rect = event?.currentTarget?.getBoundingClientRect?.();
    if (!rect || !rect.width) {
        return;
    }

    const relativeX = Number(event?.clientX || 0) - rect.left;
    const segmentWidth = rect.width / 7;
    const rawIndex = Math.floor(relativeX / Math.max(1, segmentWidth));
    const index = Math.max(0, Math.min(6, rawIndex));

    void openAttendanceIssueDrawerByIndex(index);
}

function retryAttendanceIssueLoad() {
    if (!attendanceIssueActiveDate.value) {
        return;
    }

    const page = Number(attendanceIssuePagination.value.current_page || 1);
    void loadAttendanceIssues(attendanceIssueActiveDate.value, page);
}

async function loadAttendanceStatusRows(filter = 'all', page = 1, options = {}) {
    const normalizedFilter = normalizeAttendanceStatusFilter(filter);
    const targetPage = Math.max(1, Number(page || 1));
    const normalizedSearch = String(options?.search ?? attendanceStatusSearchKeyword.value ?? '').trim();
    const cacheKey = getAttendanceStatusCacheKey(normalizedFilter, targetPage, normalizedSearch);

    attendanceStatusDrawerOpen.value = true;
    attendanceStatusActiveFilter.value = normalizedFilter;
    attendanceStatusError.value = '';

    const cached = attendanceStatusCache.value[cacheKey];
    if (cached) {
        attendanceStatusRows.value = cached.data || [];
        attendanceStatusPagination.value = cached.pagination || attendanceStatusPagination.value;
        attendanceStatusMeta.value = cached.meta || attendanceStatusMeta.value;
        attendanceStatusLoading.value = false;
        attendanceStatusRequestingKey.value = '';
        return;
    }

    if (attendanceStatusLoading.value && attendanceStatusRequestingKey.value === cacheKey) {
        return;
    }

    attendanceStatusLoading.value = true;
    attendanceStatusRequestingKey.value = cacheKey;
    attendanceStatusRows.value = [];

    try {
        const response = await axios.get('/homepage/leader-dashboard/attendance-statuses', {
            params: {
                filter: normalizedFilter,
                page: targetPage,
                per_page: attendanceStatusPagination.value.per_page || 10,
                search: normalizedSearch || undefined,
            },
        });

        const payload = {
            data: Array.isArray(response?.data?.data) ? response.data.data : [],
            pagination: response?.data?.pagination || attendanceStatusPagination.value,
            meta: response?.data?.meta || {
                date: '',
                filter: normalizedFilter,
                filters: {},
            },
        };

        attendanceStatusCache.value = {
            ...attendanceStatusCache.value,
            [cacheKey]: payload,
        };
        if (attendanceStatusRequestingKey.value !== cacheKey) {
            return;
        }
        attendanceStatusRows.value = payload.data;
        attendanceStatusPagination.value = payload.pagination;
        attendanceStatusMeta.value = payload.meta;
    } catch (error) {
        console.error('Failed to load attendance status rows', error);
        if (attendanceStatusRequestingKey.value !== cacheKey) {
            return;
        }
        attendanceStatusError.value = 'Gagal memuat detail status absensi. Silakan coba lagi.';
        attendanceStatusRows.value = [];
        attendanceStatusPagination.value = {
            current_page: 1,
            last_page: 1,
            per_page: attendanceStatusPagination.value.per_page || 10,
            total: 0,
            has_prev: false,
            has_next: false,
        };
    } finally {
        if (attendanceStatusRequestingKey.value === cacheKey) {
            attendanceStatusLoading.value = false;
            attendanceStatusRequestingKey.value = '';
        }
    }
}

function openAttendanceStatusDrawer(filter = 'all') {
    attendanceStatusSearchInput.value = '';
    attendanceStatusSearchKeyword.value = '';
    void loadAttendanceStatusRows(filter, 1, { search: '' });
}

function setAttendanceStatusFilter(filter) {
    const normalized = normalizeAttendanceStatusFilter(filter);
    if (normalized === attendanceStatusActiveFilter.value) {
        return;
    }

    void loadAttendanceStatusRows(normalized, 1);
}

function closeAttendanceStatusDrawer() {
    attendanceStatusDrawerOpen.value = false;
}

function goToAttendanceStatusPage(step) {
    if (!attendanceStatusDrawerOpen.value) {
        return;
    }

    const currentPage = Number(attendanceStatusPagination.value.current_page || 1);
    const nextPage = Math.max(1, currentPage + Number(step || 0));
    if (nextPage === currentPage) {
        return;
    }

    void loadAttendanceStatusRows(attendanceStatusActiveFilter.value, nextPage);
}

function retryAttendanceStatusLoad() {
    const page = Number(attendanceStatusPagination.value.current_page || 1);
    void loadAttendanceStatusRows(attendanceStatusActiveFilter.value, page);
}

function clearAttendanceStatusSearch() {
    attendanceStatusSearchInput.value = '';
    attendanceStatusSearchKeyword.value = '';

    if (!attendanceStatusDrawerOpen.value) {
        return;
    }

    void loadAttendanceStatusRows(attendanceStatusActiveFilter.value, 1, { search: '' });
}

function goToAttendanceIssuePage(step) {
    if (!attendanceIssueDrawerOpen.value || !attendanceIssueActiveDate.value) {
        return;
    }

    const currentPage = Number(attendanceIssuePagination.value.current_page || 1);
    const nextPage = Math.max(1, currentPage + Number(step || 0));

    if (nextPage === currentPage) {
        return;
    }

    void loadAttendanceIssues(attendanceIssueActiveDate.value, nextPage);
}

watch(searchKeyword, () => {
    if (suppressMemberSearchWatch.value) {
        return;
    }

    if (memberSearchDebounce) {
        clearTimeout(memberSearchDebounce);
    }

    memberSearchDebounce = setTimeout(() => {
        if (!memberSectionRequested.value && !memberSectionLoaded.value) {
            return;
        }

        void loadMembers(1);
    }, 250);
});

watch(attendanceStatusSearchInput, (value) => {
    if (attendanceStatusSearchDebounce) {
        clearTimeout(attendanceStatusSearchDebounce);
    }

    attendanceStatusSearchDebounce = setTimeout(() => {
        const normalized = String(value || '').trim();
        if (normalized === attendanceStatusSearchKeyword.value) {
            return;
        }

        attendanceStatusSearchKeyword.value = normalized;
        if (!attendanceStatusDrawerOpen.value) {
            return;
        }

        void loadAttendanceStatusRows(attendanceStatusActiveFilter.value, 1, { search: normalized });
    }, 280);
});

watch(kpiDetailSearchInput, (value) => {
    if (kpiDetailSearchDebounce) {
        clearTimeout(kpiDetailSearchDebounce);
    }

    kpiDetailSearchDebounce = setTimeout(() => {
        const normalized = String(value || '').trim();
        if (normalized === kpiDetailSearchKeyword.value) {
            return;
        }
        kpiDetailSearchKeyword.value = normalized;
        if (!kpiDetailDrawerOpen.value) {
            return;
        }
        kpiDetailOpenedMemberId.value = '';
        void loadKpiDetails(kpiDetailMode.value, 1, { search: normalized });
    }, 280);
});

watch(lmsDetailSearchInput, (value) => {
    if (lmsDetailSearchDebounce) {
        clearTimeout(lmsDetailSearchDebounce);
    }

    lmsDetailSearchDebounce = setTimeout(() => {
        const normalized = String(value || '').trim();
        if (normalized === lmsDetailSearchKeyword.value) {
            return;
        }
        lmsDetailSearchKeyword.value = normalized;
        if (!lmsDetailDrawerOpen.value) {
            return;
        }
        void loadLmsDetailRows(1, {
            focus: lmsDetailFocus.value,
            search: normalized,
        });
    }, 280);
});

onMounted(() => {
    void bootstrapLeaderDashboardParallel();
});

onBeforeUnmount(() => {
    if (memberSearchDebounce) {
        clearTimeout(memberSearchDebounce);
    }
    if (attendanceStatusSearchDebounce) {
        clearTimeout(attendanceStatusSearchDebounce);
    }
    if (kpiDetailSearchDebounce) {
        clearTimeout(kpiDetailSearchDebounce);
    }
    if (lmsDetailSearchDebounce) {
        clearTimeout(lmsDetailSearchDebounce);
    }

    Object.values(summaryRequestControllers.value || {}).forEach((controller) => {
        if (controller) {
            controller.abort();
        }
    });

    if (teamCalendarMonthController.value) {
        teamCalendarMonthController.value.abort();
    }
    if (teamCalendarDayDetailController.value) {
        teamCalendarDayDetailController.value.abort();
    }
});

async function ensureShiftSchedulesLoaded() {
    if (
        shiftSectionLoaded.value &&
        shiftSectionLoadedDate.value === selectedShiftDate.value &&
        shiftSectionData.value.length
    ) {
        return;
    }

    await loadShiftBoard(selectedShiftDate.value || new Date().toISOString().slice(0, 10));
}

async function loadShiftDetail(page = 1, shift = null) {
    const targetShift =
        shift ||
        selectedDateShiftSummary.value.find((item) => item.key === selectedShiftKey.value) ||
        selectedDateShiftSummary.value[0] ||
        null;

    if (!targetShift) {
        return;
    }

    const targetDate = selectedShiftDate.value || targetShift.date;
    const cacheKey = getShiftCacheKey(targetDate, targetShift.name, page);
    selectedShiftKey.value = targetShift.key;
    selectedShiftDetailPage.value = page;

    if (shiftDetailCache.value[cacheKey]) {
        selectedShiftDetailData.value = shiftDetailCache.value[cacheKey];
        shiftDetailPagination.value = shiftDetailCache.value[cacheKey].pagination || shiftDetailPagination.value;
        return;
    }

    shiftDetailLoading.value = true;

    try {
        const response = await axios.get('/homepage/leader-dashboard/shifts', {
            params: {
                date: targetDate,
                shift_name: targetShift.name,
                page,
                per_page: shiftDetailPagination.value.per_page || 8,
            },
        });

        const payload = {
            shift: response?.data?.shift || targetShift,
            data: Array.isArray(response?.data?.data) ? response.data.data.map(normalizeMember) : [],
            pagination: response?.data?.pagination || shiftDetailPagination.value,
        };

        shiftDetailCache.value = {
            ...shiftDetailCache.value,
            [cacheKey]: payload,
        };
        selectedShiftDetailData.value = payload;
        shiftDetailPagination.value = payload.pagination;
    } catch (error) {
        console.error('Failed to load shift detail', error);
    } finally {
        shiftDetailLoading.value = false;
    }
}

function openShiftDetail(shift) {
    if (!shift) return;
    void loadShiftDetail(1, shift);
}

async function fetchMemberDetail(memberId) {
    const contextYears = getMemberDetailContextYears();
    const normalizedMemberId = normalizeMemberDetailMemberId(memberId);
    const cacheKey = getMemberDetailCacheKey(normalizedMemberId, contextYears);

    if (!cacheKey || memberDetailCache.value[cacheKey] || memberDetailLoadingId.value === cacheKey) return;

    memberDetailLoadingId.value = cacheKey;
    const startedAt = Date.now();

    try {
        const response = await axios.get(
            `/homepage/leader-dashboard/members/${encodeURIComponent(normalizedMemberId)}/detail`,
            {
                params: {
                    kpi_year: contextYears.kpiYear,
                    lms_year: contextYears.lmsYear,
                },
            },
        );

        if (response?.data?.data) {
            initializeMemberKpiPanelState(normalizedMemberId, response.data.data);
            memberDetailCache.value = {
                ...memberDetailCache.value,
                [cacheKey]: response.data.data,
            };
        }
    } catch (error) {
        console.error('Failed to load member detail', error);
    } finally {
        const elapsed = Date.now() - startedAt;
        if (elapsed < MEMBER_DETAIL_MIN_LOADING_MS) {
            await new Promise((resolve) => setTimeout(resolve, MEMBER_DETAIL_MIN_LOADING_MS - elapsed));
        }

        if (memberDetailLoadingId.value === cacheKey) {
            memberDetailLoadingId.value = '';
        }
    }
}

async function toggleMember(member) {
    const id = typeof member === 'object' ? member?.id : member;
    if (!id) return;
    if (typeof member === 'object' && member) {
        initializeMemberKpiPanelState(id, member);
    }

    memberSectionRequested.value = true;
    await ensureMembersLoaded();

    const willOpen = openedMemberId.value !== id;
    openedMemberId.value = willOpen ? id : null;

    if (willOpen) {
        await fetchMemberDetail(id);
    }
}

async function openAction(action) {
    const memberId = action?.memberId || action?.member_id || '';
    const searchTerm = resolveActionMemberSearchTerm(action);
    if (!memberId) {
        memberSectionRequested.value = true;
        searchKeyword.value = searchTerm || '';
        await ensureMembersLoaded();
        await nextTick();
        scrollToTeamListSection();
        return;
    }

    memberSectionRequested.value = true;
    await nextTick();
    scrollToTeamListSection();

    try {
        suppressMemberSearchWatch.value = true;
        if (memberSearchDebounce) {
            clearTimeout(memberSearchDebounce);
            memberSearchDebounce = null;
        }

        // Saat klik dari Action Center, paksa Team List ke mode netral agar member tidak tertutup filter lama.
        searchKeyword.value = searchTerm || '';
        selectedAttendance.value = 'all';
        selectedKpi.value = 'all';
        selectedLms.value = 'all';

        const located = await findMemberPageForAction(action);

        if (located) {
            memberSectionData.value = located.rows;
            memberSectionPagination.value = located.pagination;
            memberSectionLoaded.value = true;
            const targetMember = located.found || located.rows?.[0] || null;
            if (targetMember?.id) {
                const targetMemberId = normalizeMemberDetailMemberId(targetMember.id);
                openedMemberId.value = targetMemberId;
                await fetchMemberDetail(targetMemberId);
                return;
            }
        }
    } catch (error) {
        console.error('Failed to locate member page from action center', error);
    } finally {
        suppressMemberSearchWatch.value = false;
    }

    // Fallback: tetap coba buka detail meski page lookup gagal.
    await ensureMembersLoaded();
    openedMemberId.value = normalizeMemberDetailMemberId(memberId);
    await fetchMemberDetail(memberId);
}

function setQuickFilter(key) {
    memberSectionRequested.value = true;
    void ensureMembersLoaded();

    if (key === 'approval') {
        selectedAttendance.value = 'all';
        selectedKpi.value = 'all';
        selectedLms.value = 'all';
        return;
    }

    if (key === 'follow-up' || key === 'hcis-issue') {
        selectedAttendance.value = 'all';
        selectedKpi.value = 'all';
        selectedLms.value = 'all';
        return;
    }

    if (key === 'coverage') {
        selectedAttendance.value = 'leave';
        selectedKpi.value = 'all';
        selectedLms.value = 'all';
        return;
    }

    if (key === 'kpi-alert') {
        selectedAttendance.value = 'all';
        selectedKpi.value = 'kpi-alert';
        selectedLms.value = 'all';
        return;
    }

    if (key === 'lms-risk') {
        selectedAttendance.value = 'all';
        selectedKpi.value = 'all';
        selectedLms.value = 'pending';
        return;
    }

    selectedAttendance.value = 'all';
    selectedKpi.value = 'all';
    selectedLms.value = 'all';
}

function resetMemberFilters() {
    const hadKeyword = Boolean(searchKeyword.value.trim());

    searchKeyword.value = '';
    selectedAttendance.value = 'all';
    selectedKpi.value = 'all';
    selectedLms.value = 'all';
    openedMemberId.value = null;

    if (memberSectionLoaded.value && !hadKeyword) {
        void loadMembers(1);
    }
}

function memberAttendanceFilterLabel(value) {
    const labels = {
        all: 'Semua Absensi',
        present: 'Hadir',
        absent: 'Alpha / Tidak Hadir',
        late: 'Terlambat',
        'no-checkout': 'Belum Checkout',
        leave: 'Izin / Cuti / Sakit',
    };

    return labels[value] || 'Absensi';
}

function memberKpiFilterLabel(value) {
    const labels = {
        all: 'Semua KPI',
        'kpi-alert': 'Ada Alert KPI',
        complete: 'KPI Lengkap',
    };

    return labels[value] || 'KPI';
}

function memberLmsFilterLabel(value) {
    const labels = {
        all: 'Semua LMS',
        pending: 'Training Pending',
        complete: 'Training Selesai',
    };

    return labels[value] || 'LMS';
}

function urgencyScore(member) {
    const attendanceIssueCount = Math.max(
        Number(member?.attendance?.issueCount || 0),
        member?.attendance?.issues?.length || 0,
    );
    const approvalCount = Number(member?.approvalCount || 0);
    const kpiAlertCount = member?.kpi?.pendingItems?.length || 0;
    const lmsProgress = Number(member?.lms?.progress || 0);

    const statusRiskWeight = {
        absent: 5,
        'no-checkin': 5,
        'no-checkout': 4,
        late: 3,
        leave: 2,
        permission: 2,
        sick: 1,
        working: 1,
        present: 0,
    };

    const statusRisk = statusRiskWeight[member?.attendance?.todayStatus] || 0;
    const lmsRisk = lmsProgress < 80 ? 3 : lmsProgress < 100 ? 1 : 0;

    return attendanceIssueCount * 4 + approvalCount * 3 + kpiAlertCount * 2 + lmsRisk + statusRisk;
}

function urgencyLevel(score) {
    if (score >= 12) return 'high';
    if (score >= 5) return 'medium';
    return 'low';
}

function urgencyLabel(level) {
    const labels = {
        high: 'Prioritas Tinggi',
        medium: 'Perlu Atensi',
        low: 'Aman',
    };

    return labels[level] || labels.medium;
}

function buildPriorityReasons(member) {
    const attendanceIssueCount = Math.max(
        Number(member?.attendance?.issueCount || 0),
        member?.attendance?.issues?.length || 0,
    );
    const approvalCount = Number(member?.approvalCount || 0);
    const kpiAlertCount = member?.kpi?.pendingItems?.length || 0;
    const lmsProgress = Number(member?.lms?.progress || 0);
    const status = String(member?.attendance?.todayStatus || 'present');

    const statusRiskWeight = {
        absent: 5,
        'no-checkin': 5,
        'no-checkout': 4,
        late: 3,
        leave: 2,
        permission: 2,
        sick: 1,
        working: 1,
        present: 0,
    };

    const statusRisk = Number(statusRiskWeight[status] || 0);
    const lmsRisk = lmsProgress < 80 ? 3 : lmsProgress < 100 ? 1 : 0;
    const topAttendanceIssue = member?.attendance?.issues?.[0];

    const reasonCandidates = [
        {
            key: 'attendance',
            label: 'Absensi bermasalah',
            valueLabel: `${attendanceIssueCount} issue`,
            scoreImpact: attendanceIssueCount * 4,
            evidence:
                topAttendanceIssue?.title ||
                (attendanceIssueCount > 0
                    ? `${statusLabel(status)} pada hari kerja perlu validasi.`
                    : 'Tidak ada issue absensi aktif.'),
        },
        {
            key: 'approval',
            label: 'Approval tertunda',
            valueLabel: `${approvalCount} approval`,
            scoreImpact: approvalCount * 3,
            evidence:
                approvalCount > 0
                    ? `Ada ${approvalCount} pengajuan menunggu keputusan atasan.`
                    : 'Tidak ada approval tertunda.',
        },
        {
            key: 'kpi',
            label: 'Alert KPI',
            valueLabel: `${kpiAlertCount} alert`,
            scoreImpact: kpiAlertCount * 2,
            evidence:
                member?.kpi?.pendingItems?.[0]?.title ||
                (kpiAlertCount > 0 ? 'Terdapat KPI yang belum lengkap pada periode berjalan.' : 'Tidak ada alert KPI.'),
        },
        {
            key: 'lms',
            label: 'Progress LMS',
            valueLabel: `${lmsProgress}%`,
            scoreImpact: lmsRisk,
            evidence:
                lmsProgress < 80
                    ? 'Progress masih di bawah ambang aman tim (< 80%).'
                    : lmsProgress < 100
                      ? 'Progress belum selesai penuh.'
                      : 'Progress training sudah lengkap.',
        },
        {
            key: 'status',
            label: 'Status kehadiran hari ini',
            valueLabel: member?.attendance?.todayLabel || statusLabel(status),
            scoreImpact: statusRisk,
            evidence:
                statusRisk > 0
                    ? `Status ${statusLabel(status)} menambah risiko monitoring harian.`
                    : 'Status kehadiran hari ini normal.',
        },
    ];

    const positives = reasonCandidates
        .filter((item) => item.scoreImpact > 0)
        .sort((left, right) => right.scoreImpact - left.scoreImpact);
    const neutral = reasonCandidates
        .filter((item) => item.scoreImpact === 0)
        .sort((left, right) => left.label.localeCompare(right.label, 'id-ID'));

    const combined = [...positives, ...neutral].slice(0, 5);

    if (combined.length >= 3) {
        return combined;
    }

    return [...combined, ...neutral].slice(0, 3);
}

function buildNextActions(member, reasons = []) {
    const attendanceIssueCount = Math.max(
        Number(member?.attendance?.issueCount || 0),
        member?.attendance?.issues?.length || 0,
    );
    const approvalCount = Number(member?.approvalCount || 0);
    const kpiAlertCount = member?.kpi?.pendingItems?.length || 0;
    const lmsProgress = Number(member?.lms?.progress || 0);
    const status = String(member?.attendance?.todayStatus || 'present');
    const actions = [];

    if (attendanceIssueCount > 0 || ['absent', 'no-checkin', 'no-checkout', 'late'].includes(status)) {
        actions.push({
            id: 'attendance-follow-up',
            label: 'Validasi status absensi hari ini',
            impact: 'Kurangi risiko absensi tidak tervalidasi dan salah keputusan operasional.',
            urgency: 'Urgensi: Hari ini',
            scoreImpact: Math.max(4, attendanceIssueCount * 4),
            onClickType: 'attendance-status',
            onClickPayload: {
                filter: ['absent', 'no-checkin'].includes(status) ? 'absent' : status === 'late' ? 'late' : 'all',
            },
        });
    }

    if (approvalCount > 0) {
        actions.push({
            id: 'approval-queue',
            label: 'Prioritaskan approval yang tertunda',
            impact: 'Mencegah bottleneck keputusan kerja dan administrasi tim.',
            urgency: 'Urgensi: Sebelum akhir hari',
            scoreImpact: approvalCount * 3,
            onClickType: 'kpi-drawer',
            onClickPayload: { context: 'approval_queue' },
        });
    }

    if (kpiAlertCount > 0) {
        actions.push({
            id: 'kpi-alert',
            label: 'Tinjau item KPI yang belum lengkap',
            impact: 'Menjaga target performa periode tidak tertunda.',
            urgency: 'Urgensi: 1x24 jam',
            scoreImpact: kpiAlertCount * 2,
            onClickType: 'kpi-drawer',
            onClickPayload: { context: 'monthly_avg' },
        });
    }

    if (lmsProgress < 100) {
        actions.push({
            id: 'lms-progress',
            label: 'Dorong penyelesaian training LMS',
            impact: 'Menurunkan risiko gap kompetensi anggota.',
            urgency: lmsProgress < 80 ? 'Urgensi: Tinggi minggu ini' : 'Urgensi: Monitoring minggu ini',
            scoreImpact: lmsProgress < 80 ? 3 : 1,
            onClickType: 'lms-drawer',
            onClickPayload: { focus: lmsProgress < 80 ? 'progress_below_target' : 'lms_not_started' },
        });
    }

    if (!actions.length) {
        actions.push({
            id: 'monitor-routine',
            label: 'Lanjutkan monitoring rutin anggota ini',
            impact: 'Pertahankan stabilitas performa dan kepatuhan harian.',
            urgency: 'Urgensi: Review mingguan',
            scoreImpact: 0,
            onClickType: 'focus-member',
            onClickPayload: {},
        });
    }

    return actions.sort((left, right) => right.scoreImpact - left.scoreImpact).slice(0, 3);
}

function priorityHeadline(member, reasons = [], actions = []) {
    const firstReason = reasons[0];
    if (!firstReason) {
        return member?.followUpSummary || buildFollowUpSummary(member);
    }

    const firstAction = actions[0]?.label || 'Lanjutkan monitoring rutin anggota ini.';
    return `${firstReason.label} (${firstReason.valueLabel}) menjadi faktor utama. ${firstAction}`;
}

function buildPrioritySummary(member, reasons = [], actions = []) {
    const firstReason = reasons[0];
    const action = actions[0];
    const delayImpactMap = {
        attendance: 'Risiko ketidakhadiran/lembur tidak tervalidasi bisa mengganggu coverage shift.',
        approval: 'Pengajuan kerja tertahan dan keputusan operasional tim melambat.',
        kpi: 'Target KPI periode berjalan berpotensi miss karena item belum lengkap.',
        lms: 'Gap kompetensi meningkat karena training belum selesai.',
        status: 'Status kehadiran berisiko tinggi dan butuh validasi cepat.',
    };

    return {
        headline: priorityHeadline(member, reasons, actions),
        mainIssue: firstReason
            ? `${firstReason.label} (${firstReason.valueLabel})`
            : 'Tidak ada indikator risiko kritis.',
        delayImpact: firstReason
            ? delayImpactMap[firstReason.key] || 'Perlu follow up agar risiko tidak membesar.'
            : 'Risiko saat ini rendah dan stabil.',
        firstAction: action?.label || 'Lanjutkan monitoring rutin anggota ini.',
    };
}

function handleMemberPriorityAction(member, action) {
    if (!action?.onClickType) {
        return;
    }

    if (action.onClickType === 'attendance-status') {
        openAttendanceStatusDrawer(action?.onClickPayload?.filter || 'all');
        return;
    }

    if (action.onClickType === 'kpi-drawer') {
        openKpiDetailDrawer(action?.onClickPayload?.context || 'monthly_avg');
        return;
    }

    if (action.onClickType === 'lms-drawer') {
        openLmsDetailDrawer(action?.onClickPayload?.focus || 'lms_not_started');
        return;
    }

    if (action.onClickType === 'focus-member') {
        void openAction({ memberId: member?.id });
    }
}

function attendanceStatusClass(status) {
    const classes = {
        present: 'is-success',
        working: 'is-info',
        'not-started': 'is-secondary',
        'pending-checkin': 'is-secondary',
        'no-checkin': 'is-danger',
        absent: 'is-danger',
        late: 'is-warning',
        'no-checkout': 'is-warning',
        leave: 'is-info',
        permission: 'is-info',
        sick: 'is-info',
        cuti: 'is-info',
    };

    return classes[status] || 'is-default';
}

function statusLabel(status) {
    const labels = {
        present: 'Hadir',
        working: 'Sedang Bekerja',
        'not-started': 'Belum Dimulai',
        'pending-checkin': 'Belum Dilakukan',
        'no-checkin': 'Tidak Check In / NoCO',
        absent: 'Tidak Hadir',
        late: 'Terlambat',
        'no-checkout': 'Belum Checkout',
        leave: 'Cuti',
        permission: 'Izin',
        sick: 'Sakit',
        cuti: 'Cuti',
    };

    return labels[status] || 'Belum Ada Data';
}

function approvalStatusClass(item) {
    const label = String(item?.statusLabel || '')
        .trim()
        .toLowerCase();
    const status = String(item?.status || '')
        .trim()
        .toLowerCase();
    const tone = String(item?.tone || '')
        .trim()
        .toLowerCase();
    if (label === 'expired' || status === 'expired' || tone === 'danger') {
        return 'is-expired';
    }
    if (tone === 'warning' || label === 'pending') {
        return 'is-pending';
    }
    if (tone === 'info' || label === 'future') {
        return 'is-future';
    }
    return 'is-approved';
}

function buildFollowUpSummary(member) {
    const approvalCount = Number(member?.approvalCount || 0);
    if (approvalCount > 0) {
        return `Ada ${approvalCount} pengajuan yang menunggu approval.`;
    }
    if (member.attendance.todayStatus === 'not-started') {
        return 'Jam kerja belum dimulai, status belum dihitung sebagai issue.';
    }
    if (member.attendance.todayStatus === 'pending-checkin') {
        return 'Belum masuk jam check-in, masih menunggu waktu mulai shift.';
    }
    if (member.attendance.todayStatus === 'no-checkin') {
        return 'Check-in tidak tercatat setelah jam masuk shift, perlu validasi NoCO.';
    }
    if (member.attendance.todayStatus === 'no-checkout') {
        return 'Check-out belum tercatat setelah jam pulang, perlu dicek apakah masih bekerja atau lupa absen.';
    }
    if (member.attendance.todayStatus === 'working') {
        return 'Sedang bekerja, jam pulang belum lewat atau belum checkout.';
    }
    if (member.attendance.todayStatus === 'absent') {
        return 'Tidak hadir hari ini dan perlu validasi izin/sakit/cuti.';
    }
    if (member.attendance.earlyOutCount > 0) {
        return `Ada ${member.attendance.earlyOutCount} kejadian pulang cepat pada periode ini.`;
    }
    if (member.kpi.pendingItems.length) {
        return `Ada ${member.kpi.pendingItems.length} alert KPI yang perlu diperhatikan.`;
    }
    if (member.lms.progress < 100) return `LMS belum selesai, progress saat ini ${member.lms.progress}%.`;

    return 'Tidak ada item mendesak pada periode ini.';
}

function isMemberDetailLoading(memberId) {
    const cacheKey = getMemberDetailCacheKey(memberId);
    if (!cacheKey) {
        return false;
    }

    return Boolean(memberDetailLoadingId.value === cacheKey && !memberDetailCache.value[cacheKey]);
}

function safePercent(value) {
    const number = Number(value || 0);
    if (Number.isNaN(number)) return 0;

    return Math.max(0, Math.min(number, 100));
}

function formatNumber(value, unit = '') {
    if (value === null || value === undefined || value === '') return '-';
    const number = Number(value);
    if (Number.isNaN(number)) return String(value || '-') + (unit || '');

    const rounded = Math.round(number * 10) / 10;
    const isInteger = Math.abs(rounded - Math.round(rounded)) < 1e-9;

    if (isInteger) {
        return String(Math.round(rounded)) + (unit || '');
    }

    // ensure one decimal place when needed
    return String(Math.round(rounded * 10) / 10) + (unit || '');
}

function calcProgress(completed, total) {
    const c = Number(completed || 0);
    const t = Number(total || 0);

    if (!t) return 0;

    return Math.round((c / t) * 100);
}

function sumBy(items, fn) {
    return items.reduce((total, item) => total + Number(fn(item) || 0), 0);
}

function getInitials(name) {
    return String(name || 'U')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase();
}

function cryptoRandomId() {
    return `member-${Math.random().toString(36).slice(2)}`;
}

function formatDateLabel(value) {
    const date = new Date(value);

    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function resolveKpiTrendMonthPeriodByIndex(index) {
    const trendItem = teamKpiTrend.value[Number(index || 0)] || null;
    const monthLabel = String(trendItem?.month || '')
        .trim()
        .toLowerCase();
    const year = Number(summary.value.kpiYear || new Date().getFullYear());
    const map = {
        jan: '01',
        feb: '02',
        mar: '03',
        apr: '04',
        mei: '05',
        may: '05',
        jun: '06',
        jul: '07',
        agu: '08',
        aug: '08',
        sep: '09',
        okt: '10',
        oct: '10',
        nov: '11',
        des: '12',
        dec: '12',
    };
    const mm = map[monthLabel] || String(Math.max(1, Math.min(12, Number(index || 0) + 1))).padStart(2, '0');
    return `${year}-${mm}`;
}
</script>
<style scoped>
*,
*::before,
*::after {
    box-sizing: border-box;
}

.leader-dashboard-page {
    width: 100%;
    min-width: 0;
    min-height: auto;
    overflow-x: hidden;
    /* padding: 0.65rem; */
    color: #0f172a;
    /* background:
        radial-gradient(circle at top left, rgba(99, 102, 241, 0.1), transparent 22rem),
        linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%); */
}

.leader-dashboard-container {
    width: 100%;
    /* max-width: 1480px; */
    min-width: 0;
    margin: 0 auto;
    overflow-x: hidden;
}

.inline-link-btn {
    border: 0;
    background: transparent;
    color: #4f46e5;
    font-weight: 700;
    padding: 0;
    margin: 0 0 0 0.25rem;
    cursor: pointer;
    text-decoration: underline;
}

.bi {
    display: inline-flex;
    line-height: 1;
}

button,
input,
select {
    font: inherit;
}

.section-label {
    display: block;
    font-size: 0.58rem;
    font-weight: 950;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #64748b;
}

.leader-hero {
    position: relative;
    overflow: hidden;
    border-radius: 1rem;
    padding: 0.75rem;
    color: white;
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.24), transparent 17rem),
        linear-gradient(135deg, #4f46e5, #2563eb);
    box-shadow: 0 16px 34px rgba(37, 99, 235, 0.18);
}

.leader-hero__content {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(260px, 330px);
    gap: 0.65rem;
    min-width: 0;
}

.hero-left,
.hero-right {
    min-width: 0;
}

.hero-topline {
    display: flex;
    gap: 0.35rem;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
}

.hero-eyebrow,
.period-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.32rem;
    max-width: 100%;
    padding: 0.24rem 0.48rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.18);
    font-size: 0.62rem;
    font-weight: 900;
}

.period-pill {
    color: rgba(255, 255, 255, 0.86);
}

.hero-title-row {
    margin-top: 0.55rem;
}

.hero-title-row h1 {
    margin: 0;
    color: white;
    font-size: clamp(1.25rem, 2vw, 1.9rem);
    font-weight: 950;
    letter-spacing: -0.04em;
}

.hero-title-row p {
    max-width: 780px;
    margin: 0.3rem 0 0;
    color: rgba(255, 255, 255, 0.84);
    font-size: 0.74rem;
    line-height: 1.4;
}

.hero-brief-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.35rem;
    margin-top: 0.65rem;
}

.hero-brief-grid > div,
.hero-focus-card {
    min-width: 0;
    padding: 0.48rem;
    border-radius: 0.68rem;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.18);
}

.hero-brief-grid span,
.hero-focus-card span,
.hero-focus-card small,
.hero-brief-grid small {
    display: block;
    color: rgba(255, 255, 255, 0.72);
    font-size: 0.54rem;
    font-weight: 850;
}

.hero-brief-grid strong,
.hero-focus-card strong {
    display: block;
    margin-top: 0.08rem;
    font-size: 1rem;
    font-weight: 950;
}

.hero-focus-card {
    height: 100%;
}

.hero-focus-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.55rem;
}

.hero-focus-icon {
    width: 2.25rem;
    height: 2.25rem;
    flex: 0 0 auto;
    display: grid;
    place-items: center;
    border-radius: 0.7rem;
    background: rgba(255, 255, 255, 0.14);
}

.hero-focus-list {
    display: grid;
    gap: 0.32rem;
    margin-top: 0.45rem;
}

.hero-focus-item {
    width: 100%;
    display: flex;
    gap: 0.35rem;
    min-width: 0;
    padding: 0.38rem;
    border: 0;
    border-radius: 0.58rem;
    background: rgba(255, 255, 255, 0.12);
    color: white;
    text-align: left;
    cursor: pointer;
}

.hero-focus-item > div {
    min-width: 0;
}

.hero-focus-item strong,
.hero-focus-item span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hero-focus-item strong {
    font-size: 0.58rem;
}

.quick-stat-grid,
.team-insight-grid {
    display: grid;
    gap: 0.42rem;
    margin-top: 0.5rem;
    min-width: 0;
}

.quick-stat-grid {
    grid-template-columns: repeat(5, minmax(0, 1fr));
}

.team-insight-grid {
    grid-template-columns: 1.15fr 1fr 1fr;
}

.quick-stat-card,
.insight-card,
.toolbar-card,
.member-card,
.side-card,
.shift-board-card {
    min-width: 0;
    overflow: hidden;
    border: 1px solid rgba(226, 232, 240, 0.92);
    border-radius: 0.82rem;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.032);
}

.quick-stat-card {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    min-height: 3.25rem;
    padding: 0.48rem;
    text-align: left;
    cursor: pointer;
    border: 1px solid rgba(226, 232, 240, 0.92);
}

.quick-stat-card__icon {
    flex: 0 0 auto;
    width: 1.9rem;
    height: 1.9rem;
    display: grid;
    place-items: center;
    border-radius: 0.62rem;
    background: #eef2ff;
    color: #4f46e5;
}

.quick-stat-card__copy {
    min-width: 0;
}

.quick-stat-card strong {
    display: block;
    color: #0f172a;
    font-size: 0.88rem;
    font-weight: 950;
    line-height: 1;
}

.quick-stat-card span {
    display: block;
    margin-top: 0.12rem;
    color: #334155;
    font-size: 0.58rem;
    font-weight: 900;
}

.quick-stat-card small {
    display: block;
    margin-top: 0.04rem;
    color: #64748b;
    font-size: 0.5rem;
    font-weight: 750;
}

.quick-stat-card.is-danger .quick-stat-card__icon,
.mini-metric.is-danger-soft {
    background: #fee2e2;
    color: #dc2626;
}

.quick-stat-card.is-warning .quick-stat-card__icon {
    background: #fef3c7;
    color: #d97706;
}

.quick-stat-card.is-purple .quick-stat-card__icon {
    background: #ede9fe;
    color: #7c3aed;
}

.quick-stat-card.is-info .quick-stat-card__icon {
    background: #e0f2fe;
    color: #0284c7;
}

.quick-stat-card.is-success .quick-stat-card__icon {
    background: #dcfce7;
    color: #16a34a;
}

.quick-stat-card.is-orange .quick-stat-card__icon {
    background: #ffedd5;
    color: #ea580c;
}

.insight-card,
.toolbar-card,
.side-card,
.shift-board-card {
    padding: 0.58rem;
}

.insight-card__head,
.side-card__head,
.section-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.45rem;
    margin-bottom: 0.45rem;
}

.insight-card__head.is-between {
    align-items: center;
}

.insight-card h2,
.toolbar-card h2,
.side-card h2,
.section-head h2 {
    margin: 0.04rem 0 0;
    font-size: 0.8rem;
    font-weight: 950;
    color: #0f172a;
}

.mini-badge,
.side-count {
    flex: 0 0 auto;
    padding: 0.2rem 0.4rem;
    border-radius: 999px;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 0.54rem;
    font-weight: 950;
}

.mini-badge.is-teal,
.side-count.is-teal {
    background: #ccfbf1;
    color: #0f766e;
}

.hcis-insight-card .insight-card__head.is-between {
    align-items: flex-start;
}

.hcis-head-note {
    display: block;
    margin-top: 0.18rem;
    color: #64748b;
    font-size: 0.64rem;
    font-weight: 700;
    line-height: 1.35;
}

.side-count.is-success {
    background: #dcfce7;
    color: #15803d;
}

.side-count.is-warning {
    background: #fef3c7;
    color: #b45309;
}

.hcis-insight-layout,
.approval-summary,
.lms-overview {
    display: grid;
    gap: 0.4rem;
}

.hcis-status-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.32rem;
}

.hcis-status-grid > div,
.metric-grid > div,
.kpi-period-box > div {
    min-width: 0;
    padding: 0.4rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.58rem;
    background: #f8fafc;
}

.hcis-status-grid span,
.hcis-status-grid small,
.metric-grid span,
.metric-grid small,
.kpi-period-box span,
.kpi-period-box small {
    display: block;
    color: #64748b;
    font-size: 0.5rem;
    font-weight: 850;
}

.hcis-status-grid strong,
.metric-grid strong,
.kpi-period-box strong {
    display: block;
    margin-top: 0.06rem;
    color: #0f172a;
    font-size: 0.66rem;
    font-weight: 950;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.status-bar-stack {
    display: flex;
    width: 100%;
    height: 0.55rem;
    overflow: hidden;
    border-radius: 999px;
    background: #e2e8f0;
}

.status-bar-stack__item.is-success {
    background: #22c55e;
}

.status-bar-stack__item.is-danger {
    background: #ef4444;
}

.status-bar-stack__item.is-info {
    background: #0ea5e9;
}

.mini-heatmap,
.heatmap-row > div {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.22rem;
}

.heat-cell {
    width: 100%;
    aspect-ratio: 1;
    min-height: 0.68rem;
    border-radius: 0.24rem;
    background: #e2e8f0;
}

.heat-cell.is-success {
    background: #86efac;
}

.heat-cell.is-warning {
    background: #fde68a;
}

.heat-cell.is-danger {
    background: #fca5a5;
}

.heat-cell.is-info {
    background: #bae6fd;
}

.heat-cell.is-orange {
    background: #fdba74;
}

.heat-cell.is-default {
    background: #e2e8f0;
}

.insight-note,
.coverage-alert {
    display: flex;
    gap: 0.35rem;
    align-items: flex-start;
    padding: 0.4rem;
    border-radius: 0.56rem;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 0.56rem;
    font-weight: 800;
    line-height: 1.35;
}

.top-performance-list {
    display: grid;
    gap: 0.32rem;
}

.top-performance-row {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.42rem;
    padding: 0.42rem;
    border-radius: 0.62rem;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
}

.rank-badge {
    width: 1.7rem;
    height: 1.7rem;
    display: grid;
    place-items: center;
    border-radius: 0.56rem;
    background: #ede9fe;
    color: #7c3aed;
    font-size: 0.58rem;
    font-weight: 950;
}

.top-performance-row.rank-1 .rank-badge {
    background: #fef3c7;
    color: #b45309;
}

.top-performance-user,
.top-performance-score {
    min-width: 0;
}

.top-performance-user strong,
.top-performance-score strong {
    display: block;
    color: #0f172a;
    font-size: 0.64rem;
    font-weight: 950;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.top-performance-user span,
.top-performance-score span {
    display: block;
    margin-top: 0.06rem;
    color: #64748b;
    font-size: 0.52rem;
    font-weight: 750;
}

.top-performance-score {
    text-align: right;
}

.mini-line-chart {
    display: flex;
    align-items: end;
    gap: 0.28rem;
    height: 3.4rem;
    margin-top: 0.4rem;
    padding: 0.3rem;
    border-radius: 0.6rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.mini-line-chart__bar {
    flex: 1;
    min-width: 0.4rem;
    border-radius: 999px 999px 0.2rem 0.2rem;
    background: linear-gradient(180deg, #8b5cf6, #6366f1);
}

.lms-overview {
    grid-template-columns: minmax(120px, 0.72fr) minmax(0, 1.28fr);
    align-items: start;
    gap: 0.4rem;
}

.lms-mini-stat-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.lms-ring {
    width: 5rem;
    height: 5rem;
    border-radius: 999px;
    display: grid;
    place-items: center;
    align-content: center;
    background:
        radial-gradient(circle at center, #fff 58%, transparent 59%),
        conic-gradient(#14b8a6 calc(var(--progress, 80) * 1%), #ccfbf1 0);
    border: 1px solid #ccfbf1;
}

.lms-ring strong {
    color: #0f766e;
    font-size: 0.9rem;
    font-weight: 950;
}

.lms-ring span {
    color: #0f766e;
    font-size: 0.48rem;
    font-weight: 850;
}

.lms-focus-panel {
    margin-top: 0.45rem;
    padding: 0.5rem;
    border: 1px solid #dbeafe;
    border-radius: 0.72rem;
    background: linear-gradient(180deg, #f8fbff, #f1f8ff);
}

.lms-focus-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.35rem;
}

.lms-focus-panel__head strong {
    font-size: 0.62rem;
    font-weight: 950;
    color: #0f172a;
}

.lms-focus-status {
    display: inline-flex;
    align-items: center;
    padding: 0.14rem 0.34rem;
    border-radius: 999px;
    font-size: 0.5rem;
    font-weight: 900;
    white-space: nowrap;
}

.lms-focus-status.is-warning {
    background: #fef3c7;
    color: #b45309;
}

.lms-focus-status.is-safe {
    background: #dcfce7;
    color: #166534;
}

.lms-focus-list {
    margin-top: 0.42rem;
    display: grid;
    gap: 0.28rem;
}

.lms-focus-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.35rem;
    padding: 0.36rem 0.42rem;
    border-radius: 0.58rem;
    border: 1px solid #e2e8f0;
    background: #ffffff;
}

.lms-focus-item__identity,
.lms-focus-item__meta {
    min-width: 0;
}

.lms-focus-item__identity strong {
    display: block;
    color: #0f172a;
    font-size: 0.58rem;
    font-weight: 900;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lms-focus-item__identity span {
    display: block;
    margin-top: 0.06rem;
    color: #64748b;
    font-size: 0.5rem;
    font-weight: 800;
}

.lms-focus-item__meta {
    text-align: right;
}

.lms-focus-item__meta b {
    display: block;
    color: #0f172a;
    font-size: 0.6rem;
    font-weight: 950;
}

.lms-focus-item__meta small {
    display: block;
    margin-top: 0.04rem;
    font-size: 0.48rem;
    font-weight: 850;
    color: #64748b;
}

.lms-focus-item.is-danger {
    border-color: #fecaca;
    background: #fff7f7;
}

.lms-focus-item.is-danger .lms-focus-item__meta b {
    color: #b91c1c;
}

.lms-focus-item.is-warning {
    border-color: #fde68a;
    background: #fffbeb;
}

.lms-focus-item.is-warning .lms-focus-item__meta b {
    color: #b45309;
}

.lms-focus-item.is-safe {
    border-color: #bbf7d0;
    background: #f0fdf4;
}

.lms-focus-item.is-safe .lms-focus-item__meta b {
    color: #15803d;
}

.lms-focus-empty {
    margin-top: 0.42rem;
}

.lms-focus-empty.is-waiting span {
    display: inline-flex;
    align-items: center;
    padding: 0.22rem 0.4rem;
    border-radius: 999px;
    background: #fffbeb;
    color: #9a3412;
    font-size: 0.5rem;
    font-weight: 850;
    line-height: 1.25;
}

.lms-safe-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    padding: 0.22rem 0.4rem;
    border-radius: 999px;
    background: #dcfce7;
    color: #166534;
    font-size: 0.52rem;
    font-weight: 900;
}

.lms-focus-alerts {
    margin-top: 0.42rem;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.24rem;
}

.lms-focus-alerts span {
    display: block;
    padding: 0.22rem 0.32rem;
    border-radius: 0.45rem;
    background: #ffffff;
    border: 1px solid #dbeafe;
    color: #475569;
    font-size: 0.5rem;
    font-weight: 850;
}

.lms-quick-tip {
    margin: 0.42rem 0 0;
    color: #0f766e;
    font-size: 0.52rem;
    font-weight: 820;
    line-height: 1.35;
}

.leader-main-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(260px, 315px);
    gap: 0.5rem;
    margin-top: 0.5rem;
    align-items: start;
    min-width: 0;
}

.leader-main,
.leader-side {
    display: grid;
    gap: 0.5rem;
    min-width: 0;
}

.leader-side {
    position: sticky;
    top: 0.65rem;
    align-items: start;
    align-content: start;
}

.date-picker-control {
    display: inline-flex;
    align-items: center;
    gap: 0.32rem;
    height: 1.95rem;
    padding: 0 0.5rem;
    border-radius: 0.6rem;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
}

.date-picker-control select {
    border: 0;
    outline: 0;
    background: transparent;
    color: #0f172a;
    font-size: 0.64rem;
    font-weight: 850;
}

.shift-board-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.4rem;
}

.shift-card {
    width: 100%;
    min-width: 0;
    padding: 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.7rem;
    background: #f8fafc;
    text-align: left;
    cursor: pointer;
}

.shift-card.is-warning {
    background: #fffbeb;
    border-color: #fde68a;
}

.shift-card.is-active {
    border-color: #6366f1;
    box-shadow: inset 0 0 0 1px rgba(99, 102, 241, 0.14);
}

.shift-card__head,
.shift-card__footer,
.selected-shift-detail__head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.42rem;
}

.shift-card strong,
.selected-shift-detail strong {
    display: block;
    color: #0f172a;
    font-size: 0.68rem;
    font-weight: 950;
}

.shift-card span,
.selected-shift-detail span {
    display: block;
    color: #64748b;
    font-size: 0.54rem;
    font-weight: 800;
}

.shift-card b {
    flex: 0 0 auto;
    display: grid;
    place-items: center;
    min-width: 1.65rem;
    height: 1.65rem;
    border-radius: 999px;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 0.66rem;
    font-weight: 950;
}

.shift-member-pills {
    display: flex;
    gap: 0.24rem;
    margin-top: 0.45rem;
    flex-wrap: wrap;
}

.shift-member-pills span,
.shift-member-pills small {
    width: 1.55rem;
    height: 1.55rem;
    display: grid;
    place-items: center;
    border-radius: 999px;
    background: #6366f1;
    color: #fff;
    font-size: 0.55rem;
    font-weight: 950;
}

.shift-member-pills small {
    background: #e2e8f0;
    color: #475569;
}

.shift-member-pills.is-summary-only {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.24rem;
}

.shift-member-pills.is-summary-only .shift-summary-pill {
    width: 100%;
    min-width: 0;
    height: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.35rem;
    padding: 0.24rem 0.42rem;
    border-radius: 0.5rem;
    background: #eef2ff;
    color: #3730a3;
}

.shift-member-pills.is-summary-only .shift-summary-pill b {
    min-width: auto;
    height: auto;
    border-radius: 0;
    background: transparent;
    color: inherit;
    font-size: 0.6rem;
    font-weight: 950;
    padding: 0;
}

.shift-member-pills.is-summary-only .shift-summary-pill em {
    font-style: normal;
    color: #475569;
    font-size: 0.53rem;
    font-weight: 800;
    text-align: right;
}

.shift-member-pills.is-summary-only .shift-summary-pill.is-gap {
    background: #fff7ed;
    color: #c2410c;
}

.shift-member-pills.is-summary-only .shift-summary-pill.is-gap em {
    color: #9a3412;
}

.shift-card__footer {
    margin-top: 0.45rem;
}

.shift-card__footer i {
    color: #16a34a;
}

.shift-card.is-warning .shift-card__footer i {
    color: #d97706;
}

.selected-shift-detail {
    margin-top: 0.45rem;
    padding: 0.5rem;
    border-radius: 0.7rem;
    border: 1px solid #e2e8f0;
    background: #fff;
}

.selected-shift-detail__head small {
    flex: 0 0 auto;
    padding: 0.18rem 0.36rem;
    border-radius: 999px;
    background: #dcfce7;
    color: #15803d;
    font-size: 0.52rem;
    font-weight: 950;
}

.selected-shift-detail__head small.is-warning {
    background: #fef3c7;
    color: #b45309;
}

.pagination-row {
    margin-top: 0.45rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.45rem;
}

.pagination-row span {
    font-size: 0.56rem;
    font-weight: 850;
    color: #64748b;
}

.pagination-btn {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #334155;
    border-radius: 0.48rem;
    font-size: 0.54rem;
    font-weight: 900;
    padding: 0.24rem 0.55rem;
    cursor: pointer;
}

.pagination-btn:disabled {
    cursor: not-allowed;
    opacity: 0.45;
}

.selected-shift-members {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.34rem;
    margin-top: 0.45rem;
}

.shift-person {
    min-width: 0;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    gap: 0.34rem;
    align-items: center;
    padding: 0.38rem;
    border-radius: 0.58rem;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
}

.shift-person > div:nth-child(2) {
    min-width: 0;
}

.shift-person strong,
.shift-person span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.shift-person small {
    flex: 0 0 auto;
    padding: 0.16rem 0.32rem;
    border-radius: 999px;
    font-size: 0.5rem;
    font-weight: 950;
    background: #f1f5f9;
    color: #475569;
}

.shift-person small.is-success {
    background: #dcfce7;
    color: #15803d;
}

.shift-person small.is-info {
    background: #e0f2fe;
    color: #0369a1;
}

.shift-person small.is-danger {
    background: #fee2e2;
    color: #dc2626;
}

.shift-person small.is-warning {
    background: #fef3c7;
    color: #b45309;
}

.coverage-alert {
    margin-top: 0.45rem;
    background: #fffbeb;
    color: #92400e;
}

.toolbar-card {
    display: flex;
    align-items: stretch;
    justify-content: flex-start;
    flex-direction: column;
    gap: 0.55rem;
}

.toolbar-main {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.55rem;
}

.toolbar-title {
    flex: 0 0 auto;
}

.toolbar-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    min-width: 0;
}

.toolbar-active-filters {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.2rem 0.46rem;
    border-radius: 999px;
    border: 1px solid #dbeafe;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 0.54rem;
    font-weight: 900;
}

.filter-reset-btn {
    height: 1.62rem;
    padding: 0 0.58rem;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    background: white;
    color: #475569;
    font-size: 0.56rem;
    font-weight: 900;
}

.search-box {
    width: min(100%, 220px);
    height: 1.95rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0 0.5rem;
    border-radius: 0.6rem;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
}

.search-box input {
    width: 100%;
    min-width: 0;
    border: none;
    outline: none;
    background: transparent;
    font-size: 0.68rem;
    color: #0f172a;
}

.filter-control {
    height: 1.95rem;
    min-width: 8.2rem;
    border-radius: 0.6rem;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #334155;
    padding: 0 0.42rem;
    font-size: 0.64rem;
    font-weight: 850;
    outline: none;
}

.member-list {
    display: grid;
    gap: 0.42rem;
}

.team-calendar-panel {
    position: relative;
    overflow: hidden;
    display: grid;
    gap: 0.62rem;
    padding: 0.9rem;
    border: 1px solid #dbe3ef;
    border-radius: 1rem;
    background:
        radial-gradient(circle at top right, rgba(59, 130, 246, 0.12), transparent 34%),
        linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
    isolation: isolate;
}

.team-calendar-panel::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.55), transparent 42%);
    z-index: 0;
    pointer-events: none;
}

.team-calendar-panel > * {
    position: relative;
    z-index: 1;
}

.team-calendar-panel__head {
    position: relative;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
    padding-bottom: 0.55rem;
    border-bottom: 1px solid rgba(148, 163, 184, 0.18);
}

.team-calendar-panel__head h2 {
    margin: 0.1rem 0 0;
    font-size: 0.86rem;
    font-weight: 950;
    color: #0f172a;
}

.team-calendar-panel__meta {
    position: relative;
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.45rem;
    border-radius: 999px;
    border: 1px solid #bfdbfe;
    background: rgba(239, 246, 255, 0.95);
    color: #1d4ed8;
    font-size: 0.54rem;
    font-weight: 900;
    white-space: nowrap;
}

.team-calendar-filter-row,
.team-calendar-summary-row,
.team-calendar-grid {
    position: relative;
}

.team-calendar-filter-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.team-calendar-filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    padding: 0.22rem 0.5rem;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    font-size: 0.56rem;
    font-weight: 900;
    cursor: pointer;
}

.team-calendar-filter-chip b {
    font-size: 0.52rem;
    color: #0f172a;
}

.team-calendar-filter-chip.is-active {
    border-color: #93c5fd;
    background: #dbeafe;
    color: #1d4ed8;
}

.team-calendar-summary-row {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.4rem;
}

.team-calendar-summary-pill {
    border-radius: 0.56rem;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    padding: 0.4rem 0.46rem;
    display: grid;
    gap: 0.06rem;
}

.team-calendar-summary-pill strong {
    font-size: 0.74rem;
    font-weight: 950;
    line-height: 1;
}

.team-calendar-summary-pill span {
    font-size: 0.52rem;
    font-weight: 850;
}

.team-calendar-summary-pill.is-success {
    background: #ecfdf5;
    border-color: #bbf7d0;
}

.team-calendar-summary-pill.is-success strong,
.team-calendar-summary-pill.is-success span {
    color: #15803d;
}

.team-calendar-summary-pill.is-warning {
    background: #fffbeb;
    border-color: #fde68a;
}

.team-calendar-summary-pill.is-warning strong,
.team-calendar-summary-pill.is-warning span {
    color: #b45309;
}

.team-calendar-summary-pill.is-danger {
    background: #fef2f2;
    border-color: #fecaca;
}

.team-calendar-summary-pill.is-danger strong,
.team-calendar-summary-pill.is-danger span {
    color: #b91c1c;
}

.team-calendar-summary-pill.is-info {
    background: #eff6ff;
    border-color: #bfdbfe;
}

.team-calendar-summary-pill.is-info strong,
.team-calendar-summary-pill.is-info span {
    color: #1d4ed8;
}

.team-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.42rem;
}

.team-calendar-day {
    min-height: 4.25rem;
    border: 1px solid #dbe3ef;
    border-radius: 0.78rem;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    padding: 0.48rem;
    display: grid;
    gap: 0.38rem;
    text-align: left;
    cursor: pointer;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
}

.team-calendar-day:hover {
    border-color: #93c5fd;
    background: #eff6ff;
    transform: translateY(-1px);
    box-shadow: 0 12px 22px rgba(15, 23, 42, 0.08);
}

.team-calendar-day.is-today {
    box-shadow:
        inset 0 0 0 1px #3b82f6,
        0 10px 22px rgba(59, 130, 246, 0.12);
}

.team-calendar-day.has-problem {
    border-color: #fca5a5;
}

.team-calendar-day.is-success {
    background: #f0fdf4;
}

.team-calendar-day.is-warning {
    background: #fffbeb;
}

.team-calendar-day.is-danger {
    background: #fef2f2;
}

.team-calendar-day.is-info {
    background: #eff6ff;
}

.team-calendar-day.is-default,
.team-calendar-day.is-secondary {
    background: #f8fafc;
}

.team-calendar-day__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.28rem;
}

.team-calendar-day__head > div {
    display: grid;
    gap: 0.03rem;
}

.team-calendar-day__head strong {
    font-size: 0.78rem;
    font-weight: 950;
    color: #0f172a;
    line-height: 1;
}

.team-calendar-day__head span {
    font-size: 0.49rem;
    font-weight: 850;
    color: #64748b;
}

.team-calendar-day__head small {
    font-size: 0.5rem;
    font-weight: 900;
    color: #334155;
}

.team-calendar-day__stats {
    display: grid;
    gap: 0.12rem;
}

.team-calendar-day__stats span {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.5rem;
    color: #64748b;
}

.team-calendar-day__stats b {
    color: #0f172a;
    font-weight: 950;
}

.member-card.is-open {
    border-color: rgba(79, 70, 229, 0.35);
}

.member-row {
    width: 100%;
    display: grid;
    grid-template-columns: minmax(230px, 0.95fr) minmax(0, 1.5fr) auto;
    gap: 0.5rem;
    align-items: center;
    padding: 0.52rem;
    border: none;
    background: transparent;
    text-align: left;
    cursor: pointer;
    min-width: 0;
}

.member-profile {
    display: flex;
    align-items: center;
    gap: 0.48rem;
    min-width: 0;
}

.member-avatar {
    flex: 0 0 auto;
    width: 2.05rem;
    height: 2.05rem;
    border-radius: 999px;
    display: grid;
    place-items: center;
    background: #6366f1;
    color: white;
    font-size: 0.68rem;
    font-weight: 950;
}

.member-avatar.is-sm {
    width: 1.6rem;
    height: 1.6rem;
    font-size: 0.56rem;
}

.member-name-wrap,
.member-name-line {
    min-width: 0;
}

.member-name-line {
    flex-direction: column !important;

    display: flex;
    align-items: start !important;
    justify-content: start !important;
    gap: 0.42rem;
    flex-wrap: wrap;
}

.member-name-line h3 {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 950;
    color: #0f172a;
}

.member-head-badges {
    display: inline-flex;
    align-items: center;
    gap: 0.26rem;
    flex-wrap: wrap;
}

.priority-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.18rem 0.46rem;
    border-radius: 999px;
    font-size: 0.54rem;
    font-weight: 900;
    border: 1px solid transparent;
}

.priority-pill.is-high {
    color: #b91c1c;
    background: #fee2e2;
    border-color: #fecaca;
}

.priority-pill.is-medium {
    color: #b45309;
    background: #fef3c7;
    border-color: #fde68a;
}

.priority-pill.is-low {
    color: #15803d;
    background: #dcfce7;
    border-color: #bbf7d0;
}

.member-name-wrap p {
    margin: 0.12rem 0 0;
    color: #64748b;
    font-size: 0.6rem;
    font-weight: 750;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.shift-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    max-width: 100%;
    padding: 0.18rem 0.42rem;
    border-radius: 999px;
    background: #6366f1;
    color: white;
    font-size: 0.56rem;
    font-weight: 900;
}

.shift-pill small {
    font-size: 0.54rem;
    font-weight: 800;
    opacity: 0.92;
}

.member-metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(92px, 1fr));
    gap: 0.26rem;
    min-width: 0;
}

.mini-metric {
    min-width: 0;
    min-height: 2.25rem;
    padding: 0.3rem 0.24rem;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 0.55rem;
    text-align: center;
    display: grid;
    align-content: center;
}

.mini-metric strong {
    display: block;
    font-size: 0.62rem;
    font-weight: 950;
    color: #0f172a;
    line-height: 1.1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mini-metric span {
    display: block;
    margin-top: 0.1rem;
    font-size: 0.46rem;
    font-weight: 800;
    color: #64748b;
    line-height: 1.1;
}

.mini-metric.is-success {
    background: #dcfce7;
    border-color: #bbf7d0;
}

.mini-metric.is-success strong {
    color: #15803d;
}

.mini-metric.is-danger {
    background: #fee2e2;
    border-color: #fecaca;
}

.mini-metric.is-danger strong {
    color: #dc2626;
}

.mini-metric.is-warning {
    background: #fef3c7;
    border-color: #fde68a;
}

.mini-metric.is-warning strong {
    color: #b45309;
}

.mini-metric.is-info {
    background: #e0f2fe;
    border-color: #bae6fd;
}

.mini-metric.is-info strong {
    color: #0369a1;
}

.mini-metric.is-purple {
    background: #ede9fe;
    border-color: #ddd6fe;
}

.mini-metric.is-purple strong {
    color: #7c3aed;
}

.mini-metric.is-orange {
    background: #ffedd5;
    border-color: #fed7aa;
}

.mini-metric.is-orange strong {
    color: #ea580c;
}

.member-row-action {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.38rem;
}

.row-action-label {
    display: inline-flex;
    align-items: center;
    padding: 0.18rem 0.45rem;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    font-size: 0.54rem;
    font-weight: 850;
}

.issue-count {
    min-width: 2.55rem;
    padding: 0.26rem 0.34rem;
    border-radius: 0.58rem;
    text-align: center;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
}

.issue-count.has-issue {
    background: #fff7ed;
    border-color: #fed7aa;
}

.issue-count strong {
    display: block;
    font-size: 0.68rem;
    font-weight: 950;
    color: #0f172a;
    line-height: 1;
}

.issue-count.has-issue strong {
    color: #ea580c;
}

.issue-count span {
    display: block;
    margin-top: 0.06rem;
    font-size: 0.46rem;
    font-weight: 850;
    color: #64748b;
}

.member-chevron {
    color: #64748b;
    transition: transform 0.18s ease;
}

.member-chevron.is-open {
    transform: rotate(180deg);
}

.member-detail {
    padding: 0.52rem;
    border-top: 1px solid #e2e8f0;
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.85), rgba(255, 255, 255, 0.92));
}

.detail-top {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.5rem;
    border: 1px dashed #cbd5e1;
    border-radius: 0.68rem;
    background: #f8fafc;
    margin-bottom: 0.5rem;
}

.detail-top-copy {
    min-width: 0;
}

.detail-top strong {
    display: block;
    font-size: 0.68rem;
    font-weight: 950;
    color: #0f172a;
}

.detail-top span {
    display: block;
    margin-top: 0.08rem;
    font-size: 0.58rem;
    font-weight: 750;
    color: #64748b;
}

.detail-headline {
    margin-top: 0.18rem;
}

.priority-summary-grid {
    margin-top: 0.42rem;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.34rem;
}

.priority-summary-item {
    min-width: 0;
    border: 1px solid #e2e8f0;
    border-radius: 0.62rem;
    background: white;
    padding: 0.38rem 0.42rem;
}

.priority-summary-item small {
    display: block;
    font-size: 0.52rem;
    font-weight: 800;
    color: #64748b;
}

.priority-summary-item b {
    display: block;
    margin-top: 0.14rem;
    font-size: 0.58rem;
    font-weight: 900;
    color: #0f172a;
    line-height: 1.25;
}

.detail-tags {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.28rem;
}

.tag {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.4rem;
    border-radius: 999px;
    font-size: 0.52rem;
    font-weight: 950;
}

.tag.is-danger {
    background: #fee2e2;
    color: #dc2626;
}

.tag.is-warning {
    background: #fef3c7;
    color: #b45309;
}

.tag.is-info {
    background: #e0f2fe;
    color: #0369a1;
}

.tag.is-success {
    background: #dcfce7;
    color: #15803d;
}

.tag.is-priority {
    border: 1px solid transparent;
}

.tag.is-priority.is-high {
    background: #fee2e2;
    border-color: #fecaca;
    color: #b91c1c;
}

.tag.is-priority.is-medium {
    background: #fef3c7;
    border-color: #fde68a;
    color: #92400e;
}

.tag.is-priority.is-low {
    background: #dcfce7;
    border-color: #bbf7d0;
    color: #15803d;
}

.priority-intel-grid {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 0.45rem;
    margin-bottom: 0.5rem;
}

.priority-intel-card {
    border: 1px solid #e2e8f0;
    border-radius: 0.72rem;
    background: white;
    padding: 0.48rem;
}

.priority-intel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.35rem;
    margin-bottom: 0.4rem;
}

.priority-intel-head strong {
    font-size: 0.64rem;
    font-weight: 950;
    color: #0f172a;
}

.priority-intel-head span {
    font-size: 0.52rem;
    font-weight: 800;
    color: #64748b;
}

.priority-reason-list,
.priority-action-list {
    display: flex;
    flex-direction: column;
    gap: 0.34rem;
}

.priority-reason-item {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.45rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.62rem;
    background: #f8fafc;
    padding: 0.35rem 0.42rem;
}

.priority-reason-item > div:first-child {
    min-width: 0;
}

.priority-reason-item strong {
    display: block;
    font-size: 0.6rem;
    font-weight: 900;
    color: #0f172a;
}

.priority-reason-item span {
    display: block;
    margin-top: 0.06rem;
    font-size: 0.54rem;
    font-weight: 760;
    color: #64748b;
}

.priority-reason-side {
    flex: 0 0 auto;
    text-align: right;
}

.priority-reason-side b {
    display: block;
    font-size: 0.58rem;
    font-weight: 900;
    color: #1d4ed8;
}

.priority-reason-side small {
    display: block;
    margin-top: 0.08rem;
    font-size: 0.52rem;
    font-weight: 900;
    color: #b91c1c;
}

.priority-action-item {
    width: 100%;
    border: 1px solid #e2e8f0;
    border-radius: 0.62rem;
    background: #f8fafc;
    text-align: left;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.45rem;
    padding: 0.4rem 0.45rem;
}

.priority-action-item:hover {
    border-color: #cbd5e1;
    background: #ffffff;
}

.priority-action-item > div:first-child {
    min-width: 0;
}

.priority-action-item strong {
    display: block;
    font-size: 0.6rem;
    font-weight: 900;
    color: #0f172a;
}

.priority-action-item span {
    display: block;
    margin-top: 0.08rem;
    font-size: 0.54rem;
    font-weight: 760;
    color: #64748b;
}

.priority-action-side {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    gap: 0.26rem;
    color: #1d4ed8;
}

.priority-action-side small {
    font-size: 0.52rem;
    font-weight: 900;
    color: #1d4ed8;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.45rem;
}

.detail-loading {
    border: 1px dashed #cbd5e1;
    border-radius: 0.8rem;
    background: #f8fafc;
    padding: 0.7rem;
}

.detail-loading__head {
    display: inline-flex;
    align-items: center;
    gap: 0.32rem;
    color: #475569;
    font-size: 0.62rem;
    font-weight: 850;
}

.detail-loading__grid {
    margin-top: 0.6rem;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.42rem;
}

.detail-loading__card {
    height: 5.8rem;
    border-radius: 0.72rem;
    background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
    background-size: 220% 100%;
    animation: detailShimmer 1.2s ease-in-out infinite;
}

@keyframes detailShimmer {
    0% {
        background-position: 220% 0;
    }
    100% {
        background-position: -220% 0;
    }
}

.detail-panel {
    min-width: 0;
    padding: 0.52rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.72rem;
    background: white;
}

.detail-panel--wide {
    grid-column: 1 / -1;
}

.detail-panel--kpi {
    background: linear-gradient(180deg, #ffffff, #fbfaff);
}

.detail-panel--lms {
    background: linear-gradient(180deg, #ffffff, #f8fffe);
}

.detail-panel--approval {
    background: linear-gradient(180deg, #ffffff, #fffcf6);
}

.detail-panel__head,
.panel-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    min-width: 0;
}

.panel-title {
    justify-content: flex-start;
}

.detail-panel__head {
    margin-bottom: 0.45rem;
}

.panel-title i {
    width: 1.6rem;
    height: 1.6rem;
    flex: 0 0 auto;
    display: grid;
    place-items: center;
    border-radius: 0.54rem;
    background: #eef2ff;
    color: #4f46e5;
}

.panel-title strong {
    font-size: 0.72rem;
    font-weight: 950;
    line-height: 1.2;
    color: #0f172a;
}

.panel-count {
    flex: 0 0 auto;
    padding: 0.22rem 0.44rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.58rem;
    font-weight: 900;
    line-height: 1;
}

.hcis-detail-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(240px, 0.6fr);
    gap: 0.45rem;
}

.hcis-priority-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.3rem;
    margin-bottom: 0.35rem;
}

.hcis-priority-item {
    min-width: 0;
    border: 1px solid #e2e8f0;
    border-radius: 0.58rem;
    background: #ffffff;
    padding: 0.35rem 0.42rem;
}

.hcis-priority-item span {
    display: block;
    font-size: 0.5rem;
    font-weight: 820;
    color: #64748b;
}

.hcis-priority-item strong {
    display: block;
    margin-top: 0.08rem;
    font-size: 0.62rem;
    font-weight: 950;
    color: #0f172a;
}

.hcis-priority-item.is-attention {
    background: #fff7ed;
    border-color: #fed7aa;
}

.hcis-priority-item.is-attention strong {
    color: #c2410c;
}

.metric-grid,
.kpi-period-box {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.3rem;
}

.metric-grid--hcis {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.metric-grid.is-three {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.metric-grid > div.is-done {
    background: #dcfce7;
    border-color: #bbf7d0;
}

.metric-grid > div.is-pending {
    background: #fef3c7;
    border-color: #fde68a;
}

.metric-grid > div.is-danger {
    background: #fee2e2;
    border-color: #fecaca;
}

.metric-grid > div.is-danger strong {
    color: #b91c1c;
}

.metric-grid > div.is-warning {
    background: #fef3c7;
    border-color: #fde68a;
}

.metric-grid > div.is-warning strong {
    color: #92400e;
}

.metric-grid > div.is-info {
    background: #e0f2fe;
    border-color: #bae6fd;
}

.metric-grid > div.is-info strong {
    color: #0369a1;
}

.attendance-trend-card {
    min-width: 0;
    padding: 0.45rem;
    border-radius: 0.65rem;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
}

.subsection-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.35rem;
}

.attendance-trend-card .subsection-head {
    margin-top: 0;
}

.subsection-head strong {
    font-size: 0.62rem;
    font-weight: 950;
    color: #0f172a;
}

.subsection-head span {
    font-size: 0.52rem;
    font-weight: 750;
    color: #64748b;
}

.daily-status-row {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.22rem;
}
.trend-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    margin-top: 0.35rem;
}

.trend-legend span {
    display: inline-flex;
    align-items: center;
    padding: 0.16rem 0.35rem;
    border-radius: 999px;
    font-size: 0.48rem;
    font-weight: 880;
}

.trend-legend .is-success {
    background: #dcfce7;
    color: #15803d;
}

.trend-legend .is-warning {
    background: #fef3c7;
    color: #b45309;
}

.trend-legend .is-danger {
    background: #fee2e2;
    color: #b91c1c;
}

.daily-status-cell {
    display: grid;
    place-items: center;
    min-height: 1.8rem;
    border-radius: 0.45rem;
    background: #e2e8f0;
}

.daily-status-cell span {
    font-size: 0.48rem;
    font-weight: 950;
}

.daily-status-cell.is-success {
    background: #dcfce7;
    color: #15803d;
}

.daily-status-cell.is-warning {
    background: #fef3c7;
    color: #b45309;
}

.daily-status-cell.is-danger {
    background: #fee2e2;
    color: #dc2626;
}

.daily-status-cell.is-info {
    background: #e0f2fe;
    color: #0369a1;
}

.daily-status-cell.is-orange {
    background: #ffedd5;
    color: #ea580c;
}

.mini-overtime-chart {
    display: flex;
    align-items: end;
    gap: 0.25rem;
    height: 3.2rem;
    margin-top: 0.45rem;
    padding: 0.3rem;
    border-radius: 0.55rem;
    background: #fff;
    border: 1px solid #e2e8f0;
}

.mini-overtime-chart div {
    flex: 1;
    border-radius: 999px 999px 0.2rem 0.2rem;
    background: linear-gradient(180deg, #fb923c, #f97316);
}

.attendance-trend-card small {
    display: block;
    margin-top: 0.3rem;
    color: #64748b;
    font-size: 0.5rem;
    font-weight: 750;
}

.deadline-strip,
.module-alert {
    display: flex;
    align-items: flex-start;
    gap: 0.32rem;
    margin-top: 0.38rem;
    padding: 0.42rem;
    border-radius: 0.56rem;
    font-size: 0.58rem;
    font-weight: 850;
}

.module-alert strong {
    display: block;
    font-size: 0.6rem;
    font-weight: 950;
}

.module-alert span {
    display: block;
    margin-top: 0.08rem;
    font-size: 0.54rem;
    font-weight: 750;
    line-height: 1.3;
}

.deadline-strip.is-warning,
.module-alert.is-warning {
    background: #fef3c7;
    color: #92400e;
}

.deadline-strip.is-success,
.module-alert.is-success {
    background: #dcfce7;
    color: #166534;
}

.deadline-strip.is-info,
.module-alert.is-info {
    background: #e0f2fe;
    color: #0369a1;
}

.alert-list {
    display: grid;
    gap: 0.3rem;
}

.achievement-chart {
    display: flex;
    align-items: end;
    gap: 0.3rem;
    height: 7rem;
    padding: 0.45rem;
    border-radius: 0.65rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.achievement-chart > div {
    flex: 1;
    display: grid;
    grid-template-rows: auto 1fr auto;
    align-items: end;
    gap: 0.25rem;
    height: 100%;
    min-width: 0;
    text-align: center;
}

.achievement-chart span,
.achievement-chart strong {
    color: #64748b;
    font-size: 0.5rem;
    font-weight: 850;
}

.achievement-chart div div {
    height: 100%;
    display: flex;
    align-items: end;
    justify-content: center;
}

.achievement-chart b {
    display: block;
    width: 100%;
    max-width: 1.1rem;
    min-height: 0.35rem;
    border-radius: 999px 999px 0.18rem 0.18rem;
    background: linear-gradient(180deg, #8b5cf6, #6366f1);
}

.issue-list,
.parameter-list,
.training-list,
.approval-list,
.approval-side-list,
.training-pending-list,
.action-list {
    display: grid;
    gap: 0.32rem;
    margin-top: 0.42rem;
}

.issue-item {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
    padding: 0.48rem 0.54rem;
    border-radius: 0.56rem;
    border: 1px solid transparent;
}

.issue-item > div {
    min-width: 0;
}

.issue-item i {
    flex: 0 0 auto;
    width: 1.15rem;
    height: 1.15rem;
    margin-top: 0;
    border-radius: 0.38rem;
    display: grid;
    place-items: center;
    font-size: 0.66rem;
    line-height: 1;
}

.issue-item strong {
    display: block;
    font-size: 0.66rem;
    font-weight: 950;
    color: #0f172a;
    line-height: 1.25;
}

.issue-item span {
    display: block;
    margin-top: 0.05rem;
    font-size: 0.56rem;
    line-height: 1.3;
    color: #64748b;
}

.issue-item.is-danger {
    background: #fee2e2;
    border-color: #fecaca;
}

.issue-item.is-danger i {
    color: #dc2626;
}

.issue-item.is-warning {
    background: #fef3c7;
    border-color: #fde68a;
}

.issue-item.is-warning i {
    color: #d97706;
}

.issue-item.is-info,
.issue-item.is-orange {
    background: #e0f2fe;
    border-color: #bae6fd;
}

.issue-item.is-info i,
.issue-item.is-orange i {
    color: #0284c7;
}

.progress-line {
    width: 100%;
    height: 0.38rem;
    margin-top: 0.22rem;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}

.progress-line.is-lg {
    height: 0.46rem;
}

.progress-line__fill {
    height: 100%;
    border-radius: inherit;
}

.progress-line__fill.is-purple {
    background: linear-gradient(90deg, #8b5cf6, #6366f1);
}

.progress-line__fill.is-teal {
    background: linear-gradient(90deg, #14b8a6, #06b6d4);
}

.parameter-row > div:first-child {
    display: flex;
    justify-content: space-between;
    gap: 0.4rem;
}

.parameter-row span {
    font-size: 0.56rem;
    font-weight: 850;
    color: #475569;
}

.parameter-row strong {
    font-size: 0.56rem;
    font-weight: 950;
    color: #0f172a;
}

.training-row,
.approval-item,
.approval-side-item,
.training-pending-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.42rem;
    padding: 0.4rem;
    border-radius: 0.56rem;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
}

.training-row > div,
.approval-item > div,
.approval-side-item > div,
.training-pending-item > div {
    min-width: 0;
}

.training-row strong,
.approval-item strong,
.approval-side-item strong,
.training-pending-item strong {
    display: block;
    font-size: 0.6rem;
    font-weight: 950;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.training-row span,
.approval-item span,
.approval-side-item span,
.training-pending-item span {
    display: block;
    margin-top: 0.06rem;
    font-size: 0.52rem;
    font-weight: 750;
    color: #64748b;
}

.training-status,
.approval-item small,
.approval-side-item small,
.training-pending-item small {
    flex: 0 0 auto;
    padding: 0.18rem 0.36rem;
    border-radius: 999px;
    font-size: 0.52rem !important;
    font-weight: 950 !important;
}

.training-status.is-complete {
    background: #dcfce7;
    color: #15803d;
}

.training-status.is-progress {
    background: #fef3c7;
    color: #b45309;
}

.training-status.is-pending {
    background: #fee2e2;
    color: #dc2626;
}

.approval-item {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
}

.approval-item i {
    color: #16a34a;
}

.approval-item small,
.approval-side-item small {
    background: #dcfce7;
    color: #15803d;
}

.approval-item small.is-expired,
.approval-side-item small.is-expired {
    background: #fee2e2;
    color: #b91c1c;
}

.approval-item small.is-pending,
.approval-side-item small.is-pending {
    background: #fef3c7;
    color: #b45309;
}

.approval-item small.is-future,
.approval-side-item small.is-future {
    background: #dbeafe;
    color: #1d4ed8;
}

.approval-item small.is-approved,
.approval-side-item small.is-approved {
    background: #dcfce7;
    color: #15803d;
}

.approval-side-item__meta {
    margin-top: 0.08rem;
    font-size: 0.5rem;
    font-weight: 800;
    color: #334155;
}

.approval-side-item__reason {
    margin-top: 0.08rem;
    font-size: 0.5rem;
    line-height: 1.35;
    color: #64748b;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.action-list.is-scrollable {
    max-height: 19rem;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 0.1rem;
}

.action-card {
    width: 100%;
    min-width: 0;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.38rem;
    padding: 0.42rem;
    border-radius: 0.62rem;
    border: 1px solid #e2e8f0;
    background: #fff;
    cursor: pointer;
    text-align: left;
}

.action-card__icon {
    width: 1.7rem;
    height: 1.7rem;
    display: grid;
    place-items: center;
    border-radius: 0.56rem;
    background: #f1f5f9;
    color: #475569;
}

.action-card__body {
    min-width: 0;
}

.action-card__body strong {
    display: block;
    font-size: 0.58rem;
    font-weight: 950;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.action-card__body span {
    display: block;
    margin-top: 0.06rem;
    font-size: 0.52rem;
    color: #64748b;
    line-height: 1.25;
}

.action-card__body small {
    display: block;
    margin-top: 0.08rem;
    font-size: 0.5rem;
    font-weight: 900;
    color: #475569;
}

.action-case-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.22rem;
    margin-top: 0.12rem;
}

.action-case-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.05rem 0.32rem;
    font-size: 0.48rem;
    font-weight: 850;
    border: 1px solid transparent;
    line-height: 1.2;
}

.action-case-chip.is-danger {
    background: #fee2e2;
    border-color: #fecaca;
    color: #991b1b;
}

.action-case-chip.is-warning {
    background: #fef3c7;
    border-color: #fcd34d;
    color: #92400e;
}

.action-case-chip.is-orange {
    background: #ffedd5;
    border-color: #fdba74;
    color: #9a3412;
}

.action-case-chip.is-info {
    background: #e0f2fe;
    border-color: #7dd3fc;
    color: #075985;
}

.action-case-dates {
    margin-top: 0.08rem;
    font-size: 0.48rem;
    color: #475569;
    font-weight: 750;
}

.action-card.is-danger {
    background: #fff1f2;
    border-color: #fecaca;
}

.action-card.is-warning {
    background: #fffbeb;
    border-color: #fde68a;
}

.action-card.is-info {
    background: #f0f9ff;
    border-color: #bae6fd;
}

.action-card.is-success,
.action-card.is-orange {
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.heatmap-board {
    display: grid;
    gap: 0.34rem;
}

.heatmap-row {
    display: grid;
    grid-template-columns: 4.2rem minmax(0, 1fr);
    align-items: center;
    gap: 0.4rem;
}

.heatmap-row strong {
    font-size: 0.56rem;
    font-weight: 950;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.heatmap-legend {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.25rem;
    margin-top: 0.45rem;
}

.heatmap-controls {
    display: grid;
    gap: 0.38rem;
    margin-top: 0.45rem;
    padding-top: 0.55rem;
    border-top: 1px solid rgba(148, 163, 184, 0.18);
}

.heatmap-controls--top {
    margin-bottom: 0.15rem;
}

.heatmap-controls--bottom {
    margin-top: 0.55rem;
}

.heatmap-controls__top {
    display: grid;
    margin-bottom: 0.15rem;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: center;
    gap: 0.5rem;
}

.heatmap-controls__bottom {
    display: flex;
    justify-content: flex-end;
}

.heatmap-controls__group {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.heatmap-controls__group--page {
    justify-content: space-between;
    width: 100%;
}

.heatmap-controls__range {
    justify-self: center;
    padding: 0.34rem 0.7rem;
    border-radius: 999px;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.08), rgba(14, 165, 233, 0.08));
    border: 1px solid rgba(147, 197, 253, 0.6);
    color: #1d4ed8;
    font-size: 0.62rem;
    font-weight: 900;
    letter-spacing: 0.02em;
    white-space: nowrap;
}

.heatmap-controls__page-label {
    min-width: 2.8rem;
    text-align: center;
    color: #64748b;
    font-size: 0.56rem;
    font-weight: 850;
}

@media (max-width: 640px) {
    .heatmap-controls {
        gap: 0.45rem;
    }

    .heatmap-controls__top,
    .heatmap-controls__bottom {
        grid-template-columns: 1fr;
        justify-items: stretch;
    }

    .heatmap-controls__group,
    .heatmap-controls__group--page {
        justify-content: center;
    }

    .heatmap-controls__bottom {
        justify-content: stretch;
    }

    .heatmap-controls__range {
        justify-self: stretch;
        text-align: center;
        white-space: normal;
    }
}

.heatmap-legend span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: #64748b;
    font-size: 0.5rem;
    font-weight: 850;
}

.heatmap-legend i {
    width: 0.55rem;
    height: 0.55rem;
    border-radius: 0.18rem;
}

.heatmap-legend i.is-success {
    background: #86efac;
}

.heatmap-legend i.is-warning {
    background: #fde68a;
}

.heatmap-legend i.is-danger {
    background: #fca5a5;
}

.heatmap-legend i.is-info {
    background: #bae6fd;
}

.empty-state {
    width: 100%;
    display: grid;
    place-items: center;
    gap: 0.16rem;
    min-height: 7rem;
    padding: 0.75rem;
    border: 1px dashed #cbd5e1;
    border-radius: 0.68rem;
    background: #f8fafc;
    text-align: center;
    color: #64748b;
}

.empty-state.is-compact {
    min-height: 3.1rem;
    padding: 0.48rem;
}

.empty-state.is-light {
    border-color: rgba(255, 255, 255, 0.24);
    background: rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.8);
}

.empty-state i {
    font-size: 1.2rem;
    color: #94a3b8;
}

.empty-state.is-light i,
.empty-state.is-light strong,
.empty-state.is-light span {
    color: rgba(255, 255, 255, 0.82);
}

.empty-state.is-compact i {
    font-size: 0.9rem;
}

.empty-state strong {
    display: block;
    color: #0f172a;
    font-size: 0.66rem;
    font-weight: 950;
}

.empty-state span {
    display: block;
    max-width: 20rem;
    color: #64748b;
    font-size: 0.56rem;
    font-weight: 750;
    line-height: 1.3;
}

.accordion-enter-active,
.accordion-leave-active {
    transition:
        opacity 0.16s ease,
        transform 0.16s ease;
}

.accordion-enter-from,
.accordion-leave-to {
    opacity: 0;
    transform: translateY(-0.25rem);
}

/* =========================================================
   LEADER DASHBOARD REFINEMENT
   - HCIS tanpa heatmap, diganti statistik + line chart
   - KPI menjadi ringkasan team + line chart
   - Quick stats lebih actionable untuk leader
   - Accordion member dibuat lebih compact dan mudah discan
========================================================= */

.quick-stat-grid.is-leader-focused {
    grid-template-columns: repeat(5, minmax(0, 1fr));
}

.quick-stat-card {
    position: relative;
    overflow: hidden;
    isolation: isolate;
}

.quick-stat-card::after {
    content: '';
    position: absolute;
    inset: auto -1.5rem -1.8rem auto;
    width: 4.4rem;
    height: 4.4rem;
    border-radius: 999px;
    background: currentColor;
    opacity: 0.06;
    pointer-events: none;
    z-index: -1;
}

.quick-stat-card.is-priority {
    border-color: rgba(239, 68, 68, 0.22);
    box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
}

.quick-stat-card__arrow {
    margin-left: auto;
    color: #94a3b8;
    font-size: 1.05rem;
    transition:
        transform 0.18s ease,
        color 0.18s ease;
}

.quick-stat-card:hover .quick-stat-card__arrow {
    transform: translateX(0.16rem);
    color: #334155;
}

.quick-stat-card.is-primary .quick-stat-card__icon {
    background: #e0e7ff;
    color: #4f46e5;
}

.team-insight-grid.is-balanced {
    grid-template-columns: 1.08fr 1.08fr 1fr;
    align-items: stretch;
}

.hcis-insight-card,
.kpi-summary-card {
    min-height: 20rem;
}

.leader-summary-split {
    display: grid;
    grid-template-columns: minmax(120px, 0.72fr) minmax(0, 1.28fr);
    gap: 0.5rem;
    min-width: 0;
}

.kpi-year-tools {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

.kpi-year-label {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.62rem;
    font-weight: 800;
    color: #64748b;
}

.kpi-year-select {
    min-height: 1.75rem;
    border-radius: 0.5rem;
    border: 1px solid #dbe3ef;
    background: #ffffff;
    color: #0f172a;
    font-size: 0.72rem;
    font-weight: 800;
    padding: 0 0.5rem;
    outline: none;
}

.leader-score-card {
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 6.6rem;
    padding: 0.72rem;
    border-radius: 0.86rem;
    border: 1px solid #e2e8f0;
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.95), transparent 4rem),
        linear-gradient(135deg, #f8fafc, #ffffff);
}

.leader-score-card span,
.leader-score-card small {
    display: block;
    font-size: 0.56rem;
    font-weight: 900;
    color: #64748b;
}

.leader-score-card strong {
    display: block;
    margin: 0.18rem 0;
    font-size: 1.62rem;
    line-height: 1;
    font-weight: 950;
    letter-spacing: -0.05em;
    color: #0f172a;
}

.leader-score-card.is-hcis {
    background:
        radial-gradient(circle at top right, rgba(14, 165, 233, 0.15), transparent 5rem),
        linear-gradient(135deg, #f8fafc, #ffffff);
}

.leader-score-card.is-kpi {
    background:
        radial-gradient(circle at top right, rgba(124, 58, 237, 0.15), transparent 5rem),
        linear-gradient(135deg, #f8fafc, #ffffff);
}

.leader-score-card.is-lms {
    background:
        radial-gradient(circle at top right, rgba(20, 184, 166, 0.12), transparent 5rem),
        linear-gradient(135deg, #f8fbfb, #ffffff);
    border: 1px solid rgba(20, 184, 166, 0.12);
}

.leader-score-card.is-lms {
    min-height: 6.6rem;
    padding: 0.72rem;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.leader-mini-stat-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.38rem;
    min-width: 0;
}

.leader-mini-stat-grid > div {
    min-width: 0;
    padding: 0.48rem;
    border-radius: 0.7rem;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
}

.leader-mini-stat-grid span,
.leader-mini-stat-grid small {
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 0.5rem;
    font-weight: 850;
    color: #64748b;
}

.leader-mini-stat-grid strong {
    display: block;
    margin: 0.08rem 0;
    font-size: 0.95rem;
    font-weight: 950;
    color: #0f172a;
}

.leader-mini-stat-grid .is-danger {
    background: #fef2f2;
    border-color: #fecaca;
}

.leader-mini-stat-grid .is-warning {
    background: #fffbeb;
    border-color: #fde68a;
}

.leader-mini-stat-grid .is-orange {
    background: #fff7ed;
    border-color: #fed7aa;
}

.leader-mini-stat-grid .is-success {
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.leader-mini-stat-grid .is-info {
    background: #eff6ff;
    border-color: #bfdbfe;
}

.leader-mini-stat-grid .is-purple {
    background: #f5f3ff;
    border-color: #ddd6fe;
}

.line-chart-card {
    min-width: 0;
    margin-top: 0.55rem;
    padding: 0.58rem;
    border-radius: 0.86rem;
    border: 1px solid #e2e8f0;
    background: linear-gradient(180deg, #ffffff, #f8fafc);
}

.line-chart-card__head {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.42rem;
}

.line-chart-card__head strong,
.line-chart-card__head span,
.line-chart-card__head small {
    display: block;
}

.line-chart-card__head strong {
    font-size: 0.66rem;
    font-weight: 950;
    color: #0f172a;
}

.line-chart-card__head span,
.line-chart-card__head small {
    font-size: 0.52rem;
    font-weight: 850;
    color: #64748b;
}

.leader-apex-chart {
    position: relative;
    margin-top: 0.1rem;
    border-radius: 0.72rem;
    background:
        linear-gradient(180deg, rgba(248, 250, 252, 0.8), rgba(241, 245, 249, 0.86)),
        repeating-linear-gradient(90deg, transparent 0 16%, rgba(148, 163, 184, 0.1) 16% 16.2%);
}

.leader-apex-chart.is-teal {
    margin-top: 0.45rem;
}

.leader-apex-chart.is-danger {
    cursor: pointer;
}

.leader-apex-chart.is-disabled {
    cursor: not-allowed;
}

.leader-apex-chart.is-disabled :deep(.apexcharts-canvas) {
    pointer-events: none;
}

.chart-interaction-overlay {
    position: absolute;
    inset: 0;
    border-radius: 0.72rem;
    background: rgba(255, 255, 255, 0.62);
    display: grid;
    place-items: center;
    font-size: 0.72rem;
    font-weight: 800;
    color: #64748b;
    z-index: 3;
}

.leader-apex-chart :deep(.apexcharts-canvas),
.leader-apex-chart :deep(.apexcharts-svg) {
    width: 100% !important;
}

.leader-apex-chart :deep(.apexcharts-tooltip) {
    border: 1px solid #e2e8f0;
    box-shadow: 0 8px 16px rgba(15, 23, 42, 0.12);
}

.leader-score-card.is-clickable,
.leader-mini-stat-grid > .is-clickable-stat {
    cursor: pointer;
}

.leader-score-card.is-clickable:hover,
.leader-mini-stat-grid > .is-clickable-stat:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.08);
}

.attendance-status-filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    padding: 0.72rem 1rem 0.24rem;
    border-bottom: 1px solid #e2e8f0;
    background: #ffffff;
}

.status-filter-chip {
    appearance: none;
    -webkit-appearance: none;
    flex: 0 0 auto;
    min-height: 2rem;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    background: #f8fafc;
    color: #334155;
    padding: 0.28rem 0.62rem;
    font-size: 0.68rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.status-filter-chip b {
    font-size: 0.64rem;
    color: #64748b;
}

.status-filter-chip.is-active {
    background: #eef2ff;
    border-color: #c7d2fe;
    color: #3730a3;
}

.attendance-status-drawer-main {
    min-height: 0;
    display: grid;
    grid-template-rows: auto auto minmax(0, 1fr);
}

.attendance-status-toolbar {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.72rem 1rem 0.12rem;
    border-bottom: 1px solid #eef2f7;
    background: #fff;
}

.attendance-status-search {
    flex: 1;
    min-width: 0;
    border: 1px solid #dbe3ef;
    border-radius: 0.65rem;
    background: #f8fafc;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.46rem 0.62rem;
}

.attendance-status-search i {
    color: #94a3b8;
    font-size: 0.82rem;
}

.attendance-status-search input {
    border: none;
    outline: none;
    background: transparent;
    color: #0f172a;
    width: 100%;
    font-size: 0.78rem;
    font-weight: 600;
}

.attendance-status-search input::placeholder {
    color: #94a3b8;
    font-weight: 500;
}

.attendance-status-clear-btn {
    appearance: none;
    -webkit-appearance: none;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #334155;
    border-radius: 0.56rem;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.44rem 0.62rem;
    white-space: nowrap;
}

.attendance-status-clear-btn:hover {
    background: #f8fafc;
}

.attendance-issue-card.is-skeleton .skeleton-line {
    height: 0.62rem;
    border-radius: 999px;
    background: linear-gradient(90deg, #eef2f7, #f8fafc, #eef2f7);
    background-size: 200% 100%;
    animation: skeletonPulse 1.2s linear infinite;
}

.attendance-issue-card.is-skeleton .skeleton-line.is-title {
    height: 0.8rem;
    width: 65%;
}

.attendance-issue-card.is-skeleton .skeleton-line.is-short {
    width: 45%;
}

@keyframes skeletonPulse {
    0% {
        background-position: 200% 0;
    }

    100% {
        background-position: -200% 0;
    }
}

.lms-monthly-chart :deep(.apexcharts-bar-area) {
    filter: drop-shadow(0 4px 8px rgba(20, 184, 166, 0.24));
}

.insight-note.is-warning {
    background: #fff7ed;
    color: #9a3412;
}

.mini-badge.is-danger {
    background: #fee2e2;
    color: #dc2626;
}

.kpi-watch-list {
    display: grid;
    gap: 0.32rem;
    margin-top: 0.55rem;
}

.kpi-watch-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.55rem;
    min-width: 0;
    padding: 0.42rem 0.5rem;
    border-radius: 0.68rem;
    border: 1px solid #e2e8f0;
    background: #ffffff;
}

.kpi-watch-item.is-alert {
    background: #fffbeb;
    border-color: #fde68a;
}

.kpi-watch-item div {
    min-width: 0;
}

.kpi-watch-item strong,
.kpi-watch-item span {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.kpi-watch-item strong {
    font-size: 0.62rem;
    font-weight: 950;
    color: #0f172a;
}

.kpi-watch-item span {
    margin-top: 0.08rem;
    font-size: 0.5rem;
    font-weight: 800;
    color: #64748b;
}

.kpi-watch-item b {
    flex: 0 0 auto;
    width: 2rem;
    height: 2rem;
    display: grid;
    place-items: center;
    border-radius: 0.62rem;
    background: #f5f3ff;
    color: #6d28d9;
    font-size: 0.68rem;
    font-weight: 950;
}

.member-metrics.is-monitoring {
    grid-template-columns: repeat(3, minmax(92px, 1fr));
}

.member-card {
    overflow: hidden;
}

.member-card.is-open {
    border-color: rgba(79, 70, 229, 0.22);
    box-shadow: 0 18px 42px rgba(15, 23, 42, 0.08);
}

.member-detail {
    background:
        linear-gradient(180deg, #ffffff, #f8fafc),
        radial-gradient(circle at top left, rgba(79, 70, 229, 0.08), transparent 20rem);
}

.detail-top {
    position: relative;
    padding: 0.65rem;
    border-radius: 0.85rem;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
}

.detail-top::before {
    content: '';
    position: absolute;
    inset: 0.55rem auto 0.55rem 0;
    width: 0.24rem;
    border-radius: 999px;
    background: linear-gradient(180deg, #4f46e5, #06b6d4);
}

.detail-tags .tag {
    border: 1px solid transparent;
}

.tag.is-danger {
    background: #fee2e2;
    color: #b91c1c;
    border-color: #fecaca;
}

.tag.is-warning {
    background: #fef3c7;
    color: #92400e;
    border-color: #fde68a;
}

.tag.is-success {
    background: #dcfce7;
    color: #166534;
    border-color: #bbf7d0;
}

.tag.is-info {
    background: #dbeafe;
    color: #1d4ed8;
    border-color: #bfdbfe;
}

.detail-grid {
    align-items: start;
}

.detail-panel {
    border-radius: 0.9rem;
    background: linear-gradient(180deg, #ffffff, #fbfdff);
    box-shadow: 0 10px 26px rgba(15, 23, 42, 0.045);
}

.detail-panel__head {
    padding: 0.38rem 0.42rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.68rem;
    background: #f8fafc;
    margin-bottom: 0.45rem;
}

.panel-title i {
    box-shadow: inset 0 0 0 1px rgba(79, 70, 229, 0.08);
}

.panel-title {
    min-width: 0;
    gap: 0.34rem;
}

.panel-title strong {
    line-height: 1.15;
}

.panel-count {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #334155;
}

.metric-grid > div,
.kpi-period-box > div {
    min-width: 0;
    border-radius: 0.68rem;
    background: #f8fafc;
}

.kpi-panel-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.35rem;
    margin: 0.08rem 0 0.48rem;
}

.kpi-panel-toolbar__filters {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.28rem;
    min-width: 0;
    flex: 1;
}

.kpi-panel-select {
    width: 100%;
    min-width: 0;
    height: 1.72rem;
    border-radius: 0.56rem;
    border: 1px solid #dbe3ef;
    background: #f8fafc;
    color: #334155;
    font-size: 0.56rem;
    font-weight: 850;
    padding: 0 0.42rem;
    outline: none;
}

.kpi-panel-apply-btn {
    flex: 0 0 auto;
    height: 1.72rem;
    border-radius: 0.56rem;
    border: 1px solid #c7d2fe;
    background: #eef2ff;
    color: #3730a3;
    font-size: 0.56rem;
    font-weight: 900;
    padding: 0 0.58rem;
}

.kpi-panel-apply-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.drawer-error-box.is-compact {
    padding: 0.5rem 0.58rem;
    margin-bottom: 0.42rem;
}

.drawer-error-box.is-compact strong {
    font-size: 0.62rem;
}

.issue-list,
.alert-list,
.approval-list,
.training-list,
.parameter-list {
    margin-top: 0.55rem;
}

.issue-item,
.module-alert,
.approval-item,
.training-row {
    transition:
        transform 0.16s ease,
        border-color 0.16s ease,
        box-shadow 0.16s ease;
}

.issue-item:hover,
.module-alert:hover,
.approval-item:hover,
.training-row:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
}

.hcis-issue-stack {
    display: grid;
    gap: 0.34rem;
    margin-top: 0.5rem;
    padding: 0.58rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.72rem;
    background: linear-gradient(180deg, #f8fafc, #ffffff);
}

.hcis-issue-stack .issue-list {
    margin-top: 0;
    gap: 0.34rem;
}

.hcis-issue-stack .issue-item {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: center;
    gap: 0.42rem;
    padding: 0.54rem 0.58rem;
    border: 1px solid #e5eaf2;
    border-radius: 0.68rem;
    background: #ffffff;
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.03);
}

.hcis-issue-stack .issue-item > div {
    display: grid;
    gap: 0.08rem;
}

.hcis-issue-stack .issue-item strong {
    display: block;
    font-size: 0.68rem;
    line-height: 1.2;
}

.hcis-issue-stack .issue-item span {
    display: block;
    color: #64748b;
    font-size: 0.56rem;
    font-weight: 750;
    line-height: 1.3;
}

.hcis-issue-stack .issue-item i {
    width: 1.28rem;
    height: 1.28rem;
    display: grid;
    place-items: center;
    margin-top: 0;
    border-radius: 0.42rem;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 0.7rem;
    line-height: 1;
}

.hcis-issue-stack .issue-item.is-danger i {
    background: #fee2e2;
    color: #dc2626;
}

.hcis-issue-stack .issue-item.is-warning i {
    background: #fef3c7;
    color: #d97706;
}

.hcis-issue-stack .issue-item.is-info i {
    background: #e0f2fe;
    color: #0284c7;
}

.module-alert {
    border: 1px solid #e2e8f0;
}

.module-alert.is-warning {
    background: #fffbeb;
    border-color: #fde68a;
    color: #92400e;
}

.module-alert.is-success {
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: #166534;
}

.training-status {
    border: 1px solid transparent;
}

.training-status.is-complete {
    background: #dcfce7;
    color: #166534;
    border-color: #bbf7d0;
}

.training-status.is-progress {
    background: #dbeafe;
    color: #1d4ed8;
    border-color: #bfdbfe;
}

.training-status.is-pending {
    background: #fef3c7;
    color: #92400e;
    border-color: #fde68a;
}

.attendance-issue-drawer-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.34);
    backdrop-filter: blur(2px);
    z-index: 95;
}

.attendance-issue-drawer {
    position: fixed;
    top: 0;
    right: 0;
    width: min(30rem, 92vw);
    height: 100vh;
    display: grid;
    grid-template-rows: auto 1fr auto;
    background: #ffffff;
    border-left: 1px solid #e2e8f0;
    box-shadow: -12px 0 28px rgba(15, 23, 42, 0.12);
    z-index: 110;
    transform: translateX(calc(100% + 40px));
    transition: transform 0.24s ease;
}

.attendance-issue-drawer.is-open {
    transform: translateX(0);
}

.attendance-issue-drawer__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.8rem;
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;
    background: linear-gradient(180deg, #fff, #f8fafc);
}

.attendance-issue-drawer__head h3 {
    margin: 0.2rem 0 0;
    font-size: 1.04rem;
    font-weight: 800;
    color: #0f172a;
}

.attendance-issue-drawer__head small {
    display: block;
    margin-top: 0.22rem;
    font-size: 0.76rem;
    font-weight: 700;
    color: #64748b;
}

.drawer-close-btn {
    width: 2rem;
    height: 2rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.56rem;
    display: grid;
    place-items: center;
    background: #ffffff;
    color: #334155;
}

.drawer-close-btn:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
}

.attendance-issue-drawer__body {
    overflow: auto;
    padding: 0.9rem 1rem;
    display: grid;
    align-content: start;
    gap: 0.7rem;
}

.attendance-issue-drawer__body.is-state-centered {
    align-content: center;
}

.attendance-status-skeleton-list {
    display: grid;
    gap: 0.75rem;
}

.attendance-issue-card {
    border: 1px solid #e2e8f0;
    border-radius: 0.8rem;
    background: #ffffff;
    padding: 0.72rem;
    display: grid;
    gap: 0.52rem;
}

.attendance-issue-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.7rem;
}

.attendance-issue-card__head strong {
    display: block;
    font-size: 0.86rem;
    color: #0f172a;
}

.attendance-issue-card__head span {
    display: block;
    margin-top: 0.16rem;
    font-size: 0.72rem;
    color: #64748b;
}

.attendance-issue-card__head small {
    font-size: 0.7rem;
    font-weight: 700;
    color: #475569;
}

.attendance-issue-card__meta {
    display: flex;
    gap: 0.6rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
}

.attendance-issue-pill-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.attendance-issue-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border: 1px solid transparent;
    border-radius: 999px;
    padding: 0.22rem 0.56rem;
    font-size: 0.68rem;
    font-weight: 800;
}

.attendance-issue-pill small {
    font-size: 0.62rem;
    opacity: 0.84;
}

.attendance-issue-pill.is-danger {
    background: #fee2e2;
    border-color: #fecaca;
    color: #b91c1c;
}

.attendance-issue-pill.is-warning,
.attendance-issue-pill.is-orange {
    background: #fef3c7;
    border-color: #fde68a;
    color: #92400e;
}

.attendance-issue-pill.is-info {
    background: #dbeafe;
    border-color: #bfdbfe;
    color: #1d4ed8;
}

.attendance-issue-pill.is-success {
    background: #dcfce7;
    border-color: #bbf7d0;
    color: #166534;
}

.attendance-issue-pill.is-purple {
    background: #f3e8ff;
    border-color: #e9d5ff;
    color: #6d28d9;
}

.attendance-issue-pill.is-secondary {
    background: #e2e8f0;
    border-color: #cbd5e1;
    color: #334155;
}

.kpi-member-card-btn {
    width: 100%;
    border: 0;
    background: transparent;
    padding: 0;
    text-align: left;
    display: grid;
    gap: 0.55rem;
    cursor: pointer;
}

.kpi-member-card__foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.55rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: #64748b;
}

.kpi-member-card__foot i {
    font-size: 0.76rem;
    transition: transform 0.16s ease;
}

.kpi-member-card__foot i.is-open {
    transform: rotate(180deg);
}

.kpi-member-detail-panel {
    margin-top: 0.16rem;
    padding-top: 0.62rem;
    border-top: 1px dashed #dbe3ef;
    display: grid;
    gap: 0.72rem;
}

.kpi-new-joiner-alert {
    border: 1px solid #cbd5e1;
    background: #f1f5f9;
    color: #334155;
    border-radius: 0.64rem;
    padding: 0.5rem 0.62rem;
    font-size: 0.7rem;
    font-weight: 800;
}

.attendance-status-filter-pills.is-member-detail-mode {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.filter-pill {
    border: 1px solid #dbe3ef;
    border-radius: 0.56rem;
    background: #f8fafc;
    color: #334155;
    min-height: 1.95rem;
    padding: 0.32rem 0.52rem;
    font-size: 0.7rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.16s ease;
}

.filter-pill:hover {
    border-color: #cbd5e1;
    background: #ffffff;
}

.filter-pill.is-active {
    border-color: #4f46e5;
    background: #eef2ff;
    color: #4f46e5;
}

.kpi-member-period-select {
    border: 1px solid #dbe3ef;
    border-radius: 0.56rem;
    background: #f8fafc;
    color: #334155;
    min-height: 1.95rem;
    padding: 0.32rem 0.52rem;
    font-size: 0.7rem;
    font-weight: 700;
}

.kpi-member-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.46rem;
}

.kpi-member-summary-grid > div {
    border: 1px solid #e2e8f0;
    border-radius: 0.64rem;
    background: #f8fafc;
    padding: 0.52rem 0.56rem;
    min-width: 0;
}

.kpi-member-summary-grid span,
.kpi-member-summary-grid strong {
    display: block;
}

.kpi-member-summary-grid span {
    font-size: 0.66rem;
    font-weight: 700;
    color: #64748b;
}

.kpi-member-summary-grid strong {
    margin-top: 0.12rem;
    font-size: 0.94rem;
    font-weight: 900;
    color: #0f172a;
}

.kpi-member-trend-card {
    border: 1px solid #e2e8f0;
    border-radius: 0.72rem;
    background: linear-gradient(180deg, #ffffff, #f8fafc);
    padding: 0.64rem;
    display: grid;
    gap: 0.5rem;
}

.kpi-member-trend-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.kpi-member-trend-card__head strong {
    font-size: 0.76rem;
    color: #0f172a;
}

.kpi-member-trend-card__head span {
    font-size: 0.66rem;
    color: #64748b;
    font-weight: 700;
}

.kpi-member-trend-chart {
    border-radius: 0.62rem;
    background: #ffffff;
}

.kpi-member-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.62rem;
}

.lms-year-tools {
    align-items: center;
}

.lms-drawer-toolbar {
    margin-bottom: 0.68rem;
}

.search-box.is-wide {
    width: 100%;
}

.lms-detail-member-card {
    margin-bottom: 0.56rem;
}

.lms-detail-member-head {
    width: 100%;
    border: 0;
    background: transparent;
    padding: 0;
    text-align: left;
    display: grid;
    gap: 0.42rem;
    cursor: pointer;
}

.lms-member-metrics {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.lms-member-expand-hint {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.68rem;
    color: #64748b;
    font-weight: 700;
}

.lms-member-expand-hint i {
    transition: transform 0.16s ease;
}

.lms-member-expand-hint i.is-open {
    transform: rotate(180deg);
}

.lms-member-trainings {
    margin-top: 0.58rem;
    padding-top: 0.52rem;
    border-top: 1px dashed #dbe3ef;
}

.lms-member-training-list {
    display: grid;
    gap: 0.42rem;
}

.lms-member-training-item {
    border: 1px solid #e2e8f0;
    border-radius: 0.62rem;
    padding: 0.52rem 0.56rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.62rem;
    background: #f8fafc;
}

.lms-member-training-item > div {
    min-width: 0;
}

.lms-member-training-item strong,
.lms-member-training-item span {
    display: block;
}

.lms-member-training-item strong {
    font-size: 0.75rem;
    color: #0f172a;
}

.lms-member-training-item span {
    font-size: 0.66rem;
    color: #64748b;
}

.lms-member-learning-progress {
    margin-top: 0.5rem;
    display: grid;
    gap: 0.34rem;
}

.lms-member-learning-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.56rem;
    font-size: 0.7rem;
    color: #334155;
}

.lms-member-learning-item strong {
    color: #0f172a;
}

.kpi-member-detail-block {
    border: 1px solid #e2e8f0;
    border-radius: 0.72rem;
    background: #ffffff;
    padding: 0.64rem;
    min-width: 0;
    display: grid;
    gap: 0.52rem;
}

.kpi-member-detail-block > header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.45rem;
}

.kpi-member-detail-block > header strong {
    font-size: 0.74rem;
    color: #0f172a;
}

.kpi-member-detail-block > header span {
    font-size: 0.64rem;
    color: #64748b;
    font-weight: 700;
}

.kpi-member-detail-list {
    display: grid;
    gap: 0.46rem;
    max-height: 14rem;
    overflow: auto;
    padding-right: 0.1rem;
}

.kpi-member-detail-item {
    border: 1px solid #e2e8f0;
    border-radius: 0.62rem;
    padding: 0.5rem;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.42rem;
    min-width: 0;
}

.kpi-member-detail-item.is-unified {
    align-items: stretch;
}

.kpi-member-detail-item > div {
    min-width: 0;
}

.kpi-member-detail-item strong,
.kpi-member-detail-item span {
    display: block;
}

.kpi-member-detail-item strong {
    font-size: 0.7rem;
    color: #0f172a;
    line-height: 1.3;
}

.kpi-member-detail-item span {
    margin-top: 0.1rem;
    font-size: 0.64rem;
    color: #64748b;
}

.kpi-parameter-status-stack {
    display: grid;
    align-content: start;
    justify-items: end;
    gap: 0.3rem;
}

.attendance-issue-drawer__foot {
    padding: 0.75rem 1rem 0.95rem;
    border-top: 1px solid #e2e8f0;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.attendance-issue-drawer__foot span {
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
}

.drawer-error-box {
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #b91c1c;
    border-radius: 0.72rem;
    padding: 0.72rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
}

.drawer-error-box strong {
    font-size: 0.78rem;
}

.drawer-fade-enter-active,
.drawer-fade-leave-active {
    transition: opacity 0.2s ease;
}

.drawer-fade-enter-from,
.drawer-fade-leave-to {
    opacity: 0;
}

@media (max-width: 1180px) {
    .quick-stat-grid.is-leader-focused {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .team-insight-grid.is-balanced {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 760px) {
    .quick-stat-grid.is-leader-focused,
    .leader-summary-split,
    .leader-mini-stat-grid {
        grid-template-columns: 1fr;
    }

    .member-metrics.is-monitoring {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .kpi-panel-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .kpi-panel-toolbar__filters {
        grid-template-columns: 1fr;
    }

    .detail-loading__grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 1320px) {
    .leader-hero__content,
    .leader-main-grid {
        grid-template-columns: 1fr;
    }

    .leader-side {
        position: static;
        display: block;
        columns: 2;
        column-gap: 0.5rem;
    }

    .leader-side > .side-card {
        display: inline-block;
        width: 100%;
        margin: 0 0 0.5rem;
        break-inside: avoid;
        -webkit-column-break-inside: avoid;
    }

    .member-row {
        grid-template-columns: 1fr;
    }

    .member-row-action {
        justify-content: space-between;
    }

    .action-list.is-scrollable {
        max-height: 20rem;
    }

    .hcis-detail-layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 1080px) {
    .quick-stat-grid {
        grid-template-columns: repeat(6, minmax(8rem, 1fr));
        overflow-x: auto;
        padding-bottom: 0.12rem;
    }

    .team-insight-grid {
        grid-template-columns: 1fr;
    }

    .toolbar-main,
    .toolbar-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .toolbar-main {
        flex-direction: column;
        align-items: stretch;
    }

    .toolbar-actions {
        justify-content: stretch;
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .search-box {
        width: 100%;
    }

    .filter-control {
        width: 100%;
        min-width: 0;
    }

    .member-metrics {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .hcis-priority-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .detail-grid {
        grid-template-columns: 1fr;
    }

    .priority-intel-grid {
        grid-template-columns: 1fr;
    }

    .kpi-member-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .kpi-member-detail-grid {
        grid-template-columns: 1fr;
    }

    .shift-board-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .selected-shift-members {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 760px) {
    .leader-dashboard-page {
        padding: 0.45rem;
    }

    .leader-hero {
        padding: 0.62rem;
        border-radius: 0.85rem;
    }

    .hero-brief-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .hero-title-row h1 {
        font-size: 1.18rem;
    }

    .hero-title-row p {
        font-size: 0.68rem;
    }

    .toolbar-actions {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .leader-side {
        columns: 1;
    }

    .member-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .metric-grid,
    .metric-grid.is-three,
    .metric-grid--hcis,
    .hcis-status-grid,
    .lms-overview__stats,
    .kpi-period-box {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .hcis-priority-row {
        grid-template-columns: 1fr;
    }

    .detail-top {
        grid-template-columns: 1fr;
    }

    .detail-tags {
        justify-content: flex-start;
    }

    .priority-summary-grid {
        grid-template-columns: 1fr;
    }

    .kpi-year-tools {
        width: 100%;
        justify-content: space-between;
    }
}

@media (max-width: 560px) {
    .hero-brief-grid,
    .toolbar-actions,
    .member-metrics,
    .priority-intel-grid,
    .metric-grid,
    .metric-grid.is-three,
    .metric-grid--hcis,
    .hcis-status-grid,
    .lms-overview,
    .lms-overview__stats,
    .kpi-period-box,
    .leader-side,
    .shift-board-grid {
        grid-template-columns: 1fr;
    }

    .kpi-member-summary-grid {
        grid-template-columns: 1fr;
    }

    .lms-focus-alerts {
        grid-template-columns: 1fr;
    }

    .quick-stat-grid {
        grid-template-columns: repeat(6, 8rem);
    }

    .quick-stat-card {
        min-height: 3.05rem;
    }

    .member-row {
        padding: 0.48rem;
    }

    .member-profile {
        align-items: flex-start;
    }

    .shift-pill {
        width: 100%;
        justify-content: center;
    }

    .toolbar-card,
    .side-card,
    .insight-card,
    .detail-panel,
    .shift-board-card {
        padding: 0.5rem;
    }

    .section-head {
        flex-direction: column;
        align-items: stretch;
    }

    .date-picker-control {
        width: 100%;
    }

    .date-picker-control select {
        width: 100%;
    }
}
</style>
