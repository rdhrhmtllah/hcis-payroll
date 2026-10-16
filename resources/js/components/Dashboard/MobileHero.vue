<template>
    <article class="mobile-hero is-primary" :class="[{ 'is-skeleton': isDashboardBootstrapping }]">
        <div v-if="!isDashboardBootstrapping" class="mobile-hero__glow mobile-hero__glow--one"></div>
        <div v-if="!isDashboardBootstrapping" class="mobile-hero__glow mobile-hero__glow--two"></div>

        <div class="mobile-hero__header">
            <div class="mobile-hero__topline">
                <div>
                    <template v-if="isDashboardBootstrapping">
                        <div class="leader-skeleton-line is-short mb-1" style="width: 42%"></div>
                        <div class="leader-skeleton-line is-title mb-1" style="width: 65%; height: 1rem"></div>
                        <div class="leader-skeleton-line is-short" style="width: 50%"></div>
                    </template>

                    <template v-else>
                        <span class="mobile-hero__eyebrow">{{ mobileHeroDateLabel }}</span>
                        <strong class="mobile-hero__shift">{{ todaySnapshot?.shiftLabel }}</strong>
                        <small class="mobile-hero__meta">{{ todaySnapshot?.shiftTime }}</small>
                    </template>
                </div>
            </div>

            <div class="mobile-hero__actions">
                <div
                    class="mobile-status-pill"
                    :class="isDashboardBootstrapping ? 'is-skeleton' : `is-${mobileHeroStatus?.tone || 'primary'}`"
                >
                    <template v-if="isDashboardBootstrapping">
                        <div
                            class="skeleton-avatar"
                            style="width: 0.9rem; height: 0.9rem; border-radius: 0.3rem; flex-shrink: 0"
                        ></div>
                        <div class="leader-skeleton-line is-short" style="width: 3rem"></div>
                    </template>

                    <template v-else>
                        <i :class="mobileHeroStatus?.icon"></i>
                        <span>{{ todaySnapshot?.statusLabel }}</span>
                    </template>
                </div>

                <button
                    v-if="isLeader && !isDashboardBootstrapping"
                    @click="$emit('visit-leader-dashboard')"
                    type="button"
                    class="mobile-hero-team-btn"
                    data-tour="team-dashboard-btn"
                    :class="{ 'has-badge': teamDashboardBadgeVisible }"
                    title="Dashboard Tim"
                >
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span v-if="teamDashboardBadgeVisible" :key="teamDashboardBadgeLabel" class="team-dashboard-badge">
                        {{ teamDashboardBadgeLabel }}
                    </span>
                </button>
            </div>
        </div>

        <div class="mobile-hero__times">
            <div class="mobile-time-card">
                <template v-if="isDashboardBootstrapping">
                    <div class="leader-skeleton-line is-short mb-1" style="width: 38%; margin: 0 auto"></div>
                    <div
                        class="leader-skeleton-line is-title mb-1"
                        style="width: 58%; height: 1.2rem; margin: 0 auto"
                    ></div>
                    <div class="leader-skeleton-line is-short" style="width: 52%; margin: 0 auto"></div>
                </template>

                <template v-else>
                    <span>Check In</span>
                    <strong>{{ todaySnapshot?.checkIn }}</strong>
                    <small>{{ todaySnapshot?.checkInMeta }}</small>
                </template>
            </div>

            <div class="mobile-time-card">
                <template v-if="isDashboardBootstrapping">
                    <div class="leader-skeleton-line is-short mb-1" style="width: 38%; margin: 0 auto"></div>
                    <div
                        class="leader-skeleton-line is-title mb-1"
                        style="width: 58%; height: 1.2rem; margin: 0 auto"
                    ></div>
                    <div class="leader-skeleton-line is-short" style="width: 52%; margin: 0 auto"></div>
                </template>

                <template v-else>
                    <span>Check Out</span>
                    <strong>{{ todaySnapshot?.checkOut }}</strong>
                    <small>{{ todaySnapshot?.checkOutMeta }}</small>
                </template>
            </div>
        </div>

        <div class="mobile-hero__note">
            <template v-if="isDashboardBootstrapping">
                <div
                    class="skeleton-avatar"
                    style="width: 1.4rem; height: 1.4rem; border-radius: 0.4rem; flex-shrink: 0"
                ></div>
                <div class="leader-skeleton-line" style="width: 75%"></div>
            </template>

            <template v-else>
                <i class="bi bi-calendar-range"></i>
                <span class="mobile-hero__cutoff-text"
                    >Periode Cutoff: <strong>{{ cutoffLabel }}</strong></span
                >
            </template>
        </div>

        <div class="mobile-hero__brief">
            <div>
                <template v-if="isDashboardBootstrapping">
                    <div class="leader-skeleton-line is-short mb-1" style="width: 55%"></div>
                    <div class="leader-skeleton-line is-title" style="width: 80%"></div>
                </template>

                <template v-else>
                    <span>Cutoff</span>
                    <strong>{{ cutoffMonthLabel }}, {{ workdayBrief?.cutoffProgress }}</strong>
                </template>
            </div>

            <div>
                <template v-if="isDashboardBootstrapping">
                    <div class="leader-skeleton-line is-short mb-1" style="width: 60%"></div>
                    <div class="leader-skeleton-line is-title" style="width: 75%"></div>
                </template>

                <template v-else>
                    <span>Next Shift</span>
                    <strong>{{ workdayBrief?.nextSchedule }}</strong>
                </template>
            </div>

            <div>
                <template v-if="isDashboardBootstrapping">
                    <div class="leader-skeleton-line is-short mb-1" style="width: 45%"></div>
                    <div class="leader-skeleton-line is-title" style="width: 70%"></div>
                </template>

                <template v-else>
                    <template v-if="isLeader">
                        <span>Approval</span>
                        <strong>{{ pendingApprovals }} Antrian</strong>
                    </template>
                    <template v-else>
                        <span>KPI</span>
                        <strong>{{ heroKpi?.periodLabel }}</strong>
                    </template>
                </template>
            </div>
        </div>
    </article>
</template>

<script setup>
import { DotLottieVue } from '@lottiefiles/dotlottie-vue';

defineProps({
    isDashboardBootstrapping: {
        type: Boolean,
        default: true,
    },
    todayAwareness: {
        type: Object,
        default: () => ({ tone: 'primary', icon: '', message: '' }),
    },
    mobileHeroDateLabel: {
        type: String,
        default: '',
    },
    todaySnapshot: {
        type: Object,
        default: () => ({
            shiftLabel: '',
            shiftTime: '',
            statusLabel: '',
            checkIn: '',
            checkInMeta: '',
            checkOut: '',
            checkOutMeta: '',
        }),
    },
    mobileHeroStatus: {
        type: Object,
        default: () => ({ tone: 'primary', icon: '' }),
    },
    isLeader: {
        type: Boolean,
        default: false,
    },
    teamDashboardBadgeVisible: {
        type: Boolean,
        default: false,
    },
    teamDashboardBadgeLabel: {
        type: String,
        default: '',
    },
    cutoffMonthLabel: {
        type: String,
        default: '',
    },
    workdayBrief: {
        type: Object,
        default: () => ({ cutoffProgress: '', nextSchedule: '' }),
    },
    heroKpi: {
        type: Object,
        default: () => ({ periodLabel: '' }),
    },
    cutoffLabel: {
        type: String,
        default: '',
    },
    pendingApprovals: {
        type: [Number, String],
        default: 0,
    },
});

defineEmits(['visit-leader-dashboard']);
</script>

<style scoped>
.mobile-hero {
    position: relative;
    overflow: hidden;
    display: grid;
    gap: 0.82rem;
    padding: 1rem;
    border-radius: 1.05rem;
    color: #fff;
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.18), transparent 12rem),
        linear-gradient(135deg, #4f46e5, #2563eb);
    box-shadow: 0 14px 34px rgba(37, 99, 235, 0.18);
}

.mobile-hero.is-skeleton {
    background: linear-gradient(135deg, #4f46e5, #2563eb);
}

.mobile-hero__glow {
    position: absolute;
    border-radius: 999px;
    pointer-events: none;
    opacity: 0.28;
    filter: blur(18px);
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
    background: rgba(255, 255, 255, 0.6);
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
    font-size: 0.68rem;
    font-weight: 950;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.72);
    animation: fadeIn 0.4s ease-in-out;
}

.mobile-hero__shift {
    display: block;
    margin-top: 0.24rem;
    font-size: 1.05rem;
    font-weight: 950;
    line-height: 1.12;
    animation: fadeIn 0.4s ease-in-out;
}

.mobile-hero__meta {
    display: block;
    margin-top: 0.14rem;
    font-size: 0.72rem;
    font-weight: 850;
    color: rgba(255, 255, 255, 0.76);
}

.mobile-status-pill {
    flex: 0 0 auto;
    max-width: 7.25rem;
    padding: 0.38rem 0.58rem;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: rgba(255, 255, 255, 0.14);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    font-size: 0.66rem;
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

.mobile-status-pill.is-skeleton {
    background: rgba(255, 255, 255, 0.2);
    color: rgba(255, 255, 255, 0.7);
}

.mobile-hero__times {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}

.mobile-time-card {
    min-width: 0;
    padding: 0.3rem 0.6rem;
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
    animation: fadeIn 0.4s ease-in-out;
}

.mobile-time-card small {
    margin-top: 0.22rem;
}

.mobile-time-card.is-skeleton,
.mobile-hero .is-skeleton {
    background: rgba(255, 255, 255, 0.16) !important;
    border-color: rgba(255, 255, 255, 0.18) !important;
}

.mobile-hero .leader-skeleton-line,
.mobile-hero .skeleton-avatar {
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0.15) 0%,
        rgba(255, 255, 255, 0.38) 30%,
        rgba(255, 255, 255, 0.15) 50%,
        rgba(255, 255, 255, 0.15) 100%
    );
    background-size: 200% 100%;
    animation: heroShimmer 1.5s linear infinite;
}

@keyframes heroShimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

.mobile-hero__note {
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
}

.mobile-hero__note i {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 1);
    display: flex;
    align-items: center;
    justify-content: center;
}

.mobile-hero__cutoff-text {
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.mobile-hero__cutoff-text strong {
    font-weight: 950;
    color: #fff;
    letter-spacing: 0.01em;
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

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(2px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
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
</style>
