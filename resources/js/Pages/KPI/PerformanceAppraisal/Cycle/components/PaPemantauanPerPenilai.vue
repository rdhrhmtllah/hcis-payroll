<template>
    <!--
     | PEMANTAUAN PER PENILAI — satu baris per orang yang menilai: berapa orang yang ia nilai,
     | berapa yang sudah selesai, dan status gabungannya. Yang paling tertinggal di atas.
     | Baris dibuka → orang-orang yang ia nilai beserta status tiap lembar dan tombol Akses ulang.
     -->
    <section class="pa-ppp" data-uji="pemantauan-per-penilai">
        <!-- Saringan SAMA dengan tampilan per karyawan dinilai (14 Okt 2026). -->
        <HcisFilterBar
            :search="q"
            search-placeholder="Cari nama atau NIK penilai..."
            :search-debounce="280"
            :quick-chips="chipStatus"
            :active-chip-value="status || 'ALL'"
            :filters="filters"
            :filter-values="saringan"
            variant="flat"
            sentiment="brand"
            size="sm"
            :results-count="memuat ? null : total"
            show-active-tags
            data-uji="ppp-saringan"
            @update:search="(v) => (q = v || '')"
            @chip-click="pilihChip"
            @filter-change="({ key, value }) => (saringan[key] = value)"
            @reset="aturUlang"
        />

        <!-- Loading State Skeleton -->
        <div v-if="memuat && !baris.length" class="pa-ppp__skeleton-wrap">
            <HcisSkeleton v-for="n in 5" :key="`skel-penilai-${n}`" height="3.5rem" rounded="md" />
        </div>

        <!-- Empty State -->
        <HcisEmptyState
            v-else-if="!baris.length"
            variant="card"
            sentiment="neutral"
            size="md"
            title="Tidak Ada Penilai yang Cocok"
            :description="q || status || adaSaringan ? 'Coba ubah kata kunci pencarian atau sesuaikan filter status di atas.' : 'Belum ada data penilai pada siklus ini.'"
            icon="search"
            class="my-2"
        >
            <template #actions>
                <HcisButton
                    v-if="q || status || adaSaringan"
                    label="Reset Filter"
                    variant="secondary"
                    size="sm"
                    leading-icon="arrow-counterclockwise"
                    @click="aturUlang"
                />
            </template>
        </HcisEmptyState>

        <!-- Modern Data Table -->
        <div v-else class="pa-ppp__tabel-wrap">
            <table class="pa-ppp__tabel">
                <thead>
                    <tr>
                        <th class="pa-ppp__th-chev" />
                        <th>Nama &amp; NIK Penilai</th>
                        <th class="pa-ppp__th-target">Menilai</th>
                        <th class="pa-ppp__th-progres">Kemajuan Lembar</th>
                        <th class="pa-ppp__th-status">Status Gabungan</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="p in baris" :key="kunci(p)">
                        <tr
                            class="pa-ppp__baris"
                            :class="{ 'is-open': terbuka[kunci(p)] }"
                            :data-uji="`ppp-baris-${p.kode_karyawan}`"
                            @click="buka(p)"
                        >
                            <td class="pa-ppp__td-chev">
                                <span class="pa-ppp__chev-btn" :class="{ 'is-open': terbuka[kunci(p)] }">
                                    <HcisIcon name="chevron-right" size="2xs" />
                                </span>
                            </td>
                            <td>
                                <div class="pa-ppp__identity">
                                    <strong class="pa-ppp__nama">{{ p.nama }}</strong>
                                    <span class="pa-ppp__nik">{{ p.kode_karyawan }}</span>
                                </div>
                            </td>
                            <td class="pa-ppp__td-target">
                                <span class="pa-ppp__target-badge">
                                    <HcisIcon name="people-fill" size="2xs" />
                                    {{ p.total }} orang
                                </span>
                            </td>
                            <td class="pa-ppp__td-progres">
                                <div class="pa-ppp__progres">
                                    <div class="pa-ppp__progres-bar-track" aria-hidden="true">
                                        <span
                                            class="pa-ppp__progres-bar-fill"
                                            :class="getBarColorClass(p)"
                                            :style="{ width: `${p.persen}%` }"
                                        />
                                    </div>
                                    <div class="pa-ppp__progres-meta">
                                        <strong class="pa-ppp__progres-val">{{ p.selesai }}/{{ p.total }} selesai</strong>
                                        <span class="pa-ppp__progres-pct">({{ p.persen }}%)</span>
                                    </div>
                                </div>
                            </td>
                            <td class="pa-ppp__td-status">
                                <div class="pa-ppp__status-box">
                                    <HcisBadge
                                        :label="LABEL[p.status] || p.status"
                                        :variant="getStatusBadgeVariant(p.status)"
                                        size="xs"
                                        dot
                                    />
                                    <span v-if="p.terkumpul && p.status !== 'SELESAI'" class="pa-ppp__sub-warning">
                                        <HcisIcon name="exclamation-circle-fill" size="2xs" />
                                        {{ p.terkumpul }} terisi belum dikirim
                                    </span>
                                </div>
                            </td>
                        </tr>

                        <!-- Accordion Detail Baris Orang yang Dinilai -->
                        <tr v-if="terbuka[kunci(p)]" class="pa-ppp__rinci-row">
                            <td class="pa-ppp__rinci-accent" />
                            <td colspan="4" class="pa-ppp__rinci-content">
                                <div v-if="rinci[kunci(p)] === undefined" class="pa-ppp__rinci-loading">
                                    <HcisIcon name="hourglass-split" size="xs" />
                                    <span>Memuat rincian penugasan {{ p.nama }}…</span>
                                </div>
                                <div v-else-if="!rinci[kunci(p)].length" class="pa-ppp__rinci-empty">
                                    Tidak ada rincian penugasan yang tercatat.
                                </div>
                                <div v-else class="pa-ppp__rinci-wrap">
                                    <div class="pa-ppp__rinci-head">
                                        <HcisIcon name="list-check" size="xs" color="brand" />
                                        <span>Daftar Karyawan yang Dinilai oleh <strong>{{ p.nama }}</strong> ({{ rinci[kunci(p)].length }} orang)</span>
                                    </div>
                                    <ul class="pa-ppp__daftar" :data-uji="`ppp-rinci-${p.kode_karyawan}`">
                                        <li v-for="r in rinci[kunci(p)]" :key="r.Id_PA_Assignment" class="pa-ppp__daftar-item">
                                            <div class="pa-ppp__orang">
                                                <div class="pa-ppp__orang-top">
                                                    <strong class="pa-ppp__orang-nama">{{ r.Nama_Karyawan_Yang_Dinilai }}</strong>
                                                    <span class="pa-ppp__orang-nik">{{ r.Kode_Karyawan }}</span>
                                                </div>
                                                <div class="pa-ppp__orang-role">
                                                    <HcisBadge
                                                        :label="`Sebagai ${r.Nama_Kategori || 'Penilai'}`"
                                                        variant="purple"
                                                        appearance="subtle"
                                                        size="2xs"
                                                    />
                                                </div>
                                            </div>

                                            <div class="pa-ppp__item-actions">
                                                <HcisBadge
                                                    :label="LABEL_LEMBAR[r.Status_Penugasan] || r.Status_Penugasan"
                                                    :variant="getLembarBadgeVariant(r.Status_Penugasan)"
                                                    size="xs"
                                                />
                                                <HcisButton
                                                    v-if="bolehAksesUlang"
                                                    label="Akses ulang"
                                                    variant="secondary"
                                                    size="xs"
                                                    leading-icon="arrow-repeat"
                                                    @click.stop="$emit('akses-ulang', r)"
                                                />
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Modern Pagination Bar -->
        <div v-if="totalHalaman > 1" class="pa-ppp__pagination-wrap">
            <HcisPagination
                :current-page="halaman"
                :page-size="25"
                :total="total"
                layout="total, prev, pager, next"
                size="sm"
                @update:current-page="halaman = $event"
            />
        </div>
    </section>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { HcisFilterBar } from '@/components/ui';
import { onMounted, ref, watch } from 'vue';
import {
    HcisBadge,
    HcisButton,
    HcisEmptyState,
    HcisIcon,
    HcisPagination,
    HcisSkeleton,
} from '@/components/ui';
import paApi from '../../api/paApi.js';

defineOptions({ name: 'PaPemantauanPerPenilai' });

const props = defineProps({
    cycleId: { type: [Number, String], required: true },
    bolehAksesUlang: { type: Boolean, default: false },
    /* Definisi saringan milik halaman induk (Kategori, Divisi, Level) — sama persis dengan
       tampilan per karyawan dinilai, jadi pilihannya tidak dirakit dua kali. */
    filters: { type: Array, default: () => [] },
});

defineEmits(['akses-ulang']);

const LABEL = {
    BELUM_MULAI: 'Belum mulai',
    SEDANG: 'Sedang mengisi',
    TERKUMPUL: 'Terisi, belum dikirim',
    SELESAI: 'Selesai semua',
};

const LABEL_LEMBAR = {
    BELUM_MULAI: 'Belum mengisi',
    BERJALAN: 'Sedang mengisi',
    TERKUMPUL: 'Terisi, belum dikirim',
    MENUNGGU_VERIFIKASI: 'Menunggu verifikasi',
    SELESAI: 'Selesai',
};

function getStatusBadgeVariant(st) {
    if (st === 'SELESAI') return 'success';
    if (st === 'SEDANG') return 'brand';
    if (st === 'TERKUMPUL') return 'warning';
    return 'danger';
}

function getLembarBadgeVariant(st) {
    if (st === 'SELESAI') return 'success';
    if (st === 'BERJALAN') return 'brand';
    if (st === 'TERKUMPUL' || st === 'MENUNGGU_VERIFIKASI') return 'warning';
    return 'neutral';
}

function getBarColorClass(p) {
    if (p.status === 'SELESAI') return 'is-success';
    if (p.status === 'SEDANG') return 'is-brand';
    if (p.status === 'TERKUMPUL') return 'is-warning';
    return 'is-danger';
}

const q = ref('');
const status = ref('');
const saringan = reactive({ kategori: '', id_divisi: '', id_level: '' });
const ringkasan = ref({});
const adaSaringan = computed(() => Boolean(saringan.kategori || saringan.id_divisi || saringan.id_level));

const chipStatus = computed(() => [
    { label: 'Semua Penilai', value: 'ALL', badge: ringkasan.value.semua ?? 0 },
    { label: 'Belum Mulai', value: 'BELUM_MULAI', badge: ringkasan.value.BELUM_MULAI ?? 0, color: 'warning' },
    { label: 'Sedang Mengisi', value: 'SEDANG', badge: ringkasan.value.SEDANG ?? 0, color: 'purple' },
    { label: 'Terisi, Belum Dikirim', value: 'TERKUMPUL', badge: ringkasan.value.TERKUMPUL ?? 0 },
    { label: 'Selesai Semua', value: 'SELESAI', badge: ringkasan.value.SELESAI ?? 0, color: 'success' },
]);

function pilihChip(chip) {
    const v = typeof chip === 'object' && chip !== null ? (chip.value ?? chip.id) : chip;
    status.value = !v || v === 'ALL' ? '' : v;
}

function aturUlang() {
    q.value = '';
    status.value = '';
    Object.assign(saringan, { kategori: '', id_divisi: '', id_level: '' });
}
const halaman = ref(1);
const baris = ref([]);
const total = ref(0);
const totalHalaman = ref(1);
const memuat = ref(false);
const terbuka = ref({});
const rinci = ref({});

const kunci = (p) => `${p.kode_perusahaan}|${p.kode_karyawan}`;
const angka = (v) => Number(v || 0).toLocaleString('id-ID');

let penunda = null;

async function muat() {
    memuat.value = true;
    try {
        const r = await paApi.monitoringPenilai(props.cycleId, {
            q: q.value.trim(),
            status: status.value,
            pa_rater_category_id: saringan.kategori || '',
            id_divisi: saringan.id_divisi || '',
            id_level: saringan.id_level || '',
            halaman: halaman.value,
            per_halaman: 25,
        });
        baris.value = r?.baris ?? [];
        total.value = Number(r?.total ?? 0);
        totalHalaman.value = Math.max(1, Number(r?.total_halaman ?? 1));
        ringkasan.value = r?.ringkasan ?? {};
    } catch {
        baris.value = [];
    } finally {
        memuat.value = false;
    }
}

async function buka(p) {
    const k = kunci(p);
    terbuka.value = { ...terbuka.value, [k]: !terbuka.value[k] };
    if (!terbuka.value[k] || rinci.value[k] !== undefined) return;
    try {
        const r = await paApi.rincianPenilaiMonitoring(props.cycleId, p.kode_perusahaan, p.kode_karyawan);
        rinci.value = { ...rinci.value, [k]: r?.baris ?? [] };
    } catch {
        rinci.value = { ...rinci.value, [k]: [] };
    }
}

watch([q, status], () => {
    clearTimeout(penunda);
    penunda = setTimeout(() => {
        halaman.value = 1;
        muat();
    }, 350);
});
watch(halaman, muat);
watch(() => ({ ...saringan }), () => {
    halaman.value = 1;
    muat();
});
watch(() => props.cycleId, () => {
    terbuka.value = {};
    rinci.value = {};
    halaman.value = 1;
    muat();
});

onMounted(muat);

/* Sesudah Akses ulang di halaman induk, rincian dibaca ulang. */
function segarkan() {
    rinci.value = {};
    terbuka.value = {};
    muat();
}

defineExpose({ segarkan });
</script>

<style scoped>
.pa-ppp {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    width: 100%;
}

/* Toolbar & Filters */
.pa-ppp__toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.65rem;
    padding: 0.85rem 1rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.pa-ppp__search-box {
    flex: 1 1 16rem;
    min-width: 14rem;
}

.pa-ppp__filter-pills {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-ppp__pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.65rem;
    border-radius: 999px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
    font-size: 0.74rem;
    font-weight: 650;
    cursor: pointer;
    transition: all 150ms ease;
}

.pa-ppp__pill:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #0f172a;
}

.pa-ppp__pill.is-active {
    background: #eef2ff;
    border-color: #818cf8;
    color: #4338ca;
    box-shadow: 0 1px 2px rgba(99, 102, 241, 0.1);
}

.pa-ppp__pill-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
}

.pa-ppp__pill-dot.is-danger { background: #ef4444; }
.pa-ppp__pill-dot.is-brand { background: #6366f1; }
.pa-ppp__pill-dot.is-warning { background: #f59e0b; }
.pa-ppp__pill-dot.is-success { background: #10b981; }

.pa-ppp__select-hidden {
    display: none;
}

.pa-ppp__counter {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: auto;
}

.pa-ppp__counter-hint {
    font-size: 0.72rem;
    color: #64748b;
}

.pa-ppp__skeleton-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

/* Modern Data Table */
.pa-ppp__tabel-wrap {
    overflow-x: auto;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.pa-ppp__tabel {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}

.pa-ppp__tabel th {
    padding: 0.75rem 1rem;
    text-align: left;
    color: #475569;
    font-size: 0.72rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}

.pa-ppp__th-chev { width: 2.5rem; padding-right: 0 !important; }
.pa-ppp__th-target { width: 9rem; }
.pa-ppp__th-progres { width: 16rem; }
.pa-ppp__th-status { width: 13rem; }

.pa-ppp__tabel td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.pa-ppp__baris {
    cursor: pointer;
    transition: background-color 150ms ease;
}

.pa-ppp__baris:hover {
    background-color: #f8fafc;
}

.pa-ppp__baris.is-open {
    background-color: #f8faff;
}

.pa-ppp__td-chev {
    width: 2.5rem;
    padding-right: 0 !important;
}

.pa-ppp__chev-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 4px;
    background: #f1f5f9;
    color: #64748b;
    transition: transform 200ms ease, background-color 200ms ease;
}

.pa-ppp__chev-btn.is-open {
    transform: rotate(90deg);
    background: #e0e7ff;
    color: #4338ca;
}

.pa-ppp__identity {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-ppp__nama {
    color: #0f172a;
    font-size: 0.86rem;
    font-weight: 750;
}

.pa-ppp__nik {
    font-size: 0.72rem;
    color: #64748b;
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 600;
}

.pa-ppp__target-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    background: #f1f5f9;
    color: #334155;
    font-size: 0.75rem;
    font-weight: 700;
    white-space: nowrap;
}

.pa-ppp__progres {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 12rem;
}

.pa-ppp__progres-bar-track {
    width: 100%;
    height: 6px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}

.pa-ppp__progres-bar-fill {
    display: block;
    height: 100%;
    border-radius: 999px;
    transition: width 300ms ease;
}

.pa-ppp__progres-bar-fill.is-success { background: linear-gradient(90deg, #10b981, #059669); }
.pa-ppp__progres-bar-fill.is-brand { background: linear-gradient(90deg, #6366f1, #4f46e5); }
.pa-ppp__progres-bar-fill.is-warning { background: linear-gradient(90deg, #f59e0b, #d97706); }
.pa-ppp__progres-bar-fill.is-danger { background: linear-gradient(90deg, #f87171, #ef4444); }

.pa-ppp__progres-meta {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
    font-size: 0.73rem;
}

.pa-ppp__progres-val {
    color: #0f172a;
}

.pa-ppp__progres-pct {
    color: #64748b;
    font-weight: 600;
}

.pa-ppp__status-box {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-ppp__sub-warning {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.7rem;
    color: #b45309;
    font-weight: 600;
}

/* Detail Card Accordion */
.pa-ppp__rinci-row td {
    padding: 0;
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
}

.pa-ppp__rinci-accent {
    background: #6366f1 !important;
    width: 4px !important;
    padding: 0 !important;
}

.pa-ppp__rinci-content {
    padding: 1rem 1.25rem !important;
}

.pa-ppp__rinci-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.85rem 1rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.pa-ppp__rinci-head {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.78rem;
    color: #475569;
}

.pa-ppp__rinci-loading,
.pa-ppp__rinci-empty {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
    font-size: 0.78rem;
    color: #64748b;
}

.pa-ppp__daftar {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.pa-ppp__daftar-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.55rem 0.75rem;
    border-radius: 6px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    transition: background-color 150ms ease;
}

.pa-ppp__daftar-item:hover {
    background: #f1f5f9;
}

.pa-ppp__orang {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    flex: 1;
    min-width: 14rem;
}

.pa-ppp__orang-top {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-ppp__orang-nama {
    font-size: 0.82rem;
    color: #0f172a;
    font-weight: 700;
}

.pa-ppp__orang-nik {
    font-size: 0.7rem;
    color: #64748b;
    font-family: var(--hcis-font-mono, monospace);
}

.pa-ppp__item-actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.pa-ppp__pagination-wrap {
    display: flex;
    justify-content: flex-end;
    margin-top: 0.25rem;
}

@media (max-width: 768px) {
    .pa-ppp__counter {
        margin-left: 0;
        width: 100%;
        justify-content: space-between;
    }
    .pa-ppp__filter-pills {
        width: 100%;
    }
}
</style>
