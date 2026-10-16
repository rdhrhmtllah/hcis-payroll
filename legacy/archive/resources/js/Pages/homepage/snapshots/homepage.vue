<template>
    <div class="cuti-app-container">
        <!-- <HomepageGreeting :greeting="greeting" /> -->

        <!-- <NotificationBanner
                v-if="notifications && notifications.length"
                :notifications="notifications"
                @action="handleNotifAction"
            /> -->

        <!-- <QuickActions :isLeader="isLeader" :pendingApprovals="pendingApprovals" :customActions="quickActions" /> -->

        <div class="main-content-wrapper pb-4">
            <div v-if="isFallbackHcis" class="fallback-banner mb-4">
                <div class="fallback-banner__icon">
                    <i class="bi bi-info-circle-fill"></i>
                </div>
                <div>
                    <div class="fw-bold">Sebagian data HCIS belum lengkap</div>
                    <div class="text-muted small">
                        {{
                            resolvedHcis.meta?.message || 'Dashboard tetap ditampilkan dengan data yang sudah tersedia.'
                        }}
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <HcisTodayStatusCard :today="resolvedHcis.today_status" @navigate="navigateTo" />
            </div>

            <div class="mb-4">
                <HcisSummaryStats
                    :leave-summary="resolvedHcis.leave_summary"
                    :monthly-stats="resolvedHcis.monthly_stats"
                />
            </div>

            <div class="row g-4 mb-4">
                <div class="col-12 col-xl-5">
                    <HcisAlertStack :alerts="resolvedHcis.alerts || []" @navigate="navigateTo" />
                </div>
                <div class="col-12 col-xl-7">
                    <HcisUpcomingSchedule :schedule="resolvedHcis.upcoming_schedule || []" @navigate="navigateTo" />
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-12 col-xl-7">
                    <HcisRecentActivity :items="resolvedHcis.recent_activity || []" @navigate="navigateTo" />
                </div>
                <div class="col-12 col-xl-5">
                    <TodayTimeline :events="timelineEvents" />
                </div>
            </div>

            <div v-if="isLeader && resolvedHcis.leader_summary" class="mb-4">
                <HcisLeaderPanel :summary="resolvedHcis.leader_summary" @navigate="navigateTo" />
            </div>

            <div class="mb-4">
                <HcisInsightsPanel :insights="resolvedHcis.personal_insights" @navigate="navigateTo" />
            </div>

            <div class="mb-4">
                <HcisChartsPanel :charts="resolvedHcis.charts || {}" />
            </div>

            <div class="row g-4 mb-4">
                <div class="col-12 col-xl-4">
                    <CardState v-if="!kpi" type="empty" message="Data KPI belum tersedia." :retryable="false" />
                    <KpiCard v-else :data="kpi" @navigate="navigateTo" />
                </div>
                <div class="col-12 col-xl-4">
                    <CardState v-if="!lms" type="empty" message="Data LMS belum tersedia." :retryable="false" />
                    <LmsCard v-else :data="lms" @navigate="navigateTo" />
                </div>
                <div class="col-12 col-xl-4">
                    <CardState v-if="!visitor" type="empty" message="Data visitor belum tersedia." :retryable="false" />
                    <VisitorCard v-else :data="visitor" @navigate="navigateTo" />
                </div>
            </div>

            <CalendarSection />
        </div>
    </div>
</template>

<script>
import { router } from '@inertiajs/vue3';
import HomepageGreeting from './components/HomepageGreeting.vue';
import QuickActions from './components/QuickActions.vue';
import NotificationBanner from './components/NotificationBanner.vue';
import CalendarSection from './components/CalendarSection.vue';
import KpiCard from './components/KpiCard.vue';
import LmsCard from './components/LmsCard.vue';
import VisitorCard from './components/VisitorCard.vue';
import TodayTimeline from './components/TodayTimeline.vue';
import CardState from './components/CardState.vue';
import HcisTodayStatusCard from './components/HcisTodayStatusCard.vue';
import HcisAlertStack from './components/HcisAlertStack.vue';
import HcisUpcomingSchedule from './components/HcisUpcomingSchedule.vue';
import HcisSummaryStats from './components/HcisSummaryStats.vue';
import HcisRecentActivity from './components/HcisRecentActivity.vue';
import HcisInsightsPanel from './components/HcisInsightsPanel.vue';
import HcisLeaderPanel from './components/HcisLeaderPanel.vue';
import HcisChartsPanel from './components/HcisChartsPanel.vue';

export default {
    name: 'HomepageDashboard',
    components: {
        HomepageGreeting,
        QuickActions,
        NotificationBanner,
        CalendarSection,
        KpiCard,
        LmsCard,
        VisitorCard,
        TodayTimeline,
        CardState,
        HcisTodayStatusCard,
        HcisAlertStack,
        HcisUpcomingSchedule,
        HcisSummaryStats,
        HcisRecentActivity,
        HcisInsightsPanel,
        HcisLeaderPanel,
        HcisChartsPanel,
    },
    props: {
        hcis: Object,
        kpi: Object,
        lms: Object,
        visitor: Object,
        user: Object,
        role: { type: String, default: 'employee' },
        greeting: { type: Object, default: () => ({}) },
        notifications: { type: Array, default: () => [] },
        pendingApprovals: { type: Number, default: 0 },
    },
    computed: {
        isLeader() {
            return ['team_leader', 'group_head', 'super_admin'].includes(this.role);
        },
        resolvedHcis() {
            return (
                this.hcis || {
                    today_status: {
                        status_code: 'DATA_TERSEDIA_SEBAGIAN',
                        status_label: 'Data Parsial',
                        status_tone: 'secondary',
                        headline: 'Homepage HCIS sedang menyiapkan data absensi terbaru.',
                        shift_name: 'DATA MENUNGGU SINKRON',
                        status_hari_label: 'Data sedang dilengkapi',
                        cta: [],
                    },
                    alerts: [],
                    upcoming_schedule: [],
                    leave_summary: { remaining: 0, debt: 0 },
                    monthly_stats: {},
                    recent_activity: [],
                    personal_insights: { actionable: [], analytical: [] },
                    charts: {},
                    leader_summary: this.isLeader
                        ? { pending_approvals: this.pendingApprovals, counts: {}, members: [] }
                        : null,
                    today_logs: [],
                    meta: {
                        is_fallback: true,
                        message: 'Dashboard tetap ditampilkan sambil menunggu sebagian data HCIS dilengkapi.',
                    },
                }
            );
        },
        isFallbackHcis() {
            return Boolean(this.resolvedHcis?.meta?.is_fallback);
        },
        quickActions() {
            return [
                {
                    id: 'absensi',
                    label: 'Detail Absensi',
                    icon: 'bi bi-calendar-check-fill',
                    route: '/absensiSales',
                    color: '#0f766e',
                },
                {
                    id: 'izin',
                    label: 'Ajukan Izin',
                    icon: 'bi bi-envelope-paper-fill',
                    route: '/izin',
                    color: '#2563eb',
                },
                { id: 'cuti', label: 'Ajukan Cuti', icon: 'bi bi-briefcase-fill', route: '/cuti', color: '#7c3aed' },
                {
                    id: 'lembur',
                    label: 'Ajukan Lembur',
                    icon: 'bi bi-clock-history',
                    route: '/overtime',
                    color: '#ea580c',
                },
                {
                    id: 'shift',
                    label: 'Self Shift',
                    icon: 'bi bi-arrow-left-right',
                    route: '/self-shift',
                    color: '#0891b2',
                },
            ];
        },
        timelineEvents() {
            return (this.resolvedHcis?.today_logs || []).map((log) => ({
                time: log.time,
                type: log.type || 'activity',
                icon:
                    log.type === 'checkin'
                        ? 'bi bi-box-arrow-in-right'
                        : log.type === 'checkout'
                          ? 'bi bi-box-arrow-right'
                          : 'bi bi-clock',
                color: log.type === 'checkin' ? '#10b981' : log.type === 'checkout' ? '#ef4444' : '#2563eb',
                title: `${log.activity} - ${log.machine_alias || 'Mesin Absen'}`,
                subtitle: log.status,
            }));
        },
    },
    methods: {
        navigateTo(route) {
            router.visit(route);
        },
        handleNotifAction(notif) {
            if (notif.action_url) {
                router.visit(notif.action_url);
            }
        },
    },
};
</script>

<style scoped>
.cuti-app-container {
    padding: 0;
    min-height: 100vh;
}

.main-content-wrapper {
    max-width: 1680px;
    margin: 0 auto;
    padding: 0 0.75rem;
}

.fallback-banner {
    display: flex;
    gap: 0.9rem;
    align-items: flex-start;
    padding: 1rem 1.1rem;
    border-radius: 1rem;
    background: linear-gradient(135deg, #fff7ed, #ffffff);
    border: 1px solid #fed7aa;
    box-shadow: 0 10px 24px rgba(124, 45, 18, 0.05);
}

.fallback-banner__icon {
    width: 42px;
    height: 42px;
    border-radius: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: #ffedd5;
    color: #c2410c;
}

@media (max-width: 768px) {
    .main-content-wrapper {
        padding: 0 0.5rem;
    }
}
</style>
