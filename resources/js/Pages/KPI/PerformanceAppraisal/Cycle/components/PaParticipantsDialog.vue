<template>
    <HcisDialog
        :model-value="modelValue"
        :title="cycle ? `Daftar Peserta — ${cycle.Nama_Cycle || cycle.Kode_Cycle}` : 'Daftar Peserta Cycle'"
        kicker="PERFORMANCE APPRAISAL · POPULASI & STATUS PENILAIAN"
        description="Pantau populasi karyawan terdaftar, kesiapan penilai per peserta, kelengkapan evaluasi, serta status kalkulasi skor akhir."
        icon="people-fill"
        sentiment="brand"
        variant="glass"
        size="full"
        sticky-footer
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <div class="pa-participants">
            <!-- Executive Summary Cockpit Bar -->
            <div v-if="summary" class="pa-participants__stats-grid" aria-label="Ringkasan status peserta">
                <div class="pa-stat-pill pa-stat-pill--total">
                    <div class="pa-stat-pill__icon">
                        <HcisIcon name="people-fill" size="xs" />
                    </div>
                    <div class="pa-stat-pill__info">
                        <span class="pa-stat-pill__label">Total Dijadwalkan</span>
                        <strong class="pa-stat-pill__value font-num">{{ formatAngka(totalPopulasi) }}</strong>
                    </div>
                </div>

                <div
                    class="pa-stat-pill pa-stat-pill--success"
                    :class="{ 'is-active': filters.status_peserta === 'DIIKUTKAN' }"
                    role="button"
                    tabindex="0"
                    title="Klik untuk filter peserta yang Diikutkan"
                    @click="ubahStatusPeserta({ value: 'DIIKUTKAN' })"
                    @keydown.enter="ubahStatusPeserta({ value: 'DIIKUTKAN' })"
                >
                    <div class="pa-stat-pill__icon">
                        <HcisIcon name="check-circle-fill" size="xs" />
                    </div>
                    <div class="pa-stat-pill__info">
                        <span class="pa-stat-pill__label">Diikutkan</span>
                        <strong class="pa-stat-pill__value font-num">{{ formatAngka(summary.DIIKUTKAN) }}</strong>
                    </div>
                    <span v-if="filters.status_peserta === 'DIIKUTKAN'" class="pa-stat-pill__active-dot"></span>
                </div>

                <div
                    class="pa-stat-pill pa-stat-pill--warning"
                    :class="{ 'is-active': filters.status_peserta === 'TERTUNDA' }"
                    role="button"
                    tabindex="0"
                    title="Klik untuk filter peserta yang Tertunda"
                    @click="ubahStatusPeserta({ value: filters.status_peserta === 'TERTUNDA' ? 'DIIKUTKAN' : 'TERTUNDA' })"
                    @keydown.enter="ubahStatusPeserta({ value: filters.status_peserta === 'TERTUNDA' ? 'DIIKUTKAN' : 'TERTUNDA' })"
                >
                    <div class="pa-stat-pill__icon">
                        <HcisIcon name="hourglass-split" size="xs" />
                    </div>
                    <div class="pa-stat-pill__info">
                        <span class="pa-stat-pill__label">Tertunda</span>
                        <strong class="pa-stat-pill__value font-num">{{ formatAngka(summary.TERTUNDA) }}</strong>
                    </div>
                    <span v-if="filters.status_peserta === 'TERTUNDA'" class="pa-stat-pill__active-dot"></span>
                </div>

                <span v-if="truncated" class="pa-participants__truncated">
                    <HcisIcon name="info-circle" size="2xs" /> Jumlah per halaman dipangkas server.
                </span>
            </div>

            <!-- Enhanced Filter Bar -->
            <HcisFilterBar
                :search="filters.q"
                search-placeholder="Cari nama karyawan atau kode NIK..."
                :search-debounce="260"
                :quick-chips="statusPesertaChips"
                :active-chip-value="filters.status_peserta || null"
                :filters="filterDefinitions"
                :filter-values="filterValues"
                variant="card"
                sentiment="brand"
                size="sm"
                glow
                :results-count="loading ? null : total"
                show-active-tags
                class="pa-participants__filter-bar"
                @update:search="ubahPencarian"
                @chip-click="ubahStatusPeserta"
                @filter-change="ubahFilter"
                @reset="resetFilters"
            />

            <!-- Error State -->
            <template v-if="error">
                <HcisResultState
                    :status="errorStatus === 403 ? 'warning' : 'danger'"
                    variant="flat"
                    layout="inline"
                    :title="errorStatus === 403 ? 'Akses peserta tidak tersedia' : 'Peserta gagal dimuat'"
                    :description="error"
                    primary-text="Coba lagi"
                    primary-icon="arrow-clockwise"
                    :show-retry="errorStatus !== 403"
                    @primary-click="muatHalaman(halaman)"
                />
                <div v-if="errorCode || requestId" class="pa-participants__error-meta" role="note">
                    <span v-if="errorCode">Kode: {{ errorCode }}</span>
                    <span v-if="requestId">Request ID: {{ requestId }}</span>
                </div>
            </template>

            <!-- Loading State -->
            <div v-else-if="loading && !items.length" class="pa-participants__loading" aria-live="polite">
                <div class="pa-loading-spinner-wrap">
                    <HcisIcon name="arrow-repeat" size="lg" animation="spin" color="brand" />
                    <span>Memuat daftar peserta siklus...</span>
                </div>
            </div>

            <!-- Empty State -->
            <HcisResultState
                v-else-if="!displayedItems.length"
                status="info"
                variant="flat"
                title="Tidak ada peserta yang cocok"
                description="Coba ubah kata kunci pencarian atau reset penyaring filter untuk melihat data peserta lain."
                :show-retry="false"
            />

            <!-- Participant Table -->
            <div v-else class="pa-table-wrapper">
                <HcisTable
                    :columns="participantColumns"
                    :rows="displayedItems"
                    row-key="Id_PA_Participant"
                    :loading="loading"
                    :loading-rows-count="6"
                    responsive="scroll"
                    variant="card"
                    sentiment="brand"
                    hoverable
                    sticky-header
                    class="pa-participants__table"
                >
                    <!-- Peserta Column -->
                    <template #cell-peserta="{ row }">
                        <div class="pa-person-cell">
                            <div class="pa-person-avatar">
                                <span>{{ getInitials(row.Nama_Karyawan_Saat_Cycle) }}</span>
                            </div>
                            <div class="pa-person-info">
                                <span class="pa-person-name">{{ row.Nama_Karyawan_Saat_Cycle || '—' }}</span>
                                <div class="pa-person-tags">
                                    <span class="pa-nik-chip">{{ row.Kode_Karyawan || '—' }}</span>
                                    <span v-if="row.Nama_Level_Saat_Cycle || row.ID_Level_Saat_Cycle" class="pa-level-chip">
                                        {{ labelLevelPa(row) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Organisasi & Jabatan Column -->
                    <template #cell-organisasi="{ row }">
                        <div class="pa-org-cell">
                            <span class="pa-org-title">{{ row.Nama_Jabatan_Saat_Cycle || '—' }}</span>
                            <div class="pa-org-dept">
                                <span class="pa-org-div">{{ row.Nama_Divisi_Saat_Cycle || 'Divisi —' }}</span>
                                <span v-if="row.Nama_Sub_Divisi_Saat_Cycle" class="pa-org-subdiv"> &middot; {{ row.Nama_Sub_Divisi_Saat_Cycle }}</span>
                            </div>
                            <span v-if="row.Lokasi_Saat_Cycle" class="pa-org-loc">
                                <HcisIcon name="geo-alt-fill" size="2xs" /> {{ row.Lokasi_Saat_Cycle }}
                            </span>
                        </div>
                    </template>

                    <!-- Status Kepesertaan Column -->
                    <template #cell-status="{ row }">
                        <div class="pa-status-cell">
                            <HcisBadge
                                :label="getStatusPesertaLabel(row.Status_Peserta)"
                                :variant="getStatusPesertaVariant(row.Status_Peserta)"
                                size="xs"
                                dot
                            />
                            <span v-if="row.Alasan_Dikecualikan" class="pa-excluded-hint" :title="row.Alasan_Dikecualikan">
                                <HcisIcon name="info-circle" size="2xs" />
                                <span>{{ row.Alasan_Dikecualikan }}</span>
                            </span>
                        </div>
                    </template>

                    <!-- Kesiapan Penilai Column -->
                    <template #cell-penilai="{ row }">
                        <div class="pa-rater-cell">
                            <div class="pa-rater-pill-wrap">
                                <span
                                    class="pa-rater-pill"
                                    :class="{
                                        'is-complete': (row.penilai_terpasang > 0 && (row.penilai_selesai ?? 0) >= row.penilai_terpasang),
                                        'is-partial': (row.penilai_terpasang > 0 && (row.penilai_selesai ?? 0) < row.penilai_terpasang && (row.penilai_selesai ?? 0) > 0),
                                        'is-zero': (row.penilai_terpasang > 0 && !(row.penilai_selesai ?? 0)),
                                        'is-unassigned': (!row.penilai_terpasang || row.penilai_terpasang === 0)
                                    }"
                                >
                                    <HcisIcon :name="getRaterIcon(row)" size="2xs" />
                                    <span class="pa-rater-count font-num">
                                        {{ row.penilai_selesai ?? 0 }}/{{ row.penilai_terpasang ?? 0 }} selesai
                                    </span>
                                </span>
                            </div>
                            <div v-if="row.penilai_terpasang > 0" class="pa-rater-progress-track">
                                <div
                                    class="pa-rater-progress-fill"
                                    :style="{
                                        width: `${Math.min(100, Math.round(((row.penilai_selesai ?? 0) / (row.penilai_terpasang || 1)) * 100))}%`
                                    }"
                                    :class="{
                                        'is-complete': (row.penilai_selesai ?? 0) >= row.penilai_terpasang,
                                        'is-ongoing': (row.penilai_selesai ?? 0) < row.penilai_terpasang
                                    }"
                                />
                            </div>
                        </div>
                    </template>

                    <!-- Hasil & Grade Column -->
                    <template #cell-hasil="{ row }">
                        <div class="pa-result-cell">
                            <div v-if="row.Final_Score !== null && row.Final_Score !== undefined" class="pa-score-block">
                                <div class="pa-score-row">
                                    <strong class="pa-score-value font-num">{{ formatScore(row.Final_Score) }}%</strong>
                                    <span
                                        v-if="row.Kode_Grade_Final || row.Kode_Grade_Sistem"
                                        class="pa-grade-badge"
                                        :style="{ backgroundColor: getGradeColor(row.Kode_Grade_Final || row.Kode_Grade_Sistem) }"
                                    >
                                        {{ displayGrade(row.Kode_Grade_Final || row.Kode_Grade_Sistem) }}
                                    </span>
                                    <span v-if="row.Flag_Dikalibrasi === 'Y'" class="pa-calibrated-chip" title="Hasil telah dikalibrasi">
                                        <HcisIcon name="sliders" size="2xs" />
                                    </span>
                                </div>
                                <span v-if="row.Nilai_KPI !== null || row.Nilai_Kompetensi !== null" class="pa-score-breakdown font-num">
                                    KPI: {{ formatScore(row.Nilai_KPI) }} &middot; Komp: {{ formatScore(row.Nilai_Kompetensi) }}
                                </span>
                            </div>
                            <div v-else-if="row.Status_Hasil === 'TERTAHAN'" class="pa-held-status">
                                <HcisBadge label="Tertahan" variant="warning" size="xs" dot />
                            </div>
                            <span v-else class="pa-no-score-hint">Belum ada angka</span>

                            <span v-if="row.Alasan_Tertahan" class="pa-held-reason" role="note" :title="row.Alasan_Tertahan">
                                <HcisIcon name="exclamation-triangle-fill" size="2xs" />
                                <span>{{ row.Alasan_Tertahan }}</span>
                            </span>
                        </div>
                    </template>

                    <!-- Aksi Column -->
                    <template #cell-aksi="{ row }">
                        <div class="pa-actions-cell">
                            <HcisButton
                                v-if="canAssign"
                                label="Kelola penilai"
                                variant="primary"
                                size="xs"
                                leading-icon="people"
                                @click="$emit('assign', row)"
                            />
                            <HcisButton
                                label="Lihat hasil"
                                variant="secondary"
                                size="xs"
                                leading-icon="bar-chart-line"
                                @click="$emit('detail', row)"
                            />
                        </div>
                    </template>
                </HcisTable>
            </div>
        </div>

        <!-- Footer Info & Pagination -->
        <template #footer-start>
            <div class="pa-dialog-footer-left">
                <span class="pa-dialog-hint">
                    <HcisIcon name="database-check" size="2xs" />
                    {{ total ? `${formatAngka(total)} peserta total` : 'Belum ada total' }}
                </span>
                <span v-if="totalHalaman > 1" class="pa-pagination-range font-num">
                    &middot; Halaman {{ halaman }} dari {{ totalHalaman }}
                </span>
            </div>
        </template>
        <template #footer>
            <div class="pa-pagination">
                <HcisButton
                    label="Sebelumnya"
                    variant="secondary"
                    size="sm"
                    leading-icon="chevron-left"
                    :disabled="halaman <= 1 || loading"
                    @click="muatHalaman(halaman - 1)"
                />
                <span class="pa-pagination__text font-num">Halaman {{ halaman }} dari {{ totalHalaman || 1 }}</span>
                <HcisButton
                    label="Berikutnya"
                    variant="secondary"
                    size="sm"
                    trailing-icon="chevron-right"
                    :disabled="halaman >= totalHalaman || loading"
                    @click="muatHalaman(halaman + 1)"
                />
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { labelLevelPa } from '../../utils/levelPa.js';
import { computed, reactive, watch } from 'vue';
import {
    HcisBadge,
    HcisButton,
    HcisDialog,
    HcisFilterBar,
    HcisIcon,
    HcisResultState,
    HcisTable,
} from '@/components/ui';

defineOptions({ name: 'PaParticipantsDialog' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    cycle: { type: Object, default: null },
    items: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    errorStatus: { type: Number, default: 0 },
    errorCode: { type: String, default: '' },
    requestId: { type: String, default: '' },
    halaman: { type: Number, default: 1 },
    total: { type: Number, default: 0 },
    totalHalaman: { type: Number, default: 0 },
    summary: { type: Object, default: null },
    truncated: { type: Boolean, default: false },
    canAssign: { type: Boolean, default: false },
    opsi: {
        type: Object,
        default: () => ({
            divisi: [],
            level: [],
            sub_divisi: [],
            jabatan: [],
            lokasi: [],
        }),
    },
});

const emit = defineEmits(['update:modelValue', 'load', 'detail', 'assign']);

const filters = reactive({
    q: '',
    status_hasil: '',
    status_peserta: 'DIIKUTKAN',
    id_divisi: '',
    id_sub_divisi: '',
    id_level: '',
    kode_jabatan: '',
    lokasi: '',
    per_halaman: 25,
});

const totalPopulasi = computed(() => {
    if (props.summary) {
        return (
            Number(props.summary.DIIKUTKAN || 0) +
            Number(props.summary.TERTUNDA || 0)
        );
    }
    return Number(props.total || 0);
});

const statusPesertaChips = computed(() => {
    const s = props.summary;
    return [
        { value: 'DIIKUTKAN', label: 'Diikutkan', icon: 'check-circle', color: 'success', badge: s ? formatAngka(s.DIIKUTKAN) : undefined },
        { value: 'TERTUNDA', label: 'Tertunda', icon: 'hourglass-split', color: 'warning', badge: s ? formatAngka(s.TERTUNDA) : undefined },
    ];
});

const displayedItems = computed(() => {
    return (props.items || []).filter((r) => r.Status_Peserta !== 'DIKECUALIKAN');
});

function unikOpsiAngka(items, kolomId, kolomNama) {
    const map = new Map();
    (items || []).forEach((row) => {
        const id = row[kolomId];
        const nama = row[kolomNama];
        if (id !== null && id !== undefined && id !== '' && !map.has(Number(id))) {
            map.set(Number(id), { value: Number(id), label: String(nama || `#${id}`) });
        }
    });
    return Array.from(map.values()).sort((a, b) => a.label.localeCompare(b.label));
}

function unikOpsiTeks(items, kolomKode, kolomNama) {
    const map = new Map();
    (items || []).forEach((row) => {
        const kode = row[kolomKode];
        const nama = row[kolomNama];
        if (kode && !map.has(String(kode))) {
            map.set(String(kode), { value: String(kode), label: String(nama || kode) });
        }
    });
    return Array.from(map.values()).sort((a, b) => a.label.localeCompare(b.label));
}

function unikOpsiLokasi(items) {
    const set = new Set();
    (items || []).forEach((row) => {
        const loc = row.Lokasi_Saat_Cycle;
        if (loc) set.add(String(loc));
    });
    return Array.from(set).sort().map((l) => ({ value: l, label: l }));
}

const opsiTersedia = computed(() => {
    const s = props.opsi || {};
    return {
        divisi: s.divisi?.length ? s.divisi : unikOpsiAngka(props.items, 'ID_Divisi_Saat_Cycle', 'Nama_Divisi_Saat_Cycle'),
        level: s.level?.length ? s.level : unikOpsiAngka(props.items, 'ID_Level_Saat_Cycle', 'Nama_Level_Saat_Cycle'),
        sub_divisi: s.sub_divisi?.length ? s.sub_divisi : unikOpsiAngka(props.items, 'ID_Sub_Divisi_Saat_Cycle', 'Nama_Sub_Divisi_Saat_Cycle'),
        jabatan: s.jabatan?.length ? s.jabatan : unikOpsiTeks(props.items, 'Kode_Jabatan_Saat_Cycle', 'Nama_Jabatan_Saat_Cycle'),
        lokasi: s.lokasi?.length ? s.lokasi : unikOpsiLokasi(props.items),
    };
});

const filterDefinitions = computed(() => [
    {
        key: 'id_divisi',
        label: 'Divisi',
        type: 'select',
        icon: 'building',
        placeholder: 'Semua divisi',
        clearable: true,
        filterable: true,
        options: opsiTersedia.value.divisi,
        col: 4,
    },
    {
        key: 'id_sub_divisi',
        label: 'Departemen',
        type: 'select',
        icon: 'diagram-3',
        placeholder: 'Semua departemen',
        clearable: true,
        filterable: true,
        options: opsiTersedia.value.sub_divisi,
        col: 4,
    },
    {
        key: 'id_level',
        label: 'Level',
        type: 'select',
        icon: 'layers',
        placeholder: 'Semua level',
        clearable: true,
        filterable: true,
        options: opsiTersedia.value.level,
        col: 4,
    },
    {
        key: 'kode_jabatan',
        label: 'Jabatan',
        type: 'select',
        icon: 'person-badge',
        placeholder: 'Semua jabatan',
        clearable: true,
        filterable: true,
        options: opsiTersedia.value.jabatan,
        col: 4,
    },
    {
        key: 'lokasi',
        label: 'Lokasi Kerja',
        type: 'select',
        icon: 'geo-alt',
        placeholder: 'Semua lokasi',
        clearable: true,
        filterable: true,
        options: opsiTersedia.value.lokasi,
        col: 4,
    },
    {
        key: 'status_hasil',
        label: 'Status hasil',
        type: 'select',
        icon: 'bar-chart-line',
        placeholder: 'Semua status hasil',
        clearable: true,
        filterable: true,
        options: [
            { value: 'DIHITUNG', label: 'Terkalkulasi / Dihitung' },
            { value: 'TERTAHAN', label: 'Hasil Tertahan' },
        ],
        col: 4,
    },
]);

const filterValues = computed(() => ({
    status_hasil: filters.status_hasil,
    id_divisi: filters.id_divisi,
    id_sub_divisi: filters.id_sub_divisi,
    id_level: filters.id_level,
    kode_jabatan: filters.kode_jabatan,
    lokasi: filters.lokasi,
}));

const participantColumns = [
    { key: 'peserta', label: 'Peserta', width: '230px' },
    { key: 'organisasi', label: 'Snapshot Organisasi', width: '200px' },
    { key: 'status', label: 'Status', width: '130px' },
    { key: 'penilai', label: 'Kesiapan Penilai', width: '150px' },
    { key: 'hasil', label: 'Hasil Nilai & Grade', width: '170px' },
    { key: 'aksi', label: 'Aksi', align: 'right', width: '200px' },
];

function muatHalaman(page) {
    const payload = { ...filters, halaman: page };
    if (!props.opsi?.divisi?.length) {
        payload.dengan_opsi = 1;
    }
    emit('load', payload);
}

function ubahPencarian(value) {
    filters.q = value;
    muatHalaman(1);
}

function ubahStatusPeserta(chip) {
    filters.status_peserta = chip?.value || 'DIIKUTKAN';
    muatHalaman(1);
}

function ubahFilter({ key, value }) {
    filters[key] = value ?? '';
    muatHalaman(1);
}

function resetFilters() {
    filters.q = '';
    filters.status_hasil = '';
    filters.status_peserta = 'DIIKUTKAN';
    filters.id_divisi = '';
    filters.id_sub_divisi = '';
    filters.id_level = '';
    filters.kode_jabatan = '';
    filters.lokasi = '';
    muatHalaman(1);
}

function formatAngka(value) {
    return Number(value || 0).toLocaleString('id-ID');
}

function formatScore(value) {
    if (value === null || value === undefined || isNaN(Number(value))) return '—';
    return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 1, minimumFractionDigits: 1 });
}

function getInitials(name) {
    if (!name) return '??';
    return name
        .split(' ')
        .filter(Boolean)
        .map((p) => p[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function getStatusPesertaLabel(status) {
    const map = {
        'DIIKUTKAN': 'Diikutkan',
        'TERTUNDA': 'Tertunda',
        'DIKECUALIKAN': 'Dikecualikan',
    };
    return map[status] || status || '—';
}

function getStatusPesertaVariant(status) {
    if (status === 'DIIKUTKAN') return 'success';
    if (status === 'TERTUNDA') return 'warning';
    if (status === 'DIKECUALIKAN') return 'neutral';
    return 'brand';
}

function getRaterIcon(row) {
    const terpasang = Number(row?.penilai_terpasang || 0);
    const selesai = Number(row?.penilai_selesai || 0);
    if (!terpasang) return 'person-dash';
    if (selesai >= terpasang) return 'check-circle-fill';
    if (selesai > 0) return 'hourglass-split';
    return 'clock';
}

function displayGrade(kode) {
    if (!kode || kode === '—' || kode === '(Tanpa grade)') return '—';
    return String(kode)
        .replace(/_PLUS/gi, '+')
        .replace(/_MINUS/gi, '-')
        .replace(/\b([A-E])\s*PLUS\b/gi, '$1+')
        .replace(/\b([A-E])\s*MINUS\b/gi, '$1-')
        .trim();
}

function getGradeColor(kode) {
    const k = displayGrade(kode).toUpperCase();
    if (k === 'A' || k === 'A+') return '#059669';
    if (k === 'B+' || k.includes('PLUS') || k === 'B PLUS') return '#4f46e5';
    if (k === 'B') return '#0284c7';
    if (k === 'C+' || k === 'C PLUS') return '#d97706';
    if (k === 'C') return '#ea580c';
    if (k === 'D' || k === 'E') return '#dc2626';
    return '#64748b';
}

watch(
    () => props.cycle?.Id_PA_Cycle,
    () => {
        filters.q = '';
        filters.status_hasil = '';
        filters.status_peserta = 'DIIKUTKAN';
        filters.id_divisi = '';
        filters.id_level = '';
    },
);
</script>

<style scoped>
.pa-participants {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    min-width: 0;
    width: 100%;
}

/* ------------------------------------------------------------------ */
/* Executive Summary Cockpit Bar */
/* ------------------------------------------------------------------ */
.pa-participants__stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem;
    align-items: center;
}

.pa-stat-pill {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.6rem 0.85rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    transition: all 160ms cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    user-select: none;
}

.pa-stat-pill[role='button'] {
    cursor: pointer;
}

.pa-stat-pill[role='button']:hover {
    background: #f8fafc;
    border-color: rgba(199, 210, 254, 0.9);
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(15, 23, 42, 0.06);
}

.pa-stat-pill__icon {
    display: grid;
    place-items: center;
    width: 2rem;
    height: 2rem;
    border-radius: var(--hcis-radius-full, 9999px);
    flex-shrink: 0;
}

.pa-stat-pill--total .pa-stat-pill__icon {
    background: rgba(99, 102, 241, 0.12);
    color: #4f46e5;
}

.pa-stat-pill--success .pa-stat-pill__icon {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.pa-stat-pill--warning .pa-stat-pill__icon {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
}

.pa-stat-pill--neutral .pa-stat-pill__icon {
    background: rgba(148, 163, 184, 0.15);
    color: #64748b;
}

.pa-stat-pill__info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pa-stat-pill__label {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.2;
}

.pa-stat-pill__value {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.2;
}

.pa-stat-pill.is-active {
    background: #ffffff;
    border-color: #6366f1;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2), 0 3px 8px rgba(99, 102, 241, 0.08);
}

.pa-stat-pill__active-dot {
    position: absolute;
    top: 0.45rem;
    right: 0.55rem;
    width: 6px;
    height: 6px;
    border-radius: 9999px;
    background: #4f46e5;
}

.pa-participants__truncated {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    color: #b45309;
    padding: 0.35rem 0.65rem;
    background: #fffbeb;
    border-radius: var(--hcis-radius-sm, 6px);
    border: 1px solid #fde68a;
}

/* ------------------------------------------------------------------ */
/* Filter Bar & Table Structure */
/* ------------------------------------------------------------------ */
.pa-participants__filter-bar {
    margin: 0;
}

.pa-table-wrapper {
    width: 100%;
    min-width: 0;
    background: #ffffff;
    border-radius: var(--hcis-radius-lg, 12px);
    border: 1px solid rgba(226, 232, 240, 0.85);
    overflow: hidden;
}

.pa-table-wrapper :deep(.hcis-table th) {
    background: #f8fafc !important;
    background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%) !important;
}

.pa-participants__loading {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 16rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-loading-spinner-wrap {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 1rem 1.5rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.8);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.pa-participants__error-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.8rem;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.68rem;
}

/* ------------------------------------------------------------------ */
/* Table Cells */
/* ------------------------------------------------------------------ */
.pa-person-cell {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}

.pa-person-avatar {
    width: 2rem;
    height: 2rem;
    display: grid;
    place-items: center;
    border-radius: var(--hcis-radius-full, 9999px);
    background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
    color: #3730a3;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    flex-shrink: 0;
}

.pa-person-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pa-person-name {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-person-tags {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: wrap;
}

.pa-nik-chip {
    display: inline-flex;
    padding: 0.05rem 0.3rem;
    border-radius: 4px;
    background: rgba(15, 23, 42, 0.06);
    color: #334155;
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.65rem;
    font-weight: 700;
}

.pa-level-chip {
    display: inline-flex;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    background: rgba(99, 102, 241, 0.08);
    color: #4338ca;
    font-size: 0.65rem;
    font-weight: 700;
}

.pa-org-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pa-org-title {
    font-size: 0.8rem;
    font-weight: 650;
    color: var(--hcis-text-strong, #1e293b);
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-org-dept {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-org-div {
    font-weight: 600;
    color: #475569;
}

.pa-org-subdiv {
    color: var(--hcis-text-muted, #64748b);
}

.pa-org-loc {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.65rem;
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-status-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    align-items: flex-start;
}

.pa-excluded-hint {
    display: flex;
    align-items: flex-start;
    gap: 0.25rem;
    font-size: 0.65rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.2;
    max-width: 14rem;
}

/* ------------------------------------------------------------------ */
/* Rater Cell Progress */
/* ------------------------------------------------------------------ */
.pa-rater-cell {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    min-width: 0;
}

.pa-rater-pill-wrap {
    display: flex;
    align-items: center;
}

.pa-rater-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.15rem 0.5rem;
    border-radius: var(--hcis-radius-full, 9999px);
    font-size: 0.68rem;
    font-weight: 700;
    border: 1px solid transparent;
}

.pa-rater-pill.is-complete {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
    border-color: rgba(16, 185, 129, 0.25);
}

.pa-rater-pill.is-partial {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
    border-color: rgba(245, 158, 11, 0.25);
}

.pa-rater-pill.is-zero {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
    border-color: rgba(239, 68, 68, 0.2);
}

.pa-rater-pill.is-unassigned {
    background: rgba(241, 245, 249, 0.9);
    color: #64748b;
    border-color: rgba(203, 213, 225, 0.8);
}

.pa-rater-count {
    display: inline-block;
}

.pa-rater-progress-track {
    width: 100%;
    max-width: 6.5rem;
    height: 0.3rem;
    border-radius: 9999px;
    background: rgba(226, 232, 240, 0.85);
    overflow: hidden;
}

.pa-rater-progress-fill {
    height: 100%;
    border-radius: 9999px;
    transition: width 200ms ease;
}

.pa-rater-progress-fill.is-complete {
    background: #10b981;
}

.pa-rater-progress-fill.is-ongoing {
    background: #f59e0b;
}

/* ------------------------------------------------------------------ */
/* Result & Score Cell */
/* ------------------------------------------------------------------ */
.pa-result-cell {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.pa-score-block {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.pa-score-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.pa-score-value {
    font-size: 0.875rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-grade-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.5rem;
    padding: 0.05rem 0.35rem;
    border-radius: var(--hcis-radius-xs, 4px);
    color: #ffffff;
    font-size: 0.65rem;
    font-weight: 800;
    line-height: 1.2;
}

.pa-calibrated-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.15rem;
    border-radius: 4px;
    background: #f3e8ff;
    color: #7c3aed;
    font-size: 0.65rem;
}

.pa-score-breakdown {
    font-size: 0.65rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-held-reason {
    display: flex;
    align-items: flex-start;
    gap: 0.3rem;
    max-width: 16rem;
    font-size: 0.65rem;
    color: #b45309 !important;
    line-height: 1.3;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
    background: #fffbeb;
    border: 1px solid #fef3c7;
    overflow-wrap: anywhere;
}

.pa-held-reason > span {
    min-width: 0;
}

.pa-no-score-hint {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #94a3b8);
    font-style: italic;
}

/* ------------------------------------------------------------------ */
/* Action Buttons */
/* ------------------------------------------------------------------ */
.pa-actions-cell {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.4rem;
    flex-wrap: nowrap;
}

/* ------------------------------------------------------------------ */
/* Footer & Pagination */
/* ------------------------------------------------------------------ */
.pa-dialog-footer-left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-dialog-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.74rem;
    font-weight: 650;
}

.pa-pagination-range {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-pagination {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.76rem;
}

.pa-pagination__text {
    font-weight: 600;
}

.font-num {
    font-variant-numeric: tabular-nums;
}

@media (max-width: 760px) {
    .pa-participants {
        gap: 0.75rem;
    }

    .pa-participants__stats-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.45rem;
    }

    .pa-stat-pill {
        padding: 0.45rem 0.45rem;
        gap: 0.35rem;
    }

    .pa-stat-pill__icon {
        width: 1.55rem;
        height: 1.55rem;
    }

    .pa-stat-pill__label {
        font-size: 0.56rem;
    }

    .pa-stat-pill__value {
        font-size: 0.95rem;
    }

    .pa-participants__truncated {
        grid-column: 1 / -1;
        font-size: 0.65rem;
        padding: 0.25rem 0.5rem;
    }

    .pa-dialog-footer-left {
        flex-wrap: wrap;
        gap: 0.25rem;
    }

    .pa-dialog-hint {
        font-size: 0.68rem;
    }

    .pa-pagination {
        width: 100%;
        justify-content: space-between;
        gap: 0.4rem;
    }

    .pa-pagination span {
        order: 2;
        font-size: 0.7rem;
    }

    .pa-pagination :deep(.hcis-btn:first-child) {
        order: 1;
    }
}

@media (max-width: 480px) {
    .pa-stat-pill {
        flex-direction: column;
        align-items: flex-start;
        padding: 0.4rem;
        gap: 0.25rem;
    }

    .pa-stat-pill__icon {
        width: 1.35rem;
        height: 1.35rem;
    }
}
</style>
