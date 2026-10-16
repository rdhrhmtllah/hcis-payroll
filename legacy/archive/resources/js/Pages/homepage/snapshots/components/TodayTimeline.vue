<template>
    <div v-if="events.length" class="today-timeline mb-4">
        <div class="stitch-card p-4 border-0 shadow-sm rounded-4 bg-white">
            <h5 class="fw-bold mb-4 d-flex align-items-center gap-2">
                <div class="icon-box" style="background:#F0FDF4;"><i class="bi bi-clock-history" style="color:#10B981;"></i></div>
                Timeline Hari Ini
            </h5>
            <div class="timeline-container">
                <div v-for="(e, i) in visibleEvents" :key="i" class="timeline-item d-flex gap-3 mb-0">
                    <div class="timeline-time text-end" style="width:50px;flex-shrink:0;">
                        <small class="fw-bold" style="color:#64748B;">{{ e.time }}</small>
                    </div>
                    <div class="timeline-track d-flex flex-column align-items-center" style="width:24px;flex-shrink:0;">
                        <div class="timeline-dot-outer rounded-circle d-flex align-items-center justify-content-center" :style="{ background: hexToRgba(e.color, 0.15) }">
                            <i :class="e.icon" :style="{ color: e.color, fontSize: '0.7rem' }"></i>
                        </div>
                        <div v-if="i < visibleEvents.length - 1" class="timeline-line flex-grow-1" :style="{ background: hexToRgba(e.color, 0.2) }"></div>
                    </div>
                    <div class="timeline-content pb-4">
                        <p class="mb-0 small fw-bold">{{ e.title }}</p>
                        <small class="text-muted">{{ e.subtitle }}</small>
                    </div>
                </div>
            </div>
            <button v-if="events.length > maxVisible && !showAll" class="btn btn-sm btn-link text-muted fw-semibold text-decoration-none w-100" @click="showAll = true">
                Tampilkan {{ events.length - maxVisible }} lainnya <i class="bi bi-chevron-down ms-1"></i>
            </button>
        </div>
    </div>
</template>

<script>
export default {
    name: 'TodayTimeline',
    props: {
        events: { type: Array, default: () => [] },
        maxVisible: { type: Number, default: 5 },
    },
    data() { return { showAll: false }; },
    computed: {
        visibleEvents() { return this.showAll ? this.events : this.events.slice(0, this.maxVisible); },
    },
    methods: {
        hexToRgba(hex, alpha) {
            if (!hex) return `rgba(100,116,139,${alpha})`;
            const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
            return `rgba(${r},${g},${b},${alpha})`;
        },
    },
};
</script>

<style scoped>
.icon-box { width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:10px;font-size:1.15rem;flex-shrink:0; }
.timeline-dot-outer { width:24px;height:24px;flex-shrink:0; }
.timeline-line { width:2px;min-height:20px; }
</style>
