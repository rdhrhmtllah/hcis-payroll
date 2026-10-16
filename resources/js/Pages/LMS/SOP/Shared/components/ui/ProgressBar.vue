<template>
    <div class="sop-prog-wrap">
        <div class="sop-prog">
            <i :style="{ width: pct + '%' }" :class="barClass"></i>
        </div>
        <span v-if="showLabel" class="sop-prog-label">{{ label }}</span>
    </div>
</template>

<script>
/**
 * ProgressBar — bar progres 0..100.
 * Pakai: <ProgressBar :value="60" :total="5" :done="3" showLabel />
 */
export default {
    name: 'ProgressBar',
    props: {
        value: { type: Number, default: 0 }, // persen 0..100
        done: { type: Number, default: null },
        total: { type: Number, default: null },
        showLabel: { type: Boolean, default: false },
    },
    computed: {
        pct() {
            return Math.max(0, Math.min(100, this.value || 0));
        },
        barClass() {
            if (this.pct >= 100) return 'is-ok';
            if (this.pct <= 20) return 'is-low';
            return 'is-mid';
        },
        label() {
            if (this.done !== null && this.total !== null) {
                return `${this.done} dari ${this.total} item`;
            }
            return `${this.pct}%`;
        },
    },
};
</script>

<style scoped>
.sop-prog-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.sop-prog {
    height: 6px;
    border-radius: 4px;
    background: #e9edf2;
    overflow: hidden;
    flex: 1;
    max-width: 240px;
}
.sop-prog i {
    display: block;
    height: 100%;
    border-radius: 4px;
    background: var(--hcis-indigo-600);
    transition: none;
}
.sop-prog i.is-ok {
    background: var(--hcis-emerald-600);
}
.sop-prog i.is-mid {
    background: var(--hcis-amber-600);
}
.sop-prog i.is-low {
    background: var(--hcis-red-600);
}
.sop-prog-label {
    font-size: 0.72rem;
    color: #6b7684;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
</style>
