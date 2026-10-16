<template>
    <article class="mobile-hero is-primary" :class="[{ 'is-skeleton': globalSummaryLoading }]">
        <div v-if="!globalSummaryLoading" class="mobile-hero__glow mobile-hero__glow--one"></div>
        <div v-if="!globalSummaryLoading" class="mobile-hero__glow mobile-hero__glow--two"></div>

        <div class="mobile-hero__header">
            <div class="mobile-hero__topline">
                <div>
                    <template v-if="globalSummaryLoading">
                        <div class="leader-skeleton-line is-short mb-1" style="width: 42%"></div>
                        <div class="leader-skeleton-line is-title mb-1" style="width: 65%; height: 1rem"></div>
                        <div class="leader-skeleton-line is-short" style="width: 50%"></div>
                    </template>

                    <template v-else>
                        <span class="mobile-hero__eyebrow">Leader Monitoring</span>
                        <strong class="mobile-hero__shift">Team Monitoring Center</strong>
                        <small class="mobile-hero__meta">{{ activePeriodLabel }}</small>
                    </template>
                </div>
            </div>

            <div class="mobile-hero__actions">
                <button
                    v-if="!globalSummaryLoading"
                    @click="$emit('visit-personal-dashboard')"
                    type="button"
                    class="mobile-hero-team-btn"
                    title="Dashboard Personal"
                >
                    <i class="bi bi-person-badge"></i>
                </button>
            </div>
        </div>

        <div class="mobile-hero__times">
            <div class="mobile-time-card">
                <template v-if="globalSummaryLoading">
                    <div class="leader-skeleton-line is-short mb-1" style="width: 38%; margin: 0 auto"></div>
                    <div class="leader-skeleton-line is-title mb-1" style="width: 58%; height: 1.2rem; margin: 0 auto"></div>
                    <div class="leader-skeleton-line is-short" style="width: 52%; margin: 0 auto"></div>
                </template>

                <template v-else>
                    <span>Hadir Hari Ini</span>
                    <strong>{{ summary?.presentToday }}</strong>
                    <small>{{ todayLabel }}</small>
                </template>
            </div>

            <div class="mobile-time-card">
                <template v-if="globalSummaryLoading">
                    <div class="leader-skeleton-line is-short mb-1" style="width: 38%; margin: 0 auto"></div>
                    <div class="leader-skeleton-line is-title mb-1" style="width: 58%; height: 1.2rem; margin: 0 auto"></div>
                    <div class="leader-skeleton-line is-short" style="width: 52%; margin: 0 auto"></div>
                </template>

                <template v-else>
                    <span>Total Anggota</span>
                    <strong>{{ summary?.totalMembers }}</strong>
                    <small>team aktif</small>
                </template>
            </div>
        </div>

        <div class="mobile-hero__brief">
            <div>
                <template v-if="globalSummaryLoading">
                    <div class="leader-skeleton-line is-short mb-1" style="width: 55%"></div>
                    <div class="leader-skeleton-line is-title" style="width: 80%"></div>
                </template>

                <template v-else>
                    <span>Approval</span>
                    <strong>{{ summary?.approvalQueue }} Antrian</strong>
                </template>
            </div>

            <div style="grid-column: span 2;" @click="$emit('scroll-to-action-center')" role="button" tabindex="0">
                <template v-if="isActionCenterPending">
                    <div class="leader-skeleton-line is-short mb-1" style="width: 45%"></div>
                    <div class="leader-skeleton-line is-title" style="width: 70%"></div>
                </template>

                <template v-else>
                    <span>Action Center</span>
                    <strong v-if="needsActionError" class="text-danger">Data belum tersedia</strong>
                    <strong v-else>{{ heroActionCountLabel }} Perlu Ditindak</strong>
                </template>
            </div>
        </div>
    </article>
</template>

<script setup>
defineProps({
    globalSummaryLoading: {
        type: Boolean,
        default: true,
    },
    activePeriodLabel: {
        type: String,
        default: '',
    },
    summary: {
        type: Object,
        default: () => ({
            presentToday: 0,
            totalMembers: 0,
            approvalQueue: 0,
        }),
    },
    todayLabel: {
        type: String,
        default: 'hari ini',
    },
    isActionCenterPending: {
        type: Boolean,
        default: false,
    },
    needsActionError: {
        type: Boolean,
        default: false,
    },
    heroActionCountLabel: {
        type: [String, Number],
        default: 0,
    },
});

defineEmits(['visit-personal-dashboard', 'scroll-to-action-center']);
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
.mobile-hero__brief {
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

.mobile-hero .leader-skeleton-line {
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
</style>
