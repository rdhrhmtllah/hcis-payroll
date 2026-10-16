<template>
    <div class="attendance-home-v4">
        <div class="attendance-home-v4__container">
            <!-- HERO ASISTEN HIDUP -->
            <section class="assistant-hero" :class="`is-${assistantState.tone}`">
                <div class="assistant-hero__orb assistant-hero__orb--one"></div>
                <div class="assistant-hero__orb assistant-hero__orb--two"></div>

                <div class="assistant-hero__content">
                    <div class="assistant-hero__left">
                        <!-- <div class="assistant-hero__topline">
                            <div class="assistant-badge">
                                <i class="bi bi-stars"></i>
                                <span>Asisten Absensi Aktif</span>
                            </div>
                            <div class="assistant-period-pill">
                                {{ cutoffLabel }}
                            </div>
                        </div> -->

                        <div class="assistant-hero__headline-wrap">
                            <div
                                class="hero-title-group"
                                style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap"
                            >
                                <h1 class="assistant-hero__title text-white">HCIS HOMEPAGE.</h1>
                                <!-- <a
                                    v-if="greeting.is_leader"
                                    href="/homepage/leader-dashboard"
                                    class="hero-issue-badge"
                                    title="Lihat Dashboard Tim"
                                >
                                    <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                                    <span><strong>5 Issues</strong> Team</span>
                                    <i class="bi bi-arrow-right-short"></i>
                                </a> -->

                                <a
                                    v-if="greeting.is_leader"
                                    href="/homepage/leader-dashboard"
                                    class="hero-action-btn is-secondary"
                                    style="text-decoration: none"
                                >
                                    <i class="bi bi-grid-1x2-fill"></i>
                                    Dashboard Tim Lengkap
                                </a>
                            </div>
                            <!-- <p class="assistant-hero__message">
                                Halo, <strong>{{ greeting.nama }}</strong
                                >. {{ waktuSapaan }}! Semoga harimu produktif.
                            </p> -->
                            <div class="hero-hcis-section">
                                <div class="hero-hcis-section__header">
                                    <span class="hero-hcis-section__label">HCIS Attendance Overview</span>
                                    <span class="hero-hcis-section__badge"> {{ totalProblems }} Issue </span>
                                </div>

                                <div class="hero-hcis-section__grid">
                                    <div class="hero-hcis-box is-emerald">
                                        <strong>{{ countByStatus('present') }}</strong>
                                        <span>Hadir</span>
                                    </div>

                                    <div class="hero-hcis-box is-amber">
                                        <strong>{{ countByStatus('late') }}</strong>
                                        <span>Terlambat</span>
                                    </div>

                                    <div class="hero-hcis-box is-rose">
                                        <strong>{{ countByStatus('alpha') }}</strong>
                                        <span>Alpha</span>
                                    </div>

                                    <div class="hero-hcis-box">
                                        <strong>{{ countByStatus('no-checkin') }}</strong>
                                        <span>No IN</span>
                                    </div>

                                    <div class="hero-hcis-box">
                                        <strong>{{ countByStatus('no-checkout') }}</strong>
                                        <span>No OUT</span>
                                    </div>

                                    <div class="hero-hcis-box is-violet">
                                        <strong>{{ countByStatus('izin') + countByStatus('cuti') }}</strong>
                                        <span>Izin</span>
                                    </div>
                                </div>
                            </div>
                            <!-- <p class="assistant-hero__message">{{ assistantState.message }}</p> -->
                        </div>

                        <div class="assistant-hero__chips">
                            <div class="assistant-chip assistant-chip--wide">
                                <span class="assistant-chip__label">Shift Hari Ini</span>
                                <strong class="assistant-chip__value">{{ todayHero.shiftLabel }}</strong>
                                <span class="assistant-chip__meta">{{ todayHero.shiftTime }}</span>
                            </div>

                            <div class="assistant-chip">
                                <span class="assistant-chip__label">Check In</span>
                                <strong class="assistant-chip__value">{{ todayHero.checkIn }}</strong>
                                <span class="assistant-chip__meta">{{ todayHero.checkInMeta }}</span>
                            </div>

                            <div class="assistant-chip">
                                <span class="assistant-chip__label">Check Out</span>
                                <strong class="assistant-chip__value">{{ todayHero.checkOut }}</strong>
                                <span class="assistant-chip__meta">{{ todayHero.checkOutMeta }}</span>
                            </div>
                        </div>

                        <!-- <div class="assistant-hero__actions mt-3">
                            <button type="button" class="hero-action-btn" @click="handleCheckInOut">
                                <i class="bi bi-geo-alt-fill"></i>
                                Check In/Out
                            </button>
                            <a
                                v-if="greeting.is_leader"
                                href="/homepage/leader-dashboard"
                                class="hero-action-btn is-secondary"
                                style="text-decoration: none"
                            >
                                <i class="bi bi-grid-1x2-fill"></i>
                                Dashboard Tim Lengkap
                            </a>
                        </div> -->
                    </div>
                    <div class="assistant-hero__right d-flex">
                        <!-- KPI CARD -->
                        <button type="button" class="hero-snapshot-card shell-btn" @click="visit('/kpi')">
                            <div class="hero-snapshot-card__header">
                                <div class="hero-snapshot-card__title-group">
                                    <div class="hero-snapshot-card__iconbox">
                                        <i class="bi bi-graph-up-arrow"></i>
                                    </div>
                                    <div>
                                        <div class="hero-snapshot-card__title">Key Performance Indicator</div>
                                        <div class="hero-snapshot-card__subtitle">{{ heroKpi.badgeText }}</div>
                                    </div>
                                </div>

                                <span class="hero-snapshot-card__pill hero-snapshot-card__pill--success">
                                    {{ heroKpi.statusLabel }}
                                </span>
                            </div>

                            <div class="hero-kpi-compact">
                                <div class="hero-kpi-compact__score">
                                    <div class="hero-kpi-compact__ring">
                                        <strong>{{ heroKpi.score }}</strong>
                                        <span>Skor KPI</span>
                                    </div>
                                </div>

                                <div class="hero-kpi-compact__stats">
                                    <div
                                        v-for="item in heroKpi.parameters"
                                        :key="item.name"
                                        class="hero-kpi-compact__metric"
                                    >
                                        <div class="hero-kpi-compact__metric-top">
                                            <span class="hero-kpi-compact__metric-name">{{ item.name }}</span>
                                            <span class="hero-kpi-compact__metric-value">
                                                {{ item.score }}<small v-if="item.weight"> ({{ item.weight }})</small>
                                            </span>
                                        </div>
                                        <div class="hero-kpi-compact__metric-bar">
                                            <div
                                                class="hero-kpi-compact__metric-fill"
                                                :style="{ width: `${Math.min(item.score, 100)}%` }"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- <div class="hero-mini-trend">
                                <div class="hero-mini-trend__label">Tren 6 Bulan</div>
                                <div class="hero-mini-trend__line">
                                    <div
                                        v-for="point in heroKpi.trend"
                                        :key="point.month"
                                        class="hero-mini-trend__point"
                                    >
                                        <span class="hero-mini-trend__score">{{ point.score }}</span>
                                        <small class="hero-mini-trend__month">{{ point.month }}</small>
                                    </div>
                                </div>
                            </div> -->
                            <div class="hero-mini-trend">
                                <div class="hero-mini-trend__label">Tren 6 Bulan</div>
                                <div class="hero-mini-trend__chart">
                                    <VueApexCharts
                                        type="area"
                                        height="50"
                                        :options="kpiSparklineOptions"
                                        :series="kpiSparklineSeries"
                                    />
                                </div>
                            </div>
                        </button>

                        <!-- LMS CARD -->
                        <button type="button" class="hero-snapshot-card shell-btn" @click="visit('/lms')">
                            <div class="hero-snapshot-card__header">
                                <div class="hero-snapshot-card__title-group">
                                    <div class="hero-snapshot-card__iconbox">
                                        <i class="bi bi-mortarboard"></i>
                                    </div>
                                    <div>
                                        <div class="hero-snapshot-card__title">Learning Management</div>
                                        <div class="hero-snapshot-card__subtitle">{{ heroLms.badgeText }}</div>
                                    </div>
                                </div>

                                <span class="hero-snapshot-card__pill hero-snapshot-card__pill--info">
                                    {{ heroLms.completed }}/{{ heroLms.enrolled }} Selesai
                                </span>
                            </div>

                            <div class="hero-lms-compact">
                                <div class="hero-lms-compact__next">
                                    <div class="hero-lms-compact__eyebrow">Sesi Mendatang</div>
                                    <div class="hero-lms-compact__next-card">
                                        <div class="hero-lms-compact__next-label">Materi Berikutnya</div>
                                        <div class="hero-lms-compact__next-title">{{ heroLms.nextSession }}</div>
                                        <div class="hero-lms-compact__next-date">
                                            <i class="bi bi-clock"></i>
                                            <span>{{ heroLms.nextSessionDate }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="hero-lms-compact__progress">
                                    <div class="hero-lms-compact__eyebrow">Progress Kursus</div>

                                    <div
                                        v-for="course in heroLms.progressList"
                                        :key="course.title"
                                        class="hero-lms-compact__course"
                                    >
                                        <div class="hero-lms-compact__course-top">
                                            <span class="hero-lms-compact__course-title">{{ course.title }}</span>
                                            <span class="hero-lms-compact__course-percent">{{ course.progress }}%</span>
                                        </div>
                                        <div class="hero-lms-compact__course-bar">
                                            <div
                                                class="hero-lms-compact__course-fill"
                                                :class="{ 'is-complete': course.progress >= 100 }"
                                                :style="{ width: `${Math.min(course.progress, 100)}%` }"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="hero-mini-trend">
                                <div class="hero-mini-trend__label">Training Selesai</div>
                                <div class="hero-mini-trend__chart">
                                    <VueApexCharts
                                        type="area"
                                        height="50"
                                        :options="lmsSparklineOptions"
                                        :series="lmsSparklineSeries"
                                    />
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </section>

            <!-- QUICK POWER STATS -->
            <section class="power-strip">
                <button
                    v-for="item in powerCards"
                    :key="item.key"
                    type="button"
                    class="power-card shell-btn"
                    :class="`is-${item.tone}`"
                    @click="handlePowerCardClick(item.key)"
                >
                    <div class="power-card__icon">
                        <i :class="item.icon"></i>
                    </div>
                    <div class="power-card__copy">
                        <div class="power-card__value">{{ item.value }}</div>
                        <div class="power-card__label">{{ item.label }}</div>
                        <div class="power-card__desc">{{ item.desc }}</div>
                    </div>
                </button>
            </section>

            <section class="dashboard-main-grid mt-3">
                <!-- LEFT: CALENDAR -->
                <section class="shell-card shell-card--inner cutoff-card">
                    <div class="section-head cutoff-card__head">
                        <div>
                            <div class="section-label">Kalender Cutoff</div>
                            <h2 class="section-title">Pantauan Absensi</h2>
                        </div>
                    </div>

                    <!-- SUMMARY -->
                    <div class="cutoff-summary-row">
                        <div class="cutoff-summary-pill is-emerald">Hadir {{ countByStatus('present') }}</div>
                        <div class="cutoff-summary-pill is-amber">Terlambat {{ countByStatus('late') }}</div>
                        <div class="cutoff-summary-pill is-rose">Masalah {{ totalProblems }}</div>
                        <div class="cutoff-summary-pill is-violet">
                            Izin/Cuti {{ countByStatus('izin') + countByStatus('cuti') }}
                        </div>
                    </div>

                    <!-- CALENDAR -->
                    <div class="cutoff-calendar-scroll-wrapper">
                        <div class="cutoff-calendar-grid">
                            <div v-for="day in weekdayLabels" :key="day" class="cutoff-calendar-grid__weekday">
                                {{ day }}
                            </div>

                            <div
                                v-for="day in cutoffDays"
                                :key="day.date"
                                class="calendar-card"
                                :class="getStatusClass(day)"
                                @click="openDayModal(day)"
                            >
                                <!-- HEADER -->
                                <div class="card-header">
                                    <span class="date">{{ getDayNumber(day.date) }}</span>
                                    <span class="status-badge" :class="getBadgeClass(day)">
                                        {{ getStatusLabel(day) }}
                                    </span>
                                </div>

                                <!-- SHIFT -->
                                <div class="card-shift">
                                    <span :class="getShiftClass(day)">
                                        {{ day.shift_name || 'OFF' }}
                                    </span>
                                </div>

                                <!-- CHECKTIME -->
                                <div class="card-time">
                                    <div class="time-row">
                                        <span>IN</span>
                                        <span :class="getInClass(day)">
                                            {{ day.check_in || '-' }}
                                        </span>
                                    </div>
                                    <div class="time-row">
                                        <span>OUT</span>
                                        <span :class="getOutClass(day)">
                                            {{ day.check_out || '-' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- RIGHT SIDEBAR -->
                <aside class="dashboard-side-grid">
                    <!-- 🔥 ACTION CENTER -->
                    <section class="shell-card shell-card--inner side-card">
                        <div class="section-head section-head--tight">
                            <div>
                                <div class="section-label">Action Center</div>
                                <h2 class="section-title">Perlu Tindakan</h2>
                            </div>
                        </div>

                        <div class="action-center-list">
                            <div
                                v-for="item in combinedActions"
                                :key="item.id"
                                class="action-item"
                                @click="handleAlertClick(item)"
                            >
                                <!-- DATE -->
                                <div class="action-item__date">
                                    <span class="day">{{ getDayNumber(item.date) }}</span>
                                    <span class="meta">
                                        {{ getMonthShort(item.date) }} • {{ getWeekdayShort(item.date) }}
                                    </span>
                                </div>

                                <!-- CONTENT -->
                                <div class="action-item__content">
                                    <div class="action-item__title">
                                        {{ item.title }}
                                    </div>
                                    <div class="action-item__desc">
                                        {{ item.description }}
                                    </div>
                                </div>

                                <!-- RIGHT ACTION -->
                                <div class="action-item__action">
                                    <span v-if="item.type === 'Alert'" class="badge-alert"> Alert </span>

                                    <span v-else class="badge-follow">Follow Up</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- 🔥 LMS SIDEBAR -->
                    <section class="shell-card shell-card--inner side-card">
                        <div class="section-head section-head--tight">
                            <div>
                                <div class="section-label">Learning Focus</div>
                                <h2 class="section-title">Course You're Taking</h2>
                            </div>

                            <div class="lms-header-actions">
                                <!-- <span class="lms-status">Active</span> -->
                                <button class="lms-add-btn">
                                    <i class="bi shield"></i>
                                </button>
                            </div>
                        </div>

                        <div class="lms-course-list">
                            <div v-for="course in heroLms.progressList" :key="course.title" class="lms-course-item">
                                <!-- LEFT -->
                                <div class="lms-course-item__left">
                                    <div class="lms-course-item__icon">
                                        {{ course.title.charAt(0) }}
                                    </div>

                                    <div class="lms-course-item__info">
                                        <div class="lms-course-item__title">
                                            {{ course.title }}
                                        </div>
                                        <div class="lms-course-item__author">
                                            {{ course.author || 'Instructor' }}
                                        </div>
                                    </div>
                                </div>

                                <!-- CENTER -->
                                <div class="lms-course-item__center">
                                    <div class="lms-course-item__remaining">Remaining</div>
                                    <div class="lms-course-item__time">
                                        {{ course.remaining || '8h 45 min' }}
                                    </div>
                                </div>

                                <!-- RIGHT (CIRCLE PROGRESS) -->
                                <div class="lms-course-item__progress">
                                    <div class="progress-circle">
                                        <svg viewBox="0 0 36 36">
                                            <path class="circle-bg" d="M18 2 a 16 16 0 0 1 0 32 a 16 16 0 0 1 0 -32" />
                                            <path
                                                class="circle-fill"
                                                :stroke-dasharray="course.progress + ', 100'"
                                                d="M18 2 a 16 16 0 0 1 0 32 a 16 16 0 0 1 0 -32"
                                            />
                                        </svg>
                                        <span>{{ course.progress }}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </aside>
            </section>
        </div>

        <!-- MODAL DETAIL TANGGAL -->
        <transition name="fade-scale">
            <div v-if="showDayModal && selectedDayRecord" class="day-modal-overlay" @click.self="closeDayModal">
                <div class="day-modal-card">
                    <div class="day-modal-card__head" :class="`is-${selectedDayRecord.primaryStatusTone}`">
                        <div>
                            <div class="day-modal-card__eyebrow">Detail Absensi Harian</div>
                            <div class="day-modal-card__title">
                                {{ formatLongDate(parseDate(selectedDayRecord.date)) }}
                            </div>
                            <div class="day-modal-card__subtitle">
                                {{ statusVisual(selectedDayRecord.primaryStatusKey).label }}
                                <span v-if="selectedDayRecord.shiftLabel">• {{ selectedDayRecord.shiftLabel }}</span>
                            </div>
                        </div>
                        <button type="button" class="day-modal-card__close shell-btn" @click="closeDayModal">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <div class="day-modal-card__body">
                        <section class="day-modal-section">
                            <div class="day-modal-section__title">Informasi Shift</div>
                            <div class="day-detail-grid">
                                <div class="day-detail-item">
                                    <span class="day-detail-item__label">Shift</span>
                                    <strong class="day-detail-item__value">{{
                                        selectedDayRecord.shiftLabel || 'Tidak ada shift'
                                    }}</strong>
                                </div>
                                <div class="day-detail-item">
                                    <span class="day-detail-item__label">Jam Shift</span>
                                    <strong class="day-detail-item__value">{{
                                        selectedDayRecord.shiftTime || '-'
                                    }}</strong>
                                </div>
                                <div class="day-detail-item">
                                    <span class="day-detail-item__label">Check In</span>
                                    <strong class="day-detail-item__value">{{
                                        selectedDayRecord.checkIn || '-'
                                    }}</strong>
                                </div>
                                <div class="day-detail-item">
                                    <span class="day-detail-item__label">Check Out</span>
                                    <strong class="day-detail-item__value">{{
                                        selectedDayRecord.checkOut || '-'
                                    }}</strong>
                                </div>
                            </div>
                        </section>

                        <section class="day-modal-section">
                            <div class="day-modal-section__title">Catatan Hari Tersebut</div>
                            <div class="day-note-box">
                                {{ selectedDayRecord.note || 'Tidak ada catatan tambahan.' }}
                            </div>
                        </section>

                        <section class="day-modal-section">
                            <div class="day-modal-section__title">Riwayat Checktime</div>
                            <div class="day-timeline">
                                <div
                                    v-for="(item, idx) in selectedDayTimeline"
                                    :key="`${item.time}-${idx}`"
                                    class="day-timeline__row"
                                    :class="`is-${item.kind}`"
                                >
                                    <div class="day-timeline__marker">
                                        <i :class="item.icon"></i>
                                    </div>
                                    <div class="day-timeline__content">
                                        <div class="day-timeline__title">{{ item.label }}</div>
                                        <div class="day-timeline__time">{{ item.time }}</div>
                                    </div>
                                </div>

                                <div v-if="!selectedDayTimeline.length" class="day-timeline__empty">
                                    Tidak ada riwayat checktime untuk tanggal ini.
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="day-modal-card__footer">
                        <button class="modal-action-btn shell-btn" type="button" @click="visit('/absensi')">
                            <i class="bi bi-arrow-right-circle"></i>
                            <span>Lihat Absensi</span>
                        </button>
                        <button
                            class="modal-action-btn shell-btn is-secondary"
                            type="button"
                            @click="visit('/absensi/koreksi')"
                        >
                            <i class="bi bi-pencil-square"></i>
                            <span>Ajukan Koreksi</span>
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { computed, ref, watchEffect } from 'vue';
import { router } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
const props = defineProps({
    user: { type: Object, default: () => ({}) },
    cutoffRange: {
        type: Object,
        default: () => ({
            start: '2026-03-16',
            end: '2026-04-15',
        }),
    },
    attendanceRecords: { type: Array, default: () => [] },
    followUpItems: { type: Array, default: () => [] },
    upcomingSchedule: { type: Array, default: () => [] },
    moduleSummary: { type: Object, default: () => ({}) },
    greeting: { type: Object, default: () => ({}) },
    role: { type: String, default: '' },
    notifications: { type: Array, default: () => [] },
    pendingApprovals: { type: Number, default: 0 },
    hcis: { type: Object, default: () => ({}) },
    kpi: { type: Object, default: () => ({}) },
    lms: { type: Object, default: () => ({}) },
    visitor: { type: Object, default: () => ({}) },
});

const today = new Date();
const weekdayLabels = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
const activeCalendarFilter = ref('all');
const selectedDate = ref('');
const showDayModal = ref(false);

const resolvedUser = computed(() => ({
    name: props.user?.name || props.user?.username || 'User',
    department: props.user?.department || props.user?.job_title || 'Department belum diatur',
    nik: props.user?.nik || '-',
}));

const combinedActions = computed(() => {
    return [
        ...alertChecklist.value.map((a) => ({
            ...a,
            date: todayRecord.value?.date,
            type: 'Alert',
        })),
        ...followUpResolved.value.map((f) => ({
            ...f,
            type: 'Follow Up',
        })),
    ].slice(0, 6);
});
const heroKpi = computed(() => ({
    statusLabel: 'On Track',
    badgeText: 'Snapshot Performa',
    score: props.moduleSummary?.kpi?.score ?? 88.5,
    parameters: props.moduleSummary?.kpi?.parameters ?? [
        { name: 'Project Delivery', score: 95, weight: '40%' },
        { name: 'Team Collaboration', score: 85, weight: '30%' },
        { name: 'Innovation', score: 80, weight: '30%' },
    ],
    trend: props.moduleSummary?.kpi?.trend ?? [
        { month: 'Okt', score: 78 },
        { month: 'Nov', score: 82 },
        { month: 'Des', score: 85 },
        { month: 'Jan', score: 88.5 },
        { month: 'Feb', score: 87 },
        { month: 'Mar', score: 89 },
    ],
}));

const heroLms = computed(() => ({
    badgeText: 'Ringkasan Belajar',
    completed: props.moduleSummary?.lms?.completed_courses ?? 1,
    enrolled: props.moduleSummary?.lms?.enrolled_courses ?? 3,
    nextSession: props.moduleSummary?.lms?.next_session ?? 'TRAINING-TEST',
    nextSessionDate: props.moduleSummary?.lms?.next_session_date ?? '2026-04-25 14:27',
    mandatoryPending: props.moduleSummary?.lms?.mandatory_pending ?? 1,
    progressList: props.moduleSummary?.lms?.learning_progress ?? [
        { title: 'TRAINING-TEST', progress: 25 },
        { title: 'Basic K3L', progress: 39 },
        { title: 'Training Premix', progress: 100 },
    ],
    learningHours: props.moduleSummary?.lms?.learning_hours ?? 2.5,
    trend: props.moduleSummary?.lms?.trend ?? [
        { month: 'Jan', value: 1.2 },
        { month: 'Feb', value: 1.8 },
        { month: 'Mar', value: 2.1 },
        { month: 'Apr', value: 2.5 },
    ],

    trendCompleted: props.moduleSummary?.lms?.trend_completed ?? [
        { month: 'Jan', value: 1 },
        { month: 'Feb', value: 0 },
        { month: 'Mar', value: 2 },
        { month: 'Apr', value: 1 },
    ],
}));

const kpiSparklineSeries = computed(() => [
    {
        name: 'Skor KPI',
        data: heroKpi.value.trend.map((t) => t.score),
    },
]);
const kpiSparklineOptions = computed(() => ({
    chart: {
        type: 'area',
        fontFamily: 'inherit',
        toolbar: { show: false },
        zoom: { enabled: false },
        parentHeightOffset: 0, // PENTING: Hilangkan margin bawah bawaan pembungkus chart
        dropShadow: { enabled: true, top: 1, left: 0, blur: 2, opacity: 0.1 },
    },
    grid: {
        show: false,
        padding: {
            top: -18, // Tarik grafik ke atas agar tidak terpotong
            bottom: -10, // Tarik grafik ke bawah (buang sisa ruang sumbu X)
            right: 5,
            left: 5,
        },
    },
    dataLabels: {
        enabled: true,
        background: { enabled: false },
        style: {
            fontSize: '9px', // Perkecil font sedikit agar muat di 50px
            colors: ['rgba(255, 255, 255, 0.85)'],
            fontWeight: 700,
        },
        offsetY: -3, // Dekatkan angka ke titik
    },
    markers: {
        size: 3,
        colors: ['#3b82f6'],
        strokeColors: 'rgba(255, 255, 255, 0.6)',
        strokeWidth: 2,
        hover: { size: 5 },
    },
    stroke: {
        curve: 'smooth',
        width: 2,
        colors: ['rgba(255, 255, 255, 0.4)'],
    },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.25,
            opacityTo: 0,
            stops: [0, 100],
            colorStops: [
                { offset: 0, color: 'rgba(255, 255, 255, 0.3)', opacity: 1 },
                { offset: 100, color: 'rgba(255, 255, 255, 0)', opacity: 1 },
            ],
        },
    },
    xaxis: {
        categories: heroKpi.value.trend.map((t) => t.month),
        labels: {
            style: {
                colors: 'rgba(255, 255, 255, 0.6)',
                fontSize: '8px', // Perkecil font bulan
                fontWeight: 600,
            },
            offsetY: -7, // Dekatkan teks bulan ke arah grafik
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
        crosshairs: { show: false },
        tooltip: { enabled: false },
    },
    yaxis: {
        show: false,
    },
    tooltip: {
        theme: 'dark',
        y: { formatter: (val) => val },
    },
}));
// Konfigurasi Chart Sparkline untuk LMS
const lmsSparklineSeries = computed(() => [
    {
        name: 'Selesai',
        data: heroLms.value.trendCompleted.map((t) => t.value),
    },
]);

const lmsSparklineOptions = computed(() => ({
    chart: {
        type: 'area',
        fontFamily: 'inherit',
        toolbar: { show: false },
        zoom: { enabled: false },
        parentHeightOffset: 0, // Mencegah area margin bawah terpotong
        dropShadow: { enabled: true, top: 1, left: 0, blur: 2, opacity: 0.1 },
    },
    grid: {
        show: false,
        padding: {
            top: -18, // Tarik grafik ke atas agar tidak terpotong
            bottom: -10, // Tarik grafik ke bawah (buang sisa ruang sumbu X)
            right: 5,
            left: 5,
        },
    },
    dataLabels: {
        enabled: true,
        background: { enabled: false },
        style: {
            fontSize: '9px',
            colors: ['rgba(255, 255, 255, 0.85)'],
            fontWeight: 700,
        },
        offsetY: -3,
    },
    markers: {
        size: 3,
        colors: ['#0ea5e9'], // Pakai aksen cyan/biru muda agar sedikit beda dari KPI
        strokeColors: 'rgba(255, 255, 255, 0.6)',
        strokeWidth: 2,
        hover: { size: 5 },
    },
    stroke: {
        curve: 'smooth',
        width: 2,
        colors: ['rgba(255, 255, 255, 0.4)'],
    },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.25,
            opacityTo: 0,
            stops: [0, 100],
            colorStops: [
                { offset: 0, color: 'rgba(255, 255, 255, 0.3)', opacity: 1 },
                { offset: 100, color: 'rgba(255, 255, 255, 0)', opacity: 1 },
            ],
        },
    },
    xaxis: {
        categories: heroLms.value.trendCompleted.map((t) => t.month),
        labels: {
            style: {
                colors: 'rgba(255, 255, 255, 0.6)',
                fontSize: '8px',
                fontWeight: 600,
            },
            offsetY: -7, // Teks bulan diangkat sedikit agar proporsional
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
        crosshairs: { show: false },
        tooltip: { enabled: false },
    },
    yaxis: {
        show: false,
    },
    tooltip: {
        theme: 'dark',
        y: { formatter: (val) => val },
    },
}));
const firstName = computed(() => String(resolvedUser.value.name).split(' ')[0] || 'User');

const cutoffStartDate = computed(() => parseDate(props.cutoffRange?.start || '2026-03-16'));
const cutoffEndDate = computed(() => parseDate(props.cutoffRange?.end || '2026-04-15'));

const cutoffLabel = computed(() => `${formatLongDate(cutoffStartDate.value)} — ${formatLongDate(cutoffEndDate.value)}`);

const recordsMap = computed(() => {
    const map = new Map();
    const source = props.attendanceRecords.length ? props.attendanceRecords : demoAttendanceRecords();
    source.forEach((item) => map.set(normalizeDateString(item.date), item));
    return map;
});

const cutoffDays = computed(() => {
    const days = [];
    let cursor = new Date(cutoffStartDate.value);

    while (cursor <= cutoffEndDate.value) {
        const dateKey = toYmd(cursor);
        const raw = recordsMap.value.get(dateKey) || {};
        const record = normalizeRecord(raw);

        // 🔥 NEW ENGINE
        const flags = buildAttendanceStatus(record);
        const primary = resolvePrimaryStatus(flags);

        const hasProblem = flags.some((f) => f.problem);

        days.push({
            date: dateKey,
            rawDate: new Date(cursor),

            dayNumber: String(cursor.getDate()),
            monthShort: cursor.toLocaleDateString('id-ID', { month: 'short' }),
            weekdayShort: cursor.toLocaleDateString('id-ID', { weekday: 'short' }),

            isToday: isSameDate(cursor, today),

            shiftLabel: record.shift_label || '',
            shiftTime: record.shift_time || '',

            checkIn: record.check_in || '-',
            checkOut: record.check_out || '-',

            note: record.note || '',

            // 🔥 IMPROVED
            primaryStatusKey: primary.key,
            primaryStatusTone: primary.tone,
            primaryStatusLabel: primary.label,

            hasProblem,
            flags,

            timeline: record.timeline || buildFallbackTimeline(record),
        });

        cursor.setDate(cursor.getDate() + 1);
    }

    return days;
});

const currentCutoffDay = computed(() => {
    const nowKey = toYmd(today);
    const index = cutoffDays.value.findIndex((d) => d.date === nowKey);
    return index >= 0 ? index + 1 : cutoffDays.value.length;
});

const todayRecord = computed(
    () => cutoffDays.value.find((d) => d.isToday) || cutoffDays.value[cutoffDays.value.length - 1],
);

watchEffect(() => {
    if (!selectedDate.value && todayRecord.value?.date) {
        selectedDate.value = todayRecord.value.date;
    }
});

const selectedDayRecord = computed(
    () => cutoffDays.value.find((d) => d.date === selectedDate.value) || todayRecord.value || null,
);

const selectedDayTimeline = computed(() => {
    const timeline = selectedDayRecord.value?.timeline || [];
    return timeline.map((item) => ({
        label: item.label,
        time: item.time,
        kind: item.kind,
        icon: timelineIcon(item.kind),
    }));
});

const filteredCutoffDays = computed(() => {
    if (activeCalendarFilter.value === 'all') return cutoffDays.value;
    if (activeCalendarFilter.value === 'problem') return cutoffDays.value.filter((d) => d.hasProblem);
    if (activeCalendarFilter.value === 'izin-cuti')
        return cutoffDays.value.filter((d) => ['izin', 'cuti'].includes(d.primaryStatusKey));
    if (activeCalendarFilter.value === 'late') return cutoffDays.value.filter((d) => d.primaryStatusKey === 'late');
    return cutoffDays.value;
});

const todayHero = computed(() => {
    const record = todayRecord.value || {};
    return {
        shiftLabel: record.shiftLabel || 'Belum ada shift hari ini',
        shiftTime: record.shiftTime || '-',
        checkIn: record.checkIn || '-',
        checkOut: record.checkOut || '-',
        checkInMeta: record.checkIn && record.checkIn !== '-' ? 'Sudah tercatat' : 'Belum tercatat',
        checkOutMeta: record.checkOut && record.checkOut !== '-' ? 'Sudah tercatat' : 'Belum tercatat',
        statusLabel: statusVisual(record.primaryStatusKey).label,
    };
});

const assistantState = computed(() => buildAssistantState(todayRecord.value));

const totalProblems = computed(() => cutoffDays.value.filter((d) => d.hasProblem).length);

const heroActions = computed(() => {
    const actions = [];

    if (assistantState.value.actionType === 'late-checkin') {
        actions.push({ label: 'Ajukan Izin Terlambat', icon: 'bi bi-send-check', href: '/absensi/izin-terlambat' });
    } else if (assistantState.value.actionType === 'early-checkout') {
        actions.push({ label: 'Ajukan Izin Pulang Cepat', icon: 'bi bi-send-check', href: '/absensi/pulang-cepat' });
    } else if (assistantState.value.actionType === 'missing-checkout') {
        actions.push({ label: 'Buka Absensi Hari Ini', icon: 'bi bi-arrow-right-circle', href: '/absensi' });
        actions.push({ label: 'Ajukan Lembur', icon: 'bi bi-clock-history', href: '/lembur', secondary: true });
        return actions;
    } else {
        actions.push({ label: 'Lihat Absensi', icon: 'bi bi-arrow-right-circle', href: '/absensi' });
    }

    actions.push({ label: 'Riwayat Absensi', icon: 'bi bi-calendar-week', href: '/absensi', secondary: true });
    return actions;
});

const powerCards = computed(() => [
    {
        key: 'problem',
        label: 'Perlu Ditindak',
        value: totalProblems.value,
        desc: 'Tanggal yang butuh perhatian',
        icon: 'bi bi-exclamation-octagon',
        tone: 'rose',
    },
    {
        key: 'expired',
        label: 'Pengajuan Expired',
        value: countByStatus('expired'),
        desc: 'Butuh koreksi atau tindak lanjut',
        icon: 'bi bi-hourglass-split',
        tone: 'indigo',
    },
    {
        key: 'late',
        label: 'Terlambat',
        value: countByStatus('late'),
        desc: 'Kejadian terlambat pada cutoff ini',
        icon: 'bi bi-alarm',
        tone: 'amber',
    },
    {
        key: 'leave',
        label: 'Izin / Cuti',
        value: countByStatus('izin') + countByStatus('cuti'),
        desc: 'Hari izin, sakit, atau cuti',
        icon: 'bi bi-file-earmark-medical',
        tone: 'violet',
    },
]);

const alertChecklist = computed(() => {
    const list = [];

    if (assistantState.value.actionType === 'late-checkin') {
        list.push({
            id: 'late-checkin',
            title: 'Check-in belum tercatat',
            desc: 'Jam masuk sudah lewat. Cek absensi atau ajukan izin terlambat.',
            cta: 'Tindak sekarang',
            tone: 'rose',
            icon: 'bi bi-box-arrow-in-right',
            href: '/absensi/izin-terlambat',
        });
    }

    if (assistantState.value.actionType === 'missing-checkout') {
        list.push({
            id: 'missing-checkout',
            title: 'Check-out belum tercatat',
            desc: 'Jam pulang sudah lewat. Jika lembur, lanjutkan dengan pengajuan lembur.',
            cta: 'Buka absensi',
            tone: 'indigo',
            icon: 'bi bi-box-arrow-right',
            href: '/absensi',
        });
    }

    if (assistantState.value.actionType === 'early-checkout') {
        list.push({
            id: 'early-checkout',
            title: 'Checkout lebih cepat dari shift',
            desc: 'Kalau ada alasan, segera ajukan izin pulang cepat.',
            cta: 'Ajukan sekarang',
            tone: 'amber',
            icon: 'bi bi-send-check',
            href: '/absensi/pulang-cepat',
        });
    }

    if (countByStatus('expired') > 0) {
        list.push({
            id: 'expired',
            title: 'Ada pengajuan yang expired',
            desc: 'Periksa tanggal yang belum beres dan lakukan koreksi.',
            cta: 'Lihat detail',
            tone: 'dark',
            icon: 'bi bi-hourglass-split',
            href: '/absensi',
        });
    }

    if (!list.length) {
        list.push({
            id: 'safe',
            title: 'Tidak ada pengingat mendesak',
            desc: 'Absensi hari ini terlihat aman. Kamu bisa lanjut cek cutoff atau jadwal ke depan.',
            cta: 'Lihat cutoff',
            tone: 'emerald',
            icon: 'bi bi-check2-circle',
            href: '/absensi',
        });
    }

    return list;
});

const quickActions = computed(() => [
    { label: 'Absensi', icon: 'bi bi-fingerprint', href: '/absensi' },
    { label: 'Izin', icon: 'bi bi-send-check', href: '/absensi/izin' },
    { label: 'Cuti', icon: 'bi bi-airplane', href: '/cuti' },
    { label: 'Lembur', icon: 'bi bi-clock-history', href: '/lembur' },
    { label: 'Shift', icon: 'bi bi-calendar-week', href: '/shift' },
    { label: 'Profile', icon: 'bi bi-person-circle', href: '/user-profile' },
]);

const followUpResolved = computed(() => {
    if (props.followUpItems.length) return props.followUpItems;

    return cutoffDays.value
        .filter((d) => d.hasProblem)
        .slice(0, 8)
        .map((d) => ({
            date: d.date,
            type: statusVisual(d.primaryStatusKey).label,
            tone: d.primaryStatusTone,
            title: buildFollowUpTitle(d),
            description: buildFollowUpDescription(d),
        }));
});

const cutoffSummaryCards = computed(() => [
    { label: 'Hadir', value: countByStatus('present') },
    { label: 'Terlambat', value: countByStatus('late') },
    { label: 'Tidak Check In', value: countByStatus('no-checkin') },
    { label: 'Tidak Check Out', value: countByStatus('no-checkout') },
    { label: 'Alpha', value: countByStatus('alpha') },
    { label: 'Izin / Sakit', value: countByStatus('izin') },
    { label: 'Cuti', value: countByStatus('cuti') },
    { label: 'Expired', value: countByStatus('expired') },
    { label: 'No Shift', value: countByStatus('no-shift') },
    { label: 'Total Masalah', value: totalProblems.value },
]);

const upcomingResolved = computed(() => {
    if (props.upcomingSchedule.length) return props.upcomingSchedule.slice(0, 7);

    return [
        { date: '16 Apr', label: 'Shift Pagi', badge: 'Masuk', time: '07:00 - 15:00', tone: 'indigo' },
        { date: '17 Apr', label: 'Shift Siang', badge: 'Masuk', time: '15:00 - 23:00', tone: 'emerald' },
        { date: '18 Apr', label: 'OFF', badge: 'Libur', time: 'Tidak ada shift', tone: 'slate' },
        { date: '19 Apr', label: 'Shift Pagi', badge: 'Masuk', time: '07:00 - 15:00', tone: 'indigo' },
        { date: '20 Apr', label: 'Cuti', badge: 'Approved', time: 'Tidak bekerja', tone: 'teal' },
        { date: '21 Apr', label: 'Shift Pagi', badge: 'Masuk', time: '07:00 - 15:00', tone: 'indigo' },
        { date: '22 Apr', label: 'Shift Pagi', badge: 'Masuk', time: '07:00 - 15:00', tone: 'indigo' },
    ];
});

const secondaryModules = computed(() => {
    const summary = props.moduleSummary || {};
    return [
        {
            title: 'LMS',
            icon: 'bi bi-journal-check',
            tag: 'Belajar',
            tone: 'emerald',
            description: summary?.lms?.status || 'Lihat pelatihan aktif dan tugas yang belum selesai.',
            metrics: summary?.lms?.metrics || [
                { label: 'Progress', value: '78%' },
                { label: 'Task', value: '2' },
            ],
            href: summary?.lms?.href || '/lms',
        },
        {
            title: 'KPI',
            icon: 'bi bi-graph-up-arrow',
            tag: 'Target',
            tone: 'amber',
            description: summary?.kpi?.status || 'Cek progres target dan evaluasi berjalan.',
            metrics: summary?.kpi?.metrics || [
                { label: 'Pencapaian', value: '83%' },
                { label: 'Review', value: '1' },
            ],
            href: summary?.kpi?.href || '/kpi',
        },
        {
            title: 'Visitor / Permit',
            icon: 'bi bi-car-front',
            tag: 'Permit',
            tone: 'indigo',
            description: summary?.visitor?.status || 'Pantau permit aktif dan kendaraan yang perlu dicek.',
            metrics: summary?.visitor?.metrics || [
                { label: 'Permit aktif', value: '3' },
                { label: 'Kendaraan', value: '12' },
            ],
            href: summary?.visitor?.href || '/visitor',
        },
    ];
});

const calendarFilters = [
    { key: 'all', label: 'Semua' },
    { key: 'problem', label: 'Bermasalah' },
    { key: 'late', label: 'Terlambat' },
    { key: 'izin-cuti', label: 'Izin/Cuti' },
];

function visit(href) {
    if (!href) return;
    router.visit(href);
}

function normalizeRecord(record) {
    return {
        ...record,
        shift_label: record.shift_label || record.shift_name || record.shift || '',
        shift_time: record.shift_time || record.jam_shift || '',
    };
}

function buildAttendanceStatus(record) {
    const flags = [];

    // NO SHIFT
    if (!record.shift_label) {
        flags.push({ key: 'no-shift', label: 'No Shift', tone: 'default', problem: true });
    }

    // ALPHA (tidak ada IN & OUT)
    if (!record.check_in && !record.check_out && record.shift_label) {
        flags.push({ key: 'alpha', label: 'Alpha', tone: 'danger', problem: true });
    }

    // NO CHECKIN
    if (!record.check_in && record.check_out) {
        flags.push({ key: 'no-checkin', label: 'No IN', tone: 'danger', problem: true });
    }

    // NO CHECKOUT
    if (record.check_in && !record.check_out) {
        flags.push({ key: 'no-checkout', label: 'No OUT', tone: 'danger', problem: true });
    }

    // TELAT
    if (record.is_late) {
        flags.push({ key: 'late', label: 'Telat', tone: 'warning', problem: true });
    }

    // PULANG CEPAT
    if (record.early_out) {
        flags.push({ key: 'early-out', label: 'Pulang Cepat', tone: 'warning', problem: true });
    }

    // IZIN / CUTI
    if (record.is_leave) {
        flags.push({ key: 'leave', label: 'Izin', tone: 'info', problem: false });
    }

    // HADIR NORMAL
    if (record.check_in && record.check_out && flags.length === 0) {
        flags.push({ key: 'present', label: 'Hadir', tone: 'success', problem: false });
    }

    return flags;
}

// function resolvePrimaryStatus(flags) {
//     if (!flags.length) return 'default';

//     // PRIORITAS TERTINGGI
//     const priority = ['alpha', 'no-checkin', 'no-checkout', 'late', 'early-out', 'leave', 'present'];

//     return flags.sort((a, b) => priority.indexOf(a.key) - priority.indexOf(b.key))[0];
// }

function getStatusClass(day) {
    if (day.is_alpha) return 'status-danger';
    if (day.no_in || day.no_out) return 'status-danger';
    if (day.is_late || day.early_out) return 'status-warning';
    if (day.is_leave) return 'status-info';
    if (day.is_present) return 'status-success';
    return '';
}
function getWeekdayShort(dateString) {
    return parseDate(dateString).toLocaleDateString('id-ID', { weekday: 'short' });
}
function openDayModal(day) {
    if (!day) return;
    selectedDate.value = day.date;
    showDayModal.value = true;
}

function closeDayModal() {
    showDayModal.value = false;
}

function getStatusLabel(day) {
    if (day.is_alpha) return 'Alpha';
    if (day.no_in) return 'No IN';
    if (day.no_out) return 'No OUT';
    if (day.is_late) return 'Telat';
    if (day.early_out) return 'Pulang Cepat';
    if (day.is_leave) return 'Izin';
    if (day.is_present) return 'Hadir';
    return 'Libur';
}

function getBadgeClass(day) {
    if (day.is_alpha || day.no_in || day.no_out) return 'badge-danger';
    if (day.is_late || day.early_out) return 'badge-warning';
    if (day.is_leave) return 'badge-info';
    if (day.is_present) return 'badge-success';
    return 'badge-default';
}

function getInClass(day) {
    if (day.no_in) return 'text-danger';
    if (day.is_late) return 'text-warning';
    return '';
}

function getOutClass(day) {
    if (day.no_out) return 'text-danger';
    if (day.early_out) return 'text-warning';
    return '';
}

function getShiftClass(day) {
    if (!day.shift_name) return 'shift-off';

    if (day.shift_name.includes('Pagi')) return 'shift-pagi';
    if (day.shift_name.includes('Malam')) return 'shift-malam';

    return 'shift-default';
}

function openDateFromFollow(date) {
    selectedDate.value = date;
    showDayModal.value = true;
}

function handlePowerCardClick(key) {
    if (key === 'problem') {
        activeCalendarFilter.value = 'problem';
        return;
    }
    if (key === 'late') {
        activeCalendarFilter.value = 'late';
        return;
    }
    if (key === 'leave') {
        activeCalendarFilter.value = 'izin-cuti';
        return;
    }
    if (key === 'expired') {
        const target = cutoffDays.value.find((d) => d.primaryStatusKey === 'expired');
        if (target) openDayModal(target);
    }
}

function handleAlertClick(item) {
    if (!item?.date) return;

    const targetDay = cutoffDays.value.find((d) => d.date === item.date);

    if (targetDay) {
        openDayModal(targetDay);
    }
}

function parseDate(value) {
    const [year, month, day] = String(value).split('-').map(Number);
    return new Date(year, month - 1, day);
}

function toYmd(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function normalizeDateString(value) {
    if (!value) return '';
    if (value instanceof Date) return toYmd(value);
    return String(value).slice(0, 10);
}

function isSameDate(a, b) {
    return toYmd(a) === toYmd(b);
}

function formatLongDate(date) {
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}

function getDayNumber(dateString) {
    return parseDate(dateString).getDate();
}

function getMonthShort(dateString) {
    return parseDate(dateString).toLocaleDateString('id-ID', { month: 'short' });
}

function compactLabel(key) {
    const map = {
        present: 'Hadir',
        late: 'Telat',
        'no-checkin': 'No IN',
        'no-checkout': 'No OUT',
        alpha: 'Alpha',
        izin: 'Izin',
        cuti: 'Cuti',
        'no-shift': 'No Shift',
        expired: 'Expired',
    };
    return map[key] || 'Hadir';
}

function resolvePrimaryStatus(record) {
    const status = String(record?.status || '').toLowerCase();
    if (record?.expired) return 'expired';
    if (record?.alpha || status.includes('alpha')) return 'alpha';
    if (record?.no_shift || status.includes('no shift') || status.includes('off')) return 'no-shift';
    if (record?.cuti || status.includes('cuti')) return 'cuti';
    if (record?.izin || record?.sakit || status.includes('izin') || status.includes('sakit')) return 'izin';
    if (record?.no_checkin || status.includes('no checkin') || status.includes('tidak check in')) return 'no-checkin';
    if (record?.no_checkout || status.includes('no checkout') || status.includes('tidak check out'))
        return 'no-checkout';
    if (record?.late || status.includes('terlambat')) return 'late';
    return 'present';
}

function buildFlags(record) {
    const flags = [];
    if (record?.late) flags.push({ key: 'late', label: 'Terlambat', tone: 'amber', problem: false });
    if (record?.expired) flags.push({ key: 'expired', label: 'Expired', tone: 'redline', problem: true });
    if (record?.no_checkin) flags.push({ key: 'no-checkin', label: 'No Check In', tone: 'rose', problem: true });
    if (record?.no_checkout) flags.push({ key: 'no-checkout', label: 'No Check Out', tone: 'orange', problem: true });
    if (record?.alpha) flags.push({ key: 'alpha', label: 'Alpha', tone: 'dark', problem: true });
    if (record?.cuti) flags.push({ key: 'cuti', label: 'Cuti', tone: 'teal', problem: false });
    if (record?.izin || record?.sakit) flags.push({ key: 'izin', label: 'Izin', tone: 'violet', problem: false });
    if (record?.no_shift) flags.push({ key: 'no-shift', label: 'No Shift', tone: 'slate', problem: true });
    return flags;
}

function statusVisual(key) {
    const map = {
        present: { label: 'Hadir Lengkap', tone: 'emerald', icon: 'bi bi-check2-circle' },
        late: { label: 'Terlambat', tone: 'amber', icon: 'bi bi-alarm' },
        'no-checkin': { label: 'Tidak Check In', tone: 'rose', icon: 'bi bi-box-arrow-in-right' },
        'no-checkout': { label: 'Tidak Check Out', tone: 'orange', icon: 'bi bi-box-arrow-right' },
        alpha: { label: 'Alpha', tone: 'dark', icon: 'bi bi-person-x' },
        izin: { label: 'Izin / Sakit', tone: 'violet', icon: 'bi bi-file-earmark-medical' },
        cuti: { label: 'Cuti', tone: 'teal', icon: 'bi bi-airplane' },
        'no-shift': { label: 'Tidak Ada Shift', tone: 'slate', icon: 'bi bi-dash-circle' },
        expired: { label: 'Pengajuan Expired', tone: 'redline', icon: 'bi bi-hourglass-split' },
    };
    return map[key] || map.present;
}

function countByStatus(key) {
    return cutoffDays.value.filter((d) => d.primaryStatusKey === key).length;
}

function buildFollowUpTitle(day) {
    return `${formatLongDate(day.rawDate)} • ${statusVisual(day.primaryStatusKey).label}`;
}

function buildFollowUpDescription(day) {
    if (day.primaryStatusKey === 'no-checkin') return 'Tidak ada check-in tercatat pada tanggal ini.';
    if (day.primaryStatusKey === 'no-checkout') return 'Check-out belum tercatat pada tanggal ini.';
    if (day.primaryStatusKey === 'alpha') return 'Tidak ada kehadiran dan tidak ada pengajuan pada tanggal ini.';
    if (day.primaryStatusKey === 'expired') return 'Ada pengajuan yang sudah lewat batas tindak lanjut.';
    if (day.primaryStatusKey === 'no-shift') return 'Tanggal ini tidak memiliki shift aktif / OFF.';
    if (day.primaryStatusKey === 'late') return 'Terdapat keterlambatan pada tanggal ini.';
    if (day.primaryStatusKey === 'izin') return 'Tanggal ini tercatat izin atau sakit.';
    if (day.primaryStatusKey === 'cuti') return 'Tanggal ini tercatat cuti.';
    return 'Periksa detail absensi pada tanggal ini.';
}

function buildAssistantState(day) {
    if (!day) {
        return {
            tone: 'indigo',
            icon: 'bi bi-stars',
            badge: 'Info',
            shortTitle: 'Belum ada data hari ini',
            shortDescription: 'Silakan cek data absensi kamu.',
            message: 'Aku belum menemukan data absensi hari ini.',
            actionType: 'default',
        };
    }

    const nowMinutes = getMinutesFromDate(today);
    const shiftStartMinutes = getMinutesFromTime(day.shiftTime?.split(' - ')[0] || '07:00');
    const shiftEndMinutes = getMinutesFromTime(day.shiftTime?.split(' - ')[1] || '15:00');
    const earlyCheckout =
        day.checkOut &&
        day.checkOut !== '-' &&
        shiftEndMinutes &&
        getMinutesFromTime(day.checkOut) < shiftEndMinutes - 5;
    const lateCheckinMissing =
        (!day.checkIn || day.checkIn === '-') &&
        shiftStartMinutes &&
        nowMinutes > shiftStartMinutes + 10 &&
        day.primaryStatusKey !== 'izin' &&
        day.primaryStatusKey !== 'cuti' &&
        day.primaryStatusKey !== 'no-shift';
    const missingCheckout =
        (!day.checkOut || day.checkOut === '-') &&
        day.checkIn &&
        day.checkIn !== '-' &&
        shiftEndMinutes &&
        nowMinutes > shiftEndMinutes + 10;

    if (lateCheckinMissing) {
        return {
            tone: 'rose',
            icon: 'bi bi-exclamation-octagon',
            badge: 'Gawat',
            shortTitle: 'Kamu terlambat check-in',
            shortDescription: 'Waktu masuk sudah lewat dan check-in belum tercatat.',
            message:
                'Gawat, kamu belum check-in padahal waktu masuk sudah lewat. Kalau memang ada kendala, segera ajukan izin terlambat ya.',
            actionType: 'late-checkin',
        };
    }

    if (earlyCheckout) {
        return {
            tone: 'amber',
            icon: 'bi bi-box-arrow-right',
            badge: 'Perhatian',
            shortTitle: 'Checkout terlalu awal',
            shortDescription: 'Jam pulangmu lebih cepat dari jadwal shift.',
            message:
                'Sepertinya kamu pulang lebih cepat dari jadwal. Kalau memang ada alasan, segera ajukan izin pulang cepat ya.',
            actionType: 'early-checkout',
        };
    }

    if (missingCheckout) {
        return {
            tone: 'indigo',
            icon: 'bi bi-alarm',
            badge: 'Pengingat',
            shortTitle: 'Kamu belum check-out',
            shortDescription: 'Jam pulang sudah lewat tapi checkout belum tercatat.',
            message:
                'Jangan lupa check-out ya. Kalau kamu masih bekerja lembur, jangan lupa minta atasanmu mengajukan lembur juga.',
            actionType: 'missing-checkout',
        };
    }

    if (day.primaryStatusKey === 'late') {
        return {
            tone: 'amber',
            icon: 'bi bi-alarm',
            badge: 'Terlambat',
            shortTitle: 'Ada keterlambatan hari ini',
            shortDescription: 'Check-in kamu tercatat lebih lambat dari jadwal.',
            message:
                'Hari ini check-in kamu tercatat terlambat. Pastikan keterlambatanmu sudah sesuai dan ajukan penjelasan jika diperlukan.',
            actionType: 'default',
        };
    }

    if (day.primaryStatusKey === 'izin' || day.primaryStatusKey === 'cuti') {
        return {
            tone: 'emerald',
            icon: 'bi bi-check2-circle',
            badge: 'Aman',
            shortTitle: 'Status hari ini sudah tercatat',
            shortDescription: 'Hari ini kamu memiliki status izin / cuti yang valid.',
            message:
                'Tenang, status hari ini sudah tercatat dengan benar. Kamu bisa fokus memantau periode cutoff kamu dari kalender di bawah.',
            actionType: 'default',
        };
    }

    if (day.primaryStatusKey === 'present') {
        return {
            tone: 'emerald',
            icon: 'bi bi-stars',
            badge: 'Bagus',
            shortTitle: 'Absensi hari ini terlihat aman',
            shortDescription: 'Check-in dan status hari ini terlihat baik.',
            message:
                'Bagus, absensi hari ini terlihat aman. Sekarang cek juga periode cutoff kamu, siapa tahu masih ada tanggal lain yang perlu diperbaiki.',
            actionType: 'default',
        };
    }

    return {
        tone: 'indigo',
        icon: 'bi bi-stars',
        badge: 'Info',
        shortTitle: 'Pantau absensi hari ini',
        shortDescription: 'Cek status kehadiranmu dan periksa periode cutoff aktif.',
        message:
            'Aku bantu pantau absensi kamu hari ini. Lihat juga kalender cutoff di bawah untuk tahu apakah ada tanggal lain yang masih bermasalah.',
        actionType: 'default',
    };
}

function getMinutesFromTime(value) {
    if (!value || value === '-') return null;
    const [hours, minutes] = String(value).split(':').map(Number);
    if (Number.isNaN(hours) || Number.isNaN(minutes)) return null;
    return hours * 60 + minutes;
}

function getMinutesFromDate(value) {
    return value.getHours() * 60 + value.getMinutes();
}

function timelineIcon(kind) {
    if (kind === 'checkin') return 'bi bi-box-arrow-in-right';
    if (kind === 'checkout') return 'bi bi-box-arrow-right';
    return 'bi bi-fingerprint';
}

function buildFallbackTimeline(record) {
    const timeline = [];
    if (record?.check_in && record.check_in !== '-') {
        timeline.push({ label: 'Check In', time: record.check_in, kind: 'checkin' });
    }
    if (record?.check_out && record.check_out !== '-') {
        timeline.push({ label: 'Check Out', time: record.check_out, kind: 'checkout' });
    }
    return timeline;
}

function demoAttendanceRecords() {
    return [
        {
            date: '2026-03-16',
            status: 'present',
            check_in: '07:01',
            check_out: '15:02',
            shift_label: 'Shift Pagi',
            shift_time: '07:00 - 15:00',
            note: 'Hadir lengkap',
            timeline: [
                { label: 'Check In', time: '07:01', kind: 'checkin' },
                { label: 'Check Out', time: '15:02', kind: 'checkout' },
            ],
        },
        {
            date: '2026-03-17',
            status: 'late',
            late: true,
            check_in: '07:18',
            check_out: '15:05',
            shift_label: 'Shift Pagi',
            shift_time: '07:00 - 15:00',
            note: 'Terlambat 18 menit',
            timeline: [
                { label: 'Check In', time: '07:18', kind: 'checkin' },
                { label: 'Check Out', time: '15:05', kind: 'checkout' },
            ],
        },
        {
            date: '2026-03-21',
            status: 'no checkin',
            no_checkin: true,
            check_in: '-',
            check_out: '15:00',
            shift_label: 'Shift Pagi',
            shift_time: '07:00 - 15:00',
            note: 'Tidak ada check-in',
            timeline: [{ label: 'Checktime', time: '15:00', kind: 'finger' }],
        },
        {
            date: '2026-03-25',
            status: 'izin',
            izin: true,
            check_in: '-',
            check_out: '-',
            shift_label: 'Izin',
            shift_time: '-',
            note: 'Izin disetujui',
        },
        {
            date: '2026-03-28',
            status: 'cuti',
            cuti: true,
            check_in: '-',
            check_out: '-',
            shift_label: 'Cuti Tahunan',
            shift_time: '-',
            note: 'Cuti approved',
        },
        {
            date: '2026-04-01',
            status: 'alpha',
            alpha: true,
            check_in: '-',
            check_out: '-',
            shift_label: 'Shift Pagi',
            shift_time: '07:00 - 15:00',
            note: 'Tidak masuk / alpha',
        },
        {
            date: '2026-04-03',
            status: 'late',
            late: true,
            expired: true,
            check_in: '07:22',
            check_out: '15:02',
            shift_label: 'Shift Pagi',
            shift_time: '07:00 - 15:00',
            note: 'Pengajuan terlambat expired',
            timeline: [
                { label: 'Check In', time: '07:22', kind: 'checkin' },
                { label: 'Check Out', time: '15:02', kind: 'checkout' },
            ],
        },
        {
            date: '2026-04-07',
            status: 'off',
            no_shift: true,
            check_in: '-',
            check_out: '-',
            shift_label: '',
            shift_time: '-',
            note: 'Tidak ada shift',
        },
        {
            date: '2026-04-10',
            status: 'present',
            check_in: '07:00',
            check_out: '15:01',
            shift_label: 'Shift Pagi',
            shift_time: '07:00 - 15:00',
            note: 'Hadir lengkap',
            timeline: [
                { label: 'Check In', time: '07:00', kind: 'checkin' },
                { label: 'Check Out', time: '15:01', kind: 'checkout' },
            ],
        },
        {
            date: '2026-04-15',
            status: 'no checkout',
            no_checkout: true,
            check_in: '07:03',
            check_out: '-',
            shift_label: 'Shift Pagi',
            shift_time: '07:00 - 15:00',
            note: 'Tidak ada check-out',
            timeline: [{ label: 'Check In', time: '07:03', kind: 'checkin' }],
        },
    ];
}
</script>

<style scoped>
.bi {
    display: inline-table;
}

.status-danger {
    border-color: #fecaca;
    background: #fff1f2;
}

.status-warning {
    border-color: #fde68a;
    background: #fffbeb;
}

.status-success {
    border-color: #bbf7d0;
}

.status-info {
    border-color: #c7d2fe;
    background: #eef2ff;
}

.badge-danger {
    background: #fee2e2;
    color: #b91c1c;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
}

.text-danger {
    color: #dc2626;
    font-weight: 700;
}

.text-warning {
    color: #d97706;
    font-weight: 700;
}
.badge-success {
    background: #dcfce7;
    color: #166534;
}

.badge-info {
    background: #e0e7ff;
    color: #3730a3;
}

.shift-pagi {
    background: #eff6ff;
    color: #1d4ed8;
}

.shift-malam {
    background: #1e293b;
    color: white;
}

.shift-off {
    background: #f1f5f9;
    color: #64748b;
}
.calendar-card {
    padding: 14px;
    border-radius: 1rem;
    border: 1px solid #e2e8f0;
    background: white;
    height: 130px;

    display: flex;
    flex-direction: column;
    justify-content: space-between;

    transition: all 0.2s ease;
    cursor: pointer;
}

.calendar-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.06);
}

/* HEADER */
.card-header {
    display: flex;
    justify-content: space-between;
}

.date {
    font-weight: 800;
    font-size: 18px;
}

/* BADGE */
.status-badge {
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 6px;
    font-weight: 700;
}

/* SHIFT */
.card-shift span {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 6px;
}

/* TIME */
.card-time {
    font-size: 11px;
}

.time-row {
    display: flex;
    justify-content: space-between;
}
.hero-mini-trend__chart {
    margin-top: 0.5rem;
    width: 100%;
}
.lms-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.lms-status {
    font-size: 0.6rem;
    font-weight: 700;
    color: #64748b;
}

.lms-add-btn {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    border: none;
    background: #d9f99d;
    color: #365314;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* LIST */
.lms-course-list {
    margin-top: 0.8rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

/* ITEM */
.lms-course-item {
    display: grid;
    grid-template-columns: 1fr 100px 60px;
    align-items: center;
    gap: 0.6rem;

    padding: 0.6rem;
    border-radius: 0.9rem;

    background: #f8fafc;
    transition: all 0.2s ease;
}

.lms-course-item:hover {
    background: #eef2ff;
}

/* LEFT */
.lms-course-item__left {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.lms-course-item__icon {
    width: 36px;
    height: 36px;
    border-radius: 0.7rem;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
}

.lms-course-item__title {
    font-size: 0.75rem;
    font-weight: 800;
}

.lms-course-item__author {
    font-size: 0.6rem;
    color: #64748b;
}

/* CENTER */
.lms-course-item__center {
    text-align: left;
}

.lms-course-item__remaining {
    font-size: 0.55rem;
    color: #94a3b8;
}

.lms-course-item__time {
    font-size: 0.65rem;
    font-weight: 800;
}

/* PROGRESS */
.progress-circle {
    position: relative;
    width: 42px;
    height: 42px;
}

.progress-circle svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.circle-bg {
    fill: none;
    stroke: #e5e7eb;
    stroke-width: 3;
}

.action-item__action.btn-follow {
    font-size: 0.65rem;
    font-weight: 700;
    color: #2563eb;
    background: #dbeafe;

    padding: 5px 10px;
    border-radius: 999px;

    transition: 0.2s;
}

.badge-follow {
    background: #dbeafe;
    color: #2563eb;
    font-size: 0.6rem;
    padding: 4px 8px;
    border-radius: 999px;
    font-weight: 700;
}

.circle-fill {
    fill: none;
    stroke: #22c55e;
    stroke-width: 3;
    stroke-linecap: round;
}

.progress-circle span {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    font-weight: 800;
}
/* ACTION CENTER */
.action-center-list {
    margin-top: 0.8rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
/* LIST */
.action-center-list {
    margin-top: 0.8rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

/* ITEM */
.action-item {
    display: flex;
    align-items: center;
    gap: 0.8rem;

    padding: 0.6rem;
    border-radius: 1rem;

    transition: all 0.2s ease;
    cursor: pointer;
}

.action-item:hover {
    background: #f8fafc;
}
.action-item__date {
    width: 56px;
    height: 56px;
    border-radius: 0.9rem;
    background: white;
    border: 1px solid #e2e8f0;

    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;

    box-shadow: 0 6px 14px rgba(0, 0, 0, 0.06);
}

.action-item__date .day {
    font-size: 1rem;
    font-weight: 900;
    line-height: 1;
}

.action-item__date .meta {
    font-size: 0.55rem;
    color: #64748b;
    font-weight: 700;
    margin-top: 2px;
}

.action-item__date .month {
    font-size: 0.55rem;
    color: #64748b;
    font-weight: 700;
}

/* CONTENT */
.action-item__content {
    flex: 1;
}

.action-item__title {
    font-size: 0.75rem;
    font-weight: 800;
    color: #0f172a;
}

.action-item__desc {
    font-size: 0.65rem;
    color: #64748b;
    margin-top: 2px;
}

/* ACTION RIGHT */
.action-item__action {
    display: flex;
    align-items: center;
}

/* ALERT BADGE */
.badge-alert {
    background: #fee2e2;
    color: #dc2626;
    font-size: 0.6rem;
    padding: 4px 8px;
    border-radius: 999px;
    font-weight: 700;
}

/* BUTTON */
.btn-follow {
    font-size: 0.65rem;
    font-weight: 700;
    color: #2563eb;

    padding: 5px 10px;
    border-radius: 999px;

    transition: 0.2s;
}

.btn-follow:hover {
    background: #eff6ff;
}

/* LMS SIDEBAR */
.lms-side-item {
    margin-bottom: 0.6rem;
}

.lms-side-item__top {
    display: flex;
    justify-content: space-between;
    font-size: 0.65rem;
    font-weight: 800;
}

.lms-side-item__bar {
    height: 4px;
    background: #e5e7eb;
    border-radius: 999px;
    margin-top: 3px;
}

.lms-side-item__fill {
    height: 100%;
    background: #3b82f6;
    border-radius: 999px;
}

/* SHIFT LABEL */
.cutoff-day-cell__shift {
    font-size: 0.5rem;
    font-weight: 800;
    color: #6366f1;
}

/* COMPACT */
.shell-card--inner {
    padding: 0.85rem;
}

.section-title {
    font-size: 0.9rem;
}

.section-label {
    font-size: 0.6rem;
}

.hero-hcis-section {
    margin-top: 1rem;
    padding: 0.8rem;
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(6px);
}

.hero-hcis-section__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.6rem;
}

.hero-hcis-section__label {
    font-size: 0.6rem;
    font-weight: 900;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.7);
}

.hero-hcis-section__badge {
    font-size: 0.6rem;
    font-weight: 800;
    padding: 0.2rem 0.5rem;
    border-radius: 999px;
    background: rgba(244, 63, 94, 0.2);
}

.hero-hcis-section__grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 0.2rem;
}

.hero-hcis-box {
    padding: 0.3rem;
    border-radius: 0.7rem;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.15);
    background: rgba(255, 255, 255, 0.08);
}

.hero-hcis-box strong {
    display: block;
    font-size: 0.75rem;
    font-weight: 900;
    color: #fff;
}

.hero-hcis-box span {
    font-size: 0.55rem;
    opacity: 0.7;
}

.hero-hcis-box.is-emerald {
    background: rgba(16, 185, 129, 0.25);
}

.hero-hcis-box.is-amber {
    background: rgba(245, 158, 11, 0.25);
}

.hero-hcis-box.is-rose {
    background: rgba(244, 63, 94, 0.25);
}

.hero-hcis-box.is-violet {
    background: rgba(139, 92, 246, 0.25);
}

.hero-hcis-compact {
    margin-top: 0.7rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.hero-hcis-compact__row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.4rem;
}

.hero-hcis-compact__item {
    padding: 0.55rem;
    border-radius: 0.7rem;
    background: rgba(255, 255, 255, 0.1);
    text-align: center;
}

.hero-hcis-compact__item strong {
    display: block;
    font-size: 0.75rem;
    font-weight: 900;
    color: #fff;
}

.hero-hcis-compact__item span {
    font-size: 0.55rem;
    opacity: 0.7;
}

.hero-hcis-compact__item.is-emerald {
    background: rgba(16, 185, 129, 0.2);
}
.hero-hcis-compact__item.is-amber {
    background: rgba(245, 158, 11, 0.2);
}
.hero-hcis-compact__item.is-rose {
    background: rgba(244, 63, 94, 0.2);
}
.hero-hcis-compact__item.is-violet {
    background: rgba(139, 92, 246, 0.2);
}

.attendance-home-v4,
.attendance-home-v4 * {
    box-sizing: border-box;
}

.attendance-home-v4 {
    min-height: 100%;
    padding: 1rem;
}

.attendance-home-v4__container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.shell-btn {
    border: 0;
    outline: 0;
    background: transparent;
    font: inherit;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
}

.shell-card {
    border: 1px solid rgba(226, 232, 240, 0.72);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.52) 0%, rgba(248, 250, 252, 0.52) 100%);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-radius: 1.25rem;
    box-shadow:
        0 16px 34px rgba(15, 23, 42, 0.06),
        inset 0 1px 0 rgba(255, 255, 255, 0.65);
}

.shell-card--inner {
    padding: 1rem;
}

.section-label {
    color: #94a3b8;
    font-size: 0.63rem;
    line-height: 1;
    font-weight: 950;
    text-transform: uppercase;
    letter-spacing: 0.16em;
}

.section-title {
    margin: 0.45rem 0 0;
    color: #0f172a;
    font-size: 1rem;
    font-weight: 900;
    letter-spacing: -0.02em;
}

.section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.section-head--tight {
    align-items: flex-start;
}

/* HERO */
.assistant-hero {
    position: relative;
    overflow: hidden;
    border-radius: 1.6rem;
    padding: 1.15rem;
    color: #fff;
    background: linear-gradient(135deg, #4338ca 0%, #4f46e5 40%, #3b82f6 100%);
    box-shadow:
        0 24px 48px rgba(79, 70, 229, 0.18),
        0 10px 24px rgba(15, 23, 42, 0.12);
}

.assistant-hero.is-rose {
    background: linear-gradient(135deg, #be123c 0%, #e11d48 42%, #f43f5e 100%);
}

.assistant-hero.is-amber {
    background: linear-gradient(135deg, #b45309 0%, #d97706 42%, #f59e0b 100%);
}

.assistant-hero.is-emerald {
    background: linear-gradient(135deg, #047857 0%, #059669 42%, #10b981 100%);
}

.assistant-hero__orb {
    position: absolute;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
    filter: blur(6px);
    pointer-events: none;
}

.assistant-hero__orb--one {
    width: 16rem;
    height: 16rem;
    top: -6rem;
    right: -4rem;
}

.assistant-hero__orb--two {
    width: 10rem;
    height: 10rem;
    bottom: -3rem;
    left: 38%;
}

.assistant-hero__content {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: minmax(0, 0.8fr) minmax(300px, 0.78fr);
    gap: 1rem;
}

.assistant-hero__topline {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.6rem;
}

.assistant-badge,
.assistant-period-pill {
    min-height: 2rem;
    padding: 0 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.18);
    font-size: 0.66rem;
    font-weight: 850;
}

.assistant-hero__title {
    margin: 0.8rem 0 0;
    font-size: 2.6rem !important;
    line-height: 1.05;
    font-weight: 950;
    letter-spacing: -0.04em;
}

.assistant-hero__message {
    margin: 0.55rem 0 0;
    color: rgba(255, 255, 255, 0.88);
    font-size: 0.84rem;
    line-height: 1.6;
    font-weight: 600;
    max-width: 50rem;
}

.assistant-hero__chips {
    margin-top: 1rem;
    display: grid;
    grid-template-columns: 1.1fr 1fr 1fr;
    gap: 0.75rem;
}

.assistant-chip {
    min-height: 5.9rem;
    padding: 0.9rem;
    border-radius: 1.25rem;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.14);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

.assistant-chip__label {
    display: block;
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(255, 255, 255, 0.72);
}

.assistant-chip__value {
    display: block;
    margin-top: 0.45rem;
    font-size: 0.94rem;
    font-weight: 900;
    line-height: 1.2;
}

.assistant-chip__meta {
    display: block;
    margin-top: 0.3rem;
    color: rgba(255, 255, 255, 0.82);
    font-size: 0.67rem;
    line-height: 1.45;
    font-weight: 650;
}

.assistant-hero__actions {
    margin-top: 1rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
}

.hero-issue-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.8rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    color: #fff;
    text-decoration: none;
    font-size: 0.75rem;
    transition: all 0.2s ease;
}

.hero-issue-badge:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateY(-1px);
}

.hero-issue-badge .text-warning {
    color: #fef08a;
    font-size: 0.85rem;
}

.hero-action-btn {
    min-height: 2.95rem;
    padding: 0 1rem;
    border-radius: 1rem;
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    font-weight: 850;
    background: rgba(255, 255, 255, 0.98);
    color: #312e81;
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
}

.hero-action-btn.is-secondary {
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.14);
    box-shadow: none;
}

.hero-status-card {
    height: 100%;
    padding: 1rem;
    border-radius: 1.25rem;
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

.hero-status-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.hero-status-card__icon {
    width: 3rem;
    height: 3rem;
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.16);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.hero-status-card__badge {
    padding: 0.34rem 0.58rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.16);
    font-size: 0.56rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.hero-status-card__title {
    margin-top: 1rem;
    font-size: 1rem;
    font-weight: 900;
    line-height: 1.2;
}

.hero-status-card__desc {
    margin-top: 0.4rem;
    color: rgba(255, 255, 255, 0.84);
    font-size: 0.72rem;
    line-height: 1.5;
    font-weight: 650;
}

.hero-status-grid {
    margin-top: 1rem;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.7rem;
}

.hero-status-tile {
    padding: 0.8rem;
    border-radius: 0.95rem;
    background: rgba(255, 255, 255, 0.12);
}

.hero-status-tile__label {
    display: block;
    color: rgba(255, 255, 255, 0.72);
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
}

.hero-status-tile__value {
    display: block;
    margin-top: 0.35rem;
    font-size: 0.85rem;
    font-weight: 900;
}

/* POWER STRIP */
.power-strip {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.85rem;
}

.power-card {
    min-height: 6.2rem;
    padding: 0.9rem;
    border-radius: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    text-align: left;
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

.power-card.is-rose {
    background: linear-gradient(135deg, #fff1f2, #ffe4e6);
    color: #be123c;
}
.power-card.is-indigo {
    background: linear-gradient(135deg, #eef2ff, #e0e7ff);
    color: #4338ca;
}
.power-card.is-amber {
    background: linear-gradient(135deg, #fffbeb, #fef3c7);
    color: #b45309;
}
.power-card.is-violet {
    background: linear-gradient(135deg, #f5f3ff, #ede9fe);
    color: #6d28d9;
}

.power-card__icon {
    width: 2.6rem;
    height: 2.6rem;
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.7);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1rem;
}

.power-card__value {
    font-size: 1rem;
    font-weight: 950;
    line-height: 1;
}

.power-card__label {
    margin-top: 0.25rem;
    font-size: 0.7rem;
    font-weight: 800;
}

.power-card__desc {
    margin-top: 0.2rem;
    font-size: 0.62rem;
    line-height: 1.4;
    font-weight: 650;
}

/* WORKSPACE */
.workspace-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(300px, 0.8fr);
    gap: 1rem;
}

.assistant-alert-list {
    margin-top: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
}

.assistant-alert-row {
    width: 100%;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    gap: 0.8rem;
    align-items: center;
    padding: 0.85rem;
    border-radius: 1rem;
    background: rgba(248, 250, 252, 0.92);
    text-align: left;
}

.assistant-alert-row.is-rose {
    border: 1px solid rgba(244, 63, 94, 0.18);
}
.assistant-alert-row.is-indigo {
    border: 1px solid rgba(79, 70, 229, 0.18);
}
.assistant-alert-row.is-amber {
    border: 1px solid rgba(245, 158, 11, 0.18);
}
.assistant-alert-row.is-dark {
    border: 1px solid rgba(15, 23, 42, 0.12);
}
.assistant-alert-row.is-emerald {
    border: 1px solid rgba(16, 185, 129, 0.18);
}

.assistant-alert-row__icon {
    width: 2.4rem;
    height: 2.4rem;
    border-radius: 0.95rem;
    background: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #334155;
}

.assistant-alert-row__title {
    color: #0f172a;
    font-size: 0.74rem;
    font-weight: 850;
}

.assistant-alert-row__desc {
    margin-top: 0.25rem;
    color: #64748b;
    font-size: 0.66rem;
    line-height: 1.45;
    font-weight: 650;
}

.assistant-alert-row__cta {
    color: #4f46e5;
    font-size: 0.64rem;
    font-weight: 850;
    white-space: nowrap;
}

.quick-action-grid {
    margin-top: 1rem;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.7rem;
}

.quick-action-btn {
    min-height: 4rem;
    padding: 0.8rem;
    border-radius: 1rem;
    background: rgba(248, 250, 252, 0.92);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
}

.quick-action-btn__icon {
    width: 2rem;
    height: 2rem;
    border-radius: 0.7rem;
    background: #e0e7ff;
    color: #4338ca;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.quick-action-btn__label {
    color: #334155;
    font-size: 0.68rem;
    font-weight: 800;
}

/* MAIN */
.mt-3 {
    margin-top: 1rem;
}

.dashboard-main-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.38fr) minmax(320px, 0.72fr);
    gap: 1rem;
}

.dashboard-side-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.cutoff-card__head-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
}

.calendar-filter-btn {
    min-height: 2rem;
    padding: 0 0.7rem;
    border-radius: 999px;
    background: #f8fafc;
    color: #64748b;
    font-size: 0.62rem;
    font-weight: 850;
}

.calendar-filter-btn.is-active {
    background: #e0e7ff;
    color: #4338ca;
}

.cutoff-summary-row {
    margin-top: 1rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.cutoff-summary-pill {
    min-height: 2rem;
    padding: 0 0.7rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    font-size: 0.62rem;
    font-weight: 850;
}

.cutoff-summary-pill.is-emerald {
    background: #dcfce7;
    color: #15803d;
}
.cutoff-summary-pill.is-amber {
    background: #fef3c7;
    color: #b45309;
}
.cutoff-summary-pill.is-rose {
    background: #ffe4e6;
    color: #be123c;
}
.cutoff-summary-pill.is-violet {
    background: #ede9fe;
    color: #6d28d9;
}

.cutoff-calendar-grid {
    margin-top: 1rem;
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.5rem;
}

.cutoff-calendar-grid__weekday {
    color: #64748b;
    font-size: 0.64rem;
    font-weight: 900;
    text-align: center;
    padding: 0.28rem 0;
}

.cutoff-day-cell {
    min-height: 6.85rem;
    padding: 0.55rem;
    border-radius: 0.95rem;
    border: 1px solid rgba(226, 232, 240, 0.82);
    background: #fff;
    display: flex;
    flex-direction: column;
    gap: 0.32rem;
    transition:
        transform 150ms ease,
        box-shadow 150ms ease;
}

.cutoff-day-cell:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
}

.cutoff-day-cell.is-today {
    outline: 2px solid rgba(79, 70, 229, 0.18);
}

.cutoff-day-cell.has-problem {
    border-color: rgba(239, 68, 68, 0.18);
}

.cutoff-day-cell.is-emerald {
    background: linear-gradient(180deg, #ffffff, #f0fdf4);
}
.cutoff-day-cell.is-amber {
    background: linear-gradient(180deg, #ffffff, #fffbeb);
}
.cutoff-day-cell.is-rose {
    background: linear-gradient(180deg, #ffffff, #fff1f2);
}
.cutoff-day-cell.is-orange {
    background: linear-gradient(180deg, #ffffff, #fff7ed);
}
.cutoff-day-cell.is-dark {
    background: linear-gradient(180deg, #ffffff, #f8fafc);
}
.cutoff-day-cell.is-violet {
    background: linear-gradient(180deg, #ffffff, #f5f3ff);
}
.cutoff-day-cell.is-teal {
    background: linear-gradient(180deg, #ffffff, #f0fdfa);
}
.cutoff-day-cell.is-slate {
    background: linear-gradient(180deg, #ffffff, #f8fafc);
}
.cutoff-day-cell.is-redline {
    background: linear-gradient(180deg, #ffffff, #fef2f2);
}

.cutoff-day-cell__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.35rem;
}

.cutoff-day-cell__date {
    color: #0f172a;
    font-size: 0.92rem;
    font-weight: 950;
    line-height: 1;
}

.cutoff-day-cell__dot {
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 999px;
    background: #ef4444;
    flex-shrink: 0;
}

.cutoff-day-cell__icon {
    color: #0f172a;
    font-size: 0.82rem;
}

.cutoff-day-cell__label {
    color: #334155;
    font-size: 0.58rem;
    line-height: 1.25;
    font-weight: 850;
}

.cutoff-day-cell__times {
    margin-top: auto;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.cutoff-day-cell__times span {
    min-height: 1.2rem;
    padding: 0 0.34rem;
    border-radius: 0.5rem;
    background: rgba(248, 250, 252, 0.94);
    color: #334155;
    display: inline-flex;
    align-items: center;
    font-size: 0.5rem;
    font-weight: 850;
}

.cutoff-day-cell__times span.is-empty {
    background: #fff1f2;
    color: #e11d48;
}

.follow-list-compact,
.upcoming-list-compact {
    margin-top: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.follow-compact-row {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 0.8rem;
    align-items: center;
    padding: 0.72rem;
    border-radius: 0.95rem;
    background: rgba(248, 250, 252, 0.92);
    text-align: left;
}

.follow-compact-row__date {
    width: 3.4rem;
    text-align: center;
    padding: 0.5rem 0.3rem;
    border-radius: 0.9rem;
    background: #fff;
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.05);
}

.follow-compact-row__day {
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 900;
    line-height: 1;
}

.follow-compact-row__month {
    margin-top: 0.2rem;
    color: #94a3b8;
    font-size: 0.56rem;
    font-weight: 800;
    text-transform: uppercase;
}

.follow-compact-row__title,
.upcoming-compact-row__title {
    color: #0f172a;
    font-size: 0.72rem;
    font-weight: 850;
}

.follow-compact-row__desc,
.upcoming-compact-row__meta {
    margin-top: 0.25rem;
    color: #64748b;
    font-size: 0.64rem;
    line-height: 1.45;
    font-weight: 650;
}

.upcoming-compact-row {
    display: grid;
    grid-template-columns: 4rem minmax(0, 1fr) auto;
    gap: 0.75rem;
    align-items: center;
    padding: 0.72rem;
    border-radius: 0.95rem;
    background: rgba(248, 250, 252, 0.92);
}

.upcoming-compact-row__date {
    color: #0f172a;
    font-size: 0.68rem;
    font-weight: 900;
}

.upcoming-compact-row__badge {
    padding: 0.24rem 0.48rem;
    border-radius: 999px;
    font-size: 0.52rem;
    font-weight: 900;
    text-transform: uppercase;
}

.upcoming-compact-row__badge.is-indigo {
    background: #e0e7ff;
    color: #4338ca;
}
.upcoming-compact-row__badge.is-emerald {
    background: #dcfce7;
    color: #15803d;
}
.upcoming-compact-row__badge.is-slate {
    background: #f1f5f9;
    color: #475569;
}
.upcoming-compact-row__badge.is-teal {
    background: #ccfbf1;
    color: #0f766e;
}

/* ANALYTICS */
.analytics-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 1rem;
}

.summary-grid-upgrade {
    margin-top: 1rem;
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.7rem;
}

.summary-tile-upgrade {
    padding: 0.8rem;
    border-radius: 1rem;
    background: rgba(248, 250, 252, 0.92);
}

.summary-tile-upgrade__label {
    color: #94a3b8;
    font-size: 0.6rem;
    font-weight: 800;
}

.summary-tile-upgrade__value {
    margin-top: 0.3rem;
    color: #0f172a;
    font-size: 0.84rem;
    font-weight: 900;
}

.module-grid-upgrade {
    margin-top: 1rem;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.8rem;
}

.module-card-upgrade {
    padding: 0.95rem;
    border-radius: 1.2rem;
    text-align: left;
    background: rgba(255, 255, 255, 0.84);
    border: 1px solid rgba(226, 232, 240, 0.82);
}

.module-card-upgrade__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.module-card-upgrade__icon {
    width: 2.4rem;
    height: 2.4rem;
    border-radius: 0.9rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
}

.module-card-upgrade.is-emerald .module-card-upgrade__icon {
    background: #dcfce7;
    color: #15803d;
}
.module-card-upgrade.is-amber .module-card-upgrade__icon {
    background: #fef3c7;
    color: #b45309;
}
.module-card-upgrade.is-indigo .module-card-upgrade__icon {
    background: #e0e7ff;
    color: #4338ca;
}

.module-card-upgrade__tag {
    padding: 0.28rem 0.5rem;
    border-radius: 999px;
    background: #f8fafc;
    color: #64748b;
    font-size: 0.54rem;
    font-weight: 900;
    text-transform: uppercase;
}

.module-card-upgrade__title {
    margin-top: 0.8rem;
    color: #0f172a;
    font-size: 0.94rem;
    font-weight: 900;
}

.module-card-upgrade__desc {
    margin-top: 0.4rem;
    color: #64748b;
    font-size: 0.68rem;
    line-height: 1.45;
    font-weight: 650;
}

.module-card-upgrade__metrics {
    margin-top: 0.8rem;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.6rem;
}

.module-card-upgrade__metric {
    padding: 0.65rem;
    border-radius: 0.9rem;
    background: rgba(248, 250, 252, 0.92);
}

.module-card-upgrade__metric span {
    color: #94a3b8;
    font-size: 0.58rem;
    font-weight: 800;
}

.module-card-upgrade__metric strong {
    display: block;
    margin-top: 0.24rem;
    color: #0f172a;
    font-size: 0.76rem;
    font-weight: 900;
}

/* MODAL */
.fade-scale-enter-active,
.fade-scale-leave-active {
    transition:
        opacity 220ms ease,
        transform 220ms ease;
}

.fade-scale-enter-from,
.fade-scale-leave-to {
    opacity: 0;
}

.day-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.42);
    backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 80;
    padding: 1rem;
}

.day-modal-card {
    width: 100%;
    max-width: 780px;
    max-height: calc(100vh - 2rem);
    overflow: hidden;
    border-radius: 1.4rem;
    background: rgba(255, 255, 255, 0.96);
    box-shadow: 0 24px 48px rgba(15, 23, 42, 0.2);
    display: flex;
    flex-direction: column;
}

.day-modal-card__head {
    padding: 1rem 1.1rem;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    color: #fff;
}

.day-modal-card__head.is-emerald {
    background: linear-gradient(135deg, #047857, #10b981);
}
.day-modal-card__head.is-amber {
    background: linear-gradient(135deg, #b45309, #f59e0b);
}
.day-modal-card__head.is-rose {
    background: linear-gradient(135deg, #be123c, #f43f5e);
}
.day-modal-card__head.is-orange {
    background: linear-gradient(135deg, #c2410c, #fb923c);
}
.day-modal-card__head.is-dark {
    background: linear-gradient(135deg, #1e293b, #0f172a);
}
.day-modal-card__head.is-violet {
    background: linear-gradient(135deg, #6d28d9, #8b5cf6);
}
.day-modal-card__head.is-teal {
    background: linear-gradient(135deg, #0f766e, #14b8a6);
}
.day-modal-card__head.is-slate {
    background: linear-gradient(135deg, #475569, #94a3b8);
}
.day-modal-card__head.is-redline {
    background: linear-gradient(135deg, #b91c1c, #ef4444);
}

.day-modal-card__eyebrow {
    font-size: 0.62rem;
    font-weight: 850;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    opacity: 0.82;
}

.day-modal-card__title {
    margin-top: 0.35rem;
    font-size: 1.15rem;
    font-weight: 900;
}

.day-modal-card__subtitle {
    margin-top: 0.28rem;
    font-size: 0.7rem;
    font-weight: 650;
    opacity: 0.92;
}

.day-modal-card__close {
    width: 2.4rem;
    height: 2.4rem;
    border-radius: 0.9rem;
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.day-modal-card__body {
    padding: 1rem 1.1rem;
    overflow: auto;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.day-modal-section__title {
    color: #0f172a;
    font-size: 0.78rem;
    font-weight: 900;
    margin-bottom: 0.75rem;
}

.day-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}

.day-detail-item {
    padding: 0.85rem;
    border-radius: 1rem;
    background: rgba(248, 250, 252, 0.96);
}

.day-detail-item__label {
    display: block;
    color: #94a3b8;
    font-size: 0.62rem;
    font-weight: 800;
}

.day-detail-item__value {
    display: block;
    margin-top: 0.32rem;
    color: #0f172a;
    font-size: 0.8rem;
    line-height: 1.4;
    font-weight: 900;
}

.day-note-box {
    padding: 0.85rem;
    border-radius: 1rem;
    background: rgba(248, 250, 252, 0.96);
    color: #475569;
    font-size: 0.72rem;
    line-height: 1.55;
    font-weight: 650;
}

.day-timeline {
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
}

.day-timeline__row {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 0.8rem;
    align-items: center;
    padding: 0.8rem;
    border-radius: 0.95rem;
    background: rgba(248, 250, 252, 0.96);
}

.day-timeline__marker {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 0.9rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.day-timeline__row.is-checkin .day-timeline__marker {
    background: #dcfce7;
    color: #15803d;
}
.day-timeline__row.is-checkout .day-timeline__marker {
    background: #ffedd5;
    color: #c2410c;
}
.day-timeline__row.is-finger .day-timeline__marker {
    background: #e0e7ff;
    color: #4338ca;
}

.day-timeline__title {
    color: #0f172a;
    font-size: 0.74rem;
    font-weight: 850;
}

.day-timeline__time {
    margin-top: 0.2rem;
    color: #64748b;
    font-size: 0.66rem;
    font-weight: 650;
}

.day-timeline__empty {
    padding: 0.85rem;
    border-radius: 0.95rem;
    background: rgba(248, 250, 252, 0.96);
    color: #64748b;
    font-size: 0.68rem;
    font-weight: 650;
}

.day-modal-card__footer {
    padding: 1rem 1.1rem;
    border-top: 1px solid rgba(226, 232, 240, 0.8);
    display: flex;
    justify-content: flex-end;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.modal-action-btn {
    min-height: 2.8rem;
    padding: 0 0.95rem;
    border-radius: 0.95rem;
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    font-weight: 850;
    background: linear-gradient(135deg, #4f46e5, #3b82f6);
    color: #fff;
}

.modal-action-btn.is-secondary {
    background: #f8fafc;
    color: #334155;
}

@media (max-width: 1399.98px) {
    .summary-grid-upgrade {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

@media (max-width: 1199.98px) {
    .assistant-hero__content,
    .workspace-grid,
    .dashboard-main-grid,
    .analytics-grid {
        grid-template-columns: 1fr;
    }

    .power-strip,
    .module-grid-upgrade {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .summary-grid-upgrade {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {
    .attendance-home-v4 {
        padding: 0.75rem;
    }

    .assistant-hero__chips,
    .power-strip,
    .quick-action-grid,
    .day-detail-grid,
    .summary-grid-upgrade,
    .module-grid-upgrade,
    .module-card-upgrade__metrics,
    .hero-status-grid {
        grid-template-columns: 1fr;
    }

    .cutoff-calendar-scroll-wrapper {
        overflow-x: auto;
        padding-bottom: 1rem;
        -webkit-overflow-scrolling: touch;
        margin: 0 -0.85rem;
        padding: 0 0.85rem 1rem 0.85rem;
    }

    .cutoff-calendar-grid {
        min-width: 700px; /* Force horizontal scroll */
        grid-template-columns: repeat(7, minmax(0, 1fr)) !important;
    }

    .assistant-hero__actions,
    .day-modal-card__footer {
        flex-direction: column;
    }

    .assistant-alert-row,
    .upcoming-compact-row {
        grid-template-columns: 1fr;
    }

    .section-head,
    .cutoff-card__head {
        flex-direction: column;
        align-items: flex-start;
    }
}
.assistant-hero {
    padding: 1rem;
}

.assistant-hero__title {
    margin-top: 0.7rem;
    font-size: 1.42rem;
}

.assistant-hero__message {
    margin-top: 0.45rem;
    font-size: 0.8rem;
    line-height: 1.5;
}

.assistant-hero__chips {
    margin-top: 0.85rem;
    gap: 0.6rem;
}

.assistant-chip {
    min-height: 5.15rem;
    padding: 0.78rem 0.82rem;
    border-radius: 1rem;
}

.assistant-chip__value {
    margin-top: 0.38rem;
    font-size: 0.88rem;
}

.assistant-chip__meta {
    margin-top: 0.22rem;
    font-size: 0.64rem;
}

.assistant-hero__actions {
    margin-top: 0.8rem;
    gap: 0.55rem;
}

.hero-action-btn {
    min-height: 2.7rem;
    padding: 0 0.9rem;
    border-radius: 0.95rem;
    font-size: 0.82rem;
}

/* RIGHT */
.assistant-hero__right {
    display: grid;
    grid-template-rows: 1fr 1fr 1fr;
    gap: 0.65rem;
    min-width: 0;
}

.hero-snapshot-card {
    padding: 0.85rem;
    border-radius: 1.15rem;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.16);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.07),
        0 8px 20px rgba(15, 23, 42, 0.04);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    text-align: left;
    color: #fff;
}

.hero-snapshot-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.7rem;
}

.hero-snapshot-card__title-group {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}

.hero-snapshot-card__iconbox {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 0.8rem;
    background: rgba(255, 255, 255, 0.16);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.92rem;
    flex-shrink: 0;
}

.hero-snapshot-card__title {
    font-size: 0.9rem;
    font-weight: 900;
    line-height: 1.15;
}

.hero-snapshot-card__subtitle {
    margin-top: 0.16rem;
    font-size: 0.62rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.76);
}

.hero-snapshot-card__pill {
    min-height: 1.8rem;
    padding: 0 0.65rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    font-size: 0.62rem;
    font-weight: 800;
    white-space: nowrap;
}

.hero-snapshot-card__pill--success {
    background: rgba(16, 185, 129, 0.22);
    color: #d1fae5;
    border: 1px solid rgba(209, 250, 229, 0.18);
}

.hero-snapshot-card__pill--info {
    background: rgba(125, 211, 252, 0.2);
    color: #e0f2fe;
    border: 1px solid rgba(224, 242, 254, 0.18);
}

/* KPI COMPACT */
.hero-kpi-compact {
    margin-top: 0.75rem;
    display: grid;
    grid-template-columns: 82px minmax(0, 1fr);
    gap: 0.7rem;
    align-items: start;
}

.hero-kpi-compact__score {
    display: flex;
    justify-content: center;
}

.hero-kpi-compact__ring {
    width: 76px;
    height: 76px;
    border-radius: 999px;
    border: 5px solid rgba(129, 140, 248, 0.92);
    background: rgba(255, 255, 255, 0.06);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    line-height: 1.05;
    text-align: center;
}

.hero-kpi-compact__ring strong {
    font-size: 0.96rem;
    font-weight: 900;
    color: #fff;
}

.hero-kpi-compact__ring span {
    margin-top: 0.12rem;
    font-size: 0.52rem;
    font-weight: 800;
    color: rgba(255, 255, 255, 0.74);
    text-transform: uppercase;
}

.hero-kpi-compact__stats {
    display: flex;
    flex-direction: column;
    gap: 0.48rem;
}

.hero-kpi-compact__metric-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.hero-kpi-compact__metric-name {
    font-size: 0.68rem;
    font-weight: 800;
    color: #fff;
}

.hero-kpi-compact__metric-value {
    font-size: 0.64rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.8);
    white-space: nowrap;
}

.hero-kpi-compact__metric-value small {
    font-size: 0.7em;
    opacity: 0.85;
}

.hero-kpi-compact__metric-bar {
    margin-top: 0.22rem;
    height: 0.34rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.15);
    overflow: hidden;
}

.hero-kpi-compact__metric-fill {
    height: 100%;
    border-radius: 999px;
    background: #818cf8;
}

.hero-mini-trend {
    margin-top: 0.75rem;
}

.hero-mini-trend__label {
    font-size: 0.58rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(255, 255, 255, 0.7);
}

.hero-mini-trend__line {
    margin-top: 0.45rem;
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 0.35rem;
}

.hero-mini-trend__point {
    min-height: 2.4rem;
    padding: 0.25rem;
    border-radius: 0.72rem;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.08rem;
}

.hero-mini-trend__score {
    font-size: 0.66rem;
    font-weight: 900;
    color: #fff;
}

.hero-mini-trend__month {
    font-size: 0.5rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.68);
}

/* LMS COMPACT */
.hero-lms-compact {
    margin-top: 0.75rem;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 0.7rem;
}

.hero-lms-compact__eyebrow {
    font-size: 0.58rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(255, 255, 255, 0.7);
    margin-bottom: 0.35rem;
}

.hero-lms-compact__next-card {
    padding: 0.75rem;
    border-radius: 0.95rem;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    box-shadow: 0 10px 22px rgba(29, 78, 216, 0.24);
}

.hero-lms-compact__next-label {
    font-size: 0.6rem;
    color: rgba(255, 255, 255, 0.78);
}

.hero-lms-compact__next-title {
    margin-top: 0.3rem;
    font-size: 0.84rem;
    font-weight: 900;
    line-height: 1.2;
    color: #fff;
}

.hero-lms-compact__next-date {
    margin-top: 0.55rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.68rem;
    font-weight: 700;
    color: #eff6ff;
}

.hero-lms-compact__course {
    margin-bottom: 0.45rem;
}

.hero-lms-compact__course-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.hero-lms-compact__course-title {
    font-size: 0.68rem;
    font-weight: 800;
    color: #fff;
}

.hero-lms-compact__course-percent {
    font-size: 0.64rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.8);
}

.hero-lms-compact__course-bar {
    margin-top: 0.2rem;
    height: 0.34rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.16);
    overflow: hidden;
}

.hero-lms-compact__course-fill {
    height: 100%;
    border-radius: 999px;
    background: #60a5fa;
}

.hero-lms-compact__course-fill.is-complete {
    background: #10b981;
}

.hero-lms-bottom {
    margin-top: 0.75rem;
    padding: 0.72rem 0.78rem;
    border-radius: 0.95rem;
    background: rgba(255, 255, 255, 0.1);
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 0.8rem;
    align-items: center;
}

.hero-lms-bottom__hours {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}

.hero-lms-bottom__hours-icon {
    width: 2.45rem;
    height: 2.45rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.92);
    color: #3b82f6;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.hero-lms-bottom__hours-value {
    display: block;
    font-size: 0.86rem;
    font-weight: 900;
    color: #fff;
}

.hero-lms-bottom__hours-label {
    margin-top: 0.14rem;
    font-size: 0.6rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.72);
}

.hero-lms-bottom__trend {
    min-width: 0;
}

.hero-lms-bottom__trend-label {
    font-size: 0.56rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(255, 255, 255, 0.68);
}

.hero-lms-bottom__trend-row {
    margin-top: 0.35rem;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.3rem;
}

.hero-lms-bottom__trend-item {
    min-height: 2.15rem;
    border-radius: 0.65rem;
    background: rgba(255, 255, 255, 0.07);
    border: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.06rem;
}

.hero-lms-bottom__trend-item strong {
    font-size: 0.62rem;
    font-weight: 900;
    color: #fff;
}

.hero-lms-bottom__trend-item small {
    font-size: 0.48rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.68);
}

@media (max-width: 1199.98px) {
    .assistant-hero__content,
    .workspace-grid,
    .dashboard-main-grid,
    .analytics-grid {
        grid-template-columns: 1fr;
    }

    .assistant-hero__right {
        grid-template-columns: 1fr 1fr;
        grid-template-rows: auto;
    }

    .power-strip,
    .module-grid-upgrade {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .summary-grid-upgrade {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {
    .attendance-home-v4 {
        padding: 0.75rem;
    }

    .assistant-hero__chips,
    .power-strip,
    .quick-action-grid,
    .day-detail-grid,
    .summary-grid-upgrade,
    .module-grid-upgrade,
    .module-card-upgrade__metrics,
    .assistant-hero__right,
    .hero-kpi-compact,
    .hero-mini-trend__line,
    .hero-lms-compact,
    .hero-lms-bottom__trend-row {
        grid-template-columns: 1fr;
    }

    .assistant-hero__actions,
    .day-modal-card__footer {
        flex-direction: column;
    }

    .assistant-alert-row,
    .upcoming-compact-row,
    .hero-lms-bottom {
        grid-template-columns: 1fr;
    }

    .section-head,
    .cutoff-card__head {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
