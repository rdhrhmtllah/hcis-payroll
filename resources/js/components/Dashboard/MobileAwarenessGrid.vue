<template>
    <section class="mobile-awareness-grid">
        <button
            v-for="item in mobileAwarenessCards"
            :key="item.key"
            type="button"
            class="mobile-awareness-card"
            :class="[`is-${item.tone}`, { 'is-skeleton': item.loading }]"
            @click="$emit('awareness-click', item.key)"
        >
            <div v-if="item.loading" class="mobile-awareness-card__icon skeleton-avatar"></div>

            <div v-else class="mobile-awareness-card__icon">
                <i :class="item.icon"></i>
            </div>

            <div v-if="item.loading" class="mobile-awareness-card__copy">
                <div class="leader-skeleton-line is-title mb-1" style="width: 45%"></div>
                <div class="leader-skeleton-line mb-1" style="width: 75%"></div>
            </div>

            <div v-else class="mobile-awareness-card__copy">
                <strong>{{ item.value }}</strong>
                <span>{{ item.label }}</span>
            </div>
        </button>
    </section>
</template>

<script setup>
defineProps({
    mobileAwarenessCards: {
        type: Array,
        default: () => []
    }
})

defineEmits(['awareness-click'])
</script>

<style scoped>
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
    background: #eef2ff;
    color: #4f46e5;
    transition: background 150ms ease;
}

.mobile-awareness-card__copy {
    margin-top: 0.48rem;
    min-width: 0;
}

.mobile-awareness-card__copy strong {
    display: block;
    color: #0f172a;
    font-size: 1rem;
    font-weight: 950;
    line-height: 1;
    animation: fadeIn 0.4s ease-in-out;
}

.mobile-awareness-card__copy span,
.mobile-awareness-card__copy small {
    display: block;
    margin-top: 0.18rem;
    font-size: 0.66rem;
    font-weight: 850;
    color: #64748b;
    line-height: 1.2;
    animation: fadeIn 0.4s ease-in-out;
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

.mobile-awareness-card.is-skeleton .mobile-awareness-card__icon {
    background: linear-gradient(90deg, #e2e8f0 0%, #f1f5f9 30%, #e2e8f0 50%, #e2e8f0 100%);
    background-size: 200% 100%;
    animation: awarenessShimmer 1.5s linear infinite;
    color: transparent;
}

.leader-skeleton-line {
    width: 100%;
    height: 0.6rem;
    border-radius: 999px;
    background: linear-gradient(90deg, #e2e8f0 0%, #f1f5f9 30%, #e2e8f0 50%, #e2e8f0 100%);
    background-size: 200% 100%;
    animation: awarenessShimmer 1.5s linear infinite;
}

.leader-skeleton-line.is-title {
    height: 0.8rem;
}

.leader-skeleton-line.is-short {
    width: 45%;
}

.mb-1 {
    margin-bottom: 0.25rem;
}

@keyframes awarenessShimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(2px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
