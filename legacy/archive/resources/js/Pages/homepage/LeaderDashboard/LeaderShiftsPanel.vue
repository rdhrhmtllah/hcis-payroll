<template>
    <div class="leader-panel">
        <div class="leader-panel__head">
            <div>
                <span class="section-label">Shift Distribution</span>
                <h2>Distribusi Shift & Coverage</h2>
            </div>

            <div class="date-picker-control">
                <i class="bi bi-calendar-event"></i>
                <select :value="selectedShiftDate" @change="$emit('update:selected-date', $event.target.value)">
                    <option v-for="date in availableShiftDates" :key="date.value" :value="date.value">
                        {{ date.label }}
                    </option>
                </select>
            </div>
        </div>

        <CardSkeleton v-if="loading" variant="ESS/Workflow/Task/timeline" />

        <CardState v-else-if="!selectedDateShiftSummary.length" type="empty" :message="'Belum ada distribusi shift untuk tanggal ini.'" :retryable="false" />

        <div v-else class="shift-board-grid">
            <button
                v-for="shift in selectedDateShiftSummary"
                :key="shift.key"
                type="button"
                class="shift-card"
                :class="{ 'is-warning': shift.hasGap }"
                @click="$emit('update:selected-key', shift.key)"
            >
                <div class="shift-card__head">
                    <div>
                        <strong>{{ shift.name }}</strong>
                        <span>{{ shift.time }}</span>
                    </div>

                    <b>{{ shift.memberCount }}</b>
                </div>

                <div class="shift-card__footer">
                    <span>{{ shift.hasGap ? 'Perlu coverage' : 'Coverage aman' }}</span>
                    <i :class="shift.hasGap ? 'bi bi-exclamation-circle' : 'bi bi-check2-circle'"></i>
                </div>
            </button>
        </div>

        <div v-if="selectedShiftDetail" class="selected-shift-detail">
            <div class="selected-shift-detail__head">
                <div>
                    <strong>{{ selectedShiftDetail.name }}</strong>
                    <span>{{ selectedShiftDetail.time }} - {{ selectedShiftDate }}</span>
                </div>

                <small :class="{ 'is-warning': selectedShiftDetail.hasGap }">
                    {{ selectedShiftDetail.hasGap ? 'Coverage perlu dicek' : 'Coverage aman' }}
                </small>
            </div>

            <div class="selected-shift-members">
                <div
                    v-for="memberId in selectedShiftDetail.memberIds || []"
                    :key="memberId"
                    class="shift-person"
                >
                    <div class="member-avatar is-sm">{{ String(memberId).slice(0, 2) }}</div>
                    <div>
                        <strong>{{ memberId }}</strong>
                        <span>Member shift</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import CardSkeleton from '../snapshots/components/CardSkeleton.vue';
import CardState from '../snapshots/components/CardState.vue';

defineProps({
    loading: { type: Boolean, default: false },
    shifts: { type: Array, default: () => [] },
    availableShiftDates: { type: Array, default: () => [] },
    selectedShiftDate: { type: String, default: '' },
    selectedShiftKey: { type: String, default: '' },
    selectedShiftDetail: { type: Object, default: null },
    selectedDateShiftSummary: { type: Array, default: () => [] },
});

defineEmits(['update:selected-date', 'update:selected-key']);
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
    gap: 0.75rem;
}

.leader-panel__head h2 {
    margin: 0.12rem 0 0;
    font-size: 1rem;
    font-weight: 950;
}

.date-picker-control {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0 0.6rem;
    min-height: 2.15rem;
    border-radius: 999px;
    background: white;
    border: 1px solid #e2e8f0;
}

.date-picker-control select {
    border: 0;
    outline: 0;
    background: transparent;
    font-size: 0.7rem;
}

.shift-board-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.55rem;
}

.shift-card {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 0.7rem;
    border-radius: 0.9rem;
    border: 1px solid #e2e8f0;
    background: white;
    text-align: left;
}

.shift-card__head,
.selected-shift-detail__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
}

.shift-card__head strong,
.selected-shift-detail__head strong {
    display: block;
    font-size: 0.76rem;
    font-weight: 900;
}

.shift-card__head span,
.selected-shift-detail__head span {
    font-size: 0.62rem;
    color: #64748b;
}

.shift-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.62rem;
    color: #64748b;
}

.selected-shift-detail {
    padding: 0.7rem;
    border-radius: 0.9rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.selected-shift-members {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.45rem;
    margin-top: 0.6rem;
}

.shift-person {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    border-radius: 0.75rem;
    background: white;
}

.member-avatar.is-sm {
    width: 1.8rem;
    height: 1.8rem;
    border-radius: 0.55rem;
    font-size: 0.62rem;
}

.shift-person strong {
    display: block;
    font-size: 0.68rem;
    font-weight: 900;
}

.shift-person span,
.selected-shift-detail small {
    font-size: 0.6rem;
    color: #64748b;
}

.selected-shift-detail small.is-warning {
    color: #b45309;
}

@media (max-width: 720px) {
    .shift-board-grid,
    .selected-shift-members {
        grid-template-columns: 1fr;
    }
}
</style>
