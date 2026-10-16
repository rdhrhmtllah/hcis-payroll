<template>
    <HcisDialog
        :model-value="modelValue"
        title="Audit & riwayat cycle"
        kicker="ADMINISTRATOR PA · AUDIT TRAIL"
        :description="cycle ? `${cycle.Kode_Cycle} · ${cycle.Status_Cycle}` : 'Lini masa keputusan dan aturan yang berjalan.'"
        icon="journal-text"
        sentiment="teal"
        variant="glass"
        size="2xl"
        sticky-footer
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <div class="pa-audit">
            <section class="pa-audit__intro" aria-label="Ringkasan audit">
                <div class="pa-audit__intro-main">
                    <div class="pa-audit__intro-icon"><HcisIcon name="shield-check" size="lg" color="teal" /></div>
                    <div>
                        <span class="pa-eyebrow">JEJAK KEPUTUSAN</span>
                        <h3>{{ cycle?.Nama_Cycle || 'Riwayat cycle' }}</h3>
                        <p>Gunakan audit untuk membedakan keputusan manusia dari aturan sistem yang menghasilkan banyak kejadian sekaligus.</p>
                    </div>
                </div>
                <div class="pa-audit__count-grid" aria-label="Cacah audit">
                    <div><span>Keputusan manusia</span><strong>{{ formatAngka(counts.keputusan_manusia) }}</strong></div>
                    <div><span>Oleh sistem</span><strong>{{ formatAngka(counts.oleh_sistem) }}</strong></div>
                </div>
            </section>

            <section class="pa-audit__filters" aria-label="Saringan audit">
                <HcisFilterBar
                    :search="filters.q"
                    search-placeholder="Cari sasaran atau sebab…"
                    variant="flat"
                    sentiment="teal"
                    :collapsible="false"
                    @update:search="saring({ q: $event })"
                    @reset="$emit('reset-filter')"
                />
                <div class="pa-audit__filter-grid">
                    <div class="pa-audit__field">
                        <label for="pa-audit-type">Jenis kejadian</label>
                        <HcisSelect
                            id="pa-audit-type"
                            :model-value="filters.jenis"
                            placeholder="Semua jenis"
                            aria-label="Saring jenis kejadian"
                            @update:model-value="saring({ jenis: $event })"
                        >
                            <HcisSelectOption value="" label="Semua jenis" />
                            <HcisSelectOption v-for="type in types" :key="type" :value="type" :label="formatJenis(type)" />
                        </HcisSelect>
                    </div>
                    <HcisField
                        id="pa-audit-participant"
                        :model-value="filters.participant_id"
                        label="ID peserta"
                        type="number"
                        placeholder="Semua peserta"
                        leading-icon="person-badge"
                        @update:model-value="saring({ participant_id: $event })"
                    />
                    <HcisSelect
                        id="pa-audit-page-size"
                        :model-value="filters.per_halaman"
                        label=""
                        placeholder="Baris per halaman"
                        aria-label="Jumlah baris per halaman"
                        @update:model-value="ubahUkuran($event)"
                    >
                        <HcisSelectOption :value="10" label="10 baris / halaman" />
                        <HcisSelectOption :value="25" label="25 baris / halaman" />
                        <HcisSelectOption :value="50" label="50 baris / halaman" />
                        <HcisSelectOption :value="100" label="100 baris / halaman" />
                    </HcisSelect>
                </div>
                <div class="pa-audit__filter-footer">
                    <HcisCheckbox
                        :model-value="filters.hanya_keputusan_manusia"
                        label="Hanya keputusan manusia"
                        description="Cacah sistem tetap terlihat di ringkasan."
                        size="sm"
                        variant="button"
                        color="teal"
                        @update:model-value="saring({ hanya_keputusan_manusia: $event })"
                    />
                    <HcisBadge
                        v-if="pagination.total"
                        :label="`${formatAngka(pagination.total)} kejadian`"
                        variant="teal"
                        appearance="subtle"
                        size="sm"
                    />
                </div>
            </section>

            <div v-if="loading" class="pa-audit__loading" aria-live="polite">
                <HcisIcon name="arrow-repeat" size="lg" animation="spin" color="teal" />
                <span>Memuat lini masa audit…</span>
            </div>
            <HcisResultState
                v-else-if="error"
                :status="errorStatus === 403 ? 'warning' : 'danger'"
                variant="flat"
                layout="inline"
                :title="errorStatus === 403 ? 'Akses audit tidak tersedia' : 'Audit belum dapat dimuat'"
                :description="error"
                :error-code="errorCode"
                :request-id="requestId"
                primary-text="Coba lagi"
                primary-icon="arrow-clockwise"
                @primary-click="$emit('retry')"
            />
            <HcisResultState
                v-else-if="!events.length"
                status="info"
                variant="flat"
                title="Belum ada kejadian yang cocok"
                description="Belum ada riwayat untuk saringan ini. Coba hapus saringan atau pilih jenis kejadian lain."
                icon="journal-x"
                :show-retry="false"
            />
            <section v-else class="pa-audit__timeline" aria-label="Lini masa audit">
                <HcisTimeline :items="timelineItems" direction="vertical" variant="compact" sentiment="teal" :card="false">
                    <template #itemContent="{ item }">
                        <div class="pa-audit__event-detail">
                            <p><span>Sasaran</span><strong>{{ item.sasaran || 'Tingkat cycle' }}</strong></p>
                            <p v-if="item.rincian"><span>Rincian</span>{{ item.rincian }}</p>
                            <p v-if="item.sebab"><span>Sebab</span>{{ item.sebab }}</p>
                            <p v-if="item.participant_id"><span>ID peserta</span>#{{ item.participant_id }}</p>
                        </div>
                    </template>
                </HcisTimeline>
                <HcisResultState
                    v-if="pagination.dipangkas"
                    status="warning"
                    variant="flat"
                    layout="inline"
                    title="Daftar dipangkas server"
                    description="Jumlah baris per halaman sudah disesuaikan oleh batas server."
                    :show-retry="false"
                />
            </section>

            <section v-if="bounds?.konfigurasi_tanpa_nilai_lama" class="pa-audit__boundary" role="note">
                <HcisIcon name="info-circle-fill" size="sm" color="warning" />
                <div>
                    <strong>Batas riwayat konfigurasi</strong>
                    <p>{{ bounds.catatan }}</p>
                </div>
            </section>

            <HcisPagination
                v-if="pagination.total > 0"
                :total="pagination.total"
                :current-page="pagination.halaman"
                :page-size="pagination.per_halaman"
                :page-sizes="[10, 25, 50, 100]"
                :disabled="loading"
                @current-change="$emit('page-change', $event)"
                @size-change="$emit('size-change', $event)"
            />
        </div>

        <template #footer-start>
            <span class="pa-dialog-hint"><HcisIcon name="database-check" size="2xs" /> Audit berasal dari kejadian tersimpan server</span>
        </template>
        <template #footer>
            <HcisButton variant="primary" label="Tutup" size="sm" @click="$emit('update:modelValue', false)" />
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed } from 'vue';
import {
    HcisBadge,
    HcisButton,
    HcisCheckbox,
    HcisDialog,
    HcisField,
    HcisFilterBar,
    HcisIcon,
    HcisPagination,
    HcisResultState,
    HcisSelect,
    HcisSelectOption,
    HcisTimeline,
} from '@/components/ui';

defineOptions({ name: 'PaCycleAuditDialog' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    cycle: { type: Object, default: null },
    events: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({ keputusan_manusia: 0, oleh_sistem: 0 }) },
    bounds: { type: Object, default: null },
    filters: { type: Object, default: () => ({}) },
    pagination: { type: Object, default: () => ({ halaman: 1, per_halaman: 10, total: 0, total_halaman: 1, dipangkas: false }) },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    errorStatus: { type: Number, default: 0 },
    errorCode: { type: String, default: '' },
    requestId: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'filter', 'reset-filter', 'retry', 'page-change', 'size-change']);

const timelineItems = computed(() => props.events.map((event, index) => ({
    id: `${event.waktu || 'audit'}-${event.jenis || index}-${event.participant_id || 'cycle'}-${index}`,
    title: formatJenis(event.jenis),
    status: event.oleh_sistem ? 'Aturan sistem' : 'Keputusan manusia',
    name: event.oleh_sistem ? 'Oleh sistem' : event.aktor_nama || (event.aktor_id ? `Aktor #${event.aktor_id}` : 'Pelaku tidak diketahui'),
    role: event.participant_id ? `Peserta #${event.participant_id}` : 'Tingkat siklus',
    time: formatWaktu(event.waktu),
    icon: event.oleh_sistem ? 'gear-wide-connected' : 'person-check-fill',
    sentiment: event.oleh_sistem ? 'info' : 'teal',
    avatarText: event.oleh_sistem ? 'SYS' : 'USR',
    sasaran: event.sasaran,
    rincian: event.rincian,
    sebab: event.sebab,
    participant_id: event.participant_id,
})));

function formatAngka(value) {
    return Number(value || 0).toLocaleString('id-ID');
}

function formatJenis(value) {
    return String(value || 'Kejadian').replaceAll('_', ' ');
}

function formatWaktu(value) {
    return value ? String(value).replace(' ', ' · ') : 'Waktu tidak tersedia';
}

function saring(patch) {
    emit('filter', patch);
}

function ubahUkuran(value) {
    emit('size-change', Number(value));
}
</script>

<style scoped>
.pa-audit {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    min-width: 0;
}
.pa-audit__intro,
.pa-audit__filters,
.pa-audit__boundary {
    min-width: 0;
    border: 1px solid rgba(203, 213, 225, 0.75);
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.84), rgba(240, 253, 250, 0.62));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
}
.pa-audit__intro {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem;
}
.pa-audit__intro-main {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    min-width: 0;
}
.pa-audit__intro-icon {
    display: grid;
    flex: 0 0 auto;
    width: 2.7rem;
    height: 2.7rem;
    place-items: center;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(13, 148, 136, 0.13);
}
.pa-audit__intro h3 {
    margin: 0.2rem 0 0.18rem;
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.96rem;
}
.pa-audit__intro p {
    max-width: 48rem;
    margin: 0;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.74rem;
    line-height: 1.45;
}
.pa-audit__count-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(7rem, 1fr));
    gap: 0.55rem;
    flex: 0 0 auto;
}
.pa-audit__count-grid div {
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
    min-width: 0;
    padding: 0.65rem 0.7rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(255, 255, 255, 0.75);
}
.pa-audit__count-grid span {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.64rem;
}
.pa-audit__count-grid strong {
    color: #0f766e;
    font-size: 1.05rem;
    font-variant-numeric: tabular-nums;
}
.pa-audit__filters {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    padding: 0.85rem;
}
.pa-audit__filter-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(10rem, 0.8fr) minmax(9rem, 0.55fr);
    gap: 0.7rem;
    align-items: end;
}
.pa-audit__field {
    display: flex;
    flex-direction: column;
    gap: 0.32rem;
    min-width: 0;
}
.pa-audit__field label {
    color: var(--hcis-text-strong, #334155);
    font-size: 0.72rem;
    font-weight: 700;
}
.pa-audit__filter-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.pa-audit__loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    min-height: 14rem;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.8rem;
}
.pa-audit__timeline {
    min-width: 0;
    padding: 0.15rem 0.15rem 0.2rem;
}
.pa-audit__event-detail {
    display: flex;
    flex-direction: column;
    gap: 0.28rem;
}
.pa-audit__event-detail p {
    display: grid;
    grid-template-columns: 5rem minmax(0, 1fr);
    gap: 0.55rem;
    margin: 0;
    color: var(--hcis-text-muted, #475569);
    font-size: 0.75rem;
    line-height: 1.45;
    overflow-wrap: anywhere;
}
.pa-audit__event-detail p span {
    color: var(--hcis-text-muted, #94a3b8);
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.pa-audit__event-detail p strong {
    color: var(--hcis-text-strong, #334155);
    font-weight: 700;
}
.pa-audit__boundary {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.8rem 0.9rem;
    color: #92400e;
    border-color: rgba(245, 158, 11, 0.3);
    background: linear-gradient(135deg, rgba(255, 251, 235, 0.92), rgba(255, 255, 255, 0.78));
}
.pa-audit__boundary strong {
    display: block;
    margin-bottom: 0.15rem;
    font-size: 0.76rem;
}
.pa-audit__boundary p {
    margin: 0;
    font-size: 0.73rem;
    line-height: 1.5;
}
.pa-eyebrow {
    color: #0f766e;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.1em;
}
.pa-dialog-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.7rem;
}
@media (max-width: 820px) {
    .pa-audit__intro {
        align-items: flex-start;
        flex-direction: column;
    }
    .pa-audit__count-grid {
        width: 100%;
    }
    .pa-audit__filter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 640px) {
    .pa-audit {
        gap: 0.75rem;
    }
    .pa-audit__intro {
        padding: 0.75rem;
        gap: 0.65rem;
    }
    .pa-audit__intro-icon {
        width: 2.25rem;
        height: 2.25rem;
    }
    .pa-audit__intro h3 {
        font-size: 0.88rem;
    }
    .pa-audit__intro p {
        font-size: 0.7rem;
    }
    .pa-audit__count-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.45rem;
    }
    .pa-audit__count-grid div {
        padding: 0.45rem 0.6rem;
    }
    .pa-audit__count-grid strong {
        font-size: 0.95rem;
    }
    .pa-audit__filters {
        padding: 0.75rem;
        gap: 0.65rem;
    }
    .pa-audit__filter-grid {
        grid-template-columns: 1fr;
        gap: 0.55rem;
    }
    .pa-audit__event-detail p {
        grid-template-columns: 1fr;
        gap: 0.1rem;
    }
    .pa-dialog-hint {
        font-size: 0.65rem;
    }
}
</style>
