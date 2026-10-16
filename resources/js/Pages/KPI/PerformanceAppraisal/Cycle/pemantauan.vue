<template>
    <main class="pa-pemantauan-page">
        <!-- Modern Executive Glass Header (Aligned with PA Tim Standard) -->
        <HcisHeader
            title="Monitoring Cycle"
            :kicker="isMobile ? 'PA · MONITORING' : 'PERFORMANCE APPRAISAL · MONITORING CYCLE'"
            :subtitle="isMobile
                ? 'Pantau progres pengisian rater 360°, verifikasi hasil, dan alur penutupan siklus.'
                : 'Pusat kendali monitoring progres pengisian rater 360°, verifikasi hasil evaluasi, dan alur penutupan siklus.'"
            icon="speedometer2"
            sentiment="brand"
            variant="glass"
            :size="isMobile ? 'sm' : 'md'"
            animated
            :clamp="isMobile ? 1 : null"
            :loading="loadingSiklus && !activeCycle"
            class="pa-main-header"
        >
            <template #actions>
                <div class="pa-pemantauan-header-actions">
                    <!-- Period Selector Trigger Jewel (When on Monitoring Dashboard) -->
                    <button
                        v-if="activeCycle"
                        type="button"
                        class="pa-period-trigger"
                        :class="{ 'is-loading': loadingSiklus }"
                        :disabled="loadingSiklus"
                        aria-haspopup="dialog"
                        :aria-expanded="bukaDialogPeriode"
                        title="Klik untuk memilih siklus &amp; periode penilaian"
                        @click="bukaDialogPeriode = true"
                    >
                        <div class="pa-period-trigger__jewel">
                            <HcisIcon :name="loadingSiklus ? 'arrow-repeat' : 'calendar2-range-fill'" size="xs" :class="{ 'pa-spin-icon': loadingSiklus }" />
                        </div>
                        <div class="pa-period-trigger__body">
                            <span class="pa-period-trigger__label">
                                <span>PERIODE</span>
                                <span v-if="loadingSiklus" class="pa-period-trigger__syncing">SYNC</span>
                                <span v-else-if="activeCycle.Tahun" class="pa-period-trigger__year">{{ activeCycle.Tahun }}</span>
                            </span>
                            <span class="pa-period-trigger__value">
                                {{ loadingSiklus ? 'Memuat data periode…' : (activeCycle.Nama_Cycle || activeCycle.Kode_Cycle) }}
                            </span>
                        </div>
                        <div class="pa-period-trigger__chevron">
                            <HcisIcon name="chevron-down" size="2xs" />
                        </div>
                    </button>

                    <HcisButton
                        label="Segarkan"
                        variant="glass"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :icon-only="isMobile"
                        :loading="loadingMonitoring"
                        aria-label="Segarkan data monitoring"
                        title="Segarkan data monitoring"
                        @click="muatDataMonitoring"
                    />
                </div>
            </template>

            <template #console>
                <div class="pa-pemantauan-header-console">
                    <div class="pa-pemantauan-header-console__identity">
                        <span class="pa-pemantauan-header-console__avatar" aria-hidden="true">
                            <HcisIcon name="speedometer2" size="xs" />
                        </span>
                        <span class="pa-pemantauan-header-console__name-wrap">
                            <small>Siklus Penilaian Aktif</small>
                            <strong>{{ activeCycle ? (activeCycle.Nama_Cycle || activeCycle.Kode_Cycle) : (loadingSiklus ? 'Memuat siklus…' : 'Belum memilih siklus') }}</strong>
                        </span>
                    </div>
                    <div class="pa-pemantauan-header-console__context">
                        <HcisStatusBadge
                            v-if="activeCycle"
                            :label="getStatusCycleLabel(activeCycle.Status_Cycle, isMobile)"
                            :variant="getStatusSentiment(activeCycle.Status_Cycle)"
                            :size="isMobile ? 'xs' : 'sm'"
                            dot
                        />
                        <span v-if="activeCycle?.Kode_Cycle" class="pa-console-code-pill">
                            {{ activeCycle.Kode_Cycle }}
                        </span>
                        <span v-if="sisaHariInfo" class="pa-console-countdown-pill" :class="`is-${sisaHariInfo.variant || 'brand'}`">
                            <HcisIcon :name="sisaHariInfo.icon || 'hourglass-split'" size="2xs" />
                            <span>{{ isMobile ? (sisaHariInfo.shortText || sisaHariInfo.text) : sisaHariInfo.text }}</span>
                        </span>
                    </div>
                </div>
            </template>

            <!-- Stepper penutupan: Ikhtisar + lima tahap (spec 2026-10-02) -->
            <template #tabs>
                <PaPenutupanStepper :alur="alurPenutupan" :aktif="tahapAktif" @pilih="pilihTahap" />
            </template>
        </HcisHeader>

        <!-- ============================================================
             TAB 1: RINGKASAN & PROGRES (DESIGN SYSTEM COMPLIANT)
             ============================================================ -->
        <div v-show="tabAktif === 'RINGKASAN'" class="pa-tab-ringkasan-wrap">
            <!-- 1. Banner Info Siklus Aktif (Executive Cockpit) -->
            <HcisCard
                v-if="activeCycle"
                sentiment="brand"
                variant="glass"
                size="md"
                pattern="grid"
                class="pa-cycle-hero-card"
            >
                <template #title>
                    <div class="pa-cycle-hero-title-group">
                        <span class="pa-cycle-hero-kicker">SIKLUS PENILAIAN AKTIF</span>
                        <div class="pa-cycle-hero-name-row">
                            <h2 class="pa-cycle-hero-name">{{ activeCycle.Nama_Cycle || activeCycle.Kode_Cycle }}</h2>
                            <span v-if="activeCycle.Kode_Cycle && activeCycle.Kode_Cycle !== activeCycle.Nama_Cycle" class="pa-cycle-code-pill">
                                {{ activeCycle.Kode_Cycle }}
                            </span>
                        </div>
                    </div>
                </template>

                <template #badge>
                    <div class="pa-cycle-badge-group">
                        <HcisBadge
                            :label="getStatusCycleLabel(activeCycle.Status_Cycle, isMobile)"
                            :variant="getStatusSentiment(activeCycle.Status_Cycle)"
                            appearance="glass"
                            :size="isMobile ? 'xs' : 'sm'"
                            dot
                        />
                        <HcisBadge
                            v-if="sisaHariInfo"
                            :label="isMobile ? (sisaHariInfo.shortText || sisaHariInfo.text) : sisaHariInfo.text"
                            :variant="sisaHariInfo.variant"
                            appearance="glass"
                            :size="isMobile ? 'xs' : 'sm'"
                            dot
                        />
                    </div>
                </template>

                <template #description>
                    <p v-if="!isMobile" class="pa-cycle-hero-desc">
                        Siklus evaluasi kinerja dan penilaian kompetensi 360° multi-rater yang sedang berjalan aktif.
                    </p>
                </template>

                <div class="pa-cockpit-grid">
                    <!-- 1. Large Prominent Radial Progress Gauge (ECharts) -->
                    <div class="pa-cockpit-gauge-box">
                        <div class="pa-gauge-visual-wrap">
                            <HcisChart
                                type="custom"
                                :options="cockpitRadialChartOptions"
                                :height="148"
                                :card="false"
                            />
                        </div>

                        <div class="pa-gauge-info">
                            <div class="pa-gauge-cycle-title">
                                <HcisIcon name="pie-chart-fill" size="2xs" color="brand" />
                                <strong>Progres Pengisian Siklus</strong>
                            </div>
                            <span class="pa-gauge-ratio-text">
                                <strong>{{ formatAngka(summarySelesai) }}</strong> dari <strong>{{ formatAngka(summaryTotal) }}</strong> penugasan rater terselesai
                            </span>
                            <div class="pa-gauge-status-row">
                                <HcisBadge
                                    :label="summaryPersen >= 100 ? 'Pengisian Tuntas' : summaryPersen >= 75 ? 'Hampir Selesai' : 'Sedang Berjalan'"
                                    :variant="summaryPersen >= 100 ? 'success' : summaryPersen < 40 ? 'warning' : 'brand'"
                                    appearance="subtle"
                                    size="xs"
                                    dot
                                />
                            </div>
                        </div>
                    </div>

                    <!-- 2. Timeline & Schedule Cards -->
                    <div class="pa-cycle-dates-pane">
                        <div class="pa-cycle-date-card">
                            <div class="pa-cycle-date-icon pa-cycle-date-icon--brand">
                                <HcisIcon name="calendar3" size="xs" />
                            </div>
                            <div class="pa-cycle-date-content">
                                <span class="pa-cycle-date-label">Periode Evaluasi Kerja</span>
                                <strong class="pa-cycle-date-val">
                                    {{ formatDate(activeCycle.Periode_Dinilai_Dari) }} — {{ formatDate(activeCycle.Periode_Dinilai_Sampai) }}
                                </strong>
                                <small class="pa-cycle-date-sub">Rentang waktu kinerja yang dievaluasi</small>
                            </div>
                        </div>

                        <div class="pa-cycle-date-card">
                            <div class="pa-cycle-date-icon pa-cycle-date-icon--amber">
                                <HcisIcon name="clock-history" size="xs" />
                            </div>
                            <div class="pa-cycle-date-content">
                                <div class="pa-cycle-date-label-row">
                                    <span class="pa-cycle-date-label">Jendela Pengisian Nilai</span>
                                    <HcisBadge
                                        v-if="sisaHariInfo"
                                        :label="isMobile ? (sisaHariInfo.shortText || sisaHariInfo.text) : sisaHariInfo.text"
                                        :variant="sisaHariInfo.variant"
                                        size="2xs"
                                        appearance="subtle"
                                        dot
                                    />
                                </div>
                                <strong class="pa-cycle-date-val">
                                    {{ formatDate(activeCycle.Pengisian_Dari) }} — {{ formatDate(activeCycle.Pengisian_Sampai) }}
                                </strong>
                                <small class="pa-cycle-date-sub">Batas rater mengisi kuesioner evaluasi</small>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Integrated 4 Stat Nodes -->
                    <div class="pa-cockpit-stats-strip">
                        <div class="pa-stat-node pa-stat-node--brand" title="Total penugasan rater yang terdaftar">
                            <div class="pa-stat-node__icon-box">
                                <HcisIcon name="people-fill" size="xs" />
                            </div>
                            <div class="pa-stat-node__content">
                                <span class="pa-stat-node__label">Total Penugasan</span>
                                <strong class="pa-stat-node__value">{{ formatAngka(summaryTotal) }}</strong>
                                <span class="pa-stat-node__sub">Semua rater evaluator</span>
                            </div>
                        </div>

                        <div class="pa-stat-node pa-stat-node--success" title="Penugasan evaluasi yang sudah disubmit">
                            <div class="pa-stat-node__icon-box">
                                <HcisIcon name="check-circle-fill" size="xs" />
                            </div>
                            <div class="pa-stat-node__content">
                                <span class="pa-stat-node__label">Selesai Submit</span>
                                <strong class="pa-stat-node__value">{{ formatAngka(summarySelesai) }}</strong>
                                <span class="pa-stat-node__sub">{{ summaryPersen }}% capaian</span>
                            </div>
                        </div>

                        <div
                            class="pa-stat-node pa-stat-node--warning pa-stat-node--interactive"
                            role="button"
                            tabindex="0"
                            :title="`Klik untuk melihat ${summaryBelumSelesai} penilai belum selesai di Monitoring Rater`"
                            @click="saringBelumSelesaiDanKePengisian"
                            @keydown.enter="saringBelumSelesaiDanKePengisian"
                        >
                            <div class="pa-stat-node__icon-box">
                                <HcisIcon name="hourglass-split" size="xs" />
                            </div>
                            <div class="pa-stat-node__content">
                                <span class="pa-stat-node__label">Belum Selesai</span>
                                <strong class="pa-stat-node__value">{{ formatAngka(summaryBelumSelesai) }}</strong>
                                <span class="pa-stat-node__sub">Klik lihat rater &rarr;</span>
                            </div>
                        </div>

                        <div
                            v-if="summaryTerlambat > 0"
                            class="pa-stat-node pa-stat-node--danger pa-stat-node--interactive"
                            role="button"
                            tabindex="0"
                            :title="`Klik untuk menindaklanjuti ${summaryTerlambat} rater lewat tenggat di Monitoring Rater`"
                            @click="saringOverdueDanKePengisian"
                            @keydown.enter="saringOverdueDanKePengisian"
                        >
                            <div class="pa-stat-node__icon-box">
                                <HcisIcon name="exclamation-octagon-fill" size="xs" />
                            </div>
                            <div class="pa-stat-node__content">
                                <span class="pa-stat-node__label">Lewat Tenggat</span>
                                <strong class="pa-stat-node__value">{{ formatAngka(summaryTerlambat) }}</strong>
                                <span class="pa-stat-node__sub">Perlu tindak lanjut &rarr;</span>
                            </div>
                        </div>

                        <div v-else class="pa-stat-node pa-stat-node--success" title="Semua rater masih dalam rentang waktu yang aman">
                            <div class="pa-stat-node__icon-box">
                                <HcisIcon name="shield-check" size="xs" />
                            </div>
                            <div class="pa-stat-node__content">
                                <span class="pa-stat-node__label">Status Waktu</span>
                                <strong class="pa-stat-node__value" style="font-size: 0.95rem;">Tepat Waktu</strong>
                                <span class="pa-stat-node__sub">Tidak ada overdue</span>
                            </div>
                        </div>
                    </div>
                </div>
            </HcisCard>

            <!-- 2. Antrean Keputusan & Blocker Admin -->
            <HcisCard
                title="Antrean Keputusan & Blocker Admin"
                :description="isMobile ? '' : 'Status kelayakan kalkulasi hasil, peserta tertahan data/KPI, dan verifikasi persetujuan.'"
                icon="kanban-fill"
                sentiment="brand"
                variant="glass"
                size="md"
                pattern="grid"
            >
                <template #actions>
                    <div class="pa-card-action-btns">
                        <HcisButton
                            v-if="(keranjang?.tertahan || 0) > 0 && bolehHitungUlang"
                            label="Hitung Ulang"
                            variant="warning"
                            appearance="subtle"
                            :size="isMobile ? 'xs' : 'sm'"
                            leading-icon="arrow-repeat"
                            :loading="menyapu"
                            @click="jalankanSapu('TERTAHAN')"
                        />
                        <HcisButton
                            :label="isMobile ? 'Antrean Admin' : 'Buka Tab Antrean Admin'"
                            variant="brand"
                            appearance="subtle"
                            :size="isMobile ? 'xs' : 'sm'"
                            trailing-icon="arrow-right"
                            @click="pilihTab('KERANJANG')"
                        />
                    </div>
                </template>

                <div v-if="keranjang" class="pa-baskets-grid">
                    <div
                        v-for="k in daftarKeranjang"
                        :key="k.jenis"
                        class="pa-basket-tile"
                        :class="[`is-${k.nada}`, { 'is-empty': !k.jumlah }]"
                        role="button"
                        tabindex="0"
                        :title="`Buka antrean ${k.label}`"
                        @click="pilihKeranjangDariRingkasan(k.jenis)"
                        @keydown.enter="pilihKeranjangDariRingkasan(k.jenis)"
                    >
                        <div class="pa-basket-tile__top">
                            <div class="pa-basket-tile__icon">
                                <HcisIcon :name="k.icon" size="xs" />
                            </div>
                            <strong class="pa-basket-tile__count">{{ formatAngka(k.jumlah) }}</strong>
                        </div>
                        <div class="pa-basket-tile__body">
                            <strong class="pa-basket-tile__label">{{ k.label }}</strong>
                            <small class="pa-basket-tile__desc">{{ k.sebab }}</small>
                        </div>
                        <div class="pa-basket-tile__action">
                            <span>Buka antrean &rarr;</span>
                        </div>
                    </div>
                </div>
            </HcisCard>

            <!-- 3. Sebaran Progres Pengisian Evaluasi -->
            <HcisCard
                title="Sebaran Progres Pengisian Evaluasi"
                :description="isMobile ? '' : 'Tinjauan visual distribusi persentase dan volume penyelesaian kuesioner berdasarkan perspektif penilai dan unit kerja.'"
                icon="bar-chart-line-fill"
                sentiment="brand"
                variant="glass"
                size="md"
                pattern="dots"
                class="pa-chart-card"
            >
                <template #actions>
                    <div class="pa-chart-actions-wrap">
                        <div class="pa-segmented-toggle" role="group" aria-label="Saring Sudut Pandang Grafik">
                            <button
                                type="button"
                                class="pa-segmented-btn"
                                :class="{ active: analyticsTab === 'kategori' }"
                                @click="analyticsTab = 'kategori'"
                            >
                                <HcisIcon name="people-fill" size="2xs" />
                                <span>{{ isMobile ? `Kategori (${categoryBreakdown.length})` : `Kategori Penilai (${categoryBreakdown.length})` }}</span>
                            </button>
                            <button
                                v-if="divisionList.length"
                                type="button"
                                class="pa-segmented-btn"
                                :class="{ active: analyticsTab === 'divisi' }"
                                @click="analyticsTab = 'divisi'"
                            >
                                <HcisIcon name="building" size="2xs" />
                                <span>{{ isMobile ? `Divisi (${divisionList.length})` : `Divisi Kerja (${divisionList.length})` }}</span>
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Smart Executive Insight Banner -->
                <div v-if="activeInsight" class="pa-chart-insight-bar">
                    <div class="pa-chart-insight-header-row">
                        <div class="pa-chart-insight-badge">
                            <HcisIcon name="stars" size="2xs" />
                            <span>Insight Progres</span>
                        </div>
                        <div class="pa-chart-insight-hint">
                            <HcisIcon name="cursor-fill" size="2xs" />
                            <span>{{ isMobile ? 'Tap batang filter' : 'Klik batang grafik untuk memfilter rater' }}</span>
                        </div>
                    </div>
                    <p class="pa-chart-insight-text">{{ activeInsight }}</p>
                </div>

                <!-- Category Visual Chart Section -->
                <div v-if="analyticsTab === 'kategori'" class="pa-chart-view-section">
                    <div v-if="categoryBreakdown.length" class="pa-chart-canvas-box">
                        <HcisChart
                            :card="false"
                            type="bar-horizontal"
                            :categories="categoryChartCategories"
                            :series="categoryChartSeries"
                            :height="categoryChartHeight"
                            metric-unit="%"
                            :target-value="100"
                            target-label="Target 100%"
                            sentiment="brand"
                            :tooltip-formatter="formatCategoryTooltip"
                            @chart-click="onCategoryChartClick"
                        />
                    </div>

                    <HcisEmptyState
                        v-else
                        title="Belum Ada Data Kategori"
                        description="Data kategori penilai belum tersedia pada siklus ini."
                        icon="pie-chart"
                        size="sm"
                        variant="flat"
                        sentiment="neutral"
                    />
                </div>

                <!-- Division Visual Chart Section -->
                <div v-else-if="analyticsTab === 'divisi'" class="pa-chart-view-section">
                    <div v-if="divisionList.length" class="pa-chart-canvas-box">
                        <HcisChart
                            :card="false"
                            type="bar-horizontal"
                            :categories="divisionChartCategories"
                            :series="divisionChartSeries"
                            :height="divisionChartHeight"
                            metric-unit="%"
                            :target-value="100"
                            target-label="Target 100%"
                            sentiment="brand"
                            :tooltip-formatter="formatDivisionTooltip"
                            @chart-click="onDivisionChartClick"
                        />
                    </div>

                    <HcisEmptyState
                        v-else
                        title="Belum Ada Data Divisi"
                        description="Data distribusi per divisi belum tersedia pada siklus ini."
                        icon="building"
                        size="sm"
                        variant="flat"
                        sentiment="neutral"
                    />
                </div>

                <!-- Footer Summary Strip -->
                <template #footer>
                    <div class="pa-chart-footer-strip">
                        <div class="pa-chart-legend-row">
                            <span class="pa-chart-legend-item">
                                <span class="pa-chart-legend-dot pa-chart-legend-dot--progress" />
                                <span>Capaian</span>
                            </span>
                            <span class="pa-chart-legend-item">
                                <span class="pa-chart-legend-dot pa-chart-legend-dot--target" />
                                <span>Target 100%</span>
                            </span>
                        </div>
                        <HcisButton
                            :label="isMobile ? 'Monitoring Rater Lengkap' : 'Buka Monitoring Rater Lengkap'"
                            variant="brand"
                            appearance="subtle"
                            :size="isMobile ? 'xs' : 'sm'"
                            trailing-icon="arrow-right"
                            @click="pilihTab('PENGISIAN')"
                        />
                    </div>
                </template>
            </HcisCard>


            <!-- 4. Tahap penutupan siklus — dari server, sama dengan stepper -->
            <HcisCard
                title="Tahap Penutupan Siklus"
                :description="isMobile ? '' : deskripsiTahapPenutupan"
                icon="signpost-2-fill"
                sentiment="brand"
                variant="glass"
                size="md"
                pattern="grid"
            >
                <div class="pa-lifecycle-grid is-enam">
                    <div
                        v-for="(t, i) in tahapIkhtisar"
                        :key="t.kode"
                        class="pa-lifecycle-card"
                        :class="`is-${String(t.keadaan || 'MENUNGGU').toLowerCase()}`"
                        role="button"
                        tabindex="0"
                        @click="pilihTahap(t.kode)"
                        @keydown.enter="pilihTahap(t.kode)"
                    >
                        <div class="pa-lifecycle-card__top">
                            <div class="pa-lifecycle-num">{{ String(i + 1).padStart(2, '0') }}</div>
                            <HcisBadge
                                :label="LABEL_KEADAAN[t.keadaan] || 'Menunggu'"
                                :variant="t.keadaan === 'SELESAI' ? 'success' : t.keadaan === 'BERJALAN' ? 'brand' : 'neutral'"
                                appearance="glass"
                                size="2xs"
                            />
                        </div>
                        <div class="pa-lifecycle-card__body">
                            <h4 class="pa-lifecycle-card__title">{{ t.nama }}</h4>
                            <p class="pa-lifecycle-card__desc">{{ t.ringkas || 'Memuat…' }}</p>
                            <p v-if="t.penghambat?.length" class="pa-lifecycle-card__hambat">
                                <HcisIcon name="exclamation-triangle-fill" size="2xs" /> {{ t.penghambat.length }} perlu dibereskan
                            </p>
                            <!-- Catatan informatif (mis. ditutup dengan sebagian besar penugasan kosong); tidak menahan apa pun. -->
                            <p v-else-if="t.catatan" class="pa-lifecycle-card__catatan" data-uji="catatan-tahap-ikhtisar">
                                <HcisIcon name="info-circle-fill" size="2xs" /> {{ t.catatan }}
                            </p>
                        </div>
                        <div class="pa-lifecycle-card__foot">
                            <span>Buka tahap &rarr;</span>
                        </div>
                    </div>
                </div>
            </HcisCard>
        </div>

        <!-- ============================================================
             TAB 2: MONITORING RATER (PENUGASAN EVALUASI 360°)
             ============================================================ -->
        <div v-show="tabAktif === 'PENGISIAN'" class="pa-tab-pengisian-wrap">
            <PaTahapKepala kode="pengisian" :tahap="tahapAlur('pengisian')" class="pa-tahap-kepala-jarak">
                <template #aksi>
                    <!-- Server menolak membuka bila siklus lain masih OPEN; sebabnya disebut di sini, bukan sesudah ditekan. -->
                    <span v-if="canOpenFilling && siklusLainTerbuka" class="pa-aksi-catatan" data-uji="buka-pengisian-terhalang">
                        Siklus {{ siklusLainTerbuka.Kode_Cycle || `#${siklusLainTerbuka.Id_PA_Cycle}` }} masih terbuka; tutup dulu pengisiannya.
                    </span>
                    <HcisButton
                        v-if="canOpenFilling"
                        label="Buka Pengisian"
                        variant="success"
                        size="sm"
                        leading-icon="unlock-fill"
                        :loading="actionLoading"
                        :disabled="Boolean(siklusLainTerbuka)"
                        :title="siklusLainTerbuka ? 'Hanya satu siklus boleh terbuka pada satu waktu.' : undefined"
                        data-uji="buka-pengisian"
                        @click="konfirmasiBukaPengisian"
                    />
                    <HcisButton
                        v-if="canCloseFilling"
                        label="Tutup Pengisian"
                        variant="danger"
                        size="sm"
                        leading-icon="lock-fill"
                        :loading="actionLoading"
                        @click="konfirmasiTutupPengisian"
                    />
                </template>
            </PaTahapKepala>

            <!-- Executive KPI Cockpit Grid for Tab Pengisian (4 Interactive Metric Cards) -->
            <div class="pa-pengisian-cockpit-grid">
                <!-- Card 1: Total Penugasan Rater -->
                <div
                    class="pa-cockpit-card"
                    :class="{ 'is-selected': activeStatusFilter === 'ALL' }"
                    role="button"
                    tabindex="0"
                    title="Klik untuk menampilkan semua penugasan"
                    @click="pilihSaringanStatus('ALL')"
                    @keydown.enter="pilihSaringanStatus('ALL')"
                >
                    <div class="pa-cockpit-card__icon is-brand">
                        <HcisIcon name="people-fill" size="sm" />
                    </div>
                    <div class="pa-cockpit-card__body">
                        <span class="pa-cockpit-card__label">Total Penugasan</span>
                        <div class="pa-cockpit-card__metric">
                            <span class="pa-cockpit-card__val">{{ formatAngka(summaryTotal) }}</span>
                            <span class="pa-cockpit-card__unit">Rater</span>
                        </div>
                        <div class="pa-cockpit-card__footer">
                            <span class="pa-cockpit-card__sub" data-uji="karyawan-dinilai">{{ formatAngka(summaryKaryawanDinilai) }} Karyawan Dinilai</span>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Sudah Selesai -->
                <div
                    class="pa-cockpit-card is-success"
                    :class="{ 'is-selected': activeStatusFilter === 'SELESAI' }"
                    role="button"
                    tabindex="0"
                    title="Klik untuk menyaring rater yang sudah selesai"
                    @click="pilihSaringanStatus('SELESAI')"
                    @keydown.enter="pilihSaringanStatus('SELESAI')"
                >
                    <div class="pa-cockpit-card__icon is-success">
                        <HcisIcon name="check-circle-fill" size="sm" />
                    </div>
                    <div class="pa-cockpit-card__body">
                        <span class="pa-cockpit-card__label">Sudah Selesai</span>
                        <div class="pa-cockpit-card__metric">
                            <span class="pa-cockpit-card__val">{{ formatAngka(summarySelesai) }}</span>
                            <HcisBadge
                                :label="`${summaryPersen}%`"
                                variant="success"
                                size="2xs"
                                appearance="subtle"
                            />
                        </div>
                        <div class="pa-cockpit-card__progress-wrap">
                            <div class="pa-cockpit-card__track">
                                <div class="pa-cockpit-card__fill is-success" :style="{ width: `${summaryPersen}%` }" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Belum Selesai (Belum Mulai + Berjalan) -->
                <div
                    class="pa-cockpit-card is-warning"
                    :class="{ 'is-selected': ['BELUM_SELESAI', 'BELUM_MULAI', 'BERJALAN', 'MENUNGGU_VERIFIKASI'].includes(activeStatusFilter) }"
                    role="button"
                    tabindex="0"
                    title="Klik untuk menyaring rater yang belum selesai"
                    @click="pilihSaringanStatus('BELUM_SELESAI')"
                    @keydown.enter="pilihSaringanStatus('BELUM_SELESAI')"
                >
                    <div class="pa-cockpit-card__icon is-warning">
                        <HcisIcon name="hourglass-split" size="sm" />
                    </div>
                    <div class="pa-cockpit-card__body">
                        <span class="pa-cockpit-card__label">Belum Selesai</span>
                        <div class="pa-cockpit-card__metric">
                            <span class="pa-cockpit-card__val">{{ formatAngka(summaryBelumSelesai) }}</span>
                            <span class="pa-cockpit-card__unit">{{ 100 - summaryPersen }}% sisa</span>
                        </div>
                        <div class="pa-cockpit-card__footer">
                            <!-- Rinciannya berjumlah sama dengan angka kartu: semua yang belum SELESAI. -->
                            <span class="pa-cockpit-card__sub" data-uji="rincian-belum-selesai">
                                <strong>{{ formatAngka(summaryData.belum_mulai || 0) }}</strong> belum mulai &bull; <strong>{{ formatAngka(summaryData.berjalan || 0) }}</strong> berjalan<template v-if="(summaryData.terkumpul || 0) > 0"> &bull; <strong>{{ formatAngka(summaryData.terkumpul) }}</strong> belum dikirim</template><template v-if="(summaryData.menunggu_verifikasi || 0) > 0"> &bull; <strong>{{ formatAngka(summaryData.menunggu_verifikasi) }}</strong> menunggu verifikasi HC Learn</template>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Perhatian Khusus / Status Kritis -->
                <div
                    v-if="summaryTerlambat > 0"
                    class="pa-cockpit-card is-danger"
                    :class="{ 'is-selected': activeStatusFilter === 'TERLAMBAT' }"
                    role="button"
                    tabindex="0"
                    title="Klik untuk menyaring rater yang melewati batas waktu. Termasuk lembar terisi yang belum dikirim dan yang menunggu verifikasi HC Learn; pengingat otomatis hanya dikirim ke penilai yang belum mengisi."
                    @click="pilihSaringanStatus('TERLAMBAT')"
                    @keydown.enter="pilihSaringanStatus('TERLAMBAT')"
                >
                    <div class="pa-cockpit-card__icon is-danger">
                        <HcisIcon name="exclamation-octagon-fill" size="sm" />
                    </div>
                    <div class="pa-cockpit-card__body">
                        <span class="pa-cockpit-card__label">Lewat Tenggat</span>
                        <div class="pa-cockpit-card__metric">
                            <span class="pa-cockpit-card__val text-rose-600">{{ formatAngka(summaryTerlambat) }}</span>
                            <HcisBadge
                                label="Overdue"
                                variant="danger"
                                size="2xs"
                                appearance="solid"
                            />
                        </div>
                        <div class="pa-cockpit-card__footer">
                            <span class="pa-cockpit-card__sub text-rose-600 font-semibold">Lewat tenggat, belum selesai</span>
                        </div>
                    </div>
                </div>
                <div
                    v-else-if="(summaryData.terkumpul || 0) > 0"
                    class="pa-cockpit-card is-terkumpul"
                    :class="{ 'is-selected': activeStatusFilter === 'TERKUMPUL' }"
                    role="button"
                    tabindex="0"
                    title="Klik untuk menyaring lembar terisi yang belum dikirim penilai"
                    @click="pilihSaringanStatus('TERKUMPUL')"
                    @keydown.enter="pilihSaringanStatus('TERKUMPUL')"
                >
                    <div class="pa-cockpit-card__icon is-terkumpul">
                        <HcisIcon name="inbox-fill" size="sm" />
                    </div>
                    <div class="pa-cockpit-card__body">
                        <span class="pa-cockpit-card__label">Terisi Belum Kirim</span>
                        <div class="pa-cockpit-card__metric">
                            <span class="pa-cockpit-card__val">{{ formatAngka(summaryData.terkumpul || 0) }}</span>
                            <HcisBadge
                                label="Terkumpul"
                                variant="warning"
                                size="2xs"
                                appearance="subtle"
                            />
                        </div>
                        <div class="pa-cockpit-card__footer">
                            <span class="pa-cockpit-card__sub">Kuesioner lengkap, butuh submit</span>
                        </div>
                    </div>
                </div>
                <div
                    v-else
                    class="pa-cockpit-card is-safe"
                    role="button"
                    tabindex="0"
                    title="Semua penugasan rater berjalan sesuai tenggat waktu"
                    @click="pilihSaringanStatus('ALL')"
                    @keydown.enter="pilihSaringanStatus('ALL')"
                >
                    <div class="pa-cockpit-card__icon is-safe">
                        <HcisIcon name="shield-check" size="sm" />
                    </div>
                    <div class="pa-cockpit-card__body">
                        <span class="pa-cockpit-card__label">Status Waktu</span>
                        <div class="pa-cockpit-card__metric">
                            <span class="pa-cockpit-card__val text-emerald-600">100%</span>
                            <HcisBadge
                                label="Tepat Waktu"
                                variant="success"
                                size="2xs"
                                appearance="subtle"
                            />
                        </div>
                        <div class="pa-cockpit-card__footer">
                            <span class="pa-cockpit-card__sub">Tidak ada penugasan terlambat</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KIRIMAN YANG MASUK TETAPI TIDAK TERPETAKAN PENUH (HC Learn Review Alert) -->
            <div v-if="kirimanTinjau.length" class="pa-kiriman-tinjau-card" role="region" aria-label="Kiriman HC Learn yang perlu ditinjau">
                <!-- Header Alert Strip -->
                <div class="pa-kiriman-tinjau-header">
                    <div class="pa-kiriman-tinjau-header__main">
                        <div class="pa-kiriman-icon-box">
                            <HcisIcon name="exclamation-octagon-fill" size="sm" />
                        </div>
                        <div class="pa-kiriman-tinjau-header__text">
                            <div class="pa-kiriman-title-row">
                                <strong>{{ formatAngka(kirimanTinjau.length) }} Kiriman HC Learn Perlu Ditinjau</strong>
                                <HcisBadge
                                    :label="`${kirimanTinjau.length} Butuh Pemetaan Ulang`"
                                    variant="danger"
                                    appearance="solid"
                                    size="2xs"
                                    dot
                                />
                            </div>
                            <p v-if="!isMobile" class="pa-kiriman-tinjau-desc">
                                Nilai <strong>sudah tersimpan di database</strong>, namun ada butir soal belum terpetakan ke kompetensi. Petakan ulang tanpa meminta penilai mengisi kembali.
                            </p>
                        </div>
                    </div>

                    <div class="pa-kiriman-tinjau-actions">
                        <!-- Pemetaan ulang hanya diterima server pada siklus OPEN/CLOSED (TERIMA_PAYLOAD) dan bagi pemegang izin penyiapan. -->
                        <span v-if="!bolehPetakanUlang && !memetakanSemua" class="pa-aksi-catatan" data-uji="petakan-ulang-terkunci">
                            {{ alasanTakBolehPetakan }}
                        </span>
                        <HcisButton
                            v-if="bolehPetakanUlang && !memetakanSemua"
                            :label="`Petakan Ulang Semua (${kirimanTinjau.length})`"
                            variant="danger"
                            appearance="solid"
                            size="xs"
                            leading-icon="arrow-repeat"
                            :loading="memetakanSemua"
                            :disabled="Boolean(memetakanUlang)"
                            @click="petakanUlangSemua"
                        />
                        <HcisButton
                            v-else-if="memetakanSemua"
                            :label="`Memetakan ${progresPemetaanSemua.berjalan}/${progresPemetaanSemua.total}…`"
                            variant="danger"
                            appearance="solid"
                            size="xs"
                            leading-icon="arrow-repeat"
                            loading
                            disabled
                        />
                        <HcisButton
                            :label="bukaDetailKiriman ? 'Tutup Daftar' : `Buka Daftar (${kirimanTinjau.length})`"
                            :variant="bukaDetailKiriman ? 'secondary' : 'primary'"
                            :appearance="bukaDetailKiriman ? 'subtle' : 'glass'"
                            size="xs"
                            :leading-icon="bukaDetailKiriman ? 'chevron-up' : 'list-check'"
                            @click="bukaDetailKiriman = !bukaDetailKiriman"
                        />
                        <HcisButton
                            label="Periksa Ulang"
                            variant="ghost"
                            size="xs"
                            leading-icon="arrow-clockwise"
                            :loading="memuatKirimanTinjau"
                            @click="muatKirimanTinjau"
                        />
                    </div>
                </div>

                <!-- Expandable Detail Drawer / Accordion -->
                <div v-show="bukaDetailKiriman" class="pa-kiriman-drawer">
                    <!-- Drawer Controls Toolbar -->
                    <div class="pa-kiriman-drawer-toolbar">
                        <div class="pa-kiriman-search-wrap">
                            <HcisIcon name="search" size="2xs" class="pa-kiriman-search-icon" />
                            <input
                                v-model="cariKirimanTinjau"
                                type="text"
                                class="pa-kiriman-search-input"
                                placeholder="Cari penilai, NIK, peserta, atau kode butir…"
                            />
                            <button
                                v-if="cariKirimanTinjau"
                                type="button"
                                class="pa-kiriman-search-clear"
                                @click="cariKirimanTinjau = ''"
                            >
                                <HcisIcon name="x" size="2xs" />
                            </button>
                        </div>

                        <div class="pa-kiriman-filter-pills">
                            <button
                                type="button"
                                class="pa-kiriman-filter-pill"
                                :class="{ 'is-active': saringanJenisKiriman === 'ALL' }"
                                @click="saringanJenisKiriman = 'ALL'"
                            >
                                Semua ({{ kirimanTinjau.length }})
                            </button>
                            <button
                                type="button"
                                class="pa-kiriman-filter-pill is-danger"
                                :class="{ 'is-active': saringanJenisKiriman === 'TAK_TERPETAKAN' }"
                                @click="saringanJenisKiriman = 'TAK_TERPETAKAN'"
                            >
                                Kode Tak Dikenali
                            </button>
                            <button
                                type="button"
                                class="pa-kiriman-filter-pill is-warning"
                                :class="{ 'is-active': saringanJenisKiriman === 'BUTIR_HILANG' }"
                                @click="saringanJenisKiriman = 'BUTIR_HILANG'"
                            >
                                Butir Tidak Terkirim
                            </button>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div v-if="filteredKirimanTinjau.length" class="pa-kiriman-table-container">
                        <table class="pa-kiriman-table">
                            <thead>
                                <tr>
                                    <th style="min-width: 220px;">Penilai (Rater)</th>
                                    <th style="min-width: 200px;">Karyawan Dinilai</th>
                                    <th style="min-width: 280px;">Kendala &amp; Tindakan yang Diperlukan</th>
                                    <th style="width: 130px; text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="k in paginatedKirimanTinjau" :key="k.payload_id" class="pa-kiriman-tr">
                                    <!-- Col 1: Penilai -->
                                    <td>
                                        <div class="pa-table-person-cell">
                                            <HcisAvatar :name="k.nama_penilai || 'Penilai'" size="xs" shape="circle" variant="purple" />
                                            <div class="pa-table-person-info">
                                                <strong>{{ k.nama_penilai || '—' }}</strong>
                                                <small>{{ k.kode_penilai || '—' }}</small>
                                                <span class="pa-kategori-badge" :class="`pa-kategori-badge--${getCategoryColor(k.kategori)}`" style="width: fit-content; margin-top: 2px;">
                                                    {{ k.kategori || 'Rater' }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Col 2: Karyawan Dinilai -->
                                    <td>
                                        <div class="pa-kiriman-target-box">
                                            <strong>{{ k.nama_peserta || '—' }}</strong>
                                            <small>{{ k.kode_peserta || '—' }}</small>
                                            <span class="pa-kiriman-time-meta">
                                                <HcisIcon name="clock-history" size="2xs" />
                                                <span>{{ k.diterima_at || 'Waktu tidak tercatat' }}</span>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Col 3: Kendala & Saran -->
                                    <td>
                                        <div class="pa-kiriman-issue-box">
                                            <p class="pa-kiriman-saran-text">{{ k.saran }}</p>
                                            <div v-if="k.tak_terpetakan && k.tak_terpetakan.length" class="pa-kiriman-tags-group">
                                                <span class="pa-kiriman-tag-label text-rose-700">Kode tak dikenali:</span>
                                                <span v-for="code in k.tak_terpetakan" :key="code" class="pa-code-pill is-tak-dikenal">
                                                    {{ code }}
                                                </span>
                                            </div>
                                            <div v-if="k.butir_hilang && k.butir_hilang.length" class="pa-kiriman-tags-group">
                                                <span class="pa-kiriman-tag-label text-amber-700">Butir tidak terkirim:</span>
                                                <span v-for="item in k.butir_hilang" :key="item" class="pa-code-pill is-butir-hilang">
                                                    {{ item }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Col 4: Action -->
                                    <td style="text-align: right;">
                                        <HcisButton
                                            v-if="bolehPetakanUlang"
                                            label="Petakan Ulang"
                                            variant="primary"
                                            size="xs"
                                            leading-icon="arrow-repeat"
                                            :loading="memetakanUlang === k.payload_id || memetakanSemua"
                                            :disabled="memetakanSemua"
                                            @click="petakanUlangKiriman(k)"
                                        />
                                        <span v-else class="pa-table-divisi-text">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty result for drawer search -->
                    <div v-else class="pa-kiriman-empty-search">
                        <HcisIcon name="search" size="sm" class="text-slate-400" />
                        <span>Tidak ada kiriman yang cocok dengan kata kunci atau filter saringan.</span>
                        <HcisButton
                            label="Reset Pencarian"
                            variant="ghost"
                            size="2xs"
                            @click="cariKirimanTinjau = ''; saringanJenisKiriman = 'ALL';"
                        />
                    </div>

                    <!-- Pagination for Drawer -->
                    <div v-if="filteredKirimanTinjau.length > perHalamanKirimanTinjau" class="pa-kiriman-drawer-footer">
                        <span class="pa-kiriman-page-info">
                            Menampilkan <strong>{{ (halamanKirimanTinjau - 1) * perHalamanKirimanTinjau + 1 }}</strong> &ndash; <strong>{{ Math.min(halamanKirimanTinjau * perHalamanKirimanTinjau, filteredKirimanTinjau.length) }}</strong> dari <strong>{{ filteredKirimanTinjau.length }}</strong> kiriman
                        </span>
                        <div class="pa-kiriman-page-nav">
                            <button
                                type="button"
                                class="pa-kiriman-nav-btn"
                                :disabled="halamanKirimanTinjau <= 1"
                                title="Halaman sebelumnya"
                                @click="gantiHalamanKiriman(-1)"
                            >
                                <HcisIcon name="chevron-left" size="2xs" />
                            </button>
                            <span class="pa-kiriman-nav-counter">{{ halamanKirimanTinjau }} / {{ totalHalamanKirimanTinjau }}</span>
                            <button
                                type="button"
                                class="pa-kiriman-nav-btn"
                                :disabled="halamanKirimanTinjau >= totalHalamanKirimanTinjau"
                                title="Halaman berikutnya"
                                @click="gantiHalamanKiriman(1)"
                            >
                                <HcisIcon name="chevron-right" size="2xs" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sudut pandang: per karyawan yang dinilai (baku) atau per penilai. -->
            <HcisTabs
                v-model="tampilanPengisian"
                :tabs="[{ key: 'KARYAWAN', label: 'Per karyawan dinilai', icon: 'person-lines-fill' }, { key: 'PENILAI', label: 'Per penilai', icon: 'person-check' }]"
                variant="underline"
                size="sm"
                aria-label="Tampilan pemantauan pengisian"
                class="pa-tampilan-pengisian"
                data-uji="tampilan-pengisian"
            />
            <!-- Panel dan saringan yang sama dengan tampilan per karyawan dinilai. -->
            <div v-if="tampilanPengisian === 'PENILAI' && selectedCycleId" class="pa-monitoring-hub-panel">
                <PaPemantauanPerPenilai
                    ref="perPenilaiRef"
                    :cycle-id="selectedCycleId"
                    :filters="filterDefinitions"
                    :boleh-akses-ulang="bolehOperasi('CETAK_ULANG_PINTU')"
                    @akses-ulang="bukaAksesUlang"
                />
            </div>

            <!-- 4. UNIFIED MONITORING WORKSPACE PANEL (Harmonious Single Card Hub) -->
            <div v-show="tampilanPengisian === 'KARYAWAN'" class="pa-monitoring-hub-panel">
                <!-- Top Toolbar: HcisFilterBar + Category Quick Bar -->
                <div class="pa-monitoring-hub-panel__controls">
                    <HcisFilterBar
                        :search="searchQuery"
                        :search-placeholder="isMobile ? 'Cari nama, NIK, divisi...' : 'Cari nama karyawan dinilai, nama penilai, NIK, atau divisi...'"
                        :search-debounce="280"
                        :quick-chips="statusQuickChips"
                        :active-chip-value="activeStatusFilter"
                        :filters="filterDefinitions"
                        :filter-values="selectedFilterValues"
                        variant="flat"
                        sentiment="brand"
                        size="sm"
                        show-view-toggle
                        v-model:view-mode="viewMode"
                        :results-count="loadingMonitoring ? null : totalPeserta"
                        show-active-tags
                        class="pa-pemantauan-filter-bar"
                        @update:search="onSearchChange"
                        @chip-click="onStatusChipClick"
                        @filter-change="onFilterChange"
                        @reset="resetFilters"
                    />

                    <!-- Harmonized Category Strip (Seamlessly integrated below filter controls) -->
                    <div v-if="categoryBreakdown.length" class="pa-hub-category-strip">
                        <div class="pa-hub-category-strip__label">
                            <HcisIcon name="diagram-3-fill" size="2xs" color="brand" />
                            <span>Kategori Rater:</span>
                        </div>
                        <div class="pa-hub-category-strip__pills">
                            <button
                                v-for="cat in categoryBreakdown"
                                :key="cat.kode"
                                type="button"
                                class="pa-hub-category-pill"
                                :class="{ 'is-active': String(selectedFilterValues.kategori) === String(cat.id || cat.kode) }"
                                :title="`Saring rater kategori ${cat.nama}: ${cat.selesai}/${cat.total} selesai (${cat.persen}%)`"
                                @click="toggleFilterKategori(cat.id || cat.kode)"
                            >
                                <span class="pa-kategori-badge" :class="`pa-kategori-badge--${getCategoryColor(cat.kode)}`">
                                    {{ cat.nama }}
                                </span>
                                <div class="pa-hub-category-pill__progress">
                                    <div class="pa-hub-category-pill__bar">
                                        <span :style="{ width: `${cat.persen}%` }" :class="{ 'is-complete': cat.persen === 100 }" />
                                    </div>
                                    <span class="pa-hub-category-pill__ratio">
                                        <strong>{{ cat.selesai }}</strong>/{{ cat.total }}
                                        <small>({{ cat.persen }}%)</small>
                                    </span>
                                </div>
                                <HcisIcon
                                    v-if="String(selectedFilterValues.kategori) === String(cat.id || cat.kode)"
                                    name="x-circle-fill"
                                    size="2xs"
                                    class="pa-hub-category-pill__clear"
                                />
                            </button>
                        </div>
                    </div>

                    <div v-if="loadingMonitoring" class="pa-loading-shimmer-line" aria-hidden="true" />
                </div>

                <!-- Error State inside Hub -->
                <HcisResultState
                    v-if="errorMonitoring"
                    :status="errorStatus === 403 ? 'warning' : 'danger'"
                    variant="flat"
                    layout="inline"
                    :title="errorStatus === 403 ? 'Akses ditolak' : 'Gagal memuat data pemantauan'"
                    :description="errorMonitoring"
                    primary-text="Coba Lagi"
                    primary-icon="arrow-clockwise"
                    :show-retry="errorStatus !== 403"
                    class="pa-hub-result-state"
                    @primary-click="muatDataMonitoring"
                />

                <!-- Loading Skeletons inside Hub -->
                <div v-else-if="loadingSiklus || (loadingMonitoring && !monitoringRows.length) || (!hasLoadedInitial && !monitoringRows.length)" class="pa-pemantauan-loading" aria-busy="true" aria-label="Memuat data monitoring rater">
                    <!-- Table Skeleton View -->
                    <div v-if="viewMode === 'table'" class="pa-pemantauan-table-skeleton-wrap">
                        <div class="pa-table-toolbar-skeleton">
                            <HcisSkeleton width="280px" height="1.1rem" rounded="sm" />
                            <HcisSkeleton width="130px" height="1.8rem" rounded="md" />
                        </div>

                        <div class="pa-table-skeleton-card">
                            <div class="pa-table-head-skeleton">
                                <HcisSkeleton width="2rem" height="1rem" rounded="xs" />
                                <HcisSkeleton width="180px" height="1rem" rounded="xs" />
                                <HcisSkeleton width="140px" height="1rem" rounded="xs" />
                                <HcisSkeleton width="140px" height="1rem" rounded="xs" />
                                <HcisSkeleton width="100px" height="1rem" rounded="xs" />
                                <HcisSkeleton width="80px" height="1rem" rounded="xs" />
                            </div>

                            <div v-for="n in 5" :key="`skel-row-${n}`" class="pa-table-row-skeleton">
                                <div class="pa-skel-cell-expand">
                                    <HcisSkeleton width="1.6rem" height="1.6rem" rounded="sm" />
                                </div>
                                <div class="pa-skel-cell-person">
                                    <HcisSkeleton width="2.25rem" height="2.25rem" rounded="circle" />
                                    <div class="pa-skel-col-text">
                                        <HcisSkeleton :width="n % 2 === 0 ? '160px' : '130px'" height="1rem" rounded="sm" />
                                        <HcisSkeleton width="100px" height="0.75rem" rounded="xs" />
                                    </div>
                                </div>
                                <div class="pa-skel-cell-div">
                                    <HcisSkeleton :width="n % 3 === 0 ? '140px' : '110px'" height="0.95rem" rounded="sm" />
                                    <HcisSkeleton width="70px" height="0.75rem" rounded="xs" />
                                </div>
                                <div class="pa-skel-cell-progress">
                                    <div class="pa-skel-meta-row">
                                        <HcisSkeleton width="70px" height="0.8rem" rounded="xs" />
                                        <HcisSkeleton width="36px" height="0.8rem" rounded="xs" />
                                    </div>
                                    <HcisSkeleton width="100%" height="6px" rounded="full" />
                                </div>
                                <div class="pa-skel-cell-status">
                                    <HcisSkeleton width="90px" height="1.5rem" rounded="full" />
                                </div>
                                <div class="pa-skel-cell-action">
                                    <HcisSkeleton width="80px" height="1.75rem" rounded="md" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cards Grid Skeleton View -->
                    <div v-else class="pa-pemantauan-skeleton-grid">
                        <div v-for="n in 6" :key="`skel-mon-${n}`" class="pa-pemantauan-skeleton-card">
                            <div class="pa-skel-card-head">
                                <HcisSkeleton width="2.25rem" height="2.25rem" rounded="circle" />
                                <div class="pa-skel-col-text" style="flex: 1;">
                                    <HcisSkeleton width="140px" height="1rem" rounded="sm" />
                                    <HcisSkeleton width="90px" height="0.75rem" rounded="xs" />
                                </div>
                                <HcisSkeleton width="70px" height="1.4rem" rounded="full" />
                            </div>
                            <div class="pa-skel-card-body">
                                <HcisSkeleton width="100%" height="8px" rounded="full" />
                                <div class="pa-skel-meta-row" style="margin-top: 0.5rem;">
                                    <HcisSkeleton width="80px" height="0.8rem" rounded="xs" />
                                    <HcisSkeleton width="50px" height="0.8rem" rounded="xs" />
                                </div>
                            </div>
                            <div class="pa-skel-card-foot">
                                <HcisSkeleton width="100%" height="1.85rem" rounded="md" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State: Filtered / Search has no results -->
                <HcisEmptyState
                    v-else-if="!filteredMonitoringRows.length && hasActiveFilters"
                    title="Tidak Ada Penugasan yang Cocok"
                    description="Tidak ditemukan rekaman penugasan penilaian yang sesuai dengan saringan atau kata kunci pencarian saat ini."
                    icon="search"
                    sentiment="neutral"
                    variant="flat"
                    size="md"
                    class="pa-hub-empty-state"
                >
                    <template #actions>
                        <div class="pa-empty-actions-wrap">
                            <HcisButton
                                label="Atur Ulang Semua Saringan"
                                variant="primary"
                                size="sm"
                                leading-icon="arrow-counterclockwise"
                                @click="resetFilters"
                            />
                            <HcisButton
                                v-if="activeStatusFilter !== 'ALL'"
                                label="Tampilkan Semua Status"
                                variant="secondary"
                                size="sm"
                                leading-icon="funnel"
                                @click="activeStatusFilter = 'ALL'; currentPage = 1; muatDataMonitoring();"
                            />
                            <HcisButton
                                v-if="selectedFilterValues.kategori || selectedFilterValues.id_divisi || selectedFilterValues.id_level"
                                label="Bersihkan Filter Dropdown"
                                variant="secondary"
                                size="sm"
                                leading-icon="x-circle"
                                @click="selectedFilterValues.kategori = ''; selectedFilterValues.id_divisi = ''; selectedFilterValues.id_level = ''; currentPage = 1; muatDataMonitoring();"
                            />
                            <HcisButton
                                v-if="searchQuery"
                                label="Hapus Kata Kunci"
                                variant="secondary"
                                size="sm"
                                leading-icon="x-circle"
                                @click="searchQuery = ''; onSearchChange('');"
                            />
                        </div>
                    </template>
                </HcisEmptyState>

                <!-- Empty State: Zero Data in Cycle (No Raters Assigned) -->
                <HcisEmptyState
                    v-else-if="!filteredMonitoringRows.length"
                    title="Belum Ada Penugasan Rater di Siklus Ini"
                    description="Siklus ini belum memiliki rekaman penugasan evaluasi rater 360°. Silakan siapkan data peserta dan aturan penilai agar penugasan rater terdaftar."
                    icon="people"
                    sentiment="info"
                    variant="flat"
                    size="md"
                    class="pa-hub-empty-state"
                >
                    <template #actions>
                        <div class="pa-empty-actions-wrap">
                            <HcisButton
                                v-if="selectedCycleId"
                                label="Buka Penyiapan Siklus"
                                variant="primary"
                                size="sm"
                                leading-icon="sliders"
                                @click="bukaHalamanPenyiapan"
                            />
                            <HcisButton
                                label="Segarkan Data"
                                variant="secondary"
                                size="sm"
                                leading-icon="arrow-clockwise"
                                :loading="loadingMonitoring"
                                @click="muatDataMonitoring"
                            />
                            <HcisButton
                                label="Lihat Ringkasan Siklus"
                                variant="ghost"
                                size="sm"
                                leading-icon="speedometer2"
                                @click="pilihTab('RINGKASAN')"
                            />
                        </div>
                    </template>
                </HcisEmptyState>

                <!-- Loaded Content inside Hub -->
                <div v-else class="pa-pemantauan-content">
                    <!-- Syncing Background Banner -->
                    <div v-if="loadingMonitoring" class="pa-monitor-sync-banner" aria-live="polite">
                        <HcisIcon name="arrow-repeat" size="2xs" class="pa-spin-icon" />
                        <span>Menyinkronkan data monitoring rater terbaru…</span>
                    </div>

                    <!-- Integrated Header Bar for Table / Cards -->
                    <div class="pa-hub-content-header">
                        <div class="pa-hub-content-header__left">
                            <span class="pa-table-count-label">
                                Menampilkan <strong>{{ formatAngka(groupedParticipants.length) }}</strong> dari <strong>{{ formatAngka(totalPeserta) }}</strong> karyawan dinilai
                                <span class="pa-table-count-sub">(<strong>{{ formatAngka(totalBaris) }}</strong> penugasan penilai cocok)</span>
                            </span>
                        </div>
                        <div class="pa-hub-content-header__right">
                            <HcisButton
                                v-if="viewMode === 'table'"
                                :label="allExpanded ? 'Tutup Semua Rater' : 'Buka Semua Rater'"
                                :leading-icon="allExpanded ? 'chevron-bar-contract' : 'chevron-bar-expand'"
                                variant="secondary"
                                appearance="subtle"
                                size="xs"
                                @click="toggleAllExpand"
                            />
                        </div>
                    </div>

                <!-- Mode 1: Collapsible Grouped Table by Participant (Default) -->
                <div v-if="viewMode === 'table'" class="pa-pemantauan-table-wrap" :class="{ 'is-loading': loadingMonitoring }">
                    <!-- Table Loading Overlay with Spinner & Shimmer -->
                    <div v-if="loadingMonitoring" class="pa-table-loading-overlay" aria-live="polite">
                        <div class="pa-table-loading-spinner-box">
                            <HcisIcon name="arrow-repeat" size="xs" class="pa-spin-icon" color="brand" />
                            <span>Memuat data monitoring rater…</span>
                        </div>
                    </div>
                    <div class="pa-table-scroll-container">
                        <table class="pa-collapsible-table">
                            <thead>
                                <tr>
                                    <th style="width: 3.25rem; text-align: center;"></th>
                                    <th style="min-width: 240px;">Karyawan Dinilai</th>
                                    <th style="min-width: 160px;">Divisi &amp; Level</th>
                                    <th style="min-width: 190px;">Progres Rater</th>
                                    <th style="width: 150px;">Status Evaluasi</th>
                                    <th style="width: 130px; text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="p in groupedParticipants" :key="p.id_key">
                                    <!-- Parent Row: Participant Summary -->
                                    <tr
                                        class="pa-parent-row"
                                        :class="{ 'is-expanded': isParticipantExpanded(p.id_key) }"
                                        tabindex="0"
                                        @click="toggleExpandParticipant(p.id_key)"
                                        @keydown.enter="toggleExpandParticipant(p.id_key)"
                                    >
                                        <!-- Expand Chevron Column -->
                                        <td class="pa-col-expand" @click.stop="toggleExpandParticipant(p.id_key)">
                                            <button
                                                type="button"
                                                class="pa-expand-chevron-btn"
                                                :class="{ 'is-active': isParticipantExpanded(p.id_key) }"
                                                :aria-label="isParticipantExpanded(p.id_key) ? 'Tutup rater' : 'Buka rater'"
                                            >
                                                <HcisIcon name="chevron-right" size="xs" />
                                            </button>
                                        </td>

                                        <!-- Participant Info -->
                                        <td class="pa-col-person sel-dinilai">
                                            <div class="pa-table-person-cell">
                                                <HcisAvatar :name="p.Nama_Karyawan" size="xs" shape="circle" variant="brand" />
                                                <div class="pa-table-person-info">
                                                    <button
                                                        type="button"
                                                        class="pa-table-person-link"
                                                        :title="`Buka detail hasil ${p.Nama_Karyawan}`"
                                                        @click.stop="bukaHasilPeserta(p)"
                                                    >
                                                        {{ p.Nama_Karyawan }}
                                                    </button>
                                                    <small>{{ p.Kode_Karyawan }} &bull; {{ p.Nama_Level || `Level ${p.ID_Level || '-'}` }}</small>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Divisi & Level -->
                                        <td class="pa-col-division">
                                            <div class="pa-division-cell-wrap">
                                                <span class="pa-table-divisi-text">{{ p.Nama_Divisi }}</span>
                                                <small class="pa-table-sublevel-text">{{ p.Nama_Level }}</small>
                                            </div>
                                        </td>

                                        <!-- Progress Bar & Count -->
                                        <td class="pa-col-progress">
                                            <div class="pa-progress-cell-wrap">
                                                <div class="pa-progress-meta-row">
                                                    <span class="pa-progress-count">
                                                        <strong>{{ p.selesaiRater }}</strong>/{{ p.totalRater }} Selesai
                                                    </span>
                                                    <span class="pa-progress-pct-badge" :class="{ 'is-complete': p.persen === 100 }">
                                                        {{ p.persen }}%
                                                    </span>
                                                </div>
                                                <div class="pa-progress-mini-track">
                                                    <div
                                                        class="pa-progress-mini-fill"
                                                        :class="{ 'is-complete': p.persen === 100, 'is-overdue': p.terlambatRater > 0 }"
                                                        :style="{ width: `${p.persen}%` }"
                                                    />
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Status Badge -->
                                        <td class="pa-col-status">
                                            <div class="pa-status-cell">
                                                <HcisStatusBadge
                                                    v-if="p.selesaiRater === p.totalRater && p.totalRater > 0"
                                                    status="ACTIVE"
                                                    label="Lengkap"
                                                    size="xs"
                                                />
                                                <HcisBadge
                                                    v-else-if="p.terlambatRater > 0"
                                                    label="Ada Lewat Tenggat"
                                                    variant="danger"
                                                    appearance="glass"
                                                    size="xs"
                                                    dot
                                                />
                                                <HcisBadge
                                                    v-else-if="p.selesaiRater > 0"
                                                    label="Sedang Berjalan"
                                                    variant="brand"
                                                    appearance="subtle"
                                                    size="xs"
                                                    dot
                                                />
                                                <HcisBadge
                                                    v-else
                                                    label="Belum Mengisi"
                                                    variant="warning"
                                                    appearance="subtle"
                                                    size="xs"
                                                    dot
                                                />
                                            </div>
                                        </td>

                                        <!-- Actions Column -->
                                        <td class="pa-col-action" style="text-align: right;" @click.stop>
                                            <HcisButton
                                                label="Lihat Hasil"
                                                variant="secondary"
                                                appearance="subtle"
                                                size="xs"
                                                leading-icon="folder2-open"
                                                @click.stop="bukaHasilPeserta(p)"
                                            />
                                        </td>
                                    </tr>

                                    <!-- Expanded Child Row: Raters List / Sub-Table -->
                                    <tr v-show="isParticipantExpanded(p.id_key)" class="pa-child-row">
                                        <td colspan="6" class="pa-child-cell">
                                            <div class="pa-subtable-box">
                                                <div class="pa-subtable-header">
                                                    <div class="pa-subtable-title">
                                                        <HcisIcon name="people-fill" size="2xs" color="brand" />
                                                        <span>Daftar Penilai 360° untuk <strong>{{ p.Nama_Karyawan }}</strong> ({{ p.raterTampil < p.totalRater ? `${p.raterTampil} dari ${p.totalRater} penilai, sesuai saringan` : `${p.totalRater} Penilai` }})</span>
                                                    </div>
                                                </div>

                                                <table class="pa-subtable">
                                                    <thead>
                                                        <tr>
                                                            <th style="min-width: 180px;">Penilai (Rater)</th>
                                                            <th style="width: 130px;">Kategori</th>
                                                            <th style="width: 140px;">Status Pengisian</th>
                                                            <th style="min-width: 160px;">Waktu / Batas Waktu</th>
                                                            <th style="width: 130px; text-align: right;">Akses Penilai</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr v-for="rater in p.raters" :key="rater.id_key" class="pa-subtable-tr">
                                                            <!-- Penilai -->
                                                            <td>
                                                                <div class="pa-table-person-cell">
                                                                    <HcisAvatar :name="rater.Nama_Penilai || 'Rater'" size="xs" shape="circle" variant="purple" />
                                                                    <div class="pa-table-person-info">
                                                                        <strong>{{ rater.Nama_Penilai || '—' }}</strong>
                                                                        <small>{{ rater.Kode_Penilai || '—' }}</small>
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <!-- Kategori -->
                                                            <td>
                                                                <span class="pa-kategori-badge" :class="`pa-kategori-badge--${getCategoryColor(rater.Kode_Kategori || rater.Kategori_Penilai)}`">
                                                                    {{ rater.Kategori_Penilai || rater.Nama_Kategori || '—' }}
                                                                </span>
                                                            </td>

                                                            <!-- Status Pengisian -->
                                                            <td>
                                                                <div class="pa-status-cell">
                                                                    <!--
                                                                        TERKUMPUL DIPERIKSA DULU. Lembarnya memang sudah terisi
                                                                        penuh dan Finished_At-nya sudah ada, jadi `isRowSelesai`
                                                                        akan menyebutnya selesai - padahal penilaiannya belum
                                                                        dikirim dan belum ikut dihitung sama sekali.
                                                                    -->
                                                                    <HcisBadge
                                                                        v-if="isRowTerkumpul(rater)"
                                                                        label="Terisi · Belum Dikirim"
                                                                        variant="warning"
                                                                        appearance="subtle"
                                                                        size="xs"
                                                                        dot
                                                                    />
                                                                    <HcisStatusBadge
                                                                        v-else-if="isRowSelesai(rater)"
                                                                        status="ACTIVE"
                                                                        label="Selesai"
                                                                        size="xs"
                                                                    />
                                                                    <HcisBadge
                                                                        v-else-if="rater.Status_Penugasan === 'BERJALAN' || rater.Status === 'BERJALAN'"
                                                                        label="Diisi Sebagian"
                                                                        variant="brand"
                                                                        appearance="subtle"
                                                                        size="xs"
                                                                        dot
                                                                    />
                                                                    <HcisBadge
                                                                        v-else
                                                                        label="Belum Mengisi"
                                                                        variant="warning"
                                                                        appearance="subtle"
                                                                        size="xs"
                                                                        dot
                                                                    />

                                                                    <span v-if="rater.hari_terlambat > 0 && !isRowSelesai(rater)" class="pa-terlambat-pill">
                                                                        Terlambat {{ rater.hari_terlambat }} hr
                                                                    </span>
                                                                </div>
                                                            </td>

                                                            <!-- Waktu / Tenggat -->
                                                            <td>
                                                                <div class="pa-table-time-cell">
                                                                    <span v-if="rater.Waktu_Selesai || rater.Finished_At" class="pa-time-done">
                                                                        <HcisIcon name="check-circle-fill" size="2xs" color="success" />
                                                                        {{ formatDateTime(rater.Waktu_Selesai || rater.Finished_At) }}
                                                                    </span>
                                                                    <span v-else-if="rater.Due_At" class="pa-time-due">
                                                                        <HcisIcon name="calendar-event" size="2xs" />
                                                                        Tenggat: {{ formatDate(rater.Due_At) }}
                                                                    </span>
                                                                    <span v-else class="text-slate-400">—</span>
                                                                </div>
                                                            </td>

                                                            <!-- Akses Pintu / Cetak Ulang -->
                                                            <td class="sel-pintu" style="text-align: right;">
                                                                <!-- Lembar terisi yang tak kunjung dikirim menahan hasil orang ini; dulu hanya ada di tampilan kartu. -->
                                                                <HcisButton
                                                                    v-if="bisaLepasPaksa(rater)"
                                                                    variant="ghost"
                                                                    size="xs"
                                                                    leading-icon="unlock-fill"
                                                                    data-uji="lepas-paksa-tabel"
                                                                    @click.stop="bukaLepasPaksa(rater)"
                                                                >
                                                                    Lepas paksa
                                                                </HcisButton>
                                                                <HcisButton
                                                                    v-if="bisaAksesUlang(rater)"
                                                                    variant="ghost"
                                                                    size="xs"
                                                                    leading-icon="arrow-repeat"
                                                                    :loading="aksesUlang.assignmentId === rater.Id_PA_Assignment && aksesUlang.mengirim"
                                                                    :data-uji="`akses-ulang-${rater.Id_PA_Assignment}`"
                                                                    @click.stop="bukaAksesUlang(rater)"
                                                                >
                                                                    Akses ulang
                                                                </HcisButton>
                                                                <span v-if="!bisaAksesUlang(rater) && !bisaLepasPaksa(rater)" class="pa-table-divisi-text">—</span>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Mode 2: Grid Cards using HcisCard (Grouped by Participant) -->
                <div v-else class="pa-pemantauan-grid-wrap" :class="{ 'is-loading': loadingMonitoring }">
                    <!-- Grid Loading Overlay with Spinner & Shimmer -->
                    <div v-if="loadingMonitoring" class="pa-table-loading-overlay" aria-live="polite">
                        <div class="pa-table-loading-spinner-box">
                            <HcisIcon name="arrow-repeat" size="xs" class="pa-spin-icon" color="brand" />
                            <span>Memuat data monitoring rater…</span>
                        </div>
                    </div>
                    <div class="pa-pemantauan-grid">
                    <HcisCard
                        v-for="p in groupedParticipants"
                        :key="`card-mon-${p.id_key}`"
                        variant="glass"
                        :sentiment="p.selesaiRater === p.totalRater && p.totalRater > 0 ? 'success' : (p.terlambatRater > 0 ? 'danger' : 'brand')"
                        size="sm"
                        glow
                        class="pa-mon-hcis-card"
                    >
                        <template #header>
                            <div class="pa-mon-card-header-inner">
                                <div class="pa-mon-card-target-flex">
                                    <HcisAvatar
                                        :name="p.Nama_Karyawan"
                                        size="xs"
                                        shape="circle"
                                        variant="brand"
                                    />
                                    <div class="pa-mon-card-target-meta">
                                        <span class="pa-eyebrow">KARYAWAN DINILAI</span>
                                        <button
                                            type="button"
                                            class="pa-table-person-link"
                                            :title="`Buka detail hasil ${p.Nama_Karyawan}`"
                                            @click.stop="bukaHasilPeserta(p)"
                                        >
                                            {{ p.Nama_Karyawan }}
                                        </button>
                                        <span class="pa-mon-card-target-sub">
                                            {{ p.Kode_Karyawan }} &bull; {{ p.Nama_Level || `Level ${p.ID_Level || '-'}` }}
                                        </span>
                                    </div>
                                </div>
                                <div class="pa-card-status-wrap">
                                    <HcisStatusBadge
                                        v-if="p.selesaiRater === p.totalRater && p.totalRater > 0"
                                        status="ACTIVE"
                                        label="Lengkap"
                                        size="xs"
                                    />
                                    <HcisBadge
                                        v-else-if="p.terlambatRater > 0"
                                        label="Ada Lewat Tenggat"
                                        variant="danger"
                                        appearance="glass"
                                        size="xs"
                                        dot
                                    />
                                    <HcisBadge
                                        v-else-if="p.selesaiRater > 0"
                                        label="Sedang Berjalan"
                                        variant="brand"
                                        appearance="subtle"
                                        size="xs"
                                        dot
                                    />
                                </div>
                            </div>
                        </template>

                        <div class="pa-mon-card-body-content">
                            <div class="pa-mon-card-division-badge">
                                <HcisIcon name="building" size="2xs" />
                                <span>{{ p.Nama_Divisi }}</span>
                            </div>

                            <!-- Progress Node -->
                            <div class="pa-card-progress-box">
                                <div class="pa-progress-meta-row">
                                    <span class="pa-progress-count">
                                        <strong>{{ p.selesaiRater }}</strong>/{{ p.totalRater }} Rater Selesai
                                    </span>
                                    <span class="pa-progress-pct-badge" :class="{ 'is-complete': p.persen === 100 }">
                                        {{ p.persen }}%
                                    </span>
                                </div>
                                <div class="pa-progress-mini-track">
                                    <div
                                        class="pa-progress-mini-fill"
                                        :class="{ 'is-complete': p.persen === 100, 'is-overdue': p.terlambatRater > 0 }"
                                        :style="{ width: `${p.persen}%` }"
                                    />
                                </div>
                            </div>

                            <!-- Raters List inside Card -->
                            <div class="pa-card-raters-accordion">
                                <button
                                    type="button"
                                    class="pa-card-raters-toggle"
                                    @click="toggleExpandParticipant(p.id_key)"
                                >
                                    <span>{{ isParticipantExpanded(p.id_key) ? 'Sembunyikan Daftar Rater' : `Lihat ${p.raterTampil} Penilai (Rater)` }}</span>
                                    <HcisIcon
                                        name="chevron-down"
                                        size="2xs"
                                        :class="{ 'rotate-180': isParticipantExpanded(p.id_key) }"
                                    />
                                </button>

                                <div v-show="isParticipantExpanded(p.id_key)" class="pa-card-raters-list">
                                    <div
                                        v-for="rater in p.raters"
                                        :key="`card-rater-${rater.id_key}`"
                                        class="pa-card-rater-item"
                                    >
                                        <div class="pa-card-rater-item-top">
                                            <div class="pa-card-rater-avatar-wrap">
                                                <HcisAvatar :name="rater.Nama_Penilai || 'Rater'" size="2xs" shape="circle" variant="purple" />
                                                <strong>{{ rater.Nama_Penilai || '—' }}</strong>
                                            </div>
                                            <span class="pa-kategori-badge" :class="`pa-kategori-badge--${getCategoryColor(rater.Kode_Kategori || rater.Kategori_Penilai)}`">
                                                {{ rater.Kategori_Penilai || rater.Nama_Kategori || '-' }}
                                            </span>
                                        </div>
                                        <div class="pa-card-rater-item-bot">
                                            <div class="pa-card-rater-status-info">
                                                <HcisBadge
                                                    v-if="isRowTerkumpul(rater)"
                                                    label="Terisi · Belum Dikirim"
                                                    variant="warning"
                                                    appearance="subtle"
                                                    size="xs"
                                                    dot
                                                />
                                                <HcisStatusBadge
                                                    v-else-if="isRowSelesai(rater)"
                                                    status="ACTIVE"
                                                    label="Selesai"
                                                    size="xs"
                                                />
                                                <HcisBadge
                                                    v-else-if="rater.Status_Penugasan === 'BERJALAN' || rater.Status === 'BERJALAN'"
                                                    label="Diisi Sebagian"
                                                    variant="brand"
                                                    appearance="subtle"
                                                    size="xs"
                                                    dot
                                                />
                                                <HcisBadge
                                                    v-else
                                                    label="Belum Mengisi"
                                                    variant="warning"
                                                    appearance="subtle"
                                                    size="xs"
                                                    dot
                                                />
                                                <span v-if="rater.hari_terlambat > 0 && !isRowSelesai(rater)" class="pa-terlambat-pill">
                                                    Terlambat {{ rater.hari_terlambat }} hr
                                                </span>
                                            </div>
                                            <div class="sel-pintu">
                                                <HcisButton
                                                    v-if="bisaLepasPaksa(rater)"
                                                    variant="ghost"
                                                    size="xs"
                                                    leading-icon="unlock-fill"
                                                    @click="bukaLepasPaksa(rater)"
                                                >
                                                    Lepas paksa
                                                </HcisButton>
                                                <HcisButton
                                                    v-if="bisaAksesUlang(rater)"
                                                    variant="ghost"
                                                    size="xs"
                                                    leading-icon="arrow-repeat"
                                                    :loading="aksesUlang.assignmentId === rater.Id_PA_Assignment && aksesUlang.mengirim"
                                                    @click="bukaAksesUlang(rater)"
                                                >
                                                    Akses ulang
                                                </HcisButton>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <template #footer>
                            <div class="pa-mon-card-footer-actions">
                                <HcisButton
                                    label="Buka Hasil Peserta"
                                    variant="secondary"
                                    appearance="subtle"
                                    size="xs"
                                    leading-icon="folder2-open"
                                    @click="bukaHasilPeserta(p)"
                                />
                            </div>
                        </template>
                    </HcisCard>
                </div>
                </div>

                <!-- Bottom Pagination using HcisPagination -->
                <div v-if="totalPages > 1 || totalPeserta > perPage" class="pa-pemantauan-pagination-bar">
                    <HcisPagination
                        :total="totalPeserta"
                        :current-page="currentPage"
                        :page-size="perPage"
                        layout="total, prev, pager, next"
                        size="sm"
                        background
                        @current-change="onPageChange"
                    />
                </div>
            </div>
            </div>
        </div>

        <!-- TAHAP 2 · HITUNG & VERIFIKASI: kepala + pilihan sub (hasil / antrean) -->
        <div v-show="tahapAktif === 'verifikasi'" class="pa-tahap-blok">
            <PaTahapKepala kode="verifikasi" :tahap="tahapAlur('verifikasi')">
                <template #aksi>
                    <HcisButton
                        v-if="bisaHitungMassal"
                        :label="menghitungSiklus ? `Menghitung ${formatAngka(progresHitung.diproses)}${progresHitung.total ? '/' + formatAngka(progresHitung.total) : ''} peserta…` : 'Hitung Hasil'"
                        variant="primary"
                        size="sm"
                        leading-icon="calculator-fill"
                        :loading="menghitungSiklus"
                        @click="bukaDialogHitung"
                    />
                </template>
            </PaTahapKepala>
            <div class="pa-tahap-sub" role="tablist" aria-label="Bagian hitung & verifikasi">
                <button type="button" role="tab" :aria-selected="tabAktif === 'HASIL'" :class="{ 'is-aktif': tabAktif === 'HASIL' }" @click="pilihTab('HASIL')">
                    <HcisIcon name="calculator" size="2xs" /> Hasil per peserta
                </button>
                <button type="button" role="tab" :aria-selected="tabAktif === 'KERANJANG'" :class="{ 'is-aktif': tabAktif === 'KERANJANG' }" @click="pilihTab('KERANJANG')">
                    <HcisIcon name="inbox-fill" size="2xs" /> Antrean tindak lanjut
                    <b v-if="totalKeranjang > 0">{{ formatAngka(totalKeranjang) }}</b>
                </button>
            </div>
        </div>

        <!-- ============================================================
             TAB 3: ANTREAN TINDAK LANJUT ADMIN (KERANJANG KEPUTUSAN)
             ============================================================ -->
        <div v-show="tabAktif === 'KERANJANG'" class="pa-tab-keranjang-wrap">
            <!-- Context Banner: Keranjang Tindak Lanjut Admin -->
            <HcisContextBanner
                title="Keranjang Tindak Lanjut Admin"
                kicker="PUSAT KENDALI · EXCEPTION & REVIEW"
                subtitle="Daftar peserta dengan status tertahan kendala kalkulasi dan antrean ulasan naratif susulan yang membutuhkan intervensi admin."
                icon="inboxes-fill"
                sentiment="warning"
                variant="glass"
                pattern="grid"
                :glow="true"
                class="pa-keranjang-banner"
            >
                <template #scope>
                    <HcisBadge
                        :label="totalKeranjang > 0 ? `${formatAngka(totalKeranjang)} Tindakan Perlu Diselesaikan` : 'Seluruh Antrean Bersih'"
                        :variant="totalKeranjang > 0 ? 'danger' : 'success'"
                        appearance="glass"
                        size="sm"
                        dot
                    />
                </template>
                <template #actions>
                    <HcisButton
                        v-if="totalTertahan > 0 && subTabKeranjang === 'TERTAHAN' && bolehHitungUlang"
                        label="Hitung Ulang Semua Tertahan"
                        variant="warning"
                        appearance="solid"
                        size="xs"
                        leading-icon="arrow-repeat"
                        :loading="menyapu"
                        :disabled="menyapu"
                        @click="jalankanSapu('TERTAHAN')"
                    />
                </template>
            </HcisContextBanner>

            <!-- Compact Cockpit Control Toolbar -->
            <div class="pa-queue-subtab-toolbar">
                <div class="pa-queue-subtab-left">
                    <div class="pa-queue-subtab-pill-group" role="tablist" aria-label="Kategori Antrean">
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="subTabKeranjang === 'TERTAHAN'"
                            class="pa-queue-subtab-btn"
                            :class="{ 'is-active': subTabKeranjang === 'TERTAHAN' }"
                            @click="gantiSubTabKeranjang('TERTAHAN')"
                        >
                            <HcisIcon name="exclamation-octagon-fill" size="2xs" :color="subTabKeranjang === 'TERTAHAN' ? 'danger' : 'muted'" />
                            <span class="pa-queue-subtab-label">Kendala Data &amp; Perhitungan</span>
                            <span class="pa-queue-subtab-badge" :class="totalTertahan > 0 ? 'is-danger' : 'is-muted'">
                                {{ formatAngka(totalTertahan) }}
                            </span>
                        </button>

                        <button
                            type="button"
                            role="tab"
                            :aria-selected="subTabKeranjang === 'ULASAN'"
                            class="pa-queue-subtab-btn"
                            :class="{ 'is-active': subTabKeranjang === 'ULASAN' }"
                            @click="gantiSubTabKeranjang('ULASAN')"
                        >
                            <HcisIcon name="chat-quote-fill" size="2xs" :color="subTabKeranjang === 'ULASAN' ? 'amber' : 'muted'" />
                            <span class="pa-queue-subtab-label">Ulasan Naratif &amp; Susulan</span>
                            <span class="pa-queue-subtab-badge" :class="totalUlasan > 0 ? 'is-amber' : 'is-muted'">
                                {{ formatAngka(totalUlasan) }}
                            </span>
                        </button>
                    </div>

                    <HcisBadge
                        :label="totalKeranjang > 0 ? `${formatAngka(totalKeranjang)} Tindakan Admin Perlu Diselesaikan` : 'Seluruh Antrean Bersih'"
                        :variant="totalKeranjang > 0 ? 'danger' : 'success'"
                        appearance="glass"
                        size="xs"
                        dot
                    />
                </div>

                <div class="pa-queue-subtab-right">
                    <HcisButton
                        v-if="totalTertahan > 0 && subTabKeranjang === 'TERTAHAN' && bolehHitungUlang"
                        :label="menyapu ? `Memproses ${formatAngka(progresSapu.diperiksa)} peserta…` : 'Hitung Ulang Semua Tertahan'"
                        variant="warning"
                        appearance="solid"
                        size="xs"
                        leading-icon="arrow-repeat"
                        :loading="menyapu"
                        :disabled="menyapu"
                        @click="jalankanSapu('TERTAHAN')"
                    />
                    <HcisButton
                        label="Buka Hasil &amp; Kalibrasi &rarr;"
                        variant="secondary"
                        appearance="subtle"
                        size="xs"
                        @click="pilihTab('HASIL')"
                    />
                </div>
            </div>

            <!-- Compact Horizontal Queue Selector Strips -->
            <!-- Sub-Tab 1: Kendala Data & Perhitungan (Tertahan) -->
            <div v-show="subTabKeranjang === 'TERTAHAN'" class="pa-compact-queue-strip">
                <div
                    v-for="k in antreanTertahan"
                    :key="k.jenis"
                    class="pa-compact-tile"
                    :class="[`pa-compact-tile--${k.nada}`, { 'is-active': keranjangAktif === k.jenis, 'is-empty': !k.jumlah }]"
                    role="button"
                    tabindex="0"
                    :aria-selected="keranjangAktif === k.jenis"
                    @click="k.tahap ? pilihTahap(k.tahap) : bukaKeranjang(k.jenis)"
                    @keydown.enter="k.tahap ? pilihTahap(k.tahap) : bukaKeranjang(k.jenis)"
                >
                    <div class="pa-compact-tile__head">
                        <div class="pa-compact-tile__icon-box" :class="`is-${k.nada}`">
                            <HcisIcon :name="k.icon" size="2xs" />
                        </div>
                        <div class="pa-compact-tile__count" :class="`text-${k.nada}`">
                            {{ formatAngka(k.jumlah) }}
                        </div>
                    </div>
                    <div class="pa-compact-tile__info">
                        <div class="pa-compact-tile__title">{{ k.label }}</div>
                        <div class="pa-compact-tile__desc">{{ k.sebab }}</div>
                    </div>
                    <div v-if="keranjangAktif === k.jenis" class="pa-compact-tile__active-indicator" />
                </div>
            </div>
            <!-- Kartu sebab boleh tumpang tindih: jumlahnya tidak dijumlahkan menjadi total. -->
            <p v-show="subTabKeranjang === 'TERTAHAN'" class="pa-queue-strip-note">
                <HcisIcon name="info-circle" size="2xs" color="muted" />
                Satu peserta bisa tertahan karena lebih dari satu sebab dan muncul di beberapa kartu sekaligus;
                total {{ formatAngka(keranjang?.tertahan || 0) }} peserta tertahan dihitung per orang.
            </p>

            <!-- Sub-Tab 2: Ulasan Naratif & Kiriman Susulan -->
            <div v-show="subTabKeranjang === 'ULASAN'" class="pa-compact-queue-strip pa-compact-queue-strip--dual">
                <div
                    v-for="k in antreanUlasan"
                    :key="k.jenis"
                    class="pa-compact-tile"
                    :class="[`pa-compact-tile--${k.nada}`, { 'is-active': keranjangAktif === k.jenis, 'is-empty': !k.jumlah }]"
                    role="button"
                    tabindex="0"
                    :aria-selected="keranjangAktif === k.jenis"
                    @click="k.tahap ? pilihTahap(k.tahap) : bukaKeranjang(k.jenis)"
                    @keydown.enter="k.tahap ? pilihTahap(k.tahap) : bukaKeranjang(k.jenis)"
                >
                    <div class="pa-compact-tile__head">
                        <div class="pa-compact-tile__icon-box" :class="`is-${k.nada}`">
                            <HcisIcon :name="k.icon" size="2xs" />
                        </div>
                        <div class="pa-compact-tile__count" :class="`text-${k.nada}`">
                            {{ formatAngka(k.jumlah) }}
                        </div>
                    </div>
                    <div class="pa-compact-tile__info">
                        <div class="pa-compact-tile__title">{{ k.label }}</div>
                        <div class="pa-compact-tile__desc">{{ k.sebab }}</div>
                    </div>
                    <div v-if="keranjangAktif === k.jenis" class="pa-compact-tile__active-indicator" />
                </div>
            </div>

            <!-- Active Queue Detail Table Panel (Direct View) -->
            <HcisCard
                variant="card"
                size="md"
                class="pa-active-queue-panel"
            >
                <template #header>
                    <div class="pa-active-queue-header">
                        <div class="pa-active-queue-header__left">
                            <div class="pa-queue-badge-title-row">
                                <div class="pa-queue-active-icon-box" :class="`is-${detailKeranjangAktif?.nada || 'brand'}`">
                                    <HcisIcon :name="detailKeranjangAktif?.icon || 'folder-check'" size="xs" />
                                </div>
                                <div>
                                    <div class="pa-queue-title-badge-flex">
                                        <h4 class="pa-active-queue-title">{{ labelKeranjangAktif || 'Pilih Kategori Antrean' }}</h4>
                                        <HcisBadge
                                            v-if="isiKeranjang"
                                            :label="`${formatAngka(isiKeranjang.total || isiKeranjang.baris?.length || 0)} Peserta`"
                                            variant="brand"
                                            appearance="glass"
                                            size="xs"
                                        />
                                    </div>
                                    <p class="pa-active-queue-sub">{{ detailKeranjangAktif?.sebab || 'Klik salah satu kartu kategori di atas untuk melihat daftar peserta.' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="pa-active-queue-header__right">
                            <!-- In-Queue Search Box -->
                            <div class="pa-queue-search-wrap">
                                <div class="pa-queue-search-input-box">
                                    <HcisIcon name="search" size="2xs" color="muted" />
                                    <input
                                        v-model="pencarianKeranjang"
                                        type="search"
                                        placeholder="Cari nama atau NIK di seluruh antrean ini..."
                                        class="pa-queue-search-input"
                                    />
                                    <button
                                        v-if="pencarianKeranjang"
                                        type="button"
                                        class="pa-search-clear-btn"
                                        title="Hapus pencarian"
                                        @click="pencarianKeranjang = ''"
                                    >
                                        <HcisIcon name="x-circle-fill" size="2xs" />
                                    </button>
                                </div>
                            </div>

                            <HcisButton
                                v-if="['needs_kpi', 'needs_rater', 'tertahan_lain', 'siap_dihitung', 'tertahan'].includes(keranjangAktif) && bolehHitungUlang"
                                :label="menyapu ? `Memproses ${formatAngka(progresSapu.diperiksa)} peserta…` : keranjangAktif === 'siap_dihitung' ? 'Hitung Semua yang Siap' : 'Hitung Ulang Semua Tertahan'"
                                variant="warning"
                                appearance="subtle"
                                size="xs"
                                leading-icon="arrow-repeat"
                                :loading="menyapu"
                                :disabled="menyapu"
                                @click="jalankanSapu(keranjangAktif === 'siap_dihitung' ? 'SIAP' : 'TERTAHAN')"
                            />
                        </div>
                    </div>
                </template>

                <!-- Loading State -->
                <div v-if="memuatKeranjang" class="pa-queue-loading">
                    <div class="pa-queue-skeleton-strip">
                        <HcisSkeleton width="180px" height="1.1rem" rounded="sm" />
                        <HcisSkeleton width="100%" height="2.75rem" rounded="md" />
                        <HcisSkeleton width="100%" height="2.75rem" rounded="md" />
                        <HcisSkeleton width="100%" height="2.75rem" rounded="md" />
                    </div>
                </div>

                <!-- Loaded Table -->
                <div v-else-if="filteredIsiKeranjangBaris?.length" class="pa-queue-table-wrap">
                    <table class="pa-queue-table">
                        <thead>
                            <tr>
                                <th style="min-width: 240px;">Karyawan Dinilai</th>
                                <th style="min-width: 180px;">Jabatan &amp; Unit Kerja</th>
                                <th style="min-width: 170px;">Komponen Nilai</th>
                                <th style="width: 130px;">Skor &amp; Grade</th>
                                <th style="min-width: 260px;">Diagnosa &amp; Status</th>
                                <th style="width: 140px; text-align: right;">Aksi Tindak Lanjut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="b in filteredIsiKeranjangBaris"
                                :key="b.Id_PA_Participant"
                                class="pa-queue-tr--interactive"
                                tabindex="0"
                                :title="`Buka detail verifikasi ${b.Nama_Karyawan_Saat_Cycle || b.Kode_Karyawan}`"
                                @click="bukaVerifikasi(b)"
                                @keydown.enter="bukaVerifikasi(b)"
                            >
                                <!-- Person Column -->
                                <td>
                                    <div class="pa-table-person-cell">
                                        <HcisAvatar
                                            :name="b.Nama_Karyawan_Saat_Cycle"
                                            size="xs"
                                            shape="circle"
                                            variant="brand"
                                        />
                                        <div class="pa-table-person-info">
                                            <button
                                                type="button"
                                                class="pa-table-person-link"
                                                :title="`Buka verifikasi ${b.Nama_Karyawan_Saat_Cycle}`"
                                                @click.stop="bukaVerifikasi(b)"
                                            >
                                                {{ b.Nama_Karyawan_Saat_Cycle }}
                                            </button>
                                            <small>{{ b.Kode_Karyawan }}</small>
                                        </div>
                                    </div>
                                </td>

                                <!-- Position & Division -->
                                <td>
                                    <div class="pa-table-job-info">
                                        <strong class="pa-job-title">{{ b.Nama_Jabatan_Saat_Cycle || '—' }}</strong>
                                        <div class="pa-job-meta">
                                            <span>{{ b.Nama_Divisi_Saat_Cycle || '—' }}</span>
                                            <span v-if="b.Nama_Level_Saat_Cycle" class="pa-level-dot-badge">
                                                &bull; {{ b.Nama_Level_Saat_Cycle }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Pillar Scores Breakdown -->
                                <td>
                                    <div class="pa-pillar-scores-cell">
                                        <div class="pa-score-row">
                                            <span class="pa-score-tag is-kpi">KPI</span>
                                            <strong class="pa-score-val">{{ b.Nilai_KPI != null ? formatNilai(b.Nilai_KPI) : '—' }}</strong>
                                        </div>
                                        <div class="pa-score-row">
                                            <span class="pa-score-tag is-komp">Komp</span>
                                            <strong class="pa-score-val">{{ b.Nilai_Kompetensi != null ? formatNilai(b.Nilai_Kompetensi) : '—' }}</strong>
                                        </div>
                                    </div>
                                </td>

                                <!-- Final Score & Grade -->
                                <td>
                                    <div class="pa-final-grade-cell">
                                        <div class="pa-final-num">
                                            {{ b.Final_Score != null ? formatNilai(b.Final_Score) : '—' }}
                                        </div>
                                        <HcisBadge
                                            v-if="b.Kode_Grade_Final"
                                            :label="b.Kode_Grade_Final"
                                            :variant="getGradeBadgeVariant(b.Kode_Grade_Final)"
                                            appearance="glass"
                                            size="xs"
                                        />
                                        <span v-else class="pa-no-grade-text">Belum ada</span>
                                    </div>
                                </td>

                                <!-- Reason & Diagnosis -->
                                <td>
                                    <div class="pa-reason-cell">
                                        <HcisBadge
                                            v-if="Number(b.Jumlah_KPI_Belum_Disetujui) > 0"
                                            :label="`KPI Belum Lengkap (${b.Jumlah_KPI_Belum_Disetujui})`"
                                            variant="danger"
                                            appearance="subtle"
                                            size="xs"
                                            dot
                                        />
                                        <div v-if="(b.kategori_gugur || []).length" class="pa-reason-text-row" data-uji="kategori-gugur">
                                            <HcisIcon name="people" size="2xs" color="danger" />
                                            <span class="pa-reason-desc">
                                                Kategori tidak dihitung: <strong>{{ b.kategori_gugur.join(', ') }}</strong>
                                            </span>
                                        </div>
                                        <div v-if="(b.bulan_kpi_kurang || []).length" class="pa-reason-text-row" data-uji="bulan-kpi-kurang">
                                            <HcisIcon name="calendar-x" size="2xs" color="danger" />
                                            <span class="pa-reason-desc">
                                                KPI belum diisi/disetujui: <strong>{{ b.bulan_kpi_kurang.map(namaBulanKpi).join(', ') }}</strong>
                                            </span>
                                        </div>
                                        <div class="pa-reason-text-row">
                                            <HcisIcon
                                                :name="b.Alasan_Tertahan ? 'exclamation-circle-fill' : 'check-circle-fill'"
                                                size="2xs"
                                                :color="b.Alasan_Tertahan ? 'warning' : 'success'"
                                            />
                                            <span class="pa-reason-desc">
                                                {{ b.Alasan_Tertahan || (b.Status_Hasil === 'DIHITUNG' ? 'Siap ditinjau & dikalibrasi' : (b.Status_Hasil === 'TERBIT' ? 'Sudah dipublikasikan ke karyawan' : 'Menunggu kalkulasi evaluasi')) }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Action -->
                                <td style="text-align: right;" @click.stop>
                                    <!-- Sesudah pengisian ditutup, yang tak punya angka boleh dikeluarkan beralasan
                                         supaya siklus bisa ditutup (14 Okt 2026). -->
                                    <HcisButton
                                        v-if="bolehKecualikanSesudahTutup(b)"
                                        label="Kecualikan"
                                        variant="secondary"
                                        appearance="subtle"
                                        size="xs"
                                        leading-icon="person-dash"
                                        :data-uji="`kecualikan-${b.Id_PA_Participant}`"
                                        @click="bukaKecualikan(b)"
                                    />
                                    <HcisButton
                                        label="Buka Verifikasi"
                                        variant="secondary"
                                        appearance="subtle"
                                        size="xs"
                                        trailing-icon="arrow-right"
                                        @click="bukaVerifikasi(b)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State in Queue -->
                <div v-else class="pa-queue-empty">
                    <HcisEmptyState
                        :title="pencarianKeranjang ? 'Tidak ada peserta yang cocok' : 'Antrean Ini Bersih'"
                        :description="pencarianKeranjang ? `Tidak ada peserta bernama atau ber-NIK '${pencarianKeranjang}' pada kategori antrean ini.` : 'Seluruh peserta pada kategori ini telah selesai ditindaklanjuti.'"
                        :icon="pencarianKeranjang ? 'search' : 'check2-circle'"
                    >
                        <template v-if="pencarianKeranjang" #actions>
                            <HcisButton
                                label="Reset Pencarian"
                                variant="secondary"
                                size="xs"
                                leading-icon="arrow-counterclockwise"
                                @click="pencarianKeranjang = ''"
                            />
                        </template>
                    </HcisEmptyState>
                </div>

                <!-- Bottom Pagination for Queue -->
                <template v-if="isiKeranjang?.total_halaman > 1 || (isiKeranjang?.total || 0) > (isiKeranjang?.per_halaman || 25)" #footer>
                    <div class="pa-queue-pagination-bar">
                        <div class="pa-queue-pagination-info">
                            Menampilkan halaman <strong>{{ halamanKeranjang }}</strong> dari <strong>{{ isiKeranjang?.total_halaman || 1 }}</strong> (Total <strong>{{ formatAngka(isiKeranjang?.total || 0) }}</strong> peserta)
                        </div>
                        <HcisPagination
                            :total="isiKeranjang?.total || 0"
                            :current-page="halamanKeranjang"
                            :page-size="perHalamanKeranjang"
                            layout="prev, pager, next"
                            size="xs"
                            background
                            @current-change="(p) => bukaKeranjang(keranjangAktif, p)"
                        />
                    </div>
                </template>
            </HcisCard>

            <!-- Bottom Forward Handoff Bar to Tab 4 -->
            <div class="pa-next-stage-handoff-bar">
                <div class="pa-next-stage-handoff-left">
                    <div class="pa-handoff-icon-box">
                        <HcisIcon name="arrow-right-circle-fill" size="xs" color="brand" />
                    </div>
                    <span>
                        Alur Selanjutnya: Peserta yang telah tuntas ditindaklanjuti langsung dialirkan ke <strong>Tab Hasil, Kalibrasi &amp; Publikasi (Tab 4)</strong>.
                    </span>
                </div>
                <HcisButton
                    label="Buka Hasil &amp; Kalibrasi &rarr;"
                    variant="brand"
                    appearance="solid"
                    size="xs"
                    @click="pilihTab('HASIL')"
                />
            </div>
        </div>

        <!-- ============================================================
             TAB 4: HASIL, KALIBRASI & PUBLIKASI (DESIGN SYSTEM COMPLIANT)
             ============================================================ -->
        <div v-show="tabAktif === 'HASIL'" class="pa-tab-hasil-wrap">
            <!-- Context Banner: Hasil & Penerbitan -->
            <HcisContextBanner
                title="Hasil Penilaian & Publikasi"
                kicker="MONITORING HASIL · EVALUASI AKHIR"
                subtitle="Monitoring distribusi grade hasil evaluasi, status peninjauan komite, dan proses penerbitan hasil kepada karyawan."
                icon="award-fill"
                sentiment="brand"
                variant="glass"
                pattern="grid"
                :glow="true"
                class="pa-hasil-banner"
            >
                <template #scope>
                    <HcisBadge
                        :label="`${formatAngka(totalPesertaHasil)} Hasil Terhitung`"
                        variant="brand"
                        appearance="glass"
                        size="sm"
                        dot
                    />
                </template>
                <template #capabilities>
                    <div class="pa-hasil-banner-meta">
                        <span>{{ formatAngka(cacahHasil.TERBIT) }} Terbit</span>
                        <span class="pa-sep">&bull;</span>
                        <span>{{ formatAngka(cacahHasil.SIAP) }} Siap Tinjau</span>
                        <template v-if="cacahHasil.TERTAHAN > 0">
                            <span class="pa-sep">&bull;</span>
                            <span class="text-danger font-bold">{{ formatAngka(cacahHasil.TERTAHAN) }} Tertahan</span>
                        </template>
                    </div>
                </template>
            </HcisContextBanner>

            <!-- 1. Executive Summary Stat Cards & Specialized Calibration Gateways -->
            <div class="pa-hasil-top-cockpit">
                <!-- 4 Metrics Stats Strip -->
                <div class="pa-hasil-metrics-grid">
                    <div class="pa-hasil-stat-card">
                        <div class="pa-hasil-stat-icon is-brand">
                            <HcisIcon name="people-fill" size="xs" />
                        </div>
                        <div class="pa-hasil-stat-content">
                            <span class="pa-hasil-stat-label">Total Hasil Terhitung</span>
                            <div class="pa-hasil-stat-val-row">
                                <strong class="pa-hasil-stat-num">{{ formatAngka(totalPesertaHasil) }}</strong>
                                <span class="pa-hasil-stat-unit">Peserta</span>
                            </div>
                            <small class="pa-hasil-stat-sub">Karyawan dengan kalkulasi nilai</small>
                        </div>
                    </div>

                    <div class="pa-hasil-stat-card">
                        <div class="pa-hasil-stat-icon is-warning">
                            <HcisIcon name="hourglass-split" size="xs" />
                        </div>
                        <div class="pa-hasil-stat-content">
                            <span class="pa-hasil-stat-label">Menunggu Review</span>
                            <div class="pa-hasil-stat-val-row">
                                <strong class="pa-hasil-stat-num text-warning">{{ formatAngka(cacahHasil.SIAP) }}</strong>
                                <span class="pa-hasil-stat-unit">Peserta</span>
                            </div>
                            <small class="pa-hasil-stat-sub">Sudah dihitung, siap ditinjau sebelum terbit</small>
                        </div>
                    </div>

                    <div class="pa-hasil-stat-card">
                        <div class="pa-hasil-stat-icon is-success">
                            <HcisIcon name="patch-check-fill" size="xs" />
                        </div>
                        <div class="pa-hasil-stat-content">
                            <span class="pa-hasil-stat-label">Hasil Sudah Terbit</span>
                            <div class="pa-hasil-stat-val-row">
                                <strong class="pa-hasil-stat-num text-success">{{ formatAngka(cacahHasil.TERBIT) }}</strong>
                                <span class="pa-hasil-stat-unit">Peserta</span>
                            </div>
                            <small class="pa-hasil-stat-sub">Telah dipublikasikan ke karyawan</small>
                        </div>
                    </div>

                    <!-- Pengganti "Rata-rata Skor Akhir": skor & grade sengaja tidak tampil di tahap ini. -->
                    <div
                        class="pa-hasil-stat-card is-clickable"
                        role="button"
                        tabindex="0"
                        title="Buka Antrean Tindak Lanjut"
                        data-uji="kartu-perlu-tindak-lanjut"
                        @click="pilihTab('KERANJANG')"
                        @keydown.enter="pilihTab('KERANJANG')"
                    >
                        <div class="pa-hasil-stat-icon is-purple">
                            <HcisIcon name="inbox-fill" size="xs" />
                        </div>
                        <div class="pa-hasil-stat-content">
                            <span class="pa-hasil-stat-label">Belum Masuk Tab Ini</span>
                            <div class="pa-hasil-stat-val-row">
                                <strong class="pa-hasil-stat-num text-purple">{{ formatAngka(cacahHasil.TERTAHAN + cacahHasil.BELUM) }}</strong>
                                <span class="pa-hasil-stat-unit">Peserta</span>
                            </div>
                            <small class="pa-hasil-stat-sub">{{ formatAngka(cacahHasil.TERTAHAN) }} tertahan · {{ formatAngka(cacahHasil.BELUM) }} belum dihitung → Antrean</small>
                        </div>
                    </div>
                </div>

            </div>

            <!-- 2. Controls & Filter Bar -->
            <div class="pa-hasil-controls-card">
                <div class="pa-hasil-controls-top">
                    <!-- Saringan Status Quick Chips -->
                    <div class="pa-hasil-quick-chips" role="group" aria-label="Saringan Status Hasil">
                        <button
                            type="button"
                            class="pa-hasil-chip"
                            :class="{ 'is-active': saringHasil === 'SEMUA' }"
                            @click="saringHasil = 'SEMUA'; halamanHasil = 1;"
                        >
                            <span>Semua Hasil</span>
                            <strong class="pa-hasil-chip-count">{{ formatAngka(cacahHasil.SEMUA) }}</strong>
                        </button>

                        <button
                            type="button"
                            class="pa-hasil-chip is-warning"
                            :class="{ 'is-active': saringHasil === 'SIAP' }"
                            @click="saringHasil = 'SIAP'; halamanHasil = 1;"
                        >
                            <span class="pa-chip-dot is-warning" />
                            <span>Menunggu Review</span>
                            <strong class="pa-hasil-chip-count">{{ formatAngka(cacahHasil.SIAP) }}</strong>
                        </button>

                        <button
                            type="button"
                            class="pa-hasil-chip is-success"
                            :class="{ 'is-active': saringHasil === 'TERBIT' }"
                            @click="saringHasil = 'TERBIT'; halamanHasil = 1;"
                        >
                            <span class="pa-chip-dot is-success" />
                            <span>Sudah Terbit</span>
                            <strong class="pa-hasil-chip-count">{{ formatAngka(cacahHasil.TERBIT) }}</strong>
                        </button>

                    </div>

                    <!-- Actions Right -->
                    <div class="pa-hasil-controls-actions">
                        <HcisButton
                            label="Muat Ulang"
                            variant="secondary"
                            appearance="subtle"
                            size="xs"
                            leading-icon="arrow-clockwise"
                            :loading="memuatPeserta"
                            @click="muatPeserta"
                        />
                    </div>
                </div>

                <!-- Secondary Filters Row: Search, Select Filters, View Mode -->
                <div class="pa-hasil-filters-row">
                    <div class="pa-hasil-search-box">
                        <HcisIcon name="search" size="2xs" color="muted" />
                        <input
                            v-model="searchQueryHasil"
                            type="search"
                            placeholder="Cari nama atau NIK..."
                            class="pa-hasil-search-input"
                            @input="halamanHasil = 1"
                        />
                        <button
                            v-if="searchQueryHasil"
                            type="button"
                            class="pa-search-clear-btn"
                            title="Hapus pencarian"
                            @click="searchQueryHasil = ''; halamanHasil = 1;"
                        >
                            <HcisIcon name="x-circle-fill" size="2xs" />
                        </button>
                    </div>

                    <!-- Divisi Dropdown Filter -->
                    <div v-if="uniqueDivisiHasil.length" class="pa-hasil-filter-select-wrap">
                        <select
                            v-model="filterDivisiHasil"
                            class="pa-hasil-filter-select"
                            @change="halamanHasil = 1"
                        >
                            <option value="">Semua Divisi</option>
                            <option
                                v-for="d in uniqueDivisiHasil"
                                :key="`div-${d.value}`"
                                :value="d.value"
                            >
                                {{ d.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Reset Filter Button -->
                    <HcisButton
                        v-if="searchQueryHasil || filterDivisiHasil || saringHasil !== 'SEMUA'"
                        label="Reset Saringan"
                        variant="secondary"
                        appearance="subtle"
                        size="xs"
                        leading-icon="arrow-counterclockwise"
                        @click="resetFilterHasil"
                    />

                    <!-- View Mode Switcher -->
                    <div class="pa-hasil-view-switch">
                        <button
                            type="button"
                            class="pa-view-switch-btn"
                            :class="{ 'is-active': viewModeHasil === 'table' }"
                            title="Tampilan Tabel"
                            @click="viewModeHasil = 'table'"
                        >
                            <HcisIcon name="table" size="2xs" />
                        </button>
                        <button
                            type="button"
                            class="pa-view-switch-btn"
                            :class="{ 'is-active': viewModeHasil === 'grid' }"
                            title="Tampilan Kartu"
                            @click="viewModeHasil = 'grid'"
                        >
                            <HcisIcon name="grid-fill" size="2xs" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="memuatPeserta && !pesertaHasil.length" class="pa-hasil-loading-box">
                <div class="pa-hasil-skeleton-grid">
                    <div v-for="n in 6" :key="`skel-has-${n}`" class="pa-hasil-skeleton-card">
                        <HcisSkeleton width="180px" height="1.1rem" rounded="sm" />
                        <HcisSkeleton width="100%" height="2.5rem" rounded="md" />
                        <HcisSkeleton width="90px" height="0.8rem" rounded="sm" />
                    </div>
                </div>
            </div>

            <!-- Error State -->
            <HcisResultState
                v-else-if="galatPeserta"
                status="danger"
                variant="flat"
                title="Gagal memuat hasil peserta"
                :description="galatPeserta"
                primary-text="Coba lagi"
                primary-icon="arrow-clockwise"
                @primary-click="muatPeserta"
            />

            <!-- Empty State -->
            <HcisEmptyState
                v-else-if="!pesertaHasil.length"
                :title="searchQueryHasil || filterDivisiHasil ? 'Tidak ada hasil yang cocok dengan saringan' : 'Belum ada hasil yang siap ditinjau'"
                :description="searchQueryHasil || filterDivisiHasil ? 'Coba ubah kata kunci atau atur ulang saringan divisi.' : 'Tab ini memuat peserta yang hasilnya sudah terhitung. Peserta yang tertahan dapat diselesaikan pada Tab Antrean Tindak Lanjut.'"
                icon="calculator"
            >
                <template v-if="searchQueryHasil || filterDivisiHasil || saringHasil !== 'SEMUA'" #actions>
                    <HcisButton
                        label="Atur Ulang Saringan"
                        variant="secondary"
                        size="xs"
                        leading-icon="arrow-counterclockwise"
                        @click="resetFilterHasil"
                    />
                </template>
            </HcisEmptyState>

            <!-- Loaded Content -->
            <div v-else class="pa-hasil-content-wrap">
                <!-- Mode 1: Table View -->
                <div v-if="viewModeHasil === 'table'" class="pa-hasil-table-container">
                    <div class="pa-table-count-strip">
                        <span>Menampilkan <strong>{{ formatAngka(paginatedPesertaHasil.length) }}</strong> dari total <strong>{{ formatAngka(totalHasilServer) }}</strong> peserta terhitung</span>
                    </div>

                    <table class="pa-hasil-main-table">
                        <thead>
                            <tr>
                                <th style="min-width: 230px;">Karyawan Dinilai</th>
                                <th style="min-width: 180px;">Divisi &amp; Jabatan</th>
                                <th style="min-width: 170px;">Komponen Nilai</th>
                                <th style="width: 150px;">Status Evaluasi</th>
                                <th style="width: 140px; text-align: right;">Aksi Tindak Lanjut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="p in paginatedPesertaHasil"
                                :key="p.Id_PA_Participant"
                                class="pa-hasil-tr--interactive"
                                tabindex="0"
                                :title="`Buka detail hasil & kalibrasi ${p.Nama_Karyawan_Saat_Cycle || p.Kode_Karyawan}`"
                                @click="bukaHasilPeserta({
                                    Id_PA_Participant: p.Id_PA_Participant,
                                    Id_PA_Result: p.Id_PA_Result,
                                    Nama_Karyawan_Yang_Dinilai: p.Nama_Karyawan_Saat_Cycle,
                                    Kode_Karyawan_Yang_Dinilai: p.Kode_Karyawan,
                                })"
                                @keydown.enter="bukaHasilPeserta({
                                    Id_PA_Participant: p.Id_PA_Participant,
                                    Id_PA_Result: p.Id_PA_Result,
                                    Nama_Karyawan_Saat_Cycle: p.Nama_Karyawan_Saat_Cycle,
                                    Kode_Karyawan_Yang_Dinilai: p.Kode_Karyawan,
                                })"
                            >
                                <!-- Person Column -->
                                <td>
                                    <div class="pa-table-person-cell">
                                        <HcisAvatar
                                            :name="p.Nama_Karyawan_Saat_Cycle"
                                            size="xs"
                                            shape="circle"
                                            variant="brand"
                                        />
                                        <div class="pa-table-person-info">
                                            <button
                                                type="button"
                                                class="pa-table-person-link"
                                                :title="`Buka verifikasi ${p.Nama_Karyawan_Saat_Cycle}`"
                                                @click.stop="bukaHasilPeserta({
                                                    Id_PA_Participant: p.Id_PA_Participant,
                                                    Id_PA_Result: p.Id_PA_Result,
                                                    Nama_Karyawan_Saat_Cycle: p.Nama_Karyawan_Saat_Cycle,
                                                    Kode_Karyawan_Yang_Dinilai: p.Kode_Karyawan,
                                                })"
                                            >
                                                {{ p.Nama_Karyawan_Saat_Cycle || p.Kode_Karyawan }}
                                            </button>
                                            <small>{{ p.Kode_Karyawan }} &bull; {{ p.Nama_Level_Saat_Cycle || `Level ${p.ID_Level_Saat_Cycle || '-'}` }}</small>
                                        </div>
                                    </div>
                                </td>

                                <!-- Division & Position -->
                                <td>
                                    <div class="pa-table-job-info">
                                        <strong class="pa-job-title">{{ p.Nama_Jabatan_Saat_Cycle || '—' }}</strong>
                                        <div class="pa-job-meta">
                                            <span>{{ p.Nama_Divisi_Saat_Cycle || '—' }}</span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Component Scores (KPI & Competency) -->
                                <td>
                                    <div class="pa-pillar-scores-cell">
                                        <div class="pa-score-row">
                                            <span class="pa-score-tag is-kpi">KPI</span>
                                            <strong class="pa-score-val">{{ p.Nilai_KPI != null ? formatNilai(p.Nilai_KPI) : '—' }}</strong>
                                            <small v-if="p.Bobot_KPI_Terpakai" class="pa-score-weight">({{ Number(p.Bobot_KPI_Terpakai) }}%)</small>
                                        </div>
                                        <div class="pa-score-row">
                                            <span class="pa-score-tag is-komp">Komp</span>
                                            <strong class="pa-score-val">{{ p.Nilai_Kompetensi != null ? formatNilai(p.Nilai_Kompetensi) : '—' }}</strong>
                                            <small v-if="p.Bobot_Kompetensi_Terpakai" class="pa-score-weight">({{ Number(p.Bobot_Kompetensi_Terpakai) }}%)</small>
                                        </div>
                                    </div>
                                </td>

                                <!-- Status Column -->
                                <td>
                                    <div class="pa-status-cell">
                                        <HcisStatusBadge
                                            v-if="p.Status_Hasil === 'TERBIT'"
                                            status="ACTIVE"
                                            label="Sudah Terbit"
                                            size="xs"
                                        />
                                        <HcisBadge
                                            v-else
                                            label="Siap Review"
                                            variant="warning"
                                            appearance="subtle"
                                            size="xs"
                                            dot
                                        />
                                        <small v-if="p.Published_At" class="pa-status-sub-date">
                                            Terbit: {{ formatDate(p.Published_At) }}
                                        </small>
                                        <small v-else-if="p.Calculated_At" class="pa-status-sub-date">
                                            Dihitung: {{ formatDate(p.Calculated_At) }}
                                        </small>
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td style="text-align: right;" @click.stop>
                                    <HcisButton
                                        label="Lihat detail"
                                        variant="secondary"
                                        appearance="subtle"
                                        size="xs"
                                        @click="bukaHasilPeserta({
                                            Id_PA_Participant: p.Id_PA_Participant,
                                            Id_PA_Result: p.Id_PA_Result,
                                            Nama_Karyawan_Saat_Cycle: p.Nama_Karyawan_Saat_Cycle,
                                            Kode_Karyawan: p.Kode_Karyawan,
                                        })"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mode 2: Grid Cards View -->
                <div v-else class="pa-hasil-cards-grid">
                    <HcisCard
                        v-for="p in paginatedPesertaHasil"
                        :key="`card-res-${p.Id_PA_Participant}`"
                        variant="glass"
                        :sentiment="p.Status_Hasil === 'TERBIT' ? 'success' : 'brand'"
                        size="sm"
                        glow
                        class="pa-hasil-hcis-card"
                    >
                        <template #header>
                            <div class="pa-hasil-card-header-inner">
                                <div class="pa-hasil-card-person-flex">
                                    <HcisAvatar
                                        :name="p.Nama_Karyawan_Saat_Cycle"
                                        size="xs"
                                        shape="circle"
                                        variant="brand"
                                    />
                                    <div class="pa-hasil-card-person-meta">
                                        <button
                                            type="button"
                                            class="pa-table-person-link"
                                            @click.stop="bukaHasilPeserta({
                                                Id_PA_Participant: p.Id_PA_Participant,
                                                Id_PA_Result: p.Id_PA_Result,
                                                Nama_Karyawan_Saat_Cycle: p.Nama_Karyawan_Saat_Cycle,
                                                Kode_Karyawan: p.Kode_Karyawan,
                                            })"
                                        >
                                            {{ p.Nama_Karyawan_Saat_Cycle }}
                                        </button>
                                        <span class="pa-hasil-card-sub">
                                            {{ p.Kode_Karyawan }} &bull; {{ p.Nama_Level_Saat_Cycle || `Level ${p.ID_Level_Saat_Cycle || '-'}` }}
                                        </span>
                                    </div>
                                </div>

                                <HcisStatusBadge
                                    v-if="p.Status_Hasil === 'TERBIT'"
                                    status="ACTIVE"
                                    label="Terbit"
                                    size="xs"
                                />
                                <HcisBadge
                                    v-else
                                    label="Siap Review"
                                    variant="warning"
                                    appearance="subtle"
                                    size="xs"
                                    dot
                                />
                            </div>
                        </template>

                        <div class="pa-hasil-card-body">
                            <div class="pa-mon-card-division-badge">
                                <HcisIcon name="building" size="2xs" />
                                <span>{{ p.Nama_Divisi_Saat_Cycle || '—' }}</span>
                            </div>

                            <!-- Scores Grid -->
                            <div class="pa-hasil-card-scores-strip">
                                <div class="pa-score-box">
                                    <span class="pa-score-box-label">Skor KPI</span>
                                    <strong class="pa-score-box-val">{{ p.Nilai_KPI != null ? formatNilai(p.Nilai_KPI) : '—' }}</strong>
                                </div>
                                <div class="pa-score-box">
                                    <span class="pa-score-box-label">Kompetensi</span>
                                    <strong class="pa-score-box-val">{{ p.Nilai_Kompetensi != null ? formatNilai(p.Nilai_Kompetensi) : '—' }}</strong>
                                </div>
                                <div class="pa-score-box is-final">
                                    <span class="pa-score-box-label">Skor Akhir</span>
                                    <strong class="pa-score-box-val text-brand">{{ p.Final_Score != null ? formatNilai(p.Final_Score) : '—' }}</strong>
                                </div>
                            </div>

                        </div>

                        <template #footer>
                            <div class="pa-hasil-card-footer">
                                <HcisButton
                                    label="Lihat detail"
                                    variant="secondary"
                                    appearance="subtle"
                                    size="xs"
                                    @click="bukaHasilPeserta({
                                        Id_PA_Participant: p.Id_PA_Participant,
                                        Id_PA_Result: p.Id_PA_Result,
                                        Nama_Karyawan_Saat_Cycle: p.Nama_Karyawan_Saat_Cycle,
                                        Kode_Karyawan: p.Kode_Karyawan,
                                    })"
                                />
                            </div>
                        </template>
                    </HcisCard>
                </div>

                <!-- Bottom Pagination Bar -->
                <div v-if="totalHasilServer > perHalamanHasil" class="pa-hasil-pagination-bar">
                    <span class="pa-pagination-info">
                        Menampilkan <strong>{{ (halamanHasil - 1) * perHalamanHasil + 1 }}</strong> -
                        <strong>{{ Math.min(halamanHasil * perHalamanHasil, totalHasilServer) }}</strong> dari
                        <strong>{{ formatAngka(totalHasilServer) }}</strong> peserta
                    </span>

                    <HcisPagination
                        :total="totalHasilServer"
                        :current-page="halamanHasil"
                        :page-size="perHalamanHasil"
                        layout="prev, pager, next"
                        size="xs"
                        background
                        @current-change="(p) => halamanHasil = p"
                    />
                </div>
            </div>
        </div>

        <!-- TAHAP 3 · KESIMPULAN KUALITATIF (tidak mengunci; isi awal kualitatif Balancing) -->
        <div v-if="tahapAktif === 'kualitatif' && selectedCycleId" class="pa-tahap-blok">
            <PaTahapKepala kode="kualitatif" :tahap="tahapAlur('kualitatif')" />
            <PaTahapKualitatif :cycle-id="Number(selectedCycleId)" @berubah="muatAlur" />
        </div>

        <!-- TAHAP 4 · KURVA NORMAL -->
        <div v-if="tahapAktif === 'kurva' && selectedCycleId" class="pa-tahap-blok">
            <PaTahapKepala kode="kurva" :tahap="tahapAlur('kurva')" />
            <PaTahapKurva :cycle-id="Number(selectedCycleId)" :metode="alurPenutupan?.siklus?.metode_grade || 'KURVA'" @berubah="muatAlur" />
        </div>

        <!-- TAHAP 5 · BALANCING -->
        <div v-if="tahapAktif === 'balancing' && selectedCycleId" class="pa-tahap-blok">
            <PaTahapKepala kode="balancing" :tahap="tahapAlur('balancing')" />
            <PaTahapBalancing :cycle-id="Number(selectedCycleId)" @berubah="muatAlur" />
        </div>

        <!-- TAHAP 6 · RILIS & TERBIT -->
        <div v-if="tahapAktif === 'terbit' && selectedCycleId" class="pa-tahap-blok">
            <PaTahapKepala kode="terbit" :tahap="tahapAlur('terbit')" />
            <PaTahapTerbit
                :cycle-id="Number(selectedCycleId)"
                :cacah="alurPenutupan?.cacah"
                :boleh-rilis="bolehOperasi('RILIS_LEADER')"
                :boleh-terbit="bolehOperasi('TERBITKAN')"
                @berubah="muatAlur"
                @buka-penerbitan="bukaDialogPenerbitan"
            />
        </div>

        <!-- Kecualikan peserta tanpa angka sesudah pengisian ditutup -->
        <HcisDialog
            v-model="kecualikan.buka"
            title="Kecualikan dari siklus"
            icon="person-dash"
            size="md"
            sticky-footer
        >
            <div v-if="kecualikan.baris" class="pa-kecualikan-body">
                <p class="pa-kecualikan-kalimat">
                    <strong>{{ kecualikan.baris.Nama_Karyawan_Saat_Cycle }}</strong> ({{ kecualikan.baris.Kode_Karyawan }})
                    · hasil {{ kecualikan.baris.Status_Hasil === 'TERTAHAN' ? 'tertahan' : 'belum dihitung' }}
                </p>
                <p v-if="kecualikan.baris.Alasan_Tertahan" class="pa-kecualikan-sebab">{{ kecualikan.baris.Alasan_Tertahan }}</p>
                <p class="pa-kecualikan-sebab">
                    Orang ini tidak lagi dinilai di siklus ini dan tidak menahan penutupan siklus.
                    Jawaban penilainya tetap tersimpan.
                </p>
                <HcisTextarea
                    v-model="kecualikan.alasan"
                    label="Alasan"
                    :rows="3"
                    :maxlength="500"
                    required
                    placeholder="Contoh: tidak punya akun KPI pada periode siklus ini"
                    :disabled="kecualikan.mengirim"
                    :error="kecualikan.galat"
                />
            </div>
            <template #footer>
                <HcisButton variant="secondary" label="Batal" size="sm" :disabled="kecualikan.mengirim" @click="kecualikan.buka = false" />
                <HcisButton
                    variant="primary"
                    label="Kecualikan"
                    size="sm"
                    :loading="kecualikan.mengirim"
                    :disabled="!kecualikan.alasan.trim() || kecualikan.mengirim"
                    @click="kirimKecualikan"
                />
            </template>
        </HcisDialog>

        <!--
            Dialog: Lepas Paksa Pengiriman Penilai

            Pintu darurat aturan satu-pintu. Penilai yang lembarnya sudah terisi tetapi
            tidak pernah menekan kirim membekukan hasil SEMUA peserta yang ia nilai - pada
            penilai dengan dua puluh empat lembar, dua puluh tiga orang lain yang tidak
            tahu-menahu. Wajib bersebab: yang membaca kelak perlu tahu kenapa penilaian ini
            ikut dihitung padahal pengirimnya tidak pernah menyatakan selesai.
        -->
        <HcisDialog
            v-model="lepasPaksa.terbuka"
            title="Lepas Paksa Pengiriman Penilai"
            kicker="PENGIRIMAN SATU PINTU"
            :description="`Penilai: ${lepasPaksa.penilai || '—'}`"
            icon="unlock-fill"
            sentiment="warning"
            variant="glass"
            size="sm"
            sticky-footer
        >
            <div class="pa-cetak-ulang">
                <div class="pa-cetak-ulang__banner">
                    <HcisIcon name="exclamation-triangle-fill" size="xs" color="warning" />
                    <span>
                        SELURUH penilaian yang sudah terisi milik penilai ini akan dianggap
                        terkirim dan langsung ikut dihitung — termasuk untuk peserta lain,
                        bukan hanya baris ini.
                    </span>
                </div>

                <HcisTextarea
                    id="pa-lepas-paksa-alasan"
                    v-model="lepasPaksa.alasan"
                    label="Alasan Pelepasan"
                    placeholder="Contoh: Penilai sudah mengisi seluruh lembar tetapi cuti panjang dan tidak bisa dihubungi."
                    :rows="3"
                    :maxlength="500"
                    required
                    :disabled="lepasPaksa.mengirim"
                    :error="lepasPaksa.galat"
                />
            </div>

            <template #footer>
                <HcisButton
                    variant="secondary"
                    label="Batal"
                    size="sm"
                    :disabled="lepasPaksa.mengirim"
                    @click="lepasPaksa.terbuka = false"
                />
                <HcisButton
                    variant="warning"
                    label="Lepas dan hitung"
                    size="sm"
                    leading-icon="unlock-fill"
                    :loading="lepasPaksa.mengirim"
                    @click="kirimLepasPaksa"
                />
            </template>
        </HcisDialog>

        <!--
         | Dialog: AKSES ULANG — satu pintu untuk dua jalan, dipilih menurut keadaan penilai:
         |   belum mulai     kode/tautan baru pada jadwal yang sama (tanpa pilihan)
         |   sedang mengisi  Lanjutkan isian (kode baru, jawaban tetap — baku) ATAU Ulangi dari awal
         |   sudah selesai   hanya Ulangi dari awal: jadwal dihapus di HC Learn lalu dijadwalkan baru
         | Pilihan yang menghapus jawaban tidak pernah terpilih otomatis dan menuntut centang paham.
         -->
        <HcisDialog
            v-model="aksesUlang.terbuka"
            title="Akses Ulang Penilaian"
            kicker="AKSES EVALUASI RATER"
            :description="`Rater: ${aksesUlang.penilai || '—'} · Untuk: ${aksesUlang.dinilai || '—'}`"
            icon="arrow-repeat"
            :sentiment="aksesUlang.pilihan === 'ULANG' ? 'danger' : 'warning'"
            size="sm"
            sticky-footer
            data-uji="dialog-akses-ulang"
        >
            <div class="pa-cetak-ulang">
                <div v-if="aksesUlang.kondisi === 'SEDANG'" class="pa-akses-pilihan" role="radiogroup" aria-label="Cara akses ulang">
                    <button
                        type="button"
                        role="radio"
                        :aria-checked="aksesUlang.pilihan === 'LANJUT'"
                        :class="{ 'is-dipilih': aksesUlang.pilihan === 'LANJUT' }"
                        data-uji="akses-ulang-lanjut"
                        @click="aksesUlang.pilihan = 'LANJUT'"
                    >
                        <strong>Lanjutkan isian</strong>
                        <span>Kode baru, jawaban yang sudah diisi tetap ada.</span>
                    </button>
                    <button
                        type="button"
                        role="radio"
                        :aria-checked="aksesUlang.pilihan === 'ULANG'"
                        :class="{ 'is-dipilih': aksesUlang.pilihan === 'ULANG' }"
                        data-uji="akses-ulang-ulang"
                        @click="aksesUlang.pilihan = 'ULANG'"
                    >
                        <strong>Ulangi dari awal</strong>
                        <span>Jadwal dan jawaban lama dihapus.</span>
                    </button>
                </div>

                <HcisAlert
                    v-if="aksesUlang.pilihan === 'ULANG'"
                    variant="danger"
                    appearance="subtle"
                    size="sm"
                    title="Jawaban yang sudah diisi akan DIHAPUS permanen"
                    :description="`${aksesUlang.penilai || 'Penilai'} sudah ${aksesUlang.kondisi === 'SELESAI' ? 'menyelesaikan penilaiannya' : 'mulai mengisi'}. Jadwal lama dihapus di HC Learn beserta jawabannya, lalu dijadwalkan baru — penilai mengisi dari awal.`"
                    icon="exclamation-octagon-fill"
                    data-uji="akses-ulang-peringatan"
                />
                <HcisAlert
                    v-else
                    variant="warning"
                    appearance="subtle"
                    size="sm"
                    :description="aksesUlang.kondisi === 'SEDANG'
                        ? 'Kode dan tautan baru diterbitkan; yang lama berhenti berlaku. Penilai melanjutkan isiannya.'
                        : 'Kode dan tautan baru diterbitkan; yang lama berhenti berlaku.'"
                    icon="exclamation-triangle-fill"
                />

                <div v-if="aksesUlang.pilihan === 'LANJUT'" class="pa-cetak-ulang__preset-chips">
                    <span class="pa-preset-label">Alasan Cepat:</span>
                    <div class="pa-preset-list">
                        <button type="button" class="pa-preset-chip" @click="aksesUlang.alasan = 'Tautan email rater terhapus atau tidak masuk kotak masuk.'">
                            Email Terhapus
                        </button>
                        <button type="button" class="pa-preset-chip" @click="aksesUlang.alasan = 'Rater berganti perangkat dan membutuhkan akses evaluasi baru.'">
                            Ganti Perangkat
                        </button>
                        <button type="button" class="pa-preset-chip" @click="aksesUlang.alasan = 'Paket soal diperbarui karena penyesuaian butir kompetensi.'">
                            Paket Diperbarui
                        </button>
                    </div>
                </div>

                <HcisTextarea
                    id="pa-akses-ulang-alasan"
                    v-model="aksesUlang.alasan"
                    label="Alasan"
                    :placeholder="aksesUlang.pilihan === 'ULANG'
                        ? 'Contoh: Penilai meninggalkan tes setengah jalan dan harus mengulang dari awal'
                        : 'Contoh: Tautan email rater terhapus atau rater berganti perangkat'"
                    :rows="3"
                    :maxlength="500"
                    required
                    :disabled="aksesUlang.mengirim"
                    :error="aksesUlang.galat"
                />
                <HcisCheckbox
                    v-if="aksesUlang.pilihan === 'ULANG'"
                    v-model="aksesUlang.paham"
                    size="sm"
                    label="Saya mengerti jawaban penilai ini akan dihapus dan harus diisi ulang."
                    data-uji="akses-ulang-paham"
                />
            </div>

            <template #footer>
                <HcisButton
                    variant="secondary"
                    label="Batal"
                    size="sm"
                    :disabled="aksesUlang.mengirim"
                    @click="aksesUlang.terbuka = false"
                />
                <HcisButton
                    :variant="aksesUlang.pilihan === 'ULANG' ? 'danger' : 'primary'"
                    :label="aksesUlang.pilihan === 'ULANG' ? 'Hapus Jawaban & Ulangi' : 'Kirim Akses Baru'"
                    size="sm"
                    :leading-icon="aksesUlang.pilihan === 'ULANG' ? 'calendar2-x' : 'send-fill'"
                    :loading="aksesUlang.mengirim"
                    :disabled="aksesUlang.pilihan === 'ULANG' && !aksesUlang.paham"
                    data-uji="akses-ulang-kirim"
                    @click="kirimAksesUlang"
                />
            </template>
        </HcisDialog>

        <!-- LIFECYCLE FILLING DIALOG (OPEN / CLOSE) -->
        <PaCycleFillingDialog
            v-model="dialogPengisian"
            :mode="modePengisian"
            :cycle="activeCycle"
            :acting="actionLoading"
            :action-error="galatPengisian"
            :action-error-status="galatPengisianStatus"
            :penjadwalan="penjadwalanPengisian"
            :ringkas-pengisian="summaryData"
            :menjadwalkan="sedangMenjadwalkan"
            @confirm="eksekusiPengisian"
            @jadwalkan="jadwalkanPenilaian"
            @update:penjadwalan="penjadwalanPengisian = $event"
            @jadwal-selesai="onJadwalSelesai"
            @tarik-hasil="tarikHasilPenjadwalan"
        />

        <!-- PANEL HASIL SATU PESERTA -->
        <PaResultDialog
            v-if="pesertaTerpilih"
            v-model="dialogHasilTerbuka"
            :participant="pesertaTerpilih"
            :result="hasilPeserta.detail.value"
            :loading="hasilPeserta.loading.value"
            :error="hasilPeserta.error.value"
            :error-status="hasilPeserta.errorStatus.value"
            :error-code="hasilPeserta.errorCode.value"
            :request-id="hasilPeserta.requestId.value"
            :calculation="hasilPeserta.hasilHitung.value"
            :recalculating="hasilPeserta.recalculating.value"
            :acting="hasilPeserta.acting.value"
            :action-error="hasilPeserta.actionError.value"
            :action-error-status="hasilPeserta.actionErrorStatus.value"
            :action-error-code="hasilPeserta.actionErrorCode.value"
            :action-request-id="hasilPeserta.actionRequestId.value"
            :action-success="hasilPeserta.actionSuccess.value"
            :can-calculate="bolehOperasi('HITUNG_HASIL')"
            :can-calibrate="bolehOperasi('KALIBRASI')"
            :can-publish="bolehOperasi('TERBITKAN')"
            :can-assign="bolehOperasi('BATALKAN_PENUGASAN')"
            :mode-verifikasi="tahapAktif === 'verifikasi'"
            @retry="muatDetailHasil"
            @calculate="hitungPeserta"
            @calibrate="kalibrasiPeserta"
            @publish="terbitkanSatuPeserta"
            @release-leader="rilisSatuKeLeader"
            @revoke-leader="cabutRilisSatuLeader"
            @cancel-assignment="batalkanPenugasanPeserta"
        />

        <!-- PUBLICATION DIALOG -->
        <PaPublicationDialog
            v-if="activeCycle"
            v-model="dialogPenerbitanTerbuka"
            :cycle="activeCycle"
            :summary="ringkasanPenerbitan"
            :loading="penerbitan.loading.value"
            :error="penerbitan.error.value"
            :error-status="penerbitan.errorStatus.value"
            :error-code="penerbitan.errorCode.value"
            :request-id="penerbitan.requestId.value"
            :acting="penerbitan.acting.value"
            :action-error="penerbitan.actionError.value"
            :action-error-status="penerbitan.actionErrorStatus.value"
            :action-error-code="penerbitan.actionErrorCode.value"
            :action-success="penerbitan.actionSuccess.value"
            :action-name="penerbitan.actionName.value"
            :can-publish="bolehOperasi('TERBITKAN')"
            @retry="muatRingkasanPenerbitan"
            @publish="terbitkanSiklus"
            @close-publication="tutupPenerbitanSiklus"
        />

        <!-- DIALOG PERHITUNGAN MASSAL -->
        <PaHitungSiklusDialog
            v-if="activeCycle"
            v-model="dialogHitungTerbuka"
            :cycle="activeCycle"
            :preview="pratinjauHitung"
            :loading="pratinjauHitungLoading"
            :error="pratinjauHitungError"
            :acting="menghitungSiklus"
            @retry="muatPratinjauHitung"
            @jalankan="hitungSiklus"
        />

        <!-- ================================================================= -->
        <!-- DIALOG: Pemilih Siklus & Periode Penilaian (Rich Period Selector) -->
        <!-- ================================================================= -->
        <HcisDialog
            v-model="bukaDialogPeriode"
            title="Pilih Siklus &amp; Periode Penilaian"
            subtitle="Pilih assessment cycle untuk memantau progres pengisian rater dan verifikasi hasil."
            icon="calendar2-range-fill"
            sentiment="purple"
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
                            :class="{ 'pa-filter-tab--active': tabStatusSiklus === 'ACTIVE' }"
                            @click="tabStatusSiklus = 'ACTIVE'"
                        >
                            Sedang Berjalan
                            <span class="pa-filter-tab__count">{{ cacahSiklusActive }}</span>
                        </button>
                        <button
                            type="button"
                            class="pa-filter-tab"
                            :class="{ 'pa-filter-tab--active': tabStatusSiklus === 'READY' }"
                            @click="tabStatusSiklus = 'READY'"
                        >
                            Siap Hitung / Final
                            <span class="pa-filter-tab__count">{{ cacahSiklusReady }}</span>
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
    </main>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import {
    HcisAlert,
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisCheckbox,
    HcisCard,
    HcisChart,
    HcisContextBanner,
    HcisDialog,
    HcisEmptyState,
    HcisFilterBar,
    HcisHeader,
    HcisIcon,
    HcisPagination,
    HcisProgress,
    HcisResultState,
    HcisSkeleton,
    HcisStatCard,
    HcisStatGroup,
    HcisStatusBadge,
    HcisTable,
    HcisTabs,
    HcisTextarea,
    hcisMessage,
} from '@/components/ui';
import PaHitungSiklusDialog from './components/PaHitungSiklusDialog.vue';
import PaPublicationDialog from './components/PaPublicationDialog.vue';
import PaResultDialog from './components/PaResultDialog.vue';
import PaCycleFillingDialog from './components/PaCycleFillingDialog.vue';
import PaPemantauanPerPenilai from './components/PaPemantauanPerPenilai.vue';
import PaPenutupanStepper from './components/penutupan/PaPenutupanStepper.vue';
import PaTahapBalancing from './components/penutupan/PaTahapBalancing.vue';
import PaTahapKepala from './components/penutupan/PaTahapKepala.vue';
import PaTahapKurva from './components/penutupan/PaTahapKurva.vue';
import PaTahapTerbit from './components/penutupan/PaTahapTerbit.vue';
import PaTahapKualitatif from './components/penutupan/PaTahapKualitatif.vue';
import { LABEL_KEADAAN, TAHAP_PENUTUPAN, tahapDariUrl } from './components/penutupan/tahapPenutupan.js';
import { adaYangDilewati, rilisLeaderPesertaBerkonfirmasi, terbitkanPesertaBerkonfirmasi, usePaPublication } from './composables/usePaPublication.js';
import { usePaResult } from './composables/usePaResult.js';
import paApi from '../api/paApi.js';
import { cycleDariUrl } from '../utils/cycleDariUrl.js';

defineOptions({ name: 'PerformanceAppraisalPemantauanPage' });

const props = defineProps({
    /* Hak per fase dari server (`PaCycleAdminController::pemantauan`): penyiapan, pemantauan,
       hasil. Halaman ini hanya menuntut izin pemantauan, tetapi memuat tombol milik fase lain. */
    akses: { type: Object, default: null },
});

/* Fase yang TIDAK diketahui (prop tidak dikirim) dianggap boleh: server tetap gerbangnya,
   dan menyembunyikan tombol karena data yang tak ada lebih menyesatkan daripada 403. */
function punyaAkses(fase) {
    return props.akses?.[fase] !== false;
}

const isMobile = ref(typeof window !== 'undefined' ? window.innerWidth <= 768 : false);
const cekUkuranLayar = () => {
    if (typeof window === 'undefined') return;
    isMobile.value = window.innerWidth <= 768;
};

const viewMode = ref(typeof window !== 'undefined' && window.innerWidth < 768 ? 'grid' : 'table');
const analyticsTab = ref('kategori');
const loadingSiklus = ref(false);
const loadingMonitoring = ref(false);
const hasLoadedInitial = ref(false);
const actionLoading = ref(false);

/* Lifecycle Modal State */
const dialogPengisian = ref(false);
const modePengisian = ref('open'); // 'open' | 'close'
const galatPengisian = ref('');
const galatPengisianStatus = ref(0);
const penjadwalanPengisian = ref(null);
const sedangMenjadwalkan = ref(false);

/* Cetak ulang akses rater */
const lepasPaksa = reactive({
    terbuka: false,
    mengirim: false,
    kodePerusahaan: '',
    kodeKaryawan: '',
    penilai: '',
    alasan: '',
    galat: '',
});

/* Tab Pengisian: per karyawan dinilai (baku) atau per penilai. */
const tampilanPengisian = ref('KARYAWAN');
const perPenilaiRef = ref(null);

/* Akses ulang: LANJUT = cetak ulang (kode baru, jawaban tetap); ULANG = jadwalkan ulang. */
const aksesUlang = reactive({
    terbuka: false,
    mengirim: false,
    assignmentId: null,
    penilai: '',
    dinilai: '',
    kondisi: 'BELUM',
    pilihan: 'LANJUT',
    alasan: '',
    paham: false,
    galat: '',
});

const errorMonitoring = ref('');
const errorStatus = ref(0);

const siklusList = ref([]);
const selectedCycleId = ref('');

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
    onCycleChange();
}
const summaryData = ref({});
const monitoringRows = ref([]);
const categoryBreakdown = ref([]);
const heatmapData = ref(null);
const rawPilihanSaringan = ref(null);
const overdueRows = ref([]);
const overdueTotal = ref(0);

const searchQuery = ref('');
const activeStatusFilter = ref('ALL');
const selectedFilterValues = reactive({
    kategori: '',
    id_divisi: '',
    id_level: '',
});

const currentPage = ref(1);
const totalBaris = ref(0);
// Pagination per KARYAWAN (blok `peserta` di respons monitoring); `totalBaris` tetap jumlah penugasan yang cocok.
const totalPeserta = ref(0);
const pesertaServer = ref([]);
const totalHalamanServer = ref(0);
const perPage = 10;

/* Keranjang keputusan admin */
const keranjang = ref(null);
const keranjangAktif = ref('');
const isiKeranjang = ref(null);
const memuatKeranjang = ref(false);
const menyapu = ref(false);

const activeCycle = computed(() => {
    return siklusList.value.find((c) => Number(c.Id_PA_Cycle) === Number(selectedCycleId.value)) || null;
});

const cycleQuery = computed(() => {
    return selectedCycleId.value ? `?cycle=${selectedCycleId.value}` : '';
});

const canOpenFilling = computed(() => {
    const status = activeCycle.value?.Status_Cycle;
    return status === 'LOCKED' || status === 'CLOSED';
});

/* Siklus LAIN yang sedang OPEN. Server menolak membuka pengisian selama ada satu
   (`pastikanTidakAdaSiklusLainTerbuka`), dan daftar siklusnya sudah ada di layar — jadi
   tombolnya dipadamkan beserta sebabnya, bukan dibiarkan ditekan untuk ditolak. */
const siklusLainTerbuka = computed(() => siklusList.value.find(
    (c) => c.Status_Cycle === 'OPEN' && Number(c.Id_PA_Cycle) !== Number(selectedCycleId.value),
) || null);

const canCloseFilling = computed(() => {
    return activeCycle.value?.Status_Cycle === 'OPEN';
});

const selesaiCount = computed(() => monitoringRows.value.filter((r) => isRowSelesai(r)).length);
const belumSelesaiCount = computed(() => monitoringRows.value.filter((r) => !isRowSelesai(r)).length);

const persentaseSelesai = computed(() => {
    const total = monitoringRows.value.length;
    if (!total) return 0;
    return Math.round((selesaiCount.value / total) * 100);
});

/* Normalized Summary Data */
const summaryTotal = computed(() => {
    return summaryData.value.total ?? totalBaris.value ?? monitoringRows.value.length;
});

const summarySelesai = computed(() => {
    return summaryData.value.selesai ?? selesaiCount.value;
});

/* SEMUA yang belum SELESAI — belum mulai, berjalan, terisi belum dikirim, dan menunggu
   verifikasi HC Learn. Dulu hanya dua yang pertama, sementara "% sisa" di kartu yang sama
   dihitung dari 100 − persen selesai: angka dan persennya tidak pernah cocok. */
const summaryBelumSelesai = computed(() => {
    if (summaryData.value.total !== undefined && summaryData.value.selesai !== undefined) {
        return Math.max(0, Number(summaryData.value.total || 0) - Number(summaryData.value.selesai || 0));
    }
    return belumSelesaiCount.value;
});

/* Orang yang dinilai se-siklus, dari server. `groupedParticipants` hanya halaman terbuka
   (10 orang), sehingga siklus 190 orang terbaca "10 Karyawan Dinilai". */
const summaryKaryawanDinilai = computed(() => summaryData.value.karyawan_dinilai ?? totalPeserta.value);

const summaryTerlambat = computed(() => {
    return summaryData.value.terlambat ?? 0;
});

const summaryPersen = computed(() => {
    if (summaryData.value.persen !== undefined) {
        return summaryData.value.persen;
    }
    return persentaseSelesai.value;
});

const cockpitRadialChartOptions = computed(() => {
    const val = Math.min(100, Math.max(0, summaryPersen.value || 0));
    const isSuccess = val >= 100;
    const isWarning = val < 40;
    const primaryColor = isSuccess ? '#059669' : isWarning ? '#d97706' : '#4f46e5';
    const secondaryColor = isSuccess ? '#34d399' : isWarning ? '#fbbf24' : '#818cf8';

    return {
        animationDuration: 600,
        tooltip: {
            trigger: 'item',
            backgroundColor: 'rgba(15, 23, 42, 0.95)',
            borderColor: '#334155',
            borderWidth: 1,
            padding: [8, 12],
            textStyle: { color: '#ffffff', fontSize: 12 },
            formatter: () => {
                return `<div style="font-weight: 750; margin-bottom: 4px; color: #f8fafc;">Progres Siklus</div>
                <div style="font-size: 11.5px; display: flex; justify-content: space-between; gap: 14px;">
                    <span>Capaian:</span>
                    <strong style="color: #ffffff; font-family: monospace;">${val}%</strong>
                </div>
                <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">
                    ${formatAngka(summarySelesai.value)} dari ${formatAngka(summaryTotal.value)} Selesai
                </div>`;
            },
        },
        series: [
            {
                type: 'gauge',
                startAngle: 90,
                endAngle: -270,
                min: 0,
                max: 100,
                radius: '100%',
                center: ['50%', '50%'],
                pointer: { show: false },
                progress: {
                    show: true,
                    overlap: false,
                    roundCap: true,
                    clip: false,
                    itemStyle: {
                        color: {
                            type: 'linear',
                            x: 0,
                            y: 0,
                            x2: 1,
                            y2: 1,
                            colorStops: [
                                { offset: 0, color: secondaryColor },
                                { offset: 1, color: primaryColor },
                            ],
                        },
                        shadowColor: `${primaryColor}40`,
                        shadowBlur: 10,
                    },
                },
                axisLine: {
                    lineStyle: {
                        width: 12,
                        color: [[1, 'rgba(241, 245, 249, 0.95)']],
                    },
                },
                splitLine: { show: false },
                axisTick: { show: false },
                axisLabel: { show: false },
                data: [
                    {
                        value: val,
                        name: 'TERSUBMIT',
                        title: {
                            offsetCenter: ['0%', '30%'],
                            fontSize: 10,
                            fontWeight: 800,
                            color: '#64748b',
                            fontFamily: 'Inter, system-ui, -apple-system, sans-serif',
                        },
                        detail: {
                            valueAnimation: true,
                            offsetCenter: ['0%', '-6%'],
                            fontSize: 24,
                            fontWeight: 850,
                            fontFamily: 'Inter, system-ui, -apple-system, sans-serif',
                            formatter: '{value}%',
                            color: '#0f172a',
                        },
                    },
                ],
            },
        ],
    };
});

/* Window Countdown Info */
const sisaHariInfo = computed(() => {
    if (!activeCycle.value?.Pengisian_Sampai) return null;
    try {
        const target = new Date(activeCycle.value.Pengisian_Sampai);
        const today = new Date();
        target.setHours(23, 59, 59, 999);
        const diffTime = target.getTime() - today.getTime();
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        if (diffDays < 0) {
            return {
                text: 'Jendela pengisian telah berakhir',
                shortText: 'Selesai',
                variant: 'danger',
                icon: 'lock-fill',
            };
        }
        if (diffDays === 0) {
            return {
                text: 'Hari terakhir pengisian',
                shortText: 'Hari ini',
                variant: 'warning',
                icon: 'hourglass-bottom',
            };
        }
        if (diffDays <= 3) {
            return {
                text: `${diffDays} hari tersisa`,
                shortText: `${diffDays} hari`,
                variant: 'warning',
                icon: 'hourglass-split',
            };
        }
        return {
            text: `${diffDays} hari tersisa`,
            shortText: `${diffDays} hari`,
            variant: 'brand',
            icon: 'calendar-check',
        };
    } catch {
        return null;
    }
});


/* Division List for Heatmap tab */
const divisionList = computed(() => {
    if (heatmapData.value?.baris && Array.isArray(heatmapData.value.baris)) {
        return heatmapData.value.baris.map((b) => {
            const total = Number(b.total || 0);
            const selesai = Number(b.sel?.[heatmapData.value.status?.indexOf('SELESAI')] || 0);
            const terlambat = Number(b.terlambat || 0);
            return {
                // `null` untuk "(Tanpa divisi)": barisnya tidak bisa disaring, dan kliknya tidak menebak.
                id: b.id_divisi ?? null,
                nama: b.divisi || '(Tanpa divisi)',
                total,
                selesai,
                terlambat,
                persen: total > 0 ? Math.floor((selesai / total) * 100) : 0,
            };
        });
    }

    if (rawPilihanSaringan.value?.divisi) {
        return rawPilihanSaringan.value.divisi.map((d) => ({
            id: d.id,
            nama: d.nama,
            total: 0,
            selesai: 0,
            terlambat: 0,
            persen: 0,
        }));
    }

    return [];
});

/* Chart Computations & Formatters for Tab 1 */
const categoryChartCategories = computed(() => {
    return categoryBreakdown.value.map((c) => c.nama || c.kode);
});

const categoryChartSeries = computed(() => [
    {
        name: 'Progres Selesai',
        data: categoryBreakdown.value.map((c) => c.persen),
    },
]);

const categoryChartHeight = computed(() => {
    return Math.max(180, categoryBreakdown.value.length * 46 + 24);
});

const divisionChartCategories = computed(() => {
    return divisionList.value.map((d) => d.nama || d.divisi);
});

const divisionChartSeries = computed(() => [
    {
        name: 'Progres Selesai',
        data: divisionList.value.map((d) => d.persen),
    },
]);

const divisionChartHeight = computed(() => {
    return Math.max(220, Math.min(500, divisionList.value.length * 36 + 32));
});

const categoryInsight = computed(() => {
    if (!categoryBreakdown.value.length) return '';
    const sorted = [...categoryBreakdown.value].sort((a, b) => b.persen - a.persen);
    const highest = sorted[0];
    const lowest = sorted[sorted.length - 1];
    if (highest.persen === 100 && lowest.persen === 100) {
        return 'Seluruh kategori penilai telah menyelesaikan 100% penugasan kuesioner.';
    }
    return `Kategori ${highest.nama || highest.kode} mencatat progres penyelesaian tertinggi (${highest.persen}%). Perhatian tindak lanjut diprioritaskan pada ${lowest.nama || lowest.kode} (${lowest.persen}% selesai).`;
});

const divisionInsight = computed(() => {
    if (!divisionList.value.length) return '';
    const withData = divisionList.value.filter((d) => d.total > 0);
    if (!withData.length) return '';
    const sorted = [...withData].sort((a, b) => b.persen - a.persen);
    const highest = sorted[0];
    const lowest = sorted[sorted.length - 1];
    if (highest.persen === 100 && lowest.persen === 100) {
        return 'Seluruh unit divisi kerja telah menuntaskan evaluasi kinerja 100%.';
    }
    return `Divisi ${highest.nama} memimpin tingkat penyelesaian tertinggi (${highest.persen}%). Unit ${lowest.nama} memiliki tingkat pengisian terendah (${lowest.persen}%).`;
});

const activeInsight = computed(() => {
    return analyticsTab.value === 'kategori' ? categoryInsight.value : divisionInsight.value;
});

function formatCategoryTooltip(params) {
    if (!Array.isArray(params) || !params.length) return '';
    const p = params[0];
    const cat = categoryBreakdown.value[p.dataIndex];
    if (!cat) return '';
    const selesai = formatAngka(cat.selesai);
    const total = formatAngka(cat.total);
    const belum = formatAngka(Math.max(0, cat.total - cat.selesai));
    return `
        <div style="font-weight: 750; margin-bottom: 5px; font-size: 12.5px; color: #0f172a;">${cat.nama || cat.kode}</div>
        <div style="display: flex; justify-content: space-between; gap: 16px; margin-bottom: 3px; font-size: 11.5px;">
            <span style="color: #64748b;">Progres Selesai:</span>
            <strong style="color: #059669;">${cat.persen}% (${selesai}/${total})</strong>
        </div>
        <div style="display: flex; justify-content: space-between; gap: 16px; font-size: 11.5px;">
            <span style="color: #64748b;">Belum Selesai:</span>
            <strong style="color: #d97706;">${belum} penugasan</strong>
        </div>
        <div style="margin-top: 6px; font-size: 10.5px; color: #4f46e5; border-top: 1px dashed rgba(226, 232, 240, 0.9); padding-top: 4px;">
            Saring kategori ini di Monitoring Rater &rarr;
        </div>
    `;
}

function formatDivisionTooltip(params) {
    if (!Array.isArray(params) || !params.length) return '';
    const p = params[0];
    const div = divisionList.value[p.dataIndex];
    if (!div) return '';
    const selesai = formatAngka(div.selesai);
    const total = formatAngka(div.total);
    const belum = formatAngka(Math.max(0, div.total - div.selesai));
    const overdue = div.terlambat > 0 ? `<div style="display: flex; justify-content: space-between; gap: 16px; font-size: 11.5px; color: #dc2626; margin-top: 2px;"><span>Lewat Tenggat:</span><strong>${div.terlambat} penugasan</strong></div>` : '';
    return `
        <div style="font-weight: 750; margin-bottom: 5px; font-size: 12.5px; color: #0f172a;">${div.nama || div.divisi}</div>
        <div style="display: flex; justify-content: space-between; gap: 16px; margin-bottom: 3px; font-size: 11.5px;">
            <span style="color: #64748b;">Progres Selesai:</span>
            <strong style="color: #059669;">${div.persen}% (${selesai}/${total})</strong>
        </div>
        <div style="display: flex; justify-content: space-between; gap: 16px; font-size: 11.5px;">
            <span style="color: #64748b;">Belum Selesai:</span>
            <strong style="color: #d97706;">${belum} penugasan</strong>
        </div>
        ${overdue}
        <div style="margin-top: 6px; font-size: 10.5px; color: #4f46e5; border-top: 1px dashed rgba(226, 232, 240, 0.9); padding-top: 4px;">
            ${div.id === null || div.id === undefined ? 'Tanpa divisi: tidak bisa disaring per divisi' : 'Saring divisi ini di Monitoring Rater &rarr;'}
        </div>
    `;
}

function onCategoryChartClick(params) {
    if (params && params.dataIndex !== undefined) {
        const cat = categoryBreakdown.value[params.dataIndex];
        if (cat) {
            pilihKategoriDanKePengisian(cat);
        }
    }
}

function onDivisionChartClick(params) {
    if (params && params.dataIndex !== undefined) {
        const div = divisionList.value[params.dataIndex];
        if (!div) return;
        /* Peserta tanpa divisi tidak punya id untuk disaring. Dulu kliknya diam-diam
           MENGOSONGKAN saringan divisi dan menampilkan semua orang seolah tersaring. */
        if (div.id === null || div.id === undefined) {
            hcisMessage.info('Peserta tanpa divisi tidak bisa disaring per divisi. Cari dengan nama atau NIK di Monitoring Rater.');
            return;
        }
        pilihDivisiDanKePengisian(div);
    }
}

function pilihKategoriDanKePengisian(cat) {
    let catVal = cat?.id;
    if (!catVal && rawPilihanSaringan.value?.kategori_penilai) {
        const found = rawPilihanSaringan.value.kategori_penilai.find(
            (c) => c.kode === cat.kode || c.nama === cat.nama || String(c.id) === String(cat.id),
        );
        if (found) catVal = found.id;
    }
    selectedFilterValues.kategori = catVal ? String(catVal) : String(cat?.id || cat?.kode || '');
    pilihTab('PENGISIAN');
    currentPage.value = 1;
    muatDataMonitoring();
}

function pilihDivisiDanKePengisian(div) {
    let divVal = div?.id;
    if (!divVal && rawPilihanSaringan.value?.divisi) {
        const found = rawPilihanSaringan.value.divisi.find(
            (d) => d.nama === div.nama || d.nama === div.divisi || String(d.id) === String(div.id),
        );
        if (found) divVal = found.id;
    }
    selectedFilterValues.id_divisi = divVal ? String(divVal) : '';
    pilihTab('PENGISIAN');
    currentPage.value = 1;
    muatDataMonitoring();
}

const statusQuickChips = computed(() => {
    const chips = [
        { label: 'Semua Penugasan', value: 'ALL', badge: summaryTotal.value ?? 0 },
        { label: 'Belum Mengisi', value: 'BELUM_MULAI', badge: summaryData.value.belum_mulai ?? 0, color: 'warning' },
        { label: 'Diisi Sebagian', value: 'BERJALAN', badge: summaryData.value.berjalan ?? 0, color: 'purple' },
        { label: 'Terisi, Belum Dikirim', value: 'TERKUMPUL', badge: summaryData.value.terkumpul ?? 0 },
        // Sudah dikirim, menunggu pengesah HC Learn — tanpa chip ini jumlah chip tak pernah sama dengan total.
        { label: 'Menunggu Verifikasi HC Learn', value: 'MENUNGGU_VERIFIKASI', badge: summaryData.value.menunggu_verifikasi ?? 0 },
        { label: 'Sudah Selesai', value: 'SELESAI', badge: summarySelesai.value ?? summaryData.value.selesai ?? 0, color: 'success' },
    ];
    if (summaryTerlambat.value > 0) {
        chips.push({
            label: 'Melewati Tenggat',
            value: 'TERLAMBAT',
            badge: summaryTerlambat.value,
            color: 'danger',
        });
    }
    return chips;
});

const uniqueCategories = computed(() => {
    const set = new Set();
    monitoringRows.value.forEach((r) => {
        const cat = r.Kategori_Penilai || r.Nama_Kategori;
        if (cat) set.add(cat);
    });
    return Array.from(set).map((val) => ({ value: val, label: val }));
});

const uniqueDivisi = computed(() => {
    const set = new Set();
    monitoringRows.value.forEach((r) => {
        if (r.Nama_Divisi) set.add(r.Nama_Divisi);
    });
    return Array.from(set).map((val) => ({ value: val, label: val }));
});

const filterDefinitions = computed(() => {
    const categories = rawPilihanSaringan.value?.kategori_penilai?.length
        ? rawPilihanSaringan.value.kategori_penilai.map((c) => ({ value: String(c.id || c.kode), label: c.nama || c.kode }))
        : uniqueCategories.value;

    const divisions = rawPilihanSaringan.value?.divisi?.length
        ? rawPilihanSaringan.value.divisi.map((d) => ({ value: String(d.id), label: d.nama }))
        : uniqueDivisi.value;

    const levels = rawPilihanSaringan.value?.level?.length
        ? rawPilihanSaringan.value.level.map((l) => ({ value: String(l.id), label: l.nama }))
        : [];

    const defs = [
        {
            key: 'kategori',
            label: 'Kategori Penilai',
            type: 'select',
            filterable: true,
            clearable: true,
            icon: 'people',
            options: categories,
            placeholder: 'Semua Kategori',
        },
        {
            key: 'id_divisi',
            label: 'Divisi',
            type: 'select',
            filterable: true,
            clearable: true,
            icon: 'building',
            options: divisions,
            placeholder: 'Semua Divisi',
        },
    ];

    if (levels.length > 0) {
        defs.push({
            key: 'id_level',
            label: 'Level Jabatan',
            type: 'select',
            filterable: true,
            clearable: true,
            icon: 'award',
            options: levels,
            placeholder: 'Semua Level',
        });
    }

    return defs;
});

const filteredMonitoringRows = computed(() => monitoringRows.value);

const hasActiveFilters = computed(() => {
    return Boolean(
        searchQuery.value?.trim() ||
        (activeStatusFilter.value && activeStatusFilter.value !== 'ALL') ||
        selectedFilterValues.kategori ||
        selectedFilterValues.id_divisi ||
        selectedFilterValues.id_level
    );
});

const activeStatusFilterLabel = computed(() => {
    const val = typeof activeStatusFilter.value === 'object' && activeStatusFilter.value !== null
        ? (activeStatusFilter.value.value ?? '')
        : (activeStatusFilter.value || '');
    const map = {
        ALL: 'Semua',
        BELUM_SELESAI: 'Belum Selesai',
        BELUM_MULAI: 'Belum Mengisi',
        BERJALAN: 'Diisi Sebagian',
        TERKUMPUL: 'Terisi, Belum Dikirim',
        MENUNGGU_VERIFIKASI: 'Menunggu Verifikasi HC Learn',
        SELESAI: 'Sudah Selesai',
        TERLAMBAT: 'Melewati Tenggat',
    };
    return map[val] || val;
});

const activeKategoriFilterLabel = computed(() => {
    const val = String(selectedFilterValues.kategori || '');
    if (!val) return '';
    const foundInBreakdown = categoryBreakdown.value.find((c) => String(c.id || c.kode) === val);
    if (foundInBreakdown) return foundInBreakdown.nama || foundInBreakdown.kode;
    const foundInRaw = rawPilihanSaringan.value?.kategori_penilai?.find((c) => String(c.id || c.kode) === val);
    if (foundInRaw) return foundInRaw.nama || foundInRaw.kode;
    return val;
});

const activeDivisiFilterLabel = computed(() => {
    const val = String(selectedFilterValues.id_divisi || '');
    if (!val) return '';
    const foundInList = divisionList.value.find((d) => String(d.id) === val);
    if (foundInList) return foundInList.nama || foundInList.divisi;
    const foundInRaw = rawPilihanSaringan.value?.divisi?.find((d) => String(d.id) === val);
    if (foundInRaw) return foundInRaw.nama;
    return val;
});

const activeLevelFilterLabel = computed(() => {
    const val = String(selectedFilterValues.id_level || '');
    if (!val) return '';
    const foundInRaw = rawPilihanSaringan.value?.level?.find((l) => String(l.id) === val);
    if (foundInRaw) return foundInRaw.nama;
    return val;
});

const paginatedRows = computed(() => monitoringRows.value);
const totalPages = computed(() => totalHalamanServer.value || 1);

/* Collapsible Grouped Participants by Karyawan Yang Dinilai */
const expandedParticipants = ref(new Set());

const groupedParticipants = computed(() => {
    const groups = new Map();

    monitoringRows.value.forEach((r) => {
        const pKey = r.Id_PA_Participant
            ? `P-${r.Id_PA_Participant}`
            : (r.Kode_Karyawan_Yang_Dinilai ? `K-${r.Kode_Karyawan_Yang_Dinilai}` : `N-${r.Nama_Karyawan_Yang_Dinilai || r.Nama_Karyawan}`);

        if (!groups.has(pKey)) {
            groups.set(pKey, {
                id_key: pKey,
                Id_PA_Participant: r.Id_PA_Participant,
                Id_PA_Result: r.Id_PA_Result ?? null,
                Kode_Karyawan: r.Kode_Karyawan_Yang_Dinilai || r.Kode_Karyawan,
                Kode_Karyawan_Yang_Dinilai: r.Kode_Karyawan_Yang_Dinilai || r.Kode_Karyawan,
                Nama_Karyawan: r.Nama_Karyawan_Yang_Dinilai || r.Nama_Karyawan_Saat_Cycle || r.Nama_Karyawan,
                Nama_Karyawan_Yang_Dinilai: r.Nama_Karyawan_Yang_Dinilai || r.Nama_Karyawan_Saat_Cycle || r.Nama_Karyawan,
                Nama_Divisi: r.Nama_Divisi || r.Nama_Divisi_Saat_Cycle || '—',
                Nama_Level: r.Nama_Level || r.Nama_Level_Saat_Cycle || '—',
                ID_Level: r.ID_Level,
                raters: [],
            });
        }

        groups.get(pKey).raters.push(r);
    });

    const progresServer = new Map(pesertaServer.value.map((p) => [`P-${p.Id_PA_Participant}`, p]));

    return Array.from(groups.values()).map((p) => {
        /* Progres dari SERVER, atas semua penilai orang ini — bukan dari baris yang kebetulan
           terlihat. Saringan status hanya menyempitkan daftar penilai yang ditampilkan. */
        const g = progresServer.get(p.id_key)?.progres;
        const total = g ? Number(g.total) : p.raters.length;
        const selesai = g ? Number(g.selesai) : p.raters.filter((r) => isRowSelesai(r)).length;
        const terlambat = g ? Number(g.terlambat) : p.raters.filter((r) => !isRowSelesai(r) && r.hari_terlambat > 0).length;
        const berjalan = g ? Number(g.berjalan) : p.raters.filter((r) => !isRowSelesai(r) && (r.Status_Penugasan === 'BERJALAN' || r.Status === 'BERJALAN')).length;
        const belumMulai = g ? Number(g.belum_mulai) : Math.max(0, total - selesai - berjalan);
        // Dibulatkan ke BAWAH, sama dengan server: 100% hanya bila semua penilainya selesai.
        const persen = total > 0 ? Math.floor((selesai / total) * 100) : 0;

        return {
            ...p,
            Id_PA_Result: progresServer.get(p.id_key)?.Id_PA_Result ?? p.Id_PA_Result,
            terkumpulRater: g ? Number(g.terkumpul) : p.raters.filter((r) => isRowTerkumpul(r)).length,
            raterTampil: p.raters.length,
            totalRater: total,
            selesaiRater: selesai,
            terlambatRater: terlambat,
            berjalanRater: berjalan,
            belumMulaiRater: belumMulai,
            persen,
        };
    });
});

function isParticipantExpanded(key) {
    return expandedParticipants.value.has(key);
}

function toggleExpandParticipant(key) {
    if (expandedParticipants.value.has(key)) {
        expandedParticipants.value.delete(key);
    } else {
        expandedParticipants.value.add(key);
    }
    expandedParticipants.value = new Set(expandedParticipants.value);
}

function expandAllParticipants() {
    groupedParticipants.value.forEach((p) => {
        expandedParticipants.value.add(p.id_key);
    });
    expandedParticipants.value = new Set(expandedParticipants.value);
}

function collapseAllParticipants() {
    expandedParticipants.value.clear();
    expandedParticipants.value = new Set();
}

const allExpanded = computed(() => {
    if (!groupedParticipants.value.length) return false;
    return groupedParticipants.value.every((p) => expandedParticipants.value.has(p.id_key));
});

function toggleAllExpand() {
    if (allExpanded.value) {
        collapseAllParticipants();
    } else {
        expandAllParticipants();
    }
}

function gantiHalaman(arah) {
    const tujuan = currentPage.value + arah;
    if (tujuan < 1 || tujuan > totalPages.value) return;
    currentPage.value = tujuan;
    muatDataMonitoring({ segarkanAlur: false });
}

/* HcisPagination memancarkan `update:current-page` DAN `current-change` untuk satu klik;
   cukup satu yang didengar. Halaman yang sama tidak dimuat ulang, dan keadaan tahap
   (alur penutupan) tidak berubah hanya karena berganti halaman. */
function onPageChange(page) {
    if (!page || page < 1 || page > totalPages.value || page === currentPage.value) return;
    currentPage.value = page;
    muatDataMonitoring({ segarkanAlur: false });
}

const antreanTertahan = computed(() => {
    const k = keranjang.value || {};
    return [
        {
            jenis: 'needs_kpi',
            label: 'Kendala Nilai KPI',
            sebab: 'Tertahan, antara lain karena KPI belum siap atau belum disetujui untuk kalkulasi',
            jumlah: k.needs_kpi || 0,
            nada: 'danger',
            icon: 'graph-up-arrow',
        },
        {
            /* Dulu digabung ke "Kendala Lain" pada angkanya, tetapi daftar "Kendala Lain" di
               server mengecualikannya — orangnya terhitung namun tak pernah tampil. */
            jenis: 'needs_rater',
            label: 'Kendala Penilai',
            sebab: 'Tertahan, antara lain karena kategori penilai belum memenuhi ambang. Tagih penilainya, lepas paksa lembar terisi, atau tambah penilai',
            jumlah: k.needs_rater || 0,
            nada: 'warning',
            icon: 'people-fill',
        },
        {
            jenis: 'tertahan_lain',
            label: 'Tertahan — Kendala Lain',
            sebab: 'Tertahan karena sebab di luar KPI dan penilai (mis. jenis kompetensi wajib tak tercakup)',
            jumlah: k.tertahan_lain || 0,
            nada: 'warning',
            icon: 'question-octagon-fill',
        },
        {
            jenis: 'siap_dihitung',
            label: 'Siap Dihitung',
            sebab: 'Seluruh penilai lengkap, siap dipicu proses kalkulasi nilai',
            jumlah: k.siap_dihitung || 0,
            nada: 'brand',
            icon: 'calculator-fill',
        },
    ];
});

const antreanUlasan = computed(() => {
    const k = keranjang.value || {};
    return [
        {
            /* Bulan KPI periode siklus yang realisasinya belum diisi atau belum disetujui +
               diverifikasi. Orangnya biasanya tetap DIHITUNG dari bulan yang ada, jadi tidak
               muncul di keranjang tertahan mana pun (defect UAT 8 Okt). Tidak mengunci. */
            jenis: 'kpi_belum_lengkap',
            label: 'KPI Belum Lengkap',
            sebab: 'Ada bulan KPI dalam periode siklus yang belum diisi atau belum disetujui. Hitung ulang sesudah KPI-nya beres',
            jumlah: k.kpi_belum_lengkap || 0,
            nada: 'danger',
            icon: 'calendar-x',
        },
        {
            /* Kategori penilai yang GUGUR (pengisi di bawah minimal) pada hasil DIHITUNG.
               Bobotnya sudah dibagikan ke kategori lain; penerbitan menahannya sampai admin
               memutuskan (UAT 13 Okt 2026). Kategori yang sengaja tidak diikutkan tidak ikut. */
            jenis: 'kategori_tidak_dihitung',
            label: 'Kategori Penilai Tidak Dihitung',
            sebab: 'Pengisi sebuah kategori di bawah minimal, jadi kategorinya gugur. Tagih penilainya lalu hitung ulang, atau terbitkan dengan sadar',
            jumlah: k.kategori_tidak_dihitung || 0,
            nada: 'danger',
            icon: 'people',
        },
        {
            jenis: 'needs_review_qualitative',
            label: 'Ulasan Qualitative Questions',
            sebab: 'Ditulis di tahap Kesimpulan Kualitatif →',
            // Dikerjakan di tahapnya sendiri (ruang kerja massal), bukan di tabel antrean ini.
            tahap: 'kualitatif',
            jumlah: k.needs_review_qualitative || 0,
            nada: 'amber',
            icon: 'chat-quote-fill',
        },
        {
            jenis: 'kiriman_susulan',
            label: 'Kiriman Rater Susulan',
            sebab: 'Jawaban rater diterima sesudah hasil evaluasi sempat diterbitkan',
            jumlah: k.kiriman_susulan || 0,
            nada: 'purple',
            icon: 'clock-history',
        },
    ];
});

const totalTertahanKPI = computed(() => keranjang.value?.needs_kpi || 0);

const NAMA_BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
function namaBulanKpi(periode) {
    const [t, b] = String(periode || '').split('-');
    return NAMA_BULAN[Number(b) - 1] ? `${NAMA_BULAN[Number(b) - 1]} ${t}` : periode;
}
const totalTertahanRaterLain = computed(() => {
    const k = keranjang.value || {};
    return (k.needs_rater || 0) + (k.tertahan_lain || 0) + (k.siap_dihitung || 0);
});
const totalUlasanKualitatif = computed(() => keranjang.value?.needs_review_qualitative || 0);
const totalKirimanSusulan = computed(() => keranjang.value?.kiriman_susulan || 0);

/* JUMLAH ORANG, bukan jumlah kartu. Sejak 8 Okt 2026 satu orang tertahan muncul di setiap
   kartu sebab yang ia bawa (mis. KPI DAN penilai), jadi menjumlahkan kartu menghitung orang
   yang sama dua kali. `tertahan` dari server adalah jumlah orang tertahan yang berbeda. */
const totalTertahan = computed(() => {
    const k = keranjang.value || {};
    return (k.tertahan || 0) + (k.siap_dihitung || 0);
});

const totalUlasan = computed(() => {
    return antreanUlasan.value.reduce((acc, item) => acc + (item.jumlah || 0), 0);
});

const daftarKeranjang = computed(() => [
    ...antreanTertahan.value,
    ...antreanUlasan.value,
]);

const totalKeranjang = computed(() => {
    return totalTertahan.value + totalUlasan.value;
});

const subTabKeranjang = ref('TERTAHAN');

function inisialisasiKeranjangDefault() {
    if (subTabKeranjang.value === 'TERTAHAN') {
        const item = antreanTertahan.value.find((k) => k.jumlah > 0) || antreanTertahan.value[0];
        if (item) bukaKeranjang(item.jenis);
    } else {
        const item = antreanUlasan.value.find((k) => k.jumlah > 0) || antreanUlasan.value[0];
        if (item) bukaKeranjang(item.jenis);
    }
}

function gantiSubTabKeranjang(sub) {
    if (subTabKeranjang.value === sub) return;
    subTabKeranjang.value = sub;
    inisialisasiKeranjangDefault();
}

const detailKeranjangAktif = computed(() => {
    return daftarKeranjang.value.find((k) => k.jenis === keranjangAktif.value) || null;
});

const labelKeranjangAktif = computed(() => detailKeranjangAktif.value?.label || '');

const pencarianKeranjang = ref('');
const halamanKeranjang = ref(1);
const perHalamanKeranjang = ref(25);

/* Hitung ulang BERCICIL SAMPAI HABIS (koreksi 7 Okt 2026). Dulu satu panggilan memproses 50
   orang tertahan pertama; klik berikutnya mengambil 50 orang yang SAMA, dan sisanya tak pernah
   tersentuh. Kini tiap cicilan membawa penanda `terakhir_id`. `jenis` SIAP dipakai antrean
   "Siap Dihitung" — dulu tombolnya hanya menyapu yang tertahan. */
const progresSapu = reactive({ diperiksa: 0, jadi: 0, tetap: 0, gagal: 0 });

async function jalankanSapu(jenisDiminta) {
    if (menyapu.value || !selectedCycleId.value) return;
    const jenis = jenisDiminta === 'SIAP' ? 'SIAP' : 'TERTAHAN';
    menyapu.value = true;
    Object.assign(progresSapu, { diperiksa: 0, jadi: 0, tetap: 0, gagal: 0 });
    try {
        let mulaiDariId = null;
        for (let putaran = 0; putaran < 100; putaran++) {
            const h = (await paApi.sapuTertahan(selectedCycleId.value, 50, { mulaiDariId, jenis })) || {};
            progresSapu.diperiksa += Number(h.diperiksa) || 0;
            progresSapu.jadi += Number(h.jadi_dihitung) || 0;
            progresSapu.tetap += Number(h.tetap_tertahan) || 0;
            progresSapu.gagal += Number(h.gagal) || 0;
            if (h.selesai !== false || h.terakhir_id === null || h.terakhir_id === undefined) break;
            mulaiDariId = h.terakhir_id;
        }
        errorMonitoring.value = '';
        hcisMessage.success(
            `${progresSapu.diperiksa} peserta diperiksa — ${progresSapu.jadi} kini punya nilai, ${progresSapu.tetap} masih tertahan`
                + (progresSapu.gagal ? `, ${progresSapu.gagal} gagal.` : '.'),
            jenis === 'SIAP' ? 'Perhitungan Selesai' : 'Hitung Ulang Selesai',
        );
        await muatDataMonitoring();
        if (keranjangAktif.value) {
            // Dipaksa: jenis dan halamannya sama, dan tanpa itu isinya tidak pernah dimuat ulang.
            await bukaKeranjang(keranjangAktif.value, halamanKeranjang.value, { paksa: true });
        }
    } catch (e) {
        errorMonitoring.value = e?.message || 'Gagal menghitung ulang yang tertahan.';
    } finally {
        menyapu.value = false;
    }
}

/* Pengecualian sesudah pengisian ditutup — hanya hasil tanpa angka (server menegakkan aturan
   yang sama di PaParticipantEditService::bolehDikecualikanSesudahDitutup). */
const kecualikan = reactive({ buka: false, baris: null, alasan: '', galat: '', mengirim: false });

function bolehKecualikanSesudahTutup(b) {
    return activeCycle.value?.Status_Cycle === 'CLOSED' && ['TERTAHAN', 'BELUM_DIHITUNG'].includes(b?.Status_Hasil);
}

function bukaKecualikan(b) {
    Object.assign(kecualikan, { buka: true, baris: b, alasan: '', galat: '', mengirim: false });
}

async function kirimKecualikan() {
    const b = kecualikan.baris;
    if (!b || !kecualikan.alasan.trim()) return;
    kecualikan.mengirim = true;
    kecualikan.galat = '';
    try {
        await paApi.ubahStatusPeserta(b.Id_PA_Participant, 'DIKECUALIKAN', kecualikan.alasan.trim());
        kecualikan.buka = false;
        hcisMessage.success(`${b.Nama_Karyawan_Saat_Cycle} dikecualikan dari siklus.`, 'Peserta dikecualikan');
        await Promise.allSettled([bukaKeranjang(keranjangAktif.value, halamanKeranjang.value, { paksa: true }), muatDataMonitoring({ segarkanAlur: true })]);
    } catch (e) {
        kecualikan.galat = e?.message || 'Peserta tidak bisa dikecualikan.';
    } finally {
        kecualikan.mengirim = false;
    }
}

function bukaVerifikasi(baris) {
    bukaHasilPeserta(baris);
}

const SARINGAN_PER_KERANJANG = {
    needs_kpi: 'TERTAHAN',
    needs_rater: 'TERTAHAN',
    tertahan_lain: 'TERTAHAN',
    needs_review_qualitative: 'SIAP',
    reviewing_calibration: 'SIAP',
    siap_dihitung: 'BELUM',
    terbit: 'TERBIT',
};

/* Dipakai dari PRATINJAU di tab Ringkasan, bukan dari keranjang di tabnya sendiri.
   Di tab Antrean Tindak Lanjut keranjangnya dibuka DI TEMPAT — melemparkan admin ke tab
   lain tepat setelah ia menekan antrean yang ingin dikerjakannya adalah persis kebiasaan
   yang sedang kita cabut dari modul ini. */
function keranjangKeTab(jenis) {
    const saringan = SARINGAN_PER_KERANJANG[jenis];

    if (!saringan) {
        // Kiriman susulan urusannya penugasan, bukan hasil.
        pilihTab('PENGISIAN');

        return;
    }

    pilihTab('HASIL');
    saringHasil.value = saringan;
}

let urutanKeranjang = 0;

/* Kata kunci yang dipakai muatan TERAKHIR. Pengamat kotak cari membandingkannya supaya
   pengosongan kotak oleh bukaKeranjang() sendiri tidak memicu muatan kedua. */
let cariKeranjangTermuat = '';

/* `paksa`: muat ulang walau jenis dan halamannya sama — dipakai sesudah aksi/sapu dan saat
   kata kunci berubah; tanpa itu panggilan tersebut berhenti di penjaga "sudah terbuka". */
async function bukaKeranjang(jenis, page = 1, { paksa = false } = {}) {
    if (antreanTertahan.value.some((k) => k.jenis === jenis)) {
        subTabKeranjang.value = 'TERTAHAN';
    } else if (antreanUlasan.value.some((k) => k.jenis === jenis)) {
        subTabKeranjang.value = 'ULASAN';
    }

    if (!paksa && keranjangAktif.value === jenis && page === halamanKeranjang.value && isiKeranjang.value) {
        return;
    }
    // Kata kunci hanya dikosongkan saat BERGANTI keranjang; berganti halaman tetap mencari.
    if (keranjangAktif.value !== jenis) pencarianKeranjang.value = '';
    keranjangAktif.value = jenis;
    halamanKeranjang.value = page;
    if (!paksa) isiKeranjang.value = null;
    memuatKeranjang.value = true;
    // Hanya respons permintaan TERBARU yang ditulis: klik kategori/halaman yang cepat tidak
    // boleh membiarkan isi antrean lama menimpa antrean yang sedang dipilih.
    const nomor = ++urutanKeranjang;
    const cari = (pencarianKeranjang.value || '').trim();
    cariKeranjangTermuat = cari;
    try {
        const isi = await paApi.keranjangSiklus(selectedCycleId.value, jenis, {
            halaman: page,
            per_halaman: perHalamanKeranjang.value,
            // Dicari di SERVER atas seluruh isi keranjang (nama/NIK), bukan 25 baris halaman ini.
            q: cari || undefined,
        });
        if (nomor === urutanKeranjang) isiKeranjang.value = isi;
    } catch (e) {
        if (nomor !== urutanKeranjang) return;
        errorMonitoring.value = e?.message || 'Gagal memuat isi antrean.';
        keranjangAktif.value = '';
    } finally {
        if (nomor === urutanKeranjang) memuatKeranjang.value = false;
    }
}

/* Barisnya sudah tersaring server (lihat bukaKeranjang). Dulu disaring di sini atas 25
   baris halaman yang terbuka saja, lalu menyatakan orangnya tak ditemukan "pada kategori
   antrean ini" padahal ia ada di halaman lain. */
const filteredIsiKeranjangBaris = computed(() => isiKeranjang.value?.baris || []);

let tundaCariKeranjang = null;
watch(pencarianKeranjang, () => {
    clearTimeout(tundaCariKeranjang);
    tundaCariKeranjang = setTimeout(() => {
        if (!keranjangAktif.value || (pencarianKeranjang.value || '').trim() === cariKeranjangTermuat) return;
        bukaKeranjang(keranjangAktif.value, 1, { paksa: true });
    }, 300);
});

function getGradeBadgeVariant(grade) {
    if (!grade) return 'neutral';
    const g = String(grade).trim().toUpperCase();
    if (g.startsWith('A')) return 'success';
    if (g.startsWith('B')) return 'brand';
    if (g.startsWith('C')) return 'warning';
    if (g.startsWith('D') || g.startsWith('E')) return 'danger';
    return 'neutral';
}

const daftarTab = computed(() => [
    {
        kode: 'RINGKASAN',
        nama: 'Ringkasan & Progres',
        satuan: 'Cockpit Eksekutif',
        ikon: 'speedometer2',
        badge: null,
    },
    {
        kode: 'PENGISIAN',
        nama: 'Monitoring Rater',
        satuan: 'Per Penugasan',
        ikon: 'clipboard-check',
        badge: summaryBelumSelesai.value > 0 ? `${formatAngka(summaryBelumSelesai.value)} Belum` : null,
        badgeVariant: summaryTerlambat.value > 0 ? 'danger' : 'warning',
    },
    {
        kode: 'KERANJANG',
        nama: 'Antrean Tindak Lanjut',
        satuan: 'Keputusan Admin',
        ikon: 'inbox-fill',
        badge: totalKeranjang.value > 0 ? formatAngka(totalKeranjang.value) : null,
        badgeVariant: (keranjang.value?.tertahan || 0) > 0 ? 'danger' : 'brand',
    },
    {
        /* SATU TAB, BUKAN DUA.

           Kalibrasi dan penerbitan bukan tahap yang berdiri sendiri: keduanya sudah menjadi
           tombol per orang di dalam panel hasil. Yang tersisa di tab "Kalibrasi & Publikasi"
           hanyalah tiga tautan ke halaman lain — dan sesuatu yang isinya cuma tautan bukan
           tab, itu menu.

           Sebaran nilai dan 9-box memang bersatuan POPULASI, beda dari daftar per peserta di
           bawahnya. Tempatnya tetap di sini, tetapi sebagai KONTEKS di atas daftar: urutan
           keputusannya memang begitu — lihat sebarannya dulu, baru bertindak per orang. */
        kode: 'HASIL',
        nama: 'Hasil, Kalibrasi & Publikasi',
        satuan: 'Per Peserta',
        ikon: 'calculator',
        badge: null,
    },
]);

/* ALAMAT = TAHAP. `?tahap=` menentukan tahap penutupan yang terbuka; `?tab=` lama tetap
   diterima supaya tautan dari halaman lain tidak putus (tahapPenutupan.js). */
const TAB_TAHAP = { ikhtisar: 'RINGKASAN', pengisian: 'PENGISIAN', kualitatif: 'KUALITATIF', kurva: 'KURVA', balancing: 'BALANCING', terbit: 'TERBIT' };
const TAHAP_TAB = { RINGKASAN: 'ikhtisar', PENGISIAN: 'pengisian', HASIL: 'verifikasi', KERANJANG: 'verifikasi', KUALITATIF: 'kualitatif', KURVA: 'kurva', BALANCING: 'balancing', TERBIT: 'terbit' };
const awalAlamat = typeof window !== 'undefined' ? tahapDariUrl(window.location.search) : { tahap: 'ikhtisar', sub: 'HASIL' };

const tahapAktif = ref(awalAlamat.tahap);
const tabAktif = ref(awalAlamat.tahap === 'verifikasi' ? awalAlamat.sub : TAB_TAHAP[awalAlamat.tahap]);

const alurPenutupan = ref(null);
let urutanAlur = 0;

async function muatAlur() {
    if (!selectedCycleId.value) return;
    const nomor = ++urutanAlur;
    try {
        const data = await paApi.penutupanSiklus(Number(selectedCycleId.value));
        if (nomor === urutanAlur) alurPenutupan.value = data || null;
    } catch {
        // Stepper tetap bisa dipakai tanpa keadaan; tahap menampilkan "Menunggu".
        if (nomor === urutanAlur) alurPenutupan.value = null;
    }
}

function tahapAlur(kode) {
    return (alurPenutupan.value?.tahap || []).find((t) => t.kode === kode) || null;
}

const tahapIkhtisar = computed(() => TAHAP_PENUTUPAN.map((t) => ({ ...t, ...(tahapAlur(t.kode) || {}) })));

/* Diturunkan dari daftar tahap, bukan diketik: kalimat lama menyebut "lima tahap" dan
   tertinggal ketika Kesimpulan Kualitatif ditambahkan. */
const deskripsiTahapPenutupan = computed(
    () => `${TAHAP_PENUTUPAN.length} tahap penutupan siklus: ${TAHAP_PENUTUPAN.map((t) => t.nama.toLowerCase()).join(', ')}.`,
);

function tulisAlamat() {
    if (typeof window === 'undefined') return;
    const url = new URL(window.location.href);
    url.searchParams.delete('tab');
    url.searchParams.set('tahap', tahapAktif.value);
    if (tahapAktif.value === 'verifikasi') url.searchParams.set('sub', tabAktif.value === 'KERANJANG' ? 'KERANJANG' : 'HASIL');
    else url.searchParams.delete('sub');
    window.history.replaceState(null, '', url.toString());
}

function pilihTahap(kode) {
    if (kode === 'verifikasi') {
        pilihTab(tabAktif.value === 'KERANJANG' ? 'KERANJANG' : 'HASIL');
        return;
    }
    pilihTab(TAB_TAHAP[kode] || 'RINGKASAN');
}

const tautanKalibrasi = [
    { url: '/performance-appraisal/distribusi', nama: 'Distribusi Nilai', ikon: 'bar-chart-line', ringkas: 'Sebaran grade dan nilai seluruh siklus.' },
    { url: '/performance-appraisal/hav-map', nama: 'HAV Map (9-Box)', ikon: 'grid-3x3', ringkas: 'Sembilan kotak, peserta pada tiap kotak.' },
];

/* ------------------------------------------------------------------ *
 * Tab Hasil — daftar peserta beserta hasilnya                         *
 * ------------------------------------------------------------------ */

const pesertaHasil = ref([]);
const memuatPeserta = ref(false);
const galatPeserta = ref('');
const saringHasil = ref('SEMUA');
const searchQueryHasil = ref('');
const filterDivisiHasil = ref('');
const viewModeHasil = ref(typeof window !== 'undefined' && window.innerWidth < 768 ? 'grid' : 'table');
const halamanHasil = ref(1);
const perHalamanHasil = 20;

/* LINGKUP TAB INI: yang SUDAH TERHITUNG saja.
   Tab ini meja tinjau-kalibrasi-terbit. Peserta yang belum dihitung atau tertahan bukan
   pekerjaan di sini — mereka punya tempatnya sendiri di tab "Antrean Tindak Lanjut". */
const STATUS_LINGKUP_HASIL = 'DIHITUNG,TERBIT';

/* SELURUHNYA DARI SERVER (koreksi 7 Okt 2026). Dulu tab ini memuat satu permintaan
   `per_halaman: 200` — dipangkas server menjadi 100 — lalu menghitung kartu, mencari,
   menyaring, dan memotong halaman di layar atas 100 orang itu. Peserta ke-101 dan
   seterusnya tidak pernah tampil dan tidak bisa dicari, tanpa satu pun tanda. */
const totalHasilServer = ref(0);
const opsiHasil = ref({ divisi: [], level: [] });
let urutanHasil = 0;
let tundaCariHasil = null;

/* Kartu & chip: cacah seluruh siklus dari alur penutupan, bukan dari halaman yang terbuka. */
const cacahHasil = computed(() => {
    const c = alurPenutupan.value?.cacah || {};
    const siap = Number(c.hasil_dihitung || 0);
    const terbit = Number(c.hasil_terbit || 0);

    return { SEMUA: siap + terbit, SIAP: siap, TERBIT: terbit, TERTAHAN: Number(c.hasil_tertahan || 0), BELUM: Number(c.hasil_belum || 0) };
});
const totalPesertaHasil = computed(() => cacahHasil.value.SEMUA);
const uniqueDivisiHasil = computed(() => opsiHasil.value.divisi || []);
const paginatedPesertaHasil = computed(() => pesertaHasil.value);
const STATUS_PER_SARINGAN_HASIL = { SEMUA: STATUS_LINGKUP_HASIL, SIAP: 'DIHITUNG', TERBIT: 'TERBIT' };

/* Saringan berubah → kembali ke halaman 1; perubahan halaman yang memuat. Bila sudah di
   halaman 1, muat langsung (watcher halaman tidak akan berbunyi). */
function terapkanSaringanHasil() {
    if (halamanHasil.value !== 1) halamanHasil.value = 1;
    else muatPeserta();
}
watch([saringHasil, filterDivisiHasil], terapkanSaringanHasil);
watch(searchQueryHasil, () => {
    clearTimeout(tundaCariHasil);
    tundaCariHasil = setTimeout(terapkanSaringanHasil, 300);
});
watch(halamanHasil, () => muatPeserta());

function resetFilterHasil() {
    searchQueryHasil.value = '';
    filterDivisiHasil.value = '';
    saringHasil.value = 'SEMUA';
}

function formatNilai(nilai) {
    const angka = Number(nilai);

    return Number.isFinite(angka) && nilai !== null && nilai !== ''
        ? angka.toLocaleString('id-ID', { maximumFractionDigits: 2 })
        : '—';
}

async function muatPeserta() {
    if (!selectedCycleId.value) return;

    const nomor = ++urutanHasil;
    memuatPeserta.value = true;
    galatPeserta.value = '';

    try {
        const response = await paApi.daftarPeserta(selectedCycleId.value, {
            halaman: halamanHasil.value,
            per_halaman: perHalamanHasil,
            status_peserta: 'DIIKUTKAN',
            // Lingkup, saringan, dan pencarian di SERVER (lihat catatan totalHasilServer).
            status_hasil: STATUS_PER_SARINGAN_HASIL[saringHasil.value] || STATUS_LINGKUP_HASIL,
            id_divisi: filterDivisiHasil.value || undefined,
            q: (searchQueryHasil.value || '').trim() || undefined,
            dengan_opsi: opsiHasil.value.divisi.length ? undefined : 1,
        });
        // Hanya respons terbaru yang ditulis: saringan/halaman lama yang tiba belakangan dibuang.
        if (nomor !== urutanHasil) return;
        const data = response?.result ?? response;

        pesertaHasil.value = data?.peserta || data?.baris || [];
        totalHasilServer.value = Number(data?.total ?? pesertaHasil.value.length);
        if (data?.opsi) opsiHasil.value = data.opsi;
    } catch (exception) {
        if (nomor !== urutanHasil) return;
        galatPeserta.value = exception?.message || 'Gagal memuat daftar hasil peserta.';
    } finally {
        if (nomor === urutanHasil) memuatPeserta.value = false;
    }
}

function pilihTab(kode) {
    tabAktif.value = kode;
    tahapAktif.value = TAHAP_TAB[kode] || 'ikhtisar';
    tulisAlamat();

    if (kode === 'KERANJANG') {
        if (!keranjangAktif.value) {
            inisialisasiKeranjangDefault();
        }
    } else if (kode === 'HASIL' && !pesertaHasil.value.length && !memuatPeserta.value) {
        muatPeserta();
    } else if (kode === 'PENGISIAN' && !monitoringRows.value.length && !loadingMonitoring.value && selectedCycleId.value) {
        muatDataMonitoring();
    }
}

/* ------------------------------------------------------------------ *
 * Hasil peserta & penerbitan, dikerjakan DI HALAMAN INI               *
 * ------------------------------------------------------------------ */

const hasilPeserta = usePaResult(paApi);
const penerbitan = usePaPublication(paApi);

const pesertaTerpilih = ref(null);
const dialogHasilTerbuka = ref(false);
const dialogPenerbitanTerbuka = ref(false);
const ringkasanPenerbitan = ref(null);
const menghitungSiklus = ref(false);
const dialogHitungTerbuka = ref(false);
const pratinjauHitung = ref(null);
const pratinjauHitungLoading = ref(false);
const pratinjauHitungError = ref('');

function bolehOperasi(operasi) {
    const daftar = activeCycle.value?.operasi_boleh;

    return Array.isArray(daftar) && daftar.includes(operasi);
}

/* Tombol milik fase lain hanya muncul bila DUA gerbang server terbuka: status siklus
   (`operasi_boleh`) dan izin halaman fasenya (`akses`). Salah satunya tertutup = 422/403. */
const bolehHitungUlang = computed(() => punyaAkses('hasil') && bolehOperasi('HITUNG_HASIL'));
const bolehPetakanUlang = computed(() => punyaAkses('penyiapan') && bolehOperasi('TERIMA_PAYLOAD'));
const alasanTakBolehPetakan = computed(() => (!punyaAkses('penyiapan')
    ? 'Pemetaan ulang butuh izin penyiapan siklus.'
    : `Pemetaan ulang hanya bisa saat siklus OPEN atau CLOSED (kini ${activeCycle.value?.Status_Cycle || '—'}).`));

function bisaLepasPaksa(row) {
    return isRowTerkumpul(row) && bolehHitungUlang.value;
}

async function bukaHasilPeserta(row) {
    const pid = row?.Id_PA_Participant;

    if (!pid) return;

    pesertaTerpilih.value = {
        Id_PA_Participant: pid,
        Id_PA_Result: row.Id_PA_Result ?? null,
        Nama_Karyawan_Saat_Cycle: row.Nama_Karyawan_Yang_Dinilai || row.Nama_Karyawan_Saat_Cycle || row.Nama_Karyawan || '—',
        Kode_Karyawan: row.Kode_Karyawan_Yang_Dinilai || row.Kode_Karyawan || '—',
    };

    dialogHasilTerbuka.value = true;
    await hasilPeserta.muat(pid);
}

function handleRowClick(payload) {
    const row = payload?.row || payload;
    if (!row) return;
    bukaHasilPeserta(row);
}

async function muatDetailHasil() {
    const pid = pesertaTerpilih.value?.Id_PA_Participant;

    if (!pid) return;

    await hasilPeserta.muat(pid);
}

/* SESUDAH AKSI PER PESERTA (koreksi 8 Okt 2026). Dulu hanya monitoring yang dimuat ulang,
   sehingga baris tabel Hasil tetap menampilkan status dan nilai sebelum aksi — admin yang
   baru menerbitkan seseorang masih melihatnya "Dihitung". Daftar Hasil dimuat ulang bila
   sudah pernah dimuat (tab Hasil sedang atau pernah dibuka), dan isi antrean yang sedang
   terbuka ikut disegarkan karena aksi itu memindahkan orang antar-keranjang. */
async function segarkanSesudahAksiPeserta() {
    const tugas = [muatDataMonitoring()];

    if (tabAktif.value === 'HASIL' || pesertaHasil.value.length) {
        tugas.push(muatPeserta());
    }

    if (tabAktif.value === 'KERANJANG' && keranjangAktif.value) {
        tugas.push(bukaKeranjang(keranjangAktif.value, halamanKeranjang.value, { paksa: true }));
    }

    await Promise.all(tugas);
}

async function hitungPeserta() {
    const pid = pesertaTerpilih.value?.Id_PA_Participant;

    if (!pid) return;

    await hasilPeserta.hitung(pid);
    await segarkanSesudahAksiPeserta();
}

async function kalibrasiPeserta(data) {
    const pid = pesertaTerpilih.value?.Id_PA_Participant;
    const resultId = data?.resultId
        || hasilPeserta.detail.value?.peserta?.Id_PA_Result
        || pesertaTerpilih.value?.Id_PA_Result;

    if (!pid || !resultId) return;

    await hasilPeserta.kalibrasi(pid, resultId, data?.payload || data);
    await segarkanSesudahAksiPeserta();
}

async function batalkanPenugasanPeserta(data) {
    const assignmentId = data?.assignment?.Id_PA_Assignment;

    if (!assignmentId) return;

    await hasilPeserta.batalkan(
        pesertaTerpilih.value?.Id_PA_Participant,
        assignmentId,
        data?.alasan || '',
    );
    await segarkanSesudahAksiPeserta();
}

async function terbitkanSatuPeserta() {
    const pid = pesertaTerpilih.value?.Id_PA_Participant;

    if (!pid) return;

    try {
        if ((await terbitkanPesertaBerkonfirmasi(paApi, pid))?.dibatalkan) return;
        await muatDetailHasil();
        await segarkanSesudahAksiPeserta();
    } catch (exception) {
        errorMonitoring.value = exception?.message || 'Penerbitan hasil peserta gagal.';
    }
}

/* Rilis ke LEADER: pintu terpisah dari penerbitan ke karyawan (DDL-2026-130). Yang dibuka
   adalah halaman PA Tim; orangnya sendiri tetap tidak melihat apa pun. */
async function rilisSatuKeLeader() {
    const pid = pesertaTerpilih.value?.Id_PA_Participant;

    if (!pid) return;

    try {
        if ((await rilisLeaderPesertaBerkonfirmasi(paApi, pid))?.dibatalkan) return;
        await muatDetailHasil();
        await segarkanSesudahAksiPeserta();
        hcisMessage.success('Hasil dirilis ke leader.', 'Rilis ke leader');
    } catch (exception) {
        // Toast, bukan errorMonitoring: galat halaman tertutup dialog dan tak terbaca.
        hcisMessage.error(exception?.message || 'Rilis hasil ke leader gagal.', 'Rilis gagal');
    }
}

async function cabutRilisSatuLeader(alasan) {
    const pid = pesertaTerpilih.value?.Id_PA_Participant;

    if (!pid) return;

    try {
        await paApi.cabutRilisLeader(pid, alasan);
        await muatDetailHasil();
        await segarkanSesudahAksiPeserta();
        hcisMessage.success('Rilis ke leader dicabut.', 'Rilis dicabut');
    } catch (exception) {
        hcisMessage.error(exception?.message || 'Pencabutan rilis leader gagal.', 'Pencabutan gagal');
    }
}

async function bukaDialogPenerbitan() {
    if (!selectedCycleId.value) return;

    dialogPenerbitanTerbuka.value = true;
    await muatRingkasanPenerbitan();
}

async function muatRingkasanPenerbitan() {
    if (!selectedCycleId.value) return;

    ringkasanPenerbitan.value = await penerbitan.muat(selectedCycleId.value);
}

async function terbitkanSiklus(payload) {
    if (!selectedCycleId.value) return;

    // Dialog ditutup HANYA bila berhasil: penolakan server harus tetap terbaca di dialognya.
    const hasil = await penerbitan.terbitkan(selectedCycleId.value, payload);
    if (!hasil?.data) return;
    dialogPenerbitanTerbuka.value = false;
    // Yang dilewati karena KPI belum lengkap disebut di pesannya; nadanya peringatan.
    (adaYangDilewati(hasil.data) ? hcisMessage.warning : hcisMessage.success)(
        penerbitan.actionSuccess.value || 'Penerbitan selesai.',
        'Hasil diterbitkan',
    );
    await muatDataMonitoring();
}

/* Tutup penerbitan (CLOSED → PUBLISHED). Tombolnya sudah ada di dialog penerbitan sejak
   lama tetapi tidak pernah disambungkan, sehingga siklus tidak pernah bisa PUBLISHED. */
async function tutupPenerbitanSiklus() {
    if (!selectedCycleId.value) return;

    const hasil = await penerbitan.tutup(selectedCycleId.value);
    if (!hasil?.data) return;
    dialogPenerbitanTerbuka.value = false;
    hcisMessage.success(penerbitan.actionSuccess.value || 'Penerbitan siklus ditutup.', 'Siklus PUBLISHED');
    await muatSiklusUlang();
}

/* PERHITUNGAN MASSAL MENUNTUT SIKLUS TERTUTUP, dan tombolnya harus mengatakan hal yang
   sama dengan servernya.

   `HITUNG_HASIL` mengizinkan `OPEN` — tetapi itu izin untuk jalur PER ORANG, yang memang
   melayani siklus berjalan begitu penilai seseorang lengkap. Jalur massal menolak `OPEN`
   dengan alasan yang benar: menghitung ratusan orang yang penilainya masih berjalan lalu
   mengulang seluruhnya begitu mereka selesai adalah pekerjaan yang hasilnya dibuang sendiri.

   Menawarkan tombol yang sudah pasti ditolak berarti menyuruh admin menekannya untuk
   mengetahui sesuatu yang sudah bisa dikatakan sekarang. */
const bisaHitungMassal = computed(
    () => bolehOperasi('HITUNG_HASIL') && activeCycle.value?.Status_Cycle === 'CLOSED',
);

const progresHitung = reactive({ diproses: 0, total: 0, dihitung: 0, tertahan: 0, gagal: 0 });

/* Ukuran cicilan. Terukur ±0,5 detik per peserta, jadi 100 orang sekitar satu menit —
   cukup pendek untuk lolos batas waktu permintaan, cukup panjang supaya siklus 958 orang
   tidak menjadi sepuluh putaran perjalanan jaringan. */

/* Pratinjau dimuat SETIAP KALI dialog dibuka, tidak di-cache.

   Angkanya berubah setiap ada penilai yang mengirim. Konfirmasi yang menyebut angka basi
   lebih buruk daripada yang tidak menyebut angka sama sekali: ia meyakinkan orang tentang
   sesuatu yang sudah tidak benar. */
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
    } catch (exception) {
        pratinjauHitung.value = null;
        pratinjauHitungError.value = exception?.message || 'Pratinjau perhitungan gagal dimuat.';
    } finally {
        pratinjauHitungLoading.value = false;
    }
}

/*
 | HITUNG SEBAGAI TUGAS LATAR (14 Okt 2026).
 |
 | Dulu layar mencicil 100 peserta per permintaan dari browser: ±14 menit pada 1.600 peserta,
 | dengan tab yang wajib tetap terbuka — tab tertutup meninggalkan separuh siklus terhitung.
 | Kini antrean mengerjakannya; layar memulai lalu membaca kemajuan tiap beberapa detik, dan
 | menyambung lagi bila halaman dibuka ulang selagi tugasnya masih berjalan.
 */
const JEDA_BACA_HITUNG = 3000;
let pembacaHitung = null;
let siklusDibaca = null;

function hentikanPembacaHitung() {
    if (pembacaHitung) clearTimeout(pembacaHitung);
    pembacaHitung = null;
}

function terapkanTugasHitung(tugas) {
    const r = tugas?.ringkasan || {};
    Object.assign(progresHitung, {
        diproses: Number(r.sudah) || 0,
        total: Number(r.total) || 0,
        dihitung: Number(tugas?.dihitung) || 0,
        tertahan: Number(tugas?.tertahan) || 0,
        gagal: Number(tugas?.jumlah_galat) || 0,
    });
}

async function selesaiHitung(tugas) {
    menghitungSiklus.value = false;

    if (tugas?.status === 'failed') {
        errorMonitoring.value = tugas.galat || 'Perhitungan siklus berhenti.';
    } else if (progresHitung.gagal > 0) {
        /* Yang gagal DILAPORKAN, bukan ditelan. Hasil yang sudah TERBIT memang menolak
           dihitung ulang — admin perlu tahu berapa yang dilewati. */
        errorMonitoring.value = `${progresHitung.gagal} peserta gagal dihitung `
            + '(termasuk hasil yang sudah terbit dan menolak dihitung ulang).';
    }

    await muatDataMonitoring();
    if (tabAktif.value === 'HASIL') await muatPeserta();
}

async function bacaTugasHitung(cycleId) {
    hentikanPembacaHitung();
    siklusDibaca = cycleId;

    try {
        const { tugas } = (await paApi.statusHitungLatar(cycleId)) || {};
        // Siklus sudah diganti selagi menunggu jawaban: jawabannya milik siklus lama.
        if (siklusDibaca !== cycleId) return;

        if (!tugas) {
            menghitungSiklus.value = false;
            return;
        }

        terapkanTugasHitung(tugas);

        if (tugas.status === 'queued' || tugas.status === 'processing') {
            menghitungSiklus.value = true;
            pembacaHitung = setTimeout(() => bacaTugasHitung(cycleId), JEDA_BACA_HITUNG);
            return;
        }

        if (menghitungSiklus.value) await selesaiHitung(tugas);
    } catch (exception) {
        // Gangguan sesaat saat MEMBACA kemajuan tidak menghentikan tugasnya; coba baca lagi.
        if (siklusDibaca === cycleId && menghitungSiklus.value) {
            pembacaHitung = setTimeout(() => bacaTugasHitung(cycleId), JEDA_BACA_HITUNG * 2);
        }
    }
}

async function hitungSiklus(lingkup = 'SEMUA') {
    if (menghitungSiklus.value || !selectedCycleId.value) return;

    menghitungSiklus.value = true;
    errorMonitoring.value = '';
    Object.assign(progresHitung, { diproses: 0, total: 0, dihitung: 0, tertahan: 0, gagal: 0 });

    /* Dialognya ditutup SEBELUM pekerjaannya jalan: kemajuannya tampil di tombol toolbar. */
    dialogHitungTerbuka.value = false;

    const cycleId = selectedCycleId.value;

    try {
        const { tugas } = (await paApi.mulaiHitungLatar(cycleId, { lingkup })) || {};
        terapkanTugasHitung(tugas);
        pembacaHitung = setTimeout(() => bacaTugasHitung(cycleId), JEDA_BACA_HITUNG);
        siklusDibaca = cycleId;
    } catch (exception) {
        menghitungSiklus.value = false;
        errorMonitoring.value = exception?.message || 'Perhitungan siklus gagal dimulai.';
    }
}

const tableColumns = [
    { key: 'dinilai', label: 'Karyawan Dinilai', minWidth: '220px' },
    { key: 'penilai', label: 'Penilai (Rater)', minWidth: '200px' },
    { key: 'kategori', label: 'Kategori', width: '130px' },
    { key: 'divisi', label: 'Divisi', minWidth: '150px' },
    { key: 'status', label: 'Status Pengisian', width: '150px' },
    { key: 'waktu', label: 'Waktu / Tenggat', minWidth: '160px' },
    { key: 'pintu', label: 'Akses Rater', width: '150px' },
];

/* Muat ulang daftar siklus TANPA mengganti pilihan — dipakai sesudah status siklus berubah
   (mis. PUBLISHED) supaya badge status dan operasi_boleh ikut berubah. */
async function muatSiklusUlang() {
    try {
        const response = await paApi.daftarSiklus();
        const data = response?.result ?? response;
        siklusList.value = data?.siklus || siklusList.value;
    } catch {
        // daftar lama tetap dipakai
    }
    await muatDataMonitoring();
}

/* Siklus bawaan saat halaman dibuka tanpa ?cycle=. Daftar dari server diurut Tahun lalu
   Id, sehingga "yang pertama OPEN-atau-LOCKED" memilih siklus LOCKED 2026 (#11092) padahal
   pengisian yang sedang berjalan ada di siklus OPEN 2025 (#11089). Urutannya: OPEN, LOCKED,
   CLOSED, lainnya; di dalam status yang sama, periode dinilai yang berakhir paling akhir
   (padanan `PaPesertaService::URUTAN_PERIODE`: tanpa periode jatuh ke akhir tahunnya). */
const PRIORITAS_SIKLUS_BAWAAN = ['OPEN', 'LOCKED', 'CLOSED'];

function pilihSiklusBawaan(daftar) {
    const peringkat = (c) => {
        const i = PRIORITAS_SIKLUS_BAWAAN.indexOf(c.Status_Cycle);
        return i === -1 ? PRIORITAS_SIKLUS_BAWAAN.length : i;
    };
    const akhirPeriode = (c) => String(c.Periode_Dinilai_Sampai || (c.Tahun ? `${c.Tahun}-12-31` : '')).slice(0, 10);

    return [...daftar].sort((a, b) => peringkat(a) - peringkat(b)
        || akhirPeriode(b).localeCompare(akhirPeriode(a))
        || Number(b.Id_PA_Cycle) - Number(a.Id_PA_Cycle))[0];
}

async function muatSiklus() {
    loadingSiklus.value = true;
    try {
        const response = await paApi.daftarSiklus();
        const data = response?.result ?? response;
        siklusList.value = data?.siklus || [];

        const diminta = cycleDariUrl(siklusList.value);
        if (diminta) {
            selectedCycleId.value = diminta;
            await muatDataMonitoring();
        } else if (siklusList.value.length && !selectedCycleId.value) {
            const active = pilihSiklusBawaan(siklusList.value);
            selectedCycleId.value = active.Id_PA_Cycle;
            await muatDataMonitoring();
        }
    } catch (e) {
        errorMonitoring.value = e?.message || 'Gagal memuat siklus.';
    } finally {
        loadingSiklus.value = false;
    }
}

/* Chip saringan di layar TIDAK sama dengan status di basis data.
   Chip-nya ditulis untuk dibaca orang ("Belum mulai", "Berjalan", "Selesai"), statusnya
   ditulis untuk dicocokkan mesin. Terjemahannya di satu tempat ini; mengirim kode chip
   apa adanya membuat server menyaring ke himpunan kosong tanpa satu pun galat. */
function statusUntukServer() {
    const raw = typeof activeStatusFilter.value === 'object' && activeStatusFilter.value !== null
        ? (activeStatusFilter.value.value ?? '')
        : (activeStatusFilter.value || '');
    return {
        BELUM_SELESAI: 'BELUM_SELESAI',
        BELUM_MULAI: 'BELUM_MULAI',
        PENDING: 'BELUM_MULAI',
        BERJALAN: 'BERJALAN',
        TERKUMPUL: 'TERKUMPUL',
        SELESAI: 'SELESAI',
        DONE: 'SELESAI',
        MENUNGGU_VERIFIKASI: 'MENUNGGU_VERIFIKASI',
        TERLAMBAT: 'TERLAMBAT',
    }[raw] || '';
}

/* ------------------------------------------------------------------ *
 * Kiriman HC Learn yang perlu ditinjau                                 *
 * ------------------------------------------------------------------ */

const kirimanTinjau = ref([]);
const memuatKirimanTinjau = ref(false);
const memetakanUlang = ref(0);
const memetakanSemua = ref(false);
const progresPemetaanSemua = ref({ berjalan: 0, total: 0 });
const bukaDetailKiriman = ref(false);
const cariKirimanTinjau = ref('');
const saringanJenisKiriman = ref('ALL');
const halamanKirimanTinjau = ref(1);
const perHalamanKirimanTinjau = 8;

watch([cariKirimanTinjau, saringanJenisKiriman], () => {
    halamanKirimanTinjau.value = 1;
});

const filteredKirimanTinjau = computed(() => {
    let rows = kirimanTinjau.value || [];
    if (saringanJenisKiriman.value === 'TAK_TERPETAKAN') {
        rows = rows.filter((k) => (k.tak_terpetakan || []).length > 0);
    } else if (saringanJenisKiriman.value === 'BUTIR_HILANG') {
        rows = rows.filter((k) => (k.butir_hilang || []).length > 0);
    }
    const q = cariKirimanTinjau.value?.trim().toLowerCase();
    if (q) {
        rows = rows.filter((k) => {
            const penilai = (k.nama_penilai || '').toLowerCase();
            const kodePenilai = (k.kode_penilai || '').toLowerCase();
            const peserta = (k.nama_peserta || '').toLowerCase();
            const kodePeserta = (k.kode_peserta || '').toLowerCase();
            const saran = (k.saran || '').toLowerCase();
            const takDikenal = (k.tak_terpetakan || []).join(' ').toLowerCase();
            const hilang = (k.butir_hilang || []).join(' ').toLowerCase();
            return penilai.includes(q) || kodePenilai.includes(q) || peserta.includes(q) || kodePeserta.includes(q) || saran.includes(q) || takDikenal.includes(q) || hilang.includes(q);
        });
    }
    return rows;
});

const totalHalamanKirimanTinjau = computed(() => {
    return Math.max(1, Math.ceil(filteredKirimanTinjau.value.length / perHalamanKirimanTinjau));
});

const paginatedKirimanTinjau = computed(() => {
    const start = (halamanKirimanTinjau.value - 1) * perHalamanKirimanTinjau;
    return filteredKirimanTinjau.value.slice(start, start + perHalamanKirimanTinjau);
});

function gantiHalamanKiriman(arah) {
    const target = halamanKirimanTinjau.value + arah;
    if (target >= 1 && target <= totalHalamanKirimanTinjau.value) {
        halamanKirimanTinjau.value = target;
    }
}

async function petakanUlangSemua() {
    if (memetakanSemua.value || memetakanUlang.value || !kirimanTinjau.value.length) return;

    memetakanSemua.value = true;
    const total = kirimanTinjau.value.length;
    progresPemetaanSemua.value = { berjalan: 0, total };

    let berhasil = 0;
    let belumLengkap = 0;
    let gagal = 0;

    for (let i = 0; i < total; i++) {
        const k = kirimanTinjau.value[i];
        if (!k) continue;
        progresPemetaanSemua.value.berjalan = i + 1;
        try {
            const r = await paApi.petakanUlangKiriman(selectedCycleId.value, k.payload_id);
            const hasil = r?.result ?? r ?? {};
            if (hasil?.kontrak_lengkap) {
                berhasil++;
            } else {
                belumLengkap++;
            }
        } catch {
            gagal++;
        }
    }

    if (gagal === 0 && belumLengkap === 0) {
        hcisMessage.success(`Semua ${berhasil} kiriman berhasil dipetakan ke kompetensi.`);
    } else if (berhasil > 0) {
        hcisMessage.info(`Pemetaan selesai: ${berhasil} berhasil, ${belumLengkap} belum lengkap, ${gagal} gagal.`);
    } else {
        hcisMessage.warning('Pemetaan selesai, namun butir soal masih belum sesuai dengan kompetensi.');
    }

    memetakanSemua.value = false;
    await muatKirimanTinjau();
    await muatDataMonitoring();
}

/* DIMUAT TERPISAH dari monitoring, dan sengaja.
   |
   | Monitoring dimuat ulang tiap ganti halaman, tiap ganti saringan, tiap pencarian.
   | Menumpangkan daftar ini padanya berarti menghitung ulang seluruh kiriman siklus
   | setiap kali admin mengetik satu huruf di kotak cari. */
async function muatKirimanTinjau() {
    if (!selectedCycleId.value) return;

    memuatKirimanTinjau.value = true;

    try {
        const r = await paApi.kirimanPerluDitinjau(selectedCycleId.value);
        kirimanTinjau.value = (r?.result ?? r)?.baris ?? [];
    } catch {
        /* Gagal membacanya TIDAK menjatuhkan layar pemantauan: sisanya data kita sendiri.
           Daftarnya dikosongkan supaya tidak ada baris basi yang tertinggal di layar. */
        kirimanTinjau.value = [];
    } finally {
        memuatKirimanTinjau.value = false;
    }
}

async function petakanUlangKiriman(baris) {
    if (memetakanUlang.value) return;

    memetakanUlang.value = baris.payload_id;

    try {
        const r = await paApi.petakanUlangKiriman(selectedCycleId.value, baris.payload_id);
        const hasil = r?.result ?? r ?? {};

        if (hasil?.kontrak_lengkap) {
            hcisMessage.success(
                `Kiriman ${baris.nama_penilai} berhasil dipetakan: ${hasil.berskor} butir masuk ke kompetensi.`,
            );
        } else {
            /* Berhasil dipetakan TIDAK sama dengan lengkap. Menyebutnya sukses di sini
               membuat admin mengira pekerjaannya selesai padahal barisnya akan muncul
               lagi begitu daftarnya disegarkan. */
            hcisMessage.warning(
                `Kiriman ${baris.nama_penilai} masih belum lengkap: `
                + `${(hasil.tak_terpetakan || []).length} kode tak dikenali, `
                + `${(hasil.butir_hilang || []).length} butir tidak terkirim.`,
            );
        }

        await muatKirimanTinjau();
        await muatDataMonitoring();
    } catch (exception) {
        hcisMessage.error(exception?.message || 'Pemetaan ulang gagal.');
    } finally {
        memetakanUlang.value = 0;
    }
}

/* Nomor urut permintaan monitoring: hanya respons TERBARU yang boleh menulis ke layar.
   Tanpa ini, respons halaman lama yang tiba belakangan menimpa halaman yang sedang
   dipilih — paginasi menunjuk halaman 3 sementara tabel berisi halaman 2. */
let urutanMonitoring = 0;

async function muatDataMonitoring({ segarkanAlur = true } = {}) {
    if (!selectedCycleId.value) return;

    // Keadaan tahap ikut dimuat ulang setiap kali data siklus disegarkan (tanpa ditunggu).
    if (segarkanAlur) muatAlur();

    const nomor = ++urutanMonitoring;
    loadingMonitoring.value = true;
    errorMonitoring.value = '';
    errorStatus.value = 0;

    try {
        const response = await paApi.monitoringSiklus(selectedCycleId.value, {
            halaman: currentPage.value,
            per_halaman: perPage,
            q: searchQuery.value || '',
            id_divisi: selectedFilterValues.id_divisi || '',
            id_level: selectedFilterValues.id_level || '',
            pa_rater_category_id: selectedFilterValues.kategori || '',
            status: statusUntukServer(),
        });
        if (nomor !== urutanMonitoring) return;
        const data = response?.result ?? response ?? {};

        summaryData.value = data?.ringkasan || {};
        keranjang.value = data?.keranjang || null;
        heatmapData.value = data?.peta_panas || null;
        rawPilihanSaringan.value = data?.pilihan_saringan || null;

        if (data?.kategori && typeof data.kategori === 'object') {
            categoryBreakdown.value = Object.values(data.kategori).map((val) => ({
                id: val.id,
                kode: val.kode,
                nama: val.nama || val.kode,
                total: Number(val.total || 0),
                selesai: Number(val.selesai || 0),
                persen: Number(val.persen || 0),
            }));
        } else if (Array.isArray(data?.peta_panas)) {
            categoryBreakdown.value = data.peta_panas.map((item) => ({
                kode: item.Kode_Kategori || item.kode,
                nama: item.Nama_Kategori || item.nama || item.Kode_Kategori,
                total: Number(item.total || 0),
                selesai: Number(item.selesai || 0),
                persen: Number(item.persen || (item.total ? Math.round((item.selesai / item.total) * 100) : 0)),
            }));
        } else {
            categoryBreakdown.value = [];
        }

        const halamanPenugasan = data?.penugasan || {};
        const halamanPeserta = data?.peserta || {};
        totalBaris.value = Number(halamanPenugasan.total || 0);
        totalPeserta.value = Number(halamanPeserta.total || 0);
        totalHalamanServer.value = Number(halamanPeserta.total_halaman || 0);
        pesertaServer.value = Array.isArray(halamanPeserta.baris) ? halamanPeserta.baris : [];
        /* Baris penilai diratakan dari blok `peserta`: satu halaman = sekumpulan karyawan LENGKAP
           dengan penilainya, bukan potongan 20 penugasan yang memecah satu orang ke beberapa halaman. */
        const rows = pesertaServer.value.flatMap((p) => (p.penilai || []).map((r) => ({
            ...r,
            Id_PA_Participant: p.Id_PA_Participant,
            Kode_Karyawan: p.Kode_Karyawan,
            Nama_Karyawan_Saat_Cycle: p.Nama_Karyawan_Saat_Cycle,
            Nama_Divisi_Saat_Cycle: p.Nama_Divisi_Saat_Cycle,
            Nama_Level_Saat_Cycle: p.Nama_Level_Saat_Cycle,
        })));

        monitoringRows.value = rows.map((r, idx) => ({
            ...r,
            id_key: r.Id_PA_Assignment || r.Id_PA_Rater_Assignment || `${r.Id_PA_Participant || idx}-${r.Kode_Penilai || r.Kode_Karyawan_Penilai || idx}`,
            Nama_Karyawan_Yang_Dinilai: r.Nama_Karyawan_Yang_Dinilai || r.Nama_Karyawan_Saat_Cycle || r.Nama_Karyawan,
            Kode_Karyawan_Yang_Dinilai: r.Kode_Karyawan_Yang_Dinilai || r.Kode_Karyawan,
            Nama_Penilai: r.Nama_Penilai_Saat_Cycle || r.Nama_Penilai,
            Kode_Penilai: r.Kode_Karyawan_Penilai || r.Kode_Penilai,
            Kode_Kategori: r.Kode_Kategori,
            Kategori_Penilai: r.Nama_Kategori || r.Kode_Kategori || r.Kategori_Penilai,
            Nama_Divisi: r.Nama_Divisi_Saat_Cycle || r.Nama_Divisi,
            Nama_Level: r.Nama_Level_Saat_Cycle || r.Nama_Level,
        }));

        const terlambatData = data?.terlambat || {};
        overdueTotal.value = Number(terlambatData.total || 0);
        const overdueList = Array.isArray(terlambatData.baris) ? terlambatData.baris : [];
        overdueRows.value = overdueList.map((r, idx) => ({
            ...r,
            id_key: r.Id_PA_Assignment || `overdue-${idx}`,
            Nama_Karyawan_Yang_Dinilai: r.Nama_Karyawan_Yang_Dinilai || r.Nama_Karyawan_Saat_Cycle || r.Nama_Karyawan,
            Kode_Karyawan_Yang_Dinilai: r.Kode_Karyawan_Yang_Dinilai || r.Kode_Karyawan,
            Nama_Penilai: r.Nama_Penilai_Saat_Cycle || r.Nama_Penilai,
            Kode_Penilai: r.Kode_Karyawan_Penilai || r.Kode_Penilai,
            Kode_Kategori: r.Kode_Kategori,
            Kategori_Penilai: r.Nama_Kategori || r.Kode_Kategori || r.Kategori_Penilai,
            Nama_Divisi: r.Nama_Divisi_Saat_Cycle || r.Nama_Divisi,
            Nama_Level: r.Nama_Level_Saat_Cycle || r.Nama_Level,
            hari_terlambat: Number(r.hari_terlambat || 0),
        }));
        hasLoadedInitial.value = true;
    } catch (e) {
        if (nomor !== urutanMonitoring) return;
        errorMonitoring.value = e?.message || 'Gagal memuat data monitoring pengisian.';
        errorStatus.value = Number(e?.status || 500);
    } finally {
        // Hanya permintaan terbaru yang boleh mematikan penanda muat.
        if (nomor === urutanMonitoring) loadingMonitoring.value = false;
    }
}

/* TERKUMPUL: lembarnya terisi penuh, penilainya belum menekan kirim, dan karena itu ia
   BELUM ikut dihitung. Diperiksa terpisah karena `isRowSelesai` di bawah jatuh ke
   `Finished_At` - yang pada lembar terkumpul memang sudah terisi. */
function isRowTerkumpul(row) {
    return row?.Status_Penugasan === 'TERKUMPUL' || row?.Status === 'TERKUMPUL';
}

function bukaLepasPaksa(row) {
    lepasPaksa.kodePerusahaan = row?.Kode_Perusahaan_Penilai || row?.Kode_Perusahaan || '';
    lepasPaksa.kodeKaryawan = row?.Kode_Karyawan_Penilai || row?.Kode_Penilai || '';
    lepasPaksa.penilai = row?.Nama_Penilai_Saat_Cycle || row?.Nama_Penilai || '—';
    lepasPaksa.alasan = '';
    lepasPaksa.galat = '';
    lepasPaksa.terbuka = true;
}

async function kirimLepasPaksa() {
    if (lepasPaksa.mengirim) return;

    const alasan = (lepasPaksa.alasan || '').trim();

    if (alasan.length < 5) {
        lepasPaksa.galat = 'Alasannya perlu satu kalimat yang bisa dibaca orang lain kelak, bukan satu kata.';
        return;
    }

    if (!lepasPaksa.kodeKaryawan) {
        lepasPaksa.galat = 'Kode penilai tidak terbaca dari baris ini, jadi pelepasannya tidak bisa dialamatkan.';
        return;
    }

    lepasPaksa.mengirim = true;
    lepasPaksa.galat = '';

    try {
        const r = await paApi.lepasPaksaPenilai(selectedCycleId.value, {
            kodePerusahaan: lepasPaksa.kodePerusahaan,
            kodeKaryawan: lepasPaksa.kodeKaryawan,
            alasan,
        });
        const hasil = r?.result ?? r ?? {};

        lepasPaksa.terbuka = false;
        hcisMessage.success(
            `${hasil.dilepas || 0} penilaian dilepas; ${hasil.peserta_diantrekan || 0} peserta diantrekan untuk dihitung.`,
        );
        await muatDataMonitoring();
    } catch (e) {
        lepasPaksa.galat = e?.response?.data?.message || e?.message || 'Pelepasan gagal.';
    } finally {
        lepasPaksa.mengirim = false;
    }
}

function isRowSelesai(row) {
    // TERKUMPUL bukan selesai, dan pemeriksaannya harus DI DEPAN: baris terkumpul sudah
    // punya Finished_At, jadi tanpa penjaga ini ia lolos lewat cabang terakhir di bawah.
    if (isRowTerkumpul(row)) return false;

    return (
        row.Status_Penugasan === 'SELESAI' ||
        row.Status === 'SELESAI' ||
        row.status === 'SELESAI' ||
        Boolean(row.Waktu_Selesai) ||
        Boolean(row.Finished_At) ||
        Boolean(row.Submitted_At)
    );
}

/* Gerbang siklus `CETAK_ULANG_PINTU` (hanya sampai OPEN — siklus CLOSED dulu menampilkan
   1.125 tombol yang semuanya ditolak 422). Semua status selain dibatalkan: yang sudah
   selesai hanya bisa diulang dari awal, dan dialognya yang menentukan jalannya. */
function bisaAksesUlang(row) {
    if (!row?.Id_PA_Assignment) return false;
    if (!bolehOperasi('CETAK_ULANG_PINTU')) return false;
    const status = row.Status_Penugasan || row.Status || row.status || '';
    return status !== 'DIBATALKAN';
}

/* BELUM = belum mulai; SELESAI = sudah menyetor (server menolak cetak ulang untuknya);
   SEDANG = di antaranya — satu-satunya keadaan yang menawarkan pilihan. */
function kondisiAkses(row) {
    const status = row.Status_Penugasan || row.Status || row.status || 'BELUM_MULAI';
    if (isRowSelesai(row) || isRowTerkumpul(row) || status === 'MENUNGGU_VERIFIKASI') return 'SELESAI';
    return status === 'BELUM_MULAI' ? 'BELUM' : 'SEDANG';
}

function bukaAksesUlang(row) {
    aksesUlang.assignmentId = row.Id_PA_Assignment;
    aksesUlang.penilai = row.Nama_Penilai || row.Kode_Penilai || '';
    aksesUlang.dinilai = row.Nama_Karyawan_Yang_Dinilai || '';
    aksesUlang.kondisi = kondisiAkses(row);
    aksesUlang.pilihan = aksesUlang.kondisi === 'SELESAI' ? 'ULANG' : 'LANJUT';
    aksesUlang.alasan = '';
    aksesUlang.paham = false;
    aksesUlang.galat = '';
    aksesUlang.terbuka = true;
}

async function kirimAksesUlang() {
    if (aksesUlang.mengirim) return;
    const alasan = (aksesUlang.alasan || '').trim();
    if (alasan.length < 5) {
        aksesUlang.galat = 'Alasannya perlu satu kalimat yang bisa dibaca orang lain kelak, bukan satu kata.';
        return;
    }
    const ulang = aksesUlang.pilihan === 'ULANG';
    if (ulang && !aksesUlang.paham) return;
    aksesUlang.mengirim = true;
    aksesUlang.galat = '';
    try {
        if (ulang) {
            const r = await paApi.jadwalUlangPintu(aksesUlang.assignmentId, alasan);
            hcisMessage.success(r?.catatan || 'Penilaian dijadwalkan ulang dari awal.');
        } else {
            await paApi.cetakUlangPintu(aksesUlang.assignmentId, alasan);
            hcisMessage.success('Akses evaluasi rater baru berhasil diterbitkan.');
        }
        aksesUlang.terbuka = false;
        await muatDataMonitoring();
        perPenilaiRef.value?.segarkan?.();
    } catch (e) {
        aksesUlang.galat = e?.response?.data?.message
            || e?.message
            || (ulang ? 'Penilaian tidak bisa dijadwalkan ulang.' : 'Akses evaluasi rater tidak bisa diterbitkan ulang.');
    } finally {
        aksesUlang.mengirim = false;
    }
}

function onCycleChange() {
    if (selectedCycleId.value) {
        const url = new URL(window.location.href);
        url.searchParams.set('cycle', selectedCycleId.value);
        window.history.replaceState(null, '', url.toString());
    }
    currentPage.value = 1;
    hasLoadedInitial.value = false;
    alurPenutupan.value = null;
    // Kemajuan hitung milik siklus lama tidak boleh tetap tampil; tugas siklus baru disambung.
    hentikanPembacaHitung();
    menghitungSiklus.value = false;
    if (selectedCycleId.value) bacaTugasHitung(selectedCycleId.value);
    muatDataMonitoring();

    // Daftar hasil milik siklus lama tidak boleh tetap tampil di bawah siklus baru.
    pesertaHasil.value = [];
    if (tabAktif.value === 'HASIL') muatPeserta();

    /* Daftar kiriman bermasalah ikut berganti siklus, tetapi TIDAK ikut berganti halaman
       atau pencarian — ia milik siklusnya, bukan milik daftar yang sedang disaring. */
    muatKirimanTinjau();
}

function onSearchChange(val) {
    searchQuery.value = val;
    currentPage.value = 1;
    muatDataMonitoring();
}

function onStatusChipClick(chipOrVal) {
    const val = typeof chipOrVal === 'object' && chipOrVal !== null
        ? (chipOrVal.value ?? chipOrVal.id)
        : chipOrVal;
    activeStatusFilter.value = val || 'ALL';
    currentPage.value = 1;
    muatDataMonitoring();
}

function pilihSaringanStatus(status) {
    activeStatusFilter.value = status || 'ALL';
    currentPage.value = 1;
    muatDataMonitoring();
}

function onFilterChange({ key, value }) {
    selectedFilterValues[key] = value;
    currentPage.value = 1;
    muatDataMonitoring();
}

function toggleCategoryFilter(cat) {
    const catVal = String(cat.id || cat.kode);
    if (selectedFilterValues.kategori === catVal) {
        selectedFilterValues.kategori = '';
    } else {
        selectedFilterValues.kategori = catVal;
    }
    currentPage.value = 1;
    muatDataMonitoring();
}

function toggleFilterKategori(catVal) {
    const valStr = String(catVal || '');
    if (selectedFilterValues.kategori === valStr) {
        selectedFilterValues.kategori = '';
    } else {
        selectedFilterValues.kategori = valStr;
    }
    currentPage.value = 1;
    muatDataMonitoring();
}

function toggleDivisionFilter(div) {
    const divVal = String(div.id || '');
    if (selectedFilterValues.id_divisi === divVal) {
        selectedFilterValues.id_divisi = '';
    } else {
        selectedFilterValues.id_divisi = divVal;
    }
    currentPage.value = 1;
    muatDataMonitoring();
}

function resetFilters() {
    searchQuery.value = '';
    activeStatusFilter.value = 'ALL';
    selectedFilterValues.kategori = '';
    selectedFilterValues.id_divisi = '';
    selectedFilterValues.id_level = '';
    currentPage.value = 1;
    muatDataMonitoring();
}

function filterBelumSelesai() {
    activeStatusFilter.value = 'BELUM_SELESAI';
    currentPage.value = 1;
    muatDataMonitoring();
}

function saringOverdueDanKePengisian() {
    activeStatusFilter.value = 'TERLAMBAT';
    pilihTab('PENGISIAN');
    currentPage.value = 1;
    muatDataMonitoring();
}

function saringBelumSelesaiDanKePengisian() {
    activeStatusFilter.value = 'BELUM_SELESAI';
    pilihTab('PENGISIAN');
    currentPage.value = 1;
    muatDataMonitoring();
}

function bukaHalamanPenyiapan() {
    if (selectedCycleId.value) {
        window.location.href = `/performance-appraisal/siapkan/${selectedCycleId.value}`;
    }
}

async function pilihKeranjangDariRingkasan(jenis) {
    pilihTab('KERANJANG');
    await bukaKeranjang(jenis);
}

async function konfirmasiBukaPengisian() {
    if (!activeCycle.value) return;
    modePengisian.value = 'open';
    galatPengisian.value = '';
    galatPengisianStatus.value = 0;
    penjadwalanPengisian.value = null;
    dialogPengisian.value = true;
    try {
        penjadwalanPengisian.value = await paApi.ringkasanPenjadwalan(activeCycle.value.Id_PA_Cycle);
    } catch {
        // non-blocking
    }
}

function konfirmasiTutupPengisian() {
    if (!activeCycle.value) return;
    modePengisian.value = 'close';
    galatPengisian.value = '';
    galatPengisianStatus.value = 0;
    dialogPengisian.value = true;
}

async function eksekusiPengisian(payload = {}) {
    if (!selectedCycleId.value || actionLoading.value) return;
    actionLoading.value = true;
    galatPengisian.value = '';
    galatPengisianStatus.value = 0;
    try {
        const cycleKode = activeCycle.value?.Kode_Cycle || 'Cycle';
        if (modePengisian.value === 'open') {
            const res = await paApi.bukaPengisian(selectedCycleId.value, payload);
            if (activeCycle.value) activeCycle.value.Status_Cycle = 'OPEN';
            if (res?.dibuka_ulang) {
                hcisMessage.warning(`Pengisian cycle ${cycleKode} dibuka ulang. Hasil kalkulasi sebelumnya perlu dihitung ulang.`);
            } else {
                hcisMessage.success(`Pengisian cycle ${cycleKode} berhasil dibuka (Status: OPEN).`);
            }
        } else {
            const r = (await paApi.tutupPengisian(selectedCycleId.value)) || {};
            const h = r?.result ?? r;
            if (activeCycle.value) activeCycle.value.Status_Cycle = 'CLOSED';
            hcisMessage.success(
                `Pengisian ${cycleKode} ditutup. ${h.penilaian_dilepas ?? 0} lembar terisi dilepas otomatis, `
                + `${h.perhitungan_diantrekan ?? 0} peserta diantrekan untuk dihitung, `
                + `${h.penugasan_belum_selesai ?? 0} penugasan tidak terisi.`,
            );
        }
        dialogPengisian.value = false;
        await muatDataMonitoring();
    } catch (e) {
        galatPengisian.value = e?.response?.data?.message || e?.message || 'Gagal mengubah status pengisian cycle.';
        galatPengisianStatus.value = Number(e?.response?.status || e?.status || 500);
    } finally {
        actionLoading.value = false;
    }
}

async function jadwalkanPenilaian() {
    if (!selectedCycleId.value || sedangMenjadwalkan.value) return;
    sedangMenjadwalkan.value = true;
    try {
        const hasil = await paApi.jadwalkanPenilaian(selectedCycleId.value, 200);
        if (hasil?.ringkasan) {
            penjadwalanPengisian.value = hasil.ringkasan;
        } else {
            penjadwalanPengisian.value = await paApi.ringkasanPenjadwalan(selectedCycleId.value);
        }
    } catch (e) {
        hcisMessage.error(e?.message || 'Gagal menjadwalkan rater.');
    } finally {
        sedangMenjadwalkan.value = false;
    }
}

async function tarikHasilPenjadwalan(muatan) {
    const selesai = muatan?.selesai;

    if (!selectedCycleId.value) {
        selesai?.();

        return;
    }

    try {
        const hasil = await paApi.tarikHasilPenjadwalan(selectedCycleId.value);

        if (hasil?.ringkasan) {
            penjadwalanPengisian.value = hasil.ringkasan;
        }

        /* Angkanya DISEBUTKAN, termasuk yang masih antre. "Berhasil" tanpa angka membuat
           admin mengira semuanya sudah selesai lalu menutup layar. */
        hcisMessage.success(
            `${hasil?.terisi ?? 0} akses terisi`
            + ((hasil?.masih_antre ?? 0) > 0 ? `, ${hasil.masih_antre} masih digarap HC Learn` : '')
            + ((hasil?.gagal ?? 0) > 0 ? `, ${hasil.gagal} ditolak` : '') + '.',
        );
    } catch (e) {
        hcisMessage.error(e?.message || 'Gagal menarik hasil penjadwalan.');
    } finally {
        selesai?.();
    }
}

function onJadwalSelesai(ringkasan) {
    if (ringkasan) {
        penjadwalanPengisian.value = ringkasan;
    }
    void muatDataMonitoring();
}

function getStatusSentiment(status) {
    if (status === 'PUBLISHED') return 'success';
    if (status === 'CALCULATED') return 'purple';
    if (status === 'OPEN') return 'brand';
    if (status === 'LOCKED') return 'warning';
    if (status === 'CLOSED') return 'neutral';
    if (status === 'PREPARED') return 'brand';
    return 'neutral';
}

function getStatusCycleLabel(status, compact = false) {
    if (compact) {
        const compactMap = {
            'PUBLISHED': 'Terbit',
            'CALCULATED': 'Terkalkulasi',
            'LOCKED': 'Terkunci',
            'OPEN': 'Pengisian',
            'PREPARED': 'Penyiapan',
            'DRAFT': 'Draft',
            'CLOSED': 'Ditutup',
        };
        return compactMap[status] || status || 'Cycle';
    }
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
    if (tglDari && !tglSampai) return `Mulai ${formatDate(tglDari)}`;
    if (!tglDari && tglSampai) return `Hingga ${formatDate(tglSampai)}`;
    return `${formatDate(tglDari)} – ${formatDate(tglSampai)}`;
}

function getCategoryColor(kode) {
    const k = String(kode || '').toUpperCase();
    if (k.includes('ATASAN')) return 'indigo';
    if (k.includes('PEER') || k.includes('REKAN')) return 'teal';
    if (k.includes('BAWAHAN')) return 'cyan';
    if (k.includes('SELF') || k.includes('DIRI')) return 'amber';
    return 'indigo';
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    try {
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    } catch {
        return dateStr;
    }
}

function formatDateTime(dtStr) {
    if (!dtStr) return '—';
    try {
        const d = new Date(dtStr);
        if (isNaN(d.getTime())) return dtStr;
        return d.toLocaleString('id-ID', {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return dtStr;
    }
}

function formatAngka(num) {
    return Number(num || 0).toLocaleString('id-ID');
}

onMounted(async () => {
    if (typeof window !== 'undefined') {
        cekUkuranLayar();
        window.addEventListener('resize', cekUkuranLayar, { passive: true });
        if (window.innerWidth < 768) {
            viewMode.value = 'grid';
            viewModeHasil.value = 'grid';
        }
    }

    await muatSiklus();

    // Tugas hitung yang masih berjalan (mis. halaman dibuka ulang) disambung lagi.
    if (selectedCycleId.value) bacaTugasHitung(selectedCycleId.value);

    // Tahap dari alamat memuat isinya sendiri (mis. daftar hasil) — dulu ?tab=HASIL tampil kosong.
    if (selectedCycleId.value) pilihTab(tabAktif.value);

    /* Sesudah siklusnya diketahui, bukan sebelum. Dipanggil TANPA ditunggu: daftar ini
       pelengkap, dan layar pemantauan tidak boleh menunggu perhitungannya untuk tergambar. */
    muatKirimanTinjau();
});

onUnmounted(() => {
    hentikanPembacaHitung();
    siklusDibaca = null;
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', cekUkuranLayar);
    }
});
</script>

<style scoped>
.pa-kecualikan-body {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pa-kecualikan-kalimat {
    margin: 0;
    font-size: 0.875rem;
}

.pa-kecualikan-sebab {
    margin: 0;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.8rem;
}

/* Kiriman HC Learn yang Perlu Ditinjau - Card & Drawer */
.pa-kiriman-tinjau-card {
    border: 1px solid rgba(244, 63, 94, 0.3);
    border-radius: var(--hcis-radius-xl, 14px);
    background: linear-gradient(135deg, rgba(255, 241, 242, 0.75), #ffffff 50%, rgba(255, 241, 242, 0.5));
    box-shadow: 0 2px 10px -2px rgba(244, 63, 94, 0.08);
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.pa-kiriman-tinjau-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.25rem;
    padding: 1rem 1.25rem;
    flex-wrap: wrap;
}

.pa-kiriman-tinjau-header__main {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    flex: 1;
    min-width: 0;
    max-width: 100%;
}

.pa-kiriman-icon-box {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(244, 63, 94, 0.12);
    color: #e11d48;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-kiriman-tinjau-header__text {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    min-width: 0;
}

.pa-kiriman-title-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-kiriman-title-row strong {
    font-size: 0.92rem;
    font-weight: 800;
    color: #9f1239;
}

.pa-kiriman-tinjau-desc {
    margin: 0;
    font-size: 0.76rem;
    color: #475569;
    line-height: 1.45;
}

.pa-kiriman-tinjau-desc strong {
    color: #0f172a;
}

.pa-kiriman-tinjau-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-self: center;
}

.pa-kiriman-drawer {
    border-top: 1px solid rgba(244, 63, 94, 0.18);
    background: #ffffff;
    padding: 1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-kiriman-drawer-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.pa-kiriman-search-wrap {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
    max-width: 320px;
}

.pa-kiriman-search-icon {
    position: absolute;
    left: 0.65rem;
    color: #94a3b8;
    pointer-events: none;
}

.pa-kiriman-search-input {
    width: 100%;
    height: 2rem;
    padding: 0 1.8rem 0 1.8rem;
    font-size: 0.76rem;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    background: #f8fafc;
    color: #0f172a;
    transition: all 0.15s;
}

.pa-kiriman-search-input:focus {
    outline: none;
    border-color: #f43f5e;
    box-shadow: 0 0 0 2px rgba(244, 63, 94, 0.15);
    background: #ffffff;
}

.pa-kiriman-search-clear {
    position: absolute;
    right: 0.5rem;
    background: none;
    border: none;
    cursor: pointer;
    color: #94a3b8;
    padding: 0.1rem;
}

.pa-kiriman-search-clear:hover {
    color: #ef4444;
}

.pa-kiriman-filter-pills {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-kiriman-filter-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #64748b;
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
}

.pa-kiriman-filter-pill:hover {
    background: #f1f5f9;
    color: #1e293b;
    border-color: #cbd5e1;
}

.pa-kiriman-filter-pill.is-active {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
}

.pa-kiriman-filter-pill.is-danger.is-active {
    background: #e11d48;
    color: #ffffff;
    border-color: #e11d48;
}

.pa-kiriman-filter-pill.is-warning.is-active {
    background: #d97706;
    color: #ffffff;
    border-color: #d97706;
}

.pa-kiriman-table-container {
    border: 1px solid #f1f5f9;
    border-radius: var(--hcis-radius-md, 10px);
    overflow-x: auto;
    background: #ffffff;
}

.pa-kiriman-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.76rem;
    text-align: left;
}

.pa-kiriman-table th {
    padding: 0.65rem 0.85rem;
    background: #f8fafc;
    color: #64748b;
    font-size: 0.7rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}

.pa-kiriman-tr {
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s;
}

.pa-kiriman-tr:hover {
    background: rgba(248, 250, 252, 0.75);
}

.pa-kiriman-tr td {
    padding: 0.65rem 0.85rem;
    vertical-align: middle;
}

.pa-kiriman-target-box {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.pa-kiriman-target-box strong {
    color: #0f172a;
    font-weight: 700;
}

.pa-kiriman-target-box small {
    color: #64748b;
}

.pa-kiriman-time-meta {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.68rem;
    color: #94a3b8;
    margin-top: 0.15rem;
}

.pa-kiriman-issue-box {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.pa-kiriman-saran-text {
    margin: 0;
    font-size: 0.74rem;
    color: #334155;
    line-height: 1.4;
}

.pa-kiriman-tags-group {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
    margin-top: 0.15rem;
}

.pa-kiriman-tag-label {
    font-size: 0.68rem;
    font-weight: 700;
}

.pa-code-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.05rem 0.4rem;
    border-radius: 4px;
    font-family: ui-monospace, monospace;
    font-size: 0.68rem;
    font-weight: 700;
}

.pa-code-pill.is-tak-dikenal {
    background: #ffe4e6;
    color: #be123c;
    border: 1px solid #fecdd3;
}

.pa-code-pill.is-butir-hilang {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
}

.pa-kiriman-empty-search {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 2rem 1rem;
    color: #64748b;
    font-size: 0.78rem;
}

.pa-kiriman-drawer-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding-top: 0.4rem;
    flex-wrap: wrap;
}

.pa-kiriman-page-info {
    font-size: 0.72rem;
    color: #64748b;
}

.pa-kiriman-page-info strong {
    color: #0f172a;
}

.pa-kiriman-page-nav {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.pa-kiriman-nav-btn {
    width: 1.65rem;
    height: 1.65rem;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s;
}

.pa-kiriman-nav-btn:hover:not(:disabled) {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.pa-kiriman-nav-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.pa-kiriman-nav-counter {
    font-size: 0.72rem;
    font-weight: 600;
    color: #475569;
    padding: 0 0.35rem;
}

.pa-pemantauan-page {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    padding: 0 0 5rem 0;
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
}

.pa-main-header {
    margin-bottom: 0;
}

.pa-pemantauan-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* ------------------------------------------------------------------ */
/* Period Selector Trigger Jewel Button (Aligned with PA Tim Standard) */
/* ------------------------------------------------------------------ */
.pa-period-trigger {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.35rem 0.85rem 0.35rem 0.45rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.95);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    cursor: pointer;
    transition: all 160ms ease;
    text-align: left;
    outline: none;
    max-width: 380px;
    min-width: 0;
    flex: 1 1 auto;
    user-select: none;
}

.pa-period-trigger:hover:not(:disabled) {
    background: #ffffff;
    border-color: var(--hcis-brand-500, #6366f1);
    box-shadow: 0 4px 14px rgb(var(--hcis-brand-500-rgb, 99 102 241) / 0.15);
    transform: translateY(-1px);
}

.pa-period-trigger.is-loading {
    border-color: rgba(99, 102, 241, 0.4);
    background: rgba(238, 242, 255, 0.9);
    cursor: wait;
}

.pa-period-trigger__syncing {
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    background: var(--hcis-brand-600, #4f46e5);
    color: #ffffff;
    font-weight: 800;
    font-size: 0.58rem;
    letter-spacing: 0.04em;
}

.pa-spin-icon {
    animation: pa-spin 0.85s linear infinite;
}

@keyframes pa-spin {
    100% { transform: rotate(360deg); }
}

.pa-period-trigger__jewel {
    display: grid;
    place-items: center;
    width: 2rem;
    height: 2rem;
    border-radius: 0.6rem;
    background: linear-gradient(145deg, #eef2ff, #e0e7ff);
    color: #4338ca;
    flex-shrink: 0;
    transition: all 160ms ease;
}

.pa-period-trigger:hover:not(:disabled) .pa-period-trigger__jewel {
    background: var(--hcis-brand-600, #4f46e5);
    color: #ffffff;
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
    font-weight: 800;
    color: var(--hcis-brand-700, #4338ca);
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.pa-period-trigger__year {
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    background: rgba(99, 102, 241, 0.1);
    font-weight: 750;
    font-size: 0.6rem;
    color: #4338ca;
}

.pa-period-trigger__value {
    font-size: 0.78rem;
    font-weight: 750;
    color: var(--hcis-slate-800, #1e293b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-period-trigger__chevron {
    color: var(--hcis-slate-400, #94a3b8);
    display: grid;
    place-items: center;
    margin-left: 0.15rem;
}

/* ------------------------------------------------------------------ */
/* Header Console Strip (Aligned with PA Tim Standard) */
/* ------------------------------------------------------------------ */
.pa-pemantauan-header-console {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    min-width: 0;
    width: 100%;
}

.pa-pemantauan-header-console__identity {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
    flex: 1 1 auto;
}

.pa-pemantauan-header-console__context {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
    flex-shrink: 0;
}

.pa-pemantauan-header-console__avatar {
    display: grid;
    flex: 0 0 2.35rem;
    place-items: center;
    width: 2.35rem;
    height: 2.35rem;
    border: 1px solid rgba(99, 102, 241, 0.22);
    border-radius: 0.8rem;
    background: linear-gradient(145deg, #eef2ff, #e0e7ff);
    color: #4338ca;
    font-size: 0.75rem;
    font-weight: 800;
}

.pa-pemantauan-header-console__name-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
    min-width: 0;
}

.pa-pemantauan-header-console small {
    color: var(--hcis-slate-500, #64748b);
    font-size: 0.72rem;
}

.pa-pemantauan-header-console strong {
    overflow: hidden;
    color: var(--hcis-slate-800, #1e293b);
    font-size: 0.84rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pa-console-code-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.18rem 0.55rem;
    border-radius: var(--hcis-radius-sm, 6px);
    background: rgba(241, 245, 249, 0.95);
    border: 1px solid rgba(203, 213, 225, 0.85);
    color: var(--hcis-slate-700, #334155);
    font-family: var(--hcis-font-mono, ui-monospace, monospace);
    font-size: 0.72rem;
    font-weight: 750;
    line-height: 1.2;
    white-space: nowrap;
    letter-spacing: 0.02em;
}

.pa-console-countdown-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.18rem 0.6rem;
    border-radius: var(--hcis-radius-full, 9999px);
    font-size: 0.72rem;
    font-weight: 750;
    line-height: 1.2;
    white-space: nowrap;
}

.pa-console-countdown-pill.is-brand {
    background: rgba(99, 102, 241, 0.08);
    color: #4338ca;
    border: 1px solid rgba(99, 102, 241, 0.22);
}

.pa-console-countdown-pill.is-warning {
    background: rgba(245, 158, 11, 0.1);
    color: #b45309;
    border: 1px solid rgba(245, 158, 11, 0.25);
}

.pa-console-countdown-pill.is-danger {
    background: rgba(239, 68, 68, 0.1);
    color: #b91c1c;
    border: 1px solid rgba(239, 68, 68, 0.25);
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
    border-color: #7c3aed;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15);
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
    color: #7c3aed;
    background: rgba(255, 255, 255, 0.8);
}

.pa-filter-tab--active {
    background: #ffffff;
    color: #7c3aed;
    border-color: rgba(124, 58, 237, 0.3);
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
    background: rgba(124, 58, 237, 0.15);
    color: #6d28d9;
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
    border-color: #c4b5fd;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.1), 0 1px 3px rgba(15, 23, 42, 0.06);
    transform: translateY(-2px);
}

.pa-cycle-card--selected {
    background: linear-gradient(180deg, #ffffff 0%, #faf5ff 100%);
    border-color: #7c3aed;
    box-shadow: 0 0 0 1.5px #7c3aed, 0 4px 14px rgba(124, 58, 237, 0.16);
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
    background: rgba(124, 58, 237, 0.1);
    color: #7c3aed;
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
    background: #f3e8ff;
    color: #6b21a8;
    font-size: 0.72rem;
    font-weight: 750;
}

.pa-cycle-card__select-action {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: #7c3aed;
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

/* ==========================================================================
   TAB 1: EXECUTIVE COCKPIT & RINGKASAN SIKLUS AKTIF
   ========================================================================== */
.pa-tab-ringkasan-wrap {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.pa-cycle-hero-card {
    border-radius: var(--hcis-radius-xl, 16px);
    overflow: hidden;
}

.pa-cycle-hero-title-group {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-cycle-hero-kicker {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: var(--hcis-brand-600, #4f46e5);
}

.pa-cycle-hero-name-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.pa-cycle-hero-name {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 850;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.2;
}

.pa-cycle-code-pill {
    font-family: var(--hcis-font-mono, ui-monospace, monospace);
    font-size: 0.72rem;
    font-weight: 750;
    color: #4338ca;
    background: #eef2ff;
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
    border: 1px solid #e0e7ff;
}

.pa-cycle-badge-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-cycle-hero-desc {
    margin: 0;
    font-size: 0.78rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.45;
}

/* 3-Column Grid for Executive Cockpit */
.pa-cockpit-grid {
    display: grid;
    grid-template-columns: minmax(320px, 360px) minmax(280px, 1fr) minmax(320px, 360px);
    gap: 1.25rem;
    align-items: stretch;
}

/* 1. Large Radial Progress Gauge Box */
.pa-cockpit-gauge-box {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding: 0.4rem 1.15rem 0.4rem 0.2rem;
    border-right: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-gauge-visual-wrap {
    position: relative;
    width: 148px;
    height: 148px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pa-gauge-info {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    min-width: 0;
}

.pa-gauge-cycle-title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.pa-gauge-cycle-title strong {
    font-size: 0.88rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-gauge-ratio-text {
    font-size: 0.74rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.45;
}

.pa-gauge-ratio-text strong {
    color: var(--hcis-text-strong, #0f172a);
    font-variant-numeric: tabular-nums;
}

.pa-gauge-status-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.2rem;
}

/* 2. Timeline & Schedule Cards Pane */
.pa-cycle-dates-pane {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    justify-content: center;
}

.pa-cycle-date-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.75rem 0.95rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    transition: all 150ms ease;
}

.pa-cycle-date-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.pa-cycle-date-icon {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-cycle-date-icon--brand {
    background: rgba(79, 70, 229, 0.1);
    color: #4f46e5;
}

.pa-cycle-date-icon--amber {
    background: rgba(245, 158, 11, 0.1);
    color: #d97706;
}

.pa-cycle-date-content {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
    flex: 1;
}

.pa-cycle-date-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
}

.pa-cycle-date-label {
    font-size: 0.72rem;
    font-weight: 750;
    color: var(--hcis-text-muted, #64748b);
}

.pa-cycle-date-val {
    font-size: 0.86rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
    font-variant-numeric: tabular-nums;
}

.pa-cycle-date-sub {
    font-size: 0.68rem;
    color: #94a3b8;
}

/* 3. Integrated 4 Compact Stat Nodes */
.pa-cockpit-stats-strip {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem;
    align-content: center;
}

.pa-stat-node {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.65rem 0.8rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    transition: all 150ms ease;
}

.pa-stat-node:hover {
    border-color: rgba(203, 213, 225, 0.9);
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(15, 23, 42, 0.04);
}

.pa-stat-node--interactive {
    cursor: pointer;
    user-select: none;
}

.pa-stat-node--interactive:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.08);
}

.pa-stat-node--interactive:focus-visible {
    outline: none;
    border-color: var(--hcis-brand-600, #4f46e5);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}

.pa-stat-node__icon-box {
    width: 2.1rem;
    height: 2.1rem;
    border-radius: var(--hcis-radius-sm, 6px);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-stat-node--brand .pa-stat-node__icon-box {
    background: rgba(79, 70, 229, 0.1);
    color: #4f46e5;
}
.pa-stat-node--brand .pa-stat-node__value {
    color: #4338ca;
}

.pa-stat-node--success .pa-stat-node__icon-box {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
}
.pa-stat-node--success .pa-stat-node__value {
    color: #047857;
}

.pa-stat-node--warning .pa-stat-node__icon-box {
    background: rgba(245, 158, 11, 0.1);
    color: #d97706;
}
.pa-stat-node--warning .pa-stat-node__value {
    color: #b45309;
}

.pa-stat-node--danger .pa-stat-node__icon-box {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
}
.pa-stat-node--danger .pa-stat-node__value {
    color: #b91c1c;
}

.pa-stat-node--neutral .pa-stat-node__icon-box {
    background: #f1f5f9;
    color: #64748b;
}

.pa-stat-node__content {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pa-stat-node__label {
    font-size: 0.68rem;
    font-weight: 650;
    color: var(--hcis-text-muted, #64748b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-stat-node__value {
    font-size: 1.15rem;
    font-weight: 850;
    font-variant-numeric: tabular-nums;
    line-height: 1.2;
}

.pa-stat-node__sub {
    font-size: 0.64rem;
    color: var(--hcis-text-muted, #94a3b8);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ==========================================================================
   TAB 1: CARDS LAINNYA (BASKETS, CHARTS, LIFECYCLE)
   ========================================================================== */
.pa-baskets-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.85rem;
}

.pa-basket-tile {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.6rem;
    padding: 0.9rem 1rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    cursor: pointer;
    transition: all 160ms cubic-bezier(0.4, 0, 0.2, 1);
    user-select: none;
}

.pa-basket-tile:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 14px -2px rgba(15, 23, 42, 0.08);
    border-color: rgba(203, 213, 225, 0.95);
}

.pa-basket-tile__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pa-basket-tile__icon {
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-basket-tile.is-danger .pa-basket-tile__icon { background: rgba(239, 68, 68, 0.12); color: #dc2626; }
.pa-basket-tile.is-danger .pa-basket-tile__count { color: #dc2626; }

.pa-basket-tile.is-warning .pa-basket-tile__icon,
.pa-basket-tile.is-amber .pa-basket-tile__icon { background: rgba(245, 158, 11, 0.12); color: #d97706; }
.pa-basket-tile.is-warning .pa-basket-tile__count,
.pa-basket-tile.is-amber .pa-basket-tile__count { color: #d97706; }

.pa-basket-tile.is-purple .pa-basket-tile__icon { background: rgba(168, 85, 247, 0.12); color: #9333ea; }
.pa-basket-tile.is-purple .pa-basket-tile__count { color: #9333ea; }

.pa-basket-tile.is-brand .pa-basket-tile__icon { background: rgba(79, 70, 229, 0.12); color: #4f46e5; }
.pa-basket-tile.is-brand .pa-basket-tile__count { color: #4338ca; }

.pa-basket-tile.is-empty {
    opacity: 0.72;
    background: #f8fafc;
}

.pa-basket-tile.is-empty:hover {
    opacity: 1;
    background: #ffffff;
}

.pa-basket-tile__count {
    font-size: 1.45rem;
    font-weight: 850;
    font-variant-numeric: tabular-nums;
    line-height: 1;
}

.pa-basket-tile__body {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-basket-tile__label {
    font-size: 0.82rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-basket-tile__desc {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.35;
}

.pa-basket-tile__action {
    font-size: 0.72rem;
    font-weight: 700;
    color: #4f46e5;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    margin-top: 0.2rem;
}

.pa-lifecycle-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.85rem;
}

.pa-lifecycle-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 1rem 1.1rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-left: 3.5px solid #cbd5e1;
    cursor: pointer;
    transition: all 160ms ease;
    user-select: none;
}

.pa-lifecycle-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 14px -2px rgba(15, 23, 42, 0.08);
    border-color: #818cf8;
}

.pa-lifecycle-card.is-berjalan {
    border-color: rgba(199, 210, 254, 0.9);
    border-left: 3.5px solid #4f46e5;
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.45), #ffffff);
    box-shadow: 0 2px 8px -2px rgba(79, 70, 229, 0.08);
}

.pa-lifecycle-card.is-berjalan .pa-lifecycle-num {
    color: #4f46e5;
}

.pa-lifecycle-card.is-selesai {
    border-color: rgba(167, 243, 208, 0.85);
    border-left: 3.5px solid #10b981;
    background: linear-gradient(135deg, rgba(236, 253, 245, 0.35), #ffffff);
}

.pa-lifecycle-card.is-selesai .pa-lifecycle-num {
    color: #10b981;
}

.pa-lifecycle-card__hambat {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin: 0.35rem 0 0 0;
    font-size: 0.68rem;
    font-weight: 750;
    color: #d97706;
    background: rgba(245, 158, 11, 0.12);
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    width: fit-content;
}

.pa-lifecycle-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pa-lifecycle-num {
    font-size: 1.15rem;
    font-weight: 850;
    color: #c7d2fe;
    font-family: var(--hcis-font-mono, ui-monospace, monospace);
}

.pa-lifecycle-card:hover .pa-lifecycle-num {
    color: #6366f1;
}

.pa-lifecycle-card__body {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-lifecycle-card__title {
    margin: 0;
    font-size: 0.88rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-lifecycle-card__desc {
    margin: 0;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.45;
}

.pa-lifecycle-card__foot {
    font-size: 0.72rem;
    font-weight: 750;
    color: #4f46e5;
}

.pa-chart-actions-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-segmented-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    background: #f1f5f9;
    padding: 0.25rem;
    border-radius: var(--hcis-radius-md, 8px);
}

.pa-segmented-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.7rem;
    border: none;
    background: transparent;
    border-radius: var(--hcis-radius-sm, 6px);
    font: inherit;
    font-size: 0.74rem;
    font-weight: 650;
    color: var(--hcis-text-muted, #64748b);
    cursor: pointer;
    transition: all 120ms ease;
}

.pa-segmented-btn:hover {
    color: var(--hcis-text-strong, #0f172a);
    background: rgba(255, 255, 255, 0.6);
}

.pa-segmented-btn.active {
    background: #ffffff;
    color: #4338ca;
    font-weight: 750;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.pa-chart-insight-bar {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    padding: 0.75rem 0.95rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.75), rgba(248, 250, 252, 0.9));
    border: 1px solid rgba(199, 210, 254, 0.75);
    margin-bottom: 0.85rem;
}

.pa-chart-insight-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    width: 100%;
}

.pa-chart-insight-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #4338ca;
    background: rgba(79, 70, 229, 0.12);
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    flex-shrink: 0;
}

.pa-chart-insight-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.68rem;
    font-weight: 600;
    color: #64748b;
    margin-left: auto;
}

.pa-chart-insight-text {
    margin: 0;
    font-size: 0.78rem;
    color: #1e293b;
    font-weight: 600;
    line-height: 1.45;
}

.pa-chart-footer-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    width: 100%;
    flex-wrap: wrap;
}

.pa-chart-legend-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-chart-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: #475569;
    background: #f1f5f9;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-chart-legend-dot {
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 999px;
    flex-shrink: 0;
}

.pa-chart-legend-dot--progress {
    background: #4f46e5;
}

.pa-chart-legend-dot--target {
    background: #10b981;
}

/* 3. Multi-Perspective Breakdown Mini Meter */
.pa-cockpit-breakdown-box {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    padding-left: 1.15rem;
    border-left: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-breakdown-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-breakdown-title {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.76rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-breakdown-toggle-group {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    background: #f1f5f9;
    padding: 0.15rem;
    border-radius: var(--hcis-radius-sm, 6px);
}

.pa-mini-toggle-btn {
    border: 0;
    background: transparent;
    padding: 0.2rem 0.45rem;
    font: inherit;
    font-size: 0.66rem;
    font-weight: 650;
    color: var(--hcis-text-muted, #64748b);
    border-radius: 4px;
    cursor: pointer;
    transition: all 120ms ease;
}

.pa-mini-toggle-btn.active {
    background: #ffffff;
    color: #4338ca;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
}

.pa-mini-bars-list,
.pa-mini-division-list {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    max-height: 150px;
    overflow-y: auto;
    padding-right: 0.2rem;
}

.pa-mini-bar-item,
.pa-mini-div-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.45rem 0.6rem;
    border-radius: var(--hcis-radius-sm, 6px);
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.8);
    cursor: pointer;
    transition: all 120ms ease;
}

.pa-mini-bar-item:hover,
.pa-mini-div-item:hover {
    border-color: #818cf8;
    background: #f8fafc;
}

.pa-mini-bar-item.is-selected,
.pa-mini-div-item.is-selected {
    border-color: #4f46e5;
    background: #f5f3ff;
    box-shadow: 0 0 0 1px #4f46e5;
}

.pa-mini-bar-meta,
.pa-mini-div-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.72rem;
}

.pa-mini-bar-name {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-weight: 650;
    color: var(--hcis-text-strong, #1e293b);
}

.pa-mini-div-name {
    font-weight: 650;
    color: var(--hcis-text-strong, #1e293b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 140px;
}

.pa-mini-bar-pct,
.pa-mini-div-pct {
    font-size: 0.7rem;
    font-weight: 850;
    font-variant-numeric: tabular-nums;
}

.pa-mini-track {
    height: 0.35rem;
    border-radius: 999px;
    background: #f1f5f9;
    overflow: hidden;
}

.pa-mini-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 200ms ease;
}

.pa-mini-bar-foot,
.pa-mini-div-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.62rem;
    font-variant-numeric: tabular-nums;
    color: var(--hcis-text-muted, #64748b);
}

/* Category colors */
.pa-cat-dot {
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 999px;
}
.pa-cat-dot--indigo { background: #4f46e5; }
.pa-cat-dot--teal { background: #0d9488; }
.pa-cat-dot--cyan { background: #0284c7; }
.pa-cat-dot--amber { background: #d97706; }

.pa-cat-bar--indigo { background: linear-gradient(90deg, #4f46e5, #6366f1); }
.pa-cat-bar--teal { background: linear-gradient(90deg, #0d9488, #14b8a6); }
.pa-cat-bar--cyan { background: linear-gradient(90deg, #0284c7, #38bdf8); }
.pa-cat-bar--amber { background: linear-gradient(90deg, #d97706, #f59e0b); }

.text-indigo { color: #4f46e5; }
.text-teal { color: #0d9488; }
.text-cyan { color: #0284c7; }
.text-amber { color: #d97706; }

.pa-cat-pending-text {
    color: #d97706;
    font-weight: 650;
}

/* ==========================================================================
   TAB 3: ANTREAN TINDAK LANJUT ADMIN (Keranjang Keputusan)
   ========================================================================== */
.pa-tab-keranjang-wrap {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* 1. Banner & Hero Styles */
.pa-keranjang-banner,
.pa-hasil-banner {
    width: 100%;
}

.pa-hasil-banner-meta {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.78rem;
    color: var(--hcis-text-muted, #64748b);
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.pa-keranjang-hero-card {
    border-radius: var(--hcis-radius-lg, 14px);
    overflow: hidden;
}

.pa-keranjang-hero-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
}

.pa-keranjang-hero-info {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    flex: 1;
    min-width: 0;
}

.pa-keranjang-hero-title-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.pa-hero-icon-box {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: var(--hcis-radius-sm, 8px);
    background: rgba(79, 70, 229, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-keranjang-hero-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 850;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.2;
}

.pa-keranjang-hero-desc {
    margin: 0;
    font-size: 0.76rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.45;
    max-width: 58rem;
}

.pa-keranjang-hero-stats {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-shrink: 0;
}

.pa-stat-pill {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.1rem;
    padding: 0.6rem 0.95rem;
    border-radius: var(--hcis-radius-md, 10px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: #ffffff;
    min-width: 6.25rem;
    text-align: center;
    transition: transform 140ms ease, box-shadow 140ms ease;
}

.pa-stat-pill:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px -2px rgba(15, 23, 42, 0.06);
}

.pa-stat-pill__num {
    font-size: 1.35rem;
    font-weight: 850;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}

.pa-stat-pill__label {
    font-size: 0.64rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--hcis-text-muted, #64748b);
}

.pa-stat-pill.is-danger {
    background: linear-gradient(135deg, rgba(254, 242, 242, 0.7), rgba(255, 255, 255, 0.9));
    border-color: rgba(254, 202, 202, 0.9);
}
.pa-stat-pill.is-danger .pa-stat-pill__num { color: #dc2626; }

.pa-stat-pill.is-warning {
    background: linear-gradient(135deg, rgba(254, 243, 199, 0.7), rgba(255, 255, 255, 0.9));
    border-color: rgba(254, 243, 199, 0.9);
}
.pa-stat-pill.is-warning .pa-stat-pill__num { color: #d97706; }

.pa-stat-pill.is-amber {
    background: linear-gradient(135deg, rgba(254, 243, 199, 0.7), rgba(255, 255, 255, 0.9));
    border-color: rgba(254, 243, 199, 0.9);
}
.pa-stat-pill.is-amber .pa-stat-pill__num { color: #b45309; }

.pa-stat-pill.is-purple {
    background: linear-gradient(135deg, rgba(250, 245, 255, 0.7), rgba(255, 255, 255, 0.9));
    border-color: rgba(233, 213, 255, 0.9);
}
.pa-stat-pill.is-purple .pa-stat-pill__num { color: #9333ea; }

.pa-stat-pill.is-brand {
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.7), rgba(255, 255, 255, 0.9));
    border-color: rgba(199, 210, 254, 0.9);
}
.pa-stat-pill.is-brand .pa-stat-pill__num { color: #4338ca; }

.pa-stat-pill.is-success {
    background: linear-gradient(135deg, rgba(240, 253, 244, 0.7), rgba(255, 255, 255, 0.9));
    border-color: rgba(187, 247, 208, 0.9);
}
.pa-stat-pill.is-success .pa-stat-pill__num { color: #16a34a; }

.pa-stat-pill.is-muted {
    background: #f8fafc;
    border-color: #e2e8f0;
    opacity: 0.75;
}
.pa-stat-pill.is-muted .pa-stat-pill__num { color: #64748b; }

/* ==========================================================================
   TAB 3: ANTREAN TINDAK LANJUT ADMIN (SUB-TABS & COMPACT STRIPS)
   ========================================================================== */
.pa-tab-keranjang-wrap {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
}

.pa-queue-subtab-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.pa-queue-subtab-left {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
}

.pa-queue-subtab-pill-group {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f1f5f9;
}

.pa-queue-subtab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.35rem 0.75rem;
    border: 1px solid transparent;
    border-radius: var(--hcis-radius-sm, 6px);
    background: transparent;
    font: inherit;
    color: var(--hcis-text-muted, #64748b);
    cursor: pointer;
    white-space: nowrap;
    transition: all 140ms ease;
}

.pa-queue-subtab-btn:hover {
    color: var(--hcis-text-strong, #0f172a);
    background: rgba(255, 255, 255, 0.6);
}

.pa-queue-subtab-btn.is-active {
    background: #ffffff;
    color: var(--hcis-text-strong, #0f172a);
    border-color: rgba(203, 213, 225, 0.9);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.pa-queue-subtab-label {
    font-size: 0.76rem;
    font-weight: 700;
}

.pa-queue-subtab-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.25rem;
    height: 1.15rem;
    padding: 0 0.35rem;
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

.pa-queue-subtab-badge.is-danger {
    background: #fee2e2;
    color: #b91c1c;
}

.pa-queue-subtab-badge.is-amber {
    background: #fef3c7;
    color: #b45309;
}

.pa-queue-subtab-badge.is-muted {
    background: #e2e8f0;
    color: #64748b;
}

.pa-queue-subtab-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Compact Horizontal Queue Selector Strips */
.pa-compact-queue-strip {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.75rem;
}

.pa-compact-queue-strip--dual {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.pa-queue-strip-note {
    display: flex;
    align-items: flex-start;
    gap: 0.375rem;
    margin: 0;
    font-size: 0.75rem;
    line-height: 1.4;
    color: var(--hcis-text-muted, #64748b);
}

.pa-compact-tile {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    padding: 0.75rem 0.9rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    cursor: pointer;
    transition: all 160ms cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    user-select: none;
}

.pa-compact-tile:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.08);
    border-color: rgba(203, 213, 225, 0.95);
}

.pa-compact-tile:focus-visible {
    outline: none;
    border-color: var(--hcis-brand-600, #4f46e5);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}

.pa-compact-tile.is-active {
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.7), #ffffff);
    border-color: #4f46e5;
    box-shadow: 0 0 0 1px #4f46e5, 0 4px 14px -2px rgba(79, 70, 229, 0.12);
}

.pa-compact-tile.is-empty {
    opacity: 0.7;
}

.pa-compact-tile.is-empty:hover {
    opacity: 1;
}

.pa-compact-tile__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pa-compact-tile__icon-box {
    width: 1.85rem;
    height: 1.85rem;
    border-radius: var(--hcis-radius-sm, 6px);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-compact-tile__icon-box.is-danger { background: rgba(239, 68, 68, 0.12); color: #dc2626; }
.pa-compact-tile__icon-box.is-warning { background: rgba(245, 158, 11, 0.12); color: #d97706; }
.pa-compact-tile__icon-box.is-amber { background: rgba(245, 158, 11, 0.12); color: #b45309; }
.pa-compact-tile__icon-box.is-purple { background: rgba(168, 85, 247, 0.12); color: #9333ea; }
.pa-compact-tile__icon-box.is-brand { background: rgba(79, 70, 229, 0.12); color: #4f46e5; }
.pa-compact-tile__icon-box.is-success { background: rgba(34, 197, 94, 0.12); color: #16a34a; }
.pa-compact-tile__icon-box.is-muted { background: rgba(100, 116, 139, 0.12); color: #64748b; }

.pa-compact-tile__count {
    font-size: 1.35rem;
    font-weight: 850;
    line-height: 1;
    font-variant-numeric: tabular-nums;
}

.pa-compact-tile__count.text-danger { color: #dc2626; }
.pa-compact-tile__count.text-warning { color: #d97706; }
.pa-compact-tile__count.text-amber { color: #b45309; }
.pa-compact-tile__count.text-purple { color: #9333ea; }
.pa-compact-tile__count.text-brand { color: #4338ca; }
.pa-compact-tile__count.text-success { color: #16a34a; }
.pa-compact-tile__count.text-muted { color: #64748b; }

.pa-compact-tile__info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-compact-tile__title {
    font-size: 0.78rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.25;
}

.pa-compact-tile__desc {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-compact-tile__active-indicator {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: #4f46e5;
    border-radius: 999px 999px 0 0;
}

/* Bottom Forward Handoff Bar to Tab 4 */
.pa-next-stage-handoff-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 1rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.95), rgba(238, 242, 255, 0.75));
    border: 1px dashed rgba(199, 210, 254, 0.95);
    flex-wrap: wrap;
}

.pa-next-stage-handoff-left {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.76rem;
    color: var(--hcis-text-strong, #334155);
}

.pa-next-stage-handoff-left strong {
    color: var(--hcis-brand-700, #4338ca);
}

.pa-handoff-icon-box {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* 4. Active Queue Detail Table Panel */
.pa-active-queue-panel {
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.06);
    border: 1px solid rgba(203, 213, 225, 0.95);
    background: #ffffff;
}

.pa-active-queue-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    width: 100%;
    flex-wrap: wrap;
}

.pa-active-queue-header__left {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}

.pa-queue-badge-title-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.pa-queue-active-icon-box {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: var(--hcis-radius-sm, 8px);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-queue-active-icon-box.is-danger { background: rgba(239, 68, 68, 0.12); color: #dc2626; }
.pa-queue-active-icon-box.is-warning { background: rgba(245, 158, 11, 0.12); color: #d97706; }
.pa-queue-active-icon-box.is-purple { background: rgba(168, 85, 247, 0.12); color: #9333ea; }
.pa-queue-active-icon-box.is-brand { background: rgba(79, 70, 229, 0.12); color: #4f46e5; }
.pa-queue-active-icon-box.is-amber { background: rgba(245, 158, 11, 0.12); color: #b45309; }
.pa-queue-active-icon-box.is-success { background: rgba(34, 197, 94, 0.12); color: #16a34a; }
.pa-queue-active-icon-box.is-muted { background: rgba(100, 116, 139, 0.12); color: #64748b; }

.pa-queue-title-badge-flex {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-active-queue-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 850;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-active-queue-sub {
    margin: 0.1rem 0 0;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-active-queue-header__right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-queue-search-wrap {
    min-width: 240px;
}

.pa-queue-search-input-box {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.65rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-sm, 6px);
    background: #ffffff;
    transition: all 140ms ease;
}

.pa-queue-search-input-box:focus-within {
    border-color: #4f46e5;
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.15);
}

.pa-queue-search-input {
    border: none;
    outline: none;
    font: inherit;
    font-size: 0.74rem;
    color: var(--hcis-text-strong, #0f172a);
    width: 100%;
    background: transparent;
}

.pa-queue-search-input::placeholder {
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-search-clear-btn {
    border: none;
    background: transparent;
    color: var(--hcis-text-muted, #94a3b8);
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
}

.pa-search-clear-btn:hover {
    color: #475569;
}

.pa-queue-loading {
    padding: 1rem 0;
}

.pa-queue-skeleton-strip {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.pa-queue-table-wrap {
    width: 100%;
    overflow-x: auto;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-md, 8px);
}

.pa-queue-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.76rem;
}

.pa-queue-table thead th {
    text-align: left;
    padding: 0.6rem 0.85rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.68rem;
    font-weight: 750;
    color: var(--hcis-text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.pa-queue-table tbody tr {
    border-bottom: 1px solid #f1f5f9;
    transition: background 140ms ease;
}

.pa-queue-table tbody tr:last-child {
    border-bottom: 0;
}

.pa-queue-table td {
    padding: 0.65rem 0.85rem;
    vertical-align: middle;
}

.pa-queue-tr--interactive {
    cursor: pointer;
}

.pa-queue-tr--interactive:hover {
    background: rgba(248, 250, 252, 0.95);
}

.pa-queue-tr--interactive:focus-visible {
    outline: none;
    background: #eff6ff;
}

.pa-table-job-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-job-title {
    font-size: 0.78rem;
    color: var(--hcis-text-strong, #0f172a);
    font-weight: 650;
}

.pa-job-meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.7rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-level-dot-badge {
    color: var(--hcis-brand-700, #4338ca);
    font-weight: 650;
}

.pa-pillar-scores-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-score-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.72rem;
}

.pa-score-tag {
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    padding: 0.08rem 0.3rem;
    border-radius: 3px;
    letter-spacing: 0.04em;
}

.pa-score-tag.is-kpi {
    background: rgba(79, 70, 229, 0.1);
    color: #4338ca;
}

.pa-score-tag.is-komp {
    background: rgba(14, 116, 144, 0.1);
    color: #0e7490;
}

.pa-score-val {
    font-variant-numeric: tabular-nums;
    font-weight: 750;
    color: var(--hcis-text-strong, #1e293b);
}

.pa-final-grade-cell {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-final-num {
    font-size: 0.88rem;
    font-weight: 850;
    font-variant-numeric: tabular-nums;
    color: #4338ca;
}

.pa-no-grade-text {
    font-size: 0.7rem;
    color: var(--hcis-text-muted, #94a3b8);
    font-style: italic;
}

.pa-reason-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    align-items: flex-start;
}

.pa-reason-text-row {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    color: var(--hcis-text-strong, #334155);
}

.pa-reason-desc {
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.35;
}

.pa-queue-empty {
    padding: 2rem 0;
}

.pa-queue-pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    width: 100%;
    padding: 0.25rem 0;
}

.pa-queue-pagination-info {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-queue-pagination-info strong {
    color: var(--hcis-text-strong, #0f172a);
    font-variant-numeric: tabular-nums;
}

/* ==========================================================================
   TABS & MONITORING CONTENT (HEADER INTEGRATED)
   ========================================================================== */
.pa-monitor-tabs {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin: 0;
    padding: 0.35rem 0;
    border: 0;
    background: transparent;
    overflow-x: auto;
    width: 100%;
}

.pa-monitor-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.85rem;
    border: 1px solid transparent;
    border-radius: var(--hcis-radius-md, 8px);
    background: transparent;
    font: inherit;
    color: var(--hcis-text-muted, #64748b);
    cursor: pointer;
    white-space: nowrap;
    transition: all 140ms ease;
}

.pa-monitor-tab:hover {
    color: #0f172a;
    background: rgba(255, 255, 255, 0.75);
}

.pa-monitor-tab--aktif {
    background: #ffffff;
    color: #4338ca;
    border-color: rgba(203, 213, 225, 0.9);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.pa-monitor-tab__nama {
    font-size: 0.8rem;
    font-weight: 750;
}

.pa-monitor-tab__satuan {
    font-size: 0.66rem;
    opacity: 0.75;
    font-weight: 500;
}

.pa-monitor-tab__badge {
    margin-left: 0.15rem;
}

/* ==========================================================================
   TAB 1: RINGKASAN & PROGRES (DESIGN SYSTEM COMPLIANT)
   ========================================================================== */
.pa-tab-ringkasan-wrap {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* 1. Active Cycle Hero Banner */
.pa-cycle-hero-title-group {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-cycle-hero-kicker {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--hcis-brand-600, #4f46e5);
}

.pa-cycle-hero-name-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.pa-cycle-hero-name {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 850;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.2;
}

.pa-cycle-code-pill {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-brand-700, #4338ca);
    background: rgba(79, 70, 229, 0.1);
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
}

.pa-cycle-badge-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-cycle-hero-desc {
    margin: 0.2rem 0 0;
    font-size: 0.76rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.4;
}

.pa-cycle-info-grid {
    display: grid;
    grid-template-columns: 1.15fr 1fr;
    gap: 1.25rem;
    align-items: stretch;
}

.pa-cycle-dates-pane {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pa-cycle-date-card {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(248, 250, 252, 0.8);
    border: 1px solid rgba(226, 232, 240, 0.9);
    transition: all 140ms ease;
}

.pa-cycle-date-card:hover {
    background: #ffffff;
    border-color: rgba(203, 213, 225, 0.95);
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.04);
}

.pa-cycle-date-icon {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: var(--hcis-radius-sm, 8px);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-cycle-date-icon--brand {
    background: rgba(79, 70, 229, 0.12);
    color: #4f46e5;
}

.pa-cycle-date-icon--amber {
    background: rgba(245, 158, 11, 0.14);
    color: #d97706;
}

.pa-cycle-date-content {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pa-cycle-date-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.pa-cycle-date-val {
    font-size: 0.84rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-cycle-date-sub {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-cycle-progress-pane {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.85rem;
    padding: 1rem 1.15rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(248, 250, 252, 0.8);
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-cycle-progress-meta {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}

.pa-cycle-progress-header-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-cycle-progress-title {
    font-size: 0.82rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-cycle-progress-sub {
    font-size: 0.7rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-cycle-progress-pct {
    font-size: 1.4rem;
    font-weight: 850;
    color: var(--hcis-brand-600, #4f46e5);
    line-height: 1;
}

.pa-cycle-progress-summary-chips {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
    font-size: 0.72rem;
}

.pa-summary-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.6rem;
    border-radius: var(--hcis-radius-sm, 6px);
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.74rem;
}

.pa-summary-chip.is-interactive {
    cursor: pointer;
    font: inherit;
    transition: all 140ms ease;
}

.pa-summary-chip.is-interactive:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px -1px rgba(15, 23, 42, 0.08);
}

.pa-summary-chip__dot {
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 999px;
}

.pa-summary-chip.is-success {
    color: #15803d;
    border-color: rgba(187, 247, 208, 0.9);
    background: #f0fdf4;
}
.pa-summary-chip.is-success .pa-summary-chip__dot {
    background: #22c55e;
}

.pa-summary-chip.is-warning {
    color: #b45309;
    border-color: rgba(254, 243, 199, 0.9);
    background: #fffbeb;
}
.pa-summary-chip.is-warning .pa-summary-chip__dot {
    background: #f59e0b;
}
.pa-summary-chip.is-warning.is-interactive:hover {
    border-color: #f59e0b;
    background: #fef3c7;
}

.pa-summary-chip.is-danger {
    color: #b91c1c;
    border-color: rgba(254, 202, 202, 0.9);
    background: #fef2f2;
}
.pa-summary-chip.is-danger .pa-summary-chip__dot {
    background: #ef4444;
}
.pa-summary-chip.is-danger.is-interactive:hover {
    border-color: #ef4444;
    background: #fee2e2;
}

.pa-summary-chip.is-ontime {
    color: #15803d;
    border-color: rgba(187, 247, 208, 0.9);
    background: #f0fdf4;
}

/* 2. Decision & Blocker Baskets */
.pa-card-action-btns {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-baskets-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.85rem;
}

.pa-basket-tile {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    padding: 0.95rem 1rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: #f8fafc;
    border: 1px solid rgba(226, 232, 240, 0.95);
    cursor: pointer;
    transition: all 160ms cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-basket-tile:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 14px -2px rgba(15, 23, 42, 0.08);
    background: #ffffff;
}

.pa-basket-tile.is-brand:hover { border-color: #818cf8; }
.pa-basket-tile.is-warning:hover { border-color: #f59e0b; }
.pa-basket-tile.is-danger:hover { border-color: #ef4444; }
.pa-basket-tile.is-success:hover { border-color: #10b981; }
.pa-basket-tile.is-neutral:hover,
.pa-basket-tile.is-muted:hover { border-color: #94a3b8; }
.pa-basket-tile.is-empty { opacity: 0.85; }

.pa-basket-tile__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-basket-tile__icon {
    width: 2rem;
    height: 2rem;
    border-radius: var(--hcis-radius-sm, 8px);
    display: flex;
    align-items: center;
    justify-content: center;
}

.pa-basket-tile.is-brand .pa-basket-tile__icon { background: rgba(79, 70, 229, 0.1); color: #4f46e5; }
.pa-basket-tile.is-warning .pa-basket-tile__icon { background: rgba(245, 158, 11, 0.12); color: #d97706; }
.pa-basket-tile.is-danger .pa-basket-tile__icon { background: rgba(239, 68, 68, 0.12); color: #dc2626; }
.pa-basket-tile.is-success .pa-basket-tile__icon { background: rgba(16, 185, 129, 0.12); color: #059669; }
.pa-basket-tile.is-neutral .pa-basket-tile__icon,
.pa-basket-tile.is-muted .pa-basket-tile__icon { background: #e2e8f0; color: #64748b; }

.pa-basket-tile__count {
    font-size: 1.35rem;
    font-weight: 850;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-basket-tile.is-brand .pa-basket-tile__count { color: #4338ca; }
.pa-basket-tile.is-warning .pa-basket-tile__count { color: #b45309; }
.pa-basket-tile.is-danger .pa-basket-tile__count { color: #b91c1c; }
.pa-basket-tile.is-success .pa-basket-tile__count { color: #047857; }
.pa-basket-tile.is-neutral .pa-basket-tile__count,
.pa-basket-tile.is-muted .pa-basket-tile__count { color: #475569; }

.pa-basket-tile__body {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    flex: 1;
}

.pa-basket-tile__label {
    font-size: 0.8rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-basket-tile__desc {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.35;
}

.pa-basket-tile__action {
    margin-top: 0.35rem;
    padding-top: 0.4rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.9);
    font-size: 0.7rem;
    font-weight: 650;
    color: var(--hcis-brand-600, #4f46e5);
}

/* 4. Progress Breakdown & Visualization */
.pa-chart-actions-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-chart-insight-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 1.15rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.06), rgba(99, 102, 241, 0.02));
    border: 1px solid rgba(199, 210, 254, 0.8);
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}

.pa-chart-insight-main {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
    min-width: 260px;
}

.pa-chart-insight-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.22rem 0.55rem;
    border-radius: 999px;
    background: rgba(79, 70, 229, 0.12);
    color: var(--hcis-brand-700, #4338ca);
    font-size: 0.7rem;
    font-weight: 750;
    flex-shrink: 0;
}

.pa-chart-insight-text {
    margin: 0;
    font-size: 0.76rem;
    color: var(--hcis-text-strong, #1e293b);
    line-height: 1.45;
}

.pa-chart-insight-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.7rem;
    font-weight: 650;
    color: var(--hcis-brand-600, #4f46e5);
    background: rgba(255, 255, 255, 0.9);
    padding: 0.25rem 0.6rem;
    border-radius: 999px;
    border: 1px solid rgba(199, 210, 254, 0.75);
    white-space: nowrap;
}

.pa-chart-view-section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pa-chart-canvas-box {
    padding: 1rem 1.25rem 0.5rem 1rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(248, 250, 252, 0.75);
    border: 1px solid rgba(226, 232, 240, 0.9);
    overflow: hidden;
}

.pa-chart-footer-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    padding-top: 0.25rem;
    flex-wrap: wrap;
    width: 100%;
}

.pa-chart-legend-row {
    display: flex;
    align-items: center;
    gap: 1.15rem;
    flex-wrap: wrap;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
}

.pa-chart-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.pa-chart-legend-dot {
    width: 0.55rem;
    height: 0.55rem;
    border-radius: 999px;
}

.pa-chart-legend-dot--progress {
    background: linear-gradient(90deg, #818cf8, #4f46e5);
}

.pa-chart-legend-dot--target {
    background: #f59e0b;
    border: 1px dashed #d97706;
}

.pa-segmented-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    background: #f1f5f9;
    padding: 0.2rem;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-segmented-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    border: none;
    background: transparent;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 650;
    color: var(--hcis-text-muted, #64748b);
    cursor: pointer;
    transition: all 140ms ease;
}

.pa-segmented-btn.active {
    background: #ffffff;
    color: #4338ca;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

/* 5. Lifecycle Steps Navigation Grid */
.pa-lifecycle-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}

.pa-lifecycle-card {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 1.1rem 1.15rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: #f8fafc;
    border: 1px solid rgba(226, 232, 240, 0.9);
    cursor: pointer;
    transition: all 160ms cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-lifecycle-card:hover {
    background: #ffffff;
    border-color: #818cf8;
    box-shadow: 0 4px 16px -2px rgba(79, 70, 229, 0.08);
    transform: translateY(-2px);
}

.pa-lifecycle-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pa-lifecycle-num {
    font-size: 0.88rem;
    font-weight: 850;
    color: #4f46e5;
    background: rgba(79, 70, 229, 0.1);
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
}

.pa-lifecycle-card__body {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    flex: 1;
}

.pa-lifecycle-card__title {
    margin: 0;
    font-size: 0.88rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-lifecycle-card__desc {
    margin: 0;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.4;
}

.pa-lifecycle-card__foot {
    margin-top: 0.35rem;
    padding-top: 0.45rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.9);
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--hcis-brand-600, #4f46e5);
}

/* ==========================================================================
   TAB 3: KERANJANG WRAP
   ========================================================================== */
.pa-tab-keranjang-wrap {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pa-tab-pengisian-wrap {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.pa-pemantauan-content {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

/* Override HcisCard header for participant cards to prevent mobile grid breakage */
.pa-mon-hcis-card .hcis-card__header {
    display: flex !important;
    flex-direction: column !important;
    align-items: stretch !important;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
}

.pa-mon-hcis-card .hcis-card__header-main {
    display: flex !important;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
}

.pa-mon-hcis-card .hcis-card__title-wrap {
    display: flex !important;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
}

/* Executive Cockpit Grid for Tab Pengisian (4-Card Grid) */
.pa-pengisian-cockpit-grid,
.pa-pengisian-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.85rem;
    width: 100%;
    max-width: 100%;
    min-width: 0;
}

.pa-cockpit-card,
.pa-pengisian-stat-card {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 1rem 1.15rem;
    border-radius: var(--hcis-radius-xl, 14px);
    border: 1px solid rgba(226, 232, 240, 0.95);
    background: #ffffff;
    cursor: pointer;
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    user-select: none;
    position: relative;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.pa-cockpit-card:hover,
.pa-pengisian-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px -2px rgba(15, 23, 42, 0.08);
    border-color: rgba(99, 102, 241, 0.35);
}

.pa-cockpit-card:focus-visible,
.pa-pengisian-stat-card:focus-visible {
    outline: 2px solid var(--hcis-brand-500, #6366f1);
    outline-offset: 2px;
}

.pa-cockpit-card.is-selected,
.pa-pengisian-stat-card.is-selected {
    border-color: var(--hcis-brand-500, #6366f1);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2), 0 4px 14px -2px rgba(99, 102, 241, 0.15);
    background: linear-gradient(to bottom, #ffffff, rgba(238, 242, 255, 0.5));
}

.pa-cockpit-card.is-success.is-selected,
.pa-pengisian-stat-card.is-success.is-selected {
    border-color: #10b981;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2), 0 4px 14px -2px rgba(16, 185, 129, 0.15);
    background: linear-gradient(to bottom, #ffffff, rgba(236, 253, 245, 0.5));
}

.pa-cockpit-card.is-warning.is-selected,
.pa-pengisian-stat-card.is-warning.is-selected {
    border-color: #f59e0b;
    box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2), 0 4px 14px -2px rgba(245, 158, 11, 0.15);
    background: linear-gradient(to bottom, #ffffff, rgba(254, 243, 199, 0.5));
}

.pa-cockpit-card.is-terkumpul.is-selected,
.pa-pengisian-stat-card.is-terkumpul.is-selected {
    border-color: #8b5cf6;
    box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.2), 0 4px 14px -2px rgba(139, 92, 246, 0.15);
    background: linear-gradient(to bottom, #ffffff, rgba(245, 243, 255, 0.5));
}

.pa-cockpit-card.is-danger.is-selected,
.pa-pengisian-stat-card.is-danger.is-selected {
    border-color: #ef4444;
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2), 0 4px 14px -2px rgba(239, 68, 68, 0.15);
    background: linear-gradient(to bottom, #ffffff, rgba(254, 242, 242, 0.5));
}

.pa-cockpit-card__icon,
.pa-pengisian-stat-card__icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: var(--hcis-radius-md, 10px);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-cockpit-card__icon.is-brand,
.pa-pengisian-stat-card__icon.is-total {
    background: rgba(99, 102, 241, 0.12);
    color: #4f46e5;
}

.pa-cockpit-card__icon.is-success,
.pa-pengisian-stat-card__icon.is-success {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.pa-cockpit-card__icon.is-warning,
.pa-pengisian-stat-card__icon.is-warning {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
}

.pa-cockpit-card__icon.is-terkumpul,
.pa-pengisian-stat-card__icon.is-terkumpul {
    background: rgba(139, 92, 246, 0.12);
    color: #7c3aed;
}

.pa-cockpit-card__icon.is-danger,
.pa-pengisian-stat-card__icon.is-danger {
    background: rgba(239, 68, 68, 0.12);
    color: #dc2626;
}

.pa-cockpit-card__icon.is-safe {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.pa-cockpit-card__body,
.pa-pengisian-stat-card__body {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}

.pa-cockpit-card__label,
.pa-pengisian-stat-card__label {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 0.2rem;
}

.pa-cockpit-card__metric,
.pa-pengisian-stat-card__val {
    display: flex;
    align-items: baseline;
    gap: 0.45rem;
}

.pa-cockpit-card__val,
.pa-pengisian-stat-card__val strong {
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
    font-variant-numeric: tabular-nums;
    line-height: 1.2;
}

.pa-cockpit-card__unit,
.pa-pengisian-stat-card__sub {
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #94a3b8);
    font-weight: 500;
}

.pa-cockpit-card__progress-wrap {
    margin-top: 0.5rem;
    width: 100%;
}

.pa-cockpit-card__track,
.pa-pengisian-stat-card__track {
    width: 100%;
    height: 5px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}

.pa-cockpit-card__fill,
.pa-pengisian-stat-card__fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.4s ease;
}

.pa-cockpit-card__fill.is-success,
.pa-pengisian-stat-card__fill.is-success {
    background: linear-gradient(90deg, #10b981, #059669);
}

.pa-cockpit-card__footer,
.pa-pengisian-stat-card__meta {
    margin-top: 0.35rem;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-cockpit-card__sub {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
}

/* Unified Monitoring Workspace Hub Panel */
.pa-monitoring-hub-panel {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-xl, 14px);
    padding: 1.15rem 1.25rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.pa-monitoring-hub-panel__controls {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    position: relative;
}

/* Harmonized Category Quick Bar */
.pa-hub-category-strip,
.pa-category-strip-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0.75rem;
    background: rgba(248, 250, 252, 0.75);
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: var(--hcis-radius-lg, 10px);
    flex-wrap: wrap;
}

.pa-hub-category-strip__label,
.pa-category-strip-head {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #1e293b);
    white-space: nowrap;
    flex-shrink: 0;
}

.pa-hub-category-strip__pills,
.pa-category-pills-row {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
    flex: 1;
    min-width: 0;
}

.pa-hub-category-pill,
.pa-category-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.22rem 0.55rem 0.22rem 0.35rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    cursor: pointer;
    transition: all 0.18s ease;
    font-size: 0.74rem;
}

.pa-hub-category-pill:hover,
.pa-category-pill-btn:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px -1px rgba(15, 23, 42, 0.06);
}

.pa-hub-category-pill:focus-visible,
.pa-category-pill-btn:focus-visible {
    outline: 2px solid var(--hcis-brand-500, #6366f1);
    outline-offset: 2px;
}

.pa-hub-category-pill.is-active,
.pa-category-pill-btn.is-active {
    background: #eff6ff;
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
    font-weight: 600;
}

.pa-hub-category-pill__progress,
.pa-category-pill-progress {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.pa-hub-category-pill__bar,
.pa-category-pill-bar {
    width: 42px;
    height: 4px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}

.pa-hub-category-pill__bar span,
.pa-category-pill-bar span {
    display: block;
    height: 100%;
    background: #3b82f6;
    border-radius: 999px;
    transition: width 0.3s;
}

.pa-hub-category-pill__bar span.is-complete,
.pa-category-pill-bar span.is-complete {
    background: #10b981;
}

.pa-hub-category-pill__ratio,
.pa-category-pill-ratio {
    font-size: 0.72rem;
    color: #475569;
    font-variant-numeric: tabular-nums;
}

.pa-hub-category-pill__ratio strong,
.pa-category-pill-ratio strong {
    color: #0f172a;
}

.pa-hub-category-pill__ratio small,
.pa-category-pill-ratio small {
    color: #64748b;
    font-size: 0.68rem;
    margin-left: 2px;
}

.pa-hub-category-pill__clear,
.pa-category-pill-clear {
    color: #94a3b8;
    transition: color 0.15s;
    margin-left: -2px;
}

.pa-hub-category-pill__clear:hover,
.pa-category-pill-clear:hover {
    color: #ef4444;
}

/* Filter Controls */
.pa-pemantauan-controls {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    position: relative;
}

.pa-loading-shimmer-line {
    height: 3px;
    width: 100%;
    background: linear-gradient(90deg, #6366f1 0%, #a855f7 50%, #6366f1 100%);
    background-size: 200% 100%;
    animation: paShimmerBar 1.2s infinite linear;
    border-radius: 999px;
    margin-top: -0.35rem;
    margin-bottom: 0.35rem;
}

@keyframes paShimmerBar {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Integrated Header Bar for Table / Cards */
.pa-hub-content-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.35rem 0.2rem;
    flex-wrap: wrap;
    border-bottom: 1px solid rgba(241, 245, 249, 0.9);
    margin-bottom: 0.25rem;
}

.pa-hub-content-header__left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 1;
    min-width: 0;
}

.pa-hub-content-header__right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.pa-table-count-label {
    font-size: 0.8rem;
    color: var(--hcis-text-strong, #1e293b);
}

.pa-table-count-sub {
    font-size: 0.76rem;
    color: var(--hcis-text-muted, #64748b);
    margin-left: 0.25rem;
}

/* Table Wrap & Collapsible Matrix */
.pa-pemantauan-table-wrap,
.pa-pemantauan-grid-wrap {
    position: relative;
    width: 100%;
    min-width: 0;
}

.pa-pemantauan-table-wrap {
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-xl, 14px);
    overflow: hidden;
    background: rgba(255, 255, 255, 0.95);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.pa-pemantauan-table-wrap.is-loading .pa-table-scroll-container,
.pa-pemantauan-grid-wrap.is-loading .pa-pemantauan-grid {
    opacity: 0.42;
    filter: grayscale(15%);
    pointer-events: none;
    transition: opacity 180ms ease, filter 180ms ease;
}

.pa-table-loading-overlay {
    position: absolute;
    inset: 0;
    z-index: 30;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding-top: 3.5rem;
    background: rgba(255, 255, 255, 0.45);
    backdrop-filter: blur(2px);
    border-radius: var(--hcis-radius-xl, 14px);
    animation: paFadeInOverlay 160ms ease forwards;
    pointer-events: all;
}

.pa-table-loading-spinner-box {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.6rem 1.15rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.96);
    border: 1px solid rgba(99, 102, 241, 0.25);
    box-shadow: 0 8px 24px -4px rgba(79, 70, 229, 0.18), 0 2px 6px rgba(15, 23, 42, 0.06);
    font-size: 0.8rem;
    font-weight: 650;
    color: #4338ca;
    letter-spacing: -0.01em;
}

@keyframes paFadeInOverlay {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}

.pa-table-count-label {
    font-size: 0.76rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-table-count-label strong {
    color: var(--hcis-text-strong, #0f172a);
    font-variant-numeric: tabular-nums;
}

.pa-table-scroll-container {
    width: 100%;
    overflow-x: auto;
}

.pa-collapsible-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.78rem;
    text-align: left;
}

.pa-collapsible-table thead th {
    padding: 0.75rem 1rem;
    background: #f8fafc;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.72rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid rgba(226, 232, 240, 0.9);
    white-space: nowrap;
}

.pa-parent-row {
    cursor: pointer;
    transition: background 140ms ease;
    border-bottom: 1px solid rgba(226, 232, 240, 0.7);
}

.pa-parent-row:hover {
    background: rgba(248, 250, 252, 0.9);
}

.pa-parent-row.is-expanded {
    background: rgba(241, 245, 249, 0.65);
    border-bottom: 1px solid transparent;
}

.pa-parent-row td {
    padding: 0.75rem 1rem;
    vertical-align: middle;
}

.pa-col-expand {
    text-align: center;
    width: 3.25rem;
    padding-left: 0.75rem !important;
    padding-right: 0.25rem !important;
}

.pa-expand-chevron-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.6rem;
    height: 1.6rem;
    border: 1px solid rgba(203, 213, 225, 0.7);
    border-radius: var(--hcis-radius-xs, 6px);
    background: #ffffff;
    color: var(--hcis-text-muted, #64748b);
    cursor: pointer;
    transition: transform 180ms cubic-bezier(0.4, 0, 0.2, 1), background 140ms ease, color 140ms ease;
}

.pa-expand-chevron-btn:hover {
    background: #eff6ff;
    color: #2563eb;
    border-color: #93c5fd;
}

.pa-expand-chevron-btn.is-active {
    transform: rotate(90deg);
    background: #4f46e5;
    color: #ffffff;
    border-color: #4f46e5;
}

.pa-division-cell-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-table-sublevel-text {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-progress-cell-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    min-width: 160px;
}

.pa-progress-meta-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.72rem;
}

.pa-progress-count {
    color: var(--hcis-text-strong, #334155);
    font-weight: 600;
}

.pa-progress-count strong {
    color: var(--hcis-text-strong, #0f172a);
    font-variant-numeric: tabular-nums;
}

.pa-progress-pct-badge {
    font-size: 0.65rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    padding: 0.1rem 0.35rem;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
}

.pa-progress-pct-badge.is-complete {
    background: #dcfce7;
    color: #15803d;
}

.pa-progress-mini-track {
    width: 100%;
    height: 0.38rem;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}

.pa-progress-mini-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #6366f1, #4f46e5);
    transition: width 240ms cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-progress-mini-fill.is-complete {
    background: linear-gradient(90deg, #22c55e, #16a34a);
}

.pa-progress-mini-fill.is-overdue {
    background: linear-gradient(90deg, #f59e0b, #ef4444);
}

/* Child Collapsible Row & Subtable */
.pa-child-row {
    background: rgba(248, 250, 252, 0.9);
    border-bottom: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-child-cell {
    padding: 0.5rem 1rem 1rem 3.5rem !important;
}

.pa-subtable-box {
    border: 1px solid rgba(203, 213, 225, 0.8);
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.05);
    overflow: hidden;
}

.pa-subtable-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.55rem 0.85rem;
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.6), rgba(248, 250, 252, 0.9));
    border-bottom: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-subtable-title {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.74rem;
    color: var(--hcis-text-strong, #1e293b);
}

.pa-subtable-title strong {
    color: #4338ca;
}

.pa-subtable {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.75rem;
}

.pa-subtable thead th {
    padding: 0.5rem 0.85rem;
    background: #f8fafc;
    font-size: 0.68rem;
    font-weight: 750;
    color: var(--hcis-text-muted, #64748b);
    border-bottom: 1px solid #e2e8f0;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.pa-subtable-tr {
    border-bottom: 1px solid #f1f5f9;
    transition: background 120ms ease;
}

.pa-subtable-tr:last-child {
    border-bottom: 0;
}

.pa-subtable-tr:hover {
    background: #fafafa;
}

.pa-subtable-tr td {
    padding: 0.6rem 0.85rem;
    vertical-align: middle;
}

.pa-table-person-cell {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}

.pa-table-person-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-table-person-info strong {
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.8rem;
}

.pa-table-person-link {
    padding: 0;
    border: 0;
    background: none;
    font: inherit;
    font-weight: 700;
    color: var(--hcis-brand-700, #1d4ed8);
    text-align: left;
    cursor: pointer;
}

.pa-table-person-link:hover,
.pa-table-person-link:focus-visible {
    text-decoration: underline;
}

.pa-table-person-info small {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.7rem;
}

.pa-kategori-badge {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: var(--hcis-radius-xs, 4px);
    font-size: 0.7rem;
    font-weight: 750;
    white-space: nowrap;
}

.pa-kategori-badge--indigo {
    background: rgba(99, 102, 241, 0.12);
    color: #4338ca;
}

.pa-kategori-badge--teal {
    background: rgba(20, 184, 166, 0.12);
    color: #0f766e;
}

.pa-kategori-badge--cyan {
    background: rgba(6, 182, 212, 0.12);
    color: #0e7490;
}

.pa-kategori-badge--amber {
    background: rgba(245, 158, 11, 0.12);
    color: #b45309;
}

.pa-status-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    align-items: flex-start;
}

.pa-terlambat-pill {
    display: inline-block;
    padding: 0.1rem 0.35rem;
    border-radius: 999px;
    background: #fee2e2;
    color: #b91c1c;
    font-size: 0.65rem;
    font-weight: 800;
}

.pa-table-time-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    font-size: 0.72rem;
}

.pa-time-done {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: #15803d;
    font-weight: 600;
}

.pa-time-due {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-table-divisi-text {
    font-size: 0.76rem;
    color: var(--hcis-text-muted, #475569);
}

/* Grid Cards with HcisCard */
.pa-pemantauan-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.9rem;
}

.pa-mon-hcis-card {
    transition: transform 160ms ease, box-shadow 160ms ease;
}

.pa-mon-hcis-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px -4px rgba(15, 23, 42, 0.08);
}

.pa-mon-card-header-inner {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.6rem;
    width: 100%;
}

.pa-mon-card-target-flex {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    min-width: 0;
}

.pa-mon-card-target-meta {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.pa-eyebrow {
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.08em;
}

.pa-mon-card-target-sub {
    font-size: 0.7rem;
    color: var(--hcis-text-muted, #64748b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-card-status-wrap {
    flex-shrink: 0;
}

.pa-mon-card-body-content {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pa-mon-card-division-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #475569);
    background: rgba(241, 245, 249, 0.8);
    padding: 0.2rem 0.5rem;
    border-radius: var(--hcis-radius-xs, 4px);
    width: fit-content;
}

.pa-card-progress-box {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0.6rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-card-raters-accordion {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.pa-card-raters-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 0.45rem 0.65rem;
    background: #f1f5f9;
    border: 1px solid rgba(203, 213, 225, 0.7);
    border-radius: var(--hcis-radius-sm, 6px);
    font: inherit;
    font-size: 0.72rem;
    font-weight: 650;
    color: var(--hcis-text-strong, #334155);
    cursor: pointer;
    transition: all 140ms ease;
}

.pa-card-raters-toggle:hover {
    background: #e2e8f0;
    color: #1e293b;
}

.pa-card-raters-list {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    padding: 0.35rem 0;
}

.pa-card-rater-item {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0.5rem 0.65rem;
    border-radius: var(--hcis-radius-sm, 6px);
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-card-rater-item-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.45rem;
}

.pa-card-rater-avatar-wrap {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}

.pa-card-rater-avatar-wrap strong {
    font-size: 0.75rem;
    color: var(--hcis-text-strong, #1e293b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-card-rater-item-bot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
    padding-top: 0.3rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.9);
}

.pa-card-rater-status-info {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-mon-card-footer-actions {
    display: flex;
    justify-content: flex-end;
    width: 100%;
}

/* Pagination */
.pa-pemantauan-pagination-bar {
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
    font-variant-numeric: tabular-nums;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

/* ==========================================================================
   TAB 4: HASIL, KALIBRASI & PUBLIKASI (DESIGN SYSTEM COMPLIANT)
   ========================================================================== */
.pa-tab-hasil-wrap {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* Top Cockpit: Metrics & Gateways */
.pa-hasil-top-cockpit {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-hasil-metrics-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.75rem;
}

.pa-hasil-stat-card {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 1rem 1.15rem;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    transition: all 160ms cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-hasil-stat-card:hover {
    transform: translateY(-2px);
    border-color: rgba(199, 210, 254, 0.9);
    box-shadow: 0 6px 16px -2px rgba(15, 23, 42, 0.07);
}

.pa-hasil-stat-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.35rem;
    height: 2.35rem;
    border-radius: var(--hcis-radius-md, 8px);
    flex-shrink: 0;
}

.pa-hasil-stat-icon.is-brand {
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
}

.pa-hasil-stat-icon.is-warning {
    background: #fffbeb;
    color: #d97706;
    border: 1px solid #fde68a;
}

.pa-hasil-stat-icon.is-success {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}

.pa-hasil-stat-icon.is-purple {
    background: #f5f3ff;
    color: #7c3aed;
    border: 1px solid #ddd6fe;
}

.pa-hasil-stat-content {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pa-hasil-stat-label {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.pa-hasil-stat-val-row {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
    margin-top: 0.2rem;
}

.pa-hasil-stat-num {
    font-size: 1.45rem;
    font-weight: 850;
    font-variant-numeric: tabular-nums;
    line-height: 1.15;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-hasil-stat-unit {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-hasil-stat-sub {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
    margin-top: 0.2rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* 3 Specialized Gateways */
.pa-hasil-gateways-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
}

.pa-hasil-gateway-item {
    display: flex;
    align-items: center;
    gap: 0.95rem;
    padding: 0.95rem 1.15rem;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-lg, 12px);
    text-decoration: none;
    color: inherit;
    transition: all 160ms cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.pa-hasil-gateway-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px -2px rgba(15, 23, 42, 0.08);
}

.pa-hasil-gateway-item.is-kalibrasi:hover {
    border-color: #f59e0b;
    background: #fffdfa;
}

.pa-hasil-gateway-item.is-hav:hover {
    border-color: #4f46e5;
    background: #fafaff;
}

.pa-hasil-gateway-item.is-distribusi:hover {
    border-color: #06b6d4;
    background: #f8fdff;
}

.pa-gateway-icon-box {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: var(--hcis-radius-md, 8px);
    flex-shrink: 0;
}

.pa-gateway-icon-box.is-amber {
    background: #fffbeb;
    color: #d97706;
    border: 1px solid #fde68a;
}

.pa-gateway-icon-box.is-brand {
    background: #eff6ff;
    color: #3b82f6;
    border: 1px solid #bfdbfe;
}

.pa-gateway-icon-box.is-cyan {
    background: #ecfeff;
    color: #0891b2;
    border: 1px solid #a5f3fc;
}

.pa-gateway-body {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex: 1;
    min-width: 0;
}

.pa-gateway-kicker {
    font-size: 0.62rem;
    font-weight: 750;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-gateway-title {
    font-size: 0.86rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.25;
}

.pa-gateway-desc {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.35;
    margin: 0;
}

.pa-gateway-arrow {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-text-muted, #94a3b8);
    transition: transform 140ms ease, color 140ms ease;
    flex-shrink: 0;
}

.pa-hasil-gateway-item:hover .pa-gateway-arrow {
    transform: translateX(3px);
    color: var(--hcis-text-strong, #0f172a);
}

/* Controls & Filter Bar */
.pa-hasil-controls-card {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0.95rem 1.15rem;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.pa-hasil-controls-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.pa-hasil-quick-chips {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-hasil-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border: 1px solid rgba(203, 213, 225, 0.8);
    border-radius: 999px;
    background: #f8fafc;
    color: var(--hcis-text-strong, #334155);
    font: inherit;
    font-size: 0.74rem;
    font-weight: 650;
    cursor: pointer;
    transition: all 140ms ease;
}

.pa-hasil-chip:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.pa-hasil-chip.is-active {
    background: #eff6ff;
    border-color: #3b82f6;
    color: #1d4ed8;
    box-shadow: 0 0 0 1px #3b82f6;
}

.pa-hasil-chip.is-warning.is-active {
    background: #fffbeb;
    border-color: #f59e0b;
    color: #b45309;
    box-shadow: 0 0 0 1px #f59e0b;
}

.pa-hasil-chip.is-success.is-active {
    background: #ecfdf5;
    border-color: #10b981;
    color: #047857;
    box-shadow: 0 0 0 1px #10b981;
}

.pa-hasil-chip.is-purple.is-active {
    background: #f5f3ff;
    border-color: #8b5cf6;
    color: #6d28d9;
    box-shadow: 0 0 0 1px #8b5cf6;
}

.pa-chip-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.pa-chip-dot.is-warning { background: #f59e0b; }
.pa-chip-dot.is-success { background: #10b981; }
.pa-chip-dot.is-purple { background: #8b5cf6; }

.pa-hasil-chip-count {
    font-variant-numeric: tabular-nums;
    font-size: 0.72rem;
    font-weight: 750;
    padding: 0.05rem 0.4rem;
    background: rgba(0, 0, 0, 0.06);
    border-radius: 999px;
}

.pa-hasil-controls-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Secondary Filters Row */
.pa-hasil-filters-row {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    flex-wrap: wrap;
    padding-top: 0.65rem;
    border-top: 1px solid rgba(241, 245, 249, 0.95);
}

.pa-hasil-search-box {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.4rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    border: 1px solid rgba(203, 213, 225, 0.85);
    flex: 1;
    min-width: 220px;
    transition: all 120ms ease;
}

.pa-hasil-search-box:focus-within {
    border-color: #3b82f6;
    background: #ffffff;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.12);
}

.pa-hasil-search-input {
    width: 100%;
    border: none;
    background: transparent;
    font: inherit;
    font-size: 0.76rem;
    color: var(--hcis-text-strong, #0f172a);
    outline: none;
}

.pa-hasil-search-input::placeholder {
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-hasil-filter-select-wrap {
    position: relative;
}

.pa-hasil-filter-select {
    padding: 0.4rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    border: 1px solid rgba(203, 213, 225, 0.85);
    font: inherit;
    font-size: 0.74rem;
    color: var(--hcis-text-strong, #334155);
    outline: none;
    cursor: pointer;
    transition: all 120ms ease;
}

.pa-hasil-filter-select:focus {
    border-color: #3b82f6;
    background: #ffffff;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.12);
}

.pa-hasil-view-switch {
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    padding: 0.2rem;
    background: #f1f5f9;
    border-radius: var(--hcis-radius-sm, 6px);
    border: 1px solid rgba(203, 213, 225, 0.6);
    margin-left: auto;
}

.pa-view-switch-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.3rem 0.55rem;
    border: none;
    border-radius: 4px;
    background: transparent;
    color: var(--hcis-text-muted, #64748b);
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

/* Loading & Empty Skeletons */
.pa-hasil-loading-box {
    padding: 1rem 0;
}

.pa-hasil-skeleton-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.85rem;
}

.pa-hasil-skeleton-card {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 0.9rem 1rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
}

/* Table View */
.pa-hasil-content-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pa-hasil-table-container {
    display: flex;
    flex-direction: column;
    gap: 0;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-lg, 12px);
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.pa-table-count-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.6rem 1rem;
    font-size: 0.74rem;
    color: var(--hcis-text-muted, #64748b);
    background: #f8fafc;
    border-bottom: 1px solid rgba(226, 232, 240, 0.8);
}

.pa-hasil-main-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.78rem;
    text-align: left;
}

.pa-hasil-main-table th {
    padding: 0.75rem 0.95rem;
    background: #f8fafc;
    color: var(--hcis-text-muted, #475569);
    font-weight: 750;
    font-size: 0.72rem;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    border-bottom: 1px solid rgba(226, 232, 240, 0.9);
    white-space: nowrap;
}

.pa-hasil-main-table td {
    padding: 0.75rem 0.95rem;
    border-bottom: 1px solid rgba(241, 245, 249, 0.95);
    vertical-align: middle;
}

.pa-hasil-tr--interactive {
    cursor: pointer;
    transition: all 130ms cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.pa-hasil-tr--interactive:hover {
    background: #f8fafc;
}

.pa-hasil-tr--interactive:hover td:first-child {
    box-shadow: inset 3px 0 0 #4f46e5;
}

.pa-table-job-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-job-title {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #1e293b);
}

.pa-job-meta {
    font-size: 0.7rem;
    color: var(--hcis-text-muted, #64748b);
}

/* Pillar Scores Cell */
.pa-pillar-scores-cell {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.pa-score-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.pa-score-tag {
    font-size: 0.62rem;
    font-weight: 750;
    padding: 0.1rem 0.38rem;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
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
    font-size: 0.8rem;
    font-weight: 750;
    font-variant-numeric: tabular-nums;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-score-weight {
    font-size: 0.66rem;
    color: var(--hcis-text-muted, #94a3b8);
}

/* Final Score & Grade */
.pa-final-grade-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-final-num {
    font-size: 1.05rem;
    font-weight: 850;
    font-variant-numeric: tabular-nums;
    color: var(--hcis-brand-700, #1d4ed8);
}

.pa-grade-badge-wrap {
    display: flex;
    align-items: center;
    gap: 0.35rem;
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
    letter-spacing: 0.02em;
}

.pa-no-grade-text {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-status-cell {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-status-sub-date {
    font-size: 0.66rem;
    color: var(--hcis-text-muted, #94a3b8);
}

/* Grid Cards View */
.pa-hasil-cards-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.85rem;
}

.pa-hasil-hcis-card {
    transition: transform 140ms ease, box-shadow 140ms ease;
}

.pa-hasil-hcis-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px -2px rgba(15, 23, 42, 0.08);
}

.pa-hasil-card-header-inner {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
    width: 100%;
}

.pa-hasil-card-person-flex {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
}

.pa-hasil-card-person-meta {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pa-hasil-card-sub {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-hasil-card-body {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.pa-hasil-card-scores-strip {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.4rem;
    padding: 0.5rem 0.65rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.pa-score-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.1rem;
    text-align: center;
}

.pa-score-box-label {
    font-size: 0.64rem;
    color: var(--hcis-text-muted, #64748b);
    font-weight: 600;
}

.pa-score-box-val {
    font-size: 0.85rem;
    font-weight: 750;
    font-variant-numeric: tabular-nums;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-hasil-card-grade-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
    padding-top: 0.2rem;
}

.pa-grade-label {
    font-size: 0.72rem;
    font-weight: 650;
    color: var(--hcis-text-muted, #64748b);
}

.pa-hasil-card-footer {
    display: flex;
    justify-content: flex-end;
    width: 100%;
}

.pa-hasil-pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.85rem 1.15rem;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

/* Cetak Ulang Dialog */
.pa-akses-pilihan {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}

.pa-akses-pilihan button {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    padding: 0.625rem 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    text-align: left;
    cursor: pointer;
}

.pa-akses-pilihan button strong {
    font-size: 0.8125rem;
}

.pa-akses-pilihan button span {
    color: #64748b;
    font-size: 0.75rem;
}

.pa-akses-pilihan button.is-dipilih {
    border-color: #4f46e5;
    background: #f8faff;
}

.pa-cetak-ulang {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-3, 12px);
}

.pa-cetak-ulang__banner {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.6rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #fffbeb;
    border: 1px solid #fde68a;
    font-size: 0.74rem;
    color: #92400e;
    line-height: 1.45;
}

.pa-cetak-ulang__nota {
    margin: 0;
    font-size: 13px;
    line-height: 1.5;
    color: var(--hcis-text-muted, #64748b);
}

.pa-cetak-ulang__preset-chips {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.pa-preset-label {
    font-size: 0.72rem;
    font-weight: 650;
    color: var(--hcis-text-muted, #64748b);
}

.pa-preset-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.pa-preset-chip {
    padding: 0.25rem 0.55rem;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    background: #f8fafc;
    color: var(--hcis-text-strong, #334155);
    font-size: 0.7rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 120ms ease;
}

.pa-preset-chip:hover {
    border-color: #4f46e5;
    color: #4f46e5;
    background: #eff6ff;
}

/* Confirmation Dialogs */
.pa-confirm-dialog-content {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pa-confirm-dialog-content p {
    margin: 0;
    font-size: 0.82rem;
    color: var(--hcis-text-strong, #334155);
    line-height: 1.5;
}

.pa-dialog-info-box {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.6rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    font-size: 0.74rem;
    color: #1e40af;
}

.pa-dialog-warning-box {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.6rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #fffbeb;
    border: 1px solid #fde68a;
    font-size: 0.74rem;
    color: #b45309;
}

/* Skeletons */
.pa-pemantauan-loading {
    padding: 0.5rem 0 1.5rem;
    width: 100%;
}

.pa-table-toolbar-skeleton {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0.25rem 0.75rem;
    gap: 1rem;
}

.pa-table-skeleton-card {
    display: flex;
    flex-direction: column;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-xl, 16px);
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.pa-table-head-skeleton {
    display: grid;
    grid-template-columns: 3.25rem minmax(240px, 2.5fr) minmax(160px, 1.8fr) minmax(190px, 2fr) 150px 130px;
    align-items: center;
    gap: 1rem;
    padding: 0.9rem 1.25rem;
    background: rgba(248, 250, 252, 0.9);
    border-bottom: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-table-row-skeleton {
    display: grid;
    grid-template-columns: 3.25rem minmax(240px, 2.5fr) minmax(160px, 1.8fr) minmax(190px, 2fr) 150px 130px;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgba(241, 245, 249, 0.9);
}

.pa-table-row-skeleton:last-child {
    border-bottom: none;
}

.pa-skel-cell-expand {
    display: flex;
    justify-content: center;
    align-items: center;
}

.pa-skel-cell-person {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}

.pa-skel-cell-div {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    min-width: 0;
}

.pa-skel-cell-progress {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    min-width: 0;
}

.pa-skel-meta-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-skel-cell-status {
    display: flex;
    align-items: center;
}

.pa-skel-cell-action {
    display: flex;
    justify-content: flex-end;
}

.pa-skel-col-text {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    min-width: 0;
}

/* Card Mode Skeletons */
.pa-pemantauan-skeleton-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
}

.pa-pemantauan-skeleton-card {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 1.25rem;
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: var(--hcis-radius-xl, 16px);
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.pa-skel-card-head {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pa-skel-card-body {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.pa-skel-card-foot {
    margin-top: auto;
    padding-top: 0.5rem;
    border-top: 1px solid rgba(241, 245, 249, 0.9);
}

/* Syncing Banner */
.pa-monitor-sync-banner {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.85rem;
    margin-bottom: 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(79, 70, 229, 0.08);
    border: 1px solid rgba(79, 70, 229, 0.2);
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-brand-700, #4338ca);
}

.pa-spin-icon {
    animation: pa-spin 1s linear infinite;
}

@keyframes pa-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Empty States */
.pa-pemantauan-empty-card {
    padding: 3rem 1.5rem;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    border-radius: var(--hcis-radius-xl, 16px);
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-empty-actions-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-top: 0.5rem;
}

@media (max-width: 900px) {
    .pa-table-head-skeleton {
        display: none;
    }

    .pa-table-row-skeleton {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
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

/* ==========================================================================
   PENUTUPAN SIKLUS: TAHAP & SUBTABS
   ========================================================================== */
.pa-tahap-blok {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.pa-tahap-kepala-jarak {
    margin-bottom: 1rem;
}

/* Sebab sebuah tombol tidak ditawarkan (izin fase / status siklus) — ditulis, bukan disembunyikan diam-diam. */
.pa-aksi-catatan {
    align-self: center;
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748b;
    line-height: 1.35;
}

/* Catatan informatif tahap (bukan penghambat). */
.pa-lifecycle-card__catatan {
    display: flex;
    align-items: flex-start;
    gap: 0.3rem;
    margin: 0.35rem 0 0;
    font-size: 0.7rem;
    font-weight: 600;
    color: #475569;
    line-height: 1.35;
}

.pa-tahap-sub {
    display: inline-flex;
    gap: 2px;
    width: fit-content;
    max-width: 100%;
    padding: 3px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.pa-tahap-sub button {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.9rem;
    border: 0;
    border-radius: 9px;
    background: transparent;
    color: #475569;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    transition: all 150ms ease;
}

.pa-tahap-sub button b {
    padding: 0 0.4rem;
    border-radius: 999px;
    background: #fee2e2;
    color: #b91c1c;
    font-size: 0.68rem;
}

.pa-tahap-sub button.is-aktif {
    background: #fff;
    color: #4338ca;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.1);
}

.pa-lifecycle-grid.is-enam {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.pa-lifecycle-card.is-selesai .pa-lifecycle-num {
    background: #dcfce7;
    color: #15803d;
}

.pa-lifecycle-card.is-berjalan {
    border-color: #a5b4fc;
}

.pa-lifecycle-card.is-dilewati {
    opacity: 0.75;
}

.pa-lifecycle-card__hambat {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    margin: 0.35rem 0 0;
    font-size: 0.72rem;
    font-weight: 700;
    color: #b45309;
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

.pa-admin-switcher__item.active,
.pa-admin-switcher__item--active {
    background: #ffffff;
    color: #4338ca;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

/* ==========================================================================
   RESPONSIVE BREAKPOINTS (DESKTOP, TABLET, MOBILE)
   ========================================================================== */

/* 1. Large Tablet / Narrow Desktop (<= 1200px) */
@media (max-width: 1200px) {
    .pa-cockpit-grid {
        grid-template-columns: minmax(300px, 340px) minmax(0, 1fr);
    }
    .pa-cockpit-stats-strip {
        grid-column: 1 / -1;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .pa-pengisian-cockpit-grid,
    .pa-pengisian-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .pa-compact-queue-strip {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .pa-baskets-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .pa-lifecycle-grid,
    .pa-lifecycle-grid.is-enam {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .pa-hasil-metrics-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .pa-hasil-cards-grid,
    .pa-hasil-skeleton-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .pa-pemantauan-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

/* 2. Tablet Mode (<= 992px) */
@media (max-width: 992px) {
    .pa-cockpit-grid {
        grid-template-columns: 1fr;
    }
    .pa-cockpit-gauge-box {
        border-right: none;
        padding-right: 0;
        border-bottom: 1px solid rgba(226, 232, 240, 0.9);
        padding-bottom: 1.15rem;
    }
    .pa-cockpit-stats-strip {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .pa-baskets-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .pa-lifecycle-grid.is-enam {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .pa-hasil-gateways-grid {
        grid-template-columns: 1fr;
    }
    .pa-table-head-skeleton {
        display: none;
    }
    .pa-table-row-skeleton {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
}

/* 3. Small Tablet (<= 768px) */
@media (max-width: 768px) {
    /* Header Actions & Period Trigger */
    .pa-pemantauan-header-actions {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        flex-wrap: nowrap;
    }

    .pa-period-trigger {
        flex: 1 1 auto;
        min-width: 0;
        height: 2.25rem;
        padding: 0.2rem 0.55rem 0.2rem 0.35rem;
        gap: 0.4rem;
        border-radius: var(--hcis-radius-md, 8px);
    }

    .pa-period-trigger__jewel {
        width: 1.65rem;
        height: 1.65rem;
        border-radius: 0.4rem;
    }

    .pa-period-trigger__body {
        flex: 1;
        min-width: 0;
        max-width: none;
        gap: 0;
    }

    .pa-period-trigger__label {
        font-size: 0.54rem;
        line-height: 1;
        gap: 0.25rem;
    }

    .pa-period-trigger__year {
        font-size: 0.52rem;
        padding: 0.02rem 0.25rem;
    }

    .pa-period-trigger__value {
        font-size: 0.72rem;
        font-weight: 750;
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pa-period-trigger__chevron {
        margin-left: 0.05rem;
    }

    .pa-pemantauan-header-actions :deep(.hcis-btn) {
        flex: 0 0 auto;
        height: 2.25rem;
        min-height: 2.25rem;
        padding: 0 0.65rem;
        border-radius: var(--hcis-radius-md, 8px);
    }

    .pa-pemantauan-header-actions :deep(.hcis-btn.hcis-btn--icon-only) {
        width: 2.25rem;
        padding: 0;
        display: grid;
        place-items: center;
    }

    /* Console Row: Single-line Sleek Status Strip */
    :deep(.hcis-header__console) {
        margin-top: 0.35rem;
        padding-top: 0.4rem;
        border-top: 1px dashed rgba(226, 232, 240, 0.75);
    }

    .pa-pemantauan-header-console {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.4rem;
        width: 100%;
        min-width: 0;
    }

    .pa-pemantauan-header-console__identity {
        display: none; /* Hide on mobile to prevent duplicate cycle name and give full width to status & countdown */
    }

    .pa-pemantauan-header-console__context {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.35rem;
        width: 100%;
        min-width: 0;
        flex-wrap: nowrap;
    }

    .pa-pemantauan-header-console__context :deep(.hcis-status-badge) {
        flex-shrink: 0;
        white-space: nowrap;
    }

    .pa-console-code-pill {
        font-size: 0.65rem;
        padding: 0.12rem 0.45rem;
        flex-shrink: 0;
        white-space: nowrap;
    }

    .pa-console-countdown-pill {
        font-size: 0.65rem;
        padding: 0.12rem 0.5rem;
        margin-left: auto;
        flex-shrink: 0;
        white-space: nowrap;
    }

    .pa-pengisian-cockpit-grid,
    .pa-pengisian-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .pa-kiriman-tinjau-header {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }
    .pa-kiriman-tinjau-actions {
        width: 100%;
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
    }
    .pa-kiriman-drawer-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }
    .pa-kiriman-search-wrap {
        max-width: 100%;
        width: 100%;
    }
    .pa-kiriman-filter-pills {
        width: 100%;
        overflow-x: auto;
        flex-wrap: nowrap;
        padding-bottom: 0.25rem;
        -webkit-overflow-scrolling: touch;
    }
    .pa-table-toolbar-strip.is-unified-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 0.6rem;
    }
    .pa-table-toolbar-left {
        width: 100%;
        min-width: 0;
    }
    .pa-table-toolbar-right {
        justify-content: flex-end;
    }
    .pa-child-cell {
        padding: 0.5rem 0.5rem 0.85rem 0.5rem !important;
    }
}

/* 4. Mobile Mode (<= 640px) */
@media (max-width: 640px) {
    .pa-pemantauan-page {
        padding: 0 0 5rem 0;
        gap: 0.85rem;
    }

    /* Tab 1: Executive Cockpit & Ringkasan */
    .pa-tab-ringkasan-wrap {
        gap: 0.85rem;
    }
    .pa-cycle-hero-title-group {
        gap: 0.15rem;
    }
    .pa-cycle-hero-kicker {
        font-size: 0.62rem;
        letter-spacing: 0.06em;
    }
    .pa-cycle-hero-name-row {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 0.4rem;
        flex-wrap: wrap;
    }
    .pa-cycle-hero-name {
        font-size: 1.1rem;
        font-weight: 850;
        line-height: 1.25;
        word-break: break-word;
    }
    .pa-cycle-code-pill {
        font-size: 0.66rem;
        padding: 0.08rem 0.4rem;
    }
    .pa-cycle-badge-group {
        gap: 0.35rem;
        flex-wrap: wrap;
    }
    .pa-cycle-hero-desc {
        font-size: 0.72rem;
        line-height: 1.4;
    }
    .pa-cockpit-grid {
        gap: 0.65rem;
    }
    .pa-cockpit-gauge-box {
        flex-direction: column;
        text-align: center;
        align-items: center;
        justify-content: center;
        padding: 0.85rem 0.65rem 0.75rem 0.65rem;
        gap: 0.5rem;
        background: linear-gradient(135deg, rgba(248, 250, 252, 0.85), rgba(241, 245, 249, 0.5));
        border: 1px solid rgba(226, 232, 240, 0.85);
        border-radius: var(--hcis-radius-lg, 12px);
    }
    .pa-gauge-visual-wrap {
        width: 136px;
        height: 136px;
    }
    .pa-gauge-info {
        align-items: center;
        text-align: center;
        gap: 0.35rem;
    }
    .pa-gauge-cycle-title strong {
        font-size: 0.82rem;
    }
    .pa-gauge-ratio-text {
        font-size: 0.74rem;
    }
    .pa-gauge-status-row {
        justify-content: center;
        margin-top: 0.1rem;
    }
    .pa-cycle-dates-pane {
        gap: 0.45rem;
    }
    .pa-cycle-date-card {
        padding: 0.6rem 0.75rem;
        gap: 0.65rem;
        border-radius: var(--hcis-radius-md, 10px);
    }
    .pa-cycle-date-icon {
        width: 2rem;
        height: 2rem;
        border-radius: 6px;
    }
    .pa-cycle-date-label {
        font-size: 0.66rem;
    }
    .pa-cycle-date-val {
        font-size: 0.8rem;
        font-weight: 800;
    }
    .pa-cycle-date-sub {
        font-size: 0.64rem;
    }
    .pa-cockpit-stats-strip {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.45rem;
    }
    .pa-stat-node {
        padding: 0.55rem 0.6rem;
        gap: 0.45rem;
        border-radius: var(--hcis-radius-md, 10px);
        -webkit-tap-highlight-color: transparent;
    }
    .pa-stat-node--interactive:active {
        transform: scale(0.98);
    }
    .pa-stat-node__icon-box {
        width: 1.85rem;
        height: 1.85rem;
        border-radius: 6px;
    }
    .pa-stat-node__label {
        font-size: 0.64rem;
    }
    .pa-stat-node__value {
        font-size: 1.05rem;
    }
    .pa-stat-node__sub {
        font-size: 0.6rem;
    }
    .pa-card-action-btns {
        flex-direction: column;
        width: 100%;
        gap: 0.4rem;
    }
    .pa-card-action-btns :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
        min-height: 38px;
    }
    .pa-baskets-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.45rem;
    }
    .pa-basket-tile {
        padding: 0.65rem 0.7rem;
        gap: 0.4rem;
        border-radius: var(--hcis-radius-md, 10px);
        -webkit-tap-highlight-color: transparent;
    }
    .pa-basket-tile:active {
        transform: scale(0.98);
    }
    .pa-basket-tile__icon {
        width: 1.85rem;
        height: 1.85rem;
        border-radius: 6px;
    }
    .pa-basket-tile__count {
        font-size: 1.25rem;
    }
    .pa-basket-tile__label {
        font-size: 0.76rem;
        line-height: 1.25;
    }
    .pa-basket-tile__desc {
        font-size: 0.66rem;
        line-height: 1.3;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .pa-basket-tile__action {
        font-size: 0.66rem;
        margin-top: 0.15rem;
    }

    /* Sebaran Chart */
    .pa-chart-card :deep(.hcis-card__header) {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }
    .pa-chart-card :deep(.hcis-card__header-main) {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        width: 100%;
    }
    .pa-chart-card :deep(.hcis-card__actions) {
        width: 100%;
    }
    .pa-chart-actions-wrap {
        width: 100%;
    }
    .pa-segmented-toggle {
        width: 100%;
        display: flex;
        padding: 0.2rem;
        background: #f1f5f9;
        border-radius: var(--hcis-radius-md, 8px);
    }
    .pa-segmented-btn {
        flex: 1;
        justify-content: center;
        padding: 0.4rem 0.45rem;
        font-size: 0.72rem;
        min-height: 36px;
    }
    .pa-chart-insight-bar {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        padding: 0.6rem 0.75rem;
        border-radius: var(--hcis-radius-md, 8px);
        margin-bottom: 0.65rem;
        background: linear-gradient(135deg, rgba(238, 242, 255, 0.75), rgba(248, 250, 252, 0.9));
        border: 1px solid rgba(199, 210, 254, 0.75);
    }
    .pa-chart-insight-header-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.4rem;
        width: 100%;
    }
    .pa-chart-insight-badge {
        font-size: 0.62rem;
        padding: 0.1rem 0.45rem;
    }
    .pa-chart-insight-text {
        font-size: 0.72rem;
        line-height: 1.4;
        margin: 0;
    }
    .pa-chart-insight-hint {
        font-size: 0.64rem;
        color: #64748b;
    }
    .pa-chart-footer-strip {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 0.55rem;
        width: 100%;
    }
    .pa-chart-legend-row {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        width: 100%;
    }
    .pa-chart-legend-item {
        font-size: 0.68rem;
        padding: 0.15rem 0.45rem;
    }
    .pa-chart-footer-strip :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
        min-height: 38px;
    }

    /* Tahap Penutupan Siklus */
    .pa-lifecycle-grid,
    .pa-lifecycle-grid.is-enam {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
    .pa-lifecycle-card {
        padding: 0.75rem 0.85rem;
        gap: 0.45rem;
        border-radius: var(--hcis-radius-md, 10px);
        -webkit-tap-highlight-color: transparent;
    }
    .pa-lifecycle-card:active {
        transform: scale(0.985);
    }
    .pa-lifecycle-num {
        font-size: 1rem;
    }
    .pa-lifecycle-card__title {
        font-size: 0.84rem;
    }
    .pa-lifecycle-card__desc {
        font-size: 0.7rem;
        line-height: 1.38;
    }
    .pa-lifecycle-card__foot {
        font-size: 0.7rem;
    }

    /* Tab 2: Monitoring Rater (Compact Mobile View) */
    .pa-tab-pengisian-wrap {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
        overflow-x: hidden;
    }
    .pa-tahap-kepala-jarak {
        margin-bottom: 0;
    }
    .pa-pengisian-cockpit-grid,
    .pa-pengisian-stat-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.45rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-cockpit-card,
    .pa-pengisian-stat-card {
        padding: 0.55rem 0.65rem;
        gap: 0.45rem;
        border-radius: var(--hcis-radius-md, 10px);
        min-width: 0;
        max-width: 100%;
        box-sizing: border-box;
        overflow: hidden;
        -webkit-tap-highlight-color: transparent;
    }
    .pa-cockpit-card:active,
    .pa-pengisian-stat-card:active {
        transform: scale(0.98);
    }
    .pa-cockpit-card.is-danger,
    .pa-pengisian-stat-card.is-danger {
        grid-column: span 2;
    }
    .pa-cockpit-card__icon,
    .pa-pengisian-stat-card__icon {
        width: 1.75rem;
        height: 1.75rem;
        border-radius: 6px;
        flex-shrink: 0;
    }
    .pa-cockpit-card__body,
    .pa-pengisian-stat-card__body {
        display: flex;
        flex-direction: column;
        min-width: 0;
        max-width: 100%;
        flex: 1;
        overflow: hidden;
    }
    .pa-cockpit-card__label,
    .pa-pengisian-stat-card__label {
        font-size: 0.62rem;
        margin-bottom: 0.1rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }
    .pa-cockpit-card__metric,
    .pa-pengisian-stat-card__val {
        display: flex;
        align-items: baseline;
        gap: 0.25rem;
        flex-wrap: wrap;
        min-width: 0;
        max-width: 100%;
    }
    .pa-cockpit-card__val,
    .pa-pengisian-stat-card__val strong {
        font-size: 1.05rem;
        word-break: break-word;
    }
    .pa-cockpit-card__unit,
    .pa-pengisian-stat-card__sub {
        font-size: 0.6rem;
        word-break: break-word;
    }
    .pa-cockpit-card__progress-wrap,
    .pa-pengisian-stat-card__track {
        width: 100%;
        max-width: 100%;
        height: 4px;
        margin-top: 0.2rem;
    }
    .pa-cockpit-card__footer,
    .pa-pengisian-stat-card__meta {
        margin-top: 0.15rem;
        font-size: 0.58rem;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }
    .pa-monitoring-hub-panel {
        padding: 0.75rem 0.65rem;
        gap: 0.75rem;
        border-radius: var(--hcis-radius-lg, 12px);
    }
    .pa-hub-category-strip,
    .pa-category-strip-card {
        padding: 0.45rem 0.55rem;
        border-radius: var(--hcis-radius-md, 8px);
        gap: 0.35rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-hub-category-strip__label,
    .pa-category-strip-head {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 0.3rem;
        font-size: 0.7rem;
    }
    .pa-hub-category-strip__pills,
    .pa-category-pills-row {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow-x: auto;
        flex-wrap: nowrap;
        padding-bottom: 0.2rem;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        gap: 0.35rem;
    }
    .pa-hub-category-strip__pills::-webkit-scrollbar,
    .pa-category-pills-row::-webkit-scrollbar {
        display: none;
    }
    .pa-hub-category-pill,
    .pa-category-pill-btn {
        flex-shrink: 0;
        min-height: 28px;
        padding: 0.15rem 0.45rem 0.15rem 0.3rem;
        gap: 0.3rem;
        font-size: 0.68rem;
    }
    .pa-hub-category-pill__bar,
    .pa-category-pill-bar {
        width: 28px;
        height: 3px;
    }
    .pa-hub-category-pill__ratio,
    .pa-category-pill-ratio {
        font-size: 0.64rem;
    }
    .pa-hub-content-header {
        padding: 0.25rem 0.1rem;
        gap: 0.4rem;
    }

    /* Kiriman HC Learn Tinjau Card on Mobile */
    .pa-kiriman-tinjau-card {
        border-radius: var(--hcis-radius-md, 10px);
        margin-bottom: 0.4rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-kiriman-tinjau-header {
        padding: 0.65rem 0.75rem;
        gap: 0.55rem;
        flex-direction: column;
        align-items: stretch;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-kiriman-tinjau-header__main {
        gap: 0.55rem;
        min-width: 0;
        max-width: 100%;
        width: 100%;
    }
    .pa-kiriman-icon-box {
        width: 1.85rem;
        height: 1.85rem;
        border-radius: 6px;
        flex-shrink: 0;
    }
    .pa-kiriman-title-row {
        flex-wrap: wrap;
        gap: 0.35rem;
        width: 100%;
        min-width: 0;
        max-width: 100%;
    }
    .pa-kiriman-title-row strong {
        font-size: 0.8rem;
        word-break: break-word;
    }
    .pa-kiriman-tinjau-desc {
        font-size: 0.68rem;
        line-height: 1.35;
        word-break: break-word;
    }
    .pa-kiriman-tinjau-actions {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        align-self: stretch;
    }
    .pa-kiriman-tinjau-actions :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
        min-height: 34px;
        font-size: 0.72rem;
    }
    .pa-kiriman-drawer {
        padding: 0.65rem 0.75rem;
        gap: 0.55rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
        overflow: hidden;
    }
    .pa-kiriman-drawer-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 0.45rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }
    .pa-kiriman-search-wrap {
        max-width: 100%;
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-kiriman-filter-pills {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow-x: auto;
        flex-wrap: nowrap;
        padding-bottom: 0.2rem;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        gap: 0.3rem;
    }
    .pa-kiriman-filter-pills::-webkit-scrollbar {
        display: none;
    }
    .pa-kiriman-filter-pill {
        flex-shrink: 0;
        padding: 0.2rem 0.5rem;
        font-size: 0.68rem;
    }
    .pa-kiriman-table-container {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        display: block;
        box-sizing: border-box;
    }
    .pa-kiriman-drawer-footer {
        flex-direction: column;
        align-items: stretch;
        gap: 0.45rem;
        text-align: center;
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }
    .pa-kiriman-page-nav {
        justify-content: center;
    }

    /* Filter Bar and Unified Toolbar */
    .pa-table-toolbar-strip.is-unified-toolbar {
        padding: 0.55rem 0.75rem;
        margin-bottom: 0.45rem;
        border-radius: var(--hcis-radius-md, 10px);
        flex-direction: column;
        align-items: stretch;
        gap: 0.45rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-table-toolbar-left {
        flex-direction: column;
        align-items: stretch;
        gap: 0.35rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }
    .pa-table-count-label {
        font-size: 0.7rem;
        line-height: 1.35;
        word-break: break-word;
    }
    .pa-table-active-tags {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow-x: auto;
        flex-wrap: nowrap;
        padding-bottom: 0.2rem;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        gap: 0.3rem;
    }
    .pa-table-active-tags::-webkit-scrollbar {
        display: none;
    }
    .pa-active-tag {
        padding: 0.15rem 0.45rem;
        font-size: 0.66rem;
        flex-shrink: 0;
    }
    .pa-table-toolbar-right {
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }
    .pa-table-toolbar-right :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
        min-height: 34px;
        font-size: 0.72rem;
    }

    /* Collapsible Table Mode */
    .pa-pemantauan-table-wrap {
        border-radius: var(--hcis-radius-md, 10px);
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow: hidden;
        box-sizing: border-box;
    }
    .pa-table-scroll-container {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow-x: auto;
        display: block;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-x: contain;
        box-sizing: border-box;
    }
    .pa-collapsible-table thead th {
        padding: 0.5rem 0.6rem;
        font-size: 0.66rem;
    }
    .pa-parent-row td {
        padding: 0.5rem 0.6rem;
    }
    .pa-col-expand {
        width: 2.25rem;
        padding-left: 0.35rem !important;
        padding-right: 0.15rem !important;
    }
    .pa-expand-chevron-btn {
        width: 1.45rem;
        height: 1.45rem;
    }
    .pa-progress-cell-wrap {
        min-width: 120px;
    }
    .pa-child-cell {
        padding: 0.35rem 0.35rem 0.6rem 0.35rem !important;
        box-sizing: border-box;
    }
    .pa-subtable-box {
        border-radius: var(--hcis-radius-sm, 6px);
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow-x: auto;
        display: block;
        -webkit-overflow-scrolling: touch;
        box-sizing: border-box;
    }
    .pa-subtable-header {
        padding: 0.4rem 0.55rem;
    }
    .pa-subtable-title {
        font-size: 0.66rem;
    }
    .pa-subtable thead th {
        padding: 0.35rem 0.5rem;
        font-size: 0.62rem;
    }
    .pa-subtable-tr td {
        padding: 0.4rem 0.5rem;
        font-size: 0.7rem;
    }

    /* Cards Grid Mode */
    .pa-pemantauan-grid,
    .pa-pemantauan-skeleton-grid {
        grid-template-columns: 1fr;
        gap: 0.55rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }
    .pa-mon-hcis-card {
        border-radius: var(--hcis-radius-md, 10px);
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
        overflow: hidden;
    }
    .pa-mon-hcis-card .hcis-card__header {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        padding: 0.65rem 0.75rem !important;
        box-sizing: border-box !important;
    }
    .pa-mon-hcis-card .hcis-card__header-main,
    .pa-mon-hcis-card .hcis-card__title-wrap {
        display: flex !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
    }
    .pa-mon-card-header-inner {
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 0.4rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }
    .pa-mon-card-target-flex {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        min-width: 0;
        flex: 1;
        overflow: hidden;
    }
    .pa-mon-card-target-meta {
        display: flex;
        flex-direction: column;
        min-width: 0;
        flex: 1;
        overflow: hidden;
        gap: 0.05rem;
    }
    .pa-table-person-link {
        font-size: 0.78rem;
        font-weight: 750;
        white-space: normal;
        word-break: break-word;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        max-width: 100%;
    }
    .pa-mon-card-target-sub {
        font-size: 0.64rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }
    .pa-card-status-wrap {
        align-self: center;
        flex-shrink: 0;
    }
    .pa-mon-card-body-content {
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow: hidden;
    }
    .pa-mon-card-division-badge {
        font-size: 0.64rem;
        padding: 0.12rem 0.4rem;
        max-width: 100%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .pa-card-progress-box {
        padding: 0.45rem 0.6rem;
        gap: 0.2rem;
        border-radius: var(--hcis-radius-sm, 6px);
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-progress-count {
        font-size: 0.65rem;
    }
    .pa-progress-pct-badge {
        font-size: 0.6rem;
        padding: 0.08rem 0.3rem;
    }
    .pa-card-raters-toggle {
        min-height: 34px;
        padding: 0.35rem 0.55rem;
        font-size: 0.7rem;
        border-radius: var(--hcis-radius-sm, 6px);
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-card-raters-list {
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }
    .pa-card-rater-item {
        padding: 0.38rem 0.5rem;
        gap: 0.25rem;
        border-radius: var(--hcis-radius-sm, 6px);
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-card-rater-item-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.35rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }
    .pa-card-rater-avatar-wrap {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        min-width: 0;
        flex: 1;
        overflow: hidden;
    }
    .pa-card-rater-avatar-wrap strong {
        font-size: 0.74rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        flex: 1;
        min-width: 0;
    }
    .pa-kategori-badge {
        font-size: 0.62rem;
        padding: 0.1rem 0.35rem;
        flex-shrink: 0;
    }
    .pa-card-rater-item-bot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.35rem;
        flex-wrap: wrap;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        padding-top: 0.25rem;
        border-top: 1px dashed rgba(226, 232, 240, 0.9);
    }
    .pa-card-rater-status-info {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        flex-wrap: wrap;
        min-width: 0;
    }
    .pa-terlambat-pill {
        font-size: 0.6rem;
        padding: 0.08rem 0.3rem;
    }
    .sel-pintu {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        flex-wrap: wrap;
        margin-left: auto;
    }
    .sel-pintu :deep(.hcis-btn) {
        font-size: 0.68rem;
        padding: 0.15rem 0.4rem;
        min-height: 28px;
    }
    .pa-mon-card-footer-actions {
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }
    .pa-mon-card-footer-actions :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
        min-height: 34px;
        font-size: 0.72rem;
    }

    /* Bottom Pagination */
    .pa-pemantauan-pagination-bar {
        padding: 0.5rem 0.65rem;
        border-radius: var(--hcis-radius-md, 10px);
        justify-content: center;
        width: 100%;
    }
    .pa-pemantauan-pagination-bar :deep(.hcis-pagination) {
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
        gap: 0.3rem;
    }

    /* Tab 3: Keranjang & Antrean */
    .pa-tab-keranjang-wrap {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
        overflow-x: hidden;
    }
    .pa-queue-subtab-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 0.45rem;
        padding: 0.55rem 0.65rem;
        border-radius: var(--hcis-radius-md, 10px);
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-queue-subtab-left,
    .pa-queue-subtab-right {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
        gap: 0.35rem;
    }
    .pa-queue-subtab-pill-group {
        width: 100%;
        display: flex;
        padding: 0.2rem;
        gap: 0.2rem;
        background: #f1f5f9;
        border-radius: var(--hcis-radius-md, 8px);
        box-sizing: border-box;
    }
    .pa-queue-subtab-btn {
        flex: 1;
        justify-content: center;
        padding: 0.35rem 0.45rem;
        min-height: 36px;
        font-size: 0.7rem;
        border-radius: var(--hcis-radius-sm, 6px);
        -webkit-tap-highlight-color: transparent;
    }
    .pa-queue-subtab-label {
        font-size: 0.68rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .pa-queue-subtab-badge {
        font-size: 0.6rem;
        padding: 0 0.3rem;
        height: 1.05rem;
        min-width: 1.1rem;
    }
    .pa-queue-subtab-right :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
        min-height: 34px;
        font-size: 0.72rem;
    }
    .pa-compact-queue-strip,
    .pa-compact-queue-strip--dual {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.4rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-compact-tile {
        padding: 0.55rem 0.65rem;
        gap: 0.3rem;
        border-radius: var(--hcis-radius-md, 10px);
        min-width: 0;
        max-width: 100%;
        overflow: hidden;
        box-sizing: border-box;
        -webkit-tap-highlight-color: transparent;
    }
    .pa-compact-tile:active {
        transform: scale(0.98);
    }
    .pa-compact-tile__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .pa-compact-tile__icon-box {
        width: 1.65rem;
        height: 1.65rem;
        border-radius: 6px;
        flex-shrink: 0;
    }
    .pa-compact-tile__count {
        font-size: 1.15rem;
        font-weight: 850;
    }
    .pa-compact-tile__title {
        font-size: 0.74rem;
        font-weight: 750;
        line-height: 1.25;
    }
    .pa-compact-tile__desc {
        font-size: 0.62rem;
        line-height: 1.25;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        white-space: normal;
    }
    .pa-active-queue-panel {
        border-radius: var(--hcis-radius-md, 10px);
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
        overflow: hidden;
    }
    .pa-active-queue-panel :deep(.hcis-card__header) {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        padding: 0.65rem 0.75rem !important;
        box-sizing: border-box !important;
        gap: 0.5rem;
    }
    .pa-active-queue-panel :deep(.hcis-card__header-main),
    .pa-active-queue-panel :deep(.hcis-card__title-wrap) {
        display: flex !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
    }
    .pa-active-queue-header {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
        width: 100%;
        min-width: 0;
    }
    .pa-active-queue-header__left {
        width: 100%;
        min-width: 0;
    }
    .pa-queue-badge-title-row {
        gap: 0.5rem;
        width: 100%;
        min-width: 0;
    }
    .pa-queue-active-icon-box {
        width: 1.85rem;
        height: 1.85rem;
        border-radius: 6px;
        flex-shrink: 0;
    }
    .pa-active-queue-title {
        font-size: 0.86rem;
        word-break: break-word;
    }
    .pa-active-queue-sub {
        font-size: 0.66rem;
        line-height: 1.35;
    }
    .pa-active-queue-header__right {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
        gap: 0.35rem;
    }
    .pa-queue-search-wrap {
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-queue-search-input-box {
        padding: 0.35rem 0.6rem;
        min-height: 34px;
        box-sizing: border-box;
    }
    .pa-queue-search-input {
        font-size: 0.74rem;
    }
    .pa-active-queue-header__right :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
        min-height: 34px;
        font-size: 0.72rem;
    }
    .pa-queue-table-wrap {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow-x: auto;
        display: block;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-x: contain;
        box-sizing: border-box;
    }
    .pa-queue-table thead th {
        padding: 0.45rem 0.6rem;
        font-size: 0.66rem;
    }
    .pa-queue-table td {
        padding: 0.45rem 0.6rem;
        font-size: 0.72rem;
    }
    .pa-queue-pagination-bar {
        padding: 0.5rem 0.65rem;
        border-radius: var(--hcis-radius-md, 10px);
        flex-direction: column;
        align-items: stretch;
        text-align: center;
        gap: 0.4rem;
        width: 100%;
    }
    .pa-queue-pagination-info {
        font-size: 0.68rem;
    }
    .pa-queue-pagination-bar :deep(.hcis-pagination) {
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
        gap: 0.25rem;
    }
    .pa-next-stage-handoff-bar {
        padding: 0.6rem 0.75rem;
        border-radius: var(--hcis-radius-md, 10px);
        flex-direction: column;
        align-items: stretch;
        gap: 0.45rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-next-stage-handoff-left {
        font-size: 0.7rem;
        line-height: 1.35;
    }
    .pa-next-stage-handoff-bar :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
        min-height: 34px;
        font-size: 0.72rem;
    }

    /* Tab 4: Hasil & Kalibrasi Nilai */
    .pa-tab-hasil-wrap {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
        overflow-x: hidden;
    }
    .pa-hasil-top-cockpit {
        gap: 0.5rem;
        width: 100%;
        min-width: 0;
    }
    .pa-hasil-metrics-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.4rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-hasil-stat-card {
        padding: 0.45rem 0.55rem;
        gap: 0.4rem;
        border-radius: var(--hcis-radius-md, 10px);
        min-width: 0;
        max-width: 100%;
        overflow: hidden;
        box-sizing: border-box;
        -webkit-tap-highlight-color: transparent;
    }
    .pa-hasil-stat-card:active {
        transform: scale(0.98);
    }
    .pa-hasil-stat-icon {
        width: 1.65rem;
        height: 1.65rem;
        border-radius: 6px;
        flex-shrink: 0;
    }
    .pa-hasil-stat-content {
        display: flex;
        flex-direction: column;
        min-width: 0;
        max-width: 100%;
        flex: 1;
        overflow: hidden;
    }
    .pa-hasil-stat-label {
        font-size: 0.62rem;
        margin-bottom: 0.1rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }
    .pa-hasil-stat-val-row {
        display: flex;
        align-items: baseline;
        gap: 0.25rem;
        flex-wrap: wrap;
        min-width: 0;
        max-width: 100%;
        margin-top: 0.1rem;
    }
    .pa-hasil-stat-num {
        font-size: 1.05rem;
        font-weight: 850;
        word-break: break-word;
    }
    .pa-hasil-stat-unit {
        font-size: 0.6rem;
    }
    .pa-hasil-stat-sub {
        font-size: 0.58rem;
        color: #64748b;
        margin-top: 0.1rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }
    .pa-hasil-controls-card {
        padding: 0.6rem 0.75rem;
        border-radius: var(--hcis-radius-md, 10px);
        gap: 0.45rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-hasil-controls-top {
        flex-direction: column;
        align-items: stretch;
        gap: 0.45rem;
        width: 100%;
        min-width: 0;
    }
    .pa-hasil-quick-chips {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow-x: auto;
        flex-wrap: nowrap;
        padding-bottom: 0.2rem;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        gap: 0.3rem;
    }
    .pa-hasil-quick-chips::-webkit-scrollbar {
        display: none;
    }
    .pa-hasil-chip {
        flex-shrink: 0;
        padding: 0.2rem 0.5rem;
        font-size: 0.68rem;
        min-height: 28px;
    }
    .pa-hasil-chip-count {
        font-size: 0.64rem;
        padding: 0.04rem 0.35rem;
    }
    .pa-hasil-controls-actions {
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }
    .pa-hasil-controls-actions :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
        min-height: 32px;
        font-size: 0.72rem;
    }
    .pa-hasil-filters-row {
        flex-direction: column;
        align-items: stretch;
        gap: 0.4rem;
        padding-top: 0.4rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }
    .pa-hasil-search-box {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        padding: 0.35rem 0.65rem;
        min-height: 34px;
        box-sizing: border-box;
    }
    .pa-hasil-search-input {
        font-size: 0.74rem;
    }
    .pa-hasil-filter-select-wrap,
    .pa-hasil-filter-select {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        min-height: 34px;
        font-size: 0.72rem;
        box-sizing: border-box;
    }
    .pa-hasil-view-switch {
        margin-left: 0;
        width: 100%;
        display: flex;
        padding: 0.2rem;
        background: #f1f5f9;
        border-radius: var(--hcis-radius-sm, 6px);
        box-sizing: border-box;
    }
    .pa-view-switch-btn {
        flex: 1;
        justify-content: center;
        padding: 0.35rem 0.5rem;
        min-height: 32px;
        font-size: 0.72rem;
    }
    .pa-hasil-content-wrap {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        gap: 0.55rem;
        box-sizing: border-box;
    }
    .pa-hasil-table-container {
        border-radius: var(--hcis-radius-md, 10px);
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow-x: auto;
        display: block;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-x: contain;
        box-sizing: border-box;
    }
    .pa-table-count-strip {
        padding: 0.45rem 0.65rem;
        font-size: 0.68rem;
    }
    .pa-hasil-main-table th {
        padding: 0.45rem 0.6rem;
        font-size: 0.66rem;
    }
    .pa-hasil-main-table td {
        padding: 0.45rem 0.6rem;
        font-size: 0.72rem;
    }
    .pa-hasil-cards-grid,
    .pa-hasil-skeleton-grid {
        grid-template-columns: 1fr;
        gap: 0.55rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-hasil-hcis-card {
        border-radius: var(--hcis-radius-md, 10px);
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
        overflow: hidden;
    }
    .pa-hasil-hcis-card :deep(.hcis-card__header) {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        padding: 0.65rem 0.75rem !important;
        box-sizing: border-box !important;
    }
    .pa-hasil-hcis-card :deep(.hcis-card__header-main),
    .pa-hasil-hcis-card :deep(.hcis-card__title-wrap) {
        display: flex !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
    }
    .pa-hasil-card-header-inner {
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 0.4rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }
    .pa-hasil-card-person-flex {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        min-width: 0;
        flex: 1;
        overflow: hidden;
    }
    .pa-hasil-card-person-meta {
        display: flex;
        flex-direction: column;
        min-width: 0;
        flex: 1;
        overflow: hidden;
        gap: 0.05rem;
    }
    .pa-hasil-card-sub {
        font-size: 0.64rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }
    .pa-hasil-card-body {
        gap: 0.45rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow: hidden;
    }
    .pa-hasil-card-scores-strip {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.35rem;
        padding: 0.45rem 0.55rem;
        border-radius: var(--hcis-radius-sm, 6px);
        background: #f8fafc;
        width: 100%;
        box-sizing: border-box;
    }
    .pa-score-box {
        gap: 0.1rem;
    }
    .pa-score-box-label {
        font-size: 0.6rem;
    }
    .pa-score-box-val {
        font-size: 0.85rem;
    }
    .pa-hasil-card-footer {
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }
    .pa-hasil-card-footer :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
        min-height: 34px;
        font-size: 0.72rem;
    }
    .pa-hasil-pagination-bar {
        padding: 0.5rem 0.65rem;
        border-radius: var(--hcis-radius-md, 10px);
        flex-direction: column;
        align-items: stretch;
        text-align: center;
        gap: 0.4rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-pagination-info {
        font-size: 0.68rem;
        line-height: 1.35;
    }
    .pa-hasil-pagination-bar :deep(.hcis-pagination) {
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
        gap: 0.25rem;
    }

    /* Period Dialog */
    .pa-period-dialog__filter-tabs {
        width: 100%;
        overflow-x: auto;
        flex-wrap: nowrap;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 0.35rem;
    }
    .pa-period-dialog__grid {
        grid-template-columns: 1fr;
        max-height: 65vh;
    }
    .pa-period-dialog__footer-bar {
        flex-direction: column-reverse;
        align-items: stretch;
        gap: 0.5rem;
    }
    .pa-period-dialog__footer-bar :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
    }

    /* Stepper penutupan */
    .pa-tahap-blok {
        gap: 0.5rem;
        margin-bottom: 0.65rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-tahap-sub {
        width: 100%;
        display: flex;
        padding: 0.2rem;
        gap: 0.25rem;
        background: #f1f5f9;
        border-radius: var(--hcis-radius-md, 10px);
        border: 1px solid rgba(226, 232, 240, 0.9);
        box-sizing: border-box;
    }
    .pa-tahap-sub button {
        flex: 1;
        justify-content: center;
        padding: 0.4rem 0.5rem;
        font-size: 0.72rem;
        font-weight: 700;
        border-radius: var(--hcis-radius-sm, 7px);
        min-height: 38px;
        -webkit-tap-highlight-color: transparent;
    }
    .pa-tahap-sub button b {
        padding: 0.05rem 0.35rem;
        font-size: 0.62rem;
        line-height: 1;
    }
}

/* 5. Small Mobile (<= 420px) */
@media (max-width: 420px) {
    .pa-pemantauan-header-console__context {
        gap: 0.25rem;
    }
    .pa-console-code-pill {
        font-size: 0.6rem;
        padding: 0.08rem 0.35rem;
    }
    .pa-console-countdown-pill {
        font-size: 0.6rem;
        padding: 0.08rem 0.4rem;
    }
    .pa-cockpit-stats-strip {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.35rem;
    }
    .pa-stat-node {
        padding: 0.45rem 0.5rem;
        gap: 0.35rem;
    }
    .pa-stat-node__icon-box {
        width: 1.65rem;
        height: 1.65rem;
    }
    .pa-stat-node__value {
        font-size: 0.95rem;
    }
    .pa-stat-node__label {
        font-size: 0.6rem;
    }
    .pa-stat-node__sub {
        font-size: 0.56rem;
    }
    .pa-baskets-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.35rem;
    }
    .pa-basket-tile {
        padding: 0.55rem 0.55rem;
        gap: 0.3rem;
    }
    .pa-basket-tile__icon {
        width: 1.65rem;
        height: 1.65rem;
    }
    .pa-basket-tile__count {
        font-size: 1.15rem;
    }
    .pa-basket-tile__label {
        font-size: 0.72rem;
    }
    .pa-basket-tile__desc {
        font-size: 0.62rem;
    }
    .pa-pengisian-cockpit-grid,
    .pa-pengisian-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.35rem;
    }
    .pa-cockpit-card,
    .pa-pengisian-stat-card {
        padding: 0.45rem 0.5rem;
        gap: 0.35rem;
    }
    .pa-cockpit-card__icon,
    .pa-pengisian-stat-card__icon {
        width: 1.6rem;
        height: 1.6rem;
    }
    .pa-cockpit-card__val,
    .pa-pengisian-stat-card__val strong {
        font-size: 1rem;
    }
    .pa-compact-queue-strip,
    .pa-compact-queue-strip--dual {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.35rem;
    }
    .pa-compact-tile {
        padding: 0.45rem 0.5rem;
        gap: 0.25rem;
    }
    .pa-compact-tile__icon-box {
        width: 1.5rem;
        height: 1.5rem;
    }
    .pa-compact-tile__count {
        font-size: 1.05rem;
    }
    .pa-compact-tile__title {
        font-size: 0.7rem;
    }
    .pa-compact-tile__desc {
        font-size: 0.58rem;
    }
    .pa-hasil-metrics-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.35rem;
    }
    .pa-hasil-stat-card {
        padding: 0.45rem 0.5rem;
        gap: 0.35rem;
    }
    .pa-hasil-stat-icon {
        width: 1.5rem;
        height: 1.5rem;
    }
    .pa-hasil-stat-num {
        font-size: 1rem;
    }
    .pa-kiriman-tinjau-actions {
        display: flex;
        flex-direction: column;
    }
    .pa-kiriman-tinjau-actions :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
    }
}
</style>
