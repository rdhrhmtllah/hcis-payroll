<template>
    <div class="hcis-page-layout">
        <!-- 1. Executive Page Header -->
        <HcisHeader
            title="Team Performance"
            kicker="Performance · Team KPI"
            subtitle="Pantau capaian KPI seluruh karyawan: sebaran performa, peta capaian bulanan, dan rincian transaksi."
            icon="people-fill"
            sentiment="brand"
            variant="glass"
            size="md"
            :badge="labelPeriodeAktif"
            badge-variant="brand"
            animated
            class="mb-3"
        >
            <template #actions>
                <div class="d-none d-md-flex align-items-center gap-2">
                    <HcisButton
                        variant="primary"
                        size="sm"
                        icon="arrow-clockwise"
                        :loading="loading.kpiUpdatesList || loading.heatmap"
                        @click="fetchListData(1)"
                    >
                        <span>Segarkan</span>
                    </HcisButton>
                </div>
            </template>
        </HcisHeader>

        <!-- 2. Signature Executive Stat Cards Group (Directly below Header) -->
        <div class="hcis-kokpit-stats mb-3">
            <HcisStatGroup
                :stats="statCards"
                :cols="statCards.length"
                gap="sm"
                appearance="crystal"
                layout="compact"
            />
        </div>

        <!-- 3. Integrated Filter & Period Control Strip -->
        <div class="hcis-filter-strip mb-4">
            <div class="hcis-filter-strip__main">
                <!-- Quick Presets -->
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

                <!-- Month & Year Group in Date Controls -->
                <div class="hcis-filter-strip__date-group">
                    <div class="hcis-filter-field hcis-filter-field--compact">
                        <label class="hcis-filter-field__label d-none d-lg-inline-flex">Bulan:</label>
                        <el-select
                            v-model="selectedMonth"
                            filterable
                            placeholder="Pilih Bulan"
                            class="hcis-select-control hcis-select-control--month"
                        >
                            <el-option v-for="item in months" :key="item.value" :label="item.label" :value="item.value" />
                        </el-select>
                    </div>

                    <div class="hcis-filter-field hcis-filter-field--compact">
                        <label class="hcis-filter-field__label d-none d-lg-inline-flex">Tahun:</label>
                        <el-select
                            v-model="selectedYear"
                            filterable
                            placeholder="Pilih Tahun"
                            class="hcis-select-control hcis-select-control--year"
                        >
                            <el-option v-for="item in years" :key="item.value" :label="item.label" :value="item.value" />
                        </el-select>
                    </div>
                </div>

                <!-- Actions: Filter Organisasi Toggle Button & Reset -->
                <div class="hcis-filter-strip__actions">
                    <button
                        type="button"
                        class="hcis-filter-toggle-btn"
                        :class="{
                            'hcis-filter-toggle-btn--active': showOrgFilters,
                            'hcis-filter-toggle-btn--has-filter': activeOrgFilterCount > 0,
                        }"
                        @click="showOrgFilters = !showOrgFilters"
                    >
                        <i class="bi bi-funnel-fill text-primary"></i>
                        <span>Filter Organisasi</span>
                        <span v-if="activeOrgFilterCount > 0" class="hcis-filter-badge-count">
                            {{ activeOrgFilterCount }}
                        </span>
                        <i class="bi ms-1" :class="showOrgFilters ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                    </button>

                    <!-- Reset Filter Button if any custom filter is active -->
                    <button
                        v-if="hasActiveFilter"
                        type="button"
                        class="hcis-filter-reset-action"
                        title="Reset Semua Filter"
                        @click="resetAllFilters"
                    >
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                    </button>
                </div>
            </div>

            <!-- Expandable Organization Filter Drawer -->
            <transition name="hcis-filter-slide">
                <div v-if="showOrgFilters" class="hcis-filter-drawer">
                    <div class="hcis-filter-drawer__grid">
                        <!-- Divisi Filter -->
                        <div class="hcis-filter-field">
                            <label class="hcis-filter-field__label">
                                <i class="bi bi-diagram-3-fill text-primary me-1"></i>Divisi:
                            </label>
                            <el-select
                                v-model="selectedDivision"
                                filterable
                                multiple
                                clearable
                                collapse-tags
                                collapse-tags-tooltip
                                :max-collapse-tags="1"
                                placeholder="Semua Divisi"
                                class="hcis-select-control hcis-select-control--wide"
                                @change="handleDivisionChange"
                            >
                                <el-option
                                    v-for="item in divisions"
                                    :key="item.value"
                                    :label="item.label"
                                    :value="item.value"
                                    :disabled="selectedDivision.includes('ALL') && item.value !== 'ALL'"
                                />
                            </el-select>
                        </div>

                        <!-- Departemen Filter -->
                        <div class="hcis-filter-field">
                            <label class="hcis-filter-field__label">
                                <i class="bi bi-building text-primary me-1"></i>Departemen:
                            </label>
                            <el-select
                                v-model="selectedDepartment"
                                filterable
                                multiple
                                clearable
                                collapse-tags
                                collapse-tags-tooltip
                                :max-collapse-tags="1"
                                :placeholder="departmentPlaceholder"
                                :disabled="isDepartmentDisabled"
                                :loading="loading.departments"
                                class="hcis-select-control hcis-select-control--wide"
                                @change="handleDepartmentChange"
                            >
                                <el-option
                                    v-for="item in departments"
                                    :key="item.value"
                                    :label="item.label"
                                    :value="item.value"
                                    :disabled="selectedDepartment.includes('ALL') && item.value !== 'ALL'"
                                />
                            </el-select>
                        </div>

                        <!-- Level Filter -->
                        <div class="hcis-filter-field">
                            <label class="hcis-filter-field__label">
                                <i class="bi bi-layers-fill text-primary me-1"></i>Level:
                            </label>
                            <el-select
                                v-model="selectedLevel"
                                filterable
                                multiple
                                collapse-tags
                                collapse-tags-tooltip
                                :max-collapse-tags="1"
                                placeholder="Semua Level"
                                class="hcis-select-control hcis-select-control--wide"
                                @change="handleLevelChange"
                            >
                                <el-option
                                    v-for="item in level"
                                    :key="item.value"
                                    :label="item.label"
                                    :value="item.value"
                                    :disabled="selectedLevel.includes('ALL') && item.value !== 'ALL'"
                                />
                            </el-select>
                        </div>
                    </div>
                </div>
            </transition>
        </div>

        <!-- 4. Sebaran capaian & peringkat -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-xl-7">
                <HcisContainer
                    variant="glass"
                    sentiment="brand"
                    fluid
                    :title="chartTitleBar"
                    :subtitle="`Capaian tiap karyawan, urut dari tertinggi (${labelPeriodeAktif})`"
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
                                v-if="barPagination.totalData > 0 || kategoriGrafikBar.length > 0"
                                :label="`${barPagination.totalData || kategoriGrafikBar.length} Karyawan`"
                                variant="brand"
                                size="xs"
                            />
                        </div>
                    </template>

                    <div class="hcis-chart-pane">
                        <div class="hcis-chart-canvas-wrapper flex-grow-1">
                            <HcisChart
                                :card="false"
                                type="bar"
                                :categories="kategoriGrafikBar"
                                :series="seriGrafikBar"
                                :options="opsiGrafikBar"
                                :height="330"
                                :target-value="100"
                                target-label="Target 100%"
                                :tooltip-formatter="formatTooltipGrafikBar"
                                :loading="loading.kpiUpdatesList || loading.barChart"
                                empty-text="Belum ada data capaian pada filter ini"
                            />
                        </div>
                        <div class="hcis-chart-footer-strip mt-2">
                            <div class="d-flex align-items-center gap-1 text-truncate">
                                <i class="bi bi-info-circle text-primary flex-shrink-0"></i>
                                <span class="text-truncate">Warna bar (batang) sesuai dengan indikator range performance, dot line (garis putus-putus) adalah standar kinerja 100%</span>
                            </div>
                        </div>
                    </div>

                    <template #footer>
                        <div v-if="barTotalPage > 1" class="w-100">
                            <HcisPagination
                                :current-page="barCurrentPage"
                                :page-size="barPagination.limit"
                                :total="barPagination.totalData"
                                size="sm"
                                layout="total, prev, pager, next"
                                @current-change="changeBarPage"
                            />
                        </div>
                    </template>
                </HcisContainer>
            </div>

            <div class="col-12 col-xl-5">
                <HcisContainer
                    variant="glass"
                    :sentiment="papanPeringkat === 'top' ? 'success' : 'warning'"
                    fluid
                    title="Papan Peringkat"
                    :subtitle="
                        papanPeringkat === 'top'
                            ? `Top ${topPerformersList.length || selectedRankLimit} capaian tertinggi (${labelPeriodeAktif})`
                            : `Top ${underPerformersList.length || selectedRankLimit} perlu perhatian (${labelPeriodeAktif})`
                    "
                    :icon="papanPeringkat === 'top' ? 'trophy-fill' : 'exclamation-triangle-fill'"
                    class="h-100 hcis-container--peringkat"
                >
                    <template #actions>
                        <div class="hcis-peringkat-controls">
                            <el-select
                                v-model="selectedRankLimit"
                                placeholder="Limit"
                                class="hcis-select-control hcis-select-control--rank-limit"
                                style="width: 104px !important;"
                            >
                                <el-option :value="5" label="Top 5" />
                                <el-option :value="10" label="Top 10" />
                                <el-option :value="15" label="Top 15" />
                                <el-option :value="20" label="Top 20" />
                            </el-select>
                            <el-select
                                v-model="filterLevelPeringkat"
                                placeholder="Level"
                                filterable
                                multiple
                                clearable
                                collapse-tags
                                collapse-tags-tooltip
                                :max-collapse-tags="1"
                                class="hcis-select-control hcis-select-control--rank-level"
                                style="width: 130px !important;"
                                @change="gantiLevelPeringkat"
                            >
                                <el-option
                                    v-for="item in level"
                                    :key="item.value"
                                    :label="item.label"
                                    :value="item.value"
                                    :disabled="filterLevelPeringkat.includes('ALL') && item.value !== 'ALL'"
                                />
                            </el-select>
                        </div>
                    </template>

                    <div class="hcis-segment-tabs mb-3">
                        <button
                            type="button"
                            class="hcis-segment-tab hcis-segment-tab--top"
                            :class="{ 'hcis-segment-tab--active': papanPeringkat === 'top' }"
                            @click="papanPeringkat = 'top'"
                        >
                            <i class="bi bi-trophy-fill text-warning me-1 flex-shrink-0"></i>
                            <span class="text-truncate">Terbaik ({{ topPerformersList.length }})</span>
                        </button>
                        <button
                            type="button"
                            class="hcis-segment-tab hcis-segment-tab--under"
                            :class="{ 'hcis-segment-tab--active': papanPeringkat === 'under' }"
                            @click="papanPeringkat = 'under'"
                        >
                            <i class="bi bi-exclamation-circle-fill text-danger me-1 flex-shrink-0"></i>
                            <span class="text-truncate">Perlu Perhatian ({{ underPerformersList.length }})</span>
                        </button>
                    </div>

                    <div v-if="loading.kpiUpdatesList" class="hcis-peringkat-list d-flex flex-column gap-2">
                        <div v-for="n in Math.min(selectedRankLimit, 5)" :key="n" class="hcis-peringkat-baris">
                            <HcisSkeleton width="36px" height="36px" rounded="full" />
                            <div class="flex-grow-1 min-w-0">
                                <HcisSkeleton width="60%" height="0.875rem" rounded="sm" class="mb-1.5" />
                                <HcisSkeleton width="40%" height="0.65rem" rounded="sm" />
                            </div>
                            <HcisSkeleton width="52px" height="1.35rem" rounded="full" />
                        </div>
                    </div>

                    <div v-else-if="daftarPeringkat.length > 0" class="hcis-peringkat-list d-flex flex-column gap-2">
                        <div
                            v-for="(orang, i) in daftarPeringkat"
                            :key="orang.id || orang.Id_Update_Performance || i"
                            class="hcis-peringkat-baris"
                            :class="{ 'hcis-peringkat-baris--mati': !orang.Id_Update_Performance }"
                            :role="orang.Id_Update_Performance ? 'button' : null"
                            :tabindex="orang.Id_Update_Performance ? 0 : null"
                            :title="
                                orang.Id_Update_Performance
                                    ? 'Klik untuk melihat rincian KPI karyawan ini'
                                    : 'Tidak ada update performance pada periode ini, jadi rinciannya belum bisa dibuka'
                            "
                            @click="orang.Id_Update_Performance && openDetailModal(orang)"
                        >
                            <span class="hcis-peringkat-rank" :class="`hcis-peringkat-rank--${i + 1}`">
                                {{ i + 1 }}
                            </span>
                            <div class="flex-grow-1 min-w-0" style="min-width: 0; overflow: hidden;">
                                <p class="hcis-peringkat-nama text-truncate mb-0">{{ orang.Nama_Lengkap || '-' }}</p>
                                <p class="hcis-peringkat-unit text-truncate mb-0">
                                    {{ orang.Nama_Divisi || '-' }} &middot; {{ orang.Nama_Departemen || '-' }}
                                </p>
                            </div>
                            <div class="d-flex align-items-center gap-1.5 flex-shrink-0">
                                <HcisBadge
                                    :label="`${formatAngka(orang.Total_Score)}%`"
                                    :variant="warnaBandKinerja(orang.Total_Score).badgeVariant"
                                    size="sm"
                                />
                                <i class="bi bi-chevron-right hcis-peringkat-chevron"></i>
                            </div>
                        </div>
                    </div>

                    <HcisEmptyState
                        v-else
                        title="Belum Ada Data Peringkat"
                        description="Belum ada data performa karyawan yang tercatat untuk filter dan periode ini."
                        icon="people"
                        size="sm"
                        variant="glass"
                    />
                </HcisContainer>
            </div>
        </div>

        <!-- 5. Peta capaian (heatmap) -->
        <HcisContainer
            variant="glass"
            sentiment="violet"
            fluid
            :title="heatmapTitle"
            subtitle="Capaian tiap karyawan per bulan; kotak kosong berarti belum ada update disetujui"
            icon="grid-3x3-gap-fill"
            class="mb-4"
        >
            <template #actions>
                <HcisBadge
                    v-if="heatmapPagination.totalData > 0"
                    :label="`${heatmapPagination.totalData} Karyawan`"
                    variant="purple"
                    size="xs"
                />
            </template>

            <!-- Petunjuk Geser Horizontal di Tampilan Mobile -->
            <div class="d-flex d-md-none align-items-center gap-1.5 text-slate-500 mb-2 px-0.5" style="font-size: 0.6875rem; font-weight: 550;">
                <i class="bi bi-arrow-left-right text-violet-600 flex-shrink-0"></i>
                <span>Geser ke samping untuk melihat 12 bulan (Jan–Des)</span>
            </div>

            <div class="hcis-heatmap-scroll-wrapper">
                <div class="hcis-heatmap-chart-inner">
                    <HcisChart
                        :card="false"
                        type="heatmap"
                        value-label="Capaian"
                        value-suffix="%"
                        :categories="kategoriHeatmap"
                        :series="seriHeatmap"
                        :data="dataHeatmap"
                        :options="opsiHeatmap"
                        :tooltip-formatter="formatTooltipHeatmap"
                        :height="520"
                        :loading="loading.heatmap"
                        empty-text="Belum ada data capaian pada filter ini"
                    />
                </div>
            </div>

            <template #footer>
                <div v-if="heatmapTotalPage > 1" class="w-100">
                    <HcisPagination
                        :current-page="heatmapCurrentPage"
                        :page-size="heatmapPagination.limit"
                        :total="heatmapPagination.totalData"
                        size="sm"
                        layout="total, prev, pager, next"
                        @current-change="changeHeatmapPage"
                    />
                </div>
            </template>
        </HcisContainer>

        <!-- 6. Daftar update KPI terbaru -->
        <HcisContainer
            variant="glass"
            sentiment="brand"
            fluid
            :title="kpiUpdatesTitle"
            :subtitle="`${pagination.totalData || 0} karyawan pada filter terpilih`"
            icon="list-check"
            class="mb-4"
        >
            <template #actions>
                <div class="d-flex align-items-center gap-2">
                    <HcisBadge
                        :label="`Urut: ${statusSortDirection === 'asc' ? 'Terendah' : 'Tertinggi'}`"
                        variant="brand"
                        size="xs"
                    />
                </div>
            </template>

            <div class="hcis-kpi-toolbar mb-3">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-md-6">
                        <div class="hcis-search-box">
                            <i class="bi bi-search hcis-search-icon"></i>
                            <input
                                v-model="filteredSearch"
                                type="text"
                                class="form-control hcis-search-input"
                                placeholder="Cari nama, departemen, divisi..."
                            />
                            <button
                                v-if="filteredSearch"
                                type="button"
                                class="btn btn-sm hcis-search-clear"
                                @click="filteredSearch = ''"
                            >
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="hcis-segment-tabs">
                            <button
                                type="button"
                                class="hcis-segment-tab"
                                :class="{ 'hcis-segment-tab--active': statusSortDirection === 'desc' }"
                                @click="changeSortDirection('desc')"
                            >
                                <i class="bi bi-sort-down me-1"></i>Capaian Tertinggi
                            </button>
                            <button
                                type="button"
                                class="hcis-segment-tab"
                                :class="{ 'hcis-segment-tab--active': statusSortDirection === 'asc' }"
                                @click="changeSortDirection('asc')"
                            >
                                <i class="bi bi-sort-up me-1"></i>Capaian Terendah
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="loading.kpiUpdatesList || loading.isSearchingOrSorting" class="hcis-team-kpi-grid">
                <div v-for="n in 6" :key="n" class="hcis-team-kpi-card hcis-kpi-card--skeleton">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <HcisSkeleton width="45%" height="1.25rem" rounded="sm" />
                        <HcisSkeleton width="25%" height="1.25rem" rounded="full" />
                    </div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <HcisSkeleton width="40px" height="40px" rounded="full" />
                        <div class="flex-grow-1">
                            <HcisSkeleton width="60%" height="1.1rem" rounded="sm" class="mb-1" />
                            <HcisSkeleton width="40%" height="0.75rem" rounded="sm" />
                        </div>
                    </div>
                    <div class="p-2 bg-slate-50 rounded mb-2">
                        <div class="d-flex justify-content-between mb-2">
                            <HcisSkeleton width="25%" height="1rem" rounded="sm" />
                            <HcisSkeleton width="25%" height="1rem" rounded="sm" />
                            <HcisSkeleton width="25%" height="1rem" rounded="sm" />
                        </div>
                        <HcisSkeleton width="100%" height="0.4rem" rounded="full" />
                    </div>
                    <div class="d-flex justify-content-between pt-1">
                        <HcisSkeleton width="50%" height="0.8rem" rounded="sm" />
                        <HcisSkeleton width="15px" height="0.8rem" rounded="sm" />
                    </div>
                </div>
            </div>

            <div v-else-if="kpiUpdatesList.length > 0" class="hcis-team-kpi-grid">
                <div
                    v-for="orang in kpiUpdatesList"
                    :key="orang.id"
                    class="hcis-team-kpi-card"
                    role="button"
                    tabindex="0"
                    title="Klik untuk melihat rincian KPI karyawan ini"
                    @click="openDetailModal(orang)"
                >
                    <!-- Top Bar: Tags & Status Badge -->
                    <div class="hcis-team-kpi-card__top">
                        <div class="hcis-team-kpi-card__tags">
                            <span class="hcis-team-kpi-card__category" :title="orang.division || '-'">
                                <i class="bi bi-building me-1 opacity-75"></i>{{ orang.division || '-' }}
                            </span>
                            <span v-if="orang.subdivision" class="hcis-team-kpi-card__subdept" :title="orang.subdivision">
                                {{ orang.subdivision }}
                            </span>
                        </div>
                        <HcisBadge
                            :label="orang.status || 'N/A'"
                            :variant="variantStatusKaryawan(orang.status)"
                            size="sm"
                            dot
                        />
                    </div>

                    <!-- Profile Section: Avatar & Name -->
                    <div class="hcis-team-kpi-card__profile">
                        <div
                            class="hcis-team-kpi-card__avatar"
                            :class="`hcis-team-kpi-card__avatar--${variantStatusKaryawan(orang.status)}`"
                        >
                            {{ inisialNama(orang.name) }}
                        </div>
                        <div class="hcis-team-kpi-card__user-info min-w-0">
                            <h4 class="hcis-team-kpi-card__name text-truncate" :title="orang.name">
                                {{ orang.name }}
                            </h4>
                            <span class="hcis-team-kpi-card__user-unit text-truncate">
                                {{ orang.division || '-' }}
                            </span>
                        </div>
                    </div>

                    <!-- Performance Enclosed Capsule -->
                    <div class="hcis-team-kpi-card__capsule">
                        <div class="hcis-team-kpi-card__metrics">
                            <div class="hcis-team-kpi-card__metric">
                                <span class="hcis-team-kpi-card__metric-label">Capaian</span>
                                <span
                                    class="hcis-team-kpi-card__metric-value"
                                    :class="`text-${variantStatusKaryawan(orang.status)}`"
                                >
                                    {{ formatAngka(orang.achievement) }}%
                                </span>
                            </div>
                            <div class="hcis-team-kpi-card__metric-sep"></div>
                            <div class="hcis-team-kpi-card__metric">
                                <span class="hcis-team-kpi-card__metric-label">Target</span>
                                <span class="hcis-team-kpi-card__metric-value text-muted">100%</span>
                            </div>
                            <div class="hcis-team-kpi-card__metric-sep"></div>
                            <div class="hcis-team-kpi-card__metric text-end">
                                <span class="hcis-team-kpi-card__metric-label">Periode</span>
                                <span class="hcis-team-kpi-card__metric-value text-slate-700">{{ labelPeriodeAktif }}</span>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="hcis-team-kpi-card__progress-bar">
                            <div
                                class="hcis-team-kpi-card__progress-fill"
                                :class="`hcis-kpi-card__progress-fill--${variantStatusKaryawan(orang.status)}`"
                                :style="{ width: `${Math.min(Math.max(Number(orang.achievement || 0), 0), 100)}%` }"
                            />
                        </div>
                    </div>

                    <!-- Footer Action Strip -->
                    <div class="hcis-team-kpi-card__footer">
                        <span class="hcis-team-kpi-card__action-text">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Rincian Parameter KPI
                        </span>
                        <i class="bi bi-chevron-right hcis-team-kpi-card__chevron"></i>
                    </div>
                </div>
            </div>

            <HcisEmptyState
                v-else
                :title="filteredSearch ? 'Karyawan Tidak Ditemukan' : 'Belum Ada Pembaruan KPI'"
                :description="
                    filteredSearch
                        ? `Tidak ditemukan karyawan yang sesuai dengan kata kunci '${filteredSearch}'.`
                        : 'Belum ada data pembaruan KPI yang disetujui pada filter dan periode ini.'
                "
                icon="search"
                size="sm"
                variant="glass"
            >
                <template #actions>
                    <HcisButton
                        v-if="filteredSearch"
                        variant="outline"
                        size="xs"
                        icon="arrow-counterclockwise"
                        @click="filteredSearch = ''"
                    >
                        Reset Pencarian
                    </HcisButton>
                </template>
            </HcisEmptyState>

            <template #footer>
                <div v-if="pagination.totalPage > 1" class="w-100">
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

        <!-- 7. Modal detail: badan yang sama dengan dashboard individu -->
        <HcisDialog
            v-model="isModalVisible"
            title="Detail Employee Performance"
            kicker="Profil &amp; Capaian Karyawan"
            :description="detailNamaKaryawan || 'Rincian capaian KPI, profil, dan tindak lanjut perbaikan'"
            size="2xl"
            variant="glass"
            sentiment="brand"
            icon="person-badge"
            :badge="kpiRecentUpdateHeaders.Periode || ''"
            badge-variant="brand"
            :loading="loading.detailKPI"
            :esc-hint="true"
            @close="closeDetailModal"
        >
            <template #default>
                <PemilihPeriodeDetail
                    v-if="detailRefUpdate"
                    :nilai="detailPeriode.nilai"
                    :tahun="detailPeriode.tahun"
                    :navigasi="detailNavigasi"
                    :loading="loading.detailKPI"
                    @ganti="muatDetailPeriode"
                    @terapkan="terapkanPeriodeKeDashboard"
                />

                <KpiDetailIsi
                    :header="kpiRecentUpdateHeaders"
                    :items="kpiItemsExample"
                    :loading="loading.detailKPI"
                    :kosong="detailKosong"
                    :is-full-year="isFullYear"
                />
            </template>

            <template #footer-start>
                <div class="d-none d-sm-flex align-items-center gap-2 text-muted small text-nowrap">
                    <i class="bi bi-person text-primary"></i>
                    <span
                        >Karyawan: <strong class="text-dark">{{ detailNamaKaryawan || '-' }}</strong></span
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
    name: 'DashboardKpi',
    components: {
        ElSelect,
        ElOption,
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
        PemilihPeriodeDetail,
        KpiDetailIsi,
    },
    data() {
        const currentDate = new Date();
        let targetMonthVal = currentDate.getMonth(); // 0 = Jan, 1 = Feb, etc.
        let targetYearVal = currentDate.getFullYear();

        if (targetMonthVal === 0) {
            targetMonthVal = 12;
            targetYearVal -= 1;
        }

        const defaultMonthStr = targetMonthVal.toString().padStart(2, '0');
        const defaultYearStr = targetYearVal.toString();

        const currentYear = currentDate.getFullYear();
        const years = Array.from({ length: 10 }, (_, i) => ({
            label: (currentYear - 2 + i).toString(),
            value: (currentYear - 2 + i).toString(),
        }));

        return {
            isFullYear: false,
            agreementChecked: false,
            isSearchingOrSorting: false,
            isModalVisible: false,
            // Ganti periode DI DALAM modal detail: hanya isi modal yang berubah;
            // dashboard baru ikut bila pengguna menekan "Terapkan ke dashboard".
            detailRefUpdate: null,
            detailPeriode: { nilai: '01', tahun: new Date().getFullYear() },
            detailNavigasi: [],
            detailKosong: null,
            detailNamaKaryawan: '',
            isDepartmentDisabled: false,
            selectedDepartmentForLine: null,
            selectedYearForLine: null,
            statusSortDirection: null,
            listDataWidget: {},
            kpiRecentUpdateHeaders: {},
            kpiItemsExample: [],
            kpiUpdatesTitle: 'Recent KPI Updates',
            filteredSearch: '',
            heatmapTitle: 'Grafik Trend Performance',
            heatmapSeries: [],
            heatmapDetails: [],
            heatmapPagination: {
                page: 1,
                limit: 20,
                totalPage: 0,
                totalData: 0,
            },
            // Label bulan untuk sumbu X heatmap; lihat catatan labelGrafikBar.
            labelHeatmap: [],

            chartTitleBar: 'Grafik Employee Performance',
            chartBarSeries: [],
            chartBarDetails: [],
            // Sumbu X grafik batang. Dulu di sini ada seluruh konfigurasi ApexCharts;
            // HcisChart (ECharts) hanya perlu labelnya, sisanya milik komponen.
            labelGrafikBar: [],
            barPagination: {
                page: 1,
                limit: 20,
                totalPage: 0,
                totalData: 0,
            },
            divisions: [],
            departments: [],
            months: [
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
                { label: 'Full-Year', value: 'ALL' },
                { label: 'YTD', value: 'YTD' },
            ],
            level: [],
            kpiUpdatesList: [],
            topPerformersList: [],
            underPerformersList: [],
            years,
            selectedYear: defaultYearStr,
            selectedMonth: defaultMonthStr,
            selectedDivision: ['ALL'],
            selectedDepartment: ['ALL'],
            selectedLevel: ['ALL'],
            selectedRankLimit: 5,
            showOrgFilters: false,
            selectedLevelForTop: ['ALL'],
            filterLevelPeringkat: ['ALL'],
            selectedLevelForUnder: ['ALL'],
            loading: {
                divisions: true,
                // 'departments' dulu tertulis DUA kali di blok ini; yang terbaca
                // hanya yang terakhir, jadi nilai pertamanya tidak pernah berlaku.
                departments: true,
                detailKPI: false,
                kpiUpdatesList: true,
                isSearchingOrSorting: false,
                heatmap: false,
                barChart: false,
            },
            // Papan peringkat: satu wadah, dua sisi (terbaik / perlu perhatian).
            papanPeringkat: 'top',
            pagination: {
                page: 1,
                limit: 6,
                totalPage: 0,
                totalData: 0,
            },
        };
    },
    computed: {
        activeOrgFilterCount() {
            let count = 0;
            if (this.selectedDivision && !this.selectedDivision.includes('ALL') && this.selectedDivision.length > 0) {
                count += this.selectedDivision.length;
            }
            if (this.selectedDepartment && !this.selectedDepartment.includes('ALL') && this.selectedDepartment.length > 0) {
                count += this.selectedDepartment.length;
            }
            if (this.selectedLevel && !this.selectedLevel.includes('ALL') && this.selectedLevel.length > 0) {
                count += this.selectedLevel.length;
            }
            return count;
        },
        papanPeringkatTitle() {
            if (this.papanPeringkat === 'top') {
                const count = this.topPerformersList.length;
                return count > 0 ? `Top ${count} Performers` : `Top ${this.selectedRankLimit} Performers`;
            }
            const count = this.underPerformersList.length;
            return count > 0 ? `Perlu Perhatian (${count})` : 'Perlu Perhatian';
        },
        /** Label periode yang sedang dilihat; dipakai lencana header dan kartu. */
        labelPeriodeAktif() {
            const bulan = this.months.find((m) => m.value === this.selectedMonth);
            const namaBulan = bulan ? bulan.label : '';
            if (this.selectedMonth === 'ALL') return `Full-Year ${this.selectedYear || ''}`.trim();
            if (this.selectedMonth === 'YTD') return `YTD ${this.selectedYear || ''}`.trim();
            return `${namaBulan} ${this.selectedYear || ''}`.trim();
        },
        statCards() {
            const memuat = this.loading.kpiUpdatesList;
            const total = Number(this.listDataWidget?.Employee ?? 0);
            const onTarget = Number(this.listDataWidget?.On_Target ?? 0);
            const underTarget = Number(this.listDataWidget?.Under_Target ?? 0);
            const porsi = total > 0 ? Math.round((onTarget / total) * 100) : 0;

            return [
                {
                    id: 'total-karyawan',
                    label: 'Total Karyawan',
                    value: memuat ? '...' : total,
                    sentiment: 'brand',
                    icon: 'people-fill',
                    pattern: 'dots',
                    caption: this.labelPeriodeAktif,
                    loading: memuat,
                },
                {
                    id: 'on-target',
                    label: 'Mencapai Target',
                    value: memuat ? '...' : onTarget,
                    sentiment: 'success',
                    icon: 'graph-up-arrow',
                    pattern: 'grid',
                    caption: memuat ? '' : `${porsi}% dari total karyawan`,
                    loading: memuat,
                },
                {
                    id: 'under-target',
                    label: 'Di Bawah Target',
                    value: memuat ? '...' : underTarget,
                    sentiment: underTarget > 0 ? 'warning' : 'neutral',
                    icon: 'graph-down-arrow',
                    pattern: 'stripes',
                    caption: 'Capaian < 100%',
                    loading: memuat,
                },
            ];
        },
        /** Sumbu X grafik batang; datanya sama dengan yang dipakai kartu lama. */
        kategoriGrafikBar() {
            return this.labelGrafikBar || [];
        },
        seriGrafikBar() {
            const rawData = this.chartBarSeries?.[0]?.data || [];
            if (!rawData.length) return [];

            const dataWithMasterColors = rawData.map((val) => {
                const score = typeof val === 'object' && val !== null ? val.value : Number(val || 0);
                const band = this.warnaBandKinerja(score);
                return {
                    value: score,
                    itemStyle: {
                        borderRadius: [8, 8, 0, 0],
                        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                            { offset: 0, color: band.gradient.top },
                            { offset: 0.8, color: band.gradient.bottom },
                            { offset: 1, color: band.gradient.glow },
                        ]),
                        shadowColor: band.shadow,
                        shadowBlur: 8,
                        shadowOffsetY: 3,
                    },
                    emphasis: {
                        itemStyle: {
                            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                { offset: 0, color: band.gradient.top },
                                { offset: 1, color: band.gradient.bottom },
                            ]),
                            shadowBlur: 14,
                            shadowColor: band.shadow,
                        },
                    },
                };
            });

            return [
                {
                    name: 'Capaian',
                    data: dataWithMasterColors,
                },
            ];
        },
        opsiGrafikBar() {
            return {
                grid: {
                    top: 24,
                    right: 12,
                    bottom: 24,
                    left: 4,
                    containLabel: true,
                },
                xAxis: {
                    axisLabel: {
                        interval: 0,
                        rotate: 35,
                        fontSize: 9.5,
                        margin: 6,
                        formatter: (val) => {
                            if (!val) return '';
                            return val.length > 10 ? `${val.substring(0, 9)}…` : val;
                        },
                    },
                },
            };
        },
        barTotalPage() {
            return this.barPagination.totalPage;
        },
        barCurrentPage() {
            if (this.barTotalPage === 0) {
                return 1;
            }
            return Math.min(Math.max(this.barPagination.page, 1), this.barTotalPage);
        },
        barPageStart() {
            if (this.barTotalPage === 0) {
                return 0;
            }
            return (this.barCurrentPage - 1) * this.barPagination.limit + 1;
        },
        barPageEnd() {
            if (this.barTotalPage === 0) {
                return 0;
            }
            return Math.min(this.barCurrentPage * this.barPagination.limit, this.barPagination.totalData);
        },
        barVisiblePages() {
            const total = this.barTotalPage;
            const current = this.barCurrentPage;

            if (!total) {
                return [];
            }

            let start = current - 2;
            let end = current + 2;

            if (start < 1) {
                start = 1;
                end = Math.min(5, total);
            }

            if (end > total) {
                end = total;
                start = Math.max(1, total - 4);
            }

            const pages = [];
            for (let i = start; i <= end; i++) {
                pages.push(i);
            }
            return pages;
        },
        kategoriHeatmap() {
            return this.labelHeatmap || [];
        },
        /** Nama karyawan jadi sumbu Y heatmap (kontrak HcisChart: series[0].categories). */
        seriHeatmap() {
            const nama = (this.heatmapPaginatedSeries || []).map((s) => s.name);
            return nama.length ? [{ name: 'Capaian', categories: nama }] : [];
        },
        /**
         * Opsi visualMap piecewise untuk Heatmap KPI agar warnanya selaras
         * dengan master performa dan bar chart:
         * Excellent (>=106%), Good (90-105%), Satisfactory (75-89%), Not Perform (<75%).
         */
        opsiHeatmap() {
            return {
                grid: {
                    top: 24,
                    right: 20,
                    bottom: 48,
                    left: 12,
                    containLabel: true,
                },
                xAxis: {
                    type: 'category',
                    axisLine: { show: false },
                    axisTick: { show: false },
                    axisLabel: {
                        interval: 0,
                        fontSize: 11,
                        fontWeight: 700,
                        color: '#475569',
                    },
                },
                yAxis: {
                    type: 'category',
                    axisLine: { show: false },
                    axisTick: { show: false },
                    axisLabel: {
                        fontSize: 11,
                        fontWeight: 600,
                        color: '#1e293b',
                        formatter: (val) => {
                            if (!val) return '';
                            return val.length > 24 ? `${val.substring(0, 22)}…` : val;
                        },
                    },
                },
                series: [
                    {
                        label: {
                            show: true,
                            formatter: (p) => {
                                const val = Array.isArray(p.data) ? p.data[2] : (p.data.value ?? p.data);
                                if (val === null || val === undefined || val === '' || val < 0) return '';
                                const num = parseFloat(val);
                                if (isNaN(num) || num === 0) return '';
                                return Number.isInteger(num) ? `${num}%` : `${num.toFixed(1)}%`;
                            },
                            color: '#ffffff',
                            fontSize: 10,
                            fontWeight: 750,
                            fontFamily: 'inherit',
                            textShadowColor: 'rgba(0, 0, 0, 0.4)',
                            textShadowBlur: 3,
                        },
                        itemStyle: {
                            borderRadius: 6,
                            borderColor: '#ffffff',
                            borderWidth: 3,
                            shadowColor: 'rgba(15, 23, 42, 0.04)',
                            shadowBlur: 4,
                        },
                        emphasis: {
                            itemStyle: {
                                borderRadius: 7,
                                borderColor: '#ffffff',
                                borderWidth: 3.5,
                                shadowBlur: 14,
                                shadowColor: 'rgba(99, 102, 241, 0.5)',
                            },
                            label: {
                                show: true,
                                fontSize: 11,
                                fontWeight: 850,
                                color: '#ffffff',
                            },
                        },
                    },
                ],
                visualMap: {
                    type: 'piecewise',
                    orient: 'horizontal',
                    left: 'center',
                    bottom: 4,
                    itemGap: 12,
                    itemWidth: 14,
                    itemHeight: 10,
                    itemSymbol: 'roundRect',
                    text: null,
                    inRange: null,
                    pieces: [
                        { gte: 106, label: 'Excellent (≥106%)', color: '#10b981' },
                        { gte: 90, lt: 106, label: 'Good (90-105%)', color: '#6366f1' },
                        { gte: 75, lt: 90, label: 'Satisfactory (75-89%)', color: '#f59e0b' },
                        { gte: 0, lt: 75, label: 'Not Perform (<75%)', color: '#ef4444' },
                    ],
                    textStyle: {
                        fontSize: 10.5,
                        fontWeight: 600,
                        color: '#64748b',
                        fontFamily: 'inherit',
                    },
                },
            };
        },
        /**
         * Heatmap ECharts memakai [x, y, nilai]. Nilai -1 (dari server: belum ada
         * update disetujui) sengaja TIDAK dikirim, supaya kotaknya kosong alih-alih
         * digambar sebagai capaian negatif.
         */
        dataHeatmap() {
            const keluar = [];
            (this.heatmapPaginatedSeries || []).forEach((baris, y) => {
                (baris.data || []).forEach((nilai, x) => {
                    if (nilai === null || nilai === undefined || nilai < 0) return;
                    keluar.push([x, y, nilai]);
                });
            });
            return keluar;
        },
        daftarPeringkat() {
            return (this.papanPeringkat === 'top' ? this.topPerformersList : this.underPerformersList) || [];
        },
        visiblePages() {
            const total = this.pagination.totalPage;
            const current = this.pagination.page;
            let start = current - 2;
            let end = current + 2;

            if (start < 1) {
                start = 1;
                end = Math.min(5, total);
            }

            if (end > total) {
                end = total;
                start = Math.max(1, total - 4);
            }

            const pages = [];
            for (let i = start; i <= end; i++) {
                pages.push(i);
            }
            return pages;
        },
        heatmapTotalPage() {
            return this.heatmapPagination.totalPage;
        },
        heatmapCurrentPage() {
            if (this.heatmapTotalPage === 0) {
                return 1;
            }

            return Math.min(Math.max(this.heatmapPagination.page, 1), this.heatmapTotalPage);
        },
        heatmapPageStart() {
            if (this.heatmapTotalPage === 0) {
                return 0;
            }

            return (this.heatmapCurrentPage - 1) * this.heatmapPagination.limit + 1;
        },
        heatmapPageEnd() {
            if (this.heatmapTotalPage === 0) {
                return 0;
            }

            return Math.min(this.heatmapCurrentPage * this.heatmapPagination.limit, this.heatmapPagination.totalData);
        },
        heatmapVisiblePages() {
            const total = this.heatmapTotalPage;
            const current = this.heatmapCurrentPage;

            if (!total) {
                return [];
            }

            let start = current - 2;
            let end = current + 2;

            if (start < 1) {
                start = 1;
                end = Math.min(5, total);
            }

            if (end > total) {
                end = total;
                start = Math.max(1, total - 4);
            }

            const pages = [];
            for (let i = start; i <= end; i++) {
                pages.push(i);
            }

            return pages;
        },
        heatmapPaginatedSeries() {
            return this.heatmapSeries;
        },
        heatmapPaginatedDetails() {
            return this.heatmapDetails;
        },
        departmentPlaceholder() {
            return this.isDepartmentDisabled ? 'Select a Division first' : 'Departement';
        },
        hasActiveFilter() {
            const isCustomDiv = !this.selectedDivision.includes('ALL') || this.selectedDivision.length > 1;
            const isCustomDept = !this.selectedDepartment.includes('ALL') || this.selectedDepartment.length > 1;
            const isCustomLvl = !this.selectedLevel.includes('ALL') || this.selectedLevel.length > 1;

            const currentDate = new Date();
            let targetMonthVal = currentDate.getMonth();
            let targetYearVal = currentDate.getFullYear();
            if (targetMonthVal === 0) {
                targetMonthVal = 12;
                targetYearVal -= 1;
            }
            const defaultMonthStr = targetMonthVal.toString().padStart(2, '0');
            const defaultYearStr = targetYearVal.toString();
            const isCustomPeriod = this.selectedMonth !== defaultMonthStr || this.selectedYear !== defaultYearStr;

            return isCustomDiv || isCustomDept || isCustomLvl || isCustomPeriod || Boolean(this.filteredSearch);
        },
        activeFilters() {
            return {
                div: this.selectedDivision,
                dept: this.selectedDepartment,
                lvl: this.selectedLevel,
                mon: this.selectedMonth,
                yr: this.selectedYear,
                lvltp: this.selectedLevelForTop,
                lvlur: this.selectedLevelForUnder,
                rankLimit: this.selectedRankLimit,
            };
        },
    },
    methods: {
        isQuickPeriodActive(type) {
            const currentDate = new Date();
            let targetMonthVal = currentDate.getMonth();
            let targetYearVal = currentDate.getFullYear();
            if (targetMonthVal === 0) {
                targetMonthVal = 12;
                targetYearVal -= 1;
            }
            const defaultMonthStr = targetMonthVal.toString().padStart(2, '0');
            const defaultYearStr = targetYearVal.toString();

            if (type === 'current_month') {
                return this.selectedMonth === defaultMonthStr && this.selectedYear === defaultYearStr;
            }
            if (type === 'ytd') {
                return this.selectedMonth === 'YTD';
            }
            if (type === 'full_year') {
                return this.selectedMonth === 'ALL';
            }
            return false;
        },
        setQuickPeriod(type) {
            const currentDate = new Date();
            let targetMonthVal = currentDate.getMonth();
            let targetYearVal = currentDate.getFullYear();
            if (targetMonthVal === 0) {
                targetMonthVal = 12;
                targetYearVal -= 1;
            }
            if (type === 'current_month') {
                this.selectedMonth = targetMonthVal.toString().padStart(2, '0');
                this.selectedYear = targetYearVal.toString();
            } else if (type === 'ytd') {
                this.selectedMonth = 'YTD';
            } else if (type === 'full_year') {
                this.selectedMonth = 'ALL';
            }
        },
        resetAllFilters() {
            const currentDate = new Date();
            let targetMonthVal = currentDate.getMonth();
            let targetYearVal = currentDate.getFullYear();
            if (targetMonthVal === 0) {
                targetMonthVal = 12;
                targetYearVal -= 1;
            }
            this.selectedDivision = ['ALL'];
            this.selectedDepartment = ['ALL'];
            this.selectedLevel = ['ALL'];
            this.selectedMonth = targetMonthVal.toString().padStart(2, '0');
            this.selectedYear = targetYearVal.toString();
            this.filteredSearch = '';
            this.statusSortDirection = null;
        },
        inisialNama(nama) {
            if (!nama) return 'K';
            const clean = nama.replace(/^(dr\.|drg\.|h\.|hj\.|prof\.|ir\.)\s+/i, '').trim();
            const parts = clean.split(/\s+/).filter(Boolean);
            if (parts.length === 0) return 'K';
            if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
            return (parts[0][0] + parts[1][0]).toUpperCase();
        },
        warnaBandKinerja(skor) {
            const val = Number(skor || 0);
            if (val >= 106) {
                return {
                    kode: 'ET',
                    label: 'Excellent',
                    badgeVariant: 'success',
                    solidColor: '#10b981',
                    gradient: {
                        top: '#10b981',
                        bottom: '#059669',
                        glow: '#047857',
                    },
                    shadow: 'rgba(16, 185, 129, 0.35)',
                };
            }
            if (val >= 90) {
                return {
                    kode: 'GD',
                    label: 'Good',
                    badgeVariant: 'brand',
                    solidColor: '#6366f1',
                    gradient: {
                        top: '#6366f1',
                        bottom: '#4f46e5',
                        glow: '#4338ca',
                    },
                    shadow: 'rgba(99, 102, 241, 0.35)',
                };
            }
            if (val >= 75) {
                return {
                    kode: 'SY',
                    label: 'Satisfactory',
                    badgeVariant: 'warning',
                    solidColor: '#f59e0b',
                    gradient: {
                        top: '#fbbf24',
                        bottom: '#f59e0b',
                        glow: '#d97706',
                    },
                    shadow: 'rgba(245, 158, 11, 0.35)',
                };
            }
            return {
                kode: 'NP',
                label: 'Not Perform',
                badgeVariant: 'danger',
                solidColor: '#ef4444',
                gradient: {
                    top: '#f87171',
                    bottom: '#ef4444',
                    glow: '#dc2626',
                },
                shadow: 'rgba(239, 68, 68, 0.35)',
            };
        },
        /**
         * Tooltip grafik batang: satu batang = SATU karyawan, bukan jumlah orang
         * per tingkat. Departemen dan jabatannya ikut ditampilkan -- itulah isi
         * Chart_Bar.details, yang tanpa ini tidak pernah terbaca siapa pun.
         */
        formatTooltipGrafikBar(params) {
            const titik = Array.isArray(params) ? params[0] : params;
            if (!titik) return '';
            const rincian = this.chartBarDetails?.[titik.dataIndex] || {};
            const nama = rincian.nama || titik.name || '-';
            const unit = [rincian.departemen, rincian.jabatan].filter(Boolean).join(' &middot; ');
            const rawVal = typeof titik.value === 'object' && titik.value !== null ? titik.value.value : titik.value;
            const val = this.formatAngka(rawVal);
            const band = this.warnaBandKinerja(val);
            const dot = `<span style="display:inline-block;margin-right:6px;border-radius:50%;width:8px;height:8px;background-color:${band.solidColor};"></span>`;
            return `<div style="font-weight:700;margin-bottom:2px;">${nama}</div>
                ${unit ? `<div style="font-size:11.5px;color:#64748b;margin-bottom:4px;">${unit}</div>` : ''}
                <div style="font-size:11.5px;display:flex;justify-content:space-between;gap:14px;align-items:center;margin-top:4px;">
                    <span>${dot}${band.label}:</span>
                    <strong style="font-family:monospace;color:${band.solidColor};">${val}%</strong>
                </div>`;
        },
        /**
         * Tooltip interaktif heatmap dengan detail divisi/departemen dan indikator warna master.
         */
        formatTooltipHeatmap(p) {
            const d = p.data;
            if (!d) return '';
            const val = Array.isArray(d) ? d[2] : (d.value ?? d);
            if (val === null || val === undefined || val < 0) return '';
            const xLabel = this.kategoriHeatmap[d[0]] || d[0];
            const yLabel = this.seriHeatmap?.[0]?.categories?.[d[1]] || d[1];
            const rincian = this.heatmapDetails?.[d[1]] || {};
            const unit = [rincian.divisi, rincian.departemen].filter(Boolean).join(' &middot; ');
            const band = this.warnaBandKinerja(val);
            const dot = `<span style="display:inline-block;margin-right:6px;border-radius:50%;width:8px;height:8px;background-color:${band.solidColor};"></span>`;

            return `<div style="font-weight:700;margin-bottom:2px;">${yLabel} &middot; ${xLabel}</div>
                ${unit ? `<div style="font-size:11.5px;color:#64748b;margin-bottom:4px;">${unit}</div>` : ''}
                <div style="font-size:11.5px;display:flex;justify-content:space-between;gap:14px;align-items:center;margin-top:4px;">
                    <span>${dot}${band.label}:</span>
                    <strong style="font-family:monospace;color:${band.solidColor};">${this.formatAngka(val)}%</strong>
                </div>`;
        },
        formatAngka(nilai) {
            const angka = typeof nilai === 'number' ? nilai : parseFloat(nilai);
            if (isNaN(angka)) return 0;
            return Number.isInteger(angka) ? angka : parseFloat(angka.toFixed(1));
        },
        /** Warna lencana status; nama statusnya datang dari master performance. */
        variantStatusKaryawan(status) {
            const s = (status || '').toLowerCase();
            if (s.includes('excellent') || s.includes('sangat baik') || s === 'et') return 'success';
            if (s.includes('good') || s.includes('baik') || s === 'gd') return 'brand';
            if (s.includes('satisfactory') || s.includes('cukup') || s === 'sy') return 'warning';
            if (s.includes('not perform') || s.includes('kurang') || s.includes('poor') || s === 'np') return 'danger';
            return 'neutral';
        },
        async fetchListDivisi() {
            this.loading.divisions = true;
            try {
                const response = await axios.get('/dashboard-ceo/option/list-divisi');
                if (response.status === 200 && response.data?.result) {
                    const divisiList = response.data.result.map((item) => ({
                        value: item.Id_Divisi,
                        label: `${item.Nama_Divisi}`,
                    }));

                    this.divisions = [{ value: 'ALL', label: 'All' }, ...divisiList];
                }
            } catch (error) {
                console.error('Gagal mengambil data satuan:', error);
                this.divisions = [];
                this.departments = [];
            } finally {
                this.loading.divisions = false;
            }
        },
        async handleDivisionChange(currentSelection) {
            if (currentSelection.includes('ALL') && currentSelection.length > 1) {
                this.selectedDivision = ['ALL'];
                return;
            }

            this.selectedDepartment = [];
            this.departments = [];

            if (this.selectedDivision.length > 0) {
                this.isDepartmentDisabled = false;
                await this.fetchListDepartemen();

                if (this.selectedDivision.includes('ALL')) {
                    this.selectedDepartment = ['ALL'];
                }
            } else {
                this.isDepartmentDisabled = true;
            }
        },
        async fetchListDepartemen() {
            if (!this.selectedDivision || this.selectedDivision.length === 0) {
                this.departments = [];
                return;
            }

            this.loading.departments = true;
            try {
                const divisionIds = this.selectedDivision.join(',');
                const response = await axios.get(`/dashboard-ceo/option/list-departemen/${divisionIds}`);

                let departmentList = [];
                if (response.status === 200 && response.data?.result) {
                    departmentList = response.data.result.map((item) => ({
                        value: item.ID_Sub_Divisi,
                        label: `${item.nama_sub_divisi}`,
                    }));
                }

                this.departments = [{ value: 'ALL', label: 'All' }, ...departmentList];

                if (this.selectedDivision.includes('ALL')) {
                    this.selectedDepartment = ['ALL'];
                }
            } catch (error) {
                console.error('Gagal mengambil data departemen:', error);
                this.departments = [];
            } finally {
                this.loading.departments = false;
            }
        },
        async fetchListLevel() {
            this.loading.level = true;
            try {
                const response = await axios.get('/dashboard-ceo/option/list-level');
                if (response.status === 200 && response.data?.result) {
                    const levelList = response.data.result.map((item) => ({
                        value: item.Id_Level,
                        label: `${item.nama_level}`,
                    }));

                    this.level = [{ value: 'ALL', label: 'All' }, ...levelList];
                }
            } catch (error) {
                console.error('Gagal mengambil data satuan:', error);
                this.level = [];
            } finally {
                this.loading.level = false;
            }
        },
        async fetchListData(page = 1, heatmapPage = 1, barPage = 1) {
            this.loading.kpiUpdatesList = true;
            this.loading.heatmap = true;
            this.loading.barChart = true;
            try {
                const response = await axios.get('/api/v2/dashboard-ceo/all', {
                    params: this.buildDashboardParams(page, heatmapPage, barPage),
                });

                if (response.status === 200 && response.data?.result) {
                    this.kpiUpdatesList = response.data.result.Recent_Kpi_Update.data;
                    this.listDataWidget = response.data.result.Widget;
                    this.topPerformersList = response.data.result.Top_3_Performers;
                    this.underPerformersList = response.data.result.Under_3_Performers;
                    this.pagination.page = page;
                    this.is_full_year = response.data.result.Recent_Kpi_Update.is_full_year;
                    this.pagination.totalPage = response.data.result.Recent_Kpi_Update.total_page;
                    this.pagination.totalData = response.data.result.Recent_Kpi_Update.total_data;

                    this.syncBarChartFromResponse(response.data.result.Chart_Bar);
                    this.syncHeatmapFromResponse(response.data.result.Chart_Heatmap);
                } else {
                    this.kpiUpdatesList = [];
                    this.topPerformersList = [];
                    this.underPerformersList = [];
                    this.listDataWidget = {};
                    this.pagination.totalData = 0;
                    this.pagination.totalPage = 0;
                    this.syncBarChartFromResponse(null);
                    this.syncHeatmapFromResponse(null);
                }
            } catch (error) {
                this.kpiUpdatesList = [];
                console.error('Gagal mengambil data list:', error);
            } finally {
                this.loading.kpiUpdatesList = false;
                this.loading.heatmap = false;
                this.loading.barChart = false;
            }
        },
        buildDashboardParams(page = 1, heatmapPage = 1, barPage = 1) {
            const params = {
                limit: this.pagination.limit,
                page,
                q: this.filteredSearch,
                divisi: this.selectedDivision.join(','),
                departemen: this.selectedDepartment.join(','),
                level: this.selectedLevel.join(','),
                bulan: this.selectedMonth,
                tahun: this.selectedYear,
                levelTop: this.selectedLevelForTop.join(','),
                levelUnder: this.selectedLevelForUnder.join(','),
                top_limit: this.selectedRankLimit,
                heatmap_page: heatmapPage,
                heatmap_limit: this.heatmapPagination.limit,
                bar_page: barPage,
                bar_limit: this.barPagination.limit,
            };

            if (this.statusSortDirection) {
                params.sort_status = this.statusSortDirection;
            }

            return params;
        },
        syncBarChartFromResponse(barData) {
            if (barData && Array.isArray(barData.labels)) {
                this.chartBarSeries = [
                    {
                        name: 'Achievement',
                        data: barData.series || [],
                    },
                ];
                this.chartBarDetails = barData.details || [];
                this.labelGrafikBar = barData.labels || [];
                this.barPagination.page = barData.page || 1;
                this.barPagination.limit = barData.limit || 20;
                this.barPagination.totalPage = barData.total_page || 0;
                this.barPagination.totalData = barData.total_data || (barData.labels ? barData.labels.length : 0);
                return;
            }

            this.chartBarSeries = [];
            this.chartBarDetails = [];
            this.labelGrafikBar = [];
            this.barPagination.page = 1;
            this.barPagination.totalPage = 0;
            this.barPagination.totalData = 0;
        },
        syncHeatmapFromResponse(heatmapData) {
            if (heatmapData && heatmapData.series && heatmapData.categories) {
                this.heatmapSeries = heatmapData.series.map((item) => ({
                    ...item,
                    data: item.data.map((val) => (val == null ? -1 : val)),
                }));
                this.heatmapDetails = heatmapData.details;
                this.heatmapPagination.page = heatmapData.page || 1;
                this.heatmapPagination.limit = heatmapData.limit || 20;
                this.heatmapPagination.totalPage = heatmapData.total_page || 0;
                this.heatmapPagination.totalData = heatmapData.total_data || 0;
                this.labelHeatmap = heatmapData.categories;
                return;
            }

            this.heatmapSeries = [];
            this.heatmapDetails = [];
            this.heatmapPagination.page = 1;
            this.heatmapPagination.totalData = 0;
            this.heatmapPagination.totalPage = 0;
            this.labelHeatmap = [];
        },
        async fetchBarChartOnly(barPage = 1) {
            this.loading.barChart = true;
            try {
                const response = await axios.get('/api/v2/dashboard-ceo/all', {
                    params: this.buildDashboardParams(this.pagination.page, this.heatmapPagination.page, barPage),
                });

                if (response.status === 200 && response.data?.result) {
                    this.syncBarChartFromResponse(response.data.result.Chart_Bar);
                }
            } catch (error) {
                console.error('Gagal mengambil data bar chart:', error);
            } finally {
                this.loading.barChart = false;
            }
        },
        async fetchHeatmapOnly(heatmapPage = 1) {
            this.loading.heatmap = true;
            try {
                const response = await axios.get('/api/v2/dashboard-ceo/all', {
                    params: this.buildDashboardParams(this.pagination.page, heatmapPage, this.barPagination.page),
                });

                if (response.status === 200 && response.data?.result) {
                    this.syncHeatmapFromResponse(response.data.result.Chart_Heatmap);
                }
            } catch (error) {
                console.error('Gagal mengambil data heatmap:', error);
            } finally {
                this.loading.heatmap = false;
            }
        },
        async fetchRecentKpiOnly(page = 1) {
            this.loading.isSearchingOrSorting = true;
            try {
                const params = {
                    limit: this.pagination.limit,
                    page,
                    q: this.filteredSearch,
                    divisi: this.selectedDivision.join(','),
                    departemen: this.selectedDepartment.join(','),
                    level: this.selectedLevel.join(','),
                    bulan: this.selectedMonth,
                    tahun: this.selectedYear,
                    sort_status: this.statusSortDirection,
                };

                const response = await axios.get('/api/v2/dashboard-ceo/all', {
                    params,
                });

                if (response.status === 200 && response.data?.result) {
                    const recentUpdateData = response.data.result.Recent_Kpi_Update;
                    this.kpiUpdatesList = recentUpdateData.data;
                    this.pagination.page = page;
                    this.pagination.totalPage = recentUpdateData.total_page;
                    this.pagination.totalData = recentUpdateData.total_data;
                }
            } catch (error) {
                console.error('Gagal mengambil data list (search/sort):', error);
            } finally {
                this.loading.isSearchingOrSorting = false;
            }
        },
        async fetchDetailRecentKpi(item) {
            this.loading.detailKPI = true;
            this.kpiRecentUpdateHeaders = {};
            this.kpiItemsExample = [];

            try {
                // Mode detail: 'ytd' saat filter YTD, selain itu pakai flag full-year (boolean).
                const detailMode = String(this.selectedMonth).toUpperCase() === 'YTD' ? 'ytd' : this.is_full_year;
                const response = await axios.get(
                    `/api/v1/dashboard-ceo/detail/${item.Id_Pengajuan}/${item.Id_Update_Performance}/${detailMode}`,
                );

                if (response.status === 200 && response.data?.result) {
                    this.isiDetail(response.data.result);
                }
            } catch (error) {
                this.kpiRecentUpdateHeaders = {};
                this.kpiItemsExample = [];
                console.error('Gagal mengambil data list:', error);
            } finally {
                this.loading.detailKPI = false;
            }
        },
        debouncedSearch: debounce(function () {
            this.fetchListData(1);
        }, 500),
        changeSortDirection(direction) {
            if (this.statusSortDirection === direction) {
                this.statusSortDirection = null;
            } else {
                this.statusSortDirection = direction;
            }
            this.fetchRecentKpiOnly(1);
        },
        nextPage() {
            if (this.pagination.page < this.pagination.totalPage) {
                this.fetchListData(this.pagination.page + 1, this.heatmapPagination.page);
            }
        },
        prevPage() {
            if (this.pagination.page > 1) {
                this.fetchListData(this.pagination.page - 1, this.heatmapPagination.page);
            }
        },
        changePage(page) {
            if (page !== this.pagination.page) {
                this.fetchRecentKpiOnly(page);
            }
        },
        changeHeatmapPage(page) {
            if (page !== this.heatmapCurrentPage) {
                this.fetchHeatmapOnly(page);
            }
        },
        nextHeatmapPage() {
            if (this.heatmapCurrentPage < this.heatmapTotalPage) {
                this.fetchHeatmapOnly(this.heatmapCurrentPage + 1);
            }
        },
        prevHeatmapPage() {
            if (this.heatmapCurrentPage > 1) {
                this.fetchHeatmapOnly(this.heatmapCurrentPage - 1);
            }
        },
        changeBarPage(page) {
            if (page !== this.barCurrentPage) {
                this.fetchBarChartOnly(page);
            }
        },
        nextBarPage() {
            if (this.barCurrentPage < this.barTotalPage) {
                this.fetchBarChartOnly(this.barCurrentPage + 1);
            }
        },
        prevBarPage() {
            if (this.barCurrentPage > 1) {
                this.fetchBarChartOnly(this.barCurrentPage - 1);
            }
        },
        /** Pasang hasil detail (header, parameter, radial) ke modal. */
        isiDetail(result) {
            const header = result.header;
            this.kpiRecentUpdateHeaders = header;
            this.isFullYear = header?.is_year;
            this.kpiItemsExample = result.details;
        },
        /** Nilai periode detail dari filter dashboard: '01'..'12' | 'ytd' | 'full'. */
        periodeDariFilter() {
            const bulan = String(this.selectedMonth || '').toUpperCase();
            const nilai =
                bulan === 'YTD' ? 'ytd' : bulan === 'ALL' ? 'full' : String(this.selectedMonth).padStart(2, '0');
            return { nilai, tahun: Number(this.selectedYear) || new Date().getFullYear() };
        },
        async muatDetailPeriode({ nilai, tahun }) {
            if (!this.detailRefUpdate) return;
            this.loading.detailKPI = true;
            try {
                const { data } = await axios.get('/api/v1/dashboard-ceo/detail-periode', {
                    params: { ref_update: this.detailRefUpdate, tahun, periode: nilai },
                });
                const result = data.result;
                this.detailPeriode = { nilai: result.periode.nilai, tahun: result.periode.tahun };
                this.detailNavigasi = result.navigasi || [];

                if (result.kosong) {
                    this.detailKosong = { status: result.status_kosong, label: result.periode.label };
                } else {
                    this.detailKosong = null;
                    this.detailRefUpdate = result.rujukan?.id_update || this.detailRefUpdate;
                    this.isiDetail(result);
                    this.detailNamaKaryawan = result.header?.Nama_Lengkap || this.detailNamaKaryawan;
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
            // activeFilters memantau keduanya dan memuat ulang dashboard.
            this.selectedYear = String(tahun);
            this.selectedMonth = nilai === 'ytd' ? 'YTD' : nilai === 'full' ? 'ALL' : nilai;
            this.closeDetailModal();
        },
        openDetailModal(item) {
            this.detailKosong = null;
            this.detailNavigasi = [];
            this.detailNamaKaryawan = item.name || item.Nama_Lengkap || '';
            this.detailRefUpdate = item.Id_Update_Performance || null;
            this.isModalVisible = true;

            if (this.detailRefUpdate) {
                // Jalur baru: isi + strip navigasi sekaligus, dari periode filter.
                this.muatDetailPeriode(this.periodeDariFilter());
            } else {
                // Baris tanpa transaksi rujukan: tetap jalur lama, tanpa navigasi.
                this.fetchDetailRecentKpi(item);
            }
        },
        closeDetailModal() {
            this.isModalVisible = false;
        },
        handleDepartmentChange(currentSelection) {
            if (currentSelection.includes('ALL') && currentSelection.length > 1) {
                this.selectedDepartment = ['ALL'];
            }
        },
        handleLevelChange(currentSelection) {
            if (currentSelection.includes('ALL') && currentSelection.length > 1) {
                this.selectedLevel = ['ALL'];
            }
        },
        handleLevelChangeTop(currentSelection) {
            if (currentSelection.includes('ALL') && currentSelection.length > 1) {
                this.selectedLevelForTop = ['ALL'];
            }
        },
        gantiLevelPeringkat(currentSelection) {
            if (currentSelection.includes('ALL') && currentSelection.length > 1) {
                this.filterLevelPeringkat = ['ALL'];
            }
            this.selectedLevelForTop = [...this.filterLevelPeringkat];
            this.selectedLevelForUnder = [...this.filterLevelPeringkat];
        },
        handleLevelChangeUnder(currentSelection) {
            if (currentSelection.includes('ALL') && currentSelection.length > 1) {
                this.selectedLevelForUnder = ['ALL'];
            }
        },
    },
    async mounted() {
        await this.fetchListDivisi();
        await this.fetchListLevel();
        await this.fetchListDepartemen();
    },
    watch: {
        filteredSearch: debounce(function () {
            this.fetchRecentKpiOnly(1); // <-- GANTI ke method baru
        }, 500),
        activeFilters: {
            handler() {
                this.heatmapPagination.page = 1;
                this.barPagination.page = 1;
                this.fetchListData(1, 1, 1);
            },
            deep: true,
            immediate: true,
        },
    },
};
</script>

<style scoped src="../../../components/ui/kpi-dashboard-kokpit.css"></style>

<style scoped>
/* Grid Kartu Karyawan (Responsive Desktop Grid & Mobile Single Col) */
.hcis-team-kpi-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

@media (max-width: 1200px) {
    .hcis-team-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .hcis-team-kpi-grid {
        grid-template-columns: 1fr;
        gap: 0.625rem;
    }

    .hcis-team-kpi-card {
        padding: 0.8125rem 0.875rem !important;
        gap: 0.625rem !important;
        border-radius: var(--hcis-radius-md, 0.625rem) !important;
    }

    .hcis-team-kpi-card:active {
        transform: scale(0.988) !important;
        background: #f8fafc !important;
    }

    .hcis-team-kpi-card__top {
        gap: 0.35rem !important;
    }

    .hcis-team-kpi-card__category {
        max-width: 130px !important;
        font-size: 0.625rem !important;
        padding: 0.1rem 0.35rem !important;
    }

    .hcis-team-kpi-card__subdept {
        max-width: 120px !important;
        font-size: 0.625rem !important;
        padding: 0.1rem 0.35rem !important;
    }

    .hcis-team-kpi-card__profile {
        gap: 0.625rem !important;
    }

    .hcis-team-kpi-card__avatar {
        width: 2.25rem !important;
        height: 2.25rem !important;
        font-size: 0.75rem !important;
    }

    .hcis-team-kpi-card__name {
        font-size: 0.875rem !important;
        margin-bottom: 0.1rem !important;
    }

    .hcis-team-kpi-card__user-unit {
        font-size: 0.6875rem !important;
    }

    .hcis-team-kpi-card__capsule {
        padding: 0.5rem 0.625rem !important;
        gap: 0.375rem !important;
    }

    .hcis-team-kpi-card__metric-label {
        font-size: 0.5625rem !important;
    }

    .hcis-team-kpi-card__metric-value {
        font-size: 0.9375rem !important;
    }

    .hcis-team-kpi-card__metric:last-child .hcis-team-kpi-card__metric-value {
        font-size: 0.8125rem !important;
    }

    .hcis-team-kpi-card__metric-sep {
        height: 20px !important;
    }

    .hcis-team-kpi-card__progress-bar {
        height: 4.5px !important;
    }

    .hcis-team-kpi-card__footer {
        padding-top: 0.25rem !important;
        font-size: 0.6875rem !important;
    }

    .hcis-team-kpi-card__action-text {
        font-size: 0.6875rem !important;
    }
}

/* Individual Team KPI Card */
.hcis-team-kpi-card {
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-lg, 0.75rem);
    padding: 1rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.75rem;
    box-shadow:
        0 1px 3px 0 rgba(0, 0, 0, 0.04),
        0 1px 2px -1px rgba(0, 0, 0, 0.03);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    cursor: pointer;
    min-width: 0;
    position: relative;
}

.hcis-team-kpi-card:hover {
    transform: translateY(-3px);
    border-color: rgba(99, 102, 241, 0.4);
    box-shadow:
        0 8px 24px -4px rgba(99, 102, 241, 0.12),
        0 2px 6px -1px rgba(0, 0, 0, 0.04);
}

.hcis-team-kpi-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    min-width: 0;
}

.hcis-team-kpi-card__tags {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
    min-width: 0;
}

.hcis-team-kpi-card__category {
    font-size: 0.65625rem;
    font-weight: 700;
    color: var(--hcis-slate-600, #475569);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    background: rgba(241, 245, 249, 0.95);
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    border: 1px solid rgba(226, 232, 240, 0.85);
    line-height: 1.3;
    max-width: 180px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hcis-team-kpi-card__subdept {
    font-size: 0.65625rem;
    font-weight: 600;
    color: var(--hcis-indigo-700, #4338ca);
    background: rgba(238, 242, 255, 0.9);
    border: 1px solid rgba(199, 210, 254, 0.8);
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    line-height: 1.3;
    max-width: 160px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hcis-team-kpi-card__profile {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}

.hcis-team-kpi-card__avatar {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8125rem;
    font-weight: 800;
    color: #ffffff;
    flex-shrink: 0;
    background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.25);
}

.hcis-team-kpi-card__avatar--success {
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.25);
}

.hcis-team-kpi-card__avatar--brand {
    background: linear-gradient(135deg, #4338ca 0%, #6366f1 100%);
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.25);
}

.hcis-team-kpi-card__avatar--warning {
    background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
    box-shadow: 0 2px 6px rgba(245, 158, 11, 0.25);
}

.hcis-team-kpi-card__avatar--danger {
    background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.25);
}

.hcis-team-kpi-card__avatar--neutral {
    background: linear-gradient(135deg, #475569 0%, #64748b 100%);
    box-shadow: 0 2px 6px rgba(100, 116, 139, 0.25);
}

.hcis-team-kpi-card__user-info {
    flex: 1 1 auto;
    min-width: 0;
}

.hcis-team-kpi-card__name {
    font-size: 0.9375rem;
    font-weight: 750;
    color: var(--hcis-slate-800, #1e293b);
    margin: 0 0 0.15rem 0;
    line-height: 1.3;
}

.hcis-team-kpi-card__user-unit {
    font-size: 0.75rem;
    color: var(--hcis-slate-500, #64748b);
    display: block;
    line-height: 1.25;
}

/* Enclosed Capsule */
.hcis-team-kpi-card__capsule {
    background: rgba(248, 250, 252, 0.95);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-md, 0.5rem);
    padding: 0.625rem 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    transition: background-color 0.2s ease, border-color 0.2s ease;
}

.hcis-team-kpi-card:hover .hcis-team-kpi-card__capsule {
    background: rgba(241, 245, 249, 0.85);
    border-color: rgba(203, 213, 225, 0.95);
}

.hcis-team-kpi-card__metrics {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.hcis-team-kpi-card__metric {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.hcis-team-kpi-card__metric-label {
    font-size: 0.625rem;
    font-weight: 700;
    color: var(--hcis-slate-500, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    line-height: 1.2;
    margin-bottom: 0.15rem;
}

.hcis-team-kpi-card__metric-value {
    font-size: 1.0625rem;
    font-weight: 850;
    color: var(--hcis-slate-800, #1e293b);
    line-height: 1.2;
    font-feature-settings: 'tnum';
    font-variant-numeric: tabular-nums;
}

.hcis-team-kpi-card__metric-sep {
    width: 1px;
    height: 24px;
    background: rgba(226, 232, 240, 0.95);
    flex-shrink: 0;
}

.hcis-team-kpi-card__progress-bar {
    height: 5px;
    background: rgba(226, 232, 240, 0.95);
    border-radius: 9999px;
    overflow: hidden;
    width: 100%;
}

.hcis-team-kpi-card__progress-fill {
    height: 100%;
    border-radius: 9999px;
    transition: width 0.4s ease;
}

/* Footer Strip */
.hcis-team-kpi-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.75rem;
    color: var(--hcis-slate-500, #64748b);
    padding-top: 0.35rem;
    border-top: 1px solid rgba(241, 245, 249, 0.9);
}

.hcis-team-kpi-card__action-text {
    font-size: 0.71875rem;
    font-weight: 600;
    color: var(--hcis-slate-600, #475569);
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    transition: color 0.2s ease;
}

.hcis-team-kpi-card:hover .hcis-team-kpi-card__action-text {
    color: var(--hcis-indigo-600, #4f46e5);
}

.hcis-team-kpi-card__chevron {
    font-size: 0.75rem;
    color: var(--hcis-slate-400, #94a3b8);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.hcis-team-kpi-card:hover .hcis-team-kpi-card__chevron {
    color: var(--hcis-indigo-600, #4f46e5);
    transform: translateX(3px);
}

/* Tombol Reset Filter */
.hcis-filter-reset-btn {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-slate-600, #475569);
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-md, 0.5rem);
    padding: 0.3125rem 0.65rem;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    height: 32px;
}

.hcis-filter-reset-btn:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
    border-color: rgba(239, 68, 68, 0.25);
}

/* Papan peringkat (Terbaik / Perlu Perhatian) */
.hcis-peringkat-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: nowrap;
}

:deep(.hcis-select-control--rank-limit),
.hcis-select-control--rank-limit {
    width: 104px !important;
    min-width: 104px !important;
    flex-shrink: 0 !important;
}

:deep(.hcis-select-control--rank-level),
.hcis-select-control--rank-level {
    width: 130px !important;
    min-width: 130px !important;
    flex-shrink: 0 !important;
}

.hcis-segment-tabs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.25rem;
    background: rgba(241, 245, 249, 0.85);
    padding: 3px;
    border-radius: var(--hcis-radius-md, 0.5rem);
    border: 1px solid rgba(226, 232, 240, 0.85);
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.hcis-segment-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    border: none;
    background: transparent;
    color: var(--hcis-slate-600, #475569);
    font-size: 0.75rem;
    font-weight: 650;
    padding: 0.35rem 0.5rem;
    border-radius: calc(var(--hcis-radius-md, 0.5rem) - 2px);
    cursor: pointer;
    transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
    white-space: nowrap;
    text-align: center;
    line-height: 1.25;
    min-width: 0;
}

.hcis-segment-tab:hover {
    color: var(--hcis-slate-800, #1e293b);
    background: rgba(255, 255, 255, 0.5);
}

.hcis-segment-tab--active {
    background: #ffffff !important;
    font-weight: 750 !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(0, 0, 0, 0.04) !important;
}

.hcis-segment-tab--top.hcis-segment-tab--active {
    color: var(--hcis-emerald-700, #047857) !important;
}

.hcis-segment-tab--under.hcis-segment-tab--active {
    color: var(--hcis-red-600, #dc2626) !important;
}

.hcis-peringkat-list {
    min-height: 310px;
    max-height: 380px;
    overflow-y: auto;
    overflow-x: hidden !important;
    width: 100%;
    min-width: 0;
    padding-right: 0.25rem;
    box-sizing: border-box;
    scrollbar-width: thin;
    scrollbar-color: rgba(203, 213, 225, 0.8) transparent;
}

.hcis-peringkat-list::-webkit-scrollbar {
    width: 4px;
}

.hcis-peringkat-list::-webkit-scrollbar-track {
    background: transparent;
}

.hcis-peringkat-list::-webkit-scrollbar-thumb {
    background-color: rgba(203, 213, 225, 0.8);
    border-radius: 9999px;
}

.hcis-peringkat-baris {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 0.875rem;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-md, 0.625rem);
    background: #ffffff;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    cursor: pointer;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
    overflow: hidden;
}

.hcis-peringkat-baris:hover {
    border-color: var(--hcis-brand-300, #a5b4fc);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px -2px rgba(99, 102, 241, 0.1);
}

/* Tanpa rujukan transaksi, detailnya pasti kosong — barisnya tidak berpura-pura
   bisa diklik. Tetap terbaca penuh: yang hilang hanya janji yang tak bisa ditepati. */
.hcis-peringkat-baris--mati,
.hcis-peringkat-baris--mati:hover {
    cursor: default;
    transform: none;
    box-shadow: none;
    border-color: rgba(226, 232, 240, 0.9);
}

.hcis-peringkat-baris--mati .hcis-peringkat-chevron {
    visibility: hidden;
}

.hcis-peringkat-baris:active {
    transform: scale(0.99);
    background: rgba(248, 250, 252, 0.95);
}

.hcis-peringkat-rank {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    flex-shrink: 0;
    border-radius: 999px;
    font-size: 0.875rem;
    font-weight: 800;
    color: var(--hcis-slate-600, #475569);
    background: var(--hcis-slate-100, #f1f5f9);
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.04);
}

.hcis-peringkat-rank--1 {
    color: #b45309;
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border: 1px solid #fcd34d;
    box-shadow: 0 2px 6px -1px rgba(245, 158, 11, 0.35);
}

.hcis-peringkat-rank--2 {
    color: #475569;
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    border: 1px solid #cbd5e1;
    box-shadow: 0 2px 6px -1px rgba(100, 116, 139, 0.25);
}

.hcis-peringkat-rank--3 {
    color: #c2410c;
    background: linear-gradient(135deg, #ffedd5 0%, #fed7aa 100%);
    border: 1px solid #fdba74;
    box-shadow: 0 2px 6px -1px rgba(234, 88, 12, 0.3);
}

.hcis-peringkat-nama {
    margin: 0;
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--hcis-slate-800, #1e293b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
    max-width: 100%;
    display: block;
}

.hcis-peringkat-unit {
    margin: 0;
    font-size: 0.75rem;
    color: var(--hcis-slate-500, #64748b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
    max-width: 100%;
    display: block;
}

.hcis-peringkat-chevron {
    font-size: 0.75rem;
    color: var(--hcis-slate-400, #94a3b8);
    transition: all 0.2s ease;
}

.hcis-peringkat-baris:hover .hcis-peringkat-chevron {
    color: var(--hcis-indigo-600, #4f46e5);
    transform: translateX(2px);
}

@media (max-width: 768px) {
    .hcis-peringkat-controls {
        gap: 0.35rem;
    }

    :deep(.hcis-select-control--rank-limit),
    .hcis-select-control--rank-limit {
        display: none !important;
    }

    :deep(.hcis-select-control--rank-level),
    .hcis-select-control--rank-level {
        width: 110px !important;
        min-width: 110px !important;
    }

    .hcis-segment-tab {
        font-size: 0.71875rem;
        padding: 0.32rem 0.25rem;
    }

    .hcis-peringkat-list {
        min-height: auto;
        max-height: 360px;
    }

    .hcis-peringkat-baris {
        padding: 0.5rem 0.65rem;
        gap: 0.55rem;
    }

    .hcis-peringkat-rank {
        width: 1.85rem;
        height: 1.85rem;
        font-size: 0.75rem;
    }

    .hcis-peringkat-nama {
        font-size: 0.8125rem;
    }

    .hcis-peringkat-unit {
        font-size: 0.6875rem;
    }
}

/* Legend Strip & Dots */
.hcis-chart-legend-strip {
    background: rgba(248, 250, 252, 0.85);
    padding: 0.25rem 0.625rem;
    border-radius: var(--hcis-radius-full, 9999px);
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.hcis-chart-legend-item {
    font-size: 0.6875rem;
    font-weight: 600;
}

.hcis-legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 9999px;
    display: inline-block;
    flex-shrink: 0;
}

.hcis-legend-dot--et {
    background-color: #10b981;
    box-shadow: 0 0 6px rgba(16, 185, 129, 0.4);
}

.hcis-legend-dot--gd {
    background-color: #6366f1;
    box-shadow: 0 0 6px rgba(99, 102, 241, 0.4);
}

.hcis-legend-dot--sy {
    background-color: #f59e0b;
    box-shadow: 0 0 6px rgba(245, 158, 11, 0.4);
}

.hcis-legend-dot--np {
    background-color: #ef4444;
    box-shadow: 0 0 6px rgba(239, 68, 68, 0.4);
}

/* Heatmap Mobile Responsive & Horizontal Scroll */
.hcis-heatmap-scroll-wrapper {
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: rgba(203, 213, 225, 0.8) transparent;
    border-radius: var(--hcis-radius-md, 0.5rem);
}

.hcis-heatmap-scroll-wrapper::-webkit-scrollbar {
    height: 6px;
}

.hcis-heatmap-scroll-wrapper::-webkit-scrollbar-track {
    background: rgba(241, 245, 249, 0.6);
    border-radius: 9999px;
}

.hcis-heatmap-scroll-wrapper::-webkit-scrollbar-thumb {
    background-color: rgba(203, 213, 225, 0.85);
    border-radius: 9999px;
}

.hcis-heatmap-chart-inner {
    min-width: 840px;
    width: 100%;
}

@media (min-width: 992px) {
    .hcis-heatmap-chart-inner {
        min-width: 100%;
    }
}
</style>
