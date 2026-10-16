<template>
    <div class="sop-ring" :style="{ width: size + 'px', height: size + 'px' }">
        <svg :width="size" :height="size" :viewBox="`0 0 ${size} ${size}`">
            <defs>
                <linearGradient :id="gradId" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" :stop-color="color" />
                    <stop offset="100%" :stop-color="color2" />
                </linearGradient>
                <filter :id="glowId" x1="-20%" y1="-20%" width="140%" height="140%">
                    <feGaussianBlur stdDeviation="3" result="blur" />
                    <feComposite in="SourceGraphic" in2="blur" operator="over" />
                </filter>
            </defs>
            <circle class="ring-track" :cx="c" :cy="c" :r="r" :stroke-width="stroke" fill="none" />
            <circle
                class="ring-fill"
                :cx="c"
                :cy="c"
                :r="r"
                :stroke="`url(#${gradId})`"
                :stroke-width="stroke"
                fill="none"
                stroke-linecap="round"
                :stroke-dasharray="circ"
                :stroke-dashoffset="offset"
                :transform="`rotate(-90 ${c} ${c})`"
                :filter="`url(#${glowId})`"
            />
        </svg>
        <div class="ring-center">
            <div class="ring-value" :style="{ color: color }">
                {{ display }}<span v-if="unit" class="ring-unit">{{ unit }}</span>
            </div>
            <div v-if="label" class="ring-label">{{ label }}</div>
        </div>
    </div>
</template>

<script>
/**
 * SopRing — radial progress ring dengan animasi fill + angka count-up.
 * Pakai: <SopRing :value="72" :size="140" label="Kepatuhan" unit="%" />
 */
let _uid = 0;
export default {
    name: 'SopRing',
    props: {
        value: { type: Number, default: 0 }, // 0..100
        size: { type: Number, default: 140 },
        stroke: { type: Number, default: 12 },
        color: { type: String, default: '#6366f1' },
        color2: { type: String, default: '#818cf8' },
        label: { type: String, default: '' },
        unit: { type: String, default: '%' },
    },
    data() {
        const id = _uid++;
        return { anim: 0, gradId: `sopRingGrad${id}`, glowId: `sopRingGlow${id}` };
    },
    computed: {
        c() {
            return this.size / 2;
        },
        r() {
            return this.size / 2 - this.stroke / 2 - 2;
        },
        circ() {
            return 2 * Math.PI * this.r;
        },
        pct() {
            return Math.max(0, Math.min(100, this.anim));
        },
        offset() {
            return this.circ * (1 - this.pct / 100);
        },
        display() {
            return Math.round(this.anim);
        },
    },
    mounted() {
        this.animateTo(this.value);
    },
    watch: {
        value(v) {
            this.animateTo(v);
        },
    },
    methods: {
        animateTo(target) {
            const reduce =
                typeof window !== 'undefined' &&
                window.matchMedia &&
                window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const to = Math.max(0, Math.min(100, target || 0));
            if (reduce) {
                this.anim = to;
                return;
            }
            const from = this.anim;
            const dur = 900;
            const start = performance.now();
            const step = (now) => {
                const t = Math.min(1, (now - start) / dur);
                const ease = 1 - Math.pow(1 - t, 3);
                this.anim = from + (to - from) * ease;
                if (t < 1) requestAnimationFrame(step);
                else this.anim = to;
            };
            requestAnimationFrame(step);
        },
    },
};
</script>

<style scoped>
.sop-ring {
    position: relative;
    display: inline-grid;
    place-items: center;
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.sop-ring:hover {
    transform: scale(1.04);
}
.ring-track {
    stroke: rgba(99, 102, 241, 0.12);
}
.ring-fill {
    transition: stroke-dashoffset 0.1s linear;
}
.ring-center {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
}
.ring-value {
    font-size: 1.7rem;
    font-weight: 800;
    line-height: 1;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.03em;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
}
.ring-unit {
    font-size: 0.9rem;
    font-weight: 700;
    margin-left: 2px;
}
.ring-label {
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--text-muted, var(--hcis-slate-500));
    margin-top: 0.25rem;
}
</style>
