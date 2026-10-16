<template>
    <main class="pa-riwayat-page">
        <!-- Modern Executive Glass Header (Aligned with PA Standard) -->
        <HcisHeader
            :title="isMobile ? 'Riwayat Penilaian' : 'Riwayat Penilaian & Lini Masa Kinerja'"
            :kicker="isMobile ? 'PA · RIWAYAT' : 'PERFORMANCE APPRAISAL · AUDIT & TREN HISTORIS'"
            :subtitle="isMobile
                ? 'Rekam jejak penilaian karyawan lintas tahun & tren nilai organisasi.'
                : 'Penelusuran rekam jejak penilaian karyawan lintas tahun dan lintas cycle, lengkap dengan visualisasi tren nilai organisasi, audit kalibrasi, dan status penerbitan.'"
            icon="clock-history"
            sentiment="brand"
            variant="glass"
            :size="isMobile ? 'sm' : 'md'"
            animated
            :clamp="isMobile ? 1 : null"
            :loading="loadingRiwayat && !riwayatRows.length"
            class="pa-main-header"
        >
            <template #actions>
                <div class="pa-riwayat-header-actions">
                    <HcisButton
                        label="Segarkan"
                        variant="glass"
                        :size="isMobile ? 'xs' : 'sm'"
                        leading-icon="arrow-clockwise"
                        :icon-only="isMobile"
                        :loading="loadingRiwayat || audit.loading.value"
                        aria-label="Segarkan data riwayat"
                        title="Segarkan data riwayat"
                        @click="segarkanHalaman"
                    />

                    <HcisButton
                        href="/performance-appraisal/dashboard"
                        :label="isMobile ? 'Tower' : 'Control Tower'"
                        variant="secondary"
                        :size="isMobile ? 'xs' : 'sm'"
                        leading-icon="speedometer2"
                        aria-label="Buka Dashboard PA Control Tower"
                        title="Dashboard PA Control Tower"
                    />
                </div>
            </template>

            <!-- Bottom Header Integrated Tabs Navigation -->
            <template #tabs>
                <HcisTabs
                    v-model="tabAktif"
                    :tabs="daftarTab"
                    variant="header"
                    size="sm"
                    :block="!isMobile"
                    aria-label="Navigasi Riwayat & Audit"
                    @update:model-value="gantiTab"
                />
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
                    <a href="/performance-appraisal/hav-map" class="pa-admin-switcher__item">
                        <HcisIcon name="grid-3x3" size="2xs" />
                        <span>9-Box HAV Map</span>
                    </a>
                    <a href="/performance-appraisal/distribusi" class="pa-admin-switcher__item">
                        <HcisIcon name="bar-chart-fill" size="2xs" />
                        <span>Distribusi Performance</span>
                    </a>
                    <a href="/performance-appraisal/riwayat" class="pa-admin-switcher__item active pa-admin-switcher__item--active">
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

        <!-- EXECUTIVE TL;DR COCKPIT (4 CRYSTAL STAT CARDS - 2x2 ULTRA-COMPACT ON MOBILE) -->
        <div v-if="loadingRiwayat && !riwayatRows.length" class="pa-skeleton-cockpit">
            <HcisSkeleton v-for="n in 4" :key="`skel-rw-${n}`" :height="isMobile ? '3.8rem' : '5.5rem'" rounded="md" />
        </div>
        <div v-else class="pa-cockpit-section" aria-label="Ringkasan Riwayat Penilaian">
            <HcisStatGroup
                :stats="riwayatStats"
                :cols="isMobile ? 2 : 4"
                gap="xs"
                :layout="isMobile ? 'dense' : 'compact'"
                appearance="crystal"
                class="pa-stat-group"
                @item-click="onStatCardItemClick"
            />
        </div>

        <!-- ============================================================
             SUB-TAB 1: RINGKASAN & ANALISIS TREN (CHARTS & ANALYTICS)
             ============================================================ -->
        <div v-show="tabAktif === 'RINGKASAN'" class="pa-tab-pane">
            <!-- 1. Executive Performance Insights Strip -->
            <div v-if="trendData.length" class="pa-cockpit-section mb-1" aria-label="Insight Performa Organisasi">
                <HcisStatGroup
                    :stats="trendInsightStats"
                    :cols="isMobile ? 2 : 4"
                    gap="xs"
                    :layout="isMobile ? 'dense' : 'compact'"
                    appearance="crystal"
                    class="pa-stat-group"
                />
            </div>

            <!-- 2. Main Highlight Visualizer: Tren Rata-rata Nilai Akhir Organisasi (Area Chart) -->
            <div class="pa-main-chart-card">
                <HcisChart
                    v-if="trendChartCategories.length"
                    type="area"
                    title="Tren Rata-rata Nilai Akhir Organisasi"
                    kicker="ORGANIZATION PERFORMANCE TREND"
                    description="Pergerakan nilai rata-rata akhir korporasi lintas assessment cycle diurutkan secara kronologis."
                    icon="graph-up"
                    sentiment="brand"
                    variant="glass"
                    :height="isMobile ? 240 : 320"
                    :categories="trendChartCategories"
                    :series="trendChartSeries"
                    :metric-value="latestTrendScore ? `${latestTrendScore}` : ''"
                    metric-label="Rata-rata Siklus Terakhir"
                    :metric-trend="trendDeltaText"
                    :metric-trend-direction="trendDeltaDirection"
                    allow-table-toggle
                    allow-export
                    smooth
                />
                <div v-else-if="loadingRiwayat" class="pa-chart-loading-box">
                    <HcisSkeleton width="200px" height="1.2rem" rounded="sm" class="mb-2" />
                    <HcisSkeleton width="100%" height="260px" rounded="md" />
                </div>
                <div v-else class="pa-chart-empty-box">
                    <HcisEmptyState
                        title="Belum ada data tren organisasi"
                        description="Grafik tren akan terisi otomatis saat terdapat siklus penilaian dengan kalkulasi skor akhir."
                        icon="graph-up"
                    />
                </div>
            </div>

            <!-- 3. Supporting Analytics Grid: Volume Peserta & Distribusi Grade -->
            <div class="pa-supporting-charts-grid">
                <!-- Supporting Chart 1: Volume Peserta Lintas Cycle -->
                <div class="pa-support-chart-item">
                    <HcisChart
                        v-if="trendChartCategories.length"
                        type="bar"
                        title="Volume Partisipasi Peserta"
                        kicker="PARTICIPANT VOLUME"
                        description="Jumlah karyawan yang dievaluasi pada setiap siklus penilaian."
                        icon="people-fill"
                        sentiment="purple"
                        variant="glass"
                        :height="isMobile ? 220 : 280"
                        :categories="trendChartCategories"
                        :series="participantVolumeSeries"
                        :metric-value="totalPesertaAllCycles ? formatAngka(totalPesertaAllCycles) : ''"
                        metric-label="Total Evaluasi Tercatat"
                        allow-table-toggle
                        allow-export
                    />
                </div>

                <!-- Supporting Chart 2: Sebaran Grade Historis -->
                <div class="pa-support-chart-item">
                    <HcisChart
                        v-if="gradeDonutData.length"
                        type="donut"
                        title="Distribusi Sebaran Grade Historis"
                        kicker="GRADE COMPOSITION"
                        description="Proporsi hasil evaluasi berdasarkan perolehan grade akhir karyawan."
                        icon="pie-chart-fill"
                        sentiment="teal"
                        variant="glass"
                        :height="isMobile ? 220 : 280"
                        :data="gradeDonutData"
                        allow-table-toggle
                        allow-export
                    />
                </div>
            </div>

            <!-- 4. Historical Cycle Summary & Comparative Ledger -->
            <section v-if="trendData.length" class="pa-riwayat-trend-card" aria-label="Tren Nilai Rata-rata Lintas Cycle">
                <div class="pa-riwayat-trend-card__head">
                    <div class="pa-trend-head-left">
                        <div class="pa-trend-icon-box">
                            <HcisIcon name="calendar-check" size="sm" color="purple" />
                        </div>
                        <div>
                            <span class="pa-eyebrow">RINGKASAN LINTAS SIKLUS</span>
                            <h4 class="pa-trend-title">Daftar Siklus Penilaian Terdata</h4>
                            <p class="pa-trend-subtitle">Analisis komparatif kinerja dan rekam jejak historis per assessment cycle.</p>
                        </div>
                    </div>

                    <div class="pa-trend-head-right">
                        <HcisBadge
                            :label="`${trendData.length} Assessment Cycle Terdata`"
                            variant="purple"
                            appearance="glass"
                            size="sm"
                            dot
                        />

                        <!-- View switch for cycle section -->
                        <div class="pa-view-switch">
                            <button
                                type="button"
                                class="pa-view-switch-btn"
                                :class="{ 'is-active': trendViewMode === 'grid' }"
                                title="Tampilan Kartu"
                                @click="trendViewMode = 'grid'"
                            >
                                <HcisIcon name="grid-fill" size="2xs" />
                                <span>Kartu</span>
                            </button>
                            <button
                                type="button"
                                class="pa-view-switch-btn"
                                :class="{ 'is-active': trendViewMode === 'table' }"
                                title="Tampilan Tabel Perbandingan"
                                @click="trendViewMode = 'table'"
                            >
                                <HcisIcon name="table" size="2xs" />
                                <span>Tabel</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mode A: Grid Cards -->
                <div v-if="trendViewMode === 'grid'" class="pa-trend-items-grid">
                    <div
                        v-for="t in trendData"
                        :key="t.id_cycle || t.cycle_id || t.nama_cycle || t.tahun"
                        class="pa-trend-item-card"
                    >
                        <div class="pa-trend-item-top">
                            <span class="pa-trend-cycle-name" :title="t.nama_cycle || t.kode_cycle || t.tahun">
                                {{ t.nama_cycle || t.kode_cycle || t.tahun }}
                            </span>
                            <span v-if="t.tahun" class="pa-trend-year-pill font-num">{{ t.tahun }}</span>
                        </div>

                        <div class="pa-trend-score-row">
                            <strong class="pa-trend-score font-num">{{ (t.rata_rata ?? t.rerata) != null ? Number(t.rata_rata ?? t.rerata).toFixed(2) : '—' }}</strong>
                        </div>

                        <!-- Score Range Bar if min/max exists -->
                        <div v-if="t.tertinggi != null || t.terendah != null" class="pa-trend-range-box">
                            <div class="pa-trend-range-labels font-num">
                                <span>Min: {{ t.terendah != null ? Number(t.terendah).toFixed(1) : '—' }}</span>
                                <span>Max: {{ t.tertinggi != null ? Number(t.tertinggi).toFixed(1) : '—' }}</span>
                            </div>
                            <div class="pa-trend-range-track">
                                <div
                                    class="pa-trend-range-bar"
                                    :style="{
                                        left: `${persenSkalaTren(t.terendah ?? 0)}%`,
                                        width: `${Math.max(6, persenSkalaTren(t.tertinggi ?? 0) - persenSkalaTren(t.terendah ?? 0))}%`
                                    }"
                                />
                            </div>
                        </div>

                        <div class="pa-trend-item-foot">
                            <small class="pa-trend-count font-num">
                                <HcisIcon name="people-fill" size="2xs" />
                                {{ formatAngka(t.jumlah ?? t.jumlah_peserta) }} peserta
                            </small>

                            <button
                                v-if="t.id_cycle || t.cycle_id"
                                type="button"
                                class="pa-trend-link-btn"
                                title="Buka arsip hasil peserta siklus ini"
                                @click="filterDanBukaArsipCycle(String(t.id_cycle || t.cycle_id))"
                            >
                                Saring di Arsip &rarr;
                            </button>
                        </div>

                        <!-- Action shortcuts footer -->
                        <div v-if="t.id_cycle || t.cycle_id" class="pa-trend-item-actions">
                            <button
                                type="button"
                                class="pa-trend-action-chip"
                                title="Buka Jejak Audit untuk siklus ini"
                                @click="bukaAuditCycle(String(t.id_cycle || t.cycle_id))"
                            >
                                <HcisIcon name="fingerprint" size="2xs" />
                                <span>Audit</span>
                            </button>
                            <a
                                :href="`/performance-appraisal/hav-map?cycle=${t.id_cycle || t.cycle_id}`"
                                class="pa-trend-action-chip"
                                title="Buka 9-Box HAV Map untuk siklus ini"
                            >
                                <HcisIcon name="grid-3x3" size="2xs" />
                                <span>9-Box</span>
                            </a>
                            <a
                                :href="`/performance-appraisal/distribusi?cycle=${t.id_cycle || t.cycle_id}`"
                                class="pa-trend-action-chip"
                                title="Buka Distribusi Nilai untuk siklus ini"
                            >
                                <HcisIcon name="bar-chart-fill" size="2xs" />
                                <span>Distribusi</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Mode B: Comparative Table Ledger -->
                <div v-else class="pa-trend-table-wrap">
                    <table class="pa-trend-comparative-table">
                        <thead>
                            <tr>
                                <th style="min-width: 220px;">Assessment Cycle</th>
                                <th style="width: 100px;">Tahun</th>
                                <th style="width: 130px;">Partisipasi</th>
                                <th style="width: 160px;">Rata-rata Akhir</th>
                                <th style="min-width: 160px;">Rentang Skor (Min - Max)</th>
                                <th style="min-width: 240px; text-align: right;">Aksi &amp; Telusuri</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in trendData" :key="`tbl-${t.id_cycle || t.cycle_id || t.nama_cycle || t.tahun}`">
                                <td>
                                    <div class="pa-trend-table-cycle">
                                        <strong class="pa-trend-table-cycle-name">{{ t.nama_cycle || t.kode_cycle }}</strong>
                                        <small class="pa-trend-table-cycle-code font-num">{{ t.kode_cycle }}</small>
                                    </div>
                                </td>
                                <td>
                                    <span class="pa-trend-year-pill font-num">{{ t.tahun || '—' }}</span>
                                </td>
                                <td>
                                    <span class="pa-trend-count font-num">
                                        <HcisIcon name="people-fill" size="2xs" />
                                        {{ formatAngka(t.jumlah ?? t.jumlah_peserta) }} orang
                                    </span>
                                </td>
                                <td>
                                    <div class="pa-trend-score-cell">
                                        <strong class="pa-trend-score-sm font-num">{{ (t.rata_rata ?? t.rerata) != null ? Number(t.rata_rata ?? t.rerata).toFixed(2) : '—' }}</strong>
                                    </div>
                                </td>
                                <td>
                                    <div v-if="t.tertinggi != null || t.terendah != null" class="pa-trend-table-range">
                                        <span class="font-num">{{ t.terendah != null ? Number(t.terendah).toFixed(1) : '—' }}</span>
                                        <span class="pa-range-sep">&rarr;</span>
                                        <span class="font-num font-bold">{{ t.tertinggi != null ? Number(t.tertinggi).toFixed(1) : '—' }}</span>
                                    </div>
                                    <span v-else class="text-slate-400 text-xs">—</span>
                                </td>
                                <td style="text-align: right;">
                                    <div class="pa-trend-table-actions">
                                        <HcisButton
                                            label="Telusuri Arsip"
                                            variant="primary"
                                            appearance="subtle"
                                            size="xs"
                                            leading-icon="journal-bookmark-fill"
                                            @click="filterDanBukaArsipCycle(String(t.id_cycle || t.cycle_id))"
                                        />
                                        <HcisButton
                                            label="Audit"
                                            variant="secondary"
                                            appearance="subtle"
                                            size="xs"
                                            leading-icon="fingerprint"
                                            @click="bukaAuditCycle(String(t.id_cycle || t.cycle_id))"
                                        />
                                        <a
                                            :href="`/performance-appraisal/hav-map?cycle=${t.id_cycle || t.cycle_id}`"
                                            class="pa-table-quick-link"
                                            title="Buka 9-Box HAV Map"
                                        >
                                            <HcisIcon name="grid-3x3" size="2xs" />
                                        </a>
                                        <a
                                            :href="`/performance-appraisal/distribusi?cycle=${t.id_cycle || t.cycle_id}`"
                                            class="pa-table-quick-link"
                                            title="Buka Distribusi Nilai"
                                        >
                                            <HcisIcon name="bar-chart-fill" size="2xs" />
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- ============================================================
             SUB-TAB 2: REKAM JEJAK HASIL KARYAWAN (ARSIP HASIL)
             ============================================================ -->
        <div v-show="tabAktif === 'ARSIP'" class="pa-tab-pane">
            <!-- Contextual Handoff Alert if Cycle is selected -->
            <div v-if="selectedFilterValues.cycle_id" class="pa-cycle-context-alert">
                <div class="pa-cycle-context-left">
                    <div class="pa-context-icon-box">
                        <HcisIcon name="info-circle-fill" size="xs" color="brand" />
                    </div>
                    <div class="pa-context-info">
                        <strong>Filter Aktif: {{ labelSelectedCycle }}</strong>
                        <span>Melihat rekam jejak penilaian untuk siklus ini. Anda juga dapat meninjau kronologi keputusan komite pada tab Jejak Audit.</span>
                    </div>
                </div>
                <HcisButton
                    label="Buka Jejak Audit Cycle Ini &rarr;"
                    variant="brand"
                    appearance="subtle"
                    size="xs"
                    @click="gantiTab('AUDIT')"
                />
            </div>

            <!-- Standard Design System Filter Bar -->
            <HcisFilterBar
                :search="searchQuery"
                search-placeholder="Cari nama karyawan, NIK, divisi, jabatan, atau nama cycle..."
                :quick-chips="statusQuickChips"
                :active-chip-value="activeStatusChip"
                :filters="daftarFilterRiwayat"
                :filter-values="selectedFilterValues"
                :collapsible="true"
                show-view-toggle
                :view-mode="viewMode"
                :results-count="totalRecords"
                variant="card"
                sentiment="brand"
                size="sm"
                glow
                class="pa-riwayat-filter-bar mb-3"
                @update:search="onSearchChange"
                @chip-click="onStatusChipClick"
                @filter-change="onFilterChange"
                @update:view-mode="viewMode = $event"
                @reset="resetFilters"
            />

            <!-- Error State -->
            <HcisResultState
                v-if="errorRiwayat"
                :status="errorStatus === 403 ? 'warning' : 'danger'"
                variant="flat"
                :title="errorStatus === 403 ? 'Akses ditolak' : 'Gagal memuat riwayat penilaian'"
                :description="errorRiwayat"
                primary-text="Coba Lagi"
                primary-icon="arrow-clockwise"
                :show-retry="errorStatus !== 403"
                @primary-click="muatDataRiwayat"
            />

            <!-- Loading State -->
            <div v-else-if="loadingRiwayat && !riwayatRows.length" class="pa-riwayat-loading">
                <div class="pa-riwayat-skeleton-grid">
                    <div v-for="n in 6" :key="`skel-riw-${n}`" class="pa-riwayat-skeleton-card">
                        <HcisSkeleton width="180px" height="1.1rem" rounded="sm" />
                        <HcisSkeleton width="100%" height="2rem" rounded="md" />
                        <HcisSkeleton width="90px" height="0.8rem" rounded="sm" />
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <HcisEmptyState
                v-else-if="!riwayatRows.length"
                title="Tidak ada riwayat penilaian yang cocok"
                description="Tidak ditemukan rekam jejak penilaian kinerja yang sesuai dengan saringan atau kata kunci pencarian."
                icon="clock-history"
            >
                <template v-if="searchQuery || selectedFilterValues.tahun || selectedFilterValues.cycle_id || selectedFilterValues.kode_grade || selectedFilterValues.status_hasil || activeStatusChip !== 'ALL'" #actions>
                    <HcisButton
                        label="Atur Ulang Saringan"
                        variant="secondary"
                        size="xs"
                        leading-icon="arrow-counterclockwise"
                        @click="resetFilters"
                    />
                </template>
            </HcisEmptyState>

            <!-- Loaded Content -->
            <div v-else class="pa-riwayat-content-area">
                <!-- Mode 1: Table View -->
                <div v-if="viewMode === 'table'" class="pa-riwayat-table-container">
                    <div class="pa-table-count-strip">
                        <span>Menampilkan <strong>{{ formatAngka(riwayatRows.length) }}</strong> baris rekam jejak (Halaman <strong>{{ currentPage }}</strong> dari <strong>{{ totalPages }}</strong>)</span>
                    </div>

                    <div class="pa-table-scroll-wrap">
                        <table class="pa-riwayat-main-table">
                            <thead>
                                <tr>
                                    <th style="min-width: 220px;">Karyawan Dinilai</th>
                                    <th style="min-width: 180px;">Jabatan &amp; Divisi</th>
                                    <th style="min-width: 160px;">Assessment Cycle</th>
                                    <th style="min-width: 150px;">Komponen Nilai</th>
                                    <th style="width: 140px;">Skor &amp; Grade</th>
                                    <th style="min-width: 140px;">Kuadran HAV</th>
                                    <th style="width: 120px;">Status</th>
                                    <th style="width: 100px; text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="r in riwayatRows"
                                    :key="r.id_unique"
                                    class="pa-riwayat-tr--interactive"
                                    tabindex="0"
                                    :title="`Buka rincian penilaian ${r.Nama_Karyawan_Saat_Cycle || r.Nama_Karyawan}`"
                                    @click="openParticipantModal(r)"
                                    @keydown.enter="openParticipantModal(r)"
                                >
                                    <!-- Person -->
                                    <td>
                                        <div class="pa-table-person-cell">
                                            <HcisAvatar
                                                :name="r.Nama_Karyawan_Saat_Cycle || r.Nama_Karyawan"
                                                size="xs"
                                                shape="circle"
                                                variant="brand"
                                            />
                                            <div class="pa-person-meta">
                                                <strong class="pa-person-name">{{ r.Nama_Karyawan_Saat_Cycle || r.Nama_Karyawan }}</strong>
                                                <div class="pa-person-sub-row">
                                                    <span class="pa-nik-pill font-num">{{ r.Kode_Karyawan || '—' }}</span>
                                                    <span v-if="r.Nama_Level_Saat_Cycle || r.Nama_Level" class="pa-level-pill">
                                                        {{ r.Nama_Level_Saat_Cycle || r.Nama_Level }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Position & Division -->
                                    <td>
                                        <div class="pa-table-job-cell">
                                            <strong class="pa-job-title">{{ r.Nama_Jabatan_Saat_Cycle || r.Nama_Jabatan || '—' }}</strong>
                                            <small class="pa-job-div">{{ r.Nama_Divisi_Saat_Cycle || r.Nama_Divisi || '—' }}</small>
                                        </div>
                                    </td>

                                    <!-- Cycle & Year -->
                                    <td>
                                        <div class="pa-table-cycle-cell">
                                            <strong class="pa-cycle-name">{{ r.Nama_Cycle || r.Kode_Cycle }}</strong>
                                            <span class="pa-cycle-year-badge font-num">Tahun {{ r.Tahun }}</span>
                                        </div>
                                    </td>

                                    <!-- Component Scores -->
                                    <td>
                                        <div class="pa-pillar-scores-cell">
                                            <div class="pa-score-row">
                                                <span class="pa-score-tag is-kpi">KPI</span>
                                                <strong class="pa-score-val font-num">{{ formatNumber(r.Nilai_KPI) }}</strong>
                                            </div>
                                            <div class="pa-score-row">
                                                <span class="pa-score-tag is-komp">Komp</span>
                                                <strong class="pa-score-val font-num">{{ formatNumber(r.Nilai_Kompetensi) }}</strong>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Final Score & Grade -->
                                    <td>
                                        <div class="pa-final-grade-cell">
                                            <span class="pa-final-num font-num">{{ formatNumber(r.Final_Score) }}</span>
                                            <div class="pa-grade-badge-wrap">
                                                <HcisBadge
                                                    v-if="r.Kode_Grade_Final || r.Kode_Grade_Sistem"
                                                    :label="`Grade ${displayGrade(r.Kode_Grade_Final || r.Kode_Grade_Sistem)}`"
                                                    :variant="getGradeBadgeVariant(r.Kode_Grade_Final || r.Kode_Grade_Sistem)"
                                                    appearance="glass"
                                                    size="xs"
                                                />
                                                <span v-else class="pa-no-grade-text">—</span>

                                                <span v-if="r.dikalibrasi || (r.Kode_Grade_Final && r.Kode_Grade_Sistem && r.Kode_Grade_Final !== r.Kode_Grade_Sistem)" class="pa-calibrated-tag">
                                                    Dikalibrasi
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- HAV Quadrant -->
                                    <td>
                                        <span v-if="r.Nama_Kuadran" class="pa-quadrant-tag">
                                            {{ r.Nama_Kuadran }} (Box {{ r.Nomor_Box || '-' }})
                                        </span>
                                        <span v-else class="text-slate-400 text-xs">—</span>
                                    </td>

                                    <!-- Status -->
                                    <td>
                                        <HcisStatusBadge
                                            v-if="r.Status_Hasil === 'DITERBITKAN' || r.Status_Hasil === 'TERBIT'"
                                            status="ACTIVE"
                                            label="Diterbitkan"
                                            size="xs"
                                        />
                                        <HcisBadge
                                            v-else-if="r.Status_Hasil"
                                            :label="r.Status_Hasil"
                                            variant="warning"
                                            appearance="subtle"
                                            size="xs"
                                            dot
                                        />
                                        <span v-else class="text-slate-400 text-xs">—</span>
                                    </td>

                                    <!-- Action -->
                                    <td style="text-align: right;" @click.stop>
                                        <HcisButton
                                            label="Rincian"
                                            variant="secondary"
                                            appearance="subtle"
                                            size="xs"
                                            leading-icon="eye"
                                            @click="openParticipantModal(r)"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Mode 2: Grid Cards View -->
                <div v-else class="pa-riwayat-cards-grid">
                    <HcisCard
                        v-for="r in riwayatRows"
                        :key="`card-${r.id_unique}`"
                        variant="glass"
                        :sentiment="r.Status_Hasil === 'DITERBITKAN' || r.Status_Hasil === 'TERBIT' ? 'success' : 'brand'"
                        :size="isMobile ? 'xs' : 'sm'"
                        glow
                        class="pa-riwayat-hcis-card"
                    >
                        <template #header>
                            <div class="pa-card-header-inner">
                                <div class="pa-card-person-flex">
                                    <HcisAvatar
                                        :name="r.Nama_Karyawan_Saat_Cycle || r.Nama_Karyawan"
                                        size="xs"
                                        shape="circle"
                                        variant="brand"
                                    />
                                    <div class="pa-card-person-meta">
                                        <button
                                            type="button"
                                            class="pa-card-person-link"
                                            @click.stop="openParticipantModal(r)"
                                        >
                                            {{ r.Nama_Karyawan_Saat_Cycle || r.Nama_Karyawan }}
                                        </button>
                                        <span class="pa-card-person-sub font-num">
                                            {{ r.Kode_Karyawan }} &bull; {{ r.Nama_Level_Saat_Cycle || r.Nama_Level || '-' }}
                                        </span>
                                    </div>
                                </div>

                                <HcisStatusBadge
                                    v-if="r.Status_Hasil === 'DITERBITKAN' || r.Status_Hasil === 'TERBIT'"
                                    status="ACTIVE"
                                    label="Terbit"
                                    size="xs"
                                />
                                <HcisBadge
                                    v-else-if="r.Status_Hasil"
                                    :label="r.Status_Hasil"
                                    variant="warning"
                                    appearance="subtle"
                                    size="xs"
                                    dot
                                />
                            </div>
                        </template>

                        <div class="pa-card-body-content">
                            <!-- Job & Cycle Info -->
                            <div class="pa-card-meta-row">
                                <span class="pa-card-job-pill">{{ r.Nama_Jabatan_Saat_Cycle || r.Nama_Jabatan || '—' }}</span>
                                <span class="pa-card-cycle-pill font-num">{{ r.Nama_Cycle || r.Kode_Cycle }} ({{ r.Tahun }})</span>
                            </div>

                            <!-- 3-Column Score Strip -->
                            <div class="pa-card-scores-strip">
                                <div class="pa-score-col">
                                    <span class="pa-score-col-label">KPI</span>
                                    <strong class="pa-score-col-val font-num">{{ formatNumber(r.Nilai_KPI) }}</strong>
                                </div>
                                <div class="pa-score-col">
                                    <span class="pa-score-col-label">Kompetensi</span>
                                    <strong class="pa-score-col-val font-num">{{ formatNumber(r.Nilai_Kompetensi) }}</strong>
                                </div>
                                <div class="pa-score-col is-final">
                                    <span class="pa-score-col-label">Skor Akhir</span>
                                    <strong class="pa-score-col-val font-num text-brand">{{ formatNumber(r.Final_Score) }}</strong>
                                </div>
                            </div>

                            <!-- Grade & HAV Row -->
                            <div class="pa-card-grade-hav-row">
                                <div class="pa-grade-box">
                                    <span class="pa-grade-label">Grade:</span>
                                    <HcisBadge
                                        v-if="r.Kode_Grade_Final || r.Kode_Grade_Sistem"
                                        :label="`Grade ${displayGrade(r.Kode_Grade_Final || r.Kode_Grade_Sistem)}`"
                                        :variant="getGradeBadgeVariant(r.Kode_Grade_Final || r.Kode_Grade_Sistem)"
                                        appearance="glass"
                                        size="xs"
                                    />
                                    <span v-if="r.dikalibrasi" class="pa-calibrated-tag">Dikalibrasi</span>
                                </div>

                                <span v-if="r.Nama_Kuadran" class="pa-quadrant-tag">
                                    Box {{ r.Nomor_Box || '-' }}
                                </span>
                            </div>
                        </div>

                        <template #footer>
                            <div class="pa-card-footer-actions">
                                <HcisButton
                                    label="Rincian Hasil"
                                    variant="secondary"
                                    appearance="subtle"
                                    size="xs"
                                    leading-icon="eye"
                                    @click="openParticipantModal(r)"
                                />
                            </div>
                        </template>
                    </HcisCard>
                </div>

                <!-- Bottom Pagination Bar -->
                <div v-if="totalPages > 1 || totalRecords > 25" class="pa-riwayat-pagination-bar">
                    <span class="pa-pagination-info font-num">
                        Halaman <strong>{{ currentPage }}</strong> dari <strong>{{ totalPages }}</strong> (Total <strong>{{ formatAngka(totalRecords) }}</strong> baris)
                    </span>

                    <HcisPagination
                        :total="totalRecords"
                        :current-page="currentPage"
                        :page-size="25"
                        layout="prev, pager, next"
                        size="xs"
                        background
                        @current-change="changePage"
                        @update:current-page="changePage"
                    />
                </div>
            </div>
        </div>

        <!-- ============================================================
             SUB-TAB 3: JEJAK AUDIT KEPUTUSAN SIKLUS (AUDIT TRAIL)
             ============================================================ -->
        <div v-show="tabAktif === 'AUDIT'" class="pa-tab-pane">
            <!-- 1. Cycle Selector & Quick Switcher Card -->
            <HcisCard
                variant="glass"
                sentiment="brand"
                :size="isMobile ? 'xs' : 'sm'"
                glow
                class="pa-audit-selector-card"
            >
                <div class="pa-audit-selector-inner">
                    <div class="pa-audit-selector-title-col">
                        <div class="pa-audit-sel-icon-box">
                            <HcisIcon name="fingerprint" size="sm" color="brand" />
                        </div>
                        <div>
                            <span class="pa-eyebrow">JEJAK AUDIT &amp; KEPUTUSAN SIKLUS</span>
                            <h3 class="pa-audit-title-text">Kronologi Peristiwa &amp; Keputusan Komite</h3>
                            <p class="pa-audit-desc-text">
                                Penelusuran seluruh tindakan perubahan konfigurasi, penugasan, override, kalibrasi, dan penerbitan beserta aktor dan justifikasinya.
                            </p>
                        </div>
                    </div>

                    <div class="pa-audit-selector-controls">
                        <div class="pa-audit-select-field">
                            <label class="pa-audit-sel-label" for="pa-audit-cycle-select">
                                <HcisIcon name="calendar-event" size="2xs" />
                                <span>Siklus Penilaian:</span>
                            </label>
                            <select
                                id="pa-audit-cycle-select"
                                v-model="selectedFilterValues.cycle_id"
                                class="pa-audit-cycle-native-select"
                                @change="onCycleAuditSelectChange"
                            >
                                <option value="">— Pilih Satu Assessment Cycle —</option>
                                <option v-for="s in filterChoices.siklus" :key="`aud-c-${s.id}`" :value="String(s.id)">
                                    {{ s.kode }} · {{ s.nama }} ({{ s.tahun || '—' }})
                                </option>
                            </select>
                        </div>

                        <HcisButton
                            v-if="selectedFilterValues.cycle_id"
                            label="Segarkan"
                            variant="glass"
                            :size="isMobile ? 'xs' : 'sm'"
                            leading-icon="arrow-clockwise"
                            :icon-only="isMobile"
                            :loading="audit.loading.value"
                            title="Segarkan data audit"
                            @click="muatAudit({ halaman: audit.pagination.halaman })"
                        />
                    </div>
                </div>

                <!-- Quick Switcher Cycle Chips if cycles available -->
                <div v-if="filterChoices.siklus?.length" class="pa-audit-quick-cycles-bar">
                    <span class="pa-quick-bar-label">Siklus Cepat:</span>
                    <div class="pa-quick-chips-scroll">
                        <button
                            v-for="s in filterChoices.siklus.slice(0, 6)"
                            :key="`qc-${s.id}`"
                            type="button"
                            class="pa-quick-cycle-pill"
                            :class="{ 'is-active': String(selectedFilterValues.cycle_id) === String(s.id) }"
                            @click="pilihCycleCepat(String(s.id))"
                        >
                            <span class="pa-qc-name">{{ s.nama || s.kode }}</span>
                            <span v-if="s.tahun" class="pa-qc-year font-num">{{ s.tahun }}</span>
                        </button>
                    </div>
                </div>
            </HcisCard>

            <!-- 2. Empty State if No Cycle is Selected -->
            <HcisEmptyState
                v-if="!selectedFilterValues.cycle_id"
                title="Pilih satu siklus penilaian terlebih dahulu"
                description="Jejak audit tersimpan spesifik per assessment cycle. Silakan pilih siklus pada menu dropdown di atas atau klik salah satu tombol siklus cepat."
                icon="journal-text"
                variant="card"
                sentiment="brand"
            >
                <template #actions>
                    <div v-if="filterChoices.siklus?.length" class="pa-quick-cycle-picks">
                        <HcisButton
                            v-for="s in filterChoices.siklus.slice(0, 3)"
                            :key="`pick-btn-${s.id}`"
                            :label="`${s.kode} · ${s.nama}`"
                            variant="secondary"
                            size="xs"
                            leading-icon="calendar-check"
                            @click="pilihCycleCepat(String(s.id))"
                        />
                    </div>
                </template>
            </HcisEmptyState>

            <!-- 3. If Cycle is Selected -->
            <template v-else>
                <!-- A. Cockpit Metric Cards (4 Crystal Cards - 2x2 Dense on Mobile) -->
                <div class="pa-cockpit-section" aria-label="Ringkasan Jejak Audit">
                    <HcisStatGroup
                        :stats="auditStats"
                        :cols="isMobile ? 2 : 4"
                        gap="xs"
                        :layout="isMobile ? 'dense' : 'compact'"
                        appearance="crystal"
                        class="pa-stat-group"
                    />
                </div>

                <!-- B. Design System Filter Bar for Audit -->
                <HcisFilterBar
                    :search="auditSearchQuery"
                    search-placeholder="Cari rincian peristiwa, sasaran karyawan, sebab/alasan, atau nama pelaku..."
                    :quick-chips="auditQuickChips"
                    :active-chip-value="auditActiveChip"
                    :filters="daftarFilterAudit"
                    :filter-values="auditFilterValues"
                    :collapsible="true"
                    :results-count="audit.pagination.total"
                    variant="card"
                    sentiment="brand"
                    size="sm"
                    glow
                    class="pa-audit-filter-bar mb-3"
                    @update:search="onAuditSearchChange"
                    @chip-click="onAuditChipClick"
                    @filter-change="onAuditFilterChange"
                    @reset="resetAuditFilters"
                />

                <!-- C. Boundary Alert if exists -->
                <HcisAlert
                    v-if="audit.batas.value?.konfigurasi_tanpa_nilai_lama"
                    sentiment="warning"
                    variant="subtle"
                    size="sm"
                    icon="info-circle-fill"
                    title="Batas Riwayat Konfigurasi"
                    :description="audit.batas.value?.catatan || 'Beberapa riwayat konfigurasi awal sebelum pelacakan audit terperinci tidak memiliki nilai lama.'"
                    class="mb-2"
                />

                <!-- D. Error State -->
                <HcisResultState
                    v-if="audit.error.value"
                    :status="audit.errorStatus.value === 403 ? 'warning' : 'danger'"
                    variant="flat"
                    :title="audit.errorStatus.value === 403 ? 'Akses audit tidak tersedia' : 'Gagal memuat jejak audit'"
                    :description="audit.error.value"
                    :error-code="audit.errorCode.value"
                    :request-id="audit.requestId.value"
                    primary-text="Coba Lagi"
                    primary-icon="arrow-clockwise"
                    :show-retry="audit.errorStatus.value !== 403"
                    @primary-click="muatAudit"
                />

                <!-- E. Loading State -->
                <div v-else-if="audit.loading.value && !audit.kejadian.value.length" class="pa-audit-loading">
                    <div class="pa-audit-skeleton-list">
                        <div v-for="n in 4" :key="`skel-aud-${n}`" class="pa-audit-skeleton-item">
                            <HcisSkeleton width="140px" height="1rem" rounded="sm" />
                            <HcisSkeleton width="100%" height="2.5rem" rounded="md" />
                            <HcisSkeleton width="200px" height="0.8rem" rounded="sm" />
                        </div>
                    </div>
                </div>

                <!-- F. Empty Events State -->
                <HcisEmptyState
                    v-else-if="!audit.kejadian.value.length"
                    title="Belum ada catatan peristiwa"
                    description="Tidak ditemukan jejak tindakan audit pada siklus ini yang sesuai dengan saringan atau kata kunci pencarian Anda."
                    icon="fingerprint"
                    variant="card"
                    sentiment="neutral"
                >
                    <template v-if="auditSearchQuery || auditFilterValues.jenis || audit.filters.hanya_keputusan_manusia" #actions>
                        <HcisButton
                            label="Atur Ulang Saringan Audit"
                            variant="secondary"
                            size="xs"
                            leading-icon="arrow-counterclockwise"
                            @click="resetAuditFilters"
                        />
                    </template>
                </HcisEmptyState>

                <!-- G. Loaded Timeline Stream -->
                <div v-else class="pa-audit-timeline-container">
                    <HcisTimeline
                        :items="auditTimelineItems"
                        direction="vertical"
                        variant="compact"
                        sentiment="brand"
                        :card="false"
                        class="pa-audit-timeline-stream"
                    >
                        <template #itemContent="{ item }">
                            <div class="pa-audit-event-card-body">
                                <p v-if="item.content" class="pa-audit-item-desc">
                                    {{ item.content }}
                                </p>

                                <!-- Representative Samples Tag List -->
                                <div v-if="item.sasaran_contoh?.length" class="pa-audit-sample-targets">
                                    <span class="pa-sample-label">
                                        <HcisIcon name="people" size="2xs" /> Contoh Sasaran:
                                    </span>
                                    <div class="pa-sample-tags-wrap">
                                        <span v-for="(sc, sIdx) in item.sasaran_contoh" :key="sIdx" class="pa-sample-tag">
                                            {{ sc }}
                                        </span>
                                        <span v-if="item.sasaran_lain" class="pa-sample-more-tag font-num">
                                            +{{ item.sasaran_lain }} lainnya
                                        </span>
                                    </div>
                                </div>

                                <!-- Justification / Reason Blockquote -->
                                <div v-if="item.sebab" class="pa-audit-justification-box">
                                    <HcisIcon name="chat-left-quote-fill" size="2xs" class="pa-quote-icon" />
                                    <p class="pa-quote-text">&ldquo;{{ item.sebab }}&rdquo;</p>
                                </div>
                            </div>
                        </template>
                    </HcisTimeline>

                    <!-- Bottom Pagination -->
                    <div v-if="audit.pagination.total_halaman > 1 || audit.pagination.total > 10" class="pa-audit-pagination-bar">
                        <span class="pa-pagination-info font-num">
                            Halaman <strong>{{ audit.pagination.halaman }}</strong> dari <strong>{{ audit.pagination.total_halaman }}</strong> (Total <strong>{{ formatAngka(audit.pagination.total) }}</strong> kejadian)
                        </span>

                        <HcisPagination
                            :total="audit.pagination.total"
                            :current-page="audit.pagination.halaman"
                            :page-size="audit.pagination.per_halaman || 10"
                            layout="prev, pager, next"
                            size="xs"
                            background
                            :disabled="audit.loading.value"
                            @current-change="onAuditPageChange"
                            @update:current-page="onAuditPageChange"
                        />
                    </div>
                </div>
            </template>
        </div>

        <!-- Result Detail Modal Component -->
        <PaResultDialog
            v-if="selectedParticipant"
            v-model="participantModalOpen"
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
            @retry="muatDetailPeserta"
        />
    </main>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import {
    HcisAlert,
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisChart,
    HcisEmptyState,
    HcisFilterBar,
    HcisHeader,
    HcisIcon,
    HcisPagination,
    HcisResultState,
    HcisSkeleton,
    HcisStatCard,
    HcisStatGroup,
    HcisStatusBadge,
    HcisTabs,
    HcisTimeline,
} from '@/components/ui';
import PaResultDialog from '../Cycle/components/PaResultDialog.vue';
import { usePaCycleAudit } from '../Cycle/composables/usePaCycleAudit.js';
import { usePaResult } from '../Cycle/composables/usePaResult.js';
import paApi from '../api/paApi.js';

defineOptions({ name: 'PerformanceAppraisalRiwayatPage' });

/* Responsive viewport detection */
const isMobile = ref(typeof window !== 'undefined' ? window.innerWidth <= 768 : false);
let mql = null;
const updateIsMobile = (e) => {
    isMobile.value = e.matches;
};

/* Sub-Tabs State */
const tabAktif = ref('RINGKASAN');
const trendViewMode = ref('grid');

const loadingRiwayat = ref(false);
const errorRiwayat = ref('');
const errorStatus = ref(0);

const riwayatRows = ref([]);
/* Skala batang rentang tren: nilai akhir bisa melewati 100 (KPI dibatasi 125), jadi
   puncaknya diambil dari data, minimal 100 — bukan dipotong di 100. */
const skalaTren = computed(() => Math.max(100, ...trendData.value.map((t) => Number(t.tertinggi) || 0)));

function persenSkalaTren(nilai) {
    return Math.max(0, Math.min(100, (Number(nilai) / skalaTren.value) * 100));
}

const trendData = ref([]);
const filterChoices = ref({});
const totalRecords = ref(0);
const totalPages = ref(1);
const currentPage = ref(1);

const searchQuery = ref('');
const activeStatusChip = ref('ALL');
const viewMode = ref('table');

const selectedFilterValues = reactive({
    tahun: '',
    cycle_id: '',
    kode_grade: '',
    status_hasil: '',
});

const selectedParticipant = ref(null);
const participantModalOpen = ref(false);
const participantResultState = usePaResult(paApi);

/* Composable yang SAMA dengan dialog audit di halaman daftar siklus. */
const audit = usePaCycleAudit(paApi);

const LABEL_JENIS_AUDIT = {
    PENUGASAN_DIBATALKAN: 'Penugasan dibatalkan',
    OVERRIDE_PENILAI: 'Override penilai',
    OVERRIDE_PILAR: 'Override bobot pilar',
    OVERRIDE_SYARAT: 'Override syarat kompetensi',
    KALIBRASI: 'Kalibrasi grade',
    PESERTA_DIKECUALIKAN: 'Peserta dikecualikan',
    HASIL_DITERBITKAN: 'Hasil diterbitkan',
    BOBOT_PILAR_DIUBAH: 'Bobot pilar diubah',
    SIKLUS_DIKUNCI: 'Cycle dikunci',
    SIKLUS_DITERBITKAN: 'Cycle diterbitkan',
};

function labelJenisAudit(jenis) {
    return LABEL_JENIS_AUDIT[jenis] || jenis;
}

function getAuditEventTypeSentiment(jenis) {
    if (jenis === 'KALIBRASI') return 'warning';
    if (jenis === 'HASIL_DITERBITKAN' || jenis === 'SIKLUS_DITERBITKAN') return 'success';
    if (jenis === 'PESERTA_DIKECUALIKAN' || jenis === 'PENUGASAN_DIBATALKAN') return 'danger';
    return 'brand';
}

function rentangWaktuAudit(k) {
    const awal = k.waktu_awal || k.waktu || '';
    const akhir = k.waktu_akhir || k.waktu || '';

    if (!awal) return '—';
    if (!akhir || awal === akhir) return akhir || awal;

    return `${awal} – ${akhir.slice(11) || akhir}`;
}

async function muatAudit(patch = {}) {
    const cycleId = selectedFilterValues.cycle_id;
    if (!cycleId) return;
    await audit.muat(cycleId, patch);
}

/* Audit Filter & Stream State */
const auditSearchQuery = ref('');
const auditFilterValues = reactive({
    jenis: '',
    per_halaman: '10',
});

const auditActiveChip = computed(() => {
    if (audit.filters.hanya_keputusan_manusia) return 'HUMAN_ONLY';
    return audit.filters.jenis || '';
});

const auditStats = computed(() => {
    const cycle = (filterChoices.value.siklus || []).find((s) => String(s.id) === String(selectedFilterValues.cycle_id));
    return [
        {
            id: 'audit-total',
            label: isMobile.value ? 'Total Peristiwa' : 'Total Peristiwa Audit',
            value: formatAngka(audit.pagination.total),
            suffix: 'kejadian',
            sentiment: 'brand',
            icon: 'fingerprint',
            appearance: 'crystal',
            layout: isMobile.value ? 'dense' : 'compact',
            caption: isMobile.value ? 'Lini masa tercatat' : 'Log kronologi peristiwa tersimpan',
            pattern: 'grid',
        },
        {
            id: 'audit-manusia',
            label: isMobile.value ? 'Keputusan' : 'Keputusan Komite / Tim',
            value: formatAngka(audit.cacah.value.keputusan_manusia),
            suffix: 'tindakan',
            sentiment: 'warning',
            icon: 'person-gear',
            appearance: 'crystal',
            layout: isMobile.value ? 'dense' : 'compact',
            caption: isMobile.value ? 'Oleh manusia' : 'Intervensi & keputusan manusia',
            pattern: 'dots',
        },
        {
            id: 'audit-sistem',
            label: isMobile.value ? 'Otomatisasi' : 'Otomatisasi Sistem',
            value: formatAngka(audit.cacah.value.oleh_sistem),
            suffix: 'proses',
            sentiment: 'teal',
            icon: 'cpu',
            appearance: 'crystal',
            layout: isMobile.value ? 'dense' : 'compact',
            caption: isMobile.value ? 'Oleh sistem' : 'Eksekusi aturan & kalkulasi sistem',
            pattern: 'rings',
        },
        {
            id: 'audit-siklus',
            label: isMobile.value ? 'Siklus' : 'Target Siklus Penilaian',
            value: cycle?.kode || cycle?.nama || `Cycle #${selectedFilterValues.cycle_id}`,
            suffix: cycle?.tahun ? `(${cycle.tahun})` : '',
            sentiment: 'purple',
            icon: 'calendar2-check-fill',
            appearance: 'crystal',
            layout: isMobile.value ? 'dense' : 'compact',
            caption: isMobile.value ? 'Target audit' : 'Siklus penilaian yang ditinjau',
            pattern: 'stripes',
        },
    ];
});

const auditQuickChips = computed(() => {
    const chips = [
        {
            id: 'ALL',
            value: '',
            label: isMobile.value ? 'Semua' : 'Semua Peristiwa',
            badge: formatAngka(audit.pagination.total),
        },
    ];

    if (audit.cacah.value.keputusan_manusia > 0) {
        chips.push({
            id: 'HUMAN_ONLY',
            value: 'HUMAN_ONLY',
            label: isMobile.value ? 'Manusia' : 'Keputusan Manusia',
            badge: formatAngka(audit.cacah.value.keputusan_manusia),
            color: 'amber',
            icon: 'person-gear',
        });
    }

    (audit.jenisTersedia.value || []).slice(0, 4).forEach((j) => {
        chips.push({
            id: j,
            value: j,
            label: labelJenisAudit(j),
            color: getAuditEventTypeSentiment(j) === 'warning' ? 'amber' : getAuditEventTypeSentiment(j) === 'success' ? 'emerald' : 'indigo',
        });
    });

    return chips;
});

const daftarFilterAudit = computed(() => [
    {
        key: 'jenis',
        label: 'Jenis Peristiwa',
        placeholder: 'Semua Jenis',
        type: 'select',
        options: (audit.jenisTersedia.value || []).map((j) => ({
            label: labelJenisAudit(j),
            value: j,
        })),
    },
    {
        key: 'per_halaman',
        label: 'Baris per Halaman',
        placeholder: '10 baris',
        type: 'select',
        options: [
            { label: '10 baris / halaman', value: '10' },
            { label: '25 baris / halaman', value: '25' },
            { label: '50 baris / halaman', value: '50' },
            { label: '100 baris / halaman', value: '100' },
        ],
    },
]);

const auditTimelineItems = computed(() => {
    return (audit.kejadian.value || []).map((k, index) => {
        const sentiment = getAuditEventTypeSentiment(k.jenis);
        let icon = 'journal-check';
        if (k.jenis === 'KALIBRASI') icon = 'sliders';
        else if (k.jenis === 'HASIL_DITERBITKAN' || k.jenis === 'SIKLUS_DITERBITKAN') icon = 'patch-check-fill';
        else if (k.jenis === 'PESERTA_DIKECUALIKAN' || k.jenis === 'PENUGASAN_DIBATALKAN') icon = 'person-x-fill';
        else if (k.jenis === 'OVERRIDE_PENILAI') icon = 'person-gear';
        else if (k.jenis === 'OVERRIDE_PILAR' || k.jenis === 'BOBOT_PILAR_DIUBAH') icon = 'percent';
        else if (k.jenis === 'SIKLUS_DIKUNCI') icon = 'lock-fill';

        const meta = [];
        if (k.sasaran) {
            meta.push({ label: 'Sasaran', value: k.sasaran });
        }
        if (k.participant_id) {
            meta.push({ label: 'ID Peserta', value: `#${k.participant_id}` });
        }
        if ((k.jumlah || 1) > 1) {
            meta.push({ label: 'Cakupan', value: `${k.jumlah} orang` });
        }

        return {
            id: `aud-${k.waktu || 'time'}-${k.jenis || 'type'}-${index}`,
            title: labelJenisAudit(k.jenis),
            status: k.oleh_sistem ? 'Otomatisasi Sistem' : 'Keputusan Tim / Komite',
            name: k.oleh_sistem ? 'Sistem HCIS' : (k.aktor_nama || 'Pelaku tidak tercatat'),
            role: k.participant_id ? `Peserta #${k.participant_id}` : 'Tingkat Siklus',
            time: rentangWaktuAudit(k),
            icon: k.oleh_sistem ? 'cpu' : (k.aktor_nama ? 'person-check-fill' : icon),
            sentiment: k.oleh_sistem ? 'info' : sentiment,
            avatarText: k.oleh_sistem ? 'SYS' : (k.aktor_nama ? k.aktor_nama.slice(0, 2).toUpperCase() : 'ADM'),
            content: k.rincian || '',
            description: k.rincian || '',
            sebab: k.sebab || '',
            sasaran: k.sasaran || '',
            sasaran_contoh: k.sasaran_contoh || [],
            sasaran_lain: k.sasaran_lain || 0,
            oleh_sistem: Boolean(k.oleh_sistem),
            aktor_nama: k.aktor_nama || '',
            meta,
            raw: k,
        };
    });
});

const summaryCounts = ref({ total: 0, terbit: 0, dikalibrasi: 0 });

const calibratedCount = computed(() => summaryCounts.value.dikalibrasi ?? riwayatRows.value.filter((r) => r.dikalibrasi).length);
const publishedCount = computed(() => summaryCounts.value.terbit ?? riwayatRows.value.filter((r) => r.Status_Hasil === 'DITERBITKAN' || r.Status_Hasil === 'TERBIT' || r.Published_At).length);

const statusQuickChips = computed(() => [
    {
        id: 'ALL',
        value: 'ALL',
        label: isMobile.value ? 'Semua' : 'Semua Riwayat',
        badge: formatAngka(summaryCounts.value.total || totalRecords.value),
    },
    {
        id: 'PUBLISHED',
        value: 'PUBLISHED',
        label: isMobile.value ? 'Terbit' : 'Sudah Diterbitkan',
        badge: formatAngka(publishedCount.value),
        color: 'emerald',
    },
    {
        id: 'CALIBRATED',
        value: 'CALIBRATED',
        label: isMobile.value ? 'Kalibrasi' : 'Dikalibrasi',
        badge: formatAngka(calibratedCount.value),
        color: 'amber',
    },
]);

const daftarFilterRiwayat = computed(() => [
    {
        key: 'tahun',
        label: 'Tahun',
        placeholder: 'Semua Tahun',
        type: 'select',
        options: (filterChoices.value.tahun || []).map((y) => ({
            label: `Tahun ${y}`,
            value: String(y),
        })),
    },
    {
        key: 'cycle_id',
        label: 'Assessment Cycle',
        placeholder: 'Semua Cycle',
        type: 'select',
        options: (filterChoices.value.siklus || []).map((s) => ({
            label: `${s.kode} · ${s.nama}`,
            value: String(s.id),
        })),
    },
    {
        key: 'kode_grade',
        label: 'Grade',
        placeholder: 'Semua Grade',
        type: 'select',
        options: (filterChoices.value.grade || []).map((g) => ({
            label: `Grade ${displayGrade(g)}`,
            value: g,
        })),
    },
    {
        key: 'status_hasil',
        label: 'Status Hasil',
        placeholder: 'Semua Status Hasil',
        type: 'select',
        options: (filterChoices.value.status_hasil || []).map((st) => ({
            label: `Status: ${st}`,
            value: st,
        })),
    },
]);

const riwayatStats = computed(() => [
    {
        id: 'total-rekam-jejak',
        label: isMobile.value ? 'Total Rekam' : 'Total Rekam Jejak',
        value: formatAngka(totalRecords.value),
        suffix: 'baris',
        sentiment: 'brand',
        icon: 'journal-bookmark-fill',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
        caption: isMobile.value ? 'Riwayat tersimpan' : 'Seluruh riwayat penilaian tersimpan',
        pattern: 'grid',
        interactive: true,
    },
    {
        id: 'total-siklus',
        label: isMobile.value ? 'Cycle / Tahun' : 'Cycle / Tahun Terdata',
        value: formatAngka(filterChoices.value.tahun?.length || filterChoices.value.siklus?.length || 0),
        suffix: 'siklus',
        sentiment: 'purple',
        icon: 'calendar-range',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
        caption: isMobile.value ? 'Multi-tahun' : 'Cakupan arsip multi-tahun',
        pattern: 'dots',
        interactive: true,
    },
    {
        id: 'hasil-dikalibrasi',
        label: isMobile.value ? 'Dikalibrasi' : 'Hasil Dikalibrasi',
        value: formatAngka(calibratedCount.value),
        suffix: 'peserta',
        sentiment: 'warning',
        icon: 'sliders',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
        caption: isMobile.value ? 'Penyesuaian komite' : 'Penyesuaian oleh komite',
        pattern: 'rings',
        interactive: true,
    },
    {
        id: 'hasil-diterbitkan',
        label: isMobile.value ? 'Diterbitkan' : 'Hasil Diterbitkan',
        value: formatAngka(publishedCount.value),
        suffix: 'peserta',
        sentiment: 'success',
        icon: 'patch-check-fill',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
        caption: isMobile.value ? 'Telah diumumkan' : 'Telah diumumkan ke karyawan',
        pattern: 'stripes',
        interactive: true,
    },
]);

function onStatCardItemClick({ item }) {
    if (!item) return;
    if (item.id === 'total-rekam-jejak') {
        tabAktif.value = 'ARSIP';
        activeStatusChip.value = 'ALL';
        onStatusChipClick('ALL');
    } else if (item.id === 'total-siklus') {
        tabAktif.value = 'RINGKASAN';
    } else if (item.id === 'hasil-dikalibrasi') {
        tabAktif.value = 'ARSIP';
        activeStatusChip.value = 'CALIBRATED';
        onStatusChipClick('CALIBRATED');
    } else if (item.id === 'hasil-diterbitkan') {
        tabAktif.value = 'ARSIP';
        activeStatusChip.value = 'PUBLISHED';
        onStatusChipClick('PUBLISHED');
    }
}

const labelSelectedCycle = computed(() => {
    if (!selectedFilterValues.cycle_id) return '';
    const found = (filterChoices.value.siklus || []).find((s) => String(s.id) === String(selectedFilterValues.cycle_id));
    return found ? `${found.kode} · ${found.nama}` : `Cycle ID ${selectedFilterValues.cycle_id}`;
});

const daftarTab = computed(() => [
    {
        key: 'RINGKASAN',
        kode: 'RINGKASAN',
        label: isMobile.value ? 'Ringkasan' : 'Ringkasan & Tren',
        nama: isMobile.value ? 'Ringkasan' : 'Ringkasan & Tren',
        icon: 'graph-up-arrow',
        ikon: 'graph-up-arrow',
        badge: trendData.value.length ? `${trendData.value.length} Siklus` : null,
        badgeVariant: 'purple',
    },
    {
        key: 'ARSIP',
        kode: 'ARSIP',
        label: isMobile.value ? 'Rekam Jejak' : 'Rekam Jejak Hasil',
        nama: isMobile.value ? 'Rekam Jejak' : 'Rekam Jejak Hasil',
        icon: 'journal-bookmark-fill',
        ikon: 'journal-bookmark-fill',
        badge: totalRecords.value ? formatAngka(totalRecords.value) : null,
        badgeVariant: 'brand',
    },
    {
        key: 'AUDIT',
        kode: 'AUDIT',
        label: isMobile.value ? 'Jejak Audit' : 'Jejak Audit Siklus',
        nama: isMobile.value ? 'Jejak Audit' : 'Jejak Audit Siklus',
        icon: 'fingerprint',
        ikon: 'fingerprint',
        badge: selectedFilterValues.cycle_id ? `${audit.cacah.value.keputusan_manusia} Keputusan` : (isMobile.value ? null : 'Pilih Siklus'),
        badgeVariant: selectedFilterValues.cycle_id ? 'warning' : 'neutral',
    },
]);

function gantiTab(kode) {
    tabAktif.value = kode;
    if (kode === 'AUDIT' && selectedFilterValues.cycle_id && !audit.kejadian.value.length) {
        muatAudit({ halaman: 1 });
    }
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

function getGradeBadgeVariant(grade) {
    if (!grade) return 'neutral';
    const g = String(displayGrade(grade)).toUpperCase();
    if (g.startsWith('A')) return 'success';
    if (g.startsWith('B')) return 'brand';
    if (g.startsWith('C')) return 'warning';
    return 'danger';
}

/* ==========================================================================
   Chart & Analytics Computations
   ========================================================================== */
const trendChartCategories = computed(() => {
    return trendData.value.map((t) => t.nama_cycle || t.kode_cycle || `Tahun ${t.tahun}`);
});

const trendChartSeries = computed(() => {
    return [
        {
            name: 'Rata-rata Nilai Akhir',
            data: trendData.value.map((t) => Number(Number(t.rata_rata ?? t.rerata ?? 0).toFixed(2))),
            smooth: true,
        },
    ];
});

const participantVolumeSeries = computed(() => {
    return [
        {
            name: 'Jumlah Peserta',
            data: trendData.value.map((t) => Number(t.jumlah ?? t.jumlah_peserta ?? 0)),
        },
    ];
});

const totalPesertaAllCycles = computed(() => {
    return trendData.value.reduce((acc, t) => acc + Number(t.jumlah ?? t.jumlah_peserta ?? 0), 0);
});

const distribusiGradeServer = ref({});

const gradeDonutData = computed(() => {
    if (distribusiGradeServer.value && Object.keys(distribusiGradeServer.value).length > 0) {
        return Object.entries(distribusiGradeServer.value).map(([grade, val]) => ({
            name: `Grade ${displayGrade(grade)}`,
            value: Number(val),
        }));
    }

    const counts = {};
    riwayatRows.value.forEach((r) => {
        const g = r.Kode_Grade_Final || r.Kode_Grade_Sistem;
        if (g) {
            const formatted = displayGrade(g);
            counts[formatted] = (counts[formatted] || 0) + 1;
        }
    });

    // Tanpa hasil bergrade: donat kosong, bukan enam grade sama rata yang dikarang.

    return Object.entries(counts).map(([grade, val]) => ({
        name: `Grade ${grade}`,
        value: val,
    }));
});

const latestTrendScore = computed(() => {
    if (!trendData.value.length) return null;
    const last = trendData.value[trendData.value.length - 1];
    const val = last.rata_rata ?? last.rerata;
    return val != null ? Number(val).toFixed(2) : null;
});

const overallHighestScore = computed(() => {
    let max = null;
    trendData.value.forEach((t) => {
        const val = t.tertinggi ?? t.rata_rata ?? t.rerata;
        if (val != null) {
            const num = Number(val);
            if (max === null || num > max) max = num;
        }
    });
    return max != null ? max.toFixed(2) : null;
});

const dominantGrade = computed(() => {
    const entries = Object.entries(distribusiGradeServer.value || {});
    if (entries.length > 0) {
        entries.sort((a, b) => Number(b[1]) - Number(a[1]));
        return { grade: displayGrade(entries[0][0]), count: Number(entries[0][1]) };
    }

    const counts = {};
    riwayatRows.value.forEach((r) => {
        const g = r.Kode_Grade_Final || r.Kode_Grade_Sistem;
        if (g) {
            const formatted = displayGrade(g);
            counts[formatted] = (counts[formatted] || 0) + 1;
        }
    });
    const rEntries = Object.entries(counts);
    if (!rEntries.length) return null;
    rEntries.sort((a, b) => b[1] - a[1]);
    return { grade: rEntries[0][0], count: rEntries[0][1] };
});

const trendDeltaText = computed(() => {
    if (trendData.value.length < 2) return '';
    const curr = Number(trendData.value[trendData.value.length - 1]?.rata_rata ?? trendData.value[trendData.value.length - 1]?.rerata ?? 0);
    const prev = Number(trendData.value[trendData.value.length - 2]?.rata_rata ?? trendData.value[trendData.value.length - 2]?.rerata ?? 0);
    const diff = (curr - prev).toFixed(2);
    return diff >= 0 ? `+${diff} poin` : `${diff} poin`;
});

const trendDeltaDirection = computed(() => {
    if (trendData.value.length < 2) return 'up';
    const curr = Number(trendData.value[trendData.value.length - 1]?.rata_rata ?? trendData.value[trendData.value.length - 1]?.rerata ?? 0);
    const prev = Number(trendData.value[trendData.value.length - 2]?.rata_rata ?? trendData.value[trendData.value.length - 2]?.rerata ?? 0);
    return curr >= prev ? 'up' : 'down';
});

const trendInsightStats = computed(() => [
    {
        id: 'rata-rata-terkini',
        label: isMobile.value ? 'Rata-rata' : 'Rata-rata Terkini',
        value: latestTrendScore.value || '—',
        delta: trendDeltaText.value || undefined,
        trend: trendDeltaDirection.value === 'up' ? 'up' : trendDeltaDirection.value === 'down' ? 'down' : undefined,
        sentiment: 'brand',
        icon: 'graph-up-arrow',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
        caption: isMobile.value ? 'Siklus terbaru' : 'Rata-rata skor siklus terbaru',
        pattern: 'grid',
    },
    {
        id: 'skor-tertinggi',
        label: isMobile.value ? 'Tertinggi' : 'Skor Tertinggi Siklus',
        value: overallHighestScore.value || '—',
        sentiment: 'success',
        icon: 'trophy-fill',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
        caption: isMobile.value ? 'Puncak skor' : 'Skor tertinggi lintas siklus',
        pattern: 'dots',
    },
    {
        id: 'grade-dominan',
        label: isMobile.value ? 'Dominan' : 'Grade Paling Dominan',
        value: dominantGrade.value ? `Grade ${dominantGrade.value.grade}` : '—',
        suffix: dominantGrade.value ? `(${formatAngka(dominantGrade.value.count)})` : '',
        sentiment: 'purple',
        icon: 'award-fill',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
        caption: isMobile.value ? 'Populasi terbesar' : 'Distribusi populasi grade terbesar',
        pattern: 'rings',
    },
    {
        id: 'total-partisipasi',
        label: isMobile.value ? 'Partisipasi' : 'Total Partisipasi',
        value: formatAngka(totalPesertaAllCycles.value),
        suffix: 'evaluasi',
        sentiment: 'teal',
        icon: 'people-fill',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
        caption: isMobile.value ? 'Total peserta' : 'Total peserta seluruh evaluasi',
        pattern: 'stripes',
    },
]);

async function muatDataRiwayat() {
    loadingRiwayat.value = true;
    errorRiwayat.value = '';
    errorStatus.value = 0;

    try {
        const params = {
            halaman: currentPage.value,
            per_halaman: 25,
            q: searchQuery.value || undefined,
            tahun: selectedFilterValues.tahun || undefined,
            cycle_id: selectedFilterValues.cycle_id || undefined,
            kode_grade: selectedFilterValues.kode_grade || undefined,
            status_hasil: selectedFilterValues.status_hasil || (activeStatusChip.value === 'PUBLISHED' ? 'TERBIT' : undefined),
            // `1`, bukan `true`: lewat URLSearchParams ia menjadi teks "true" yang ditolak validasi boolean.
            dikalibrasi: activeStatusChip.value === 'CALIBRATED' ? 1 : undefined,
        };

        const response = await paApi.riwayatAdmin(params);
        const data = response?.result ?? response ?? {};

        const rows = data?.baris || [];
        riwayatRows.value = rows.map((r, idx) => ({
            ...r,
            id_unique: `${r.Id_PA_Participant || idx}-${r.Id_PA_Cycle || idx}`,
        }));

        totalRecords.value = Number(data?.total || rows.length);
        totalPages.value = Number(data?.total_halaman || 1);
        trendData.value = data?.tren || [];
        filterChoices.value = data?.pilihan_saringan || {};

        if (data?.ringkasan) {
            summaryCounts.value = data.ringkasan;
        }
        if (data?.distribusi_grade) {
            distribusiGradeServer.value = data.distribusi_grade;
        }
    } catch (e) {
        errorRiwayat.value = e?.message || 'Gagal memuat riwayat penilaian.';
        errorStatus.value = Number(e?.status || 500);
    } finally {
        loadingRiwayat.value = false;
    }
}

let searchTimer = null;
function onSearchChange(query) {
    searchQuery.value = typeof query === 'string' ? query : (query?.target?.value ?? '');
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        currentPage.value = 1;
        muatDataRiwayat();
    }, 250);
}

function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        currentPage.value = 1;
        muatDataRiwayat();
    }, 300);
}

function clearSearch() {
    searchQuery.value = '';
    currentPage.value = 1;
    muatDataRiwayat();
}

function onStatusChipClick(valOrChip) {
    const val = typeof valOrChip === 'object' && valOrChip !== null && valOrChip.value !== undefined
        ? valOrChip.value
        : valOrChip;
    activeStatusChip.value = val;
    currentPage.value = 1;
    muatDataRiwayat();
}

function onFilterChange({ key, value }) {
    selectedFilterValues[key] = value ?? '';
    currentPage.value = 1;
    muatDataRiwayat();

    if (key === 'cycle_id') {
        audit.resetFilter();
        if (value) {
            muatAudit({ halaman: 1 });
        }
    }
}

function onCycleAuditSelectChange() {
    currentPage.value = 1;
    muatDataRiwayat();
    audit.resetFilter();
    auditSearchQuery.value = '';
    auditFilterValues.jenis = '';
    auditFilterValues.per_halaman = '10';
    if (selectedFilterValues.cycle_id) {
        muatAudit({ halaman: 1 });
    }
}

function pilihCycleCepat(cycleId) {
    selectedFilterValues.cycle_id = cycleId;
    currentPage.value = 1;
    muatDataRiwayat();
    audit.resetFilter();
    auditSearchQuery.value = '';
    auditFilterValues.jenis = '';
    auditFilterValues.per_halaman = '10';
    muatAudit({ halaman: 1 });
}

function filterDanBukaArsipCycle(cycleId) {
    selectedFilterValues.cycle_id = cycleId;
    tabAktif.value = 'ARSIP';
    currentPage.value = 1;
    muatDataRiwayat();
}

function bukaAuditCycle(cycleId) {
    selectedFilterValues.cycle_id = String(cycleId);
    tabAktif.value = 'AUDIT';
    audit.resetFilter();
    auditSearchQuery.value = '';
    auditFilterValues.jenis = '';
    auditFilterValues.per_halaman = '10';
    muatAudit({ halaman: 1 });
}

let auditSearchTimer = null;
function onAuditSearchChange(q) {
    auditSearchQuery.value = typeof q === 'string' ? q : (q?.target?.value ?? '');
    clearTimeout(auditSearchTimer);
    auditSearchTimer = setTimeout(() => {
        muatAudit({ q: auditSearchQuery.value, halaman: 1 });
    }, 250);
}

function onAuditChipClick(valOrChip) {
    const val = typeof valOrChip === 'object' && valOrChip !== null && valOrChip.value !== undefined
        ? valOrChip.value
        : valOrChip;

    if (val === 'HUMAN_ONLY') {
        audit.filters.hanya_keputusan_manusia = true;
        audit.filters.jenis = '';
        auditFilterValues.jenis = '';
        muatAudit({ hanya_keputusan_manusia: true, jenis: '', halaman: 1 });
    } else {
        audit.filters.hanya_keputusan_manusia = false;
        audit.filters.jenis = val || '';
        auditFilterValues.jenis = val || '';
        muatAudit({ hanya_keputusan_manusia: false, jenis: val || '', halaman: 1 });
    }
}

function onAuditFilterChange({ key, value }) {
    auditFilterValues[key] = value ?? '';
    if (key === 'jenis') {
        audit.filters.hanya_keputusan_manusia = false;
        muatAudit({ jenis: value || '', halaman: 1 });
    } else if (key === 'per_halaman') {
        muatAudit({ per_halaman: Number(value || 10), halaman: 1 });
    }
}

function resetAuditFilters() {
    auditSearchQuery.value = '';
    auditFilterValues.jenis = '';
    auditFilterValues.per_halaman = '10';
    audit.resetFilter();
    muatAudit({ halaman: 1 });
}

function onAuditPageChange(page) {
    muatAudit({ halaman: page });
}

function resetFilters() {
    searchQuery.value = '';
    activeStatusChip.value = 'ALL';
    selectedFilterValues.tahun = '';
    selectedFilterValues.cycle_id = '';
    selectedFilterValues.kode_grade = '';
    selectedFilterValues.status_hasil = '';
    currentPage.value = 1;
    muatDataRiwayat();
}

function changePage(page) {
    currentPage.value = page;
    muatDataRiwayat();
}

function segarkanHalaman() {
    muatDataRiwayat();
    if (selectedFilterValues.cycle_id) {
        muatAudit({ halaman: audit.pagination.halaman || 1 });
    }
}

async function openParticipantModal(row) {
    selectedParticipant.value = {
        Id_PA_Participant: row.Id_PA_Participant,
        Nama_Karyawan: row.Nama_Karyawan_Saat_Cycle || row.Nama_Karyawan,
        Nama_Karyawan_Saat_Cycle: row.Nama_Karyawan_Saat_Cycle || row.Nama_Karyawan,
        Kode_Karyawan: row.Kode_Karyawan,
        Nama_Level: row.Nama_Level_Saat_Cycle || row.Nama_Level,
        Nama_Level_Saat_Cycle: row.Nama_Level_Saat_Cycle || row.Nama_Level,
        Nama_Divisi: row.Nama_Divisi_Saat_Cycle || row.Nama_Divisi,
        Nama_Divisi_Saat_Cycle: row.Nama_Divisi_Saat_Cycle || row.Nama_Divisi,
    };
    participantModalOpen.value = true;
    await muatDetailPeserta();
}

async function muatDetailPeserta() {
    if (!selectedParticipant.value?.Id_PA_Participant) return;
    await participantResultState.muat(selectedParticipant.value.Id_PA_Participant);
}

function formatNumber(val) {
    if (val === null || val === undefined || isNaN(Number(val))) return '—';
    return Number(val).toFixed(2);
}

function formatAngka(num) {
    return Number(num || 0).toLocaleString('id-ID');
}

onMounted(() => {
    if (typeof window !== 'undefined' && window.matchMedia) {
        mql = window.matchMedia('(max-width: 768px)');
        isMobile.value = mql.matches;
        mql.addEventListener?.('change', updateIsMobile);
    }
    muatDataRiwayat();
    muatAudit();
});

onUnmounted(() => {
    if (mql && mql.removeEventListener) {
        mql.removeEventListener('change', updateIsMobile);
    }
});
</script>

<style scoped>
.pa-riwayat-page {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
    padding: 0 0 5rem 0;
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
}

.pa-main-header {
    margin-bottom: 0.25rem;
}

.pa-riwayat-header-actions {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

/* Tabs Navigation Bar inside Header */
.pa-monitor-tabs {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(241, 245, 249, 0.85);
    overflow-x: auto;
    border: 1px solid rgba(203, 213, 225, 0.5);
}

.pa-monitor-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.4rem 0.8rem;
    border-radius: var(--hcis-radius-sm, 6px);
    border: none;
    background: transparent;
    color: var(--hcis-text-muted, #64748b);
    font: inherit;
    font-size: 0.76rem;
    font-weight: 650;
    cursor: pointer;
    transition: all 140ms ease;
    white-space: nowrap;
}

.pa-monitor-tab:hover {
    color: #4f46e5;
    background: rgba(255, 255, 255, 0.6);
}

.pa-monitor-tab--aktif {
    background: #ffffff;
    color: #4338ca;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.pa-monitor-tab__badge {
    font-size: 0.64rem;
}

/* Admin Switcher Console Nav */
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

/* Tab Pane */
.pa-tab-pane {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
    width: 100%;
}

/* Cockpit Section & Stat Group (Assessment Cycle Pattern) */
.pa-cockpit-section {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    width: 100%;
}

.pa-stat-group {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 0.75rem !important;
    width: 100%;
}

.pa-stat-group :deep(.hcis-stat) {
    min-height: unset !important;
    height: auto !important;
    padding: 0.6rem 0.8rem !important;
    border-radius: var(--hcis-radius-md, 10px) !important;
}

.pa-stat-group :deep(.hcis-stat__value) {
    font-size: 1.25rem !important;
    line-height: 1.2 !important;
    font-weight: 800 !important;
}

.pa-stat-group :deep(.hcis-stat__label) {
    font-size: 0.68rem !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.025em !important;
    color: var(--hcis-text-muted, #64748b) !important;
}

.pa-stat-group :deep(.hcis-stat__caption) {
    font-size: 0.62rem !important;
    margin-top: 0.08rem !important;
    color: var(--hcis-text-subtle, #94a3b8) !important;
}

.pa-stat-group :deep(.hcis-stat__icon-box) {
    width: 1.5rem !important;
    height: 1.5rem !important;
    border-radius: 6px !important;
}

.pa-skeleton-cockpit {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 0.75rem !important;
    width: 100%;
}

.pa-riwayat-stat-card {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    transition: all 140ms ease;
}

.pa-riwayat-stat-card:hover {
    border-color: rgba(199, 210, 254, 0.9);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
}

.pa-riwayat-stat-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: var(--hcis-radius-md, 8px);
    flex-shrink: 0;
}

.pa-riwayat-stat-icon.is-brand {
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
}

.pa-riwayat-stat-icon.is-purple {
    background: #f5f3ff;
    color: #7c3aed;
    border: 1px solid #ddd6fe;
}

.pa-riwayat-stat-icon.is-warning {
    background: #fffbeb;
    color: #d97706;
    border: 1px solid #fde68a;
}

.pa-riwayat-stat-icon.is-success {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}

.pa-riwayat-stat-content {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pa-riwayat-stat-label {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.2;
}

.pa-riwayat-stat-val-row {
    display: flex;
    align-items: baseline;
    gap: 0.3rem;
    margin-top: 0.15rem;
}

.pa-riwayat-stat-num {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.15;
}

.pa-riwayat-stat-unit {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-riwayat-stat-sub {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #94a3b8);
    margin-top: 0.15rem;
}

.text-purple {
    color: #7c3aed !important;
}

.text-warning {
    color: #d97706 !important;
}

.text-success {
    color: #059669 !important;
}

.text-brand {
    color: #2563eb !important;
}

.text-teal {
    color: #0d9488 !important;
}

.font-num {
    font-variant-numeric: tabular-nums;
}

/* ==========================================================================
   TAB 1: Performance Insights Strip & Charts
   ========================================================================== */
.pa-trend-insights-strip {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.75rem;
}

.pa-trend-insight-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.9));
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    transition: all 140ms ease;
}

.pa-trend-insight-item:hover {
    transform: translateY(-1px);
    border-color: rgba(199, 210, 254, 0.9);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
}

.pa-insight-icon {
    display: grid;
    place-items: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: var(--hcis-radius-md, 8px);
    flex-shrink: 0;
}

.pa-insight-icon.is-brand {
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
}

.pa-insight-icon.is-success {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}

.pa-insight-icon.is-purple {
    background: #f5f3ff;
    color: #7c3aed;
    border: 1px solid #ddd6fe;
}

.pa-insight-icon.is-teal {
    background: #f0fdfa;
    color: #0d9488;
    border: 1px solid #99f6e4;
}

.pa-insight-content {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pa-insight-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.2;
}

.pa-insight-val-row {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
    flex-wrap: wrap;
    margin-top: 0.15rem;
}

.pa-insight-num {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.2;
}

.pa-insight-unit {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-insight-delta {
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    font-size: 0.66rem;
    font-weight: 750;
    padding: 0.08rem 0.35rem;
    border-radius: 4px;
}

.pa-insight-delta.is-up {
    background: #ecfdf5;
    color: #059669;
}

.pa-insight-delta.is-down {
    background: #fef2f2;
    color: #dc2626;
}

.pa-main-chart-card {
    width: 100%;
}

.pa-supporting-charts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.pa-support-chart-item {
    min-width: 0;
}

.pa-chart-loading-box,
.pa-chart-empty-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 240px;
    padding: 2rem;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-lg, 12px);
}

/* ==========================================================================
   Historical Cycle Summary Cards & Comparative Table
   ========================================================================== */
.pa-riwayat-trend-card {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 1.15rem;
    border: 1px solid rgba(139, 92, 246, 0.22);
    border-radius: var(--hcis-radius-xl, 16px);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(245, 243, 255, 0.45));
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.pa-riwayat-trend-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.pa-trend-head-left {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
}

.pa-trend-head-right {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.pa-trend-icon-box {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f5f3ff;
    border: 1px solid #ddd6fe;
    flex-shrink: 0;
}

.pa-trend-title {
    margin: 0.1rem 0 0;
    font-size: 0.95rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-trend-subtitle {
    margin: 0.15rem 0 0;
    font-size: 0.74rem;
    color: var(--hcis-text-muted, #64748b);
}

/* Trend Cards Grid */
.pa-trend-items-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 0.85rem;
}

.pa-trend-item-card {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    padding: 0.9rem 1rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    transition: transform 140ms ease, box-shadow 140ms ease, border-color 140ms ease;
}

.pa-trend-item-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
    border-color: #8b5cf6;
}

.pa-trend-item-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.35rem;
}

.pa-trend-cycle-name {
    font-size: 0.78rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #1e293b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-trend-year-pill {
    font-size: 0.66rem;
    font-weight: 650;
    padding: 0.08rem 0.4rem;
    border-radius: 4px;
    background: #f1f5f9;
    color: #475569;
}

.pa-trend-score-row {
    display: flex;
    align-items: baseline;
    gap: 0.25rem;
}

.pa-trend-score {
    font-size: 1.45rem;
    font-weight: 800;
    line-height: 1.1;
    color: #6d28d9;
}

.pa-trend-scale {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-trend-range-box {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.35rem 0.5rem;
    border-radius: 6px;
    background: #f8fafc;
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.pa-trend-range-labels {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.65rem;
    color: var(--hcis-text-muted, #64748b);
    font-weight: 600;
}

.pa-trend-range-track {
    position: relative;
    width: 100%;
    height: 4px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}

.pa-trend-range-bar {
    position: absolute;
    top: 0;
    bottom: 0;
    border-radius: 999px;
    background: linear-gradient(90deg, #818cf8 0%, #6366f1 100%);
}

.pa-trend-item-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.35rem;
    padding-top: 0.4rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.9);
}

.pa-trend-count {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.7rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-trend-link-btn {
    border: none;
    background: transparent;
    padding: 0;
    font: inherit;
    font-size: 0.72rem;
    font-weight: 700;
    color: #4f46e5;
    cursor: pointer;
    transition: color 120ms ease;
}

.pa-trend-link-btn:hover {
    color: #3730a3;
    text-decoration: underline;
}

.pa-trend-item-actions {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding-top: 0.4rem;
    border-top: 1px solid rgba(241, 245, 249, 0.95);
}

.pa-trend-action-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.45rem;
    border-radius: 4px;
    border: 1px solid rgba(203, 213, 225, 0.7);
    background: #f8fafc;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.68rem;
    font-weight: 650;
    text-decoration: none;
    cursor: pointer;
    transition: all 120ms ease;
}

.pa-trend-action-chip:hover {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1d4ed8;
}

/* Comparative Table Ledger */
.pa-trend-table-wrap {
    width: 100%;
    overflow-x: auto;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-lg, 12px);
}

.pa-trend-comparative-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.78rem;
    text-align: left;
}

.pa-trend-comparative-table th {
    padding: 0.65rem 0.85rem;
    background: #f8fafc;
    color: var(--hcis-text-muted, #475569);
    font-weight: 700;
    font-size: 0.72rem;
    letter-spacing: 0.02em;
    border-bottom: 1px solid rgba(226, 232, 240, 0.9);
    white-space: nowrap;
}

.pa-trend-comparative-table td {
    padding: 0.7rem 0.85rem;
    border-bottom: 1px solid rgba(241, 245, 249, 0.95);
    vertical-align: middle;
}

.pa-trend-table-cycle {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.pa-trend-table-cycle-name {
    font-size: 0.8rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-trend-table-cycle-code {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-trend-score-cell {
    display: flex;
    align-items: baseline;
    gap: 0.25rem;
}

.pa-trend-score-sm {
    font-size: 0.95rem;
    font-weight: 800;
    color: #6d28d9;
}

.pa-trend-scale-sm {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-trend-table-range {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.76rem;
    color: var(--hcis-text-strong, #334155);
}

.pa-range-sep {
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-trend-table-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
}

.pa-table-quick-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.6rem;
    height: 1.6rem;
    border-radius: 4px;
    border: 1px solid rgba(203, 213, 225, 0.8);
    background: #f8fafc;
    color: var(--hcis-text-muted, #64748b);
    text-decoration: none;
    transition: all 120ms ease;
}

.pa-table-quick-link:hover {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1d4ed8;
}

.pa-eyebrow {
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.08em;
}

/* ==========================================================================
   TAB 2: Controls, Filters, Table & Cards
   ========================================================================== */
.pa-cycle-context-alert {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #eff6ff;
    border: 1px solid #bfdbfe;
}

.pa-cycle-context-left {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.pa-context-icon-box {
    display: grid;
    place-items: center;
    flex-shrink: 0;
}

.pa-context-info {
    display: flex;
    flex-direction: column;
    font-size: 0.74rem;
    color: #1e40af;
}

.pa-context-info strong {
    font-weight: 750;
}

.pa-context-info span {
    font-size: 0.7rem;
    color: #3b82f6;
}

.pa-riwayat-controls-card {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.pa-riwayat-controls-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.pa-riwayat-quick-chips {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.pa-riwayat-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.65rem;
    border-radius: 999px;
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: #f8fafc;
    color: var(--hcis-text-muted, #475569);
    font: inherit;
    font-size: 0.74rem;
    font-weight: 650;
    cursor: pointer;
    transition: all 140ms ease;
}

.pa-riwayat-chip:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.pa-riwayat-chip.is-active {
    background: #eff6ff;
    border-color: #3b82f6;
    color: #1d4ed8;
    box-shadow: 0 0 0 1px #3b82f6;
}

.pa-riwayat-chip.is-warning.is-active {
    background: #fffbeb;
    border-color: #f59e0b;
    color: #b45309;
    box-shadow: 0 0 0 1px #f59e0b;
}

.pa-riwayat-chip.is-success.is-active {
    background: #ecfdf5;
    border-color: #10b981;
    color: #047857;
    box-shadow: 0 0 0 1px #10b981;
}

.pa-chip-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.pa-chip-dot.is-warning { background: #f59e0b; }
.pa-chip-dot.is-success { background: #10b981; }

.pa-riwayat-chip-count {
    font-size: 0.7rem;
    font-weight: 750;
    padding: 0.05rem 0.35rem;
    background: rgba(0, 0, 0, 0.06);
    border-radius: 999px;
}

.pa-view-switch {
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    padding: 0.2rem;
    background: #f1f5f9;
    border-radius: var(--hcis-radius-sm, 6px);
    border: 1px solid rgba(203, 213, 225, 0.6);
}

.pa-view-switch-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.55rem;
    border: none;
    border-radius: 4px;
    background: transparent;
    color: var(--hcis-text-muted, #64748b);
    font: inherit;
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 120ms ease;
}

.pa-view-switch-btn:hover {
    color: var(--hcis-text-strong, #0f172a);
}

.pa-view-switch-btn.is-active {
    background: #ffffff;
    color: #2563eb;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
}

/* Secondary Filter Row */
.pa-riwayat-filters-row {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    flex-wrap: wrap;
    padding-top: 0.6rem;
    border-top: 1px solid rgba(241, 245, 249, 0.95);
}

.pa-search-box {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.7rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    border: 1px solid rgba(203, 213, 225, 0.8);
    flex: 1;
    min-width: 240px;
}

.pa-search-input {
    width: 100%;
    border: none;
    background: transparent;
    font: inherit;
    font-size: 0.76rem;
    color: var(--hcis-text-strong, #0f172a);
    outline: none;
}

.pa-search-input::placeholder {
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-search-clear-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    padding: 0;
}

.pa-search-clear-btn:hover {
    color: #64748b;
}

.pa-select-wrap {
    position: relative;
}

.pa-filter-select {
    padding: 0.4rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    border: 1px solid rgba(203, 213, 225, 0.8);
    font: inherit;
    font-size: 0.74rem;
    color: var(--hcis-text-strong, #334155);
    outline: none;
    cursor: pointer;
}

.pa-filter-select:focus {
    border-color: #3b82f6;
    background: #ffffff;
}

/* Skeletons */
.pa-riwayat-loading {
    padding: 1rem 0;
}

.pa-riwayat-skeleton-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.9rem;
}

.pa-riwayat-skeleton-card {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
}

/* Table Presentation */
.pa-riwayat-content-area {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pa-riwayat-table-container {
    display: flex;
    flex-direction: column;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-lg, 12px);
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.pa-table-count-strip {
    padding: 0.5rem 0.85rem;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
    background: #f8fafc;
    border-bottom: 1px solid rgba(226, 232, 240, 0.8);
}

.pa-table-scroll-wrap {
    width: 100%;
    overflow-x: auto;
}

.pa-riwayat-main-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.78rem;
    text-align: left;
}

.pa-riwayat-main-table th {
    padding: 0.65rem 0.85rem;
    background: #f8fafc;
    color: var(--hcis-text-muted, #475569);
    font-weight: 700;
    font-size: 0.72rem;
    letter-spacing: 0.02em;
    border-bottom: 1px solid rgba(226, 232, 240, 0.9);
    white-space: nowrap;
}

.pa-riwayat-main-table td {
    padding: 0.7rem 0.85rem;
    border-bottom: 1px solid rgba(241, 245, 249, 0.95);
    vertical-align: middle;
}

.pa-riwayat-tr--interactive {
    cursor: pointer;
    transition: background 120ms ease;
}

.pa-riwayat-tr--interactive:hover {
    background: #f8fafc;
}

.pa-table-person-cell {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    min-width: 0;
}

.pa-person-meta {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pa-person-name {
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.8rem;
    font-weight: 750;
    line-height: 1.25;
}

.pa-person-sub-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-nik-pill {
    display: inline-flex;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    background: rgba(15, 23, 42, 0.06);
    color: #334155;
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.65rem;
    font-weight: 700;
}

.pa-level-pill {
    display: inline-flex;
    padding: 0.05rem 0.4rem;
    border-radius: 4px;
    background: rgba(99, 102, 241, 0.08);
    color: #4338ca;
    font-size: 0.65rem;
    font-weight: 700;
}

.pa-table-job-cell {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.pa-job-title {
    font-size: 0.78rem;
    font-weight: 650;
    color: var(--hcis-text-strong, #1e293b);
    line-height: 1.25;
}

.pa-job-div {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-table-cycle-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-cycle-name {
    font-size: 0.78rem;
    font-weight: 650;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-cycle-year-badge {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
}

/* Pillar Scores Cell */
.pa-pillar-scores-cell {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-score-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.pa-score-tag {
    font-size: 0.62rem;
    font-weight: 750;
    padding: 0.08rem 0.35rem;
    border-radius: 4px;
    text-transform: uppercase;
}

.pa-score-tag.is-kpi {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}

.pa-score-tag.is-komp {
    background: #f5f3ff;
    color: #6d28d9;
    border: 1px solid #ddd6fe;
}

.pa-score-val {
    font-size: 0.78rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

/* Final Score & Grade */
.pa-final-grade-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-final-num {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--hcis-brand-700, #1d4ed8);
}

.pa-grade-badge-wrap {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: wrap;
}

.pa-calibrated-tag {
    font-size: 0.62rem;
    font-weight: 700;
    padding: 0.08rem 0.35rem;
    border-radius: 4px;
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
}

.pa-quadrant-tag {
    display: inline-block;
    padding: 0.15rem 0.45rem;
    border-radius: var(--hcis-radius-xs, 4px);
    background: rgba(139, 92, 246, 0.1);
    color: #7c3aed;
    font-size: 0.7rem;
    font-weight: 700;
}

.pa-no-grade-text {
    font-size: 0.72rem;
    color: #94a3b8;
}

/* Grid Cards View */
.pa-riwayat-cards-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.85rem;
}

.pa-riwayat-hcis-card {
    transition: transform 140ms ease, box-shadow 140ms ease;
}

.pa-riwayat-hcis-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px -2px rgba(15, 23, 42, 0.08);
}

.pa-card-header-inner {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
    width: 100%;
}

.pa-card-person-flex {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
}

.pa-card-person-meta {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pa-card-person-link {
    border: none;
    background: transparent;
    padding: 0;
    font: inherit;
    font-size: 0.78rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    text-align: left;
    cursor: pointer;
}

.pa-card-person-link:hover {
    color: #4f46e5;
}

.pa-card-person-sub {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-card-body-content {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.pa-card-meta-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
}

.pa-card-job-pill {
    font-size: 0.72rem;
    font-weight: 650;
    color: var(--hcis-text-strong, #334155);
}

.pa-card-cycle-pill {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    background: #f1f5f9;
}

.pa-card-scores-strip {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.4rem;
    padding: 0.5rem 0.65rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.pa-score-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.1rem;
    text-align: center;
}

.pa-score-col-label {
    font-size: 0.64rem;
    color: var(--hcis-text-muted, #64748b);
    font-weight: 600;
}

.pa-score-col-val {
    font-size: 0.85rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-card-grade-hav-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
    padding-top: 0.2rem;
}

.pa-grade-box {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.pa-grade-label {
    font-size: 0.72rem;
    font-weight: 650;
    color: var(--hcis-text-muted, #64748b);
}

.pa-card-footer-actions {
    display: flex;
    justify-content: flex-end;
    width: 100%;
}

/* Pagination */
.pa-riwayat-pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
    padding: 0.75rem 1rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.pa-pagination-info {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
}

/* ==========================================================================
   TAB 3: JEJAK AUDIT SIKLUS (MODERN DESIGN SYSTEM)
   ========================================================================== */
.pa-audit-selector-card {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0.9rem 1.1rem;
    border-radius: var(--hcis-radius-xl, 14px);
}

.pa-audit-selector-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.pa-audit-selector-title-col {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    min-width: 0;
}

.pa-audit-sel-icon-box {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.35rem;
    height: 2.35rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    flex-shrink: 0;
}

.pa-audit-title-text {
    margin: 0.1rem 0 0.15rem;
    font-size: 0.95rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-audit-desc-text {
    margin: 0;
    max-width: 48rem;
    font-size: 0.74rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.45;
}

.pa-audit-selector-controls {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.pa-audit-select-field {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.35rem 0.65rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    border: 1px solid rgba(203, 213, 225, 0.85);
}

.pa-audit-sel-label {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #334155);
    white-space: nowrap;
}

.pa-audit-cycle-native-select {
    border: none;
    background: transparent;
    font: inherit;
    font-size: 0.74rem;
    font-weight: 600;
    color: var(--hcis-text-strong, #0f172a);
    outline: none;
    cursor: pointer;
    max-width: 260px;
}

.pa-audit-quick-cycles-bar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-top: 0.65rem;
    border-top: 1px solid rgba(241, 245, 249, 0.9);
}

.pa-quick-bar-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: var(--hcis-text-muted, #64748b);
    white-space: nowrap;
}

.pa-quick-chips-scroll {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 0.1rem;
    width: 100%;
}

.pa-quick-chips-scroll::-webkit-scrollbar {
    display: none;
}

.pa-quick-cycle-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.22rem 0.55rem;
    border-radius: 999px;
    border: 1px solid rgba(203, 213, 225, 0.8);
    background: #ffffff;
    font: inherit;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #475569);
    cursor: pointer;
    transition: all 130ms ease;
    white-space: nowrap;
}

.pa-quick-cycle-pill:hover {
    border-color: #93c5fd;
    background: #eff6ff;
    color: #1d4ed8;
}

.pa-quick-cycle-pill.is-active {
    border-color: #3b82f6;
    background: #eff6ff;
    color: #1d4ed8;
    font-weight: 750;
    box-shadow: 0 0 0 1px #3b82f6;
}

.pa-qc-year {
    font-size: 0.64rem;
    padding: 0.05rem 0.3rem;
    border-radius: 4px;
    background: rgba(15, 23, 42, 0.06);
}

.pa-audit-loading {
    padding: 1rem 0;
}

.pa-audit-skeleton-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pa-audit-skeleton-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.9rem 1rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-audit-timeline-container {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-audit-timeline-stream {
    width: 100%;
}

.pa-audit-event-card-body {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.pa-audit-item-desc {
    margin: 0;
    font-size: 0.78rem;
    line-height: 1.5;
    color: var(--hcis-text-strong, #1e293b);
}

.pa-audit-sample-targets {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
    padding: 0.35rem 0.55rem;
    border-radius: 6px;
    background: rgba(241, 245, 249, 0.7);
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.pa-sample-label {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-text-muted, #64748b);
}

.pa-sample-tags-wrap {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: wrap;
}

.pa-sample-tag {
    display: inline-block;
    padding: 0.08rem 0.38rem;
    border-radius: 4px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
    font-size: 0.68rem;
    font-weight: 600;
}

.pa-sample-more-tag {
    font-size: 0.68rem;
    font-weight: 750;
    color: #4f46e5;
}

.pa-audit-justification-box {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    padding: 0.4rem 0.65rem;
    border-radius: 6px;
    background: #fffbeb;
    border-left: 3px solid #f59e0b;
    border-top: 1px solid #fef3c7;
    border-right: 1px solid #fef3c7;
    border-bottom: 1px solid #fef3c7;
}

.pa-quote-icon {
    color: #d97706;
    margin-top: 0.1rem;
    flex-shrink: 0;
}

.pa-quote-text {
    margin: 0;
    font-size: 0.74rem;
    font-style: italic;
    color: #92400e;
    line-height: 1.4;
}

.pa-audit-pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
    padding: 0.75rem 1rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

/* ==========================================================================
   RESPONSIVE BREAKPOINTS (Multi-Device Compact Architecture)
   ========================================================================== */
@media (max-width: 1200px) {
    .pa-stat-group,
    .pa-skeleton-cockpit,
    .pa-riwayat-metrics-grid,
    .pa-trend-insights-strip {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
    .pa-supporting-charts-grid {
        grid-template-columns: 1fr;
    }
    .pa-riwayat-cards-grid,
    .pa-riwayat-skeleton-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 1024px) {
    .pa-trend-items-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {
    .pa-riwayat-page {
        padding: 0 0 5rem 0;
        gap: 0.85rem;
    }

    .pa-riwayat-header-actions {
        width: 100%;
        justify-content: flex-end;
    }

    .pa-cockpit-section {
        gap: 0.4rem;
    }

    .pa-stat-group,
    .pa-skeleton-cockpit {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.4rem !important;
        margin-bottom: 0.25rem !important;
    }

    .pa-stat-group :deep(.hcis-stat),
    .pa-stat-group .hcis-stat {
        min-height: unset !important;
        height: auto !important;
        padding: 0.45rem 0.55rem !important;
        border-radius: var(--hcis-radius-sm, 8px) !important;
    }

    .pa-stat-group :deep(.hcis-stat__compact-wrap) {
        gap: 0.15rem !important;
    }

    .pa-stat-group :deep(.hcis-stat__value) {
        font-size: 1.15rem !important;
        line-height: 1.15 !important;
        font-weight: 800 !important;
    }

    .pa-stat-group :deep(.hcis-stat__label) {
        font-size: 0.64rem !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.02em !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .pa-stat-group :deep(.hcis-stat__caption) {
        font-size: 0.58rem !important;
        margin-top: 0.05rem !important;
        color: var(--hcis-slate-500, #64748b) !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .pa-stat-group :deep(.hcis-stat__icon-box) {
        width: 1.35rem !important;
        height: 1.35rem !important;
        border-radius: 5px !important;
    }

    .pa-monitor-tabs {
        width: 100%;
        overflow-x: auto;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
        padding: 0.25rem;
        gap: 0.25rem;
    }

    .pa-monitor-tab {
        padding: 0.35rem 0.65rem;
        font-size: 0.72rem;
    }

    .pa-admin-switcher {
        width: 100%;
        overflow-x: auto;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
        padding: 0.25rem;
        gap: 0.25rem;
    }

    .pa-admin-switcher__item {
        padding: 0.3rem 0.55rem;
        font-size: 0.7rem;
    }

    /* 2x2 Bento Metrics Grid on Mobile */
    .pa-riwayat-metrics-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.45rem;
    }

    .pa-riwayat-stat-card {
        padding: 0.65rem 0.75rem;
        border-radius: var(--hcis-radius-md, 12px);
        gap: 0.45rem;
        min-height: auto;
    }

    .pa-riwayat-stat-card:active {
        transform: scale(0.985);
    }

    .pa-riwayat-stat-icon {
        width: 1.85rem;
        height: 1.85rem;
        border-radius: 7px;
    }

    .pa-riwayat-stat-label {
        font-size: 0.62rem;
        font-weight: 750;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: #64748b;
    }

    .pa-riwayat-stat-num {
        font-size: 1.25rem;
        font-weight: 850;
        line-height: 1.15;
    }

    .pa-riwayat-stat-unit {
        font-size: 0.7rem;
    }

    .pa-riwayat-stat-sub {
        font-size: 0.64rem;
        line-height: 1.25;
        color: #64748b;
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* 2x2 Bento Performance Insights Strip on Mobile */
    .pa-trend-insights-strip {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.45rem;
    }

    .pa-trend-insight-item {
        padding: 0.65rem 0.75rem;
        border-radius: var(--hcis-radius-md, 12px);
        gap: 0.45rem;
    }

    .pa-insight-icon {
        width: 1.85rem;
        height: 1.85rem;
        border-radius: 7px;
    }

    .pa-insight-label {
        font-size: 0.62rem;
        font-weight: 750;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        color: #64748b;
    }

    .pa-insight-num {
        font-size: 1.25rem;
        font-weight: 850;
        line-height: 1.15;
    }

    .pa-insight-unit {
        font-size: 0.7rem;
    }

    .pa-insight-delta {
        font-size: 0.65rem;
        padding: 0.08rem 0.35rem;
    }

    .pa-supporting-charts-grid {
        grid-template-columns: 1fr;
        gap: 0.85rem;
    }

    /* Historical Cycle Summary */
    .pa-riwayat-trend-card {
        padding: 0.85rem;
        border-radius: var(--hcis-radius-lg, 12px);
    }

    .pa-riwayat-trend-card__head {
        flex-direction: column;
        align-items: stretch;
        gap: 0.65rem;
    }

    .pa-trend-head-right {
        justify-content: space-between;
        width: 100%;
        flex-wrap: wrap;
        gap: 0.45rem;
    }

    .pa-trend-items-grid {
        grid-template-columns: 1fr;
        gap: 0.6rem;
    }

    .pa-trend-item-card {
        padding: 0.85rem 0.95rem;
        border-radius: var(--hcis-radius-md, 12px);
    }

    .pa-trend-score {
        font-size: 1.45rem;
        font-weight: 850;
    }

    .pa-trend-item-actions {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.35rem;
    }

    .pa-trend-action-chip {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.3rem;
        padding: 0.38rem 0.5rem;
        font-size: 0.72rem;
        font-weight: 700;
        border-radius: 6px;
    }

    /* Context Alert */
    .pa-cycle-context-alert {
        flex-direction: column;
        align-items: stretch;
        gap: 0.55rem;
        padding: 0.75rem 0.85rem;
        border-radius: 10px;
    }

    /* Controls & Filter Bar (ARSIP) */
    .pa-riwayat-controls-card {
        padding: 0.75rem 0.85rem;
        border-radius: var(--hcis-radius-lg, 12px);
        gap: 0.65rem;
    }

    .pa-riwayat-controls-top {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }

    .pa-riwayat-quick-chips {
        display: flex;
        gap: 0.35rem;
        overflow-x: auto;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 0.15rem;
        width: 100%;
        flex-wrap: nowrap;
    }

    .pa-riwayat-chip {
        padding: 0.35rem 0.65rem;
        font-size: 0.72rem;
        flex-shrink: 0;
        border-radius: 8px;
    }

    .pa-view-switch {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .pa-view-switch-btn {
        justify-content: center;
        padding: 0.35rem;
        font-size: 0.74rem;
    }

    .pa-riwayat-filters-row {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.45rem;
        width: 100%;
    }

    .pa-search-box {
        grid-column: 1 / -1;
        width: 100%;
    }

    .pa-select-wrap,
    .pa-filter-select {
        width: 100%;
        height: 2.1rem;
        font-size: 0.74rem;
        border-radius: 8px;
    }

    .pa-riwayat-filters-row :deep(.hcis-button) {
        grid-column: 1 / -1;
        width: 100%;
        justify-content: center;
        min-height: 2rem;
    }

    /* Cards Grid View (ARSIP) */
    .pa-riwayat-cards-grid,
    .pa-riwayat-skeleton-grid {
        grid-template-columns: 1fr;
        gap: 0.65rem;
    }

    .pa-riwayat-hcis-card {
        border-radius: var(--hcis-radius-lg, 12px);
    }

    .pa-card-scores-strip {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.35rem;
        padding: 0.45rem 0.6rem;
        border-radius: 8px;
        background: rgba(241, 245, 249, 0.7);
    }

    .pa-score-col-label {
        font-size: 0.62rem;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 750;
    }

    .pa-score-col-val {
        font-size: 0.95rem;
        font-weight: 850;
    }

    .pa-card-footer-actions :deep(.hcis-button) {
        width: 100%;
        justify-content: center;
        min-height: 2.1rem;
    }

    /* Audit Section (AUDIT) */
    .pa-audit-header {
        flex-direction: column;
        align-items: stretch;
        gap: 0.65rem;
    }

    .pa-audit-cockpit-right {
        flex-direction: column;
        align-items: stretch;
        gap: 0.45rem;
        width: 100%;
    }

    /* Audit Section (AUDIT) */
    .pa-audit-selector-inner {
        flex-direction: column;
        align-items: stretch;
        gap: 0.65rem;
    }

    .pa-audit-selector-controls {
        flex-direction: column;
        align-items: stretch;
        gap: 0.45rem;
        width: 100%;
    }

    .pa-audit-select-field {
        width: 100%;
        justify-content: space-between;
    }

    .pa-audit-cycle-native-select {
        max-width: 100%;
        width: 100%;
    }

    .pa-audit-selector-controls :deep(.hcis-button) {
        width: 100%;
        justify-content: center;
    }

    .pa-audit-quick-cycles-bar {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.35rem;
    }

    /* Pagination */
    .pa-riwayat-pagination-bar,
    .pa-audit-pagination-bar {
        flex-direction: column;
        align-items: stretch;
        gap: 0.65rem;
        text-align: center;
    }

    .pa-trend-comparative-table th,
    .pa-trend-comparative-table td {
        padding: 0.5rem 0.6rem;
    }

    .pa-trend-table-actions {
        flex-wrap: wrap;
        justify-content: flex-start;
    }
}

@media (max-width: 440px) {
    .pa-riwayat-metrics-grid {
        gap: 0.4rem;
    }

    .pa-riwayat-stat-card {
        padding: 0.6rem 0.65rem;
    }

    .pa-riwayat-stat-num {
        font-size: 1.15rem;
    }

    .pa-trend-insights-strip {
        gap: 0.4rem;
    }

    .pa-trend-insight-item {
        padding: 0.6rem 0.65rem;
    }

    .pa-insight-num {
        font-size: 1.15rem;
    }

    .pa-riwayat-filters-row {
        grid-template-columns: 1fr;
    }
}
</style>
