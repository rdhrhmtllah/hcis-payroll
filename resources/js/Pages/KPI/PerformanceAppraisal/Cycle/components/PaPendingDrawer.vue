<template>
    <HcisDrawer
        :model-value="modelValue"
        title="Keranjang peserta tertunda"
        :description="cycle ? `${cycle.Kode_Cycle} · perlu ditinjau sebelum cycle dikunci` : ''"
        icon="exclamation-triangle-fill"
        sentiment="warning"
        size="38rem"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <div class="pa-pending">
            <div class="pa-pending__intro">
                <div class="pa-pending__count">
                    <strong>{{ items.length }}</strong
                    ><span>baris tertunda</span>
                </div>
                <p>
                    Daftar ini adalah keranjang yang dikembalikan server. Daftar kosong berarti keranjang bersih, bukan
                    gagal memuat.
                </p>
            </div>

            <HcisResultState
                v-if="error"
                :status="errorStatus === 403 ? 'warning' : 'danger'"
                variant="flat"
                layout="inline"
                :title="errorStatus === 403 ? 'Akses keranjang tidak tersedia' : 'Keranjang gagal dimuat'"
                :description="error"
                primary-text="Coba lagi"
                primary-icon="arrow-clockwise"
                :show-retry="errorStatus !== 403"
                @primary-click="$emit('retry')"
            />
            <div v-if="error && (errorCode || requestId)" class="pa-pending__error-meta" role="note">
                <span v-if="errorCode">Kode: {{ errorCode }}</span>
                <span v-if="requestId">Request ID: {{ requestId }}</span>
            </div>
            <div v-else-if="loading" class="pa-pending__loading" aria-live="polite">
                <HcisIcon name="arrow-repeat" size="lg" animation="spin" color="warning" />
                <span>Memuat peserta tertunda...</span>
            </div>
            <HcisResultState
                v-else-if="items.length === 0"
                status="success"
                variant="flat"
                title="Keranjang bersih"
                description="Tidak ada peserta yang menahan proses penguncian cycle ini."
                :show-retry="false"
            />
            <div v-else class="pa-pending__list">
                <article v-for="item in items" :key="item.Id_PA_Participant" class="pa-pending__item">
                    <div class="pa-pending__person">
                        <div class="pa-pending__avatar" aria-hidden="true">
                            <HcisIcon name="person-fill" size="sm" />
                        </div>
                        <div>
                            <strong>{{ item.Nama_Karyawan_Saat_Cycle }}</strong
                            ><span>{{ item.Kode_Perusahaan }} · {{ item.Kode_Karyawan }}</span>
                        </div>
                    </div>
                    <div class="pa-pending__level">
                        <span>Level saat cycle</span
                        ><strong>{{ item.Nama_Level_Saat_Cycle || `Level ${item.ID_Level_Saat_Cycle}` }}</strong>
                    </div>
                    <HcisButton
                        v-if="canAssign"
                        label="Kelola rater"
                        variant="secondary"
                        size="xs"
                        leading-icon="people"
                        @click="$emit('assign', item)"
                    />
                </article>
            </div>
        </div>
    </HcisDrawer>
</template>

<script setup>
import { HcisButton, HcisDrawer, HcisIcon, HcisResultState } from '@/components/ui';

defineOptions({ name: 'PaPendingDrawer' });

defineProps({
    modelValue: { type: Boolean, default: false },
    cycle: { type: Object, default: null },
    items: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    errorStatus: { type: Number, default: 0 },
    errorCode: { type: String, default: '' },
    requestId: { type: String, default: '' },
    canAssign: { type: Boolean, default: false },
});

defineEmits(['update:modelValue', 'retry', 'assign']);
</script>

<style scoped>
.pa-pending {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.pa-pending__intro {
    padding: 0.9rem;
    border: 1px solid rgba(245, 158, 11, 0.28);
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(255, 251, 235, 0.88), rgba(255, 255, 255, 0.75));
}
.pa-pending__count {
    display: flex;
    align-items: baseline;
    gap: 0.4rem;
}
.pa-pending__count strong {
    color: #b45309;
    font-size: 1.6rem;
    font-variant-numeric: tabular-nums;
}
.pa-pending__count span {
    color: var(--hcis-text-strong, #334155);
    font-size: 0.78rem;
    font-weight: 700;
}
.pa-pending__intro p {
    margin: 0.25rem 0 0;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.74rem;
    line-height: 1.45;
}
.pa-pending__loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    min-height: 10rem;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.8rem;
}
.pa-pending__error-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.8rem;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.68rem;
}
.pa-pending__list {
    display: grid;
    gap: 0.65rem;
}
.pa-pending__item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 0.7rem;
    padding: 0.8rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(255, 255, 255, 0.8);
}
.pa-pending__person {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    min-width: 0;
}
.pa-pending__avatar {
    width: 2rem;
    height: 2rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    color: #b45309;
    border-radius: 50%;
    background: rgba(245, 158, 11, 0.14);
}
.pa-pending__person div:last-child {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}
.pa-pending__person strong {
    overflow: hidden;
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.8rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pa-pending__person span,
.pa-pending__level span {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.68rem;
}
.pa-pending__level {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    text-align: right;
}
.pa-pending__level strong {
    color: #92400e;
    font-size: 0.72rem;
}
.pa-pending__item > :deep(.hcis-btn) {
    grid-column: 1 / -1;
    justify-self: end;
}
@media (max-width: 640px) {
    .pa-pending {
        gap: 0.75rem;
    }
    .pa-pending__intro {
        padding: 0.75rem;
    }
    .pa-pending__count strong {
        font-size: 1.35rem;
    }
    .pa-pending__item {
        grid-template-columns: 1fr;
        padding: 0.65rem 0.75rem;
        gap: 0.45rem;
    }
    .pa-pending__level {
        align-items: flex-start;
        text-align: left;
    }
    .pa-pending__item > :deep(.hcis-btn) {
        justify-self: stretch;
        width: 100%;
    }
}
</style>
