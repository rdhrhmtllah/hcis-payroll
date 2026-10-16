<template>
    <div class="leader-panel">
        <div class="leader-panel__head">
            <div>
                <span class="section-label">Heatmap</span>
                <h2>Pola Issue Tim</h2>
            </div>

            <span class="panel-count">{{ pagination.total || rows.length }}</span>
        </div>

        <CardSkeleton v-if="loading" variant="ESS/Workflow/Task/timeline" />

        <CardState v-else-if="!rows.length" type="empty" :message="'Heatmap akan tampil saat data anggota siap.'" :retryable="false" />

        <div v-else class="heatmap-board">
            <div v-for="member in rows" :key="member.id" class="heatmap-row">
                <strong>{{ member.name }}</strong>
                <div>
                    <span
                        v-for="day in member.attendance?.dailyLog || []"
                        :key="`${member.id}-${day.date}`"
                        class="heat-cell"
                        :class="`is-${day.tone || 'default'}`"
                        :title="`${member.name} - ${day.date} - ${day.label}`"
                    ></span>
                </div>
            </div>
        </div>

        <div v-if="pagination.total > pagination.per_page" class="pagination-row">
            <button type="button" class="pagination-btn" :disabled="!pagination.has_prev || loading" @click="$emit('load-page', pagination.current_page - 1)">
                Prev
            </button>

            <span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span>

            <button type="button" class="pagination-btn" :disabled="!pagination.has_next || loading" @click="$emit('load-page', pagination.current_page + 1)">
                Next
            </button>
        </div>
    </div>
</template>

<script setup>
import CardSkeleton from '../snapshots/components/CardSkeleton.vue';
import CardState from '../snapshots/components/CardState.vue';

defineProps({
    loading: { type: Boolean, default: false },
    rows: { type: Array, default: () => [] },
    pagination: {
        type: Object,
        default: () => ({ current_page: 1, last_page: 1, per_page: 8, total: 0, has_prev: false, has_next: false }),
    },
});

defineEmits(['load-page']);
</script>

<style scoped>
.leader-panel {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.leader-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.leader-panel__head h2 {
    margin: 0.12rem 0 0;
    font-size: 1rem;
    font-weight: 950;
}

.panel-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.1rem;
    padding: 0.3rem 0.55rem;
    border-radius: 999px;
    background: #fef3c7;
    color: #92400e;
    font-size: 0.68rem;
    font-weight: 900;
}

.heatmap-board {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.heatmap-row {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 0.5rem;
    align-items: center;
    padding: 0.55rem;
    border-radius: 0.85rem;
    background: white;
    border: 1px solid #e2e8f0;
}

.heatmap-row strong {
    font-size: 0.7rem;
    font-weight: 900;
}

.heatmap-row > div {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.25rem;
}

.heat-cell {
    display: block;
    aspect-ratio: 1 / 1;
    border-radius: 0.35rem;
    background: #e2e8f0;
}

.heat-cell.is-success {
    background: #22c55e;
}

.heat-cell.is-warning {
    background: #f59e0b;
}

.heat-cell.is-danger {
    background: #ef4444;
}

.heat-cell.is-info {
    background: #38bdf8;
}

@media (max-width: 720px) {
    .heatmap-row {
        grid-template-columns: 1fr;
    }
}
</style>
