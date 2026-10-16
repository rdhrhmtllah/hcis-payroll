<template>
    <main class="pa-admin-page">
        <HcisHeader
            :title="
                activeMaster ? activeDefinition?.headerTitle || activeDefinition?.label : 'Administrator PA'
            "
            :kicker="activeMaster ? 'MASTER DATA' : 'KPI · MASTER DATA'"
            :description="
                activeMaster
                    ? activeDefinition?.description
                    : 'Kelola fondasi PA tanpa mengubah salinan pada cycle yang sudah disiapkan.'
            "
            :icon="activeDefinition?.headerIcon || activeDefinition?.icon || 'sliders'"
            :sentiment="activeDefinition?.headerSentiment || activeDefinition?.sentiment || 'brand'"
            variant="glass"
            :size="isMobile ? 'sm' : 'md'"
            animated
            class="mb-2 mb-md-4"
        >
            <template #badge>
                <HcisBadge
                    v-if="activeMeta"
                    :label="`${formatAngka(activeMeta.total)} baris`"
                    variant="brand"
                    appearance="glass"
                    size="sm"
                    radar
                    dot
                />
            </template>
            <template #actions>
                <div class="pa-header-actions d-flex align-items-center gap-1.5 gap-sm-2">
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :loading="loadingMaster"
                        class="pa-header-btn"
                        title="Segarkan data"
                        @click="segarkanMaster"
                    >
                        <span class="d-none d-sm-inline">Segarkan</span>
                    </HcisButton>
                    <HcisButton
                        v-if="activeMeta?.dapat_ditambah"
                        variant="primary"
                        size="sm"
                        leading-icon="plus-lg"
                        class="pa-header-btn"
                        title="Tambah data baru"
                        @click="openEditor()"
                    >
                        <span class="d-none d-sm-inline">Tambah data</span>
                        <span class="d-inline d-sm-none">Tambah</span>
                    </HcisButton>
                    <HcisButton
                        href="/performance-appraisal"
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-left"
                        class="pa-header-btn"
                        title="Kembali ke Assessment Cycle"
                    >
                        <span class="d-none d-md-inline">Assessment Cycle</span>
                        <span class="d-inline d-md-none">Cycle</span>
                    </HcisButton>
                </div>
            </template>
            <template #console>
                <nav class="pa-admin-switcher" aria-label="Navigasi Master Data Performance Appraisal">
                    <a
                        v-for="item in masterNavList"
                        :key="item.slug"
                        :href="item.href"
                        class="pa-admin-switcher__item"
                        :class="{ 'pa-admin-switcher__item--active': activeMaster === item.slug }"
                    >
                        <HcisIcon :name="item.icon" size="2xs" />
                        <span>{{ item.label }}</span>
                    </a>
                </nav>
            </template>
        </HcisHeader>

        <section class="pa-admin-content">
            <template v-if="!activeMaster">
                <HcisCard variant="glass" sentiment="brand" class="pa-landing-hero mb-4">
                    <div class="pa-landing-hero__content">
                        <div class="pa-landing-hero__text">
                            <div class="pa-landing-hero__tags">
                                <HcisBadge label="ADMINISTRATOR PA" variant="brand" appearance="glass" size="xs" dot />
                                <HcisBadge label="FOUNDATION MATRIX" variant="purple" appearance="subtle" size="xs" />
                            </div>
                            <h2 class="pa-landing-hero__title">Pusat Pengaturan Master Data Performance Appraisal</h2>
                            <p class="pa-landing-hero__desc">
                                Kelola daftar kompetensi, matriks kompetensi jabatan, matriks soal, komposisi penilai 360°, bobot KPI & kompetensi, serta skala nilai & grade tanpa mengganggu salinan pada cycle yang sedang berjalan.
                            </p>
                        </div>
                        <div class="pa-landing-hero__graphic">
                            <div class="pa-landing-hero__icon-circle">
                                <HcisIcon name="diagram-3-fill" size="2xl" color="brand" />
                            </div>
                        </div>
                    </div>
                </HcisCard>

                <div class="pa-admin-cards-section">
                    <div class="pa-admin-cards-head mb-3">
                        <div>
                            <span class="pa-eyebrow">MODUL MASTER DATA</span>
                            <h3 class="pa-admin-cards-title">Pilih Ruang Kerja Master</h3>
                        </div>
                        <HcisBadge :label="`${masterTampil.length} Master Tersedia`" variant="neutral" appearance="subtle" size="sm" />
                    </div>

                    <div class="pa-admin-cards">
                        <a
                            v-for="item in masterTampil"
                            :key="item.slug"
                            :href="item.href"
                            class="pa-admin-card-link"
                        >
                            <HcisCard
                                variant="glass"
                                :sentiment="item.sentiment || 'brand'"
                                interactive
                                class="pa-admin-card"
                            >
                                <template #header>
                                    <div class="pa-admin-card__heading">
                                        <div class="pa-admin-card__icon-box" :class="`pa-admin-card-icon--${item.sentiment || 'brand'}`">
                                            <HcisIcon :name="item.icon" size="md" />
                                        </div>
                                        <HcisBadge
                                            :label="item.scopeLabel || 'MASTER'"
                                            variant="neutral"
                                            appearance="subtle"
                                            size="xs"
                                        />
                                    </div>
                                </template>

                                <h3 class="pa-admin-card__title">{{ item.label }}</h3>
                                <p class="pa-admin-card__desc">{{ item.description }}</p>

                                <template #footer>
                                    <div class="pa-admin-card__footer">
                                        <span class="pa-admin-card__cta">
                                            Buka Master <HcisIcon name="arrow-right" size="xs" />
                                        </span>
                                    </div>
                                </template>
                            </HcisCard>
                        </a>
                    </div>
                </div>
            </template>

            <template v-else>
                <HcisStatGroup
                    v-if="activeMeta && activeMaster !== 'bobot-pilar' && activeMaster !== 'pita-nilai' && activeMaster !== 'aturan-penilai' && activeMaster !== 'jenis-kompetensi' && activeMaster !== 'kompetensi'"
                    :cols="4"
                    gap="sm"
                    class="pa-master-stats"
                >
                    <HcisStatCard
                        layout="compact"
                        label="Total baris"
                        :value="formatAngka(activeMeta.total)"
                        caption="Mengikuti penyaring server"
                        icon="database"
                        sentiment="brand"
                    />
                    <HcisStatCard
                        layout="compact"
                        label="Aktif di halaman"
                        :value="hasStatusColumn ? formatAngka(activeOnPage) : '—'"
                        caption="Status yang tampil"
                        icon="patch-check-fill"
                        :sentiment="hasStatusColumn ? 'success' : 'neutral'"
                    />
                    <HcisStatCard
                        layout="compact"
                        label="Ditampilkan"
                        :value="formatAngka(rows.length)"
                        caption="Kartu atau tabel saat ini"
                        icon="list-ul"
                        sentiment="purple"
                    />
                    <HcisStatCard
                        layout="compact"
                        label="Lingkup"
                        :value="scopeLabel(activeMeta.lingkup)"
                        caption="Batas data master"
                        icon="diagram-3-fill"
                        sentiment="teal"
                    />
                </HcisStatGroup>

                <div v-if="activeMaster === 'paket-soal' || activeMaster === 'butir-soal'" class="pa-child-switcher">
                    <button
                        type="button"
                        :class="{ active: activeMaster === 'paket-soal' }"
                        @click="gantiMaster('paket-soal')"
                    >
                        <HcisIcon name="collection" size="xs" /> Paket soal</button
                    ><button
                        type="button"
                        :class="{ active: activeMaster === 'butir-soal' }"
                        @click="gantiMaster('butir-soal')"
                    >
                        <HcisIcon name="list-check" size="xs" /> Butir soal</button
                    ><span>Lingkup data mengikuti cycle dan paket yang dipilih.</span>
                </div>

                <div v-if="false" class="d-lg-none pa-mobile-nav">
                    <div class="pa-segmented-nav" role="tablist" aria-label="Tampilan master PA">
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="mobileTab === 'daftar'"
                            :class="{ active: mobileTab === 'daftar' }"
                            @click="mobileTab = 'daftar'"
                        >
                            <HcisIcon name="list-nested" size="xs" /> Daftar
                            <span>{{ activeMeta?.total || 0 }}</span>
                        </button>
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="mobileTab === 'detail'"
                            :disabled="!selectedRecord"
                            :class="{ active: mobileTab === 'detail' }"
                            @click="mobileTab = 'detail'"
                        >
                            <HcisIcon name="file-earmark-text" size="xs" /> Rincian
                        </button>
                    </div>
                </div>

                <HcisFilterBar
                    v-if="activeMaster !== 'bobot-pilar' && activeMaster !== 'pita-nilai' && activeMaster !== 'aturan-penilai' && activeMaster !== 'jenis-kompetensi' && activeMaster !== 'kompetensi'"
                    :search="filters.q"
                    search-placeholder="Cari kode atau nama master"
                    :quick-chips="statusQuickChips"
                    :active-chip-value="statusChipValue"
                    :show-view-toggle="true"
                    :view-mode="viewMode"
                    :filters="filterBarDefinitions"
                    :filter-values="filterBarValues"
                    variant="card"
                    :sentiment="activeDefinition?.sentiment || 'brand'"
                    glow
                    :results-count="activeMeta?.total ?? null"
                    show-active-tags
                    class="pa-master-filter-bar"
                    @update:search="ubahPencarian"
                    @update:view-mode="viewMode = $event"
                    @chip-click="ubahStatusChip"
                    @filter-change="ubahFilterBar"
                    @reset="resetFilters"
                />

                <!-- Dedicated Specialized View: Bobot Pilar -->
                <PaBobotPilarView
                    v-if="activeMaster === 'bobot-pilar'"
                    :rows="rows"
                    :references="references"
                    :loading="loadingMaster"
                    :error="errorMaster"
                    :search-query="filters.q"
                    :selected-level-filter="filters.id_level"
                    @edit="(row, lvl) => openBobotPilarEditor(row, lvl)"
                    @add="(lvl) => openBobotPilarEditor(null, lvl)"
                    @detail="openDetail"
                />

                <!-- Dedicated Specialized View: Rentang Nilai (kunci API tetap 'pita-nilai') -->
                <PaPitaNilaiView
                    v-else-if="activeMaster === 'pita-nilai'"
                    :rows="rows"
                    :references="references"
                    :loading="loadingMaster"
                    :error="errorMaster"
                    :search-query="filters.q"
                    :can-deactivate="Boolean(activeMeta?.dapat_dinonaktifkan)"
                    @edit="(row) => openPitaNilaiEditor(row)"
                    @add="(type, usage) => openPitaNilaiEditor(null, type, usage)"
                    @deactivate="openDeactivate"
                    @detail="openDetail"
                />

                <!-- Dedicated Specialized View: Aturan Penilai -->
                <PaAturanPenilaiView
                    v-else-if="activeMaster === 'aturan-penilai'"
                    :rows="rows"
                    :references="references"
                    :loading="loadingMaster"
                    :error="errorMaster"
                    :search-query="filters.q"
                    :selected-level-filter="filters.id_level"
                    @policy="openPolicy"
                    @create="(lvl) => openAturanPenilaiEditor(null, false, lvl)"
                    @edit="(row) => openAturanPenilaiEditor(row, false)"
                    @new-version="(row) => openAturanPenilaiEditor(row, true)"
                    @activate="activatePolicy"
                    @detail="openDetail"
                />

                <!-- Dedicated Specialized View: Jenis Kompetensi -->
                <PaJenisKompetensiView
                    v-else-if="activeMaster === 'jenis-kompetensi'"
                    :rows="rows"
                    :references="references"
                    :loading="loadingMaster"
                    :error="errorMaster"
                    :search-query="filters.q"
                    :can-deactivate="Boolean(activeMeta?.dapat_dinonaktifkan)"
                    @edit="(row) => openEditor(row)"
                    @add="openEditor(null)"
                    @deactivate="openDeactivate"
                    @detail="openDetail"
                />

                <!-- Dedicated Specialized View: Daftar Kompetensi -->
                <PaKompetensiView
                    v-else-if="activeMaster === 'kompetensi'"
                    :rows="rows"
                    :references="references"
                    :loading="loadingMaster"
                    :error="errorMaster"
                    :search-query="filters.q"
                    :can-deactivate="Boolean(activeMeta?.dapat_dinonaktifkan)"
                    @edit="(row) => openEditor(row)"
                    @add="openEditor(null)"
                    @deactivate="openDeactivate"
                    @detail="openDetail"
                    @tingkat="(row) => (kompetensiTingkat = row)"
                />

                <div v-else class="pa-master-workspace">
                    <section
                        class="pa-master-list-panel"
                        :class="{ 'pa-mobile-hidden': mobileTab === 'detail' }"
                        aria-label="Daftar master"
                    >
                        <div v-if="false" class="pa-list-panel__controls">
                            <section class="pa-master-filters">
                                <HcisField
                                    id="pa-master-search"
                                    v-model="filters.q"
                                    label="Cari"
                                    placeholder="Cari kode atau nama master"
                                    leading-icon="search"
                                    clearable
                                    size="sm"
                                    @update:model-value="ubahPencarian"
                                    @keyup.enter="loadMaster(1)"
                                />
                                <div class="pa-filter-field">
                                    <label for="pa-master-status">Status</label>
                                    <HcisSelect
                                        id="pa-master-status"
                                        v-model="filters.flag_aktif"
                                        size="sm"
                                        :filterable="false"
                                        :clearable="false"
                                        leading-icon="patch-check"
                                        placeholder="Semua status"
                                        @update:model-value="loadMaster(1)"
                                    >
                                        <HcisSelectOption value="" label="Aktif dan nonaktif" />
                                        <HcisSelectOption value="Y" label="Aktif" icon="check-circle" />
                                        <HcisSelectOption value="T" label="Nonaktif" icon="slash-circle" />
                                    </HcisSelect>
                                </div>
                                <div v-if="needsLevel" class="pa-filter-field">
                                    <label for="pa-master-level">Level</label>
                                    <HcisSelect
                                        id="pa-master-level"
                                        v-model="filters.id_level"
                                        size="sm"
                                        filterable
                                        clearable
                                        leading-icon="diagram-3"
                                        placeholder="Semua level"
                                        @update:model-value="loadMaster(1)"
                                    >
                                        <HcisSelectOption value="" label="Semua level" />
                                        <HcisSelectOption
                                            v-for="item in references.level || []"
                                            :key="item.ID_Level"
                                            :value="item.ID_Level"
                                            :label="item.Nama_Level"
                                        />
                                    </HcisSelect>
                                </div>
                                <div v-if="needsAxis" class="pa-filter-field">
                                    <label for="pa-master-axis">Score_Type</label>
                                    <HcisSelect
                                        id="pa-master-axis"
                                        v-model="filters.sumbu"
                                        size="sm"
                                        filterable
                                        clearable
                                        leading-icon="bullseye"
                                        placeholder="Semua tipe penilaian"
                                        @update:model-value="loadMaster(1)"
                                    >
                                        <HcisSelectOption value="" label="Semua tipe penilaian" />
                                        <HcisSelectOption
                                            v-for="item in references.sumbu || []"
                                            :key="item.Kode_Type"
                                            :value="item.Kode_Type"
                                            :label="item.Nama_Type"
                                        />
                                    </HcisSelect>
                                </div>
                                <div v-if="needsUse" class="pa-filter-field">
                                    <label for="pa-master-use">Score_Usage</label>
                                    <HcisSelect
                                        id="pa-master-use"
                                        v-model="filters.kegunaan"
                                        size="sm"
                                        filterable
                                        clearable
                                        leading-icon="tags"
                                        placeholder="Semua kegunaan"
                                        @update:model-value="loadMaster(1)"
                                    >
                                        <HcisSelectOption value="" label="Semua kegunaan" />
                                        <HcisSelectOption
                                            v-for="item in references.kegunaan || []"
                                            :key="item.Kode_Usage"
                                            :value="item.Kode_Usage"
                                            :label="item.Nama_Usage"
                                        />
                                    </HcisSelect>
                                </div>
                                <div v-if="needsCycle" class="pa-filter-field pa-filter-field--wide">
                                    <label for="pa-master-cycle">Cycle</label>
                                    <HcisSelect
                                        id="pa-master-cycle"
                                        v-model="filters.cycle_id"
                                        size="sm"
                                        filterable
                                        clearable
                                        leading-icon="calendar3"
                                        placeholder="Pilih cycle"
                                        @update:model-value="ubahSiklus"
                                    >
                                        <HcisSelectOption value="" label="Pilih cycle" />
                                        <HcisSelectOption
                                            v-for="item in references.siklus || []"
                                            :key="item.Id_PA_Cycle"
                                            :value="item.Id_PA_Cycle"
                                            :label="`${item.Kode_Cycle} · ${item.Nama_Cycle}`"
                                            :description="`Tahun ${item.Tahun} · ${item.Status_Cycle}`"
                                            :badge="item.Status_Cycle"
                                            badge-variant="brand"
                                        />
                                    </HcisSelect>
                                </div>
                                <div v-if="needsPackage" class="pa-filter-field pa-filter-field--wide">
                                    <label for="pa-master-package">Paket</label>
                                    <HcisSelect
                                        id="pa-master-package"
                                        v-model="filters.package_id"
                                        size="sm"
                                        filterable
                                        clearable
                                        leading-icon="collection"
                                        placeholder="Pilih paket"
                                        @update:model-value="loadMaster(1)"
                                    >
                                        <HcisSelectOption value="" label="Pilih paket" />
                                        <HcisSelectOption
                                            v-for="item in packageOptions"
                                            :key="item.Id_PA_Cycle_Question_Package"
                                            :value="item.Id_PA_Cycle_Question_Package"
                                            :label="`${item.Id_Master_Ujian} · ${item.Nama_Ujian_Sumber}`"
                                            :description="`Level ${item.ID_Level} · ${item.Sumber_Kontrak || 'Sumber belum dicatat'}`"
                                            :badge="item.ID_Level ? `L${item.ID_Level}` : ''"
                                            badge-variant="teal"
                                        />
                                    </HcisSelect>
                                </div>
                                <HcisButton
                                    label="Terapkan"
                                    variant="secondary"
                                    size="sm"
                                    leading-icon="funnel"
                                    @click="loadMaster(1)"
                                />
                            </section>

                            <div
                                v-if="supportsStatusFilter"
                                class="pa-quick-filters"
                                aria-label="Penyaring cepat status"
                            >
                                <span>Status cepat</span>
                                <button
                                    type="button"
                                    :class="{ active: filters.flag_aktif === '' }"
                                    @click="
                                        filters.flag_aktif = '';
                                        loadMaster(1);
                                    "
                                >
                                    Semua
                                </button>
                                <button
                                    type="button"
                                    :class="{ active: filters.flag_aktif === 'Y' }"
                                    @click="
                                        filters.flag_aktif = 'Y';
                                        loadMaster(1);
                                    "
                                >
                                    Aktif
                                </button>
                                <button
                                    type="button"
                                    :class="{ active: filters.flag_aktif === 'T' }"
                                    @click="
                                        filters.flag_aktif = 'T';
                                        loadMaster(1);
                                    "
                                >
                                    Nonaktif
                                </button>
                            </div>

                            <div v-if="activeFilterTags.length" class="pa-active-tags" aria-label="Penyaring aktif">
                                <span
                                    v-for="tag in activeFilterTags"
                                    :key="tag.key"
                                    class="pa-active-tag"
                                    role="button"
                                    tabindex="0"
                                    :title="`Hapus ${tag.label}`"
                                    @click="clearFilter(tag.key)"
                                    @keydown.enter.prevent="clearFilter(tag.key)"
                                >
                                    {{ tag.label }} <HcisIcon name="x-circle-fill" size="2xs" />
                                </span>
                                <button type="button" class="pa-reset-filters" @click="resetFilters">
                                    Reset filter
                                </button>
                            </div>

                            <div v-if="activeMeta" class="pa-master-caption">
                                <span
                                    ><HcisIcon name="info-circle" size="2xs" /> Lingkup:
                                    <strong>{{ scopeLabel(activeMeta.lingkup) }}</strong></span
                                ><span v-if="activeMeta.dipangkas" class="pa-warning-text"
                                    ><HcisIcon name="exclamation-triangle-fill" size="2xs" /> Jumlah per halaman
                                    dipangkas server.</span
                                >
                            </div>
                        </div>
                        <div class="pa-list-panel__header">
                            <div>
                                <span class="pa-eyebrow">{{ activeDefinition?.scopeLabel || 'MASTER' }} DATA</span>
                                <strong>Daftar {{ activeMeta?.nama || activeDefinition?.label }}</strong>
                            </div>
                            <HcisBadge
                                :label="`${formatAngka(rows.length)} tampil`"
                                variant="neutral"
                                appearance="subtle"
                                size="xs"
                            />
                        </div>

                        <HcisResultState
                            v-if="errorMaster"
                            sentiment="danger"
                            size="md"
                            :title="errorMasterStatus === 403 ? 'Akses master ditolak' : 'Data master gagal dimuat'"
                            :description="errorMaster"
                        >
                            <template #actions>
                                <HcisButton
                                    v-if="errorMasterStatus !== 403"
                                    variant="primary"
                                    size="sm"
                                    leading-icon="arrow-clockwise"
                                    label="Coba lagi"
                                    @click="loadMaster(halaman)"
                                />
                            </template>
                        </HcisResultState>
                        <div v-else-if="loadingMaster" class="pa-master-loading">
                            <div v-if="viewMode === 'grid'" class="pa-master-skeleton-grid">
                                <div v-for="n in 8" :key="`master-skel-${n}`" class="pa-master-skeleton-card">
                                    <div class="pa-master-skeleton-card__head">
                                        <HcisSkeleton width="38px" height="38px" rounded="md" />
                                        <div>
                                            <HcisSkeleton width="125px" height="0.85rem" rounded="sm" />
                                            <HcisSkeleton width="72px" height="0.65rem" rounded="pill" />
                                        </div>
                                    </div>
                                    <HcisSkeleton width="90%" height="0.7rem" rounded="sm" />
                                    <HcisSkeleton width="65%" height="0.7rem" rounded="sm" />
                                    <div class="pa-master-skeleton-card__footer">
                                        <HcisSkeleton width="74px" height="0.65rem" rounded="sm" />
                                        <HcisSkeleton width="76px" height="1.45rem" rounded="md" />
                                    </div>
                                </div>
                            </div>
                            <div v-else class="pa-master-skeleton-table">
                                <div v-for="n in 6" :key="`master-table-skel-${n}`">
                                    <HcisSkeleton width="28%" height="0.8rem" rounded="sm" />
                                    <HcisSkeleton width="22%" height="0.7rem" rounded="sm" />
                                    <HcisSkeleton width="13%" height="1.35rem" rounded="pill" />
                                    <HcisSkeleton width="15%" height="1.5rem" rounded="md" />
                                </div>
                            </div>
                        </div>
                        <HcisEmptyState
                            v-else-if="!rows.length"
                            variant="card"
                            sentiment="neutral"
                            size="md"
                            animated
                            title="Belum Ada Data Master"
                            description="Belum ada data pada kriteria penyaring saat ini. Ubah kata kunci penyaring atau tambahkan data baru."
                            icon="database"
                            class="my-4"
                        >
                            <template #actions>
                                <div class="pa-empty-actions-row">
                                    <HcisButton
                                        v-if="filters.q || filters.flag_aktif || filters.id_level"
                                        label="Reset Filter"
                                        variant="secondary"
                                        size="sm"
                                        leading-icon="arrow-counterclockwise"
                                        @click="resetFilters"
                                    />
                                    <HcisButton
                                        v-if="activeMeta?.dapat_ditambah"
                                        label="Tambah Data"
                                        variant="primary"
                                        size="sm"
                                        leading-icon="plus-lg"
                                        @click="openEditor()"
                                    />
                                </div>
                            </template>
                        </HcisEmptyState>
                        <div v-else class="pa-master-loaded">
                            <div v-if="viewMode === 'grid'" class="pa-master-list-cards">
                                <article
                                    v-for="row in rows"
                                    :key="`card-${rowId(row)}`"
                                    class="pa-master-list-card"
                                    :class="{ 'pa-master-list-card--active': selectedId === rowId(row) }"
                                    tabindex="0"
                                    role="button"
                                    :aria-label="`Detail ${rowTitle(row)}`"
                                    @click="openDetail(row)"
                                    @keydown.enter.prevent="openDetail(row)"
                                >
                                    <div class="pa-master-list-card__icon">
                                        <HcisIcon :name="activeDefinition?.icon || 'database'" size="sm" />
                                    </div>
                                    <div class="pa-master-list-card__body">
                                        <div class="pa-master-list-card__topline">
                                            <strong>{{ rowTitle(row) }}</strong>
                                            <HcisStatusBadge
                                                v-if="row.Flag_Aktif || row.Status_Policy"
                                                :status="rowStatus(row)"
                                                :label="
                                                    row.Flag_Aktif === 'Y'
                                                        ? 'Aktif'
                                                        : row.Flag_Aktif === 'T'
                                                          ? 'Nonaktif'
                                                          : row.Status_Policy
                                                "
                                                size="xs"
                                            />
                                        </div>
                                        <span>{{ rowCode(row) }}</span>
                                        <small>{{ activeDefinition?.short }} · klik untuk melihat rincian</small>
                                    </div>
                                    <div class="pa-master-list-card__footer">
                                        <span><HcisIcon name="info-circle" size="2xs" /> Detail master</span>
                                        <div class="pa-master-list-card__actions">
                                            <HcisButton
                                                v-if="activeMaster === 'aturan-penilai'"
                                                label="Rincian"
                                                variant="ghost"
                                                size="xs"
                                                leading-icon="list-check"
                                                @click.stop="openPolicy(row)"
                                            />
                                            <HcisButton
                                                v-if="activeMaster === 'paket-soal'"
                                                label="Butir"
                                                variant="ghost"
                                                size="xs"
                                                leading-icon="list-check"
                                                @click.stop="openPackage(row)"
                                            />
                                            <HcisButton
                                                label="Ubah"
                                                variant="ghost"
                                                size="xs"
                                                leading-icon="pencil-square"
                                                @click.stop="openEditor(row)"
                                            />
                                            <HcisButton
                                                v-if="activeMeta?.dapat_dinonaktifkan && row.Flag_Aktif === 'Y'"
                                                label="Nonaktifkan"
                                                variant="danger-quiet"
                                                size="xs"
                                                leading-icon="slash-circle"
                                                @click.stop="openDeactivate(row)"
                                            />
                                        </div>
                                    </div>
                                </article>
                            </div>
                            <HcisTable
                                v-if="viewMode === 'table'"
                                :columns="tableColumns"
                                :rows="rows"
                                :row-key="tableRowKey"
                                responsive="scroll"
                                hoverable
                                interactive
                                variant="glass"
                                :sentiment="activeDefinition?.sentiment || 'brand'"
                                @row-click="({ row }) => openDetail(row)"
                            >
                                <template #cell-Nama_Type="{ row }">
                                    <div class="pa-table-primary-cell">
                                        <strong>{{ rowTitle(row) }}</strong>
                                        <small>{{ rowCode(row) }}</small>
                                    </div>
                                </template>
                                <template #cell-Nama_Competency="{ row }">
                                    <div class="pa-table-primary-cell">
                                        <strong>{{ rowTitle(row) }}</strong>
                                        <small>{{ rowCode(row) }}</small>
                                    </div>
                                </template>
                                <template #cell-Expected_Behaviour="{ row }">
                                    <span
                                        class="pa-perilaku-chip"
                                        :class="{ 'is-kosong': !pecahPerilaku(row.Expected_Behaviour).length }"
                                        :title="pecahPerilaku(row.Expected_Behaviour).join('\n')"
                                    >
                                        {{ pecahPerilaku(row.Expected_Behaviour).length ? `${pecahPerilaku(row.Expected_Behaviour).length} perilaku` : 'Belum diisi' }}
                                    </span>
                                </template>
                                <template #cell-Nama_Kategori="{ row }">
                                    <div class="pa-table-primary-cell">
                                        <strong>{{ rowTitle(row) }}</strong>
                                        <small>{{ rowCode(row) }}</small>
                                    </div>
                                </template>
                                <template #cell-Nama_Policy="{ row }">
                                    <div class="pa-table-primary-cell">
                                        <strong>{{ rowTitle(row) }}</strong>
                                        <small>{{ rowCode(row) }}</small>
                                    </div>
                                </template>
                                <template #cell-Nama_Range="{ row }">
                                    <div class="pa-table-primary-cell">
                                        <strong>{{ rowTitle(row) }}</strong>
                                        <small>{{ rowCode(row) }}</small>
                                    </div>
                                </template>
                                <template #cell-Nama_Kuadran="{ row }">
                                    <div class="pa-table-primary-cell">
                                        <strong>{{ rowTitle(row) }}</strong>
                                        <small>{{ rowCode(row) }}</small>
                                    </div>
                                </template>
                                <template #cell-Nama_Ujian_Sumber="{ row }">
                                    <div class="pa-table-primary-cell">
                                        <strong>{{ rowTitle(row) }}</strong>
                                        <small>{{ rowCode(row) }}</small>
                                    </div>
                                </template>
                                <template #cell-Nama_Bagian_Sumber="{ row }">
                                    <div class="pa-table-primary-cell">
                                        <strong>{{ rowTitle(row) }}</strong>
                                        <small>{{ rowCode(row) }}</small>
                                    </div>
                                </template>
                                <template #cell-Flag_Aktif="{ row }">
                                    <HcisStatusBadge
                                        :status="rowStatus(row)"
                                        :label="row.Flag_Aktif === 'Y' ? 'Aktif' : 'Nonaktif'"
                                        size="xs"
                                    />
                                </template>
                                <template #cell-Status_Policy="{ row }">
                                    <HcisStatusBadge :status="rowStatus(row)" :label="row.Status_Policy" size="xs" />
                                </template>
                                <template #cell-aksi="{ row }">
                                    <div class="pa-row-actions">
                                        <HcisButton
                                            variant="ghost"
                                            size="xs"
                                            label="Detail"
                                            leading-icon="eye"
                                            @click.stop="openDetail(row)"
                                        />
                                        <HcisButton
                                            variant="ghost"
                                            size="xs"
                                            label="Ubah"
                                            leading-icon="pencil-square"
                                            @click.stop="openEditor(row)"
                                        />
                                        <HcisButton
                                            v-if="activeMeta?.dapat_dinonaktifkan && row.Flag_Aktif === 'Y'"
                                            variant="danger-quiet"
                                            size="xs"
                                            label="Nonaktifkan"
                                            leading-icon="slash-circle"
                                            @click.stop="openDeactivate(row)"
                                        />
                                    </div>
                                </template>
                            </HcisTable>
                            <div v-if="false" class="pa-master-table-wrap">
                                <table class="pa-master-table">
                                    <thead>
                                        <tr>
                                            <th v-for="column in tableColumns" :key="column.key">
                                                {{ column.label }}
                                            </th>
                                            <th class="pa-align-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in rows" :key="rowId(row)">
                                            <td v-for="column in tableColumns" :key="column.key">
                                                <template v-if="column.key === 'Flag_Aktif'"
                                                    ><HcisStatusBadge
                                                        :status="row[column.key] === 'Y' ? 'ACTIVE' : 'INACTIVE'"
                                                        :label="row[column.key] === 'Y' ? 'Aktif' : 'Nonaktif'"
                                                        size="xs" /></template
                                                ><template v-else-if="column.key === 'Status_Policy'"
                                                    ><HcisStatusBadge
                                                        :status="row[column.key]"
                                                        :label="row[column.key]"
                                                        size="xs" /></template
                                                ><template v-else
                                                    ><div class="pa-cell">
                                                        <strong v-if="column.emphasis">{{
                                                            displayValue(row[column.key])
                                                        }}</strong
                                                        ><span v-else>{{ displayValue(row[column.key]) }}</span
                                                        ><small v-if="column.secondary && row[column.secondary]">{{
                                                            displayValue(row[column.secondary])
                                                        }}</small>
                                                    </div></template
                                                >
                                            </td>
                                            <td class="pa-align-right">
                                                <div class="pa-row-actions">
                                                    <HcisButton
                                                        v-if="activeMaster === 'aturan-penilai'"
                                                        label="Rincian"
                                                        variant="ghost"
                                                        size="xs"
                                                        leading-icon="list-check"
                                                        @click="openPolicy(row)"
                                                    /><HcisButton
                                                        v-if="activeMaster === 'paket-soal'"
                                                        label="Butir"
                                                        variant="ghost"
                                                        size="xs"
                                                        leading-icon="list-check"
                                                        @click="openPackage(row)"
                                                    /><HcisButton
                                                        label="Ubah"
                                                        variant="secondary"
                                                        size="xs"
                                                        leading-icon="pencil-square"
                                                        @click="openEditor(row)"
                                                    /><HcisButton
                                                        v-if="activeMeta?.dapat_dinonaktifkan && row.Flag_Aktif === 'Y'"
                                                        label="Nonaktifkan"
                                                        variant="danger-quiet"
                                                        size="xs"
                                                        leading-icon="slash-circle"
                                                        @click="openDeactivate(row)"
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div v-if="activeMeta && activeMeta.total_halaman > 1" class="pa-master-pagination">
                            <HcisButton
                                label="Sebelumnya"
                                variant="secondary"
                                size="sm"
                                leading-icon="chevron-left"
                                :disabled="halaman <= 1 || loadingMaster"
                                @click="loadMaster(halaman - 1)"
                            /><span>Halaman {{ halaman }} dari {{ activeMeta.total_halaman }}</span
                            ><HcisButton
                                label="Berikutnya"
                                variant="secondary"
                                size="sm"
                                trailing-icon="chevron-right"
                                :disabled="halaman >= activeMeta.total_halaman || loadingMaster"
                                @click="loadMaster(halaman + 1)"
                            />
                        </div>
                    </section>

                    <aside
                        v-if="false"
                        class="pa-master-detail-panel"
                        :class="{ 'pa-mobile-hidden': mobileTab === 'daftar' }"
                        aria-label="Rincian master terpilih"
                    >
                        <div class="d-lg-none pa-detail-back">
                            <HcisButton
                                variant="secondary"
                                size="sm"
                                block
                                leading-icon="arrow-left"
                                label="Kembali ke daftar"
                                @click="mobileTab = 'daftar'"
                            />
                        </div>

                        <HcisCard
                            v-if="!selectedRecord"
                            variant="glass"
                            sentiment="neutral"
                            size="lg"
                            class="pa-detail-card"
                        >
                            <HcisEmptyState
                                icon="cursor"
                                title="Pilih data master"
                                description="Pilih salah satu kartu di sebelah kiri untuk melihat metadata, status, dan aksi yang tersedia."
                                variant="flat"
                                sentiment="neutral"
                            />
                        </HcisCard>

                        <HcisCard
                            v-else
                            variant="glass"
                            :sentiment="activeDefinition?.sentiment || 'brand'"
                            size="lg"
                            class="pa-detail-card"
                        >
                            <div class="pa-detail-hero">
                                <div class="pa-detail-hero__badges">
                                    <HcisBadge
                                        :label="activeDefinition?.scopeLabel || 'MASTER'"
                                        variant="neutral"
                                        appearance="subtle"
                                        size="xs"
                                    />
                                    <HcisStatusBadge
                                        v-if="selectedRecord.Flag_Aktif || selectedRecord.Status_Policy"
                                        :status="rowStatus(selectedRecord)"
                                        :label="
                                            selectedRecord.Flag_Aktif === 'Y'
                                                ? 'Aktif'
                                                : selectedRecord.Flag_Aktif === 'T'
                                                  ? 'Nonaktif'
                                                  : selectedRecord.Status_Policy
                                        "
                                        size="sm"
                                    />
                                </div>
                                <div class="pa-detail-hero__actions">
                                    <HcisButton
                                        label="Ubah"
                                        variant="secondary"
                                        size="sm"
                                        leading-icon="pencil-square"
                                        @click="openEditor(selectedRecord)"
                                    />
                                    <HcisButton
                                        v-if="activeMeta?.dapat_dinonaktifkan && selectedRecord.Flag_Aktif === 'Y'"
                                        label="Nonaktifkan"
                                        variant="danger-quiet"
                                        size="sm"
                                        leading-icon="slash-circle"
                                        @click="openDeactivate(selectedRecord)"
                                    />
                                </div>
                                <h3>{{ rowTitle(selectedRecord) }}</h3>
                                <div class="pa-detail-hero__code">
                                    <HcisIcon name="hash" size="xs" /> {{ rowCode(selectedRecord) }}
                                </div>
                            </div>

                            <HcisStatGroup :cols="3" gap="sm" class="pa-detail-stats">
                                <HcisStatCard
                                    layout="compact"
                                    label="Status"
                                    :value="rowStatus(selectedRecord)"
                                    caption="Dari respons server"
                                    icon="shield-check"
                                    :sentiment="selectedRecord.Flag_Aktif === 'Y' ? 'success' : 'neutral'"
                                />
                                <HcisStatCard
                                    layout="compact"
                                    label="Atribut"
                                    :value="formatAngka(selectedDetailFields.length)"
                                    caption="Field tersedia"
                                    icon="list-columns"
                                    sentiment="brand"
                                />
                                <HcisStatCard
                                    layout="compact"
                                    label="Lingkup"
                                    :value="scopeLabel(activeMeta?.lingkup)"
                                    caption="Aturan data"
                                    icon="diagram-3"
                                    sentiment="teal"
                                />
                            </HcisStatGroup>

                            <HcisTabs
                                v-model="detailTab"
                                :tabs="detailTabs"
                                variant="segmented"
                                class="pa-detail-tabs"
                            />

                            <div v-if="detailTab === 'ringkasan'" class="pa-detail-section">
                                <div class="pa-detail-section__heading">
                                    <div>
                                        <span class="pa-eyebrow">IDENTITAS &amp; KONFIGURASI</span>
                                        <h4>Informasi master</h4>
                                    </div>
                                    <HcisIcon name="info-square" size="md" color="brand" variant="boxed" />
                                </div>
                                <div class="pa-detail-grid">
                                    <div
                                        v-for="field in selectedDetailFields.slice(0, 8)"
                                        :key="field.key"
                                        class="pa-detail-field"
                                    >
                                        <span>{{ field.label }}</span>
                                        <template v-if="field.key === 'Flag_Aktif' || field.key === 'Status_Policy'">
                                            <HcisStatusBadge
                                                :status="rowStatus(selectedRecord)"
                                                :label="displayValue(selectedRecord[field.key])"
                                                size="xs"
                                            />
                                        </template>
                                        <strong v-else>{{ displayValue(selectedRecord[field.key]) }}</strong>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="pa-detail-section">
                                <div class="pa-detail-section__heading">
                                    <div>
                                        <span class="pa-eyebrow">DATA CONTRACT</span>
                                        <h4>Metadata lengkap</h4>
                                    </div>
                                    <HcisIcon name="braces" size="md" color="purple" variant="boxed" />
                                </div>
                                <dl class="pa-metadata-list">
                                    <template v-for="field in selectedDetailFields" :key="`metadata-${field.key}`">
                                        <dt>{{ field.label }}</dt>
                                        <dd v-if="field.key === 'Expected_Behaviour' && pecahPerilaku(selectedRecord[field.key]).length">
                                            <PaDaftarPerilaku :teks="selectedRecord[field.key]" judul="" />
                                        </dd>
                                        <dd v-else>{{ displayValue(selectedRecord[field.key]) }}</dd>
                                    </template>
                                </dl>
                            </div>

                            <div
                                v-if="activeMaster === 'aturan-penilai' || activeMaster === 'paket-soal'"
                                class="pa-detail-special"
                            >
                                <div>
                                    <HcisIcon
                                        :name="activeMaster === 'aturan-penilai' ? 'list-check' : 'collection'"
                                        size="sm"
                                        color="purple"
                                    />
                                    <div>
                                        <strong>{{
                                            activeMaster === 'aturan-penilai'
                                                ? 'Rincian aturan penilai'
                                                : 'Butir soal pada paket'
                                        }}</strong>
                                        <span>{{
                                            activeMaster === 'aturan-penilai'
                                                ? 'Simpan seluruh bobot kategori sekaligus agar validasi server tetap utuh.'
                                                : 'Buka pemetaan butir soal yang melekat pada paket cycle ini.'
                                        }}</span>
                                    </div>
                                </div>
                                <HcisButton
                                    :label="activeMaster === 'aturan-penilai' ? 'Buka rincian' : 'Buka butir'"
                                    variant="ghost"
                                    size="sm"
                                    trailing-icon="arrow-right"
                                    @click="
                                        activeMaster === 'aturan-penilai'
                                            ? openPolicy(selectedRecord)
                                            : openPackage(selectedRecord)
                                    "
                                />
                            </div>
                        </HcisCard>
                    </aside>
                </div>
            </template>
        </section>

        <PaMasterEditorDialog
            v-model="editorOpen"
            :title="
                editorRecord
                    ? `Ubah ${activeMeta?.nama || activeDefinition?.label}`
                    : `Tambah ${activeMeta?.nama || activeDefinition?.label}`
            "
            :description="activeDefinition?.description || ''"
            :fields="editorFields"
            :record="editorRecord"
            :error="editorError"
            :saving="saving"
            @save="saveMaster"
        />
        <PaBobotPilarEditorDialog
            v-model="bobotPilarEditorOpen"
            :record="bobotPilarRecord"
            :existing-rows="rows"
            :references="references"
            :error="bobotPilarError"
            :saving="savingBobotPilar"
            @save="saveBobotPilar"
        />
        <PaPitaNilaiEditorDialog
            v-model="pitaNilaiEditorOpen"
            :record="pitaNilaiRecord"
            :existing-rows="rows"
            :references="references"
            :default-type="pitaNilaiDefaultType"
            :default-usage="pitaNilaiDefaultUsage"
            :error="pitaNilaiError"
            :saving="savingPitaNilai"
            @save="savePitaNilai"
        />
        <PaAturanPenilaiEditorDialog
            v-model="aturanPenilaiEditorOpen"
            :record="aturanPenilaiRecord"
            :is-new-version="aturanPenilaiIsNewVersion"
            :default-level="aturanPenilaiDefaultLevel"
            :references="references"
            :error="aturanPenilaiError"
            :saving="savingAturanPenilai"
            @save="saveAturanPenilaiHeader"
        />
        <PaMasterDetailDialog
            v-model="detailOpen"
            :record="selectedRecord"
            :definition="activeDefinition"
            :fields="selectedDetailFields"
            :references="references"
            :scope-label="scopeLabel(activeMeta?.lingkup)"
            :master="activeMaster"
            :can-deactivate="Boolean(activeMeta?.dapat_dinonaktifkan)"
            @edit="editFromDetail"
            @deactivate="deactivateFromDetail"
            @policy="policyFromDetail"
            @package="packageFromDetail"
        />
        <PaPolicyDetailDialog
            v-model="policyOpen"
            :policy="policyRecord"
            :detail="policyDetail"
            :references="references"
            :loading="loadingDetail"
            :saving="savingPolicy"
            :activating="activatingPolicy"
            :error="detailError"
            :action-error="policyActionError"
            @retry="openPolicy(policyRecord)"
            @save="savePolicyDetail"
            @activate="activatePolicy"
        />

        <HcisDialog
            v-model="packageOpen"
            :title="packageRecord?.Nama_Ujian_Sumber || 'Butir soal paket'"
            kicker="DETAIL PAKET SOAL"
            icon="collection"
            sentiment="teal"
            variant="glass"
            size="lg"
            ><div class="pa-package-detail">
                <div class="pa-package-detail__identity">
                    <span>{{ packageRecord?.Id_Master_Ujian }}</span
                    ><strong>{{ packageRecord?.Catatan || 'Butir soal yang menggantung pada paket ini.' }}</strong>
                </div>
                <div v-if="loadingDetail" class="pa-master-loading">
                    <HcisIcon name="arrow-repeat" size="lg" animation="spin" color="brand" /><span
                        >Memuat butir soal...</span
                    >
                </div>
                <HcisResultState
                    v-else-if="!packageItems.length"
                    status="info"
                    variant="flat"
                    title="Belum ada butir soal"
                    description="Paket ini belum memiliki butir soal."
                    :show-retry="false"
                />
                <div v-else class="pa-package-items">
                    <div
                        v-for="item in packageItems"
                        :key="item.Id_PA_Cycle_Question_Package_Item"
                        class="pa-package-item"
                    >
                        <div>
                            <strong>{{ item.Id_Jenis_Soal }} · {{ item.Nama_Bagian_Sumber }}</strong
                            ><span>{{ item.Jenis_Soal }} · urutan {{ item.Urutan_Tampil }}</span>
                        </div>
                        <HcisBadge
                            :label="
                                item.PA_Competency_Id
                                    ? `Kompetensi ${item.PA_Competency_Id}`
                                    : 'Tanpa kompetensi'
                            "
                            variant="neutral"
                            appearance="subtle"
                            size="xs"
                        />
                    </div>
                </div>
            </div>
            <template #footer
                ><HcisButton variant="secondary" label="Tutup" size="sm" @click="packageOpen = false" /><HcisButton
                    variant="primary"
                    label="Kelola butir soal"
                    size="sm"
                    leading-icon="list-check"
                    @click="bukaButirDariPaket" /></template
        ></HcisDialog>

        <HcisDialog
            v-model="deactivateOpen"
            :title="akanDiaktifkan ? 'Aktifkan kembali data?' : 'Nonaktifkan data?'"
            kicker="PERUBAHAN STATUS"
            :icon="akanDiaktifkan ? 'check-circle' : 'slash-circle'"
            :sentiment="akanDiaktifkan ? 'success' : 'danger'"
            variant="glass"
            size="sm"
            ><p class="pa-modal-copy">
                {{ akanDiaktifkan
                    ? 'Data akan dipakai lagi oleh cycle berikutnya. Cycle yang sudah berjalan tidak berubah.'
                    : 'Data tidak dihapus. Riwayat dan cycle yang sudah memakai data ini tetap dapat membacanya.' }}
            </p>
            <div class="pa-deactivate-preview">
                <span>{{ deactivateRecord ? rowTitle(deactivateRecord) : '' }}</span
                ><HcisBadge
                    :label="akanDiaktifkan ? 'Nonaktif → Aktif' : 'Aktif → Nonaktif'"
                    :variant="akanDiaktifkan ? 'success' : 'danger'"
                    appearance="subtle"
                    size="sm"
                />
            </div>
            <template #footer
                ><HcisButton variant="secondary" label="Batal" size="sm" @click="deactivateOpen = false" /><HcisButton
                    :variant="akanDiaktifkan ? 'success' : 'danger'"
                    :label="akanDiaktifkan ? 'Aktifkan' : 'Nonaktifkan'"
                    size="sm"
                    :leading-icon="akanDiaktifkan ? 'check-circle' : 'slash-circle'"
                    :loading="deactivating"
                    @click="deactivate" /></template
        ></HcisDialog>

        <div v-if="notice.message" class="pa-notification" :class="`pa-notification--${notice.type}`" role="status">
            <HcisIcon
                :name="notice.type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'"
                size="sm"
            /><span>{{ notice.message }}</span
            ><button type="button" aria-label="Tutup pemberitahuan" @click="notice.message = ''">
                <HcisIcon name="x-lg" size="2xs" />
            </button>
        </div>
        <PaKompetensiTingkatDialog
            :kompetensi="kompetensiTingkat"
            @tutup="kompetensiTingkat = null"
        />
    </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import paApi from '../api/paApi.js';
import {
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDialog,
    HcisEmptyState,
    HcisField,
    HcisFilterBar,
    HcisHeader,
    HcisIcon,
    HcisResultState,
    HcisSelect,
    HcisSelectOption,
    HcisSkeleton,
    HcisStatCard,
    HcisStatGroup,
    HcisStatusBadge,
    HcisTable,
    HcisTabs,
} from '@/components/ui';
import PaMasterEditorDialog from './components/PaMasterEditorDialog.vue';
import PaDaftarPerilaku from '../components/PaDaftarPerilaku.vue';
import { pecahPerilaku } from '../utils/perilaku.js';
import PaMasterDetailDialog from './components/PaMasterDetailDialog.vue';
import PaPolicyDetailDialog from './components/PaPolicyDetailDialog.vue';
import PaBobotPilarView from './components/PaBobotPilarView.vue';
import PaBobotPilarEditorDialog from './components/PaBobotPilarEditorDialog.vue';
import PaPitaNilaiView from './components/PaPitaNilaiView.vue';
import PaPitaNilaiEditorDialog from './components/PaPitaNilaiEditorDialog.vue';
import PaAturanPenilaiView from './components/PaAturanPenilaiView.vue';
import PaAturanPenilaiEditorDialog from './components/PaAturanPenilaiEditorDialog.vue';
import PaJenisKompetensiView from './components/PaJenisKompetensiView.vue';
import PaKompetensiView from './components/PaKompetensiView.vue';
import PaKompetensiTingkatDialog from './components/PaKompetensiTingkatDialog.vue';

defineOptions({ name: 'PerformanceAppraisalMasterPage' });

// Kompetensi yang tingkatnya sedang dikelola (DDL-2026-175); null = dialog tertutup.
const kompetensiTingkat = ref(null);

/**
 * `daftar_master` dikirim server dan SUDAH DISARING menurut hak orang yang membuka.
 *
 * Tanpa membacanya, halaman ini menawarkan master yang begitu diklik menjawab 403 — dan
 * itu terjadi nyata sesudah Kuadran HAV dan Kategori Penilai dicabut (DML-2026-090):
 * datanya aman karena API-nya menolak, tetapi tombolnya masih ditawarkan. Yang bocor
 * bukan data melainkan harapan.
 *
 * Bakunya `null`, BUKAN senarai kosong. Kalau prop-nya belum sampai — halaman dibuka
 * lewat jalur yang belum mengirimnya — daftar penuh yang dipakai, bukan daftar kosong.
 * Layar yang tiba-tiba kehilangan seluruh menunya lebih membingungkan daripada layar yang
 * menawarkan satu pintu tertutup.
 */
const props = defineProps({
    daftar_master: { type: Array, default: null },
    master: { type: String, default: null },
});

const masterDefinitions = [
    {
        slug: 'jenis-kompetensi',
        label: 'Jenis Kompetensi',
        short: 'Competency Types & Dimensions',
        description: 'Master dimensi dan kelompok kompetensi yang menjadi dasar penilaian Performance Appraisal (PA 360).',
        headerTitle: 'Jenis Kompetensi',
        headerIcon: 'diagram-2-fill',
        headerSentiment: 'purple',
        icon: 'diagram-2-fill',
        sentiment: 'purple',
        color: 'purple',
        scopeLabel: 'MASTER',
        href: '/performance-appraisal/master/jenis-kompetensi',
    },
    {
        slug: 'kompetensi',
        label: 'Daftar Kompetensi',
        short: 'Competency List',
        description: 'Daftar kamus kompetensi beserta indikator perilaku untuk evaluasi PA 360.',
        headerTitle: 'Daftar Kompetensi',
        headerIcon: 'bullseye',
        headerSentiment: 'brand',
        icon: 'bullseye',
        sentiment: 'brand',
        color: 'brand',
        scopeLabel: 'MASTER',
        href: '/performance-appraisal/master/kompetensi',
    },
    {
        slug: 'syarat-kompetensi',
        label: 'Matriks Kompetensi',
        short: 'Matriks Dimensi Level',
        description: 'Matriks pemetaan dimensi dan jenis kompetensi yang wajib dievaluasi pada setiap level jabatan.',
        headerTitle: 'Matriks Kompetensi Jabatan',
        headerIcon: 'grid-3x3-gap-fill',
        headerSentiment: 'brand',
        icon: 'grid-3x3-gap-fill',
        sentiment: 'brand',
        color: 'brand',
        scopeLabel: 'MASTER',
        href: '/performance-appraisal/master/syarat-kompetensi',
    },
    {
        slug: 'ikatan-paket-soal',
        label: 'Matriks Soal',
        short: 'Matriks Soal per Level',
        description: 'Matriks pemetaan paket soal default per level jabatan dan kategori penilai untuk cycle penilaian.',
        headerTitle: 'Matriks Soal',
        headerIcon: 'diagram-3-fill',
        headerSentiment: 'teal',
        icon: 'diagram-3-fill',
        sentiment: 'teal',
        color: 'teal',
        scopeLabel: 'MASTER',
        href: '/performance-appraisal/master/ikatan-paket-soal',
    },
    {
        slug: 'kategori-penilai',
        label: 'Jenis Penilai',
        short: 'Kategori Rater 360°',
        /* HANYA nama dan deskripsinya yang bisa disunting, dan itu ditegakkan server:
           `dapat_ditambah` dan `dapat_dinonaktifkan` keduanya false di katalog master, dan
           penulisnya tidak pernah menyentuh `Kode_Kategori`. Kodenya menjadi kunci sambung
           di salinan siklus, penugasan, dan jejak hasil yang sudah tersimpan - mengubahnya
           memutus pembangunan penugasan tanpa satu pun galat yang terlihat. */
        description: 'Empat kategori penilai 360° (Atasan, Bawahan, Rekan Sejawat, Diri Sendiri). Kodenya terkunci; yang bisa disunting hanya nama dan deskripsinya.',
        headerTitle: 'Jenis Penilai 360°',
        headerIcon: 'person-badge-fill',
        headerSentiment: 'teal',
        icon: 'person-badge-fill',
        sentiment: 'teal',
        color: 'teal',
        scopeLabel: 'MASTER',
        href: '/performance-appraisal/master/kategori-penilai',
    },
    {
        slug: 'aturan-penilai',
        label: 'Komposisi Penilai',
        short: 'Bobot Rater 360°',
        description: 'Konfigurasi komposisi kategori penilai 360°, alokasi bobot persentase, dan kuota rater per level jabatan.',
        headerTitle: 'Komposisi & Bobot Penilai',
        headerIcon: 'people-fill',
        headerSentiment: 'purple',
        icon: 'people-fill',
        sentiment: 'purple',
        color: 'purple',
        scopeLabel: 'MASTER',
        href: '/performance-appraisal/master/aturan-penilai',
    },
    {
        slug: 'bobot-pilar',
        label: 'Bobot KPI & Kompetensi',
        short: 'Proporsi KPI vs Kompetensi',
        description: 'Atur proporsi pembagian bobot antara capaian KPI dan evaluasi Kompetensi per level jabatan.',
        headerTitle: 'Bobot KPI & Kompetensi',
        headerIcon: 'pie-chart-fill',
        headerSentiment: 'success',
        icon: 'pie-chart-fill',
        sentiment: 'success',
        color: 'success',
        scopeLabel: 'MASTER',
        href: '/performance-appraisal/master/bobot-pilar',
    },
    {
        slug: 'pita-nilai',
        label: 'Skala Nilai & Grade',
        short: 'Skala skor dan batasan grade',
        description: 'Kelola skala rentang skor, predikat hasil penilaian, dan batasan grade pencapaian PA.',
        headerTitle: 'Skala Nilai & Grade',
        headerIcon: 'bar-chart-steps',
        headerSentiment: 'warning',
        icon: 'bar-chart-steps',
        sentiment: 'warning',
        color: 'warning',
        scopeLabel: 'MASTER',
        href: '/performance-appraisal/master/pita-nilai',
    },
];

const fieldDefinitions = {
    'jenis-kompetensi': [
        {
            key: 'Kode_Type',
            payload: 'kode_type',
            label: 'Type Code',
            placeholder: 'Contoh: MANAJERIAL, CORE_VALUE, BEHAVIOR, QUALITATIVE',
            required: true,
            hint: 'Kode unik tipe kompetensi (huruf kapital / UPPERCASE).'
        },
        {
            key: 'Nama_Type',
            payload: 'nama_type',
            label: 'Competency Type Name',
            placeholder: 'Contoh: Kompetensi Manajerial, Core Values, Behavioral Indicators',
            required: true,
            hint: 'Nama dimensi penilaian yang tampil pada formulir dan radar kompetensi.'
        },
        {
            key: 'Urutan_Tampil',
            payload: 'urutan_tampil',
            label: 'Display Order',
            type: 'number',
            placeholder: '1, 2, 3...',
            hint: 'Nomor urutan kemunculan dimensi pada formulir dan radar evaluasi.'
        },
        {
            key: 'Deskripsi',
            payload: 'deskripsi',
            label: 'Description',
            type: 'textarea',
            wide: true,
            placeholder: 'Tuliskan deskripsi mengenai cakupan, tujuan, dan panduan penilaian untuk dimensi ini...',
        },
    ],
    kompetensi: [
        {
            key: 'PA_Competency_Type_Id',
            payload: 'pa_competency_type_id',
            label: 'Dimensi / Jenis Kompetensi',
            type: 'select',
            optionsKey: 'jenis_kompetensi',
            required: true,
            hint: 'Pilih dimensi kelompok kompetensi (Core Values, Manajerial, dsb.).',
        },
        {
            key: 'Kode_Competency',
            payload: 'kode_competency',
            label: 'Kode Kompetensi',
            placeholder: 'Contoh: C01, M02, B05...',
            required: true,
            hint: 'Kode unik kompetensi (huruf kapital & angka).',
        },
        {
            key: 'Nama_Competency',
            payload: 'nama_competency',
            label: 'Nama Kompetensi',
            placeholder: 'Contoh: Strategic Leadership, Integrity & Ethics...',
            required: true,
            hint: 'Nama kompetensi yang tampil pada formulir dan laporan evaluasi.',
        },
        {
            key: 'Urutan_Tampil',
            payload: 'urutan_tampil',
            label: 'Display Order',
            type: 'number',
            placeholder: '1, 2, 3...',
            hint: 'Nomor urutan kemunculan kompetensi.',
        },
        {
            key: 'Nilai_Ambang',
            payload: 'nilai_ambang',
            label: 'Ambang Nilai (KKM)',
            type: 'number',
            placeholder: 'Contoh: 75 — kosongkan bila belum ditetapkan',
            hint: 'Skala 0–100. Dikosongkan berarti belum ditetapkan, dan sumbu ini dilewati pada jaring KKM di radar — berbeda dari mengisinya 0.',
        },
        {
            key: 'Flag_Radar',
            payload: 'flag_radar',
            label: 'Tampilkan di Radar Kompetensi',
            type: 'select',
            // Sama dengan default kolomnya di database. Tanpa ini, kompetensi baru dibuka
            // dengan dropdown kosong dan HC diminta memilih ulang hal yang sudah punya
            // jawaban baku.
            default: 'Y',
            options: [
                { value: 'Y', label: 'Ya — jadi sumbu radar' },
                { value: 'T', label: 'Tidak — hanya muncul di rincian' },
            ],
            hint: 'Kompetensi yang tidak masuk radar tetap dinilai dan tetap terbaca di rincian; ia hanya tidak menjadi sumbu.',
        },
        {
            key: 'Deskripsi',
            payload: 'deskripsi',
            label: 'Deskripsi',
            type: 'textarea',
            wide: true,
            maxLength: 500,
            placeholder: 'Apa arti kompetensi ini. Contoh: Kemampuan mengurai masalah dan menarik kesimpulan berbasis data.',
            // Expected Behaviour tidak lagi menempel di kompetensi (10 Okt 2026): diisi per tingkat.
            hint: 'Expected Behaviour diisi per tingkat lewat tombol "Tingkat" di kartu kompetensi, dan yang tampil ke penilai mengikuti tingkat yang dituntut levelnya.',
        },
    ],
    'kategori-penilai': [
        { key: 'Nama_Kategori', payload: 'nama_kategori', label: 'Nama kategori', required: true },
        { key: 'Deskripsi', payload: 'deskripsi', label: 'Deskripsi', type: 'textarea', wide: true },
    ],
    'aturan-penilai': [
        { key: 'Kode_Policy', payload: 'kode_policy', label: 'Kode policy', required: true },
        { key: 'Nama_Policy', payload: 'nama_policy', label: 'Nama policy', required: true },
        { key: 'ID_Level', payload: 'id_level', label: 'Level', type: 'select', optionsKey: 'level', required: true },
        { key: 'Berlaku_Dari', payload: 'berlaku_dari', label: 'Berlaku dari', type: 'date' },
        { key: 'Berlaku_Sampai', payload: 'berlaku_sampai', label: 'Berlaku sampai', type: 'date' },
        { key: 'Deskripsi', payload: 'deskripsi', label: 'Deskripsi', type: 'textarea', wide: true },
        { key: 'Catatan_Versi', payload: 'catatan_versi', label: 'Catatan versi', type: 'textarea', wide: true },
    ],
    'bobot-pilar': [
        { key: 'ID_Level', payload: 'id_level', label: 'Level', type: 'select', optionsKey: 'level', required: true },
        { key: 'Bobot_KPI', payload: 'bobot_kpi', label: 'Bobot KPI', type: 'number', required: true },
        {
            key: 'Bobot_Kompetensi',
            payload: 'bobot_kompetensi',
            label: 'Bobot kompetensi',
            type: 'number',
            required: true,
        },
        { key: 'Catatan', payload: 'catatan', label: 'Catatan', type: 'textarea', wide: true },
    ],
    'pita-nilai': [
        {
            key: 'Score_Type',
            payload: 'sumbu',
            label: 'Score_Type',
            type: 'select',
            optionsKey: 'sumbu',
            required: true,
        },
        {
            key: 'Score_Usage',
            payload: 'kegunaan',
            label: 'Score_Usage',
            type: 'select',
            optionsKey: 'kegunaan',
            required: true,
        },
        { key: 'Kode_Range', payload: 'kode_range', label: 'Kode rentang', required: true },
        { key: 'Nama_Range', payload: 'nama_range', label: 'Nama rentang', required: true },
        { key: 'Nilai_Dari', payload: 'nilai_dari', label: 'Nilai dari', type: 'number', required: true },
        {
            key: 'Nilai_Sampai',
            payload: 'nilai_sampai',
            label: 'Nilai sampai',
            type: 'number',
            required: true,
            hint: 'Batas atas bersifat eksklusif.',
        },
        { key: 'Nilai_Hasil', payload: 'nilai_hasil', label: 'Nilai hasil', type: 'number' },
        { key: 'Target_Persen', payload: 'target_persen', label: 'Target persen', type: 'number' },
        { key: 'Warna', payload: 'warna', label: 'Warna', placeholder: '#4F46E5' },
        { key: 'Urutan_Tampil', payload: 'urutan_tampil', label: 'Urutan tampil', type: 'number' },
    ],
    'kuadran-hav': [
        { key: 'Nama_Kuadran', payload: 'nama_kuadran', label: 'Nama kuadran', required: true },
        { key: 'Deskripsi', payload: 'deskripsi', label: 'Deskripsi', type: 'textarea', wide: true },
        { key: 'Warna', payload: 'warna', label: 'Warna', placeholder: '#4F46E5' },
        { key: 'Urutan_Tampil', payload: 'urutan_tampil', label: 'Urutan tampil', type: 'number' },
    ],
    'paket-soal': [
        {
            key: 'PA_Cycle_Id',
            payload: 'pa_cycle_id',
            label: 'Cycle',
            type: 'select',
            optionsKey: 'siklus',
            required: true,
        },
        { key: 'ID_Level', payload: 'id_level', label: 'Level', type: 'select', optionsKey: 'level', required: true },
        { key: 'Id_Master_Ujian', payload: 'id_master_ujian', label: 'Kode paket', required: true },
        { key: 'Nama_Ujian_Sumber', payload: 'nama_ujian_sumber', label: 'Nama paket', required: true },
        { key: 'Sumber_Kontrak', payload: 'sumber_kontrak', label: 'Sumber kontrak' },
        { key: 'Catatan', payload: 'catatan', label: 'Catatan', type: 'textarea', wide: true },
    ],
    'butir-soal': [
        {
            key: 'PA_Cycle_Question_Package_Id',
            payload: 'pa_cycle_question_package_id',
            label: 'Paket soal',
            type: 'select',
            optionsKey: 'paket',
            required: true,
        },
        { key: 'Id_Jenis_Soal', payload: 'kode_soal', label: 'Kode soal', required: true },
        { key: 'Nama_Bagian_Sumber', payload: 'nama_soal_sumber', label: 'Nama soal', required: true },
        { key: 'Jenis_Soal', payload: 'jenis_soal', label: 'Jenis soal', required: true },
        {
            key: 'PA_Competency_Id',
            payload: 'pa_competency_id',
            label: 'Kompetensi',
            type: 'select',
            optionsKey: 'kompetensi',
        },
        { key: 'Bobot_Penilaian', payload: 'bobot_penilaian', label: 'Bobot penilaian', type: 'number' },
        { key: 'Skor_Maksimal', payload: 'maksimal_score', label: 'Skor maksimal', type: 'number' },
        { key: 'Urutan_Tampil', payload: 'urutan_tampil', label: 'Urutan tampil', type: 'number' },
    ],
};

const tableDefinitions = {
    'jenis-kompetensi': [
        { key: 'Kode_Type', label: 'Kode', emphasis: true },
        { key: 'Nama_Type', label: 'Nama', emphasis: true, secondary: 'Deskripsi' },
        { key: 'Urutan_Tampil', label: 'Urutan' },
        { key: 'Flag_Aktif', label: 'Status' },
    ],
    kompetensi: [
        { key: 'Kode_Competency', label: 'Kode', emphasis: true },
        { key: 'Nama_Competency', label: 'Kompetensi', emphasis: true, secondary: 'Deskripsi' },
        { key: 'PA_Competency_Type_Id', label: 'Jenis' },
        { key: 'Nilai_Ambang', label: 'KKM' },
        { key: 'Flag_Radar', label: 'Radar' },
        { key: 'Flag_Aktif', label: 'Status' },
    ],
    'kategori-penilai': [
        { key: 'Kode_Kategori', label: 'Kode', emphasis: true },
        { key: 'Nama_Kategori', label: 'Kategori', emphasis: true, secondary: 'Deskripsi' },
        { key: 'Flag_Aktif', label: 'Status' },
    ],
    'aturan-penilai': [
        { key: 'Kode_Policy', label: 'Kode policy', emphasis: true },
        { key: 'Nama_Policy', label: 'Nama policy', emphasis: true },
        { key: 'ID_Level', label: 'Level' },
        { key: 'Versi', label: 'Versi' },
        { key: 'Status_Policy', label: 'Status' },
    ],
    'bobot-pilar': [
        { key: 'ID_Level', label: 'Level', emphasis: true },
        { key: 'Bobot_KPI', label: 'Bobot KPI' },
        { key: 'Bobot_Kompetensi', label: 'Bobot kompetensi' },
        { key: 'Catatan', label: 'Catatan' },
    ],
    'pita-nilai': [
        { key: 'Score_Type', label: 'Score_Type', emphasis: true },
        { key: 'Score_Usage', label: 'Score_Usage' },
        { key: 'Kode_Range', label: 'Kode', emphasis: true },
        { key: 'Nama_Range', label: 'Nama rentang' },
        { key: 'Nilai_Dari', label: 'Dari' },
        { key: 'Nilai_Sampai', label: 'Sampai' },
        { key: 'Warna', label: 'Warna' },
        { key: 'Flag_Aktif', label: 'Status' },
    ],
    'kuadran-hav': [
        { key: 'Nomor_Box', label: 'Box', emphasis: true },
        { key: 'Kode_Kuadran', label: 'Kode', emphasis: true },
        { key: 'Nama_Kuadran', label: 'Kuadran' },
        { key: 'Tingkat_Performa', label: 'Performa' },
        { key: 'Tingkat_Kompetensi', label: 'Kompetensi' },
    ],
    'paket-soal': [
        { key: 'Id_Master_Ujian', label: 'Kode paket', emphasis: true },
        { key: 'Nama_Ujian_Sumber', label: 'Nama paket', emphasis: true, secondary: 'Sumber_Kontrak' },
        { key: 'PA_Cycle_Id', label: 'Cycle' },
        { key: 'ID_Level', label: 'Level' },
        { key: 'Flag_Aktif', label: 'Status' },
    ],
    'butir-soal': [
        { key: 'Id_Jenis_Soal', label: 'Kode soal', emphasis: true },
        { key: 'Nama_Bagian_Sumber', label: 'Nama soal', emphasis: true },
        { key: 'Jenis_Soal', label: 'Jenis' },
        { key: 'PA_Competency_Id', label: 'Kompetensi' },
        { key: 'Urutan_Tampil', label: 'Urutan' },
        { key: 'Flag_Aktif', label: 'Status' },
    ],
};

const references = ref({});
const activeMaster = ref(props.master || readSlug());
const rows = ref([]);
const activeMeta = ref(null);
const loadingMaster = ref(false);
const errorMaster = ref('');
const errorMasterStatus = ref(0);
const packageOptions = ref([]);
const selectedId = ref(null);
const detailOpen = ref(false);
const detailTab = ref('ringkasan');
const mobileTab = ref('daftar');
const editorOpen = ref(false);
const editorRecord = ref(null);
const editorError = ref('');
const saving = ref(false);
const policyOpen = ref(false);
const policyRecord = ref(null);
const policyDetail = ref([]);
const loadingDetail = ref(false);
const detailError = ref('');
const savingPolicy = ref(false);
const activatingPolicy = ref(false);
const policyActionError = ref('');
const packageOpen = ref(false);
const packageRecord = ref(null);
const packageItems = ref([]);
const deactivateOpen = ref(false);
const deactivateRecord = ref(null);
const deactivating = ref(false);
const bobotPilarEditorOpen = ref(false);
const bobotPilarRecord = ref(null);
const bobotPilarError = ref('');
const savingBobotPilar = ref(false);
const pitaNilaiEditorOpen = ref(false);
const pitaNilaiRecord = ref(null);
const pitaNilaiDefaultType = ref('FINAL');
const pitaNilaiDefaultUsage = ref('GRADE');
const pitaNilaiError = ref('');
const savingPitaNilai = ref(false);
const aturanPenilaiEditorOpen = ref(false);
const aturanPenilaiRecord = ref(null);
const aturanPenilaiIsNewVersion = ref(false);
const aturanPenilaiDefaultLevel = ref('');
const aturanPenilaiError = ref('');
const savingAturanPenilai = ref(false);
const notice = reactive({ type: 'success', message: '' });
const viewMode = ref('grid');
const filters = reactive({
    q: '',
    flag_aktif: '',
    id_level: '',
    sumbu: '',
    kegunaan: '',
    cycle_id: '',
    package_id: '',
    halaman: 1,
    per_halaman: 25,
});

function readSlug() {
    if (typeof window === 'undefined') return '';
    const parts = window.location.pathname.split('/').filter(Boolean);
    const index = parts.indexOf('master');
    return index >= 0 && parts[index + 1] ? parts[index + 1] : '';
}
/** Master yang benar-benar boleh dibuka orang ini. Dipakai NAVIGASI. */
const masterTampil = computed(() =>
    props.daftar_master === null
        ? masterDefinitions
        : masterDefinitions.filter((item) => props.daftar_master.includes(item.slug)),
);

const masterNavList = computed(() => {
    const allNav = [
        {
            slug: 'jenis-kompetensi',
            label: 'Jenis Kompetensi',
            icon: 'diagram-2-fill',
            href: '/performance-appraisal/master/jenis-kompetensi',
        },
        {
            slug: 'kompetensi',
            label: 'Daftar Kompetensi',
            icon: 'bullseye',
            href: '/performance-appraisal/master/kompetensi',
        },
        {
            slug: 'syarat-kompetensi',
            label: 'Matriks Kompetensi',
            icon: 'grid-3x3-gap-fill',
            href: '/performance-appraisal/master/syarat-kompetensi',
        },
        {
            slug: 'ikatan-paket-soal',
            label: 'Matriks Soal',
            icon: 'diagram-3-fill',
            href: '/performance-appraisal/master/ikatan-paket-soal',
        },
        {
            slug: 'kategori-penilai',
            label: 'Jenis Penilai',
            icon: 'person-badge-fill',
            href: '/performance-appraisal/master/kategori-penilai',
        },
        {
            slug: 'aturan-penilai',
            label: 'Komposisi Penilai',
            icon: 'people-fill',
            href: '/performance-appraisal/master/aturan-penilai',
        },
        {
            slug: 'bobot-pilar',
            label: 'Bobot KPI & Kompetensi',
            icon: 'pie-chart-fill',
            href: '/performance-appraisal/master/bobot-pilar',
        },
        {
            slug: 'pita-nilai',
            label: 'Skala Nilai & Grade',
            icon: 'bar-chart-steps',
            href: '/performance-appraisal/master/pita-nilai',
        },
        {
            slug: 'grup-kurva',
            label: 'Grup Level Kurva',
            icon: 'diagram-2-fill',
            href: '/performance-appraisal/master/grup-kurva',
        },
        {
            slug: 'relasi-organisasi',
            label: 'Relasi Organisasi',
            icon: 'people-fill',
            href: '/system/organisasi/relasi',
        },
    ];

    if (props.daftar_master === null) {
        return allNav;
    }
    return allNav.filter(
        (item) =>
            item.slug === 'syarat-kompetensi' ||
            item.slug === 'ikatan-paket-soal' ||
            item.slug === 'grup-kurva' ||
            item.slug === 'relasi-organisasi' ||
            props.daftar_master.includes(item.slug),
    );
});

const activeDefinition = computed(
    () =>
        masterDefinitions.find((item) => item.slug === activeMaster.value) ||
        (activeMaster.value === 'butir-soal'
            ? {
                  slug: 'butir-soal',
                  label: 'Butir Soal',
                  short: 'Pemetaan soal',
                  description: 'Pemetaan butir soal pada paket dalam satu siklus.',
                  headerTitle: 'Butir Soal',
                  headerIcon: 'list-check',
                  headerSentiment: 'brand',
                  icon: 'list-check',
                  sentiment: 'brand',
                  color: 'brand',
                  scopeLabel: 'PAKET',
              }
            : null),
);
const halaman = computed(() => Number(activeMeta.value?.halaman || 1));
const tableColumns = computed(() => {
    const definitions = tableDefinitions[activeMaster.value] || [];
    const visible = rows.value.length
        ? definitions.filter((column) =>
              rows.value.some((row) => Object.prototype.hasOwnProperty.call(row, column.key)),
          )
        : definitions;
    return [...visible, { key: 'aksi', label: 'Aksi', align: 'right' }];
});
const tableRowKey = computed(() => {
    const keys = {
        'jenis-kompetensi': 'Id_PA_Competency_Type',
        kompetensi: 'Id_PA_Competency',
        'kategori-penilai': 'Id_PA_Rater_Category',
        'aturan-penilai': 'Id_PA_Rater_Policy',
        'bobot-pilar': 'Id_PA_Pillar_Weight',
        'pita-nilai': 'Id_PA_Score_Range',
        'kuadran-hav': 'Id_PA_Hav_Quadrant',
        'paket-soal': 'Id_PA_Cycle_Question_Package',
        'butir-soal': 'Id_PA_Cycle_Question_Package_Item',
    };
    return keys[activeMaster.value] || 'id';
});
const needsLevel = computed(() => ['aturan-penilai', 'bobot-pilar'].includes(activeMaster.value));
const needsAxis = computed(() => activeMaster.value === 'pita-nilai');
const needsUse = computed(() => activeMaster.value === 'pita-nilai');
const needsCycle = computed(() => ['paket-soal', 'butir-soal'].includes(activeMaster.value));
const needsPackage = computed(() => activeMaster.value === 'butir-soal');
const supportsStatusFilter = computed(() => !['bobot-pilar', 'kuadran-hav'].includes(activeMaster.value));
const statusQuickChips = computed(() => {
    if (!supportsStatusFilter.value) return [];
    return [
        { value: '', label: 'Semua', icon: 'list-ul', color: 'brand' },
        { value: 'Y', label: 'Aktif', icon: 'check-circle', color: 'success' },
        { value: 'T', label: 'Nonaktif', icon: 'slash-circle', color: 'warning' },
    ];
});
const statusChipValue = computed(() => (supportsStatusFilter.value ? filters.flag_aktif : null));
const filterBarDefinitions = computed(() => {
    const definitions = [];
    if (supportsStatusFilter.value) {
        definitions.push({
            key: 'flag_aktif',
            label: 'Status',
            type: 'select',
            icon: 'patch-check',
            placeholder: 'Semua status',
            options: [
                { value: 'Y', label: 'Aktif' },
                { value: 'T', label: 'Nonaktif' },
            ],
        });
    }
    if (needsLevel.value) {
        definitions.push({
            key: 'id_level',
            label: 'Level',
            type: 'select',
            icon: 'diagram-3',
            placeholder: 'Semua level',
            options: (references.value.level || []).map((item) => ({
                value: item.ID_Level,
                label: item.Nama_Level,
            })),
        });
    }
    if (needsAxis.value) {
        definitions.push({
            key: 'sumbu',
            label: 'Tipe Penilaian',
            type: 'select',
            icon: 'bullseye',
            placeholder: 'Semua tipe penilaian',
            options: (references.value.sumbu || []).map((item) => ({
                value: item.Kode_Type,
                label: item.Nama_Type,
            })),
        });
        definitions.push({
            key: 'kegunaan',
            label: 'Score_Usage',
            type: 'select',
            icon: 'tags',
            placeholder: 'Semua kegunaan',
            options: (references.value.kegunaan || []).map((item) => ({
                value: item.Kode_Usage,
                label: item.Nama_Usage,
            })),
        });
    }
    if (needsCycle.value) {
        definitions.push({
            key: 'cycle_id',
            label: 'Cycle',
            type: 'select',
            icon: 'calendar3',
            placeholder: 'Semua cycle',
            options: (references.value.siklus || []).map((item) => ({
                value: item.Id_PA_Cycle,
                label: `${item.Kode_Cycle} · ${item.Nama_Cycle}`,
            })),
        });
    }
    if (needsPackage.value) {
        definitions.push({
            key: 'package_id',
            label: 'Paket',
            type: 'select',
            icon: 'collection',
            placeholder: 'Semua paket',
            options: packageOptions.value.map((item) => ({
                value: item.Id_PA_Cycle_Question_Package,
                label: `${item.Id_Master_Ujian} · ${item.Nama_Ujian_Sumber}`,
            })),
        });
    }
    return definitions;
});
const filterBarValues = computed(() =>
    filterBarDefinitions.value.reduce((values, filter) => {
        values[filter.key] = filters[filter.key];
        return values;
    }, {}),
);
const activeFilterTags = computed(() => {
    const candidates = [
        { key: 'q', prefix: 'Cari', value: filters.q },
        { key: 'id_level', prefix: 'Level', value: filters.id_level },
        { key: 'sumbu', prefix: 'Score_Type', value: filters.sumbu },
        { key: 'kegunaan', prefix: 'Score_Usage', value: filters.kegunaan },
        { key: 'cycle_id', prefix: 'Cycle', value: filters.cycle_id },
        { key: 'package_id', prefix: 'Paket', value: filters.package_id },
    ];
    return candidates
        .filter((item) => item.value !== '' && item.value !== null && item.value !== undefined)
        .map((item) => ({ ...item, label: `${item.prefix}: ${filterValueLabel(item.key, item.value)}` }));
});
const selectedRecord = computed(
    () => rows.value.find((row) => String(rowId(row)) === String(selectedId.value)) || null,
);
const hasStatusColumn = computed(() =>
    rows.value.some((row) => Object.prototype.hasOwnProperty.call(row, 'Flag_Aktif')),
);
const activeOnPage = computed(() => rows.value.filter((row) => row.Flag_Aktif === 'Y').length);
const detailTabs = [
    { key: 'ringkasan', label: 'Ringkasan', icon: 'file-earmark-text' },
    { key: 'metadata', label: 'Metadata', icon: 'list-columns' },
];
const selectedDetailFields = computed(() => {
    if (!selectedRecord.value) return [];
    return Object.keys(selectedRecord.value)
        .filter((key) => !/^Id_/.test(key))
        .map((key) => ({ key, label: labelForKey(key) }));
});
const editorFields = computed(() =>
    (fieldDefinitions[activeMaster.value] || []).map((field) => ({
        ...field,
        options:
            field.optionsKey === 'paket'
                ? packageOptions.value.map((item) => ({
                      value: item.Id_PA_Cycle_Question_Package,
                      label: `${item.Id_Master_Ujian} · ${item.Nama_Ujian_Sumber}`,
                  }))
                : field.optionsKey
                  ? (references.value[field.optionsKey] || []).map((item) =>
                        optionFromReference(field.optionsKey, item),
                    )
                  : field.options,
    })),
);

function optionFromReference(key, item) {
    const maps = {
        level: ['ID_Level', 'Nama_Level'],
        jenis_kompetensi: ['Id_PA_Competency_Type', 'Nama_Type'],
        sumbu: ['Kode_Type', 'Nama_Type'],
        kegunaan: ['Kode_Usage', 'Nama_Usage'],
        siklus: ['Id_PA_Cycle', 'Kode_Cycle'],
        kompetensi: ['Id_PA_Competency', 'Kode_Competency'],
    };
    const pair = maps[key];
    return {
        value: item[pair?.[0]],
        label: key === 'siklus' ? `${item[pair[1]]} · ${item.Nama_Cycle}` : item[pair?.[1]],
    };
}
function rowId(row) {
    return Object.entries(row).find(([key]) => /^Id_/.test(key))?.[1] || JSON.stringify(row);
}
function formatAngka(value) {
    return Number(value || 0).toLocaleString('id-ID');
}
function displayValue(value) {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'number') return value.toLocaleString('id-ID');
    return String(value);
}
function rowTitle(row) {
    return (
        row?.Nama_Competency ||
        row?.Nama_Type ||
        row?.Nama_Kategori ||
        row?.Nama_Policy ||
        row?.Nama_Range ||
        row?.Nama_Kuadran ||
        row?.Nama_Ujian_Sumber ||
        row?.Nama_Bagian_Sumber ||
        row?.Kode_Competency ||
        row?.Kode_Type ||
        row?.Kode_Policy ||
        row?.Kode_Range ||
        'Data PA'
    );
}
function rowCode(row) {
    return (
        row?.Kode_Competency ||
        row?.Kode_Type ||
        row?.Kode_Kategori ||
        row?.Kode_Policy ||
        row?.Kode_Range ||
        row?.Kode_Kuadran ||
        row?.Id_Master_Ujian ||
        row?.Id_Jenis_Soal ||
        `ID ${rowId(row)}`
    );
}
function rowStatus(row) {
    if (row?.Status_Policy) return row.Status_Policy;
    if (row?.Flag_Aktif === 'Y') return 'ACTIVE';
    if (row?.Flag_Aktif === 'T') return 'INACTIVE';
    return 'MASTER';
}
function labelForKey(key) {
    const labels = {
        PA_Competency_Type_Id: 'Jenis kompetensi',
        PA_Cycle_Id: 'Cycle',
        PA_Cycle_Question_Package_Id: 'Paket soal',
        PA_Competency_Id: 'Kompetensi',
        ID_Level: 'Level',
        Flag_Aktif: 'Status aktif',
        Flag_Berskor: 'Berskor',
        Flag_Radar: 'Tampil di radar',
        Nilai_Ambang: 'Ambang nilai (KKM)',
        Expected_Behaviour: 'Expected behaviour',
        Flag_Izinkan_Manual: 'Izinkan manual',
        Organization_Relationship_Type_Id: 'Relasi organisasi',
        Status_Policy: 'Status policy',
        Catatan_Versi: 'Catatan versi',
        Sumber_Kontrak: 'Sumber kontrak',
        Bobot_Persen: 'Bobot persen',
        Bobot_KPI: 'Bobot KPI',
        Bobot_Kompetensi: 'Bobot kompetensi',
        Nilai_Dari: 'Nilai dari',
        Nilai_Sampai: 'Nilai sampai',
        Nilai_Hasil: 'Nilai hasil',
        Target_Persen: 'Target persen',
        Urutan_Tampil: 'Urutan tampil',
        Skor_Maksimal: 'Skor maksimal',
        Bobot_Penilaian: 'Bobot penilaian',
    };
    if (labels[key]) return labels[key];
    return key
        .replace(/^PA_/, '')
        .replace(/^ID_/, '')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
function selectRow(row) {
    selectedId.value = rowId(row);
    detailTab.value = 'ringkasan';
    mobileTab.value = 'detail';
}
function openDetail(row) {
    selectRow(row);
    detailOpen.value = true;
}
function editFromDetail(row) {
    detailOpen.value = false;
    openEditor(row);
}
function deactivateFromDetail(row) {
    detailOpen.value = false;
    openDeactivate(row);
}
function policyFromDetail(row) {
    detailOpen.value = false;
    openPolicy(row);
}
function packageFromDetail(row) {
    detailOpen.value = false;
    openPackage(row);
}
function filterValueLabel(key, value) {
    const collections = {
        id_level: [references.value.level, 'ID_Level', 'Nama_Level'],
        cycle_id: [references.value.siklus, 'Id_PA_Cycle', 'Nama_Cycle'],
        package_id: [packageOptions.value, 'Id_PA_Cycle_Question_Package', 'Nama_Ujian_Sumber'],
    };
    const collection = collections[key];
    const found = collection?.[0]?.find((item) => String(item[collection[1]]) === String(value));
    return found ? found[collection[2]] : String(value);
}
function ubahPencarian(value) {
    filters.q = value || '';
    loadMaster(1);
}
function ubahStatusChip(chip) {
    filters.flag_aktif = chip?.value || '';
    loadMaster(1);
}
async function ubahFilterBar({ key, value }) {
    filters[key] = value || '';
    if (key === 'cycle_id') {
        filters.package_id = '';
        await loadPackages();
    }
    await loadMaster(1);
}
async function resetFilters() {
    filters.q = '';
    filters.flag_aktif = '';
    filters.id_level = '';
    filters.sumbu = '';
    filters.kegunaan = '';
    filters.cycle_id = '';
    filters.package_id = '';
    packageOptions.value = [];
    await loadMaster(1);
}
async function clearFilter(key) {
    filters[key] = '';
    if (key === 'cycle_id') {
        filters.package_id = '';
        await ubahSiklus();
        return;
    }
    await loadMaster(1);
}
function scopeLabel(scope) {
    return scope === 'siklus' ? 'cycle' : scope === 'paket' ? 'paket soal' : 'master global';
}
function notify(type, message) {
    notice.type = type;
    notice.message = message;
}
function queryForMaster(page = 1) {
    const query = { q: filters.q, flag_aktif: filters.flag_aktif, halaman: page, per_halaman: filters.per_halaman };
    if (needsLevel.value) query.id_level = filters.id_level;
    if (needsAxis.value) query.sumbu = filters.sumbu;
    if (needsUse.value) query.kegunaan = filters.kegunaan;
    if (needsCycle.value) query.cycle_id = filters.cycle_id;
    if (needsPackage.value) query.package_id = filters.package_id;
    return query;
}

async function loadReferences() {
    try {
        references.value = (await paApi.masterReferensi()) || {};
    } catch (error) {
        notify('error', error?.message || 'Referensi master tidak dapat dimuat.');
    }
}
async function segarkanMaster() {
    await loadReferences();
    if (needsCycle.value) await loadPackages();
    await loadMaster(halaman.value);
}
async function loadMaster(page = 1) {
    if (!activeMaster.value) return;
    if (needsCycle.value && !filters.cycle_id) {
        rows.value = [];
        activeMeta.value = null;
        selectedId.value = null;
        detailOpen.value = false;
        return;
    }
    if (needsPackage.value && !filters.package_id) {
        rows.value = [];
        activeMeta.value = null;
        selectedId.value = null;
        detailOpen.value = false;
        return;
    }
    loadingMaster.value = true;
    errorMaster.value = '';
    errorMasterStatus.value = 0;
    filters.halaman = page;
    try {
        const data = await paApi.masterDaftar(activeMaster.value, queryForMaster(page));
        activeMeta.value = data || null;
        rows.value = data?.baris || [];
        if (selectedId.value && !rows.value.some((row) => String(rowId(row)) === String(selectedId.value))) {
            selectedId.value = null;
            detailOpen.value = false;
        }
    } catch (error) {
        errorMaster.value = error?.message || 'Data master tidak dapat dimuat.';
        errorMasterStatus.value = Number(error?.status || 0);
    } finally {
        loadingMaster.value = false;
    }
}
async function loadPackages() {
    packageOptions.value = [];
    if (!filters.cycle_id) return;
    try {
        const data = await paApi.masterDaftar('paket-soal', { cycle_id: filters.cycle_id, per_halaman: 200 });
        packageOptions.value = data?.baris || [];
    } catch (error) {
        notify('error', error?.message || 'Paket soal tidak dapat dimuat.');
    }
}
async function ubahSiklus() {
    filters.package_id = '';
    await loadPackages();
    await loadMaster(1);
}
async function gantiMaster(slug) {
    activeMaster.value = slug;
    selectedId.value = null;
    detailOpen.value = false;
    detailTab.value = 'ringkasan';
    mobileTab.value = 'daftar';
    filters.q = '';
    filters.flag_aktif = '';
    filters.id_level = '';
    filters.sumbu = '';
    filters.kegunaan = '';
    filters.package_id = '';
    if (slug === 'butir-soal') await loadPackages();
    await loadMaster(1);
}
function openEditor(row = null) {
    if (activeMaster.value === 'bobot-pilar') {
        openBobotPilarEditor(row);
        return;
    }
    if (activeMaster.value === 'pita-nilai') {
        openPitaNilaiEditor(row);
        return;
    }
    if (activeMaster.value === 'aturan-penilai') {
        openAturanPenilaiEditor(row, false);
        return;
    }
    editorRecord.value = row ? { ...row } : null;
    editorError.value = '';
    editorOpen.value = true;
}

function openAturanPenilaiEditor(record = null, isNewVersion = false, defaultLevel = '') {
    aturanPenilaiRecord.value = record ? { ...record } : null;
    aturanPenilaiIsNewVersion.value = Boolean(isNewVersion);
    aturanPenilaiDefaultLevel.value = defaultLevel ? String(defaultLevel) : '';
    aturanPenilaiError.value = '';
    aturanPenilaiEditorOpen.value = true;
}

async function saveAturanPenilaiHeader(payload) {
    if (savingAturanPenilai.value) return;
    savingAturanPenilai.value = true;
    aturanPenilaiError.value = '';
    try {
        const id = aturanPenilaiRecord.value && !aturanPenilaiIsNewVersion.value
            ? rowId(aturanPenilaiRecord.value)
            : null;
        const headerData = {
            id_level: payload.id_level,
            kode_policy: payload.kode_policy,
            nama_policy: payload.nama_policy,
            berlaku_dari: payload.berlaku_dari,
            berlaku_sampai: payload.berlaku_sampai,
            deskripsi: payload.deskripsi,
            catatan_versi: payload.catatan_versi,
        };

        let result;
        if (id) {
            result = await paApi.masterPerbarui('aturan-penilai', id, headerData);
        } else {
            result = await paApi.masterSimpan('aturan-penilai', headerData);
        }

        const policyId = id || result?.id;

        // Save rincian if provided
        if (policyId && payload.rincian?.length) {
            await paApi.simpanRincianAturan(policyId, payload.rincian);
        }

        // Activate if requested and total is 100%
        if (policyId && payload.aktifkan_sekarang) {
            await paApi.aktifkanAturan(policyId);
        }

        aturanPenilaiEditorOpen.value = false;
        notify(
            'success',
            `Aturan penilai ${payload.kode_policy} berhasil ${id ? 'diperbarui' : 'dibuat'}${payload.aktifkan_sekarang ? ' dan diaktifkan' : ''}.`,
        );
        await loadMaster(halaman.value);
    } catch (error) {
        aturanPenilaiError.value = error?.message || 'Aturan penilai gagal disimpan.';
    } finally {
        savingAturanPenilai.value = false;
    }
}

function openBobotPilarEditor(record = null, levelObj = null) {
    if (record) {
        bobotPilarRecord.value = { ...record };
    } else if (levelObj) {
        bobotPilarRecord.value = {
            ID_Level: levelObj.ID_Level,
            Bobot_KPI: 80,
            Bobot_Kompetensi: 20,
            Catatan: '',
        };
    } else {
        bobotPilarRecord.value = null;
    }
    bobotPilarError.value = '';
    bobotPilarEditorOpen.value = true;
}

async function saveBobotPilar(payload) {
    if (savingBobotPilar.value) return;
    savingBobotPilar.value = true;
    bobotPilarError.value = '';
    try {
        const matchingRow = rows.value.find((r) => Number(r.ID_Level) === Number(payload.id_level));
        const id = matchingRow
            ? rowId(matchingRow)
            : (bobotPilarRecord.value && Number(bobotPilarRecord.value.ID_Level) === Number(payload.id_level)
                ? rowId(bobotPilarRecord.value)
                : null);
        if (id) {
            await paApi.masterPerbarui('bobot-pilar', id, payload);
        } else {
            await paApi.masterSimpan('bobot-pilar', payload);
        }
        bobotPilarEditorOpen.value = false;
        notify('success', 'Bobot KPI & Kompetensi berhasil disimpan.');
        await loadMaster(halaman.value);
    } catch (error) {
        bobotPilarError.value = error?.message || 'Bobot KPI & Kompetensi gagal disimpan.';
    } finally {
        savingBobotPilar.value = false;
    }
}

function openPitaNilaiEditor(record = null, presetType = 'FINAL', presetUsage = 'GRADE') {
    pitaNilaiRecord.value = record ? { ...record } : null;
    pitaNilaiDefaultType.value = record?.Score_Type || presetType || 'FINAL';
    pitaNilaiDefaultUsage.value = record?.Score_Usage || presetUsage || 'GRADE';
    pitaNilaiError.value = '';
    pitaNilaiEditorOpen.value = true;
}

async function savePitaNilai(payload) {
    if (savingPitaNilai.value) return;
    savingPitaNilai.value = true;
    pitaNilaiError.value = '';
    try {
        const id = pitaNilaiRecord.value ? rowId(pitaNilaiRecord.value) : null;
        const hasil = id
            ? await paApi.masterPerbarui('pita-nilai', id, payload)
            : await paApi.masterSimpan('pita-nilai', payload);
        pitaNilaiEditorOpen.value = false;
        const tetangga = hasil?.tetangga_disesuaikan || [];
        notify('success', tetangga.length
            ? `Rentang nilai berhasil disimpan. Batas pita ${tetangga.join(' dan ')} ikut disesuaikan supaya tetap bersambung.`
            : 'Rentang nilai berhasil disimpan.');
        await loadMaster(halaman.value);
    } catch (error) {
        pitaNilaiError.value = error?.message || 'Rentang nilai gagal disimpan.';
    } finally {
        savingPitaNilai.value = false;
    }
}
function payloadFromValues(values) {
    return (fieldDefinitions[activeMaster.value] || []).reduce((payload, field) => {
        let value = values[field.key];
        if (value === '') value = null;
        if (value !== null && value !== undefined && field.type === 'number') value = Number(value);
        payload[field.payload] = value;
        return payload;
    }, {});
}
async function saveMaster(values) {
    if (saving.value) return;
    saving.value = true;
    editorError.value = '';
    try {
        const payload = payloadFromValues(values);
        if (activeMaster.value === 'kompetensi' && !('flag_berskor' in payload)) {
            payload.flag_berskor = editorRecord.value?.Flag_Berskor || 'Y';
        }
        /* Butir kualitatif tidak punya angka, jadi tidak bisa jadi sumbu radar dan tidak bisa
           punya ambang. Server menolaknya, dan penolakan itu benar — tetapi formulir ini
           mengirim 'Y' sebagai baku, sehingga menyunting butir kualitatif untuk alasan lain
           (memperbaiki deskripsinya, misalnya) akan mentok di galat yang bukan salah HC.
           Nilainya diselaraskan di sini; penjaga di server tetap yang berwenang. */
        if (activeMaster.value === 'kompetensi' && payload.flag_berskor === 'T') {
            payload.flag_radar = 'T';
            payload.nilai_ambang = null;
        }
        const id = editorRecord.value ? rowId(editorRecord.value) : null;
        const result = id
            ? await paApi.masterPerbarui(activeMaster.value, id, payload)
            : await paApi.masterSimpan(activeMaster.value, payload);
        editorOpen.value = false;
        notify(
            'success',
            `${activeMeta.value?.nama || activeDefinition.value?.label} berhasil ${id ? 'diperbarui' : 'ditambahkan'}.`,
        );
        await loadMaster(halaman.value);
    } catch (error) {
        editorError.value = error?.message || 'Perubahan tidak dapat disimpan.';
    } finally {
        saving.value = false;
    }
}
async function openPolicy(row) {
    if (!row) return;
    policyRecord.value = row;
    policyOpen.value = true;
    loadingDetail.value = true;
    detailError.value = '';
    policyActionError.value = '';
    try {
        const data = await paApi.masterDetail('aturan-penilai', rowId(row));
        policyRecord.value = data?.baris || row;
        policyDetail.value = data?.detail || [];
    } catch (error) {
        detailError.value = error?.message || 'Rincian aturan tidak dapat dimuat.';
    } finally {
        loadingDetail.value = false;
    }
}
async function savePolicyDetail(detail) {
    if (!policyRecord.value || savingPolicy.value || activatingPolicy.value) return;
    savingPolicy.value = true;
    policyActionError.value = '';
    try {
        const payload = detail.map((row) => ({
            pa_rater_category_id: Number(row.PA_Rater_Category_Id),
            bobot_persen: Number(row.Bobot_Persen),
            min_rater: row.Min_Rater === '' ? null : Number(row.Min_Rater),
            maks_rater: row.Maks_Rater === '' ? null : Number(row.Maks_Rater),
            min_rater_selesai: row.Min_Rater_Selesai === '' ? null : Number(row.Min_Rater_Selesai),
            organization_relationship_type_id:
                row.Organization_Relationship_Type_Id === '' ? null : Number(row.Organization_Relationship_Type_Id),
            flag_izinkan_manual: row.Flag_Izinkan_Manual,
            urutan_tampil: row.Urutan_Tampil === '' ? null : Number(row.Urutan_Tampil),
            catatan_aturan: row.Catatan_Aturan || null,
        }));
        await paApi.simpanRincianAturan(rowId(policyRecord.value), payload);
        notify('success', 'Rincian aturan berhasil disimpan.');
        await openPolicy(policyRecord.value);
    } catch (error) {
        policyActionError.value = error?.message || 'Rincian aturan tidak dapat disimpan.';
    } finally {
        savingPolicy.value = false;
    }
}
async function activatePolicy(row = null) {
    const target = row || policyRecord.value;
    if (!target || savingPolicy.value || activatingPolicy.value) return;
    activatingPolicy.value = true;
    policyActionError.value = '';
    try {
        await paApi.aktifkanAturan(rowId(target));
        notify('success', `Versi aturan ${target.Kode_Policy || ''} berhasil diaktifkan.`);
        if (policyOpen.value && policyRecord.value && rowId(policyRecord.value) === rowId(target)) {
            await openPolicy(policyRecord.value);
        }
        await loadMaster(halaman.value);
    } catch (error) {
        if (policyOpen.value) {
            policyActionError.value = error?.message || 'Versi aturan tidak dapat diaktifkan.';
        } else {
            notify('error', error?.message || 'Versi aturan tidak dapat diaktifkan.');
        }
    } finally {
        activatingPolicy.value = false;
    }
}
async function openPackage(row) {
    packageRecord.value = row;
    packageOpen.value = true;
    loadingDetail.value = true;
    detailError.value = '';
    try {
        const data = await paApi.masterDetail('paket-soal', rowId(row));
        packageRecord.value = data?.baris || row;
        packageItems.value = data?.butir || [];
    } catch (error) {
        packageItems.value = [];
        notify('error', error?.message || 'Butir soal tidak dapat dimuat.');
    } finally {
        loadingDetail.value = false;
    }
}
async function bukaButirDariPaket() {
    packageOpen.value = false;
    filters.package_id = rowId(packageRecord.value);
    activeMaster.value = 'butir-soal';
    await loadMaster(1);
}
function openDeactivate(row) {
    deactivateRecord.value = row;
    deactivateOpen.value = true;
}
/* Tombol yang sama dipakai dua arah: baris nonaktif ditawari "Aktifkan". Dulu keduanya
   memanggil nonaktifkan, sehingga mengaktifkan kembali selalu ditolak "sudah nonaktif". */
const akanDiaktifkan = computed(() => Boolean(deactivateRecord.value) && deactivateRecord.value.Flag_Aktif !== 'Y');
async function deactivate() {
    if (!deactivateRecord.value || deactivating.value) return;
    deactivating.value = true;
    const aktifkan = akanDiaktifkan.value;
    try {
        if (aktifkan) {
            const hasil = await paApi.masterAktif(activeMaster.value, rowId(deactivateRecord.value));
            const peringatan = (hasil?.peringatan || []).join(' ');
            notify(peringatan ? 'warning' : 'success', peringatan
                ? `Data berhasil diaktifkan kembali. ${peringatan}`
                : 'Data berhasil diaktifkan kembali.');
        } else {
            await paApi.masterNonaktif(activeMaster.value, rowId(deactivateRecord.value));
            notify('success', 'Data berhasil dinonaktifkan.');
        }
        deactivateOpen.value = false;
        await loadMaster(halaman.value);
    } catch (error) {
        notify('error', error?.message || (aktifkan ? 'Data tidak dapat diaktifkan kembali.' : 'Data tidak dapat dinonaktifkan.'));
        // Keadaan di layar bisa basi (mis. sudah diubah di tab lain): muat ulang supaya tombolnya benar.
        await loadMaster(halaman.value);
    } finally {
        deactivating.value = false;
    }
}
watch(
    () => props.master,
    async (newVal) => {
        if (newVal && newVal !== activeMaster.value) {
            activeMaster.value = newVal;
            if (activeMaster.value === 'butir-soal') await loadPackages();
            await loadMaster(1);
        }
    },
);

const isMobile = ref(typeof window !== 'undefined' ? window.innerWidth < 768 : false);

function cekUkuranLayar() {
    if (typeof window === 'undefined') return;
    isMobile.value = window.innerWidth < 768;
}

onMounted(async () => {
    cekUkuranLayar();
    window.addEventListener('resize', cekUkuranLayar, { passive: true });
    await loadReferences();
    if (activeMaster.value === 'butir-soal') await loadPackages();
    if (activeMaster.value) await loadMaster(1);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', cekUkuranLayar);
});
</script>

<style scoped>
.pa-perilaku-chip { display: inline-flex; align-items: center; padding: 0.15rem 0.55rem; border-radius: 999px; background: #ccfbf1; color: #115e59; font-size: 0.72rem; font-weight: 700; white-space: nowrap; }
.pa-perilaku-chip.is-kosong { background: #f1f5f9; color: #94a3b8; font-weight: 600; }

.pa-admin-page {
    --pa-ink: var(--hcis-text-strong, #0f172a);
    --pa-muted: var(--hcis-text-muted, #64748b);
    min-width: 0;
    padding-bottom: 2rem;
}
.pa-admin-switcher {
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
.pa-admin-switcher::-webkit-scrollbar {
    display: none;
}
.pa-admin-switcher__item {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.4rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    color: var(--pa-muted, #64748b);
    font-size: 0.76rem;
    font-weight: 500;
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.pa-admin-switcher__item:hover {
    color: #4338ca;
    background: rgba(255, 255, 255, 0.8);
}
.pa-admin-switcher__item--active {
    color: #4338ca;
    background: #ffffff;
    font-weight: 700;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(99, 102, 241, 0.05);
}
.pa-eyebrow {
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 0.64rem;
    letter-spacing: 0.1em;
    font-weight: 800;
}
.pa-admin-content {
    min-width: 0;
}
.pa-landing-hero {
    margin-bottom: 1.25rem;
}
.pa-landing-hero__content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
}
.pa-landing-hero__text {
    flex: 1;
    min-width: 0;
}
.pa-landing-hero__tags {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}
.pa-landing-hero__title {
    margin: 0 0 0.45rem;
    color: var(--pa-ink, #0f172a);
    font-size: 1.35rem;
    font-weight: 750;
    line-height: 1.3;
}
.pa-landing-hero__desc {
    margin: 0;
    color: var(--pa-muted, #475569);
    font-size: 0.82rem;
    line-height: 1.55;
    max-width: 52rem;
}
.pa-landing-hero__graphic {
    flex-shrink: 0;
}
.pa-landing-hero__icon-circle {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 4rem;
    height: 4rem;
    border-radius: var(--hcis-radius-full, 9999px);
    background: rgba(79, 70, 229, 0.1);
    border: 1px solid rgba(79, 70, 229, 0.2);
}
.pa-admin-cards-section {
    display: flex;
    flex-direction: column;
}
.pa-admin-cards-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
}
.pa-admin-cards-title {
    margin: 0.15rem 0 0;
    color: var(--pa-ink, #0f172a);
    font-size: 1.1rem;
    font-weight: 700;
}
.pa-admin-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
}
.pa-admin-card-link {
    color: inherit;
    text-decoration: none;
    display: block;
    height: 100%;
}
.pa-admin-card {
    height: 100%;
    display: flex;
    flex-direction: column;
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.pa-admin-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
}
.pa-admin-card__heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    margin-bottom: 0.4rem;
}
.pa-admin-card__icon-box {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: var(--hcis-radius-md, 8px);
}
.pa-admin-card-icon--brand {
    background: rgba(79, 70, 229, 0.12);
    color: #4f46e5;
}
.pa-admin-card-icon--purple {
    background: rgba(139, 92, 246, 0.12);
    color: #7c3aed;
}
.pa-admin-card-icon--teal {
    background: rgba(13, 148, 136, 0.12);
    color: #0d9488;
}
.pa-admin-card-icon--success {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}
.pa-admin-card-icon--warning {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
}
.pa-admin-card__title {
    margin: 0;
    color: var(--pa-ink, #0f172a);
    font-size: 0.95rem;
    font-weight: 700;
}
.pa-admin-card__desc {
    margin: 0.35rem 0 0.75rem;
    color: var(--pa-muted, #64748b);
    font-size: 0.78rem;
    line-height: 1.5;
    flex: 1;
}
.pa-admin-card__footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-top: 0.65rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.85);
}
.pa-admin-card__cta {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: #4f46e5;
    font-size: 0.74rem;
    font-weight: 700;
    transition: transform 0.15s ease;
}
.pa-admin-card:hover .pa-admin-card__cta {
    transform: translateX(2px);
}
.pa-child-switcher {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
    padding: 0.35rem;
    margin-bottom: 0.75rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(248, 250, 252, 0.7);
}
.pa-child-switcher button {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.45rem 0.65rem;
    color: var(--pa-muted);
    border: 0;
    border-radius: var(--hcis-radius-md, 8px);
    background: transparent;
    font: inherit;
    font-size: 0.74rem;
    cursor: pointer;
}
.pa-child-switcher button.active {
    color: #4338ca;
    background: #fff;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
    font-weight: 700;
}
.pa-child-switcher span {
    margin-left: auto;
    padding: 0 0.5rem;
    color: var(--pa-muted);
    font-size: 0.68rem;
}
.pa-master-filters {
    display: flex;
    align-items: flex-end;
    gap: 0.55rem;
    flex-wrap: wrap;
    padding: 0.8rem;
    border: 1px solid rgba(99, 102, 241, 0.16);
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.5), rgba(255, 255, 255, 0.7));
}
.pa-master-filters :deep(.hcis-field) {
    flex: 1 1 14rem;
    min-width: 12rem;
}
.pa-select-field {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    min-width: 9rem;
    color: var(--pa-ink);
    font-size: 0.7rem;
    font-weight: 650;
}
.pa-select-field select {
    min-height: 2.375rem;
    padding: 0.45rem 0.6rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-md, 8px);
    color: var(--pa-ink);
    background: rgba(255, 255, 255, 0.88);
    font: inherit;
    font-size: 0.74rem;
}
.pa-master-caption {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    margin: 0.65rem 0;
    color: var(--pa-muted);
    font-size: 0.7rem;
}
.pa-master-caption span {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.pa-warning-text {
    color: #b45309;
}
.pa-master-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    min-height: 14rem;
    color: var(--pa-muted);
    font-size: 0.8rem;
}
.pa-master-table-wrap {
    overflow-x: auto;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-lg, 12px);
}
.pa-master-table {
    width: 100%;
    min-width: 720px;
    border-collapse: collapse;
    background: rgba(255, 255, 255, 0.8);
}
.pa-master-table th {
    padding: 0.7rem 0.75rem;
    color: var(--pa-muted);
    background: rgba(248, 250, 252, 0.86);
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-align: left;
    text-transform: uppercase;
    white-space: nowrap;
}
.pa-master-table td {
    padding: 0.7rem 0.75rem;
    border-top: 1px solid rgba(226, 232, 240, 0.78);
    color: var(--pa-ink);
    font-size: 0.75rem;
    vertical-align: top;
}
.pa-cell {
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
    min-width: 0;
}
.pa-cell strong {
    color: var(--pa-ink);
    font-size: 0.78rem;
}
.pa-cell span {
    color: var(--pa-muted);
}
.pa-cell small {
    max-width: 18rem;
    overflow: hidden;
    color: var(--pa-muted);
    font-size: 0.67rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pa-row-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.3rem;
    flex-wrap: wrap;
}
.pa-align-right {
    text-align: right !important;
    white-space: nowrap;
}
.pa-master-pagination {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.65rem;
    margin-top: 0.8rem;
    color: var(--pa-muted);
    font-size: 0.75rem;
}
.pa-package-detail {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}
.pa-package-detail__identity {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 0.8rem;
    border: 1px solid rgba(13, 148, 136, 0.22);
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(240, 253, 250, 0.72);
}
.pa-package-detail__identity span {
    color: #0f766e;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.08em;
}
.pa-package-detail__identity strong {
    color: var(--pa-ink);
    font-size: 0.78rem;
    font-weight: 500;
}
.pa-package-items {
    display: grid;
    gap: 0.5rem;
}
.pa-package-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.7rem;
    padding: 0.65rem 0.75rem;
    border: 1px solid rgba(226, 232, 240, 0.84);
    border-radius: var(--hcis-radius-md, 8px);
}
.pa-package-item > div {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}
.pa-package-item strong {
    color: var(--pa-ink);
    font-size: 0.78rem;
}
.pa-package-item span {
    color: var(--pa-muted);
    font-size: 0.68rem;
}
.pa-modal-copy {
    color: var(--pa-muted);
    font-size: 0.8rem;
    line-height: 1.45;
}
.pa-deactivate-preview {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.7rem;
    padding: 0.75rem;
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(254, 242, 242, 0.7);
    color: #991b1b;
    font-size: 0.8rem;
}
.pa-notification {
    position: fixed;
    z-index: 50;
    right: 1rem;
    bottom: 1rem;
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    max-width: min(32rem, calc(100vw - 2rem));
    padding: 0.8rem 1rem;
    border: 1px solid;
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: var(--hcis-shadow-lg, 0 10px 15px -3px rgb(0 0 0 / 0.08));
    backdrop-filter: blur(12px);
    font-size: 0.8rem;
}
.pa-notification--success {
    color: #047857;
    border-color: rgba(16, 185, 129, 0.3);
    background: rgba(236, 253, 245, 0.94);
}
.pa-notification--error {
    color: #b91c1c;
    border-color: rgba(239, 68, 68, 0.3);
    background: rgba(254, 242, 242, 0.94);
}
.pa-notification--warning {
    border-color: rgba(217, 119, 6, 0.35);
    background: #fffbeb;
    color: #92400e;
}
.pa-notification span {
    flex: 1;
}
.pa-notification button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.15rem;
    color: inherit;
    border: 0;
    background: transparent;
    cursor: pointer;
}
@media (max-width: 1100px) {
    .pa-admin-cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 760px) {
    .pa-master-filters {
        align-items: stretch;
        flex-direction: column;
    }
    .pa-master-filters :deep(.hcis-field),
    .pa-select-field {
        width: 100%;
        min-width: 0;
    }
    .pa-master-filters :deep(.hcis-btn) {
        width: 100%;
    }
    .pa-child-switcher span {
        width: 100%;
        margin: 0.3rem 0 0;
    }
    .pa-admin-welcome {
        align-items: flex-start;
    }
    .pa-admin-cards {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 640px) {
    .pa-admin-switcher {
        width: 100%;
        margin-top: 0.35rem;
        padding: 0.15rem;
        gap: 0.25rem;
    }
    .pa-admin-switcher__item {
        padding: 0.25rem 0.5rem;
        font-size: 0.7rem;
        gap: 0.3rem;
    }
    .pa-notification {
        right: 0.75rem;
        bottom: 0.75rem;
    }
}

/* Admin PA workspace follows the SOP master-detail rhythm while keeping PA's contract data. */
.pa-filter-field {
    display: flex;
    flex: 1 1 10rem;
    flex-direction: column;
    gap: 0.28rem;
    min-width: 10rem;
}
.pa-filter-field--wide {
    flex-basis: 15rem;
}
.pa-filter-field > label {
    color: var(--pa-ink);
    font-size: 0.68rem;
    font-weight: 750;
}
.pa-filter-field :deep(.hcis-select) {
    min-width: 0;
}
.pa-quick-filters {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
    margin: 0.55rem 0 0.2rem;
    color: var(--pa-muted);
    font-size: 0.68rem;
}
.pa-quick-filters > span {
    margin-right: 0.15rem;
    font-weight: 750;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.pa-quick-filters button {
    padding: 0.28rem 0.55rem;
    color: var(--pa-muted);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 999px;
    background: rgba(248, 250, 252, 0.82);
    font: inherit;
    cursor: pointer;
}
.pa-quick-filters button:hover,
.pa-quick-filters button.active {
    color: #4338ca;
    border-color: rgba(99, 102, 241, 0.28);
    background: rgba(238, 242, 255, 0.88);
    font-weight: 750;
}
.pa-active-tags {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
    margin: 0.45rem 0 0.15rem;
}
.pa-active-tag,
.pa-reset-filters {
    display: inline-flex;
    align-items: center;
    gap: 0.32rem;
    padding: 0.28rem 0.5rem;
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 999px;
    background: rgba(238, 242, 255, 0.72);
    color: #4338ca;
    font-size: 0.67rem;
    font-weight: 700;
}
.pa-active-tag {
    cursor: pointer;
}
.pa-active-tag:hover,
.pa-active-tag:focus-visible {
    border-color: rgba(99, 102, 241, 0.45);
    outline: none;
}
.pa-reset-filters {
    border-style: dashed;
    background: transparent;
    cursor: pointer;
}
.pa-reset-filters:hover {
    background: rgba(238, 242, 255, 0.72);
}
.pa-master-stats {
    margin: -0.2rem 0 1rem;
}
.pa-mobile-nav {
    margin-bottom: 0.75rem;
}
.pa-segmented-nav {
    display: flex;
    gap: 0.25rem;
    padding: 0.3rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(248, 250, 252, 0.82);
}
.pa-segmented-nav button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    flex: 1;
    min-height: 2.35rem;
    padding: 0.45rem 0.65rem;
    color: var(--pa-muted);
    border: 0;
    border-radius: var(--hcis-radius-md, 8px);
    background: transparent;
    font: inherit;
    font-size: 0.74rem;
    cursor: pointer;
}
.pa-segmented-nav button.active {
    color: #4338ca;
    background: #fff;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
    font-weight: 750;
}
.pa-segmented-nav button:disabled {
    cursor: not-allowed;
    opacity: 0.52;
}
.pa-segmented-nav button span {
    min-width: 1.2rem;
    padding: 0.1rem 0.3rem;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.1);
    font-size: 0.64rem;
}
.pa-master-workspace {
    display: grid;
    grid-template-columns: minmax(18rem, 0.86fr) minmax(24rem, 1.14fr);
    align-items: stretch;
    gap: 1rem;
    margin-top: 0.4rem;
}
.pa-master-list-panel,
.pa-master-detail-panel {
    min-width: 0;
}
.pa-master-list-panel {
    display: flex;
    flex-direction: column;
    min-height: 35rem;
    overflow: hidden;
    border: 1px solid rgba(99, 102, 241, 0.16);
    border-radius: var(--hcis-radius-xl, 16px);
    background: rgba(255, 255, 255, 0.72);
    box-shadow: 0 12px 32px rgba(30, 41, 59, 0.06);
}
.pa-list-panel__controls {
    padding: 0.75rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.9);
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.9), rgba(255, 255, 255, 0.72));
}
.pa-list-panel__controls .pa-master-filters {
    padding: 0;
    border: 0;
    background: transparent;
}
.pa-list-panel__controls .pa-master-caption {
    margin-bottom: 0;
}
.pa-list-panel__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    padding: 0.85rem 0.9rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.9);
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.72), rgba(255, 255, 255, 0.62));
}
.pa-list-panel__header > div {
    display: flex;
    flex-direction: column;
    gap: 0.18rem;
    min-width: 0;
}
.pa-list-panel__header strong {
    overflow: hidden;
    color: var(--pa-ink);
    font-size: 0.86rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pa-master-loaded {
    flex: 1;
    min-height: 0;
}
.pa-master-list-cards {
    display: grid;
    gap: 0.5rem;
    padding: 0.7rem;
}
.pa-master-list-card {
    display: grid;
    grid-template-columns: 2.15rem minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.65rem;
    width: 100%;
    padding: 0.7rem;
    color: inherit;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(255, 255, 255, 0.78);
    font: inherit;
    text-align: left;
    cursor: pointer;
    transition:
        transform 0.18s ease,
        border-color 0.18s ease,
        box-shadow 0.18s ease,
        background 0.18s ease;
}
.pa-master-list-card:hover,
.pa-master-list-card:focus-visible,
.pa-master-list-card--active {
    border-color: rgba(99, 102, 241, 0.4);
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.78), rgba(255, 255, 255, 0.9));
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.1);
    outline: none;
    transform: translateY(-1px);
}
.pa-master-list-card--active {
    box-shadow:
        inset 3px 0 0 #6366f1,
        0 8px 20px rgba(79, 70, 229, 0.1);
}
.pa-master-list-card__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.15rem;
    height: 2.15rem;
    color: #4f46e5;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(99, 102, 241, 0.1);
}
.pa-master-list-card__body {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}
.pa-master-list-card__topline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.45rem;
}
.pa-master-list-card__topline strong {
    overflow: hidden;
    color: var(--pa-ink);
    font-size: 0.78rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pa-master-list-card__body > span {
    overflow: hidden;
    color: #4f46e5;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pa-master-list-card__body small {
    overflow: hidden;
    color: var(--pa-muted);
    font-size: 0.66rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pa-master-table-wrap {
    display: none;
}
.pa-master-detail-panel {
    display: flex;
    min-height: 35rem;
}
.pa-detail-card {
    width: 100%;
}
.pa-detail-hero {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.85);
}
.pa-detail-hero__badges,
.pa-detail-hero__actions {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}
.pa-detail-hero__actions {
    justify-content: flex-end;
}
.pa-detail-hero h3 {
    margin: 0.15rem 0 0;
    color: var(--pa-ink);
    font-size: 1.18rem;
    line-height: 1.3;
}
.pa-detail-hero__code {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: #4f46e5;
    font-family: var(--hcis-font-mono, ui-monospace, monospace);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.05em;
}
.pa-detail-stats {
    margin: 1rem 0;
}
.pa-detail-tabs {
    margin-bottom: 1rem;
}
.pa-detail-section {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}
.pa-detail-section__heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
}
.pa-detail-section__heading h4 {
    margin: 0.2rem 0 0;
    color: var(--pa-ink);
    font-size: 0.9rem;
}
.pa-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.55rem;
}
.pa-detail-field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
    padding: 0.65rem 0.7rem;
    border: 1px solid rgba(226, 232, 240, 0.82);
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(248, 250, 252, 0.65);
}
.pa-detail-field span {
    color: var(--pa-muted);
    font-size: 0.66rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.pa-detail-field strong {
    overflow-wrap: anywhere;
    color: var(--pa-ink);
    font-size: 0.76rem;
    font-weight: 700;
}
.pa-metadata-list {
    display: grid;
    grid-template-columns: minmax(8rem, 0.65fr) minmax(0, 1.35fr);
    gap: 0;
    margin: 0;
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: var(--hcis-radius-md, 8px);
    overflow: hidden;
}
.pa-metadata-list dt,
.pa-metadata-list dd {
    margin: 0;
    padding: 0.58rem 0.65rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.75);
    font-size: 0.72rem;
}
.pa-metadata-list dt {
    color: var(--pa-muted);
    background: rgba(248, 250, 252, 0.75);
    font-weight: 700;
}
.pa-metadata-list dd {
    overflow-wrap: anywhere;
    color: var(--pa-ink);
}
.pa-metadata-list dt:nth-last-of-type(1),
.pa-metadata-list dd:nth-last-of-type(1) {
    border-bottom: 0;
}
.pa-detail-special {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
    margin-top: 1rem;
    padding: 0.75rem;
    border: 1px solid rgba(124, 58, 237, 0.2);
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(245, 243, 255, 0.78), rgba(255, 255, 255, 0.72));
}
.pa-detail-special > div {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    min-width: 0;
}
.pa-detail-special > div > div {
    display: flex;
    flex-direction: column;
    gap: 0.18rem;
}
.pa-detail-special strong {
    color: var(--pa-ink);
    font-size: 0.76rem;
}
.pa-detail-special span {
    color: var(--pa-muted);
    font-size: 0.68rem;
    line-height: 1.4;
}
.pa-detail-back {
    width: 100%;
    margin-bottom: 0.65rem;
}
.pa-master-filter-bar {
    margin-bottom: 1rem;
}
.pa-master-workspace {
    display: block;
    margin-top: 0;
}
.pa-master-list-panel {
    width: 100%;
    min-height: 0;
    overflow: visible;
}
.pa-master-detail-panel {
    display: none !important;
}
.pa-master-loaded {
    flex: initial;
}
.pa-master-list-cards {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.8rem;
    padding: 1rem;
}
.pa-master-list-card {
    display: flex;
    align-items: stretch;
    flex-direction: column;
    min-height: 11.5rem;
    gap: 0.65rem;
    padding: 0.85rem;
}
.pa-master-list-card__icon {
    width: 2.35rem;
    height: 2.35rem;
}
.pa-master-list-card__body {
    flex: 1;
}
.pa-master-list-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    min-width: 0;
    padding-top: 0.65rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.9);
}
.pa-master-list-card__footer > span {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    color: var(--pa-muted);
    font-size: 0.66rem;
    white-space: nowrap;
}
.pa-master-list-card__actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.2rem;
    min-width: 0;
}
.pa-table-primary-cell {
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
    min-width: 10rem;
}
.pa-table-primary-cell strong {
    color: var(--pa-ink);
    font-size: 0.78rem;
}
.pa-table-primary-cell small {
    color: var(--pa-muted);
    font-family: var(--hcis-font-mono, ui-monospace, monospace);
    font-size: 0.67rem;
}
.pa-master-loading {
    display: block;
    min-height: 0;
    padding: 1rem;
}
.pa-master-skeleton-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.8rem;
}
.pa-master-skeleton-card {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    min-height: 10.5rem;
    padding: 0.85rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(255, 255, 255, 0.7);
}
.pa-master-skeleton-card__head {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.pa-master-skeleton-card__head > div {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.pa-master-skeleton-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-top: auto;
    padding-top: 0.65rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.9);
}
.pa-master-skeleton-table {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0.4rem;
}
.pa-master-skeleton-table > div {
    display: grid;
    grid-template-columns: 2fr 1.5fr 0.75fr 0.85fr;
    align-items: center;
    gap: 0.8rem;
    padding: 0.7rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.72);
}
@media (max-width: 991.98px) {
    .pa-master-workspace {
        display: block;
    }
    .pa-master-list-panel,
    .pa-master-detail-panel {
        min-height: 0;
    }
    .pa-mobile-hidden {
        display: none !important;
    }
    .pa-master-list-panel {
        overflow: visible;
    }
}
@media (max-width: 1200px) {
    .pa-master-list-cards {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .pa-master-skeleton-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@media (max-width: 760px) {
    .pa-master-list-cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        padding: 0.75rem;
    }
    .pa-master-skeleton-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 640px) {
    .pa-detail-grid {
        grid-template-columns: 1fr;
    }
    .pa-detail-hero__actions {
        justify-content: flex-start;
    }
    .pa-detail-special {
        align-items: flex-start;
        flex-direction: column;
    }
    .pa-detail-special .hcis-btn {
        align-self: stretch;
    }
    .pa-metadata-list {
        grid-template-columns: 1fr;
    }
    .pa-metadata-list dt {
        padding-bottom: 0.2rem;
        border-bottom: 0;
    }
    .pa-metadata-list dd {
        padding-top: 0.2rem;
    }
    .pa-master-list-cards {
        grid-template-columns: 1fr;
    }
    .pa-master-skeleton-grid {
        grid-template-columns: 1fr;
    }
    .pa-master-skeleton-table > div {
        grid-template-columns: 1.5fr 1fr;
    }
    .pa-master-skeleton-table > div :nth-child(n + 3) {
        display: none;
    }
    .pa-master-list-card__footer {
        align-items: flex-start;
        flex-direction: column;
    }
    .pa-master-list-card__actions {
        width: 100%;
        justify-content: flex-start;
    }
}
</style>
