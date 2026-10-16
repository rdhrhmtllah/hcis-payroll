<template>
    <div class="pa-ikatan-page">
        <!-- Unified HCIS Header with Embedded Console Navigation -->
        <HcisHeader
            title="Matriks Soal"
            kicker="MASTER DATA · MATRIKS SOAL"
            subtitle="Konfigurasi matriks paket bank soal default per level jabatan dan kategori penilai untuk siklus appraisal baru."
            icon="diagram-3-fill"
            sentiment="brand"
            variant="default"
            size="md"
            :animated="false"
            :loading="memuat"
            class="mb-3"
        >
            <template #badge>
                <HcisBadge
                    v-if="totalBermasalah > 0"
                    :label="isMobile ? `${totalBermasalah} masalah` : `${totalBermasalah} perlu perbaikan`"
                    variant="danger"
                    size="sm"
                    dot
                />
                <HcisBadge
                    v-else-if="level.length > 0"
                    :label="isMobile ? `${level.length} level` : `${level.length} level jabatan`"
                    variant="brand"
                    size="sm"
                    dot
                />
            </template>
            <template #actions>
                <div class="pa-header-actions">
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        leading-icon="shield-check"
                        :loading="memverifikasi"
                        title="Validasi kesesuaian seluruh paket default terhadap katalog HC Learn live"
                        @click="verifikasi"
                    >
                        <span class="d-none d-sm-inline">Validasi Katalog</span>
                        <span class="d-inline d-sm-none">Validasi</span>
                    </HcisButton>
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :loading="memuat"
                        title="Segarkan data matriks"
                        @click="muat"
                    >
                        <span class="d-none d-sm-inline">Segarkan</span>
                    </HcisButton>
                </div>
            </template>
            <template #console>
                <nav class="pa-master-switcher" aria-label="Navigasi Master Data Performance Appraisal">
                    <a href="/performance-appraisal/master/jenis-kompetensi" class="pa-switcher-item">
                        <HcisIcon name="diagram-2-fill" size="2xs" />
                        <span>Jenis Kompetensi</span>
                    </a>
                    <a href="/performance-appraisal/master/kompetensi" class="pa-switcher-item">
                        <HcisIcon name="bullseye" size="2xs" />
                        <span>Daftar Kompetensi</span>
                    </a>
                    <a href="/performance-appraisal/master/syarat-kompetensi" class="pa-switcher-item">
                        <HcisIcon name="grid-3x3-gap-fill" size="2xs" />
                        <span>Matriks Kompetensi</span>
                    </a>
                    <span class="pa-switcher-item is-active">
                        <HcisIcon name="diagram-3-fill" size="2xs" />
                        <span>Matriks Soal</span>
                    </span>
                    <a href="/performance-appraisal/master/kategori-penilai" class="pa-switcher-item">
                        <HcisIcon name="person-badge-fill" size="2xs" />
                        <span>Jenis Penilai</span>
                    </a>
                    <a href="/performance-appraisal/master/aturan-penilai" class="pa-switcher-item">
                        <HcisIcon name="people-fill" size="2xs" />
                        <span>Komposisi Penilai</span>
                    </a>
                    <a href="/performance-appraisal/master/bobot-pilar" class="pa-switcher-item">
                        <HcisIcon name="pie-chart-fill" size="2xs" />
                        <span>Bobot KPI &amp; Kompetensi</span>
                    </a>
                    <a href="/performance-appraisal/master/pita-nilai" class="pa-switcher-item">
                        <HcisIcon name="bar-chart-steps" size="2xs" />
                        <span>Skala Nilai &amp; Grade</span>
                    </a>
                </nav>
            </template>
        </HcisHeader>

        <!-- Error State -->
        <HcisResultState
            v-if="error && !memuat"
            status="danger"
            layout="compact"
            title="Matriks pemetaan paket soal tidak dapat dimuat"
            :description="error"
            primary-text="Coba Lagi"
            primary-icon="arrow-clockwise"
            @primary-click="muat"
        />

        <!-- Loading Skeleton -->
        <div v-else-if="memuat" class="pa-ikatan-skeleton">
            <div class="pa-skel-cockpit">
                <HcisSkeleton v-for="n in 4" :key="`skel-st-${n}`" height="4.5rem" rounded="md" />
            </div>
            <div class="pa-skel-toolbar">
                <HcisSkeleton width="60%" height="2.2rem" rounded="md" />
                <HcisSkeleton width="30%" height="2.2rem" rounded="md" />
            </div>
            <!-- Grid Card Skeleton on mobile, Table skeleton on desktop -->
            <div v-if="viewMode === 'grid' || isMobile" class="pa-matrix-grid">
                <HcisCard v-for="n in 3" :key="`skel-card-${n}`" variant="default" class="pa-level-soal-card">
                    <template #header>
                        <div class="pa-soal-card__header">
                            <div class="pa-soal-card__title-group">
                                <HcisSkeleton width="2rem" height="1rem" rounded="sm" />
                                <HcisSkeleton width="8rem" height="1.1rem" rounded="sm" />
                            </div>
                            <HcisSkeleton width="4.5rem" height="1.2rem" rounded="full" />
                        </div>
                    </template>
                    <div class="pa-soal-card__body">
                        <HcisSkeleton width="7rem" height="0.9rem" rounded="sm" class="mb-2" />
                        <div class="pa-soal-card__raters-grid">
                            <HcisSkeleton v-for="c in 4" :key="`skel-c-${c}`" width="100%" height="3.2rem" rounded="sm" />
                        </div>
                    </div>
                </HcisCard>
            </div>
            <div v-else class="pa-skel-matrix">
                <div class="pa-skel-matrix-head">
                    <HcisSkeleton width="220px" height="2.5rem" rounded="sm" />
                    <HcisSkeleton v-for="n in 4" :key="`skel-th-${n}`" width="100%" height="2.5rem" rounded="sm" />
                </div>
                <div v-for="r in 4" :key="`skel-row-${r}`" class="pa-skel-matrix-row">
                    <HcisSkeleton width="220px" height="4.2rem" rounded="sm" />
                    <HcisSkeleton v-for="c in 4" :key="`skel-cell-${r}-${c}`" width="100%" height="4.2rem" rounded="sm" />
                </div>
            </div>
        </div>

        <!-- Loaded Content -->
        <template v-else>
            <!-- 4-Stat Cockpit Summary (Using HcisStatGroup and HcisStatCard) -->
            <HcisStatGroup :cols="4" class="pa-cockpit-group">
                <HcisStatCard
                    label="Level Jabatan"
                    :value="`${level.length} Level`"
                    :caption="isMobile ? 'Jenjang aktif' : 'Jenjang aktif terdaftar'"
                    icon="layers-fill"
                    sentiment="brand"
                    :layout="isMobile ? 'dense' : 'compact'"
                />
                <HcisStatCard
                    label="Kategori Penilai"
                    :value="`${kategori.length} Rater`"
                    :caption="isMobile ? 'Atasan, Peer, Sub, Self' : 'Atasan, Peer, Subordinate, Self'"
                    icon="people-fill"
                    sentiment="purple"
                    :layout="isMobile ? 'dense' : 'compact'"
                />
                <HcisStatCard
                    label="Kelengkapan"
                    :value="`${totalTerisi}/${totalSel} (${persenTerisi}%)`"
                    :caption="totalKosong === 0 ? 'Semua terpetakan' : `${totalKosong} belum terpetakan`"
                    :icon="totalKosong === 0 ? 'check-circle-fill' : 'clock-fill'"
                    :sentiment="totalKosong === 0 ? 'success' : 'brand'"
                    :layout="isMobile ? 'dense' : 'compact'"
                />
                <HcisStatCard
                    label="Validasi"
                    :value="totalBermasalah === 0 ? '100% Valid' : `${totalBermasalah} Perlu Dicek`"
                    :caption="totalBermasalah === 0 ? 'Semua paket cocok' : 'Paket berubah di HC Learn'"
                    :icon="totalBermasalah === 0 ? 'shield-check' : 'exclamation-octagon-fill'"
                    :sentiment="totalBermasalah === 0 ? 'success' : 'danger'"
                    :layout="isMobile ? 'dense' : 'compact'"
                />
            </HcisStatGroup>

            <!-- Sel salinan Master Level PA: sah dipakai, tetapi mapping-nya milik level dasar. -->
            <HcisAlert
                v-if="totalSalinan > 0"
                variant="warning"
                appearance="subtle"
                size="sm"
                :title="`${totalSalinan} sel hasil salinan level PA perlu ditinjau mapping-nya`"
                description="Mapping kode soal ke kompetensinya masih salinan dari level dasar (mis. OFFICER). Buka tiap sel bertanda Salinan, sesuaikan label kompetensinya dengan kompetensi level ini, lalu simpan — tandanya hilang sesudah disimpan."
                icon="exclamation-triangle-fill"
                class="mb-3"
                data-uji="ikatan-salinan"
            />

            <!-- Alerts & Warnings -->
            <div v-if="totalBermasalah > 0" class="pa-alerts-stack">
                <HcisAlert
                    variant="danger"
                    appearance="subtle"
                    :title="isMobile ? `${totalBermasalah} Sel Perlu Penyesuaian` : `Terdapat ${totalBermasalah} Sel Memerlukan Penyesuaian (Invalid / Berubah di HC Learn)`"
                    icon="exclamation-octagon-fill"
                >
                    Paket yang bermasalah tidak akan disalin saat pembuatan cycle baru. Periksa daftar perbaikan di bawah.
                </HcisAlert>
            </div>

            <!-- Standard Design System Filter Bar Component -->
            <HcisFilterBar
                :search="kataKunciLevel"
                :search-placeholder="isMobile ? 'Cari level...' : 'Cari level jabatan / ID level...'"
                :quick-chips="statusQuickChips"
                :active-chip-value="filterTab"
                :results-count="levelTersaring.length"
                :show-view-toggle="true"
                :view-mode="viewMode"
                variant="card"
                sentiment="brand"
                glow
                class="pa-filter-bar-wrap mb-3"
                @update:search="kataKunciLevel = $event"
                @chip-click="ubahChipFilter"
                @update:view-mode="viewMode = $event"
                @reset="resetFilter"
            />

            <!-- Contextual Empty State when filter yields 0 results -->
            <div v-if="levelTersaring.length === 0" class="pa-empty-wrapper">
                <HcisEmptyState
                    v-if="!level.length"
                    variant="glass"
                    sentiment="brand"
                    size="md"
                    animated
                    title="Belum Ada Level Jabatan"
                    description="Master level jabatan belum memiliki data untuk dipetakan paket soal defaultnya."
                    icon="layers-fill"
                    class="my-4"
                />
                <HcisEmptyState
                    v-else
                    variant="card"
                    sentiment="neutral"
                    size="md"
                    animated
                    title="Tidak Ada Level yang Cocok"
                    :description="kataKunciLevel ? `Tidak ditemukan level jabatan yang sesuai dengan pencarian '${kataKunciLevel}'.` : 'Tidak ada level jabatan yang cocok dengan penyaring status yang dipilih.'"
                    icon="search"
                    class="my-4"
                >
                    <template #actions>
                        <HcisButton
                            label="Reset Filter"
                            variant="secondary"
                            size="sm"
                            leading-icon="arrow-counterclockwise"
                            @click="resetFilter"
                        />
                    </template>
                </HcisEmptyState>
            </div>

            <!-- Mode 1: Grid Card View (Mobile default & responsive touch-friendly cards) -->
            <div v-if="viewMode === 'grid' && levelTersaring.length > 0" class="pa-matrix-grid">
                <HcisCard
                    v-for="l in levelTersaring"
                    :key="`card-lvl-${l.id_level}`"
                    variant="default"
                    :sentiment="hasLevelIssue(l) ? 'danger' : (isLevelIncomplete(l) ? 'neutral' : 'brand')"
                    class="pa-level-soal-card"
                    :class="{
                        'is-level-issue': hasLevelIssue(l),
                        'is-level-incomplete': isLevelIncomplete(l),
                    }"
                >
                    <template #header>
                        <div class="pa-level-soal-card__header">
                            <div class="pa-level-soal-card__title-group">
                                <span class="pa-level-soal-card__id">ID: {{ l.id_level }}</span>
                                <h3 class="pa-level-soal-card__title" :title="l.nama_level">{{ l.nama_level }}</h3>
                            </div>
                            <div class="pa-level-soal-card__badge">
                                <HcisBadge
                                    v-if="hasLevelIssue(l)"
                                    label="Perlu Cek"
                                    variant="danger"
                                    size="2xs"
                                    dot
                                />
                                <HcisBadge
                                    v-else-if="isLevelIncomplete(l)"
                                    label="Belum Lengkap"
                                    variant="warning"
                                    size="2xs"
                                />
                                <HcisBadge
                                    v-else
                                    label="Lengkap"
                                    variant="success"
                                    size="2xs"
                                />
                            </div>
                        </div>
                    </template>

                    <div class="pa-level-soal-card__body">
                        <!-- Meta bar: Comp count & filled evaluator count -->
                        <div class="pa-level-soal-card__meta-bar">
                            <div class="pa-level-soal-card__meta-item">
                                <HcisIcon name="check-all" size="2xs" color="brand" />
                                <span>{{ l.jumlah_kompetensi_wajib || 0 }} Komp. Wajib</span>
                            </div>
                            <div class="pa-level-soal-card__meta-item">
                                <HcisIcon name="people-fill" size="2xs" />
                                <span>{{ countFilledRaters(l) }}/{{ countEvaluatingRaters(l) }} Penilai Terisi</span>
                            </div>
                        </div>

                        <!-- Raters Grid inside Card -->
                        <div class="pa-level-soal-card__raters-grid">
                            <div
                                v-for="k in kategori"
                                :key="`card-rater-${l.id_level}-${k.kode}`"
                                class="pa-rater-cell-item"
                            >
                                <div class="pa-rater-cell-header">
                                    <HcisIcon :name="getRaterIcon(k.kode)" size="2xs" />
                                    <span>{{ formatKategori(k.nama, k.kode) }}</span>
                                </div>

                                <!-- State 1: Inactive Rater -->
                                <div
                                    v-if="!l.sel[k.kode]?.ikut_menilai && !l.sel[k.kode]?.terisi"
                                    class="pa-soal-cell pa-soal-cell--na"
                                    title="Kategori ini tidak menilai level ini"
                                >
                                    <span class="pa-soal-cell__na-text">Tidak Menilai</span>
                                </div>

                                <!-- State 2: Wrong Assignment -->
                                <button
                                    v-else-if="l.sel[k.kode]?.terisi && !l.sel[k.kode]?.ikut_menilai"
                                    type="button"
                                    class="pa-soal-cell pa-soal-cell--btn is-salah-tempat"
                                    :title="ringkasMasalah(l.sel[k.kode])"
                                    @click="bukaPilih(l, k)"
                                >
                                    <div class="pa-cell-top">
                                        <span class="pa-cell-code is-strikethrough">{{ l.sel[k.kode].id_master_ujian }}</span>
                                        <HcisBadge label="Rater Nonaktif" variant="danger" size="2xs" />
                                    </div>
                                    <div class="pa-cell-issue-text">
                                        <HcisIcon name="exclamation-triangle-fill" size="2xs" color="danger" />
                                        <span>Rater Tidak Menilai</span>
                                    </div>
                                </button>

                                <!-- State 3: Filled & Valid -->
                                <button
                                    v-else-if="l.sel[k.kode]?.terisi && l.sel[k.kode]?.sahih"
                                    type="button"
                                    class="pa-soal-cell pa-soal-cell--btn"
                                    :class="{
                                        'is-khusus': l.sel[k.kode]?.khusus,
                                        'is-bawaan': !l.sel[k.kode]?.khusus,
                                    }"
                                    :title="ringkasMasalah(l.sel[k.kode])"
                                    @click="bukaPilih(l, k)"
                                >
                                    <div class="pa-cell-top">
                                        <span class="pa-cell-code">{{ l.sel[k.kode].id_master_ujian }}</span>
                                        <HcisBadge
                                            :label="l.sel[k.kode].khusus ? 'Khusus' : 'Default'"
                                            :variant="l.sel[k.kode].khusus ? 'brand' : 'neutral'"
                                            size="2xs"
                                        />
                                        <HcisBadge
                                            v-if="l.sel[k.kode].salinan_dari"
                                            :label="'Salinan'"
                                            variant="warning"
                                            size="2xs"
                                            :title="`Mapping kompetensi disalin dari ${l.sel[k.kode].salinan_dari} saat level PA dibuat — tinjau lalu simpan sel ini.`"
                                        />
                                    </div>
                                    <div class="pa-cell-meta">
                                        <HcisIcon name="card-checklist" size="2xs" />
                                        <span>{{ l.sel[k.kode].jumlah_kompetensi }} Komp.</span>
                                        <span v-if="l.sel[k.kode].jumlah_bagian" class="pa-cell-dot">•</span>
                                        <span v-if="l.sel[k.kode].jumlah_bagian">{{ l.sel[k.kode].jumlah_bagian }} Bagian</span>
                                    </div>
                                </button>

                                <!-- State 4: Filled but Corrupted/Invalid -->
                                <button
                                    v-else-if="l.sel[k.kode]?.terisi && !l.sel[k.kode]?.sahih"
                                    type="button"
                                    class="pa-soal-cell pa-soal-cell--btn is-rusak"
                                    :title="ringkasMasalah(l.sel[k.kode])"
                                    @click="bukaPilih(l, k)"
                                >
                                    <div class="pa-cell-top">
                                        <span class="pa-cell-code is-danger">{{ l.sel[k.kode].id_master_ujian }}</span>
                                        <HcisBadge label="Perlu Cek" variant="danger" size="2xs" dot />
                                    </div>
                                    <div class="pa-cell-issue-text">
                                        <HcisIcon name="exclamation-circle-fill" size="2xs" color="danger" />
                                        <span>{{ l.sel[k.kode].masalah?.[0]?.jenis || 'Tidak Valid' }}</span>
                                    </div>
                                </button>

                                <!-- State 5: Empty (Unassigned) -->
                                <button
                                    v-else
                                    type="button"
                                    class="pa-soal-cell pa-soal-cell--btn is-kosong"
                                    title="Pilih paket default untuk rater ini"
                                    @click="bukaPilih(l, k)"
                                >
                                    <div class="pa-cell-empty-state">
                                        <HcisIcon name="plus-circle" size="xs" />
                                        <span>Pilih Paket</span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </HcisCard>
            </div>

            <!-- Mode 2: Table Matrix View (Desktop & Tablet Wide) -->
            <div v-else-if="levelTersaring.length > 0" class="pa-matrix-card-wrap">
                <div class="pa-matrix-table-wrap">
                    <table class="pa-matrix-table">
                        <thead>
                            <tr>
                                <th scope="col" class="pa-th-level">
                                    <div class="pa-th-content">
                                        <HcisIcon name="layers-fill" size="2xs" />
                                        <span>Level Jabatan &amp; Syarat</span>
                                    </div>
                                </th>
                                <th
                                    v-for="k in kategori"
                                    :key="k.kode"
                                    scope="col"
                                    class="pa-th-rater"
                                >
                                    <div class="pa-th-content">
                                        <HcisIcon :name="getRaterIcon(k.kode)" size="2xs" />
                                        <span>{{ formatKategori(k.nama, k.kode) }}</span>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="l in levelTersaring"
                                :key="l.id_level"
                                class="pa-matrix-row"
                            >
                                <!-- Level Column -->
                                <th scope="row" class="pa-td-level">
                                    <div class="pa-level-meta">
                                        <div class="pa-level-title-row">
                                            <strong>{{ l.nama_level }}</strong>
                                            <span class="pa-level-id-pill">ID: {{ l.id_level }}</span>
                                        </div>
                                        <div class="pa-level-reqs-row">
                                            <HcisIcon name="check-all" size="2xs" color="brand" />
                                            <span>{{ l.jumlah_kompetensi_wajib || 0 }} Kompetensi Wajib</span>
                                        </div>
                                    </div>
                                </th>

                                <!-- Rater Matrix Cells -->
                                <td
                                    v-for="k in kategori"
                                    :key="k.kode"
                                    class="pa-td-cell"
                                >
                                    <!-- State 1: Inactive Rater (Does not evaluate this level) -->
                                    <div
                                        v-if="!l.sel[k.kode]?.ikut_menilai && !l.sel[k.kode]?.terisi"
                                        class="pa-cell-na"
                                        title="Kategori ini tidak menilai level tersebut menurut master aturan penilai"
                                    >
                                        <span class="pa-cell-na__dash">—</span>
                                    </div>

                                    <!-- State 2: Wrong Assignment (Assigned but rater does not evaluate this level) -->
                                    <button
                                        v-else-if="l.sel[k.kode]?.terisi && !l.sel[k.kode]?.ikut_menilai"
                                        type="button"
                                        class="pa-matrix-cell-btn is-salah-tempat"
                                        :title="ringkasMasalah(l.sel[k.kode])"
                                        @click="bukaPilih(l, k)"
                                    >
                                        <div class="pa-cell-top">
                                            <span class="pa-cell-code is-strikethrough">{{ l.sel[k.kode].id_master_ujian }}</span>
                                            <HcisBadge label="Rater Nonaktif" variant="danger" size="2xs" />
                                        </div>
                                        <div class="pa-cell-issue-text">
                                            <HcisIcon name="exclamation-triangle-fill" size="2xs" color="danger" />
                                            <span>Rater Tidak Menilai</span>
                                        </div>
                                    </button>

                                    <!-- State 3: Filled & Valid -->
                                    <button
                                        v-else-if="l.sel[k.kode]?.terisi && l.sel[k.kode]?.sahih"
                                        type="button"
                                        class="pa-matrix-cell-btn"
                                        :class="{
                                            'is-khusus': l.sel[k.kode]?.khusus,
                                            'is-bawaan': !l.sel[k.kode]?.khusus,
                                        }"
                                        :title="ringkasMasalah(l.sel[k.kode])"
                                        @click="bukaPilih(l, k)"
                                    >
                                        <div class="pa-cell-top">
                                            <span class="pa-cell-code">{{ l.sel[k.kode].id_master_ujian }}</span>
                                            <HcisBadge
                                                :label="l.sel[k.kode].khusus ? 'Khusus' : 'Default'"
                                                :variant="l.sel[k.kode].khusus ? 'brand' : 'neutral'"
                                                size="2xs"
                                            />
                                            <HcisBadge
                                                v-if="l.sel[k.kode].salinan_dari"
                                                :label="'Salinan'"
                                                variant="warning"
                                                size="2xs"
                                                :title="`Mapping kompetensi disalin dari ${l.sel[k.kode].salinan_dari} saat level PA dibuat — tinjau lalu simpan sel ini.`"
                                            />
                                        </div>
                                        <div class="pa-cell-meta">
                                            <HcisIcon name="card-checklist" size="2xs" />
                                            <span>{{ l.sel[k.kode].jumlah_kompetensi }} Komp.</span>
                                            <span v-if="l.sel[k.kode].jumlah_bagian" class="pa-cell-dot">•</span>
                                            <span v-if="l.sel[k.kode].jumlah_bagian">{{ l.sel[k.kode].jumlah_bagian }} Bagian</span>
                                        </div>
                                    </button>

                                    <!-- State 4: Filled but Corrupted/Invalid -->
                                    <button
                                        v-else-if="l.sel[k.kode]?.terisi && !l.sel[k.kode]?.sahih"
                                        type="button"
                                        class="pa-matrix-cell-btn is-rusak"
                                        :title="ringkasMasalah(l.sel[k.kode])"
                                        @click="bukaPilih(l, k)"
                                    >
                                        <div class="pa-cell-top">
                                            <span class="pa-cell-code is-danger">{{ l.sel[k.kode].id_master_ujian }}</span>
                                            <HcisBadge label="Perlu Perbaikan" variant="danger" size="2xs" dot />
                                        </div>
                                        <div class="pa-cell-issue-text">
                                            <HcisIcon name="exclamation-circle-fill" size="2xs" color="danger" />
                                            <span>{{ l.sel[k.kode].masalah?.[0]?.jenis || 'Tidak Valid' }}</span>
                                        </div>
                                    </button>

                                    <!-- State 5: Empty (Unassigned) -->
                                    <button
                                        v-else
                                        type="button"
                                        class="pa-matrix-cell-btn is-kosong"
                                        title="Belum ada pilihan paket default untuk sel ini. Klik untuk memilih paket."
                                        @click="bukaPilih(l, k)"
                                    >
                                        <div class="pa-cell-empty-state">
                                            <HcisIcon name="plus-circle" size="xs" />
                                            <span>Pilih Paket</span>
                                        </div>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Detailed Actionable Issues Panel -->
            <div v-if="daftarMasalah.length" class="pa-issues-card">
                <div class="pa-issues-header">
                    <div class="pa-issues-title">
                        <HcisIcon name="wrench-adjustable" size="sm" color="danger" />
                        <div>
                            <h4>Rincian Sel yang Memerlukan Penyesuaian ({{ daftarMasalah.length }})</h4>
                            <span class="text-xs text-slate-500">Paket default berikut tidak memenuhi syarat kompetensi level atau butir soalnya telah berubah di HC Learn.</span>
                        </div>
                    </div>
                </div>

                <div class="pa-issues-list">
                    <div
                        v-for="(m, i) in daftarMasalah"
                        :key="i"
                        class="pa-issue-item"
                    >
                        <div class="pa-issue-identity">
                            <strong>{{ m.level }} × {{ formatKategori(m.kategori, m.kategori) }}</strong>
                            <code>{{ m.id_master_ujian }}</code>
                        </div>

                        <div class="pa-issue-reasons">
                            <div
                                v-for="(x, j) in m.masalah"
                                :key="j"
                                class="pa-issue-tag-row"
                            >
                                <HcisBadge :label="x.jenis" variant="danger" appearance="subtle" size="xs" />
                                <span>{{ x.pesan }}</span>
                            </div>
                        </div>

                        <div class="pa-issue-action">
                            <HcisButton
                                :label="isMobile ? 'Periksa' : 'Periksa Sekarang'"
                                variant="secondary"
                                size="xs"
                                leading-icon="arrow-clockwise"
                                :loading="memeriksaSelId === m.id"
                                :disabled="!m.id"
                                @click="periksaSel(m.id)"
                            />
                            <HcisButton
                                :label="isMobile ? 'Perbaiki' : 'Perbaiki Sel'"
                                variant="secondary"
                                size="xs"
                                leading-icon="pencil"
                                @click="bukaPilihDariMasalah(m)"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Dialog: Pilih & Tetapkan Paket Soal Default dari Katalog -->
        <HcisDialog
            v-model="dialog"
            :title="judulDialog"
            kicker="MASTER DATA · KATALOG BANK SOAL"
            icon="ui-checks"
            sentiment="brand"
            :size="isMobile ? 'full' : '2xl'"
        >
            <PaPaketSoalPicker
                :paket="katalog"
                :kompetensi-wajib="sasaran.level?.kompetensi_wajib ?? []"
                :nama-sasaran="sasaran.level ? `${sasaran.level.nama_level} × ${formatKategori(sasaran.kategori?.nama, sasaran.kategori?.kode)}` : ''"
                :kode-terpilih="sasaran.sel?.id_master_ujian || ''"
                :label-bagian="labelBagian"
                :memuat="memuatKatalog"
                :sibuk="menetapkanId"
                @pilih="tetapkan"
            />

            <template #footer>
                <div class="pa-dialog-footer-actions">
                    <HcisButton
                        v-if="sasaran.sel?.terisi"
                        variant="danger"
                        :label="isMobile ? 'Hapus' : 'Hapus dari Default'"
                        size="sm"
                        leading-icon="trash"
                        :loading="mencabut"
                        @click="cabut"
                    />
                    <HcisButton
                        variant="secondary"
                        label="Tutup"
                        size="sm"
                        :class="{ 'ms-auto': !isMobile }"
                        @click="dialog = false"
                    />
                </div>
            </template>
        </HcisDialog>

        <!--
            Dialog KEDUA: pemetaan bagian ke kompetensi.
        -->
        <HcisDialog
            v-model="dialogLabel"
            title="Pemetaan Bagian ke Kompetensi"
            kicker="MASTER DATA · MATRIKS SOAL"
            icon="diagram-3-fill"
            sentiment="brand"
            :size="isMobile ? 'full' : '3xl'"
        >
            <PaLabelBagianDialog
                v-if="ujianDilabeli"
                :id-level="sasaran.level?.id_level ?? 0"
                :nama-level="sasaran.level?.nama_level ?? ''"
                :rater-category-id="sasaran.kategori?.id ?? null"
                :nama-kategori="formatKategori(sasaran.kategori?.nama, sasaran.kategori?.kode)"
                :id-master-ujian="ujianDilabeli.id_master_ujian"
                :nama-ujian="ujianDilabeli.nama_ujian ?? ''"
                :nama-paket="ujianDilabeli.nama_paket ?? ''"
                :bagian="ujianDilabeli.bagian ?? []"
                :kompetensi-wajib="ujianDilabeli.kompetensi_wajib ?? []"
                :label-awal="labelAwal"
                :menyimpan="menyimpanLabel"
                @simpan="simpanLabel"
                @batal="dialogLabel = false"
            />
        </HcisDialog>

        <!-- Floating Notification Toast -->
        <transition name="toast">
            <div
                v-if="notifikasi.message"
                class="pa-ikatan-toast"
                :class="`pa-ikatan-toast--${notifikasi.type}`"
                role="status"
                aria-live="polite"
            >
                <HcisIcon
                    :name="notifikasi.type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'"
                    size="xs"
                />
                <div class="pa-ikatan-toast__text">
                    <strong>{{ notifikasi.type === 'success' ? 'Sukses' : 'Pemberitahuan' }}</strong>
                    <span>{{ notifikasi.message }}</span>
                </div>
                <button
                    type="button"
                    class="pa-ikatan-toast__close"
                    aria-label="Tutup pemberitahuan"
                    @click="notifikasi.message = ''"
                >
                    <HcisIcon name="x-lg" size="2xs" />
                </button>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, shallowRef } from 'vue';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCard,
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
import PaPaketSoalPicker from '../components/PaPaketSoalPicker.vue';
import PaLabelBagianDialog from './components/PaLabelBagianDialog.vue';
import paApi from '../api/paApi.js';

defineOptions({ name: 'PerformanceAppraisalIkatanPaketSoalPage' });

const isMobile = ref(false);
const isTablet = ref(false);
const viewMode = ref('table');
const viewModeInitialized = ref(false);

function cekUkuranLayar() {
    if (typeof window === 'undefined') return;
    const w = window.innerWidth;
    isMobile.value = w < 768;
    isTablet.value = w >= 768 && w < 1024;

    if (!viewModeInitialized.value) {
        viewMode.value = w < 1024 ? 'grid' : 'table';
        viewModeInitialized.value = true;
    }
}

const kategori = shallowRef([]);
const level = shallowRef([]);
const totalBermasalah = ref(0);
const totalKosong = ref(0);

const memuat = ref(false);
const memverifikasi = ref(false);
const mencabut = ref(false);
const menetapkanId = ref(null);
const error = ref('');
const notifikasi = reactive({ type: 'success', message: '' });

const filterTab = ref('semua'); // 'semua' | 'bermasalah' | 'belum_lengkap'
const kataKunciLevel = ref('');

const dialog = ref(false);

/* Dialog KEDUA. Pemilihan ujian dan pelabelan bagian sengaja dipisah: yang pertama memilih
   dari katalog HC Learn, yang kedua memetakan isinya ke kompetensi kita. Menggabungkannya
   berarti admin melihat 21 baris pelabelan sebelum ia sempat tahu ujiannya cocok. */
const dialogLabel = ref(false);
const ujianDilabeli = shallowRef(null);
const labelAwal = shallowRef({});
const menyimpanLabel = ref(false);
const memeriksaSelId = ref(null);
const memuatKatalog = ref(false);
const katalog = shallowRef([]);
const sasaran = reactive({ level: null, kategori: null, sel: null });

const judulDialog = computed(() => {
    if (!sasaran.level) return 'Pilih Paket Soal';
    return `Atur Pemetaan Paket: ${sasaran.level.nama_level} × ${formatKategori(sasaran.kategori?.nama, sasaran.kategori?.kode)}`;
});

const totalSel = computed(() => {
    return (level.value.length || 0) * (kategori.value.length || 0);
});

const totalTerisi = computed(() => {
    let count = 0;
    for (const lv of level.value) {
        for (const kat of kategori.value) {
            if (lv.sel?.[kat.kode]?.terisi) count++;
        }
    }
    return count;
});

const persenTerisi = computed(() => {
    if (!totalSel.value) return 0;
    return Math.round((totalTerisi.value / totalSel.value) * 100);
});

const totalSalinan = computed(() => level.value.reduce(
    (n, l) => n + Object.values(l.sel || {}).filter((s) => s?.terisi && s?.salinan_dari).length,
    0,
));

const levelBermasalahCount = computed(() => {
    return level.value.filter((l) =>
        Object.values(l.sel || {}).some((s) => s?.terisi && !s?.sahih)
    ).length;
});

/*
    Hanya sel yang penilainya BENAR-BENAR menilai level itu dihitung belum lengkap.
*/
const levelBelumLengkapCount = computed(() => {
    return level.value.filter((l) =>
        Object.values(l.sel || {}).some((s) => s?.ikut_menilai && !s?.terisi)
    ).length;
});

const statusQuickChips = computed(() => [
    {
        label: isMobile.value ? 'Semua' : 'Semua Level',
        value: 'semua',
        badge: level.value.length,
        color: 'brand',
    },
    {
        label: isMobile.value ? 'Masalah' : 'Perlu Perbaikan',
        value: 'bermasalah',
        badge: levelBermasalahCount.value,
        color: 'danger',
        ping: levelBermasalahCount.value > 0,
    },
    {
        label: isMobile.value ? 'Kosong' : 'Belum Lengkap',
        value: 'belum_lengkap',
        badge: levelBelumLengkapCount.value,
        color: 'warning',
    },
]);

function ubahChipFilter(chip) {
    filterTab.value = chip?.value || 'semua';
}

function resetFilter() {
    filterTab.value = 'semua';
    kataKunciLevel.value = '';
}

function hasLevelIssue(l) {
    return Object.values(l?.sel || {}).some((s) => s?.terisi && !s?.sahih);
}

function isLevelIncomplete(l) {
    return Object.values(l?.sel || {}).some((s) => s?.ikut_menilai && !s?.terisi);
}

function countFilledRaters(l) {
    return Object.values(l?.sel || {}).filter((s) => s?.ikut_menilai && s?.terisi).length;
}

function countEvaluatingRaters(l) {
    return Object.values(l?.sel || {}).filter((s) => s?.ikut_menilai).length;
}

const levelTersaring = computed(() => {
    let list = level.value;

    if (filterTab.value === 'bermasalah') {
        list = list.filter((l) =>
            Object.values(l.sel || {}).some((s) => s?.terisi && !s?.sahih)
        );
    } else if (filterTab.value === 'belum_lengkap') {
        list = list.filter((l) =>
            Object.values(l.sel || {}).some((s) => s?.ikut_menilai && !s?.terisi)
        );
    }

    const q = kataKunciLevel.value.trim().toLowerCase();
    if (q) {
        list = list.filter((l) =>
            String(l.nama_level || '').toLowerCase().includes(q) ||
            String(l.id_level || '').includes(q)
        );
    }

    return list;
});

const daftarMasalah = computed(() =>
    level.value.flatMap((l) =>
        Object.entries(l.sel || {})
            .filter(([, s]) => s?.terisi && !s?.sahih)
            .map(([kode, s]) => ({
                level: l.nama_level,
                id_level: l.id_level,
                kategori: kode,
                id: s.id,
                id_master_ujian: s.id_master_ujian,
                masalah: s.masalah ?? [],
                diperiksa_pada: s.diperiksa_pada ?? null,
                raw_level: l,
                raw_kategori: kategori.value.find((k) => k.kode === kode) || { kode, nama: kode },
            })),
    ),
);

function formatKategori(nama, kode) {
    const n = String(nama || '');
    const k = String(kode || '').toUpperCase();
    if (k.includes('ATASAN') || n.toLowerCase().includes('atasan')) return 'Atasan';
    if (k.includes('PEER') || k.includes('SEJAWAT') || n.toLowerCase().includes('sejawat') || n.toLowerCase().includes('rekan')) return 'Peer (Rekan)';
    if (k.includes('BAWAHAN') || k.includes('SUBORDINATE') || n.toLowerCase().includes('bawahan')) return 'Bawahan (Subordinate)';
    if (k.includes('DIRI') || k.includes('SELF') || n.toLowerCase().includes('diri')) return 'Diri Sendiri (Self)';
    return n || kode;
}

function getRaterIcon(kode) {
    const k = String(kode || '').toUpperCase();
    if (k.includes('ATASAN')) return 'person-badge-fill';
    if (k.includes('SEJAWAT') || k.includes('PEER')) return 'people-fill';
    if (k.includes('BAWAHAN') || k.includes('SUBORDINATE')) return 'person-lines-fill';
    if (k.includes('DIRI') || k.includes('SELF')) return 'person-fill';
    return 'diagram-3-fill';
}

function ringkasMasalah(sel) {
    if (sel && !sel.ikut_menilai && !sel.terisi) {
        return 'Kategori ini tidak menilai level tersebut menurut master aturan penilai.';
    }
    if (sel && sel.terisi && !sel.ikut_menilai) {
        return 'Kategori ini tidak lagi menilai level tersebut menurut master aturan penilai. Klik untuk mencabut atau mengganti.';
    }
    if (!sel || !sel.terisi) return 'Belum ada pilihan paket default untuk sel ini. Klik untuk memilih paket.';
    return sel.sahih
        ? `${sel.nama_ujian ?? sel.id_master_ujian} (${sel.jumlah_kompetensi || 0} Kompetensi, ${sel.jumlah_bagian || 0} Bagian)`
        : (sel.masalah ?? []).map((m) => m.pesan).join(' ');
}

function tampilkanNotifikasi(type, message) {
    notifikasi.type = type;
    notifikasi.message = message;
}

async function muat() {
    memuat.value = true;
    error.value = '';

    try {
        const response = await paApi.matriksIkatanBaku();
        const data = response?.result ?? response?.data ?? response;

        kategori.value = data?.kategori ?? [];
        level.value = data?.level ?? [];
        totalBermasalah.value = Number(data?.total_bermasalah ?? 0);
        totalKosong.value = Number(data?.total_kosong ?? 0);
    } catch (e) {
        error.value = e?.message || 'Gagal memuat matriks pemetaan paket soal.';
    } finally {
        memuat.value = false;
    }
}

/* Mapping tersimpan sel yang dibuka — untuk pratinjau bagian di pemilih paket. */
const labelBagian = ref(null);

async function muatLabelBagian(idIkatan, idMasterUjian) {
    labelBagian.value = null;
    if (!idIkatan) return;
    try {
        const r = await paApi.ambilLabelIkatan(idIkatan);
        const baris = r?.result ?? r?.data ?? r ?? [];
        const peta = {};
        for (const d of Array.isArray(baris) ? baris : []) {
            peta[`${d.id_jenis_indikator ?? ''}|${d.id_jenis_soal ?? ''}`] = { kode: d.kode_kompetensi || '', nama: d.nama_kompetensi || '' };
        }
        labelBagian.value = { id_master_ujian: idMasterUjian, peta };
    } catch {
        /* Pratinjau saja. */
    }
}

async function bukaPilih(l, k) {
    sasaran.level = l;
    sasaran.kategori = k;
    sasaran.sel = l.sel?.[k.kode];
    dialog.value = true;
    muatLabelBagian(sasaran.sel?.terisi ? sasaran.sel.id : null, sasaran.sel?.id_master_ujian);

    if (katalog.value.length) return;

    memuatKatalog.value = true;
    try {
        const response = await paApi.katalogPaketSoalMaster();
        const data = response?.result ?? response?.data ?? response;
        katalog.value = data?.paket ?? [];
    } catch (e) {
        tampilkanNotifikasi('danger', e?.message || 'Gagal memuat katalog paket soal.');
        dialog.value = false;
    } finally {
        memuatKatalog.value = false;
    }
}

function bukaPilihDariMasalah(m) {
    bukaPilih(m.raw_level, m.raw_kategori);
}

/**
 * Langkah PERTAMA: admin memilih ujian, lalu bagiannya ditarik beserta kompetensi wajib
 * level itu — satu permintaan, keduanya sekaligus.
 *
 * Penolakan terjadi DI SINI, sebelum dialog pelabelan dibuka. Percuma menggambar 21 baris
 * pelabelan kalau ujiannya berisi 25 bagian, atau kalau ada dua bagian yang identitasnya
 * kembar sehingga pemetaannya ambigu sejak awal.
 */
async function tetapkan(p) {
    menetapkanId.value = p.Id_Master_Ujian;

    try {
        const response = await paApi.ambilBagianUjian(p.Id_Master_Ujian, sasaran.level.id_level);
        const d = response?.data ?? response;

        if (d.kembar?.length) {
            tampilkanNotifikasi(
                'danger',
                `Ujian ini memuat ${d.kembar.length} bagian yang identitasnya kembar (${d.kembar.slice(0, 3).join(', ')}). ` +
                    'HC Learn tidak memberi id tersendiri per bagian, jadi bagian yang memakai bank soal yang sama ' +
                    'di dalam satu alat tes tidak bisa dibedakan. Minta HC Learn memisahkan bank soalnya.',
            );

            return;
        }

        if (!d.sepadan) {
            tampilkanNotifikasi(
                'danger',
                `Ujian ini punya ${d.bagian?.length ?? 0} bagian, sedangkan ${sasaran.level.nama_level} menuntut ` +
                    `${d.jumlah_wajib} kompetensi. Karena satu bagian mewakili tepat satu kompetensi, keduanya harus sama.`,
            );

            return;
        }

        ujianDilabeli.value = d;
        labelAwal.value = await labelDariSelSerupa(p.Id_Master_Ujian, sasaran.level.id_level);
        dialog.value = false;
        dialogLabel.value = true;
    } catch (e) {
        tampilkanNotifikasi('danger', e?.message || 'Gagal membaca bagian ujian.');
    } finally {
        menetapkanId.value = null;
    }
}

/**
 * Label dari sel LAIN yang memakai ujian dan level yang sama, bila ada.
 *
 * Kompetensi wajib ditentukan per level saja, jadi pemetaannya pasti identik — dan mengetik
 * ulang 21 baris untuk hasil yang sudah pasti sama adalah pekerjaan yang tidak perlu ada.
 * Salinannya tetap TERPISAH: memperbaiki sel sumber tidak menular ke sini.
 */
async function labelDariSelSerupa(idMasterUjian, idLevel) {
    const milik = level.value.find((l) => l.id_level === idLevel);

    const sumber = Object.values(milik?.sel ?? {}).find(
        (s) => s?.terisi && s?.id_master_ujian === idMasterUjian && s?.id !== sasaran.sel?.id,
    );

    if (!sumber) {
        return {};
    }

    try {
        const response = await paApi.ambilLabelIkatan(sumber.id);
        const baris = response?.data ?? response ?? [];

        return Object.fromEntries(
            baris.map((b) => [`${b.id_jenis_indikator}|${b.id_jenis_soal}`, Number(b.kompetensi_id)]),
        );
    } catch {
        /* Gagal menyalin bukan kegagalan pengikatan — admin tetap bisa melabeli manual.
           Diam di sini disengaja; memunculkan galat untuk kemudahan yang gagal justru
           membuat admin mengira pengikatannya yang bermasalah. */
        return {};
    }
}

/** Langkah KEDUA: pemetaan lengkap disimpan bersama header ikatannya, dalam satu transaksi. */
async function simpanLabel(label) {
    menyimpanLabel.value = true;

    try {
        await paApi.simpanIkatanBaku(
            sasaran.level.id_level,
            sasaran.kategori.id,
            ujianDilabeli.value.id_master_ujian,
            label,
        );

        tampilkanNotifikasi(
            'success',
            `Ujian ${ujianDilabeli.value.nama_ujian ?? ujianDilabeli.value.id_master_ujian} terikat untuk ` +
                `${sasaran.level.nama_level} × ${formatKategori(sasaran.kategori.nama, sasaran.kategori.kode)}, ` +
                `dengan ${Object.keys(label).length} bagian terpetakan.`,
        );

        dialogLabel.value = false;
        ujianDilabeli.value = null;
        await muat();
    } catch (e) {
        tampilkanNotifikasi('danger', e?.message || 'Gagal menyimpan pemetaan.');
    } finally {
        menyimpanLabel.value = false;
    }
}

/** Pemeriksaan manual satu sel — status yang ditampilkan matriks memang bisa sudah lama. */
async function periksaSel(id) {
    memeriksaSelId.value = id;

    try {
        const response = await paApi.periksaSelIkatan(id);
        const d = response?.data ?? response;

        tampilkanNotifikasi(
            d.status === 'SEHAT' ? 'success' : 'danger',
            d.status === 'SEHAT'
                ? 'Sel ini masih sehat terhadap katalog HC Learn.'
                : (d.masalah ?? []).map((m) => m.pesan).join(' '),
        );

        await muat();
    } catch (e) {
        tampilkanNotifikasi('danger', e?.message || 'Gagal memeriksa sel ini.');
    } finally {
        memeriksaSelId.value = null;
    }
}

async function cabut() {
    if (!sasaran.sel?.id) return;
    mencabut.value = true;

    try {
        await paApi.cabutIkatanBaku(sasaran.sel.id);
        tampilkanNotifikasi('success', 'Paket default berhasil dicabut dari sel ini.');
        dialog.value = false;
        await muat();
    } catch (e) {
        tampilkanNotifikasi('danger', e?.message || 'Gagal mencabut pilihan default.');
    } finally {
        mencabut.value = false;
    }
}

async function verifikasi() {
    memverifikasi.value = true;

    try {
        const response = await paApi.verifikasiIkatanBaku();
        const d = response?.result ?? response?.data ?? response;

        if (d?.bermasalah?.length) {
            tampilkanNotifikasi('danger', `${d.diperiksa} sel diperiksa: ${d.sahih} valid, ${d.bermasalah.length} bermasalah dan perlu diperbarui.`);
        } else {
            tampilkanNotifikasi('success', `${d?.diperiksa ?? 0} sel berhasil diverifikasi, seluruh paket default 100% valid.`);
        }

        await muat();
    } catch (e) {
        tampilkanNotifikasi('danger', e?.message || 'Verifikasi katalog gagal.');
    } finally {
        memverifikasi.value = false;
    }
}

onMounted(() => {
    cekUkuranLayar();
    window.addEventListener('resize', cekUkuranLayar);
    muat();
});

onUnmounted(() => {
    window.removeEventListener('resize', cekUkuranLayar);
});
</script>

<style scoped>
.pa-ikatan-page {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    min-width: 0;
    max-width: 100%;
    width: 100%;
    box-sizing: border-box;
}

.pa-header-actions {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

/* Master Switcher in Header Console */
.pa-master-switcher {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: nowrap;
    overflow-x: auto;
    padding: 0.25rem;
    background: rgba(241, 245, 249, 0.75);
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: var(--hcis-radius-lg, 12px);
    margin-top: 0.5rem;
    scrollbar-width: none;
}
.pa-master-switcher::-webkit-scrollbar {
    display: none;
}

.pa-switcher-item {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.4rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.76rem;
    font-weight: 500;
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.pa-switcher-item:hover {
    color: #4338ca;
    background: rgba(255, 255, 255, 0.8);
}

.pa-switcher-item.is-active {
    color: #4338ca;
    background: #ffffff;
    font-weight: 700;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(99, 102, 241, 0.05);
}

.pa-ikatan-skeleton {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    width: 100%;
}

.pa-skel-cockpit {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.75rem;
    width: 100%;
}

.pa-skel-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: #ffffff;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
}

.pa-skel-matrix {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    padding: 1rem;
}

.pa-skel-matrix-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e2e8f0;
}

.pa-skel-matrix-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0;
}

.pa-matrix-empty-cell {
    padding: 2.5rem 1rem !important;
    text-align: center;
    background: #fafbfc;
}

.pa-cockpit-group {
    width: 100%;
}

.pa-alerts-stack {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    width: 100%;
}

/* Filter Bar & Toolbar */
.pa-filter-bar-wrap {
    width: 100%;
}

.pa-matrix-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.25rem 0.35rem;
    font-size: 0.78rem;
    color: var(--hcis-text-muted, #64748b);
    flex-wrap: wrap;
}

.pa-matrix-toolbar__count {
    font-size: 0.78rem;
}

.pa-view-switch-pills {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-md, 8px);
    gap: 0.2rem;
}

.pa-view-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    border: none;
    background: transparent;
    border-radius: 6px;
    font-size: 0.74rem;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-view-pill:hover {
    color: #0f172a;
}

.pa-view-pill.is-active {
    background: #ffffff;
    color: #4338ca;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

/* Mode 1: Grid Cards (Mobile & Responsive) */
.pa-matrix-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.85rem;
    width: 100%;
}

@media (min-width: 768px) {
    .pa-matrix-grid {
        grid-template-columns: repeat(auto-fill, minmax(22rem, 1fr));
        gap: 1rem;
    }
}

.pa-level-soal-card {
    border-radius: var(--hcis-radius-lg, 12px);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.pa-level-soal-card.is-level-issue {
    border-color: #fecaca;
}

.pa-level-soal-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    width: 100%;
}

.pa-level-soal-card__title-group {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
    flex: 1;
}

.pa-level-soal-card__id {
    font-size: 0.68rem;
    color: #64748b;
    font-family: ui-monospace, monospace;
    background: #f1f5f9;
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    flex-shrink: 0;
    font-weight: 600;
}

.pa-level-soal-card__title {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 700;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pa-level-soal-card__badge {
    flex-shrink: 0;
}

.pa-level-soal-card__body {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pa-level-soal-card__meta-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.72rem;
    color: #64748b;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-level-soal-card__meta-item {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-weight: 550;
}

.pa-level-soal-card__raters-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.5rem;
}

@media (min-width: 480px) {
    .pa-level-soal-card__raters-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.pa-rater-cell-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 8px;
    padding: 0.45rem;
}

.pa-rater-cell-header {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.68rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.pa-soal-cell {
    width: 100%;
    min-height: 2.75rem;
    box-sizing: border-box;
    border-radius: 6px;
}

.pa-soal-cell--na {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
    border: 1px solid #e2e8f0;
}

.pa-soal-cell__na-text {
    font-size: 0.72rem;
    color: #94a3b8;
    font-style: italic;
}

.pa-soal-cell--btn {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.15rem;
    padding: 0.35rem 0.5rem;
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #cbd5e1);
    cursor: pointer;
    text-align: left;
    transition: border-color 100ms ease, background-color 100ms ease, box-shadow 100ms ease;
}

.pa-soal-cell--btn:hover {
    border-color: #4f46e5;
    background: #f8faff;
}

.pa-soal-cell--btn.is-bawaan {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.pa-soal-cell--btn.is-khusus {
    background: #eef2ff;
    border-color: #a5b4fc;
}

.pa-soal-cell--btn.is-kosong {
    background: #fffbeb;
    border: 1.5px dashed #f59e0b;
}

.pa-soal-cell--btn.is-kosong:hover {
    background: #fef3c7;
    border-color: #d97706;
}

.pa-soal-cell--btn.is-rusak {
    background: #fef2f2;
    border: 1.5px solid #ef4444;
}

.pa-soal-cell--btn.is-rusak:hover {
    background: #fee2e2;
}

.pa-soal-cell--btn.is-salah-tempat {
    background: #fef2f2;
    border: 1.5px dashed #dc2626;
}

/* Matrix Table Container */
.pa-matrix-card-wrap {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #cbd5e1);
    border-radius: var(--hcis-radius-md, 12px);
    overflow: hidden;
    box-sizing: border-box;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.pa-matrix-table-wrap {
    overflow-x: auto;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
    -webkit-overflow-scrolling: touch;
}

.pa-matrix-table-wrap::-webkit-scrollbar {
    height: 5px;
}

.pa-matrix-table-wrap::-webkit-scrollbar-track {
    background: #f1f5f9;
}

.pa-matrix-table-wrap::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.pa-matrix-table {
    width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
    font-size: 0.8rem;
}

.pa-matrix-table thead th {
    padding: 0.65rem 0.75rem;
    background: #f8fafc;
    border-bottom: 2px solid var(--hcis-border-subtle, #e2e8f0);
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-th-content {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-th-level {
    width: 26%;
    min-width: 14rem;
}

.pa-th-rater {
    width: 18.5%;
    min-width: 11.5rem;
}

.pa-matrix-row:hover .pa-td-level {
    background: #f8faff;
}

.pa-td-level {
    padding: 0.6rem 0.75rem;
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
    background: #ffffff;
    vertical-align: middle;
    overflow: hidden;
}

.pa-level-meta {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pa-level-title-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-level-title-row strong {
    font-size: 0.85rem;
    color: var(--hcis-text-strong, #0f172a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-level-id-pill {
    font-size: 0.64rem;
    font-family: ui-monospace, monospace;
    font-weight: 700;
    color: #4f46e5;
    background: #eef2ff;
    padding: 0.06rem 0.3rem;
    border-radius: 4px;
}

.pa-level-reqs-row {
    display: flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
    font-weight: 550;
    white-space: nowrap;
}

.pa-td-cell {
    padding: 0.35rem 0.45rem;
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
    vertical-align: middle;
}

.pa-cell-na {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 3.2rem;
    color: var(--hcis-text-muted, #94a3b8);
    font-size: 1.1rem;
    font-weight: 400;
    user-select: none;
}

.pa-matrix-cell-btn {
    width: 100%;
    min-height: 3.2rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.15rem;
    padding: 0.35rem 0.5rem;
    border-radius: 6px;
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #cbd5e1);
    cursor: pointer;
    text-align: left;
    transition: border-color 100ms ease, background-color 100ms ease, box-shadow 100ms ease;
    box-sizing: border-box;
}

.pa-matrix-cell-btn:hover {
    border-color: #4f46e5;
    background: #f8faff;
}

.pa-matrix-cell-btn.is-bawaan {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.pa-matrix-cell-btn.is-khusus {
    background: #eef2ff;
    border-color: #a5b4fc;
}

.pa-matrix-cell-btn.is-kosong {
    background: #fffbeb;
    border: 1.5px dashed #f59e0b;
}

.pa-matrix-cell-btn.is-kosong:hover {
    background: #fef3c7;
    border-color: #d97706;
}

.pa-matrix-cell-btn.is-rusak {
    background: #fef2f2;
    border: 1.5px solid #ef4444;
}

.pa-matrix-cell-btn.is-rusak:hover {
    background: #fee2e2;
}

.pa-matrix-cell-btn.is-salah-tempat {
    background: #fef2f2;
    border: 1.5px dashed #dc2626;
}

.pa-cell-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.25rem;
}

.pa-cell-code {
    font-size: 0.72rem;
    font-family: ui-monospace, monospace;
    font-weight: 750;
    color: #1e1b4b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pa-cell-code.is-danger {
    color: #dc2626;
}

.pa-cell-code.is-strikethrough {
    text-decoration: line-through;
    color: #dc2626;
}

.pa-cell-meta {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.66rem;
    color: #64748b;
}

.pa-cell-dot {
    color: #cbd5e1;
}

.pa-cell-issue-text {
    display: flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.64rem;
    color: #dc2626;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-cell-empty-state {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    color: #b45309;
    font-size: 0.72rem;
    font-weight: 700;
}

/* Issues Actionable Panel */
.pa-issues-card {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #fef2f2;
    border: 1px solid #fecaca;
    width: 100%;
    box-sizing: border-box;
}

.pa-issues-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pa-issues-title {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}

.pa-issues-title h4 {
    margin: 0;
    font-size: 0.88rem;
    font-weight: 750;
    color: #991b1b;
}

.pa-issues-list {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.pa-issue-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.55rem 0.75rem;
    border-radius: 6px;
    background: #ffffff;
    border: 1px solid #fee2e2;
    flex-wrap: wrap;
}

.pa-issue-identity {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 10rem;
}

.pa-issue-identity strong {
    font-size: 0.82rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-issue-identity code {
    font-size: 0.68rem;
    font-family: ui-monospace, monospace;
    color: #dc2626;
    font-weight: 700;
}

.pa-issue-reasons {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    flex: 1;
    min-width: 11rem;
}

.pa-issue-tag-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    color: #475569;
}

.pa-dialog-footer-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

/* Toast */
.pa-ikatan-toast {
    position: fixed;
    z-index: 60;
    right: 1.5rem;
    bottom: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    max-width: min(30rem, calc(100vw - 2rem));
    padding: 0.75rem 1rem;
    border: 1px solid;
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    font-size: 0.8rem;
}

.pa-ikatan-toast--success {
    color: #047857;
    border-color: rgba(16, 185, 129, 0.3);
    background: #ecfdf5;
}

.pa-ikatan-toast--danger {
    color: #b91c1c;
    border-color: rgba(239, 68, 68, 0.3);
    background: #fef2f2;
}

.pa-ikatan-toast__text {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    flex: 1;
}

.pa-ikatan-toast__close {
    background: none;
    border: none;
    cursor: pointer;
    color: inherit;
    padding: 0.1rem;
    display: inline-flex;
}

@media (max-width: 640px) {
    .pa-cockpit-group {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.5rem;
    }

    .pa-matrix-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 0.4rem;
    }

    .pa-view-switch-pills {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .pa-view-pill {
        justify-content: center;
    }

    .pa-dialog-footer-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }

    .pa-dialog-footer-actions .hcis-btn {
        width: 100%;
        justify-content: center;
    }

    .pa-issue-item {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }

    .pa-issue-action {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.4rem;
    }

    .pa-issue-action .hcis-btn {
        width: 100%;
        justify-content: center;
    }

    .pa-ikatan-toast {
        left: 1rem;
        right: 1rem;
        bottom: 1rem;
        max-width: none;
    }
}
</style>
