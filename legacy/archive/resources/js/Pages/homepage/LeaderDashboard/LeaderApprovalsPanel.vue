<template>
    <div class="leader-panel">
        <div class="leader-panel__head">
            <div>
                <span class="section-label">Approval</span>
                <h2>Antrian Approval</h2>
            </div>

            <span class="panel-count">{{ pagination.total || approvals.length }}</span>
        </div>

        <CardSkeleton v-if="loading" variant="default" />

        <CardState v-else-if="!approvals.length" type="empty" :message="'Tidak ada approval yang menunggu.'" :retryable="false" />

        <div v-else class="approval-list">
            <button v-for="item in approvals" :key="item.id" type="button" class="approval-item">
                <div>
                    <strong>{{ item.memberName }}</strong>
                    <span>{{ item.title }}</span>
                </div>
                <small>{{ item.statusLabel }}</small>
            </button>
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
    approvals: { type: Array, default: () => [] },
    pagination: {
        type: Object,
        default: () => ({ current_page: 1, last_page: 1, per_page: 6, total: 0, has_prev: false, has_next: false }),
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

.approval-list {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.approval-item {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.65rem 0.7rem;
    border-radius: 0.8rem;
    border: 1px solid #e2e8f0;
    background: white;
    text-align: left;
}

.approval-item strong {
    display: block;
    font-size: 0.74rem;
    font-weight: 900;
}

.approval-item span,
.approval-item small {
    font-size: 0.62rem;
    color: #64748b;
}

.pagination-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
}

.pagination-btn {
    min-height: 2rem;
    padding: 0 0.8rem;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    background: white;
    font-size: 0.65rem;
    font-weight: 800;
}
</style>
