<template>
    <div class="personal-dashboard-page">
        <div class="personal-dashboard-container">
            <template v-if="isMobileViewport">
                <div class="mobile-dashboard">
                    <div class="mobile-dashboard__body">
                        <div v-if="dashboardError" class="dashboard-alert mobile-dashboard__error">
                            <span>{{ dashboardError }}</span>
                            <button type="button" @click="retryDashboardLoad">Coba lagi</button>
                        </div>

                        <section
                            :ref="(el) => setMobileSectionRef('home', el)"
                            class="mobile-section mobile-section--intro"
                        >
                            <MobileHero
                                data-tour="hero"
                                :is-dashboard-bootstrapping="isDashboardBootstrapping"
                                :today-awareness="todayAwareness"
                                :mobile-hero-date-label="mobileHeroDateLabel"
                                :today-snapshot="todaySnapshot"
                                :mobile-hero-status="mobileHeroStatus"
                                :is-leader="isLeader"
                                :team-dashboard-badge-visible="teamDashboardBadgeVisible"
                                :team-dashboard-badge-label="teamDashboardBadgeLabel"
                                :cutoff-month-label="cutoffMonthLabel"
                                :cutoff-label="calendarCutoffLabel"
                                :workday-brief="workdayBrief"
                                :hero-kpi="heroKpi"
                                :pending-approvals="pendingApprovalsResolved"
                                @visit-leader-dashboard="handleVisit('/homepage/leader-dashboard')"
                            />

                            <MobileAwarenessGrid
                                data-tour="awareness"
                                data-lazy-domain="leave"
                                :mobile-awareness-cards="mobileAwarenessCards"
                                @awareness-click="handleAwarenessClick"
                            />

                            <section class="panel-card mobile-mini-calendar" data-tour="calendar">
                                <div class="mobile-card-head">
                                    <div class="mobile-card-head__copy">
                                        <span class="section-label">Absensi Mingguan</span>
                                        <h2>{{ mobileCalendarMonthLabel }}</h2>
                                    </div>

                                    <div class="mobile-mini-calendar__actions">
                                        <!-- Button navigasi ke tanggal hari ini-->
                                        <div class="mobile-week-nav" aria-label="Navigasi minggu">
                                            <button
                                                type="button"
                                                class="mobile-week-nav__button mobile-week-nav__button--today"
                                                :disabled="isMobileWeekNavBusy || isCurrentWeek"
                                                aria-label="Minggu ini"
                                                @click="goToCurrentWeek"
                                            >
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                            <button
                                                type="button"
                                                class="mobile-week-nav__button"
                                                :disabled="isMobileWeekNavBusy"
                                                aria-label="Minggu sebelumnya"
                                                @click="shiftMobileWeek(-1)"
                                            >
                                                <i class="bi bi-chevron-left"></i>
                                            </button>
                                            <button
                                                type="button"
                                                class="mobile-week-nav__button"
                                                :disabled="isMobileWeekNavBusy"
                                                aria-label="Minggu berikutnya"
                                                @click="shiftMobileWeek(1)"
                                            >
                                                <i class="bi bi-chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="isMobileWeekLoading" class="mobile-mini-calendar__grid is-skeleton-grid">
                                    <button
                                        v-for="n in 7"
                                        :key="`mobile-week-skeleton-${n}`"
                                        type="button"
                                        class="mobile-mini-calendar__day is-skeleton"
                                    >
                                        <div
                                            class="leader-skeleton-line is-short"
                                            style="width: 60%; margin: 0 auto"
                                        ></div>
                                        <div
                                            class="leader-skeleton-line is-title"
                                            style="width: 45%; margin: 0 auto"
                                        ></div>
                                        <div
                                            class="leader-skeleton-line is-short"
                                            style="width: 70%; margin: 0 auto"
                                        ></div>
                                    </button>
                                </div>

                                <template v-else>
                                    <EmptyState
                                        v-if="mobileWeekError"
                                        compact
                                        icon="bi bi-wifi-off"
                                        title="Kalender gagal dimuat"
                                        :description="mobileWeekError"
                                    />

                                    <div v-else-if="mobileWeekDays.length" class="mobile-mini-calendar__grid">
                                        <button
                                            v-for="day in mobileWeekDays"
                                            :key="day.date"
                                            type="button"
                                            class="mobile-mini-calendar__day"
                                            :class="[
                                                `is-${day.primaryStatusTone}`,
                                                { 'is-today': day.isToday, 'is-holiday': day.isHoliday },
                                            ]"
                                            :title="
                                                day.isHoliday ? day.holidayName || 'Hari Libur' : day.primaryStatusLabel
                                            "
                                            @click="openDayModal(day)"
                                        >
                                            <span class="mobile-mini-calendar__day-label">{{
                                                shortDayName(day.dayName)
                                            }}</span>
                                            <strong class="mobile-mini-calendar__day-number">{{
                                                day.dayNumber
                                            }}</strong>
                                            <small
                                                class="mobile-mini-calendar__day-meta"
                                                :title="
                                                    day.isHoliday
                                                        ? day.holidayName || 'Hari Libur'
                                                        : day.primaryStatusLabel
                                                "
                                            >
                                                {{ compactCalendarMeta(day) }}
                                            </small>
                                        </button>
                                    </div>

                                    <EmptyState
                                        v-else
                                        compact
                                        icon="bi bi-calendar2-week"
                                        title="Kalender mingguan kosong"
                                        description="Belum ada data absensi pada minggu ini."
                                    />
                                </template>
                            </section>

                            <section class="panel-card side-panel mobile-quick-actions" data-tour="quick-actions">
                                <div class="mobile-card-head">
                                    <div class="mobile-card-head__copy">
                                        <span class="section-label">Menu Cepat</span>
                                        <h2>Akses Cepat</h2>
                                    </div>

                                    <span class="side-count">{{ quickActionCount }}</span>
                                </div>

                                <div v-if="quickActionGroups.length" class="mobile-quick-actions__rail">
                                    <button
                                        v-for="group in quickActionGroups"
                                        :key="group.key"
                                        type="button"
                                        class="mobile-quick-action-folder"
                                        @click="openQuickActionGroup(group)"
                                    >
                                        <span class="mobile-quick-action-folder__icon">
                                            <i :class="group.icon"></i>
                                        </span>
                                        <span class="mobile-quick-action-folder__copy">
                                            <strong>{{ group.label }}</strong>
                                            <small>{{ group.items.length }} menu - {{ group.moduleLabel }}</small>
                                        </span>
                                    </button>
                                </div>

                                <EmptyState
                                    v-else
                                    compact
                                    icon="bi bi-grid"
                                    title="Menu cepat belum tersedia"
                                    description="Menu akan mengikuti akses sidebar kamu."
                                />
                            </section>

                            <section class="panel-card side-panel mobile-accordion-card" data-tour="leader-approvals">
                                <button
                                    type="button"
                                    class="mobile-accordion__trigger"
                                    @click.stop="toggleMobilePanel('action')"
                                >
                                    <div class="module-title">
                                        <div class="module-icon is-orange">
                                            <i class="bi bi-bell-fill"></i>
                                        </div>

                                        <div class="module-title-copy">
                                            <span class="section-label">Action Center</span>
                                            <h2>Perlu Saya Tindak</h2>
                                        </div>
                                    </div>

                                    <div class="mobile-accordion__meta">
                                        <span class="side-count">{{ unreadPendingCount }}</span>
                                        <i
                                            :class="mobilePanelState.action ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"
                                        ></i>
                                    </div>
                                </button>

                                <transition
                                    name="accordion"
                                    @enter="onAccordionEnter"
                                    @after-enter="onAccordionAfterEnter"
                                    @leave="onAccordionLeave"
                                >
                                    <div v-show="mobilePanelState.action" class="mobile-accordion__body">
                                        <div class="action-list">
                                            <div
                                                v-if="isNeedsActionLoading || isDashboardBootstrapping"
                                                class="action-skeleton-list"
                                            >
                                                <div
                                                    v-for="n in 3"
                                                    :key="`mobile-action-skeleton-${n}`"
                                                    class="action-card is-skeleton"
                                                >
                                                    <div class="action-card__icon skeleton-avatar"></div>
                                                    <div class="action-card__body">
                                                        <div
                                                            class="leader-skeleton-line is-title mb-1"
                                                            style="width: 60%"
                                                        ></div>
                                                        <div class="leader-skeleton-line" style="width: 85%"></div>
                                                        <div
                                                            class="leader-skeleton-line is-short mt-1"
                                                            style="width: 50%"
                                                        ></div>
                                                    </div>
                                                    <div class="action-card__actions">
                                                        <div
                                                            class="skeleton-avatar"
                                                            style="
                                                                width: 1rem;
                                                                height: 1rem;
                                                                border-radius: 0.3rem;
                                                                flex: 0 0 auto;
                                                            "
                                                        ></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <template v-else>
                                                <div
                                                    v-for="item in pendingItems"
                                                    :key="item.id"
                                                    role="button"
                                                    tabindex="0"
                                                    class="action-card"
                                                    :class="[`is-${item.tone}`, { 'is-read': item.is_read }]"
                                                    @click="handlePendingItem(item)"
                                                    @keydown.enter.prevent="handlePendingItem(item)"
                                                >
                                                    <div class="action-card__icon">
                                                        <i :class="item.icon"></i>
                                                    </div>

                                                    <div class="action-card__body">
                                                        <strong>{{ item.title }}</strong>
                                                        <span>{{ item.description }}</span>
                                                        <small>{{ item.module }} • {{ item.actionLabel }}</small>
                                                    </div>

                                                    <div class="action-card__actions">
                                                        <i
                                                            v-if="isNotificationDismissBusy(item)"
                                                            class="bi bi-arrow-repeat action-loading-spin"
                                                        ></i>
                                                        <i v-else class="bi bi-arrow-right-short"></i>
                                                    </div>
                                                </div>

                                                <div
                                                    v-if="
                                                        !isDashboardBootstrapping &&
                                                        !needsActionError &&
                                                        actionCenterTotalPages > 1
                                                    "
                                                    class="submission-pagination"
                                                >
                                                    <button
                                                        type="button"
                                                        class="submission-pagination__btn"
                                                        :disabled="actionCenterPage <= 1 || actionCenterPageLoading"
                                                        @click.stop="goToActionCenterPage(actionCenterPage - 1)"
                                                    >
                                                        <i class="bi bi-chevron-left"></i>
                                                    </button>
                                                    <span class="submission-pagination__info">
                                                        <template v-if="actionCenterPageLoading">
                                                            <i class="bi bi-arrow-repeat action-loading-spin"></i>
                                                        </template>
                                                        <template v-else>
                                                            {{ actionCenterPage }} / {{ actionCenterTotalPages }}
                                                        </template>
                                                    </span>
                                                    <button
                                                        type="button"
                                                        class="submission-pagination__btn"
                                                        :disabled="
                                                            actionCenterPage >= actionCenterTotalPages ||
                                                            actionCenterPageLoading
                                                        "
                                                        @click.stop="goToActionCenterPage(actionCenterPage + 1)"
                                                    >
                                                        <i class="bi bi-chevron-right"></i>
                                                    </button>
                                                </div>

                                                <Transition name="action-page-fade">
                                                    <div
                                                        v-if="actionCenterPageLoading"
                                                        class="action-page-loader"
                                                    ></div>
                                                </Transition>

                                                <div
                                                    v-if="!isDashboardBootstrapping && needsActionError"
                                                    class="action-error-card"
                                                >
                                                    <span>{{ needsActionError }}</span>
                                                    <button type="button" @click="retryDashboardLoad">Coba lagi</button>
                                                </div>

                                                <EmptyState
                                                    v-if="
                                                        !isDashboardBootstrapping &&
                                                        !needsActionError &&
                                                        !compactPendingItems.length
                                                    "
                                                    compact
                                                    icon="bi bi-check2-circle"
                                                    title="Tidak ada tindakan"
                                                    description="Semua terlihat aman untuk saat ini."
                                                />
                                            </template>
                                        </div>
                                    </div>
                                </transition>
                            </section>

                            <section
                                class="panel-card side-panel attendance-donut-card mobile-attendance-donut-card mobile-accordion-card"
                                data-tour="attendance-composition"
                                @click="handleAttendanceDonutDetail('all')"
                            >
                                <button class="mobile-accordion__trigger" @click.stop="toggleMobilePanel('attendance')">
                                    <div class="module-title">
                                        <div class="module-icon is-teal">
                                            <i class="bi bi-pie-chart-fill"></i>
                                        </div>
                                        <div class="module-title-copy">
                                            <span class="section-label">Absensi Cutoff</span>
                                            <h2>Komposisi Kehadiran</h2>
                                        </div>
                                    </div>
                                    <div class="mobile-accordion__meta">
                                        <span class="side-count">{{ attendanceDonutSummary.percentLabel }}</span>
                                        <i
                                            :class="
                                                mobilePanelState.attendance ? 'bi bi-chevron-up' : 'bi bi-chevron-down'
                                            "
                                        ></i>
                                    </div>
                                </button>

                                <transition
                                    name="accordion"
                                    @enter="onAccordionEnter"
                                    @after-enter="onAccordionAfterEnter"
                                    @leave="onAccordionLeave"
                                >
                                    <div
                                        v-show="mobilePanelState.attendance"
                                        class="mobile-accordion__content"
                                        @click="handleAttendanceDonutDetail('all')"
                                    >
                                        <select
                                            class="attendance-donut-period-select"
                                            :value="selectedAttendanceDonutPeriod"
                                            :disabled="isAttendanceDonutLoading"
                                            @click.stop
                                            @change.stop="handleAttendanceDonutPeriodChange"
                                        >
                                            <option
                                                v-for="period in attendanceDonutPeriodOptions"
                                                :key="`mobile-donut-period-${period.value}`"
                                                :value="period.value"
                                            >
                                                {{ period.label }}
                                            </option>
                                        </select>

                                        <div v-if="isAttendanceDonutLoading" class="attendance-donut-skeleton">
                                            <div class="attendance-donut-skeleton__chart"></div>
                                            <div class="attendance-donut-skeleton__lines">
                                                <span
                                                    v-for="n in 4"
                                                    :key="`mobile-attendance-donut-skeleton-${n}`"
                                                ></span>
                                            </div>
                                        </div>

                                        <EmptyState
                                            v-else-if="attendanceDonutError"
                                            compact
                                            icon="bi bi-exclamation-triangle"
                                            title="Komposisi gagal dimuat"
                                            :description="attendanceDonutError"
                                        />

                                        <EmptyState
                                            v-else-if="!attendanceDonutSummary.totalDays"
                                            compact
                                            icon="bi bi-pie-chart"
                                            title="Absensi cutoff kosong"
                                            description="Komposisi akan muncul setelah data kalender tersedia."
                                        />

                                        <template v-else>
                                            <div class="attendance-donut-chart">
                                                <VueApexCharts
                                                    type="donut"
                                                    height="168"
                                                    :options="attendanceDonutOptions"
                                                    :series="attendanceDonutSeries"
                                                />
                                            </div>

                                            <div class="attendance-donut-meta">
                                                <strong
                                                    >{{ attendanceDonutSummary.presentCount }} dari
                                                    {{ attendanceDonutSummary.totalDays }} hari hadir</strong
                                                >
                                                <span>Periode {{ attendanceDonutSummary.periodLabel }}</span>
                                            </div>

                                            <div class="attendance-donut-legend">
                                                <button
                                                    v-for="item in attendanceDonutStats"
                                                    :key="`mobile-donut-${item.key}`"
                                                    type="button"
                                                    class="attendance-donut-legend__item"
                                                    :class="`is-${item.tone}`"
                                                    :disabled="!item.count"
                                                    @click.stop="handleAttendanceDonutLegend(item)"
                                                >
                                                    <span
                                                        class="attendance-donut-legend__dot"
                                                        :style="{ backgroundColor: item.color }"
                                                    ></span>
                                                    <span>{{ item.label }}</span>
                                                    <strong>{{ item.count }}</strong>
                                                </button>
                                            </div>

                                            <button
                                                type="button"
                                                class="attendance-donut-action"
                                                @click.stop="handleAttendanceDonutDetail('all')"
                                            >
                                                <span>Lihat detail</span>
                                                <i class="bi bi-arrow-right-short"></i>
                                            </button>
                                        </template>
                                    </div>
                                </transition>
                            </section>
                        </section>

                        <section
                            :ref="(el) => setMobileSectionRef('hcis', el)"
                            class="mobile-section"
                            data-tour="module-hcis"
                        >
                            <article class="panel-card module-card hcis-insight-card mobile-accordion-card">
                                <button
                                    type="button"
                                    class="mobile-accordion__trigger"
                                    @click.stop="toggleMobilePanel('hcis')"
                                >
                                    <div class="module-title">
                                        <div class="module-icon is-indigo">
                                            <i class="bi bi-fingerprint"></i>
                                        </div>

                                        <div class="module-title-copy">
                                            <span class="section-label">Absensi</span>
                                            <h2>Ringkasan HCIS Saya</h2>
                                        </div>
                                    </div>

                                    <div class="mobile-accordion__meta">
                                        <span
                                            class="mini-badge"
                                            :class="{ 'is-danger': hcisPersonal.stats.attendanceIssues > 0 }"
                                        >
                                            {{ hcisPersonal.stats.attendanceIssues }} issue
                                        </span>
                                        <i
                                            :class="mobilePanelState.hcis ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"
                                        ></i>
                                    </div>
                                </button>

                                <transition
                                    name="accordion"
                                    @enter="onAccordionEnter"
                                    @after-enter="onAccordionAfterEnter"
                                    @leave="onAccordionLeave"
                                >
                                    <div v-show="mobilePanelState.hcis" class="mobile-accordion__body">
                                        <template v-if="isHcisLoading || isDashboardBootstrapping">
                                            <div class="leader-summary-split">
                                                <div class="leader-score-card is-hcis is-skeleton">
                                                    <span>Status Hari Ini</span>
                                                    <div
                                                        class="leader-skeleton-line is-title mt-2 mb-2"
                                                        style="width: 50%"
                                                    ></div>
                                                    <div class="leader-skeleton-line" style="width: 90%"></div>
                                                    <div
                                                        class="leader-skeleton-line is-short mt-1"
                                                        style="width: 60%"
                                                    ></div>
                                                </div>

                                                <div class="leader-mini-stat-grid">
                                                    <div
                                                        v-for="n in 4"
                                                        :key="`mobile-hcis-skeleton-${n}`"
                                                        class="is-skeleton"
                                                    >
                                                        <div
                                                            class="leader-skeleton-line is-short mb-1"
                                                            style="width: 40%"
                                                        ></div>
                                                        <div
                                                            class="leader-skeleton-line is-title mt-1 mb-1"
                                                            style="width: 60%"
                                                        ></div>
                                                        <div
                                                            class="leader-skeleton-line is-short mt-1"
                                                            style="width: 80%"
                                                        ></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="line-chart-card">
                                                <div class="line-chart-card__head">
                                                    <div>
                                                        <div
                                                            class="leader-skeleton-line is-title mb-1"
                                                            style="width: 8rem"
                                                        ></div>
                                                        <div
                                                            class="leader-skeleton-line is-short"
                                                            style="width: 5rem"
                                                        ></div>
                                                    </div>
                                                    <div
                                                        class="leader-skeleton-line is-short"
                                                        style="width: 4rem"
                                                    ></div>
                                                </div>
                                                <div class="leader-apex-chart" style="height: 120px; padding: 0.8rem">
                                                    <div
                                                        class="leader-skeleton-line"
                                                        style="height: 100%; border-radius: 0.5rem"
                                                    ></div>
                                                </div>
                                            </div>
                                        </template>

                                        <template v-else>
                                            <div class="leader-summary-split">
                                                <button
                                                    type="button"
                                                    class="leader-score-card is-hcis is-clickable"
                                                    @click="todayRecord ? openDayModal(todayRecord) : null"
                                                >
                                                    <span>Status Hari Ini</span>
                                                    <strong>{{ todaySnapshot.statusLabel }}</strong>
                                                    <small
                                                        >{{ todaySnapshot.shiftTime }} - IN
                                                        {{ todaySnapshot.checkIn }} / OUT
                                                        {{ todaySnapshot.checkOut }}</small
                                                    >
                                                </button>

                                                <div class="leader-mini-stat-grid">
                                                    <div
                                                        v-for="item in hcisPersonalStats"
                                                        :key="`mobile-${item.key}`"
                                                        :class="`is-${item.tone}`"
                                                    >
                                                        <span>{{ item.label }}</span>
                                                        <strong>{{ item.value }}</strong>
                                                        <small>{{ item.caption }}</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="line-chart-card">
                                                <div class="line-chart-card__head">
                                                    <div>
                                                        <strong>Persentase Kehadiran</strong>
                                                        <span>{{ hcisTrendScopeLabel }}</span>
                                                    </div>
                                                    <small>{{ hcisTrendLatestLabel }}</small>
                                                </div>

                                                <div class="leader-apex-chart is-success">
                                                    <VueApexCharts
                                                        v-if="hcisPersonal.trend.length"
                                                        type="area"
                                                        height="120"
                                                        :options="hcisTrendChartOptions"
                                                        :series="hcisTrendChartSeries"
                                                    />

                                                    <EmptyState
                                                        v-else
                                                        compact
                                                        icon="bi bi-activity"
                                                        title="Trend HCIS kosong"
                                                        description="Belum ada histori absensi."
                                                    />
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </transition>
                            </article>
                        </section>

                        <section
                            :ref="(el) => setMobileSectionRef('kpi', el)"
                            class="mobile-section"
                            data-tour="module-kpi"
                        >
                            <article class="panel-card module-card mobile-accordion-card">
                                <button
                                    type="button"
                                    class="mobile-accordion__trigger"
                                    @click.stop="toggleMobilePanel('kpi')"
                                >
                                    <div class="module-title">
                                        <div class="module-icon is-purple">
                                            <i class="bi bi-graph-up-arrow"></i>
                                        </div>

                                        <div class="module-title-copy">
                                            <span class="section-label">KPI Saya</span>
                                            <h2>KPI Parameter {{ heroKpi.yearLabel }}</h2>
                                        </div>
                                    </div>

                                    <div class="mobile-accordion__meta">
                                        <span class="period-badge is-purple">{{ heroKpi.parameterPeriod }}</span>
                                        <i
                                            :class="mobilePanelState.kpi ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"
                                        ></i>
                                    </div>
                                </button>

                                <transition
                                    name="accordion"
                                    @enter="onAccordionEnter"
                                    @after-enter="onAccordionAfterEnter"
                                    @leave="onAccordionLeave"
                                >
                                    <div v-show="mobilePanelState.kpi" class="mobile-accordion__body">
                                        <div
                                            v-if="isKpiLoading || isKpiRefreshing || isDashboardBootstrapping"
                                            class="module-skeleton is-kpi"
                                        >
                                            <div class="kpi-layout mobile-kpi-layout">
                                                <div class="kpi-score-card is-skeleton">
                                                    <div
                                                        class="leader-skeleton-line is-title mb-2"
                                                        style="width: 55%; background: rgba(255, 255, 255, 0.3)"
                                                    ></div>
                                                    <div
                                                        class="leader-skeleton-line mb-1"
                                                        style="width: 70%; background: rgba(255, 255, 255, 0.2)"
                                                    ></div>
                                                    <div
                                                        class="leader-skeleton-line is-short"
                                                        style="background: rgba(255, 255, 255, 0.15)"
                                                    ></div>
                                                    <div
                                                        class="kpi-score-card__mini"
                                                        style="background: rgba(255, 255, 255, 0.12)"
                                                    >
                                                        <div
                                                            class="leader-skeleton-line is-short mb-1"
                                                            style="width: 60%; background: rgba(255, 255, 255, 0.28)"
                                                        ></div>
                                                        <div
                                                            class="leader-skeleton-line is-title mb-1"
                                                            style="width: 45%; background: rgba(255, 255, 255, 0.32)"
                                                        ></div>
                                                        <div
                                                            class="leader-skeleton-line is-short"
                                                            style="width: 55%; background: rgba(255, 255, 255, 0.22)"
                                                        ></div>
                                                    </div>
                                                </div>

                                                <div class="kpi-stat-grid">
                                                    <div
                                                        v-for="n in 2"
                                                        :key="`mobile-kpi-stat-skeleton-${n}`"
                                                        class="is-skeleton"
                                                    >
                                                        <div class="leader-skeleton-line is-short mb-1"></div>
                                                        <div
                                                            class="leader-skeleton-line is-title mb-1"
                                                            style="width: 60%"
                                                        ></div>
                                                        <div class="leader-skeleton-line" style="width: 78%"></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="kpi-status-panel">
                                                <div
                                                    v-for="n in 2"
                                                    :key="`mobile-kpi-status-sk-${n}`"
                                                    class="kpi-status-panel__item is-skeleton"
                                                >
                                                    <div
                                                        class="leader-skeleton-line is-short mb-1"
                                                        style="width: 55%"
                                                    ></div>
                                                    <div class="leader-skeleton-line is-title" style="width: 65%"></div>
                                                </div>
                                            </div>

                                            <div class="mini-parameter-list is-skeleton">
                                                <div v-for="n in 3" :key="`mobile-kpi-param-skeleton-${n}`">
                                                    <div>
                                                        <div class="leader-skeleton-line mb-1" style="width: 65%"></div>
                                                        <div class="leader-skeleton-line is-short"></div>
                                                    </div>
                                                    <div class="leader-skeleton-line" style="height: 0.45rem"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            v-else
                                            class="kpi-detail-stack"
                                            :class="{ 'is-refreshing': isKpiRefreshing }"
                                        >
                                            <div class="kpi-control-row">
                                                <label>
                                                    <span>Tahun</span>
                                                    <select
                                                        :value="heroKpi.year"
                                                        :disabled="isKpiRefreshing"
                                                        @change="handleKpiYearChange"
                                                    >
                                                        <option
                                                            v-for="year in heroKpi.yearOptions"
                                                            :key="`mobile-kpi-year-${year}`"
                                                            :value="year"
                                                        >
                                                            {{ year }}
                                                        </option>
                                                    </select>
                                                </label>

                                                <label>
                                                    <span>Periode</span>
                                                    <select
                                                        :value="heroKpi.selectedPeriod"
                                                        :disabled="isKpiRefreshing"
                                                        @change="handleKpiPeriodChange"
                                                    >
                                                        <option
                                                            v-for="period in heroKpi.periodOptions"
                                                            :key="`mobile-kpi-period-${period.value}`"
                                                            :value="period.value"
                                                        >
                                                            {{ period.label }}
                                                        </option>
                                                    </select>
                                                </label>
                                            </div>

                                            <div v-if="isKpiRefreshing" class="kpi-refresh-state">
                                                <i class="bi bi-arrow-repeat"></i>
                                                <span>Memuat KPI {{ pendingKpiSelectionLabel }}...</span>
                                                <b></b>
                                            </div>

                                            <EmptyState
                                                v-if="
                                                    !heroKpi.hasData &&
                                                    !isKpiLoading &&
                                                    !isKpiRefreshing &&
                                                    !isDashboardBootstrapping
                                                "
                                                icon="bi bi-bar-chart"
                                                title="Data KPI belum tersedia"
                                                :description="heroKpi.emptyMessage"
                                            />

                                            <template v-else>
                                                <div class="kpi-warning-list" v-if="heroKpi.warningItems.length">
                                                    <div
                                                        v-for="warning in heroKpi.warningItems"
                                                        :key="warning.key"
                                                        class="inline-warning"
                                                        :class="warning.tone"
                                                    >
                                                        <i :class="warning.icon"></i>
                                                        <span>{{ warning.text }}</span>
                                                    </div>
                                                </div>

                                                <div class="kpi-layout mobile-kpi-layout">
                                                    <div class="kpi-score-card">
                                                        <strong>{{ heroKpi.score }}</strong>
                                                        <span>Skor Terpilih</span>
                                                        <small>{{ heroKpi.performancePeriod }}</small>

                                                        <div class="kpi-score-card__mini">
                                                            <span>Achievement YTD</span>
                                                            <strong>{{ heroKpi.ytdScore }}%</strong>
                                                            <small
                                                                >{{ heroKpi.ytdSubmittedCount }} periode terisi</small
                                                            >
                                                        </div>
                                                    </div>

                                                    <div class="kpi-stat-grid">
                                                        <div>
                                                            <span>Achievement Grade</span>
                                                            <strong :style="{ color: heroKpi.achievementGradeColor }">{{
                                                                heroKpi.achievementGradeLabel
                                                            }}</strong>
                                                            <small>periode terpilih</small>
                                                        </div>

                                                        <div>
                                                            <span>YTD Grade</span>
                                                            <strong :style="{ color: heroKpi.ytdGradeColor }">{{
                                                                heroKpi.ytdGradeLabel
                                                            }}</strong>
                                                            <small>year to date</small>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="kpi-status-panel">
                                                    <div class="kpi-status-panel__item">
                                                        <span>KPI Parameter</span>
                                                        <strong :class="heroKpi.parameterStatusTone">{{
                                                            heroKpi.parameterStatusLabel
                                                        }}</strong>
                                                    </div>

                                                    <div class="kpi-status-panel__item">
                                                        <span>Update Performance</span>
                                                        <strong :class="heroKpi.performanceStatusTone">{{
                                                            heroKpi.performanceStatusLabel
                                                        }}</strong>
                                                    </div>
                                                </div>

                                                <div class="mini-parameter-list">
                                                    <div
                                                        v-for="param in heroKpi.parameters.slice(0, 4)"
                                                        :key="param.id || param.name"
                                                    >
                                                        <div>
                                                            <span>{{ param.name }}</span>
                                                            <strong>
                                                                {{ param.achievement }}%
                                                                <small v-if="param.weight">({{ param.weight }}%)</small>
                                                            </strong>
                                                        </div>

                                                        <small class="mini-parameter-meta">
                                                            Target {{ param.target }} · Actual {{ param.actual }} ·
                                                            {{ param.statusLabel }}
                                                        </small>

                                                        <div class="mini-progress">
                                                            <div
                                                                class="mini-progress__fill"
                                                                :style="{
                                                                    width: `${safePercent(param.achievement)}%`,
                                                                    background: param.statusColor || '#7c3aed',
                                                                }"
                                                            ></div>
                                                        </div>
                                                    </div>

                                                    <EmptyState
                                                        v-if="!heroKpi.parameters.length"
                                                        compact
                                                        icon="bi bi-list-check"
                                                        title="Parameter KPI kosong"
                                                        description="Belum ada Parameter KPI tahun/periode ini."
                                                    />
                                                </div>

                                                <div class="chart-safe">
                                                    <VueApexCharts
                                                        v-if="heroKpi.trend.length"
                                                        :key="`mobile-kpi-trend-${heroKpi.year}-${heroKpi.selectedPeriod}`"
                                                        type="area"
                                                        height="135"
                                                        :options="kpiTrendOptions"
                                                        :series="kpiTrendSeries"
                                                    />

                                                    <EmptyState
                                                        v-else
                                                        compact
                                                        icon="bi bi-activity"
                                                        title="Trend KPI kosong"
                                                        description="Belum ada histori skor KPI."
                                                    />
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </transition>
                            </article>
                        </section>

                        <section
                            :ref="(el) => setMobileSectionRef('lms', el)"
                            class="mobile-section"
                            data-tour="module-lms"
                        >
                            <article class="panel-card module-card mobile-accordion-card">
                                <button
                                    type="button"
                                    class="mobile-accordion__trigger"
                                    @click.stop="toggleMobilePanel('lms')"
                                >
                                    <div class="module-title">
                                        <div class="module-icon is-teal">
                                            <i class="bi bi-mortarboard"></i>
                                        </div>

                                        <div class="module-title-copy">
                                            <span class="section-label">Learning Saya</span>
                                            <h2>Learning Management</h2>
                                        </div>
                                    </div>

                                    <div class="mobile-accordion__meta">
                                        <span class="period-badge is-teal"
                                            >{{ heroLms.completed }}/{{ heroLms.total }}</span
                                        >
                                        <i
                                            :class="mobilePanelState.lms ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"
                                        ></i>
                                    </div>
                                </button>

                                <transition
                                    name="accordion"
                                    @enter="onAccordionEnter"
                                    @after-enter="onAccordionAfterEnter"
                                    @leave="onAccordionLeave"
                                >
                                    <div v-show="mobilePanelState.lms" class="mobile-accordion__body">
                                        <div
                                            v-if="isLmsLoading || isDashboardBootstrapping"
                                            class="module-skeleton is-lms"
                                        >
                                            <div class="lms-next-card is-skeleton">
                                                <div>
                                                    <div class="leader-skeleton-line is-short mb-1"></div>
                                                    <div
                                                        class="leader-skeleton-line is-title mb-1"
                                                        style="width: 70%"
                                                    ></div>
                                                    <div class="leader-skeleton-line" style="width: 80%"></div>
                                                </div>
                                                <div class="skeleton-avatar lms-progress-ring"></div>
                                            </div>

                                            <div class="lms-main-progress is-skeleton">
                                                <div>
                                                    <div class="leader-skeleton-line is-short mb-1"></div>
                                                    <div
                                                        class="leader-skeleton-line is-title mb-1"
                                                        style="width: 55%"
                                                    ></div>
                                                    <div class="leader-skeleton-line" style="width: 75%"></div>
                                                </div>
                                                <div class="leader-skeleton-line" style="height: 0.55rem"></div>
                                            </div>

                                            <div class="lms-stat-grid">
                                                <div
                                                    v-for="n in 2"
                                                    :key="`mobile-lms-stat-skeleton-${n}`"
                                                    class="is-skeleton"
                                                >
                                                    <div class="leader-skeleton-line is-short mb-1"></div>
                                                    <div
                                                        class="leader-skeleton-line is-title mb-1"
                                                        style="width: 58%"
                                                    ></div>
                                                    <div class="leader-skeleton-line" style="width: 78%"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <EmptyState
                                            v-else-if="!heroLms.hasData && !isDashboardBootstrapping"
                                            icon="bi bi-journal-bookmark"
                                            title="Belum ada training"
                                            description="Training belum diberikan atau data LMS belum tersedia."
                                        />

                                        <template v-else>
                                            <div class="lms-next-card">
                                                <div class="lms-next-card__info">
                                                    <span>Next Training</span>
                                                    <strong>{{ heroLms.nextSession }}</strong>
                                                    <small>{{ heroLms.nextSessionDate }}</small>
                                                </div>

                                                <div class="lms-progress-card">
                                                    <div class="lms-progress-ring">
                                                        <span>Learning</span>
                                                        <strong>{{ heroLms.progress }}%</strong>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="lms-main-progress">
                                                <div>
                                                    <span>Skor Progress Learning</span>
                                                    <strong>{{ heroLms.progressLabel }}</strong>
                                                    <small
                                                        >{{ heroLms.completed }}/{{ heroLms.total }} training
                                                        selesai</small
                                                    >
                                                </div>

                                                <div class="mini-progress is-large">
                                                    <div
                                                        class="mini-progress__fill is-teal"
                                                        :style="{ width: `${safePercent(heroLms.progress)}%` }"
                                                    ></div>
                                                </div>
                                            </div>

                                            <div class="lms-stat-grid">
                                                <div>
                                                    <span>Avg Durasi</span>
                                                    <strong>{{ heroLms.avgLearningDurationLabel }}</strong>
                                                    <small>Rata-rata/training</small>
                                                </div>

                                                <div>
                                                    <span>Avg Learning Gain</span>
                                                    <strong>{{
                                                        heroLms.learningGain ? `+${heroLms.learningGain}%` : '-'
                                                    }}</strong>
                                                    <small>Pre/Post test</small>
                                                </div>
                                            </div>

                                            <div class="chart-block lms-duration-card">
                                                <div class="chart-head">
                                                    <span>Durasi Belajar Per Bulan</span>
                                                    <strong>{{ heroLms.avgMonthlyDurationLabel }} avg</strong>
                                                </div>

                                                <div class="chart-safe">
                                                    <VueApexCharts
                                                        v-if="heroLms.durationMonthly.length"
                                                        type="area"
                                                        height="135"
                                                        :options="lmsDurationChartOptions"
                                                        :series="lmsDurationChartSeries"
                                                    />

                                                    <EmptyState
                                                        v-else
                                                        compact
                                                        icon="bi bi-activity"
                                                        title="Trend durasi kosong"
                                                        description="Belum ada histori durasi belajar."
                                                    />
                                                </div>
                                            </div>

                                            <div class="training-list mobile-training-list">
                                                <div
                                                    v-for="course in heroLms.progressList.slice(0, 4)"
                                                    :key="course.title"
                                                    class="training-item"
                                                >
                                                    <div class="training-item__top">
                                                        <div>
                                                            <strong>{{ course.title }}</strong>
                                                            <span>{{
                                                                course.deadline ||
                                                                course.remaining ||
                                                                'Tidak ada deadline'
                                                            }}</span>
                                                        </div>

                                                        <small :class="{ 'is-complete': course.progress >= 100 }">
                                                            {{ course.progress }}%
                                                        </small>
                                                    </div>

                                                    <div class="mini-progress">
                                                        <div
                                                            class="mini-progress__fill is-teal"
                                                            :style="{ width: `${safePercent(course.progress)}%` }"
                                                        ></div>
                                                    </div>
                                                </div>

                                                <EmptyState
                                                    v-if="!heroLms.progressList.length"
                                                    compact
                                                    icon="bi bi-journal"
                                                    title="List training kosong"
                                                    description="Belum ada training aktif."
                                                />
                                            </div>
                                        </template>
                                    </div>
                                </transition>
                            </article>
                        </section>

                        <section
                            :ref="(el) => setMobileSectionRef('more', el)"
                            class="mobile-section"
                            data-tour="module-overtime"
                        >
                            <article
                                v-if="!isLeader"
                                ref="overtimeSectionRef"
                                class="panel-card module-card mobile-accordion-card"
                            >
                                <button
                                    type="button"
                                    class="mobile-accordion__trigger"
                                    @click.stop="toggleMobilePanel('overtime')"
                                >
                                    <div class="module-title">
                                        <div class="module-icon is-orange">
                                            <i class="bi bi-clock-history"></i>
                                        </div>

                                        <div class="module-title-copy">
                                            <span class="section-label">Lembur Saya</span>
                                            <h2>Assignment Lembur</h2>
                                        </div>
                                    </div>

                                    <div class="mobile-accordion__meta">
                                        <span class="period-badge is-orange">{{ overtimeSelectedCutoffLabel }}</span>
                                        <i
                                            :class="
                                                mobilePanelState.overtime ? 'bi bi-chevron-up' : 'bi bi-chevron-down'
                                            "
                                        ></i>
                                    </div>
                                </button>

                                <transition
                                    name="accordion"
                                    @enter="onAccordionEnter"
                                    @after-enter="onAccordionAfterEnter"
                                    @leave="onAccordionLeave"
                                >
                                    <div v-show="mobilePanelState.overtime" class="mobile-accordion__body">
                                        <div v-if="overtimeRevealed" class="overtime-filters">
                                            <select
                                                class="cutoff-period-select"
                                                :value="selectedOvertimeCutoff"
                                                :disabled="overtimeCutoffSwitching"
                                                @change="handleOvertimeCutoffChange"
                                            >
                                                <option
                                                    v-for="period in cutoffPeriodOptions"
                                                    :key="period.value"
                                                    :value="period.value"
                                                >
                                                    {{ period.label }}
                                                </option>
                                            </select>
                                            <span v-if="overtimeCutoffSwitching" class="overtime-filters__hint">
                                                Memuat cutoff…
                                            </span>
                                        </div>

                                        <div
                                            v-if="isOvertimeLoading || isDashboardBootstrapping || overtimeCutoffSwitching"
                                            class="module-skeleton is-overtime"
                                        >
                                            <div class="overtime-summary-grid">
                                                <div
                                                    v-for="n in 4"
                                                    :key="`mobile-overtime-skeleton-${n}`"
                                                    class="is-skeleton"
                                                >
                                                    <div class="leader-skeleton-line is-short mb-1"></div>
                                                    <div
                                                        class="leader-skeleton-line is-title mb-1"
                                                        style="width: 55%"
                                                    ></div>
                                                    <div class="leader-skeleton-line" style="width: 75%"></div>
                                                </div>
                                            </div>
                                            <div class="module-alert is-info is-skeleton">
                                                <div
                                                    class="skeleton-avatar"
                                                    style="width: 1.35rem; height: 1.35rem; border-radius: 999px"
                                                ></div>
                                                <div class="leader-skeleton-line" style="width: 85%"></div>
                                            </div>
                                        </div>

                                        <EmptyState
                                            v-else-if="!overtimeSummary.hasData && !isDashboardBootstrapping"
                                            icon="bi bi-clock"
                                            title="Belum ada data lembur"
                                            description="Assignment atau histori lembur belum tersedia pada periode ini."
                                        />

                                        <template v-else>
                                            <div class="overtime-summary-grid">
                                                <div class="overtime-total">
                                                    <span>Total Lembur</span>
                                                    <strong>{{ overtimeSummary.totalHours }} jam</strong>
                                                    <small>{{ overtimeSummary.periodLabel }}</small>
                                                </div>

                                                <div>
                                                    <span>Lembur Shift</span>
                                                    <strong>{{ overtimeSummary.shiftHours }} jam</strong>
                                                    <small>Dari long shift</small>
                                                </div>

                                                <div>
                                                    <span>Sebelum</span>
                                                    <strong>{{ overtimeSummary.beforeWorkHours }} jam</strong>
                                                    <small>Lembur awal</small>
                                                </div>

                                                <div>
                                                    <span>Setelah</span>
                                                    <strong>{{ overtimeSummary.afterWorkHours }} jam</strong>
                                                    <small>Lembur hari kerja</small>
                                                </div>

                                                <div>
                                                    <span>Libur</span>
                                                    <strong>{{ overtimeSummary.holidayHours }} jam</strong>
                                                    <small>Hari libur</small>
                                                </div>
                                            </div>

                                            <div class="module-alert is-info">
                                                <i class="bi bi-person-check"></i>
                                                <span>{{ overtimeSummary.assignmentNote }}</span>
                                            </div>

                                            <div class="overtime-assignment-panel">
                                                <div class="overtime-assignment-head">
                                                    <div>
                                                        <span>Penugasan Terbaru</span>
                                                        <strong>{{ overtimeAssignmentSummaryLabel }}</strong>
                                                    </div>
                                                    <small>{{ overtimeAssignmentRangeLabel }}</small>
                                                </div>

                                                <div
                                                    v-if="isOvertimeAssignmentLoading"
                                                    class="overtime-assignment-list"
                                                >
                                                    <div
                                                        v-for="n in 3"
                                                        :key="`mobile-overtime-assignment-skeleton-${n}`"
                                                        class="overtime-assignment-item is-skeleton"
                                                    >
                                                        <div class="leader-skeleton-line is-title mb-1"></div>
                                                        <div class="leader-skeleton-line mb-1" style="width: 80%"></div>
                                                        <div
                                                            class="leader-skeleton-line is-short"
                                                            style="width: 60%"
                                                        ></div>
                                                    </div>
                                                </div>

                                                <EmptyState
                                                    v-else-if="!overtimeAssignments.length"
                                                    compact
                                                    icon="bi bi-calendar2-check"
                                                    title="Penugasan belum ada"
                                                    description="List penugasan akan tampil saat ada data lembur."
                                                />

                                                <div v-else class="overtime-date-groups">
                                                    <div
                                                        v-for="group in overtimeAssignmentsByDate"
                                                        :key="group.dateKey"
                                                        class="overtime-date-group"
                                                        :class="{
                                                            'is-expanded': isOvertimeDateExpanded(group.dateKey),
                                                        }"
                                                    >
                                                        <button
                                                            type="button"
                                                            class="overtime-date-head is-accordion"
                                                            @click="toggleOvertimeDate(group.dateKey)"
                                                        >
                                                            <span class="overtime-date-head__left">
                                                                <i
                                                                    class="bi overtime-accordion-chevron"
                                                                    :class="
                                                                        isOvertimeDateExpanded(group.dateKey)
                                                                            ? 'bi-chevron-down'
                                                                            : 'bi-chevron-right'
                                                                    "
                                                                ></i>
                                                                <span class="overtime-date-head__date">{{
                                                                    group.dateLabel
                                                                }}</span>
                                                            </span>
                                                            <span class="overtime-date-head__right">
                                                                <span class="overtime-date-head__badges">
                                                                    <span
                                                                        v-for="badge in getOvertimeTypeBadges(
                                                                            group.items,
                                                                        )"
                                                                        :key="badge.label"
                                                                        class="overtime-type-badge"
                                                                        :class="`is-${badge.tone}`"
                                                                        >{{ badge.label }}</span
                                                                    >
                                                                </span>
                                                                <span
                                                                    class="overtime-day-chip is-mobile-tooltip"
                                                                    tabindex="0"
                                                                    role="button"
                                                                    :aria-label="group.dayCalc.plain"
                                                                    @click.stop
                                                                >
                                                                    {{ group.dayKoefisienChipLabel }}
                                                                    <i class="bi bi-info-circle overtime-info-icon"></i>

                                                                    <span class="overtime-tooltip" role="tooltip">
                                                                        <span class="overtime-tooltip__title">
                                                                            Perhitungan lembur {{ group.dateLabel }}
                                                                        </span>
                                                                        <span
                                                                            v-for="(row, ri) in group.dayCalc.rows"
                                                                            :key="ri"
                                                                            class="overtime-tooltip__row"
                                                                            :class="`is-${row.tone}`"
                                                                        >
                                                                            <span>{{ row.label }}</span>
                                                                            <strong>{{ row.value }}</strong>
                                                                        </span>
                                                                        <span class="overtime-tooltip__row is-sah">
                                                                            <span
                                                                                >Lembur sah (dibulatkan ke bawah)</span
                                                                            >
                                                                            <strong>{{
                                                                                group.dayCalc.sahLabel
                                                                            }}</strong>
                                                                        </span>
                                                                        <span class="overtime-tooltip__note">
                                                                            Pembulatan & pengurangan keterlambatan
                                                                            sesuai ketentuan HC.
                                                                        </span>
                                                                    </span>
                                                                </span>
                                                            </span>
                                                        </button>

                                                                                                                 <transition
                                                             name="accordion"
                                                             @enter="onAccordionEnter"
                                                             @after-enter="onAccordionAfterEnter"
                                                             @leave="onAccordionLeave"
                                                         >
                                                             <div
                                                                 v-show="isOvertimeDateExpanded(group.dateKey)"
                                                                 class="overtime-date-items"
                                                             >
                                                                 <button
                                                                     v-for="item in group.items"
                                                                     :key="item.key"
                                                                     type="button"
                                                                     class="overtime-assignment-item"
                                                                     :class="{ 'is-auto-shift': item.isAutoShift }"
                                                                     @click="openOvertimeAssignmentDetail(item)"
                                                                 >
                                                                     <div class="overtime-assignment-item__content">
                                                                         <div class="overtime-assignment-col-info">
                                                                             <strong class="overtime-type-title">{{ item.typeLabel }}</strong>
                                                                             <div class="overtime-time-range">
                                                                                 <i class="bi bi-clock"></i>
                                                                                 <span>{{ item.timeRangeLabel }}</span>
                                                                             </div>
                                                                             <span class="overtime-source-badge" :class="{ 'is-auto': item.isAutoShift }">
                                                                                 <i :class="item.isAutoShift ? 'bi bi-magic' : 'bi bi-hash'"></i>
                                                                                 <span>{{ item.sourceLabel }}</span>
                                                                                 <template v-if="item.isAutoShift && item.shiftUpToLabel">
                                                                                     · {{ item.shiftUpToLabel }}
                                                                                 </template>
                                                                             </span>
                                                                         </div>

                                                                         <div class="overtime-assignment-col-reason">
                                                                             <div v-if="item.reason" class="overtime-card-reason">
                                                                                 <i class="bi bi-chat-left-text"></i>
                                                                                 <p class="overtime-reason-text" :title="item.reason">{{ item.reason }}</p>
                                                                             </div>
                                                                         </div>

                                                                         <div class="overtime-assignment-col-meta">
                                                                             <span class="overtime-pill-duration">{{ item.durationLabel }}</span>
                                                                             <span class="overtime-pill-absensi" :class="`is-${item.absensiTone}`">{{ item.absensiLabel }}</span>
                                                                         </div>

                                                                         <div class="overtime-assignment-col-status">
                                                                             <span class="overtime-status" :class="`is-${item.statusTone}`">
                                                                                 <span class="overtime-status-dot"></span>
                                                                                 <span class="overtime-status-label">{{ item.statusLabel }}</span>
                                                                             </span>
                                                                         </div>
                                                                     </div>
                                                                     <i class="bi bi-chevron-right"></i>
                                                                 </button>
                                                             </div>
                                                         </transition>
                                                    </div>
                                                </div>

                                            <!-- Kontrol halaman versi ringkas untuk layar sempit. -->
                                            <div
                                                v-if="overtimeAssignmentPageCount > 1"
                                                class="overtime-assignment-pager"
                                            >
                                                <button
                                                    type="button"
                                                    class="overtime-pager-btn"
                                                    :disabled="overtimeAssignmentPage <= 1"
                                                    aria-label="Tanggal sebelumnya"
                                                    @click="gantiHalamanLembur(-1)"
                                                >
                                                    <i class="bi bi-chevron-left"></i>
                                                </button>
                                                <span class="overtime-pager-info">
                                                    {{ overtimeAssignmentPage }} / {{ overtimeAssignmentPageCount }}
                                                </span>
                                                <button
                                                    type="button"
                                                    class="overtime-pager-btn"
                                                    :disabled="overtimeAssignmentPage >= overtimeAssignmentPageCount"
                                                    aria-label="Tanggal berikutnya"
                                                    @click="gantiHalamanLembur(1)"
                                                >
                                                    <i class="bi bi-chevron-right"></i>
                                                </button>
                                            </div>
                                            </div>

                                            <div class="chart-block">
                                                <div class="chart-head">
                                                    <span>Trend Lembur — 2 Cutoff Terakhir</span>
                                                    <strong>{{ overtimeTrendLoading ? 'Memuat…' : 'Per minggu' }}</strong>
                                                </div>

                                                <div class="chart-safe">
                                                    <div
                                                        v-if="overtimeTrendLoading && !overtimeTrend.length"
                                                        class="leader-apex-chart is-skeleton"
                                                        style="height: 180px; padding: 0.8rem"
                                                    >
                                                        <div
                                                            class="leader-skeleton-line"
                                                            style="height: 100%; border-radius: 0.5rem"
                                                        ></div>
                                                    </div>

                                                    <VueApexCharts
                                                        v-else-if="overtimeTrend.length"
                                                        type="line"
                                                        height="180"
                                                        :options="overtimeChartOptions"
                                                        :series="overtimeChartSeries"
                                                    />

                                                    <EmptyState
                                                        v-else
                                                        compact
                                                        icon="bi bi-activity"
                                                        title="Trend lembur kosong"
                                                        description="Belum ada histori lembur pada 2 cutoff terakhir."
                                                    />
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </transition>
                            </article>

                            <article class="panel-card side-panel mobile-accordion-card" data-tour="submissions">
                                <button
                                    type="button"
                                    class="mobile-accordion__trigger"
                                    @click.stop="toggleMobilePanel('submissions')"
                                >
                                    <div class="module-title">
                                        <div class="module-icon is-purple">
                                            <i class="bi bi-file-earmark-text"></i>
                                        </div>

                                        <div class="module-title-copy">
                                            <span class="section-label">Pengajuan Saya</span>
                                            <h2>Status Pengajuan</h2>
                                        </div>
                                    </div>

                                    <div class="mobile-accordion__meta">
                                        <span class="side-count">{{ personalSubmissions.length }}</span>
                                        <i
                                            :class="
                                                mobilePanelState.submissions ? 'bi bi-chevron-up' : 'bi bi-chevron-down'
                                            "
                                        ></i>
                                    </div>
                                </button>

                                <transition
                                    name="accordion"
                                    @enter="onAccordionEnter"
                                    @after-enter="onAccordionAfterEnter"
                                    @leave="onAccordionLeave"
                                >
                                    <div v-show="mobilePanelState.submissions" class="mobile-accordion__body">
                                        <div class="submission-list">
                                            <div
                                                v-if="isSubmissionsLoading || isDashboardBootstrapping"
                                                class="submission-list is-skeleton-list"
                                            >
                                                <div
                                                    v-for="n in 3"
                                                    :key="`mobile-submission-skeleton-${n}`"
                                                    class="submission-item is-skeleton"
                                                >
                                                    <div>
                                                        <div
                                                            class="leader-skeleton-line is-title mb-1"
                                                            style="width: 5rem"
                                                        ></div>
                                                        <div class="leader-skeleton-line" style="width: 9rem"></div>
                                                    </div>
                                                    <div
                                                        class="leader-skeleton-line is-short"
                                                        style="width: 4rem"
                                                    ></div>
                                                </div>
                                            </div>

                                            <template v-else>
                                                <button
                                                    v-for="item in paginatedSubmissions"
                                                    :key="item.id"
                                                    type="button"
                                                    class="submission-item"
                                                    @click="handleSubmissionClick(item)"
                                                >
                                                    <div>
                                                        <strong>{{ item.title }}</strong>
                                                        <span
                                                            >{{ item.submittedDateLabel || item.date }} •
                                                            {{ item.description }}</span
                                                        >
                                                        <span
                                                            v-if="item.detailLabel || item.periodLabel"
                                                            class="submission-item__detail"
                                                        >
                                                            <template v-if="item.detailLabel">{{
                                                                item.detailLabel
                                                            }}</template>
                                                            <template v-if="item.detailLabel && item.periodLabel">
                                                                •
                                                            </template>
                                                            <template v-if="item.periodLabel">{{
                                                                item.periodLabel
                                                            }}</template>
                                                        </span>
                                                        <em v-if="item.expiryNote">{{ item.expiryNote }}</em>
                                                    </div>

                                                    <small :class="`is-${item.status}`">
                                                        {{ item.statusLabel }}
                                                    </small>
                                                </button>

                                                <div v-if="submissionsTotalPages > 1" class="submission-pagination">
                                                    <button
                                                        type="button"
                                                        class="submission-pagination__btn"
                                                        :disabled="submissionsPage <= 1"
                                                        @click="goToSubmissionsPage(submissionsPage - 1)"
                                                    >
                                                        <i class="bi bi-chevron-left"></i>
                                                    </button>
                                                    <span class="submission-pagination__info">
                                                        {{ submissionsPage }} / {{ submissionsTotalPages }}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        class="submission-pagination__btn"
                                                        :disabled="submissionsPage >= submissionsTotalPages"
                                                        @click="goToSubmissionsPage(submissionsPage + 1)"
                                                    >
                                                        <i class="bi bi-chevron-right"></i>
                                                    </button>
                                                </div>

                                                <EmptyState
                                                    v-if="!isDashboardBootstrapping && !personalSubmissions.length"
                                                    compact
                                                    icon="bi bi-file-earmark-check"
                                                    title="Belum ada pengajuan"
                                                    description="Pengajuan aktif akan muncul di sini."
                                                />
                                            </template>
                                        </div>
                                    </div>
                                </transition>
                            </article>

                            <article v-if="false" class="panel-card side-panel mobile-accordion-card">
                                <button
                                    type="button"
                                    class="mobile-accordion__trigger"
                                    @click.stop="toggleMobilePanel('upcoming')"
                                >
                                    <div class="module-title">
                                        <div class="module-icon is-info mobile-module-icon">
                                            <i class="bi bi-calendar3"></i>
                                        </div>

                                        <div class="module-title-copy">
                                            <span class="section-label">Jadwal Berikutnya</span>
                                            <h2>Upcoming</h2>
                                        </div>
                                    </div>

                                    <div class="mobile-accordion__meta">
                                        <span class="side-count">{{ upcomingResolved.length }}</span>
                                        <i
                                            :class="
                                                mobilePanelState.upcoming ? 'bi bi-chevron-up' : 'bi bi-chevron-down'
                                            "
                                        ></i>
                                    </div>
                                </button>

                                <transition
                                    name="accordion"
                                    @enter="onAccordionEnter"
                                    @after-enter="onAccordionAfterEnter"
                                    @leave="onAccordionLeave"
                                >
                                    <div v-show="mobilePanelState.upcoming" class="mobile-accordion__body">
                                        <div class="schedule-list">
                                            <div
                                                v-if="isUpcomingLoading || isDashboardBootstrapping"
                                                class="schedule-list is-skeleton-list"
                                            >
                                                <div
                                                    v-for="n in 3"
                                                    :key="`mobile-upcoming-skeleton-${n}`"
                                                    class="schedule-item is-skeleton"
                                                >
                                                    <div class="schedule-date skeleton-avatar"></div>
                                                    <div>
                                                        <div
                                                            class="leader-skeleton-line is-title mb-1"
                                                            style="width: 7rem"
                                                        ></div>
                                                        <div class="leader-skeleton-line" style="width: 9rem"></div>
                                                    </div>
                                                    <div
                                                        class="leader-skeleton-line is-short"
                                                        style="width: 3rem"
                                                    ></div>
                                                </div>
                                            </div>

                                            <template v-else>
                                                <div
                                                    v-for="item in compactUpcoming"
                                                    :key="`${item.date}-${item.label}`"
                                                    role="button"
                                                    tabindex="0"
                                                    class="schedule-item is-clickable"
                                                    @click="openSelfShift()"
                                                    @keydown.enter.prevent="openSelfShift()"
                                                    @keydown.space.prevent="openSelfShift()"
                                                >
                                                    <div class="schedule-date">
                                                        <strong>{{ item.date }}</strong>
                                                    </div>

                                                    <div>
                                                        <strong>{{ item.label }}</strong>
                                                        <span>{{ item.time }}</span>
                                                    </div>

                                                    <small>{{ item.badge }}</small>
                                                </div>

                                                <EmptyState
                                                    v-if="!isDashboardBootstrapping && !compactUpcoming.length"
                                                    compact
                                                    icon="bi bi-calendar2"
                                                    title="Jadwal kosong"
                                                    description="Belum ada jadwal berikutnya."
                                                />
                                            </template>
                                        </div>
                                    </div>
                                </transition>
                            </article>
                        </section>

                        <section :ref="(el) => setMobileSectionRef('absensi', el)" class="mobile-section">
                            <section class="panel-card attendance-panel mobile-attendance-panel mobile-accordion-card">
                                <button
                                    class="mobile-accordion__trigger"
                                    @click.stop="toggleMobilePanel('kalenderCutoff')"
                                >
                                    <div class="module-title">
                                        <div class="module-icon is-emerald">
                                            <i class="bi bi-calendar2-range"></i>
                                        </div>
                                        <div class="module-title-copy">
                                            <span class="section-label">Kalender Cutoff</span>
                                            <h2>Pantauan Absensi Saya</h2>
                                        </div>
                                    </div>
                                    <div class="mobile-accordion__meta">
                                        <span
                                            v-if="!isAttendanceCalendarLoading && calendarAttendanceIssueCount > 0"
                                            class="side-count is-danger"
                                        >
                                            {{ calendarAttendanceIssueCount }}
                                        </span>
                                        <i
                                            :class="
                                                mobilePanelState.kalenderCutoff
                                                    ? 'bi bi-chevron-up'
                                                    : 'bi bi-chevron-down'
                                            "
                                        ></i>
                                    </div>
                                </button>

                                <transition
                                    name="accordion"
                                    @enter="onAccordionEnter"
                                    @after-enter="onAccordionAfterEnter"
                                    @leave="onAccordionLeave"
                                >
                                    <div v-show="mobilePanelState.kalenderCutoff" class="mobile-accordion__content">
                                        <div class="mobile-card-head mobile-card-head--stack">
                                            <div class="calendar-filters">
                                                <select
                                                    class="cutoff-period-select"
                                                    :value="selectedCutoffPeriod"
                                                    :disabled="isAttendanceCalendarLoading"
                                                    @change="handleCutoffPeriodChange"
                                                >
                                                    <option
                                                        v-for="period in cutoffPeriodOptions"
                                                        :key="period.value"
                                                        :value="period.value"
                                                    >
                                                        {{ period.label }}
                                                    </option>
                                                </select>

                                                <button
                                                    v-for="filter in calendarFilters"
                                                    :key="filter.key"
                                                    type="button"
                                                    class="filter-chip"
                                                    :class="{ 'is-active': activeCalendarFilter === filter.key }"
                                                    @click="activeCalendarFilter = filter.key"
                                                >
                                                    {{ filter.label }}
                                                </button>
                                            </div>
                                        </div>

                                        <template v-if="isAttendanceCalendarLoading">
                                            <div class="attendance-summary-row">
                                                <div
                                                    v-for="n in 5"
                                                    :key="`mobile-summary-skeleton-${n}`"
                                                    class="summary-pill is-skeleton"
                                                >
                                                    <div
                                                        class="leader-skeleton-line is-title mb-1"
                                                        style="width: 45%; margin: 0 auto"
                                                    ></div>
                                                    <div
                                                        class="leader-skeleton-line is-short"
                                                        style="width: 70%; margin: 0 auto"
                                                    ></div>
                                                </div>
                                            </div>

                                            <div class="calendar-scroll">
                                                <div class="calendar-grid is-skeleton-grid">
                                                    <div
                                                        v-for="n in 14"
                                                        :key="`mobile-calendar-skeleton-${n}`"
                                                        class="calendar-day is-skeleton"
                                                    >
                                                        <div class="calendar-day__top">
                                                            <div class="calendar-day__date">
                                                                <div
                                                                    class="leader-skeleton-line is-title mb-1"
                                                                    style="width: 1.4rem"
                                                                ></div>
                                                                <div
                                                                    class="leader-skeleton-line is-short"
                                                                    style="width: 2.2rem"
                                                                ></div>
                                                            </div>
                                                            <div
                                                                class="leader-skeleton-line is-short"
                                                                style="width: 3.2rem"
                                                            ></div>
                                                        </div>
                                                        <div class="leader-skeleton-line" style="width: 70%"></div>
                                                        <div
                                                            class="leader-skeleton-line is-short"
                                                            style="width: 50%"
                                                        ></div>
                                                        <div class="calendar-day__times">
                                                            <div>
                                                                <div
                                                                    class="leader-skeleton-line is-short"
                                                                    style="width: 0.9rem"
                                                                ></div>
                                                                <div
                                                                    class="leader-skeleton-line is-short"
                                                                    style="width: 2rem"
                                                                ></div>
                                                            </div>
                                                            <div>
                                                                <div
                                                                    class="leader-skeleton-line is-short"
                                                                    style="width: 0.9rem"
                                                                ></div>
                                                                <div
                                                                    class="leader-skeleton-line is-short"
                                                                    style="width: 2rem"
                                                                ></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>

                                        <template v-else>
                                            <EmptyState
                                                v-if="!isDashboardBootstrapping && !calendarCutoffDays.length"
                                                compact
                                                icon="bi bi-calendar2"
                                                title="Data absensi belum tersedia"
                                                description="Dashboard sudah dimuat, tetapi belum ada data absensi untuk periode ini."
                                            />

                                            <template v-else>
                                                <div class="attendance-summary-row">
                                                    <div class="summary-pill is-success">
                                                        <strong>{{ calendarPresentDayCount }}</strong>
                                                        <span>Hadir</span>
                                                    </div>

                                                    <div class="summary-pill is-success">
                                                        <strong>{{ calendarOnTimeDayCount }}</strong>
                                                        <span>On-time</span>
                                                    </div>

                                                    <div class="summary-pill is-warning">
                                                        <strong>{{ calendarLateDayCount }}</strong>
                                                        <span>Terlambat</span>
                                                    </div>

                                                    <div class="summary-pill is-danger">
                                                        <strong>{{ calendarAttendanceIssueCount }}</strong>
                                                        <span>Issue</span>
                                                    </div>

                                                    <div class="summary-pill is-info">
                                                        <strong>{{
                                                            countCalendarByStatus('izin') +
                                                            countCalendarByStatus('cuti')
                                                        }}</strong>
                                                        <span>Izin / Cuti</span>
                                                    </div>
                                                    <div class="summary-pill is-info">
                                                        <strong>{{ countCalendarByStatus('sakit') }}</strong>
                                                        <span>Sakit</span>
                                                    </div>
                                                </div>

                                                <div class="calendar-scroll">
                                                    <div class="calendar-grid">
                                                        <button
                                                            v-for="day in filteredCutoffDays"
                                                            :key="day.date"
                                                            type="button"
                                                            class="calendar-day"
                                                            :class="[
                                                                `is-${day.primaryStatusTone}`,
                                                                {
                                                                    'is-today': day.isToday,
                                                                    'has-problem': day.hasProblem,
                                                                },
                                                            ]"
                                                            @click="openDayModal(day)"
                                                        >
                                                            <div class="calendar-day__top">
                                                                <div class="calendar-day__date">
                                                                    <strong>{{ day.dayNumber }}</strong>
                                                                    <span class="calendar-day__weekday">
                                                                        {{ day.dayName || '-' }}
                                                                    </span>
                                                                </div>
                                                                <span :class="`is-${day.primaryStatusTone}`">
                                                                    {{ day.primaryStatusLabel }}
                                                                </span>
                                                            </div>

                                                            <div
                                                                class="calendar-day__shift"
                                                                :title="day.shiftLabel || 'OFF'"
                                                            >
                                                                {{ day.shiftLabel || 'OFF' }}
                                                            </div>

                                                            <div
                                                                v-if="
                                                                    day.statusChips.some(
                                                                        (c) =>
                                                                            !(
                                                                                c.label === day.primaryStatusLabel &&
                                                                                c.tone === day.primaryStatusTone
                                                                            ),
                                                                    )
                                                                "
                                                                class="calendar-day__chips"
                                                            >
                                                                <span
                                                                    v-for="chip in day.statusChips.filter(
                                                                        (c) =>
                                                                            !(
                                                                                c.label === day.primaryStatusLabel &&
                                                                                c.tone === day.primaryStatusTone
                                                                            ),
                                                                    )"
                                                                    :key="chip.key"
                                                                    :class="`is-${chip.tone}`"
                                                                >
                                                                    {{ chip.label }}
                                                                </span>
                                                            </div>

                                                            <div class="calendar-day__times">
                                                                <div>
                                                                    <small>IN</small>
                                                                    <strong>{{ day.checkIn || '-' }}</strong>
                                                                </div>

                                                                <div>
                                                                    <small>OUT</small>
                                                                    <strong>{{ day.checkOut || '-' }}</strong>
                                                                </div>
                                                            </div>
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                        </template>
                                    </div>
                                </transition>
                            </section>
                        </section>
                    </div>
                </div>
            </template>

            <template v-else>
                <!-- HERO -->
                <section class="personal-hero" data-tour="hero">
                    <div class="hero-orb hero-orb--one"></div>
                    <div class="hero-orb hero-orb--two"></div>

                    <div class="personal-hero__content">
                        <div class="personal-hero__left">
                            <div class="hero-topline">
                                <div class="hero-period">
                                    <i class="bi bi-calendar3"></i>
                                    <span>Cut off Periode {{ cutoffMonthLabel }}, {{ cutoffLabel }}</span>
                                </div>

                                <div class="hero-role-pill">
                                    <i :class="isLeader ? 'bi bi-people-fill' : 'bi bi-person-badge'"></i>
                                    <span>{{ dashboardRoleLabel }}</span>
                                </div>
                            </div>

                            <div class="hero-title-row">
                                <div class="hero-title-copy">
                                    <h1>{{ heroSummary.title }}</h1>
                                    <p>{{ heroSummary.message }}</p>
                                </div>
                            </div>

                            <div class="today-status-strip">
                                <template v-if="isHeroAttendanceLoading">
                                    <div
                                        v-for="n in 4"
                                        :key="`today-chip-skeleton-${n}`"
                                        class="today-chip is-skeleton"
                                        :class="{ 'is-wide': n === 1 }"
                                    >
                                        <div class="leader-skeleton-line is-short mb-1"></div>
                                        <div class="leader-skeleton-line is-title mb-1" style="width: 70%"></div>
                                        <div class="leader-skeleton-line" style="width: 80%"></div>
                                    </div>
                                </template>

                                <template v-else>
                                    <div class="today-chip is-wide">
                                        <span>Shift Hari Ini</span>
                                        <strong>{{ todaySnapshot.shiftLabel }}</strong>
                                        <small>{{ todaySnapshot.shiftTime }}</small>
                                    </div>

                                    <div class="today-chip">
                                        <span>Check In</span>
                                        <strong>{{ todaySnapshot.checkIn }}</strong>
                                        <small>{{ todaySnapshot.checkInMeta }}</small>
                                    </div>

                                    <div class="today-chip">
                                        <span>Check Out</span>
                                        <strong>{{ todaySnapshot.checkOut }}</strong>
                                        <small>{{ todaySnapshot.checkOutMeta }}</small>
                                    </div>

                                    <div class="today-chip">
                                        <span>Status</span>
                                        <strong>{{ todaySnapshot.statusLabel }}</strong>
                                        <small>{{ todaySnapshot.statusMeta }}</small>
                                    </div>
                                </template>

                                <div class="">
                                    <button
                                        v-if="isLeader"
                                        @click="handleVisit('/homepage/leader-dashboard')"
                                        type="button"
                                        class="leader-shortcut-btn"
                                        data-tour="team-dashboard-btn"
                                        :class="{ 'has-badge': teamDashboardBadgeVisible }"
                                    >
                                        <i class="bi bi-grid-1x2-fill"></i>
                                        Dashboard Tim
                                        <i class="bi bi-arrows-angle-expand ms-2"></i>
                                        <span
                                            v-if="teamDashboardBadgeVisible"
                                            :key="teamDashboardBadgeLabel"
                                            class="team-dashboard-badge"
                                        >
                                            {{ teamDashboardBadgeLabel }}
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="personal-hero__right">
                            <section class="workday-brief-card">
                                <div class="brief-head">
                                    <div v-if="isWorkdayBriefLoading">
                                        <div class="leader-skeleton-line is-short mb-1" style="width: 5rem"></div>
                                        <div class="leader-skeleton-line is-title mb-1" style="width: 8rem"></div>
                                        <div class="leader-skeleton-line" style="width: 7rem"></div>
                                    </div>

                                    <div v-else>
                                        <span>Workday Brief</span>
                                        <strong>{{ workdayBrief.mainValue }}</strong>
                                        <small>{{ workdayBrief.mainLabel }}</small>
                                    </div>

                                    <div class="brief-icon">
                                        <i class="bi bi-speedometer2"></i>
                                    </div>
                                </div>

                                <div class="brief-grid">
                                    <template v-if="isWorkdayBriefLoading">
                                        <div v-for="n in 4" :key="`brief-grid-skeleton-${n}`" class="is-skeleton">
                                            <div class="leader-skeleton-line is-short mb-1"></div>
                                            <div class="leader-skeleton-line is-title" style="width: 70%"></div>
                                        </div>
                                    </template>

                                    <template v-else>
                                        <div>
                                            <span>Cutoff Day</span>
                                            <strong>{{ workdayBrief.cutoffProgress }}</strong>
                                        </div>

                                        <div>
                                            <span>Next Schedule</span>
                                            <strong>{{ workdayBrief.nextSchedule }}</strong>
                                        </div>

                                        <div>
                                            <span>KPI Periode</span>
                                            <strong>{{ heroKpi.periodLabel }}</strong>
                                        </div>

                                        <div>
                                            <span>LMS Progress</span>
                                            <strong>{{ heroLms.progressLabel }}</strong>
                                        </div>
                                    </template>
                                </div>

                                <div v-if="isWorkdayBriefLoading" class="brief-note is-skeleton">
                                    <div>
                                        <div class="leader-skeleton-line is-short mb-1"></div>
                                        <div class="leader-skeleton-line is-title" style="width: 4rem"></div>
                                    </div>
                                    <div class="leader-skeleton-line" style="width: 7rem"></div>
                                </div>

                                <div v-else class="brief-note">
                                    <div>
                                        <span>Approval Menunggu</span>
                                        <strong>{{ pendingApprovalsResolved }}</strong>
                                    </div>
                                    <small>Butuh tindakan leader.</small>
                                </div>
                            </section>
                        </div>
                    </div>
                </section>

                <div v-if="dashboardError" class="dashboard-alert">
                    <div>
                        <strong>Data dashboard gagal dimuat</strong>
                        <span>{{ dashboardError }}</span>
                    </div>

                    <button type="button" class="dashboard-alert__action" @click="retryDashboardLoad">Coba lagi</button>
                </div>

                <!-- TOP STATS -->
                <section class="awareness-strip" data-tour="awareness" data-lazy-domain="leave">
                    <button
                        v-for="item in awarenessCards"
                        :key="item.key"
                        type="button"
                        class="awareness-card"
                        :class="[`is-${item.tone}`, { 'is-skeleton': item.loading }]"
                        @click="handleAwarenessClick(item.key)"
                    >
                        <div v-if="item.loading" class="awareness-card__icon skeleton-avatar"></div>

                        <div v-else class="awareness-card__icon">
                            <i :class="item.icon"></i>
                        </div>

                        <div v-if="item.loading" class="awareness-card__copy">
                            <div class="leader-skeleton-line is-title mb-1" style="width: 45%"></div>
                            <div class="leader-skeleton-line mb-1" style="width: 75%"></div>
                            <div class="leader-skeleton-line is-short"></div>
                        </div>

                        <div v-else class="awareness-card__copy">
                            <strong>{{ item.value }}</strong>
                            <span>{{ item.label }}</span>
                            <small>{{ item.description }}</small>
                        </div>

                        <i class="bi bi-arrow-right-short awareness-card__arrow"></i>
                    </button>
                </section>

                <!-- MAIN GRID -->
                <section class="dashboard-grid">
                    <main class="dashboard-main">
                        <!-- ATTENDANCE CALENDAR -->
                        <section ref="attendanceCalendarSectionRef" class="panel-card attendance-panel">
                            <div class="panel-head is-tight">
                                <div>
                                    <span class="section-label">Kalender Cutoff</span>
                                    <h2>Pantauan Absensi Saya</h2>
                                </div>

                                <div class="calendar-filters">
                                    <select
                                        class="cutoff-period-select"
                                        :value="selectedCutoffPeriod"
                                        :disabled="isAttendanceCalendarLoading"
                                        @change="handleCutoffPeriodChange"
                                    >
                                        <option
                                            v-for="period in cutoffPeriodOptions"
                                            :key="period.value"
                                            :value="period.value"
                                        >
                                            {{ period.label }}
                                        </option>
                                    </select>

                                    <button
                                        v-for="filter in calendarFilters"
                                        :key="filter.key"
                                        type="button"
                                        class="filter-chip"
                                        :class="{ 'is-active': activeCalendarFilter === filter.key }"
                                        @click="activeCalendarFilter = filter.key"
                                    >
                                        {{ filter.label }}
                                    </button>
                                </div>
                            </div>

                            <template v-if="isAttendanceCalendarLoading">
                                <div class="attendance-summary-row">
                                    <div v-for="n in 5" :key="`summary-skeleton-${n}`" class="summary-pill is-skeleton">
                                        <div
                                            class="leader-skeleton-line is-title mb-1"
                                            style="width: 45%; margin: 0 auto"
                                        ></div>
                                        <div
                                            class="leader-skeleton-line is-short"
                                            style="width: 70%; margin: 0 auto"
                                        ></div>
                                    </div>
                                </div>

                                <div class="calendar-scroll">
                                    <div class="calendar-grid is-skeleton-grid">
                                        <div
                                            v-for="n in 14"
                                            :key="`calendar-skeleton-${n}`"
                                            class="calendar-day is-skeleton"
                                        >
                                            <div class="calendar-day__top">
                                                <div class="calendar-day__date">
                                                    <div
                                                        class="leader-skeleton-line is-title mb-1"
                                                        style="width: 1.4rem"
                                                    ></div>
                                                    <div
                                                        class="leader-skeleton-line is-short"
                                                        style="width: 2.2rem"
                                                    ></div>
                                                </div>
                                                <div class="leader-skeleton-line is-short" style="width: 3.2rem"></div>
                                            </div>
                                            <div class="leader-skeleton-line" style="width: 70%"></div>
                                            <div class="leader-skeleton-line is-short" style="width: 50%"></div>
                                            <div class="calendar-day__times">
                                                <div>
                                                    <div
                                                        class="leader-skeleton-line is-short"
                                                        style="width: 0.9rem"
                                                    ></div>
                                                    <div
                                                        class="leader-skeleton-line is-short"
                                                        style="width: 2.2rem"
                                                    ></div>
                                                </div>
                                                <div>
                                                    <div
                                                        class="leader-skeleton-line is-short"
                                                        style="width: 0.9rem"
                                                    ></div>
                                                    <div
                                                        class="leader-skeleton-line is-short"
                                                        style="width: 2.2rem"
                                                    ></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <template v-else>
                                <EmptyState
                                    v-if="!calendarCutoffDays.length"
                                    compact
                                    icon="bi bi-calendar2"
                                    title="Data absensi belum tersedia"
                                    description="Dashboard sudah dimuat, tetapi belum ada data absensi untuk periode ini."
                                />

                                <template v-else>
                                    <div class="attendance-summary-row">
                                        <div class="summary-pill is-success">
                                            <strong>{{ calendarPresentDayCount }}</strong>
                                            <span>Hadir</span>
                                        </div>

                                        <div class="summary-pill is-success">
                                            <strong>{{ calendarOnTimeDayCount }}</strong>
                                            <span>On-time</span>
                                        </div>

                                        <div class="summary-pill is-warning">
                                            <strong>{{ calendarLateDayCount }}</strong>
                                            <span>Terlambat</span>
                                        </div>

                                        <div class="summary-pill is-danger">
                                            <strong>{{ calendarAttendanceIssueCount }}</strong>
                                            <span>Issue</span>
                                        </div>

                                        <div class="summary-pill is-info">
                                            <strong>{{
                                                countCalendarByStatus('izin') + countCalendarByStatus('cuti')
                                            }}</strong>
                                            <span>Izin / Cuti</span>
                                        </div>
                                        <div class="summary-pill is-info">
                                            <strong>{{ countCalendarByStatus('sakit') }}</strong>
                                            <span>Sakit</span>
                                        </div>
                                    </div>

                                    <div class="calendar-scroll">
                                        <div class="calendar-grid">
                                            <button
                                                v-for="day in filteredCutoffDays"
                                                :key="day.date"
                                                type="button"
                                                class="calendar-day"
                                                :class="[
                                                    `is-${day.primaryStatusTone}`,
                                                    {
                                                        'is-today': day.isToday,
                                                        'has-problem': day.hasProblem,
                                                    },
                                                ]"
                                                @click="openDayModal(day)"
                                            >
                                                <div class="calendar-day__top">
                                                    <div class="calendar-day__date">
                                                        <strong>{{ day.dayNumber }}</strong>
                                                        <span class="calendar-day__weekday">
                                                            {{ day.dayName || '-' }}
                                                        </span>
                                                    </div>
                                                    <span :class="`is-${day.primaryStatusTone}`">
                                                        {{ day.primaryStatusLabel }}
                                                    </span>
                                                </div>

                                                <div class="calendar-day__shift" :title="day.shiftLabel || 'OFF'">
                                                    {{ day.shiftLabel || 'OFF' }}
                                                </div>

                                                <div
                                                    v-if="
                                                        day.statusChips.some(
                                                            (c) =>
                                                                !(
                                                                    c.label === day.primaryStatusLabel &&
                                                                    c.tone === day.primaryStatusTone
                                                                ),
                                                        )
                                                    "
                                                    class="calendar-day__chips"
                                                >
                                                    <span
                                                        v-for="chip in day.statusChips.filter(
                                                            (c) =>
                                                                !(
                                                                    c.label === day.primaryStatusLabel &&
                                                                    c.tone === day.primaryStatusTone
                                                                ),
                                                        )"
                                                        :key="chip.key"
                                                        :class="`is-${chip.tone}`"
                                                    >
                                                        {{ chip.label }}
                                                    </span>
                                                </div>

                                                <div class="calendar-day__times">
                                                    <div>
                                                        <small>IN</small>
                                                        <strong>{{ day.checkIn || '-' }}</strong>
                                                    </div>

                                                    <div>
                                                        <small>OUT</small>
                                                        <strong>{{ day.checkOut || '-' }}</strong>
                                                    </div>
                                                </div>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </template>
                        </section>
                        <section class="module-top-grid">
                            <!-- HCIS -->
                            <article class="panel-card module-card hcis-insight-card" data-tour="module-hcis">
                                <div class="module-card__head">
                                    <div class="module-title">
                                        <div class="module-icon is-indigo">
                                            <i class="bi bi-fingerprint"></i>
                                        </div>

                                        <div class="module-title-copy">
                                            <span class="section-label">Absensi</span>
                                            <h2>Ringkasan HCIS Saya</h2>
                                        </div>
                                    </div>

                                    <span
                                        class="mini-badge"
                                        :class="{ 'is-danger': hcisPersonal.stats.attendanceIssues > 0 }"
                                    >
                                        {{ hcisPersonal.stats.attendanceIssues }} issue
                                    </span>
                                </div>

                                <template v-if="isHcisLoading || isDashboardBootstrapping">
                                    <div class="leader-summary-split">
                                        <div class="leader-score-card is-hcis is-skeleton">
                                            <span>Status Hari Ini</span>
                                            <div
                                                class="leader-skeleton-line is-title mt-2 mb-2"
                                                style="width: 50%"
                                            ></div>
                                            <div class="leader-skeleton-line" style="width: 90%"></div>
                                            <div class="leader-skeleton-line is-short mt-1" style="width: 60%"></div>
                                        </div>

                                        <div class="leader-mini-stat-grid">
                                            <div
                                                v-for="n in 4"
                                                :key="`personal-hcis-skeleton-${n}`"
                                                class="is-skeleton"
                                            >
                                                <div
                                                    class="leader-skeleton-line is-short mb-1"
                                                    style="width: 40%"
                                                ></div>
                                                <div
                                                    class="leader-skeleton-line is-title mt-1 mb-1"
                                                    style="width: 60%"
                                                ></div>
                                                <div
                                                    class="leader-skeleton-line is-short mt-1"
                                                    style="width: 80%"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="line-chart-card">
                                        <div class="line-chart-card__head">
                                            <div>
                                                <div
                                                    class="leader-skeleton-line is-title mb-1"
                                                    style="width: 9rem"
                                                ></div>
                                                <div class="leader-skeleton-line is-short" style="width: 6rem"></div>
                                            </div>
                                            <div class="leader-skeleton-line is-short" style="width: 4rem"></div>
                                        </div>
                                        <div class="leader-apex-chart" style="height: 120px; padding: 0.8rem">
                                            <div
                                                class="leader-skeleton-line"
                                                style="height: 100%; border-radius: 0.5rem"
                                            ></div>
                                        </div>
                                    </div>
                                </template>

                                <template v-else>
                                    <div class="leader-summary-split">
                                        <button
                                            type="button"
                                            class="leader-score-card is-hcis is-clickable"
                                            @click="todayRecord ? openDayModal(todayRecord) : null"
                                        >
                                            <span>Status Hari Ini</span>
                                            <strong>{{ todaySnapshot.statusLabel }}</strong>
                                            <small
                                                >{{ todaySnapshot.shiftTime }} - IN {{ todaySnapshot.checkIn }} / OUT
                                                {{ todaySnapshot.checkOut }}</small
                                            >
                                        </button>

                                        <div class="leader-mini-stat-grid">
                                            <div
                                                v-for="item in hcisPersonalStats"
                                                :key="item.key"
                                                :class="`is-${item.tone}`"
                                            >
                                                <span>{{ item.label }}</span>
                                                <strong>{{ item.value }}</strong>
                                                <small>{{ item.caption }}</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="line-chart-card">
                                        <div class="line-chart-card__head">
                                            <div>
                                                <strong>Persentase Kehadiran</strong>
                                                <span>{{ hcisTrendScopeLabel }}</span>
                                            </div>
                                            <small>{{ hcisTrendLatestLabel }}</small>
                                        </div>

                                        <div class="leader-apex-chart is-success">
                                            <VueApexCharts
                                                v-if="hcisPersonal.trend.length"
                                                type="area"
                                                height="120"
                                                :options="hcisTrendChartOptions"
                                                :series="hcisTrendChartSeries"
                                            />

                                            <EmptyState
                                                v-else
                                                compact
                                                icon="bi bi-activity"
                                                title="Trend HCIS kosong"
                                                description="Belum ada histori absensi."
                                            />
                                        </div>
                                    </div>
                                </template>
                            </article>

                            <!-- KPI -->
                            <article class="panel-card module-card" data-tour="module-kpi">
                                <div class="module-card__head">
                                    <div class="module-title">
                                        <div class="module-icon is-purple">
                                            <i class="bi bi-graph-up-arrow"></i>
                                        </div>

                                        <div class="module-title-copy">
                                            <span class="section-label">KPI Saya</span>
                                            <h2>KPI Parameter {{ heroKpi.yearLabel }}</h2>
                                        </div>
                                    </div>

                                    <span class="period-badge is-purple">{{ heroKpi.parameterPeriod }}</span>
                                </div>

                                <button
                                    v-if="!kpiRevealed"
                                    type="button"
                                    class="module-reveal module-reveal--purple"
                                    @click="revealKpi"
                                >
                                    <span class="module-reveal__aura"></span>
                                    <span class="module-reveal__icon"><i class="bi bi-graph-up-arrow"></i></span>
                                    <span class="module-reveal__title">Lihat Detail KPI</span>
                                    <span class="module-reveal__desc"
                                        >Skor terpilih, parameter, achievement YTD, dan tren 6 bulan.</span
                                    >
                                    <span class="module-reveal__cta">
                                        <i class="bi bi-lightning-charge-fill"></i>
                                        <span>Muat data</span>
                                    </span>
                                </button>

                                <div v-else class="kpi-detail-stack" :class="{ 'is-refreshing': isKpiRefreshing }">
                                    <!-- Refresh indicator -->
                                    <div v-if="isKpiRefreshing" class="kpi-refresh-state">
                                        <i class="bi bi-arrow-repeat"></i>
                                        <span>Memuat KPI {{ pendingKpiSelectionLabel }}...</span>
                                        <b></b>
                                    </div>

                                    <!-- Warnings (only when data is loaded) -->
                                    <div
                                        v-if="
                                            !isKpiLoading &&
                                            !isDashboardBootstrapping &&
                                            !isKpiRefreshing &&
                                            heroKpi.warningItems.length
                                        "
                                        class="kpi-warning-list"
                                    >
                                        <div
                                            v-for="warning in heroKpi.warningItems"
                                            :key="warning.key"
                                            class="inline-warning"
                                            :class="warning.tone"
                                        >
                                            <i :class="warning.icon"></i>
                                            <span>{{ warning.text }}</span>
                                        </div>
                                    </div>

                                    <!-- Score + Selects (always same layout) -->
                                    <div class="kpi-layout">
                                        <!-- Score card: skeleton or real -->
                                        <div class="kpi-score-card">
                                            <template v-if="isKpiLoading || isDashboardBootstrapping">
                                                <div
                                                    class="leader-skeleton-line is-title mb-2"
                                                    style="width: 55%; background: rgba(255, 255, 255, 0.3)"
                                                ></div>
                                                <div
                                                    class="leader-skeleton-line mb-1"
                                                    style="width: 70%; background: rgba(255, 255, 255, 0.2)"
                                                ></div>
                                                <div
                                                    class="leader-skeleton-line is-short"
                                                    style="background: rgba(255, 255, 255, 0.15)"
                                                ></div>
                                                <div
                                                    class="kpi-score-card__mini"
                                                    style="background: rgba(255, 255, 255, 0.12)"
                                                >
                                                    <div
                                                        class="leader-skeleton-line is-short mb-1"
                                                        style="width: 65%; background: rgba(255, 255, 255, 0.28)"
                                                    ></div>
                                                    <div
                                                        class="leader-skeleton-line is-title mb-1"
                                                        style="width: 48%; background: rgba(255, 255, 255, 0.32)"
                                                    ></div>
                                                    <div
                                                        class="leader-skeleton-line is-short"
                                                        style="width: 58%; background: rgba(255, 255, 255, 0.22)"
                                                    ></div>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <strong>{{ heroKpi.score }}</strong>
                                                <span>Skor Terpilih</span>
                                                <small>{{ heroKpi.performancePeriod }}</small>
                                                <div class="kpi-score-card__mini">
                                                    <span>Achievement YTD</span>
                                                    <strong>{{ heroKpi.ytdScore }}%</strong>
                                                    <small>{{ heroKpi.ytdSubmittedCount }} periode terisi</small>
                                                </div>
                                            </template>
                                        </div>

                                        <!-- Right: selects always usable + stats -->
                                        <div class="kpi-detail-stack">
                                            <div class="kpi-filter-row">
                                                <select
                                                    :value="heroKpi.year"
                                                    :disabled="
                                                        isKpiRefreshing || isKpiLoading || isDashboardBootstrapping
                                                    "
                                                    @change="handleKpiYearChange"
                                                >
                                                    <option
                                                        v-for="year in heroKpi.yearOptions"
                                                        :key="`kpi-year-${year}`"
                                                        :value="year"
                                                    >
                                                        {{ year }}
                                                    </option>
                                                </select>
                                                <select
                                                    :value="heroKpi.selectedPeriod"
                                                    :disabled="
                                                        isKpiRefreshing || isKpiLoading || isDashboardBootstrapping
                                                    "
                                                    @change="handleKpiPeriodChange"
                                                >
                                                    <option
                                                        v-for="period in heroKpi.periodOptions"
                                                        :key="`kpi-period-${period.value}`"
                                                        :value="period.value"
                                                    >
                                                        {{ period.label }}
                                                    </option>
                                                </select>
                                            </div>

                                            <!-- Stats: skeleton or real -->
                                            <template v-if="isKpiLoading || isDashboardBootstrapping">
                                                <div class="kpi-stat-grid" style="grid-template-columns: 1fr">
                                                    <div class="is-skeleton">
                                                        <div class="leader-skeleton-line is-short mb-1"></div>
                                                        <div
                                                            class="leader-skeleton-line is-title mb-1"
                                                            style="width: 55%"
                                                        ></div>
                                                        <div class="leader-skeleton-line" style="width: 70%"></div>
                                                    </div>
                                                </div>
                                                <div class="kpi-status-panel">
                                                    <div v-for="n in 2" :key="`kpi-stat-sk-${n}`" class="is-skeleton">
                                                        <div class="leader-skeleton-line is-short mb-1"></div>
                                                        <div
                                                            class="leader-skeleton-line is-title"
                                                            style="width: 65%"
                                                        ></div>
                                                    </div>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <div class="kpi-stat-grid" style="grid-template-columns: 1fr 1fr">
                                                    <div>
                                                        <span>Achievement Grade</span>
                                                        <strong :style="{ color: heroKpi.achievementGradeColor }">{{
                                                            heroKpi.achievementGradeLabel
                                                        }}</strong>
                                                        <small>periode terpilih</small>
                                                    </div>
                                                    <div>
                                                        <span>YTD Grade</span>
                                                        <strong :style="{ color: heroKpi.ytdGradeColor }">{{
                                                            heroKpi.ytdGradeLabel
                                                        }}</strong>
                                                        <small>year to date</small>
                                                    </div>
                                                </div>
                                                <div class="kpi-status-panel">
                                                    <div class="kpi-status-panel__item">
                                                        <span>KPI Parameter</span>
                                                        <strong :class="heroKpi.parameterStatusTone">{{
                                                            heroKpi.parameterStatusLabel
                                                        }}</strong>
                                                    </div>
                                                    <div class="kpi-status-panel__item">
                                                        <span>Update Performance</span>
                                                        <strong :class="heroKpi.performanceStatusTone">{{
                                                            heroKpi.performanceStatusLabel
                                                        }}</strong>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Parameter list header -->
                                    <div class="kpi-list-header">
                                        <template v-if="isKpiLoading || isDashboardBootstrapping">
                                            <div class="leader-skeleton-line" style="width: 6rem; height: 0.6rem"></div>
                                        </template>
                                        <template v-else>
                                            <i class="bi bi-list-check"></i>
                                            <span>Parameter KPI</span>
                                        </template>
                                    </div>

                                    <!-- Parameter list: skeleton, empty, or data -->
                                    <div
                                        class="mini-parameter-list"
                                        :class="{ 'is-skeleton': isKpiLoading || isDashboardBootstrapping }"
                                    >
                                        <template v-if="isKpiLoading || isDashboardBootstrapping">
                                            <div v-for="n in 3" :key="`kpi-param-sk-${n}`">
                                                <div>
                                                    <div class="leader-skeleton-line mb-1" style="width: 65%"></div>
                                                    <div class="leader-skeleton-line is-short"></div>
                                                </div>
                                                <div class="leader-skeleton-line" style="height: 0.35rem"></div>
                                            </div>
                                        </template>
                                        <EmptyState
                                            v-else-if="!heroKpi.hasData"
                                            compact
                                            icon="bi bi-bar-chart"
                                            title="Data KPI belum tersedia"
                                            :description="heroKpi.emptyMessage"
                                        />
                                        <template v-else>
                                            <div
                                                v-for="param in heroKpi.parameters"
                                                :key="param.id || param.name"
                                                :class="{
                                                    'parameter not-inPeriode':
                                                        param.statusLabel == 'Periode KPI belum aktif',
                                                }"
                                            >
                                                <div>
                                                    <div class="kpi-param-name">
                                                        <div
                                                            class="kpi-param-dot"
                                                            :class="
                                                                param.achievement >= 100
                                                                    ? 'is-excellent'
                                                                    : param.achievement >= 70
                                                                      ? 'is-good'
                                                                      : param.achievement > 0
                                                                        ? 'is-low'
                                                                        : 'is-idle'
                                                            "
                                                        ></div>
                                                        <span>{{ param.name }}</span>
                                                    </div>
                                                    <strong>
                                                        {{ param.achievement }}%
                                                        <small v-if="param.weight">({{ param.weight }}%)</small>
                                                    </strong>
                                                </div>
                                                <small class="mini-parameter-meta">
                                                    Target {{ param.target }} · Actual {{ param.actual }} ·
                                                    {{ param.statusLabel }}
                                                </small>
                                                <div class="mini-progress">
                                                    <div
                                                        class="mini-progress__fill"
                                                        :style="{
                                                            width: `${safePercent(param.achievement)}%`,
                                                            background: param.statusColor || '#7c3aed',
                                                        }"
                                                    ></div>
                                                </div>
                                            </div>
                                            <EmptyState
                                                v-if="!heroKpi.parameters.length"
                                                compact
                                                icon="bi bi-list-check"
                                                title="Parameter KPI kosong"
                                                description="Belum ada Parameter KPI tahun/periode ini."
                                            />
                                        </template>
                                    </div>

                                    <!-- Trend Chart: skeleton or real -->
                                    <div class="chart-block">
                                        <div class="chart-head">
                                            <template v-if="isKpiLoading || isDashboardBootstrapping">
                                                <div class="leader-skeleton-line is-title" style="width: 8rem"></div>
                                                <div class="leader-skeleton-line is-short" style="width: 5rem"></div>
                                            </template>
                                            <template v-else>
                                                <span>Trend KPI 6 Bulan</span>
                                                <strong>{{ heroKpi.trendDirection }}</strong>
                                            </template>
                                        </div>
                                        <div class="chart-safe">
                                            <template v-if="isKpiLoading || isDashboardBootstrapping">
                                                <div style="height: 80px; padding: 0.5rem">
                                                    <div
                                                        class="leader-skeleton-line"
                                                        style="height: 100%; border-radius: 0.5rem"
                                                    ></div>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <VueApexCharts
                                                    v-if="heroKpi.trend.length"
                                                    :key="`kpi-trend-${heroKpi.year}-${heroKpi.selectedPeriod}`"
                                                    type="area"
                                                    height="96"
                                                    :options="kpiTrendOptions"
                                                    :series="kpiTrendSeries"
                                                />
                                                <EmptyState
                                                    v-else
                                                    compact
                                                    icon="bi bi-activity"
                                                    title="Trend KPI kosong"
                                                    description="Belum ada histori skor KPI."
                                                />
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </article>

                            <!-- LMS -->
                            <article class="panel-card module-card" data-tour="module-lms">
                                <div class="module-card__head">
                                    <div class="module-title">
                                        <div class="module-icon is-teal">
                                            <i class="bi bi-mortarboard"></i>
                                        </div>

                                        <div class="module-title-copy">
                                            <span class="section-label">Learning Saya</span>
                                            <h2>Learning Management</h2>
                                        </div>
                                    </div>

                                    <span class="period-badge is-teal"
                                        >{{ heroLms.completed }}/{{ heroLms.total }} selesai</span
                                    >
                                </div>

                                <button
                                    v-if="!lmsRevealed"
                                    type="button"
                                    class="module-reveal module-reveal--teal"
                                    @click="revealLms"
                                >
                                    <span class="module-reveal__aura"></span>
                                    <span class="module-reveal__icon"><i class="bi bi-mortarboard"></i></span>
                                    <span class="module-reveal__title">Lihat Progres Learning</span>
                                    <span class="module-reveal__desc"
                                        >Progres keseluruhan, daftar training, dan tren durasi belajar.</span
                                    >
                                    <span class="module-reveal__cta">
                                        <i class="bi bi-lightning-charge-fill"></i>
                                        <span>Muat data</span>
                                    </span>
                                </button>

                                <template v-else>
                                    <!-- 1. Progress Hero: same layout, skeleton or real -->
                                    <div class="lms-hero">
                                        <div
                                            class="lms-progress-ring"
                                            :class="{ 'skeleton-avatar': isLmsLoading || isDashboardBootstrapping }"
                                        >
                                            <template v-if="!(isLmsLoading || isDashboardBootstrapping)">
                                                <span>Overall</span>
                                                <strong>{{ heroLms.progress }}%</strong>
                                            </template>
                                        </div>
                                        <div class="lms-hero__info">
                                            <template v-if="isLmsLoading || isDashboardBootstrapping">
                                                <div
                                                    class="leader-skeleton-line is-short mb-1"
                                                    style="width: 55%"
                                                ></div>
                                                <div class="leader-skeleton-line" style="height: 0.48rem"></div>
                                                <div class="leader-skeleton-line is-short" style="width: 40%"></div>
                                            </template>
                                            <template v-else>
                                                <div class="lms-hero__top">
                                                    <span>Progress Keseluruhan</span>
                                                    <strong class="lms-hero__badge">{{ heroLms.progressLabel }}</strong>
                                                </div>
                                                <div class="mini-progress is-large">
                                                    <div
                                                        class="mini-progress__fill is-teal"
                                                        :style="{ width: `${safePercent(heroLms.progress)}%` }"
                                                    ></div>
                                                </div>
                                                <small
                                                    >{{ heroLms.completed }}/{{ heroLms.total }} training selesai</small
                                                >
                                            </template>
                                        </div>
                                    </div>

                                    <!-- 2. Quick Stats: same layout, skeleton or real -->
                                    <div class="lms-quick-stats">
                                        <template v-if="isLmsLoading || isDashboardBootstrapping">
                                            <div
                                                v-for="n in 2"
                                                :key="`lms-stat-sk-${n}`"
                                                class="lms-stat-pill is-skeleton"
                                            >
                                                <div
                                                    class="skeleton-avatar"
                                                    style="
                                                        width: 1.1rem;
                                                        height: 1.1rem;
                                                        border-radius: 0.4rem;
                                                        flex: 0 0 auto;
                                                    "
                                                ></div>
                                                <div style="flex: 1; min-width: 0">
                                                    <div
                                                        class="leader-skeleton-line is-short mb-1"
                                                        style="width: 50%"
                                                    ></div>
                                                    <div class="leader-skeleton-line" style="width: 65%"></div>
                                                </div>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div class="lms-stat-pill">
                                                <i class="bi bi-clock-history"></i>
                                                <div>
                                                    <span>Avg Durasi</span>
                                                    <strong>{{ heroLms.avgLearningDurationLabel }}</strong>
                                                </div>
                                            </div>
                                            <div class="lms-stat-pill">
                                                <i class="bi bi-graph-up-arrow"></i>
                                                <div>
                                                    <span>Avg Learning Gain</span>
                                                    <strong>{{
                                                        heroLms.learningGain ? `+${heroLms.learningGain}%` : '-'
                                                    }}</strong>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    <!-- 3. Next Training (only when loaded and has session) -->
                                    <div
                                        v-if="
                                            !isLmsLoading &&
                                            !isDashboardBootstrapping &&
                                            heroLms.nextSession &&
                                            heroLms.nextSession !== '-'
                                        "
                                        class="lms-next-highlight"
                                    >
                                        <i class="bi bi-calendar2-check"></i>
                                        <div class="lms-next-highlight__info">
                                            <span>Training Berikutnya</span>
                                            <strong>{{ heroLms.nextSession }}</strong>
                                        </div>
                                        <small class="lms-next-highlight__date">{{ heroLms.nextSessionDate }}</small>
                                    </div>

                                    <!-- 4. Training List header -->
                                    <div class="lms-list-header">
                                        <template v-if="isLmsLoading || isDashboardBootstrapping">
                                            <div class="leader-skeleton-line" style="width: 5rem; height: 0.6rem"></div>
                                        </template>
                                        <template v-else>
                                            <i class="bi bi-list-check"></i>
                                            <span>Daftar Training</span>
                                        </template>
                                    </div>

                                    <!-- Training list: skeleton or real -->
                                    <div
                                        class="training-list"
                                        :class="{ 'is-skeleton': isLmsLoading || isDashboardBootstrapping }"
                                    >
                                        <template v-if="isLmsLoading || isDashboardBootstrapping">
                                            <div v-for="n in 3" :key="`lms-item-sk-${n}`" class="training-item">
                                                <div class="leader-skeleton-line mb-1" style="width: 70%"></div>
                                                <div class="leader-skeleton-line" style="height: 0.35rem"></div>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div
                                                v-for="course in heroLms.progressList.slice(0, 6)"
                                                :key="course.title"
                                                class="training-item"
                                            >
                                                <div class="training-item__top">
                                                    <div class="training-item__body">
                                                        <div
                                                            class="training-item__dot"
                                                            :class="
                                                                course.progress >= 100
                                                                    ? 'is-done'
                                                                    : course.progress > 0
                                                                      ? 'is-active'
                                                                      : 'is-idle'
                                                            "
                                                        ></div>
                                                        <div>
                                                            <strong>{{ course.title }}</strong>
                                                            <span>{{
                                                                course.deadline ||
                                                                course.remaining ||
                                                                'Tidak ada deadline'
                                                            }}</span>
                                                        </div>
                                                    </div>
                                                    <small :class="{ 'is-complete': course.progress >= 100 }">
                                                        {{ course.progress }}%
                                                    </small>
                                                </div>
                                                <div class="mini-progress">
                                                    <div
                                                        class="mini-progress__fill is-teal"
                                                        :style="{ width: `${safePercent(course.progress)}%` }"
                                                    ></div>
                                                </div>
                                            </div>
                                            <!-- Placeholder slots when training list is short -->
                                            <template v-if="heroLms.progressList.length > 0">
                                                <div
                                                    v-for="n in Math.max(0, 3 - heroLms.progressList.length)"
                                                    :key="`lms-placeholder-${n}`"
                                                    class="training-item-placeholder"
                                                >
                                                    <div class="placeholder-dot"></div>
                                                    <span>Slot training kosong</span>
                                                </div>
                                            </template>
                                            <EmptyState
                                                v-if="!heroLms.progressList.length"
                                                compact
                                                icon="bi bi-journal"
                                                title="List training kosong"
                                                description="Belum ada training aktif."
                                            />
                                        </template>
                                    </div>

                                    <!-- 5. Duration Chart: skeleton or real -->
                                    <div class="chart-block">
                                        <div class="chart-head">
                                            <template v-if="isLmsLoading || isDashboardBootstrapping">
                                                <div class="leader-skeleton-line is-title" style="width: 8rem"></div>
                                                <div class="leader-skeleton-line is-short" style="width: 5rem"></div>
                                            </template>
                                            <template v-else>
                                                <span>Durasi Belajar Per Bulan</span>
                                                <strong>{{ heroLms.avgMonthlyDurationLabel }} avg</strong>
                                            </template>
                                        </div>
                                        <div class="chart-safe">
                                            <template v-if="isLmsLoading || isDashboardBootstrapping">
                                                <div style="height: 80px; padding: 0.5rem">
                                                    <div
                                                        class="leader-skeleton-line"
                                                        style="height: 100%; border-radius: 0.5rem"
                                                    ></div>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <VueApexCharts
                                                    v-if="heroLms.durationMonthly.length"
                                                    :key="`lms-duration-${heroLms.durationMonthly.length}`"
                                                    type="area"
                                                    height="96"
                                                    :options="lmsDurationChartOptions"
                                                    :series="lmsDurationChartSeries"
                                                />
                                                <EmptyState
                                                    v-else
                                                    compact
                                                    icon="bi bi-activity"
                                                    title="Trend durasi kosong"
                                                    description="Belum ada histori durasi belajar."
                                                />
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </article>

                            <!-- OVERTIME -->
                            <article
                                v-if="!isLeader"
                                ref="overtimeSectionRef"
                                class="panel-card module-card overtime-card"
                                data-tour="module-overtime"
                            >
                                <div class="module-card__head">
                                    <div class="module-title">
                                        <div class="module-icon is-orange">
                                            <i class="bi bi-clock-history"></i>
                                        </div>

                                        <div class="module-title-copy">
                                            <span class="section-label">Lembur Saya</span>
                                            <h2>Assignment Lembur</h2>
                                        </div>
                                    </div>

                                    <div v-if="overtimeRevealed" class="overtime-filters">
                                        <select
                                            class="cutoff-period-select"
                                            :value="selectedOvertimeCutoff"
                                            :disabled="overtimeCutoffSwitching"
                                            @change="handleOvertimeCutoffChange"
                                        >
                                            <option
                                                v-for="period in cutoffPeriodOptions"
                                                :key="period.value"
                                                :value="period.value"
                                            >
                                                {{ period.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <span v-else class="period-badge is-orange">{{
                                        overtimeSummary.periodLabel
                                    }}</span>
                                </div>

                                <button
                                    v-if="!overtimeRevealed"
                                    type="button"
                                    class="module-reveal module-reveal--orange"
                                    @click="revealOvertime"
                                >
                                    <span class="module-reveal__aura"></span>
                                    <span class="module-reveal__icon"><i class="bi bi-clock-history"></i></span>
                                    <span class="module-reveal__title">Lihat Assignment Lembur</span>
                                    <span class="module-reveal__desc"
                                        >Ringkasan jam lembur, penugasan per tanggal, dan perhitungan koefisien.</span
                                    >
                                    <span class="module-reveal__cta">
                                        <i class="bi bi-lightning-charge-fill"></i>
                                        <span>Muat data</span>
                                    </span>
                                </button>

                                <template v-else>
                                    <div
                                        v-if="isOvertimeLoading || isDashboardBootstrapping || overtimeCutoffSwitching"
                                        class="module-skeleton is-overtime"
                                    >
                                        <div class="overtime-summary-grid">
                                            <div v-for="n in 6" :key="`overtime-skeleton-${n}`" class="is-skeleton">
                                                <div class="leader-skeleton-line is-short mb-1"></div>
                                                <div
                                                    class="leader-skeleton-line is-title mb-1"
                                                    style="width: 55%"
                                                ></div>
                                                <div class="leader-skeleton-line" style="width: 75%"></div>
                                            </div>
                                        </div>
                                        <div class="module-alert is-info is-skeleton">
                                            <div
                                                class="skeleton-avatar"
                                                style="width: 1.35rem; height: 1.35rem; border-radius: 999px"
                                            ></div>
                                            <div class="leader-skeleton-line" style="width: 85%"></div>
                                        </div>
                                        <div class="chart-block is-skeleton">
                                            <div class="chart-head">
                                                <div class="leader-skeleton-line is-title" style="width: 8rem"></div>
                                                <div class="leader-skeleton-line is-short" style="width: 5rem"></div>
                                            </div>
                                            <div class="leader-apex-chart" style="height: 120px; padding: 0.8rem">
                                                <div
                                                    class="leader-skeleton-line"
                                                    style="height: 100%; border-radius: 0.5rem"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>

                                    <EmptyState
                                        v-else-if="!overtimeSummary.hasData && !isDashboardBootstrapping"
                                        icon="bi bi-clock"
                                        title="Belum ada data lembur"
                                        description="Assignment atau histori lembur belum tersedia pada periode ini."
                                    />

                                    <template v-else>
                                        <div class="overtime-summary-grid">
                                            <div class="overtime-total">
                                                <span>Total Lembur</span>
                                                <strong>{{ overtimeSummary.totalHours }} jam</strong>
                                                <small>{{ overtimeSummary.periodLabel }}</small>
                                            </div>

                                            <div>
                                                <span>Lembur Shift</span>
                                                <strong>{{ overtimeSummary.shiftHours }} jam</strong>
                                                <small>Otomatis dari long shift</small>
                                            </div>

                                            <div>
                                                <span>Sebelum Kerja</span>
                                                <strong>{{ overtimeSummary.beforeWorkHours }} jam</strong>
                                                <small>Lembur awal</small>
                                            </div>

                                            <div>
                                                <span>Setelah Kerja</span>
                                                <strong>{{ overtimeSummary.afterWorkHours }} jam</strong>
                                                <small>Lembur hari kerja</small>
                                            </div>

                                            <div>
                                                <span>Hari Libur</span>
                                                <strong>{{ overtimeSummary.holidayHours }} jam</strong>
                                                <small>Libur/off day</small>
                                            </div>

                                            <div>
                                                <span>Assignment</span>
                                                <strong>{{ overtimeSummary.activeAssignments }}</strong>
                                                <small>Aktif</small>
                                            </div>
                                        </div>

                                        <div class="module-alert is-info">
                                            <i class="bi bi-person-check"></i>
                                            <span>{{ overtimeSummary.assignmentNote }}</span>
                                        </div>

                                        <div class="overtime-assignment-panel">
                                            <div class="overtime-assignment-head">
                                                <div>
                                                    <span>Penugasan Lembur</span>
                                                    <strong>{{ overtimeAssignmentSummaryLabel }}</strong>
                                                </div>
                                                <small>{{ overtimeAssignmentRangeLabel }}</small>
                                            </div>

                                            <div v-if="isOvertimeAssignmentLoading" class="overtime-assignment-list">
                                                <div
                                                    v-for="n in 4"
                                                    :key="`overtime-assignment-skeleton-${n}`"
                                                    class="overtime-assignment-item is-skeleton"
                                                >
                                                    <div class="leader-skeleton-line is-title mb-1"></div>
                                                    <div class="leader-skeleton-line mb-1" style="width: 82%"></div>
                                                    <div class="leader-skeleton-line is-short" style="width: 58%"></div>
                                                </div>
                                            </div>

                                            <EmptyState
                                                v-else-if="!overtimeAssignments.length"
                                                compact
                                                icon="bi bi-calendar2-check"
                                                title="Penugasan belum ada"
                                                description="Belum ada penugasan lembur yang bisa ditampilkan."
                                            />

                                            <div v-else class="overtime-date-groups">
                                                <div
                                                    v-for="group in overtimeAssignmentsByDate"
                                                    :key="group.dateKey"
                                                    class="overtime-date-group"
                                                    :class="{ 'is-expanded': isOvertimeDateExpanded(group.dateKey) }"
                                                >
                                                    <!-- Header tanggal: accordion trigger + chip lembur sah -->
                                                    <button
                                                        type="button"
                                                        class="overtime-date-head is-accordion"
                                                        @click="toggleOvertimeDate(group.dateKey)"
                                                    >
                                                        <span class="overtime-date-head__left">
                                                            <i
                                                                class="bi overtime-accordion-chevron"
                                                                :class="
                                                                    isOvertimeDateExpanded(group.dateKey)
                                                                        ? 'bi-chevron-down'
                                                                        : 'bi-chevron-right'
                                                                "
                                                            ></i>
                                                            <span class="overtime-date-head__date">{{
                                                                group.dateLabel
                                                            }}</span>
                                                        </span>
                                                        <span class="overtime-date-head__right">
                                                            <span class="overtime-date-head__badges">
                                                                <span
                                                                    v-for="badge in getOvertimeTypeBadges(group.items)"
                                                                    :key="badge.label"
                                                                    class="overtime-type-badge"
                                                                    :class="`is-${badge.tone}`"
                                                                    >{{ badge.label }}</span
                                                                >
                                                            </span>
                                                            <span
                                                                class="overtime-day-chip"
                                                                tabindex="0"
                                                                role="button"
                                                                :aria-label="group.dayCalc.plain"
                                                                @click.stop
                                                            >
                                                                {{ group.dayKoefisienChipLabel }}
                                                                <i class="bi bi-info-circle overtime-info-icon"></i>

                                                                <span class="overtime-tooltip" role="tooltip">
                                                                    <span class="overtime-tooltip__title">
                                                                        Perhitungan lembur {{ group.dateLabel }}
                                                                    </span>
                                                                    <span
                                                                        v-for="(row, ri) in group.dayCalc.rows"
                                                                        :key="ri"
                                                                        class="overtime-tooltip__row"
                                                                        :class="`is-${row.tone}`"
                                                                    >
                                                                        <span>{{ row.label }}</span>
                                                                        <strong>{{ row.value }}</strong>
                                                                    </span>
                                                                    <span class="overtime-tooltip__row is-sah">
                                                                        <span>Lembur sah (dibulatkan ke bawah)</span>
                                                                        <strong>{{ group.dayCalc.sahLabel }}</strong>
                                                                    </span>
                                                                    <span class="overtime-tooltip__note">
                                                                        Pembulatan & pengurangan keterlambatan sesuai
                                                                        ketentuan HC.
                                                                    </span>
                                                                </span>
                                                            </span>
                                                        </span>
                                                    </button>

                                                    <!-- Komponen lembur pada tanggal ini (durasi MURNI per jenis) -->
                                                                                                         <transition
                                                         name="accordion"
                                                         @enter="onAccordionEnter"
                                                         @after-enter="onAccordionAfterEnter"
                                                         @leave="onAccordionLeave"
                                                     >
                                                         <div
                                                             v-show="isOvertimeDateExpanded(group.dateKey)"
                                                             class="overtime-date-items"
                                                         >
                                                             <button
                                                                 v-for="item in group.items"
                                                                 :key="item.key"
                                                                 type="button"
                                                                 class="overtime-assignment-item"
                                                                 :class="{ 'is-auto-shift': item.isAutoShift }"
                                                                 @click="openOvertimeAssignmentDetail(item)"
                                                             >
                                                                 <div class="overtime-assignment-item__content">
                                                                     <div class="overtime-assignment-col-info">
                                                                         <strong class="overtime-type-title">{{ item.typeLabel }}</strong>
                                                                         <div class="overtime-time-range">
                                                                             <i class="bi bi-clock"></i>
                                                                             <span>{{ item.timeRangeLabel }}</span>
                                                                         </div>
                                                                         <span class="overtime-source-badge" :class="{ 'is-auto': item.isAutoShift }">
                                                                             <i :class="item.isAutoShift ? 'bi bi-magic' : 'bi bi-hash'"></i>
                                                                             <span>{{ item.sourceLabel }}</span>
                                                                             <template v-if="item.isAutoShift && item.shiftUpToLabel">
                                                                                 · {{ item.shiftUpToLabel }}
                                                                             </template>
                                                                         </span>
                                                                     </div>

                                                                     <div class="overtime-assignment-col-reason">
                                                                         <div v-if="item.reason" class="overtime-card-reason">
                                                                             <i class="bi bi-chat-left-text"></i>
                                                                             <p class="overtime-reason-text" :title="item.reason">{{ item.reason }}</p>
                                                                         </div>
                                                                     </div>

                                                                     <div class="overtime-assignment-col-meta">
                                                                         <span class="overtime-pill-duration">{{ item.durationLabel }}</span>
                                                                         <span class="overtime-pill-absensi" :class="`is-${item.absensiTone}`">{{ item.absensiLabel }}</span>
                                                                     </div>

                                                                     <div class="overtime-assignment-col-status">
                                                                         <span class="overtime-status" :class="`is-${item.statusTone}`">
                                                                             <span class="overtime-status-dot"></span>
                                                                             <span class="overtime-status-label">{{ item.statusLabel }}</span>
                                                                         </span>
                                                                     </div>
                                                                 </div>
                                                                 <i class="bi bi-chevron-right"></i>
                                                             </button>
                                                         </div>
                                                     </transition>
                                                </div>
                                            </div>

                                            <!-- Kontrol halaman: hanya muncul bila memang ada halaman kedua. -->
                                            <div
                                                v-if="overtimeAssignmentPageCount > 1"
                                                class="overtime-assignment-pager"
                                            >
                                                <button
                                                    type="button"
                                                    class="overtime-pager-btn"
                                                    :disabled="overtimeAssignmentPage <= 1"
                                                    aria-label="Tanggal sebelumnya"
                                                    @click="gantiHalamanLembur(-1)"
                                                >
                                                    <i class="bi bi-chevron-left"></i>
                                                </button>
                                                <span class="overtime-pager-info">
                                                    {{ overtimeAssignmentPage }} / {{ overtimeAssignmentPageCount }}
                                                </span>
                                                <button
                                                    type="button"
                                                    class="overtime-pager-btn"
                                                    :disabled="overtimeAssignmentPage >= overtimeAssignmentPageCount"
                                                    aria-label="Tanggal berikutnya"
                                                    @click="gantiHalamanLembur(1)"
                                                >
                                                    <i class="bi bi-chevron-right"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="chart-block">
                                            <div class="chart-head">
                                                <span>Trend Lembur — 2 Cutoff Terakhir</span>
                                                <strong>{{ overtimeTrendLoading ? 'Memuat…' : 'Titik per minggu' }}</strong>
                                            </div>

                                            <div class="chart-safe">
                                                <div
                                                    v-if="overtimeTrendLoading && !overtimeTrend.length"
                                                    class="leader-apex-chart is-skeleton"
                                                    style="height: 190px; padding: 0.8rem"
                                                >
                                                    <div
                                                        class="leader-skeleton-line"
                                                        style="height: 100%; border-radius: 0.5rem"
                                                    ></div>
                                                </div>

                                                <VueApexCharts
                                                    v-else-if="overtimeTrend.length"
                                                    type="line"
                                                    height="190"
                                                    :options="overtimeChartOptions"
                                                    :series="overtimeChartSeries"
                                                />

                                                <EmptyState
                                                    v-else
                                                    compact
                                                    icon="bi bi-activity"
                                                    title="Trend lembur kosong"
                                                    description="Belum ada histori lembur pada 2 cutoff terakhir."
                                                />
                                            </div>
                                        </div>
                                    </template>
                                </template>
                            </article>
                        </section>
                    </main>

                    <!-- RIGHT SIDEBAR -->
                    <aside class="dashboard-side">
                        <section class="panel-card side-panel quick-action-panel" data-tour="quick-actions">
                            <div class="panel-head is-tight">
                                <div>
                                    <span class="section-label">Menu Cepat</span>
                                    <h2>Akses Cepat</h2>
                                </div>

                                <span class="side-count">{{ quickActionCount }}</span>
                            </div>

                            <div v-if="quickActionGroups.length" class="quick-action-folder-grid">
                                <button
                                    v-for="group in quickActionGroups"
                                    :key="group.key"
                                    type="button"
                                    class="quick-action-folder"
                                    @click="openQuickActionGroup(group)"
                                >
                                    <span class="quick-action-folder__icon">
                                        <i :class="group.icon"></i>
                                    </span>

                                    <span class="quick-action-folder__copy">
                                        <strong>{{ group.label }}</strong>
                                        <small>{{ group.items.length }} menu - {{ group.moduleLabel }}</small>
                                    </span>

                                    <!-- <i class="bi bi-chevron-right"></i> -->
                                </button>
                            </div>

                            <EmptyState
                                v-else
                                compact
                                icon="bi bi-grid"
                                title="Menu cepat belum tersedia"
                                description="Menu akan mengikuti akses sidebar kamu."
                            />
                        </section>
                        <section
                            class="panel-card side-panel action-center-panel"
                            :class="{ 'mobile-accordion-card': isMobileViewport }"
                            data-tour="leader-approvals"
                        >
                            <button
                                v-if="isMobileViewport"
                                class="mobile-accordion__trigger"
                                @click.stop="toggleMobilePanel('action')"
                            >
                                <div class="module-title">
                                    <div class="module-icon is-orange">
                                        <i class="bi bi-bell-fill"></i>
                                    </div>
                                    <div class="module-title-copy">
                                        <span class="section-label">Action Center</span>
                                        <h2>Perlu Saya Tindak</h2>
                                    </div>
                                </div>
                                <div class="mobile-accordion__meta">
                                    <span class="side-count" v-if="unreadPendingCount">{{ unreadPendingCount }}</span>
                                    <i :class="mobilePanelState.action ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"></i>
                                </div>
                            </button>

                            <div class="panel-head is-tight" v-else>
                                <div>
                                    <span class="section-label">Action Center</span>
                                    <h2>Perlu Saya Tindak</h2>
                                </div>

                                <span class="side-count">{{ unreadPendingCount }}</span>
                            </div>

                            <transition
                                name="accordion"
                                @enter="onAccordionEnter"
                                @after-enter="onAccordionAfterEnter"
                                @leave="onAccordionLeave"
                            >
                                <div
                                    v-show="!isMobileViewport || mobilePanelState.action"
                                    class="mobile-accordion__content"
                                >
                                    <div class="action-list">
                                        <div
                                            v-if="isNeedsActionLoading || isDashboardBootstrapping"
                                            class="action-skeleton-list"
                                        >
                                            <div
                                                v-for="n in 3"
                                                :key="`action-skeleton-${n}`"
                                                class="action-card is-skeleton"
                                            >
                                                <div class="action-card__icon skeleton-avatar"></div>
                                                <div class="action-card__body">
                                                    <div
                                                        class="leader-skeleton-line is-title mb-1"
                                                        style="width: 60%"
                                                    ></div>
                                                    <div class="leader-skeleton-line" style="width: 85%"></div>
                                                    <div
                                                        class="leader-skeleton-line is-short mt-1"
                                                        style="width: 50%"
                                                    ></div>
                                                </div>
                                                <div class="action-card__actions">
                                                    <div
                                                        class="skeleton-avatar"
                                                        style="
                                                            width: 1rem;
                                                            height: 1rem;
                                                            border-radius: 0.3rem;
                                                            flex: 0 0 auto;
                                                        "
                                                    ></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            v-else
                                            v-for="item in pendingItems"
                                            :key="item.id"
                                            role="button"
                                            tabindex="0"
                                            class="action-card"
                                            :class="[`is-${item.tone}`, { 'is-read': item.is_read }]"
                                            @click="handlePendingItem(item)"
                                            @keydown.enter.prevent="handlePendingItem(item)"
                                        >
                                            <div class="action-card__icon">
                                                <i :class="item.icon"></i>
                                            </div>

                                            <div class="action-card__body">
                                                <strong>{{ item.title }}</strong>
                                                <span>{{ truncateText(item.description, 100) }}</span>
                                                <small>{{ item.module }} • {{ item.actionLabel }}</small>
                                            </div>

                                            <div class="action-card__actions">
                                                <i
                                                    v-if="isNotificationDismissBusy(item)"
                                                    class="bi bi-arrow-repeat action-loading-spin"
                                                ></i>
                                                <i v-else class="bi bi-arrow-right-short"></i>
                                            </div>
                                        </div>

                                        <div
                                            v-if="
                                                !isDashboardBootstrapping &&
                                                !isNeedsActionLoading &&
                                                !needsActionError &&
                                                actionCenterTotalPages > 1
                                            "
                                            class="submission-pagination"
                                        >
                                            <button
                                                type="button"
                                                class="submission-pagination__btn"
                                                :disabled="actionCenterPage <= 1 || actionCenterPageLoading"
                                                @click="goToActionCenterPage(actionCenterPage - 1)"
                                            >
                                                <i class="bi bi-chevron-left"></i>
                                            </button>
                                            <span class="submission-pagination__info">
                                                <template v-if="actionCenterPageLoading">
                                                    <i class="bi bi-arrow-repeat action-loading-spin"></i>
                                                </template>
                                                <template v-else>
                                                    {{ actionCenterPage }} / {{ actionCenterTotalPages }}
                                                </template>
                                            </span>
                                            <button
                                                type="button"
                                                class="submission-pagination__btn"
                                                :disabled="
                                                    actionCenterPage >= actionCenterTotalPages ||
                                                    actionCenterPageLoading
                                                "
                                                @click="goToActionCenterPage(actionCenterPage + 1)"
                                            >
                                                <i class="bi bi-chevron-right"></i>
                                            </button>
                                        </div>

                                        <Transition name="action-page-fade">
                                            <div v-if="actionCenterPageLoading" class="action-page-loader"></div>
                                        </Transition>

                                        <div
                                            v-if="
                                                !isDashboardBootstrapping && !isNeedsActionLoading && needsActionError
                                            "
                                            class="action-error-card"
                                        >
                                            <span>{{ needsActionError }}</span>
                                            <button type="button" @click="retryDashboardLoad">Coba lagi</button>
                                        </div>

                                        <EmptyState
                                            v-if="
                                                !isDashboardBootstrapping &&
                                                !isNeedsActionLoading &&
                                                !needsActionError &&
                                                !pendingItems.length
                                            "
                                            compact
                                            icon="bi bi-check2-circle"
                                            title="Tidak ada tindakan"
                                            description="Semua terlihat aman untuk saat ini."
                                        />
                                    </div>
                                </div>
                            </transition>
                        </section>

                        <section
                            class="panel-card side-panel attendance-donut-card"
                            data-tour="attendance-composition"
                            @click="handleAttendanceDonutDetail('all')"
                            :class="{ 'mobile-accordion-card': isMobileViewport }"
                        >
                            <button
                                v-if="isMobileViewport"
                                class="mobile-accordion__trigger"
                                @click.stop="toggleMobilePanel('attendance')"
                            >
                                <div class="module-title">
                                    <div class="module-icon is-teal">
                                        <i class="bi bi-pie-chart-fill"></i>
                                    </div>
                                    <div class="module-title-copy">
                                        <span class="section-label">Absensi Cutoff</span>
                                        <h2>Komposisi Kehadiran</h2>
                                    </div>
                                </div>
                                <div class="mobile-accordion__meta">
                                    <span class="side-count">{{ attendanceDonutSummary.percentLabel }}</span>
                                    <i
                                        :class="mobilePanelState.attendance ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"
                                    ></i>
                                </div>
                            </button>

                            <div class="attendance-donut-card__head" v-else @click="handleAttendanceDonutDetail('all')">
                                <div>
                                    <span class="section-label">Absensi Cutoff</span>
                                    <h2>Komposisi Kehadiran</h2>
                                </div>

                                <span class="side-count">{{ attendanceDonutSummary.percentLabel }}</span>
                            </div>

                            <transition
                                name="accordion"
                                @enter="onAccordionEnter"
                                @after-enter="onAccordionAfterEnter"
                                @leave="onAccordionLeave"
                            >
                                <div
                                    v-show="!isMobileViewport || mobilePanelState.attendance"
                                    class="mobile-accordion__content"
                                    @click="handleAttendanceDonutDetail('all')"
                                >
                                    <select
                                        class="attendance-donut-period-select"
                                        :value="selectedAttendanceDonutPeriod"
                                        :disabled="isAttendanceDonutLoading"
                                        @click.stop
                                        @change.stop="handleAttendanceDonutPeriodChange"
                                    >
                                        <option
                                            v-for="period in attendanceDonutPeriodOptions"
                                            :key="`donut-period-${period.value}`"
                                            :value="period.value"
                                        >
                                            {{ period.label }}
                                        </option>
                                    </select>

                                    <div v-if="isAttendanceDonutLoading" class="attendance-donut-skeleton">
                                        <div class="attendance-donut-skeleton__chart"></div>
                                        <div class="attendance-donut-skeleton__lines">
                                            <span v-for="n in 4" :key="`attendance-donut-skeleton-${n}`"></span>
                                        </div>
                                    </div>

                                    <EmptyState
                                        v-else-if="attendanceDonutError"
                                        compact
                                        icon="bi bi-exclamation-triangle"
                                        title="Komposisi gagal dimuat"
                                        :description="attendanceDonutError"
                                    />

                                    <EmptyState
                                        v-else-if="!attendanceDonutSummary.totalDays"
                                        compact
                                        icon="bi bi-pie-chart"
                                        title="Absensi cutoff kosong"
                                        description="Komposisi akan muncul setelah data kalender tersedia."
                                    />

                                    <template v-else>
                                        <div class="attendance-donut-chart">
                                            <VueApexCharts
                                                type="donut"
                                                height="178"
                                                :options="attendanceDonutOptions"
                                                :series="attendanceDonutSeries"
                                            />
                                        </div>

                                        <div class="attendance-donut-meta">
                                            <strong
                                                >{{ attendanceDonutSummary.presentCount }} dari
                                                {{ attendanceDonutSummary.totalDays }} hari hadir</strong
                                            >
                                            <span>Periode {{ attendanceDonutSummary.periodLabel }}</span>
                                        </div>

                                        <div class="attendance-donut-legend">
                                            <button
                                                v-for="item in attendanceDonutStats"
                                                :key="`donut-${item.key}`"
                                                type="button"
                                                class="attendance-donut-legend__item"
                                                :class="`is-${item.tone}`"
                                                :disabled="!item.count"
                                                @click.stop="handleAttendanceDonutLegend(item)"
                                            >
                                                <span
                                                    class="attendance-donut-legend__dot"
                                                    :style="{ backgroundColor: item.color }"
                                                ></span>
                                                <span>{{ item.label }}</span>
                                                <strong>{{ item.count }}</strong>
                                            </button>
                                        </div>

                                        <button
                                            type="button"
                                            class="attendance-donut-action"
                                            @click.stop="handleAttendanceDonutDetail('all')"
                                        >
                                            <span>Lihat detail</span>
                                            <i class="bi bi-arrow-right-short"></i>
                                        </button>
                                    </template>
                                </div>
                            </transition>
                        </section>

                        <section
                            class="panel-card side-panel"
                            :class="{ 'mobile-accordion-card': isMobileViewport }"
                            data-tour="submissions"
                        >
                            <button
                                v-if="isMobileViewport"
                                class="mobile-accordion__trigger"
                                @click.stop="toggleMobilePanel('submissions')"
                            >
                                <div class="module-title">
                                    <div class="module-icon is-blue">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </div>
                                    <div class="module-title-copy">
                                        <span class="section-label">Pengajuan Saya</span>
                                        <h2>Status Pengajuan</h2>
                                    </div>
                                </div>
                                <div class="mobile-accordion__meta">
                                    <i
                                        :class="
                                            mobilePanelState.submissions ? 'bi bi-chevron-up' : 'bi bi-chevron-down'
                                        "
                                    ></i>
                                </div>
                            </button>

                            <div class="panel-head is-tight" v-else>
                                <div>
                                    <span class="section-label">Pengajuan Saya</span>
                                    <h2>Status Pengajuan</h2>
                                </div>
                            </div>

                            <transition
                                name="accordion"
                                @enter="onAccordionEnter"
                                @after-enter="onAccordionAfterEnter"
                                @leave="onAccordionLeave"
                            >
                                <div
                                    v-show="!isMobileViewport || mobilePanelState.submissions"
                                    class="mobile-accordion__content"
                                >
                                    <button
                                        v-if="!submissionsRevealed"
                                        type="button"
                                        class="module-reveal module-reveal--blue module-reveal--compact"
                                        @click="revealSubmissions"
                                    >
                                        <span class="module-reveal__aura"></span>
                                        <span class="module-reveal__icon"><i class="bi bi-file-earmark-text"></i></span>
                                        <span class="module-reveal__title">Lihat Status Pengajuan</span>
                                        <span class="module-reveal__desc"
                                            >Cuti, izin, lembur, dan pengajuan lain beserta statusnya.</span
                                        >
                                        <span class="module-reveal__cta">
                                            <i class="bi bi-lightning-charge-fill"></i>
                                            <span>Muat data</span>
                                        </span>
                                    </button>

                                    <div v-else class="submission-list">
                                        <div
                                            v-if="isSubmissionsLoading || isDashboardBootstrapping"
                                            class="submission-list is-skeleton-list"
                                        >
                                            <div
                                                v-for="n in 3"
                                                :key="`submission-skeleton-${n}`"
                                                class="submission-item is-skeleton"
                                            >
                                                <div>
                                                    <div
                                                        class="leader-skeleton-line is-title mb-1"
                                                        style="width: 5rem"
                                                    ></div>
                                                    <div class="leader-skeleton-line" style="width: 9rem"></div>
                                                </div>
                                                <div class="leader-skeleton-line is-short" style="width: 4rem"></div>
                                            </div>
                                        </div>

                                        <button
                                            v-if="!isSubmissionsLoading && !isDashboardBootstrapping"
                                            v-for="item in paginatedSubmissions"
                                            :key="item.id"
                                            type="button"
                                            class="submission-item"
                                            @click="handleSubmissionClick(item)"
                                        >
                                            <div>
                                                <strong>{{ item.title }}</strong>
                                                <span
                                                    >{{ item.submittedDateLabel || item.date }} •
                                                    {{ item.description }}</span
                                                >
                                                <span
                                                    v-if="item.detailLabel || item.periodLabel"
                                                    class="submission-item__detail"
                                                >
                                                    <template v-if="item.detailLabel">{{ item.detailLabel }}</template>
                                                    <template v-if="item.detailLabel && item.periodLabel"> • </template>
                                                    <template v-if="item.periodLabel">{{ item.periodLabel }}</template>
                                                </span>
                                                <em v-if="item.expiryNote">{{ item.expiryNote }}</em>
                                            </div>

                                            <small :class="`is-${item.status}`">
                                                {{ item.statusLabel }}
                                            </small>
                                        </button>

                                        <div
                                            v-if="
                                                !isSubmissionsLoading &&
                                                !isDashboardBootstrapping &&
                                                submissionsTotalPages > 1
                                            "
                                            class="submission-pagination"
                                        >
                                            <button
                                                type="button"
                                                class="submission-pagination__btn"
                                                :disabled="submissionsPage <= 1"
                                                @click="goToSubmissionsPage(submissionsPage - 1)"
                                            >
                                                <i class="bi bi-chevron-left"></i>
                                            </button>
                                            <span class="submission-pagination__info">
                                                {{ submissionsPage }} / {{ submissionsTotalPages }}
                                            </span>
                                            <button
                                                type="button"
                                                class="submission-pagination__btn"
                                                :disabled="submissionsPage >= submissionsTotalPages"
                                                @click="goToSubmissionsPage(submissionsPage + 1)"
                                            >
                                                <i class="bi bi-chevron-right"></i>
                                            </button>
                                        </div>

                                        <EmptyState
                                            v-if="
                                                !isSubmissionsLoading &&
                                                !isDashboardBootstrapping &&
                                                !personalSubmissions.length
                                            "
                                            compact
                                            icon="bi bi-file-earmark-check"
                                            title="Belum ada pengajuan"
                                            description="Pengajuan aktif akan muncul di sini."
                                        />
                                    </div>
                                </div>
                            </transition>
                        </section>
                    </aside>
                </section>
            </template>
        </div>

        <!-- MODAL DETAIL TANGGAL -->
        <Modal
            :show="showDayModal && !!selectedDayRecord"
            title="Detail Absensi Harian"
            :subtitle="selectedDayRecord ? formatLongDate(parseDate(selectedDayRecord.date)) : ''"
            size="large"
            :loading="isDayDetailLoading"
            loading-text="Memuat detail absensi..."
            @close="closeDayModal"
        >
            <template #body>
                <div v-if="selectedDayRecord" class="day-modal__body">
                    <!-- Hero Status Block -->
                    <div class="dm-hero" :class="`is-${selectedDayRecord.primaryStatusTone || 'default'}`">
                        <div class="dm-hero__badge">
                            <i class="bi bi-circle-fill"></i>
                            <span>{{ selectedDayRecord.primaryStatusLabel || 'Tidak ada status' }}</span>
                        </div>
                        <div class="dm-hero__times">
                            <div class="dm-hero__time">
                                <span>Check In</span>
                                <strong>{{
                                    selectedDayRecord.selected_checkin || selectedDayRecord.checkIn || '--:--'
                                }}</strong>
                            </div>
                            <div class="dm-hero__time-sep">
                                <i class="bi bi-arrow-right-short"></i>
                            </div>
                            <div class="dm-hero__time">
                                <span>Check Out</span>
                                <strong>{{
                                    selectedDayRecord.selected_checkout || selectedDayRecord.checkOut || '--:--'
                                }}</strong>
                            </div>
                        </div>
                        <div class="dm-hero__shift-row">
                            <i class="bi bi-clock"></i>
                            <span>{{ selectedDayShiftName }}</span>
                            <span class="dm-hero__shift-dot">·</span>
                            <span>{{ selectedDayShiftTime }}</span>
                        </div>
                    </div>

                    <!-- Holiday banner -->
                    <div v-if="selectedDayRecord.isHoliday || selectedDayRecord.holidayName" class="dm-holiday-banner">
                        <i class="bi bi-calendar-heart-fill"></i>
                        <div>
                            <strong>Hari Libur</strong>
                            <span>{{ selectedDayRecord.holidayName || 'Libur Nasional / Perusahaan' }}</span>
                        </div>
                    </div>

                    <!-- Note (shown only when present) -->
                    <div v-if="selectedDayRecord.note" class="dm-note">
                        <i class="bi bi-sticky-fill"></i>
                        <p>{{ selectedDayRecord.note }}</p>
                    </div>

                    <!-- Status & Flags -->
                    <div class="dm-section">
                        <div class="dm-section__head">
                            <i class="bi bi-flag-fill"></i>
                            <span>Status &amp; Flag</span>
                        </div>
                        <div class="dm-flag-list">
                            <div
                                v-for="flag in selectedDayFlags"
                                :key="flag.key"
                                class="dm-flag"
                                :class="`is-${flag.tone}`"
                            >
                                <i :class="statusVisual(flag.key).icon"></i>
                                <span>{{ flag.label }}</span>
                            </div>
                            <EmptyState
                                v-if="!selectedDayFlags.length"
                                compact
                                icon="bi bi-check2-circle"
                                title="Tidak ada flag"
                                description="Semua baik, tidak ada flag tambahan."
                            />
                        </div>
                    </div>

                    <!-- Absensi Terpilih (Comparison Card) -->
                    <div class="dm-section">
                        <div class="dm-section__head">
                            <i class="bi bi-fingerprint"></i>
                            <span>Absensi Terpilih</span>
                        </div>

                        <!-- Skeleton -->
                        <div v-if="isDayDetailLoading" class="dm-compare">
                            <div class="dm-compare__col">
                                <div class="dm-compare__col-head">
                                    <div class="leader-skeleton-line is-short" style="width: 5rem"></div>
                                </div>
                                <div class="dm-compare__row" v-for="n in 2" :key="`sk-l-${n}`">
                                    <div class="leader-skeleton-line is-short mb-1" style="width: 3rem"></div>
                                    <div class="leader-skeleton-line is-title" style="width: 4rem"></div>
                                </div>
                            </div>
                            <div class="dm-compare__divider"></div>
                            <div class="dm-compare__col">
                                <div class="dm-compare__col-head">
                                    <div class="leader-skeleton-line is-short" style="width: 4rem"></div>
                                </div>
                                <div class="dm-compare__row" v-for="n in 2" :key="`sk-r-${n}`">
                                    <div class="leader-skeleton-line is-short mb-1" style="width: 3rem"></div>
                                    <div class="leader-skeleton-line is-title" style="width: 4rem"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Loaded -->
                        <div
                            v-else-if="
                                selectedDayShiftInfo.jamMasuk ||
                                selectedDayShiftInfo.checkIn ||
                                selectedDayShiftInfo.jamKeluar ||
                                selectedDayShiftInfo.checkOut
                            "
                            class="dm-compare"
                        >
                            <!-- Jadwal Shift -->
                            <div class="dm-compare__col">
                                <div class="dm-compare__col-head">
                                    <i class="bi bi-calendar3"></i>
                                    <span>Jadwal Shift</span>
                                </div>
                                <div class="dm-compare__row">
                                    <span class="dm-compare__label">Masuk</span>
                                    <strong class="dm-compare__time">{{ selectedDayShiftInfo.jamMasuk || '-' }}</strong>
                                </div>
                                <div class="dm-compare__row">
                                    <span class="dm-compare__label">Pulang</span>
                                    <strong class="dm-compare__time">{{
                                        selectedDayShiftInfo.jamKeluar || '-'
                                    }}</strong>
                                </div>
                            </div>

                            <div class="dm-compare__divider"></div>

                            <!-- Aktual -->
                            <div class="dm-compare__col">
                                <div class="dm-compare__col-head">
                                    <i class="bi bi-fingerprint"></i>
                                    <span>Aktual</span>
                                </div>
                                <div class="dm-compare__row">
                                    <span class="dm-compare__label">Check In</span>
                                    <strong
                                        class="dm-compare__time"
                                        :class="{
                                            'is-present': !!selectedDayShiftInfo.checkIn,
                                            'is-absent': !selectedDayShiftInfo.checkIn,
                                        }"
                                        >{{ selectedDayShiftInfo.checkIn || '-' }}</strong
                                    >
                                </div>
                                <div class="dm-compare__row">
                                    <span class="dm-compare__label">Check Out</span>
                                    <strong
                                        class="dm-compare__time"
                                        :class="{
                                            'is-present': !!selectedDayShiftInfo.checkOut,
                                            'is-absent': !selectedDayShiftInfo.checkOut,
                                        }"
                                        >{{ selectedDayShiftInfo.checkOut || '-' }}</strong
                                    >
                                </div>
                            </div>
                        </div>

                        <EmptyState
                            v-else
                            compact
                            icon="bi bi-fingerprint"
                            title="Checktime kosong"
                            description="Tidak ada check-in/check-out terpilih dari SP."
                        />
                    </div>

                    <!-- Transaksi Terkait -->
                    <div class="dm-section">
                        <div class="dm-section__head">
                            <i class="bi bi-journal-check"></i>
                            <span>Transaksi Terkait</span>
                        </div>
                        <div class="dm-items">
                            <template v-if="isDayDetailLoading">
                                <div v-for="n in 2" :key="`tx-sk-${n}`" class="dm-item is-skeleton">
                                    <div
                                        class="skeleton-avatar"
                                        style="width: 2.2rem; height: 2.2rem; border-radius: 0.72rem; flex-shrink: 0"
                                    ></div>
                                    <div>
                                        <div class="leader-skeleton-line is-title mb-1" style="width: 8rem"></div>
                                        <div class="leader-skeleton-line" style="width: 12rem"></div>
                                    </div>
                                </div>
                            </template>
                            <template v-else>
                                <div v-for="tx in selectedDayTransactions" :key="tx.id" class="dm-item dm-item--tx">
                                    <div class="dm-item__icon">
                                        <i :class="tx.icon || 'bi bi-receipt-cutoff'"></i>
                                    </div>
                                    <div class="dm-item__body">
                                        <div class="dm-item__title-row">
                                            <strong>{{ tx.title }}</strong>
                                            <span
                                                v-if="tx.status_label"
                                                class="dm-tx-status"
                                                :class="`is-${tx.status_tone || 'info'}`"
                                            >
                                                <i :class="txStatusIcon(tx.status_tone)"></i>
                                                {{ tx.status_label }}
                                            </span>
                                        </div>
                                        <span class="dm-item__desc">{{ tx.description }}</span>
                                        <span v-if="tx.no_transaksi" class="dm-item__no">
                                            <i class="bi bi-hash"></i>{{ tx.no_transaksi }}
                                        </span>
                                    </div>
                                </div>
                            </template>
                            <EmptyState
                                v-if="!isDayDetailLoading && !selectedDayTransactions.length"
                                compact
                                icon="bi bi-journal-check"
                                title="Tidak ada transaksi"
                                description="Belum ada izin, cuti, sakit, atau lembur terkait tanggal ini."
                            />
                        </div>
                    </div>

                    <!-- Log Mesin -->
                    <div class="dm-section">
                        <div class="dm-section__head">
                            <i class="bi bi-cpu"></i>
                            <span>Log Mesin</span>
                            <span v-if="!isDayDetailLoading && selectedDayRawLogs.length" class="dm-section__count">{{
                                selectedDayRawLogs.length
                            }}</span>
                        </div>
                        <div v-if="isDayDetailLoading" class="dm-raw-list">
                            <div v-for="n in 4" :key="`raw-sk-${n}`" class="dm-raw-item is-skeleton">
                                <div>
                                    <div class="leader-skeleton-line is-title mb-1" style="width: 4rem"></div>
                                    <div class="leader-skeleton-line is-short"></div>
                                </div>
                                <div class="leader-skeleton-line is-short" style="width: 5.5rem"></div>
                            </div>
                        </div>
                        <div v-else class="dm-raw-list">
                            <div
                                v-for="log in selectedDayRawLogs"
                                :key="log.id || log.checktime"
                                class="dm-raw-item"
                                :class="{ 'is-selected': log.is_selected_checkin || log.is_selected_checkout }"
                            >
                                <div class="dm-raw-item__time">
                                    <strong>{{ log.time || '-' }}</strong>
                                    <div class="dm-raw-item__meta">
                                        <span
                                            class="dm-raw-item__source"
                                            :class="log.is_online ? 'is-online' : 'is-office'"
                                        >
                                            <i :class="log.is_online ? 'bi bi-wifi' : 'bi bi-building'"></i>
                                            {{ log.keterangan || '-' }}
                                        </span>
                                        <span v-if="!log.is_online && log.lokasi" class="dm-raw-item__lokasi">
                                            <i class="bi bi-geo-alt"></i>
                                            {{ log.lokasi }}
                                        </span>
                                    </div>
                                </div>
                                <div class="dm-raw-item__badges">
                                    <span v-if="log.is_selected_checkin" class="dm-badge is-checkin">In</span>
                                    <span v-if="log.is_selected_checkout" class="dm-badge is-checkout">Out</span>
                                </div>
                            </div>
                            <EmptyState
                                v-if="!selectedDayRawLogs.length"
                                compact
                                icon="bi bi-fingerprint"
                                title="Data mesin kosong"
                                description="Tidak ada raw CHECKINOUT pada tanggal ini."
                            />
                        </div>
                    </div>
                </div>
            </template>
        </Modal>

        <Modal
            :show="showOvertimeDetailModal && !!selectedOvertimeAssignment"
            title="Detail Penugasan Lembur"
            :subtitle="selectedOvertimeAssignment?.transactionNumber || ''"
            size="large"
            @close="closeOvertimeAssignmentDetail"
        >
            <template #body>
                <div v-if="selectedOvertimeAssignment" class="overtime-detail">
                    <section class="overtime-detail-hero" :class="`is-${selectedOvertimeAssignment.statusTone}`">
                        <div>
                            <span>{{ selectedOvertimeAssignment.statusLabel }}</span>
                            <h3>{{ selectedOvertimeAssignment.typeLabel }}</h3>
                            <p>
                                {{ selectedOvertimeAssignment.dateLabel }} -
                                {{ selectedOvertimeAssignment.timeRangeLabel }}
                            </p>
                            <p class="overtime-detail-hero__source">
                                <i :class="selectedOvertimeAssignment.isAutoShift ? 'bi bi-magic' : 'bi bi-hash'"></i>
                                Sumber: {{ selectedOvertimeAssignment.sourceLabel }}
                            </p>
                        </div>
                        <strong>{{ selectedOvertimeAssignment.durationLabel }}</strong>
                    </section>

                    <!-- Info khusus Lembur Shift otomatis (tanpa transaksi pengajuan) -->
                    <section v-if="selectedOvertimeAssignment.isAutoShift" class="overtime-detail-section">
                        <div class="overtime-detail-autoshift">
                            <i class="bi bi-magic"></i>
                            <div>
                                <strong>Lembur otomatis dari long shift</strong>
                                <p>
                                    Lembur ini tidak memerlukan pengajuan. Sistem menghitungnya otomatis dari durasi
                                    long shift yang kamu jalani pada tanggal ini.
                                </p>
                            </div>
                        </div>

                        <!-- Rincian shift: shift apa, batas (Up To), dan aktual yang didapat -->
                        <div class="overtime-detail-grid is-shift">
                            <div class="overtime-detail-card">
                                <span>Shift</span>
                                <strong>{{ selectedOvertimeAssignment.shiftName || '-' }}</strong>
                            </div>
                            <div class="overtime-detail-card">
                                <span>
                                    Maksimum Lembur Shift
                                    <i
                                        class="bi bi-info-circle overtime-info-icon"
                                        title="Batas maksimum lembur untuk shift ini (Durasi_Lembur). Aktual tidak akan melebihi nilai ini."
                                    ></i>
                                </span>
                                <strong>{{ selectedOvertimeAssignment.shiftUpToLabel || 'Tidak ada' }}</strong>
                            </div>
                            <div class="overtime-detail-card is-highlight">
                                <span>Aktual Kamu Dapat</span>
                                <strong>{{ selectedOvertimeAssignment.shiftActualLabel }}</strong>
                            </div>
                            <div class="overtime-detail-card">
                                <span>
                                    Check In
                                    <i
                                        class="bi bi-info-circle overtime-info-icon"
                                        title="Jam check-in dari SP (sumber kebenaran tunggal). Data ini menjadi asal-muasal perhitungan aktual lembur."
                                    ></i>
                                </span>
                                <strong>{{ selectedOvertimeAssignment.checkInLabel || '-' }}</strong>
                            </div>
                            <div class="overtime-detail-card">
                                <span>
                                    Check Out
                                    <i
                                        class="bi bi-info-circle overtime-info-icon"
                                        title="Jam check-out dari SP (sumber kebenaran tunggal). Data ini menjadi asal-muasal perhitungan aktual lembur."
                                    ></i>
                                </span>
                                <strong>{{ selectedOvertimeAssignment.checkOutLabel || '-' }}</strong>
                            </div>
                        </div>
                    </section>

                    <!-- Detail transaksi (hanya untuk lembur ber-pengajuan) -->
                    <template v-else>
                        <section class="overtime-detail-grid">
                            <div class="overtime-detail-card">
                                <span>Jadwal Penugasan</span>
                                <strong>{{ selectedOvertimeAssignment.assignedRangeLabel }}</strong>
                            </div>
                            <div class="overtime-detail-card">
                                <span>Aktual Absensi</span>
                                <strong>{{ selectedOvertimeAssignment.actualRangeLabel }}</strong>
                            </div>
                            <div class="overtime-detail-card">
                                <span>Check In</span>
                                <strong>{{ selectedOvertimeAssignment.checkInLabel }}</strong>
                            </div>
                            <div class="overtime-detail-card">
                                <span>Check Out</span>
                                <strong>{{ selectedOvertimeAssignment.checkOutLabel }}</strong>
                            </div>
                        </section>

                        <section class="overtime-detail-section">
                            <div class="overtime-detail-section__head">
                                <i class="bi bi-clipboard2-check"></i>
                                <span>Status Perhitungan</span>
                            </div>
                            <div class="overtime-detail-status">
                                <span :class="`is-${selectedOvertimeAssignment.absensiTone}`">
                                    {{ selectedOvertimeAssignment.absensiLabel }}
                                </span>
                                <p>{{ selectedOvertimeAssignment.requirementLabel }}</p>
                            </div>
                        </section>
                    </template>

                    <section class="overtime-detail-section">
                        <div class="overtime-detail-section__head">
                            <i class="bi bi-calculator"></i>
                            <span>Rincian Lembur Hari Ini ({{ selectedOvertimeAssignment.dateLabel }})</span>
                        </div>
                        <div class="overtime-breakdown">
                            <div class="overtime-breakdown__row">
                                <span>Durasi penugasan ini</span>
                                <strong>{{ selectedOvertimeAssignment.durationLabel }}</strong>
                            </div>
                            <div v-if="selectedOvertimeAssignment.hasShift" class="overtime-breakdown__row">
                                <span>Lembur shift (otomatis)</span>
                                <strong>{{ selectedOvertimeAssignment.dayShiftLabel }}</strong>
                            </div>
                            <div class="overtime-breakdown__row">
                                <span>Total kotor sehari</span>
                                <strong>{{ selectedOvertimeAssignment.dayGrossLabel }}</strong>
                            </div>
                            <div
                                v-if="selectedOvertimeAssignment.dayRealLateMinutes > 0"
                                class="overtime-breakdown__row is-deduction"
                            >
                                <span>
                                    Pengurangan keterlambatan
                                    <i
                                        class="bi bi-info-circle overtime-info-icon"
                                        title="Keterlambatan mengurangi durasi lembur sesuai ketentuan HC."
                                    ></i>
                                </span>
                                <strong>− {{ selectedOvertimeAssignment.dayRealLateMinutes }}m</strong>
                            </div>
                            <div class="overtime-breakdown__row is-total">
                                <span>Total bersih sehari</span>
                                <strong>{{ selectedOvertimeAssignment.dayNetLabel }}</strong>
                            </div>
                            <div class="overtime-breakdown__row is-koef">
                                <span>
                                    Jam lembur sah
                                    <i
                                        class="bi bi-info-circle overtime-info-icon"
                                        title="Dibulatkan ke bawah (floor) ke jam penuh sesuai ketentuan HC."
                                    ></i>
                                </span>
                                <strong>{{ selectedOvertimeAssignment.dayKoefisienLabel }}</strong>
                            </div>
                        </div>
                    </section>

                    <section class="overtime-detail-section">
                        <div class="overtime-detail-section__head">
                            <i class="bi bi-chat-left-text"></i>
                            <span>Alasan / Catatan</span>
                        </div>
                        <p class="overtime-detail-note">{{ selectedOvertimeAssignment.fullReason }}</p>
                    </section>
                </div>
            </template>
        </Modal>

        <transition name="fade-scale">
            <div v-if="activeQuickActionGroup" class="quick-action-modal-overlay" @click.self="closeQuickActionGroup">
                <section
                    ref="quickActionModalRef"
                    class="quick-action-modal-card"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="activeQuickActionGroup?.label || 'Akses Cepat'"
                    tabindex="-1"
                    @keydown.esc.prevent="closeQuickActionGroup"
                    @keydown.tab="trapFocusWithin"
                >
                    <header class="quick-action-modal-head">
                        <div class="quick-action-modal-title">
                            <span class="quick-action-modal-icon">
                                <i :class="activeQuickActionGroup.icon"></i>
                            </span>
                            <div>
                                <span class="section-label">Header Menu</span>
                                <h3>{{ activeQuickActionGroup.label }}</h3>
                                <small>{{ activeQuickActionGroup.items.length }} menu tersedia</small>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="quick-action-modal-close"
                            aria-label="Tutup menu cepat"
                            @click="closeQuickActionGroup"
                        >
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </header>

                    <div class="quick-action-modal-body">
                        <button
                            v-for="item in activeQuickActionGroup.items"
                            :key="item.key"
                            type="button"
                            class="quick-action-modal-item"
                            @click="handleQuickAction(item)"
                        >
                            <span class="quick-action-modal-item__icon">
                                <i :class="quickActionIcon(item, activeQuickActionGroup)"></i>
                            </span>
                            <span class="quick-action-modal-item__copy">
                                <strong>{{ item.title }}</strong>
                                <small>{{ item.moduleLabel }}</small>
                            </span>
                            <i class="bi bi-arrow-right-short"></i>
                        </button>
                    </div>

                    <footer class="quick-action-modal-footer">
                        <button type="button" class="quick-action-modal-back" @click="closeQuickActionGroup">
                            <i class="bi bi-arrow-left"></i>
                            <span>Kembali</span>
                        </button>
                    </footer>
                </section>
            </div>
        </transition>
    </div>
</template>

<script setup>
import {
    computed,
    defineComponent,
    h,
    onBeforeUnmount,
    onMounted,
    ref,
    reactive,
    watch,
    watchEffect,
    nextTick,
} from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
import axios from 'axios';
import { DotLottieVue } from '@lottiefiles/dotlottie-vue';
import Modal from '@/components/legacy/modalV2-noir.vue';
import MobileHero from '@/components/Dashboard/MobileHero.vue';
import MobileAwarenessGrid from '@/components/Dashboard/MobileAwarenessGrid.vue';
import { useHomepageTour } from '@/composables/useHomepageTour';
import { tungguTourBolehOtomatis } from '@/composables/tourRegistry.js';
import { trapFocusWithin } from '@/utils/dialogFocus';

const EmptyState = defineComponent({
    name: 'EmptyState',
    props: {
        icon: { type: String, default: 'bi bi-inbox' },
        title: { type: String, default: 'Data belum tersedia' },
        description: { type: String, default: 'Data akan muncul ketika sudah tersedia.' },
        compact: { type: Boolean, default: false },
    },
    setup(props) {
        return () =>
            h('div', { class: ['empty-state', { 'is-compact': props.compact }] }, [
                h('i', { class: props.icon }),
                h('strong', props.title),
                h('span', props.description),
            ]);
    },
});

const page = usePage();
const props = defineProps({
    user: { type: Object, default: () => ({}) },
    cutoffRange: {
        type: Object,
        default: () => {
            const now = new Date();

            const formatYmd = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');

                return `${year}-${month}-${day}`;
            };

            return {
                start: formatYmd(now),
                end: formatYmd(now),
            };
        },
    },
    attendanceRecords: { type: Array, default: () => [] },
    upcomingSchedule: { type: Array, default: () => [] },
    moduleSummary: { type: Object, default: () => ({}) },
    greeting: { type: Object, default: () => ({}) },
    role: { type: String, default: '' },
    pendingApprovals: { type: Number, default: 0 },
    hcis: { type: Object, default: () => ({}) },
    kpi: { type: Object, default: () => ({}) },
    lms: { type: Object, default: () => ({}) },
    overtime: { type: Object, default: () => ({}) },
    leave: { type: Object, default: () => ({}) },
    submissions: { type: Array, default: () => [] },
    initialDashboard: { type: Object, default: null },
    tourProgress: { type: Object, default: null },
});

// ── Homepage Onboarding Tour (driver.js) ────────────────────────────────
// Auto-register di tourRegistry global. AppTopbar Help popover akan baca registry
// & menampilkan CTA "Mulai Tour Interaktif" kontekstual.
// `isLeader` membuat step adaptif — leader dapat step Action Center, staff dapat step Lembur.
const tour = useHomepageTour(
    props.tourProgress,
    {
        component: 'homepage/homepage',
        label: 'Tour Homepage Dashboard',
        description: 'Pandu kamu mengenali semua bagian dashboard utama.',
    },
    {
        isLeader: Boolean(props.greeting?.is_leader || props.role === 'leader'),
        beforeStep: prepareHomepageTourStep,
    },
);

const LOGIN_SPLASH_OVERLAY_ID = 'evo-splash-overlay';
const HOMEPAGE_AUTO_TOUR_DELAY = 1500;
const LOGIN_SPLASH_MAX_WAIT = 7000;
let autoStartTourTimer = null;
// Penantian modal global bisa lama; kalau orangnya keburu pindah halaman,
// tour milik halaman yang sudah dilepas tidak boleh ikut menyala.
let autoStartTourDibatalkan = false;

function waitForLoginSplashToFinish(timeout = LOGIN_SPLASH_MAX_WAIT) {
    if (typeof window === 'undefined') {
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        const startedAt = window.performance?.now?.() ?? Date.now();
        const now = () => window.performance?.now?.() ?? Date.now();
        const settle = () => {
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(resolve);
            });
        };
        const check = () => {
            const splashOverlay = document.getElementById(LOGIN_SPLASH_OVERLAY_ID);
            if (!splashOverlay || now() - startedAt >= timeout) {
                settle();
                return;
            }

            window.setTimeout(check, 120);
        };

        check();
    });
}

const autoStartTourIfFirstTime = () => {
    if (!tour.isFirstTime.value || tour.isActive.value || typeof window === 'undefined') {
        return;
    }

    autoStartTourTimer = window.setTimeout(async () => {
        await waitForLoginSplashToFinish();
        await tungguTourBolehOtomatis();

        if (!autoStartTourDibatalkan && tour.isFirstTime.value && !tour.isActive.value) {
            tour.start({ fromBeginning: true });
        }
    }, HOMEPAGE_AUTO_TOUR_DELAY);
};

const today = new Date();
const attendanceGraceMinutes = 10;
const weekdayLabels = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
const mobileBreakpoint = 767.98;
const mobileTabs = [
    { key: 'home', label: 'Beranda', icon: 'bi bi-house-door' },
    { key: 'absensi', label: 'Absensi', icon: 'bi bi-fingerprint' },
    { key: 'kpi', label: 'KPI', icon: 'bi bi-graph-up-arrow' },
    { key: 'lms', label: 'Learning', icon: 'bi bi-mortarboard' },
    { key: 'more', label: 'Lainnya', icon: 'bi bi-grid' },
];
const activeQuickActionGroup = ref(null);
const quickActionModalRef = ref(null);
let quickActionModalTrigger = null;

function isObjectPayload(value) {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function truncateText(text, maxLength) {
    if (text.length <= maxLength) {
        return text;
    }
    return text.slice(0, maxLength) + '...';
}

function hasDashboardPayload(value) {
    return isObjectPayload(value) && Object.keys(value).length > 0;
}

const hydratedDashboard = hasDashboardPayload(props.initialDashboard) ? props.initialDashboard : null;

const activeCalendarFilter = ref('all');
const selectedCutoffPeriod = ref(hydratedDashboard?.cutoffRange?.period || '');
const selectedAttendanceDonutPeriod = ref(hydratedDashboard?.cutoffRange?.period || '');
const selectedDate = ref('');
const showDayModal = ref(false);
const showOvertimeDetailModal = ref(false);
const selectedOvertimeAssignment = ref(null);
const expandedOvertimeDates = ref(new Set());
const overtimeSectionRef = ref(null);
const attendanceCalendarSectionRef = ref(null);
const hcisTrendHoverIndex = ref(null);
const dayDetailCache = ref({});
const dayDetailLoadingDate = ref('');
const dayDetailError = ref('');
const personalActionItems = ref([]);
const notificationDismissBusy = ref({});
const needsActionError = ref('');
const actionCenterPage = ref(1);
const actionCenterPagination = ref({ current_page: 1, last_page: 1, total: 0, has_prev: false, has_next: false });
const needsActionSummary = ref(null);
const actionCenterPageLoading = ref(false);
const teamDashboardBadge = ref({
    count: 0,
    label: '',
    hasItems: false,
});
const teamDashboardBadgeLoading = ref(false);
const teamDashboardBadgeController = ref(null);
const selectedKpiYear = ref(null);
const selectedKpiPeriod = ref('');
// Kartu KPI, LMS & Pengajuan dimuat by-click (on-demand): tidak ditembak saat
// load/scroll, hanya saat user membuka kartunya -> memangkas query berat (mis.
// AllTransactionsReadService untuk submissions) yg sering tak dilihat.
const kpiRevealed = ref(false);
const lmsRevealed = ref(false);
const submissionsRevealed = ref(false);
const overtimeRevealed = ref(false);
const dashboardState = ref(hydratedDashboard);
const calendarState = ref({
    cutoffRange: hydratedDashboard?.cutoffRange || null,
    cutoffPeriodOptions: Array.isArray(hydratedDashboard?.cutoffPeriodOptions)
        ? hydratedDashboard.cutoffPeriodOptions
        : [],
    attendanceRecords: Array.isArray(hydratedDashboard?.attendanceRecords) ? hydratedDashboard.attendanceRecords : [],
});
const attendanceDonutState = ref({
    cutoffRange: hydratedDashboard?.cutoffRange || null,
    cutoffPeriodOptions: Array.isArray(hydratedDashboard?.cutoffPeriodOptions)
        ? hydratedDashboard.cutoffPeriodOptions
        : [],
    stats: [],
    summary: null,
});
const overtimeAssignmentState = ref({
    items: [],
    meta: null,
});
// Cutoff yang sedang ditampilkan di card lembur (default = cutoff berjalan bootstrap).
const selectedOvertimeCutoff = ref(hydratedDashboard?.cutoffRange?.period || '');
// Cache detail lembur per-cutoff (on-demand) + trend 2-cutoff (fixed, sekali muat).
const overtimeDetailState = ref({
    byCutoff: {},
    trend: [],
    trendLoaded: false,
});
const overtimeCutoffSwitching = ref(false);
const overtimeTrendLoading = ref(false);
const overtimeDetailController = ref(null);
const overtimeTrendController = ref(null);
const attendanceDonutLoading = ref(true);
const attendanceDonutError = ref('');
const attendanceDonutController = ref(null);
const dashboardError = ref('');
const dashboardLoaded = ref(Boolean(hydratedDashboard));
const isDashboardBootstrapping = ref(false);
const domainLoading = ref({
    attendance: false,
    hcis: false,
    needsAction: false,
    kpi: false,
    lms: false,
    overtimeAssignments: false,
    leave: false,
    submissions: false,
    upcoming: false,
});
const domainControllers = ref({});
const domainVersions = ref({});

/*
 * Pembatas konkurensi: maksimal MAX_PARALLEL request domain berjalan bersamaan.
 * Tanpa ini, mount homepage menembak 10 request serentak -> dikali banyak user =
 * burst yang membuat CPU Cloud SQL melonjak. Antrian ini meratakan beban.
 */
const MAX_PARALLEL_DOMAIN_REQUESTS = 3;
let activeDomainRequests = 0;
const domainRequestQueue = [];

function runQueuedDomainRequests() {
    while (activeDomainRequests < MAX_PARALLEL_DOMAIN_REQUESTS && domainRequestQueue.length) {
        const job = domainRequestQueue.shift();
        activeDomainRequests += 1;
        Promise.resolve()
            .then(job)
            .catch(() => {}) // error sudah diteruskan ke pemanggil via reject; cegah unhandled rejection
            .finally(() => {
                activeDomainRequests -= 1;
                runQueuedDomainRequests();
            });
    }
}

function enqueueDomainRequest(task) {
    return new Promise((resolve, reject) => {
        // Teruskan hasil/eror task APA ADANYA (reject tetap reject) supaya
        // loadDomain bisa membedakan sukses vs error/abort di catch-nya.
        domainRequestQueue.push(() => task().then(resolve, reject));
        runQueuedDomainRequests();
    });
}

/*
 * Cache klien (sessionStorage) untuk payload domain. Saat mount / refresh /
 * navigasi balik, render dari cache dulu (0 query), lalu revalidate di belakang.
 * TTL pendek supaya data tetap aktual; absensi HARI INI tetap di-revalidate.
 */
const DOMAIN_CACHE_TTL_MS = 60 * 1000;
const DOMAIN_CACHE_PREFIX = 'hp.domain.v1:';

function domainCacheKey(domain, params) {
    const suffix = params && Object.keys(params).length ? ':' + JSON.stringify(params) : '';
    return DOMAIN_CACHE_PREFIX + domain + suffix;
}

function readDomainCache(domain, params) {
    if (typeof sessionStorage === 'undefined') return null;
    try {
        const raw = sessionStorage.getItem(domainCacheKey(domain, params));
        if (!raw) return null;
        const entry = JSON.parse(raw);
        if (!entry || Date.now() - entry.t > DOMAIN_CACHE_TTL_MS) return null;
        return entry;
    } catch {
        return null;
    }
}

function writeDomainCache(domain, params, payload, raw) {
    if (typeof sessionStorage === 'undefined') return;
    try {
        sessionStorage.setItem(domainCacheKey(domain, params), JSON.stringify({ t: Date.now(), payload, raw }));
    } catch {
        /* kuota penuh / mode privat: abaikan, cache opsional */
    }
}

const isMobileViewport = ref(typeof window !== 'undefined' ? window.innerWidth <= mobileBreakpoint : false);

const activeMobileTab = ref('home');
const mobileSectionRefs = ref({});
const mobileWeekOffset = ref(0);
const mobileWeekState = ref({
    weekStart: '',
    weekEnd: '',
    attendanceRecords: [],
});
const mobileWeekCache = ref({});
const mobileWeekLoading = ref(true);
const mobileWeekError = ref('');
const mobileWeekController = ref(null);
const mobileWeekRequestKey = ref('');
const mobileWeekNavigationLocked = ref(false);
const onAccordionEnter = (el) => {
    el.style.height = '0px';
    void el.offsetHeight;
    el.style.height = el.scrollHeight + 'px';
};
const onAccordionAfterEnter = (el) => {
    el.style.height = 'auto';
};
const onAccordionLeave = (el) => {
    el.style.height = getComputedStyle(el).height;
    void el.offsetHeight;
    setTimeout(() => {
        el.style.height = '0px';
    }, 10);
};

const mobilePanelState = ref({
    action: true,
    calendar: false,
    attendance: false,
    kalenderCutoff: false,
    hcis: false,
    kpi: false,
    lms: false,
    overtime: false,
    submissions: false,
    upcoming: false,
});

const isLeader = computed(() => Boolean(props.greeting?.is_leader || props.role === 'leader'));
const dashboardRoleLabel = computed(() => (isLeader.value ? 'Leader Team' : props.role || 'Anggota'));
const dashboard = computed(() => dashboardState.value || {});
const isTeamDashboardBadgeLoading = computed(() => teamDashboardBadgeLoading.value);
const hasAttendanceDomain = computed(
    () => Boolean(dashboard.value.cutoffRange) || Array.isArray(dashboard.value.attendanceRecords),
);
const hasHcisDomain = computed(() => hasDashboardPayload(dashboard.value.hcis));
const hasKpiDomain = computed(() => hasDashboardPayload(dashboard.value.kpi));
const hasLmsDomain = computed(() => hasDashboardPayload(dashboard.value.lms));
const hasOvertimeDomain = computed(() => hasDashboardPayload(dashboard.value.overtime));
const hasLeaveDomain = computed(() => hasDashboardPayload(dashboard.value.leave));
const hasSubmissionsDomain = computed(() => Array.isArray(dashboard.value.submissions));
const hasUpcomingDomain = computed(() => Array.isArray(dashboard.value.upcomingSchedule));
const isAttendanceLoading = computed(() => domainLoading.value.attendance && !hasAttendanceDomain.value);
const isAttendanceCalendarLoading = computed(
    () => isDashboardBootstrapping.value || isAttendanceLoading.value || domainLoading.value.attendance,
);
const isAttendanceDonutLoading = computed(() => attendanceDonutLoading.value);
const isMobileWeekLoading = computed(() => mobileWeekLoading.value);
const isMobileWeekNavBusy = computed(() => mobileWeekLoading.value || mobileWeekNavigationLocked.value);
const isCurrentWeek = computed(() => mobileWeekOffset.value === 0);
const isHcisLoading = computed(() => domainLoading.value.hcis && !hasHcisDomain.value);
const isNeedsActionLoading = computed(
    () => domainLoading.value.needsAction && !personalActionItems.value.length && !needsActionError.value,
);
const isKpiLoading = computed(() => domainLoading.value.kpi && !hasKpiDomain.value);
const isKpiRefreshing = computed(() => domainLoading.value.kpi && hasKpiDomain.value);
const isLmsLoading = computed(() => domainLoading.value.lms && !hasLmsDomain.value);
const isOvertimeLoading = computed(() => domainLoading.value.attendance && !hasOvertimeDomain.value);
const isOvertimeAssignmentLoading = computed(
    () => domainLoading.value.overtimeAssignments && !overtimeAssignmentState.value.items.length,
);
const isLeaveLoading = computed(() => domainLoading.value.leave && !hasLeaveDomain.value);
const isSubmissionsLoading = computed(() => domainLoading.value.submissions && !hasSubmissionsDomain.value);
const isUpcomingLoading = computed(() => domainLoading.value.upcoming && !hasUpcomingDomain.value);
const dashboardLoading = computed(() => Object.values(domainLoading.value).some(Boolean));
const isHeroAttendanceLoading = computed(
    () => isDashboardBootstrapping.value || isDashboardHydrating.value || isAttendanceLoading.value,
);
const isWorkdayBriefLoading = computed(
    () =>
        isDashboardBootstrapping.value ||
        isDashboardHydrating.value ||
        isAttendanceLoading.value ||
        isKpiLoading.value ||
        isLmsLoading.value,
);
const pendingApprovalsResolved = computed(() =>
    Number(dashboard.value.pendingApprovals ?? props.pendingApprovals ?? 0),
);
const pendingKpiSelectionLabel = computed(() => {
    const year = selectedKpiYear.value || heroKpi.value.year || new Date().getFullYear();
    const period = selectedKpiPeriod.value || heroKpi.value.selectedPeriod || '';
    const periodLabel = period ? formatKpiPeriodLabel(period) : heroKpi.value.periodLabel;

    return [periodLabel, year ? `tahun ${year}` : ''].filter(Boolean).join(' ');
});
const cutoffMonthLabel = computed(
    () =>
        dashboard.value.cutoffRange?.label ||
        new Date().toLocaleDateString('id-ID', {
            month: 'long',
            year: 'numeric',
        }),
);
const cutoffStartDate = computed(() => parseDate(dashboard.value.cutoffRange?.start || props.cutoffRange?.start));
const cutoffEndDate = computed(() => parseDate(dashboard.value.cutoffRange?.end || props.cutoffRange?.end));

const cutoffLabel = computed(() => `${formatLongDate(cutoffStartDate.value)} — ${formatLongDate(cutoffEndDate.value)}`);

const calendarCutoffStartDate = computed(() =>
    parseDate(calendarState.value.cutoffRange?.start || dashboard.value.cutoffRange?.start || props.cutoffRange?.start),
);
const calendarCutoffEndDate = computed(() =>
    parseDate(calendarState.value.cutoffRange?.end || dashboard.value.cutoffRange?.end || props.cutoffRange?.end),
);
const calendarCutoffLabel = computed(
    () => `${formatLongDate(calendarCutoffStartDate.value)} - ${formatLongDate(calendarCutoffEndDate.value)}`,
);

const cutoffPeriodOptions = computed(() => {
    const options = Array.isArray(calendarState.value.cutoffPeriodOptions)
        ? calendarState.value.cutoffPeriodOptions
        : [];
    const currentPeriod =
        calendarState.value.cutoffRange?.period ||
        selectedCutoffPeriod.value ||
        toYmd(calendarCutoffStartDate.value).slice(0, 7);

    if (options.length) {
        return options;
    }

    return [
        {
            value: currentPeriod,
            label: calendarState.value.cutoffRange?.label || calendarCutoffLabel.value,
            start: calendarState.value.cutoffRange?.start || toYmd(calendarCutoffStartDate.value),
            end: calendarState.value.cutoffRange?.end || toYmd(calendarCutoffEndDate.value),
        },
    ];
});

const attendanceRecordsResolved = computed(() => {
    return Array.isArray(dashboard.value.attendanceRecords) ? dashboard.value.attendanceRecords : [];
});

const calendarAttendanceRecordsResolved = computed(() => {
    return Array.isArray(calendarState.value.attendanceRecords) ? calendarState.value.attendanceRecords : [];
});

const mobileWeekAttendanceRecordsResolved = computed(() => {
    return Array.isArray(mobileWeekState.value.attendanceRecords) ? mobileWeekState.value.attendanceRecords : [];
});

const recordsMap = computed(() => {
    const map = new Map();
    const source = attendanceRecordsResolved.value;

    source.forEach((item) => {
        map.set(normalizeDateString(item.date), item);
    });

    return map;
});

const calendarRecordsMap = computed(() => {
    const map = new Map();
    const source = calendarAttendanceRecordsResolved.value;

    source.forEach((item) => {
        map.set(normalizeDateString(item.date), item);
    });

    return map;
});

const mobileWeekRecordsMap = computed(() => {
    const map = new Map();
    const source = mobileWeekAttendanceRecordsResolved.value;

    source.forEach((item) => {
        map.set(normalizeDateString(item.date), item);
    });

    return map;
});

function buildCalendarDay(sourceDate, sourceMap) {
    const cursor = new Date(sourceDate);
    const dateKey = toYmd(cursor);
    const raw = sourceMap.get(dateKey) || {};
    const record = normalizeRecord(raw);
    const isFuture = cursor > today;
    const attendanceTiming = resolveAttendanceTiming(record, dateKey, cursor);
    const flags = buildFlags(record, isFuture, attendanceTiming);
    const primary = resolvePrimaryStatus(record, flags, isFuture, attendanceTiming);
    const primaryVisual = statusVisual(primary);
    const hasProblem = flags.some((flag) => flag.problem);
    const statusChips = buildDayStatusChips(record, flags, primary, attendanceTiming);

    const holidayName = record.holiday_name || record.holidayName || '';
    const hasHolidayData = Boolean(record.is_holiday ?? record.isHoliday ?? holidayName);
    const isActuallyOff =
        record.no_shift ||
        (record.status || '').toLowerCase().includes('off') ||
        (normalizeTimeValue(record.check_in) === '-' && normalizeTimeValue(record.check_out) === '-');
    const isHoliday = hasHolidayData && isActuallyOff;

    return {
        date: dateKey,
        rawDate: new Date(cursor),
        dayNumber: String(cursor.getDate()),
        dayName: cursor.toLocaleDateString('id-ID', { weekday: 'long' }),
        isToday: isSameDate(cursor, today),
        isFuture,
        shiftLabel: record.shift_label || '',
        shiftTime: record.shift_time || '',
        checkIn: normalizeTimeValue(record.check_in),
        checkOut: normalizeTimeValue(record.check_out),
        lateMinutes: record.late_minutes,
        earlyOutMinutes: record.early_out_minutes,
        note: record.note || '',
        primaryStatusKey: primary,
        primaryStatusLabel: primaryVisual.label,
        primaryStatusTone: primaryVisual.tone,
        hasProblem,
        flags,
        statusChips,
        timeline: record.timeline || buildFallbackTimeline(record),
        holidayName,
        isHoliday,
    };
}

const cutoffDays = computed(() => {
    if (!isAttendanceLoading.value && !attendanceRecordsResolved.value.length) {
        return [];
    }

    const days = [];
    const cursor = new Date(cutoffStartDate.value);

    while (cursor <= cutoffEndDate.value) {
        const dateKey = toYmd(cursor);
        const raw = recordsMap.value.get(dateKey) || {};
        const record = normalizeRecord(raw);

        const isFuture = cursor > today;
        const attendanceTiming = resolveAttendanceTiming(record, dateKey, cursor);
        const flags = buildFlags(record, isFuture, attendanceTiming);
        const primary = resolvePrimaryStatus(record, flags, isFuture, attendanceTiming);
        const primaryVisual = statusVisual(primary);
        const hasProblem = flags.some((flag) => flag.problem);
        const statusChips = buildDayStatusChips(record, flags, primary, attendanceTiming);

        days.push({
            date: dateKey,
            rawDate: new Date(cursor),
            dayNumber: String(cursor.getDate()),
            dayName: cursor.toLocaleDateString('id-ID', { weekday: 'long' }),
            isToday: isSameDate(cursor, today),
            isFuture,
            shiftLabel: record.shift_label || '',
            shiftTime: record.shift_time || '',
            checkIn: normalizeTimeValue(record.check_in),
            checkOut: normalizeTimeValue(record.check_out),
            lateMinutes: record.late_minutes,
            earlyOutMinutes: record.early_out_minutes,
            note: record.note || '',
            primaryStatusKey: primary,
            primaryStatusLabel: primaryVisual.label,
            primaryStatusTone: primaryVisual.tone,
            hasProblem,
            flags,
            statusChips,
            timeline: record.timeline || buildFallbackTimeline(record),
        });

        cursor.setDate(cursor.getDate() + 1);
    }

    return days;
});

const calendarCutoffDays = computed(() => {
    if (!isAttendanceCalendarLoading.value && !calendarAttendanceRecordsResolved.value.length) {
        return [];
    }

    // Kasus umum (cutoff terkunci berjalan): calendarState & dashboard diisi dari
    // instance array yang SAMA oleh applyAttendanceSummary -> hasil loop identik.
    // Reuse cutoffDays agar pipeline per-hari ~30x tak dijalankan dua kali.
    // Saat drilldown/ganti periode (syncDashboard:false) array berbeda -> loop penuh.
    if (
        calendarAttendanceRecordsResolved.value === attendanceRecordsResolved.value &&
        calendarCutoffStartDate.value?.getTime?.() === cutoffStartDate.value?.getTime?.() &&
        calendarCutoffEndDate.value?.getTime?.() === cutoffEndDate.value?.getTime?.()
    ) {
        return cutoffDays.value;
    }

    const days = [];
    const cursor = new Date(calendarCutoffStartDate.value);

    while (cursor <= calendarCutoffEndDate.value) {
        const dateKey = toYmd(cursor);
        const raw = calendarRecordsMap.value.get(dateKey) || {};
        const record = normalizeRecord(raw);

        const isFuture = cursor > today;
        const attendanceTiming = resolveAttendanceTiming(record, dateKey, cursor);
        const flags = buildFlags(record, isFuture, attendanceTiming);
        const primary = resolvePrimaryStatus(record, flags, isFuture, attendanceTiming);
        const primaryVisual = statusVisual(primary);
        const hasProblem = flags.some((flag) => flag.problem);
        const statusChips = buildDayStatusChips(record, flags, primary, attendanceTiming);

        days.push({
            date: dateKey,
            rawDate: new Date(cursor),
            dayNumber: String(cursor.getDate()),
            dayName: cursor.toLocaleDateString('id-ID', { weekday: 'long' }),
            isToday: isSameDate(cursor, today),
            isFuture,
            shiftLabel: record.shift_label || '',
            shiftTime: record.shift_time || '',
            checkIn: normalizeTimeValue(record.check_in),
            checkOut: normalizeTimeValue(record.check_out),
            lateMinutes: record.late_minutes,
            note: record.note || '',
            primaryStatusKey: primary,
            primaryStatusLabel: primaryVisual.label,
            primaryStatusTone: primaryVisual.tone,
            hasProblem,
            flags,
            statusChips,
            timeline: record.timeline || buildFallbackTimeline(record),
        });

        cursor.setDate(cursor.getDate() + 1);
    }

    return days;
});

const todayRecord = computed(
    () => cutoffDays.value.find((day) => day.isToday) || cutoffDays.value[cutoffDays.value.length - 1] || null,
);

const isDashboardHydrating = computed(
    () => dashboardLoading.value && !dashboardLoaded.value && !hasDashboardPayload(dashboardState.value),
);

watchEffect(() => {
    if (!selectedDate.value && todayRecord.value?.date) {
        selectedDate.value = todayRecord.value.date;
    }
});

const selectedDayRecord = computed(() => {
    const base =
        mobileWeekDays.value.find((day) => day.date === selectedDate.value) ||
        calendarCutoffDays.value.find((day) => day.date === selectedDate.value) ||
        cutoffDays.value.find((day) => day.date === selectedDate.value) ||
        todayRecord.value ||
        null;
    if (!base) return null;

    const detail = dayDetailCache.value[base.date] || null;

    return {
        ...base,
        ...(detail || {}),
        note: detail?.note ?? base.note,
        timeline: detail?.timeline ?? base.timeline,
    };
});

const selectedDayFlags = computed(() => {
    const detailFlags = dayDetailCache.value[selectedDate.value]?.flags;
    return Array.isArray(detailFlags) && detailFlags.length ? detailFlags : selectedDayRecord.value?.flags || [];
});

const isDayDetailLoading = computed(() => dayDetailLoadingDate.value === selectedDate.value);

const selectedDayTimeline = computed(() => {
    const timeline = selectedDayRecord.value?.timeline || [];

    return timeline.map((item) => ({
        label: item.label,
        time: item.time,
        kind: item.kind,
        icon: timelineIcon(item.kind),
    }));
});

const selectedDayShiftInfo = computed(() => {
    const record = selectedDayRecord.value;
    const cache = dayDetailCache.value[selectedDate.value];
    return {
        jamMasuk: record?.jam_masuk || cache?.selected_shift?.jam_masuk || null,
        jamKeluar: record?.jam_keluar || cache?.selected_shift?.jam_keluar || null,
        checkIn: record?.check_in && record.check_in !== '-' ? record.check_in : cache?.selected_checkin || null,
        checkOut: record?.check_out && record.check_out !== '-' ? record.check_out : cache?.selected_checkout || null,
    };
});

const selectedDayRawLogs = computed(() => {
    const rows = dayDetailCache.value[selectedDate.value]?.raw_logs;
    return Array.isArray(rows) ? rows : [];
});

const selectedDayTransactions = computed(() => {
    const rows = dayDetailCache.value[selectedDate.value]?.transactions;
    return Array.isArray(rows) ? rows : [];
});

function txStatusIcon(tone) {
    switch (tone) {
        case 'success':
            return 'bi bi-check-circle-fill';
        case 'warning':
            return 'bi bi-hourglass-split';
        case 'danger':
            return 'bi bi-x-circle-fill';
        default:
            return 'bi bi-info-circle-fill';
    }
}

const selectedDayShiftName = computed(() => {
    return selectedDayRecord.value?.selected_shift?.name || selectedDayRecord.value?.shiftLabel || 'Tidak ada shift';
});

const selectedDayShiftTime = computed(() => {
    const selectedShift = selectedDayRecord.value?.selected_shift || {};
    if (selectedShift.jam_masuk && selectedShift.jam_keluar) {
        return `${selectedShift.jam_masuk} - ${selectedShift.jam_keluar}`;
    }

    return selectedDayRecord.value?.shiftTime || '-';
});

function isCanceledRequest(error) {
    return (
        error?.name === 'CanceledError' ||
        error?.name === 'AbortError' ||
        error?.code === 'ERR_CANCELED' ||
        (typeof axios.isCancel === 'function' && axios.isCancel(error))
    );
}

function normalizeOptionalYear(value) {
    const year = Number(value || 0);
    return Number.isInteger(year) && year >= 2000 && year <= 2100 ? year : null;
}

function resolveDashboardYear(domain) {
    const source = dashboard.value?.[domain] || props.initialDashboard?.[domain] || props.moduleSummary?.[domain] || {};
    return normalizeOptionalYear(source.year ?? source.kpi_year ?? source.selected_year ?? source.selectedYear);
}

function paramsWithYear(year, key = 'year') {
    return year ? { [key]: year } : {};
}

function syncKpiSelectors(data = {}) {
    const year = normalizeOptionalYear(
        data.selectedYear ?? data.selected_year ?? data.kpi_year ?? data.year ?? selectedKpiYear.value,
    );
    const period = normalizeKpiMonthValue(
        data.selectedPeriod ?? data.selected_period ?? data.period ?? selectedKpiPeriod.value ?? '',
    );

    selectedKpiYear.value = year;
    selectedKpiPeriod.value = period || '';
}

function buildKpiDetailParams(overrides = {}) {
    const year = normalizeOptionalYear(overrides.year ?? selectedKpiYear.value ?? resolveDashboardYear('kpi'));
    const period = normalizeKpiMonthValue(overrides.period ?? selectedKpiPeriod.value);
    const params = {
        mode: overrides.mode || (period === 'all' ? 'ytd' : 'period'),
        ...paramsWithYear(year),
    };

    if (period) {
        params.period = period;
    }

    return params;
}

function loadPersonalKpiDetail(overrides = {}) {
    return loadDomain(
        'kpi',
        '/homepage/personal/kpi-detail',
        (data) => {
            const payload = data || {};
            syncKpiSelectors(payload);
            patchDashboard({ kpi: payload });
        },
        { params: buildKpiDetailParams(overrides) },
    );
}

// Buka kartu KPI on-demand: tandai revealed lalu tembak loader SEKALI. Idempoten
// (klik kedua diabaikan) — selanjutnya refresh/ganti periode lewat handler biasa.
function revealKpi() {
    if (kpiRevealed.value) return;
    kpiRevealed.value = true;
    void loadPersonalKpiDetail({ year: resolveDashboardYear('kpi') });
}

// Buka kartu LMS on-demand: reuse loader lazy-domain 'lms' (idempoten via
// lazyDomainsTriggered set).
function revealLms() {
    if (lmsRevealed.value) return;
    lmsRevealed.value = true;
    triggerLazyDomain('lms');
}

// Buka kartu Pengajuan on-demand: reuse loader lazy-domain 'submissions'.
function revealSubmissions() {
    if (submissionsRevealed.value) return;
    submissionsRevealed.value = true;
    triggerLazyDomain('submissions');
}

// Buka kartu Lembur/Assignment Lembur on-demand: reuse loader lazy-domain
// 'overtimeAssignments' (untuk leader jadi no-op — kartu juga v-if !isLeader).
function revealOvertime() {
    if (overtimeRevealed.value) return;
    overtimeRevealed.value = true;
    // Seed cutoff terpilih ke cutoff berjalan supaya picker menampilkan default benar.
    if (!selectedOvertimeCutoff.value) {
        selectedOvertimeCutoff.value = overtimeBootstrapPeriod.value || '';
    }
    triggerLazyDomain('overtimeAssignments');
    // Trend 2-cutoff dimuat on-demand di sini (bukan di bootstrap) supaya SP rentang
    // panjang tak membebani load awal / endpoint absensi lain.
    void loadOvertimeTrend();
}

// SATU sumber kebenaran untuk "panel by-click terbuka => data dimuat". Menangkap
// SEMUA jalur yang membuka panel (toggleMobilePanel, scrollToMobileSection,
// handleAwarenessClick, prepareHomepageTourStep) — bukan cuma toggle. revealX
// idempoten (early-return) jadi aman dipanggil berulang.
watch(
    [
        () => mobilePanelState.value.kpi,
        () => mobilePanelState.value.lms,
        () => mobilePanelState.value.submissions,
        () => mobilePanelState.value.overtime,
    ],
    ([kpi, lms, submissions, overtime]) => {
        if (kpi) revealKpi();
        if (lms) revealLms();
        if (submissions) revealSubmissions();
        if (overtime) revealOvertime();
    },
);

function handleKpiYearChange(event) {
    const year = normalizeOptionalYear(event?.target?.value);
    selectedKpiYear.value = year;
    void loadPersonalKpiDetail({ year, period: selectedKpiPeriod.value });
}

function handleKpiPeriodChange(event) {
    selectedKpiPeriod.value = normalizeKpiMonthValue(event?.target?.value || '');
    void loadPersonalKpiDetail({
        year: selectedKpiYear.value,
        period: selectedKpiPeriod.value,
    });
}

function startDomainRequest(domain) {
    if (domainControllers.value[domain]) {
        domainControllers.value[domain].abort();
    }

    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    const version = Number(domainVersions.value[domain] || 0) + 1;
    domainControllers.value = { ...domainControllers.value, [domain]: controller };
    domainVersions.value = { ...domainVersions.value, [domain]: version };

    return { controller, version };
}

function isLatestDomainRequest(domain, version) {
    return Number(domainVersions.value[domain] || 0) === Number(version || 0);
}

function patchDashboard(partial) {
    dashboardState.value = {
        ...(dashboardState.value || {}),
        ...(partial || {}),
    };
    dashboardLoaded.value = true;
}

async function loadDomain(domain, endpoint, mapper, options = {}) {
    const params = options.params || {};

    // Cache-first: kalau ada cache segar, render dulu (tanpa nunggu jaringan).
    // Lalu tetap revalidate di belakang, KECUALI options.cacheOnly diminta.
    if (!options.skipCache) {
        const cached = readDomainCache(domain, params);
        if (cached) {
            try {
                mapper(cached.payload, cached.raw || {});
            } catch {
                /* mapper gagal pada data cache lama: lanjut revalidate */
            }
            if (options.cacheOnly) return;
        }
    }

    const { controller, version } = startDomainRequest(domain);
    domainLoading.value = { ...domainLoading.value, [domain]: true };
    dashboardError.value = '';

    try {
        const response = await enqueueDomainRequest(() =>
            axios.get(endpoint, {
                signal: controller?.signal,
                params,
            }),
        );

        if (!isLatestDomainRequest(domain, version)) return;

        const payload = response?.data?.data ?? response?.data ?? {};
        writeDomainCache(domain, params, payload, response?.data || {});
        mapper(payload, response?.data || {});
    } catch (error) {
        if (isCanceledRequest(error) || !isLatestDomainRequest(domain, version)) return;

        console.error(`Failed to load homepage ${domain}`, error);
        const message = 'Sebagian data dashboard gagal dimuat.';
        if (domain === 'needsAction') {
            needsActionError.value = message;
        } else if (!dashboardError.value) {
            dashboardError.value = message;
        }
    } finally {
        if (isLatestDomainRequest(domain, version)) {
            domainLoading.value = { ...domainLoading.value, [domain]: false };
        }
    }
}

function buildAttendanceParams(overrides = {}) {
    const period = overrides.cutoffPeriod || '';
    return period ? { cutoff_period: period } : {};
}

function applyAttendanceSummary(data, { syncDashboard = true } = {}) {
    // Segarkan acuan "hari ini" tiap data absensi masuk agar isToday/isFuture tak
    // basi bila tab dibiarkan terbuka melewati tengah malam (today ditangkap sekali
    // saat setup). cutoffDays yang ikut recompute akan membaca nilai terbaru.
    today.setTime(Date.now());

    const cutoffRange = data?.cutoffRange || null;
    selectedCutoffPeriod.value = cutoffRange?.period || selectedCutoffPeriod.value;
    calendarState.value = {
        cutoffRange,
        cutoffPeriodOptions: Array.isArray(data?.cutoffPeriodOptions) ? data.cutoffPeriodOptions : [],
        attendanceRecords: Array.isArray(data?.attendanceRecords) ? data.attendanceRecords : [],
    };

    if (syncDashboard) {
        patchDashboard({
            cutoffRange,
            cutoffPeriodOptions: Array.isArray(data?.cutoffPeriodOptions) ? data.cutoffPeriodOptions : [],
            attendanceRecords: Array.isArray(data?.attendanceRecords) ? data.attendanceRecords : [],
            overtime: data?.overtime || {},
            pendingApprovals: Number(data?.pendingApprovals ?? dashboard.value.pendingApprovals ?? 0),
        });
    }
}

function loadPersonalAttendanceSummary(overrides = {}) {
    const syncDashboard = overrides.syncDashboard ?? !overrides.cutoffPeriod;

    return loadDomain(
        'attendance',
        '/homepage/personal/attendance-summary',
        (data) => {
            applyAttendanceSummary(data, { syncDashboard });
        },
        {
            params: buildAttendanceParams(overrides),
            // Absensi (terutama HARI INI) harus selalu fresh: jangan render dari
            // cache klien. Endpoint ini sudah ringan di server (memoize + SYNC
            // 1 hari), jadi tak perlu cache di sisi klien.
            skipCache: true,
        },
    );
}

function loadOvertimeAssignments(overrides = {}) {
    // Rentang tanggal opsional (dari opsi cutoff terpilih) supaya daftar penugasan
    // ikut cutoff yang dipilih di picker. Tanpa override = cutoff berjalan (default
    // server). loadDomain mem-cache per-(params) jadi tiap cutoff punya entry sendiri.
    // Seluruh periode diambil SEKALI, lalu dipaginasi di browser.
    //
    // per_page 200 adalah plafon yang sama dengan yang dijaga server. Satu hari
    // paling banyak melahirkan 3 komponen, jadi satu cutoff (31 hari) mentok di
    // 93 item — 200 memberi ruang tanpa membuka pintu muatan raksasa.
    const params = { page: 1, per_page: 200 };
    if (overrides.tglAwal && overrides.tglAkhir) {
        params.tgl_awal = overrides.tglAwal;
        params.tgl_akhir = overrides.tglAkhir;
    }

    return loadDomain(
        'overtimeAssignments',
        '/uDash/getAllLembur',
        (data, raw) => {
            const rows = Array.isArray(data) ? data : Array.isArray(raw?.data) ? raw.data : [];

            overtimeAssignmentState.value = {
                items: rows,
                meta: {
                    currentPage: raw?.current_page ?? 1,
                    total: Number(raw?.total ?? rows.length),
                },
            };
        },
        { params },
    );
}

// Trend lembur 2-cutoff (per minggu) — ON-DEMAND, sekali muat saat card dibuka.
// Fixed: tidak ikut berubah saat cutoff picker diganti.
async function loadOvertimeTrend() {
    if (overtimeDetailState.value.trendLoaded || overtimeTrendLoading.value) return;

    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    overtimeTrendController.value?.abort?.();
    overtimeTrendController.value = controller;
    overtimeTrendLoading.value = true;

    try {
        const response = await axios.get('/homepage/personal/overtime-detail', {
            signal: controller?.signal,
            params: { include_trend: 1 },
        });
        const data = response?.data?.data ?? response?.data ?? {};

        overtimeDetailState.value = {
            ...overtimeDetailState.value,
            trend: Array.isArray(data?.monthlyTrend) ? data.monthlyTrend : [],
            trendLoaded: true,
        };
    } catch (error) {
        if (!isCanceledRequest(error)) {
            console.error('Failed to load overtime trend', error);
        }
    } finally {
        overtimeTrendLoading.value = false;
    }
}

// Ringkasan lembur untuk cutoff NON-berjalan (on-demand, di-cache per cutoff).
async function loadOvertimeDetailSummary(period) {
    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    overtimeDetailController.value?.abort?.();
    overtimeDetailController.value = controller;

    try {
        const response = await axios.get('/homepage/personal/overtime-detail', {
            signal: controller?.signal,
            params: { cutoff_period: period },
        });
        const data = response?.data?.data ?? response?.data ?? {};

        overtimeDetailState.value = {
            ...overtimeDetailState.value,
            byCutoff: {
                ...overtimeDetailState.value.byCutoff,
                [period]: data?.overtime || {},
            },
        };
    } catch (error) {
        if (!isCanceledRequest(error)) {
            console.error('Failed to load overtime detail', error);
        }
    }
}

// Ganti cutoff card lembur: angka ringkasan + daftar penugasan ikut cutoff terpilih
// (chart trend TIDAK ikut). Cutoff berjalan pakai payload bootstrap (tanpa fetch).
async function handleOvertimeCutoffChange(event) {
    const period = event?.target?.value || '';
    if (!period || period === selectedOvertimeCutoff.value) return;

    selectedOvertimeCutoff.value = period;

    const option = (cutoffPeriodOptions.value || []).find((opt) => opt.value === period) || null;
    const assignmentOverrides =
        option?.start && option?.end ? { tglAwal: option.start, tglAkhir: option.end } : {};

    const currentPeriod = overtimeBootstrapPeriod.value;
    const tasks = [];

    if (period !== currentPeriod && !overtimeDetailState.value.byCutoff[period]) {
        tasks.push(loadOvertimeDetailSummary(period));
    }
    tasks.push(loadOvertimeAssignments(assignmentOverrides));

    overtimeCutoffSwitching.value = true;
    try {
        await Promise.all(tasks);
    } finally {
        overtimeCutoffSwitching.value = false;
    }
}

async function loadAttendanceDonutComposition(overrides = {}) {
    const period = overrides.cutoffPeriod || selectedAttendanceDonutPeriod.value || selectedCutoffPeriod.value || '';
    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;

    attendanceDonutController.value?.abort?.();
    attendanceDonutController.value = controller;
    attendanceDonutLoading.value = true;
    attendanceDonutError.value = '';

    try {
        const response = await axios.get('/homepage/personal/attendance-composition', {
            signal: controller?.signal,
            params: buildAttendanceParams({ cutoffPeriod: period }),
        });
        const data = response?.data?.data ?? response?.data ?? {};

        attendanceDonutState.value = {
            cutoffRange: data?.cutoffRange || null,
            cutoffPeriodOptions: Array.isArray(data?.cutoffPeriodOptions) ? data.cutoffPeriodOptions : [],
            stats: Array.isArray(data?.stats) ? data.stats : [],
            summary: data?.summary || null,
        };
        selectedAttendanceDonutPeriod.value =
            data?.cutoffRange?.period || period || selectedAttendanceDonutPeriod.value || selectedCutoffPeriod.value;
    } catch (error) {
        if (isCanceledRequest(error)) return;

        console.error('Failed to load homepage attendance composition', error);
        attendanceDonutError.value = 'Komposisi absensi gagal dimuat.';
        attendanceDonutState.value = {
            ...attendanceDonutState.value,
            stats: [],
            summary: null,
        };
    } finally {
        if (attendanceDonutController.value === controller) {
            attendanceDonutLoading.value = false;
            attendanceDonutController.value = null;
        }
    }
}

async function loadMobileWeekCalendar() {
    const weekStart = toYmd(mobileWeekStartDate.value);
    const cached = mobileWeekCache.value[weekStart];

    if (mobileWeekLoading.value && mobileWeekRequestKey.value === weekStart) {
        return;
    }

    // Minggu yang MENGANDUNG kemarin/hari ini = realtime: JANGAN pakai cache klien
    // (check-in/out & koreksi 2 hari terakhir harus selalu fresh). Minggu lampau
    // (seluruhnya < kemarin) boleh dari cache.
    const todayYmd = toYmd(new Date());
    const weekEndYmd = toYmd(new Date(mobileWeekStartDate.value.getTime() + 6 * 24 * 60 * 60 * 1000));
    const yesterdayYmd = toYmd(new Date(Date.now() - 24 * 60 * 60 * 1000));
    const weekIsRealtime = weekEndYmd >= yesterdayYmd; // minggu menyentuh kemarin/hari ini/future

    if (cached && !weekIsRealtime) {
        mobileWeekState.value = cached;
        mobileWeekError.value = '';
        mobileWeekLoading.value = false;
        mobileWeekRequestKey.value = '';
        return;
    }

    if (mobileWeekController.value) {
        mobileWeekController.value.abort();
    }

    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    mobileWeekController.value = controller;
    mobileWeekRequestKey.value = weekStart;
    mobileWeekLoading.value = true;
    mobileWeekError.value = '';

    try {
        const response = await axios.get('/homepage/personal/weekly-calendar', {
            signal: controller?.signal,
            params: { week_start: weekStart },
        });
        const payload = response?.data?.data ?? {};
        const nextState = {
            weekStart: payload.weekStart || weekStart,
            weekEnd: payload.weekEnd || toYmd(new Date(mobileWeekStartDate.value.getTime() + 6 * 24 * 60 * 60 * 1000)),
            attendanceRecords: Array.isArray(payload.attendanceRecords) ? payload.attendanceRecords : [],
        };

        mobileWeekState.value = nextState;
        // Hanya simpan ke cache klien bila minggu LAMPAU (stabil). Minggu realtime
        // tak di-cache supaya selalu fetch fresh (konsisten dgn guard di atas).
        if (!weekIsRealtime) {
            mobileWeekCache.value = {
                ...mobileWeekCache.value,
                [weekStart]: nextState,
            };
        }
    } catch (error) {
        if (isCanceledRequest(error)) return;

        console.error('Failed to load homepage weekly calendar', error);
        mobileWeekError.value = 'Data mingguan belum bisa dimuat.';
        mobileWeekState.value = {
            weekStart,
            weekEnd: toYmd(new Date(mobileWeekStartDate.value.getTime() + 6 * 24 * 60 * 60 * 1000)),
            attendanceRecords: [],
        };
    } finally {
        if (mobileWeekController.value === controller) {
            mobileWeekLoading.value = false;
            mobileWeekRequestKey.value = '';
        }
    }
}

async function loadTeamDashboardBadge() {
    if (!isLeader.value) return;

    teamDashboardBadgeController.value?.abort?.();
    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    teamDashboardBadgeController.value = controller;
    teamDashboardBadgeLoading.value = true;

    try {
        const kpiYear = resolveDashboardYear('kpi');
        const response = await axios.get('/homepage/personal/needs-action-badge', {
            signal: controller?.signal,
            params: paramsWithYear(kpiYear, 'kpi_year'),
        });
        const payload = response?.data?.data ?? {};

        teamDashboardBadge.value = {
            count: Number(payload.count || 0),
            label: String(payload.label || ''),
            hasItems: Boolean(payload.has_items),
        };
    } catch (error) {
        if (isCanceledRequest(error)) return;

        console.error('Failed to load team dashboard badge', error);
        teamDashboardBadge.value = {
            count: 0,
            label: '',
            hasItems: false,
        };
    } finally {
        if (teamDashboardBadgeController.value === controller) {
            teamDashboardBadgeLoading.value = false;
        }
    }
}

/*
 * Domain "lazy": panel di bawah lipatan. Tidak dimuat saat mount; dipicu saat
 * panelnya masuk viewport (IntersectionObserver) atau sebagai fallback idle.
 * Disimpan agar observer & fallback memanggil loader yang sama (idempoten via
 * versioning + concurrency queue).
 */
function loadLazyDomain(domain) {
    const lmsYear = resolveDashboardYear('lms');
    switch (domain) {
        case 'lms':
            return loadDomain('lms', '/homepage/personal/lms-summary', (data) => patchDashboard({ lms: data || {} }), {
                params: paramsWithYear(lmsYear),
            });
        case 'leave':
            return loadDomain('leave', '/homepage/personal/leave-summary', (data) =>
                patchDashboard({ leave: data || {} }),
            );
        case 'submissions':
            return loadSubmissionsPage(submissionsPage.value);
        case 'upcoming':
            return loadDomain('upcoming', '/homepage/personal/upcoming-schedule', (data) =>
                patchDashboard({ upcomingSchedule: Array.isArray(data) ? data : [] }),
            );
        case 'overtimeAssignments':
            return isLeader.value ? Promise.resolve() : loadOvertimeAssignments();
        default:
            return Promise.resolve();
    }
}

// LMS, submissions & overtimeAssignments keluar dari lazy-scroll: kini murni
// by-click (revealLms/revealSubmissions/revealOvertime). Sisa lazy-scroll: leave.
const LAZY_DOMAINS = ['leave'];
const lazyDomainsTriggered = new Set();

function triggerLazyDomain(domain) {
    if (lazyDomainsTriggered.has(domain)) return;
    lazyDomainsTriggered.add(domain);
    void loadLazyDomain(domain);
}

/**
 * Apakah panel sebuah lazy-domain terlihat di viewport SAAT INI (dengan margin kecil,
 * selaras rootMargin observer). Dipakai fallback idle agar hanya memuat panel yang
 * benar-benar terlihat tanpa scroll, bukan semua domain.
 */
function isLazyDomainVisible(domain) {
    if (typeof window === 'undefined' || typeof document === 'undefined') return false;
    const el = document.querySelector(`[data-lazy-domain="${domain}"]`);
    if (!el) return false;
    const rect = el.getBoundingClientRect();
    const viewportH = window.innerHeight || document.documentElement.clientHeight || 0;
    const margin = 200; // selaras rootMargin observer
    return rect.top < viewportH + margin && rect.bottom > -margin;
}

function whenIdle(cb, timeout = 1500) {
    if (typeof window !== 'undefined' && typeof window.requestIdleCallback === 'function') {
        window.requestIdleCallback(cb, { timeout });
    } else {
        window.setTimeout(cb, 300);
    }
}

/**
 * Bootstrap above-the-fold dalam SATU request (/homepage/personal/bootstrap):
 * attendance + composition + hcis + weekly dilayani dari 1 pass SP absen di
 * server (memoization + trend in-memory). Menggantikan 4 request terpisah di
 * initial load -> memangkas fan-out & hit tabel sessions/cache. Below-the-fold
 * tetap lazy. Loader individual (loadPersonalAttendanceSummary, loadMobileWeek-
 * Calendar, dll.) tetap ada untuk navigasi/refresh spesifik.
 */
async function loadPersonalBootstrap() {
    const { controller, version } = startDomainRequest('attendance');
    domainLoading.value = { ...domainLoading.value, attendance: true };
    dashboardError.value = '';
    attendanceDonutLoading.value = true;
    attendanceDonutError.value = '';
    mobileWeekLoading.value = true;
    mobileWeekError.value = '';

    try {
        const response = await axios.get('/homepage/personal/bootstrap', {
            signal: controller?.signal,
        });

        if (!isLatestDomainRequest('attendance', version)) return;

        const data = response?.data?.data ?? {};

        // 1) Ringkasan absensi (+ overtime + pendingApprovals) -> dashboard & kalender.
        applyAttendanceSummary(data.attendance || {}, { syncDashboard: true });

        // 2) Komposisi donut (logika apply sama dgn loadAttendanceDonutComposition).
        const comp = data.composition || {};
        attendanceDonutState.value = {
            cutoffRange: comp?.cutoffRange || null,
            cutoffPeriodOptions: Array.isArray(comp?.cutoffPeriodOptions) ? comp.cutoffPeriodOptions : [],
            stats: Array.isArray(comp?.stats) ? comp.stats : [],
            summary: comp?.summary || null,
        };
        selectedAttendanceDonutPeriod.value =
            comp?.cutoffRange?.period || selectedAttendanceDonutPeriod.value || selectedCutoffPeriod.value;

        // 3) Ringkasan HCIS.
        patchDashboard({ hcis: data.hcis || {} });

        // 4) Kalender mingguan (minggu berjalan). Navigasi ke minggu lain tetap
        //    lewat loadMobileWeekCalendar (fetch spesifik week_start).
        const wk = data.weeklyCalendar || {};
        mobileWeekState.value = {
            weekStart: wk.weekStart || toYmd(mobileWeekStartDate.value),
            weekEnd: wk.weekEnd || toYmd(new Date(mobileWeekStartDate.value.getTime() + 6 * 24 * 60 * 60 * 1000)),
            attendanceRecords: Array.isArray(wk.attendanceRecords) ? wk.attendanceRecords : [],
        };
    } catch (error) {
        if (isCanceledRequest(error) || !isLatestDomainRequest('attendance', version)) return;

        console.error('Failed to load homepage bootstrap', error);
        dashboardError.value = 'Sebagian data dashboard gagal dimuat.';
    } finally {
        if (isLatestDomainRequest('attendance', version)) {
            domainLoading.value = { ...domainLoading.value, attendance: false };
        }
        attendanceDonutLoading.value = false;
        mobileWeekLoading.value = false;
        mobileWeekRequestKey.value = '';
    }
}

async function loadDashboard(options = {}) {
    const bootstrapLoad = Boolean(options.bootstrap);

    if (bootstrapLoad) {
        isDashboardBootstrapping.value = true;
        lazyDomainsTriggered.clear();

        // Kartu by-click yang SUDAH dibuka user harus ikut dimuat ulang: revealX
        // early-return karena flag *Revealed masih true, sedangkan lazyDomainsTriggered
        // baru saja di-clear. Tanpa ini, retryDashboardLoad meninggalkan kartu revealed
        // dengan data lama/kosong. Pada mount pertama semua flag false -> no-op.
        if (kpiRevealed.value) void loadPersonalKpiDetail({ year: resolveDashboardYear('kpi') });
        if (lmsRevealed.value) triggerLazyDomain('lms');
        if (submissionsRevealed.value) triggerLazyDomain('submissions');
        if (overtimeRevealed.value) triggerLazyDomain('overtimeAssignments');
    }

    try {
        // GELOMBANG 1 — above-the-fold dlm 1 request: attendance + composition +
        // hcis + weekly (bootstrap). Di-AWAIT supaya skeleton inti mati setelah
        // data ini tiba.
        await Promise.allSettled([loadPersonalBootstrap(), loadTeamDashboardBadge()]);
    } finally {
        if (bootstrapLoad) {
            isDashboardBootstrapping.value = false;
        }
    }

    // GELOMBANG 2 — penting tapi tak harus instan: needs-action, upcoming.
    // (hcis termuat via bootstrap di Gelombang 1; KPI kini by-click via revealKpi.)
    void Promise.allSettled([loadNeedsActionPage(1), loadLazyDomain('upcoming')]);

    // GELOMBANG 3 — lazy: panel bawah-lipatan dipicu IntersectionObserver. Sebagai
    // fallback (observer gagal / panel sudah terlihat tanpa scroll), saat idle HANYA
    // muat domain yang panelnya BENAR-BENAR terlihat sekarang. Sebelumnya fallback
    // memuat SEMUA lazy domain tanpa cek visibility -> niat lazy batal & tiap login
    // menembak 5 query (lms/leave/submissions/upcoming/overtimeAssignments) walau
    // mayoritas user cuma lihat absensi lalu pergi. Sisanya tetap dimuat saat di-scroll.
    whenIdle(() => {
        if (typeof IntersectionObserver === 'undefined') {
            // Browser tanpa observer: tak ada mekanisme scroll-load -> muat semua.
            LAZY_DOMAINS.forEach((domain) => triggerLazyDomain(domain));
            return;
        }
        LAZY_DOMAINS.forEach((domain) => {
            if (isLazyDomainVisible(domain)) triggerLazyDomain(domain);
        });
    });
}

function retryDashboardLoad() {
    void loadDashboard();
}

const calendarFilters = [
    { key: 'all', label: 'Semua' },
    { key: 'problem', label: 'Bermasalah' },
    { key: 'late', label: 'Terlambat' },
    { key: 'early-out', label: 'Pulang Cepat' },
    { key: 'no-in-out', label: 'No IN/OUT' },
    { key: 'izin-cuti', label: 'Izin/Cuti' },
    { key: 'sakit', label: 'Sakit' },
];

const filteredCutoffDays = computed(() => {
    if (activeCalendarFilter.value === 'all') return calendarCutoffDays.value;
    if (activeCalendarFilter.value === 'problem') return calendarCutoffDays.value.filter((day) => day.hasProblem);
    if (activeCalendarFilter.value === 'late') {
        return calendarCutoffDays.value.filter((day) => dayHasStatus(day, 'late'));
    }
    if (activeCalendarFilter.value === 'early-out') {
        return calendarCutoffDays.value.filter((day) => dayHasStatus(day, 'early-out'));
    }
    if (activeCalendarFilter.value === 'no-in-out') {
        return calendarCutoffDays.value.filter((day) => ['no-checkin', 'no-checkout'].includes(day.primaryStatusKey));
    }
    if (activeCalendarFilter.value === 'izin-cuti') {
        return calendarCutoffDays.value.filter((day) => ['izin', 'cuti'].includes(day.primaryStatusKey));
    }
    if (activeCalendarFilter.value === 'sakit') {
        return calendarCutoffDays.value.filter((day) => day.primaryStatusKey === 'sakit');
    }

    return calendarCutoffDays.value;
});

const todaySnapshot = computed(() => {
    if (isDashboardHydrating.value) {
        return {
            shiftLabel: 'Memuat data...',
            shiftTime: '-',
            checkIn: '-',
            checkOut: '-',
            checkInMeta: 'Memuat',
            checkOutMeta: 'Memuat',
            statusLabel: 'Memuat',
            statusMeta: 'Mengambil data backend',
        };
    }

    const record = todayRecord.value || {};
    const checkIn = record.checkIn || '-';
    const checkOut = record.checkOut || '-';

    return {
        shiftLabel: record.shiftLabel || 'Belum ada shift hari ini',
        shiftTime: record.shiftTime || '-',
        checkIn,
        checkOut,
        checkInMeta: checkIn !== '-' ? 'Sudah tercatat' : 'Belum tercatat',
        checkOutMeta: checkOut !== '-' ? 'Sudah tercatat' : 'Belum tercatat',
        statusLabel: record.primaryStatusLabel || 'Belum ada data',
        statusMeta: record.isToday ? 'Hari ini' : 'Periode berjalan',
    };
});

const todayAwareness = computed(() => buildTodayAwareness(todayRecord.value));

const attendanceIssueCount = computed(() => cutoffDays.value.filter((day) => day.hasProblem).length);
const calendarAttendanceIssueCount = computed(() => calendarCutoffDays.value.filter((day) => day.hasProblem).length);
const calendarOnTimeDayCount = computed(() => countCalendarByStatus('present'));
const calendarLateDayCount = computed(() => countCalendarByStatus('late'));
const calendarPresentDayCount = computed(() => calendarOnTimeDayCount.value + calendarLateDayCount.value);
const actionCenterIssueCount = computed(() => unreadPendingCount.value);
const totalLateMinutes = computed(() =>
    cutoffDays.value.reduce((total, day) => total + Number(day?.lateMinutes || 0), 0),
);
const totalEarlyOutMinutes = computed(() =>
    cutoffDays.value.reduce((total, day) => total + Number(day?.earlyOutMinutes || 0), 0),
);

const attendanceDonutDefinitions = [
    {
        key: 'present',
        label: 'Hadir',
        tone: 'success',
        color: '#16a34a',
        filter: 'all',
    },
    {
        key: 'on-time',
        label: 'On-time',
        tone: 'success',
        color: '#22c55e',
        filter: 'on-time',
    },
    {
        key: 'late',
        label: 'Terlambat',
        tone: 'warning',
        color: '#f59e0b',
        filter: 'late',
    },
    {
        key: 'early-out',
        label: 'Pulang Cepat',
        tone: 'warning',
        color: '#fb923c',
        filter: 'early-out',
    },
    {
        key: 'no-in-out',
        label: 'No IN/OUT',
        tone: 'danger',
        color: '#ef4444',
        filter: 'no-in-out',
    },
    {
        key: 'izin-cuti',
        label: 'Izin/Cuti',
        tone: 'info',
        color: '#2563eb',
        filter: 'izin-cuti',
    },
    {
        key: 'sakit',
        label: 'Sakit',
        tone: 'info',
        color: '#0ea5e9',
        filter: 'sakit',
    },
    {
        key: 'off',
        label: 'Libur/Off',
        tone: 'muted',
        color: '#64748b',
        filter: 'all',
    },
];

const attendanceDonutStats = computed(() => {
    const source = Array.isArray(attendanceDonutState.value.stats) ? attendanceDonutState.value.stats : [];
    const byKey = source.reduce((result, item) => {
        if (item?.key) result[item.key] = item;
        return result;
    }, {});

    return attendanceDonutDefinitions.map((item) => ({
        ...item,
        ...(byKey[item.key] || {}),
        count: Number(byKey[item.key]?.count || 0),
    }));
});

const attendanceDonutSeries = computed(() => attendanceDonutStats.value.map((item) => item.count));
const attendanceDonutLabels = computed(() => attendanceDonutStats.value.map((item) => item.label));
const attendanceDonutPeriodOptions = computed(() => {
    const options = Array.isArray(attendanceDonutState.value.cutoffPeriodOptions)
        ? attendanceDonutState.value.cutoffPeriodOptions
        : [];

    if (options.length) return options;

    return Array.isArray(calendarState.value.cutoffPeriodOptions) ? calendarState.value.cutoffPeriodOptions : [];
});

const attendanceDonutSummary = computed(() => {
    const source = attendanceDonutState.value.summary || {};
    const totalDays =
        Number(source.totalDays ?? source.total_days) ||
        attendanceDonutSeries.value.reduce((total, value) => total + Number(value || 0), 0);
    const presentCount =
        Number(source.presentCount ?? source.present_count) ||
        attendanceDonutStats.value.find((item) => item.key === 'present')?.count ||
        0;
    const percentage =
        Number(source.percentage) || (totalDays > 0 ? Math.round((Number(presentCount || 0) / totalDays) * 100) : 0);

    return {
        totalDays,
        presentCount,
        percentage,
        percentLabel: source.percentLabel || source.percent_label || `${percentage}%`,
        periodLabel:
            source.periodLabel ||
            source.period_label ||
            attendanceDonutState.value.cutoffRange?.label ||
            calendarCutoffLabel.value ||
            cutoffMonthLabel.value ||
            'Cutoff aktif',
    };
});

const attendanceDonutOptions = computed(() => ({
    chart: {
        toolbar: { show: false },
        fontFamily: 'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 420,
        },
    },
    colors: attendanceDonutStats.value.map((item) => item.color),
    labels: attendanceDonutLabels.value,
    dataLabels: { enabled: false },
    legend: { show: false },
    stroke: {
        width: 3,
        colors: ['#ffffff'],
    },
    plotOptions: {
        pie: {
            expandOnClick: false,
            donut: {
                size: '68%',
                labels: {
                    show: true,
                    name: {
                        show: true,
                        fontSize: '10px',
                        fontWeight: 800,
                        color: '#64748b',
                        offsetY: 14,
                    },
                    value: {
                        show: true,
                        fontSize: '20px',
                        fontWeight: 950,
                        color: '#0f172a',
                        offsetY: -12,
                        formatter: () => attendanceDonutSummary.value.percentLabel,
                    },
                    total: {
                        show: true,
                        showAlways: true,
                        label: 'Hadir',
                        fontSize: '10px',
                        fontWeight: 850,
                        color: '#64748b',
                        formatter: () => attendanceDonutSummary.value.percentLabel,
                    },
                },
            },
        },
    },
    tooltip: {
        y: {
            formatter: (value) => `${Number(value || 0)} hari`,
        },
    },
    states: {
        hover: { filter: { type: 'lighten', value: 0.03 } },
        active: { filter: { type: 'none' } },
    },
}));

const hcisPersonal = computed(() => {
    const source = dashboard.value.hcis || {};
    const sourceStats = source?.stats || {};
    const fallbackAlphaCount = cutoffDays.value.filter((day) => day.primaryStatusKey === 'alpha').length;
    const fallbackNoCheckinCount = cutoffDays.value.filter((day) => day.primaryStatusKey === 'no-checkin').length;
    const fallbackNoCheckoutCount = cutoffDays.value.filter((day) => day.primaryStatusKey === 'no-checkout').length;
    const fallbackTrend = [
        buildHcisTrendPointFromDays(cutoffDays.value, dashboard.value.cutoffRange || props.cutoffRange),
    ];

    return {
        stats: {
            attendanceIssues: Number(sourceStats.attendanceIssues ?? attendanceIssueCount.value ?? 0),
            hcisCritical: Number(sourceStats.hcisCritical ?? attendanceIssueCount.value ?? 0),
            lateCount: Number(
                sourceStats.lateCount ?? cutoffDays.value.filter((day) => dayHasStatus(day, 'late')).length,
            ),
            lateMinutes: Number(sourceStats.lateMinutes ?? sourceStats.late_minutes ?? totalLateMinutes.value),
            alphaCount: Number(sourceStats.alphaCount ?? sourceStats.alpha_count ?? fallbackAlphaCount),
            noCheckinCount: Number(sourceStats.noCheckinCount ?? fallbackNoCheckinCount),
            noCheckoutCount: Number(sourceStats.noCheckoutCount ?? fallbackNoCheckoutCount),
            noInOutCount: Number(
                sourceStats.noInOutCount ??
                    Number(sourceStats.noCheckinCount ?? fallbackNoCheckinCount) +
                        Number(sourceStats.noCheckoutCount ?? fallbackNoCheckoutCount),
            ),
            izinCutiCount: Number(
                sourceStats.izinCutiCount ??
                    cutoffDays.value.filter((day) => ['izin', 'cuti'].includes(day.primaryStatusKey)).length,
            ),
        },
        trend:
            Array.isArray(source?.trend) && source.trend.length
                ? source.trend.map(normalizeHcisTrendPoint)
                : fallbackTrend.filter(Boolean),
    };
});

const hcisTrendLatestLabel = computed(() => {
    const latest = hcisPersonal.value.trend[hcisPersonal.value.trend.length - 1] || null;
    const value = Number(latest?.percentage ?? latest?.value ?? 0);

    return `${Number.isFinite(value) ? value.toFixed(value % 1 === 0 ? 0 : 1) : 0}% terbaru`;
});

// Subtitle trend kehadiran: dinamis mengikuti cutoff berjalan (bukan lagi teks
// statis "4 minggu / 7 cutoff terakhir"). Trend kini per-minggu sepanjang cutoff.
const hcisTrendScopeLabel = computed(() => {
    const label =
        dashboard.value.hcis?.cutoffRange?.label ||
        dashboard.value.cutoffRange?.label ||
        calendarState.value.cutoffRange?.label ||
        '';

    return label ? `Per minggu · ${label}` : 'Per minggu · cutoff berjalan';
});

const hcisPersonalStats = computed(() => [
    {
        key: 'late',
        label: 'Terlambat',
        value: hcisPersonal.value.stats.lateCount,
        caption: 'hari cutoff aktif',
        tone: 'warning',
    },
    {
        key: 'no-in-out',
        label: 'No IN/OUT',
        value: hcisPersonal.value.stats.noInOutCount,
        caption: 'perlu cek',
        tone: 'danger',
    },
    {
        key: 'alpha',
        label: 'Jumlah Alpha',
        value: hcisPersonal.value.stats.alphaCount,
        caption: 'cutoff aktif',
        tone: 'orange',
    },
    {
        key: 'izin-cuti',
        label: 'Izin/Cuti',
        value: hcisPersonal.value.stats.izinCutiCount,
        caption: 'tercatat',
        tone: 'info',
    },
]);

const heroSummary = computed(() => {
    if (isDashboardHydrating.value) {
        return {
            title: 'Ringkasan Hari Ini',
            message: 'Sedang menyiapkan ringkasan absensi, KPI, LMS, lembur, dan pengajuan kamu.',
            focusLabel: 'Memuat data terbaru',
            focusIcon: 'bi bi-hourglass-split',
        };
    }

    const highlights = [];

    if (isLeader.value && pendingApprovalsResolved.value > 0) {
        highlights.push({
            label: `${pendingApprovalsResolved.value} approval menunggu`,
            icon: 'bi bi-clipboard-check',
        });
    }

    if (heroKpi.value.pendingCount > 0) {
        highlights.push({
            label: `${heroKpi.value.pendingCount} item KPI perlu ditutup`,
            icon: 'bi bi-graph-up-arrow',
        });
    }

    if (heroLms.value.mandatoryPending > 0) {
        highlights.push({
            label: `${heroLms.value.mandatoryPending} training wajib belum selesai`,
            icon: 'bi bi-mortarboard',
        });
    }

    if (attendanceIssueCount.value > 0) {
        highlights.push({
            label: `${attendanceIssueCount.value} catatan absensi perlu dicek`,
            icon: 'bi bi-fingerprint',
        });
    }

    const primaryHighlight = highlights[0] || {
        label: `Status hari ini: ${todaySnapshot.value.statusLabel}`,
        icon: 'bi bi-stars',
    };

    const extraHighlights = highlights.slice(1, 3).map((item) => item.label);
    const baseMessage = 'Pantau absensi, KPI, LMS, lembur, dan pengajuan dalam satu tampilan.';
    const contextualMessage = extraHighlights.length
        ? `${primaryHighlight.label}. ${todayAwareness.value.message} ${extraHighlights.join(' | ')}.`
        : `${baseMessage} ${todayAwareness.value.message}`;

    return {
        title: 'Ringkasan Hari Ini',
        message: contextualMessage,
        focusLabel: primaryHighlight.label,
        focusIcon: primaryHighlight.icon,
    };
});

const workdayBrief = computed(() => {
    if (isDashboardHydrating.value) {
        return {
            mainValue: 'Memuat...',
            mainLabel: '-',
            cutoffProgress: '-',
            nextSchedule: 'Memuat...',
        };
    }

    const totalDays = cutoffDays.value.length || 1;
    const todayIndex = cutoffDays.value.findIndex((day) => day.isToday);
    const cutoffDay = todayIndex >= 0 ? todayIndex + 1 : totalDays;
    const nextSchedule = upcomingResolved.value?.[1]?.label || '-';

    return {
        mainValue: todaySnapshot.value.statusLabel,
        mainLabel: todaySnapshot.value.shiftTime,
        cutoffProgress: `${cutoffDay}/${totalDays}`,
        nextSchedule,
    };
});

const heroKpi = computed(() => {
    const source = dashboard.value.kpi || props.moduleSummary?.kpi || {};
    const parameter = source.parameter || {};
    const performance = source.performance || {};
    const ytd = source.ytd || {};
    const parameterSchedule = source.parameterSchedule || source.parameter_schedule || {};
    const performanceSchedule = source.performanceSchedule || source.performance_schedule || {};
    const rawParameterRows = parameter.rows || source.parameter_rows || source.parameterRows || source.parameters || [];
    const parameters = normalizeKpiParameters(rawParameterRows);
    const parameterSubmitted = Boolean(
        parameter.isSubmitted ??
        parameter.is_submitted ??
        source.parameter_submitted ??
        source.parameterSubmitted ??
        false,
    );
    const parameterHasTargets = Boolean(
        parameter.hasTargets ??
        parameter.has_targets ??
        source.parameter_has_targets ??
        source.parameterHasTargets ??
        parameterSubmitted,
    );
    const performanceSubmitted = Boolean(
        performance.isSubmitted ??
        performance.is_submitted ??
        source.performance_submitted ??
        source.performanceSubmitted ??
        false,
    );
    const parameterScheduled = Boolean(
        parameter.isScheduled ??
        parameter.is_scheduled ??
        parameterSchedule.isScheduled ??
        parameterSchedule.is_scheduled ??
        false,
    );
    const performanceScheduled = Boolean(
        performance.isScheduled ??
        performance.is_scheduled ??
        performanceSchedule.isScheduled ??
        performanceSchedule.is_scheduled ??
        false,
    );
    const ytdHasData = Boolean(ytd.hasData ?? ytd.has_data ?? ytd.period_count ?? ytd.submitted_count ?? false);
    const hasData = Boolean(
        Object.keys(source).length > 0 &&
        (source.has_data ??
            source.hasData ??
            (parameterScheduled || performanceScheduled || parameters.length || ytdHasData)),
    );

    const periodLabel =
        source.period_label ||
        source.periodLabel ||
        source.selectedPeriodLabel ||
        formatKpiPeriodLabel(source.selectedPeriod || source.selected_period || source.period) ||
        'Periode berjalan';
    const parameterPeriod =
        parameter.periodLabel ||
        parameter.period_label ||
        source.parameter_period ||
        source.parameterPeriod ||
        periodLabel;
    const performancePeriod =
        performance.periodLabel ||
        performance.period_label ||
        source.performance_period ||
        source.performancePeriod ||
        periodLabel;
    const year = Number(
        source.selectedYear ??
            source.selected_year ??
            source.kpi_year ??
            source.year ??
            selectedKpiYear.value ??
            new Date().getFullYear(),
    );
    const selectedPeriod = normalizeKpiMonthValue(
        source.selectedPeriod || source.selected_period || source.period || selectedKpiPeriod.value || '',
    );
    const performanceItemCount = Number(
        performance.itemCount ??
            performance.item_count ??
            source.performance_item_count ??
            source.performanceItemCount ??
            parameters.filter((param) => param.hasPerformanceUpdate).length,
    );
    const score = source.score ?? performance?.summary?.score ?? ytd.score ?? ytd.achievement ?? 0;
    const achievement = source.achievement ?? performance?.summary?.score ?? ytd.achievement ?? 0;
    const yearOptions = normalizeKpiYearOptions(source.yearOptions || source.year_options || [], year);
    const periodOptions = normalizeKpiPeriodOptions(
        source.periodOptions || source.period_options || [],
        selectedPeriod,
    );

    // Sumber tunggal & otoritatif: pending_items dari backend (sudah akurat — cek jadwal aktif
    // perorangan→global, Tgl_Masuk, submission benar, due count, tone info/warning).
    const backendPendingItems = Array.isArray(source.pending_items || source.pendingItems)
        ? source.pending_items || source.pendingItems
        : [];

    const warningItems = backendPendingItems.map((item) => ({
        key: item.id || item.key || 'kpi',
        tone: item.tone === 'info' ? 'is-info' : item.tone === 'success' ? 'is-success' : 'is-warning',
        icon: item.tone === 'info' ? 'bi bi-info-circle' : 'bi bi-exclamation-circle',
        title: item.title || '',
        text: item.description || item.title || '',
    }));

    if (source.deadline_text || source.deadlineText) {
        warningItems.push({
            key: 'deadline',
            tone: 'is-info',
            icon: 'bi bi-clock-history',
            title: 'Deadline KPI',
            text: source.deadline_text || source.deadlineText,
        });
    }

    return {
        hasData,
        score: hasData ? score : '-',
        gradeLabel: source.grade_label || source.gradeLabel || ytd.grade_label || ytd.gradeLabel || 'Belum ada data',
        achievement,
        weightedScore: source.weighted_score ?? source.weightedScore ?? 0,
        parameterSubmitted,
        parameterHasTargets,
        performanceSubmitted,
        parameterScheduled,
        performanceScheduled,
        periodLabel,
        parameterPeriod,
        performancePeriod,
        year,
        selectedPeriod,
        yearOptions,
        periodOptions,
        yearLabel: `Tahun ${year}`,
        rankLabel: source.rank_label || source.rankLabel || '-',
        rankMeta: source.rank_meta || source.rankMeta || 'Belum dihitung',
        trendDirection:
            source.trend_direction || source.trendDirection || ytd.trend_direction || ytd.trendDirection || '0%',
        kpiPendingItems: backendPendingItems,
        pendingCount: warningItems.filter((w) => w.tone === 'is-warning').length,
        parameterStatusLabel: (() => {
            if (!parameterScheduled) return 'Belum Dijadwalkan';
            const approval = parameter.approvalStatus ?? parameter.approval_status ?? 'none';
            if (approval === 'approved') return 'Sudah Disetujui ✓';
            if (approval === 'rejected') return 'Ditolak, Revisi';
            if (approval === 'pending') return 'Menunggu Approval';
            if (parameterSubmitted) return 'Sudah Mengisi';
            return 'Belum Mengisi';
        })(),
        parameterStatusTone: (() => {
            if (!parameterScheduled) return 'is-muted';
            const approval = parameter.approvalStatus ?? parameter.approval_status ?? 'none';
            if (approval === 'approved') return 'is-success';
            if (approval === 'rejected') return 'is-danger';
            if (approval === 'pending') return 'is-info';
            if (parameterSubmitted) return 'is-success';
            return 'is-warning';
        })(),
        performanceStatusLabel: (() => {
            if (!performanceScheduled) return 'Belum Dijadwalkan';
            const approval = performance.approvalStatus ?? performance.approval_status ?? 'none';
            const hasParams = Boolean(performance.hasParameters ?? performance.has_parameters ?? parameterHasTargets);
            if (approval === 'approved') return 'Sudah Mengisi ✓';
            if (approval === 'rejected') return 'Ditolak, Revisi';
            if (approval === 'pending') return 'Menunggu Approval';
            if (performanceSubmitted) return 'Sudah Mengisi';
            if (!hasParams) return 'Tidak Ada Parameter';
            // Punya parameter, tetapi tak ada target jatuh tempo di periode ini —
            // tidak ada yang bisa diisi, jadi bukan "Belum Mengisi".
            if (!(performance.hasDueTarget ?? performance.has_due_target ?? true)) return 'Tidak Ada Target';
            return 'Belum Mengisi';
        })(),
        performanceStatusTone: (() => {
            if (!performanceScheduled) return 'is-muted';
            const approval = performance.approvalStatus ?? performance.approval_status ?? 'none';
            const hasParams = Boolean(performance.hasParameters ?? performance.has_parameters ?? parameterHasTargets);
            if (approval === 'approved') return 'is-success';
            if (approval === 'rejected') return 'is-danger';
            if (approval === 'pending') return 'is-info';
            if (performanceSubmitted) return 'is-success';
            if (!hasParams) return 'is-muted';
            if (!(performance.hasDueTarget ?? performance.has_due_target ?? true)) return 'is-muted';
            return 'is-warning';
        })(),
        parameterCount: parameterHasTargets
            ? Number(source.parameter_count ?? source.parameterCount ?? parameters.length)
            : 0,
        performanceItemCount,
        ytdHasData,
        ytdScore: ytd.score ?? ytd.achievement ?? achievement ?? 0,
        ytdSubmittedCount: Number(
            ytd.submitted_count ?? ytd.submittedCount ?? ytd.period_count ?? ytd.periodCount ?? 0,
        ),
        achievementGradeLabel: source.achievement_grade_label || source.achievementGradeLabel || '-',
        achievementGradeColor: source.achievement_grade_color || source.achievementGradeColor || '#94a3b8',
        ytdGradeLabel: source.ytd_grade_label || source.ytdGradeLabel || ytd.grade_label || ytd.gradeLabel || '-',
        ytdGradeColor: source.ytd_grade_color || source.ytdGradeColor || ytd.grade_color || ytd.gradeColor || '#94a3b8',
        parameterMessage:
            parameter.message ||
            (parameterSubmitted
                ? `KPI Parameter ${parameterPeriod} sudah diisi.`
                : parameterHasTargets
                  ? `Target KPI ${parameterPeriod} tersedia, tetapi tidak ada jadwal pengisian aktif.`
                  : `Belum ada Parameter KPI tahun/periode ini.`),
        performanceMessage:
            performance.message ||
            (performanceSubmitted
                ? `Sudah mengisi update performance periode ${performancePeriod}.`
                : `Belum mengisi update performance periode ${performancePeriod}.`),
        performanceCanSubmit: performance.canSubmit ?? performance.can_submit ?? true,
        emptyMessage:
            source.emptyMessage ||
            source.empty_message ||
            'Belum ada jadwal pengisian KPI atau update performance tahun ini.',
        warningItems,
        trend: hasData ? source.trend || ytd.trend || [] : [],
        parameters: parameterHasTargets ? parameters : [],
    };
});

function normalizeKpiYearOptions(source, fallbackYear) {
    const years = (Array.isArray(source) ? source : [])
        .map((item) => Number(item?.value ?? item?.year ?? item))
        .filter((year) => Number.isInteger(year) && year >= 2000 && year <= 2100);

    if (fallbackYear && !years.includes(Number(fallbackYear))) {
        years.unshift(Number(fallbackYear));
    }

    return [...new Set(years)].sort((a, b) => b - a);
}

function normalizeKpiPeriodOptions(source, selectedPeriod) {
    const selectedMonth = normalizeKpiMonthValue(selectedPeriod);
    const options = (Array.isArray(source) ? source : [])
        .map((item) => {
            const value = normalizeKpiMonthValue(item?.value ?? item?.month ?? item?.period ?? item);

            return {
                value,
                label: item?.label || formatKpiPeriodLabel(value) || value,
            };
        })
        .filter((item) => item.value);

    if (selectedMonth && !options.some((item) => item.value === selectedMonth)) {
        options.unshift({
            value: selectedMonth,
            label: formatKpiPeriodLabel(selectedMonth) || selectedMonth,
        });
    }

    return options;
}

function normalizeKpiMonthValue(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';

    const fullPeriod = raw.match(/^\d{4}-(\d{2})$/);
    if (fullPeriod) return fullPeriod[1];

    if (/^\d{1,2}$/.test(raw)) {
        const month = Number(raw);
        return month >= 1 && month <= 12 ? String(month).padStart(2, '0') : '';
    }

    return raw;
}

function formatKpiPeriodLabel(value) {
    if (['all', 'full_year'].includes(String(value || '').toLowerCase())) {
        return 'Full Year';
    }

    const monthOnly = String(value || '').match(/^(\d{1,2})$/);
    if (monthOnly) {
        const date = new Date(2000, Number(monthOnly[1]) - 1, 1);

        return date.toLocaleDateString('id-ID', {
            month: 'long',
        });
    }

    const match = String(value || '').match(/^(\d{4})-(\d{2})$/);
    if (!match) return '';

    const date = new Date(Number(match[1]), Number(match[2]) - 1, 1);

    return date.toLocaleDateString('id-ID', {
        month: 'long',
        year: 'numeric',
    });
}

function normalizeKpiParameters(source) {
    return (Array.isArray(source) ? source : []).map((item, index) => {
        const achievement = Number(item?.achievement ?? item?.score ?? 0);
        const weight = Number(item?.weight ?? item?.bobot ?? 0);

        return {
            id: item?.id || `${item?.name || item?.parameter || 'parameter'}-${index}`,
            name: item?.name || item?.parameter || 'Parameter KPI',
            score: Number(item?.score ?? achievement),
            achievement,
            weight,
            target: Number(item?.target ?? 0),
            actual: Number(item?.actual ?? 0),
            statusLabel:
                item?.status || item?.statusLabel || (item?.has_performance_update ? 'Terisi' : 'Belum ada penilaian'),
            statusColor: item?.status_color || item?.statusColor || '#7c3aed',
            formStatus: item?.form_status || item?.formStatus || '',
            hasPerformanceUpdate: Boolean(item?.has_performance_update ?? item?.hasPerformanceUpdate ?? false),
        };
    });
}

const heroLms = computed(() => {
    const source = dashboard.value.lms || props.moduleSummary?.lms || {};
    const hasData = Object.keys(source).length > 0 && (source.has_data ?? source.hasData ?? true);

    const completed = hasData ? (source.completed_courses ?? source.completed ?? 0) : 0;
    const total = hasData ? (source.enrolled_courses ?? source.total ?? 0) : 0;
    const progress = total ? Math.round((completed / total) * 100) : 0;

    const completedMonthly = hasData ? source.completed_monthly || source.completedMonthly || [] : [];
    const durationMonthly = hasData ? source.duration_monthly || source.durationMonthly || [] : [];
    const learningHours = hasData ? Number(source.learning_hours ?? source.learningHours ?? 0) : 0;
    // Avg durasi = rata-rata durasi belajar per training yang sudah dilalui (dihitung di backend).
    const avgLearningDurationHours = hasData ? Number(source.avg_duration_hours ?? source.avgDurationHours ?? 0) : 0;
    // Rata-rata durasi per bulan aktif — dipakai khusus untuk header chart bulanan.
    const activeDurationRows = durationMonthly.filter((item) => Number(item.avg_hours ?? item.avgHours ?? 0) > 0);
    const avgMonthlyDurationHours = activeDurationRows.length
        ? activeDurationRows.reduce((sum, item) => sum + Number(item.avg_hours ?? item.avgHours ?? 0), 0) /
          activeDurationRows.length
        : 0;

    return {
        hasData,
        completed,
        total,
        progress: source.progress ?? progress,
        progressLabel: total ? `${source.progress ?? progress}%` : '-',
        completedThisMonth: source.completed_this_month ?? source.completedThisMonth ?? 0,
        mandatoryPending: source.mandatory_pending ?? source.mandatoryPending ?? 0,
        averageScore: source.average_score ?? source.averageScore ?? null,
        learningGain: source.learning_gain ?? source.learningGain ?? null,
        learningHours,
        certificates: source.certificates ?? 0,
        overdue: source.overdue ?? 0,
        nextSession: source.next_session || source.nextSession || 'Tidak ada training aktif',
        nextSessionDate: source.next_session_date || source.nextSessionDate || '-',
        completedMonthly,
        durationMonthly,
        avgLearningDurationHours,
        avgLearningDurationLabel: formatLearningDurationLabel(avgLearningDurationHours),
        avgMonthlyDurationHours,
        avgMonthlyDurationLabel: formatLearningDurationLabel(avgMonthlyDurationHours),
        attemptedCourses: hasData ? Number(source.attempted_courses ?? source.attemptedCourses ?? 0) : 0,
        progressList: hasData ? source.learning_progress || source.progressList || [] : [],
    };
});

// Cutoff bootstrap (berjalan) = sumber angka default dari dashboard. Cutoff lain
// diambil on-demand ke overtimeDetailState.byCutoff[period].
const overtimeBootstrapPeriod = computed(
    () => dashboard.value.cutoffRange?.period || selectedOvertimeCutoff.value || '',
);

const overtimeSummary = computed(() => {
    const currentPeriod = overtimeBootstrapPeriod.value;
    const selected = selectedOvertimeCutoff.value || currentPeriod;
    const cachedDetail = overtimeDetailState.value.byCutoff[selected];
    // Cutoff berjalan -> pakai payload bootstrap; cutoff lain -> pakai cache detail
    // on-demand (fallback ke bootstrap saat cache belum siap; skeleton menutupi).
    const source =
        selected && selected !== currentPeriod && cachedDetail
            ? cachedDetail
            : dashboard.value.overtime || props.moduleSummary?.overtime || {};
    const hasData = Object.keys(source).length > 0 && (source.has_data ?? source.hasData ?? true);

    return {
        hasData,
        periodLabel: source.period_label || source.periodLabel || 'Bulan berjalan',
        totalHours: hasData ? (source.total_hours ?? source.totalHours ?? 0) : 0,
        workdayHours: hasData ? (source.workday_hours ?? source.workdayHours ?? 0) : 0,
        shiftHours: hasData ? (source.shift_hours ?? source.shiftHours ?? 0) : 0,
        beforeWorkHours: hasData ? (source.before_work_hours ?? source.beforeWorkHours ?? 0) : 0,
        afterWorkHours: hasData ? (source.after_work_hours ?? source.afterWorkHours ?? 0) : 0,
        holidayHours: hasData ? (source.holiday_hours ?? source.holidayHours ?? 0) : 0,
        activeAssignments: hasData ? (source.active_assignments ?? source.activeAssignments ?? 0) : 0,
        totalThisMonth: hasData ? (source.total_this_month ?? source.totalThisMonth ?? 0) : 0,
        nextAssignmentLabel:
            source.next_assignment_label || source.nextAssignmentLabel || 'Tidak ada assignment aktif.',
        assignmentNote:
            source.assignment_note ||
            source.assignmentNote ||
            'Ringkasan lembur akan muncul saat ada transaksi yang tercatat.',
        monthlyTrend: hasData ? source.monthly_trend || source.monthlyTrend || [] : [],
    };
});

// Trend lembur 2-cutoff (titik per minggu) = FIXED, dimuat on-demand saat card
// dibuka; TIDAK ikut berganti saat cutoff picker diubah.
const overtimeTrend = computed(() =>
    Array.isArray(overtimeDetailState.value.trend) ? overtimeDetailState.value.trend : [],
);

// Label cutoff yang sedang dipilih (untuk badge pojok mobile — desktop pakai select).
const overtimeSelectedCutoffLabel = computed(() => {
    const match = (cutoffPeriodOptions.value || []).find((opt) => opt.value === selectedOvertimeCutoff.value);
    return match?.label || overtimeSummary.value.periodLabel || 'Periode berjalan';
});

const overtimeAssignments = computed(() =>
    (overtimeAssignmentState.value.items || []).map((item, index) => normalizeOvertimeAssignment(item, index)),
);

// Kelompokkan transaksi lembur per TANGGAL KERJA. Header grup memuat agregat HARI
// (total bersih + jam sah), sedangkan tiap item memuat durasi MURNI per jenis.
// Ini memisahkan level-hari vs level-transaksi sehingga tidak ada double-counting.
//
// SELURUH item periode dikelompokkan di sini, tidak lagi dipotong 5 di depan.
// Pemotongan lama membuang sisanya tanpa jejak: kartu menulis "N item terbaru"
// dan tidak ada cara apa pun melihat item berikutnya.
const overtimeAssignmentGroupsAll = computed(() => {
    const groups = new Map();
    for (const item of overtimeAssignments.value) {
        if (!groups.has(item.dateKey)) {
            groups.set(item.dateKey, {
                dateKey: item.dateKey,
                dateLabel: item.dateLabel,
                dayGrossLabel: item.dayGrossLabel,
                dayNetLabel: item.dayNetLabel,
                dayKoefisienLabel: item.dayKoefisienLabel,
                dayKoefisienChipLabel: item.dayKoefisienChipLabel,
                dayCalc: item.dayCalc,
                dayGrossMinutes: item.dayGrossMinutes,
                dayNetMinutes: item.dayNetMinutes,
                dayShiftMinutes: item.dayShiftMinutes,
                dayShiftLabel: item.dayShiftLabel,
                dayRealLateMinutes: item.dayRealLateMinutes,
                hasShift: item.hasShift,
                items: [],
            });
        }
        groups.get(item.dateKey).items.push(item);
    }
    return Array.from(groups.values());
});

// Paginasi dilakukan di BROWSER, dan satuannya TANGGAL — bukan item.
//
// Memotong per item akan membelah satu tanggal ke dua halaman, sehingga
// agregat harian di kepala grup (total bersih, jam sah) berdiri di atas
// sebagian datanya sendiri dan angkanya berbohong.
//
// Tidak dipaginasi di server karena servernya tidak bisa ikut bekerja lebih
// sedikit: SP absensi bekerja per RENTANG TANGGAL dan komponen lembur tetap
// dipekarkan dari seluruh hari. Paginasi server hanya akan memangkas JSON,
// dengan bayaran satu perjalanan penuh (±600 ms) tiap pindah halaman.
const OVERTIME_GROUPS_PER_PAGE = 5;
const overtimeAssignmentPage = ref(1);

const overtimeAssignmentPageCount = computed(() =>
    Math.max(1, Math.ceil(overtimeAssignmentGroupsAll.value.length / OVERTIME_GROUPS_PER_PAGE)),
);

// Halaman dijaga tetap sah: mengganti periode atau memuat ulang bisa membuat
// daftarnya menyusut sementara nomor halaman tertinggal di angka lama.
watch([overtimeAssignmentPageCount, () => selectedOvertimeCutoff.value], () => {
    if (overtimeAssignmentPage.value > overtimeAssignmentPageCount.value) {
        overtimeAssignmentPage.value = 1;
    }
});

const overtimeAssignmentsByDate = computed(() => {
    const mulai = (overtimeAssignmentPage.value - 1) * OVERTIME_GROUPS_PER_PAGE;
    return overtimeAssignmentGroupsAll.value.slice(mulai, mulai + OVERTIME_GROUPS_PER_PAGE);
});

/** "Tanggal 1–5 dari 12" — menyebut yang ditampilkan DAN yang tidak. */
const overtimeAssignmentRangeLabel = computed(() => {
    const total = overtimeAssignmentGroupsAll.value.length;
    if (!total) return '';

    const mulai = (overtimeAssignmentPage.value - 1) * OVERTIME_GROUPS_PER_PAGE + 1;
    const akhir = Math.min(mulai + OVERTIME_GROUPS_PER_PAGE - 1, total);

    return total <= OVERTIME_GROUPS_PER_PAGE
        ? `${total} tanggal`
        : `Tanggal ${mulai}–${akhir} dari ${total}`;
});

function gantiHalamanLembur(arah) {
    const berikut = overtimeAssignmentPage.value + arah;
    if (berikut < 1 || berikut > overtimeAssignmentPageCount.value) return;
    overtimeAssignmentPage.value = berikut;
}

const overtimeAssignmentSummaryLabel = computed(() => {
    const count = overtimeAssignments.value.length;
    if (!count) return 'Belum ada penugasan aktif';

    const activeCount = overtimeAssignments.value.filter((item) => item.statusKey === 'process').length;
    if (activeCount) return `${activeCount} penugasan masih proses`;

    return `${count} penugasan terakhir`;
});

const leaveSummary = computed(() => {
    const source = dashboard.value.leave || props.moduleSummary?.leave || {};

    return {
        quota: source.quota ?? source.total_quota ?? 0,
        remaining: source.remaining ?? source.remaining_quota ?? 0,
        used: source.used ?? source.used_quota ?? 0,
        periodLabel: source.period_label || source.periodLabel || 'Tahun berjalan',
    };
});

// Pengajuan Saya: paginasi BACKEND. dashboard.submissions hanya berisi item
// HALAMAN AKTIF (4 terbaru di awal); halaman lain diambil on-demand dari server
// supaya interaksi dengan AllTransactionsReadService per load tetap kecil.
const personalSubmissions = computed(() => {
    const items = dashboard.value.submissions || [];
    return Array.isArray(items) ? items : [];
});

// Item halaman aktif yang ditampilkan = langsung dari server (sudah diurutkan
// terbaru duluan di backend, di-page dengan offset/limit).
const paginatedSubmissions = computed(() => personalSubmissions.value);

const submissionsPage = ref(1);
const submissionsPagination = ref({ current_page: 1, last_page: 1, total: 0, has_prev: false, has_next: false });
const submissionsTotalPages = computed(() => Math.max(1, Number(submissionsPagination.value.last_page || 1)));

function loadSubmissionsPage(page = 1) {
    const target = Math.max(1, Number(page) || 1);
    return loadDomain(
        'submissions',
        '/homepage/personal/submissions-summary',
        (data, raw) => {
            patchDashboard({ submissions: Array.isArray(data) ? data : [] });
            const meta = raw?.pagination || null;
            if (meta) {
                submissionsPagination.value = meta;
                submissionsPage.value = Number(meta.current_page || target);
            } else {
                submissionsPage.value = target;
            }
        },
        { params: { page: target }, skipCache: true },
    );
}

function goToSubmissionsPage(page) {
    const target = Math.min(Math.max(1, page), submissionsTotalPages.value);
    if (target === submissionsPage.value) return;
    void loadSubmissionsPage(target);
}

const actionCenterTotalPages = computed(() => Math.max(1, Number(actionCenterPagination.value.last_page || 1)));

// Action Center: paginasi BACKEND, 5 item/halaman. Deteksi notif hanya jalan di
// server saat page 1 (initial load); ganti halaman cuma membaca data supaya
// tarik data ringan. skipCache agar item selalu fresh setelah dibaca/diklik.
function loadNeedsActionPage(page = 1) {
    const target = Math.max(1, Number(page) || 1);
    const kpiYear = resolveDashboardYear('kpi');
    return loadDomain(
        'needsAction',
        '/homepage/personal/needs-action',
        (data, raw) => {
            needsActionError.value = '';
            personalActionItems.value = Array.isArray(raw?.data) ? raw.data : Array.isArray(data) ? data : [];
            const meta = raw?.pagination || null;
            if (meta) {
                actionCenterPagination.value = meta;
                actionCenterPage.value = Number(meta.current_page || target);
            } else {
                actionCenterPage.value = target;
            }
            needsActionSummary.value = raw?.summary || null;
        },
        { params: { ...paramsWithYear(kpiYear, 'kpi_year'), page: target }, skipCache: true },
    );
}

function goToActionCenterPage(page) {
    const target = Math.min(Math.max(1, page), actionCenterTotalPages.value);
    if (target === actionCenterPage.value) return;
    actionCenterPageLoading.value = true;
    void loadNeedsActionPage(target).finally(() => {
        actionCenterPageLoading.value = false;
    });
}

const navigationModules = computed(() => page.props?.layout?.navigation?.modules || []);

const quickActionGroups = computed(() => {
    return navigationModules.value
        .flatMap((module, moduleIndex) =>
            (module.groups || []).map((group, groupIndex) => {
                const items = (group.items || [])
                    .map((item, itemIndex) => ({
                        ...item,
                        key: `${module.id || module.code}-${group.id || groupIndex}-${item.id || item.url || itemIndex}`,
                        module,
                        moduleLabel: module.label || module.name || module.code || 'Menu',
                        groupTitle: group.title || module.label || module.name || 'Menu',
                        order: moduleIndex * 100 + groupIndex * 10 + itemIndex,
                    }))
                    .filter((item) => isQuickActionVisible(item))
                    .sort((a, b) => a.order - b.order);

                if (!items.length) {
                    return null;
                }

                return {
                    key: `${module.id || module.code || moduleIndex}-${group.id || groupIndex}`,
                    label: group.title || module.label || module.name || 'Menu',
                    moduleLabel: module.label || module.name || module.code || 'Menu',
                    icon: module.icon || items[0]?.icon || 'bi bi-grid',
                    order: moduleIndex * 100 + groupIndex,
                    items,
                };
            }),
        )
        .filter(Boolean)
        .sort((a, b) => a.order - b.order);
});

const quickActionCount = computed(() => quickActionGroups.value.length);

const isKpiNeedsActionItem = (item) => {
    const id = String(item?.id || '').toLowerCase();
    const title = String(item?.title || '').toLowerCase();
    const category = String(item?.category || item?.module || '').toLowerCase();

    if (!category.includes('kpi') && !id.startsWith('kpi-')) {
        return true;
    }

    if (id.includes('parameter') || title.includes('parameter')) {
        return heroKpi.value.parameterScheduled && !heroKpi.value.parameterSubmitted;
    }

    if (id.includes('performance') || title.includes('performance')) {
        return heroKpi.value.performanceScheduled && !heroKpi.value.performanceSubmitted;
    }

    return true;
};

function compareNeedsActionPriority(a, b) {
    // Item yang sudah dibaca diturunkan ke bawah; di dalam tiap grup tetap
    // mengikuti prioritas & urutan notification_id seperti sebelumnya.
    const readCompare = Number(Boolean(a?.is_read)) - Number(Boolean(b?.is_read));
    if (readCompare !== 0) {
        return readCompare;
    }

    const priorityCompare = Number(a?.priority ?? 3) - Number(b?.priority ?? 3);
    if (priorityCompare !== 0) {
        return priorityCompare;
    }

    return Number(b?.notification_id ?? 0) - Number(a?.notification_id ?? 0);
}

const effectivePersonalActionItems = computed(() =>
    personalActionItems.value.filter(isKpiNeedsActionItem).slice().sort(compareNeedsActionPriority),
);

const pendingItems = computed(() => {
    if (personalActionItems.value.length || isNeedsActionLoading.value || needsActionError.value) {
        return effectivePersonalActionItems.value;
    }

    const items = [];

    if (todayAwareness.value.actionType === 'late-checkin') {
        items.push({
            id: 'hcis-late-checkin',
            module: 'HCIS',
            tone: 'danger',
            icon: 'bi bi-box-arrow-in-right',
            title: 'Check-in belum tercatat',
            description: 'Jam masuk sudah lewat, tapi check-in belum ada.',
            actionLabel: 'Cek tanggal',
            date: todayRecord.value?.date,
        });
    }

    if (todayAwareness.value.actionType === 'missing-checkout') {
        items.push({
            id: 'hcis-missing-checkout',
            module: 'HCIS',
            tone: 'warning',
            icon: 'bi bi-box-arrow-right',
            title: 'Check-out belum tercatat',
            description: 'Jam pulang sudah lewat, tapi check-out belum ada.',
            actionLabel: 'Cek tanggal',
            date: todayRecord.value?.date,
        });
    }

    cutoffDays.value
        .filter((day) => day.hasProblem && !day.isToday)
        .slice(0, 4)
        .forEach((day) => {
            items.push({
                id: `hcis-${day.date}`,
                module: 'HCIS',
                tone: day.primaryStatusTone,
                icon: statusVisual(day.primaryStatusKey).icon,
                title: `${day.primaryStatusLabel} pada ${formatShortDate(day.rawDate)}`,
                description: buildFollowUpDescription(day),
                actionLabel: 'Cek tanggal',
                date: day.date,
            });
        });

    // Warning KPI (parameter & update performance) langsung dari backend pending_items
    // yang sudah akurat. Tampilkan hanya yang bertone warning di action center.
    heroKpi.value.warningItems
        .filter((warning) => warning.key === 'parameter' || warning.key === 'performance')
        .filter((warning) => warning.tone === 'is-warning')
        .forEach((warning) => {
            items.push({
                id: `kpi-${warning.key}`,
                module: 'KPI',
                tone: 'warning',
                icon: warning.icon,
                title: warning.title || warning.text,
                description: warning.title ? warning.text : `Periode KPI: ${heroKpi.value.periodLabel}`,
                actionLabel: 'Perhatian',
            });
        });

    if (heroLms.value.mandatoryPending > 0) {
        items.push({
            id: 'lms-mandatory',
            module: 'LMS',
            tone: 'info',
            icon: 'bi bi-journal-bookmark',
            title: `${heroLms.value.mandatoryPending} training wajib belum selesai`,
            description: `Training berikutnya: ${heroLms.value.nextSession}.`,
            actionLabel: 'Perhatian',
        });
    }

    if (overtimeSummary.value.activeAssignments > 0) {
        items.push({
            id: 'overtime-assignment',
            module: 'Lembur',
            tone: 'orange',
            icon: 'bi bi-clock-history',
            title: `${overtimeSummary.value.activeAssignments} assignment lembur aktif`,
            description: overtimeSummary.value.nextAssignmentLabel,
            actionLabel: 'Lihat tren',
            scrollToOvertime: true,
        });
    }

    personalSubmissions.value
        .filter((item) => ['pending', 'rejected', 'expired'].includes(item.status))
        .slice(0, 2)
        .forEach((submission) => {
            items.push({
                id: `submission-${submission.id}`,
                module: 'Pengajuan',
                tone: ['rejected', 'expired'].includes(submission.status) ? 'danger' : 'warning',
                icon: 'bi bi-file-earmark-text',
                title: `${submission.title} ${submission.statusLabel}`,
                description: `${submission.date} • ${submission.description}`,
                actionLabel: 'Status',
                href: submission.href,
                action_url: submission.href,
            });
        });

    return items.slice(0, 12);
});

const teamDashboardBadgeCount = computed(() => (isLeader.value ? Number(teamDashboardBadge.value.count || 0) : 0));
const teamDashboardBadgeLabel = computed(() => String(teamDashboardBadge.value.label || ''));
const teamDashboardBadgeVisible = computed(() => teamDashboardBadgeCount.value > 0);

const awarenessCards = computed(() => [
    {
        key: 'attendance',
        label: 'Absensi Issue',
        value: isDashboardBootstrapping.value || isDashboardHydrating.value ? '...' : attendanceIssueCount.value,
        description: 'Tanggal perlu dicek',
        icon: 'bi bi-fingerprint',
        tone: 'danger',
        loading: isDashboardBootstrapping.value || isAttendanceLoading.value || isDashboardHydrating.value,
    },
    {
        key: 'attendance-timing',
        label: 'Telat / Pulang Cepat',
        value:
            isDashboardBootstrapping.value || isDashboardHydrating.value
                ? '...'
                : `${totalLateMinutes.value}m / ${totalEarlyOutMinutes.value}m`,
        description: 'Total menit cutoff',
        icon: 'bi bi-stopwatch',
        tone: 'orange',
        loading: isDashboardBootstrapping.value || isAttendanceLoading.value || isDashboardHydrating.value,
    },
    {
        key: 'overtime',
        label: 'Total Lembur',
        value:
            isDashboardBootstrapping.value || isDashboardHydrating.value
                ? '...'
                : `${overtimeSummary.value.totalHours}h`,
        description: overtimeSummary.value.periodLabel,
        icon: 'bi bi-clock-history',
        tone: 'orange',
        loading: isDashboardBootstrapping.value || isOvertimeLoading.value || isDashboardHydrating.value,
    },
    {
        key: 'leave',
        label: 'Jatah Cuti',
        value: isDashboardBootstrapping.value || isDashboardHydrating.value ? '...' : `${leaveSummary.value.remaining}`,
        // description: `${leaveSummary.value.used} terpakai`,
        icon: 'bi bi-airplane',
        tone: 'info',
        loading: isDashboardBootstrapping.value || isLeaveLoading.value || isDashboardHydrating.value,
    },

    {
        key: 'approval',
        label: isLeader.value ? 'Approval' : 'Assignment',
        value:
            isDashboardBootstrapping.value || isDashboardHydrating.value
                ? '...'
                : isLeader.value
                  ? pendingApprovalsResolved.value
                  : overtimeSummary.value.activeAssignments,
        description: isLeader.value ? 'Menunggu tindakan' : 'Lembur aktif',
        icon: isLeader.value ? 'bi bi-check2-square' : 'bi bi-person-check',
        tone: 'success',
        loading:
            isDashboardBootstrapping.value ||
            isDashboardHydrating.value ||
            (!isLeader.value && isOvertimeLoading.value),
    },
]);

const upcomingResolved = computed(() => {
    return Array.isArray(dashboard.value.upcomingSchedule) ? dashboard.value.upcomingSchedule.slice(0, 7) : [];
});

const mobileAwarenessCards = computed(() => {
    const preferredKeys = ['attendance', 'attendance-timing', 'leave'];

    return preferredKeys.map((key) => awarenessCards.value.find((item) => item.key === key)).filter(Boolean);
});

const compactPendingItems = computed(() => pendingItems.value.slice(0, 5));
// Hitungan Action Center = item yang BELUM dibaca. Item read tetap tampil
// (abu-abu) tapi tidak dihitung di badge/chip.
const unreadPendingCount = computed(() => {
    // Saat data berasal dari server (ada item + summary), pakai total belum-dibaca
    // LINTAS SEMUA HALAMAN dari backend supaya badge tetap akurat walau daftar
    // hanya menampilkan 5/halaman. Fallback ke hitung lokal saat memakai item
    // turunan client (belum ada data server).
    if (personalActionItems.value.length && needsActionSummary.value) {
        return Number(needsActionSummary.value.unread_active_items || 0);
    }
    return pendingItems.value.filter((item) => !item.is_read).length;
});
const compactUpcoming = computed(() => upcomingResolved.value.slice(0, 4));

const mobileHeroStatus = computed(() => {
    if (isDashboardHydrating.value) {
        return {
            label: 'Memuat',
            tone: 'info',
            icon: 'bi bi-hourglass-split',
        };
    }

    return statusVisual(todayRecord.value?.primaryStatusKey || 'future');
});

const mobileHeroDateLabel = computed(() => {
    const referenceDate = todayRecord.value?.rawDate || today;

    return referenceDate.toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'short',
    });
});

async function goToCurrentWeek() {
    if (isMobileWeekNavBusy.value) return;

    mobileWeekNavigationLocked.value = true;

    try {
        mobileWeekOffset.value = 0;
        await loadMobileWeekCalendar();
    } finally {
        const unlock = () => {
            mobileWeekNavigationLocked.value = false;
        };

        if (typeof window === 'undefined') {
            unlock();
        } else {
            window.setTimeout(unlock, 180);
        }
    }
}

async function shiftMobileWeek(direction) {
    const delta = Number(direction || 0);
    if (!delta || isMobileWeekNavBusy.value) return;

    mobileWeekNavigationLocked.value = true;

    try {
        mobileWeekOffset.value += delta;
        await loadMobileWeekCalendar();
    } finally {
        const unlock = () => {
            mobileWeekNavigationLocked.value = false;
        };

        if (typeof window === 'undefined') {
            unlock();
        } else {
            window.setTimeout(unlock, 180);
        }
    }
}

const mobileWeekStartDate = computed(() => {
    const start = new Date(today);
    start.setHours(0, 0, 0, 0);
    start.setDate(start.getDate() - start.getDay() + mobileWeekOffset.value * 7);

    return start;
});

const mobileWeekDays = computed(() => {
    const days = [];
    const cursor = new Date(mobileWeekStartDate.value);

    for (let index = 0; index < 7; index += 1) {
        days.push(buildCalendarDay(cursor, mobileWeekRecordsMap.value));
        cursor.setDate(cursor.getDate() + 1);
    }

    return days;
});

const mobileCalendarMonthLabel = computed(() => {
    const start = mobileWeekDays.value[0]?.rawDate || mobileWeekStartDate.value;
    const end = mobileWeekDays.value[6]?.rawDate || start;

    if (!start) return '-';

    // if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
    //     return `${start.getDate()} - ${end.toLocaleDateString('id-ID', {
    //         day: 'numeric',
    //         month: 'long',
    //         year: 'numeric',
    //     })}`;
    // }

    // return `${start.toLocaleDateString('id-ID', {
    //     day: 'numeric',
    //     month: 'short',
    // })} - ${end.toLocaleDateString('id-ID', {
    //     day: 'numeric',
    //     month: 'short',
    //     year: 'numeric',
    // })}`;

    // Ubah jadi Hanya Tampilkan bulan dan tahun
    return `${start.toLocaleDateString('id-ID', {
        month: 'long',
        year: 'numeric',
    })}`;
});

const kpiTrendSeries = computed(() => [
    {
        name: 'Skor KPI',
        data: heroKpi.value.trend.map((item) => item.score),
    },
]);

const kpiTrendBounds = computed(() => {
    const scores = heroKpi.value.trend.map((item) => Number(item.score)).filter((score) => Number.isFinite(score));

    if (!scores.length) {
        return { min: 0, max: 100 };
    }

    const minScore = Math.min(...scores);
    const maxScore = Math.max(...scores);
    const padding = Math.max(4, Math.round((maxScore - minScore) * 0.2));

    return {
        min: Math.max(0, minScore - padding),
        max: maxScore + padding,
    };
});

const kpiTrendOptions = computed(() => ({
    chart: {
        type: 'area',
        toolbar: { show: false },
        zoom: { enabled: false },
        sparkline: { enabled: false },
        fontFamily: 'inherit',
    },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2.6 },
    fill: {
        type: 'gradient',
        gradient: { opacityFrom: 0.18, opacityTo: 0 },
    },
    grid: {
        show: false,
        borderColor: '#e2e8f0',
        strokeDashArray: 0,
        padding: { top: 8, right: 0, bottom: 2, left: 0 },
    },
    xaxis: {
        categories: heroKpi.value.trend.map((item) => item.month || '-'),
        labels: { show: true, style: { fontSize: '10px', fontWeight: 700 } },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        min: kpiTrendBounds.value.min,
        max: kpiTrendBounds.value.max,
        labels: { show: false },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    tooltip: {
        theme: 'dark',
        x: {
            formatter: (_, opts) => heroKpi.value.trend[opts.dataPointIndex]?.month || '',
        },
    },
}));

const hcisTrendChartSeries = computed(() => [
    {
        name: 'Kehadiran',
        data: hcisPersonal.value.trend.map((item) => Number(item.percentage ?? item.value ?? 0)),
    },
]);

const hcisTrendChartOptions = computed(() => ({
    chart: {
        type: 'area',
        toolbar: { show: false },
        zoom: { enabled: false },
        sparkline: { enabled: false },
        fontFamily: 'inherit',
        events: {
            markerClick: (_, __, config) => {
                handleHcisTrendPointClick(config?.dataPointIndex);
            },
            dataPointSelection: (_, __, config) => {
                handleHcisTrendPointClick(config?.dataPointIndex);
            },
            dataPointMouseEnter: (_, __, config) => {
                hcisTrendHoverIndex.value = Number.isInteger(config?.dataPointIndex) ? config.dataPointIndex : null;
            },
            mouseLeave: () => {
                hcisTrendHoverIndex.value = null;
            },
            click: (_, __, config) => {
                const clickedIndex = Number.isInteger(config?.dataPointIndex) ? config.dataPointIndex : null;
                handleHcisTrendPointClick(clickedIndex ?? hcisTrendHoverIndex.value);
            },
        },
    },
    colors: ['#22c55e'],
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2.4 },
    fill: {
        type: 'gradient',
        gradient: { opacityFrom: 0.28, opacityTo: 0.02 },
    },
    grid: {
        borderColor: '#e2e8f0',
        strokeDashArray: 4,
    },
    xaxis: {
        categories: hcisPersonal.value.trend.map((item) => item.label),
        labels: { style: { colors: '#64748b', fontSize: '10px' } },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        min: 0,
        max: 100,
        labels: {
            show: true,
            style: { colors: '#64748b', fontSize: '10px' },
            formatter: (value) => `${Math.round(Number(value || 0))}%`,
        },
    },
    markers: {
        size: 4,
        strokeWidth: 2,
        hover: { size: 6 },
    },
    tooltip: {
        theme: 'dark',
        shared: true,
        intersect: false,
        custom: ({ dataPointIndex }) => buildHcisTrendTooltip(dataPointIndex),
    },
}));

const lmsDurationChartSeries = computed(() => [
    {
        name: 'Avg Durasi',
        data: heroLms.value.durationMonthly.map((item) => Number(item.avg_hours ?? item.avgHours ?? 0)),
    },
]);

const lmsDurationChartOptions = computed(() => ({
    chart: {
        type: 'area',
        toolbar: { show: false },
        zoom: { enabled: false },
        fontFamily: 'inherit',
    },
    stroke: { curve: 'smooth', width: 3 },
    fill: {
        type: 'gradient',
        gradient: { shadeIntensity: 0.4, opacityFrom: 0.32, opacityTo: 0.02, stops: [0, 90, 100] },
    },
    colors: ['#0f766e'],
    dataLabels: { enabled: false },
    grid: {
        show: false,
        strokeDashArray: 0,
        padding: { left: 0, right: 0, top: 0, bottom: 0 },
    },
    xaxis: {
        categories: heroLms.value.durationMonthly.map((item) => item.month || item.period || '-'),
        labels: { style: { fontSize: '10px', fontWeight: 700 } },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        labels: { show: false },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    tooltip: {
        theme: 'dark',
        y: { formatter: (value) => formatLearningDurationLabel(value) },
    },
}));

const overtimeChartSeries = computed(() => [
    {
        name: 'Sebelum Kerja',
        data: overtimeTrend.value.map((item) => item.before ?? 0),
    },
    {
        name: 'Setelah Kerja',
        data: overtimeTrend.value.map((item) => item.after ?? 0),
    },
    {
        name: 'Lembur Shift',
        data: overtimeTrend.value.map((item) => item.shift ?? 0),
    },
    {
        name: 'Hari Libur',
        data: overtimeTrend.value.map((item) => item.holiday ?? 0),
    },
]);

const overtimeChartOptions = computed(() => ({
    chart: {
        type: 'line',
        toolbar: { show: false },
        zoom: { enabled: false },
        fontFamily: 'inherit',
    },
    // Urutan warna mengikuti urutan seri: Sebelum, Setelah, Lembur Shift, Hari Libur
    colors: ['#3b82f6', '#16a34a', '#8b5cf6', '#f59e0b'],
    stroke: { curve: 'smooth', width: 2 },
    markers: { size: 3, strokeWidth: 2 },
    grid: {
        strokeDashArray: 4,
        padding: { left: 0, right: 8, top: 0, bottom: 0 },
    },
    xaxis: {
        categories: overtimeTrend.value.map((item) => item.month),
        labels: { style: { fontSize: '10px', fontWeight: 700 } },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        labels: { style: { fontSize: '10px', fontWeight: 700 } },
    },
    legend: {
        position: 'top',
        horizontalAlign: 'left',
        fontSize: '11px',
        fontWeight: 700,
        markers: { width: 8, height: 8 },
    },
    tooltip: {
        theme: 'dark',
        custom: ({ dataPointIndex }) => buildOvertimeTrendTooltip(dataPointIndex),
    },
}));

function buildOvertimeTrendTooltip(dataPointIndex) {
    const pointIndex = Number(dataPointIndex);
    const trend = overtimeTrend.value || [];
    const point = trend[Number.isInteger(pointIndex) && pointIndex >= 0 ? pointIndex : 0] || {};

    const month = escapeTooltipText(point.month || '-');
    const total = Number(point.total ?? 0);
    const before = Number(point.before ?? 0);
    const after = Number(point.after ?? 0);
    const shift = Number(point.shift ?? 0);
    const holiday = Number(point.holiday ?? 0);
    const assignments = Number(point.assignment_days ?? 0);

    const rows = [
        { label: 'Total Lembur', value: `${total} jam`, tone: 'sah' },
        { label: 'Lembur Shift', value: `${shift} jam`, tone: 'base' },
        { label: 'Sebelum Kerja', value: `${before} jam`, tone: 'muted' },
        { label: 'Setelah Kerja', value: `${after} jam`, tone: 'muted' },
        { label: 'Hari Libur', value: `${holiday} jam`, tone: 'muted' },
        { label: 'Assignment', value: `${assignments} hari`, tone: 'muted' },
    ];

    const rowsHtml = rows
        .map(
            (row) =>
                `<span class="hcis-trend-tooltip__row is-${row.tone}"><span>${row.label}</span> <strong>${row.value}</strong></span>`,
        )
        .join('');

    return `
        <div class="hcis-trend-tooltip">
            <span class="hcis-trend-tooltip__title">${month}</span>
            ${rowsHtml}
        </div>
    `;
}

function normalizeQuickActionUrl(url) {
    return String(url || '')
        .trim()
        .replace(/^https?:\/\/[^/]+/i, '')
        .replace(/\/+$/, '');
}

function isQuickActionVisible(item) {
    const url = normalizeQuickActionUrl(item?.url);

    return Boolean(url) && url !== '/homepage';
}

function quickActionIcon(item, module) {
    return item?.icon || module?.icon || item?.module?.icon || 'bi bi-grid';
}

function openQuickActionGroup(group) {
    if (!group?.items?.length) {
        return;
    }

    quickActionModalTrigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    activeQuickActionGroup.value = group;
    nextTick(() => quickActionModalRef.value?.focus?.());
}

function closeQuickActionGroup() {
    activeQuickActionGroup.value = null;
    nextTick(() => {
        quickActionModalTrigger?.focus?.();
        quickActionModalTrigger = null;
    });
}

function handleQuickAction(item) {
    closeQuickActionGroup();
    visit(item?.url);
}

function handleAwarenessClick(key) {
    if (isMobileViewport.value) {
        if (key === 'attendance' || key === 'attendance-timing') {
            activeCalendarFilter.value = 'problem';
            if (key === 'attendance-timing') activeCalendarFilter.value = 'late';
            scrollToMobileSection('absensi');
            return;
        }

        if (key === 'overtime' || key === 'approval' || key === 'submission') {
            scrollToMobileSection('more');
            return;
        }

        if (key === 'kpi' || key === 'lms') {
            mobilePanelState.value = { ...mobilePanelState.value, [key]: true };
            scrollToMobileSection(key);
            return;
        }
    }

    if (key === 'attendance') {
        // Pilih filter "Bermasalah" lalu arahkan ke card Kalender Cutoff.
        activeCalendarFilter.value = 'problem';
        scrollToAttendanceCalendar();
        return;
    }

    if (key === 'attendance-timing') {
        activeCalendarFilter.value = 'late';
        scrollToAttendanceCalendar();
        return;
    }

    if (key === 'overtime') {
        scrollToOvertime();
        return;
    }

    if (key === 'kpi' || key === 'lms') {
        document.querySelector(`[data-tour="module-${key}"]`)?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
        return;
    }

    if (key === 'leave') {
        router.visit('/cuti');
        return;
    }

    if (key === 'approval' || key === 'submission') {
        // document.querySelector('[data-tour="leader-approvals"]')?.scrollIntoView({
        //     behavior: 'smooth',
        //     block: 'start',
        // });
        router.visit('/homepage/leader-dashboard');
    }
}

function handleVisit(url) {
    if (url) {
        router.visit(url);
    }
}

function toggleMobilePanel(key) {
    mobilePanelState.value = {
        ...mobilePanelState.value,
        [key]: !mobilePanelState.value[key],
    };
    // Pemicu load by-click (kpi/lms/submissions) ditangani terpusat oleh watcher
    // pada mobilePanelState -> lihat watch(...) di dekat revealSubmissions.
}

async function prepareHomepageTourStep(stepKey) {
    if (!isMobileViewport.value) {
        await nextTick();
        return 0;
    }

    const panelByStep = {
        'module-hcis': 'hcis',
        'module-kpi': 'kpi',
        'module-lms': 'lms',
        'module-overtime': 'overtime',
        'leader-approvals': 'action',
        'attendance-composition': 'attendance',
        submissions: 'submissions',
    };

    const sectionByStep = {
        hero: 'home',
        awareness: 'home',
        calendar: 'home',
        'quick-actions': 'home',
        'attendance-composition': 'home',
        'leader-approvals': 'home',
        'module-hcis': 'hcis',
        'module-kpi': 'kpi',
        'module-lms': 'lms',
        'module-overtime': 'more',
        submissions: 'more',
    };

    const nextPanel = panelByStep[stepKey];
    const sectionKey = sectionByStep[stepKey];
    if (!nextPanel && !sectionKey) {
        return 0;
    }

    if (nextPanel) {
        mobilePanelState.value = {
            ...mobilePanelState.value,
            [nextPanel]: true,
        };
    }

    if (sectionKey) {
        activeMobileTab.value = sectionKey;
    }

    await nextTick();
    return nextPanel ? 340 : 80;
}

function setMobileSectionRef(key, element) {
    if (element) {
        mobileSectionRefs.value[key] = element;
        return;
    }

    delete mobileSectionRefs.value[key];
}

function updateViewportState() {
    if (typeof window === 'undefined') return;
    isMobileViewport.value = window.innerWidth <= mobileBreakpoint;
}

function syncActiveMobileTab() {
    if (!isMobileViewport.value || typeof window === 'undefined') return;

    const threshold = 160;
    let currentKey = mobileTabs[0]?.key || 'home';

    mobileTabs.forEach((tab) => {
        const element = mobileSectionRefs.value[tab.key];

        if (!element) return;

        if (element.getBoundingClientRect().top - threshold <= 0) {
            currentKey = tab.key;
        }
    });

    activeMobileTab.value = currentKey;
}

// Throttle handler scroll: syncActiveMobileTab membaca getBoundingClientRect() untuk
// tiap tab (forced reflow). Tanpa throttle ini jalan tiap frame scroll -> jank di
// HP. rAF ticking guard membatasi ke <=1x per frame.
let mobileTabTicking = false;
function onScrollSyncMobileTab() {
    if (mobileTabTicking) return;
    mobileTabTicking = true;
    window.requestAnimationFrame(() => {
        syncActiveMobileTab();
        mobileTabTicking = false;
    });
}

function scrollToMobileSection(key) {
    activeMobileTab.value = key;

    if (key === 'hcis') {
        mobilePanelState.value.hcis = true;
    } else if (key === 'kpi') {
        mobilePanelState.value.kpi = true;
    } else if (key === 'lms') {
        mobilePanelState.value.lms = true;
    } else if (key === 'absensi') {
        mobilePanelState.value.kalenderCutoff = true;
    } else if (key === 'more') {
        mobilePanelState.value.overtime = true;
        mobilePanelState.value.submissions = true;
        mobilePanelState.value.upcoming = true;
    }

    if (typeof window === 'undefined') return;

    const element = mobileSectionRefs.value[key];

    if (!element) return;

    const offset = key === 'home' ? 88 : 136;
    const targetTop = element.getBoundingClientRect().top + window.scrollY - offset;

    window.scrollTo({
        top: Math.max(targetTop, 0),
        behavior: 'smooth',
    });
}

let resizeDebounceTimer = null;
function handleWindowResize() {
    if (resizeDebounceTimer) window.clearTimeout(resizeDebounceTimer);
    resizeDebounceTimer = window.setTimeout(() => {
        updateViewportState();
        syncActiveMobileTab();
        resizeDebounceTimer = null;
    }, 150);
}

// Card "Upcoming / Jadwal Berikutnya": klik → menuju halaman Self Shift.
function openSelfShift() {
    router.visit('/self-shift');
}

function handleSubmissionClick(item) {
    if (item.href) {
        router.visit(item.href);
        return;
    }

    if (item.title === 'SHIFT') {
        if (item.itemDate) {
            const day = cutoffDays.value.find((d) => d.date === item.itemDate);
            if (day) {
                openDayModal(day);
                return;
            }
        }
    }
}

function handlePendingItem(item) {
    // Klik = tandai sudah dibaca secara optimistic; rollback bila backend gagal.
    markPendingItemRead(item);

    if (item.scrollToOvertime) {
        scrollToOvertime();
        return;
    }

    const actionUrl = item.action_url || item.actionUrl || item.href;
    if (actionUrl && actionUrl !== '/homepage') {
        router.visit(actionUrl);
        return;
    }

    if (item.date) {
        const day = cutoffDays.value.find((d) => d.date === item.date);

        if (day) {
            openDayModal(day);
        }
    }
}

function isNotificationDismissBusy(item) {
    const key = item?.notification_id || item?.notification_key || item?.id;
    return Boolean(key && notificationDismissBusy.value[key]);
}

// Tandai item Action Center sudah dibaca (abu-abu) tanpa menghapusnya. Item
// tetap tampil sampai keluar window cutoff. Idempotent: kalau sudah read, skip.
async function markPendingItemRead(item) {
    const notificationId = item?.notification_id;
    const busyKey = notificationId || item?.notification_key || item?.id;

    if (!notificationId || !busyKey || item.is_read || notificationDismissBusy.value[busyKey]) {
        return;
    }

    const previousItems = personalActionItems.value;
    const previousError = needsActionError.value;
    const previousSummary = needsActionSummary.value;

    // Optimistic: langsung abu-abu di UI supaya responsif.
    setPendingItemRead(item);
    needsActionError.value = '';

    notificationDismissBusy.value = {
        ...notificationDismissBusy.value,
        [busyKey]: true,
    };

    try {
        await axios.post(`/homepage/notifications/${encodeURIComponent(notificationId)}/read`);
    } catch (error) {
        console.error('Failed to mark action notification as read', error);
        personalActionItems.value = previousItems;
        needsActionSummary.value = previousSummary;
        needsActionError.value = 'Status dibaca gagal disimpan. Silakan coba lagi.';
    } finally {
        const next = { ...notificationDismissBusy.value };
        delete next[busyKey];
        notificationDismissBusy.value = next;
        if (!needsActionError.value) {
            needsActionError.value = previousError;
        }
    }
}

function setPendingItemRead(item) {
    let markedUnreadToRead = false;
    personalActionItems.value = personalActionItems.value.map((candidate) => {
        const matches =
            (item.notification_id && candidate.notification_id === item.notification_id) ||
            (item.notification_key && candidate.notification_key === item.notification_key) ||
            candidate.id === item.id;

        if (matches && !candidate.is_read) {
            markedUnreadToRead = true;
        }

        return matches ? { ...candidate, is_read: true } : candidate;
    });

    // Turunkan badge total (lintas halaman) secara optimistik saat item yang
    // tadinya belum dibaca ditandai read. unreadPendingCount memakai angka ini.
    if (markedUnreadToRead && needsActionSummary.value) {
        const current = Number(needsActionSummary.value.unread_active_items || 0);
        needsActionSummary.value = {
            ...needsActionSummary.value,
            unread_active_items: Math.max(0, current - 1),
        };
    }
}

function scrollToOvertime() {
    if (isMobileViewport.value) {
        mobilePanelState.value = {
            ...mobilePanelState.value,
            overtime: true,
        };
        scrollToMobileSection('more');
        return;
    }

    overtimeSectionRef.value?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
}

function safePercent(value) {
    const number = Number(value || 0);
    if (Number.isNaN(number)) return 0;
    return Math.max(0, Math.min(number, 100));
}

function normalizeRecord(record) {
    const lateMinutes = Number(record.late_minutes ?? record.lateMinutes ?? 0);

    return {
        ...record,
        shift_label: record.shift_label || record.shift_name || record.shift || '',
        shift_time: record.shift_time || record.jam_shift || '',
        late: parseBooleanFlag(record.late ?? record.is_late, lateMinutes > 0),
        is_late: parseBooleanFlag(record.is_late ?? record.late, lateMinutes > 0),
        late_minutes: lateMinutes,
        early_out_minutes: Number(record.early_out_minutes ?? record.earlyOutMinutes ?? 0),
    };
}

function parseBooleanFlag(value, fallback = false) {
    if (value === undefined || value === null || value === '') return Boolean(fallback);
    if (typeof value === 'boolean') return value;
    if (typeof value === 'number') return value > 0;

    const normalized = String(value).trim().toLowerCase();
    if (['1', 'true', 'yes', 'y', 'ya'].includes(normalized)) return true;
    if (['0', 'false', 'no', 'n', 'tidak'].includes(normalized)) return false;

    return Boolean(fallback);
}

function hasLateAttendance(record, status = '') {
    return (
        Number(record?.late_minutes ?? record?.lateMinutes ?? 0) > 0 ||
        parseBooleanFlag(record?.late ?? record?.is_late, false) ||
        String(status || '')
            .toLowerCase()
            .includes('terlambat') ||
        String(status || '')
            .toLowerCase()
            .includes('late')
    );
}

/**
 * Terlambat yang TIDAK ikut terhitung sebagai keterlambatan.
 *
 * SP menolkan RealLate bila di bawah toleransi, dan memotongnya di 50 menit.
 * hasLateAttendance() di atas hanya melihat angka yang sudah dinolkan itu,
 * jadi orang yang datang 08:10 dari jadwal 08:00 dengan toleransi 15 menit
 * terbaca persis sama dengan yang datang tepat waktu.
 *
 * Ini keadaan TERPISAH, bukan pengganti: satu tanggal bisa sekaligus
 * terlambat-dimaafkan DAN tanpa check-out, dan dua-duanya harus terlihat.
 */
function hasForgivenLateness(record) {
    const k = record?.keterlambatan;

    return Boolean(k?.terlambat_sebenarnya) && Number(k?.tak_tercatat_menit ?? 0) > 0;
}

/** Kalimat pendek untuk lencana: "Telat 10 mnt (toleransi)". */
function forgivenLatenessLabel(record) {
    const k = record?.keterlambatan;
    if (!k) return 'Terlambat tak tercatat';

    const sebab = k.kena_plafon
        ? 'dibatasi'
        : {
              terlambat_toleransi: 'toleransi',
              terlambat_berizin: 'berizin',
              terlambat_dinolkan: 'tak tercatat',
          }[k.kategori] || 'tak tercatat';

    return `Telat ${k.mentah_menit} mnt (${sebab})`;
}

function normalizeTimeValue(value) {
    if (!value || value === '-' || value === 'null') return '-';
    return value;
}

function normalizeShiftMinutes(record) {
    const [shiftStart, shiftEnd] = String(record?.shift_time || '').split(' - ');
    const start = getMinutesFromTime(shiftStart || record?.jam_masuk);
    let end = getMinutesFromTime(shiftEnd || record?.jam_keluar);

    if (start !== null && end !== null && end <= start) {
        end += 1440;
    }

    return { start, end };
}

function resolveAttendanceTiming(record, dateKey, rawDate = null) {
    const current = new Date();
    const targetDate = rawDate || parseDate(dateKey);
    const isToday = isSameDate(targetDate, current);
    const isFutureDate = targetDate > new Date(current.getFullYear(), current.getMonth(), current.getDate());
    const { start, end } = normalizeShiftMinutes(record);
    let nowMinutes = getMinutesFromDate(current);

    if (isToday && start !== null && end !== null && end > 1440 && nowMinutes < start) {
        nowMinutes += 1440;
    }

    return {
        isToday,
        isFutureDate,
        shiftStartMinutes: start,
        shiftEndMinutes: end,
        beforeShiftStart: isToday && start !== null ? nowMinutes < start : false,
        afterShiftStartGrace: isToday && start !== null ? nowMinutes >= start + attendanceGraceMinutes : !isToday,
        afterShiftEndGrace: isToday && end !== null ? nowMinutes >= end + attendanceGraceMinutes : !isToday,
    };
}

function buildFlags(record, isFuture = false, timing = null) {
    const flags = [];
    const status = String(record?.status || '').toLowerCase();
    const isFutureLike = isFuture || timing?.isFutureDate || record?.future || status.includes('future');
    const isWaitingShift = record?.waiting_shift || status.includes('waiting shift') || status.includes('menunggu');
    const isWorking = record?.working || status.includes('working') || status.includes('sedang bekerja');
    const shouldSuppressOpenAttendanceIssue = isFutureLike || isWaitingShift || isWorking;

    if (record?.expired) flags.push({ key: 'expired', label: 'Pengajuan Expired', tone: 'danger', problem: true });
    if (!isFutureLike && !isWaitingShift && (record?.alpha || status.includes('alpha'))) {
        flags.push({ key: 'alpha', label: 'Alpha', tone: 'danger', problem: true });
    }
    if (!shouldSuppressOpenAttendanceIssue && (record?.no_checkin || status.includes('no checkin'))) {
        flags.push({ key: 'no-checkin', label: 'No Check In', tone: 'danger', problem: true });
    }
    if (!shouldSuppressOpenAttendanceIssue && (record?.no_checkout || status.includes('no checkout'))) {
        flags.push({ key: 'no-checkout', label: 'No Check Out', tone: 'warning', problem: true });
    }
    if (!isFutureLike && !isWaitingShift && hasLateAttendance(record, status)) {
        flags.push({ key: 'late', label: 'Terlambat', tone: 'warning', problem: false });
    }
    // Bendera sendiri, bukan cabang 'else' dari yang di atas: keduanya bisa
    // hidup bersamaan ketika seseorang terlambat 60 menit — 50 terhitung,
    // 10 sisanya terpotong plafon.
    if (!isFutureLike && !isWaitingShift && hasForgivenLateness(record)) {
        flags.push({
            key: 'late-forgiven',
            label: forgivenLatenessLabel(record),
            tone: 'info',
            problem: false,
        });
    }
    if (record?.early_out) flags.push({ key: 'early-out', label: 'Pulang Cepat', tone: 'warning', problem: true });
    if (record?.cuti || status.includes('cuti'))
        flags.push({ key: 'cuti', label: 'Cuti', tone: 'info', problem: false });
    if (record?.izin || record?.sakit || record?.is_leave || status.includes('izin') || status.includes('sakit')) {
        flags.push({ key: 'izin', label: 'Izin / Sakit', tone: 'info', problem: false });
    }
    if (record?.no_shift || status.includes('off')) {
        flags.push({ key: 'no-shift', label: 'OFF / No Shift', tone: 'default', problem: false });
    }
    if (
        (record?.is_holiday || record?.holiday_name || record?.holidayName) &&
        (record?.no_shift || status.includes('off'))
    ) {
        const liburLabel = record?.holiday_name || record?.holidayName || 'Hari Libur';
        flags.push({ key: 'hari-libur', label: liburLabel, tone: 'info', problem: false });
    }

    return flags;
}

function buildDayStatusChips(record, flags, primaryKey, timing = null) {
    const chips = [];
    const hasFlag = (key) => flags.some((flag) => flag.key === key);
    const lateMinutes = Number(record?.late_minutes ?? record?.lateMinutes ?? 0);
    const earlyOutMinutes = Number(record?.early_out_minutes ?? record?.earlyOutMinutes ?? 0);

    if (hasFlag('late') && lateMinutes > 0) {
        chips.push({ key: 'late-duration', label: `Telat ${formatDurationShort(lateMinutes)}`, tone: 'warning' });
    }

    // Chip terpisah, sengaja BUKAN pengganti chip di atas — KECUALI untuk kasus
    // plafon.
    //
    // Semula keduanya sengaja muncul berdampingan: "Telat 50m" dari angka resmi,
    // lalu "Telat 55 mnt (dibatasi)" yang menyebut kenyataannya. Keputusan itu
    // DICABUT atas permintaan pemilik halaman: pada kasus plafon, dua chip
    // "Telat" bersebelahan dengan angka berbeda lebih membingungkan daripada
    // informatif, dan angka resmi sudah terwakili chip di atas. Yang tetap
    // ditampilkan hanya keadaan yang TIDAK punya chip lain untuk mewakilinya —
    // toleransi, berizin, dan tak-tercatat — karena pada kasus itu angka
    // resminya nol dan tanpa chip ini harinya terlihat seolah tepat waktu.
    //
    // Yang disembunyikan hanya CHIP-nya. Bendera 'late-forgiven' sengaja
    // dibiarkan hidup di buildDayFlags karena dayHasStatus() memakainya untuk
    // menghitung "Terlambat"; membuangnya di sana akan menurunkan angka
    // penghitung, bukan sekadar merapikan tampilan.
    if (hasFlag('late-forgiven') && !record?.keterlambatan?.kena_plafon) {
        chips.push({
            key: 'late-forgiven',
            label: forgivenLatenessLabel(record),
            tone: 'info',
        });
    }

    if (hasFlag('early-out')) {
        chips.push({
            key: 'early-out-duration',
            label: earlyOutMinutes > 0 ? `Pulang cepat ${formatDurationShort(earlyOutMinutes)}` : 'Pulang cepat',
            tone: 'orange',
        });
    }

    if (hasFlag('no-checkin')) {
        chips.push({ key: 'no-checkin', label: 'No IN', tone: 'danger' });
    }

    if (hasFlag('no-checkout')) {
        chips.push({ key: 'no-checkout', label: 'No OUT', tone: 'warning' });
    }

    if (primaryKey === 'waiting-shift' || record?.waiting_shift || timing?.beforeShiftStart) {
        chips.push({ key: 'waiting-shift', label: 'Menunggu Jam Kerja', tone: 'default' });
    }

    return chips.slice(0, 3);
}

function resolvePrimaryStatus(record, flags, isFuture = false, timing = null) {
    if (isFuture || timing?.isFutureDate || record?.future) return 'future';

    const status = String(record?.status || '').toLowerCase();
    if (record?.waiting_shift || status.includes('waiting shift') || status.includes('menunggu'))
        return 'waiting-shift';
    // "Sedang bekerja" hanya valid bila ini benar-benar hari ini dan jam pulang shift
    // belum lewat. Untuk tanggal yang sudah lewat, flag/status "working" dari backend
    // tidak boleh dipercaya mentah — biarkan jatuh ke logika no-checkout di bawah.
    const isWorkingFlag = record?.working || status.includes('working') || status.includes('sedang bekerja');
    if (isWorkingFlag && timing?.isToday && !timing?.afterShiftEndGrace) return 'working';

    const priority = [
        'expired',
        'future',
        'alpha',
        'no-checkin',
        'no-checkout',
        'early-out',
        'late',
        'sakit',
        'izin',
        'cuti',
        'hari-libur',
        'no-shift',
    ];

    for (const key of priority) {
        if (flags.some((flag) => flag.key === key)) return key;
    }

    const checkIn = normalizeTimeValue(record?.check_in);
    const checkOut = normalizeTimeValue(record?.check_out);

    if (checkIn !== '-' && checkOut !== '-') return 'present';

    if (record?.shift_label || record?.shift_name || record?.shift) {
        if (checkIn === '-' && checkOut === '-') {
            if (timing?.beforeShiftStart) return 'waiting-shift';
            if (timing?.isToday && !timing?.afterShiftEndGrace) return 'no-checkin';
            return isFuture ? 'future' : 'alpha';
        }
        if (checkIn !== '-' && checkOut === '-')
            return timing?.isToday && !timing?.afterShiftEndGrace ? 'working' : 'no-checkout';
        if (checkIn === '-' && checkOut !== '-') return 'no-checkin';
    }

    return 'present';
}

function statusVisual(key) {
    const map = {
        present: { label: 'On-time', tone: 'success', icon: 'bi bi-check2-circle' },
        future: { label: 'Belum Dijalankan', tone: 'default', icon: 'bi bi-clock-history' },
        'waiting-shift': { label: 'Menunggu Jam Kerja', tone: 'default', icon: 'bi bi-hourglass-split' },
        working: { label: 'Sedang Bekerja', tone: 'info', icon: 'bi bi-person-workspace' },
        late: { label: 'Terlambat', tone: 'warning', icon: 'bi bi-alarm' },
        // Sengaja TIDAK masuk daftar prioritas di resolvePrimaryStatus: ia tak
        // boleh menggeser badge dari masalah yang sebenarnya (No OUT, Alpha).
        // Tempatnya di chip, berdampingan dengan keadaan lain pada hari yang sama.
        'late-forgiven': { label: 'Telat tak tercatat', tone: 'info', icon: 'bi bi-alarm' },
        'no-checkin': { label: 'No IN', tone: 'danger', icon: 'bi bi-box-arrow-in-right' },
        'no-checkout': { label: 'No OUT', tone: 'warning', icon: 'bi bi-box-arrow-right' },
        'early-out': { label: 'Pulang Cepat', tone: 'warning', icon: 'bi bi-box-arrow-right' },
        alpha: { label: 'Alpha', tone: 'danger', icon: 'bi bi-person-x' },
        izin: { label: 'Izin / Sakit', tone: 'info', icon: 'bi bi-file-earmark-medical' },
        izin: { label: 'Izin', tone: 'info', icon: 'bi bi-file-earmark-text' },
        sakit: { label: 'Sakit', tone: 'info', icon: 'bi bi-file-earmark-medical' },
        cuti: { label: 'Cuti', tone: 'info', icon: 'bi bi-airplane' },
        'no-shift': { label: 'OFF', tone: 'default', icon: 'bi bi-dash-circle' },
        'hari-libur': { label: 'Hari Libur', tone: 'holiday', icon: 'bi bi-calendar-heart-fill' },
        expired: { label: 'Expired', tone: 'danger', icon: 'bi bi-hourglass-split' },
    };

    return map[key] || map.present;
}

function buildTodayAwareness(day) {
    if (isDashboardHydrating.value) {
        return {
            tone: 'info',
            icon: 'bi bi-hourglass-split',
            badge: 'Memuat',
            message: 'Sedang mengambil data homepage dari backend.',
            actionType: 'default',
        };
    }

    if (!day) {
        return {
            tone: 'info',
            icon: 'lottie',
            badge: 'Info',
            message: 'Aku belum menemukan data absensi hari ini.',
            actionType: 'default',
        };
    }

    if (day.primaryStatusKey === 'waiting-shift') {
        return {
            tone: 'info',
            icon: 'bi bi-hourglass-split',
            badge: 'Menunggu',
            message: 'Jam kerja belum dimulai. Absensi belum dinilai sebagai alpha atau terlambat.',
            actionType: 'default',
        };
    }

    if (day.primaryStatusKey === 'working') {
        return {
            tone: 'info',
            icon: 'bi bi-person-workspace',
            badge: 'Sedang Bekerja',
            message: 'Check-in sudah tercatat. Check-out belum dianggap bermasalah karena jam pulang belum lewat.',
            actionType: 'default',
        };
    }

    const nowMinutes = getMinutesFromDate(new Date());
    const [shiftStart, shiftEnd] = String(day.shiftTime || '').split(' - ');
    const shiftStartMinutes = getMinutesFromTime(shiftStart);
    const shiftEndMinutes = getMinutesFromTime(shiftEnd);

    const checkInMissing =
        (!day.checkIn || day.checkIn === '-') && shiftStartMinutes && nowMinutes > shiftStartMinutes + 10;

    const checkoutMissing =
        day.checkIn &&
        day.checkIn !== '-' &&
        (!day.checkOut || day.checkOut === '-') &&
        shiftEndMinutes &&
        nowMinutes > shiftEndMinutes + 10;

    const earlyCheckout =
        day.checkOut &&
        day.checkOut !== '-' &&
        shiftEndMinutes &&
        getMinutesFromTime(day.checkOut) < shiftEndMinutes - 5;

    if (checkInMissing && !['izin', 'cuti', 'no-shift'].includes(day.primaryStatusKey)) {
        return {
            tone: 'danger',
            icon: 'bi bi-exclamation-octagon',
            badge: 'Perlu Tindakan',
            message: 'Jam masuk sudah lewat, tapi check-in kamu belum tercatat.',
            actionType: 'late-checkin',
        };
    }

    if (checkoutMissing) {
        return {
            tone: 'warning',
            icon: 'bi bi-box-arrow-right',
            badge: 'Pengingat',
            message: 'Jam pulang sudah lewat, tapi check-out kamu belum tercatat.',
            actionType: 'missing-checkout',
        };
    }

    if (earlyCheckout) {
        return {
            tone: 'warning',
            icon: 'bi bi-exclamation-circle',
            badge: 'Perhatian',
            message: 'Check-out kamu lebih cepat dari jadwal shift.',
            actionType: 'early-checkout',
        };
    }

    if (day.primaryStatusKey === 'late') {
        return {
            tone: 'warning',
            icon: 'bi bi-alarm',
            badge: 'Terlambat',
            message: 'Hari ini check-in kamu tercatat terlambat.',
            actionType: 'default',
        };
    }

    if (['izin', 'cuti'].includes(day.primaryStatusKey)) {
        return {
            tone: 'success',
            icon: 'bi bi-check2-circle',
            badge: 'Tercatat',
            message: 'Status hari ini sudah tercatat sebagai izin atau cuti.',
            actionType: 'default',
        };
    }

    if (day.primaryStatusKey === 'present') {
        return {
            tone: 'success',
            icon: 'lottie',
            badge: 'Aman',
            message: 'Absensi hari ini terlihat aman. Tetap pantau cutoff, KPI, LMS, dan lembur kamu.',
            actionType: 'default',
        };
    }

    return {
        tone: 'info',
        icon: 'lottie',
        badge: 'Info',
        message: 'Aku bantu pantau absensi, KPI, LMS, lembur, dan pengajuan kamu.',
        actionType: 'default',
    };
}

function countByStatus(key) {
    return cutoffDays.value.filter((day) => dayHasStatus(day, key)).length;
}

function countCalendarByStatus(key) {
    return calendarCutoffDays.value.filter((day) => dayHasStatus(day, key)).length;
}

function dayHasStatus(day, key) {
    if (!day || !key) return false;
    if (day.primaryStatusKey === key) return true;
    if (!Array.isArray(day.flags)) return false;

    // 'late-forgiven' ikut terhitung sebagai 'late'.
    //
    // Orangnya memang terlambat; hanya angkanya yang dimaafkan SP. Tanpa ini
    // penghitung menulis "Terlambat: 0" sementara kartu di bawahnya mengaku
    // "Telat 10 mnt (toleransi)" — dua pernyataan yang saling membantah di
    // layar yang sama, dan yang salah justru yang lebih besar hurufnya.
    //
    // Disatukan DI SINI supaya penghitung, saringan "Terlambat", dan daftar
    // harinya memakai satu aturan. Menuliskannya per pemanggil adalah cara
    // paling pasti membuat salah satunya tertinggal.
    const kunciCocok = key === 'late' ? ['late', 'late-forgiven'] : [key];

    return day.flags.some((flag) => kunciCocok.includes(flag.key));
}

function isAssessableCalendarDay(day) {
    if (!day) return false;
    return !['future', 'waiting-shift', 'working', 'no-shift'].includes(day.primaryStatusKey);
}

function isPresentCalendarDay(day) {
    if (!isAssessableCalendarDay(day)) return false;
    return (
        !['alpha', 'no-checkin'].includes(day.primaryStatusKey) &&
        (day.checkIn !== '-' || ['present', 'late', 'no-checkout'].includes(day.primaryStatusKey))
    );
}

function buildHcisTrendPointFromDays(days, range = {}) {
    const sourceDays = Array.isArray(days) ? days : [];
    const assessableDays = sourceDays.filter(isAssessableCalendarDay);
    const totalDays = assessableDays.length;
    const presentDays = assessableDays.filter(isPresentCalendarDay).length;
    const leaveDays = sourceDays.filter((day) => dayHasStatus(day, 'cuti')).length;
    const sickDays = sourceDays.filter((day) => dayHasStatus(day, 'izin')).length;
    const fulfilledDays = Math.min(totalDays, presentDays + sickDays + leaveDays);
    const percentage = totalDays > 0 ? Number(((fulfilledDays / totalDays) * 100).toFixed(1)) : 0;

    return normalizeHcisTrendPoint({
        period: range?.period || selectedCutoffPeriod.value,
        label: range?.label || 'Cutoff aktif',
        cutoff_start: range?.start || '',
        cutoff_end: range?.end || '',
        value: percentage,
        percentage,
        present_days: presentDays,
        fulfilled_days: fulfilledDays,
        total_days: totalDays,
        sick_days: sickDays,
        leave_days: leaveDays,
        late_minutes: sourceDays.reduce((total, day) => total + Number(day?.lateMinutes || 0), 0),
    });
}

function normalizeHcisTrendPoint(item = {}) {
    const percentage = Number(item.percentage ?? item.value ?? 0);

    return {
        ...item,
        period: String(item.period || item.cutoff_period || ''),
        label: String(item.label || item.period || '-'),
        cutoff_start: item.cutoff_start || item.cutoffStart || '',
        cutoff_end: item.cutoff_end || item.cutoffEnd || '',
        value: Number.isFinite(percentage) ? percentage : 0,
        percentage: Number.isFinite(percentage) ? percentage : 0,
        present_days: Number(item.present_days ?? item.presentDays ?? 0),
        fulfilled_days: Number(item.fulfilled_days ?? item.fulfilledDays ?? item.present_days ?? item.presentDays ?? 0),
        total_days: Number(item.total_days ?? item.totalDays ?? 0),
        sick_days: Number(item.sick_days ?? item.sickDays ?? 0),
        leave_days: Number(item.leave_days ?? item.leaveDays ?? 0),
        late_minutes: Number(item.late_minutes ?? item.lateMinutes ?? 0),
    };
}

function formatAttendancePercentage(value) {
    const number = Number(value || 0);
    if (!Number.isFinite(number)) return '0%';

    return `${number.toFixed(number % 1 === 0 ? 0 : 1)}%`;
}

function escapeTooltipText(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function buildHcisTrendTooltip(dataPointIndex) {
    const pointIndex = Number(dataPointIndex);
    if (Number.isInteger(pointIndex) && pointIndex >= 0) {
        hcisTrendHoverIndex.value = pointIndex;
    }

    const point = hcisPersonal.value.trend[Number.isInteger(pointIndex) && pointIndex >= 0 ? pointIndex : 0] || {};
    const percentage = formatAttendancePercentage(point.percentage ?? point.value);
    const fulfilled = Number(point.fulfilled_days || point.present_days || 0);
    const totalDays = Number(point.total_days || 0);
    const present = Number(point.present_days || 0);
    const sick = Number(point.sick_days || 0);
    const leave = Number(point.leave_days || 0);
    const lateMinutes = Number(point.late_minutes || 0);

    const rows = [
        { label: 'Persentase', value: percentage, tone: 'sah' },
        { label: 'Terpenuhi', value: `${fulfilled}/${totalDays} hari aktif`, tone: 'base' },
        { label: 'Hadir fisik', value: `${present} hari`, tone: 'muted' },
        { label: 'Sakit', value: `${sick} hari`, tone: 'muted' },
        { label: 'Cuti', value: `${leave} kali`, tone: 'muted' },
        { label: 'Terlambat', value: formatDurationShort(lateMinutes), tone: lateMinutes > 0 ? 'deduction' : 'muted' },
    ];

    const rowsHtml = rows
        .map(
            (row) =>
                `<span class="hcis-trend-tooltip__row is-${row.tone}"><span>${row.label}</span> <strong>${row.value}</strong></span>`,
        )
        .join('');

    return `
        <div class="hcis-trend-tooltip">
            <span class="hcis-trend-tooltip__title">${escapeTooltipText(point.label || 'Cutoff')}</span>
            ${rowsHtml}
            <span class="hcis-trend-tooltip__note">Persentase = (hadir + sakit + cuti) / hari aktif × 100%</span>
        </div>
    `;
}

function scrollToAttendanceCalendar() {
    if (isMobileViewport.value) {
        scrollToMobileSection('absensi');
        return;
    }

    attendanceCalendarSectionRef.value?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
}

function handleCutoffPeriodChange(event) {
    const period = event?.target?.value || '';
    if (!period || period === selectedCutoffPeriod.value) return;

    selectedCutoffPeriod.value = period;
    selectedDate.value = '';
    dayDetailCache.value = {};
    selectedAttendanceDonutPeriod.value = period;
    void loadAttendanceDonutComposition({ cutoffPeriod: period });
    void loadPersonalAttendanceSummary({ cutoffPeriod: period, syncDashboard: false });
}

function handleAttendanceDonutPeriodChange(event) {
    const period = event?.target?.value || '';
    if (!period || period === selectedAttendanceDonutPeriod.value) return;

    selectedAttendanceDonutPeriod.value = period;
    void loadAttendanceDonutComposition({ cutoffPeriod: period });
}

async function handleAttendanceDonutDetail(filter = 'all') {
    activeCalendarFilter.value = filter || 'all';
    const period = selectedAttendanceDonutPeriod.value || selectedCutoffPeriod.value;

    if (period && period !== selectedCutoffPeriod.value) {
        selectedCutoffPeriod.value = period;
        selectedDate.value = '';
        dayDetailCache.value = {};
        await loadPersonalAttendanceSummary({ cutoffPeriod: period, syncDashboard: false });
    }

    scrollToAttendanceCalendar();
}

function handleAttendanceDonutLegend(item) {
    if (!item?.count) return;
    void handleAttendanceDonutDetail(item.filter || 'all');
}

async function handleHcisTrendPointClick(index) {
    const pointIndex = Number(index);
    if (!Number.isInteger(pointIndex) || pointIndex < 0) return;

    const point = hcisPersonal.value.trend[pointIndex] || null;
    const period = String(point?.period || '');
    if (!period) return;

    selectedCutoffPeriod.value = period;
    selectedDate.value = '';
    dayDetailCache.value = {};

    try {
        if (calendarState.value.cutoffRange?.period !== period) {
            await loadPersonalAttendanceSummary({ cutoffPeriod: period, syncDashboard: false });
        }
    } finally {
        window.setTimeout(scrollToAttendanceCalendar, 80);
    }
}

async function fetchDayDetail(date) {
    if (!date || dayDetailCache.value[date]?.raw_logs !== undefined || dayDetailLoadingDate.value === date) return;

    dayDetailLoadingDate.value = date;
    dayDetailError.value = '';

    try {
        const response = await axios.get('/homepage/personal/calendar/day-detail', {
            params: { date },
        });

        if (response?.data?.data) {
            dayDetailCache.value = {
                ...dayDetailCache.value,
                [date]: response.data.data,
            };
        }
    } catch (error) {
        console.error('Failed to load attendance day detail', error);
        dayDetailError.value = 'Gagal memuat detail absensi.';
    } finally {
        if (dayDetailLoadingDate.value === date) {
            dayDetailLoadingDate.value = '';
        }
    }
}

async function openDayModal(day) {
    if (!day) return;
    selectedDate.value = day.date;
    showDayModal.value = true;
    await fetchDayDetail(day.date);
}

function closeDayModal() {
    showDayModal.value = false;
}

function openOvertimeAssignmentDetail(item) {
    selectedOvertimeAssignment.value = item;
    showOvertimeDetailModal.value = true;
}

function closeOvertimeAssignmentDetail() {
    showOvertimeDetailModal.value = false;
}

function toggleOvertimeDate(dateKey) {
    const next = new Set(expandedOvertimeDates.value);
    if (next.has(dateKey)) {
        next.delete(dateKey);
    } else {
        next.add(dateKey);
    }
    expandedOvertimeDates.value = next;
}

function isOvertimeDateExpanded(dateKey) {
    return expandedOvertimeDates.value.has(dateKey);
}

const OVERTIME_TYPE_BADGES = {
    before: { label: 'Sebelum', tone: 'blue' },
    after: { label: 'Setelah', tone: 'green' },
    shift: { label: 'Shift', tone: 'purple' },
    holiday: { label: 'Libur', tone: 'amber' },
};

function getOvertimeTypeKey(item) {
    if (item.isAutoShift) return 'shift';
    const label = String(item.typeLabel || '').toLowerCase();
    if (label.includes('sebelum')) return 'before';
    if (label.includes('setelah')) return 'after';
    if (label.includes('libur') || label.includes('holiday') || label.includes('off')) return 'holiday';
    if (label.includes('shift')) return 'shift';
    return 'after';
}

function getOvertimeTypeBadges(items) {
    const seen = new Set();
    const badges = [];
    for (const item of items) {
        const key = getOvertimeTypeKey(item);
        if (seen.has(key)) continue;
        seen.add(key);
        badges.push(OVERTIME_TYPE_BADGES[key] || { label: item.typeLabel, tone: 'base' });
    }
    return badges;
}

function buildFollowUpDescription(day) {
    if (day.primaryStatusKey === 'no-checkin') return 'Tidak ada check-in tercatat pada tanggal ini.';
    if (day.primaryStatusKey === 'no-checkout') return 'Check-out belum tercatat pada tanggal ini.';
    if (day.primaryStatusKey === 'future') return 'Tanggal ini belum dijalankan, jadi belum bisa dinilai.';
    if (day.primaryStatusKey === 'waiting-shift') return 'Jam kerja belum dimulai, jadi absensi masih menunggu.';
    if (day.primaryStatusKey === 'working')
        return 'Karyawan masih dalam jam kerja, check-out belum dinilai bermasalah.';
    if (day.primaryStatusKey === 'alpha') return 'Tidak ada kehadiran dan tidak ada pengajuan pada tanggal ini.';
    if (day.primaryStatusKey === 'expired') return 'Ada pengajuan yang sudah melewati batas tindak lanjut.';
    if (day.primaryStatusKey === 'late') return 'Terdapat keterlambatan pada tanggal ini.';
    if (day.primaryStatusKey === 'early-out') return 'Check-out lebih cepat dari jadwal shift.';
    return 'Periksa detail absensi pada tanggal ini.';
}

function visit(href) {
    if (!href) return;
    router.visit(href);
}

function parseDate(value) {
    const [year, month, day] = String(value || '')
        .split('-')
        .map(Number);

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
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function formatShortDate(date) {
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
    });
}

function formatLearningDurationLabel(hoursValue) {
    const hours = Number(hoursValue || 0);
    if (!hours) return '0j';

    const totalMinutes = Math.round(hours * 60);
    const wholeHours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;

    if (wholeHours && minutes) return `${wholeHours}j ${minutes}m`;
    if (wholeHours) return `${wholeHours}j`;

    return `${minutes}m`;
}

function formatDurationShort(minutesValue) {
    const totalMinutes = Math.max(0, Math.round(Number(minutesValue || 0)));
    if (!totalMinutes) return '0m';

    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;

    if (hours && minutes) return `${hours}j ${minutes}m`;
    if (hours) return `${hours}j`;

    return `${minutes}m`;
}

// Label jam ringkas dari nilai JAM (bukan menit). Mis. 4 -> "4 jam", 2.5 -> "2.5 jam".
function formatHoursLabel(hoursValue) {
    const value = Math.max(0, Number(hoursValue || 0));
    const rounded = Math.round(value * 10) / 10;
    const text = Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(1);

    return `${text} jam`;
}

function parseDateTimeValue(value) {
    if (!value || value === '-') return null;

    const text = String(value).trim();
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);

    if (match) {
        return new Date(
            Number(match[1]),
            Number(match[2]) - 1,
            Number(match[3]),
            Number(match[4] || 0),
            Number(match[5] || 0),
            Number(match[6] || 0),
        );
    }

    const parsed = new Date(text);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function formatDateTimeDate(value) {
    const date = parseDateTimeValue(value);
    return date ? formatShortDate(date) : '-';
}

function formatDateTimeTime(value) {
    const date = parseDateTimeValue(value);
    if (!date) {
        const timeMatch = String(value || '').match(/(\d{2}):(\d{2})/);
        return timeMatch ? `${timeMatch[1]}:${timeMatch[2]}` : '-';
    }

    return date.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
}

function normalizeOvertimeStatus(status) {
    const value = String(status || '').toLowerCase();
    if (value.includes('selesai') || value.includes('approved')) {
        return { key: 'done', label: 'Selesai', tone: 'success' };
    }
    if (value.includes('tolak') || value.includes('reject')) {
        return { key: 'rejected', label: 'Ditolak', tone: 'danger' };
    }

    return { key: 'process', label: status || 'Proses', tone: 'warning' };
}

function normalizeOvertimeAssignment(raw = {}, index = 0) {
    const isAutoShift = parseBooleanFlag(raw.Is_Auto_Shift ?? raw.is_auto_shift, false);
    const status = isAutoShift
        ? { key: 'auto', label: 'Otomatis', tone: 'info' }
        : normalizeOvertimeStatus(raw.Status ?? raw.status);
    const startRaw = raw.Tanggal_Lembur_Dari ?? raw.tanggal_lembur_dari ?? raw.start_at;
    const endRaw = raw.Tanggal_Lembur_Sampai ?? raw.tanggal_lembur_sampai ?? raw.end_at;
    const kerjaRaw = raw.Tanggal_Kerja ?? raw.tanggal_kerja ?? raw.Tanggal_Lembur ?? startRaw;

    // Durasi MURNI transaksi ini (menit, langsung dari SP). Tidak ada lagi cabang
    // "estimasi" — semua sudah di-floor di SP sehingga tidak memberi harapan palsu.
    const pureMinutes = Math.max(0, Math.round(Number(raw.Durasi_Murni_Menit ?? raw.durasi_murni_menit ?? 0)));
    const durationLabel = pureMinutes > 0 ? formatDurationShort(pureMinutes) : '-';

    // Konteks agregat per HARI (sama untuk semua transaksi se-tanggal)
    const dayGrossMinutes = Math.max(0, Math.round(Number(raw.Hari_Overtime_Gross_Menit ?? 0)));
    const dayNetMinutes = Math.max(0, Math.round(Number(raw.Hari_Overtime_Calculated_Menit ?? 0)));
    const dayShiftMinutes = Math.max(0, Math.round(Number(raw.Hari_Overtime_Shift_Menit ?? 0)));
    const dayKoefisienHours = Number(raw.Hari_Koefisien_OT_Jam ?? 0);
    const dayRealLateMinutes = Math.max(0, Math.round(Number(raw.Hari_Real_Late_Menit ?? 0)));
    const hasShift = parseBooleanFlag(raw.Hari_Ada_Shift ?? raw.hari_ada_shift, dayShiftMinutes > 0);

    const absensiComplete = parseBooleanFlag(raw.Absensi_Lengkap ?? raw.absensi_lengkap, false);
    const reason = String(raw.Alasan ?? raw.alasan ?? 'Tidak ada catatan.').trim() || 'Tidak ada catatan.';
    const assignedRangeLabel =
        raw.Assigned_Range_Label ||
        raw.assigned_range_label ||
        `${formatDateTimeDate(startRaw)} - ${formatDateTimeTime(startRaw)}-${formatDateTimeTime(endRaw)}`;
    const actualRangeLabel = raw.Actual_Range_Label || raw.actual_range_label || '-';
    const transactionNumber = raw.No_Transaksi ?? raw.no_transaksi ?? `LEMBUR-${index + 1}`;
    const dateKey = kerjaRaw ? String(kerjaRaw).slice(0, 10) : `tanpa-tanggal-${index}`;

    // Konteks shift (long shift) untuk komponen LEMBUR_SHIFT otomatis.
    const shiftName = String(raw.Shift_Nama ?? raw.shift_nama ?? '').trim();
    const shiftUpToHours = Number(raw.Shift_Up_To_Jam ?? raw.shift_up_to_jam ?? 0);
    const shiftActualHours = Number(raw.Shift_Aktual_Jam ?? raw.shift_aktual_jam ?? 0);

    // Sumber per-row: nomor transaksi (lembur ber-pengajuan) atau nama shift (otomatis).
    const sourceLabel =
        raw.Source_Label ||
        raw.source_label ||
        (isAutoShift
            ? `Shift ${shiftName || 'long shift'}`
            : transactionNumber && transactionNumber !== '-'
              ? transactionNumber
              : 'Tanpa nomor transaksi');

    return {
        raw,
        key: `${transactionNumber}-${index}`,
        transactionNumber,
        isAutoShift,
        statusKey: status.key,
        statusLabel: status.label,
        statusTone: status.tone,
        typeLabel: raw.Jenis_Lembur_Label ?? raw.jenis_lembur_label ?? raw.Jenis_Lembur ?? raw.Jenis ?? 'Lembur',
        dateKey,
        dateLabel: formatDateTimeDate(kerjaRaw),
        timeRangeLabel: isAutoShift
            ? 'Otomatis dari long shift'
            : `${formatDateTimeTime(startRaw)} - ${formatDateTimeTime(endRaw)}`,
        durationLabel,
        pureMinutes,
        // Sumber per-row + konteks shift
        sourceLabel,
        shiftName,
        shiftUpToHours,
        shiftActualHours,
        shiftUpToLabel: shiftUpToHours > 0 ? `Up To ${formatHoursLabel(shiftUpToHours)}` : '',
        shiftActualLabel: `${formatHoursLabel(shiftActualHours)}`,
        reason: truncateText(reason, 120),
        fullReason: reason,
        absensiLabel:
            raw.Absensi_Status ?? raw.absensi_status ?? (absensiComplete ? 'Absensi lengkap' : 'Absensi belum lengkap'),
        absensiTone: isAutoShift ? 'info' : absensiComplete ? 'success' : 'warning',
        requirementLabel:
            raw.Absensi_Requirement ?? raw.absensi_requirement ?? 'Ikuti kelengkapan absensi sesuai jenis lembur.',
        assignedRangeLabel,
        actualRangeLabel,
        checkInLabel: formatDateTimeTime(raw.CheckIn ?? raw.check_in),
        checkOutLabel: formatDateTimeTime(raw.CheckOut ?? raw.check_out),

        // Konteks HARI untuk header grup & modal breakdown
        dayGrossMinutes,
        dayNetMinutes,
        dayShiftMinutes,
        dayKoefisienHours,
        dayRealLateMinutes,
        hasShift,
        dayGrossLabel: dayGrossMinutes > 0 ? formatDurationShort(dayGrossMinutes) : '-',
        dayNetLabel: dayNetMinutes > 0 ? formatDurationShort(dayNetMinutes) : '-',
        dayShiftLabel: dayShiftMinutes > 0 ? formatDurationShort(dayShiftMinutes) : '-',
        dayKoefisienLabel: `${Math.floor(Math.max(0, dayKoefisienHours))}j`,
        // Chip ringkas: angka FINAL yang dipakai (jam sah)
        dayKoefisienChipLabel: `Lembur sah: ${Math.floor(Math.max(0, dayKoefisienHours))} jam`,
        // Tooltip kaya: breakdown terstruktur (dirender sebagai tooltip kustom)
        dayCalc: buildOvertimeCalc(
            dayGrossMinutes,
            dayShiftMinutes,
            dayRealLateMinutes,
            dayNetMinutes,
            Math.floor(Math.max(0, dayKoefisienHours)),
        ),
    };
}

// Breakdown perhitungan lembur sah per hari untuk tooltip kustom (terstruktur & konsisten).
function buildOvertimeCalc(grossMinutes, shiftMinutes, lateMinutes, netMinutes, sahHours) {
    const rows = [{ label: 'Lembur kotor', value: formatDurationShort(grossMinutes), tone: 'base' }];
    if (shiftMinutes > 0) {
        rows.push({ label: 'Termasuk lembur shift', value: formatDurationShort(shiftMinutes), tone: 'muted' });
    }
    rows.push({
        label: 'Pengurangan keterlambatan',
        value: lateMinutes > 0 ? `− ${formatDurationShort(lateMinutes)}` : '0m',
        tone: lateMinutes > 0 ? 'deduction' : 'muted',
    });
    rows.push({ label: 'Lembur bersih', value: formatDurationShort(netMinutes), tone: 'net' });
    return {
        rows,
        sahHours,
        sahLabel: `${sahHours} jam`,
        // String fallback untuk pembaca layar / atribut title
        plain:
            `Lembur kotor ${formatDurationShort(grossMinutes)} − keterlambatan ${formatDurationShort(lateMinutes)} ` +
            `= bersih ${formatDurationShort(netMinutes)}, dibulatkan ke bawah jadi ${sahHours} jam (ketentuan HC).`,
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
        timeline.push({
            label: 'Check In',
            time: record.check_in,
            kind: 'checkin',
        });
    }

    if (record?.check_out && record.check_out !== '-') {
        timeline.push({
            label: 'Check Out',
            time: record.check_out,
            kind: 'checkout',
        });
    }

    return timeline;
}

function shortDayName(value) {
    return String(value || '').slice(0, 3);
}

function compactCalendarMeta(day) {
    if (!day) return '-';
    if (day.checkIn && day.checkIn !== '-') return day.checkIn;
    if (day.isHoliday || day.primaryStatusKey === 'hari-libur') {
        return day.holidayName || 'Libur';
    }

    const shortStatusMap = {
        future: 'Belum',
        late: 'Late',
        'no-checkin': 'No IN',
        'no-checkout': 'No OUT',
        'early-out': 'Cepat',
        alpha: 'Alpha',
        izin: 'Izin',
        cuti: 'Cuti',
        'no-shift': 'OFF',
        'waiting-shift': 'Menunggu',
        working: 'Kerja',
        expired: 'Expired',
        present: 'Hadir',
    };

    return shortStatusMap[day.primaryStatusKey] || day.primaryStatusLabel || '-';
}

let hpRevealObserver = null;
let lazyDomainObserver = null;

// Replaced by native CSS card entrance animation system

/*
 * Pasang IntersectionObserver pada elemen ber-atribut [data-lazy-domain].
 * Saat panel masuk viewport, picu loader domainnya (sekali). Mayoritas user
 * cuma melihat absensi lalu pergi -> panel bawah (LMS/leave/dll) tak dimuat,
 * memangkas query yang sia-sia. Fallback idle di loadDashboard tetap menjaga
 * data terisi bila observer tak tersedia / panel sudah terlihat tanpa scroll.
 */
function setupLazyDomainObserver() {
    if (typeof IntersectionObserver === 'undefined') return;

    nextTick(() => {
        lazyDomainObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    const domain = entry.target.getAttribute('data-lazy-domain');
                    if (domain) triggerLazyDomain(domain);
                    lazyDomainObserver?.unobserve(entry.target);
                });
            },
            { rootMargin: '200px 0px' }, // pra-muat sedikit sebelum benar-benar terlihat
        );

        document.querySelectorAll('[data-lazy-domain]').forEach((el) => lazyDomainObserver.observe(el));
    });
}

onMounted(() => {
    // Bootstrap tunggal memuat attendance + composition + hcis + weekly sekaligus.
    // Tak perlu lagi loadMobileWeekCalendar()/loadAttendanceDonutComposition()
    // terpisah di mount (keduanya kini datang dari respons bootstrap).
    void loadDashboard({ bootstrap: true });
    updateViewportState();

    if (typeof window === 'undefined') return;

    setupLazyDomainObserver();

    window.addEventListener('resize', handleWindowResize);
    window.addEventListener('scroll', onScrollSyncMobileTab, { passive: true });
    syncActiveMobileTab();

    // Auto-jalankan tour kalau user pertama kali (tour_progress = not_started)
    autoStartTourIfFirstTime();
});

onBeforeUnmount(() => {
    Object.values(domainControllers.value).forEach((controller) => controller?.abort?.());
    domainControllers.value = {};
    mobileWeekController.value?.abort?.();
    mobileWeekController.value = null;
    attendanceDonutController.value?.abort?.();
    attendanceDonutController.value = null;

    hpRevealObserver?.disconnect();
    hpRevealObserver = null;

    lazyDomainObserver?.disconnect();
    lazyDomainObserver = null;

    if (typeof window === 'undefined') return;

    autoStartTourDibatalkan = true;

    if (autoStartTourTimer) {
        window.clearTimeout(autoStartTourTimer);
        autoStartTourTimer = null;
    }

    window.removeEventListener('resize', handleWindowResize);
    window.removeEventListener('scroll', onScrollSyncMobileTab);

    if (resizeDebounceTimer) {
        window.clearTimeout(resizeDebounceTimer);
        resizeDebounceTimer = null;
    }
});
</script>

<style scoped>
*,
*::before,
*::after {
    box-sizing: border-box;
}

.bi {
    display: contents !important;
}
.personal-dashboard-page {
    width: 100%;
    min-width: 0;
    min-height: 100vh;
    --primary: var(--hcis-indigo-500);
    --primary-dark: var(--hcis-indigo-600);
    --primary-light: var(--hcis-indigo-300);
    --success: var(--hcis-emerald-500);
    --warning: var(--hcis-amber-500);
    --danger: var(--hcis-red-500);
    --info: var(--hcis-cyan-500);
    --text: var(--hcis-slate-700);
    --text-muted: var(--hcis-slate-500);
    --border: var(--hcis-slate-200);
    --shadow-md: 0 4px 12px rgba(15, 23, 42, 0.06);
    --shadow-lg: 0 10px 28px rgba(15, 23, 42, 0.08);
    --shadow-xl: 0 20px 48px rgba(15, 23, 42, 0.1);
    --glass-bg: rgba(255, 255, 255, 0.82);
    --glass-border: rgba(199, 210, 254, 0.65);
    --glass-inset: inset 0 1.5px 0 rgba(255, 255, 255, 0.85);
    --liquid-ease: cubic-bezier(0.22, 1, 0.36, 1);
    --hp-reveal-duration: 780ms;

    /* ── Slate scale (neutral surfaces & text) ───────────────────────── */
    --slate-50: var(--hcis-slate-50);
    --slate-100: var(--hcis-slate-100);
    --slate-200: var(--hcis-slate-200);
    --slate-300: var(--hcis-slate-300);
    --slate-400: var(--hcis-slate-400);
    --slate-500: var(--hcis-slate-500);
    --slate-600: var(--hcis-slate-600);
    --slate-700: var(--hcis-slate-700);
    --slate-800: var(--hcis-slate-800);
    --slate-900: var(--hcis-slate-900);

    /* ── Indigo accent scale ─────────────────────────────────────────── */
    --indigo-50: var(--hcis-indigo-50);
    --indigo-100: var(--hcis-indigo-100);
    --indigo-500: var(--hcis-indigo-500);
    --indigo-600: var(--hcis-indigo-600);

    /* ── Surface & radius tokens (enterprise consistency) ────────────── */
    --surface: var(--hcis-white);
    --surface-muted: var(--slate-50);
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
    --radius-xl: 20px;
    --radius-pill: 999px;

    /* ── Responsive breakpoints (single source of truth, doc only) ───── */
    /* xs:480  sm:576  md:768  lg:992  xl:1080  2xl:1320 */

    color: var(--slate-900);
}

.personal-dashboard-container {
    width: 100%;
    /* max-width: 1480px; */
    min-width: 0;
    margin: 0 auto;
}

.mobile-dashboard {
    min-height: 100vh;
    overflow-x: hidden;
    padding-bottom: calc(5rem + env(safe-area-inset-bottom, 0px));
}

.mobile-dashboard__body {
    display: grid;
    background-color: #fff;

    gap: 0.72rem;
    /* padding: 0.95rem 0.85rem 0.3rem; */
}

.mobile-dashboard__error {
    margin-top: 0;
}

.mobile-section {
    display: grid;
    gap: 0.72rem;
}

.mobile-section--intro {
    padding-top: 0.18rem;
}

.mobile-hero {
    position: relative;
    overflow: hidden;
    display: grid;
    gap: 0.72rem;
    padding: 0.9rem;
    border-radius: 1.05rem;
    color: #fff;
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.24), transparent 14rem),
        linear-gradient(135deg, var(--hcis-indigo-600), var(--hcis-blue-600));
    box-shadow: 0 14px 34px rgba(37, 99, 235, 0.18);
}

.parameter.not-inPeriode {
    /* disabled stle */

    opacity: 0.4;
    pointer-events: none;
}

/* 
.mobile-hero.is-success {
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.22), transparent 14rem),
        linear-gradient(135deg, var(--hcis-emerald-600), var(--hcis-blue-600));
}

.mobile-hero.is-warning {
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.22), transparent 14rem),
        linear-gradient(135deg, var(--hcis-amber-600), var(--hcis-indigo-600));
}

.mobile-hero.is-danger {
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.22), transparent 14rem),
        linear-gradient(135deg, var(--hcis-red-600), var(--hcis-indigo-600));
} */

.mobile-hero__glow {
    position: absolute;
    border-radius: 999px;
    pointer-events: none;
    opacity: 0.32;
    filter: blur(14px);
}

.mobile-hero__glow--one {
    width: 11rem;
    height: 11rem;
    top: -4rem;
    right: -3.5rem;
    background: #fff;
}

.mobile-hero__glow--two {
    width: 9rem;
    height: 9rem;
    left: 28%;
    bottom: -4rem;
    background: var(--hcis-cyan-400);
}

.mobile-hero__header {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.48rem;
}

.mobile-hero__topline {
    flex: 1;
}

.mobile-hero__topline,
.mobile-hero__times,
.mobile-hero__brief,
.mobile-hero__note {
    position: relative;
    z-index: 1;
}

.mobile-hero__actions {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex: 0 0 auto;
}

.mobile-hero__eyebrow {
    display: block;
    font-size: 0.62rem;
    font-weight: 950;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.72);
}

.mobile-hero__shift {
    display: block;
    margin-top: 0.24rem;
    font-size: 0.94rem;
    font-weight: 950;
    line-height: 1.12;
}

.mobile-hero__meta {
    display: block;
    margin-top: 0.14rem;
    font-size: 0.66rem;
    font-weight: 850;
    color: rgba(255, 255, 255, 0.76);
}

.mobile-status-pill {
    flex: 0 0 auto;
    max-width: 8.25rem;
    padding: 0.36rem 0.55rem;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: rgba(255, 255, 255, 0.14);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.28rem;
    font-size: 0.58rem;
    font-weight: 950;
    line-height: 1.15;
    text-align: center;
}

.mobile-status-pill.is-success {
    background: rgba(16, 185, 129, 0.22);
}

.mobile-status-pill.is-warning {
    background: rgba(245, 158, 11, 0.24);
}

.mobile-status-pill.is-danger {
    background: rgba(239, 68, 68, 0.24);
}

.mobile-status-pill.is-info,
.mobile-status-pill.is-default {
    background: rgba(14, 165, 233, 0.22);
}

.mobile-hero__times {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}

.mobile-time-card {
    min-width: 0;
    padding: 0.8rem 0.6rem;
    border-radius: 0.88rem;
    border: 1px solid rgba(255, 255, 255, 0.14);
    background: rgba(255, 255, 255, 0.1);
    text-align: center;
}

.mobile-time-card span,
.mobile-time-card small {
    display: block;
    font-size: 0.65rem;
    font-weight: 850;
    color: rgba(255, 255, 255, 0.76);
}

.mobile-time-card strong {
    display: block;
    margin-top: 0.3rem;
    font-size: 1.52rem;
    font-weight: 950;
    line-height: 1;
    letter-spacing: -0.04em;
}

.mobile-time-card small {
    margin-top: 0.22rem;
}

.mobile-hero__note {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    font-size: 0.74rem;
    font-weight: 750;
    line-height: 1.35;
    color: rgba(255, 255, 255, 0.92);
}

.mobile-hero__lottie {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    margin-top: 0.05rem;
}

.mobile-hero__brief {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.42rem;
}

.mobile-hero__brief > div {
    min-width: 0;
    padding: 0.56rem;
    border-radius: 0.76rem;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.mobile-hero__brief span {
    display: block;
    font-size: 0.62rem;
    font-weight: 850;
    color: rgba(255, 255, 255, 0.72);
}

.mobile-hero__brief strong {
    display: block;
    margin-top: 0.14rem;
    font-size: 0.72rem;
    font-weight: 950;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.mobile-awareness-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.42rem;
}

.mobile-awareness-card {
    width: 100%;
    min-width: 0;
    min-height: 5.6rem;
    padding: 0.7rem 0.6rem;
    border: 1px solid rgba(226, 232, 240, 0.92);
    border-radius: 0.9rem;
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
    text-align: left;
    cursor: pointer;
    transition:
        transform 180ms ease,
        box-shadow 180ms ease,
        border-color 180ms ease;
}

.mobile-awareness-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
    border-color: rgba(199, 210, 254, 0.8);
}

.mobile-awareness-card:active {
    transform: scale(0.97);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
}

.mobile-awareness-card__icon {
    width: 2rem;
    height: 2rem;
    border-radius: 0.72rem;
    display: grid;
    place-items: center;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
}

.mobile-awareness-card__copy {
    margin-top: 0.48rem;
    min-width: 0;
}

.mobile-awareness-card__copy strong {
    display: block;
    color: var(--hcis-slate-900);
    font-size: 1rem;
    font-weight: 950;
    line-height: 1;
}

.mobile-awareness-card__copy span,
.mobile-awareness-card__copy small {
    display: block;
    margin-top: 0.18rem;
    font-size: 0.3rem;
    font-weight: 850;
    color: var(--hcis-slate-500);
    line-height: 1.2;
}

.mobile-awareness-card.is-danger .mobile-awareness-card__icon {
    background: var(--hcis-red-100);
    color: var(--hcis-red-600);
}

.mobile-awareness-card.is-purple .mobile-awareness-card__icon {
    background: #ede9fe;
    color: var(--hcis-violet-600);
}

.mobile-awareness-card.is-info .mobile-awareness-card__icon {
    background: var(--hcis-sky-100);
    color: var(--hcis-sky-600);
}

.mobile-awareness-card.is-orange .mobile-awareness-card__icon {
    background: var(--hcis-orange-100);
    color: var(--hcis-orange-600);
}

.mobile-awareness-card.is-success .mobile-awareness-card__icon {
    background: var(--hcis-green-100);
    color: var(--hcis-green-600);
}

.mobile-mini-calendar,
.mobile-accordion-card,
.mobile-attendance-panel {
    padding: 0.76rem;
    border-radius: 1rem;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.045);
}

.mobile-quick-actions {
    padding: 0.76rem;
    border-radius: 1rem;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.045);
}

.mobile-card-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.65rem;
    margin-bottom: 0.68rem;
}

.mobile-card-head__copy {
    min-width: 0;
}

.mobile-mini-calendar__actions {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    gap: 0.42rem;
}

.mobile-week-nav {
    display: inline-flex;
    align-items: center;
    gap: 0.18rem;
    padding: 0.12rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 999px;
    background: var(--hcis-slate-50);
}

.mobile-week-nav__button {
    width: 1.72rem;
    height: 1.72rem;
    border: 0;
    border-radius: 999px;
    display: grid;
    place-items: center;
    background: #fff;
    color: var(--hcis-slate-700);
    font-size: 0.72rem;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
    cursor: pointer;
    transition:
        background 150ms ease,
        box-shadow 150ms ease,
        opacity 150ms ease;
}

.mobile-week-nav__button:hover:not(:disabled) {
    background: var(--hcis-slate-100);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.12);
}
.mobile-week-nav__button.mobile-week-nav__button--today {
    margin-right: 6px;
}

.mobile-week-nav__button:active {
    transform: translateY(1px);
}

.mobile-week-nav__button:disabled {
    cursor: wait;
    opacity: 0.6;
    /* box-shadow: none; */
}

.mobile-card-head--stack {
    align-items: flex-start;
}

.mobile-card-head .section-label {
    line-height: 1;
}

.mobile-card-head h2 {
    margin: 0.18rem 0 0;
    font-size: 0.98rem;
    font-weight: 950;
    line-height: 1.05;
    color: var(--hcis-slate-900);
}

.mobile-inline-link {
    border: 0;
    background: transparent;
    color: var(--hcis-indigo-600);
    font-size: 0.62rem;
    font-weight: 900;
    cursor: pointer;
    white-space: nowrap;
}

.mobile-mini-calendar__grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.3rem;
}

.mobile-mini-calendar__day {
    min-width: 0;
    min-height: 4.3rem;
    padding: 0.54rem 0.2rem 0.5rem;
    border-radius: 0.9rem;
    border: 1px solid #d6dee9;
    background: #fff;
    color: var(--hcis-slate-900);
    display: grid;
    align-content: start;
    gap: 0.14rem;
    text-align: center;
    cursor: pointer;
    transition:
        transform 150ms ease,
        box-shadow 150ms ease,
        border-color 150ms ease,
        background 150ms ease;
}

.mobile-mini-calendar__day:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.08);
    border-color: #b8c9d9;
}

.mobile-mini-calendar__day-label,
.mobile-mini-calendar__day-meta {
    font-size: 0.6rem;
    font-weight: 850;
    color: inherit;
    opacity: 0.8;
}

.mobile-mini-calendar__day-number {
    font-size: 1.18rem;
    font-weight: 950;
    line-height: 1;
    color: inherit;
}

.mobile-mini-calendar__day-meta {
    line-height: 1.12;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.mobile-mini-calendar__day.is-success {
    background: linear-gradient(180deg, var(--hcis-green-50) 0%, #dff7e8 100%);
    border-color: #b9e8c7;
    color: var(--hcis-green-700);
}

.mobile-mini-calendar__day.is-warning {
    background: linear-gradient(180deg, #fff7df 0%, #feefc0 100%);
    border-color: #f4db8d;
    color: var(--hcis-amber-700);
}

.mobile-mini-calendar__day.is-danger {
    background: linear-gradient(180deg, #fff0f1 0%, #fde3e7 100%);
    border-color: #f3c1ca;
    color: var(--hcis-red-600);
}

.mobile-mini-calendar__day.is-info {
    background: linear-gradient(180deg, var(--hcis-sky-50) 0%, #dff0fc 100%);
    border-color: #b8ddf4;
    color: var(--hcis-sky-700);
}

.mobile-mini-calendar__day.is-default {
    background: var(--hcis-slate-50);
    border-color: #d6dee9;
    color: var(--hcis-slate-600);
}

.mobile-mini-calendar__day.is-holiday {
    background: linear-gradient(180deg, var(--hcis-red-50) 0%, var(--hcis-red-100) 100%);
    border-color: var(--hcis-red-200);
    color: var(--hcis-red-600);
    position: relative;
}

.mobile-mini-calendar__day.is-holiday::after {
    content: '';
    position: absolute;
    top: 0.2rem;
    right: 0.2rem;
    width: 0.38rem;
    height: 0.38rem;
    border-radius: 999px;
    background: var(--hcis-red-500);
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.18);
}

.mobile-mini-calendar__day.is-holiday:hover {
    background: linear-gradient(180deg, var(--hcis-red-100) 0%, var(--hcis-red-200) 100%);
    border-color: var(--hcis-red-300);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(220, 38, 38, 0.1);
}

.mobile-mini-calendar__day.is-holiday.is-today {
    background: linear-gradient(145deg, var(--hcis-red-600), var(--hcis-red-700));
    border-color: var(--hcis-red-800);
    color: #fff;
    box-shadow: 0 6px 18px rgba(220, 38, 38, 0.32);
}

.mobile-mini-calendar__day.is-holiday.is-today::after {
    background: var(--hcis-red-300);
    box-shadow: 0 0 0 2px rgba(252, 165, 165, 0.35);
}

.mobile-mini-calendar__day.is-holiday.is-today:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 24px rgba(220, 38, 38, 0.4);
}

.mobile-mini-calendar__day.is-holiday.is-today .mobile-mini-calendar__day-label,
.mobile-mini-calendar__day.is-holiday.is-today .mobile-mini-calendar__day-meta {
    opacity: 0.85;
    color: #fff;
}

.mobile-mini-calendar__day.is-today {
    background: linear-gradient(145deg, var(--hcis-indigo-600), var(--hcis-indigo-700));
    border-color: var(--hcis-indigo-800);
    color: #fff;
    box-shadow: 0 6px 18px rgba(79, 70, 229, 0.26);
}

.mobile-mini-calendar__day.is-today:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 24px rgba(79, 70, 229, 0.34);
}

.mobile-mini-calendar__day.is-today .mobile-mini-calendar__day-label,
.mobile-mini-calendar__day.is-today .mobile-mini-calendar__day-meta {
    opacity: 0.8;
    color: #fff;
}

.mobile-quick-actions__rail {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: minmax(7rem, 38%);
    grid-template-rows: repeat(2, minmax(4.55rem, auto));
    gap: 0.46rem;
    overflow-x: auto;
    overflow-y: hidden;
    padding: 0.04rem 0.08rem 0.32rem;
    scroll-snap-type: x proximity;
    -webkit-overflow-scrolling: touch;
}

.mobile-quick-actions__rail::-webkit-scrollbar {
    height: 4px;
}

.mobile-quick-actions__rail::-webkit-scrollbar-track {
    background: transparent;
}

.mobile-quick-actions__rail::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: var(--hcis-slate-300);
}

.mobile-quick-action-folder {
    min-width: 0;
    min-height: 5rem;
    padding: 0.62rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.86rem;
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-slate-50) 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    text-align: center;
    scroll-snap-align: start;
    cursor: pointer;
    transition:
        border-color 200ms ease,
        background 200ms ease,
        box-shadow 200ms ease,
        transform 200ms ease;
}

.mobile-quick-action-folder:hover {
    background: linear-gradient(135deg, var(--hcis-indigo-50) 0%, var(--hcis-indigo-100) 100%);
    border-color: var(--hcis-indigo-200);
    box-shadow: 0 6px 16px rgba(99, 102, 241, 0.1);
    transform: translateY(-1px);
}

.mobile-quick-action-folder:active {
    transform: translateY(1px);
}

.mobile-quick-action-folder__icon {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 0.8rem;
    display: grid;
    place-items: center;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    flex-shrink: 0;
    font-size: 0.92rem;
}

.mobile-quick-action-folder__copy {
    width: 100%;
    min-width: 0;
    display: grid;
    gap: 0.14rem;
}

.mobile-quick-action-folder__copy strong {
    color: var(--hcis-slate-900);
    font-size: 0.68rem;
    font-weight: 950;
    line-height: 1.15;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.mobile-quick-action-folder__copy small {
    color: var(--hcis-slate-500);
    font-size: 0.58rem;
    font-weight: 850;
    line-height: 1.1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mobile-accordion__trigger {
    width: 100%;
    padding: 0.12rem 0.08rem;
    border: 0;
    background: transparent;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.68rem;
    text-align: left;
    cursor: pointer;
    font-family: inherit;
    min-height: 3.35rem;
    border-radius: 0.62rem;
    transition: background 150ms ease;
}

.mobile-accordion__trigger:hover {
    background: rgba(15, 23, 42, 0.025);
}

.mobile-accordion-card .module-title {
    flex: 1;
    min-width: 0;
    align-items: center;
    gap: 0.62rem;
}

.mobile-accordion-card .module-icon {
    width: 2.26rem;
    height: 2.26rem;
    border-radius: 0.72rem;
}

.mobile-accordion-card .module-title-copy {
    flex: 1;
    display: grid;
    gap: 0.16rem;
    min-width: 0;
}

.mobile-accordion-card .section-label {
    font-size: 0.64rem;
    letter-spacing: 0.11em;
    line-height: 1;
}

.mobile-accordion-card .module-title-copy h2 {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 900;
    line-height: 1.2;
    color: var(--hcis-slate-800);
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.mobile-accordion__meta {
    flex: 0 0 auto;
    min-width: 4.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.45rem;
    color: var(--hcis-slate-400);
}

.mobile-accordion-card .period-badge {
    max-width: 7rem;
    padding: 0.24rem 0.46rem;
    font-size: 0.62rem;
}

.mobile-accordion-card .side-count {
    min-width: 1.46rem;
    height: 1.46rem;
    font-size: 0.62rem;
}

.mobile-accordion__meta > i {
    font-size: 0.8rem;
}

.mobile-accordion__body {
    margin-top: 0.68rem;
    padding-top: 0.68rem;
    border-top: 1px solid var(--hcis-slate-100);
    animation: accordion-open 0.26s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.mobile-dashboard .action-list,
.mobile-dashboard .submission-list,
.mobile-dashboard .schedule-list {
    gap: 0.36rem;
}

.mobile-kpi-layout {
    grid-template-columns: 1fr;
}

.mobile-training-list {
    max-height: none;
}

.mobile-dashboard .action-card,
.mobile-dashboard .submission-item,
.mobile-dashboard .schedule-item {
    border-radius: 0.76rem;
    box-shadow: none;
}

.mobile-dashboard .action-card {
    padding: 0.46rem;
    gap: 0.42rem;
}

.mobile-dashboard .action-card__icon {
    width: 1.7rem;
    height: 1.7rem;
    border-radius: 0.56rem;
}

.mobile-dashboard .action-card__body strong,
.mobile-dashboard .submission-item strong,
.mobile-dashboard .schedule-item > div:nth-child(2) strong {
    font-size: 0.68rem;
}

.mobile-dashboard .action-card__body span,
.mobile-dashboard .submission-item span,
.mobile-dashboard .schedule-item > div:nth-child(2) span {
    font-size: 0.62rem;
}

.mobile-dashboard .action-card__body small {
    font-size: 0.58rem;
}

.mobile-dashboard .submission-item em {
    font-size: 0.58rem;
}

.mobile-dashboard .submission-item {
    gap: 0.44rem;
    padding: 0.5rem 0.54rem;
}

.mobile-dashboard .submission-item small,
.mobile-dashboard .schedule-item small {
    font-size: 0.58rem;
    padding: 0.18rem 0.38rem;
}

.mobile-dashboard .schedule-item {
    gap: 0.44rem;
    padding: 0.5rem 0.54rem;
}

.mobile-dashboard .schedule-date {
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 0.62rem;
}

.mobile-dashboard .schedule-date strong {
    font-size: 0.62rem;
}

.mobile-dashboard .attendance-summary-row {
    gap: 0.36rem;
    margin-bottom: 0.52rem;
}

.mobile-dashboard .summary-pill {
    padding: 0.48rem 0.28rem;
    border-radius: 0.72rem;
}

.mobile-dashboard .summary-pill strong {
    font-size: 0.86rem;
}

.mobile-dashboard .summary-pill span {
    margin-top: 0.12rem;
    font-size: 0.58rem;
    color: currentColor;
    opacity: 0.78;
}

.mobile-dashboard .loading-state.is-compact,
.mobile-dashboard .empty-state.is-compact {
    min-height: 3.25rem;
    padding: 0.5rem;
}

.mobile-bottom-nav {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 35;
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.16rem;
    padding: 0.4rem 0.42rem calc(0.4rem + env(safe-area-inset-bottom, 0px));
    background: rgba(255, 255, 255, 0.96);
    border-top: 1px solid rgba(226, 232, 240, 0.92);
    box-shadow: 0 -8px 22px rgba(15, 23, 42, 0.07);
    /* backdrop-filter: blur(20px); */
    /* -webkit-backdrop-filter: blur(20px); */
}

.mobile-bottom-nav__item {
    border: 0;
    background: transparent;
    color: var(--hcis-slate-400);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.16rem;
    padding: 0.24rem 0.1rem;
    font-family: inherit;
    cursor: pointer;
    border-radius: 0.76rem;
}

.mobile-bottom-nav__item i {
    font-size: 0.94rem;
}

.mobile-bottom-nav__item span {
    font-size: 0.64rem;
    font-weight: 900;
}

.mobile-bottom-nav__item.is-active {
    color: var(--hcis-indigo-600);
    background: rgba(79, 70, 229, 0.08);
}

.bi {
    display: inline-flex;
    line-height: 1;
}

.section-label {
    display: block;
    font-size: 0.64rem;
    font-weight: 900;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    color: var(--hcis-slate-500);
}

.personal-hero {
    position: relative;
    overflow: hidden;
    border-radius: 1.25rem;
    padding: clamp(0.9rem, 2vw, 1.2rem);
    color: white;
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.24), transparent 17rem),
        linear-gradient(135deg, var(--primary-dark), var(--primary));
    box-shadow:
        0 22px 52px rgba(79, 70, 229, 0.22),
        inset 0 1.5px 0 rgba(255, 255, 255, 0.2);
}

.personal-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.18), transparent 55%);
    pointer-events: none;
    z-index: 0;
}

.hero-orb {
    position: absolute;
    border-radius: 999px;
    pointer-events: none;
}

.hero-orb--one {
    width: 16rem;
    height: 16rem;
    right: -6rem;
    top: -7rem;
    background: #fff;
    filter: blur(18px);
    opacity: 0.32;
}

.hero-orb--two {
    width: 12rem;
    height: 12rem;
    left: 42%;
    bottom: -8rem;
    background: var(--hcis-cyan-400);
    filter: blur(22px);
    opacity: 0.22;
}

.personal-hero__content {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(280px, 340px);
    gap: 0.75rem;
    align-items: stretch;
    min-width: 0;
}

.personal-hero__left,
.personal-hero__right,
.hero-title-copy,
.module-title-copy {
    min-width: 0;
}

.hero-title-copy h1 {
    color: #fff;
}

.hero-topline {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
    min-width: 0;
}

.hero-badge,
.hero-period,
.hero-role-pill {
    max-width: 100%;
    display: inline-flex;
    align-items: center;
    gap: 0.34rem;
    padding: 0.26rem 0.5rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.18);
    font-size: 0.64rem;
    font-weight: 900;
}

.hero-period span,
.hero-role-pill span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hero-title-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.75rem;
    margin-top: 0.6rem;
    min-width: 0;
}

.hero-title-row h1 {
    margin: 0;
    font-size: clamp(1.55rem, 2.6vw, 2.1rem);
    font-weight: 950;
    letter-spacing: -0.035em;
}

.hero-title-row p {
    max-width: 760px;
    margin: 0.32rem 0 0;
    color: rgba(255, 255, 255, 0.84);
    font-size: clamp(0.78rem, 1vw, 0.88rem);
    line-height: 1.55;
}

.hero-summary-callout {
    margin-top: 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    max-width: 100%;
    padding: 0.6rem 0.8rem;
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid rgba(255, 255, 255, 0.16);
    /* backdrop-filter: blur(10px); */
    /* -webkit-backdrop-filter: blur(10px); */
}

.hero-summary-callout__icon {
    width: 2rem;
    height: 2rem;
    border-radius: 0.75rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.16);
    flex: 0 0 auto;
}

.hero-summary-callout__copy {
    min-width: 0;
    display: grid;
    gap: 0.12rem;
}

.hero-summary-callout__copy span {
    font-size: 0.62rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.16em;
    color: rgba(255, 255, 255, 0.72);
}

.hero-summary-callout__copy strong {
    font-size: 0.84rem;
    line-height: 1.35;
    font-weight: 850;
    color: #fff;
}

.leader-shortcut-btn {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    gap: 0.36rem;
    min-height: 2rem;
    padding: 0 0.62rem;
    border-radius: 0.66rem;
    color: white;
    text-decoration: none;
    font-size: 0.66rem;
    font-weight: 900;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.2);
    position: relative;
    transition:
        transform 0.2s ease-in-out,
        background 0.2s ease-in-out,
        border-color 0.2s ease-in-out,
        box-shadow 0.2s ease-in-out;
}

.leader-shortcut-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
}
/* 
.leader-shortcut-btn.has-badge {
    padding-right: 1.55rem;
} */

.mobile-hero-team-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: relative;
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 0.72rem;
    color: white;
    font-size: 0.9rem;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.13));
    border: 1px solid rgba(255, 255, 255, 0.28);
    cursor: pointer;
    transition:
        transform 0.2s ease-in-out,
        background 0.2s ease-in-out,
        border-color 0.2s ease-in-out,
        box-shadow 0.2s ease-in-out;
    /* backdrop-filter: blur(8px); */
    box-shadow:
        0 0.45rem 1rem rgba(8, 16, 42, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.12);
}

.mobile-hero-team-btn:active {
    background: rgba(255, 255, 255, 0.28);
    border-color: rgba(255, 255, 255, 0.4);
    transform: scale(0.92);
}

.team-dashboard-badge {
    position: absolute;
    top: -0.34rem;
    right: -0.34rem;
    min-width: 1.15rem;
    height: 1.15rem;
    padding: 0 0.28rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255, 255, 255, 0.88);
    background: linear-gradient(180deg, #fb7185 0%, var(--hcis-red-500) 100%);
    color: #fff;
    font-size: 0.5rem;
    font-weight: 950;
    line-height: 1;
    letter-spacing: 0.01em;
    box-shadow: 0 0.42rem 1rem rgba(185, 28, 28, 0.34);
    transform-origin: 50% 50%;
    animation: badgePopBounce 560ms cubic-bezier(0.2, 1.35, 0.24, 1) both;
}

@keyframes badgePopBounce {
    0% {
        opacity: 0;
        transform: scale(0.35) translate(0.18rem, -0.18rem);
    }

    60% {
        opacity: 1;
        transform: scale(1.14) translate(0, 0);
    }

    78% {
        transform: scale(0.96);
    }

    100% {
        opacity: 1;
        transform: scale(1);
    }
}

.today-status-strip {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) repeat(3, minmax(0, 1fr));
    gap: 0.42rem;
    margin-top: 0.75rem;
    min-width: 0;
}

.today-chip {
    min-width: 0;
    min-height: 3.55rem;
    padding: 0.52rem;
    border-radius: 0.78rem;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.18);
}

.today-chip span,
.today-chip small {
    display: block;
    font-size: 0.6rem;
    font-weight: 850;
    color: rgba(255, 255, 255, 0.72);
}

.today-chip strong {
    display: block;
    margin-top: 0.1rem;
    font-size: 0.84rem;
    font-weight: 950;
    line-height: 1.15;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.workday-brief-card {
    height: 100%;
    min-width: 0;
    padding: 0.68rem;
    border-radius: 0.9rem;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.18);
}

.brief-head {
    display: flex;
    justify-content: space-between;
    gap: 0.65rem;
    align-items: center;
    min-width: 0;
}

.brief-head span,
.brief-head small,
.brief-grid span,
.brief-note span,
.brief-note small {
    display: block;
    color: rgba(255, 255, 255, 0.72);
    font-size: 0.58rem;
    font-weight: 850;
}

.brief-head strong {
    display: block;
    margin-top: 0.08rem;
    font-size: 1.25rem;
    font-weight: 950;
}

.brief-icon {
    width: 2.45rem;
    height: 2.45rem;
    flex: 0 0 auto;
    border-radius: 0.78rem;
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.14);
    font-size: 1rem;
}

.brief-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.38rem;
    margin-top: 0.6rem;
    min-width: 0;
}

.brief-grid > div,
.brief-note {
    min-width: 0;
    padding: 0.46rem;
    border-radius: 0.64rem;
    background: rgba(255, 255, 255, 0.12);
}

.brief-grid strong,
.brief-note strong {
    display: block;
    margin-top: 0.08rem;
    color: #fff;
    font-size: 0.68rem;
    font-weight: 950;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.brief-note {
    margin-top: 0.4rem;
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    align-items: center;
}

.awareness-strip {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.48rem;
    margin-top: 0.62rem;
    min-width: 0;
}

.awareness-card {
    position: relative;
    width: 100%;
    min-width: 0;
    min-height: 3.55rem;
    display: flex;
    align-items: center;
    gap: 0.56rem;
    padding: 0.56rem 0.68rem;
    border: 1px solid rgba(226, 232, 240, 0.92);
    border-radius: 0.82rem;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.95)), var(--hcis-white);
    box-shadow:
        0 10px 24px rgba(15, 23, 42, 0.045),
        inset 0 1px 0 rgba(255, 255, 255, 0.92);
    overflow: hidden;
    isolation: isolate;
    text-align: left;
    cursor: pointer;
    transition:
        transform 180ms var(--liquid-ease),
        box-shadow 180ms var(--liquid-ease),
        border-color 180ms ease,
        background 180ms ease;
}

.awareness-card::after {
    content: '';
    position: absolute;
    inset: auto -1.7rem -2rem auto;
    width: 4.8rem;
    height: 4.8rem;
    border-radius: 999px;
    background: currentColor;
    opacity: 0.045;
    pointer-events: none;
    z-index: -1;
    transform: scale(0.86);
    transition:
        opacity 180ms ease,
        transform 180ms var(--liquid-ease);
}

.awareness-card:hover {
    transform: translateY(-2px);
    border-color: rgba(148, 163, 184, 0.42);
    background: linear-gradient(180deg, var(--hcis-white), rgba(248, 250, 252, 0.98)), var(--hcis-white);
    box-shadow:
        0 16px 34px rgba(15, 23, 42, 0.08),
        0 4px 12px rgba(99, 102, 241, 0.06);
}

.awareness-card:hover::after {
    opacity: 0.085;
    transform: scale(1);
}

.awareness-card:active {
    transform: translateY(0);
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.05);
}

.awareness-card:focus-visible {
    outline: 2px solid rgba(79, 70, 229, 0.36);
    outline-offset: 2px;
}

.awareness-card__icon {
    flex: 0 0 auto;
    width: 2.25rem;
    height: 2.25rem;
    display: grid;
    place-items: center;
    border-radius: 0.74rem;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    font-size: 1rem;
    transition:
        transform 180ms var(--liquid-ease),
        box-shadow 180ms ease;
}

.awareness-card__copy {
    flex: 1;
    min-width: 0;
}

.awareness-card__copy strong {
    display: block;
    color: var(--hcis-slate-900);
    font-size: clamp(0.98rem, 1.05vw, 1.15rem);
    font-weight: 950;
    line-height: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.awareness-card__copy span {
    display: block;
    margin-top: 0.16rem;
    color: var(--hcis-slate-700);
    font-size: 0.66rem;
    font-weight: 900;
    line-height: 1.15;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.awareness-card__copy small {
    display: block;
    margin-top: 0.1rem;
    color: var(--hcis-slate-500);
    font-size: 0.58rem;
    font-weight: 800;
    line-height: 1.2;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.awareness-card__arrow {
    flex: 0 0 auto;
    margin-left: auto;
    color: var(--hcis-slate-400);
    font-size: 1.02rem;
    opacity: 0;
    transform: translateX(-0.2rem);
    transition:
        opacity 180ms ease,
        transform 180ms var(--liquid-ease),
        color 180ms ease;
}

.awareness-card:hover .awareness-card__arrow,
.awareness-card:focus-visible .awareness-card__arrow {
    opacity: 1;
    transform: translateX(0);
    color: var(--hcis-slate-700);
}

.awareness-card.is-danger .awareness-card__icon {
    background: var(--hcis-red-100);
    color: var(--hcis-red-600);
}

.awareness-card.is-danger {
    color: var(--hcis-red-600);
}

.awareness-card.is-purple .awareness-card__icon {
    background: #ede9fe;
    color: var(--hcis-violet-600);
}

.awareness-card.is-purple {
    color: var(--hcis-violet-600);
}

.awareness-card.is-info .awareness-card__icon {
    background: var(--hcis-sky-100);
    color: var(--hcis-sky-600);
}

.awareness-card.is-info {
    color: var(--hcis-sky-600);
}

.awareness-card.is-orange .awareness-card__icon {
    background: var(--hcis-orange-100);
    color: var(--hcis-orange-600);
}

.awareness-card.is-orange {
    color: var(--hcis-orange-600);
}

.awareness-card.is-success .awareness-card__icon {
    background: var(--hcis-green-100);
    color: var(--hcis-green-600);
}

.awareness-card.is-success {
    color: var(--hcis-green-600);
}

/* Per-tone icon glow on hover */
.awareness-card:hover .awareness-card__icon {
    transition: box-shadow 220ms var(--liquid-ease);
}
.awareness-card.is-danger:hover .awareness-card__icon {
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.14);
}
.awareness-card.is-purple:hover .awareness-card__icon {
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.14);
}
.awareness-card.is-info:hover .awareness-card__icon {
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.14);
}
.awareness-card.is-success:hover .awareness-card__icon {
    box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.14);
}
.awareness-card.is-orange:hover .awareness-card__icon {
    box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.14);
}

.dashboard-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(280px, 330px);
    gap: 0.6rem;
    align-items: start;
    margin-top: 0.6rem;
    min-width: 0;
}

.dashboard-main,
.dashboard-side {
    min-width: 0;
    display: grid;
    gap: 0.6rem;
}

.dashboard-side {
    position: sticky;
    top: 0.8rem;
}

.panel-card {
    position: relative;
    min-width: 0;
    overflow: hidden;
    border: 1px solid var(--glass-border);
    border-radius: 1rem;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(255, 255, 255, 0.92));
    box-shadow:
        0 10px 28px rgba(15, 23, 42, 0.055),
        var(--glass-inset);
    /* backdrop-filter: blur(22px) saturate(1.14); */
    /* -webkit-backdrop-filter: blur(22px) saturate(1.14); */
    transition:
        box-shadow 240ms var(--liquid-ease),
        transform 240ms var(--liquid-ease),
        border-color 240ms ease;
}

.panel-card::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.42), transparent 42%);
    pointer-events: none;
    z-index: 0;
}

.dashboard-side .panel-card {
    border-color: rgba(199, 210, 254, 0.72);
    background:
        radial-gradient(circle at top right, rgba(99, 102, 241, 0.055), transparent 65%),
        linear-gradient(135deg, rgba(255, 255, 255, 0.92), rgba(238, 242, 255, 0.72));
}

.module-card:hover,
.side-panel:hover {
    transform: translateY(-2px);
    box-shadow:
        0 16px 36px rgba(15, 23, 42, 0.08),
        0 4px 12px rgba(99, 102, 241, 0.07),
        var(--glass-inset);
    border-color: rgba(165, 180, 252, 0.6);
}

.module-top-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.6rem;
    min-width: 0;
}

.overtime-card {
    grid-column: 1 / -1;
}

.module-card,
.attendance-panel,
.side-panel {
    padding: 0.68rem;
}

.module-card__head,
.module-title,
.panel-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.55rem;
    min-width: 0;
}

.module-title {
    align-items: center;
    justify-content: flex-start;
}

.module-card__head,
.panel-head {
    margin-bottom: 0.5rem;
}

/* ===== Kartu KPI/LMS on-demand: teaser "klik untuk muat" ===== */
.module-reveal {
    position: relative;
    width: 100%;
    min-height: 13rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 2.2rem 1.5rem;
    border: 1.5px dashed var(--reveal-border, #ddd6fe);
    border-radius: 1rem;
    background:
        radial-gradient(120% 90% at 50% -10%, var(--reveal-tint, rgba(124, 58, 237, 0.09)) 0%, transparent 60%),
        linear-gradient(180deg, var(--hcis-white) 0%, var(--reveal-bg, #faf9ff) 100%);
    text-align: center;
    cursor: pointer;
    overflow: hidden;
    transition:
        transform 220ms cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 220ms ease,
        border-color 220ms ease;
}

.module-reveal--purple {
    --reveal-border: #ddd6fe;
    --reveal-tint: rgba(124, 58, 237, 0.1);
    --reveal-bg: #faf9ff;
    --reveal-accent: var(--hcis-violet-600);
    --reveal-accent-2: var(--hcis-purple-500);
}

.module-reveal--teal {
    --reveal-border: #99f6e4;
    --reveal-tint: rgba(13, 148, 136, 0.1);
    --reveal-bg: #f5fffd;
    --reveal-accent: #0d9488;
    --reveal-accent-2: #14b8a6;
}

.module-reveal--blue {
    --reveal-border: #bfdbfe;
    --reveal-tint: rgba(37, 99, 235, 0.1);
    --reveal-bg: #f7faff;
    --reveal-accent: var(--hcis-blue-600);
    --reveal-accent-2: var(--hcis-blue-500);
}

.module-reveal--orange {
    --reveal-border: #fed7aa;
    --reveal-tint: rgba(234, 88, 12, 0.1);
    --reveal-bg: #fff9f5;
    --reveal-accent: var(--hcis-orange-600);
    --reveal-accent-2: #f97316;
}

/* Varian ringkas untuk side-panel (mis. Pengajuan) yang lebih pendek. */
.module-reveal--compact {
    min-height: 10rem;
    padding: 1.7rem 1.25rem;
}

.module-reveal--compact .module-reveal__icon {
    width: 2.7rem;
    height: 2.7rem;
    font-size: 1.15rem;
}

.module-reveal:hover {
    transform: translateY(-2px);
    border-color: var(--reveal-accent-2);
    box-shadow: 0 16px 34px -18px var(--reveal-accent, rgba(124, 58, 237, 0.5));
}

.module-reveal:active {
    transform: translateY(0);
}

.module-reveal__aura {
    position: absolute;
    top: -35%;
    left: 50%;
    width: 16rem;
    height: 16rem;
    transform: translateX(-50%);
    border-radius: 50%;
    background: radial-gradient(circle, var(--reveal-tint) 0%, transparent 68%);
    opacity: 0.9;
    pointer-events: none;
    animation: reveal-aura 3.6s ease-in-out infinite;
}

@keyframes reveal-aura {
    0%,
    100% {
        transform: translateX(-50%) scale(1);
        opacity: 0.7;
    }
    50% {
        transform: translateX(-50%) scale(1.14);
        opacity: 1;
    }
}

.module-reveal__icon {
    position: relative;
    width: 3.1rem;
    height: 3.1rem;
    display: grid;
    place-items: center;
    border-radius: 1rem;
    color: #fff;
    font-size: 1.3rem;
    background: linear-gradient(135deg, var(--reveal-accent) 0%, var(--reveal-accent-2) 100%);
    box-shadow: 0 10px 22px -10px var(--reveal-accent);
}

.module-reveal__title {
    position: relative;
    font-size: 0.98rem;
    font-weight: 950;
    color: var(--hcis-slate-900);
    letter-spacing: -0.01em;
}

.module-reveal__desc {
    position: relative;
    max-width: 20rem;
    font-size: 0.72rem;
    font-weight: 650;
    line-height: 1.45;
    color: var(--hcis-slate-500);
}

.module-reveal__cta {
    position: relative;
    margin-top: 0.35rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1.05rem;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 850;
    color: #fff;
    background: linear-gradient(135deg, var(--reveal-accent) 0%, var(--reveal-accent-2) 100%);
    box-shadow: 0 8px 18px -8px var(--reveal-accent);
    transition: transform 180ms ease;
}

.module-reveal:hover .module-reveal__cta {
    transform: scale(1.04);
}

.module-reveal__cta i {
    font-size: 0.82rem;
}

@media (prefers-reduced-motion: reduce) {
    .module-reveal__aura {
        animation: none;
    }
}

.module-card h2,
.panel-head h2 {
    margin: 0.06rem 0 0;
    font-size: 0.86rem;
    font-weight: 950;
    color: var(--hcis-slate-900);
}

.module-icon {
    flex: 0 0 auto;
    width: 2.2rem;
    height: 2.2rem;
    display: grid;
    place-items: center;
    border-radius: 0.72rem;
}

.module-icon.is-purple {
    background: #ede9fe;
    color: var(--hcis-violet-600);
}

.module-icon.is-teal {
    background: #ccfbf1;
    color: #0f766e;
}

.module-icon.is-orange {
    background: var(--hcis-orange-100);
    color: var(--hcis-orange-600);
}

.module-icon.is-indigo {
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-600);
}

.mini-badge {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 1.35rem;
    padding: 0.24rem 0.5rem;
    border-radius: 999px;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    font-size: 0.64rem;
    font-weight: 950;
}

.mini-badge.is-danger {
    background: var(--hcis-red-100);
    color: var(--hcis-red-600);
}

.mini-badge.is-success {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}

.mini-badge.is-orange {
    background: var(--hcis-orange-100);
    color: var(--hcis-orange-600);
}

.hcis-insight-card {
    grid-column: 1 / -1;
}

.leader-summary-split {
    display: grid;
    grid-template-columns: minmax(120px, 0.72fr) minmax(0, 1.28fr);
    gap: 0.5rem;
    min-width: 0;
}

.leader-score-card {
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 6.6rem;
    padding: 0.72rem;
    border-radius: 0.86rem;
    border: 1px solid var(--hcis-slate-200);
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.95), transparent 4rem),
        linear-gradient(135deg, var(--hcis-slate-50), var(--hcis-white));
    text-align: left;
}

.leader-score-card span,
.leader-score-card small {
    display: block;
    color: var(--hcis-slate-500);
    font-size: 0.65rem;
    font-weight: 900;
}

.leader-score-card strong {
    display: block;
    margin: 0.18rem 0;
    color: var(--hcis-slate-900);
    font-size: 1.44rem;
    font-weight: 950;
    line-height: 1;
    letter-spacing: -0.04em;
}

.leader-score-card.is-hcis {
    background:
        radial-gradient(circle at top right, rgba(14, 165, 233, 0.15), transparent 5rem),
        linear-gradient(135deg, var(--hcis-slate-50), var(--hcis-white));
}

.leader-score-card.is-clickable {
    cursor: pointer;
}

.leader-score-card.is-clickable:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.08);
}

.leader-mini-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.38rem;
    min-width: 0;
}

.leader-mini-stat-grid > div {
    min-width: 0;
    padding: 0.48rem;
    border-radius: 0.7rem;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
}

.leader-mini-stat-grid span,
.leader-mini-stat-grid small {
    display: block;
    overflow: hidden;
    color: var(--hcis-slate-500);
    font-size: 0.5rem;
    font-weight: 850;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.leader-mini-stat-grid strong {
    display: block;
    margin: 0.08rem 0;
    color: var(--hcis-slate-900);
    font-size: 0.95rem;
    font-weight: 950;
}

.leader-mini-stat-grid .is-danger {
    background: var(--hcis-red-50);
    border-color: var(--hcis-red-200);
}

.leader-mini-stat-grid .is-warning {
    background: var(--hcis-amber-50);
    border-color: var(--hcis-amber-200);
}

.leader-mini-stat-grid .is-orange {
    background: #fff7ed;
    border-color: #fed7aa;
}

.leader-mini-stat-grid .is-success {
    background: var(--hcis-green-50);
    border-color: var(--hcis-green-200);
}

.leader-mini-stat-grid .is-info {
    background: #eff6ff;
    border-color: #bfdbfe;
}

.line-chart-card {
    min-width: 0;
    margin-top: 0.55rem;
    padding: 0.58rem;
    border-radius: 0.86rem;
    border: 1px solid var(--hcis-slate-200);
    background: linear-gradient(180deg, var(--hcis-white), var(--hcis-slate-50));
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
    color: var(--hcis-slate-900);
    font-size: 0.66rem;
    font-weight: 950;
}

.line-chart-card__head span,
.line-chart-card__head small {
    color: var(--hcis-slate-500);
    font-size: 0.62rem;
    font-weight: 850;
}

.leader-apex-chart {
    position: relative;
    margin-top: 0.1rem;
    border-radius: 0.72rem;
    background:
        linear-gradient(180deg, rgba(248, 250, 252, 0.8), rgba(241, 245, 249, 0.86)),
        repeating-linear-gradient(90deg, transparent 0 16%, rgba(148, 163, 184, 0.1) 16% 16.2%);
}

.leader-apex-chart :deep(.apexcharts-canvas),
.leader-apex-chart :deep(.apexcharts-svg) {
    width: 100% !important;
}

.leader-apex-chart :deep(.apexcharts-marker) {
    cursor: pointer;
}

:deep(.hcis-trend-tooltip) {
    display: flex;
    flex-direction: column;
    gap: 0.34rem;
    min-width: 10.5rem;
    padding: 0.72rem 0.8rem;
    color: var(--hcis-slate-200);
    font-size: 0.66rem;
    font-weight: 700;
}

:deep(.hcis-trend-tooltip__title) {
    font-size: 0.66rem;
    font-weight: 950;
    color: var(--hcis-slate-50);
    padding-bottom: 0.3rem;
    margin-bottom: 0.12rem;
    border-bottom: 1px solid rgba(148, 163, 184, 0.25);
}

:deep(.hcis-trend-tooltip__row) {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.7rem;
}

:deep(.hcis-trend-tooltip__row span) {
    color: var(--hcis-slate-300);
    font-size: 0.66rem;
    font-weight: 700;
}

:deep(.hcis-trend-tooltip__row strong) {
    font-weight: 950;
    color: var(--hcis-slate-100);
    white-space: nowrap;
}

:deep(.hcis-trend-tooltip__row.is-muted) {
    color: var(--hcis-slate-400);
}

:deep(.hcis-trend-tooltip__row.is-muted strong) {
    color: var(--hcis-slate-400);
}

:deep(.hcis-trend-tooltip__row.is-deduction),
:deep(.hcis-trend-tooltip__row.is-deduction strong) {
    color: var(--hcis-red-300);
}

:deep(.hcis-trend-tooltip__row.is-net strong) {
    color: #93c5fd;
}

:deep(.hcis-trend-tooltip__row.is-sah),
:deep(.hcis-trend-tooltip__row.is-sah strong) {
    color: #86efac;
    font-weight: 950;
}

:deep(.hcis-trend-tooltip__note) {
    display: block;
    margin-top: 0.1rem;
    padding-top: 0.28rem;
    border-top: 1px solid rgba(148, 163, 184, 0.18);
    color: var(--hcis-slate-400);
    font-size: 0.56rem;
    font-weight: 600;
    line-height: 1.45;
}

.leader-skeleton-line,
.skeleton-avatar {
    background: linear-gradient(90deg, var(--hcis-slate-100) 0%, var(--hcis-slate-200) 20%, var(--hcis-slate-100) 40%, var(--hcis-slate-100) 100%);
    background-size: 200% 100%;
    animation: leaderSkeletonPulse 1.5s linear infinite;
}

.leader-skeleton-line {
    width: 100%;
    height: 0.6rem;
    border-radius: 999px;
}

.leader-skeleton-line.is-title {
    height: 0.8rem;
    width: 70%;
}

.leader-skeleton-line.is-short {
    width: 45%;
}

.is-skeleton {
    pointer-events: none !important;
    position: relative;
    overflow: hidden;
}

.is-skeleton .leader-skeleton-line,
.is-skeleton.leader-skeleton-line {
    opacity: 0.95;
}

.module-skeleton {
    display: grid;
    gap: 0.5rem;
    align-content: start;
    min-width: 0;
}

.mobile-hero-skeleton {
    min-width: 0;
    display: grid;
    gap: 0.22rem;
    align-content: start;
}

.mobile-status-pill.is-skeleton {
    background: rgba(255, 255, 255, 0.2);
    color: rgba(255, 255, 255, 0.7);
}

.mobile-hero__note.is-skeleton,
.brief-note.is-skeleton,
.module-alert.is-skeleton {
    display: flex;
    align-items: center;
    gap: 0.44rem;
}

.mobile-time-card.is-skeleton,
.today-chip.is-skeleton,
.brief-grid .is-skeleton,
.leader-mini-stat-grid .is-skeleton,
.kpi-stat-grid .is-skeleton,
.lms-stat-grid .is-skeleton,
.overtime-summary-grid .is-skeleton,
.summary-pill.is-skeleton,
.calendar-day.is-skeleton,
.mobile-mini-calendar__day.is-skeleton,
.submission-item.is-skeleton,
.schedule-item.is-skeleton,
.dm-timeline__item.is-skeleton,
.dm-item.is-skeleton,
.dm-raw-item.is-skeleton {
    background: var(--hcis-slate-50) !important;
    border-color: var(--hcis-slate-200) !important;
    color: var(--hcis-slate-500);
}

.brief-grid .is-skeleton,
.leader-mini-stat-grid .is-skeleton,
.kpi-stat-grid .is-skeleton,
.lms-stat-grid .is-skeleton,
.overtime-summary-grid .is-skeleton,
.kpi-status-panel .is-skeleton,
.summary-pill.is-skeleton,
.calendar-day.is-skeleton,
.mobile-mini-calendar__day.is-skeleton,
.dm-item.is-skeleton {
    display: grid;
    align-content: start;
    gap: 0.22rem;
}

.today-chip.is-skeleton,
.workday-brief-card .is-skeleton,
.mobile-hero .is-skeleton {
    background: rgba(255, 255, 255, 0.16) !important;
    border-color: rgba(255, 255, 255, 0.18) !important;
}

.today-chip.is-skeleton .leader-skeleton-line,
.workday-brief-card .leader-skeleton-line,
.mobile-hero .leader-skeleton-line {
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0.2) 0%,
        rgba(255, 255, 255, 0.42) 24%,
        rgba(255, 255, 255, 0.2) 48%,
        rgba(255, 255, 255, 0.2) 100%
    );
    background-size: 200% 100%;
}

.module-skeleton .chart-block {
    margin-top: 0.1rem;
}

.lms-next-card .lms-progress-ring.skeleton-avatar,
.lms-progress-ring.skeleton-avatar {
    background: linear-gradient(90deg, var(--hcis-slate-100) 0%, var(--hcis-slate-200) 20%, var(--hcis-slate-100) 40%, var(--hcis-slate-100) 100%) !important;
    background-size: 200% 100% !important;
    color: transparent;
}

.is-skeleton-grid {
    opacity: 0.98;
}

.schedule-item.is-skeleton .schedule-date {
    min-height: 2.15rem;
    border-radius: 0.62rem;
}

.submission-list.is-skeleton-list,
.schedule-list.is-skeleton-list {
    width: 100%;
}

.module-icon.is-info {
    background: var(--hcis-sky-100);
    color: var(--hcis-sky-600);
}

.period-badge {
    flex: 0 0 auto;
    max-width: 9rem;
    padding: 0.28rem 0.52rem;
    border-radius: 999px;
    font-size: 0.64rem;
    font-weight: 950;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.period-badge.is-purple {
    background: #ede9fe;
    color: var(--hcis-violet-600);
}

.period-badge.is-teal {
    background: #ccfbf1;
    color: #0f766e;
}

.period-badge.is-orange {
    background: var(--hcis-orange-100);
    color: var(--hcis-orange-600);
}

.kpi-warning-list {
    display: grid;
    gap: 0.35rem;
    margin-bottom: 0.45rem;
}

.inline-warning {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.48rem 0.5rem;
    border-radius: 0.66rem;
    font-size: 0.68rem;
    font-weight: 850;
}

.inline-warning.is-warning {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-800);
}

.inline-warning.is-info {
    background: var(--hcis-sky-100);
    color: #075985;
}

.inline-warning.is-success {
    background: var(--hcis-green-100);
    color: #166534;
}

.inline-warning.is-danger {
    background: var(--hcis-red-100);
    color: var(--hcis-red-800);
}

.kpi-detail-stack {
    display: grid;
    gap: 0.55rem;
    min-width: 0;
}

.kpi-control-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.42rem;
    min-width: 0;
}

.kpi-control-row label {
    display: grid;
    gap: 0.18rem;
    min-width: 0;
}

.kpi-control-row span {
    color: var(--hcis-slate-500);
    font-size: 0.64rem;
    font-weight: 900;
}

.kpi-control-row select {
    width: 100%;
    min-width: 0;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.62rem;
    background: var(--hcis-white);
    color: var(--hcis-slate-900);
    font-size: 0.7rem;
    font-weight: 850;
    padding: 0.44rem 0.5rem;
}

.kpi-control-row select:disabled {
    cursor: wait;
    opacity: 0.72;
}

.kpi-refresh-state {
    min-width: 0;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: center;
    gap: 0.42rem;
    padding: 0.48rem 0.55rem;
    border: 1px solid #ddd6fe;
    border-radius: 0.66rem;
    background: linear-gradient(135deg, var(--hcis-violet-50), var(--hcis-indigo-50));
    color: #5b21b6;
}

.kpi-refresh-state i {
    font-size: 0.78rem;
    animation: spin-soft 0.9s linear infinite;
}

.kpi-refresh-state span {
    min-width: 0;
    overflow: hidden;
    font-size: 0.68rem;
    font-weight: 900;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.kpi-refresh-state b {
    grid-column: 1 / -1;
    display: block;
    height: 0.28rem;
    border-radius: 999px;
    background: linear-gradient(90deg, #ddd6fe 0%, #8b5cf6 50%, #ddd6fe 100%);
    background-size: 200% 100%;
    animation: loading-shimmer 1.2s ease-in-out infinite;
}

.kpi-flow-title,
.kpi-performance-panel {
    min-width: 0;
    padding: 0.52rem;
    border-radius: 0.72rem;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
}

.kpi-flow-title {
    display: grid;
    gap: 0.18rem;
}

.kpi-flow-title > div,
.kpi-performance-panel {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.6rem;
}

.kpi-flow-title span,
.kpi-flow-title small,
.kpi-performance-panel span,
.kpi-performance-panel small {
    display: block;
    color: var(--hcis-slate-500);
    font-size: 0.64rem;
    font-weight: 850;
    line-height: 1.25;
}

.kpi-flow-title strong,
.kpi-performance-panel strong {
    display: block;
    color: var(--hcis-slate-900);
    font-size: 0.78rem;
    font-weight: 950;
    line-height: 1.2;
}

.kpi-performance-panel > div {
    min-width: 0;
}

.kpi-status-panel {
    min-width: 0;
    padding: 0.52rem;
    border-radius: 0.72rem;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.42rem;
}

.kpi-stat-grid > .kpi-status-panel {
    grid-column: 1 / -1;
}

.kpi-status-panel__item {
    min-width: 0;
}

.kpi-status-panel__item span,
.kpi-status-panel__item small {
    display: block;
    color: var(--hcis-slate-500);
    font-size: 0.64rem;
    font-weight: 850;
    line-height: 1.25;
}

.kpi-status-panel__item strong {
    display: block;
    color: var(--hcis-slate-900);
    font-size: 0.78rem;
    font-weight: 950;
    line-height: 1.2;
}

.kpi-status-panel__item strong.is-success {
    color: #166534;
}

.kpi-status-panel__item strong.is-warning {
    color: var(--hcis-amber-800);
}

.kpi-status-panel__item strong.is-muted {
    color: var(--hcis-slate-400);
}

/* ── KPI Filter Row (compact selects in right panel) ── */
.kpi-filter-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.28rem;
    min-width: 0;
}

.kpi-filter-row select {
    width: 100%;
    min-width: 0;
    padding: 0.36rem 1.4rem 0.36rem 0.5rem;
    border: 1px solid #ddd6fe;
    border-radius: 0.55rem;
    background: #faf5ff
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%237c3aed'/%3E%3C/svg%3E")
        no-repeat right 0.42rem center / 8px 5px;
    -webkit-appearance: none;
    appearance: none;
    color: #4c1d95;
    font-size: 0.66rem;
    font-weight: 900;
    cursor: pointer;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.kpi-filter-row select:disabled {
    opacity: 0.55;
    cursor: wait;
}

/* ── KPI Hero ─────────────────────────────── */
.kpi-hero {
    display: grid;
    grid-template-columns: 120px minmax(0, 1fr);
    gap: 0.42rem;
    min-width: 0;
}

.kpi-hero__info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.36rem;
    align-content: stretch;
    min-width: 0;
}

.kpi-hero__stat {
    min-width: 0;
    padding: 0.46rem;
    border-radius: 0.66rem;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
}

.kpi-hero__stat span {
    display: block;
    color: var(--hcis-slate-500);
    font-size: 0.62rem;
    font-weight: 850;
}

.kpi-hero__stat strong {
    display: block;
    margin-top: 0.08rem;
    color: var(--hcis-slate-900);
    font-size: 0.82rem;
    font-weight: 950;
    line-height: 1.1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.kpi-hero__stat small {
    display: block;
    color: var(--hcis-slate-400);
    font-size: 0.6rem;
    font-weight: 800;
    margin-top: 0.06rem;
}

/* ── KPI Status Row ───────────────────────── */
.kpi-status-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.34rem;
}

.kpi-status-pill {
    display: flex;
    align-items: center;
    gap: 0.36rem;
    padding: 0.34rem 0.42rem;
    border-radius: 0.65rem;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    min-width: 0;
}

.kpi-status-pill > i {
    flex: 0 0 auto;
    font-size: 0.78rem;
    color: var(--hcis-slate-400);
}

.kpi-status-pill > div {
    min-width: 0;
}

.kpi-status-pill span {
    display: block;
    color: var(--hcis-slate-500);
    font-size: 0.6rem;
    font-weight: 850;
}

.kpi-status-pill strong {
    display: block;
    color: var(--hcis-slate-900);
    font-size: 0.68rem;
    font-weight: 950;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.kpi-status-pill.is-success {
    background: var(--hcis-green-50);
    border-color: var(--hcis-green-200);
}

.kpi-status-pill.is-success > i {
    color: var(--hcis-green-600);
}

.kpi-status-pill.is-warning {
    background: var(--hcis-amber-50);
    border-color: var(--hcis-amber-200);
}

.kpi-status-pill.is-warning > i {
    color: var(--hcis-amber-600);
}

.kpi-status-pill.is-info {
    background: var(--hcis-sky-50);
    border-color: var(--hcis-sky-200);
}

.kpi-status-pill.is-info > i {
    color: var(--hcis-sky-600);
}

/* ── KPI List Header ──────────────────────── */
.kpi-list-header {
    display: flex;
    align-items: center;
    gap: 0.28rem;
    margin-bottom: -0.2rem; /* offset grid gap so label hugs the list */
}

.kpi-list-header > i {
    font-size: 0.68rem;
    color: var(--hcis-violet-600);
}

.kpi-list-header > span {
    color: var(--hcis-slate-600);
    font-size: 0.62rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* ── KPI Parameter Dot ────────────────────── */
.kpi-param-name {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    min-width: 0;
    flex: 1;
}

.kpi-param-dot {
    flex: 0 0 auto;
    width: 0.44rem;
    height: 0.44rem;
    border-radius: 999px;
    background: var(--hcis-slate-300);
}

.kpi-param-dot.is-excellent {
    background: #22c55e;
    box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.22);
}

.kpi-param-dot.is-good {
    background: var(--hcis-blue-500);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.22);
}

.kpi-param-dot.is-low {
    background: var(--hcis-amber-500);
    box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.22);
}

.kpi-param-dot.is-idle {
    background: var(--hcis-slate-300);
}

/* override shared margin-top inside grid container */
.kpi-detail-stack .mini-parameter-list {
    margin-top: 0;
}

/* keep existing kpi-layout for mobile compatibility */
.kpi-layout {
    display: grid;
    grid-template-columns: 134px minmax(0, 1fr);
    gap: 0.45rem;
    min-width: 0;
}

.kpi-score-card {
    min-width: 0;
    min-height: 7rem;
    border-radius: 0.8rem;
    display: grid;
    place-items: center;
    align-content: center;
    gap: 0.24rem;
    padding: 0.78rem 0.68rem;
    background: linear-gradient(135deg, var(--hcis-violet-700) 0%, var(--hcis-indigo-600) 55%, var(--hcis-indigo-700) 100%);
    color: #fff;
    box-shadow: 0 18px 32px rgba(67, 56, 202, 0.2);
    text-align: center;
}

.kpi-score-card strong {
    font-size: 1.65rem;
    font-weight: 950;
    line-height: 1;
}

.kpi-score-card span,
.kpi-score-card small {
    display: block;
    margin-top: 0.08rem;
    font-size: 0.56rem;
    font-weight: 850;
    opacity: 0.9;
}

.kpi-score-card__mini {
    width: 100%;
    margin-top: 0.2rem;
    padding: 0.45rem 0.4rem 0.38rem;
    border-radius: 0.72rem;
    background: rgba(255, 255, 255, 0.12);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.14);
    display: grid;
    gap: 0.06rem;
    text-align: center;
}

.kpi-score-card__mini span {
    display: block;
    font-size: 0.62rem;
    font-weight: 900;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    opacity: 0.88;
}

.kpi-score-card__mini strong {
    display: block;
    font-size: 1rem;
    font-weight: 950;
    line-height: 1;
}

.kpi-grade-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.3rem;
    padding: 0.18rem 0;
}

.kpi-grade-row + .kpi-grade-row {
    border-top: 1px solid rgba(255, 255, 255, 0.12);
}

.kpi-grade-row span {
    font-size: 0.56rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.8;
    flex-shrink: 0;
}

.kpi-grade-row strong {
    font-size: 0.72rem;
    font-weight: 950;
    line-height: 1;
    text-align: right;
}

.kpi-score-card__mini small {
    display: block;
    font-size: 0.6rem;
    font-weight: 800;
    opacity: 0.82;
}

.kpi-stat-grid,
.lms-stat-grid,
.overtime-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.38rem;
    min-width: 0;
}

.kpi-stat-grid > div,
.lms-stat-grid > div,
.overtime-summary-grid > div {
    min-width: 0;
    padding: 0.46rem;
    border-radius: 0.66rem;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
}

.kpi-stat-grid span,
.kpi-stat-grid small,
.lms-stat-grid span,
.lms-stat-grid small,
.overtime-summary-grid span,
.overtime-summary-grid small,
.lms-main-progress span,
.lms-main-progress small {
    display: block;
    color: var(--hcis-slate-500);
    font-size: 0.64rem;
    font-weight: 850;
}

.kpi-stat-grid strong,
.lms-stat-grid strong,
.overtime-summary-grid strong,
.lms-main-progress strong {
    display: block;
    margin-top: 0.08rem;
    color: var(--hcis-slate-900);
    font-size: 0.72rem;
    font-weight: 950;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mini-parameter-list,
.training-list {
    display: grid;
    margin-top: 0.48rem;
    min-width: 0;
}

.mini-parameter-list {
    gap: 0.34rem;
    max-height: 9.5rem;
    overflow-y: auto;
    padding-right: 0.2rem;
    scrollbar-width: thin;
    scrollbar-color: #ddd6fe transparent;
}

.mini-parameter-list::-webkit-scrollbar {
    width: 3px;
}

.mini-parameter-list::-webkit-scrollbar-track {
    background: transparent;
}

.mini-parameter-list::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: #ddd6fe;
}

.training-list {
    gap: 0.2rem;
    overflow: auto;
    align-content: start;
    align-items: start;
    height: 7.5rem;

    scrollbar-width: thin;
    scrollbar-color: #99f6e4 transparent;
}

.training-list::-webkit-scrollbar {
    width: 3px;
}

.training-list::-webkit-scrollbar-track {
    background: transparent;
}

.training-list::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: #99f6e4;
}

.mini-parameter-list > div,
.training-item {
    min-width: 0;
}

.mini-parameter-list > div > div:first-child,
.training-item__top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
}

.mini-parameter-list span,
.training-item span {
    min-width: 0;
    color: var(--hcis-slate-600);
    font-size: 0.65rem;
    font-weight: 850;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mini-parameter-list strong,
.training-item strong {
    flex: 0 0 auto;
    color: var(--hcis-slate-900);
    font-size: 0.65rem;
    font-weight: 950;
}

.mini-parameter-meta {
    display: block;
    margin-top: 0.1rem;
    color: var(--hcis-slate-500);
    font-size: 0.62rem;
    font-weight: 800;
    line-height: 1.25;
}

.training-item {
    padding: 0.26rem 0.4rem;
    border-radius: 0.5rem;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
}

.training-item-placeholder {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.36rem 0.5rem;
    border-radius: 0.5rem;
    border: 1px dashed var(--hcis-slate-300);
    background: rgba(248, 250, 252, 0.45);
    color: var(--hcis-slate-400);
    font-size: 0.62rem;
    font-weight: 500;
}

.placeholder-dot {
    width: 0.44rem;
    height: 0.44rem;
    border-radius: 999px;
    border: 1px dashed var(--hcis-slate-300);
    background: transparent;
}

.training-item__top > div,
.training-item__body > div {
    min-width: 0;
}

.training-item__top strong {
    display: block;
    flex: unset;
    margin-bottom: 0.04rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.training-item__top small {
    display: inline-block;
    padding: 0.16rem 0.34rem;
    border-radius: 999px;
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
    font-size: 0.62rem;
    font-weight: 950;
}

.training-item__top small.is-complete {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}

.mini-progress {
    width: 100%;
    height: 0.35rem;
    margin-top: 0.16rem;
    border-radius: 999px;
    background: var(--hcis-slate-300);
    overflow: hidden;
}

.mini-progress.is-large {
    height: 0.48rem;
}

.mini-progress__fill {
    height: 100%;
    border-radius: inherit;
}

.mini-progress__fill.is-purple {
    background: linear-gradient(90deg, #8b5cf6, var(--hcis-indigo-600));
}

.mini-progress__fill.is-teal {
    background: linear-gradient(90deg, #14b8a6, var(--hcis-cyan-500));
}

.lms-next-card {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 0.62rem;
    align-items: stretch;
    min-width: 0;
    padding: 0.55rem;
    border-radius: 0.75rem;
    background: transparent;
    border: none;
    margin-bottom: 0.48rem;
}

.lms-next-card__info,
.lms-progress-card {
    min-width: 0;
}

.lms-next-card__info {
    padding: 0.62rem 0.68rem;
    border-radius: 0.85rem;
    background: #f0fdfa;
    border: 1px solid #ccfbf1;
}

.lms-progress-card {
    display: grid;
    place-items: center;
    padding: 0.24rem;
    border-radius: 0.85rem;
    background: var(--hcis-cyan-50);
    border: 1px solid #99f6e4;
    box-shadow: 0 12px 24px rgba(20, 184, 166, 0.12);
}

.lms-next-card span,
.lms-next-card small {
    display: block;
    color: #0f766e;
    font-size: 0.64rem;
    font-weight: 850;
}

.lms-next-card strong {
    display: block;
    margin-top: 0.12rem;
    color: var(--hcis-slate-900);
    font-size: 0.78rem;
    font-weight: 950;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lms-progress-ring {
    flex: 0 0 auto;
    width: 4.1rem;
    height: 4.1rem;
    display: grid;
    place-items: center;
    align-content: center;
    gap: 0.08rem;
    border-radius: 999px;
    background: linear-gradient(135deg, #14b8a6, var(--hcis-cyan-500));
    color: white;
    font-weight: 950;
    text-align: center;
    box-shadow: 0 14px 26px rgba(20, 184, 166, 0.22);
}

.lms-progress-ring span {
    display: block;
    font-size: 0.58rem;
    font-weight: 900;
    line-height: 1;
    letter-spacing: 0.05em;
    opacity: 0.9;
    text-transform: uppercase;
}

.lms-progress-ring strong {
    display: block;
    margin-top: 0.02rem;
    color: inherit;
    font-size: 0.96rem;
    font-weight: 950;
    line-height: 1;
}

.lms-stat-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin-top: 0.48rem;
}

/* ── LMS Hero ─────────────────────────────── */
.lms-hero {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.55rem 0.65rem;
    border-radius: 0.85rem;
    background: linear-gradient(135deg, #f0fdfa, var(--hcis-cyan-50));
    border: 1px solid #ccfbf1;
    min-width: 0;
    margin-bottom: 0.42rem;
}

.lms-hero .lms-progress-ring {
    width: 3.5rem;
    height: 3.5rem;
}

.lms-hero__info {
    display: grid;
    gap: 0.26rem;
    min-width: 0;
    flex: 1;
}

.lms-hero__top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
}

.lms-hero__top > span {
    color: #0f766e;
    font-size: 0.57rem;
    font-weight: 850;
    min-width: 0;
}

.lms-hero__badge {
    flex: 0 0 auto;
    font-size: 0.64rem !important;
    font-weight: 950 !important;
    color: #0f766e !important;
    background: rgba(20, 184, 166, 0.16);
    padding: 0.12rem 0.44rem;
    border-radius: 999px;
}

.lms-hero__info > small {
    color: var(--hcis-slate-500);
    font-size: 0.62rem;
    font-weight: 800;
}

/* ── LMS Quick Stats ──────────────────────── */
.lms-quick-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.34rem;
    margin-bottom: 0.42rem;
}

.lms-stat-pill {
    display: flex;
    align-items: center;
    gap: 0.38rem;
    padding: 0.36rem 0.44rem;
    border-radius: 0.65rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    min-width: 0;
}

.lms-stat-pill > i {
    flex: 0 0 auto;
    font-size: 0.8rem;
    color: #0f766e;
}

.lms-stat-pill > div {
    min-width: 0;
}

.lms-stat-pill span {
    display: block;
    color: var(--hcis-slate-500);
    font-size: 0.62rem;
    font-weight: 850;
}

.lms-stat-pill strong {
    display: block;
    color: var(--hcis-slate-900);
    font-size: 0.65rem;
    font-weight: 950;
}

/* ── Next Training Highlight ──────────────── */
.lms-next-highlight {
    display: flex;
    align-items: center;
    gap: 0.42rem;
    padding: 0.38rem 0.48rem;
    margin-bottom: 0.42rem;
    border-radius: 0.65rem;
    background: var(--hcis-yellow-50);
    border: 1px solid var(--hcis-yellow-200);
    min-width: 0;
}

.lms-next-highlight > i {
    flex: 0 0 auto;
    font-size: 0.8rem;
    color: #ca8a04;
}

.lms-next-highlight__info {
    flex: 1;
    min-width: 0;
}

.lms-next-highlight__info span {
    display: block;
    color: var(--hcis-amber-800);
    font-size: 0.6rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.lms-next-highlight__info strong {
    display: block;
    color: var(--hcis-slate-900);
    font-size: 0.68rem;
    font-weight: 950;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lms-next-highlight__date {
    flex: 0 0 auto;
    color: var(--hcis-amber-800);
    font-size: 0.62rem;
    font-weight: 850;
    white-space: nowrap;
}

/* ── Training List Header ─────────────────── */
.lms-list-header {
    display: flex;
    align-items: center;
    gap: 0.28rem;
    margin-bottom: 0.3rem;
}

.lms-list-header > i {
    font-size: 0.68rem;
    color: #0f766e;
}

.lms-list-header > span {
    color: var(--hcis-slate-600);
    font-size: 0.62rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* label immediately above training-list, so reset the shared margin-top */
.lms-list-header + .training-list {
    margin-top: 0;
}

/* ── Training Item Status Dot ─────────────── */
.training-item__body {
    display: flex;
    align-items: center;
    gap: 0.36rem;
    min-width: 0;
    flex: 1;
}

.training-item__dot {
    flex: 0 0 auto;
    width: 0.44rem;
    height: 0.44rem;
    border-radius: 999px;
    background: var(--hcis-slate-300);
}

.training-item__dot.is-done {
    background: #22c55e;
    box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.2);
}

.training-item__dot.is-active {
    background: #14b8a6;
    box-shadow: 0 0 0 2px rgba(20, 184, 166, 0.2);
}

.training-item__dot.is-idle {
    background: var(--hcis-slate-300);
}

.overtime-total {
    background: linear-gradient(135deg, var(--hcis-orange-100), #fff7ed) !important;
    border-color: #fed7aa !important;
}

.overtime-total strong {
    color: var(--hcis-orange-600) !important;
    font-size: 0.98rem !important;
}

.overtime-assignment-panel {
    min-width: 0;
    display: grid;
    gap: 0.48rem;
    margin-top: 0.52rem;
    padding: 0.5rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.66rem;
    background: var(--hcis-white);
}

.overtime-assignment-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    min-width: 0;
}

.overtime-assignment-pager {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--hcis-slate-200);
}

.overtime-pager-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.85rem;
    height: 1.85rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.5rem;
    background: #fff;
    color: var(--hcis-slate-600);
    transition: background-color 0.15s ease, border-color 0.15s ease;
}

.overtime-pager-btn:hover:not(:disabled) {
    background: var(--hcis-slate-100);
    border-color: var(--hcis-slate-300);
}

/* Nonaktif harus TERLIHAT nonaktif: tombol yang tampak hidup tapi diam
   membuat orang mengira halamannya rusak, bukan mengira sudah di ujung. */
.overtime-pager-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.overtime-pager-info {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
    font-variant-numeric: tabular-nums;
}

.overtime-assignment-head span,
.overtime-assignment-head small {
    display: block;
    color: var(--hcis-slate-500);
    font-size: 0.62rem;
    font-weight: 900;
}

.overtime-assignment-head strong {
    display: block;
    margin-top: 0.08rem;
    color: var(--hcis-slate-900);
    font-size: 0.76rem;
    font-weight: 950;
}

.overtime-assignment-head small {
    flex: 0 0 auto;
    padding: 0.18rem 0.48rem;
    border-radius: 999px;
    background: var(--hcis-slate-100);
}

.overtime-assignment-list {
    display: grid;
    gap: 0.38rem;
    min-width: 0;
    max-height: 18rem;
    overflow-y: auto;
}

/* ===== Grup lembur per tanggal ===== */
.overtime-date-groups {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    max-height: none;
    overflow: visible;
}

.overtime-date-group {
    display: flex;
    flex-direction: column;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.6rem;
    background: var(--hcis-white);
    overflow: visible;
}

.overtime-date-group.is-expanded {
    border-color: var(--hcis-slate-300);
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.06);
}

.overtime-date-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.4rem;
    padding: 0.18rem 0.2rem 0.28rem;
    border-bottom: 1px dashed var(--hcis-slate-200);
}

.overtime-date-head.is-accordion {
    width: 100%;
    min-height: 2.8rem;
    border: none;
    background: none;
    cursor: pointer;
    text-align: left;
    font: inherit;
    color: inherit;
    padding: 0.46rem 0.56rem;
    transition: background 0.15s ease;
}

.overtime-date-head.is-accordion:hover {
    background: var(--hcis-slate-50);
}

.overtime-date-group.is-expanded .overtime-date-head.is-accordion {
    background: var(--hcis-slate-100);
    border-bottom: 1px solid var(--hcis-slate-200);
}

.overtime-date-head__left {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
}

.overtime-date-head__right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.overtime-accordion-chevron {
    font-size: 0.7rem;
    color: var(--hcis-slate-400);
    transition: transform 0.2s ease;
}

.overtime-date-head__badges {
    display: flex;
    align-items: center;
    gap: 0.28rem;
    flex-wrap: nowrap;
}

.overtime-type-badge {
    font-size: 0.55rem;
    font-weight: 800;
    padding: 0.12rem 0.42rem;
    border-radius: 999px;
    white-space: nowrap;
    letter-spacing: 0.01em;
    text-transform: uppercase;
}

.overtime-type-badge.is-blue {
    color: #1d4ed8;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
}

.overtime-type-badge.is-green {
    color: var(--hcis-green-700);
    background: var(--hcis-green-50);
    border: 1px solid var(--hcis-green-200);
}

.overtime-type-badge.is-purple {
    color: var(--hcis-violet-700);
    background: #faf5ff;
    border: 1px solid #ddd6fe;
}

.overtime-type-badge.is-amber {
    color: var(--hcis-amber-700);
    background: var(--hcis-amber-50);
    border: 1px solid var(--hcis-amber-200);
}

.overtime-date-head__date {
    font-size: 0.74rem;
    font-weight: 950;
    color: var(--hcis-slate-900);
}


.accordion-enter-active,
.accordion-leave-active {
    transition:
        height 0.3s cubic-bezier(0.4, 0, 0.2, 1),
        opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1),
        margin-top 0.3s cubic-bezier(0.4, 0, 0.2, 1),
        padding-top 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden !important;
}
.accordion-enter-from,
.accordion-leave-to {
    opacity: 0;
    margin-top: 0 !important;
    padding-top: 0 !important;
    border-top-color: transparent !important;
}

.overtime-date-items {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.75rem;
    max-height: none;
    overflow-y: visible;
    border: 1px solid var(--hcis-slate-200);
    border-top: none;
    border-radius: 0 0 0.75rem 0.75rem;
    background: #fafbfc;
}

.overtime-day-chip {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.24rem 0.62rem;
    border-radius: 999px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #c2410c;
    font-size: 0.66rem;
    font-weight: 950;
    cursor: help;
    white-space: nowrap;
    outline: none;
    transition: background 0.16s ease;
}

.overtime-day-chip:hover,
.overtime-day-chip:focus-visible {
    background: var(--hcis-orange-100);
}

.overtime-day-chip .overtime-info-icon {
    color: var(--hcis-orange-600);
}

/* ===== Tooltip kustom (rapi, multi-baris) ===== */
.overtime-tooltip {
    position: absolute;
    top: calc(100% + 0.5rem);
    right: 0;
    z-index: 60;
    width: 16.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.34rem;
    padding: 0.72rem 0.8rem;
    border-radius: 0.7rem;
    background: var(--hcis-slate-900);
    color: var(--hcis-slate-200);
    box-shadow: 0 14px 32px rgba(15, 23, 42, 0.28);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-0.25rem);
    transition:
        opacity 0.14s ease,
        transform 0.14s ease,
        visibility 0.14s ease;
    white-space: normal;
    text-align: left;
    pointer-events: none;
}

.overtime-tooltip::after {
    content: '';
    position: absolute;
    bottom: 100%;
    right: 1.1rem;
    border: 0.4rem solid transparent;
    border-bottom-color: var(--hcis-slate-900);
}

.overtime-day-chip:hover .overtime-tooltip,
.overtime-day-chip:focus-visible .overtime-tooltip {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.overtime-tooltip__title {
    font-size: 0.66rem;
    font-weight: 950;
    color: var(--hcis-slate-50);
    padding-bottom: 0.3rem;
    margin-bottom: 0.12rem;
    border-bottom: 1px solid rgba(148, 163, 184, 0.25);
}

.overtime-tooltip__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.7rem;
    font-size: 0.66rem;
    font-weight: 700;
    color: var(--hcis-slate-300);
}

.overtime-tooltip__row strong {
    font-weight: 950;
    color: var(--hcis-slate-100);
    white-space: nowrap;
}

.overtime-tooltip__row.is-muted {
    color: var(--hcis-slate-400);
}

.overtime-tooltip__row.is-muted strong {
    color: var(--hcis-slate-400);
}

.overtime-tooltip__row.is-deduction,
.overtime-tooltip__row.is-deduction strong {
    color: var(--hcis-red-300);
}

.overtime-tooltip__row.is-net strong {
    color: #86efac;
}

.overtime-tooltip__row.is-sah {
    margin-top: 0.18rem;
    padding-top: 0.34rem;
    border-top: 1px solid rgba(148, 163, 184, 0.25);
    font-size: 0.7rem;
}

.overtime-tooltip__row.is-sah strong {
    color: var(--hcis-orange-300);
    font-size: 0.82rem;
}

.overtime-tooltip__note {
    margin-top: 0.18rem;
    font-size: 0.58rem;
    font-weight: 600;
    line-height: 1.4;
    color: var(--hcis-slate-400);
}

.overtime-info-icon {
    color: var(--hcis-slate-400);
    cursor: help;
    font-size: 0.7rem;
}

.overtime-info-icon:hover {
    color: var(--hcis-slate-600);
}

.overtime-assignment-item.is-auto-shift {
    cursor: default;
    background: var(--hcis-slate-100);
    border-style: dashed;
}

.overtime-assignment-item.is-auto-shift:hover {
    transform: none;
    border-color: var(--hcis-slate-300);
    background: var(--hcis-slate-100);
}

.overtime-status-dot.is-info {
    background: var(--hcis-blue-500);
}

.overtime-assignment-item {
    width: 100%;
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    padding: 0.75rem 1.25rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.75rem;
    background: var(--hcis-white);
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
    color: inherit;
    text-align: left;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.overtime-assignment-item:hover {
    transform: translateY(-2px);
    border-color: #f97316;
    background: #fffaf7;
    box-shadow: 0 4px 12px 0 rgba(249, 115, 22, 0.08);
}

.overtime-assignment-item__content {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
}

.overtime-assignment-col-info {
    flex: 0 0 16rem;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.overtime-type-title {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    line-height: 1.25;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.overtime-time-range {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    color: var(--hcis-slate-500);
    font-weight: 600;
}

.overtime-source-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.68rem;
    font-weight: 700;
    background-color: var(--hcis-slate-50);
    color: var(--hcis-slate-600);
    border: 1px solid var(--hcis-slate-200);
    width: fit-content;
}

.overtime-source-badge.is-auto {
    background-color: #eff6ff;
    color: #1d4ed8;
    border-color: #dbeafe;
}

.overtime-assignment-col-reason {
    flex: 1;
    min-width: 0;
}

.overtime-card-reason {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    background-color: var(--hcis-slate-50);
    border-left: 3px solid var(--hcis-slate-300);
    border-radius: 0.25rem;
}

.overtime-card-reason i {
    font-size: 0.75rem;
    color: var(--hcis-slate-400);
    margin-top: 0.1rem;
}

.overtime-reason-text {
    font-size: 0.75rem;
    line-height: 1.4;
    color: var(--hcis-slate-600);
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.overtime-assignment-col-meta {
    flex: 0 0 11rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    justify-content: flex-end;
}

.overtime-pill-duration {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.625rem;
    background-color: var(--hcis-sky-100);
    color: var(--hcis-sky-700);
    font-size: 0.75rem;
    font-weight: 700;
    border-radius: 9999px;
    white-space: nowrap;
}

.overtime-pill-absensi {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.625rem;
    font-size: 0.75rem;
    font-weight: 700;
    border-radius: 9999px;
    background-color: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    white-space: nowrap;
}

.overtime-pill-absensi.is-success {
    background-color: var(--hcis-green-100);
    color: var(--hcis-green-700);
}

.overtime-pill-absensi.is-warning {
    background-color: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
}

.overtime-pill-absensi.is-danger {
    background-color: var(--hcis-red-100);
    color: var(--hcis-red-700);
}

.overtime-assignment-col-status {
    flex: 0 0 6.5rem;
    display: flex;
    justify-content: flex-end;
}

.overtime-status {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
}

.overtime-status-dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 9999px;
    display: inline-block;
    background: var(--hcis-amber-500);
}

.overtime-status.is-success .overtime-status-dot { background-color: var(--hcis-green-600); }
.overtime-status.is-warning .overtime-status-dot { background-color: var(--hcis-amber-500); }
.overtime-status.is-danger .overtime-status-dot { background-color: var(--hcis-red-600); }
.overtime-status.is-info .overtime-status-dot { background-color: var(--hcis-blue-500); }

.overtime-assignment-item > i {
    color: var(--hcis-slate-400);
    font-size: 1.1rem;
    transition: transform 0.2s ease, color 0.2s ease;
    flex-shrink: 0;
}

.overtime-detail {
    display: grid;
    gap: 0.8rem;
}

.overtime-detail-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem;
    border-radius: 0.8rem;
    background: linear-gradient(135deg, #f97316, var(--hcis-amber-500));
    color: #fff;
}

.overtime-detail-hero.is-success {
    background: linear-gradient(135deg, var(--hcis-emerald-600), var(--hcis-emerald-500));
}

.overtime-detail-hero.is-warning {
    background: linear-gradient(135deg, var(--hcis-orange-600), var(--hcis-amber-500));
}

.overtime-detail-hero.is-danger {
    background: linear-gradient(135deg, var(--hcis-red-600), var(--hcis-red-500));
}

.overtime-detail-hero span {
    display: inline-flex;
    padding: 0.18rem 0.5rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.2);
    font-size: 0.66rem;
    font-weight: 950;
}

.overtime-detail-hero h3 {
    margin: 0.55rem 0 0;
    font-size: 1.1rem;
    font-weight: 950;
}

.overtime-detail-hero p {
    margin: 0.2rem 0 0;
    font-size: 0.78rem;
    font-weight: 800;
    opacity: 0.86;
}

.overtime-detail-hero strong {
    flex: 0 0 auto;
    font-size: 1.15rem;
    font-weight: 950;
}

.overtime-detail-hero__source {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    margin-top: 0.35rem !important;
    padding: 0.12rem 0.5rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.45);
    font-size: 0.7rem !important;
    font-weight: 900 !important;
    opacity: 1 !important;
}

.overtime-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}

.overtime-detail-grid.is-shift {
    grid-template-columns: repeat(5, minmax(0, 1fr));
    margin-top: 0.4rem;
}

@media (max-width: 860px) {
    .overtime-detail-grid.is-shift {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.overtime-detail-card.is-highlight {
    border-color: #fb923c;
    background: #fff7ed;
}

.overtime-detail-card.is-highlight strong {
    color: #c2410c;
}

@media (max-width: 640px) {
    .overtime-detail-grid.is-shift {
        grid-template-columns: 1fr;
    }
}

.overtime-detail-card {
    min-width: 0;
    padding: 0.7rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.66rem;
    background: var(--hcis-slate-50);
}

.overtime-detail-card span,
.overtime-detail-section__head {
    display: flex;
    align-items: center;
    gap: 0.34rem;
    color: var(--hcis-slate-500);
    font-size: 0.66rem;
    font-weight: 950;
}

.overtime-detail-card strong {
    display: block;
    margin-top: 0.18rem;
    color: var(--hcis-slate-900);
    font-size: 0.78rem;
    font-weight: 900;
    overflow-wrap: anywhere;
}

.overtime-detail-section {
    display: grid;
    gap: 0.4rem;
}

.overtime-detail-status {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    padding: 0.68rem;
    border-radius: 0.66rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
}

.overtime-detail-status span {
    flex: 0 0 auto;
    padding: 0.2rem 0.52rem;
    border-radius: 999px;
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
    font-size: 0.64rem;
    font-weight: 950;
}

.overtime-detail-status span.is-success {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}

.overtime-detail-status p,
.overtime-detail-note {
    margin: 0;
    color: var(--hcis-slate-600);
    font-size: 0.74rem;
    font-weight: 750;
    line-height: 1.55;
}

.overtime-detail-note {
    padding: 0.7rem;
    border-radius: 0.66rem;
    background: var(--hcis-amber-50);
    border: 1px solid var(--hcis-amber-200);
    color: var(--hcis-amber-800);
}

/* ===== Info box Lembur Shift otomatis (modal) ===== */
.overtime-detail-autoshift {
    display: flex;
    gap: 0.6rem;
    align-items: flex-start;
    padding: 0.8rem;
    border-radius: 0.66rem;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
}

.overtime-detail-autoshift i {
    color: var(--hcis-blue-600);
    font-size: 1.1rem;
    margin-top: 0.1rem;
}

.overtime-detail-autoshift strong {
    display: block;
    color: #1e40af;
    font-size: 0.78rem;
    font-weight: 950;
    margin-bottom: 0.18rem;
}

.overtime-detail-autoshift p {
    margin: 0;
    color: #1e3a8a;
    font-size: 0.72rem;
    font-weight: 650;
    line-height: 1.5;
}

/* ===== Breakdown lembur di modal detail ===== */
.overtime-breakdown {
    display: flex;
    flex-direction: column;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.66rem;
    overflow: hidden;
}

.overtime-breakdown__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    padding: 0.56rem 0.74rem;
    font-size: 0.74rem;
    font-weight: 750;
    color: var(--hcis-slate-600);
    border-bottom: 1px solid var(--hcis-slate-100);
}

.overtime-breakdown__row:last-child {
    border-bottom: none;
}

.overtime-breakdown__row span {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.overtime-breakdown__row strong {
    color: var(--hcis-slate-900);
    font-weight: 950;
}

.overtime-breakdown__row.is-deduction {
    background: var(--hcis-red-50);
    color: var(--hcis-red-700);
}

.overtime-breakdown__row.is-deduction strong {
    color: var(--hcis-red-600);
}

.overtime-breakdown__row.is-total {
    background: var(--hcis-green-50);
}

.overtime-breakdown__row.is-total strong {
    color: var(--hcis-green-700);
}

.overtime-breakdown__row.is-koef {
    background: #fff7ed;
}

.overtime-breakdown__row.is-koef strong {
    color: var(--hcis-orange-600);
}

.module-alert {
    display: flex;
    align-items: center;
    gap: 0.36rem;
    margin-top: 0.5rem;
    padding: 0.48rem;
    border-radius: 0.66rem;
    font-size: 0.6rem;
    font-weight: 850;
}

.module-alert.is-info {
    background: var(--hcis-sky-100);
    color: var(--hcis-sky-700);
}

.chart-block {
    min-width: 0;
    margin-top: 0.52rem;
    padding: 0.42rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.7rem;
    background: var(--hcis-slate-50);
    overflow: hidden;
}

.chart-safe {
    width: 100%;
    min-width: 0;
    overflow: visible;
}

.chart-safe :deep(.apexcharts-canvas),
.chart-safe :deep(svg),
.chart-safe :deep(foreignObject) {
    max-width: 100% !important;
}

.chart-head {
    display: flex;
    justify-content: space-between;
    gap: 0.55rem;
    align-items: center;
    margin-bottom: 0.22rem;
    min-width: 0;
}

.chart-head span,
.chart-head strong {
    min-width: 0;
    font-size: 0.6rem;
    font-weight: 900;
    color: var(--hcis-slate-600);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.chart-head strong {
    flex: 0 0 auto;
    color: var(--hcis-slate-900);
}

.calendar-filters {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.overtime-filters {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}

/* Di header desktop select duduk di pojok kanan (menggantikan period-badge). Di
   body accordion mobile dia berdiri sendiri di atas grid dengan jarak bawah. */
.mobile-accordion__body .overtime-filters {
    margin-bottom: 0.75rem;
    justify-content: flex-start;
}

.overtime-filters__hint {
    font-size: 0.62rem;
    font-weight: 800;
    color: var(--hcis-amber-700);
}

.cutoff-period-select {
    min-height: 1.9rem;
    max-width: 12rem;
    padding: 0 1.8rem 0 0.62rem;
    border: 1px solid var(--hcis-slate-300);
    border-radius: 999px;
    background: #fff;
    color: var(--hcis-slate-900);
    font-size: 0.62rem;
    font-weight: 900;
    cursor: pointer;
    transition: border-color 140ms ease;
}

.cutoff-period-select:hover:not(:disabled) {
    border-color: var(--hcis-slate-400);
}

.cutoff-period-select:disabled {
    cursor: wait;
    color: var(--hcis-slate-400);
    background: var(--hcis-slate-50);
    opacity: 0.7;
}

.filter-chip {
    min-height: 1.9rem;
    padding: 0 0.58rem;
    border-radius: 999px;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-600);
    font-size: 0.62rem;
    font-weight: 900;
    cursor: pointer;
    transition:
        background 140ms ease,
        border-color 140ms ease,
        color 140ms ease,
        box-shadow 140ms ease;
}

.filter-chip:hover:not(.is-active) {
    background: var(--hcis-slate-100);
    border-color: var(--hcis-slate-300);
    color: var(--hcis-slate-700);
}

.filter-chip:active {
    transform: scale(0.96);
}

.filter-chip.is-active {
    color: #fff;
    background: var(--hcis-indigo-600);
    border-color: var(--hcis-indigo-700);
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.22);
}

.attendance-summary-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(7.2rem, 1fr));
    gap: 0.36rem;
    margin-bottom: 0.52rem;
}

.summary-pill {
    min-width: 0;
    padding: 0.5rem 0.42rem;
    border-radius: 0.78rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    text-align: center;
}

.summary-pill strong {
    display: block;
    font-size: 0.9rem;
    font-weight: 950;
    line-height: 1;
}

.summary-pill span {
    display: block;
    margin-top: 0.16rem;
    font-size: 0.62rem;
    font-weight: 850;
    color: var(--hcis-slate-500);
    line-height: 1.25;
}

.summary-pill.is-success {
    background: linear-gradient(135deg, var(--hcis-green-100), var(--hcis-green-200));
    border-color: #86efac;
    color: var(--hcis-green-700);
}

.summary-pill.is-warning {
    background: linear-gradient(135deg, var(--hcis-amber-100), var(--hcis-amber-200));
    border-color: var(--hcis-amber-300);
    color: var(--hcis-amber-800);
}

.summary-pill.is-danger {
    background: linear-gradient(135deg, var(--hcis-red-100), var(--hcis-red-200));
    border-color: var(--hcis-red-300);
    color: var(--hcis-red-700);
}

.summary-pill.is-info {
    background: linear-gradient(135deg, var(--hcis-sky-100), var(--hcis-sky-200));
    border-color: var(--hcis-sky-300);
    color: var(--hcis-sky-700);
}

.summary-pill.is-orange {
    background: linear-gradient(135deg, var(--hcis-orange-100), #fed7aa);
    border-color: var(--hcis-orange-300);
    color: #c2410c;
}

.calendar-scroll {
    width: 100%;
    overflow-x: auto;
    padding-bottom: 0.5rem;
    -webkit-overflow-scrolling: touch;
    scroll-snap-type: x proximity;
}

.calendar-scroll::-webkit-scrollbar {
    height: 4px;
}

.calendar-scroll::-webkit-scrollbar-track {
    background: var(--hcis-slate-100);
    border-radius: 999px;
    margin: 0 0.5rem;
}

.calendar-scroll::-webkit-scrollbar-thumb {
    background: var(--hcis-slate-300);
    border-radius: 999px;
}

.calendar-scroll::-webkit-scrollbar-thumb:hover {
    background: var(--hcis-slate-400);
}

.calendar-grid {
    min-width: 720px;
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.38rem;
}

.calendar-weekday {
    padding: 0.22rem;
    color: var(--hcis-slate-500);
    font-size: 0.58rem;
    font-weight: 950;
    text-align: center;
}

.calendar-day {
    min-width: 0;
    min-height: 6.15rem;
    padding: 0.5rem;
    border-radius: 0.8rem;
    border: 1px solid var(--hcis-slate-200);
    background: #fff;
    cursor: pointer;
    text-align: left;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition:
        transform 150ms ease,
        box-shadow 150ms ease,
        border-color 150ms ease;
}

.calendar-day:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
    border-color: var(--hcis-slate-300);
}

.calendar-day:active {
    transform: scale(0.98);
    box-shadow: none;
}

.calendar-day.is-today {
    background: linear-gradient(180deg, var(--hcis-indigo-50) 0%, #f5f7ff 100%);
    border-color: var(--hcis-indigo-400);
    box-shadow:
        inset 0 0 0 1px rgba(99, 102, 241, 0.18),
        0 4px 14px rgba(79, 70, 229, 0.1);
}

.calendar-day.has-problem {
    border-color: #fed7aa;
}

.calendar-day.is-success {
    background: var(--hcis-green-50);
}

.calendar-day.is-warning {
    background: var(--hcis-amber-50);
}

.calendar-day.is-danger {
    background: #fff1f2;
}

.calendar-day.is-info {
    background: var(--hcis-sky-50);
}

.calendar-day.is-default {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-300);
}

.calendar-day__top {
    display: flex;
    flex-direction: column;
    gap: 0.22rem;
}

.calendar-day__date {
    min-width: 0;
    display: flex;
    flex-direction: row;
    align-items: baseline;
    gap: 0.26rem;
}

.calendar-day__top strong {
    color: var(--hcis-slate-900);
    font-size: 0.88rem;
    font-weight: 950;
    flex-shrink: 0;
    line-height: 1;
}

.calendar-day__weekday {
    min-width: 0;
    color: var(--hcis-slate-500);
    font-size: 0.6rem;
    font-weight: 900;
    line-height: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Status badge — direct child only, so weekday span inside __date is unaffected */
.calendar-day__top > span {
    align-self: flex-start;
    padding: 0.15rem 0.4rem;
    border-radius: 999px;
    font-size: 0.57rem;
    font-weight: 950;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    letter-spacing: 0.01em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.calendar-day__top > span.is-success {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}

.calendar-day__top > span.is-warning {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-800);
}

.calendar-day__top > span.is-danger {
    background: var(--hcis-red-100);
    color: var(--hcis-red-700);
}

.calendar-day__top > span.is-info {
    background: var(--hcis-sky-100);
    color: var(--hcis-sky-700);
}

.calendar-day__top > span.is-default {
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-600);
}

.calendar-day__shift {
    margin-top: 0.24rem;
    color: var(--hcis-slate-600);
    font-size: 0.58rem;
    font-weight: 850;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.calendar-day__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.18rem;
    margin-top: 0.26rem;
}

.calendar-day__chips span {
    display: inline-flex;
    align-items: center;
    max-width: 100%;
    padding: 0.14rem 0.32rem;
    border-radius: 999px;
    font-size: 0.58rem;
    font-weight: 950;
    line-height: 1.1;
    color: var(--hcis-slate-600);
    background: rgba(226, 232, 240, 0.85);
    white-space: nowrap;
}

.calendar-day__chips span.is-warning {
    color: var(--hcis-amber-800);
    background: rgba(254, 243, 199, 0.9);
}

.calendar-day__chips span.is-orange {
    color: #c2410c;
    background: rgba(255, 237, 213, 0.94);
}

.calendar-day__chips span.is-danger {
    color: var(--hcis-red-700);
    background: rgba(254, 226, 226, 0.94);
}

.calendar-day__chips span.is-info {
    color: var(--hcis-sky-700);
    background: rgba(224, 242, 254, 0.94);
}

.calendar-day__chips span.is-default {
    color: var(--hcis-slate-600);
    background: rgba(226, 232, 240, 0.94);
}

.calendar-day__times {
    display: grid;
    gap: 0.18rem;
    margin-top: 0.28rem;
    padding-top: 0.28rem;
    border-top: 1px solid rgba(226, 232, 240, 0.7);
}

.calendar-day__times div {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.32rem;
}

.calendar-day__times small {
    color: var(--hcis-slate-500);
    font-size: 0.52rem;
    font-weight: 900;
    letter-spacing: 0.04em;
}

.calendar-day__times strong {
    color: var(--hcis-slate-900);
    font-size: 0.6rem;
    font-weight: 950;
}

.side-count {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.55rem;
    height: 1.55rem;
    border-radius: 999px;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    font-size: 0.66rem;
    font-weight: 950;
}

.side-count.is-danger {
    background: var(--hcis-red-50);
    color: var(--hcis-red-500);
}

.action-list,
.submission-list,
.schedule-list {
    display: grid;
    gap: 0.4rem;
    min-width: 0;
    position: relative;
}

.action-page-loader {
    position: absolute;
    inset: 0;
    z-index: 3;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.72);
    backdrop-filter: blur(1.5px);
    border-radius: 0.5rem;
    font-size: 1.1rem;
    color: var(--hcis-slate-500);
    pointer-events: none;
}

.action-page-loader::after {
    content: '';
    width: 1.25rem;
    height: 1.25rem;
    border: 2px solid var(--hcis-slate-300);
    border-top-color: var(--hcis-indigo-500);
    border-radius: 50%;
    animation: action-spin 0.7s linear infinite;
}

.action-page-fade-enter-active,
.action-page-fade-leave-active {
    transition: opacity 0.15s ease;
}

.action-page-fade-enter-from,
.action-page-fade-leave-to {
    opacity: 0;
}

.action-list.is-scrollable {
    max-height: 24rem;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 0.1rem;
}

.action-skeleton-list {
    display: grid;
    gap: 0.4rem;
}

.action-card {
    width: 100%;
    min-width: 0;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    gap: 0.45rem;
    align-items: center;
    padding: 0.48rem;
    border-radius: 0.72rem;
    border: 1px solid var(--hcis-slate-200);
    background: #fff;
    text-align: left;
    cursor: pointer;
    transition:
        opacity 0.25s ease,
        background-color 0.25s ease,
        border-color 0.25s ease;
}

.action-card.is-danger {
    background: #fff1f2;
    border-color: var(--hcis-red-200);
}

.action-card.is-warning {
    background: var(--hcis-amber-50);
    border-color: var(--hcis-amber-200);
}

.action-card.is-info {
    background: var(--hcis-sky-50);
    border-color: var(--hcis-sky-200);
}

.action-card.is-success {
    background: var(--hcis-green-50);
    border-color: var(--hcis-green-200);
}

.action-card.is-orange {
    background: #fff7ed;
    border-color: #fed7aa;
}

/* Item sudah dibaca: abu-abu/redup, override warna tone apa pun. Tetap bisa
   diklik (navigasi), hanya menandakan sudah ditindaklanjuti. */
.action-card.is-read,
.action-card.is-read.is-danger,
.action-card.is-read.is-warning,
.action-card.is-read.is-info,
.action-card.is-read.is-success,
.action-card.is-read.is-orange {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-200);
    opacity: 0.7;
}

.action-card.is-read .action-card__icon {
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-400);
}

.action-card.is-read .action-card__body strong {
    color: var(--hcis-slate-500);
    font-weight: 700;
}

.action-card.is-read .action-card__body span,
.action-card.is-read .action-card__body small {
    color: var(--hcis-slate-400);
}

.action-card__icon {
    width: 1.85rem;
    height: 1.85rem;
    display: grid;
    place-items: center;
    border-radius: 0.62rem;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}

.action-card__body {
    min-width: 0;
}

.action-card__body strong {
    display: block;
    color: var(--hcis-slate-900);
    font-size: 0.62rem;
    font-weight: 950;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.action-card__body span {
    display: block;
    margin-top: 0.1rem;
    color: var(--hcis-slate-500);
    font-size: 0.64rem;
    line-height: 1.3;
}

.action-card__body small {
    display: block;
    margin-top: 0.12rem;
    color: var(--hcis-slate-600);
    font-size: 0.62rem;
    font-weight: 900;
}

.action-card__actions {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.32rem;
}

.action-card__done {
    min-height: 1.45rem;
    padding: 0 0.45rem;
    border: 1px solid rgba(79, 70, 229, 0.22);
    border-radius: 999px;
    background: var(--hcis-white);
    color: var(--hcis-indigo-600);
    font-size: 0.5rem;
    font-weight: 950;
    white-space: nowrap;
    cursor: pointer;
}

.action-card__done:disabled {
    opacity: 0.55;
    cursor: wait;
}

.action-card__done i,
.action-card__done span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.action-loading-spin {
    animation: action-spin 0.85s linear infinite;
}

@keyframes action-spin {
    to {
        transform: rotate(360deg);
    }
}

.mark-done-confirm {
    display: grid;
    gap: 0.85rem;
}

.mark-done-confirm__hero {
    position: relative;
    display: flex;
    gap: 0.8rem;
    padding: 0.95rem;
    border-radius: 0.95rem;
    border: 1px solid rgba(79, 70, 229, 0.16);
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.92), rgba(240, 253, 244, 0.88)), var(--hcis-white);
    overflow: hidden;
}

.mark-done-confirm__hero::after {
    content: '';
    position: absolute;
    inset: auto 0 0;
    height: 3px;
    background: linear-gradient(90deg, var(--hcis-indigo-600), #22c55e, #f97316);
}

.mark-done-confirm__icon {
    position: relative;
    z-index: 1;
    flex: 0 0 auto;
    width: 2.85rem;
    height: 2.85rem;
    display: grid;
    place-items: center;
    border-radius: 0.85rem;
    background: var(--hcis-white);
    color: var(--hcis-green-600);
    border: 1px solid rgba(34, 197, 94, 0.2);
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
    font-size: 1.35rem;
}

.mark-done-confirm__copy {
    position: relative;
    z-index: 1;
    min-width: 0;
}

.mark-done-confirm__copy h3 {
    margin: 0.14rem 0 0;
    color: var(--hcis-slate-900);
    font-size: 0.98rem;
    line-height: 1.25;
    font-weight: 950;
}

.mark-done-confirm__copy p {
    margin: 0.35rem 0 0;
    color: var(--hcis-slate-600);
    font-size: 0.76rem;
    line-height: 1.5;
    font-weight: 750;
}

.mark-done-confirm__summary {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.45rem;
}

.mark-done-confirm__summary span {
    min-width: 0;
    display: inline-flex;
    align-items: center;
    gap: 0.42rem;
    padding: 0.58rem 0.68rem;
    border-radius: 0.78rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    color: var(--hcis-slate-700);
    font-size: 0.68rem;
    font-weight: 900;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mark-done-confirm__summary i {
    flex: 0 0 auto;
    color: var(--hcis-indigo-600);
}

.mark-done-confirm__note {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    padding: 0.72rem 0.82rem;
    border-radius: 0.82rem;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
}

.mark-done-confirm__note i {
    flex: 0 0 auto;
    margin-top: 0.05rem;
    color: var(--hcis-orange-600);
}

.mark-done-confirm__note p {
    margin: 0;
    font-size: 0.7rem;
    line-height: 1.5;
    font-weight: 850;
}

@media (max-width: 480px) {
    .mark-done-confirm__hero {
        padding: 0.82rem;
    }

    .mark-done-confirm__summary {
        grid-template-columns: 1fr;
    }
}

.action-card.is-skeleton {
    cursor: default;
    pointer-events: none;
}

.action-card.is-skeleton .action-card__icon {
    border: none;
    background: var(--hcis-slate-200);
}

.quick-action-panel {
    display: grid;
    gap: 0.48rem;
}

.quick-action-folder-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.4rem;
    max-height: 21rem;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 0.2rem;
    min-width: 0;
    scrollbar-width: thin;
    scrollbar-color: var(--hcis-slate-300) transparent;
}

.quick-action-folder-grid::-webkit-scrollbar {
    width: 3px;
}

.quick-action-folder-grid::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 999px;
}

.quick-action-folder-grid::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: var(--hcis-slate-300);
}

.quick-action-folder-grid::-webkit-scrollbar-thumb:hover {
    background: var(--hcis-slate-400);
}

.quick-action-folder {
    min-width: 0;
    min-height: 5.25rem;
    padding: 0.58rem 0.5rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.82rem;
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-slate-50) 100%);
    color: var(--hcis-slate-700);
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto;
    justify-items: center;
    align-items: center;
    gap: 0.3rem;
    text-align: center;
    cursor: pointer;
    transition:
        border-color 200ms ease,
        background 200ms ease,
        box-shadow 200ms ease,
        transform 200ms ease;
}

.quick-action-folder:hover {
    border-color: var(--hcis-indigo-200);
    background: var(--hcis-white);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.045);
    transform: translateY(-1px);
}

.quick-action-folder__icon {
    width: 2.15rem;
    height: 2.15rem;
    border-radius: 0.72rem;
    display: grid;
    place-items: center;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    font-size: 1rem;
}

.quick-action-folder__copy {
    min-width: 0;
    width: 100%;
    display: grid;
    gap: 0.14rem;
}

.quick-action-folder__copy strong {
    color: var(--hcis-slate-900);
    font-size: 0.62rem;
    font-weight: 950;
    line-height: 1.12;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.quick-action-folder__copy small {
    color: var(--hcis-slate-500);
    font-size: 0.5rem;
    font-weight: 850;
    line-height: 1.15;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.quick-action-folder > .bi-chevron-right {
    color: var(--hcis-slate-400);
    font-size: 0.72rem;
}

.quick-action-group {
    min-width: 0;
    display: grid;
    gap: 0.32rem;
}

.quick-action-group__title {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--hcis-slate-500);
    font-size: 0.65rem;
    font-weight: 950;
    text-transform: uppercase;
}

.quick-action-group__title i {
    color: var(--hcis-indigo-600);
    font-size: 0.72rem;
}

.quick-action-group__title span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.quick-action-list {
    display: grid;
    gap: 0.32rem;
    min-width: 0;
}

.quick-action-button {
    width: 100%;
    min-width: 0;
    min-height: 3.05rem;
    padding: 0.45rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.76rem;
    background: var(--hcis-white);
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.42rem;
    text-align: left;
    color: var(--hcis-slate-700);
    cursor: pointer;
    transition:
        border-color 200ms ease,
        background 200ms ease,
        box-shadow 200ms ease,
        transform 200ms ease;
}

.quick-action-button:hover {
    border-color: var(--hcis-indigo-200);
    background: var(--hcis-slate-50);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.045);
    transform: translateY(-1px);
}

.quick-action-button__icon {
    width: 1.9rem;
    height: 1.9rem;
    border-radius: 0.66rem;
    display: grid;
    place-items: center;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
}

.quick-action-button__copy {
    min-width: 0;
    display: grid;
    gap: 0.1rem;
}

.quick-action-button__copy strong {
    color: var(--hcis-slate-900);
    font-size: 0.62rem;
    font-weight: 950;
    line-height: 1.15;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.quick-action-button__copy small {
    color: var(--hcis-slate-500);
    font-size: 0.62rem;
    font-weight: 850;
    line-height: 1.1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.quick-action-button > .bi-arrow-right-short {
    color: var(--hcis-slate-400);
    font-size: 1rem;
}

.attendance-donut-card {
    display: grid;
    gap: 0.58rem;
    cursor: pointer;
}

.attendance-donut-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.72rem;
    min-width: 0;
}

.attendance-donut-card__head h2 {
    margin: 0.08rem 0 0;
    color: var(--hcis-slate-900);
    font-size: 0.86rem;
    font-weight: 950;
    line-height: 1.15;
}

.attendance-donut-period-select {
    width: 100%;
    min-height: 2.28rem;
    padding: 0.46rem 2rem 0.46rem 0.62rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.72rem;
    background: var(--hcis-white);
    color: var(--hcis-slate-700);
    font-size: 0.62rem;
    font-weight: 900;
    line-height: 1.15;
    cursor: pointer;
}

.attendance-donut-period-select:disabled {
    cursor: wait;
    color: var(--hcis-slate-400);
    background: var(--hcis-slate-50);
}

.attendance-donut-chart {
    min-width: 0;
    margin: -0.18rem -0.25rem -0.38rem;
}

.attendance-donut-meta {
    display: grid;
    gap: 0.12rem;
    min-width: 0;
}

.attendance-donut-meta strong {
    color: var(--hcis-slate-900);
    font-size: 0.68rem;
    font-weight: 950;
    line-height: 1.22;
}

.attendance-donut-meta span {
    color: var(--hcis-slate-500);
    font-size: 0.65rem;
    font-weight: 800;
    line-height: 1.2;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.attendance-donut-legend {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
    min-width: 0;
}

.attendance-donut-legend__item {
    min-width: 0;
    min-height: 2.08rem;
    padding: 0.34rem 0.4rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.72rem;
    background: var(--hcis-white);
    color: var(--hcis-slate-700);
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.28rem;
    text-align: left;
    cursor: pointer;
    transition:
        border-color 180ms ease,
        background 180ms ease,
        transform 180ms ease;
}

.attendance-donut-legend__item:hover:not(:disabled) {
    border-color: var(--hcis-indigo-200);
    background: var(--hcis-slate-50);
    transform: translateY(-1px);
}

.attendance-donut-legend__item:disabled {
    cursor: default;
    opacity: 0.58;
}

.attendance-donut-legend__dot {
    width: 0.48rem;
    height: 0.48rem;
    border-radius: 999px;
    box-shadow: 0 0 0 3px rgba(226, 232, 240, 0.7);
}

.attendance-donut-legend__item span:not(.attendance-donut-legend__dot) {
    min-width: 0;
    color: var(--hcis-slate-600);
    font-size: 0.64rem;
    font-weight: 900;
    line-height: 1.08;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.attendance-donut-legend__item strong {
    color: var(--hcis-slate-900);
    font-size: 0.66rem;
    font-weight: 950;
}

.attendance-donut-legend__item.is-success {
    background: var(--hcis-green-50);
    border-color: var(--hcis-green-200);
}

.attendance-donut-legend__item.is-warning {
    background: var(--hcis-amber-50);
    border-color: var(--hcis-amber-200);
}

.attendance-donut-legend__item.is-danger {
    background: #fff1f2;
    border-color: var(--hcis-red-200);
}

.attendance-donut-legend__item.is-info {
    background: #eff6ff;
    border-color: #bfdbfe;
}

.attendance-donut-legend__item.is-muted,
.attendance-donut-legend__item.is-neutral {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-200);
}

.attendance-donut-action {
    width: 100%;
    min-height: 2.28rem;
    margin-top: 0.5rem;
    padding: 0.48rem 0.62rem;
    border: 1px solid var(--hcis-indigo-100);
    border-radius: 0.72rem;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.24rem;
    font-size: 0.62rem;
    font-weight: 950;
    cursor: pointer;
    transition:
        background 180ms ease,
        transform 180ms ease;
}

.attendance-donut-action:hover {
    background: var(--hcis-indigo-100);
    transform: translateY(-1px);
}

.attendance-donut-action i {
    font-size: 1rem;
    line-height: 1;
}

.attendance-donut-skeleton {
    display: grid;
    gap: 0.62rem;
    justify-items: center;
    padding: 0.28rem 0 0.18rem;
}

.attendance-donut-skeleton__chart,
.attendance-donut-skeleton__lines span {
    background: linear-gradient(90deg, var(--hcis-slate-200) 0%, var(--hcis-slate-50) 50%, var(--hcis-slate-200) 100%);
    background-size: 200% 100%;
    animation: loading-shimmer 1.35s ease-in-out infinite;
}

.attendance-donut-skeleton__chart {
    width: 8.2rem;
    height: 8.2rem;
    border-radius: 999px;
}

.attendance-donut-skeleton__lines {
    width: 100%;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.36rem;
}

.attendance-donut-skeleton__lines span {
    height: 2.08rem;
    border-radius: 0.72rem;
}

.mobile-attendance-donut-card {
    gap: 0.54rem;
}

.mobile-attendance-donut-card .attendance-donut-chart {
    margin-top: -0.3rem;
}

.mobile-attendance-donut-card .attendance-donut-legend {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.28rem;
}

.mobile-attendance-donut-card .attendance-donut-legend__item {
    min-height: 2.18rem;
    padding: 0.34rem;
    grid-template-columns: auto minmax(0, 1fr);
}

.mobile-attendance-donut-card .attendance-donut-legend__item strong {
    grid-column: 2;
    justify-self: start;
}

.quick-action-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 1200;
    display: grid;
    place-items: center;
    padding: 1rem;
    background: rgba(15, 23, 42, 0.72);
    /* backdrop-filter: blur(10px); */
    /* -webkit-backdrop-filter: blur(10px); */
}

.quick-action-modal-card {
    width: min(31rem, 100%);
    max-height: min(42rem, calc(100vh - 2rem));
    overflow: hidden;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.97);
    box-shadow: 0 28px 70px rgba(15, 23, 42, 0.24);
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto;
}

.quick-action-modal-head {
    padding: 1rem;
    background: linear-gradient(135deg, var(--hcis-indigo-50) 0%, var(--hcis-white) 100%);
    border-bottom: 1px solid var(--hcis-slate-200);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}

.quick-action-modal-title {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 0.72rem;
}

.quick-action-modal-icon {
    width: 2.6rem;
    height: 2.6rem;
    border-radius: 0.86rem;
    display: grid;
    place-items: center;
    background: var(--hcis-indigo-600);
    color: var(--hcis-white);
    box-shadow: 0 12px 26px rgba(79, 70, 229, 0.22);
    flex-shrink: 0;
}

.quick-action-modal-title h3 {
    margin: 0.12rem 0 0;
    color: var(--hcis-slate-900);
    font-size: 1rem;
    font-weight: 950;
    line-height: 1.1;
}

.quick-action-modal-title small {
    display: block;
    margin-top: 0.2rem;
    color: var(--hcis-slate-500);
    font-size: 0.62rem;
    font-weight: 850;
}

.quick-action-modal-close {
    width: 2.15rem;
    height: 2.15rem;
    border: 0;
    border-radius: 999px;
    background: var(--hcis-white);
    color: var(--hcis-slate-500);
    display: grid;
    place-items: center;
    cursor: pointer;
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.08);
}

.quick-action-modal-body {
    min-height: 0;
    overflow-y: auto;
    padding: 0.75rem;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}

.quick-action-modal-item {
    min-width: 0;
    min-height: 5.15rem;
    padding: 0.62rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.86rem;
    background: var(--hcis-white);
    color: var(--hcis-slate-700);
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.5rem;
    text-align: left;
    cursor: pointer;
    transition:
        border-color 200ms ease,
        box-shadow 200ms ease,
        transform 200ms ease;
}

.quick-action-modal-item:hover {
    border-color: var(--hcis-indigo-200);
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
    transform: translateY(-1px);
}

.quick-action-modal-item__icon {
    width: 2rem;
    height: 2rem;
    border-radius: 0.72rem;
    display: grid;
    place-items: center;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
}

.quick-action-modal-item__copy {
    min-width: 0;
    display: grid;
    gap: 0.16rem;
}

.quick-action-modal-item__copy strong {
    color: var(--hcis-slate-900);
    font-size: 0.7rem;
    font-weight: 950;
    line-height: 1.15;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.quick-action-modal-item__copy small {
    color: var(--hcis-slate-500);
    font-size: 0.64rem;
    font-weight: 850;
    line-height: 1.15;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.quick-action-modal-item > .bi-arrow-right-short {
    color: var(--hcis-slate-400);
    font-size: 1.05rem;
}

.quick-action-modal-footer {
    padding: 0.75rem 1rem;
    border-top: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    display: flex;
    justify-content: center;
}

.quick-action-modal-back {
    min-height: 2.35rem;
    padding: 0 0.9rem;
    border: 0;
    border-radius: 0.76rem;
    background: var(--hcis-indigo-600);
    color: var(--hcis-white);
    display: inline-flex;
    align-items: center;
    gap: 0.42rem;
    font-size: 0.68rem;
    font-weight: 950;
    cursor: pointer;
}

@media (max-width: 576px) {
    .quick-action-modal-overlay {
        align-items: end;
        padding: 0.75rem;
    }

    .quick-action-modal-card {
        max-height: min(38rem, calc(100vh - 1.5rem));
        border-radius: 1rem;
    }

    .quick-action-modal-body {
        grid-template-columns: 1fr;
    }
}

.action-error-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.45rem;
    padding: 0.55rem;
    border: 1px dashed var(--hcis-red-300);
    border-radius: 0.72rem;
    background: #fff1f2;
    color: var(--hcis-red-800);
    font-size: 0.58rem;
    font-weight: 850;
}

.action-error-card button {
    flex: 0 0 auto;
    border: none;
    border-radius: 999px;
    background: var(--hcis-red-600);
    color: #fff;
    padding: 0.26rem 0.56rem;
    font-size: 0.64rem;
    font-weight: 950;
    cursor: pointer;
}

.submission-item {
    width: 100%;
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.48rem;
    padding: 0.48rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.7rem;
    background: #fff;
    text-align: left;
    cursor: pointer;
}

.submission-item > div {
    min-width: 0;
}

.submission-item strong {
    display: block;
    color: var(--hcis-slate-900);
    font-size: 0.62rem;
    font-weight: 950;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.submission-item span {
    display: block;
    margin-top: 0.1rem;
    color: var(--hcis-slate-500);
    font-size: 0.64rem;
    font-weight: 750;
}

.submission-item .submission-item__detail {
    color: var(--hcis-slate-700);
    font-size: 0.61rem;
    font-weight: 900;
    line-height: 1.25;
}

.submission-item em {
    display: block;
    margin-top: 0.1rem;
    color: var(--hcis-amber-700);
    font-size: 0.62rem;
    font-style: normal;
    font-weight: 900;
}

.submission-item small {
    flex: 0 0 auto;
    padding: 0.2rem 0.4rem;
    border-radius: 999px;
    font-size: 0.62rem;
    font-weight: 950;
}

.submission-item small.is-pending {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
}

.submission-item small.is-approved {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}

.submission-item small.is-rejected,
.submission-item small.is-expired {
    background: var(--hcis-red-100);
    color: var(--hcis-red-600);
}

.submission-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding-top: 0.35rem;
}

.submission-pagination__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.85rem;
    height: 1.85rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.55rem;
    background: #fff;
    color: var(--hcis-slate-600);
    cursor: pointer;
    transition:
        background 0.15s ease,
        border-color 0.15s ease,
        color 0.15s ease;
}

.submission-pagination__btn:hover:not(:disabled) {
    background: var(--hcis-slate-100);
    border-color: var(--hcis-slate-300);
    color: var(--hcis-slate-800);
}

.submission-pagination__btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.submission-pagination__info {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
    min-width: 2.5rem;
    text-align: center;
}

.schedule-item {
    min-width: 0;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.48rem;
    padding: 0.48rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.7rem;
    background: #fff;
}

.schedule-item.is-clickable {
    cursor: pointer;
    transition:
        border-color 0.18s ease,
        box-shadow 0.18s ease,
        transform 0.18s ease;
}

.schedule-item.is-clickable:hover,
.schedule-item.is-clickable:focus-visible {
    border-color: var(--hcis-indigo-200);
    box-shadow: 0 6px 18px rgba(79, 70, 229, 0.14);
    transform: translateY(-1px);
    outline: none;
}

.schedule-date {
    width: 2.25rem;
    height: 2.25rem;
    display: grid;
    place-items: center;
    border-radius: 0.68rem;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    text-align: center;
}

.schedule-date strong {
    font-size: 0.58rem;
    font-weight: 950;
}

.schedule-item > div:nth-child(2) {
    min-width: 0;
}

.schedule-item > div:nth-child(2) strong {
    display: block;
    color: var(--hcis-slate-900);
    font-size: 0.62rem;
    font-weight: 950;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.schedule-item > div:nth-child(2) span {
    display: block;
    margin-top: 0.1rem;
    color: var(--hcis-slate-500);
    font-size: 0.64rem;
    font-weight: 750;
}

.schedule-item small {
    flex: 0 0 auto;
    padding: 0.18rem 0.36rem;
    border-radius: 999px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-700);
    font-size: 0.5rem;
    font-weight: 950;
}

.empty-state {
    width: 100%;
    min-width: 0;
    display: grid;
    place-items: center;
    gap: 0.18rem;
    min-height: 8rem;
    padding: 0.85rem;
    border: 1px dashed var(--hcis-slate-300);
    border-radius: 0.75rem;
    background: var(--hcis-slate-50);
    text-align: center;
    color: var(--hcis-slate-500);
}

.empty-state.is-compact {
    min-height: 3.5rem;
    padding: 0.55rem;
}

.empty-state i {
    font-size: 1.35rem;
    color: var(--hcis-slate-400);
}

.empty-state.is-compact i {
    font-size: 1rem;
}

.empty-state strong {
    display: block;
    color: var(--hcis-slate-900);
    font-size: 0.72rem;
    font-weight: 950;
}

.empty-state span {
    display: block;
    max-width: 20rem;
    color: var(--hcis-slate-500);
    font-size: 0.6rem;
    font-weight: 750;
    line-height: 1.35;
}

.loading-state {
    width: 100%;
    min-width: 0;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 0.55rem;
    align-items: start;
    min-height: 8rem;
    padding: 0.8rem;
    border: 1px dashed var(--hcis-slate-300);
    border-radius: 0.75rem;
    background: linear-gradient(180deg, var(--hcis-slate-50), var(--hcis-indigo-50));
}

.loading-state.is-compact {
    min-height: 4.25rem;
    padding: 0.6rem;
}

.loading-state__icon {
    width: 2rem;
    height: 2rem;
    display: grid;
    place-items: center;
    border-radius: 0.65rem;
    background: rgba(148, 163, 184, 0.12);
    color: var(--hcis-slate-500);
    animation: pulse-soft 1.6s ease-in-out infinite;
}

.loading-state__copy {
    min-width: 0;
    display: grid;
    gap: 0.35rem;
}

.loading-state__copy strong {
    display: block;
    font-size: 0.72rem;
    font-weight: 950;
    color: var(--hcis-slate-700);
}

.loading-bar {
    display: block;
    height: 0.6rem;
    border-radius: 999px;
    background: linear-gradient(90deg, #dbeafe 0%, var(--hcis-indigo-200) 50%, #dbeafe 100%);
    background-size: 200% 100%;
    animation: loading-shimmer 1.4s ease-in-out infinite;
}

.dashboard-alert {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-top: 0.55rem;
    padding: 0.75rem 0.9rem;
    border: 1px solid var(--hcis-red-200);
    border-radius: 0.85rem;
    background: #fff1f2;
    color: #9f1239;
}

.dashboard-alert strong {
    display: block;
    font-size: 0.76rem;
    font-weight: 950;
}

.dashboard-alert span {
    display: block;
    margin-top: 0.12rem;
    font-size: 0.62rem;
    font-weight: 700;
}

.dashboard-alert__action {
    flex: 0 0 auto;
    border: 0;
    border-radius: 999px;
    padding: 0.4rem 0.8rem;
    background: var(--hcis-rose-600);
    color: #fff;
    font-size: 0.64rem;
    font-weight: 900;
}

@keyframes pulse-soft {
    0%,
    100% {
        opacity: 0.72;
    }

    50% {
        opacity: 1;
    }
}

@keyframes loading-shimmer {
    0% {
        background-position: 200% 0;
    }

    100% {
        background-position: -200% 0;
    }
}

@keyframes spin-soft {
    to {
        transform: rotate(360deg);
    }
}

@keyframes leaderSkeletonPulse {
    0% {
        background-position: 200% 0;
    }

    100% {
        background-position: -200% 0;
    }
}

.modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 1050;
    display: grid;
    place-items: center;
    padding: 1rem;
    background: rgba(15, 23, 42, 0.82);
    /* backdrop-filter: blur(6px); */
}

/* ── Day Modal Redesign (dm-* prefix) ── */

.day-modal__body {
    /* padding: 1rem; */
    display: grid;
    gap: 0.85rem;
}

/* Hero */
.dm-hero {
    border-radius: 1rem;
    padding: 1.1rem 1rem 1rem;
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-blue-600) 100%);
    color: white;
    position: relative;
    overflow: hidden;
}

.dm-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at 100% 0%, rgba(255, 255, 255, 0.12) 0%, transparent 60%);
    pointer-events: none;
}

.dm-hero.is-success {
    background: linear-gradient(135deg, var(--hcis-emerald-600) 0%, var(--hcis-emerald-500) 100%);
}
.dm-hero.is-warning {
    background: linear-gradient(135deg, var(--hcis-amber-600) 0%, var(--hcis-amber-500) 100%);
}
.dm-hero.is-danger {
    background: linear-gradient(135deg, var(--hcis-red-600) 0%, var(--hcis-red-500) 100%);
}
.dm-hero.is-info {
    background: linear-gradient(135deg, var(--hcis-sky-600) 0%, var(--hcis-sky-500) 100%);
}
.dm-hero.is-purple {
    background: linear-gradient(135deg, var(--hcis-violet-600) 0%, var(--hcis-violet-400) 100%);
}
.dm-hero.is-default {
    background: linear-gradient(135deg, var(--hcis-slate-600) 0%, var(--hcis-slate-500) 100%);
}
.dm-hero.is-holiday {
    background: linear-gradient(135deg, var(--hcis-red-600) 0%, var(--hcis-red-500) 100%);
}

.dm-hero__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.34rem;
    padding: 0.24rem 0.65rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.2);
    font-size: 0.65rem;
    font-weight: 900;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    backdrop-filter: blur(4px);
}

.dm-hero__badge .bi-circle-fill {
    font-size: 0.4rem;
    animation: dm-pulse 1.8s ease-in-out infinite;
}

.dm-hero__times {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 0.85rem;
}

.dm-hero__time {
    flex: 1;
    min-width: 0;
}

.dm-hero__time > span {
    display: block;
    font-size: 0.62rem;
    font-weight: 800;
    opacity: 0.72;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.dm-hero__time > strong {
    display: block;
    font-size: 1.8rem;
    font-weight: 950;
    line-height: 1;
    margin-top: 0.12rem;
    letter-spacing: -0.025em;
}

.dm-hero__time-sep {
    font-size: 1.5rem;
    opacity: 0.45;
    flex-shrink: 0;
    line-height: 1;
}

.dm-hero__shift-row {
    display: flex;
    align-items: center;
    gap: 0.38rem;
    margin-top: 0.72rem;
    padding-top: 0.62rem;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    font-size: 0.7rem;
    font-weight: 800;
    opacity: 0.84;
}

.dm-hero__shift-dot {
    opacity: 0.5;
}

/* Note */
.dm-note {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.75rem 0.85rem;
    border-radius: 0.9rem;
    background: var(--hcis-amber-50);
    border: 1px solid var(--hcis-amber-200);
    color: var(--hcis-amber-800);
}

.dm-note > i {
    flex-shrink: 0;
    color: var(--hcis-amber-500);
    font-size: 0.95rem;
    margin-top: 0.05rem;
}

.dm-note > p {
    margin: 0;
    font-size: 0.74rem;
    font-weight: 750;
    line-height: 1.55;
}

/* Holiday banner inside day modal */
.dm-holiday-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.75rem 0.85rem;
    border-radius: 0.9rem;
    background: linear-gradient(135deg, var(--hcis-red-50) 0%, var(--hcis-red-50) 100%);
    border: 1px solid var(--hcis-red-200);
    color: var(--hcis-red-700);
}

.dm-holiday-banner > i {
    flex-shrink: 0;
    color: var(--hcis-red-500);
    font-size: 1.05rem;
    margin-top: 0.05rem;
}

.dm-holiday-banner > div {
    min-width: 0;
}

.dm-holiday-banner strong {
    display: block;
    font-size: 0.78rem;
    font-weight: 900;
    color: var(--hcis-red-600);
    line-height: 1.3;
}

.dm-holiday-banner span {
    display: block;
    margin-top: 0.1rem;
    font-size: 0.7rem;
    font-weight: 750;
    color: var(--hcis-red-700);
    line-height: 1.4;
}

/* Section wrapper */
.dm-section {
    display: grid;
    gap: 0.55rem;
}

.dm-section__head {
    display: flex;
    align-items: center;
    gap: 0.42rem;
    color: var(--hcis-slate-600);
    font-size: 0.68rem;
    font-weight: 950;
    text-transform: uppercase;
    letter-spacing: 0.07em;
}

.dm-section__head > i {
    font-size: 0.88rem;
    color: var(--hcis-indigo-600);
}

.dm-section__count {
    margin-left: auto;
    padding: 0.1rem 0.48rem;
    border-radius: 999px;
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-600);
    font-size: 0.62rem;
    font-weight: 900;
    text-transform: none;
    letter-spacing: 0;
}

/* Flags */
.dm-flag-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.dm-flag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.38rem 0.65rem;
    border-radius: 999px;
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-200);
    color: var(--hcis-slate-600);
    font-size: 0.68rem;
    font-weight: 900;
    transition: transform 0.15s ease;
}

.dm-flag:hover {
    transform: scale(1.04);
}
.dm-flag.is-danger {
    background: var(--hcis-red-100);
    border-color: var(--hcis-red-300);
    color: var(--hcis-red-600);
}
.dm-flag.is-warning {
    background: var(--hcis-amber-100);
    border-color: var(--hcis-amber-300);
    color: var(--hcis-amber-700);
}
.dm-flag.is-info {
    background: var(--hcis-sky-100);
    border-color: var(--hcis-sky-300);
    color: var(--hcis-sky-700);
}
.dm-flag.is-success {
    background: var(--hcis-green-100);
    border-color: #86efac;
    color: var(--hcis-green-600);
}
.dm-flag.is-purple {
    background: #ede9fe;
    border-color: #c4b5fd;
    color: var(--hcis-violet-600);
}

/* Timeline */
.dm-timeline {
    display: grid;
}

.dm-timeline__item {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
}

.dm-timeline__item.is-skeleton .dm-timeline__card {
    flex: 1;
    padding: 0.6rem 0.75rem;
    border-radius: 0.8rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    margin-bottom: 0.55rem;
}

.dm-timeline__connector {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 2.2rem;
}

.dm-timeline__dot {
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 0.72rem;
    display: grid;
    place-items: center;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    font-size: 0.85rem;
    flex-shrink: 0;
}

.dm-timeline__line {
    width: 2px;
    flex: 1;
    min-height: 0.65rem;
    background: linear-gradient(to bottom, var(--hcis-indigo-200), var(--hcis-indigo-100));
    border-radius: 1px;
    margin: 0.18rem 0;
}

.dm-timeline__item.is-checkin .dm-timeline__dot {
    background: var(--hcis-green-100);
    color: var(--hcis-green-600);
}
.dm-timeline__item.is-checkout .dm-timeline__dot {
    background: var(--hcis-red-100);
    color: var(--hcis-red-600);
}

.dm-timeline__card {
    flex: 1;
    padding: 0.6rem 0.75rem;
    border-radius: 0.8rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    margin-bottom: 0.55rem;
}

.dm-timeline__card strong {
    display: block;
    color: var(--hcis-slate-900);
    font-size: 0.76rem;
    font-weight: 950;
}

.dm-timeline__card span {
    display: block;
    margin-top: 0.1rem;
    color: var(--hcis-slate-500);
    font-size: 0.68rem;
    font-weight: 800;
}

/* Absensi Comparison Card */
.dm-compare {
    display: flex;
    gap: 0;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 1rem;
    overflow: hidden;
}

.dm-compare__col {
    flex: 1;
    padding: 0.85rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.dm-compare__col-head {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.68rem;
    font-weight: 800;
    color: var(--hcis-slate-400);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.15rem;
}

.dm-compare__divider {
    width: 1px;
    background: var(--hcis-slate-200);
    align-self: stretch;
    margin: 0.75rem 0;
}

.dm-compare__row {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
}

.dm-compare__label {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--hcis-slate-400);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.dm-compare__time {
    font-size: 1.05rem;
    font-weight: 900;
    color: var(--hcis-slate-900);
    line-height: 1.2;
}

.dm-compare__time.is-present {
    color: var(--hcis-green-600);
}

.dm-compare__time.is-absent {
    color: var(--hcis-slate-300);
}

/* Items (transactions) */
.dm-items {
    display: grid;
    gap: 0.42rem;
}

.dm-item {
    display: flex;
    align-items: center;
    gap: 0.72rem;
    padding: 0.7rem 0.75rem;
    border-radius: 0.85rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
}

.dm-item__icon {
    flex-shrink: 0;
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 0.72rem;
    display: grid;
    place-items: center;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    font-size: 0.9rem;
}

.dm-item > div:last-child {
    min-width: 0;
}

.dm-item strong {
    display: block;
    color: var(--hcis-slate-900);
    font-size: 0.76rem;
    font-weight: 950;
}

.dm-item span {
    display: block;
    margin-top: 0.1rem;
    color: var(--hcis-slate-500);
    font-size: 0.68rem;
    font-weight: 800;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Transaksi terkait: tampilkan judul + status + deskripsi penuh */
.dm-item--tx {
    align-items: flex-start;
}

.dm-item__body {
    min-width: 0;
    flex: 1;
}

.dm-item__title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.dm-item__desc {
    white-space: normal;
    overflow: visible;
    text-overflow: clip;
    line-height: 1.35;
}

.dm-item__no {
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    margin-top: 0.18rem;
    font-size: 0.62rem;
    font-weight: 800;
    color: var(--hcis-slate-400);
    letter-spacing: 0.02em;
}
.dm-item__no i {
    font-size: 0.6rem;
}

.dm-tx-status {
    display: inline-flex;
    align-items: center;
    gap: 0.22rem;
    flex-shrink: 0;
    margin-top: 0;
    padding: 0.12rem 0.45rem;
    border-radius: 999px;
    font-size: 0.6rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    white-space: nowrap;
}

.dm-tx-status.is-success {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}
.dm-tx-status.is-warning {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
}
.dm-tx-status.is-danger {
    background: var(--hcis-red-100);
    color: var(--hcis-red-600);
}
.dm-tx-status.is-info {
    background: #dbeafe;
    color: #1d4ed8;
}

/* Raw log list */
.dm-raw-list {
    display: grid;
    gap: 0.35rem;
}

.dm-raw-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    padding: 0.58rem 0.72rem;
    border-radius: 0.72rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    transition:
        background 0.15s ease,
        border-color 0.15s ease;
}

.dm-raw-item.is-selected {
    background: var(--hcis-green-50);
    border-color: #86efac;
    box-shadow: inset 3px 0 0 #22c55e;
}

.dm-raw-item.is-skeleton {
    align-items: center;
    justify-content: space-between;
}

.dm-raw-item__time strong {
    display: block;
    color: var(--hcis-slate-900);
    font-size: 0.78rem;
    font-weight: 950;
}

.dm-raw-item__meta {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    margin-top: 0.12rem;
}

.dm-raw-item__source {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    font-size: 0.63rem;
    font-weight: 800;
    line-height: 1;
    border-radius: 999px;
    padding: 0.18rem 0.5rem;
    width: fit-content;
}

.dm-raw-item__source i,
.dm-raw-item__lokasi i {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    line-height: 1;
    flex-shrink: 0;
}

.dm-raw-item__source.is-online {
    background: #eff6ff;
    color: var(--hcis-blue-600);
}

.dm-raw-item__source.is-office {
    background: var(--hcis-green-50);
    color: var(--hcis-green-600);
}

.dm-raw-item__lokasi {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.62rem;
    font-weight: 700;
    line-height: 1;
    color: var(--hcis-slate-500);
    padding-left: 0.35rem;
}

.dm-raw-item__badges {
    flex-shrink: 0;
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.28rem;
}

.dm-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.24rem 0.52rem;
    border-radius: 999px;
    font-size: 0.64rem;
    font-weight: 900;
}

.dm-badge.is-checkin {
    background: var(--hcis-green-100);
    color: var(--hcis-green-600);
    border: 1px solid #86efac;
}
.dm-badge.is-checkout {
    background: #fff7ed;
    color: var(--hcis-orange-600);
    border: 1px solid #fed7aa;
}

@keyframes dm-pulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.35;
    }
}

.fade-scale-enter-active,
.fade-scale-leave-active {
    transition:
        opacity 0.18s ease,
        transform 0.18s ease;
}

.fade-scale-enter-from,
.fade-scale-leave-to {
    opacity: 0;
    transform: scale(0.98);
}

@media (max-width: 1320px) {
    .personal-hero__content,
    .dashboard-grid {
        grid-template-columns: 1fr;
    }

    .dashboard-side {
        position: static;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .action-list.is-scrollable {
        max-height: 18rem;
    }
}

@media (max-width: 1080px) {
    .awareness-strip {
        grid-template-columns: repeat(3, minmax(12rem, 1fr));
    }

    .module-top-grid {
        grid-template-columns: 1fr;
    }

    .dashboard-side {
        grid-template-columns: 1fr;
    }

    .today-status-strip {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .lms-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .personal-hero__content {
        grid-template-columns: 1fr;
    }

    .workday-brief-card {
        height: auto;
    }

    .leader-summary-split {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 760px) {
    /* .personal-dashboard-page {
        padding: 0.5rem;
    } */

    .personal-hero {
        padding: 0.72rem;
        border-radius: 0.95rem;
    }

    .hero-title-row {
        flex-direction: column;
    }

    .hero-title-row p {
        font-size: 0.74rem;
    }

    .today-status-strip {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.36rem;
    }

    .today-chip {
        min-height: 3.3rem;
        padding: 0.46rem;
    }

    .today-chip.is-wide {
        grid-column: 1 / -1;
    }

    .awareness-strip {
        grid-template-columns: repeat(5, minmax(11.8rem, 1fr));
        gap: 0.42rem;
        overflow-x: auto;
        overscroll-behavior-inline: contain;
        scroll-snap-type: x proximity;
        scrollbar-width: none;
        padding: 0.04rem 0.02rem 0.24rem;
    }

    .awareness-card {
        min-height: 3.38rem;
        padding: 0.52rem 0.6rem;
        scroll-snap-align: start;
    }

    .awareness-card__icon {
        width: 2.05rem;
        height: 2.05rem;
    }

    .awareness-card__copy strong {
        font-size: 0.96rem;
    }

    .awareness-strip::-webkit-scrollbar {
        display: none;
    }

    .panel-head {
        flex-direction: column;
        gap: 0.42rem;
    }

    .calendar-filters {
        justify-content: flex-start;
    }

    .attendance-summary-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .leader-summary-split,
    .leader-mini-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .kpi-layout {
        grid-template-columns: 1fr;
    }

    .kpi-score-card {
        min-height: 5.7rem;
    }

    .kpi-status-panel {
        grid-template-columns: 1fr;
    }

    .overtime-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

        .overtime-assignment-item {
        padding: 0.85rem 1rem;
        gap: 0.75rem;
    }

    .overtime-assignment-item__content {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }

    .overtime-assignment-col-info {
        flex: none;
    }

    .overtime-assignment-col-reason {
        flex: none;
    }

    .overtime-assignment-col-meta {
        flex: none;
        justify-content: flex-start;
        gap: 0.35rem;
    }

    .overtime-assignment-col-status {
        flex: none;
        justify-content: flex-start;
        order: -1;
    }

    .overtime-detail-grid {
        grid-template-columns: 1fr;
    }

    .overtime-detail-hero,
    .overtime-detail-status {
        flex-direction: column;
    }

    .period-badge {
        max-width: 8rem;
    }

    .panel-card {
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        background: rgba(255, 255, 255, 0.97);
    }

    .module-card:hover,
    .side-panel:hover {
        transform: none;
    }
}

@media (max-width: 520px) {
    .hero-topline {
        gap: 0.3rem;
    }

    .hero-badge,
    .hero-period,
    .hero-role-pill {
        font-size: 0.56rem;
        padding: 0.22rem 0.4rem;
    }

    .hero-title-row h1 {
        font-size: 1.22rem;
    }

    .brief-grid,
    .today-status-strip {
        grid-template-columns: 1fr;
    }

    .awareness-strip {
        grid-template-columns: repeat(5, minmax(11.2rem, 1fr));
    }

    .dashboard-grid,
    .dashboard-main,
    .dashboard-side {
        gap: 0.5rem;
    }

    .module-card,
    .attendance-panel,
    .side-panel {
        padding: 0.58rem;
    }

    .calendar-grid {
        min-width: 650px;
    }

    .calendar-day {
        min-height: 5.6rem;
    }

    .lms-stat-grid,
    .kpi-stat-grid,
    .overtime-summary-grid,
    .leader-mini-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .leader-summary-split {
        grid-template-columns: 1fr;
    }

    .dm-raw-item {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        padding: 0.52rem 0.68rem;
        gap: 0.5rem;
    }

    .dm-raw-item__time strong {
        font-size: 0.82rem;
    }

    .dm-raw-item__badges {
        justify-content: flex-end;
    }

    .dm-compare__col {
        padding: 0.65rem 0.75rem;
        gap: 0.4rem;
    }

    .dm-hero__time > strong {
        font-size: 1.45rem;
    }

    .chart-block {
        padding: 0.34rem;
    }
}

/* --- START UI/UX OPTIMIZATIONS --- */
/* Smooth scroll behavior */
.personal-dashboard-container {
    scroll-behavior: smooth;
}

/* Base interactive feedback for mobile */
.action-card,
.mobile-quick-action-folder,
.attendance-donut-action {
    transition:
        transform 0.2s cubic-bezier(0.4, 0, 0.2, 1),
        background-color 0.2s ease,
        opacity 0.2s ease;
}

.action-card:active,
.mobile-quick-action-folder:active,
.attendance-donut-action:active {
    transform: scale(0.97);
    opacity: 0.9;
}

/* Pulse animation for important stats */
.kpi-score-card strong,
.side-count {
    animation: fadeInScale 0.4s ease-out;
}

@keyframes fadeInScale {
    0% {
        opacity: 0;
        transform: scale(0.95);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}

/* ═══════════════════════════════════════════════════════════════
   SKELETON SHIMMER — background-position sweep
   Uses the same reliable technique as loading-shimmer:
   a gradient background larger than the element, then animate
   background-position to create a light-sweep effect.
   ═══════════════════════════════════════════════════════════════ */
.leader-skeleton-line,
.skeleton-avatar {
    background: linear-gradient(90deg, var(--hcis-slate-200) 0%, var(--hcis-slate-100) 40%, var(--hcis-slate-200) 60%, var(--hcis-slate-200) 100%) !important;
    background-size: 250% 100% !important;
    animation: loading-shimmer 1.6s ease-in-out infinite !important;
}

.theme-dark .leader-skeleton-line,
.theme-dark .skeleton-avatar {
    background: linear-gradient(90deg, var(--hcis-slate-800) 0%, var(--hcis-slate-700) 40%, var(--hcis-slate-800) 60%, var(--hcis-slate-800) 100%) !important;
    background-size: 250% 100% !important;
    animation: loading-shimmer 1.6s ease-in-out infinite !important;
}

/* Maintain light-colored skeletons on dark backgrounds (hero section) */
.today-chip.is-skeleton .leader-skeleton-line,
.workday-brief-card .leader-skeleton-line,
.mobile-hero .leader-skeleton-line {
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0.12) 0%,
        rgba(255, 255, 255, 0.28) 40%,
        rgba(255, 255, 255, 0.12) 60%,
        rgba(255, 255, 255, 0.12) 100%
    ) !important;
    background-size: 250% 100% !important;
    animation: loading-shimmer 1.6s ease-in-out infinite !important;
}

/* Accordion smooth toggle */
.mobile-accordion__body {
    animation: slideDownFade 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    transform-origin: top;
}

@keyframes slideDownFade {
    0% {
        opacity: 0;
        transform: translateY(-10px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

/* KPI Donut entry animation */
.attendance-donut-chart {
    animation: fadeIn 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}
/* --- END UI/UX OPTIMIZATIONS --- */

/* ── Card Entrance Animation System ─────────────────── */
@keyframes card-entrance {
    0% {
        opacity: 0;
        transform: translateY(22px) scale(0.985);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Base card animation classes */
.personal-hero,
.awareness-strip,
.dashboard-main > .panel-card,
.dashboard-side > .panel-card,
.mobile-dashboard__body > .mobile-section {
    animation: card-entrance 0.85s cubic-bezier(0.16, 1, 0.3, 1) both;
}

/* Staggered entrance delays - Desktop */
.personal-hero {
    animation-delay: 50ms;
}
.awareness-strip {
    animation-delay: 110ms;
}
.dashboard-main > .panel-card:nth-child(1) {
    animation-delay: 170ms;
}
.dashboard-main > .panel-card:nth-child(2) {
    animation-delay: 250ms;
}
.dashboard-main > .panel-card:nth-child(3) {
    animation-delay: 330ms;
}
.dashboard-main > .panel-card:nth-child(4) {
    animation-delay: 410ms;
}

.dashboard-side > .panel-card:nth-child(1) {
    animation-delay: 210ms;
}
.dashboard-side > .panel-card:nth-child(2) {
    animation-delay: 290ms;
}
.dashboard-side > .panel-card:nth-child(3) {
    animation-delay: 370ms;
}
.dashboard-side > .panel-card:nth-child(4) {
    animation-delay: 450ms;
}

/* Staggered entrance delays - Mobile Accordions */
.mobile-dashboard__body > .mobile-section:nth-child(1) {
    animation-delay: 60ms;
}
.mobile-dashboard__body > .mobile-section:nth-child(2) {
    animation-delay: 130ms;
}
.mobile-dashboard__body > .mobile-section:nth-child(3) {
    animation-delay: 200ms;
}
.mobile-dashboard__body > .mobile-section:nth-child(4) {
    animation-delay: 270ms;
}
.mobile-dashboard__body > .mobile-section:nth-child(5) {
    animation-delay: 340ms;
}
.mobile-dashboard__body > .mobile-section:nth-child(6) {
    animation-delay: 410ms;
}
.mobile-dashboard__body > .mobile-section:nth-child(7) {
    animation-delay: 480ms;
}

/* ── Mobile: strip desktop radial gradients & blur ─ */
@media (max-width: 991.98px) {
    /* --- Mobile Accordion Components --- */
    .mobile-accordion-card {
        padding: 0.5rem 0.6rem !important;
        margin-top: 0.35rem !important;
        margin-bottom: 0.35rem !important;
        border: 1px solid var(--hcis-slate-200);
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
        border-radius: 0.75rem;
        min-height: auto !important;
        height: auto !important;
    }

    .mobile-accordion__trigger {
        width: 100%;
        padding: 0;
        border: 0;
        background: transparent;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        text-align: left;
        cursor: pointer;
        font-family: inherit;
        min-height: 2.8rem;
        border-radius: 0.5rem;
        transition: background 150ms ease;
    }

    .mobile-accordion__trigger:hover {
        background: rgba(15, 23, 42, 0.025);
    }

    .mobile-accordion-card .module-title {
        flex: 1;
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .mobile-accordion-card .module-icon {
        width: 2rem;
        height: 2rem;
        display: grid;
        place-items: center;
        border-radius: 0.5rem;
        font-size: 0.95rem;
        flex-shrink: 0;
    }

    .mobile-accordion-card .module-icon.is-purple {
        background: var(--hcis-purple-100);
        color: var(--hcis-purple-600);
    }
    .mobile-accordion-card .module-icon.is-orange {
        background: var(--hcis-orange-100);
        color: var(--hcis-orange-600);
    }
    .mobile-accordion-card .module-icon.is-blue {
        background: #dbeafe;
        color: var(--hcis-blue-600);
    }
    .mobile-accordion-card .module-icon.is-emerald {
        background: var(--hcis-emerald-100);
        color: var(--hcis-emerald-600);
    }

    .mobile-accordion-card .module-title-copy {
        flex: 1;
        display: grid;
        gap: 0.08rem;
        min-width: 0;
    }

    .mobile-accordion-card .section-label {
        font-size: 0.58rem;
        letter-spacing: 0.08em;
        line-height: 1;
        color: var(--hcis-slate-500);
        text-transform: uppercase;
        font-weight: 800;
    }

    .mobile-accordion-card .module-title-copy h2 {
        margin: 0;
        font-size: 0.82rem;
        font-weight: 900;
        line-height: 1.2;
        color: var(--hcis-slate-800);
        display: -webkit-box;
        overflow: hidden;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    .mobile-accordion__meta {
        flex: 0 0 auto;
        min-width: 3.5rem;
        display: inline-flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.35rem;
    }

    .mobile-accordion__meta i {
        font-size: 0.85rem;
        color: var(--hcis-slate-500);
        transition: transform 0.2s;
    }

    .accordion-enter-active,
    .accordion-leave-active {
        transition:
            height 0.3s cubic-bezier(0.4, 0, 0.2, 1),
            opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1),
            margin-top 0.3s cubic-bezier(0.4, 0, 0.2, 1),
            padding-top 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden !important;
    }
    .accordion-enter-from,
    .accordion-leave-to {
        opacity: 0;
        margin-top: 0 !important;
        padding-top: 0 !important;
        border-top-color: transparent !important;
    }

    .mobile-accordion__content {
        padding-top: 0.5rem;
    }
    .personal-dashboard-page {
        background: transparent;
    }

    .panel-card {
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        background: rgba(255, 255, 255, 0.97);
    }
}

/* ── Enterprise Polish: scrollbar, focus, perf ───────────────────────────
   Konsolidasi peningkatan kualitas lintas-section. Ditempatkan sebelum
   blok reduced-motion agar preferensi aksesibilitas tetap menang terakhir. */

/* Custom scrollbar modern & halus pada area scrollable internal */
.personal-dashboard-page :is(.panel-card, .mobile-accordion__body, .dashboard-main, .side-panel) {
    scrollbar-width: thin;
    scrollbar-color: var(--slate-300) transparent;
}
.personal-dashboard-page :is(.panel-card, .mobile-accordion__body, .dashboard-main, .side-panel)::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.personal-dashboard-page
    :is(.panel-card, .mobile-accordion__body, .dashboard-main, .side-panel)::-webkit-scrollbar-thumb {
    background: var(--slate-300);
    border-radius: var(--radius-pill);
}
.personal-dashboard-page
    :is(.panel-card, .mobile-accordion__body, .dashboard-main, .side-panel)::-webkit-scrollbar-thumb:hover {
    background: var(--slate-400);
}
.personal-dashboard-page ::-webkit-scrollbar-track {
    background: transparent;
}

/* Aksesibilitas: focus ring konsisten hanya saat navigasi keyboard */
.personal-dashboard-page :is(button, a, [role='button'], [tabindex]):focus-visible {
    outline: 2px solid var(--indigo-500);
    outline-offset: 2px;
    border-radius: var(--radius-sm);
}
.personal-dashboard-page :is(button, a, [role='button']):focus:not(:focus-visible) {
    outline: none;
}

/* Performa render: lewati paint untuk panel berat yang jauh di bawah lipatan.
   Tidak diterapkan ke kolom utama / kartu berisi chart agar Apex tetap
   ter-render benar; hanya panel mandiri di bawah grid utama. Mobile saja
   dilewati karena single-column sudah ringan. */
@media (min-width: 992px) {
    .personal-dashboard-page .dashboard-main > .panel-card:not(:first-of-type) {
        content-visibility: auto;
        contain-intrinsic-size: auto 520px;
    }
}

/* Konsistensi tap target di sentuh (mobile) — minimal 44px */
@media (hover: none) and (pointer: coarse) {
    .personal-dashboard-page :is(.quick-action-tile, .mobile-tab, button.calendar-day) {
        min-height: 44px;
    }
}

/* ── Reduced Motion Support ──────────────────────── */
@media (prefers-reduced-motion: reduce) {
    .personal-hero,
    .awareness-strip,
    .dashboard-main > .panel-card,
    .dashboard-side > .panel-card,
    .mobile-dashboard__body > .mobile-section {
        animation: none !important;
        opacity: 1 !important;
        transform: none !important;
    }

    .panel-card,
    .awareness-card,
    .module-card,
    .side-panel {
        transition: none;
    }

    .leader-skeleton-line,
    .skeleton-avatar {
        background: var(--hcis-slate-200) !important;
        animation: none !important;
    }

    .team-dashboard-badge,
    .mobile-accordion__body,
    .attendance-donut-chart {
        animation: none;
    }
}
</style>
