<template>
    <div class="pa-hav-map-page">
        <!-- Unified Header -->
        <HcisHeader
            title="Matriks 9-Box HAV Map"
            kicker="HASIL PENILAIAN · 9-BOX TALENT MATRIX"
            subtitle="Pemetaan talenta 3×3 berdasarkan sumbu Kinerja (KPI) dan Kompetensi untuk evaluasi talenta dan perencanaan suksesi."
            icon="grid-3x3-gap-fill"
            sentiment="purple"
            variant="glass"
            :size="isMobile ? 'sm' : 'md'"
            animated
            class="mb-3"
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
                <div class="pa-hav-header-actions">
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
                        <span v-if="activeCycle && !isMobile" class="pa-period-trigger__badge-wrap">
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
                        :label="isMobile ? '' : 'Segarkan'"
                        variant="glass"
                        :size="isMobile ? 'xs' : 'sm'"
                        leading-icon="arrow-clockwise"
                        :title="isMobile ? 'Segarkan' : ''"
                        :loading="havState.loading.value"
                        @click="muatDataHavMap"
                    />

                    <HcisButton
                        :href="`/performance-appraisal/pemantauan${selectedCycleId ? `?cycle=${selectedCycleId}&tab=HASIL` : '?tab=HASIL'}`"
                        :label="isMobile ? 'Hasil' : 'Monitoring & Hasil'"
                        variant="secondary"
                        :size="isMobile ? 'xs' : 'sm'"
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
                    <a href="/performance-appraisal/hav-map" class="pa-admin-switcher__item active pa-admin-switcher__item--active">
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

        <!-- EXECUTIVE TL;DR COCKPIT (4 CRYSTAL STAT CARDS - 2x2 ULTRA-COMPACT ON MOBILE) -->
        <div v-if="havState.ringkasan.value" class="pa-cockpit-section" aria-label="Ringkasan HAV Map">
            <HcisStatGroup
                :stats="havStats"
                :cols="isMobile ? 2 : 4"
                gap="xs"
                :layout="isMobile ? 'dense' : 'compact'"
                appearance="crystal"
                class="pa-stat-group"
            />
        </div>

        <!-- Comprehensive Multi-Select Filter Bar -->
        <div v-if="allParticipantsRaw.length" class="pa-hav-filter-wrap">
            <HcisFilterBar
                :search="searchQuery"
                search-placeholder="Cari nama karyawan, NIK, jabatan, divisi, atau departemen..."
                :search-debounce="200"
                :quick-chips="talentQuickChips"
                :active-chip-value="activeQuickChip"
                :filters="filterDefinitions"
                :filter-values="selectedFilterValues"
                variant="card"
                sentiment="purple"
                size="sm"
                glow
                :is-mobile="isMobile"
                :results-count="havState.loading.value ? null : totalFilteredParticipants"
                show-active-tags
                @update:search="onSearchChange"
                @chip-click="onQuickChipClick"
                @filter-change="onFilterChange"
                @reset="resetFilters"
            />
        </div>

        <!-- Error State -->
        <HcisResultState
            v-if="havState.error.value"
            :status="havState.errorStatus.value === 403 ? 'warning' : 'danger'"
            variant="flat"
            layout="inline"
            :title="havState.errorStatus.value === 403 ? 'Akses ditolak' : 'Gagal memuat HAV Map'"
            :description="havState.error.value"
            primary-text="Coba Lagi"
            primary-icon="arrow-clockwise"
            :show-retry="havState.errorStatus.value !== 403"
            @primary-click="muatDataHavMap"
        />

        <!-- Loading State Skeleton -->
        <div v-else-if="havState.loading.value && !havState.kuadran.value.length" class="pa-hav-loading">
            <div class="pa-hav-skeleton-grid">
                <div v-for="n in 9" :key="`skel-box-${n}`" class="pa-hav-skeleton-box">
                    <HcisSkeleton width="80px" height="1rem" rounded="sm" />
                    <HcisSkeleton width="140px" height="1.4rem" rounded="md" />
                    <HcisSkeleton width="100%" height="4rem" rounded="md" />
                </div>
            </div>
        </div>

        <!-- Empty State (No Quadrants / No Calculation Yet) -->
        <HcisEmptyState
            v-else-if="!havState.kuadran.value.length"
            variant="card"
            sentiment="brand"
            size="md"
            icon="grid-3x3"
            title="Belum Ada Data Pemetaan Matriks 9-Box"
            description="Siklus penilaian ini belum memiliki peserta yang selesai dihitung atau dipetakan ke kuadran Human Asset Value Map. Pastikan pengisian nilai sudah tuntas dan proses kalkulasi telah dijalankan."
        >
            <template #actions>
                <div class="d-flex align-items-center gap-2">
                    <HcisButton
                        label="Segarkan Data"
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :loading="havState.loading.value"
                        @click="muatDataHavMap"
                    />
                    <HcisButton
                        :href="`/performance-appraisal/pemantauan${selectedCycleId ? `?cycle=${selectedCycleId}&tab=HASIL` : '?tab=HASIL'}`"
                        label="Buka Monitoring & Hasil"
                        variant="brand"
                        size="sm"
                        leading-icon="hourglass-split"
                    />
                </div>
            </template>
        </HcisEmptyState>

        <!-- Loaded 9-Box Matrix Canvas -->
        <div v-else class="pa-hav-matrix-container">
            <!-- Framework Wrapper -->
            <div class="pa-hav-canvas">
                <!-- Canvas Control Bar -->
                <div class="pa-hav-canvas__header">
                    <div class="pa-hav-canvas__title">
                        <span class="pa-eyebrow">MATRIKS TALENTA 3×3</span>
                        <h3>Human Asset Value Map (Performance vs Competency)</h3>
                    </div>

                    <div class="pa-hav-canvas__controls">
                        <!-- Mode Tampilan Switcher (Matriks vs Daftar Kotak vs Scatter) -->
                        <div class="pa-view-segmented" role="tablist" aria-label="Mode Tampilan 9-Box">
                            <button
                                type="button"
                                role="tab"
                                :aria-selected="viewMode === 'matrix'"
                                :class="{ active: viewMode === 'matrix' }"
                                class="pa-view-segmented__btn"
                                @click="setViewMode('matrix')"
                            >
                                <span class="pa-view-segmented__icon-wrap">
                                    <HcisIcon name="grid-3x3" size="xs" />
                                </span>
                                <span class="pa-view-segmented__label">Matriks 3×3</span>
                            </button>
                            <button
                                type="button"
                                role="tab"
                                :aria-selected="viewMode === 'list'"
                                :class="{ active: viewMode === 'list' }"
                                class="pa-view-segmented__btn"
                                @click="setViewMode('list')"
                            >
                                <span class="pa-view-segmented__icon-wrap">
                                    <HcisIcon name="card-list" size="xs" />
                                </span>
                                <span class="pa-view-segmented__label">Daftar Kotak</span>
                                <span v-if="allParticipantsRaw.length" class="pa-view-segmented__badge">
                                    {{ totalFilteredParticipants }}
                                </span>
                            </button>
                            <button
                                type="button"
                                role="tab"
                                :aria-selected="viewMode === 'scatter'"
                                :class="{ active: viewMode === 'scatter' }"
                                class="pa-view-segmented__btn"
                                @click="setViewMode('scatter')"
                            >
                                <span class="pa-view-segmented__icon-wrap">
                                    <HcisIcon name="graph-up" size="xs" />
                                </span>
                                <span class="pa-view-segmented__label">Scatter 2D</span>
                            </button>
                        </div>

                        <HcisBadge
                            :label="isMobile ? 'Y: Komp · X: Kinerja' : 'Sumbu Y: Kompetensi · Sumbu X: Kinerja'"
                            variant="purple"
                            appearance="subtle"
                            size="sm"
                        />
                    </div>
                </div>

                <!-- Tampilan 1: 3x3 Axis Grid Container (Mode Matriks) -->
                <div v-if="viewMode === 'matrix'" class="pa-hav-framework-wrap">
                    <!-- Mobile Hint Strip -->
                    <div v-if="isMobile" class="pa-hav-mobile-hint">
                        <HcisIcon name="arrows-expand" size="2xs" />
                        <span>Geser ke samping untuk menjelajah seluruh sumbu 3×3, atau gunakan tab <strong>Daftar Kotak</strong> di atas.</span>
                    </div>

                    <!-- Column Axis Labels (X-Axis: Kinerja / KPI) -->
                    <div class="pa-hav-axis-cols">
                        <!-- Coordinate Intersection Anchor -->
                        <div class="pa-axis-corner-card">
                            <div class="pa-corner-head">
                                <HcisIcon name="grid-3x3" size="2xs" class="text-indigo-600" />
                                <span>MATRIKS 2D</span>
                            </div>
                            <div class="pa-corner-vectors">
                                <div class="pa-corner-vector">
                                    <HcisIcon name="arrow-up" size="2xs" class="text-purple-600" />
                                    <span>Kompetensi (Y)</span>
                                </div>
                                <div class="pa-corner-vector">
                                    <HcisIcon name="arrow-right" size="2xs" class="text-indigo-600" />
                                    <span>Kinerja (X)</span>
                                </div>
                            </div>
                        </div>

                        <!-- X-Axis Columns -->
                        <div class="pa-axis-col-card">
                            <div class="pa-axis-col-badge-row">
                                <span class="pa-axis-level-indicator is-low"></span>
                                <span class="pa-axis-col-kicker">&lt; STANDAR</span>
                            </div>
                            <strong class="pa-axis-col-name">Kinerja Rendah</strong>
                            <span class="pa-axis-col-desc">Capaian KPI di bawah target</span>
                        </div>

                        <div class="pa-axis-col-card">
                            <div class="pa-axis-col-badge-row">
                                <span class="pa-axis-level-indicator is-med"></span>
                                <span class="pa-axis-col-kicker">MEMENUHI STANDAR</span>
                            </div>
                            <strong class="pa-axis-col-name">Kinerja Sedang</strong>
                            <span class="pa-axis-col-desc">Capaian KPI sesuai ekspektasi</span>
                        </div>

                        <div class="pa-axis-col-card">
                            <div class="pa-axis-col-badge-row">
                                <span class="pa-axis-level-indicator is-high"></span>
                                <span class="pa-axis-col-kicker">MELAMPAUI STANDAR</span>
                            </div>
                            <strong class="pa-axis-col-name">Kinerja Tinggi</strong>
                            <span class="pa-axis-col-desc">Capaian KPI melampaui target</span>
                        </div>
                    </div>

                    <!-- 3x3 Grid with Y-Axis Indicators -->
                    <div class="pa-hav-grid-with-y">
                        <!-- Y-Axis Row Labels (Competency) -->
                        <div class="pa-hav-axis-rows">
                            <!-- High Competency -->
                            <div class="pa-axis-row-card is-high">
                                <HcisBadge label="TINGGI" variant="purple" appearance="solid" size="xs" />
                                <div class="pa-axis-row-meta">
                                    <strong class="pa-axis-row-name">Kompetensi Tinggi</strong>
                                    <span class="pa-axis-row-desc">Melampaui standar perilaku &amp; kompetensi</span>
                                </div>
                                <div class="pa-axis-row-line"></div>
                            </div>

                            <!-- Medium Competency -->
                            <div class="pa-axis-row-card is-med">
                                <HcisBadge label="SEDANG" variant="brand" appearance="solid" size="xs" />
                                <div class="pa-axis-row-meta">
                                    <strong class="pa-axis-row-name">Kompetensi Sedang</strong>
                                    <span class="pa-axis-row-desc">Memenuhi standar perilaku &amp; kompetensi</span>
                                </div>
                                <div class="pa-axis-row-line"></div>
                            </div>

                            <!-- Low Competency -->
                            <div class="pa-axis-row-card is-low">
                                <HcisBadge label="RENDAH" variant="warning" appearance="solid" size="xs" />
                                <div class="pa-axis-row-meta">
                                    <strong class="pa-axis-row-name">Kompetensi Rendah</strong>
                                    <span class="pa-axis-row-desc">Di bawah standar perilaku &amp; kompetensi</span>
                                </div>
                                <div class="pa-axis-row-line"></div>
                            </div>
                        </div>

                        <!-- 9 Quadrant Cards using HCIS Card Design System -->
                        <div class="pa-hav-matrix-grid" role="grid" aria-label="Sembilan kotak HAV Map">
                            <HcisCard
                                v-for="item in filteredQuadrants"
                                :key="item.Id_PA_Hav_Quadrant || item.Nomor_Box"
                                variant="glass"
                                :sentiment="getBoxSentiment(item)"
                                :pattern="getBoxPattern(item)"
                                :icon="getBoxIcon(item)"
                                size="sm"
                                class="pa-hav-quadrant-card"
                            >
                                <template #title>
                                    <span class="pa-quadrant-title">{{ item.Nama_Kuadran }}</span>
                                </template>

                                <template #badge>
                                    <div class="pa-quadrant-badges">
                                        <HcisBadge
                                            :label="`BOX ${item.Nomor_Box}`"
                                            :variant="getBoxSentiment(item)"
                                            appearance="solid"
                                            size="xs"
                                        />
                                        <HcisBadge
                                            :label="`${item.peserta ? item.peserta.length : 0} org`"
                                            :variant="(item.peserta && item.peserta.length > 0) ? getBoxSentiment(item) : 'neutral'"
                                            :appearance="(item.peserta && item.peserta.length > 0) ? 'glass' : 'subtle'"
                                            size="xs"
                                            :dot="item.peserta && item.peserta.length > 0"
                                        />
                                    </div>
                                </template>

                                <template #description>
                                    <span class="pa-quadrant-axes">
                                        {{ item.Tingkat_Kompetensi }} Komp. · {{ item.Tingkat_Performa }} Perf.
                                    </span>
                                </template>

                                <div class="pa-quadrant-body">
                                    <!-- Context Description / Recommendation Guideline -->
                                    <p v-if="item.Deskripsi || getBoxActionGuideline(item)" class="pa-quadrant-desc">
                                        {{ item.Deskripsi || getBoxActionGuideline(item) }}
                                    </p>

                                    <!-- 1. Participant List (Vertical Roster Layout) -->
                                    <div
                                        v-if="item.peserta && item.peserta.length"
                                        class="pa-quadrant-roster"
                                    >
                                        <div class="pa-roster-list">
                                            <div
                                                v-for="p in item.peserta"
                                                :key="p.Id_PA_Participant"
                                                class="pa-roster-item"
                                                :class="{
                                                    'is-matched': searchQuery && isParticipantMatched(p),
                                                }"
                                                :title="`${p.Nama_Karyawan_Saat_Cycle || p.Nama_Karyawan} (${p.Kode_Karyawan || '—'}) · ${p.Nama_Divisi_Saat_Cycle || p.Nama_Divisi || ''} - Klik untuk detail penilaian`"
                                                @click="openParticipantModal(p)"
                                            >
                                                <div class="pa-roster-avatar" :style="{ background: getAvatarBg(p), color: getAvatarColor(p) }">
                                                    {{ getInitials(p.Nama_Karyawan_Saat_Cycle || p.Nama_Karyawan) }}
                                                </div>
                                                <div class="pa-roster-meta">
                                                    <div class="pa-roster-name-row">
                                                        <span class="pa-roster-name">{{ p.Nama_Karyawan_Saat_Cycle || p.Nama_Karyawan }}</span>
                                                        <span v-if="p.Flag_Dikalibrasi === 'Y'" class="pa-roster-calibrated-dot" title="Dikalibrasi"></span>
                                                    </div>
                                                    <span class="pa-roster-sub">
                                                        {{ p.Kode_Karyawan }} &bull; {{ p.Nama_Jabatan_Saat_Cycle || p.Nama_Divisi_Saat_Cycle || '—' }}
                                                    </span>
                                                </div>
                                                <HcisIcon name="chevron-right" size="2xs" class="pa-roster-arrow" />
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 2. Clean Empty State for 0-participant Box -->
                                    <div v-else class="pa-quadrant-empty">
                                        <div class="pa-empty-badge-row">
                                            <HcisIcon name="person-dash" size="xs" class="text-muted" />
                                            <span class="pa-empty-label">0 Peserta Terpetakan</span>
                                        </div>
                                    </div>
                                </div>
                            </HcisCard>
                        </div>
                    </div>
                </div>

                <!-- Tampilan 2: Daftar Kotak (Mode Box Cards List - Ideal for Mobile) -->
                <div v-else-if="viewMode === 'list'" class="pa-hav-box-list-wrap">
                    <!-- List Mode Mobile Toolbar -->
                    <div class="pa-box-list-toolbar">
                        <div class="pa-box-list-toolbar__summary">
                            <span class="pa-box-list-toolbar__title">Ringkasan Kuadran Talenta</span>
                            <span class="pa-box-list-toolbar__meta">
                                <strong>{{ totalFilteredParticipants }}</strong> peserta terpetakan dalam <strong>9</strong> kuadran
                            </span>
                        </div>
                        <div class="pa-box-list-toolbar__actions">
                            <button
                                type="button"
                                class="pa-box-list-btn"
                                title="Buka semua kotak"
                                @click="bukaSemuaKotak"
                            >
                                <HcisIcon name="arrows-expand" size="2xs" />
                                <span>Buka Semua</span>
                            </button>
                            <button
                                type="button"
                                class="pa-box-list-btn"
                                title="Tutup semua kotak"
                                @click="tutupSemuaKotak"
                            >
                                <HcisIcon name="arrows-collapse" size="2xs" />
                                <span>Tutup Semua</span>
                            </button>
                        </div>
                    </div>

                    <div class="pa-box-list-grid">
                        <div
                            v-for="item in filteredQuadrants"
                            :key="`box-list-item-${item.Nomor_Box}`"
                            class="pa-box-card"
                            :class="[
                                `pa-box-card--box-${item.Nomor_Box}`,
                                { 'is-collapsed': !isBoxExpanded(item.Nomor_Box) }
                            ]"
                        >
                            <div
                                class="pa-box-card__header"
                                role="button"
                                tabindex="0"
                                :aria-expanded="isBoxExpanded(item.Nomor_Box)"
                                @click="toggleBoxExpansion(item.Nomor_Box)"
                                @keydown.enter.space.prevent="toggleBoxExpansion(item.Nomor_Box)"
                            >
                                <div class="pa-box-card__header-left">
                                    <HcisBadge
                                        :label="`BOX ${item.Nomor_Box}`"
                                        :variant="getBoxSentiment(item)"
                                        appearance="solid"
                                        size="xs"
                                    />
                                    <div class="pa-box-card__title-wrap">
                                        <strong class="pa-box-card__title">{{ item.Nama_Kuadran }}</strong>
                                        <span class="pa-box-card__axes">
                                            Komp: <strong>{{ item.Tingkat_Kompetensi }}</strong> &bull; Kinerja: <strong>{{ item.Tingkat_Performa }}</strong>
                                        </span>
                                    </div>
                                </div>
                                <div class="pa-box-card__header-right">
                                    <HcisBadge
                                        :label="`${item.peserta ? item.peserta.length : 0} Peserta`"
                                        :variant="(item.peserta && item.peserta.length > 0) ? getBoxSentiment(item) : 'neutral'"
                                        :appearance="(item.peserta && item.peserta.length > 0) ? 'glass' : 'subtle'"
                                        size="xs"
                                        :dot="item.peserta && item.peserta.length > 0"
                                    />
                                    <div class="pa-box-card__chevron" :class="{ 'is-expanded': isBoxExpanded(item.Nomor_Box) }">
                                        <HcisIcon name="chevron-down" size="2xs" />
                                    </div>
                                </div>
                            </div>

                            <p v-if="item.Deskripsi || getBoxActionGuideline(item)" class="pa-box-card__guideline">
                                <HcisIcon name="lightbulb-fill" size="2xs" class="text-amber-500" />
                                <span>{{ item.Deskripsi || getBoxActionGuideline(item) }}</span>
                            </p>

                            <!-- Participant List inside Card (Expandable) -->
                            <div v-if="isBoxExpanded(item.Nomor_Box)" class="pa-box-card__content">
                                <div v-if="item.peserta && item.peserta.length" class="pa-box-card__roster">
                                    <div class="pa-box-card__roster-head">
                                        <span>Daftar Anggota ({{ item.peserta.length }} Orang)</span>
                                        <span class="pa-box-card__roster-tip">Ketuk untuk rincian &amp; kalibrasi</span>
                                    </div>
                                    <div class="pa-box-card__roster-items">
                                        <div
                                            v-for="p in item.peserta"
                                            :key="p.Id_PA_Participant"
                                            class="pa-roster-item"
                                            :class="{ 'is-matched': searchQuery && isParticipantMatched(p) }"
                                            @click="openParticipantModal(p)"
                                        >
                                            <div class="pa-roster-avatar" :style="{ background: getAvatarBg(p), color: getAvatarColor(p) }">
                                                {{ getInitials(p.Nama_Karyawan_Saat_Cycle || p.Nama_Karyawan) }}
                                            </div>
                                            <div class="pa-roster-meta">
                                                <div class="pa-roster-name-row">
                                                    <span class="pa-roster-name">{{ p.Nama_Karyawan_Saat_Cycle || p.Nama_Karyawan }}</span>
                                                    <span
                                                        v-if="p.Flag_Dikalibrasi === 'Y'"
                                                        class="pa-roster-calibrated-badge"
                                                        title="Hasil telah dikalibrasi"
                                                    >
                                                        Dikalibrasi
                                                    </span>
                                                </div>
                                                <span class="pa-roster-sub">
                                                    {{ p.Kode_Karyawan }} &bull; {{ p.Nama_Jabatan_Saat_Cycle || p.Nama_Divisi_Saat_Cycle || '—' }}
                                                </span>
                                            </div>

                                            <!-- Mini Scores Summary -->
                                            <div v-if="p.Skor_KPI || p.Nilai_KPI || p.Skor_Kompetensi || p.Nilai_Kompetensi" class="pa-roster-scores">
                                                <span v-if="p.Skor_KPI || p.Nilai_KPI" class="pa-roster-score pa-roster-score--kpi" title="Skor KPI">
                                                    KPI: {{ formatSkorSingkat(p.Skor_KPI ?? p.Nilai_KPI) }}
                                                </span>
                                                <span v-if="p.Skor_Kompetensi || p.Nilai_Kompetensi" class="pa-roster-score pa-roster-score--comp" title="Skor Kompetensi">
                                                    Komp: {{ formatSkorSingkat(p.Skor_Kompetensi ?? p.Nilai_Kompetensi) }}
                                                </span>
                                            </div>

                                            <HcisIcon name="chevron-right" size="2xs" class="pa-roster-arrow" />
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="pa-box-card__empty">
                                    <HcisIcon name="person-dash" size="xs" class="text-muted" />
                                    <span>Tidak ada peserta pada kuadran ini</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tampilan 3: ECharts 2D Scatter Matrix Canvas (Mode Scatter) -->
                <div v-else class="pa-hav-scatter-wrap">
                    <div class="pa-scatter-chart-card">
                        <HcisChart
                            type="custom"
                            :options="scatterChartOptions"
                            :height="isMobile ? 380 : 520"
                            :card="false"
                            variant="flat"
                            @chart-click="handleScatterClick"
                        />
                        <div class="pa-scatter-footer-tip">
                            <HcisIcon name="info-circle-fill" size="2xs" class="text-indigo-500" />
                            <span>Ketuk titik lingkaran pada grafik untuk melihat rincian penilaian &amp; membuka lembar kalibrasi. Sumbu X: Capaian KPI &bull; Sumbu Y: Capaian Kompetensi.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Participant Detail Modal -->
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
            :can-calibrate="true"
            :can-calculate="true"
            @retry="muatDetailPeserta"
            @calibrate="simpanKalibrasi"
            @calculate="hitungUlang"
        />

        <!-- ================================================================= -->
        <!-- DIALOG: Pemilih Siklus & Periode Penilaian (Rich Period Selector) -->
        <!-- ================================================================= -->
        <HcisDialog
            v-model="bukaDialogPeriode"
            title="Pilih Siklus &amp; Periode Penilaian"
            subtitle="Pilih assessment cycle untuk memuat pemetaan matriks 9-Box HAV Map talenta."
            icon="calendar2-range-fill"
            sentiment="purple"
            variant="glass"
            :size="isMobile ? 'full' : 'lg'"
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
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import {
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisChart,
    HcisDialog,
    HcisEmptyState,
    HcisFilterBar,
    HcisHeader,
    HcisIcon,
    HcisResultState,
    HcisSkeleton,
    HcisStatCard,
    HcisStatGroup,
} from '@/components/ui';
import PaResultDialog from '../Cycle/components/PaResultDialog.vue';
import { usePaHavMap } from '../Cycle/composables/usePaHavMap.js';
import { usePaResult } from '../Cycle/composables/usePaResult.js';
import paApi from '../api/paApi.js';
import { cycleDariUrl } from '../utils/cycleDariUrl.js';
import { areaKuadran, batasSumbu } from '../utils/havScatter.js';

defineOptions({ name: 'PerformanceAppraisalHavMapPage' });

/* Responsive Viewport State */
const isMobile = ref(false);
const isTablet = ref(false);
const hasUserChosenViewMode = ref(false);
const viewMode = ref('matrix');

function setViewMode(mode) {
    hasUserChosenViewMode.value = true;
    viewMode.value = mode;
}

function cekUkuranLayar() {
    if (typeof window === 'undefined') return;
    const mobile = window.innerWidth <= 768;
    isMobile.value = mobile;
    isTablet.value = window.innerWidth > 768 && window.innerWidth <= 1024;
    if (!hasUserChosenViewMode.value) {
        viewMode.value = mobile ? 'list' : 'matrix';
    }
}

const loadingSiklus = ref(false);
const siklusList = ref([]);
const selectedCycleId = ref('');
const searchQuery = ref('');
const activeQuickChip = ref('ALL');

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

    muatDataHavMap();
}

const selectedFilterValues = reactive({
    divisi: [],
    sub_divisi: [],
    level: [],
    lokasi: [],
});

const selectedParticipant = ref(null);
const participantModalOpen = ref(false);

const havState = usePaHavMap(paApi);
const participantResultState = usePaResult(paApi);

const standardGridOrder = [7, 3, 1, 8, 4, 2, 9, 6, 5];

const activeCycle = computed(() => {
    return siklusList.value.find((c) => Number(c.Id_PA_Cycle) === Number(selectedCycleId.value)) || null;
});

const allParticipantsRaw = computed(() => {
    const list = havState.kuadran.value || [];
    const results = [];
    list.forEach((q) => {
        const ps = q.peserta || [];
        ps.forEach((p) => {
            results.push({
                ...p,
                Nomor_Box: q.Nomor_Box,
                Nama_Kuadran: q.Nama_Kuadran,
                Tingkat_Performa: q.Tingkat_Performa,
                Tingkat_Kompetensi: q.Tingkat_Kompetensi,
                Warna: q.Warna || getBoxColor(q),
            });
        });
    });
    return results;
});

const isAnyFilterActive = computed(() => {
    return (
        Boolean(searchQuery.value.trim()) ||
        activeQuickChip.value !== 'ALL' ||
        (Array.isArray(selectedFilterValues.divisi) && selectedFilterValues.divisi.length > 0) ||
        (Array.isArray(selectedFilterValues.sub_divisi) && selectedFilterValues.sub_divisi.length > 0) ||
        (Array.isArray(selectedFilterValues.level) && selectedFilterValues.level.length > 0) ||
        (Array.isArray(selectedFilterValues.lokasi) && selectedFilterValues.lokasi.length > 0)
    );
});

// Dynamic options for multi-select filters based on loaded dataset
const uniqueDivisiOptions = computed(() => {
    const set = new Set();
    allParticipantsRaw.value.forEach((p) => {
        const d = p.Nama_Divisi_Saat_Cycle || p.Nama_Divisi;
        if (d) set.add(d);
    });
    return Array.from(set).sort().map((val) => ({ value: val, label: val }));
});

const uniqueSubDivisiOptions = computed(() => {
    const set = new Set();
    allParticipantsRaw.value.forEach((p) => {
        const sd = p.Nama_Sub_Divisi_Saat_Cycle || p.Nama_Sub_Divisi;
        if (sd) set.add(sd);
    });
    return Array.from(set).sort().map((val) => ({ value: val, label: val }));
});

const uniqueLevelOptions = computed(() => {
    const set = new Set();
    allParticipantsRaw.value.forEach((p) => {
        const lvl = p.Nama_Level_Saat_Cycle || p.Nama_Level;
        if (lvl) set.add(lvl);
    });
    return Array.from(set).sort().map((val) => ({ value: val, label: val }));
});

const uniqueLokasiOptions = computed(() => {
    const set = new Set();
    allParticipantsRaw.value.forEach((p) => {
        const loc = p.Lokasi_Saat_Cycle || p.Lokasi;
        if (loc) set.add(loc);
    });
    return Array.from(set).sort().map((val) => ({ value: val, label: val }));
});

const filterDefinitions = computed(() => [
    {
        key: 'divisi',
        label: 'Divisi',
        type: 'select',
        multiple: true,
        filterable: true,
        clearable: true,
        collapseTags: true,
        maxCollapseTags: 1,
        options: uniqueDivisiOptions.value,
        placeholder: 'Pilih satu atau beberapa divisi...',
        col: 3,
    },
    {
        key: 'sub_divisi',
        label: 'Departemen / Sub Divisi',
        type: 'select',
        multiple: true,
        filterable: true,
        clearable: true,
        collapseTags: true,
        maxCollapseTags: 1,
        options: uniqueSubDivisiOptions.value,
        placeholder: 'Pilih satu atau beberapa departemen...',
        col: 3,
    },
    {
        key: 'level',
        label: 'Tingkat Jabatan (Level)',
        type: 'select',
        multiple: true,
        filterable: true,
        clearable: true,
        collapseTags: true,
        maxCollapseTags: 1,
        options: uniqueLevelOptions.value,
        placeholder: 'Pilih satu atau beberapa level...',
        col: 3,
    },
    {
        key: 'lokasi',
        label: 'Lokasi Kerja',
        type: 'select',
        multiple: true,
        filterable: true,
        clearable: true,
        collapseTags: true,
        maxCollapseTags: 1,
        options: uniqueLokasiOptions.value,
        placeholder: 'Pilih satu atau beberapa lokasi...',
        col: 3,
    },
]);

const talentQuickChips = computed(() => {
    const all = allParticipantsRaw.value;
    const starsCount = all.filter((p) => [1, 2, 3].includes(Number(p.Nomor_Box))).length;
    const coreCount = all.filter((p) => [4, 5, 6].includes(Number(p.Nomor_Box))).length;
    const developingCount = all.filter((p) => [7, 8, 9].includes(Number(p.Nomor_Box))).length;

    return [
        { label: 'Semua Talenta', value: 'ALL', count: all.length, badge: all.length },
        { label: 'Bintang & Potensial (Box 1-3)', value: 'STARS', count: starsCount, badge: starsCount, color: 'success' },
        { label: 'Talenta Inti (Box 4-6)', value: 'CORE', count: coreCount, badge: coreCount, color: 'brand' },
        { label: 'Perlu Bimbingan (Box 7-9)', value: 'DEVELOPING', count: developingCount, badge: developingCount, color: 'warning' },
    ];
});

function matchesAllFilters(p) {
    // Quick chip filter
    if (activeQuickChip.value === 'STARS') {
        if (![1, 2, 3].includes(Number(p.Nomor_Box))) return false;
    } else if (activeQuickChip.value === 'CORE') {
        if (![4, 5, 6].includes(Number(p.Nomor_Box))) return false;
    } else if (activeQuickChip.value === 'DEVELOPING') {
        if (![7, 8, 9].includes(Number(p.Nomor_Box))) return false;
    }

    // Dropdown multi-select filters
    if (Array.isArray(selectedFilterValues.divisi) && selectedFilterValues.divisi.length > 0) {
        const div = p.Nama_Divisi_Saat_Cycle || p.Nama_Divisi || '';
        if (!selectedFilterValues.divisi.includes(div)) return false;
    }

    if (Array.isArray(selectedFilterValues.sub_divisi) && selectedFilterValues.sub_divisi.length > 0) {
        const sdiv = p.Nama_Sub_Divisi_Saat_Cycle || p.Nama_Sub_Divisi || '';
        if (!selectedFilterValues.sub_divisi.includes(sdiv)) return false;
    }

    if (Array.isArray(selectedFilterValues.level) && selectedFilterValues.level.length > 0) {
        const lvl = p.Nama_Level_Saat_Cycle || p.Nama_Level || '';
        if (!selectedFilterValues.level.includes(lvl)) return false;
    }

    if (Array.isArray(selectedFilterValues.lokasi) && selectedFilterValues.lokasi.length > 0) {
        const loc = p.Lokasi_Saat_Cycle || p.Lokasi || '';
        if (!selectedFilterValues.lokasi.includes(loc)) return false;
    }

    // Search query
    if (searchQuery.value.trim()) {
        return isParticipantMatched(p);
    }

    return true;
}

const filteredQuadrants = computed(() => {
    const list = havState.kuadran.value || [];
    if (!list.length) return [];

    const map = new Map();
    list.forEach((q) => {
        const ps = q.peserta || [];
        const filtered = ps.filter((p) => matchesAllFilters({ ...p, Nomor_Box: q.Nomor_Box }));
        map.set(Number(q.Nomor_Box), {
            ...q,
            peserta: filtered,
            jumlah: filtered.length,
        });
    });

    const ordered = [];
    for (const num of standardGridOrder) {
        if (map.has(num)) {
            ordered.push(map.get(num));
        }
    }
    list.forEach((q) => {
        if (!standardGridOrder.includes(Number(q.Nomor_Box))) {
            const filtered = (q.peserta || []).filter((p) => matchesAllFilters({ ...p, Nomor_Box: q.Nomor_Box }));
            ordered.push({ ...q, peserta: filtered, jumlah: filtered.length });
        }
    });

    return ordered;
});

const totalFilteredParticipants = computed(() => {
    return filteredQuadrants.value.reduce((acc, q) => acc + (q.peserta?.length || 0), 0);
});

const allFilteredParticipants = computed(() => {
    const results = [];
    filteredQuadrants.value.forEach((q) => {
        (q.peserta || []).forEach((p) => {
            results.push({
                ...p,
                Nomor_Box: q.Nomor_Box,
                Nama_Kuadran: q.Nama_Kuadran,
                Tingkat_Performa: q.Tingkat_Performa,
                Tingkat_Kompetensi: q.Tingkat_Kompetensi,
                Warna: q.Warna || getBoxColor(q),
            });
        });
    });
    return results;
});

const havStats = computed(() => {
    const ringkasan = havState.ringkasan.value;
    if (!ringkasan) return [];

    return [
        {
            id: 'total-terpetakan',
            label: isMobile.value ? 'Total Terpetakan' : 'Total Terpetakan',
            value: formatAngka(totalFilteredParticipants.value),
            suffix: 'orang',
            sentiment: 'brand',
            icon: 'people-fill',
            appearance: 'crystal',
            layout: isMobile.value ? 'dense' : 'compact',
            caption: isMobile.value
                ? 'Total populasi'
                : (isAnyFilterActive.value ? `Filter aktif (${formatAngka(allParticipantsRaw.value.length)} total)` : 'Seluruh populasi hasil'),
            pattern: 'rings',
        },
        {
            id: 'sudah-dihitung',
            label: isMobile.value ? 'Sudah Dihitung' : 'Sudah Dihitung',
            // Terpetakan = sudah dihitung ATAU sudah terbit; keduanya punya kuadran.
            value: formatAngka(Number(ringkasan.dihitung || 0) + Number(ringkasan.terbit || 0)),
            suffix: 'orang',
            sentiment: 'success',
            icon: 'calculator-fill',
            appearance: 'crystal',
            layout: isMobile.value ? 'dense' : 'compact',
            caption: isMobile.value ? 'Terpetakan' : 'Terpetakan pada 9-Box',
            pattern: 'grid',
        },
        {
            id: 'hasil-tertahan',
            label: isMobile.value ? 'Hasil Tertahan' : 'Hasil Tertahan',
            value: formatAngka(ringkasan.tertahan),
            suffix: ringkasan.tertahan > 0 ? 'tertahan' : 'aman',
            sentiment: ringkasan.tertahan > 0 ? 'warning' : 'neutral',
            icon: ringkasan.tertahan > 0 ? 'hourglass-split' : 'patch-check-fill',
            appearance: 'crystal',
            layout: isMobile.value ? 'dense' : 'compact',
            caption: isMobile.value
                ? (ringkasan.tertahan > 0 ? 'Tertahan' : 'Semua terhitung')
                : (ringkasan.tertahan > 0 ? 'Menunggu kelengkapan' : 'Nol tertahan'),
            pattern: 'dots',
        },
        {
            id: 'sudah-diterbitkan',
            label: isMobile.value ? 'Sudah Diterbitkan' : 'Sudah Diterbitkan',
            value: formatAngka(ringkasan.terbit),
            suffix: 'rilis',
            sentiment: 'purple',
            icon: 'send-check-fill',
            appearance: 'crystal',
            layout: isMobile.value ? 'dense' : 'compact',
            caption: isMobile.value ? 'Siap rilis' : 'Dapat dilihat karyawan',
            pattern: 'hex',
        },
    ];
});

function onSearchChange(val) {
    searchQuery.value = val;
}

function onQuickChipClick(chip) {
    activeQuickChip.value = chip.value || 'ALL';
}

function onFilterChange({ key, value }) {
    if (value === '' || value === null || value === undefined) {
        selectedFilterValues[key] = [];
    } else {
        selectedFilterValues[key] = Array.isArray(value) ? value : [value];
    }
}

function resetFilters() {
    searchQuery.value = '';
    activeQuickChip.value = 'ALL';
    selectedFilterValues.divisi = [];
    selectedFilterValues.sub_divisi = [];
    selectedFilterValues.level = [];
    selectedFilterValues.lokasi = [];
}

function getBoxSentiment(item) {
    const n = Number(item?.Nomor_Box ?? item);
    switch (n) {
        case 1: return 'success';
        case 2: return 'purple';
        case 3: return 'brand';
        case 4: return 'teal';
        case 5: return 'warning';
        case 6: return 'info';
        case 7: return 'purple';
        case 8: return 'warning';
        case 9: return 'danger';
        default: return 'brand';
    }
}

function getBoxPattern(item) {
    const n = Number(item?.Nomor_Box ?? item);
    switch (n) {
        case 1: return 'rings';
        case 2: return 'hex';
        case 3: return 'grid';
        case 4: return 'dots';
        case 5: return 'stripes';
        case 6: return 'crosshair';
        case 7: return 'dots';
        case 8: return 'grid';
        case 9: return 'crosshair';
        default: return 'dots';
    }
}

function getBoxIcon(item) {
    const n = Number(item?.Nomor_Box ?? item);
    switch (n) {
        case 1: return 'star-fill';
        case 2: return 'lightning-charge-fill';
        case 3: return 'award-fill';
        case 4: return 'person-check-fill';
        case 5: return 'trophy-fill';
        case 6: return 'hand-thumbs-up-fill';
        case 7: return 'gem';
        case 8: return 'exclamation-triangle-fill';
        case 9: return 'x-octagon-fill';
        default: return 'grid-3x3';
    }
}

function getBoxColor(item) {
    if (item?.Warna) return item.Warna;
    const n = Number(item?.Nomor_Box ?? item);
    switch (n) {
        case 1: return '#10b981';
        case 2: return '#8b5cf6';
        case 3: return '#6366f1';
        case 4: return '#0d9488';
        case 5: return '#f59e0b';
        case 6: return '#0284c7';
        case 7: return '#7c3aed';
        case 8: return '#ea580c';
        case 9: return '#e11d48';
        default: return '#6366f1';
    }
}

/* Panduan tiap kotak datang dari master (`N_PA_Hav_Quadrant.Deskripsi`). Panduan cadangan yang
   dulu ditulis di sini bertentangan dengan master (mis. kotak 2 berperforma TINGGI disarankan
   "fokus peningkatan performa"), jadi dibuang. */
function getBoxActionGuideline() {
    return '';
}

const scatterChartOptions = computed(() => {
    const pts = allFilteredParticipants.value;

    const seriesData = pts.map((p) => {
        const kpi = Number(p.Skor_KPI ?? p.Nilai_KPI ?? p.Final_Score ?? 0);
        const komp = Number(p.Skor_Kompetensi ?? p.Nilai_Kompetensi ?? p.Final_Score ?? 0);
        const color = p.Warna || getBoxColor(p);
        return {
            value: [kpi, komp],
            participant: p,
            itemStyle: {
                color,
                shadowBlur: 10,
                shadowColor: color,
                opacity: 0.92,
            },
        };
    });

    const maxX = batasSumbu(seriesData.map((d) => d.value[0]));
    const maxY = batasSumbu(seriesData.map((d) => d.value[1]));

    return {
        tooltip: {
            trigger: 'item',
            backgroundColor: 'rgba(15, 23, 42, 0.95)',
            borderColor: 'rgba(255, 255, 255, 0.12)',
            borderWidth: 1,
            padding: [10, 14],
            textStyle: { color: '#ffffff', fontSize: 12 },
            formatter: (params) => {
                const p = params.data?.participant;
                if (!p) return '';
                const name = p.Nama_Karyawan_Saat_Cycle || p.Nama_Karyawan || 'Karyawan';
                const nik = p.Kode_Karyawan || '—';
                const jab = p.Nama_Jabatan_Saat_Cycle || p.Nama_Jabatan || '—';
                const div = p.Nama_Divisi_Saat_Cycle || p.Nama_Divisi || '—';
                const kpi = Number(p.Skor_KPI ?? p.Nilai_KPI ?? 0).toFixed(2);
                const komp = Number(p.Skor_Kompetensi ?? p.Nilai_Kompetensi ?? 0).toFixed(2);
                const final = Number(p.Final_Score ?? p.Nilai_Akhir ?? 0).toFixed(2);
                const boxName = p.Nama_Kuadran ? `Box ${p.Nomor_Box} · ${p.Nama_Kuadran}` : `Box ${p.Nomor_Box}`;

                return `
                    <div style="min-width: 220px; font-family: inherit;">
                        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.12); padding-bottom: 6px; margin-bottom: 8px;">
                            <strong style="color: #ffffff; font-size: 13px;">${name}</strong>
                            <span style="background: ${p.Warna || '#6366f1'}; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700;">Box ${p.Nomor_Box}</span>
                        </div>
                        <div style="color: #94a3b8; font-size: 11px; margin-bottom: 8px;">${nik} &bull; ${jab} (${div})</div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; font-size: 11px; background: rgba(255,255,255,0.06); padding: 6px 8px; border-radius: 6px; margin-bottom: 8px;">
                            <div><span style="color: #94a3b8;">Skor KPI:</span> <strong style="color: #38bdf8;">${kpi}</strong></div>
                            <div><span style="color: #94a3b8;">Kompetensi:</span> <strong style="color: #c084fc;">${komp}</strong></div>
                            <div><span style="color: #94a3b8;">Skor Akhir:</span> <strong style="color: #34d399;">${final}</strong></div>
                            <div><span style="color: #94a3b8;">Kuadran:</span> <strong style="color: #fbbf24;">${boxName}</strong></div>
                        </div>
                        <div style="font-size: 10px; color: #818cf8; text-align: center;">&bull; Klik titik untuk inspeksi &amp; kalibrasi &bull;</div>
                    </div>
                `;
            },
        },
        grid: {
            top: 40,
            right: 40,
            bottom: 60,
            left: 60,
        },
        xAxis: {
            type: 'value',
            min: 0,
            max: maxX,
            name: 'Kinerja / Capaian KPI (Sumbu X) →',
            nameLocation: 'middle',
            nameGap: 34,
            nameTextStyle: { color: '#475569', fontWeight: 600, fontSize: 12 },
            splitLine: { lineStyle: { color: 'rgba(99, 102, 241, 0.15)', type: 'dashed' } },
            axisLine: { lineStyle: { color: '#94a3b8' } },
            axisLabel: { color: '#64748b', fontSize: 11 },
        },
        yAxis: {
            type: 'value',
            min: 0,
            max: maxY,
            name: '← Kompetensi / Perilaku (Sumbu Y)',
            nameLocation: 'middle',
            nameGap: 42,
            nameTextStyle: { color: '#475569', fontWeight: 600, fontSize: 12 },
            splitLine: { lineStyle: { color: 'rgba(99, 102, 241, 0.15)', type: 'dashed' } },
            axisLine: { lineStyle: { color: '#94a3b8' } },
            axisLabel: { color: '#64748b', fontSize: 11 },
        },
        series: [
            {
                name: 'Peserta 9-Box',
                type: 'scatter',
                symbolSize: 14,
                emphasis: {
                    scale: 1.5,
                    itemStyle: {
                        borderColor: '#ffffff',
                        borderWidth: 2,
                    },
                },
                data: seriesData,
                markArea: {
                    silent: true,
                    itemStyle: {
                        borderWidth: 1,
                        borderColor: 'rgba(99, 102, 241, 0.18)',
                        borderType: 'dashed',
                    },
                    data: areaKuadran({
                        pitaKpi: havState.pita.value.kpi,
                        pitaKompetensi: havState.pita.value.kompetensi,
                        kuadran: havState.kuadran.value,
                        maxX,
                        maxY,
                        warna: getBoxColor,
                    }),
                },
            },
        ],
    };
});

function handleScatterClick(params) {
    const p = params?.data?.participant;
    if (p) {
        openParticipantModal(p);
    }
}

function isParticipantMatched(p) {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return true;
    return (
        String(p.Nama_Karyawan_Saat_Cycle || p.Nama_Karyawan || '').toLowerCase().includes(q) ||
        String(p.Kode_Karyawan || '').toLowerCase().includes(q) ||
        String(p.Nama_Jabatan_Saat_Cycle || p.Nama_Jabatan || '').toLowerCase().includes(q) ||
        String(p.Nama_Divisi_Saat_Cycle || p.Nama_Divisi || '').toLowerCase().includes(q) ||
        String(p.Nama_Sub_Divisi_Saat_Cycle || p.Nama_Sub_Divisi || '').toLowerCase().includes(q)
    );
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
            await muatDataHavMap();
        } else if (siklusList.value.length && !selectedCycleId.value) {
            const active = siklusList.value.find(
                (c) => c.Status_Cycle === 'CALCULATED' || c.Status_Cycle === 'PUBLISHED' || c.Status_Cycle === 'CLOSED',
            ) || siklusList.value[0];
            selectedCycleId.value = String(active.Id_PA_Cycle);

            if (typeof window !== 'undefined') {
                const url = new URL(window.location.href);
                url.searchParams.set('cycle', String(active.Id_PA_Cycle));
                window.history.replaceState({}, '', url.toString());
            }

            await muatDataHavMap();
        }
    } catch (e) {
        havState.error.value = e?.message || 'Gagal memuat siklus.';
    } finally {
        loadingSiklus.value = false;
    }
}

async function muatDataHavMap() {
    if (!selectedCycleId.value) return;
    await havState.muat(selectedCycleId.value);
}

function onCycleChange() {
    muatDataHavMap();
}

async function openParticipantModal(participant) {
    selectedParticipant.value = participant;
    participantModalOpen.value = true;
    await muatDetailPeserta();
}

async function muatDetailPeserta() {
    if (!selectedParticipant.value?.Id_PA_Participant) return;
    await participantResultState.muat(selectedParticipant.value.Id_PA_Participant);
}

async function simpanKalibrasi(data) {
    const pid = selectedParticipant.value?.Id_PA_Participant;
    const resultId = data?.resultId || selectedParticipant.value?.Id_PA_Result;
    const payload = data?.payload || data;
    if (!pid || !resultId) return;
    await participantResultState.kalibrasi(pid, resultId, payload);
    await muatDataHavMap();
}

async function hitungUlang() {
    const pid = selectedParticipant.value?.Id_PA_Participant;
    if (!pid) return;
    await participantResultState.hitung(pid);
    await muatDataHavMap();
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

function formatAngka(num) {
    return Number(num || 0).toLocaleString('id-ID');
}

/* Box List Accordion State */
const expandedBoxes = ref(new Set([1, 2, 3, 4, 5, 6, 7, 8, 9]));

function isBoxExpanded(boxNum) {
    return expandedBoxes.value.has(Number(boxNum));
}

function toggleBoxExpansion(boxNum) {
    const num = Number(boxNum);
    const next = new Set(expandedBoxes.value);
    if (next.has(num)) {
        next.delete(num);
    } else {
        next.add(num);
    }
    expandedBoxes.value = next;
}

function bukaSemuaKotak() {
    expandedBoxes.value = new Set([1, 2, 3, 4, 5, 6, 7, 8, 9]);
}

function tutupSemuaKotak() {
    expandedBoxes.value = new Set();
}

function getAvatarBg(p) {
    const colors = ['#e0e7ff', '#fce7f3', '#dcfce7', '#fef3c7', '#e0f2fe', '#f3e8ff'];
    const charCode = (p?.Nama_Karyawan_Saat_Cycle || p?.Nama_Karyawan || 'A').charCodeAt(0) || 0;
    return colors[charCode % colors.length];
}

function getAvatarColor(p) {
    const colors = ['#3730a3', '#9d174d', '#166534', '#92400e', '#075985', '#6b21a8'];
    const charCode = (p?.Nama_Karyawan_Saat_Cycle || p?.Nama_Karyawan || 'A').charCodeAt(0) || 0;
    return colors[charCode % colors.length];
}

function formatSkorSingkat(val) {
    if (val == null || val === '') return '—';
    const n = Number(val);
    return isNaN(n) ? String(val) : n.toFixed(1);
}

onMounted(() => {
    cekUkuranLayar();
    if (typeof window !== 'undefined') {
        window.addEventListener('resize', cekUkuranLayar, { passive: true });
    }
    muatSiklus();
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', cekUkuranLayar);
    }
});
</script>

<style scoped>
.pa-hav-map-page {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
    padding: 0 0 5rem 0;
    min-width: 0;
    max-width: 100%;
    width: 100%;
    box-sizing: border-box;
    overflow-x: clip;
}

.pa-hav-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
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
    background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%);
    color: #ffffff;
    box-shadow: 0 2px 4px rgba(124, 58, 237, 0.3);
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
    background: rgba(124, 58, 237, 0.1);
    color: #7c3aed;
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
    color: #7c3aed;
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
    gap: 0.35rem;
    padding: 0.25rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(241, 245, 249, 0.75);
    overflow-x: auto;
    scrollbar-width: none;
}
.pa-admin-switcher::-webkit-scrollbar {
    display: none;
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

.pa-cockpit-section {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

.pa-stat-group {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 0.75rem !important;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

.pa-hav-filter-wrap {
    width: 100%;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

/* Canvas 9-Box */
.pa-hav-matrix-container {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

.pa-hav-canvas {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
    padding: 1.25rem;
    border: 1px solid var(--hcis-border-default, #e2e8f0);
    border-radius: var(--hcis-radius-xl, 16px);
    background: var(--hcis-surface, #ffffff);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    min-width: 0;
    max-width: 100%;
    width: 100%;
    box-sizing: border-box;
    overflow: hidden;
}

.pa-hav-canvas__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.7);
}

.pa-hav-canvas__title h3 {
    margin: 0.15rem 0 0;
    font-size: 1.05rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-hav-canvas__controls {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.pa-view-segmented {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.04);
}

.pa-view-segmented__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.42rem 0.85rem;
    border: none;
    border-radius: 7px;
    background: transparent;
    color: var(--hcis-text-muted, #64748b);
    font: inherit;
    font-size: 0.78rem;
    font-weight: 650;
    cursor: pointer;
    transition: all 180ms cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
    user-select: none;
}

.pa-view-segmented__icon-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: transform 180ms ease, color 180ms ease;
}

.pa-view-segmented__btn:hover {
    color: #1e293b;
    background: rgba(255, 255, 255, 0.65);
}

.pa-view-segmented__btn.active {
    background: #ffffff;
    color: #4338ca;
    font-weight: 750;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08), 0 1px 2px rgba(67, 56, 202, 0.04);
}

.pa-view-segmented__btn.active .pa-view-segmented__icon-wrap {
    color: #4f46e5;
    transform: scale(1.08);
}

.pa-view-segmented__badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.08rem 0.38rem;
    border-radius: 999px;
    background: rgba(224, 231, 255, 0.9);
    color: #4338ca;
    font-size: 0.62rem;
    font-weight: 750;
    line-height: 1.1;
    transition: all 180ms ease;
}

.pa-view-segmented__btn.active .pa-view-segmented__badge {
    background: #4338ca;
    color: #ffffff;
}

.pa-hav-scatter-wrap {
    width: 100%;
}

.pa-scatter-chart-card {
    background: #ffffff;
    border-radius: var(--hcis-radius-lg, 12px);
    border: 1px solid #e2e8f0;
    padding: 0.75rem;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.02);
}

/* 3x3 Framework with Premium Axis Headers */
.pa-hav-framework-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    width: 100%;
    overflow-x: auto;
    padding-bottom: 0.5rem;
}

/* X-Axis Columns Header */
.pa-hav-axis-cols {
    display: grid;
    grid-template-columns: 145px repeat(3, minmax(0, 1fr));
    gap: 0.85rem;
    align-items: stretch;
}

/* Corner Intersection Anchor */
.pa-axis-corner-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.35rem;
    padding: 0.55rem 0.65rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.95), rgba(241, 245, 249, 0.85));
    border: 1px solid rgba(203, 213, 225, 0.8);
    box-shadow: inset 0 1px 0 #ffffff;
}

.pa-corner-head {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.62rem;
    font-weight: 800;
    color: var(--hcis-brand-600, #4f46e5);
    letter-spacing: 0.06em;
}

.pa-corner-vectors {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-corner-vector {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.66rem;
    font-weight: 650;
    color: var(--hcis-text-strong, #334155);
}

/* X-Axis Column Card */
.pa-axis-col-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.15rem;
    padding: 0.55rem 0.75rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    text-align: center;
}

.pa-axis-col-badge-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.pa-axis-level-indicator {
    width: 6px;
    height: 6px;
    border-radius: 999px;
}

.pa-axis-level-indicator.is-low { background: #f43f5e; }
.pa-axis-level-indicator.is-med { background: #0284c7; }
.pa-axis-level-indicator.is-high { background: #10b981; }

.pa-axis-col-kicker {
    font-size: 0.6rem;
    font-weight: 800;
    color: var(--hcis-text-muted, #64748b);
    letter-spacing: 0.05em;
}

.pa-axis-col-name {
    font-size: 0.8rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.25;
}

.pa-axis-col-desc {
    font-size: 0.64rem;
    color: var(--hcis-text-muted, #64748b);
}

/* Y-Axis Layout */
.pa-hav-grid-with-y {
    display: grid;
    grid-template-columns: 145px 1fr;
    gap: 0.85rem;
    align-items: stretch;
}

.pa-hav-axis-rows {
    display: grid;
    grid-template-rows: repeat(3, minmax(14.5rem, 1fr));
    gap: 0.85rem;
}

/* Y-Axis Row Card */
.pa-axis-row-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.85rem 0.65rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    text-align: center;
    position: relative;
    box-sizing: border-box;
}

.pa-axis-row-meta {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-axis-row-name {
    font-size: 0.78rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.25;
}

.pa-axis-row-desc {
    font-size: 0.62rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.3;
}

.pa-hav-matrix-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    grid-template-rows: repeat(3, minmax(14.5rem, 1fr));
    gap: 0.85rem;
}

/* Quadrant Card in HcisCard */
.pa-hav-quadrant-card {
    min-height: 14.5rem;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
}

.pa-quadrant-title {
    font-size: 0.88rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.25;
}

.pa-quadrant-badges {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.pa-quadrant-axes {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
    font-weight: 600;
}

.pa-quadrant-body {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    height: 100%;
}

.pa-quadrant-desc {
    margin: 0;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #475569);
    line-height: 1.35;
}

/* Structured Vertical Roster */
.pa-quadrant-roster {
    display: flex;
    flex-direction: column;
    margin-top: auto;
    width: 100%;
    min-height: 0;
}

.pa-roster-list {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    padding-top: 0.45rem;
    border-top: 1px dashed rgba(203, 213, 225, 0.7);
    max-height: 8.5rem;
    overflow-y: auto;
    padding-right: 0.2rem;
    scrollbar-width: thin;
}

.pa-roster-item {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.35rem 0.5rem;
    border-radius: var(--hcis-radius-sm, 6px);
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid rgba(226, 232, 240, 0.85);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
    cursor: pointer;
    transition: all 130ms ease;
}

.pa-roster-item:hover {
    background: #ffffff;
    border-color: var(--hcis-brand-500, #6366f1);
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.1);
    transform: translateX(2px);
}

.pa-roster-item.is-matched {
    border-color: var(--hcis-brand-500, #6366f1);
    background: #eef2ff;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
}

.pa-roster-avatar {
    display: grid;
    place-items: center;
    width: 1.4rem;
    height: 1.4rem;
    border-radius: 999px;
    background: #e0e7ff;
    color: #4338ca;
    font-size: 0.58rem;
    font-weight: 800;
    flex-shrink: 0;
}

.pa-roster-meta {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}

.pa-roster-name {
    font-size: 0.74rem;
    font-weight: 650;
    color: var(--hcis-text-strong, #1e293b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.25;
}

.pa-roster-sub {
    font-size: 0.62rem;
    color: var(--hcis-text-muted, #64748b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.2;
}

.pa-roster-arrow {
    color: #cbd5e1;
    transition: color 130ms ease, transform 130ms ease;
    flex-shrink: 0;
}

.pa-roster-item:hover .pa-roster-arrow {
    color: var(--hcis-brand-500, #6366f1);
    transform: translateX(1px);
}

/* 0-Participant State inside HcisCard */
.pa-quadrant-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: auto;
    padding: 0.65rem 0.5rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(248, 250, 252, 0.65);
    border: 1px dashed rgba(226, 232, 240, 0.85);
}

.pa-empty-badge-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.pa-empty-label {
    font-size: 0.68rem;
    font-weight: 650;
    color: var(--hcis-text-muted, #64748b);
}

.pa-eyebrow {
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.08em;
}

/* Skeletons */
.pa-hav-loading {
    padding: 1rem 0;
}

.pa-hav-skeleton-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.95rem;
}

.pa-hav-skeleton-box {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    min-height: 12rem;
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

/* Mobile Gesture Hint */
.pa-hav-mobile-hint {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.95), rgba(245, 243, 255, 0.95));
    border: 1px dashed rgba(199, 210, 254, 0.9);
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.7rem;
    color: #4338ca;
    line-height: 1.35;
    box-shadow: 0 1px 2px rgba(99, 102, 241, 0.05);
}

/* ------------------------------------------------------------------ */
/* Daftar Kotak (Box Cards List Mode) - Mobile Optimized View */
/* ------------------------------------------------------------------ */
.pa-hav-box-list-wrap {
    width: 100%;
}

.pa-box-list-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    margin-bottom: 0.85rem;
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.95), rgba(241, 245, 249, 0.9));
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-md, 10px);
    flex-wrap: wrap;
}

.pa-box-list-toolbar__summary {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.pa-box-list-toolbar__title {
    font-size: 0.8rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-box-list-toolbar__meta {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-box-list-toolbar__actions {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.pa-box-list-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.3rem 0.55rem;
    border-radius: var(--hcis-radius-sm, 6px);
    background: #ffffff;
    border: 1px solid rgba(203, 213, 225, 0.85);
    color: var(--hcis-text-strong, #334155);
    font-size: 0.68rem;
    font-weight: 650;
    cursor: pointer;
    transition: all 140ms ease;
    user-select: none;
}

.pa-box-list-btn:hover {
    background: #f8fafc;
    border-color: #818cf8;
    color: #4f46e5;
}

.pa-box-list-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
    gap: 0.85rem;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

.pa-box-card {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 0.85rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    transition: all 180ms ease;
    box-sizing: border-box;
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
}

.pa-box-card:hover {
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
    border-color: rgba(199, 210, 254, 0.9);
}

.pa-box-card.is-collapsed {
    background: rgba(248, 250, 252, 0.85);
    padding-bottom: 0.65rem;
}

.pa-box-card--box-1 { border-left: 4px solid #10b981; }
.pa-box-card--box-2 { border-left: 4px solid #06b6d4; }
.pa-box-card--box-3 { border-left: 4px solid #6366f1; }
.pa-box-card--box-4 { border-left: 4px solid #3b82f6; }
.pa-box-card--box-5 { border-left: 4px solid #8b5cf6; }
.pa-box-card--box-6 { border-left: 4px solid #ec4899; }
.pa-box-card--box-7 { border-left: 4px solid #f59e0b; }
.pa-box-card--box-8 { border-left: 4px solid #f97316; }
.pa-box-card--box-9 { border-left: 4px solid #ef4444; }

.pa-box-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
    cursor: pointer;
    user-select: none;
    border-radius: var(--hcis-radius-md, 8px);
    transition: opacity 140ms ease;
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
}

.pa-box-card__header:hover {
    opacity: 0.9;
}

.pa-box-card__header-left {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    min-width: 0;
    flex: 1;
    overflow: hidden;
}

.pa-box-card__header-right {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    flex-shrink: 0;
}

.pa-box-card__chevron {
    display: grid;
    place-items: center;
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 999px;
    background: rgba(241, 245, 249, 0.9);
    color: var(--hcis-text-muted, #64748b);
    transition: transform 200ms cubic-bezier(0.4, 0, 0.2, 1), background 200ms ease, color 200ms ease;
    flex-shrink: 0;
}

.pa-box-card__chevron.is-expanded {
    transform: rotate(180deg);
    background: #e0e7ff;
    color: #4338ca;
}

.pa-box-card__title-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
    flex: 1;
    overflow: hidden;
}

.pa-box-card__title {
    font-size: 0.88rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.25;
    word-break: break-word;
}

.pa-box-card__axes {
    font-size: 0.66rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.2;
    word-break: break-word;
}

.pa-box-card__axes strong {
    color: var(--hcis-text-strong, #334155);
}

.pa-box-card__guideline {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    margin: 0;
    padding: 0.45rem 0.6rem;
    background: rgba(248, 250, 252, 0.85);
    border-radius: var(--hcis-radius-sm, 6px);
    border: 1px solid rgba(226, 232, 240, 0.7);
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #475569);
    line-height: 1.35;
    word-break: break-word;
    overflow-wrap: break-word;
    max-width: 100%;
    box-sizing: border-box;
}

.pa-box-card__content {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

.pa-box-card__roster {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    margin-top: auto;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

.pa-box-card__roster-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.35rem;
    font-size: 0.64rem;
    font-weight: 750;
    color: var(--hcis-text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    min-width: 0;
    flex-wrap: wrap;
}

.pa-box-card__roster-tip {
    font-size: 0.6rem;
    font-weight: 500;
    color: #818cf8;
    text-transform: none;
    letter-spacing: normal;
}

.pa-box-card__roster-items {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    max-height: 15rem;
    overflow-y: auto;
    padding-right: 0.2rem;
    scrollbar-width: thin;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

.pa-roster-name-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
    overflow: hidden;
}

.pa-roster-calibrated-dot {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: #f59e0b;
    flex-shrink: 0;
}

.pa-roster-calibrated-badge {
    padding: 0.08rem 0.35rem;
    border-radius: 999px;
    background: #fef3c7;
    color: #b45309;
    font-size: 0.58rem;
    font-weight: 700;
    flex-shrink: 0;
}

.pa-roster-scores {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-shrink: 0;
}

.pa-roster-score {
    padding: 0.12rem 0.35rem;
    border-radius: 4px;
    font-size: 0.6rem;
    font-weight: 700;
    font-family: var(--hcis-font-mono, monospace);
    line-height: 1;
}

.pa-roster-score--kpi {
    background: #e0f2fe;
    color: #0369a1;
}

.pa-roster-score--comp {
    background: #f3e8ff;
    color: #7e22ce;
}

.pa-box-card__empty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.85rem 0.5rem;
    background: rgba(248, 250, 252, 0.65);
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px dashed rgba(226, 232, 240, 0.85);
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #94a3b8);
    margin-top: auto;
}

.pa-scatter-footer-tip {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    padding: 0.55rem 0.75rem;
    margin-top: 0.65rem;
    background: rgba(248, 250, 252, 0.95);
    border: 1px dashed rgba(203, 213, 225, 0.85);
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #475569);
    line-height: 1.35;
}

@media (min-width: 769px) and (max-width: 1100px) {
    .pa-stat-group {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.5rem !important;
    }
}

@media (max-width: 1024px) {
    .pa-hav-axis-cols {
        grid-template-columns: 120px repeat(3, minmax(180px, 1fr));
        min-width: 680px;
    }
    .pa-hav-grid-with-y {
        grid-template-columns: 120px 1fr;
        min-width: 680px;
    }
    .pa-hav-matrix-grid {
        grid-template-columns: repeat(3, minmax(180px, 1fr));
    }
}

@media (max-width: 768px) {
    .pa-hav-map-page {
        padding: 0 0 5rem 0;
        gap: 0.75rem;
        min-width: 0;
        max-width: 100%;
        overflow-x: hidden;
        width: 100%;
        box-sizing: border-box;
    }

    .pa-cockpit-section {
        gap: 0.35rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }

    .pa-stat-group {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.5rem !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }

    .pa-hav-filter-wrap {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }

    .pa-hav-filter-wrap :deep(.hcis-filter-bar) {
        padding: 0.55rem 0.65rem !important;
        border-radius: 12px !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }

    .pa-hav-canvas {
        padding: 0.85rem 0.75rem;
        min-width: 0;
        max-width: 100%;
        width: 100%;
        box-sizing: border-box;
        overflow: hidden;
    }

    .pa-box-list-grid {
        grid-template-columns: minmax(0, 1fr) !important;
        gap: 0.65rem !important;
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
    }

    .pa-box-card {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }

    .pa-box-list-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 0.45rem;
        padding: 0.45rem 0.65rem;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }
    .pa-box-list-toolbar__actions {
        justify-content: flex-end;
    }
    .pa-hav-framework-wrap {
        padding-bottom: 0.75rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        box-sizing: border-box;
    }
    .pa-axis-corner-card {
        position: sticky;
        left: 0;
        z-index: 10;
        background: #f8fafc;
    }
    .pa-hav-axis-rows {
        position: sticky;
        left: 0;
        z-index: 9;
        background: #f8fafc;
    }
    .pa-hav-axis-cols {
        grid-template-columns: 95px repeat(3, minmax(160px, 1fr));
        min-width: 580px;
    }
    .pa-hav-grid-with-y {
        grid-template-columns: 95px 1fr;
        min-width: 580px;
    }
    .pa-hav-matrix-grid {
        grid-template-columns: repeat(3, minmax(160px, 1fr));
    }
    .pa-roster-item {
        padding: 0.45rem 0.55rem;
        min-height: 2.5rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
}

@media (max-width: 640px) {
    .pa-hav-map-page {
        padding: 0 0 5rem 0;
        gap: 0.75rem;
        min-width: 0;
        max-width: 100%;
        overflow-x: hidden;
        width: 100%;
        box-sizing: border-box;
    }

    .pa-cockpit-section {
        gap: 0.4rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }

    .pa-stat-group {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.4rem !important;
        margin-bottom: 0.25rem !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }

    .pa-stat-group :deep(.hcis-stat),
    .pa-stat-group .hcis-stat {
        min-height: unset !important;
        height: auto !important;
        padding: 0.45rem 0.55rem !important;
        border-radius: var(--hcis-radius-sm, 8px) !important;
        min-width: 0 !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }

    .pa-stat-group :deep(.hcis-stat__compact-wrap) {
        gap: 0.15rem !important;
        min-width: 0 !important;
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

    .pa-hav-canvas {
        padding: 0.75rem 0.65rem;
        min-width: 0;
        max-width: 100%;
        width: 100%;
        box-sizing: border-box;
        overflow: hidden;
    }
    .pa-hav-canvas__header {
        flex-direction: column;
        align-items: stretch;
        gap: 0.65rem;
        width: 100%;
        max-width: 100%;
    }
    .pa-hav-canvas__controls {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
        width: 100%;
        max-width: 100%;
    }
    .pa-view-segmented {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        width: 100%;
        padding: 0.2rem;
        gap: 0.2rem;
        border-radius: 8px;
        box-sizing: border-box;
    }
    .pa-view-segmented__btn {
        justify-content: center;
        padding: 0.45rem 0.2rem;
        font-size: 0.72rem;
        gap: 0.25rem;
        border-radius: 6px;
        min-width: 0;
    }
    .pa-view-segmented__label {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .pa-view-segmented__badge {
        font-size: 0.58rem;
        padding: 0.04rem 0.25rem;
    }
    .pa-hav-header-actions {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr auto auto;
        gap: 0.35rem;
        min-width: 0;
        max-width: 100%;
        box-sizing: border-box;
    }
    .pa-period-trigger {
        max-width: 100%;
        width: 100%;
        padding: 0.3rem 0.5rem;
        min-width: 0;
        box-sizing: border-box;
    }
    .pa-period-trigger__body {
        min-width: 0;
        overflow: hidden;
    }
    .pa-period-trigger__value {
        font-size: 0.72rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .pa-period-dialog__grid {
        grid-template-columns: 1fr;
    }
    .pa-period-dialog__controls {
        gap: 0.65rem;
    }
    .pa-filter-tab {
        padding: 0.3rem 0.65rem;
        font-size: 0.72rem;
    }

    .pa-box-list-grid {
        grid-template-columns: minmax(0, 1fr) !important;
        gap: 0.65rem !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
    }

    .pa-box-card {
        padding: 0.65rem 0.65rem;
        gap: 0.5rem;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box;
    }

    .pa-box-card__header-left {
        gap: 0.35rem;
    }

    .pa-box-card__header-right {
        gap: 0.3rem;
    }

    .pa-roster-item {
        padding: 0.35rem 0.45rem;
        gap: 0.35rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }

    .pa-roster-avatar {
        width: 1.25rem;
        height: 1.25rem;
        font-size: 0.52rem;
    }

    .pa-roster-scores {
        gap: 0.2rem;
    }

    .pa-roster-score {
        padding: 0.1rem 0.25rem;
        font-size: 0.56rem;
    }
}
</style>
