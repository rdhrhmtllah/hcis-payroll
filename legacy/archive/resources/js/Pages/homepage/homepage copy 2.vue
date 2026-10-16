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
                            <article
                                class="mobile-hero"
                                :class="[`is-${todayAwareness.tone}`, { 'is-skeleton': isDashboardBootstrapping }]"
                            >
                                <div class="mobile-hero__glow mobile-hero__glow--one"></div>
                                <div class="mobile-hero__glow mobile-hero__glow--two"></div>

                                <div class="mobile-hero__header">
                                    <div class="mobile-hero__topline">
                                        <div>
                                            <template v-if="isDashboardBootstrapping">
                                                <div class="leader-skeleton-line is-title" style="width: 48%"></div>
                                                <div class="leader-skeleton-line mb-1" style="width: 65%"></div>
                                                <div class="leader-skeleton-line is-short"></div>
                                            </template>

                                            <template v-else>
                                                <span class="mobile-hero__eyebrow">{{ mobileHeroDateLabel }}</span>
                                                <strong class="mobile-hero__shift">{{
                                                    todaySnapshot.shiftLabel
                                                }}</strong>
                                                <small class="mobile-hero__meta">{{ todaySnapshot.shiftTime }}</small>
                                            </template>
                                        </div>
                                    </div>

                                    <div class="mobile-hero__actions">
                                        <div
                                            class="mobile-status-pill"
                                            :class="
                                                isDashboardBootstrapping ? 'is-skeleton' : `is-${mobileHeroStatus.tone}`
                                            "
                                        >
                                            <template v-if="isDashboardBootstrapping">
                                                <div class="skeleton-avatar" style="width: 36px; height: 24px"></div>
                                                <div class="leader-skeleton-line is-short" style="width: 40%"></div>
                                            </template>

                                            <template v-else>
                                                <i :class="mobileHeroStatus.icon"></i>
                                                <span>{{ todaySnapshot.statusLabel }}</span>
                                            </template>
                                        </div>

                                        <button
                                            v-if="isLeader && !isDashboardBootstrapping"
                                            @click="handleVisit('/homepage/leader-dashboard')"
                                            type="button"
                                            class="mobile-hero-team-btn"
                                            :class="{ 'has-badge': teamDashboardBadgeVisible }"
                                            title="Dashboard Tim"
                                        >
                                            <i class="bi bi-grid-1x2-fill"></i>
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

                                <div class="mobile-hero__times">
                                    <div class="mobile-time-card">
                                        <template v-if="isDashboardBootstrapping">
                                            <div class="leader-skeleton-line is-title"></div>
                                            <div class="leader-skeleton-line mb-1" style="width: 65%"></div>
                                            <div class="leader-skeleton-line is-short"></div>
                                        </template>

                                        <template v-else>
                                            <span>Check In</span>
                                            <strong>{{ todaySnapshot.checkIn }}</strong>
                                            <small>{{ todaySnapshot.checkInMeta }}</small>
                                        </template>
                                    </div>

                                    <div class="mobile-time-card">
                                        <template v-if="isDashboardBootstrapping">
                                            <div class="leader-skeleton-line is-title"></div>
                                            <div class="leader-skeleton-line mb-1" style="width: 65%"></div>
                                            <div class="leader-skeleton-line is-short"></div>
                                        </template>

                                        <template v-else>
                                            <span>Check Out</span>
                                            <strong>{{ todaySnapshot.checkOut }}</strong>
                                            <small>{{ todaySnapshot.checkOutMeta }}</small>
                                        </template>
                                    </div>
                                </div>

                                <div class="mobile-hero__note">
                                    <template v-if="isDashboardBootstrapping">
                                        <div class="leader-skeleton-line" style="width: 75%"></div>
                                    </template>

                                    <template v-else>
                                        <template v-if="todayAwareness.icon === 'lottie'">
                                            <DotLottieVue
                                                src="/animation/Assistant.lottie"
                                                :height="34"
                                                :width="34"
                                                :speed="1"
                                                autoplay
                                                loop
                                                class="mobile-hero__lottie"
                                            />
                                        </template>
                                        <i v-else :class="todayAwareness.icon"></i>
                                        <span>{{ todayAwareness.message }}</span>
                                    </template>
                                </div>

                                <div class="mobile-hero__brief">
                                    <div>
                                        <template v-if="isDashboardBootstrapping">
                                            <div class="leader-skeleton-line is-title" style="width: 55%"></div>
                                        </template>

                                        <template v-else>
                                            <span>Cutoff</span>
                                            <strong>{{ cutoffMonthLabel }}, {{ workdayBrief.cutoffProgress }}</strong>
                                        </template>
                                    </div>

                                    <div>
                                        <template v-if="isDashboardBootstrapping">
                                            <div class="leader-skeleton-line is-title" style="width: 55%"></div>
                                        </template>

                                        <template v-else>
                                            <span>Next Shift</span>
                                            <strong>{{ workdayBrief.nextSchedule }}</strong>
                                        </template>
                                    </div>

                                    <div>
                                        <template v-if="isDashboardBootstrapping">
                                            <div class="leader-skeleton-line is-title" style="width: 45%"></div>
                                        </template>

                                        <template v-else>
                                            <span>KPI</span>
                                            <strong>{{ heroKpi.periodLabel }}</strong>
                                        </template>
                                    </div>
                                </div>
                            </article>

                            <section class="mobile-awareness-grid">
                                <button
                                    v-for="item in mobileAwarenessCards"
                                    :key="item.key"
                                    type="button"
                                    class="mobile-awareness-card"
                                    :class="[`is-${item.tone}`, { 'is-skeleton': item.loading }]"
                                    @click="handleAwarenessClick(item.key)"
                                >
                                    <div v-if="item.loading" class="mobile-awareness-card__icon skeleton-avatar"></div>

                                    <div v-else class="mobile-awareness-card__icon">
                                        <i :class="item.icon"></i>
                                    </div>

                                    <div v-if="item.loading" class="mobile-awareness-card__copy">
                                        <div class="leader-skeleton-line is-title mb-1" style="width: 45%"></div>
                                        <div class="leader-skeleton-line mb-1" style="width: 75%"></div>
                                        <div class="leader-skeleton-line is-short"></div>
                                    </div>

                                    <div v-else class="mobile-awareness-card__copy">
                                        <strong>{{ item.value }}</strong>
                                        <span>{{ item.label }}</span>
                                        <small>{{ item.description }}</small>
                                    </div>
                                </button>
                            </section>

                            <section class="panel-card mobile-mini-calendar">
                                <div class="mobile-card-head">
                                    <div class="mobile-card-head__copy">
                                        <span class="section-label">Absensi Mingguan</span>
                                        <h2>{{ mobileCalendarMonthLabel }}</h2>
                                    </div>

                                    <div class="mobile-mini-calendar__actions">
                                        <!-- Button navigasi ke tanggal hari ini-->
                                        <div class="mobile-week-nav" aria-label="Navigasi minggu">
                                            <!-- <button
                                                type="button"
                                                class="mobile-week-nav__button mobile-week-nav__button--today"
                                                :disabled="isMobileWeekNavBusy || isCurrentWeek"
                                                aria-label="Minggu ini"
                                                @click="goToCurrentWeek"
                                            >
                                                <span class="mobile-mini-calendar__day-label">Now</span>
                                            </button> -->
                                            <button
                                                type="button"
                                                class="mobile-week-nav__button mobile-week-nav__button--today"
                                                :disabled="isMobileWeekNavBusy || isCurrentWeek"
                                                aria-label="Minggu ini"
                                                @click="goTo"
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

                                        <!-- <button
                                            type="button"
                                            class="mobile-inline-link"
                                            @click="scrollToMobileSection('absensi')"
                                        >
                                            Lihat cutoff
                                        </button> -->
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
                                            :class="[`is-${day.primaryStatusTone}`, { 'is-today': day.isToday }]"
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
                                                :title="day.primaryStatusLabel"
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

                            <section class="panel-card side-panel mobile-quick-actions">
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

                            <section
                                class="panel-card side-panel attendance-donut-card mobile-attendance-donut-card"
                                @click="handleAttendanceDonutDetail('all')"
                            >
                                <div class="attendance-donut-card__head">
                                    <div>
                                        <span class="section-label">Absensi Cutoff</span>
                                        <h2>Komposisi Kehadiran</h2>
                                    </div>

                                    <span class="side-count">{{ attendanceDonutSummary.percentLabel }}</span>
                                </div>

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
                                        <span v-for="n in 4" :key="`mobile-attendance-donut-skeleton-${n}`"></span>
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
                            </section>

                            <section class="panel-card side-panel mobile-accordion-card">
                                <button
                                    type="button"
                                    class="mobile-accordion__trigger"
                                    @click="toggleMobilePanel('action')"
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
                                        <span class="side-count">{{ pendingItems.length }}</span>
                                        <i
                                            :class="mobilePanelState.action ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"
                                        ></i>
                                    </div>
                                </button>

                                <div v-if="mobilePanelState.action" class="mobile-accordion__body">
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
                                                    <div class="leader-skeleton-line is-short mt-1"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <template v-else>
                                            <div
                                                v-for="item in compactPendingItems"
                                                :key="item.id"
                                                role="button"
                                                tabindex="0"
                                                class="action-card"
                                                :class="`is-${item.tone}`"
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
                                                    <button
                                                        v-if="item.dismissible"
                                                        type="button"
                                                        class="action-card__done"
                                                        :disabled="isNotificationDismissBusy(item)"
                                                        @click.stop="dismissPendingNotification(item)"
                                                    >
                                                        Mark Done
                                                    </button>
                                                    <i class="bi bi-arrow-right-short"></i>
                                                </div>
                                            </div>

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
                            </section>
                        </section>

                        <section :ref="(el) => setMobileSectionRef('kpi', el)" class="mobile-section">
                            <article class="panel-card module-card mobile-accordion-card">
                                <button
                                    type="button"
                                    class="mobile-accordion__trigger"
                                    @click="toggleMobilePanel('kpi')"
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

                                <div v-if="mobilePanelState.kpi" class="mobile-accordion__body">
                                    <div
                                        v-if="isKpiLoading || isKpiRefreshing || isDashboardBootstrapping"
                                        class="module-skeleton is-kpi"
                                    >
                                        <div class="kpi-layout mobile-kpi-layout">
                                            <div class="kpi-score-card is-skeleton">
                                                <div
                                                    class="leader-skeleton-line is-title mb-2"
                                                    style="width: 55%"
                                                ></div>
                                                <div class="leader-skeleton-line mb-1" style="width: 70%"></div>
                                                <div class="leader-skeleton-line is-short"></div>
                                            </div>

                                            <div class="kpi-stat-grid">
                                                <div
                                                    v-for="n in 4"
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

                                    <div v-else class="kpi-detail-stack" :class="{ 'is-refreshing': isKpiRefreshing }">
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
                                                        <small>{{ heroKpi.ytdSubmittedCount }} periode terisi</small>
                                                    </div>
                                                </div>

                                                <div class="kpi-stat-grid">
                                                    <div>
                                                        <span>Performance</span>
                                                        <strong>{{ heroKpi.performanceItemCount }}</strong>
                                                        <small>item terisi</small>
                                                    </div>

                                                    <div>
                                                        <span>Pending</span>
                                                        <strong>{{ heroKpi.pendingCount }}</strong>
                                                        <small>Perlu ditinjau</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="kpi-status-panel">
                                                <div class="kpi-status-panel__item">
                                                    <span>KPI Parameter</span>
                                                    <strong
                                                        :class="
                                                            heroKpi.parameterSubmitted ? 'is-success' : 'is-warning'
                                                        "
                                                        >{{
                                                            heroKpi.parameterSubmitted
                                                                ? 'Sudah mengisi'
                                                                : heroKpi.parameterHasTargets
                                                                  ? 'Target tersedia'
                                                                  : 'Belum mengisi'
                                                        }}</strong
                                                    >
                                                </div>

                                                <div class="kpi-status-panel__item">
                                                    <span>Update Performance</span>
                                                    <strong
                                                        :class="
                                                            heroKpi.performanceSubmitted ? 'is-success' : 'is-warning'
                                                        "
                                                        >{{
                                                            heroKpi.performanceSubmitted
                                                                ? 'Sudah mengisi'
                                                                : 'Belum mengisi'
                                                        }}</strong
                                                    >
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
                                                            class="mini-progress__fill is-purple"
                                                            :style="{ width: `${safePercent(param.achievement)}%` }"
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
                            </article>
                        </section>

                        <section :ref="(el) => setMobileSectionRef('kpi', el)" class="mobile-section">
                            <article class="panel-card module-card mobile-accordion-card">
                                <button
                                    type="button"
                                    class="mobile-accordion__trigger"
                                    @click="toggleMobilePanel('kpi')"
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

                                <div v-if="mobilePanelState.kpi" class="mobile-accordion__body">
                                    <div
                                        v-if="isKpiLoading || isKpiRefreshing || isDashboardBootstrapping"
                                        class="module-skeleton is-kpi"
                                    >
                                        <div class="kpi-layout mobile-kpi-layout">
                                            <div class="kpi-score-card is-skeleton">
                                                <div
                                                    class="leader-skeleton-line is-title mb-2"
                                                    style="width: 55%"
                                                ></div>
                                                <div class="leader-skeleton-line mb-1" style="width: 70%"></div>
                                                <div class="leader-skeleton-line is-short"></div>
                                            </div>

                                            <div class="kpi-stat-grid">
                                                <div
                                                    v-for="n in 4"
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

                                    <div v-else class="kpi-detail-stack" :class="{ 'is-refreshing': isKpiRefreshing }">
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
                                                        <small>{{ heroKpi.ytdSubmittedCount }} periode terisi</small>
                                                    </div>
                                                </div>

                                                <div class="kpi-stat-grid">
                                                    <div>
                                                        <span>Performance</span>
                                                        <strong>{{ heroKpi.performanceItemCount }}</strong>
                                                        <small>item terisi</small>
                                                    </div>

                                                    <div>
                                                        <span>Pending</span>
                                                        <strong>{{ heroKpi.pendingCount }}</strong>
                                                        <small>Perlu ditinjau</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="kpi-status-panel">
                                                <div class="kpi-status-panel__item">
                                                    <span>KPI Parameter</span>
                                                    <strong
                                                        :class="
                                                            heroKpi.parameterSubmitted ? 'is-success' : 'is-warning'
                                                        "
                                                        >{{
                                                            heroKpi.parameterSubmitted
                                                                ? 'Sudah mengisi'
                                                                : heroKpi.parameterHasTargets
                                                                  ? 'Target tersedia'
                                                                  : 'Belum mengisi'
                                                        }}</strong
                                                    >
                                                </div>

                                                <div class="kpi-status-panel__item">
                                                    <span>Update Performance</span>
                                                    <strong
                                                        :class="
                                                            heroKpi.performanceSubmitted ? 'is-success' : 'is-warning'
                                                        "
                                                        >{{
                                                            heroKpi.performanceSubmitted
                                                                ? 'Sudah mengisi'
                                                                : 'Belum mengisi'
                                                        }}</strong
                                                    >
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
                                                            class="mini-progress__fill is-purple"
                                                            :style="{ width: `${safePercent(param.achievement)}%` }"
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
                            </article>
                        </section>

                        <section :ref="(el) => setMobileSectionRef('lms', el)" class="mobile-section">
                            <article class="panel-card module-card mobile-accordion-card">
                                <button
                                    type="button"
                                    class="mobile-accordion__trigger"
                                    @click="toggleMobilePanel('lms')"
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

                                <div v-if="mobilePanelState.lms" class="mobile-accordion__body">
                                    <div v-if="isLmsLoading || isDashboardBootstrapping" class="module-skeleton is-lms">
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
                                                    >{{ heroLms.completed }}/{{ heroLms.total }} training selesai</small
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
                                                <small>Rata-rata/bulan</small>
                                            </div>

                                            <div>
                                                <span>Learning Gain</span>
                                                <strong>{{
                                                    heroLms.learningGain ? `+${heroLms.learningGain}%` : '-'
                                                }}</strong>
                                                <small>Pre/Post test</small>
                                            </div>
                                        </div>

                                        <div class="chart-block lms-duration-card">
                                            <div class="chart-head">
                                                <span>Durasi Belajar Per Bulan</span>
                                                <strong>{{ heroLms.avgLearningDurationLabel }} avg</strong>
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
                                                            course.deadline || course.remaining || 'Tidak ada deadline'
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
                            </article>
                        </section>

                        <section :ref="(el) => setMobileSectionRef('more', el)" class="mobile-section">
                            <article
                                v-if="!isLeader"
                                ref="overtimeSectionRef"
                                class="panel-card module-card mobile-accordion-card"
                            >
                                <button
                                    type="button"
                                    class="mobile-accordion__trigger"
                                    @click="toggleMobilePanel('overtime')"
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
                                        <span class="period-badge is-orange">{{ overtimeSummary.periodLabel }}</span>
                                        <i
                                            :class="
                                                mobilePanelState.overtime ? 'bi bi-chevron-up' : 'bi bi-chevron-down'
                                            "
                                        ></i>
                                    </div>
                                </button>

                                <div v-if="mobilePanelState.overtime" class="mobile-accordion__body">
                                    <div
                                        v-if="isOvertimeLoading || isDashboardBootstrapping"
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
                                                <strong>{{ overtimeSummary.totalHours }}h</strong>
                                                <small>{{ overtimeSummary.periodLabel }}</small>
                                            </div>

                                            <div>
                                                <span>Sebelum</span>
                                                <strong>{{ overtimeSummary.beforeWorkHours }}h</strong>
                                                <small>Early overtime</small>
                                            </div>

                                            <div>
                                                <span>Setelah</span>
                                                <strong>{{ overtimeSummary.afterWorkHours }}h</strong>
                                                <small>After shift</small>
                                            </div>

                                            <div>
                                                <span>Libur</span>
                                                <strong>{{ overtimeSummary.holidayHours }}h</strong>
                                                <small>Hari libur</small>
                                            </div>
                                        </div>

                                        <div class="module-alert is-info">
                                            <i class="bi bi-person-check"></i>
                                            <span>{{ overtimeSummary.assignmentNote }}</span>
                                        </div>

                                        <div class="chart-block">
                                            <div class="chart-head">
                                                <span>Trend Lembur</span>
                                                <strong>{{ overtimeSummary.totalThisMonth }}h bulan ini</strong>
                                            </div>

                                            <div class="chart-safe">
                                                <VueApexCharts
                                                    v-if="overtimeSummary.monthlyTrend.length"
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
                                                    description="Belum ada histori lembur bulanan."
                                                />
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </article>

                            <article class="panel-card side-panel mobile-accordion-card">
                                <button
                                    type="button"
                                    class="mobile-accordion__trigger"
                                    @click="toggleMobilePanel('submissions')"
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

                                <div v-if="mobilePanelState.submissions" class="mobile-accordion__body">
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
                                                <div class="leader-skeleton-line is-short" style="width: 4rem"></div>
                                            </div>
                                        </div>

                                        <template v-else>
                                            <button
                                                v-for="item in compactSubmissions"
                                                :key="item.id"
                                                type="button"
                                                class="submission-item"
                                                @click="item.href ? visit(item.href) : null"
                                            >
                                                <div>
                                                    <strong>{{ item.title }}</strong>
                                                    <span>{{ item.date }} • {{ item.description }}</span>
                                                    <em v-if="item.expiryNote">{{ item.expiryNote }}</em>
                                                </div>

                                                <small :class="`is-${item.status}`">
                                                    {{ item.statusLabel }}
                                                </small>
                                            </button>

                                            <EmptyState
                                                v-if="!isDashboardBootstrapping && !compactSubmissions.length"
                                                compact
                                                icon="bi bi-file-earmark-check"
                                                title="Belum ada pengajuan"
                                                description="Pengajuan aktif akan muncul di sini."
                                            />
                                        </template>
                                    </div>
                                </div>
                            </article>

                            <article class="panel-card side-panel mobile-accordion-card">
                                <button
                                    type="button"
                                    class="mobile-accordion__trigger"
                                    @click="toggleMobilePanel('upcoming')"
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

                                <div v-if="mobilePanelState.upcoming" class="mobile-accordion__body">
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
                                                <div class="leader-skeleton-line is-short" style="width: 3rem"></div>
                                            </div>
                                        </div>

                                        <template v-else>
                                            <div
                                                v-for="item in compactUpcoming"
                                                :key="`${item.date}-${item.label}`"
                                                class="schedule-item"
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
                            </article>
                        </section>

                        <section :ref="(el) => setMobileSectionRef('absensi', el)" class="mobile-section">
                            <section class="panel-card attendance-panel mobile-attendance-panel">
                                <div class="mobile-card-head mobile-card-head--stack">
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
                                        <div
                                            v-for="n in 4"
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
                                                <div class="leader-skeleton-line is-short" style="width: 55%"></div>
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
                                                <strong>{{ countCalendarByStatus('present') }}</strong>
                                                <span>Hadir</span>
                                            </div>

                                            <div class="summary-pill is-warning">
                                                <strong>{{ calendarTotalLateMinutes }}</strong>
                                                <span>Menit Terlambat</span>
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

                                                    <div class="calendar-day__shift">
                                                        {{ day.shiftLabel || 'OFF' }}
                                                    </div>

                                                    <div v-if="day.statusChips.length" class="calendar-day__chips">
                                                        <span
                                                            v-for="chip in day.statusChips"
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
                        </section>
                    </div>

                    <!-- <nav class="mobile-bottom-nav">
                        <button
                            v-for="tab in mobileTabs"
                            :key="`bottom-${tab.key}`"
                            type="button"
                            class="mobile-bottom-nav__item"
                            :class="{ 'is-active': activeMobileTab === tab.key }"
                            @click="scrollToMobileSection(tab.key)"
                        >
                            <i :class="tab.icon"></i>
                            <span>{{ tab.label }}</span>
                        </button>
                    </nav> -->
                </div>
            </template>

            <template v-else>
                <!-- HERO -->
                <section class="personal-hero">
                    <div class="hero-orb hero-orb--one"></div>
                    <div class="hero-orb hero-orb--two"></div>

                    <div class="personal-hero__content">
                        <div class="personal-hero__left">
                            <div class="hero-topline">
                                <!-- <div class="hero-badge">
                                    <template v-if="todayAwareness.icon === 'lottie'">
                                        <DotLottieVue
                                            src="/animation/Assistant.lottie"
                                            :height="14"
                                            :width="14"
                                            :speed="1"
                                            autoplay
                                            loop
                                            class="mobile-hero__lottie"
                                        />
                                    </template>
                                    <span>{{ todayAwareness.badge }}</span>
                                </div> -->

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

                            <!-- <div class="hero-summary-callout">
                                <div class="hero-summary-callout__icon">
                                    <i :class="heroSummary.focusIcon"></i>
                                </div>

                                <div class="hero-summary-callout__copy">
                                    <span>Fokus Hari Ini</span>
                                    <strong>{{ heroSummary.focusLabel }}</strong>
                                </div>
                            </div> -->

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
                <section class="awareness-strip">
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
                    </button>
                </section>

                <!-- MAIN GRID -->
                <section class="dashboard-grid">
                    <main class="dashboard-main">
                        <section class="module-top-grid">
                            <!-- HCIS -->
                            <article class="panel-card module-card hcis-insight-card">
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
                                                <span>7 cutoff terakhir</span>
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
                            <article class="panel-card module-card">
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

                                <div class="kpi-detail-stack" :class="{ 'is-refreshing': isKpiRefreshing }">
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
                                                <div class="kpi-stat-grid" style="grid-template-columns: 1fr">
                                                    <div>
                                                        <span>Achievement</span>
                                                        <strong>{{ heroKpi.achievement }}%</strong>
                                                        <small>periode terpilih</small>
                                                    </div>
                                                </div>
                                                <div class="kpi-status-panel">
                                                    <div class="kpi-status-panel__item">
                                                        <span>KPI Parameter</span>
                                                        <strong
                                                            :class="
                                                                heroKpi.parameterSubmitted ? 'is-success' : 'is-warning'
                                                            "
                                                            >{{
                                                                heroKpi.parameterSubmitted
                                                                    ? 'Sudah mengisi'
                                                                    : heroKpi.parameterHasTargets
                                                                      ? 'Target tersedia'
                                                                      : 'Belum mengisi'
                                                            }}</strong
                                                        >
                                                    </div>
                                                    <div class="kpi-status-panel__item">
                                                        <span>Update Performance</span>
                                                        <strong
                                                            :class="
                                                                heroKpi.performanceSubmitted
                                                                    ? 'is-success'
                                                                    : 'is-warning'
                                                            "
                                                            >{{
                                                                heroKpi.performanceSubmitted
                                                                    ? 'Sudah mengisi'
                                                                    : 'Belum mengisi'
                                                            }}</strong
                                                        >
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
                                            <div v-for="param in heroKpi.parameters" :key="param.id || param.name">
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
                                                        class="mini-progress__fill is-purple"
                                                        :style="{ width: `${safePercent(param.achievement)}%` }"
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
                            <article class="panel-card module-card">
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

                                <!-- 1. Progress Hero: same layout, skeleton or real -->
                                <div class="lms-hero">
                                    <div class="lms-progress-ring">
                                        <template v-if="!(isLmsLoading || isDashboardBootstrapping)">
                                            <span>Overall</span>
                                            <strong>{{ heroLms.progress }}%</strong>
                                        </template>
                                    </div>
                                    <div class="lms-hero__info">
                                        <template v-if="isLmsLoading || isDashboardBootstrapping">
                                            <div class="leader-skeleton-line is-short mb-1" style="width: 55%"></div>
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
                                            <small>{{ heroLms.completed }}/{{ heroLms.total }} training selesai</small>
                                        </template>
                                    </div>
                                </div>

                                <!-- 2. Quick Stats: same layout, skeleton or real -->
                                <div class="lms-quick-stats">
                                    <template v-if="isLmsLoading || isDashboardBootstrapping">
                                        <div v-for="n in 2" :key="`lms-stat-sk-${n}`" class="lms-stat-pill is-skeleton">
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
                                                <span>Learning Gain</span>
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
                                                            course.deadline || course.remaining || 'Tidak ada deadline'
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
                                            <strong>{{ heroLms.avgLearningDurationLabel }} avg</strong>
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
                            </article>

                            <!-- OVERTIME -->
                            <article
                                v-if="!isLeader"
                                ref="overtimeSectionRef"
                                class="panel-card module-card overtime-card"
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

                                    <span class="period-badge is-orange">{{ overtimeSummary.periodLabel }}</span>
                                </div>

                                <div
                                    v-if="isOvertimeLoading || isDashboardBootstrapping"
                                    class="module-skeleton is-overtime"
                                >
                                    <div class="overtime-summary-grid">
                                        <div v-for="n in 6" :key="`overtime-skeleton-${n}`" class="is-skeleton">
                                            <div class="leader-skeleton-line is-short mb-1"></div>
                                            <div class="leader-skeleton-line is-title mb-1" style="width: 55%"></div>
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
                                            <strong>{{ overtimeSummary.totalHours }}h</strong>
                                            <small>{{ overtimeSummary.periodLabel }}</small>
                                        </div>

                                        <div>
                                            <span>Hari Kerja</span>
                                            <strong>{{ overtimeSummary.workdayHours }}h</strong>
                                            <small>Awal + setelah kerja</small>
                                        </div>

                                        <div>
                                            <span>Sebelum Kerja</span>
                                            <strong>{{ overtimeSummary.beforeWorkHours }}h</strong>
                                            <small>Early overtime</small>
                                        </div>

                                        <div>
                                            <span>Setelah Kerja</span>
                                            <strong>{{ overtimeSummary.afterWorkHours }}h</strong>
                                            <small>After shift</small>
                                        </div>

                                        <div>
                                            <span>Hari Libur</span>
                                            <strong>{{ overtimeSummary.holidayHours }}h</strong>
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

                                    <div class="chart-block">
                                        <div class="chart-head">
                                            <span>Trend Lembur Bulanan</span>
                                            <strong>{{ overtimeSummary.totalThisMonth }}h bulan ini</strong>
                                        </div>

                                        <div class="chart-safe">
                                            <VueApexCharts
                                                v-if="overtimeSummary.monthlyTrend.length"
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
                                                description="Belum ada histori lembur bulanan."
                                            />
                                        </div>
                                    </div>
                                </template>
                            </article>
                        </section>

                        <!-- ATTENDANCE CALENDAR -->
                        <section ref="attendanceCalendarSectionRef" class="panel-card attendance-panel">
                            <div class="panel-head">
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
                                    <div v-for="n in 4" :key="`summary-skeleton-${n}`" class="summary-pill is-skeleton">
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
                                            <div class="leader-skeleton-line is-short" style="width: 55%"></div>
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
                                            <strong>{{ countCalendarByStatus('present') }}</strong>
                                            <span>Hadir</span>
                                        </div>

                                        <div class="summary-pill is-warning">
                                            <strong>{{ calendarTotalLateMinutes }}</strong>
                                            <span>Menit Terlambat</span>
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

                                        <!-- <div class="summary-pill is-orange">
                                        <strong>{{ countByStatus('expired') }}</strong>
                                        <span>Expired</span>
                                    </div> -->
                                    </div>

                                    <div class="calendar-scroll">
                                        <div class="calendar-grid">
                                            <!-- <div v-for="day in weekdayLabels" :key="day" class="calendar-weekday">
                                            {{ day }}
                                        </div> -->

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

                                                <div class="calendar-day__shift">
                                                    {{ day.shiftLabel || 'OFF' }}
                                                </div>

                                                <div v-if="day.statusChips.length" class="calendar-day__chips">
                                                    <span
                                                        v-for="chip in day.statusChips"
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
                    </main>

                    <!-- RIGHT SIDEBAR -->
                    <aside class="dashboard-side">
                        <section class="panel-card side-panel action-center-panel">
                            <div class="panel-head is-tight">
                                <div>
                                    <span class="section-label">Action Center</span>
                                    <h2>Perlu Saya Tindak</h2>
                                </div>

                                <span class="side-count">{{ pendingItems.length }}</span>
                            </div>

                            <div class="action-list is-scrollable">
                                <div
                                    v-if="isNeedsActionLoading || isDashboardBootstrapping"
                                    class="action-skeleton-list"
                                >
                                    <div v-for="n in 3" :key="`action-skeleton-${n}`" class="action-card is-skeleton">
                                        <div class="action-card__icon skeleton-avatar"></div>
                                        <div class="action-card__body">
                                            <div class="leader-skeleton-line is-title mb-1" style="width: 60%"></div>
                                            <div class="leader-skeleton-line" style="width: 85%"></div>
                                            <div class="leader-skeleton-line is-short mt-1"></div>
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
                                    :class="`is-${item.tone}`"
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
                                        <button
                                            v-if="item.dismissible"
                                            type="button"
                                            class="action-card__done"
                                            :disabled="isNotificationDismissBusy(item)"
                                            @click.stop="dismissPendingNotification(item)"
                                        >
                                            Mark Done
                                        </button>
                                        <i class="bi bi-arrow-right-short"></i>
                                    </div>
                                </div>

                                <div
                                    v-if="!isDashboardBootstrapping && !isNeedsActionLoading && needsActionError"
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
                        </section>

                        <section class="panel-card side-panel quick-action-panel">
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
                            class="panel-card side-panel attendance-donut-card"
                            @click="handleAttendanceDonutDetail('all')"
                        >
                            <div class="attendance-donut-card__head">
                                <div>
                                    <span class="section-label">Absensi Cutoff</span>
                                    <h2>Komposisi Kehadiran</h2>
                                </div>

                                <span class="side-count">{{ attendanceDonutSummary.percentLabel }}</span>
                            </div>

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
                        </section>

                        <section class="panel-card side-panel">
                            <div class="panel-head is-tight">
                                <div>
                                    <span class="section-label">Pengajuan Saya</span>
                                    <h2>Status Pengajuan</h2>
                                </div>
                            </div>

                            <div class="submission-list">
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
                                            <div class="leader-skeleton-line is-title mb-1" style="width: 5rem"></div>
                                            <div class="leader-skeleton-line" style="width: 9rem"></div>
                                        </div>
                                        <div class="leader-skeleton-line is-short" style="width: 4rem"></div>
                                    </div>
                                </div>

                                <button
                                    v-if="!isSubmissionsLoading && !isDashboardBootstrapping"
                                    v-for="item in personalSubmissions.slice(0, 4)"
                                    :key="item.id"
                                    type="button"
                                    class="submission-item"
                                    @click="item.href ? visit(item.href) : null"
                                >
                                    <div>
                                        <strong>{{ item.title }}</strong>
                                        <span>{{ item.date }} • {{ item.description }}</span>
                                        <em v-if="item.expiryNote">{{ item.expiryNote }}</em>
                                    </div>

                                    <small :class="`is-${item.status}`">
                                        {{ item.statusLabel }}
                                    </small>
                                </button>

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
                        </section>

                        <section class="panel-card side-panel">
                            <div class="panel-head is-tight">
                                <div>
                                    <span class="section-label">Jadwal Berikutnya</span>
                                    <h2>Upcoming</h2>
                                </div>
                            </div>

                            <div class="schedule-list">
                                <div
                                    v-if="isUpcomingLoading || isDashboardBootstrapping"
                                    class="schedule-list is-skeleton-list"
                                >
                                    <div
                                        v-for="n in 3"
                                        :key="`upcoming-skeleton-${n}`"
                                        class="schedule-item is-skeleton"
                                    >
                                        <div class="schedule-date skeleton-avatar"></div>
                                        <div>
                                            <div class="leader-skeleton-line is-title mb-1" style="width: 7rem"></div>
                                            <div class="leader-skeleton-line" style="width: 9rem"></div>
                                        </div>
                                        <div class="leader-skeleton-line is-short" style="width: 3rem"></div>
                                    </div>
                                </div>

                                <div
                                    v-if="!isUpcomingLoading && !isDashboardBootstrapping"
                                    v-for="item in upcomingResolved.slice(0, 5)"
                                    :key="`${item.date}-${item.label}`"
                                    class="schedule-item"
                                    :class="`is-${item.tone || 'default'}`"
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
                                    v-if="!isUpcomingLoading && !isDashboardBootstrapping && !upcomingResolved.length"
                                    compact
                                    icon="bi bi-calendar2"
                                    title="Jadwal kosong"
                                    description="Belum ada jadwal berikutnya."
                                />
                            </div>
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
                    <section class="modal-section">
                        <h4>Ringkasan Shift</h4>

                        <div class="modal-detail-grid">
                            <div>
                                <span>Status</span>
                                <strong>{{ selectedDayRecord.primaryStatusLabel || '-' }}</strong>
                            </div>

                            <div>
                                <span>Shift</span>
                                <strong>{{ selectedDayShiftName }}</strong>
                            </div>

                            <div>
                                <span>Jam Shift</span>
                                <strong>{{ selectedDayShiftTime }}</strong>
                            </div>

                            <div>
                                <span>Check In / Out</span>
                                <strong
                                    >{{ selectedDayRecord.selected_checkin || selectedDayRecord.checkIn || '-' }} /
                                    {{
                                        selectedDayRecord.selected_checkout || selectedDayRecord.checkOut || '-'
                                    }}</strong
                                >
                            </div>
                        </div>
                    </section>

                    <section class="modal-section">
                        <h4>Catatan</h4>
                        <div class="note-box">
                            {{ selectedDayRecord.note || 'Tidak ada catatan tambahan.' }}
                        </div>
                    </section>

                    <section class="modal-section">
                        <h4>Flag / Status</h4>

                        <div class="flag-list">
                            <div
                                v-for="flag in selectedDayFlags"
                                :key="flag.key"
                                class="flag-item"
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
                                description="Tidak ada flag tambahan."
                            />
                        </div>
                    </section>

                    <section class="modal-section">
                        <h4>Transaksi Terkait</h4>

                        <div class="transaction-list">
                            <template v-if="isDayDetailLoading">
                                <div
                                    v-for="n in 2"
                                    :key="`transaction-skeleton-${n}`"
                                    class="transaction-card is-skeleton"
                                >
                                    <div
                                        class="skeleton-avatar"
                                        style="width: 1.4rem; height: 1.4rem; border-radius: 999px"
                                    ></div>
                                    <div>
                                        <div class="leader-skeleton-line is-title mb-1" style="width: 8rem"></div>
                                        <div class="leader-skeleton-line" style="width: 12rem"></div>
                                    </div>
                                </div>
                            </template>

                            <template v-else>
                                <div v-for="item in selectedDayTransactions" :key="item.id" class="transaction-card">
                                    <i class="bi bi-receipt-cutoff"></i>
                                    <div>
                                        <strong>{{ item.title }}</strong>
                                        <span>{{ item.description }}</span>
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
                    </section>

                    <section class="modal-section">
                        <h4>Riwayat Checktime Terpilih</h4>

                        <div class="timeline-list">
                            <template v-if="isDayDetailLoading">
                                <div v-for="n in 2" :key="`timeline-skeleton-${n}`" class="timeline-item is-skeleton">
                                    <div class="timeline-marker skeleton-avatar"></div>

                                    <div>
                                        <div class="leader-skeleton-line is-title mb-1" style="width: 6rem"></div>
                                        <div class="leader-skeleton-line is-short"></div>
                                    </div>
                                </div>
                            </template>

                            <template v-else>
                                <div
                                    v-for="(item, index) in selectedDayTimeline"
                                    :key="`${item.time}-${index}`"
                                    class="timeline-item"
                                    :class="`is-${item.kind}`"
                                >
                                    <div class="timeline-marker">
                                        <i :class="item.icon"></i>
                                    </div>

                                    <div>
                                        <strong>{{ item.label }}</strong>
                                        <span>{{ item.time }}</span>
                                    </div>
                                </div>
                            </template>

                            <EmptyState
                                v-if="!isDayDetailLoading && !selectedDayTimeline.length"
                                compact
                                icon="bi bi-fingerprint"
                                title="Checktime kosong"
                                description="Tidak ada check-in/check-out terpilih dari SP."
                            />
                        </div>
                    </section>

                    <section class="modal-section">
                        <h4>Riwayat Checktime Mesin</h4>

                        <div v-if="isDayDetailLoading" class="raw-checktime-list">
                            <div v-for="n in 4" :key="`raw-skeleton-${n}`" class="raw-checktime-item is-skeleton">
                                <div>
                                    <div class="leader-skeleton-line is-title mb-1" style="width: 5rem"></div>
                                    <div class="leader-skeleton-line is-short"></div>
                                </div>
                                <div class="leader-skeleton-line is-short" style="width: 5.5rem"></div>
                            </div>
                        </div>

                        <div v-else class="raw-checktime-list">
                            <div
                                v-for="log in selectedDayRawLogs"
                                :key="log.id || log.checktime"
                                class="raw-checktime-item"
                                :class="{ 'is-selected': log.is_selected_checkin || log.is_selected_checkout }"
                            >
                                <div>
                                    <strong>{{ log.time || '-' }}</strong>
                                    <span>{{ log.checktime || 'CHECKINOUT' }}</span>
                                </div>

                                <div class="raw-checktime-badges">
                                    <span v-if="log.is_selected_checkin" class="mini-badge is-success"
                                        >Check In terpilih</span
                                    >
                                    <span v-if="log.is_selected_checkout" class="mini-badge is-orange"
                                        >Check Out terpilih</span
                                    >
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
                    </section>
                </div>
            </template>
        </Modal>

        <transition name="fade-scale">
            <div v-if="activeQuickActionGroup" class="quick-action-modal-overlay" @click.self="closeQuickActionGroup">
                <section class="quick-action-modal-card" role="dialog" aria-modal="true">
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
import { computed, defineComponent, h, onBeforeUnmount, onMounted, ref, watchEffect } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
import axios from 'axios';
import { DotLottieVue } from '@lottiefiles/dotlottie-vue';
import Modal from '@/components/ui/modalV2-noir.vue';

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
});

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
const overtimeSectionRef = ref(null);
const attendanceCalendarSectionRef = ref(null);
const hcisTrendHoverIndex = ref(null);
const dayDetailCache = ref({});
const dayDetailLoadingDate = ref('');
const personalActionItems = ref([]);
const notificationDismissBusy = ref({});
const needsActionError = ref('');
const teamDashboardBadge = ref({
    count: 0,
    label: '',
    hasItems: false,
});
const teamDashboardBadgeLoading = ref(false);
const teamDashboardBadgeController = ref(null);
const selectedKpiYear = ref(null);
const selectedKpiPeriod = ref('');
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
    leave: false,
    submissions: false,
    upcoming: false,
});
const domainControllers = ref({});
const domainVersions = ref({});
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
const mobilePanelState = ref({
    action: true,
    kpi: true,
    lms: false,
    overtime: false,
    submissions: true,
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
const isHcisLoading = computed(() => domainLoading.value.hcis && !hasHcisDomain.value);
const isNeedsActionLoading = computed(
    () => domainLoading.value.needsAction && !personalActionItems.value.length && !needsActionError.value,
);
const isKpiLoading = computed(() => domainLoading.value.kpi && !hasKpiDomain.value);
const isKpiRefreshing = computed(() => domainLoading.value.kpi && hasKpiDomain.value);
const isLmsLoading = computed(() => domainLoading.value.lms && !hasLmsDomain.value);
const isOvertimeLoading = computed(() => domainLoading.value.attendance && !hasOvertimeDomain.value);
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
    () => `${formatLongDate(calendarCutoffStartDate.value)} â€” ${formatLongDate(calendarCutoffEndDate.value)}`,
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

const selectedDayRawLogs = computed(() => {
    const rows = dayDetailCache.value[selectedDate.value]?.raw_logs;
    return Array.isArray(rows) ? rows : [];
});

const selectedDayTransactions = computed(() => {
    const rows = dayDetailCache.value[selectedDate.value]?.transactions;
    return Array.isArray(rows) ? rows : [];
});

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
    const { controller, version } = startDomainRequest(domain);
    domainLoading.value = { ...domainLoading.value, [domain]: true };
    dashboardError.value = '';

    try {
        const response = await axios.get(endpoint, {
            signal: controller?.signal,
            params: options.params || {},
        });

        if (!isLatestDomainRequest(domain, version)) return;

        const payload = response?.data?.data ?? response?.data ?? {};
        mapper(payload, response?.data || {});
    } catch (error) {
        if (isCanceledRequest(error) || !isLatestDomainRequest(domain, version)) return;

        console.error(`Failed to load homepage ${domain}`, error);
        const message = error?.response?.data?.message || 'Sebagian data dashboard gagal dimuat.';
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
        },
    );
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
        attendanceDonutError.value = error?.response?.data?.message || 'Komposisi absensi gagal dimuat.';
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

async function loadMobileWeekCalendar() {
    const weekStart = toYmd(mobileWeekStartDate.value);
    const cached = mobileWeekCache.value[weekStart];

    if (mobileWeekLoading.value && mobileWeekRequestKey.value === weekStart) {
        return;
    }

    if (cached) {
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
        mobileWeekCache.value = {
            ...mobileWeekCache.value,
            [weekStart]: nextState,
        };
    } catch (error) {
        if (isCanceledRequest(error)) return;

        console.error('Failed to load homepage weekly calendar', error);
        mobileWeekError.value = error?.response?.data?.message || 'Data mingguan belum bisa dimuat.';
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

async function loadDashboard(options = {}) {
    const kpiYear = resolveDashboardYear('kpi');
    const lmsYear = resolveDashboardYear('lms');
    const bootstrapLoad = Boolean(options.bootstrap);

    if (bootstrapLoad) {
        isDashboardBootstrapping.value = true;
    }

    try {
        await Promise.allSettled([
            loadPersonalAttendanceSummary(),
            loadTeamDashboardBadge(),
            loadDomain('hcis', '/homepage/personal/hcis-summary', (data) => {
                patchDashboard({ hcis: data || {} });
            }),
            loadDomain(
                'needsAction',
                '/homepage/personal/needs-action',
                (data, raw) => {
                    needsActionError.value = '';
                    personalActionItems.value = Array.isArray(raw?.data) ? raw.data : Array.isArray(data) ? data : [];
                },
                { params: paramsWithYear(kpiYear, 'kpi_year') },
            ),
            loadPersonalKpiDetail({ year: kpiYear }),
            loadDomain('lms', '/homepage/personal/lms-summary', (data) => patchDashboard({ lms: data || {} }), {
                params: paramsWithYear(lmsYear),
            }),
            loadDomain('leave', '/homepage/personal/leave-summary', (data) => patchDashboard({ leave: data || {} })),
            loadDomain('submissions', '/homepage/personal/submissions-summary', (data) =>
                patchDashboard({ submissions: Array.isArray(data) ? data : [] }),
            ),
            loadDomain('upcoming', '/homepage/personal/upcoming-schedule', (data) =>
                patchDashboard({ upcomingSchedule: Array.isArray(data) ? data : [] }),
            ),
        ]);
    } finally {
        if (bootstrapLoad) {
            isDashboardBootstrapping.value = false;
        }
    }
}

function retryDashboardLoad() {
    void loadDashboard();
}

const calendarFilters = [
    { key: 'all', label: 'Semua' },
    { key: 'problem', label: 'Bermasalah' },
    { key: 'late', label: 'Terlambat' },
    { key: 'no-in-out', label: 'No IN/OUT' },
    { key: 'izin-cuti', label: 'Izin/Cuti' },
];

const filteredCutoffDays = computed(() => {
    if (activeCalendarFilter.value === 'all') return calendarCutoffDays.value;
    if (activeCalendarFilter.value === 'problem') return calendarCutoffDays.value.filter((day) => day.hasProblem);
    if (activeCalendarFilter.value === 'late') {
        return calendarCutoffDays.value.filter((day) => dayHasStatus(day, 'late'));
    }
    if (activeCalendarFilter.value === 'no-in-out') {
        return calendarCutoffDays.value.filter((day) => ['no-checkin', 'no-checkout'].includes(day.primaryStatusKey));
    }
    if (activeCalendarFilter.value === 'izin-cuti') {
        return calendarCutoffDays.value.filter((day) => ['izin', 'cuti'].includes(day.primaryStatusKey));
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
const actionCenterIssueCount = computed(() => pendingItems.value.length);
const totalLateMinutes = computed(() =>
    cutoffDays.value.reduce((total, day) => total + Number(day?.lateMinutes || 0), 0),
);
const totalEarlyOutMinutes = computed(() =>
    cutoffDays.value.reduce((total, day) => total + Number(day?.earlyOutMinutes || 0), 0),
);
const calendarTotalLateMinutes = computed(() =>
    calendarCutoffDays.value.reduce((total, day) => total + Number(day?.lateMinutes || 0), 0),
);
const calendarTotalEarlyOutMinutes = computed(() =>
    calendarCutoffDays.value.reduce((total, day) => total + Number(day?.earlyOutMinutes || 0), 0),
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
        key: 'late',
        label: 'Terlambat',
        tone: 'warning',
        color: '#f59e0b',
        filter: 'late',
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
        key: 'off',
        label: 'Libur/Off',
        tone: 'muted',
        color: '#64748b',
        filter: 'all',
    },
    {
        key: 'pending',
        label: 'Belum Dinilai',
        tone: 'neutral',
        color: '#94a3b8',
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

const hcisPersonalStats = computed(() => [
    {
        key: 'late',
        label: 'Terlambat',
        value: hcisPersonal.value.stats.lateMinutes,
        caption: 'menit cutoff aktif',
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
        key: 'action-center-issue',
        label: 'Jumlah Issue',
        value: actionCenterIssueCount.value,
        caption: 'Action Center',
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
    const nextSchedule = upcomingResolved.value?.[0]?.label || '-';

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

    const warningItems = [];

    if (parameterScheduled) {
        warningItems.push({
            key: 'parameter',
            tone: parameterSubmitted ? 'is-success' : parameterHasTargets ? 'is-info' : 'is-warning',
            icon: parameterSubmitted
                ? 'bi bi-check-circle'
                : parameterHasTargets
                  ? 'bi bi-info-circle'
                  : 'bi bi-exclamation-circle',
            text:
                parameter.message ||
                (parameterSubmitted
                    ? `KPI Parameter ${parameterPeriod} sudah diisi.`
                    : parameterHasTargets
                      ? `Target KPI ${parameterPeriod} tersedia, tetapi tidak ada jadwal pengisian aktif.`
                      : `KPI Parameter ${parameterPeriod || `tahun ${year}`} belum diisi.`),
        });
    }

    if (performanceScheduled) {
        warningItems.push({
            key: 'performance',
            tone: performanceSubmitted ? 'is-success' : performance.canSubmit === false ? 'is-danger' : 'is-warning',
            icon: performanceSubmitted
                ? 'bi bi-check-circle'
                : performance.canSubmit === false
                  ? 'bi bi-slash-circle'
                  : 'bi bi-exclamation-circle',
            text:
                performance.message ||
                (performanceSubmitted
                    ? `Update Performance ${performancePeriod} sudah diisi.`
                    : `Update Performance ${performancePeriod} belum diisi.`),
        });
    }

    if (!parameterScheduled && !performanceScheduled && ytdHasData) {
        warningItems.push({
            key: 'ytd',
            tone: 'is-info',
            icon: 'bi bi-activity',
            text: ytd.message || `Tidak ada jadwal aktif. Menampilkan achievement YTD tahun ${year}.`,
        });
    }

    if (source.deadline_text || source.deadlineText) {
        warningItems.push({
            key: 'deadline',
            tone: 'is-info',
            icon: 'bi bi-clock-history',
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
        pendingCount:
            Number(parameterScheduled && !parameterSubmitted) + Number(performanceScheduled && !performanceSubmitted),
        parameterCount: parameterHasTargets
            ? Number(source.parameter_count ?? source.parameterCount ?? parameters.length)
            : 0,
        performanceItemCount,
        ytdHasData,
        ytdScore: ytd.score ?? ytd.achievement ?? achievement ?? 0,
        ytdSubmittedCount: Number(
            ytd.submitted_count ?? ytd.submittedCount ?? ytd.period_count ?? ytd.periodCount ?? 0,
        ),
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
    const activeDurationRows = durationMonthly.filter((item) => Number(item.avg_hours ?? item.avgHours ?? 0) > 0);
    const avgLearningDurationHours = activeDurationRows.length
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
        learningHours: source.learning_hours ?? source.learningHours ?? 0,
        certificates: source.certificates ?? 0,
        overdue: source.overdue ?? 0,
        nextSession: source.next_session || source.nextSession || 'Tidak ada training aktif',
        nextSessionDate: source.next_session_date || source.nextSessionDate || '-',
        completedMonthly,
        durationMonthly,
        avgLearningDurationHours,
        avgLearningDurationLabel: formatLearningDurationLabel(avgLearningDurationHours),
        progressList: hasData ? source.learning_progress || source.progressList || [] : [],
    };
});

const overtimeSummary = computed(() => {
    const source = dashboard.value.overtime || props.moduleSummary?.overtime || {};
    const hasData = Object.keys(source).length > 0 && (source.has_data ?? source.hasData ?? true);

    return {
        hasData,
        periodLabel: source.period_label || source.periodLabel || 'Bulan berjalan',
        totalHours: hasData ? (source.total_hours ?? source.totalHours ?? 0) : 0,
        workdayHours: hasData ? (source.workday_hours ?? source.workdayHours ?? 0) : 0,
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

const leaveSummary = computed(() => {
    const source = dashboard.value.leave || props.moduleSummary?.leave || {};

    return {
        quota: source.quota ?? source.total_quota ?? 0,
        remaining: source.remaining ?? source.remaining_quota ?? 0,
        used: source.used ?? source.used_quota ?? 0,
        periodLabel: source.period_label || source.periodLabel || 'Tahun berjalan',
    };
});

const personalSubmissions = computed(() => {
    return dashboard.value.submissions || [];
});

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

    heroKpi.value.warningItems
        .filter((warning) => {
            if (warning.key === 'parameter') {
                return heroKpi.value.parameterScheduled && !heroKpi.value.parameterSubmitted;
            }

            if (warning.key === 'performance') {
                return heroKpi.value.performanceScheduled && !heroKpi.value.performanceSubmitted;
            }

            return false;
        })
        .forEach((warning) => {
            items.push({
                id: `kpi-${warning.key}`,
                module: 'KPI',
                tone: warning.tone === 'is-info' ? 'info' : 'warning',
                icon: warning.icon,
                title: warning.text,
                description: `Periode KPI: ${heroKpi.value.periodLabel}`,
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
    const preferredKeys = isLeader.value ? ['attendance', 'approval', 'leave'] : ['attendance', 'overtime', 'leave'];

    return preferredKeys.map((key) => awarenessCards.value.find((item) => item.key === key)).filter(Boolean);
});

const compactPendingItems = computed(() => pendingItems.value.slice(0, 5));
const compactSubmissions = computed(() => personalSubmissions.value.slice(0, 3));
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
        data: overtimeSummary.value.monthlyTrend.map((item) => item.before),
    },
    {
        name: 'Setelah Kerja',
        data: overtimeSummary.value.monthlyTrend.map((item) => item.after),
    },
    {
        name: 'Hari Libur',
        data: overtimeSummary.value.monthlyTrend.map((item) => item.holiday),
    },
]);

const overtimeChartOptions = computed(() => ({
    chart: {
        type: 'line',
        toolbar: { show: false },
        zoom: { enabled: false },
        fontFamily: 'inherit',
    },
    stroke: { curve: 'smooth', width: 2 },
    markers: { size: 3, strokeWidth: 2 },
    grid: {
        strokeDashArray: 4,
        padding: { left: 0, right: 8, top: 0, bottom: 0 },
    },
    xaxis: {
        categories: overtimeSummary.value.monthlyTrend.map((item) => item.month),
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
        y: { formatter: (value) => `${value} jam` },
    },
}));

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

    activeQuickActionGroup.value = group;
}

function closeQuickActionGroup() {
    activeQuickActionGroup.value = null;
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
    }

    if (key === 'attendance') {
        activeCalendarFilter.value = 'problem';
        return;
    }

    if (key === 'attendance-timing') {
        activeCalendarFilter.value = 'late';
        scrollToAttendanceCalendar();
        return;
    }

    if (key === 'overtime') {
        scrollToOvertime();
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

function scrollToMobileSection(key) {
    activeMobileTab.value = key;

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

function handleWindowResize() {
    updateViewportState();
    syncActiveMobileTab();
}

function handlePendingItem(item) {
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

async function dismissPendingNotification(item) {
    const notificationId = item?.notification_id;
    const busyKey = notificationId || item?.notification_key || item?.id;

    if (!notificationId || !busyKey || notificationDismissBusy.value[busyKey]) {
        return;
    }

    notificationDismissBusy.value = {
        ...notificationDismissBusy.value,
        [busyKey]: true,
    };

    try {
        await axios.post(`/homepage/notifications/${encodeURIComponent(notificationId)}/dismiss`);
        personalActionItems.value = personalActionItems.value.filter((candidate) => {
            if (notificationId && candidate.notification_id === notificationId) {
                return false;
            }
            if (item.notification_key && candidate.notification_key === item.notification_key) {
                return false;
            }

            return candidate.id !== item.id;
        });
    } catch (error) {
        console.error('Failed to dismiss action notification', error);
    } finally {
        const next = { ...notificationDismissBusy.value };
        delete next[busyKey];
        notificationDismissBusy.value = next;
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
    if (record?.early_out) flags.push({ key: 'early-out', label: 'Pulang Cepat', tone: 'warning', problem: true });
    if (record?.cuti || status.includes('cuti'))
        flags.push({ key: 'cuti', label: 'Cuti', tone: 'info', problem: false });
    if (record?.izin || record?.sakit || record?.is_leave || status.includes('izin') || status.includes('sakit')) {
        flags.push({ key: 'izin', label: 'Izin / Sakit', tone: 'info', problem: false });
    }
    if (record?.no_shift || status.includes('off')) {
        flags.push({ key: 'no-shift', label: 'OFF / No Shift', tone: 'default', problem: false });
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
        chips.push({ key: 'waiting-shift', label: 'Menunggu jam kerja', tone: 'default' });
    }

    if (primaryKey === 'working' || record?.working) {
        chips.push({ key: 'working', label: 'Sedang bekerja', tone: 'info' });
    }

    return chips.slice(0, 3);
}

function resolvePrimaryStatus(record, flags, isFuture = false, timing = null) {
    if (isFuture || timing?.isFutureDate || record?.future) return 'future';

    const status = String(record?.status || '').toLowerCase();
    if (record?.waiting_shift || status.includes('waiting shift') || status.includes('menunggu'))
        return 'waiting-shift';
    if (record?.working || status.includes('working') || status.includes('sedang bekerja')) return 'working';

    const priority = [
        'expired',
        'future',
        'alpha',
        'no-checkin',
        'no-checkout',
        'early-out',
        'late',
        'izin',
        'cuti',
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
        present: { label: 'Hadir', tone: 'success', icon: 'bi bi-check2-circle' },
        future: { label: 'Belum Dijalankan', tone: 'default', icon: 'bi bi-clock-history' },
        'waiting-shift': { label: 'Menunggu Jam Kerja', tone: 'default', icon: 'bi bi-hourglass-split' },
        working: { label: 'Sedang Bekerja', tone: 'info', icon: 'bi bi-person-workspace' },
        late: { label: 'Terlambat', tone: 'warning', icon: 'bi bi-alarm' },
        'no-checkin': { label: 'No IN', tone: 'danger', icon: 'bi bi-box-arrow-in-right' },
        'no-checkout': { label: 'No OUT', tone: 'warning', icon: 'bi bi-box-arrow-right' },
        'early-out': { label: 'Pulang Cepat', tone: 'warning', icon: 'bi bi-box-arrow-right' },
        alpha: { label: 'Alpha', tone: 'danger', icon: 'bi bi-person-x' },
        izin: { label: 'Izin / Sakit', tone: 'info', icon: 'bi bi-file-earmark-medical' },
        cuti: { label: 'Cuti', tone: 'info', icon: 'bi bi-airplane' },
        'no-shift': { label: 'OFF', tone: 'default', icon: 'bi bi-dash-circle' },
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

    return Array.isArray(day.flags) && day.flags.some((flag) => flag.key === key);
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
    const lateMinutes = Number(point.late_minutes || 0);

    return `
        <div class="hcis-trend-tooltip">
            <strong>${escapeTooltipText(point.label || 'Cutoff')}</strong>
            <span class="hcis-trend-tooltip__percent">${percentage}</span>
            <span>Terpenuhi: ${Number(point.fulfilled_days || point.present_days || 0)}/${Number(point.total_days || 0)} hari aktif</span>
            <span>Hadir fisik: ${Number(point.present_days || 0)} hari</span>
            <span>Sakit: ${Number(point.sick_days || 0)} hari</span>
            <span>Cuti: ${Number(point.leave_days || 0)} kali</span>
            <span>Terlambat: ${formatDurationShort(lateMinutes)}</span>
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
    if (!date || dayDetailCache.value[date] || dayDetailLoadingDate.value === date) return;

    dayDetailLoadingDate.value = date;

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

onMounted(() => {
    void loadDashboard({ bootstrap: true });
    void loadMobileWeekCalendar();
    void loadAttendanceDonutComposition({ cutoffPeriod: selectedAttendanceDonutPeriod.value });
    updateViewportState();

    if (typeof window === 'undefined') return;

    window.addEventListener('resize', handleWindowResize);
    window.addEventListener('scroll', syncActiveMobileTab, { passive: true });
    syncActiveMobileTab();
});

onBeforeUnmount(() => {
    Object.values(domainControllers.value).forEach((controller) => controller?.abort?.());
    domainControllers.value = {};
    mobileWeekController.value?.abort?.();
    mobileWeekController.value = null;
    attendanceDonutController.value?.abort?.();
    attendanceDonutController.value = null;

    if (typeof window === 'undefined') return;

    window.removeEventListener('resize', handleWindowResize);
    window.removeEventListener('scroll', syncActiveMobileTab);
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
    overflow-x: hidden;
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #a5b4fc;
    /* padding: 0.8rem; */
    color: #0f172a;
    /* background:
        radial-gradient(circle at top left, rgba(99, 102, 241, 0.11), transparent 24rem),
        linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%); */
}

.personal-dashboard-container {
    width: 100%;
    /* max-width: 1480px; */
    min-width: 0;
    margin: 0 auto;
    overflow-x: hidden;
}

.mobile-dashboard {
    min-height: 100vh;
    /* padding-bottom: calc(5rem + env(safe-area-inset-bottom, 0px));
    background:
        radial-gradient(circle at top left, rgba(148, 163, 184, 0.12), transparent 20rem),
        linear-gradient(180deg, #f6f8fc 0%, #edf2f7 100%); */
}

.mobile-dashboard__body {
    display: grid;
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
        linear-gradient(135deg, #4f46e5, #2563eb);
    box-shadow: 0 14px 34px rgba(37, 99, 235, 0.18);
}

.mobile-hero.is-success {
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.22), transparent 14rem),
        linear-gradient(135deg, #059669, #2563eb);
}

.mobile-hero.is-warning {
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.22), transparent 14rem),
        linear-gradient(135deg, #d97706, #4f46e5);
}

.mobile-hero.is-danger {
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.22), transparent 14rem),
        linear-gradient(135deg, #dc2626, #4f46e5);
}

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
    background: #22d3ee;
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
    font-size: 0.58rem;
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
    max-width: 7.25rem;
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
    padding: 0.7rem 0.55rem;
    border-radius: 0.88rem;
    border: 1px solid rgba(255, 255, 255, 0.14);
    background: rgba(255, 255, 255, 0.1);
    text-align: center;
}

.mobile-time-card span,
.mobile-time-card small {
    display: block;
    font-size: 0.56rem;
    font-weight: 850;
    color: rgba(255, 255, 255, 0.76);
}

.mobile-time-card strong {
    display: block;
    margin-top: 0.3rem;
    font-size: 1.42rem;
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
    gap: 0.4rem;
    font-size: 0.66rem;
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
    padding: 0.46rem;
    border-radius: 0.76rem;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.mobile-hero__brief span {
    display: block;
    font-size: 0.52rem;
    font-weight: 850;
    color: rgba(255, 255, 255, 0.72);
}

.mobile-hero__brief strong {
    display: block;
    margin-top: 0.12rem;
    font-size: 0.64rem;
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
    min-height: 5.1rem;
    padding: 0.56rem 0.5rem;
    border: 1px solid rgba(226, 232, 240, 0.92);
    border-radius: 0.9rem;
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
    text-align: left;
    cursor: pointer;
}

.mobile-awareness-card__icon {
    width: 1.78rem;
    height: 1.78rem;
    border-radius: 0.65rem;
    display: grid;
    place-items: center;
    background: #eef2ff;
    color: #4f46e5;
}

.mobile-awareness-card__copy {
    margin-top: 0.42rem;
    min-width: 0;
}

.mobile-awareness-card__copy strong {
    display: block;
    color: #0f172a;
    font-size: 0.88rem;
    font-weight: 950;
    line-height: 1;
}

.mobile-awareness-card__copy span,
.mobile-awareness-card__copy small {
    display: block;
    margin-top: 0.16rem;
    font-size: 0.56rem;
    font-weight: 850;
    color: #64748b;
    line-height: 1.2;
}

.mobile-awareness-card.is-danger .mobile-awareness-card__icon {
    background: #fee2e2;
    color: #dc2626;
}

.mobile-awareness-card.is-purple .mobile-awareness-card__icon {
    background: #ede9fe;
    color: #7c3aed;
}

.mobile-awareness-card.is-info .mobile-awareness-card__icon {
    background: #e0f2fe;
    color: #0284c7;
}

.mobile-awareness-card.is-orange .mobile-awareness-card__icon {
    background: #ffedd5;
    color: #ea580c;
}

.mobile-awareness-card.is-success .mobile-awareness-card__icon {
    background: #dcfce7;
    color: #16a34a;
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
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    background: #f8fafc;
}

.mobile-week-nav__button {
    width: 1.72rem;
    height: 1.72rem;
    border: 0;
    border-radius: 999px;
    display: grid;
    place-items: center;
    background: #fff;
    color: #334155;
    font-size: 0.72rem;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
    cursor: pointer;
    transition: 0.4s all ease;
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
    color: #0f172a;
}

.mobile-inline-link {
    border: 0;
    background: transparent;
    color: #4f46e5;
    font-size: 0.62rem;
    font-weight: 900;
    cursor: pointer;
    white-space: nowrap;
}

.mobile-mini-calendar__grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.26rem;
}

.mobile-mini-calendar__day {
    min-width: 0;
    min-height: 3rem;
    padding: 0.5rem 0.14rem 0.42rem;
    border-radius: 0.82rem;
    border: 1px solid #d6dee9;
    background: #fff;
    color: #0f172a;
    display: grid;
    align-content: start;
    gap: 0.12rem;
    text-align: center;
    cursor: pointer;
}

.mobile-mini-calendar__day-label,
.mobile-mini-calendar__day-meta {
    font-size: 0.48rem;
    font-weight: 850;
    color: inherit;
    opacity: 0.82;
}

.mobile-mini-calendar__day-number {
    font-size: 1.14rem;
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
    background: linear-gradient(180deg, #f0fdf4 0%, #dff7e8 100%);
    border-color: #b9e8c7;
    color: #15803d;
}

.mobile-mini-calendar__day.is-warning {
    background: linear-gradient(180deg, #fff7df 0%, #feefc0 100%);
    border-color: #f4db8d;
    color: #b45309;
}

.mobile-mini-calendar__day.is-danger {
    background: linear-gradient(180deg, #fff0f1 0%, #fde3e7 100%);
    border-color: #f3c1ca;
    color: #dc2626;
}

.mobile-mini-calendar__day.is-info {
    background: linear-gradient(180deg, #f0f9ff 0%, #dff0fc 100%);
    border-color: #b8ddf4;
    color: #0369a1;
}

.mobile-mini-calendar__day.is-default {
    background: #f8fafc;
    border-color: #d6dee9;
    color: #475569;
}

.mobile-mini-calendar__day.is-today {
    box-shadow:
        inset 0 0 0 1.5px rgba(79, 70, 229, 0.24),
        0 8px 18px rgba(79, 70, 229, 0.08);
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
    background: #cbd5e1;
}

.mobile-quick-action-folder {
    min-width: 0;
    min-height: 4.55rem;
    padding: 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.86rem;
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.36rem;
    text-align: center;
    scroll-snap-align: start;
    cursor: pointer;
    transition:
        border-color 200ms ease,
        background 200ms ease,
        box-shadow 200ms ease,
        transform 200ms ease;
}

.mobile-quick-action-folder:active {
    transform: translateY(1px);
}

.mobile-quick-action-folder__icon {
    width: 2.05rem;
    height: 2.05rem;
    border-radius: 0.72rem;
    display: grid;
    place-items: center;
    background: #eef2ff;
    color: #4f46e5;
    flex-shrink: 0;
}

.mobile-quick-action-folder__copy {
    width: 100%;
    min-width: 0;
    display: grid;
    gap: 0.12rem;
}

.mobile-quick-action-folder__copy strong {
    color: #0f172a;
    font-size: 0.62rem;
    font-weight: 950;
    line-height: 1.15;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.mobile-quick-action-folder__copy small {
    color: #64748b;
    font-size: 0.5rem;
    font-weight: 850;
    line-height: 1.1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mobile-accordion__trigger {
    width: 100%;
    padding: 0;
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
    font-size: 0.54rem;
    letter-spacing: 0.13em;
    line-height: 1;
}

.mobile-accordion-card .module-title-copy h2 {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 900;
    line-height: 1.2;
    color: #1e293b;
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
    color: #94a3b8;
}

.mobile-accordion-card .period-badge {
    max-width: 7rem;
    padding: 0.2rem 0.42rem;
    font-size: 0.52rem;
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
    margin-top: 0.58rem;
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
    font-size: 0.6rem;
}

.mobile-dashboard .action-card__body span,
.mobile-dashboard .submission-item span,
.mobile-dashboard .schedule-item > div:nth-child(2) span {
    font-size: 0.52rem;
}

.mobile-dashboard .action-card__body small {
    font-size: 0.48rem;
}

.mobile-dashboard .submission-item em {
    font-size: 0.48rem;
}

.mobile-dashboard .submission-item {
    gap: 0.42rem;
    padding: 0.46rem 0.5rem;
}

.mobile-dashboard .submission-item small,
.mobile-dashboard .schedule-item small {
    font-size: 0.48rem;
    padding: 0.16rem 0.34rem;
}

.mobile-dashboard .schedule-item {
    gap: 0.42rem;
    padding: 0.46rem 0.5rem;
}

.mobile-dashboard .schedule-date {
    width: 2rem;
    height: 2rem;
    border-radius: 0.58rem;
}

.mobile-dashboard .schedule-date strong {
    font-size: 0.54rem;
}

.mobile-dashboard .attendance-summary-row {
    gap: 0.3rem;
    margin-bottom: 0.48rem;
}

.mobile-dashboard .summary-pill {
    padding: 0.36rem 0.2rem;
    border-radius: 0.6rem;
}

.mobile-dashboard .summary-pill strong {
    font-size: 0.78rem;
}

.mobile-dashboard .summary-pill span {
    margin-top: 0.1rem;
    font-size: 0.48rem;
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
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}

.mobile-bottom-nav__item {
    border: 0;
    background: transparent;
    color: #94a3b8;
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
    font-size: 0.52rem;
    font-weight: 900;
}

.mobile-bottom-nav__item.is-active {
    color: #4f46e5;
    background: rgba(79, 70, 229, 0.08);
}

.bi {
    display: inline-flex;
    line-height: 1;
}

.section-label {
    display: block;
    font-size: 0.62rem;
    font-weight: 950;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #64748b;
}

.personal-hero {
    position: relative;
    overflow: hidden;
    border-radius: 1.15rem;
    padding: 0.85rem;
    color: white;
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.24), transparent 17rem),
        linear-gradient(135deg, var(--primary-dark), var(--primary));
    box-shadow: 0 18px 42px rgba(79, 70, 229, 0.2);
}

.hero-orb {
    position: absolute;
    border-radius: 999px;
    filter: blur(8px);
    opacity: 0.28;
    pointer-events: none;
}

.hero-orb--one {
    width: 16rem;
    height: 16rem;
    right: -6rem;
    top: -7rem;
    background: #fff;
}

.hero-orb--two {
    width: 12rem;
    height: 12rem;
    left: 42%;
    bottom: -8rem;
    background: #22d3ee;
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
    font-size: clamp(1.3rem, 2.2vw, 1.95rem);
    font-weight: 950;
    letter-spacing: -0.04em;
}

.hero-title-row p {
    max-width: 760px;
    margin: 0.32rem 0 0;
    color: rgba(255, 255, 255, 0.84);
    font-size: 0.78rem;
    line-height: 1.45;
}

.hero-summary-callout {
    margin-top: 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    max-width: 100%;
    padding: 0.6rem 0.8rem;
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.16);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
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
    backdrop-filter: blur(8px);
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
    background: linear-gradient(180deg, #fb7185 0%, #ef4444 100%);
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
    font-size: 0.56rem;
    font-weight: 850;
    color: rgba(255, 255, 255, 0.72);
}

.today-chip strong {
    display: block;
    margin-top: 0.1rem;
    font-size: 0.8rem;
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
    gap: 0.5rem;
    margin-top: 0.6rem;
    min-width: 0;
}

.awareness-card {
    width: 100%;
    min-width: 0;
    min-height: 3.7rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.58rem;
    border: 1px solid rgba(226, 232, 240, 0.92);
    border-radius: 0.86rem;
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 9px 22px rgba(15, 23, 42, 0.035);
    text-align: left;
    cursor: default;
}

.awareness-card__icon {
    flex: 0 0 auto;
    width: 2.15rem;
    height: 2.15rem;
    display: grid;
    place-items: center;
    border-radius: 0.72rem;
    background: #eef2ff;
    color: #4f46e5;
}

.awareness-card__copy {
    min-width: 0;
}

.awareness-card__copy strong {
    display: block;
    color: #0f172a;
    font-size: 0.98rem;
    font-weight: 950;
    line-height: 1;
}

.awareness-card__copy span {
    display: block;
    margin-top: 0.14rem;
    color: #334155;
    font-size: 0.64rem;
    font-weight: 900;
}

.awareness-card__copy small {
    display: block;
    margin-top: 0.07rem;
    color: #64748b;
    font-size: 0.56rem;
    font-weight: 750;
}

.awareness-card.is-danger .awareness-card__icon {
    background: #fee2e2;
    color: #dc2626;
}

.awareness-card.is-purple .awareness-card__icon {
    background: #ede9fe;
    color: #7c3aed;
}

.awareness-card.is-info .awareness-card__icon {
    background: #e0f2fe;
    color: #0284c7;
}

.awareness-card.is-orange .awareness-card__icon {
    background: #ffedd5;
    color: #ea580c;
}

.awareness-card.is-success .awareness-card__icon {
    background: #dcfce7;
    color: #16a34a;
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
    min-width: 0;
    overflow: hidden;
    border: 1px solid rgba(226, 232, 240, 0.92);
    border-radius: 0.9rem;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.035);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
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

.module-card h2,
.panel-head h2 {
    margin: 0.06rem 0 0;
    font-size: 0.86rem;
    font-weight: 950;
    color: #0f172a;
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
    color: #7c3aed;
}

.module-icon.is-teal {
    background: #ccfbf1;
    color: #0f766e;
}

.module-icon.is-orange {
    background: #ffedd5;
    color: #ea580c;
}

.module-icon.is-indigo {
    background: #e0e7ff;
    color: #4f46e5;
}

.mini-badge {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 1.35rem;
    padding: 0.22rem 0.48rem;
    border-radius: 999px;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 0.56rem;
    font-weight: 950;
}

.mini-badge.is-danger {
    background: #fee2e2;
    color: #dc2626;
}

.mini-badge.is-success {
    background: #dcfce7;
    color: #15803d;
}

.mini-badge.is-orange {
    background: #ffedd5;
    color: #ea580c;
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
    border: 1px solid #e2e8f0;
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.95), transparent 4rem),
        linear-gradient(135deg, #f8fafc, #ffffff);
    text-align: left;
}

.leader-score-card span,
.leader-score-card small {
    display: block;
    color: #64748b;
    font-size: 0.56rem;
    font-weight: 900;
}

.leader-score-card strong {
    display: block;
    margin: 0.18rem 0;
    color: #0f172a;
    font-size: 1.44rem;
    font-weight: 950;
    line-height: 1;
    letter-spacing: -0.04em;
}

.leader-score-card.is-hcis {
    background:
        radial-gradient(circle at top right, rgba(14, 165, 233, 0.15), transparent 5rem),
        linear-gradient(135deg, #f8fafc, #ffffff);
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
    border: 1px solid #e2e8f0;
    background: #f8fafc;
}

.leader-mini-stat-grid span,
.leader-mini-stat-grid small {
    display: block;
    overflow: hidden;
    color: #64748b;
    font-size: 0.5rem;
    font-weight: 850;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.leader-mini-stat-grid strong {
    display: block;
    margin: 0.08rem 0;
    color: #0f172a;
    font-size: 0.95rem;
    font-weight: 950;
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
    color: #0f172a;
    font-size: 0.66rem;
    font-weight: 950;
}

.line-chart-card__head span,
.line-chart-card__head small {
    color: #64748b;
    font-size: 0.52rem;
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

.leader-apex-chart :deep(.hcis-trend-tooltip) {
    display: grid;
    gap: 0.18rem;
    min-width: 9.5rem;
    padding: 0.58rem 0.68rem;
    color: #e5e7eb;
    font-size: 0.62rem;
    font-weight: 800;
}

.leader-apex-chart :deep(.hcis-trend-tooltip strong),
.leader-apex-chart :deep(.hcis-trend-tooltip span) {
    display: block;
}

.leader-apex-chart :deep(.hcis-trend-tooltip strong) {
    color: #ffffff;
    font-size: 0.68rem;
    font-weight: 950;
}

.leader-apex-chart :deep(.hcis-trend-tooltip__percent) {
    color: #86efac;
    font-size: 0.92rem;
    font-weight: 950;
}

.leader-skeleton-line,
.skeleton-avatar {
    background: linear-gradient(90deg, #f1f5f9 0%, #e2e8f0 20%, #f1f5f9 40%, #f1f5f9 100%);
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
.transaction-card.is-skeleton,
.timeline-item.is-skeleton {
    background: #f8fafc !important;
    border-color: #e2e8f0 !important;
    color: #64748b;
}

.brief-grid .is-skeleton,
.leader-mini-stat-grid .is-skeleton,
.kpi-stat-grid .is-skeleton,
.lms-stat-grid .is-skeleton,
.overtime-summary-grid .is-skeleton,
.summary-pill.is-skeleton,
.calendar-day.is-skeleton,
.mobile-mini-calendar__day.is-skeleton,
.transaction-card.is-skeleton,
.timeline-item.is-skeleton {
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
    background: linear-gradient(90deg, #f1f5f9 0%, #e2e8f0 20%, #f1f5f9 40%, #f1f5f9 100%) !important;
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
    background: #e0f2fe;
    color: #0284c7;
}

.period-badge {
    flex: 0 0 auto;
    max-width: 9rem;
    padding: 0.24rem 0.46rem;
    border-radius: 999px;
    font-size: 0.56rem;
    font-weight: 950;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.period-badge.is-purple {
    background: #ede9fe;
    color: #7c3aed;
}

.period-badge.is-teal {
    background: #ccfbf1;
    color: #0f766e;
}

.period-badge.is-orange {
    background: #ffedd5;
    color: #ea580c;
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
    padding: 0.45rem;
    border-radius: 0.66rem;
    font-size: 0.62rem;
    font-weight: 850;
}

.inline-warning.is-warning {
    background: #fef3c7;
    color: #92400e;
}

.inline-warning.is-info {
    background: #e0f2fe;
    color: #075985;
}

.inline-warning.is-success {
    background: #dcfce7;
    color: #166534;
}

.inline-warning.is-danger {
    background: #fee2e2;
    color: #991b1b;
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
    color: #64748b;
    font-size: 0.54rem;
    font-weight: 900;
}

.kpi-control-row select {
    width: 100%;
    min-width: 0;
    border: 1px solid #e2e8f0;
    border-radius: 0.62rem;
    background: #ffffff;
    color: #0f172a;
    font-size: 0.62rem;
    font-weight: 850;
    padding: 0.42rem 0.5rem;
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
    background: linear-gradient(135deg, #f5f3ff, #eef2ff);
    color: #5b21b6;
}

.kpi-refresh-state i {
    font-size: 0.78rem;
    animation: spin-soft 0.9s linear infinite;
}

.kpi-refresh-state span {
    min-width: 0;
    overflow: hidden;
    font-size: 0.6rem;
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
    border: 1px solid #e2e8f0;
    background: #f8fafc;
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
    color: #64748b;
    font-size: 0.54rem;
    font-weight: 850;
    line-height: 1.25;
}

.kpi-flow-title strong,
.kpi-performance-panel strong {
    display: block;
    color: #0f172a;
    font-size: 0.72rem;
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
    border: 1px solid #e2e8f0;
    background: #f8fafc;
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
    color: #64748b;
    font-size: 0.54rem;
    font-weight: 850;
    line-height: 1.25;
}

.kpi-status-panel__item strong {
    display: block;
    color: #0f172a;
    font-size: 0.72rem;
    font-weight: 950;
    line-height: 1.2;
}

.kpi-status-panel__item strong.is-success {
    color: #166534;
}

.kpi-status-panel__item strong.is-warning {
    color: #92400e;
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
    padding: 0.32rem 1.4rem 0.32rem 0.45rem;
    border: 1px solid #ddd6fe;
    border-radius: 0.55rem;
    background: #faf5ff
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%237c3aed'/%3E%3C/svg%3E")
        no-repeat right 0.42rem center / 8px 5px;
    -webkit-appearance: none;
    appearance: none;
    color: #4c1d95;
    font-size: 0.58rem;
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
    border: 1px solid #e2e8f0;
    background: #f8fafc;
}

.kpi-hero__stat span {
    display: block;
    color: #64748b;
    font-size: 0.52rem;
    font-weight: 850;
}

.kpi-hero__stat strong {
    display: block;
    margin-top: 0.06rem;
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 950;
    line-height: 1.1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.kpi-hero__stat small {
    display: block;
    color: #94a3b8;
    font-size: 0.5rem;
    font-weight: 800;
    margin-top: 0.04rem;
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
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    min-width: 0;
}

.kpi-status-pill > i {
    flex: 0 0 auto;
    font-size: 0.78rem;
    color: #94a3b8;
}

.kpi-status-pill > div {
    min-width: 0;
}

.kpi-status-pill span {
    display: block;
    color: #64748b;
    font-size: 0.48rem;
    font-weight: 850;
}

.kpi-status-pill strong {
    display: block;
    color: #0f172a;
    font-size: 0.6rem;
    font-weight: 950;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.kpi-status-pill.is-success {
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.kpi-status-pill.is-success > i {
    color: #16a34a;
}

.kpi-status-pill.is-warning {
    background: #fffbeb;
    border-color: #fde68a;
}

.kpi-status-pill.is-warning > i {
    color: #d97706;
}

.kpi-status-pill.is-info {
    background: #f0f9ff;
    border-color: #bae6fd;
}

.kpi-status-pill.is-info > i {
    color: #0284c7;
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
    color: #7c3aed;
}

.kpi-list-header > span {
    color: #475569;
    font-size: 0.52rem;
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
    background: #cbd5e1;
}

.kpi-param-dot.is-excellent {
    background: #22c55e;
    box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.22);
}

.kpi-param-dot.is-good {
    background: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.22);
}

.kpi-param-dot.is-low {
    background: #f59e0b;
    box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.22);
}

.kpi-param-dot.is-idle {
    background: #cbd5e1;
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
    background: linear-gradient(135deg, #6d28d9 0%, #4f46e5 55%, #4338ca 100%);
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
    font-size: 0.52rem;
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

.kpi-score-card__mini small {
    display: block;
    font-size: 0.5rem;
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
    border: 1px solid #e2e8f0;
    background: #f8fafc;
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
    color: #64748b;
    font-size: 0.54rem;
    font-weight: 850;
}

.kpi-stat-grid strong,
.lms-stat-grid strong,
.overtime-summary-grid strong,
.lms-main-progress strong {
    display: block;
    margin-top: 0.08rem;
    color: #0f172a;
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
    max-height: 9.5rem;
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
    color: #475569;
    font-size: 0.58rem;
    font-weight: 850;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mini-parameter-list strong,
.training-item strong {
    flex: 0 0 auto;
    color: #0f172a;
    font-size: 0.58rem;
    font-weight: 950;
}

.mini-parameter-meta {
    display: block;
    margin-top: 0.08rem;
    color: #64748b;
    font-size: 0.52rem;
    font-weight: 800;
    line-height: 1.25;
}

.training-item {
    padding: 0.26rem 0.4rem;
    border-radius: 0.5rem;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
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
    padding: 0.14rem 0.3rem;
    border-radius: 999px;
    background: #fef3c7;
    color: #b45309;
    font-size: 0.52rem;
    font-weight: 950;
}

.training-item__top small.is-complete {
    background: #dcfce7;
    color: #15803d;
}

.mini-progress {
    width: 100%;
    height: 0.35rem;
    margin-top: 0.16rem;
    border-radius: 999px;
    background: #e2e8f0;
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
    background: linear-gradient(90deg, #8b5cf6, #4f46e5);
}

.mini-progress__fill.is-teal {
    background: linear-gradient(90deg, #14b8a6, #06b6d4);
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
    background: #ecfeff;
    border: 1px solid #99f6e4;
    box-shadow: 0 12px 24px rgba(20, 184, 166, 0.12);
}

.lms-next-card span,
.lms-next-card small {
    display: block;
    color: #0f766e;
    font-size: 0.56rem;
    font-weight: 850;
}

.lms-next-card strong {
    display: block;
    margin-top: 0.1rem;
    color: #0f172a;
    font-size: 0.72rem;
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
    background: linear-gradient(135deg, #14b8a6, #06b6d4);
    color: white;
    font-weight: 950;
    text-align: center;
    box-shadow: 0 14px 26px rgba(20, 184, 166, 0.22);
}

.lms-progress-ring span {
    display: block;
    font-size: 0.48rem;
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
    background: linear-gradient(135deg, #f0fdfa, #ecfeff);
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
    font-size: 0.54rem !important;
    font-weight: 950 !important;
    color: #0f766e !important;
    background: rgba(20, 184, 166, 0.16);
    padding: 0.1rem 0.4rem;
    border-radius: 999px;
}

.lms-hero__info > small {
    color: #64748b;
    font-size: 0.52rem;
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
    background: #f8fafc;
    border: 1px solid #e2e8f0;
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
    color: #64748b;
    font-size: 0.5rem;
    font-weight: 850;
}

.lms-stat-pill strong {
    display: block;
    color: #0f172a;
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
    background: #fefce8;
    border: 1px solid #fef08a;
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
    color: #92400e;
    font-size: 0.48rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.lms-next-highlight__info strong {
    display: block;
    color: #0f172a;
    font-size: 0.62rem;
    font-weight: 950;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lms-next-highlight__date {
    flex: 0 0 auto;
    color: #92400e;
    font-size: 0.52rem;
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
    color: #475569;
    font-size: 0.52rem;
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
    background: #cbd5e1;
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
    background: #cbd5e1;
}

.overtime-total {
    background: linear-gradient(135deg, #ffedd5, #fff7ed) !important;
    border-color: #fed7aa !important;
}

.overtime-total strong {
    color: #ea580c !important;
    font-size: 0.98rem !important;
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
    background: #e0f2fe;
    color: #0369a1;
}

.chart-block {
    min-width: 0;
    margin-top: 0.52rem;
    padding: 0.42rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.7rem;
    background: #f8fafc;
    overflow: hidden;
}

.chart-safe {
    width: 100%;
    min-width: 0;
    overflow: hidden;
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
    color: #475569;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.chart-head strong {
    flex: 0 0 auto;
    color: #0f172a;
}

.calendar-filters {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.cutoff-period-select {
    min-height: 1.7rem;
    max-width: 12rem;
    padding: 0 1.8rem 0 0.55rem;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    background: #fff;
    color: #0f172a;
    font-size: 0.58rem;
    font-weight: 900;
    cursor: pointer;
}

.cutoff-period-select:disabled {
    cursor: wait;
    color: #94a3b8;
    background: #f8fafc;
}

.filter-chip {
    min-height: 1.7rem;
    padding: 0 0.48rem;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    font-size: 0.58rem;
    font-weight: 900;
    cursor: pointer;
}

.filter-chip.is-active {
    color: #fff;
    background: #4f46e5;
    border-color: #4f46e5;
}

.attendance-summary-row {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.36rem;
    margin-bottom: 0.52rem;
}

.summary-pill {
    min-width: 0;
    padding: 0.42rem;
    border-radius: 0.68rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    text-align: center;
}

.summary-pill strong {
    display: block;
    font-size: 0.84rem;
    font-weight: 950;
    line-height: 1;
}

.summary-pill span {
    display: block;
    margin-top: 0.12rem;
    font-size: 0.52rem;
    font-weight: 850;
    color: #64748b;
}

.summary-pill.is-success {
    background: #dcfce7;
    border-color: #bbf7d0;
    color: #15803d;
}

.summary-pill.is-warning {
    background: #fef3c7;
    border-color: #fde68a;
    color: #b45309;
}

.summary-pill.is-danger {
    background: #fee2e2;
    border-color: #fecaca;
    color: #dc2626;
}

.summary-pill.is-info {
    background: #e0f2fe;
    border-color: #bae6fd;
    color: #0369a1;
}

.summary-pill.is-orange {
    background: #ffedd5;
    border-color: #fed7aa;
    color: #ea580c;
}

.calendar-scroll {
    width: 100%;
    overflow-x: auto;
    padding-bottom: 0.1rem;
    -webkit-overflow-scrolling: touch;
}

.calendar-grid {
    min-width: 720px;
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.38rem;
}

.calendar-weekday {
    padding: 0.22rem;
    color: #64748b;
    font-size: 0.58rem;
    font-weight: 950;
    text-align: center;
}

.calendar-day {
    min-width: 0;
    min-height: 6.15rem;
    padding: 0.46rem;
    border-radius: 0.72rem;
    border: 1px solid #e2e8f0;
    background: #fff;
    cursor: pointer;
    text-align: left;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.calendar-day.is-today {
    border-color: #6366f1;
    box-shadow: inset 0 0 0 1px rgba(99, 102, 241, 0.2);
}

.calendar-day.has-problem {
    border-color: #fed7aa;
}

.calendar-day.is-success {
    background: #f0fdf4;
}

.calendar-day.is-warning {
    background: #fffbeb;
}

.calendar-day.is-danger {
    background: #fff1f2;
}

.calendar-day.is-info {
    background: #f0f9ff;
}

.calendar-day.is-default {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.calendar-day__top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.32rem;
}

.calendar-day__date {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.02rem;
}

.calendar-day__top strong {
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 950;
}

.calendar-day__weekday {
    color: #64748b;
    font-size: 0.48rem;
    font-weight: 900;
    line-height: 1;
}

.calendar-day__top span {
    padding: 0.13rem 0.3rem;
    border-radius: 999px;
    font-size: 0.48rem;
    font-weight: 950;
    background: #f1f5f9;
    color: #475569;
}

.calendar-day__top span.is-success {
    background: #dcfce7;
    color: #15803d;
}

.calendar-day__top span.is-warning {
    background: #fef3c7;
    color: #b45309;
}

.calendar-day__top span.is-danger {
    background: #fee2e2;
    color: #dc2626;
}

.calendar-day__top span.is-info {
    background: #e0f2fe;
    color: #0369a1;
}

.calendar-day__top span.is-default {
    background: #e2e8f0;
    color: #475569;
}

.calendar-day__shift {
    margin-top: 0.28rem;
    color: #334155;
    font-size: 0.56rem;
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
    padding: 0.11rem 0.28rem;
    border-radius: 999px;
    font-size: 0.46rem;
    font-weight: 950;
    line-height: 1.1;
    color: #475569;
    background: rgba(226, 232, 240, 0.85);
    white-space: nowrap;
}

.calendar-day__chips span.is-warning {
    color: #92400e;
    background: rgba(254, 243, 199, 0.9);
}

.calendar-day__chips span.is-orange {
    color: #c2410c;
    background: rgba(255, 237, 213, 0.94);
}

.calendar-day__chips span.is-danger {
    color: #b91c1c;
    background: rgba(254, 226, 226, 0.94);
}

.calendar-day__chips span.is-info {
    color: #0369a1;
    background: rgba(224, 242, 254, 0.94);
}

.calendar-day__chips span.is-default {
    color: #475569;
    background: rgba(226, 232, 240, 0.94);
}

.calendar-day__times {
    display: grid;
    gap: 0.15rem;
    margin-top: 0.28rem;
}

.calendar-day__times div {
    display: flex;
    justify-content: space-between;
    gap: 0.36rem;
}

.calendar-day__times small {
    color: #64748b;
    font-size: 0.5rem;
    font-weight: 900;
}

.calendar-day__times strong {
    color: #0f172a;
    font-size: 0.58rem;
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
    background: #eef2ff;
    color: #4f46e5;
    font-size: 0.66rem;
    font-weight: 950;
}

.action-list,
.submission-list,
.schedule-list {
    display: grid;
    gap: 0.4rem;
    min-width: 0;
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
    border: 1px solid #e2e8f0;
    background: #fff;
    text-align: left;
    cursor: pointer;
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

.action-card.is-success {
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.action-card.is-orange {
    background: #fff7ed;
    border-color: #fed7aa;
}

.action-card__icon {
    width: 1.85rem;
    height: 1.85rem;
    display: grid;
    place-items: center;
    border-radius: 0.62rem;
    background: #f1f5f9;
    color: #475569;
}

.action-card__body {
    min-width: 0;
}

.action-card__body strong {
    display: block;
    color: #0f172a;
    font-size: 0.62rem;
    font-weight: 950;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.action-card__body span {
    display: block;
    margin-top: 0.08rem;
    color: #64748b;
    font-size: 0.56rem;
    line-height: 1.3;
}

.action-card__body small {
    display: block;
    margin-top: 0.1rem;
    color: #475569;
    font-size: 0.52rem;
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
    background: #ffffff;
    color: #4f46e5;
    font-size: 0.5rem;
    font-weight: 950;
    white-space: nowrap;
    cursor: pointer;
}

.action-card__done:disabled {
    opacity: 0.55;
    cursor: wait;
}

.action-card.is-skeleton {
    cursor: default;
    pointer-events: none;
}

.action-card.is-skeleton .action-card__icon {
    border: none;
    background: #e2e8f0;
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
    padding-right: 0.08rem;
    min-width: 0;
}

.quick-action-folder-grid::-webkit-scrollbar {
    width: 4px;
}

.quick-action-folder-grid::-webkit-scrollbar-track {
    background: transparent;
}

.quick-action-folder-grid::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: #cbd5e1;
}

.quick-action-folder {
    min-width: 0;
    min-height: 5.25rem;
    padding: 0.58rem 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.82rem;
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    color: #334155;
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
    border-color: #c7d2fe;
    background: #ffffff;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.045);
    transform: translateY(-1px);
}

.quick-action-folder__icon {
    width: 2.15rem;
    height: 2.15rem;
    border-radius: 0.72rem;
    display: grid;
    place-items: center;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 1rem;
}

.quick-action-folder__copy {
    min-width: 0;
    width: 100%;
    display: grid;
    gap: 0.14rem;
}

.quick-action-folder__copy strong {
    color: #0f172a;
    font-size: 0.62rem;
    font-weight: 950;
    line-height: 1.12;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.quick-action-folder__copy small {
    color: #64748b;
    font-size: 0.5rem;
    font-weight: 850;
    line-height: 1.15;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.quick-action-folder > .bi-chevron-right {
    color: #94a3b8;
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
    color: #64748b;
    font-size: 0.55rem;
    font-weight: 950;
    text-transform: uppercase;
}

.quick-action-group__title i {
    color: #4f46e5;
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
    border: 1px solid #e2e8f0;
    border-radius: 0.76rem;
    background: #ffffff;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.42rem;
    text-align: left;
    color: #334155;
    cursor: pointer;
    transition:
        border-color 200ms ease,
        background 200ms ease,
        box-shadow 200ms ease,
        transform 200ms ease;
}

.quick-action-button:hover {
    border-color: #c7d2fe;
    background: #f8fafc;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.045);
    transform: translateY(-1px);
}

.quick-action-button__icon {
    width: 1.9rem;
    height: 1.9rem;
    border-radius: 0.66rem;
    display: grid;
    place-items: center;
    background: #eef2ff;
    color: #4f46e5;
}

.quick-action-button__copy {
    min-width: 0;
    display: grid;
    gap: 0.1rem;
}

.quick-action-button__copy strong {
    color: #0f172a;
    font-size: 0.62rem;
    font-weight: 950;
    line-height: 1.15;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.quick-action-button__copy small {
    color: #64748b;
    font-size: 0.52rem;
    font-weight: 850;
    line-height: 1.1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.quick-action-button > .bi-arrow-right-short {
    color: #94a3b8;
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
    color: #0f172a;
    font-size: 0.86rem;
    font-weight: 950;
    line-height: 1.15;
}

.attendance-donut-period-select {
    width: 100%;
    min-height: 2.28rem;
    padding: 0.46rem 2rem 0.46rem 0.62rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.72rem;
    background: #ffffff;
    color: #334155;
    font-size: 0.62rem;
    font-weight: 900;
    line-height: 1.15;
    cursor: pointer;
}

.attendance-donut-period-select:disabled {
    cursor: wait;
    color: #94a3b8;
    background: #f8fafc;
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
    color: #0f172a;
    font-size: 0.68rem;
    font-weight: 950;
    line-height: 1.22;
}

.attendance-donut-meta span {
    color: #64748b;
    font-size: 0.55rem;
    font-weight: 800;
    line-height: 1.2;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.attendance-donut-legend {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.32rem;
    min-width: 0;
}

.attendance-donut-legend__item {
    min-width: 0;
    min-height: 2.08rem;
    padding: 0.34rem 0.4rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.72rem;
    background: #ffffff;
    color: #334155;
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
    border-color: #c7d2fe;
    background: #f8fafc;
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
    color: #475569;
    font-size: 0.52rem;
    font-weight: 900;
    line-height: 1.08;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.attendance-donut-legend__item strong {
    color: #0f172a;
    font-size: 0.66rem;
    font-weight: 950;
}

.attendance-donut-legend__item.is-success {
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.attendance-donut-legend__item.is-warning {
    background: #fffbeb;
    border-color: #fde68a;
}

.attendance-donut-legend__item.is-danger {
    background: #fff1f2;
    border-color: #fecaca;
}

.attendance-donut-legend__item.is-info {
    background: #eff6ff;
    border-color: #bfdbfe;
}

.attendance-donut-legend__item.is-muted,
.attendance-donut-legend__item.is-neutral {
    background: #f8fafc;
    border-color: #e2e8f0;
}

.attendance-donut-action {
    width: 100%;
    min-height: 2.28rem;
    padding: 0.48rem 0.62rem;
    border: 1px solid #e0e7ff;
    border-radius: 0.72rem;
    background: #eef2ff;
    color: #4338ca;
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
    background: #e0e7ff;
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
    background: linear-gradient(90deg, #e2e8f0 0%, #f8fafc 50%, #e2e8f0 100%);
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
    background: rgba(15, 23, 42, 0.42);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
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
    background: linear-gradient(135deg, #eef2ff 0%, #ffffff 100%);
    border-bottom: 1px solid #e2e8f0;
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
    background: #4f46e5;
    color: #ffffff;
    box-shadow: 0 12px 26px rgba(79, 70, 229, 0.22);
    flex-shrink: 0;
}

.quick-action-modal-title h3 {
    margin: 0.12rem 0 0;
    color: #0f172a;
    font-size: 1rem;
    font-weight: 950;
    line-height: 1.1;
}

.quick-action-modal-title small {
    display: block;
    margin-top: 0.2rem;
    color: #64748b;
    font-size: 0.62rem;
    font-weight: 850;
}

.quick-action-modal-close {
    width: 2.15rem;
    height: 2.15rem;
    border: 0;
    border-radius: 999px;
    background: #ffffff;
    color: #64748b;
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
    border: 1px solid #e2e8f0;
    border-radius: 0.86rem;
    background: #ffffff;
    color: #334155;
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
    border-color: #c7d2fe;
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
    transform: translateY(-1px);
}

.quick-action-modal-item__icon {
    width: 2rem;
    height: 2rem;
    border-radius: 0.72rem;
    display: grid;
    place-items: center;
    background: #eef2ff;
    color: #4f46e5;
}

.quick-action-modal-item__copy {
    min-width: 0;
    display: grid;
    gap: 0.16rem;
}

.quick-action-modal-item__copy strong {
    color: #0f172a;
    font-size: 0.7rem;
    font-weight: 950;
    line-height: 1.15;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.quick-action-modal-item__copy small {
    color: #64748b;
    font-size: 0.54rem;
    font-weight: 850;
    line-height: 1.15;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.quick-action-modal-item > .bi-arrow-right-short {
    color: #94a3b8;
    font-size: 1.05rem;
}

.quick-action-modal-footer {
    padding: 0.75rem 1rem;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
    display: flex;
    justify-content: center;
}

.quick-action-modal-back {
    min-height: 2.35rem;
    padding: 0 0.9rem;
    border: 0;
    border-radius: 0.76rem;
    background: #4f46e5;
    color: #ffffff;
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
    border: 1px dashed #fca5a5;
    border-radius: 0.72rem;
    background: #fff1f2;
    color: #991b1b;
    font-size: 0.58rem;
    font-weight: 850;
}

.action-error-card button {
    flex: 0 0 auto;
    border: none;
    border-radius: 999px;
    background: #dc2626;
    color: #fff;
    padding: 0.24rem 0.5rem;
    font-size: 0.54rem;
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
    border: 1px solid #e2e8f0;
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
    color: #0f172a;
    font-size: 0.62rem;
    font-weight: 950;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.submission-item span {
    display: block;
    margin-top: 0.08rem;
    color: #64748b;
    font-size: 0.54rem;
    font-weight: 750;
}

.submission-item em {
    display: block;
    margin-top: 0.08rem;
    color: #b45309;
    font-size: 0.5rem;
    font-style: normal;
    font-weight: 900;
}

.submission-item small {
    flex: 0 0 auto;
    padding: 0.18rem 0.36rem;
    border-radius: 999px;
    font-size: 0.52rem;
    font-weight: 950;
}

.submission-item small.is-pending {
    background: #fef3c7;
    color: #b45309;
}

.submission-item small.is-approved {
    background: #dcfce7;
    color: #15803d;
}

.submission-item small.is-rejected,
.submission-item small.is-expired {
    background: #fee2e2;
    color: #dc2626;
}

.schedule-item {
    min-width: 0;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.48rem;
    padding: 0.48rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.7rem;
    background: #fff;
}

.schedule-date {
    width: 2.25rem;
    height: 2.25rem;
    display: grid;
    place-items: center;
    border-radius: 0.68rem;
    background: #eef2ff;
    color: #4f46e5;
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
    color: #0f172a;
    font-size: 0.62rem;
    font-weight: 950;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.schedule-item > div:nth-child(2) span {
    display: block;
    margin-top: 0.08rem;
    color: #64748b;
    font-size: 0.54rem;
    font-weight: 750;
}

.schedule-item small {
    flex: 0 0 auto;
    padding: 0.18rem 0.36rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: #334155;
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
    border: 1px dashed #cbd5e1;
    border-radius: 0.75rem;
    background: #f8fafc;
    text-align: center;
    color: #64748b;
}

.empty-state.is-compact {
    min-height: 3.5rem;
    padding: 0.55rem;
}

.empty-state i {
    font-size: 1.35rem;
    color: #94a3b8;
}

.empty-state.is-compact i {
    font-size: 1rem;
}

.empty-state strong {
    display: block;
    color: #0f172a;
    font-size: 0.72rem;
    font-weight: 950;
}

.empty-state span {
    display: block;
    max-width: 20rem;
    color: #64748b;
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
    border: 1px dashed #cbd5e1;
    border-radius: 0.75rem;
    background: linear-gradient(180deg, #f8fafc, #eef2ff);
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
    color: #64748b;
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
    color: #334155;
}

.loading-bar {
    display: block;
    height: 0.6rem;
    border-radius: 999px;
    background: linear-gradient(90deg, #dbeafe 0%, #c7d2fe 50%, #dbeafe 100%);
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
    border: 1px solid #fecaca;
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
    background: #e11d48;
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
    background: rgba(15, 23, 42, 0.5);
    backdrop-filter: blur(6px);
}

.day-modal {
    width: min(720px, 100%);
    max-height: 90vh;
    overflow: hidden;
    border-radius: 1rem;
    background: #fff;
    box-shadow: 0 30px 80px rgba(15, 23, 42, 0.24);
}

.day-modal__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.9rem;
    color: white;
    background: linear-gradient(135deg, #4f46e5, #2563eb);
}

.day-modal__head.is-success {
    background: linear-gradient(135deg, #059669, #2563eb);
}

.day-modal__head.is-warning {
    background: linear-gradient(135deg, #d97706, #4f46e5);
}

.day-modal__head.is-danger {
    background: linear-gradient(135deg, #dc2626, #4f46e5);
}

.day-modal__head.is-info {
    background: linear-gradient(135deg, #0284c7, #4f46e5);
}

.day-modal__head span {
    display: block;
    font-size: 0.62rem;
    font-weight: 950;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.8;
}

.day-modal__head h3 {
    margin: 0.22rem 0 0;
    font-size: 1.15rem;
    font-weight: 950;
}

.day-modal__head p {
    margin: 0.16rem 0 0;
    font-size: 0.74rem;
    opacity: 0.84;
}

.modal-close {
    width: 2rem;
    height: 2rem;
    border: none;
    border-radius: 0.68rem;
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.14);
    color: white;
    cursor: pointer;
}

.day-modal__body {
    max-height: 62vh;
    overflow: auto;
    padding: 0.9rem;
    display: grid;
    gap: 0.7rem;
}

.modal-section h4 {
    margin: 0 0 0.45rem;
    color: #0f172a;
    font-size: 0.78rem;
    font-weight: 950;
}

.modal-detail-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.45rem;
}

.modal-detail-grid > div {
    min-width: 0;
    padding: 0.55rem;
    border-radius: 0.72rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.modal-detail-grid span {
    display: block;
    color: #64748b;
    font-size: 0.56rem;
    font-weight: 850;
}

.modal-detail-grid strong {
    display: block;
    margin-top: 0.12rem;
    color: #0f172a;
    font-size: 0.72rem;
    font-weight: 950;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.note-box {
    padding: 0.65rem;
    border-radius: 0.72rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #334155;
    font-size: 0.72rem;
    font-weight: 750;
}

.flag-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.flag-item {
    display: inline-flex;
    align-items: center;
    gap: 0.32rem;
    padding: 0.35rem 0.5rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: #334155;
    font-size: 0.64rem;
    font-weight: 900;
}

.flag-item.is-danger {
    background: #fee2e2;
    color: #dc2626;
}

.flag-item.is-warning {
    background: #fef3c7;
    color: #b45309;
}

.flag-item.is-info {
    background: #e0f2fe;
    color: #0369a1;
}

.timeline-list {
    display: grid;
    gap: 0.4rem;
}

.timeline-item {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.55rem;
    border-radius: 0.72rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.timeline-marker {
    width: 1.9rem;
    height: 1.9rem;
    display: grid;
    place-items: center;
    border-radius: 0.65rem;
    background: #eef2ff;
    color: #4f46e5;
}

.timeline-item strong {
    display: block;
    color: #0f172a;
    font-size: 0.7rem;
    font-weight: 950;
}

.timeline-item span {
    display: block;
    margin-top: 0.08rem;
    color: #64748b;
    font-size: 0.62rem;
    font-weight: 800;
}

.transaction-list,
.raw-checktime-list {
    display: grid;
    gap: 0.4rem;
}

.transaction-card,
.raw-checktime-item {
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.55rem;
    padding: 0.55rem;
    border-radius: 0.72rem;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
}

.transaction-card {
    justify-content: flex-start;
}

.transaction-card > i {
    flex: 0 0 auto;
    width: 1.9rem;
    height: 1.9rem;
    display: grid;
    place-items: center;
    border-radius: 0.65rem;
    background: #eef2ff;
    color: #4f46e5;
}

.transaction-card > div,
.raw-checktime-item > div:first-child {
    min-width: 0;
}

.transaction-card strong,
.raw-checktime-item strong {
    display: block;
    color: #0f172a;
    font-size: 0.7rem;
    font-weight: 950;
}

.transaction-card span,
.raw-checktime-item span {
    display: block;
    margin-top: 0.08rem;
    color: #64748b;
    font-size: 0.62rem;
    font-weight: 800;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.raw-checktime-item.is-selected {
    background: #f0fdf4;
    border-color: #86efac;
    box-shadow: inset 3px 0 0 #22c55e;
}

.raw-checktime-badges {
    flex: 0 0 auto;
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.25rem;
}

.raw-checktime-badges .mini-badge {
    min-height: 1.2rem;
    font-size: 0.5rem;
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
        grid-template-columns: repeat(3, minmax(0, 1fr));
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
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.4rem;
    }

    .awareness-card {
        min-height: 3.35rem;
        padding: 0.48rem;
    }

    .awareness-card__icon {
        width: 1.95rem;
        height: 1.95rem;
    }

    .awareness-card__copy strong {
        font-size: 0.9rem;
    }

    .panel-head {
        flex-direction: column;
        gap: 0.42rem;
    }

    .calendar-filters {
        justify-content: flex-start;
    }

    .attendance-summary-row,
    .modal-detail-grid {
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

    .period-badge {
        max-width: 8rem;
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
    .today-status-strip,
    .modal-detail-grid {
        grid-template-columns: 1fr;
    }

    .awareness-strip {
        grid-template-columns: 1fr;
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

    .raw-checktime-item {
        align-items: flex-start;
        flex-direction: column;
    }

    .raw-checktime-badges {
        justify-content: flex-start;
    }

    .chart-block {
        padding: 0.34rem;
    }

    .day-modal {
        max-height: 92vh;
    }
}
</style>
