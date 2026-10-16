<template>
    <section class="pa-control-tower" aria-label="Dashboard Administrator PA">
        <!-- Skeleton Loading State -->
        <div v-if="cyclesLoading && !cycles.length" class="pa-control-tower__loading-grid" aria-live="polite">
            <HcisCard v-for="item in 4" :key="item" loading variant="glass" sentiment="brand" class="pa-control-tower__loading-card" />
        </div>

        <!-- Empty Cycles State -->
        <HcisResultState
            v-else-if="!cycles.length"
            status="info"
            variant="glass"
            title="Belum ada siklus untuk dianalisis"
            description="Buat atau siapkan siklus PA terlebih dahulu agar Dashboard dapat menampilkan ringkasan kesiapan, progres, dan hasil."
            icon="bar-chart-line"
            :show-retry="false"
        />

        <template v-else>
            <!-- 1. Hero Cockpit & Cycle Selector -->
            <HcisCard variant="glass" sentiment="brand" pattern="grid" class="pa-control-tower__hero-cockpit mb-4">
                <template #header>
                    <div class="pa-control-tower__hero-heading">
                        <div class="pa-control-tower__hero-titles">
                            <span class="pa-report-eyebrow">PERFORMANCE APPRAISAL · EXECUTIVE COCKPIT</span>
                            <h2>Dashboard Eksekutif PA</h2>
                            <p>Pusat komando dan ringkasan kesehatan siklus penilaian kinerja, kepatuhan penilai, serta sebaran talenta organisasi.</p>
                        </div>
                        <div class="pa-control-tower__hero-badges">
                            <HcisBadge
                                v-if="selectedCycle"
                                :label="statusLabel(selectedCycle.Status_Cycle) || selectedCycle.Status_Cycle || 'CYCLE'"
                                :status="selectedCycle.Status_Cycle"
                                :variant="statusTone(selectedCycle.Status_Cycle)"
                                appearance="glass"
                                size="md"
                                dot
                            />
                        </div>
                    </div>
                </template>

                <div class="pa-control-tower__hero-grid">
                    <div class="pa-report-select">
                        <label for="pa-control-tower-cycle">Siklus yang dianalisis</label>
                        <HcisSelect
                            id="pa-control-tower-cycle"
                            v-model="selectedCycleId"
                            placeholder="Pilih siklus"
                            leading-icon="calendar-range"
                            filterable
                            aria-label="Siklus yang dianalisis"
                        >
                            <HcisSelectOption
                                v-for="cycle in cycles"
                                :key="cycle.Id_PA_Cycle"
                                :value="String(cycle.Id_PA_Cycle)"
                                :label="`${cycle.Kode_Cycle || 'Tanpa kode'} · ${cycle.Nama_Cycle || 'Siklus PA'}`"
                            />
                        </HcisSelect>
                    </div>

                    <div class="pa-control-tower__hero-meta" aria-live="polite">
                        <div class="pa-meta-chip">
                            <HcisIcon name="calendar-event" size="sm" color="brand" />
                            <div>
                                <span>Periode dinilai</span>
                                <strong>{{ periodLabel }}</strong>
                            </div>
                        </div>
                        <div class="pa-meta-chip">
                            <HcisIcon name="clock-history" size="sm" color="teal" />
                            <div>
                                <span>Pembaruan data</span>
                                <strong>{{ lastUpdated || 'Memuat data…' }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="pa-control-tower__hero-actions">
                        <HcisButton
                            variant="secondary"
                            size="sm"
                            leading-icon="arrow-clockwise"
                            label="Segarkan data"
                            :loading="anyLoading"
                            :disabled="!selectedCycleId"
                            @click="refreshReports"
                        />
                    </div>
                </div>
            </HcisCard>

            <!-- Loading Skeleton for Dashboard Content -->
            <div v-if="ringkasan.loading && !ringkasan.data" class="pa-report-skeleton-grid" aria-live="polite">
                <HcisCard v-for="item in 4" :key="item" loading variant="glass" sentiment="brand" />
            </div>

            <!-- Error States -->
            <HcisResultState
                v-else-if="ringkasan.status === 403"
                status="danger"
                variant="glass"
                title="Akses laporan tidak tersedia"
                description="Akun ini belum memiliki izin untuk melihat ringkasan siklus PA."
                icon="shield-lock-fill"
                :show-retry="false"
            />
            <HcisResultState
                v-else-if="ringkasan.error"
                status="danger"
                variant="glass"
                title="Ringkasan belum dapat dimuat"
                :description="ringkasan.error"
                primary-text="Coba lagi"
                primary-icon="arrow-clockwise"
                @primary-click="loadSummary"
            />

            <!-- Main Executive Dashboard Content -->
            <section v-else-if="ringkasan.data" class="pa-report-workspace" aria-labelledby="pa-summary-title">
                <!-- Section Heading -->
                <div class="pa-report-section-heading">
                    <div>
                        <span class="pa-report-eyebrow">EXECUTIVE SNAPSHOT</span>
                        <h2 id="pa-summary-title">Kesehatan siklus dalam satu pandangan</h2>
                        <p>Kesehatan cycle dalam satu pandangan — agregat kepatuhan rater, kesiapan peserta, dan progres evaluasi.</p>
                    </div>
                    <HcisBadge
                        :label="`${formatNumber(summaryData.penugasan?.penyelesaian_persen)}% selesai`"
                        :variant="Number(summaryData.penugasan?.penyelesaian_persen || 0) >= 80 ? 'success' : Number(summaryData.penugasan?.penyelesaian_persen || 0) >= 40 ? 'brand' : 'warning'"
                        appearance="glass"
                        size="sm"
                    />
                </div>

                <!-- 2. Hero 4 Stat Cards -->
                <div class="pa-report-stat-grid">
                    <HcisStatCard
                        label="Peserta"
                        :value="formatNumber(summaryData.peserta?.total)"
                        :caption="`${formatNumber(summaryData.peserta?.diikutkan)} diikutkan · ${formatNumber(summaryData.peserta?.dikecualikan)} dikecualikan`"
                        icon="people-fill"
                        sentiment="brand"
                        layout="hero"
                    />
                    <HcisStatCard
                        label="Penugasan"
                        :value="formatNumber(summaryData.penugasan?.total)"
                        :caption="`${formatNumber(summaryData.penugasan?.selesai)} selesai · ${formatNumber(summaryData.penugasan?.terlambat)} terlambat`"
                        icon="diagram-3-fill"
                        sentiment="teal"
                        layout="hero"
                    />
                    <HcisStatCard
                        label="Hasil"
                        :value="formatNumber(summaryData.hasil?.total)"
                        :caption="`${formatNumber(summaryData.hasil?.terbit)} terbit · ${formatNumber(summaryData.hasil?.tertahan)} tertahan`"
                        icon="award-fill"
                        sentiment="purple"
                        layout="hero"
                    />
                    <HcisStatCard
                        label="Penyelesaian"
                        :value="`${formatNumber(summaryData.penugasan?.penyelesaian_persen)}%`"
                        :caption="`${formatNumber(summaryData.penugasan?.selesai)} dari ${formatNumber(summaryData.penugasan?.total)} formulir`"
                        icon="speedometer2"
                        :progress="Number(summaryData.penugasan?.penyelesaian_persen || 0)"
                        sentiment="success"
                        layout="hero"
                    />
                </div>

                <!-- 3. Workflow Health & Result Readiness -->
                <div class="pa-report-two-column mb-4">
                    <HcisCard variant="glass" sentiment="brand" class="pa-report-card">
                        <template #header>
                            <div class="pa-report-card__heading">
                                <div>
                                    <span class="pa-report-eyebrow">WORKFLOW HEALTH</span>
                                    <h3>Status penugasan rater</h3>
                                </div>
                                <HcisIcon name="activity" size="md" color="brand" />
                            </div>
                        </template>
                        <div class="pa-status-list">
                            <div v-for="item in statusEntries(summaryData.penugasan?.per_status)" :key="item.key" class="pa-status-row">
                                <div class="pa-status-row__label">
                                    <span class="pa-status-dot" :class="`pa-status-dot--${statusTone(item.key)}`" />
                                    <span>{{ statusLabel(item.key) }}</span>
                                </div>
                                <strong>{{ formatNumber(item.value) }}</strong>
                            </div>
                        </div>
                        <div class="pa-report-progress-block">
                            <div class="d-flex justify-content-between gap-2 mb-2">
                                <span>Progres penilaian formulir</span>
                                <strong>{{ formatNumber(summaryData.penugasan?.penyelesaian_persen) }}%</strong>
                            </div>
                            <HcisProgress
                                :value="Number(summaryData.penugasan?.penyelesaian_persen || 0)"
                                sentiment="success"
                                size="md"
                                :show-value="false"
                                animated
                            />
                        </div>
                    </HcisCard>

                    <HcisCard variant="glass" sentiment="purple" class="pa-report-card">
                        <template #header>
                            <div class="pa-report-card__heading">
                                <div>
                                    <span class="pa-report-eyebrow">RESULT READINESS</span>
                                    <h3>Status hasil penilaian</h3>
                                </div>
                                <HcisIcon name="clipboard2-pulse" size="md" color="purple" />
                            </div>
                        </template>
                        <div class="pa-status-list">
                            <div v-for="item in statusEntries(summaryData.hasil?.per_status)" :key="item.key" class="pa-status-row">
                                <div class="pa-status-row__label">
                                    <span class="pa-status-dot" :class="`pa-status-dot--${statusTone(item.key)}`" />
                                    <span>{{ statusLabel(item.key) }}</span>
                                </div>
                                <strong>{{ formatNumber(item.value) }}</strong>
                            </div>
                        </div>
                        <div class="pa-result-callout">
                            <HcisIcon name="info-circle-fill" size="sm" color="purple" />
                            <span>{{ formatNumber(summaryData.peserta?.tertunda) }} peserta masih berada di keranjang tertunda.</span>
                        </div>
                    </HcisCard>
                </div>

                <!-- 4. Visual Analytics: Grade Distribution & Score Histogram -->
                <div class="pa-report-two-column mb-4">
                    <HcisChart
                        :key="`grade-${selectedCycleId}-${distribution.data ? 'ready' : 'empty'}`"
                        type="donut"
                        :data="gradeChartData"
                        :loading="distribution.loading"
                        :height="280"
                        title="Komposisi Grade"
                        kicker="GRADE SEBARAN"
                        :description="distribution.data?.grade_sementara
                            ? 'Grade SEMENTARA — kurva belum dikunci, angkanya masih bisa bergeser.'
                            : 'Distribusi predikat grade hasil penilaian kinerja peserta.'"
                        :badge="`Completion: ${formatNumber(summaryData.penugasan?.penyelesaian_persen)}%`"
                        :badge-variant="Number(summaryData.penugasan?.penyelesaian_persen || 0) >= 100 ? 'success' : 'purple'"
                        sentiment="purple"
                        variant="glass"
                        empty-text="Belum Ada Data Grade"
                        allow-table-toggle
                    >
                        <template #footer>
                            <div class="pa-chart-completion-footer">
                                <div class="d-flex align-items-center gap-2">
                                    <HcisIcon name="check2-circle" size="xs" color="purple" />
                                    <span class="pa-completion-label">Completion Keseluruhan</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="pa-completion-meta">{{ formatNumber(summaryData.penugasan?.selesai) }} dari {{ formatNumber(summaryData.penugasan?.total) }} selesai</span>
                                    <HcisBadge
                                        :label="`${formatNumber(summaryData.penugasan?.penyelesaian_persen)}%`"
                                        :variant="Number(summaryData.penugasan?.penyelesaian_persen || 0) >= 100 ? 'success' : 'purple'"
                                        appearance="subtle"
                                        size="xs"
                                    />
                                </div>
                            </div>
                        </template>
                    </HcisChart>

                    <HcisChart
                        :key="`histogram-${selectedCycleId}-${distribution.data ? 'ready' : 'empty'}`"
                        type="bar"
                        :categories="histogramCategories"
                        :series="histogramSeries"
                        :loading="distribution.loading"
                        :height="280"
                        title="Sebaran Rentang Nilai"
                        kicker="RENTANG SKOR (0–100)"
                        description="Distribusi jumlah peserta berdasarkan rentang skor akhir."
                        :badge="distribution.data?.keseluruhan?.rata_rata ? `Rerata: ${formatDecimal(distribution.data.keseluruhan.rata_rata)}` : 'Skor 0–100'"
                        badge-variant="teal"
                        sentiment="teal"
                        variant="glass"
                        empty-text="Belum Ada Data Nilai"
                        allow-table-toggle
                    >
                        <template #footer>
                            <div class="pa-chart-completion-footer">
                                <div class="d-flex align-items-center gap-2">
                                    <HcisIcon name="bar-chart-line-fill" size="xs" color="teal" />
                                    <span class="pa-completion-label">Rentang Skor Akhir</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="pa-completion-meta">Min {{ formatDecimal(distribution.data?.keseluruhan?.terendah) }} · Maks {{ formatDecimal(distribution.data?.keseluruhan?.tertinggi) }}</span>
                                    <HcisBadge
                                        :label="`${formatNumber(distribution.data?.keseluruhan?.jumlah || summaryData.hasil?.total || 0)} Peserta`"
                                        variant="teal"
                                        appearance="subtle"
                                        size="xs"
                                    />
                                </div>
                            </div>
                        </template>
                    </HcisChart>
                </div>

                <!-- 5. Division Progress & Coverage Table -->
                <HcisCard variant="glass" sentiment="teal" class="pa-report-card mb-4">
                    <template #header>
                        <div class="pa-report-card__heading">
                            <div>
                                <span class="pa-report-eyebrow">ORGANIZATION PROGRESS</span>
                                <h3>Kesiapan penugasan per divisi</h3>
                                <p>Total seluruh penugasan per divisi kerja, termasuk kepatuhan pengisian rater.</p>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <HcisBadge :label="`${summaryData.per_divisi?.length || 0} divisi`" variant="teal" appearance="subtle" size="sm" />
                                <HcisButton
                                    href="/performance-appraisal/pemantauan"
                                    label="Buka Monitoring &amp; Verifikasi"
                                    variant="secondary"
                                    size="xs"
                                    leading-icon="arrow-up-right"
                                />
                            </div>
                        </div>
                    </template>

                    <div v-if="summaryData.per_divisi?.length" class="pa-division-table-wrap">
                        <table class="pa-division-table">
                            <caption class="visually-hidden">Kesiapan penugasan per divisi</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Divisi</th>
                                    <th scope="col">Penugasan</th>
                                    <th scope="col">Selesai</th>
                                    <th scope="col">Terlambat</th>
                                    <th scope="col">Penyelesaian</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="division in summaryData.per_divisi" :key="`${division.id_divisi}-${division.nama_divisi}`">
                                    <th scope="row">
                                        <span class="pa-division-name">{{ division.nama_divisi || '(Tanpa divisi)' }}</span>
                                    </th>
                                    <td>{{ formatNumber(division.total) }}</td>
                                    <td>{{ formatNumber(division.selesai) }}</td>
                                    <td>
                                        <HcisBadge
                                            :label="formatNumber(division.terlambat)"
                                            :variant="Number(division.terlambat) ? 'danger' : 'success'"
                                            appearance="subtle"
                                            size="xs"
                                        />
                                    </td>
                                    <td>
                                        <div class="pa-inline-progress">
                                            <span>{{ formatNumber(division.penyelesaian_persen) }}%</span>
                                            <i><b :style="{ width: `${safePercent(division.penyelesaian_persen)}%` }" /></i>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <HcisResultState
                        v-else
                        status="info"
                        variant="flat"
                        title="Belum ada cakupan divisi"
                        description="Belum ada penugasan yang dapat diringkas per divisi."
                        :show-retry="false"
                    />
                </HcisCard>

                <!-- 6. Talent Highlights & Spotlight -->
                <div class="pa-talent-spotlight mb-4">
                    <div class="pa-talent-spotlight__heading">
                        <div>
                            <span class="pa-report-eyebrow">TALENT HIGHLIGHTS</span>
                            <h3>Sorotan talenta &amp; performa karyawan</h3>
                            <p>Peserta ber-grade teratas, berkompetensi tinggi menurut kuadran HAV, dan ber-grade terbawah yang memerlukan pendampingan.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <HcisButton
                                href="/performance-appraisal/hav-map"
                                label="9-Box HAV Map"
                                variant="secondary"
                                size="xs"
                                leading-icon="grid-3x3"
                            />
                            <HcisButton
                                href="/performance-appraisal/distribusi"
                                label="Distribusi Performance"
                                variant="secondary"
                                size="xs"
                                leading-icon="bar-chart"
                            />
                        </div>
                    </div>

                    <div class="pa-talent-spotlight__grid">
                        <!-- Top Performers -->
                        <HcisCard variant="glass" sentiment="brand" class="pa-spotlight-card">
                            <template #header>
                                <div class="pa-spotlight-card__head">
                                    <div class="pa-spotlight-card__title">
                                        <span class="pa-spotlight-badge pa-spotlight-badge--gold">🏆</span>
                                        <strong>Top Performers</strong>
                                    </div>
                                    <HcisBadge label="Grade Teratas" variant="brand" appearance="subtle" size="xs" />
                                </div>
                            </template>
                            <div v-if="distribution.data?.talent_highlights?.top_performers?.length" class="pa-spotlight-list">
                                <div
                                    v-for="emp in distribution.data.talent_highlights.top_performers.slice(0, 5)"
                                    :key="emp.participant_id"
                                    class="pa-spotlight-row"
                                    @click="handleSelectParticipant(emp)"
                                >
                                    <div class="pa-spotlight-row__info">
                                        <strong>{{ emp.nama }}</strong>
                                        <span>{{ emp.kode_karyawan }} · {{ emp.level || emp.divisi }}</span>
                                    </div>
                                    <div class="pa-spotlight-row__score">
                                        <span class="pa-score-tag pa-score-tag--high">{{ formatDecimal(emp.skor) }}</span>
                                        <HcisBadge :label="namaGrade(emp.grade)" variant="brand" appearance="subtle" size="2xs" />
                                    </div>
                                </div>
                            </div>
                            <p v-else class="pa-spotlight-empty">Belum ada data nilai terhitung.</p>
                        </HcisCard>

                        <!-- High Potential -->
                        <HcisCard variant="glass" sentiment="purple" class="pa-spotlight-card">
                            <template #header>
                                <div class="pa-spotlight-card__head">
                                    <div class="pa-spotlight-card__title">
                                        <span class="pa-spotlight-badge pa-spotlight-badge--purple">⭐</span>
                                        <strong>High Potential</strong>
                                    </div>
                                    <HcisBadge label="Kompetensi Tinggi" variant="purple" appearance="subtle" size="xs" />
                                </div>
                            </template>
                            <div v-if="distribution.data?.talent_highlights?.high_potential?.length" class="pa-spotlight-list">
                                <div
                                    v-for="emp in distribution.data.talent_highlights.high_potential.slice(0, 5)"
                                    :key="emp.participant_id"
                                    class="pa-spotlight-row"
                                    @click="handleSelectParticipant(emp)"
                                >
                                    <div class="pa-spotlight-row__info">
                                        <strong>{{ emp.nama }}</strong>
                                        <span>{{ emp.kode_karyawan }} · {{ emp.level || emp.divisi }}</span>
                                    </div>
                                    <div class="pa-spotlight-row__score">
                                        <span class="pa-score-tag pa-score-tag--purple">{{ formatDecimal(emp.nilai_kompetensi ?? emp.skor) }}</span>
                                        <HcisBadge :label="emp.kuadran || '–'" variant="purple" appearance="subtle" size="2xs" />
                                    </div>
                                </div>
                            </div>
                            <p v-else class="pa-spotlight-empty">Belum ada data nilai kompetensi.</p>
                        </HcisCard>

                        <!-- Need Improvement -->
                        <HcisCard variant="glass" sentiment="warning" class="pa-spotlight-card">
                            <template #header>
                                <div class="pa-spotlight-card__head">
                                    <div class="pa-spotlight-card__title">
                                        <span class="pa-spotlight-badge pa-spotlight-badge--warning">⚠️</span>
                                        <strong>Need Improvement</strong>
                                    </div>
                                    <HcisBadge label="Grade Terbawah" variant="warning" appearance="subtle" size="xs" />
                                </div>
                            </template>
                            <div v-if="distribution.data?.talent_highlights?.need_improvement?.length" class="pa-spotlight-list">
                                <div
                                    v-for="emp in distribution.data.talent_highlights.need_improvement.slice(0, 5)"
                                    :key="emp.participant_id"
                                    class="pa-spotlight-row"
                                    @click="handleSelectParticipant(emp)"
                                >
                                    <div class="pa-spotlight-row__info">
                                        <strong>{{ emp.nama }}</strong>
                                        <span>{{ emp.kode_karyawan }} · {{ emp.level || emp.divisi }}</span>
                                    </div>
                                    <div class="pa-spotlight-row__score">
                                        <span class="pa-score-tag pa-score-tag--low">{{ formatDecimal(emp.skor) }}</span>
                                        <HcisBadge :label="namaGrade(emp.grade)" variant="warning" appearance="subtle" size="2xs" />
                                    </div>
                                </div>
                            </div>
                            <p v-else class="pa-spotlight-empty">Tidak ada karyawan ber-grade terbawah.</p>
                        </HcisCard>
                    </div>
                </div>
            </section>
        </template>
    </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import {
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisChart,
    HcisIcon,
    HcisProgress,
    HcisResultState,
    HcisSelect,
    HcisSelectOption,
    HcisStatCard,
} from '@/components/ui';
import { usePaAdminControlTower } from '../composables/usePaAdminControlTower.js';

defineOptions({ name: 'PaAdminControlTower' });

const props = defineProps({
    cycles: { type: Array, default: () => [] },
    cyclesLoading: { type: Boolean, default: false },
    initialCycleId: { type: [String, Number], default: '' },
});

const emit = defineEmits(['refresh-cycles', 'select-participant']);
const selectedCycleId = ref('');
const lastUpdated = ref('');

const {
    ringkasan,
    distribusi: distribution,
    muatRingkasan,
    muatLaporanSiklus,
} = usePaAdminControlTower();

const selectedCycle = computed(() => props.cycles.find((cycle) => String(cycle.Id_PA_Cycle) === String(selectedCycleId.value)) || null);
const summaryData = computed(() => ringkasan.data || {});
const anyLoading = computed(() => ringkasan.loading || distribution.loading);

const periodLabel = computed(() => {
    const cycle = summaryData.value.siklus || selectedCycle.value;
    if (!cycle?.Periode_Dinilai_Dari) return 'Belum ditetapkan';
    return `${dateLabel(cycle.Periode_Dinilai_Dari)} – ${dateLabel(cycle.Periode_Dinilai_Sampai)}`;
});

const gradeChartData = computed(() => {
    const list = distribution.data?.grade || [];
    return list.map((item) => ({
        name: item.nama_grade ? `${item.nama_grade} (${formatGradeLabel(item.kode_grade).replace(/^Grade /, '')})` : formatGradeLabel(item.kode_grade),
        value: Number(item.jumlah || 0),
    }));
});

/* Nama grade dari laporan distribusi (pita kurva/salinan siklus), bukan tebakan dari kode.
   Tanpa grade → tanda kosong, bukan grade karangan. */
function namaGrade(kode) {
    if (!kode) return '–';
    const g = (distribution.data?.grade || []).find((item) => item.kode_grade === kode);
    return g?.nama_grade || formatGradeLabel(kode).replace(/^Grade /, '');
}

function formatGradeLabel(kode) {
    if (!kode || kode === '(Tanpa grade)') return 'Tanpa Grade';
    const clean = String(kode)
        .replace(/_PLUS/gi, '+')
        .replace(/_MINUS/gi, '-')
        .replace(/\+/g, '+')
        .trim();
    if (clean.toLowerCase().startsWith('grade')) return clean;
    return `Grade ${clean}`;
}
const histogramCategories = computed(() => {
    const list = distribution.data?.histogram || [];
    return list.map((item) => `${formatDecimal(item.dari)}–${formatDecimal(item.sampai)}`);
});
const histogramSeries = computed(() => {
    const list = distribution.data?.histogram || [];
    if (!list.length) return [];
    return [{
        name: 'Peserta',
        data: list.map((item) => Number(item.jumlah || 0)),
    }];
});

watch(
    () => props.cycles,
    (cycles) => {
        if (!cycles.length) {
            selectedCycleId.value = '';
            return;
        }
        if (props.initialCycleId && cycles.some((c) => String(c.Id_PA_Cycle) === String(props.initialCycleId))) {
            selectedCycleId.value = String(props.initialCycleId);
        } else if (!cycles.some((cycle) => String(cycle.Id_PA_Cycle) === String(selectedCycleId.value))) {
            selectedCycleId.value = String(cycles[0].Id_PA_Cycle);
        }
    },
    { immediate: true },
);

watch(selectedCycleId, (id, previousId) => {
    if (!id || id === previousId) return;
    void muatLaporanSiklus(id, { distribusi: { id_level: '', id_divisi: '' } }).then(() => {
        lastUpdated.value = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    });
});

if (selectedCycleId.value) {
    void muatLaporanSiklus(selectedCycleId.value, { distribusi: { id_level: '', id_divisi: '' } }).then(() => {
        lastUpdated.value = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    });
}

function loadSummary() {
    return muatRingkasan(selectedCycleId.value);
}

async function refreshReports() {
    emit('refresh-cycles');
    await muatLaporanSiklus(selectedCycleId.value, { distribusi: { id_level: '', id_divisi: '' } });
    lastUpdated.value = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

function handleSelectParticipant(emp) {
    emit('select-participant', {
        Id_PA_Participant: emp.participant_id,
        Kode_Karyawan: emp.kode_karyawan,
        Nama_Karyawan_Saat_Cycle: emp.nama,
        Nama_Divisi_Saat_Cycle: emp.divisi,
        Nama_Level_Saat_Cycle: emp.level,
    });
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('id-ID');
}

function formatDecimal(value) {
    if (value === null || value === undefined || value === '') return '—';
    return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2, minimumFractionDigits: Number(value) % 1 ? 2 : 0 });
}

function dateLabel(value) {
    if (!value) return '—';
    return new Date(`${String(value).slice(0, 10)}T00:00:00`).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function safePercent(value) {
    return Math.max(0, Math.min(100, Number(value || 0)));
}

function statusEntries(statuses) {
    return Object.entries(statuses || {}).map(([key, value]) => ({ key, value }));
}

function statusLabel(status) {
    const labels = {
        BELUM_MULAI: 'Belum mulai',
        BERJALAN: 'Berjalan',
        TERKUMPUL: 'Terisi, belum dikirim',
        MENUNGGU_VERIFIKASI: 'Menunggu verifikasi',
        SELESAI: 'Selesai',
        BELUM_DIHITUNG: 'Belum dihitung',
        DIHITUNG: 'Dihitung',
        TERBIT: 'Terbit',
        TERTAHAN: 'Tertahan',
        DIIKUTKAN: 'Diikutkan',
        DIKECUALIKAN: 'Dikecualikan',
        TERTUNDA: 'Tertunda',
        DRAFT: 'Draft',
        PREPARED: 'Disiapkan',
        LOCKED: 'Terkunci',
        OPEN: 'Buka Pengisian',
        CLOSED: 'Pengisian Ditutup',
        PUBLISHED: 'Diterbitkan',
    };
    return labels[status] || String(status || '—').replaceAll('_', ' ');
}

function statusTone(status) {
    if (['SELESAI', 'TERBIT', 'DIIKUTKAN', 'PUBLISHED'].includes(status)) return 'success';
    if (['TERKUMPUL', 'MENUNGGU_VERIFIKASI', 'CLOSED'].includes(status)) return 'warning';
    if (['TERTAHAN', 'BERJALAN', 'OPEN'].includes(status)) return status === 'BERJALAN' || status === 'OPEN' ? 'brand' : 'warning';
    if (['BELUM_DIHITUNG', 'BELUM_MULAI', 'DIKECUALIKAN', 'DRAFT', 'PREPARED', 'LOCKED'].includes(status)) return 'neutral';
    return 'neutral';
}
</script>

<style scoped>
.pa-control-tower { min-width: 0; }
.pa-control-tower__loading-grid, .pa-report-skeleton-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; }
.pa-control-tower__loading-card { min-height: 10rem; }

/* Hero Cockpit */
.pa-control-tower__hero-cockpit { overflow: hidden; }
.pa-control-tower__hero-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; width: 100%; }
.pa-control-tower__hero-titles h2 { margin: .3rem 0 0; color: var(--hcis-text-primary, #0f172a); font-size: clamp(1.2rem, 2.2vw, 1.55rem); font-weight: 750; letter-spacing: -.02em; }
.pa-control-tower__hero-titles p { margin: .42rem 0 0; color: var(--hcis-text-secondary, #64748b); font-size: .84rem; line-height: 1.55; }
.pa-control-tower__hero-grid { display: grid; grid-template-columns: minmax(16rem, 1.3fr) minmax(18rem, 1.4fr) auto; align-items: end; gap: 1.25rem; margin-top: .5rem; }
.pa-control-tower__hero-meta { display: flex; align-items: center; gap: 1rem; }
.pa-meta-chip { display: flex; align-items: center; gap: .7rem; padding: .55rem .85rem; border-radius: .75rem; background: rgba(241, 245, 249, 0.75); border: 1px solid rgba(226, 232, 240, 0.8); }
.pa-meta-chip span, .pa-report-select label, .pa-report-eyebrow { display: block; color: var(--hcis-text-tertiary, #94a3b8); font-size: .67rem; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
.pa-meta-chip strong { display: block; margin-top: .15rem; color: var(--hcis-text-primary, #0f172a); font-size: .82rem; font-weight: 700; }
.pa-report-select label { margin: 0 0 .45rem .2rem; color: var(--hcis-text-secondary, #64748b); letter-spacing: .05em; }

/* Workspace Headings */
.pa-report-workspace { min-width: 0; }
.pa-report-section-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin: 1.15rem 0 1rem; }
.pa-report-section-heading h2 { margin: .25rem 0 0; color: var(--hcis-text-primary, #0f172a); font-size: clamp(1.2rem, 2.2vw, 1.55rem); font-weight: 750; letter-spacing: -.02em; }
.pa-report-section-heading p { margin: .35rem 0 0; color: var(--hcis-text-secondary, #64748b); font-size: .82rem; }

/* Stat Grid */
.pa-report-stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .9rem; margin-bottom: 1.15rem; }
.pa-report-two-column { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; align-items: stretch; }
.pa-report-two-column :deep(.hcis-chart-wrapper) { height: 100%; display: flex; flex-direction: column; }
.pa-report-two-column :deep(.hcis-card) { height: 100%; display: flex; flex-direction: column; justify-content: space-between; }
.pa-report-two-column :deep(.hcis-card__body) { flex: 1; display: flex; flex-direction: column; justify-content: center; min-height: 280px; }
.pa-report-two-column :deep(.hcis-card__header) { min-height: 4.8rem; }

/* Status List & Rows */
.pa-report-card { min-width: 0; }
.pa-report-card__heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; width: 100%; }
.pa-report-card__heading h3 { margin: .25rem 0 0; color: var(--hcis-text-primary, #0f172a); font-size: 1rem; font-weight: 750; }
.pa-report-card__heading p { margin: .35rem 0 0; color: var(--hcis-text-secondary, #64748b); font-size: .8rem; }
.pa-status-list { display: grid; gap: .15rem; }
.pa-status-row { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .68rem .05rem; border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0); }
.pa-status-row:last-child { border-bottom: 0; }
.pa-status-row__label { display: flex; align-items: center; gap: .58rem; color: var(--hcis-text-secondary, #475569); font-size: .82rem; }
.pa-status-row strong { color: var(--hcis-text-primary, #0f172a); font-size: .9rem; }
.pa-status-dot { width: .52rem; height: .52rem; flex: 0 0 auto; border-radius: 999px; background: #94a3b8; box-shadow: 0 0 0 4px rgb(148 163 184 / .14); }
.pa-status-dot--success { background: #10b981; box-shadow: 0 0 0 4px rgb(16 185 129 / .14); }
.pa-status-dot--warning { background: #f59e0b; box-shadow: 0 0 0 4px rgb(245 158 11 / .14); }
.pa-status-dot--brand { background: #6366f1; box-shadow: 0 0 0 4px rgb(99 102 241 / .14); }
.pa-report-progress-block { margin-top: 1rem; padding-top: .85rem; border-top: 1px solid var(--hcis-border-subtle, #e2e8f0); color: var(--hcis-text-secondary, #64748b); font-size: .76rem; }
.pa-report-progress-block strong { color: var(--hcis-text-primary, #0f172a); }
.pa-result-callout { display: flex; align-items: flex-start; gap: .65rem; margin-top: 1rem; padding: .8rem .9rem; border: 1px solid rgb(99 102 241 / .16); border-radius: .8rem; background: rgb(99 102 241 / .06); color: #475569; font-size: .78rem; line-height: 1.5; }
.pa-chart-completion-footer { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding-top: .75rem; margin-top: .5rem; border-top: 1px solid var(--hcis-border-subtle, #e2e8f0); }
.pa-completion-label { font-size: .8rem; font-weight: 700; color: var(--hcis-text-primary, #0f172a); }
.pa-completion-meta { font-size: .75rem; color: var(--hcis-text-secondary, #64748b); }

/* Division Table */
.pa-division-table-wrap { overflow-x: auto; }
.pa-division-table { width: 100%; border-collapse: collapse; min-width: 38rem; font-size: .8rem; }
.pa-division-table th, .pa-division-table td { padding: .75rem .65rem; border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0); text-align: right; white-space: nowrap; }
.pa-division-table th:first-child, .pa-division-table td:first-child { text-align: left; }
.pa-division-table thead th { color: var(--hcis-text-tertiary, #94a3b8); font-size: .66rem; letter-spacing: .08em; text-transform: uppercase; }
.pa-division-table tbody th { color: var(--hcis-text-primary, #0f172a); font-weight: 700; }
.pa-division-name { display: inline-block; max-width: 18rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: bottom; }
.pa-inline-progress { display: flex; align-items: center; justify-content: flex-end; gap: .6rem; min-width: 8rem; color: var(--hcis-text-primary, #0f172a); font-weight: 700; }
.pa-inline-progress i { display: block; width: 4.5rem; height: .35rem; overflow: hidden; border-radius: 99px; background: #e2e8f0; }
.pa-inline-progress b { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #14b8a6, #6366f1); }

/* Talent Spotlight */
.pa-talent-spotlight { padding: 1.25rem; border-radius: 1rem; background: rgba(255, 255, 255, 0.7); border: 1px solid var(--hcis-border-subtle, #e2e8f0); }
.pa-talent-spotlight__heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
.pa-talent-spotlight__heading h3 { margin: .25rem 0 0; color: var(--hcis-text-primary, #0f172a); font-size: 1.05rem; font-weight: 750; }
.pa-talent-spotlight__heading p { margin: .35rem 0 0; color: var(--hcis-text-secondary, #64748b); font-size: .8rem; }
.pa-talent-spotlight__grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }

.pa-spotlight-card { min-width: 0; }
.pa-spotlight-card__head { display: flex; align-items: center; justify-content: space-between; width: 100%; }
.pa-spotlight-card__title { display: flex; align-items: center; gap: .45rem; font-size: .88rem; font-weight: 750; color: var(--hcis-text-primary, #0f172a); }
.pa-spotlight-badge { display: inline-flex; align-items: center; justify-content: center; width: 1.4rem; height: 1.4rem; font-size: .85rem; }
.pa-spotlight-list { display: grid; gap: .4rem; }
.pa-spotlight-row { display: flex; align-items: center; justify-content: space-between; gap: .65rem; padding: .55rem .65rem; border-radius: .6rem; background: rgba(248, 250, 252, 0.7); border: 1px solid rgba(226, 232, 240, 0.7); cursor: pointer; transition: all 150ms ease; }
.pa-spotlight-row:hover { background: #ffffff; border-color: rgba(99, 102, 241, 0.35); box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06); transform: translateY(-1px); }
.pa-spotlight-row__info { flex: 1; min-width: 0; }
.pa-spotlight-row__info strong { display: block; color: var(--hcis-text-primary, #0f172a); font-size: .78rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pa-spotlight-row__info span { display: block; margin-top: .1rem; color: var(--hcis-text-tertiary, #94a3b8); font-size: .68rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pa-spotlight-row__score { display: flex; flex-direction: column; align-items: flex-end; gap: .15rem; flex-shrink: 0; }
.pa-score-tag { font-size: .76rem; font-weight: 800; font-variant-numeric: tabular-nums; }
.pa-score-tag--high { color: #16a34a; }
.pa-score-tag--purple { color: #7c3aed; }
.pa-score-tag--low { color: #d97706; }
.pa-spotlight-empty { margin: .5rem 0; color: var(--hcis-text-tertiary, #94a3b8); font-size: .76rem; text-align: center; }

/* 7. Quick Module Jump Hub */
.pa-command-pad { padding: 1.25rem; border-radius: 1rem; background: rgba(248, 250, 252, 0.8); border: 1px solid var(--hcis-border-subtle, #e2e8f0); }
.pa-command-pad__heading { margin-bottom: 1rem; }
.pa-command-pad__heading h3 { margin: .25rem 0 0; color: var(--hcis-text-primary, #0f172a); font-size: 1.05rem; font-weight: 750; }
.pa-command-pad__heading p { margin: .3rem 0 0; color: var(--hcis-text-secondary, #64748b); font-size: .8rem; }
.pa-command-pad__grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .85rem; }

.pa-command-tile { display: flex; align-items: center; gap: .75rem; padding: .85rem; border-radius: .75rem; background: #ffffff; border: 1px solid rgba(226, 232, 240, 0.8); text-decoration: none; color: inherit; transition: all 180ms ease; }
.pa-command-tile:hover { border-color: rgba(99, 102, 241, 0.4); box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08); transform: translateY(-2px); }
.pa-command-tile__icon { display: flex; align-items: center; justify-content: center; width: 2.2rem; height: 2.2rem; border-radius: .65rem; flex-shrink: 0; }
.pa-command-tile__icon--brand { background: rgba(99, 102, 241, 0.12); color: #4f46e5; }
.pa-command-tile__icon--teal { background: rgba(20, 184, 166, 0.12); color: #0d9488; }
.pa-command-tile__icon--purple { background: rgba(168, 85, 247, 0.12); color: #9333ea; }
.pa-command-tile__icon--warning { background: rgba(245, 158, 11, 0.12); color: #d97706; }
.pa-command-tile__icon--neutral { background: rgba(148, 163, 184, 0.15); color: #475569; }
.pa-command-tile__info { flex: 1; min-width: 0; }
.pa-command-tile__info strong { display: block; color: var(--hcis-text-primary, #0f172a); font-size: .8rem; font-weight: 750; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pa-command-tile__info span { display: block; margin-top: .12rem; color: var(--hcis-text-secondary, #64748b); font-size: .68rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pa-command-tile__arrow { color: var(--hcis-text-tertiary, #94a3b8); transition: transform 150ms ease; }
.pa-command-tile:hover .pa-command-tile__arrow { color: #4f46e5; transform: translateX(3px); }

@media (max-width: 1200px) {
    .pa-command-pad__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 1100px) {
    .pa-control-tower__hero-grid { grid-template-columns: 1fr; }
    .pa-report-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .pa-talent-spotlight__grid { grid-template-columns: 1fr; }
}

@media (max-width: 760px) {
    .pa-control-tower__loading-grid, .pa-report-skeleton-grid, .pa-report-two-column, .pa-report-stat-grid { grid-template-columns: 1fr; }
    .pa-control-tower__hero-heading, .pa-report-section-heading, .pa-report-card__heading, .pa-talent-spotlight__heading { flex-direction: column; align-items: flex-start; }
    .pa-command-pad__grid { grid-template-columns: 1fr; }
    .pa-division-table { min-width: 35rem; }
}
</style>
