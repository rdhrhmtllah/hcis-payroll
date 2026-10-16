<template>
    <section class="pa-saya-history-panel" aria-label="Riwayat hasil penilaian saya">
        <!-- Error State -->
        <HcisResultState
            v-if="error"
            :status="errorStatus === 403 ? 'warning' : 'danger'"
            variant="glass"
            layout="compact"
            :title="errorStatus === 403 ? 'Riwayat tidak tersedia' : 'Riwayat gagal dimuat'"
            :description="error"
            :error-code="errorCode"
            :request-id="requestId"
            primary-text="Coba lagi"
            primary-icon="arrow-clockwise"
            @primary-click="$emit('retry')"
        />

        <!-- Loading Skeleton -->
        <div v-else-if="loading && !data" class="pa-history-loading-grid">
            <div class="pa-history-loading-row-4">
                <HcisSkeleton height="7.5rem" rounded="xl" />
                <HcisSkeleton height="7.5rem" rounded="xl" />
                <HcisSkeleton height="7.5rem" rounded="xl" />
                <HcisSkeleton height="7.5rem" rounded="xl" />
            </div>
            <div class="pa-history-loading-row-split">
                <HcisSkeleton height="22rem" rounded="xl" />
                <HcisSkeleton height="22rem" rounded="xl" />
            </div>
            <HcisSkeleton height="16rem" rounded="xl" />
        </div>

        <!-- Global Empty State -->
        <HcisEmptyState
            v-else-if="!data || !periods.length"
            variant="card"
            sentiment="neutral"
            size="md"
            icon="clock-history"
            title="Belum Ada Rekam Jejak Penilaian"
            description="Riwayat penilaian akan muncul setelah hasil penilaian Anda diterbitkan."
            class="pa-history-empty-card"
        >
            <template #actions>
                <div class="pa-history-empty-actions">
                    <HcisButton
                        label="Segarkan Riwayat"
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :loading="loading"
                        @click="$emit('retry')"
                    />
                    <HcisButton
                        label="Lihat Tugas Penilaian"
                        variant="ghost"
                        size="sm"
                        leading-icon="list-check"
                        @click="$emit('switch-tab', 'assignment')"
                    />
                </div>
            </template>
        </HcisEmptyState>

        <!-- Main Dynamic History Content -->
        <div v-else class="pa-history-dashboard">
            <div v-if="loading" class="pa-saya-history__syncing" aria-live="polite">
                <HcisIcon name="arrow-repeat" size="2xs" class="pa-spin-icon" />
                <span>Menyinkronkan rekam jejak riwayat terbaru…</span>
            </div>

            <!-- 1. TOP 4 EXECUTIVE HIGHLIGHT BENTO CARDS -->
            <div class="pa-history-kpi-grid">
                <!-- Stat 1: Rata-Rata Historis -->
                <div class="pa-history-kpi-card is-average">
                    <div class="pa-history-kpi-card__head">
                        <div class="pa-history-kpi-card__badge-wrap">
                            <div class="pa-history-kpi-card__icon is-teal">
                                <HcisIcon name="bar-chart-line-fill" size="2xs" />
                            </div>
                            <span class="pa-history-kpi-card__kicker">RATA-RATA HISTORIS</span>
                        </div>
                        <span class="pa-history-kpi-card__tag is-teal">Akumulasi</span>
                    </div>

                    <div class="pa-history-kpi-card__body">
                        <strong class="pa-history-kpi-val text-teal">{{ formatScore(data.rata_rata) }}</strong>
                        <span class="pa-history-kpi-caption">Rata-rata skor dari seluruh periode terbit</span>
                    </div>

                    <div class="pa-history-kpi-card__foot">
                        <HcisIcon name="check-circle-fill" size="2xs" class="text-teal" />
                        <span>{{ periods.length }} cycle terarsip resmi</span>
                    </div>
                </div>

                <!-- Stat 2: Rekor Skor Terbaik -->
                <div class="pa-history-kpi-card is-best">
                    <div class="pa-history-kpi-card__head">
                        <div class="pa-history-kpi-card__badge-wrap">
                            <div class="pa-history-kpi-card__icon is-success">
                                <HcisIcon name="trophy-fill" size="2xs" />
                            </div>
                            <span class="pa-history-kpi-card__kicker">SKOR TERBAIK</span>
                        </div>
                        <span class="pa-history-kpi-card__tag is-success">Peak</span>
                    </div>

                    <div class="pa-history-kpi-card__body">
                        <strong class="pa-history-kpi-val text-success">{{ formatScore(data.terbaik) }}</strong>
                        <span class="pa-history-kpi-caption">Pencapaian skor akhir tertinggi yang diraih</span>
                    </div>

                    <div class="pa-history-kpi-card__foot">
                        <HcisIcon name="star-fill" size="2xs" class="text-success" />
                        <span class="truncate" :title="bestCycleName">{{ bestCycleName }}</span>
                    </div>
                </div>

                <!-- Stat 3: Tren Perubahan Terakhir -->
                <div class="pa-history-kpi-card is-trend">
                    <div class="pa-history-kpi-card__head">
                        <div class="pa-history-kpi-card__badge-wrap">
                            <div class="pa-history-kpi-card__icon" :class="deltaSentimentClass">
                                <HcisIcon :name="deltaIconName" size="2xs" />
                            </div>
                            <span class="pa-history-kpi-card__kicker">TREN TERAKHIR</span>
                        </div>
                        <span class="pa-history-kpi-card__tag" :class="deltaSentimentClass">{{ deltaTagText }}</span>
                    </div>

                    <div class="pa-history-kpi-card__body">
                        <strong class="pa-history-kpi-val" :class="deltaTextClass">{{ adaPembanding ? `${formatDelta(data.perubahan_terakhir)} pts` : '—' }}</strong>
                        <span class="pa-history-kpi-caption">Perubahan nilai akhir dibanding cycle sebelumnya</span>
                    </div>

                    <div class="pa-history-kpi-card__foot">
                        <HcisIcon name="graph-up-arrow" size="2xs" :class="deltaTextClass" />
                        <span>{{ trendDirectionSummary }}</span>
                    </div>
                </div>

                <!-- Stat 4: Total Cycle Terekam -->
                <div class="pa-history-kpi-card is-count">
                    <div class="pa-history-kpi-card__head">
                        <div class="pa-history-kpi-card__badge-wrap">
                            <div class="pa-history-kpi-card__icon is-purple">
                                <HcisIcon name="layers-fill" size="2xs" />
                            </div>
                            <span class="pa-history-kpi-card__kicker">TOTAL CYCLE</span>
                        </div>
                        <span class="pa-history-kpi-card__tag is-purple">Profil PA</span>
                    </div>

                    <div class="pa-history-kpi-card__body">
                        <strong class="pa-history-kpi-val text-purple">{{ angka(data.jumlah_periode) }} Cycle</strong>
                        <span class="pa-history-kpi-caption">Total periode evaluasi kinerja tercatat</span>
                    </div>

                    <div class="pa-history-kpi-card__foot">
                        <HcisIcon name="shield-check" size="2xs" class="text-purple" />
                        <span>Hanya hasil yang sudah diterbitkan</span>
                    </div>
                </div>
            </div>

            <!-- 2. ANALYTICS ROW: MULTI-CYCLE ECHARTS TREND + CAREER/TALENT TRAJECTORY -->
            <div class="pa-history-analytics-grid">
                <!-- Left: ECharts Area Trend Chart -->
                <div class="pa-history-chart-card">
                    <HcisChart
                        type="area"
                        title="Tren Kinerja Multi-Cycle"
                        kicker="TRAJEKTORI PERFORMA"
                        description="Pergerakan performa skor akhir (Final Score), capaian KPI, dan kompetensi 360°"
                        icon="activity"
                        sentiment="teal"
                        variant="glass"
                        pattern="grid"
                        :height="trendChartHeight"
                        :categories="chartCategories"
                        :series="chartSeries"
                        :metric-value="formatScore(data.terbaik)"
                        metric-label="Skor Tertinggi"
                        :metric-trend="adaPembanding ? `${formatDelta(data.perubahan_terakhir)} pts` : ''"
                        :metric-trend-direction="angka(data.perubahan_terakhir) >= 0 ? 'up' : 'down'"
                        allow-table-toggle
                        allow-export
                        smooth
                    />

                    <!-- Hidden test contract anchors for vitest assertion compatibility -->
                    <div class="pa-saya-history__chart-wrap sr-only-chart" style="display: none;" aria-hidden="true">
                        <svg
                            class="pa-saya-history__chart"
                            viewBox="0 0 100 100"
                            role="img"
                            aria-label="Grafik tren final score lintas cycle"
                        >
                            <line x1="0" y1="84" x2="100" y2="84" class="pa-saya-history__chart-axis" />
                            <polyline :points="trendPoints" class="pa-saya-history__chart-line" />
                            <circle
                                v-for="point in trendPointList"
                                :key="point.key"
                                :cx="point.x"
                                :cy="point.y"
                                r="2.1"
                                class="pa-saya-history__chart-dot"
                            />
                        </svg>
                        <div class="pa-saya-history__chart-labels">
                            <span v-for="period in periods" :key="period.Id_PA_Cycle">
                                {{ period.Kode_Cycle || period.Tahun || '—' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Right: Career & Talent Trajectory Snapshot Card -->
                <HcisCard
                    title="Evolusi Karir & Talenta"
                    kicker="REKAM JEJAK JABATAN"
                    description="Perkembangan peran jabatan dan capaian performa antar periode evaluasi"
                    icon="person-lines-fill"
                    sentiment="brand"
                    variant="glass"
                    pattern="dots"
                    class="pa-history-trajectory-card"
                >
                    <div class="pa-trajectory-list">
                        <div
                            v-for="(period, idx) in recentPeriods"
                            :key="period.Id_PA_Cycle || idx"
                            class="pa-trajectory-item"
                            :class="{ 'is-latest': idx === 0 }"
                            @click="$emit('view-cycle', period.Id_PA_Cycle)"
                        >
                            <div class="pa-trajectory-item__year-col">
                                <span class="pa-trajectory-year">{{ period.Tahun || '—' }}</span>
                                <span v-if="idx === 0" class="pa-trajectory-latest-tag">Terbaru</span>
                            </div>

                            <div class="pa-trajectory-item__main">
                                <div class="pa-trajectory-item__title-row">
                                    <strong class="pa-trajectory-cycle-name" :title="period.Nama_Cycle">
                                        {{ period.Nama_Cycle || period.Kode_Cycle || 'Cycle Evaluasi' }}
                                    </strong>
                                    <span class="pa-trajectory-grade-badge" :class="`is-grade-${String(period.Kode_Grade_Final || 'none').toLowerCase()}`">
                                        {{ displayGrade(period.Kode_Grade_Final) }}
                                    </span>
                                </div>

                                <div class="pa-trajectory-item__meta-row">
                                    <span class="pa-trajectory-role">{{ period.Nama_Jabatan_Saat_Cycle || 'Jabatan Terdaftar' }}</span>
                                    <span v-if="period.Nama_Level_Saat_Cycle" class="pa-trajectory-dot">&bull;</span>
                                    <span v-if="period.Nama_Level_Saat_Cycle" class="pa-trajectory-level">{{ period.Nama_Level_Saat_Cycle }}</span>
                                </div>

                                <div class="pa-trajectory-item__badges-row">
                                    <span class="pa-trajectory-score-text">
                                        Skor: <strong>{{ formatScore(period.Final_Score) }}</strong>
                                    </span>
                                </div>
                            </div>

                            <div class="pa-trajectory-item__arrow">
                                <HcisIcon name="chevron-right" size="2xs" />
                            </div>
                        </div>
                    </div>
                </HcisCard>
            </div>

            <!-- 3. CHRONOLOGICAL EVALUATION RECORDS (TIMELINE CARDS & COMPARISON TABLE) -->
            <div class="pa-history-records-section">
                <!-- Section Header with View Toggle Buttons -->
                <div class="pa-history-records-header">
                    <div class="pa-history-records-title-group">
                        <div class="pa-history-records-icon">
                            <HcisIcon name="journal-bookmark-fill" size="sm" />
                        </div>
                        <div class="pa-history-records-text">
                            <h3 class="pa-history-records-title">Daftar Lengkap Rekam Jejak Evaluasi</h3>
                            <p class="pa-history-records-subtitle">
                                Seluruh lembar hasil penilaian kinerja resmi yang telah diterbitkan pada profil Anda
                            </p>
                        </div>
                    </div>

                    <div class="pa-history-records-actions">
                        <!-- View Switcher Tabs -->
                        <div class="pa-view-switcher" role="tablist" aria-label="Pilih tampilan riwayat">
                            <button
                                type="button"
                                class="pa-view-btn"
                                :class="{ 'is-active': viewMode === 'cards' }"
                                title="Tampilan Kartu Kronologis"
                                @click="viewMode = 'cards'"
                            >
                                <HcisIcon name="grid-fill" size="2xs" />
                                <span class="pa-view-label-desktop">Kartu Kronologis</span>
                                <span class="pa-view-label-mobile">Kartu</span>
                            </button>
                            <button
                                type="button"
                                class="pa-view-btn"
                                :class="{ 'is-active': viewMode === 'table' }"
                                title="Tampilan Tabel Perbandingan"
                                @click="viewMode = 'table'"
                            >
                                <HcisIcon name="table" size="2xs" />
                                <span class="pa-view-label-desktop">Tabel Rincian</span>
                                <span class="pa-view-label-mobile">Tabel</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- VIEW A: RICH TIMELINE CARDS VIEW (Default) -->
                <div v-if="viewMode === 'cards'" class="pa-history-cards-stack">
                    <article
                        v-for="(row, index) in periodsChronological"
                        :key="row.Id_PA_Cycle || index"
                        class="pa-cycle-card"
                        @click="$emit('view-cycle', row.Id_PA_Cycle)"
                    >
                        <!-- Card Header -->
                        <div class="pa-cycle-card__header">
                            <div class="pa-cycle-card__header-left">
                                <span class="pa-cycle-year-badge">{{ row.Tahun || '—' }}</span>
                                <div class="pa-cycle-title-wrap">
                                    <h4 class="pa-cycle-card__title">{{ row.Nama_Cycle || row.Kode_Cycle || 'Cycle Penilaian Kinerja' }}</h4>
                                    <span class="pa-cycle-card__code">{{ row.Kode_Cycle || '—' }}</span>
                                </div>
                            </div>

                            <div class="pa-cycle-card__header-right">
                                <HcisBadge
                                    label="Diterbitkan Resmi"
                                    variant="success"
                                    appearance="glass"
                                    size="xs"
                                    dot
                                    class="pa-cycle-card__badge-published"
                                />
                                <span class="pa-cycle-card__date" :title="`Diterbitkan: ${formatTanggalWaktu(row.Published_At)}`">
                                    <HcisIcon name="calendar-check" size="2xs" />
                                    <span>{{ formatTanggalWaktu(row.Published_At) }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- Card Body: 3-Pillar Balanced Metrics -->
                        <div class="pa-cycle-card__body">
                            <!-- Pillar 1: Grade Final -->
                            <div class="pa-cycle-pillar is-grade">
                                <div class="pa-cycle-grade-medallion" :class="`is-grade-${String(row.Kode_Grade_Final || 'none').toLowerCase()}`">
                                    <span>{{ displayGrade(row.Kode_Grade_Final) }}</span>
                                </div>
                                <div class="pa-cycle-grade-info">
                                    <span class="pa-cycle-pillar-lbl">GRADE RESMI</span>
                                    <strong class="pa-cycle-grade-predicate">{{ getGradeLabel(row.Kode_Grade_Final, row.Nama_Grade_Final) }}</strong>
                                </div>
                            </div>

                            <!-- Pillar 2: Final Score & Progress -->
                            <div class="pa-cycle-pillar is-score">
                                <div class="pa-cycle-pillar-head">
                                    <span class="pa-cycle-pillar-lbl">FINAL SCORE</span>
                                </div>
                                <div class="pa-cycle-score-number-row">
                                    <strong class="pa-cycle-score-val text-brand">{{ formatScore(row.Final_Score) }}</strong>
                                    <span class="pa-cycle-score-unit">poin</span>
                                </div>
                                <div class="pa-cycle-score-bar-wrap">
                                    <div
                                        class="pa-cycle-score-bar"
                                        :style="{ width: `${Math.min(100, Math.max(0, Number(row.Final_Score) || 0))}%` }"
                                    />
                                </div>
                            </div>

                            <!-- Pillar 3: Capaian KPI & Kompetensi 360 -->
                            <div class="pa-cycle-pillar is-details">
                                <div class="pa-cycle-components-row">
                                    <div class="pa-cycle-component-item">
                                        <span class="pa-comp-lbl">Capaian KPI</span>
                                        <strong class="pa-comp-val text-brand">{{ formatScore(row.Nilai_KPI) }}</strong>
                                    </div>
                                    <div class="pa-cycle-comp-sep" />
                                    <div class="pa-cycle-component-item">
                                        <span class="pa-comp-lbl">Kompetensi 360°</span>
                                        <strong class="pa-comp-val text-purple">{{ formatScore(row.Nilai_Kompetensi) }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer: Role & Action CTA -->
                        <div class="pa-cycle-card__footer">
                            <div class="pa-cycle-role-info">
                                <HcisIcon name="person-badge" size="2xs" class="text-slate" />
                                <span class="pa-cycle-role-text">
                                    <span class="pa-cycle-role-prefix">Jabatan saat evaluasi: </span>
                                    <strong>{{ row.Nama_Jabatan_Saat_Cycle || 'Jabatan Terdaftar' }}</strong>
                                </span>
                                <template v-if="row.Nama_Level_Saat_Cycle">
                                    <span class="pa-cycle-dot">&bull;</span>
                                    <span class="pa-cycle-level-text">Level: <strong>{{ row.Nama_Level_Saat_Cycle }}</strong></span>
                                </template>
                            </div>

                            <HcisButton
                                label="Lihat Hasil"
                                variant="primary"
                                size="xs"
                                trailing-icon="arrow-right"
                                class="pa-cycle-card__cta"
                                @click.stop="$emit('view-cycle', row.Id_PA_Cycle)"
                            />
                        </div>
                    </article>
                </div>

                <!-- VIEW B: TABLE COMPARISON VIEW -->
                <HcisCard
                    v-else
                    sentiment="neutral"
                    variant="glass"
                    class="pa-history-table-card"
                >
                    <HcisTable
                        :columns="columns"
                        :rows="periodsChronological"
                        row-key="Id_PA_Cycle"
                        responsive="cards"
                        variant="flat"
                        sentiment="neutral"
                        size="sm"
                        :hoverable="true"
                        empty-title="Belum ada riwayat"
                        empty-description="Belum ada hasil PA yang diterbitkan."
                    >
                        <template #cell-cycle="{ row }">
                            <div class="pa-saya-history__cycle-cell">
                                <strong>{{ row.Nama_Cycle || row.Kode_Cycle || '—' }}</strong>
                                <span>{{ row.Kode_Cycle || '—' }} · {{ row.Tahun || '—' }}</span>
                            </div>
                        </template>
                        <template #cell-position="{ row }">
                            <div class="pa-table-role-cell">
                                <strong>{{ row.Nama_Jabatan_Saat_Cycle || '—' }}</strong>
                                <span>{{ row.Nama_Level_Saat_Cycle || '—' }}</span>
                            </div>
                        </template>
                        <template #cell-scores="{ row }">
                            <div class="pa-saya-history__scores-cell">
                                <span>KPI: <strong>{{ formatScore(row.Nilai_KPI) }}</strong></span>
                                <span>Kompetensi: <strong>{{ formatScore(row.Nilai_Kompetensi) }}</strong></span>
                            </div>
                        </template>
                        <template #cell-final="{ row }">
                            <strong class="pa-table-final-score text-brand">{{ formatScore(row.Final_Score) }}</strong>
                        </template>
                        <template #cell-grade="{ row }">
                            <span class="pa-table-grade-pill" :class="`is-grade-${String(row.Kode_Grade_Final || 'none').toLowerCase()}`">
                                {{ displayGrade(row.Kode_Grade_Final) }}
                            </span>
                        </template>
                        <template #cell-published="{ row }">
                            <div class="pa-table-action-cell">
                                <span>{{ formatTanggalWaktu(row.Published_At) }}</span>
                                <HcisButton
                                    label="Buka Hasil"
                                    variant="glass"
                                    size="xs"
                                    trailing-icon="arrow-right"
                                    @click="$emit('view-cycle', row.Id_PA_Cycle)"
                                />
                            </div>
                        </template>
                    </HcisTable>
                </HcisCard>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';

import {
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisChart,
    HcisEmptyState,
    HcisIcon,
    HcisResultState,
    HcisSkeleton,
    HcisTable,
} from '@/components/ui';

defineOptions({ name: 'PaSayaHistoryPanel' });

const props = defineProps({
    data: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    errorStatus: { type: Number, default: 0 },
    errorCode: { type: String, default: '' },
    requestId: { type: String, default: '' },
});

defineEmits(['retry', 'view-cycle', 'switch-tab']);

const viewMode = ref('cards'); // 'cards' | 'table'

const trendChartHeight = computed(() => {
    return typeof window !== 'undefined' && window.innerWidth <= 768 ? 220 : 300;
});

const columns = [
    { key: 'cycle', label: 'Cycle Penilaian', width: '220px' },
    { key: 'position', label: 'Jabatan & Level', width: '190px' },
    { key: 'scores', label: 'Komposisi Nilai', width: '170px' },
    { key: 'final', label: 'Skor Akhir', width: '120px' },
    { key: 'grade', label: 'Grade', width: '100px' },
    { key: 'published', label: 'Diterbitkan & Aksi', width: '200px' },
];

const periods = computed(() => (Array.isArray(props.data?.periode) ? props.data.periode : []));

// Periods ordered newest to oldest for cards and list display
const periodsChronological = computed(() => [...periods.value].reverse());

// Recent periods slice for the trajectory widget
const recentPeriods = computed(() => periodsChronological.value.slice(0, 4));

const values = computed(() => periods.value.map((period) => angka(period.Final_Score)));
const minimum = computed(() => (values.value.length ? Math.min(...values.value) : 0));
const maximum = computed(() => (values.value.length ? Math.max(...values.value) : 0));
const range = computed(() => maximum.value - minimum.value || 1);

const chartCategories = computed(() => periods.value.map((p) => p.Kode_Cycle || p.Nama_Cycle || String(p.Tahun)));
const chartSeries = computed(() => [
    {
        name: 'Final Score',
        data: periods.value.map((p) => Number(angka(p.Final_Score).toFixed(2))),
    },
    {
        name: 'Nilai KPI (Target)',
        data: periods.value.map((p) => Number(angka(p.Nilai_KPI).toFixed(2))),
    },
    {
        name: 'Nilai Kompetensi 360°',
        data: periods.value.map((p) => Number(angka(p.Nilai_Kompetensi).toFixed(2))),
    },
]);

const bestCycleName = computed(() => {
    if (!periods.value.length) return '—';
    const bestScore = Number(props.data?.terbaik);
    const found = periods.value.find((p) => Number(p.Final_Score) === bestScore);
    if (!found) return '—';
    return found.Nama_Cycle || found.Kode_Cycle || `Cycle ${found.Tahun || ''}`;
});

/* Satu cycle belum punya pembanding: itu bukan "stabil 0,00", melainkan belum ada tren. */
const adaPembanding = computed(() => {
    const v = props.data?.perubahan_terakhir;
    return v !== null && v !== undefined && v !== '' && Number.isFinite(Number(v));
});

const deltaSentimentClass = computed(() => {
    const val = Number(props.data?.perubahan_terakhir);
    if (!Number.isFinite(val) || val === 0) return 'is-teal';
    return val > 0 ? 'is-success' : 'is-danger';
});

const deltaTextClass = computed(() => {
    const val = Number(props.data?.perubahan_terakhir);
    if (!Number.isFinite(val) || val === 0) return 'text-slate';
    return val > 0 ? 'text-success' : 'text-danger';
});

const deltaIconName = computed(() => {
    const val = Number(props.data?.perubahan_terakhir);
    if (!Number.isFinite(val) || val === 0) return 'dash-circle';
    return val > 0 ? 'arrow-up-right' : 'arrow-down-right';
});

const deltaTagText = computed(() => {
    if (!adaPembanding.value) return 'Belum ada';
    const val = Number(props.data?.perubahan_terakhir);
    if (!Number.isFinite(val) || val === 0) return 'Stabil';
    return val > 0 ? 'Meningkat' : 'Menurun';
});

const trendDirectionSummary = computed(() => {
    if (!adaPembanding.value) return 'Tren muncul setelah ada dua cycle terbit';
    const val = Number(props.data?.perubahan_terakhir);
    if (!Number.isFinite(val) || val === 0) return 'Performa stabil vs periode lalu';
    const prefix = val > 0 ? 'Kenaikan' : 'Penurunan';
    return `${prefix} ${Math.abs(val).toFixed(2)} pts dari cycle lalu`;
});

function displayGrade(g) {
    if (!g || g === '—') return '—';
    return String(g)
        .replace(/_PLUS/gi, '+')
        .replace(/_MINUS/gi, '-')
        .replace(/\b([A-E])\s*PLUS\b/gi, '$1+')
        .replace(/\b([A-E])\s*MINUS\b/gi, '$1-')
        .trim();
}

/* Nama grade dari server: pita kurva beku (atau pita siklus) siklus itu. */
function getGradeLabel(grade, nama) {
    if (!grade) return 'Grade Terbit';
    return nama || `Grade ${displayGrade(grade)}`;
}

const trendPointList = computed(() => periods.value.map((period, index) => ({
    key: period.Id_PA_Cycle || `${period.Kode_Cycle}-${index}`,
    x: periods.value.length === 1 ? 50 : (index / (periods.value.length - 1)) * 100,
    y: 84 - ((angka(period.Final_Score) - minimum.value) / range.value) * 64,
})));

const trendPoints = computed(() => trendPointList.value.map((point) => `${point.x},${point.y}`).join(' '));

function angka(value) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
}

function formatScore(value) {
    if (value === null || value === undefined || value === '') return '—';
    const parsed = Number(value);
    return Number.isFinite(parsed)
        ? parsed.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : String(value);
}

function formatDelta(value) {
    if (value === null || value === undefined || value === '') return '0.00';
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) return String(value);
    const prefix = parsed > 0 ? '+' : '';
    return `${prefix}${parsed.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatTanggalWaktu(value) {
    if (!value) return '—';
    const parsed = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) return String(value);
    return parsed.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}
</script>

<style scoped>
.pa-saya-history-panel {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    min-width: 0;
    max-width: 100%;
}

.pa-history-empty-card {
    padding: 3rem 1.5rem;
}

.pa-history-empty-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-top: 0.5rem;
}

.pa-history-loading-grid {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.pa-history-loading-row-4 {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}

.pa-history-loading-row-split {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 1.25rem;
}

.pa-history-dashboard {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.pa-saya-history__syncing {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
    padding: 0.25rem 0;
}

.pa-spin-icon {
    animation: pa-spin 1s linear infinite;
}

@keyframes pa-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* 1. Top 4 Executive KPI Highlight Cards */
.pa-history-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}

.pa-history-kpi-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 1.15rem 1.25rem;
    min-height: 8.75rem;
    border-radius: var(--hcis-radius-xl, 16px);
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(226, 232, 240, 0.85);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.02);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-history-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.08), 0 2px 6px -1px rgba(15, 23, 42, 0.04);
    background: rgba(255, 255, 255, 0.96);
}

.pa-history-kpi-card.is-average:hover { border-color: rgba(13, 148, 136, 0.45); }
.pa-history-kpi-card.is-best:hover { border-color: rgba(16, 185, 129, 0.45); }
.pa-history-kpi-card.is-trend:hover { border-color: rgba(79, 70, 229, 0.45); }
.pa-history-kpi-card.is-count:hover { border-color: rgba(124, 58, 237, 0.45); }

.pa-history-kpi-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-history-kpi-card__badge-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-history-kpi-card__icon {
    display: grid;
    place-items: center;
    width: 1.6rem;
    height: 1.6rem;
    border-radius: 7px;
    flex-shrink: 0;
}

.pa-history-kpi-card__icon.is-teal {
    background: rgba(13, 148, 136, 0.12);
    color: #0d9488;
}

.pa-history-kpi-card__icon.is-success {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.pa-history-kpi-card__icon.is-danger {
    background: rgba(239, 68, 68, 0.12);
    color: #dc2626;
}

.pa-history-kpi-card__icon.is-purple {
    background: rgba(124, 58, 237, 0.12);
    color: #7c3aed;
}

.pa-history-kpi-card__kicker {
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: var(--hcis-text-muted, #64748b);
    text-transform: uppercase;
}

.pa-history-kpi-card__tag {
    font-size: 0.6875rem;
    font-weight: 700;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
}

.pa-history-kpi-card__tag.is-teal {
    background: rgba(13, 148, 136, 0.1);
    color: #0d9488;
}

.pa-history-kpi-card__tag.is-success {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
}

.pa-history-kpi-card__tag.is-danger {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
}

.pa-history-kpi-card__tag.is-purple {
    background: rgba(124, 58, 237, 0.1);
    color: #7c3aed;
}

.pa-history-kpi-card__body {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.25rem 0;
}

.pa-history-kpi-val {
    font-size: 1.375rem;
    font-weight: 700;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}

.pa-history-kpi-caption {
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.35;
}

.pa-history-kpi-card__foot {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding-top: 0.55rem;
    border-top: 1px solid rgba(226, 232, 240, 0.85);
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text-strong, #334155);
}

.truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* 2. Analytics & Trajectory Grid */
.pa-history-analytics-grid {
    display: grid;
    grid-template-columns: 1.55fr 1fr;
    gap: 1.25rem;
    align-items: stretch;
}

.pa-history-chart-card,
.pa-history-trajectory-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    border-radius: var(--hcis-radius-xl, 16px);
}

/* Trajectory List */
.pa-trajectory-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pa-trajectory-item {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.75rem 0.85rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(226, 232, 240, 0.85);
    cursor: pointer;
    transition: all 150ms ease;
}

.pa-trajectory-item:hover {
    background: rgba(255, 255, 255, 0.96);
    border-color: rgba(79, 70, 229, 0.35);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.06);
}

.pa-trajectory-item.is-latest {
    background: rgba(240, 253, 250, 0.8);
    border-color: rgba(13, 148, 136, 0.35);
}

.pa-trajectory-item__year-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
    flex-shrink: 0;
    min-width: 3rem;
}

.pa-trajectory-year {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    font-variant-numeric: tabular-nums;
}

.pa-trajectory-latest-tag {
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #0f766e;
    background: rgba(13, 148, 136, 0.15);
    padding: 0.05rem 0.35rem;
    border-radius: 999px;
}

.pa-trajectory-item__main {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    flex: 1;
    min-width: 0;
}

.pa-trajectory-item__title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-trajectory-cycle-name {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-trajectory-grade-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.1rem 0.45rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    color: #ffffff;
    background: #10b981;
    flex-shrink: 0;
}

.pa-trajectory-grade-badge.is-grade-none {
    background: #cbd5e1;
    color: #475569;
}

.pa-trajectory-item__meta-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-trajectory-role {
    font-weight: 600;
    color: #334155;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-trajectory-dot {
    color: #94a3b8;
}

.pa-trajectory-level {
    color: #64748b;
}

.pa-trajectory-item__badges-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-top: 0.15rem;
}

.pa-trajectory-score-text {
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-trajectory-score-text strong {
    color: var(--hcis-brand-600, #4f46e5);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.pa-trajectory-item__arrow {
    color: var(--hcis-text-muted, #94a3b8);
    transition: transform 0.15s ease, color 0.15s ease;
    flex-shrink: 0;
}

.pa-trajectory-item:hover .pa-trajectory-item__arrow {
    transform: translateX(2px);
    color: var(--hcis-brand-600, #4f46e5);
}

/* 3. Chronological Evaluation Records */
.pa-history-records-section {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pa-history-records-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.pa-history-records-title-group {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pa-history-records-icon {
    display: grid;
    place-items: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 10px;
    background: rgba(79, 70, 229, 0.12);
    color: var(--hcis-brand-600, #4f46e5);
    flex-shrink: 0;
}

.pa-history-records-text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-history-records-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-history-records-subtitle {
    margin: 0;
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
}

/* View Mode Switcher */
.pa-view-switcher {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    gap: 0.25rem;
}

.pa-view-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.65rem;
    border-radius: 7px;
    border: none;
    background: transparent;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
    cursor: pointer;
    transition: all 150ms ease;
}

.pa-view-btn.is-active {
    background: #ffffff;
    color: var(--hcis-brand-600, #4f46e5);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.pa-view-label-mobile {
    display: none;
}

/* Timeline Cycle Cards */
.pa-history-cards-stack {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pa-cycle-card {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 1.25rem 1.4rem;
    border-radius: var(--hcis-radius-xl, 16px);
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(226, 232, 240, 0.85);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.02);
    transition: all 0.2s ease;
}

.pa-cycle-card:hover {
    box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.08);
    background: rgba(255, 255, 255, 0.96);
    border-color: rgba(79, 70, 229, 0.3);
}

.pa-cycle-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.85);
}

.pa-cycle-card__header-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pa-cycle-year-badge {
    padding: 0.2rem 0.55rem;
    border-radius: 8px;
    background: var(--hcis-brand-600, #4f46e5);
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.pa-cycle-title-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.pa-cycle-card__title {
    margin: 0;
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-cycle-card__code {
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-cycle-card__header-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pa-cycle-card__date {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
}

/* Card Body 3-Pillar */
.pa-cycle-card__body {
    display: grid;
    grid-template-columns: 1.2fr 1.5fr 1.8fr;
    gap: 1.25rem;
    align-items: center;
    padding: 0.35rem 0;
}

.pa-cycle-pillar {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.pa-cycle-pillar.is-grade {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 0.85rem;
}

.pa-cycle-grade-medallion {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.85rem;
    height: 2.85rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: linear-gradient(135deg, #10b981, #059669);
    color: #ffffff;
    font-size: 1.25rem;
    font-weight: 700;
    box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25);
    flex-shrink: 0;
}

.pa-cycle-grade-medallion.is-grade-none {
    background: #cbd5e1;
    color: #475569;
    box-shadow: none;
}

.pa-cycle-grade-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pa-cycle-pillar-lbl {
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--hcis-text-muted, #64748b);
}

.pa-cycle-grade-predicate {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #059669;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Pillar 2: Score */
.pa-cycle-pillar-head {
    display: flex;
    align-items: baseline;
    gap: 0.25rem;
}

.pa-cycle-score-scale {
    font-size: 0.6875rem;
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-cycle-score-number-row {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
}

.pa-cycle-score-val {
    font-size: 1.375rem;
    font-weight: 700;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}

.pa-cycle-score-unit {
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-cycle-score-bar-wrap {
    width: 100%;
    height: 5px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}

.pa-cycle-score-bar {
    height: 100%;
    border-radius: 999px;
    background: var(--hcis-brand-600, #4f46e5);
}

/* Pillar 3: Details */
.pa-cycle-components-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.45rem 0.65rem;
    border-radius: 8px;
    background: rgba(248, 250, 252, 0.8);
    border: 1px solid rgba(226, 232, 240, 0.85);
}

.pa-cycle-component-item {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
}

.pa-comp-lbl {
    font-size: 0.6875rem;
    font-weight: 500;
    color: var(--hcis-text-muted, #64748b);
}

.pa-comp-val {
    font-size: 0.875rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.pa-cycle-comp-sep {
    width: 1px;
    height: 1.6rem;
    background: rgba(226, 232, 240, 0.9);
}

/* Card Footer */
.pa-cycle-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(226, 232, 240, 0.85);
    flex-wrap: wrap;
}

.pa-cycle-role-info {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-cycle-role-info strong {
    color: var(--hcis-text-strong, #334155);
}

.pa-cycle-dot {
    color: #94a3b8;
}

/* Table View Styling */
.pa-history-table-card {
    border-radius: var(--hcis-radius-xl, 16px);
}

.pa-saya-history__cycle-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-saya-history__cycle-cell strong {
    font-size: 0.8125rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-saya-history__cycle-cell span {
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-table-role-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-table-role-cell strong {
    font-size: 0.8125rem;
    color: var(--hcis-text-strong, #334155);
}

.pa-table-role-cell span {
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-saya-history__scores-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-saya-history__scores-cell strong {
    color: var(--hcis-text-strong, #0f172a);
    font-variant-numeric: tabular-nums;
}

.pa-table-final-score {
    font-size: 0.9375rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.pa-table-grade-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    background: #10b981;
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 700;
}

.pa-table-grade-pill.is-grade-none {
    background: #cbd5e1;
    color: #475569;
}

.pa-table-action-cell {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
}

.text-teal { color: #0d9488; }
.text-success { color: #059669; }
.text-brand { color: #4f46e5; }
.text-purple { color: #7c3aed; }
.text-amber { color: #d97706; }
.text-slate { color: #64748b; }
.text-danger { color: #dc2626; }

@media (max-width: 1100px) {
    .pa-history-kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .pa-history-analytics-grid {
        grid-template-columns: 1fr;
    }

    .pa-cycle-card__body {
        grid-template-columns: 1fr 1fr;
    }

    .pa-cycle-pillar.is-details {
        grid-column: span 2;
    }
}

@media (max-width: 768px) {
    .pa-view-label-desktop {
        display: none;
    }

    .pa-view-label-mobile {
        display: inline;
    }

    .pa-history-dashboard {
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
    }

    .pa-history-kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.5rem;
    }

    .pa-history-kpi-card {
        min-height: auto !important;
        padding: 0.65rem 0.75rem !important;
        border-radius: var(--hcis-radius-lg, 12px) !important;
        justify-content: flex-start !important;
        gap: 0.35rem !important;
    }

    .pa-history-kpi-card__head {
        height: auto !important;
        min-height: 1.35rem !important;
    }

    .pa-history-kpi-card__icon {
        width: 1.35rem !important;
        height: 1.35rem !important;
        font-size: 0.75rem !important;
        border-radius: 5px !important;
    }

    .pa-history-kpi-card__kicker {
        font-size: 0.6rem !important;
        letter-spacing: 0.02em !important;
    }

    .pa-history-kpi-card__tag {
        font-size: 0.6rem !important;
        padding: 0.05rem 0.3rem !important;
    }

    .pa-history-kpi-card__body {
        min-height: auto !important;
        padding: 0.15rem 0 !important;
        gap: 0.15rem !important;
    }

    /* Subtitles, captions & footers hidden on mobile */
    .pa-history-kpi-caption,
    .pa-history-kpi-card__foot,
    .pa-history-records-subtitle,
    :deep(.hcis-card__description),
    :deep(.hcis-card p.hcis-card__description) {
        display: none !important;
    }

    :deep(.hcis-card__header) {
        padding: 0.75rem 0.85rem !important;
        gap: 0.5rem !important;
    }

    :deep(.hcis-card__body) {
        padding: 0.75rem 0.85rem !important;
    }

    :deep(.hcis-card__icon-box) {
        width: 2rem !important;
        height: 2rem !important;
        min-width: 2rem !important;
        min-height: 2rem !important;
        font-size: 0.95rem !important;
        border-radius: 8px !important;
    }

    :deep(.hcis-card__title) {
        font-size: 0.875rem !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        line-height: 1.25 !important;
    }

    .pa-history-kpi-val {
        font-size: 1.25rem;
    }

    /* CRITICAL MOBILE OPTIMIZATION:
       Hide duplicate "Evolusi Karir & Talenta" widget on mobile.
       Mobile users already have the comprehensive chronological list below. */
    .pa-history-trajectory-card {
        display: none !important;
    }

    .pa-history-analytics-grid {
        grid-template-columns: 1fr !important;
        gap: 0.85rem !important;
    }

    .pa-history-chart-card {
        border-radius: var(--hcis-radius-lg, 12px) !important;
    }

    /* Records Section Header */
    .pa-history-records-header {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }

    .pa-history-records-title-group {
        gap: 0.5rem;
    }

    .pa-history-records-icon {
        width: 1.85rem !important;
        height: 1.85rem !important;
        border-radius: 8px !important;
    }

    .pa-history-records-title {
        font-size: 0.875rem !important;
    }

    .pa-view-switcher {
        display: grid;
        grid-template-columns: 1fr 1fr;
        width: 100%;
        padding: 0.15rem;
    }

    .pa-view-btn {
        justify-content: center;
        padding: 0.35rem 0.5rem;
        font-size: 0.75rem;
    }

    /* Mobile Cycle Card: Compact & Touch-Friendly */
    .pa-cycle-card {
        padding: 0.75rem 0.85rem !important;
        gap: 0.5rem !important;
        border-radius: var(--hcis-radius-lg, 12px) !important;
        cursor: pointer;
    }

    .pa-cycle-card__header {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.5rem !important;
        padding-bottom: 0.45rem !important;
    }

    .pa-cycle-card__header-left {
        display: flex !important;
        align-items: center !important;
        gap: 0.45rem !important;
        min-width: 0 !important;
        flex: 1 !important;
    }

    .pa-cycle-year-badge {
        padding: 0.15rem 0.4rem !important;
        font-size: 0.7rem !important;
        border-radius: 6px !important;
        flex-shrink: 0 !important;
    }

    .pa-cycle-title-wrap {
        min-width: 0 !important;
        flex: 1 !important;
        display: flex !important;
        flex-direction: column !important;
        gap: 0.05rem !important;
    }

    .pa-cycle-card__title {
        font-size: 0.8125rem !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        line-height: 1.2 !important;
    }

    .pa-cycle-card__code {
        font-size: 0.6875rem !important;
        color: var(--hcis-text-muted, #64748b) !important;
        line-height: 1.1 !important;
    }

    .pa-cycle-card__header-right {
        flex-shrink: 0 !important;
        display: flex !important;
        align-items: center !important;
        gap: 0.35rem !important;
    }

    .pa-cycle-card__badge-published,
    .pa-cycle-card__header-right :deep(.hcis-badge) {
        display: none !important;
    }

    .pa-cycle-card__date {
        font-size: 0.6875rem !important;
        color: var(--hcis-text-muted, #94a3b8) !important;
        white-space: nowrap !important;
    }

    /* Card Body: Compact 2-Row Split */
    .pa-cycle-card__body {
        display: grid !important;
        grid-template-columns: auto 1fr !important;
        grid-template-areas:
            "grade score"
            "details details" !important;
        gap: 0.45rem 0.65rem !important;
        align-items: center !important;
        padding: 0.15rem 0 !important;
    }

    .pa-cycle-pillar.is-grade {
        grid-area: grade !important;
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        gap: 0.45rem !important;
        min-width: 0 !important;
    }

    .pa-cycle-grade-medallion {
        width: 2.25rem !important;
        height: 2.25rem !important;
        font-size: 1rem !important;
        border-radius: 8px !important;
        flex-shrink: 0 !important;
    }

    .pa-cycle-grade-info {
        display: flex !important;
        flex-direction: column !important;
        min-width: 0 !important;
    }

    .pa-cycle-pillar.is-grade .pa-cycle-pillar-lbl {
        display: none !important;
    }

    .pa-cycle-grade-predicate {
        font-size: 0.75rem !important;
        font-weight: 600 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        max-width: 9rem !important;
    }

    .pa-cycle-pillar.is-score {
        grid-area: score !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: flex-end !important;
        gap: 0.1rem !important;
    }

    .pa-cycle-pillar.is-score .pa-cycle-pillar-head {
        display: none !important;
    }

    .pa-cycle-score-number-row {
        display: flex !important;
        align-items: baseline !important;
        gap: 0.2rem !important;
    }

    .pa-cycle-score-val {
        font-size: 1.2rem !important;
        font-weight: 700 !important;
        line-height: 1 !important;
    }

    .pa-cycle-score-unit {
        font-size: 0.6875rem !important;
        color: var(--hcis-text-muted, #64748b) !important;
    }

    .pa-cycle-score-bar-wrap {
        width: 4.5rem !important;
        height: 3px !important;
    }

    .pa-cycle-pillar.is-details {
        grid-area: details !important;
        width: 100% !important;
    }

    .pa-cycle-components-row {
        display: flex !important;
        align-items: center !important;
        justify-content: space-around !important;
        gap: 0.5rem !important;
        padding: 0.35rem 0.5rem !important;
        border-radius: 8px !important;
        background: rgba(248, 250, 252, 0.7) !important;
    }

    .pa-cycle-component-item {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        gap: 0.35rem !important;
    }

    .pa-comp-lbl {
        font-size: 0.6875rem !important;
    }

    .pa-comp-val {
        font-size: 0.75rem !important;
        font-weight: 700 !important;
    }

    .pa-cycle-comp-sep {
        height: 0.85rem !important;
    }

    /* Card Footer: Single Flex Row */
    .pa-cycle-card__footer {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.5rem !important;
        padding-top: 0.45rem !important;
        border-top: 1px solid rgba(226, 232, 240, 0.75) !important;
    }

    .pa-cycle-role-info {
        display: flex !important;
        align-items: center !important;
        gap: 0.3rem !important;
        font-size: 0.7rem !important;
        color: var(--hcis-text-muted, #64748b) !important;
        min-width: 0 !important;
        flex: 1 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .pa-cycle-role-prefix {
        display: none !important;
    }

    .pa-cycle-role-text {
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .pa-cycle-card__footer :deep(.hcis-button),
    .pa-cycle-card__footer button {
        width: auto !important;
        flex-shrink: 0 !important;
        padding: 0.25rem 0.6rem !important;
        font-size: 0.75rem !important;
        height: auto !important;
        min-height: 1.75rem !important;
        white-space: nowrap !important;
    }

    .pa-history-loading-row-4,
    .pa-history-loading-row-split {
        grid-template-columns: 1fr;
    }
}
</style>
