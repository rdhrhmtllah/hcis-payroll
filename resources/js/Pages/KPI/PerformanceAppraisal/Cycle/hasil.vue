<template>
    <main class="pa-hasil-page">
        <!-- Unified Header -->
        <HcisHeader
            title="Hasil &amp; Penerbitan Nilai"
            kicker="PERFORMANCE APPRAISAL · FASE HASIL"
            description="Kalkulasi nilai akhir, telaah distribusi skor, selenggarakan kalibrasi, dan terbitkan nilai evaluasi kinerja kepada karyawan."
            icon="calculator-fill"
            sentiment="brand"
            variant="glass"
            size="md"
            animated
            class="mb-4"
        >
            <template #badge>
                <HcisBadge
                    v-if="activeCycle"
                    :label="`Cycle: ${activeCycle.Nama_Cycle || activeCycle.Kode_Cycle} (${activeCycle.Status_Cycle})`"
                    :variant="getStatusSentiment(activeCycle.Status_Cycle)"
                    appearance="glass"
                    size="sm"
                    radar
                    dot
                />
            </template>

            <template #actions>
                <div class="pa-hasil-header-actions">
                    <!-- Cycle Selector -->
                    <div class="pa-cycle-select-wrap">
                        <label for="pa-hasil-cycle-select" class="sr-only">Pilih Cycle</label>
                        <select
                            id="pa-hasil-cycle-select"
                            v-model="selectedCycleId"
                            class="pa-cycle-select"
                            :disabled="loadingSiklus || loadingPeserta"
                            @change="onCycleChange"
                        >
                            <option v-if="!siklusList.length" value="">Memuat cycle...</option>
                            <option
                                v-for="c in siklusList"
                                :key="c.Id_PA_Cycle"
                                :value="c.Id_PA_Cycle"
                            >
                                {{ c.Nama_Cycle || c.Kode_Cycle }} ({{ c.Status_Cycle }})
                            </option>
                        </select>
                    </div>

                    <HcisButton
                        label="Segarkan"
                        variant="glass"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :loading="loadingPeserta"
                        @click="muatDataHasil"
                    />

                    <!-- Batch Calculate -->
                    <HcisButton
                        v-if="bisaHitungMassal"
                        :label="calculating
                            ? `Menghitung ${Number(progresHitung.diproses || 0).toLocaleString('id-ID')} peserta…`
                            : 'Hitung Hasil'"
                        variant="primary"
                        size="sm"
                        leading-icon="calculator"
                        :loading="calculating"
                        @click="bukaDialogHitung"
                    />

                    <!-- Publish Button -->
                    <HcisButton
                        v-if="canPublish"
                        label="Terbitkan Nilai"
                        variant="success"
                        size="sm"
                        leading-icon="send-check-fill"
                        @click="openPublishDialog"
                    />
                </div>
            </template>

            <template #console>
                <nav class="pa-admin-switcher" aria-label="Navigasi Performance Appraisal">
                    <a href="/performance-appraisal" class="pa-admin-switcher__item">
                        <HcisIcon name="grid-fill" size="2xs" />
                        <span>Assessment Cycle</span>
                    </a>
                    <a href="/performance-appraisal/pemantauan" class="pa-admin-switcher__item">
                        <HcisIcon name="hourglass-split" size="2xs" />
                        <span>Monitoring &amp; Verifikasi</span>
                    </a>
                    <a href="/performance-appraisal/hasil" class="pa-admin-switcher__item active pa-admin-switcher__item--active">
                        <HcisIcon name="calculator-fill" size="2xs" />
                        <span>Hasil &amp; Penerbitan</span>
                    </a>
                    <a href="/performance-appraisal/hav-map" class="pa-admin-switcher__item">
                        <HcisIcon name="grid-3x3" size="2xs" />
                        <span>9-Box HAV Map</span>
                    </a>
                    <a href="/performance-appraisal/distribusi" class="pa-admin-switcher__item">
                        <HcisIcon name="bar-chart-fill" size="2xs" />
                        <span>Distribusi Performance</span>
                    </a>
                    <a href="/performance-appraisal/riwayat" class="pa-admin-switcher__item">
                        <HcisIcon name="clock-history" size="2xs" />
                        <span>Riwayat Penilaian</span>
                    </a>
                    <a href="/performance-appraisal/dashboard" class="pa-admin-switcher__item">
                        <HcisIcon name="speedometer2" size="2xs" />
                        <span>Dashboard PA</span>
                    </a>
                    <a href="/performance-appraisal/master/syarat-kompetensi" class="pa-admin-switcher__item">
                        <HcisIcon name="diagram-3-fill" size="2xs" />
                        <span>Master Data</span>
                    </a>
                </nav>
            </template>
        </HcisHeader>

        <!-- Halaman lama: alur kerjanya kini di Penutupan Siklus (spec 2026-10-02). -->
        <a
            class="pa-hasil-pindah"
            :href="`/performance-appraisal/pemantauan?tahap=verifikasi${selectedCycleId ? `&cycle=${selectedCycleId}` : ''}`"
        >
            <strong>Pekerjaan hitung, kurva, balancing, dan penerbitan kini ada di Penutupan Siklus.</strong>
            <span>Halaman ini hanya untuk melihat. Buka Penutupan Siklus &rarr;</span>
        </a>

        <!-- Executive Stat Group -->
        <HcisStatGroup :cols="4" gap="sm" class="pa-hasil-stats">
            <HcisStatCard
                layout="compact"
                label="Peserta Terhitung"
                :value="`${formatAngka(calculatedCount)} / ${formatAngka(participants.length)}`"
                caption="Karyawan dengan kalkulasi nilai"
                icon="people-fill"
                sentiment="brand"
            />
            <HcisStatCard
                layout="compact"
                label="Rata-rata Skor Akhir"
                :value="averageScore > 0 ? averageScore.toFixed(2) : '—'"
                caption="Skala skor 0 - 100"
                icon="graph-up"
                sentiment="purple"
            />
            <HcisStatCard
                layout="compact"
                label="Peserta Dikalibrasi"
                :value="formatAngka(calibratedCount)"
                :caption="calibratedCount > 0 ? 'Hasil penyesuaian komite' : 'Menggunakan skor sistem'"
                icon="sliders"
                :sentiment="calibratedCount > 0 ? 'warning' : 'neutral'"
            />
            <HcisStatCard
                layout="compact"
                label="Status Penerbitan"
                :value="publishedCount > 0 ? `${formatAngka(publishedCount)} Terbit` : 'Belum Terbit'"
                :caption="activeCycle?.Status_Cycle === 'PUBLISHED' ? 'Siklus sudah diterbitkan' : 'Menunggu publikasi'"
                icon="patch-check-fill"
                :sentiment="publishedCount > 0 ? 'success' : 'neutral'"
            />
        </HcisStatGroup>

        <!-- Specialized Results Gateway Navigation Cards -->
        <section class="pa-gateways-section" aria-label="Akses Cepat Modul Analisis Hasil">
            <div class="pa-gateways-grid">
                <a href="/performance-appraisal/pemantauan?tab=HASIL" class="pa-gateway-card pa-gateway-card--kalibrasi">
                    <div class="pa-gateway-card__icon">
                        <HcisIcon name="hourglass-split" size="md" />
                    </div>
                    <div class="pa-gateway-card__text">
                        <span class="pa-eyebrow">MONITORING &amp; VERIFIKASI</span>
                        <h4>Monitoring &amp; Hasil</h4>
                        <p>Tinjau dan sesuaikan grade/kuadran karyawan pasca kalkulasi sistem pada layar pemantauan terpadu.</p>
                    </div>
                    <div class="pa-gateway-card__arrow">
                        <HcisIcon name="arrow-right-circle-fill" size="sm" />
                    </div>
                </a>

                <a href="/performance-appraisal/hav-map" class="pa-gateway-card pa-gateway-card--hav">
                    <div class="pa-gateway-card__icon">
                        <HcisIcon name="grid-3x3-gap-fill" size="md" />
                    </div>
                    <div class="pa-gateway-card__text">
                        <span class="pa-eyebrow">TALENT MATRIX</span>
                        <h4>Matriks 9-Box HAV Map</h4>
                        <p>Pemetaan visual talenta 3x3 berdasarkan sumbu Nilai Kinerja (KPI) dan Kompetensi.</p>
                    </div>
                    <div class="pa-gateway-card__arrow">
                        <HcisIcon name="arrow-right-circle-fill" size="sm" />
                    </div>
                </a>

                <a href="/performance-appraisal/distribusi" class="pa-gateway-card pa-gateway-card--distribusi">
                    <div class="pa-gateway-card__icon">
                        <HcisIcon name="bar-chart-fill" size="md" />
                    </div>
                    <div class="pa-gateway-card__text">
                        <span class="pa-eyebrow">BELL CURVE</span>
                        <h4>Distribusi Nilai &amp; Grade</h4>
                        <p>Analisis kepatuhan sebaran nilai actual vs kuota persentase korporasi (Grade A, B+, B, C, D).</p>
                    </div>
                    <div class="pa-gateway-card__arrow">
                        <HcisIcon name="arrow-right-circle-fill" size="sm" />
                    </div>
                </a>

                <a href="/performance-appraisal/riwayat" class="pa-gateway-card pa-gateway-card--riwayat">
                    <div class="pa-gateway-card__icon">
                        <HcisIcon name="clock-history" size="md" />
                    </div>
                    <div class="pa-gateway-card__text">
                        <span class="pa-eyebrow">AUDIT TRAIL</span>
                        <h4>Riwayat &amp; Lini Masa Audit</h4>
                        <p>Pencarian rekam jejak nilai lintas tahun dan audit riwayat penyesuaian rater/kalibrasi.</p>
                    </div>
                    <div class="pa-gateway-card__arrow">
                        <HcisIcon name="arrow-right-circle-fill" size="sm" />
                    </div>
                </a>
            </div>
        </section>

        <!-- Participants Table Filter Toolbar -->
        <div class="pa-hasil-controls">
            <HcisFilterBar
                :search="searchQuery"
                search-placeholder="Cari nama karyawan, NIK, divisi, level, atau grade..."
                :search-debounce="280"
                :quick-chips="statusQuickChips"
                :active-chip-value="activeStatusFilter"
                :filters="filterDefinitions"
                :filter-values="selectedFilterValues"
                variant="card"
                sentiment="brand"
                size="sm"
                glow
                :results-count="loadingPeserta ? null : filteredParticipants.length"
                show-active-tags
                @update:search="onSearchChange"
                @chip-click="onStatusChipClick"
                @filter-change="onFilterChange"
                @reset="resetFilters"
            />
        </div>

        <!-- Error State -->
        <HcisResultState
            v-if="errorPeserta"
            :status="errorStatus === 403 ? 'warning' : 'danger'"
            variant="flat"
            layout="inline"
            :title="errorStatus === 403 ? 'Akses ditolak' : 'Gagal memuat hasil peserta'"
            :description="errorPeserta"
            primary-text="Coba Lagi"
            primary-icon="arrow-clockwise"
            :show-retry="errorStatus !== 403"
            @primary-click="muatDataHasil"
        />

        <!-- Loading Skeletons -->
        <div v-else-if="loadingPeserta && !participants.length" class="pa-hasil-loading">
            <div class="pa-hasil-skeleton-grid">
                <div v-for="n in 6" :key="`skel-has-${n}`" class="pa-hasil-skeleton-card">
                    <HcisSkeleton width="180px" height="1.1rem" rounded="sm" />
                    <HcisSkeleton width="100%" height="2rem" rounded="md" />
                    <HcisSkeleton width="80px" height="0.8rem" rounded="sm" />
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <HcisEmptyState
            v-else-if="!filteredParticipants.length"
            title="Tidak ada hasil peserta yang cocok"
            description="Tidak ditemukan peserta evaluasi kinerja yang sesuai dengan kriteria pencarian atau saringan status."
            icon="calculator"
        />

        <!-- Results Table -->
        <div v-else class="pa-hasil-table-wrap">
            <HcisTable
                :columns="tableColumns"
                :rows="paginatedRows"
                row-key="Id_PA_Participant"
                responsive="scroll"
                hoverable
                variant="glass"
                sentiment="brand"
                @row-click="({ row }) => openParticipantDetail(row)"
            >
                <template #cell-karyawan="{ row }">
                    <div class="pa-table-person-cell">
                        <strong>{{ row.Nama_Karyawan_Saat_Cycle || row.Nama_Karyawan }}</strong>
                        <small>{{ row.Kode_Karyawan }} &bull; {{ row.Nama_Level_Saat_Cycle || row.Nama_Level || `Level ${row.ID_Level_Saat_Cycle || row.ID_Level || '-'}` }}</small>
                    </div>
                </template>

                <template #cell-divisi="{ row }">
                    <span class="pa-table-divisi-text">{{ row.Nama_Divisi_Saat_Cycle || row.Nama_Divisi || '—' }}</span>
                </template>

                <template #cell-skor_kpi="{ row }">
                    <span class="pa-score-text font-semibold">{{ formatNumber(row.Nilai_KPI) }}</span>
                </template>

                <template #cell-skor_kompetensi="{ row }">
                    <span class="pa-score-text font-semibold">{{ formatNumber(row.Nilai_Kompetensi) }}</span>
                </template>

                <template #cell-skor_akhir="{ row }">
                    <span class="pa-score-final font-bold">{{ formatNumber(nilaiAkhir(row)) }}</span>
                </template>

                <template #cell-grade="{ row }">
                    <div class="pa-grade-cell">
                        <span
                            class="pa-grade-badge"
                            :class="{ 'pa-grade-badge--calibrated': row.Flag_Dikalibrasi === 'Y' }"
                        >
                            <!-- Siklus kurva: grade resmi baru ada sesudah kurva dikunci di layar Distribusi;
                                 sebelumnya server mengirim Grade_Sementara (bisa bergeser). -->
                            {{ gradeTampil(row) || (row.Status_Hasil === 'DIHITUNG' ? 'Menunggu kurva' : '—') }}
                        </span>
                        <small
                            v-if="!row.Kode_Grade_Final && !row.Kode_Grade_Sistem && row.Grade_Sementara"
                            class="pa-calibrated-tag"
                            title="Kurva belum dikunci; grade bisa bergeser saat hasil lain masuk."
                            >Sementara</small
                        >
                        <small v-if="row.Flag_Dikalibrasi === 'Y'" class="pa-calibrated-tag">Dikalibrasi</small>
                    </div>
                </template>

                <template #cell-status="{ row }">
                    <HcisStatusBadge
                        v-if="row.Status_Hasil"
                        :status="row.Status_Hasil"
                        :label="row.Status_Hasil"
                        size="xs"
                    />
                    <span v-else class="text-slate-400 text-xs">—</span>
                </template>

                <template #cell-aksi="{ row }">
                    <div class="pa-row-actions">
                        <HcisButton
                            label="Lihat Rincian"
                            variant="glass"
                            size="xs"
                            leading-icon="eye"
                            @click.stop="openParticipantDetail(row)"
                        />
                    </div>
                </template>
            </HcisTable>

            <!-- Pagination Bar -->
            <div v-if="totalPages > 1" class="pa-hasil-pagination-bar">
                <span class="pa-pagination-info">
                    Menampilkan <strong>{{ (currentPage - 1) * perPage + 1 }}</strong> -
                    <strong>{{ Math.min(currentPage * perPage, filteredParticipants.length) }}</strong> dari
                    <strong>{{ filteredParticipants.length }}</strong> peserta
                </span>

                <div class="pa-pagination-buttons">
                    <HcisButton
                        label="Sebelumnya"
                        size="xs"
                        variant="secondary"
                        leading-icon="chevron-left"
                        :disabled="currentPage <= 1"
                        @click="currentPage--"
                    />
                    <span class="pa-page-num">{{ currentPage }} / {{ totalPages }}</span>
                    <HcisButton
                        label="Berikutnya"
                        size="xs"
                        variant="secondary"
                        trailing-icon="chevron-right"
                        :disabled="currentPage >= totalPages"
                        @click="currentPage++"
                    />
                </div>
            </div>
        </div>

        <!-- Participant Result Detail Modal -->
        <PaResultDialog
            v-if="selectedParticipant"
            v-model="participantResultOpen"
            :participant="selectedParticipant"
            :result="participantResultState.detail.value"
            :loading="participantResultState.loading.value"
            :error="participantResultState.error.value"
            :error-status="participantResultState.errorStatus.value"
            :error-code="participantResultState.errorCode.value"
            :request-id="participantResultState.requestId.value"
            :calculation="participantResultState.hasilHitung.value"
            :recalculating="participantResultState.recalculating.value"
            :acting="participantResultState.acting.value"
            :action-error="participantResultState.actionError.value"
            :action-error-status="participantResultState.actionErrorStatus.value"
            :action-error-code="participantResultState.actionErrorCode.value"
            :action-request-id="participantResultState.actionRequestId.value"
            :action-success="participantResultState.actionSuccess.value"
            :can-calibrate="canCalibrate"
            :can-calculate="canCalculate"
            :can-publish="false"
            @retry="muatDetailHasil"
            @calibrate="kalibrasiPeserta"
            @calculate="hitungUlangPeserta"
        />

        <!--
         | Publication Dialog
         |
         | `can-publish` WAJIB dikirim. Propnya bawaan `false`, dan sampai 7 September 2026
         | dialog ini dibuka tanpa pernah mengirimnya — sehingga tombol "Terbitkan hasil
         | siap" di dalamnya tidak pernah muncul sama sekali, dan menerbitkan hasil dari
         | layar ini mustahil tanpa satu pun pesan yang menjelaskan kenapa.
         -->
        <PaPublicationDialog
            v-if="activeCycle"
            v-model="publicationOpen"
            :cycle="activeCycle"
            :summary="publicationSummary"
            :participants="participants"
            :loading="publicationState.loading.value"
            :error="publicationState.error.value"
            :error-status="publicationState.errorStatus.value"
            :error-code="publicationState.errorCode.value"
            :request-id="publicationState.requestId.value"
            :acting="publicationState.acting.value"
            :action-error="publicationState.actionError.value"
            :action-error-status="publicationState.actionErrorStatus.value"
            :action-error-code="publicationState.actionErrorCode.value"
            :action-request-id="publicationState.actionRequestId.value"
            :action-success="publicationState.actionSuccess.value"
            :action-name="publicationState.actionName.value"
            :can-publish="canPublish"
            @retry="muatRingkasanPenerbitan"
            @publish="terbitkanNilai"
            @close-publication="tutupPenerbitan"
        />

        <!-- Calculation Dialog -->
        <PaHitungSiklusDialog
            v-if="activeCycle"
            v-model="dialogHitungTerbuka"
            :cycle="activeCycle"
            :preview="pratinjauHitung"
            :loading="pratinjauHitungLoading"
            :error="pratinjauHitungError"
            :acting="calculating"
            @retry="muatPratinjauHitung"
            @jalankan="handleHitungSiklus"
        />
    </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import {
    HcisBadge,
    HcisButton,
    HcisEmptyState,
    HcisFilterBar,
    HcisHeader,
    HcisIcon,
    HcisResultState,
    HcisSkeleton,
    HcisStatCard,
    HcisStatGroup,
    HcisStatusBadge,
    HcisTable,
} from '@/components/ui';
import PaHitungSiklusDialog from './components/PaHitungSiklusDialog.vue';
import PaPublicationDialog from './components/PaPublicationDialog.vue';
import PaResultDialog from './components/PaResultDialog.vue';
import { usePaPublication } from './composables/usePaPublication.js';
import { usePaResult } from './composables/usePaResult.js';
import paApi from '../api/paApi.js';
import { cycleDariUrl } from '../utils/cycleDariUrl.js';

defineOptions({ name: 'PerformanceAppraisalHasilPage' });

const loadingSiklus = ref(false);
const loadingPeserta = ref(false);
const calculating = ref(false);
const errorPeserta = ref('');
const errorStatus = ref(0);

const siklusList = ref([]);
const selectedCycleId = ref('');
const participants = ref([]);

const searchQuery = ref('');
const activeStatusFilter = ref('ALL');
const selectedFilterValues = reactive({
    grade: '',
    id_divisi: '',
});

const currentPage = ref(1);
const perPage = 20;

const selectedParticipant = ref(null);
const participantResultOpen = ref(false);
const publicationOpen = ref(false);
const publicationSummary = ref(null);

const participantResultState = usePaResult(paApi);
const publicationState = usePaPublication(paApi);

const activeCycle = computed(() => {
    return siklusList.value.find((c) => Number(c.Id_PA_Cycle) === Number(selectedCycleId.value)) || null;
});

/* SATU SUMBER IZIN: daftar operasi yang dikirim server bersama siklusnya.

   Sebelum 7 September 2026 ketiganya disusun dari daftar status yang ditulis di sini, dan
   daftarnya keliru dalam dua arah sekaligus. Ia menyebut `CALCULATED` — status yang tidak
   pernah ada di sistem ini — dan ia TIDAK menyebut `OPEN`, padahal gerbang server
   mengizinkan hitung, kalibrasi, dan terbit pada `OPEN` maupun `CLOSED`. Akibatnya
   kemampuan menghitung peserta yang sudah lengkap selagi pengisian masih berjalan tidak
   pernah punya tombol, dan tidak ada galat apa pun yang menandainya: tombolnya sekadar
   tidak ada. */
function bolehOperasi(operasi) {
    const daftar = activeCycle.value?.operasi_boleh;

    return Array.isArray(daftar) && daftar.includes(operasi);
}

const canCalculate = computed(() => bolehOperasi('HITUNG_HASIL'));

/* PERHITUNGAN MASSAL menuntut lebih dari izinnya: siklusnya harus sudah DITUTUP.

   `HITUNG_HASIL` mengizinkan `OPEN` juga - tetapi itu izin untuk jalur PER ORANG, yang
   memang melayani siklus berjalan begitu penilai seseorang lengkap. Jalur massal menolak
   `OPEN` dengan alasan yang benar: menghitung ratusan orang yang penilainya masih berjalan
   lalu mengulang seluruhnya begitu mereka selesai adalah pekerjaan yang hasilnya dibuang
   sendiri. Menawarkan tombol yang sudah pasti ditolak berarti menyuruh admin menekannya
   untuk mengetahui sesuatu yang sudah bisa dikatakan sekarang. */
const bisaHitungMassal = computed(
    () => canCalculate.value && activeCycle.value?.Status_Cycle === 'CLOSED',
);
const canCalibrate = computed(() => bolehOperasi('KALIBRASI'));
const canPublish = computed(() => bolehOperasi('TERBITKAN'));

/* Status hasil dari server: BELUM_DIHITUNG, DIHITUNG, TERTAHAN, TERBIT. Nilai akhir = Final_Score.
   (Sampai 14 Okt 2026 halaman ini membaca 'DITERBITKAN'/'DIKALIBRASI' dan `Nilai_Akhir` yang tidak
   pernah dikirim: kartu Terbit selalu "Belum Terbit", rata-rata 0, saringan Dihitung/Diterbitkan kosong.) */
const STATUS_TERBIT = 'TERBIT';
const nilaiAkhir = (p) => p.Final_Score ?? null;
/* Grade yang DITAMPILKAN: resmi lebih dulu, sementara (kurva belum dikunci) hanya pengisi.
   Satu aturan untuk kolom, opsi saringan, saringan, dan pencarian. */
const gradeTampil = (p) => p.Kode_Grade_Final || p.Kode_Grade_Sistem || p.Grade_Sementara || '';

const calculatedCount = computed(() => participants.value.filter((p) => p.Status_Hasil === 'DIHITUNG' || p.Status_Hasil === STATUS_TERBIT || nilaiAkhir(p) != null).length);
const calibratedCount = computed(() => participants.value.filter((p) => p.Flag_Dikalibrasi === 'Y').length);
const publishedCount = computed(() => participants.value.filter((p) => p.Status_Hasil === STATUS_TERBIT).length);

const averageScore = computed(() => {
    const scores = participants.value.map((p) => Number(nilaiAkhir(p))).filter((s) => !isNaN(s) && s > 0);
    if (!scores.length) return 0;
    const sum = scores.reduce((acc, v) => acc + v, 0);
    return sum / scores.length;
});

const statusQuickChips = computed(() => [
    { label: 'Semua Peserta', value: 'ALL', count: participants.value.length },
    { label: 'Sudah Dihitung', value: 'CALCULATED', count: calculatedCount.value },
    { label: 'Dikalibrasi', value: 'CALIBRATED', count: calibratedCount.value },
    { label: 'Diterbitkan', value: 'PUBLISHED', count: publishedCount.value },
]);

const uniqueGrades = computed(() => {
    const set = new Set();
    participants.value.forEach((p) => {
        const grade = gradeTampil(p);
        if (grade) set.add(grade);
    });
    return Array.from(set).map((val) => ({ value: val, label: `Grade ${val}` }));
});

const uniqueDivisi = computed(() => {
    const set = new Set();
    participants.value.forEach((p) => {
        if (p.Nama_Divisi) set.add(p.Nama_Divisi);
    });
    return Array.from(set).map((val) => ({ value: val, label: val }));
});

const filterDefinitions = computed(() => [
    {
        key: 'grade',
        label: 'Grade Nilai',
        options: uniqueGrades.value,
        placeholder: 'Semua Grade',
    },
    {
        key: 'id_divisi',
        label: 'Divisi',
        options: uniqueDivisi.value,
        placeholder: 'Semua Divisi',
    },
]);

const filteredParticipants = computed(() => {
    let list = participants.value;

    if (activeStatusFilter.value === 'CALCULATED') {
        list = list.filter((p) => nilaiAkhir(p) != null);
    } else if (activeStatusFilter.value === 'CALIBRATED') {
        list = list.filter((p) => p.Flag_Dikalibrasi === 'Y');
    } else if (activeStatusFilter.value === 'PUBLISHED') {
        list = list.filter((p) => p.Status_Hasil === STATUS_TERBIT);
    }

    if (selectedFilterValues.grade) {
        list = list.filter((p) => gradeTampil(p) === selectedFilterValues.grade);
    }
    if (selectedFilterValues.id_divisi) {
        list = list.filter((p) => p.Nama_Divisi === selectedFilterValues.id_divisi);
    }

    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter((p) => {
            const nama = (p.Nama_Karyawan_Saat_Cycle || p.Nama_Karyawan || '').toLowerCase();
            const nik = (p.Kode_Karyawan || '').toLowerCase();
            const divisi = (p.Nama_Divisi_Saat_Cycle || p.Nama_Divisi || '').toLowerCase();
            const level = (p.Nama_Level_Saat_Cycle || p.Nama_Level || '').toLowerCase();
            const grade = gradeTampil(p).toLowerCase();
            return nama.includes(q) || nik.includes(q) || divisi.includes(q) || level.includes(q) || grade.includes(q);
        });
    }

    return list;
});

const totalPages = computed(() => Math.ceil(filteredParticipants.value.length / perPage) || 1);

const paginatedRows = computed(() => {
    const start = (currentPage.value - 1) * perPage;
    return filteredParticipants.value.slice(start, start + perPage);
});

const tableColumns = [
    { key: 'karyawan', label: 'Karyawan', minWidth: '220px' },
    { key: 'divisi', label: 'Divisi', minWidth: '160px' },
    { key: 'skor_kpi', label: 'Skor KPI', width: '100px', align: 'center' },
    { key: 'skor_kompetensi', label: 'Skor Komp.', width: '105px', align: 'center' },
    { key: 'skor_akhir', label: 'Skor Akhir', width: '110px', align: 'center' },
    { key: 'grade', label: 'Grade', width: '110px', align: 'center' },
    { key: 'status', label: 'Status Hasil', width: '130px' },
    { key: 'aksi', label: 'Aksi', align: 'right', width: '130px' },
];

async function muatSiklus() {
    loadingSiklus.value = true;
    try {
        const response = await paApi.daftarSiklus();
        const data = response?.result ?? response;
        siklusList.value = data?.siklus || [];

        /* Siklus yang diminta lewat `?cycle=` menang atas pilihan bawaan — admin yang
           datang dari daftar siklus sedang menunjuk satu siklus tertentu, dan halaman
           yang diam-diam memilih siklus lain membuatnya membaca angka yang salah. */
        const diminta = cycleDariUrl(siklusList.value);

        if (diminta) {
            selectedCycleId.value = diminta;
            await muatDataHasil();
        } else if (siklusList.value.length && !selectedCycleId.value) {
            const active = siklusList.value.find((c) => c.Status_Cycle === 'CLOSED' || c.Status_Cycle === 'CALCULATED' || c.Status_Cycle === 'PUBLISHED') || siklusList.value[0];
            selectedCycleId.value = active.Id_PA_Cycle;
            await muatDataHasil();
        }
    } catch (e) {
        errorPeserta.value = e?.message || 'Gagal memuat siklus.';
    } finally {
        loadingSiklus.value = false;
    }
}

async function muatDataHasil() {
    if (!selectedCycleId.value) return;

    loadingPeserta.value = true;
    errorPeserta.value = '';
    errorStatus.value = 0;

    try {
        const response = await paApi.daftarPeserta(selectedCycleId.value);
        const data = response?.result ?? response;
        participants.value = data?.peserta || data?.baris || [];
        currentPage.value = 1;
    } catch (e) {
        errorPeserta.value = e?.message || 'Gagal memuat daftar hasil peserta.';
        errorStatus.value = Number(e?.status || 500);
    } finally {
        loadingPeserta.value = false;
    }
}

function onCycleChange() {
    muatDataHasil();
}

function onSearchChange(val) {
    searchQuery.value = val;
    currentPage.value = 1;
}

function onStatusChipClick(val) {
    activeStatusFilter.value = val;
    currentPage.value = 1;
}

function onFilterChange({ key, value }) {
    selectedFilterValues[key] = value;
    currentPage.value = 1;
}

function resetFilters() {
    searchQuery.value = '';
    activeStatusFilter.value = 'ALL';
    selectedFilterValues.grade = '';
    selectedFilterValues.id_divisi = '';
    currentPage.value = 1;
}

/* Ukuran cicilan. Terukur +-0,5 detik per peserta, jadi 100 orang sekitar satu menit -
   cukup pendek untuk lolos batas waktu permintaan, cukup panjang supaya siklus 958 orang
   tidak menjadi sepuluh putaran perjalanan jaringan. */
const UKURAN_CICILAN_HITUNG = 100;

const dialogHitungTerbuka = ref(false);
const pratinjauHitung = ref(null);
const pratinjauHitungLoading = ref(false);
const pratinjauHitungError = ref('');
const progresHitung = reactive({ diproses: 0, dihitung: 0, tertahan: 0, gagal: 0 });

/* Pratinjau dimuat SETIAP KALI dialog dibuka, tidak di-cache: angkanya berubah setiap ada
   penilai yang mengirim, dan konfirmasi yang menyebut angka basi lebih buruk daripada yang
   tidak menyebut angka sama sekali. */
async function bukaDialogHitung() {
    dialogHitungTerbuka.value = true;
    await muatPratinjauHitung();
}

async function muatPratinjauHitung() {
    if (!selectedCycleId.value) return;

    pratinjauHitungLoading.value = true;
    pratinjauHitungError.value = '';

    try {
        pratinjauHitung.value = await paApi.pratinjauHitungSiklus(selectedCycleId.value);
    } catch (e) {
        pratinjauHitung.value = null;
        pratinjauHitungError.value = e?.message || 'Pratinjau perhitungan gagal dimuat.';
    } finally {
        pratinjauHitungLoading.value = false;
    }
}

/*
 | DICICIL SAMPAI HABIS, bukan satu permintaan.
 |
 | Sampai 7 September 2026 layar ini memanggil `hitungSiklus` tanpa `batas` sama sekali,
 | sehingga seluruh peserta dijalankan dalam satu permintaan HTTP. Pada 958 peserta itu
 | belasan menit - jauh melewati batas waktu permintaan mana pun - dan gejalanya bukan galat
 | melainkan tombol yang ditekan lalu tidak terjadi apa-apa.
 */
async function handleHitungSiklus(lingkup = 'SEMUA') {
    if (!selectedCycleId.value || calculating.value) return;

    calculating.value = true;
    Object.assign(progresHitung, { diproses: 0, dihitung: 0, tertahan: 0, gagal: 0 });
    dialogHitungTerbuka.value = false;

    try {
        let mulaiDariId = null;

        for (let putaran = 0; putaran < 60; putaran++) {
            const hasil = await paApi.hitungSiklus(selectedCycleId.value, {
                batas: UKURAN_CICILAN_HITUNG,
                mulaiDariId,
                lingkup,
            });

            progresHitung.diproses += Number(hasil?.diproses) || 0;
            progresHitung.dihitung += Number(hasil?.dihitung) || 0;
            progresHitung.tertahan += Number(hasil?.tertahan) || 0;
            progresHitung.gagal += (hasil?.gagal || []).length;

            if (hasil?.selesai || !hasil?.terakhir_id) break;

            mulaiDariId = hasil.terakhir_id;
        }

        await muatDataHasil();
    } catch (e) {
        alert(e?.message || 'Gagal menghitung nilai siklus.');
    } finally {
        calculating.value = false;
    }
}

async function openParticipantDetail(participant) {
    selectedParticipant.value = participant;
    participantResultOpen.value = true;
    await muatDetailHasil();
}

async function muatDetailHasil() {
    if (!selectedParticipant.value?.Id_PA_Participant) return;
    await participantResultState.muat(selectedParticipant.value.Id_PA_Participant);
}

async function kalibrasiPeserta(data) {
    const pid = selectedParticipant.value?.Id_PA_Participant;
    const resultId = data?.resultId || selectedParticipant.value?.Id_PA_Result;
    const payload = data?.payload || data;
    if (!pid || !resultId) return;
    await participantResultState.kalibrasi(pid, resultId, payload);
    await muatDataHasil();
}

async function hitungUlangPeserta() {
    const pid = selectedParticipant.value?.Id_PA_Participant;
    if (!pid) return;
    await participantResultState.hitung(pid);
    await muatDataHasil();
}

async function openPublishDialog() {
    if (!selectedCycleId.value) return;
    publicationOpen.value = true;
    await muatRingkasanPenerbitan();
}

async function muatRingkasanPenerbitan() {
    if (!selectedCycleId.value) return;
    const res = await publicationState.muat(selectedCycleId.value);
    publicationSummary.value = res;
}

async function terbitkanNilai(payload) {
    if (!selectedCycleId.value) return;
    // Ditutup hanya bila berhasil, supaya penolakan server tetap terbaca di dialog.
    const hasil = await publicationState.terbitkan(selectedCycleId.value, payload);
    if (!hasil?.data) return;
    publicationOpen.value = false;
    await muatDataHasil();
}

async function tutupPenerbitan() {
    if (!selectedCycleId.value) return;
    const hasil = await publicationState.tutup(selectedCycleId.value);
    if (!hasil?.data) return;
    publicationOpen.value = false;
    await muatSiklus();
    await muatDataHasil();
}

function getStatusSentiment(status) {
    if (status === 'PUBLISHED') return 'success';
    if (status === 'CALCULATED') return 'purple';
    if (status === 'CLOSED') return 'brand';
    return 'neutral';
}

function formatNumber(val) {
    if (val === null || val === undefined || isNaN(Number(val))) return '—';
    return Number(val).toFixed(2);
}

function formatAngka(num) {
    return Number(num || 0).toLocaleString('id-ID');
}

onMounted(() => {
    muatSiklus();
});
</script>

<style scoped>
.pa-hasil-page {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
    padding: 0 0 5rem 0;
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
}

.pa-hasil-header-actions {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.pa-cycle-select-wrap {
    display: inline-flex;
    align-items: center;
}

.pa-cycle-select {
    min-height: 2.15rem;
    padding: 0.35rem 0.65rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-md, 8px);
    background: #ffffff;
    color: var(--hcis-text-strong, #0f172a);
    font: inherit;
    font-size: 0.78rem;
    font-weight: 700;
}

.pa-admin-switcher {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.25rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(241, 245, 249, 0.75);
    overflow-x: auto;
}

.pa-admin-switcher__item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.65rem;
    border-radius: var(--hcis-radius-sm, 6px);
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.74rem;
    font-weight: 650;
    text-decoration: none;
    transition: all 150ms ease;
    white-space: nowrap;
}

.pa-admin-switcher__item:hover {
    color: #4f46e5;
    background: rgba(255, 255, 255, 0.6);
}

.pa-admin-switcher__item.active {
    background: #ffffff;
    color: #4338ca;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.pa-hasil-stats {
    margin: 0;
}

/* Gateway Navigation Section */
.pa-gateways-section {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pa-gateways-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.75rem;
}

.pa-gateway-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.95rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    text-decoration: none;
    transition: all 180ms ease;
}

.pa-gateway-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
    border-color: #7c3aed;
}

.pa-gateway-card__icon {
    display: grid;
    place-items: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: var(--hcis-radius-md, 8px);
    flex-shrink: 0;
}

.pa-gateway-card--kalibrasi .pa-gateway-card__icon {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
}

.pa-gateway-card--hav .pa-gateway-card__icon {
    background: rgba(99, 102, 241, 0.12);
    color: #4f46e5;
}

.pa-gateway-card--distribusi .pa-gateway-card__icon {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.pa-gateway-card--riwayat .pa-gateway-card__icon {
    background: rgba(100, 116, 139, 0.12);
    color: #475569;
}

.pa-gateway-card__text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex: 1;
}

.pa-gateway-card__text h4 {
    margin: 0;
    font-size: 0.84rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-gateway-card__text p {
    margin: 0;
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.35;
}

.pa-gateway-card__arrow {
    color: var(--hcis-text-muted, #94a3b8);
    transition: transform 150ms ease;
}

.pa-gateway-card:hover .pa-gateway-card__arrow {
    color: #4f46e5;
    transform: translateX(2px);
}

.pa-eyebrow {
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.08em;
}

/* Controls */
.pa-hasil-controls {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

/* Table Wrap */
.pa-hasil-table-wrap {
    width: 100%;
    min-width: 0;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-xl, 16px);
    overflow: hidden;
    background: rgba(255, 255, 255, 0.85);
}

.pa-table-person-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-table-person-cell strong {
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.82rem;
}

.pa-table-person-cell small {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.7rem;
}

.pa-table-divisi-text {
    font-size: 0.76rem;
    color: var(--hcis-text-muted, #475569);
}

.pa-score-text {
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.8rem;
    color: var(--hcis-text-strong, #334155);
}

.pa-score-final {
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.85rem;
    color: #4f46e5;
}

.pa-grade-cell {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.15rem;
}

.pa-grade-badge {
    padding: 0.15rem 0.5rem;
    border-radius: var(--hcis-radius-xs, 4px);
    background: #f1f5f9;
    color: var(--hcis-text-strong, #0f172a);
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.76rem;
    font-weight: 800;
}

.pa-grade-badge--calibrated {
    background: rgba(245, 158, 11, 0.15);
    color: #b45309;
}

.pa-calibrated-tag {
    font-size: 0.62rem;
    color: #d97706;
    font-weight: 700;
}

.pa-row-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.25rem;
}

/* Pagination */
.pa-hasil-pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
    padding: 0.75rem 1rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(248, 250, 252, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.pa-pagination-info {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-pagination-buttons {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-page-num {
    font-size: 0.72rem;
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

/* Skeletons */
.pa-hasil-loading {
    padding: 1rem 0;
}

.pa-hasil-skeleton-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.9rem;
}

.pa-hasil-skeleton-card {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
}

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

@media (max-width: 1024px) {
    .pa-gateways-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .pa-hasil-page {
        padding: 0 0 5rem 0;
    }
    .pa-gateways-grid {
        grid-template-columns: 1fr;
    }
}

.pa-hasil-pindah {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 1rem;
    padding: 0.7rem 1rem;
    border: 1px solid #c7d2fe;
    border-radius: 12px;
    background: #eef2ff;
    color: #3730a3;
    font-size: 0.82rem;
    text-decoration: none;
}

.pa-hasil-pindah span {
    font-weight: 700;
}
</style>
