<template>
    <main class="pa-distribusi-page">
        <!-- Unified Header -->
        <HcisHeader
            title="Distribusi Performance &amp; Kurva Normal"
            kicker="PERFORMANCE APPRAISAL · ANALISIS SEBARAN HASIL"
            description="Analisis sebaran hasil penilaian organisasi, pemodelan kurva distribusi normal per tingkat jabatan, kepatuhan kuota grade korporasi, dan ringkasan performa talenta."
            icon="bar-chart-fill"
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
                <div class="pa-distribusi-header-actions">
                    <!-- Premium Period Selector Trigger Button -->
                    <button
                        type="button"
                        class="pa-period-trigger"
                        :disabled="loadingSiklus"
                        :class="{ 'pa-period-trigger--loading': loadingSiklus }"
                        aria-haspopup="dialog"
                        :aria-expanded="bukaDialogPeriode"
                        title="Klik untuk memilih siklus &amp; periode penilaian"
                        @click="bukaDialogPeriode = true"
                    >
                        <div class="pa-period-trigger__jewel">
                            <HcisIcon name="calendar2-range-fill" size="xs" />
                        </div>
                        <div class="pa-period-trigger__body">
                            <span class="pa-period-trigger__label">
                                PERIODE AKTIF
                                <span v-if="activeCycle?.Tahun" class="pa-period-trigger__year">{{ activeCycle.Tahun }}</span>
                            </span>
                            <span class="pa-period-trigger__value">
                                {{ activeCycle ? (activeCycle.Nama_Cycle || activeCycle.Kode_Cycle) : (loadingSiklus ? 'Memuat siklus...' : 'Pilih Siklus') }}
                            </span>
                        </div>
                        <span v-if="activeCycle" class="pa-period-trigger__badge-wrap">
                            <HcisBadge
                                :label="getStatusCycleLabel(activeCycle.Status_Cycle)"
                                :variant="getStatusSentiment(activeCycle.Status_Cycle)"
                                appearance="glass"
                                size="xs"
                                dot
                            />
                        </span>
                        <div class="pa-period-trigger__chevron">
                            <HcisIcon name="chevron-down" size="2xs" />
                        </div>
                    </button>

                    <HcisButton
                        label="Segarkan"
                        variant="glass"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :loading="loadingDistribusi"
                        @click="muatDataDistribusi"
                    />

                    <HcisButton
                        label="Export PDF"
                        variant="primary"
                        sentiment="brand"
                        size="sm"
                        leading-icon="file-earmark-pdf-fill"
                        :disabled="!selectedCycleId || loadingDistribusi"
                        :loading="loadingExportPdf"
                        @click="eksporLaporanPdf"
                    />

                    <HcisButton
                        :href="`/performance-appraisal/pemantauan${selectedCycleId ? `?cycle=${selectedCycleId}&tab=HASIL` : '?tab=HASIL'}`"
                        label="Monitoring &amp; Hasil"
                        variant="secondary"
                        size="sm"
                        leading-icon="hourglass-split"
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
                    <a href="/performance-appraisal/hav-map" class="pa-admin-switcher__item">
                        <HcisIcon name="grid-3x3" size="2xs" />
                        <span>9-Box HAV Map</span>
                    </a>
                    <a href="/performance-appraisal/distribusi" class="pa-admin-switcher__item active pa-admin-switcher__item--active">
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

        <!-- Executive Cockpit Stat Group -->
        <HcisStatGroup :cols="4" gap="sm" class="pa-distribusi-stats">
            <HcisStatCard
                layout="compact"
                label="Total Partisipan Dinilai"
                :value="formatAngka(totalPesertaDinilai)"
                :caption="`Tercakup dalam ${levelStatsList.length} tingkatan jabatan`"
                icon="people-fill"
                sentiment="brand"
                pattern="rings"
            />
            <HcisStatCard
                layout="compact"
                label="Rata-rata Skor (Mean)"
                :value="statData.rerata != null ? `${Number(statData.rerata).toFixed(1)}%` : '—'"
                :caption="`KPI: ${formatRerata(statData.rerata_kpi)} · Komp: ${formatRerata(statData.rerata_kompetensi)}`"
                icon="calculator"
                sentiment="brand"
                pattern="hex"
            />
            <HcisStatCard
                layout="compact"
                label="Simpangan Baku (Std Dev)"
                :value="statData.simpangan_baku != null ? `± ${Number(statData.simpangan_baku).toFixed(1)}%` : '—'"
                :caption="`Rentang sebaran: ${formatNumber(statData.min)}% s/d ${formatNumber(statData.maks)}%`"
                icon="graph-up"
                sentiment="purple"
                pattern="grid"
            />
            <HcisStatCard
                layout="compact"
                label="Kelayakan Kurva Lonceng"
                :value="cukupUntukKurva ? 'Memenuhi Syarat' : 'Sampel Terbatas'"
                :caption="cukupUntukKurva ? 'Distribusi normal tervalidasi' : alasanKurvaDitahan"
                icon="bell"
                :sentiment="cukupUntukKurva ? 'success' : 'warning'"
                pattern="dots"
            />
        </HcisStatGroup>

        <!-- Error State -->
        <HcisResultState
            v-if="errorDistribusi"
            :status="errorStatus === 403 ? 'warning' : 'danger'"
            variant="flat"
            layout="inline"
            :title="errorStatus === 403 ? 'Akses ditolak' : 'Gagal memuat distribusi nilai'"
            :description="errorDistribusi"
            primary-text="Coba Lagi"
            primary-icon="arrow-clockwise"
            :show-retry="errorStatus !== 403"
            @primary-click="muatDataDistribusi"
        />

        <!-- Loading State -->
        <div v-else-if="loadingDistribusi && !distribusiData.grade?.length" class="pa-distribusi-loading">
            <div class="pa-distribusi-skeleton-grid">
                <div v-for="n in 3" :key="`skel-dist-${n}`" class="pa-distribusi-skeleton-card">
                    <HcisSkeleton width="220px" height="1.4rem" rounded="sm" />
                    <HcisSkeleton width="100%" height="10rem" rounded="md" />
                </div>
            </div>
        </div>

        <!-- Loaded Content -->
        <div v-else class="pa-distribusi-content">
            <!-- Kurva grade: kunci/buka dan batas grade per grup level (siklus bermetode KURVA). -->
            <!-- Laporan hanya membaca: kunci/buka kurva dikerjakan di Penutupan Siklus (spec 2026-10-02). -->
            <a
                v-if="selectedCycleId"
                class="pa-kurva-pindah"
                :href="`/performance-appraisal/pemantauan?tahap=kurva&cycle=${selectedCycleId}`"
                data-uji="tautan-tahap-kurva"
            >
                <HcisIcon name="lock-fill" size="xs" />
                <span><strong>Atur grup &amp; kunci kurva grade</strong> ada di Penutupan Siklus → tahap Kurva Normal.</span>
                <b>Buka &rarr;</b>
            </a>

            <!-- ================================================================= -->
            <!-- SECTION 1: Analisis Kurva Normal per Tingkat Jabatan -->
            <!-- ================================================================= -->
            <HcisCard
                variant="glass"
                sentiment="brand"
                pattern="rings"
                class="pa-section-card"
            >
                <template #header>
                    <div class="pa-section-header">
                        <div class="pa-section-header__title-group">
                            <span class="pa-eyebrow">DISTRIBUSI NORMAL MULTI-LEVEL</span>
                            <h2 class="pa-section-title">Analisis Kurva Normal per Tingkat Jabatan</h2>
                            <p class="pa-section-desc">
                                Persebaran Nilai KPI dan Nilai Kompetensi dimodelkan sebagai kurva distribusi normal (Gaussian PDF) untuk setiap tingkat jabatan, guna melihat pola sebaran dan variasi nilai (mean &amp; standar deviasi) antar level jabatan.
                            </p>
                        </div>
                        <div class="pa-section-header__badge-group">
                            <HcisBadge
                                label="Gaussian Modeling"
                                variant="brand"
                                appearance="glass"
                                size="sm"
                                dot
                            />
                        </div>
                    </div>
                </template>

                <!-- Kurva hanya bila ada level dengan data cukup (aturan server, minimal 30 nilai). -->
                <div v-if="!adaKurvaLevel" class="pa-histogram-empty" data-uji="kurva-level-ditahan">
                    <HcisIcon name="info-circle" size="xs" color="warning" />
                    <span>{{ alasanKurvaDitahan }}</span>
                </div>

                <!-- Dual Bell Curves Grid -->
                <div v-else class="pa-normal-curves-grid">
                    <!-- Curve 1: Nilai KPI per Tingkat Jabatan -->
                    <div class="pa-curve-box">
                        <div class="pa-curve-box__head">
                            <div class="d-flex align-items-center gap-2">
                                <span class="pa-curve-dot pa-curve-dot--kpi" />
                                <h3 class="pa-curve-title">Distribusi Normal — Nilai KPI per Tingkat Jabatan</h3>
                            </div>
                            <span class="pa-curve-meta">Sumbu X: Skor KPI (%) · Sumbu Y: Densitas Probabilitas</span>
                        </div>
                        <div class="pa-curve-chart-wrapper">
                            <HcisChart
                                type="custom"
                                :options="kpiBellCurveChartOptions"
                                :height="340"
                                :card="false"
                                variant="flat"
                            />
                        </div>
                    </div>

                    <!-- Curve 2: Nilai Kompetensi per Tingkat Jabatan -->
                    <div class="pa-curve-box">
                        <div class="pa-curve-box__head">
                            <div class="d-flex align-items-center gap-2">
                                <span class="pa-curve-dot pa-curve-dot--komp" />
                                <h3 class="pa-curve-title">Distribusi Normal — Nilai Kompetensi per Tingkat Jabatan</h3>
                            </div>
                            <span class="pa-curve-meta">Sumbu X: Skor Kompetensi (%) · Sumbu Y: Densitas Probabilitas</span>
                        </div>
                        <div class="pa-curve-chart-wrapper">
                            <HcisChart
                                type="custom"
                                :options="kompetensiBellCurveChartOptions"
                                :height="340"
                                :card="false"
                                variant="flat"
                            />
                        </div>
                    </div>
                </div>

                <!-- Level Legend Chips -->
                <div class="pa-level-legend-wrap">
                    <span class="pa-level-legend-label">Tingkat Jabatan:</span>
                    <div class="pa-level-chips">
                        <div
                            v-for="lvl in levelStatsList"
                            :key="lvl.nama"
                            class="pa-level-chip"
                        >
                            <span class="pa-level-chip__color" :style="{ backgroundColor: lvl.color }" />
                            <span class="pa-level-chip__name">{{ lvl.nama }}</span>
                            <span class="pa-level-chip__count">({{ formatAngka(lvl.jumlah) }} org)</span>
                        </div>
                    </div>
                </div>
            </HcisCard>

            <!-- ================================================================= -->
            <!-- SECTION 2: Ringkasan Statistik per Tingkat Jabatan (Table) -->
            <!-- ================================================================= -->
            <HcisCard
                variant="glass"
                sentiment="purple"
                pattern="grid"
                class="pa-section-card"
            >
                <template #header>
                    <div class="pa-section-header">
                        <div class="pa-section-header__title-group">
                            <span class="pa-eyebrow">PARAMETRIK &amp; DISPERSI</span>
                            <h2 class="pa-section-title">Ringkasan Statistik per Tingkat Jabatan</h2>
                            <p class="pa-section-desc">
                                Rangkuman jumlah populasi, nilai rata-rata (mean), serta simpangan baku (standar deviasi) masing-masing pilar penilaian pada tiap tingkatan struktural.
                            </p>
                        </div>
                    </div>
                </template>

                <div class="pa-table-responsive-wrap">
                    <table class="pa-stats-table">
                        <thead>
                            <tr>
                                <th class="text-left">Tingkat Jabatan</th>
                                <th class="text-center">Jml Karyawan</th>
                                <th class="text-center">Rata-rata KPI</th>
                                <th class="text-center">Std. Deviasi KPI</th>
                                <th class="text-center">Rata-rata Kompetensi</th>
                                <th class="text-center">Std. Deviasi Kompetensi</th>
                                <th class="text-center">Karakteristik Dispersi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!levelStatsList.length">
                                <td colspan="7" class="pa-tabel-kosong">Belum ada hasil penilaian di cycle ini.</td>
                            </tr>
                            <tr
                                v-for="row in levelStatsList"
                                :key="row.nama"
                                class="pa-stats-table__row"
                            >
                                <td>
                                    <div class="pa-level-badge-cell">
                                        <span class="pa-level-indicator" :style="{ backgroundColor: row.color }" />
                                        <div class="pa-level-name-wrap">
                                            <strong class="pa-level-name">{{ row.nama }}</strong>
                                            <span class="pa-level-sub">Proporsi: {{ calcLevelPercentage(row.jumlah) }}%</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center font-num font-bold">
                                    {{ formatAngka(row.jumlah) }}
                                </td>
                                <td class="text-center font-num font-bold text-indigo-700">
                                    <div class="pa-metric-cell">
                                        <span>{{ row.rata_kpi != null ? `${Number(row.rata_kpi).toFixed(1)}%` : '—' }}</span>
                                        <div class="pa-mini-bar-track">
                                            <div
                                                class="pa-mini-bar-fill pa-mini-bar-fill--kpi"
                                                :style="{ width: `${Math.min(100, Number(row.rata_kpi) || 0)}%` }"
                                            />
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center font-num text-slate-600">
                                    {{ row.sd_kpi != null ? `± ${Number(row.sd_kpi).toFixed(1)}%` : '—' }}
                                </td>
                                <td class="text-center font-num font-bold text-teal-700">
                                    <div class="pa-metric-cell">
                                        <span>{{ row.rata_kompetensi != null ? `${Number(row.rata_kompetensi).toFixed(1)}%` : '—' }}</span>
                                        <div class="pa-mini-bar-track">
                                            <div
                                                class="pa-mini-bar-fill pa-mini-bar-fill--komp"
                                                :style="{ width: `${Math.min(100, Number(row.rata_kompetensi) || 0)}%` }"
                                            />
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center font-num text-slate-600">
                                    {{ row.sd_kompetensi != null ? `± ${Number(row.sd_kompetensi).toFixed(1)}%` : '—' }}
                                </td>
                                <td class="text-center">
                                    <HcisBadge
                                        :label="getDispersionLabel(row.sd_kpi, row.sd_kompetensi)"
                                        :variant="getDispersionVariant(row.sd_kpi, row.sd_kompetensi)"
                                        appearance="glass"
                                        size="xs"
                                    />
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="pa-stats-table__footer-row">
                                <td>
                                    <strong>Total Organisasi / Rata-rata Gabungan</strong>
                                </td>
                                <td class="text-center font-num font-bold">
                                    {{ formatAngka(totalPesertaDinilai) }}
                                </td>
                                <td class="text-center font-num font-bold text-indigo-700">
                                    {{ statData.rerata_kpi != null ? `${Number(statData.rerata_kpi).toFixed(1)}%` : '—' }}
                                </td>
                                <td class="text-center font-num text-slate-600">
                                    {{ statData.standar_deviasi_kpi != null ? `± ${Number(statData.standar_deviasi_kpi).toFixed(1)}%` : '—' }}
                                </td>
                                <td class="text-center font-num font-bold text-teal-700">
                                    {{ statData.rerata_kompetensi != null ? `${Number(statData.rerata_kompetensi).toFixed(1)}%` : '—' }}
                                </td>
                                <td class="text-center font-num text-slate-600">
                                    {{ statData.standar_deviasi_kompetensi != null ? `± ${Number(statData.standar_deviasi_kompetensi).toFixed(1)}%` : '—' }}
                                </td>
                                <td class="text-center font-num text-xs text-slate-500">
                                    Mean: {{ Number(statData.rerata || 0).toFixed(1) }}%
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </HcisCard>

            <!-- ================================================================= -->
            <!-- SECTION 3: Distribusi Nilai (Bell Curve) & Distribusi Grade -->
            <!-- ================================================================= -->
            <div class="pa-corporate-dist-grid">
                <!-- Card A: Distribusi Nilai Akhir Korporasi (Bell Curve & Histogram) -->
                <HcisCard
                    variant="glass"
                    sentiment="purple"
                    pattern="rings"
                    class="pa-corp-bell-card"
                >
                    <template #header>
                        <div class="pa-section-header">
                            <div class="pa-section-header__title-group">
                                <span class="pa-eyebrow">DISTRIBUSI NILAI (BELL CURVE)</span>
                                <h3 class="pa-card-title-lg">Sebaran Frekuensi Nilai &amp; Kurva Lonceng Korporasi</h3>
                            </div>
                            <HcisBadge
                                :label="cukupUntukKurva ? 'Kurva Normal Aktif' : 'Kurva Ditahan'"
                                :variant="cukupUntukKurva ? 'success' : 'warning'"
                                size="sm"
                                appearance="glass"
                                glow
                                dot
                            />
                        </div>
                    </template>

                    <div v-if="histogramBins.length" class="pa-histogram-chart-wrapper">
                        <HcisChart
                            type="custom"
                            :options="corporateBellCurveChartOptions"
                            :height="320"
                            :card="false"
                            variant="flat"
                        />
                    </div>
                    <div v-else class="pa-histogram-empty">
                        <HcisIcon name="info-circle" size="xs" color="warning" />
                        <span>{{ alasanKurvaDitahan }}</span>
                    </div>
                </HcisCard>

                <!-- Card B: Distribusi Grade & Kepatuhan Kuota -->
                <HcisCard
                    variant="glass"
                    sentiment="brand"
                    pattern="grid"
                    class="pa-corp-grade-card"
                >
                    <template #header>
                        <div class="pa-section-header">
                            <div class="pa-section-header__title-group">
                                <span class="pa-eyebrow">{{
                                    metodeKurva ? 'DISTRIBUSI GRADE · KURVA NORMAL' : 'DISTRIBUSI GRADE & KUOTA'
                                }}</span>
                                <h3 class="pa-card-title-lg">
                                    {{
                                        metodeKurva
                                            ? 'Perbandingan Aktual vs Proporsi Teoretis'
                                            : 'Perbandingan Aktual vs Target Persentase'
                                    }}
                                </h3>
                            </div>
                            <HcisBadge
                                v-if="distribusiData.grade_sementara"
                                label="Sementara · kurva belum dikunci"
                                variant="warning"
                                size="xs"
                                data-uji="grade-sementara"
                            />
                        </div>
                    </template>

                    <div class="pa-grade-table-wrap">
                        <table class="pa-grade-distribution-table">
                            <thead>
                                <tr>
                                    <th>Grade</th>
                                    <th>Kategori</th>
                                    <th class="text-center">Jml</th>
                                    <th class="text-center">Aktual</th>
                                    <th class="text-center">{{ metodeKurva ? 'Teoretis' : 'Target' }}</th>
                                    <th class="text-center">Deviasi</th>
                                    <th>Proporsi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!gradeList.length">
                                    <td colspan="7" class="pa-tabel-kosong">Belum ada hasil bergrade di cycle ini.</td>
                                </tr>
                                <tr
                                    v-for="g in gradeList"
                                    :key="g.kode || g.label"
                                    :class="{ 'pa-row-deviation': isSignificantDeviation(g) }"
                                >
                                    <td>
                                        <span class="pa-grade-tag" :style="{ backgroundColor: getGradeColor(g.kode) }">
                                            {{ g.kode }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="pa-grade-label-wrap">
                                            <strong class="pa-grade-category-title">{{ g.label }}</strong>
                                            <span v-if="getGradeSubtext(g.kode)" class="pa-grade-category-sub">{{ getGradeSubtext(g.kode) }}</span>
                                        </div>
                                    </td>
                                    <td class="text-center font-num font-bold">
                                        {{ formatAngka(g.jumlah) }}
                                    </td>
                                    <td class="text-center font-num font-bold text-indigo-700">
                                        {{ formatNumber(g.persen) }}%
                                    </td>
                                    <td class="text-center font-num text-slate-500">
                                        {{ g.target_persen != null ? `${formatNumber(g.target_persen)}%` : '—' }}
                                    </td>
                                    <td class="text-center font-num">
                                        <span
                                            v-if="g.selisih != null"
                                            class="pa-diff-badge"
                                            :class="{
                                                'pa-diff-badge--plus': Number(g.selisih) > 0,
                                                'pa-diff-badge--minus': Number(g.selisih) < 0,
                                                'pa-diff-badge--zero': Number(g.selisih) === 0,
                                            }"
                                        >
                                            {{ Number(g.selisih) > 0 ? `+${formatNumber(g.selisih)}%` : `${formatNumber(g.selisih)}%` }}
                                        </span>
                                        <span v-else class="text-slate-400">—</span>
                                    </td>
                                    <td>
                                        <div class="pa-table-bar-track">
                                            <div
                                                class="pa-table-bar-fill"
                                                :style="{
                                                    width: `${Math.min(100, Number(g.persen) || 0)}%`,
                                                    backgroundColor: getGradeColor(g.kode),
                                                }"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </HcisCard>
            </div>

            <!-- ================================================================= -->
            <!-- SECTION 4: Talent Highlights (3-Card Section) -->
            <!-- ================================================================= -->
            <div class="pa-talent-highlights-section">
                <div class="pa-talent-section-head">
                    <span class="pa-eyebrow">TALENT HIGHLIGHTS &amp; SEGMENTASI</span>
                    <h2 class="pa-section-title">Sorotan Talenta Berdasarkan Hasil Penilaian</h2>
                    <p class="pa-section-desc">
                        Identifikasi instan karyawan dengan pencapaian puncak (Top Performer), talenta berkemampuan tinggi (High Potential), dan karyawan yang membutuhkan program peningkatan kinerja (Need Improvement).
                    </p>
                </div>

                <div class="pa-talent-cards-grid">
                    <!-- Card 1: 🏆 Top Performer -->
                    <HcisCard
                        variant="glass"
                        sentiment="success"
                        pattern="rings"
                        class="pa-talent-card pa-talent-card--top"
                    >
                        <template #header>
                            <div class="pa-talent-card-header">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="pa-talent-icon">🏆</span>
                                    <div>
                                        <h3 class="pa-talent-card-title">Grade Tertinggi</h3>
                                        <span class="pa-talent-card-subtitle">{{ kriteriaTalenta.teratas }}</span>
                                    </div>
                                </div>
                                <HcisBadge
                                    :label="`${topPerformersList.length} Talenta`"
                                    variant="success"
                                    appearance="glass"
                                    size="xs"
                                />
                            </div>
                        </template>

                        <div class="pa-talent-list">
                            <p v-if="!topPerformersList.length" class="pa-talent-kosong" data-uji="talenta-kosong">Belum ada peserta dengan grade tertinggi.</p>
                            <div
                                v-for="(item, idx) in topPerformersList"
                                :key="item.participant_id || item.nama || idx"
                                class="pa-talent-item"
                            >
                                <div class="pa-talent-item__left">
                                    <span class="pa-talent-rank">#{{ idx + 1 }}</span>
                                    <HcisAvatar
                                        :name="item.nama"
                                        size="sm"
                                        shape="circle"
                                        variant="glass"
                                        sentiment="success"
                                    />
                                    <div class="pa-talent-info">
                                        <strong class="pa-talent-name">{{ item.nama }}</strong>
                                        <span class="pa-talent-sub">{{ item.level }} · {{ item.divisi }}</span>
                                    </div>
                                </div>
                                <div class="pa-talent-item__right">
                                    <span class="pa-talent-score-pill pa-talent-score-pill--success">
                                        {{ Number(item.skor).toFixed(0) }}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </HcisCard>

                    <!-- Card 2: ⭐ High Potential -->
                    <HcisCard
                        variant="glass"
                        sentiment="brand"
                        pattern="hex"
                        class="pa-talent-card pa-talent-card--hipo"
                    >
                        <template #header>
                            <div class="pa-talent-card-header">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="pa-talent-icon">⭐</span>
                                    <div>
                                        <h3 class="pa-talent-card-title">Potensi Tinggi (HAV)</h3>
                                        <span class="pa-talent-card-subtitle">Kuadran HAV berkompetensi tinggi</span>
                                    </div>
                                </div>
                                <HcisBadge
                                    :label="`${highPotentialList.length} Talenta`"
                                    variant="brand"
                                    appearance="glass"
                                    size="xs"
                                />
                            </div>
                        </template>

                        <div class="pa-talent-list">
                            <p v-if="!highPotentialList.length" class="pa-talent-kosong" data-uji="talenta-kosong">Belum ada peserta di kuadran berkompetensi tinggi.</p>
                            <div
                                v-for="(item, idx) in highPotentialList"
                                :key="item.participant_id || item.nama || idx"
                                class="pa-talent-item"
                            >
                                <div class="pa-talent-item__left">
                                    <span class="pa-talent-rank pa-talent-rank--brand">#{{ idx + 1 }}</span>
                                    <HcisAvatar
                                        :name="item.nama"
                                        size="sm"
                                        shape="circle"
                                        variant="glass"
                                        sentiment="brand"
                                    />
                                    <div class="pa-talent-info">
                                        <strong class="pa-talent-name">{{ item.nama }}</strong>
                                        <span class="pa-talent-sub">{{ item.level }} · {{ item.divisi }}</span>
                                    </div>
                                </div>
                                <div class="pa-talent-item__right">
                                    <span class="pa-talent-score-pill pa-talent-score-pill--brand">
                                        {{ Number(item.skor).toFixed(0) }}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </HcisCard>

                    <!-- Card 3: ⚠ Need Improvement -->
                    <HcisCard
                        variant="glass"
                        sentiment="danger"
                        pattern="grid"
                        class="pa-talent-card pa-talent-card--needs"
                    >
                        <template #header>
                            <div class="pa-talent-card-header">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="pa-talent-icon">⚠</span>
                                    <div>
                                        <h3 class="pa-talent-card-title">Grade Terendah</h3>
                                        <span class="pa-talent-card-subtitle">{{ kriteriaTalenta.terbawah }}</span>
                                    </div>
                                </div>
                                <HcisBadge
                                    :label="`${needImprovementList.length} Talenta`"
                                    variant="danger"
                                    appearance="glass"
                                    size="xs"
                                />
                            </div>
                        </template>

                        <div class="pa-talent-list">
                            <p v-if="!needImprovementList.length" class="pa-talent-kosong" data-uji="talenta-kosong">Tidak ada peserta dengan grade terendah.</p>
                            <div
                                v-for="(item, idx) in needImprovementList"
                                :key="item.participant_id || item.nama || idx"
                                class="pa-talent-item"
                            >
                                <div class="pa-talent-item__left">
                                    <span class="pa-talent-rank pa-talent-rank--danger">!</span>
                                    <HcisAvatar
                                        :name="item.nama"
                                        size="sm"
                                        shape="circle"
                                        variant="glass"
                                        sentiment="danger"
                                    />
                                    <div class="pa-talent-info">
                                        <strong class="pa-talent-name">{{ item.nama }}</strong>
                                        <span class="pa-talent-sub">{{ item.level }} · {{ item.divisi }}</span>
                                    </div>
                                </div>
                                <div class="pa-talent-item__right">
                                    <span class="pa-talent-score-pill pa-talent-score-pill--danger">
                                        {{ Number(item.skor).toFixed(0) }}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </HcisCard>
                </div>
            </div>

            <!-- ================================================================= -->
            <!-- DIALOG: Pemilih Siklus & Periode Penilaian (Rich Period Selector) -->
            <!-- ================================================================= -->
            <HcisDialog
                v-model="bukaDialogPeriode"
                title="Pilih Siklus &amp; Periode Penilaian"
                subtitle="Pilih assessment cycle untuk memuat analisis sebaran nilai, kurva lonceng normal, dan sorotan talenta."
                icon="calendar2-range-fill"
                sentiment="brand"
                variant="glass"
                size="lg"
                pattern="grid"
            >
                <div class="pa-period-dialog">
                    <!-- Search & Filter Controls -->
                    <div class="pa-period-dialog__controls">
                        <div class="pa-period-dialog__search-wrap">
                            <HcisIcon name="search" size="xs" class="pa-period-dialog__search-icon" />
                            <input
                                v-model="kataCariSiklus"
                                type="text"
                                class="pa-period-dialog__search-input"
                                placeholder="Cari nama siklus, kode, atau tahun (contoh: 2025, Semester 1)..."
                                aria-label="Cari siklus penilaian"
                            />
                            <button
                                v-if="kataCariSiklus"
                                type="button"
                                class="pa-period-dialog__clear-btn"
                                title="Hapus pencarian"
                                @click="kataCariSiklus = ''"
                            >
                                <HcisIcon name="x-circle-fill" size="2xs" />
                            </button>
                        </div>

                        <div class="pa-period-dialog__filter-tabs" role="tablist" aria-label="Filter status siklus">
                            <button
                                type="button"
                                class="pa-filter-tab"
                                :class="{ 'pa-filter-tab--active': tabStatusSiklus === 'SEMUA' }"
                                @click="tabStatusSiklus = 'SEMUA'"
                            >
                                Semua
                                <span class="pa-filter-tab__count">{{ siklusList.length }}</span>
                            </button>
                            <button
                                type="button"
                                class="pa-filter-tab"
                                :class="{ 'pa-filter-tab--active': tabStatusSiklus === 'READY' }"
                                @click="tabStatusSiklus = 'READY'"
                            >
                                Siap Analisis
                                <span class="pa-filter-tab__count">{{ cacahSiklusReady }}</span>
                            </button>
                            <button
                                type="button"
                                class="pa-filter-tab"
                                :class="{ 'pa-filter-tab--active': tabStatusSiklus === 'ACTIVE' }"
                                @click="tabStatusSiklus = 'ACTIVE'"
                            >
                                Sedang Berjalan
                                <span class="pa-filter-tab__count">{{ cacahSiklusActive }}</span>
                            </button>
                            <button
                                type="button"
                                class="pa-filter-tab"
                                :class="{ 'pa-filter-tab--active': tabStatusSiklus === 'OTHER' }"
                                @click="tabStatusSiklus = 'OTHER'"
                            >
                                Arsip / Lainnya
                                <span class="pa-filter-tab__count">{{ cacahSiklusOther }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Cycle Grid List -->
                    <div v-if="filteredSiklusList.length" class="pa-period-dialog__grid">
                        <div
                            v-for="c in filteredSiklusList"
                            :key="c.Id_PA_Cycle"
                            class="pa-cycle-card"
                            :class="{
                                'pa-cycle-card--selected': Number(c.Id_PA_Cycle) === Number(selectedCycleId),
                            }"
                            role="button"
                            tabindex="0"
                            @click="pilihSiklus(c)"
                            @keydown.enter="pilihSiklus(c)"
                            @keydown.space.prevent="pilihSiklus(c)"
                        >
                            <div class="pa-cycle-card__header">
                                <div class="pa-cycle-card__badges">
                                    <span class="pa-cycle-code-chip">{{ c.Kode_Cycle || 'NO-CODE' }}</span>
                                    <span v-if="c.Tahun" class="pa-cycle-year-chip">Tahun {{ c.Tahun }}</span>
                                </div>
                                <HcisBadge
                                    :label="getStatusCycleLabel(c.Status_Cycle)"
                                    :variant="getStatusSentiment(c.Status_Cycle)"
                                    appearance="glass"
                                    size="xs"
                                    dot
                                />
                            </div>

                            <div class="pa-cycle-card__body">
                                <h4 class="pa-cycle-card__title">
                                    {{ c.Nama_Cycle || c.Kode_Cycle || 'Siklus Penilaian Kinerja' }}
                                </h4>

                                <div class="pa-cycle-card__meta-list">
                                    <div class="pa-cycle-meta-item">
                                        <HcisIcon name="calendar-event" size="2xs" class="text-slate-400" />
                                        <span>Periode: <strong>{{ formatTanggalPeriode(c.Periode_Dinilai_Dari, c.Periode_Dinilai_Sampai) }}</strong></span>
                                    </div>
                                    <div v-if="c.Pengisian_Dari || c.Pengisian_Sampai" class="pa-cycle-meta-item">
                                        <HcisIcon name="pencil-square" size="2xs" class="text-slate-400" />
                                        <span>Pengisian: <strong>{{ formatTanggalPeriode(c.Pengisian_Dari, c.Pengisian_Sampai) }}</strong></span>
                                    </div>
                                </div>
                            </div>

                            <div class="pa-cycle-card__footer">
                                <div v-if="Number(c.Id_PA_Cycle) === Number(selectedCycleId)" class="pa-cycle-card__selected-pill">
                                    <HcisIcon name="check-circle-fill" size="xs" />
                                    <span>Siklus Terpilih</span>
                                </div>
                                <div v-else class="pa-cycle-card__select-action">
                                    <span>Pilih Siklus</span>
                                    <HcisIcon name="arrow-right-short" size="sm" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="pa-period-dialog__empty">
                        <HcisEmptyState
                            title="Tidak ada siklus yang sesuai"
                            description="Coba ubah kata kunci pencarian atau ganti filter status siklus."
                            icon="search"
                            variant="glass"
                            size="sm"
                        >
                            <template #action>
                                <HcisButton
                                    label="Reset Pencarian &amp; Filter"
                                    variant="secondary"
                                    size="xs"
                                    leading-icon="arrow-counterclockwise"
                                    @click="resetFilterSiklus"
                                />
                            </template>
                        </HcisEmptyState>
                    </div>
                </div>

                <template #footer>
                    <div class="pa-period-dialog__footer-bar">
                        <span class="pa-period-dialog__info-text">
                            Menampilkan <strong>{{ filteredSiklusList.length }}</strong> dari <strong>{{ siklusList.length }}</strong> siklus
                        </span>
                        <HcisButton
                            label="Tutup"
                            variant="ghost"
                            size="sm"
                            @click="bukaDialogPeriode = false"
                        />
                    </div>
                </template>
            </HcisDialog>
        </div>
    </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import * as echarts from 'echarts';
import {
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisChart,
    HcisDialog,
    HcisEmptyState,
    HcisHeader,
    HcisIcon,
    HcisResultState,
    HcisSkeleton,
    HcisStatCard,
    HcisStatGroup,
} from '@/components/ui';
import paApi from '../api/paApi.js';
import { cycleDariUrl } from '../utils/cycleDariUrl.js';

defineOptions({ name: 'PerformanceAppraisalDistribusiPage' });

const loadingSiklus = ref(false);
const loadingDistribusi = ref(false);
const errorDistribusi = ref('');
const errorStatus = ref(0);

const siklusList = ref([]);
const selectedCycleId = ref('');
const distribusiData = ref({});
const loadingExportPdf = ref(false);

/* Period Selector Dialog State */
const bukaDialogPeriode = ref(false);
const kataCariSiklus = ref('');
const tabStatusSiklus = ref('SEMUA');

const cacahSiklusReady = computed(() => {
    return siklusList.value.filter((c) => c.Status_Cycle === 'CALCULATED' || c.Status_Cycle === 'PUBLISHED').length;
});

const cacahSiklusActive = computed(() => {
    return siklusList.value.filter((c) => c.Status_Cycle === 'OPEN' || c.Status_Cycle === 'LOCKED').length;
});

const cacahSiklusOther = computed(() => {
    return siklusList.value.filter((c) => !['CALCULATED', 'PUBLISHED', 'OPEN', 'LOCKED'].includes(c.Status_Cycle)).length;
});

const filteredSiklusList = computed(() => {
    let list = siklusList.value;

    if (tabStatusSiklus.value === 'READY') {
        list = list.filter((c) => c.Status_Cycle === 'CALCULATED' || c.Status_Cycle === 'PUBLISHED');
    } else if (tabStatusSiklus.value === 'ACTIVE') {
        list = list.filter((c) => c.Status_Cycle === 'OPEN' || c.Status_Cycle === 'LOCKED');
    } else if (tabStatusSiklus.value === 'OTHER') {
        list = list.filter((c) => !['CALCULATED', 'PUBLISHED', 'OPEN', 'LOCKED'].includes(c.Status_Cycle));
    }

    const q = kataCariSiklus.value.trim().toLowerCase();
    if (q) {
        list = list.filter((c) => {
            const nama = (c.Nama_Cycle || '').toLowerCase();
            const kode = (c.Kode_Cycle || '').toLowerCase();
            const tahun = String(c.Tahun || '');
            const status = (c.Status_Cycle || '').toLowerCase();
            const statusLbl = getStatusCycleLabel(c.Status_Cycle).toLowerCase();
            return nama.includes(q) || kode.includes(q) || tahun.includes(q) || status.includes(q) || statusLbl.includes(q);
        });
    }

    return list;
});

function resetFilterSiklus() {
    kataCariSiklus.value = '';
    tabStatusSiklus.value = 'SEMUA';
}

function pilihSiklus(cycle) {
    if (!cycle) return;
    selectedCycleId.value = String(cycle.Id_PA_Cycle);
    bukaDialogPeriode.value = false;

    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('cycle', String(cycle.Id_PA_Cycle));
        window.history.replaceState({}, '', url.toString());
    }

    muatDataDistribusi();
}

function eksporLaporanPdf() {
    if (!selectedCycleId.value) return;
    const url = paApi.exportDistribusiPdfUrl(selectedCycleId.value);
    window.open(url, '_blank');
}

const activeCycle = computed(() => {
    return siklusList.value.find((c) => Number(c.Id_PA_Cycle) === Number(selectedCycleId.value)) || null;
});

const statData = computed(() => {
    const k = distribusiData.value.keseluruhan || distribusiData.value.statistik || {};
    return {
        rerata: k.rata_rata ?? k.rerata ?? null,
        simpangan_baku: k.standar_deviasi ?? k.simpangan_baku ?? null,
        min: k.terendah ?? k.min ?? null,
        maks: k.tertinggi ?? k.maks ?? null,
        jumlah: k.jumlah ?? 0,
        /* Hanya angka dari server. Dulu yang kosong diisi turunan karangan (rata-rata akhir
           × 1,01 untuk KPI, × 0,98 untuk kompetensi) dan terbaca sebagai statistik asli. */
        rerata_kpi: k.rata_kpi ?? k.rerata_kpi ?? null,
        standar_deviasi_kpi: k.standar_deviasi_kpi ?? k.sd_kpi ?? null,
        rerata_kompetensi: k.rata_kompetensi ?? k.rerata_kompetensi ?? null,
        standar_deviasi_kompetensi: k.standar_deviasi_kompetensi ?? k.sd_kompetensi ?? null,
    };
});

const LEVEL_COLOR_PALETTE = ['#64748b', '#0d9488', '#2563eb', '#7c3aed', '#059669', '#d97706', '#dc2626'];

/* Statistik per level APA ADANYA dari server. Tidak ada lagi level contoh (620 Staff, 340
   Supervisor, ...) saat siklus belum punya hasil, dan tidak ada rata-rata/simpangan baku
   patokan (75, 10, ...) untuk yang kosong — angka karangan terbaca sebagai data asli. */
const levelStatsList = computed(() => (distribusiData.value.per_level || [])
    .filter((lvl) => Number(lvl.jumlah || 0) > 0)
    .map((lvl, idx) => ({
        id_level: lvl.id_level,
        nama: lvl.nama_level || `Level ${idx + 1}`,
        color: LEVEL_COLOR_PALETTE[idx % LEVEL_COLOR_PALETTE.length],
        jumlah: Number(lvl.jumlah || 0),
        rata_kpi: lvl.rata_kpi ?? null,
        sd_kpi: lvl.standar_deviasi_kpi ?? null,
        rata_kompetensi: lvl.rata_kompetensi ?? null,
        sd_kompetensi: lvl.standar_deviasi_kompetensi ?? null,
        // Ambang kurva milik server (minimal 30 nilai); layar tidak menggambar di bawahnya.
        cukup_untuk_kurva: Boolean(lvl.cukup_untuk_kurva),
    })));

/* Level yang boleh digambar sebagai kurva normal: cukup datanya dan statistiknya lengkap. */
function levelBerkurva(isKpi) {
    return levelStatsList.value.filter((l) => {
        const sd = isKpi ? l.sd_kpi : l.sd_kompetensi;
        const rata = isKpi ? l.rata_kpi : l.rata_kompetensi;
        return l.cukup_untuk_kurva && rata != null && sd != null && Number(sd) > 0;
    });
}
const adaKurvaLevel = computed(() => levelBerkurva(true).length > 0 || levelBerkurva(false).length > 0);

const totalPesertaDinilai = computed(() => {
    if (statData.value.jumlah > 0) {
        return statData.value.jumlah;
    }
    return levelStatsList.value.reduce((acc, curr) => acc + curr.jumlah, 0);
});

/* Siklus KURVA: pembandingnya proporsi teoretis kurva normal, dan sebelum kurva dikunci
   angkanya dari grade SEMENTARA (server menandainya lewat `grade_sementara`). */
const metodeKurva = computed(() => distribusiData.value.metode_grade === 'KURVA');

const gradeList = computed(() => {
    const raw = distribusiData.value.grade || [];
    if (raw.length > 0) {
        return raw.map((g) => {
            const rawKode = g.kode_grade || g.kode || g.Kode_Range || '—';
            const cleanKode = displayGrade(rawKode);
            return {
                kode: cleanKode,
                raw_kode: rawKode,
                label: formatGradeCategory(rawKode, g.nama_grade || g.label),
                jumlah: Number(g.jumlah || 0),
                persen: Number(g.persen || 0),
                target_persen: g.target_persen ?? null,
                selisih: g.selisih ?? null,
            };
        });
    }
    // Belum ada hasil: daftar kosong, bukan sebaran contoh.
    return [];
});

const histogramBins = computed(() => {
    const raw = distribusiData.value.histogram || [];
    if (raw.length > 0) {
        return raw.map((b) => ({
            batas_bawah: b.dari ?? b.batas_bawah ?? 0,
            batas_atas: b.sampai ?? b.batas_atas ?? 0,
            jumlah: Number(b.jumlah || 0),
            label: b.label || (b.dari !== undefined ? `${b.dari}-${b.sampai}` : '—'),
        }));
    }
    return [];
});

const cukupUntukKurva = computed(() => {
    const k = distribusiData.value.keseluruhan || distribusiData.value;
    return Boolean(k.cukup_untuk_kurva ?? true);
});

const alasanKurvaDitahan = computed(() => {
    const k = distribusiData.value.keseluruhan || distribusiData.value;
    return k.alasan_kurva_ditahan || 'Populasi sampel belum mencukupi untuk pembentukan kurva normal.';
});

/* Sorotan talenta HANYA dari server. Dulu daftar yang kosong diisi nama karangan
   ("Andi Rahman", "Siti Wulandari", "Karyawan #4021") yang terbaca sebagai karyawan nyata. */
/* Dasar daftar dari server: peringkat ikut grade (urutan pita siklus), potensi ikut HAV. */
const kriteriaTalenta = computed(() => {
    const k = distribusiData.value.talent_highlights?.kriteria || {};
    const daftar = (kode) => (Array.isArray(kode) && kode.length ? `Grade ${kode.map(displayGrade).join(', ')}` : 'Grade —');
    return { teratas: daftar(k.grade_teratas), terbawah: daftar(k.grade_terbawah) };
});

const topPerformersList = computed(() => {
    const raw = distribusiData.value.talent_highlights?.top_performers;
    if (raw && raw.length > 0) return raw;
    return [];
});

const highPotentialList = computed(() => {
    const raw = distribusiData.value.talent_highlights?.high_potential;
    if (raw && raw.length > 0) return raw;
    return [];
});

const needImprovementList = computed(() => {
    const raw = distribusiData.value.talent_highlights?.need_improvement;
    if (raw && raw.length > 0) return raw;
    return [];
});

/* ------------------------------------------------------------------ */
/* Mathematical Gaussian PDF Normal Distribution Calculation */
/* ------------------------------------------------------------------ */
function calcGaussianPdf(x, mean, stdDev) {
    if (!stdDev || stdDev <= 0) return 0;
    const factor = 1 / (stdDev * Math.sqrt(2 * Math.PI));
    const exponent = -0.5 * Math.pow((x - mean) / stdDev, 2);
    return factor * Math.exp(exponent) * 100; // Scaled to % density
}

/* Common score range for continuous curves: 30% to 110% in steps of 2% */
const SCORE_X_STEPS = Array.from({ length: 41 }, (_, i) => 30 + i * 2);

function buildMultiLevelNormalCurveOptions(type = 'kpi') {
    const isKpi = type === 'kpi';
    const levels = levelBerkurva(isKpi);

    const series = levels.map((lvl) => {
        const mean = Number(isKpi ? lvl.rata_kpi : lvl.rata_kompetensi);
        const sd = Number(isKpi ? lvl.sd_kpi : lvl.sd_kompetensi);

        const curvePoints = SCORE_X_STEPS.map((x) => [x, Number(calcGaussianPdf(x, mean, sd).toFixed(3))]);

        return {
            name: lvl.nama,
            type: 'line',
            smooth: true,
            showSymbol: false,
            symbolSize: 4,
            lineStyle: {
                width: 2.8,
                color: lvl.color,
            },
            itemStyle: {
                color: lvl.color,
            },
            areaStyle: {
                color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                    { offset: 0, color: hexToRgba(lvl.color, 0.22) },
                    { offset: 1, color: hexToRgba(lvl.color, 0.0) },
                ]),
            },
            data: curvePoints,
        };
    });

    return {
        tooltip: {
            trigger: 'axis',
            backgroundColor: 'rgba(15, 23, 42, 0.94)',
            borderColor: 'rgba(255, 255, 255, 0.12)',
            borderWidth: 1,
            padding: [10, 14],
            textStyle: { color: '#ffffff', fontSize: 11 },
            formatter: (params) => {
                if (!params || !params.length) return '';
                const score = params[0].data ? params[0].data[0] : params[0].axisValue;
                let html = `<div style="font-weight: 800; margin-bottom: 6px; color: #a5b4fc; font-size: 12px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 4px;">
                    Skor ${isKpi ? 'KPI' : 'Kompetensi'}: ${score}%
                </div>`;
                params.forEach((p) => {
                    const lvl = levels.find((l) => l.nama === p.seriesName);
                    const meanVal = isKpi ? lvl?.rata_kpi : lvl?.rata_kompetensi;
                    const sdVal = isKpi ? lvl?.sd_kpi : lvl?.sd_kompetensi;
                    const density = p.data ? p.data[1] : 0;
                    html += `<div style="display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-top: 3px;">
                        <span style="display: flex; align-items: center; gap: 6px;">
                            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: ${p.color};"></span>
                            <span style="color: #e2e8f0; font-weight: 600;">${p.seriesName}:</span>
                        </span>
                        <strong style="color: #ffffff;">${density}% (Mean: ${meanVal}%, ±${sdVal}%)</strong>
                    </div>`;
                });
                return html;
            },
        },
        legend: {
            show: true,
            top: 2,
            right: 0,
            icon: 'circle',
            itemWidth: 8,
            itemHeight: 8,
            textStyle: { color: '#475569', fontSize: 11, fontWeight: 600 },
        },
        grid: {
            top: 38,
            right: 18,
            bottom: 28,
            left: 36,
            containLabel: true,
        },
        xAxis: {
            type: 'value',
            min: 30,
            max: 110,
            interval: 10,
            axisLabel: {
                formatter: '{value}%',
                color: '#64748b',
                fontSize: 10.5,
                fontWeight: 600,
            },
            axisLine: { lineStyle: { color: '#cbd5e1' } },
            splitLine: { lineStyle: { color: 'rgba(203, 213, 225, 0.4)', type: 'dashed' } },
        },
        yAxis: {
            type: 'value',
            name: 'Kepadatan Probabilitas (PDF)',
            nameTextStyle: { color: '#94a3b8', fontSize: 10, padding: [0, 0, 0, 10] },
            axisLabel: {
                formatter: '{value}%',
                color: '#64748b',
                fontSize: 10,
            },
            splitLine: { lineStyle: { color: 'rgba(203, 213, 225, 0.4)', type: 'dashed' } },
        },
        series,
    };
}

const kpiBellCurveChartOptions = computed(() => buildMultiLevelNormalCurveOptions('kpi'));
const kompetensiBellCurveChartOptions = computed(() => buildMultiLevelNormalCurveOptions('kompetensi'));

/* Overall Corporate Bell Curve Options */
const corporateBellCurveChartOptions = computed(() => {
    const bins = histogramBins.value;
    if (!bins.length) return {};

    const categories = bins.map((b) => b.label || `${b.batas_bawah}-${b.batas_atas}`);
    const barData = bins.map((b) => Number(b.jumlah) || 0);

    const mean = Number(statData.value.rerata) || 76;
    const stdDev = Number(statData.value.simpangan_baku) || 10;
    const total = Number(totalPesertaDinilai.value) || barData.reduce((a, b) => a + b, 0) || 1;

    const normalCurveData = bins.map((b) => {
        if (!cukupUntukKurva.value || stdDev <= 0) return null;
        const mid = (Number(b.batas_bawah) + Number(b.batas_atas)) / 2;
        const binWidth = Math.max(1, Number(b.batas_atas) - Number(b.batas_bawah));
        const z = (mid - mean) / stdDev;
        const pdf = (1 / (stdDev * Math.sqrt(2 * Math.PI))) * Math.exp(-0.5 * z * z);
        const expectedCount = Math.round(total * binWidth * pdf * 10) / 10;
        return expectedCount;
    });

    return {
        tooltip: {
            trigger: 'axis',
            backgroundColor: 'rgba(15, 23, 42, 0.94)',
            borderColor: 'rgba(255, 255, 255, 0.12)',
            borderWidth: 1,
            padding: [10, 14],
            textStyle: { color: '#ffffff', fontSize: 11 },
            formatter: (params) => {
                if (!params || !params.length) return '';
                const idx = params[0].dataIndex;
                const bin = bins[idx];
                const count = barData[idx];
                const pct = total > 0 ? ((count / total) * 100).toFixed(1) : 0;
                let html = `<div style="font-weight: 800; margin-bottom: 6px; color: #a5b4fc; font-size: 12px;">Interval Skor: ${bin.label}%</div>`;
                html += `<div style="display: flex; justify-content: space-between; gap: 16px; margin-top: 3px;">
                    <span style="color: #cbd5e1;">Jumlah Aktual:</span>
                    <strong style="color: #ffffff;">${count} orang (${pct}%)</strong>
                </div>`;
                if (cukupUntukKurva.value && normalCurveData[idx] !== null) {
                    html += `<div style="display: flex; justify-content: space-between; gap: 16px; margin-top: 3px;">
                        <span style="color: #67e8f9;">Ekspektasi Kurva Lonceng:</span>
                        <strong style="color: #67e8f9;">~${normalCurveData[idx]} orang</strong>
                    </div>`;
                }
                return html;
            },
        },
        legend: {
            show: true,
            top: 0,
            right: 0,
            textStyle: { color: '#475569', fontSize: 11, fontWeight: 600 },
        },
        grid: {
            top: 36,
            right: 18,
            bottom: 28,
            left: 36,
            containLabel: true,
        },
        xAxis: {
            type: 'category',
            data: categories,
            axisLine: { lineStyle: { color: '#cbd5e1' } },
            axisLabel: { color: '#64748b', fontSize: 11, fontWeight: 600 },
        },
        yAxis: {
            type: 'value',
            name: 'Jumlah Peserta (Orang)',
            nameTextStyle: { color: '#94a3b8', fontSize: 10, padding: [0, 0, 0, 10] },
            splitLine: { lineStyle: { color: 'rgba(203, 213, 225, 0.4)', type: 'dashed' } },
            axisLabel: { color: '#64748b', fontSize: 11 },
        },
        series: [
            {
                name: 'Jumlah Aktual',
                type: 'bar',
                barMaxWidth: 38,
                itemStyle: {
                    color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                        { offset: 0, color: '#6366f1' },
                        { offset: 1, color: '#4338ca' },
                    ]),
                    borderRadius: [6, 6, 0, 0],
                },
                data: barData,
            },
            ...(cukupUntukKurva.value && stdDev > 0
                ? [
                      {
                          name: 'Kurva Lonceng (Teoritis)',
                          type: 'line',
                          smooth: true,
                          symbol: 'circle',
                          symbolSize: 6,
                          lineStyle: { width: 3, color: '#06b6d4' },
                          itemStyle: { color: '#06b6d4' },
                          areaStyle: {
                              color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                  { offset: 0, color: 'rgba(6, 182, 212, 0.22)' },
                                  { offset: 1, color: 'rgba(6, 182, 212, 0.0)' },
                              ]),
                          },
                          data: normalCurveData,
                      },
                  ]
                : []),
        ],
    };
});

function hexToRgba(hex, alpha = 1) {
    let c = hex.replace('#', '');
    if (c.length === 3) {
        c = c.split('').map((char) => char + char).join('');
    }
    const num = parseInt(c, 16);
    const r = (num >> 16) & 255;
    const g = (num >> 8) & 255;
    const b = num & 255;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function displayGrade(kode) {
    if (!kode || kode === '—' || kode === '(Tanpa grade)') return '—';
    return String(kode)
        .replace(/_PLUS/gi, '+')
        .replace(/_MINUS/gi, '-')
        .replace(/\bB\s*PLUS\b/gi, 'B+')
        .replace(/\bC\s*PLUS\b/gi, 'C+')
        .replace(/\bA\s*PLUS\b/gi, 'A+')
        .trim();
}

function formatGradeCategory(kode, label) {
    const clean = displayGrade(kode);
    if (label && !label.includes('_PLUS') && !label.startsWith('Grade B_') && !label.startsWith('Grade C_')) {
        if (clean === 'B+' && (label === 'Sangat Baik' || label.toLowerCase().includes('sangat baik'))) {
            return 'Sangat Baik (B Plus)';
        }
        if (clean === 'C+' && (label === 'Cukup' || label.toLowerCase().includes('cukup'))) {
            return 'Cukup (C Plus)';
        }
        return label;
    }

    const map = {
        'A': 'Istimewa (Top Performer)',
        'B+': 'Sangat Baik (B Plus · High Performer)',
        'B': 'Baik (Solid Performer)',
        'C+': 'Cukup (C Plus · Moderate)',
        'C': 'Kurang (Below Target)',
        'D': 'Sangat Kurang (Need Improvement)',
        'E': 'Sangat Kurang (Unsatisfactory)',
    };
    return map[clean] || (clean !== '—' ? `Grade ${clean}` : 'Tanpa Kategori');
}

function getGradeSubtext(kode) {
    const clean = displayGrade(kode);
    const subMap = {
        'A': 'Kinerja istimewa melampaui seluruh target',
        'B+': 'Performa di atas standar ekspektasi (High Performer)',
        'B': 'Kinerja konsisten memenuhi target (Solid)',
        'C+': 'Kinerja cukup dengan ruang peningkatan',
        'C': 'Membutuhkan pembinaan dan evaluasi berkala',
        'D': 'Memerlukan program intervensi perbaikan kinerja',
    };
    return subMap[clean] || '';
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

function isSignificantDeviation(g) {
    if (g.selisih == null) return false;
    return Math.abs(Number(g.selisih)) >= 5;
}

function calcLevelPercentage(jumlah) {
    const total = totalPesertaDinilai.value;
    if (!total || total <= 0) return '0.0';
    return ((Number(jumlah || 0) / total) * 100).toFixed(1);
}

function getDispersionLabel(sdKpi, sdKomp) {
    const avgSd = ((Number(sdKpi || 10) + Number(sdKomp || 11)) / 2);
    if (avgSd <= 7) return 'Sangat Homogen';
    if (avgSd <= 10) return 'Konsisten';
    return 'Variatif';
}

function getDispersionVariant(sdKpi, sdKomp) {
    const avgSd = ((Number(sdKpi || 10) + Number(sdKomp || 11)) / 2);
    if (avgSd <= 7) return 'success';
    if (avgSd <= 10) return 'brand';
    return 'purple';
}

async function muatSiklus() {
    loadingSiklus.value = true;
    try {
        const response = await paApi.daftarSiklus();
        const data = response?.result ?? response;
        siklusList.value = data?.siklus || [];

        const diminta = cycleDariUrl(siklusList.value);

        if (diminta) {
            selectedCycleId.value = String(diminta);
            await muatDataDistribusi();
        } else if (siklusList.value.length && !selectedCycleId.value) {
            const active = siklusList.value.find(
                (c) => c.Status_Cycle === 'CALCULATED' || c.Status_Cycle === 'PUBLISHED' || c.Status_Cycle === 'CLOSED',
            ) || siklusList.value[0];
            selectedCycleId.value = String(active.Id_PA_Cycle);
            
            // Sync URL parameter silently
            if (typeof window !== 'undefined') {
                const url = new URL(window.location.href);
                url.searchParams.set('cycle', String(active.Id_PA_Cycle));
                window.history.replaceState({}, '', url.toString());
            }

            await muatDataDistribusi();
        }
    } catch (e) {
        errorDistribusi.value = e?.message || 'Gagal memuat siklus.';
    } finally {
        loadingSiklus.value = false;
    }
}

async function muatDataDistribusi() {
    if (!selectedCycleId.value) return;

    loadingDistribusi.value = true;
    errorDistribusi.value = '';
    errorStatus.value = 0;

    try {
        const response = await paApi.distribusiSiklus(selectedCycleId.value);
        distribusiData.value = response?.result ?? response ?? {};
    } catch (e) {
        errorDistribusi.value = e?.message || 'Gagal memuat distribusi nilai siklus.';
        errorStatus.value = Number(e?.status || 500);
    } finally {
        loadingDistribusi.value = false;
    }
}

function onCycleChange() {
    muatDataDistribusi();
}

function getStatusSentiment(status) {
    if (status === 'PUBLISHED') return 'success';
    if (status === 'CALCULATED') return 'purple';
    if (status === 'OPEN') return 'brand';
    if (status === 'LOCKED') return 'warning';
    if (status === 'CLOSED') return 'neutral';
    return 'neutral';
}

function getStatusCycleLabel(status) {
    const map = {
        'PUBLISHED': 'Terbit & Final',
        'CALCULATED': 'Terkalkulasi',
        'LOCKED': 'Terkunci (Siap Hitung)',
        'OPEN': 'Pengisian Berjalan',
        'PREPARED': 'Siap / Penyiapan',
        'DRAFT': 'Draft',
        'CLOSED': 'Ditutup / Arsip',
    };
    return map[status] || status || 'Cycle';
}

function formatTanggalPeriode(tglDari, tglSampai) {
    if (!tglDari && !tglSampai) return 'Periode dinilai belum ditentukan';
    if (tglDari && !tglSampai) return `Mulai ${formatTgl(tglDari)}`;
    if (!tglDari && tglSampai) return `Hingga ${formatTgl(tglSampai)}`;
    return `${formatTgl(tglDari)} – ${formatTgl(tglSampai)}`;
}

function formatTgl(str) {
    if (!str) return '—';
    try {
        const d = new Date(str);
        if (isNaN(d.getTime())) return str;
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch {
        return str;
    }
}

function formatRerata(val) {
    if (val === null || val === undefined || isNaN(Number(val))) return '—';
    return `${Number(val).toFixed(1)}%`;
}

function formatNumber(val) {
    if (val === null || val === undefined || isNaN(Number(val))) return '—';
    return Number(val).toFixed(1);
}

function formatAngka(num) {
    return Number(num || 0).toLocaleString('id-ID');
}

onMounted(() => {
    muatSiklus();
});
</script>

<style scoped>
.pa-tabel-kosong,
.pa-talent-kosong {
    padding: 1rem;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.85rem;
    text-align: center;
}

.pa-distribusi-page {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    padding: 0 0 5rem 0;
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
}

.pa-distribusi-header-actions {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

/* ------------------------------------------------------------------ */
/* Premium Period Selector Trigger Button in Header */
/* ------------------------------------------------------------------ */
.pa-period-trigger {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.35rem 0.75rem 0.35rem 0.45rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-full, 9999px);
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
    cursor: pointer;
    text-align: left;
    transition: all 180ms cubic-bezier(0.4, 0, 0.2, 1);
    font: inherit;
    color: var(--hcis-text-strong, #0f172a);
    max-width: 380px;
    user-select: none;
}

.pa-period-trigger:hover:not(:disabled) {
    background: #ffffff;
    border-color: #818cf8;
    box-shadow: 0 3px 8px rgba(99, 102, 241, 0.14), 0 1px 3px rgba(15, 23, 42, 0.08);
    transform: translateY(-1px);
}

.pa-period-trigger:active:not(:disabled) {
    transform: translateY(0);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
}

.pa-period-trigger:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.pa-period-trigger__jewel {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: var(--hcis-radius-full, 9999px);
    background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
    color: #ffffff;
    box-shadow: 0 2px 4px rgba(79, 70, 229, 0.3);
    flex-shrink: 0;
}

.pa-period-trigger__body {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
    min-width: 0;
    flex: 1;
}

.pa-period-trigger__label {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.62rem;
    font-weight: 750;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1;
}

.pa-period-trigger__year {
    display: inline-flex;
    padding: 0.05rem 0.3rem;
    border-radius: 4px;
    background: rgba(99, 102, 241, 0.1);
    color: #4f46e5;
    font-size: 0.6rem;
    font-weight: 800;
}

.pa-period-trigger__value {
    font-size: 0.8rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.25;
}

.pa-period-trigger__badge-wrap {
    flex-shrink: 0;
}

.pa-period-trigger__chevron {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-text-muted, #94a3b8);
    transition: transform 180ms ease;
    flex-shrink: 0;
    margin-left: -0.15rem;
}

.pa-period-trigger:hover .pa-period-trigger__chevron {
    color: #4f46e5;
    transform: translateY(1px);
}

/* ------------------------------------------------------------------ */
/* Rich Period Selector Dialog Components */
/* ------------------------------------------------------------------ */
.pa-period-dialog {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    padding: 0.25rem 0;
}

.pa-period-dialog__controls {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-period-dialog__search-wrap {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}

.pa-period-dialog__search-icon {
    position: absolute;
    left: 1rem;
    color: #94a3b8;
    pointer-events: none;
}

.pa-period-dialog__search-input {
    width: 100%;
    height: 2.75rem;
    padding: 0 2.5rem 0 2.75rem;
    font: inherit;
    font-size: 0.86rem;
    color: var(--hcis-text-strong, #0f172a);
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(203, 213, 225, 0.85);
    border-radius: var(--hcis-radius-lg, 12px);
    outline: none;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: all 180ms ease;
}

.pa-period-dialog__search-input:focus {
    border-color: #6366f1;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.pa-period-dialog__clear-btn {
    position: absolute;
    right: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 50%;
    transition: color 150ms ease;
}

.pa-period-dialog__clear-btn:hover {
    color: #475569;
}

.pa-period-dialog__filter-tabs {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    overflow-x: auto;
    padding-bottom: 0.25rem;
}

.pa-filter-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    font: inherit;
    font-size: 0.76rem;
    font-weight: 650;
    color: var(--hcis-text-muted, #64748b);
    background: rgba(241, 245, 249, 0.8);
    border: 1px solid transparent;
    border-radius: var(--hcis-radius-full, 9999px);
    cursor: pointer;
    white-space: nowrap;
    transition: all 150ms ease;
}

.pa-filter-tab:hover {
    color: #4f46e5;
    background: rgba(255, 255, 255, 0.8);
}

.pa-filter-tab--active {
    background: #ffffff;
    color: #4338ca;
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.pa-filter-tab__count {
    display: inline-flex;
    padding: 0.05rem 0.35rem;
    border-radius: 9999px;
    font-size: 0.65rem;
    font-weight: 750;
    background: rgba(148, 163, 184, 0.2);
    color: inherit;
}

.pa-filter-tab--active .pa-filter-tab__count {
    background: rgba(99, 102, 241, 0.15);
    color: #4338ca;
}

/* Cycle Cards Bento Grid */
.pa-period-dialog__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 0.85rem;
    max-height: 52vh;
    overflow-y: auto;
    padding: 0.25rem 0.25rem 0.5rem 0.25rem;
}

.pa-cycle-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.85rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-lg, 12px);
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    transition: all 180ms cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.pa-cycle-card:hover {
    background: #ffffff;
    border-color: #a5b4fc;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1), 0 1px 3px rgba(15, 23, 42, 0.06);
    transform: translateY(-2px);
}

.pa-cycle-card--selected {
    background: linear-gradient(180deg, #ffffff 0%, #f5f7ff 100%);
    border-color: #6366f1;
    box-shadow: 0 0 0 1.5px #6366f1, 0 4px 14px rgba(99, 102, 241, 0.16);
}

.pa-cycle-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-cycle-card__badges {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.pa-cycle-code-chip {
    display: inline-flex;
    padding: 0.15rem 0.45rem;
    border-radius: 5px;
    background: rgba(15, 23, 42, 0.06);
    color: var(--hcis-text-strong, #0f172a);
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.pa-cycle-year-chip {
    display: inline-flex;
    padding: 0.15rem 0.45rem;
    border-radius: 5px;
    background: rgba(99, 102, 241, 0.1);
    color: #4f46e5;
    font-size: 0.68rem;
    font-weight: 750;
}

.pa-cycle-card__body {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.pa-cycle-card__title {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.35;
}

.pa-cycle-card__meta-list {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.pa-cycle-meta-item {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.2;
}

.pa-cycle-meta-item strong {
    color: var(--hcis-text-strong, #334155);
}

.pa-cycle-card__footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-top: 0.65rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.85);
    margin-top: auto;
}

.pa-cycle-card__selected-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border-radius: var(--hcis-radius-full, 9999px);
    background: #e0e7ff;
    color: #3730a3;
    font-size: 0.72rem;
    font-weight: 750;
}

.pa-cycle-card__select-action {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: #4f46e5;
    font-size: 0.74rem;
    font-weight: 700;
    transition: transform 150ms ease;
}

.pa-cycle-card:hover .pa-cycle-card__select-action {
    transform: translateX(3px);
}

.pa-period-dialog__empty {
    padding: 2rem 1rem;
}

.pa-period-dialog__footer-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

.pa-period-dialog__info-text {
    font-size: 0.76rem;
    color: var(--hcis-text-muted, #64748b);
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

.pa-distribusi-stats {
    margin: 0;
}

.pa-distribusi-content {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Sections */
.pa-section-card {
    display: flex;
    flex-direction: column;
}

.pa-section-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    width: 100%;
}

.pa-section-header__title-group {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-eyebrow {
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.08em;
}

.pa-section-title {
    margin: 0;
    font-size: 1.08rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
    letter-spacing: -0.01em;
}

.pa-section-desc {
    margin: 0.15rem 0 0;
    font-size: 0.76rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.45;
}

.pa-card-title-lg {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

/* Dual Bell Curves Grid */
.pa-normal-curves-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.25rem;
    margin-top: 0.5rem;
}

.pa-curve-box {
    display: flex;
    flex-direction: column;
    padding: 1rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(255, 255, 255, 0.7);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.pa-curve-box__head {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    margin-bottom: 0.5rem;
}

.pa-curve-dot {
    width: 0.65rem;
    height: 0.65rem;
    border-radius: 50%;
}

.pa-curve-dot--kpi {
    background: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
}

.pa-curve-dot--komp {
    background: #0d9488;
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.2);
}

.pa-curve-title {
    margin: 0;
    font-size: 0.88rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-curve-meta {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
    font-weight: 500;
}

.pa-curve-chart-wrapper {
    position: relative;
    width: 100%;
    min-height: 340px;
}

/* Level Legend Chips */
.pa-level-legend-wrap {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    margin-top: 1rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(248, 250, 252, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.7);
    flex-wrap: wrap;
}

.pa-level-legend-label {
    font-size: 0.72rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #334155);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.pa-level-chips {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.pa-level-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.25rem 0.55rem;
    border-radius: var(--hcis-radius-sm, 6px);
    background: #ffffff;
    border: 1px solid rgba(203, 213, 225, 0.7);
    font-size: 0.72rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.pa-level-chip__color {
    width: 0.55rem;
    height: 0.55rem;
    border-radius: 50%;
}

.pa-level-chip__name {
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-level-chip__count {
    color: var(--hcis-text-muted, #64748b);
    font-variant-numeric: tabular-nums;
}

/* Statistics Table */
.pa-table-responsive-wrap {
    width: 100%;
    overflow-x: auto;
    margin-top: 0.5rem;
}

.pa-stats-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.78rem;
}

.pa-stats-table th {
    padding: 0.7rem 0.85rem;
    border-bottom: 2px solid rgba(226, 232, 240, 0.9);
    background: rgba(248, 250, 252, 0.8);
    color: var(--hcis-text-strong, #334155);
    font-weight: 750;
    white-space: nowrap;
}

.pa-stats-table td {
    padding: 0.75rem 0.85rem;
    border-bottom: 1px solid rgba(241, 245, 249, 0.9);
    color: var(--hcis-text-strong, #0f172a);
    vertical-align: middle;
}

.pa-stats-table__row:hover {
    background: rgba(248, 250, 252, 0.6);
}

.pa-level-badge-cell {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.pa-level-indicator {
    width: 0.6rem;
    height: 0.6rem;
    border-radius: 50%;
    flex-shrink: 0;
}

.pa-level-name-wrap {
    display: flex;
    flex-direction: column;
}

.pa-level-name {
    font-size: 0.8rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-level-sub {
    font-size: 0.66rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-metric-cell {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.pa-mini-bar-track {
    width: 4.5rem;
    height: 0.3rem;
    border-radius: 999px;
    background: rgba(226, 232, 240, 0.8);
    overflow: hidden;
}

.pa-mini-bar-fill {
    height: 100%;
    border-radius: 999px;
}

.pa-mini-bar-fill--kpi {
    background: linear-gradient(90deg, #6366f1, #4f46e5);
}

.pa-mini-bar-fill--komp {
    background: linear-gradient(90deg, #14b8a6, #0d9488);
}

.pa-stats-table__footer-row {
    background: rgba(241, 245, 249, 0.6);
    border-top: 2px solid rgba(203, 213, 225, 0.8);
}

.pa-stats-table__footer-row td {
    padding: 0.85rem;
}

/* Corporate Dist Grid (Bell Curve & Grade) */
.pa-corporate-dist-grid {
    display: grid;
    grid-template-columns: 1.15fr 1fr;
    gap: 1.25rem;
}

.pa-corp-bell-card,
.pa-corp-grade-card {
    display: flex;
    flex-direction: column;
}

.pa-histogram-chart-wrapper {
    width: 100%;
    min-height: 320px;
    margin-top: 0.5rem;
}

.pa-histogram-empty {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(254, 243, 199, 0.3);
    color: #92400e;
    font-size: 0.76rem;
}

.pa-grade-table-wrap {
    width: 100%;
    overflow-x: auto;
    margin-top: 0.5rem;
}

.pa-grade-distribution-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.76rem;
}

.pa-grade-distribution-table th {
    padding: 0.6rem 0.65rem;
    border-bottom: 2px solid rgba(226, 232, 240, 0.8);
    background: rgba(248, 250, 252, 0.7);
    color: var(--hcis-text-strong, #334155);
    font-weight: 750;
    text-align: left;
}

.pa-grade-distribution-table td {
    padding: 0.65rem;
    border-bottom: 1px solid rgba(241, 245, 249, 0.8);
    color: var(--hcis-text-strong, #0f172a);
}

.pa-row-deviation {
    background: rgba(254, 243, 199, 0.25);
}

.pa-grade-label-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
}

.pa-grade-category-title {
    font-size: 0.82rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.25;
}

.pa-grade-category-sub {
    font-size: 0.6875rem;
    font-weight: 500;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.3;
}

.pa-grade-tag {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    padding: 0.2rem 0.5rem;
    border-radius: var(--hcis-radius-sm, 6px);
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}

.pa-diff-badge {
    display: inline-block;
    padding: 0.12rem 0.4rem;
    border-radius: var(--hcis-radius-xs, 4px);
    font-size: 0.7rem;
    font-weight: 800;
}

.pa-diff-badge--plus {
    background: rgba(16, 185, 129, 0.15);
    color: #059669;
}

.pa-diff-badge--minus {
    background: rgba(239, 68, 68, 0.15);
    color: #dc2626;
}

.pa-diff-badge--zero {
    background: rgba(241, 245, 249, 0.8);
    color: #64748b;
}

.pa-table-bar-track {
    width: 6rem;
    height: 0.45rem;
    border-radius: 999px;
    background: rgba(226, 232, 240, 0.8);
    overflow: hidden;
}

.pa-table-bar-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 200ms ease;
}

/* Talent Highlights Section */
.pa-talent-highlights-section {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}

.pa-talent-section-head {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-talent-cards-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1.15rem;
}

.pa-talent-card {
    display: flex;
    flex-direction: column;
}

.pa-talent-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    width: 100%;
}

.pa-talent-icon {
    font-size: 1.35rem;
    line-height: 1;
}

.pa-talent-card-title {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-talent-card-subtitle {
    font-size: 0.66rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-talent-list {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    padding-top: 0.4rem;
}

.pa-talent-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.55rem 0.65rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(226, 232, 240, 0.75);
    transition: all 150ms ease;
}

.pa-talent-item:hover {
    background: #ffffff;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
}

.pa-talent-item__left {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    min-width: 0;
}

.pa-talent-rank {
    font-size: 0.68rem;
    font-weight: 800;
    color: #059669;
    min-width: 1.2rem;
}

.pa-talent-rank--brand {
    color: #4f46e5;
}

.pa-talent-rank--danger {
    color: #dc2626;
}

.pa-talent-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pa-talent-name {
    font-size: 0.78rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-talent-sub {
    font-size: 0.64rem;
    color: var(--hcis-text-muted, #64748b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-talent-score-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.2rem 0.5rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

.pa-talent-score-pill--success {
    background: rgba(16, 185, 129, 0.15);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.25);
}

.pa-talent-score-pill--brand {
    background: rgba(99, 102, 241, 0.15);
    color: #4f46e5;
    border: 1px solid rgba(99, 102, 241, 0.25);
}

.pa-talent-score-pill--danger {
    background: rgba(239, 68, 68, 0.15);
    color: #dc2626;
    border: 1px solid rgba(239, 68, 68, 0.25);
}

/* Skeletons */
.pa-distribusi-loading {
    padding: 1rem 0;
}

.pa-distribusi-skeleton-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

.pa-distribusi-skeleton-card {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1.25rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: var(--hcis-radius-xl, 18px);
    background: #ffffff;
}

.font-num {
    font-variant-numeric: tabular-nums;
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
    .pa-normal-curves-grid,
    .pa-corporate-dist-grid,
    .pa-talent-cards-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .pa-distribusi-page {
        padding: 0 0 5rem 0;
    }

    .pa-period-dialog__grid {
        grid-template-columns: 1fr;
    }

    .pa-period-trigger {
        max-width: 100%;
        width: 100%;
    }

    .pa-period-dialog__controls {
        gap: 0.65rem;
    }

    .pa-filter-tab {
        padding: 0.3rem 0.65rem;
        font-size: 0.72rem;
    }
}

.pa-kurva-pindah {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 1rem;
    padding: 0.7rem 1rem;
    border: 1px solid #c7d2fe;
    border-radius: 12px;
    background: #eef2ff;
    color: #3730a3;
    font-size: 0.82rem;
    text-decoration: none;
}

.pa-kurva-pindah b {
    margin-left: auto;
}
</style>
