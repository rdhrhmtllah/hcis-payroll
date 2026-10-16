<template>
    <div class="hcis-page-layout">
        <!-- 1. Executive Page Header -->
        <HcisHeader
            title="My Performance"
            kicker="Performance · Individual KPI"
            subtitle="Pantau realisasi KPI bulanan, tren akumulasi tahunan (YTD), dan evaluasi tindak lanjut perbaikan (PICA)."
            icon="speedometer2"
            sentiment="brand"
            variant="glass"
            size="lg"
            :badge="chartPeriodLabel"
            badge-variant="brand"
            class="mb-3"
        >
            <template #actions>
                <div class="d-none d-md-flex align-items-center gap-2">
                    <HcisButton variant="glass" size="sm" icon="journal-text" @click="openDetailModal()">
                        <span>Rincian Lengkap</span>
                    </HcisButton>
                    <HcisButton
                        variant="primary"
                        size="sm"
                        icon="arrow-clockwise"
                        :loading="loading.widgets || loading.kpiList"
                        @click="fetchListData(1)"
                    >
                        <span>Segarkan</span>
                    </HcisButton>
                </div>
            </template>
        </HcisHeader>

        <!-- 2. Signature Executive Stat Cards Group (Directly below Header) -->
        <div class="hcis-kokpit-stats mb-2">
            <HcisStatGroup
                :stats="statCards"
                :cols="3"
                gap="sm"
                appearance="crystal"
                layout="compact"
                @item-click="handleStatClick"
            />
        </div>

        <!-- 3. Integrated Period Control & Quick Filters Strip -->
        <div class="hcis-filter-strip mb-4">
            <div class="hcis-filter-strip__main">
                <!-- Quick Period Presets Segment -->
                <div class="hcis-filter-strip__presets">
                    <span class="hcis-filter-tag d-none d-sm-inline-flex">
                        <i class="bi bi-lightning-charge-fill text-warning me-1"></i>Preset:
                    </span>
                    <div class="hcis-filter-segmented">
                        <button
                            type="button"
                            class="hcis-filter-pill"
                            :class="{ 'hcis-filter-pill--active': isQuickPeriodActive('current_month') }"
                            @click="setQuickPeriod('current_month')"
                        >
                            <i class="bi bi-calendar2-check me-1"></i>Bulan Ini
                        </button>
                        <button
                            type="button"
                            class="hcis-filter-pill"
                            :class="{ 'hcis-filter-pill--active': isQuickPeriodActive('ytd') }"
                            @click="setQuickPeriod('ytd')"
                        >
                            <i class="bi bi-graph-up-arrow me-1"></i>YTD
                        </button>
                        <button
                            type="button"
                            class="hcis-filter-pill"
                            :class="{ 'hcis-filter-pill--active': isQuickPeriodActive('full_year') }"
                            @click="setQuickPeriod('full_year')"
                        >
                            <i class="bi bi-calendar-range me-1"></i>Full-Year
                        </button>
                    </div>
                </div>

                <!-- Custom Period Dropdowns -->
                <div class="hcis-filter-strip__dropdowns">
                    <div class="hcis-filter-item">
                        <span class="hcis-filter-label">Bulan:</span>
                        <el-select
                            v-model="selectedMonth"
                            filterable
                            placeholder="Pilih Bulan"
                            class="hcis-select-control hcis-select-control--month"
                        >
                            <el-option
                                v-for="item in months"
                                :key="item.value"
                                :label="item.label"
                                :value="item.value"
                            />
                        </el-select>
                    </div>

                    <div class="hcis-filter-item">
                        <span class="hcis-filter-label">Tahun:</span>
                        <el-select
                            v-model="selectedYear"
                            filterable
                            placeholder="Pilih Tahun"
                            class="hcis-select-control hcis-select-control--year"
                        >
                            <el-option
                                v-for="item in years"
                                :key="item.value"
                                :label="item.label"
                                :value="item.value"
                            />
                        </el-select>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Main Section: Charts & Parameter KPI -->
        <div class="row g-4 mb-4">
            <!-- Left: Performance Chart (Symmetrical HcisContainer with Standalone Canvas) -->
            <div class="col-12 col-xl-6">
                <HcisContainer
                    variant="glass"
                    sentiment="brand"
                    fluid
                    title="Tren Kinerja Bulanan"
                    :subtitle="`Skor bulanan terverifikasi (${chartPeriodLabel})`"
                    icon="bar-chart-fill"
                    class="h-100"
                >
                    <template #actions>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <div class="hcis-chart-legend-strip d-none d-sm-flex align-items-center gap-2">
                                <span class="hcis-chart-legend-item d-flex align-items-center gap-1 small">
                                    <span class="hcis-legend-dot hcis-legend-dot--et"></span>
                                    <span class="text-slate-600 font-medium">ET (&ge;106%)</span>
                                </span>
                                <span class="hcis-chart-legend-item d-flex align-items-center gap-1 small">
                                    <span class="hcis-legend-dot hcis-legend-dot--gd"></span>
                                    <span class="text-slate-600 font-medium">GD (90-105%)</span>
                                </span>
                                <span class="hcis-chart-legend-item d-flex align-items-center gap-1 small">
                                    <span class="hcis-legend-dot hcis-legend-dot--sy"></span>
                                    <span class="text-slate-600 font-medium">SY (75-89%)</span>
                                </span>
                                <span class="hcis-chart-legend-item d-flex align-items-center gap-1 small">
                                    <span class="hcis-legend-dot hcis-legend-dot--np"></span>
                                    <span class="text-slate-600 font-medium">NP (&lt;75%)</span>
                                </span>
                            </div>
                            <HcisBadge
                                v-if="activeMonthScore !== null"
                                :label="chartActiveMonthBadgeLabel"
                                :variant="activeMonthSentiment"
                                size="xs"
                                dot
                            />
                            <HcisBadge
                                v-else-if="listDataWidget?.Performance?.Keterangan"
                                :label="listDataWidget.Performance.Keterangan"
                                :variant="performanceSentiment"
                                size="xs"
                                dot
                            />
                        </div>
                    </template>

                    <div class="hcis-chart-pane">
                        <!-- High-Density Dual-Pillar Executive Cockpit -->
                        <div v-if="grafikKinerja.ytd !== null" class="hcis-ytd-cockpit mb-3">
                            <!-- Pillar 1: YTD Score & Trend -->
                            <div class="hcis-ytd-cockpit__pillar">
                                <div class="hcis-ytd-cockpit__header">
                                    <span class="hcis-ytd-cockpit__dot" aria-hidden="true"></span>
                                    <span class="hcis-ytd-cockpit__label">Skor Akumulasi YTD</span>
                                </div>
                                <div class="hcis-ytd-cockpit__main">
                                    <div class="hcis-ytd-cockpit__val-group">
                                        <span class="hcis-ytd-cockpit__val">{{
                                            formatSkorGrafik(grafikKinerja.ytd)
                                        }}</span>
                                        <span class="hcis-ytd-cockpit__unit">Poin</span>
                                    </div>
                                    <div
                                        v-if="grafikTren.teks"
                                        class="hcis-ytd-cockpit__trend"
                                        :class="`hcis-ytd-cockpit__trend--${grafikTren.arah}`"
                                    >
                                        <i
                                            :class="
                                                grafikTren.arah === 'up'
                                                    ? 'bi bi-arrow-up-short'
                                                    : grafikTren.arah === 'down'
                                                      ? 'bi bi-arrow-down-short'
                                                      : 'bi bi-dash'
                                            "
                                        ></i>
                                        <span>{{ grafikTren.teks }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Divider -->
                            <div class="hcis-ytd-cockpit__divider"></div>

                            <!-- Pillar 2: Period Insight -->
                            <div class="hcis-ytd-cockpit__pillar hcis-ytd-cockpit__pillar--meta">
                                <div class="hcis-ytd-cockpit__header">
                                    <i class="bi bi-calendar2-check text-primary me-1"></i>
                                    <span class="hcis-ytd-cockpit__label">Rentang Terdata</span>
                                </div>
                                <div class="hcis-ytd-cockpit__meta-row">
                                    <span class="hcis-ytd-cockpit__meta-badge">
                                        {{ grafikYtdShortLabel }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Standalone ECharts Canvas -->
                        <div class="hcis-chart-canvas-wrapper flex-grow-1">
                            <HcisChart
                                :card="false"
                                type="bar"
                                :categories="grafikKinerja.bulan"
                                :series="grafikSeri"
                                :height="chartHeight"
                                :tooltip-formatter="formatTooltipGrafik"
                                :options="opsiGrafikKinerja"
                                :loading="loading.widgets"
                                empty-text="Belum ada skor bulanan yang disetujui"
                            />
                        </div>

                        <!-- Footnote & Peak Month Bar -->
                        <div class="hcis-chart-footer-strip mt-2">
                            <div class="d-flex align-items-center gap-1 text-truncate">
                                <i class="bi bi-info-circle text-primary flex-shrink-0"></i>
                                <span class="text-truncate">Warna bar sesuai master performa; bar ber-border &amp; glow: periode aktif / terverifikasi.</span>
                            </div>
                            <div
                                v-if="skorBulanTertinggi"
                                class="hcis-chart-footer-peak d-none d-sm-flex align-items-center gap-1"
                            >
                                <i class="bi bi-trophy text-warning"></i>
                                <span
                                    >Puncak:
                                    <strong
                                        >{{ skorBulanTertinggi.bulan }} ({{ skorBulanTertinggi.nilai }})</strong
                                    ></span
                                >
                            </div>
                        </div>
                    </div>
                </HcisContainer>
            </div>

            <!-- Right: Current KPI Parameters -->
            <div class="col-12 col-xl-6">
                <HcisContainer
                    variant="glass"
                    sentiment="brand"
                    fluid
                    title="Parameter KPI"
                    :subtitle="`${filteredKpiList.length} parameter pada periode terpilih`"
                    icon="card-checklist"
                    class="h-100"
                >
                    <template #actions>
                        <div class="d-flex align-items-center gap-1">
                            <HcisBadge :label="`${kpiAchievedCount} Tercapai`" variant="success" size="xs" />
                            <HcisBadge
                                v-if="kpiNeedAttentionCount > 0"
                                :label="`${kpiNeedAttentionCount} Perlu Aksi`"
                                variant="warning"
                                size="xs"
                            />
                        </div>
                    </template>

                    <!-- KPI Search & Segment Tabs Toolbar -->
                    <div class="hcis-kpi-toolbar mb-3">
                        <div class="row g-2 align-items-center">
                            <div class="col-12 col-sm-6">
                                <div class="hcis-search-box">
                                    <i class="bi bi-search hcis-search-icon"></i>
                                    <input
                                        v-model="kpiSearchQuery"
                                        type="text"
                                        class="form-control hcis-search-input"
                                        placeholder="Cari parameter, kategori, deskripsi..."
                                    />
                                    <button
                                        v-if="kpiSearchQuery"
                                        type="button"
                                        class="btn btn-sm hcis-search-clear"
                                        @click="kpiSearchQuery = ''"
                                    >
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="hcis-segment-tabs">
                                    <button
                                        type="button"
                                        class="hcis-segment-tab"
                                        :class="{ 'hcis-segment-tab--active': kpiFilterTab === 'all' }"
                                        @click="kpiFilterTab = 'all'"
                                    >
                                        Semua ({{ kpiList.length }})
                                    </button>
                                    <button
                                        type="button"
                                        class="hcis-segment-tab"
                                        :class="{ 'hcis-segment-tab--active': kpiFilterTab === 'achieved' }"
                                        @click="kpiFilterTab = 'achieved'"
                                    >
                                        Tercapai ({{ kpiAchievedCount }})
                                    </button>
                                    <button
                                        type="button"
                                        class="hcis-segment-tab"
                                        :class="{ 'hcis-segment-tab--active': kpiFilterTab === 'need_attention' }"
                                        @click="kpiFilterTab = 'need_attention'"
                                    >
                                        Perlu Aksi ({{ kpiNeedAttentionCount }})
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- KPI List Body -->
                    <div class="hcis-kpi-scroll-container">
                        <!-- Loading State -->
                        <div v-if="loading.kpiList" class="d-flex flex-column gap-3 p-2">
                            <div v-for="n in 3" :key="n" class="hcis-kpi-card hcis-kpi-card--skeleton">
                                <div class="d-flex justify-content-between mb-2">
                                    <HcisSkeleton width="60%" height="1.25rem" rounded="sm" />
                                    <HcisSkeleton width="20%" height="1.25rem" rounded="sm" />
                                </div>
                                <HcisSkeleton width="100%" height="0.5rem" rounded="full" class="mb-3" />
                                <div class="d-flex justify-content-between">
                                    <HcisSkeleton width="30%" height="1rem" rounded="sm" />
                                    <HcisSkeleton width="30%" height="1rem" rounded="sm" />
                                    <HcisSkeleton width="25%" height="1rem" rounded="sm" />
                                </div>
                            </div>
                        </div>

                        <!-- Data Cards -->
                        <div v-else-if="filteredKpiList.length > 0" class="hcis-kpi-grid">
                            <div
                                v-for="kpi in filteredKpiList"
                                :key="kpi.Id_Pengajuan_Detail"
                                class="hcis-kpi-card"
                                :class="{ 'hcis-kpi-card--inactive': !kpi.isActive }"
                                @click="openDetailModal(kpi)"
                                role="button"
                                tabindex="0"
                            >
                                <!-- Top Bar: Badges, Category & Status -->
                                <div class="hcis-kpi-card__header">
                                    <div class="hcis-kpi-card__tags">
                                        <span v-if="kpi.Nama_Kategori" class="hcis-kpi-card__category">
                                            {{ kpi.Nama_Kategori }}
                                        </span>
                                        <span class="hcis-kpi-card__weight">
                                            Bobot {{ formatAchievement(kpi.Bobot) || 0 }}%
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <HcisBadge
                                            :label="getKpiStatusBadge(kpi).label"
                                            :variant="getKpiStatusBadge(kpi).variant"
                                            size="xs"
                                            :dot="kpi.isActive"
                                            :pulse="kpi.isActive && Number(kpi.score || 0) < 100"
                                        />
                                        <i class="bi bi-chevron-right hcis-kpi-card__chevron"></i>
                                    </div>
                                </div>

                                <!-- Parameter Title -->
                                <h4 class="hcis-kpi-card__name">{{ kpi.name }}</h4>

                                <!-- Description with clean text-truncate -->
                                <p
                                    v-if="kpi.Deskripsi"
                                    class="hcis-kpi-card__desc"
                                    :title="kpi.Deskripsi"
                                >
                                    {{ kpi.Deskripsi }}
                                </p>

                                <!-- Unified Performance Capsule (Metrics + Embedded Progress) -->
                                <div class="hcis-kpi-card__capsule">
                                    <div class="hcis-kpi-card__metrics">
                                        <div class="hcis-kpi-card__metric">
                                            <span class="hcis-kpi-card__metric-label">Target</span>
                                            <span class="hcis-kpi-card__metric-value">
                                                {{
                                                    convertValueUnit(kpi.Total_Target, kpi.Format_Input, kpi.Simbol_Satuan)
                                                }}
                                            </span>
                                        </div>
                                        <div class="hcis-kpi-card__metric-sep"></div>
                                        <div class="hcis-kpi-card__metric">
                                            <span class="hcis-kpi-card__metric-label">Aktual</span>
                                            <span class="hcis-kpi-card__metric-value hcis-kpi-card__metric-value--actual">
                                                {{
                                                    convertValueUnit(kpi.Total_Actual, kpi.Format_Input, kpi.Simbol_Satuan)
                                                }}
                                            </span>
                                        </div>
                                        <div class="hcis-kpi-card__metric-sep"></div>
                                        <div class="hcis-kpi-card__metric">
                                            <span class="hcis-kpi-card__metric-label">Capaian</span>
                                            <span
                                                class="hcis-kpi-card__metric-value"
                                                :class="`text-${getKpiStatusBadge(kpi).variant}`"
                                            >
                                                {{ formatAchievement(kpi.score || kpi.achievement) || 0 }}%
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Integrated Progress Bar inside Capsule -->
                                    <div class="hcis-kpi-card__progress-wrap">
                                        <div class="hcis-kpi-card__progress-bar">
                                            <div
                                                class="hcis-kpi-card__progress-fill"
                                                :class="`hcis-kpi-card__progress-fill--${getKpiStatusBadge(kpi).variant}`"
                                                :style="{
                                                    width: `${Math.min(Math.max(Number(kpi.score || kpi.achievement || 0), 0), 100)}%`,
                                                    background: kpi.isActive && kpi.Warna ? kpi.Warna : undefined,
                                                }"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="py-4">
                            <HcisEmptyState
                                :title="kpiSearchQuery ? 'Parameter Tidak Ditemukan' : 'Belum Ada Parameter KPI'"
                                :description="
                                    kpiSearchQuery
                                        ? `Tidak ditemukan parameter KPI yang sesuai dengan kata kunci '${kpiSearchQuery}'.`
                                        : 'Belum ada data parameter KPI yang tercatat untuk periode ini.'
                                "
                                icon="search"
                                size="sm"
                                variant="glass"
                            >
                                <template #actions>
                                    <HcisButton
                                        v-if="kpiSearchQuery || kpiFilterTab !== 'all'"
                                        variant="outline"
                                        size="xs"
                                        icon="arrow-counterclockwise"
                                        @click="
                                            kpiSearchQuery = '';
                                            kpiFilterTab = 'all';
                                        "
                                    >
                                        Reset Filter
                                    </HcisButton>
                                </template>
                            </HcisEmptyState>
                        </div>
                    </div>
                </HcisContainer>
            </div>
        </div>

        <!-- 5. PICA (Problem Identification & Corrective Action) Section -->
        <HcisContainer
            variant="glass"
            sentiment="warning"
            fluid
            title="PICA (Problem Identification & Corrective Action)"
            subtitle="Dokumentasi identifikasi kendala, akar masalah, dan tindak lanjut perbaikan KPI"
            icon="clipboard-pulse"
            class="mb-4"
        >
            <template #actions>
                <div class="d-flex align-items-center gap-2">
                    <HcisBadge
                        :label="`${pagination.totalData || 0} Catatan Kendala`"
                        variant="warning"
                        size="xs"
                        dot
                    />
                </div>
            </template>

            <!-- PICA Search Bar & Context Toolbar -->
            <div class="hcis-pica-toolbar mb-3">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-md-5 col-lg-4">
                        <div class="hcis-search-box">
                            <i class="bi bi-search hcis-search-icon"></i>
                            <input
                                v-model="picaSearchQuery"
                                type="text"
                                class="form-control hcis-search-input"
                                placeholder="Cari kendala, rencana aksi, atau KPI..."
                            />
                            <button
                                v-if="picaSearchQuery"
                                type="button"
                                class="btn btn-sm hcis-search-clear"
                                @click="picaSearchQuery = ''"
                            >
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-12 col-md-7 col-lg-8 d-none d-md-flex align-items-center justify-content-end">
                        <span class="hcis-pica-toolbar__hint">
                            <i class="bi bi-info-circle text-primary me-1"></i>
                            Gunakan PICA untuk melacak akar penyebab performa di bawah target dan memantau realisasi
                            rencana aksinya.
                        </span>
                    </div>
                </div>
            </div>

            <!-- PICA Skeleton Loading -->
            <div v-if="loading.picaList" class="row g-3">
                <div v-for="n in 2" :key="n" class="col-12 col-lg-6">
                    <div class="hcis-pica-card hcis-pica-card--skeleton">
                        <div class="d-flex justify-content-between mb-3">
                            <HcisSkeleton width="50%" height="1.25rem" rounded="sm" />
                            <HcisSkeleton width="20%" height="1.25rem" rounded="full" />
                        </div>
                        <HcisSkeleton width="100%" height="3.75rem" rounded="md" class="mb-2" />
                        <HcisSkeleton width="100%" height="3.75rem" rounded="md" class="mb-3" />
                        <div class="d-flex justify-content-between pt-2 border-top">
                            <HcisSkeleton width="35%" height="1rem" rounded="sm" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- PICA Card Grid -->
            <div v-else-if="filteredPicaList.length > 0" class="row g-3">
                <div v-for="pica in filteredPicaList" :key="pica.id" class="col-12 col-lg-6">
                    <div class="hcis-pica-card">
                        <!-- Card Header -->
                        <div class="hcis-pica-card__header">
                            <div class="hcis-pica-card__period">
                                <i class="bi bi-calendar3 me-1 text-primary"></i>
                                <span>{{ pica.periode || selectedMonthLabel }}</span>
                            </div>
                            <HcisBadge
                                :label="getPicaBadge(pica).label"
                                :variant="getPicaBadge(pica).variant"
                                size="xs"
                                dot
                            />
                        </div>

                        <!-- KPI Title -->
                        <div class="hcis-pica-card__title-row">
                            <h5 class="hcis-pica-card__title">{{ pica.kpiName }}</h5>
                        </div>

                        <!-- Dual Insight Pods -->
                        <div class="hcis-pica-card__boxes">
                            <!-- Problem Identification Pod -->
                            <div class="hcis-pica-box hcis-pica-box--problem">
                                <div class="hcis-pica-box__header">
                                    <span class="hcis-pica-box__badge hcis-pica-box__badge--problem">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                        Identifikasi Masalah
                                    </span>
                                </div>
                                <p class="hcis-pica-box__text">
                                    {{ pica.identifikasi || 'Belum ada catatan identifikasi masalah.' }}
                                </p>
                            </div>

                            <!-- Corrective Action Pod -->
                            <div class="hcis-pica-box hcis-pica-box--action">
                                <div class="hcis-pica-box__header">
                                    <span class="hcis-pica-box__badge hcis-pica-box__badge--action">
                                        <i class="bi bi-shield-check me-1"></i>
                                        Rencana Aksi & Koreksi
                                    </span>
                                </div>
                                <p class="hcis-pica-box__text">
                                    {{ pica.koreksi_aksi || 'Belum ada rekomendasi aksi korektif tercatat.' }}
                                </p>
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div class="hcis-pica-card__footer">
                            <div class="hcis-pica-card__due">
                                <i class="bi bi-clock-history me-1.5 text-primary"></i>
                                <span class="hcis-pica-card__due-label">Target Realisasi:</span>
                                <strong class="hcis-pica-card__due-date">{{ formatDate(pica.tanggal) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty PICA State -->
            <div v-else class="py-3">
                <HcisEmptyState
                    variant="glass"
                    sentiment="success"
                    icon="check-circle-fill"
                    :title="picaSearchQuery ? 'Catatan Tidak Ditemukan' : 'Tidak Ada Catatan Kendala'"
                    :description="
                        picaSearchQuery
                            ? `Tidak ditemukan catatan kendala atau rencana aksi yang sesuai dengan kata kunci '${picaSearchQuery}'.`
                            : 'Seluruh indikator kinerja berjalan dengan baik dan belum ada catatan kendala operasional (PICA) pada periode ini.'
                    "
                    size="sm"
                >
                    <template #actions>
                        <HcisButton
                            v-if="picaSearchQuery"
                            variant="outline"
                            size="xs"
                            icon="arrow-counterclockwise"
                            @click="picaSearchQuery = ''"
                        >
                            Reset Pencarian
                        </HcisButton>
                    </template>
                </HcisEmptyState>
            </div>

            <!-- PICA Pagination Footer -->
            <template #footer v-if="pagination.totalPage > 1">
                <div class="w-100">
                    <HcisPagination
                        :current-page="pagination.page"
                        :page-size="pagination.limit"
                        :total="pagination.totalData"
                        size="sm"
                        layout="total, prev, pager, next"
                        @current-change="changePage"
                    />
                </div>
            </template>
        </HcisContainer>

        <!-- 6. Detail Modal (HcisDialog) -->
        <HcisDialog
            v-model="isModalVisible"
            title="Detail My Performance"
            kicker="Performance · Detail KPI"
            description="Rincian komprehensif capaian KPI, data profil, dan tindak lanjut perbaikan"
            size="2xl"
            variant="glass"
            sentiment="brand"
            icon="speedometer2"
            :badge="detailPeriodeBadge"
            badge-variant="brand"
            :loading="loading.detailKPI"
            :esc-hint="true"
            @close="closeDetailModal"
        >
            <template #default>
                <!-- 1. Toolbar Pemilih Periode -->
                <PemilihPeriodeDetail
                    :nilai="detailPeriode.nilai"
                    :tahun="detailPeriode.tahun"
                    :navigasi="detailNavigasi"
                    :loading="loading.detailKPI"
                    @ganti="muatDetailPeriode"
                    @terapkan="terapkanPeriodeKeDashboard"
                />

                <!-- 2. Isi detail: komponen yang sama dengan dashboard tim -->
                <KpiDetailIsi
                    :header="kpiRecentUpdateHeaders"
                    :items="kpiItemsExample"
                    :loading="loading.detailKPI"
                    :kosong="detailKosong"
                    :is-full-year="isFullYear"
                />
            </template>

            <!-- Footer: Bilateral Action Slot -->
            <template #footer-start>
                <div class="d-none d-sm-flex align-items-center gap-2 text-muted small text-nowrap">
                    <i class="bi bi-calendar2-check text-primary"></i>
                    <span
                        >Periode Data: <strong class="text-dark">{{ detailPeriodeLabel }}</strong></span
                    >
                </div>
            </template>
            <template #footer>
                <HcisButton variant="glass" size="sm" icon="x-lg" class="w-100 w-sm-auto" @click="closeDetailModal">
                    Tutup
                </HcisButton>
            </template>
        </HcisDialog>
    </div>
</template>

<script>
import axios from 'axios';
import * as echarts from 'echarts';
import { debounce } from 'lodash';
import { ElSelect, ElOption } from 'element-plus';
import {
    HcisHeader,
    HcisContainer,
    HcisStatGroup,
    HcisChart,
    HcisBadge,
    HcisButton,
    HcisEmptyState,
    HcisSkeleton,
    HcisDialog,
    HcisPagination,
} from '@/components/ui';
import PemilihPeriodeDetail from '../../../components/ui/PemilihPeriodeDetail.vue';
import KpiDetailIsi from '../../../components/ui/KpiDetailIsi.vue';

export default {
    name: 'StaffPerformanceDashboardPage',
    components: {
        HcisHeader,
        HcisContainer,
        HcisStatGroup,
        HcisChart,
        HcisBadge,
        HcisButton,
        HcisEmptyState,
        HcisSkeleton,
        HcisDialog,
        HcisPagination,
        ElSelect,
        ElOption,
        PemilihPeriodeDetail,
        KpiDetailIsi,
    },
    props: {
        warnaNetral: { type: String, default: 'rgba(46, 204, 113, 1)' },
    },
    data() {
        return {
            is_full_year: false,
            isFullYear: false,
            listDataWidget: {},
            selectedMonth: null,
            selectedYear: null,
            kpiRecentUpdateHeaders: {},
            kpiItemsExample: [],
            isModalVisible: false,
            detailPeriode: { nilai: '01', tahun: new Date().getFullYear() },
            detailNavigasi: [],
            detailKosong: null,
            kpiSearchQuery: '',
            kpiFilterTab: 'all',
            picaSearchQuery: '',
            statusSortDirection: null,
            grafikKinerja: { bulan: [], skor: [], ytd: null, ytdBulan: [], ytdTahun: null },
            months: [
                { label: 'Full-Year (12 Bulan)', value: 'all' },
                { label: 'YTD (Akumulasi)', value: 'YTD' },
                { label: 'Januari', value: '01' },
                { label: 'Februari', value: '02' },
                { label: 'Maret', value: '03' },
                { label: 'April', value: '04' },
                { label: 'Mei', value: '05' },
                { label: 'Juni', value: '06' },
                { label: 'Juli', value: '07' },
                { label: 'Agustus', value: '08' },
                { label: 'September', value: '09' },
                { label: 'Oktober', value: '10' },
                { label: 'November', value: '11' },
                { label: 'Desember', value: '12' },
            ],
            kpiUpdatesList: [],
            kpiList: [],
            deskripsiTerbuka: {},
            deskripsiTerpotong: {},
            picaList: [],
            years: [],
            loading: {
                widgets: false,
                kpiList: false,
                picaList: false,
                kpiUpdatesList: false,
                detailKPI: false,
            },
            pagination: {
                page: 1,
                limit: 5,
                totalPage: 0,
                totalData: 0,
            },
            windowWidth: typeof window !== 'undefined' ? window.innerWidth : 1200,
        };
    },
    computed: {
        chartHeight() {
            return this.windowWidth <= 768 ? 210 : 285;
        },
        opsiGrafikKinerja() {
            const isMobile = this.windowWidth <= 768;
            return {
                grid: {
                    left: isMobile ? 2 : 6,
                    right: isMobile ? 6 : 10,
                    top: isMobile ? 18 : 24,
                    bottom: isMobile ? 4 : 8,
                    containLabel: true,
                },
                yAxis: {
                    splitNumber: isMobile ? 4 : 5,
                    axisLabel: {
                        fontSize: isMobile ? 9.5 : 11,
                    },
                },
                xAxis: {
                    axisLabel: {
                        fontSize: isMobile ? 9.5 : 11,
                        interval: 0,
                    },
                },
            };
        },
        modalTitle() {
            return 'Detail My Performance';
        },
        detailPeriodeBadge() {
            if (this.kpiRecentUpdateHeaders?.Periode) {
                return this.kpiRecentUpdateHeaders.Periode;
            }
            return this.detailPeriodeLabel;
        },
        detailPeriodeLabel() {
            const { nilai, tahun } = this.detailPeriode;
            if (nilai === 'ytd') return `YTD ${tahun}`;
            if (nilai === 'full') return `Full-Year ${tahun}`;
            const monthNames = [
                'Januari',
                'Februari',
                'Maret',
                'April',
                'Mei',
                'Juni',
                'Juli',
                'Agustus',
                'September',
                'Oktober',
                'November',
                'Desember',
            ];
            const num = parseInt(nilai, 10);
            if (!isNaN(num) && num >= 1 && num <= 12) {
                return `${monthNames[num - 1]} ${tahun}`;
            }
            return `${nilai || ''} ${tahun || ''}`.trim();
        },
        selectedMonthLabel() {
            const month = this.months.find((m) => m.value === this.selectedMonth);
            return month ? month.label : 'Full-Year';
        },
        chartPeriodLabel() {
            const val = String(this.selectedMonth || '').toUpperCase();
            if (val === 'YTD') return `Akumulasi YTD ${this.selectedYear || ''}`;
            if (val === 'ALL' || val === '') return `Full Year ${this.selectedYear || ''}`;
            return `${this.selectedMonthLabel} ${this.selectedYear || ''}`;
        },
        activeChartIndex() {
            const bulan = this.grafikKinerja.bulan || [];
            if (!bulan.length) return -1;
            const m = String(this.selectedMonth || '').toUpperCase();
            if (m !== 'ALL' && m !== 'YTD' && m !== '') {
                const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                const num = parseInt(this.selectedMonth, 10);
                const targetName = !isNaN(num) && num >= 1 && num <= 12 ? monthNames[num - 1] : this.selectedMonth;
                const idx = bulan.findIndex((b) => String(b).toLowerCase() === String(targetName).toLowerCase());
                if (idx !== -1) return idx;
            }
            return bulan.length - 1;
        },
        activeMonthScore() {
            const bulan = this.grafikKinerja.bulan || [];
            const skor = this.grafikKinerja.skor || [];
            if (!bulan.length || !skor.length) return null;
            const idx = this.activeChartIndex;
            if (idx >= 0 && idx < skor.length) {
                return skor[idx];
            }
            return null;
        },
        activeMonthSentiment() {
            if (this.activeMonthScore === null) return 'brand';
            return this.warnaBandKinerja(this.activeMonthScore).badgeVariant;
        },
        chartActiveMonthBadgeLabel() {
            if (this.activeMonthScore === null) return '';
            const isMobile = this.windowWidth <= 768;
            const scoreVal = this.formatSkorGrafik(this.activeMonthScore);
            if (isMobile) {
                const m = String(this.selectedMonth || '').toUpperCase();
                if (m === 'YTD') return `YTD: ${scoreVal}`;
                if (m === 'ALL' || m === '') return `Skor: ${scoreVal}`;
                const monthNamesShort = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                const num = parseInt(this.selectedMonth, 10);
                const shortName = !isNaN(num) && num >= 1 && num <= 12 ? monthNamesShort[num - 1] : this.selectedMonthLabel;
                return `${shortName}: ${scoreVal}`;
            }
            return `Skor ${this.selectedMonthLabel}: ${scoreVal}`;
        },
        grafikSeri() {
            const skor = this.grafikKinerja.skor || [];
            const activeIdx = this.activeChartIndex;
            const isMobile = this.windowWidth <= 768;
            return [
                {
                    name: 'Skor bulanan',
                    label: {
                        show: true,
                        position: 'top',
                        distance: 3,
                        color: '#475569',
                        fontWeight: 700,
                        fontSize: isMobile ? 9 : 10.5,
                        textBorderColor: '#ffffff',
                        textBorderWidth: 3,
                        formatter: ({ value }) => this.formatSkorGrafik(value),
                    },
                    markLine: {
                        symbol: ['none', 'none'],
                        silent: true,
                        lineStyle: {
                            color: 'rgba(245, 158, 11, 0.45)',
                            type: 'dashed',
                            width: 1.25,
                        },
                        data: [
                            {
                                yAxis: 100,
                                label: {
                                    show: false,
                                },
                            },
                        ],
                    },
                    data: skor.map((nilai, i) => {
                        const isFocus = i === activeIdx;
                        const band = this.warnaBandKinerja(nilai);
                        if (isFocus) {
                            return {
                                value: nilai,
                                itemStyle: {
                                    color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                        { offset: 0, color: band.gradient.top },
                                        { offset: 1, color: band.gradient.bottom },
                                    ]),
                                    borderColor: band.borderColor,
                                    borderWidth: 2.5,
                                    shadowBlur: 14,
                                    shadowColor: band.activeShadow,
                                    borderRadius: [6, 6, 0, 0],
                                },
                                emphasis: {
                                    itemStyle: {
                                        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                            { offset: 0, color: band.gradient.top },
                                            { offset: 1, color: band.gradient.bottom },
                                        ]),
                                        borderColor: '#ffffff',
                                        borderWidth: 2.5,
                                        shadowBlur: 18,
                                        shadowColor: band.activeShadow,
                                    },
                                },
                                label: {
                                    color: band.textColor,
                                    fontWeight: 800,
                                    fontSize: isMobile ? 10.5 : 12,
                                    textBorderColor: '#ffffff',
                                    textBorderWidth: 3,
                                },
                            };
                        }
                        return {
                            value: nilai,
                            itemStyle: {
                                color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                    { offset: 0, color: band.gradient.top },
                                    { offset: 0.8, color: band.gradient.bottom },
                                    { offset: 1, color: band.gradient.glow },
                                ]),
                                shadowColor: band.shadow,
                                shadowBlur: 6,
                                shadowOffsetY: 2,
                                borderRadius: [6, 6, 0, 0],
                            },
                            emphasis: {
                                itemStyle: {
                                    color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                        { offset: 0, color: band.gradient.top },
                                        { offset: 1, color: band.gradient.bottom },
                                    ]),
                                    shadowBlur: 14,
                                    shadowColor: band.activeShadow,
                                },
                            },
                        };
                    }),
                },
            ];
        },
        grafikYtdLabel() {
            const { ytd, ytdBulan, ytdTahun } = this.grafikKinerja;
            if (ytd === null) return '';
            const singkat = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            if (!ytdBulan || !ytdBulan.length) return `Skor akumulasi ${ytdTahun || ''}`;
            const awal = singkat[ytdBulan[0] - 1];
            const akhir = singkat[ytdBulan[ytdBulan.length - 1] - 1];
            const rentang = awal === akhir ? awal : `${awal}–${akhir}`;
            return `Skor akumulasi ${rentang} ${ytdTahun} · ${ytdBulan.length} bulan terisi`;
        },
        grafikYtdShortLabel() {
            const { ytd, ytdBulan, ytdTahun } = this.grafikKinerja;
            if (ytd === null) return 'Belum ada akumulasi';
            const singkat = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            if (!ytdBulan || !ytdBulan.length) return `Tahun ${ytdTahun || ''}`;
            const awal = singkat[ytdBulan[0] - 1];
            const akhir = singkat[ytdBulan[ytdBulan.length - 1] - 1];
            const rentang = awal === akhir ? awal : `${awal}–${akhir}`;
            return `${rentang} ${ytdTahun} (${ytdBulan.length} bln)`;
        },
        grafikTren() {
            const { skor, bulan } = this.grafikKinerja;
            if (!skor || skor.length < 2) return { teks: '', arah: 'neutral' };
            const kini = bulan[bulan.length - 1];
            const sebelum = bulan[bulan.length - 2];
            const selisih = skor[skor.length - 1] - skor[skor.length - 2];
            if (Math.abs(selisih) < 0.05) return { teks: `${kini} sama dgn ${sebelum}`, arah: 'neutral' };
            return {
                teks: `${selisih > 0 ? '+' : '−'}${this.formatSkorGrafik(Math.abs(selisih))} ${kini} vs ${sebelum}`,
                arah: selisih > 0 ? 'up' : 'down',
            };
        },
        skorBulananRataRata() {
            const skor = this.grafikKinerja.skor || [];
            if (!skor.length) return null;
            const avg = skor.reduce((a, b) => a + Number(b || 0), 0) / skor.length;
            return this.formatSkorGrafik(avg);
        },
        skorBulanTertinggi() {
            const skor = this.grafikKinerja.skor || [];
            const bulan = this.grafikKinerja.bulan || [];
            if (!skor.length) return null;
            let maxIndex = 0;
            skor.forEach((val, idx) => {
                if (Number(val) > Number(skor[maxIndex])) maxIndex = idx;
            });
            return {
                bulan: bulan[maxIndex] || '',
                nilai: this.formatSkorGrafik(skor[maxIndex]),
            };
        },
        performanceSentiment() {
            const ket = (this.listDataWidget?.Performance?.Keterangan || '').toLowerCase().trim();
            if (ket.includes('excellent') || ket === 'et') return 'success';
            if (ket.includes('good') || ket === 'gd') return 'brand';
            if (ket.includes('satisfactory') || ket === 'sy') return 'warning';
            if (ket.includes('poor') || ket.includes('not perform') || ket === 'np') return 'danger';

            const rawScore = this.listDataWidget?.Achievement;
            if (rawScore !== undefined && rawScore !== null && rawScore !== '') {
                return this.warnaBandKinerja(rawScore).badgeVariant;
            }
            return 'neutral';
        },
        achievementSentiment() {
            const rawScore = this.listDataWidget?.Achievement;
            if (rawScore !== undefined && rawScore !== null && rawScore !== '') {
                return this.warnaBandKinerja(rawScore).badgeVariant;
            }
            return 'neutral';
        },
        kpiAchievedCount() {
            return this.kpiList.filter((k) => k.isActive && Number(k.score || k.achievement || 0) >= 100).length;
        },
        kpiNeedAttentionCount() {
            return this.kpiList.filter((k) => k.isActive && Number(k.score || k.achievement || 0) < 100).length;
        },
        kpiInactiveCount() {
            return this.kpiList.filter((k) => !k.isActive).length;
        },
        filteredKpiList() {
            let list = this.kpiList || [];

            if (this.kpiFilterTab === 'achieved') {
                list = list.filter((k) => k.isActive && Number(k.score || k.achievement || 0) >= 100);
            } else if (this.kpiFilterTab === 'need_attention') {
                list = list.filter((k) => k.isActive && Number(k.score || k.achievement || 0) < 100);
            } else if (this.kpiFilterTab === 'inactive') {
                list = list.filter((k) => !k.isActive);
            }

            const q = (this.kpiSearchQuery || '').trim().toLowerCase();
            if (q) {
                list = list.filter((k) => {
                    const name = (k.name || '').toLowerCase();
                    const desc = (k.Deskripsi || '').toLowerCase();
                    const category = (k.Nama_Kategori || '').toLowerCase();
                    const status = (k.Keterangan || '').toLowerCase();
                    return name.includes(q) || desc.includes(q) || category.includes(q) || status.includes(q);
                });
            }
            return list;
        },
        filteredPicaList() {
            let list = this.picaList || [];
            const q = (this.picaSearchQuery || '').trim().toLowerCase();
            if (q) {
                list = list.filter((p) => {
                    const name = (p.kpiName || '').toLowerCase();
                    const iden = (p.identifikasi || '').toLowerCase();
                    const corr = (p.koreksi_aksi || '').toLowerCase();
                    const status = (p.status || '').toLowerCase();
                    return name.includes(q) || iden.includes(q) || corr.includes(q) || status.includes(q);
                });
            }
            return list;
        },
        statCards() {
            const rawScore = this.listDataWidget?.Achievement;
            const scoreNum = typeof rawScore === 'number' ? rawScore : parseFloat(rawScore) || 0;
            const isWidgetLoading = this.loading.widgets;

            return [
                {
                    id: 'perf-status',
                    label: 'Status Kinerja',
                    value: isWidgetLoading ? '...' : this.listDataWidget?.Performance?.Keterangan || 'N/A',
                    sentiment: this.performanceSentiment || 'brand',
                    icon: 'award-fill',
                    iconAnimation: 'pulse',
                    pattern: 'grid',
                    caption: `${this.selectedMonthLabel} ${this.selectedYear || ''} (Skor: ${this.formatAchievement(scoreNum)})`,
                    loading: isWidgetLoading,
                    clickable: true,
                    layout: 'compact',
                    appearance: 'crystal',
                },
                {
                    id: 'kpi-count',
                    label: 'Parameter KPI',
                    value: isWidgetLoading
                        ? '...'
                        : `${this.kpiList.filter((k) => k.isActive).length}`,
                    suffix: 'Aktif',
                    sentiment: 'teal',
                    icon: 'list-check',
                    iconAnimation: 'hover',
                    pattern: 'dots',
                    caption: `${this.kpiAchievedCount}/${this.kpiList.length} Capai Target`,
                    loading: isWidgetLoading,
                    clickable: false,
                    layout: 'compact',
                    appearance: 'crystal',
                },
                {
                    id: 'pica-count',
                    label: 'Tindak Lanjut PICA',
                    value: isWidgetLoading ? '...' : `${this.pagination.totalData || 0}`,
                    suffix: 'Item',
                    sentiment: (this.pagination.totalData || 0) > 0 ? 'warning' : 'rose',
                    icon: 'hourglass-split',
                    iconAnimation: 'pulse',
                    pattern: 'stripes',
                    caption:
                        (this.pagination.totalData || 0) > 0
                            ? `${this.pagination.totalData} Catatan Kendala`
                            : 'Tidak Ada Kendala',
                    loading: isWidgetLoading,
                    clickable: false,
                    layout: 'compact',
                    appearance: 'crystal',
                },
            ];
        },
        activeFilters() {
            return {
                mon: this.selectedMonth,
                yr: this.selectedYear,
            };
        },
    },
    mounted() {
        this._ukurDeskripsiSaatResize = () => {
            this.windowWidth = window.innerWidth;
            this.ukurDeskripsi();
        };
        window.addEventListener('resize', this._ukurDeskripsiSaatResize);
    },
    beforeUnmount() {
        window.removeEventListener('resize', this._ukurDeskripsiSaatResize);
    },
    methods: {
        handleStatClick({ item }) {
            if (item?.clickable || item?.id === 'perf-status') {
                this.openDetailModal();
            }
        },
        setQuickPeriod(type) {
            if (type === 'current_month') {
                const now = new Date();
                this.selectedYear = String(now.getFullYear());
                this.selectedMonth = String(now.getMonth() + 1).padStart(2, '0');
            } else if (type === 'ytd') {
                this.selectedMonth = 'YTD';
            } else if (type === 'full_year') {
                this.selectedMonth = 'all';
            }
        },
        isQuickPeriodActive(type) {
            const m = String(this.selectedMonth || '').toUpperCase();
            if (type === 'ytd') return m === 'YTD';
            if (type === 'full_year') return m === 'ALL' || m === '';
            if (type === 'current_month') {
                const currentMonthStr = String(new Date().getMonth() + 1).padStart(2, '0');
                const currentYearStr = String(new Date().getFullYear());
                return this.selectedMonth === currentMonthStr && String(this.selectedYear) === currentYearStr;
            }
            return false;
        },
        warnaBandKinerja(skor) {
            const val = Number(skor || 0);
            if (val >= 106) {
                return {
                    kode: 'ET',
                    label: 'Excellent',
                    badgeVariant: 'success',
                    solidColor: '#10b981',
                    textColor: '#047857',
                    borderColor: '#a7f3d0',
                    gradient: {
                        top: '#10b981',
                        bottom: '#059669',
                        glow: '#047857',
                    },
                    shadow: 'rgba(16, 185, 129, 0.35)',
                    activeShadow: 'rgba(16, 185, 129, 0.65)',
                };
            }
            if (val >= 90) {
                return {
                    kode: 'GD',
                    label: 'Good',
                    badgeVariant: 'brand',
                    solidColor: '#6366f1',
                    textColor: '#3730a3',
                    borderColor: '#a5b4fc',
                    gradient: {
                        top: '#6366f1',
                        bottom: '#4f46e5',
                        glow: '#4338ca',
                    },
                    shadow: 'rgba(99, 102, 241, 0.35)',
                    activeShadow: 'rgba(99, 102, 241, 0.65)',
                };
            }
            if (val >= 75) {
                return {
                    kode: 'SY',
                    label: 'Satisfactory',
                    badgeVariant: 'warning',
                    solidColor: '#f59e0b',
                    textColor: '#b45309',
                    borderColor: '#fde68a',
                    gradient: {
                        top: '#fbbf24',
                        bottom: '#f59e0b',
                        glow: '#d97706',
                    },
                    shadow: 'rgba(245, 158, 11, 0.35)',
                    activeShadow: 'rgba(245, 158, 11, 0.65)',
                };
            }
            return {
                kode: 'NP',
                label: 'Not Perform',
                badgeVariant: 'danger',
                solidColor: '#ef4444',
                textColor: '#b91c1c',
                borderColor: '#fecaca',
                gradient: {
                    top: '#f87171',
                    bottom: '#ef4444',
                    glow: '#dc2626',
                },
                shadow: 'rgba(239, 68, 68, 0.35)',
                activeShadow: 'rgba(239, 68, 68, 0.65)',
            };
        },
        getKpiStatusBadge(kpi) {
            if (!kpi.isActive) {
                return { label: 'Belum Aktif', variant: 'neutral', color: '#94a3b8' };
            }
            const ket = (kpi.Keterangan || '').trim();
            const score = Number(kpi.score || kpi.achievement || 0);
            const band = this.warnaBandKinerja(score);
            return {
                label: ket || band.label,
                variant: band.badgeVariant,
                color: band.solidColor,
            };
        },
        getPicaBadge(pica) {
            const rawLabel = pica.status || pica.Keterangan || 'Review';
            const st = rawLabel.toLowerCase().trim();

            if (st.includes('excellent') || st.includes('completed') || st.includes('selesai') || st === 'et') {
                return { label: rawLabel, variant: 'success' };
            }
            if (st.includes('good') || st.includes('baik') || st === 'gd') {
                return { label: rawLabel, variant: 'brand' };
            }
            if (
                st.includes('satisfactory') ||
                st.includes('cukup') ||
                st.includes('action') ||
                st.includes('pending') ||
                st === 'sy'
            ) {
                return { label: rawLabel, variant: 'warning' };
            }
            if (
                st.includes('poor') ||
                st.includes('not perform') ||
                st.includes('kurang') ||
                st.includes('danger') ||
                st === 'np'
            ) {
                return { label: rawLabel, variant: 'danger' };
            }

            if (pica.warna) {
                const hex = pica.warna.toLowerCase();
                if (hex.includes('green') || hex.startsWith('#10') || hex.startsWith('#05') || hex.startsWith('#2e')) {
                    return { label: rawLabel, variant: 'success' };
                }
                if (hex.includes('blue') || hex.startsWith('#3b') || hex.startsWith('#63') || hex.startsWith('#00')) {
                    return { label: rawLabel, variant: 'brand' };
                }
                if (hex.includes('yellow') || hex.includes('orange') || hex.startsWith('#f5') || hex.startsWith('#f1')) {
                    return { label: rawLabel, variant: 'warning' };
                }
                return { label: rawLabel, variant: 'danger' };
            }

            return { label: rawLabel, variant: 'danger' };
        },
        formatSkorGrafik(nilai) {
            return Number(nilai).toLocaleString('id-ID', {
                maximumFractionDigits: 1,
            });
        },
        formatTooltipGrafik(params) {
            const item = Array.isArray(params) ? params[0] : params;
            if (!item) return '';
            const namaBulan = {
                Jan: 'Januari',
                Feb: 'Februari',
                Mar: 'Maret',
                Apr: 'April',
                Mei: 'Mei',
                Jun: 'Juni',
                Jul: 'Juli',
                Agu: 'Agustus',
                Sep: 'September',
                Okt: 'Oktober',
                Nov: 'November',
                Des: 'Desember',
            };
            const bulan = namaBulan[item.name] || item.name;
            const rawVal = typeof item.value === 'object' ? item.value?.value : item.value;
            const nilai = this.formatSkorGrafik(rawVal);
            const band = this.warnaBandKinerja(rawVal);
            const dot = `<span style="display:inline-block;margin-right:6px;border-radius:50%;width:8px;height:8px;background-color:${band.solidColor};"></span>`;

            return `<div style="font-weight:700;margin-bottom:2px;">${bulan} ${this.selectedYear || ''}</div>
                <div style="font-size:11.5px;display:flex;justify-content:space-between;gap:14px;align-items:center;margin-top:4px;">
                    <span>${dot}${band.label}:</span>
                    <strong style="font-family:monospace;color:${band.solidColor};">${nilai}%</strong>
                </div>`;
        },
        simpanElDeskripsi(id, el) {
            this._elDeskripsi = this._elDeskripsi || {};
            if (el) this._elDeskripsi[id] = el;
            else delete this._elDeskripsi[id];
        },
        ukurDeskripsi() {
            this.$nextTick(() => {
                const hasil = {};
                Object.entries(this._elDeskripsi || {}).forEach(([id, el]) => {
                    hasil[id] = this.deskripsiTerbuka[id] || el.scrollHeight > el.clientHeight + 1;
                });
                this.deskripsiTerpotong = hasil;
            });
        },
        toggleDeskripsi(id) {
            this.deskripsiTerbuka = {
                ...this.deskripsiTerbuka,
                [id]: !this.deskripsiTerbuka[id],
            };
        },
        formatAchievement(value) {
            const num = Number(value);
            if (isNaN(num)) return '0';
            return num % 1 === 0 ? num.toString() : num.toFixed(1);
        },
        convertValueUnit(value, format, simbol = null) {
            if (!format || value === undefined || value === null) return '-';
            let simbolSatuan = simbol ? simbol.trim() : '';
            let decimals = 2;
            let result = '';

            if (format === 'currency') {
                result = new Intl.NumberFormat('id-ID').format(value);
                return (simbolSatuan ? simbolSatuan + '. ' : 'Rp ') + result;
            } else if (format === 'percentage') {
                const numericValue = parseFloat(value) || 0;
                const strValue = value.toString();
                const userInputDecimal = strValue.includes('.') || strValue.includes(',');
                if (!userInputDecimal && numericValue % 1 === 0) {
                    result = `${numericValue}`;
                } else {
                    result = `${numericValue.toFixed(decimals)}`;
                }
                return `${result}%`;
            } else {
                const numericValue = parseFloat(value) || 0;
                result = new Intl.NumberFormat('id-ID', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 2,
                }).format(numericValue);
                if (simbolSatuan === '%') {
                    return `${result}%`;
                }
                return simbolSatuan ? `${result} ${simbolSatuan}` : `${result}`;
            }
        },
        async fetchListData(page = 1) {
            this.loading.widgets = true;
            this.loading.kpiList = true;
            this.loading.picaList = true;
            this.loading.kpiUpdatesList = true;

            try {
                const params = {
                    limit: this.pagination.limit,
                    page,
                    bulan: this.selectedMonth,
                    tahun: this.selectedYear,
                };

                const response = await axios.get('/api/v1/dashboard-ceo/my-performance', { params });
                if (response?.status === 200 && response?.data?.result) {
                    this.processApiResponse(response.data.result, page);
                } else {
                    this.resetData();
                }
            } catch (error) {
                this.kpiUpdatesList = [];
                console.error('Gagal mengambil data list:', error);
            } finally {
                setTimeout(() => {
                    this.loading.widgets = false;
                    this.loading.kpiList = false;
                    this.loading.picaList = false;
                    this.loading.kpiUpdatesList = false;
                }, 300);
            }
        },
        processApiResponse(result, page) {
            this.kpiUpdatesList = result.Recent_Kpi_Update?.data || [];
            this.listDataWidget = result.Widget || {};
            this.is_full_year = result.is_full_year;

            this.kpiList = (result.kpis || []).map((kpi) => ({
                ...kpi,
                isActive: kpi.is_active ?? (kpi.Keterangan ?? '') !== 'Belum Aktif',
            }));
            this.deskripsiTerbuka = {};
            this.deskripsiTerpotong = {};

            this.pagination.page = page;
            this.pagination.totalPage = result.Recent_Kpi_Update?.total_page || 0;
            this.pagination.totalData = result.Recent_Kpi_Update?.total_data || 0;

            this.$nextTick(() => {
                this.selectedMonth = result.selectedMonth;
                this.selectedYear = result.selectedYear;
            });

            const selectedYearNum = parseInt(this.selectedYear || new Date().getFullYear(), 10);
            this.years = Array.from({ length: 5 }, (_, i) => ({
                label: (selectedYearNum - 2 + i).toString(),
                value: (selectedYearNum - 2 + i).toString(),
            }));

            // Process chart data
            const chartLineData = result.Chart_Line;
            const bulan = [];
            const skor = [];
            let ytd = null;
            if (chartLineData && Array.isArray(chartLineData.labels) && Array.isArray(chartLineData.series)) {
                chartLineData.labels.forEach((label, i) => {
                    const nilai = Number(chartLineData.series[i]);
                    if (String(label).toUpperCase() === 'YTD') {
                        ytd = Number.isFinite(nilai) ? nilai : null;
                    } else {
                        bulan.push(label);
                        skor.push(Number.isFinite(nilai) ? nilai : 0);
                    }
                });
            }
            this.grafikKinerja = {
                bulan,
                skor,
                ytd,
                ytdBulan: chartLineData?.ytd_bulan_terhitung || [],
                ytdTahun: chartLineData?.ytd_tahun || this.selectedYear,
            };

            // Process PICA data
            this.picaList = (result.Recent_Kpi_Update?.data || []).map((update) => ({
                id: update.id,
                kpiName: update.name,
                identifikasi: update.identifikasi,
                koreksi_aksi: update.koreksi_aksi,
                tanggal: update.Tanggal_Pelaksanaan,
                periode: update.periode,
                status: update.Keterangan,
                warna: update.Warna,
            }));
        },
        formatDate(dateString) {
            if (!dateString) return '-';
            const options = {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            };
            try {
                return new Date(dateString).toLocaleDateString('id-ID', options);
            } catch (e) {
                return dateString;
            }
        },
        resetData() {
            this.kpiUpdatesList = [];
            this.listDataWidget = {};
            this.pagination.totalData = 0;
            this.pagination.totalPage = 0;
            this.kpiList = [];
            this.picaList = [];
        },
        changePage(page) {
            if (page !== this.pagination.page && page >= 1 && page <= this.pagination.totalPage) {
                this.fetchListData(page);
            }
        },
        isiDetail(result) {
            const header = result.header;
            this.kpiRecentUpdateHeaders = header || {};
            this.isFullYear = header?.is_year;
            this.kpiItemsExample = result.details || [];
        },
        periodeDariFilter() {
            const bulan = String(this.selectedMonth || '').toUpperCase();
            const nilai =
                bulan === 'YTD'
                    ? 'ytd'
                    : bulan === 'ALL' || bulan === ''
                      ? 'full'
                      : String(this.selectedMonth).padStart(2, '0');
            return {
                nilai,
                tahun: Number(this.selectedYear) || new Date().getFullYear(),
            };
        },
        async muatDetailPeriode({ nilai, tahun }) {
            this.loading.detailKPI = true;
            try {
                const { data } = await axios.get('/api/v1/dashboard-my-performance/detail-periode', {
                    params: { tahun, periode: nilai },
                });
                const result = data.result;
                this.detailPeriode = {
                    nilai: result.periode.nilai,
                    tahun: result.periode.tahun,
                };
                this.detailNavigasi = result.navigasi || [];

                if (result.kosong) {
                    this.detailKosong = {
                        status: result.status_kosong,
                        label: result.periode.label,
                    };
                } else {
                    this.detailKosong = null;
                    this.isiDetail(result);
                }
            } catch (error) {
                this.detailPeriode = { nilai, tahun };
                this.detailKosong = { status: 'gagal', label: '' };
                console.error('Gagal memuat detail periode:', error);
            } finally {
                this.loading.detailKPI = false;
            }
        },
        terapkanPeriodeKeDashboard() {
            const { nilai, tahun } = this.detailPeriode;
            this.selectedYear = String(tahun);
            this.selectedMonth = nilai === 'ytd' ? 'YTD' : nilai === 'full' ? 'all' : nilai;
            this.closeDetailModal();
        },
        openDetailModal() {
            this.detailKosong = null;
            this.detailNavigasi = [];
            this.isModalVisible = true;
            this.muatDetailPeriode(this.periodeDariFilter());
        },
        closeDetailModal() {
            this.isModalVisible = false;
        },
        formatScore(value) {
            if (typeof value !== 'number' || !isFinite(value)) return 0;
            if (Number.isInteger(value)) return value;
            let rounded = parseFloat(value.toFixed(1));
            return Number.isInteger(rounded) ? rounded : rounded;
        },
    },
    watch: {
        'loading.kpiList'(sedangMemuat) {
            if (!sedangMemuat) this.ukurDeskripsi();
        },
        activeFilters: {
            handler() {
                this.fetchListData(1);
            },
            deep: true,
            immediate: true,
        },
    },
};
</script>

<style scoped src="../../../components/ui/kpi-dashboard-kokpit.css"></style>
