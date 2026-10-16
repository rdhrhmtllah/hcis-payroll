<template>
    <div class="rs-preview">
        <div v-if="!tanpaHeader" class="rs-preview-head">
            <div class="rs-preview-head-title">
                <div class="d-flex align-items-center gap-2">
                    <span class="sop-eyebrow mb-0">Hasil Langsung</span>
                    <Transition name="rs-badge-swap" mode="out-in">
                        <span v-if="status === 'memuat' || loading" key="updating" class="rs-updating-indicator">
                            <span class="rs-updating-radar" aria-hidden="true" />
                            <span class="rs-updating-label">Sinkronisasi Server…</span>
                        </span>
                        <span v-else-if="status === 'siap'" key="live" class="rs-live-indicator">
                            <span class="rs-live-dot" aria-hidden="true" /> Live
                        </span>
                    </Transition>
                </div>
                <h6 class="sop-h mb-0">Pratinjau Laporan</h6>
            </div>

            <div class="rs-preview-head-right">
                <div v-if="hasil" class="rs-count-wrap">
                    <span class="rs-preview-badge" :class="{ 'is-refreshing': status === 'memuat' || loading }">
                        <i class="bi bi-table me-1"></i>
                        <span>{{ formattedTotal }} data</span>
                        <span class="opacity-40 px-1">·</span>
                        <span>{{ hasil.columns?.length ?? 0 }} kolom</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- 1. INITIAL LOADING: Clean & Tidy Proportional Table Skeleton -->
        <div v-if="status === 'memuat' && !hasil" class="rs-loading-view">
            <div class="rs-loading-toolbar">
                <div class="d-flex align-items-center gap-2">
                    <span class="spinner-border spinner-border-sm text-brand-600" role="status" />
                    <span class="text-slate-600 small fw-semibold">Menyusun data pratinjau…</span>
                </div>
                <HcisSkeleton width="110px" height="1.35rem" rounded="pill" />
            </div>
            <div class="rs-loading-table-card">
                <div class="rs-loading-table-head">
                    <HcisSkeleton v-for="i in 5" :key="i" height="1rem" :width="`${12 + (i * 4)}%`" rounded="sm" />
                </div>
                <div class="rs-loading-table-body">
                    <div v-for="r in 7" :key="r" class="rs-loading-table-row">
                        <HcisSkeleton v-for="c in 5" :key="c" height="0.85rem" :width="`${18 + ((r + c) % 4) * 12}%`" rounded="sm" />
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. BELUM SIAP: Belum memilih dimensi / ukuran -->
        <div v-else-if="status === 'belum'" class="rs-state">
            <HcisEmptyState
                icon="sliders"
                variant="card"
                size="md"
                sentiment="neutral"
                title="Belum siap dipratinjau"
                :description="pesanBelum"
            />
        </div>

        <!-- 3. KOSONG: Filter menghasilkan 0 baris -->
        <div v-else-if="status === 'kosong'" class="rs-state">
            <HcisEmptyState
                icon="funnel"
                variant="card"
                size="md"
                sentiment="warning"
                title="Tidak ada baris data"
                description="Kriteria filter yang Anda pilih tidak menemukan data yang cocok. Coba sesuaikan atau longgarkan filter di panel sebelah kiri."
            />
        </div>

        <!-- 4. BERAT: Dataset terlalu besar untuk pratinjau seketika, tapi export tetap bisa -->
        <div v-else-if="status === 'berat'" class="rs-state">
            <HcisEmptyState
                icon="hourglass-split"
                variant="card"
                size="md"
                sentiment="warning"
                title="Dataset terlalu besar untuk pratinjau seketika"
                description="Definisi laporan ini tetap valid dan dapat langsung dijalankan sebagai export latar belakang. File Excel/CSV lengkap akan dibuat oleh worker server."
            />
        </div>

        <!-- 5. GALAT: Error saat eksekusi kueri -->
        <div v-else-if="status === 'galat'" class="rs-state">
            <HcisResultState
                status="error"
                variant="card"
                size="md"
                kicker="KENDALA SISTEM"
                title="Pratinjau Gagal Disusun"
                :description="pesanGalat"
                :show-aura="true"
                primary-text="Coba Muat Ulang"
                primary-icon="arrow-clockwise"
                primary-variant="primary"
                @primary-click="$emit('ulangi')"
            />
        </div>

        <div v-else-if="hasil" class="rs-tabel-section">
            <div class="rs-tabel-toolbar mb-2">
                <div class="rs-tabel-search">
                    <i class="bi bi-search rs-tabel-search-icon" />
                    <input
                        v-model="kataKunciTabel"
                        type="text"
                        placeholder="Cari data di server database…"
                        class="rs-tabel-search-input"
                    />
                    <button
                        v-if="kataKunciTabel"
                        type="button"
                        class="rs-tabel-search-clear"
                        title="Hapus pencarian"
                        @click="hapusPencarian"
                    >
                        <i class="bi bi-x-circle-fill" />
                    </button>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="rs-tabel-telemetry">
                        Menampilkan {{ hasil.rows?.length ?? 0 }} dari {{ formattedTotal }} baris data
                    </span>
                </div>
            </div>

            <div class="rs-tabel-bungkus">
                <HcisDataTable
                    variant="flat"
                    size="full"
                    table-size="sm"
                    :columns="kolomTabel"
                    :data="hasil.rows ?? []"
                    :total="totalData"
                    :page="page"
                    :page-size="pageSize"
                    :sort-key="sortKey"
                    :sort-order="sortOrder"
                    :loading="status === 'memuat' || loading"
                    :searchable="false"
                    :allow-add="false"
                    :allow-export="false"
                    :draggable-columns="true"
                    :empty-title="'Tidak ada baris data'"
                    :empty-description="'Kata kunci pencarian tidak menemukan baris data yang cocok.'"
                    @column-reorder="$emit('columnReorder', $event)"
                    @page-change="onPageChange"
                    @size-change="onSizeChange"
                    @sort="onTableSort"
                    @update:sort-key="$emit('update:sortKey', $event)"
                    @update:sort-order="$emit('update:sortOrder', $event)"
                />
            </div>
        </div>

        <!-- Catatan kaki TIDAK boleh disembunyikan: pemotongan dan kolom ter-mask
             yang tak terlihat adalah mode gagal terburuk, karena angkanya tetap
             terbaca masuk akal. -->
        <ul v-if="hasil?.catatan?.length" class="rs-catatan">
            <li v-for="(c, i) in hasil.catatan" :key="i">
                <i class="bi bi-info-circle-fill rs-catatan-icon"></i>
                <span>{{ c }}</span>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisDataTable from '@/components/ui/HcisDataTable.vue';
import HcisEmptyState from '@/components/ui/HcisEmptyState.vue';
import HcisResultState from '@/components/ui/HcisResultState.vue';
import HcisSkeleton from '@/components/ui/HcisSkeleton.vue';

const props = defineProps({
    status: { type: String, default: 'belum' }, // belum|memuat|siap|kosong|berat|galat
    hasil: { type: Object, default: null },
    pesanGalat: { type: String, default: 'Terjadi kesalahan saat menyusun pratinjau.' },
    galatDefinisi: { type: Object, default: () => ({}) },
    tanpaHeader: { type: Boolean, default: false },
    page: { type: Number, default: 1 },
    pageSize: { type: Number, default: 24 },
    search: { type: String, default: '' },
    sortKey: { type: String, default: '' },
    sortOrder: { type: String, default: 'asc' },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits([
    'ulangi',
    'columnReorder',
    'update:page',
    'update:pageSize',
    'update:search',
    'update:sortKey',
    'update:sortOrder',
    'sort',
    'cari',
    'muat-halaman',
]);

const kataKunciTabel = ref(props.search);
let timerPencarian = null;

watch(() => props.search, (val) => {
    if (val !== kataKunciTabel.value) {
        kataKunciTabel.value = val;
    }
});

// Debounce pencarian server-side: 350ms setelah berhenti mengetik
watch(kataKunciTabel, (baru) => {
    clearTimeout(timerPencarian);
    timerPencarian = setTimeout(() => {
        emit('update:search', baru);
        emit('update:page', 1);
        emit('cari', baru);
    }, 350);
});

function hapusPencarian() {
    clearTimeout(timerPencarian);
    kataKunciTabel.value = '';
    emit('update:search', '');
    emit('update:page', 1);
    emit('cari', '');
}

function onPageChange(p) {
    emit('update:page', p);
    emit('muat-halaman', { page: p, pageSize: props.pageSize });
}

function onSizeChange(s) {
    emit('update:pageSize', s);
    emit('update:page', 1);
    emit('muat-halaman', { page: 1, pageSize: s });
}

function onTableSort(sortData) {
    emit('update:sortKey', sortData.key);
    emit('update:sortOrder', sortData.order);
    emit('sort', sortData);
}

const totalData = computed(() => {
    if (typeof props.hasil?.total === 'number') return props.hasil.total;
    return props.hasil?.rows?.length ?? 0;
});

const formattedTotal = computed(() => {
    return totalData.value.toLocaleString('id-ID');
});

/* Compiler mengirim `center` untuk kolom angka; HcisTable membaca `align`.
 * Setiap kolom diaktifkan sortable: true agar dapat diurutkan (ASC/DESC). */
const kolomTabel = computed(() =>
    (props.hasil?.columns ?? []).map((c) => ({
        key: c.key,
        label: c.label,
        align: c.center ? 'center' : 'left',
        sortable: true,
    }))
);

const pesanBelum = computed(() => {
    const pesan = Object.values(props.galatDefinisi);
    return pesan.length ? pesan.join(' ') : 'Pilih dimensi dan ukuran di sebelah kiri untuk melihat hasilnya di sini.';
});
</script>

<style scoped>
.rs-preview {
    display: flex;
    flex-direction: column;
    min-height: 340px;
}

.rs-preview-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.75rem;
}

.rs-preview-head-title {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.rs-preview-head-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.rs-live-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.1rem 0.45rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #15803d;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.rs-live-dot {
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 9999px;
    background: #22c55e;
    box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.3);
    animation: hcis-pulse-dot 1.8s infinite;
}

/* ══ CYBER UPDATING RADAR PILL ══ */
.rs-updating-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.1rem 0.5rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1d4ed8;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.rs-updating-radar {
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 9999px;
    background: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.4);
    animation: hcis-radar-pulse 1.2s infinite;
}

@keyframes hcis-radar-pulse {
    0% { transform: scale(0.85); box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7); }
    70% { transform: scale(1.15); box-shadow: 0 0 0 5px rgba(59, 130, 246, 0); }
    100% { transform: scale(0.85); box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
}

@keyframes hcis-pulse-dot {
    0% { transform: scale(0.9); opacity: 0.8; }
    50% { transform: scale(1.2); opacity: 1; }
    100% { transform: scale(0.9); opacity: 0.8; }
}

.rs-badge-swap-enter-active,
.rs-badge-swap-leave-active {
    transition: all 180ms ease;
}
.rs-badge-swap-enter-from,
.rs-badge-swap-leave-to {
    opacity: 0;
    transform: scale(0.92);
}

.rs-preview-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.22rem 0.7rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: var(--hcis-surface-brand, #eef2ff);
    border: 1px solid var(--hcis-border-brand, #c7d2fe);
    color: var(--hcis-brand-700, #4338ca);
    font-size: 0.76rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    box-shadow: 0 1px 3px rgba(99, 102, 241, 0.12);
    transition: all 180ms ease;
}

.rs-preview-badge.is-refreshing {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1d4ed8;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
}

.rs-tabel-section {
    display: flex;
    flex-direction: column;
}

.rs-tabel-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.rs-tabel-search {
    position: relative;
    display: flex;
    align-items: center;
    max-width: 260px;
    width: 100%;
}

.rs-tabel-search-icon {
    position: absolute;
    left: 0.65rem;
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #94a3b8);
    pointer-events: none;
}

.rs-tabel-search-spinner {
    position: absolute;
    left: 0.65rem;
    width: 0.75rem;
    height: 0.75rem;
    border-width: 1.5px;
    color: #4f46e5;
    pointer-events: none;
}

.rs-tabel-search-input {
    width: 100%;
    height: 1.95rem;
    padding: 0.25rem 1.75rem 0.25rem 1.9rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    border: 1px solid var(--hcis-border-medium, #cbd5e1);
    background: var(--hcis-surface, #ffffff);
    font-size: 0.76rem;
    color: var(--hcis-text-strong, #1e293b);
    outline: none;
    transition: all 150ms ease;
}

.rs-tabel-search.is-searching .rs-tabel-search-input {
    border-color: #a5b4fc;
    background: #fafafa;
}

.rs-tabel-search-input:focus {
    border-color: var(--hcis-brand-600, #4f46e5);
    box-shadow: 0 0 0 2px var(--hcis-focus-ring, rgba(99, 102, 241, 0.15));
}

.rs-tabel-search-clear {
    position: absolute;
    right: 0.55rem;
    border: none;
    background: transparent;
    color: var(--hcis-text-muted, #94a3b8);
    cursor: pointer;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    padding: 0;
}

.rs-telemetry-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.15rem 0.5rem;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: var(--hcis-radius-pill, 9999px);
    font-size: 0.68rem;
    font-weight: 600;
    color: #1d4ed8;
}

.rs-pulse-dot {
    width: 0.35rem;
    height: 0.35rem;
    border-radius: 9999px;
    background: #3b82f6;
    animation: hcis-pulse-dot 1.2s infinite;
}

.rs-tabel-telemetry {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
    font-variant-numeric: tabular-nums;
    font-weight: 500;
}

.rs-state {
    padding: 1.5rem 0.5rem;
}

/* ══ CLEAN & TIDY SKELETON TABLE LOADING ══ */
.rs-loading-view {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.rs-loading-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.15rem 0.25rem;
}

.rs-loading-table-card {
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-xl, 14px);
    background: var(--hcis-surface, #ffffff);
    overflow: hidden;
    box-shadow: 0 4px 18px -2px rgba(15, 23, 42, 0.06), 0 1px 3px rgba(15, 23, 42, 0.03);
}

.rs-loading-table-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    padding: 0.85rem 1.25rem;
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.rs-loading-table-body {
    display: flex;
    flex-direction: column;
}

.rs-loading-table-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid rgba(241, 245, 249, 0.95);
}

.rs-loading-table-row:last-child {
    border-bottom: none;
}

.rs-loading-table-row:nth-child(even) {
    background: rgba(248, 250, 252, 0.6);
}

.rs-berat {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 2rem 1.5rem;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: var(--hcis-radius-xl, 14px);
    color: #92400e;
    box-shadow: 0 4px 14px rgba(245, 158, 11, 0.1);
}

.rs-berat-icon-box {
    width: 3rem;
    height: 3rem;
    border-radius: 9999px;
    background: #fef3c7;
    border: 1px solid #fde68a;
    color: #b45309;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    margin-bottom: 0.75rem;
}

.rs-berat-title {
    font-size: 0.95rem;
    font-weight: 700;
    margin: 0 0 0.35rem;
    color: #78350f;
}

.rs-berat-desc {
    margin: 0;
    font-size: 0.82rem;
    max-width: 420px;
    line-height: 1.5;
    color: #92400e;
}

/* Tabel lebar menggulir di dalam wadahnya sendiri; halaman tidak boleh
   menggulir mendatar. Sisanya — zebra, hover, kepala lengket, angka rata
   kanan — kini milik HcisDataTable, bukan ditulis ulang di sini. */
.rs-tabel-bungkus {
    width: 100%;
    overflow-x: auto;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-xl, 14px);
    background: var(--hcis-surface, #ffffff);
    box-shadow: 0 4px 18px -2px rgba(15, 23, 42, 0.06), 0 1px 3px rgba(15, 23, 42, 0.03);
}

.rs-tabel-bungkus :deep(.hcis-table) {
    width: 100%;
}

/* Compact, crisp BI/Data Grid Density */
.rs-tabel-bungkus :deep(.hcis-table th) {
    padding: 0.55rem 0.75rem;
    font-size: 0.7rem;
    letter-spacing: 0.04em;
}

.rs-tabel-bungkus :deep(.hcis-table td) {
    padding: 0.45rem 0.75rem;
    font-size: 0.785rem;
    line-height: 1.35;
}

.rs-tabel-bungkus :deep(.hcis-table__th-content) {
    gap: 0.35rem;
}

.rs-tabel-bungkus :deep(.hcis-data-table__pagination) {
    padding: 0.5rem 0.75rem;
}

.rs-catatan {
    list-style: none;
    margin: 0.75rem 0 0;
    padding: 0.65rem 0.85rem;
    background: var(--hcis-surface-soft, #f8fafc);
    border-left: 3px solid var(--hcis-brand-600, #4f46e5);
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.78rem;
    color: var(--hcis-text, #334155);
}

.rs-catatan li {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    line-height: 1.45;
}

.rs-catatan li + li {
    margin-top: 0.35rem;
}

.rs-catatan-icon {
    color: var(--hcis-brand-600, #4f46e5);
    margin-top: 0.1rem;
    flex-shrink: 0;
}
</style>
