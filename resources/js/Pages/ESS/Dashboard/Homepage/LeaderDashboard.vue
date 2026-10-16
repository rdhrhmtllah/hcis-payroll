<template>
    <div class="leader-dashboard-page">
        <div class="leader-dashboard-container">
            <!-- HERO -->
            <section class="leader-hero" data-tour="leader-hero">
                <div class="hero-orb hero-orb--one"></div>
                <div class="hero-orb hero-orb--two"></div>

                <div class="leader-hero__content">
                    <div class="hero-left">
                        <div class="hero-topline">
                            <div class="hero-eyebrow">
                                <i class="bi bi-grid-1x2-fill"></i>
                                Leader Monitoring
                            </div>

                            <div v-if="!isMobileViewport" class="hero-eyebrow hero-eyebrow--period">
                                <i class="bi bi-calendar-range"></i>
                                <span class="leader-hero__cutoff-text"
                                    >Periode Cutoff: <strong>{{ calendarCutoffLabel }}</strong></span
                                >
                            </div>

                            <button
                                v-if="isMobileViewport"
                                @click="router.visit('/homepage')"
                                type="button"
                                class="leader-shortcut-btn leader-hero__shortcut"
                                aria-label="Dashboard Personal"
                                title="Dashboard Personal"
                            >
                                <i class="bi bi-person-badge"></i>
                                <span class="leader-hero__shortcut-label">Dashboard Personal</span>
                            </button>
                        </div>

                        <div class="hero-title-row">
                            <h1>Team Monitoring Center</h1>
                            <p>
                                Pantau absensi, shift, lembur, cuti, KPI, LMS, dan approval tim dalam satu dashboard
                                awareness yang ringkas untuk leader.
                            </p>
                        </div>

                        <div class="hero-brief-grid">
                            <template v-if="globalSummaryLoading">
                                <div v-if="!isMobileViewport">
                                    <span>Total Anggota</span>
                                    <div class="leader-skeleton-line is-title mt-1 mb-1" style="width: 50%"></div>
                                    <div class="leader-skeleton-line is-short"></div>
                                </div>

                                <div>
                                    <span>Hadir Hari Ini</span>
                                    <div class="leader-skeleton-line is-title mt-1 mb-1" style="width: 50%"></div>
                                    <div class="leader-skeleton-line is-short"></div>
                                </div>

                                <div>
                                    <span>Antrian Approval</span>
                                    <div class="leader-skeleton-line is-title mt-1 mb-1" style="width: 50%"></div>
                                    <div class="leader-skeleton-line is-short"></div>
                                </div>
                            </template>
                            <template v-else>
                                <div v-if="!isMobileViewport">
                                    <span>Total Anggota</span>
                                    <strong>{{ summary.totalMembers }}</strong>
                                    <small>team aktif</small>
                                </div>

                                <div>
                                    <span>Hadir Hari Ini</span>
                                    <strong>{{ summary.presentToday }}</strong>
                                    <small>{{ todayLabel }}</small>
                                </div>

                                <div>
                                    <span>Antrian Approval</span>
                                    <strong>{{ summary.approvalQueue }}</strong>
                                    <small>sidebar approval</small>
                                </div>
                            </template>
                        </div>

                        <div v-if="!isMobileViewport" class="leader-hero__actions">
                            <button
                                @click="router.visit('/homepage')"
                                type="button"
                                class="leader-shortcut-btn leader-hero__shortcut"
                                data-tour="leader-hero-dashboard-personal"
                                aria-label="Dashboard Personal"
                                title="Dashboard Personal"
                            >
                                <i class="bi bi-person-badge"></i>
                                <span class="leader-hero__shortcut-label">Dashboard Personal</span>
                            </button>
                        </div>

                        <div v-if="isMobileViewport" class="leader-hero__note">
                            <i class="bi bi-calendar-range"></i>
                            <span class="leader-hero__cutoff-text"
                                >Periode Cutoff: <strong>{{ calendarCutoffLabel }}</strong></span
                            >
                        </div>
                    </div>

                    <div class="hero-right">
                        <div
                            class="hero-focus-card is-clickable-anchor"
                            data-tour="leader-hero-action-focus"
                            role="button"
                            tabindex="0"
                            @click="scrollToActionCenterSection"
                            @keydown.enter.prevent="scrollToActionCenterSection"
                            @keydown.space.prevent="scrollToActionCenterSection"
                        >
                            <div class="hero-focus-card__head">
                                <div>
                                    <span>Perlu Ditindak</span>
                                    <template v-if="isActionCenterPending">
                                        <div
                                            class="hero-skeleton-line mt-1 mb-1"
                                            style="width: 2.5rem; height: 1.25rem"
                                        ></div>
                                        <div class="hero-skeleton-line" style="width: 4rem; height: 0.5rem"></div>
                                    </template>
                                    <template v-else>
                                        <strong>{{ heroActionCountLabel }}</strong>
                                        <small>
                                            {{ needsActionError ? 'data belum tersedia' : 'item aktif' }}
                                        </small>
                                    </template>
                                </div>

                                <div
                                    class="hero-focus-icon"
                                    :class="actionItemCount > 0 ? 'is-danger animate-action-pulse' : ''"
                                >
                                    <i
                                        class="bi bi-bell-fill"
                                        :class="{ 'animate-icon-ring': actionItemCount > 0 }"
                                    ></i>
                                </div>
                            </div>

                            <div class="hero-focus-list">
                                <template v-if="isActionCenterPending">
                                    <div
                                        v-for="n in 2"
                                        :key="`hero-action-skeleton-${n}`"
                                        class="hero-focus-item is-skeleton"
                                        style="cursor: default"
                                    >
                                        <div
                                            class="hero-skeleton-avatar"
                                            style="width: 1.2rem; height: 1.2rem; flex: 0 0 auto"
                                        ></div>
                                        <div style="flex: 1; display: flex; flex-direction: column; gap: 0.25rem">
                                            <div class="hero-skeleton-line" style="width: 80%; height: 0.65rem"></div>
                                            <div class="hero-skeleton-line" style="width: 50%; height: 0.5rem"></div>
                                        </div>
                                    </div>
                                </template>

                                <div v-else-if="needsActionError" class="hero-focus-loading is-error">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <span>Action Center belum siap. Coba refresh sebentar lagi.</span>
                                </div>

                                <div
                                    v-for="item in heroActionItems"
                                    :key="item.id"
                                    class="hero-focus-item"
                                    @click="openAction(item)"
                                >
                                    <div class="hero-focus-item__main">
                                        <i :class="item.icon"></i>
                                        <div>
                                            <strong>{{ item.title }}</strong>
                                            <span>{{ item.meta }}</span>
                                        </div>
                                    </div>
                                </div>

                                <EmptyState
                                    v-if="!isActionCenterPending && !needsActionError && !heroActionItems.length"
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
            <!-- <section class="quick-stat-grid is-leader-focused">
                <template v-if="globalSummaryLoading">
                    <div
                        v-for="n in quickStatSkeletonCount"
                        :key="`quick-stat-skeleton-${n}`"
                        class="quick-stat-card is-skeleton"
                        style="cursor: default"
                    >
                        <div
                            class="quick-stat-card__icon skeleton-avatar"
                            style="border-radius: 0.5rem; background-color: #f1f5f9"
                        ></div>
                        <div class="quick-stat-card__copy" style="flex: 1; padding-right: 0.5rem">
                            <div class="leader-skeleton-line is-title mb-1" style="width: 40%"></div>
                            <div class="leader-skeleton-line" style="width: 80%"></div>
                            <div class="leader-skeleton-line is-short mt-1" style="width: 60%"></div>
                        </div>
                    </div>
                </template>
                <template v-else>
                    <button
                        v-for="item in visibleQuickStats"
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
                </template>
            </section> -->

            <!-- INSIGHTS -->
            <section class="team-insight-grid is-balanced">
                <!-- HCIS -->
                <article
                    class="insight-card hcis-insight-card"
                    :class="{ 'mobile-accordion-card': isMobileViewport }"
                    data-tour="leader-insight-hcis"
                    @click.capture="handleHcisCardInteract"
                >
                    <button
                        v-if="isMobileViewport"
                        type="button"
                        class="mobile-accordion__trigger"
                        data-tour="leader-insight-hcis-anchor"
                        @click.stop="toggleMobileLeaderPanel('hcis')"
                    >
                        <div class="module-title">
                            <div class="module-icon is-indigo"><i class="bi bi-fingerprint"></i></div>
                            <div class="module-title-copy">
                                <span class="section-label">Absensi</span>
                                <h2>Ringkasan HCIS Team</h2>
                            </div>
                        </div>
                        <div class="mobile-accordion__meta">
                            <span class="mini-badge" :class="{ 'is-danger': summary.attendanceIssues > 0 }">
                                {{ summary.attendanceIssues }} issue
                            </span>
                            <i :class="mobileLeaderPanelState.hcis ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"></i>
                        </div>
                    </button>
                    <div v-else class="insight-card__head is-between" data-tour="leader-insight-hcis-anchor">
                        <div>
                            <span class="section-label">Absensi</span>
                            <h2>Ringkasan HCIS Team</h2>
                            <!-- <small class="hcis-head-note">
                                Issue = tidak hadir pada hari kerja ({{ attendanceIssuePeriodLabel }})
                            </small> -->
                        </div>

                        <span class="mini-badge" :class="{ 'is-danger': summary.attendanceIssues > 0 }">
                            {{ summary.attendanceIssues }} issue
                        </span>
                    </div>

                    <transition
                        name="accordion"
                        @enter="onAccordionEnter"
                        @after-enter="onAccordionAfterEnter"
                        @leave="onAccordionLeave"
                    >
                        <div v-show="!isMobileViewport || mobileLeaderPanelState.hcis" class="mobile-accordion__body">
                            <div class="leader-summary-split">
                                <template v-if="globalSummaryLoading || isHcisTrendLoading">
                                    <div class="leader-score-card is-hcis is-skeleton" style="cursor: default">
                                        <span>Status Hari Ini</span>
                                        <div class="leader-skeleton-line is-title mt-2 mb-2" style="width: 50%"></div>
                                        <div class="leader-skeleton-line" style="width: 90%"></div>
                                        <div class="leader-skeleton-line is-short mt-1" style="width: 60%"></div>
                                    </div>

                                    <div class="leader-mini-stat-grid">
                                        <div
                                            v-for="n in hcisMonitoringStats?.length ?? 4"
                                            :key="`hcis-stat-skeleton-${n}`"
                                            class="is-skeleton"
                                            style="border: 1px solid #e2e8f0; background: #f8fafc"
                                        >
                                            <div class="leader-skeleton-line is-short mb-1" style="width: 40%"></div>
                                            <div
                                                class="leader-skeleton-line is-title mt-1 mb-1"
                                                style="width: 60%"
                                            ></div>
                                            <div class="leader-skeleton-line is-short mt-1" style="width: 80%"></div>
                                        </div>
                                    </div>
                                </template>
                                <template v-else-if="hcisDomainError">
                                    <div class="drawer-error-box is-compact leader-insight-inline-error">
                                        <strong>{{ hcisDomainError }}</strong>
                                        <button
                                            type="button"
                                            class="pagination-btn"
                                            @click.stop="loadHcisSummaryDomain(true)"
                                        >
                                            Coba lagi
                                        </button>
                                    </div>
                                </template>
                                <template v-else>
                                    <div
                                        class="leader-score-card is-hcis is-clickable"
                                        role="button"
                                        tabindex="0"
                                        @click.stop="openHcisTodayDrawer()"
                                        @keydown.enter.prevent="openHcisTodayDrawer()"
                                        @keydown.space.prevent="openHcisTodayDrawer()"
                                    >
                                        <span>Status Hari Ini</span>
                                        <strong
                                            >{{ formatNumber(summary.presentToday) }}/{{
                                                formatNumber(summary.scheduledToday)
                                            }}</strong
                                        >
                                        <small>{{ hcisStatusInsightText }}</small>
                                    </div>

                                    <div class="leader-mini-stat-grid">
                                        <div
                                            v-for="item in hcisMonitoringStats"
                                            :key="item.key"
                                            :class="[`is-${item.tone}`, 'is-clickable-stat']"
                                            role="button"
                                            tabindex="0"
                                            @click.stop="openHcisPeriodDrawer(item.category)"
                                            @keydown.enter.prevent="openHcisPeriodDrawer(item.category)"
                                            @keydown.space.prevent="openHcisPeriodDrawer(item.category)"
                                        >
                                            <span>{{ item.label }}</span>
                                            <strong>{{ formatNumber(item.value, item.unit || '') }}</strong>
                                            <small>{{ item.caption }}</small>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div class="line-chart-card">
                                <template v-if="globalSummaryLoading || isHcisTrendLoading">
                                    <div class="line-chart-card__head is-skeleton">
                                        <div style="flex: 1">
                                            <div class="leader-skeleton-line is-title mb-1" style="width: 50%"></div>
                                            <div class="leader-skeleton-line is-short" style="width: 70%"></div>
                                        </div>
                                        <div
                                            class="skeleton-avatar"
                                            style="width: 3rem; height: 1.2rem; border-radius: 4px"
                                        ></div>
                                    </div>
                                    <div
                                        class="leader-apex-chart is-skeleton"
                                        style="
                                            height: 140px;
                                            display: flex;
                                            align-items: flex-end;
                                            gap: 0.5rem;
                                            padding: 1rem;
                                        "
                                    >
                                        <div
                                            class="leader-skeleton-line"
                                            style="width: 100%; height: 100%; border-radius: 0.5rem"
                                        ></div>
                                    </div>
                                </template>
                                <template v-else-if="hcisDomainError">
                                    <div class="drawer-error-box is-compact leader-insight-inline-error">
                                        <strong>{{ hcisDomainError }}</strong>
                                        <button
                                            type="button"
                                            class="pagination-btn"
                                            @click.stop="loadHcisSummaryDomain(true)"
                                        >
                                            Coba lagi
                                        </button>
                                    </div>
                                </template>
                                <template v-else>
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
                                </template>
                            </div>
                        </div>
                    </transition>
                </article>

                <article
                    class="insight-card kpi-summary-card"
                    :class="{ 'mobile-accordion-card': isMobileViewport }"
                    data-tour="leader-insight-kpi"
                    @click.capture="handleKpiCardInteract"
                >
                    <button
                        v-if="isMobileViewport"
                        type="button"
                        class="mobile-accordion__trigger"
                        @click.stop="toggleMobileLeaderPanel('kpi')"
                    >
                        <div class="module-title">
                            <div class="module-icon is-purple"><i class="bi bi-graph-up-arrow"></i></div>
                            <div class="module-title-copy">
                                <span class="section-label">KPI</span>
                                <h2>Ringkasan KPI Team</h2>
                            </div>
                        </div>
                        <div class="mobile-accordion__meta">
                            <span class="period-badge is-purple">{{
                                summary.kpiYear || selectedKpiYear || kpiMonthLabel
                            }}</span>
                            <i :class="mobileLeaderPanelState.kpi ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"></i>
                        </div>
                    </button>
                    <div v-else class="insight-card__head is-between">
                        <div>
                            <span class="section-label">KPI</span>
                            <h2>Ringkasan KPI Team</h2>
                        </div>

                        <div class="kpi-year-tools">
                            <!-- <label class="kpi-year-label">
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
                            </label> -->
                            <span class="mini-badge">{{ summary.kpiYear || selectedKpiYear || kpiMonthLabel }}</span>
                        </div>
                    </div>

                    <transition
                        name="accordion"
                        @enter="onAccordionEnter"
                        @after-enter="onAccordionAfterEnter"
                        @leave="onAccordionLeave"
                    >
                        <div v-show="!isMobileViewport || mobileLeaderPanelState.kpi" class="mobile-accordion__body">
                            <button
                                v-if="!kpiRevealed"
                                type="button"
                                class="module-reveal module-reveal--purple"
                                @click.stop="handleKpiCardInteract"
                            >
                                <span class="module-reveal__aura"></span>
                                <span class="module-reveal__icon"><i class="bi bi-graph-up-arrow"></i></span>
                                <span class="module-reveal__title">Lihat Ringkasan KPI Team</span>
                                <span class="module-reveal__desc">
                                    Skor performance, parameter tersubmit, achievement YTD, dan tren 12 bulan.
                                </span>
                                <span class="module-reveal__cta">
                                    <i class="bi bi-lightning-charge-fill"></i>
                                    <span>Muat data</span>
                                </span>
                            </button>
                            <template v-else>
                            <div class="leader-summary-split">
                                <template v-if="isKpiInsightPending">
                                    <div class="leader-score-card is-kpi is-skeleton" style="cursor: default">
                                        <span>AVG Update Performance</span>
                                        <div class="leader-skeleton-line is-title mt-2 mb-2" style="width: 50%"></div>
                                        <div class="leader-skeleton-line is-short" style="width: 70%"></div>
                                    </div>

                                    <div class="leader-mini-stat-grid">
                                        <div
                                            v-for="n in 4"
                                            :key="`kpi-stat-skeleton-${n}`"
                                            class="is-skeleton"
                                            style="border: 1px solid #e2e8f0; background: #f8fafc"
                                        >
                                            <div class="leader-skeleton-line is-short mb-1" style="width: 40%"></div>
                                            <div
                                                class="leader-skeleton-line is-title mt-1 mb-1"
                                                style="width: 60%"
                                            ></div>
                                            <div class="leader-skeleton-line is-short mt-1" style="width: 80%"></div>
                                        </div>
                                    </div>
                                </template>
                                <template v-else-if="kpiDomainError">
                                    <div class="drawer-error-box is-compact leader-insight-inline-error">
                                        <strong>{{ kpiDomainError }}</strong>
                                        <button
                                            type="button"
                                            class="pagination-btn"
                                            @click.stop="loadKpiSummaryDomain(true)"
                                        >
                                            Coba lagi
                                        </button>
                                    </div>
                                </template>
                                <template v-else>
                                    <div
                                        class="leader-score-card is-kpi is-clickable"
                                        @click.stop="openKpiDetailDrawer(isKpiFullYearView ? 'ytd_avg' : 'monthly_avg')"
                                    >
                                        <span>AVG Update Performance</span>
                                        <strong>{{ formatNumber(kpiPrimaryScore, '%') }}</strong>
                                        <small v-if="isKpiFullYearView">Avg Full Year {{ summary.kpiYear }}</small>
                                        <small v-else>
                                            Avg {{ summary.performancePeriodLabel }} ·
                                            <button
                                                class="inline-link-btn"
                                                @click.stop="openKpiDetailDrawer('ytd_avg')"
                                            >
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
                                </template>
                            </div>

                            <div class="line-chart-card">
                                <template v-if="isKpiInsightPending">
                                    <div class="line-chart-card__head is-skeleton">
                                        <div style="flex: 1">
                                            <div class="leader-skeleton-line is-title mb-1" style="width: 50%"></div>
                                            <div class="leader-skeleton-line is-short" style="width: 70%"></div>
                                        </div>
                                        <div
                                            class="skeleton-avatar"
                                            style="width: 3rem; height: 1.2rem; border-radius: 4px"
                                        ></div>
                                    </div>
                                    <div
                                        class="leader-apex-chart is-skeleton"
                                        style="
                                            height: 140px;
                                            display: flex;
                                            align-items: flex-end;
                                            gap: 0.5rem;
                                            padding: 1rem;
                                        "
                                    >
                                        <div
                                            class="leader-skeleton-line"
                                            style="width: 100%; height: 100%; border-radius: 0.5rem"
                                        ></div>
                                    </div>
                                </template>
                                <template v-else-if="kpiDomainError">
                                    <div class="drawer-error-box is-compact leader-insight-inline-error">
                                        <strong>{{ kpiDomainError }}</strong>
                                        <button
                                            type="button"
                                            class="pagination-btn"
                                            @click.stop="loadKpiSummaryDomain(true)"
                                        >
                                            Coba lagi
                                        </button>
                                    </div>
                                </template>
                                <template v-else>
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
                                </template>
                            </div>
                            </template>
                        </div>
                    </transition>
                </article>

                <!-- LMS -->
                <article
                    class="insight-card lms-summary-card"
                    :class="{ 'mobile-accordion-card': isMobileViewport }"
                    data-tour="leader-insight-lms"
                    @click.capture="handleLmsCardInteract"
                >
                    <button
                        v-if="isMobileViewport"
                        type="button"
                        class="mobile-accordion__trigger"
                        @click.stop="toggleMobileLeaderPanel('lms')"
                    >
                        <div class="module-title">
                            <div class="module-icon is-teal"><i class="bi bi-journal-bookmark-fill"></i></div>
                            <div class="module-title-copy">
                                <span class="section-label">LMS</span>
                                <h2>Progress Learning Team</h2>
                            </div>
                        </div>
                        <div class="mobile-accordion__meta">
                            <span class="mini-badge is-teal">{{ summary.avgLmsProgress }}%</span>
                            <i :class="mobileLeaderPanelState.lms ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"></i>
                        </div>
                    </button>
                    <div v-else class="insight-card__head is-between">
                        <div>
                            <span class="section-label">LMS</span>
                            <h2>Progress Learning Team</h2>
                        </div>

                        <div class="kpi-year-tools lms-year-tools">
                            <!-- <label class="kpi-year-label">
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
                            </label> -->
                            <span class="mini-badge is-teal">{{ summary.avgLmsProgress }}%</span>
                        </div>
                    </div>

                    <transition
                        name="accordion"
                        @enter="onAccordionEnter"
                        @after-enter="onAccordionAfterEnter"
                        @leave="onAccordionLeave"
                    >
                        <div v-show="!isMobileViewport || mobileLeaderPanelState.lms" class="mobile-accordion__body">
                            <button
                                v-if="!lmsRevealed"
                                type="button"
                                class="module-reveal module-reveal--teal"
                                @click.stop="handleLmsCardInteract"
                            >
                                <span class="module-reveal__aura"></span>
                                <span class="module-reveal__icon"><i class="bi bi-mortarboard"></i></span>
                                <span class="module-reveal__title">Lihat Progress Learning Team</span>
                                <span class="module-reveal__desc">
                                    Rata-rata progress, status training tim, dan tren durasi belajar.
                                </span>
                                <span class="module-reveal__cta">
                                    <i class="bi bi-lightning-charge-fill"></i>
                                    <span>Muat data</span>
                                </span>
                            </button>
                            <template v-else>
                            <div class="lms-overview">
                                <template v-if="globalSummaryLoading || isLmsDomainLoading">
                                    <div class="leader-score-card is-lms is-skeleton" style="cursor: default">
                                        <span>Avg Progress</span>
                                        <div class="leader-skeleton-line is-title mt-2 mb-2" style="width: 50%"></div>
                                        <div class="leader-skeleton-line is-short" style="width: 70%"></div>
                                    </div>

                                    <div class="lms-overview__stats leader-mini-stat-grid lms-mini-stat-grid">
                                        <div
                                            v-for="n in 4"
                                            :key="`lms-stat-skeleton-${n}`"
                                            class="is-skeleton"
                                            style="border: 1px solid #e2e8f0; background: #f8fafc"
                                        >
                                            <div class="leader-skeleton-line is-short mb-1" style="width: 40%"></div>
                                            <div
                                                class="leader-skeleton-line is-title mt-1 mb-1"
                                                style="width: 60%"
                                            ></div>
                                            <div class="leader-skeleton-line is-short mt-1" style="width: 80%"></div>
                                        </div>
                                    </div>
                                </template>
                                <template v-else-if="lmsDomainError">
                                    <div class="drawer-error-box is-compact leader-insight-inline-error">
                                        <strong>{{ lmsDomainError }}</strong>
                                        <button
                                            type="button"
                                            class="pagination-btn"
                                            @click.stop="loadLmsSummaryDomain(true)"
                                        >
                                            Coba lagi
                                        </button>
                                    </div>
                                </template>
                                <template v-else>
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
                                        <small
                                            >{{ formatNumber(summary.totalTrainingPending) }} anggota belum
                                            selesai</small
                                        >
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
                                            <small>anggota</small>
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
                                </template>
                            </div>

                            <div class="line-chart-card">
                                <template v-if="globalSummaryLoading || isLmsDomainLoading">
                                    <div class="line-chart-card__head is-skeleton">
                                        <div style="flex: 1">
                                            <div class="leader-skeleton-line is-title mb-1" style="width: 50%"></div>
                                            <div class="leader-skeleton-line is-short" style="width: 70%"></div>
                                        </div>
                                        <div
                                            class="skeleton-avatar"
                                            style="width: 3rem; height: 1.2rem; border-radius: 4px"
                                        ></div>
                                    </div>
                                    <div
                                        class="leader-apex-chart is-skeleton"
                                        style="
                                            height: 140px;
                                            display: flex;
                                            align-items: flex-end;
                                            gap: 0.5rem;
                                            padding: 1rem;
                                        "
                                    >
                                        <div
                                            class="leader-skeleton-line"
                                            style="width: 100%; height: 100%; border-radius: 0.5rem"
                                        ></div>
                                    </div>
                                </template>
                                <template v-else-if="lmsDomainError">
                                    <div class="drawer-error-box is-compact leader-insight-inline-error">
                                        <strong>{{ lmsDomainError }}</strong>
                                        <button
                                            type="button"
                                            class="pagination-btn"
                                            @click.stop="loadLmsSummaryDomain(true)"
                                        >
                                            Coba lagi
                                        </button>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="line-chart-card__head">
                                        <div>
                                            <strong>Trend Durasi Training Team</strong>
                                            <span
                                                >rata-rata jam per training selesai · Jan-Des
                                                {{ selectedLmsYear }}</span
                                            >
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
                                </template>
                            </div>
                            </template>
                        </div>
                    </transition>
                </article>
            </section>

            <!-- HEATMAP (full width) -->
            <!-- MAIN -->
            <section class="leader-main-grid">
                <main class="leader-main">
                    <!-- HEATMAP (kolom kiri, di atas Team List) -->
                    <section
                        ref="heatmapSectionRef"
                        class="heatmap-card"
                        data-tour="leader-issue-heatmap"
                        :class="{ 'mobile-accordion-card': isMobileViewport }"
                        @click.capture="ensureHeatmapLoaded"
                    >
                        <button
                            v-if="isMobileViewport"
                            type="button"
                            class="mobile-accordion__trigger"
                            @click.stop="toggleMobileLeaderPanel('heatmap')"
                        >
                            <div class="module-title">
                                <div class="module-icon is-rose"><i class="bi bi-grid-3x3-gap-fill"></i></div>
                                <div class="module-title-copy">
                                    <span class="section-label">Heatmap</span>
                                    <h2>Pola Issue Tim</h2>
                                </div>
                            </div>
                            <div class="mobile-accordion__meta">
                                <span class="side-count is-warning">{{ heatmapIssueCount }}</span>
                                <i
                                    :class="mobileLeaderPanelState.heatmap ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"
                                ></i>
                            </div>
                        </button>

                        <!-- Header desktop: judul kiri · (select periode + search) di kanan -->
                        <div v-else class="heatmap-card__head">
                            <div class="heatmap-card__title">
                                <span class="section-label">Heatmap</span>
                                <h2>Pola Issue Tim</h2>
                            </div>

                            <div class="heatmap-card__controls" v-if="heatmapSectionLoaded">
                                <div class="heatmap-period">
                                    <i class="bi bi-calendar3"></i>
                                    <select
                                        class="heatmap-month-select"
                                        :value="heatmapCutoffKey"
                                        @change="onHeatmapMonthChange($event)"
                                        @click.stop
                                    >
                                        <option v-for="opt in heatmapMonthOptions" :key="opt.key" :value="opt.key">
                                            {{ opt.label }}
                                        </option>
                                    </select>
                                </div>

                                <div class="search-box heatmap-search">
                                    <i class="bi bi-search"></i>
                                    <input
                                        type="text"
                                        v-model="heatmapSearchQuery"
                                        placeholder="Cari nama anggota..."
                                        @input="onHeatmapSearchInput"
                                        @click.stop
                                    />
                                </div>
                            </div>
                        </div>

                        <transition
                            name="accordion"
                            @enter="onAccordionEnter"
                            @after-enter="onAccordionAfterEnter"
                            @leave="onAccordionLeave"
                        >
                            <div
                                v-show="!isMobileViewport || mobileLeaderPanelState.heatmap"
                                class="mobile-accordion__body"
                            >
                                <!-- Kontrol mobile: select + search (tanpa Prev/Next) -->
                                <div v-if="isMobileViewport && heatmapSectionLoaded" class="heatmap-controls-mobile">
                                    <div class="heatmap-period">
                                        <i class="bi bi-calendar3"></i>
                                        <select
                                            class="heatmap-month-select"
                                            :value="heatmapCutoffKey"
                                            @change="onHeatmapMonthChange($event)"
                                            @click.stop
                                        >
                                            <option v-for="opt in heatmapMonthOptions" :key="opt.key" :value="opt.key">
                                                {{ opt.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="search-box heatmap-search">
                                        <i class="bi bi-search"></i>
                                        <input
                                            type="text"
                                            v-model="heatmapSearchQuery"
                                            placeholder="Cari nama anggota..."
                                            @input="onHeatmapSearchInput"
                                            @click.stop
                                        />
                                    </div>
                                </div>

                                <div v-if="heatmapSectionLoaded && heatmapDateRangeLabel" class="heatmap-range-note">
                                    {{ heatmapDateRangeLabel }}
                                </div>

                                <div class="heatmap-board">
                                    <div v-if="heatmapSectionError" class="drawer-error-box is-compact">
                                        <strong>{{ heatmapSectionError }}</strong>
                                        <button type="button" class="pagination-btn" @click.stop="loadHeatmap(1)">
                                            Coba lagi
                                        </button>
                                    </div>

                                    <div class="heatmap-scroll">
                                        <!-- Baris header tanggal -->
                                        <div
                                            v-if="!heatmapSectionLoading && heatmapDayHeaders.length"
                                            class="heatmap-row heatmap-row--header"
                                        >
                                            <strong class="heatmap-corner"></strong>
                                            <div class="heatmap-row__days">
                                                <span
                                                    v-for="h in heatmapDayHeaders"
                                                    :key="`hdr-${h.key}`"
                                                    class="heat-dayhead"
                                                    :class="{
                                                        'is-weekstart': h.isWeekStart,
                                                        'is-today': h.isToday,
                                                        'is-weekend': h.isWeekend,
                                                    }"
                                                >
                                                    <em>{{ h.dow }}</em>
                                                    <b>{{ h.dateNum }}</b>
                                                </span>
                                            </div>
                                            <div class="heat-badges heat-badges--header">
                                                <span
                                                    v-for="cat in heatmapCategoryMeta"
                                                    :key="`hdr-cat-${cat.key}`"
                                                    class="heat-badge is-ghost"
                                                    :class="cat.cls"
                                                    :title="cat.label"
                                                >
                                                    <i :class="cat.icon"></i>
                                                </span>
                                            </div>
                                        </div>

                                        <template v-if="heatmapSectionLoading">
                                            <div
                                                v-for="n in 6"
                                                :key="`heatmap-skeleton-${n}`"
                                                class="heatmap-row is-skeleton"
                                            >
                                                <strong
                                                    ><span
                                                        class="leader-skeleton-line"
                                                        style="width: 80%; height: 0.6rem; display: block"
                                                    ></span
                                                ></strong>
                                                <div class="heatmap-row__days">
                                                    <span
                                                        v-for="i in heatmapDayCount"
                                                        :key="`heatmap-box-skeleton-${n}-${i}`"
                                                        class="heat-cell skeleton-avatar"
                                                        style="opacity: 0.4"
                                                    ></span>
                                                </div>
                                            </div>
                                        </template>

                                        <template v-else-if="heatmapRows.length">
                                            <div
                                                v-for="member in heatmapRows"
                                                :key="member.id"
                                                class="heatmap-row heatmap-row--button"
                                                role="button"
                                                tabindex="0"
                                                :aria-label="`Cari ${member.name} di Team List`"
                                                @click="openMemberFromHeatmap(member)"
                                                @keydown.enter.prevent="openMemberFromHeatmap(member)"
                                            >
                                                <strong>{{ member.name }}</strong>

                                                <div class="heatmap-row__days">
                                                    <span
                                                        v-for="day in member.attendance.dailyLog"
                                                        :key="`${member.id}-${day.fullDate || day.date}`"
                                                        class="heat-cell"
                                                        :class="[
                                                            `is-${day.tone}`,
                                                            { 'is-weekstart': day.dayKey === 1 },
                                                        ]"
                                                        :title="`${member.name} - ${day.date} - ${day.label}`"
                                                    ></span>
                                                </div>

                                                <div class="heat-badges">
                                                    <span
                                                        v-for="cat in heatmapCategoryMeta"
                                                        :key="`${member.id}-cat-${cat.key}`"
                                                        class="heat-badge"
                                                        :class="[
                                                            cat.cls,
                                                            {
                                                                'is-zero': !(
                                                                    (member.categoryCounts &&
                                                                        member.categoryCounts[cat.key]) ||
                                                                    0
                                                                ),
                                                            },
                                                        ]"
                                                        :title="cat.label"
                                                    >
                                                        <i :class="cat.icon"></i>
                                                        {{
                                                            (member.categoryCounts && member.categoryCounts[cat.key]) ||
                                                            0
                                                        }}
                                                    </span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

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
                                    <span v-for="cat in heatmapCategoryMeta" :key="`legend-${cat.key}`"
                                        ><i :class="cat.cls"></i> {{ cat.label }}</span
                                    >
                                </div>
                            </div>
                        </transition>
                    </section>

                    <!-- SHIFT VISUALIZATION -->
                    <!-- <section ref="shiftSectionRef" class="shift-board-card" @click.capture="ensureShiftSchedulesLoaded">
                        <div class="section-head">
                            <div>
                                <span class="section-label">Shift Distribution</span>
                                <h2>Distribusi Shift & Ketersediaan Anggota</h2>
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
                            <template v-if="shiftSectionLoading">
                                <div v-for="n in 3" :key="`shift-skeleton-${n}`" class="shift-card is-skeleton" style="cursor: default; padding: 0.62rem;">
                                    <div class="shift-card__head" style="margin-bottom: 0.5rem; border: none; padding: 0;">
                                        <div style="flex: 1;">
                                            <div class="leader-skeleton-line is-title mb-1" style="width: 70%"></div>
                                            <div class="leader-skeleton-line is-short" style="width: 40%"></div>
                                        </div>
                                        <div class="skeleton-avatar" style="width: 2rem; height: 1.5rem; border-radius: 0.4rem;"></div>
                                    </div>
                                    <div class="leader-skeleton-line" style="width: 100%; height: 0.4rem;"></div>
                                </div>
                            </template>

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

                                    <b>{{ shift.scheduledCount }}</b>
                                </div>

                                <div class="shift-member-pills is-summary-only">
                                    <span class="shift-summary-pill">
                                        <b>{{ shift.availableCount }}/{{ shift.scheduledCount }}</b>
                                        <em>tersedia / terjadwal</em>
                                    </span>
                                    <small v-if="shift.unavailableCount > 0" class="shift-summary-pill is-gap">
                                        <b>{{ shift.unavailableCount }}</b>
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
                                description="Belum ada anggota terjadwal pada tanggal ini."
                            />
                        </div>

                        <div v-if="shiftSectionError" class="drawer-error-box is-compact">
                            <strong>{{ shiftSectionError }}</strong>
                            <button type="button" class="pagination-btn" @click="retryShiftBoardLoad">Coba lagi</button>
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

                            <template v-if="shiftDetailLoading">
                                <div v-for="n in 3" :key="`shift-detail-skeleton-${n}`" class="shift-person is-skeleton">
                                    <div class="member-avatar is-sm skeleton-avatar" style="border-radius: 50%;"></div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div class="leader-skeleton-line is-title mb-1" style="width: 60%"></div>
                                        <div class="leader-skeleton-line is-short" style="width: 40%"></div>
                                    </div>
                                </div>
                            </template>

                            <div v-else-if="shiftDetailError" class="drawer-error-box is-compact">
                                <strong>{{ shiftDetailError }}</strong>
                                <button
                                    type="button"
                                    class="pagination-btn"
                                    @click="loadShiftDetail(selectedShiftDetailPage, selectedShiftDetail)"
                                >
                                    Coba lagi
                                </button>
                            </div>

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

                                    <small :class="person.availability?.is_available ? 'is-success' : 'is-warning'">
                                        {{ person.availability?.label || person.attendance.todayLabel }}
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
                    <section
                        ref="teamListSectionRef"
                        class="toolbar-card team-list-toolbar"
                        data-tour="leader-team-members"
                        :class="{ 'mobile-accordion-card': isMobileViewport }"
                        @click.capture="ensureMembersLoaded"
                    >
                        <div
                            class="toolbar-main"
                            :class="{ 'is-clickable': isMobileViewport, 'is-open': mobileLeaderPanelState.members }"
                            @click.stop="isMobileViewport ? toggleMobileLeaderPanel('members') : null"
                        >
                            <div
                                class="toolbar-title"
                                :style="{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    width: isMobileViewport ? '100%' : 'auto',
                                    flex: isMobileViewport ? '1 1 100%' : '0 0 auto',
                                    paddingRight: isMobileViewport ? '0' : '1rem',
                                }"
                            >
                                <div v-if="isMobileViewport" class="module-title">
                                    <div class="module-icon is-indigo"><i class="bi bi-people-fill"></i></div>
                                    <div class="module-title-copy">
                                        <span class="section-label">Team List</span>
                                        <h2>Daftar Anggota Tim</h2>
                                    </div>
                                </div>
                                <div v-else>
                                    <span class="section-label">Team List</span>
                                    <h2>Daftar Anggota Tim</h2>
                                </div>
                                <div class="mobile-accordion__meta" v-if="isMobileViewport">
                                    <span
                                        class="side-count"
                                        :class="memberAwarenessCount > 0 ? 'is-danger' : 'is-indigo'"
                                    >
                                        {{ memberAwarenessCount }}
                                    </span>
                                    <i
                                        :class="
                                            mobileLeaderPanelState.members ? 'bi bi-chevron-up' : 'bi bi-chevron-down'
                                        "
                                    ></i>
                                </div>
                            </div>

                            <transition
                                name="accordion"
                                @enter="onAccordionEnter"
                                @after-enter="onAccordionAfterEnter"
                                @leave="onAccordionLeave"
                            >
                                <div
                                    class="toolbar-actions"
                                    data-tour="leader-team-members-filters"
                                    v-show="!isMobileViewport || mobileLeaderPanelState.members"
                                >
                                    <div class="search-box" :class="{ 'is-loading': showMemberSearchFeedback }">
                                        <i
                                            :class="
                                                showMemberSearchFeedback
                                                    ? 'bi bi-arrow-repeat action-loading-spin is-loading'
                                                    : 'bi bi-search'
                                            "
                                        ></i>
                                        <input
                                            v-model="searchKeyword"
                                            type="text"
                                            placeholder="Cari anggota..."
                                            @focus="ensureMembersLoaded"
                                        />
                                    </div>

                                    <!-- Filter attendance/kpi/lms disembunyikan: butuh data SEMUA
                                         anggota (131), tak kompatibel dgn paginasi backend 5/halaman.
                                         Pencarian (search) tetap jalan via SQL. -->
                                    <select
                                        v-if="false"
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

                                    <select v-if="false" v-model="selectedKpi" class="filter-control" @focus="ensureMembersLoaded">
                                        <option value="all">Semua KPI</option>
                                        <option value="kpi-alert">Ada Alert KPI</option>
                                        <option value="complete">KPI Lengkap</option>
                                    </select>

                                    <select v-if="false" v-model="selectedLms" class="filter-control" @focus="ensureMembersLoaded">
                                        <option value="all">Semua LMS</option>
                                        <option value="pending">Training Pending</option>
                                        <option value="complete">Training Selesai</option>
                                    </select>
                                </div>
                            </transition>
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
                    <section
                        class="member-list team-list-content"
                        :class="{ 'is-loading': showTeamListSkeleton }"
                        data-tour="leader-team-members-content"
                        @click.capture="ensureMembersLoaded"
                        v-show="!isMobileViewport || mobileLeaderPanelState.members"
                    >
                        <template v-if="showTeamListSkeleton">
                            <div
                                v-if="showMemberSearchFeedback"
                                class="member-search-feedback"
                                role="status"
                                aria-live="polite"
                            >
                                <div class="loading-status-pill">
                                    <i
                                        :class="
                                            needsActionNavigationLoading
                                                ? 'bi bi-compass loading-status-icon'
                                                : 'bi bi-arrow-repeat loading-status-icon'
                                        "
                                    ></i>
                                    <strong>{{ memberSearchFeedbackTitle }}</strong>
                                </div>
                                <span>{{ memberSearchFeedbackDescription }}</span>
                                <div class="loading-progress-bar"><span></span></div>
                            </div>

                            <article v-for="n in 4" :key="`member-skeleton-${n}`" class="member-card is-skeleton">
                                <div class="member-row" style="cursor: default">
                                    <div class="member-profile">
                                        <div class="member-avatar skeleton-avatar"></div>
                                        <div class="member-name-wrap" style="flex: 1; min-width: 0">
                                            <div class="leader-skeleton-line is-title mb-1" style="width: 60%"></div>
                                            <div class="leader-skeleton-line is-short"></div>
                                        </div>
                                    </div>

                                    <div class="member-metrics is-monitoring">
                                        <div
                                            class="mini-metric"
                                            v-for="m in 3"
                                            :key="m"
                                            style="border-color: transparent; background: transparent"
                                        >
                                            <div
                                                class="leader-skeleton-line is-title mb-1"
                                                style="width: 60%; margin: 0 auto"
                                            ></div>
                                            <div
                                                class="leader-skeleton-line is-short"
                                                style="width: 40%; margin: 0 auto"
                                            ></div>
                                        </div>
                                    </div>

                                    <div class="member-row-action">
                                        <div
                                            class="skeleton-avatar"
                                            style="width: 3rem; height: 1rem; border-radius: 99px"
                                        ></div>
                                    </div>
                                </div>
                            </article>
                        </template>

                        <div v-else-if="memberSectionError" class="drawer-error-box">
                            <strong>{{ memberSectionError }}</strong>
                            <button type="button" class="pagination-btn" @click="loadMembers(1)">Coba lagi</button>
                        </div>

                        <EmptyState
                            v-else-if="!members.length"
                            icon="bi bi-people"
                            title="Belum ada anggota tim"
                            description="Klik area ini atau gunakan filter untuk memuat data anggota tim."
                        />

                        <EmptyState
                            v-else-if="!filteredMembers.length"
                            icon="bi bi-search"
                            title="Data tidak ditemukan"
                            description="Coba ubah keyword pencarian atau filter monitoring."
                        />

                        <article
                            v-else
                            v-for="(member, memberIndex) in filteredMembers"
                            :key="member.id"
                            class="member-card"
                            :data-tour="memberIndex === 0 ? 'leader-team-member-card' : null"
                            :class="{ 'is-open': openedMemberId === member.id }"
                        >
                            <button
                                type="button"
                                class="member-row"
                                @click="toggleMember(member)"
                                :aria-expanded="openedMemberId === member.id"
                                :aria-controls="`member-detail-${member.id}`"
                            >
                                <div
                                    class="member-profile"
                                    :data-tour="memberIndex === 0 ? 'leader-team-member-identity' : null"
                                >
                                    <div class="member-avatar">{{ member.initials }}</div>

                                    <div class="member-name-wrap">
                                        <div class="member-name-line d-flex">
                                            <h3 class="member-name-title">{{ member.name }}</h3>

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

                                <div
                                    class="member-metrics is-monitoring"
                                    :data-tour="memberIndex === 0 ? 'leader-team-member-metrics' : null"
                                >
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

                                <div
                                    class="member-row-action"
                                    :data-tour="memberIndex === 0 ? 'leader-team-member-status' : null"
                                >
                                    <span class="row-action-label">{{ member.attendance.todayLabel }}</span>
                                    <i
                                        class="bi bi-chevron-down member-chevron"
                                        :class="{ 'is-open': openedMemberId === member.id }"
                                    ></i>
                                </div>
                            </button>

                            <transition name="accordion">
                                <div
                                    v-if="openedMemberId === member.id"
                                    class="member-detail"
                                    :data-tour="memberIndex === 0 ? 'leader-team-member-detail' : null"
                                    :id="`member-detail-${member.id}`"
                                >
                                    <div class="detail-top">
                                        <div class="detail-top-header">
                                            <div class="detail-top-title">
                                                <i class="bi bi-flag-fill detail-top-icon"></i>
                                                <strong>Prioritas Tindak Lanjut</strong>
                                            </div>
                                            <div class="detail-tags">
                                                <span class="tag is-priority" :class="`is-${member.urgencyLevel}`">
                                                    <i
                                                        class="bi"
                                                        :class="{
                                                            'bi-exclamation-triangle-fill':
                                                                member.urgencyLevel === 'high',
                                                            'bi-exclamation-circle-fill':
                                                                member.urgencyLevel === 'medium',
                                                            'bi-check-circle-fill': member.urgencyLevel === 'low',
                                                        }"
                                                    ></i>
                                                    {{ member.urgencyLabel }}
                                                </span>

                                                <span
                                                    v-if="
                                                        member.attendance.issues.length || member.attendance.issueCount
                                                    "
                                                    class="tag is-danger"
                                                >
                                                    {{
                                                        member.attendance.issues.length || member.attendance.issueCount
                                                    }}
                                                    issue absensi
                                                </span>

                                                <span v-if="member.kpi.pendingItems.length" class="tag is-warning">
                                                    {{ member.kpi.pendingItems.length }} alert KPI
                                                </span>

                                                <span v-if="member.approvalCount" class="tag is-success">
                                                    {{ member.approvalCount }} approval
                                                </span>

                                                <span v-if="isMemberDetailLoading(member.id)" class="tag is-info">
                                                    <i class="bi bi-hourglass-split"></i> Memuat...
                                                </span>

                                                <span v-if="!member.issueCount" class="tag is-info">
                                                    <i class="bi bi-shield-check"></i> Aman
                                                </span>
                                            </div>
                                        </div>

                                        <p class="detail-headline">{{ member.prioritySummary.headline }}</p>

                                        <div class="priority-summary-grid">
                                            <div class="priority-summary-item is-issue">
                                                <div class="priority-summary-icon">
                                                    <i class="bi bi-bullseye"></i>
                                                </div>
                                                <div class="priority-summary-content">
                                                    <small>Masalah utama</small>
                                                    <b>{{ member.prioritySummary.mainIssue }}</b>
                                                </div>
                                            </div>

                                            <div class="priority-summary-item is-impact">
                                                <div class="priority-summary-icon">
                                                    <i class="bi bi-lightning-charge-fill"></i>
                                                </div>
                                                <div class="priority-summary-content">
                                                    <small>Dampak jika ditunda</small>
                                                    <b>{{ member.prioritySummary.delayImpact }}</b>
                                                </div>
                                            </div>

                                            <div class="priority-summary-item is-action">
                                                <div class="priority-summary-icon">
                                                    <i class="bi bi-cursor-fill"></i>
                                                </div>
                                                <div class="priority-summary-content">
                                                    <small>Tindakan pertama</small>
                                                    <b>{{ member.prioritySummary.firstAction }}</b>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="priority-intel-grid">
                                        <section
                                            class="priority-intel-card"
                                            :data-tour="
                                                memberIndex === 0 ? 'leader-team-member-priority-reasons' : null
                                            "
                                        >
                                            <header class="priority-intel-head">
                                                <div class="priority-intel-head-left">
                                                    <i
                                                        class="bi bi-graph-up-arrow priority-intel-head-icon is-reason"
                                                    ></i>
                                                    <strong>Kenapa {{ member.urgencyLabel }}?</strong>
                                                </div>
                                                <span class="priority-intel-count"
                                                    >{{ member.priorityReasons.length }} indikator</span
                                                >
                                            </header>

                                            <div class="priority-reason-list">
                                                <article
                                                    v-for="(reason, rIdx) in member.priorityReasons"
                                                    :key="`${member.id}-${reason.key}`"
                                                    class="priority-reason-item"
                                                    :class="{
                                                        'is-top': rIdx === 0,
                                                        'is-neutral': reason.scoreImpact === 0,
                                                    }"
                                                >
                                                    <div
                                                        class="priority-reason-indicator"
                                                        :class="{
                                                            'is-high': reason.scoreImpact >= 4,
                                                            'is-medium':
                                                                reason.scoreImpact >= 2 && reason.scoreImpact < 4,
                                                            'is-low': reason.scoreImpact > 0 && reason.scoreImpact < 2,
                                                            'is-none': reason.scoreImpact === 0,
                                                        }"
                                                    ></div>
                                                    <div class="priority-reason-body">
                                                        <div class="priority-reason-top">
                                                            <strong>{{ reason.label }}</strong>
                                                            <span class="priority-reason-value">{{
                                                                reason.valueLabel
                                                            }}</span>
                                                        </div>
                                                        <span class="priority-reason-evidence">{{
                                                            reason.evidence
                                                        }}</span>
                                                    </div>
                                                </article>
                                            </div>
                                        </section>

                                        <section class="priority-intel-card is-action-card">
                                            <header class="priority-intel-head">
                                                <div class="priority-intel-head-left">
                                                    <i
                                                        class="bi bi-lightning-charge-fill priority-intel-head-icon is-action"
                                                    ></i>
                                                    <strong>Langkah Selanjutnya</strong>
                                                </div>
                                                <span class="priority-intel-count"
                                                    >{{ member.nextActions.length }} aksi</span
                                                >
                                            </header>

                                            <div class="priority-action-list">
                                                <button
                                                    v-for="(action, aIdx) in member.nextActions"
                                                    :key="`${member.id}-${action.id}`"
                                                    type="button"
                                                    class="priority-action-item"
                                                    :class="{ 'is-primary': aIdx === 0 }"
                                                    @click.stop="handleMemberPriorityAction(member, action)"
                                                >
                                                    <div class="priority-action-number">{{ aIdx + 1 }}</div>
                                                    <div class="priority-action-body">
                                                        <strong>{{ action.label }}</strong>
                                                        <span class="priority-action-urgency">{{
                                                            action.urgency
                                                        }}</span>
                                                    </div>
                                                    <i class="bi bi-arrow-right priority-action-arrow"></i>
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
                                        <section
                                            class="detail-panel detail-panel--wide"
                                            :data-tour="memberIndex === 0 ? 'leader-team-member-hcis-detail' : null"
                                        >
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
                                                                'is-warning': member.attendance.noCheckinCount > 0,
                                                            }"
                                                        >
                                                            <span>No Checkin</span>
                                                            <strong>{{ member.attendance.noCheckinCount }}x</strong>
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

                                                <div
                                                    class="member-weekly-calendar"
                                                    :data-tour="
                                                        memberIndex === 0 ? 'leader-team-member-weekly-calendar' : null
                                                    "
                                                >
                                                    <div class="weekly-cal__head">
                                                        <div class="weekly-cal__title">
                                                            <i class="bi bi-calendar-week"></i>
                                                            <strong>Kalender Mingguan</strong>
                                                        </div>
                                                        <div class="weekly-cal__nav">
                                                            <button
                                                                type="button"
                                                                class="weekly-cal__nav-btn"
                                                                :disabled="
                                                                    !canNavigateWeekPrev(member) ||
                                                                    isMemberCalendarLoading(member.id)
                                                                "
                                                                @click.stop="navigateMemberWeek(member.id, -1)"
                                                                title="Minggu Sebelumnya"
                                                            >
                                                                <i class="bi bi-chevron-left"></i>
                                                            </button>
                                                            <span class="weekly-cal__range">
                                                                <template v-if="isMemberCalendarLoading(member.id)">
                                                                    <span class="weekly-cal__range-skel"></span>
                                                                </template>
                                                                <template v-else>{{
                                                                    getMemberCalendarRangeLabel(member)
                                                                }}</template>
                                                            </span>
                                                            <button
                                                                type="button"
                                                                class="weekly-cal__nav-btn"
                                                                :disabled="
                                                                    !canNavigateWeekNext(member) ||
                                                                    isMemberCalendarLoading(member.id)
                                                                "
                                                                @click.stop="navigateMemberWeek(member.id, 1)"
                                                                title="Minggu Berikutnya"
                                                            >
                                                                <i class="bi bi-chevron-right"></i>
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <!-- Loading Skeleton -->
                                                    <div
                                                        v-if="
                                                            isMemberCalendarLoading(member.id) &&
                                                            !getMemberCalendarDays(member).length
                                                        "
                                                        class="weekly-cal__grid weekly-cal__grid--skeleton"
                                                    >
                                                        <div
                                                            v-for="n in 7"
                                                            :key="`skel-${n}`"
                                                            class="weekly-cal__day-skel"
                                                        >
                                                            <span></span><span></span><span></span><span></span>
                                                        </div>
                                                    </div>

                                                    <!-- Calendar Grid -->
                                                    <div
                                                        v-else-if="getMemberCalendarDays(member).length"
                                                        class="weekly-cal__grid"
                                                        :class="{ 'is-loading': isMemberCalendarLoading(member.id) }"
                                                    >
                                                        <button
                                                            v-for="day in getMemberCalendarDays(member)"
                                                            :key="`cal-${member.id}-${day.fullDate}`"
                                                            type="button"
                                                            class="weekly-cal__day"
                                                            :class="[
                                                                `is-${day.tone}`,
                                                                {
                                                                    'is-today': day.isToday,
                                                                    'is-active':
                                                                        memberCalendarActiveDay.memberId ===
                                                                            member.id &&
                                                                        memberCalendarActiveDay.date === day.fullDate,
                                                                },
                                                            ]"
                                                            @click.stop="toggleMemberCalendarDay(member, day)"
                                                        >
                                                            <span class="weekly-cal__day-name">{{ day.day }}</span>
                                                            <span class="weekly-cal__day-num">{{ day.dateNum }}</span>
                                                            <span class="weekly-cal__day-dot">
                                                                <i
                                                                    v-if="day.tone === 'holiday'"
                                                                    class="bi bi-calendar-heart-fill"
                                                                ></i>
                                                                <i
                                                                    v-else-if="day.tone === 'success'"
                                                                    class="bi bi-check-circle-fill"
                                                                ></i>
                                                                <i
                                                                    v-else-if="day.tone === 'warning'"
                                                                    class="bi bi-exclamation-triangle-fill"
                                                                ></i>
                                                                <i
                                                                    v-else-if="day.tone === 'danger'"
                                                                    class="bi bi-x-circle-fill"
                                                                ></i>
                                                                <i
                                                                    v-else-if="day.tone === 'info'"
                                                                    class="bi bi-info-circle-fill"
                                                                ></i>
                                                                <i
                                                                    v-else-if="day.tone === 'working'"
                                                                    class="bi bi-briefcase-fill"
                                                                ></i>
                                                                <i
                                                                    v-else-if="day.tone === 'secondary'"
                                                                    class="bi bi-hourglass-split"
                                                                ></i>
                                                                <i v-else class="bi bi-dash-circle"></i>
                                                            </span>
                                                            <span class="weekly-cal__day-label">{{
                                                                day.shortLabel
                                                            }}</span>
                                                            <div
                                                                v-if="day.overtimeHours > 0"
                                                                class="weekly-cal__day-badge is-overtime"
                                                                title="Lembur"
                                                            >
                                                                <i class="bi bi-clock-history"></i>
                                                            </div>
                                                            <div
                                                                v-if="day.pengajuan"
                                                                class="weekly-cal__day-badge is-pengajuan"
                                                                :title="day.pengajuan.label"
                                                            >
                                                                <i class="bi bi-file-earmark-text"></i>
                                                            </div>
                                                        </button>
                                                    </div>

                                                    <!-- Empty State -->
                                                    <div v-else class="weekly-cal__empty">
                                                        <i class="bi bi-calendar-x"></i>
                                                        <span>Belum ada data kalender</span>
                                                    </div>

                                                    <!-- Day Detail Popover -->
                                                    <transition name="cal-popover-fade">
                                                        <div
                                                            v-if="
                                                                memberCalendarActiveDay.memberId === member.id &&
                                                                memberCalendarActiveDay.date
                                                            "
                                                            class="weekly-cal__popover"
                                                        >
                                                            <div class="weekly-cal__popover-head">
                                                                <div>
                                                                    <strong
                                                                        >{{ memberCalendarActiveDay.data?.day }},
                                                                        {{ memberCalendarActiveDay.data?.date }}</strong
                                                                    >
                                                                    <span
                                                                        :class="`tone-badge is-${memberCalendarActiveDay.data?.tone}`"
                                                                        >{{ memberCalendarActiveDay.data?.label }}</span
                                                                    >
                                                                </div>
                                                                <button
                                                                    type="button"
                                                                    class="weekly-cal__popover-close"
                                                                    @click.stop="closeMemberCalendarDay()"
                                                                >
                                                                    <i class="bi bi-x-lg"></i>
                                                                </button>
                                                            </div>

                                                            <div class="weekly-cal__popover-body">
                                                                <div class="weekly-cal__popover-grid">
                                                                    <div class="weekly-cal__popover-cell">
                                                                        <span class="weekly-cal__popover-cell-label"
                                                                            ><i class="bi bi-box-arrow-in-right"></i>
                                                                            Check In</span
                                                                        >
                                                                        <strong>{{
                                                                            memberCalendarActiveDay.data?.checkIn || '-'
                                                                        }}</strong>
                                                                    </div>
                                                                    <div class="weekly-cal__popover-cell">
                                                                        <span class="weekly-cal__popover-cell-label"
                                                                            ><i class="bi bi-box-arrow-right"></i> Check
                                                                            Out</span
                                                                        >
                                                                        <strong>{{
                                                                            memberCalendarActiveDay.data?.checkOut ||
                                                                            '-'
                                                                        }}</strong>
                                                                    </div>
                                                                </div>

                                                                <div
                                                                    v-if="memberCalendarActiveDay.data?.holidayName"
                                                                    class="weekly-cal__popover-row is-holiday"
                                                                >
                                                                    <span
                                                                        ><i class="bi bi-calendar-heart-fill"></i> Hari
                                                                        Libur</span
                                                                    >
                                                                    <strong>{{
                                                                        memberCalendarActiveDay.data.holidayName
                                                                    }}</strong>
                                                                </div>

                                                                <div
                                                                    v-if="memberCalendarActiveDay.data?.shiftName"
                                                                    class="weekly-cal__popover-row"
                                                                >
                                                                    <span><i class="bi bi-clock"></i> Shift</span>
                                                                    <strong
                                                                        >{{ memberCalendarActiveDay.data.shiftName }}
                                                                        <template
                                                                            v-if="
                                                                                memberCalendarActiveDay.data?.shiftTime
                                                                            "
                                                                            >({{
                                                                                memberCalendarActiveDay.data.shiftTime
                                                                            }})</template
                                                                        ></strong
                                                                    >
                                                                </div>

                                                                <div
                                                                    v-if="memberCalendarActiveDay.data?.lateMinutes > 0"
                                                                    class="weekly-cal__popover-row is-warning"
                                                                >
                                                                    <span><i class="bi bi-alarm"></i> Terlambat</span>
                                                                    <strong
                                                                        >{{
                                                                            memberCalendarActiveDay.data.lateMinutes
                                                                        }}
                                                                        menit</strong
                                                                    >
                                                                </div>
                                                                <div
                                                                    v-if="memberCalendarActiveDay.data?.isEarlyOut"
                                                                    class="weekly-cal__popover-row is-orange"
                                                                >
                                                                    <span
                                                                        ><i class="bi bi-door-open"></i> Pulang
                                                                        Cepat</span
                                                                    >
                                                                    <strong
                                                                        >{{
                                                                            memberCalendarActiveDay.data
                                                                                ?.earlyMinutes || 0
                                                                        }}
                                                                        menit</strong
                                                                    >
                                                                </div>
                                                                <div
                                                                    v-if="
                                                                        memberCalendarActiveDay.data?.overtimeHours > 0
                                                                    "
                                                                    class="weekly-cal__popover-row is-info"
                                                                >
                                                                    <span
                                                                        ><i class="bi bi-clock-history"></i>
                                                                        Lembur</span
                                                                    >
                                                                    <strong
                                                                        >{{
                                                                            memberCalendarActiveDay.data.overtimeHours
                                                                        }}
                                                                        jam</strong
                                                                    >
                                                                </div>
                                                                <div
                                                                    v-if="memberCalendarActiveDay.data?.pengajuan"
                                                                    class="weekly-cal__popover-row is-info"
                                                                >
                                                                    <span
                                                                        ><i class="bi bi-file-earmark-check"></i>
                                                                        {{
                                                                            memberCalendarActiveDay.data.pengajuan.label
                                                                        }}</span
                                                                    >
                                                                    <strong class="tone-badge is-info">{{
                                                                        memberCalendarActiveDay.data.pengajuan.status
                                                                    }}</strong>
                                                                </div>

                                                                <button
                                                                    type="button"
                                                                    class="weekly-cal__riwayat-btn"
                                                                    @click.stop="
                                                                        openMemberDayRiwayat(
                                                                            member,
                                                                            memberCalendarActiveDay.date,
                                                                            memberCalendarActiveDay.data,
                                                                        )
                                                                    "
                                                                >
                                                                    <i class="bi bi-clock-history"></i>
                                                                    <span>Riwayat Absensi</span>
                                                                    <i class="bi bi-arrow-right-short"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </transition>

                                                    <div class="weekly-cal__legend">
                                                        <span><i class="is-success"></i> Hadir</span>
                                                        <span><i class="is-warning"></i> Telat</span>
                                                        <span><i class="is-danger"></i> Issue</span>
                                                        <span><i class="is-working"></i> Bekerja</span>
                                                        <span><i class="is-info"></i> Izin/Cuti</span>
                                                        <span><i class="is-default"></i> OFF</span>
                                                        <span><i class="is-holiday"></i> Hari Libur</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div
                                                class="hcis-issue-stack"
                                                :data-tour="memberIndex === 0 ? 'leader-team-member-issue-list' : null"
                                            >
                                                <IssueList
                                                    :items="member.attendance.issues"
                                                    empty-title="Absensi aman"
                                                    empty-description="Tidak ada issue absensi pada periode ini."
                                                />
                                            </div>
                                        </section>

                                        <!-- KPI DETAIL -->
                                        <section
                                            class="detail-panel detail-panel--kpi"
                                            :data-tour="memberIndex === 0 ? 'leader-team-member-kpi-detail' : null"
                                        >
                                            <PanelHead
                                                icon="bi bi-bar-chart-line"
                                                title="KPI"
                                                :count="memberKpiPanelView(member).performancePeriod"
                                            />

                                            <div
                                                v-if="getMemberKpiPanelError(member.id)"
                                                class="drawer-error-box is-compact"
                                            >
                                                <strong>{{ getMemberKpiPanelError(member.id) }}</strong>
                                            </div>

                                            <!-- Warnings (only when data is loaded) -->
                                            <div
                                                v-if="
                                                    !isMemberKpiPanelLoading(member.id) &&
                                                    memberKpiPanelView(member).pendingItems &&
                                                    memberKpiPanelView(member).pendingItems.length
                                                "
                                                class="kpi-warning-list"
                                            >
                                                <div
                                                    v-for="item in memberKpiPanelView(member).pendingItems"
                                                    :key="item.id || item.key"
                                                    class="inline-warning"
                                                    :class="
                                                        item.tone === 'info'
                                                            ? 'is-info'
                                                            : item.tone === 'success'
                                                              ? 'is-success'
                                                              : 'is-warning'
                                                    "
                                                >
                                                    <i
                                                        :class="
                                                            item.tone === 'info'
                                                                ? 'bi bi-info-circle'
                                                                : 'bi bi-exclamation-circle'
                                                        "
                                                    ></i>
                                                    <span
                                                        ><strong>{{ item.title }}:</strong>
                                                        {{ item.description || item.title }}</span
                                                    >
                                                </div>
                                            </div>

                                            <!-- Score + Selects (always same layout) -->
                                            <div class="kpi-layout">
                                                <!-- Score card: skeleton or real -->
                                                <div class="kpi-score-card">
                                                    <template v-if="isMemberKpiPanelLoading(member.id)">
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
                                                                style="
                                                                    width: 65%;
                                                                    background: rgba(255, 255, 255, 0.28);
                                                                "
                                                            ></div>
                                                            <div
                                                                class="leader-skeleton-line is-title mb-1"
                                                                style="
                                                                    width: 48%;
                                                                    background: rgba(255, 255, 255, 0.32);
                                                                "
                                                            ></div>
                                                            <div
                                                                class="leader-skeleton-line is-short"
                                                                style="
                                                                    width: 58%;
                                                                    background: rgba(255, 255, 255, 0.22);
                                                                "
                                                            ></div>
                                                        </div>
                                                    </template>
                                                    <template v-else>
                                                        <strong>{{ memberKpiPanelView(member).score }}</strong>
                                                        <span>Skor Terpilih</span>
                                                        <small>{{
                                                            memberKpiPanelView(member).performancePeriod
                                                        }}</small>
                                                        <div class="kpi-score-card__mini">
                                                            <span>Achievement YTD</span>
                                                            <strong>{{ memberKpiPanelView(member).ytdScore }}%</strong>
                                                            <small
                                                                >{{
                                                                    memberKpiPanelView(member).ytdSubmittedCount
                                                                }}
                                                                periode terisi</small
                                                            >
                                                        </div>
                                                    </template>
                                                </div>

                                                <!-- Right: selects always usable + stats -->
                                                <div class="kpi-detail-stack">
                                                    <div class="kpi-filter-row">
                                                        <select
                                                            :value="getMemberKpiPanelState(member).draftYear"
                                                            :disabled="isMemberKpiPanelLoading(member.id)"
                                                            @change="
                                                                handleMemberKpiYearChange(member, $event?.target?.value)
                                                            "
                                                        >
                                                            <option
                                                                v-for="year in kpiYearOptions"
                                                                :key="`${member.id}-year-${year}`"
                                                                :value="year"
                                                            >
                                                                {{ year }}
                                                            </option>
                                                        </select>
                                                        <select
                                                            :value="getMemberKpiPanelState(member).draftPeriod"
                                                            :disabled="isMemberKpiPanelLoading(member.id)"
                                                            @change="
                                                                handleMemberKpiPeriodChange(
                                                                    member,
                                                                    $event?.target?.value,
                                                                )
                                                            "
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

                                                    <!-- Stats: skeleton or real -->
                                                    <template v-if="isMemberKpiPanelLoading(member.id)">
                                                        <div class="kpi-stat-grid" style="grid-template-columns: 1fr">
                                                            <div class="is-skeleton">
                                                                <div class="leader-skeleton-line is-short mb-1"></div>
                                                                <div
                                                                    class="leader-skeleton-line is-title mb-1"
                                                                    style="width: 55%"
                                                                ></div>
                                                                <div
                                                                    class="leader-skeleton-line"
                                                                    style="width: 70%"
                                                                ></div>
                                                            </div>
                                                        </div>
                                                        <div class="kpi-status-panel">
                                                            <div
                                                                v-for="n in 2"
                                                                :key="`kpi-stat-sk-${n}`"
                                                                class="is-skeleton"
                                                            >
                                                                <div class="leader-skeleton-line is-short mb-1"></div>
                                                                <div
                                                                    class="leader-skeleton-line is-title"
                                                                    style="width: 65%"
                                                                ></div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <template v-else>
                                                        <div
                                                            class="kpi-stat-grid"
                                                            style="grid-template-columns: 1fr 1fr"
                                                        >
                                                            <div>
                                                                <span>Achievement Grade</span>
                                                                <strong
                                                                    :style="{
                                                                        color: memberKpiPanelView(member)
                                                                            .achievementGradeColor,
                                                                    }"
                                                                    >{{
                                                                        memberKpiPanelView(member).achievementGradeLabel
                                                                    }}</strong
                                                                >
                                                                <small>periode terpilih</small>
                                                            </div>
                                                            <div>
                                                                <span>YTD Grade</span>
                                                                <strong
                                                                    :style="{
                                                                        color: memberKpiPanelView(member).ytdGradeColor,
                                                                    }"
                                                                    >{{
                                                                        memberKpiPanelView(member).ytdGradeLabel
                                                                    }}</strong
                                                                >
                                                                <small>year to date</small>
                                                            </div>
                                                        </div>
                                                        <div class="kpi-status-panel">
                                                            <div class="kpi-status-panel__item">
                                                                <span>KPI Parameter</span>
                                                                <strong
                                                                    :class="
                                                                        getParameterStatusTone(
                                                                            memberKpiPanelView(member),
                                                                        )
                                                                    "
                                                                >
                                                                    {{
                                                                        getParameterStatusLabel(
                                                                            memberKpiPanelView(member),
                                                                        )
                                                                    }}
                                                                </strong>
                                                            </div>
                                                            <div class="kpi-status-panel__item">
                                                                <span>Update Performance</span>
                                                                <strong
                                                                    :class="
                                                                        getPerformanceStatusTone(
                                                                            memberKpiPanelView(member),
                                                                        )
                                                                    "
                                                                >
                                                                    {{
                                                                        getPerformanceStatusLabel(
                                                                            memberKpiPanelView(member),
                                                                        )
                                                                    }}
                                                                </strong>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>

                                            <!-- Parameter list header -->
                                            <div class="kpi-list-header">
                                                <template v-if="isMemberKpiPanelLoading(member.id)">
                                                    <div
                                                        class="leader-skeleton-line"
                                                        style="width: 6rem; height: 0.6rem"
                                                    ></div>
                                                </template>
                                                <template v-else>
                                                    <i class="bi bi-list-check"></i>
                                                    <span>Parameter KPI</span>
                                                </template>
                                            </div>

                                            <!-- Parameter list: skeleton, empty, or data -->
                                            <div
                                                class="mini-parameter-list"
                                                :class="{ 'is-skeleton': isMemberKpiPanelLoading(member.id) }"
                                            >
                                                <template v-if="isMemberKpiPanelLoading(member.id)">
                                                    <div v-for="n in 3" :key="`kpi-param-sk-${n}`">
                                                        <div>
                                                            <div
                                                                class="leader-skeleton-line mb-1"
                                                                style="width: 65%"
                                                            ></div>
                                                            <div class="leader-skeleton-line is-short"></div>
                                                        </div>
                                                        <div class="leader-skeleton-line" style="height: 0.35rem"></div>
                                                    </div>
                                                </template>
                                                <template
                                                    v-else-if="
                                                        !memberKpiPanelView(member).parameters ||
                                                        !memberKpiPanelView(member).parameters.length
                                                    "
                                                >
                                                    <EmptyState
                                                        compact
                                                        icon="bi bi-list-check"
                                                        title="Parameter KPI kosong"
                                                        description="Belum ada Parameter KPI tahun/periode ini."
                                                    />
                                                </template>
                                                <template v-else>
                                                    <span
                                                        v-for="param in memberKpiPanelView(member).parameters"
                                                        :key="param.id || param.name"
                                                    >
                                                        <span
                                                            :class="{
                                                                'parameter not-inPeriode':
                                                                    param.status === 'Periode KPI belum aktif',
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
                                                                    <small v-if="param.weight"
                                                                        >({{ param.weight }}%)</small
                                                                    >
                                                                </strong>
                                                            </div>
                                                            <small class="mini-parameter-meta">
                                                                Target {{ param.target }} · Actual {{ param.actual }} ·
                                                                {{ param.status }}
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
                                                        </span>
                                                    </span>
                                                </template>
                                            </div>

                                            <!-- Trend Chart: skeleton or real -->
                                            <div class="chart-block">
                                                <div class="chart-head">
                                                    <template v-if="isMemberKpiPanelLoading(member.id)">
                                                        <div
                                                            class="leader-skeleton-line is-title"
                                                            style="width: 8rem"
                                                        ></div>
                                                        <div
                                                            class="leader-skeleton-line is-short"
                                                            style="width: 5rem"
                                                        ></div>
                                                    </template>
                                                    <template v-else>
                                                        <span>Trend KPI 6 Bulan</span>
                                                        <strong>{{ memberKpiPanelView(member).trendDirection }}</strong>
                                                    </template>
                                                </div>
                                                <div class="chart-safe">
                                                    <template v-if="isMemberKpiPanelLoading(member.id)">
                                                        <div style="height: 80px; padding: 0.5rem">
                                                            <div
                                                                class="leader-skeleton-line"
                                                                style="height: 100%; border-radius: 0.5rem"
                                                            ></div>
                                                        </div>
                                                    </template>
                                                    <template v-else>
                                                        <VueApexCharts
                                                            v-if="
                                                                memberKpiPanelView(member).trend &&
                                                                memberKpiPanelView(member).trend.length
                                                            "
                                                            :key="`member-kpi-trend-${member.id}-${getMemberKpiPanelState(member).appliedPeriod}`"
                                                            type="area"
                                                            height="96"
                                                            :options="getMemberKpiChartOptions(member)"
                                                            :series="getMemberKpiChartSeries(member)"
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
                                        </section>

                                        <!-- LMS DETAIL -->
                                        <section
                                            class="detail-panel detail-panel--lms"
                                            :data-tour="memberIndex === 0 ? 'leader-team-member-lms-detail' : null"
                                        >
                                            <div class="module-card__head">
                                                <div class="module-title">
                                                    <div class="module-icon is-teal">
                                                        <i class="bi bi-mortarboard"></i>
                                                    </div>
                                                    <div class="module-title-copy">
                                                        <span class="section-label">Learning</span>
                                                        <h2>Learning Management</h2>
                                                    </div>
                                                </div>
                                                <span class="period-badge is-teal"
                                                    >{{ memberLmsView(member).completed }}/{{
                                                        memberLmsView(member).total
                                                    }}
                                                    selesai</span
                                                >
                                            </div>

                                            <EmptyState
                                                v-if="!memberLmsView(member).hasData"
                                                icon="bi bi-journal-bookmark"
                                                title="Belum ada training"
                                                description="Training belum diberikan atau data LMS belum tersedia."
                                            />

                                            <template v-else>
                                                <!-- 1. Progress Hero -->
                                                <div class="lms-hero">
                                                    <div class="lms-progress-ring">
                                                        <span>Overall</span>
                                                        <strong>{{ memberLmsView(member).progress }}%</strong>
                                                    </div>
                                                    <div class="lms-hero__info">
                                                        <div class="lms-hero__top">
                                                            <span>Progress Keseluruhan</span>
                                                            <strong class="lms-hero__badge">{{
                                                                memberLmsView(member).progressLabel
                                                            }}</strong>
                                                        </div>
                                                        <div class="mini-progress is-large">
                                                            <div
                                                                class="mini-progress__fill is-teal"
                                                                :style="{
                                                                    width: `${safePercent(memberLmsView(member).progress)}%`,
                                                                }"
                                                            ></div>
                                                        </div>
                                                        <small
                                                            >{{ memberLmsView(member).completed }}/{{
                                                                memberLmsView(member).total
                                                            }}
                                                            training selesai</small
                                                        >
                                                    </div>
                                                </div>

                                                <!-- 2. Quick Stats -->
                                                <div class="lms-quick-stats">
                                                    <div class="lms-stat-pill">
                                                        <i class="bi bi-clock-history"></i>
                                                        <div>
                                                            <span>Avg Durasi</span>
                                                            <strong>{{
                                                                memberLmsView(member).avgLearningDurationLabel
                                                            }}</strong>
                                                        </div>
                                                    </div>
                                                    <div class="lms-stat-pill">
                                                        <i class="bi bi-graph-up-arrow"></i>
                                                        <div>
                                                            <span>Avg Learning Gain</span>
                                                            <strong>{{
                                                                memberLmsView(member).learningGain
                                                                    ? `+${memberLmsView(member).learningGain}%`
                                                                    : '-'
                                                            }}</strong>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- 3. Next Training -->
                                                <div
                                                    v-if="
                                                        memberLmsView(member).nextSession &&
                                                        memberLmsView(member).nextSession !== '-' &&
                                                        memberLmsView(member).nextSession !== 'Tidak ada training aktif'
                                                    "
                                                    class="lms-next-highlight"
                                                >
                                                    <i class="bi bi-calendar2-check"></i>
                                                    <div class="lms-next-highlight__info">
                                                        <span>Training Berikutnya</span>
                                                        <strong>{{ memberLmsView(member).nextSession }}</strong>
                                                    </div>
                                                    <small class="lms-next-highlight__date">{{
                                                        memberLmsView(member).nextSessionDate
                                                    }}</small>
                                                </div>

                                                <!-- 4. Training List header -->
                                                <div class="lms-list-header">
                                                    <i class="bi bi-list-check"></i>
                                                    <span>Daftar Training</span>
                                                </div>

                                                <!-- Training list -->
                                                <div class="training-list">
                                                    <div
                                                        v-for="course in memberLmsView(member).progressList.slice(0, 6)"
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
                                                    <template v-if="memberLmsView(member).progressList.length > 0">
                                                        <div
                                                            v-for="n in Math.max(
                                                                0,
                                                                3 - memberLmsView(member).progressList.length,
                                                            )"
                                                            :key="`member-lms-placeholder-${n}`"
                                                            class="training-item-placeholder"
                                                        >
                                                            <div class="placeholder-dot"></div>
                                                            <span>Slot training kosong</span>
                                                        </div>
                                                    </template>
                                                    <EmptyState
                                                        v-if="!memberLmsView(member).progressList.length"
                                                        compact
                                                        icon="bi bi-journal"
                                                        title="List training kosong"
                                                        description="Belum ada training aktif."
                                                    />
                                                </div>

                                                <!-- 5. Duration Chart -->
                                                <div class="chart-block">
                                                    <div class="chart-head">
                                                        <span>Durasi Belajar Per Bulan</span>
                                                        <strong
                                                            >{{
                                                                memberLmsView(member).avgMonthlyDurationLabel
                                                            }}
                                                            avg</strong
                                                        >
                                                    </div>
                                                    <div class="chart-safe">
                                                        <VueApexCharts
                                                            v-if="memberLmsView(member).durationMonthly.length"
                                                            :key="`member-lms-duration-${member.id}-${memberLmsView(member).durationMonthly.length}`"
                                                            type="area"
                                                            height="96"
                                                            :options="getMemberLmsChartOptions(member)"
                                                            :series="getMemberLmsChartSeries(member)"
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
                                            </template>
                                        </section>

                                        <!-- CUTI / APPROVAL -->
                                        <section
                                            class="detail-panel detail-panel--approval"
                                            :data-tour="memberIndex === 0 ? 'leader-team-member-approval-detail' : null"
                                        >
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
                                                <button
                                                    v-for="approval in member.approvals"
                                                    :key="approval.id"
                                                    class="approval-item"
                                                    :class="`is-${approval.tone}`"
                                                    type="button"
                                                    @click="openApprovalQueue(approval.url, approval.id)"
                                                >
                                                    <i :class="approval.icon"></i>

                                                    <div>
                                                        <strong>{{ approval.title }}</strong>
                                                        <span>{{ approval.description }}</span>
                                                        <small v-if="approval.expired_at" class="approval-expiry">
                                                            Batas: {{ formatDateId(approval.expired_at) }}
                                                        </small>
                                                    </div>

                                                    <small :class="approvalStatusClass(approval)">
                                                        {{ approval.statusLabel }}
                                                    </small>
                                                </button>

                                                <EmptyState
                                                    v-if="!member.approvalCount"
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
                        data-tour="leader-team-calendar"
                        :class="{ 'mobile-accordion-card': isMobileViewport }"
                        tabindex="0"
                        @click.capture="ensureTeamCalendarMonthLoaded"
                        @focusin.capture="ensureTeamCalendarMonthLoaded"
                    >
                        <button
                            v-if="isMobileViewport"
                            type="button"
                            class="team-calendar-panel__head is-clickable"
                            :class="{ 'is-open': mobileLeaderPanelState.calendar }"
                            @click.stop="toggleMobileLeaderPanel('calendar')"
                            style="width: 100%; text-align: left; background: none; border: none; padding: 0"
                        >
                            <div class="module-title">
                                <div class="module-icon is-orange">
                                    <i class="bi bi-calendar2-week"></i>
                                </div>
                                <div class="module-title-copy">
                                    <span class="section-label">Kalender Cutoff Tim</span>
                                    <h2>Pantauan Harian Tim</h2>
                                </div>
                            </div>
                            <div class="mobile-accordion__meta">
                                <span
                                    class="side-count"
                                    :class="(teamCalendarStats.issue_day_count || 0) > 0 ? 'is-danger' : 'is-orange'"
                                >
                                    {{ teamCalendarStats.issue_day_count || 0 }}
                                </span>
                                <i
                                    :class="mobileLeaderPanelState.calendar ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"
                                ></i>
                            </div>
                        </button>

                        <div v-else class="team-calendar-panel__head">
                            <div>
                                <span class="section-label">Kalender Cutoff Tim</span>
                                <h2>Pantauan Harian Tim</h2>
                            </div>

                            <small class="team-calendar-panel__meta">
                                {{ teamCalendarMonthMeta.cutoff_label || '-' }}
                            </small>
                        </div>

                        <transition
                            name="accordion"
                            @enter="onAccordionEnter"
                            @after-enter="onAccordionAfterEnter"
                            @leave="onAccordionLeave"
                        >
                            <div
                                v-show="!isMobileViewport || mobileLeaderPanelState.calendar"
                                class="mobile-accordion__body"
                                style="min-width: 0"
                            >
                                <template v-if="teamCalendarMonthLoading">
                                    <div class="team-calendar-grid">
                                        <div
                                            v-for="n in 14"
                                            :key="`calendar-skeleton-${n}`"
                                            class="team-calendar-day is-skeleton"
                                            style="cursor: default"
                                        >
                                            <div class="team-calendar-day__head">
                                                <div style="flex: 1">
                                                    <div
                                                        class="leader-skeleton-line is-title mb-1"
                                                        style="width: 40%"
                                                    ></div>
                                                    <div class="leader-skeleton-line is-short" style="width: 60%"></div>
                                                </div>
                                                <div
                                                    class="skeleton-avatar"
                                                    style="width: 2rem; height: 1.2rem; border-radius: 4px"
                                                ></div>
                                            </div>
                                            <div
                                                class="team-calendar-day__stats"
                                                style="display: flex; gap: 0.5rem; border: none; padding-top: 0.5rem"
                                            >
                                                <div class="leader-skeleton-line is-short" style="width: 40%"></div>
                                                <div class="leader-skeleton-line is-short" style="width: 40%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

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
                            </div>
                        </transition>
                    </section>
                </main>

                <!-- SIDEBAR -->
                <aside class="leader-side">
                    <section
                        ref="actionCenterSectionRef"
                        class="side-card action-center-card"
                        data-tour="leader-action-center"
                        :class="{ 'mobile-accordion-card': isMobileViewport }"
                    >
                        <button
                            v-if="isMobileViewport"
                            type="button"
                            class="mobile-accordion__trigger"
                            @click.stop="toggleMobileLeaderPanel('action')"
                        >
                            <div class="module-title">
                                <div class="module-icon" :class="actionItemCount > 0 ? 'is-danger' : 'is-orange'">
                                    <i class="bi bi-bell-fill"></i>
                                </div>
                                <div class="module-title-copy">
                                    <span class="section-label">Action Center</span>
                                    <h2>Perlu Ditindak</h2>
                                </div>
                            </div>
                            <div class="mobile-accordion__meta">
                                <span class="side-count" :class="{ 'is-danger': actionItemCount > 0 }">{{
                                    actionItemCount
                                }}</span>
                                <i
                                    :class="mobileLeaderPanelState.action ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"
                                ></i>
                            </div>
                        </button>
                        <div v-else class="side-card__head">
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

                        <transition
                            name="accordion"
                            @enter="onAccordionEnter"
                            @after-enter="onAccordionAfterEnter"
                            @leave="onAccordionLeave"
                        >
                            <div
                                v-show="!isMobileViewport || mobileLeaderPanelState.action"
                                class="mobile-accordion__body"
                            >
                                <div class="action-list is-scrollable">
                                    <template v-if="needsActionLoading">
                                        <div
                                            v-for="n in 3"
                                            :key="`action-skeleton-${n}`"
                                            class="action-card is-skeleton"
                                        >
                                            <div
                                                class="action-card__icon skeleton-avatar"
                                                style="border-radius: 0.62rem"
                                            ></div>
                                            <div class="action-card__body">
                                                <div
                                                    class="leader-skeleton-line is-title mb-1"
                                                    style="width: 60%"
                                                ></div>
                                                <div class="leader-skeleton-line" style="width: 80%"></div>
                                                <div class="leader-skeleton-line is-short mt-1"></div>
                                            </div>
                                        </div>
                                    </template>

                                    <EmptyState
                                        v-else-if="needsActionError"
                                        compact
                                        icon="bi bi-exclamation-triangle"
                                        title="Gagal memuat action center"
                                        :description="needsActionError"
                                    />

                                    <template v-else>
                                        <div
                                            v-for="action in sortedActionItems"
                                            :key="action.id"
                                            role="button"
                                            tabindex="0"
                                            class="action-card"
                                            :class="[
                                                `is-${action.tone}`,
                                                {
                                                    'is-pending':
                                                        needsActionNavigationLoading &&
                                                        actionNavigationTargetId &&
                                                        actionNavigationTargetId === action.id,
                                                    'is-disabled': needsActionNavigationLoading,
                                                    'is-read': isActionRead(action),
                                                },
                                            ]"
                                            @click="openAction(action)"
                                            @keydown.enter.prevent="openAction(action)"
                                            @keydown.space.prevent="openAction(action)"
                                        >
                                            <div class="action-card__icon">
                                                <i :class="action.icon"></i>
                                            </div>

                                            <div class="action-card__body">
                                                <strong>{{ actionCardTitle(action) }}</strong>
                                                <span>{{ actionCardDescription(action) }}</span>
                                                <div
                                                    v-if="
                                                        action.category === 'hcis' &&
                                                        (action.caseBreakdown.no_checkin > 0 ||
                                                            action.caseBreakdown.no_checkout > 0 ||
                                                            action.caseBreakdown.late > 0 ||
                                                            action.caseBreakdown.absent > 0 ||
                                                            action.caseBreakdown.no_data > 0)
                                                    "
                                                    class="action-case-row"
                                                >
                                                    <span
                                                        v-if="action.caseBreakdown.no_checkin > 0"
                                                        class="action-case-chip is-danger"
                                                    >
                                                        Tidak check-in: {{ action.caseBreakdown.no_checkin }}
                                                    </span>
                                                    <span
                                                        v-if="action.caseBreakdown.no_checkout > 0"
                                                        class="action-case-chip is-warning"
                                                    >
                                                        Belum check-out: {{ action.caseBreakdown.no_checkout }}
                                                    </span>
                                                    <span
                                                        v-if="action.caseBreakdown.late > 0"
                                                        class="action-case-chip is-orange"
                                                    >
                                                        Terlambat: {{ action.caseBreakdown.late }}
                                                    </span>
                                                    <span
                                                        v-if="action.caseBreakdown.absent > 0"
                                                        class="action-case-chip is-danger"
                                                    >
                                                        Tidak hadir: {{ action.caseBreakdown.absent }}
                                                    </span>
                                                    <span
                                                        v-if="action.caseBreakdown.no_data > 0"
                                                        class="action-case-chip is-info"
                                                    >
                                                        Data absen belum masuk: {{ action.caseBreakdown.no_data }}
                                                    </span>
                                                </div>
                                                <small
                                                    v-if="action.category === 'hcis' && action.caseDates.length"
                                                    class="action-case-dates"
                                                >
                                                    {{ actionCaseDatesLabel(action) }}
                                                </small>
                                                <!-- <small>{{ actionMetaLabel(action) }}</small> -->
                                            </div>

                                            <div class="action-card__actions">
                                                <i
                                                    v-if="
                                                        needsActionNavigationLoading &&
                                                        actionNavigationTargetId &&
                                                        actionNavigationTargetId === action.id
                                                    "
                                                    class="bi bi-arrow-repeat action-loading-spin"
                                                ></i>
                                                <i v-else class="bi bi-arrow-right-short"></i>
                                            </div>
                                        </div>

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
                            </div>
                        </transition>
                    </section>

                    <section
                        ref="approvalsSectionRef"
                        class="side-card approval-side-card"
                        data-tour="leader-approval-queue"
                        :class="{ 'mobile-accordion-card': isMobileViewport }"
                        @click.capture="ensureApprovalsLoaded"
                    >
                        <button
                            v-if="isMobileViewport"
                            type="button"
                            class="mobile-accordion__trigger"
                            @click.stop="toggleMobileLeaderPanel('approval')"
                        >
                            <div class="module-title">
                                <div class="module-icon is-emerald"><i class="bi bi-check2-circle"></i></div>
                                <div class="module-title-copy">
                                    <span class="section-label">Approval</span>
                                    <h2>Antrian Approval</h2>
                                </div>
                            </div>
                            <div class="mobile-accordion__meta">
                                <span class="side-count is-success">{{ approvalItemCount }}</span>
                                <i
                                    :class="mobileLeaderPanelState.approval ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"
                                ></i>
                            </div>
                        </button>
                        <div v-else class="side-card__head">
                            <div>
                                <span class="section-label">Approval</span>
                                <h2>Antrian Approval</h2>
                            </div>

                            <span class="side-count is-success">{{ approvalItemCount }}</span>
                        </div>

                        <transition
                            name="accordion"
                            @enter="onAccordionEnter"
                            @after-enter="onAccordionAfterEnter"
                            @leave="onAccordionLeave"
                        >
                            <div
                                v-show="!isMobileViewport || mobileLeaderPanelState.approval"
                                class="mobile-accordion__body"
                            >
                                <div class="approval-side-list">
                                    <div v-if="approvalSectionError" class="drawer-error-box is-compact">
                                        <strong>{{ approvalSectionError }}</strong>
                                        <button type="button" class="pagination-btn" @click.stop="loadApprovals(1)">
                                            Coba lagi
                                        </button>
                                    </div>
                                    <template v-if="approvalSectionLoading">
                                        <div
                                            v-for="n in 3"
                                            :key="`approval-skeleton-${n}`"
                                            class="approval-side-item is-skeleton"
                                        >
                                            <div style="text-align: start; flex: 1">
                                                <div
                                                    class="leader-skeleton-line is-title mb-1"
                                                    style="width: 60%"
                                                ></div>
                                                <div class="leader-skeleton-line" style="width: 80%"></div>
                                                <div class="leader-skeleton-line is-short mt-1"></div>
                                            </div>
                                            <div
                                                class="skeleton-avatar"
                                                style="width: 3.5rem; height: 1.25rem; border-radius: 99px"
                                            ></div>
                                        </div>
                                    </template>

                                    <button
                                        v-for="item in approvalItems"
                                        :key="item.id"
                                        type="button"
                                        class="approval-side-item"
                                        @click="openApprovalQueue(item.url, item.id)"
                                    >
                                        <div style="text-align: start">
                                            <strong>{{ item.memberName }}</strong>
                                            <span>{{ item.title }}</span>
                                            <span class="approval-side-item__meta">
                                                {{ item.meta || '-' }}
                                            </span>
                                            <span v-if="item.expired_at" class="approval-side-item__expiry">
                                                Batas Approve: {{ formatDateId(item.expired_at) }}
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

                                    <button
                                        v-if="!approvalSectionLoading && !approvalSectionLoaded"
                                        type="button"
                                        class="module-reveal module-reveal--compact module-reveal--emerald"
                                        @click.stop="ensureApprovalsLoaded"
                                    >
                                        <span class="module-reveal__aura"></span>
                                        <span class="module-reveal__icon"><i class="bi bi-check2-square"></i></span>
                                        <span class="module-reveal__title">Lihat Antrian Approval</span>
                                        <span class="module-reveal__desc">
                                            Daftar pengajuan tim yang menunggu keputusan Anda.
                                        </span>
                                        <span class="module-reveal__cta">
                                            <i class="bi bi-lightning-charge-fill"></i>
                                            <span>Muat data</span>
                                        </span>
                                    </button>

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
                            </div>
                        </transition>
                    </section>

                    <section
                        ref="trainingSectionRef"
                        class="side-card learning-side-card"
                        data-tour="leader-training-pending"
                        :class="{ 'mobile-accordion-card': isMobileViewport }"
                        @click.capture="ensureTrainingLoaded"
                    >
                        <button
                            v-if="isMobileViewport"
                            type="button"
                            class="mobile-accordion__trigger"
                            @click.stop="toggleMobileLeaderPanel('training')"
                        >
                            <div class="module-title">
                                <div class="module-icon is-teal"><i class="bi bi-book-half"></i></div>
                                <div class="module-title-copy">
                                    <span class="section-label">Learning</span>
                                    <h2>Training Pending</h2>
                                </div>
                            </div>
                            <div class="mobile-accordion__meta">
                                <span class="side-count is-teal">{{ trainingPendingCount }}</span>
                                <i
                                    :class="mobileLeaderPanelState.training ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"
                                ></i>
                            </div>
                        </button>
                        <div v-else class="side-card__head">
                            <div>
                                <span class="section-label">Learning</span>
                                <h2>Training Pending</h2>
                            </div>

                            <span class="side-count is-teal">{{ trainingPendingCount }}</span>
                        </div>

                        <transition
                            name="accordion"
                            @enter="onAccordionEnter"
                            @after-enter="onAccordionAfterEnter"
                            @leave="onAccordionLeave"
                        >
                            <div
                                v-show="!isMobileViewport || mobileLeaderPanelState.training"
                                class="mobile-accordion__body"
                            >
                                <div class="training-pending-list">
                                    <div v-if="trainingSectionError" class="drawer-error-box is-compact">
                                        <strong>{{ trainingSectionError }}</strong>
                                        <button
                                            type="button"
                                            class="pagination-btn"
                                            @click.stop="loadTrainingPending(1)"
                                        >
                                            Coba lagi
                                        </button>
                                    </div>
                                    <template v-if="trainingSectionLoading">
                                        <div
                                            v-for="n in 3"
                                            :key="`training-skeleton-${n}`"
                                            class="training-pending-item is-skeleton"
                                        >
                                            <div style="flex: 1">
                                                <div
                                                    class="leader-skeleton-line is-title mb-1"
                                                    style="width: 70%"
                                                ></div>
                                                <div class="leader-skeleton-line is-short" style="width: 40%"></div>
                                            </div>
                                            <div
                                                class="skeleton-avatar"
                                                style="width: 2rem; height: 1.25rem; border-radius: 4px"
                                            ></div>
                                        </div>
                                    </template>

                                    <div
                                        v-for="item in trainingPendingMembers"
                                        :key="item.id"
                                        class="training-pending-item"
                                    >
                                        <div>
                                            <strong>{{ item.name }}</strong>
                                            <span>{{ item.lms.completed }}/{{ item.lms.total }} selesai</span>
                                        </div>

                                        <small>{{ item.lms.progress }}%</small>
                                    </div>

                                    <EmptyState
                                        v-if="
                                            !trainingSectionLoading &&
                                            trainingSectionLoaded &&
                                            !trainingPendingMembers.length
                                        "
                                        compact
                                        icon="bi bi-check2-circle"
                                        title="LMS aman"
                                        description="Tidak ada training pending pada periode ini."
                                    />

                                    <button
                                        v-if="!trainingSectionLoading && !trainingSectionLoaded"
                                        type="button"
                                        class="module-reveal module-reveal--compact module-reveal--teal"
                                        @click.stop="ensureTrainingLoaded"
                                    >
                                        <span class="module-reveal__aura"></span>
                                        <span class="module-reveal__icon"><i class="bi bi-journal-bookmark"></i></span>
                                        <span class="module-reveal__title">Lihat Training Pending</span>
                                        <span class="module-reveal__desc">
                                            Daftar anggota dengan training yang belum selesai.
                                        </span>
                                        <span class="module-reveal__cta">
                                            <i class="bi bi-lightning-charge-fill"></i>
                                            <span>Muat data</span>
                                        </span>
                                    </button>
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
                            </div>
                        </transition>
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

            <aside
                class="attendance-issue-drawer"
                :class="{ 'is-open': teamCalendarDayDetailDrawerOpen }"
                role="dialog"
                aria-modal="true"
                aria-label="Detail Kalender Tim"
                tabindex="-1"
                @keydown.esc.prevent="closeTeamCalendarDayDetailDrawer"
                @keydown.tab="trapFocusWithin"
            >
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
                    <template v-if="teamCalendarDayDetailLoading">
                        <article
                            v-for="n in 3"
                            :key="`timeline-skeleton-${n}`"
                            class="attendance-issue-card is-skeleton"
                        >
                            <div class="attendance-issue-card__head">
                                <div style="flex: 1">
                                    <div class="leader-skeleton-line is-title mb-1"></div>
                                    <div class="leader-skeleton-line is-short" style="width: 60%"></div>
                                </div>
                                <div
                                    class="skeleton-avatar"
                                    style="width: 4rem; height: 1rem; border-radius: 4px"
                                ></div>
                            </div>
                            <div class="attendance-issue-card__meta">
                                <div class="leader-skeleton-line is-short"></div>
                                <div class="leader-skeleton-line is-short" style="width: 30%"></div>
                                <div class="leader-skeleton-line is-short" style="width: 30%"></div>
                            </div>
                        </article>
                    </template>

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
                            class="attendance-issue-card is-clickable-member"
                            role="button"
                            tabindex="0"
                            @click="openMemberFromDrawer(item, 'calendar-day-detail')"
                            @keydown.enter.prevent="openMemberFromDrawer(item, 'calendar-day-detail')"
                            @keydown.space.prevent="openMemberFromDrawer(item, 'calendar-day-detail')"
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

            <!-- DRAWER A — Breakdown kategori HCIS (periode cutoff berjalan) -->
            <transition name="drawer-fade">
                <div
                    v-if="hcisPeriodDrawerOpen"
                    class="attendance-issue-drawer-backdrop"
                    @click="closeHcisPeriodDrawer"
                ></div>
            </transition>

            <aside
                class="attendance-issue-drawer"
                :class="{ 'is-open': hcisPeriodDrawerOpen }"
                role="dialog"
                aria-modal="true"
                aria-label="Detail Kategori HCIS"
                tabindex="-1"
                @keydown.esc.prevent="closeHcisPeriodDrawer"
                @keydown.tab="trapFocusWithin"
            >
                <div class="attendance-issue-drawer__head">
                    <div>
                        <span class="section-label">Detail HCIS — Periode Berjalan</span>
                        <h3>{{ hcisPeriodCategoryLabel }}</h3>
                        <small>
                            {{ hcisPeriodMeta.cutoff_label || '-' }} · {{ hcisPeriodMeta.total_members || 0 }} anggota ·
                            {{ hcisPeriodMeta.total_days || 0 }} hari
                        </small>
                    </div>

                    <button type="button" class="drawer-close-btn" @click="closeHcisPeriodDrawer">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="attendance-issue-drawer__body">
                    <template v-if="hcisPeriodLoading">
                        <article
                            v-for="n in 3"
                            :key="`hcis-period-skeleton-${n}`"
                            class="attendance-issue-card is-skeleton"
                        >
                            <div class="attendance-issue-card__head">
                                <div style="flex: 1">
                                    <div class="leader-skeleton-line is-title mb-1"></div>
                                    <div class="leader-skeleton-line is-short" style="width: 60%"></div>
                                </div>
                                <div
                                    class="skeleton-avatar"
                                    style="width: 4rem; height: 1rem; border-radius: 4px"
                                ></div>
                            </div>
                            <div class="attendance-issue-card__meta">
                                <div class="leader-skeleton-line is-short"></div>
                            </div>
                        </article>
                    </template>

                    <div v-else-if="hcisPeriodError" class="drawer-error-box">
                        <strong>{{ hcisPeriodError }}</strong>
                        <button type="button" class="pagination-btn" @click="retryHcisPeriodLoad">Coba lagi</button>
                    </div>

                    <template v-else-if="hcisPeriodRows.length">
                        <article
                            v-for="item in hcisPeriodRows"
                            :key="`hcis-period-${item.id}`"
                            class="attendance-issue-card"
                        >
                            <div
                                class="attendance-issue-card__head is-clickable-member"
                                role="button"
                                tabindex="0"
                                @click="toggleHcisMemberAccordion(item, 'period')"
                                @keydown.enter.prevent="toggleHcisMemberAccordion(item, 'period')"
                                @keydown.space.prevent="toggleHcisMemberAccordion(item, 'period')"
                            >
                                <div>
                                    <strong>{{ item.name }}</strong>
                                    <span>{{ item.position || '-' }} · {{ item.department || '-' }}</span>
                                </div>
                                <span class="attendance-issue-pill" :class="`is-${hcisCategoryTone}`">
                                    {{ item.day_count || 0 }} hari
                                    <i
                                        class="bi"
                                        :class="hcisAccordionOpenKey === item.id ? 'bi-chevron-up' : 'bi-chevron-down'"
                                    ></i>
                                </span>
                            </div>

                            <div class="attendance-issue-card__meta">
                                <span>{{ summarizeDates(item.dates) }}</span>
                            </div>

                            <!-- Accordion: detail tanggal (lazy fetch saat dibuka) -->
                            <div v-if="hcisAccordionOpenKey === item.id" class="hcis-accordion-body">
                                <template v-if="hcisAccordionLoading">
                                    <div class="leader-skeleton-line is-short mb-1" style="width: 80%"></div>
                                    <div class="leader-skeleton-line is-short mb-1" style="width: 70%"></div>
                                    <div class="leader-skeleton-line is-short" style="width: 75%"></div>
                                </template>
                                <div v-else-if="hcisAccordionError" class="drawer-error-box is-compact">
                                    <strong>{{ hcisAccordionError }}</strong>
                                    <button type="button" class="pagination-btn" @click="reloadHcisAccordion">
                                        Coba lagi
                                    </button>
                                </div>
                                <ul v-else-if="hcisAccordionRows.length" class="hcis-accordion-list">
                                    <li v-for="d in hcisAccordionRows" :key="`acc-${item.id}-${d.date}`">
                                        <div class="hcis-accordion-list__row">
                                            <strong>{{ d.day_label }}</strong>
                                            <span class="attendance-issue-pill" :class="`is-${d.tone || 'default'}`">
                                                {{ d.label }}
                                                <small v-if="d.status === 'late' && Number(d.late_minutes) > 0">
                                                    {{ d.late_minutes }} mnt
                                                </small>
                                                <small v-else-if="Number(d.early_minutes) > 0">
                                                    pulang cepat {{ d.early_minutes }} mnt
                                                </small>
                                            </span>
                                        </div>
                                        <div class="hcis-accordion-list__meta">
                                            <span>Shift: {{ d.shift_name || '-' }} ({{ d.shift_time || '-' }})</span>
                                            <span>CI: {{ d.check_in || '-' }}</span>
                                            <span>CO: {{ d.check_out || '-' }}</span>
                                        </div>
                                    </li>
                                </ul>
                                <p v-else class="hcis-accordion-empty">Tidak ada detail tanggal.</p>
                            </div>
                        </article>
                    </template>

                    <EmptyState
                        v-else-if="hcisPeriodDrawerOpen"
                        compact
                        icon="bi bi-check2-circle"
                        title="Tidak ada data"
                        description="Tidak ada anggota pada kategori ini di periode berjalan."
                    />
                </div>

                <div v-if="hcisPeriodDrawerOpen" class="attendance-issue-drawer__foot">
                    <button
                        type="button"
                        class="pagination-btn"
                        :disabled="hcisPeriodLoading || !hcisPeriodPagination.has_prev"
                        @click="goToHcisPeriodPage(-1)"
                    >
                        Prev
                    </button>
                    <span>Halaman {{ hcisPeriodPagination.current_page }} / {{ hcisPeriodPagination.last_page }}</span>
                    <button
                        type="button"
                        class="pagination-btn"
                        :disabled="hcisPeriodLoading || !hcisPeriodPagination.has_next"
                        @click="goToHcisPeriodPage(1)"
                    >
                        Next
                    </button>
                </div>
            </aside>

            <!-- DRAWER B — Breakdown status hari ini -->
            <transition name="drawer-fade">
                <div
                    v-if="hcisTodayDrawerOpen"
                    class="attendance-issue-drawer-backdrop"
                    @click="closeHcisTodayDrawer"
                ></div>
            </transition>

            <aside
                class="attendance-issue-drawer"
                :class="{ 'is-open': hcisTodayDrawerOpen }"
                role="dialog"
                aria-modal="true"
                aria-label="Detail Status Hari Ini"
                tabindex="-1"
                @keydown.esc.prevent="closeHcisTodayDrawer"
                @keydown.tab="trapFocusWithin"
            >
                <div class="attendance-issue-drawer__head">
                    <div>
                        <span class="section-label">Detail Status Hari Ini</span>
                        <h3>{{ hcisTodayMeta.day_label || 'Hari Ini' }}</h3>
                        <small>{{ hcisTodayData.working_total || 0 }} anggota bertugas hari ini</small>
                    </div>

                    <button type="button" class="drawer-close-btn" @click="closeHcisTodayDrawer">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="attendance-issue-drawer__body">
                    <template v-if="hcisTodayLoading">
                        <div
                            v-for="n in 5"
                            :key="`hcis-today-skeleton-${n}`"
                            class="attendance-issue-card is-skeleton"
                            style="margin-bottom: 0.5rem"
                        >
                            <div class="leader-skeleton-line is-short mb-1" style="width: 50%"></div>
                            <div class="leader-skeleton-line is-title" style="width: 30%"></div>
                        </div>
                    </template>

                    <div v-else-if="hcisTodayError" class="drawer-error-box">
                        <strong>{{ hcisTodayError }}</strong>
                        <button type="button" class="pagination-btn" @click="retryHcisTodayLoad">Coba lagi</button>
                    </div>

                    <template v-else>
                        <article
                            v-for="stat in hcisTodayStats"
                            :key="`hcis-today-stat-${stat.key}`"
                            class="attendance-issue-card hcis-today-stat"
                        >
                            <div
                                class="attendance-issue-card__head is-clickable-member"
                                role="button"
                                tabindex="0"
                                @click="toggleHcisTodayStat(stat)"
                                @keydown.enter.prevent="toggleHcisTodayStat(stat)"
                                @keydown.space.prevent="toggleHcisTodayStat(stat)"
                            >
                                <div>
                                    <strong>{{ stat.label }}</strong>
                                    <span>{{ stat.caption }}</span>
                                </div>
                                <span class="attendance-issue-pill" :class="`is-${stat.tone}`">
                                    {{ stat.displayValue }}
                                    <i
                                        v-if="stat.statusKey"
                                        class="bi"
                                        :class="hcisTodayOpenKey === stat.key ? 'bi-chevron-up' : 'bi-chevron-down'"
                                    ></i>
                                </span>
                            </div>

                            <!-- Semua stat bisa di-expand jadi list anggota (status hari ini) -->
                            <div v-if="stat.statusKey && hcisTodayOpenKey === stat.key" class="hcis-accordion-body">
                                <template v-if="hcisTodayMembersLoading">
                                    <div class="leader-skeleton-line is-short mb-1" style="width: 80%"></div>
                                    <div class="leader-skeleton-line is-short" style="width: 70%"></div>
                                </template>
                                <div v-else-if="hcisTodayMembersError" class="drawer-error-box is-compact">
                                    <strong>{{ hcisTodayMembersError }}</strong>
                                    <button type="button" class="pagination-btn" @click="reloadHcisTodayMembers">
                                        Coba lagi
                                    </button>
                                </div>
                                <ul v-else-if="hcisTodayMemberRows.length" class="hcis-accordion-list">
                                    <li v-for="m in hcisTodayMemberRows" :key="`today-m-${stat.key}-${m.id}`">
                                        <div class="hcis-accordion-list__row">
                                            <strong>{{ m.name }}</strong>
                                            <span class="attendance-issue-pill" :class="`is-${m.tone || 'default'}`">
                                                {{ m.label }}
                                                <small v-if="m.status === 'late' && Number(m.late_minutes) > 0">
                                                    {{ m.late_minutes }} mnt
                                                </small>
                                                <small v-else-if="Number(m.early_minutes) > 0">
                                                    pulang cepat {{ m.early_minutes }} mnt
                                                </small>
                                            </span>
                                        </div>
                                        <div class="hcis-accordion-list__meta">
                                            <span>{{ m.position || '-' }}</span>
                                            <span>Shift: {{ m.shift_name || '-' }} ({{ m.shift_time || '-' }})</span>
                                            <span>CI: {{ m.check_in || '-' }}</span>
                                            <span>CO: {{ m.check_out || '-' }}</span>
                                        </div>
                                    </li>
                                </ul>
                                <p v-else class="hcis-accordion-empty">Tidak ada anggota pada status ini.</p>
                            </div>
                        </article>
                    </template>
                </div>
            </aside>

            <transition name="drawer-fade">
                <div
                    v-if="attendanceIssueDrawerOpen"
                    class="attendance-issue-drawer-backdrop"
                    @click="closeAttendanceIssueDrawer"
                ></div>
            </transition>

            <aside
                class="attendance-issue-drawer"
                :class="{ 'is-open': attendanceIssueDrawerOpen }"
                role="dialog"
                aria-modal="true"
                aria-label="Detail Issue Absensi"
                tabindex="-1"
                @keydown.esc.prevent="closeAttendanceIssueDrawer"
                @keydown.tab="trapFocusWithin"
            >
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
                    <template v-if="attendanceIssueLoading">
                        <article v-for="n in 3" :key="`issue-skeleton-${n}`" class="attendance-issue-card is-skeleton">
                            <div class="attendance-issue-card__head">
                                <div style="flex: 1">
                                    <div class="leader-skeleton-line is-title mb-1"></div>
                                    <div class="leader-skeleton-line is-short" style="width: 60%"></div>
                                </div>
                                <div
                                    class="skeleton-avatar"
                                    style="width: 4rem; height: 1rem; border-radius: 4px"
                                ></div>
                            </div>
                            <div class="attendance-issue-card__meta">
                                <div class="leader-skeleton-line is-short"></div>
                                <div class="leader-skeleton-line is-short" style="width: 30%"></div>
                            </div>
                        </article>
                    </template>

                    <div v-else-if="attendanceIssueError" class="drawer-error-box">
                        <strong>{{ attendanceIssueError }}</strong>
                        <button type="button" class="pagination-btn" @click="retryAttendanceIssueLoad">
                            Coba lagi
                        </button>
                    </div>

                    <template v-else-if="attendanceIssueRows.length">
                        <article
                            v-for="item in attendanceIssueRows"
                            :key="item.id"
                            class="attendance-issue-card is-clickable-member"
                            role="button"
                            tabindex="0"
                            @click="openMemberFromDrawer(item, 'attendance-issue')"
                            @keydown.enter.prevent="openMemberFromDrawer(item, 'attendance-issue')"
                            @keydown.space.prevent="openMemberFromDrawer(item, 'attendance-issue')"
                        >
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
                                <button
                                    v-for="issue in item.issues"
                                    :key="`${item.id}-${issue.key}`"
                                    type="button"
                                    class="attendance-issue-pill"
                                    :class="`is-${issue.tone || 'warning'}`"
                                    @click.stop="handleIssuePillClick(item)"
                                >
                                    {{ issue.label }}
                                    <small v-if="issue.meta">{{ issue.meta }}</small>
                                </button>
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

            <aside
                class="attendance-issue-drawer"
                :class="{ 'is-open': attendanceStatusDrawerOpen }"
                role="dialog"
                aria-modal="true"
                aria-label="Detail Status Absensi"
                tabindex="-1"
                @keydown.esc.prevent="closeAttendanceStatusDrawer"
                @keydown.tab="trapFocusWithin"
            >
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
                                class="attendance-issue-card is-clickable-member"
                                role="button"
                                tabindex="0"
                                @click="openMemberFromDrawer(item, 'attendance-status')"
                                @keydown.enter.prevent="openMemberFromDrawer(item, 'attendance-status')"
                                @keydown.space.prevent="openMemberFromDrawer(item, 'attendance-status')"
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

            <aside
                class="attendance-issue-drawer"
                :class="{ 'is-open': kpiDetailDrawerOpen }"
                role="dialog"
                aria-modal="true"
                aria-label="Detail KPI Team"
                tabindex="-1"
                @keydown.esc.prevent="closeKpiDetailDrawer"
                @keydown.tab="trapFocusWithin"
            >
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
                        <template v-if="kpiDetailLoading">
                            <article v-for="n in 3" :key="`kpi-skeleton-${n}`" class="kpi-member-card is-skeleton">
                                <div class="kpi-member-card__head">
                                    <div class="member-profile">
                                        <div class="member-avatar skeleton-avatar"></div>
                                        <div style="flex: 1; min-width: 0">
                                            <div class="leader-skeleton-line is-title mb-1"></div>
                                            <div class="leader-skeleton-line is-short"></div>
                                        </div>
                                    </div>
                                    <div
                                        class="skeleton-avatar"
                                        style="width: 3rem; height: 1.5rem; border-radius: 0.5rem"
                                    ></div>
                                </div>
                            </article>
                        </template>
                        <div v-else-if="kpiDetailError" class="drawer-error-box">
                            <strong>{{ kpiDetailError }}</strong>
                            <button type="button" class="pagination-btn" @click="retryKpiDetailLoad">Coba lagi</button>
                        </div>
                        <template v-else-if="kpiDetailRows.length">
                            <article v-for="item in kpiDetailRows" :key="item.id" class="attendance-issue-card">
                                <template v-if="kpiDetailContext === 'approval_queue'">
                                    <a
                                        :href="item.url || '/kpi-approval'"
                                        class="kpi-member-card-btn"
                                        style="text-decoration: none; display: block"
                                    >
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
                                        </div>
                                        <div class="attendance-issue-pill-wrap">
                                            <span
                                                class="attendance-issue-pill"
                                                :class="item.queue_type === 'performance_kpi' ? 'is-purple' : 'is-info'"
                                            >
                                                {{ item.queue_type_label || 'Approval KPI' }}
                                            </span>
                                            <span class="attendance-issue-pill is-warning">Menunggu approval</span>
                                        </div>
                                    </a>
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
                                            <span class="attendance-issue-pill" :class="getParameterStatusTone(item)">
                                                Parameter: {{ getParameterStatusLabel(item) }}
                                            </span>
                                            <span class="attendance-issue-pill" :class="getPerformanceStatusTone(item)">
                                                Performance: {{ getPerformanceStatusLabel(item)
                                                }}{{
                                                    item.cutoff_performance_scheduled || item.performanceScheduled
                                                        ? ' (' +
                                                          formatNumber(
                                                              item.monthly_score_current_cutoff ||
                                                                  item.monthly_score ||
                                                                  0,
                                                              '%',
                                                          ) +
                                                          ')'
                                                        : ''
                                                }}
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
                                            <template v-if="isKpiMemberDetailLoading(item.id)">
                                                <div
                                                    class="kpi-member-detail-block is-skeleton"
                                                    style="padding: 1rem; border-top: 1px solid var(--border-color)"
                                                >
                                                    <div
                                                        class="leader-skeleton-line is-title mb-2"
                                                        style="width: 40%"
                                                    ></div>
                                                    <div class="kpi-member-detail-list">
                                                        <div
                                                            v-for="n in 3"
                                                            :key="`kpi-detail-item-skeleton-${n}`"
                                                            class="kpi-member-detail-item"
                                                            style="display: flex; gap: 1rem; margin-bottom: 0.5rem"
                                                        >
                                                            <div style="flex: 1">
                                                                <div
                                                                    class="leader-skeleton-line is-title mb-1"
                                                                    style="width: 80%"
                                                                ></div>
                                                                <div class="leader-skeleton-line is-short"></div>
                                                            </div>
                                                            <div
                                                                class="skeleton-avatar"
                                                                style="
                                                                    width: 2.5rem;
                                                                    height: 1.25rem;
                                                                    border-radius: 4px;
                                                                "
                                                            ></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
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

                                                <!-- KPI Status Panel (mirrors Personal Dashboard) -->
                                                <div class="kpi-status-panel kpi-member-status-panel">
                                                    <div class="kpi-status-panel__item">
                                                        <span>KPI Parameter</span>
                                                        <strong
                                                            :class="
                                                                getParameterStatusTone(
                                                                    kpiMemberDetailFor(item.id)?.summary || item,
                                                                )
                                                            "
                                                            >{{
                                                                getParameterStatusLabel(
                                                                    kpiMemberDetailFor(item.id)?.summary || item,
                                                                )
                                                            }}</strong
                                                        >
                                                    </div>
                                                    <div class="kpi-status-panel__item">
                                                        <span>Update Performance</span>
                                                        <strong
                                                            :class="
                                                                getPerformanceStatusTone(
                                                                    kpiMemberDetailFor(item.id)?.summary || item,
                                                                )
                                                            "
                                                            >{{
                                                                getPerformanceStatusLabel(
                                                                    kpiMemberDetailFor(item.id)?.summary || item,
                                                                )
                                                            }}</strong
                                                        >
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

                                                <!-- Parameter KPI list — personal dashboard style -->
                                                <section class="kpi-member-detail-block">
                                                    <header>
                                                        <strong><i class="bi bi-list-check"></i> Parameter KPI</strong>
                                                        <span
                                                            >{{
                                                                kpiMemberDetailFor(item.id)?.parameter_rows?.length || 0
                                                            }}
                                                            parameter</span
                                                        >
                                                    </header>
                                                    <div
                                                        v-if="kpiMemberDetailFor(item.id)?.parameter_rows?.length"
                                                        class="kpi-member-param-list"
                                                    >
                                                        <span
                                                            v-for="(param, index) in kpiMemberDetailFor(item.id)
                                                                ?.parameter_rows || []"
                                                            :key="`${item.id}-param-${index}`"
                                                            :class="{
                                                                'not-inPeriode':
                                                                    param.status === 'Periode KPI belum aktif',
                                                            }"
                                                        >
                                                            <div>
                                                                <div class="kpi-param-name">
                                                                    <div
                                                                        class="kpi-param-dot"
                                                                        :class="
                                                                            Number(param.achievement || 0) >= 100
                                                                                ? 'is-excellent'
                                                                                : Number(param.achievement || 0) >= 70
                                                                                  ? 'is-good'
                                                                                  : Number(param.achievement || 0) > 0
                                                                                    ? 'is-low'
                                                                                    : 'is-idle'
                                                                        "
                                                                    ></div>
                                                                    <span>{{
                                                                        param.parameter || param.name || 'Parameter KPI'
                                                                    }}</span>
                                                                </div>
                                                                <strong>
                                                                    {{ formatNumber(param.achievement || 0, '%') }}
                                                                    <small v-if="param.bobot || param.weight">
                                                                        ({{
                                                                            formatNumber(
                                                                                param.bobot || param.weight || 0,
                                                                                '%',
                                                                            )
                                                                        }})</small
                                                                    >
                                                                </strong>
                                                            </div>
                                                            <small class="mini-parameter-meta">
                                                                Target {{ formatNumber(param.target || 0) }} · Actual
                                                                {{ formatNumber(param.actual || 0) }} ·
                                                                {{
                                                                    param.status ||
                                                                    param.statusLabel ||
                                                                    'Belum ada penilaian'
                                                                }}
                                                            </small>
                                                            <div class="mini-progress">
                                                                <div
                                                                    class="mini-progress__fill"
                                                                    :style="{
                                                                        width: `${Math.min(100, Math.max(0, Number(param.achievement || 0)))}%`,
                                                                        background: param.statusColor || '#7c3aed',
                                                                    }"
                                                                ></div>
                                                            </div>
                                                        </span>
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

            <aside
                class="attendance-issue-drawer lms-detail-drawer"
                :class="{ 'is-open': lmsDetailDrawerOpen }"
                role="dialog"
                aria-modal="true"
                aria-label="Detail LMS Team"
                tabindex="-1"
                @keydown.esc.prevent="closeLmsDetailDrawer"
                @keydown.tab="trapFocusWithin"
            >
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

                    <template v-if="lmsDetailLoading">
                        <div class="lms-detail-grid">
                            <article
                                v-for="n in 3"
                                :key="`lms-skeleton-${n}`"
                                class="lms-detail-member-card is-skeleton"
                            >
                                <div class="lms-detail-member-head">
                                    <div class="member-profile">
                                        <div class="member-avatar skeleton-avatar"></div>
                                        <div style="flex: 1; min-width: 0">
                                            <div class="leader-skeleton-line is-title mb-1"></div>
                                            <div class="leader-skeleton-line is-short"></div>
                                        </div>
                                    </div>
                                    <div
                                        class="skeleton-avatar"
                                        style="width: 1.5rem; height: 1.5rem; border-radius: 50%"
                                    ></div>
                                </div>
                            </article>
                        </div>
                    </template>

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
                                        Avg Durasi: {{ formatLearningDurationLabel(item.avg_duration_hours || 0) }}
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
                                    <template v-if="isLmsMemberDetailLoading(item.id)">
                                        <div class="lms-member-training-list is-skeleton mt-2">
                                            <div
                                                v-for="n in 2"
                                                :key="`lms-training-skeleton-${n}`"
                                                class="lms-member-training-item"
                                                style="
                                                    padding: 1rem;
                                                    border-radius: 0.5rem;
                                                    background: var(--surface-low);
                                                    margin-bottom: 0.5rem;
                                                "
                                            >
                                                <div style="flex: 1">
                                                    <div class="leader-skeleton-line is-title mb-1"></div>
                                                    <div class="leader-skeleton-line is-short"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

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

        <!-- Mark Done Confirmation Modal -->
        <ModalV2
            v-model:show="showMarkDoneConfirm"
            title="Konfirmasi Selesai"
            subtitle="Apakah Anda yakin telah menindaklanjuti kasus ini?"
            size="small"
            :show-footer="true"
            :show-cancel="true"
            :show-confirm="true"
            cancel-text="Batal"
            confirm-text="Tandai Selesai"
            @confirm="confirmMarkDone"
            @close="showMarkDoneConfirm = false"
        >
            <template #body>
                <div class="text-center" style="padding: 1rem 0">
                    <i class="bi bi-info-circle text-primary mb-3" style="font-size: 2.5rem"></i>
                    <p class="text-muted mb-0" style="font-size: 0.95rem; line-height: 1.5">
                        Item ini akan ditandai sebagai selesai dan dihapus dari daftar tindakan Anda.
                    </p>
                </div>
            </template>
        </ModalV2>

        <!-- Riwayat Absensi (raw checktime) Modal -->
        <ModalV2
            v-model:show="memberRiwayat.show"
            title="Riwayat Absensi"
            :subtitle="memberRiwayat.dateLabel || memberRiwayat.memberName || ''"
            size="small"
            :show-footer="true"
            :show-cancel="true"
            :show-confirm="false"
            cancel-text="Tutup"
            @close="closeMemberDayRiwayat"
        >
            <template #body>
                <div class="riwayat-absensi">
                    <div class="riwayat-absensi__meta" v-if="memberRiwayat.memberName">
                        <i class="bi bi-person-circle"></i>
                        <span>{{ memberRiwayat.memberName }}</span>
                    </div>

                    <!-- Loading -->
                    <div v-if="memberRiwayat.loading" class="riwayat-absensi__loading">
                        <div v-for="n in 3" :key="`riwayat-skel-${n}`" class="riwayat-absensi__skel">
                            <span class="riwayat-absensi__skel-dot"></span>
                            <span class="riwayat-absensi__skel-line"></span>
                        </div>
                    </div>

                    <!-- Error -->
                    <div v-else-if="memberRiwayat.error" class="riwayat-absensi__empty is-error">
                        <i class="bi bi-exclamation-octagon"></i>
                        <span>{{ memberRiwayat.error }}</span>
                    </div>

                    <!-- Empty -->
                    <div v-else-if="!memberRiwayat.logs.length" class="riwayat-absensi__empty">
                        <i class="bi bi-calendar-x"></i>
                        <span>Tidak ada scan absensi pada tanggal ini.</span>
                    </div>

                    <!-- Timeline -->
                    <ul v-else class="riwayat-absensi__timeline">
                        <li
                            v-for="(log, idx) in memberRiwayat.logs"
                            :key="`riwayat-log-${idx}`"
                            class="riwayat-absensi__item"
                            :class="`is-${log.type}`"
                        >
                            <span class="riwayat-absensi__icon">
                                <i v-if="log.type === 'checkin'" class="bi bi-box-arrow-in-right"></i>
                                <i v-else-if="log.type === 'checkout'" class="bi bi-box-arrow-right"></i>
                                <i v-else class="bi bi-fingerprint"></i>
                            </span>
                            <div class="riwayat-absensi__body">
                                <div class="riwayat-absensi__row-top">
                                    <strong class="riwayat-absensi__time">{{ log.time }}</strong>
                                    <span class="riwayat-absensi__tag" :class="`is-${log.type}`">{{
                                        log.type_label
                                    }}</span>
                                </div>
                                <span class="riwayat-absensi__machine">
                                    <i class="bi bi-hdd-network"></i> {{ log.machine }}
                                </span>
                            </div>
                        </li>
                    </ul>
                </div>
            </template>
        </ModalV2>
    </div>
</template>

<script setup>
const onAccordionEnter = (el) => {
    el.style.height = '0px';
    void el.offsetHeight; // reflow
    el.style.height = el.scrollHeight + 'px';
};
const onAccordionAfterEnter = (el) => {
    el.style.height = 'auto';
};
const onAccordionLeave = (el) => {
    el.style.height = getComputedStyle(el).height;
    void el.offsetHeight; // reflow
    el.style.height = '0px';
};
import { computed, defineComponent, h, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import VueApexCharts from 'vue3-apexcharts';
import ModalV2 from '@/components/legacy/modalV2-noir.vue';
import MobileLeaderHero from '@/components/Dashboard/MobileLeaderHero.vue';
import { useLeaderDashboardTour } from '@/composables/useLeaderDashboardTour';
import { tungguTourBolehOtomatis } from '@/composables/tourRegistry.js';
import { useRequestLifecycle } from '@/composables/useRequestLifecycle';
import { trapFocusWithin } from '@/utils/dialogFocus';

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
            label: 'Periode berjalan',
            kpiMonth: 'Bulan berjalan',
            cutoff_start: '',
            cutoff_end: '',
            cutoff_label: '',
        }),
    },
    todayLabel: { type: String, default: '' },
    useDemoData: { type: Boolean, default: false },
    tourProgress: { type: Object, default: null },
});

const tour = useLeaderDashboardTour(
    props.tourProgress,
    {
        component: 'homepage/LeaderDashboard',
        label: 'Tour Leader Dashboard',
        description: 'Pandu kamu mengenali area monitoring tim di Leader Dashboard.',
    },
    {
        beforeStep: (action, step) => {
            // Mobile accordion panels must be opened before the tour can
            // highlight the element inside. The `mobilePanel` property on
            // each step maps to a key in `mobileLeaderPanelState`.
            if (action === 'open-mobile-panel') {
                const panel = step?.mobilePanel;
                if (panel && isMobileViewport.value && mobileLeaderPanelState.value[panel] !== undefined) {
                    mobileLeaderPanelState.value[panel] = true;
                }
                return null;
            }

            if (action === 'load-team-members') {
                return ensureMembersLoaded();
            }

            if (action === 'load-team-calendar') {
                return loadTeamCalendarMonth();
            }

            if (action === 'load-approvals') {
                return loadApprovals();
            }

            if (action === 'load-heatmap') {
                return loadHeatmap();
            }

            if (action === 'load-training') {
                return loadTrainingPending();
            }

            if (action === 'open-first-team-member') {
                return openFirstTeamMemberForTour();
            }

            return null;
        },
    },
);

// Penantian modal global bisa lama; kalau orangnya keburu pindah halaman,
// tour milik halaman yang sudah dilepas tidak boleh ikut menyala.
let autoStartTourDibatalkan = false;

const autoStartTourIfFirstTime = () => {
    if (!tour.isFirstTime.value || tour.isActive.value) return;

    setTimeout(async () => {
        await tungguTourBolehOtomatis();

        if (!autoStartTourDibatalkan && tour.isFirstTime.value && !tour.isActive.value) {
            tour.start({ fromBeginning: true });
        }
    }, 1500);
};

const todayLabel = computed(() => props.todayLabel || formatDateLabel(new Date().toISOString().slice(0, 10)));

const mobileBreakpoint = 768;
const isMobileViewport = ref(typeof window !== 'undefined' ? window.innerWidth <= mobileBreakpoint : false);

const mobileLeaderPanelState = ref({
    action: true,
    hcis: false,
    kpi: false,
    lms: false,
    members: false,
    calendar: false,
    approval: false,
    heatmap: false,
    training: false,
});

function toggleMobileLeaderPanel(panel) {
    if (mobileLeaderPanelState.value[panel] !== undefined) {
        mobileLeaderPanelState.value[panel] = !mobileLeaderPanelState.value[panel];
    }
}

// Reveal-on-open juga saat panel dibuka lewat jalur non-klik-langsung (tour,
// toggle programatik). @click.capture pada card menangkap klik/tap manual;
// watch ini jaring untuk sisanya. handleKpi/Lms + ensureApprovals idempoten.
watch(
    () => mobileLeaderPanelState.value.kpi,
    (open) => {
        if (open) handleKpiCardInteract();
    },
);
watch(
    () => mobileLeaderPanelState.value.lms,
    (open) => {
        if (open) handleLmsCardInteract();
    },
);
watch(
    () => mobileLeaderPanelState.value.approval,
    (open) => {
        if (open) void ensureApprovalsLoaded();
    },
);
watch(
    () => mobileLeaderPanelState.value.training,
    (open) => {
        if (open) void ensureTrainingLoaded();
    },
);

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
const actionCenterSectionRef = ref(null);
const shiftSectionRef = ref(null);
const approvalsSectionRef = ref(null);
const heatmapSectionRef = ref(null);
const trainingSectionRef = ref(null);
const requestLifecycle = useRequestLifecycle();
const memberDetailCache = shallowRef({});
const memberDetailLoadingId = ref('');
const memberWeekOffsetMap = ref({});
const memberCalendarActiveDay = ref({ memberId: '', date: '', data: null });
const memberRiwayat = ref({
    show: false,
    loading: false,
    error: '',
    memberId: '',
    memberName: '',
    date: '',
    dateLabel: '',
    logs: [],
});
let memberRiwayatReqId = 0;
const MEMBER_DETAIL_MIN_LOADING_MS = 320;
const memberSectionRequested = ref(false);
const memberSectionLoading = ref(false);
const memberSectionLoaded = ref(false);
const memberSectionError = ref('');
const memberSearchPending = ref(false);
const memberSectionData = shallowRef([]);
const memberSectionPagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 5,
    total: 0,
    has_prev: false,
    has_next: false,
});
const shiftSectionRequested = ref(false);
const shiftSectionLoading = ref(false);
const shiftSectionLoaded = ref(false);
const shiftSectionError = ref('');
const shiftSectionData = shallowRef([]);
const shiftSectionLoadedDate = ref('');
const shiftDetailLoading = ref(false);
const shiftDetailError = ref('');
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
const approvalSectionError = ref('');
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
    approval_total: 0,
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
// Cold-start handling: saat backend baru men-trigger sync background (sync_pending),
// data masih di-refresh. Kita auto-refetch sekali setelah jeda, maks beberapa kali,
// agar list ter-update tanpa user perlu refresh manual.
const needsActionSyncRefetchTimer = ref(null);
const needsActionSyncRefetchAttempts = ref(0);
const NEEDS_ACTION_SYNC_REFETCH_MAX = 3;
const NEEDS_ACTION_SYNC_REFETCH_DELAY = 4000;
const needsActionNavigationLoading = ref(false);
const actionNavigationTargetId = ref('');
const actionDismissBusy = ref({});
const showMarkDoneConfirm = ref(false);

// Status "sudah dibaca" untuk item Action Center. Item yang sudah diklik dianggap
// dibaca → tampil abu-abu & turun ke bawah. Disimpan di localStorage agar bertahan
// saat reload (per-leader supaya tidak tercampur antar akun).
const ACTION_READ_STORAGE_KEY = 'leaderDashboard.actionRead';
const readActionIds = ref(new Set());

function loadReadActionIds() {
    try {
        const raw = window.localStorage?.getItem(ACTION_READ_STORAGE_KEY);
        if (!raw) {
            return;
        }
        const parsed = JSON.parse(raw);
        if (Array.isArray(parsed)) {
            readActionIds.value = new Set(parsed.map((id) => String(id)));
        }
    } catch (error) {
        console.warn('Gagal memuat status baca Action Center', error);
    }
}

function persistReadActionIds() {
    try {
        window.localStorage?.setItem(ACTION_READ_STORAGE_KEY, JSON.stringify(Array.from(readActionIds.value)));
    } catch (error) {
        console.warn('Gagal menyimpan status baca Action Center', error);
    }
}

function isActionRead(action) {
    return readActionIds.value.has(String(action?.id || ''));
}

function markActionRead(action) {
    const id = String(action?.id || '').trim();
    if (!id || readActionIds.value.has(id)) {
        return;
    }
    // Buat Set baru agar reaktivitas Vue ter-trigger.
    const next = new Set(readActionIds.value);
    next.add(id);
    readActionIds.value = next;
    persistReadActionIds();
}
const itemToMarkDone = ref(null);

function initiateMarkDone(item) {
    itemToMarkDone.value = item;
    showMarkDoneConfirm.value = true;
}

async function confirmMarkDone() {
    if (!itemToMarkDone.value) return;
    showMarkDoneConfirm.value = false;
    await dismissActionNotification(itemToMarkDone.value);
    itemToMarkDone.value = null;
}
const heatmapSectionRequested = ref(false);
const heatmapSectionLoading = ref(false);
const heatmapSectionLoaded = ref(false);
const heatmapSectionError = ref('');
const heatmapSectionData = shallowRef([]);
const heatmapWeekStart = ref(null);
const heatmapWeekEnd = ref(null);
const heatmapDateRangeLabel = ref('');
const heatmapSearchQuery = ref('');
// Periode cutoff yang sedang dilihat di heatmap. Default = periode cutoff berjalan,
// diturunkan dari cutoff_start props (sama persis dgn Team Calendar via
// getTeamCalendarInitialPeriod) supaya bulan = bulan START cutoff dari tabel,
// bukan bulan kalender hari ini.
const heatmapCutoffMonth = ref(getTeamCalendarInitialPeriod());
const heatmapSectionPagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 5,
    total: 0,
    has_prev: false,
    has_next: false,
});

// Metadata 9 kategori klasifikasi untuk badge + legend heatmap.
const heatmapCategoryMeta = [
    { key: 'on-time', label: 'On-time', icon: 'bi bi-check-circle-fill', cls: 'tone-success' },
    { key: 'late', label: 'Telat', icon: 'bi bi-alarm-fill', cls: 'tone-warning' },
    { key: 'early-out', label: 'Pulang Cepat', icon: 'bi bi-box-arrow-right', cls: 'tone-orange' },
    { key: 'working', label: 'Sedang Bekerja', icon: 'bi bi-person-workspace', cls: 'tone-working' },
    { key: 'cuti', label: 'Cuti', icon: 'bi bi-airplane-fill', cls: 'tone-info' },
    { key: 'sakit', label: 'Sakit', icon: 'bi bi-heart-pulse-fill', cls: 'tone-info2' },
    { key: 'absent', label: 'Alpha', icon: 'bi bi-x-octagon-fill', cls: 'tone-danger' },
    { key: 'no-checkin', label: 'No In', icon: 'bi bi-box-arrow-in-right', cls: 'tone-danger2' },
    { key: 'no-checkout', label: 'No Out', icon: 'bi bi-box-arrow-left', cls: 'tone-danger3' },
];
const trainingSectionRequested = ref(false);
const trainingSectionLoading = ref(false);
const trainingSectionLoaded = ref(false);
const trainingSectionError = ref('');
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
        year: getTeamCalendarInitialPeriod().year,
        month: getTeamCalendarInitialPeriod().month,
        total_members: 0,
    },
});
const teamCalendarMonthCache = shallowRef({});
const teamCalendarMonthRequestingKey = ref('');
const teamCalendarMonthController = ref(null);
const teamCalendarPanelRef = ref(null);
const teamCalendarPanelObserver = ref(null);
const heavySectionObserver = ref(null);
const teamCalendarInitialPeriod = getTeamCalendarInitialPeriod();
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
// Reveal-on-click (seperti homepage.vue revealKpi/revealLms): KPI & LMS TIDAK dimuat
// eager saat kunjungan; hanya saat card diklik/di-expand → hemat query berat
// (union-9-view approval / LMS) untuk leader yang cuma buka absensi.
const kpiRevealed = ref(false);
const lmsRevealed = ref(false);
const isHcisTrendLoading = ref(false);
const isKpiDomainLoading = ref(false);
const isLmsDomainLoading = ref(false);
const hcisDomainError = ref('');
const kpiDomainError = ref('');
const lmsDomainError = ref('');
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

/*
 * Pembatas konkurensi request leader: maksimal MAX_PARALLEL berjalan bersamaan.
 * Tiap query domain leader berat (SP absen tim + approval union 9 view), jadi
 * burst paralel saat mount = lonjakan CPU Cloud SQL. Antrian ini meratakannya.
 */
const MAX_PARALLEL_LEADER_REQUESTS = 2;
let activeLeaderRequests = 0;
const leaderRequestQueue = [];

function runQueuedLeaderRequests() {
    while (activeLeaderRequests < MAX_PARALLEL_LEADER_REQUESTS && leaderRequestQueue.length) {
        const job = leaderRequestQueue.shift();
        activeLeaderRequests += 1;
        Promise.resolve()
            .then(job)
            .catch(() => {}) // error sudah diteruskan ke pemanggil via reject
            .finally(() => {
                activeLeaderRequests -= 1;
                runQueuedLeaderRequests();
            });
    }
}

function enqueueLeaderRequest(task) {
    return new Promise((resolve, reject) => {
        leaderRequestQueue.push(() => task().then(resolve, reject));
        runQueuedLeaderRequests();
    });
}

/*
 * Cache klien (sessionStorage) untuk ringkasan domain leader berat. Saat refresh
 * / navigasi balik, render dari cache dulu, lalu revalidate. TTL pendek.
 */
const LEADER_CACHE_TTL_MS = 60 * 1000;
const LEADER_CACHE_PREFIX = 'ld.domain.v1:';

function leaderCacheKey(domain, params) {
    const suffix = params && Object.keys(params).length ? ':' + JSON.stringify(params) : '';
    return LEADER_CACHE_PREFIX + domain + suffix;
}

function readLeaderCache(domain, params) {
    if (typeof sessionStorage === 'undefined') return null;
    try {
        const raw = sessionStorage.getItem(leaderCacheKey(domain, params));
        if (!raw) return null;
        const entry = JSON.parse(raw);
        if (!entry || Date.now() - entry.t > LEADER_CACHE_TTL_MS) return null;
        return entry.payload;
    } catch {
        return null;
    }
}

function writeLeaderCache(domain, params, payload) {
    if (typeof sessionStorage === 'undefined') return;
    try {
        sessionStorage.setItem(leaderCacheKey(domain, params), JSON.stringify({ t: Date.now(), payload }));
    } catch {
        /* kuota penuh / mode privat: abaikan */
    }
}

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

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('resize', handleResize);
        handleResize();
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', handleResize);
    }
    if (teamCalendarPanelObserver.value) {
        teamCalendarPanelObserver.value.disconnect();
    }
});

function handleResize() {
    isMobileViewport.value = window.innerWidth <= mobileBreakpoint;
}
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
const calendarCutoffLabel = computed(() => {
    const periodSource = props.overview?.period || props.period || {};
    const start = String(periodSource.cutoff_start || periodSource.cutoffStart || '').slice(0, 10);
    const end = String(periodSource.cutoff_end || periodSource.cutoffEnd || '').slice(0, 10);

    if (start && end) {
        const formatLongDate = (dateString) => {
            return new Date(`${dateString}T00:00:00`).toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
        };
        return `${formatLongDate(start)} - ${formatLongDate(end)}`;
    }

    return activePeriodLabel.value;
});
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
        scheduledCount: Number(
            shift.scheduledCount ?? shift.scheduled_count ?? shift.memberCount ?? shift.memberIds?.length ?? 0,
        ),
        unavailableCount: Number(
            shift.unavailableCount ??
                shift.unavailable_count ??
                shift.gapCount ??
                shift.unavailableMemberIds?.length ??
                0,
        ),
        availableCount: Number(
            shift.availableCount ??
                shift.available_count ??
                shift.coverageCount ??
                shift.coverage_count ??
                Math.max(
                    0,
                    Number(shift.memberCount ?? shift.memberIds?.length ?? 0) -
                        Number(shift.gapCount ?? shift.unavailableMemberIds?.length ?? 0),
                ),
        ),
        availabilityRate: Number(shift.availabilityRate ?? shift.availability_rate ?? 0),
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
            (selectedAttendance.value === 'absent' &&
                ['absent', 'no-checkin'].includes(member.attendance.todayStatus)) ||
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

const memberAwarenessCount = computed(() => {
    return filteredMembers.value.filter((m) => m.hcisRiskCount > 0 || m.kpiAlertCount > 0).length;
});
const showMemberSearchFeedback = computed(() => {
    const hasSearchIntent = Boolean(searchKeyword.value.trim()) || needsActionNavigationLoading.value;
    return (
        hasSearchIntent &&
        (memberSearchPending.value || needsActionNavigationLoading.value || memberSectionLoading.value)
    );
});

const showTeamListSkeleton = computed(() => memberSectionLoading.value || showMemberSearchFeedback.value);

const memberSearchFeedbackTitle = computed(() => {
    if (needsActionNavigationLoading.value) {
        return 'Menemukan anggota target...';
    }

    return 'Memproses pencarian anggota tim...';
});

const memberSearchFeedbackDescription = computed(() => {
    if (needsActionNavigationLoading.value) {
        return 'Menyesuaikan filter, mencari data anggota, lalu membuka detail otomatis.';
    }

    if (memberSearchPending.value) {
        return 'Menyelaraskan keyword dengan data tim terbaru.';
    }

    return 'Menyiapkan hasil pencarian anggota.';
});

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
        categoryCounts: member.categoryCounts || {},
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

// Jumlah hari (kolom) pada periode aktif — untuk skeleton & lebar grid.
const heatmapDayCount = computed(() => {
    const first = heatmapRows.value[0];
    const len = first?.attendance?.dailyLog?.length || 0;
    return len > 0 ? len : 30;
});

// Header kolom tanggal (diambil dari anggota pertama). Tiap item:
// - dateNum: angka tanggal (1..31)
// - dow: inisial hari (S/S/R/K/J/S/M)
// - isWeekStart: true bila Senin (dayKey 1) → ditebalkan + garis pemisah minggu
// - isToday: tandai hari ini
const heatmapDayHeaders = computed(() => {
    const first = heatmapRows.value[0];
    const log = first?.attendance?.dailyLog;
    if (!Array.isArray(log) || !log.length) {
        return [];
    }
    const todayStr = new Date().toISOString().slice(0, 10);
    const dowLabels = ['M', 'S', 'S', 'R', 'K', 'J', 'S']; // index 0=Minggu .. 6=Sabtu (getDay)
    return log.map((d) => {
        const full = String(d.fullDate || '');
        const dt = full ? new Date(`${full}T00:00:00`) : null;
        const dateNum = dt ? dt.getDate() : '';
        const jsDow = dt ? dt.getDay() : -1; // 0=Minggu
        return {
            key: full || d.date,
            dateNum,
            dow: jsDow >= 0 ? dowLabels[jsDow] : '',
            isWeekStart: jsDow === 1, // Senin = awal minggu
            isToday: full === todayStr,
            isWeekend: jsDow === 0 || jsDow === 6,
        };
    });
});

// Key bulan cutoff terpilih ("YYYY-M") untuk binding <select>.
const heatmapCutoffKey = computed(() => `${heatmapCutoffMonth.value.year}-${heatmapCutoffMonth.value.month}`);

// Periode cutoff "berjalan" = bulan START cutoff dari tabel (lewat cutoff_start props),
// bukan bulan kalender hari ini. Disamakan dgn Team Calendar.
const heatmapCurrentCutoffPeriod = computed(() => getTeamCalendarInitialPeriod());

// Daftar 12 periode cutoff terakhir, di-anchor ke bulan START cutoff berjalan.
const heatmapMonthOptions = computed(() => {
    const opts = [];
    const cur = heatmapCurrentCutoffPeriod.value;
    for (let i = 0; i < 12; i++) {
        const d = new Date(cur.year, cur.month - 1 - i, 1);
        const year = d.getFullYear();
        const month = d.getMonth() + 1;
        opts.push({
            key: `${year}-${month}`,
            year,
            month,
            label: `Cutoff ${d.toLocaleDateString('id-ID', { month: 'short', year: 'numeric' })}`,
        });
    }
    return opts;
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
    const scheduledToday = Number(hcisStats.scheduledToday ?? totalMembers);
    const presentToday = Number(hcisStats.presentToday ?? 0);
    const absentToday = Number(hcisStats.absentToday ?? 0);
    const noCheckinToday = Number(hcisStats.noCheckinToday ?? absentToday ?? 0);
    const pendingCheckInToday = Number(hcisStats.pendingCheckInToday ?? 0);
    const workingToday = Number(hcisStats.workingToday ?? 0);
    const noCheckoutToday = Number(hcisStats.noCheckoutToday ?? 0);
    // Count cutoff-period untuk 5 kartu stat HCIS. Prefer hcisStats (data card yang
    // lazily-loaded & sudah termasuk pulang cepat), fallback ke base overview summary.
    const alphaCount = Number(hcisStats.alphaCount ?? base.alphaCount ?? 0);
    const sickDays = Number(base.sickDays || 0);
    const lateCount = Number(hcisStats.lateCount ?? 0);
    const earlyOutCount = Number(hcisStats.earlyOutCount ?? base.earlyOutCount ?? 0);
    const noCheckinCount = Number(hcisStats.noCheckinCount ?? base.noCheckinCount ?? 0);
    const noCheckoutCount = Number(hcisStats.noCheckoutCount ?? 0);
    const leaveDays = Number(hcisStats.leaveDays ?? base.leaveDays ?? 0);
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
    // Rata-rata hanya dari bulan aktif (value > 0) agar bulan kosong tidak menarik turun angka.
    const lmsActiveMonths = Array.isArray(lmsDomainSummary.value?.trend)
        ? lmsDomainSummary.value.trend.filter((item) => Number(item.value || 0) > 0)
        : [];
    const lmsAvgMonthlyHours = Number(
        lmsActiveMonths.length
            ? lmsActiveMonths.reduce((sum, item) => sum + Number(item.value || 0), 0) / lmsActiveMonths.length
            : 0,
    );
    const approvalQueue = approvalItemCount.value || Number(base.approvalQueue || 0);
    const followUpTotal = Number(base.followUpTotal || attendanceIssues + kpiPending + approvalQueue + lmsAtRisk || 0);

    const totalScheduledToday = selectedDateShiftSummary.value.reduce(
        (total, shift) => total + Number(shift.scheduledCount || 0),
        0,
    );

    const totalAvailableToday = selectedDateShiftSummary.value.reduce(
        (total, shift) => total + Number(shift.availableCount || 0),
        0,
    );

    const shiftCoverage = totalScheduledToday
        ? Math.min(100, Math.round((totalAvailableToday / totalScheduledToday) * 100))
        : Number(base.shiftCoverage || 0);

    const kpiImprovement = Number(kpiStats.kpiImprovement ?? base.kpiImprovement ?? 0);

    return {
        totalMembers,
        scheduledToday,
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
        noCheckinCount,
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

const isKpiInsightPending = computed(() => {
    return isKpiDomainLoading.value && !kpiDomainSummary.value;
});

const kpiPrimaryScore = computed(() => {
    if (isKpiFullYearView.value) {
        return Number(summary.value.ytdAvgScore || 0);
    }
    return Number(summary.value.monthlyAvgScore || 0);
});

const quickStats = computed(() => {
    const hcisActionCount = needsActionLoaded.value ? Number(needsActionSummary.value?.hcis_total || 0) : null;

    return [
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
            value: hcisActionCount === null ? '-' : hcisActionCount,
            description: 'sinkron Perlu Ditindak (Action Center)',
            icon: 'bi bi-calendar-x',
            tone: hcisActionCount === null ? 'info' : hcisActionCount > 0 ? 'warning' : 'success',
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
    ];
});

const quickStatVisibilityMap = computed(() => ({
    'follow-up': false,
    approval: false,
}));

const visibleQuickStats = computed(() =>
    quickStats.value.filter((item) => quickStatVisibilityMap.value[item.key] !== false),
);

const quickStatSkeletonCount = computed(() => Math.max(3, visibleQuickStats.value.length || 0));

// 5 kartu stat HCIS — scope PERIODE CUTOFF BERJALAN. Angka diambil dari summary
// (sudah dihitung saat load card, tanpa query tambahan). `category` dipakai untuk
// membuka Drawer A (breakdown per kategori).
const hcisMonitoringStats = computed(() => {
    const absentCount = Number(summary.value.alphaCount || 0);
    const noCiCoTotal = Number(summary.value.noCheckinCount || 0) + Number(summary.value.noCheckoutCount || 0);
    const lateEarlyTotal = Number(summary.value.lateCount || 0) + Number(summary.value.earlyOutCount || 0);
    const issueTotal = absentCount + noCiCoTotal + lateEarlyTotal;
    const izinCutiTotal = Number(summary.value.leaveDays || 0) + Number(summary.value.sickDays || 0);

    return [
        {
            key: 'absent',
            category: 'absent',
            label: 'Tidak Hadir',
            value: absentCount,
            caption: 'periode berjalan',
            tone: absentCount > 0 ? 'danger' : 'success',
        },
        {
            key: 'no-in-out',
            category: 'no-in-out',
            label: 'No CI/CO',
            value: noCiCoTotal,
            caption: 'periode berjalan',
            tone: noCiCoTotal > 0 ? 'danger' : 'success',
        },
        {
            key: 'late-early',
            category: 'late-early',
            label: 'Terlambat / Pulang Cepat',
            value: lateEarlyTotal,
            caption: 'periode berjalan',
            tone: lateEarlyTotal > 0 ? 'warning' : 'success',
        },
        // {
        //     key: 'issue',
        //     category: 'issue',
        //     label: 'Issue',
        //     value: issueTotal,
        //     caption: 'periode berjalan',
        //     tone: issueTotal > 0 ? 'danger' : 'success',
        // },
        {
            key: 'izin-cuti',
            category: 'izin-cuti',
            label: 'Izin / Cuti',
            value: izinCutiTotal,
            caption: 'periode berjalan',
            tone: izinCutiTotal > 0 ? 'info' : 'success',
        },
    ];
});

const hcisStatusInsightText = computed(() => {
    const noCheckInToday = Number(summary.value.noCheckinToday || summary.value.absentToday || 0);
    const pendingCheckInToday = Number(summary.value.pendingCheckInToday || 0);
    const noCheckOutToday = Number(summary.value.noCheckoutToday || 0);

    if (noCheckInToday > 0 || noCheckOutToday > 0) {
        return 'Perlu validasi absensi untuk anggota dengan check-in/check-out yang belum lengkap.';
    }
    if (pendingCheckInToday > 0) {
        return 'Sebagian anggota belum masuk jam kerja. Pantau kembali menjelang jam masuk shift.';
    }

    return 'Kehadiran tim hari ini stabil. Lanjutkan monitoring ritme kerja per shift.';
});

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
            date: item.date || '',
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
                const idx = Number(dataPointIndex ?? 0);
                // Konsisten dgn click handler: pakai tanggal eksplisit dari backend.
                const date = hcisDailyTrend.value[idx]?.date || resolveTrendDateByIndex(idx);
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
        if (member.attendance.issues && member.attendance.issues.length > 0) {
            const issueDates = member.attendance.issues
                .map((issue) => {
                    const match = (issue.title || '').match(/(\d{1,2}\s\w+\s\d{4})/);
                    return match ? match[1] : '';
                })
                .filter(Boolean);

            const dateStr =
                issueDates.length > 2
                    ? `${issueDates.slice(0, 2).join(', ')} +${issueDates.length - 2}...`
                    : issueDates.join(', ');

            items.push({
                id: `attendance-${member.id}`,
                memberId: member.id,
                tone: 'warning',
                icon: 'bi bi-exclamation-circle',
                title: `${member.name} - Absensi bermasalah`,
                description: `${member.attendance.issues.length} issue: ${dateStr}`,
                meta: 'HCIS Absensi',
            });
        } else if (!member.attendance.issues.length && member.attendance.issueCount > 0) {
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
                item?.tone || (severity === 'critical' ? 'danger' : severity === 'high' ? 'warning' : 'orange');
            const icon =
                item?.icon ||
                (category === 'hcis'
                    ? 'bi bi-person-exclamation'
                    : category === 'approval'
                      ? 'bi bi-check2-square'
                      : 'bi bi-graph-up-arrow');
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
                    absent: Number(item?.case_breakdown?.absent || 0),
                    no_data: Number(item?.case_breakdown?.no_data || 0),
                },
                caseDates: Array.isArray(item?.case_dates) ? item.case_dates : [],
                url: item?.url || resolveActionCenterUrl(item),
                noTransaksi: item?.no_transaksi || '',
                notificationId: item?.notification_id || null,
                notificationKey: item?.notification_key || '',
                dismissible: Boolean(item?.dismissible && item?.notification_id),
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
            absent: 0,
            no_data: 0,
        },
        caseDates: Array.isArray(item?.caseDates) ? item.caseDates : [],
        dismissible: false,
    }));
});

// Item yang sudah dibaca diturunkan ke bawah (stable: urutan asli dipertahankan
// di dalam tiap grup belum-dibaca / sudah-dibaca).
const sortedActionItems = computed(() => {
    const items = actionItems.value;
    const unread = [];
    const read = [];
    for (const item of items) {
        if (readActionIds.value.has(String(item?.id || ''))) {
            read.push(item);
        } else {
            unread.push(item);
        }
    }
    return [...unread, ...read];
});

function actionCardTitle(action) {
    const rawTitle = String(action?.title || '').trim();
    if (!rawTitle) {
        return 'Perlu tindak lanjut';
    }

    if (String(action?.category || '').toLowerCase() !== 'hcis') {
        return rawTitle;
    }

    const segments = rawTitle.split(' - ');
    if (segments.length < 2) {
        return rawTitle;
    }

    const memberName = String(segments[0] || '').trim();
    return memberName ? `${memberName} - Perlu validasi absensi` : rawTitle;
}

function totalActionCaseCount(action) {
    const breakdown = action?.caseBreakdown || {};
    return (
        Number(breakdown.no_checkin || 0) +
        Number(breakdown.no_checkout || 0) +
        Number(breakdown.late || 0) +
        Number(breakdown.absent || 0) +
        Number(breakdown.no_data || 0)
    );
}

function actionCardDescription(action) {
    const category = String(action?.category || '').toLowerCase();
    const rawDescription = String(action?.description || '').trim();

    if (category === 'hcis') {
        const totalCase = totalActionCaseCount(action);
        if (totalCase > 0) {
            return `${totalCase} kejadian absensi perlu validasi pada periode cutoff berjalan.`;
        }
    }

    return rawDescription || 'Klik untuk melihat detail tindak lanjut.';
}

function actionCaseDatesLabel(action) {
    const dates = (Array.isArray(action?.caseDates) ? action.caseDates : [])
        .map((item) => String(item || '').trim())
        .filter(Boolean);

    if (!dates.length) {
        return '';
    }
    if (dates.length <= 2) {
        return `Tanggal kejadian: ${dates.join(', ')}`;
    }

    return `Tanggal kejadian: ${dates.slice(0, 2).join(', ')} (+${dates.length - 2} lainnya)`;
}

function actionMetaLabel(action) {
    const rawMeta = String(action?.meta || '').trim();
    if (!rawMeta) {
        return 'Klik kartu ini untuk membuka detail anggota.';
    }

    // if (/^(HCIS|KPI|LMS|APPROVAL)\b/i.test(rawMeta)) {
    //     return `Konteks: ${rawMeta}`;
    // }

    return rawMeta;
}

const isActionCenterPending = computed(() => {
    if (needsActionLoading.value) {
        return true;
    }

    return !needsActionLoaded.value && !Boolean(needsActionError.value);
});

const heroActionItems = computed(() => {
    if (!needsActionLoaded.value || needsActionLoading.value || needsActionError.value) {
        return [];
    }

    return actionItems.value.slice(0, isMobileViewport.value ? 3 : 4);
});

const heroActionCountLabel = computed(() => {
    if (isActionCenterPending.value) {
        return '-';
    }
    if (needsActionError.value) {
        return '!';
    }

    return String(actionItemCount.value);
});

const actionItemCount = computed(() => {
    if (needsActionLoaded.value && !needsActionLoading.value) {
        return Number(needsActionSummary.value?.total_active_items || needsActionPagination.value?.total || 0);
    }

    // Jangan menampilkan angka fallback sebelum Action Center selesai dimuat.
    return 0;
});

function normalizeAttendanceIssueText(value) {
    return String(value || '')
        .toLowerCase()
        .replace(/[^a-z0-9\s/-]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function resolveAttendanceIssueCategory(issue) {
    const key = normalizeAttendanceIssueText(issue?.key || issue?.status || issue?.type || '');
    const text = normalizeAttendanceIssueText(
        [issue?.title, issue?.label, issue?.description, issue?.meta].filter(Boolean).join(' '),
    );

    if (key === 'late' || /\bterlambat\b|\blate\b/.test(text)) {
        return 'late';
    }
    if (
        key === 'no-checkout' ||
        key === 'no checkout' ||
        /\bbelum checkout\b|\bno checkout\b|\bno-checkout\b|\btidak checkout\b/.test(text)
    ) {
        return 'no-checkout';
    }
    if (key === 'absent' || key === 'alpha' || /\btidak hadir\b|\balpha\b/.test(text)) {
        return 'alpha';
    }
    if (
        key === 'no-checkin' ||
        key === 'no checkin' ||
        /\btidak check ?in\b|\bno check ?in\b|\bno-checkin\b|\bbelum ada check ?in\b/.test(text)
    ) {
        return 'no-checkin';
    }

    return '';
}

function deriveAttendanceIssueBreakdown(issues) {
    return (Array.isArray(issues) ? issues : []).reduce(
        (acc, issue) => {
            const category = resolveAttendanceIssueCategory(issue);
            if (!category) {
                return acc;
            }

            acc.resolvedCount += 1;
            if (category === 'alpha') {
                acc.alphaCount += 1;
            } else if (category === 'no-checkin') {
                acc.noCheckinCount += 1;
            } else if (category === 'no-checkout') {
                acc.noCheckoutCount += 1;
            } else if (category === 'late') {
                acc.lateCount += 1;
            }

            return acc;
        },
        {
            resolvedCount: 0,
            alphaCount: 0,
            noCheckinCount: 0,
            noCheckoutCount: 0,
            lateCount: 0,
        },
    );
}

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
            noCheckinCount: Number(attendance.noCheckinCount ?? attendance.no_checkin_count ?? 0),
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
            achievementGradeLabel:
                kpi.achievement_grade_label || kpi.achievementGradeLabel || kpi.grade || kpi.gradeLabel || '-',
            achievementGradeColor: kpi.achievement_grade_color || kpi.achievementGradeColor || '#94a3b8',
            ytdScore: Number(kpi.ytdScore ?? kpi.ytd_score ?? 0),
            ytdSubmittedCount: Number(kpi.ytdSubmittedCount ?? kpi.ytd_submitted_count ?? 0),
            ytdGradeLabel: kpi.ytd_grade_label || kpi.ytdGradeLabel || '-',
            ytdGradeColor: kpi.ytd_grade_color || kpi.ytdGradeColor || '#94a3b8',
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
            trend:
                Array.isArray(kpi.trend) && kpi.trend.length
                    ? kpi.trend
                    : Array.isArray(kpi.monthlyAchievement || kpi.monthly_achievement)
                      ? (kpi.monthlyAchievement || kpi.monthly_achievement).map((item) => ({
                            month: String(item?.month || '').trim() || String(item?.period || '').trim() || '-',
                            score: Number(item?.score ?? item?.value ?? 0),
                            value: Number(item?.score ?? item?.value ?? 0),
                        }))
                      : [],
            parameterScheduled: Boolean(kpi.parameterScheduled ?? kpi.parameter_scheduled ?? false),
            performanceScheduled: Boolean(kpi.performanceScheduled ?? kpi.performance_scheduled ?? false),
            parameterApprovalStatus: String(kpi.parameterApprovalStatus ?? kpi.parameter_approval_status ?? 'none'),
            performanceApprovalStatus: String(
                kpi.performanceApprovalStatus ?? kpi.performance_approval_status ?? 'none',
            ),
            performanceHasParameters: Boolean(kpi.performanceHasParameters ?? kpi.performance_has_parameters ?? false),
            performanceHasDueTarget: kpi.performanceHasDueTarget ?? kpi.performance_has_due_target ?? true,
        },
        lms: {
            hasData: Boolean(lms.has_data ?? lms.hasData ?? Number(lms.total ?? lms.enrolled_courses ?? 0) > 0),
            completed: Number(lms.completed ?? lms.completed_courses ?? 0),
            total: Number(lms.total ?? lms.enrolled_courses ?? 0),
            pending: Number(
                lms.pending ??
                    lms.mandatory_pending ??
                    Math.max(
                        0,
                        Number(lms.total ?? lms.enrolled_courses ?? 0) -
                            Number(lms.completed ?? lms.completed_courses ?? 0),
                    ),
            ),
            progress: Number(
                lms.progress ?? calcProgress(lms.completed ?? lms.completed_courses, lms.total ?? lms.enrolled_courses),
            ),
            averageScore: lms.averageScore ?? lms.average_score ?? null,
            learningGain: lms.learningGain ?? lms.learning_gain ?? null,
            learningHours: Number(lms.learning_hours ?? lms.learningHours ?? 0),
            avgDurationHours: Number(lms.avg_duration_hours ?? lms.avgDurationHours ?? 0),
            attemptedCourses: Number(lms.attempted_courses ?? lms.attemptedCourses ?? 0),
            nextSession: lms.next_session || lms.nextSession || 'Tidak ada training aktif',
            nextSessionDate: lms.next_session_date || lms.nextSessionDate || '-',
            trainings: Array.isArray(lms.trainings) ? lms.trainings : [],
            progressList: Array.isArray(lms.learning_progress || lms.progressList)
                ? lms.learning_progress || lms.progressList
                : [],
            completedMonthly: Array.isArray(lms.completedMonthly || lms.completed_monthly)
                ? lms.completedMonthly || lms.completed_monthly
                : [],
            durationMonthly: Array.isArray(lms.duration_monthly || lms.durationMonthly)
                ? lms.duration_monthly || lms.durationMonthly
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

    const issueBreakdown = deriveAttendanceIssueBreakdown(normalized.attendance.issues);
    const canDeriveFromIssueList =
        normalized.attendance.issues.length > 0 &&
        normalized.attendance.issueCount <= normalized.attendance.issues.length &&
        issueBreakdown.resolvedCount > 0;

    // Jika list issue periode sudah lengkap, jadikan list issue sebagai sumber kebenaran untuk breakdown stat.
    if (canDeriveFromIssueList) {
        normalized.attendance.alphaCount = issueBreakdown.alphaCount;
        normalized.attendance.absentCount = issueBreakdown.alphaCount;
        normalized.attendance.noCheckinCount = issueBreakdown.noCheckinCount;

        if (issueBreakdown.noCheckoutCount > 0) {
            normalized.attendance.noCheckoutCount = issueBreakdown.noCheckoutCount;
        }
        if (issueBreakdown.lateCount > 0 && normalized.attendance.lateCount === 0) {
            normalized.attendance.lateCount = issueBreakdown.lateCount;
        }
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
        const response = await enqueueLeaderRequest(() =>
            axios.get('/homepage/leader-dashboard/needs-action', {
                params: {
                    page: targetPage,
                    per_page: needsActionPagination.value.per_page || 10,
                    category: needsActionCategory.value || 'all',
                    search: '',
                    kpi_year: normalizeKpiYear(selectedKpiYear.value),
                },
            }),
        );

        needsActionRows.value = Array.isArray(response?.data?.data?.items) ? response.data.data.items : [];
        needsActionSummary.value = response?.data?.data?.summary || needsActionSummary.value;
        needsActionPagination.value = response?.data?.pagination || needsActionPagination.value;
        needsActionLoaded.value = true;

        // Jika backend baru men-trigger sync background, jadwalkan satu refetch agar
        // data hasil sync muncul tanpa refresh manual (terutama saat cold-start).
        maybeScheduleNeedsActionSyncRefetch(Boolean(response?.data?.data?.summary?.sync_pending), targetPage);
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
    needsActionSyncRefetchAttempts.value = 0;
    void loadNeedsAction(needsActionPagination.value.current_page || 1, { force: true });
}

function clearNeedsActionSyncRefetch() {
    if (needsActionSyncRefetchTimer.value) {
        clearTimeout(needsActionSyncRefetchTimer.value);
        needsActionSyncRefetchTimer.value = null;
    }
}

function maybeScheduleNeedsActionSyncRefetch(syncPending, page) {
    clearNeedsActionSyncRefetch();

    if (!syncPending) {
        needsActionSyncRefetchAttempts.value = 0;
        return;
    }

    if (needsActionSyncRefetchAttempts.value >= NEEDS_ACTION_SYNC_REFETCH_MAX) {
        return;
    }

    needsActionSyncRefetchAttempts.value += 1;
    needsActionSyncRefetchTimer.value = setTimeout(() => {
        needsActionSyncRefetchTimer.value = null;
        void loadNeedsAction(page, { force: true });
    }, NEEDS_ACTION_SYNC_REFETCH_DELAY);
}

function isActionDismissBusy(action) {
    const key = action?.notificationId || action?.notificationKey || action?.id;
    return Boolean(key && actionDismissBusy.value[key]);
}

async function dismissActionNotification(action) {
    const notificationId = action?.notificationId;
    const busyKey = notificationId || action?.notificationKey || action?.id;

    if (!notificationId || !busyKey || actionDismissBusy.value[busyKey]) {
        return;
    }

    actionDismissBusy.value = {
        ...actionDismissBusy.value,
        [busyKey]: true,
    };

    try {
        await axios.post(`/homepage/notifications/${encodeURIComponent(notificationId)}/dismiss`);
        needsActionRows.value = needsActionRows.value.filter((candidate) => {
            if (notificationId && candidate?.notification_id === notificationId) {
                return false;
            }
            if (action.notificationKey && candidate?.notification_key === action.notificationKey) {
                return false;
            }

            return true;
        });

        const category = String(action?.category || '').toLowerCase();
        needsActionSummary.value = {
            ...needsActionSummary.value,
            total_active_items: Math.max(0, Number(needsActionSummary.value?.total_active_items || 0) - 1),
            hcis_total:
                category === 'hcis'
                    ? Math.max(0, Number(needsActionSummary.value?.hcis_total || 0) - 1)
                    : needsActionSummary.value?.hcis_total || 0,
            kpi_total:
                category === 'kpi'
                    ? Math.max(0, Number(needsActionSummary.value?.kpi_total || 0) - 1)
                    : needsActionSummary.value?.kpi_total || 0,
            approval_total:
                category === 'approval'
                    ? Math.max(0, Number(needsActionSummary.value?.approval_total || 0) - 1)
                    : needsActionSummary.value?.approval_total || 0,
        };
        needsActionPagination.value = {
            ...needsActionPagination.value,
            total: Math.max(0, Number(needsActionPagination.value?.total || 0) - 1),
        };
    } catch (error) {
        console.error('Failed to dismiss leader action notification', error);
        needsActionError.value = 'Gagal memproses tindakan. Silakan coba lagi.';
    } finally {
        const next = { ...actionDismissBusy.value };
        delete next[busyKey];
        actionDismissBusy.value = next;
    }
}

async function loadMembers(page = 1) {
    const targetPage = Math.max(1, Number(page || 1));
    const requestKey = 'members';
    const { signal, version } = requestLifecycle.start(requestKey);

    memberSectionRequested.value = true;
    memberSectionLoading.value = true;
    memberSectionError.value = '';

    try {
        const response = await axios.get('/homepage/leader-dashboard/members', {
            signal,
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

        if (!requestLifecycle.isLatest(requestKey, version)) return;
        memberSectionData.value = Array.isArray(response?.data?.data) ? response.data.data.map(normalizeMember) : [];
        memberSectionPagination.value = response?.data?.pagination || memberSectionPagination.value;
        memberSectionLoaded.value = true;
        openedMemberId.value = null;
    } catch (error) {
        if (isCanceledRequest(error) || !requestLifecycle.isLatest(requestKey, version)) return;
        console.error('Failed to load team members', error);
        memberSectionData.value = [];
        memberSectionError.value = 'Gagal memuat daftar anggota tim.';
    } finally {
        if (requestLifecycle.isLatest(requestKey, version)) {
            memberSectionLoading.value = false;
            requestLifecycle.finish(requestKey, version);
        }
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

function scrollToActionCenterSection() {
    const element = actionCenterSectionRef.value;
    if (!element || typeof element.scrollIntoView !== 'function') {
        return;
    }

    if (isMobileViewport.value && mobileLeaderPanelState.value.action === false) {
        mobileLeaderPanelState.value.action = true;
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
        found =
            rows.find(
                (member) =>
                    String(member?.name || '')
                        .trim()
                        .toLowerCase() === normalizedSearchName,
            ) || null;
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
    if (shiftSectionLoaded.value && shiftSectionLoadedDate.value === targetDate) {
        return;
    }
    const requestKey = 'shift-board';
    const { signal, version } = requestLifecycle.start(requestKey);

    shiftSectionRequested.value = true;
    shiftSectionLoading.value = true;
    shiftSectionError.value = '';

    try {
        const response = await axios.get('/homepage/leader-dashboard/shifts', {
            signal,
            params: {
                date: targetDate,
            },
        });

        if (!requestLifecycle.isLatest(requestKey, version)) return;
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
        if (isCanceledRequest(error) || !requestLifecycle.isLatest(requestKey, version)) return;
        console.error('Failed to load shift board', error);
        shiftSectionData.value = [];
        shiftSectionError.value = 'Gagal memuat distribusi shift.';
    } finally {
        if (requestLifecycle.isLatest(requestKey, version)) {
            shiftSectionLoading.value = false;
            requestLifecycle.finish(requestKey, version);
        }
    }
}

function retryShiftBoardLoad() {
    shiftSectionLoaded.value = false;
    void loadShiftBoard(selectedShiftDate.value || new Date().toISOString().slice(0, 10));
}

async function loadApprovals(page = approvalSectionPagination.value.current_page || 1) {
    const targetPage = Math.max(1, page);
    const requestKey = 'approvals';
    const { signal, version } = requestLifecycle.start(requestKey);

    approvalSectionRequested.value = true;
    approvalSectionLoading.value = true;
    approvalSectionError.value = '';

    try {
        const response = await axios.get('/homepage/leader-dashboard/approvals', {
            signal,
            params: {
                page: targetPage,
                per_page: approvalSectionPagination.value.per_page || 6,
            },
        });

        if (!requestLifecycle.isLatest(requestKey, version)) return;
        approvalSectionData.value = Array.isArray(response?.data?.data) ? response.data.data : [];
        approvalSectionPagination.value = response?.data?.pagination || approvalSectionPagination.value;
        approvalSectionLoaded.value = true;
    } catch (error) {
        if (isCanceledRequest(error) || !requestLifecycle.isLatest(requestKey, version)) return;
        console.error('Failed to load approvals', error);
        approvalSectionData.value = [];
        approvalSectionError.value = 'Gagal memuat antrian approval.';
    } finally {
        if (requestLifecycle.isLatest(requestKey, version)) {
            approvalSectionLoading.value = false;
            requestLifecycle.finish(requestKey, version);
        }
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
        const response = await enqueueLeaderRequest(() =>
            axios.get('/homepage/leader-dashboard/summary', {
                signal: controller?.signal,
            }),
        );
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

    // Cache-first: render dari cache klien dulu (jika ada), lalu revalidate.
    if (!force) {
        const cached = readLeaderCache('hcis');
        if (cached) hcisDomainSummary.value = cached;
    }

    const { controller, requestVersion } = startSummaryRequest('hcis');
    isHcisTrendLoading.value = true;
    hcisDomainError.value = '';

    try {
        const response = await enqueueLeaderRequest(() =>
            axios.get('/homepage/leader-dashboard/summary/hcis', {
                signal: controller?.signal,
            }),
        );
        if (!isLatestSummaryRequest('hcis', requestVersion)) {
            return;
        }

        hcisDomainSummary.value = response?.data?.data || null;
        writeLeaderCache('hcis', null, hcisDomainSummary.value);
    } catch (error) {
        if (isCanceledRequest(error)) {
            return;
        }

        console.warn('Failed to load HCIS domain summary', error);
        if (isLatestSummaryRequest('hcis', requestVersion)) {
            hcisDomainSummary.value = null;
            hcisDomainError.value = 'Gagal memuat ringkasan HCIS team. Silakan coba lagi.';
        }
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

    const requestedYear = normalizeKpiYear(selectedKpiYear.value);

    // Cache-first per tahun: render dari cache klien dulu, lalu revalidate.
    if (!force) {
        const cached = readLeaderCache('kpi', { year: requestedYear });
        if (cached) kpiDomainSummary.value = cached;
    }

    const { controller, requestVersion } = startSummaryRequest('kpi');
    isKpiDomainLoading.value = true;
    kpiDomainError.value = '';

    try {
        const response = await enqueueLeaderRequest(() =>
            axios.get('/homepage/leader-dashboard/summary/kpi', {
                signal: controller?.signal,
                params: {
                    year: requestedYear,
                },
            }),
        );
        if (!isLatestSummaryRequest('kpi', requestVersion)) {
            return;
        }

        kpiDomainSummary.value = response?.data?.data || null;
        writeLeaderCache('kpi', { year: requestedYear }, kpiDomainSummary.value);
        const resolvedYear = Number(response?.data?.data?.stats?.kpiYear || 0);
        if (resolvedYear >= 2000 && resolvedYear <= 2100) {
            selectedKpiYear.value = resolvedYear;
        }
    } catch (error) {
        if (isCanceledRequest(error)) {
            return;
        }

        console.warn('Failed to load KPI domain summary', error);
        if (isLatestSummaryRequest('kpi', requestVersion)) {
            kpiDomainError.value = 'Gagal memuat ringkasan KPI team. Silakan coba lagi.';
        }
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

    const lmsYear = Number(selectedLmsYear.value || currentLmsYear);

    // Cache-first per tahun: render dari cache klien dulu, lalu revalidate.
    if (!force) {
        const cached = readLeaderCache('lms', { year: lmsYear });
        if (cached) lmsDomainSummary.value = cached;
    }

    const { controller, requestVersion } = startSummaryRequest('lms');
    isLmsDomainLoading.value = true;
    lmsDomainError.value = '';

    try {
        const response = await enqueueLeaderRequest(() =>
            axios.get('/homepage/leader-dashboard/summary/lms', {
                signal: controller?.signal,
                params: {
                    year: lmsYear,
                },
            }),
        );
        if (!isLatestSummaryRequest('lms', requestVersion)) {
            return;
        }

        lmsDomainSummary.value = response?.data?.data || null;
        writeLeaderCache('lms', { year: lmsYear }, lmsDomainSummary.value);
        const resolvedYear = Number(response?.data?.data?.stats?.year || 0);
        if (resolvedYear >= 2000 && resolvedYear <= 2100) {
            selectedLmsYear.value = resolvedYear;
        }
    } catch (error) {
        if (isCanceledRequest(error)) {
            return;
        }

        console.warn('Failed to load LMS domain summary', error);
        if (isLatestSummaryRequest('lms', requestVersion)) {
            lmsDomainSummary.value = null;
            lmsDomainError.value = 'Gagal memuat ringkasan LMS team. Silakan coba lagi.';
        }
    } finally {
        if (isLatestSummaryRequest('lms', requestVersion)) {
            isLmsDomainLoading.value = false;
        }
    }
}

async function loadHeatmap(page = heatmapSectionPagination.value.current_page || 1) {
    const targetPage = Math.max(1, page);
    const requestKey = 'heatmap';
    const { signal, version } = requestLifecycle.start(requestKey);

    heatmapSectionRequested.value = true;
    heatmapSectionLoading.value = true;
    heatmapSectionError.value = '';

    try {
        const params = {
            page: targetPage,
            per_page: heatmapSectionPagination.value.per_page || 5,
            cutoff_year: heatmapCutoffMonth.value.year,
            cutoff_month: heatmapCutoffMonth.value.month,
        };

        if (heatmapSearchQuery.value) {
            params.search = heatmapSearchQuery.value;
        }

        const response = await axios.get('/homepage/leader-dashboard/heatmap', { params, signal });

        if (!requestLifecycle.isLatest(requestKey, version)) return;
        heatmapSectionData.value = Array.isArray(response?.data?.data) ? response.data.data : [];
        heatmapSectionPagination.value = response?.data?.pagination || heatmapSectionPagination.value;

        const range = response?.data?.dateRange;
        heatmapDateRangeLabel.value = range
            ? `${range.period_label || ''}${range.formatted ? ' · ' + range.formatted : ''}`.trim()
            : '';

        if (range) {
            heatmapWeekStart.value = range.start_date;
            heatmapWeekEnd.value = range.end_date;
            // Sinkronkan dropdown dengan periode cutoff yang dikembalikan backend.
            if (range.cutoff_year && range.cutoff_month) {
                heatmapCutoffMonth.value = {
                    year: Number(range.cutoff_year),
                    month: Number(range.cutoff_month),
                };
            }
        }

        heatmapSectionLoaded.value = true;
    } catch (error) {
        if (isCanceledRequest(error) || !requestLifecycle.isLatest(requestKey, version)) return;
        console.error('Failed to load heatmap', error);
        heatmapSectionData.value = [];
        heatmapSectionError.value = 'Gagal memuat heatmap issue tim.';
    } finally {
        if (requestLifecycle.isLatest(requestKey, version)) {
            heatmapSectionLoading.value = false;
            requestLifecycle.finish(requestKey, version);
        }
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
    const requestKey = 'training';
    const { signal, version } = requestLifecycle.start(requestKey);

    trainingSectionRequested.value = true;
    trainingSectionLoading.value = true;
    trainingSectionError.value = '';

    try {
        const response = await axios.get('/homepage/leader-dashboard/training-pending', {
            signal,
            params: {
                page: targetPage,
                per_page: trainingSectionPagination.value.per_page || 6,
            },
        });

        if (!requestLifecycle.isLatest(requestKey, version)) return;
        trainingSectionData.value = Array.isArray(response?.data?.data) ? response.data.data : [];
        trainingSectionPagination.value = response?.data?.pagination || trainingSectionPagination.value;
        trainingSectionLoaded.value = true;
    } catch (error) {
        if (isCanceledRequest(error) || !requestLifecycle.isLatest(requestKey, version)) return;
        console.error('Failed to load training pending', error);
        trainingSectionData.value = [];
        trainingSectionError.value = 'Gagal memuat training pending.';
    } finally {
        if (requestLifecycle.isLatest(requestKey, version)) {
            trainingSectionLoading.value = false;
            requestLifecycle.finish(requestKey, version);
        }
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

function getTeamCalendarInitialPeriod() {
    const periodSource = props.overview?.period || props.period || {};
    const cutoffStart = String(periodSource.cutoff_start || periodSource.cutoffStart || '').slice(0, 10);
    const sourceDate = cutoffStart ? new Date(`${cutoffStart}T00:00:00`) : new Date();
    const validDate = Number.isNaN(sourceDate.getTime()) ? new Date() : sourceDate;

    return {
        year: validDate.getFullYear(),
        month: validDate.getMonth() + 1,
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
    const defaultPeriod = getTeamCalendarInitialPeriod();
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

function attachHeavySectionObserver() {
    const sections = [
        [shiftSectionRef.value, ensureShiftSchedulesLoaded],
        [teamListSectionRef.value, ensureMembersLoaded],
        [teamCalendarPanelRef.value, ensureTeamCalendarMonthLoaded],
        // Antrian Approval kini ON-CLICK (bukan scroll-lazy) — card sudah punya
        // @click.capture="ensureApprovalsLoaded" + placeholder "Klik untuk memuat approval".
        [heatmapSectionRef.value, ensureHeatmapLoaded],
        // Training Pending (Learning) kini ON-CLICK (bukan scroll-lazy) — card sudah
        // punya @click.capture="ensureTrainingLoaded" + placeholder module-reveal.
    ].filter(([element]) => Boolean(element));

    if (!sections.length) return;

    if (typeof window === 'undefined' || typeof IntersectionObserver === 'undefined') {
        sections.forEach(([, loader]) => void loader());
        return;
    }

    heavySectionObserver.value?.disconnect?.();
    const loaders = new Map(sections);
    heavySectionObserver.value = new IntersectionObserver(
        (entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                const loader = loaders.get(entry.target);
                if (loader) void loader();
                observer.unobserve(entry.target);
            });
        },
        {
            root: null,
            rootMargin: '360px 0px',
            threshold: 0.01,
        },
    );

    sections.forEach(([element]) => heavySectionObserver.value.observe(element));
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

/* ============================================================================
 * HCIS Breakdown Drawers (Drawer A: periode cutoff, Drawer B: hari ini)
 * Semua lazy-load — card HCIS tetap ringan. 3 endpoint:
 *  - /hcis/period-breakdown        (Drawer A)
 *  - /hcis/today-breakdown         (Drawer B)
 *  - /hcis/member-category-dates   (accordion, dipakai A & B)
 * ========================================================================== */

const HCIS_CATEGORY_LABELS = {
    absent: 'Tidak Hadir',
    'no-in-out': 'No Check-In / Check-Out',
    'late-early': 'Terlambat / Pulang Cepat',
    issue: 'Issue Absensi',
    'izin-cuti': 'Izin / Cuti / Sakit',
};
const HCIS_CATEGORY_TONES = {
    absent: 'danger',
    'no-in-out': 'danger',
    'late-early': 'warning',
    issue: 'danger',
    'izin-cuti': 'info',
};

const MONTHS_ID = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

function formatShortDate(value) {
    const raw = String(value || '').trim();
    const m = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!m) {
        return raw;
    }
    return `${Number(m[3])} ${MONTHS_ID[Number(m[2]) - 1] || ''} ${m[1]}`;
}

// Ringkas daftar tanggal jadi "..., 21 Jun 2026, +3" (tampilkan 1 terakhir + sisa).
function summarizeDates(dates) {
    const list = Array.isArray(dates) ? dates.filter(Boolean) : [];
    if (!list.length) {
        return '-';
    }
    const sorted = [...list].sort();
    const last = formatShortDate(sorted[sorted.length - 1]);
    const remaining = sorted.length - 1;
    if (remaining <= 0) {
        return last;
    }
    return `…, ${last}, +${remaining}`;
}

// ---- Drawer A: state ----
const hcisPeriodDrawerOpen = ref(false);
const hcisPeriodLoading = ref(false);
const hcisPeriodError = ref('');
const hcisPeriodCategory = ref('issue');
const hcisPeriodRows = shallowRef([]);
const hcisPeriodMeta = shallowRef({});
const hcisPeriodPagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    has_prev: false,
    has_next: false,
});
const hcisPeriodController = ref(null);
const hcisPeriodRequestingKey = ref('');
const hcisPeriodCache = shallowRef({});

const hcisPeriodCategoryLabel = computed(() => HCIS_CATEGORY_LABELS[hcisPeriodCategory.value] || 'Detail HCIS');
const hcisCategoryTone = computed(() => HCIS_CATEGORY_TONES[hcisPeriodCategory.value] || 'default');

// ---- Accordion (per-anggota) state — dipakai Drawer A & B ----
const hcisAccordionOpenKey = ref('');
const hcisAccordionLoading = ref(false);
const hcisAccordionError = ref('');
const hcisAccordionRows = shallowRef([]);
const hcisAccordionController = ref(null);
const hcisAccordionContext = ref({ kode: '', category: '', scope: 'period' });

function getHcisPeriodCacheKey(category, page) {
    return `${category}|${page}`;
}

async function loadHcisPeriodBreakdown(category, page = 1) {
    const cat = String(category || 'issue').trim();
    const targetPage = Math.max(1, Number(page || 1));
    const cacheKey = getHcisPeriodCacheKey(cat, targetPage);

    hcisPeriodDrawerOpen.value = true;
    hcisPeriodCategory.value = cat;
    hcisPeriodError.value = '';

    const cached = hcisPeriodCache.value[cacheKey];
    if (cached) {
        hcisPeriodRows.value = cached.data || [];
        hcisPeriodPagination.value = cached.pagination || hcisPeriodPagination.value;
        hcisPeriodMeta.value = cached.meta || hcisPeriodMeta.value;
        return;
    }

    if (hcisPeriodController.value) {
        hcisPeriodController.value.abort();
    }
    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    hcisPeriodController.value = controller;

    hcisPeriodLoading.value = true;
    hcisPeriodRequestingKey.value = cacheKey;
    hcisPeriodRows.value = [];

    try {
        const response = await axios.get('/homepage/leader-dashboard/hcis/period-breakdown', {
            signal: controller?.signal,
            params: {
                category: cat,
                page: targetPage,
                per_page: hcisPeriodPagination.value.per_page || 10,
            },
        });

        const payload = {
            data: Array.isArray(response?.data?.data) ? response.data.data : [],
            pagination: response?.data?.pagination || hcisPeriodPagination.value,
            meta: response?.data?.meta || {},
        };

        hcisPeriodCache.value = { ...hcisPeriodCache.value, [cacheKey]: payload };

        if (hcisPeriodRequestingKey.value !== cacheKey) {
            return;
        }
        hcisPeriodRows.value = payload.data;
        hcisPeriodPagination.value = payload.pagination;
        hcisPeriodMeta.value = payload.meta;
    } catch (error) {
        if (isCanceledRequest(error)) {
            return;
        }
        if (hcisPeriodRequestingKey.value !== cacheKey) {
            return;
        }
        console.error('Failed to load HCIS period breakdown', error);
        hcisPeriodError.value = 'Gagal memuat data. Silakan coba lagi.';
        hcisPeriodRows.value = [];
    } finally {
        if (hcisPeriodRequestingKey.value === cacheKey) {
            hcisPeriodLoading.value = false;
            hcisPeriodRequestingKey.value = '';
        }
    }
}

function openHcisPeriodDrawer(category) {
    // Reset accordion saat ganti kategori.
    hcisAccordionOpenKey.value = '';
    hcisAccordionRows.value = [];
    void loadHcisPeriodBreakdown(category, 1);
}

function closeHcisPeriodDrawer() {
    hcisPeriodDrawerOpen.value = false;
    hcisAccordionOpenKey.value = '';
}

function retryHcisPeriodLoad() {
    void loadHcisPeriodBreakdown(hcisPeriodCategory.value, Number(hcisPeriodPagination.value.current_page || 1));
}

function goToHcisPeriodPage(step) {
    if (!hcisPeriodDrawerOpen.value) {
        return;
    }
    const currentPage = Number(hcisPeriodPagination.value.current_page || 1);
    const nextPage = Math.max(1, currentPage + Number(step || 0));
    if (nextPage === currentPage) {
        return;
    }
    hcisAccordionOpenKey.value = '';
    void loadHcisPeriodBreakdown(hcisPeriodCategory.value, nextPage);
}

// ---- Accordion loader (lazy, request setiap dibuka) ----
async function loadHcisMemberDates(kode, category, scope) {
    hcisAccordionContext.value = { kode, category, scope };
    hcisAccordionError.value = '';
    hcisAccordionRows.value = [];

    if (hcisAccordionController.value) {
        hcisAccordionController.value.abort();
    }
    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    hcisAccordionController.value = controller;
    hcisAccordionLoading.value = true;

    try {
        const response = await axios.get('/homepage/leader-dashboard/hcis/member-category-dates', {
            signal: controller?.signal,
            params: { kode, category, scope },
        });
        // Pastikan accordion yang diminta masih yang terbuka.
        if (hcisAccordionContext.value.kode !== kode || hcisAccordionContext.value.category !== category) {
            return;
        }
        hcisAccordionRows.value = Array.isArray(response?.data?.data) ? response.data.data : [];
    } catch (error) {
        if (isCanceledRequest(error)) {
            return;
        }
        console.error('Failed to load HCIS member category dates', error);
        hcisAccordionError.value = 'Gagal memuat detail. Coba lagi.';
        hcisAccordionRows.value = [];
    } finally {
        hcisAccordionLoading.value = false;
    }
}

function toggleHcisMemberAccordion(item, scope) {
    const id = String(item?.id || '');
    if (!id) {
        return;
    }
    if (hcisAccordionOpenKey.value === id) {
        hcisAccordionOpenKey.value = '';
        return;
    }
    hcisAccordionOpenKey.value = id;
    void loadHcisMemberDates(id, hcisPeriodCategory.value, scope || 'period');
}

function reloadHcisAccordion() {
    const ctx = hcisAccordionContext.value;
    if (!ctx.kode) {
        return;
    }
    void loadHcisMemberDates(ctx.kode, ctx.category, ctx.scope);
}

// ---- Drawer B: state ----
const hcisTodayDrawerOpen = ref(false);
const hcisTodayLoading = ref(false);
const hcisTodayError = ref('');
const hcisTodayData = shallowRef({ stats: {}, working_total: 0 });
const hcisTodayMeta = shallowRef({});
const hcisTodayController = ref(null);
const hcisTodayLoaded = ref(false);

// Stat hari ini yang punya `category` bisa di-expand jadi list anggota (scope=today).
const hcisTodayOpenKey = ref('');
const hcisTodayMembersLoading = ref(false);
const hcisTodayMembersError = ref('');
const hcisTodayMemberRows = shallowRef([]);
const hcisTodayMembersController = ref(null);

const hcisTodayStats = computed(() => {
    const s = hcisTodayData.value.stats || {};
    const total = Number(hcisTodayData.value.working_total || 0);
    const n = (v) => Number(v || 0);
    // Tiap stat punya `statusKey` → semua bisa di-expand jadi list anggota (scope hari ini).
    return [
        {
            key: 'present',
            statusKey: 'present',
            label: 'Hadir',
            caption: 'on-time + terlambat + pulang cepat',
            displayValue: `${n(s.present)}/${total}`,
            tone: n(s.present) > 0 ? 'success' : 'secondary',
        },
        {
            key: 'on-time',
            statusKey: 'on-time',
            label: 'On-time',
            caption: 'tepat waktu',
            displayValue: n(s['on-time']),
            tone: 'success',
        },
        {
            key: 'late',
            statusKey: 'late',
            label: 'Terlambat',
            caption: 'check-in melewati jam masuk',
            displayValue: n(s.late),
            tone: n(s.late) > 0 ? 'warning' : 'secondary',
        },
        {
            key: 'early-out',
            statusKey: 'early-out',
            label: 'Pulang Cepat',
            caption: 'pulang sebelum jam selesai',
            displayValue: n(s['early-out']),
            tone: n(s['early-out']) > 0 ? 'warning' : 'secondary',
        },
        {
            key: 'pending-checkin',
            statusKey: 'pending-checkin',
            label: 'Menunggu Check-In',
            caption: 'belum masuk jam kerja',
            displayValue: n(s['pending-checkin']),
            tone: n(s['pending-checkin']) > 0 ? 'secondary' : 'success',
        },
        {
            key: 'working',
            statusKey: 'working',
            label: 'Sedang Bekerja',
            caption: 'sudah check-in, belum check-out',
            displayValue: n(s.working),
            tone: n(s.working) > 0 ? 'info' : 'secondary',
        },
        {
            key: 'absent',
            statusKey: 'absent',
            label: 'Tidak Hadir / Alpha',
            caption: 'tanpa check-in & check-out',
            displayValue: n(s.absent),
            tone: n(s.absent) > 0 ? 'danger' : 'success',
        },
        {
            key: 'no-in-out',
            statusKey: 'no-in-out',
            label: 'Tidak Check-In / Out',
            caption: 'check-in atau check-out belum lengkap',
            displayValue: n(s['no-in-out']),
            tone: n(s['no-in-out']) > 0 ? 'danger' : 'success',
        },
    ];
});

async function loadHcisTodayBreakdown(force = false) {
    hcisTodayDrawerOpen.value = true;
    hcisTodayError.value = '';

    if (hcisTodayLoaded.value && !force) {
        return;
    }

    if (hcisTodayController.value) {
        hcisTodayController.value.abort();
    }
    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    hcisTodayController.value = controller;
    hcisTodayLoading.value = true;

    try {
        const response = await axios.get('/homepage/leader-dashboard/hcis/today-breakdown', {
            signal: controller?.signal,
        });
        hcisTodayData.value = response?.data?.data || { stats: {}, working_total: 0 };
        hcisTodayMeta.value = response?.data?.meta || {};
        hcisTodayLoaded.value = true;
    } catch (error) {
        if (isCanceledRequest(error)) {
            return;
        }
        console.error('Failed to load HCIS today breakdown', error);
        hcisTodayError.value = 'Gagal memuat status hari ini. Silakan coba lagi.';
    } finally {
        hcisTodayLoading.value = false;
    }
}

function openHcisTodayDrawer() {
    hcisTodayOpenKey.value = '';
    hcisTodayMemberRows.value = [];
    void loadHcisTodayBreakdown(false);
}

function closeHcisTodayDrawer() {
    hcisTodayDrawerOpen.value = false;
    hcisTodayOpenKey.value = '';
}

function retryHcisTodayLoad() {
    void loadHcisTodayBreakdown(true);
}

// Klik stat hari ini → expand list anggota berstatus tsb PADA HARI INI (lazy fetch).
async function loadHcisTodayMembers(statusKey) {
    hcisTodayMembersError.value = '';
    hcisTodayMemberRows.value = [];

    if (hcisTodayMembersController.value) {
        hcisTodayMembersController.value.abort();
    }
    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    hcisTodayMembersController.value = controller;
    hcisTodayMembersLoading.value = true;

    try {
        const response = await axios.get('/homepage/leader-dashboard/hcis/today-members', {
            signal: controller?.signal,
            params: { status: statusKey },
        });
        hcisTodayMemberRows.value = Array.isArray(response?.data?.data) ? response.data.data : [];
    } catch (error) {
        if (isCanceledRequest(error)) {
            return;
        }
        console.error('Failed to load HCIS today members', error);
        hcisTodayMembersError.value = 'Gagal memuat anggota. Coba lagi.';
        hcisTodayMemberRows.value = [];
    } finally {
        hcisTodayMembersLoading.value = false;
    }
}

function toggleHcisTodayStat(stat) {
    if (!stat?.statusKey) {
        return;
    }
    if (hcisTodayOpenKey.value === stat.key) {
        hcisTodayOpenKey.value = '';
        return;
    }
    hcisTodayOpenKey.value = stat.key;
    void loadHcisTodayMembers(stat.statusKey);
}

function reloadHcisTodayMembers() {
    const stat = hcisTodayStats.value.find((s) => s.key === hcisTodayOpenKey.value);
    if (stat?.statusKey) {
        void loadHcisTodayMembers(stat.statusKey);
    }
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

// Pemilihan periode cutoff lewat dropdown (Prev/Next dihapus — cukup select).
function onHeatmapMonthChange(event) {
    const [year, month] = String(event?.target?.value || '')
        .split('-')
        .map((v) => Number(v));
    if (!year || !month) {
        return;
    }
    heatmapCutoffMonth.value = { year, month };
    void loadHeatmap(1);
}

function executeHeatmapSearch() {
    void loadHeatmap(heatmapSectionPagination.value.current_page);
}

let heatmapSearchTimeout = null;

function onHeatmapSearchInput() {
    if (heatmapSearchTimeout) {
        clearTimeout(heatmapSearchTimeout);
    }
    heatmapSearchTimeout = setTimeout(() => {
        executeHeatmapSearch();
    }, 800); // 800ms debounce
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
    // GELOMBANG 1 — inti above-the-fold: ringkasan global + antrian perlu-tindakan.
    // Di-await supaya tampil duluan; sisanya menyusul (queue membatasi paralelisme).
    await Promise.allSettled([loadGlobalSummary(), loadNeedsAction(1)]);

    // GELOMBANG 2 — HANYA HCIS (absensi = concern utama leader). KPI, LMS, dan Antrian
    // Approval kini ON-CLICK (reveal saat card dibuka) supaya kunjungan dashboard tidak
    // memicu query berat (union-9-view approval / LMS) untuk leader yang tak melihatnya.
    void Promise.allSettled([loadHcisSummaryDomain()]);
}

function handleHcisCardInteract() {
    void loadHcisSummaryDomain();
}

function handleKpiCardInteract() {
    kpiRevealed.value = true; // buka card → tampilkan konten + muat (idempoten via guard loader)
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
    kpiDomainError.value = '';
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
    const fallbackPeriod = buildKpiPeriodValue(normalizedYear, now.getMonth());

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
    console.log(memberLike, defaults);
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
        const label = new Date(`${value}-01`).toLocaleDateString('id-ID', { month: 'long' });
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

function normalizeMemberKpiParameters(source) {
    return (Array.isArray(source) ? source : []).map((item, index) => {
        const achievement = Number(item?.achievement ?? item?.score ?? 0);
        const weight = Number(item?.weight ?? item?.bobot ?? item?.Bobot ?? 0);

        return {
            id: item?.id || `${item?.name || item?.parameter || 'parameter'}-${index}`,
            name: item?.name || item?.parameter || 'Parameter KPI',
            score: Number(item?.score ?? achievement),
            achievement,
            weight,
            target: Number(item?.target ?? 0),
            actual: Number(item?.actual ?? 0),
            status:
                item?.status || item?.statusLabel || (item?.has_performance_update ? 'Terisi' : 'Belum ada penilaian'),
            statusColor: item?.status_color || item?.statusColor || '#7c3aed',
            formStatus: item?.form_status || item?.formStatus || '',
            hasPerformanceUpdate: Boolean(item?.has_performance_update ?? item?.hasPerformanceUpdate ?? false),
        };
    });
}

function mapKpiPanelViewFromDetail(detailData, baseKpi = {}, fallbackPeriod = '') {
    const summary = detailData?.summary || {};
    const trendRows = Array.isArray(detailData?.trend_12_months) ? detailData.trend_12_months : [];
    const parameterRows = Array.isArray(detailData?.parameter_rows) ? detailData.parameter_rows : [];
    const selectedPeriod = String(summary?.period || fallbackPeriod || '').trim();
    const score =
        summary?.selected_score !== undefined
            ? summary.selected_score === '-'
                ? '-'
                : Number(summary.selected_score)
            : 0;
    const periodLabel =
        summary?.period_label || (selectedPeriod ? `Periode ${kpiPanelPeriodLabel(selectedPeriod)}` : '-');

    return {
        year: Number(baseKpi?.year || selectedKpiYear.value || new Date().getFullYear()),
        selectedPeriod,
        score: summary?.selected_score ?? '-',
        grade: baseKpi?.grade || '',
        achievement: score,
        achievementGradeLabel:
            summary?.achievement_grade_label || baseKpi?.achievementGradeLabel || baseKpi?.grade || '-',
        achievementGradeColor: summary?.achievement_grade_color || baseKpi?.achievementGradeColor || '#94a3b8',
        ytdScore: Number(summary?.ytd_score ?? baseKpi?.ytdScore ?? 0),
        ytdSubmittedCount: Number(summary?.ytd_submitted_count ?? baseKpi?.ytdSubmittedCount ?? 0),
        ytdGradeLabel: summary?.ytd_grade_label || baseKpi?.ytdGradeLabel || '-',
        ytdGradeColor: summary?.ytd_grade_color || baseKpi?.ytdGradeColor || '#94a3b8',
        trendDirection: summary?.trend_direction || baseKpi?.trendDirection || '0%',
        parameterPeriod: periodLabel,
        performancePeriod: periodLabel,
        pendingItems: Array.isArray(baseKpi?.pendingItems) ? baseKpi.pendingItems : [],
        monthlyAchievement: trendRows.map((item) => ({
            month: String(item?.month || '').trim() || String(item?.period || '').trim() || '-',
            value: Number(item?.score || 0),
        })),
        trend: trendRows.map((item) => ({
            month: String(item?.month || '').trim() || String(item?.period || '').trim() || '-',
            score: Number(item?.score || 0),
            value: Number(item?.score || 0),
        })),
        parameters: normalizeMemberKpiParameters(parameterRows),
        parameterScheduled: Boolean(summary?.form_scheduled),
        performanceScheduled: Boolean(summary?.cutoff_performance_scheduled),
        parameterApprovalStatus: String(summary?.parameter_approval_status || 'none'),
        performanceApprovalStatus: String(summary?.performance_approval_status || 'none'),
        performanceHasParameters: Boolean(summary?.performance_has_parameters),
        performanceHasDueTarget: summary?.performance_has_due_target ?? true,
        parameterSubmitted: Boolean(summary?.parameter_submitted),
        performanceSubmitted: Boolean(summary?.performance_submitted),
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
        score: baseKpi?.score ?? '-',
        grade: baseKpi?.grade || '',
        achievement: Number(baseKpi?.achievement || 0),
        achievementGradeLabel: baseKpi?.achievementGradeLabel || baseKpi?.grade || '-',
        achievementGradeColor: baseKpi?.achievementGradeColor || '#94a3b8',
        ytdScore: Number(baseKpi?.ytdScore || 0),
        ytdSubmittedCount: Number(baseKpi?.ytdSubmittedCount || 0),
        ytdGradeLabel: baseKpi?.ytdGradeLabel || '-',
        ytdGradeColor: baseKpi?.ytdGradeColor || '#94a3b8',
        trendDirection: baseKpi?.trendDirection || '0%',
        parameterPeriod: baseKpi?.parameterPeriod || `Periode ${kpiPanelPeriodLabel(state.appliedPeriod)}`,
        performancePeriod: baseKpi?.performancePeriod || `Periode ${kpiPanelPeriodLabel(state.appliedPeriod)}`,
        pendingItems: Array.isArray(baseKpi?.pendingItems) ? baseKpi.pendingItems : [],
        monthlyAchievement: Array.isArray(baseKpi?.monthlyAchievement) ? baseKpi.monthlyAchievement : [],
        trend:
            Array.isArray(baseKpi?.trend) && baseKpi.trend.length
                ? baseKpi.trend
                : Array.isArray(baseKpi?.monthlyAchievement)
                  ? baseKpi.monthlyAchievement.map((item) => ({
                        month: String(item?.month || '').trim() || String(item?.period || '').trim() || '-',
                        score: Number(item?.score ?? item?.value ?? 0),
                        value: Number(item?.score ?? item?.value ?? 0),
                    }))
                  : [],
        parameters: normalizeMemberKpiParameters(baseKpi?.parameters),
        parameterScheduled: Boolean(baseKpi?.parameterScheduled),
        performanceScheduled: Boolean(baseKpi?.performanceScheduled),
        parameterApprovalStatus: String(baseKpi?.parameterApprovalStatus || 'none'),
        performanceApprovalStatus: String(baseKpi?.performanceApprovalStatus || 'none'),
        performanceHasParameters: Boolean(baseKpi?.performanceHasParameters),
        performanceHasDueTarget: baseKpi?.performanceHasDueTarget ?? true,
        parameterSubmitted: Boolean(baseKpi?.parameterSubmitted),
        performanceSubmitted: Boolean(baseKpi?.performanceSubmitted),
    };
}

function getMemberKpiTrendBounds(member) {
    const view = memberKpiPanelView(member);
    const scores = (view.trend || []).map((item) => Number(item.score)).filter((score) => Number.isFinite(score));

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
}

function getMemberKpiChartSeries(member) {
    const view = memberKpiPanelView(member);
    return [
        {
            name: 'Skor KPI',
            data: (view.trend || []).map((item) => Number(item.score || 0)),
        },
    ];
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

// View LMS per member — disamakan persis dengan heroLms di personal dashboard (homepage.vue).
function memberLmsView(member) {
    const source = member?.lms || {};
    const hasData = Boolean(source.hasData ?? source.has_data ?? Number(source.total ?? 0) > 0);

    const completed = hasData ? Number(source.completed ?? 0) : 0;
    const total = hasData ? Number(source.total ?? 0) : 0;
    const progress = Number(source.progress ?? (total ? Math.round((completed / total) * 100) : 0));

    const durationMonthly = hasData ? (Array.isArray(source.durationMonthly) ? source.durationMonthly : []) : [];
    const learningHours = hasData ? Number(source.learningHours ?? 0) : 0;
    // Avg durasi per training yang sudah dilalui (dihitung di backend).
    const avgLearningDurationHours = hasData ? Number(source.avgDurationHours ?? 0) : 0;
    // Rata-rata durasi per bulan aktif — khusus header chart bulanan.
    const activeDurationRows = durationMonthly.filter((item) => Number(item.avg_hours ?? item.avgHours ?? 0) > 0);
    const avgMonthlyDurationHours = activeDurationRows.length
        ? activeDurationRows.reduce((sum, item) => sum + Number(item.avg_hours ?? item.avgHours ?? 0), 0) /
          activeDurationRows.length
        : 0;

    return {
        hasData,
        completed,
        total,
        progress,
        progressLabel: total ? `${progress}%` : '-',
        averageScore: source.averageScore ?? null,
        learningGain: source.learningGain ?? null,
        learningHours,
        avgLearningDurationHours,
        avgLearningDurationLabel: formatLearningDurationLabel(avgLearningDurationHours),
        avgMonthlyDurationHours,
        avgMonthlyDurationLabel: formatLearningDurationLabel(avgMonthlyDurationHours),
        attemptedCourses: hasData ? Number(source.attemptedCourses ?? 0) : 0,
        nextSession: source.nextSession || 'Tidak ada training aktif',
        nextSessionDate: source.nextSessionDate || '-',
        durationMonthly,
        progressList: hasData ? (Array.isArray(source.progressList) ? source.progressList : []) : [],
    };
}

function getMemberLmsChartSeries(member) {
    const view = memberLmsView(member);
    return [
        {
            name: 'Avg Durasi',
            data: view.durationMonthly.map((item) => Number(item.avg_hours ?? item.avgHours ?? 0)),
        },
    ];
}

function getMemberLmsChartOptions(member) {
    const view = memberLmsView(member);
    return {
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
            categories: view.durationMonthly.map((item) => item.month || item.period || '-'),
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
    };
}

function getMemberKpiChartOptions(member) {
    const view = memberKpiPanelView(member);
    const bounds = getMemberKpiTrendBounds(member);
    return {
        chart: {
            type: 'area',
            toolbar: { show: false },
            zoom: { enabled: false },
            sparkline: { enabled: false },
            fontFamily: 'inherit',
        },
        colors: ['#7c3aed'],
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
            categories: (view.trend || []).map((item) => item.month || '-'),
            labels: {
                show: true,
                style: { fontSize: '10px', fontWeight: 700 },
            },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            min: bounds.min,
            max: bounds.max,
            labels: { show: false },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        tooltip: {
            theme: 'dark',
            x: {
                formatter: (_, opts) => (view.trend || [])[opts.dataPointIndex]?.month || '',
            },
            y: {
                formatter: (val) => `${val}%`,
            },
        },
    };
}

async function handleMemberKpiYearChange(member, yearValue) {
    setMemberKpiDraftYear(member, yearValue);
    await applyMemberKpiPanelFilter(member);
}

async function handleMemberKpiPeriodChange(member, periodValue) {
    setMemberKpiDraftPeriod(member, periodValue);
    await applyMemberKpiPanelFilter(member);
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
    const requestKey = 'kpi-drawer';
    const { signal, version } = requestLifecycle.start(requestKey);

    try {
        const response = await axios.get('/homepage/leader-dashboard/kpi-details', {
            signal,
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

        if (!requestLifecycle.isLatest(requestKey, version)) return;
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
        if (isCanceledRequest(error) || !requestLifecycle.isLatest(requestKey, version)) return;
        console.error('Failed to load KPI detail rows', error);
        kpiDetailError.value = 'Gagal memuat detail KPI. Silakan coba lagi.';
        kpiDetailRows.value = [];
        kpiDetailOpenedMemberId.value = '';
    } finally {
        if (requestLifecycle.isLatest(requestKey, version)) {
            kpiDetailLoading.value = false;
            requestLifecycle.finish(requestKey, version);
        }
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

function getParameterStatusLabel(item) {
    const isNewJoiner = Boolean(item?.is_new_joiner ?? item?.isNewJoiner);
    if (isNewJoiner) return 'Baru bergabung';
    const isScheduled = Boolean(item?.parameterScheduled ?? item?.form_scheduled ?? false);
    if (!isScheduled) return 'Belum Dijadwalkan';
    const approval = String(item?.parameterApprovalStatus ?? item?.parameter_approval_status ?? 'none');
    if (approval === 'approved') return 'Sudah Disetujui ✓';
    if (approval === 'rejected') return 'Ditolak, Revisi';
    if (approval === 'pending') return 'Menunggu Approval';
    const submitted = Boolean(item?.parameterSubmitted ?? item?.parameter_submitted ?? item?.form_submitted ?? false);
    if (submitted) return 'Sudah Mengisi';
    return 'Belum Mengisi';
}

function getParameterStatusTone(item) {
    const isNewJoiner = Boolean(item?.is_new_joiner ?? item?.isNewJoiner);
    if (isNewJoiner) return 'is-secondary';
    const isScheduled = Boolean(item?.parameterScheduled ?? item?.form_scheduled ?? false);
    if (!isScheduled) return 'is-secondary';
    const approval = String(item?.parameterApprovalStatus ?? item?.parameter_approval_status ?? 'none');
    if (approval === 'approved') return 'is-success';
    if (approval === 'rejected') return 'is-danger';
    if (approval === 'pending') return 'is-info';
    const submitted = Boolean(item?.parameterSubmitted ?? item?.parameter_submitted ?? item?.form_submitted ?? false);
    if (submitted) return 'is-success';
    return 'is-warning';
}

function getPerformanceStatusLabel(item) {
    const isNewJoiner = Boolean(item?.is_new_joiner ?? item?.isNewJoiner);
    if (isNewJoiner) return 'Baru bergabung';
    const isScheduled = Boolean(item?.performanceScheduled ?? item?.cutoff_performance_scheduled ?? false);
    if (!isScheduled) return 'Belum Dijadwalkan';
    const approval = String(item?.performanceApprovalStatus ?? item?.performance_approval_status ?? 'none');
    const hasParams = Boolean(item?.performanceHasParameters ?? item?.performance_has_parameters ?? false);
    if (approval === 'approved') return 'Sudah Mengisi ✓';
    if (approval === 'rejected') return 'Ditolak, Revisi';
    if (approval === 'pending') return 'Menunggu Approval';
    const submitted = Boolean(item?.performanceSubmitted ?? item?.performance_submitted ?? false);
    if (submitted) return 'Sudah Mengisi';
    if (!hasParams) return 'Tidak Ada Parameter';
    // Punya parameter, tetapi tak ada target jatuh tempo di periode ini.
    if (!punyaTargetJatuhTempo(item)) return 'Tidak Ada Target';
    return 'Belum Mengisi';
}

function punyaTargetJatuhTempo(item) {
    return Boolean(item?.performanceHasDueTarget ?? item?.performance_has_due_target ?? true);
}

function getPerformanceStatusTone(item) {
    const isNewJoiner = Boolean(item?.is_new_joiner ?? item?.isNewJoiner);
    if (isNewJoiner) return 'is-secondary';
    const isScheduled = Boolean(item?.performanceScheduled ?? item?.cutoff_performance_scheduled ?? false);
    if (!isScheduled) return 'is-secondary';
    const approval = String(item?.performanceApprovalStatus ?? item?.performance_approval_status ?? 'none');
    const hasParams = Boolean(item?.performanceHasParameters ?? item?.performance_has_parameters ?? false);
    if (approval === 'approved') return 'is-success';
    if (approval === 'rejected') return 'is-danger';
    if (approval === 'pending') return 'is-info';
    const submitted = Boolean(item?.performanceSubmitted ?? item?.performance_submitted ?? false);
    if (submitted) return 'is-success';
    if (!hasParams) return 'is-secondary';
    if (!punyaTargetJatuhTempo(item)) return 'is-secondary';
    return 'is-warning';
}

function kpiFormCount(item) {
    return Number(item?.form_kpi_count ?? item?.form_kpi?.length ?? 0);
}

function kpiPerformanceCount(item) {
    return Number(item?.performance_kpi_count ?? item?.performance_kpi?.length ?? 0);
}

function handleLmsCardInteract() {
    lmsRevealed.value = true; // buka card → tampilkan konten + muat (idempoten via guard loader)
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
    const requestKey = 'lms-drawer';
    const { signal, version } = requestLifecycle.start(requestKey);

    try {
        const response = await axios.get('/homepage/leader-dashboard/lms-team-members', {
            signal,
            params: {
                year: normalizeLmsYear(selectedLmsYear.value),
                focus,
                search: search || undefined,
                page: targetPage,
                per_page: lmsDetailPagination.value.per_page || 10,
            },
        });

        if (!requestLifecycle.isLatest(requestKey, version)) return;
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
        if (isCanceledRequest(error) || !requestLifecycle.isLatest(requestKey, version)) return;
        console.error('Failed to load LMS drawer rows', error);
        lmsDetailError.value = 'Gagal memuat detail LMS. Silakan coba lagi.';
        lmsDetailRows.value = [];
        lmsDetailOpenedMemberId.value = '';
    } finally {
        if (requestLifecycle.isLatest(requestKey, version)) {
            lmsDetailLoading.value = false;
            requestLifecycle.finish(requestKey, version);
        }
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

function closeMemberSourceDrawer(source = '') {
    const normalizedSource = String(source || '')
        .trim()
        .toLowerCase();

    if (normalizedSource === 'calendar-day-detail') {
        teamCalendarDayDetailDrawerOpen.value = false;
        return;
    }
    if (normalizedSource === 'attendance-status') {
        attendanceStatusDrawerOpen.value = false;
        return;
    }

    attendanceIssueDrawerOpen.value = false;
}

function openMemberFromDrawer(memberLike, source = 'attendance-issue') {
    const memberId = normalizeMemberDetailMemberId(
        memberLike?.id || memberLike?.memberId || memberLike?.member_id || memberLike?.kode_karyawan || '',
    );

    if (!memberId) {
        return;
    }

    const memberName = String(memberLike?.name || memberLike?.memberName || memberLike?.member_name || '').trim();
    closeMemberSourceDrawer(source);

    void openAction({
        id: `${String(source || 'drawer')
            .trim()
            .toLowerCase()}-${memberId}`,
        memberId,
        memberName,
        title: memberName ? `${memberName} - Detail absensi` : `${memberId} - Detail absensi`,
    });
}

function openMemberFromHeatmap(member) {
    openMemberFromDrawer(member, 'heatmap');
}

function handleIssuePillClick(memberLike) {
    openMemberFromDrawer(memberLike, 'attendance-issue');
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

    const point = hcisDailyTrend.value[index] || {};
    // Pakai tanggal eksplisit dari backend bila tersedia (anti off-by-one).
    // Fallback ke penebakan by-label hanya untuk data trend lama tanpa `date`.
    const targetDate = point.date || resolveTrendDateByLabel(point.label || '', index);
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
        memberSearchPending.value = false;
        return;
    }

    if (memberSearchDebounce) {
        clearTimeout(memberSearchDebounce);
    }

    memberSearchPending.value = true;
    memberSearchDebounce = setTimeout(async () => {
        if (!memberSectionRequested.value && !memberSectionLoaded.value) {
            memberSearchPending.value = false;
            return;
        }

        try {
            await loadMembers(1);
        } finally {
            memberSearchPending.value = false;
        }
    }, 250);
});

watch([selectedAttendance, selectedKpi, selectedLms], () => {
    if (!memberSectionRequested.value && !memberSectionLoaded.value) {
        return;
    }

    void loadMembers(1);
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

let lastDrawerTrigger = null;

watch(
    () =>
        Boolean(
            teamCalendarDayDetailDrawerOpen.value ||
            attendanceIssueDrawerOpen.value ||
            attendanceStatusDrawerOpen.value ||
            kpiDetailDrawerOpen.value ||
            lmsDetailDrawerOpen.value,
        ),
    async (isOpen, wasOpen) => {
        if (isOpen && !wasOpen) {
            lastDrawerTrigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            await nextTick();
            document.querySelector('.attendance-issue-drawer.is-open')?.focus?.();
            return;
        }

        if (!isOpen && wasOpen) {
            await nextTick();
            lastDrawerTrigger?.focus?.();
            lastDrawerTrigger = null;
        }
    },
);

onMounted(() => {
    loadReadActionIds();
    void bootstrapLeaderDashboardParallel();
    nextTick(() => attachHeavySectionObserver());
    autoStartTourIfFirstTime();
});

onBeforeUnmount(() => {
    autoStartTourDibatalkan = true;
    clearNeedsActionSyncRefetch();
    if (memberSearchDebounce) {
        clearTimeout(memberSearchDebounce);
        memberSearchPending.value = false;
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
    heavySectionObserver.value?.disconnect?.();
    requestLifecycle.cancelAll();
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
    shiftDetailError.value = '';

    if (shiftDetailCache.value[cacheKey]) {
        selectedShiftDetailData.value = shiftDetailCache.value[cacheKey];
        shiftDetailPagination.value = shiftDetailCache.value[cacheKey].pagination || shiftDetailPagination.value;
        return;
    }

    shiftDetailLoading.value = true;
    const requestKey = 'shift-detail';
    const { signal, version } = requestLifecycle.start(requestKey);

    try {
        const response = await axios.get('/homepage/leader-dashboard/shifts', {
            signal,
            params: {
                date: targetDate,
                shift_name: targetShift.name,
                page,
                per_page: shiftDetailPagination.value.per_page || 8,
            },
        });

        if (!requestLifecycle.isLatest(requestKey, version)) return;
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
        if (isCanceledRequest(error) || !requestLifecycle.isLatest(requestKey, version)) return;
        console.error('Failed to load shift detail', error);
        selectedShiftDetailData.value = null;
        shiftDetailError.value = 'Gagal memuat detail shift.';
    } finally {
        if (requestLifecycle.isLatest(requestKey, version)) {
            shiftDetailLoading.value = false;
            requestLifecycle.finish(requestKey, version);
        }
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

    if (!cacheKey) return null;
    if (memberDetailCache.value[cacheKey]) {
        return memberDetailCache.value[cacheKey];
    }
    if (memberDetailLoadingId.value === cacheKey) return null;

    memberDetailLoadingId.value = cacheKey;
    const startedAt = Date.now();
    let detailPayload = null;

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
            detailPayload = response.data.data;
        }
    } catch (error) {
        console.error('Failed to load member detail', error);
        memberSectionError.value = 'Gagal memuat detail anggota.';
    } finally {
        const elapsed = Date.now() - startedAt;
        if (elapsed < MEMBER_DETAIL_MIN_LOADING_MS) {
            await new Promise((resolve) => setTimeout(resolve, MEMBER_DETAIL_MIN_LOADING_MS - elapsed));
        }

        if (memberDetailLoadingId.value === cacheKey) {
            memberDetailLoadingId.value = '';
        }
    }

    return detailPayload;
}

async function loadMemberDetailBundle(memberId, options = {}) {
    const normalizedMemberId = normalizeMemberDetailMemberId(memberId);
    if (!normalizedMemberId) {
        return null;
    }

    const includeCalendar = options?.includeCalendar !== false;
    const tasks = [fetchMemberDetail(normalizedMemberId)];
    if (includeCalendar) {
        tasks.push(loadMemberWeeklyCalendar(normalizedMemberId));
    }

    const [detailPayload] = await Promise.all(tasks);
    return detailPayload;
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
        await loadMemberDetailBundle(id);
    }
}

async function openFirstTeamMemberForTour() {
    mobileLeaderPanelState.value.members = true;
    memberSectionRequested.value = true;
    await ensureMembersLoaded();
    await nextTick();

    const firstMember = filteredMembers.value[0] || members.value[0];
    if (!firstMember?.id) {
        return;
    }

    if (openedMemberId.value !== firstMember.id) {
        openedMemberId.value = firstMember.id;
        initializeMemberKpiPanelState(firstMember.id, firstMember);
        await loadMemberDetailBundle(firstMember.id);
    }

    await nextTick();
}

// Card Antrian Approval: arahkan leader ke halaman detail transaksi
// dengan ?no_transaksi=... agar bisa langsung approve pengajuan timnya.
// URL di-resolve oleh backend (resolveApprovalUrl) dengan pola yang sama
// seperti card Pengajuan Saya di homepage.vue.
function openApprovalQueue(url, id) {
    console.log('Opening approval queue for id', url, id);
    router.visit(url || '/homepage');
}

function resolveActionCenterUrl(item) {
    const noTransaksi = String(item?.no_transaksi || '').trim();
    if (!noTransaksi) return null;

    const prefix = noTransaksi.substring(0, 2).toUpperCase();
    const encoded = encodeURIComponent(noTransaksi);

    // Izin / Cuti / Sakit / Terlambat / Pulang Cepat
    if (['CT', 'IS', 'IP', 'TP', 'TL', 'PC'].includes(prefix)) {
        return `/izinLevelUp?no_transaksi=${encoded}`;
    }
    // Shift / Tukar Hari / Status Hari / Konversi Lembur
    if (['SH', 'TH', 'TS', 'KL'].includes(prefix)) {
        return `/shift-approval?no_transaksi=${encoded}`;
    }
    // KPI Parameter (KPI, PKPI)
    if (['KP', 'PK'].includes(prefix)) {
        return `/kpi-approval?no_transaksi=${encoded}`;
    }
    // KPI Performance (AKPI, TAKPI)
    if (prefix === 'AK' || prefix === 'TA') {
        return `/performance-approval?no_transaksi=${encoded}`;
    }
    // Lembur
    if (prefix === 'LM') {
        return `/lembur-management?no_transaksi=${encoded}`;
    }
    return null;
}

async function openAction(action) {
    if (needsActionNavigationLoading.value) {
        return;
    }

    // Tandai item sebagai sudah dibaca (abu-abu + turun ke bawah).
    markActionRead(action);

    const targetActionId = String(action?.id || '').trim();
    needsActionNavigationLoading.value = true;
    actionNavigationTargetId.value = targetActionId;

    try {
        // Approval items: langsung navigasi ke URL approval (leader sebagai eksekutor)
        const category = String(action?.category || '').toLowerCase();
        const actionUrl = action?.url || null;
        if (category === 'approval' && actionUrl) {
            window.open(actionUrl, '_blank');
            needsActionNavigationLoading.value = false;
            return;
        }

        const memberId = action?.memberId || action?.member_id || '';

        if (isMobileViewport.value) {
            mobileLeaderPanelState.value.members = true;
        }

        if (!memberId) {
            memberSectionRequested.value = true;
            searchKeyword.value = '';
            await ensureMembersLoaded();
            await nextTick();
            scrollToTeamListSection();
            return;
        }

        memberSectionRequested.value = true;
        await nextTick();
        scrollToTeamListSection();

        suppressMemberSearchWatch.value = true;
        if (memberSearchDebounce) {
            clearTimeout(memberSearchDebounce);
            memberSearchDebounce = null;
        }

        // Saat klik dari Action Center, isi search keyword dengan nama member
        const memberName = String(action?.title || '').split(' - ')[0] || '';
        searchKeyword.value = memberName.trim();
        selectedAttendance.value = 'all';
        selectedKpi.value = 'all';
        selectedLms.value = 'all';

        const normalizedActionMemberId = normalizeMemberDetailMemberId(memberId);
        const prefetchMemberBundlePromise = normalizedActionMemberId
            ? loadMemberDetailBundle(normalizedActionMemberId)
            : null;

        const located = await findMemberPageForAction(action);

        if (located) {
            memberSectionData.value = located.rows;
            memberSectionPagination.value = located.pagination;
            memberSectionLoaded.value = true;
            const targetMember = located.found || located.rows?.[0] || null;
            if (targetMember?.id) {
                const targetMemberId = normalizeMemberDetailMemberId(targetMember.id);
                openedMemberId.value = targetMemberId;
                if (prefetchMemberBundlePromise && targetMemberId === normalizedActionMemberId) {
                    await prefetchMemberBundlePromise;
                } else {
                    await loadMemberDetailBundle(targetMemberId);
                }
                return;
            }
        }

        // Fallback: tetap coba buka detail berdasarkan memberId bila hasil search belum menemukan baris yang presisi.
        const normalizedMemberId = normalizedActionMemberId;
        if (normalizedMemberId) {
            openedMemberId.value = normalizedMemberId;
            const detailPayload = prefetchMemberBundlePromise
                ? await prefetchMemberBundlePromise
                : await loadMemberDetailBundle(normalizedMemberId);

            if (detailPayload) {
                const syntheticRow = normalizeMember(detailPayload);
                const existingRows = Array.isArray(memberSectionData.value) ? memberSectionData.value : [];
                const mergedRows = [syntheticRow, ...existingRows.filter((row) => row?.id !== syntheticRow.id)];
                memberSectionData.value = mergedRows;
                memberSectionLoaded.value = true;
            } else {
                await ensureMembersLoaded();
            }
        }
    } catch (error) {
        console.error('Failed to locate member page from action center', error);
        memberSectionError.value = 'Gagal navigasi ke anggota. Silakan coba lagi.';
    } finally {
        suppressMemberSearchWatch.value = false;
        needsActionNavigationLoading.value = false;
        actionNavigationTargetId.value = '';
    }
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
        leave: 0,
        permission: 0,
        sick: 0,
        working: 0,
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
        leave: 0,
        permission: 0,
        sick: 0,
        working: 0,
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
            label: 'Validasi status absensi atau shift dihari tertentu',
            impact: 'Kurangi risiko absensi tidak tervalidasi shift salah atau tidak terbentuk.',
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
        'no-checkin': 'Tidak Check In',
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

// ── Weekly Calendar for Member Detail (Lazy-Load) ──

const memberWeeklyCalCache = shallowRef({});
const memberWeeklyCalLoadingId = ref('');

function getMemberCalendarCacheKey(memberId, weekDate) {
    return `${String(memberId || '')
        .trim()
        .toUpperCase()}|${weekDate || 'current'}`;
}

function getMemberCalendarData(memberId) {
    const key = String(memberId || '')
        .trim()
        .toUpperCase();
    // Find the active cache for this member — use the latest loaded week
    const weekDate = memberWeekOffsetMap.value[key] || null;
    const cacheKey = getMemberCalendarCacheKey(key, weekDate);
    return memberWeeklyCalCache.value[cacheKey] || null;
}

function getMemberCalendarDays(member) {
    const calData = getMemberCalendarData(member?.id);
    if (!calData?.days?.length) return [];

    const todayStr = new Date().toISOString().slice(0, 10);
    const labelMap = {
        Hadir: 'Hadir',
        Terlambat: 'Telat',
        'Tidak Hadir': 'Alpha',
        'Tidak Check In': 'No CI',
        'Belum Checkout': 'No CO',
        'Sedang Bekerja': 'Bekerja',
        'Belum Dimulai': 'Pending',
        'Belum Ada Data': '-',
        OFF: 'OFF',
        'Hari Libur': 'Libur',
        Cuti: 'Cuti',
        Sakit: 'Sakit',
        Izin: 'Izin',
    };

    return calData.days.map((day) => {
        const fullDate = day.fullDate || '';
        const dateNum = fullDate ? String(parseInt(fullDate.split('-')[2], 10) || '') : '';
        return {
            ...day,
            fullDate,
            dateNum,
            isToday: fullDate === todayStr,
            shortLabel: labelMap[day.label] || day.label || '-',
        };
    });
}

function getMemberCalendarRangeLabel(member) {
    const calData = getMemberCalendarData(member?.id);
    return calData?.week?.label || '-';
}

function isMemberCalendarLoading(memberId) {
    const key = String(memberId || '')
        .trim()
        .toUpperCase();
    return key !== '' && memberWeeklyCalLoadingId.value === key;
}

function canNavigateWeekPrev(member) {
    const calData = getMemberCalendarData(member?.id);
    return calData?.canPrev !== false;
}

function canNavigateWeekNext(member) {
    const calData = getMemberCalendarData(member?.id);
    return calData?.canNext === true;
}

async function loadMemberWeeklyCalendar(memberId, weekDate = null) {
    const key = String(memberId || '')
        .trim()
        .toUpperCase();
    if (!key) return;

    const cacheKey = getMemberCalendarCacheKey(key, weekDate);
    if (memberWeeklyCalCache.value[cacheKey]) {
        // Already cached, just update the offset pointer
        memberWeekOffsetMap.value = { ...memberWeekOffsetMap.value, [key]: weekDate };
        return;
    }

    if (memberWeeklyCalLoadingId.value === key) return;

    memberWeeklyCalLoadingId.value = key;
    try {
        const params = {};
        if (weekDate) params.week_date = weekDate;

        const response = await axios.get(
            `/homepage/leader-dashboard/members/${encodeURIComponent(key)}/weekly-calendar`,
            { params },
        );

        const data = response?.data?.data || null;
        if (data) {
            memberWeeklyCalCache.value = {
                ...memberWeeklyCalCache.value,
                [cacheKey]: data,
            };
            memberWeekOffsetMap.value = { ...memberWeekOffsetMap.value, [key]: weekDate };
        }
    } catch (error) {
        console.error('Failed to load member weekly calendar', error);
        memberSectionError.value = 'Gagal memuat kalender mingguan anggota.';
    } finally {
        if (memberWeeklyCalLoadingId.value === key) {
            memberWeeklyCalLoadingId.value = '';
        }
    }
}

function navigateMemberWeek(memberId, direction) {
    const key = String(memberId || '')
        .trim()
        .toUpperCase();
    if (!key) return;

    const calData = getMemberCalendarData(key);
    if (!calData?.week) return;

    // Close popover
    if (memberCalendarActiveDay.value.memberId === key) {
        closeMemberCalendarDay();
    }

    // Calculate new week anchor date
    const currentStart = new Date(calData.week.start + 'T00:00:00');
    const offsetDays = Number(direction) * 7;
    const newAnchor = new Date(currentStart);
    newAnchor.setDate(newAnchor.getDate() + offsetDays);

    const yyyy = newAnchor.getFullYear();
    const mm = String(newAnchor.getMonth() + 1).padStart(2, '0');
    const dd = String(newAnchor.getDate()).padStart(2, '0');
    const newWeekDate = `${yyyy}-${mm}-${dd}`;

    void loadMemberWeeklyCalendar(key, newWeekDate);
}

function toggleMemberCalendarDay(member, day) {
    const memberId = String(member?.id || '')
        .trim()
        .toUpperCase();
    const date = day?.fullDate || '';

    if (memberCalendarActiveDay.value.memberId === memberId && memberCalendarActiveDay.value.date === date) {
        closeMemberCalendarDay();
        return;
    }

    memberCalendarActiveDay.value = { memberId, date, data: day };
}

function closeMemberCalendarDay() {
    memberCalendarActiveDay.value = { memberId: '', date: '', data: null };
}

async function openMemberDayRiwayat(member, date, dayData) {
    const memberId = String(member?.id || '')
        .trim()
        .toUpperCase();
    const targetDate = date || dayData?.fullDate || '';
    if (!memberId || !targetDate) return;

    const reqId = ++memberRiwayatReqId;

    // Show modal immediately with loading state
    memberRiwayat.value = {
        show: true,
        loading: true,
        error: '',
        memberId,
        memberName: member?.name || dayData?.day || '',
        date: targetDate,
        dateLabel: dayData?.date || '',
        logs: [],
    };

    try {
        const response = await axios.get(
            `/homepage/leader-dashboard/members/${encodeURIComponent(memberId)}/day-checktimes`,
            { params: { date: targetDate } },
        );

        // Ignore stale responses (user opened a different day meanwhile)
        if (reqId !== memberRiwayatReqId) return;

        const data = response?.data?.data || {};
        memberRiwayat.value = {
            ...memberRiwayat.value,
            loading: false,
            error: '',
            memberName: data?.member?.nama || memberRiwayat.value.memberName,
            dateLabel: data?.date_label || memberRiwayat.value.dateLabel,
            logs: Array.isArray(data?.logs) ? data.logs : [],
        };
    } catch (error) {
        if (reqId !== memberRiwayatReqId) return;
        console.error('Failed to load member day checktimes', error);
        memberRiwayat.value = {
            ...memberRiwayat.value,
            loading: false,
            error: 'Gagal memuat riwayat absensi.',
            logs: [],
        };
    }
}

function closeMemberDayRiwayat() {
    memberRiwayatReqId++; // invalidate any in-flight request
    memberRiwayat.value = {
        ...memberRiwayat.value,
        show: false,
        loading: false,
    };
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

function formatDateId(value) {
    const date = new Date(value);
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
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
*,
*::before,
*::after {
    box-sizing: border-box;
}

.bi {
    display: inline-table !important;
    /* line-height: 1; */
}
.leader-dashboard-page {
    width: 100%;
    min-width: 0;
    min-height: auto;
    overflow-x: hidden;
    /* padding: 0.65rem; */
    color: var(--hcis-slate-900);
    /* background:
        radial-gradient(circle at top left, rgba(99, 102, 241, 0.1), transparent 22rem),
        linear-gradient(180deg, var(--hcis-slate-50) 0%, #eef2f7 100%); */
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
    color: var(--hcis-indigo-600);
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
    color: var(--hcis-slate-500);
}

.leader-hero {
    position: relative;
    overflow: hidden;
    border-radius: 1.25rem;
    padding: clamp(0.9rem, 2vw, 1.2rem);
    color: white;
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.24), transparent 17rem),
        linear-gradient(135deg, var(--hcis-indigo-600), var(--hcis-blue-600));
    box-shadow:
        0 22px 52px rgba(79, 70, 229, 0.22),
        inset 0 1.5px 0 rgba(255, 255, 255, 0.2);
}

.leader-hero::before {
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

.leader-hero__content {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(280px, 340px);
    gap: 0.75rem;
    align-items: stretch;
    min-width: 0;
}

.hero-left,
.hero-right {
    min-width: 0;
}

.hero-topline {
    display: flex;
    gap: 0.4rem;
    align-items: center;
    justify-content: start;
    flex-wrap: wrap;
    min-width: 0;
}

.hero-eyebrow--period {
    font-weight: 600;
    max-width: 100%;
}

.hero-eyebrow--period .leader-hero__cutoff-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hero-eyebrow--period strong {
    font-weight: 950;
    color: #fff;
    letter-spacing: 0.01em;
}

.leader-hero__actions {
    display: flex;
    margin-top: 0.7rem;
}

.hero-eyebrow {
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

.leader-hero__note {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.35rem 0.65rem;
    border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.18);
    font-size: 0.7rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.95);
    width: 100%;
    justify-content: center;
    margin-top: 0.75rem;
}

.leader-hero__note i {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 1);
    display: flex;
    align-items: center;
    justify-content: center;
}

.leader-hero__cutoff-text {
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.leader-hero__cutoff-text strong {
    font-weight: 950;
    color: #fff;
    letter-spacing: 0.01em;
}

.leader-hero__shortcut {
    flex: 0 0 auto;
    min-height: 2.12rem;
    border-radius: 0.75rem;
    font-size: 0.68rem;
    padding: 0 0.7rem;
    background: rgba(255, 255, 255, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.28);
    box-shadow: 0 4px 12px rgba(8, 16, 42, 0.08);
}

.leader-hero__shortcut:hover {
    background: rgba(255, 255, 255, 0.24);
    box-shadow: 0 6px 16px rgba(8, 16, 42, 0.12);
}

.hero-title-row {
    margin-top: 0.6rem;
}

.hero-title-row h1 {
    margin: 0;
    color: white;
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

.hero-brief-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.5rem;
    margin-top: 0.75rem;
}

.hero-brief-grid > div,
.hero-focus-card {
    min-width: 0;
    padding: 0.6rem 0.7rem;
    border-radius: 0.85rem;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.18);
}

.hero-brief-grid span,
.hero-focus-card span,
.hero-focus-card small,
.hero-brief-grid small {
    display: block;
    color: rgba(255, 255, 255, 0.72);
    font-size: 0.58rem;
    font-weight: 850;
}

.hero-brief-grid strong,
.hero-focus-card strong {
    display: block;
    margin-top: 0.12rem;
    font-size: 1.15rem;
    font-weight: 950;
}

.hero-focus-card {
    height: 100%;
}

.hero-focus-card.is-clickable-anchor {
    cursor: pointer;
    transition:
        box-shadow 0.18s ease,
        transform 0.18s ease;
}

.hero-focus-card.is-clickable-anchor:hover,
.hero-focus-card.is-clickable-anchor:focus-visible {
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.14);
    transform: translateY(-1px);
    outline: none;
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

.hero-focus-icon.is-danger {
    background: rgba(225, 29, 72, 0.25);
    color: #fda4af;
    border: 1px solid rgba(225, 29, 72, 0.35);
}

.hero-focus-list {
    display: grid;
    gap: 0.32rem;
    margin-top: 0.45rem;
}

.hero-focus-loading {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.45rem;
    border-radius: 0.56rem;
    background: rgba(255, 255, 255, 0.12);
    border: 1px dashed rgba(255, 255, 255, 0.24);
    color: rgba(255, 255, 255, 0.92);
    font-size: 0.56rem;
    font-weight: 800;
}

.hero-focus-loading.is-error {
    border-style: solid;
    border-color: rgba(252, 165, 165, 0.45);
    background: rgba(127, 29, 29, 0.22);
}

.hero-focus-item {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
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

.hero-focus-item__main {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
    flex: 1;
}

.hero-focus-item__main > div {
    min-width: 0;
}

.hero-focus-item__main strong,
.hero-focus-item__main span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: block;
}

.hero-focus-item__main strong {
    font-size: 0.58rem;
}

.hero-focus-done-btn {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    padding: 0.28rem 0.6rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.52rem;
    font-weight: 800;
    cursor: pointer;
    backdrop-filter: blur(4px);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.hero-focus-done-btn i {
    font-size: 0.62rem;
}

.hero-focus-done-btn:hover:not(:disabled) {
    background: rgba(34, 197, 94, 0.15);
    border-color: rgba(34, 197, 94, 0.4);
    color: var(--hcis-white);
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(34, 197, 94, 0.1);
}

.hero-focus-done-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
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
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
}

.quick-stat-card__copy {
    min-width: 0;
}

.quick-stat-card strong {
    display: block;
    color: var(--hcis-slate-900);
    font-size: 0.88rem;
    font-weight: 950;
    line-height: 1;
}

.quick-stat-card span {
    display: block;
    margin-top: 0.12rem;
    color: var(--hcis-slate-700);
    font-size: 0.58rem;
    font-weight: 900;
}

.quick-stat-card small {
    display: block;
    margin-top: 0.04rem;
    color: var(--hcis-slate-500);
    font-size: 0.5rem;
    font-weight: 750;
}

.quick-stat-card.is-danger .quick-stat-card__icon,
.mini-metric.is-danger-soft {
    background: var(--hcis-red-100);
    color: var(--hcis-red-600);
}

.quick-stat-card.is-warning .quick-stat-card__icon {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-600);
}

.quick-stat-card.is-purple .quick-stat-card__icon {
    background: #ede9fe;
    color: var(--hcis-violet-600);
}

.quick-stat-card.is-info .quick-stat-card__icon {
    background: var(--hcis-sky-100);
    color: var(--hcis-sky-600);
}

.quick-stat-card.is-success .quick-stat-card__icon {
    background: var(--hcis-green-100);
    color: var(--hcis-green-600);
}

.quick-stat-card.is-orange .quick-stat-card__icon {
    background: var(--hcis-orange-100);
    color: var(--hcis-orange-600);
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
    color: var(--hcis-slate-900);
}

.mini-badge,
.side-count {
    flex: 0 0 auto;
    padding: 0.2rem 0.4rem;
    border-radius: 999px;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
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
    color: var(--hcis-slate-500);
    font-size: 0.64rem;
    font-weight: 700;
    line-height: 1.35;
}

.side-count.is-success {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}

.side-count.is-warning {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
}

.side-count.is-orange {
    background: var(--hcis-orange-100);
    color: var(--hcis-orange-600);
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
    padding: 0.32rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 0.6rem;
    background: var(--hcis-white);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    will-change: transform, box-shadow;
    transition: transform 300ms cubic-bezier(0.16, 1, 0.3, 1), box-shadow 300ms cubic-bezier(0.16, 1, 0.3, 1), border-color 200ms ease;
}

.hcis-status-grid > div:hover,
.metric-grid > div:hover,
.kpi-period-box > div:hover {
    transform: translateY(-4px);
    border-color: var(--hcis-slate-300);
    box-shadow: 0 12px 24px -4px rgba(15, 23, 42, 0.1);
}

.hcis-status-grid span,
.hcis-status-grid small,
.metric-grid span,
.metric-grid small,
.kpi-period-box span,
.kpi-period-box small {
    display: block;
    color: var(--hcis-slate-500);
    font-size: 0.5rem;
    font-weight: 850;
}

.hcis-status-grid strong,
.metric-grid strong,
.kpi-period-box strong {
    display: block;
    margin-top: 0.06rem;
    color: var(--hcis-slate-900);
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
    background: var(--hcis-slate-200);
}

.status-bar-stack__item.is-success {
    background: #22c55e;
}

.status-bar-stack__item.is-danger {
    background: var(--hcis-red-500);
}

.status-bar-stack__item.is-info {
    background: var(--hcis-sky-500);
}

.mini-heatmap,
/* Grid hari heatmap: lebar sel tetap + auto-flow kolom (periode cutoff ~30 hari).
   Dibungkus .heatmap-scroll untuk scroll horizontal; nama anggota sticky. */
.heatmap-row__days {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: 16px;
    gap: 0.2rem;
}

.heat-cell {
    width: 16px;
    height: 16px;
    aspect-ratio: 1;
    min-height: 0;
    border-radius: 0.24rem;
    background: var(--hcis-slate-200);
}

/* Garis pemisah tipis di awal tiap minggu (Senin) agar mata mudah mengelompokkan. */
.heat-cell.is-weekstart,
.heat-dayhead.is-weekstart {
    box-shadow: -3px 0 0 -2px var(--hcis-slate-300);
}

.heat-cell.is-success {
    background: #86efac;
}

.heat-cell.is-warning {
    background: var(--hcis-amber-200);
}

.heat-cell.is-danger {
    background: var(--hcis-red-300);
}

.heat-cell.is-info {
    background: var(--hcis-sky-200);
}

.heat-cell.is-working {
    background: #60a5fa;
}

.heat-cell.is-orange {
    background: var(--hcis-orange-300);
}

.heat-cell.is-default {
    background: var(--hcis-slate-200);
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
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
}

.rank-badge {
    width: 1.7rem;
    height: 1.7rem;
    display: grid;
    place-items: center;
    border-radius: 0.56rem;
    background: #ede9fe;
    color: var(--hcis-violet-600);
    font-size: 0.58rem;
    font-weight: 950;
}

.top-performance-row.rank-1 .rank-badge {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
}

.top-performance-user,
.top-performance-score {
    min-width: 0;
}

.top-performance-user strong,
.top-performance-score strong {
    display: block;
    color: var(--hcis-slate-900);
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
    color: var(--hcis-slate-500);
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
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
}

.mini-line-chart__bar {
    flex: 1;
    min-width: 0.4rem;
    border-radius: 999px 999px 0.2rem 0.2rem;
    background: linear-gradient(180deg, #8b5cf6, var(--hcis-indigo-500));
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
    color: var(--hcis-slate-900);
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
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
}

.lms-focus-status.is-safe {
    background: var(--hcis-green-100);
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
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-white);
}

.lms-focus-item__identity,
.lms-focus-item__meta {
    min-width: 0;
}

.lms-focus-item__identity strong {
    display: block;
    color: var(--hcis-slate-900);
    font-size: 0.58rem;
    font-weight: 900;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lms-focus-item__identity span {
    display: block;
    margin-top: 0.06rem;
    color: var(--hcis-slate-500);
    font-size: 0.5rem;
    font-weight: 800;
}

.lms-focus-item__meta {
    text-align: right;
}

.lms-focus-item__meta b {
    display: block;
    color: var(--hcis-slate-900);
    font-size: 0.6rem;
    font-weight: 950;
}

.lms-focus-item__meta small {
    display: block;
    margin-top: 0.04rem;
    font-size: 0.48rem;
    font-weight: 850;
    color: var(--hcis-slate-500);
}

.lms-focus-item.is-danger {
    border-color: var(--hcis-red-200);
    background: #fff7f7;
}

.lms-focus-item.is-danger .lms-focus-item__meta b {
    color: var(--hcis-red-700);
}

.lms-focus-item.is-warning {
    border-color: var(--hcis-amber-200);
    background: var(--hcis-amber-50);
}

.lms-focus-item.is-warning .lms-focus-item__meta b {
    color: var(--hcis-amber-700);
}

.lms-focus-item.is-safe {
    border-color: var(--hcis-green-200);
    background: var(--hcis-green-50);
}

.lms-focus-item.is-safe .lms-focus-item__meta b {
    color: var(--hcis-green-700);
}

.lms-focus-empty {
    margin-top: 0.42rem;
}

.lms-focus-empty.is-waiting span {
    display: inline-flex;
    align-items: center;
    padding: 0.22rem 0.4rem;
    border-radius: 999px;
    background: var(--hcis-amber-50);
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
    background: var(--hcis-green-100);
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
    background: var(--hcis-white);
    border: 1px solid #dbeafe;
    color: var(--hcis-slate-600);
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
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-600);
}

.date-picker-control select {
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--hcis-slate-900);
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
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.7rem;
    background: var(--hcis-slate-50);
    text-align: left;
    cursor: pointer;
}

.shift-card.is-warning {
    background: var(--hcis-amber-50);
    border-color: var(--hcis-amber-200);
}

.shift-card.is-active {
    border-color: var(--hcis-indigo-500);
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
    color: var(--hcis-slate-900);
    font-size: 0.68rem;
    font-weight: 950;
}

.shift-card span,
.selected-shift-detail span {
    display: block;
    color: var(--hcis-slate-500);
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
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
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
    background: var(--hcis-indigo-500);
    color: #fff;
    font-size: 0.55rem;
    font-weight: 950;
}

.shift-member-pills small {
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-600);
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
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-800);
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
    color: var(--hcis-slate-600);
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
    color: var(--hcis-green-600);
}

.shift-card.is-warning .shift-card__footer i {
    color: var(--hcis-amber-600);
}

.selected-shift-detail {
    margin-top: 0.45rem;
    padding: 0.5rem;
    border-radius: 0.7rem;
    border: 1px solid var(--hcis-slate-200);
    background: #fff;
}

.selected-shift-detail__head small {
    flex: 0 0 auto;
    padding: 0.18rem 0.36rem;
    border-radius: 999px;
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
    font-size: 0.52rem;
    font-weight: 950;
}

.selected-shift-detail__head small.is-warning {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
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
    color: var(--hcis-slate-500);
}

.pagination-btn {
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-700);
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
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
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
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}

.shift-person small.is-success {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}

.shift-person small.is-info {
    background: var(--hcis-sky-100);
    color: var(--hcis-sky-700);
}

.shift-person small.is-danger {
    background: var(--hcis-red-100);
    color: var(--hcis-red-600);
}

.shift-person small.is-warning {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
}

.coverage-alert {
    margin-top: 0.45rem;
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-800);
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
    flex-wrap: wrap;
}

.toolbar-title {
    flex: 0 0 auto;
}

.toolbar-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    flex: 1 1 auto;
    flex-wrap: wrap;
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
    border: 1px solid var(--hcis-slate-200);
    background: white;
    color: var(--hcis-slate-600);
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
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    flex-shrink: 0;
    min-width: 150px;
}

.search-box.is-loading {
    border-color: #bfdbfe;
    background: linear-gradient(135deg, #f8fbff 0%, #eff6ff 100%);
    box-shadow: 0 0 0 3px rgba(191, 219, 254, 0.28);
}

.search-box i.is-loading {
    color: #1d4ed8;
}

.search-box input {
    width: 100%;
    min-width: 0;
    border: none;
    outline: none;
    background: transparent;
    font-size: 0.68rem;
    color: var(--hcis-slate-900);
}

.filter-control {
    height: 1.95rem;
    min-width: 8.2rem;
    border-radius: 0.6rem;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-700);
    padding: 0 0.42rem;
    font-size: 0.64rem;
    font-weight: 850;
    outline: none;
}

.member-list {
    display: grid;
    gap: 0.42rem;
}

.team-list-content {
    align-content: start;
}

.team-list-content.is-loading {
    min-height: 16.5rem;
}

.member-search-feedback {
    display: grid;
    gap: 0.42rem;
    padding: 0.68rem 0.74rem;
    border: 1px solid #dbeafe;
    border-radius: 0.9rem;
    background: linear-gradient(135deg, #f8fbff 0%, #eff6ff 100%);
    color: var(--hcis-slate-600);
    box-shadow: 0 8px 18px rgba(59, 130, 246, 0.08);
}

.member-search-feedback i {
    font-size: 0.72rem;
    color: #1d4ed8;
}

.member-search-feedback strong {
    font-size: 0.68rem;
    color: var(--hcis-slate-800);
}

.member-search-feedback span {
    font-size: 0.58rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
}

.loading-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    width: fit-content;
    padding: 0.22rem 0.46rem;
    border-radius: 999px;
    border: 1px solid #bfdbfe;
    background: var(--hcis-white);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}

.loading-status-icon {
    display: inline-block;
    transform-origin: center;
    animation: loading-icon-pulse 0.95s ease-in-out infinite;
}

@keyframes loading-icon-pulse {
    0%,
    100% {
        opacity: 0.75;
        transform: scale(0.96);
    }
    50% {
        opacity: 1;
        transform: scale(1);
    }
}

.loading-progress-bar {
    position: relative;
    width: 100%;
    height: 0.28rem;
    border-radius: 999px;
    background: rgba(148, 163, 184, 0.2);
    overflow: hidden;
}

.loading-progress-bar > span {
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(90deg, rgba(29, 78, 216, 0.15) 0%, var(--hcis-blue-500) 40%, rgba(29, 78, 216, 0.15) 100%);
    transform: translateX(-100%);
    animation: loading-progress-shift 1.15s ease-in-out infinite;
}

@keyframes loading-progress-shift {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

.team-calendar-panel {
    position: relative;
    display: grid;
    gap: 0.62rem;
    padding: 0.9rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 1rem;
    background: var(--hcis-white);
    box-shadow:
        0 4px 6px -1px rgba(15, 23, 42, 0.05),
        0 2px 4px -2px rgba(15, 23, 42, 0.05);
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
    color: var(--hcis-slate-900);
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
    margin-bottom: 1rem;
}

.team-calendar-filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    padding: 0.22rem 0.5rem;
    border-radius: 999px;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-600);
    font-size: 0.56rem;
    font-weight: 900;
    cursor: pointer;
}

.team-calendar-filter-chip b {
    font-size: 0.52rem;
    color: var(--hcis-slate-900);
}

.team-calendar-filter-chip.is-active {
    border-color: #93c5fd;
    background: #dbeafe;
    color: #1d4ed8;
}

.team-calendar-summary-row {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.team-calendar-summary-pill {
    border-radius: 0.56rem;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
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
    background: var(--hcis-emerald-50);
    border-color: var(--hcis-green-200);
}

.team-calendar-summary-pill.is-success strong,
.team-calendar-summary-pill.is-success span {
    color: var(--hcis-green-700);
}

.team-calendar-summary-pill.is-warning {
    background: var(--hcis-amber-50);
    border-color: var(--hcis-amber-200);
}

.team-calendar-summary-pill.is-warning strong,
.team-calendar-summary-pill.is-warning span {
    color: var(--hcis-amber-700);
}

.team-calendar-summary-pill.is-danger {
    background: var(--hcis-red-50);
    border-color: var(--hcis-red-200);
}

.team-calendar-summary-pill.is-danger strong,
.team-calendar-summary-pill.is-danger span {
    color: var(--hcis-red-700);
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
    min-height: 5.5rem;
    border: 1px solid #dbe3ef;
    border-radius: 0.6rem;
    background: linear-gradient(180deg, var(--hcis-white) 0%, var(--hcis-slate-50) 100%);
    padding: 0.6rem;
    display: flex;
    flex-direction: column;
    text-align: left;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.02);
    transition: all 0.2s ease;
}

.team-calendar-day:hover {
    border-color: #93c5fd;
    background: #eff6ff;
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(59, 130, 246, 0.08);
}

.team-calendar-day.is-today {
    box-shadow:
        inset 0 0 0 1px var(--hcis-blue-500),
        0 8px 16px rgba(59, 130, 246, 0.1);
}

.team-calendar-day.has-problem {
    border-color: var(--hcis-red-300);
}

.team-calendar-day.is-success {
    background: var(--hcis-green-50);
    border-color: var(--hcis-green-200);
}

.team-calendar-day.is-warning {
    background: var(--hcis-amber-50);
    border-color: var(--hcis-amber-200);
}

.team-calendar-day.is-danger {
    background: var(--hcis-red-50);
    border-color: var(--hcis-red-200);
}

.team-calendar-day.is-info {
    background: #eff6ff;
    border-color: #bfdbfe;
}

.team-calendar-day.is-default,
.team-calendar-day.is-secondary {
    background: var(--hcis-slate-50);
}

.team-calendar-day__head {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.35rem;
    margin-bottom: auto;
    width: 100%;
}

.team-calendar-day__head > div {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
    width: 100%;
}

.team-calendar-day__head strong {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--hcis-slate-800);
    line-height: 1;
}

.team-calendar-day__head span {
    font-size: 0.6rem;
    font-weight: 600;
    color: var(--hcis-slate-400);
}

.team-calendar-day__head small {
    font-size: 0.55rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    background: rgba(15, 23, 42, 0.06);
    padding: 0.25rem 0.45rem;
    border-radius: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.team-calendar-day__stats {
    display: grid;
    gap: 0.15rem;
    margin-top: 0.75rem;
    width: 100%;
}

.team-calendar-day__stats span {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.55rem;
    color: var(--hcis-slate-500);
    font-weight: 500;
}

.team-calendar-day__stats b {
    color: var(--hcis-slate-800);
    font-weight: 800;
    font-size: 0.6rem;
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
    background: var(--hcis-indigo-500);
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
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: flex-start;
    gap: 0.42rem;
    flex-wrap: wrap;
}

.member-name-line h3 {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 950;
    color: var(--hcis-slate-900);
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
    color: var(--hcis-red-700);
    background: var(--hcis-red-100);
    border-color: var(--hcis-red-200);
}

.priority-pill.is-medium {
    color: var(--hcis-amber-700);
    background: var(--hcis-amber-100);
    border-color: var(--hcis-amber-200);
}

.priority-pill.is-low {
    color: var(--hcis-green-700);
    background: var(--hcis-green-100);
    border-color: var(--hcis-green-200);
}

.member-name-wrap p {
    margin: 0.12rem 0 0;
    color: var(--hcis-slate-500);
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
    background: var(--hcis-indigo-500);
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
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    border-radius: 0.55rem;
    text-align: center;
    display: grid;
    align-content: center;
}

.mini-metric strong {
    display: block;
    font-size: 0.62rem;
    font-weight: 950;
    color: var(--hcis-slate-900);
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
    color: var(--hcis-slate-500);
    line-height: 1.1;
}

.mini-metric.is-success {
    background: var(--hcis-green-100);
    border-color: var(--hcis-green-200);
}

.mini-metric.is-success strong {
    color: var(--hcis-green-700);
}

.mini-metric.is-danger {
    background: var(--hcis-red-100);
    border-color: var(--hcis-red-200);
}

.mini-metric.is-danger strong {
    color: var(--hcis-red-600);
}

.mini-metric.is-warning {
    background: var(--hcis-amber-100);
    border-color: var(--hcis-amber-200);
}

.mini-metric.is-warning strong {
    color: var(--hcis-amber-700);
}

.mini-metric.is-info {
    background: var(--hcis-sky-100);
    border-color: var(--hcis-sky-200);
}

.mini-metric.is-info strong {
    color: var(--hcis-sky-700);
}

.mini-metric.is-purple {
    background: #ede9fe;
    border-color: #ddd6fe;
}

.mini-metric.is-purple strong {
    color: var(--hcis-violet-600);
}

.mini-metric.is-orange {
    background: var(--hcis-orange-100);
    border-color: #fed7aa;
}

.mini-metric.is-orange strong {
    color: var(--hcis-orange-600);
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
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-600);
    font-size: 0.54rem;
    font-weight: 850;
}

.issue-count {
    min-width: 2.55rem;
    padding: 0.26rem 0.34rem;
    border-radius: 0.58rem;
    text-align: center;
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-200);
}

.issue-count.has-issue {
    background: #fff7ed;
    border-color: #fed7aa;
}

.issue-count strong {
    display: block;
    font-size: 0.68rem;
    font-weight: 950;
    color: var(--hcis-slate-900);
    line-height: 1;
}

.issue-count.has-issue strong {
    color: var(--hcis-orange-600);
}

.issue-count span {
    display: block;
    margin-top: 0.06rem;
    font-size: 0.46rem;
    font-weight: 850;
    color: var(--hcis-slate-500);
}

.member-chevron {
    color: var(--hcis-slate-500);
    transition: transform 0.18s ease;
}

.member-chevron.is-open {
    transform: rotate(180deg);
}

.member-detail {
    padding: 0.55rem;
    border-top: 1px solid rgba(226, 232, 240, 0.8);
    background: var(--hcis-white);
}

/* ─── DETAIL TOP: Prioritas Tindak Lanjut ─── */
.detail-top {
    display: flex;
    flex-direction: column;
    gap: 0.42rem;
    padding: 0.65rem 0.7rem;
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: 0.85rem;
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-slate-50) 60%, var(--hcis-slate-100) 100%);
    margin-bottom: 0.55rem;
    position: relative;
    overflow: hidden;
}

.detail-top::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--hcis-indigo-600), var(--hcis-cyan-500), var(--hcis-emerald-500));
    border-radius: 0.85rem 0.85rem 0 0;
}

.detail-top-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.detail-top-title {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.detail-top-icon {
    font-size: 0.72rem;
    color: var(--hcis-indigo-600);
}

.detail-top-title strong {
    font-size: 0.72rem;
    font-weight: 950;
    color: var(--hcis-slate-900);
    letter-spacing: -0.01em;
}

.detail-headline {
    margin: 0;
    font-size: 0.6rem;
    font-weight: 750;
    color: var(--hcis-slate-600);
    line-height: 1.4;
}

.detail-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.26rem;
}

.tag {
    display: inline-flex;
    align-items: center;
    gap: 0.18rem;
    padding: 0.18rem 0.42rem;
    border-radius: 999px;
    font-size: 0.52rem;
    font-weight: 900;
    letter-spacing: 0.01em;
    transition:
        transform 0.15s ease,
        box-shadow 0.15s ease;
}

.tag:hover {
    transform: translateY(-1px);
}

.tag i {
    font-size: 0.48rem;
}

.tag.is-danger {
    background: var(--hcis-red-100);
    color: var(--hcis-red-600);
}

.tag.is-warning {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
}

.tag.is-info {
    background: var(--hcis-sky-100);
    color: var(--hcis-sky-700);
}

.tag.is-success {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}

.tag.is-priority {
    border: 1px solid transparent;
    font-weight: 950;
}

.tag.is-priority.is-high {
    background: linear-gradient(135deg, var(--hcis-red-100), var(--hcis-red-200));
    border-color: var(--hcis-red-300);
    color: var(--hcis-red-700);
}

.tag.is-priority.is-medium {
    background: linear-gradient(135deg, var(--hcis-amber-100), var(--hcis-amber-200));
    border-color: var(--hcis-amber-400);
    color: var(--hcis-amber-800);
}

.tag.is-priority.is-low {
    background: linear-gradient(135deg, var(--hcis-green-100), var(--hcis-green-200));
    border-color: #86efac;
    color: var(--hcis-green-700);
}

/* ─── PRIORITY SUMMARY GRID ─── */
.priority-summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.38rem;
}

.priority-summary-item {
    display: flex;
    align-items: flex-start;
    gap: 0.38rem;
    min-width: 0;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.72rem;
    background: var(--hcis-white);
    padding: 0.45rem 0.5rem;
    transition:
        border-color 0.18s ease,
        box-shadow 0.18s ease;
}

.priority-summary-item:hover {
    border-color: var(--hcis-slate-300);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.priority-summary-item.is-issue {
    border-left: 3px solid var(--hcis-red-500);
}

.priority-summary-item.is-impact {
    border-left: 3px solid var(--hcis-amber-500);
}

.priority-summary-item.is-action {
    border-left: 3px solid var(--hcis-blue-500);
}

.priority-summary-icon {
    flex: 0 0 auto;
    width: 1.5rem;
    height: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.45rem;
    font-size: 0.62rem;
}

.priority-summary-item.is-issue .priority-summary-icon {
    background: var(--hcis-red-50);
    color: var(--hcis-red-500);
}

.priority-summary-item.is-impact .priority-summary-icon {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-500);
}

.priority-summary-item.is-action .priority-summary-icon {
    background: #eff6ff;
    color: var(--hcis-blue-500);
}

.priority-summary-content {
    min-width: 0;
    flex: 1;
}

.priority-summary-content small {
    display: block;
    font-size: 0.5rem;
    font-weight: 800;
    color: var(--hcis-slate-400);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.priority-summary-content b {
    display: block;
    margin-top: 0.12rem;
    font-size: 0.58rem;
    font-weight: 900;
    color: var(--hcis-slate-900);
    line-height: 1.3;
}

/* ─── PRIORITY INTEL GRID ─── */
.priority-intel-grid {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 0.5rem;
    margin-bottom: 0.55rem;
}

.priority-intel-card {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.8rem;
    background: var(--hcis-white);
    padding: 0.55rem;
    transition: box-shadow 0.18s ease;
}

.priority-intel-card:hover {
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
}

.priority-intel-card.is-action-card {
    background: linear-gradient(180deg, var(--hcis-white) 0%, #fafbff 100%);
}

.priority-intel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.35rem;
    margin-bottom: 0.45rem;
    padding-bottom: 0.35rem;
    border-bottom: 1px solid var(--hcis-slate-100);
}

.priority-intel-head-left {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    min-width: 0;
}

.priority-intel-head-icon {
    font-size: 0.65rem;
}

.priority-intel-head-icon.is-reason {
    color: var(--hcis-violet-600);
}

.priority-intel-head-icon.is-action {
    color: var(--hcis-blue-600);
}

.priority-intel-head strong {
    font-size: 0.64rem;
    font-weight: 950;
    color: var(--hcis-slate-900);
}

.priority-intel-count {
    font-size: 0.5rem;
    font-weight: 800;
    color: var(--hcis-slate-400);
    background: var(--hcis-slate-100);
    padding: 0.12rem 0.35rem;
    border-radius: 999px;
    white-space: nowrap;
}

/* ─── PRIORITY REASON LIST ─── */
.priority-reason-list,
.priority-action-list {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.priority-reason-item {
    display: flex;
    align-items: stretch;
    gap: 0.4rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.62rem;
    background: var(--hcis-slate-50);
    padding: 0.38rem 0.45rem;
    transition:
        background 0.15s ease,
        border-color 0.15s ease;
}

.priority-reason-item:hover {
    background: var(--hcis-slate-100);
    border-color: var(--hcis-slate-300);
}

.priority-reason-item.is-top {
    background: var(--hcis-yellow-50);
    border-color: var(--hcis-amber-200);
}

.priority-reason-item.is-neutral {
    opacity: 0.72;
}

.priority-reason-indicator {
    flex: 0 0 3px;
    border-radius: 999px;
    align-self: stretch;
}

.priority-reason-indicator.is-high {
    background: linear-gradient(180deg, var(--hcis-red-500), var(--hcis-red-600));
}

.priority-reason-indicator.is-medium {
    background: linear-gradient(180deg, var(--hcis-amber-500), var(--hcis-amber-600));
}

.priority-reason-indicator.is-low {
    background: linear-gradient(180deg, var(--hcis-blue-500), var(--hcis-blue-600));
}

.priority-reason-indicator.is-none {
    background: var(--hcis-slate-300);
}

.priority-reason-body {
    flex: 1;
    min-width: 0;
}

.priority-reason-top {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.35rem;
}

.priority-reason-top strong {
    font-size: 0.6rem;
    font-weight: 900;
    color: var(--hcis-slate-900);
}

.priority-reason-value {
    flex: 0 0 auto;
    font-size: 0.56rem;
    font-weight: 900;
    color: var(--hcis-indigo-600);
    background: var(--hcis-indigo-50);
    padding: 0.08rem 0.3rem;
    border-radius: 999px;
}

.priority-reason-evidence {
    display: block;
    margin-top: 0.1rem;
    font-size: 0.52rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    line-height: 1.35;
}

/* ─── PRIORITY ACTION LIST ─── */
.priority-action-item {
    width: 100%;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.62rem;
    background: var(--hcis-slate-50);
    text-align: left;
    display: flex;
    align-items: center;
    gap: 0.42rem;
    padding: 0.42rem 0.5rem;
    cursor: pointer;
    transition: all 0.18s ease;
}

.priority-action-item:hover {
    border-color: var(--hcis-indigo-400);
    background: var(--hcis-indigo-50);
    transform: translateX(2px);
}

.priority-action-item.is-primary {
    background: linear-gradient(135deg, var(--hcis-indigo-50) 0%, var(--hcis-indigo-100) 100%);
    border-color: var(--hcis-indigo-200);
}

.priority-action-item.is-primary:hover {
    border-color: var(--hcis-indigo-400);
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.12);
}

.priority-action-number {
    flex: 0 0 auto;
    width: 1.35rem;
    height: 1.35rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-700);
    font-size: 0.55rem;
    font-weight: 950;
}

.priority-action-item.is-primary .priority-action-number {
    background: var(--hcis-indigo-600);
    color: var(--hcis-white);
}

.priority-action-body {
    flex: 1;
    min-width: 0;
}

.priority-action-body strong {
    display: block;
    font-size: 0.6rem;
    font-weight: 900;
    color: var(--hcis-slate-900);
}

.priority-action-urgency {
    display: block;
    margin-top: 0.06rem;
    font-size: 0.5rem;
    font-weight: 800;
    color: var(--hcis-indigo-500);
}

.priority-action-arrow {
    flex: 0 0 auto;
    font-size: 0.7rem;
    color: var(--hcis-indigo-300);
    transition:
        transform 0.18s ease,
        color 0.18s ease;
}

.priority-action-item:hover .priority-action-arrow {
    transform: translateX(3px);
    color: var(--hcis-indigo-600);
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.45rem;
}

.detail-loading {
    border: 1px dashed var(--hcis-slate-300);
    border-radius: 0.8rem;
    background: var(--hcis-slate-50);
    padding: 0.7rem;
}

.detail-loading__head {
    display: inline-flex;
    align-items: center;
    gap: 0.32rem;
    color: var(--hcis-slate-600);
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
    background: linear-gradient(90deg, var(--hcis-slate-200) 25%, var(--hcis-slate-100) 50%, var(--hcis-slate-200) 75%);
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
    padding: 0.55rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 0.75rem;
    background: linear-gradient(135deg, var(--hcis-slate-50) 0%, var(--hcis-slate-100) 100%);
    box-shadow:
        0 4px 6px -1px rgba(0, 0, 0, 0.02),
        0 2px 4px -1px rgba(0, 0, 0, 0.01);

    display: flex;
    flex-direction: column;
    min-height: 32rem;
    max-height: 48rem;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: var(--hcis-slate-300) transparent;
}

.detail-panel::-webkit-scrollbar {
    width: 4px;
}

.detail-panel::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: var(--hcis-slate-300);
}

.detail-panel--wide {
    grid-column: 1 / -1;
    min-height: unset;
    max-height: unset;
    overflow-y: visible;
}

.detail-panel--kpi {
    background: linear-gradient(135deg, #fbfaff 0%, #f3f0ff 100%);
}

.detail-panel--lms {
    background: linear-gradient(135deg, #f8fffe 0%, #ebfbf8 100%);
}

.detail-panel--lms .empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    min-height: 14rem;
}

/* === LMS member detail card — disamakan dengan personal dashboard (homepage.vue) === */
.detail-panel--lms .lms-progress-ring {
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

.detail-panel--lms .lms-progress-ring span {
    display: block;
    font-size: 0.58rem;
    font-weight: 900;
    line-height: 1;
    letter-spacing: 0.05em;
    opacity: 0.9;
    text-transform: uppercase;
}

.detail-panel--lms .lms-progress-ring strong {
    display: block;
    margin-top: 0.02rem;
    color: inherit;
    font-size: 0.96rem;
    font-weight: 950;
    line-height: 1;
}

.detail-panel--lms .lms-hero {
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

.detail-panel--lms .lms-hero .lms-progress-ring {
    width: 3.5rem;
    height: 3.5rem;
}

.detail-panel--lms .lms-hero__info {
    display: grid;
    gap: 0.26rem;
    min-width: 0;
    flex: 1;
}

.detail-panel--lms .lms-hero__top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
}

.detail-panel--lms .lms-hero__top > span {
    color: #0f766e;
    font-size: 0.57rem;
    font-weight: 850;
    min-width: 0;
}

.detail-panel--lms .lms-hero__badge {
    flex: 0 0 auto;
    font-size: 0.64rem !important;
    font-weight: 950 !important;
    color: #0f766e !important;
    background: rgba(20, 184, 166, 0.16);
    padding: 0.12rem 0.44rem;
    border-radius: 999px;
}

.detail-panel--lms .lms-hero__info > small {
    color: var(--hcis-slate-500);
    font-size: 0.62rem;
    font-weight: 800;
}

.detail-panel--lms .lms-quick-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.34rem;
    margin-bottom: 0.42rem;
}

.detail-panel--lms .lms-stat-pill {
    display: flex;
    align-items: center;
    gap: 0.38rem;
    padding: 0.36rem 0.44rem;
    border-radius: 0.65rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    min-width: 0;
}

.detail-panel--lms .lms-stat-pill > i {
    flex: 0 0 auto;
    font-size: 0.8rem;
    color: #0f766e;
}

.detail-panel--lms .lms-stat-pill > div {
    min-width: 0;
}

.detail-panel--lms .lms-stat-pill span {
    display: block;
    color: var(--hcis-slate-500);
    font-size: 0.62rem;
    font-weight: 850;
}

.detail-panel--lms .lms-stat-pill strong {
    display: block;
    color: var(--hcis-slate-900);
    font-size: 0.65rem;
    font-weight: 950;
}

.detail-panel--lms .lms-next-highlight {
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

.detail-panel--lms .lms-next-highlight > i {
    flex: 0 0 auto;
    font-size: 0.8rem;
    color: #ca8a04;
}

.detail-panel--lms .lms-next-highlight__info {
    flex: 1;
    min-width: 0;
}

.detail-panel--lms .lms-next-highlight__info span {
    display: block;
    color: var(--hcis-amber-800);
    font-size: 0.6rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.detail-panel--lms .lms-next-highlight__info strong {
    display: block;
    color: var(--hcis-slate-900);
    font-size: 0.68rem;
    font-weight: 950;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.detail-panel--lms .lms-next-highlight__date {
    flex: 0 0 auto;
    color: var(--hcis-amber-800);
    font-size: 0.62rem;
    font-weight: 850;
    white-space: nowrap;
}

.detail-panel--lms .lms-list-header {
    display: flex;
    align-items: center;
    gap: 0.28rem;
    margin-bottom: 0.3rem;
}

.detail-panel--lms .lms-list-header > i {
    font-size: 0.68rem;
    color: #0f766e;
}

.detail-panel--lms .lms-list-header > span {
    color: var(--hcis-slate-600);
    font-size: 0.62rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.detail-panel--lms .lms-list-header + .training-list {
    margin-top: 0;
}

.detail-panel--lms .module-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    margin-bottom: 0.5rem;
    min-width: 0;
}

.detail-panel--lms .module-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}

.detail-panel--lms .module-title-copy {
    min-width: 0;
}

.detail-panel--lms .module-title-copy h2 {
    margin: 0;
    font-size: 0.86rem;
    font-weight: 950;
    color: var(--hcis-slate-900);
    line-height: 1.1;
}

.detail-panel--lms .section-label {
    display: block;
    font-size: 0.64rem;
    font-weight: 900;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    color: var(--hcis-slate-500);
}

.detail-panel--lms .module-icon {
    flex: 0 0 auto;
    width: 2.2rem;
    height: 2.2rem;
    display: grid;
    place-items: center;
    border-radius: 0.72rem;
}

.detail-panel--lms .module-icon.is-teal {
    background: #ccfbf1;
    color: #0f766e;
}

.detail-panel--lms .period-badge {
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

.detail-panel--lms .period-badge.is-teal {
    background: #ccfbf1;
    color: #0f766e;
}

.detail-panel--lms .training-item {
    min-width: 0;
}

.detail-panel--lms .training-item__top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
}

.detail-panel--lms .training-item__top > div,
.detail-panel--lms .training-item__body > div {
    min-width: 0;
}

.detail-panel--lms .training-item span {
    min-width: 0;
    color: var(--hcis-slate-600);
    font-size: 0.65rem;
    font-weight: 850;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.detail-panel--lms .training-item strong {
    flex: 0 0 auto;
    color: var(--hcis-slate-900);
    font-size: 0.65rem;
    font-weight: 950;
}

.detail-panel--lms .training-item__top strong {
    display: block;
    flex: unset;
    margin-bottom: 0.04rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.detail-panel--lms .training-item__top small {
    display: inline-block;
    padding: 0.16rem 0.34rem;
    border-radius: 999px;
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
    font-size: 0.62rem;
    font-weight: 950;
}

.detail-panel--lms .training-item__top small.is-complete {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}

.detail-panel--lms .training-item__body {
    display: flex;
    align-items: center;
    gap: 0.36rem;
    min-width: 0;
    flex: 1;
}

.detail-panel--lms .training-item__dot {
    flex: 0 0 auto;
    width: 0.44rem;
    height: 0.44rem;
    border-radius: 999px;
    background: var(--hcis-slate-300);
}

.detail-panel--lms .training-item__dot.is-done {
    background: linear-gradient(135deg, #14b8a6, var(--hcis-cyan-500));
}

.detail-panel--lms .training-item__dot.is-active {
    background: var(--hcis-amber-500);
}

.detail-panel--lms .training-item-placeholder {
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

.detail-panel--lms .placeholder-dot {
    width: 0.44rem;
    height: 0.44rem;
    border-radius: 999px;
    border: 1px dashed var(--hcis-slate-300);
    background: transparent;
}

.detail-panel--lms .mini-progress {
    width: 100%;
    height: 0.35rem;
    margin-top: 0.16rem;
    border-radius: 999px;
    background: var(--hcis-slate-300);
    overflow: hidden;
}

.detail-panel--lms .mini-progress.is-large {
    height: 0.48rem;
}

.detail-panel--lms .mini-progress__fill {
    height: 100%;
    border-radius: inherit;
}

.detail-panel--lms .mini-progress__fill.is-teal {
    background: linear-gradient(90deg, #14b8a6, var(--hcis-cyan-500));
}

.detail-panel--lms .chart-block {
    min-width: 0;
    margin-top: auto;
    padding: 0.42rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.7rem;
    background: var(--hcis-slate-50);
    overflow: hidden;
    flex-shrink: 0;
}

.detail-panel--lms .chart-head {
    display: flex;
    justify-content: space-between;
    gap: 0.55rem;
    align-items: center;
    margin-bottom: 0.22rem;
    min-width: 0;
}

.detail-panel--lms .chart-head span {
    color: var(--hcis-slate-600);
    font-size: 0.62rem;
    font-weight: 900;
}

.detail-panel--lms .chart-head strong {
    color: #0f766e;
    font-size: 0.62rem;
    font-weight: 950;
}

.detail-panel--lms .chart-safe {
    width: 100%;
    min-width: 0;
    overflow: hidden;
}

.detail-panel--lms .training-list {
    display: grid;
    gap: 0.3rem;
    max-height: 12rem;
    overflow-y: auto;
    padding-right: 0.18rem;
    scrollbar-width: thin;
    scrollbar-color: #99f6e4 transparent;
}

.detail-panel--lms .training-list::-webkit-scrollbar {
    width: 3px;
}

.detail-panel--lms .training-list::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: #99f6e4;
}

.detail-panel--approval {
    background: linear-gradient(135deg, #fffcf6 0%, #fef5e7 100%);
}

.detail-panel--approval .approval-list {
    max-height: 13rem;
    overflow-y: auto;
    padding-right: 0.18rem;
    scrollbar-width: thin;
    scrollbar-color: var(--hcis-amber-200) transparent;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.32rem;
}

.detail-panel--approval .approval-list .empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 1.5rem 1rem;
    min-height: 10rem;
}

.detail-panel--approval .approval-list::-webkit-scrollbar {
    width: 3px;
}

.detail-panel--approval .approval-list::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: var(--hcis-amber-200);
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
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
}

.panel-title strong {
    font-size: 0.72rem;
    font-weight: 950;
    line-height: 1.2;
    color: var(--hcis-slate-900);
}

.panel-count {
    flex: 0 0 auto;
    padding: 0.22rem 0.44rem;
    border-radius: 999px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    font-size: 0.5rem;
    font-weight: 900;
    line-height: 1;
}

.hcis-detail-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(260px, 1.4fr);
    gap: 0.35rem;
}

.hcis-priority-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.3rem;
    margin-bottom: 0.35rem;
}

.hcis-priority-item {
    min-width: 0;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 0.6rem;
    background: var(--hcis-white);
    padding: 0.45rem 0.5rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.hcis-priority-item span {
    display: block;
    font-size: 0.5rem;
    font-weight: 820;
    color: var(--hcis-slate-500);
}

.hcis-priority-item strong {
    display: block;
    margin-top: 0.08rem;
    font-size: 0.62rem;
    font-weight: 950;
    color: var(--hcis-slate-900);
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
    gap: 0.22rem;
}

.metric-grid--hcis {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.metric-grid.is-three {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.metric-grid > div.is-done {
    background: var(--hcis-green-100);
    border-color: var(--hcis-green-200);
}

.metric-grid > div.is-pending {
    background: var(--hcis-amber-100);
    border-color: var(--hcis-amber-200);
}

.metric-grid > div.is-danger {
    background: var(--hcis-red-100);
    border-color: var(--hcis-red-200);
}

.metric-grid > div.is-danger strong {
    color: var(--hcis-red-700);
}

.metric-grid > div.is-warning {
    background: var(--hcis-amber-100);
    border-color: var(--hcis-amber-200);
}

.metric-grid > div.is-warning strong {
    color: var(--hcis-amber-800);
}

.metric-grid > div.is-info {
    background: var(--hcis-sky-100);
    border-color: var(--hcis-sky-200);
}

.metric-grid > div.is-info strong {
    color: var(--hcis-sky-700);
}

/* ── Member Weekly Calendar ── */
.member-weekly-calendar {
    position: relative;
    min-width: 0;
    padding: 0.55rem;
    border-radius: 0.75rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    background: linear-gradient(135deg, var(--hcis-slate-50) 0%, var(--hcis-slate-100) 100%);
}

.weekly-cal__head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 0.5rem;
}

.weekly-cal__title {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.weekly-cal__title i {
    font-size: 0.72rem;
    color: var(--hcis-indigo-600);
}

.weekly-cal__title strong {
    font-size: 0.62rem;
    font-weight: 950;
    color: var(--hcis-slate-900);
}

.weekly-cal__nav {
    display: flex;
    align-items: center;
    gap: 0.2rem;
}

.weekly-cal__nav-btn {
    display: grid;
    place-items: center;
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 0.4rem;
    border: 1px solid var(--hcis-slate-200);
    background: #fff;
    color: var(--hcis-slate-600);
    font-size: 0.58rem;
    cursor: pointer;
    padding: 0;
    transition: all 0.15s ease;
}

.weekly-cal__nav-btn:hover:not(:disabled) {
    background: var(--hcis-indigo-600);
    color: #fff;
    border-color: var(--hcis-indigo-600);
    transform: scale(1.05);
}

.weekly-cal__nav-btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.weekly-cal__range {
    font-size: 0.62rem;
    font-weight: 800;
    color: var(--hcis-slate-600);
    min-width: 5rem;
    text-align: center;
}

.weekly-cal__grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.25rem;
}

.weekly-cal__day {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.12rem;
    padding: 0.35rem 0.15rem;
    border-radius: 0.55rem;
    border: 1.5px solid transparent;
    background: #fff;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}

.weekly-cal__day:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.weekly-cal__day.is-active {
    border-color: var(--hcis-indigo-600);
    box-shadow:
        0 0 0 2px rgba(79, 70, 229, 0.15),
        0 4px 12px rgba(0, 0, 0, 0.08);
}

.weekly-cal__day.is-today {
    border-color: var(--hcis-indigo-600);
    background: linear-gradient(135deg, var(--hcis-indigo-50) 0%, var(--hcis-indigo-100) 100%);
}

.weekly-cal__day.is-today::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 0.55rem;
    height: 2px;
    background: var(--hcis-indigo-600);
    border-radius: 0 0 2px 2px;
}

/* Tone colors for day cards */
.weekly-cal__day.is-success {
    background: linear-gradient(135deg, var(--hcis-green-50) 0%, var(--hcis-green-100) 100%);
}
.weekly-cal__day.is-warning {
    background: linear-gradient(135deg, var(--hcis-amber-50) 0%, var(--hcis-amber-100) 100%);
}
.weekly-cal__day.is-danger {
    background: linear-gradient(135deg, var(--hcis-red-50) 0%, var(--hcis-red-100) 100%);
}
.weekly-cal__day.is-info {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
}
.weekly-cal__day.is-working {
    background: linear-gradient(135deg, #f0fdfa 0%, #ccfbf1 100%);
}
.weekly-cal__day.is-default {
    background: var(--hcis-slate-100);
}
.weekly-cal__day.is-secondary {
    background: var(--hcis-slate-50);
}
/* Tanggal merah / hari libur nasional */
.weekly-cal__day.is-holiday {
    background: linear-gradient(135deg, var(--hcis-red-50) 0%, var(--hcis-red-200) 100%);
    border-color: var(--hcis-red-300);
}

.weekly-cal__day-name {
    font-size: 0.54rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-500);
}

.weekly-cal__day-num {
    font-size: 0.92rem;
    font-weight: 950;
    color: var(--hcis-slate-900);
    line-height: 1;
}

.weekly-cal__day.is-success .weekly-cal__day-num {
    color: var(--hcis-green-700);
}
.weekly-cal__day.is-warning .weekly-cal__day-num {
    color: var(--hcis-amber-700);
}
.weekly-cal__day.is-danger .weekly-cal__day-num {
    color: var(--hcis-red-600);
}
.weekly-cal__day.is-info .weekly-cal__day-num {
    color: var(--hcis-sky-700);
}
.weekly-cal__day.is-working .weekly-cal__day-num {
    color: #0d9488;
}
.weekly-cal__day.is-default .weekly-cal__day-num {
    color: var(--hcis-slate-400);
}
.weekly-cal__day.is-secondary .weekly-cal__day-num {
    color: var(--hcis-slate-400);
}
.weekly-cal__day.is-holiday .weekly-cal__day-num {
    color: var(--hcis-red-600);
}

.weekly-cal__day-dot {
    font-size: 0.72rem;
    line-height: 1;
}

.weekly-cal__day.is-success .weekly-cal__day-dot {
    color: #22c55e;
}
.weekly-cal__day.is-warning .weekly-cal__day-dot {
    color: var(--hcis-amber-500);
}
.weekly-cal__day.is-danger .weekly-cal__day-dot {
    color: var(--hcis-red-500);
}
.weekly-cal__day.is-info .weekly-cal__day-dot {
    color: var(--hcis-blue-500);
}
.weekly-cal__day.is-working .weekly-cal__day-dot {
    color: #14b8a6;
}
.weekly-cal__day.is-default .weekly-cal__day-dot {
    color: var(--hcis-slate-300);
}
.weekly-cal__day.is-secondary .weekly-cal__day-dot {
    color: var(--hcis-slate-400);
}
.weekly-cal__day.is-holiday .weekly-cal__day-dot {
    color: var(--hcis-red-600);
}

.weekly-cal__day-label {
    font-size: 0.52rem;
    font-weight: 850;
    color: var(--hcis-slate-500);
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.weekly-cal__day.is-success .weekly-cal__day-label {
    color: var(--hcis-green-600);
}
.weekly-cal__day.is-warning .weekly-cal__day-label {
    color: var(--hcis-amber-600);
}
.weekly-cal__day.is-danger .weekly-cal__day-label {
    color: var(--hcis-red-600);
}
.weekly-cal__day.is-info .weekly-cal__day-label {
    color: var(--hcis-blue-600);
}
.weekly-cal__day.is-working .weekly-cal__day-label {
    color: #0d9488;
}
.weekly-cal__day.is-secondary .weekly-cal__day-label {
    color: var(--hcis-slate-400);
}
.weekly-cal__day.is-holiday .weekly-cal__day-label {
    color: var(--hcis-red-600);
    font-weight: 900;
}

/* Day Badges */
.weekly-cal__day-badge {
    position: absolute;
    top: 0.15rem;
    font-size: 0.42rem;
    line-height: 1;
    opacity: 0.85;
}

.weekly-cal__day-badge.is-overtime {
    right: 0.15rem;
    color: var(--hcis-amber-500);
}

.weekly-cal__day-badge.is-pengajuan {
    left: 0.15rem;
    color: var(--hcis-blue-500);
}

/* ── Calendar Popover ── */
.weekly-cal__popover {
    margin-top: 0.45rem;
    padding: 0.5rem;
    border-radius: 0.65rem;
    background: #fff;
    border: 1px solid var(--hcis-slate-200);
    box-shadow:
        0 8px 24px rgba(0, 0, 0, 0.06),
        0 2px 8px rgba(0, 0, 0, 0.04);
}

.weekly-cal__popover-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.35rem;
    margin-bottom: 0.4rem;
    padding-bottom: 0.35rem;
    border-bottom: 1px solid var(--hcis-slate-100);
}

.weekly-cal__popover-head > div {
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
}

.weekly-cal__popover-head strong {
    font-size: 0.62rem;
    font-weight: 950;
    color: var(--hcis-slate-900);
}

.weekly-cal__popover-head .tone-text {
    font-size: 0.52rem;
    font-weight: 850;
}

.tone-text.is-success {
    color: var(--hcis-green-600);
}
.tone-text.is-warning {
    color: var(--hcis-amber-600);
}
.tone-text.is-danger {
    color: var(--hcis-red-600);
}
.tone-text.is-info {
    color: var(--hcis-blue-600);
}
.tone-text.is-working {
    color: #0d9488;
}
.tone-text.is-default {
    color: var(--hcis-slate-400);
}

.weekly-cal__popover-close {
    display: grid;
    place-items: center;
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 0.35rem;
    border: none;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    font-size: 0.6rem;
    cursor: pointer;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.weekly-cal__popover-close:hover {
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-900);
}

.weekly-cal__popover-body {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.weekly-cal__popover-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.25rem 0.35rem;
    border-radius: 0.4rem;
    background: var(--hcis-slate-50);
    font-size: 0.52rem;
}

.weekly-cal__popover-row span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--hcis-slate-500);
    font-weight: 800;
}

.weekly-cal__popover-row span i {
    font-size: 0.55rem;
}

.weekly-cal__popover-row strong {
    font-weight: 950;
    color: var(--hcis-slate-900);
}

.weekly-cal__popover-row.is-warning {
    background: var(--hcis-amber-50);
}
.weekly-cal__popover-row.is-warning strong {
    color: var(--hcis-amber-700);
}

.weekly-cal__popover-row.is-orange {
    background: #fff7ed;
}
.weekly-cal__popover-row.is-orange strong {
    color: var(--hcis-orange-600);
}

.weekly-cal__popover-row.is-info {
    background: #eff6ff;
}
.weekly-cal__popover-row.is-info strong {
    color: #1d4ed8;
}

.weekly-cal__popover-row.is-holiday {
    background: var(--hcis-red-50);
}
.weekly-cal__popover-row.is-holiday span,
.weekly-cal__popover-row.is-holiday strong {
    color: var(--hcis-red-600);
}

/* ── Riwayat Absensi trigger button (inside popover) ── */
.weekly-cal__riwayat-btn {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    width: 100%;
    margin-top: 0.5rem;
    padding: 0.55rem 0.7rem;
    border: 1px solid var(--hcis-indigo-200);
    border-radius: 0.6rem;
    background: linear-gradient(135deg, var(--hcis-indigo-50), var(--hcis-violet-50));
    color: var(--hcis-indigo-700);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition:
        background 0.18s ease,
        border-color 0.18s ease,
        transform 0.12s ease;
}
.weekly-cal__riwayat-btn:hover {
    background: linear-gradient(135deg, var(--hcis-indigo-100), #ede9fe);
    border-color: var(--hcis-indigo-300);
}
.weekly-cal__riwayat-btn:active {
    transform: scale(0.985);
}
.weekly-cal__riwayat-btn span {
    flex: 1;
    text-align: left;
}
.weekly-cal__riwayat-btn i:last-child {
    font-size: 1.05rem;
}

/* ── Riwayat Absensi modal body ── */
.riwayat-absensi {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0.25rem 0;
}
.riwayat-absensi__meta {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.6rem;
    background: var(--hcis-slate-100);
    border-radius: 0.55rem;
    color: var(--hcis-slate-700);
    font-size: 0.82rem;
    font-weight: 600;
    align-self: flex-start;
}
.riwayat-absensi__meta i {
    color: var(--hcis-indigo-500);
}

/* loading skeleton */
.riwayat-absensi__loading {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.riwayat-absensi__skel {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.riwayat-absensi__skel-dot {
    width: 1.8rem;
    height: 1.8rem;
    border-radius: 50%;
    flex-shrink: 0;
}
.riwayat-absensi__skel-line {
    flex: 1;
    height: 0.9rem;
    border-radius: 0.4rem;
}
.riwayat-absensi__skel-dot,
.riwayat-absensi__skel-line {
    background: linear-gradient(90deg, #eef2f7 25%, var(--hcis-slate-200) 37%, #eef2f7 63%);
    background-size: 400% 100%;
    animation: riwayat-shimmer 1.3s ease infinite;
}
@keyframes riwayat-shimmer {
    0% {
        background-position: 100% 0;
    }
    100% {
        background-position: 0 0;
    }
}

/* empty / error */
.riwayat-absensi__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1.6rem 1rem;
    color: var(--hcis-slate-400);
    text-align: center;
    font-size: 0.85rem;
}
.riwayat-absensi__empty i {
    font-size: 1.7rem;
    color: var(--hcis-slate-300);
}
.riwayat-absensi__empty.is-error {
    color: var(--hcis-red-700);
}
.riwayat-absensi__empty.is-error i {
    color: var(--hcis-red-400);
}

/* timeline */
.riwayat-absensi__timeline {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
}
.riwayat-absensi__item {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0.7rem;
    padding: 0.55rem 0 0.55rem 0;
}
.riwayat-absensi__item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 0.85rem;
    top: 2.2rem;
    bottom: -0.15rem;
    width: 2px;
    background: var(--hcis-slate-200);
}
.riwayat-absensi__icon {
    position: relative;
    z-index: 1;
    flex-shrink: 0;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 0.85rem;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    border: 2px solid #fff;
    box-shadow: 0 0 0 1px var(--hcis-slate-200);
}
.riwayat-absensi__item.is-checkin .riwayat-absensi__icon {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
    box-shadow: 0 0 0 1px var(--hcis-green-200);
}
.riwayat-absensi__item.is-checkout .riwayat-absensi__icon {
    background: var(--hcis-red-100);
    color: var(--hcis-red-700);
    box-shadow: 0 0 0 1px var(--hcis-red-200);
}
.riwayat-absensi__body {
    flex: 1;
    min-width: 0;
}
.riwayat-absensi__row-top {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.riwayat-absensi__time {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
    font-variant-numeric: tabular-nums;
}
.riwayat-absensi__tag {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    padding: 0.12rem 0.45rem;
    border-radius: 0.35rem;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}
.riwayat-absensi__tag.is-checkin {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}
.riwayat-absensi__tag.is-checkout {
    background: var(--hcis-red-100);
    color: var(--hcis-red-700);
}
.riwayat-absensi__machine {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    margin-top: 0.2rem;
    font-size: 0.75rem;
    color: var(--hcis-slate-400);
}

/* ── Calendar Legend ── */
.weekly-cal__legend {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.45rem;
    padding-top: 0.35rem;
    border-top: 1px solid var(--hcis-slate-200);
}

.weekly-cal__legend span {
    display: inline-flex;
    align-items: center;
    gap: 0.18rem;
    font-size: 0.56rem;
    font-weight: 850;
    color: var(--hcis-slate-500);
}

.weekly-cal__legend i {
    width: 0.55rem;
    height: 0.55rem;
    border-radius: 50%;
    display: inline-block;
}

.weekly-cal__legend i.is-success {
    background: #22c55e;
}
.weekly-cal__legend i.is-warning {
    background: var(--hcis-amber-500);
}
.weekly-cal__legend i.is-danger {
    background: var(--hcis-red-500);
}
.weekly-cal__legend i.is-working {
    background: #14b8a6;
}
.weekly-cal__legend i.is-info {
    background: var(--hcis-blue-500);
}
.weekly-cal__legend i.is-default {
    background: var(--hcis-slate-300);
}
.weekly-cal__legend i.is-holiday {
    background: var(--hcis-red-600);
}

/* Skeleton loading */
.weekly-cal__grid--skeleton {
    gap: 0.25rem;
}

.weekly-cal__day-skel {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.18rem;
    padding: 0.35rem 0.15rem;
    border-radius: 0.55rem;
    background: var(--hcis-slate-100);
    animation: cal-skel-pulse 1.2s ease-in-out infinite;
}

.weekly-cal__day-skel span {
    display: block;
    border-radius: 0.25rem;
    background: var(--hcis-slate-200);
}

.weekly-cal__day-skel span:nth-child(1) {
    width: 1.4rem;
    height: 0.4rem;
}
.weekly-cal__day-skel span:nth-child(2) {
    width: 1rem;
    height: 0.7rem;
    border-radius: 0.3rem;
}
.weekly-cal__day-skel span:nth-child(3) {
    width: 0.6rem;
    height: 0.6rem;
    border-radius: 50%;
}
.weekly-cal__day-skel span:nth-child(4) {
    width: 1.6rem;
    height: 0.35rem;
}

@keyframes cal-skel-pulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.45;
    }
}

.weekly-cal__range-skel {
    display: inline-block;
    width: 5.5rem;
    height: 0.55rem;
    border-radius: 0.25rem;
    background: var(--hcis-slate-200);
    animation: cal-skel-pulse 1.2s ease-in-out infinite;
}

/* Loading overlay for grid */
.weekly-cal__grid.is-loading {
    opacity: 0.5;
    pointer-events: none;
    transition: opacity 0.2s ease;
}

/* Empty state */
.weekly-cal__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.3rem;
    padding: 1.2rem 0.5rem;
    color: var(--hcis-slate-400);
}

.weekly-cal__empty i {
    font-size: 1.2rem;
}

.weekly-cal__empty span {
    font-size: 0.52rem;
    font-weight: 800;
}

/* CI/CO Popover Grid */
.weekly-cal__popover-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.25rem;
    margin-bottom: 0.25rem;
}

.weekly-cal__popover-cell {
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
    padding: 0.35rem;
    border-radius: 0.45rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-100);
}

.weekly-cal__popover-cell-label {
    display: flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.45rem;
    font-weight: 800;
    color: var(--hcis-slate-400);
}

.weekly-cal__popover-cell-label i {
    font-size: 0.48rem;
}

.weekly-cal__popover-cell strong {
    font-size: 0.68rem;
    font-weight: 950;
    color: var(--hcis-slate-900);
}

/* Tone badge pill */
.tone-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.1rem 0.35rem;
    border-radius: 999px;
    font-size: 0.45rem;
    font-weight: 900;
}

.tone-badge.is-success {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}
.tone-badge.is-warning {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
}
.tone-badge.is-danger {
    background: var(--hcis-red-100);
    color: var(--hcis-red-600);
}
.tone-badge.is-info {
    background: #dbeafe;
    color: #1d4ed8;
}
.tone-badge.is-working {
    background: #ccfbf1;
    color: #0d9488;
}
.tone-badge.is-default {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
}

.weekly-cal__legend i.is-default {
    background: var(--hcis-slate-300);
}

/* Popover transition */
.cal-popover-fade-enter-active,
.cal-popover-fade-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
}

.cal-popover-fade-enter-from,
.cal-popover-fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

/* Responsive: stack calendar on very small screens */
@media (max-width: 480px) {
    .weekly-cal__grid {
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 0.15rem;
    }

    .weekly-cal__day {
        padding: 0.25rem 0.1rem;
    }

    .weekly-cal__day-num {
        font-size: 0.78rem;
    }

    .weekly-cal__day-label {
        font-size: 0.46rem;
    }

    .weekly-cal__popover-row {
        font-size: 0.48rem;
    }

    .weekly-cal__popover-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.15rem;
    }
}

/* Keep old subsection styles for non-calendar usage */
.subsection-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.35rem;
}

.subsection-head strong {
    font-size: 0.62rem;
    font-weight: 950;
    color: var(--hcis-slate-900);
}

.subsection-head span {
    font-size: 0.52rem;
    font-weight: 750;
    color: var(--hcis-slate-500);
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
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-800);
}

.deadline-strip.is-success,
.module-alert.is-success {
    background: var(--hcis-green-100);
    color: #166534;
}

.deadline-strip.is-info,
.module-alert.is-info {
    background: var(--hcis-sky-100);
    color: var(--hcis-sky-700);
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
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
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
    color: var(--hcis-slate-500);
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
    background: linear-gradient(180deg, #8b5cf6, var(--hcis-indigo-500));
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

.training-list {
    min-height: 7.5rem;
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
    color: var(--hcis-slate-900);
    line-height: 1.25;
}

.issue-item span {
    display: block;
    margin-top: 0.05rem;
    font-size: 0.56rem;
    line-height: 1.3;
    color: var(--hcis-slate-500);
}

.issue-item.is-danger {
    background: var(--hcis-red-100);
    border-color: var(--hcis-red-200);
}

.issue-item.is-danger i {
    color: var(--hcis-red-600);
}

.issue-item.is-warning {
    background: var(--hcis-amber-100);
    border-color: var(--hcis-amber-200);
}

.issue-item.is-warning i {
    color: var(--hcis-amber-600);
}

.issue-item.is-info,
.issue-item.is-orange {
    background: var(--hcis-sky-100);
    border-color: var(--hcis-sky-200);
}

.issue-item.is-info i,
.issue-item.is-orange i {
    color: var(--hcis-sky-600);
}

.progress-line {
    width: 100%;
    height: 0.38rem;
    margin-top: 0.22rem;
    border-radius: 999px;
    background: var(--hcis-slate-200);
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
    background: linear-gradient(90deg, #8b5cf6, var(--hcis-indigo-500));
}

.progress-line__fill.is-teal {
    background: linear-gradient(90deg, #14b8a6, var(--hcis-cyan-500));
}

.parameter-row > div:first-child {
    display: flex;
    justify-content: space-between;
    gap: 0.4rem;
}

.parameter-row span {
    font-size: 0.56rem;
    font-weight: 850;
    color: var(--hcis-slate-600);
}

.parameter-row strong {
    font-size: 0.56rem;
    font-weight: 950;
    color: var(--hcis-slate-900);
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
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
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
    color: var(--hcis-slate-900);
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
    color: var(--hcis-slate-500);
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
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}

.training-status.is-progress {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
}

.training-status.is-pending {
    background: var(--hcis-red-100);
    color: var(--hcis-red-600);
}

/* Keep LMS training rows readable on narrow detail panels. */
.detail-panel--lms .training-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: flex-start;
}

.detail-panel--lms .training-row > div {
    min-width: 0;
}

.detail-panel--lms .training-row strong {
    white-space: normal;
    overflow: visible;
    text-overflow: clip;
    overflow-wrap: anywhere;
    line-height: 1.25;
}

.detail-panel--lms .training-row span {
    word-break: break-word;
}

.detail-panel--lms .training-status {
    margin-top: 0;
    white-space: nowrap;
    align-self: flex-start;
}

.approval-item {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    cursor: pointer;
    font: inherit;
    text-align: inherit;
    color: inherit;
}

.approval-item i {
    color: var(--hcis-green-600);
}

.approval-item small,
.approval-side-item small {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}

.approval-item small.is-expired,
.approval-side-item small.is-expired {
    background: var(--hcis-red-100);
    color: var(--hcis-red-700);
}

.approval-item small.is-pending,
.approval-side-item small.is-pending {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
}

.approval-item small.is-future,
.approval-side-item small.is-future {
    background: #dbeafe;
    color: #1d4ed8;
}

.approval-item small.is-approved,
.approval-side-item small.is-approved {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}

.approval-side-item__meta {
    margin-top: 0.08rem;
    font-size: 0.5rem;
    font-weight: 800;
    color: var(--hcis-slate-700);
}

.approval-side-item__reason {
    margin-top: 0.08rem;
    font-size: 0.5rem;
    line-height: 1.35;
    color: var(--hcis-slate-500);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.approval-side-item__expiry {
    margin-top: 0.08rem;
    font-size: 0.5rem;
    font-weight: 600;
    color: var(--hcis-red-500) !important;
}

.approval-expiry {
    display: block;
    margin-top: 0.12rem;
    font-size: 0.55rem;
    font-weight: 600;
    color: var(--hcis-red-500);
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
    border: 1px solid var(--hcis-slate-200);
    background: #fff;
    cursor: pointer;
    text-align: left;
    transition:
        opacity 0.25s ease,
        background-color 0.25s ease,
        border-color 0.25s ease,
        filter 0.25s ease;
}

.action-card.is-disabled {
    cursor: wait;
    opacity: 0.92;
}

.action-card.is-pending {
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.28);
}

/* Item yang sudah dibaca: abu-abu & muted, override warna tone. */
.action-card.is-read {
    background: var(--hcis-slate-100) !important;
    border-color: var(--hcis-slate-200) !important;
    filter: grayscale(1);
    opacity: 0.6;
}

.action-card.is-read .action-card__icon {
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-400);
}

.action-card.is-read .action-card__body strong {
    color: var(--hcis-slate-500);
    font-weight: 800;
}

.action-card.is-read .action-case-chip {
    filter: grayscale(1);
}

.action-card.is-read:hover {
    opacity: 0.85;
}

.action-card__icon {
    width: 1.7rem;
    height: 1.7rem;
    display: grid;
    place-items: center;
    border-radius: 0.56rem;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}

.action-card__body {
    min-width: 0;
}

.action-card__body strong {
    display: block;
    font-size: 0.58rem;
    font-weight: 950;
    color: var(--hcis-slate-900);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.action-card__body span {
    display: block;
    margin-top: 0.06rem;
    font-size: 0.52rem;
    color: var(--hcis-slate-500);
    line-height: 1.25;
}

.action-card__body small {
    display: block;
    margin-top: 0.08rem;
    font-size: 0.5rem;
    font-weight: 900;
    color: var(--hcis-slate-600);
}

.action-card__actions {
    display: flex;
    align-items: center;
    gap: 0.28rem;
}

.action-card__done {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 1.45rem;
    padding: 0 0.6rem;
    border-radius: 999px;
    border: 1px solid var(--hcis-indigo-200);
    background: var(--hcis-white);
    color: var(--hcis-indigo-700);
    font-size: 0.55rem;
    font-weight: 800;
    white-space: nowrap;
    cursor: pointer;
    transition: all 0.2s ease;
}

.action-card__done:hover:not(:disabled) {
    background: #f5f7ff;
    border-color: var(--hcis-indigo-400);
}

.action-card__done:disabled {
    cursor: wait;
    opacity: 0.72;
}

.action-loading-spin {
    display: inline-block;
    animation: action-spin 0.75s linear infinite;
}

@keyframes action-spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
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
    background: var(--hcis-red-100);
    border-color: var(--hcis-red-200);
    color: var(--hcis-red-800);
}

.action-case-chip.is-warning {
    background: var(--hcis-amber-100);
    border-color: var(--hcis-amber-300);
    color: var(--hcis-amber-800);
}

.action-case-chip.is-orange {
    background: var(--hcis-orange-100);
    border-color: var(--hcis-orange-300);
    color: #9a3412;
}

.action-case-chip.is-info {
    background: var(--hcis-sky-100);
    border-color: var(--hcis-sky-300);
    color: #075985;
}

.action-case-dates {
    margin-top: 0.08rem;
    font-size: 0.48rem;
    color: var(--hcis-slate-600);
    font-weight: 750;
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

.action-card.is-success,
.action-card.is-orange {
    background: var(--hcis-green-50);
    border-color: var(--hcis-green-200);
}

.heatmap-board {
    display: grid;
    gap: 0.34rem;
}

.heatmap-row {
    display: grid;
    grid-template-columns: 7.5rem max-content minmax(8rem, 1fr);
    align-items: center;
    gap: 0.55rem;
    padding: 0.16rem 0;
}

.heatmap-row--button {
    width: 100%;
    padding: 0.16rem 0;
    border: 0;
    background: transparent;
    text-align: left;
    font: inherit;
    cursor: pointer;
    border-radius: 0.38rem;
}

.heatmap-row--button:hover {
    background: var(--hcis-slate-50);
}

.heatmap-row--button:hover strong {
    color: var(--hcis-blue-600);
}

.heatmap-row--button:focus-visible {
    outline: 2px solid var(--hcis-blue-600);
    outline-offset: 2px;
}

/* Nama anggota: sticky di kiri saat area hari di-scroll horizontal. */
.heatmap-row strong {
    position: sticky;
    left: 0;
    z-index: 2;
    background: #fff;
    padding-right: 0.4rem;
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.heatmap-row--button:hover strong {
    background: var(--hcis-slate-50);
}

/* Baris header tanggal di atas grid. */
.heatmap-row--header {
    padding-bottom: 0.3rem;
    margin-bottom: 0.15rem;
    border-bottom: 1px solid #eef2f7;
    align-items: end;
}

.heatmap-row--header .heatmap-corner {
    background: #fff;
}

.heat-dayhead {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1px;
    width: 16px;
    font-variant-numeric: tabular-nums;
}

.heat-dayhead em {
    font-style: normal;
    font-size: 0.46rem;
    font-weight: 700;
    line-height: 1;
    color: var(--hcis-slate-400);
}

.heat-dayhead b {
    font-size: 0.56rem;
    font-weight: 700;
    line-height: 1;
    color: var(--hcis-slate-500);
}

.heat-dayhead.is-weekstart b {
    color: var(--hcis-slate-900);
    font-weight: 900;
}

.heat-dayhead.is-weekend b {
    color: var(--hcis-rose-500);
}

.heat-dayhead.is-today b {
    color: #fff;
    background: var(--hcis-blue-600);
    border-radius: 999px;
    padding: 1px 3px;
}

/* Card heatmap (kolom kiri, di atas Team List). */
.heatmap-card {
    background: #fff;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.9rem;
    padding: 0.85rem 0.95rem;
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.04);
}

.heatmap-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem 0.75rem;
    margin-bottom: 0.7rem;
}

.heatmap-card__title h2 {
    font-size: 1.02rem;
    font-weight: 800;
    margin: 0;
}

.heatmap-card__controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.heatmap-period {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    height: 1.95rem;
    padding: 0 0.5rem;
    border-radius: 0.6rem;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-500);
    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease;
}

.heatmap-period:hover {
    border-color: var(--hcis-slate-300);
}

.heatmap-period:focus-within {
    border-color: var(--hcis-blue-500);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.heatmap-period i {
    font-size: 0.8rem;
    color: var(--hcis-slate-500);
}

.heatmap-search {
    height: 2rem;
    width: 220px;
    max-width: 100%;
}

/* Kontrol mobile (stack di bawah trigger accordion). */
.heatmap-controls-mobile {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 0.55rem;
}

.heatmap-controls-mobile .heatmap-search {
    width: 100%;
}

.heatmap-range-note {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    margin-bottom: 0.5rem;
}

/* Hanya area hari + badges yang scroll; nama sticky tetap terlihat. */
.heatmap-scroll {
    overflow-x: auto;
    overflow-y: hidden;
    padding-bottom: 0.3rem;
}

/* Badge ringkasan per-kategori di kanan tiap baris. */
.heat-badges {
    display: flex;
    flex-wrap: nowrap;
    gap: 0.26rem;
    justify-content: flex-start;
    padding-left: 0.3rem;
}

.heat-badges--header {
    align-items: flex-end;
    display: none;
}

.heat-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.16rem;
    font-size: 0.6rem;
    font-weight: 800;
    line-height: 1;
    padding: 0.16rem 0.34rem;
    border-radius: 999px;
    white-space: nowrap;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}

/* Badge bernilai 0 diredupkan supaya yang penting menonjol. */
.heat-badge.is-zero {
    opacity: 0.4;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-400);
}

/* Badge header (hanya ikon, sebagai penanda kolom). */
.heat-badge.is-ghost {
    background: transparent;
    padding: 0.1rem;
}

.heat-badge i {
    font-size: 0.6rem;
}

.heat-badge.tone-success {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}
.heat-badge.tone-warning {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
}
.heat-badge.tone-orange {
    background: var(--hcis-orange-100);
    color: #c2410c;
}
.heat-badge.tone-working {
    background: #dbeafe;
    color: #1d4ed8;
}
.heat-badge.tone-info {
    background: var(--hcis-cyan-100);
    color: var(--hcis-cyan-700);
}
.heat-badge.tone-info2 {
    background: #ede9fe;
    color: var(--hcis-violet-700);
}
.heat-badge.tone-danger {
    background: var(--hcis-red-100);
    color: var(--hcis-red-700);
}
.heat-badge.tone-danger2 {
    background: var(--hcis-red-100);
    color: var(--hcis-rose-700);
}
.heat-badge.tone-danger3 {
    background: var(--hcis-rose-100);
    color: #9f1239;
}

/* Dropdown bulan cutoff di kontrol heatmap. */
.heatmap-period select.heatmap-month-select {
    height: 100% !important;
    padding: 0 0.25rem 0 0 !important;
    border: none !important;
    background: transparent !important;
    color: var(--hcis-slate-900) !important;
    font-size: 0.72rem !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    outline: none !important;
    box-shadow: none !important;
}

.heatmap-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 0.85rem;
    margin-top: 0.55rem;
    padding-top: 0.5rem;
    border-top: 1px dashed var(--hcis-slate-200);
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
    color: var(--hcis-slate-500);
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

/* === Responsif heatmap-card (struktur baru) === */
@media (max-width: 768px) {
    .heatmap-card {
        padding: 0.7rem;
    }

    /* Kolom nama lebih sempit + sel sedikit dikecilkan agar lega di layar kecil. */
    .heatmap-row {
        grid-template-columns: 6.5rem max-content minmax(6rem, 1fr);
        gap: 0.4rem;
    }

    .heatmap-row strong {
        font-size: 0.66rem;
    }

    .heatmap-row__days {
        grid-auto-columns: 14px;
    }

    .heat-cell {
        width: 14px;
        height: 14px;
    }

    .heat-dayhead {
        width: 14px;
    }

    .heatmap-card__controls .heatmap-search {
        width: 180px;
    }
}

@media (max-width: 480px) {
    .heatmap-row {
        grid-template-columns: 5.2rem max-content minmax(5rem, 1fr);
    }

    .heat-badge {
        font-size: 0.56rem;
        padding: 0.14rem 0.28rem;
    }
}

.heatmap-legend span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--hcis-slate-500);
    font-size: 0.5rem;
    font-weight: 850;
}

.heatmap-legend i {
    width: 0.62rem;
    height: 0.62rem;
    border-radius: 0.18rem;
    flex-shrink: 0;
}

/* Legacy tone classes (masih dipakai di tempat lain bila ada). */
.heatmap-legend i.is-success {
    background: #86efac;
}
.heatmap-legend i.is-warning {
    background: var(--hcis-amber-200);
}
.heatmap-legend i.is-danger {
    background: var(--hcis-red-300);
}
.heatmap-legend i.is-info {
    background: var(--hcis-sky-200);
}
.heatmap-legend i.is-working {
    background: #60a5fa;
}

/* 9 kategori klasifikasi (selaras dgn .heat-badge.tone-*). */
.heatmap-legend i.tone-success {
    background: #86efac;
}
.heatmap-legend i.tone-warning {
    background: var(--hcis-amber-200);
}
.heatmap-legend i.tone-orange {
    background: var(--hcis-orange-300);
}
.heatmap-legend i.tone-working {
    background: #60a5fa;
}
.heatmap-legend i.tone-info {
    background: var(--hcis-cyan-300);
}
.heatmap-legend i.tone-info2 {
    background: #c4b5fd;
}
.heatmap-legend i.tone-danger {
    background: var(--hcis-red-300);
}
.heatmap-legend i.tone-danger2 {
    background: #fb7185;
}
.heatmap-legend i.tone-danger3 {
    background: var(--hcis-rose-500);
}

.empty-state {
    width: 100%;
    display: grid;
    place-items: center;
    gap: 0.16rem;
    min-height: 7rem;
    padding: 0.75rem;
    border: 1px dashed var(--hcis-slate-300);
    border-radius: 0.68rem;
    background: var(--hcis-slate-50);
    text-align: center;
    color: var(--hcis-slate-500);
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
    color: var(--hcis-slate-400);
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
    color: var(--hcis-slate-900);
    font-size: 0.66rem;
    font-weight: 950;
}

.empty-state span {
    display: block;
    max-width: 20rem;
    color: var(--hcis-slate-500);
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
    color: var(--hcis-slate-400);
    font-size: 1.05rem;
    transition:
        transform 0.18s ease,
        color 0.18s ease;
}

.quick-stat-card:hover .quick-stat-card__arrow {
    transform: translateX(0.16rem);
    color: var(--hcis-slate-700);
}

.quick-stat-card.is-primary .quick-stat-card__icon {
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-600);
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
    color: var(--hcis-slate-500);
}

.kpi-year-select {
    min-height: 1.75rem;
    border-radius: 0.5rem;
    border: 1px solid #dbe3ef;
    background: var(--hcis-white);
    color: var(--hcis-slate-900);
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
    border: 1px solid var(--hcis-slate-200);
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.95), transparent 4rem),
        linear-gradient(135deg, var(--hcis-slate-50), var(--hcis-white));
}

.leader-score-card span,
.leader-score-card small {
    display: block;
    font-size: 0.56rem;
    font-weight: 900;
    color: var(--hcis-slate-500);
}

.leader-score-card strong {
    display: block;
    margin: 0.18rem 0;
    font-size: 1.62rem;
    line-height: 1;
    font-weight: 950;
    letter-spacing: -0.05em;
    color: var(--hcis-slate-900);
}

.leader-score-card.is-hcis {
    background:
        radial-gradient(circle at top right, rgba(14, 165, 233, 0.15), transparent 5rem),
        linear-gradient(135deg, var(--hcis-slate-50), var(--hcis-white));
}

.leader-score-card.is-kpi {
    background:
        radial-gradient(circle at top right, rgba(124, 58, 237, 0.15), transparent 5rem),
        linear-gradient(135deg, var(--hcis-slate-50), var(--hcis-white));
}

.leader-score-card.is-lms {
    background:
        radial-gradient(circle at top right, rgba(20, 184, 166, 0.12), transparent 5rem),
        linear-gradient(135deg, #f8fbfb, var(--hcis-white));
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
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
}

.leader-mini-stat-grid span,
.leader-mini-stat-grid small {
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 0.5rem;
    font-weight: 850;
    color: var(--hcis-slate-500);
}

.leader-mini-stat-grid strong {
    display: block;
    margin: 0.08rem 0;
    font-size: 0.95rem;
    font-weight: 950;
    color: var(--hcis-slate-900);
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

.leader-mini-stat-grid .is-purple {
    background: var(--hcis-violet-50);
    border-color: #ddd6fe;
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
    font-size: 0.66rem;
    font-weight: 950;
    color: var(--hcis-slate-900);
}

.line-chart-card__head span,
.line-chart-card__head small {
    font-size: 0.52rem;
    font-weight: 850;
    color: var(--hcis-slate-500);
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
    color: var(--hcis-slate-500);
    z-index: 3;
}

.leader-apex-chart :deep(.apexcharts-canvas),
.leader-apex-chart :deep(.apexcharts-svg) {
    width: 100% !important;
}

.leader-apex-chart :deep(.apexcharts-tooltip) {
    border: 1px solid var(--hcis-slate-200);
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
    border-bottom: 1px solid var(--hcis-slate-200);
    background: var(--hcis-white);
}

.status-filter-chip {
    appearance: none;
    -webkit-appearance: none;
    flex: 0 0 auto;
    min-height: 2rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 999px;
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-700);
    padding: 0.28rem 0.62rem;
    font-size: 0.68rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.status-filter-chip b {
    font-size: 0.64rem;
    color: var(--hcis-slate-500);
}

.status-filter-chip.is-active {
    background: var(--hcis-indigo-50);
    border-color: var(--hcis-indigo-200);
    color: var(--hcis-indigo-800);
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
    background: var(--hcis-slate-50);
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.46rem 0.62rem;
}

.attendance-status-search i {
    color: var(--hcis-slate-400);
    font-size: 0.82rem;
}

.attendance-status-search input {
    border: none;
    outline: none;
    background: transparent;
    color: var(--hcis-slate-900);
    width: 100%;
    font-size: 0.78rem;
    font-weight: 600;
}

.attendance-status-search input::placeholder {
    color: var(--hcis-slate-400);
    font-weight: 500;
}

.attendance-status-clear-btn {
    appearance: none;
    -webkit-appearance: none;
    border: 1px solid var(--hcis-slate-200);
    background: #fff;
    color: var(--hcis-slate-700);
    border-radius: 0.56rem;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.44rem 0.62rem;
    white-space: nowrap;
}

.attendance-status-clear-btn:hover {
    background: var(--hcis-slate-50);
}

.attendance-issue-card.is-skeleton .skeleton-line {
    width: 100%;
    height: 0.62rem;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--hcis-slate-200) 25%, var(--hcis-slate-100) 50%, var(--hcis-slate-200) 75%);
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
    background: var(--hcis-red-100);
    color: var(--hcis-red-600);
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
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-white);
}

.kpi-watch-item.is-alert {
    background: var(--hcis-amber-50);
    border-color: var(--hcis-amber-200);
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
    color: var(--hcis-slate-900);
}

.kpi-watch-item span {
    margin-top: 0.08rem;
    font-size: 0.5rem;
    font-weight: 800;
    color: var(--hcis-slate-500);
}

.kpi-watch-item b {
    flex: 0 0 auto;
    width: 2rem;
    height: 2rem;
    display: grid;
    place-items: center;
    border-radius: 0.62rem;
    background: var(--hcis-violet-50);
    color: var(--hcis-violet-700);
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
        linear-gradient(180deg, var(--hcis-white), var(--hcis-slate-50)),
        radial-gradient(circle at top left, rgba(79, 70, 229, 0.08), transparent 20rem);
}

.detail-top {
    position: relative;
    padding: 0.7rem 0.75rem;
    border-radius: 0.85rem;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-white);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
}

.detail-tags .tag {
    border: 1px solid transparent;
}

.tag.is-danger {
    background: var(--hcis-red-100);
    color: var(--hcis-red-700);
    border-color: var(--hcis-red-200);
}

.tag.is-warning {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-800);
    border-color: var(--hcis-amber-200);
}

.tag.is-success {
    background: var(--hcis-green-100);
    color: #166534;
    border-color: var(--hcis-green-200);
}

.tag.is-info {
    background: #dbeafe;
    color: #1d4ed8;
    border-color: #bfdbfe;
}

.detail-grid {
    align-items: stretch;
}

.detail-panel {
    border-radius: 0.85rem;
    background: linear-gradient(135deg, var(--hcis-slate-50) 0%, var(--hcis-slate-100) 100%);
    box-shadow: 0 10px 26px rgba(15, 23, 42, 0.03);
}

.detail-panel__head {
    padding: 0.38rem 0.42rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.68rem;
    background: var(--hcis-slate-50);
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
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    color: var(--hcis-slate-700);
}

.metric-grid > div,
.kpi-period-box > div {
    min-width: 0;
    border-radius: 0.68rem;
    background: var(--hcis-slate-50);
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
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-700);
    font-size: 0.56rem;
    font-weight: 850;
    padding: 0 0.42rem;
    outline: none;
}

.kpi-panel-apply-btn {
    flex: 0 0 auto;
    height: 1.72rem;
    border-radius: 0.56rem;
    border: 1px solid var(--hcis-indigo-200);
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-800);
    font-size: 0.56rem;
    font-weight: 900;
    padding: 0 0.58rem;
}

.kpi-panel-apply-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

/* ── KPI Warning List & Inline Warnings ── */
.detail-panel--kpi .kpi-warning-list {
    display: grid;
    gap: 0.35rem;
    margin-bottom: 0.45rem;
}
.detail-panel--kpi .inline-warning {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.48rem 0.5rem;
    border-radius: 0.66rem;
    font-size: 0.68rem;
    font-weight: 850;
}
.detail-panel--kpi .inline-warning.is-warning {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-800);
}
.detail-panel--kpi .inline-warning.is-info {
    background: var(--hcis-sky-100);
    color: #075985;
}
.detail-panel--kpi .inline-warning.is-success {
    background: var(--hcis-green-100);
    color: #166534;
}
.detail-panel--kpi .inline-warning.is-danger {
    background: var(--hcis-red-100);
    color: var(--hcis-red-800);
}

/* ── KPI Detail Stack & Filter Row ── */
.detail-panel--kpi .kpi-detail-stack {
    display: grid;
    gap: 0.55rem;
    min-width: 0;
}
.detail-panel--kpi .kpi-filter-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.28rem;
    min-width: 0;
}
.detail-panel--kpi .kpi-filter-row select {
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
.detail-panel--kpi .kpi-filter-row select:disabled {
    opacity: 0.55;
    cursor: wait;
}

/* ── KPI Card Layout (sama dengan homepage) ── */
.detail-panel--kpi .kpi-layout {
    display: grid;
    grid-template-columns: 120px minmax(0, 1fr);
    gap: 0.45rem;
    min-width: 0;
    margin-bottom: 0.42rem;
}

.detail-panel--kpi .kpi-score-card {
    min-width: 0;
    min-height: 6.5rem;
    border-radius: 0.8rem;
    display: grid;
    place-items: center;
    align-content: center;
    gap: 0.22rem;
    padding: 0.72rem 0.6rem;
    background: linear-gradient(135deg, var(--hcis-violet-700) 0%, var(--hcis-indigo-600) 55%, var(--hcis-indigo-700) 100%);
    color: #fff;
    box-shadow: 0 14px 28px rgba(67, 56, 202, 0.2);
    text-align: center;
}

.detail-panel--kpi .kpi-score-card > strong {
    font-size: 1.5rem;
    font-weight: 950;
    line-height: 1;
}

.detail-panel--kpi .kpi-score-card > span,
.detail-panel--kpi .kpi-score-card > small {
    display: block;
    margin-top: 0.06rem;
    font-size: 0.54rem;
    font-weight: 850;
    opacity: 0.9;
}

.detail-panel--kpi .kpi-score-card__mini {
    width: 100%;
    margin-top: 0.18rem;
    padding: 0.4rem 0.38rem 0.34rem;
    border-radius: 0.68rem;
    background: rgba(255, 255, 255, 0.12);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.14);
    display: grid;
    gap: 0.05rem;
    text-align: center;
}

.detail-panel--kpi .kpi-score-card__mini span {
    display: block;
    font-size: 0.58rem;
    font-weight: 900;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    opacity: 0.88;
}

.detail-panel--kpi .kpi-score-card__mini strong {
    display: block;
    font-size: 0.92rem;
    font-weight: 950;
    line-height: 1;
}

.detail-panel--kpi .kpi-score-card__mini small {
    display: block;
    font-size: 0.56rem;
    font-weight: 800;
    opacity: 0.82;
}

.detail-panel--kpi .kpi-grade-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.3rem;
    padding: 0.18rem 0;
}

.detail-panel--kpi .kpi-grade-row + .kpi-grade-row {
    border-top: 1px solid rgba(255, 255, 255, 0.12);
}

.detail-panel--kpi .kpi-grade-row span {
    font-size: 0.56rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.8;
    flex-shrink: 0;
}

.detail-panel--kpi .kpi-grade-row strong {
    font-size: 0.72rem;
    font-weight: 950;
    line-height: 1;
    text-align: right;
}

.detail-panel--kpi .kpi-detail-side {
    display: flex;
    flex-direction: column;
    gap: 0.38rem;
    min-width: 0;
}

.detail-panel--kpi .kpi-stat-grid {
    display: grid;
    gap: 0.35rem;
    min-width: 0;
}

.detail-panel--kpi .kpi-stat-grid > div {
    min-width: 0;
    padding: 0.44rem;
    border-radius: 0.62rem;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
}

.detail-panel--kpi .kpi-stat-grid span,
.detail-panel--kpi .kpi-stat-grid small {
    display: block;
    color: var(--hcis-slate-500);
    font-size: 0.62rem;
    font-weight: 850;
}

.detail-panel--kpi .kpi-stat-grid strong {
    display: block;
    margin-top: 0.06rem;
    color: var(--hcis-slate-900);
    font-size: 0.7rem;
    font-weight: 950;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* ── KPI List Header ── */
.detail-panel--kpi .kpi-list-header {
    display: flex;
    align-items: center;
    gap: 0.26rem;
    margin-top: 0.12rem;
    margin-bottom: -0.18rem;
}

.detail-panel--kpi .kpi-list-header > i {
    font-size: 0.66rem;
    color: var(--hcis-violet-600);
}

.detail-panel--kpi .kpi-list-header > span {
    color: var(--hcis-slate-600);
    font-size: 0.6rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* ── Mini Parameter List ── */
.detail-panel--kpi .mini-parameter-list {
    display: flex;
    flex-direction: column;
    gap: 0.32rem;
    margin-top: 0.46rem;
    min-width: 0;
    max-height: 9rem;
    overflow-y: auto;
    padding-right: 0.18rem;
    scrollbar-width: thin;
    scrollbar-color: #ddd6fe transparent;
}

.detail-panel--kpi .mini-parameter-list .empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 1rem;
    min-height: 8rem;
}

.detail-panel--kpi .mini-parameter-list::-webkit-scrollbar {
    width: 3px;
}

.detail-panel--kpi .mini-parameter-list::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: #ddd6fe;
}

.detail-panel--kpi .mini-parameter-list > span {
    min-width: 0;
}

.detail-panel--kpi .mini-parameter-list > span > span.not-inPeriode {
    opacity: 0.45;
    pointer-events: none;
}

.detail-panel--kpi .mini-parameter-list > span > span > div:first-child {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.42rem;
    min-width: 0;
}

.detail-panel--kpi .kpi-param-name {
    display: flex;
    align-items: center;
    gap: 0.28rem;
    min-width: 0;
    flex: 1;
}

.detail-panel--kpi .kpi-param-name > span {
    min-width: 0;
    color: var(--hcis-slate-600);
    font-size: 0.63rem;
    font-weight: 850;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.detail-panel--kpi .mini-parameter-list > span > span > div:first-child > strong {
    flex: 0 0 auto;
    color: var(--hcis-slate-900);
    font-size: 0.63rem;
    font-weight: 950;
}

.detail-panel--kpi .mini-parameter-meta {
    display: block;
    margin-top: 0.08rem;
    color: var(--hcis-slate-500);
    font-size: 0.6rem;
    font-weight: 800;
    line-height: 1.25;
}

/* ── Chart Block (Achievement Bulanan) ── */
.detail-panel--kpi .chart-block {
    margin-top: auto;
    min-width: 0;
    flex-shrink: 0;
}

.detail-panel--kpi .chart-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.22rem;
}

.detail-panel--kpi .chart-head span {
    font-size: 0.62rem;
    font-weight: 900;
    color: var(--hcis-slate-900);
}

.detail-panel--kpi .chart-head strong {
    font-size: 0.58rem;
    font-weight: 800;
    color: var(--hcis-slate-500);
}

.detail-panel--kpi .chart-safe {
    border-radius: 0.65rem;
    overflow: hidden;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
}

.drawer-error-box.is-compact {
    padding: 0.5rem 0.58rem;
    margin-bottom: 0.42rem;
}

.drawer-error-box.is-compact strong {
    font-size: 0.62rem;
}

.leader-insight-inline-error {
    grid-column: 1 / -1;
    margin-top: 0.1rem;
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
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.72rem;
    background: linear-gradient(180deg, var(--hcis-slate-50), var(--hcis-white));
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
    background: var(--hcis-white);
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
    color: var(--hcis-slate-500);
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
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    font-size: 0.7rem;
    line-height: 1;
}

.hcis-issue-stack .issue-item.is-danger i {
    background: var(--hcis-red-100);
    color: var(--hcis-red-600);
}

.hcis-issue-stack .issue-item.is-warning i {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-600);
}

.hcis-issue-stack .issue-item.is-info i {
    background: var(--hcis-sky-100);
    color: var(--hcis-sky-600);
}

.module-alert {
    border: 1px solid var(--hcis-slate-200);
}

.module-alert.is-warning {
    background: var(--hcis-amber-50);
    border-color: var(--hcis-amber-200);
    color: var(--hcis-amber-800);
}

.module-alert.is-success {
    background: var(--hcis-green-50);
    border-color: var(--hcis-green-200);
    color: #166534;
}

.training-status {
    border: 1px solid transparent;
}

.training-status.is-complete {
    background: var(--hcis-green-100);
    color: #166534;
    border-color: var(--hcis-green-200);
}

.training-status.is-progress {
    background: #dbeafe;
    color: #1d4ed8;
    border-color: #bfdbfe;
}

.training-status.is-pending {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-800);
    border-color: var(--hcis-amber-200);
}

.attendance-issue-drawer-backdrop {
    position: fixed;
    inset: 0;
    top: 56px; /* Adjust this to match your top bar height */
    background: rgba(15, 23, 42, 0.34);
    backdrop-filter: blur(2px);
    z-index: 95;
}

.attendance-issue-drawer {
    position: fixed;
    top: 56px; /* Adjust this to match your top bar height */
    right: 0;
    width: min(30rem, 92vw);
    height: calc(100vh - 56px);
    display: grid;
    grid-template-rows: auto 1fr auto;
    background: var(--hcis-white);
    border-left: 1px solid var(--hcis-slate-200);
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
    border-bottom: 1px solid var(--hcis-slate-200);
    background: linear-gradient(180deg, #fff, var(--hcis-slate-50));
}

.attendance-issue-drawer__head h3 {
    margin: 0.2rem 0 0;
    font-size: 1.04rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
}

.attendance-issue-drawer__head small {
    display: block;
    margin-top: 0.22rem;
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
}

.drawer-close-btn {
    width: 2rem;
    height: 2rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.56rem;
    display: grid;
    place-items: center;
    background: var(--hcis-white);
    color: var(--hcis-slate-700);
}

.drawer-close-btn:hover {
    border-color: var(--hcis-slate-300);
    background: var(--hcis-slate-50);
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
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.8rem;
    background: var(--hcis-white);
    padding: 0.72rem;
    display: grid;
    gap: 0.52rem;
}

.attendance-issue-card.is-clickable-member {
    cursor: pointer;
    transition:
        border-color 0.18s ease,
        box-shadow 0.18s ease,
        transform 0.18s ease;
}

.attendance-issue-card.is-clickable-member:hover,
.attendance-issue-card.is-clickable-member:focus-visible {
    border-color: #93c5fd;
    box-shadow: 0 8px 22px rgba(59, 130, 246, 0.15);
    transform: translateY(-1px);
    outline: none;
}

/* HCIS breakdown accordion (Drawer A & B) */
.hcis-accordion-body {
    margin-top: 0.6rem;
    padding-top: 0.6rem;
    border-top: 1px dashed var(--hcis-slate-200);
}

.hcis-accordion-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.hcis-accordion-list > li {
    background: var(--hcis-slate-50);
    border: 1px solid #eef2f7;
    border-radius: 8px;
    padding: 0.5rem 0.65rem;
}

.hcis-accordion-list__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
}

.hcis-accordion-list__row strong {
    font-size: 0.82rem;
    color: var(--hcis-slate-900);
}

.hcis-accordion-list__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem 0.9rem;
    margin-top: 0.35rem;
    font-size: 0.74rem;
    color: var(--hcis-slate-500);
}

.hcis-accordion-empty {
    margin: 0;
    font-size: 0.78rem;
    color: var(--hcis-slate-400);
    text-align: center;
    padding: 0.4rem 0;
}

.hcis-today-stat .attendance-issue-pill i {
    margin-left: 0.25rem;
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
    color: var(--hcis-slate-900);
}

.attendance-issue-card__head span {
    display: block;
    margin-top: 0.16rem;
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
}

.attendance-issue-card__head small {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
}

.attendance-issue-card__meta {
    display: flex;
    gap: 0.6rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
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
    background: var(--hcis-red-100);
    border-color: var(--hcis-red-200);
    color: var(--hcis-red-700);
}

.attendance-issue-pill.is-warning,
.attendance-issue-pill.is-orange {
    background: var(--hcis-amber-100);
    border-color: var(--hcis-amber-200);
    color: var(--hcis-amber-800);
}

.attendance-issue-pill.is-info {
    background: #dbeafe;
    border-color: #bfdbfe;
    color: #1d4ed8;
}

.attendance-issue-pill.is-success {
    background: var(--hcis-green-100);
    border-color: var(--hcis-green-200);
    color: #166534;
}

.attendance-issue-pill.is-purple {
    background: var(--hcis-purple-100);
    border-color: #e9d5ff;
    color: var(--hcis-violet-700);
}

.attendance-issue-pill.is-secondary {
    background: var(--hcis-slate-200);
    border-color: var(--hcis-slate-300);
    color: var(--hcis-slate-700);
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
    color: var(--hcis-slate-500);
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
    border: 1px solid var(--hcis-slate-300);
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-700);
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
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-700);
    min-height: 1.95rem;
    padding: 0.32rem 0.52rem;
    font-size: 0.7rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.16s ease;
}

.filter-pill:hover {
    border-color: var(--hcis-slate-300);
    background: var(--hcis-white);
}

.filter-pill.is-active {
    border-color: var(--hcis-indigo-600);
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
}

.kpi-member-period-select {
    border: 1px solid #dbe3ef;
    border-radius: 0.56rem;
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-700);
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
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.64rem;
    background: var(--hcis-slate-50);
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
    color: var(--hcis-slate-500);
}

.kpi-member-summary-grid strong {
    margin-top: 0.12rem;
    font-size: 0.94rem;
    font-weight: 900;
    color: var(--hcis-slate-900);
}

.kpi-member-trend-card {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.72rem;
    background: linear-gradient(180deg, var(--hcis-white), var(--hcis-slate-50));
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
    color: var(--hcis-slate-900);
}

.kpi-member-trend-card__head span {
    font-size: 0.66rem;
    color: var(--hcis-slate-500);
    font-weight: 700;
}

.kpi-member-trend-chart {
    border-radius: 0.62rem;
    background: var(--hcis-white);
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
    color: var(--hcis-slate-500);
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
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.62rem;
    padding: 0.52rem 0.56rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.62rem;
    background: var(--hcis-slate-50);
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
    color: var(--hcis-slate-900);
}

.lms-member-training-item span {
    font-size: 0.66rem;
    color: var(--hcis-slate-500);
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
    color: var(--hcis-slate-700);
}

.lms-member-learning-item strong {
    color: var(--hcis-slate-900);
}

.kpi-member-detail-block {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.72rem;
    background: var(--hcis-white);
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
    color: var(--hcis-slate-900);
}

.kpi-member-detail-block > header span {
    font-size: 0.64rem;
    color: var(--hcis-slate-500);
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
    border: 1px solid var(--hcis-slate-200);
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
    color: var(--hcis-slate-900);
    line-height: 1.3;
}

.kpi-member-detail-item span {
    margin-top: 0.1rem;
    font-size: 0.64rem;
    color: var(--hcis-slate-500);
}

.kpi-parameter-status-stack {
    display: grid;
    align-content: start;
    justify-items: end;
    gap: 0.3rem;
}

/* KPI Status Panel — mirrors Personal Dashboard */
.kpi-status-panel {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.48rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.62rem;
    background: var(--hcis-slate-50);
    padding: 0.54rem 0.7rem;
}

.kpi-member-status-panel {
    margin-top: 0;
}

.kpi-status-panel__item {
    display: grid;
    gap: 0.18rem;
}

.kpi-status-panel__item span {
    font-size: 0.62rem;
    color: var(--hcis-slate-400);
    font-weight: 500;
}

.kpi-status-panel__item strong {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-900);
}

.kpi-status-panel__item strong.is-success {
    color: var(--hcis-green-600);
}
.kpi-status-panel__item strong.is-danger {
    color: var(--hcis-red-600);
}
.kpi-status-panel__item strong.is-info {
    color: var(--hcis-sky-600);
}
.kpi-status-panel__item strong.is-warning {
    color: var(--hcis-amber-600);
}
.kpi-status-panel__item strong.is-muted,
.kpi-status-panel__item strong.is-secondary {
    color: var(--hcis-slate-400);
}

/* Personal-style parameter list in member detail */
.kpi-member-param-list {
    display: grid;
    gap: 0.5rem;
    max-height: 15rem;
    overflow: auto;
    padding-right: 0.1rem;
}

.kpi-member-param-list > span {
    display: grid;
    gap: 0.25rem;
    padding: 0.46rem 0.56rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.55rem;
    background: #fff;
}

.kpi-member-param-list > span.not-inPeriode {
    opacity: 0.5;
}

.kpi-member-param-list > span > div:first-child {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
}

.kpi-member-param-list > span > div:first-child strong {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
    white-space: nowrap;
}

.kpi-member-param-list > span > div:first-child strong small {
    font-size: 0.62rem;
    font-weight: 500;
    color: var(--hcis-slate-500);
}

.kpi-param-name {
    display: flex;
    align-items: center;
    gap: 0.38rem;
    min-width: 0;
    flex: 1;
}

.kpi-param-name span {
    font-size: 0.68rem;
    color: var(--hcis-slate-700);
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.kpi-param-dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    flex-shrink: 0;
    background: var(--hcis-slate-300);
}

.kpi-param-dot.is-excellent {
    background: var(--hcis-green-600);
}
.kpi-param-dot.is-good {
    background: var(--hcis-blue-600);
}
.kpi-param-dot.is-low {
    background: var(--hcis-amber-600);
}
.kpi-param-dot.is-idle {
    background: var(--hcis-slate-300);
}

.mini-parameter-meta {
    display: block;
    font-size: 0.61rem;
    color: var(--hcis-slate-400);
    line-height: 1.4;
}

.mini-progress {
    height: 0.3rem;
    background: var(--hcis-slate-300);
    border-radius: 9999px;
    overflow: hidden;
}

.mini-progress__fill {
    height: 100%;
    border-radius: 9999px;
    transition: width 0.4s ease;
    background: var(--hcis-slate-400);
}

.mini-progress__fill.is-purple {
    background: linear-gradient(90deg, var(--hcis-violet-600), var(--hcis-violet-400));
}

.attendance-issue-drawer__foot {
    padding: 0.75rem 1rem 0.95rem;
    border-top: 1px solid var(--hcis-slate-200);
    background: var(--hcis-white);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.attendance-issue-drawer__foot span {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
}

.drawer-error-box {
    border: 1px solid var(--hcis-red-200);
    background: var(--hcis-red-50);
    color: var(--hcis-red-700);
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
        grid-template-columns: repeat(3, minmax(0, 1fr));
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
        position: relative;
    }

    .member-profile {
        padding-right: 6.5rem;
    }

    .member-row-action {
        position: absolute;
        top: 0.52rem;
        right: 0.52rem;
        justify-content: flex-end;
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
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .detail-grid {
        grid-template-columns: 1fr;
    }

    .detail-panel {
        min-height: unset;
        max-height: unset;
        overflow-y: visible;
    }

    .detail-panel--lms .training-list {
        max-height: 16rem;
    }

    .detail-panel--approval .approval-list {
        max-height: 16rem;
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
        grid-template-columns: repeat(3, minmax(0, 1fr));
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
        grid-template-columns: repeat(3, minmax(0, 1fr));
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
    .lms-overview,
    .lms-overview__stats,
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

/* =========================================================
   MINIMUM DIMENSIONS
   ========================================================= */
.insight-card {
    min-height: 22rem;
}
.side-card {
    min-height: 10rem;
}
.quick-stat-card {
    min-height: 3.5rem;
}
.member-card {
    min-height: 3.6rem;
}
.line-chart-card {
    min-height: 10rem;
}
.leader-score-card {
    min-height: 7rem;
}
.leader-mini-stat-grid > div {
    min-height: 4.5rem;
}
.team-calendar-day {
    min-height: 4.5rem;
}
.heatmap-board {
    min-height: 6rem;
}
.hcis-insight-card,
.kpi-summary-card,
.lms-summary-card,
.team-list-card,
.team-calendar-panel {
    min-height: 18rem;
}

/* =========================================================
   LOADING SKELETON
   ========================================================= */
.leader-loading-state {
    padding: 1.5rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.8rem;
}
.leader-loading-state.is-compact {
    padding: 0.75rem;
    gap: 0.5rem;
}
.leader-loading-state strong {
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--hcis-slate-500);
}
.leader-skeleton-list {
    width: 100%;
    max-width: 280px;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    align-items: center;
}
@keyframes leaderSkeletonPulse {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

/* =========================================================
   UI SKELETON SCREENS
   ========================================================= */
.skeleton-avatar {
    border-radius: 50%;
}

.hero-skeleton-avatar {
    border-radius: 50%;
}

.hero-skeleton-line,
.hero-skeleton-avatar {
    background-color: rgba(255, 255, 255, 0.1);
    background-image: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.2) 50%,
        rgba(255, 255, 255, 0) 100%
    );
    background-size: 200% 100%;
    animation: leaderSkeletonPulse 1.5s ease-in-out infinite;
    border-radius: 0.3rem;
}

.leader-skeleton-line,
.skeleton-avatar {
    background-color: var(--hcis-slate-100);
    background-image: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.8) 50%,
        rgba(255, 255, 255, 0) 100%
    );
    background-size: 200% 100%;
    animation: leaderSkeletonPulse 1.5s ease-in-out infinite;
}

.leader-skeleton-line {
    width: 100%;
    height: 0.65rem;
    border-radius: 0.3rem;
}
.leader-skeleton-line.is-title {
    height: 0.95rem;
    width: 70%;
}
.leader-skeleton-line.is-short {
    width: 45%;
}

.is-skeleton {
    pointer-events: none !important;
}

.action-card.is-skeleton .action-card__icon {
    background: var(--hcis-slate-200);
    border: none;
    border-radius: 0.62rem;
}

.action-card.is-skeleton .action-card__body {
    flex: 1;
    min-width: 0;
}

.approval-side-item.is-skeleton {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.training-pending-item.is-skeleton {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mb-1 {
    margin-bottom: 0.25rem !important;
}
.mb-2 {
    margin-bottom: 0.5rem !important;
}
.mt-1 {
    margin-top: 0.25rem !important;
}
.mt-2 {
    margin-top: 0.5rem !important;
}

/* =========================================================
   MOBILE ACCORDION & LAYOUT
   ========================================================= */
.mobile-chevron {
    margin-left: 0.5rem;
    color: var(--hcis-slate-500);
    font-size: 0.85rem;
    transition: transform 0.2s ease;
}
.mobile-chevron.is-open {
    transform: rotate(180deg);
}
.header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

@keyframes actionIconPulse {
    0% {
        box-shadow: 0 0 0 0 rgba(225, 29, 72, 0.4);
    }
    70% {
        box-shadow: 0 0 0 8px rgba(225, 29, 72, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(225, 29, 72, 0);
    }
}

.animate-action-pulse {
    animation: actionIconPulse 2s infinite;
}

@keyframes actionIconRing {
    0% {
        transform: rotate(0);
    }
    10% {
        transform: rotate(25deg);
    }
    20% {
        transform: rotate(-20deg);
    }
    30% {
        transform: rotate(15deg);
    }
    40% {
        transform: rotate(-10deg);
    }
    50% {
        transform: rotate(5deg);
    }
    60% {
        transform: rotate(0);
    }
    100% {
        transform: rotate(0);
    }
}

.animate-icon-ring {
    animation: actionIconRing 1.5s infinite;
    display: inline-block;
    transform-origin: top center;
}

@media (max-width: 768px) {
    .leader-dashboard-container {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .leader-main-grid,
    .team-insight-grid,
    .leader-main,
    .leader-side {
        display: contents;
    }

    .leader-hero {
        order: 0;
    }

    .hero-topline {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.14rem;
        width: 100%;
    }

    .hero-eyebrow {
        background: transparent !important;
        border: none !important;
        padding: 0 !important;
        font-size: 0.68rem !important;
        font-weight: 950 !important;
        letter-spacing: 0.12em !important;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.72) !important;
        backdrop-filter: none !important;
    }

    .hero-eyebrow i {
        display: none !important;
    }

    .leader-hero__shortcut {
        position: absolute;
        top: 0.62rem;
        right: 0.62rem;
        z-index: 3;
        width: 2.1rem;
        height: 2.1rem;
        min-height: 2.1rem;
        padding: 0;
        border-radius: 0.72rem;
        font-size: 0.9rem;
        gap: 0;
        justify-content: center;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.13));
        border-color: rgba(255, 255, 255, 0.28);
        box-shadow:
            0 0.45rem 1rem rgba(8, 16, 42, 0.08),
            inset 0 1px 0 rgba(255, 255, 255, 0.12);
    }

    .leader-hero__shortcut-label {
        display: none;
    }
    .quick-stat-grid {
        order: 1;
    }
    .action-center-card {
        order: 2;
    }
    .hcis-insight-card {
        order: 3;
    }
    .kpi-summary-card {
        order: 4;
    }
    .lms-summary-card {
        order: 5;
    }
    .team-list-toolbar {
        order: 6;
    }
    .team-list-content {
        order: 7;
    }
    .team-calendar-panel {
        order: 8;
    }
    .approval-side-card {
        order: 9;
    }
    .heatmap-side-card {
        order: 10;
    }
    .learning-side-card {
        order: 11;
    }

    /* --- Compact Grid Optimizations --- */
    .hero-brief-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.5rem !important;
    }

    .leader-mini-stat-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.5rem !important;
    }

    /* Horizontal Swipeable Quick Stats */
    .quick-stat-grid {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        gap: 0.5rem;
        padding-bottom: 0.5rem; /* for scrollbar spacing */
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none; /* Firefox */
    }
    .quick-stat-grid::-webkit-scrollbar {
        display: none; /* Chrome/Safari */
    }
    .quick-stat-grid > .quick-stat-card {
        flex: 0 0 auto;
        width: 80vw;
        max-width: 320px;
        scroll-snap-align: center;
    }

    /* Shift Board Swipeable */
    .shift-board-grid {
        display: flex !important;
        flex-wrap: nowrap !important;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        gap: 0.5rem;
        padding-bottom: 0.5rem;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .shift-board-grid::-webkit-scrollbar {
        display: none;
    }
    .shift-board-grid > .shift-pillar {
        flex: 0 0 auto;
        width: 85vw;
        scroll-snap-align: center;
    }

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

    .mobile-accordion-card .module-icon.is-indigo {
        background: var(--hcis-indigo-100);
        color: var(--hcis-indigo-600);
    }
    .mobile-accordion-card .module-icon.is-purple {
        background: var(--hcis-purple-100);
        color: var(--hcis-purple-600);
    }
    .mobile-accordion-card .module-icon.is-teal {
        background: #ccfbf1;
        color: #0d9488;
    }
    .mobile-accordion-card .module-icon.is-orange {
        background: var(--hcis-orange-100);
        color: var(--hcis-orange-600);
    }
    .mobile-accordion-card .module-icon.is-blue {
        background: #dbeafe;
        color: var(--hcis-blue-600);
    }
    .mobile-accordion-card .module-icon.is-rose {
        background: var(--hcis-rose-100);
        color: var(--hcis-rose-600);
    }
    .mobile-accordion-card .module-icon.is-emerald {
        background: var(--hcis-emerald-100);
        color: var(--hcis-emerald-600);
    }
    .mobile-accordion-card .module-icon.is-danger {
        background: var(--hcis-rose-100);
        color: var(--hcis-rose-600);
    }

    /* animations moved outside media query */

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

    .mobile-accordion-card .period-badge.is-purple {
        background: var(--hcis-purple-100);
        color: var(--hcis-purple-700);
    }

    .mobile-accordion__meta .mini-badge,
    .mobile-accordion__meta .period-badge,
    .mobile-accordion__meta .side-count {
        min-width: 1.8rem;
        height: 1.5rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 0 0.5rem;
        font-size: 0.65rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .mobile-accordion__meta .side-count:not([class*='is-']) {
        background: var(--hcis-slate-100);
        color: var(--hcis-slate-600);
    }

    .mobile-accordion__meta > i {
        font-size: 0.8rem;
    }

    .mobile-accordion__body {
        margin-top: 0.68rem;
        padding-top: 0.68rem;
        border-top: 1px solid var(--hcis-slate-100);
    }

    .accordion-enter-active,
    .accordion-leave-active {
        transition:
            height 0.3s cubic-bezier(0.4, 0, 0.2, 1),
            opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1),
            margin-top 0.3s cubic-bezier(0.4, 0, 0.2, 1),
            padding-top 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
    }
    .accordion-enter-from,
    .accordion-leave-to {
        opacity: 0;
        margin-top: 0 !important;
        padding-top: 0 !important;
        border-top-color: transparent !important;
    }

    /* Fallbacks for unmodified elements */
    .insight-card__head.is-clickable:not(.is-open) ~ *:not(.mobile-accordion__body),
    .side-card__head.is-clickable:not(.is-open) ~ *:not(.mobile-accordion__body),
    .toolbar-main.is-clickable:not(.is-open) ~ *:not(.mobile-accordion__body),
    .section-head.is-clickable:not(.is-open) ~ *:not(.mobile-accordion__body) {
        display: none !important;
    }

    /* Team Calendar Mobile Layout */
    .team-calendar-grid {
        grid-template-columns: repeat(7, minmax(110px, 1fr)) !important;
        overflow-x: auto !important;
        padding-bottom: 0.5rem !important;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .team-calendar-grid::-webkit-scrollbar {
        display: none;
    }
    .team-calendar-summary-row {
        flex-wrap: nowrap !important;
        overflow-x: auto !important;
        padding-bottom: 0.5rem !important;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .team-calendar-summary-row::-webkit-scrollbar {
        display: none;
    }
    .team-calendar-summary-pill {
        flex: 0 0 auto !important;
        min-width: 80px !important;
    }
}

/* ────────────────────────────────────────── */
/* Accessibility & Responsive Enhancement CSS */
/* ────────────────────────────────────────── */

/* Member name title styling */
.member-name-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--hcis-slate-900);
}

/* Reduce member-detail padding */
.member-detail {
    padding: 0.5rem !important;
}

/* Tighten spacing on detail panels */
.detail-top,
.detail-panel,
.priority-summary-grid {
    gap: 0.5rem;
}

/* Weekly calendar popover: responsive behavior */
@media (max-width: 480px) {
    /* Transform popover to fixed bottom drawer on small screens */
    .weekly-cal__popover {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        margin: 0;
        border-radius: 0.75rem 0.75rem 0 0;
        padding: 1rem;
        max-height: 72vh;
        overflow-y: auto;
        z-index: 1100;
        box-shadow: 0 -8px 32px rgba(2, 6, 23, 0.12);
        border: 1px solid rgba(15, 23, 42, 0.06);
        background: var(--hcis-white);
    }

    /* Add subtle backdrop via pseudo-element inside container (keeps markup unchanged) */
    .weekly-cal__popover::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(2, 6, 23, 0.28);
        z-index: -1;
    }

    /* Tighten member-detail spacing on mobile */
    .member-detail {
        padding: 0.35rem !important;
    }

    /* Reduce grid gaps for compact layout */
    .priority-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.45rem;
    }

    .weekly-cal__head .weekly-cal__range {
        font-size: 0.85rem;
    }
    .kpi-member-detail-item.is-disabled {
        opacity: 0.5;
    }
    .parameter-row.is-disabled {
        opacity: 0.5;
    }
}

/* ===== Reveal-on-click card (selaras homepage.vue module-reveal) ===== */
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

.module-reveal--emerald {
    --reveal-border: var(--hcis-emerald-200);
    --reveal-tint: rgba(5, 150, 105, 0.1);
    --reveal-bg: #f3fdf8;
    --reveal-accent: var(--hcis-emerald-600);
    --reveal-accent-2: var(--hcis-emerald-500);
}

/* Varian ringkas untuk side-card (mis. Antrian Approval) yang lebih pendek. */
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
</style>

<style scoped>
/*
 * Non-scoped rules for programmatic components (render functions / h()).
 * These CANNOT use Vue `scoped` because h()-rendered nodes don't receive the
 * data-v-* attribute. To prevent these generic class names (.empty-state,
 * .panel-title, .issue-item, dst) bocor & menimpa style page lain saat navigasi
 * Inertia (SPA tidak reload), SEMUA selector di-namespace di bawah root page
 * `.leader-dashboard-page`. Dengan begitu rule ini hanya berlaku di dalam page
 * ini dan tidak bertabrakan dengan page lain yang juga punya `.empty-state` dkk.
 */
.leader-dashboard-page .detail-panel__head,
.leader-dashboard-page .panel-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    min-width: 0;
}

.leader-dashboard-page .panel-title {
    justify-content: flex-start;
}

.leader-dashboard-page .detail-panel__head {
    margin-bottom: 0.45rem;
    padding: 0.38rem 0.42rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.68rem;
    background: var(--hcis-slate-50);
}

.leader-dashboard-page .panel-title i {
    width: 1.6rem;
    height: 1.6rem;
    flex: 0 0 auto;
    display: grid;
    place-items: center;
    border-radius: 0.54rem;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
}

.leader-dashboard-page .panel-title strong {
    font-size: 0.72rem;
    font-weight: 950;
    line-height: 1.2;
    color: var(--hcis-slate-900);
}

.leader-dashboard-page .panel-count {
    flex: 0 0 auto;
    padding: 0.22rem 0.44rem;
    border-radius: 999px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    font-size: 0.5rem;
    font-weight: 900;
    line-height: 1;
}

/* Issue list (rendered via h()) */
.leader-dashboard-page .issue-list {
    display: grid;
    gap: 0.45rem;
    margin-top: 0.55rem;
}

.leader-dashboard-page .issue-item {
    display: flex;
    gap: 0.6rem;
    align-items: flex-start;
    padding: 0.54rem 0.58rem;
    border-radius: 0.68rem;
    background: var(--hcis-white);
    border: 1px solid #e5eaf2;
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.03);
}

.leader-dashboard-page .issue-item i {
    width: 1.28rem;
    height: 1.28rem;
    display: grid;
    place-items: center;
    margin-top: 0;
    border-radius: 0.42rem;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    font-size: 0.85rem;
    line-height: 1;
}

.leader-dashboard-page .issue-item strong {
    display: block;
    font-size: 0.72rem;
    font-weight: 800;
    line-height: 1.2;
}

.leader-dashboard-page .issue-item span {
    display: block;
    color: var(--hcis-slate-500);
    font-size: 0.62rem;
    font-weight: 700;
}

.leader-dashboard-page .issue-item.is-danger i {
    background: var(--hcis-red-100);
    color: var(--hcis-red-600);
}

.leader-dashboard-page .issue-item.is-warning i {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-600);
}

.leader-dashboard-page .issue-item.is-info i {
    background: var(--hcis-sky-100);
    color: var(--hcis-sky-600);
}

.leader-dashboard-page .issue-item.is-success i {
    background: var(--hcis-green-100);
    color: #166534;
}

/* ── Empty State ── */
.leader-dashboard-page .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 1.5rem;
    color: var(--hcis-slate-500);
    width: 100%;
}

.leader-dashboard-page .empty-state i {
    font-size: 1.8rem;
    color: var(--hcis-slate-300);
    margin-bottom: 0.5rem;
}

.leader-dashboard-page .empty-state strong {
    display: block;
    font-size: 0.76rem;
    font-weight: 800;
    color: var(--hcis-slate-700);
    margin-bottom: 0.2rem;
}

.leader-dashboard-page .empty-state span {
    display: block;
    font-size: 0.68rem;
    color: var(--hcis-slate-500);
    font-weight: 500;
    line-height: 1.4;
}

/* ── Compact Empty State ── */
.leader-dashboard-page .empty-state.is-compact {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: flex-start;
    gap: 0.5rem;
    padding: 0.55rem 0.72rem;
    border-radius: 0.62rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    text-align: left;
    width: 100%;
}

.leader-dashboard-page .empty-state.is-compact i {
    font-size: 0.95rem;
    color: var(--hcis-slate-500);
    margin-bottom: 0;
    flex-shrink: 0;
}

.leader-dashboard-page .empty-state.is-compact strong {
    font-size: 0.72rem;
    font-weight: 850;
    color: var(--hcis-slate-700);
    margin-bottom: 0;
    white-space: nowrap;
}

.leader-dashboard-page .empty-state.is-compact span {
    font-size: 0.68rem;
    color: var(--hcis-slate-500);
    font-weight: 500;
    margin-bottom: 0;
}

/* ── HCIS Absensi Aman (Success Green State) ── */
.leader-dashboard-page .hcis-issue-stack .empty-state.is-compact {
    background: var(--hcis-emerald-50); /* Light green background */
    border-color: var(--hcis-emerald-200); /* Muted green border */
}

.leader-dashboard-page .hcis-issue-stack .empty-state.is-compact i {
    color: var(--hcis-emerald-500); /* Green checkmark icon */
}

.leader-dashboard-page .hcis-issue-stack .empty-state.is-compact strong {
    color: var(--hcis-emerald-700); /* Muted dark green title */
}

.leader-dashboard-page .hcis-issue-stack .empty-state.is-compact span {
    color: var(--hcis-emerald-800); /* Description text color */
    font-weight: 600;
}
</style>
